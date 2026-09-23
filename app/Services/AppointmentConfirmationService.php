<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\AppointmentRepository;
use App\Repositories\AppointmentRequestRepository;
use App\Repositories\PatientRepository;
use App\Repositories\ServiceRepository;
use DateInterval;
use DateTime;
use PDO;
use RuntimeException;
use Throwable;

class AppointmentConfirmationService
{
    private PDO $db;
    private AppointmentRequestRepository $requests;
    private AppointmentRepository $appointments;
    private PatientRepository $patients;
    private ServiceRepository $services;
    private BookingAvailabilityService $availability;
    private PatientAccountProvisioningService $accounts;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->requests = new AppointmentRequestRepository();
        $this->appointments = new AppointmentRepository();
        $this->patients = new PatientRepository();
        $this->services = new ServiceRepository();
        $this->availability = new BookingAvailabilityService();
        $this->accounts = new PatientAccountProvisioningService();
    }

    public function confirmRequest(int $requestId, int $staffUserId, ?int $dentistId = null, ?string $staffNotes = null): int
    {
        if ($requestId <= 0) {
            throw new RuntimeException('Appointment request not found.');
        }

        if ($staffUserId <= 0) {
            throw new RuntimeException('Invalid staff user.');
        }

        $accountProvisionResult = null;

        $this->db->beginTransaction();

        try {
            $request = method_exists($this->requests, 'findByIdForUpdate')
                ? $this->requests->findByIdForUpdate($requestId)
                : $this->findRequestForUpdate($requestId);

            if (!$request) {
                throw new RuntimeException('Appointment request not found.');
            }

            if (!in_array((string) ($request['request_status'] ?? ''), ['pending', 'under_review', 'rescheduled'], true)) {
                throw new RuntimeException('This request can no longer be confirmed.');
            }

            $effectiveDentistId = $dentistId && $dentistId > 0
                ? $dentistId
                : (int) ($request['preferred_dentist_id'] ?? 0);

            if ($effectiveDentistId <= 0) {
                throw new RuntimeException('Please select an available dentist.');
            }

            $appointmentDate = trim((string) ($request['preferred_date'] ?? ''));
            $startTime = $this->normalizeTime((string) ($request['preferred_start_time'] ?? ''));
            $serviceId = (int) ($request['service_id'] ?? 0);

            if ($appointmentDate === '' || $startTime === '' || $serviceId <= 0) {
                throw new RuntimeException('The request schedule or service is incomplete.');
            }

            $service = $this->services->findById($serviceId);

            if (!$service) {
                throw new RuntimeException('Service not found.');
            }

            $this->availability->ensureSlotStillAvailable(
                $appointmentDate,
                $startTime,
                $serviceId,
                $effectiveDentistId
            );

            $patient = $this->patients->findStrongMatchFromRequest($request);

            if (!$patient) {
                $patientId = $this->patients->createFromAppointmentRequest($request, $staffUserId);
                $patient = $this->patients->findById($patientId);
            }

            if (!$patient || empty($patient['patient_id'])) {
                throw new RuntimeException('Failed to create or resolve patient record for this appointment request.');
            }

            $patientId = (int) $patient['patient_id'];

            if (method_exists($this->requests, 'assignPatient')) {
                $this->requests->assignPatient($requestId, $patientId);
            }

            $start = new DateTime($appointmentDate . ' ' . $startTime);
            $duration = max(1, (int) ($service['estimated_duration_minutes'] ?? 30));
            $end = clone $start;
            $end->add(new DateInterval('PT' . $duration . 'M'));

            $appointmentId = $this->appointments->create([
                'appointment_code' => 'APT-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'request_id' => $requestId,
                'patient_id' => $patientId,
                'dentist_id' => $effectiveDentistId,
                'service_id' => $serviceId,
                'appointment_date' => $appointmentDate,
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'estimated_duration_minutes' => $duration,
                'estimated_price' => (float) ($service['estimated_price'] ?? 0),
                'status' => 'confirmed',
                'arrival_status' => 'pending',
                'grace_period_minutes' => 30,
                'booked_by' => $staffUserId,
                'confirmed_by' => $staffUserId,
                'remarks' => $staffNotes !== '' ? $staffNotes : null,
            ]);

            if (method_exists($this->requests, 'markConfirmed')) {
                $this->requests->markConfirmed($requestId, $appointmentId, $patientId, $staffUserId, $staffNotes);
            } else {
                $this->requests->updateAfterConfirmation($requestId, $appointmentId, $staffUserId, $staffNotes);
            }

            $this->createStatusLog($appointmentId, $staffUserId, $staffNotes);
            $this->createReminders($appointmentId, $patientId, $appointmentDate);
            $this->createAuditLog($requestId, $appointmentId, $staffUserId);

            if ($this->shouldCreatePendingAccount($request, $patient)) {
                $patient = $this->patients->findById($patientId) ?: $patient;
                $accountProvisionResult = $this->accounts->createPendingAccountForPatient($patient);
            }

            $this->db->commit();

            if (is_array($accountProvisionResult) && empty($accountProvisionResult['sent'])) {
                error_log('[AppointmentConfirmationService] Pending account created but setup link was not sent automatically for request_id=' . $requestId);
            }

            return $appointmentId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function shouldCreatePendingAccount(array $request, array $patient): bool
    {
        $requested = (int) ($request['wants_patient_account'] ?? 0) === 1;

        if (!$requested) {
            return false;
        }

        if (!empty($patient['user_id'])) {
            return false;
        }

        $email = trim((string) ($patient['email'] ?? ''));
        $phone = trim((string) ($patient['contact_number'] ?? ''));

        return $email !== '' || $phone !== '';
    }

    private function findRequestForUpdate(int $requestId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM appointment_requests
            WHERE request_id = :request_id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':request_id' => $requestId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function createStatusLog(int $appointmentId, int $staffUserId, ?string $remarks): void
    {
        $class = '\\App\\Repositories\\AppointmentStatusLogRepository';

        if (!class_exists($class)) {
            return;
        }

        $repo = new $class();

        if (!method_exists($repo, 'create')) {
            return;
        }

        $repo->create([
            'appointment_id' => $appointmentId,
            'old_status' => 'pending',
            'new_status' => 'confirmed',
            'changed_by' => $staffUserId,
            'remarks' => $remarks !== null && $remarks !== '' ? $remarks : 'Appointment confirmed from request.',
        ]);
    }

    private function createAuditLog(int $requestId, int $appointmentId, int $staffUserId): void
    {
        $class = '\\App\\Repositories\\AuditLogRepository';

        if (!class_exists($class)) {
            return;
        }

        $repo = new $class();

        if (!method_exists($repo, 'create')) {
            return;
        }

        $repo->create([
            'user_id' => $staffUserId,
            'module_name' => 'appointment_requests',
            'action_name' => 'confirm',
            'record_type' => 'appointment_request',
            'record_id' => $requestId,
            'description' => 'Confirmed appointment request and created appointment #' . $appointmentId,
        ]);
    }

    private function createReminders(int $appointmentId, int $patientId, string $appointmentDate): void
    {
        $class = '\\App\\Repositories\\ReminderRepository';

        if (!class_exists($class)) {
            return;
        }

        $repo = new $class();

        if (method_exists($repo, 'createAppointmentReminderSet')) {
            $repo->createAppointmentReminderSet($appointmentId, $patientId, $appointmentDate);
        }
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);
        return preg_match('/^\d{2}:\d{2}$/', $time) ? $time . ':00' : $time;
    }
}