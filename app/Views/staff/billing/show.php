<?php

use App\Core\Csrf;

$pageTitle = 'Billing Details';

$billing = isset($billing) && is_array($billing) ? $billing : [];
$items = isset($items) && is_array($items) ? $items : [];
$payments = isset($payments) && is_array($payments) ? $payments : [];

$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

$baseUrl = '/DentalClinic/public';
$csrfToken = Csrf::token();

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('billingShowMoney')) {
    function billingShowMoney($value): string
    {
        return '₱' . number_format((float) $value, 2);
    }
}

if (!function_exists('billingShowName')) {
    function billingShowName(array $row, string $prefix): string
    {
        $name = trim(implode(' ', array_filter([
            $row[$prefix . '_first_name'] ?? '',
            $row[$prefix . '_middle_name'] ?? '',
            $row[$prefix . '_last_name'] ?? '',
        ])));

        return $name !== '' ? $name : 'Unknown';
    }
}

if (!function_exists('billingShowDate')) {
    function billingShowDate($date): string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return '—';
        }

        $time = strtotime($date);

        return $time ? date('M d, Y', $time) : $date;
    }
}

if (!function_exists('billingShowDateTime')) {
    function billingShowDateTime($date): string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return '—';
        }

        $time = strtotime($date);

        return $time ? date('M d, Y h:i A', $time) : $date;
    }
}

if (!function_exists('billingShowTime')) {
    function billingShowTime($time): string
    {
        $time = trim((string) $time);

        if ($time === '') {
            return '—';
        }

        $parsed = strtotime($time);

        return $parsed ? date('h:i A', $parsed) : $time;
    }
}

if (!function_exists('billingShowStatus')) {
    function billingShowStatus(string $status): string
    {
        $status = trim($status);

        if ($status === '') {
            return 'Unpaid';
        }

        return ucwords(str_replace('_', ' ', $status));
    }
}

if (!function_exists('billingShowPaymentMethod')) {
    function billingShowPaymentMethod(string $method): string
    {
        return 'Cash';
    }
}

$billingId = (int) ($billing['billing_id'] ?? 0);
$paymentStatus = strtolower(trim((string) ($billing['payment_status'] ?? 'unpaid')));
$balance = (float) ($billing['balance'] ?? 0);
$canPay = $billingId > 0 && $balance > 0 && !in_array($paymentStatus, ['paid', 'cancelled'], true);

ob_start();
?>

<link rel="stylesheet" href="<?= e($baseUrl) ?>/assets/css/staff-billing.css">

<style>
.billing-detail-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.billing-detail-box {
    border: 1px solid #e5e7eb;
    background: #ffffff;
    padding: 14px;
}

.billing-detail-box span {
    display: block;
    margin-bottom: 6px;
    color: #6b7280;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.billing-detail-box strong {
    display: block;
    color: #111827;
    word-break: break-word;
}

.billing-detail-box small {
    display: block;
    margin-top: 4px;
    color: #6b7280;
    font-size: 12px;
}

.payment-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.payment-form-grid .form-full {
    grid-column: 1 / -1;
}

.billing-table small {
    display: block;
    margin-top: 4px;
    color: #6b7280;
    font-size: 12px;
}

.readonly-payment-method {
    background: #f9fafb;
    cursor: not-allowed;
}

@media (max-width: 900px) {
    .billing-detail-grid,
    .payment-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="staff-billing-page">
    <div class="billing-header">
        <div>
            <p class="eyebrow">Staff Module</p>
            <h1>Billing Details</h1>
            <p class="muted">
                View automatic billing totals, payment history, and record cash payment information.
            </p>
        </div>

        <a class="btn btn-light" href="<?= e($baseUrl) ?>/staff/billing">
            Back to Billing
        </a>
    </div>

    <?php if (!empty($flash_success)): ?>
        <div class="alert alert-success">
            <?= e($flash_success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($flash_error)): ?>
        <div class="alert alert-error">
            <?= e($flash_error) ?>
        </div>
    <?php endif; ?>

    <section class="billing-card">
        <div class="table-header">
            <div>
                <h2><?= e($billing['billing_number'] ?? ('BILL-' . $billingId)) ?></h2>
                <p class="muted">Generated automatically by the system.</p>
            </div>

            <span class="status-pill status-<?= e($paymentStatus) ?>">
                <?= e(billingShowStatus($paymentStatus)) ?>
            </span>
        </div>

        <div class="billing-detail-grid">
            <div class="billing-detail-box">
                <span>Patient</span>
                <strong><?= e(billingShowName($billing, 'patient')) ?></strong>
                <small><?= e($billing['patient_code'] ?? '') ?></small>
            </div>

            <div class="billing-detail-box">
                <span>Contact Number</span>
                <strong><?= e($billing['patient_contact_number'] ?? 'No contact') ?></strong>
            </div>

            <div class="billing-detail-box">
                <span>Email</span>
                <strong><?= e($billing['patient_email'] ?? 'No email') ?></strong>
            </div>

            <div class="billing-detail-box">
                <span>Appointment</span>
                <strong><?= e(billingShowDate($billing['appointment_date'] ?? '')) ?></strong>
                <small><?= e(billingShowTime($billing['start_time'] ?? '')) ?></small>
            </div>

            <div class="billing-detail-box">
                <span>Appointment Status</span>
                <strong><?= e(billingShowStatus((string) ($billing['appointment_status'] ?? ''))) ?></strong>
            </div>

            <div class="billing-detail-box">
                <span>Dentist</span>
                <strong>Dr. <?= e(billingShowName($billing, 'dentist')) ?></strong>
            </div>
        </div>
    </section>

    <section class="summary-grid">
        <div class="summary-card">
            <span>Subtotal</span>
            <strong><?= billingShowMoney($billing['subtotal'] ?? 0) ?></strong>
        </div>

        <div class="summary-card">
            <span>Discount</span>
            <strong><?= billingShowMoney($billing['discount_amount'] ?? 0) ?></strong>
        </div>

        <div class="summary-card">
            <span>Total Amount</span>
            <strong><?= billingShowMoney($billing['total_amount'] ?? 0) ?></strong>
        </div>

        <div class="summary-card">
            <span>Amount Paid</span>
            <strong><?= billingShowMoney($billing['amount_paid'] ?? 0) ?></strong>
        </div>

        <div class="summary-card">
            <span>Balance</span>
            <strong><?= billingShowMoney($billing['balance'] ?? 0) ?></strong>
        </div>
    </section>

    <section class="billing-card">
        <div class="table-header">
            <div>
                <h2>Billing Items</h2>
                <p class="muted">Subtotal is computed from these items.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="billing-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Description</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                No billing items found.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong><?= e($item['item_name'] ?? '') ?></strong>
                            </td>

                            <td>
                                <?= e($item['description'] ?? '—') ?>
                            </td>

                            <td>
                                <?= (int) ($item['quantity'] ?? 1) ?>
                            </td>

                            <td>
                                <?= billingShowMoney($item['unit_price'] ?? 0) ?>
                            </td>

                            <td>
                                <strong><?= billingShowMoney($item['total_price'] ?? 0) ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($canPay): ?>
        <section class="billing-card" id="record-payment">
            <div class="table-header">
                <div>
                    <h2>Record Cash Payment</h2>
                    <p class="muted">
                        Staff enters the cash payment amount only. Receipt number, balance, and status are automatic.
                    </p>
                </div>
            </div>

            <form method="POST" action="<?= e($baseUrl) ?>/staff/billing/payment">
                <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="billing_id" value="<?= $billingId ?>">
                <input type="hidden" name="payment_method" value="cash">

                <div class="payment-form-grid">
                    <div class="form-group">
                        <label for="amount_paid">Payment Amount</label>
                        <input
                            type="number"
                            id="amount_paid"
                            name="amount_paid"
                            min="0.01"
                            max="<?= e(number_format($balance, 2, '.', '')) ?>"
                            step="0.01"
                            required
                            placeholder="Maximum <?= e(number_format($balance, 2, '.', '')) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Payment Method</label>
                        <input
                            type="text"
                            value="Cash"
                            class="readonly-payment-method"
                            readonly
                        >
                    </div>

                    <div class="form-group">
                        <label for="reference_number">Reference Number</label>
                        <input
                            type="text"
                            id="reference_number"
                            name="reference_number"
                            maxlength="120"
                            placeholder="Optional"
                        >
                    </div>

                    <div class="form-group">
                        <label>Current Balance</label>
                        <input
                            type="text"
                            value="<?= e(billingShowMoney($balance)) ?>"
                            readonly
                        >
                    </div>

                    <div class="form-group form-full">
                        <label for="remarks">Remarks</label>
                        <textarea
                            id="remarks"
                            name="remarks"
                            rows="3"
                            maxlength="1000"
                            placeholder="Optional payment note"
                        ></textarea>
                    </div>
                </div>

                <div class="filter-actions" style="margin-top:14px;">
                    <button class="btn btn-primary" type="submit">
                        Save Payment and Generate Receipt
                    </button>
                </div>
            </form>
        </section>
    <?php else: ?>
        <section class="billing-card">
            <h2>Payment</h2>
            <p class="muted">
                This billing record cannot receive a new payment because it is already paid, cancelled, or has no remaining balance.
            </p>
        </section>
    <?php endif; ?>

    <section class="billing-card">
        <div class="table-header">
            <div>
                <h2>Payment History</h2>
                <p class="muted">Each cash payment has its own generated receipt.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="billing-table">
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Date Paid</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Before</th>
                        <th>After</th>
                        <th>Received By</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                No payment records found.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($payments as $payment): ?>
                        <?php $paymentId = (int) ($payment['payment_id'] ?? 0); ?>

                        <tr>
                            <td>
                                <strong><?= e($payment['receipt_number'] ?? '') ?></strong>

                                <?php if (!empty($payment['reference_number'])): ?>
                                    <small>Ref: <?= e($payment['reference_number']) ?></small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= e(billingShowDateTime($payment['paid_at'] ?? '')) ?>
                            </td>

                            <td>
                                <?= e(billingShowPaymentMethod((string) ($payment['payment_method'] ?? 'cash'))) ?>
                            </td>

                            <td>
                                <strong><?= billingShowMoney($payment['amount_paid'] ?? 0) ?></strong>
                            </td>

                            <td>
                                <?= billingShowMoney($payment['balance_before'] ?? 0) ?>
                            </td>

                            <td>
                                <?= billingShowMoney($payment['balance_after'] ?? 0) ?>
                            </td>

                            <td>
                                <?= e(billingShowName($payment, 'received_by')) ?>
                            </td>

                            <td>
                                <?php if ($paymentId > 0): ?>
                                    <a
                                        class="btn btn-small btn-light"
                                        target="_blank"
                                        href="<?= e($baseUrl) ?>/staff/billing/receipt?id=<?= $paymentId ?>"
                                    >
                                        Receipt
                                    </a>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php
$staffContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';