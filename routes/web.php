<?php

use App\Core\Router;
use App\Core\View;
use App\Controllers\AuthController;
use App\Controllers\BookingController;
use App\Core\Auth;
use App\Controllers\Staff\AppointmentRequestController as StaffAppointmentRequestController;
use App\Controllers\Staff\AppointmentController as StaffAppointmentController;
use App\Controllers\Dentist\AvailabilityController as DentistAvailabilityController;
use App\Controllers\Staff\PatientController as StaffPatientController;
use App\Controllers\Patient\DashboardController as PatientDashboardController;
use App\Controllers\Patient\VerificationController as PatientVerificationController;
use App\Controllers\Staff\BillingController as StaffBillingController;
use App\Controllers\Staff\FollowUpController as StaffFollowUpController;
use App\Controllers\Staff\MessageController as StaffMessageController;
use App\Controllers\Staff\AttachmentController as StaffAttachmentController;
use App\Controllers\Staff\NotificationController as StaffNotificationController;
use App\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Controllers\AppointmentTrackingController;
use App\Controllers\Patient\AppointmentController as PatientAppointmentController;
use App\Controllers\HomeController;
use App\Controllers\Dentist\WorkspaceController as DentistWorkspaceController;
use App\Controllers\Dentist\AppointmentController as DentistAppointmentController;
use App\Controllers\Dentist\PatientController as DentistPatientController;
use App\Controllers\Dentist\NotificationController as DentistNotificationController;
use App\Controllers\Patient\DocumentController as PatientDocumentController;
use App\Controllers\Staff\BulkMessageController;
use App\Controllers\ChatWidgetController;
use App\Controllers\SettingsController;
use App\Controllers\Auth\PasswordSetupController;
use App\Controllers\Auth\ForgotPasswordController;
use App\Controllers\Auth\AccountRecoveryController;
use App\Controllers\PublicController;
use App\Controllers\PrivacyRequestController;
use App\Controllers\Patient\PrivacyRequestController as PatientPrivacyRequestController;
use App\Controllers\Staff\PrivacyRequestController as StaffPrivacyRequestController;
use App\Controllers\Staff\PatientVerificationController as StaffPatientVerificationController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Controllers\Admin\UserController as AdminUserController;
use App\Controllers\Admin\DentistController as AdminDentistController;
use App\Controllers\Admin\AuditTrailController as AdminAuditTrailController;
use App\Controllers\Admin\BackupController as AdminBackupController;
use App\Core\Database;



$router = new Router();

/*
|--------------------------------------------------------------------------
| Maintenance Mode Guard
|--------------------------------------------------------------------------
| Put this before ALL routes.
*/

function maintenance_setting_value(string $group, string $key, string $default = ''): string
{
    try {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT setting_value
            FROM system_settings
            WHERE setting_group = :setting_group
              AND setting_key = :setting_key
            LIMIT 1
        ");

        $stmt->execute([
            'setting_group' => $group,
            'setting_key' => $key,
        ]);

        $value = $stmt->fetchColumn();

        if ($value === false) {
            return $default;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function maintenance_setting_enabled(string $group, string $key, bool $default = false): bool
{
    $value = maintenance_setting_value($group, $key, $default ? '1' : '0');

    return in_array(
        strtolower(trim($value)),
        ['1', 'yes', 'true', 'on', 'enabled'],
        true
    );
}

function maintenance_current_path(): string
{
    $basePath = '/DentalClinic/public';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    if (str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath));
    }

    $path = '/' . trim($path, '/');

    if ($path === '/index.php') {
        return '/';
    }

    if (str_starts_with($path, '/index.php/')) {
        $path = substr($path, strlen('/index.php'));
        $path = '/' . trim($path, '/');
    }

    return $path === '' ? '/' : $path;
}

function maintenance_is_owner_or_admin(): bool
{
    try {
        return Auth::hasAnyRole(['owner', 'admin']);
    } catch (Throwable $e) {
        return false;
    }
}

function maintenance_render_page(): void
{
    $clinicName = maintenance_setting_value(
        'clinic_profile',
        'clinic_name',
        'Dental Clinic'
    );

    $message = maintenance_setting_value(
        'maintenance',
        'maintenance_message',
        'The system is currently under maintenance. Please check back later.'
    );

    http_response_code(503);
    header('Retry-After: 3600');
    header('Content-Type: text/html; charset=utf-8');

    echo '<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance Mode</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f7f8fa;
            color: #222;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px;
        }

        .maintenance-card {
            width: min(520px, 100%);
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 28px;
            box-shadow: 0 16px 46px rgba(15, 23, 42, .08);
            padding: 34px;
            text-align: center;
        }

        .maintenance-icon {
            width: 74px;
            height: 74px;
            margin: 0 auto 18px;
            border-radius: 24px;
            background: #e7f6f4;
            color: #0f766e;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .maintenance-title {
            margin: 0;
            font-size: 28px;
            line-height: 1.15;
            font-weight: 900;
            letter-spacing: -.04em;
        }

        .maintenance-clinic {
            margin-top: 8px;
            color: #0f766e;
            font-size: 14px;
            font-weight: 900;
        }

        .maintenance-message {
            margin: 16px 0 0;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.7;
        }

        .maintenance-actions {
            margin-top: 24px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .maintenance-btn {
            min-height: 42px;
            border-radius: 999px;
            padding: 0 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #222;
            color: #fff;
            font-size: 13px;
            font-weight: 900;
            text-decoration: none;
        }

        .maintenance-btn.secondary {
            background: #fff;
            color: #222;
            border: 1px solid #d1d5db;
        }
    </style>
</head>
<body>
    <main class="maintenance-card">
        <div class="maintenance-icon">
            <svg width="34" height="34" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M14.7 6.3L17.7 3.3L20.7 6.3L17.7 9.3Z"></path>
                <path d="M4 20L14 10"></path>
                <path d="M6 18L8 20"></path>
            </svg>
        </div>

        <h1 class="maintenance-title">We&rsquo;ll be back soon</h1>
        <div class="maintenance-clinic">' . htmlspecialchars($clinicName, ENT_QUOTES, 'UTF-8') . '</div>
        <p class="maintenance-message">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>

        <div class="maintenance-actions">
            <a href="/DentalClinic/public/index.php/login" class="maintenance-btn">Admin Login</a>
            <a href="javascript:location.reload()" class="maintenance-btn secondary">Refresh</a>
        </div>
    </main>
</body>
</html>';

    exit;
}

function maintenance_guard(): void
{
    $enabled = maintenance_setting_enabled('maintenance', 'maintenance_mode', false);

    if (!$enabled) {
        return;
    }

    $path = maintenance_current_path();

    if (preg_match('/\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2|ttf|map)$/i', $path)) {
        return;
    }

    if (
        $path === '/login' ||
        $path === '/logout' ||
        $path === '/setup-password' ||
        str_starts_with($path, '/login/') ||
        str_starts_with($path, '/index.php/login')
    ) {
        return;
    }

    if (
        str_starts_with($path, '/images') ||
        str_starts_with($path, '/uploads') ||
        str_starts_with($path, '/assets')
    ) {
        return;
    }

    $isOwnerOrAdmin = maintenance_is_owner_or_admin();

    if ($isOwnerOrAdmin) {
        return;
    }

    $wantsJson = str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_starts_with($path, '/chat/widget')
        || str_starts_with($path, '/booking/')
        || str_starts_with($path, '/book');

    if ($wantsJson) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'maintenance' => true,
            'message' => maintenance_setting_value(
                'maintenance',
                'maintenance_message',
                'The system is currently under maintenance. Please check back later.'
            ),
        ]);

        exit;
    }

    maintenance_render_page();
}

maintenance_guard();

$router->get('/', [HomeController::class, 'index']);
$router->post('/chat/widget/start', [ChatWidgetController::class, 'start']);
$router->get('/chat/widget/fetch', [ChatWidgetController::class, 'fetch']);
$router->post('/chat/widget/send', [ChatWidgetController::class, 'send']);


$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/setup-password', [PasswordSetupController::class, 'show']);
$router->post('/setup-password', [PasswordSetupController::class, 'submit']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);

$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/forgot-password', [ForgotPasswordController::class, 'show']);
$router->post('/forgot-password/lookup', [ForgotPasswordController::class, 'lookup']);
$router->post('/forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp']);
$router->get('/forgot-password/choose-method', [ForgotPasswordController::class, 'chooseMethod']);
$router->get('/forgot-password/verify-otp', [ForgotPasswordController::class, 'showVerifyOtp']);
$router->post('/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
$router->get('/forgot-password/reset', [ForgotPasswordController::class, 'showReset']);
$router->post('/forgot-password/reset', [ForgotPasswordController::class, 'reset']);
$router->post('/forgot-password/send-reset-link', [ForgotPasswordController::class, 'sendResetLink']);


$router->get('/account-recovery', [AccountRecoveryController::class, 'show']);
$router->post('/account-recovery', [AccountRecoveryController::class, 'store']);



$router->get('/privacy-notice', [PublicController::class, 'privacyNotice']);

$router->get('/privacy-request', [PrivacyRequestController::class, 'create']);
$router->post('/privacy-request', [PrivacyRequestController::class, 'store']);

$router->get('/patient/privacy-requests', [PatientPrivacyRequestController::class, 'index']);
$router->get('/patient/privacy-requests/create', [PatientPrivacyRequestController::class, 'create']);
$router->post('/patient/privacy-requests', [PatientPrivacyRequestController::class, 'store']);

$router->get('/staff/privacy-requests', [StaffPrivacyRequestController::class, 'index']);
$router->get('/staff/privacy-requests/show', [StaffPrivacyRequestController::class, 'show']);
$router->post('/staff/privacy-requests/update', [StaffPrivacyRequestController::class, 'update']);

$router->get('/patient/pending-review', [PatientDashboardController::class, 'pendingReview']);
$router->get('/patient/medical-history', [PatientVerificationController::class, 'medicalHistory']);
$router->post('/patient/medical-history', [PatientVerificationController::class, 'saveMedicalHistory']);







$router->get('/book', [BookingController::class, 'entry']);
$router->get('/book/guest', [BookingController::class, 'guestForm']);
$router->post('/book/review', [BookingController::class, 'review']);
$router->post('/book/store', [BookingController::class, 'store']);
$router->get('/book/success', [BookingController::class, 'success']);

$router->get('/book/contact-options', [BookingController::class, 'contactOptions']);
$router->post('/book/send-verification-otp', [BookingController::class, 'sendVerificationOtp']);
$router->get('/book/verify-contact', [BookingController::class, 'showVerifyContactOtp']);
$router->post('/book/verify-contact', [BookingController::class, 'verifyContactOtp']);

$router->get('/booking/service-meta', [BookingController::class, 'serviceMeta']);
$router->get('/booking/service-questions', [BookingController::class, 'serviceQuestions']);
$router->get('/booking/available-dentists', [BookingController::class, 'availableDentists']);
$router->get('/booking/available-slots', [BookingController::class, 'availableSlots']);
$router->get('/booking/calendar-availability', [BookingController::class, 'calendarAvailability']);



$router->get('/staff/appointment-requests', [StaffAppointmentRequestController::class, 'index']);
$router->get('/staff/appointment-requests/show', [StaffAppointmentRequestController::class, 'show']);
$router->post('/staff/appointment-requests/confirm', [StaffAppointmentRequestController::class, 'confirm']);
$router->post('/staff/appointment-requests/reject', [StaffAppointmentRequestController::class, 'reject']);
$router->post('/staff/appointment-requests/reschedule', [StaffAppointmentRequestController::class, 'reschedule']);


$router->get('/staff/appointments', [StaffAppointmentController::class, 'index']);
$router->get('/staff/appointments/create', [StaffAppointmentController::class, 'create']);
$router->post('/staff/appointments/store', [StaffAppointmentController::class, 'store']);
$router->get('/staff/appointments/show', [StaffAppointmentController::class, 'show']);
$router->get('/staff/appointments/available-slots', [StaffAppointmentController::class, 'availableSlots']);
$router->get('/staff/appointments/queue', [StaffAppointmentController::class, 'queue']);
$router->post('/staff/appointments/check-in', [App\Controllers\Staff\AppointmentController::class, 'checkIn']);
$router->post('/staff/appointments/in-progress', [App\Controllers\Staff\AppointmentController::class, 'inProgress']);
$router->post('/staff/appointments/complete', [App\Controllers\Staff\AppointmentController::class, 'complete']);
$router->post('/staff/appointments/no-show', [App\Controllers\Staff\AppointmentController::class, 'noShow']);
$router->post('/staff/appointments/cancel', [App\Controllers\Staff\AppointmentController::class, 'cancel']);
$router->get('/staff/appointment-requests/available-dates', [StaffAppointmentRequestController::class, 'availableDates']);
$router->get('/staff/appointment-requests/available-slots', [StaffAppointmentRequestController::class, 'availableSlots']);


$router->get('/staff/notifications', [StaffNotificationController::class, 'index']);
$router->get('/staff/notifications/latest', [StaffNotificationController::class, 'latestJson']);
$router->post('/staff/notifications/read', [StaffNotificationController::class, 'markRead']);
$router->post('/staff/notifications/read-all', [StaffNotificationController::class, 'markAllRead']);






// Dentist Dashboard
$router->get('/dentist/dashboard', [DentistWorkspaceController::class, 'dashboard']);

// Dentist Appointments
$router->get('/dentist/appointments', [DentistAppointmentController::class, 'index']);
$router->get('/dentist/appointments/show', [DentistAppointmentController::class, 'show']);

$router->post('/dentist/appointments/start-treatment', [DentistAppointmentController::class, 'startTreatment']);
$router->post('/dentist/appointments/complete-treatment', [DentistAppointmentController::class, 'completeTreatment']);
$router->get('/dentist/availability/weekly-schedule', [DentistAppointmentController::class, 'weeklySchedule']);

$router->post('/staff/appointments/start-procedure', [App\Controllers\Staff\AppointmentController::class, 'inProgress']);
$router->post('/staff/appointments/complete-procedure', [App\Controllers\Staff\AppointmentController::class, 'complete']);

$router->post('/dentist/appointments/start-procedure', [DentistAppointmentController::class, 'startTreatment']);
$router->post('/dentist/appointments/complete-procedure', [DentistAppointmentController::class, 'completeTreatment']);

// Dentist Patients
$router->get('/dentist/patients', [DentistPatientController::class, 'index']);
$router->get('/dentist/patients/show', [DentistPatientController::class, 'show']);
$router->post('/dentist/patients/update-profile', [DentistPatientController::class, 'updateProfile']);
$router->post('/dentist/patients/save-dental-history', [DentistPatientController::class, 'saveDentalHistory']);
$router->post('/dentist/patients/save-medical-history', [DentistPatientController::class, 'saveMedicalHistory']);
$router->post('/dentist/patients/start-treatment-record', [DentistPatientController::class, 'startTreatmentRecord']);
$router->post('/dentist/patients/save-treatment', [DentistPatientController::class, 'saveTreatment']);
$router->post('/dentist/patients/update-treatment', [DentistPatientController::class, 'updateTreatment']);
$router->post('/dentist/patients/save-odontogram', [DentistPatientController::class, 'saveOdontogram']);

// Dentist Other Pages
$router->get('/dentist/dental-records', [DentistWorkspaceController::class, 'dentalRecords']);
$router->get('/dentist/treatments', [DentistWorkspaceController::class, 'treatments']);
$router->get('/dentist/followups', [DentistWorkspaceController::class, 'followUps']);
$router->get('/dentist/messages', [DentistWorkspaceController::class, 'messages']);

// Dentist Availability
$router->get('/dentist/availability', [DentistAvailabilityController::class, 'index']);
$router->post('/dentist/availability/weekly', [DentistAvailabilityController::class, 'storeWeekly']);
$router->post('/dentist/availability/unavailable/store', [DentistAvailabilityController::class, 'storeUnavailableDate']);
$router->post('/dentist/availability/unavailable/delete', [DentistAvailabilityController::class, 'deleteUnavailableDate']);
$router->post('/dentist/availability/override/store', [DentistAvailabilityController::class, 'storeDateOverride']);
$router->post('/dentist/availability/override/delete', [DentistAvailabilityController::class, 'deleteDateOverride']);

// Dentist Notifications
$router->get('/dentist/notifications', [DentistNotificationController::class, 'index']);
$router->post('/dentist/notifications/mark-read', [DentistNotificationController::class, 'markRead']);
$router->post('/dentist/notifications/mark-one-read', [DentistNotificationController::class, 'markOneRead']);






//Documents Dentist

$router->get('/dentist/patients/document', [DentistPatientController::class, 'document']);
$router->post('/dentist/patients/upload-document', [DentistPatientController::class, 'uploadDocument']);
$router->post('/dentist/patients/delete-document', [DentistPatientController::class, 'deleteDocument']);








//Settings Dentist
// Settings
$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings/account', [SettingsController::class, 'updateAccount']);
$router->post('/settings/privacy', [SettingsController::class, 'updatePrivacy']);
$router->post('/settings/language', [SettingsController::class, 'updateLanguage']);
$router->post('/settings/appearance', [SettingsController::class, 'updateAppearance']);
$router->post('/settings/reset-appearance', [SettingsController::class, 'resetAppearance']);






$router->get('/staff/patients', [StaffPatientController::class, 'index']);
$router->get('/staff/patient-verification', [StaffPatientVerificationController::class, 'index']);
$router->get('/staff/patient-verification/show', [StaffPatientVerificationController::class, 'show']);
$router->post('/staff/patient-verification/status', [StaffPatientVerificationController::class, 'updateStatus']);
$router->post('/staff/patient-verification/invite', [StaffPatientVerificationController::class, 'invite']);
$router->post('/staff/patient-verification/connect-user', [StaffPatientVerificationController::class, 'connectUser']);
$router->get('/staff/patients/show', [StaffPatientController::class, 'show']);

$router->get('/staff/patients/create', [StaffPatientController::class, 'create']);
$router->post('/staff/patients/store', [StaffPatientController::class, 'store']);

$router->post('/staff/patients/update-record', [StaffPatientController::class, 'updateRecord']);
$router->post('/staff/patients/update-profile', [StaffPatientController::class, 'updateProfile']);
$router->post('/staff/patients/save-dental-history', [StaffPatientController::class, 'saveDentalHistory']);
$router->post('/staff/patients/save-medical-history', [StaffPatientController::class, 'saveMedicalHistory']);

$router->post('/staff/patients/convert-from-appointment', [StaffPatientController::class, 'convertFromAppointment']);


$router->get('/staff/billing', [StaffBillingController::class, 'index']);
$router->get('/staff/billing/create', [StaffBillingController::class, 'create']);
$router->post('/staff/billing/store', [StaffBillingController::class, 'store']);
$router->get('/staff/billing/show', [StaffBillingController::class, 'show']);
$router->post('/staff/billing/payment', [StaffBillingController::class, 'addPayment']);
$router->get('/staff/billing/receipt', [StaffBillingController::class, 'receipt']);

$router->post('/staff/billing/record-payment', [StaffBillingController::class, 'recordPayment']);

$router->get('/staff/billing', [StaffBillingController::class, 'index']);
$router->get('/staff/billing/show', [StaffBillingController::class, 'show']);
$router->post('/staff/billing/payment', [StaffBillingController::class, 'addPayment']);
$router->get('/staff/billing/receipt', [StaffBillingController::class, 'receipt']);
$router->post('/staff/billing/generate-from-appointment', [StaffBillingController::class, 'generateFromAppointment']);

$router->post('/staff/billing/record-payment', [StaffBillingController::class, 'addPayment']);




$router->get('/staff/followups', [StaffFollowUpController::class, 'index']);
$router->get('/staff/followups/show', [StaffFollowUpController::class, 'show']);
$router->post('/staff/followups/create-from-treatment', [StaffFollowUpController::class, 'createFromTreatment']);
$router->post('/staff/followups/update-status', [StaffFollowUpController::class, 'updateStatus']);

$router->get('/staff/messages', [StaffMessageController::class, 'index']);
$router->get('/staff/messages/show', [StaffMessageController::class, 'show']);
$router->post('/staff/messages/create', [StaffMessageController::class, 'create']);
$router->post('/staff/messages/reply', [StaffMessageController::class, 'reply']);
$router->post('/staff/messages/archive', [StaffMessageController::class, 'archive']);
$router->post('/staff/messages/unarchive', [StaffMessageController::class, 'unarchive']);

$router->get('/staff/attachments/show', [StaffAttachmentController::class, 'show']);
$router->post('/staff/messages/bot-reply', [StaffMessageController::class, 'botReply']);
$router->get('/staff/messages/bulk', [BulkMessageController::class, 'index']);
$router->get('/staff/messages/bulk/preview', [BulkMessageController::class, 'preview']);
$router->post('/staff/messages/bulk/send', [BulkMessageController::class, 'sendBulk']);



$router->get('/staff/attachments/show', [StaffAttachmentController::class, 'show']);
$router->post('/staff/attachments/upload', [StaffAttachmentController::class, 'upload']);
$router->get('/staff/notifications', [StaffNotificationController::class, 'index']);
$router->get('/staff/notifications/latest', [StaffNotificationController::class, 'latest']);
$router->post('/staff/notifications/read', [StaffNotificationController::class, 'read']);


$router->get('/track-request', [AppointmentTrackingController::class, 'form']);
$router->post('/track-request/search', [AppointmentTrackingController::class, 'search']);
$router->post('/track-request/cancel', [AppointmentTrackingController::class, 'cancel']);


$router->get('/patient/appointments', [PatientAppointmentController::class, 'index']);
$router->get('/patient/appointments/request', [PatientAppointmentController::class, 'showRequest']);
$router->get('/patient/appointments/show', [PatientAppointmentController::class, 'showAppointment']);
$router->post('/patient/appointments/request/cancel', [PatientAppointmentController::class, 'cancelRequest']);


$router->get('/booking/provinces', [BookingController::class, 'provinces']);
$router->get('/booking/cities', [BookingController::class, 'cities']);
$router->get('/booking/barangays', [BookingController::class, 'barangays']);



$router->get('/booking/address-suggestions', [BookingController::class, 'addressSuggestions']);

$router->get('/staff/dashboard', [StaffDashboardController::class, 'index']);

$router->get('/staff/dashboard/stats', [StaffDashboardController::class, 'stats']);






$router->get('/patient/documents', [PatientDocumentController::class, 'index']);
$router->post('/patient/documents/upload', [PatientDocumentController::class, 'upload']);
$router->get('/patient/documents/file', [PatientDocumentController::class, 'file']);
$router->post('/patient/documents/delete', [PatientDocumentController::class, 'delete']);






/*
|--------------------------------------------------------------------------
| Owner/Admin Routes
|--------------------------------------------------------------------------
| Access:
| owner, admin
| Do not use superadmin.
*/

$router->get('/admin/dashboard', [AdminDashboardController::class, 'index']);

$router->get('/admin/settings', [AdminSettingsController::class, 'index']);
$router->post('/admin/settings/clinic-profile', [AdminSettingsController::class, 'saveClinicProfile']);
$router->post('/admin/settings/public-website', [AdminSettingsController::class, 'savePublicWebsite']);
$router->post('/admin/settings/appointment-rules', [AdminSettingsController::class, 'saveAppointmentRules']);
$router->post('/admin/settings/clinic-hours', [AdminSettingsController::class, 'saveClinicHours']);
$router->post('/admin/settings/notifications', [AdminSettingsController::class, 'saveNotifications']);
$router->post('/admin/settings/messaging', [AdminSettingsController::class, 'saveMessaging']);
$router->post('/admin/settings/billing', [AdminSettingsController::class, 'saveBilling']);
$router->post('/admin/settings/documents', [AdminSettingsController::class, 'saveDocuments']);
$router->post('/admin/settings/security', [AdminSettingsController::class, 'saveSecurity']);
$router->post('/admin/settings/audit', [AdminSettingsController::class, 'saveAudit']);
$router->post('/admin/settings/backup', [AdminSettingsController::class, 'saveBackup']);
$router->post('/admin/settings/appearance', [AdminSettingsController::class, 'saveAppearance']);
$router->post('/admin/settings/maintenance', [AdminSettingsController::class, 'saveMaintenance']);
$router->post('/admin/settings/save-one', [AdminSettingsController::class, 'saveOne']);



$router->post('/admin/settings/services/store', [AdminSettingsController::class, 'storeService']);
$router->post('/admin/settings/services/update', [AdminSettingsController::class, 'updateService']);
$router->post('/admin/settings/services/status', [AdminSettingsController::class, 'toggleService']);
$router->post('/admin/settings/services/archive', [AdminSettingsController::class, 'archiveService']);
$router->post('/admin/settings/services/restore', [AdminSettingsController::class, 'restoreService']);
$router->post('/admin/settings/services/delete', [AdminSettingsController::class, 'deleteArchivedService']);



$router->get('/admin/settings', [AdminSettingsController::class, 'index']);

$router->get('/admin/settings/clinic-profile', [AdminSettingsController::class, 'clinicProfile']);
$router->get('/admin/settings/public-website', [AdminSettingsController::class, 'publicWebsite']);
$router->get('/admin/settings/appointment-rules', [AdminSettingsController::class, 'appointmentRules']);
$router->get('/admin/settings/clinic-hours', [AdminSettingsController::class, 'clinicHours']);
$router->get('/admin/settings/services', [AdminSettingsController::class, 'services']);
$router->get('/admin/settings/notifications', [AdminSettingsController::class, 'notifications']);
$router->get('/admin/settings/messaging', [AdminSettingsController::class, 'messaging']);
$router->get('/admin/settings/billing', [AdminSettingsController::class, 'billing']);
$router->get('/admin/settings/documents', [AdminSettingsController::class, 'documents']);
$router->get('/admin/settings/security', [AdminSettingsController::class, 'security']);
$router->get('/admin/settings/audit', [AdminSettingsController::class, 'audit']);
$router->get('/admin/settings/backup', [AdminSettingsController::class, 'backup']);
$router->get('/admin/settings/appearance', [AdminSettingsController::class, 'appearance']);
$router->get('/admin/settings/maintenance', [AdminSettingsController::class, 'maintenance']);
$router->get('/admin/settings/field', [AdminSettingsController::class, 'field']);




$router->get('/admin/users', [AdminUserController::class, 'index']);
$router->get('/admin/users/edit', [AdminUserController::class, 'edit']);
$router->post('/admin/users/update', [AdminUserController::class, 'update']);
$router->post('/admin/users/status', [AdminUserController::class, 'updateStatus']);
$router->post('/admin/users/roles', [AdminUserController::class, 'updateRoles']);
$router->post('/admin/users/reset-password', [AdminUserController::class, 'resetPassword']);

$router->get('/admin/dentists', [AdminDentistController::class, 'index']);
$router->get('/admin/dentists/create', [AdminDentistController::class, 'create']);
$router->post('/admin/dentists/store', [AdminDentistController::class, 'store']);
$router->get('/admin/dentists/edit', [AdminDentistController::class, 'edit']);
$router->post('/admin/dentists/update', [AdminDentistController::class, 'update']);
$router->post('/admin/dentists/status', [AdminDentistController::class, 'updateStatus']);

$router->get('/admin/audit-trail', [AdminAuditTrailController::class, 'index']);

$router->get('/admin/backup', [AdminBackupController::class, 'index']);
$router->post('/admin/backup/create', [AdminBackupController::class, 'create']);
$router->get('/admin/backup/download', [AdminBackupController::class, 'download']);
$router->post('/admin/backup/delete', [AdminBackupController::class, 'delete']);




$router->get('/patient/dashboard', [PatientDashboardController::class, 'index']);
