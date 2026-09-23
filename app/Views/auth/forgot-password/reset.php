<?php

use App\Core\Csrf;

$errors = $errors ?? [];
$success = $success ?? null;
$token = $token ?? ($_GET['token'] ?? '');

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
        max-width: 480px;
        background: rgba(255,255,255,.97);
        border-radius: 24px;
        padding: 32px;
        box-shadow: 0 24px 70px rgba(15,23,42,.28);
    }

    .auth-simple-card h1 {
        text-align: center;
        color: #10233f;
        margin: 0 0 10px;
        font-size: 28px;
        font-weight: 900;
    }

    .auth-simple-card p {
        text-align: center;
        color: #64748b;
        line-height: 1.6;
        margin-bottom: 22px;
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-label {
        display: block;
        font-weight: 800;
        margin-bottom: 7px;
        color: #0f172a;
        font-size: 14px;
    }

    .password-input {
        width: 100%;
        height: 48px;
        border: 1px solid rgba(148,163,184,.45);
        border-radius: 14px;
        padding: 0 14px;
        font-size: 14px;
        outline: none;
        background: #ffffff;
        color: #0f172a;
    }

    .password-input:focus {
        border-color: #0d9e8c;
        box-shadow: 0 0 0 3px rgba(13,158,140,.14);
    }

    .primary-btn {
        width: 100%;
        height: 48px;
        border: 0;
        border-radius: 14px;
        background: linear-gradient(135deg, #0d9e8c, #08796c);
        color: #fff;
        font-weight: 900;
        cursor: pointer;
        font-size: 14px;
    }

    .primary-btn:hover {
        background: linear-gradient(135deg, #0b8d7d, #07685e);
    }

    .error-box,
    .success-box {
        border-radius: 14px;
        padding: 12px 14px;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 16px;
    }

    .error-box {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .success-box {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .password-help {
        margin-top: -6px;
        margin-bottom: 16px;
        color: #64748b;
        font-size: 12px;
        line-height: 1.5;
    }

    @media (max-width: 520px) {
        .auth-simple-page {
            padding: 28px 14px;
        }

        .auth-simple-card {
            padding: 24px 20px;
            border-radius: 20px;
        }
    }
</style>

<div class="auth-simple-page">
    <div class="auth-simple-card">
        <h1>Reset Password</h1>

        <p>
            Create a new password for your account.
        </p>

        <?php if (!empty($errors['password'])): ?>
            <div class="error-box">
                <?= htmlspecialchars((string) $errors['password'][0], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-box">
                <?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/DentalClinic/public/forgot-password/reset">
            <?= Csrf::inputField(); ?>

            <input
                type="hidden"
                name="token"
                value="<?= htmlspecialchars((string) $token, ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="form-group">
                <label class="form-label" for="password">
                    New Password
                </label>

                <input
                    class="password-input"
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    required
                    autocomplete="new-password"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirmation">
                    Confirm New Password
                </label>

                <input
                    class="password-input"
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    minlength="8"
                    required
                    autocomplete="new-password"
                >
            </div>

            <div class="password-help">
                Password must be at least 8 characters and must be different from your old password.
            </div>

            <button type="submit" class="primary-btn">
                Save New Password
            </button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Reset Password';
require __DIR__ . '/../../layouts/main.php';