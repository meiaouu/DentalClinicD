<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ReceiptRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO receipts (
                treatment_id,
                payment_id,
                receipt_number,
                issued_by,
                issued_at
            ) VALUES (
                :treatment_id,
                :payment_id,
                :receipt_number,
                :issued_by,
                :issued_at
            )
        ");
        $stmt->execute([
            'treatment_id' => $data['treatment_id'],
            'payment_id' => $data['payment_id'],
            'receipt_number' => $data['receipt_number'],
            'issued_by' => $data['issued_by'],
            'issued_at' => $data['issued_at'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findByPaymentId(int $paymentId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                receipt_id,
                treatment_id,
                payment_id,
                receipt_number,
                issued_by,
                issued_at
            FROM receipts
            WHERE payment_id = :payment_id
            LIMIT 1
        ");
        $stmt->execute([
            'payment_id' => $paymentId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByTreatmentId(int $treatmentId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                receipt_id,
                treatment_id,
                payment_id,
                receipt_number,
                issued_by,
                issued_at
            FROM receipts
            WHERE treatment_id = :treatment_id
            ORDER BY issued_at DESC, receipt_id DESC
        ");
        $stmt->execute([
            'treatment_id' => $treatmentId,
        ]);

        return $stmt->fetchAll();
    }
}