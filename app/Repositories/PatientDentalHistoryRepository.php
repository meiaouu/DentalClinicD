<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PatientDentalHistoryRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findLatestByPatientId(int $patientId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                dental_history_id,
                patient_id,
                previous_dentist,
                last_dental_visit,
                gums_bleed,
                bad_breath,
                loose_teeth,
                sensitive_teeth,
                clicking_jaw,
                notes,
                updated_by,
                created_at,
                updated_at
            FROM patient_dental_histories
            WHERE patient_id = :patient_id
            ORDER BY dental_history_id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'patient_id' => $patientId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateOrCreate(int $patientId, array $data): void
    {
        $existing = $this->findLatestByPatientId($patientId);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE patient_dental_histories
                SET previous_dentist = :previous_dentist,
                    last_dental_visit = :last_dental_visit,
                    gums_bleed = :gums_bleed,
                    bad_breath = :bad_breath,
                    loose_teeth = :loose_teeth,
                    sensitive_teeth = :sensitive_teeth,
                    clicking_jaw = :clicking_jaw,
                    notes = :notes,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE dental_history_id = :dental_history_id
            ");
            $stmt->execute([
                'previous_dentist' => $data['previous_dentist'],
                'last_dental_visit' => $data['last_dental_visit'],
                'gums_bleed' => $data['gums_bleed'],
                'bad_breath' => $data['bad_breath'],
                'loose_teeth' => $data['loose_teeth'],
                'sensitive_teeth' => $data['sensitive_teeth'],
                'clicking_jaw' => $data['clicking_jaw'],
                'notes' => $data['notes'],
                'updated_by' => $data['updated_by'],
                'dental_history_id' => $existing['dental_history_id'],
            ]);
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO patient_dental_histories (
                patient_id,
                previous_dentist,
                last_dental_visit,
                gums_bleed,
                bad_breath,
                loose_teeth,
                sensitive_teeth,
                clicking_jaw,
                notes,
                updated_by,
                created_at,
                updated_at
            ) VALUES (
                :patient_id,
                :previous_dentist,
                :last_dental_visit,
                :gums_bleed,
                :bad_breath,
                :loose_teeth,
                :sensitive_teeth,
                :clicking_jaw,
                :notes,
                :updated_by,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            'patient_id' => $patientId,
            'previous_dentist' => $data['previous_dentist'],
            'last_dental_visit' => $data['last_dental_visit'],
            'gums_bleed' => $data['gums_bleed'],
            'bad_breath' => $data['bad_breath'],
            'loose_teeth' => $data['loose_teeth'],
            'sensitive_teeth' => $data['sensitive_teeth'],
            'clicking_jaw' => $data['clicking_jaw'],
            'notes' => $data['notes'],
            'updated_by' => $data['updated_by'],
        ]);
    }
}