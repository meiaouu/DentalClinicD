<?php

use App\Core\Csrf;

$errors = $errors ?? [];
$old = $old ?? [];
$success = $success ?? null;

if (!function_exists('fp_e')) {
    function fp_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

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

    .form-group {
        margin-bottom: 16px;
    }

    label {
        display: block;
        font-weight: 700;
        margin-bottom: 7px;
        color: #1f2937;
    }

    input[type="text"] {
        width: 100%;
        height: 48px;
        border: 1px solid rgba(148,163,184,.45);
        border-radius: 14px;
        padding: 0 14px;
        font-size: 14px;
        box-sizing: border-box;
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
</style>

<div class="auth-simple-page">
    <div class="auth-simple-card">
        <h1>Forgot Password</h1>
        <p>Enter your username, email, or mobile number. We will check your available recovery options.</p>

        <?php if (!empty($errors['forgot'])): ?>
            <div class="error-box"><?= fp_e($errors['forgot'][0]) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-box"><?= fp_e($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="/DentalClinic/public/forgot-password/lookup">
            <?= Csrf::inputField(); ?>

            <div class="form-group">
                <label for="identifier">Username, Email, or Mobile Number</label>
                <input
                    type="text"
                    id="identifier"
                    name="identifier"
                    value="<?= fp_e($old['identifier'] ?? '') ?>"
                    required
                    placeholder="Username, email, or 09XXXXXXXXX"
                    autocomplete="username"
                >
            </div>

            <button type="submit">Find Recovery Options</button>
        </form>

        <div class="links">
            <a href="/DentalClinic/public/account-recovery">Can’t access your email or phone?</a>
            <a href="/DentalClinic/public/login">Back to login</a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Forgot Password';
require __DIR__ . '/../../layouts/main.php';