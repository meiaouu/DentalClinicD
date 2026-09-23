<?php

namespace App\Controllers\Auth;

use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Services\PatientAccountProvisioningService;
use RuntimeException;
use Throwable;

class PasswordSetupController
{
    private PatientAccountProvisioningService $accounts;

    public function __construct()
    {
        $this->accounts = new PatientAccountProvisioningService();
    }

    public function show(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        $user = $this->accounts->validatePasswordSetupToken($token);

        View::render('auth.setup-password', [
            'token' => $token,
            'isValidToken' => $user !== null,
            'errors' => Session::get('errors', []),
            'flash_error' => Session::get('flash_error'),
            'flash_success' => Session::get('flash_success'),
        ]);

        Session::remove('errors');
        Session::remove('flash_error');
        Session::remove('flash_success');
    }

    public function submit(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        try {
            $this->accounts->activateAccount($token, $password, $passwordConfirmation);

            Session::set('flash_success', 'Your account is now active. You can log in.');
            header('Location: ' . $this->url('/login'));
            exit;
        } catch (RuntimeException $e) {
            Session::set('flash_error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('[PasswordSetupController] ' . $e->getMessage());
            Session::set('flash_error', 'Unable to set password. Please try again.');
        }

        header('Location: ' . $this->url('/setup-password?token=' . rawurlencode($token)));
        exit;
    }

    private function url(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }
}