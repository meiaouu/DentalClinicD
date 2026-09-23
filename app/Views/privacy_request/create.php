<?php

use App\Core\Csrf;

$old = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$success = $success ?? null;
$authUser = isset($authUser) && is_array($authUser) ? $authUser : null;
$patient = isset($patient) && is_array($patient) ? $patient : null;

$baseUrl = '/DentalClinic/public';

if (!function_exists('privacy_request_e')) {
    function privacy_request_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$fullName = trim((string) ($old['full_name'] ?? ''));

if ($patient) {
    $fullName = trim((string) (($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')));
}

$email = $patient['email'] ?? ($old['email'] ?? '');
$contact = $patient['contact_number'] ?? ($old['contact_number'] ?? '');

ob_start();
?>

<style>
.pr-page {
    min-height: 100vh;
    background: #faf8f4;
    padding: 42px 16px;
}

.pr-card {
    max-width: 720px;
    margin: 0 auto;
    background: #ffffff;
    border: 1px solid #e4e1da;
    border-radius: 10px;
    padding: 28px;
    box-shadow: 0 8px 28px rgba(11, 31, 58, .08);
}

.pr-title {
    margin: 0 0 8px;
    font-size: 28px;
    color: #0b1f3a;
}

.pr-text {
    color: #6e6860;
    line-height: 1.6;
    margin-bottom: 20px;
}

.pr-alert {
    padding: 12px 14px;
    border-radius: 8px;
    margin-bottom: 14px;
    font-size: 14px;
}

.pr-alert.error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.pr-alert.success {
    background: #ecfdf5;
    border: 1px solid #bbf7d0;
    color: #166534;
}

.pr-field {
    margin-bottom: 14px;
}

.pr-label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 800;
    color: #334155;
}

.pr-input,
.pr-select,
.pr-textarea {
    width: 100%;
    border: 1px solid #d7dde8;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 14px;
}

.pr-textarea {
    min-height: 130px;
    resize: vertical;
}

.pr-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.pr-btn {
    min-height: 42px;
    border: 0;
    border-radius: 8px;
    padding: 0 18px;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.pr-btn.primary {
    background: #0b1f3a;
    color: #ffffff;
}

.pr-btn.secondary {
    border: 1px solid #d7dde8;
    color: #0b1f3a;
    background: #ffffff;
}
</style>

<div class="pr-page">
    <main class="pr-card">
        <h1 class="pr-title">Submit Privacy Request</h1>
        <p class="pr-text">
            Use this form to request access, correction, deletion/blocking review, objection, or another privacy-related concern.
        </p>

        <?php if ($success): ?>
            <div class="pr-alert success"><?= privacy_request_e($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="pr-alert error">
                <?php foreach ($errors as $group): ?>
                    <?php foreach ((array) $group as $message): ?>
                        <div><?= privacy_request_e($message) ?></div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= privacy_request_e($baseUrl . '/privacy-request') ?>">
            <?= Csrf::inputField(); ?>

            <div class="pr-field">
                <label class="pr-label">Full Name</label>
                <input class="pr-input" type="text" name="full_name" value="<?= privacy_request_e($fullName) ?>" required <?= $patient ? 'readonly' : '' ?>>
            </div>

            <div class="pr-field">
                <label class="pr-label">Email</label>
                <input class="pr-input" type="email" name="email" value="<?= privacy_request_e($email) ?>">
            </div>

            <div class="pr-field">
                <label class="pr-label">Contact Number</label>
                <input class="pr-input" type="text" name="contact_number" value="<?= privacy_request_e($contact) ?>">
            </div>

            <div class="pr-field">
                <label class="pr-label">Request Type</label>
                <select class="pr-select" name="request_type" required>
                    <?php $selectedType = (string) ($old['request_type'] ?? ''); ?>
                    <option value="">Select request type</option>
                    <option value="access" <?= $selectedType === 'access' ? 'selected' : '' ?>>Access</option>
                    <option value="correction" <?= $selectedType === 'correction' ? 'selected' : '' ?>>Correction</option>
                    <option value="deletion_blocking" <?= $selectedType === 'deletion_blocking' ? 'selected' : '' ?>>Deletion / Blocking Review</option>
                    <option value="objection" <?= $selectedType === 'objection' ? 'selected' : '' ?>>Objection</option>
                    <option value="other" <?= $selectedType === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <div class="pr-field">
                <label class="pr-label">Request Details</label>
                <textarea class="pr-textarea" name="request_details" required><?= privacy_request_e($old['request_details'] ?? '') ?></textarea>
            </div>

            <div class="pr-actions">
                <button type="submit" class="pr-btn primary">Submit Request</button>
                <a href="<?= privacy_request_e($baseUrl . '/privacy-notice') ?>" class="pr-btn secondary">Privacy Notice</a>
            </div>
        </form>
    </main>
</div>

<?php
$content = ob_get_clean();
$title = 'Privacy Request';

$layout = __DIR__ . '/../layouts/main.php';

if (is_file($layout)) {
    require $layout;
} else {
    echo $content;
}