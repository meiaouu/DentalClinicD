<?php

use App\Core\Csrf;

$patient = $patient ?? [];
$attachments = $attachments ?? [];
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

ob_start();
?>
<div class="card">
    <h1 style="margin-top:0;">Patient Attachments</h1>

    <?php if ($flash_success): ?>
        <p style="color:green;"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <p style="color:#b91c1c;"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <p><strong>Patient:</strong> <?= htmlspecialchars(trim(($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Patient Code:</strong> <?= htmlspecialchars((string) ($patient['patient_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

    <hr>

    <h2>Upload Attachment</h2>
    <form method="POST" action="/staff/attachments/upload" enctype="multipart/form-data">
        <?= Csrf::inputField(); ?>
        <input type="hidden" name="patient_id" value="<?= (int) ($patient['patient_id'] ?? 0) ?>">

        <div class="form-group">
            <label>File Category</label>
            <select name="file_category">
                <option value="xray">X-ray</option>
                <option value="document">Document</option>
                <option value="prescription">Prescription</option>
                <option value="treatment_image">Treatment Image</option>
            </select>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3"></textarea>
        </div>

        <div class="form-group">
            <label>Choose File</label>
            <input type="file" name="attachment" required>
        </div>

        <button type="submit">Upload Attachment</button>
    </form>

    <hr>

    <h2>Attachment List</h2>
    <?php if (empty($attachments)): ?>
        <p>No attachments uploaded yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($attachments as $attachment): ?>
                <li>
                    <?= htmlspecialchars((string) ($attachment['original_file_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    |
                    <?= htmlspecialchars((string) ($attachment['file_category'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    |
                    <?= htmlspecialchars((string) ($attachment['mime_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    |
                    <?= number_format(((int) ($attachment['file_size'] ?? 0)) / 1024, 2) ?> KB
                    <?php if (!empty($attachment['description'])): ?>
                        | <?= htmlspecialchars((string) $attachment['description'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p style="margin-top:20px;">
        <a href="/staff/patients/show?id=<?= (int) ($patient['patient_id'] ?? 0) ?>">Back to patient record</a>
    </p>
</div>
<?php
$content = ob_get_clean();
$title = 'Patient Attachments';
require __DIR__ . '/../../layouts/main.php';