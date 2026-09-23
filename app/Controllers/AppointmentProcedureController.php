<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Repositories\DentistLookupRepository;
use App\Services\AppointmentProcedureWorkflowService;
use App\Services\PatientConversionService;
use PDO;
use RuntimeException;

class AppointmentProcedureController
{
    private AppointmentProcedureWorkflowService $workflow;
    private PatientConversionService $patientConversion;
    private DentistLookupRepository $dentists;
    private PDO $db;

    public function __construct()
    {
        $this->workflow = new AppointmentProcedureWorkflowService();
        $this->patientConversion = new PatientConversionService();
        $this->dentists = new DentistLookupRepository();
        $this->db = Database::getConnection();
    }

    public function startProcedure(): void
    {
        $this->handleProcedureAction('start');
    }

    public function completeProcedure(): void
    {
        $this->handleProcedureAction('complete');
    }

    private function handleProcedureAction(string $action): void
    {
        $role = $this->requireStaffOrDentist();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $user = Auth::user();
        $userId = (int) ($user['user_id'] ?? 0);
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $redirectTo = $this->sanitizeRedirect((string) ($_POST['redirect_to'] ?? ''));
        $allowedDentistId = $role === 'dentist' ? $this->resolveDentistId($userId) : null;

        try {
            if ($appointmentId <= 0) {
                throw new RuntimeException('Invalid appointment ID.');
            }

            if ($action === 'start') {
                $updated = $this->workflow->startProcedure(
                    $appointmentId,
                    $userId,
                    $remarks !== '' ? $remarks : null,
                    $allowedDentistId
                );

                Session::set('flash_success', 'Procedure started using the actual server time.');
            } elseif ($action === 'complete') {
                $updated = $this->workflow->completeProcedure(
                    $appointmentId,
                    $userId,
                    $remarks !== '' ? $remarks : null,
                    $allowedDentistId
                );

                if ($role === 'staff' && empty($updated['patient_id'])) {
                    $this->patientConversion->convertCompletedAppointment($appointmentId, $userId);
                }

                Session::set('flash_success', 'Procedure completed using the actual server time.');
            } else {
                throw new RuntimeException('Invalid procedure action.');
            }

            if ($redirectTo === '') {
                $redirectTo = $this->defaultRedirect($role, $updated ?? []);
            }
        } catch (\Throwable $e) {
            Session::set('flash_error', $e->getMessage());

            if ($redirectTo === '') {
                $redirectTo = $this->defaultRedirect($role, []);
            }
        }

        header('Location: ' . $redirectTo);
        exit;
    }

    private function requireStaffOrDentist(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

        if (str_contains($path, '/dentist/')) {
            Auth::requireRole('dentist');
            return 'dentist';
        }

        if (str_contains($path, '/staff/')) {
            Auth::requireRole('staff');
            return 'staff';
        }

        $user = Auth::user();
        $roleName = strtolower(trim((string) ($user['role_name'] ?? $user['role'] ?? '')));

        if ($roleName === 'dentist') {
            Auth::requireRole('dentist');
            return 'dentist';
        }

        Auth::requireRole('staff');
        return 'staff';
    }

    private function resolveDentistId(int $userId): int
    {
        if ($userId <= 0) {
            throw new RuntimeException('Invalid authenticated dentist account.');
        }

        if (method_exists($this->dentists, 'findByUserId')) {
            $dentist = $this->dentists->findByUserId($userId);
            if (is_array($dentist) && !empty($dentist['dentist_id'])) {
                return (int) $dentist['dentist_id'];
            }
        }

        $stmt = $this->db->prepare("
            SELECT dentist_id
            FROM dentists
            WHERE user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $dentistId = (int) ($stmt->fetchColumn() ?: 0);

        if ($dentistId <= 0) {
            throw new RuntimeException('Dentist profile not found.');
        }

        return $dentistId;
    }

    private function sanitizeRedirect(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $base = '/DentalClinic/public';

        if (str_starts_with($url, $base . '/')) {
            return $url;
        }

        if (str_starts_with($url, '/staff/') || str_starts_with($url, '/dentist/')) {
            return $base . $url;
        }

        return '';
    }

    private function defaultRedirect(string $role, array $appointment): string
    {
        $base = '/DentalClinic/public';

        if ($role === 'dentist') {
            return $base . '/dentist/appointments';
        }

        $date = trim((string) ($appointment['appointment_date'] ?? ''));

        if ($date === '') {
            $date = date('Y-m-d');
        }

        return $base . '/staff/appointments?date=' . urlencode($date);
    }
}
