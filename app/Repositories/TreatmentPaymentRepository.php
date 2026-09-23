<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TreatmentPaymentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO treatment_payments (
                treatment_id,
                appointment_id,
                patient_id,
                amount_paid,
                payment_method,
                reference_number,
                received_by,
                payment_date,
                remarks,
                created_at
            ) VALUES (
                :treatment_id,
                :appointment_id,
                :patient_id,
                :amount_paid,
                :payment_method,
                :reference_number,
                :received_by,
                :payment_date,
                :remarks,
                NOW()
            )
        ");
        $stmt->execute([
            'treatment_id' => $data['treatment_id'],
            'appointment_id' => $data['appointment_id'],
            'patient_id' => $data['patient_id'],
            'amount_paid' => $data['amount_paid'],
            'payment_method' => $data['payment_method'],
            'reference_number' => $data['reference_number'],
            'received_by' => $data['received_by'],
            'payment_date' => $data['payment_date'],
            'remarks' => $data['remarks'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getByTreatmentId(int $treatmentId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                payment_id,
                treatment_id,
                appointment_id,
                patient_id,
                amount_paid,
                payment_method,
                reference_number,
                received_by,
                payment_date,
                remarks,
                created_at
            FROM treatment_payments
            WHERE treatment_id = :treatment_id
            ORDER BY payment_date DESC, payment_id DESC
        ");
        $stmt->execute([
            'treatment_id' => $treatmentId,
        ]);

        return $stmt->fetchAll();
    }

    public function sumByTreatmentId(int $treatmentId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount_paid), 0)
            FROM treatment_payments
            WHERE treatment_id = :treatment_id
        ");
        $stmt->execute([
            'treatment_id' => $treatmentId,
        ]);

        return (float) $stmt->fetchColumn();
    }

    public function findById(int $paymentId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM treatment_payments
            WHERE payment_id = :payment_id
            LIMIT 1
        ");
        $stmt->execute([
            'payment_id' => $paymentId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }
}