<?php

use App\Core\Csrf;

$pageTitle = 'My Documents';
$baseUrl = '/DentalClinic/public';

$documents = isset($documents) && is_array($documents) ? $documents : [];
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('documentSize')) {
    function documentSize($bytes): string
    {
        $bytes = (int) $bytes;

        if ($bytes <= 0) {
            return '—';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        return number_format($bytes / 1024, 2) . ' KB';
    }
}

if (!function_exists('documentCategoryLabel')) {
    function documentCategoryLabel(string $category): string
    {
        $labels = [
            'xray' => 'X-ray',
            'consent_form' => 'Consent Form',
            'prescription' => 'Prescription',
            'referral' => 'Referral Letter',
            'medical_certificate' => 'Medical Certificate',
            'lab_result' => 'Lab Result',
            'treatment_photo' => 'Treatment Photo',
            'other' => 'Other Document',
        ];

        return $labels[$category] ?? 'Other Document';
    }
}

ob_start();
?>

<style>
.patient-documents-page {
    padding: 28px;
    background: #f3f4f6;
    min-height: calc(100vh - 70px);
    font-family: Arial, sans-serif;
}

.patient-documents-shell {
    max-width: 1100px;
    margin: 0 auto;
}

.documents-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    padding: 18px;
    margin-bottom: 16px;
}

.documents-title {
    margin: 0 0 6px;
    color: #111827;
    font-size: 24px;
    font-weight: 900;
}

.documents-subtitle {
    margin: 0 0 18px;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.5;
}

.flash-message {
    padding: 11px 13px;
    margin-bottom: 12px;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid #dddddd;
}

.flash-message.success {
    color: #166534;
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.flash-message.error {
    color: #b91c1c;
    background: #fef2f2;
    border-color: #fecaca;
}

.document-form {
    display: grid;
    gap: 12px;
}

.document-form label {
    display: block;
    margin-bottom: 6px;
    color: #111827;
    font-size: 12px;
    font-weight: 900;
}

.document-form input,
.document-form select,
.document-form textarea {
    width: 100%;
    min-height: 40px;
    border: 1px solid #d1d5db;
    padding: 8px 10px;
    font-size: 13px;
    box-sizing: border-box;
}

.document-form textarea {
    min-height: 80px;
    resize: vertical;
}

.document-button {
    width: fit-content;
    min-height: 38px;
    padding: 0 14px;
    border: 1px solid #111827;
    background: #111827;
    color: #ffffff;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
}

.document-button:hover {
    background: #0f766e;
    border-color: #0f766e;
}

.documents-table-wrap {
    overflow-x: auto;
}

.documents-table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

.documents-table th,
.documents-table td {
    padding: 12px;
    border-bottom: 1px solid #eeeeee;
    text-align: left;
    font-size: 13px;
    vertical-align: middle;
}

.documents-table th {
    background: #fafafa;
    color: #111827;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}

.document-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.document-link,
.document-delete {
    min-height: 32px;
    padding: 0 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #111827;
    background: #ffffff;
    color: #111827;
    text-decoration: none;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
}

.document-link:hover {
    background: #f3f4f6;
}

.document-delete {
    border-color: #fecaca;
    color: #991b1b;
}

.document-delete:hover {
    background: #fef2f2;
}

.empty-state {
    padding: 18px;
    border: 1px dashed #d1d5db;
    background: #fafafa;
    color: #6b7280;
    text-align: center;
    font-size: 13px;
    font-weight: 700;
}
</style>

<div class="patient-documents-page">
    <div class="patient-documents-shell">

        <div class="documents-card">
            <h1 class="documents-title">My Documents</h1>
            <p class="documents-subtitle">
                Upload X-rays, prescriptions, medical certificates, lab results, or other supporting dental documents.
            </p>

            <?php if ($flash_success): ?>
                <div class="flash-message success"><?= e($flash_success) ?></div>
            <?php endif; ?>

            <?php if ($flash_error): ?>
                <div class="flash-message error"><?= e($flash_error) ?></div>
            <?php endif; ?>

            <form
                method="POST"
                action="<?= e($baseUrl . '/patient/documents/upload') ?>"
                enctype="multipart/form-data"
                class="document-form"
            >
                <?= Csrf::inputField(); ?>

                <div>
                    <label for="file_category">Document Type</label>
                    <select id="file_category" name="file_category" required>
                        <option value="xray">X-ray</option>
                        <option value="consent_form">Consent Form</option>
                        <option value="prescription">Prescription</option>
                        <option value="referral">Referral Letter</option>
                        <option value="medical_certificate">Medical Certificate</option>
                        <option value="lab_result">Lab Result</option>
                        <option value="treatment_photo">Treatment Photo</option>
                        <option value="other">Other Document</option>
                    </select>
                </div>

                <div>
                    <label for="document_file">File</label>
                    <input
                        id="document_file"
                        type="file"
                        name="document_file"
                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                        required
                    >
                </div>

                <div>
                    <label for="description">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        maxlength="1000"
                        placeholder="Example: X-ray taken before consultation"
                    ></textarea>
                </div>

                <button type="submit" class="document-button">
                    Upload Document
                </button>
            </form>
        </div>

        <div class="documents-card">
            <h2 class="documents-title">Uploaded Documents</h2>

            <?php if (empty($documents)): ?>
                <div class="empty-state">No documents uploaded yet.</div>
            <?php else: ?>
                <div class="documents-table-wrap">
                    <table class="documents-table">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th>Size</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($documents as $document): ?>
                                <?php
                                    $attachmentId = (int) ($document['attachment_id'] ?? 0);
                                    $fileUrl = $baseUrl . '/patient/documents/file?id=' . $attachmentId;
                                    $downloadUrl = $fileUrl . '&mode=download';
                                ?>

                                <tr>
                                    <td>
                                        <strong><?= e($document['original_file_name'] ?? 'Document') ?></strong><br>
                                        <small><?= e($document['mime_type'] ?? '') ?></small>
                                    </td>

                                    <td><?= e(documentCategoryLabel((string) ($document['file_category'] ?? 'other'))) ?></td>

                                    <td><?= e($document['description'] ?? '—') ?></td>

                                    <td>
                                        <?php
                                            $createdAt = $document['created_at'] ?? '';
                                            $timestamp = strtotime((string) $createdAt);
                                            echo $timestamp ? e(date('M d, Y', $timestamp)) : '—';
                                        ?>
                                    </td>

                                    <td><?= e(documentSize($document['file_size'] ?? 0)) ?></td>

                                    <td>
                                        <div class="document-actions">
                                            <a class="document-link" href="<?= e($fileUrl) ?>" target="_blank">
                                                View
                                            </a>

                                            <a class="document-link" href="<?= e($downloadUrl) ?>">
                                                Download
                                            </a>

                                            <form
                                                method="POST"
                                                action="<?= e($baseUrl . '/patient/documents/delete') ?>"
                                                onsubmit="return confirm('Delete this document?');"
                                            >
                                                <?= Csrf::inputField(); ?>
                                                <input type="hidden" name="attachment_id" value="<?= (int) $attachmentId ?>">

                                                <button type="submit" class="document-delete">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../layouts/app.php';
?>