<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\UserSettingsRepository;
use RuntimeException;
use Throwable;

class SettingsController
{
    private UserSettingsRepository $settings;

    public function __construct()
    {
        $this->settings = new UserSettingsRepository();
    }

    public function index(): void
    {
        $user = $this->requireLoggedIn();

        $userId = (int) $user['user_id'];
        $settings = $this->settings->findByUserId($userId);
        $freshUser = $this->settings->findUserById($userId) ?: $user;

        View::render('settings.index', [
            'pageTitle' => 'Settings',
            'title' => 'Settings',
            'authUser' => $freshUser,
            'settings' => $settings,
            'activeTab' => trim((string) ($_GET['tab'] ?? 'account')),
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function updateAccount(): void
{
    $user = $this->requireLoggedIn();

    if (!$this->verifyCsrf()) {
        $this->fail('Invalid CSRF token. Please refresh the page and try again.');
    }

    $userId = (int) $user['user_id'];

    try {
        $accountAction = strtolower(trim((string) ($_POST['account_action'] ?? '')));
        $currentPassword = (string) ($_POST['current_password'] ?? '');

        if ($accountAction === '') {
            throw new RuntimeException('Invalid account update action.');
        }

        if ($currentPassword === '') {
            throw new RuntimeException('Current password is required.');
        }

        $storedHash = $this->settings->getPasswordHashForUser($userId);

        if ($storedHash === '' || !password_verify($currentPassword, $storedHash)) {
            throw new RuntimeException('Current password is incorrect.');
        }

        $data = [];
        $sessionUpdates = [
            'user_id' => $userId,
        ];

        if ($accountAction === 'username') {
            $username = strtolower(trim((string) ($_POST['username'] ?? '')));

            if ($username === '') {
                throw new RuntimeException('Username is required.');
            }

            if (!preg_match('/^[a-zA-Z0-9_]{3,100}$/', $username)) {
                throw new RuntimeException('Username must be 3 to 100 characters and may only contain letters, numbers, and underscore.');
            }

            if ($this->settings->usernameExistsForOtherUser($username, $userId)) {
                throw new RuntimeException('This username is already used by another account.');
            }

            $data['username'] = $username;
            $sessionUpdates['username'] = $username;
        } elseif ($accountAction === 'name') {
            $firstName = $this->requiredText($_POST['first_name'] ?? '', 'First name is required.');
            $lastName = $this->requiredText($_POST['last_name'] ?? '', 'Last name is required.');

            $data['first_name'] = $firstName;
            $data['last_name'] = $lastName;

            $sessionUpdates['first_name'] = $firstName;
            $sessionUpdates['last_name'] = $lastName;
        } elseif ($accountAction === 'email') {
            $email = strtolower($this->requiredText($_POST['email'] ?? '', 'Email is required.'));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid email address.');
            }

            if ($this->settings->emailExistsForOtherUser($email, $userId)) {
                throw new RuntimeException('This email is already used by another account.');
            }

            $data['email'] = $email;
            $sessionUpdates['email'] = $email;
        } elseif ($accountAction === 'contact') {
            $contactNumber = trim((string) ($_POST['contact_number'] ?? ''));

            if ($contactNumber === '') {
                throw new RuntimeException('Contact number is required.');
            }

            if (!$this->isValidPhilippineMobile($contactNumber)) {
                throw new RuntimeException('Invalid contact number. Use 09XXXXXXXXX, 639XXXXXXXXX, or +639XXXXXXXXX.');
            }

            $data['contact_number'] = $contactNumber;
            $sessionUpdates['contact_number'] = $contactNumber;
        } elseif ($accountAction === 'password') {
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_new_password'] ?? '');

            if (strlen($newPassword) < 8) {
                throw new RuntimeException('New password must be at least 8 characters.');
            }

            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('New password and confirmation do not match.');
            }

            if (password_verify($newPassword, $storedHash)) {
                throw new RuntimeException('New password must be different from your current password.');
            }

            $data['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        } else {
            throw new RuntimeException('Invalid account update action.');
        }

        $this->settings->updateAccount($userId, $data);

        if (count($sessionUpdates) > 1) {
            $this->refreshSessionUser($sessionUpdates);
        }

        $this->success('Account setting updated successfully.');
    } catch (Throwable $e) {
        $this->fail($e->getMessage());
    }
}

    public function updatePrivacy(): void
    {
        $user = $this->requireLoggedIn();

        if (!$this->verifyCsrf()) {
            $this->fail('Invalid CSRF token. Please refresh the page and try again.', 'privacy');
        }

        try {
            $this->settings->updatePrivacy((int) $user['user_id'], $_POST);
            $this->success('Privacy settings saved successfully.', 'privacy');
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 'privacy');
        }
    }

    public function updateLanguage(): void
    {
        $user = $this->requireLoggedIn();

        if (!$this->verifyCsrf()) {
            $this->fail('Invalid CSRF token. Please refresh the page and try again.', 'language');
        }

        try {
            $language = strtolower(trim((string) ($_POST['language'] ?? 'english')));

            $this->settings->updateLanguage((int) $user['user_id'], $language);
            $this->success('Language preference saved successfully.', 'language');
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 'language');
        }
    }

    public function updateAppearance(): void
    {
        $user = $this->requireLoggedIn();

        if (!$this->verifyCsrf()) {
            $this->fail('Invalid CSRF token. Please refresh the page and try again.', 'appearance');
        }

        try {
            $this->settings->updateAppearance((int) $user['user_id'], $_POST);

            if ($this->wantsJson()) {
                $settings = $this->settings->findByUserId((int) $user['user_id']);

                $this->json([
                    'success' => true,
                    'message' => 'Appearance settings saved successfully.',
                    'settings' => $settings,
                    'body_classes' => $this->settings->bodyClasses($settings),
                ]);
            }

            $this->success('Appearance settings saved successfully.', 'appearance');
        } catch (Throwable $e) {
            if ($this->wantsJson()) {
                $this->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            $this->fail($e->getMessage(), 'appearance');
        }
    }

    public function resetAppearance(): void
    {
        $user = $this->requireLoggedIn();

        if (!$this->verifyCsrf()) {
            $this->fail('Invalid CSRF token. Please refresh the page and try again.', 'appearance');
        }

        try {
            $this->settings->resetAppearance((int) $user['user_id']);

            if ($this->wantsJson()) {
                $settings = $this->settings->findByUserId((int) $user['user_id']);

                $this->json([
                    'success' => true,
                    'message' => 'Appearance settings reset successfully.',
                    'settings' => $settings,
                    'body_classes' => $this->settings->bodyClasses($settings),
                ]);
            }

            $this->success('Appearance settings reset successfully.', 'appearance');
        } catch (Throwable $e) {
            if ($this->wantsJson()) {
                $this->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            $this->fail($e->getMessage(), 'appearance');
        }
    }

    private function requireLoggedIn(): array
    {
        if (method_exists(Auth::class, 'requireLogin')) {
            Auth::requireLogin();
        }

        $user = Auth::user();

        if (!$user || empty($user['user_id'])) {
            header('Location: /DentalClinic/public/login');
            exit;
        }

        return $user;
    }

    private function verifyCsrf(): bool
    {
        $token = $_POST['_csrf_token']
            ?? $_POST['_token']
            ?? $_POST['csrf_token']
            ?? null;

        return Csrf::verify($token);
    }

    private function requiredText(mixed $value, string $message, int $maxLength = 255): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new RuntimeException($message);
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function isValidPhilippineMobile(string $number): bool
    {
        return preg_match('/^(09\d{9}|639\d{9}|\+639\d{9})$/', $number) === 1;
    }

    private function refreshSessionUser(array $updatedUser): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        foreach (['user', 'auth_user'] as $key) {
            if (!empty($_SESSION[$key]) && is_array($_SESSION[$key])) {
                if ((int) ($_SESSION[$key]['user_id'] ?? 0) === (int) $updatedUser['user_id']) {
                    $_SESSION[$key] = array_merge($_SESSION[$key], $updatedUser);
                }
            }
        }
    }

    private function success(string $message, string $tab = 'account'): void
    {
        if ($this->wantsJson()) {
            $this->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        Session::set('flash_success', $message);
        header('Location: /DentalClinic/public/settings?tab=' . urlencode($tab));
        exit;
    }

    private function fail(string $message, string $tab = 'account'): void
    {
        if ($this->wantsJson()) {
            $this->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        Session::set('flash_error', $message);
        header('Location: /DentalClinic/public/settings?tab=' . urlencode($tab));
        exit;
    }

    private function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    private function json(array $payload, int $statusCode = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}