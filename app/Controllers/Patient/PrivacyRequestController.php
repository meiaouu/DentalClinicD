<?php

namespace App\Controllers\Patient;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\PatientRepository;
use App\Repositories\PrivacyRequestRepository;
use App\Services\PrivacyRequestService;
use RuntimeException;
use Throwable;

class PrivacyRequestController
{
    private PatientRepository $patients;
    private PrivacyRequestRepository $requests;
    private PrivacyRequestService $service;

    public function __construct()
    {
        $this->patients = new PatientRepository();
        $this->requests = new PrivacyRequestRepository();
        $this->service = new PrivacyRequestService();
    }

    public function index(): void
    {
        Auth::requireRole('patient');

        $patient = $this->currentPatient();

        View::render('patient.privacy_requests.index', [
            'patient' => $patient,
            'requests' => $this->requests->findByPatientId((int) $patient['patient_id']),
            'success' => Session::get('success'),
            'errors' => Session::get('errors', []),
        ]);

        Session::remove('success');
        Session::remove('errors');
    }

    public function create(): void
    {
        Auth::requireRole('patient');

        View::render('patient.privacy_requests.create', [
            'patient' => $this->currentPatient(),
            'old' => Session::get('old', []),
            'errors' => Session::get('errors', []),
        ]);

        Session::remove('old');
        Session::remove('errors');
    }

    public function store(): void
    {
        Auth::requireRole('patient');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $user = Auth::user();
        $patient = $this->currentPatient();

        try {
            $fullName = trim(
                (string) (($patient['first_name'] ?? '') . ' ' .
                ($patient['middle_name'] ?? '') . ' ' .
                ($patient['last_name'] ?? ''))
            );

            $this->service->submitRequest([
                'user_id' => (int) $user['user_id'],
                'patient_id' => (int) $patient['patient_id'],
                'full_name' => $fullName,
                'email' => $patient['email'] ?? null,
                'contact_number' => $patient['contact_number'] ?? null,
                'request_type' => trim((string) ($_POST['request_type'] ?? '')),
                'request_details' => trim((string) ($_POST['request_details'] ?? '')),
            ]);

            Session::set('success', 'Privacy request submitted successfully.');
            header('Location: ' . $this->url('/patient/privacy-requests'));
            exit;
        } catch (RuntimeException $e) {
            Session::set('errors', [
                'privacy_request' => [$e->getMessage()],
            ]);
        } catch (Throwable $e) {
            error_log('[Patient\PrivacyRequestController] ' . $e->getMessage());

            Session::set('errors', [
                'privacy_request' => ['Something went wrong. Please try again.'],
            ]);
        }

        Session::set('old', $_POST);
        header('Location: ' . $this->url('/patient/privacy-requests/create'));
        exit;
    }

    private function currentPatient(): array
    {
        $user = Auth::user();
        $patient = $this->patients->findByUserId((int) ($user['user_id'] ?? 0));

        if (!$patient) {
            throw new RuntimeException('Patient profile not found.');
        }

        return $patient;
    }

    private function url(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }
}