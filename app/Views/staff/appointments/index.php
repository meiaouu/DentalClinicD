<?php
use App\Core\Csrf;

$appointments = $appointments ?? [];
$date = $date ?? date('Y-m-d');
$status = $status ?? '';
$page = $page ?? 1;
$perPage = $perPage ?? 20;
$total = $total ?? 0;
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

$waitingQueue = $waitingQueue ?? [];
$inProgress = $inProgress ?? [];
$completed = $completed ?? [];
$nextPatient = $nextPatient ?? null;

$totalPages = max(1, (int) ceil($total / $perPage));
$baseUrl = '/DentalClinic/public';

$statuses = [
    '' => 'All',
    'rescheduled' => 'Rescheduled',
    'completed' => 'Completed',
    'no_show' => 'No Show',
    'cancelled' => 'Cancelled',
];

$statusCounts = array_merge([
    '' => 0,
    'rescheduled' => 0,
    'completed' => 0,
    'no_show' => 0,
    'cancelled' => 0,
], isset($statusCounts) && is_array($statusCounts) ? $statusCounts : []);

$currentUrl = $baseUrl . '/staff/appointments?date=' . urlencode($date) . ($status !== '' ? '&status=' . urlencode($status) : '') . '&page=' . $page;
$hasFlash = !empty($flash_success) || !empty($flash_error);

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('niceDate')) {
    function niceDate(?string $date): string
    {
        if (!$date) {
            return '—';
        }

        $time = strtotime($date);
        return $time ? date('M d, Y', $time) : $date;
    }
}

if (!function_exists('niceTime')) {
    function niceTime(?string $time): string
    {
        if (!$time) {
            return '';
        }

        $parsed = strtotime($time);
        return $parsed ? date('h:i A', $parsed) : $time;
    }
}

if (!function_exists('completedTime')) {
    function completedTime(array $appointment): string
    {
        $status = strtolower(trim((string) ($appointment['status'] ?? '')));

        if ($status !== 'completed') {
            return '';
        }

        $completedAt = trim((string) (
            $appointment['actual_completed_at']
            ?? $appointment['completed_at']
            ?? ''
        ));

        if ($completedAt === '' || strtotime($completedAt) === false) {
            return 'Not recorded';
        }

        return date('h:i A', strtotime($completedAt));
    }
}

if (!function_exists('isAppointmentCheckedIn')) {
    function isAppointmentCheckedIn(array $appointment): bool
    {
        $status = strtolower(trim((string) ($appointment['status'] ?? '')));
        $arrivalStatus = strtolower(trim((string) ($appointment['arrival_status'] ?? '')));
        $checkedInAt = trim((string) ($appointment['checked_in_at'] ?? ''));

        return (
            $arrivalStatus === 'checked_in'
            || $checkedInAt !== ''
            || in_array($status, ['checked_in', 'in_progress', 'completed'], true)
        );
    }
}

if (!function_exists('checkedInTime')) {
    function checkedInTime(array $appointment): string
    {
        $checkedInAt = trim((string) ($appointment['checked_in_at'] ?? ''));

        if ($checkedInAt === '' || strtotime($checkedInAt) === false) {
            return '';
        }

        return date('h:i A', strtotime($checkedInAt));
    }
}

if (!function_exists('actualStartedTime')) {
    function actualStartedTime(array $appointment): string
    {
        $startedAt = trim((string) ($appointment['actual_started_at'] ?? ''));

        if ($startedAt === '' || strtotime($startedAt) === false) {
            return '';
        }

        return date('h:i A', strtotime($startedAt));
    }
}

if (!function_exists('fullName')) {
    function fullName(array $row, string $prefix = 'patient'): string
    {
        $name = trim((string) (
            ($row[$prefix . '_first_name'] ?? '') . ' ' .
            ($row[$prefix . '_last_name'] ?? '')
        ));

        return $name !== '' ? $name : 'Unknown Patient';
    }
}

ob_start();
?>


<style>


.appointments-page,
.appointments-page * {
    box-sizing: border-box;
}

.appointments-page {
    min-height: calc(100dvh - 74px);
    background: #ffffff;
    color: #222222;
    font-family: var(--font-ui);
    padding: 24px 16px 40px;
}

.appointments-page input,
.appointments-page select,
.appointments-page textarea,
.appointments-page button,
.appointments-page a {
    font-family: var(--font-ui);
}

.appointments-shell {
    max-width: 1180px;
    margin: 0 auto;
}

.appointments-header {
    margin-bottom: 18px;
}

.appointments-title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #222222;
    letter-spacing: -0.02em;
}

.appointments-subtitle {
    margin: 5px 0 0;
    font-size: 13px;
    color: #666666;
}

.appointments-toolbar {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.toolbar-actions {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.queue-toggle-btn {
    width: 38px;
    height: 38px;
    border: 1px solid #dddddd;
    border-radius: 2px;
    background: #ffffff;
    color: #222222;
    font-size: 18px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.queue-toggle-btn:hover {
    background: #111827;
    color: #ffffff;
    border-color: #111827;
}

.queue-toggle-btn span {
    line-height: 1;
}

.appointments-grid {
    transition: grid-template-columns 0.28s ease;
}

.appointments-grid.queue-is-hidden {
    grid-template-columns: minmax(0, 1fr) 0;
}

.queue-panel {
    overflow: hidden;
    opacity: 1;
    transform: translateX(0);
    transition:
        opacity 0.25s ease,
        transform 0.25s ease,
        border-color 0.25s ease;
}

.appointments-grid.queue-is-hidden .queue-panel {
    opacity: 0;
    transform: translateX(18px);
    pointer-events: none;
    border-color: transparent;
}

.toolbar-left {
    display: flex;
    align-items: end;
    gap: 10px;
    flex-wrap: wrap;
}

.form-group {
    display: grid;
    gap: 5px;
}

.form-group label {
    font-size: 11px;
    font-weight: 600;
    color: #555555;
}

.form-control,
.form-textarea {
    width: 100%;
    border: 1px solid #dddddd;
    border-radius: 2px;
    background: #ffffff;
    color: #222222;
    font-family: var(--font-ui);
    box-sizing: border-box;
    outline: none;
}

.form-control {
    height: 38px;
    padding: 0 10px;
    font-size: 13px;
}

.form-textarea {
    min-height: 70px;
    padding: 9px 10px;
    font-size: 13px;
    resize: vertical;
}

.form-control:focus,
.form-textarea:focus {
    border-color: #9ca3af;
}

.date-input {
    min-width: 190px;
}

.btn-secondary,
.btn-complete,
.btn-no-show,
.btn-cancel,
.page-link,
.queue-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 14px;
    border: 1px solid #dddddd;
    border-radius: 2px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    box-sizing: border-box;
}

.btn-secondary {
    background: #ffffff;
    color: #222222;
}

.btn-secondary:hover {
    background: #f5f5f5;
}

.btn-complete {
    border-color: #15803d;
    background: #15803d;
    color: #ffffff;
}

.btn-no-show {
    border-color: #f59e0b;
    background: #f59e0b;
    color: #ffffff;
}

.btn-cancel {
    border-color: #dc2626;
    background: #dc2626;
    color: #ffffff;
}

.appointments-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 16px;
    align-items: start;
}

.main-panel,
.queue-panel {
    border: 1px solid #dddddd;
    background: #ffffff;
}

.status-tabs {
    display: flex;
    gap: 18px;
    overflow-x: auto;
    border-bottom: 1px solid #eeeeee;
    padding: 0 14px;
}

.status-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 44px;
    color: #666666;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    border-bottom: 2px solid transparent;
}

.status-tab.active {
    color: #111827;
    border-bottom-color: #111827;
}

.status-count {
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: #f1f1f1;
    color: #333333;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
}

.panel-header {
    padding: 14px;
    border-bottom: 1px solid #eeeeee;
}

.panel-title {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #222222;
    letter-spacing: -0.01em;
}

.panel-body {
    padding: 14px;
}

.flash-wrap {
    display: grid;
    gap: 8px;
    margin-bottom: 12px;
}

.flash-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #dddddd;
    font-size: 13px;
    font-weight: 700;
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

.flash-close {
    border: none;
    background: transparent;
    color: inherit;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
}

.empty-state {
    border: 1px dashed #cccccc;
    padding: 18px;
    color: #666666;
    font-size: 13px;
    text-align: center;
    background: #ffffff;
}

.appointment-table {
    border: 1px solid #eeeeee;
    background: #ffffff;
}

.appointment-table-header,
.appointment-summary {
    display: grid;
    grid-template-columns:
        minmax(170px, 1fr)
        minmax(240px, 1.4fr)
        minmax(140px, 0.8fr)
        100px
        34px;
    gap: 12px;
    align-items: center;
}

.appointment-table-header {
    min-height: 38px;
    padding: 0 12px;
    background: #fafafa;
    border-bottom: 1px solid #eeeeee;
    color: #555555;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.appointment-card {
    border-bottom: 1px solid #eeeeee;
    background: #ffffff;
}

.appointment-card:last-child {
    border-bottom: none;
}

.appointment-summary {
    position: relative;
}

.appointment-summary.is-in-progress {
    background: #fffdf5;
    border-radius: 12px;
    overflow: hidden;
}

.appointment-summary.is-in-progress::after {
    content: "";
    position: absolute;
    inset: 0px;
    border-radius: 1px;
    pointer-events: none;
    z-index: 2;
    background-image:
        linear-gradient(90deg, #111827 50%, transparent 50%),
        linear-gradient(90deg, #111827 50%, transparent 50%),
        linear-gradient(0deg, #111827 50%, transparent 50%),
        linear-gradient(0deg, #111827 50%, transparent 50%);
    background-size:
        18px 2px,
        18px 2px,
        2px 18px,
        2px 18px;
    background-repeat:
        repeat-x,
        repeat-x,
        repeat-y,
        repeat-y;
    background-position:
        0 0,
        0 100%,
        0 0,
        100% 0;
    animation: inProgressBorderMove 10s linear infinite;
}

@keyframes inProgressBorderMove {
    from {
        background-position:
            0 0,
            0 100%,
            0 0,
            100% 0;
    }

    to {
        background-position:
            36px 0,
            -36px 100%,
            0 -36px,
            100% 36px;
    }
}

.appointment-summary {
    min-height: 64px;
    padding: 10px 12px;
    cursor: pointer;
}

.appointment-summary:hover {
    background: #fafafa;
}

.appointment-main-code {
    font-size: 13px;
    font-weight: 700;
    color: #222222;
    margin-bottom: 3px;
    font-family: var(--font-mono);
}

.appointment-main-name,
.appointment-detail-line,
.appointment-time-box {
    font-size: 12px;
    color: #555555;
    line-height: 1.4;
}

.appointment-list-details {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.appointment-detail-line {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
}

.appointment-detail-line strong,
.appointment-time-box strong {
    display: block;
    color: #222222;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 2px;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 24px;
    padding: 0 9px;
    border: 1px solid #dddddd;
    border-radius: 1px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
    white-space: nowrap;
}

.status-pill.confirmed {
    color: #166534;
    border-color: #bbf7d0;
    background: #67bf81;
}

.status-pill.checked_in {
    color: #0369a1;
    border-color: #bae6fd;
    background: #82cdff;
}

.status-pill.in_progress {
    color: #3c085f;
    border-color: #cc9aff;
    background: #e4c4ff;
}

.status-pill.rescheduled,
.status-pill.pending {
    color: #92400e;
    border-color: #fde68a;
    background: #ffeb9a;
}

.status-pill.completed {
    color: #1d4ed8;
    border-color: #bfdbfe;
    background: #91c1ff;
}

.status-pill.no_show,
.status-pill.cancelled {
    color: #b91c1c;
    border-color: #fecaca;
    background: #ff9f9f;
}

.appointment-toggle {
    width: 28px;
    height: 28px;
    border: 1px solid #dddddd;
    border-radius: 999px;
    background: #ffffff;
    color: #222222;
    cursor: pointer;
    font-size: 14px;
    font-weight: 700;
}

.appointment-card.is-open .appointment-toggle {
    transform: rotate(180deg);
}

.appointment-details {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.24s ease;
}

.appointment-details-inner {
    padding: 12px;
    border-top: 1px solid #eeeeee;
    background: #fafafa;
}

.action-strip {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: end;
}

.remarks-box {
    display: grid;
    gap: 5px;
}

.remarks-label {
    font-size: 11px;
    font-weight: 700;
    color: #555555;
}

.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.pagination {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.page-link {
    min-width: 36px;
    background: #ffffff;
    color: #222222;
}

.page-link.active {
    border-color: #111827;
    background: #111827;
    color: #ffffff;
}

/* Queue */
.queue-panel {
    position: sticky;
    top: 12px;
}

.queue-header {
    padding: 14px;
    border-bottom: 1px solid #eeeeee;
}

.queue-title {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: -0.01em;
}

.queue-subtitle {
    margin-top: 4px;
    font-size: 12px;
    color: #666666;
}

.queue-body {
    padding: 14px;
    display: grid;
    gap: 12px;
}

.queue-box {
    border: 1px solid #eeeeee;
    padding: 12px;
    background: #fafafa;
}

.queue-box.dark {
    background: #111827;
    color: #ffffff;
    border-color: #111827;
}

.queue-label {
    margin-bottom: 8px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #666666;
}

.queue-box.dark .queue-label {
    color: #d1d5db;
}

.queue-name {
    font-size: 18px;
    font-weight: 700;
    color: inherit;
    letter-spacing: -0.02em;
}

.queue-meta {
    margin-top: 6px;
    font-size: 12px;
    line-height: 1.5;
    color: #666666;
}

.queue-box.dark .queue-meta {
    color: #d1d5db;
}

.queue-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    border: 1px solid #eeeeee;
    padding: 10px;
}

.queue-row-name {
    font-size: 13px;
    font-weight: 700;
}

.queue-row-time {
    margin-top: 3px;
    font-size: 12px;
    color: #666666;
}

.queue-btn.start {
    border-color: #111827;
    background: #111827;
    color: #ffffff;
}

.queue-btn.complete {
    width: 100%;
    border-color: #15803d;
    background: #15803d;
    color: #ffffff;
}

.completed-time-text,
.checked-in-time-text {
    margin-top: 5px;
    color: #666666;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.3;
}

.completed-time-text {
    color: #1d4ed8;
    font-size: 9px;
}

.checked-in-time-text {
    color: #64748b;
}

.checked-in-time-text.is-checked {
    color: #15803d;
}

.checked-in-time-text.not-checked {
    color: #64748b;
}

@media (max-width: 1100px) {
    .appointments-grid {
        grid-template-columns: 1fr;
    }

    .queue-panel {
        position: static;
    }
}

@media (max-width: 900px) {
    .appointment-table {
        overflow-x: auto;
    }

    .appointment-table-header,
    .appointment-summary {
        min-width: 900px;
    }
}

@media (max-width: 700px) {
    .appointments-page {
        padding: 16px 10px 32px;
    }

    .appointments-toolbar,
    .toolbar-left {
        display: grid;
        grid-template-columns: 1fr;
        width: 100%;
    }

    .date-input,
    .form-group,
    .btn-secondary {
        width: 100%;
    }

    .action-strip {
        grid-template-columns: 1fr;
    }

    .action-buttons {
        justify-content: flex-start;
    }
}
</style>

<div class="appointments-page">
    <div class="appointments-shell">
        <div class="appointments-toolbar">
            <div class="toolbar-left">
                <form method="GET" action="<?= e($baseUrl . '/staff/appointments') ?>" id="appointmentFilterForm">
                    <input type="hidden" name="status" value="<?= e($status) ?>">

                    <div class="form-group">
                        <label for="date">Date</label>
                        <input
                            class="form-control date-input"
                            id="date"
                            type="date"
                            name="date"
                            value="<?= e($date) ?>"
                            data-auto-submit="1"
                        >
                    </div>
                </form>
            </div>

            <div class="toolbar-actions">
                <a class="btn-secondary" href="<?= e($baseUrl . '/staff/appointments?date=' . urlencode(date('Y-m-d')) . ($status !== '' ? '&status=' . urlencode($status) : '')) ?>">
                    Today
                </a>

                <button
                    type="button"
                    class="queue-toggle-btn"
                    id="queueToggleBtn"
                    aria-label="Show clinic queue"
                    aria-expanded="false"
                    title="Show clinic queue"
                >
                    <span id="queueToggleIcon">☰</span>
                </button>
            </div>
        </div>

        <div class="appointments-grid queue-is-hidden" id="appointmentsGrid">
            <main class="main-panel">
                <div class="status-tabs">
                    <?php foreach ($statuses as $statusValue => $statusLabel): ?>
                        <a
                            href="<?= e($baseUrl . '/staff/appointments?date=' . urlencode($date) . ($statusValue !== '' ? '&status=' . urlencode($statusValue) : '')) ?>"
                            class="status-tab <?= $status === $statusValue ? 'active' : '' ?>"
                        >
                            <span><?= e($statusLabel) ?></span>
                            <span class="status-count"><?= (int) ($statusCounts[$statusValue] ?? 0) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="panel-header">
                    <h2 class="panel-title">
                        <?= $status === '' ? 'Appointment Schedule' : e($statuses[$status] ?? 'Appointment') . ' Appointments' ?>
                    </h2>
                </div>

                <div class="panel-body">
                    <?php if ($hasFlash): ?>
                        <div class="flash-wrap">
                            <?php if ($flash_success): ?>
                                <div class="flash-box success" data-flash-box>
                                    <span><?= e((string) $flash_success) ?></span>
                                    <button type="button" class="flash-close" data-flash-close>&times;</button>
                                </div>
                            <?php endif; ?>

                            <?php if ($flash_error): ?>
                                <div class="flash-box error" data-flash-box>
                                    <span><?= e((string) $flash_error) ?></span>
                                    <button type="button" class="flash-close" data-flash-close>&times;</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($appointments)): ?>
                        <div class="empty-state">
                            No appointments found for this selected date<?= $status !== '' ? ' and status' : '' ?>.
                        </div>
                    <?php else: ?>
                        <?php
                            $sortedAppointments = $appointments;

                            usort($sortedAppointments, function ($a, $b) {
                                $finalStatuses = ['completed', 'no_show', 'cancelled'];

                                $groupA = in_array((string) ($a['status'] ?? ''), $finalStatuses, true) ? 1 : 0;
                                $groupB = in_array((string) ($b['status'] ?? ''), $finalStatuses, true) ? 1 : 0;

                                if ($groupA !== $groupB) {
                                    return $groupA <=> $groupB;
                                }

                                $timeCompare = strcmp((string) ($a['start_time'] ?? ''), (string) ($b['start_time'] ?? ''));

                                if ($timeCompare !== 0) {
                                    return $timeCompare;
                                }

                                return ((int) ($a['appointment_id'] ?? 0)) <=> ((int) ($b['appointment_id'] ?? 0));
                            });
                        ?>

                        <div class="appointment-table">
                            <div class="appointment-table-header">
                                <span>Appointment</span>
                                <span>Details</span>
                                <span>Schedule</span>
                                <span>Status</span>
                                <span></span>
                            </div>

                            <?php foreach ($sortedAppointments as $appointment): ?>
                                <?php
                                    $appointmentId = (int) ($appointment['appointment_id'] ?? 0);
                                    $patientName = fullName($appointment, 'patient');
                                    $dentistName = trim((string) (($appointment['dentist_first_name'] ?? '') . ' ' . ($appointment['dentist_last_name'] ?? '')));
                                    $appointmentStatus = strtolower(trim((string) ($appointment['status'] ?? 'confirmed')));

                                    $finalStatuses = ['completed', 'cancelled', 'no_show'];

                                    $canCheckIn = in_array($appointmentStatus, ['confirmed', 'rescheduled'], true);
                                    $canStart = $appointmentStatus === 'checked_in';
                                    $canComplete = $appointmentStatus === 'in_progress';
                                    $canNoShow = in_array($appointmentStatus, ['confirmed', 'rescheduled', 'checked_in'], true);
                                    $canCancel = in_array($appointmentStatus, ['pending', 'confirmed', 'rescheduled'], true);
                                    $showActionButtons = !in_array($appointmentStatus, $finalStatuses, true);
                                ?>

                                <article class="appointment-card" data-appointment-card>
                                    <div class="appointment-summary <?= $appointmentStatus === 'in_progress' ? 'is-in-progress' : '' ?>" data-appointment-toggle>
                                        <div>
                                            <div class="appointment-main-code">
                                                <?= e((string) ($appointment['appointment_code'] ?? '')) ?>
                                            </div>
                                            <div class="appointment-main-name">
                                                <?= e($patientName) ?>
                                            </div>
                                        </div>

                                        <div class="appointment-list-details">
                                            <div class="appointment-detail-line">
                                                <strong>Service</strong>
                                                <?= e((string) ($appointment['service_name'] ?? 'N/A')) ?>
                                            </div>

                                            <div class="appointment-detail-line">
                                                <strong>Dentist</strong>
                                                <?= e($dentistName !== '' ? ('Dr. ' . $dentistName) : 'Unassigned') ?>
                                            </div>

                                            <div class="appointment-detail-line">
                                                <strong>Remarks</strong>
                                                <?= e((string) (($appointment['remarks'] ?? '') !== '' ? $appointment['remarks'] : 'No remarks')) ?>
                                            </div>
                                        </div>

                                        <div class="appointment-time-box">
                                            <strong><?= e(niceDate((string) ($appointment['appointment_date'] ?? ''))) ?></strong>
                                            <?= e(niceTime((string) ($appointment['start_time'] ?? ''))) ?>
                                            -
                                            <?= e(niceTime((string) ($appointment['end_time'] ?? ''))) ?>
                                        </div>

                                        <div>
                                            <span class="status-pill <?= e($appointmentStatus) ?>">
                                                <?= e(str_replace('_', ' ', $appointmentStatus)) ?>
                                            </span>
                                            

                                            <?php if ($appointmentStatus === 'completed'): ?>
                                                <div class="completed-time-text">
                                                    Completed: <?= e(completedTime($appointment)) ?>
                                                </div>

                                                <div class="checked-in-time-text is-checked">
                                                    Checked in<?= checkedInTime($appointment) !== '' ? ' · ' . e(checkedInTime($appointment)) : '' ?>
                                                </div>
                                            <?php elseif (isAppointmentCheckedIn($appointment)): ?>
                                                <div class="checked-in-time-text is-checked">
                                                    Checked in<?= checkedInTime($appointment) !== '' ? ' · ' . e(checkedInTime($appointment)) : '' ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="checked-in-time-text not-checked">
                                                    Not checked in
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div>
                                            <button type="button" class="appointment-toggle" aria-label="Show appointment details">
                                                ▾
                                            </button>
                                        </div>
                                    </div>

                                    <div class="appointment-details">
                                        <div class="appointment-details-inner">
                                            <div class="action-strip">
                                                <div class="remarks-box">
                                                    <label class="remarks-label">Note</label>
                                                    <textarea class="form-textarea action-remarks" placeholder="Optional note"></textarea>
                                                </div>

                                                <div class="action-buttons">
                                                    <?php if ($showActionButtons): ?>
                                                        <?php if ($canCheckIn): ?>
                                                            <form method="POST" action="<?= e($baseUrl . '/staff/appointments/check-in') ?>" class="quick-action-form" data-action-type="check_in">
                                                                <?= Csrf::inputField(); ?>
                                                                <input type="hidden" name="appointment_id" value="<?= $appointmentId ?>">
                                                                <input type="hidden" name="remarks" value="">
                                                                <input type="hidden" name="redirect_to" value="<?= e($currentUrl) ?>">
                                                                <button type="submit" class="btn-secondary">Check-In</button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <?php if ($canStart): ?>
                                                            <form method="POST" action="<?= e($baseUrl . '/staff/appointments/in-progress') ?>" class="quick-action-form" data-action-type="in_progress">
                                                                <?= Csrf::inputField(); ?>
                                                                <input type="hidden" name="appointment_id" value="<?= $appointmentId ?>">
                                                                <input type="hidden" name="remarks" value="">
                                                                <input type="hidden" name="redirect_to" value="<?= e($currentUrl) ?>">
                                                                <button type="submit" class="btn-secondary">In Progress</button>
                                                                <?php if ($appointmentStatus === 'in_progress' && actualStartedTime($appointment) !== ''): ?>
    <div class="checked-in-time-text is-checked">
        Started: <?= e(actualStartedTime($appointment)) ?>
    </div>
<?php endif; ?>
                                                            </form>
                                                        <?php endif; ?>

                                                        <?php if ($canComplete): ?>
                                                            <form method="POST" action="<?= e($baseUrl . '/staff/appointments/complete') ?>" class="quick-action-form" data-action-type="complete">
                                                                <?= Csrf::inputField(); ?>
                                                                <input type="hidden" name="appointment_id" value="<?= $appointmentId ?>">
                                                                <input type="hidden" name="remarks" value="">
                                                                <input type="hidden" name="redirect_to" value="<?= e($currentUrl) ?>">
                                                                <button type="submit" class="btn-complete">Complete Procedure</button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <?php if ($canNoShow): ?>
                                                            <form method="POST" action="<?= e($baseUrl . '/staff/appointments/no-show') ?>" class="quick-action-form" data-action-type="no_show">
                                                                <?= Csrf::inputField(); ?>
                                                                <input type="hidden" name="appointment_id" value="<?= $appointmentId ?>">
                                                                <input type="hidden" name="remarks" value="">
                                                                <input type="hidden" name="redirect_to" value="<?= e($currentUrl) ?>">
                                                                <button type="submit" class="btn-no-show">No Show</button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <?php if ($canCancel): ?>
                                                            <form method="POST" action="<?= e($baseUrl . '/staff/appointments/cancel') ?>" class="quick-action-form" data-action-type="cancel">
                                                                <?= Csrf::inputField(); ?>
                                                                <input type="hidden" name="appointment_id" value="<?= $appointmentId ?>">
                                                                <input type="hidden" name="remarks" value="">
                                                                <input type="hidden" name="redirect_to" value="<?= e($currentUrl) ?>">
                                                                <button type="submit" class="btn-cancel">Cancel</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="appointment-detail-line">No actions available.</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a
                                        class="page-link <?= $i === $page ? 'active' : '' ?>"
                                        href="<?= e($baseUrl . '/staff/appointments?page=' . $i . '&date=' . urlencode($date) . ($status !== '' ? '&status=' . urlencode($status) : '')) ?>"
                                    >
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>

            <aside class="queue-panel">
                <div class="queue-header">
                    <h2 class="queue-title">Clinic Queue</h2>
                    <div class="queue-subtitle">Live patient flow for today</div>
                </div>

                <div class="queue-body">
                    <div class="queue-box">
                        <div class="queue-label">In Progress</div>

                        <?php if (!empty($inProgress)): ?>
                            <?php $activePatient = $inProgress[0]; ?>

                            <div class="queue-name">
                                <?= e(fullName($activePatient, 'patient')) ?>
                            </div>

                            <div class="queue-meta">
                                <?= e(niceTime((string) ($activePatient['start_time'] ?? ''))) ?>

                                <?php if (!empty($activePatient['service_name'])): ?>
                                    · <?= e((string) $activePatient['service_name']) ?>
                                <?php endif; ?>

                                <?php if (!empty($activePatient['dentist_first_name']) || !empty($activePatient['dentist_last_name'])): ?>
                                    <br>
                                    Dr. <?= e(trim((string) (($activePatient['dentist_first_name'] ?? '') . ' ' . ($activePatient['dentist_last_name'] ?? '')))) ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="queue-meta">No active treatment right now.</div>
                        <?php endif; ?>
                    </div>

                    <div class="queue-box dark">
                        <div class="queue-label">Next Patient</div>

                        <?php if ($nextPatient): ?>
                            <div class="queue-name">
                                <?= e(fullName($nextPatient, 'patient')) ?>
                            </div>

                            <div class="queue-meta">
                                <?= e(niceTime((string) ($nextPatient['start_time'] ?? ''))) ?>

                                <?php if (!empty($nextPatient['service_name'])): ?>
                                    · <?= e((string) $nextPatient['service_name']) ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="queue-meta">No next patient in queue.</div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($waitingQueue)): ?>
                        <?php foreach ($waitingQueue as $index => $appt): ?>
                            <div class="queue-row">
                                <div>
                                    <div class="queue-row-name">
                                        #<?= $index + 1 ?> <?= e(fullName($appt, 'patient')) ?>
                                    </div>

                                    <div class="queue-row-time">
                                        <?= e(niceTime((string) ($appt['start_time'] ?? ''))) ?>
                                    </div>
                                </div>

                                <form method="POST" action="<?= e($baseUrl . '/staff/appointments/in-progress') ?>" class="quick-action-form" data-action-type="in_progress">
                                    <?= Csrf::inputField(); ?>
                                    <input type="hidden" name="appointment_id" value="<?= (int) ($appt['appointment_id'] ?? 0) ?>">
                                    <input type="hidden" name="remarks" value="">
                                    <input type="hidden" name="redirect_to" value="<?= e($currentUrl) ?>">
                                    <button type="submit" class="btn-secondary">Start Procedure</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($inProgress)): ?>
                        <?php foreach ($inProgress as $appt): ?>
                            <form method="POST" action="<?= e($baseUrl . '/staff/appointments/complete') ?>" class="quick-action-form" data-action-type="complete">
                                <?= Csrf::inputField(); ?>
                                <input type="hidden" name="appointment_id" value="<?= (int) ($appt['appointment_id'] ?? 0) ?>">
                                <input type="hidden" name="remarks" value="">
                                <input type="hidden" name="redirect_to" value="<?= e($currentUrl) ?>">
                                <button type="submit" class="queue-btn complete">Complete Current Treatment</button>
                            </form>
                            <?php break; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('appointmentFilterForm');
    const autoSubmitFields = document.querySelectorAll('[data-auto-submit="1"]');
    const quickActionForms = document.querySelectorAll('.quick-action-form');
    const flashBoxes = document.querySelectorAll('[data-flash-box]');
    const appointmentCards = document.querySelectorAll('[data-appointment-card]');
    const appointmentsGrid = document.getElementById('appointmentsGrid');
    const queueToggleBtn = document.getElementById('queueToggleBtn');
    const queueToggleIcon = document.getElementById('queueToggleIcon');

    autoSubmitFields.forEach(function (field) {
        field.addEventListener('change', function () {
            if (filterForm) {
                filterForm.submit();
            }
        });
    });

    appointmentCards.forEach(function (card) {
        const toggleArea = card.querySelector('[data-appointment-toggle]');
        const details = card.querySelector('.appointment-details');

        if (!toggleArea || !details) {
            return;
        }

        toggleArea.addEventListener('click', function () {
            const isOpen = card.classList.contains('is-open');

            if (isOpen) {
                details.style.maxHeight = details.scrollHeight + 'px';

                window.requestAnimationFrame(function () {
                    details.style.maxHeight = '0px';
                });

                card.classList.remove('is-open');
                return;
            }

            card.classList.add('is-open');
            details.style.maxHeight = details.scrollHeight + 'px';
        });
    });

    quickActionForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const card = form.closest('.appointment-card, .queue-row, .queue-body');
            const textarea = card ? card.querySelector('.action-remarks') : null;
            const hiddenRemarks = form.querySelector('input[name="remarks"]');
            const actionType = form.getAttribute('data-action-type');

            if (textarea && hiddenRemarks) {
                hiddenRemarks.value = textarea.value.trim();
            }

            const messages = {
                check_in: 'Mark this patient as Checked-In?',
                in_progress: 'Start treatment for this patient?',
                complete: 'Are you sure you want to mark this appointment as Completed?',
                no_show: 'Are you sure you want to mark this appointment as No Show?',
                cancel: 'Are you sure you want to cancel this appointment?'
            };

            if (messages[actionType] && !window.confirm(messages[actionType])) {
                event.preventDefault();
            }
        });
    });

    flashBoxes.forEach(function (box) {
        const closeBtn = box.querySelector('[data-flash-close]');

        function hideFlash() {
            if (box.parentNode) {
                box.parentNode.removeChild(box);
            }
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', hideFlash);
        }

        window.setTimeout(hideFlash, 4500);
    });

    function updateQueueButtonState() {
        if (!appointmentsGrid || !queueToggleBtn || !queueToggleIcon) {
            return;
        }

        const isHidden = appointmentsGrid.classList.contains('queue-is-hidden');

        queueToggleIcon.textContent = isHidden ? '☰' : '×';
        queueToggleBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        queueToggleBtn.setAttribute('aria-label', isHidden ? 'Show clinic queue' : 'Hide clinic queue');
        queueToggleBtn.setAttribute('title', isHidden ? 'Show clinic queue' : 'Hide clinic queue');
    }

    if (appointmentsGrid && queueToggleBtn) {
        updateQueueButtonState();

        queueToggleBtn.addEventListener('click', function () {
            appointmentsGrid.classList.toggle('queue-is-hidden');
            updateQueueButtonState();
        });
    }
});
</script>

<?php
$staffContent = ob_get_clean();
$pageTitle = 'Appointments';
require __DIR__ . '/../layouts/app.php';
?>