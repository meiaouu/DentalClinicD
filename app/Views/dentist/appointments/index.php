<?php

use App\Core\Csrf;

$pageTitle = 'My Appointments';

$todayAppointments = isset($todayAppointments) && is_array($todayAppointments) ? $todayAppointments : [];
$upcomingAppointments = isset($upcomingAppointments) && is_array($upcomingAppointments) ? $upcomingAppointments : [];
$pastAppointments = isset($pastAppointments) && is_array($pastAppointments) ? $pastAppointments : [];

$date = $date ?? '';
$status = $status ?? '';

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('patientName')) {
    function patientName(array $appointment): string
    {
        $patientName = trim(
            (string) ($appointment['patient_first_name'] ?? '') . ' ' .
            (string) ($appointment['patient_middle_name'] ?? '') . ' ' .
            (string) ($appointment['patient_last_name'] ?? '')
        );

        if ($patientName !== '') {
            return $patientName;
        }

        $guestName = trim(
            (string) ($appointment['guest_first_name'] ?? '') . ' ' .
            (string) ($appointment['guest_middle_name'] ?? '') . ' ' .
            (string) ($appointment['guest_last_name'] ?? '')
        );

        return $guestName !== '' ? $guestName : 'Unnamed Patient';
    }
}

if (!function_exists('patientType')) {
    function patientType(array $appointment): string
    {
        $visitType = trim((string) ($appointment['patient_visit_type'] ?? ''));

        if (in_array($visitType, ['New', 'Returning'], true)) {
            return $visitType;
        }

        $patientId = (int) ($appointment['patient_id'] ?? 0);

        if ($patientId <= 0) {
            return 'New';
        }

        $priorAppointmentCount = (int) (
            $appointment['prior_appointment_count']
            ?? $appointment['past_appointment_count']
            ?? 0
        );

        $totalAppointments = (int) ($appointment['patient_total_appointments'] ?? 0);

        if ($priorAppointmentCount > 0 || $totalAppointments > 1) {
            return 'Returning';
        }

        return 'New';
    }
}

if (!function_exists('statusLabel')) {
    function statusLabel(string $status): string
    {
        return $status !== '' ? ucwords(str_replace('_', ' ', $status)) : 'N/A';
    }
}

if (!function_exists('statusClass')) {
    function statusClass(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'confirmed' => 'confirmed',
            'checked_in' => 'checked',
            'in_progress' => 'progress',
            'completed' => 'completed',
            'rescheduled' => 'rescheduled',
            'cancelled', 'rejected', 'no_show' => 'cancelled',
            default => 'default',
        };
    }
}

if (!function_exists('formatDateValue')) {
    function formatDateValue(string $date): string
    {
        if ($date === '' || !strtotime($date)) {
            return 'No date';
        }

        return date('M d, Y', strtotime($date));
    }
}

if (!function_exists('formatTimeValue')) {
    function formatTimeValue(string $time): string
    {
        if ($time === '' || !strtotime($time)) {
            return '';
        }

        return date('h:i A', strtotime($time));
    }
}

if (!function_exists('formatCompletedTimeValue')) {
    function formatCompletedTimeValue(array $appointment): string
    {
        $completedAt = trim((string) (
            $appointment['actual_completed_at']
            ?? $appointment['completed_at']
            ?? ''
        ));

        if ($completedAt === '' || strtotime($completedAt) === false) {
            return 'Completed time not recorded';
        }

        return date('h:i A', strtotime($completedAt));
    }
}

if (!function_exists('formatActualStartedTimeValue')) {
    function formatActualStartedTimeValue(array $appointment): string
    {
        $startedAt = trim((string) ($appointment['actual_started_at'] ?? ''));

        if ($startedAt === '' || strtotime($startedAt) === false) {
            return 'Not started';
        }

        return date('h:i A', strtotime($startedAt));
    }
}

if (!function_exists('formatActualDurationValue')) {
    function formatActualDurationValue(array $appointment): string
    {
        $startedAt = trim((string) ($appointment['actual_started_at'] ?? ''));
        $completedAt = trim((string) ($appointment['actual_completed_at'] ?? ''));

        if (
            $startedAt === '' ||
            $completedAt === '' ||
            strtotime($startedAt) === false ||
            strtotime($completedAt) === false
        ) {
            return '—';
        }

        $seconds = strtotime($completedAt) - strtotime($startedAt);

        if ($seconds < 0) {
            return '—';
        }

        $minutes = (int) floor($seconds / 60);
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return $hours . ' hr ' . $remainingMinutes . ' min';
        }

        if ($hours > 0) {
            return $hours . ' hr';
        }

        return $minutes . ' min';
    }
}

if (!function_exists('appointmentCheckedInText')) {
    function appointmentCheckedInText(array $appointment): string
    {
        $status = strtolower(trim((string) ($appointment['status'] ?? '')));

        return in_array($status, ['checked_in', 'in_progress', 'completed'], true)
            ? 'Yes'
            : 'No';
    }
}

if (!function_exists('formatCheckedInTimeValue')) {
    function formatCheckedInTimeValue(array $appointment): string
    {
        if (appointmentCheckedInText($appointment) !== 'Yes') {
            return '';
        }

        $checkedAt = trim((string) (
            $appointment['checked_in_at']
            ?? $appointment['check_in_at']
            ?? ''
        ));

        if ($checkedAt === '' || strtotime($checkedAt) === false) {
            return '';
        }

        return date('h:i A', strtotime($checkedAt));
    }
}

if (!function_exists('sortAppointmentsCompletedLast')) {
    function sortAppointmentsCompletedLast(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            $aStatus = strtolower(trim((string) ($a['status'] ?? '')));
            $bStatus = strtolower(trim((string) ($b['status'] ?? '')));

            $aIsCompleted = $aStatus === 'completed' ? 1 : 0;
            $bIsCompleted = $bStatus === 'completed' ? 1 : 0;

            if ($aIsCompleted !== $bIsCompleted) {
                return $aIsCompleted <=> $bIsCompleted;
            }

            $aDate = (string) ($a['appointment_date'] ?? '');
            $bDate = (string) ($b['appointment_date'] ?? '');

            if ($aDate !== $bDate) {
                return strcmp($aDate, $bDate);
            }

            $aTime = (string) ($a['start_time'] ?? '');
            $bTime = (string) ($b['start_time'] ?? '');

            if ($aTime !== $bTime) {
                return strcmp($aTime, $bTime);
            }

            return (int) ($a['appointment_id'] ?? 0) <=> (int) ($b['appointment_id'] ?? 0);
        });

        return $rows;
    }
}

if (!function_exists('renderAppointmentRows')) {
    function renderAppointmentRows(array $rows): void
    {
        if (empty($rows)) {
            echo '<div class="empty-state">No appointments found.</div>';
            return;
        }

        $rows = sortAppointmentsCompletedLast($rows);

        foreach ($rows as $appointment) {
            $appointmentId = (int) ($appointment['appointment_id'] ?? 0);
            $patientId = (int) ($appointment['patient_id'] ?? 0);

            $name = patientName($appointment);
            $type = patientType($appointment);
            $service = (string) ($appointment['service_name'] ?? 'Service');
            $appointmentCode = (string) ($appointment['appointment_code'] ?? ('APT-' . $appointmentId));

            $date = (string) ($appointment['appointment_date'] ?? '');
            $start = (string) ($appointment['start_time'] ?? '');
            $end = (string) ($appointment['end_time'] ?? '');
            $status = (string) ($appointment['status'] ?? '');

            $statusKey = strtolower(trim($status));
            $isTodayAppointment = $date === date('Y-m-d');

            $canStartNow = $statusKey === 'checked_in' && $isTodayAppointment && $patientId > 0;
            $canContinueTreatment = $statusKey === 'in_progress' && $patientId > 0;

            $treatmentRecordUrl = $patientId > 0
                ? '/DentalClinic/public/dentist/patients/show?id=' . urlencode((string) $patientId) .
                    '&tab=treatment-records' .
                    '&appointment_id=' . urlencode((string) $appointmentId)
                : '#';

            $notes = trim((string) ($appointment['request_notes'] ?? $appointment['remarks'] ?? ''));
            $completedTime = formatCompletedTimeValue($appointment);

            $checkedInText = appointmentCheckedInText($appointment);
            $checkedInTime = formatCheckedInTimeValue($appointment);
            $checkedInClass = $checkedInText === 'Yes' ? 'yes' : 'no';

            $dateLabel = formatDateValue($date);
            $startLabel = formatTimeValue($start);
            $endLabel = formatTimeValue($end);
            $timeLabel = trim($startLabel . ($endLabel !== '' ? ' - ' . $endLabel : ''));

            $rowStatusClass = $statusKey === 'completed' ? ' is-completed' : '';

            echo '<div
                role="button"
                tabindex="0"
                class="appointment-row' . e($rowStatusClass) . '"
                data-appointment-id="' . e((string) $appointmentId) . '"
                data-patient-id="' . e((string) $patientId) . '"
                data-can-start-now="' . e($canStartNow ? '1' : '0') . '"
                data-can-continue-treatment="' . e($canContinueTreatment ? '1' : '0') . '"
                data-treatment-url="' . e($treatmentRecordUrl) . '"
                data-name="' . e($name) . '"
                data-type="' . e($type) . '"
                data-service="' . e($service) . '"
                data-code="' . e($appointmentCode) . '"
                data-date="' . e($dateLabel) . '"
                data-time="' . e($timeLabel !== '' ? $timeLabel : 'No time') . '"
                data-status="' . e(statusLabel($status)) . '"
                data-status-class="' . e(statusClass($status)) . '"
                data-notes="' . e($notes !== '' ? $notes : 'No notes') . '"
                data-completed-time="' . e($completedTime !== '' ? $completedTime : '—') . '"
                data-actual-started-time="' . e(formatActualStartedTimeValue($appointment)) . '"
data-actual-completed-time="' . e(formatCompletedTimeValue($appointment)) . '"
data-actual-duration="' . e(formatActualDurationValue($appointment)) . '"
                data-checked-in="' . e($checkedInText) . '"
                data-checked-in-time="' . e($checkedInTime !== '' ? $checkedInTime : '—') . '"
            >';

            echo '<span class="appointment-cell appointment-code">' . e($appointmentCode) . '</span>';

            echo '<span class="appointment-cell">';
            echo '<strong class="patient-name">' . e($name) . '</strong>';
            echo '<small class="patient-meta">' . e($type) . ' Patient</small>';
            echo '<small class="checkin-label ' . e($checkedInClass) . '">Checked in: ' . e($checkedInText) . '</small>';
            echo '</span>';

            echo '<span class="appointment-cell">' . e($service) . '</span>';

            echo '<span class="appointment-cell">';
            echo '<strong>' . e($dateLabel) . '</strong>';
            echo '<small class="patient-meta">' . e($timeLabel !== '' ? $timeLabel : 'No time') . '</small>';
            echo '</span>';

            echo '<span class="appointment-cell">';
            echo '<span class="status-label ' . e(statusClass($status)) . '">' . e(statusLabel($status)) . '</span>';

            if ($statusKey === 'completed') {
                echo '<small class="completed-time-label">Completed: ' . e($completedTime !== '' ? $completedTime : 'Not recorded') . '</small>';
            }

            echo '</span>';
            echo '</div>';
        }
    }
}

ob_start();
?>

<style>
.checkin-label {
    font-size: 11px;
    font-weight: 800;
    line-height: 1.2;
}

.checkin-label.yes {
    color: #15803d;
}

.checkin-label.no {
    color: #94a3b8;
}

.dentist-page-extension {
    min-height: calc(100dvh - 74px);
    background: #f5f5f500;
    color: #111827;
    padding: 10px;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

.completed-time-label {
    color: #7a7a7a;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.2;
}

.filter-card {
    background: #ffffff;
    border: 1px solid #d7dee8;
    padding: 10px;
    margin-bottom: 12px;
    box-sizing: border-box;
}

.filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: end;
}

.filter-group {
    width: 210px;
    max-width: 100%;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    color: #111827;
    font-size: 12px;
    font-weight: 800;
}

.filter-control {
    width: 100%;
    height: 35px;
    border: 1px solid #b8c4d4;
    background: #ffffff;
    color: #111827;
    padding: 0 8px;
    font-size: 13px;
    box-sizing: border-box;
}

.filter-control:focus {
    outline: none;
    border-color: #15806e;
}

.main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 320px;
    gap: 12px;
    align-items: start;
}

.left-panel,
.right-panel {
    background: #ffffff;
    border: 1px solid #d7dee8;
    padding: 12px;
    box-sizing: border-box;
}

.right-panel {
    position: sticky;
    top: 82px;
}

.panel-title {
    margin: 0 0 10px;
    font-size: 17px;
    color: #111827;
    font-weight: 900;
}

.appointment-section {
    margin-bottom: 16px;
}

.appointment-section:last-child {
    margin-bottom: 0;
}

.today-block {
    background: #f0fdfa;
    border: 2px solid #158074;
    padding: 10px;
}

.today-block .section-title {
    color: #15803d;
    font-size: 16px;
}

.today-block .appointment-table {
    border-color: #15803d;
}

.today-block .appointment-header {
    background: #dff5ef;
}

.today-block .appointment-row {
    background: #ffffff;
}

.today-block .appointment-row:hover,
.today-block .appointment-row.active {
    background: #dcfce7;
}

.today-block .appointment-row.active {
    box-shadow: inset 4px 0 0 #15803d;
}

.upcoming-block {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    padding: 10px;
}

.upcoming-block .section-title {
    color: #94a3b8;
}

.upcoming-block .appointment-table {
    border-color: #e5e7eb;
}

.upcoming-block .appointment-header {
    background: #f1f5f9;
    color: #94a3b8;
}

.upcoming-block .appointment-row {
    background: #fafafa;
    opacity: 0.58;
}

.upcoming-block .appointment-row:hover,
.upcoming-block .appointment-row.active {
    opacity: 1;
    background: #f8fafc;
}

.upcoming-block .appointment-row.active {
    box-shadow: inset 3px 0 0 #94a3b8;
}

.past-block {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    padding: 10px;
}

.past-block .appointment-header {
    background: #f8fafc;
    color: #64748b;
}

.section-title {
    margin: 0 0 8px;
    color: #111827;
    font-size: 15px;
    font-weight: 900;
}

.section-title.today {
    color: #15803d;
}

.section-title.past {
    color: #6b7280;
}

.appointment-table {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #cbd5e1;
    background: #ffffff;
}

.appointment-header,
.appointment-row {
    display: grid;
    grid-template-columns:
        minmax(135px, 0.9fr)
        minmax(180px, 1.3fr)
        minmax(150px, 1fr)
        minmax(150px, 1fr)
        minmax(110px, 0.8fr);
    min-width: 820px;
    align-items: center;
}

.appointment-header {
    background: #dff5ef;
    color: #111827;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}

.appointment-header span {
    padding: 9px 8px;
    border-right: 1px solid #cbd5e1;
}

.appointment-header span:last-child {
    border-right: none;
}

.appointment-row {
    width: 100%;
    border: none;
    border-bottom: 1px solid #e5e7eb;
    background: #ffffff;
    padding: 0;
    color: #111827;
    text-align: left;
    cursor: pointer;
    font-family: Arial, sans-serif;
}

.appointment-row:last-child {
    border-bottom: none;
}

.appointment-row:hover,
.appointment-row.active {
    background: #f0fdf4;
}

.appointment-row.active {
    box-shadow: inset 3px 0 0 #15803d;
}

.appointment-cell {
    min-height: 54px;
    padding: 9px 8px;
    border-right: 1px solid #e5e7eb;
    display: grid;
    align-content: center;
    gap: 3px;
    font-size: 13px;
    box-sizing: border-box;
    overflow: hidden;
}

.appointment-cell:last-child {
    border-right: none;
}

.appointment-code {
    color: #374151;
    font-weight: 900;
}

.patient-name {
    color: #111827;
    font-weight: 900;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.patient-meta {
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}

.status-label {
    width: fit-content;
    min-height: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 8px;
    border: 1px solid #94a3b8;
    background: #ffffff;
    color: #374151;
    font-size: 11px;
    font-weight: 900;
    white-space: nowrap;
}

.status-label.confirmed,
.status-label.checked{
    color: #0c5828;
    border-color: #22c55e;
    background-color: #9fdfb7;
}
.status-label.progress{
    color: #590e92;
    border-color: #8546dd;
    background-color: #cdb4ee
}

.status-label.completed {
    color: #1d4ed8;
    border-color: #60a5fa;
    background-color: #9cbef4
}

.status-label.rescheduled {
    color: #92400e;
    border-color: #f59e0b;
    background-color: #ffeea8
}

.status-label.cancelled {
    color: #b91c1c;
    border-color: #ef4444;
    background-color: #ffa99c
}

.empty-state {
    border: 1px dashed #cbd5e1;
    background: #ffffff;
    color: #64748b;
    padding: 12px;
    font-size: 13px;
    font-weight: 700;
}

.detail-empty {
    border: 1px dashed #cbd5e1;
    background: #ffffff;
    color: #64748b;
    padding: 12px;
    font-size: 13px;
    font-weight: 700;
}

.detail-row {
    border-bottom: 1px solid #e5e7eb;
    padding: 9px 0;
}

.detail-label {
    display: block;
    margin-bottom: 4px;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.detail-value {
    color: #111827;
    font-size: 14px;
    font-weight: 900;
    line-height: 1.4;
    word-break: break-word;
}

.detail-status {
    width: fit-content;
    margin-top: 2px;
}

.record-btn {
    min-height: 36px;
    margin-top: 12px;
    padding: 0 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #15803d;
    border: 1px solid #15803d;
    color: #ffffff;
    font-size: 13px;
    font-weight: 900;
    text-decoration: none;
    box-sizing: border-box;
    width: 100%;
}

.record-btn:hover {
    background: #166534;
    border-color: #166534;
}

.record-btn.disabled {
    background: #e5e7eb;
    border-color: #d1d5db;
    color: #6b7280;
    pointer-events: none;
}

.start-now-form {
    margin-top: 10px;
}

.start-now-btn,
.continue-treatment-btn {
    width: 100%;
    min-height: 38px;
    border: 1px solid #111827;
    background: #111827;
    color: #ffffff;
    font-size: 13px;
    font-weight: 900;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    box-sizing: border-box;
}

.continue-treatment-btn {
    margin-top: 10px;
}

.start-now-btn:hover,
.continue-treatment-btn:hover {
    background: #15803d;
    border-color: #15803d;
}

.appointment-row.is-completed {
    opacity: 0.72;
    background: #f8fafc;
}

.appointment-row.is-completed:hover,
.appointment-row.is-completed.active {
    opacity: 1;
    background: #eef6ff;
}

.appointment-row.is-completed .patient-name,
.appointment-row.is-completed .appointment-code {
    color: #64748b;
}

.filter-card,
.left-panel,
.right-panel,
.appointment-section {
    animation: softFadeIn 0.22s ease both;
}

.appointment-section:nth-child(2) {
    animation-delay: 0.04s;
}

.appointment-section:nth-child(3) {
    animation-delay: 0.08s;
}

.appointment-row {
    transition:
        background-color 0.18s ease,
        box-shadow 0.18s ease,
        opacity 0.18s ease,
        color 0.18s ease;
}

.appointment-cell,
.patient-name,
.patient-meta,
.appointment-code,
.status-label,
.record-btn,
.start-now-btn,
.continue-treatment-btn {
    transition:
        color 0.18s ease,
        background-color 0.18s ease,
        border-color 0.18s ease,
        opacity 0.18s ease;
}

.detail-empty,
#detailContent {
    animation: softDetailIn 0.2s ease both;
}

#detailContent.detail-changing {
    opacity: 0;
    transform: translateY(3px);
}

#detailContent.detail-visible {
    opacity: 1;
    transform: translateY(0);
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

@keyframes softFadeIn {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

@keyframes softDetailIn {
    from {
        opacity: 0;
        transform: translateY(3px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 1000px) {
    .main-grid {
        grid-template-columns: 1fr;
    }

    .right-panel {
        position: static;
    }
}

@media (max-width: 760px) {
    .dentist-page-extension {
        padding: 8px;
    }

    .filter-form {
        display: grid;
        grid-template-columns: 1fr;
    }

    .filter-group {
        width: 100%;
    }

    .main-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .left-panel,
    .right-panel,
    .filter-card {
        padding: 10px;
    }

    .right-panel {
        position: static;
        order: -1;
    }

    .appointment-table {
        border: 0;
        overflow: visible;
        background: transparent;
    }

    .appointment-header {
        display: none;
    }

    .appointment-list {
        display: grid;
        gap: 8px;
    }

    .appointment-row {
        min-width: 0;
        width: 100%;
        display: grid;
        grid-template-columns: 1fr;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        padding: 10px;
        gap: 8px;
    }

    .appointment-cell {
        min-height: auto;
        border-right: 0;
        border-bottom: 1px solid #f1f5f9;
        padding: 0 0 8px;
    }

    .appointment-cell:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .appointment-code {
        font-size: 12px;
        color: #15803d;
    }

    .patient-name {
        white-space: normal;
        overflow: visible;
        text-overflow: unset;
    }

    .today-block,
    .upcoming-block,
    .past-block {
        padding: 8px;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
</style>

<div class="dentist-page-extension">

    <div class="filter-card">
        <form id="appointmentFilterForm" method="GET" action="/DentalClinic/public/dentist/appointments" class="filter-form">
            <div class="filter-group">
                <label for="date">Date</label>
                <input
                    id="date"
                    class="filter-control auto-filter"
                    type="date"
                    name="date"
                    value="<?= e((string) $date) ?>"
                >
            </div>

            <div class="filter-group">
                <label for="status">Status</label>
                <select id="status" class="filter-control auto-filter" name="status">
                    <option value="">All Statuses</option>
                    <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="checked_in" <?= $status === 'checked_in' ? 'selected' : '' ?>>Checked-In</option>
                    <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="no_show" <?= $status === 'no_show' ? 'selected' : '' ?>>No-Show</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="rescheduled" <?= $status === 'rescheduled' ? 'selected' : '' ?>>Rescheduled</option>
                </select>
            </div>
        </form>
    </div>

    <div class="main-grid">
        <div class="left-panel">
            <div class="appointment-section today-block">
                <h2 class="section-title today">Today</h2>

                <div class="appointment-table">
                    <div class="appointment-header">
                        <span>APT No.</span>
                        <span>Patient</span>
                        <span>Service</span>
                        <span>Schedule</span>
                        <span>Status</span>
                    </div>

                    <div class="appointment-list">
                        <?php renderAppointmentRows($todayAppointments); ?>
                    </div>
                </div>
            </div>

            <div class="appointment-section upcoming-block">
                <h2 class="section-title upcoming">Upcoming</h2>

                <div class="appointment-table">
                    <div class="appointment-header">
                        <span>APT No.</span>
                        <span>Patient</span>
                        <span>Service</span>
                        <span>Schedule</span>
                        <span>Status</span>
                    </div>

                    <div class="appointment-list">
                        <?php renderAppointmentRows($upcomingAppointments); ?>
                    </div>
                </div>
            </div>

            <div class="appointment-section past-block">
                <h2 class="section-title past">Past</h2>

                <div class="appointment-table">
                    <div class="appointment-header">
                        <span>APT No.</span>
                        <span>Patient</span>
                        <span>Service</span>
                        <span>Schedule</span>
                        <span>Status</span>
                    </div>

                    <div class="appointment-list">
                        <?php renderAppointmentRows($pastAppointments); ?>
                    </div>
                </div>
            </div>
        </div>

        <aside class="right-panel">
            <h2 class="panel-title">Appointment Info</h2>

            <div id="emptyDetail" class="detail-empty">
                Select an appointment from the list.
            </div>

            <div id="detailContent" style="display: none;">
                <div class="detail-row">
                    <span class="detail-label">Appointment No.</span>
                    <span class="detail-value" id="detailCode"></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Patient</span>
                    <span class="detail-value" id="detailName"></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Patient Type</span>
                    <span class="detail-value" id="detailType"></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Checked-In</span>
                    <span class="detail-value" id="detailCheckedIn"></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Service</span>
                    <span class="detail-value" id="detailService"></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Schedule</span>
                    <span class="detail-value" id="detailSchedule"></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Notes / Concern</span>
                    <span class="detail-value" id="detailNotes"></span>
                </div>

                <a href="#" id="recordBtn" class="record-btn">
                    View Patient Record
                </a>

                <form
    method="POST"
    action="/DentalClinic/public/dentist/appointments/start-treatment"
    id="startNowForm"
    class="start-now-form"
    style="display:none;"
>
    <?= Csrf::inputField(); ?>
    <input type="hidden" name="appointment_id" id="startNowAppointmentId" value="">

    <button type="submit" class="start-now-btn">
        Start Procedure
    </button>
</form>

<a href="#" id="continueTreatmentBtn" class="continue-treatment-btn" style="display:none;">
    Continue Treatment Record
</a>

<form
    method="POST"
    action="/DentalClinic/public/dentist/appointments/complete-treatment"
    id="completeProcedureForm"
    class="start-now-form"
    style="display:none;"
>
    <?= Csrf::inputField(); ?>
    <input type="hidden" name="appointment_id" id="completeProcedureAppointmentId" value="">
    <input type="hidden" name="redirect_to" value="<?= e('/DentalClinic/public/dentist/appointments') ?>">

    <button type="submit" class="start-now-btn">
        Complete Procedure
    </button>
</form>
            </div>
        </aside>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('appointmentFilterForm');
    const filterControls = document.querySelectorAll('.auto-filter');

    filterControls.forEach(function (control) {
        control.addEventListener('change', function () {
            if (filterForm) {
                filterForm.submit();
            }
        });
    });

    const items = document.querySelectorAll('.appointment-row');

    const emptyDetail = document.getElementById('emptyDetail');
    const detailContent = document.getElementById('detailContent');

    const detailCode = document.getElementById('detailCode');
    const detailName = document.getElementById('detailName');
    const detailType = document.getElementById('detailType');
    const detailCheckedIn = document.getElementById('detailCheckedIn');
    const detailService = document.getElementById('detailService');
    const detailSchedule = document.getElementById('detailSchedule');
    const detailStatus = document.getElementById('detailStatus');
    const detailCompletedTime = document.getElementById('detailCompletedTime');
    const completedTimeRow = document.getElementById('completedTimeRow');
    const detailNotes = document.getElementById('detailNotes');
    const recordBtn = document.getElementById('recordBtn');

    const startNowForm = document.getElementById('startNowForm');
    const startNowAppointmentId = document.getElementById('startNowAppointmentId');
    const continueTreatmentBtn = document.getElementById('continueTreatmentBtn');
const completeProcedureForm = document.getElementById('completeProcedureForm');
const completeProcedureAppointmentId = document.getElementById('completeProcedureAppointmentId');
const detailActualStarted = document.getElementById('detailActualStarted');
const detailActualCompleted = document.getElementById('detailActualCompleted');
const detailActualDuration = document.getElementById('detailActualDuration');


    function updateDetails(item) {
        if (!item) {
            return;
        }

        if (detailCode) {
            detailCode.textContent = item.dataset.code || 'N/A';
        }

        if (detailName) {
            detailName.textContent = item.dataset.name || 'N/A';
        }

        if (detailType) {
            detailType.textContent = item.dataset.type || 'N/A';
        }

        const checkedIn = item.dataset.checkedIn || 'No';
        const checkedInTime = item.dataset.checkedInTime || '—';

        if (detailCheckedIn) {
            detailCheckedIn.textContent = checkedIn === 'Yes' && checkedInTime !== '—'
                ? checkedIn + ' | ' + checkedInTime
                : checkedIn;
        }

        if (detailService) {
            detailService.textContent = item.dataset.service || 'N/A';
        }

        if (detailSchedule) {
            detailSchedule.textContent = (item.dataset.date || 'No date') + ' | ' + (item.dataset.time || 'No time');
        }

        if (detailActualStarted) {
    detailActualStarted.textContent = item.dataset.actualStartedTime || 'Not started';
}

if (detailActualCompleted) {
    detailActualCompleted.textContent = item.dataset.actualCompletedTime || 'Not completed';
}

if (detailActualDuration) {
    detailActualDuration.textContent = item.dataset.actualDuration || '—';
}

        if (detailStatus) {
            detailStatus.textContent = item.dataset.status || 'N/A';
            detailStatus.className = 'detail-value detail-status status-label ' + (item.dataset.statusClass || 'default');
        }

        if ((item.dataset.status || '').toLowerCase() === 'completed') {
            if (completedTimeRow) {
                completedTimeRow.style.display = 'block';
            }

            if (detailCompletedTime) {
                detailCompletedTime.textContent = item.dataset.completedTime || 'Completed time not recorded';
            }
        } else {
            if (completedTimeRow) {
                completedTimeRow.style.display = 'none';
            }

            if (detailCompletedTime) {
                detailCompletedTime.textContent = '';
            }
        }

        if (detailNotes) {
            detailNotes.textContent = item.dataset.notes || 'No notes';
        }

        const patientId = item.dataset.patientId || '0';
        const appointmentId = item.dataset.appointmentId || '';
        const treatmentUrl = item.dataset.treatmentUrl || '#';

        const canStartNow = item.dataset.canStartNow === '1';
        const canContinueTreatment = item.dataset.canContinueTreatment === '1';

        if (recordBtn) {
            if (parseInt(patientId, 10) > 0) {
                recordBtn.href = '/DentalClinic/public/dentist/patients/show?id=' + encodeURIComponent(patientId);
                recordBtn.classList.remove('disabled');
                recordBtn.textContent = 'View Patient Record';
            } else {
                recordBtn.href = '#';
                recordBtn.classList.add('disabled');
                recordBtn.textContent = 'No Patient Record Yet';
            }
        }

        if (startNowForm && startNowAppointmentId) {
            if (canStartNow) {
                startNowForm.style.display = 'block';
                startNowAppointmentId.value = appointmentId;
            } else {
                startNowForm.style.display = 'none';
                startNowAppointmentId.value = '';
            }
        }

        if (completeProcedureForm && completeProcedureAppointmentId) {
    const currentStatus = (item.dataset.status || '').toLowerCase().replace(/\s+/g, '_');

    if (currentStatus === 'in_progress') {
        completeProcedureForm.style.display = 'block';
        completeProcedureAppointmentId.value = appointmentId;
    } else {
        completeProcedureForm.style.display = 'none';
        completeProcedureAppointmentId.value = '';
    }
}

        if (continueTreatmentBtn) {
            if (canContinueTreatment && treatmentUrl !== '#') {
                continueTreatmentBtn.style.display = 'inline-flex';
                continueTreatmentBtn.href = treatmentUrl;
            } else {
                continueTreatmentBtn.style.display = 'none';
                continueTreatmentBtn.href = '#';
            }
        }
    }

    function selectAppointment(item) {
        if (!item) {
            return;
        }

        items.forEach(function (row) {
            row.classList.remove('active');
        });

        item.classList.add('active');

        if (emptyDetail) {
            emptyDetail.style.display = 'none';
        }

        if (!detailContent) {
            return;
        }

        detailContent.classList.remove('detail-visible');
        detailContent.classList.add('detail-changing');

        window.setTimeout(function () {
            detailContent.style.display = 'block';
            updateDetails(item);

            window.requestAnimationFrame(function () {
                detailContent.classList.remove('detail-changing');
                detailContent.classList.add('detail-visible');
            });
        }, 90);
    }

    items.forEach(function (item) {
        item.addEventListener('click', function () {
            selectAppointment(item);
        });

        item.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            selectAppointment(item);
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
?>