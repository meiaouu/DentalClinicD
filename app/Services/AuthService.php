<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\PatientRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use PDO;
use RuntimeException;
use Throwable;

class AuthService
{
    private const GENERIC_LOGIN_ERROR = 'Invalid username/email or password.';
    private const MAX_FAILED_ATTEMPTS = 5;
    private const ATTEMPT_WINDOW_MINUTES = 15;
    private const LOCKOUT_MINUTES = 15;

    private UserRepository $users;
    private RoleRepository $roles;
    private PatientRepository $patients;
    private PDO $db;

    /** @var array<string, array<string, bool>> */
    private array $tableColumnsCache = [];

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->roles = new RoleRepository();
        $this->patients = new PatientRepository();
        $this->db = Database::getConnection();

        $this->ensureLoginAttemptsTable();
    }


    public function attemptLogin(
        string $login,
        string $password,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $login = trim($login);
        $password = (string) $password;
        $ipAddress = $this->cleanIp($ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? ''));
        $userAgent = $this->cleanUserAgent($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));

        if ($login === '' || $password === '') {
            throw new RuntimeException(self::GENERIC_LOGIN_ERROR);
        }

        if ($this->isLoginLocked($login, $ipAddress)) {
            $this->writeAuthAudit(
                null,
                'login_locked',
                'Blocked login during temporary lockout.',
                $ipAddress,
                $userAgent
            );

            throw new RuntimeException('Too many failed attempts. Please try again after 15 minutes.');
        }

        try {
            $user = $this->findUserByLoginIdentifier($login);

            if (!$user || empty($user['password'])) {
                $this->recordFailedLoginAttempt($login, $ipAddress, null, $userAgent);
                throw new RuntimeException(self::GENERIC_LOGIN_ERROR);
            }

            $storedHash = (string) $user['password'];

            if (!password_verify($password, $storedHash)) {
                $this->recordFailedLoginAttempt($login, $ipAddress, (int) $user['user_id'], $userAgent);
                throw new RuntimeException(self::GENERIC_LOGIN_ERROR);
            }

        
           $accountStatus = strtolower(trim((string) ($user['account_status'] ?? 'active')));
$roleName = strtolower((string) ($user['role_name'] ?? $user['role'] ?? ''));

if ($accountStatus === 'pending_password_setup') {
    $this->writeAuthAudit(
        (int) $user['user_id'],
        'login_requires_password_setup',
        'User must complete password setup before login.',
        $ipAddress,
        $userAgent
    );

    throw new RuntimeException('Please verify your account or set your password before logging in.');
}


if ($roleName === 'patient' && in_array($accountStatus, ['pending_review', 'rejected'], true)) {
 
} elseif (!in_array($accountStatus, ['active', ''], true)) {
    $this->writeAuthAudit(
        (int) $user['user_id'],
        'login_blocked',
        'Blocked login because account status is not active.',
        $ipAddress,
        $userAgent
    );

    throw new RuntimeException(self::GENERIC_LOGIN_ERROR);
}

            if (isset($user['is_active']) && (int) $user['is_active'] !== 1) {
                $this->writeAuthAudit(
                    (int) $user['user_id'],
                    'login_blocked',
                    'Blocked login because account is inactive.',
                    $ipAddress,
                    $userAgent
                );

                throw new RuntimeException(self::GENERIC_LOGIN_ERROR);
            }

            if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                $this->rehashPassword((int) $user['user_id'], $password);
            }

            $this->updateLastLoginAt((int) $user['user_id']);
            $this->clearLoginAttempts($login, $ipAddress);

            $this->writeAuthAudit(
                (int) $user['user_id'],
                'login_success',
                'User logged in successfully.',
                $ipAddress,
                $userAgent
            );

            unset($user['password']);

            $roleName = strtolower((string) ($user['role_name'] ?? $user['role'] ?? ''));

$user['role'] = $roleName;
$user['role_name'] = $roleName;
$user['account_status'] = $accountStatus;
$user['logged_in'] = time();
$user['last_activity'] = time();

return $user;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log('AuthService login error: ' . $e->getMessage());

            $this->recordFailedLoginAttempt($login, $ipAddress, null, $userAgent);

            throw new RuntimeException(self::GENERIC_LOGIN_ERROR);
        }
    }

    public function registerPatient(array $data): int
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $username = $this->generatePatientUsername(
    trim((string) ($data['first_name'] ?? '')),
    trim((string) ($data['last_name'] ?? ''))
);
        $contactNumber = $this->normalizePhoneInput((string) ($data['contact_number'] ?? ''));

        $password = (string) ($data['password'] ?? '');
        $confirmPassword = (string) ($data['password_confirmation'] ?? '');

        $firstName = trim((string) ($data['first_name'] ?? ''));
        $middleName = trim((string) ($data['middle_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));

        $sex = trim((string) ($data['sex'] ?? ''));
        $birthDate = trim((string) ($data['birth_date'] ?? ''));

        $this->validatePatientRegistration([
    'email' => $email,
    'contact_number' => $contactNumber,
    'password' => $password,
    'password_confirmation' => $confirmPassword,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'sex' => $sex,
    'birth_date' => $birthDate,
]);

        $patientRole = $this->roles->findByRoleName('patient');

        if (!$patientRole) {
            throw new RuntimeException('Patient role not found in database.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $userId = $this->users->create([
    'role_id' => (int) $patientRole['role_id'],
    'first_name' => $firstName,
    'middle_name' => $middleName !== '' ? $middleName : null,
    'last_name' => $lastName,
    'sex' => $sex !== '' ? $sex : null,
    'birth_date' => $birthDate !== '' ? $birthDate : null,
    'contact_number' => $contactNumber,
    'email' => $email,
    'username' => $username,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'account_status' => 'pending_review',
    'is_active' => 1,
]);

            $patientCode = 'PAT-' . date('Ymd') . '-' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT);

            $this->patients->create([
                'user_id' => $userId,
                'patient_code' => $patientCode,
                'first_name' => $firstName,
                'middle_name' => $middleName !== '' ? $middleName : null,
                'last_name' => $lastName,
                'sex' => $sex !== '' ? $sex : null,
                'birth_date' => $birthDate !== '' ? $birthDate : null,
                'civil_status' => trim((string) ($data['civil_status'] ?? '')) ?: null,
                'address' => trim((string) ($data['address'] ?? '')) ?: null,
                'occupation' => trim((string) ($data['occupation'] ?? '')) ?: null,
                'contact_number' => $contactNumber,
                'email' => $email,
                'emergency_contact_name' => null,
'emergency_contact_number' => null,
                'notes' => null,
                'profile_status' => 'pending_review',
                'created_by' => null,
            ]);

            $this->writeAuthAudit(
                $userId,
                'patient_registration',
                'Patient account registered successfully.',
                $this->cleanIp($_SERVER['REMOTE_ADDR'] ?? ''),
                $this->cleanUserAgent($_SERVER['HTTP_USER_AGENT'] ?? '')
            );

            $db->commit();

            return $userId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Patient registration failed: ' . $e->getMessage());

            throw $e;
        }
    }

public function resolveRedirectByRole(array $user): string
{
    $role = strtolower(trim((string) ($user['role_name'] ?? $user['role'] ?? '')));

    return match ($role) {
        'owner', 'admin' => '/admin/dashboard',
        'dentist' => '/dentist/dashboard',
        'staff' => '/staff/dashboard',
        'patient' => '/patient/dashboard',
        default => '/login',
    };
}

    public function recordLogout(?int $userId, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->writeAuthAudit(
            $userId,
            'logout',
            'User logged out.',
            $this->cleanIp($ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '')),
            $this->cleanUserAgent($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''))
        );
    }

    public function recordPasswordChanged(int $userId, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->writeAuthAudit(
            $userId,
            'password_changed',
            'User changed account password.',
            $this->cleanIp($ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '')),
            $this->cleanUserAgent($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''))
        );
    }

    public function recordPasswordSetupCompleted(int $userId, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->writeAuthAudit(
            $userId,
            'password_setup_completed',
            'User completed password setup.',
            $this->cleanIp($ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '')),
            $this->cleanUserAgent($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''))
        );
    }

    public function recordPasswordResetRequested(?int $userId, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->writeAuthAudit(
            $userId,
            'password_reset_requested',
            'Password reset was requested.',
            $this->cleanIp($ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '')),
            $this->cleanUserAgent($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''))
        );
    }

    public function recordPasswordResetCompleted(int $userId, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->writeAuthAudit(
            $userId,
            'password_reset_completed',
            'Password reset was completed.',
            $this->cleanIp($ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? '')),
            $this->cleanUserAgent($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''))
        );
    }

    private function validatePatientRegistration(array $data): void
    {
        if ($data['first_name'] === '') {
            throw new RuntimeException('First name is required.');
        }

        if ($data['last_name'] === '') {
            throw new RuntimeException('Last name is required.');
        }

        if ($data['sex'] === '') {
            throw new RuntimeException('Sex is required.');
        }

        if ($data['birth_date'] === '') {
            throw new RuntimeException('Birth date is required.');
        }

        if ($data['contact_number'] === '') {
            throw new RuntimeException('Contact number is required.');
        }

        if (!$this->isValidPhilippineMobile($data['contact_number'])) {
            throw new RuntimeException('Invalid contact number. Use 09XXXXXXXXX, 639XXXXXXXXX, or +639XXXXXXXXX.');
        }

        if ($data['email'] === '') {
            throw new RuntimeException('Email is required.');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }


        if ($data['password'] === '' || $data['password_confirmation'] === '') {
            throw new RuntimeException('Password and confirmation are required.');
        }

        if (strlen($data['password']) < 8) {
            throw new RuntimeException('Password must be at least 8 characters.');
        }

        if ($data['password'] !== $data['password_confirmation']) {
            throw new RuntimeException('Password confirmation does not match.');
        }

        $stmt = $this->db->prepare("
    SELECT user_id
    FROM users
    WHERE email = :email
       OR contact_number = :contact_number
    LIMIT 1
");

$stmt->execute([
    ':email' => $data['email'],
    ':contact_number' => $data['contact_number'],
]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Email or contact number is already registered.');
        }
    }

    private function findUserByLoginIdentifier(string $login): ?array
    {
        $variants = $this->loginVariants($login);

        if (empty($variants)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($variants), '?'));

        $stmt = $this->db->prepare("
            SELECT
                u.*,
                r.role_name
            FROM users u
            INNER JOIN roles r ON r.role_id = u.role_id
            WHERE u.username IN ($placeholders)
               OR u.email IN ($placeholders)
               OR u.contact_number IN ($placeholders)
            LIMIT 1
        ");

        $stmt->execute(array_merge($variants, $variants, $variants));

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    private function loginVariants(string $login): array
    {
        $login = trim($login);

        if ($login === '') {
            return [];
        }

        $variants = [
            $login,
            strtolower($login),
        ];

        $digits = preg_replace('/\D+/', '', $login);

        if ($digits !== '') {
            if (preg_match('/^09\d{9}$/', $digits)) {
                $variants[] = $digits;
                $variants[] = '63' . substr($digits, 1);
                $variants[] = '+63' . substr($digits, 1);
            } elseif (preg_match('/^639\d{9}$/', $digits)) {
                $variants[] = $digits;
                $variants[] = '+' . $digits;
                $variants[] = '0' . substr($digits, 2);
            }
        }

        return array_values(array_unique(array_filter($variants, static function ($value): bool {
            return trim((string) $value) !== '';
        })));
    }

    private function isLoginLocked(string $login, string $ipAddress): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT locked_until
                FROM login_attempts
                WHERE login_identifier = :login_identifier
                  AND ip_address = :ip_address
                  AND locked_until IS NOT NULL
                  AND locked_until > NOW()
                ORDER BY attempt_id DESC
                LIMIT 1
            ");

            $stmt->execute([
                ':login_identifier' => $this->normalizeLoginIdentifier($login),
                ':ip_address' => $ipAddress,
            ]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('Login lockout check failed: ' . $e->getMessage());

            return false;
        }
    }

    private function recordFailedLoginAttempt(
        string $login,
        string $ipAddress,
        ?int $userId = null,
        ?string $userAgent = null
    ): void {
        try {
            $loginIdentifier = $this->normalizeLoginIdentifier($login);

            $countStmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM login_attempts
                WHERE login_identifier = :login_identifier
                  AND ip_address = :ip_address
                  AND was_successful = 0
                  AND attempted_at >= DATE_SUB(NOW(), INTERVAL " . self::ATTEMPT_WINDOW_MINUTES . " MINUTE)
            ");

            $countStmt->execute([
                ':login_identifier' => $loginIdentifier,
                ':ip_address' => $ipAddress,
            ]);

            $failedCount = (int) $countStmt->fetchColumn();

            $lockedUntil = ($failedCount + 1) >= self::MAX_FAILED_ATTEMPTS
                ? date('Y-m-d H:i:s', time() + (self::LOCKOUT_MINUTES * 60))
                : null;

            $stmt = $this->db->prepare("
                INSERT INTO login_attempts (
                    user_id,
                    login_identifier,
                    ip_address,
                    was_successful,
                    locked_until,
                    attempted_at
                ) VALUES (
                    :user_id,
                    :login_identifier,
                    :ip_address,
                    0,
                    :locked_until,
                    NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':login_identifier' => $loginIdentifier,
                ':ip_address' => $ipAddress,
                ':locked_until' => $lockedUntil,
            ]);

            $this->writeAuthAudit(
                $userId,
                $lockedUntil ? 'login_locked' : 'login_failed',
                $lockedUntil
                    ? 'Login temporarily locked after repeated failed attempts.'
                    : 'Failed login attempt.',
                $ipAddress,
                $this->cleanUserAgent($userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''))
            );
        } catch (Throwable $e) {
            error_log('Failed login attempt logging failed: ' . $e->getMessage());
        }
    }

    private function clearLoginAttempts(string $login, string $ipAddress): void
    {
        try {
            $loginIdentifier = $this->normalizeLoginIdentifier($login);

            $stmt = $this->db->prepare("
                INSERT INTO login_attempts (
                    user_id,
                    login_identifier,
                    ip_address,
                    was_successful,
                    locked_until,
                    attempted_at
                ) VALUES (
                    NULL,
                    :login_identifier,
                    :ip_address,
                    1,
                    NULL,
                    NOW()
                )
            ");

            $stmt->execute([
                ':login_identifier' => $loginIdentifier,
                ':ip_address' => $ipAddress,
            ]);

            $deleteStmt = $this->db->prepare("
                DELETE FROM login_attempts
                WHERE login_identifier = :login_identifier
                  AND ip_address = :ip_address
                  AND was_successful = 0
            ");

            $deleteStmt->execute([
                ':login_identifier' => $loginIdentifier,
                ':ip_address' => $ipAddress,
            ]);
        } catch (Throwable $e) {
            error_log('Clearing login attempts failed: ' . $e->getMessage());
        }
    }

    private function writeAuthAudit(
        ?int $userId,
        string $action,
        string $description,
        string $ipAddress,
        string $userAgent
    ): void {
        try {
            $columns = $this->tableColumns('audit_logs');

            if (empty($columns)) {
                return;
            }

            $data = [];

            if (isset($columns['user_id'])) {
                $data['user_id'] = $userId;
            }

            if (isset($columns['action'])) {
                $data['action'] = $action;
            }

            if (isset($columns['entity_type'])) {
                $data['entity_type'] = 'authentication';
            }

            if (isset($columns['entity_id'])) {
                $data['entity_id'] = $userId;
            }

            if (isset($columns['module_name'])) {
                $data['module_name'] = 'authentication';
            }

            if (isset($columns['action_name'])) {
                $data['action_name'] = $action;
            }

            if (isset($columns['record_type'])) {
                $data['record_type'] = 'user';
            }

            if (isset($columns['record_id'])) {
                $data['record_id'] = $userId !== null ? (string) $userId : null;
            }

            if (isset($columns['description'])) {
                $data['description'] = $description;
            }

            if (isset($columns['ip_address'])) {
                $data['ip_address'] = $ipAddress;
            }

            if (isset($columns['user_agent'])) {
                $data['user_agent'] = $userAgent;
            }

            if (empty($data)) {
                return;
            }

            $insertColumns = array_keys($data);

            $placeholders = array_map(static function (string $column): string {
                return ':' . $column;
            }, $insertColumns);

            if (isset($columns['created_at'])) {
                $insertColumns[] = 'created_at';
                $placeholders[] = 'NOW()';
            }

            $sql = "
                INSERT INTO audit_logs (" . implode(', ', $insertColumns) . ")
                VALUES (" . implode(', ', $placeholders) . ")
            ";

            $stmt = $this->db->prepare($sql);

            foreach ($data as $column => $value) {
                if ($value === null) {
                    $stmt->bindValue(':' . $column, null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':' . $column, $value);
                }
            }

            $stmt->execute();
        } catch (Throwable $e) {
            error_log('Auth audit failed: ' . $e->getMessage());
        }
    }

    private function generatePatientUsername(string $firstName, string $lastName): string
{
    $base = strtolower($firstName . '.' . $lastName);
    $base = preg_replace('/[^a-z0-9_\.]/', '', $base) ?? 'patient';
    $base = trim($base, '.');

    if ($base === '' || strlen($base) < 3) {
        $base = 'patient';
    }

    $base = substr($base, 0, 80);

    for ($i = 0; $i < 20; $i++) {
        $suffix = random_int(1000, 9999);
        $username = $base . $suffix;

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE username = :username
        ");

        $stmt->execute([
            ':username' => $username,
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            return $username;
        }
    }

    return 'patient' . date('YmdHis') . random_int(100, 999);
}

    private function updateLastLoginAt(int $userId): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE users
                SET last_login_at = NOW()
                WHERE user_id = :user_id
                LIMIT 1
            ");

            $stmt->execute([
                ':user_id' => $userId,
            ]);
        } catch (Throwable $e) {
            error_log('Last login update failed: ' . $e->getMessage());
        }
    }

    private function rehashPassword(int $userId, string $plainPassword): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE users
                SET password = :password,
                    updated_at = NOW()
                WHERE user_id = :user_id
                LIMIT 1
            ");

            $stmt->execute([
                ':password' => password_hash($plainPassword, PASSWORD_DEFAULT),
                ':user_id' => $userId,
            ]);
        } catch (Throwable $e) {
            error_log('Password rehash failed: ' . $e->getMessage());
        }
    }

    private function ensureLoginAttemptsTable(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    attempt_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT UNSIGNED DEFAULT NULL,
                    login_identifier VARCHAR(190) NOT NULL,
                    ip_address VARCHAR(60) NOT NULL,
                    was_successful TINYINT(1) NOT NULL DEFAULT 0,
                    locked_until DATETIME DEFAULT NULL,
                    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (attempt_id),
                    KEY idx_login_attempts_user_id (user_id),
                    KEY idx_login_attempts_identifier_ip_time (login_identifier, ip_address, attempted_at),
                    KEY idx_login_attempts_locked_until (locked_until)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        } catch (Throwable $e) {
            error_log('Could not create login_attempts table: ' . $e->getMessage());
        }
    }

    private function tableColumns(string $table): array
    {
        if (isset($this->tableColumnsCache[$table])) {
            return $this->tableColumnsCache[$table];
        }

        try {
            $stmt = $this->db->query("DESCRIBE `{$table}`");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $columns = [];

            foreach ($rows as $row) {
                $field = strtolower((string) ($row['Field'] ?? ''));

                if ($field !== '') {
                    $columns[$field] = true;
                }
            }

            $this->tableColumnsCache[$table] = $columns;

            return $columns;
        } catch (Throwable $e) {
            $this->tableColumnsCache[$table] = [];

            return [];
        }
    }

    private function normalizeLoginIdentifier(string $login): string
    {
        $login = strtolower(trim($login));
        $digits = preg_replace('/\D+/', '', $login);

        if ($digits !== '') {
            if (preg_match('/^09\d{9}$/', $digits)) {
                return '63' . substr($digits, 1);
            }

            if (preg_match('/^639\d{9}$/', $digits)) {
                return $digits;
            }
        }

        return $login;
    }

    private function normalizePhoneInput(string $number): string
    {
        return preg_replace('/[\s-]+/', '', trim($number)) ?? '';
    }

    private function isValidPhilippineMobile(string $number): bool
    {
        return preg_match('/^(09\d{9}|639\d{9}|\+639\d{9})$/', $number) === 1;
    }

    private function cleanIp(string $ipAddress): string
    {
        return substr($ipAddress, 0, 60);
    }

    private function cleanUserAgent(string $userAgent): string
    {
        return substr($userAgent, 0, 255);
    }
}