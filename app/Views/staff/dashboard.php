<?php

$pendingRequestsCount = (int) ($pendingRequestsCount ?? 0);
$todayAppointmentsCount = (int) ($todayAppointmentsCount ?? 0);
$patientsCount = (int) ($patientsCount ?? 0);
$pendingFollowUpsCount = (int) ($pendingFollowUpsCount ?? 0);
$upcomingAppointmentsCount = (int) ($upcomingAppointmentsCount ?? 0);
$pendingBillsCount = (int) ($pendingBillsCount ?? 0);

$todayAppointments = isset($todayAppointments) && is_array($todayAppointments) ? $todayAppointments : [];
$recentRequests = isset($recentRequests) && is_array($recentRequests) ? $recentRequests : [];
$recentPatients = isset($recentPatients) && is_array($recentPatients) ? $recentPatients : [];
$pendingBills = isset($pendingBills) && is_array($pendingBills) ? $pendingBills : [];

$authUser = $authUser ?? null;

$displayName = trim((string) (($authUser['first_name'] ?? '') . ' ' . ($authUser['last_name'] ?? '')));

if ($displayName === '') {
    $displayName = 'Staff';
}

$baseUrl = '/DentalClinic/public';

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dashboardDate')) {
    function dashboardDate(?string $date): string
    {
        if (!$date) {
            return '—';
        }

        $time = strtotime($date);

        return $time ? date('M d, Y', $time) : e($date);
    }
}

if (!function_exists('dashboardTime')) {
    function dashboardTime(?string $time): string
    {
        if (!$time) {
            return '';
        }

        $parsed = strtotime($time);

        return $parsed ? date('h:i A', $parsed) : e($time);
    }
}

if (!function_exists('dashboardMoney')) {
    function dashboardMoney($value): string
    {
        return '₱' . number_format((float) $value, 2);
    }
}

if (!function_exists('dashboardFullName')) {
    function dashboardFullName(array $row, string $prefix = 'patient'): string
    {
        $name = trim((string) (
            ($row[$prefix . '_first_name'] ?? '') . ' ' .
            ($row[$prefix . '_middle_name'] ?? '') . ' ' .
            ($row[$prefix . '_last_name'] ?? '')
        ));

        return $name !== '' ? $name : 'Unknown';
    }
}

ob_start();
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
:root {
    --font-ui: 'DM Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    --font-mono: 'DM Mono', Consolas, "Liberation Mono", monospace;
}

.staff-dashboard-page,
.staff-dashboard-page *,
.dashboard-modal,
.dashboard-modal * {
    box-sizing: border-box;
}

.staff-dashboard-page {
    padding: 18px;
    background: #f4f4f4;
    min-height: calc(100dvh - 74px);
    color: #111827;
    font-family: var(--font-ui);
}

.staff-dashboard-page button,
.staff-dashboard-page input,
.staff-dashboard-page select,
.staff-dashboard-page textarea,
.dashboard-modal button,
.dashboard-modal input,
.dashboard-modal select,
.dashboard-modal textarea {
    font-family: var(--font-ui);
}

.dashboard-title-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 14px;
}

.dashboard-title {
    margin: 0;
    font-size: 24px;
    font-weight: 900;
    letter-spacing: -0.03em;
}

.dashboard-subtitle {
    margin: 4px 0 0;
    color: #555;
    font-size: 13px;
}

.dashboard-date {
    font-size: 13px;
    font-weight: 700;
    background: #fff;
    border: 1px solid #ddd;
    padding: 8px 10px;
}

.dashboard-menu {
    background: #ffffff;
    border: 1px solid #ddd;
    padding: 8px 10px;
    margin-bottom: 14px;
    font-size: 13px;
    color: #444;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}

.summary-card {
    background: #ffffff;
    border: 1px solid #d6d6d6;
    padding: 14px;
    min-height: 92px;
}

.pending-card {
    background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.10), rgba(255, 255, 255, 0)),
        linear-gradient(120deg, #52c5a8 0%, #1f9a94 100%);
    width: 100%;
}

.summary-card-title {
    margin: 0;
    font-size: 14px;
    font-weight: 900;
}

.summary-card-value {
    margin: 8px 0;
    font-size: 26px;
    font-weight: 900;
    letter-spacing: -0.04em;
}

.summary-card-link,
.summary-card-button {
    display: inline-block;
    border: none;
    background: transparent;
    padding: 0;
    color: #111827;
    font-size: 13px;
    font-weight: 700;
    text-decoration: underline;
    cursor: pointer;
}

.summary-card-link:hover,
.summary-card-button:hover,
.action-link:hover {
    color: #0f766e;
}

.dashboard-content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.dashboard-panel {
    background: #ffffff;
    border: 1px solid #d6d6d6;
    padding: 14px;
}

.panel-title {
    margin: 0 0 10px;
    font-size: 17px;
    font-weight: 900;
    letter-spacing: -0.02em;
}

.simple-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.simple-table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
    font-size: 13px;
    font-family: var(--font-ui);
}

.simple-table th,
.simple-table td {
    border: 1px solid #ddd;
    padding: 9px;
    text-align: left;
    vertical-align: top;
}

.simple-table th {
    background: #f7f7f7;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.simple-table small {
    font-family: var(--font-mono);
    color: #6b7280;
}

.status-label {
    display: inline-block;
    border: 1px solid #aaa;
    padding: 3px 7px;
    font-size: 12px;
    font-weight: 700;
    text-transform: capitalize;
    white-space: nowrap;
}

.empty-box {
    border: 1px dashed #bbb;
    padding: 18px;
    color: #555;
    font-size: 13px;
    text-align: center;
}

.dashboard-actions {
    margin-top: 12px;
}

.action-link {
    color: #111827;
    font-weight: 800;
    font-size: 13px;
    text-decoration: underline;
}

.dashboard-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 9999;
    padding: 30px 16px;
    overflow-y: auto;
    font-family: var(--font-ui);
}

.dashboard-modal.active {
    display: block;
}

.modal-box {
    max-width: 900px;
    margin: 0 auto;
    background: #ffffff;
    border: 1px solid #ccc;
    padding: 14px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
    margin-bottom: 10px;
}

.modal-title {
    margin: 0;
    font-size: 18px;
    font-weight: 900;
    letter-spacing: -0.02em;
}

.modal-close {
    border: none;
    background: transparent;
    font-size: 24px;
    cursor: pointer;
    line-height: 1;
}

.dataTables_wrapper {
    width: 100%;
    font-family: var(--font-ui);
}

.modal-table-tools {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 10px;
}

.modal-table-tools .dt-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.modal-table-tools .dt-button {
    border: 1px solid #9ca3af !important;
    background: #ffffff !important;
    color: #111827 !important;
    padding: 5px 9px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    cursor: pointer;
    font-family: var(--font-ui) !important;
}

.modal-table-tools .dataTables_length,
.modal-table-tools .dataTables_filter {
    font-size: 13px;
    font-family: var(--font-ui);
}

.modal-table-tools .dataTables_filter input,
.modal-table-tools .dataTables_length select {
    border: 1px solid #9ca3af;
    padding: 5px 7px;
    background: #ffffff;
    font-family: var(--font-ui);
}

.modal-table-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    font-size: 13px;
    font-family: var(--font-ui);
}

.dataTables_paginate .paginate_button {
    border: 1px solid #d1d5db !important;
    background: #ffffff !important;
    color: #111827 !important;
    padding: 5px 8px !important;
    margin-left: 2px !important;
    font-family: var(--font-ui) !important;
}

.dataTables_paginate .paginate_button.current {
    background: #b1b1b1 !important;
    color: #ffffff !important;
    border-color: #111827 !important;
}

@media (max-width: 1000px) {
    .summary-grid,
    .dashboard-content-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 700px) {
    .staff-dashboard-page {
        padding: 12px;
    }

    .dashboard-title-row {
        display: block;
    }

    .dashboard-date {
        margin-top: 10px;
        width: fit-content;
    }

    .summary-grid,
    .dashboard-content-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="staff-dashboard-page">
    <div class="dashboard-title-row">
        <div>
            <h1 class="dashboard-title">Dashboard</h1>
            <p class="dashboard-subtitle">
                Welcome back, <?= e($displayName) ?>. Here is today’s clinic overview.
            </p>
        </div>

        <div class="dashboard-date">
            <?= e(date('F d, Y')) ?>
        </div>
    </div>

    <section class="summary-grid">
        <div class="summary-card pending-card">
            <h2 class="summary-card-title">Pending Appointment</h2>
            <div class="summary-card-value" id="pendingRequestsCount"><?= $pendingRequestsCount ?></div>

            <button type="button" class="summary-card-button" data-open-modal="pendingRequestsModal">
                More Info
            </button>
        </div>

        <div class="summary-card">
            <h2 class="summary-card-title">Pending Bill</h2>
            <div class="summary-card-value" id="pendingBillsCount"><?= $pendingBillsCount ?></div>

            <button type="button" class="summary-card-button" data-open-modal="pendingBillsModal">
                More Info
            </button>
        </div>

        <div class="summary-card">
            <h2 class="summary-card-title">Today’s Appointments</h2>
            <div class="summary-card-value" id="todayAppointmentsCount"><?= $todayAppointmentsCount ?></div>

            <a class="summary-card-link" href="<?= e($baseUrl . '/staff/appointments?date=' . date('Y-m-d')) ?>">
                View Schedule
            </a>
        </div>

        <div class="summary-card">
            <h2 class="summary-card-title">Patients</h2>
            <div class="summary-card-value" id="patientsCount"><?= $patientsCount ?></div>

            <a class="summary-card-link" href="<?= e($baseUrl . '/staff/patients') ?>">
                View Patients
            </a>
        </div>
    </section>

    <section class="dashboard-content-grid">
        <div class="dashboard-panel">
            <h2 class="panel-title">Recent Patient</h2>

            <?php if (empty($recentPatients)): ?>
                <div class="empty-box">No patient records found.</div>
            <?php else: ?>
                <div class="simple-table-wrap">
                    <table class="simple-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th>Address</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($recentPatients as $patient): ?>
                                <?php
                                    $patientName = trim((string) (
                                        ($patient['first_name'] ?? '') . ' ' .
                                        ($patient['middle_name'] ?? '') . ' ' .
                                        ($patient['last_name'] ?? '')
                                    ));

                                    if ($patientName === '') {
                                        $patientName = 'Unknown Patient';
                                    }
                                ?>

                                <tr>
                                    <td>
                                        <strong><?= e($patientName) ?></strong><br>
                                        <small><?= e((string) ($patient['patient_code'] ?? '')) ?></small>
                                    </td>
                                    <td><?= e((string) ($patient['contact_number'] ?? '—')) ?></td>
                                    <td><?= e((string) ($patient['address'] ?? '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="dashboard-actions">
                    <a class="action-link" href="<?= e($baseUrl . '/staff/patients') ?>">
                        Open Patient Records
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-panel">
            <h2 class="panel-title">Today’s Appointment</h2>

            <?php if (empty($todayAppointments)): ?>
                <div class="empty-box">No appointments scheduled today.</div>
            <?php else: ?>
                <div class="simple-table-wrap">
                    <table class="simple-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Service</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($todayAppointments as $appointment): ?>
                                <?php
                                    $patientName = dashboardFullName($appointment, 'patient');
                                    $statusText = (string) ($appointment['status'] ?? 'pending');
                                ?>

                                <tr>
                                    <td>
                                        <strong><?= e($patientName) ?></strong><br>
                                        <small><?= e((string) ($appointment['appointment_code'] ?? '')) ?></small>
                                    </td>

                                    <td><?= e((string) ($appointment['service_name'] ?? 'N/A')) ?></td>

                                    <td>
                                        <?= e(dashboardTime((string) ($appointment['start_time'] ?? ''))) ?>
                                        <?= !empty($appointment['end_time']) ? ' - ' . e(dashboardTime((string) $appointment['end_time'])) : '' ?>
                                    </td>

                                    <td>
                                        <span class="status-label"><?= e(str_replace('_', ' ', $statusText)) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="dashboard-actions">
                    <a class="action-link" href="<?= e($baseUrl . '/staff/appointments?date=' . date('Y-m-d')) ?>">
                        Open Appointments
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<div class="dashboard-modal" id="pendingRequestsModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2 class="modal-title">Pending Appointment Requests</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <div id="pendingRequestsEmpty" class="empty-box" <?= empty($recentRequests) ? '' : 'style="display:none;"' ?>>
            No pending appointment requests.
        </div>

        <div class="simple-table-wrap" id="pendingRequestsTableWrap" <?= empty($recentRequests) ? 'style="display:none;"' : '' ?>>
            <table id="pendingRequestsTable" class="simple-table export-modal-table">
                <thead>
                    <tr>
                        <th>Request Code</th>
                        <th>Requester</th>
                        <th>Service</th>
                        <th>Preferred Schedule</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody id="pendingRequestsTableBody">
                    <?php foreach ($recentRequests as $request): ?>
                        <?php
                            $requestId = (int) ($request['request_id'] ?? 0);

                            $requestName = trim((string) (
                                (!empty($request['patient_first_name']) ? $request['patient_first_name'] : ($request['guest_first_name'] ?? '')) . ' ' .
                                (!empty($request['patient_middle_name']) ? $request['patient_middle_name'] : ($request['guest_middle_name'] ?? '')) . ' ' .
                                (!empty($request['patient_last_name']) ? $request['patient_last_name'] : ($request['guest_last_name'] ?? ''))
                            ));

                            if ($requestName === '') {
                                $requestName = 'Unknown Requester';
                            }
                        ?>

                        <tr>
                            <td><?= e((string) ($request['request_code'] ?? ('REQ-' . $requestId))) ?></td>
                            <td><?= e($requestName) ?></td>
                            <td><?= e((string) ($request['service_name'] ?? 'N/A')) ?></td>

                            <td>
                                <?= e(dashboardDate((string) ($request['preferred_date'] ?? ''))) ?><br>
                                <small><?= e(dashboardTime((string) ($request['preferred_start_time'] ?? ''))) ?></small>
                            </td>

                            <td>
                                <span class="status-label"><?= e(str_replace('_', ' ', (string) ($request['request_status'] ?? 'pending'))) ?></span>
                            </td>

                            <td>
                                <?php if ($requestId > 0): ?>
                                    <a class="action-link" href="<?= e($baseUrl . '/staff/appointment-requests/show?id=' . $requestId) ?>">
                                        Review
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="dashboard-actions">
                <a class="action-link" href="<?= e($baseUrl . '/staff/appointment-requests') ?>">
                    Open All Requests
                </a>
            </div>
        </div>
    </div>
</div>

<div class="dashboard-modal" id="pendingBillsModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2 class="modal-title">Pending Bills</h2>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <?php if (empty($pendingBills)): ?>
            <div class="empty-box">No pending bills.</div>
        <?php else: ?>
            <div class="simple-table-wrap">
                <table id="pendingBillsTable" class="simple-table export-modal-table">
                    <thead>
                        <tr>
                            <th>Billing Ref</th>
                            <th>Patient</th>
                            <th>Service / Procedure</th>
                            <th>Total Amount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($pendingBills as $bill): ?>
                            <?php
                                $billingId = (int) ($bill['billing_id'] ?? 0);
                                $treatmentId = (int) ($bill['treatment_id'] ?? 0);

                                $billingRef = (string) (
                                    $bill['billing_number']
                                    ?? $bill['appointment_code']
                                    ?? ($billingId > 0 ? 'BILL-' . $billingId : 'TRT-' . $treatmentId)
                                );

                                $patientName = dashboardFullName($bill, 'patient');

                                $serviceName = trim((string) (
                                    $bill['service_name']
                                    ?? $bill['procedure_name']
                                    ?? 'Manual Billing'
                                ));

                                $paymentStatus = strtolower(trim((string) ($bill['payment_status'] ?? 'unpaid')));

                                $openUrl = $billingId > 0
                                    ? $baseUrl . '/staff/billing/show?id=' . $billingId
                                    : $baseUrl . '/staff/billing/show?treatment_id=' . $treatmentId;
                            ?>

                            <tr>
                                <td><?= e($billingRef) ?></td>

                                <td>
                                    <strong><?= e($patientName) ?></strong>
                                    <?php if (!empty($bill['patient_code'])): ?>
                                        <br>
                                        <small><?= e((string) $bill['patient_code']) ?></small>
                                    <?php endif; ?>
                                </td>

                                <td><?= e($serviceName !== '' ? $serviceName : 'Manual Billing') ?></td>

                                <td><?= e(dashboardMoney($bill['total_amount'] ?? 0)) ?></td>
                                <td><?= e(dashboardMoney($bill['amount_paid'] ?? 0)) ?></td>
                                <td><strong><?= e(dashboardMoney($bill['balance'] ?? 0)) ?></strong></td>

                                <td>
                                    <span class="status-label">
                                        <?= e(str_replace('_', ' ', $paymentStatus)) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($billingId > 0 || $treatmentId > 0): ?>
                                        <a class="action-link" href="<?= e($openUrl) ?>">
                                            Open
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="dashboard-actions">
                <a class="action-link" href="<?= e($baseUrl . '/staff/billing?status=unpaid') ?>">
                    Open Billing
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const baseUrl = '/DentalClinic/public';

    const openButtons = document.querySelectorAll('[data-open-modal]');
    const closeButtons = document.querySelectorAll('[data-close-modal]');
    const modals = document.querySelectorAll('.dashboard-modal');

    const pendingRequestsCount = document.getElementById('pendingRequestsCount');
    const pendingBillsCount = document.getElementById('pendingBillsCount');
    const todayAppointmentsCount = document.getElementById('todayAppointmentsCount');
    const patientsCount = document.getElementById('patientsCount');

    const pendingRequestsEmpty = document.getElementById('pendingRequestsEmpty');
    const pendingRequestsTableWrap = document.getElementById('pendingRequestsTableWrap');
    const pendingRequestsTableBody = document.getElementById('pendingRequestsTableBody');

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatDate(value) {
        if (!value) {
            return '—';
        }

        const date = new Date(value + 'T00:00:00');

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric'
        });
    }

    function formatTime(value) {
        if (!value) {
            return '';
        }

        const parts = String(value).split(':');
        let hour = parseInt(parts[0] || '0', 10);
        const minute = parts[1] || '00';
        const suffix = hour >= 12 ? 'PM' : 'AM';

        hour = hour % 12;

        if (hour === 0) {
            hour = 12;
        }

        return String(hour).padStart(2, '0') + ':' + minute + ' ' + suffix;
    }

    function closeAllModals() {
        modals.forEach(function (modal) {
            modal.classList.remove('active');
        });
    }

    function initExportTable(tableSelector, title) {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
            return;
        }

        const table = jQuery(tableSelector);

        if (!table.length) {
            return;
        }

        if (jQuery.fn.DataTable.isDataTable(tableSelector)) {
            table.DataTable().columns.adjust().draw(false);
            return;
        }

        table.DataTable({
            pageLength: 10,
            autoWidth: false,
            ordering: true,
            searching: true,
            lengthChange: true,
            dom: '<"modal-table-tools"lBf>rt<"modal-table-bottom"ip>',
            buttons: [
                {
                    extend: 'colvis',
                    text: 'Column visibility'
                },
                {
                    extend: 'copy',
                    text: 'Copy',
                    title: title
                },
                {
                    extend: 'csv',
                    text: 'CSV',
                    title: title
                },
                {
                    extend: 'excel',
                    text: 'Excel',
                    title: title
                },
                {
                    extend: 'pdf',
                    text: 'PDF',
                    title: title
                },
                {
                    extend: 'print',
                    text: 'Print',
                    title: title
                }
            ]
        });
    }

    function destroyDataTable(tableSelector) {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
            return;
        }

        if (jQuery.fn.DataTable.isDataTable(tableSelector)) {
            jQuery(tableSelector).DataTable().destroy();
        }
    }

    function renderPendingRequests(requests) {
        if (!pendingRequestsTableBody || !pendingRequestsEmpty || !pendingRequestsTableWrap) {
            return;
        }

        destroyDataTable('#pendingRequestsTable');

        pendingRequestsTableBody.innerHTML = '';

        if (!Array.isArray(requests) || requests.length === 0) {
            pendingRequestsEmpty.style.display = '';
            pendingRequestsTableWrap.style.display = 'none';
            return;
        }

        pendingRequestsEmpty.style.display = 'none';
        pendingRequestsTableWrap.style.display = '';

        requests.forEach(function (request) {
            const requestId = parseInt(request.request_id || 0, 10);

            const requester = [
                request.patient_first_name || request.guest_first_name || '',
                request.patient_middle_name || request.guest_middle_name || '',
                request.patient_last_name || request.guest_last_name || ''
            ].join(' ').trim() || 'Unknown Requester';

            const statusText = String(request.request_status || 'pending').replaceAll('_', ' ');

            const rowHtml =
                '<tr>' +
                    '<td>' + escapeHtml(request.request_code || ('REQ-' + requestId)) + '</td>' +
                    '<td>' + escapeHtml(requester) + '</td>' +
                    '<td>' + escapeHtml(request.service_name || 'N/A') + '</td>' +
                    '<td>' +
                        escapeHtml(formatDate(request.preferred_date || '')) +
                        '<br><small>' + escapeHtml(formatTime(request.preferred_start_time || '')) + '</small>' +
                    '</td>' +
                    '<td><span class="status-label">' + escapeHtml(statusText) + '</span></td>' +
                    '<td>' +
                        (requestId > 0
                            ? '<a class="action-link" href="' + baseUrl + '/staff/appointment-requests/show?id=' + encodeURIComponent(requestId) + '">Review</a>'
                            : '—') +
                    '</td>' +
                '</tr>';

            pendingRequestsTableBody.insertAdjacentHTML('beforeend', rowHtml);
        });
    }

    async function refreshDashboardStats() {
        try {
            const response = await fetch(baseUrl + '/staff/dashboard/stats', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store'
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (!data || data.success !== true) {
                return;
            }

            if (pendingRequestsCount) {
                pendingRequestsCount.textContent = String(data.pending_requests_count ?? 0);
            }

            if (pendingBillsCount) {
                pendingBillsCount.textContent = String(data.pending_bills_count ?? 0);
            }

            if (todayAppointmentsCount) {
                todayAppointmentsCount.textContent = String(data.today_appointments_count ?? 0);
            }

            if (patientsCount) {
                patientsCount.textContent = String(data.patients_count ?? 0);
            }

            if (Array.isArray(data.recent_requests)) {
                renderPendingRequests(data.recent_requests);
            }
        } catch (error) {
            console.error('Dashboard refresh failed:', error);
        }
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);

        if (!modal) {
            return;
        }

        closeAllModals();
        modal.classList.add('active');

        if (modalId === 'pendingRequestsModal') {
            refreshDashboardStats();
        }

        setTimeout(function () {
            if (modalId === 'pendingRequestsModal') {
                initExportTable('#pendingRequestsTable', 'Pending Appointment Requests');
            }

            if (modalId === 'pendingBillsModal') {
                initExportTable('#pendingBillsTable', 'Pending Bills');
            }
        }, 150);
    }

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const modalId = button.getAttribute('data-open-modal');
            openModal(modalId);
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', closeAllModals);
    });

    modals.forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeAllModals();
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });

    refreshDashboardStats();

    setInterval(refreshDashboardStats, 10000);

    window.addEventListener('focus', refreshDashboardStats);
});
</script>

<?php
$staffContent = ob_get_clean();
$pageTitle = 'Staff Dashboard';
require __DIR__ . '/layouts/app.php';
?>