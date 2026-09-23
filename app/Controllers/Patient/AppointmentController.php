<?php

namespace App\Controllers\Patient;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AppointmentRepository;
use App\Repositories\AppointmentRequestRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\PatientRepository;

class AppointmentController
{
    private PatientRepository $patients;
    private AppointmentRequestRepository $requests;
    private AppointmentRepository $appointments;
    private AuditLogRepository $auditLogs;

    public function __construct()
    {
        $this->patients = new PatientRepository();
        $this->requests = new AppointmentRequestRepository();
        $this->appointments = new AppointmentRepository();
        $this->auditLogs = new AuditLogRepository();
    }

    public function index(): void
    {
        Auth::requireRole('patient');

        $user = Auth::user();
        $patient = $this->patients->findByUserId((int) $user['user_id']);

        if (!$patient) {
            http_response_code(404);
            exit('Patient profile not found.');
        }

        $requestPage = max(1, (int) ($_GET['request_page'] ?? 1));
        $appointmentPage = max(1, (int) ($_GET['appointment_page'] ?? 1));
        $perPage = 10;

        View::render('patient.appointments.index', [
            'patient' => $patient,
            'requests' => $this->requests->getByPatientId(
                (int) $patient['patient_id'],
                $perPage,
                ($requestPage - 1) * $perPage
            ),
            'appointments' => $this->appointments->getByPatientId(
                (int) $patient['patient_id'],
                $perPage,
                ($appointmentPage - 1) * $perPage
            ),
            'requestPage' => $requestPage,
            'appointmentPage' => $appointmentPage,
            'requestTotal' => $this->requests->countByPatientId((int) $patient['patient_id']),
            'appointmentTotal' => $this->appointments->countByPatientId((int) $patient['patient_id']),
            'perPage' => $perPage,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function showRequest(): void
    {
        Auth::requireRole('patient');

        $user = Auth::user();
        $patient = $this->patients->findByUserId((int) $user['user_id']);

        if (!$patient) {
            http_response_code(404);
            exit('Patient profile not found.');
        }

        $requestId = (int) ($_GET['id'] ?? 0);
        $request = $this->requests->findByIdAndPatientId($requestId, (int) $patient['patient_id']);

        if (!$request) {
            http_response_code(404);
            exit('Request not found.');
        }

        View::render('patient.appointments.request_show', [
            'request' => $request,
            'answers' => $this->requests->getAnswersByRequestId($requestId),
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function showAppointment(): void
    {
        Auth::requireRole('patient');

        $user = Auth::user();
        $patient = $this->patients->findByUserId((int) $user['user_id']);

        if (!$patient) {
            http_response_code(404);
            exit('Patient profile not found.');
        }

        $appointmentId = (int) ($_GET['id'] ?? 0);
        $appointment = $this->appointments->findByIdAndPatientId($appointmentId, (int) $patient['patient_id']);

        if (!$appointment) {
            http_response_code(404);
            exit('Appointment not found.');
        }

        View::render('patient.appointments.show', [
            'appointment' => $appointment,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function cancelRequest(): void
    {
        Auth::requireRole('patient');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $user = Auth::user();
        $patient = $this->patients->findByUserId((int) $user['user_id']);

        if (!$patient) {
            Session::set('flash_error', 'Patient profile not found.');
            header('Location: ' . $this->prefix('/patient/appointments'));
            exit;
        }

        $requestId = (int) ($_POST['request_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));

        try {
            $request = $this->requests->findByIdAndPatientId($requestId, (int) $patient['patient_id']);

            if (!$request) {
                throw new \RuntimeException('Request not found.');
            }

            $this->requests->cancelPendingByPatient(
                $requestId,
                (int) $patient['patient_id'],
                $reason !== '' ? ('Cancelled by patient. Reason: ' . $reason) : 'Cancelled by patient.'
            );

            $this->auditLogs->create([
                'user_id' => (int) $user['user_id'],
                'module_name' => 'appointment_requests',
                'action_name' => 'cancel_by_patient',
                'record_type' => 'appointment_request',
                'record_id' => $requestId,
                'description' => 'Registered patient cancelled own pending request.',
            ]);

            Session::set('flash_success', 'Appointment request cancelled successfully.');
        } catch (\Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: ' . $this->prefix('/patient/appointments'));
        exit;
    }

    private function prefix(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }
}