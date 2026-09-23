<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PatientMedicalHistoryRepository
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
                medical_history_id,
                patient_id,
                physician_name,
                physician_contact,
                allergies,
                medications,
                blood_pressure,
                diabetes,
                pregnancy_status,
                bleeding_disorder,
                notes,
                updated_by
            FROM patient_medical_histories
            WHERE patient_id = :patient_id
            ORDER BY medical_history_id DESC
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
                UPDATE patient_medical_histories
                SET physician_name = :physician_name,
                    physician_contact = :physician_contact,
                    allergies = :allergies,
                    medications = :medications,
                    blood_pressure = :blood_pressure,
                    diabetes = :diabetes,
                    pregnancy_status = :pregnancy_status,
                    bleeding_disorder = :bleeding_disorder,
                    notes = :notes,
                    updated_by = :updated_by
                WHERE medical_history_id = :medical_history_id
            ");
            $stmt->execute([
                'physician_name' => $data['physician_name'],
                'physician_contact' => $data['physician_contact'],
                'allergies' => $data['allergies'],
                'medications' => $data['medications'],
                'blood_pressure' => $data['blood_pressure'],
                'diabetes' => $data['diabetes'],
                'pregnancy_status' => $data['pregnancy_status'],
                'bleeding_disorder' => $data['bleeding_disorder'],
                'notes' => $data['notes'],
                'updated_by' => $data['updated_by'],
                'medical_history_id' => $existing['medical_history_id'],
            ]);
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO patient_medical_histories (
                patient_id,
                physician_name,
                physician_contact,
                allergies,
                medications,
                blood_pressure,
                diabetes,
                pregnancy_status,
                bleeding_disorder,
                notes,
                updated_by
            ) VALUES (
                :patient_id,
                :physician_name,
                :physician_contact,
                :allergies,
                :medications,
                :blood_pressure,
                :diabetes,
                :pregnancy_status,
                :bleeding_disorder,
                :notes,
                :updated_by
            )
        ");
        $stmt->execute([
            'patient_id' => $patientId,
            'physician_name' => $data['physician_name'],
            'physician_contact' => $data['physician_contact'],
            'allergies' => $data['allergies'],
            'medications' => $data['medications'],
            'blood_pressure' => $data['blood_pressure'],
            'diabetes' => $data['diabetes'],
            'pregnancy_status' => $data['pregnancy_status'],
            'bleeding_disorder' => $data['bleeding_disorder'],
            'notes' => $data['notes'],
            'updated_by' => $data['updated_by'],
        ]);
    }
}