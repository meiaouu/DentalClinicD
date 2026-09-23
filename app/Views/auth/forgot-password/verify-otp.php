<?php

use App\Core\Csrf;

$errors = $errors ?? [];
$success = $success ?? null;

ob_start();
?>

<style>
    body { overflow-y: auto !important; }
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
        max-width: 460px;
        background: rgba(255,255,255,.97);
        border-radius: 24px;
        padding: 32px;
        box-shadow: 0 24px 70px rgba(15,23,42,.28);
    }
    h1 { text-align:center;color:#10233f;margin:0 0 10px; }
    p { text-align:center;color:#64748b;line-height:1.6; }
    input {
        width: 100%;
        height: 52px;
        border: 1px solid rgba(148,163,184,.45);
        border-radius: 14px;
        padding: 0 14px;
        font-size: 20px;
        text-align: center;
        letter-spacing: 6px;
    }
    button {
        width: 100%;
        height: 48px;
        border: 0;
        border-radius: 14px;
        margin-top: 16px;
        background: linear-gradient(135deg, #0d9e8c, #08796c);
        color: #fff;
        font-weight: 800;
        cursor: pointer;
    }
    .error-box, .success-box {
        border-radius: 14px;
        padding: 12px 14px;
        font-size: 14px;
        margin-bottom: 16px;
    }
    .error-box { background:#fef2f2;color:#991b1b; }
    .success-box { background:#ecfdf5;color:#065f46; }
    .links { text-align:center;margin-top:18px; }
    .links a { color:#08796c;font-weight:700;text-decoration:none; }
</style>

<div class="auth-simple-page">
    <div class="auth-simple-card">
        <h1>Verify OTP</h1>
        <p>Enter the 6-digit OTP sent to your selected recovery option.</p>

        <?php if (!empty($errors['otp'])): ?>
            <div class="error-box"><?= htmlspecialchars((string) $errors['otp'][0], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-box"><?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" action="/DentalClinic/public/forgot-password/verify-otp">
            <?= Csrf::inputField(); ?>

            <input
                type="text"
                name="otp"
                maxlength="6"
                inputmode="numeric"
                pattern="[0-9]{6}"
                required
                autocomplete="one-time-code"
                placeholder="000000"
            >

            <button type="submit">Verify OTP</button>
        </form>

        <div class="links">
            <a href="/DentalClinic/public/forgot-password">Request new OTP</a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Verify OTP';
require __DIR__ . '/../../layouts/main.php';