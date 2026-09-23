<?php

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AppointmentReminderRepository;
use App\Services\AppointmentReminderService;
use Throwable;

class BulkMessageController
{
    private AppointmentReminderRepository $appointments;
    private AppointmentReminderService $reminders;

    public function __construct()
    {
        $this->appointments = new AppointmentReminderRepository();
        $this->reminders = new AppointmentReminderService();
    }

    public function index(): void
    {
        Auth::requireRole('staff');

        $filters = [
            'date_from' => trim((string) ($_GET['date_from'] ?? date('Y-m-d'))),
            'date_to' => trim((string) ($_GET['date_to'] ?? date('Y-m-d', strtotime('+14 days')))),
            'dentist_id' => (int) ($_GET['dentist_id'] ?? 0),
            'service_id' => (int) ($_GET['service_id'] ?? 0),
            'reminder_type' => trim((string) ($_GET['reminder_type'] ?? '')),
        ];

        if (!$this->isValidReminderTypeOrEmpty($filters['reminder_type'])) {
            $filters['reminder_type'] = '';
        }

        $appointments = $this->appointments->findUpcomingConfirmedAppointments($filters);
        $dentists = $this->appointments->getActiveDentists();
        $services = $this->appointments->getActiveServices();

        View::render('staff/messages/bulk', [
            'appointments' => $appointments,
            'dentists' => $dentists,
            'services' => $services,
            'filters' => $filters,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function preview(): void
    {
        Auth::requireRole('staff');

        header('Content-Type: application/json; charset=utf-8');

        $appointmentId = (int) ($_GET['appointment_id'] ?? 0);
        $reminderType = trim((string) ($_GET['reminder_type'] ?? ''));

        if ($appointmentId <= 0 || !$this->isValidReminderType($reminderType)) {
            echo json_encode([
                'ok' => false,
                'message' => 'Invalid preview request.',
            ]);
            exit;
        }

        try {
            $appointment = $this->appointments->findAppointmentById($appointmentId);

            if (!$appointment) {
                echo json_encode([
                    'ok' => false,
                    'message' => 'Appointment not found.',
                ]);
                exit;
            }

            $message = $this->reminders->buildReminderMessage($appointment, $reminderType);

            echo json_encode([
                'ok' => true,
                'message' => $message,
            ]);
            exit;
        } catch (Throwable $e) {
            echo json_encode([
                'ok' => false,
                'message' => 'Unable to build preview message.',
            ]);
            exit;
        }
    }

    public function sendBulk(): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $appointmentIds = $_POST['appointment_ids'] ?? [];
        $reminderType = trim((string) ($_POST['reminder_type'] ?? ''));

        $redirectQuery = $this->buildRedirectQuery($_POST);

        if (!$this->isValidReminderType($reminderType)) {
            Session::set('flash_error', 'Invalid reminder type.');
            header('Location: /DentalClinic/public/staff/messages/bulk' . $redirectQuery);
            exit;
        }

        if (!is_array($appointmentIds)) {
            $appointmentIds = [];
        }

        $appointmentIds = array_values(array_unique(array_filter(array_map(
            static fn ($value): int => (int) $value,
            $appointmentIds
        ), static fn (int $value): bool => $value > 0)));

        if (empty($appointmentIds)) {
            Session::set('flash_error', 'Please select at least one appointment.');
            header('Location: /DentalClinic/public/staff/messages/bulk' . $redirectQuery);
            exit;
        }

        $user = Auth::user();
        $sentBy = (int) ($user['user_id'] ?? 0);

        $summary = [
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($appointmentIds as $appointmentId) {
            $result = $this->reminders->sendReminderForAppointment(
                $appointmentId,
                $reminderType,
                $sentBy > 0 ? $sentBy : null
            );

            $status = (string) ($result['status'] ?? '');

            if ($status === 'failed') {
                $summary['failed']++;
            } elseif ($status === 'skipped') {
                $summary['skipped']++;
            } else {
                $summary['sent']++;
            }
        }

        Session::set(
            'flash_success',
            'Bulk reminder completed. Sent: ' . $summary['sent'] .
            ', Skipped: ' . $summary['skipped'] .
            ', Failed: ' . $summary['failed'] . '.'
        );

        header('Location: /DentalClinic/public/staff/messages/bulk' . $redirectQuery);
        exit;
    }

    private function isValidReminderType(string $reminderType): bool
    {
        return in_array($reminderType, [
            'appointment_3_days',
            'appointment_2_days',
            'appointment_1_day',
        ], true);
    }

    private function isValidReminderTypeOrEmpty(string $reminderType): bool
    {
        return $reminderType === '' || $this->isValidReminderType($reminderType);
    }

    private function buildRedirectQuery(array $source): string
    {
        $query = [];

        foreach (['date_from', 'date_to', 'dentist_id', 'service_id', 'reminder_type'] as $key) {
            if (!isset($source[$key])) {
                continue;
            }

            $value = trim((string) $source[$key]);

            if ($value !== '') {
                $query[$key] = $value;
            }
        }

        return !empty($query) ? '?' . http_build_query($query) : '';
    }
}