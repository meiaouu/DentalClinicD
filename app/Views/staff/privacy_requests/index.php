<?php

$requests = isset($requests) && is_array($requests) ? $requests : [];
$filters = isset($filters) && is_array($filters) ? $filters : [];
$success = $success ?? null;
$errors = isset($errors) && is_array($errors) ? $errors : [];
$baseUrl = '/DentalClinic/public';

if (!function_exists('staff_pr_e')) {
    function staff_pr_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

ob_start();
?>

<style>
.staff-pr-wrap {
    padding: 28px;
}

.staff-pr-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 24px;
}

.staff-pr-title {
    margin: 0 0 18px;
    color: #0b1f3a;
}

.staff-pr-filter {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.staff-pr-input,
.staff-pr-select {
    min-height: 38px;
    border: 1px solid #d7dde8;
    border-radius: 8px;
    padding: 0 10px;
}

.staff-pr-btn {
    min-height: 38px;
    border: 0;
    border-radius: 8px;
    padding: 0 14px;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
    background: #0b1f3a;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
}

.staff-pr-table {
    width: 100%;
    border-collapse: collapse;
}

.staff-pr-table th,
.staff-pr-table td {
    border-bottom: 1px solid #e5e7eb;
    padding: 12px;
    text-align: left;
    font-size: 13px;
}

.staff-pr-status {
    display: inline-flex;
    padding: 4px 8px;
    border-radius: 999px;
    background: #f1f5f9;
    color: #334155;
    font-weight: 800;
    font-size: 12px;
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

<div class="staff-pr-wrap">
    <main class="staff-pr-card">
        <h1 class="staff-pr-title">Privacy Requests</h1>

        <?php if ($success): ?>
            <div class="staff-pr-alert success"><?= staff_pr_e($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="staff-pr-alert error">
                <?php foreach ($errors as $group): ?>
                    <?php foreach ((array) $group as $message): ?>
                        <div><?= staff_pr_e($message) ?></div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="staff-pr-filter" method="GET" action="<?= staff_pr_e($baseUrl . '/staff/privacy-requests') ?>">
            <input class="staff-pr-input" type="text" name="keyword" placeholder="Search name, email, contact..." value="<?= staff_pr_e($filters['keyword'] ?? '') ?>">

            <?php $status = (string) ($filters['status'] ?? ''); ?>
            <select class="staff-pr-select" name="status">
                <option value="">All statuses</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="reviewing" <?= $status === 'reviewing' ? 'selected' : '' ?>>Reviewing</option>
                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
            </select>

            <?php $type = (string) ($filters['request_type'] ?? ''); ?>
            <select class="staff-pr-select" name="request_type">
                <option value="">All types</option>
                <option value="access" <?= $type === 'access' ? 'selected' : '' ?>>Access</option>
                <option value="correction" <?= $type === 'correction' ? 'selected' : '' ?>>Correction</option>
                <option value="deletion_blocking" <?= $type === 'deletion_blocking' ? 'selected' : '' ?>>Deletion / Blocking</option>
                <option value="objection" <?= $type === 'objection' ? 'selected' : '' ?>>Objection</option>
                <option value="other" <?= $type === 'other' ? 'selected' : '' ?>>Other</option>
            </select>

            <button type="submit" class="staff-pr-btn">Filter</button>
        </form>

        <table class="staff-pr-table">
            <thead>
                <tr>
                    <th>Requester</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="5">No privacy requests found.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td>
                            <strong><?= staff_pr_e($request['full_name'] ?? '') ?></strong><br>
                            <?= staff_pr_e($request['email'] ?? '') ?><br>
                            <?= staff_pr_e($request['contact_number'] ?? '') ?>
                        </td>
                        <td><?= staff_pr_e(str_replace('_', ' ', ucfirst((string) $request['request_type']))) ?></td>
                        <td><span class="staff-pr-status"><?= staff_pr_e((string) $request['status']) ?></span></td>
                        <td><?= staff_pr_e((string) $request['created_at']) ?></td>
                        <td>
                            <a class="staff-pr-btn" href="<?= staff_pr_e($baseUrl . '/staff/privacy-requests/show?id=' . (int) $request['privacy_request_id']) ?>">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</div>

<?php
$content = ob_get_clean();
$title = 'Privacy Requests';

$layout = __DIR__ . '/../../layouts/main.php';

if (is_file($layout)) {
    require $layout;
} else {
    echo $content;
}