<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use PDO;
use RuntimeException;
use Throwable;
use App\Services\AuditLogger;
class SettingsController
{
    private PDO $db;

    private array $sections = [
        'clinic-profile' => [
            'title' => 'Clinic Profile',
            'description' => 'Basic clinic identity shown across the system and public pages.',
        ],
        'public-website' => [
            'title' => 'Public Website',
            'description' => 'Homepage text, hero images, gallery, and public page content.',
        ],
        'appointment-rules' => [
            'title' => 'Appointment Rules',
            'description' => 'Online booking, approval, cancellation, and scheduling rules.',
        ],
        'clinic-hours' => [
            'title' => 'Clinic Hours',
            'description' => 'Weekly opening hours, closing hours, and break times.',
        ],
        'services' => [
            'title' => 'Services',
            'description' => 'Dental services, prices, duration, images, and availability.',
        ],
        'notifications' => [
            'title' => 'Notifications',
            'description' => 'Appointment reminders and system notification settings.',
        ],
        'messaging' => [
            'title' => 'Messaging & Chatbot',
            'description' => 'Chatbot, guest chat, patient messages, and file upload settings.',
        ],
        'billing' => [
            'title' => 'Billing & Receipts',
            'description' => 'Billing module, receipt numbers, discounts, and payment settings.',
        ],
        'documents' => [
            'title' => 'Documents',
            'description' => 'Document upload permissions, file size, and audit controls.',
        ],
        'security' => [
            'title' => 'Security',
            'description' => 'Password rules, login attempts, 2FA, and session security.',
        ],
        'audit' => [
            'title' => 'Audit Trail',
            'description' => 'Choose which actions are logged in the audit trail.',
        ],
        'backup' => [
            'title' => 'Backup & Recovery',
            'description' => 'Backup retention, storage path, download, and restore settings.',
        ],
        'appearance' => [
            'title' => 'Appearance & Branding',
            'description' => 'System logo, favicon, theme mode, accent color, and receipt style.',
        ],
        'maintenance' => [
            'title' => 'Maintenance',
            'description' => 'Maintenance mode, maintenance message, and cleanup settings.',
        ],
    ];

    private array $settingFields = [
        'clinic-profile' => [
            'clinic_name' => ['group' => 'clinic_profile', 'label' => 'Clinic Name', 'type' => 'text', 'description' => 'Main clinic name shown across the system and public pages.'],
            'tagline' => ['group' => 'clinic_profile', 'label' => 'Tagline', 'type' => 'text', 'description' => 'Short clinic tagline or slogan.'],
            'phone_number' => ['group' => 'clinic_profile', 'label' => 'Phone Number', 'type' => 'text', 'description' => 'Primary clinic phone number.'],
            'email' => ['group' => 'clinic_profile', 'label' => 'Email', 'type' => 'email', 'description' => 'Official clinic email address.'],
            'facebook_url' => ['group' => 'clinic_profile', 'label' => 'Facebook URL', 'type' => 'url', 'description' => 'Official Facebook page link.'],
            'google_maps_url' => ['group' => 'clinic_profile', 'label' => 'Google Maps URL', 'type' => 'url', 'description' => 'Clinic location link from Google Maps.'],
            'owner_name' => ['group' => 'clinic_profile', 'label' => 'Owner Name', 'type' => 'text', 'description' => 'Clinic owner name.'],
            'clinic_logo' => ['group' => 'clinic_profile', 'label' => 'Clinic Logo', 'type' => 'file', 'description' => 'Clinic logo image.'],
            'address' => ['group' => 'clinic_profile', 'label' => 'Address', 'type' => 'textarea', 'description' => 'Complete clinic address.'],
            'about_clinic' => ['group' => 'clinic_profile', 'label' => 'About Clinic Text', 'type' => 'textarea', 'description' => 'Clinic background or about description.'],
        ],
        'public-website' => [
            'homepage_hero_title' => ['group' => 'public_website', 'label' => 'Hero Title', 'type' => 'text', 'description' => 'Main title shown on the public homepage.'],
            'hero_image' => ['group' => 'public_website', 'label' => 'Hero Image', 'type' => 'file', 'description' => 'Homepage hero image.'],
            'homepage_subtitle' => ['group' => 'public_website', 'label' => 'Homepage Subtitle', 'type' => 'textarea', 'description' => 'Short subtitle shown below the hero title.'],
            'about_section' => ['group' => 'public_website', 'label' => 'About Section', 'type' => 'textarea', 'description' => 'Public website about section content.'],
            'featured_services' => ['group' => 'public_website', 'label' => 'Featured Services', 'type' => 'textarea', 'description' => 'Featured services text for the homepage.'],
            'gallery_visibility' => ['group' => 'public_website', 'label' => 'Gallery Visibility', 'type' => 'checkbox', 'description' => 'Show or hide the homepage gallery.'],
            'contact_section' => ['group' => 'public_website', 'label' => 'Contact Section', 'type' => 'textarea', 'description' => 'Public contact section content.'],
            'homepage_cta' => ['group' => 'public_website', 'label' => 'Homepage Call-to-Action', 'type' => 'text', 'description' => 'Button or call-to-action text.'],
        ],
        'appointment-rules' => [
            'enable_online_booking' => ['group' => 'appointment_rules', 'label' => 'Enable Online Booking', 'type' => 'checkbox', 'description' => 'Allow patients or guests to book online.'],
            'enable_guest_booking' => ['group' => 'appointment_rules', 'label' => 'Enable Guest Booking', 'type' => 'checkbox', 'description' => 'Allow appointment booking without patient login.'],
            'require_staff_approval' => ['group' => 'appointment_rules', 'label' => 'Require Staff Approval', 'type' => 'checkbox', 'description' => 'Require staff confirmation before appointment is final.'],
            'allow_same_day_booking' => ['group' => 'appointment_rules', 'label' => 'Allow Same-Day Booking', 'type' => 'checkbox', 'description' => 'Allow bookings for the current day.'],
            'minimum_booking_notice_hours' => ['group' => 'appointment_rules', 'label' => 'Minimum Booking Notice Hours', 'type' => 'number', 'default' => '24', 'description' => 'Minimum hours required before booking.'],
            'maximum_advance_booking_days' => ['group' => 'appointment_rules', 'label' => 'Maximum Advance Booking Days', 'type' => 'number', 'default' => '30', 'description' => 'Maximum days allowed for advance booking.'],
            'maximum_appointments_per_day' => ['group' => 'appointment_rules', 'label' => 'Maximum Appointments Per Day', 'type' => 'number', 'default' => '30', 'description' => 'Maximum clinic appointments allowed per day.'],
            'default_appointment_duration' => ['group' => 'appointment_rules', 'label' => 'Default Appointment Duration', 'type' => 'number', 'default' => '30', 'description' => 'Default appointment duration in minutes.'],
            'allow_cancellation' => ['group' => 'appointment_rules', 'label' => 'Allow Cancellation', 'type' => 'checkbox', 'description' => 'Allow appointment cancellation.'],
            'allow_rescheduling' => ['group' => 'appointment_rules', 'label' => 'Allow Rescheduling', 'type' => 'checkbox', 'description' => 'Allow appointment rescheduling.'],
            'cancellation_deadline_hours' => ['group' => 'appointment_rules', 'label' => 'Cancellation Deadline Hours', 'type' => 'number', 'default' => '24', 'description' => 'Deadline before appointment cancellation closes.'],
        ],
        'notifications' => [
            'enable_appointment_reminders' => ['group' => 'notifications', 'label' => 'Enable Appointment Reminders', 'type' => 'checkbox', 'description' => 'Enable appointment reminder notifications.'],
            'enable_3_day_reminder' => ['group' => 'notifications', 'label' => 'Enable 3-Day Reminder', 'type' => 'checkbox', 'description' => 'Send reminder three days before appointment.'],
            'enable_2_day_reminder' => ['group' => 'notifications', 'label' => 'Enable 2-Day Reminder', 'type' => 'checkbox', 'description' => 'Send reminder two days before appointment.'],
            'enable_1_day_reminder' => ['group' => 'notifications', 'label' => 'Enable 1-Day Reminder', 'type' => 'checkbox', 'description' => 'Send reminder one day before appointment.'],
            'enable_dentist_notifications' => ['group' => 'notifications', 'label' => 'Enable Dentist Notifications', 'type' => 'checkbox', 'description' => 'Notify dentists about appointment updates.'],
            'enable_staff_notifications' => ['group' => 'notifications', 'label' => 'Enable Staff Notifications', 'type' => 'checkbox', 'description' => 'Notify staff about system updates.'],
            'enable_patient_notifications' => ['group' => 'notifications', 'label' => 'Enable Patient Notifications', 'type' => 'checkbox', 'description' => 'Notify patients about appointments.'],
            'default_reminder_template' => ['group' => 'notifications', 'label' => 'Default Reminder Template', 'type' => 'textarea', 'description' => 'Default text used for appointment reminders.'],
        ],
        'messaging' => [
            'enable_chatbot' => ['group' => 'messaging', 'label' => 'Enable Chatbot', 'type' => 'checkbox', 'description' => 'Enable chatbot support.'],
            'enable_guest_chatbot' => ['group' => 'messaging', 'label' => 'Enable Guest Chatbot', 'type' => 'checkbox', 'description' => 'Allow guests to use chatbot support.'],
            'enable_staff_patient_messaging' => ['group' => 'messaging', 'label' => 'Enable Staff-Patient Messaging', 'type' => 'checkbox', 'description' => 'Allow staff and patients to message each other.'],
            'allow_guest_messaging' => ['group' => 'messaging', 'label' => 'Allow Guest Messaging', 'type' => 'checkbox', 'description' => 'Allow guests to send messages.'],
            'allow_patient_file_upload' => ['group' => 'messaging', 'label' => 'Allow Patient File Upload', 'type' => 'checkbox', 'description' => 'Allow patients to upload files in chat.'],
            'disable_file_upload_for_guests' => ['group' => 'messaging', 'label' => 'Disable File Upload for Guests', 'type' => 'checkbox', 'description' => 'Prevent guest users from uploading files.'],
            'chatbot_welcome_message' => ['group' => 'messaging', 'label' => 'Chatbot Welcome Message', 'type' => 'textarea', 'description' => 'First message shown by the chatbot.'],
            'chatbot_fallback_message' => ['group' => 'messaging', 'label' => 'Chatbot Fallback Message', 'type' => 'textarea', 'description' => 'Message shown when chatbot cannot answer.'],
            'archive_inactive_conversations_days' => ['group' => 'messaging', 'label' => 'Archive Inactive Conversations Days', 'type' => 'number', 'default' => '30', 'description' => 'Days before inactive conversations are archived.'],
        ],
        'billing' => [
            'enable_billing_module' => ['group' => 'billing', 'label' => 'Enable Billing Module', 'type' => 'checkbox', 'description' => 'Enable billing and receipt features.'],
            'currency' => ['group' => 'billing', 'label' => 'Currency', 'type' => 'text', 'default' => 'PHP', 'description' => 'Currency used for billing.'],
            'billing_number_prefix' => ['group' => 'billing', 'label' => 'Billing Number Prefix', 'type' => 'text', 'default' => 'BILL', 'description' => 'Prefix for billing numbers.'],
            'receipt_number_prefix' => ['group' => 'billing', 'label' => 'Receipt Number Prefix', 'type' => 'text', 'default' => 'RCPT', 'description' => 'Prefix for receipt numbers.'],
            'allow_partial_payment' => ['group' => 'billing', 'label' => 'Allow Partial Payment', 'type' => 'checkbox', 'description' => 'Allow patients to pay partially.'],
            'allow_discount' => ['group' => 'billing', 'label' => 'Allow Discount', 'type' => 'checkbox', 'description' => 'Allow discounts in billing.'],
            'require_discount_remarks' => ['group' => 'billing', 'label' => 'Require Discount Remarks', 'type' => 'checkbox', 'description' => 'Require remarks when a discount is applied.'],
            'require_reference_number' => ['group' => 'billing', 'label' => 'Require Reference Number', 'type' => 'checkbox', 'description' => 'Require reference number for manual payment methods.'],
            'receipt_footer_message' => ['group' => 'billing', 'label' => 'Receipt Footer Message', 'type' => 'textarea', 'description' => 'Footer message shown on receipts.'],
        ],
        'documents' => [
            'enable_document_uploads' => ['group' => 'documents', 'label' => 'Enable Document Uploads', 'type' => 'checkbox', 'description' => 'Allow document uploads.'],
            'allowed_file_types' => ['group' => 'documents', 'label' => 'Allowed File Types', 'type' => 'text', 'default' => 'pdf,jpg,jpeg,png,webp', 'description' => 'Allowed document file extensions.'],
            'maximum_file_size_mb' => ['group' => 'documents', 'label' => 'Maximum File Size MB', 'type' => 'number', 'default' => '5', 'description' => 'Maximum uploaded document file size.'],
            'allow_dentist_upload' => ['group' => 'documents', 'label' => 'Allow Dentist Upload', 'type' => 'checkbox', 'description' => 'Allow dentists to upload patient documents.'],
            'allow_staff_upload' => ['group' => 'documents', 'label' => 'Allow Staff Upload', 'type' => 'checkbox', 'description' => 'Allow staff to upload patient documents.'],
            'enable_document_audit_log' => ['group' => 'documents', 'label' => 'Enable Document Audit Log', 'type' => 'checkbox', 'description' => 'Log document upload, update, and delete actions.'],
            'use_soft_delete' => ['group' => 'documents', 'label' => 'Use Soft Delete', 'type' => 'checkbox', 'description' => 'Keep deleted document records instead of permanently deleting them.'],
        ],
        'security' => [
            'minimum_password_length' => ['group' => 'security', 'label' => 'Minimum Password Length', 'type' => 'number', 'default' => '8', 'description' => 'Minimum required password length.'],
            'require_strong_password' => ['group' => 'security', 'label' => 'Require Strong Password', 'type' => 'checkbox', 'description' => 'Require strong password rules.'],
            'enable_2fa_owner_admin_staff' => ['group' => 'security', 'label' => 'Enable 2FA for Owner/Admin/Staff', 'type' => 'checkbox', 'description' => 'Require two-factor authentication if available.'],
            'session_timeout_minutes' => ['group' => 'security', 'label' => 'Session Timeout Minutes', 'type' => 'number', 'default' => '60', 'description' => 'Automatic logout time in minutes.'],
            'maximum_login_attempts' => ['group' => 'security', 'label' => 'Maximum Login Attempts', 'type' => 'number', 'default' => '5', 'description' => 'Allowed failed login attempts.'],
            'lock_account_after_failed_attempts' => ['group' => 'security', 'label' => 'Lock Account After Failed Attempts', 'type' => 'checkbox', 'description' => 'Lock account after too many failed attempts.'],
        ],
        'audit' => [
            'enable_audit_logging' => ['group' => 'audit', 'label' => 'Enable Audit Logging', 'type' => 'checkbox', 'description' => 'Enable system audit logging.'],
            'log_login_logout' => ['group' => 'audit', 'label' => 'Log Login/Logout', 'type' => 'checkbox', 'description' => 'Log user login and logout actions.'],
            'log_appointment_changes' => ['group' => 'audit', 'label' => 'Log Appointment Changes', 'type' => 'checkbox', 'description' => 'Log appointment status and schedule changes.'],
            'log_billing_payment_actions' => ['group' => 'audit', 'label' => 'Log Billing and Payment Actions', 'type' => 'checkbox', 'description' => 'Log billing and payment updates.'],
            'log_document_actions' => ['group' => 'audit', 'label' => 'Log Document Actions', 'type' => 'checkbox', 'description' => 'Log document uploads and deletes.'],
            'log_settings_updates' => ['group' => 'audit', 'label' => 'Log Settings Updates', 'type' => 'checkbox', 'description' => 'Log owner/admin settings changes.'],
            'log_backup_restore_actions' => ['group' => 'audit', 'label' => 'Log Backup/Restore Actions', 'type' => 'checkbox', 'description' => 'Log backup and restore actions.'],
        ],
        'backup' => [
            'backup_retention_days' => ['group' => 'backup', 'label' => 'Backup Retention Days', 'type' => 'number', 'default' => '30', 'description' => 'Number of days to keep backup files.'],
            'backup_storage_path' => ['group' => 'backup', 'label' => 'Backup Storage Path', 'type' => 'text', 'default' => 'backups', 'description' => 'Folder path where backup files are saved.'],
            'allow_backup_download' => ['group' => 'backup', 'label' => 'Allow Backup Download', 'type' => 'checkbox', 'description' => 'Allow owner/admin to download backup files.'],
            'allow_restore_backup' => ['group' => 'backup', 'label' => 'Allow Backup Restore', 'type' => 'checkbox', 'description' => 'Allow database restore actions.'],
        ],
        'appearance' => [
            'system_logo' => ['group' => 'appearance', 'label' => 'System Logo', 'type' => 'file', 'description' => 'Main system logo.'],
            'favicon' => ['group' => 'appearance', 'label' => 'Favicon', 'type' => 'file', 'description' => 'Browser tab icon.'],
            'login_page_background' => ['group' => 'appearance', 'label' => 'Login Page Background', 'type' => 'file', 'description' => 'Background image for login page.'],
            'default_theme_mode' => ['group' => 'appearance', 'label' => 'Default Theme Mode', 'type' => 'select', 'options' => ['light' => 'Light', 'dark' => 'Dark'], 'default' => 'light', 'description' => 'Default system theme mode.'],
            'default_accent_color' => ['group' => 'appearance', 'label' => 'Default Accent Color', 'type' => 'text', 'default' => '#0f766e', 'description' => 'Default interface accent color.'],
            'receipt_design_style' => ['group' => 'appearance', 'label' => 'Receipt Design Style', 'type' => 'select', 'options' => ['simple' => 'Simple', 'modern' => 'Modern'], 'default' => 'simple', 'description' => 'Default receipt print style.'],
            'public_homepage_theme' => ['group' => 'appearance', 'label' => 'Public Homepage Theme', 'type' => 'select', 'options' => ['default' => 'Default', 'minimal' => 'Minimal'], 'default' => 'default', 'description' => 'Public homepage theme style.'],
        ],
        'maintenance' => [
            'maintenance_mode' => ['group' => 'maintenance', 'label' => 'Maintenance Mode', 'type' => 'checkbox', 'description' => 'Enable maintenance mode.'],
            'maintenance_message' => ['group' => 'maintenance', 'label' => 'Maintenance Message', 'type' => 'textarea', 'description' => 'Message shown during maintenance.'],
            'allow_owner_admin_login' => ['group' => 'maintenance', 'label' => 'Allow Owner/Admin Login During Maintenance', 'type' => 'checkbox', 'description' => 'Allow owner/admin access while maintenance mode is active.'],
            'clear_old_logs_days' => ['group' => 'maintenance', 'label' => 'Clear Old Logs After Days', 'type' => 'number', 'default' => '90', 'description' => 'Delete logs older than this number of days.'],
            'clear_temp_files_enabled' => ['group' => 'maintenance', 'label' => 'Enable Temp File Cleanup', 'type' => 'checkbox', 'description' => 'Allow cleanup of temporary files.'],
        ],
    ];

    private array $allowedServices = [
        'Tooth Extraction',
        'Dental Cleaning',
        'Tooth Restoration',
        'Root Canal Treatment',
        'Dentures/Crowns/Fixed Bridges',
        'Orthodontics/Braces',
        'Surgery',
        'Dental Implants',
        'Teeth Whitening',
    ];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function index(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);
        $this->requireOwnerOrAdmin();

        $settings = $this->settings();
        $clinicHours = $this->clinicHoursRows();
        $services = $this->serviceRows(false);
        $archivedServices = $this->serviceRows(true);

        View::render('admin.settings.index', [
            'pageTitle' => 'System Settings',
            'sections' => $this->sections,
            'settingFields' => $this->settingFields,
            'settings' => $settings,
            'clinicHours' => $clinicHours,
            'services' => $services,
            'archivedServices' => $archivedServices,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        $this->clearFlash();
    }

    public function clinicProfile(): void { $this->renderSection('clinic-profile'); }
    public function publicWebsite(): void { $this->renderSection('public-website'); }
    public function appointmentRules(): void { $this->renderSection('appointment-rules'); }
    public function clinicHours(): void { $this->renderSection('clinic-hours'); }
    public function services(): void { $this->renderSection('services'); }
    public function notifications(): void { $this->renderSection('notifications'); }
    public function messaging(): void { $this->renderSection('messaging'); }
    public function billing(): void { $this->renderSection('billing'); }
    public function documents(): void { $this->renderSection('documents'); }
    public function security(): void { $this->renderSection('security'); }
    public function audit(): void { $this->renderSection('audit'); }
    public function backup(): void { $this->renderSection('backup'); }
    public function appearance(): void { $this->renderSection('appearance'); }
    public function maintenance(): void { $this->renderSection('maintenance'); }

    public function field(): void
    {
        $this->requireOwnerOrAdmin();

        $sectionKey = trim((string) ($_GET['section'] ?? ''));
        $fieldKey = trim((string) ($_GET['field'] ?? ''));

        if (!isset($this->sections[$sectionKey], $this->settingFields[$sectionKey][$fieldKey])) {
            http_response_code(404);
            exit('Setting field not found.');
        }

        View::render('admin.settings.field', [
            'pageTitle' => $this->settingFields[$sectionKey][$fieldKey]['label'] ?? 'Edit Setting',
            'sectionKey' => $sectionKey,
            'fieldKey' => $fieldKey,
            'section' => $this->sections[$sectionKey],
            'field' => $this->settingFields[$sectionKey][$fieldKey],
            'settings' => $this->settings(),
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        $this->clearFlash();
    }

    public function saveOne(): void
    {
        $user = $this->requireOwnerOrAdmin();
        $this->verifyCsrfOrFail();

        $group = trim((string) ($_POST['setting_group'] ?? ''));
        $key = trim((string) ($_POST['setting_key'] ?? ''));
        $redirect = $this->safeRedirect((string) ($_POST['redirect_to'] ?? '/DentalClinic/public/admin/settings'));

        try {
            $field = $this->findFieldByGroupKey($group, $key);

            if (!$field) {
                throw new RuntimeException('Invalid setting field.');
            }

            $type = (string) ($field['type'] ?? 'text');
            $value = $this->inputValue($key, $field, 'setting_value');
            if ($type === 'checkbox') {
    $value = ((string) ($_POST['setting_value'] ?? '0') === '1') ? '1' : '0';
}

            if ($type === 'file') {
                $uploadedPath = $this->uploadFile('setting_file', 'settings');

                if ($uploadedPath !== '') {
                    $value = $uploadedPath;
                } else {
                    $value = $this->settingValue($group, $key, (string) ($field['default'] ?? ''));
                }
            }

            $this->saveSetting($group, $key, $value, $type, $this->isPublicSetting($group, $key), (int) $user['user_id']);
            $this->auditLog((int) $user['user_id'], 'system_settings', "Updated setting: {$group}.{$key}");

            Session::set('flash_success', 'Setting saved successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        $this->redirect($redirect);
    }

    public function saveClinicProfile(): void { $this->saveSection('clinic-profile'); }
    public function savePublicWebsite(): void { $this->saveSection('public-website'); }
    public function saveAppointmentRules(): void { $this->saveSection('appointment-rules'); }
    public function saveNotifications(): void { $this->saveSection('notifications'); }
    public function saveMessaging(): void { $this->saveSection('messaging'); }
    public function saveBilling(): void { $this->saveSection('billing'); }
    public function saveDocuments(): void { $this->saveSection('documents'); }
    public function saveSecurity(): void { $this->saveSection('security'); }
    public function saveAudit(): void { $this->saveSection('audit'); }
    public function saveBackup(): void { $this->saveSection('backup'); }
    public function saveAppearance(): void { $this->saveSection('appearance'); }
    public function saveMaintenance(): void { $this->saveSection('maintenance'); }

    public function saveClinicHours(): void
    {
        $user = $this->requireOwnerOrAdmin();
        $this->verifyCsrfOrFail();

        try {
            $days = $_POST['days'] ?? [];

            if (!is_array($days)) {
                throw new RuntimeException('Invalid clinic hours data.');
            }

            $stmt = $this->db->prepare("
                INSERT INTO clinic_hours (
                    day_of_week,
                    is_open,
                    opening_time,
                    closing_time,
                    break_start,
                    break_end,
                    updated_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :day_of_week,
                    :is_open,
                    :opening_time,
                    :closing_time,
                    :break_start,
                    :break_end,
                    :updated_by,
                    NOW(),
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    is_open = VALUES(is_open),
                    opening_time = VALUES(opening_time),
                    closing_time = VALUES(closing_time),
                    break_start = VALUES(break_start),
                    break_end = VALUES(break_end),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()
            ");

            foreach ([1, 2, 3, 4, 5, 6, 0] as $dayIndex) {
                $row = isset($days[$dayIndex]) && is_array($days[$dayIndex]) ? $days[$dayIndex] : [];

                $isOpen = isset($row['is_open']) ? 1 : 0;
                $opening = $this->timeOrNull($row['opening_time'] ?? null);
                $closing = $this->timeOrNull($row['closing_time'] ?? null);
                $breakStart = $this->timeOrNull($row['break_start'] ?? null);
                $breakEnd = $this->timeOrNull($row['break_end'] ?? null);

                if ($isOpen && $opening && $closing && $opening >= $closing) {
                    throw new RuntimeException('Opening time must be earlier than closing time.');
                }

                if ($isOpen && $breakStart && $breakEnd && $breakStart >= $breakEnd) {
                    throw new RuntimeException('Break start must be earlier than break end.');
                }

                $stmt->execute([
                    'day_of_week' => $dayIndex,
                    'is_open' => $isOpen,
                    'opening_time' => $isOpen ? $opening : null,
                    'closing_time' => $isOpen ? $closing : null,
                    'break_start' => $isOpen ? $breakStart : null,
                    'break_end' => $isOpen ? $breakEnd : null,
                    'updated_by' => (int) $user['user_id'],
                ]);
            }

            $this->auditLog((int) $user['user_id'], 'clinic_hours', 'Updated clinic hours.');
            Session::set('flash_success', 'Clinic hours saved successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        $this->redirect('/DentalClinic/public/admin/settings/clinic-hours');
    }

    public function storeService(): void
    {
        $user = $this->requireOwnerOrAdmin();
        $this->verifyCsrfOrFail();

        try {
            $name = $this->requiredText($_POST['service_name'] ?? '', 'Service name is required.', 150);
            $duration = $this->positiveInt($_POST['estimated_duration_minutes'] ?? 30, 5, 1440);
            $price = $this->money($_POST['estimated_price'] ?? 0);
            $displayOrder = $this->positiveInt($_POST['display_order'] ?? 0, 0, 9999);
            $description = trim((string) ($_POST['description'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $image = $this->uploadFile('service_image', 'services');

            $stmt = $this->db->prepare("
                INSERT INTO services (
                    service_name,
                    description,
                    estimated_duration_minutes,
                    estimated_price,
                    service_image,
                    display_order,
                    is_active,
                    created_at,
                    updated_at
                ) VALUES (
                    :service_name,
                    :description,
                    :duration,
                    :price,
                    :image,
                    :display_order,
                    :is_active,
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                'service_name' => $name,
                'description' => $description,
                'duration' => $duration,
                'price' => $price,
                'image' => $image !== '' ? $image : null,
                'display_order' => $displayOrder,
                'is_active' => $isActive,
            ]);

            $this->auditLog((int) $user['user_id'], 'services', 'Created service: ' . $name);
            Session::set('flash_success', 'Service added successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        $this->redirect('/DentalClinic/public/admin/settings/services');
    }

    public function updateService(): void
    {
        $user = $this->requireOwnerOrAdmin();
        $this->verifyCsrfOrFail();

        try {
            $serviceId = (int) ($_POST['service_id'] ?? 0);

            if ($serviceId <= 0) {
                throw new RuntimeException('Invalid service.');
            }

            $name = $this->requiredText($_POST['service_name'] ?? '', 'Service name is required.', 150);
            $duration = $this->positiveInt($_POST['estimated_duration_minutes'] ?? 30, 5, 1440);
            $price = $this->money($_POST['estimated_price'] ?? 0);
            $displayOrder = $this->positiveInt($_POST['display_order'] ?? 0, 0, 9999);
            $description = trim((string) ($_POST['description'] ?? ''));
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $image = $this->uploadFile('service_image', 'services');

            if ($image !== '') {
                $stmt = $this->db->prepare("
                    UPDATE services
                    SET service_name = :service_name,
                        description = :description,
                        estimated_duration_minutes = :duration,
                        estimated_price = :price,
                        service_image = :image,
                        display_order = :display_order,
                        is_active = :is_active,
                        updated_at = NOW()
                    WHERE service_id = :service_id
                    LIMIT 1
                ");

                $stmt->execute([
                    'service_name' => $name,
                    'description' => $description,
                    'duration' => $duration,
                    'price' => $price,
                    'image' => $image,
                    'display_order' => $displayOrder,
                    'is_active' => $isActive,
                    'service_id' => $serviceId,
                ]);
            } else {
                $stmt = $this->db->prepare("
                    UPDATE services
                    SET service_name = :service_name,
                        description = :description,
                        estimated_duration_minutes = :duration,
                        estimated_price = :price,
                        display_order = :display_order,
                        is_active = :is_active,
                        updated_at = NOW()
                    WHERE service_id = :service_id
                    LIMIT 1
                ");

                $stmt->execute([
                    'service_name' => $name,
                    'description' => $description,
                    'duration' => $duration,
                    'price' => $price,
                    'display_order' => $displayOrder,
                    'is_active' => $isActive,
                    'service_id' => $serviceId,
                ]);
            }

            $this->auditLog((int) $user['user_id'], 'services', 'Updated service: ' . $name);
            Session::set('flash_success', 'Service updated successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        $this->redirect('/DentalClinic/public/admin/settings/services');
    }

    public function toggleService(): void
    {
        $user = $this->requireOwnerOrAdmin();
        $this->verifyCsrfOrFail();

        try {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $isActive = (int) ($_POST['is_active'] ?? 0) === 1 ? 1 : 0;

            if ($serviceId <= 0) {
                throw new RuntimeException('Invalid service.');
            }

            $stmt = $this->db->prepare("
                UPDATE services
                SET is_active = :is_active,
                    updated_at = NOW()
                WHERE service_id = :service_id
                LIMIT 1
            ");

            $stmt->execute([
                'is_active' => $isActive,
                'service_id' => $serviceId,
            ]);

            $this->auditLog((int) $user['user_id'], 'services', 'Updated service status.');
            Session::set('flash_success', 'Service status updated successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        $this->redirect('/DentalClinic/public/admin/settings/services');
    }

    public function archiveService(): void
    {
        $user = $this->requireOwnerOrAdmin();
        $this->verifyCsrfOrFail();

        try {
            $serviceId = (int) ($_POST['service_id'] ?? 0);

            if ($serviceId <= 0) {
                throw new RuntimeException('Invalid service.');
            }

            $stmt = $this->db->prepare("UPDATE services SET is_active = 0, archived_at = NOW(), archived_by = :archived_by, updated_at = NOW() WHERE service_id = :service_id LIMIT 1");
            $stmt->execute([
                ':archived_by' => (int) $user['user_id'],
                ':service_id' => $serviceId,
            ]);

            $this->auditLog((int) $user['user_id'], 'services', 'Archived service #' . $serviceId . '.');
            Session::set('flash_success', 'Service moved to archive.');
        } catch (Throwable $e) {
            Session::set('flash_error', 'Unable to archive service.');
        }

        $this->redirect('/DentalClinic/public/admin/settings/services');
    }

    public function restoreService(): void
    {
        $user = $this->requireOwnerOrAdmin();
        $this->verifyCsrfOrFail();

        try {
            $serviceId = (int) ($_POST['service_id'] ?? 0);

            if ($serviceId <= 0) {
                throw new RuntimeException('Invalid service.');
            }

            $stmt = $this->db->prepare("UPDATE services SET is_active = 1, archived_at = NULL, archived_by = NULL, updated_at = NOW() WHERE service_id = :service_id LIMIT 1");
            $stmt->execute([':service_id' => $serviceId]);

            $this->auditLog((int) $user['user_id'], 'services', 'Restored service #' . $serviceId . '.');
            Session::set('flash_success', 'Service restored successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', 'Unable to restore service.');
        }

        $this->redirect('/DentalClinic/public/admin/settings/services');
    }

    public function deleteArchivedService(): void
    {
        $user = $this->requireOwnerOrAdmin();
        $this->verifyCsrfOrFail();

        try {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $stmt = $this->db->prepare('SELECT service_name, archived_at FROM services WHERE service_id = :service_id LIMIT 1');
            $stmt->execute([':service_id' => $serviceId]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$service || empty($service['archived_at'])) {
                throw new RuntimeException('Only archived services can be permanently deleted.');
            }

            $delete = $this->db->prepare('DELETE FROM services WHERE service_id = :service_id LIMIT 1');
            $delete->execute([':service_id' => $serviceId]);

            $this->auditLog((int) $user['user_id'], 'services', 'Permanently deleted archived service: ' . (string) ($service['service_name'] ?? $serviceId));
            Session::set('flash_success', 'Archived service permanently deleted.');
        } catch (Throwable $e) {
            error_log('[SettingsController::deleteArchivedService] ' . $e->getMessage());
            Session::set('flash_error', 'This service cannot be permanently deleted because it is referenced by existing records.');
        }

        $this->redirect('/DentalClinic/public/admin/settings/services');
    }

    private function renderSection(string $sectionKey): void
    {
        $this->requireOwnerOrAdmin();

        if (!isset($this->sections[$sectionKey])) {
            http_response_code(404);
            exit('Settings section not found.');
        }

        View::render('admin.settings.section', [
            'pageTitle' => $this->sections[$sectionKey]['title'] ?? 'Settings',
            'sectionKey' => $sectionKey,
            'section' => $this->sections[$sectionKey],
            'sections' => $this->sections,
            'settingFields' => $this->settingFields,
            'settings' => $this->settings(),
            'clinicHours' => $this->clinicHoursRows(),
            'services' => $this->serviceRows(false),
            'archivedServices' => $this->serviceRows(true),
            'allowedServices' => $this->allowedServices,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        $this->clearFlash();
    }

    private function saveSection(string $sectionKey): void
    {
        $user = $this->requireOwnerOrAdmin();
        $this->verifyCsrfOrFail();

        try {
            if (!isset($this->settingFields[$sectionKey])) {
                throw new RuntimeException('Invalid settings section.');
            }

            foreach ($this->settingFields[$sectionKey] as $key => $field) {
                $group = (string) ($field['group'] ?? '');
                $type = (string) ($field['type'] ?? 'text');

                if ($group === '') {
                    continue;
                }

                if ($type === 'file') {
                    $uploaded = $this->uploadFile($key, 'settings');
                    $value = $uploaded !== ''
                        ? $uploaded
                        : $this->settingValue($group, (string) $key, (string) ($field['default'] ?? ''));
                } else {
                    $value = $this->inputValue((string) $key, $field, (string) $key);
                }

                $this->saveSetting($group, (string) $key, $value, $type, $this->isPublicSetting($group, (string) $key), (int) $user['user_id']);
            }

            $this->auditLog((int) $user['user_id'], str_replace('-', '_', $sectionKey), 'Updated ' . $this->sections[$sectionKey]['title'] . ' settings.');
            Session::set('flash_success', 'Settings saved successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        $this->redirect('/DentalClinic/public/admin/settings/' . $sectionKey);
    }

    private function settings(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM system_settings
            ORDER BY setting_group ASC, setting_key ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];

        foreach ($rows as $row) {
            $settings[(string) $row['setting_group']][(string) $row['setting_key']] = $row;
        }

        return $settings;
    }

    private function settingValue(string $group, string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare("
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

        return $value === false ? $default : (string) $value;
    }

    private function saveSetting(
        string $group,
        string $key,
        string $value,
        string $type,
        int $isPublic,
        int $userId
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO system_settings (
                setting_group,
                setting_key,
                setting_value,
                value_type,
                is_public,
                updated_by,
                created_at,
                updated_at,
                setting_type
            ) VALUES (
                :setting_group,
                :setting_key,
                :setting_value,
                'string',
                :is_public,
                :updated_by,
                NOW(),
                NOW(),
                :setting_type
            )
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = VALUES(value_type),
                is_public = VALUES(is_public),
                updated_by = VALUES(updated_by),
                updated_at = NOW(),
                setting_type = VALUES(setting_type)
        ");

        $stmt->execute([
            'setting_group' => $group,
            'setting_key' => $key,
            'setting_value' => $value,
            'is_public' => $isPublic,
            'updated_by' => $userId,
            'setting_type' => $type,
        ]);
    }

    private function clinicHoursRows(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM clinic_hours
            ORDER BY FIELD(day_of_week, 1, 2, 3, 4, 5, 6, 0)
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function serviceRows(bool $archived = false): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM services
            WHERE " . ($archived ? 'archived_at IS NOT NULL' : 'archived_at IS NULL') . "
            ORDER BY display_order ASC, service_name ASC, service_id ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function inputValue(string $key, array $field, string $inputName): string
{
    $type = (string) ($field['type'] ?? 'text');
    $default = (string) ($field['default'] ?? '');

    if ($type === 'checkbox') {
        $rawValue = $_POST[$inputName] ?? '0';

        if (is_array($rawValue)) {
            $rawValue = end($rawValue);
        }

        return in_array(
            strtolower(trim((string) $rawValue)),
            ['1', 'yes', 'true', 'on', 'enabled'],
            true
        ) ? '1' : '0';
    }

    $value = trim((string) ($_POST[$inputName] ?? $default));

    if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException(($field['label'] ?? $key) . ' must be a valid email address.');
    }

    if ($type === 'url' && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
        throw new RuntimeException(($field['label'] ?? $key) . ' must be a valid URL.');
    }

    if ($type === 'number') {
        if ($value === '') {
            return $default !== '' ? $default : '0';
        }

        if (!is_numeric($value)) {
            throw new RuntimeException(($field['label'] ?? $key) . ' must be a number.');
        }

        return (string) $value;
    }

    if ($type === 'select') {
        $options = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];

        if (!empty($options) && !array_key_exists($value, $options)) {
            throw new RuntimeException('Invalid selected value.');
        }
    }

    if ($type === 'textarea') {
        return mb_substr($value, 0, 20000);
    }

    return mb_substr($value, 0, 5000);
}

    private function isPublicSetting(string $group, string $key): int
    {
        $publicKeys = [
            'clinic_profile' => [
                'clinic_name',
                'tagline',
                'address',
                'phone_number',
                'email',
                'facebook_url',
                'google_maps_url',
                'clinic_logo',
                'about_clinic',
            ],
            'public_website' => [
                'homepage_hero_title',
                'hero_image',
                'homepage_subtitle',
                'about_section',
                'featured_services',
                'gallery_visibility',
                'contact_section',
                'homepage_cta',
            ],
            'appearance' => [
                'system_logo',
                'favicon',
                'default_accent_color',
                'public_homepage_theme',
            ],
        ];

        return in_array($key, $publicKeys[$group] ?? [], true) ? 1 : 0;
    }

    private function uploadFile(string $inputName, string $folder): string
    {
        if (empty($_FILES[$inputName]) || !is_array($_FILES[$inputName])) {
            return '';
        }

        $file = $_FILES[$inputName];

        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'ico', 'svg'];

        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('Invalid file type. Allowed: JPG, PNG, WEBP, GIF, ICO, SVG.');
        }

        $maxBytes = 5 * 1024 * 1024;

        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException('File must not exceed 5MB.');
        }

        $projectRoot = dirname(__DIR__, 3);
        $relativeDir = '/uploads/' . trim($folder, '/');
        $publicDir = $projectRoot . '/public' . $relativeDir;

        if (!is_dir($publicDir) && !mkdir($publicDir, 0775, true) && !is_dir($publicDir)) {
            throw new RuntimeException('Unable to create upload directory.');
        }

        $safeName = $folder . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destination = $publicDir . '/' . $safeName;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('Unable to save uploaded file.');
        }

        return $relativeDir . '/' . $safeName;
    }

    private function timeOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
            throw new RuntimeException('Invalid time format.');
        }

        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    private function requiredText(mixed $value, string $message, int $max = 255): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            throw new RuntimeException($message);
        }

        return mb_substr($value, 0, $max);
    }

    private function positiveInt(mixed $value, int $min, int $max): int
    {
        $value = (int) $value;

        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    private function money(mixed $value): string
    {
        $value = (float) $value;

        if ($value < 0) {
            $value = 0;
        }

        return number_format($value, 2, '.', '');
    }

    private function requireOwnerOrAdmin(): array
    {
        if (method_exists(Auth::class, 'requireLogin')) {
            Auth::requireLogin();
        }

        $user = Auth::user();

        if (!$user || empty($user['user_id'])) {
            $this->redirect('/DentalClinic/public/login');
        }

        if (!Auth::hasAnyRole(['owner', 'admin'])) {
            http_response_code(403);
            exit('Access denied.');
        }

        return $user;
    }

    private function verifyCsrfOrFail(): void
    {
        $token = $_POST['_csrf_token']
            ?? $_POST['_token']
            ?? $_POST['csrf_token']
            ?? null;

        if (!Csrf::verify($token)) {
            throw new RuntimeException('Invalid CSRF token. Please refresh and try again.');
        }
    }

    private function auditLog(int $userId, string $entityType, string $description): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (
                    user_id,
                    action,
                    entity_type,
                    entity_id,
                    module_name,
                    action_name,
                    record_type,
                    record_id,
                    description,
                    ip_address,
                    created_at
                ) VALUES (
                    :user_id,
                    'settings.update',
                    :entity_type,
                    NULL,
                    'settings',
                    'update',
                    :record_type,
                    '',
                    :description,
                    :ip_address,
                    NOW()
                )
            ");

            
            $stmt->execute([
                'user_id' => $userId,
                'entity_type' => $entityType,
                'record_type' => $entityType,
                'description' => $description,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable) {
        }
        
    }
    
    private function findFieldByGroupKey(string $group, string $key): ?array
{
    foreach ($this->settingFields as $sectionFields) {
        foreach ($sectionFields as $fieldKey => $field) {
            if (
                (string) ($field['group'] ?? '') === $group
                && (string) $fieldKey === $key
            ) {
                return $field;
            }
        }
    }

    return null;
}

    private function safeRedirect(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '/DentalClinic/public/admin/settings';
        }

        if (str_starts_with($url, '/DentalClinic/public/admin/settings')) {
            return $url;
        }

        return '/DentalClinic/public/admin/settings';
    }

    private function clearFlash(): void
    {
        Session::remove('flash_success');
        Session::remove('flash_error');
        
    }

    private function redirect(string $url): void
    {
        
        header('Location: ' . $url);
        exit;
    }
}