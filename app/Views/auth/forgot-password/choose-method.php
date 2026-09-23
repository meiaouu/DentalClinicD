<?php

use App\Core\Csrf;

$errors = $errors ?? [];
$options = $options ?? [];
$success = $success ?? null;

if (!function_exists('fp_e')) {
    function fp_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$hasEmail = !empty($options['email']);
$hasPhone = !empty($options['phone']);

$defaultMethod = $hasEmail ? 'email' : ($hasPhone ? 'phone' : '');

ob_start();
?>

<style>
    body {
        overflow-y: auto !important;
    }

    .auth-simple-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 48px 16px;
        background: linear-gradient(135deg, rgba(8,20,36,.92), rgba(13,158,140,.55));
    }

    .auth-simple-card {
        width: 100%;
        max-width: 520px;
        background: rgba(255,255,255,.97);
        border-radius: 24px;
        padding: 32px;
        box-shadow: 0 24px 70px rgba(15,23,42,.28);
    }

    .auth-simple-card h1 {
        margin: 0 0 10px;
        color: #10233f;
        font-size: 28px;
        text-align: center;
    }

    .auth-simple-card p {
        color: #64748b;
        line-height: 1.6;
        text-align: center;
        margin-bottom: 22px;
    }

    .error-box,
    .success-box {
        border-radius: 14px;
        padding: 12px 14px;
        font-size: 14px;
        margin-bottom: 16px;
    }

    .error-box {
        background: #fef2f2;
        color: #991b1b;
    }

    .success-box {
        background: #ecfdf5;
        color: #065f46;
    }

    .method-title {
        margin: 0 0 12px;
        color: #10233f;
        font-size: 15px;
        font-weight: 900;
    }

    .method-list {
        display: grid;
        gap: 12px;
        margin-bottom: 18px;
    }

    .method-option {
        position: relative;
    }

    .method-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .method-option label {
        display: flex;
        align-items: center;
        gap: 14px;
        width: 100%;
        min-height: 70px;
        border: 1px solid rgba(13,158,140,.24);
        background: rgba(13,158,140,.06);
        border-radius: 18px;
        padding: 14px;
        cursor: pointer;
        transition: all .2s ease;
        box-sizing: border-box;
    }

    .method-option input:checked + label {
        border-color: rgba(13,158,140,.75);
        background: rgba(13,158,140,.12);
        box-shadow: 0 10px 24px rgba(13,158,140,.12);
    }

    .method-radio {
        width: 20px;
        height: 20px;
        border-radius: 999px;
        border: 2px solid #cbd5e1;
        background: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .method-radio::after {
        content: "";
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: #0d9e8c;
        opacity: 0;
        transform: scale(.6);
        transition: all .2s ease;
    }

    .method-option input:checked + label .method-radio {
        border-color: #0d9e8c;
    }

    .method-option input:checked + label .method-radio::after {
        opacity: 1;
        transform: scale(1);
    }

    .method-icon {
        width: 42px;
        height: 42px;
        border-radius: 999px;
        background: rgba(13,158,140,.14);
        color: #0d9e8c;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .method-icon svg {
        width: 21px;
        height: 21px;
    }

    .method-info {
        flex: 1;
        min-width: 0;
    }

    .method-name {
        display: block;
        color: #10233f;
        font-size: 14px;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .method-value {
        display: block;
        color: #334155;
        font-size: 14px;
        font-weight: 800;
        word-break: break-word;
    }

    .method-note {
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
        margin: 0 0 18px;
    }

    button {
        width: 100%;
        height: 48px;
        border: 0;
        border-radius: 14px;
        background: linear-gradient(135deg, #0d9e8c, #08796c);
        color: #fff;
        font-weight: 800;
        cursor: pointer;
    }

    button:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .links {
        text-align: center;
        margin-top: 18px;
        display: grid;
        gap: 8px;
    }

    .links a {
        color: #08796c;
        font-weight: 700;
        text-decoration: none;
    }

    @media (max-width: 520px) {
        .auth-simple-card {
            padding: 26px 20px;
        }

        .method-option label {
            align-items: flex-start;
        }
    }
</style>

<div class="auth-simple-page">
    <div class="auth-simple-card">
        <h1>Choose Recovery Method</h1>
        <p>Select where you want to receive your password reset link.</p>

        <?php if (!empty($errors['forgot'])): ?>
            <div class="error-box"><?= fp_e($errors['forgot'][0]) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-box"><?= fp_e($success) ?></div>
        <?php endif; ?>

        <?php if (!$hasEmail && !$hasPhone): ?>
            <div class="error-box">No recovery option is available for this account. Please contact the clinic.</div>

            <div class="links">
                <a href="/DentalClinic/public/forgot-password">Try again</a>
                <a href="/DentalClinic/public/login">Back to login</a>
            </div>
        <?php else: ?>
            <form method="POST" action="/DentalClinic/public/forgot-password/send-reset-link" id="resetMethodForm">
                <?= Csrf::inputField(); ?>

                <h2 class="method-title">Send reset link through</h2>

                <div class="method-list">
                    <?php if ($hasEmail): ?>
                        <div class="method-option">
                            <input
                                type="radio"
                                id="method_email"
                                name="delivery_method"
                                value="email"
                                required
                                <?= $defaultMethod === 'email' ? 'checked' : '' ?>
                            >

                            <label for="method_email">
                                <span class="method-radio"></span>

                                <span class="method-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v11A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5v-11Z" stroke="currentColor" stroke-width="2"/>
                                        <path d="m5 7 7 6 7-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>

                                <span class="method-info">
                                    <span class="method-name">Email Address</span>
                                    <span class="method-value"><?= fp_e($options['email']) ?></span>
                                </span>
                            </label>
                        </div>
                    <?php endif; ?>

                    <?php if ($hasPhone): ?>
                        <div class="method-option">
                            <input
                                type="radio"
                                id="method_phone"
                                name="delivery_method"
                                value="phone"
                                required
                                <?= $defaultMethod === 'phone' ? 'checked' : '' ?>
                            >

                            <label for="method_phone">
                                <span class="method-radio"></span>

                                <span class="method-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M8 2h8a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2"/>
                                        <path d="M11 18h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </span>

                                <span class="method-info">
                                    <span class="method-name">Phone Number</span>
                                    <span class="method-value"><?= fp_e($options['phone']) ?></span>
                                </span>
                            </label>
                        </div>
                    <?php endif; ?>
                </div>

                <p class="method-note">
                    For your security, your full contact details are hidden. The reset link will expire after 15 minutes.
                </p>

                <button type="submit" id="sendResetBtn">
                    <?= $defaultMethod === 'phone' ? 'Send Reset Link to Phone' : 'Send Reset Link to Email' ?>
                </button>
            </form>

            <div class="links">
                <a href="/DentalClinic/public/forgot-password">Use another account</a>
                <a href="/DentalClinic/public/account-recovery">Can’t access your email or phone?</a>
                <a href="/DentalClinic/public/login">Back to login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const button = document.getElementById('sendResetBtn');
    const form = document.getElementById('resetMethodForm');
    const radios = document.querySelectorAll('input[name="delivery_method"]');

    function updateButton() {
        const selected = document.querySelector('input[name="delivery_method"]:checked');

        if (!button || !selected) {
            return;
        }

        button.textContent = selected.value === 'phone'
            ? 'Send Reset Link to Phone'
            : 'Send Reset Link to Email';
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', updateButton);
    });

    if (form && button) {
        form.addEventListener('submit', function () {
            button.disabled = true;
            button.textContent = 'Sending...';
        });
    }

    updateButton();
});
</script>

<?php
$content = ob_get_clean();
$title = 'Choose Recovery Method';
require __DIR__ . '/../../layouts/main.php';