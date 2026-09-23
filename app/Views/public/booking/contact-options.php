<?php

use App\Core\Csrf;

$errors = $errors ?? [];
$success = $success ?? null;

$selectedMethod = strtolower(trim((string) ($selectedMethod ?? 'phone')));

$email = trim((string) ($email ?? ''));
$contactNumber = trim((string) ($contactNumber ?? ''));

$emailMasked = trim((string) ($emailMasked ?? ''));
$contactNumberMasked = trim((string) ($contactNumberMasked ?? ''));

$otpCooldownSeconds = max(0, (int) ($otpCooldownSeconds ?? 0));

$hasPhone = $contactNumber !== '' || $contactNumberMasked !== '';
$hasEmail = $email !== '' || $emailMasked !== '';

if ($selectedMethod === 'email' && !$hasEmail) {
    $selectedMethod = 'phone';
}

if ($selectedMethod === 'phone' && !$hasPhone) {
    $selectedMethod = 'email';
}

if (!in_array($selectedMethod, ['phone', 'email'], true)) {
    $selectedMethod = $hasPhone ? 'phone' : ($hasEmail ? 'email' : '');
}

$defaultMethod = $selectedMethod;

$defaultDestination = $defaultMethod === 'phone'
    ? $contactNumberMasked
    : $emailMasked;

ob_start();
?>

<style>
    :root {
        --otp-primary: #0d9e8c;
        --otp-primary-dark: #08796c;
        --otp-navy: #10233f;
        --otp-text: #1f2937;
        --otp-muted: #64748b;
        --otp-line: #d9e2ec;
        --otp-card: #ffffff;
        --otp-danger-bg: #fef2f2;
        --otp-danger-text: #991b1b;
        --otp-success-bg: #ecfdf5;
        --otp-success-text: #065f46;
        --otp-warning-bg: #fff7ed;
        --otp-warning-text: #9a3412;
    }

    body {
        overflow-y: auto !important;
    }

    .otp-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px 16px;
        background:
            radial-gradient(circle at 20% 15%, rgba(13, 158, 140, 0.18), transparent 28%),
            radial-gradient(circle at 85% 80%, rgba(16, 35, 63, 0.10), transparent 30%),
            linear-gradient(135deg, #eef7f6, #f8fafc);
    }

    .otp-card {
        position: relative;
        width: 100%;
        max-width: 430px;
        background: var(--otp-card);
        border-radius: 28px;
        padding: 28px 28px 26px;
        box-shadow:
            0 24px 60px rgba(15, 23, 42, 0.14),
            0 0 0 1px rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(226, 232, 240, 0.9);
    }

    .otp-close {
        position: absolute;
        top: 16px;
        right: 18px;
        width: 30px;
        height: 30px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        text-decoration: none;
        font-size: 24px;
        line-height: 1;
        transition: background 0.2s ease, color 0.2s ease;
    }

    .otp-close:hover {
        background: #f1f5f9;
        color: var(--otp-navy);
    }

    .otp-illustration {
        width: 110px;
        height: 88px;
        margin: 0 auto 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .otp-illustration svg {
        width: 110px;
        height: 88px;
    }

    .otp-method-tabs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        padding: 5px;
        border-radius: 999px;
        background: #f8fafc;
        border: 1px solid var(--otp-line);
        margin: 10px 0 20px;
    }

    .otp-method {
        position: relative;
    }

    .otp-method input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .otp-method label {
        height: 38px;
        border-radius: 999px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        cursor: pointer;
        color: #94a3b8;
        font-size: 13px;
        font-weight: 800;
        transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
    }

    .otp-method input:disabled + label {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .otp-method-check {
        width: 19px;
        height: 19px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #e2e8f0;
        color: #ffffff;
    }

    .otp-method-check svg {
        width: 12px;
        height: 12px;
    }

    .otp-method input:checked + label {
        background: #ffffff;
        color: var(--otp-navy);
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
    }

    .otp-method input:checked + label .otp-method-check {
        background: var(--otp-primary);
    }

    .otp-title {
        margin: 0;
        text-align: center;
        color: var(--otp-navy);
        font-size: 22px;
        font-weight: 900;
        letter-spacing: -0.03em;
    }

    .otp-subtitle {
        max-width: 310px;
        margin: 8px auto 20px;
        text-align: center;
        color: var(--otp-muted);
        font-size: 12.5px;
        line-height: 1.6;
    }

    .error-box,
    .success-box,
    .cooldown-box {
        border-radius: 16px;
        padding: 12px 14px;
        font-size: 13px;
        line-height: 1.5;
        margin-bottom: 14px;
        border: 1px solid transparent;
    }

    .error-box {
        background: var(--otp-danger-bg);
        color: var(--otp-danger-text);
        border-color: rgba(185, 28, 28, 0.14);
    }

    .success-box {
        background: var(--otp-success-bg);
        color: var(--otp-success-text);
        border-color: rgba(5, 150, 105, 0.16);
    }

    .cooldown-box {
        display: none;
        background: var(--otp-warning-bg);
        color: var(--otp-warning-text);
        border-color: rgba(234, 88, 12, 0.16);
        font-weight: 700;
    }

    .cooldown-box.is-visible {
        display: block;
    }

    .otp-destination-box {
        display: flex;
        align-items: center;
        gap: 10px;
        height: 52px;
        border: 1px solid var(--otp-line);
        border-radius: 18px;
        background: #ffffff;
        padding: 0 12px;
        margin-bottom: 18px;
    }

    .otp-destination-icon {
        width: 34px;
        height: 34px;
        border-radius: 999px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(13, 158, 140, 0.11);
        color: var(--otp-primary);
        flex-shrink: 0;
    }

    .otp-destination-icon svg {
        width: 18px;
        height: 18px;
    }

    .otp-destination-label {
        flex: 1;
        min-width: 0;
        color: var(--otp-text);
        font-size: 14px;
        font-weight: 800;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .otp-destination-type {
        display: block;
        color: var(--otp-muted);
        font-size: 11px;
        font-weight: 800;
        margin-bottom: 2px;
    }

    .otp-clear {
        color: #cbd5e1;
        font-size: 22px;
        line-height: 1;
    }

    .otp-note {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        color: var(--otp-muted);
        font-size: 12px;
        line-height: 1.5;
        margin-bottom: 18px;
    }

    .otp-note svg {
        width: 16px;
        height: 16px;
        color: var(--otp-primary);
        flex-shrink: 0;
        margin-top: 1px;
    }

    .otp-submit {
        width: 100%;
        height: 50px;
        border: 0;
        border-radius: 999px;
        background: var(--otp-primary);
        color: #ffffff;
        font-size: 14px;
        font-weight: 900;
        cursor: pointer;
        box-shadow: 0 12px 24px rgba(13, 158, 140, 0.24);
        transition: background 0.2s ease, transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
    }

    .otp-submit:hover:not(:disabled) {
        background: var(--otp-primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 16px 30px rgba(13, 158, 140, 0.30);
    }

    .otp-submit:disabled {
        opacity: 0.55;
        cursor: not-allowed;
        box-shadow: none;
    }

    @media (max-width: 480px) {
        .otp-page {
            align-items: flex-start;
            padding: 22px 14px;
        }

        .otp-card {
            padding: 26px 20px 24px;
            border-radius: 24px;
        }
    }
</style>

<div class="otp-page">
    <main class="otp-card">
        <a href="/DentalClinic/public/?open_booking=1" class="otp-close" aria-label="Back to booking">×</a>

        <div class="otp-illustration" aria-hidden="true">
            <svg viewBox="0 0 140 100" fill="none">
                <path d="M23 51c0-20 16-36 36-36h38c9 0 16 7 16 16v38c0 9-7 16-16 16H59c-20 0-36-16-36-34Z" fill="#E6F7F5"/>
                <path d="M36 33c6 22 22 36 48 42l13-13c-2-3-5-5-8-7l-11 5c-11-5-19-13-24-24l5-11c-2-3-4-6-7-8L36 33Z" fill="#0D9E8C"/>
                <path d="M65 32h36a9 9 0 0 1 9 9v25a9 9 0 0 1-9 9H65a9 9 0 0 1-9-9V41a9 9 0 0 1 9-9Z" fill="#10233F"/>
                <path d="M70 45h24M70 55h32M70 65h18" stroke="#fff" stroke-width="4" stroke-linecap="round" opacity=".9"/>
                <circle cx="104" cy="44" r="3" fill="#fff"/>
            </svg>
        </div>

        <form method="POST" action="/DentalClinic/public/book/send-verification-otp" id="contactOtpForm">
            <?= Csrf::inputField(); ?>

            <input type="hidden" name="contact_number" value="<?= htmlspecialchars((string) $contactNumber, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="email" value="<?= htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8') ?>">

            <div class="otp-method-tabs">
                <div class="otp-method">
                   <input
    type="radio"
    id="delivery_phone"
    name="delivery_method"
    value="phone"
    required
    <?= $defaultMethod === 'phone' ? 'checked="checked"' : '' ?>
>
                    <label for="delivery_phone">
                        <span class="otp-method-check">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        Phone
                    </label>
                </div>

                <div class="otp-method">
                   <input
    type="radio"
    id="delivery_email"
    name="delivery_method"
    value="email"
    required
    <?= $defaultMethod === 'email' ? 'checked="checked"' : '' ?>
>
                    <label for="delivery_email">
                        <span class="otp-method-check">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        Email
                    </label>
                </div>
            </div>

            <h1 class="otp-title" id="otpTitle">
                <?= $defaultMethod === 'phone' ? 'Phone Verification' : ($defaultMethod === 'email' ? 'Email Verification' : 'Contact Verification') ?>
            </h1>

            <p class="otp-subtitle" id="otpSubtitle">
                <?= $defaultMethod === 'phone'
                    ? 'We will send a 6-digit OTP to your provided mobile number.'
                    : ($defaultMethod === 'email'
                        ? 'We will send a 6-digit OTP to your provided email address.'
                        : 'No contact option was found. Please go back and enter your details again.') ?>
            </p>

            <?php if (!empty($errors['verification'])): ?>
                <div class="error-box">
                    <?= htmlspecialchars((string) $errors['verification'][0], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success-box">
                    <?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="cooldown-box" id="otpCooldownBox">
                Please wait <span id="otpCooldownText">0</span> before requesting another OTP.
            </div>

            <div class="otp-destination-box">
                <span class="otp-destination-icon" id="destinationIcon"></span>

                <span class="otp-destination-label">
                    <span class="otp-destination-type" id="destinationType">
                        <?= $defaultMethod === 'phone' ? 'Phone Number' : ($defaultMethod === 'email' ? 'Email Address' : 'Contact') ?>
                    </span>

                    <span id="destinationText">
                        <?= htmlspecialchars((string) ($defaultDestination !== '' ? $defaultDestination : 'No contact selected'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </span>

                <span class="otp-clear">×</span>
            </div>

            <div class="otp-note">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 3 5 6v5c0 5 3 8.5 7 10 4-1.5 7-5 7-10V6l-7-3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="m9.5 12 1.7 1.7 3.5-3.7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Your full contact details are hidden. OTP codes are temporary and should never be shared.</span>
            </div>

            <button
                type="submit"
                class="otp-submit"
                id="sendOtpBtn"
                <?= $defaultMethod === '' ? 'disabled' : '' ?>
            >
                <?= $defaultMethod === 'phone' ? 'Send Code to Phone' : ($defaultMethod === 'email' ? 'Send Code to Email' : 'No Method Available') ?>
            </button>
        </form>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const phoneMasked = <?= json_encode($contactNumberMasked) ?>;
    const emailMasked = <?= json_encode($emailMasked) ?>;
    const serverCooldownSeconds = <?= json_encode($otpCooldownSeconds) ?>;

    const title = document.getElementById('otpTitle');
    const subtitle = document.getElementById('otpSubtitle');
    const destinationType = document.getElementById('destinationType');
    const destinationText = document.getElementById('destinationText');
    const destinationIcon = document.getElementById('destinationIcon');
    const sendBtn = document.getElementById('sendOtpBtn');
    const form = document.getElementById('contactOtpForm');
    const radios = document.querySelectorAll('input[name="delivery_method"]');
    const cooldownBox = document.getElementById('otpCooldownBox');
    const cooldownText = document.getElementById('otpCooldownText');

    let cooldownTimer = null;
    let baseButtonText = sendBtn ? sendBtn.textContent.trim() : '';

    const phoneIcon = `
        <svg viewBox="0 0 24 24" fill="none">
            <path d="M8 2h8a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2"/>
            <path d="M11 18h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
    `;

    const emailIcon = `
        <svg viewBox="0 0 24 24" fill="none">
            <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5v-11Z" stroke="currentColor" stroke-width="2"/>
            <path d="m5 7 7 6 7-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    `;

    function selectedMethod() {
        const selected = document.querySelector('input[name="delivery_method"]:checked');
        return selected ? selected.value : '';
    }

    function formatSeconds(seconds) {
        seconds = Math.max(0, parseInt(seconds, 10) || 0);

        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;

        if (minutes <= 0) {
            return remainingSeconds + ' second' + (remainingSeconds === 1 ? '' : 's');
        }

        return minutes + ':' + String(remainingSeconds).padStart(2, '0');
    }

    function getCooldownKey() {
        return 'booking_otp_cooldown_' + selectedMethod();
    }

    function getStoredCooldownSeconds() {
        const expiresAt = parseInt(localStorage.getItem(getCooldownKey()) || '0', 10);

        if (!expiresAt) {
            return 0;
        }

        const remaining = Math.ceil((expiresAt - Date.now()) / 1000);

        if (remaining <= 0) {
            localStorage.removeItem(getCooldownKey());
            return 0;
        }

        return remaining;
    }

    function storeCooldown(seconds) {
        seconds = Math.max(0, parseInt(seconds, 10) || 0);

        if (seconds <= 0) {
            localStorage.removeItem(getCooldownKey());
            return;
        }

        localStorage.setItem(getCooldownKey(), String(Date.now() + (seconds * 1000)));
    }

    function stopCooldown() {
        if (cooldownTimer) {
            clearInterval(cooldownTimer);
            cooldownTimer = null;
        }

        if (cooldownBox) {
            cooldownBox.classList.remove('is-visible');
        }

        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.textContent = baseButtonText;
        }
    }

    function startCooldown(seconds) {
        seconds = Math.max(0, parseInt(seconds, 10) || 0);

        if (!sendBtn || seconds <= 0) {
            stopCooldown();
            return;
        }

        if (cooldownTimer) {
            clearInterval(cooldownTimer);
        }

        let remaining = seconds;

        sendBtn.disabled = true;

        if (cooldownBox) {
            cooldownBox.classList.add('is-visible');
        }

        function tick() {
            if (remaining <= 0) {
                localStorage.removeItem(getCooldownKey());
                stopCooldown();
                return;
            }

            if (cooldownText) {
                cooldownText.textContent = formatSeconds(remaining);
            }

            sendBtn.textContent = 'Wait ' + formatSeconds(remaining);
            remaining--;
        }

        tick();
        cooldownTimer = setInterval(tick, 1000);
    }

    function updateState() {
        const method = selectedMethod();

        if (!destinationIcon || !sendBtn) {
            return;
        }

        if (!method) {
            title.textContent = 'Contact Verification';
            subtitle.textContent = 'No contact option was found. Please go back and enter your details again.';
            destinationType.textContent = 'Contact';
            destinationText.textContent = 'No contact selected';
            destinationIcon.innerHTML = phoneIcon;
            sendBtn.disabled = true;
            sendBtn.textContent = 'No Method Available';
            baseButtonText = 'No Method Available';
            return;
        }

        if (method === 'phone') {
            title.textContent = 'Phone Verification';
            subtitle.textContent = 'We will send a 6-digit OTP to your provided mobile number.';
            destinationType.textContent = 'Phone Number';
            destinationText.textContent = phoneMasked || 'Phone selected';
            destinationIcon.innerHTML = phoneIcon;
            baseButtonText = 'Send Code to Phone';
        } else {
            title.textContent = 'Email Verification';
            subtitle.textContent = 'We will send a 6-digit OTP to your provided email address.';
            destinationType.textContent = 'Email Address';
            destinationText.textContent = emailMasked || 'Email selected';
            destinationIcon.innerHTML = emailIcon;
            baseButtonText = 'Send Code to Email';
        }

        const storedCooldown = getStoredCooldownSeconds();

        if (storedCooldown > 0) {
            startCooldown(storedCooldown);
        } else if (serverCooldownSeconds > 0) {
            storeCooldown(serverCooldownSeconds);
            startCooldown(serverCooldownSeconds);
        } else {
            stopCooldown();
            sendBtn.textContent = baseButtonText;
        }
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', updateState);
    });

    if (form && sendBtn) {
        form.addEventListener('submit', function (event) {
            if (sendBtn.disabled) {
                event.preventDefault();
                return;
            }

            
            sendBtn.disabled = true;
            sendBtn.textContent = 'Sending...';
        });
    }

    updateState();
});
</script>

<?php
$content = ob_get_clean();
$title = 'Verify Contact';
require __DIR__ . '/../../layouts/main.php';