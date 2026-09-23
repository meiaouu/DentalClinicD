<?php

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AuditLogRepository;
use App\Services\PatientAccountProvisioningService;
use App\Repositories\UserRepository;
use PDO;
use RuntimeException;
use Throwable;

class PatientVerificationController
{
    private PDO $db;
    private AuditLogRepository $audit;
    private PatientAccountProvisioningService $accounts;
    private UserRepository $users;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->audit = new AuditLogRepository();
        $this->accounts = new PatientAccountProvisioningService();
        $this->users = new UserRepository();
    }

    public function index(): void
    {
        Auth::requireRole('staff');
        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $patients = $this->findPatients($keyword, $status);

        View::render('staff.patient-verification.index', [
            'patients' => $patients,
            'keyword' => $keyword,
            'status' => $status,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);
        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function show(): void
    {
        Auth::requireRole('staff');
        $patient = $this->findPatient((int) ($_GET['id'] ?? 0));
        if (!$patient) {
            http_response_code(404);
            exit('Patient not found.');
        }

        View::render('staff.patient-verification.show', [
            'patient' => $patient,
            'medicalHistory' => $this->findHistory('patient_medical_histories', 'medical_history_id', (int) $patient['patient_id']),
            'dentalHistory' => $this->findHistory('patient_dental_histories', 'dental_history_id', (int) $patient['patient_id']),
            'possibleUser' => empty($patient['linked_user_id']) ? $this->findPossibleUser($patient) : null,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);
        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function updateStatus(): void
    {
        Auth::requireRole('staff');
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $status = strtolower(trim((string) ($_POST['verification_status'] ?? '')));
        $allowed = ['approved', 'rejected', 'requires_update'];
        $patient = $this->findPatient($patientId);

        if (!$patient || !in_array($status, $allowed, true)) {
            Session::set('flash_error', 'Invalid patient review request.');
            $this->redirect($patientId);
        }

        $staff = Auth::user() ?? [];
        $notes = trim((string) ($_POST['verification_notes'] ?? ''));
        if ($status !== 'approved' && $notes === '') {
            Session::set('flash_error', 'Please provide review notes for this decision.');
            $this->redirect($patientId);
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("UPDATE patients SET verification_status = :status, verification_reviewed_by = :reviewed_by, verification_reviewed_at = NOW(), verification_notes = :notes, profile_status = CASE WHEN :status = 'approved' THEN 'active' ELSE profile_status END WHERE patient_id = :patient_id");
            $stmt->execute([
                ':status' => $status,
                ':reviewed_by' => (int) ($staff['user_id'] ?? 0),
                ':notes' => $notes !== '' ? $notes : null,
                ':patient_id' => $patientId,
            ]);

            $this->audit->create([
                'user_id' => (int) ($staff['user_id'] ?? 0),
                'module_name' => 'patient_verification',
                'action_name' => $status,
                'record_type' => 'patient',
                'record_id' => $patientId,
                'description' => 'Staff changed patient verification status to ' . $status . '.',
            ]);
            $this->db->commit();
            Session::set('flash_success', 'Patient verification status updated.');
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[PatientVerificationController::updateStatus] ' . $e->getMessage());
            Session::set('flash_error', 'Unable to update patient status.');
        }

        $this->redirect($patientId);
    }

    public function invite(): void
    {
        Auth::requireRole('staff');
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $patient = $this->findPatient($patientId);
        if (!$patient) {
            Session::set('flash_error', 'Patient not found.');
            header('Location: /DentalClinic/public/staff/patient-verification');
            exit;
        }

        try {
            $result = $this->accounts->createPendingAccountForPatient($patient);
            Session::set($result['sent'] ? 'flash_success' : 'flash_error', $result['message']);
        } catch (Throwable $e) {
            error_log('[PatientVerificationController::invite] ' . $e->getMessage());
            Session::set('flash_error', $e->getMessage());
        }

        $this->redirect($patientId);
    }

    public function connectUser(): void
    {
        Auth::requireRole('staff');
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);
        $patient = $this->findPatient($patientId);

        if (!$patient || $userId <= 0 || !empty($patient['linked_user_id'])) {
            Session::set('flash_error', 'This patient is already connected or the connection request is invalid.');
            $this->redirect($patientId);
        }

        $user = $this->users->findById($userId);
        if (!$user) {
            Session::set('flash_error', 'User account not found.');
            $this->redirect($patientId);
        }

        $existingLink = $this->findPatientByUserId($userId);
        if ($existingLink) {
            Session::set('flash_error', 'That user account is already connected to another patient record.');
            $this->redirect($patientId);
        }

        try {
            $staff = Auth::user() ?? [];
            $stmt = $this->db->prepare('UPDATE patients SET user_id = :user_id WHERE patient_id = :patient_id AND user_id IS NULL');
            $stmt->execute([':user_id' => $userId, ':patient_id' => $patientId]);

            $this->audit->create([
                'user_id' => (int) ($staff['user_id'] ?? 0),
                'module_name' => 'patient_verification',
                'action_name' => 'connect_user',
                'record_type' => 'patient',
                'record_id' => $patientId,
                'description' => 'Staff explicitly connected user #' . $userId . ' to patient record.',
            ]);
            Session::set('flash_success', 'Existing user account connected without changing patient history.');
        } catch (Throwable $e) {
            error_log('[PatientVerificationController::connectUser] ' . $e->getMessage());
            Session::set('flash_error', 'Unable to connect the user account.');
        }

        $this->redirect($patientId);
    }

    private function findPatients(string $keyword, string $status): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'p.verification_status = :status';
            $params[':status'] = $status;
        }
        if ($keyword !== '') {
            $where[] = '(p.first_name LIKE :keyword OR p.last_name LIKE :keyword OR p.email LIKE :keyword OR p.contact_number LIKE :keyword OR p.patient_code LIKE :keyword)';
            $params[':keyword'] = '%' . $keyword . '%';
        }

        $stmt = $this->db->prepare("SELECT p.*, u.user_id AS linked_user_id, u.email AS user_email FROM patients p LEFT JOIN users u ON u.user_id = p.user_id WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(p.verification_status, 'submitted', 'requires_update', 'incomplete', 'approved', 'rejected'), p.updated_at DESC LIMIT 200");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function findPatient(int $patientId): ?array
    {
        if ($patientId <= 0) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT p.*, u.user_id AS linked_user_id, u.email AS user_email FROM patients p LEFT JOIN users u ON u.user_id = p.user_id WHERE p.patient_id = :patient_id LIMIT 1');
        $stmt->execute([':patient_id' => $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function findPatientByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT patient_id FROM patients WHERE user_id = :user_id LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function findPossibleUser(array $patient): ?array
    {
        $email = strtolower(trim((string) ($patient['email'] ?? '')));
        if ($email !== '') {
            $user = $this->users->findByEmail($email);
            if ($user) {
                return $user;
            }
        }

        $phone = trim((string) ($patient['contact_number'] ?? ''));
        return $phone !== '' ? $this->users->findByContactNumber($phone) : null;
    }

    private function findHistory(string $table, string $idColumn, int $patientId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `{$table}` WHERE patient_id = :patient_id ORDER BY `{$idColumn}` DESC LIMIT 1");
        $stmt->execute([':patient_id' => $patientId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function redirect(int $patientId): void
    {
        header('Location: /DentalClinic/public/staff/patient-verification/show?id=' . $patientId);
        exit;
    }
}
