<?php

$requestCode = $requestCode ?? '';

$baseUrl = '/DentalClinic/public';

$e = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$requestCode = trim((string) $requestCode);
$encodedRequestCode = rawurlencode($requestCode);

$trackUrl = $baseUrl . '/track-request';
$rescheduleUrl = $baseUrl . '/track-request';
$cancelUrl = $baseUrl . '/track-request';

if ($requestCode !== '') {
    $trackUrl .= '?request_code=' . $encodedRequestCode;
    $rescheduleUrl .= '?request_code=' . $encodedRequestCode . '&action=reschedule';
    $cancelUrl .= '?request_code=' . $encodedRequestCode . '&action=cancel';
}

ob_start();
?>

<style>
.success-page,
.success-page * {
    box-sizing: border-box;
}

body {
    background: #f4f8fb;
    color: #111827;
    overflow-x: hidden;
}

.success-bg {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    overflow: hidden;
}

.success-bg::before {
    content: '';
    position: absolute;
    inset: -40px;
    background-image:
        linear-gradient(rgba(15, 118, 110, 0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(15, 118, 110, 0.04) 1px, transparent 1px);
    background-size: 48px 48px;
    animation: successGridDrift 26s linear infinite;
}

.success-bg::after {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 15% 18%, rgba(20, 184, 166, 0.10), transparent 35%),
        radial-gradient(circle at 85% 75%, rgba(37, 99, 235, 0.08), transparent 32%),
        radial-gradient(circle at 50% 8%, rgba(15, 118, 110, 0.06), transparent 28%);
}

@keyframes successGridDrift {
    from {
        transform: translate(0, 0);
    }

    to {
        transform: translate(48px, 48px);
    }
}

.success-page {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    background: transparent;
    padding: 34px 12px 44px;
    font-family: var(--font-ui, "DM Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif);
}

.success-container {
    max-width: 560px;
    margin: 0 auto;
}

.success-card {
    background: #ffffff;
    border: 1px solid #ececec;
    border-radius: 5px;
    padding: 28px 30px 24px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.success-icon {
    width: 42px;
    height: 42px;
    margin: 0 auto 18px;
    border-radius: 999px;
    background: #dcfce7;
    color: #15803d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 800;
}

.success-title {
    margin: 0 0 8px;
    text-align: center;
    font-size: 22px;
    line-height: 1.3;
    font-weight: 700;
    color: #111827;
}

.success-subtitle {
    max-width: 400px;
    margin: 0 auto 22px;
    text-align: center;
    color: #4b5563;
    font-size: 13px;
    line-height: 1.6;
}

.success-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 18px 0;
}

.success-details {
    display: grid;
    gap: 15px;
}

.success-row {
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: 18px;
    align-items: start;
}

.success-label {
    color: #111827;
    font-size: 13px;
    font-weight: 600;
}

.success-value {
    color: #374151;
    font-size: 13px;
    line-height: 1.55;
    word-break: break-word;
}

.success-code {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    max-width: 100%;
    padding: 6px 10px;
    border-radius: 6px;
    background: #f9fafb;
    border: 1px solid #d1d5db;
    color: #111827;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.03em;
    word-break: break-all;
}

.success-status {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    padding: 5px 9px;
    border-radius: 999px;
    background: #fef3c7;
    border: 1px solid #fde68a;
    color: #92400e;
    font-size: 12px;
    font-weight: 800;
}

.success-note {
    margin-top: 18px;
    padding: 11px 13px;
    border-radius: 8px;
    background: #fafafa;
    border: 1px solid #e5e7eb;
    color: #4b5563;
    font-size: 12px;
    line-height: 1.6;
}

.success-change {
    margin-top: 18px;
    padding-top: 18px;
    border-top: 1px solid #e5e7eb;
    text-align: center;
    color: #374151;
    font-size: 13px;
    line-height: 1.5;
}

.success-change strong {
    color: #111827;
    font-weight: 700;
}

.success-change-links {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-left: 4px;
}

.success-change-links a {
    color: #376cdf;
    font-weight: 700;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.success-change-links a:hover {
    color: #1d4ed8;
}

.success-actions {
    margin-top: 18px;
    padding-top: 18px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-primary,
.btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 145px;
    min-height: 38px;
    padding: 0 14px;
    border-radius: 5px;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
    border: 1px solid transparent;
}

.btn-primary {
    background: #0e0e1d;
    border-color: #09090b;
    color: #ffffff;
}

.btn-primary:hover {
    background: #202024;
    border-color: #202024;
}

.btn-secondary {
    background: #ffffff;
    border-color: #d1d5db;
    color: #374151;
}

.btn-secondary:hover {
    background: #fafafa;
}

.success-warning {
    margin-top: 14px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    font-size: 12px;
    line-height: 1.5;
}

.success-footer {
    margin-top: 14px;
    text-align: center;
    color: #111827;
    font-size: 13px;
    font-weight: 800;
}

@media (max-width: 520px) {
    .success-page {
        padding: 24px 12px 36px;
    }

    .success-card {
        padding: 22px 18px;
    }

    .success-row {
        grid-template-columns: 1fr;
        gap: 4px;
    }

    .success-change-links {
        display: flex;
        margin-top: 6px;
        margin-left: 0;
    }

    .success-actions {
        flex-direction: column;
    }

    .btn-primary,
    .btn-secondary {
        width: 100%;
    }
}
</style>

<div class="success-bg"></div>

<div class="success-page">
    <div class="success-container">
        <div class="success-card">
            <div class="success-icon">✓</div>

            <h1 class="success-title">Your booking request was sent</h1>

            <p class="success-subtitle">
                The clinic received your request. Please save your request code so you can track, reschedule, or cancel it if needed.
            </p>

            <div class="success-divider"></div>

            <div class="success-details">
                <div class="success-row">
                    <div class="success-label">What</div>
                    <div class="success-value">Dental appointment request</div>
                </div>

                <div class="success-row">
                    <div class="success-label">Status</div>
                    <div class="success-value">
                        <span class="success-status">Pending clinic review</span>
                    </div>
                </div>

                <div class="success-row">
                    <div class="success-label">Reference</div>
                    <div class="success-value">
                        <span class="success-code">
                            <?= $e($requestCode !== '' ? $requestCode : 'N/A') ?>
                        </span>
                    </div>
                </div>

                <div class="success-row">
                    <div class="success-label">Next step</div>
                    <div class="success-value">
                        The clinic will review your preferred schedule before confirming the final appointment.
                    </div>
                </div>
            </div>

            <div class="success-note">
                This is not yet a confirmed appointment. Please wait for clinic approval or use your request code to check the request status.
            </div>

            <div class="success-change">
                <strong>Need to make a change?</strong>

                <span class="success-change-links">
                    <a href="<?= $e($rescheduleUrl) ?>">Reschedule</a>
                    <span>or</span>
                    <a href="<?= $e($cancelUrl) ?>">Cancel</a>
                </span>
            </div>

            <div class="success-actions">
                <a href="<?= $e($trackUrl) ?>" class="btn-secondary">
                    Track Request
                </a>

                <a href="<?= $e($baseUrl . '/') ?>" class="btn-primary">
                    Back to Home
                </a>
            </div>

            <?php if ($requestCode === ''): ?>
                <div class="success-warning">
                    Request code was not found on this page. You can still open tracking, but you will need to manually enter your request code and mobile number.
                </div>
            <?php endif; ?>
        </div>

        <div class="success-footer">
            Dr. Brendalyn Wansi Calacat Dental Clinic
        </div>
    </div>
</div>

<script>
localStorage.removeItem('dental_clinic_booking_form_v1');
localStorage.removeItem('bwc_dental_booking_v2');
</script>

<?php
$content = ob_get_clean();
$title = 'Booking Success';

require __DIR__ . '/../../layouts/main.php';