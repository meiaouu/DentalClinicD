<?php
use App\Core\Csrf;

$token = trim((string) ($token ?? ''));
$isValidToken = (bool) ($isValidToken ?? false);
$errors = isset($errors) && is_array($errors) ? $errors : [];
$flash_error = $flash_error ?? null;
$flash_success = $flash_success ?? null;
$baseUrl = '/DentalClinic/public';

if (!function_exists('setup_password_e')) {
    function setup_password_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

ob_start();
?>

<style>
.setup-page,
.setup-page * {
    box-sizing: border-box;
}

body {
    background: #f5f7fb;
    color: #172033;
}

.setup-page {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 32px 16px;
}

.setup-card {
    width: min(460px, 100%);
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 10px 32px rgba(15, 23, 42, .08);
    padding: 28px;
}

.setup-title {
    margin: 0 0 8px;
    font-size: 24px;
    font-weight: 800;
    color: #0b1f3a;
}

.setup-text {
    margin: 0 0 22px;
    color: #64748b;
    line-height: 1.6;
    font-size: 14px;
}

.setup-alert {
    margin-bottom: 16px;
    padding: 12px 14px;
    border-radius: 8px;
    font-size: 14px;
    line-height: 1.5;
}

.setup-alert.error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.setup-alert.success {
    background: #ecfdf5;
    border: 1px solid #bbf7d0;
    color: #166534;
}

.setup-field {
    margin-bottom: 14px;
}

.setup-label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.setup-input {
    width: 100%;
    height: 44px;
    border: 1px solid #d7dde8;
    border-radius: 8px;
    padding: 0 12px;
    font-size: 14px;
    outline: none;
}

.setup-input:focus {
    border-color: #0d9e8c;
    box-shadow: 0 0 0 3px rgba(13, 158, 140, .12);
}

.setup-btn {
    width: 100%;
    min-height: 44px;
    border: 0;
    border-radius: 8px;
    background: #0b1f3a;
    color: #ffffff;
    font-weight: 800;
    cursor: pointer;
}

.setup-link {
    display: inline-block;
    margin-top: 16px;
    color: #0d9e8c;
    font-weight: 700;
    text-decoration: none;
}
</style>

<div class="setup-page">
    <main class="setup-card">
        <h1 class="setup-title">Set your patient portal password</h1>
        <p class="setup-text">
            Create your own password to activate your account. You will use this account to view appointments and records online.
        </p>

        <?php if ($flash_error): ?>
            <div class="setup-alert error"><?= setup_password_e($flash_error) ?></div>
        <?php endif; ?>

        <?php if ($flash_success): ?>
            <div class="setup-alert success"><?= setup_password_e($flash_success) ?></div>
        <?php endif; ?>

        <?php if (!$isValidToken): ?>
            <div class="setup-alert error">Setup link is invalid or expired.</div>
            <a class="setup-link" href="<?= setup_password_e($baseUrl . '/login') ?>">Back to login</a>
        <?php else: ?>
            <form method="POST" action="<?= setup_password_e($baseUrl . '/setup-password') ?>" autocomplete="off">
                <?= Csrf::inputField(); ?>
                <input type="hidden" name="token" value="<?= setup_password_e($token) ?>">

                <div class="setup-field">
                    <label class="setup-label" for="password">New password</label>
                    <input class="setup-input" type="password" id="password" name="password" minlength="8" required>
                </div>

                <div class="setup-field">
                    <label class="setup-label" for="password_confirmation">Confirm password</label>
                    <input class="setup-input" type="password" id="password_confirmation" name="password_confirmation" minlength="8" required>
                </div>

                <button type="submit" class="setup-btn">Activate Account</button>
            </form>
        <?php endif; ?>
    </main>
</div>

<?php
$content = ob_get_clean();
$title = 'Set Password — Patient Portal';
$layout = __DIR__ . '/../layouts/main.php';

if (is_file($layout)) {
    require $layout;
} else {
    echo $content;
}