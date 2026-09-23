<?php

namespace App\Controllers\Dentist;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AppointmentRepository;
use App\Repositories\ClinicalRecordRepository;
use App\Repositories\DentalChartRepository;
use App\Repositories\DentistRepository;
use App\Repositories\ExaminationRepository;
use App\Repositories\OdontogramRepository;
use App\Repositories\TreatmentRepository;
use RuntimeException;
use Throwable;
use App\Services\PatientAccessPolicy;
use App\Services\PrivacyAuditService;


class ClinicalRecordController
{
    private AppointmentRepository $appointments;
    private DentistRepository $dentists;
    private ExaminationRepository $examinations;
    private OdontogramRepository $odontograms;
    private TreatmentRepository $treatments;
    private ClinicalRecordRepository $clinicalRecords;
    private DentalChartRepository $dentalChart;
    private PatientAccessPolicy $patientAccess;
private PrivacyAuditService $privacyAudit;

    public function __construct()
    {
        $this->appointments = new AppointmentRepository();
        $this->dentists = new DentistRepository();
        $this->examinations = new ExaminationRepository();
        $this->odontograms = new OdontogramRepository();
        $this->treatments = new TreatmentRepository();
        $this->clinicalRecords = new ClinicalRecordRepository();
        $this->dentalChart = new DentalChartRepository();
        $this->patientAccess = new PatientAccessPolicy();
$this->privacyAudit = new PrivacyAuditService();
    }

    public function index(): void
    {
        Auth::requireRole('dentist');

        $authUser = Auth::user();

        $search = trim((string) ($_GET['search'] ?? ''));
        $date = trim((string) ($_GET['date'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));

        $allowedStatuses = [
            '',
            'pending',
            'confirmed',
            'checked_in',
            'in_progress',
            'completed',
            'rescheduled',
            'cancelled',
            'no_show',
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $records = $this->clinicalRecords->listForDentistUser(
            (int) ($authUser['user_id'] ?? 0),
            $date !== '' ? $date : null,
            $status !== '' ? $status : null,
            $search
        );

        View::render('dentist.clinical.index', [
            'records' => $records,
            'search' => $search,
            'date' => $date,
            'status' => $status,
        ]);
    }

    public function show(): void
    {
        Auth::requireRole('dentist');

        $appointmentId = (int) ($_GET['appointment_id'] ?? ($_GET['id'] ?? 0));

        try {
            [$dentist, $appointment] = $this->resolveDentistAppointment($appointmentId);

            $patientId = (int) ($appointment['patient_id'] ?? 0);

            if ($patientId <= 0) {
                throw new RuntimeException('This appointment has no linked patient record yet. Please check in or convert the patient first.');
            }

            $examinationId = $this->findOrCreateExaminationId(
                $appointmentId,
                $patientId,
                (int) $dentist['dentist_id']
            );

            $examination = $this->examinations->findByAppointmentId($appointmentId) ?: [];
            $odontogramEntries = $this->getLegacyOdontogramEntries($examinationId);
            $treatment = $this->getTreatmentByAppointmentId($appointmentId);
            $entries = $this->dentalChart->getEntriesByPatientId($patientId);

            View::render('dentist.clinical.show', [
                'dentist' => $dentist,
                'appointment' => $appointment,
                'examination' => $examination,
                'odontogramEntries' => $odontogramEntries,
                'treatment' => $treatment,
                'patientId' => $patientId,
                'appointmentId' => $appointmentId,
                'examinationId' => $examinationId,
                'entries' => $entries,
                'entriesByTooth' => $this->dentalChart->groupByTooth($entries),
                'surfaces' => DentalChartRepository::SURFACES,
                'procedures' => $this->procedureOptions(),
                'statuses' => DentalChartRepository::STATUSES,
                'flash_success' => Session::get('flash_success'),
                'flash_error' => Session::get('flash_error'),
            ]);

            Session::remove('flash_success');
            Session::remove('flash_error');
        } catch (Throwable $e) {
            http_response_code(403);
            exit($e->getMessage());
        }
    }

    public function chart(): void
{
    Auth::requireRole('dentist');

    $appointmentId = (int) ($_GET['appointment_id'] ?? ($_GET['id'] ?? 0));

    try {
        [$dentist, $appointment] = $this->resolveDentistAppointment($appointmentId);

        $patientId = (int) ($appointment['patient_id'] ?? 0);

        if ($patientId <= 0) {
            throw new RuntimeException('This appointment has no linked patient record yet. Please check in or convert the patient first.');
        }

        $examinationId = $this->findOrCreateExaminationId(
            $appointmentId,
            $patientId,
            (int) $dentist['dentist_id']
        );

        $entries = $this->dentalChart->getEntriesByPatientId($patientId);

        /*
            IMPORTANT:
            This is why your treatment values were not showing after save.
            The chart page must receive the treatment record.
        */
        $treatment = $this->getTreatmentByAppointmentId($appointmentId);

        View::render('dentist.clinical-record.chart', [
            'dentist' => $dentist,
            'appointment' => $appointment,

            'patientId' => $patientId,
            'appointmentId' => $appointmentId,
            'examinationId' => $examinationId,

            'entries' => $entries,
            'entriesByTooth' => $this->dentalChart->groupByTooth($entries),
            'surfaces' => DentalChartRepository::SURFACES,
            'procedures' => DentalChartRepository::PROCEDURES,
            'statuses' => DentalChartRepository::STATUSES,

            'treatment' => $treatment,

            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    } catch (Throwable $e) {
        http_response_code(403);
        exit($e->getMessage());
    }
}

    public function saveExamination(): void
    {
        Auth::requireRole('dentist');
        $this->ensureCsrf();

        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

        try {
            [$dentist, $appointment] = $this->resolveDentistAppointment($appointmentId);

            $patientId = (int) ($appointment['patient_id'] ?? 0);

            if ($patientId <= 0) {
                throw new RuntimeException('This appointment is not yet linked to a patient record.');
            }

            $examinationDate = trim((string) ($_POST['examination_date'] ?? date('Y-m-d')));

            if ($examinationDate === '') {
                $examinationDate = date('Y-m-d');
            }

            $this->examinations->updateOrCreateByAppointment($appointmentId, [
                'patient_id' => $patientId,
                'dentist_id' => (int) $dentist['dentist_id'],
                'examination_date' => $examinationDate,
                'chief_complaint' => trim((string) ($_POST['chief_complaint'] ?? '')),
                'intraoral_exam_notes' => trim((string) ($_POST['intraoral_exam_notes'] ?? '')),
                'clinical_findings' => trim((string) ($_POST['clinical_findings'] ?? '')),
                'diagnosis' => trim((string) ($_POST['diagnosis'] ?? '')),
                'treatment_plan' => trim((string) ($_POST['treatment_plan'] ?? '')),
                'recommendations' => trim((string) ($_POST['recommendations'] ?? '')),
                'notes' => trim((string) ($_POST['notes'] ?? '')),
            ]);

            Session::set('flash_success', 'Examination saved successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        $this->redirectAfterPost($appointmentId);
    }

    public function saveOdontogram(): void
    {
        Auth::requireRole('dentist');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            $this->jsonOrExit(false, 'Invalid CSRF token.', 419);
        }

        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

        try {
            $authUser = Auth::user();
            $userId = (int) ($authUser['user_id'] ?? 0);

            $dentist = $this->dentalChart->findDentistByUserId($userId);

            if (!$dentist || empty($dentist['dentist_id'])) {
                throw new RuntimeException('Dentist profile not found.');
            }

            $dentistId = (int) $dentist['dentist_id'];
            $patientId = (int) ($_POST['patient_id'] ?? 0);
            $examinationId = (int) ($_POST['examination_id'] ?? 0);

            if ($appointmentId <= 0 || $patientId <= 0) {
                throw new RuntimeException('Invalid appointment or patient record.');
            }

            $appointment = $this->appointments->findDentistAppointmentById($appointmentId, $dentistId);

            if (!$appointment) {
                throw new RuntimeException('Appointment not found or not assigned to you.');
            }

            if ((int) ($appointment['patient_id'] ?? 0) !== $patientId) {
                throw new RuntimeException('Patient record does not match this appointment.');
            }

            if ($examinationId <= 0) {
                $examinationId = $this->dentalChart->findOrCreateExamination(
                    $appointmentId,
                    $patientId,
                    $dentistId
                );
            }

            if (!$this->dentalChart->examinationBelongsToPatient($examinationId, $patientId, $dentistId)) {
                throw new RuntimeException('Invalid examination record.');
            }

            $toothNumbers = $_POST['tooth_numbers'] ?? [];

            if (!is_array($toothNumbers) || empty($toothNumbers)) {
                $singleToothValue = trim((string) ($_POST['tooth_number'] ?? ''));

                if ($singleToothValue !== '') {
                    $toothNumbers = array_filter(array_map('trim', explode(',', $singleToothValue)));
                }
            }

            $toothNumbers = array_values(array_unique(array_filter(
                array_map(
                    static fn ($value): int => (int) $value,
                    is_array($toothNumbers) ? $toothNumbers : []
                ),
                static fn (int $value): bool => $value > 0
            )));

            $validTeeth = [
                18, 17, 16, 15, 14, 13, 12, 11,
                21, 22, 23, 24, 25, 26, 27, 28,
                48, 47, 46, 45, 44, 43, 42, 41,
                31, 32, 33, 34, 35, 36, 37, 38,
            ];

            foreach ($toothNumbers as $toothNumber) {
                if (!in_array($toothNumber, $validTeeth, true)) {
                    throw new RuntimeException('Invalid tooth number selected.');
                }
            }

            if (empty($toothNumbers)) {
                $toothNumbers = [0];
            }

            $surface = trim((string) ($_POST['surface'] ?? ''));

            if ($surface !== '' && !in_array($surface, DentalChartRepository::SURFACES, true)) {
                throw new RuntimeException('Invalid tooth surface.');
            }

            $procedureName = trim((string) ($_POST['procedure_name'] ?? ''));

            if ($procedureName === '') {
                throw new RuntimeException('Please select a dental procedure.');
            }

            if (!in_array($procedureName, $this->procedureOptions(), true)) {
                throw new RuntimeException('Invalid dental procedure.');
            }

            $status = trim((string) ($_POST['status'] ?? 'planned'));

            if (!in_array($status, DentalChartRepository::STATUSES, true)) {
                $status = 'planned';
            }

            $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 2000);

            $savedEntries = [];

            foreach ($toothNumbers as $toothNumber) {
                $entryData = [
                    'patient_id' => $patientId,
                    'appointment_id' => $appointmentId,
                    'examination_id' => $examinationId,
                    'dentist_id' => $dentistId,
                    'tooth_number' => $toothNumber,
                    'surface' => $surface,
                    'procedure_name' => $procedureName,
                    'status' => $status,
                    'notes' => $notes,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ];

                $entryId = $this->dentalChart->createEntry($entryData);
                $entry = $this->dentalChart->findEntryById($entryId);

                if ($entry) {
                    $savedEntries[] = $entry;
                }
            }

            if ($this->wantsJson()) {
                header('Content-Type: application/json');

                echo json_encode([
                    'success' => true,
                    'message' => 'Dental chart entry saved successfully.',
                    'entries' => $savedEntries,
                ]);

                exit;
            }

            Session::set('flash_success', 'Dental chart entry saved successfully.');
            header('Location: ' . $this->url('/dentist/clinical-record/chart?appointment_id=' . $appointmentId));
            exit;
        } catch (Throwable $e) {
            if ($this->wantsJson()) {
                $this->jsonOrExit(false, $e->getMessage(), 422);
            }

            Session::set('flash_error', $e->getMessage());
            header('Location: ' . $this->url('/dentist/clinical-record/chart?appointment_id=' . $appointmentId));
            exit;
        }
    }

    public function saveTreatment(): void
{
    Auth::requireRole('dentist');
    $this->ensureCsrf();

    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

    try {
        [$dentist, $appointment] = $this->resolveDentistAppointment($appointmentId);

        $patientId = (int) ($appointment['patient_id'] ?? 0);

        if ($patientId <= 0) {
            throw new RuntimeException('This appointment is not yet linked to a patient record.');
        }

        $examination = $this->examinations->findByAppointmentId($appointmentId);

        $actualCharge = max(0, (float) ($_POST['actual_charge'] ?? 0));
        $amountPaid = max(0, (float) ($_POST['amount_paid'] ?? 0));
        $balance = max(0, $actualCharge - $amountPaid);

        $treatmentStatus = trim((string) ($_POST['treatment_status'] ?? 'completed'));
        $allowedTreatmentStatuses = ['planned', 'in_progress', 'completed'];

        if (!in_array($treatmentStatus, $allowedTreatmentStatuses, true)) {
            $treatmentStatus = 'completed';
        }

        $treatmentDate = trim((string) ($_POST['treatment_date'] ?? date('Y-m-d')));

        if ($treatmentDate === '') {
            $treatmentDate = date('Y-m-d');
        }

        $this->treatments->updateOrCreateByAppointment($appointmentId, [
            'examination_id' => $examination ? (int) ($examination['examination_id'] ?? 0) : null,
            'patient_id' => $patientId,
            'dentist_id' => (int) $dentist['dentist_id'],

            'treatment_date' => $treatmentDate,
            'procedure_name' => trim((string) ($_POST['procedure_name'] ?? '')),
            'treated_tooth' => trim((string) ($_POST['treated_tooth'] ?? '')),

            'description' => trim((string) ($_POST['description'] ?? '')),
            'prescription' => trim((string) ($_POST['prescription'] ?? '')),
            'recommendation' => trim((string) ($_POST['recommendation'] ?? '')),

            'estimated_price' => max(0, (float) ($_POST['estimated_price'] ?? 0)),
            'actual_charge' => $actualCharge,
            'amount_paid' => $amountPaid,
            'balance' => $balance,

            'treatment_status' => $treatmentStatus,
            'remarks' => trim((string) ($_POST['remarks'] ?? '')),
        ]);

        Session::set('flash_success', 'Treatment record saved successfully.');
    } catch (Throwable $e) {
        Session::set('flash_error', $e->getMessage());
    }

    /*
        Redirect back to chart page if the form requested it.
        This keeps the user on the Patient Treatment Record section.
    */
    $redirectTo = trim((string) ($_POST['redirect_to'] ?? ''));

    if ($redirectTo !== '' && str_starts_with($redirectTo, '/dentist/clinical-record/chart')) {
        header('Location: ' . $this->url($redirectTo));
        exit;
    }

    $this->redirectToClinicalRecord($appointmentId);
}

    private function resolveDentistAppointment(int $appointmentId): array
    {
        if ($appointmentId <= 0) {
            throw new RuntimeException('Invalid appointment.');
        }

        $user = Auth::user();
        $dentist = $this->dentists->findByUserId((int) ($user['user_id'] ?? 0));
        $appointment = $this->appointments->findDetailedById($appointmentId);

        if (!$dentist) {
            throw new RuntimeException('Dentist profile not found.');
        }

        if (!$appointment) {
            throw new RuntimeException('Appointment not found.');
        }

        if ((int) ($appointment['dentist_id'] ?? 0) !== (int) ($dentist['dentist_id'] ?? 0)) {
            throw new RuntimeException('You are not allowed to update this record.');
        }

        return [$dentist, $appointment];
    }

    

public function resetOdontogram(): void
{
    Auth::requireRole('dentist');

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    $postedPatientId = (int) ($_POST['patient_id'] ?? 0);

    try {
        [$dentist, $appointment] = $this->resolveDentistAppointment($appointmentId);

        $patientId = (int) ($appointment['patient_id'] ?? 0);

        if ($patientId <= 0) {
            throw new RuntimeException('This appointment has no linked patient record.');
        }

        if ($postedPatientId > 0 && $postedPatientId !== $patientId) {
            throw new RuntimeException('Patient record does not match this appointment.');
        }

    
        $deleted = $this->dentalChart->deleteAllEntriesForPatient($patientId);

        Session::set(
            'flash_success',
            'Odontogram reset successfully. Removed ' . $deleted . ' saved record(s).'
        );
    } catch (Throwable $e) {
        Session::set('flash_error', $e->getMessage());
    }

    header('Location: ' . $this->url('/dentist/clinical-record/chart?appointment_id=' . urlencode((string) $appointmentId)));
    exit;
}



    private function findOrCreateExaminationId(int $appointmentId, int $patientId, int $dentistId): int
    {
        $examination = $this->examinations->findByAppointmentId($appointmentId);

        if ($examination && !empty($examination['examination_id'])) {
            return (int) $examination['examination_id'];
        }

        $examinationId = (int) $this->examinations->updateOrCreateByAppointment($appointmentId, [
            'patient_id' => $patientId,
            'dentist_id' => $dentistId,
            'examination_date' => date('Y-m-d'),
            'chief_complaint' => '',
            'intraoral_exam_notes' => '',
            'clinical_findings' => '',
            'diagnosis' => '',
            'treatment_plan' => '',
            'recommendations' => '',
            'notes' => '',
        ]);

        if ($examinationId <= 0) {
            throw new RuntimeException('Unable to create examination record.');
        }

        return $examinationId;
    }

    private function getLegacyOdontogramEntries(int $examinationId): array
    {
        if ($examinationId <= 0) {
            return [];
        }

        try {
            if (method_exists($this->odontograms, 'getByExaminationId')) {
                $entries = $this->odontograms->getByExaminationId($examinationId);
                return is_array($entries) ? $entries : [];
            }

            if (method_exists($this->odontograms, 'findByExaminationId')) {
                $entries = $this->odontograms->findByExaminationId($examinationId);
                return is_array($entries) ? $entries : [];
            }
        } catch (Throwable $e) {
            return [];
        }

        return [];
    }

    private function getTreatmentByAppointmentId(int $appointmentId): array
    {
        if ($appointmentId <= 0) {
            return [];
        }

        try {
            if (method_exists($this->treatments, 'findByAppointmentId')) {
                $treatment = $this->treatments->findByAppointmentId($appointmentId);
                return is_array($treatment) ? $treatment : [];
            }

            if (method_exists($this->treatments, 'findByAppointment')) {
                $treatment = $this->treatments->findByAppointment($appointmentId);
                return is_array($treatment) ? $treatment : [];
            }
        } catch (Throwable $e) {
            return [];
        }

        return [];
    }

    private function procedureOptions(): array
    {
        return array_values(array_unique(array_merge(
            DentalChartRepository::PROCEDURES,
            [
                'Tooth Extraction',
                'Dental Cleaning',
                'Tooth Restoration',
                'Root Canal Treatment',
                'Dentures, Crowns and Fixed Bridges',
                'Orthodontics (Braces)',
                'Surgery',
                'Dental Implants',
                'Teeth Whitening',
            ]
        )));
    }

    private function ensureCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }

    private function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    private function jsonOrExit(bool $success, string $message, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        echo json_encode([
            'success' => $success,
            'message' => $message,
        ]);

        exit;
    }

    private function redirectAfterPost(int $appointmentId): void
    {
        $redirectTo = trim((string) ($_POST['redirect_to'] ?? ''));

        if ($redirectTo !== '' && str_starts_with($redirectTo, '/dentist/')) {
            header('Location: ' . $this->url($redirectTo));
            exit;
        }

        $this->redirectToClinicalRecord($appointmentId);
    }

    private function redirectToClinicalRecord(int $appointmentId): void
    {
        header('Location: ' . $this->url('/dentist/clinical-record?appointment_id=' . urlencode((string) $appointmentId)));
        exit;
    }

    private function url(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }
}