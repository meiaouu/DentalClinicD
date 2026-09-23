<?php

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Repositories\PatientRepository;
use App\Services\PatientConversionService;
use DateTime;
use PDO;
use RuntimeException;
use Throwable;
use App\Services\PatientAccessPolicy;
use App\Services\PrivacyAuditService;


class PatientController
{
    private PatientRepository $patients;
    private PatientConversionService $conversion;
    
    private PDO $db;

private PatientAccessPolicy $patientAccess;
private PrivacyAuditService $privacyAudit;





public function updateRecord(): void
{
    Auth::requireRole('staff');

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $staff = Auth::user();
    $patientId = (int) ($_POST['patient_id'] ?? 0);

    try {
        if ($patientId <= 0) {
            throw new RuntimeException('Invalid patient record.');
        }

        $firstName = $this->requiredText($_POST['first_name'] ?? '', 'First name is required.');
        $lastName = $this->requiredText($_POST['last_name'] ?? '', 'Last name is required.');
        $contactNumber = trim((string) ($_POST['contact_number'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $birthDate = $this->dateOrNull($_POST['birth_date'] ?? null);

        if ($contactNumber !== '' && !$this->isValidPhilippineMobile($contactNumber)) {
            throw new RuntimeException('Invalid contact number. Use 09XXXXXXXXX, 639XXXXXXXXX, or +639XXXXXXXXX.');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email address.');
        }

        if ($birthDate !== null && strtotime($birthDate) > time()) {
            throw new RuntimeException('Birth date cannot be in the future.');
        }

        $this->db->beginTransaction();

        $this->patients->updateProfile($patientId, [
            'first_name' => $firstName,
            'middle_name' => $this->textOrNull($_POST['middle_name'] ?? null),
            'last_name' => $lastName,
            'birth_date' => $birthDate,
            'sex' => $this->allowedOrNull($_POST['sex'] ?? null, ['Male', 'Female', 'male', 'female']),
            'civil_status' => $this->allowedOrNull($_POST['civil_status'] ?? null, ['Single', 'Married', 'Widowed', 'Separated', 'single', 'married', 'widowed', 'separated']),
            'address' => $this->textOrNull($_POST['address'] ?? null),
            'occupation' => $this->textOrNull($_POST['occupation'] ?? null),
            'contact_number' => $contactNumber !== '' ? $contactNumber : null,
            'email' => $email !== '' ? $email : null,
            'emergency_contact_name' => $this->textOrNull($_POST['emergency_contact_name'] ?? null),
            'emergency_contact_number' => $this->textOrNull($_POST['emergency_contact_number'] ?? null),
            'notes' => $this->textOrNull($_POST['notes'] ?? null),
        ]);

        $dentalData = [
            'worn_denture' => $this->yesNoOrNull($_POST['worn_denture'] ?? null),
            'last_dental_visit' => $this->dateOrNull($_POST['last_dental_visit'] ?? null),
            'last_dental_visit_reason' => $this->textOrNull($_POST['last_dental_visit_reason'] ?? null),
            'updated_by' => (int) ($staff['user_id'] ?? 0),
        ];

        $existingDentalId = $this->findExistingDentalHistoryId($patientId);

        if ($existingDentalId > 0) {
            $stmt = $this->db->prepare("
                UPDATE patient_dental_histories
                SET worn_denture = :worn_denture,
                    last_dental_visit = :last_dental_visit,
                    last_dental_visit_reason = :last_dental_visit_reason,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE dental_history_id = :dental_history_id
            ");

            $stmt->execute([
                ':worn_denture' => $dentalData['worn_denture'],
                ':last_dental_visit' => $dentalData['last_dental_visit'],
                ':last_dental_visit_reason' => $dentalData['last_dental_visit_reason'],
                ':updated_by' => $dentalData['updated_by'],
                ':dental_history_id' => $existingDentalId,
            ]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO patient_dental_histories (
                    patient_id,
                    worn_denture,
                    last_dental_visit,
                    last_dental_visit_reason,
                    updated_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :patient_id,
                    :worn_denture,
                    :last_dental_visit,
                    :last_dental_visit_reason,
                    :updated_by,
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                ':patient_id' => $patientId,
                ':worn_denture' => $dentalData['worn_denture'],
                ':last_dental_visit' => $dentalData['last_dental_visit'],
                ':last_dental_visit_reason' => $dentalData['last_dental_visit_reason'],
                ':updated_by' => $dentalData['updated_by'],
            ]);
        }

        $medicalData = [
            'under_physician_care' => $this->yesNoOrNull($_POST['under_physician_care'] ?? null),
            'physician_care_details' => $this->textOrNull($_POST['physician_care_details'] ?? null),
            'is_pregnant' => $this->yesNoOrNull($_POST['is_pregnant'] ?? null),
            'taking_medicine' => $this->yesNoOrNull($_POST['taking_medicine'] ?? null),
            'medicine_details' => $this->textOrNull($_POST['medicine_details'] ?? null),

            'condition_high_blood_pressure' => isset($_POST['condition_high_blood_pressure']) ? 1 : 0,
            'condition_low_blood_pressure' => isset($_POST['condition_low_blood_pressure']) ? 1 : 0,
            'condition_asthma' => isset($_POST['condition_asthma']) ? 1 : 0,
            'condition_heart_disease' => isset($_POST['condition_heart_disease']) ? 1 : 0,
            'condition_diabetes' => isset($_POST['condition_diabetes']) ? 1 : 0,
            'condition_tuberculosis' => isset($_POST['condition_tuberculosis']) ? 1 : 0,
            'condition_thyroid_problem' => isset($_POST['condition_thyroid_problem']) ? 1 : 0,
            'condition_bleeding_problems' => isset($_POST['condition_bleeding_problems']) ? 1 : 0,
            'condition_hiv_aids' => isset($_POST['condition_hiv_aids']) ? 1 : 0,
            'condition_hepatitis' => isset($_POST['condition_hepatitis']) ? 1 : 0,
            'condition_others' => isset($_POST['condition_others']) ? 1 : 0,

            'allergy_local_anesthesia' => $this->yesNoOrNull($_POST['allergy_local_anesthesia'] ?? null),
            'allergy_antibiotics' => $this->yesNoOrNull($_POST['allergy_antibiotics'] ?? null),
            'allergy_pain_killer' => $this->yesNoOrNull($_POST['allergy_pain_killer'] ?? null),
            'allergy_others' => $this->yesNoOrNull($_POST['allergy_others'] ?? null),

            'hospitalized' => $this->yesNoOrNull($_POST['hospitalized'] ?? null),
            'hospitalization_when' => $this->textOrNull($_POST['hospitalization_when'] ?? null),
            'hospitalization_why' => $this->textOrNull($_POST['hospitalization_why'] ?? null),
            'updated_by' => (int) ($staff['user_id'] ?? 0),
        ];

        $existingMedicalId = $this->findExistingMedicalHistoryId($patientId);

        if ($existingMedicalId > 0) {
            $medicalData['medical_history_id'] = $existingMedicalId;

            $stmt = $this->db->prepare("
                UPDATE patient_medical_histories
                SET under_physician_care = :under_physician_care,
                    physician_care_details = :physician_care_details,
                    is_pregnant = :is_pregnant,
                    taking_medicine = :taking_medicine,
                    medicine_details = :medicine_details,

                    condition_high_blood_pressure = :condition_high_blood_pressure,
                    condition_low_blood_pressure = :condition_low_blood_pressure,
                    condition_asthma = :condition_asthma,
                    condition_heart_disease = :condition_heart_disease,
                    condition_diabetes = :condition_diabetes,
                    condition_tuberculosis = :condition_tuberculosis,
                    condition_thyroid_problem = :condition_thyroid_problem,
                    condition_bleeding_problems = :condition_bleeding_problems,
                    condition_hiv_aids = :condition_hiv_aids,
                    condition_hepatitis = :condition_hepatitis,
                    condition_others = :condition_others,

                    allergy_local_anesthesia = :allergy_local_anesthesia,
                    allergy_antibiotics = :allergy_antibiotics,
                    allergy_pain_killer = :allergy_pain_killer,
                    allergy_others = :allergy_others,

                    hospitalized = :hospitalized,
                    hospitalization_when = :hospitalization_when,
                    hospitalization_why = :hospitalization_why,
                    updated_by = :updated_by
                WHERE medical_history_id = :medical_history_id
            ");

            $stmt->execute($this->prefixParams($medicalData));
        } else {
            $medicalData['patient_id'] = $patientId;

            $stmt = $this->db->prepare("
                INSERT INTO patient_medical_histories (
                    patient_id,
                    under_physician_care,
                    physician_care_details,
                    is_pregnant,
                    taking_medicine,
                    medicine_details,

                    condition_high_blood_pressure,
                    condition_low_blood_pressure,
                    condition_asthma,
                    condition_heart_disease,
                    condition_diabetes,
                    condition_tuberculosis,
                    condition_thyroid_problem,
                    condition_bleeding_problems,
                    condition_hiv_aids,
                    condition_hepatitis,
                    condition_others,

                    allergy_local_anesthesia,
                    allergy_antibiotics,
                    allergy_pain_killer,
                    allergy_others,

                    hospitalized,
                    hospitalization_when,
                    hospitalization_why,
                    updated_by
                ) VALUES (
                    :patient_id,
                    :under_physician_care,
                    :physician_care_details,
                    :is_pregnant,
                    :taking_medicine,
                    :medicine_details,

                    :condition_high_blood_pressure,
                    :condition_low_blood_pressure,
                    :condition_asthma,
                    :condition_heart_disease,
                    :condition_diabetes,
                    :condition_tuberculosis,
                    :condition_thyroid_problem,
                    :condition_bleeding_problems,
                    :condition_hiv_aids,
                    :condition_hepatitis,
                    :condition_others,

                    :allergy_local_anesthesia,
                    :allergy_antibiotics,
                    :allergy_pain_killer,
                    :allergy_others,

                    :hospitalized,
                    :hospitalization_when,
                    :hospitalization_why,
                    :updated_by
                )
            ");

            $stmt->execute($this->prefixParams($medicalData));
        }

        $this->db->commit();

        Session::set('flash_success', 'Patient record, dental history, and medical history updated successfully.');
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        Session::set('flash_error', $e->getMessage());
    }

    header('Location: ' . $this->url('/staff/patients/show?id=' . $patientId));
    exit;
}








    public function __construct()
    {
        $this->patients = new PatientRepository();
        $this->conversion = new PatientConversionService();
        $this->db = Database::getConnection();
        $this->patientAccess = new PatientAccessPolicy();
$this->privacyAudit = new PrivacyAuditService();
    }

    public function index(): void
    {
        Auth::requireRole('staff');

        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        View::render('staff.patients.index', [
            'patients' => $this->patients->paginateForStaff($keyword, $perPage, $offset),
            'keyword' => $keyword,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $this->patients->countForStaff($keyword),
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

   public function show(): void
{
    Auth::requireRole('staff');

    $user = Auth::user();
    $patientId = (int) ($_GET['id'] ?? 0);

    try {
        if ($patientId <= 0) {
            throw new RuntimeException('Patient not found.');
        }

        $this->patientAccess->assertCanViewPatient($user, $patientId);

        $patient = $this->patients->findById($patientId);

        if (!$patient) {
            http_response_code(404);
            exit('Patient not found.');
        }

        $this->privacyAudit->log(
            (int) ($user['user_id'] ?? 0),
            'patients',
            'view',
            'patient',
            $patientId,
            'Viewed patient profile.'
        );
    } catch (RuntimeException $e) {
        http_response_code(403);
        exit($e->getMessage());
    }

    $odontogramEntries = $this->getPatientOdontogramEntries($patientId);

    View::render('staff.patients.show', [
        'patient' => $patient,
        'medicalHistory' => $this->patients->findMedicalHistory($patientId) ?: [],
        'dentalHistory' => $this->patients->findDentalHistory($patientId) ?: [],
        'appointments' => $this->patients->getAppointmentHistory($patientId),

        /*
            Dentist-created intraoral exam / odontogram records.
            Staff can view these but should not edit them.
        */
        'odontogramEntries' => $odontogramEntries,
        'odontogramEntriesByTooth' => $this->groupOdontogramEntriesByTooth($odontogramEntries),
        'latestIntraoralExam' => $this->getLatestIntraoralExam($patientId),

        'flash_success' => Session::get('flash_success'),
        'flash_error' => Session::get('flash_error'),
    ]);

    Session::remove('flash_success');
    Session::remove('flash_error');
}

    public function create(): void
    {
        Auth::requireRole('staff');

        View::render('staff.patients.create', [
            'old' => Session::get('old', []),
            'errors' => Session::get('errors', []),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('old');
        Session::remove('errors');
        Session::remove('flash_error');
    }

    public function store(): void
{
    Auth::requireRole('staff');

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $staff = Auth::user();
    $staffUserId = (int) ($staff['user_id'] ?? 0);

    try {
        $data = $this->validatedPatientInput($_POST);

        $patientId = $this->conversion->createOrUseExistingPatient(
            $data,
            $staffUserId
        );

        $this->saveInitialMedicalHistory($patientId, $_POST, $staffUserId);
        $this->saveInitialDentalHistory($patientId, $_POST, $staffUserId);

$this->privacyAudit->log(
    $staffUserId,
    'patients',
    'create',
    'patient',
    $patientId,
    'Created or linked patient profile.'
);

        Session::set('flash_success', 'Patient saved successfully.');
        header('Location: ' . $this->url('/staff/patients/show?id=' . $patientId));
        exit;
    } catch (Throwable $e) {
        Session::set('flash_error', $e->getMessage());
        Session::set('old', $_POST);
        header('Location: ' . $this->url('/staff/patients/create'));
        exit;
    }
}










    public function updateProfile(): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $staff = Auth::user();
$patientId = (int) ($_POST['patient_id'] ?? 0);

try {
    if ($patientId <= 0) {
        throw new RuntimeException('Invalid patient record.');
    }

    $this->patientAccess->assertCanViewPatient($staff, $patientId);

            $firstName = $this->requiredText($_POST['first_name'] ?? '', 'First name is required.');
            $lastName = $this->requiredText($_POST['last_name'] ?? '', 'Last name is required.');
            $contactNumber = trim((string) ($_POST['contact_number'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $birthDate = $this->dateOrNull($_POST['birth_date'] ?? null);

            if ($contactNumber !== '' && !$this->isValidPhilippineMobile($contactNumber)) {
                throw new RuntimeException('Invalid contact number. Use 09XXXXXXXXX, 639XXXXXXXXX, or +639XXXXXXXXX.');
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Invalid email address.');
            }

            if ($birthDate !== null && strtotime($birthDate) > time()) {
                throw new RuntimeException('Birth date cannot be in the future.');
            }

            $insuranceEffectiveDate = $this->dateOrNull($_POST['insurance_effective_date'] ?? null);

            $this->patients->updateProfile($patientId, [
                
                'first_name' => $firstName,
                'middle_name' => $this->textOrNull($_POST['middle_name'] ?? null),
                'last_name' => $lastName,
                'birth_date' => $birthDate,
                'sex' => $this->allowedOrNull($_POST['sex'] ?? null, ['Male', 'Female', 'male', 'female']),
                'civil_status' => $this->allowedOrNull($_POST['civil_status'] ?? null, ['Single', 'Married', 'Widowed', 'Separated', 'single', 'married', 'widowed', 'separated']),
                'address' => $this->textOrNull($_POST['address'] ?? null),
                'occupation' => $this->textOrNull($_POST['occupation'] ?? null),
                'contact_number' => $contactNumber !== '' ? $contactNumber : null,
                'email' => $email !== '' ? $email : null,
                'emergency_contact_name' => $this->textOrNull($_POST['emergency_contact_name'] ?? null),
                'emergency_contact_number' => $this->textOrNull($_POST['emergency_contact_number'] ?? null),
                'notes' => $this->textOrNull($_POST['notes'] ?? null),

                /*
                    Optional columns.
                    Your PatientRepository::updateProfile() should ignore these if the column
                    does not exist in your patients table.
                */
                'religion' => $this->textOrNull($_POST['religion'] ?? null),
                'nationality' => $this->textOrNull($_POST['nationality'] ?? null),
                'nickname' => $this->textOrNull($_POST['nickname'] ?? null),
                'home_number' => $this->textOrNull($_POST['home_number'] ?? null),
                'office_number' => $this->textOrNull($_POST['office_number'] ?? null),
                'fax_number' => $this->textOrNull($_POST['fax_number'] ?? null),
                'dental_insurance' => $this->textOrNull($_POST['dental_insurance'] ?? null),
                'insurance_effective_date' => $insuranceEffectiveDate,
                'guardian_name' => $this->textOrNull($_POST['guardian_name'] ?? null),
                'guardian_occupation' => $this->textOrNull($_POST['guardian_occupation'] ?? null),
                'referred_by' => $this->textOrNull($_POST['referred_by'] ?? null),
                'consultation_reason' => $this->textOrNull($_POST['consultation_reason'] ?? null),
            ]);
            $this->privacyAudit->log(
    (int) ($staff['user_id'] ?? 0),
    'patients',
    'update',
    'patient',
    $patientId,
    'Updated patient profile.'
);

            Session::set('flash_success', 'Patient information updated successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: ' . $this->url('/staff/patients/show?id=' . $patientId));
        exit;
    }



    

    public function saveDentalHistory(): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $staff = Auth::user();
        $patientId = (int) ($_POST['patient_id'] ?? 0);

        try {
            if ($patientId <= 0) {
                throw new RuntimeException('Invalid patient record.');
            }

            $data = [
                'worn_denture' => $this->yesNoOrNull($_POST['worn_denture'] ?? null),
                'last_dental_visit' => $this->dateOrNull($_POST['last_dental_visit'] ?? null),
                'last_dental_visit_reason' => $this->textOrNull($_POST['last_dental_visit_reason'] ?? null),
                'updated_by' => (int) ($staff['user_id'] ?? 0),
            ];

            $existingId = $this->findExistingDentalHistoryId($patientId);

            if ($existingId > 0) {
                $stmt = $this->db->prepare("
                    UPDATE patient_dental_histories
                    SET worn_denture = :worn_denture,
                        last_dental_visit = :last_dental_visit,
                        last_dental_visit_reason = :last_dental_visit_reason,
                        updated_by = :updated_by,
                        updated_at = NOW()
                    WHERE dental_history_id = :dental_history_id
                ");

                $stmt->execute([
                    ':worn_denture' => $data['worn_denture'],
                    ':last_dental_visit' => $data['last_dental_visit'],
                    ':last_dental_visit_reason' => $data['last_dental_visit_reason'],
                    ':updated_by' => $data['updated_by'],
                    ':dental_history_id' => $existingId,
                ]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO patient_dental_histories (
                        patient_id,
                        worn_denture,
                        last_dental_visit,
                        last_dental_visit_reason,
                        updated_by,
                        created_at,
                        updated_at
                    ) VALUES (
                        :patient_id,
                        :worn_denture,
                        :last_dental_visit,
                        :last_dental_visit_reason,
                        :updated_by,
                        NOW(),
                        NOW()
                    )
                ");

                $stmt->execute([
                    ':patient_id' => $patientId,
                    ':worn_denture' => $data['worn_denture'],
                    ':last_dental_visit' => $data['last_dental_visit'],
                    ':last_dental_visit_reason' => $data['last_dental_visit_reason'],
                    ':updated_by' => $data['updated_by'],
                ]);
            }
            $this->patientAccess->assertCanViewPatient(Auth::user(), $patientId);

$this->privacyAudit->log(
    (int) (Auth::user()['user_id'] ?? 0),
    'patient_dental_histories',
    'update',
    'patient',
    $patientId,
    'Updated patient dental history.'
);

            Session::set('flash_success', 'Dental history saved successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: ' . $this->url('/staff/patients/show?id=' . $patientId));
        exit;
    }

    public function saveMedicalHistory(): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $staff = Auth::user();
        $patientId = (int) ($_POST['patient_id'] ?? 0);

        try {
            if ($patientId <= 0) {
                throw new RuntimeException('Invalid patient record.');
            }

            $data = [
                'under_physician_care' => $this->yesNoOrNull($_POST['under_physician_care'] ?? null),
                'physician_care_details' => $this->textOrNull($_POST['physician_care_details'] ?? null),
                'is_pregnant' => $this->yesNoOrNull($_POST['is_pregnant'] ?? null),
                'taking_medicine' => $this->yesNoOrNull($_POST['taking_medicine'] ?? null),
                'medicine_details' => $this->textOrNull($_POST['medicine_details'] ?? null),

                'condition_high_blood_pressure' => isset($_POST['condition_high_blood_pressure']) ? 1 : 0,
                'condition_low_blood_pressure' => isset($_POST['condition_low_blood_pressure']) ? 1 : 0,
                'condition_asthma' => isset($_POST['condition_asthma']) ? 1 : 0,
                'condition_heart_disease' => isset($_POST['condition_heart_disease']) ? 1 : 0,
                'condition_diabetes' => isset($_POST['condition_diabetes']) ? 1 : 0,
                'condition_tuberculosis' => isset($_POST['condition_tuberculosis']) ? 1 : 0,
                'condition_thyroid_problem' => isset($_POST['condition_thyroid_problem']) ? 1 : 0,
                'condition_bleeding_problems' => isset($_POST['condition_bleeding_problems']) ? 1 : 0,
                'condition_hiv_aids' => isset($_POST['condition_hiv_aids']) ? 1 : 0,
                'condition_hepatitis' => isset($_POST['condition_hepatitis']) ? 1 : 0,
                'condition_others' => isset($_POST['condition_others']) ? 1 : 0,

                'allergy_local_anesthesia' => $this->yesNoOrNull($_POST['allergy_local_anesthesia'] ?? null),
                'allergy_antibiotics' => $this->yesNoOrNull($_POST['allergy_antibiotics'] ?? null),
                'allergy_pain_killer' => $this->yesNoOrNull($_POST['allergy_pain_killer'] ?? null),
                'allergy_others' => $this->yesNoOrNull($_POST['allergy_others'] ?? null),

                'hospitalized' => $this->yesNoOrNull($_POST['hospitalized'] ?? null),
                'hospitalization_when' => $this->textOrNull($_POST['hospitalization_when'] ?? null),
                'hospitalization_why' => $this->textOrNull($_POST['hospitalization_why'] ?? null),
                'updated_by' => (int) ($staff['user_id'] ?? 0),
            ];

            $existingId = $this->findExistingMedicalHistoryId($patientId);

            if ($existingId > 0) {
                $data['medical_history_id'] = $existingId;

                $stmt = $this->db->prepare("
                    UPDATE patient_medical_histories
                    SET under_physician_care = :under_physician_care,
                        physician_care_details = :physician_care_details,
                        is_pregnant = :is_pregnant,
                        taking_medicine = :taking_medicine,
                        medicine_details = :medicine_details,

                        condition_high_blood_pressure = :condition_high_blood_pressure,
                        condition_low_blood_pressure = :condition_low_blood_pressure,
                        condition_asthma = :condition_asthma,
                        condition_heart_disease = :condition_heart_disease,
                        condition_diabetes = :condition_diabetes,
                        condition_tuberculosis = :condition_tuberculosis,
                        condition_thyroid_problem = :condition_thyroid_problem,
                        condition_bleeding_problems = :condition_bleeding_problems,
                        condition_hiv_aids = :condition_hiv_aids,
                        condition_hepatitis = :condition_hepatitis,
                        condition_others = :condition_others,

                        allergy_local_anesthesia = :allergy_local_anesthesia,
                        allergy_antibiotics = :allergy_antibiotics,
                        allergy_pain_killer = :allergy_pain_killer,
                        allergy_others = :allergy_others,

                        hospitalized = :hospitalized,
                        hospitalization_when = :hospitalization_when,
                        hospitalization_why = :hospitalization_why,
                        updated_by = :updated_by
                    WHERE medical_history_id = :medical_history_id
                ");

                $stmt->execute($this->prefixParams($data));
            } else {
                $data['patient_id'] = $patientId;

                $stmt = $this->db->prepare("
                    INSERT INTO patient_medical_histories (
                        patient_id,
                        under_physician_care,
                        physician_care_details,
                        is_pregnant,
                        taking_medicine,
                        medicine_details,

                        condition_high_blood_pressure,
                        condition_low_blood_pressure,
                        condition_asthma,
                        condition_heart_disease,
                        condition_diabetes,
                        condition_tuberculosis,
                        condition_thyroid_problem,
                        condition_bleeding_problems,
                        condition_hiv_aids,
                        condition_hepatitis,
                        condition_others,

                        allergy_local_anesthesia,
                        allergy_antibiotics,
                        allergy_pain_killer,
                        allergy_others,

                        hospitalized,
                        hospitalization_when,
                        hospitalization_why,
                        updated_by
                    ) VALUES (
                        :patient_id,
                        :under_physician_care,
                        :physician_care_details,
                        :is_pregnant,
                        :taking_medicine,
                        :medicine_details,

                        :condition_high_blood_pressure,
                        :condition_low_blood_pressure,
                        :condition_asthma,
                        :condition_heart_disease,
                        :condition_diabetes,
                        :condition_tuberculosis,
                        :condition_thyroid_problem,
                        :condition_bleeding_problems,
                        :condition_hiv_aids,
                        :condition_hepatitis,
                        :condition_others,

                        :allergy_local_anesthesia,
                        :allergy_antibiotics,
                        :allergy_pain_killer,
                        :allergy_others,

                        :hospitalized,
                        :hospitalization_when,
                        :hospitalization_why,
                        :updated_by
                    )
                ");

                $stmt->execute($this->prefixParams($data));
            }
            $this->patientAccess->assertCanViewPatient(Auth::user(), $patientId);

$this->privacyAudit->log(
    (int) (Auth::user()['user_id'] ?? 0),
    'patient_medical_histories',
    'update',
    'patient',
    $patientId,
    'Updated patient medical history.'
);

            Session::set('flash_success', 'Medical history saved successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: ' . $this->url('/staff/patients/show?id=' . $patientId));
        exit;
    }

    public function convertFromAppointment(): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $staff = Auth::user();
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

        try {
            $patientId = $this->conversion->convertCompletedAppointment(
                $appointmentId,
                (int) ($staff['user_id'] ?? 0)
            );

            Session::set('flash_success', 'Guest converted to patient successfully.');
            header('Location: ' . $this->url('/staff/patients/show?id=' . $patientId));
            exit;
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
            header('Location: ' . $this->url('/staff/appointments/show?id=' . $appointmentId));
            exit;
        }
    }

    

    private function validatedPatientInput(array $input): array
    {
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $middleName = trim((string) ($input['middle_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $contactNumber = trim((string) ($input['contact_number'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $birthDate = $this->dateOrNull($input['birth_date'] ?? null);

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('First name and last name are required.');
        }

        if ($contactNumber === '') {
            throw new RuntimeException('Contact number is required.');
        }

        if (!$this->isValidPhilippineMobile($contactNumber)) {
            throw new RuntimeException('Invalid contact number. Use 09XXXXXXXXX, 639XXXXXXXXX, or +639XXXXXXXXX.');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email address.');
        }

        if ($birthDate !== null && strtotime($birthDate) > time()) {
            throw new RuntimeException('Birth date cannot be in the future.');
        }

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName !== '' ? $middleName : null,
            'last_name' => $lastName,
            'sex' => $this->textOrNull($input['sex'] ?? null),
            'birth_date' => $birthDate,
            'civil_status' => $this->textOrNull($input['civil_status'] ?? null),
            'address' => $this->textOrNull($input['address'] ?? null),
            'occupation' => $this->textOrNull($input['occupation'] ?? null),
            'contact_number' => $contactNumber,
            'email' => $email !== '' ? $email : null,
            'emergency_contact_name' => $this->textOrNull($input['emergency_contact_name'] ?? null),
            'emergency_contact_number' => $this->textOrNull($input['emergency_contact_number'] ?? null),
            'notes' => $this->textOrNull($input['notes'] ?? null),
            'profile_status' => 'active',
        ];
    }


    private function saveInitialMedicalHistory(int $patientId, array $input, int $staffUserId): void
{
    if ($patientId <= 0 || !$this->hasAnyMedicalHistoryInput($input)) {
        return;
    }

    $allergies = [];

    if (isset($input['allergy_local_anesthesia'])) {
        $allergies[] = 'Local anesthesia';
    }

    if (isset($input['allergy_antibiotics'])) {
        $allergies[] = 'Antibiotics';
    }

    if (isset($input['allergy_pain_killer'])) {
        $allergies[] = 'Pain killer';
    }

    $otherAllergy = $this->textOrNull($input['allergy_others_details'] ?? null);

    if ($otherAllergy !== null) {
        $allergies[] = $otherAllergy;
    }

    $data = [
        'patient_id' => $patientId,

        'under_physician_care' => $this->yesNoOrNull($input['under_physician_care'] ?? null),
        'physician_name' => $this->textOrNull($input['physician_name'] ?? null),
        'physician_contact' => $this->textOrNull($input['physician_contact'] ?? null),
        'physician_care_details' => $this->textOrNull($input['physician_care_details'] ?? null),

        'is_pregnant' => $this->yesNoOrNull($input['is_pregnant'] ?? null),
        'pregnancy_status' => $this->yesNoOrNull($input['is_pregnant'] ?? null),

        'taking_medicine' => $this->yesNoOrNull($input['taking_medicine'] ?? null),
        'medicine_details' => $this->textOrNull($input['medicine_details'] ?? null),
        'medications' => $this->textOrNull($input['medicine_details'] ?? null),

        'blood_pressure' => $this->textOrNull($input['blood_pressure'] ?? null),

        'condition_high_blood_pressure' => isset($input['condition_high_blood_pressure']) ? 1 : 0,
        'condition_low_blood_pressure' => isset($input['condition_low_blood_pressure']) ? 1 : 0,
        'condition_asthma' => isset($input['condition_asthma']) ? 1 : 0,
        'condition_heart_disease' => isset($input['condition_heart_disease']) ? 1 : 0,
        'condition_diabetes' => isset($input['condition_diabetes']) ? 1 : 0,
        'condition_tuberculosis' => isset($input['condition_tuberculosis']) ? 1 : 0,
        'condition_thyroid_problem' => isset($input['condition_thyroid_problem']) ? 1 : 0,
        'condition_bleeding_problems' => isset($input['condition_bleeding_problems']) ? 1 : 0,
        'condition_hiv_aids' => isset($input['condition_hiv_aids']) ? 1 : 0,
        'condition_hepatitis' => isset($input['condition_hepatitis']) ? 1 : 0,
        'condition_others' => isset($input['condition_others']) || trim((string) ($input['condition_others_details'] ?? '')) !== '' ? 1 : 0,
        'condition_others_details' => $this->textOrNull($input['condition_others_details'] ?? null),

        'diabetes' => isset($input['condition_diabetes']) ? 1 : 0,
        'bleeding_disorder' => isset($input['condition_bleeding_problems']) ? 1 : 0,

        'allergy_local_anesthesia' => isset($input['allergy_local_anesthesia']) ? 'yes' : null,
        'allergy_antibiotics' => isset($input['allergy_antibiotics']) ? 'yes' : null,
        'allergy_pain_killer' => isset($input['allergy_pain_killer']) ? 'yes' : null,
        'allergy_others' => isset($input['allergy_others']) || $otherAllergy !== null ? 'yes' : null,
        'allergy_others_details' => $otherAllergy,
        'allergies' => !empty($allergies) ? implode(', ', $allergies) : null,

        'hospitalized' => $this->yesNoOrNull($input['hospitalized'] ?? null),
        'hospitalization_when' => $this->textOrNull($input['hospitalization_when'] ?? null),
        'hospitalization_why' => $this->textOrNull($input['hospitalization_why'] ?? null),

        'notes' => $this->textOrNull($input['medical_notes'] ?? null),
        'updated_by' => $staffUserId,
    ];

    $this->upsertPatientHistory('patient_medical_histories', 'medical_history_id', $data);
}

private function saveInitialDentalHistory(int $patientId, array $input, int $staffUserId): void
{
    if ($patientId <= 0 || !$this->hasAnyDentalHistoryInput($input)) {
        return;
    }

    $data = [
        'patient_id' => $patientId,
        'previous_dentist' => $this->textOrNull($input['previous_dentist'] ?? null),
        'last_dental_visit' => $this->dateOrNull($input['last_dental_visit'] ?? null),
        'last_dental_visit_reason' => $this->textOrNull($input['last_dental_visit_reason'] ?? null),
        'worn_denture' => $this->yesNoOrNull($input['worn_denture'] ?? null),
        'gums_bleed' => $this->yesNoOrNull($input['gums_bleed'] ?? null),
        'bad_breath' => $this->yesNoOrNull($input['bad_breath'] ?? null),
        'loose_teeth' => $this->yesNoOrNull($input['loose_teeth'] ?? null),
        'sensitive_teeth' => $this->yesNoOrNull($input['sensitive_teeth'] ?? null),
        'clicking_jaw' => $this->yesNoOrNull($input['clicking_jaw'] ?? null),
        'notes' => $this->textOrNull($input['dental_notes'] ?? null),
        'updated_by' => $staffUserId,
    ];

    $this->upsertPatientHistory('patient_dental_histories', 'dental_history_id', $data);
}

private function hasAnyMedicalHistoryInput(array $input): bool
{
    $keys = [
        'under_physician_care',
        'physician_name',
        'physician_contact',
        'physician_care_details',
        'is_pregnant',
        'taking_medicine',
        'medicine_details',
        'blood_pressure',
        'condition_high_blood_pressure',
        'condition_low_blood_pressure',
        'condition_asthma',
        'condition_heart_disease',
        'condition_diabetes',
        'condition_tuberculosis',
        'condition_thyroid_problem',
        'condition_bleeding_problems',
        'condition_hiv_aids',
        'condition_hepatitis',
        'condition_others',
        'condition_others_details',
        'allergy_local_anesthesia',
        'allergy_antibiotics',
        'allergy_pain_killer',
        'allergy_others',
        'allergy_others_details',
        'hospitalized',
        'hospitalization_when',
        'hospitalization_why',
        'medical_notes',
    ];

    foreach ($keys as $key) {
        if (isset($input[$key]) && trim((string) $input[$key]) !== '') {
            return true;
        }
    }

    return false;
}

private function hasAnyDentalHistoryInput(array $input): bool
{
    $keys = [
        'previous_dentist',
        'last_dental_visit',
        'last_dental_visit_reason',
        'worn_denture',
        'gums_bleed',
        'bad_breath',
        'loose_teeth',
        'sensitive_teeth',
        'clicking_jaw',
        'dental_notes',
    ];

    foreach ($keys as $key) {
        if (isset($input[$key]) && trim((string) $input[$key]) !== '') {
            return true;
        }
    }

    return false;
}

private function upsertPatientHistory(string $table, string $primaryKey, array $data): void
{
    $allowedTables = [
        'patient_medical_histories',
        'patient_dental_histories',
    ];

    if (!in_array($table, $allowedTables, true)) {
        throw new RuntimeException('Invalid history table.');
    }

    $columns = $this->getTableColumns($table);

    if (!isset($columns['patient_id'])) {
        return;
    }

    $filtered = [];

    foreach ($data as $column => $value) {
        if (isset($columns[$column])) {
            $filtered[$column] = $value;
        }
    }

    if (count($filtered) <= 1) {
        return;
    }

    $stmt = $this->db->prepare("
        SELECT `$primaryKey`
        FROM `$table`
        WHERE patient_id = :patient_id
        LIMIT 1
    ");

    $stmt->execute([
        ':patient_id' => (int) $filtered['patient_id'],
    ]);

    $existingId = (int) $stmt->fetchColumn();

    if ($existingId > 0) {
        $sets = [];
        $params = [
            ':history_id' => $existingId,
        ];

        foreach ($filtered as $column => $value) {
            if (in_array($column, ['patient_id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            $sets[] = "`$column` = :$column";
            $params[':' . $column] = $value;
        }

        if (isset($columns['updated_at'])) {
            $sets[] = "`updated_at` = NOW()";
        }

        if (empty($sets)) {
            return;
        }

        $sql = "
            UPDATE `$table`
            SET " . implode(', ', $sets) . "
            WHERE `$primaryKey` = :history_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return;
    }

    $insertColumns = array_keys($filtered);
    $insertValues = array_map(static fn ($column) => ':' . $column, $insertColumns);
    $params = [];

    foreach ($filtered as $column => $value) {
        $params[':' . $column] = $value;
    }

    if (isset($columns['created_at']) && !in_array('created_at', $insertColumns, true)) {
        $insertColumns[] = 'created_at';
        $insertValues[] = 'NOW()';
    }

    if (isset($columns['updated_at']) && !in_array('updated_at', $insertColumns, true)) {
        $insertColumns[] = 'updated_at';
        $insertValues[] = 'NOW()';
    }

    $quotedColumns = array_map(static fn ($column) => "`$column`", $insertColumns);

    $sql = "
        INSERT INTO `$table` (" . implode(', ', $quotedColumns) . ")
        VALUES (" . implode(', ', $insertValues) . ")
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
}

private function getTableColumns(string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $allowedTables = [
        'patient_medical_histories',
        'patient_dental_histories',
    ];

    if (!in_array($table, $allowedTables, true)) {
        throw new RuntimeException('Invalid table.');
    }

    $stmt = $this->db->query("SHOW COLUMNS FROM `$table`");
    $columns = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        if (!empty($column['Field'])) {
            $columns[(string) $column['Field']] = true;
        }
    }

    $cache[$table] = $columns;

    return $columns;
}

    private function findExistingDentalHistoryId(int $patientId): int
    {
        $stmt = $this->db->prepare("
            SELECT dental_history_id
            FROM patient_dental_histories
            WHERE patient_id = :patient_id
            LIMIT 1
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function findExistingMedicalHistoryId(int $patientId): int
    {
        $stmt = $this->db->prepare("
            SELECT medical_history_id
            FROM patient_medical_histories
            WHERE patient_id = :patient_id
            LIMIT 1
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function isValidPhilippineMobile(string $number): bool
    {
        return preg_match('/^(09\d{9}|639\d{9}|\+639\d{9})$/', $number) === 1;
    }

    private function yesNoOrNull($value): ?string
    {
        $value = strtolower(trim((string) $value));

        if (in_array($value, ['yes', 'no'], true)) {
            return $value;
        }

        return null;
    }

    private function dateOrNull($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);
        $errors = DateTime::getLastErrors();

        if (
            !$date ||
            $date->format('Y-m-d') !== $value ||
            ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return null;
        }

        return $value;
    }

    private function textOrNull($value, int $maxLength = 2000): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function requiredText($value, string $message): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new RuntimeException($message);
        }

        return mb_substr($value, 0, 255);
    }

    private function allowedOrNull($value, array $allowed): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach ($allowed as $allowedValue) {
            if (strcasecmp($value, $allowedValue) === 0) {
                return ucfirst(strtolower($allowedValue));
            }
        }

        return null;
    }

    private function prefixParams(array $data): array
    {
        $params = [];

        foreach ($data as $key => $value) {
            $params[':' . $key] = $value;
        }

        return $params;
    }


    private function getPatientOdontogramEntries(int $patientId): array
{
    if ($patientId <= 0) {
        return [];
    }

    if (
        !$this->tableExists('examinations') ||
        !$this->tableExists('odontogram_entries')
    ) {
        return [];
    }

    try {
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
                e.examination_date,
                e.intraoral_exam_notes,
                e.clinical_findings,
                e.diagnosis,
                e.treatment_plan,
                e.recommendations,
                e.notes AS exam_notes,

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
                    NULLIF(TRIM(e.intraoral_exam_notes), ''),
                    NULLIF(TRIM(e.clinical_findings), ''),
                    ''
                ) AS notes,

                du.first_name AS dentist_first_name,
                du.middle_name AS dentist_middle_name,
                du.last_name AS dentist_last_name
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
            LEFT JOIN dentists d ON d.dentist_id = e.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            WHERE e.patient_id = :patient_id
            ORDER BY
                oe.created_at DESC,
                oe.odontogram_id DESC
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

private function getLatestIntraoralExam(int $patientId): array
{
    if ($patientId <= 0 || !$this->tableExists('examinations')) {
        return [];
    }

    try {
        $stmt = $this->db->prepare("
            SELECT
                e.*,
                du.first_name AS dentist_first_name,
                du.middle_name AS dentist_middle_name,
                du.last_name AS dentist_last_name
            FROM examinations e
            LEFT JOIN dentists d ON d.dentist_id = e.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            WHERE e.patient_id = :patient_id
            ORDER BY
                COALESCE(e.updated_at, e.created_at, e.examination_date) DESC,
                e.examination_id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    } catch (Throwable $e) {
        return [];
    }
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

private function tableExists(string $tableName): bool
{
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

        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

    private function url(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }
}