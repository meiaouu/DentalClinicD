<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\PatientRepository;
use App\Services\PrivacyRequestService;
use RuntimeException;
use Throwable;

class PrivacyRequestController
{
    private PrivacyRequestService $service;
    private PatientRepository $patients;

    public function __construct()
    {
        $this->service = new PrivacyRequestService();
        $this->patients = new PatientRepository();
    }

    public function create(): void
    {
        $authUser = Auth::check() ? Auth::user() : null;
        $patient = null;

        if ($authUser && ($authUser['role_name'] ?? '') === 'patient') {
            $patient = $this->patients->findByUserId((int) $authUser['user_id']);
        }

        View::render('privacy_request.create', [
            'old' => Session::get('old', []),
            'errors' => Session::get('errors', []),
            'success' => Session::get('success'),
            'authUser' => $authUser,
            'patient' => $patient,
        ]);

        Session::remove('old');
        Session::remove('errors');
        Session::remove('success');
    }

    public function store(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $authUser = Auth::check() ? Auth::user() : null;
        $patient = null;

        if ($authUser && ($authUser['role_name'] ?? '') === 'patient') {
            $patient = $this->patients->findByUserId((int) $authUser['user_id']);
        }

        try {
            $fullName = trim((string) ($_POST['full_name'] ?? ''));

            if ($patient) {
                $fullName = trim(
                    (string) (($patient['first_name'] ?? '') . ' ' .
                    ($patient['middle_name'] ?? '') . ' ' .
                    ($patient['last_name'] ?? ''))
                );
            }

            $this->service->submitRequest([
                'user_id' => $authUser ? (int) ($authUser['user_id'] ?? 0) : null,
                'patient_id' => $patient ? (int) ($patient['patient_id'] ?? 0) : null,
                'full_name' => $fullName,
                'email' => $patient['email'] ?? trim((string) ($_POST['email'] ?? '')),
                'contact_number' => $patient['contact_number'] ?? trim((string) ($_POST['contact_number'] ?? '')),
                'request_type' => trim((string) ($_POST['request_type'] ?? '')),
                'request_details' => trim((string) ($_POST['request_details'] ?? '')),
            ]);

            Session::set('success', 'Privacy request submitted successfully.');
            header('Location: ' . $this->url('/privacy-request'));
            exit;
        } catch (RuntimeException $e) {
            Session::set('errors', [
                'privacy_request' => [$e->getMessage()],
            ]);
        } catch (Throwable $e) {
            error_log('[PrivacyRequestController] ' . $e->getMessage());

            Session::set('errors', [
                'privacy_request' => ['Something went wrong. Please try again.'],
            ]);
        }

        Session::set('old', $_POST);
        header('Location: ' . $this->url('/privacy-request'));
        exit;
    }

    private function url(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }
}