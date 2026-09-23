<?php

use App\Core\Csrf;

$old = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$baseUrl = '/DentalClinic/public';

if (!function_exists('patient_pr_create_e')) {
    function patient_pr_create_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

ob_start();
?>

<style>
.pr-form-wrap {
    padding: 28px;
}

.pr-card {
    max-width: 720px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 24px;
}

.pr-title {
    margin: 0 0 18px;
    color: #0b1f3a;
}

.pr-alert {
    padding: 12px;
    border-radius: 8px;
    background: #fef2f2;
    color: #991b1b;
    margin-bottom: 14px;
}

.pr-field {
    margin-bottom: 14px;
}

.pr-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 800;
    font-size: 13px;
}

.pr-select,
.pr-textarea {
    width: 100%;
    border: 1px solid #d7dde8;
    border-radius: 8px;
    padding: 10px 12px;
}

.pr-textarea {
    min-height: 140px;
    resize: vertical;
}

.pr-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.pr-btn {
    min-height: 40px;
    border: 0;
    border-radius: 8px;
    padding: 0 16px;
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
    background: #ffffff;
    color: #0b1f3a;
    border: 1px solid #d7dde8;
}
</style>

<div class="pr-form-wrap">
    <main class="pr-card">
        <h1 class="pr-title">Create Privacy Request</h1>

        <?php if (!empty($errors)): ?>
            <div class="pr-alert">
                <?php foreach ($errors as $group): ?>
                    <?php foreach ((array) $group as $message): ?>
                        <div><?= patient_pr_create_e($message) ?></div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= patient_pr_create_e($baseUrl . '/patient/privacy-requests') ?>">
            <?= Csrf::inputField(); ?>

            <div class="pr-field">
                <label class="pr-label">Request Type</label>
                <?php $selectedType = (string) ($old['request_type'] ?? ''); ?>
                <select class="pr-select" name="request_type" required>
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
                <textarea class="pr-textarea" name="request_details" required><?= patient_pr_create_e($old['request_details'] ?? '') ?></textarea>
            </div>

            <div class="pr-actions">
                <button class="pr-btn primary" type="submit">Submit Request</button>
                <a class="pr-btn secondary" href="<?= patient_pr_create_e($baseUrl . '/patient/privacy-requests') ?>">Cancel</a>
            </div>
        </form>
    </main>
</div>

<?php
$content = ob_get_clean();
$title = 'Create Privacy Request';

$layout = __DIR__ . '/../../layouts/main.php';

if (is_file($layout)) {
    require $layout;
} else {
    echo $content;
}