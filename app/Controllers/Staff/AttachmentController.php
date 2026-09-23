<?php

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AttachmentRepository;
use App\Repositories\PatientRepository;
use App\Services\AuditLogService;
use App\Services\FileUploadService;
use PDO;
use RuntimeException;
use Throwable;

class AttachmentController
{
    private AttachmentRepository $attachments;
    private PatientRepository $patients;
    private FileUploadService $uploads;
    private AuditLogService $audit;

    private string $baseUrl = '/DentalClinic/public';

    public function __construct()
    {
        $this->attachments = new AttachmentRepository();
        $this->patients = new PatientRepository();
        $this->uploads = new FileUploadService();
        $this->audit = new AuditLogService();
    }

    public function show(): void
    {
        Auth::requireRole('staff');

        $patientId = (int) ($_GET['patient_id'] ?? 0);
        $patient = $this->patients->findById($patientId);

        if (!$patient) {
            http_response_code(404);
            exit('Patient not found.');
        }

        View::render('staff.attachments.show', [
            'patient' => $patient,
            'attachments' => $this->attachments->getByPatientId($patientId, 50),
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function upload(): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $staffUser = Auth::user();
        $patientId = (int) ($_POST['patient_id'] ?? 0);

        try {
            $patient = $this->patients->findById($patientId);

            if (!$patient) {
                throw new RuntimeException('Patient not found.');
            }

            if (!isset($_FILES['attachment'])) {
                throw new RuntimeException('Please choose a file to upload.');
            }

            $stored = $this->uploads->storeUploadedFile(
                $_FILES['attachment'],
                __DIR__ . '/../../../public/uploads/patient_attachments'
            );

            $db = Database::getConnection();

            $stmt = $db->prepare("
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
                    :appointment_id,
                    :examination_id,
                    :treatment_id,
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
                'patient_id' => $patientId,
                'appointment_id' => !empty($_POST['appointment_id']) ? (int) $_POST['appointment_id'] : null,
                'examination_id' => !empty($_POST['examination_id']) ? (int) $_POST['examination_id'] : null,
                'treatment_id' => !empty($_POST['treatment_id']) ? (int) $_POST['treatment_id'] : null,
                'uploaded_by' => (int) ($staffUser['user_id'] ?? 0),
                'file_category' => trim((string) ($_POST['file_category'] ?? 'document')),
                'original_file_name' => $stored['original_file_name'],
                'stored_file_name' => $stored['stored_file_name'],
                'file_path' => $stored['file_path'],
                'mime_type' => $stored['mime_type'],
                'file_size' => $stored['file_size'],
                'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            ]);

            $this->audit->log(
                (int) ($staffUser['user_id'] ?? 0),
                'attachments',
                'upload',
                'patient',
                $patientId,
                'Uploaded attachment for patient #' . $patientId
            );

            Session::set('flash_success', 'Attachment uploaded successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: ' . $this->baseUrl . '/staff/attachments/show?patient_id=' . urlencode((string) $patientId));
        exit;
    }

    public function file(): void
    {
        Auth::requireRole('staff');

        $attachmentId = (int) ($_GET['id'] ?? 0);

        if ($attachmentId <= 0) {
            http_response_code(404);
            exit('Attachment not found.');
        }

        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT *
            FROM attachments
            WHERE attachment_id = :attachment_id
            LIMIT 1
        ");

        $stmt->execute([
            'attachment_id' => $attachmentId,
        ]);

        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attachment) {
            http_response_code(404);
            exit('Attachment not found.');
        }

        $filePath = trim((string) ($attachment['file_path'] ?? ''));

        if ($filePath === '') {
            http_response_code(404);
            exit('Attachment path is missing.');
        }

        $publicDir = realpath(__DIR__ . '/../../../public');

        if ($publicDir === false) {
            http_response_code(500);
            exit('Public directory not found.');
        }

        $fullPath = $publicDir . '/' . ltrim($filePath, '/');
        $realFilePath = realpath($fullPath);

        if (
            $realFilePath === false ||
            strpos($realFilePath, $publicDir) !== 0 ||
            !is_file($realFilePath)
        ) {
            http_response_code(404);
            exit('File not found.');
        }

        $mimeType = trim((string) ($attachment['mime_type'] ?? 'application/octet-stream'));
        $originalName = basename((string) ($attachment['original_file_name'] ?? 'attachment'));

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($realFilePath));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $originalName) . '"');

        readfile($realFilePath);
        exit;
    }
}