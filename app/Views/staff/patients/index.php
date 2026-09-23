<?php
$patients = $patients ?? [];
$keyword = $keyword ?? '';
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('staffPatientFullName')) {
    function staffPatientFullName(array $patient): string
    {
        $name = trim(
            (string) ($patient['first_name'] ?? '') . ' ' .
            (string) ($patient['middle_name'] ?? '') . ' ' .
            (string) ($patient['last_name'] ?? '')
        );

        return $name !== '' ? $name : 'Unnamed Patient';
    }
}

if (!function_exists('staffPatientAgeValue')) {
    function staffPatientAgeValue(array $patient): string
    {
        if (empty($patient['birth_date'])) {
            return '—';
        }

        try {
            $birth = new DateTime((string) $patient['birth_date']);
            $today = new DateTime();

            return (string) $today->diff($birth)->y;
        } catch (Throwable $e) {
            return '—';
        }
    }
}

if (!function_exists('staffPatientStatusText')) {
    function staffPatientStatusText(string $status): string
    {
        $status = trim($status);

        return $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Active';
    }
}

if (!function_exists('staffPatientStatusClass')) {
    function staffPatientStatusClass(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'inactive' => 'inactive',
            'archived' => 'archived',
            default => 'active',
        };
    }
}

if (!function_exists('staffPatientCreatedAtRaw')) {
    function staffPatientCreatedAtRaw(array $patient): string
    {
        foreach (['created_at', 'registered_at', 'date_created', 'created_on'] as $column) {
            $value = trim((string) ($patient[$column] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

if (!function_exists('staffPatientAddedAgo')) {
    function staffPatientAddedAgo(array $patient): string
    {
        $createdAt = staffPatientCreatedAtRaw($patient);

        if ($createdAt === '') {
            return 'Added time unknown';
        }

        $timestamp = strtotime($createdAt);

        if ($timestamp === false) {
            return 'Added time unknown';
        }

        $now = time();
        $diff = max(0, $now - $timestamp);

        if ($diff < 1) {
            return 'Just now';
        }

        if ($diff < 60) {
            return 'Added ' . $diff . 's ago';
        }

        $minutes = intdiv($diff, 60);

        if ($minutes < 60) {
            return 'Added ' . $minutes . 'm ago';
        }

        $hours = intdiv($diff, 3600);

        if ($hours < 24) {
            return 'Added ' . $hours . 'h ago';
        }

        $days = intdiv($diff, 86400);

        if ($days < 30) {
            return 'Added ' . $days . 'd ago';
        }

        $months = intdiv($days, 30);

        if ($months < 12) {
            return 'Added ' . $months . 'mo ago';
        }

        $years = intdiv($days, 365);

        return 'Added ' . $years . 'y ago';
    }
}

if (!function_exists('staffPatientAddedTitle')) {
    function staffPatientAddedTitle(array $patient): string
    {
        $createdAt = staffPatientCreatedAtRaw($patient);

        if ($createdAt === '') {
            return 'Added time unknown';
        }

        $timestamp = strtotime($createdAt);

        if ($timestamp === false) {
            return 'Added time unknown';
        }

        return 'Added on ' . date('M d, Y h:i A', $timestamp);
    }
}

if (!function_exists('staffPatientCreatedSortValue')) {
    function staffPatientCreatedSortValue(array $patient): string
    {
        $createdAt = staffPatientCreatedAtRaw($patient);

        if ($createdAt === '') {
            return '0';
        }

        $timestamp = strtotime($createdAt);

        return $timestamp !== false ? (string) $timestamp : '0';
    }
}

ob_start();
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<style>
:root {
    --font-ui: 'DM Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    --font-mono: 'DM Mono', Consolas, "Liberation Mono", monospace;
}

.staff-patients-page,
.staff-patients-page * {
    box-sizing: border-box;
}

.staff-patients-page {
    min-height: calc(100dvh - 74px);
    background: #f5f6f800;
    color: #1f2937;
    padding: 2px 16px 40px;
    font-family: var(--font-ui);
}

.staff-patients-page input,
.staff-patients-page select,
.staff-patients-page button,
.staff-patients-page textarea {
    font-family: var(--font-ui);
}

.patients-shell {
    max-width: 1180px;
    margin: 0 auto;
}

.patient-header {
    margin-bottom: 18px;
}

.patient-title {
    margin: 0;
    font-size: 24px;
    font-weight: 800;
    color: #111827;
    letter-spacing: -0.02em;
}

.patient-subtitle {
    margin: 6px 0 0;
    color: #6b7280;
    font-size: 13px;
}

.patient-flash-wrap {
    display: grid;
    gap: 8px;
    margin-bottom: 14px;
}

.patient-flash {
    padding: 11px 13px;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    border-radius: 8px;
}

.patient-flash.success {
    color: #15803d;
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.patient-flash.error {
    color: #b91c1c;
    background: #fef2f2;
    border-color: #fecaca;
}

.filter-card,
.patient-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 2px;
    box-shadow: 0 8px 26px rgba(15, 23, 42, 0.04);
}

.filter-card {
    padding: 18px;
    margin-bottom: 10px;
}

.filter-form {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: end;
}

.filter-group label {
    display: block;
    margin-bottom: 7px;
    font-size: 12px;
    font-weight: 700;
    color: #374151;
}

.filter-control {
    width: 100%;
    height: 30px;
    border: 1px solid #d1d5db;
    border-radius: 1px;
    background: #ffffff;
    color: #111827;
    padding: 0 12px;
    font-size: 14px;
    font-family: var(--font-ui);
    box-sizing: border-box;
    outline: none;
}

.filter-control:focus {
    border-color: #9ca3af;
    box-shadow: 0 0 0 3px rgba(156, 163, 175, 0.14);
}

.add-patient-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 15px;
    border: 1px solid #111827;
    border-radius: 8px;
    background: #111827;
    color: #ffffff;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
}

.add-patient-btn:hover {
    background: #374151;
    border-color: #374151;
}

.patient-card {
    padding: 18px;
}

.patient-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.patient-card-title {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: #111827;
    letter-spacing: -0.02em;
}

.patient-table-wrap {
    width: 100%;
    overflow-x: auto;
}

#patientRecordsTable {
    width: 100% !important;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
    background: #ffffff;
    font-family: var(--font-ui);
}

#patientRecordsTable thead th {
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

#patientRecordsTable tbody td {
    border-bottom: 1px solid #eef2f7;
    padding: 12px 10px;
    vertical-align: middle;
    color: #111827;
}

#patientRecordsTable tbody tr {
    cursor: pointer;
    transition: background 0.16s ease;
}

#patientRecordsTable tbody tr:hover {
    background: #f8fafc;
}

.patient-name {
    font-weight: 800;
    color: #111827;
}

.patient-code {
    font-weight: 700;
    color: #374151;
    font-family: var(--font-mono);
    font-size: 12px;
}

.muted-text {
    color: #6b7280;
}

.status-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 24px;
    padding: 0 9px;
    border-radius: 999px;
    border: 1px solid #d1d5db;
    font-size: 11px;
    font-weight: 800;
    text-transform: capitalize;
    background: #ffffff;
    white-space: nowrap;
}

.status-label.active {
    color: #166534;
    border-color: #bbf7d0;
    background: #f0fdf4;
}

.status-label.inactive {
    color: #92400e;
    border-color: #fde68a;
    background: #fffbeb;
}

.status-label.archived {
    color: #b91c1c;
    border-color: #fecaca;
    background: #fef2f2;
}

.added-time {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 0 9px;
    border-radius: 999px;
    color: #848c9b;
    font-size: 11px;
    font-weight: 500;
    white-space: nowrap;
}

.dataTables_wrapper {
    width: 100%;
    font-family: var(--font-ui);
}

.patient-table-tools {
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
    border: 1px solid #d1d5db !important;
    background: #ffffff !important;
    color: #374151 !important;
    padding: 6px 12px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    box-shadow: none !important;
    margin: 0 !important;
    border-radius: 7px !important;
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
    font-family: var(--font-ui) !important;
}

.dataTables_paginate .paginate_button.current {
    background: #b1b1b1 !important;
    color: #ffffff !important;
    border-color: #111827 !important;
}

.dataTables_paginate .paginate_button.disabled {
    opacity: 0.5;
}

@media (max-width: 800px) {
    .staff-patients-page {
        padding: 18px 10px 32px;
    }

    .filter-form {
        grid-template-columns: 1fr;
    }

    .add-patient-btn {
        width: 100%;
    }

    .patient-card-head {
        display: block;
    }

    .patient-table-tools,
    .dt-buttons {
        justify-content: flex-start;
    }
}
</style>

<div class="staff-patients-page">
    <div class="patients-shell">

        <?php if ($flash_success || $flash_error): ?>
            <div class="patient-flash-wrap">
                <?php if ($flash_success): ?>
                    <div class="patient-flash success">
                        <?= e((string) $flash_success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($flash_error): ?>
                    <div class="patient-flash error">
                        <?= e((string) $flash_error) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section class="filter-card">
            <form id="patientFilterForm" method="GET" action="/DentalClinic/public/staff/patients" class="filter-form">
                <div class="filter-group">
                    <label for="keyword">Search Patient</label>
                    <input
                        id="keyword"
                        class="filter-control auto-filter"
                        type="search"
                        name="keyword"
                        placeholder="Search name, contact number, email, or patient code"
                        value="<?= e((string) $keyword) ?>"
                        autocomplete="off"
                    >
                </div>

                <a href="/DentalClinic/public/staff/patients/create" class="add-patient-btn">
                    Add Patient
                </a>
            </form>
        </section>

        <section class="patient-card">
            <div class="patient-card-head">
                <h2 class="patient-card-title">Records List</h2>
            </div>

            <div class="patient-table-wrap">
                <table id="patientRecordsTable" class="display nowrap">
                    <thead>
                        <tr>
                            <th>Patient Code</th>
                            <th>Fullname</th>
                            <th>Age</th>
                            <th>Phone No.</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Visits</th>
                            <th>Added</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                            <?php
                                $patientId = (int) ($patient['patient_id'] ?? 0);
                                $patientCode = (string) ($patient['patient_code'] ?? ('PAT-' . $patientId));
                                $name = staffPatientFullName($patient);
                                $age = staffPatientAgeValue($patient);
                                $contact = trim((string) ($patient['contact_number'] ?? ''));
                                $email = trim((string) ($patient['email'] ?? ''));
                                $profileStatus = (string) ($patient['profile_status'] ?? 'active');
                                $appointmentCount = (int) ($patient['appointment_count'] ?? $patient['total_appointments'] ?? 0);
                                $recordUrl = '/DentalClinic/public/staff/patients/show?id=' . $patientId;
                                $addedAgo = staffPatientAddedAgo($patient);
                                $addedTitle = staffPatientAddedTitle($patient);
                                $createdSortValue = staffPatientCreatedSortValue($patient);
                            ?>

                            <tr data-href="<?= e($recordUrl) ?>" tabindex="0">
                                <td>
                                    <span class="patient-code">
                                        <?= e($patientCode) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="patient-name">
                                        <?= e($name) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e($age !== '—' ? $age . ' yrs' : '—') ?>
                                </td>

                                <td>
                                    <?= e($contact !== '' ? $contact : 'No contact') ?>
                                </td>

                                <td>
                                    <span class="muted-text">
                                        <?= e($email !== '' ? $email : 'No email') ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status-label <?= e(staffPatientStatusClass($profileStatus)) ?>">
                                        <?= e(staffPatientStatusText($profileStatus)) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e((string) $appointmentCount) ?> visit(s)
                                </td>

                                <td data-order="<?= e($createdSortValue) ?>">
                                    <span class="added-time" title="<?= e($addedTitle) ?>">
                                        <?= e($addedAgo) ?>
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
    const filterForm = document.getElementById('patientFilterForm');
    const keywordInput = document.getElementById('keyword');

    let searchTimer = null;

    if (keywordInput && filterForm) {
        keywordInput.addEventListener('input', function () {
            clearTimeout(searchTimer);

            searchTimer = setTimeout(function () {
                filterForm.submit();
            }, 500);
        });

        keywordInput.addEventListener('change', function () {
            filterForm.submit();
        });
    }

    if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
        return;
    }

    const table = jQuery('#patientRecordsTable');

    table.DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        ordering: true,
        searching: false,
        autoWidth: false,
        scrollX: true,
        order: [],
        dom: '<"patient-table-tools"B>lirtp',
        buttons: [
            {
                extend: 'copy',
                text: 'Copy',
                title: 'Patient Records'
            },
            {
                extend: 'csv',
                text: 'CSV',
                title: 'Patient Records'
            },
            {
                extend: 'excel',
                text: 'Excel',
                title: 'Patient Records'
            },
            {
                extend: 'pdf',
                text: 'PDF',
                title: 'Patient Records'
            },
            {
                extend: 'print',
                text: 'Print',
                title: 'Patient Records'
            }
        ],
        language: {
            emptyTable: 'No patient records found',
            zeroRecords: 'No matching patient records found'
        }
    });

    table.on('click', 'tbody tr[data-href]', function (event) {
        if (event.target.closest('a, button, input, select, textarea')) {
            return;
        }

        const href = this.getAttribute('data-href');

        if (href) {
            window.location.href = href;
        }
    });

    table.on('keydown', 'tbody tr[data-href]', function (event) {
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
$pageTitle = 'Patient Records';
require __DIR__ . '/../layouts/app.php';
?>