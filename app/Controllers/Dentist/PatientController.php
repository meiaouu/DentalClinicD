<?php

namespace App\Controllers\Dentist;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Repositories\BillingRepository;
use App\Repositories\NotificationRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;
use App\Services\PatientAccessPolicy;
use App\Services\PrivacyAuditService;


class PatientController
{
    private PDO $db;
    private NotificationRepository $notifications;
    private BillingRepository $billings;
private PatientAccessPolicy $patientAccess;
private PrivacyAuditService $privacyAudit;


    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->notifications = new NotificationRepository();
        $this->billings = new BillingRepository();
        $this->patientAccess = new PatientAccessPolicy();
$this->privacyAudit = new PrivacyAuditService();
    }

    public function index(): void
    {
        Auth::requireRole('dentist');

        $dentist = $this->getCurrentDentist();

        if (!$dentist) {
            http_response_code(403);
            exit('Dentist profile not found.');
        }

        $dentistId = (int) $dentist['dentist_id'];
        $search = trim((string) ($_GET['search'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));

        $patients = $this->getAssignedPatients($dentistId, $search, $status);

        View::render('dentist.patients.index', [
            'patients' => $patients,
            'search' => $search,
            'status' => $status,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function show(): void
{
    Auth::requireRole('dentist');

    $dentist = $this->getCurrentDentist();

    if (!$dentist) {
        http_response_code(403);
        exit('Dentist profile not found.');
    }

    $dentistId = (int) $dentist['dentist_id'];
    $patientId = (int) ($_GET['id'] ?? $_GET['patient_id'] ?? 0);

    if ($patientId <= 0) {
        http_response_code(404);
        exit('Patient not found.');
    }

    if (!$this->dentistCanAccessPatient($dentistId, $patientId)) {
        http_response_code(403);
        exit('You are not allowed to view this patient record.');
    }

    $patient = $this->findPatient($patientId);

    if (!$patient) {
        http_response_code(404);
        exit('Patient not found.');
    }

    $medicalHistory = $this->getMedicalHistory($patientId) ?? [];
    $dentalHistory = $this->getDentalHistory($patientId) ?? [];
    $appointments = $this->getPatientAppointmentsForDentist($patientId, $dentistId);
    $treatments = $this->getPatientTreatmentsForDentist($patientId, $dentistId);
    $todayTreatmentAppointment = $this->findTodayTreatmentAppointment($patientId, $dentistId);

    $latestAppointmentId = 0;

    if (!empty($todayTreatmentAppointment['appointment_id'])) {
        $latestAppointmentId = (int) $todayTreatmentAppointment['appointment_id'];
    } else {
        foreach ($appointments as $appointmentItem) {
            $candidateAppointmentId = (int) ($appointmentItem['appointment_id'] ?? 0);

            if ($candidateAppointmentId > 0) {
                $latestAppointmentId = $candidateAppointmentId;
                break;
            }
        }
    }

    $odontogramEntries = $this->getPatientOdontogramEntriesForDentist($patientId, $dentistId);
    $odontogramEntriesByTooth = $this->groupOdontogramEntriesByTooth($odontogramEntries);

    $documents = $this->getPatientDocumentsForDentist($patientId, $dentistId);
    $documentCount = count($documents);
    $latestDocument = $documents[0] ?? null;
    

    $user = Auth::user();

$this->patientAccess->assertCanViewPatient($user, $patientId);

$this->privacyAudit->log(
    (int) ($user['user_id'] ?? 0),
    'patients',
    'view',
    'patient',
    $patientId,
    'Dentist viewed patient chart.'
);

    View::render('dentist/patients/show', [
        'patient' => $patient,
        'medicalHistory' => $medicalHistory,
        'dentalHistory' => $dentalHistory,
        'appointments' => $appointments,
        'treatments' => $treatments,
        'odontogramEntries' => $odontogramEntries,
        'odontogramEntriesByTooth' => $odontogramEntriesByTooth,
        'todayTreatmentAppointment' => $todayTreatmentAppointment,
        'latestAppointmentId' => $latestAppointmentId,

        'documents' => $documents,
        'documentCount' => $documentCount,
        'latestDocument' => $latestDocument,

        'flash_success' => Session::get('flash_success'),
        'flash_error' => Session::get('flash_error'),
    ]);

    Session::remove('flash_success');
    Session::remove('flash_error');
}



public function startTreatmentRecord(): void
{
    Auth::requireRole('dentist');
    $user = Auth::user();
$actorUserId = (int) ($user['user_id'] ?? 0);
    

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $dentist = $this->getCurrentDentist();

    if (!$dentist) {
        http_response_code(403);
        exit('Dentist profile not found.');
    }

    $dentistId = (int) $dentist['dentist_id'];
    $patientId = (int) ($_POST['patient_id'] ?? 0);
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

    if ($patientId <= 0 || $appointmentId <= 0) {
        Session::set('flash_error', 'Invalid patient or appointment.');
        header('Location: ' . $this->url('/dentist/patients'));
        exit;
    }

    try {
        if (!$this->dentistCanAccessPatient($dentistId, $patientId)) {
            throw new RuntimeException('You are not allowed to update this patient record.');
        }

        $this->db->beginTransaction();

        $appointment = $this->findAppointmentForTreatmentForUpdate(
            $appointmentId,
            $patientId,
            $dentistId
        );

        if (!$appointment) {
            throw new RuntimeException('Linked appointment not found.');
        }

        $oldStatus = strtolower(trim((string) ($appointment['status'] ?? '')));

        if (in_array($oldStatus, ['cancelled', 'rejected', 'no_show', 'completed'], true)) {
            throw new RuntimeException('This appointment cannot be started.');
        }

        if (!in_array($oldStatus, ['confirmed', 'rescheduled', 'checked_in', 'in_progress'], true)) {
            throw new RuntimeException('Only confirmed, rescheduled, checked-in, or in-progress appointments can be started.');
        }

        $now = $this->serverDateTime();

        if (empty($appointment['actual_started_at'])) {
            $stmt = $this->db->prepare("
                UPDATE appointments
                SET
                    status = 'in_progress',
                    actual_started_at = :actual_started_at,
                    updated_at = :updated_at
                WHERE appointment_id = :appointment_id
                  AND patient_id = :patient_id
                  AND dentist_id = :dentist_id
                LIMIT 1
            ");

            $stmt->execute([
                ':actual_started_at' => $now,
                ':updated_at' => $now,
                ':appointment_id' => $appointmentId,
                ':patient_id' => $patientId,
                ':dentist_id' => $dentistId,
            ]);

            if ($oldStatus !== 'in_progress') {
                $this->logAppointmentStatusChange(
                    $appointmentId,
                    $oldStatus,
                    'in_progress',
                    'Treatment record started by dentist.'
                );
            }
        }

        $this->db->commit();

        $this->privacyAudit->log(
    $actorUserId,
    'appointments',
    'start_treatment',
    'patient',
    $patientId,
    'Dentist started treatment record and recorded actual start time.'
);

        Session::set('flash_success', 'Treatment started. Actual start time was recorded.');
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        Session::set('flash_error', $e->getMessage());
    }

    header(
        'Location: ' .
        $this->url('/dentist/patients/show?id=' . $patientId .
            '&tab=treatment-records' .
            '&appointment_id=' . $appointmentId .
            '&from_start=1'
        )
    );
    exit;
}



   public function saveTreatment(): void
{
    Auth::requireRole('dentist');

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $dentist = $this->getCurrentDentist();

    if (!$dentist) {
        http_response_code(403);
        exit('Dentist profile not found.');
    }

    $dentistId = (int) $dentist['dentist_id'];
    $patientId = (int) ($_POST['patient_id'] ?? 0);
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

    if ($patientId <= 0) {
        Session::set('flash_error', 'Invalid patient record.');
        header('Location: ' . $this->url('/dentist/patients'));
        exit;
    }

    $billingSyncWarning = null;
    $shouldNotifyStaffCompleted = false;
    $appointmentForNotification = null;

    try {
        if (!$this->dentistCanAccessPatient($dentistId, $patientId)) {
            throw new RuntimeException('You are not allowed to update this patient record.');
        }

        $appointment = null;

        if ($appointmentId > 0) {
            $appointment = $this->findAppointmentForTreatment($appointmentId, $patientId, $dentistId);
        }

        if (!$appointment) {
            $appointment = $this->findTodayTreatmentAppointment($patientId, $dentistId);
            $appointmentId = (int) ($appointment['appointment_id'] ?? 0);
        }

        if (!$appointment) {
            $appointment = $this->findLatestAppointmentForTreatment($patientId, $dentistId);
            $appointmentId = (int) ($appointment['appointment_id'] ?? 0);
        }

        $appointmentForNotification = $appointment;

        $procedureName = $this->nullableText($_POST['procedure_name'] ?? '', 255);

        if ($procedureName === null && $appointment) {
            $procedureName = $this->nullableText($appointment['service_name'] ?? '', 255);
        }

        if ($procedureName === null) {
            throw new RuntimeException('Procedure is required.');
        }

        $treatmentDate = $this->validDateOrToday($_POST['treatment_date'] ?? '');
        $treatedTooth = $this->nullableText($_POST['treated_tooth'] ?? '', 100);
        $status = $this->allowedTreatmentStatus($_POST['treatment_status'] ?? 'completed');

        $estimatedPrice = 0.00;

        if ($appointment) {
            $estimatedPrice = (float) (
                $appointment['estimated_price']
                ?? $appointment['service_estimated_price']
                ?? 0
            );
        }

        $actualChargeRaw = trim((string) ($_POST['actual_charge'] ?? ''));

        $actualCharge = $actualChargeRaw === '' && $estimatedPrice > 0
            ? round($estimatedPrice, 2)
            : $this->moneyValue($actualChargeRaw);

        $amountPaid = 0.00;
        $balance = $actualCharge;

        $examinationId = 0;

        if ($appointmentId > 0) {
            $examinationId = $this->findLatestExaminationIdForTreatment(
                $patientId,
                $dentistId,
                $appointmentId
            );
        }

        $actorUserId = (int) (Auth::user()['user_id'] ?? 0);

        $this->db->beginTransaction();

        $stmt = $this->db->prepare("
            INSERT INTO treatments (
                appointment_id,
                examination_id,
                patient_id,
                dentist_id,
                treatment_date,
                procedure_name,
                treated_tooth,
                description,
                prescription,
                recommendation,
                estimated_price,
                actual_charge,
                amount_paid,
                balance,
                treatment_status,
                remarks,
                created_at,
                updated_at
            ) VALUES (
                :appointment_id,
                :examination_id,
                :patient_id,
                :dentist_id,
                :treatment_date,
                :procedure_name,
                :treated_tooth,
                NULL,
                NULL,
                NULL,
                :estimated_price,
                :actual_charge,
                :amount_paid,
                :balance,
                :treatment_status,
                NULL,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            ':appointment_id' => $appointmentId > 0 ? $appointmentId : null,
            ':examination_id' => $examinationId > 0 ? $examinationId : null,
            ':patient_id' => $patientId,
            ':dentist_id' => $dentistId,
            ':treatment_date' => $treatmentDate,
            ':procedure_name' => $procedureName,
            ':treated_tooth' => $treatedTooth,
            ':estimated_price' => $estimatedPrice,
            ':actual_charge' => $actualCharge,
            ':amount_paid' => $amountPaid,
            ':balance' => $balance,
            ':treatment_status' => $status,
        ]);

        $treatmentId = (int) $this->db->lastInsertId();

$this->privacyAudit->log(
    $actorUserId,
    'treatments',
    'create',
    'patient',
    $patientId,
    'Dentist created patient treatment record.'
);
        $billingSyncWarning = $this->syncTreatmentBillingWithSavepoint($treatmentId, $actorUserId);

        if ($appointmentId > 0 && in_array($status, ['completed', 'performed'], true)) {
            $shouldNotifyStaffCompleted = $this->completeAppointmentAfterTreatmentSaveInsideTransaction(
                $appointmentId,
                $patientId,
                $dentistId,
                $actorUserId
            );
        }

        $this->db->commit();

        if ($shouldNotifyStaffCompleted) {
            $freshAppointmentForNotification = $this->findAppointmentForTreatmentNotification($appointmentId);
            $notificationAppointment = $freshAppointmentForNotification ?: $appointmentForNotification;

            if (is_array($notificationAppointment)) {
                $this->notifyStaffTreatmentCompletedAfterTreatmentSave(
                    $notificationAppointment,
                    $actorUserId
                );
            }
        }

        Session::set('flash_success', 'Treatment record saved successfully.');

        if ($billingSyncWarning !== null) {
            Session::set('flash_error', $billingSyncWarning);
        }
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        Session::set('flash_error', $e->getMessage());
    }
    

    $this->redirectToPatientShowWithAppointment(
    $patientId,
    'treatment-records',
    $appointmentId
);


}

    public function updateTreatment(): void
{
    Auth::requireRole('dentist');

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $dentist = $this->getCurrentDentist();

    if (!$dentist) {
        http_response_code(403);
        exit('Dentist profile not found.');
    }

    $dentistId = (int) $dentist['dentist_id'];
    $patientId = (int) ($_POST['patient_id'] ?? 0);
    $treatmentId = (int) ($_POST['treatment_id'] ?? 0);

    if ($patientId <= 0 || $treatmentId <= 0) {
        Session::set('flash_error', 'Invalid treatment record.');
        header('Location: ' . $this->url('/dentist/patients'));
        exit;
    }

    $billingSyncWarning = null;
    

    $appointmentId = 0;
$actorUserId = (int) (Auth::user()['user_id'] ?? 0);

    try {
        if (!$this->dentistCanAccessPatient($dentistId, $patientId)) {
            throw new RuntimeException('You are not allowed to update this patient record.');
        }

        $existingTreatment = $this->findTreatmentForDentist($treatmentId, $patientId, $dentistId);

        if (!$existingTreatment) {
            throw new RuntimeException('Treatment record not found or access denied.');
        }

        $procedureName = $this->requiredText(
            $_POST['procedure_name'] ?? '',
            'Procedure is required.',
            255
        );

        $treatmentDate = $this->validDateOrToday($_POST['treatment_date'] ?? '');
        $treatedTooth = $this->nullableText($_POST['treated_tooth'] ?? '', 100);
        $actualCharge = $this->moneyValue($_POST['actual_charge'] ?? 0);

        /*
            Important:
            Dentist edits treatment charge only.
            Staff Billing owns actual payment records.
        */
        $amountPaid = 0.00;
        $balance = $actualCharge;

        $status = $this->allowedTreatmentStatus($_POST['treatment_status'] ?? 'completed');
        $appointmentId = (int) ($existingTreatment['appointment_id'] ?? 0);
        $actorUserId = (int) (Auth::user()['user_id'] ?? 0);

        $this->db->beginTransaction();

        $stmt = $this->db->prepare("
            UPDATE treatments
            SET
                treatment_date = :treatment_date,
                treated_tooth = :treated_tooth,
                procedure_name = :procedure_name,
                actual_charge = :actual_charge,
                amount_paid = :amount_paid,
                balance = :balance,
                treatment_status = :treatment_status,
                updated_at = NOW()
            WHERE treatment_id = :treatment_id
              AND patient_id = :patient_id
            LIMIT 1
        ");

        $stmt->execute([
            ':treatment_date' => $treatmentDate,
            ':treated_tooth' => $treatedTooth,
            ':procedure_name' => $procedureName,
            ':actual_charge' => $actualCharge,
            ':amount_paid' => $amountPaid,
            ':balance' => $balance,
            ':treatment_status' => $status,
            ':treatment_id' => $treatmentId,
            ':patient_id' => $patientId,
        ]);

        /*
            This updates billing item amount and recomputes billing total.
            Existing staff payments remain safe.
        */
        $billingSyncWarning = $this->syncTreatmentBillingWithSavepoint($treatmentId, $actorUserId);

        if ($appointmentId > 0 && in_array($status, ['completed', 'performed'], true)) {
            $this->completeAppointmentFromTreatment(
                $appointmentId,
                $patientId,
                $dentistId,
                'Appointment completed after dentist updated treatment.'
            );
        }

        $this->db->commit();
        $this->privacyAudit->log(
    $actorUserId,
    'treatments',
    'update',
    'patient',
    $patientId,
    'Dentist updated patient treatment record.'
);

        Session::set('flash_success', 'Treatment record updated successfully.');

        if ($billingSyncWarning !== null) {
            Session::set('flash_error', $billingSyncWarning);
        }
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        Session::set('flash_error', $e->getMessage());
    }

    header(
    'Location: ' .
    $this->url('/dentist/patients/show?id=' . $patientId .
        '&tab=treatment-records' .
        '&appointment_id=' . $appointmentId
    )
);
exit;
}

    public function saveOdontogram(): void
    {
        Auth::requireRole('dentist');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $dentist = $this->getCurrentDentist();

        if (!$dentist) {
            http_response_code(403);
            exit('Dentist profile not found.');
        }

        $dentistId = (int) $dentist['dentist_id'];
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $toothNumber = (int) ($_POST['tooth_number'] ?? 0);
        $conditionCode = trim((string) ($_POST['condition_code'] ?? ''));
        $remarks = $this->nullableText($_POST['remarks'] ?? '', 2000);
        $surfaces = $_POST['surfaces'] ?? [];

        if ($patientId <= 0 || $toothNumber <= 0 || $conditionCode === '') {
            Session::set('flash_error', 'Please select a tooth and condition before saving.');
            $this->redirectToPatientShow($patientId, 'dental-chart');
        }

        if (!$this->dentistCanAccessPatient($dentistId, $patientId)) {
            http_response_code(403);
            exit('You are not allowed to update this patient record.');
        }

        $allowedConditions = [
            'Sound',
            'Caries',
            'Missing',
            'Restoration',
            'Fractured',
            'Root Canal Treated',
            'Impacted',
            'For Extraction',
            'Crown',
            'Pontic',
            'Sealant',
            'Other',
        ];

        if (!in_array($conditionCode, $allowedConditions, true)) {
            Session::set('flash_error', 'Selected tooth condition is invalid.');
            $this->redirectToPatientShow($patientId, 'dental-chart');
        }

        if (!is_array($surfaces)) {
            $surfaces = [];
        }

        $allowedSurfaces = ['M', 'D', 'F', 'L', 'B', 'P', 'I', 'O'];

        $surfaces = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => strtoupper(trim((string) $value)),
            $surfaces
        ), static fn ($value): bool => in_array($value, $allowedSurfaces, true))));

        try {
            $this->db->beginTransaction();

            if ($appointmentId <= 0) {
                $appointment = $this->findTodayTreatmentAppointment($patientId, $dentistId)
                    ?? $this->findLatestAppointmentForTreatment($patientId, $dentistId);

                $appointmentId = (int) ($appointment['appointment_id'] ?? 0);
            }

            $examinationId = $this->findOrCreateExaminationId(
                $patientId,
                $dentistId,
                $appointmentId > 0 ? $appointmentId : null
            );

            $surfaceValues = !empty($surfaces) ? $surfaces : [''];

            $stmt = $this->db->prepare("
                INSERT INTO odontogram_entries (
                    examination_id,
                    tooth_number,
                    surface,
                    condition_code,
                    remarks,
                    created_at
                ) VALUES (
                    :examination_id,
                    :tooth_number,
                    :surface,
                    :condition_code,
                    :remarks,
                    NOW()
                )
            ");

            foreach ($surfaceValues as $surface) {
                $stmt->execute([
                    ':examination_id' => $examinationId,
                    ':tooth_number' => $toothNumber,
                    ':surface' => $surface !== '' ? $surface : null,
                    ':condition_code' => $conditionCode,
                    ':remarks' => $remarks,
                ]);
            }

            $this->db->commit();

            Session::set('flash_success', 'Odontogram mark saved successfully.');
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            Session::set('flash_error', 'Unable to save odontogram mark.');
        }

        $this->redirectToPatientShow($patientId, 'dental-chart');
        $this->privacyAudit->log(
    (int) ($user['user_id'] ?? 0),
    'odontogram_entries',
    'update',
    'patient',
    $patientId,
    'Dentist updated patient odontogram.'
);
    }


    public function uploadDocument(): void
{
    Auth::requireRole('dentist');

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $dentist = $this->getCurrentDentist();

    if (!$dentist) {
        http_response_code(403);
        exit('Dentist profile not found.');
    }

    $dentistId = (int) $dentist['dentist_id'];
    $userId = (int) (Auth::user()['user_id'] ?? 0);
    $patientId = (int) ($_POST['patient_id'] ?? 0);
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

    try {
        if ($patientId <= 0) {
            throw new RuntimeException('Invalid patient record.');
        }

        if (!$this->dentistCanAccessPatient($dentistId, $patientId)) {
            throw new RuntimeException('You are not allowed to upload documents for this patient.');
        }

        if ($userId <= 0) {
            throw new RuntimeException('Invalid authenticated user.');
        }

        if (empty($_FILES['document_file']) || !is_array($_FILES['document_file'])) {
            throw new RuntimeException('Please select a document to upload.');
        }

        $file = $_FILES['document_file'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed. Please select a valid file.');
        }

        $maxSize = 10 * 1024 * 1024;

        if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > $maxSize) {
            throw new RuntimeException('File must not exceed 10MB.');
        }

        $originalName = $this->cleanOriginalFileName((string) ($file['name'] ?? 'document'));
        $tmpPath = (string) ($file['tmp_name'] ?? '');

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        $detectedMime = $this->detectMimeType($tmpPath);
        $extension = $this->allowedDocumentExtension($detectedMime, $originalName);

        $category = $this->allowedDocumentCategory($_POST['file_category'] ?? 'other');
        $description = $this->nullableText($_POST['description'] ?? '', 1000);

        $projectRoot = dirname(__DIR__, 3);
        $storageDir = $projectRoot . '/storage/patient_documents/' . $patientId;

        if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true)) {
            throw new RuntimeException('Unable to create document storage folder.');
        }

        $storedFileName = 'doc_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
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
                :appointment_id,
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
            ':appointment_id' => $appointmentId > 0 ? $appointmentId : null,
            ':uploaded_by' => $userId,
            ':file_category' => $category,
            ':original_file_name' => $originalName,
            ':stored_file_name' => $storedFileName,
            ':file_path' => $relativePath,
            ':mime_type' => $detectedMime,
            ':file_size' => (int) $file['size'],
            ':description' => $description,
        ]);

        Session::set('flash_success', 'Document uploaded successfully.');
    } catch (Throwable $e) {
        Session::set('flash_error', $e->getMessage());
    }

    $this->redirectToPatientShow($patientId, 'documents');
}

public function deleteDocument(): void
{
    Auth::requireRole('dentist');

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $dentist = $this->getCurrentDentist();

    if (!$dentist) {
        http_response_code(403);
        exit('Dentist profile not found.');
    }

    $dentistId = (int) $dentist['dentist_id'];
    $attachmentId = (int) ($_POST['attachment_id'] ?? 0);
    $patientId = (int) ($_POST['patient_id'] ?? 0);

    try {
        if ($attachmentId <= 0 || $patientId <= 0) {
            throw new RuntimeException('Invalid document record.');
        }

        $document = $this->findPatientDocumentForDentist($attachmentId, $dentistId);

        if (!$document) {
            throw new RuntimeException('Document not found or access denied.');
        }

        $documentPatientId = (int) ($document['patient_id'] ?? 0);

        if ($documentPatientId !== $patientId) {
            throw new RuntimeException('Document does not belong to this patient.');
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

    $this->redirectToPatientShow($patientId, 'documents');
}

public function document(): void
{
    Auth::requireRole('dentist');

    $dentist = $this->getCurrentDentist();

    if (!$dentist) {
        http_response_code(403);
        exit('Dentist profile not found.');
    }

    $dentistId = (int) $dentist['dentist_id'];
    $attachmentId = (int) ($_GET['id'] ?? 0);
    $mode = strtolower(trim((string) ($_GET['mode'] ?? 'inline')));

    if ($attachmentId <= 0) {
        http_response_code(404);
        exit('Document not found.');
    }

    $document = $this->findPatientDocumentForDentist($attachmentId, $dentistId);

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

    $isPreviewable = $this->isPreviewableDocumentMime($mimeType);
    $disposition = ($mode === 'download' || !$isPreviewable) ? 'attachment' : 'inline';

    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $originalName) . '"');

    readfile($filePath);
    exit;
}

   private function syncTreatmentBillingWithSavepoint(int $treatmentId, int $actorUserId): ?string
{
    if ($treatmentId <= 0) {
        return 'Treatment was saved, but billing was not synced because the treatment reference is invalid.';
    }

    if ($actorUserId <= 0) {
        return 'Treatment was saved, but billing was not synced because the current user is invalid.';
    }

    if (!method_exists($this->billings, 'syncTreatmentBilling')) {
        return 'Treatment was saved, but billing was not synced because BillingRepository::syncTreatmentBilling() is missing.';
    }

    $savepoint = 'billing_sync_savepoint';

    try {
        if ($this->db->inTransaction()) {
            $this->db->exec("SAVEPOINT {$savepoint}");
        }

        $this->billings->syncTreatmentBilling($treatmentId, $actorUserId);

        if ($this->db->inTransaction()) {
            $this->db->exec("RELEASE SAVEPOINT {$savepoint}");
        }

        return null;
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            try {
                $this->db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            } catch (Throwable $rollbackError) {
            }

            try {
                $this->db->exec("RELEASE SAVEPOINT {$savepoint}");
            } catch (Throwable $releaseError) {
            }
        }

        return 'Treatment was saved, but billing was not synced: ' . $e->getMessage();
    }
}

    private function syncTreatmentPaymentWithSavepoint(
        int $treatmentId,
        float $desiredAmountPaid,
        float $actualCharge,
        int $actorUserId
    ): ?string {
        if ($treatmentId <= 0) {
            return 'Treatment was saved, but payment was not synced because the treatment reference is invalid.';
        }

        if ($desiredAmountPaid <= 0) {
            $billing = $this->findBillingForTreatmentPayment($treatmentId);

            if ($billing) {
                $this->syncTreatmentFinancialSnapshotFromBilling(
                    $treatmentId,
                    (int) ($billing['billing_id'] ?? 0)
                );
            }

            return null;
        }

        if ($desiredAmountPaid > $actualCharge) {
            return 'Treatment was saved, but payment was not synced because amount paid is greater than amount charged.';
        }

        if ($actorUserId <= 0) {
            return 'Treatment was saved, but payment was not synced because the current user is invalid.';
        }

        if (!$this->tableExists('billing_payments')) {
            return 'Treatment was saved, but payment was not synced because billing_payments table does not exist.';
        }

        $savepoint = 'treatment_payment_savepoint';

        try {
            if ($this->db->inTransaction()) {
                $this->db->exec("SAVEPOINT {$savepoint}");
            }

            $billing = $this->findBillingForTreatmentPayment($treatmentId);

            if (!$billing) {
                throw new RuntimeException('Billing record was not found for this treatment.');
            }

            $billingId = (int) ($billing['billing_id'] ?? 0);

            if ($billingId <= 0) {
                throw new RuntimeException('Invalid billing record.');
            }

            $currentPaid = $this->getBillingPaidAmount($billingId);

            if ($desiredAmountPaid < $currentPaid) {
                throw new RuntimeException('Amount paid cannot be lower than the already recorded payment.');
            }

            $paymentDelta = round($desiredAmountPaid - $currentPaid, 2);

            if ($paymentDelta > 0) {
                $this->insertBillingPaymentForTreatment(
                    $billingId,
                    $paymentDelta,
                    $actorUserId
                );
            }

            $this->billings->updatePaymentTotals($billingId);
            $this->syncTreatmentFinancialSnapshotFromBilling($treatmentId, $billingId);

            if ($this->db->inTransaction()) {
                $this->db->exec("RELEASE SAVEPOINT {$savepoint}");
            }

            return null;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                try {
                    $this->db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
                } catch (Throwable $rollbackError) {
                }

                try {
                    $this->db->exec("RELEASE SAVEPOINT {$savepoint}");
                } catch (Throwable $releaseError) {
                }
            }

            return 'Treatment was saved, but payment was not synced: ' . $e->getMessage();
        }
    }

    private function getCurrentPaidAmountForTreatment(int $treatmentId): float
    {
        if ($treatmentId <= 0) {
            return 0.00;
        }

        $billing = $this->findBillingForTreatmentPayment($treatmentId);

        if ($billing) {
            return $this->getBillingPaidAmount((int) $billing['billing_id']);
        }

        $stmt = $this->db->prepare("
            SELECT amount_paid
            FROM treatments
            WHERE treatment_id = :treatment_id
            LIMIT 1
        ");

        $stmt->execute([
            ':treatment_id' => $treatmentId,
        ]);

        return round((float) $stmt->fetchColumn(), 2);
    }

    private function findBillingForTreatmentPayment(int $treatmentId): ?array
    {
        if ($treatmentId <= 0) {
            return null;
        }

        if ($this->tableHasColumn('billing_items', 'treatment_id')) {
            $stmt = $this->db->prepare("
                SELECT b.*
                FROM billings b
                INNER JOIN billing_items bi ON bi.billing_id = b.billing_id
                WHERE bi.treatment_id = :treatment_id
                ORDER BY b.billing_id DESC
                LIMIT 1
            ");

            $stmt->execute([
                ':treatment_id' => $treatmentId,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return $row;
            }
        }

        if ($this->tableHasColumn('billings', 'treatment_id')) {
            $stmt = $this->db->prepare("
                SELECT *
                FROM billings
                WHERE treatment_id = :treatment_id
                ORDER BY billing_id DESC
                LIMIT 1
            ");

            $stmt->execute([
                ':treatment_id' => $treatmentId,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return $row;
            }
        }

        $stmt = $this->db->prepare("
            SELECT appointment_id
            FROM treatments
            WHERE treatment_id = :treatment_id
            LIMIT 1
        ");

        $stmt->execute([
            ':treatment_id' => $treatmentId,
        ]);

        $appointmentId = (int) $stmt->fetchColumn();

        if ($appointmentId <= 0) {
            return null;
        }

        $billingStmt = $this->db->prepare("
            SELECT *
            FROM billings
            WHERE appointment_id = :appointment_id
            ORDER BY billing_id DESC
            LIMIT 1
        ");

        $billingStmt->execute([
            ':appointment_id' => $appointmentId,
        ]);

        $billing = $billingStmt->fetch(PDO::FETCH_ASSOC);

        return $billing ?: null;
    }

    private function getBillingPaidAmount(int $billingId): float
    {
        if ($billingId <= 0 || !$this->tableExists('billing_payments')) {
            return 0.00;
        }

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount_paid), 0)
            FROM billing_payments
            WHERE billing_id = :billing_id
        ");

        $stmt->execute([
            ':billing_id' => $billingId,
        ]);

        return round((float) $stmt->fetchColumn(), 2);
    }

    private function insertBillingPaymentForTreatment(
        int $billingId,
        float $amountPaid,
        int $actorUserId
    ): void {
        if ($billingId <= 0) {
            throw new RuntimeException('Invalid billing record.');
        }

        if ($amountPaid <= 0) {
            return;
        }

        $columns = $this->getTableColumnsForPaymentSync('billing_payments');

        if (empty($columns)) {
            throw new RuntimeException('billing_payments table columns could not be read.');
        }

        if (!isset($columns['billing_id']) || !isset($columns['amount_paid'])) {
            throw new RuntimeException('billing_payments table is missing required columns.');
        }

        $data = [
            'billing_id' => $billingId,
            'amount_paid' => number_format($amountPaid, 2, '.', ''),
        ];

        if (isset($columns['payment_method'])) {
            $data['payment_method'] = 'cash';
        }

        if (isset($columns['reference_number'])) {
            $data['reference_number'] = '';
        }

        if (isset($columns['receipt_number'])) {
            $data['receipt_number'] = $this->generateBillingPaymentReceiptNumber();
        }

        if (isset($columns['received_by'])) {
            $data['received_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        if (isset($columns['recorded_by'])) {
            $data['recorded_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        if (isset($columns['created_by'])) {
            $data['created_by'] = $actorUserId > 0 ? $actorUserId : null;
        }

        if (isset($columns['paid_at'])) {
            $data['paid_at'] = date('Y-m-d H:i:s');
        }

        if (isset($columns['payment_date'])) {
            $data['payment_date'] = date('Y-m-d');
        }

        if (isset($columns['remarks'])) {
            $data['remarks'] = 'Payment recorded from dentist treatment record.';
        }

        if (isset($columns['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        if (isset($columns['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $this->insertDynamicRow('billing_payments', $data);
    }

    private function syncTreatmentFinancialSnapshotFromBilling(int $treatmentId, int $billingId): void
    {
        if ($treatmentId <= 0 || $billingId <= 0) {
            return;
        }

        $stmt = $this->db->prepare("
            SELECT amount_paid, balance
            FROM billings
            WHERE billing_id = :billing_id
            LIMIT 1
        ");

        $stmt->execute([
            ':billing_id' => $billingId,
        ]);

        $billing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$billing) {
            return;
        }

        $update = $this->db->prepare("
            UPDATE treatments
            SET
                amount_paid = :amount_paid,
                balance = :balance,
                updated_at = NOW()
            WHERE treatment_id = :treatment_id
            LIMIT 1
        ");

        $update->execute([
            ':amount_paid' => number_format((float) ($billing['amount_paid'] ?? 0), 2, '.', ''),
            ':balance' => number_format((float) ($billing['balance'] ?? 0), 2, '.', ''),
            ':treatment_id' => $treatmentId,
        ]);
    }

    private function generateBillingPaymentReceiptNumber(): string
    {
        do {
            $receiptNumber = 'RCPT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM billing_payments
                WHERE receipt_number = :receipt_number
            ");

            $stmt->execute([
                ':receipt_number' => $receiptNumber,
            ]);

            $exists = (int) $stmt->fetchColumn() > 0;
        } while ($exists);

        return $receiptNumber;
    }

    private function getPatientDocuments(int $patientId): array
{
    if ($patientId <= 0) {
        return [];
    }

    if (!$this->tableExists('attachments')) {
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

    private function getCurrentDentist(): ?array
    {
        $user = Auth::user();
        $userId = (int) ($user['user_id'] ?? 0);

        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM dentists
            WHERE user_id = :user_id
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $dentist = $stmt->fetch(PDO::FETCH_ASSOC);

        return $dentist ?: null;
    }

    private function getAssignedPatients(int $dentistId, string $search = '', string $status = ''): array
    {
        $sql = "
            SELECT
                p.*,
                COALESCE(ac.appointment_count, 0) AS appointment_count
            FROM patients p
            INNER JOIN (
                SELECT DISTINCT patient_id
                FROM appointments
                WHERE dentist_id = :assigned_dentist_id
                  AND patient_id IS NOT NULL
            ) assigned ON assigned.patient_id = p.patient_id
            LEFT JOIN (
                SELECT patient_id, COUNT(*) AS appointment_count
                FROM appointments
                WHERE dentist_id = :count_dentist_id
                  AND patient_id IS NOT NULL
                GROUP BY patient_id
            ) ac ON ac.patient_id = p.patient_id
            WHERE 1 = 1
        ";

        $params = [
            ':assigned_dentist_id' => $dentistId,
            ':count_dentist_id' => $dentistId,
        ];

        if ($search !== '') {
            $sql .= "
                AND (
                    p.patient_code LIKE :search
                    OR p.first_name LIKE :search
                    OR p.middle_name LIKE :search
                    OR p.last_name LIKE :search
                    OR CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE :search
                    OR p.contact_number LIKE :search
                    OR p.email LIKE :search
                )
            ";

            $params[':search'] = '%' . $search . '%';
        }

        if ($status !== '') {
            $sql .= " AND p.profile_status = :profile_status ";
            $params[':profile_status'] = $status;
        }

        $sql .= "
            ORDER BY
                p.last_name ASC,
                p.first_name ASC,
                p.patient_id DESC
            LIMIT 200
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function dentistCanAccessPatient(int $dentistId, int $patientId): bool
    {
        if ($dentistId <= 0 || $patientId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointments
            WHERE dentist_id = :dentist_id
              AND patient_id = :patient_id
            LIMIT 1
        ");

        $stmt->execute([
            ':dentist_id' => $dentistId,
            ':patient_id' => $patientId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function findPatient(int $patientId): ?array
    {
        if ($patientId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM patients
            WHERE patient_id = :patient_id
            LIMIT 1
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
        ]);

        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        return $patient ?: null;
    }

    private function getMedicalHistory(int $patientId): ?array
    {
        if ($patientId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM patient_medical_histories
            WHERE patient_id = :patient_id
            ORDER BY medical_history_id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
        ]);

        $history = $stmt->fetch(PDO::FETCH_ASSOC);

        return $history ?: null;
    }

    private function getDentalHistory(int $patientId): ?array
    {
        if ($patientId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM patient_dental_histories
            WHERE patient_id = :patient_id
            ORDER BY dental_history_id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
        ]);

        $history = $stmt->fetch(PDO::FETCH_ASSOC);

        return $history ?: null;
    }

    private function getPatientAppointmentsForDentist(int $patientId, int $dentistId): array
    {
        if ($patientId <= 0 || $dentistId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                a.*,
                s.service_name,
                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name
            FROM appointments a
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            WHERE a.patient_id = :patient_id
              AND a.dentist_id = :dentist_id
            ORDER BY
                a.appointment_date DESC,
                a.start_time DESC,
                a.appointment_id DESC
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
            ':dentist_id' => $dentistId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getPatientTreatmentsForDentist(int $patientId, int $dentistId): array
    {
        if ($patientId <= 0 || $dentistId <= 0) {
            return [];
        }

        if ($this->tableHasColumn('billing_items', 'treatment_id')) {
            $billingJoin = "
                LEFT JOIN (
                    SELECT treatment_id, MIN(billing_id) AS billing_id
                    FROM billing_items
                    WHERE treatment_id IS NOT NULL
                    GROUP BY treatment_id
                ) bi_link ON bi_link.treatment_id = t.treatment_id
                LEFT JOIN billings b ON b.billing_id = bi_link.billing_id
            ";
        } elseif ($this->tableHasColumn('billings', 'treatment_id')) {
            $billingJoin = "
                LEFT JOIN billings b ON b.treatment_id = t.treatment_id
            ";
        } else {
            $billingJoin = "
                LEFT JOIN billings b ON b.appointment_id = t.appointment_id
            ";
        }

        $sql = "
            SELECT
                t.*,

                b.billing_id,
                b.billing_number,
                COALESCE(b.total_amount, t.actual_charge, 0) AS billing_total_amount,
                COALESCE(b.amount_paid, t.amount_paid, 0) AS amount_paid,
                COALESCE(b.balance, GREATEST(0, COALESCE(t.actual_charge, 0) - COALESCE(t.amount_paid, 0))) AS balance,
                COALESCE(b.payment_status, CASE WHEN COALESCE(t.balance, 0) <= 0 THEN 'paid' ELSE 'unpaid' END) AS payment_status,

               a.appointment_code,
a.appointment_date,
a.start_time,
a.end_time,
a.actual_started_at AS appointment_actual_started_at,
a.actual_completed_at AS appointment_actual_completed_at,
a.completed_at AS appointment_completed_at,

                s.service_name,

                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name
            FROM treatments t
            {$billingJoin}
            LEFT JOIN appointments a ON a.appointment_id = t.appointment_id
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN dentists d ON d.dentist_id = COALESCE(t.dentist_id, a.dentist_id)
            LEFT JOIN users du ON du.user_id = d.user_id
            WHERE t.patient_id = :patient_id
              AND (
                    t.dentist_id = :treatment_dentist_id
                    OR a.dentist_id = :appointment_dentist_id
              )
            ORDER BY
                t.treatment_date DESC,
                t.treatment_id DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':patient_id' => $patientId,
            ':treatment_dentist_id' => $dentistId,
            ':appointment_dentist_id' => $dentistId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getPatientOdontogramEntriesForDentist(int $patientId, int $dentistId): array
    {
        if ($patientId <= 0 || $dentistId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                oe.odontogram_id,
                oe.examination_id,
                oe.tooth_number,
                oe.surface,
                oe.condition_code,
                oe.remarks,
                oe.created_at,
                e.appointment_id,
                e.patient_id,
                e.dentist_id,
                COALESCE(
                    NULLIF(TRIM(t.procedure_name), ''),
                    NULLIF(TRIM(oe.condition_code), ''),
                    'Recorded Finding'
                ) AS procedure_name,
                COALESCE(
                    NULLIF(TRIM(t.treatment_status), ''),
                    'planned'
                ) AS status,
                COALESCE(
                    NULLIF(TRIM(oe.remarks), ''),
                    ''
                ) AS notes
            FROM odontogram_entries oe
            INNER JOIN examinations e ON e.examination_id = oe.examination_id
            LEFT JOIN (
                SELECT t1.*
                FROM treatments t1
                INNER JOIN (
                    SELECT
                        examination_id,
                        MAX(treatment_id) AS latest_treatment_id
                    FROM treatments
                    WHERE examination_id IS NOT NULL
                    GROUP BY examination_id
                ) latest_treatment ON latest_treatment.latest_treatment_id = t1.treatment_id
            ) t ON t.examination_id = e.examination_id
            WHERE e.patient_id = :patient_id
              AND e.dentist_id = :dentist_id
            ORDER BY
                oe.created_at DESC,
                oe.odontogram_id DESC
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
            ':dentist_id' => $dentistId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function groupOdontogramEntriesByTooth(array $entries): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $toothNumber = (int) ($entry['tooth_number'] ?? 0);

            if ($toothNumber > 0) {
                $grouped[$toothNumber][] = $entry;
            }
        }

        return $grouped;
    }

    private function findTodayTreatmentAppointment(int $patientId, int $dentistId): ?array
    {
        if ($patientId <= 0 || $dentistId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                a.*,
                s.service_name,
                s.estimated_price AS service_estimated_price
            FROM appointments a
            LEFT JOIN services s ON s.service_id = a.service_id
            WHERE a.patient_id = :patient_id
              AND a.dentist_id = :dentist_id
              AND a.appointment_date = CURDATE()
              AND a.status IN ('checked_in', 'in_progress', 'confirmed', 'rescheduled')
            ORDER BY
                FIELD(a.status, 'in_progress', 'checked_in', 'confirmed', 'rescheduled'),
                a.start_time ASC,
                a.appointment_id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
            ':dentist_id' => $dentistId,
        ]);

        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        return $appointment ?: null;
    }

    private function findLatestAppointmentForTreatment(int $patientId, int $dentistId): ?array
    {
        if ($patientId <= 0 || $dentistId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                a.*,
                s.service_name,
                s.estimated_price AS service_estimated_price
            FROM appointments a
            LEFT JOIN services s ON s.service_id = a.service_id
            WHERE a.patient_id = :patient_id
              AND a.dentist_id = :dentist_id
            ORDER BY
                a.appointment_date DESC,
                a.start_time DESC,
                a.appointment_id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
            ':dentist_id' => $dentistId,
        ]);

        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        return $appointment ?: null;
    }

    private function findAppointmentForTreatment(int $appointmentId, int $patientId, int $dentistId): ?array
    {
        if ($appointmentId <= 0 || $patientId <= 0 || $dentistId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                a.*,
                s.service_name,
                s.estimated_price AS service_estimated_price
            FROM appointments a
            LEFT JOIN services s ON s.service_id = a.service_id
            WHERE a.appointment_id = :appointment_id
              AND a.patient_id = :patient_id
              AND a.dentist_id = :dentist_id
            LIMIT 1
        ");

        $stmt->execute([
            ':appointment_id' => $appointmentId,
            ':patient_id' => $patientId,
            ':dentist_id' => $dentistId,
        ]);

        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        return $appointment ?: null;
    }

    private function findTreatmentForDentist(int $treatmentId, int $patientId, int $dentistId): ?array
    {
        if ($treatmentId <= 0 || $patientId <= 0 || $dentistId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT t.*
            FROM treatments t
            LEFT JOIN appointments a ON a.appointment_id = t.appointment_id
            WHERE t.treatment_id = :treatment_id
              AND t.patient_id = :patient_id
              AND (
                    t.dentist_id = :treatment_dentist_id
                    OR a.dentist_id = :appointment_dentist_id
              )
            LIMIT 1
        ");

        $stmt->execute([
            ':treatment_id' => $treatmentId,
            ':patient_id' => $patientId,
            ':treatment_dentist_id' => $dentistId,
            ':appointment_dentist_id' => $dentistId,
        ]);

        $treatment = $stmt->fetch(PDO::FETCH_ASSOC);

        return $treatment ?: null;
    }

    private function findLatestExaminationIdForTreatment(int $patientId, int $dentistId, int $appointmentId): int
    {
        if ($patientId <= 0 || $dentistId <= 0 || $appointmentId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare("
            SELECT examination_id
            FROM examinations
            WHERE patient_id = :patient_id
              AND dentist_id = :dentist_id
              AND appointment_id = :appointment_id
            ORDER BY examination_id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
            ':dentist_id' => $dentistId,
            ':appointment_id' => $appointmentId,
        ]);

        return (int) $stmt->fetchColumn();
    }

   private function completeAppointmentFromTreatment(
    int $appointmentId,
    int $patientId,
    int $dentistId,
    string $remarks
): void {
    $actorUserId = (int) (Auth::user()['user_id'] ?? 0);

    $this->completeAppointmentAfterTreatmentSaveInsideTransaction(
        $appointmentId,
        $patientId,
        $dentistId,
        $actorUserId
    );
}

    private function logAppointmentStatusChange(
        int $appointmentId,
        string $oldStatus,
        string $newStatus,
        string $remarks
    ): void {
        try {
            if (!$this->tableExists('appointment_status_logs')) {
                return;
            }

            $user = Auth::user();
            $userId = (int) ($user['user_id'] ?? 0);

            $stmt = $this->db->prepare("
                INSERT INTO appointment_status_logs (
                    appointment_id,
                    old_status,
                    new_status,
                    changed_by,
                    remarks,
                    changed_at
                ) VALUES (
                    :appointment_id,
                    :old_status,
                    :new_status,
                    :changed_by,
                    :remarks,
                    NOW()
                )
            ");

            $stmt->execute([
                ':appointment_id' => $appointmentId,
                ':old_status' => $oldStatus,
                ':new_status' => $newStatus,
                ':changed_by' => $userId > 0 ? $userId : null,
                ':remarks' => $remarks,
            ]);
        } catch (Throwable $e) {
        }
    }

    private function findOrCreateExaminationId(int $patientId, int $dentistId, ?int $appointmentId = null): int
    {
        if ($patientId <= 0 || $dentistId <= 0) {
            throw new RuntimeException('Invalid examination reference.');
        }

        if ($appointmentId !== null && $appointmentId > 0) {
            $stmt = $this->db->prepare("
                SELECT examination_id
                FROM examinations
                WHERE patient_id = :patient_id
                  AND dentist_id = :dentist_id
                  AND appointment_id = :appointment_id
                ORDER BY examination_id DESC
                LIMIT 1
            ");

            $stmt->execute([
                ':patient_id' => $patientId,
                ':dentist_id' => $dentistId,
                ':appointment_id' => $appointmentId,
            ]);

            $existingId = (int) $stmt->fetchColumn();

            if ($existingId > 0) {
                return $existingId;
            }

            $insert = $this->db->prepare("
                INSERT INTO examinations (
                    appointment_id,
                    patient_id,
                    dentist_id,
                    examination_date,
                    created_at,
                    updated_at
                ) VALUES (
                    :appointment_id,
                    :patient_id,
                    :dentist_id,
                    CURDATE(),
                    NOW(),
                    NOW()
                )
            ");

            $insert->execute([
                ':appointment_id' => $appointmentId,
                ':patient_id' => $patientId,
                ':dentist_id' => $dentistId,
            ]);

            return (int) $this->db->lastInsertId();
        }

        $stmt = $this->db->prepare("
            SELECT examination_id
            FROM examinations
            WHERE patient_id = :patient_id
              AND dentist_id = :dentist_id
            ORDER BY examination_id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
            ':dentist_id' => $dentistId,
        ]);

        $existingId = (int) $stmt->fetchColumn();

        if ($existingId > 0) {
            return $existingId;
        }

        $insert = $this->db->prepare("
            INSERT INTO examinations (
                patient_id,
                dentist_id,
                examination_date,
                created_at,
                updated_at
            ) VALUES (
                :patient_id,
                :dentist_id,
                CURDATE(),
                NOW(),
                NOW()
            )
        ");

        $insert->execute([
            ':patient_id' => $patientId,
            ':dentist_id' => $dentistId,
        ]);

        return (int) $this->db->lastInsertId();
    }
private function completeAppointmentAfterTreatmentSaveInsideTransaction(
    int $appointmentId,
    int $patientId,
    int $dentistId,
    int $actorUserId
): bool {
    if ($appointmentId <= 0 || $patientId <= 0 || $dentistId <= 0) {
        return false;
    }

    $stmt = $this->db->prepare("
        SELECT *
        FROM appointments
        WHERE appointment_id = :appointment_id
          AND patient_id = :patient_id
          AND dentist_id = :dentist_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':appointment_id' => $appointmentId,
        ':patient_id' => $patientId,
        ':dentist_id' => $dentistId,
    ]);

    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new RuntimeException('Linked appointment not found.');
    }

    $oldStatus = strtolower(trim((string) ($appointment['status'] ?? '')));

    if (in_array($oldStatus, ['cancelled', 'rejected', 'no_show'], true)) {
        throw new RuntimeException('This appointment cannot be completed.');
    }

    if (empty($appointment['actual_started_at'])) {
        throw new RuntimeException('Please click Start Treatment Record before saving the treatment.');
    }

    if (!empty($appointment['actual_completed_at'])) {
        return false;
    }

    $now = date('Y-m-d H:i:s');

    $update = $this->db->prepare("
        UPDATE appointments
        SET
            status = 'completed',
            actual_completed_at = :actual_completed_at,
            completed_at = :completed_at,
            updated_at = :updated_at
        WHERE appointment_id = :appointment_id
          AND patient_id = :patient_id
          AND dentist_id = :dentist_id
        LIMIT 1
    ");

    $update->execute([
        ':actual_completed_at' => $now,
        ':completed_at' => $now,
        ':updated_at' => $now,
        ':appointment_id' => $appointmentId,
        ':patient_id' => $patientId,
        ':dentist_id' => $dentistId,
    ]);

    if ($oldStatus !== 'completed') {
        $this->insertAppointmentStatusLogAfterTreatmentSave(
            $appointmentId,
            $oldStatus,
            'completed',
            $actorUserId,
            'Treatment record saved. Actual completion time recorded.'
        );
    }

    return true;
}

    private function notifyStaffTreatmentCompletedAfterTreatmentSave(array $appointment, int $actorUserId): void
    {
        try {
            $appointmentId = (int) ($appointment['appointment_id'] ?? 0);

            if ($appointmentId <= 0) {
                return;
            }

            $appointmentDate = trim((string) ($appointment['appointment_date'] ?? date('Y-m-d')));
            $patientName = $this->appointmentPatientNameForTreatmentNotification($appointment);
            $dentistName = $this->appointmentDentistNameForTreatmentNotification($appointment);
            $dateLabel = $this->appointmentDateLabelForTreatmentNotification($appointment);
            $timeLabel = $this->appointmentTimeLabelForTreatmentNotification($appointment);

            $title = 'Treatment completed';
            $message = $dentistName . ' completed treatment for ' . $patientName . ' on ' . $dateLabel . ' at ' . $timeLabel . '. Please review billing or payment if needed.';
            $linkUrl = '/DentalClinic/public/staff/appointments?date=' . urlencode($appointmentDate);

            $this->createStaffNotificationRowsForTreatmentCompletion(
                'treatment_completed',
                $title,
                $message,
                $linkUrl,
                $appointmentId,
                $actorUserId > 0 ? $actorUserId : null
            );
        } catch (Throwable $e) {
        }
    }

    private function insertAppointmentStatusLogAfterTreatmentSave(
        int $appointmentId,
        string $oldStatus,
        string $newStatus,
        int $actorUserId,
        string $remarks
    ): void {
        try {
            if (!$this->tableExists('appointment_status_logs')) {
                return;
            }

            $stmt = $this->db->prepare("
                INSERT INTO appointment_status_logs (
                    appointment_id,
                    old_status,
                    new_status,
                    changed_by,
                    remarks,
                    changed_at
                ) VALUES (
                    :appointment_id,
                    :old_status,
                    :new_status,
                    :changed_by,
                    :remarks,
                    NOW()
                )
            ");

            $stmt->execute([
                ':appointment_id' => $appointmentId,
                ':old_status' => $oldStatus,
                ':new_status' => $newStatus,
                ':changed_by' => $actorUserId > 0 ? $actorUserId : null,
                ':remarks' => $remarks,
            ]);
        } catch (Throwable $e) {
        }
    }

    private function findAppointmentForTreatmentNotification(int $appointmentId): ?array
    {
        if ($appointmentId <= 0) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT
                    a.*,

                    p.first_name AS patient_first_name,
                    p.middle_name AS patient_middle_name,
                    p.last_name AS patient_last_name,

                    ar.guest_first_name,
                    ar.guest_middle_name,
                    ar.guest_last_name,

                    du.first_name AS dentist_first_name,
                    du.middle_name AS dentist_middle_name,
                    du.last_name AS dentist_last_name,

                    s.service_name
                FROM appointments a
                LEFT JOIN patients p ON p.patient_id = a.patient_id
                LEFT JOIN appointment_requests ar ON ar.request_id = a.request_id
                LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
                LEFT JOIN users du ON du.user_id = d.user_id
                LEFT JOIN services s ON s.service_id = a.service_id
                WHERE a.appointment_id = :appointment_id
                LIMIT 1
            ");

            $stmt->execute([
                ':appointment_id' => $appointmentId,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function createStaffNotificationRowsForTreatmentCompletion(
        string $type,
        string $title,
        string $message,
        string $linkUrl,
        int $appointmentId,
        ?int $actorUserId
    ): void {
        if (!$this->tableExists('notifications')) {
            return;
        }

        $staffUserIds = $this->getStaffUserIdsForTreatmentNotification();

        if (empty($staffUserIds)) {
            $this->insertRoleBasedStaffNotificationForTreatmentCompletion(
                $type,
                $title,
                $message,
                $linkUrl,
                $appointmentId,
                $actorUserId
            );

            return;
        }

        foreach ($staffUserIds as $staffUserId) {
            $this->insertStaffNotificationForTreatmentCompletion(
                $staffUserId,
                $type,
                $title,
                $message,
                $linkUrl,
                $appointmentId,
                $actorUserId
            );
        }
    }

    private function getStaffUserIdsForTreatmentNotification(): array
    {
        try {
            if (!$this->tableExists('users')) {
                return [];
            }

            $userColumns = $this->getTableColumnsForTreatmentNotification('users');

            if (isset($userColumns['role_id']) && $this->tableExists('roles')) {
                $roleColumns = $this->getTableColumnsForTreatmentNotification('roles');

                $roleNameColumn = null;

                foreach (['role_name', 'name', 'slug'] as $candidate) {
                    if (isset($roleColumns[$candidate])) {
                        $roleNameColumn = $candidate;
                        break;
                    }
                }

                if ($roleNameColumn === null) {
                    return [];
                }

                $activeSql = isset($userColumns['is_active'])
                    ? 'AND u.is_active = 1'
                    : '';

                $stmt = $this->db->prepare("
                    SELECT u.user_id
                    FROM users u
                    INNER JOIN roles r ON r.role_id = u.role_id
                    WHERE LOWER(r.`{$roleNameColumn}`) IN ('staff', 'admin')
                    {$activeSql}
                ");

                $stmt->execute();

                return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            }

            if (isset($userColumns['role_name'])) {
                $activeSql = isset($userColumns['is_active'])
                    ? 'AND is_active = 1'
                    : '';

                $stmt = $this->db->prepare("
                    SELECT user_id
                    FROM users
                    WHERE LOWER(role_name) IN ('staff', 'admin')
                    {$activeSql}
                ");

                $stmt->execute();

                return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            }

            return [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function insertStaffNotificationForTreatmentCompletion(
        int $staffUserId,
        string $type,
        string $title,
        string $message,
        string $linkUrl,
        int $appointmentId,
        ?int $actorUserId
    ): void {
        if ($staffUserId <= 0) {
            return;
        }

        $columns = $this->getTableColumnsForTreatmentNotification('notifications');

        if (empty($columns)) {
            return;
        }

        $data = [];

        if (isset($columns['user_id'])) {
            $data['user_id'] = $staffUserId;
        }

        if (isset($columns['recipient_user_id'])) {
            $data['recipient_user_id'] = $staffUserId;
        }

        if (isset($columns['recipient_role'])) {
            $data['recipient_role'] = 'staff';
        }

        if (!isset($data['user_id']) && !isset($data['recipient_user_id']) && !isset($data['recipient_role'])) {
            return;
        }

        $this->fillNotificationData(
            $data,
            $columns,
            $type,
            $title,
            $message,
            $linkUrl,
            $appointmentId,
            $actorUserId
        );

        $this->insertNotificationDataForTreatmentCompletion($data);
    }

    private function insertRoleBasedStaffNotificationForTreatmentCompletion(
        string $type,
        string $title,
        string $message,
        string $linkUrl,
        int $appointmentId,
        ?int $actorUserId
    ): void {
        $columns = $this->getTableColumnsForTreatmentNotification('notifications');

        if (empty($columns) || !isset($columns['recipient_role'])) {
            return;
        }

        $data = [
            'recipient_role' => 'staff',
        ];

        $this->fillNotificationData(
            $data,
            $columns,
            $type,
            $title,
            $message,
            $linkUrl,
            $appointmentId,
            $actorUserId
        );

        $this->insertNotificationDataForTreatmentCompletion($data);
    }

    private function fillNotificationData(
        array &$data,
        array $columns,
        string $type,
        string $title,
        string $message,
        string $linkUrl,
        int $appointmentId,
        ?int $actorUserId
    ): void {
        if (isset($columns['type'])) {
            $data['type'] = $type;
        }

        if (isset($columns['notification_type'])) {
            $data['notification_type'] = $type;
        }

        if (isset($columns['title'])) {
            $data['title'] = $title;
        }

        if (isset($columns['message'])) {
            $data['message'] = $message;
        }

        if (isset($columns['link_url'])) {
            $data['link_url'] = $linkUrl;
        }

        if (isset($columns['target_url'])) {
            $data['target_url'] = $linkUrl;
        }

        if (isset($columns['related_table'])) {
            $data['related_table'] = 'appointment';
        }

        if (isset($columns['related_type'])) {
            $data['related_type'] = 'appointment';
        }

        if (isset($columns['related_id'])) {
            $data['related_id'] = $appointmentId;
        }

        if (isset($columns['appointment_id'])) {
            $data['appointment_id'] = $appointmentId;
        }

        if (isset($columns['actor_user_id'])) {
            $data['actor_user_id'] = $actorUserId;
        }

        if (isset($columns['created_by'])) {
            $data['created_by'] = $actorUserId;
        }

        if (isset($columns['is_read'])) {
            $data['is_read'] = 0;
        }

        if (isset($columns['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        if (isset($columns['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
    }

    private function insertNotificationDataForTreatmentCompletion(array $data): void
    {
        if (empty($data)) {
            return;
        }

        $fields = array_keys($data);

        $quotedFields = array_map(
            static fn (string $field): string => '`' . str_replace('`', '', $field) . '`',
            $fields
        );

        $placeholders = array_map(
            static fn (string $field): string => ':' . $field,
            $fields
        );

        $sql = "
            INSERT INTO notifications (
                " . implode(', ', $quotedFields) . "
            ) VALUES (
                " . implode(', ', $placeholders) . "
            )
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($data as $field => $value) {
            if ($value === null) {
                $stmt->bindValue(':' . $field, null, PDO::PARAM_NULL);
                continue;
            }

            if (is_int($value)) {
                $stmt->bindValue(':' . $field, $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue(':' . $field, (string) $value);
        }

        $stmt->execute();
    }

    private function getTableColumnsForTreatmentNotification(string $tableName): array
    {
        static $cache = [];

        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        $cache[$tableName] = [];

        try {
            if (!$this->tableExists($tableName)) {
                return $cache[$tableName];
            }

            $stmt = $this->db->prepare("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ");

            $stmt->execute([
                ':table_name' => $tableName,
            ]);

            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            foreach ($columns as $column) {
                $cache[$tableName][strtolower((string) $column)] = true;
            }
        } catch (Throwable $e) {
            $cache[$tableName] = [];
        }

        return $cache[$tableName];
    }

    private function getTableColumnsForPaymentSync(string $tableName): array
    {
        static $cache = [];

        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        $cache[$tableName] = [];

        try {
            if (!$this->tableExists($tableName)) {
                return $cache[$tableName];
            }

            $stmt = $this->db->prepare("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ");

            $stmt->execute([
                ':table_name' => $tableName,
            ]);

            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            foreach ($columns as $column) {
                $cache[$tableName][strtolower((string) $column)] = true;
            }
        } catch (Throwable $e) {
            $cache[$tableName] = [];
        }

        return $cache[$tableName];
    }

    private function insertDynamicRow(string $tableName, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $safeTableName = str_replace('`', '', $tableName);
        $fields = array_keys($data);

        $quotedFields = array_map(
            static fn (string $field): string => '`' . str_replace('`', '', $field) . '`',
            $fields
        );

        $placeholders = array_map(
            static fn (string $field): string => ':' . $field,
            $fields
        );

        $sql = "
            INSERT INTO `{$safeTableName}` (
                " . implode(', ', $quotedFields) . "
            ) VALUES (
                " . implode(', ', $placeholders) . "
            )
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($data as $field => $value) {
            if ($value === null) {
                $stmt->bindValue(':' . $field, null, PDO::PARAM_NULL);
                continue;
            }

            if (is_int($value)) {
                $stmt->bindValue(':' . $field, $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue(':' . $field, (string) $value);
        }

        $stmt->execute();
    }

    private function appointmentPatientNameForTreatmentNotification(array $appointment): string
    {
        $patientName = trim(
            (string) (($appointment['patient_first_name'] ?? '') . ' ' .
            ($appointment['patient_middle_name'] ?? '') . ' ' .
            ($appointment['patient_last_name'] ?? ''))
        );

        if ($patientName !== '') {
            return $patientName;
        }

        $guestName = trim(
            (string) (($appointment['guest_first_name'] ?? '') . ' ' .
            ($appointment['guest_middle_name'] ?? '') . ' ' .
            ($appointment['guest_last_name'] ?? ''))
        );

        return $guestName !== '' ? $guestName : 'the patient';
    }

    private function appointmentDentistNameForTreatmentNotification(array $appointment): string
    {
        $dentistName = trim(
            (string) (($appointment['dentist_first_name'] ?? '') . ' ' .
            ($appointment['dentist_middle_name'] ?? '') . ' ' .
            ($appointment['dentist_last_name'] ?? ''))
        );

        return $dentistName !== '' ? 'Dr. ' . $dentistName : 'The dentist';
    }

    private function appointmentDateLabelForTreatmentNotification(array $appointment): string
    {
        $date = trim((string) ($appointment['appointment_date'] ?? ''));

        if ($date === '') {
            return 'the scheduled date';
        }

        $timestamp = strtotime($date);

        return $timestamp !== false ? date('M d, Y', $timestamp) : $date;
    }

    private function appointmentTimeLabelForTreatmentNotification(array $appointment): string
    {
        $time = trim((string) ($appointment['start_time'] ?? ''));

        if ($time === '') {
            return 'the scheduled time';
        }

        $timestamp = strtotime($time);

        return $timestamp !== false ? date('h:i A', $timestamp) : $time;
    }

    private function validDateOrToday($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return date('Y-m-d');
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        $errors = DateTime::getLastErrors();

        if (
            !$date ||
            $date->format('Y-m-d') !== $value ||
            ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return date('Y-m-d');
        }

        return $value;
    }

    private function requiredText($value, string $message, int $maxLength = 255): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new RuntimeException($message);
        }

        return $this->limitText($value, $maxLength);
    }

    private function nullableText($value, int $maxLength = 2000): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $this->limitText($value, $maxLength);
    }

    private function limitText(string $value, int $maxLength): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength);
        }

        return substr($value, 0, $maxLength);
    }

    private function moneyValue($value): float
    {
        $value = trim(str_replace(',', '', (string) $value));

        if ($value === '') {
            return 0.00;
        }

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new RuntimeException('Invalid amount format. Use numbers only, up to 2 decimal places.');
        }

        $number = (float) $value;

        if ($number < 0) {
            throw new RuntimeException('Amount cannot be negative.');
        }

        return round($number, 2);
    }

    private function allowedTreatmentStatus($value): string
    {
        $value = strtolower(trim((string) $value));

        $allowed = [
            'planned',
            'performed',
            'completed',
            'cancelled',
        ];

        return in_array($value, $allowed, true) ? $value : 'completed';
    }

    private function tableExists(string $tableName): bool
    {
        static $cache = [];

        $key = strtolower($tableName);

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ");

            $stmt->execute([
                ':table_name' => $tableName,
            ]);

            $cache[$key] = (int) $stmt->fetchColumn() > 0;

            return $cache[$key];
        } catch (Throwable $e) {
            $cache[$key] = false;

            return false;
        }
    }

    private function tableHasColumn(string $tableName, string $columnName): bool
    {
        static $cache = [];

        $key = strtolower($tableName . '.' . $columnName);

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name
            ");

            $stmt->execute([
                ':table_name' => $tableName,
                ':column_name' => $columnName,
            ]);

            $cache[$key] = (int) $stmt->fetchColumn() > 0;

            return $cache[$key];
        } catch (Throwable $e) {
            $cache[$key] = false;

            return false;
        }
    }

    private function redirectToPatientShow(int $patientId, string $tab = 'patient-record'): void
    {
        if ($patientId <= 0) {
            header('Location: ' . $this->url('/dentist/patients'));
            exit;
        }

        header('Location: ' . $this->url('/dentist/patients/show?id=' . $patientId . '&tab=' . urlencode($tab)));
        exit;
    }


private function getPatientDocumentsForDentist(int $patientId, int $dentistId): array
{
    if ($patientId <= 0 || $dentistId <= 0) {
        return [];
    }

    if (!$this->tableExists('attachments')) {
        return [];
    }

    if (!$this->dentistCanAccessPatient($dentistId, $patientId)) {
        return [];
    }

    $stmt = $this->db->prepare("
        SELECT
            a.*,
            u.first_name AS uploaded_by_first_name,
            u.middle_name AS uploaded_by_middle_name,
            u.last_name AS uploaded_by_last_name,
            ap.appointment_code,
            ap.appointment_date
        FROM attachments a
        LEFT JOIN users u ON u.user_id = a.uploaded_by
        LEFT JOIN appointments ap ON ap.appointment_id = a.appointment_id
        WHERE a.patient_id = :patient_id
        ORDER BY
            a.created_at DESC,
            a.attachment_id DESC
    ");

    $stmt->execute([
        ':patient_id' => $patientId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

private function findPatientDocumentForDentist(int $attachmentId, int $dentistId): ?array
{
    if ($attachmentId <= 0 || $dentistId <= 0 || !$this->tableExists('attachments')) {
        return null;
    }

    $stmt = $this->db->prepare("
        SELECT *
        FROM attachments
        WHERE attachment_id = :attachment_id
        LIMIT 1
    ");

    $stmt->execute([
        ':attachment_id' => $attachmentId,
    ]);

    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        return null;
    }

    $patientId = (int) ($document['patient_id'] ?? 0);

    if ($patientId <= 0 || !$this->dentistCanAccessPatient($dentistId, $patientId)) {
        return null;
    }

    return $document;
}

private function detectMimeType(string $path): string
{
    if (!is_file($path)) {
        throw new RuntimeException('Uploaded file cannot be inspected.');
    }

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
        throw new RuntimeException('Only PDF, JPG, PNG, and WEBP documents are allowed.');
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

private function cleanOriginalFileName(string $name): string
{
    $name = basename($name);
    $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: 'document';
    $name = trim($name);

    return $name !== '' ? $name : 'document';
}

private function isPreviewableDocumentMime(string $mimeType): bool
{
    return $mimeType === 'application/pdf'
        || str_starts_with($mimeType, 'image/');
}


private function serverDateTime(): string
{
    $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');

    return (new DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s');
}

private function findAppointmentForTreatmentForUpdate(
    int $appointmentId,
    int $patientId,
    int $dentistId
): ?array {
    if ($appointmentId <= 0 || $patientId <= 0 || $dentistId <= 0) {
        return null;
    }

    $stmt = $this->db->prepare("
        SELECT
            a.*,
            s.service_name,
            s.estimated_price AS service_estimated_price
        FROM appointments a
        LEFT JOIN services s ON s.service_id = a.service_id
        WHERE a.appointment_id = :appointment_id
          AND a.patient_id = :patient_id
          AND a.dentist_id = :dentist_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':appointment_id' => $appointmentId,
        ':patient_id' => $patientId,
        ':dentist_id' => $dentistId,
    ]);

    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    return $appointment ?: null;
}

private function redirectToPatientShowWithAppointment(
    int $patientId,
    string $tab,
    int $appointmentId
): void {
    if ($patientId <= 0) {
        header('Location: ' . $this->url('/dentist/patients'));
        exit;
    }

    $url = '/dentist/patients/show?id=' . $patientId . '&tab=' . urlencode($tab);

    if ($appointmentId > 0) {
        $url .= '&appointment_id=' . $appointmentId;
    }

    header('Location: ' . $this->url($url));
    exit;
}

    private function url(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }
}