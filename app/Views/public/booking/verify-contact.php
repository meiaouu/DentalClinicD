<?php

use App\Core\Csrf;

$errors = $errors ?? [];
$success = $success ?? null;

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
        --otp-danger-bg: #fef2f2;
        --otp-danger-text: #991b1b;
        --otp-success-bg: #ecfdf5;
        --otp-success-text: #065f46;
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
        background: #ffffff;
        border-radius: 28px;
        padding: 32px 28px 26px;
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
        width: 120px;
        height: 98px;
        margin: 0 auto 14px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .otp-illustration svg {
        width: 120px;
        height: 98px;
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
        max-width: 320px;
        margin: 8px auto 22px;
        text-align: center;
        color: var(--otp-muted);
        font-size: 12.5px;
        line-height: 1.6;
    }

    .error-box,
    .success-box {
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

    .otp-boxes {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 8px;
        margin-bottom: 16px;
    }

    .otp-digit {
        width: 100%;
        height: 58px;
        border: 1.5px solid var(--otp-line);
        border-radius: 14px;
        background: #f8fafc;
        text-align: center;
        font-size: 22px;
        font-weight: 900;
        color: var(--otp-navy);
        outline: none;
        transition:
            border-color 0.2s ease,
            box-shadow 0.2s ease,
            background 0.2s ease;
    }

    .otp-digit:focus {
        border-color: var(--otp-primary);
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(13, 158, 140, 0.12);
    }

    .otp-digit.filled {
        background: #ffffff;
        border-color: rgba(13, 158, 140, 0.45);
    }

    .resend-wrap {
        text-align: center;
        margin-bottom: 18px;
    }

    .resend-link {
        color: var(--otp-muted);
        font-size: 13px;
        font-weight: 800;
        text-decoration: underline;
    }

    .resend-link:hover {
        color: var(--otp-primary-dark);
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
        transition:
            background 0.2s ease,
            transform 0.18s ease,
            box-shadow 0.18s ease,
            opacity 0.18s ease;
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

    .otp-note {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin-top: 16px;
        color: var(--otp-muted);
        font-size: 12px;
        line-height: 1.5;
    }

    .otp-note svg {
        width: 16px;
        height: 16px;
        color: var(--otp-primary);
        flex-shrink: 0;
        margin-top: 1px;
    }

    @media (max-width: 480px) {
        .otp-page {
            align-items: flex-start;
            padding: 22px 14px;
        }

        .otp-card {
            padding: 30px 20px 24px;
            border-radius: 24px;
        }

        .otp-digit {
            height: 52px;
            border-radius: 12px;
            font-size: 20px;
        }
    }
</style>

<div class="otp-page">
    <main class="otp-card">
        <a href="/DentalClinic/public/?open_booking=1" class="otp-close" aria-label="Start again">×</a>

        <div class="otp-illustration" aria-hidden="true">
            <svg viewBox="0 0 140 110" fill="none">
                <path d="M44 47V35c0-17 13-30 30-30s30 13 30 30v12" stroke="#0D9E8C" stroke-width="8" stroke-linecap="round"/>
                <path d="M36 45h76a9 9 0 0 1 9 9v42a9 9 0 0 1-9 9H36a9 9 0 0 1-9-9V54a9 9 0 0 1 9-9Z" fill="#10233F"/>
                <path d="M74 66a8 8 0 0 0-4 15v9h8v-9a8 8 0 0 0-4-15Z" fill="#fff"/>
                <circle cx="105" cy="21" r="15" fill="#E6F7F5" stroke="#0D9E8C" stroke-width="5"/>
                <path d="M115 31l14 14" stroke="#0D9E8C" stroke-width="6" stroke-linecap="round"/>
                <path d="M125 41h8v8" stroke="#0D9E8C" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        <h1 class="otp-title">Enter OTP Code</h1>

        <p class="otp-subtitle">
            Enter the 6-digit verification code sent to your selected contact method.
        </p>

        <?php if (!empty($errors['otp'])): ?>
            <div class="error-box">
                <?= htmlspecialchars((string) $errors['otp'][0], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

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

        <form method="POST" action="/DentalClinic/public/book/verify-contact" id="otpVerifyForm">
            <?= Csrf::inputField(); ?>

            <input type="hidden" name="otp" id="otpHidden" value="">

            <div class="otp-boxes" aria-label="OTP Code">
                <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="OTP digit 1">
                <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 2">
                <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 3">
                <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 4">
                <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 5">
                <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="OTP digit 6">
            </div>

            <div class="resend-wrap">
                <a href="/DentalClinic/public/book/contact-options" class="resend-link">
                    Resend Code
                </a>
            </div>

            <button type="submit" class="otp-submit" id="verifyOtpBtn">
                Verify Code
            </button>

            <div class="otp-note">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 3 5 6v5c0 5 3 8.5 7 10 4-1.5 7-5 7-10V6l-7-3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="m9.5 12 1.7 1.7 3.5-3.7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Never share your OTP code with anyone. The code is temporary and can only be used once.</span>
            </div>
        </form>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('otpVerifyForm');
    const hidden = document.getElementById('otpHidden');
    const inputs = Array.from(document.querySelectorAll('.otp-digit'));
    const submitBtn = document.getElementById('verifyOtpBtn');

    function syncOtp() {
        hidden.value = inputs.map(function (input) {
            return input.value;
        }).join('');

        inputs.forEach(function (input) {
            input.classList.toggle('filled', input.value !== '');
        });
    }

    function focusInput(index) {
        if (inputs[index]) {
            inputs[index].focus();
            inputs[index].select();
        }
    }

    inputs.forEach(function (input, index) {
        input.addEventListener('input', function () {
            input.value = input.value.replace(/\D/g, '').slice(0, 1);
            syncOtp();

            if (input.value && inputs[index + 1]) {
                focusInput(index + 1);
            }
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Backspace' && !input.value && inputs[index - 1]) {
                event.preventDefault();
                focusInput(index - 1);
                inputs[index - 1].value = '';
                syncOtp();
            }

            if (event.key === 'ArrowLeft' && inputs[index - 1]) {
                event.preventDefault();
                focusInput(index - 1);
            }

            if (event.key === 'ArrowRight' && inputs[index + 1]) {
                event.preventDefault();
                focusInput(index + 1);
            }
        });

        input.addEventListener('paste', function (event) {
            event.preventDefault();

            const pasted = (event.clipboardData || window.clipboardData)
                .getData('text')
                .replace(/\D/g, '')
                .slice(0, 6);

            if (!pasted) {
                return;
            }

            inputs.forEach(function (box) {
                box.value = '';
            });

            pasted.split('').forEach(function (digit, pasteIndex) {
                if (inputs[pasteIndex]) {
                    inputs[pasteIndex].value = digit;
                }
            });

            syncOtp();

            const focusIndex = Math.min(pasted.length, inputs.length) - 1;

            if (focusIndex >= 0) {
                focusInput(focusIndex);
            }
        });
    });

    if (form) {
        form.addEventListener('submit', function (event) {
            syncOtp();

            if (!/^\d{6}$/.test(hidden.value)) {
                event.preventDefault();
                alert('Please enter the complete 6-digit OTP code.');

                if (inputs[0]) {
                    focusInput(0);
                }

                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Verifying...';
            }
        });
    }

    if (inputs[0]) {
        focusInput(0);
    }

    syncOtp();
});
</script>

<?php
$content = ob_get_clean();
$title = 'Verify OTP';
require __DIR__ . '/../../layouts/main.php';