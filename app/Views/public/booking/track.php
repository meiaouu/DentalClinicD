<?php

use App\Core\Csrf;

$pageTitle = 'Track Appointment Request';

$errors = $errors ?? [];
$old = $old ?? [];
$result = $result ?? null;
$answers = $answers ?? [];
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

$baseUrl = '/DentalClinic/public';

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('trackDate')) {
    function trackDate($date): string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return 'N/A';
        }

        $time = strtotime($date);

        return $time ? date('M d, Y', $time) : $date;
    }
}

if (!function_exists('trackTime')) {
    function trackTime($time): string
    {
        $time = trim((string) $time);

        if ($time === '') {
            return 'N/A';
        }

        $parsed = strtotime($time);

        return $parsed ? date('h:i A', $parsed) : $time;
    }
}

if (!function_exists('trackStatusText')) {
    function trackStatusText($status): string
    {
        $status = trim((string) $status);

        if ($status === '') {
            return 'N/A';
        }

        return ucwords(str_replace('_', ' ', $status));
    }
}

if (!function_exists('trackPatientName')) {
    function trackPatientName(array $row): string
    {
        $patientName = trim((string) (
            ($row['patient_first_name'] ?? '') . ' ' .
            ($row['patient_middle_name'] ?? '') . ' ' .
            ($row['patient_last_name'] ?? '')
        ));

        if ($patientName !== '') {
            return $patientName;
        }

        $guestName = trim((string) (
            ($row['guest_first_name'] ?? '') . ' ' .
            ($row['guest_middle_name'] ?? '') . ' ' .
            ($row['guest_last_name'] ?? '')
        ));

        return $guestName !== '' ? $guestName : 'Unknown Patient';
    }
}

if (!function_exists('trackDentistName')) {
    function trackDentistName(array $row): string
    {
        $name = trim((string) (
            ($row['dentist_first_name'] ?? '') . ' ' .
            ($row['dentist_middle_name'] ?? '') . ' ' .
            ($row['dentist_last_name'] ?? '')
        ));

        return $name !== '' ? 'Dr. ' . $name : 'To be assigned';
    }
}

if (!function_exists('trackServiceName')) {
   if (!function_exists('trackServiceName')) {
    function trackServiceName(array $row): string
    {
        $normalizeServiceList = static function ($value): string {
            if (is_array($value)) {
                $items = [];

                foreach ($value as $item) {
                    if (is_array($item)) {
                        $serviceName = trim((string) (
                            $item['service_name']
                            ?? $item['name']
                            ?? $item['label']
                            ?? ''
                        ));

                        if ($serviceName !== '') {
                            $items[] = $serviceName;
                        }

                        continue;
                    }

                    $serviceName = trim((string) $item);

                    if ($serviceName !== '') {
                        $items[] = $serviceName;
                    }
                }

                return implode(', ', array_unique($items));
            }

            return trim((string) $value);
        };

        if (!empty($row['service_names'])) {
            $serviceNames = $normalizeServiceList($row['service_names']);

            if ($serviceNames !== '') {
                return $serviceNames;
            }
        }

        if (!empty($row['services'])) {
            $serviceNames = $normalizeServiceList($row['services']);

            if ($serviceNames !== '') {
                return $serviceNames;
            }
        }

        if (!empty($row['service_name'])) {
            $serviceName = $normalizeServiceList($row['service_name']);

            if ($serviceName !== '') {
                return $serviceName;
            }
        }

        return 'N/A';
    }
}
}

if (!function_exists('trackRequestContact')) {
    function trackRequestContact(array $row, array $old): string
    {
        return trim((string) (
            $row['guest_contact_number']
            ?? $row['patient_contact_number']
            ?? $row['contact_number']
            ?? $old['contact_number']
            ?? ''
        ));
    }
}

if (!function_exists('trackCurrentStep')) {
    function trackCurrentStep(string $status): int
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'pending' => 1,
            'under_review', 'rescheduled' => 2,
            'confirmed', 'approved', 'checked_in', 'in_progress' => 3,
            'completed' => 4,
            'cancelled', 'rejected', 'no_show' => 5,
            default => 1,
        };
    }
}

$status = $result ? strtolower(trim((string) ($result['request_status'] ?? 'pending'))) : '';
$currentStep = trackCurrentStep($status);

$isStopped = in_array($status, ['cancelled', 'rejected', 'no_show'], true);
$canCancel = $result && in_array($status, ['pending', 'under_review', 'rescheduled'], true);

$queryRequestCode = trim((string) ($_GET['request_code'] ?? ''));
$requestCodeValue = (string) ($old['request_code'] ?? ($result['request_code'] ?? $queryRequestCode));
$contactValue = (string) ($old['contact_number'] ?? trackRequestContact(is_array($result) ? $result : [], $old));

$timelineSteps = [
    [
        'step' => 1,
        'title' => 'Request Submitted',
        'date' => trackDate($result['created_at'] ?? ''),
        'message' => 'Your appointment request was received by the clinic.',
    ],
    [
        'step' => 2,
        'title' => 'Clinic Review',
        'date' => trackDate($result['updated_at'] ?? ''),
        'message' => 'The clinic staff will review your selected service, date, and time.',
    ],
    [
        'step' => 3,
        'title' => 'Appointment Confirmation',
        'date' => trackDate($result['appointment_date'] ?? $result['preferred_date'] ?? ''),
        'message' => 'Your appointment will be confirmed once the clinic approves the schedule.',
    ],
    [
        'step' => 4,
        'title' => 'Clinic Visit',
        'date' => trackDate($result['appointment_date'] ?? $result['preferred_date'] ?? ''),
        'message' => 'Please arrive on time once your appointment is confirmed.',
    ],
];

if ($isStopped) {
    $timelineSteps[] = [
        'step' => 5,
        'title' => trackStatusText($status),
        'date' => trackDate($result['updated_at'] ?? ''),
        'message' => 'This appointment request is no longer active.',
    ];
}

ob_start();
?>

<style>
.track-page,
.track-page * {
    box-sizing: border-box;
}

.track-page {
    min-height: 100vh;
    background: #ffffff;
    color: #111827;
    font-family: Arial, sans-serif;
}

.track-topbar {
    height: 34px;
  
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 0 0 0;
    background: #ffffff;
}

.track-topbar-title {
    padding-left: 4px;
    color: #1f2937;
    font-size: 13px;
    font-weight: 400;
}

.track-back {
    min-width: 70px;
    height: 34px;
    border: 0;
    background: #243447;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.track-back:hover {
    background: #111827;
}

.track-shell {
    width: min(620px, calc(100% - 28px));
    margin: 0 auto;
    padding: 10px 0 44px;
}

.track-title {
    margin: 0;
    color: #6b7280;
    text-align: center;
    font-size: 26px;
    font-weight: 400;
    line-height: 1.2;
}

.track-subtitle {
    margin: 4px 0 12px;
    color: #6b7280;
    text-align: center;
    font-size: 11px;
    font-weight: 700;
}

.track-form-card {
    width: 100%;
    margin: 0 auto 18px;
    padding: 12px;
    border: 1px solid #d1d5db;
    background: #ffffff;
}

.track-form {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 8px;
    align-items: end;
}

.form-label {
    display: block;
    margin-bottom: 4px;
    color: #6b7280;
    font-size: 11px;
    font-weight: 700;
}

.form-control,
.form-textarea {
    width: 100%;
    min-height: 34px;
    border: 1px solid #cfd4dc;
    background: #ffffff;
    color: #111827;
    padding: 7px 9px;
    font-size: 12px;
    font-family: Arial, sans-serif;
    outline: none;
}

.form-control:focus,
.form-textarea:focus {
    border-color: #4dbd7a;
}

.form-textarea {
    min-height: 76px;
    resize: vertical;
    line-height: 1.5;
}

.form-error {
    display: block;
    margin-top: 4px;
    color: #b91c1c;
    font-size: 11px;
    font-weight: 700;
}

.track-btn,
.track-btn-secondary,
.track-btn-danger {
    min-height: 34px;
    padding: 0 13px;
    border: 1px solid transparent;
    font-size: 12px;
    font-weight: 700;
    font-family: Arial, sans-serif;
    text-decoration: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.track-btn {
    background: #4dbd7a;
    border-color: #4dbd7a;
    color: #ffffff;
}

.track-btn:hover {
    background: #35a966;
    border-color: #35a966;
}

.track-btn-secondary {
    background: #ffffff;
    border-color: #cfd4dc;
    color: #374151;
}

.track-btn-secondary:hover {
    background: #f3f4f6;
}

.track-btn-danger {
    background: #b91c1c;
    border-color: #b91c1c;
    color: #ffffff;
}

.track-btn-danger:hover {
    background: #991b1b;
    border-color: #991b1b;
}

.flash-box {
    width: 100%;
    margin: 0 auto 10px;
    padding: 9px 11px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    font-size: 12px;
    font-weight: 700;
}

.flash-box.success {
    color: #166534;
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.flash-box.error {
    color: #991b1b;
    background: #fef2f2;
    border-color: #fecaca;
}

.track-status-bar {
    width: 100%;
    height: 25px;
    margin: 0 auto 14px;
    background: #4dbd7a;
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    justify-content: center;
}

.track-status-bar.stopped {
    background: #b91c1c;
}

.track-layout {
    width: 100%;
    margin: 0 auto;
}

.timeline {
    position: relative;
    padding: 0 0 0 48px;
}

.timeline::before {
    content: "";
    position: absolute;
    top: 13px;
    bottom: 18px;
    left: 23px;
    width: 1px;
    background: #d1d5db;
}

.timeline-item {
    position: relative;
    margin-bottom: 16px;
}

.timeline-dot {
    position: absolute;
    top: 10px;
    left: -32px;
    width: 20px;
    height: 20px;
    border-radius: 999px;
    background: #d1d5db;
    display: flex;
    align-items: center;
    justify-content: center;
}

.timeline-dot::after {
    content: "";
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: #ffffff;
}

.timeline-item.done .timeline-dot,
.timeline-item.active .timeline-dot {
    background: #4dbd7a;
}

.timeline-item.stopped .timeline-dot {
    background: #b91c1c;
}

.timeline-card {
    min-height: 64px;
    border: 1px solid #cfd4dc;
    background: #ffffff;
    padding: 9px 12px;
}

.timeline-meta {
    margin-bottom: 4px;
    color: #9ca3af;
    font-size: 10px;
    font-weight: 700;
}

.timeline-title {
    margin: 0 0 3px;
    color: #111827;
    font-size: 12px;
    font-weight: 800;
}

.timeline-message {
    margin: 0;
    color: #111827;
    font-size: 11px;
    line-height: 1.35;
    font-weight: 600;
}

.details-card {
    margin: 6px 0 16px 48px;
    border: 1px solid #cfd4dc;
    background: #ffffff;
}

.details-header {
    padding: 10px 12px;
    border-bottom: 1px solid #e5e7eb;
}

.details-title {
    margin: 0;
    color: #111827;
    font-size: 13px;
    font-weight: 800;
}

.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
}

.details-item {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f5f9;
}

.details-item:nth-child(odd) {
    border-right: 1px solid #f1f5f9;
}

.details-item.full {
    grid-column: 1 / -1;
    border-right: 0;
}

.details-label {
    display: block;
    margin-bottom: 3px;
    color: #6b7280;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
}

.details-value {
    color: #111827;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.45;
    word-break: break-word;
}

.answers-card,
.cancel-card {
    margin: 12px 0 0 48px;
    border: 1px solid #cfd4dc;
    background: #ffffff;
}

.answers-header,
.cancel-header {
    padding: 10px 12px;
    border-bottom: 1px solid #e5e7eb;
}

.answers-title,
.cancel-title {
    margin: 0;
    font-size: 13px;
    font-weight: 800;
    color: #111827;
}

.answer-row {
    display: grid;
    grid-template-columns: 170px 1fr;
    gap: 8px;
    padding: 9px 12px;
    border-bottom: 1px solid #f1f5f9;
}

.answer-row:last-child {
    border-bottom: 0;
}

.answer-question {
    color: #6b7280;
    font-size: 11px;
    font-weight: 800;
}

.answer-value {
    color: #111827;
    font-size: 11px;
    font-weight: 700;
}

.cancel-body {
    padding: 12px;
}

.cancel-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 8px;
}

.track-footer-actions {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 18px;
}

.track-empty {
    margin-top: 14px;
    border: 1px dashed #cfd4dc;
    padding: 20px;
    text-align: center;
    color: #6b7280;
    font-size: 12px;
    font-weight: 700;
}

.track-result {
    scroll-margin-top: 18px;
}

.track-result.is-visible {
    animation: trackPop 0.22s ease both;
}

@keyframes trackPop {
    from {
        opacity: 0;
        transform: translateY(-6px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 700px) {
    .track-shell {
        width: min(100% - 22px, 620px);
    }

    .track-title {
        font-size: 22px;
    }

    .track-form {
        grid-template-columns: 1fr;
    }

    .timeline {
        padding-left: 38px;
    }

    .timeline::before {
        left: 18px;
    }

    .timeline-dot {
        left: -29px;
    }

    .details-card,
    .answers-card,
    .cancel-card {
        margin-left: 38px;
    }

    .details-grid {
        grid-template-columns: 1fr;
    }

    .details-item:nth-child(odd) {
        border-right: 0;
    }

    .answer-row {
        grid-template-columns: 1fr;
    }

    .track-footer-actions {
        display: grid;
        grid-template-columns: 1fr;
    }

    .track-btn-secondary,
    .track-btn-danger,
    .track-btn {
        width: 100%;
    }
}
</style>

<div class="track-page">
    <div class="track-topbar">
       
        <a href="<?= e($baseUrl . '/') ?>" class="track-back">Back</a>
    </div>

    <div class="track-shell">
        <h1 class="track-title">Appointment Tracking</h1>
        <p class="track-subtitle">We support appointment request tracking.</p>

        <?php if ($flash_success): ?>
            <div class="flash-box success"><?= e((string) $flash_success) ?></div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="flash-box error"><?= e((string) $flash_error) ?></div>
        <?php endif; ?>

        <div class="track-form-card">
            <form method="POST" action="<?= e($baseUrl . '/track-request/search#trackResultPanel') ?>" class="track-form">
                <?= Csrf::inputField(); ?>

                <div>
                    <label class="form-label" for="request_code">Request Code</label>
                    <input
                        class="form-control"
                        type="text"
                        id="request_code"
                        name="request_code"
                        value="<?= e($requestCodeValue) ?>"
                        placeholder="REQ-YYYYMMDD-XXXX"
                        autocomplete="off"
                        required
                    >

                    <?php if (!empty($errors['request_code'])): ?>
                        <span class="form-error"><?= e($errors['request_code'][0]) ?></span>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="form-label" for="contact_number">Mobile Number</label>
                    <input
                        class="form-control"
                        type="text"
                        id="contact_number"
                        name="contact_number"
                        value="<?= e($contactValue) ?>"
                        placeholder="09XXXXXXXXX"
                        autocomplete="tel"
                        required
                    >

                    <?php if (!empty($errors['contact_number'])): ?>
                        <span class="form-error"><?= e($errors['contact_number'][0]) ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="track-btn">Track Request</button>
            </form>
        </div>

        <?php if ($result): ?>
            <div class="track-result is-visible" id="trackResultPanel" tabindex="-1">
                <div class="track-status-bar <?= $isStopped ? 'stopped' : '' ?>">
                    <?= e(trackStatusText($status)) ?>
                </div>

                <div class="track-layout">
                    <div class="timeline">
                        <?php foreach ($timelineSteps as $item): ?>
                            <?php
                                $stepNo = (int) $item['step'];
                                $itemClass = '';

                                if ($isStopped && $stepNo === 5) {
                                    $itemClass = 'stopped active';
                                } elseif (!$isStopped && $stepNo < $currentStep) {
                                    $itemClass = 'done';
                                } elseif (!$isStopped && $stepNo === $currentStep) {
                                    $itemClass = 'active';
                                } elseif ($isStopped && $stepNo < 5) {
                                    $itemClass = 'done';
                                }
                            ?>

                            <div class="timeline-item <?= e($itemClass) ?>">
                                <div class="timeline-dot"></div>

                                <div class="timeline-card">
                                    <div class="timeline-meta">
                                        Appointment Step <?= (int) $stepNo ?> · <?= e((string) $item['date']) ?>
                                    </div>

                                    <h2 class="timeline-title"><?= e((string) $item['title']) ?></h2>
                                    <p class="timeline-message"><?= e((string) $item['message']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="details-card">
                        <div class="details-header">
                            <h2 class="details-title">Request Details</h2>
                        </div>

                        <div class="details-grid">
                            <div class="details-item">
                                <span class="details-label">Request Code</span>
                                <div class="details-value"><?= e((string) ($result['request_code'] ?? 'N/A')) ?></div>
                            </div>

                            <div class="details-item">
                                <span class="details-label">Patient</span>
                                <div class="details-value"><?= e(trackPatientName($result)) ?></div>
                            </div>

                            <div class="details-item">
                                <span class="details-label">Service</span>
                                <div class="details-value"><?= e(trackServiceName($result)) ?></div>
                            </div>

                            <div class="details-item">
                                <span class="details-label">Dentist</span>
                                <div class="details-value"><?= e(trackDentistName($result)) ?></div>
                            </div>

                            <div class="details-item">
                                <span class="details-label">Preferred Date</span>
                                <div class="details-value"><?= e(trackDate($result['preferred_date'] ?? '')) ?></div>
                            </div>

                            <div class="details-item">
                                <span class="details-label">Preferred Time</span>
                                <div class="details-value"><?= e(trackTime($result['preferred_start_time'] ?? '')) ?></div>
                            </div>

                            <div class="details-item full">
                                <span class="details-label">Notes</span>
                                <div class="details-value">
                                    <?= nl2br(e((string) ($result['notes'] ?? 'No notes provided.'))) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($answers)): ?>
                        <div class="answers-card">
                            <div class="answers-header">
                                <h2 class="answers-title">Service Answers</h2>
                            </div>

                            <?php foreach ($answers as $answer): ?>
                                <div class="answer-row">
                                    <div class="answer-question">
                                        <?= e((string) ($answer['option_name'] ?? 'Question')) ?>
                                    </div>

                                    <div class="answer-value">
                                        <?= e((string) ($answer['value_label'] ?? $answer['answer_text'] ?? '')) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($canCancel): ?>
                        <div class="cancel-card">
                            <div class="cancel-header">
                                <h2 class="cancel-title">Cancel Request</h2>
                            </div>

                            <div class="cancel-body">
                                <form method="POST" action="<?= e($baseUrl . '/track-request/cancel') ?>">
                                    <?= Csrf::inputField(); ?>

                                    <input type="hidden" name="request_code" value="<?= e((string) ($result['request_code'] ?? '')) ?>">
                                    <input type="hidden" name="contact_number" value="<?= e(trackRequestContact($result, $old)) ?>">

                                    <label class="form-label" for="reason">Reason</label>
                                    <textarea
                                        class="form-textarea"
                                        id="reason"
                                        name="reason"
                                        rows="3"
                                        placeholder="Optional reason for cancellation"
                                    ></textarea>

                                    <div class="cancel-actions">
                                        <button
                                            type="submit"
                                            class="track-btn-danger"
                                            onclick="return confirm('Are you sure you want to cancel this request?');"
                                        >
                                            Cancel Pending Request
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="track-empty">
                Enter your request code and mobile number above to view your appointment tracking progress.
            </div>
        <?php endif; ?>

        <div class="track-footer-actions">
            <a class="track-btn-secondary" href="<?= e($baseUrl . '/?open_booking=1') ?>">
    Book Appointment
</a>

            <a class="track-btn-secondary" href="<?= e($baseUrl . '/') ?>">
                Back to Home
            </a>
        </div>
    </div>
</div>

<?php if ($result): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const resultPanel = document.getElementById('trackResultPanel');

    if (!resultPanel) {
        return;
    }

    window.setTimeout(function () {
        resultPanel.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

        resultPanel.focus({
            preventScroll: true
        });
    }, 120);
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title = 'Track Appointment Request';

require __DIR__ . '/../../layouts/main.php';