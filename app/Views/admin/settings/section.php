<?php

use App\Core\Csrf;

$pageTitle = $section['title'] ?? 'Settings';
$baseUrl = '/DentalClinic/public';

$sectionKey = isset($sectionKey) ? (string) $sectionKey : '';
$section = isset($section) && is_array($section) ? $section : [];
$settings = isset($settings) && is_array($settings) ? $settings : [];
$clinicHours = isset($clinicHours) && is_array($clinicHours) ? $clinicHours : [];
$services = isset($services) && is_array($services) ? $services : [];
$archivedServices = isset($archivedServices) && is_array($archivedServices) ? $archivedServices : [];
$allowedServices = isset($allowedServices) && is_array($allowedServices) ? $allowedServices : [];

$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

if (!function_exists('admin_setting_e')) {
    function admin_setting_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_setting_value')) {
    function admin_setting_value(array $settings, string $group, string $key, string $default = ''): string
    {
        $value = $settings[$group][$key] ?? $default;

        if (is_array($value)) {
            $value = $value['setting_value'] ?? $value['value'] ?? $value['setting_text'] ?? $default;
        }

        return (string) $value;
    }
}

if (!function_exists('admin_setting_is_enabled')) {
    function admin_setting_is_enabled(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'yes', 'true', 'on', 'enabled'], true);
    }
}

if (!function_exists('admin_setting_short_value')) {
    function admin_setting_short_value(string $value, int $limit = 48): string
    {
        $value = trim($value);

        if ($value === '') {
            return 'Not set';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value) <= $limit) {
                return $value;
            }

            return mb_substr($value, 0, $limit - 3) . '...';
        }

        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit - 3) . '...';
    }
}

if (!function_exists('admin_simple_icon')) {
    function admin_simple_icon(string $type = 'settings'): string
    {
        $type = strtolower($type);

        $paths = [
            'clinic-profile' => '<path d="M4 19V9.5L12 4L20 9.5V19"></path><path d="M8 19V12H16V19"></path><path d="M10 15H14"></path><path d="M12 13V17"></path>',
            'public-website' => '<circle cx="12" cy="12" r="9"></circle><path d="M3 12H21"></path><path d="M12 3C14.5 5.7 15.7 8.7 15.7 12C15.7 15.3 14.5 18.3 12 21C9.5 18.3 8.3 15.3 8.3 12C8.3 8.7 9.5 5.7 12 3Z"></path>',
            'appointment-rules' => '<rect x="4" y="5" width="16" height="16" rx="3"></rect><path d="M8 3V7"></path><path d="M16 3V7"></path><path d="M4 10H20"></path><path d="M8 15L11 18L16 13"></path>',
            'clinic-hours' => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7V12L16 14"></path>',
            'services' => '<path d="M8.5 4C5.8 4 4 6.3 4 9.2C4 14.2 7 21 9.2 21C10.7 21 10 15.5 12 15.5C14 15.5 13.3 21 14.8 21C17 21 20 14.2 20 9.2C20 6.3 18.2 4 15.5 4C14 4 13.1 4.8 12 4.8C10.9 4.8 10 4 8.5 4Z"></path><path d="M9 10H15"></path><path d="M12 7V13"></path>',
            'notifications' => '<path d="M18 16H6C7.2 14.7 7.7 12.9 7.7 10.5C7.7 7.7 9.5 5.5 12 5.5C14.5 5.5 16.3 7.7 16.3 10.5C16.3 12.9 16.8 14.7 18 16Z"></path><path d="M10 19C10.5 20 11.1 20.5 12 20.5C12.9 20.5 13.5 20 14 19"></path>',
            'messaging' => '<rect x="4" y="5" width="16" height="12" rx="4"></rect><path d="M8 17L5 21V16"></path><path d="M8 10H16"></path><path d="M8 14H13"></path>',
            'billing' => '<rect x="4" y="6" width="16" height="12" rx="3"></rect><path d="M4 10H20"></path><path d="M8 15H11"></path><path d="M14 15H17"></path>',
            'documents' => '<path d="M7 3H14L19 8V21H7V3Z"></path><path d="M14 3V8H19"></path><path d="M10 13H16"></path><path d="M10 17H15"></path>',
            'security' => '<rect x="5" y="10" width="14" height="10" rx="3"></rect><path d="M8 10V7C8 4.8 9.6 3.5 12 3.5C14.4 3.5 16 4.8 16 7V10"></path><path d="M12 14V17"></path>',
            'audit' => '<rect x="6" y="3" width="12" height="18" rx="3"></rect><path d="M9 8H15"></path><path d="M9 13L11 15L15 11"></path>',
            'backup' => '<rect x="5" y="5" width="14" height="14" rx="3"></rect><path d="M8 5V11H16V5"></path><path d="M9 16H15"></path>',
            'appearance' => '<circle cx="12" cy="12" r="8"></circle><path d="M12 4A8 8 0 0 1 12 20"></path>',
            'maintenance' => '<path d="M14.7 6.3L17.7 3.3L20.7 6.3L17.7 9.3Z"></path><path d="M4 20L14 10"></path><path d="M6 18L8 20"></path>',
            'checkbox' => '<rect x="4" y="4" width="16" height="16" rx="5"></rect><path d="M8 12L11 15L16 9"></path>',
            'file' => '<path d="M7 3H14L19 8V21H7V3Z"></path><path d="M14 3V8H19"></path>',
            'number' => '<path d="M9 4L7 20"></path><path d="M17 4L15 20"></path><path d="M5 9H20"></path><path d="M4 15H19"></path>',
            'email' => '<rect x="4" y="6" width="16" height="12" rx="3"></rect><path d="M5 8L12 13L19 8"></path>',
            'url' => '<path d="M10 13A4 4 0 0 0 15.5 13.5L18 11A4 4 0 0 0 12.5 5.5L11.5 6.5"></path><path d="M14 11A4 4 0 0 0 8.5 10.5L6 13A4 4 0 0 0 11.5 18.5L12.5 17.5"></path>',
            'textarea' => '<rect x="4" y="5" width="16" height="14" rx="3"></rect><path d="M8 10H16"></path><path d="M8 14H15"></path>',
            'select' => '<rect x="4" y="6" width="16" height="12" rx="3"></rect><path d="M8 10H16"></path><path d="M8 14H12"></path>',
            'text' => '<rect x="4" y="6" width="16" height="12" rx="3"></rect><path d="M8 10H16"></path><path d="M8 14H13"></path>',
            'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15A1.7 1.7 0 0 0 19.7 13.1L18.8 11.6A1.7 1.7 0 0 1 18.8 10.4L19.7 8.9A1.7 1.7 0 0 0 19.4 7L17 4.6A1.7 1.7 0 0 0 15.1 4.3L13.6 5.2A1.7 1.7 0 0 1 12.4 5.2L10.9 4.3A1.7 1.7 0 0 0 9 4.6L6.6 7A1.7 1.7 0 0 0 6.3 8.9L7.2 10.4A1.7 1.7 0 0 1 7.2 11.6L6.3 13.1A1.7 1.7 0 0 0 6.6 15L9 17.4A1.7 1.7 0 0 0 10.9 17.7L12.4 16.8A1.7 1.7 0 0 1 13.6 16.8L15.1 17.7A1.7 1.7 0 0 0 17 17.4Z"></path>',
        ];

        $path = $paths[$type] ?? $paths['settings'];

        return '
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                ' . $path . '
            </svg>
        ';
    }
}

if (empty($allowedServices)) {
    $allowedServices = [
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
}

$settingFields = isset($settingFields) && is_array($settingFields) ? $settingFields : [];

if (empty($settingFields)) {
    $settingFields = [
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
}

$hourMap = [];

foreach ($clinicHours as $hour) {
    $hourMap[(int) ($hour['day_of_week'] ?? 0)] = $hour;
}

$days = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    0 => 'Sunday',
];

ob_start();
?>

<style>
:root {
    --settings-bg: #f7f8fa;
    --settings-panel: #ffffff;
    --settings-text: #222222;
    --settings-muted: #6b7280;
    --settings-soft: #f3f4f6;
    --settings-line: #e5e7eb;
    --settings-line-strong: #d1d5db;
    --settings-black: #222222;
    --settings-green: #11845b;
    --settings-red: #b42318;
    --settings-teal: #0f766e;
    --settings-teal-soft: #e7f6f4;
    --settings-shadow: 0 16px 46px rgba(15, 23, 42, .08);
}

.admin-settings-page,
.admin-settings-page * {
    box-sizing: border-box;
}

.admin-settings-page {
    min-height: 100vh;
    padding: 22px;
    background: var(--settings-bg);
    color: var(--settings-text);
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.settings-wrap {
    width: min(1200px, 100%);
    margin: 0 auto;
}

.settings-top {
    position: relative;
    top: auto;
    z-index: 1;
    padding: 8px 0 16px;
    background: transparent;
    backdrop-filter: none;
}
.s-top {
    position: relative;
    top: auto;
    z-index: 1;
    background: transparent;
    backdrop-filter: none;
    padding: 8px 0 16px;
}
.settings-header {
    display: flex;
    align-items: center;
    gap: 13px;
    margin-bottom: 14px;
}

.settings-back {
    width: 42px;
    height: 42px;
    border: 1px solid var(--settings-line);
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    color: var(--settings-text);
    text-decoration: none;
    flex: 0 0 auto;
}

.settings-title {
    margin: 0;
    font-size: 28px;
    line-height: 1.1;
    font-weight: 850;
    letter-spacing: -.04em;
}

.settings-description {
    margin: 4px 0 0;
    color: var(--settings-muted);
    font-size: 13.5px;
    line-height: 1.45;
}

.settings-search {
    width: 100%;
    height: 46px;
    border: 1px solid var(--settings-line);
    border-radius: 999px;
    padding: 0 15px;
    background: #ffffff;
    display: flex;
    align-items: center;
    gap: 10px;
}

.settings-search svg {
    color: #9ca3af;
    flex: 0 0 auto;
}

.settings-search input {
    width: 100%;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--settings-text);
    font: inherit;
    font-size: 14px;
}

.settings-flash-wrap {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 14px;
}

.settings-flash {
    display: flex;
    align-items: center;
    gap: 10px;
    border-radius: 16px;
    padding: 13px 15px;
    border: 1px solid var(--settings-line);
    background: #ffffff;
    font-size: 13px;
    font-weight: 750;
}

.settings-flash.success {
    color: var(--settings-green);
    background: #ecfdf3;
    border-color: #bbf7d0;
}

.settings-flash.error {
    color: var(--settings-red);
    background: #fef3f2;
    border-color: #fecaca;
}

.settings-card {
    overflow: hidden;
    border: 1px solid var(--settings-line);
    border-radius: 1px;
    background: #ffffff;
    box-shadow: var(--settings-shadow);
}

.settings-card-head {
    padding: 20px;
    border-bottom: 1px solid var(--settings-line);
    display: flex;
    align-items: center;
    gap: 14px;
}

.settings-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    background: var(--settings-teal-soft);
    color: var(--settings-teal);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}

.settings-card-title {
    font-size: 18px;
    font-weight: 850;
    letter-spacing: -.02em;
}

.settings-card-subtitle {
    margin-top: 3px;
    color: var(--settings-muted);
    font-size: 13px;
    line-height: 1.45;
}

.settings-section {
    padding: 18px 20px 22px;
}

.settings-heading {
    margin: 0;
    font-size: 18px;
    font-weight: 850;
}

.settings-subheading {
    margin: 5px 0 16px;
    color: var(--settings-muted);
    font-size: 13px;
    line-height: 1.5;
}

.settings-list {
    display: flex;
    flex-direction: column;
    border-top: 1px solid var(--settings-line);
}

.settings-row {
    min-height: 72px;
    width: 100%;
    padding: 14px 0;
    border: 0;
    border-bottom: 1px solid var(--settings-line);
    background: transparent;
    color: inherit;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 13px;
}

.settings-row:hover .settings-row-title {
    color: var(--settings-teal);
}

.settings-row-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    background: var(--settings-soft);
    color: #374151;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}

.settings-row-main {
    min-width: 0;
    flex: 1 1 auto;
}

.settings-row-title {
    display: block;
    color: var(--settings-text);
    font-size: 14.5px;
    font-weight: 850;
    line-height: 1.3;
    transition: color .16s ease;
}

.settings-row-description {
    display: block;
    margin-top: 3px;
    color: var(--settings-muted);
    font-size: 12.5px;
    line-height: 1.45;
}

.settings-row-value {
    max-width: 240px;
    color: var(--settings-muted);
    font-size: 12px;
    font-weight: 750;
    line-height: 1.35;
    text-align: right;
}

.settings-arrow {
    color: #9ca3af;
    flex: 0 0 auto;
}

.settings-pill {
    min-height: 28px;
    padding: 0 11px;
    border-radius: 999px;
    background: var(--settings-soft);
    color: #4b5563;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

.settings-pill.green {
    background: #ecfdf3;
    color: var(--settings-green);
}

.settings-pill.red {
    background: #fef3f2;
    color: var(--settings-red);
}

.settings-switch {
    width: 44px;
    height: 26px;
    padding: 3px;
    border-radius: 999px;
    background: #eef0f3;
    display: inline-flex;
    align-items: center;
    justify-content: flex-start;
    flex: 0 0 auto;
    transition: background .18s ease;
}

.settings-switch::after {
    content: "";
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .18);
    transition: transform .18s ease;
}

.settings-switch.is-on {
    background: var(--settings-black);
}

.settings-switch.is-on::after {
    transform: translateX(18px);
}

.real-toggle-input {
    display: none;
}

.real-toggle {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    user-select: none;
}

.real-toggle-track {
    width: 44px;
    height: 26px;
    padding: 3px;
    border-radius: 999px;
    background: #eef0f3;
    display: inline-flex;
    align-items: center;
    justify-content: flex-start;
    transition: background .18s ease;
}

.real-toggle-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .18);
    transition: transform .18s ease;
}

.real-toggle-input:checked + .real-toggle-track {
    background: var(--settings-black);
}

.real-toggle-input:checked + .real-toggle-track .real-toggle-thumb {
    transform: translateX(18px);
}

.real-toggle-text {
    min-width: 44px;
    color: var(--settings-muted);
    font-size: 12px;
    font-weight: 800;
}

.settings-input,
.settings-textarea,
.settings-file {
    width: 100%;
    border: 1px solid var(--settings-line-strong);
    border-radius: 14px;
    background: #ffffff;
    color: var(--settings-text);
    font: inherit;
    font-size: 14px;
    outline: none;
    transition: border-color .16s ease, box-shadow .16s ease;
}

.settings-input,
.settings-file {
    min-height: 44px;
    padding: 0 13px;
}

.settings-textarea {
    min-height: 96px;
    padding: 12px 13px;
    resize: vertical;
}

.settings-input:focus,
.settings-textarea:focus,
.settings-file:focus {
    border-color: var(--settings-black);
    box-shadow: 0 0 0 4px rgba(34, 34, 34, .07);
}

.settings-button {
    min-height: 42px;
    border: 1px solid transparent;
    border-radius: 999px;
    padding: 0 17px;
    background: transparent;
    color: inherit;
    font: inherit;
    font-size: 13px;
    font-weight: 850;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: transform .16s ease, background .16s ease, border-color .16s ease;
}

.settings-button:active {
    transform: scale(.98);
}

.settings-button.primary {
    background: var(--settings-black);
    color: #ffffff;
}

.settings-button.primary:hover {
    background: #000000;
}

.settings-button.ghost {
    background: #ffffff;
    color: var(--settings-text);
    border-color: var(--settings-line-strong);
}

.settings-button.ghost:hover {
    background: #f9fafb;
}

.settings-button.small {
    min-height: 36px;
    padding: 0 13px;
    font-size: 12px;
}

.settings-save-bar {
    margin-top: 18px;
    padding-top: 18px;
    border-top: 1px solid var(--settings-line);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.settings-help {
    color: var(--settings-muted);
    font-size: 12px;
    line-height: 1.45;
}

.hours-list {
    border-top: 1px solid var(--settings-line);
    display: flex;
    flex-direction: column;
}

.hour-row {
    padding: 16px 0;
    border-bottom: 1px solid var(--settings-line);
    display: flex;
    align-items: flex-start;
    gap: 14px;
}

.hour-day {
    width: 120px;
    padding-top: 4px;
    font-size: 14px;
    font-weight: 850;
    flex: 0 0 auto;
}

.hour-times {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.time-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.time-label {
    width: 82px;
    color: var(--settings-muted);
    font-size: 12px;
    font-weight: 800;
}

.service-stats {
    margin-bottom: 16px;
    border-top: 1px solid var(--settings-line);
    border-bottom: 1px solid var(--settings-line);
    display: flex;
    flex-direction: column;
}

.service-stat {
    padding: 13px 0;
    border-bottom: 1px solid var(--settings-line);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.service-stat:last-child {
    border-bottom: 0;
}

.service-stat-label {
    color: var(--settings-muted);
    font-size: 13px;
    font-weight: 800;
}

.service-stat-number {
    font-size: 20px;
    font-weight: 900;
}

.service-toolbar {
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.service-list {
    border-top: 1px solid var(--settings-line);
    display: flex;
    flex-direction: column;
}

.service-item {
    border-bottom: 1px solid var(--settings-line);
}

.service-head {
    width: 100%;
    padding: 16px 0;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 13px;
}

.service-thumb {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    background: var(--settings-teal-soft);
    color: var(--settings-teal);
    overflow: hidden;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}

.service-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.service-main {
    min-width: 0;
    flex: 1 1 auto;
}

.service-name {
    color: var(--settings-text);
    font-size: 14.5px;
    font-weight: 900;
    line-height: 1.3;
}

.service-description {
    margin-top: 3px;
    color: var(--settings-muted);
    font-size: 12.5px;
    line-height: 1.45;
}

.service-meta {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
    flex-wrap: wrap;
}

.service-chip {
    min-height: 26px;
    padding: 0 10px;
    border-radius: 999px;
    background: var(--settings-soft);
    color: #4b5563;
    display: inline-flex;
    align-items: center;
    font-size: 11.5px;
    font-weight: 850;
    white-space: nowrap;
}

.service-chip.teal {
    background: var(--settings-teal-soft);
    color: var(--settings-teal);
}

.service-chip.green {
    background: #ecfdf3;
    color: var(--settings-green);
}

.service-chip.red {
    background: #fef3f2;
    color: var(--settings-red);
}

.service-chevron {
    color: #9ca3af;
    transition: transform .18s ease;
}

.service-chevron.open {
    transform: rotate(180deg);
}

.service-edit {
    display: none;
    padding: 0 0 18px 61px;
}

.service-edit.open {
    display: block;
}

.form-list {
    display: flex;
    flex-direction: column;
    gap: 13px;
}

.form-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.form-label {
    color: var(--settings-text);
    font-size: 12.5px;
    font-weight: 850;
}

.empty-state {
    padding: 18px;
    border: 1px dashed var(--settings-line-strong);
    border-radius: 18px;
    background: #f9fafb;
    color: var(--settings-muted);
    font-size: 13px;
    font-weight: 750;
    text-align: center;
}

.drawer-overlay {
    position: fixed;
    inset: 0;
    z-index: 100;
    padding: 18px;
    background: rgba(15, 23, 42, .38);
    backdrop-filter: blur(8px);
    display: none;
}

.drawer-overlay.open {
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.drawer {
    width: min(680px, 100%);
    max-height: 92vh;
    overflow: auto;
    border-radius: 28px 28px 0 0;
    background: #ffffff;
    box-shadow: 0 -18px 60px rgba(15, 23, 42, .22);
}

.drawer-head {
    padding: 18px 20px;
    border-bottom: 1px solid var(--settings-line);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.drawer-title {
    font-size: 17px;
    font-weight: 900;
}

.drawer-close {
    width: 38px;
    height: 38px;
    border: 1px solid var(--settings-line);
    border-radius: 999px;
    background: #ffffff;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.drawer-body {
    padding: 18px 20px;
}

.drawer-foot {
    padding: 16px 20px;
    border-top: 1px solid var(--settings-line);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

@media (max-width: 720px) {
    .admin-settings-page {
        padding: 14px;
    }

    .settings-title {
        font-size: 24px;
    }

    .settings-card {
        border-radius: 22px;
    }

    .settings-card-head,
    .settings-section {
        padding-left: 16px;
        padding-right: 16px;
    }

    .settings-row {
        align-items: flex-start;
    }

    .settings-row-value {
        display: none;
    }

    .hour-row {
        flex-direction: column;
    }

    .hour-day {
        width: auto;
    }

    .time-row {
        width: 100%;
    }

    .time-label {
        width: 78px;
    }

    .service-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .service-head {
        align-items: flex-start;
    }

    .service-meta {
        display: none;
    }

    .service-edit {
        padding-left: 0;
    }

    .settings-save-bar {
        align-items: stretch;
        flex-direction: column;
    }

    .settings-save-bar .settings-button {
        width: 100%;
    }

    .drawer-foot {
        flex-direction: column;
    }

    .drawer-foot .settings-button {
        width: 100%;
    }
}
</style>

<div class="admin-settings-page">
    <div class="settings-wrap">
        <div class="settings-top">
            <div class="settings-header">
                <a href="<?= admin_setting_e($baseUrl . '/admin/settings') ?>" class="settings-back" aria-label="Back to settings">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15 18L9 12L15 6"></path>
                    </svg>
                </a>

                <div>
                    <h1 class="settings-title"><?= admin_setting_e($section['title'] ?? 'Settings') ?></h1>
                    <p class="settings-description"><?= admin_setting_e($section['description'] ?? 'Manage this admin settings section.') ?></p>
                </div>
            </div>

            <?php if ($sectionKey !== 'clinic-hours'): ?>
                <label class="settings-search" for="<?= $sectionKey === 'services' ? 'serviceSearch' : 'sectionSearch' ?>">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="M20 20L16.6 16.6"></path>
                    </svg>
                    <input
                        type="search"
                        id="<?= $sectionKey === 'services' ? 'serviceSearch' : 'sectionSearch' ?>"
                        placeholder="<?= $sectionKey === 'services' ? 'Search services' : 'Search settings' ?>"
                        autocomplete="off"
                    >
                </label>
            <?php endif; ?>
        </div>

        <?php if ($flash_success || $flash_error): ?>
            <div class="settings-flash-wrap">
                <?php if ($flash_success): ?>
                    <div class="settings-flash success">
                        <?= admin_simple_icon('checkbox') ?>
                        <span><?= admin_setting_e($flash_success) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($flash_error): ?>
                    <div class="settings-flash error">
                        <?= admin_simple_icon('settings') ?>
                        <span><?= admin_setting_e($flash_error) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section class="settings-card">
            <div class="settings-card-head">
                <div class="settings-card-icon">
                    <?= admin_simple_icon($sectionKey) ?>
                </div>

                <div>
                    <div class="settings-card-title"><?= admin_setting_e($section['title'] ?? 'Settings') ?></div>
                    <div class="settings-card-subtitle"><?= admin_setting_e($section['description'] ?? 'Simple and user-friendly admin controls.') ?></div>
                </div>
            </div>

            <div class="settings-section">
                <?php if ($sectionKey === 'clinic-hours'): ?>
                    <h2 class="settings-heading">Clinic Hours</h2>
                    <p class="settings-subheading">Check open days, then set opening, closing, and break hours.</p>

                    <form method="POST" action="<?= admin_setting_e($baseUrl . '/admin/settings/clinic-hours') ?>">
                        <?= Csrf::inputField(); ?>

                        <div class="hours-list">
                            <?php foreach ($days as $dayIndex => $dayLabel): ?>
                                <?php
                                    $row = $hourMap[$dayIndex] ?? [];
                                    $isOpen = (int) ($row['is_open'] ?? 0) === 1;
                                    $uid = 'open_day_' . $dayIndex;
                                ?>

                                <div class="hour-row">
                                    <div class="hour-day"><?= admin_setting_e($dayLabel) ?></div>

                                    <label class="settings-check" for="<?= admin_setting_e($uid) ?>">
                                        <input
                                            type="checkbox"
                                            id="<?= admin_setting_e($uid) ?>"
                                            class="settings-check-input js-status-check"
                                            name="days[<?= (int) $dayIndex ?>][is_open]"
                                            value="1"
                                            data-on="Open"
                                            data-off="Closed"
                                            <?= $isOpen ? 'checked' : '' ?>
                                        >
                                        <span class="settings-check-box"></span>
                                        <span class="settings-pill <?= $isOpen ? 'green' : 'red' ?> js-status-pill">
                                            <?= $isOpen ? 'Open' : 'Closed' ?>
                                        </span>
                                    </label>

                                    <div class="hour-times">
                                        <div class="time-row">
                                            <span class="time-label">Opens</span>
                                            <input
                                                type="time"
                                                class="settings-input"
                                                name="days[<?= (int) $dayIndex ?>][opening_time]"
                                                value="<?= admin_setting_e(substr((string) ($row['opening_time'] ?? ''), 0, 5)) ?>"
                                            >
                                        </div>

                                        <div class="time-row">
                                            <span class="time-label">Closes</span>
                                            <input
                                                type="time"
                                                class="settings-input"
                                                name="days[<?= (int) $dayIndex ?>][closing_time]"
                                                value="<?= admin_setting_e(substr((string) ($row['closing_time'] ?? ''), 0, 5)) ?>"
                                            >
                                        </div>

                                        <div class="time-row">
                                            <span class="time-label">Break Start</span>
                                            <input
                                                type="time"
                                                class="settings-input"
                                                name="days[<?= (int) $dayIndex ?>][break_start]"
                                                value="<?= admin_setting_e(substr((string) ($row['break_start'] ?? ''), 0, 5)) ?>"
                                            >
                                        </div>

                                        <div class="time-row">
                                            <span class="time-label">Break End</span>
                                            <input
                                                type="time"
                                                class="settings-input"
                                                name="days[<?= (int) $dayIndex ?>][break_end]"
                                                value="<?= admin_setting_e(substr((string) ($row['break_end'] ?? ''), 0, 5)) ?>"
                                            >
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="settings-save-bar">
                            <button type="submit" class="settings-button primary">
                                Save Clinic Hours
                            </button>

                            <span class="settings-help">Changes apply immediately.</span>
                        </div>
                    </form>

                <?php elseif ($sectionKey === 'services'): ?>
                    <?php
                        $totalSvcs = count($services);
                        $activeSvcs = count(array_filter($services, static function ($service): bool {
                            return (int) ($service['is_active'] ?? 1) === 1;
                        }));
                    ?>

                    <datalist id="serviceNameSuggestions">
                        <?php foreach ($allowedServices as $serviceNameSuggestion): ?>
                            <option value="<?= admin_setting_e((string) $serviceNameSuggestion) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>

                    <h2 class="settings-heading">Dental Services</h2>
                    <p class="settings-subheading">Add, edit, activate, disable, or archive services used in appointment booking.</p>

                    <div class="service-stats">
                        <div class="service-stat">
                            <span class="service-stat-label">Total Services</span>
                            <span class="service-stat-number"><?= (int) $totalSvcs ?></span>
                        </div>

                        <div class="service-stat">
                            <span class="service-stat-label">Active Services</span>
                            <span class="service-stat-number"><?= (int) $activeSvcs ?></span>
                        </div>

                        <div class="service-stat">
                            <span class="service-stat-label">Inactive Services</span>
                            <span class="service-stat-number"><?= (int) ($totalSvcs - $activeSvcs) ?></span>
                        </div>
                    </div>

                    <div class="service-toolbar">
                        <button type="button" class="settings-button primary" id="openAddService">
                            <?= admin_simple_icon('services') ?>
                            Add Service
                        </button>
                    </div>

                    <div class="service-list" id="servicesList">
                        <?php if (empty($services)): ?>
                            <div class="empty-state">No services configured yet. Click Add Service to get started.</div>
                        <?php endif; ?>

                        <?php foreach ($services as $service): ?>
                            <?php
                                $serviceId = (int) ($service['service_id'] ?? 0);
                                $isActive = (int) ($service['is_active'] ?? 1) === 1;
                                $svcName = (string) ($service['service_name'] ?? '');
                                $svcDesc = (string) ($service['description'] ?? '');
                                $svcDur = (int) ($service['estimated_duration_minutes'] ?? 30);
                                $svcPrice = (float) ($service['estimated_price'] ?? 0);
                                $svcOrder = (int) ($service['display_order'] ?? 0);
                                $svcImg = (string) ($service['service_image'] ?? '');
                            ?>

                            <div class="service-item" data-service-name="<?= admin_setting_e(strtolower($svcName . ' ' . $svcDesc)) ?>">
                                <div class="service-head" data-expand-service="<?= $serviceId ?>">
                                    <div class="service-thumb">
                                        <?php if (trim($svcImg) !== ''): ?>
                                            <img src="<?= admin_setting_e($baseUrl . '/' . ltrim($svcImg, '/')) ?>" alt="">
                                        <?php else: ?>
                                            <?= admin_simple_icon('services') ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="service-main">
                                        <div class="service-name"><?= admin_setting_e($svcName) ?></div>

                                        <?php if (trim($svcDesc) !== ''): ?>
                                            <div class="service-description"><?= admin_setting_e($svcDesc) ?></div>
                                        <?php else: ?>
                                            <div class="service-description">No description added.</div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="service-meta">
                                        <span class="service-chip teal">PHP <?= number_format($svcPrice, 2) ?></span>
                                        <span class="service-chip"><?= $svcDur ?> min</span>
                                        <span class="service-chip <?= $isActive ? 'green' : 'red' ?>">
                                            <?= $isActive ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>

                                    <form
                                        method="POST"
                                        action="<?= admin_setting_e($baseUrl . '/admin/settings/services/status') ?>"
                                        onsubmit="return confirm('Update service status?');"
                                    >
                                        <?= Csrf::inputField(); ?>
                                        <input type="hidden" name="service_id" value="<?= $serviceId ?>">
                                        <input type="hidden" name="is_active" value="<?= $isActive ? 0 : 1 ?>">

                                        <button type="submit" class="settings-button small <?= $isActive ? 'ghost' : 'primary' ?>">
                                            <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <?php if (!$isActive): ?>
                                        <form
                                            method="POST"
                                            action="<?= admin_setting_e($baseUrl . '/admin/settings/services/archive') ?>"
                                            onsubmit="return confirm('Move this deactivated service to the archive?');"
                                        >
                                            <?= Csrf::inputField(); ?>
                                            <input type="hidden" name="service_id" value="<?= $serviceId ?>">
                                            <button type="submit" class="settings-button small ghost">Archive</button>
                                        </form>
                                    <?php endif; ?>

                                    <svg class="service-chevron" id="chevron<?= $serviceId ?>" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M6 9L12 15L18 9"></path>
                                    </svg>
                                </div>

                                <div class="service-edit" id="serviceEdit<?= $serviceId ?>">
                                    <form
                                        method="POST"
                                        enctype="multipart/form-data"
                                        action="<?= admin_setting_e($baseUrl . '/admin/settings/services/update') ?>"
                                    >
                                        <?= Csrf::inputField(); ?>
                                        <input type="hidden" name="service_id" value="<?= $serviceId ?>">

                                        <div class="form-list">
                                            <div class="form-field">
                                                <label class="form-label" for="sn_<?= $serviceId ?>">Service Name</label>
                                                <input
                                                    id="sn_<?= $serviceId ?>"
                                                    type="text"
                                                    name="service_name"
                                                    class="settings-input"
                                                    value="<?= admin_setting_e($svcName) ?>"
                                                    list="serviceNameSuggestions"
                                                    maxlength="150"
                                                    required
                                                >
                                            </div>

                                            <div class="form-field">
                                                <label class="form-label" for="dur_<?= $serviceId ?>">Duration in minutes</label>
                                                <input
                                                    id="dur_<?= $serviceId ?>"
                                                    type="number"
                                                    name="estimated_duration_minutes"
                                                    class="settings-input"
                                                    min="5"
                                                    step="5"
                                                    value="<?= $svcDur ?>"
                                                    required
                                                >
                                            </div>

                                            <div class="form-field">
                                                <label class="form-label" for="pr_<?= $serviceId ?>">Base Price</label>
                                                <input
                                                    id="pr_<?= $serviceId ?>"
                                                    type="number"
                                                    name="estimated_price"
                                                    class="settings-input"
                                                    min="0"
                                                    step="0.01"
                                                    value="<?= number_format($svcPrice, 2, '.', '') ?>"
                                                    required
                                                >
                                            </div>

                                            <div class="form-field">
                                                <label class="form-label" for="do_<?= $serviceId ?>">Display Order</label>
                                                <input
                                                    id="do_<?= $serviceId ?>"
                                                    type="number"
                                                    name="display_order"
                                                    class="settings-input"
                                                    min="0"
                                                    step="1"
                                                    value="<?= $svcOrder ?>"
                                                >
                                            </div>

                                            <div class="form-field">
                                                <label class="form-label" for="desc_<?= $serviceId ?>">Description</label>
                                                <textarea id="desc_<?= $serviceId ?>" name="description" class="settings-textarea"><?= admin_setting_e($svcDesc) ?></textarea>
                                            </div>

                                            <div class="form-field">
                                                <label class="form-label">Replace Image</label>
                                                <input type="file" name="service_image" class="settings-file" accept=".jpg,.jpeg,.png,.webp">
                                            </div>

                                            <div class="form-field">
                                                <label class="settings-check">
                                                    <input
                                                        type="checkbox"
                                                        class="settings-check-input js-status-check"
                                                        name="is_active"
                                                        value="1"
                                                        data-on="Active"
                                                        data-off="Inactive"
                                                        <?= $isActive ? 'checked' : '' ?>
                                                    >
                                                    <span class="settings-check-box"></span>
                                                    <span class="settings-pill <?= $isActive ? 'green' : 'red' ?> js-status-pill">
                                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </label>
                                                <span class="settings-help">Active services are visible to patients during booking.</span>
                                            </div>

                                            <div class="settings-save-bar">
                                                <button type="button" class="settings-button ghost" data-collapse-service="<?= $serviceId ?>">Cancel</button>
                                                <button type="submit" class="settings-button primary">Save Changes</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <section class="settings-card" style="margin-top:18px;">
                        <div class="settings-card-head">
                            <div>
                                <div class="settings-card-title">Archived Services</div>
                                <div class="settings-card-subtitle">Restore a service to make it available again, or permanently delete it if it has no linked records.</div>
                            </div>
                        </div>

                        <div class="settings-section">
                            <?php if (empty($archivedServices)): ?>
                                <div class="empty-state">No archived services.</div>
                            <?php else: ?>
                                <div class="service-list">
                                    <?php foreach ($archivedServices as $archivedService): ?>
                                        <?php $archivedId = (int) ($archivedService['service_id'] ?? 0); ?>
                                        <div class="service-item">
                                            <div class="service-head">
                                                <div class="service-main">
                                                    <div class="service-name"><?= admin_setting_e((string) ($archivedService['service_name'] ?? '')) ?></div>
                                                    <div class="service-description">Archived <?= admin_setting_e((string) ($archivedService['archived_at'] ?? '')) ?></div>
                                                </div>
                                                <form method="POST" action="<?= admin_setting_e($baseUrl . '/admin/settings/services/restore') ?>">
                                                    <?= Csrf::inputField(); ?>
                                                    <input type="hidden" name="service_id" value="<?= $archivedId ?>">
                                                    <button type="submit" class="settings-button small primary">Restore</button>
                                                </form>
                                                <form method="POST" action="<?= admin_setting_e($baseUrl . '/admin/settings/services/delete') ?>" onsubmit="return confirm('Permanently delete this archived service? This cannot be undone.');">
                                                    <?= Csrf::inputField(); ?>
                                                    <input type="hidden" name="service_id" value="<?= $archivedId ?>">
                                                    <button type="submit" class="settings-button small ghost">Delete Permanently</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <div class="drawer-overlay" id="addServiceOverlay" role="dialog" aria-modal="true" aria-label="Add Service">
                        <div class="drawer">
                            <div class="drawer-head">
                                <div class="drawer-title">Add New Service</div>

                                <button type="button" class="drawer-close" id="closeAddService" aria-label="Close">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M18 6L6 18"></path>
                                        <path d="M6 6L18 18"></path>
                                    </svg>
                                </button>
                            </div>

                            <form
                                method="POST"
                                enctype="multipart/form-data"
                                action="<?= admin_setting_e($baseUrl . '/admin/settings/services/store') ?>"
                            >
                                <?= Csrf::inputField(); ?>

                                <div class="drawer-body">
                                    <div class="form-list">
                                        <div class="form-field">
                                            <label class="form-label" for="add_sn">Service Name</label>
                                            <input
                                                id="add_sn"
                                                type="text"
                                                name="service_name"
                                                class="settings-input"
                                                list="serviceNameSuggestions"
                                                maxlength="150"
                                                required
                                                placeholder="Example: Dental Consultation"
                                            >
                                            <span class="settings-help">Choose a suggestion or type your own custom service name.</span>
                                        </div>

                                        <div class="form-field">
                                            <label class="form-label" for="add_dur">Duration in minutes</label>
                                            <input
                                                id="add_dur"
                                                type="number"
                                                name="estimated_duration_minutes"
                                                class="settings-input"
                                                min="5"
                                                step="5"
                                                value="30"
                                                required
                                            >
                                        </div>

                                        <div class="form-field">
                                            <label class="form-label" for="add_pr">Base Price</label>
                                            <input
                                                id="add_pr"
                                                type="number"
                                                name="estimated_price"
                                                class="settings-input"
                                                min="0"
                                                step="0.01"
                                                value="0.00"
                                                required
                                            >
                                        </div>

                                        <div class="form-field">
                                            <label class="form-label" for="add_order">Display Order</label>
                                            <input
                                                id="add_order"
                                                type="number"
                                                name="display_order"
                                                class="settings-input"
                                                min="0"
                                                step="1"
                                                value="0"
                                            >
                                        </div>

                                        <div class="form-field">
                                            <label class="form-label">Service Image</label>
                                            <input type="file" name="service_image" class="settings-file" accept=".jpg,.jpeg,.png,.webp">
                                        </div>

                                        <div class="form-field">
                                            <label class="form-label" for="add_desc">Description</label>
                                            <textarea
                                                id="add_desc"
                                                name="description"
                                                class="settings-textarea"
                                                placeholder="Briefly describe this service."
                                            ></textarea>
                                        </div>

                                        <div class="form-field">
                                            <label class="settings-check">
                                                <input
                                                    type="checkbox"
                                                    class="settings-check-input js-status-check"
                                                    name="is_active"
                                                    value="1"
                                                    data-on="Active"
                                                    data-off="Inactive"
                                                    checked
                                                >
                                                <span class="settings-check-box"></span>
                                                <span class="settings-pill green js-status-pill">Active</span>
                                            </label>
                                            <span class="settings-help">Active services are visible to patients during booking.</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="drawer-foot">
                                    <button type="button" class="settings-button ghost" id="cancelAddService">Cancel</button>
                                    <button type="submit" class="settings-button primary">Add Service</button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <?php $sectionFields = $settingFields[$sectionKey] ?? []; ?>

                    <h2 class="settings-heading">Settings List</h2>
                    <p class="settings-subheading">Tap a setting to view or update it. Current status is shown on the right.</p>

                    <?php if (empty($sectionFields)): ?>
                        <div class="empty-state">No settings configured for this section yet.</div>
                    <?php else: ?>
                        <div class="settings-list" id="fieldList">
                            <?php foreach ($sectionFields as $fieldKey => $field): ?>
                                <?php
                                    $group = (string) ($field['group'] ?? '');
                                    $label = (string) ($field['label'] ?? $fieldKey);
                                    $desc = (string) ($field['description'] ?? '');
                                    $type = (string) ($field['type'] ?? 'text');
                                    $default = (string) ($field['default'] ?? '');

                                    $value = $group !== ''
                                        ? admin_setting_value($settings, $group, (string) $fieldKey, $default)
                                        : '';

                                    $isEnabled = $type === 'checkbox' && admin_setting_is_enabled($value);

                                    if ($type === 'checkbox') {
                                        $statusText = $isEnabled ? 'Enabled' : 'Disabled';
                                        $valueClass = $isEnabled ? 'green' : 'red';
                                    } elseif ($type === 'file') {
                                        $statusText = trim($value) !== '' ? 'File uploaded' : 'No file';
                                        $valueClass = '';
                                    } else {
                                        $statusText = admin_setting_short_value($value);
                                        $valueClass = '';
                                    }

                                    $editUrl = $baseUrl . '/admin/settings/field?section=' . urlencode($sectionKey) . '&field=' . urlencode((string) $fieldKey);
                                    $searchText = strtolower($label . ' ' . $desc . ' ' . $statusText . ' ' . $type);
                                ?>

                                <a
                                    href="<?= admin_setting_e($editUrl) ?>"
                                    class="settings-row"
                                    data-field-search="<?= admin_setting_e($searchText) ?>"
                                >
                                    <span class="settings-row-icon">
                                        <?= admin_simple_icon($type) ?>
                                    </span>

                                    <span class="settings-row-main">
                                        <span class="settings-row-title"><?= admin_setting_e($label) ?></span>
                                        <span class="settings-row-description"><?= admin_setting_e($desc) ?></span>
                                    </span>

                                    <span class="settings-row-value">
                                        <span class="settings-pill <?= admin_setting_e($valueClass) ?>">
                                            <?= admin_setting_e($statusText) ?>
                                        </span>
                                    </span>

                                    <span class="settings-arrow" aria-hidden="true">
                                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                                            <path d="M9 18L15 12L9 6"></path>
                                        </svg>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    'use strict';

    document.querySelectorAll('.js-status-check').forEach(function (checkbox) {
        var wrapper = checkbox.closest('.settings-check');
        var pill = wrapper ? wrapper.querySelector('.js-status-pill') : null;

        if (!pill) {
            return;
        }

        checkbox.addEventListener('change', function () {
            var onText = checkbox.getAttribute('data-on') || 'Enabled';
            var offText = checkbox.getAttribute('data-off') || 'Disabled';

            pill.textContent = checkbox.checked ? onText : offText;
            pill.classList.toggle('green', checkbox.checked);
            pill.classList.toggle('red', !checkbox.checked);
        });
    });

    document.addEventListener('click', function (event) {
        var serviceHead = event.target.closest('[data-expand-service]');

        if (serviceHead && !event.target.closest('form') && !event.target.closest('button')) {
            var serviceId = serviceHead.getAttribute('data-expand-service');
            var panel = document.getElementById('serviceEdit' + serviceId);
            var chevron = document.getElementById('chevron' + serviceId);

            if (panel) {
                panel.classList.toggle('open');
            }

            if (chevron) {
                chevron.classList.toggle('open');
            }

            return;
        }

        var collapse = event.target.closest('[data-collapse-service]');

        if (collapse) {
            var collapseId = collapse.getAttribute('data-collapse-service');
            var collapsePanel = document.getElementById('serviceEdit' + collapseId);
            var collapseChevron = document.getElementById('chevron' + collapseId);

            if (collapsePanel) {
                collapsePanel.classList.remove('open');
            }

            if (collapseChevron) {
                collapseChevron.classList.remove('open');
            }

            return;
        }

        if (event.target.closest('#openAddService')) {
            var overlay = document.getElementById('addServiceOverlay');

            if (overlay) {
                overlay.classList.add('open');
                document.body.style.overflow = 'hidden';
            }

            return;
        }

        if (event.target.closest('#closeAddService') || event.target.closest('#cancelAddService')) {
            var closeOverlay = document.getElementById('addServiceOverlay');

            if (closeOverlay) {
                closeOverlay.classList.remove('open');
                document.body.style.overflow = '';
            }

            return;
        }

        if (event.target.id === 'addServiceOverlay') {
            event.target.classList.remove('open');
            document.body.style.overflow = '';
        }
    });

    var serviceSearch = document.getElementById('serviceSearch');

    if (serviceSearch) {
        serviceSearch.addEventListener('input', function () {
            var query = this.value.toLowerCase().trim();

            document.querySelectorAll('.service-item').forEach(function (card) {
                var name = card.getAttribute('data-service-name') || '';
                card.style.display = (!query || name.includes(query)) ? '' : 'none';
            });
        });
    }

    var sectionSearch = document.getElementById('sectionSearch');

    if (sectionSearch) {
        sectionSearch.addEventListener('input', function () {
            var query = this.value.toLowerCase().trim();

            document.querySelectorAll('[data-field-search]').forEach(function (row) {
                var haystack = row.getAttribute('data-field-search') || '';
                row.style.display = (!query || haystack.includes(query)) ? '' : 'none';
            });
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        var overlay = document.getElementById('addServiceOverlay');

        if (overlay && overlay.classList.contains('open')) {
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }
    });
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';