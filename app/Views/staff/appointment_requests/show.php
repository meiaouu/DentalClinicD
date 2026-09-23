<?php

use App\Core\Csrf;

$requestItem = $requestItem ?? ($request ?? []);
$dentists = $dentists ?? [];
$answers = $answers ?? [];
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('displayText')) {
    function displayText($value, string $fallback = 'N/A'): string
    {
        $text = trim((string) $value);
        return $text !== '' ? e($text) : e($fallback);
    }
}

if (!function_exists('requestStatusClass')) {
    function requestStatusClass(string $status): string
    {
        $status = strtolower(trim($status));
        return preg_replace('/[^a-z0-9_-]/', '', $status) ?: 'pending';
    }
}

if (!function_exists('requestNiceDateTimeDate')) {
    function requestNiceDateTimeDate(?string $date): string
    {
        $date = trim((string) $date);
        if ($date === '') return '';
        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d', $timestamp) : $date;
    }
}

if (!function_exists('requestNiceDateTimeDateLong')) {
    function requestNiceDateTimeDateLong(?string $date): string
    {
        $date = trim((string) $date);
        if ($date === '') return '';
        $timestamp = strtotime($date);
        return $timestamp ? date('F j, Y', $timestamp) : $date;
    }
}

if (!function_exists('requestNiceDateTimeTime')) {
    function requestNiceDateTimeTime(?string $time): string
    {
        $time = trim((string) $time);
        if ($time === '') return '';
        $timestamp = strtotime($time);
        return $timestamp ? date('h:i A', $timestamp) : $time;
    }
}

if (!function_exists('requestServiceText')) {
    function requestServiceText(array $request): string
    {
        if (!empty($request['service_names']) && is_array($request['service_names'])) {
            $names = array_filter(array_map('strval', $request['service_names']));
            $text = implode(', ', $names);
            if (trim($text) !== '') return $text;
        }

        if (!empty($request['service_names_text'])) return (string) $request['service_names_text'];
        if (!empty($request['services_text'])) return (string) $request['services_text'];
        if (!empty($request['service_name'])) return (string) $request['service_name'];
        if (!empty($request['name'])) return (string) $request['name'];
        if (!empty($request['service'])) return (string) $request['service'];

        return '';
    }
}

if (!function_exists('requestServiceList')) {
    function requestServiceList(array $request): array
    {
        if (!empty($request['service_names']) && is_array($request['service_names'])) {
            return array_values(array_filter(array_map('strval', $request['service_names'])));
        }

        $serviceText = requestServiceText($request);

        if ($serviceText === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $serviceText))));
    }
}

$requestName = trim((string) (
    ($requestItem['guest_first_name'] ?? $requestItem['patient_first_name'] ?? $requestItem['first_name'] ?? '') . ' ' .
    ($requestItem['guest_middle_name'] ?? $requestItem['patient_middle_name'] ?? $requestItem['middle_name'] ?? '') . ' ' .
    ($requestItem['guest_last_name'] ?? $requestItem['patient_last_name'] ?? $requestItem['last_name'] ?? '')
));

if ($requestName === '') {
    $requestName = 'Unnamed Request';
}

$requestStatus = strtolower(trim((string) ($requestItem['request_status'] ?? 'pending')));
$requestStatusClass = requestStatusClass($requestStatus);

$isGuest = empty($requestItem['patient_id']) || !empty($requestItem['is_guest']);

$requestId = (int) ($requestItem['request_id'] ?? 0);
$serviceId = (int) ($requestItem['service_id'] ?? 0);

$preferredDate = (string) ($requestItem['preferred_date'] ?? '');
$preferredStartTime = (string) ($requestItem['preferred_start_time'] ?? '');

$serviceText = requestServiceText($requestItem);
$serviceList = requestServiceList($requestItem);

$contactNumber = (string) (
    $requestItem['guest_contact_number']
    ?? $requestItem['contact_number']
    ?? $requestItem['patient_contact_number']
    ?? ''
);

$email = (string) (
    $requestItem['guest_email']
    ?? $requestItem['email']
    ?? $requestItem['patient_email']
    ?? ''
);

$requestNotes = (string) (
    $requestItem['notes']
    ?? $requestItem['request_notes']
    ?? ''
);

$requestSex = (string) ($requestItem['sex'] ?? $requestItem['patient_sex'] ?? '');
$requestBirthDate = (string) ($requestItem['birth_date'] ?? $requestItem['patient_birth_date'] ?? '');
$requestCivilStatus = (string) ($requestItem['civil_status'] ?? $requestItem['patient_civil_status'] ?? '');
$requestOccupation = (string) ($requestItem['occupation'] ?? $requestItem['patient_occupation'] ?? '');
$requestAddress = (string) ($requestItem['address'] ?? $requestItem['patient_address'] ?? '');

$requestAge = 'N/A';

if ($requestBirthDate !== '') {
    try {
        $today = new DateTime();
        $birth = new DateTime($requestBirthDate);
        $requestAge = (string) $today->diff($birth)->y;
    } catch (Throwable $exception) {
        $requestAge = 'N/A';
    }
}

$canConfirmOrReject = in_array($requestStatus, ['pending', 'under_review', 'rescheduled'], true);
$canReschedule = in_array($requestStatus, ['pending', 'under_review', 'rescheduled', 'confirmed'], true);

ob_start();
?>



<style>
.rp,
.rp *,
.rp *::before,
.rp *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

.rp {


    --mint:        #e8f5f0;
    --mint-mid:    #c4e4d8;
    --teal:        #1a7a5e;
    --teal-dark:   #145f49;
    --teal-light:  #d0efe5;
    --cream:       #faf9f6;
    --warm-white:  #ffffff;
    --border:      #e4e0d8;
    --border-dark: #ccc9bf;
    --text-main:   #1c1c1a;
    --text-mid:    #4a4a45;
    --text-muted:  #8a8a82;
    --text-light:  #aeada5;
    --red:         #c0392b;
    --red-bg:      #fdf2f1;
    --red-border:  #f0c0bb;
    --amber:       #92600a;
    --amber-bg:    #fdf6ec;
    --amber-border:#e8d0a0;
    --green:       #166534;
    --green-bg:    #f0fdf6;
    --green-border:#a7f3d0;
    --blue:        #1e40af;
    --blue-bg:     #eff6ff;
    --blue-border: #bfdbfe;
    --radius-sm:   1px;
    --radius-md:   1px;
    --radius-lg:   1px;
    --shadow-sm:   0 1px 3px rgba(0,0,0,.06);
    --shadow-md:   0 4px 16px rgba(0,0,0,.08);
 
 
}

.rp-inner {
    max-width: 1180px;
    margin: 0 auto;
}

.rp .alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 16px;
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 20px;
    border: 1px solid;
}
.rp{
    background-color: #f5f5f700;
}

.rp .alert.success {
    background: var(--green-bg);
    border-color: var(--green-border);
    color: var(--green);
}

.rp .alert.error {
    background: var(--red-bg);
    border-color: var(--red-border);
    color: var(--red);
}

.rp .alert svg {
    flex-shrink: 0;
    margin-top: 1px;
}

.rp .layout-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 5px;
    align-items: start;
}

.rp .card {
    background: var(--warm-white);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.rp .card + .card {
    margin-top: 8px;
}

.rp .card-header {
    padding: 18px 22px 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.rp .card-header-icon {
    width: 34px;
    height: 34px;
    border-radius: var(--radius-sm);
    background: var(--mint);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.rp .card-header-icon svg {
    color: var(--teal);
}

.rp .card-title {
    font-family: var(--serif);
    font-size: 17px;
    color: var(--text-main);
    font-weight: 400;
    letter-spacing: -.01em;
}

.rp .card-body {
    padding: 20px 22px;
}

.rp .hero-card {
    background: linear-gradient(135deg, #ffffff 0%, #ffffff 100%);
    border-color: transparent;
    border-radius: var(--radius-lg);
    padding: 50px 26px;
    box-shadow: var(--shadow-md);
    color: #000000;
    margin-bottom: 10px;
    position: relative;
    overflow: hidden;
}

.rp .hero-main {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
}

.rp .hero-left {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    min-width: 0;
}

.rp .hero-info {
    min-width: 0;
}

.rp .hero-name {
    font-family: var(--serif);
    font-size: 30px;
    font-weight: 400;
    color: #000000;
    line-height: 1.15;
    margin: 0 0 8px;
    word-break: break-word;
}

.rp .hero-code {
    font-size: 12px;
    color: rgba(0, 0, 0, 0.65);
    margin: 0;
    font-family: 'Courier New', monospace;
    letter-spacing: 0.04em;
}

.rp .hero-badges {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 3px;
}

.rp .back-icon-btn {
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    border-radius: 1px;
    background: var(--warm-white);
    color: var(--text-mid);
    text-decoration: none;
    transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease, transform 0.15s ease;
}

.rp .back-icon-btn svg {
    width: 20px;
    height: 20px;
}

.rp .back-icon-btn svg path {
    fill: none;
    stroke: currentColor;
    stroke-width: 2.4;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.rp .back-icon-btn:hover {
    border-color: var(--teal);
    background: var(--mint);
    color: var(--teal);
    transform: translateX(-2px);
}

.rp .badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .02em;
}

.rp .badge-status {
    background: rgba(255, 255, 255, 0.42);
    color: #000000;
    backdrop-filter: blur(4px);
    text-transform: capitalize;
}

.rp .badge-status.pending {
    background: #ffea81;
}

.rp .badge-status.confirmed {
    background: rgb(74, 222, 128);
}

.rp .badge-status.rejected,
.rp .badge-status.cancelled,
.rp .badge-status.cancelled_by_patient {
    background: rgba(248, 113, 113, 0.9);
}

.rp .badge-type {
    background: rgba(255,255,255,.12);
    color: rgba(0, 0, 0, 0.85);
}

.rp .badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    opacity: .8;
}

.rp .appt-strip {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0;
}

.rp .appt-strip-item {
    padding: 18px 20px;
    border-right: 1px solid var(--border);
}

.rp .appt-strip-item:last-child {
    border-right: none;
}

.rp .appt-strip-label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.rp .appt-strip-label svg {
    color: var(--teal);
}

.rp .appt-strip-value {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-main);
    line-height: 1.3;
}

.rp .appt-strip-sub {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

.rp .service-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 4px;
}

.rp .service-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 1px;
    font-size: 12px;
    font-weight: 500;
    background: #e1e1e1;
    color: black;
}

.rp .info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0;
}

.rp .info-cell {
    padding: 5px 16px;
    border-bottom: 1px solid var(--border);
    border-right: 1px solid var(--border);
}

.rp .info-cell:nth-child(even) {
    border-right: none;
}

.rp .info-cell.full {
    grid-column: 1 / -1;
    border-right: none;
}

.rp .info-cell:last-child,
.rp .info-cell:nth-last-child(2):not(.full) {
    border-bottom: none;
}

.rp .info-cell.full:last-child {
    border-bottom: none;
}

.rp .info-label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.rp .info-label svg {
    color: var(--teal);
    opacity: .7;
}

.rp .info-value {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-main);
    line-height: 1.45;
    word-break: break-word;
}

.rp .info-value.muted {
    font-weight: 400;
    color: var(--text-mid);
    font-size: 13px;
}

.rp .info-value.na {
    color: var(--text-light);
    font-style: italic;
    font-weight: 400;
}

.rp .patient-header {
    display: flex;
    align-items: center;
    gap: 1px;
    padding: 10px 22px;
    border-bottom: 1px solid var(--border);
}

.rp .request-detail-sidebar {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.rp .confirm-card {
    border-color: var(--green-border);
    background: linear-gradient(180deg, #effff7 0%, #e2ffef 100%);
}

.rp .confirm-card .card-header {
    border-bottom-color: var(--green-border);
}

.rp .confirm-card .card-header-icon {
    background: var(--green-bg);
}

.rp .confirm-card .card-header-icon svg {
    color: var(--green);
}

.rp .form-group {
    margin-bottom: 14px;
}

.rp .form-group:last-child {
    margin-bottom: 0;
}

.rp .form-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-mid);
    margin-bottom: 6px;
    letter-spacing: .01em;
}

.rp .form-label svg {
    color: var(--teal);
}

.rp .form-control {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid var(--border-dark);
    border-radius: var(--radius-sm);
    font-family: var(--sans);
    font-size: 14px;
    color: var(--text-main);
    background: var(--warm-white);
    transition: border-color .15s, box-shadow .15s;
    outline: none;
    appearance: none;
}

.rp .form-control:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 3px rgba(26,122,94,.12);
}

.rp .form-control::placeholder {
    color: var(--text-light);
}

.rp select.form-control {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238a8a82' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 32px;
    cursor: pointer;
}

.rp textarea.form-control {
    min-height: 80px;
    resize: vertical;
    line-height: 1.6;
}

.rp .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: 100%;
    padding: 10px 16px;
    border: none;
    border-radius: var(--radius-sm);
    font-family: var(--sans);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s, transform .1s, opacity .15s;
    line-height: 1;
}

.rp .btn:active:not(:disabled) {
    transform: translateY(1px);
}

.rp .btn:disabled {
    opacity: .45;
    cursor: not-allowed;
}

.rp .btn-confirm {
    background: var(--teal);
    color: #fff;
}

.rp .btn-confirm:hover:not(:disabled) {
    background: var(--teal-dark);
}

.rp .btn-reschedule {
    background: var(--text-main);
    color: #fff;
}

.rp .btn-reschedule:hover:not(:disabled) {
    background: #333;
}

.rp .btn-reject {
    background: var(--red-bg);
    color: var(--red);
    border: 1px solid var(--red-border);
}

.rp .btn-reject:hover:not(:disabled) {
    background: #fce4e1;
    border-color: #e8a09a;
}

.rp .hint {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 10px;
    line-height: 1.5;
    display: flex;
    gap: 6px;
    align-items: flex-start;
}

.rp .hint svg {
    flex-shrink: 0;
    margin-top: 1px;
    color: var(--text-light);
}

.rp .status-hint {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-weight: 500;
    margin-top: 12px;
}

.rp .status-hint.warning {
    background: var(--amber-bg);
    color: var(--amber);
    border: 1px solid var(--amber-border);
}

.rp .status-hint.info {
    background: var(--blue-bg);
    color: var(--blue);
    border: 1px solid var(--blue-border);
}

.rp .answers-list {
    display: grid;
    gap: 10px;
    list-style: none;
}

.rp .answers-list li {
    padding: 12px 16px;
    background: var(--cream);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
}

.rp .answer-question {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--teal);
    margin-bottom: 4px;
}

.rp .answer-value {
    font-size: 14px;
    color: var(--text-main);
    font-weight: 500;
}

.rp .advanced-details {
    background: var(--warm-white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    margin-top: 0;
}

.rp .advanced-summary {
    padding: 16px 22px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-mid);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    user-select: none;
    list-style: none;
}

.rp .advanced-summary::-webkit-details-marker {
    display: none;
}

.rp .advanced-summary:hover {
    color: var(--text-main);
}

.rp .advanced-summary-icon {
    width: 30px;
    height: 30px;
    border-radius: var(--radius-sm);
    background: #f5f5f2;
    display: flex;
    align-items: center;
    justify-content: center;
}

.rp .advanced-chevron {
    margin-left: auto;
    transition: transform .2s;
    color: var(--text-muted);
}

.rp details[open] .advanced-chevron {
    transform: rotate(180deg);
}

.rp .advanced-body {
    border-top: 1px solid var(--border);
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
}

.rp .advanced-section {
    padding: 22px;
}

.rp .advanced-section + .advanced-section {
    border-left: 1px solid var(--border);
}

.rp .advanced-section-title {
    font-family: var(--serif);
    font-size: 16px;
    font-weight: 400;
    color: var(--text-main);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.rp .advanced-section-title svg {
    color: var(--teal);
}

.rp #rescheduleTimeSlot:disabled {
    opacity: .6;
}

@media (max-width: 860px) {
    .rp .layout-grid {
        grid-template-columns: 1fr;
    }

    .rp .appt-strip {
        grid-template-columns: 1fr;
    }

    .rp .appt-strip-item {
        border-right: none;
        border-bottom: 1px solid var(--border);
    }

    .rp .appt-strip-item:last-child {
        border-bottom: none;
    }

    .rp .advanced-body {
        grid-template-columns: 1fr;
    }

    .rp .advanced-section + .advanced-section {
        border-left: none;
        border-top: 1px solid var(--border);
    }
}

@media (max-width: 700px) {
    .rp .hero-main {
        flex-direction: column;
    }

    .rp .hero-badges {
        justify-content: flex-start;
        padding-left: 52px;
    }

    .rp .hero-name {
        font-size: 24px;
    }
}

@media (max-width: 520px) {
    .rp {
        padding-left: 10px;
        padding-right: 10px;
    }

    .rp .info-grid {
        grid-template-columns: 1fr;
    }

    .rp .info-cell {
        border-right: none;
    }

    .rp .info-cell:nth-last-child(2) {
        border-bottom: 1px solid var(--border);
    }

    .rp .hero-name {
        font-size: 24px;
    }
}
</style>

<div class="rp">
    <div class="rp-inner">

        <?php if ($flash_success): ?>
            <div class="alert success" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                <?= e((string) $flash_success) ?>
            </div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="alert error" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <?= e((string) $flash_error) ?>
            </div>
        <?php endif; ?>

        <div class="hero-card">
            <div class="hero-main">
                <div class="hero-left">
                    <a href="/DentalClinic/public/staff/appointment-requests" class="back-icon-btn" aria-label="Back to appointment requests">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M15 6L9 12L15 18"></path>
                        </svg>
                    </a>

                    <div class="hero-info">
                        <h1 class="hero-name"><?= e($requestName) ?></h1>

                        <p class="hero-code">
                            <?php if (!empty($requestItem['request_code'])): ?>
                                # <?= e((string) $requestItem['request_code']) ?>
                            <?php else: ?>
                                Request #<?= $requestId ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="hero-badges">
                    <span class="badge badge-status <?= e($requestStatusClass) ?>">
                        <span class="badge-dot"></span>
                        <?= e(ucwords(str_replace('_', ' ', $requestStatus))) ?>
                    </span>

                    <span class="badge badge-type">
                        <?php if ($isGuest): ?>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            Guest
                        <?php else: ?>
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                            </svg>
                            Existing Patient
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="layout-grid">

            <div class="main-col">

                <div class="card">
                    <div class="appt-strip">
                        <div class="appt-strip-item">
                            <div class="appt-strip-label">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Date
                            </div>
                            <?php if ($preferredDate !== ''): ?>
                                <div class="appt-strip-value"><?= e(requestNiceDateTimeDateLong($preferredDate)) ?></div>
                                <div class="appt-strip-sub"><?= e(date('l', strtotime($preferredDate))) ?></div>
                            <?php else: ?>
                                <div class="appt-strip-value" style="color:var(--text-light);font-style:italic;font-size:14px;">Not specified</div>
                            <?php endif; ?>
                        </div>

                        <div class="appt-strip-item">
                            <div class="appt-strip-label">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                Time
                            </div>
                            <?php if ($preferredStartTime !== ''): ?>
                                <div class="appt-strip-value"><?= e(requestNiceDateTimeTime($preferredStartTime)) ?></div>
                                <div class="appt-strip-sub">Preferred time</div>
                            <?php else: ?>
                                <div class="appt-strip-value" style="color:var(--text-light);font-style:italic;font-size:14px;">Not specified</div>
                            <?php endif; ?>
                        </div>

                        <div class="appt-strip-item">
                            <div class="appt-strip-label">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                Service(s)
                            </div>
                            <?php if (!empty($serviceList)): ?>
                                <div class="service-chips">
                                    <?php foreach ($serviceList as $svc): ?>
                                        <span class="service-chip">
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                            <?= e($svc) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="appt-strip-value" style="color:var(--text-light);font-style:italic;font-size:14px;">None selected</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="patient-header"></div>

                    <div class="info-grid">

                    <div class="info-cell">
    <div class="info-label">
        Privacy Consent
    </div>

    <?php if (!empty($privacyConsent) && (int) ($privacyConsent['accepted'] ?? 0) === 1): ?>
        <div class="info-value" style="color:var(--green);font-weight:700;">
            Accepted
        </div>
        <div class="info-value muted" style="font-size:12px;margin-top:3px;">
            Version: <?= e((string) ($privacyConsent['consent_version'] ?? 'N/A')) ?><br>
            Date: <?= e((string) ($privacyConsent['created_at'] ?? 'N/A')) ?>
        </div>
    <?php else: ?>
        <div class="info-value" style="color:var(--red);font-weight:700;">
            Missing / Not logged
        </div>
    <?php endif; ?>
</div>

<div class="info-cell">
    <div class="info-label">
        Patient Portal Account
    </div>

    <?php if (!empty($requestItem['wants_patient_account'])): ?>
        <div class="info-value" style="color:var(--green);font-weight:700;">
            Requested
        </div>
        <div class="info-value muted" style="font-size:12px;margin-top:3px;">
            A pending account will be created after confirmation.
        </div>
    <?php else: ?>
        <div class="info-value na">
            Not requested
        </div>
    <?php endif; ?>
</div>
                        <div class="info-cell">
                            <div class="info-label">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.78a16 16 0 0 0 6.31 6.31l.94-.94a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7a2 2 0 0 1 1.72 2.02z"/></svg>
                                Contact
                            </div>
                            <?php if ($contactNumber !== ''): ?>
                                <div class="info-value"><?= e($contactNumber) ?></div>
                            <?php else: ?>
                                <div class="info-value na">Not provided</div>
                            <?php endif; ?>
                        </div>

                        <div class="info-cell">
                            <div class="info-label">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                Email
                            </div>
                            <?php if ($email !== ''): ?>
                                <div class="info-value"><?= e($email) ?></div>
                            <?php else: ?>
                                <div class="info-value na">Not provided</div>
                            <?php endif; ?>
                        </div>

                        <div class="info-cell">
                            <div class="info-label">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Birth Date
                            </div>
                            <?php if ($requestBirthDate !== ''): ?>
                                <div class="info-value"><?= e(requestNiceDateTimeDateLong($requestBirthDate)) ?></div>
                            <?php else: ?>
                                <div class="info-value na">Not provided</div>
                            <?php endif; ?>
                        </div>

                        <div class="info-cell">
                            <div class="info-label">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                Civil Status
                            </div>
                            <?php if ($requestCivilStatus !== ''): ?>
                                <div class="info-value"><?= e(ucfirst($requestCivilStatus)) ?></div>
                            <?php else: ?>
                                <div class="info-value na">Not provided</div>
                            <?php endif; ?>
                        </div>

                        <div class="info-cell">
                            <div class="info-label">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                                Occupation
                            </div>
                            <?php if ($requestOccupation !== ''): ?>
                                <div class="info-value"><?= e($requestOccupation) ?></div>
                            <?php else: ?>
                                <div class="info-value na">Not provided</div>
                            <?php endif; ?>
                        </div>

                        <div class="info-cell">
                            <div class="info-label">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                                Age
                            </div>
                            <div class="info-value">
                                <?= e($requestAge) ?><?= $requestAge !== 'N/A' ? ' years old' : '' ?>
                            </div>
                        </div>

                        <div class="info-cell full">
                            <div class="info-label">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                Address
                            </div>
                            <?php if ($requestAddress !== ''): ?>
                                <div class="info-value muted"><?= nl2br(e($requestAddress)) ?></div>
                            <?php else: ?>
                                <div class="info-value na">No address provided.</div>
                            <?php endif; ?>
                        </div>

                        <div class="info-cell full">
                            <div class="info-label">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                Patient Notes
                            </div>
                            <?php if ($requestNotes !== ''): ?>
                                <div class="info-value muted"><?= nl2br(e($requestNotes)) ?></div>
                            <?php else: ?>
                                <div class="info-value na">No notes provided.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($answers)): ?>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-header-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>
                            </div>
                            <h2 class="card-title">Additional Answers</h2>
                        </div>

                        <div class="card-body">
                            <ul class="answers-list">
                                <?php foreach ($answers as $answer): ?>
                                    <li>
                                        <div class="answer-question"><?= displayText($answer['option_name'] ?? 'Question') ?></div>
                                        <div class="answer-value"><?= displayText(($answer['value_label'] ?? '') ?: ($answer['answer_text'] ?? '')) ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <details class="advanced-details">
                    <summary class="advanced-summary">
                        <div class="advanced-summary-icon">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" color="var(--text-mid)"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                        </div>
                        Advanced Actions
                        <svg class="advanced-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>

                    <div class="advanced-body">
                        <div class="advanced-section">
                            <div class="advanced-section-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                Reschedule
                            </div>

                            <form method="POST" action="/DentalClinic/public/staff/appointment-requests/reschedule">
                                <?= Csrf::inputField(); ?>
                                <input type="hidden" name="request_id" value="<?= $requestId ?>">

                                <div class="form-group">
                                    <label class="form-label" for="rescheduleDentist">
                                        Assign Dentist
                                    </label>
                                    <select name="dentist_id" id="rescheduleDentist" class="form-control" required>
                                        <option value="">Select dentist</option>
                                        <?php foreach ($dentists as $dentist): ?>
                                            <option value="<?= (int) ($dentist['dentist_id'] ?? 0) ?>">
                                                <?= e((string) ($dentist['label'] ?? trim((string) (($dentist['first_name'] ?? '') . ' ' . ($dentist['last_name'] ?? ''))))) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="rescheduleDate">
                                        New Date
                                    </label>
                                    <input type="date" name="preferred_date" id="rescheduleDate" class="form-control" min="<?= e(date('Y-m-d')) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="rescheduleTimeSlot">
                                        Available Time
                                    </label>
                                    <select name="preferred_start_time" id="rescheduleTimeSlot" class="form-control" required disabled>
                                        <option value="">Select dentist and date first</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="rescheduleNotes">
                                        Reason for Rescheduling
                                    </label>
                                    <textarea id="rescheduleNotes" name="staff_notes" class="form-control" placeholder="Briefly explain why you're rescheduling this appointment…"></textarea>
                                </div>

                                <button type="submit" class="btn btn-reschedule" <?= (!$canReschedule || empty($dentists)) ? 'disabled' : '' ?>>
                                    Reschedule Appointment
                                </button>

                                <?php if (!$canReschedule): ?>
                                    <div class="status-hint warning" style="margin-top:12px;">
                                        This request can no longer be rescheduled.
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>

                        <div class="advanced-section">
                            <div class="advanced-section-title" style="color:var(--red);">
                                Reject Request
                            </div>

                            <form method="POST" action="/DentalClinic/public/staff/appointment-requests/reject">
                                <?= Csrf::inputField(); ?>
                                <input type="hidden" name="request_id" value="<?= $requestId ?>">

                                <div class="form-group">
                                    <label class="form-label" for="rejectReason">
                                        Reason for Rejection <span style="color:var(--red);">*</span>
                                    </label>
                                    <textarea id="rejectReason" name="staff_notes" class="form-control" placeholder="Provide a clear reason for rejecting this appointment request. This may be shared with the patient." required style="min-height:120px;"></textarea>
                                </div>

                                <div class="hint" style="margin-bottom:14px;">
                                    Rejecting this request is permanent and the patient will be notified.
                                </div>

                                <button type="submit" class="btn btn-reject" <?= !$canConfirmOrReject ? 'disabled' : '' ?>>
                                    Reject This Request
                                </button>

                                <?php if (!$canConfirmOrReject): ?>
                                    <div class="status-hint warning" style="margin-top:12px;">
                                        This request can no longer be rejected.
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </details>

            </div>

            <div class="request-detail-sidebar">
                <div class="card confirm-card">
                    <div class="card-header">
                        <div class="card-header-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <h2 class="card-title">Confirm Appointment</h2>
                    </div>

                    <div class="card-body">
                        <form id="confirmRequestForm" method="POST" action="/DentalClinic/public/staff/appointment-requests/confirm">
                            <?= Csrf::inputField(); ?>
                            <input type="hidden" name="request_id" value="<?= $requestId ?>">

                            <div class="form-group">
                                <label class="form-label" for="confirmDentist">
                                    Assign Dentist <span style="color:var(--red);">*</span>
                                </label>
                                <select name="dentist_id" id="confirmDentist" class="form-control" required <?= empty($dentists) ? 'disabled' : '' ?>>
                                    <option value="">Choose a dentist</option>
                                    <?php foreach ($dentists as $dentist): ?>
                                        <option value="<?= (int) ($dentist['dentist_id'] ?? 0) ?>">
                                            <?= e((string) ($dentist['label'] ?? trim((string) (($dentist['first_name'] ?? '') . ' ' . ($dentist['last_name'] ?? ''))))) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="confirmNotes">
                                    Internal Notes
                                </label>
                                <textarea id="confirmNotes" name="staff_notes" class="form-control" placeholder="Optional notes visible only to staff…"></textarea>
                            </div>

                            <button
                                type="submit"
                                id="confirmButton"
                                class="btn btn-confirm"
                                <?= (!$canConfirmOrReject || empty($dentists)) ? 'disabled' : '' ?>
                            >
                                Confirm Appointment
                            </button>

                            <?php if (!$canConfirmOrReject): ?>
                                <div class="status-hint warning">
                                    Cannot confirm — status is <strong><?= e(ucwords(str_replace('_', ' ', $requestStatus))) ?></strong>.
                                </div>
                            <?php elseif (empty($dentists)): ?>
                                <div class="status-hint warning">
                                    No dentists are currently available.
                                </div>
                            <?php else: ?>
                                <div class="hint">
                                    Select a dentist and click Confirm.
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
(function () {
    const dentistSel = document.getElementById('rescheduleDentist');
    const dateInput = document.getElementById('rescheduleDate');
    const timeSel = document.getElementById('rescheduleTimeSlot');
    const confirmForm = document.getElementById('confirmRequestForm');
    const confirmButton = document.getElementById('confirmButton');
    const serviceId = <?= (int) $serviceId ?>;

    function resetSlots(msg) {
        if (!timeSel) {
            return;
        }

        timeSel.innerHTML = '';

        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = msg || 'Select dentist and date first';

        timeSel.appendChild(opt);
        timeSel.disabled = true;
    }

    async function loadSlots() {
        const dentistId = dentistSel ? dentistSel.value : '';
        const date = dateInput ? dateInput.value : '';

        if (!dentistId || !date || serviceId <= 0) {
            resetSlots();
            return;
        }

        resetSlots('Loading available slots…');

        try {
            const url = '/DentalClinic/public/staff/appointment-requests/available-slots'
                + '?dentist_id=' + encodeURIComponent(dentistId)
                + '&service_id=' + encodeURIComponent(serviceId)
                + '&date=' + encodeURIComponent(date);

            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!res.ok) {
                throw new Error('Network error');
            }

            const data = await res.json();
            const slots = Array.isArray(data.slots) ? data.slots : [];

            timeSel.innerHTML = '';

            if (!slots.length) {
                resetSlots('No available slots on this date');
                return;
            }

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Choose a time slot';
            timeSel.appendChild(placeholder);

            slots.forEach(function (slot) {
                const option = document.createElement('option');
                option.value = slot.start_time || '';
                option.textContent = slot.label || slot.start_time || '';
                timeSel.appendChild(option);
            });

            timeSel.disabled = false;
        } catch (err) {
            console.error(err);
            resetSlots('Unable to load slots — try again');
        }
    }

    if (dentistSel) {
        dentistSel.addEventListener('change', loadSlots);
    }

    if (dateInput) {
        dateInput.addEventListener('change', loadSlots);
    }

    if (confirmForm && confirmButton) {
        confirmForm.addEventListener('submit', function () {
            confirmButton.disabled = true;
            confirmButton.textContent = 'Confirming…';
        });
    }

    resetSlots();
})();
</script>

<?php
$staffContent = ob_get_clean();
$pageTitle = 'Appointment Request — ' . $requestName;

require __DIR__ . '/../layouts/app.php';
?>