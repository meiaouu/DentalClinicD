<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class PatientAccessPolicy
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function assertCanViewPatient(array $user, int $patientId): void
    {
        if (!$this->canViewPatient($user, $patientId)) {
            http_response_code(403);
            throw new RuntimeException('You are not allowed to view this patient record.');
        }
    }

    public function canViewPatient(array $user, int $patientId): bool
    {
        if ($patientId <= 0) {
            return false;
        }

        $role = strtolower((string) ($user['role_name'] ?? ''));
        $userId = (int) ($user['user_id'] ?? 0);

        if ($userId <= 0) {
            return false;
        }

        if (in_array($role, ['admin', 'staff'], true)) {
            return true;
        }

        if ($role === 'patient') {
            return $this->isOwnPatientRecord($userId, $patientId);
        }

        if ($role === 'dentist') {
            return $this->dentistHasAssignedPatient($userId, $patientId);
        }

        return false;
    }

    private function isOwnPatientRecord(int $userId, int $patientId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM patients
            WHERE patient_id = :patient_id
              AND user_id = :user_id
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
            ':user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function dentistHasAssignedPatient(int $dentistUserId, int $patientId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointments a
            INNER JOIN dentists d ON d.dentist_id = a.dentist_id
            WHERE d.user_id = :dentist_user_id
              AND a.patient_id = :patient_id
              AND a.status IN ('confirmed', 'checked_in', 'in_progress', 'completed', 'rescheduled')
        ");

        $stmt->execute([
            ':dentist_user_id' => $dentistUserId,
            ':patient_id' => $patientId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}