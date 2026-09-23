<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ExaminationRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByAppointmentId(int $appointmentId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                examination_id,
                appointment_id,
                patient_id,
                dentist_id,
                examination_date,
                chief_complaint,
                intraoral_exam_notes,
                clinical_findings,
                diagnosis,
                treatment_plan,
                recommendations,
                notes,
                created_at,
                updated_at
            FROM examinations
            WHERE appointment_id = :appointment_id
            ORDER BY examination_id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'appointment_id' => $appointmentId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateOrCreateByAppointment(int $appointmentId, array $data): int
    {
        $existing = $this->findByAppointmentId($appointmentId);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE examinations
                SET examination_date = :examination_date,
                    chief_complaint = :chief_complaint,
                    intraoral_exam_notes = :intraoral_exam_notes,
                    clinical_findings = :clinical_findings,
                    diagnosis = :diagnosis,
                    treatment_plan = :treatment_plan,
                    recommendations = :recommendations,
                    notes = :notes,
                    updated_at = NOW()
                WHERE examination_id = :examination_id
            ");
            $stmt->execute([
                'examination_date' => $data['examination_date'],
                'chief_complaint' => $data['chief_complaint'],
                'intraoral_exam_notes' => $data['intraoral_exam_notes'],
                'clinical_findings' => $data['clinical_findings'],
                'diagnosis' => $data['diagnosis'],
                'treatment_plan' => $data['treatment_plan'],
                'recommendations' => $data['recommendations'],
                'notes' => $data['notes'],
                'examination_id' => $existing['examination_id'],
            ]);

            return (int) $existing['examination_id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO examinations (
                appointment_id,
                patient_id,
                dentist_id,
                examination_date,
                chief_complaint,
                intraoral_exam_notes,
                clinical_findings,
                diagnosis,
                treatment_plan,
                recommendations,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :appointment_id,
                :patient_id,
                :dentist_id,
                :examination_date,
                :chief_complaint,
                :intraoral_exam_notes,
                :clinical_findings,
                :diagnosis,
                :treatment_plan,
                :recommendations,
                :notes,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            'appointment_id' => $appointmentId,
            'patient_id' => $data['patient_id'],
            'dentist_id' => $data['dentist_id'],
            'examination_date' => $data['examination_date'],
            'chief_complaint' => $data['chief_complaint'],
            'intraoral_exam_notes' => $data['intraoral_exam_notes'],
            'clinical_findings' => $data['clinical_findings'],
            'diagnosis' => $data['diagnosis'],
            'treatment_plan' => $data['treatment_plan'],
            'recommendations' => $data['recommendations'],
            'notes' => $data['notes'],
        ]);

        return (int) $this->db->lastInsertId();
    }
}