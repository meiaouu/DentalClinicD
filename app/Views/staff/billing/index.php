<?php

use App\Core\Csrf;

$pageTitle = 'Staff Billing';

$summary = isset($summary) && is_array($summary) ? $summary : [];
$billings = isset($billings) && is_array($billings) ? $billings : [];
$readyAppointments = isset($readyAppointments) && is_array($readyAppointments) ? $readyAppointments : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];

$page = max(1, (int) ($page ?? 1));
$perPage = max(1, (int) ($perPage ?? 15));
$total = max(0, (int) ($total ?? 0));
$totalPages = max(1, (int) ceil($total / $perPage));

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

if (!function_exists('billingMoney')) {
    function billingMoney($value): string
    {
        return '₱' . number_format((float) $value, 2);
    }
}

if (!function_exists('billingName')) {
    function billingName(array $row, string $prefix): string
    {
        $name = trim(implode(' ', array_filter([
            $row[$prefix . '_first_name'] ?? '',
            $row[$prefix . '_middle_name'] ?? '',
            $row[$prefix . '_last_name'] ?? '',
        ])));

        return $name !== '' ? $name : 'Unknown';
    }
}

if (!function_exists('billingStatus')) {
    function billingStatus(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}

if (!function_exists('billingDate')) {
    function billingDate($date): string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return '—';
        }

        $time = strtotime($date);

        return $time ? date('M d, Y', $time) : $date;
    }
}

if (!function_exists('billingTime')) {
    function billingTime($time): string
    {
        $time = trim((string) $time);

        if ($time === '') {
            return '—';
        }

        $parsed = strtotime($time);

        return $parsed ? date('h:i A', $parsed) : $time;
    }
}

$queryBase = $filters;
unset($queryBase['page'], $queryBase['per_page']);

$selectedStatus = (string) ($filters['status'] ?? '');

$statuses = [
    '' => 'All',
    'unpaid' => 'Unpaid',
    'partial' => 'Partial',
    'paid' => 'Paid',
    'cancelled' => 'Cancelled',
];

ob_start();
?>


<link rel="stylesheet" href="<?= e($baseUrl) ?>/assets/css/staff-billing.css">

<style>


.staff-billing-page,
.staff-billing-page * {
    box-sizing: border-box;
    font-family: var(--font-ui);
}

.staff-billing-page button,
.staff-billing-page input,
.staff-billing-page select,
.staff-billing-page textarea {
    font-family: var(--font-ui);
}

.billing-table {
    font-family: var(--font-ui);
}

.billing-table small {
    display: block;
    margin-top: 4px;
    color: #6b7280;
    font-size: 12px;
}

.billing-table td:first-child strong,
.ready-billing-item small,
.pagination span {
    font-family: var(--font-mono);
}

.actions-col {
    white-space: nowrap;
}

.ready-billing-list {
    display: grid;
    gap: 10px;
}

.ready-billing-item {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr auto;
    gap: 12px;
    align-items: center;
    padding: 12px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
}

.ready-billing-item strong,
.ready-billing-item small {
    display: block;
}

.ready-billing-item small {
    color: #6b7280;
    margin-top: 3px;
}

@media (max-width: 900px) {
    .ready-billing-item {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="staff-billing-page">
    <div class="billing-header">
        <div>

        </div>
    </div>

    <?php if (!empty($flash_success)): ?>
        <div class="alert alert-success"><?= e($flash_success) ?></div>
    <?php endif; ?>

    <?php if (!empty($flash_error)): ?>
        <div class="alert alert-error"><?= e($flash_error) ?></div>
    <?php endif; ?>

    <section class="summary-grid">
        <div class="summary-card">
            <span>Total Unpaid Balance</span>
            <strong><?= billingMoney($summary['total_unpaid_balance'] ?? 0) ?></strong>
        </div>

        <div class="summary-card">
            <span>Today's Collections</span>
            <strong><?= billingMoney($summary['today_collections'] ?? 0) ?></strong>
        </div>

        <div class="summary-card">
            <span>Paid Bills</span>
            <strong><?= (int) ($summary['paid_bills_count'] ?? 0) ?></strong>
        </div>

        <div class="summary-card">
            <span>Partial Bills</span>
            <strong><?= (int) ($summary['partial_bills_count'] ?? 0) ?></strong>
        </div>
    </section>

    <?php if (!empty($readyAppointments)): ?>
        <section class="billing-card">
            <div class="table-header">
                <div>
                    <h2>Completed Appointments Ready for Billing</h2>
                    <p class="muted">Generate billing automatically from completed appointments without existing billing records.</p>
                </div>
            </div>

            <div class="ready-billing-list">
                <?php foreach ($readyAppointments as $appointment): ?>
                    <?php
                    $appointmentId = (int) ($appointment['appointment_id'] ?? 0);
                    $patientName = billingName($appointment, 'patient');
                    $dentistName = billingName($appointment, 'dentist');
                    $serviceName = trim((string) ($appointment['service_name'] ?? 'Dental Service'));
                    $price = (float) ($appointment['estimated_price'] ?? $appointment['service_estimated_price'] ?? 0);
                    ?>

                    <div class="ready-billing-item">
                        <div>
                            <strong><?= e($patientName) ?></strong>
                            <small><?= e($appointment['patient_code'] ?? '') ?></small>
                        </div>

                        <div>
                            <strong><?= e($serviceName) ?></strong>
                            <small><?= billingMoney($price) ?></small>
                        </div>

                        <div>
                            <strong><?= e(billingDate($appointment['appointment_date'] ?? '')) ?></strong>
                            <small><?= e(billingTime($appointment['start_time'] ?? '')) ?> · Dr. <?= e($dentistName) ?></small>
                        </div>

                        <form method="POST" action="<?= e($baseUrl) ?>/staff/billing/generate-from-appointment">
                            <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="appointment_id" value="<?= $appointmentId ?>">

                            <button type="submit" class="btn btn-primary">
                                Generate Billing
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="billing-card">
        <form method="GET" action="<?= e($baseUrl) ?>/staff/billing" class="filter-form">
            <div class="form-group form-grow">
                <label for="keyword">Search</label>
                <input
                    type="text"
                    id="keyword"
                    name="keyword"
                    value="<?= e($filters['keyword'] ?? '') ?>"
                    placeholder="Search patient name, patient code, billing number, or appointment code"
                >
            </div>

            <div class="form-group">
                <label for="status">Payment Status</label>
                <select id="status" name="status">
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="date_from">Date From</label>
                <input type="date" id="date_from" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="date_to">Date To</label>
                <input type="date" id="date_to" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary" type="submit">Filter</button>
                <a class="btn btn-light" href="<?= e($baseUrl) ?>/staff/billing">Reset</a>
            </div>
        </form>
    </section>

    <section class="billing-card">
        <div class="table-header">
            <div>
                <h2>Billing Records</h2>
                <p class="muted"><?= (int) $total ?> record(s) found from the database.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="billing-table">
                <thead>
                    <tr>
                        <th>Billing</th>
                        <th>Patient</th>
                        <th>Appointment</th>
                        <th>Service / Procedure</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($billings)): ?>
                        <tr>
                            <td colspan="9" class="empty-state">No billing records found.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($billings as $billing): ?>
                        <?php
                        $billingId = (int) ($billing['billing_id'] ?? 0);
                        $patientName = billingName($billing, 'patient');
                        $paymentStatus = strtolower(trim((string) ($billing['payment_status'] ?? 'unpaid')));
                        $balance = (float) ($billing['balance'] ?? 0);
                        $canPay = $billingId > 0 && $balance > 0 && !in_array($paymentStatus, ['paid', 'cancelled'], true);
                        ?>

                        <tr>
                            <td>
                                <strong><?= e($billing['billing_number'] ?? ('BILL-' . $billingId)) ?></strong>
                                <small><?= e(billingDate($billing['created_at'] ?? '')) ?></small>
                            </td>

                            <td>
                                <strong><?= e($patientName) ?></strong>
                                <small><?= e($billing['patient_code'] ?? '') ?></small>
                            </td>

                            <td>
                                <?php if (!empty($billing['appointment_date'])): ?>
                                    <strong><?= e(billingDate($billing['appointment_date'])) ?></strong>
                                    <small><?= e(billingTime($billing['start_time'] ?? '')) ?></small>
                                <?php else: ?>
                                    <span class="muted">No appointment</span>
                                <?php endif; ?>
                            </td>

                            <td><?= e($billing['service_name'] ?? 'Manual Billing') ?></td>

                            <td><strong><?= billingMoney($billing['total_amount'] ?? 0) ?></strong></td>
                            <td><?= billingMoney($billing['amount_paid'] ?? 0) ?></td>
                            <td><strong><?= billingMoney($billing['balance'] ?? 0) ?></strong></td>

                            <td>
                                <span class="status-pill status-<?= e($paymentStatus) ?>">
                                    <?= e(billingStatus($paymentStatus)) ?>
                                </span>
                            </td>

                            <td class="actions-col">
                                <a class="btn btn-small" href="<?= e($baseUrl) ?>/staff/billing/show?id=<?= $billingId ?>">
                                    View
                                </a>

                                <?php if ($canPay): ?>
                                    <a class="btn btn-small btn-primary" href="<?= e($baseUrl) ?>/staff/billing/show?id=<?= $billingId ?>#record-payment">
                                        Payment
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($billing['latest_payment_id'])): ?>
                                    <a
                                        class="btn btn-small btn-light"
                                        target="_blank"
                                        href="<?= e($baseUrl) ?>/staff/billing/receipt?id=<?= (int) $billing['latest_payment_id'] ?>"
                                    >
                                        Receipt
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <?php $prevQuery = http_build_query(array_merge($queryBase, ['page' => $page - 1])); ?>
                    <a class="btn btn-light" href="<?= e($baseUrl) ?>/staff/billing?<?= e($prevQuery) ?>">Previous</a>
                <?php endif; ?>

                <span>Page <?= (int) $page ?> of <?= (int) $totalPages ?></span>

                <?php if ($page < $totalPages): ?>
                    <?php $nextQuery = http_build_query(array_merge($queryBase, ['page' => $page + 1])); ?>
                    <a class="btn btn-light" href="<?= e($baseUrl) ?>/staff/billing?<?= e($nextQuery) ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php
$staffContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
?>