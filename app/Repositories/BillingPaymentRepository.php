<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

class BillingPaymentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function createPayment(array $data): int
    {
        $billingId = (int) ($data['billing_id'] ?? 0);
        $receivedBy = (int) ($data['received_by'] ?? 0);
        $receiptNumber = trim((string) ($data['receipt_number'] ?? ''));
        $amountPaid = round((float) ($data['amount_paid'] ?? 0), 2);
        $paymentMethod = trim((string) ($data['payment_method'] ?? ''));
        $referenceNumber = $this->nullableText($data['reference_number'] ?? null, 120);
        $balanceBefore = round((float) ($data['balance_before'] ?? 0), 2);
        $balanceAfter = round((float) ($data['balance_after'] ?? 0), 2);
        $remarks = $this->nullableText($data['remarks'] ?? null, 1000);

        if ($billingId <= 0) {
            throw new RuntimeException('Invalid billing record.');
        }

        if ($receivedBy <= 0) {
            throw new RuntimeException('Invalid receiving staff.');
        }

        if ($receiptNumber === '') {
            throw new RuntimeException('Receipt number is required.');
        }

        if ($amountPaid <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO billing_payments (
                billing_id,
                received_by,
                receipt_number,
                amount_paid,
                payment_method,
                reference_number,
                balance_before,
                balance_after,
                remarks,
                paid_at
            ) VALUES (
                :billing_id,
                :received_by,
                :receipt_number,
                :amount_paid,
                :payment_method,
                :reference_number,
                :balance_before,
                :balance_after,
                :remarks,
                NOW()
            )
        ");

        $stmt->execute([
            ':billing_id' => $billingId,
            ':received_by' => $receivedBy,
            ':receipt_number' => $receiptNumber,
            ':amount_paid' => number_format($amountPaid, 2, '.', ''),
            ':payment_method' => $paymentMethod,
            ':reference_number' => $referenceNumber,
            ':balance_before' => number_format($balanceBefore, 2, '.', ''),
            ':balance_after' => number_format($balanceAfter, 2, '.', ''),
            ':remarks' => $remarks,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findByBillingId(int $billingId): array
    {
        if ($billingId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                bp.*,
                u.first_name AS received_by_first_name,
                u.middle_name AS received_by_middle_name,
                u.last_name AS received_by_last_name
            FROM billing_payments bp
            LEFT JOIN users u ON u.user_id = bp.received_by
            WHERE bp.billing_id = :billing_id
            ORDER BY bp.paid_at DESC, bp.payment_id DESC
        ");

        $stmt->execute([
            ':billing_id' => $billingId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findPaymentReceipt(int $paymentId): ?array
    {
        if ($paymentId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                bp.*,

                b.billing_number,
                b.total_amount,
                b.amount_paid AS billing_amount_paid,
                b.balance AS billing_balance,
                b.payment_status,

                p.patient_code,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,

                a.appointment_code,
                a.appointment_date,
                a.start_time,
                a.end_time,

                s.service_name,

                du.first_name AS dentist_first_name,
                du.middle_name AS dentist_middle_name,
                du.last_name AS dentist_last_name,

                ru.first_name AS received_by_first_name,
                ru.middle_name AS received_by_middle_name,
                ru.last_name AS received_by_last_name
            FROM billing_payments bp
            INNER JOIN billings b ON b.billing_id = bp.billing_id
            INNER JOIN patients p ON p.patient_id = b.patient_id
            LEFT JOIN appointments a ON a.appointment_id = b.appointment_id
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN dentists d ON d.dentist_id = b.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            LEFT JOIN users ru ON ru.user_id = bp.received_by
            WHERE bp.payment_id = :payment_id
            LIMIT 1
        ");

        $stmt->execute([
            ':payment_id' => $paymentId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function generateReceiptNumber(): string
    {
        do {
            $number = 'RCPT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM billing_payments
                WHERE receipt_number = :receipt_number
            ");

            $stmt->execute([
                ':receipt_number' => $number,
            ]);

            $exists = (int) $stmt->fetchColumn() > 0;
        } while ($exists);

        return $number;
    }

    private function nullableText($value, int $maxLength): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength);
        }

        return substr($value, 0, $maxLength);
    }
}