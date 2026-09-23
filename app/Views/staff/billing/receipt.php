<?php

$pageTitle = 'Payment Receipt';

$receipt = isset($receipt) && is_array($receipt) ? $receipt : [];

$baseUrl = '/DentalClinic/public';

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('receiptMoney')) {
    function receiptMoney($value): string
    {
        return '₱' . number_format((float) $value, 2);
    }
}

if (!function_exists('receiptName')) {
    function receiptName(array $row, string $prefix): string
    {
        $name = trim(implode(' ', array_filter([
            $row[$prefix . '_first_name'] ?? '',
            $row[$prefix . '_middle_name'] ?? '',
            $row[$prefix . '_last_name'] ?? '',
        ])));

        return $name !== '' ? $name : 'Unknown';
    }
}


if (!function_exists('receiptPaymentMethodLabel')) {
    function receiptPaymentMethodLabel(string $method): string
    {
        $labels = [
            'cash' => 'Cash',
            'bank_transfer_manual' => 'Bank Transfer Manual',
            'card_terminal' => 'Card Terminal',
            'other' => 'Other',
        ];

        return $labels[$method] ?? 'Other';
    }
}

if (!function_exists('receiptDate')) {
    function receiptDate($date): string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return '—';
        }

        $time = strtotime($date);

        return $time ? date('M d, Y h:i A', $time) : $date;
    }
}

if (!function_exists('receiptOnlyDate')) {
    function receiptOnlyDate($date): string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return '—';
        }

        $time = strtotime($date);

        return $time ? date('M d, Y', $time) : $date;
    }
}

if (!function_exists('receiptMethod')) {
    function receiptMethod(string $method): string
    {
        $labels = [
            'cash' => 'Cash',
            'gcash_manual' => 'GCash Manual',
            'bank_transfer_manual' => 'Bank Transfer Manual',
            'card_terminal' => 'Card Terminal',
            'other' => 'Other',
        ];

        return $labels[$method] ?? ucwords(str_replace('_', ' ', $method));
    }
}

$billingId = (int) ($receipt['billing_id'] ?? 0);

ob_start();
?>

<style>
.receipt-page {
    max-width: 850px;
    margin: 24px auto;
    background: #ffffff;
    border: 1px solid #d1d5db;
    padding: 28px;
    color: #111827;
}

.receipt-header {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    border-bottom: 2px solid #111827;
    padding-bottom: 18px;
    margin-bottom: 22px;
}

.receipt-header h1 {
    margin: 0;
    font-size: 26px;
}

.receipt-header p {
    margin: 4px 0 0;
    color: #6b7280;
}

.receipt-section {
    margin-top: 20px;
}

.receipt-section h2 {
    margin: 0 0 10px;
    font-size: 15px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.receipt-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    border-top: 1px solid #e5e7eb;
    border-left: 1px solid #e5e7eb;
}

.receipt-grid div {
    padding: 12px;
    border-right: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
}

.receipt-grid span {
    display: block;
    margin-bottom: 5px;
    color: #6b7280;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.receipt-grid strong {
    display: block;
    word-break: break-word;
}

.receipt-total {
    margin-top: 20px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.receipt-total div {
    border: 1px solid #d1d5db;
    padding: 14px;
}

.receipt-total span {
    display: block;
    margin-bottom: 6px;
    color: #6b7280;
    font-size: 12px;
    text-transform: uppercase;
}

.receipt-total strong {
    font-size: 20px;
}

.receipt-footer {
    margin-top: 24px;
    padding-top: 14px;
    border-top: 1px solid #e5e7eb;
    color: #6b7280;
    font-size: 13px;
}

.receipt-actions {
    max-width: 850px;
    margin: 18px auto;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn {
    display: inline-block;
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    text-decoration: none;
    cursor: pointer;
    font-weight: 600;
}

.btn-primary {
    background: #0f766e;
    border-color: #0f766e;
    color: #ffffff;
}

@media print {
    body {
        background: #ffffff !important;
    }

    .receipt-actions,
    aside,
    header,
    nav {
        display: none !important;
    }

    .receipt-page {
        max-width: 100%;
        margin: 0;
        border: none;
        padding: 0;
    }
}
</style>

<div class="receipt-page">
    <div class="receipt-header">
        <div>
            <h1>Dental Clinic</h1>
            <p>Payment Receipt</p>
        </div>

        <div>
            <strong><?= e($receipt['receipt_number'] ?? '') ?></strong>
            <p><?= e(receiptDate($receipt['paid_at'] ?? '')) ?></p>
        </div>
    </div>

    <div class="receipt-section">
        <h2>Receipt Information</h2>

        <div class="receipt-grid">
            <div>
                <span>Receipt Number</span>
                <strong><?= e($receipt['receipt_number'] ?? '') ?></strong>
            </div>

            <div>
                <span>Billing Number</span>
                <strong><?= e($receipt['billing_number'] ?? '') ?></strong>
            </div>

            <div>
                <span>Date Paid</span>
                <strong><?= e(receiptDate($receipt['paid_at'] ?? '')) ?></strong>
            </div>

            <div>
                <span>Received By</span>
                <strong><?= e(receiptName($receipt, 'received_by')) ?></strong>
            </div>
        </div>
    </div>

    <div class="receipt-section">
        <h2>Patient and Appointment</h2>

        <div class="receipt-grid">
            <div>
                <span>Patient Name</span>
                <strong><?= e(receiptName($receipt, 'patient')) ?></strong>
            </div>

            <div>
                <span>Contact Number</span>
                <strong><?= e($receipt['patient_contact_number'] ?? 'No contact') ?></strong>
            </div>

            <div>
                <span>Appointment Date</span>
                <strong><?= e(receiptOnlyDate($receipt['appointment_date'] ?? '')) ?></strong>
            </div>

            <div>
                <span>Dentist</span>
                <strong>Dr. <?= e(receiptName($receipt, 'dentist')) ?></strong>
            </div>
        </div>
    </div>

    <div class="receipt-section">
        <h2>Payment Information</h2>

        <div class="receipt-grid">
            <div>
                <span>Payment Method</span>
                <strong><?= e(receiptMethod((string) ($receipt['payment_method'] ?? ''))) ?></strong>
            </div>

            <div>
                <span>Reference Number</span>
                <strong><?= e($receipt['reference_number'] ?? '—') ?></strong>
            </div>

            <div>
                <span>Service / Procedure</span>
                <strong><?= e($receipt['service_name'] ?? 'Manual Billing') ?></strong>
            </div>

            <div>
                <span>Remarks</span>
                <strong><?= e($receipt['remarks'] ?? '—') ?></strong>
            </div>
        </div>

        <div class="receipt-total">
            <div>
                <span>Amount Paid</span>
                <strong><?= receiptMoney($receipt['amount_paid'] ?? 0) ?></strong>
            </div>

            <div>
                <span>Balance Before</span>
                <strong><?= receiptMoney($receipt['balance_before'] ?? 0) ?></strong>
            </div>

            <div>
                <span>Balance After</span>
                <strong><?= receiptMoney($receipt['balance_after'] ?? 0) ?></strong>
            </div>
        </div>
    </div>

    <p class="receipt-footer">
        This receipt confirms that payment information was manually recorded by clinic staff.
        This is not an online payment confirmation.
    </p>
</div>

<div class="receipt-actions">
    <a class="btn" href="<?= e($baseUrl) ?>/staff/billing/show?id=<?= $billingId ?>">
        Back to Billing Details
    </a>

    <button class="btn btn-primary" type="button" onclick="window.print()">
        Print Receipt
    </button>
</div>

<?php
$staffContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';