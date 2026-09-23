<?php

use App\Core\Csrf;

$waitingQueue = isset($waitingQueue) && is_array($waitingQueue) ? $waitingQueue : [];
$inProgress = isset($inProgress) && is_array($inProgress) ? $inProgress : [];
$completed = isset($completed) && is_array($completed) ? $completed : [];
$nextPatient = isset($nextPatient) && is_array($nextPatient) ? $nextPatient : null;

if (!$nextPatient && !empty($waitingQueue)) {
    $nextPatient = $waitingQueue[0];
}

$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

$baseUrl = '/DentalClinic/public';
$queueUrl = $baseUrl . '/staff/appointments/queue';

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('queuePatientName')) {
    function queuePatientName(array $appointment): string
    {
        $name = trim((string) (
            ($appointment['patient_first_name'] ?? $appointment['guest_first_name'] ?? '') . ' ' .
            ($appointment['patient_last_name'] ?? $appointment['guest_last_name'] ?? '')
        ));

        return $name !== '' ? $name : 'Unnamed Patient';
    }
}

if (!function_exists('queueServiceName')) {
    function queueServiceName(array $appointment): string
    {
        $service = trim((string) (
            $appointment['service_name']
            ?? $appointment['service']
            ?? $appointment['name']
            ?? ''
        ));

        return $service !== '' ? $service : 'No service';
    }
}

if (!function_exists('queueDentistName')) {
    function queueDentistName(array $appointment): string
    {
        $name = trim((string) (
            ($appointment['dentist_first_name'] ?? '') . ' ' .
            ($appointment['dentist_last_name'] ?? '')
        ));

        return $name !== '' ? 'Dr. ' . $name : 'No dentist assigned';
    }
}

if (!function_exists('queueTimeLabel')) {
    function queueTimeLabel(array $appointment): string
    {
        $start = trim((string) ($appointment['start_time'] ?? ''));
        $end = trim((string) ($appointment['end_time'] ?? ''));

        if ($start === '' || strtotime($start) === false) {
            return 'No time';
        }

        $startLabel = date('h:i A', strtotime($start));

        if ($end === '' || strtotime($end) === false) {
            return $startLabel;
        }

        return $startLabel . ' - ' . date('h:i A', strtotime($end));
    }
}

if (!function_exists('queueAppointmentCode')) {
    function queueAppointmentCode(array $appointment): string
    {
        $code = trim((string) ($appointment['appointment_code'] ?? ''));

        return $code !== '' ? $code : 'APT-' . (int) ($appointment['appointment_id'] ?? 0);
    }
}

if (!function_exists('queueCompletedTime')) {
    function queueCompletedTime(array $appointment): string
    {
        $completedAt = trim((string) ($appointment['completed_at'] ?? ''));

        if ($completedAt === '') {
            $completedAt = trim((string) ($appointment['updated_at'] ?? ''));
        }

        if ($completedAt === '' || strtotime($completedAt) === false) {
            return 'Completed time not recorded';
        }

        return date('h:i A', strtotime($completedAt));
    }
}

if (!function_exists('queueStatusLabel')) {
    function queueStatusLabel(string $mode): string
    {
        return match ($mode) {
            'next' => 'Next Patient',
            'waiting' => 'Waiting',
            'progress' => 'In Progress',
            'completed' => 'Completed',
            default => 'Appointment',
        };
    }
}

if (!function_exists('renderQueueDisplayPatient')) {
    function renderQueueDisplayPatient(array $appointment, string $mode, string $baseUrl, string $queueUrl): void
    {
        $appointmentId = (int) ($appointment['appointment_id'] ?? 0);
        $patientName = queuePatientName($appointment);
        $appointmentCode = queueAppointmentCode($appointment);
        $serviceName = queueServiceName($appointment);
        $timeLabel = queueTimeLabel($appointment);
        $dentistName = queueDentistName($appointment);
        $statusLabel = queueStatusLabel($mode);

        echo '<article class="display-patient display-patient-' . e($mode) . '">';
        echo '<div class="display-patient-main">';
        echo '<span class="display-code">' . e($appointmentCode) . '</span>';
        echo '<strong class="display-name">' . e($patientName) . '</strong>';
        echo '<span class="display-meta">' . e($serviceName) . ' · ' . e($timeLabel) . '</span>';
        echo '<span class="display-meta muted">' . e($dentistName) . '</span>';
        echo '</div>';

        echo '<div class="display-actions">';

        if ($mode === 'waiting') {
            echo '<form method="POST" action="' . e($baseUrl . '/staff/appointments/in-progress') . '">';
            echo Csrf::inputField();
            echo '<input type="hidden" name="appointment_id" value="' . $appointmentId . '">';
            echo '<input type="hidden" name="redirect_to" value="' . e($queueUrl) . '">';
            echo '<button type="submit" class="display-btn">Start</button>';
            echo '</form>';
        }

        if ($mode === 'progress') {
            echo '<form method="POST" action="' . e($baseUrl . '/staff/appointments/complete') . '">';
            echo Csrf::inputField();
            echo '<input type="hidden" name="appointment_id" value="' . $appointmentId . '">';
            echo '<input type="hidden" name="redirect_to" value="' . e($queueUrl) . '">';
            echo '<button type="submit" class="display-btn">Complete</button>';
            echo '</form>';
        }

        echo '<button
            type="button"
            class="display-link queue-open-details"
            data-appointment-id="' . e((string) $appointmentId) . '"
            data-code="' . e($appointmentCode) . '"
            data-patient="' . e($patientName) . '"
            data-service="' . e($serviceName) . '"
            data-time="' . e($timeLabel) . '"
            data-dentist="' . e($dentistName) . '"
            data-status="' . e($statusLabel) . '"
        >View</button>';

        echo '</div>';
        echo '</article>';
    }
}

ob_start();
?>


<style>

.queue-page,
.queue-page *,
.queue-modal-backdrop,
.queue-modal-backdrop * {
    box-sizing: border-box;
}

.queue-page {
    min-height: calc(100dvh - 74px);
    padding: 18px;
    background: #f5f5f5;
    color: #111827;
    font-family: var(--font-ui);
}

.queue-page button,
.queue-page input,
.queue-page select,
.queue-page textarea,
.queue-modal-backdrop button,
.queue-modal-backdrop input,
.queue-modal-backdrop select,
.queue-modal-backdrop textarea {
    font-family: var(--font-ui);
}

.queue-shell {
    max-width: 1180px;
    margin: 0 auto;
}

.queue-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 14px;
}

.queue-date {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 13px;
    font-weight: 700;
}

.queue-header-actions {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.queue-refresh {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    padding: 0 14px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
    font-family: var(--font-ui);
}

.queue-refresh:hover {
    background: #111827;
    color: #ffffff;
    border-color: #111827;
}

.flash-box {
    padding: 12px 14px;
    margin-bottom: 12px;
    font-size: 13px;
    font-weight: 800;
    border: 1px solid transparent;
}

.flash-box.success {
    background: #f0fdf4;
    color: #166534;
    border-color: #bbf7d0;
}

.flash-box.error {
    background: #fef2f2;
    color: #991b1b;
    border-color: #fecaca;
}

.queue-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 14px;
}

.summary-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    padding: 14px;
}

.summary-card.highlight {
    border-left: 4px solid #0f766e;
}

.summary-label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    color: #6b7280;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.summary-value {
    display: block;
    font-size: 30px;
    font-weight: 900;
    color: #111827;
    line-height: 1;
    letter-spacing: -0.04em;
}

.summary-note {
    display: block;
    margin-top: 7px;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}

.queue-display-board {
    position: relative;
    min-height: 520px;
    background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.10), rgba(255, 255, 255, 0)),
        linear-gradient(120deg, #43b99c 0%, #0f827d 100%);
    border: 1px solid rgba(15, 118, 110, 0.35);
    box-shadow: 0 2px 8px rgba(17, 24, 39, 0.20);
    overflow: hidden;
    margin-bottom: 18px;
}

.queue-display-board::before {
    content: "";
    position: absolute;
    top: 46px;
    bottom: 46px;
    left: 50%;
    width: 1px;
    background: rgba(255, 255, 255, 0.40);
}

.queue-board-grid {
    position: relative;
    z-index: 1;
    min-height: 520px;
    display: grid;
    grid-template-columns: 1fr 1fr;
}

.queue-board-left,
.queue-board-right {
    padding: 24px 36px;
}

.board-section {
    margin-bottom: 58px;
}

.board-section:last-child {
    margin-bottom: 0;
}

.board-label {
    margin: 0 0 12px;
    color: rgba(255, 255, 255, 0.88);
    font-size: 16px;
    font-weight: 900;
    letter-spacing: 0.03em;
}

.board-empty {
    color: rgba(255, 255, 255, 0.82);
    font-size: 15px;
    font-weight: 800;
    letter-spacing: 0.02em;
}

.display-patient {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 14px;
    align-items: center;
    padding: 10px 0;
    color: #ffffff;
}

.display-patient-main {
    min-width: 0;
    display: grid;
    gap: 5px;
}

.display-code {
    color: rgba(255, 255, 255, 0.92);
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    word-break: break-word;
    font-family: var(--font-mono);
}

.display-name {
    color: #ffffff;
    font-size: 28px;
    font-weight: 900;
    line-height: 1.15;
    word-break: break-word;
    letter-spacing: -0.04em;
}

.display-meta {
    color: rgba(255, 255, 255, 0.88);
    font-size: 13px;
    font-weight: 800;
}

.display-meta.muted {
    color: rgba(255, 255, 255, 0.72);
}

.display-actions {
    display: grid;
    gap: 7px;
    min-width: 86px;
}

.display-btn,
.display-link {
    min-height: 30px;
    padding: 0 10px;
    border: 1px solid rgba(255, 255, 255, 0.55);
    background: rgba(255, 255, 255, 0.14);
    color: #ffffff;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-ui);
}

.display-btn:hover,
.display-link:hover {
    background: rgba(255, 255, 255, 0.24);
}

.board-expand {
    position: absolute;
    right: 16px;
    bottom: 14px;
    z-index: 2;
    width: 28px;
    height: 28px;
    border: 0;
    background: transparent;
    color: rgba(255, 255, 255, 0.88);
    cursor: pointer;
    font-size: 24px;
    font-weight: 400;
    line-height: 1;
}

.completed-section {
    background: #ffffff;
    border: 1px solid #e5e7eb;
}

.completed-head {
    padding: 14px;
    border-bottom: 1px solid #e5e7eb;
}

.completed-title {
    margin: 0;
    color: #111827;
    font-size: 18px;
    font-weight: 900;
    letter-spacing: -0.03em;
}

.completed-subtitle {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 12px;
    font-weight: 700;
}

.completed-list {
    display: grid;
    gap: 10px;
    padding: 12px;
}

.completed-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
    border: 1px solid #e5e7eb;
    border-left: 4px solid #15803d;
    background: #ffffff;
    padding: 12px;
}

.completed-name {
    display: block;
    color: #111827;
    font-size: 15px;
    font-weight: 900;
}

.completed-meta {
    display: block;
    margin-top: 3px;
    color: #6b7280;
    font-size: 12px;
    font-weight: 700;
}

.completed-link {
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    font-size: 12px;
    font-weight: 900;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-family: var(--font-ui);
}

.completed-link:hover {
    background: #f3f4f6;
}

.empty-state {
    padding: 16px;
    color: #6b7280;
    font-size: 13px;
    font-weight: 700;
    text-align: center;
    background: #f9fafb;
    border: 1px dashed #cbd5e1;
}

.queue-page.is-fullscreen {
    padding: 0;
    background: #111827;
}

.queue-page.is-fullscreen .queue-shell {
    max-width: none;
}

.queue-page.is-fullscreen .queue-header,
.queue-page.is-fullscreen .queue-summary,
.queue-page.is-fullscreen .flash-box,
.queue-page.is-fullscreen .completed-section {
    display: none;
}

.queue-page.is-fullscreen .queue-display-board {
    min-height: 100dvh;
    margin: 0;
    border: 0;
    box-shadow: none;
}

.queue-page.is-fullscreen .queue-board-grid {
    min-height: 100dvh;
}

.queue-page.is-fullscreen .display-actions {
    display: none;
}

.queue-page.is-fullscreen .display-name {
    font-size: 42px;
}

.queue-page.is-fullscreen .display-code {
    font-size: 20px;
}

.queue-page.is-fullscreen .board-label {
    font-size: 22px;
}

body.queue-modal-open {
    overflow: hidden;
}

body.queue-modal-open .queue-shell {
    filter: blur(3px);
    transition: filter 0.18s ease;
}

.queue-modal-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    background: rgba(17, 24, 39, 0.45);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    font-family: var(--font-ui);
}

.queue-modal-backdrop.is-open {
    display: flex;
}

.queue-modal {
    width: min(520px, 100%);
    background: #ffffff;
    border: 1px solid #d1d5db;
    box-shadow: 0 24px 70px rgba(17, 24, 39, 0.28);
    animation: queueModalIn 0.18s ease both;
}

.queue-modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 16px 18px;
    border-bottom: 1px solid #e5e7eb;
}

.queue-modal-title {
    margin: 0;
    color: #111827;
    font-size: 19px;
    font-weight: 900;
    letter-spacing: -0.03em;
}

.queue-modal-subtitle {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 12px;
    font-weight: 700;
}

.queue-modal-close {
    width: 34px;
    height: 34px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    font-size: 22px;
    font-weight: 900;
    line-height: 1;
    cursor: pointer;
}

.queue-modal-close:hover {
    background: #f3f4f6;
}

.queue-modal-body {
    padding: 16px 18px;
    display: grid;
    gap: 10px;
}

.queue-detail-row {
    display: grid;
    grid-template-columns: 135px minmax(0, 1fr);
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}

.queue-detail-row:last-child {
    border-bottom: 0;
}

.queue-detail-label {
    color: #64748b;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}

.queue-detail-value {
    color: #111827;
    font-size: 14px;
    font-weight: 800;
    line-height: 1.4;
    word-break: break-word;
}

#modalAppointmentCode {
    font-family: var(--font-mono);
}

.queue-detail-status {
    width: fit-content;
    min-height: 24px;
    padding: 0 9px;
    display: inline-flex;
    align-items: center;
    border: 1px solid #0f766e;
    background: #ccfbf1;
    color: #0f766e;
    font-size: 12px;
    font-weight: 900;
}

.queue-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 14px 18px 18px;
    border-top: 1px solid #e5e7eb;
}

.queue-modal-btn {
    min-height: 36px;
    padding: 0 14px;
    border: 1px solid #111827;
    background: #111827;
    color: #ffffff;
    font-size: 13px;
    font-weight: 900;
    cursor: pointer;
}

.queue-modal-btn:hover {
    background: #0f766e;
    border-color: #0f766e;
}

@keyframes queueModalIn {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (max-width: 1000px) {
    .queue-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .queue-display-board,
    .queue-board-grid {
        min-height: auto;
    }

    .queue-display-board::before {
        display: none;
    }

    .queue-board-grid {
        grid-template-columns: 1fr;
    }

    .queue-board-left,
    .queue-board-right {
        padding: 22px;
    }

    .queue-board-right {
        border-top: 1px solid rgba(255, 255, 255, 0.28);
    }
}

@media (max-width: 700px) {
    .queue-page {
        padding: 14px;
    }

    .queue-header {
        display: grid;
        gap: 10px;
    }

    .queue-header-actions {
        display: grid;
        grid-template-columns: 1fr;
    }

    .queue-summary {
        grid-template-columns: 1fr;
    }

    .queue-board-left,
    .queue-board-right {
        padding: 18px;
    }

    .display-patient {
        grid-template-columns: 1fr;
    }

    .display-actions {
        display: flex;
        flex-wrap: wrap;
    }

    .display-name {
        font-size: 22px;
    }

    .display-code {
        font-size: 13px;
    }

    .completed-item {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 520px) {
    .queue-detail-row {
        grid-template-columns: 1fr;
        gap: 4px;
    }
}
</style>

<div class="queue-page" id="queuePage">
    <div class="queue-shell">
        <div class="queue-header">
            <div>
                <p class="queue-date"><?= e(date('F d, Y')) ?></p>
            </div>

            <div class="queue-header-actions">
                <button type="button" class="queue-refresh" onclick="window.location.reload()">
                    Refresh
                </button>
            </div>
        </div>

        <?php if ($flash_success): ?>
            <div class="flash-box success"><?= e((string) $flash_success) ?></div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="flash-box error"><?= e((string) $flash_error) ?></div>
        <?php endif; ?>

        <div class="queue-summary">
            <div class="summary-card highlight">
                <span class="summary-label">Next Patient</span>
                <span class="summary-value"><?= $nextPatient ? '1' : '0' ?></span>
                <span class="summary-note">
                    <?= $nextPatient ? e(queuePatientName($nextPatient)) : 'No next patient' ?>
                </span>
            </div>

            <div class="summary-card">
                <span class="summary-label">Waiting</span>
                <span class="summary-value"><?= count($waitingQueue) ?></span>
                <span class="summary-note">Checked-in patients</span>
            </div>

            <div class="summary-card">
                <span class="summary-label">In Progress</span>
                <span class="summary-value"><?= count($inProgress) ?></span>
                <span class="summary-note">Currently being treated</span>
            </div>

            <div class="summary-card">
                <span class="summary-label">Completed</span>
                <span class="summary-value"><?= count($completed) ?></span>
                <span class="summary-note">Finished today</span>
            </div>
        </div>

        <section class="queue-display-board" aria-label="Clinic queue display board">
            <div class="queue-board-grid">
                <div class="queue-board-left">
                    <div class="board-section">
                        <h2 class="board-label">Next</h2>

                        <?php if ($nextPatient): ?>
                            <?php renderQueueDisplayPatient($nextPatient, 'next', $baseUrl, $queueUrl); ?>
                        <?php else: ?>
                            <div class="board-empty">No next patient.</div>
                        <?php endif; ?>
                    </div>

                    <div class="board-section">
                        <h2 class="board-label">Waiting...</h2>

                        <?php if (empty($waitingQueue)): ?>
                            <div class="board-empty">No waiting patients.</div>
                        <?php else: ?>
                            <?php foreach ($waitingQueue as $appt): ?>
                                <?php renderQueueDisplayPatient($appt, 'waiting', $baseUrl, $queueUrl); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="queue-board-right">
                    <div class="board-section">
                        <h2 class="board-label">In Progress</h2>

                        <?php if (empty($inProgress)): ?>
                            <div class="board-empty">No treatment in progress.</div>
                        <?php else: ?>
                            <?php foreach ($inProgress as $appt): ?>
                                <?php renderQueueDisplayPatient($appt, 'progress', $baseUrl, $queueUrl); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <button type="button" class="board-expand" id="boardExpandBtn" title="Toggle full screen">
                ⛶
            </button>
        </section>

        <section class="completed-section">
            <div class="completed-head">
                <h2 class="completed-title">Completed</h2>
                <p class="completed-subtitle">Appointments completed today.</p>
            </div>

            <div class="completed-list">
                <?php if (empty($completed)): ?>
                    <div class="empty-state">No completed appointments yet.</div>
                <?php else: ?>
                    <?php foreach ($completed as $appt): ?>
                        <?php
                            $appointmentId = (int) ($appt['appointment_id'] ?? 0);
                            $appointmentCode = queueAppointmentCode($appt);
                            $patientName = queuePatientName($appt);
                            $serviceName = queueServiceName($appt);
                            $timeLabel = queueTimeLabel($appt);
                            $dentistName = queueDentistName($appt);
                        ?>

                        <article class="completed-item">
                            <div>
                                <span class="completed-name"><?= e($patientName) ?></span>
                                <span class="completed-meta">
                                    <?= e($appointmentCode) ?>
                                    ·
                                    <?= e($serviceName) ?>
                                    ·
                                    <?= e(queueCompletedTime($appt)) ?>
                                    ·
                                    <?= e($dentistName) ?>
                                </span>
                            </div>

                            <button
                                type="button"
                                class="completed-link queue-open-details"
                                data-appointment-id="<?= e((string) $appointmentId) ?>"
                                data-code="<?= e($appointmentCode) ?>"
                                data-patient="<?= e($patientName) ?>"
                                data-service="<?= e($serviceName) ?>"
                                data-time="<?= e($timeLabel) ?>"
                                data-dentist="<?= e($dentistName) ?>"
                                data-status="Completed"
                            >
                                View Details
                            </button>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<div class="queue-modal-backdrop" id="queueDetailsModal" aria-hidden="true">
    <div class="queue-modal" role="dialog" aria-modal="true" aria-labelledby="queueModalTitle">
        <div class="queue-modal-header">
            <div>
                <h2 class="queue-modal-title" id="queueModalTitle">Appointment Details</h2>
                <p class="queue-modal-subtitle">Clinic queue appointment information</p>
            </div>

            <button type="button" class="queue-modal-close" data-close-queue-modal aria-label="Close">
                ×
            </button>
        </div>

        <div class="queue-modal-body">
            <div class="queue-detail-row">
                <span class="queue-detail-label">Appointment No.</span>
                <span class="queue-detail-value" id="modalAppointmentCode">—</span>
            </div>

            <div class="queue-detail-row">
                <span class="queue-detail-label">Patient</span>
                <span class="queue-detail-value" id="modalPatientName">—</span>
            </div>

            <div class="queue-detail-row">
                <span class="queue-detail-label">Service</span>
                <span class="queue-detail-value" id="modalServiceName">—</span>
            </div>

            <div class="queue-detail-row">
                <span class="queue-detail-label">Schedule</span>
                <span class="queue-detail-value" id="modalSchedule">—</span>
            </div>

            <div class="queue-detail-row">
                <span class="queue-detail-label">Dentist</span>
                <span class="queue-detail-value" id="modalDentist">—</span>
            </div>

            <div class="queue-detail-row">
                <span class="queue-detail-label">Status</span>
                <span class="queue-detail-status" id="modalStatus">—</span>
            </div>
        </div>

        <div class="queue-modal-footer"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const page = document.getElementById('queuePage');
    const expandBtn = document.getElementById('boardExpandBtn');

    const modal = document.getElementById('queueDetailsModal');
    const openButtons = document.querySelectorAll('.queue-open-details');
    const closeButtons = document.querySelectorAll('[data-close-queue-modal]');

    const modalAppointmentCode = document.getElementById('modalAppointmentCode');
    const modalPatientName = document.getElementById('modalPatientName');
    const modalServiceName = document.getElementById('modalServiceName');
    const modalSchedule = document.getElementById('modalSchedule');
    const modalDentist = document.getElementById('modalDentist');
    const modalStatus = document.getElementById('modalStatus');

    if (page && expandBtn) {
        expandBtn.addEventListener('click', function () {
            page.classList.toggle('is-fullscreen');

            if (page.classList.contains('is-fullscreen')) {
                expandBtn.textContent = '×';
                return;
            }

            expandBtn.textContent = '⛶';
        });
    }

    function setText(element, value) {
        if (!element) {
            return;
        }

        const cleanValue = String(value || '').trim();
        element.textContent = cleanValue !== '' ? cleanValue : '—';
    }

    function openQueueModal(button) {
        if (!modal || !button) {
            return;
        }

        setText(modalAppointmentCode, button.dataset.code);
        setText(modalPatientName, button.dataset.patient);
        setText(modalServiceName, button.dataset.service);
        setText(modalSchedule, button.dataset.time);
        setText(modalDentist, button.dataset.dentist);
        setText(modalStatus, button.dataset.status);

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('queue-modal-open');
    }

    function closeQueueModal() {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('queue-modal-open');
    }

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            openQueueModal(button);
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', closeQueueModal);
    });

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeQueueModal();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeQueueModal();
        }
    });
});
</script>

<?php
$staffContent = ob_get_clean();
$pageTitle = 'Clinic Queue';
require __DIR__ . '/../layouts/app.php';
?>