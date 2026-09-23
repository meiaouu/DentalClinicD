<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByUsernameOrEmail(string $login): ?array
    {
        $login = trim($login);

        if ($login === '') {
            return null;
        }

        /*
            Use u.* so newly added columns like account_status,
            email_verified_at, and password_setup_token_hash are included
            without breaking existing login logic.
        */
        $stmt = $this->db->prepare("
            SELECT
                u.*,
                r.role_name
            FROM users u
            INNER JOIN roles r ON r.role_id = u.role_id
            WHERE u.username = :login OR u.email = :login
            LIMIT 1
        ");

        $stmt->execute([
            ':login' => $login,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $email = trim(strtolower($email));

        if ($email === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM users
            WHERE LOWER(email) = :email
            LIMIT 1
        ");

        $stmt->execute([
            ':email' => $email,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByContactNumber(string $contactNumber): ?array
    {
        $digits = preg_replace('/\D+/', '', trim($contactNumber));

        if ($digits === '') {
            return null;
        }

        $variants = [$digits];

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $variants[] = '63' . substr($digits, 1);
            $variants[] = '+' . '63' . substr($digits, 1);
        } elseif (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $variants[] = '0' . substr($digits, 2);
            $variants[] = '+' . $digits;
        }

        $placeholders = [];
        $params = [];

        foreach (array_values(array_unique($variants)) as $index => $variant) {
            $placeholder = ':contact_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $variant;
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE contact_number IN (' . implode(', ', $placeholders) . ') LIMIT 1'
        );
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $username = trim($username);

        if ($username === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM users
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            ':username' => $username,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (
                role_id,
                first_name,
                middle_name,
                last_name,
                sex,
                birth_date,
                contact_number,
                email,
                username,
                password,
                account_status,
                is_active,
                created_at,
                updated_at
            ) VALUES (
                :role_id,
                :first_name,
                :middle_name,
                :last_name,
                :sex,
                :birth_date,
                :contact_number,
                :email,
                :username,
                :password,
                :account_status,
                :is_active,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            ':role_id' => $data['role_id'],
            ':first_name' => $data['first_name'],
            ':middle_name' => $data['middle_name'] ?? null,
            ':last_name' => $data['last_name'],
            ':sex' => $data['sex'] ?? null,
            ':birth_date' => $data['birth_date'] ?? null,
            ':contact_number' => $data['contact_number'] ?? null,
            ':email' => $data['email'] ?? null,
            ':username' => $data['username'],
            ':password' => $data['password'],
            ':account_status' => $data['account_status'] ?? 'pending_review',
            ':is_active' => $data['is_active'] ?? 1,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                u.*,
                r.role_name
            FROM users u
            LEFT JOIN roles r ON r.role_id = u.role_id
            WHERE u.user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createPendingPatientAccount(array $patient, string $tokenHash, string $expiresAt): int
    {
        $patientId = (int) ($patient['patient_id'] ?? 0);

        if ($patientId <= 0) {
            throw new RuntimeException('Invalid patient record.');
        }

        $email = $this->nullIfBlank($patient['email'] ?? null);
        $contactNumber = $this->nullIfBlank($patient['contact_number'] ?? null);

        if ($email === null && $contactNumber === null) {
            throw new RuntimeException('Patient email or phone number is required for account setup.');
        }

        if ($email !== null && $this->findByEmail($email)) {
            throw new RuntimeException('Patient account already exists.');
        }

        $roleId = $this->findRoleIdByName('patient');

        if ($roleId <= 0) {
            throw new RuntimeException('Patient role was not found.');
        }

        /*
            This is intentionally unusable.
            The patient must set their own password through the setup link.
        */
        $unusablePasswordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        $username = $this->generatePendingPatientUsername($patientId);

        $data = [
            'role_id' => $roleId,
            'first_name' => $this->nullIfBlank($patient['first_name'] ?? null),
            'middle_name' => $this->nullIfBlank($patient['middle_name'] ?? null),
            'last_name' => $this->nullIfBlank($patient['last_name'] ?? null),
            'sex' => $this->nullIfBlank($patient['sex'] ?? null),
            'birth_date' => $this->nullIfBlank($patient['birth_date'] ?? null),
            'contact_number' => $contactNumber,
            'email' => $email,
            'username' => $username,
            'password' => $unusablePasswordHash,
            'is_active' => 0,
            'account_status' => 'pending_verification',
            'email_verified_at' => null,
            'phone_verified_at' => null,
            'password_setup_token_hash' => $tokenHash,
            'password_setup_expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $data = $this->filterUserColumns($data);

        $columns = array_keys($data);
        $columnSql = implode(', ', array_map(fn (string $column): string => "`{$column}`", $columns));
        $valueSql = implode(', ', array_map(fn (string $column): string => ":{$column}", $columns));

        $params = [];

        foreach ($data as $column => $value) {
            $params[":{$column}"] = $value;
        }

        $stmt = $this->db->prepare("
            INSERT INTO users ({$columnSql})
            VALUES ({$valueSql})
        ");

        $stmt->execute($params);

        return (int) $this->db->lastInsertId();
    }

    public function findByPasswordSetupTokenHash(string $tokenHash): ?array
    {
        $tokenHash = trim($tokenHash);

        if ($tokenHash === '') {
            return null;
        }

        if (!$this->userColumnExists('password_setup_token_hash')) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                u.*,
                r.role_name
            FROM users u
            LEFT JOIN roles r ON r.role_id = u.role_id
            WHERE u.password_setup_token_hash = :token_hash
            LIMIT 1
        ");

        $stmt->execute([
            ':token_hash' => $tokenHash,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function activateAccountWithPassword(int $userId, string $passwordHash): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $sets = [
            'password = :password',
        ];

        $params = [
            ':user_id' => $userId,
            ':password' => $passwordHash,
        ];

        if ($this->userColumnExists('account_status')) {
            $sets[] = "account_status = 'active'";
        }

        if ($this->userColumnExists('is_active')) {
            $sets[] = 'is_active = 1';
        }

        if ($this->userColumnExists('email_verified_at')) {
            $sets[] = 'email_verified_at = COALESCE(email_verified_at, NOW())';
        }

        if ($this->userColumnExists('password_setup_token_hash')) {
            $sets[] = 'password_setup_token_hash = NULL';
        }

        if ($this->userColumnExists('password_setup_expires_at')) {
            $sets[] = 'password_setup_expires_at = NULL';
        }

        if ($this->userColumnExists('updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET " . implode(', ', $sets) . "
            WHERE user_id = :user_id
              AND (
                    account_status = 'pending_verification'
                    OR account_status IS NULL
                    OR account_status = ''
                  )
            LIMIT 1
        ");

        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function markEmailVerified(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (!$this->userColumnExists('email_verified_at')) {
            return false;
        }

        $sets = [
            'email_verified_at = NOW()',
        ];

        if ($this->userColumnExists('updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET " . implode(', ', $sets) . "
            WHERE user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updatePasswordSetupToken(int $userId, string $tokenHash, string $expiresAt): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (
            !$this->userColumnExists('password_setup_token_hash') ||
            !$this->userColumnExists('password_setup_expires_at')
        ) {
            return false;
        }

        $sets = [
            'password_setup_token_hash = :token_hash',
            'password_setup_expires_at = :expires_at',
        ];

        $params = [
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
        ];

        if ($this->userColumnExists('account_status')) {
            $sets[] = "account_status = 'pending_verification'";
        }

        if ($this->userColumnExists('is_active')) {
            $sets[] = 'is_active = 0';
        }

        if ($this->userColumnExists('updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET " . implode(', ', $sets) . "
            WHERE user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    private function findRoleIdByName(string $roleName): int
    {
        $stmt = $this->db->prepare("
            SELECT role_id
            FROM roles
            WHERE role_name = :role_name
            LIMIT 1
        ");

        $stmt->execute([
            ':role_name' => $roleName,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function generatePendingPatientUsername(int $patientId): string
    {
        do {
            $username = 'patient_' . $patientId . '_' . strtolower(substr(bin2hex(random_bytes(4)), 0, 8));
        } while ($this->findByUsername($username));

        return $username;
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function filterUserColumns(array $data): array
    {
        $columns = $this->getUserColumns();
        $filtered = [];

        foreach ($data as $column => $value) {
            if (in_array($column, $columns, true)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    private function userColumnExists(string $column): bool
    {
        return in_array($column, $this->getUserColumns(), true);
    }

    private function getUserColumns(): array
    {
        static $columns = null;

        if ($columns !== null) {
            return $columns;
        }

        $stmt = $this->db->query("SHOW COLUMNS FROM users");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $columns = array_map(
            fn (array $row): string => (string) $row['Field'],
            $rows
        );

        return $columns;
    }
}