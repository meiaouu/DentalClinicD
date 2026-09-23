<?php

use App\Core\Csrf;

$request = isset($request) && is_array($request) ? $request : [];
$success = $success ?? null;
$errors = isset($errors) && is_array($errors) ? $errors : [];
$baseUrl = '/DentalClinic/public';

if (!function_exists('staff_pr_show_e')) {
    function staff_pr_show_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

ob_start();
?>

<style>
.staff-pr-show {
    padding: 28px;
}

.staff-pr-card {
    max-width: 900px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 24px;
}

.staff-pr-title {
    margin: 0 0 18px;
    color: #0b1f3a;
}

.staff-pr-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}

.staff-pr-item {
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
}

.staff-pr-label {
    font-size: 11px;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 800;
    margin-bottom: 4px;
}

.staff-pr-value {
    color: #0f172a;
    font-size: 14px;
}

.staff-pr-details {
    white-space: pre-wrap;
    line-height: 1.7;
}

.staff-pr-field {
    margin-bottom: 14px;
}

.staff-pr-select,
.staff-pr-textarea {
    width: 100%;
    border: 1px solid #d7dde8;
    border-radius: 8px;
    padding: 10px 12px;
}

.staff-pr-textarea {
    min-height: 120px;
    resize: vertical;
}

.staff-pr-btn {
    min-height: 40px;
    border: 0;
    border-radius: 8px;
    padding: 0 16px;
    font-weight: 800;
    cursor: pointer;
    background: #0b1f3a;
    color: #ffffff;
}

.staff-pr-link {
    display: inline-flex;
    margin-top: 16px;
    color: #0b1f3a;
    text-decoration: none;
    font-weight: 800;
}

.staff-pr-alert {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 14px;
}

.staff-pr-alert.success {
    background: #ecfdf5;
    color: #166534;
}

.staff-pr-alert.error {
    background: #fef2f2;
    color: #991b1b;
}
</style>

<div class="staff-pr-show">
    <main class="staff-pr-card">
        <h1 class="staff-pr-title">Privacy Request Details</h1>

        <?php if ($success): ?>
            <div class="staff-pr-alert success"><?= staff_pr_show_e($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="staff-pr-alert error">
                <?php foreach ($errors as $group): ?>
                    <?php foreach ((array) $group as $message): ?>
                        <div><?= staff_pr_show_e($message) ?></div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="staff-pr-grid">
            <div class="staff-pr-item">
                <div class="staff-pr-label">Requester</div>
                <div class="staff-pr-value"><?= staff_pr_show_e($request['full_name'] ?? '') ?></div>
            </div>

            <div class="staff-pr-item">
                <div class="staff-pr-label">Status</div>
                <div class="staff-pr-value"><?= staff_pr_show_e($request['status'] ?? '') ?></div>
            </div>

            <div class="staff-pr-item">
                <div class="staff-pr-label">Email</div>
                <div class="staff-pr-value"><?= staff_pr_show_e($request['email'] ?? '') ?></div>
            </div>

            <div class="staff-pr-item">
                <div class="staff-pr-label">Contact</div>
                <div class="staff-pr-value"><?= staff_pr_show_e($request['contact_number'] ?? '') ?></div>
            </div>

            <div class="staff-pr-item">
                <div class="staff-pr-label">Type</div>
                <div class="staff-pr-value"><?= staff_pr_show_e(str_replace('_', ' ', ucfirst((string) ($request['request_type'] ?? '')))) ?></div>
            </div>

            <div class="staff-pr-item">
                <div class="staff-pr-label">Submitted</div>
                <div class="staff-pr-value"><?= staff_pr_show_e($request['created_at'] ?? '') ?></div>
            </div>
        </div>

        <div class="staff-pr-item" style="margin-bottom:20px;">
            <div class="staff-pr-label">Request Details</div>
            <div class="staff-pr-value staff-pr-details"><?= staff_pr_show_e($request['request_details'] ?? '') ?></div>
        </div>

        <form method="POST" action="<?= staff_pr_show_e($baseUrl . '/staff/privacy-requests/update') ?>">
            <?= Csrf::inputField(); ?>
            <input type="hidden" name="privacy_request_id" value="<?= (int) ($request['privacy_request_id'] ?? 0) ?>">

            <div class="staff-pr-field">
                <label class="staff-pr-label">Update Status</label>
                <?php $status = (string) ($request['status'] ?? 'pending'); ?>
                <select class="staff-pr-select" name="status" required>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="reviewing" <?= $status === 'reviewing' ? 'selected' : '' ?>>Reviewing</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>

            <div class="staff-pr-field">
                <label class="staff-pr-label">Response Notes</label>
                <textarea class="staff-pr-textarea" name="response_notes"><?= staff_pr_show_e($request['response_notes'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="staff-pr-btn">Save Update</button>
        </form>

        <a class="staff-pr-link" href="<?= staff_pr_show_e($baseUrl . '/staff/privacy-requests') ?>">Back to Privacy Requests</a>
    </main>
</div>

<?php
$content = ob_get_clean();
$title = 'Privacy Request Details';

$layout = __DIR__ . '/../../layouts/main.php';

if (is_file($layout)) {
    require $layout;
} else {
    echo $content;
}