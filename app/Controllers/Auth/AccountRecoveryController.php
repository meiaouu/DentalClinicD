<?php

namespace App\Controllers\Auth;

use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Services\PasswordResetService;
use Throwable;

class AccountRecoveryController
{
    private PasswordResetService $passwordReset;

    public function __construct()
    {
        $this->passwordReset = new PasswordResetService();
    }

    public function show(): void
    {
        View::render('auth.account-recovery', [
            'errors' => Session::get('errors', []),
            'success' => Session::get('success'),
            'old' => Session::get('old', []),
        ]);

        Session::remove('errors');
        Session::remove('success');
        Session::remove('old');
    }

    public function store(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::set('errors', [
                'recovery' => ['Invalid request. Please refresh the page and try again.'],
            ]);

            header('Location: /DentalClinic/public/account-recovery');
            exit;
        }

        $data = [
            'requester_type' => trim((string) ($_POST['requester_type'] ?? 'unknown')),
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'birth_date' => trim((string) ($_POST['birth_date'] ?? '')),
            'last_known_email' => trim((string) ($_POST['last_known_email'] ?? '')),
            'last_known_phone' => trim((string) ($_POST['last_known_phone'] ?? '')),
            'appointment_or_request_code' => trim((string) ($_POST['appointment_or_request_code'] ?? '')),
            'message' => trim((string) ($_POST['message'] ?? '')),
            'privacy_consent' => isset($_POST['privacy_consent']) ? 1 : 0,
        ];

        try {
            $this->passwordReset->createManualRecoveryRequest(
                $data,
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
            );

            Session::set('success', 'Your recovery request was submitted. Clinic staff will review it manually.');
            header('Location: /DentalClinic/public/account-recovery');
            exit;
        } catch (Throwable $e) {
            Session::set('errors', [
                'recovery' => [$e->getMessage()],
            ]);

            Session::set('old', $data);

            header('Location: /DentalClinic/public/account-recovery');
            exit;
        }
    }
}