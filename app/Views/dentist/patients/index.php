<?php
$pageTitle = 'Patient Records';

$patients = isset($patients) && is_array($patients) ? $patients : [];
$search = $search ?? '';
$status = $status ?? '';
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dentistPatientFullName')) {
    function dentistPatientFullName(array $patient): string
    {
        $name = trim(
            (string) ($patient['first_name'] ?? '') . ' ' .
            (string) ($patient['middle_name'] ?? '') . ' ' .
            (string) ($patient['last_name'] ?? '')
        );

        return $name !== '' ? $name : 'Unnamed Patient';
    }
}

if (!function_exists('dentistPatientAgeValue')) {
    function dentistPatientAgeValue(array $patient): string
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

if (!function_exists('dentistPatientCreatedAtValue')) {
    function dentistPatientCreatedAtValue(array $patient): string
    {
        $createdAt = trim((string) ($patient['created_at'] ?? ''));

        if ($createdAt === '') {
            return '';
        }

        $timestamp = strtotime($createdAt);

        return $timestamp !== false ? date('c', $timestamp) : '';
    }
}

if (!function_exists('dentistPatientRelativeAddedText')) {
    function dentistPatientRelativeAddedText(array $patient): string
    {
        $createdAt = trim((string) ($patient['created_at'] ?? ''));

        if ($createdAt === '') {
            return 'Added date unavailable';
        }

        $timestamp = strtotime($createdAt);

        if ($timestamp === false) {
            return 'Added date unavailable';
        }

        $seconds = max(0, time() - $timestamp);

        if ($seconds < 5) {
            return 'Added just now';
        }

        $units = [
            'yr' => 31536000,
            'mo' => 2592000,
            'day' => 86400,
            'hr' => 3600,
            'min' => 60,
            'sec' => 1,
        ];

        foreach ($units as $label => $value) {
            if ($seconds >= $value) {
                $count = (int) floor($seconds / $value);
                return 'Added ' . $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
            }
        }

        return 'Added just now';
    }
}



}

if (!function_exists('dentistPatientStatusText')) {
    function dentistPatientStatusText(string $status): string
    {
        $status = trim($status);

        return $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Active';
    }
}

if (!function_exists('dentistPatientStatusClass')) {
    function dentistPatientStatusClass(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'inactive' => 'inactive',
            'archived' => 'archived',
            default => 'active',
        };
    }
}

ob_start();
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

<style>
.added-time {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 0 9px;
    
    background:transparent;
    color: #949494;
    font-size: 11px;
    font-weight: 300;
    white-space: nowrap;
}

.added-time.unavailable {
    border-color: #e5e7eb;
    background: #f9fafb;
    color: #6b7280;
}

.dentist-patients-page {
    min-height: calc(100dvh - 74px);
    background: #f5f6f800;
    color: #111827;
    padding: 2px 16px 40px;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

.patient-shell {
    max-width: 1180px;
    margin: 0 auto;
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
    border-radius: 5px;
    box-shadow: 0 8px 26px rgba(15, 23, 42, 0.04);
}

.filter-card {
    padding: 18px;
    margin-bottom: 10px;
}

.filter-form {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-left {
    display: flex;
    align-items: end;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-group {
    max-width: 100%;
}

.filter-group.search-group {
    width: 380px;
}

.filter-group.status-group {
    width: 180px;
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
    height: 36px;
    border: 1px solid #d1d5db;
    border-radius: 1px;
    background: #ffffff;
    color: #111827;
    padding: 0 12px;
    font-size: 14px;
    box-sizing: border-box;
    outline: none;
}

.filter-control:focus {
    border-color: #9ca3af;
    box-shadow: 0 0 0 3px rgba(156, 163, 175, 0.14);
}

.filter-reset-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 0 13px;
    border: 1px solid #111827;
    background: #ffffff;
    color: #111827;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    box-sizing: border-box;
}

.filter-reset-btn:hover {
    background: #f3f4f6;
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

.patient-title {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: #111827;
}

.patient-table-wrap {
    width: 100%;
    overflow-x: auto;
}

#dentistPatientRecordsTable {
    width: 100% !important;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
    background: #ffffff;
}

#dentistPatientRecordsTable thead th {
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

#dentistPatientRecordsTable tbody td {
    border-bottom: 1px solid #eef2f7;
    padding: 12px 10px;
    vertical-align: middle;
    color: #111827;
}

#dentistPatientRecordsTable tbody tr.clickable-patient-row {
    cursor: pointer;
    transition: background 0.16s ease;
}

#dentistPatientRecordsTable tbody tr.clickable-patient-row:hover {
    background: #f8fafc;
}

#dentistPatientRecordsTable tbody tr.clickable-patient-row:focus {
    outline: 2px solid #111827;
    outline-offset: -2px;
}

.column-filter-row th {
    background: #f9fafb !important;
    padding: 6px 10px !important;
}

.column-filter {
    width: 100%;
    height: 28px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    padding: 4px 6px;
    font-size: 12px;
    box-sizing: border-box;
    outline: none;
}

.column-filter:focus {
    border-color: #111827;
}

.patient-name {
    font-weight: 800;
    color: #111827;
}

.patient-code {
    font-weight: 700;
    color: #374151;
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
    background: #dcfce7;
}

.status-label.inactive {
    color: #92400e;
    border-color: #fde68a;
    background: #fef3c7;
}

.status-label.archived {
    color: #b91c1c;
    border-color: #fecaca;
    background: #fee2e2;
}

.dataTables_wrapper {
    width: 100%;
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
    border: 1px solid #4d9674 !important;
    background: #ffffff !important;
    color: #175447 !important;
    padding: 6px 12px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    box-shadow: none !important;
    margin: 0 !important;
    border-radius: 0 !important;
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
}

.dataTables_length select {
    border: 1px solid #d1d5db;
    border-radius: 7px;
    padding: 5px 7px;
    background: #ffffff;
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

@media (max-width: 800px) {
    .dentist-patients-page {
        padding: 18px 10px 32px;
    }

    .filter-form,
    .filter-left {
        display: grid;
        grid-template-columns: 1fr;
        width: 100%;
    }

    .filter-group.search-group,
    .filter-group.status-group {
        width: 100%;
    }

    .filter-reset-btn {
        width: 100%;
    }

    .patient-table-tools,
    .dt-buttons {
        justify-content: flex-start;
    }
}
</style>

<div class="dentist-patients-page">
    <div class="patient-shell">
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
            <form id="patientFilterForm" method="GET" action="/DentalClinic/public/dentist/patients" class="filter-form">
                <div class="filter-left">
                    <div class="filter-group search-group">
                        <label for="search">Search Patient</label>
                        <input
                            id="search"
                            class="filter-control auto-filter"
                            type="search"
                            name="search"
                            placeholder="Search name, contact, email, or patient code"
                            value="<?= e((string) $search) ?>"
                        >
                    </div>

                    <div class="filter-group status-group">
                        <label for="status">Status</label>
                        <select id="status" class="filter-control auto-filter" name="status">
                            <option value="">All Statuses</option>
                            <option value="active" <?= (string) $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= (string) $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="archived" <?= (string) $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                </div>

                <a href="/DentalClinic/public/dentist/patients" class="filter-reset-btn">
                    Reset
                </a>
            </form>
        </section>

        <section class="patient-card">
            <div class="patient-card-head">
                <h1 class="patient-title">Patient Records</h1>
            </div>

            <div class="patient-table-wrap">
                <table id="dentistPatientRecordsTable" class="display nowrap">
                    <thead>
                        <tr>
                            <th>Patient Code</th>
                            <th>Fullname</th>
                            <th>Age</th>
                            <th>Phone No.</th>
                            <th>Email</th>
                            <th>Status</th>

<th>Visits</th>
                        </tr>

                        <tr class="column-filter-row">
                            <th><input class="column-filter" type="text" placeholder="Patient Code"></th>
                            <th><input class="column-filter" type="text" placeholder="Fullname"></th>
                            <th><input class="column-filter" type="text" placeholder="Age"></th>
                            <th><input class="column-filter" type="text" placeholder="Phone No."></th>
                            <th><input class="column-filter" type="text" placeholder="Email"></th>
                            <th><input class="column-filter" type="text" placeholder="Status"></th>
                            
                            <th><input class="column-filter" type="text" placeholder="Visits"></th>
                            <th><input class="column-filter" type="text" placeholder="Added"></th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                            <?php
                                $patientId = (int) ($patient['patient_id'] ?? 0);
                                $patientCode = (string) ($patient['patient_code'] ?? ('PAT-' . $patientId));
                                $name = dentistPatientFullName($patient);
                                $age = dentistPatientAgeValue($patient);
                                $contact = trim((string) ($patient['contact_number'] ?? ''));
                                $email = trim((string) ($patient['email'] ?? ''));
                                $profileStatus = (string) ($patient['profile_status'] ?? 'active');
                                $appointmentCount = (int) ($patient['appointment_count'] ?? $patient['total_appointments'] ?? 0);
                                $createdAtValue = dentistPatientCreatedAtValue($patient);
$createdAtText = dentistPatientRelativeAddedText($patient);
                                $recordUrl = '/DentalClinic/public/dentist/patients/show?id=' . $patientId;
                                $isClickable = $patientId > 0;
                            ?>

                            <tr
                                class="<?= $isClickable ? 'clickable-patient-row' : '' ?>"
                                <?= $isClickable ? 'data-href="' . e($recordUrl) . '" tabindex="0"' : '' ?>
                            >
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
                                    <?= e($email !== '' ? $email : 'No email') ?>
                                </td>

                               <td>
    <span class="status-label <?= e(dentistPatientStatusClass($profileStatus)) ?>">
        <?= e(dentistPatientStatusText($profileStatus)) ?>
    </span>
</td>



<td>
    <?= e((string) $appointmentCount) ?> visit(s)
</td>

<td>
    <span
        class="added-time <?= $createdAtValue !== '' ? '' : 'unavailable' ?>"
        data-created-at="<?= e($createdAtValue) ?>"
    >
        <?= e($createdAtText) ?>
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

function formatAddedTime(createdAt) {
    if (!createdAt) {
        return 'Added date unavailable';
    }

    const createdDate = new Date(createdAt);

    if (Number.isNaN(createdDate.getTime())) {
        return 'Added date unavailable';
    }

    const now = new Date();
    let seconds = Math.floor((now.getTime() - createdDate.getTime()) / 1000);

    if (seconds < 0) {
        seconds = 0;
    }

    if (seconds < 5) {
        return 'Added just now';
    }

    const units = [
        { label: 'yr', seconds: 31536000 },
        { label: 'mo', seconds: 2592000 },
        { label: 'day', seconds: 86400 },
        { label: 'hr', seconds: 3600 },
        { label: 'min', seconds: 60 },
        { label: 'sec', seconds: 1 }
    ];

    for (const unit of units) {
        if (seconds >= unit.seconds) {
            const count = Math.floor(seconds / unit.seconds);
            return 'Added ' + count + ' ' + unit.label + (count > 1 ? 's' : '') + ' ago';
        }
    }

    return 'Just now';
}

function updateAddedTimes() {
    document.querySelectorAll('.added-time[data-created-at]').forEach(function (item) {
        const createdAt = item.getAttribute('data-created-at');

        if (!createdAt) {
            item.textContent = 'Added date unavailable';
            item.classList.add('unavailable');
            return;
        }

        item.textContent = formatAddedTime(createdAt);
    });
}

updateAddedTimes();
window.setInterval(updateAddedTimes, 1000);


    const filterForm = document.getElementById('patientFilterForm');
    const filterControls = document.querySelectorAll('.auto-filter');

    let searchTimer = null;

    filterControls.forEach(function (control) {
        control.addEventListener('change', function () {
            if (filterForm) {
                filterForm.submit();
            }
        });

        if (control.type === 'search') {
            control.addEventListener('input', function () {
                clearTimeout(searchTimer);

                searchTimer = setTimeout(function () {
                    if (filterForm) {
                        filterForm.submit();
                    }
                }, 500);
            });
        }
    });

    if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
        return;
    }

    const table = jQuery('#dentistPatientRecordsTable');

    const dataTable = table.DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        ordering: true,
        searching: true,
        autoWidth: false,
        scrollX: true,
        orderCellsTop: true,
        order: [],
        dom: '<"patient-table-tools"B>lirtp',
        buttons: [
            {
                extend: 'copy',
                text: 'Copy',
                title: 'Dentist Patient Records',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7]
                }
            },
            {
                extend: 'csv',
                text: 'CSV',
                title: 'Dentist Patient Records',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7]
                }
            },
            {
                extend: 'excel',
                text: 'Excel',
                title: 'Dentist Patient Records',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7]
                }
            },
            {
                extend: 'pdf',
                text: 'PDF',
                title: 'Dentist Patient Records',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6]
                }
            },
            {
                extend: 'print',
                text: 'Print',
                title: 'Dentist Patient Records',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6]
                }
            }
        ],
        language: {
            emptyTable: 'No patient records found',
            zeroRecords: 'No matching patient records found'
        }
    });

    table.find('thead tr.column-filter-row th').each(function (index) {
        const input = jQuery(this).find('input');

        input.on('keyup change clear', function () {
            if (dataTable.column(index).search() !== this.value) {
                dataTable.column(index).search(this.value).draw();
            }
        });
    });

    table.on('click', 'tbody tr.clickable-patient-row', function (event) {
        if (event.target.closest('a, button, input, select, textarea')) {
            return;
        }

        const href = this.getAttribute('data-href');

        if (href) {
            window.location.href = href;
        }
    });

    table.on('keydown', 'tbody tr.clickable-patient-row', function (event) {
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
$content = ob_get_clean();
$pageTitle = 'Patient Records';
require __DIR__ . '/../layouts/app.php';
?>