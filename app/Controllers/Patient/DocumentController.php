<?php

namespace App\Controllers\Patient;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use PDO;
use RuntimeException;
use Throwable;

class DocumentController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function index(): void
    {
        Auth::requireRole('patient');

        $patient = $this->getCurrentPatient();

        if (!$patient) {
            http_response_code(403);
            exit('Patient profile not found.');
        }

        $patientId = (int) $patient['patient_id'];
        $documents = $this->getPatientDocuments($patientId);

        View::render('patient/documents/index', [
            'patient' => $patient,
            'documents' => $documents,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function upload(): void
    {
        Auth::requireRole('patient');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $patient = $this->getCurrentPatient();

        if (!$patient) {
            http_response_code(403);
            exit('Patient profile not found.');
        }

        $patientId = (int) $patient['patient_id'];
        $userId = (int) (Auth::user()['user_id'] ?? 0);

        try {
            if ($patientId <= 0 || $userId <= 0) {
                throw new RuntimeException('Invalid patient account.');
            }

            if (empty($_FILES['document_file']) || !is_array($_FILES['document_file'])) {
                throw new RuntimeException('Please select a document to upload.');
            }

            $file = $_FILES['document_file'];

            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Upload failed. Please choose a valid file.');
            }

            $maxSize = 10 * 1024 * 1024;

            if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > $maxSize) {
                throw new RuntimeException('File must not exceed 10MB.');
            }

            $tmpPath = (string) ($file['tmp_name'] ?? '');

            if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
                throw new RuntimeException('Invalid uploaded file.');
            }

            $originalName = $this->cleanOriginalFileName((string) ($file['name'] ?? 'document'));
            $mimeType = $this->detectMimeType($tmpPath);
            $extension = $this->allowedDocumentExtension($mimeType, $originalName);

            $category = $this->allowedDocumentCategory($_POST['file_category'] ?? 'other');
            $description = $this->nullableText($_POST['description'] ?? '', 1000);

            $projectRoot = dirname(__DIR__, 3);
            $storageDir = $projectRoot . '/storage/patient_documents/' . $patientId;

            if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true)) {
                throw new RuntimeException('Unable to create document storage folder.');
            }

            $storedFileName = 'patient_doc_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $absolutePath = $storageDir . '/' . $storedFileName;
            $relativePath = 'storage/patient_documents/' . $patientId . '/' . $storedFileName;

            if (!move_uploaded_file($tmpPath, $absolutePath)) {
                throw new RuntimeException('Unable to save uploaded document.');
            }

            $stmt = $this->db->prepare("
                INSERT INTO attachments (
                    patient_id,
                    appointment_id,
                    examination_id,
                    treatment_id,
                    uploaded_by,
                    file_category,
                    original_file_name,
                    stored_file_name,
                    file_path,
                    mime_type,
                    file_size,
                    description,
                    created_at
                ) VALUES (
                    :patient_id,
                    NULL,
                    NULL,
                    NULL,
                    :uploaded_by,
                    :file_category,
                    :original_file_name,
                    :stored_file_name,
                    :file_path,
                    :mime_type,
                    :file_size,
                    :description,
                    NOW()
                )
            ");

            $stmt->execute([
                ':patient_id' => $patientId,
                ':uploaded_by' => $userId,
                ':file_category' => $category,
                ':original_file_name' => $originalName,
                ':stored_file_name' => $storedFileName,
                ':file_path' => $relativePath,
                ':mime_type' => $mimeType,
                ':file_size' => (int) $file['size'],
                ':description' => $description,
            ]);

            Session::set('flash_success', 'Document uploaded successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: /DentalClinic/public/patient/documents');
        exit;
    }

    public function file(): void
    {
        Auth::requireRole('patient');

        $patient = $this->getCurrentPatient();

        if (!$patient) {
            http_response_code(403);
            exit('Patient profile not found.');
        }

        $patientId = (int) $patient['patient_id'];
        $attachmentId = (int) ($_GET['id'] ?? 0);
        $mode = strtolower(trim((string) ($_GET['mode'] ?? 'inline')));

        if ($attachmentId <= 0) {
            http_response_code(404);
            exit('Document not found.');
        }

        $document = $this->findOwnDocument($attachmentId, $patientId);

        if (!$document) {
            http_response_code(403);
            exit('Document not found or access denied.');
        }

        $projectRoot = dirname(__DIR__, 3);
        $filePath = $projectRoot . '/' . ltrim((string) ($document['file_path'] ?? ''), '/\\');

        if (!is_file($filePath)) {
            http_response_code(404);
            exit('File not found.');
        }

        $mimeType = (string) ($document['mime_type'] ?? 'application/octet-stream');
        $originalName = $this->cleanOriginalFileName((string) ($document['original_file_name'] ?? 'document'));

        $disposition = ($mode === 'download' || !$this->isPreviewableDocumentMime($mimeType))
            ? 'attachment'
            : 'inline';

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $originalName) . '"');

        readfile($filePath);
        exit;
    }

    public function delete(): void
    {
        Auth::requireRole('patient');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $patient = $this->getCurrentPatient();

        if (!$patient) {
            http_response_code(403);
            exit('Patient profile not found.');
        }

        $patientId = (int) $patient['patient_id'];
        $attachmentId = (int) ($_POST['attachment_id'] ?? 0);

        try {
            if ($attachmentId <= 0 || $patientId <= 0) {
                throw new RuntimeException('Invalid document record.');
            }

            $document = $this->findOwnDocument($attachmentId, $patientId);

            if (!$document) {
                throw new RuntimeException('Document not found or access denied.');
            }

            $projectRoot = dirname(__DIR__, 3);
            $filePath = $projectRoot . '/' . ltrim((string) ($document['file_path'] ?? ''), '/\\');

            $stmt = $this->db->prepare("
                DELETE FROM attachments
                WHERE attachment_id = :attachment_id
                  AND patient_id = :patient_id
                LIMIT 1
            ");

            $stmt->execute([
                ':attachment_id' => $attachmentId,
                ':patient_id' => $patientId,
            ]);

            if (is_file($filePath)) {
                @unlink($filePath);
            }

            Session::set('flash_success', 'Document deleted successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: /DentalClinic/public/patient/documents');
        exit;
    }

    private function getCurrentPatient(): ?array
    {
        $user = Auth::user();
        $userId = (int) ($user['user_id'] ?? 0);

        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM patients
            WHERE user_id = :user_id
              AND profile_status = 'active'
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        return $patient ?: null;
    }

    private function getPatientDocuments(int $patientId): array
    {
        if ($patientId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                a.*,
                u.first_name AS uploaded_by_first_name,
                u.middle_name AS uploaded_by_middle_name,
                u.last_name AS uploaded_by_last_name
            FROM attachments a
            LEFT JOIN users u ON u.user_id = a.uploaded_by
            WHERE a.patient_id = :patient_id
            ORDER BY a.created_at DESC, a.attachment_id DESC
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function findOwnDocument(int $attachmentId, int $patientId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM attachments
            WHERE attachment_id = :attachment_id
              AND patient_id = :patient_id
            LIMIT 1
        ");

        $stmt->execute([
            ':attachment_id' => $attachmentId,
            ':patient_id' => $patientId,
        ]);

        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        return $document ?: null;
    }

    private function detectMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if (!$finfo) {
            throw new RuntimeException('File validation is not available.');
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
    }

    private function allowedDocumentExtension(string $mimeType, string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $allowed = [
            'application/pdf' => ['pdf'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
        ];

        if (!isset($allowed[$mimeType])) {
            throw new RuntimeException('Only PDF, JPG, PNG, and WEBP files are allowed.');
        }

        if (!in_array($extension, $allowed[$mimeType], true)) {
            throw new RuntimeException('File extension does not match the uploaded file type.');
        }

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    private function allowedDocumentCategory($value): string
    {
        $value = strtolower(trim((string) $value));

        $allowed = [
            'xray',
            'consent_form',
            'prescription',
            'referral',
            'medical_certificate',
            'lab_result',
            'treatment_photo',
            'other',
        ];

        return in_array($value, $allowed, true) ? $value : 'other';
    }

    private function nullableText($value, int $maxLength = 1000): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLength)
            : substr($value, 0, $maxLength);
    }

    private function cleanOriginalFileName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: 'document';
        $name = trim($name);

        return $name !== '' ? $name : 'document';
    }

    private function isPreviewableDocumentMime(string $mimeType): bool
    {
        return $mimeType === 'application/pdf' || str_starts_with($mimeType, 'image/');
    }
}