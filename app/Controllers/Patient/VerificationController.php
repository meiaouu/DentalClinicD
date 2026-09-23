<?php

namespace App\Controllers\Patient;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Repositories\PatientRepository;
use App\Repositories\AuditLogRepository;
use RuntimeException;
use Throwable;

class VerificationController
{
    private PatientRepository $patients;
    private AuditLogRepository $audit;
    private \PDO $db;

    public function __construct()
    {
        $this->patients = new PatientRepository();
        $this->audit = new AuditLogRepository();
        $this->db = Database::getConnection();
    }

    public function medicalHistory(): void
    {
        Auth::requireRole('patient');
        $patient = $this->patientForCurrentUser();

        View::render('patient.medical-history', [
            'authUser' => Auth::user(),
            'patient' => $patient,
            'medicalHistory' => $this->patients->findMedicalHistory((int) $patient['patient_id']) ?: [],
            'dentalHistory' => $this->patients->findDentalHistory((int) $patient['patient_id']) ?: [],
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function saveMedicalHistory(): void
    {
        Auth::requireRole('patient');
        $this->verifyCsrf();
        $user = Auth::user() ?? [];

        try {
            $patient = $this->patientForCurrentUser();
            $patientId = (int) $patient['patient_id'];

            $this->db->beginTransaction();
            $this->patients->updateProfile($patientId, [
                'first_name' => $this->requiredText($_POST['first_name'] ?? '', 'First name is required.'),
                'middle_name' => $this->nullableText($_POST['middle_name'] ?? null),
                'last_name' => $this->requiredText($_POST['last_name'] ?? '', 'Last name is required.'),
                'birth_date' => $this->dateOrNull($_POST['birth_date'] ?? null),
                'sex' => $this->nullableText($_POST['sex'] ?? null),
                'address' => $this->nullableText($_POST['address'] ?? null),
                'contact_number' => $this->nullableText($_POST['contact_number'] ?? null),
                'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
                'emergency_contact_name' => $this->nullableText($_POST['emergency_contact_name'] ?? null),
                'emergency_contact_number' => $this->nullableText($_POST['emergency_contact_number'] ?? null),
            ]);

            $this->patients->saveMedicalHistory($patientId, [
                'under_physician_care' => $this->yesNo($_POST['under_physician_care'] ?? null),
                'physician_care_details' => $this->nullableText($_POST['physician_care_details'] ?? null),
                'taking_medicine' => $this->yesNo($_POST['taking_medicine'] ?? null),
                'medicine_details' => $this->nullableText($_POST['medicine_details'] ?? null),
                'allergy_others' => $this->yesNo($_POST['allergy_others'] ?? null),
                'hospitalized' => $this->yesNo($_POST['hospitalized'] ?? null),
                'hospitalization_when' => $this->nullableText($_POST['hospitalization_when'] ?? null),
                'hospitalization_why' => $this->nullableText($_POST['hospitalization_why'] ?? null),
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
                'updated_by' => (int) $user['user_id'],
            ]);

            $this->patients->saveDentalHistory($patientId, [
                'previous_dentist' => $this->nullableText($_POST['previous_dentist'] ?? null),
                'last_dental_visit' => $this->dateOrNull($_POST['last_dental_visit'] ?? null),
                'last_dental_visit_reason' => $this->nullableText($_POST['last_dental_visit_reason'] ?? null),
                'worn_denture' => $this->yesNo($_POST['worn_denture'] ?? null),
                'gums_bleed' => $this->yesNo($_POST['gums_bleed'] ?? null),
                'bad_breath' => $this->yesNo($_POST['bad_breath'] ?? null),
                'loose_teeth' => $this->yesNo($_POST['loose_teeth'] ?? null),
                'sensitive_teeth' => $this->yesNo($_POST['sensitive_teeth'] ?? null),
                'clicking_jaw' => $this->yesNo($_POST['clicking_jaw'] ?? null),
                'notes' => $this->nullableText($_POST['dental_notes'] ?? null),
                'updated_by' => (int) $user['user_id'],
            ]);

            $stmt = $this->db->prepare("UPDATE patients SET verification_status = 'submitted', verification_submitted_at = NOW(), verification_notes = NULL WHERE patient_id = :patient_id");
            $stmt->execute([':patient_id' => $patientId]);

            $this->audit->create([
                'user_id' => (int) $user['user_id'],
                'module_name' => 'patient_verification',
                'action_name' => 'submit',
                'record_type' => 'patient',
                'record_id' => $patientId,
                'description' => 'Patient submitted medical and dental history for review.',
            ]);

            $this->db->commit();
            Session::set('flash_success', 'Your information was submitted for clinic review.');
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[PatientVerificationController::saveMedicalHistory] ' . $e->getMessage());
            Session::set('flash_error', $e instanceof RuntimeException ? $e->getMessage() : 'Unable to submit your information right now.');
        }

        header('Location: /DentalClinic/public/patient/medical-history');
        exit;
    }

    private function patientForCurrentUser(): array
    {
        $user = Auth::user() ?? [];
        $patient = $this->patients->findByUserId((int) ($user['user_id'] ?? 0));

        if (!$patient) {
            throw new RuntimeException('Your patient record is not linked yet. Please contact the clinic.');
        }

        return $patient;
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }

    private function requiredText(mixed $value, string $message): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new RuntimeException($message);
        }
        return mb_substr($value, 0, 255);
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, 5000);
    }

    private function yesNo(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['yes', 'no'], true) ? $value : null;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = date_create($value);
        if (!$date || $date->format('Y-m-d') !== $value || $value > date('Y-m-d')) {
            throw new RuntimeException('Please enter valid dates.');
        }
        return $value;
    }
}
