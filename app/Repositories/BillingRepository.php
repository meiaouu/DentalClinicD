<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class BillingRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function paginate(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        if ($keyword !== '') {
            $conditions[] = "
                (
                    b.billing_number LIKE :keyword
                    OR p.patient_code LIKE :keyword
                    OR p.first_name LIKE :keyword
                    OR p.middle_name LIKE :keyword
                    OR p.last_name LIKE :keyword
                    OR CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE :keyword
                    OR p.contact_number LIKE :keyword
                    OR a.appointment_code LIKE :keyword
                )
            ";
            $params[':keyword'] = '%' . $keyword . '%';
        }

        if ($status !== '' && in_array($status, ['unpaid', 'partial', 'paid', 'cancelled'], true)) {
            $conditions[] = "b.payment_status = :payment_status";
            $params[':payment_status'] = $status;
        }

        if ($this->isValidDate($dateFrom)) {
            $conditions[] = "DATE(b.created_at) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }

        if ($this->isValidDate($dateTo)) {
            $conditions[] = "DATE(b.created_at) <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        $whereSql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = "
            SELECT COUNT(*)
            FROM billings b
            INNER JOIN patients p ON p.patient_id = b.patient_id
            LEFT JOIN appointments a ON a.appointment_id = b.appointment_id
            $whereSql
        ";

        $countStmt = $this->db->prepare($countSql);

        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }

        $countStmt->execute();

        $total = (int) $countStmt->fetchColumn();

        $sql = "
            SELECT
                b.*,

                p.patient_code,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,

                a.appointment_code,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.status AS appointment_status,

                COALESCE(items.procedure_names, s.service_name, 'Manual Billing') AS service_name,

                du.first_name AS dentist_first_name,
                du.middle_name AS dentist_middle_name,
                du.last_name AS dentist_last_name,

                latest.latest_payment_id,
                latest.latest_receipt_number
            FROM billings b
            INNER JOIN patients p ON p.patient_id = b.patient_id
            LEFT JOIN appointments a ON a.appointment_id = b.appointment_id
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN dentists d ON d.dentist_id = b.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            LEFT JOIN (
                SELECT
                    billing_id,
                    GROUP_CONCAT(item_name ORDER BY billing_item_id ASC SEPARATOR ', ') AS procedure_names
                FROM billing_items
                GROUP BY billing_id
            ) items ON items.billing_id = b.billing_id
            LEFT JOIN (
                SELECT
                    bp1.billing_id,
                    bp1.payment_id AS latest_payment_id,
                    bp1.receipt_number AS latest_receipt_number
                FROM billing_payments bp1
                INNER JOIN (
                    SELECT billing_id, MAX(payment_id) AS max_payment_id
                    FROM billing_payments
                    GROUP BY billing_id
                ) bp2 ON bp2.max_payment_id = bp1.payment_id
            ) latest ON latest.billing_id = b.billing_id
            $whereSql
            ORDER BY b.created_at DESC, b.billing_id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function summary(): array
    {
        $stmt = $this->db->query("
            SELECT
                COALESCE(SUM(CASE WHEN payment_status IN ('unpaid', 'partial') THEN balance ELSE 0 END), 0) AS total_unpaid_balance,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_bills_count,
                COALESCE(SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END), 0) AS partial_bills_count
            FROM billings
        ");

        $billingRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $paymentStmt = $this->db->query("
            SELECT COALESCE(SUM(amount_paid), 0)
            FROM billing_payments
            WHERE DATE(paid_at) = CURDATE()
        ");

        return [
            'total_unpaid_balance' => (float) ($billingRow['total_unpaid_balance'] ?? 0),
            'today_collections' => (float) $paymentStmt->fetchColumn(),
            'paid_bills_count' => (int) ($billingRow['paid_bills_count'] ?? 0),
            'partial_bills_count' => (int) ($billingRow['partial_bills_count'] ?? 0),
        ];
    }

    public function findById(int $billingId): ?array
    {
        if ($billingId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                b.*,

                p.patient_code,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,
                p.email AS patient_email,
                p.address AS patient_address,

                a.appointment_code,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.status AS appointment_status,

                COALESCE(items.procedure_names, s.service_name, 'Manual Billing') AS service_name,

                du.first_name AS dentist_first_name,
                du.middle_name AS dentist_middle_name,
                du.last_name AS dentist_last_name,

                cu.first_name AS created_by_first_name,
                cu.last_name AS created_by_last_name
            FROM billings b
            INNER JOIN patients p ON p.patient_id = b.patient_id
            LEFT JOIN appointments a ON a.appointment_id = b.appointment_id
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN dentists d ON d.dentist_id = b.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            LEFT JOIN users cu ON cu.user_id = b.created_by
            LEFT JOIN (
                SELECT
                    billing_id,
                    GROUP_CONCAT(item_name ORDER BY billing_item_id ASC SEPARATOR ', ') AS procedure_names
                FROM billing_items
                GROUP BY billing_id
            ) items ON items.billing_id = b.billing_id
            WHERE b.billing_id = :billing_id
            LIMIT 1
        ");

        $stmt->execute([
            ':billing_id' => $billingId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findItems(int $billingId): array
    {
        if ($billingId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM billing_items
            WHERE billing_id = :billing_id
            ORDER BY billing_item_id ASC
        ");

        $stmt->execute([
            ':billing_id' => $billingId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findCompletedAppointmentsWithoutBilling(array $filters): array
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        $conditions = [
            "a.status = 'completed'",
            "a.patient_id IS NOT NULL",
            "b.billing_id IS NULL",
        ];

        $params = [];

        if ($keyword !== '') {
            $conditions[] = "
                (
                    a.appointment_code LIKE :keyword
                    OR p.patient_code LIKE :keyword
                    OR p.first_name LIKE :keyword
                    OR p.last_name LIKE :keyword
                    OR CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE :keyword
                )
            ";
            $params[':keyword'] = '%' . $keyword . '%';
        }

        $sql = "
            SELECT
                a.appointment_id,
                a.appointment_code,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.estimated_price,

                p.patient_code,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,

                s.service_name,
                s.estimated_price AS service_estimated_price,

                du.first_name AS dentist_first_name,
                du.middle_name AS dentist_middle_name,
                du.last_name AS dentist_last_name
            FROM appointments a
            INNER JOIN patients p ON p.patient_id = a.patient_id
            LEFT JOIN billings b ON b.appointment_id = a.appointment_id
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY a.appointment_date DESC, a.start_time DESC, a.appointment_id DESC
            LIMIT 25
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function hasBillingForAppointment(int $appointmentId): bool
    {
        if ($appointmentId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM billings
            WHERE appointment_id = :appointment_id
        ");

        $stmt->execute([
            ':appointment_id' => $appointmentId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function createFromAppointment(int $appointmentId, int $staffUserId): int
    {
        if ($appointmentId <= 0) {
            throw new RuntimeException('Invalid appointment.');
        }

        if ($staffUserId <= 0) {
            throw new RuntimeException('Invalid staff user.');
        }

        $startedTransaction = false;

        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTransaction = true;
            }

            $stmt = $this->db->prepare("
                SELECT
                    a.*,
                    s.service_name,
                    s.estimated_price AS service_estimated_price
                FROM appointments a
                LEFT JOIN services s ON s.service_id = a.service_id
                WHERE a.appointment_id = :appointment_id
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                ':appointment_id' => $appointmentId,
            ]);

            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$appointment) {
                throw new RuntimeException('Appointment not found.');
            }

            if ((string) ($appointment['status'] ?? '') !== 'completed') {
                throw new RuntimeException('Only completed appointments can generate billing.');
            }

            $patientId = (int) ($appointment['patient_id'] ?? 0);

            if ($patientId <= 0) {
                throw new RuntimeException('Appointment is not linked to a patient.');
            }

            if ($this->hasBillingForAppointment($appointmentId)) {
                throw new RuntimeException('This appointment already has a billing record.');
            }

            $dentistId = !empty($appointment['dentist_id']) ? (int) $appointment['dentist_id'] : null;
            $serviceId = !empty($appointment['service_id']) ? (int) $appointment['service_id'] : null;

            $itemName = trim((string) ($appointment['service_name'] ?? ''));

            if ($itemName === '') {
                $itemName = 'Dental Service';
            }

            $unitPrice = round((float) (
                $appointment['estimated_price']
                ?? $appointment['service_estimated_price']
                ?? 0
            ), 2);

            $subtotal = $unitPrice;
            $discountAmount = 0.00;
            $totalAmount = max(0, round($subtotal - $discountAmount, 2));
            $amountPaid = 0.00;
            $balance = $totalAmount;
            $paymentStatus = $balance <= 0 ? 'paid' : 'unpaid';

            $billingNumber = $this->generateBillingNumber();

            $billingStmt = $this->db->prepare("
                INSERT INTO billings (
                    billing_number,
                    patient_id,
                    appointment_id,
                    dentist_id,
                    created_by,
                    subtotal,
                    discount_amount,
                    total_amount,
                    amount_paid,
                    balance,
                    payment_status,
                    remarks,
                    created_at,
                    updated_at
                ) VALUES (
                    :billing_number,
                    :patient_id,
                    :appointment_id,
                    :dentist_id,
                    :created_by,
                    :subtotal,
                    :discount_amount,
                    :total_amount,
                    :amount_paid,
                    :balance,
                    :payment_status,
                    :remarks,
                    NOW(),
                    NOW()
                )
            ");

            $billingStmt->bindValue(':billing_number', $billingNumber);
            $billingStmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
            $billingStmt->bindValue(':appointment_id', $appointmentId, PDO::PARAM_INT);

            if ($dentistId === null) {
                $billingStmt->bindValue(':dentist_id', null, PDO::PARAM_NULL);
            } else {
                $billingStmt->bindValue(':dentist_id', $dentistId, PDO::PARAM_INT);
            }

            $billingStmt->bindValue(':created_by', $staffUserId, PDO::PARAM_INT);
            $billingStmt->bindValue(':subtotal', number_format($subtotal, 2, '.', ''));
            $billingStmt->bindValue(':discount_amount', number_format($discountAmount, 2, '.', ''));
            $billingStmt->bindValue(':total_amount', number_format($totalAmount, 2, '.', ''));
            $billingStmt->bindValue(':amount_paid', number_format($amountPaid, 2, '.', ''));
            $billingStmt->bindValue(':balance', number_format($balance, 2, '.', ''));
            $billingStmt->bindValue(':payment_status', $paymentStatus);
            $billingStmt->bindValue(':remarks', 'Auto-generated from completed appointment.');
            $billingStmt->execute();

            $billingId = (int) $this->db->lastInsertId();

            $itemStmt = $this->db->prepare("
                INSERT INTO billing_items (
                    billing_id,
                    service_id,
                    item_name,
                    description,
                    quantity,
                    unit_price,
                    total_price,
                    created_at
                ) VALUES (
                    :billing_id,
                    :service_id,
                    :item_name,
                    :description,
                    1,
                    :unit_price,
                    :total_price,
                    NOW()
                )
            ");

            $itemStmt->bindValue(':billing_id', $billingId, PDO::PARAM_INT);

            if ($serviceId === null) {
                $itemStmt->bindValue(':service_id', null, PDO::PARAM_NULL);
            } else {
                $itemStmt->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
            }

            $itemStmt->bindValue(':item_name', $itemName);
            $itemStmt->bindValue(':description', 'Initial billing item from completed appointment.');
            $itemStmt->bindValue(':unit_price', number_format($unitPrice, 2, '.', ''));
            $itemStmt->bindValue(':total_price', number_format($unitPrice, 2, '.', ''));
            $itemStmt->execute();

            $this->recomputeTotals($billingId);

            if ($startedTransaction) {
                $this->db->commit();
            }

            return $billingId;
        } catch (Throwable $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function updatePaymentSummary(int $billingId, float $newAmountPaid, float $newBalance, string $newStatus): bool
    {
        if ($billingId <= 0) {
            return false;
        }

        if (!in_array($newStatus, ['unpaid', 'partial', 'paid', 'cancelled'], true)) {
            throw new RuntimeException('Invalid payment status.');
        }

        $stmt = $this->db->prepare("
            UPDATE billings
            SET
                amount_paid = :amount_paid,
                balance = :balance,
                payment_status = :payment_status,
                updated_at = NOW()
            WHERE billing_id = :billing_id
            LIMIT 1
        ");

        $stmt->execute([
            ':amount_paid' => number_format($newAmountPaid, 2, '.', ''),
            ':balance' => number_format($newBalance, 2, '.', ''),
            ':payment_status' => $newStatus,
            ':billing_id' => $billingId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function generateBillingNumber(): string
    {
        do {
            $number = 'BILL-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM billings
                WHERE billing_number = :billing_number
            ");

            $stmt->execute([
                ':billing_number' => $number,
            ]);

            $exists = (int) $stmt->fetchColumn() > 0;
        } while ($exists);

        return $number;
    }

    public function recomputeTotals(int $billingId): bool
    {
        if ($billingId <= 0) {
            return false;
        }

        $subtotalStmt = $this->db->prepare("
            SELECT COALESCE(SUM(total_price), 0)
            FROM billing_items
            WHERE billing_id = :billing_id
        ");

        $subtotalStmt->execute([
            ':billing_id' => $billingId,
        ]);

        $subtotal = round((float) $subtotalStmt->fetchColumn(), 2);

        $billingStmt = $this->db->prepare("
            SELECT discount_amount, payment_status
            FROM billings
            WHERE billing_id = :billing_id
            LIMIT 1
        ");

        $billingStmt->execute([
            ':billing_id' => $billingId,
        ]);

        $billing = $billingStmt->fetch(PDO::FETCH_ASSOC);

        if (!$billing) {
            return false;
        }

        $discountAmount = round((float) ($billing['discount_amount'] ?? 0), 2);

        if ($discountAmount < 0) {
            $discountAmount = 0.00;
        }

        if ($discountAmount > $subtotal) {
            $discountAmount = $subtotal;
        }

        $totalAmount = max(0, round($subtotal - $discountAmount, 2));

        $paidStmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount_paid), 0)
            FROM billing_payments
            WHERE billing_id = :billing_id
        ");

        $paidStmt->execute([
            ':billing_id' => $billingId,
        ]);

        $amountPaid = round((float) $paidStmt->fetchColumn(), 2);
        $balance = max(0, round($totalAmount - $amountPaid, 2));

        $currentStatus = strtolower(trim((string) ($billing['payment_status'] ?? 'unpaid')));

        if ($currentStatus === 'cancelled') {
            $paymentStatus = 'cancelled';
        } elseif ($balance <= 0) {
            $paymentStatus = 'paid';
        } elseif ($amountPaid > 0) {
            $paymentStatus = 'partial';
        } else {
            $paymentStatus = 'unpaid';
        }

        $update = $this->db->prepare("
            UPDATE billings
            SET
                subtotal = :subtotal,
                discount_amount = :discount_amount,
                total_amount = :total_amount,
                amount_paid = :amount_paid,
                balance = :balance,
                payment_status = :payment_status,
                updated_at = NOW()
            WHERE billing_id = :billing_id
            LIMIT 1
        ");

        $update->execute([
            ':subtotal' => number_format($subtotal, 2, '.', ''),
            ':discount_amount' => number_format($discountAmount, 2, '.', ''),
            ':total_amount' => number_format($totalAmount, 2, '.', ''),
            ':amount_paid' => number_format($amountPaid, 2, '.', ''),
            ':balance' => number_format($balance, 2, '.', ''),
            ':payment_status' => $paymentStatus,
            ':billing_id' => $billingId,
        ]);

        return true;
    }

    public function countPendingBills(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*)
            FROM billings
            WHERE payment_status IN ('unpaid', 'partial')
              AND balance > 0
        ");

        return (int) $stmt->fetchColumn();
    }

    private function isValidDate(string $date): bool
    {
        if ($date === '') {
            return false;
        }

        $parsed = \DateTime::createFromFormat('Y-m-d', $date);
        $errors = \DateTime::getLastErrors();

        return $parsed
            && $parsed->format('Y-m-d') === $date
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }


    public function syncTreatmentBilling(int $treatmentId, int $userId): int
{
    if ($treatmentId <= 0) {
        throw new RuntimeException('Invalid treatment record.');
    }

    if ($userId <= 0) {
        throw new RuntimeException('Invalid user.');
    }

    $startedTransaction = false;

    try {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $startedTransaction = true;
        }

        $treatment = $this->autoBillingFindTreatment($treatmentId);

        if (!$treatment) {
            throw new RuntimeException('Treatment record not found.');
        }

        $patientId = (int) ($treatment['patient_id'] ?? 0);
        $appointmentId = !empty($treatment['appointment_id']) ? (int) $treatment['appointment_id'] : null;
        $dentistId = !empty($treatment['dentist_id']) ? (int) $treatment['dentist_id'] : null;

        if ($patientId <= 0) {
            throw new RuntimeException('Treatment is not linked to a patient.');
        }

        $procedureName = trim((string) ($treatment['procedure_name'] ?? ''));

        if ($procedureName === '') {
            $procedureName = trim((string) ($treatment['service_name'] ?? ''));
        }

        if ($procedureName === '') {
            $procedureName = 'Dental Treatment';
        }

        $actualCharge = round((float) ($treatment['actual_charge'] ?? 0), 2);

        if ($actualCharge < 0) {
            throw new RuntimeException('Treatment charge cannot be negative.');
        }

        $billing = $this->autoBillingFindBillingForTreatment($treatmentId, $appointmentId);

        if ($billing) {
            $billingId = (int) ($billing['billing_id'] ?? 0);
        } else {
            $billingId = $this->autoBillingCreateShell(
                $patientId,
                $appointmentId,
                $dentistId,
                $treatmentId,
                $userId
            );
        }

        $this->autoBillingUpsertTreatmentItem(
            $billingId,
            $treatmentId,
            $procedureName,
            $treatment,
            $actualCharge
        );

        $this->updatePaymentTotals($billingId);

        if ($startedTransaction) {
            $this->db->commit();
        }

        return $billingId;
    } catch (Throwable $e) {
        if ($startedTransaction && $this->db->inTransaction()) {
            $this->db->rollBack();
        }

        throw $e;
    }
}

public function updatePaymentTotals(int $billingId): bool
{
    if ($billingId <= 0) {
        return false;
    }

    $subtotalStmt = $this->db->prepare("
        SELECT COALESCE(SUM(total_price), 0)
        FROM billing_items
        WHERE billing_id = :billing_id
    ");

    $subtotalStmt->execute([
        ':billing_id' => $billingId,
    ]);

    $subtotal = round((float) $subtotalStmt->fetchColumn(), 2);

    $billingStmt = $this->db->prepare("
        SELECT
            discount_amount,
            payment_status
        FROM billings
        WHERE billing_id = :billing_id
        LIMIT 1
    ");

    $billingStmt->execute([
        ':billing_id' => $billingId,
    ]);

    $billing = $billingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$billing) {
        return false;
    }

    $discountAmount = round((float) ($billing['discount_amount'] ?? 0), 2);

    if ($discountAmount < 0) {
        $discountAmount = 0.00;
    }

    if ($discountAmount > $subtotal) {
        $discountAmount = $subtotal;
    }

    $totalAmount = max(0, round($subtotal - $discountAmount, 2));

    $paidStmt = $this->db->prepare("
        SELECT COALESCE(SUM(amount_paid), 0)
        FROM billing_payments
        WHERE billing_id = :billing_id
    ");

    $paidStmt->execute([
        ':billing_id' => $billingId,
    ]);

    $amountPaid = round((float) $paidStmt->fetchColumn(), 2);
    $balance = max(0, round($totalAmount - $amountPaid, 2));

    $currentStatus = strtolower(trim((string) ($billing['payment_status'] ?? 'unpaid')));

    if ($currentStatus === 'cancelled') {
        $paymentStatus = 'cancelled';
    } elseif ($balance <= 0) {
        $paymentStatus = 'paid';
    } elseif ($amountPaid > 0) {
        $paymentStatus = 'partial';
    } else {
        $paymentStatus = 'unpaid';
    }

    $update = $this->db->prepare("
        UPDATE billings
        SET
            subtotal = :subtotal,
            discount_amount = :discount_amount,
            total_amount = :total_amount,
            amount_paid = :amount_paid,
            balance = :balance,
            payment_status = :payment_status,
            updated_at = NOW()
        WHERE billing_id = :billing_id
        LIMIT 1
    ");

    $update->execute([
        ':subtotal' => number_format($subtotal, 2, '.', ''),
        ':discount_amount' => number_format($discountAmount, 2, '.', ''),
        ':total_amount' => number_format($totalAmount, 2, '.', ''),
        ':amount_paid' => number_format($amountPaid, 2, '.', ''),
        ':balance' => number_format($balance, 2, '.', ''),
        ':payment_status' => $paymentStatus,
        ':billing_id' => $billingId,
    ]);

    return true;
}

private function autoBillingFindTreatment(int $treatmentId): ?array
{
    $stmt = $this->db->prepare("
        SELECT
            t.*,
            a.service_id,
            s.service_name
        FROM treatments t
        LEFT JOIN appointments a ON a.appointment_id = t.appointment_id
        LEFT JOIN services s ON s.service_id = a.service_id
        WHERE t.treatment_id = :treatment_id
        LIMIT 1
    ");

    $stmt->execute([
        ':treatment_id' => $treatmentId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

private function autoBillingFindBillingForTreatment(int $treatmentId, ?int $appointmentId): ?array
{
    if ($this->autoBillingHasColumn('billing_items', 'treatment_id')) {
        $stmt = $this->db->prepare("
            SELECT b.*
            FROM billings b
            INNER JOIN billing_items bi ON bi.billing_id = b.billing_id
            WHERE bi.treatment_id = :treatment_id
            ORDER BY b.billing_id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':treatment_id' => $treatmentId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row;
        }
    }

    if ($this->autoBillingHasColumn('billings', 'treatment_id')) {
        $stmt = $this->db->prepare("
            SELECT *
            FROM billings
            WHERE treatment_id = :treatment_id
            ORDER BY billing_id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':treatment_id' => $treatmentId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row;
        }
    }

    if ($appointmentId !== null && $appointmentId > 0) {
        $stmt = $this->db->prepare("
            SELECT *
            FROM billings
            WHERE appointment_id = :appointment_id
            ORDER BY billing_id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':appointment_id' => $appointmentId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row;
        }
    }

    return null;
}

private function autoBillingCreateShell(
    int $patientId,
    ?int $appointmentId,
    ?int $dentistId,
    int $treatmentId,
    int $userId
): int {
    $columns = [
        'billing_number',
        'patient_id',
        'appointment_id',
        'dentist_id',
        'created_by',
        'subtotal',
        'discount_amount',
        'total_amount',
        'amount_paid',
        'balance',
        'payment_status',
        'remarks',
        'created_at',
        'updated_at',
    ];

    $values = [
        ':billing_number',
        ':patient_id',
        ':appointment_id',
        ':dentist_id',
        ':created_by',
        '0.00',
        '0.00',
        '0.00',
        '0.00',
        '0.00',
        "'unpaid'",
        ':remarks',
        'NOW()',
        'NOW()',
    ];

    if ($this->autoBillingHasColumn('billings', 'treatment_id')) {
        array_splice($columns, 3, 0, 'treatment_id');
        array_splice($values, 3, 0, ':treatment_id');
    }

    $sql = "
        INSERT INTO billings (
            " . implode(', ', $columns) . "
        ) VALUES (
            " . implode(', ', $values) . "
        )
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->bindValue(':billing_number', $this->generateBillingNumber());
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);

    if ($appointmentId === null) {
        $stmt->bindValue(':appointment_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':appointment_id', $appointmentId, PDO::PARAM_INT);
    }

    if ($this->autoBillingHasColumn('billings', 'treatment_id')) {
        $stmt->bindValue(':treatment_id', $treatmentId, PDO::PARAM_INT);
    }

    if ($dentistId === null) {
        $stmt->bindValue(':dentist_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':dentist_id', $dentistId, PDO::PARAM_INT);
    }

    $stmt->bindValue(':created_by', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':remarks', 'Auto-created from dentist treatment record.');
    $stmt->execute();

    return (int) $this->db->lastInsertId();
}

private function autoBillingUpsertTreatmentItem(
    int $billingId,
    int $treatmentId,
    string $procedureName,
    array $treatment,
    float $actualCharge
): void {
    $descriptionParts = [];

    $treatedTooth = trim((string) ($treatment['treated_tooth'] ?? ''));

    if ($treatedTooth !== '') {
        $descriptionParts[] = 'Tooth: ' . $treatedTooth;
    }

    $descriptionParts[] = 'Treatment ID: ' . $treatmentId;

    $description = implode("\n", $descriptionParts);
    $serviceId = !empty($treatment['service_id']) ? (int) $treatment['service_id'] : null;

    $billingItemId = 0;

    if ($this->autoBillingHasColumn('billing_items', 'treatment_id')) {
        $stmt = $this->db->prepare("
            SELECT billing_item_id
            FROM billing_items
            WHERE treatment_id = :treatment_id
            LIMIT 1
        ");

        $stmt->execute([
            ':treatment_id' => $treatmentId,
        ]);

        $billingItemId = (int) $stmt->fetchColumn();
    }

    if ($billingItemId <= 0) {
        $stmt = $this->db->prepare("
            SELECT billing_item_id
            FROM billing_items
            WHERE billing_id = :billing_id
              AND description LIKE :marker
            LIMIT 1
        ");

        $stmt->execute([
            ':billing_id' => $billingId,
            ':marker' => '%Treatment ID: ' . $treatmentId . '%',
        ]);

        $billingItemId = (int) $stmt->fetchColumn();
    }

    if ($billingItemId > 0) {
        $update = $this->db->prepare("
            UPDATE billing_items
            SET
                billing_id = :billing_id,
                service_id = :service_id,
                item_name = :item_name,
                description = :description,
                quantity = 1,
                unit_price = :unit_price,
                total_price = :total_price
            WHERE billing_item_id = :billing_item_id
            LIMIT 1
        ");

        $update->bindValue(':billing_id', $billingId, PDO::PARAM_INT);

        if ($serviceId === null) {
            $update->bindValue(':service_id', null, PDO::PARAM_NULL);
        } else {
            $update->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
        }

        $update->bindValue(':item_name', $procedureName);
        $update->bindValue(':description', $description);
        $update->bindValue(':unit_price', number_format($actualCharge, 2, '.', ''));
        $update->bindValue(':total_price', number_format($actualCharge, 2, '.', ''));
        $update->bindValue(':billing_item_id', $billingItemId, PDO::PARAM_INT);
        $update->execute();

        return;
    }

    $columns = [
        'billing_id',
        'service_id',
        'item_name',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'created_at',
    ];

    $values = [
        ':billing_id',
        ':service_id',
        ':item_name',
        ':description',
        '1',
        ':unit_price',
        ':total_price',
        'NOW()',
    ];

    if ($this->autoBillingHasColumn('billing_items', 'treatment_id')) {
        array_splice($columns, 2, 0, 'treatment_id');
        array_splice($values, 2, 0, ':treatment_id');
    }

    $sql = "
        INSERT INTO billing_items (
            " . implode(', ', $columns) . "
        ) VALUES (
            " . implode(', ', $values) . "
        )
    ";

    $insert = $this->db->prepare($sql);

    $insert->bindValue(':billing_id', $billingId, PDO::PARAM_INT);

    if ($serviceId === null) {
        $insert->bindValue(':service_id', null, PDO::PARAM_NULL);
    } else {
        $insert->bindValue(':service_id', $serviceId, PDO::PARAM_INT);
    }

    if ($this->autoBillingHasColumn('billing_items', 'treatment_id')) {
        $insert->bindValue(':treatment_id', $treatmentId, PDO::PARAM_INT);
    }

    $insert->bindValue(':item_name', $procedureName);
    $insert->bindValue(':description', $description);
    $insert->bindValue(':unit_price', number_format($actualCharge, 2, '.', ''));
    $insert->bindValue(':total_price', number_format($actualCharge, 2, '.', ''));
    $insert->execute();
}

private function autoBillingHasColumn(string $tableName, string $columnName): bool
{
    static $cache = [];

    $key = strtolower($tableName . '.' . $columnName);

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");

        $stmt->execute([
            ':table_name' => $tableName,
            ':column_name' => $columnName,
        ]);

        $cache[$key] = (int) $stmt->fetchColumn() > 0;

        return $cache[$key];
    } catch (Throwable $e) {
        $cache[$key] = false;

        return false;
    }
}
}