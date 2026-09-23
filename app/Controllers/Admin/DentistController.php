<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AdminDentistRepository;
use App\Repositories\AdminUserRepository;
use RuntimeException;
use Throwable;
use App\Core\Database;
use PDO;


class DentistController
{
    private AdminDentistRepository $dentists;
    private AdminUserRepository $users;

    public function __construct()
    {
        $this->dentists = new AdminDentistRepository();
        $this->users = new AdminUserRepository();
    }

    public function index(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);

        View::render('admin.dentists.index', [
            'dentists' => $this->dentists->all(),
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function create(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);

        View::render('admin.dentists.create', [
            'users' => $this->dentists->availableUsers(),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_error');
    }

    public function store(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);
        $this->verifyCsrf();

        try {
            $userId = (int) ($_POST['user_id'] ?? 0);

            if ($userId <= 0) {
                throw new RuntimeException('Please select a user account.');
            }

            $dentistId = $this->dentists->create([
                'user_id' => $userId,
                'dentist_code' => trim((string) ($_POST['dentist_code'] ?? '')),
                'license_number' => trim((string) ($_POST['license_number'] ?? '')),
                'specialization' => trim((string) ($_POST['specialization'] ?? '')),
                'consultation_fee' => (float) ($_POST['consultation_fee'] ?? 0),
                'bio' => trim((string) ($_POST['bio'] ?? '')),
                'is_owner' => isset($_POST['is_owner']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
            ]);

            $this->users->assignRoleByName($userId, 'dentist');
            $this->users->audit('dentists.create', 'dentists', $dentistId, 'Created dentist profile.');

            Session::set('flash_success', 'Dentist profile created successfully.');
            header('Location: /DentalClinic/public/admin/dentists');
            exit;
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
            header('Location: /DentalClinic/public/admin/dentists/create');
            exit;
        }
    }

    public function edit(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);

        $dentistId = (int) ($_GET['id'] ?? 0);

        if ($dentistId <= 0) {
            http_response_code(404);
            exit('Dentist not found.');
        }

        $dentist = $this->dentists->find($dentistId);

        if (!$dentist) {
            http_response_code(404);
            exit('Dentist not found.');
        }

        View::render('admin.dentists.edit', [
            'dentist' => $dentist,
            'users' => $this->dentists->availableUsers((int) ($dentist['user_id'] ?? 0)),
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function update(): void
{
    Auth::requireAnyRole(['owner', 'admin']);

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $dentistId = (int) ($_POST['dentist_id'] ?? 0);

    if ($dentistId <= 0) {
        Session::set('flash_error', 'Invalid dentist selected.');
        header('Location: /DentalClinic/public/admin/dentists');
        exit;
    }

    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $middleName = trim((string) ($_POST['middle_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $contactNumber = trim((string) ($_POST['contact_number'] ?? ''));

    $dentistCode = trim((string) ($_POST['dentist_code'] ?? ''));
    $licenseNumber = trim((string) ($_POST['license_number'] ?? ''));
    $specialization = trim((string) ($_POST['specialization'] ?? ''));
    $consultationFee = (float) ($_POST['consultation_fee'] ?? 0);
    $bio = trim((string) ($_POST['bio'] ?? ''));

    $isOwner = isset($_POST['is_owner']) && (string) $_POST['is_owner'] === '1' ? 1 : 0;
    $isActive = isset($_POST['is_active']) && (string) $_POST['is_active'] === '1' ? 1 : 0;

    $errors = [];

    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }

    if ($lastName === '') {
        $errors[] = 'Last name is required.';
    }

    if ($username === '') {
        $errors[] = 'Username is required.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($dentistCode === '') {
        $errors[] = 'Dentist code is required.';
    }

    if ($consultationFee < 0) {
        $errors[] = 'Consultation fee cannot be negative.';
    }

    if ($contactNumber !== '' && !preg_match('/^(09\d{9}|639\d{9}|\+639\d{9})$/', preg_replace('/[\s-]+/', '', $contactNumber))) {
        $errors[] = 'Please enter a valid Philippine mobile number.';
    }

    if (!empty($errors)) {
        Session::set('flash_error', implode(' ', $errors));
        header('Location: /DentalClinic/public/admin/dentists');
        exit;
    }

    $db = Database::getConnection();

    try {
        $db->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Get the real user_id from dentist_id
        |--------------------------------------------------------------------------
        | Do not trust the hidden user_id from the form. Always get it from database.
        */
        $stmt = $db->prepare("
            SELECT user_id
            FROM dentists
            WHERE dentist_id = :dentist_id
            LIMIT 1
        ");

        $stmt->execute([
            'dentist_id' => $dentistId,
        ]);

        $userId = (int) $stmt->fetchColumn();

        if ($userId <= 0) {
            throw new RuntimeException('Dentist not found.');
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate username/email
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE username = :username
              AND user_id <> :user_id
        ");

        $stmt->execute([
            'username' => $username,
            'user_id' => $userId,
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Username is already used by another user.');
        }

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE email = :email
              AND user_id <> :user_id
        ");

        $stmt->execute([
            'email' => $email,
            'user_id' => $userId,
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Email is already used by another user.');
        }

        /*
        |--------------------------------------------------------------------------
        | Update users table
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            UPDATE users
            SET
                first_name = :first_name,
                middle_name = :middle_name,
                last_name = :last_name,
                username = :username,
                email = :email,
                contact_number = :contact_number,
                is_active = :is_active,
                updated_at = NOW()
            WHERE user_id = :user_id
        ");

        $stmt->execute([
            'first_name' => $firstName,
            'middle_name' => $middleName !== '' ? $middleName : null,
            'last_name' => $lastName,
            'username' => $username,
            'email' => $email,
            'contact_number' => $contactNumber !== '' ? $contactNumber : null,
            'is_active' => $isActive,
            'user_id' => $userId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update dentists table
        |--------------------------------------------------------------------------
        */
        $stmt = $db->prepare("
            UPDATE dentists
            SET
                dentist_code = :dentist_code,
                license_number = :license_number,
                specialization = :specialization,
                consultation_fee = :consultation_fee,
                bio = :bio,
                is_owner = :is_owner,
                is_active = :is_active,
                updated_at = NOW()
            WHERE dentist_id = :dentist_id
        ");

        $stmt->execute([
            'dentist_code' => $dentistCode,
            'license_number' => $licenseNumber !== '' ? $licenseNumber : null,
            'specialization' => $specialization !== '' ? $specialization : null,
            'consultation_fee' => $consultationFee,
            'bio' => $bio !== '' ? $bio : null,
            'is_owner' => $isOwner,
            'is_active' => $isActive,
            'dentist_id' => $dentistId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Audit Trail
        |--------------------------------------------------------------------------
        | This keeps the action traceable.
        */
        try {
            $authUser = Auth::user();
            $actorId = (int) ($authUser['user_id'] ?? 0);

            $audit = $db->prepare("
                INSERT INTO audit_logs
                    (user_id, action_type, table_name, record_id, description, created_at)
                VALUES
                    (:user_id, :action_type, :table_name, :record_id, :description, NOW())
            ");

            $audit->execute([
                'user_id' => $actorId > 0 ? $actorId : null,
                'action_type' => 'update',
                'table_name' => 'dentists',
                'record_id' => $dentistId,
                'description' => 'Updated dentist profile: ' . $firstName . ' ' . $lastName,
            ]);
        } catch (Throwable $auditError) {
            error_log('[DentistController::update audit] ' . $auditError->getMessage());
        }

        $db->commit();

        Session::set('flash_success', 'Dentist updated successfully.');
        header('Location: /DentalClinic/public/admin/dentists');
        exit;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        error_log('[DentistController::update] ' . $e->getMessage());

        Session::set('flash_error', $e->getMessage());
        header('Location: /DentalClinic/public/admin/dentists');
        exit;
    }
}

    public function updateStatus(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);
        $this->verifyCsrf();

        $dentistId = (int) ($_POST['dentist_id'] ?? 0);
        $isActive = (int) ($_POST['is_active'] ?? 0) === 1;

        try {
            if ($dentistId <= 0) {
                throw new RuntimeException('Invalid dentist.');
            }

            $this->dentists->setActive($dentistId, $isActive);
            $this->users->audit('dentists.status', 'dentists', $dentistId, $isActive ? 'Activated dentist.' : 'Deactivated dentist.');

            Session::set('flash_success', 'Dentist status updated successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: /DentalClinic/public/admin/dentists');
        exit;
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}