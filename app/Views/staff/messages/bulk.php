<?php

use App\Core\Csrf;

$pageTitle = 'Bulk Appointment Reminders';
$baseUrl = '/DentalClinic/public';

$appointments = isset($appointments) && is_array($appointments) ? $appointments : [];
$dentists = isset($dentists) && is_array($dentists) ? $dentists : [];
$services = isset($services) && is_array($services) ? $services : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];

$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('selectedValue')) {
    function selectedValue($current, $target): string
    {
        return (string) $current === (string) $target ? 'selected' : '';
    }
}

if (!function_exists('formatReminderDate')) {
    function formatReminderDate($date): string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return '—';
        }

        $timestamp = strtotime($date);

        return $timestamp ? date('M d, Y', $timestamp) : $date;
    }
}

if (!function_exists('formatReminderTime')) {
    function formatReminderTime($start, $end): string
    {
        $start = trim((string) $start);
        $end = trim((string) $end);

        if ($start === '' && $end === '') {
            return '—';
        }

        $startText = $start;
        $endText = $end;

        if ($start !== '') {
            $timestamp = strtotime($start);
            $startText = $timestamp ? date('h:i A', $timestamp) : $start;
        }

        if ($end !== '') {
            $timestamp = strtotime($end);
            $endText = $timestamp ? date('h:i A', $timestamp) : $end;
        }

        return $startText !== '' && $endText !== ''
            ? $startText . ' - ' . $endText
            : ($startText !== '' ? $startText : $endText);
    }
}

if (!function_exists('personName')) {
    function personName($first, $middle, $last, string $fallback = '—'): string
    {
        $name = trim(implode(' ', array_filter([
            trim((string) $first),
            trim((string) $middle),
            trim((string) $last),
        ])));

        return $name !== '' ? $name : $fallback;
    }
}

if (!function_exists('reminderTypeLabel')) {
    function reminderTypeLabel(string $type): string
    {
        return match ($type) {
            'appointment_3_days' => '3 days before',
            'appointment_2_days' => '2 days before',
            'appointment_1_day' => '1 day before',
            default => 'Select reminder day',
        };
    }
}

if (!function_exists('reminderStatusText')) {
    function reminderStatusText(array $row): string
    {
        $items = [];

        foreach ([
            'appointment_3_days_status' => '3-day',
            'appointment_2_days_status' => '2-day',
            'appointment_1_day_status' => '1-day',
        ] as $key => $label) {
            $status = trim((string) ($row[$key] ?? ''));

            if ($status !== '') {
                $items[] = $label . ' ' . $status;
            }
        }

        return !empty($items) ? implode(', ', $items) : 'Not sent';
    }
}

ob_start();
?>

<link rel="stylesheet" href="<?= e($baseUrl . '/assets/css/staff-bulk-messages.css') ?>">

<div class="bulk-message-page">
    <div class="bulk-message-shell">
        <div class="bulk-header">
            <div>
                <h1>Bulk Appointment Reminders</h1>
                <p>
                    Send appointment reminders to patients with upcoming confirmed appointments.
                    Reminders are logged to prevent duplicate sending.
                </p>
            </div>
        </div>

        <?php if ($flash_success): ?>
            <div class="bulk-flash success"><?= e($flash_success) ?></div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="bulk-flash error"><?= e($flash_error) ?></div>
        <?php endif; ?>

        <div class="bulk-card">
            <form method="GET" action="<?= e($baseUrl . '/staff/messages/bulk') ?>" class="bulk-filter-form">
                <div class="bulk-field">
                    <label for="date_from">Date From</label>
                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="<?= e($filters['date_from'] ?? date('Y-m-d')) ?>"
                    >
                </div>

                <div class="bulk-field">
                    <label for="date_to">Date To</label>
                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="<?= e($filters['date_to'] ?? date('Y-m-d', strtotime('+14 days'))) ?>"
                    >
                </div>

                <div class="bulk-field">
                    <label for="dentist_id">Dentist</label>
                    <select id="dentist_id" name="dentist_id">
                        <option value="">All dentists</option>
                        <?php foreach ($dentists as $dentist): ?>
                            <?php
                                $dentistId = (int) ($dentist['dentist_id'] ?? 0);
                                $dentistName = personName(
                                    $dentist['first_name'] ?? '',
                                    $dentist['middle_name'] ?? '',
                                    $dentist['last_name'] ?? '',
                                    'Dentist'
                                );
                            ?>
                            <option value="<?= $dentistId ?>" <?= selectedValue($filters['dentist_id'] ?? '', $dentistId) ?>>
                                Dr. <?= e($dentistName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bulk-field">
                    <label for="service_id">Service</label>
                    <select id="service_id" name="service_id">
                        <option value="">All services</option>
                        <?php foreach ($services as $service): ?>
                            <?php $serviceId = (int) ($service['service_id'] ?? 0); ?>
                            <option value="<?= $serviceId ?>" <?= selectedValue($filters['service_id'] ?? '', $serviceId) ?>>
                                <?= e($service['service_name'] ?? 'Service') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bulk-field">
                    <label for="filter_reminder_type">Reminder Day</label>
                    <select id="filter_reminder_type" name="reminder_type">
                        <option value="">All reminder days</option>
                        <option value="appointment_3_days" <?= selectedValue($filters['reminder_type'] ?? '', 'appointment_3_days') ?>>3 days before</option>
                        <option value="appointment_2_days" <?= selectedValue($filters['reminder_type'] ?? '', 'appointment_2_days') ?>>2 days before</option>
                        <option value="appointment_1_day" <?= selectedValue($filters['reminder_type'] ?? '', 'appointment_1_day') ?>>1 day before</option>
                    </select>
                </div>

                <div class="bulk-filter-actions">
                    <button type="submit" class="bulk-btn primary">Apply Filters</button>
                    <a href="<?= e($baseUrl . '/staff/messages/bulk') ?>" class="bulk-btn secondary">Reset</a>
                </div>
            </form>
        </div>

        <form
            method="POST"
            action="<?= e($baseUrl . '/staff/messages/bulk/send') ?>"
            id="bulkReminderForm"
            class="bulk-card"
            data-preview-url="<?= e($baseUrl . '/staff/messages/bulk/preview') ?>"
        >
            <?= Csrf::inputField(); ?>

            <input type="hidden" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
            <input type="hidden" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
            <input type="hidden" name="dentist_id" value="<?= e((string) ($filters['dentist_id'] ?? '')) ?>">
            <input type="hidden" name="service_id" value="<?= e((string) ($filters['service_id'] ?? '')) ?>">

            <div class="bulk-send-toolbar">
                <div class="bulk-send-left">
                    <label class="bulk-check-label">
                        <input type="checkbox" id="selectAllAppointments">
                        <span>Select all patients</span>
                    </label>

                    <span class="bulk-selected-count">
                        Selected: <strong id="selectedAppointmentCount">0</strong>
                    </span>
                </div>

                <div class="bulk-send-right">
                    <select name="reminder_type" id="sendReminderType" required>
                        <option value="">Select reminder to send</option>
                        <option value="appointment_3_days" <?= selectedValue($filters['reminder_type'] ?? '', 'appointment_3_days') ?>>3 days before</option>
                        <option value="appointment_2_days" <?= selectedValue($filters['reminder_type'] ?? '', 'appointment_2_days') ?>>2 days before</option>
                        <option value="appointment_1_day" <?= selectedValue($filters['reminder_type'] ?? '', 'appointment_1_day') ?>>1 day before</option>
                    </select>

                    <button type="button" class="bulk-btn secondary" id="previewSelectedMessage">
                        Preview Message
                    </button>

                    <button type="submit" class="bulk-btn primary">
                        Send Reminders
                    </button>
                </div>
            </div>

            <div class="bulk-table-wrap">
                <table class="bulk-table">
                    <thead>
                        <tr>
                            <th>Select</th>
                            <th>Patient</th>
                            <th>Contact</th>
                            <th>Appointment Date</th>
                            <th>Time</th>
                            <th>Dentist</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Reminder Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="bulk-empty">
                                        No upcoming confirmed appointments found for the selected filters.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($appointments as $appointment): ?>
                            <?php
                                $appointmentId = (int) ($appointment['appointment_id'] ?? 0);

                                $patientName = personName(
                                    $appointment['patient_first_name'] ?? '',
                                    $appointment['patient_middle_name'] ?? '',
                                    $appointment['patient_last_name'] ?? '',
                                    'Unnamed Patient'
                                );

                                $dentistName = personName(
                                    $appointment['dentist_first_name'] ?? '',
                                    $appointment['dentist_middle_name'] ?? '',
                                    $appointment['dentist_last_name'] ?? '',
                                    'Unassigned'
                                );

                                if ($dentistName !== 'Unassigned') {
                                    $dentistName = 'Dr. ' . $dentistName;
                                }

                                $contact = trim((string) ($appointment['patient_contact_number'] ?? ''));
                                $email = trim((string) ($appointment['patient_email'] ?? ''));
                                $contactText = $contact !== '' ? $contact : ($email !== '' ? $email : 'No contact');

                                $notificationAllowed = (int) ($appointment['notification_allowed'] ?? 1) === 1;
                            ?>

                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        class="appointment-checkbox"
                                        name="appointment_ids[]"
                                        value="<?= $appointmentId ?>"
                                        <?= $appointmentId > 0 ? '' : 'disabled' ?>
                                    >
                                </td>

                                <td>
                                    <strong><?= e($patientName) ?></strong>
                                    <?php if (!$notificationAllowed): ?>
                                        <small class="bulk-warning">Notification consent not allowed</small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= e($contactText) ?>
                                </td>

                                <td>
                                    <?= e(formatReminderDate($appointment['appointment_date'] ?? '')) ?>
                                </td>

                                <td>
                                    <?= e(formatReminderTime($appointment['start_time'] ?? '', $appointment['end_time'] ?? '')) ?>
                                </td>

                                <td>
                                    <?= e($dentistName) ?>
                                </td>

                                <td>
                                    <?= e($appointment['service_name'] ?? '—') ?>
                                </td>

                                <td>
                                    <span class="bulk-status confirmed">
                                        <?= e(ucwords(str_replace('_', ' ', (string) ($appointment['status'] ?? 'confirmed')))) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="bulk-reminder-status">
                                        <?= e(reminderStatusText($appointment)) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<div class="bulk-modal-backdrop" id="bulkPreviewModal" aria-hidden="true">
    <div class="bulk-modal" role="dialog" aria-modal="true" aria-labelledby="bulkPreviewTitle">
        <div class="bulk-modal-header">
            <h2 id="bulkPreviewTitle">Message Preview</h2>
            <button type="button" class="bulk-modal-close" id="closeBulkPreviewModal" aria-label="Close">×</button>
        </div>

        <div class="bulk-modal-body">
            <p class="bulk-preview-note">
                This preview uses the first selected appointment.
            </p>

            <pre id="bulkPreviewMessage">Loading...</pre>
        </div>
    </div>
</div>

<script src="<?= e($baseUrl . '/assets/js/staff-bulk-messages.js') ?>"></script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
?>