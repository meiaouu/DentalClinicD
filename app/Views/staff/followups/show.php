<?php

use App\Core\Csrf;

$mode = $mode ?? 'list';
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

ob_start();
?>
<div class="card">
    <h1 style="margin-top:0;">Follow-ups</h1>

    <?php if ($flash_success): ?>
        <p style="color:green;"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <p style="color:#b91c1c;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($mode === 'list'): ?>
        <?php
        $followUps = $followUps ?? [];
        $total = $total ?? 0;
        $page = $page ?? 1;
        $perPage = $perPage ?? 15;
        $status = $status ?? '';
        $totalPages = max(1, (int) ceil($total / $perPage));
        ?>

        <form method="GET" action="/staff/followups" style="margin-bottom:20px;">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    <?php foreach (['scheduled', 'completed', 'cancelled', 'missed'] as $statusOption): ?>
                        <option value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>" <?= $status === $statusOption ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">Filter</button>
        </form>

        <?php if (empty($followUps)): ?>
            <p>No follow-ups found.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($followUps as $item): ?>
                    <li>
                        <a href="/staff/followups/show?id=<?= (int) $item['follow_up_id'] ?>">
                            <?= htmlspecialchars(trim(($item['patient_last_name'] ?? '') . ', ' . ($item['patient_first_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        |
                        <?= htmlspecialchars((string) ($item['recommended_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        |
                        <?= htmlspecialchars((string) ($item['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        |
                        <?= htmlspecialchars((string) ($item['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div style="margin-top:20px;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="/staff/followups?page=<?= $i ?><?= $status !== '' ? '&status=' . urlencode($status) : '' ?>" style="margin-right:8px;<?= $i === $page ? 'font-weight:bold;' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <?php
        $followUp = $followUp ?? [];
        $reminders = $reminders ?? [];
        ?>
        <p><strong>Patient:</strong> <?= htmlspecialchars(trim(($followUp['patient_first_name'] ?? '') . ' ' . ($followUp['patient_middle_name'] ?? '') . ' ' . ($followUp['patient_last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Dentist:</strong> Dr. <?= htmlspecialchars(trim(($followUp['dentist_first_name'] ?? '') . ' ' . ($followUp['dentist_last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Treatment:</strong> <?= htmlspecialchars((string) ($followUp['procedure_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Recommended Date:</strong> <?= htmlspecialchars((string) ($followUp['recommended_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Reason:</strong> <?= htmlspecialchars((string) ($followUp['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Status:</strong> <?= htmlspecialchars((string) ($followUp['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Remarks:</strong><br><?= nl2br(htmlspecialchars((string) ($followUp['remarks'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p>

        <hr>

        <h2>Update Status</h2>
        <form method="POST" action="/staff/followups/update-status">
            <?= Csrf::inputField(); ?>
            <input type="hidden" name="follow_up_id" value="<?= (int) ($followUp['follow_up_id'] ?? 0) ?>">

            <div class="form-group">
                <label>Status</label>
                <select name="status" required>
                    <?php foreach (['scheduled', 'completed', 'cancelled', 'missed'] as $statusOption): ?>
                        <option value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>" <?= (($followUp['status'] ?? '') === $statusOption) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" rows="3"></textarea>
            </div>

            <button type="submit">Update Follow-up</button>
        </form>

        <hr>

        <h2>Reminder Queue</h2>
        <?php if (empty($reminders)): ?>
            <p>No reminders queued.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($reminders as $reminder): ?>
                    <li>
                        <?= htmlspecialchars((string) ($reminder['reminder_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        |
                        <?= htmlspecialchars((string) ($reminder['reminder_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        |
                        <?= htmlspecialchars((string) ($reminder['reminder_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p style="margin-top:20px;">
            <a href="/staff/followups">Back to follow-up list</a>
        </p>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$title = 'Follow-ups';
require __DIR__ . '/../../layouts/main.php';