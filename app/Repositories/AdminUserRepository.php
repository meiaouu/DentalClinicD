<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

class AdminUserRepository
{
    private PDO $db;
    private array $userColumns = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->userColumns = $this->columns('users');
    }

    public function all(string $search = ''): array
    {
        $sql = "
            SELECT
                u.*,
                primary_role.role_name AS primary_role_name,
                GROUP_CONCAT(DISTINCT r.role_name ORDER BY r.role_name SEPARATOR ', ') AS roles_text
            FROM users u
            LEFT JOIN roles primary_role ON primary_role.role_id = u.role_id
            LEFT JOIN user_roles ur ON ur.user_id = u.user_id
            LEFT JOIN roles r ON r.role_id = ur.role_id
        ";

        $params = [];

        if ($search !== '') {
            $sql .= "
                WHERE u.first_name LIKE :search
                   OR u.middle_name LIKE :search
                   OR u.last_name LIKE :search
                   OR u.username LIKE :search
                   OR u.email LIKE :search
            ";

            $params[':search'] = '%' . $search . '%';
        }

        $sql .= "
            GROUP BY u.user_id
            ORDER BY u.user_id DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function roles(): array
    {
        $stmt = $this->db->query("
            SELECT role_id, role_name
            FROM roles
            WHERE LOWER(role_name) IN ('owner', 'admin', 'dentist', 'staff', 'patient')
            ORDER BY FIELD(LOWER(role_name), 'owner', 'admin', 'dentist', 'staff', 'patient')
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findWithRoles(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                u.*,
                primary_role.role_name AS primary_role_name
            FROM users u
            LEFT JOIN roles primary_role ON primary_role.role_id = u.role_id
            WHERE u.user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        $roleStmt = $this->db->prepare("
            SELECT role_id
            FROM user_roles
            WHERE user_id = :user_id
        ");

        $roleStmt->execute([
            ':user_id' => $userId,
        ]);

        $user['role_ids'] = array_map('intval', $roleStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        return $user;
    }

    public function updateProfile(int $userId, array $data): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Invalid user.');
        }

        $allowed = [
            'first_name',
            'middle_name',
            'last_name',
            'sex',
            'birth_date',
            'contact_number',
            'email',
            'username',
        ];

        $sets = [];
        $params = [
            ':user_id' => $userId,
        ];

        foreach ($allowed as $column) {
            if (!isset($this->userColumns[$column])) {
                continue;
            }

            $value = trim((string) ($data[$column] ?? ''));

            if ($column === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Invalid email address.');
            }

            $sets[] = "`$column` = :$column";
            $params[':' . $column] = $value !== '' ? $value : null;
        }

        if (isset($this->userColumns['updated_at'])) {
            $sets[] = 'updated_at = NOW()';
        }

        if (empty($sets)) {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET " . implode(', ', $sets) . "
            WHERE user_id = :user_id
        ");

        $stmt->execute($params);
    }

    public function syncRoles(int $userId, array $roleIds): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Invalid user.');
        }

        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));

        if (empty($roleIds)) {
            throw new RuntimeException('User must have at least one role.');
        }

        $this->db->beginTransaction();

        try {
            $delete = $this->db->prepare("
                DELETE FROM user_roles
                WHERE user_id = :user_id
            ");

            $delete->execute([
                ':user_id' => $userId,
            ]);

            $insert = $this->db->prepare("
                INSERT IGNORE INTO user_roles (
                    user_id,
                    role_id
                ) VALUES (
                    :user_id,
                    :role_id
                )
            ");

            foreach ($roleIds as $roleId) {
                $insert->execute([
                    ':user_id' => $userId,
                    ':role_id' => $roleId,
                ]);
            }

            if (isset($this->userColumns['role_id'])) {
                $primaryRoleId = (int) $roleIds[0];

                $updatePrimary = $this->db->prepare("
                    UPDATE users
                    SET role_id = :role_id
                    WHERE user_id = :user_id
                ");

                $updatePrimary->execute([
                    ':role_id' => $primaryRoleId,
                    ':user_id' => $userId,
                ]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function assignRoleByName(int $userId, string $roleName): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Invalid user.');
        }

        $stmt = $this->db->prepare("
            SELECT role_id
            FROM roles
            WHERE LOWER(role_name) = LOWER(:role_name)
            LIMIT 1
        ");

        $stmt->execute([
            ':role_name' => $roleName,
        ]);

        $roleId = (int) ($stmt->fetchColumn() ?: 0);

        if ($roleId <= 0) {
            throw new RuntimeException('Role not found.');
        }

        $insert = $this->db->prepare("
            INSERT IGNORE INTO user_roles (
                user_id,
                role_id
            ) VALUES (
                :user_id,
                :role_id
            )
        ");

        $insert->execute([
            ':user_id' => $userId,
            ':role_id' => $roleId,
        ]);
    }

    public function setActive(int $userId, bool $active): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Invalid user.');
        }

        if (!isset($this->userColumns['is_active'])) {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET is_active = :is_active
            WHERE user_id = :user_id
        ");

        $stmt->execute([
            ':is_active' => $active ? 1 : 0,
            ':user_id' => $userId,
        ]);
    }

    public function resetPassword(int $userId, string $plainPassword): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Invalid user.');
        }

        if (strlen($plainPassword) < 8) {
            throw new RuntimeException('Password must be at least 8 characters.');
        }

        $passwordColumn = '';

        if (isset($this->userColumns['password'])) {
            $passwordColumn = 'password';
        }

        if (isset($this->userColumns['password_hash'])) {
            $passwordColumn = 'password_hash';
        }

        if ($passwordColumn === '') {
            throw new RuntimeException('No password column found in users table.');
        }

        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            UPDATE users
            SET `$passwordColumn` = :password
            WHERE user_id = :user_id
        ");

        $stmt->execute([
            ':password' => $hash,
            ':user_id' => $userId,
        ]);
    }

    public function hasOwnerAdminRole(int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM user_roles ur
            INNER JOIN roles r ON r.role_id = ur.role_id
            WHERE ur.user_id = :user_id
              AND LOWER(r.role_name) IN ('owner', 'admin')
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function roleIdsContainOwnerAdmin(array $roleIds): bool
    {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));

        if (empty($roleIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM roles
            WHERE role_id IN ($placeholders)
              AND LOWER(role_name) IN ('owner', 'admin')
        ");

        $stmt->execute($roleIds);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function countActiveOwnerAdmins(): int
    {
        $activeCondition = isset($this->userColumns['is_active'])
            ? "AND u.is_active = 1"
            : "";

        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT u.user_id)
            FROM users u
            INNER JOIN user_roles ur ON ur.user_id = u.user_id
            INNER JOIN roles r ON r.role_id = ur.role_id
            WHERE LOWER(r.role_name) IN ('owner', 'admin')
            $activeCondition
        ");

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function audit(string $action, string $entityType, ?int $entityId, string $description): void
    {
        try {
            $authUser = \App\Core\Auth::user();
            $userId = (int) ($authUser['user_id'] ?? 0);

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (
                    user_id,
                    action,
                    entity_type,
                    entity_id,
                    description,
                    ip_address,
                    created_at
                ) VALUES (
                    :user_id,
                    :action,
                    :entity_type,
                    :entity_id,
                    :description,
                    :ip_address,
                    NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $userId > 0 ? $userId : null,
                ':action' => $action,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':description' => $description,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Audit logging must not break the main admin workflow.
        }
    }

    private function columns(string $table): array
    {
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM `$table`");

            $columns = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $field = (string) ($row['Field'] ?? '');

                if ($field !== '') {
                    $columns[$field] = true;
                }
            }

            return $columns;
        } catch (\Throwable $e) {
            return [];
        }
    }
}