<?php

$requests = isset($requests) && is_array($requests) ? $requests : [];
$success = $success ?? null;
$errors = isset($errors) && is_array($errors) ? $errors : [];
$baseUrl = '/DentalClinic/public';

if (!function_exists('patient_pr_e')) {
    function patient_pr_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

ob_start();
?>

<style>
.pr-wrap {
    padding: 28px;
}

.pr-panel {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 24px;
}

.pr-top {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 20px;
}

.pr-title {
    margin: 0;
    color: #0b1f3a;
    font-size: 24px;
}

.pr-btn {
    background: #0b1f3a;
    color: #ffffff;
    min-height: 38px;
    padding: 0 14px;
    border-radius: 8px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    font-weight: 800;
    font-size: 13px;
}

.pr-alert {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 14px;
}

.pr-alert.success {
    background: #ecfdf5;
    color: #166534;
}

.pr-table {
    width: 100%;
    border-collapse: collapse;
}

.pr-table th,
.pr-table td {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    font-size: 13px;
}

.pr-status {
    display: inline-flex;
    padding: 4px 8px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    font-weight: 800;
    font-size: 12px;
}
</style>

<div class="pr-wrap">
    <div class="pr-panel">
        <div class="pr-top">
            <h1 class="pr-title">My Privacy Requests</h1>
            <a class="pr-btn" href="<?= patient_pr_e($baseUrl . '/patient/privacy-requests/create') ?>">New Request</a>
        </div>

        <?php if ($success): ?>
            <div class="pr-alert success"><?= patient_pr_e($success) ?></div>
        <?php endif; ?>

        <table class="pr-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Response</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="4">No privacy requests submitted yet.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><?= patient_pr_e(str_replace('_', ' ', ucfirst((string) $request['request_type']))) ?></td>
                        <td><span class="pr-status"><?= patient_pr_e((string) $request['status']) ?></span></td>
                        <td><?= patient_pr_e((string) $request['created_at']) ?></td>
                        <td><?= patient_pr_e((string) ($request['response_notes'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'My Privacy Requests';

$layout = __DIR__ . '/../../layouts/main.php';

if (is_file($layout)) {
    require $layout;
} else {
    echo $content;
}