<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;
use PDO;
use RuntimeException;
use Throwable;

class AuthController
{
    private const GENERIC_LOGIN_ERROR = 'Invalid username/email or password.';
    private const MAX_FAILED_ATTEMPTS = 5;
    private const ATTEMPT_WINDOW_MINUTES = 15;
    private const LOCKOUT_MINUTES = 15;

    private AuthService $authService;
    private PDO $db;

    private array $tableColumnsCache = [];

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->db = Database::getConnection();

        $this->ensureLoginAttemptsTable();
    }

    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: ' . $this->prefix($this->authService->resolveRedirectByRole(Auth::user())));
            exit;
        }

        View::render('auth.login', [
            'errors' => Session::get('errors', []),
            'old' => Session::get('old', []),
            'success' => Session::get('success'),
        ]);

        Session::remove('errors');
        Session::remove('old');
        Session::remove('success');
    }

    public function login(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::set('errors', [
                'login' => ['Invalid request. Please refresh the page and try again.'],
            ]);

            header('Location: ' . $this->prefix('/login'));
            exit;
        }

        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ipAddress = $this->ipAddress();
        $userAgent = $this->userAgent();

        if ($login === '' || $password === '') {
            $this->failLogin($login);
        }

        try {
            if ($this->isLoginLocked($login, $ipAddress)) {
                $this->writeAuthAudit(
                    null,
                    'login_locked',
                    'Blocked login during temporary lockout.',
                    $ipAddress,
                    $userAgent
                );

                Session::set('errors', [
                    'login' => ['Too many failed attempts. Please try again after 15 minutes.'],
                ]);

                Session::set('old', [
                    'login' => $login,
                ]);

                header('Location: ' . $this->prefix('/login'));
                exit;
            }

            $user = $this->findUserByLoginIdentifier($login);

            if (!$user || empty($user['password']) || !password_verify($password, (string) $user['password'])) {
                $this->recordFailedLoginAttempt($login, $ipAddress, $user ? (int) $user['user_id'] : null);
                $this->failLogin($login);
            }

            $accountStatus = strtolower(trim((string) ($user['account_status'] ?? 'active')));
            $roleName = strtolower((string) ($user['role_name'] ?? $user['role'] ?? ''));

            if ($accountStatus === 'pending_password_setup') {
                $this->writeAuthAudit(
                    (int) $user['user_id'],
                    'login_requires_password_setup',
                    'User was redirected to password setup.',
                    $ipAddress,
                    $userAgent
                );

                Session::set('success', 'Please set your password before logging in.');
                header('Location: ' . $this->prefix('/setup-password'));
                exit;
            }

            if (isset($user['is_active']) && (int) $user['is_active'] !== 1) {
                $this->writeAuthAudit(
                    (int) $user['user_id'],
                    'login_blocked',
                    'Blocked login because account is inactive.',
                    $ipAddress,
                    $userAgent
                );

                $this->failLogin($login);
            }

            if ($roleName === 'patient' && $accountStatus === 'pending_review') {
                session_regenerate_id(true);

                $safeUser = $this->safeSessionUser($user);

                Auth::login($safeUser);

                $this->updateLastLoginAt((int) $user['user_id']);
                $this->clearLoginAttempts($login, $ipAddress);

                $this->writeAuthAudit(
                    (int) $user['user_id'],
                    'login_pending_review',
                    'Pending patient logged in and was sent to dashboard completion flow.',
                    $ipAddress,
                    $userAgent
                );

                header('Location: ' . $this->prefix('/patient/dashboard'));
                exit;
            }

            if ($roleName === 'patient' && $accountStatus === 'rejected') {
                session_regenerate_id(true);

                $safeUser = $this->safeSessionUser($user);

                Auth::login($safeUser);

                $this->updateLastLoginAt((int) $user['user_id']);
                $this->clearLoginAttempts($login, $ipAddress);

                $this->writeAuthAudit(
                    (int) $user['user_id'],
                    'login_rejected_registration',
                    'Rejected patient registration attempted portal access.',
                    $ipAddress,
                    $userAgent
                );

                header('Location: ' . $this->prefix('/patient/pending-review'));
                exit;
            }

            if ($accountStatus !== 'active') {
                $this->writeAuthAudit(
                    (int) $user['user_id'],
                    'login_blocked',
                    'Blocked login because account status is not active.',
                    $ipAddress,
                    $userAgent
                );

                $this->failLogin($login);
            }

            if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
                $this->rehashPassword((int) $user['user_id'], $password);
            }

            session_regenerate_id(true);

            $safeUser = $this->safeSessionUser($user);

            Auth::login($safeUser);

            $this->updateLastLoginAt((int) $user['user_id']);
            $this->clearLoginAttempts($login, $ipAddress);

            $this->writeAuthAudit(
                (int) $user['user_id'],
                'login_success',
                'User logged in successfully.',
                $ipAddress,
                $userAgent
            );

            header('Location: ' . $this->prefix($this->authService->resolveRedirectByRole($safeUser)));
            exit;
        } catch (Throwable $e) {
            error_log('Login error: ' . $e->getMessage());

            $this->recordFailedLoginAttempt($login, $ipAddress, null);
            $this->failLogin($login);
        }
    }

    public function showRegister(): void
    {
        if (Auth::check()) {
            header('Location: ' . $this->prefix($this->authService->resolveRedirectByRole(Auth::user())));
            exit;
        }

        View::render('auth.register', [
            'errors' => Session::get('errors', []),
            'old' => Session::get('old', []),
        ]);

        Session::remove('errors');
        Session::remove('old');
    }

    public function register(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::set('errors', [
                'register' => ['Invalid request. Please refresh the page and try again.'],
            ]);

            header('Location: ' . $this->prefix('/register'));
            exit;
        }

        $data = [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'middle_name' => trim((string) ($_POST['middle_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'sex' => trim((string) ($_POST['sex'] ?? '')),
            'birth_date' => trim((string) ($_POST['birth_date'] ?? '')),
            'civil_status' => trim((string) ($_POST['civil_status'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'occupation' => trim((string) ($_POST['occupation'] ?? '')),
            'contact_number' => $this->normalizePhoneInput((string) ($_POST['contact_number'] ?? '')),
            'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'password' => (string) ($_POST['password'] ?? ''),
            'password_confirmation' => (string) ($_POST['password_confirmation'] ?? ''),
        ];

        try {
            $this->validateRegistrationData($data);

            $userId = $this->authService->registerPatient($data);

            $this->writeAuthAudit(
                $userId,
                'patient_registration_submitted',
                'Patient registration submitted for staff review.',
                $this->ipAddress(),
                $this->userAgent()
            );

            $stmt = $this->db->prepare("SELECT u.*, r.role_name FROM users u INNER JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = :user_id LIMIT 1");
            $stmt->execute([':user_id' => $userId]);
            $registeredUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registeredUser) {
                throw new RuntimeException('Registration completed, but the account could not be loaded. Please log in.');
            }

            Auth::login($this->safeSessionUser($registeredUser));
            Session::set('flash_success', 'Registration complete. Please finish your patient information.');
            header('Location: ' . $this->prefix('/patient/dashboard'));
            exit;
        } catch (RuntimeException $e) {
            Session::set('errors', [
                'register' => [$e->getMessage()],
            ]);

            Session::set('old', $this->safeOldInput($data));

            header('Location: ' . $this->prefix('/register'));
            exit;
        } catch (Throwable $e) {
            error_log('Registration error: ' . $e->getMessage());

            Session::set('errors', [
                'register' => ['Registration failed. Please check your details and try again.'],
            ]);

            Session::set('old', $this->safeOldInput($data));

            header('Location: ' . $this->prefix('/register'));
            exit;
        }
    }

    public function logout(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::set('errors', [
                'login' => ['Invalid logout request. Please try again.'],
            ]);

            header('Location: ' . $this->prefix('/login'));
            exit;
        }

        $user = Auth::user();
        $userId = isset($user['user_id']) ? (int) $user['user_id'] : null;

        $this->writeAuthAudit(
            $userId,
            'logout',
            'User logged out.',
            $this->ipAddress(),
            $this->userAgent()
        );

        Session::remove('user');
        Session::remove('auth_user');

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();

                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    (bool) $params['secure'],
                    (bool) $params['httponly']
                );
            }

            session_destroy();
        }

        session_start();
        session_regenerate_id(true);

        header('Location: ' . $this->prefix('/login'));
        exit;
    }

    private function validateRegistrationData(array $data): void
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

        if (!in_array($data['sex'], ['female', 'male'], true)) {
            throw new RuntimeException('Please select a valid sex.');
        }

        if ($data['birth_date'] === '') {
            throw new RuntimeException('Birth date is required.');
        }

        $birthTimestamp = strtotime($data['birth_date']);

        if (!$birthTimestamp || date('Y-m-d', $birthTimestamp) > date('Y-m-d')) {
            throw new RuntimeException('Please enter a valid birth date.');
        }

        if ($data['civil_status'] !== '') {
            $allowedCivilStatuses = [
                'Single',
                'Married',
                'Widowed',
                'Separated',
            ];

            if (!in_array($data['civil_status'], $allowedCivilStatuses, true)) {
                throw new RuntimeException('Please select a valid civil status.');
            }
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

    private function safeSessionUser(array $user): array
    {
        $roleName = strtolower((string) ($user['role_name'] ?? $user['role'] ?? ''));

        return [
            'user_id' => (int) ($user['user_id'] ?? 0),
            'role_id' => (int) ($user['role_id'] ?? 0),
            'role' => $roleName,
            'role_name' => $roleName,
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'first_name' => (string) ($user['first_name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'account_status' => strtolower((string) ($user['account_status'] ?? 'active')),
            'logged_in' => time(),
            'last_activity' => time(),
        ];
    }

    private function failLogin(string $login): void
    {
        Session::set('errors', [
            'login' => [self::GENERIC_LOGIN_ERROR],
        ]);

        Session::set('old', [
            'login' => $login,
        ]);

        header('Location: ' . $this->prefix('/login'));
        exit;
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

    private function recordFailedLoginAttempt(string $login, string $ipAddress, ?int $userId = null): void
    {
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
                $this->userAgent()
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
        return strtolower(trim($login));
    }

    private function normalizePhoneInput(string $number): string
    {
        return preg_replace('/[\s-]+/', '', trim($number)) ?? '';
    }

    private function isValidPhilippineMobile(string $number): bool
    {
        return preg_match('/^(09\d{9}|639\d{9}|\+639\d{9})$/', $number) === 1;
    }

    private function safeOldInput(array $data): array
    {
        unset($data['password'], $data['password_confirmation']);

        return $data;
    }

    private function ipAddress(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 60);
    }

    private function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    private function prefix(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }
}