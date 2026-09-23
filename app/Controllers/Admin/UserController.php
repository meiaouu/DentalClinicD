<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AdminUserRepository;
use RuntimeException;
use Throwable;
use App\Core\Database;

use PDO;

class UserController
{
    private AdminUserRepository $users;

    public function __construct()
    {
        $this->users = new AdminUserRepository();
    }

    public function index(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);

        $search = trim((string) ($_GET['search'] ?? ''));

        View::render('admin.users.index', [
            'users' => $this->users->all($search),
            'roles' => $this->users->roles(),
            'search' => $search,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function edit(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);

        $this->redirectUsers(302);
    }

    public function update(): void
{
    $actor = $this->requireOwnerOrAdminForUserSave();

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $db = Database::getConnection();

    try {
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            throw new RuntimeException('Invalid user selected.');
        }

        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $middleName = trim((string) ($_POST['middle_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $contactNumber = trim((string) ($_POST['contact_number'] ?? ''));
        $isActive = (int) ($_POST['is_active'] ?? 1) === 1 ? 1 : 0;

        if ($firstName === '') {
            throw new RuntimeException('First name is required.');
        }

        if ($lastName === '') {
            throw new RuntimeException('Last name is required.');
        }

        if ($username === '') {
            throw new RuntimeException('Username is required.');
        }

        if (!preg_match('/^[a-zA-Z0-9_]{3,100}$/', $username)) {
            throw new RuntimeException('Username must be 3 to 100 characters and may only contain letters, numbers, and underscore.');
        }

        if ($email === '') {
            throw new RuntimeException('Email is required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        if ($contactNumber !== '' && !preg_match('/^(09\d{9}|639\d{9}|\+639\d{9})$/', $contactNumber)) {
            throw new RuntimeException('Invalid contact number. Use 09XXXXXXXXX, 639XXXXXXXXX, or +639XXXXXXXXX.');
        }

        $roleIds = $this->cleanPostedRoleIds($_POST['role_ids'] ?? []);

        if (empty($roleIds)) {
            throw new RuntimeException('Please assign at least one role.');
        }

        $validRoleIds = $this->getValidRoleIds($db, $roleIds);

        if (count($validRoleIds) !== count($roleIds)) {
            throw new RuntimeException('One or more selected roles are invalid.');
        }

        $primaryRoleId = (int) $roleIds[0];

        $db->beginTransaction();

        $lockStmt = $db->prepare("
            SELECT user_id
            FROM users
            WHERE user_id = :user_id
            LIMIT 1
            FOR UPDATE
        ");
        $lockStmt->execute([
            ':user_id' => $userId,
        ]);

        if (!$lockStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('User not found.');
        }

        $usernameStmt = $db->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE username = :username
              AND user_id <> :user_id
        ");
        $usernameStmt->execute([
            ':username' => $username,
            ':user_id' => $userId,
        ]);

        if ((int) $usernameStmt->fetchColumn() > 0) {
            throw new RuntimeException('Username is already used by another user.');
        }

        $emailStmt = $db->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE email = :email
              AND user_id <> :user_id
        ");
        $emailStmt->execute([
            ':email' => $email,
            ':user_id' => $userId,
        ]);

        if ((int) $emailStmt->fetchColumn() > 0) {
            throw new RuntimeException('Email is already used by another user.');
        }

        $updateStmt = $db->prepare("
            UPDATE users
            SET
                role_id = :role_id,
                first_name = :first_name,
                middle_name = :middle_name,
                last_name = :last_name,
                username = :username,
                email = :email,
                contact_number = :contact_number,
                is_active = :is_active,
                updated_at = NOW()
            WHERE user_id = :user_id
            LIMIT 1
        ");

        $updateStmt->execute([
            ':role_id' => $primaryRoleId,
            ':first_name' => $firstName,
            ':middle_name' => $middleName !== '' ? $middleName : null,
            ':last_name' => $lastName,
            ':username' => $username,
            ':email' => $email,
            ':contact_number' => $contactNumber !== '' ? $contactNumber : null,
            ':is_active' => $isActive,
            ':user_id' => $userId,
        ]);

        $deleteRolesStmt = $db->prepare("
            DELETE FROM user_roles
            WHERE user_id = :user_id
        ");
        $deleteRolesStmt->execute([
            ':user_id' => $userId,
        ]);

        $insertRoleStmt = $db->prepare("
            INSERT INTO user_roles (user_id, role_id, assigned_at)
            VALUES (:user_id, :role_id, NOW())
        ");

        foreach ($roleIds as $roleId) {
            $insertRoleStmt->execute([
                ':user_id' => $userId,
                ':role_id' => (int) $roleId,
            ]);
        }

        $this->auditAdminUserAction(
            $db,
            (int) ($actor['user_id'] ?? 0),
            'users.update',
            $userId,
            'Updated user account and role assignments.'
        );

        $db->commit();

        Session::set('flash_success', 'User and role updated successfully.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        error_log('[Admin User Update] ' . $e->getMessage());

        Session::set('flash_error', $e->getMessage());
    }

    header('Location: /DentalClinic/public/admin/users');
    exit;
}

    public function updateStatus(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);
        $this->verifyCsrf();

        $authUser = Auth::user();
        $currentUserId = (int) ($authUser['user_id'] ?? 0);

        $userId = (int) ($_POST['user_id'] ?? 0);
        $isActive = (int) ($_POST['is_active'] ?? 0) === 1;

        try {
            if ($userId <= 0) {
                throw new RuntimeException('Invalid user.');
            }

            $this->changeUserStatusSafely($userId, $isActive, $currentUserId);

            Session::set('flash_success', 'User status updated successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        $this->redirectUsers();
    }

    public function updateRoles(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);
        $this->verifyCsrf();

        $authUser = Auth::user();
        $currentUserId = (int) ($authUser['user_id'] ?? 0);

        $userId = (int) ($_POST['user_id'] ?? 0);

        /*
         * Supports both the new modal field name role_ids[]
         * and any older form that may still submit roles[].
         */
        $rawRoleIds = $_POST['role_ids'] ?? ($_POST['roles'] ?? []);

        if (!is_array($rawRoleIds)) {
            $rawRoleIds = [$rawRoleIds];
        }

        $roleIds = array_values(array_unique(array_filter(array_map(
            static fn($roleId): int => (int) $roleId,
            $rawRoleIds
        ), static fn(int $roleId): bool => $roleId > 0)));

        try {
            if ($userId <= 0) {
                throw new RuntimeException('Invalid user.');
            }

            if (empty($roleIds)) {
                throw new RuntimeException('Please assign at least one role to this user.');
            }

            $existingUser = $this->users->findWithRoles($userId);

            if (!$existingUser) {
                throw new RuntimeException('User not found.');
            }

            $willHaveOwnerAdmin = $this->users->roleIdsContainOwnerAdmin($roleIds);
            $currentlyHasOwnerAdmin = $this->users->hasOwnerAdminRole($userId);

            if (
                $currentlyHasOwnerAdmin &&
                !$willHaveOwnerAdmin &&
                $this->users->countActiveOwnerAdmins() <= 1
            ) {
                throw new RuntimeException('You cannot remove the last owner/admin role.');
            }

            if (
                $userId === $currentUserId &&
                $currentlyHasOwnerAdmin &&
                !$willHaveOwnerAdmin &&
                $this->users->countActiveOwnerAdmins() <= 1
            ) {
                throw new RuntimeException('You cannot remove your own owner/admin access because you are the last owner/admin.');
            }

            $this->users->syncRoles($userId, $roleIds);
            $this->users->audit('users.roles', 'users', $userId, 'Updated user roles.');

            Session::set('flash_success', 'User roles updated successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        $this->redirectUsers();
    }

    public function resetPassword(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);
        $this->verifyCsrf();

        $userId = (int) ($_POST['user_id'] ?? 0);
        $newPassword = trim((string) ($_POST['new_password'] ?? ''));

        try {
            if ($userId <= 0) {
                throw new RuntimeException('Invalid user.');
            }

            $existingUser = $this->users->findWithRoles($userId);

            if (!$existingUser) {
                throw new RuntimeException('User not found.');
            }

            if ($newPassword === '') {
                $newPassword = 'Temp-' . strtoupper(bin2hex(random_bytes(4)));
            }

            if (strlen($newPassword) < 8) {
                throw new RuntimeException('Password must be at least 8 characters.');
            }

            $this->users->resetPassword($userId, $newPassword);
            $this->users->audit('users.reset_password', 'users', $userId, 'Reset user password.');

            Session::set('flash_success', 'Password reset successfully. Temporary password: ' . $newPassword);
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        $this->redirectUsers();
    }

    private function changeUserStatusSafely(int $userId, bool $isActive, int $currentUserId): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Invalid user.');
        }

        $existingUser = $this->users->findWithRoles($userId);

        if (!$existingUser) {
            throw new RuntimeException('User not found.');
        }

        if ($userId === $currentUserId && !$isActive) {
            throw new RuntimeException('You cannot deactivate your own account.');
        }

        if (
            !$isActive &&
            $this->users->hasOwnerAdminRole($userId) &&
            $this->users->countActiveOwnerAdmins() <= 1
        ) {
            throw new RuntimeException('You cannot deactivate the last owner/admin account.');
        }

        $this->users->setActive($userId, $isActive);
        $this->users->audit(
            'users.status',
            'users',
            $userId,
            $isActive ? 'Activated user.' : 'Deactivated user.'
        );
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }

    private function redirectUsers(int $statusCode = 303): void
    {
        header('Location: /DentalClinic/public/admin/users', true, $statusCode);
        exit;
    }

    private function cleanPostedRoleIds($roleIds): array
{
    if (!is_array($roleIds)) {
        $roleIds = [$roleIds];
    }

    $clean = [];

    foreach ($roleIds as $roleId) {
        $roleId = (int) $roleId;

        if ($roleId > 0) {
            $clean[$roleId] = $roleId;
        }
    }

    return array_values($clean);
}

private function getValidRoleIds(PDO $db, array $roleIds): array
{
    if (empty($roleIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($roleIds), '?'));

    $stmt = $db->prepare("
        SELECT role_id
        FROM roles
        WHERE role_id IN ($placeholders)
    ");

    $stmt->execute(array_values($roleIds));

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

private function requireOwnerOrAdminForUserSave(): array
{
    if (method_exists(Auth::class, 'requireLogin')) {
        Auth::requireLogin();
    }

    $user = Auth::user();

    $allowed = false;

    if (method_exists(Auth::class, 'hasAnyRole')) {
        $allowed = Auth::hasAnyRole(['owner', 'admin']);
    } elseif (method_exists(Auth::class, 'hasRole')) {
        $allowed = Auth::hasRole('owner') || Auth::hasRole('admin');
    }

    if (!$user || empty($user['user_id']) || !$allowed) {
        http_response_code(403);
        exit('Unauthorized.');
    }

    return $user;
}

private function auditAdminUserAction(PDO $db, int $actorUserId, string $action, int $recordId, string $description): void
{
    try {
        $columns = $db->query("SHOW COLUMNS FROM audit_logs")->fetchAll(PDO::FETCH_COLUMN) ?: [];

        if (empty($columns)) {
            return;
        }

        $data = [];

        if (in_array('user_id', $columns, true)) {
            $data['user_id'] = $actorUserId > 0 ? $actorUserId : null;
        }

        if (in_array('action', $columns, true)) {
            $data['action'] = $action;
        }

        if (in_array('table_name', $columns, true)) {
            $data['table_name'] = 'users';
        }

        if (in_array('record_id', $columns, true)) {
            $data['record_id'] = $recordId;
        }

        if (in_array('module', $columns, true)) {
            $data['module'] = 'users';
        }

        if (in_array('target_type', $columns, true)) {
            $data['target_type'] = 'user';
        }

        if (in_array('target_id', $columns, true)) {
            $data['target_id'] = (string) $recordId;
        }

        if (in_array('description', $columns, true)) {
            $data['description'] = $description;
        }

        if (in_array('ip_address', $columns, true)) {
            $data['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
        }

        if (in_array('user_agent', $columns, true)) {
            $data['user_agent'] = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        }

        if (in_array('created_at', $columns, true)) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        if (empty($data)) {
            return;
        }

        $fieldList = array_keys($data);
        $placeholders = array_map(static fn ($field) => ':' . $field, $fieldList);

        $stmt = $db->prepare("
            INSERT INTO audit_logs (" . implode(', ', $fieldList) . ")
            VALUES (" . implode(', ', $placeholders) . ")
        ");

        $stmt->execute($data);
    } catch (Throwable $e) {
        error_log('[Audit Log Failed] ' . $e->getMessage());
    }
}
}