<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\AppointmentRepository;
use App\Repositories\AppointmentRequestRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\PatientRepository;
use PDO;
use RuntimeException;

class PatientConversionService
{
    private PDO $db;
    private AppointmentRepository $appointments;
    private AppointmentRequestRepository $requests;
    private PatientRepository $patients;
    private AuditLogRepository $auditLogs;
    private PatientMatchService $matcher;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->appointments = new AppointmentRepository();
        $this->requests = new AppointmentRequestRepository();
        $this->patients = new PatientRepository();
        $this->auditLogs = new AuditLogRepository();
        $this->matcher = new PatientMatchService();
    }

    public function convertCompletedAppointment(int $appointmentId, int $staffUserId): int
    {
        $appointment = $this->appointments->findDetailedById($appointmentId);

        if (!$appointment) {
            throw new RuntimeException('Appointment not found.');
        }

        if ((string) ($appointment['status'] ?? '') !== 'completed') {
            throw new RuntimeException('Only completed appointments can be converted to patient records.');
        }

        if (!empty($appointment['patient_id'])) {
            return (int) $appointment['patient_id'];
        }

        $requestId = (int) ($appointment['request_id'] ?? 0);
        if ($requestId <= 0) {
            throw new RuntimeException('This appointment is not linked to an appointment request.');
        }

        $request = $this->requests->findDetailedById($requestId);
        if (!$request) {
            throw new RuntimeException('Linked appointment request not found.');
        }

        $candidate = [
            'first_name' => (string) ($request['guest_first_name'] ?? ''),
            'middle_name' => (string) ($request['guest_middle_name'] ?? ''),
            'last_name' => (string) ($request['guest_last_name'] ?? ''),
            'sex' => $request['sex'] ?? null,
            'birth_date' => $request['birth_date'] ?? null,
            'civil_status' => $request['civil_status'] ?? null,
            'address' => $request['address'] ?? null,
            'occupation' => $request['occupation'] ?? null,
            'contact_number' => (string) ($request['guest_contact_number'] ?? ''),
            'email' => (string) ($request['guest_email'] ?? ''),
            'emergency_contact_name' => $request['emergency_contact_name'] ?? null,
            'emergency_contact_number' => $request['emergency_contact_number'] ?? null,
            'notes' => 'Converted from completed appointment #' . $appointmentId,
        ];

        $this->db->beginTransaction();

        try {
            $existing = $this->matcher->findExistingPatient($candidate);

            if ($existing) {
                $patientId = (int) $existing['patient_id'];
            } else {
                $patientId = $this->patients->create([
                    'user_id' => null,
                    'patient_code' => 'PAT-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
                    'first_name' => $candidate['first_name'],
                    'middle_name' => $candidate['middle_name'],
                    'last_name' => $candidate['last_name'],
                    'sex' => $candidate['sex'],
                    'birth_date' => $candidate['birth_date'],
                    'civil_status' => $candidate['civil_status'],
                    'address' => $candidate['address'],
                    'occupation' => $candidate['occupation'],
                    'contact_number' => $candidate['contact_number'],
                    'email' => $candidate['email'] !== '' ? $candidate['email'] : null,
                    'emergency_contact_name' => $candidate['emergency_contact_name'],
                    'emergency_contact_number' => $candidate['emergency_contact_number'],
                    'notes' => $candidate['notes'],
                    'profile_status' => 'active',
                    'created_by' => $staffUserId,
                ]);
            }

            $this->appointments->assignPatient($appointmentId, $patientId);
            $this->requests->assignPatient($requestId, $patientId);

            $this->auditLogs->create([
                'user_id' => $staffUserId,
                'module_name' => 'patients',
                'action_name' => 'convert_guest_to_patient',
                'record_type' => 'appointment',
                'record_id' => $appointmentId,
                'description' => 'Linked completed appointment to patient #' . $patientId,
            ]);

            $this->db->commit();
            return $patientId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function createOrUseExistingPatient(array $data, int $staffUserId): int
    {
        $this->db->beginTransaction();

        try {
            $existing = $this->matcher->findExistingPatient($data);

            if ($existing) {
                $patientId = (int) $existing['patient_id'];
            } else {
                $patientId = $this->patients->create([
                    'user_id' => null,
                    'patient_code' => 'PAT-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'],
                    'last_name' => $data['last_name'],
                    'sex' => $data['sex'],
                    'birth_date' => $data['birth_date'],
                    'civil_status' => $data['civil_status'],
                    'address' => $data['address'],
                    'occupation' => $data['occupation'],
                    'contact_number' => $data['contact_number'],
                    'email' => $data['email'] ?: null,
                    'emergency_contact_name' => $data['emergency_contact_name'],
                    'emergency_contact_number' => $data['emergency_contact_number'],
                    'notes' => $data['notes'],
                    'profile_status' => $data['profile_status'] ?? 'active',
                    'created_by' => $staffUserId,
                ]);
            }

            $this->auditLogs->create([
                'user_id' => $staffUserId,
                'module_name' => 'patients',
                'action_name' => 'create_manual',
                'record_type' => 'patient',
                'record_id' => $patientId,
                'description' => 'Created or matched patient from staff entry.',
            ]);

            $this->db->commit();
            return $patientId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}