<?php

namespace App\Services;

use App\Repositories\AppointmentRepository;
use App\Repositories\AppointmentRequestRepository;
use App\Repositories\AppointmentStatusLogRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\PatientRepository;
use RuntimeException;

class AppointmentStatusService
{
    private AppointmentRepository $appointments;
    private AppointmentStatusLogRepository $statusLogs;
    private AuditLogRepository $auditLogs;
    private AppointmentRequestRepository $requests;
    private PatientRepository $patients;

    public function __construct()
    {
        $this->appointments = new AppointmentRepository();
        $this->statusLogs = new AppointmentStatusLogRepository();
        $this->auditLogs = new AuditLogRepository();
        $this->requests = new AppointmentRequestRepository();
        $this->patients = new PatientRepository();
    }
    public function checkIn(array $appointment, int $userId): void
{
    $this->transition($appointment, $userId, 'checked_in', [
        'checked_in_at' => date('Y-m-d H:i:s'),
    ], null, 'appointment_checked_in');
}

public function markInProgress(array $appointment, int $userId): void
{
    $this->transition($appointment, $userId, 'in_progress', [], null, 'appointment_in_progress');
}

    public function complete(array $appointment, int $changedBy, ?string $remarks = null): void
    {
        $currentStatus = (string) ($appointment['status'] ?? '');
        $appointmentId = (int) ($appointment['appointment_id'] ?? 0);

        if (!in_array($currentStatus, ['confirmed', 'rescheduled', 'checked_in', 'in_progress'], true)) {
            throw new RuntimeException('Invalid appointment status transition.');
        }

        if ($appointmentId <= 0) {
            throw new RuntimeException('Appointment not found.');
        }

        // Create patient only when appointment is completed
        if (empty($appointment['patient_id']) && !empty($appointment['request_id'])) {
            $requestId = (int) $appointment['request_id'];
            $request = $this->requests->findDetailedById($requestId);

            if ($request) {
                $existingPatientId = !empty($request['patient_id']) ? (int) $request['patient_id'] : 0;

                if ($existingPatientId > 0) {
                    $this->appointments->assignPatient($appointmentId, $existingPatientId);
                } else {
                    $existingPatient = null;
                    $contactNumber = trim((string) ($request['guest_contact_number'] ?? ''));

                    if ($contactNumber !== '') {
                        $existingPatient = $this->patients->findByContactNumber($contactNumber);
                    }

                    if ($existingPatient) {
                        $patientId = (int) ($existingPatient['patient_id'] ?? 0);
                    } else {
                        $patientId = $this->patients->create([
                            'user_id' => null,
                            'patient_code' => 'PAT-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
                            'first_name' => trim((string) ($request['guest_first_name'] ?? '')),
                            'middle_name' => trim((string) ($request['guest_middle_name'] ?? '')),
                            'last_name' => trim((string) ($request['guest_last_name'] ?? '')),
                            'sex' => $request['sex'] ?? null,
                            'birth_date' => $request['birth_date'] ?? null,
                            'civil_status' => $request['civil_status'] ?? null,
                            'address' => $request['address'] ?? null,
                            'occupation' => $request['occupation'] ?? null,
                            'contact_number' => $contactNumber !== '' ? $contactNumber : null,
                            'email' => !empty($request['guest_email']) ? trim((string) $request['guest_email']) : null,
                            'emergency_contact_name' => $request['emergency_contact_name'] ?? null,
                            'emergency_contact_number' => $request['emergency_contact_number'] ?? null,
                            'notes' => 'Auto-created after completed appointment from request #' . $requestId,
                            'profile_status' => 'active',
                            'created_by' => $changedBy,
                        ]);
                    }

                    if ($patientId <= 0) {
                        throw new RuntimeException('Failed to create patient record.');
                    }

                    $this->appointments->assignPatient($appointmentId, $patientId);
                }
            }
        }

        $this->appointments->updateStatus(
            $appointmentId,
            'completed',
            $remarks,
            [
                'completed_at' => date('Y-m-d H:i:s'),
            ]
        );

        $this->statusLogs->create([
            'appointment_id' => $appointmentId,
            'old_status' => $currentStatus,
            'new_status' => 'completed',
            'changed_by' => $changedBy,
            'remarks' => $remarks,
        ]);

        $this->auditLogs->create([
            'user_id' => $changedBy,
            'module_name' => 'appointments',
            'action_name' => 'appointment_completed',
            'record_type' => 'appointment',
            'record_id' => $appointmentId,
            'description' => 'Appointment status changed from ' . $currentStatus . ' to completed',
        ]);
    }

    public function markNoShow(array $appointment, int $userId, ?string $remarks = null): void
    {
        $this->requireCurrentStatus($appointment, ['confirmed', 'rescheduled', 'checked_in']);

        $this->transition(
            $appointment,
            $userId,
            'no_show',
            [
                'no_show_at' => date('Y-m-d H:i:s'),
            ],
            $remarks,
            'appointment_no_show'
        );
    }

    public function cancel(array $appointment, int $userId, ?string $remarks = null): void
    {
        $this->requireCurrentStatus($appointment, ['pending', 'confirmed', 'rescheduled', 'checked_in']);

        $this->transition(
            $appointment,
            $userId,
            'cancelled',
            [
                'cancelled_by' => $userId,
            ],
            $remarks,
            'appointment_cancelled'
        );
    }

    private function transition(
        array $appointment,
        int $userId,
        string $newStatus,
        array $extraFields,
        ?string $remarks,
        string $auditAction
    ): void {
        $appointmentId = (int) ($appointment['appointment_id'] ?? 0);
        $oldStatus = (string) ($appointment['status'] ?? '');

        if ($appointmentId <= 0) {
            throw new RuntimeException('Appointment not found.');
        }

        $this->appointments->updateStatus(
            $appointmentId,
            $newStatus,
            $remarks,
            $extraFields
        );

        $this->statusLogs->create([
            'appointment_id' => $appointmentId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $userId,
            'remarks' => $remarks,
        ]);

        $this->auditLogs->create([
            'user_id' => $userId,
            'module_name' => 'appointments',
            'action_name' => $auditAction,
            'record_type' => 'appointment',
            'record_id' => $appointmentId,
            'description' => 'Appointment status changed from ' . $oldStatus . ' to ' . $newStatus,
        ]);
    }

    private function requireCurrentStatus(array $appointment, array $allowed): void
    {
        $currentStatus = (string) ($appointment['status'] ?? '');

        if (!in_array($currentStatus, $allowed, true)) {
            throw new RuntimeException('Invalid appointment status transition.');
        }
    }
}