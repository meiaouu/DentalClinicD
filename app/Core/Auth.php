<?php

namespace App\Core;

class Auth
{
    private const ADMIN_DENTIST_USER_ID = 24;

    public static function check(): bool
    {
        return Session::has('auth_user');
    }

    public static function user(): ?array
    {
        return Session::get('auth_user');
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('auth_user', $user);
        Session::set('auth_last_activity', time());
    }

    public static function logout(): void
    {
        Session::remove('auth_user');
        Session::remove('auth_last_activity');
        Session::regenerate();
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }

        self::enforceIdleTimeout();
    }



  

    private static function enforceIdleTimeout(): void
    {
        $lastActivity = (int) Session::get('auth_last_activity', 0);
        $timeoutSeconds = 60 * 60 * 2; // 2 hours

        if ($lastActivity > 0 && (time() - $lastActivity) > $timeoutSeconds) {
            self::logout();
            header('Location: /login');
            exit;
        }

        Session::set('auth_last_activity', time());
    }


public static function roles(): array
{
    $user = self::user();

    if (!$user || empty($user['user_id'])) {
        return [];
    }

    try {
        $db = \App\Core\Database::getConnection();
        $roles = [];

        /*
            New multi-role table.
        */
        $stmt = $db->prepare("
            SELECT r.role_name
            FROM user_roles ur
            INNER JOIN roles r ON r.role_id = ur.role_id
            WHERE ur.user_id = :user_id
        ");

        $stmt->execute([
            ':user_id' => (int) $user['user_id'],
        ]);

        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [] as $roleName) {
            $roles[] = self::normalizeRoleName((string) $roleName);
        }

        /*
            Backward compatibility:
            still read users.role_id so old accounts keep working.
        */
        $stmt = $db->prepare("
            SELECT r.role_name
            FROM users u
            INNER JOIN roles r ON r.role_id = u.role_id
            WHERE u.user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => (int) $user['user_id'],
        ]);

        $primaryRole = $stmt->fetchColumn();

        if ($primaryRole) {
            $roles[] = self::normalizeRoleName((string) $primaryRole);
        }

        /*
            Session compatibility if your login stores role_name.
        */
        if (!empty($user['role_name'])) {
            $roles[] = self::normalizeRoleName((string) $user['role_name']);
        }

        return array_values(array_unique(array_filter($roles)));
    } catch (\Throwable $e) {
        return [];
    }
}

public static function hasRole(string $role): bool
{
    $normalizedRole = self::normalizeRoleName($role);
    $roles = self::roles();

    if ($normalizedRole === 'admin') {
        return self::isAdminDentistAccount()
            && (in_array('admin', $roles, true) || in_array('owner', $roles, true));
    }

    if ($normalizedRole === 'dentist' && self::isAdminDentistAccount()) {
        return in_array('dentist', $roles, true) || self::hasDentistProfile();
    }

    if ($normalizedRole === 'dentist' && in_array('admin', $roles, true)) {
        return false;
    }

    return in_array($normalizedRole, $roles, true);
}

public static function isAdminDentistAccount(): bool
{
    return (int) (self::user()['user_id'] ?? 0) === self::ADMIN_DENTIST_USER_ID;
}

private static function hasDentistProfile(): bool
{
    try {
        $stmt = \App\Core\Database::getConnection()->prepare(
            'SELECT 1 FROM dentists WHERE user_id = :user_id LIMIT 1'
        );
        $stmt->execute([':user_id' => self::ADMIN_DENTIST_USER_ID]);

        return (bool) $stmt->fetchColumn();
    } catch (\Throwable $e) {
        return false;
    }
}

public static function hasAnyRole(array $roles): bool
{
    foreach ($roles as $role) {
        if (self::hasRole((string) $role)) {
            return true;
        }
    }

    return false;
}

public static function requireRole(string $role): void
{
    if (!self::check()) {
        header('Location: /DentalClinic/public/login');
        exit;
    }

    if (!self::hasRole($role)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

public static function requireAnyRole(array $roles): void
{
    if (!self::check()) {
        header('Location: /DentalClinic/public/login');
        exit;
    }

    if (!self::hasAnyRole($roles)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

private static function normalizeRoleName(string $role): string
{
    $role = strtolower(trim($role));
    $role = str_replace([' ', '-'], '_', $role);

    return match ($role) {
        'administrator' => 'admin',
        'owner' => 'admin',
        'admin' => 'admin',
        'admin_dentist' => 'admin',
        'admin/dentist' => 'admin',
        'owner_admin' => 'admin',
        'owner/admin' => 'admin',
        'doctor' => 'dentist',
        'dentist' => 'dentist',
        'medical_staff' => 'staff',
        'staff' => 'staff',
        'patient' => 'patient',
        default => $role,
    };
}









}