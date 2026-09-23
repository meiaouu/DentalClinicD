<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TreatmentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByAppointmentId(int $appointmentId): ?array
    {
        if ($appointmentId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                treatment_id,
                appointment_id,
                examination_id,
                patient_id,
                dentist_id,
                treatment_date,
                procedure_name,
                treated_tooth,
                description,
                prescription,
                recommendation,
                estimated_price,
                actual_charge,
                amount_paid,
                balance,
                treatment_status,
                remarks,
                created_at,
                updated_at
            FROM treatments
            WHERE appointment_id = :appointment_id
            ORDER BY treatment_id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':appointment_id' => $appointmentId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByAppointment(int $appointmentId): ?array
    {
        return $this->findByAppointmentId($appointmentId);
    }

    public function findDetailedById(int $treatmentId): ?array
    {
        if ($treatmentId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                t.*,
                a.appointment_code,
                a.appointment_date,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,
                p.email AS patient_email,
                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name
            FROM treatments t
            LEFT JOIN appointments a ON a.appointment_id = t.appointment_id
            LEFT JOIN patients p ON p.patient_id = t.patient_id
            LEFT JOIN dentists d ON d.dentist_id = t.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            WHERE t.treatment_id = :treatment_id
            LIMIT 1
        ");

        $stmt->execute([
            ':treatment_id' => $treatmentId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function updateOrCreateByAppointment(int $appointmentId, array $data): int
    {
        if ($appointmentId <= 0) {
            throw new \RuntimeException('Invalid appointment record.');
        }

        $existing = $this->findByAppointmentId($appointmentId);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE treatments
                SET examination_id = :examination_id,
                    treatment_date = :treatment_date,
                    procedure_name = :procedure_name,
                    treated_tooth = :treated_tooth,
                    description = :description,
                    prescription = :prescription,
                    recommendation = :recommendation,
                    estimated_price = :estimated_price,
                    actual_charge = :actual_charge,
                    amount_paid = :amount_paid,
                    balance = :balance,
                    treatment_status = :treatment_status,
                    remarks = :remarks,
                    updated_at = NOW()
                WHERE treatment_id = :treatment_id
            ");

            $stmt->execute([
                ':examination_id' => $data['examination_id'] ?? null,
                ':treatment_date' => $data['treatment_date'] ?? date('Y-m-d'),
                ':procedure_name' => $data['procedure_name'] ?? '',
                ':treated_tooth' => $data['treated_tooth'] ?? '',
                ':description' => $data['description'] ?? '',
                ':prescription' => $data['prescription'] ?? '',
                ':recommendation' => $data['recommendation'] ?? '',
                ':estimated_price' => $data['estimated_price'] ?? 0,
                ':actual_charge' => $data['actual_charge'] ?? 0,
                ':amount_paid' => $data['amount_paid'] ?? 0,
                ':balance' => $data['balance'] ?? 0,
                ':treatment_status' => $data['treatment_status'] ?? 'completed',
                ':remarks' => $data['remarks'] ?? '',
                ':treatment_id' => (int) $existing['treatment_id'],
            ]);

            return (int) $existing['treatment_id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO treatments (
                appointment_id,
                examination_id,
                patient_id,
                dentist_id,
                treatment_date,
                procedure_name,
                treated_tooth,
                description,
                prescription,
                recommendation,
                estimated_price,
                actual_charge,
                amount_paid,
                balance,
                treatment_status,
                remarks,
                created_at,
                updated_at
            ) VALUES (
                :appointment_id,
                :examination_id,
                :patient_id,
                :dentist_id,
                :treatment_date,
                :procedure_name,
                :treated_tooth,
                :description,
                :prescription,
                :recommendation,
                :estimated_price,
                :actual_charge,
                :amount_paid,
                :balance,
                :treatment_status,
                :remarks,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            ':appointment_id' => $appointmentId,
            ':examination_id' => $data['examination_id'] ?? null,
            ':patient_id' => $data['patient_id'] ?? null,
            ':dentist_id' => $data['dentist_id'] ?? null,
            ':treatment_date' => $data['treatment_date'] ?? date('Y-m-d'),
            ':procedure_name' => $data['procedure_name'] ?? '',
            ':treated_tooth' => $data['treated_tooth'] ?? '',
            ':description' => $data['description'] ?? '',
            ':prescription' => $data['prescription'] ?? '',
            ':recommendation' => $data['recommendation'] ?? '',
            ':estimated_price' => $data['estimated_price'] ?? 0,
            ':actual_charge' => $data['actual_charge'] ?? 0,
            ':amount_paid' => $data['amount_paid'] ?? 0,
            ':balance' => $data['balance'] ?? 0,
            ':treatment_status' => $data['treatment_status'] ?? 'completed',
            ':remarks' => $data['remarks'] ?? '',
        ]);

        return (int) $this->db->lastInsertId();
    }
    public function getByPatientId(int $patientId): array
{
    $stmt = $this->db->prepare("
        SELECT
            t.*,
            a.appointment_code,
            a.appointment_date,
            s.service_name,
            du.first_name AS dentist_first_name,
            du.last_name AS dentist_last_name
        FROM treatments t
        LEFT JOIN appointments a ON a.appointment_id = t.appointment_id
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN dentists d ON d.dentist_id = t.dentist_id
        LEFT JOIN users du ON du.user_id = d.user_id
        WHERE t.patient_id = :patient_id
        ORDER BY t.treatment_date DESC, t.treatment_id DESC
    ");

    $stmt->execute([
        ':patient_id' => $patientId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

    public function updateFinancialSummary(
        int $treatmentId,
        float $amountPaid,
        float $balance
    ): void {
        if ($treatmentId <= 0) {
            throw new \RuntimeException('Invalid treatment record.');
        }

        $stmt = $this->db->prepare("
            UPDATE treatments
            SET amount_paid = :amount_paid,
                balance = :balance,
                updated_at = NOW()
            WHERE treatment_id = :treatment_id
        ");

        $stmt->execute([
            ':amount_paid' => $amountPaid,
            ':balance' => $balance,
            ':treatment_id' => $treatmentId,
        ]);
    }
}