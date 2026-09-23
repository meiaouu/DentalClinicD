<?php
$requests = $requests ?? [];
$services = $services ?? [];
$selectedServiceId = $selectedServiceId ?? null;
$sort = $sort ?? 'latest';
$page = $page ?? 1;
$perPage = $perPage ?? 10;
$total = $total ?? 0;
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('requestNiceDate')) {
    function requestNiceDate(?string $date): string
    {
        if (!$date) {
            return '';
        }

        $timestamp = strtotime($date);

        return $timestamp ? date('Y-m-d', $timestamp) : $date;
    }
}

if (!function_exists('requestNiceTime')) {
    function requestNiceTime(?string $time): string
    {
        if (!$time) {
            return '';
        }

        $timestamp = strtotime($time);

        return $timestamp ? date('h:i A', $timestamp) : $time;
    }
}

if (!function_exists('requestStatusText')) {
    function requestStatusText(string $status): string
    {
        return ucwords(str_replace('_', ' ', strtolower(trim($status))));
    }
}

if (!function_exists('requestStatusClass')) {
    function requestStatusClass(string $status): string
    {
        $status = strtolower(trim($status));

        return preg_replace('/[^a-z0-9_-]/', '', $status) ?: 'pending';
    }
}

if (!function_exists('requestServiceText')) {
    function requestServiceText(array $request): string
    {
        if (!empty($request['service_names']) && is_array($request['service_names'])) {
            $names = array_filter(array_map('trim', array_map('strval', $request['service_names'])));
            $text = implode(', ', $names);

            if ($text !== '') {
                return $text;
            }
        }

        if (!empty($request['service_names_text'])) {
            return (string) $request['service_names_text'];
        }

        if (!empty($request['services_text'])) {
            return (string) $request['services_text'];
        }

        if (!empty($request['service_name'])) {
            return (string) $request['service_name'];
        }

        return '';
    }
}

ob_start();
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<style>


.staff-request-page,
.staff-request-page * {
    box-sizing: border-box;
}

.staff-request-page {
    min-height: calc(100dvh - 74px);
    background: #f5f6f800;
    color: #1f2937;
    padding: 2px 16px 40px;
    font-family: var(--font-ui);
}

.request-shell {
    max-width: 1180px;
    margin: 0 auto;
}

.request-flash-wrap {
    display: grid;
    gap: 8px;
    margin-bottom: 14px;
}

.flash-box {
    padding: 11px 13px;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    border-radius: 8px;
}

.flash-box.success {
    color: #15803d;
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.flash-box.error {
    color: #b91c1c;
    background: #fef2f2;
    border-color: #fecaca;
}

.filter-card,
.request-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 1px;
    box-shadow: 0 8px 26px rgba(15, 23, 42, 0.04);
}

.filter-card {
    padding: 18px;
    margin-bottom: 10px;
}

.filter-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 220px 180px;
    gap: 12px;
    align-items: end;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    font-size: 12px;
    font-weight: 700;
    color: #374151;
}

.form-control,
.form-group select {
    width: 100%;
    height: 30px;
    border: 1px solid #d1d5db;
    border-radius: 1px;
    background: #ffffff;
    color: #111827;
    padding: 0 12px;
    font-size: 14px;
    font-family: var(--font-ui);
    outline: none;
}

.form-control:focus,
.form-group select:focus {
    border-color: #9ca3af;
    box-shadow: 0 0 0 3px rgba(156, 163, 175, 0.14);
}

.request-card {
    padding: 18px;
}

.request-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.request-title {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #111827;
}

.request-table-wrap {
    width: 100%;
    overflow-x: auto;
}

#appointmentRequestsTable {
    width: 100% !important;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
    background: #ffffff;
    font-family: var(--font-ui);
}

#appointmentRequestsTable thead th {
    background: #f9fafb;
    color: #374151;
    font-size: 12px;
    font-weight: 800;
    border-bottom: 1px solid #e5e7eb;
    padding: 12px 10px;
    text-align: left;
    vertical-align: middle;
    white-space: nowrap;
}

#appointmentRequestsTable tbody td {
    border-bottom: 1px solid #eef2f7;
    padding: 12px 10px;
    vertical-align: middle;
    color: #111827;
}

#appointmentRequestsTable tbody tr.clickable-request-row {
    cursor: pointer;
    transition: background 0.16s ease;
}

#appointmentRequestsTable tbody tr.clickable-request-row:hover {
    background: #f8fafc;
}

#appointmentRequestsTable tbody tr.request-row-new {
    background: #f1f5f9;
}

#appointmentRequestsTable tbody tr.request-row-new:hover {
    background: #e9eef5;
}

#appointmentRequestsTable tbody tr.request-row-new td {
    color: #64748b;
}

.request-name {
    font-weight: 800;
    color: #111827;
}

#appointmentRequestsTable tbody tr.request-row-new .request-name {
    color: #475569;
}

.guest-label,
.patient-label,
.new-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 20px;
    margin-left: 5px;
    padding: 0 7px;
    font-size: 10px;
    font-weight: 800;
    border-radius: 9px;
    vertical-align: middle;
}

.guest-label {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}

.patient-label {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #bbf7d0;
}

.new-label {
    background: #e2e8f0;
    color: #475569;
    border: 1px solid #cbd5e1;
}

.request-services {
    display: block;
    max-width: 320px;
    color: #111827;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.45;
    white-space: normal;
    word-break: break-word;
}

.status-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 24px;
    padding: 0 9px;
    border-radius: 2px;
    border: 1px solid #d1d5db;
    font-size: 11px;
    font-weight: 800;
    text-transform: capitalize;
    background: #ffffff;
    white-space: nowrap;
}

.status-label.pending,
.status-label.under_review,
.status-label.rescheduled {
    color: #92400e;
    border-color: #ffffff;
    background: #fff1bb;
}

.status-label.confirmed {
    color: #166534;
    border-color: #bbf7d0;
    background: #9fe8b5;
}

.status-label.rejected,
.status-label.cancelled,
.status-label.cancelled_by_patient {
    color: #b91c1c;
    border-color: #fecaca;
    background: #f6aeae;
}

.dataTables_wrapper {
    width: 100%;
    font-family: var(--font-ui);
}

.request-table-tools {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
}

.dt-buttons {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
    margin-bottom: 8px;
}

.dt-button {
    border: 1px solid #4d9674 !important;
    background: #ffffff !important;
    color: #175447 !important;
    padding: 6px 12px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    box-shadow: none !important;
    margin: 0 !important;
    border-radius: 0 !important;
    font-family: var(--font-ui) !important;
}

.dt-button:hover {
    background: #f9fafb !important;
    color: #111827 !important;
}

.dataTables_length,
.dataTables_info,
.dataTables_paginate {
    font-size: 13px;
    color: #374151;
    font-family: var(--font-ui);
}

.dataTables_length select {
    border: 1px solid #d1d5db;
    border-radius: 7px;
    padding: 5px 7px;
    background: #ffffff;
    font-family: var(--font-ui);
}

.dataTables_paginate .paginate_button {
    border: 1px solid #d1d5db !important;
    background: #ffffff !important;
    color: #374151 !important;
    padding: 6px 9px !important;
    margin-left: 3px !important;
    border-radius: 7px !important;
}

.dataTables_paginate .paginate_button.current {
    background: #111827 !important;
    color: #ffffff !important;
    border-color: #111827 !important;
}

.dataTables_paginate .paginate_button.disabled {
    opacity: 0.5;
}

@media (max-width: 900px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .staff-request-page {
        padding: 18px 10px 32px;
    }

    .request-table-tools,
    .dt-buttons {
        justify-content: flex-start;
    }
}
</style>

<div class="staff-request-page">
    <div class="request-shell">

        <?php if ($flash_success || $flash_error): ?>
            <div class="request-flash-wrap">
                <?php if ($flash_success): ?>
                    <div class="flash-box success"><?= e((string) $flash_success) ?></div>
                <?php endif; ?>

                <?php if ($flash_error): ?>
                    <div class="flash-box error"><?= e((string) $flash_error) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section class="filter-card">
            <form method="GET" action="/DentalClinic/public/staff/appointment-requests" id="requestFilterForm">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="requestSearch">Search Request</label>
                        <input
                            id="requestSearch"
                            class="form-control"
                            type="search"
                            placeholder="Search name, service, date, or status"
                            autocomplete="off"
                        >
                    </div>

                    <div class="form-group">
                        <label for="service_id">Service</label>
                        <select name="service_id" id="service_id" data-auto-filter="1">
                            <option value="">All Services</option>

                            <?php foreach ($services as $service): ?>
                                <?php
                                    $serviceId = is_array($service)
                                        ? (int) ($service['service_id'] ?? 0)
                                        : (int) ($service->service_id ?? 0);

                                    $serviceName = is_array($service)
                                        ? (string) ($service['service_name'] ?? '')
                                        : (string) ($service->service_name ?? '');
                                ?>

                                <?php if ($serviceId > 0): ?>
                                    <option value="<?= $serviceId ?>" <?= ((int) $selectedServiceId === $serviceId) ? 'selected' : '' ?>>
                                        <?= e($serviceName) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="sort">Sort</label>
                        <select name="sort" id="sort" data-auto-filter="1">
                            <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Latest</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        </select>
                    </div>
                </div>
            </form>
        </section>

        <section class="request-card">
            <div class="request-card-head">
                <h2 class="request-title">Requests List</h2>
            </div>

            <div class="request-table-wrap">
                <table id="appointmentRequestsTable" class="display nowrap">
                    <thead>
                        <tr>
                            <th>Preferred Date</th>
                            <th>Preferred Time</th>
                            <th>Fullname</th>
                            <th>Date Created</th>
                            <th>Service(s)</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <?php
                                $requestId = (int) ($request['request_id'] ?? 0);
                                $patientId = (int) ($request['patient_id'] ?? 0);
                                $isGuest = $patientId <= 0;

                                $requestName = trim((string) (
                                    (!$isGuest && !empty($request['patient_first_name']) ? $request['patient_first_name'] : ($request['guest_first_name'] ?? ''))
                                    . ' ' .
                                    (!$isGuest && !empty($request['patient_middle_name']) ? $request['patient_middle_name'] : ($request['guest_middle_name'] ?? ''))
                                    . ' ' .
                                    (!$isGuest && !empty($request['patient_last_name']) ? $request['patient_last_name'] : ($request['guest_last_name'] ?? ''))
                                ));

                                $requestStatus = strtolower(trim((string) ($request['request_status'] ?? 'pending')));
                                $requestStatusClass = requestStatusClass($requestStatus);
                                $requestUrl = '/DentalClinic/public/staff/appointment-requests/show?id=' . $requestId;

                                $isNewUnopened = $requestStatus === 'pending' && empty($request['reviewed_at']);

                                $createdAt = (string) ($request['created_at'] ?? '');
                                $createdOrder = strtotime($createdAt) ?: $requestId;

                                $preferredDate = (string) ($request['preferred_date'] ?? '');
                                $preferredDateOrder = strtotime($preferredDate) ?: 0;

                                $preferredStartTime = (string) ($request['preferred_start_time'] ?? '');
                                $preferredTimeOrder = strtotime($preferredStartTime) ?: 0;

                                $serviceText = requestServiceText($request);
                            ?>

                            <tr
                                class="clickable-request-row<?= $isNewUnopened ? ' request-row-new' : '' ?>"
                                data-href="<?= e($requestUrl) ?>"
                                tabindex="0"
                            >
                                <td data-order="<?= e((string) $preferredDateOrder) ?>">
                                    <?= e(requestNiceDate($preferredDate)) ?>
                                </td>

                                <td data-order="<?= e((string) $preferredTimeOrder) ?>">
                                    <?= e(requestNiceTime($preferredStartTime)) ?>
                                </td>

                                <td>
                                    <span class="request-name">
                                        <?= e($requestName !== '' ? $requestName : 'Unknown Requester') ?>
                                    </span>

                                    <?php if ($isGuest): ?>
                                        <span class="guest-label">Guest</span>
                                    <?php else: ?>
                                        <span class="patient-label">Patient</span>
                                    <?php endif; ?>

                                    <?php if ($isNewUnopened): ?>
                                        <span class="new-label">New</span>
                                    <?php endif; ?>
                                </td>

                                <td data-order="<?= e((string) $createdOrder) ?>">
                                    <?= e(requestNiceDate($createdAt)) ?>
                                </td>

                                <td>
                                    <span class="request-services">
                                        <?= e($serviceText !== '' ? $serviceText : '—') ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status-label <?= e($requestStatusClass) ?>">
                                        <?= e(requestStatusText($requestStatus)) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('requestFilterForm');
    const autoFilterFields = document.querySelectorAll('[data-auto-filter="1"]');
    const requestSearch = document.getElementById('requestSearch');
    const sortDirection = <?= json_encode($sort === 'oldest' ? 'asc' : 'desc') ?>;

    autoFilterFields.forEach(function (field) {
        field.addEventListener('change', function () {
            if (filterForm) {
                filterForm.submit();
            }
        });
    });

    if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
        return;
    }

    const table = jQuery('#appointmentRequestsTable');

    const dataTable = table.DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        ordering: true,
        searching: true,
        autoWidth: false,
        scrollX: true,
        order: [[3, sortDirection]],
        dom: '<"request-table-tools"B>lirtp',
        buttons: [
            {
                extend: 'copy',
                text: 'Copy',
                title: 'Appointment Requests'
            },
            {
                extend: 'csv',
                text: 'CSV',
                title: 'Appointment Requests'
            },
            {
                extend: 'excel',
                text: 'Excel',
                title: 'Appointment Requests'
            },
            {
                extend: 'pdf',
                text: 'PDF',
                title: 'Appointment Requests'
            },
            {
                extend: 'print',
                text: 'Print',
                title: 'Appointment Requests'
            }
        ],
        language: {
            emptyTable: 'No appointment requests found',
            zeroRecords: 'No matching appointment requests found'
        }
    });

    if (requestSearch) {
        requestSearch.addEventListener('input', function () {
            dataTable.search(this.value).draw();
        });
    }

    table.on('click', 'tbody tr.clickable-request-row', function (event) {
        if (event.target.closest('a, button, input, select, textarea')) {
            return;
        }

        const href = this.getAttribute('data-href');

        if (href) {
            window.location.href = href;
        }
    });

    table.on('keydown', 'tbody tr.clickable-request-row', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();

        const href = this.getAttribute('data-href');

        if (href) {
            window.location.href = href;
        }
    });
});
</script>

<?php
$staffContent = ob_get_clean();
$pageTitle = 'Appointment Requests';
require __DIR__ . '/../layouts/app.php';
?>