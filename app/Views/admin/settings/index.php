<?php
$pageTitle = 'System Settings';
$baseUrl   = '/DentalClinic/public';

$settings    = isset($settings) && is_array($settings) ? $settings : [];
$clinicHours = isset($clinicHours) && is_array($clinicHours) ? $clinicHours : [];
$services    = isset($services) && is_array($services) ? $services : [];

$flash_success = $flash_success ?? null;
$flash_error   = $flash_error ?? null;

if (!function_exists('settings_e')) {
    function settings_e(?string $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('settings_raw_value')) {
    function settings_raw_value(array $s, string $g, string $k, string $d = ''): string
    {
        $v = $s[$g][$k] ?? $d;

        if (is_array($v)) {
            $v = $v['setting_value'] ?? $v['value'] ?? $v['setting_text'] ?? $d;
        }

        return (string) $v;
    }
}

if (!function_exists('settings_bool_on')) {
    function settings_bool_on(array $s, string $g, string $k): bool
    {
        return in_array(
            strtolower(trim(settings_raw_value($s, $g, $k, '0'))),
            ['1', 'yes', 'true', 'on', 'enabled'],
            true
        );
    }
}

if (!function_exists('settings_bool_text')) {
    function settings_bool_text(array $s, string $g, string $k): string
    {
        return settings_bool_on($s, $g, $k) ? 'Enabled' : 'Disabled';
    }
}

if (!function_exists('settings_short_text')) {
    function settings_short_text(string $value, int $limit = 42): string
    {
        $value = trim($value);

        if ($value === '') {
            return 'Not set';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value) <= $limit
                ? $value
                : mb_substr($value, 0, $limit - 3) . '...';
        }

        return strlen($value) <= $limit
            ? $value
            : substr($value, 0, $limit - 3) . '...';
    }
}

if (!function_exists('settings_icon')) {
    function settings_icon(string $key): string
    {
        $key = strtolower($key);

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
            'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15A1.7 1.7 0 0 0 19.7 13.1L18.8 11.6A1.7 1.7 0 0 1 18.8 10.4L19.7 8.9A1.7 1.7 0 0 0 19.4 7L17 4.6A1.7 1.7 0 0 0 15.1 4.3L13.6 5.2A1.7 1.7 0 0 1 12.4 5.2L10.9 4.3A1.7 1.7 0 0 0 9 4.6L6.6 7A1.7 1.7 0 0 0 6.3 8.9L7.2 10.4A1.7 1.7 0 0 1 7.2 11.6L6.3 13.1A1.7 1.7 0 0 0 6.6 15L9 17.4A1.7 1.7 0 0 0 10.9 17.7L12.4 16.8A1.7 1.7 0 0 1 13.6 16.8L15.1 17.7A1.7 1.7 0 0 0 17 17.4Z"></path>',
        ];

        $path = $paths[$key] ?? $paths['settings'];

        return '
            <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.15" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                ' . $path . '
            </svg>
        ';
    }
}

$configuredSettings = 0;

foreach ($settings as $groupRows) {
    if (!is_array($groupRows)) {
        continue;
    }

    foreach ($groupRows as $v) {
        if (is_array($v)) {
            $v = $v['setting_value'] ?? $v['value'] ?? '';
        }

        if (trim((string) $v) !== '') {
            $configuredSettings++;
        }
    }
}

$openDays = 0;

foreach ($clinicHours as $h) {
    if ((int) ($h['is_open'] ?? 0) === 1) {
        $openDays++;
    }
}

$activeServices = 0;

foreach ($services as $sv) {
    if ((int) ($sv['is_active'] ?? 1) === 1) {
        $activeServices++;
    }
}

$clinicName = settings_raw_value($settings, 'clinic_profile', 'clinic_name', 'Dental Clinic');

$sections = [
    'clinic-profile' => [
        'title' => 'Clinic Profile',
        'description' => 'Manage clinic name, logo, address, contact details, and about information.',
        'meta' => $clinicName,
        'group' => 'core',
        'keywords' => 'clinic name logo address phone email owner about contact profile',
    ],
    'public-website' => [
        'title' => 'Public Website',
        'description' => 'Update homepage content, hero text, gallery visibility, and public call-to-action.',
        'meta' => 'Homepage content',
        'group' => 'core',
        'keywords' => 'homepage hero website gallery public about cta',
    ],
    'appointment-rules' => [
        'title' => 'Appointment Rules',
        'description' => 'Control online booking, guest booking, appointment approval, and cancellation rules.',
        'meta' => 'Booking: ' . settings_bool_text($settings, 'appointment_rules', 'enable_online_booking'),
        'group' => 'core',
        'keywords' => 'appointment booking guest approval cancellation rescheduling same day',
    ],
    'clinic-hours' => [
        'title' => 'Clinic Hours',
        'description' => 'Set open days, opening time, closing time, breaks, and weekly clinic schedule.',
        'meta' => $openDays . ' open day/s',
        'group' => 'core',
        'keywords' => 'clinic hours opening closing break schedule monday sunday',
    ],
    'services' => [
        'title' => 'Services',
        'description' => 'Add, edit, price, activate, or disable the dental services offered by the clinic.',
        'meta' => $activeServices . ' active service/s',
        'group' => 'core',
        'keywords' => 'services price duration dental cleaning extraction braces implants whitening',
    ],
    'notifications' => [
        'title' => 'Notifications',
        'description' => 'Configure appointment reminders and alerts for patients, staff, and dentists.',
        'meta' => 'Reminders: ' . settings_bool_text($settings, 'notifications', 'enable_appointment_reminders'),
        'group' => 'comm',
        'keywords' => 'notifications reminders patient dentist staff appointment 3 day 2 day 1 day',
    ],
    'messaging' => [
        'title' => 'Messaging & Chatbot',
        'description' => 'Manage chatbot replies, guest chat, patient messaging, and file upload options.',
        'meta' => 'Chatbot: ' . settings_bool_text($settings, 'messaging', 'enable_chatbot'),
        'group' => 'comm',
        'keywords' => 'messages messaging chatbot guest chat file upload patient staff',
    ],
    'billing' => [
        'title' => 'Billing & Receipts',
        'description' => 'Configure billing module, payment methods, discounts, receipt prefix, and currency.',
        'meta' => 'Currency: ' . settings_raw_value($settings, 'billing', 'currency', 'PHP'),
        'group' => 'finance',
        'keywords' => 'billing receipt payment gcash cash bank discount currency partial',
    ],
    'documents' => [
        'title' => 'Documents',
        'description' => 'Manage upload permissions, allowed file types, file size limits, and document audit.',
        'meta' => 'Uploads: ' . settings_bool_text($settings, 'documents', 'enable_document_uploads'),
        'group' => 'finance',
        'keywords' => 'documents uploads file type pdf image dentist staff audit soft delete',
    ],
    'security' => [
        'title' => 'Security',
        'description' => 'Control password rules, 2FA, session timeout, login attempts, and account protection.',
        'meta' => 'Min password: ' . settings_raw_value($settings, 'security', 'minimum_password_length', '8') . ' chars',
        'group' => 'system',
        'keywords' => 'security password 2fa session timeout login attempts lock csrf maintenance',
    ],
    'audit' => [
        'title' => 'Audit Trail',
        'description' => 'Choose which system actions are recorded for monitoring and accountability.',
        'meta' => 'Audit: ' . settings_bool_text($settings, 'audit', 'enable_audit_logging'),
        'group' => 'system',
        'keywords' => 'audit trail logs login logout appointment billing documents backup settings',
    ],
    'backup' => [
        'title' => 'Backup & Recovery',
        'description' => 'Manage backup retention, storage path, download rules, and restore permissions.',
        'meta' => 'Retention: ' . settings_raw_value($settings, 'backup', 'backup_retention_days', '30') . ' days',
        'group' => 'system',
        'keywords' => 'backup recovery restore database download retention storage',
    ],
    'appearance' => [
        'title' => 'Appearance & Branding',
        'description' => 'Update system logo, favicon, theme mode, accent color, and receipt appearance.',
        'meta' => 'Theme: ' . settings_raw_value($settings, 'appearance', 'default_theme_mode', 'light'),
        'group' => 'system',
        'keywords' => 'appearance branding logo favicon theme color homepage receipt dark light',
    ],
    'maintenance' => [
        'title' => 'Maintenance',
        'description' => 'Enable maintenance mode, edit maintenance message, and clean old temporary files.',
        'meta' => 'Mode: ' . settings_bool_text($settings, 'maintenance', 'maintenance_mode'),
        'group' => 'system',
        'keywords' => 'maintenance mode message old logs temporary files cleanup system',
    ],
];

$mostVisitedDefaultKeys = [
    'clinic-profile',
    'appointment-rules',
    'services',
];

$statusRows = [
    [
        'label' => 'Online Booking',
        'description' => 'Patients can book appointments from the public website.',
        'on' => settings_bool_on($settings, 'appointment_rules', 'enable_online_booking'),
        'url' => $baseUrl . '/admin/settings/appointment-rules',
        'icon' => 'appointment-rules',
    ],
    [
        'label' => 'Guest Booking',
        'description' => 'Guests can request appointments without logging in.',
        'on' => settings_bool_on($settings, 'appointment_rules', 'enable_guest_booking'),
        'url' => $baseUrl . '/admin/settings/appointment-rules',
        'icon' => 'appointment-rules',
    ],
    [
        'label' => 'Chatbot',
        'description' => 'Chatbot support is available on the public website.',
        'on' => settings_bool_on($settings, 'messaging', 'enable_chatbot'),
        'url' => $baseUrl . '/admin/settings/messaging',
        'icon' => 'messaging',
    ],
    [
        'label' => 'Maintenance Mode',
        'description' => 'Temporarily limits access while the system is under maintenance.',
        'on' => settings_bool_on($settings, 'maintenance', 'maintenance_mode'),
        'warn' => true,
        'url' => $baseUrl . '/admin/settings/maintenance',
        'icon' => 'maintenance',
    ],
    [
        'label' => 'Billing Module',
        'description' => 'Billing, payments, and receipts are available.',
        'on' => settings_bool_on($settings, 'billing', 'enable_billing_module'),
        'url' => $baseUrl . '/admin/settings/billing',
        'icon' => 'billing',
    ],
    [
        'label' => 'Two-Factor Authentication',
        'description' => 'Extra account protection for owner, admin, or staff accounts.',
        'on' => settings_bool_on($settings, 'security', 'enable_2fa_owner_admin_staff'),
        'url' => $baseUrl . '/admin/settings/security',
        'icon' => 'security',
    ],
];

$statsRows = [
    [
        'label' => 'Categories',
        'value' => (string) count($sections),
        'description' => 'Total settings sections',
        'icon' => 'settings',
    ],
    [
        'label' => 'Open Days',
        'value' => (string) $openDays,
        'description' => 'Days clinic is open',
        'icon' => 'clinic-hours',
    ],
    [
        'label' => 'Services',
        'value' => (string) $activeServices,
        'description' => 'Active dental services',
        'icon' => 'services',
    ],
];

$groups = [
    'core' => [
        'label' => 'Clinic & Appointments',
        'description' => 'Main clinic details, booking rules, clinic hours, and service setup.',
    ],
    'comm' => [
        'label' => 'Communication',
        'description' => 'Notifications, reminders, messaging, and chatbot controls.',
    ],
    'finance' => [
        'label' => 'Finance & Documents',
        'description' => 'Billing, receipts, uploads, and document controls.',
    ],
    'system' => [
        'label' => 'System & Security',
        'description' => 'Security, audit logs, backups, branding, and maintenance.',
    ],
];

$settingsSearchItems = [];

foreach ($sections as $sk => $sec) {
    $settingsSearchItems[] = [
        'key' => $sk,
        'url' => $baseUrl . '/admin/settings/' . $sk,
        'title' => $sec['title'],
        'description' => $sec['description'],
        'meta' => $sec['meta'],
        'iconSvg' => settings_icon($sk),
        'search' => strtolower($sec['title'] . ' ' . $sec['description'] . ' ' . $sec['meta'] . ' ' . $sec['keywords']),
    ];
}

ob_start();
?>

<style>
:root {
    --set-bg: #f7f8fa;
    --set-panel: #ffffff;
    --set-text: #222222;
    --set-muted: #6b7280;
    --set-light: #9ca3af;
    --set-line: #e5e7eb;
    --set-line-strong: #d1d5db;
    --set-soft: #f3f4f6;
    --set-black: #222222;
    --set-teal: #0f766e;
    --set-teal-soft: #e7f6f4;
    --set-green: #11845b;
    --set-green-soft: #ecfdf3;
    --set-red: #b42318;
    --set-red-soft: #fef3f2;
    --set-amber: #d97706;
    --set-amber-soft: #fffbeb;
    --set-shadow: 0 16px 46px rgba(15, 23, 42, .08);
}

*,
*::before,
*::after {
    box-sizing: border-box;
}

.settings-page {
    min-height: 100vh;
    padding: 22px;
    background: var(--set-bg);
    color: var(--set-text);
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.settings-wrap {
    width: min(1200px, 100%);
    margin: 0 auto;
}

.settings-top {
    position: sticky;
    top: 0;
    z-index: 50;
    padding: 8px 0 14px;
    background: rgba(247, 248, 250, .92);
    backdrop-filter: blur(14px);
}

.settings-header {
    margin-bottom: 14px;
}

.settings-title {
    margin: 0;
    color: var(--set-text);
    font-size: 29px;
    line-height: 1.1;
    font-weight: 900;
    letter-spacing: -.045em;
}

.settings-subtitle {
    margin: 5px 0 0;
    color: var(--set-muted);
    font-size: 13.5px;
    line-height: 1.45;
}

.settings-search {
    width: 100%;
    min-height: 46px;
    border: 1px solid var(--set-line);
    border-radius: 999px;
    padding: 0 16px;
    background: var(--set-panel);
    box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
    display: flex;
    align-items: center;
    gap: 10px;
}

.settings-search svg {
    color: var(--set-light);
    flex: 0 0 auto;
}

.settings-search input {
    width: 100%;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--set-text);
    font: inherit;
    font-size: 14px;
}

.settings-search input::placeholder {
    color: var(--set-light);
}

.settings-most {
    margin: 2px 0 16px;
}

.settings-most-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.settings-most-title {
    margin: 0;
    color: var(--set-text);
    font-size: 18px;
    line-height: 1.2;
    font-weight: 900;
    letter-spacing: -.02em;
}

.settings-most-desc {
    margin: 4px 0 0;
    color: var(--set-muted);
    font-size: 13px;
    line-height: 1.45;
}

.settings-most-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.settings-most-card {
    min-height: 134px;
    border: 1px solid var(--set-line);
    border-radius: 20px;
    background: var(--set-panel);
    color: inherit;
    text-decoration: none;
    padding: 16px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
    display: flex;
    flex-direction: column;
    gap: 12px;
    transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
}

.settings-most-card:hover {
    transform: translateY(-2px);
    border-color: var(--set-teal);
    box-shadow: 0 16px 36px rgba(15, 23, 42, .09);
}

.settings-most-icon {
    width: 44px;
    height: 44px;
    border-radius: 15px;
    background: var(--set-teal-soft);
    color: var(--set-teal);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.settings-most-name {
    display: block;
    color: var(--set-text);
    font-size: 14.5px;
    line-height: 1.25;
    font-weight: 900;
}

.settings-most-meta {
    display: block;
    margin-top: 4px;
    color: var(--set-muted);
    font-size: 12px;
    line-height: 1.35;
    font-weight: 700;
}

.settings-most-count {
    margin-top: auto;
    min-height: 26px;
    width: fit-content;
    max-width: 100%;
    padding: 0 10px;
    border-radius: 999px;
    background: var(--set-teal-soft);
    color: var(--set-teal);
    display: inline-flex;
    align-items: center;
    font-size: 11.5px;
    font-weight: 850;
    white-space: nowrap;
}

.settings-flashes {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 14px;
}

.settings-flash {
    min-height: 48px;
    border: 1px solid var(--set-line);
    border-radius: 16px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 800;
}

.settings-flash.success {
    background: var(--set-green-soft);
    border-color: #bbf7d0;
    color: var(--set-green);
}

.settings-flash.error {
    background: var(--set-red-soft);
    border-color: #fecaca;
    color: var(--set-red);
}

.settings-panel {
    overflow: hidden;
    border: 1px solid var(--set-line);
    border-radius: 1px;
    background: var(--set-panel);
    box-shadow: var(--set-shadow);
}

.settings-panel + .settings-panel {
    margin-top: 16px;
}

.settings-panel-head {
    padding: 20px;
    border-bottom: 1px solid var(--set-line);
    display: flex;
    align-items: center;
    gap: 14px;
}

.settings-panel-icon {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    background: var(--set-teal-soft);
    color: var(--set-teal);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}

.settings-panel-title {
    color: var(--set-text);
    font-size: 18px;
    line-height: 1.25;
    font-weight: 900;
    letter-spacing: -.02em;
}

.settings-panel-desc {
    margin-top: 3px;
    color: var(--set-muted);
    font-size: 13px;
    line-height: 1.45;
}

.settings-section {
    padding: 18px 20px 22px;
}

.settings-heading {
    margin: 0;
    color: var(--set-text);
    font-size: 18px;
    line-height: 1.25;
    font-weight: 900;
}

.settings-help {
    margin: 5px 0 16px;
    color: var(--set-muted);
    font-size: 13px;
    line-height: 1.45;
}

.settings-list {
    border-top: 1px solid var(--set-line);
    display: flex;
    flex-direction: column;
}

.settings-row {
    width: 100%;
    min-height: 72px;
    padding: 15px 0;
    border-bottom: 1px solid var(--set-line);
    background: transparent;
    color: inherit;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 13px;
}

.settings-row:hover .settings-row-title {
    color: var(--set-teal);
}

.settings-row-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    background: var(--set-soft);
    color: #374151;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}

.settings-row-icon.teal {
    background: var(--set-teal-soft);
    color: var(--set-teal);
}

.settings-row-main {
    min-width: 0;
    flex: 1 1 auto;
}

.settings-row-title {
    display: block;
    color: var(--set-text);
    font-size: 14.5px;
    line-height: 1.3;
    font-weight: 900;
    transition: color .16s ease;
}

.settings-row-desc {
    display: block;
    margin-top: 3px;
    color: var(--set-muted);
    font-size: 12.5px;
    line-height: 1.45;
}

.settings-row-value {
    max-width: 240px;
    color: var(--set-muted);
    text-align: right;
    font-size: 12px;
    line-height: 1.35;
    font-weight: 800;
    flex: 0 1 auto;
}

.settings-arrow {
    color: var(--set-light);
    flex: 0 0 auto;
}

.settings-pill {
    min-height: 28px;
    padding: 0 11px;
    border-radius: 999px;
    background: var(--set-soft);
    color: #4b5563;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 850;
    white-space: nowrap;
}

.settings-pill.teal {
    background: var(--set-teal-soft);
    color: var(--set-teal);
}

.settings-pill.green {
    background: var(--set-green-soft);
    color: var(--set-green);
}

.settings-pill.red {
    background: var(--set-red-soft);
    color: var(--set-red);
}

.settings-pill.amber {
    background: var(--set-amber-soft);
    color: var(--set-amber);
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
    border-radius: 999px;
    background: #ffffff;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .18);
    transition: transform .18s ease;
}

.settings-switch.on {
    background: var(--set-black);
}

.settings-switch.on::after {
    transform: translateX(18px);
}

.settings-switch.warn {
    background: var(--set-amber);
}

.settings-stat-value {
    min-width: 48px;
    color: var(--set-text);
    text-align: right;
    font-size: 24px;
    line-height: 1;
    font-weight: 950;
    letter-spacing: -.03em;
}

.settings-default.hidden {
    display: none;
}

.settings-results {
    display: none;
    overflow: hidden;
    border: 1px solid var(--set-line);
    border-radius: 26px;
    background: var(--set-panel);
    box-shadow: var(--set-shadow);
    margin-bottom: 16px;
}

.settings-results.open {
    display: block;
}

.settings-results-head {
    padding: 18px 20px;
    border-bottom: 1px solid var(--set-line);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.settings-results-title {
    color: var(--set-text);
    font-size: 15px;
    font-weight: 900;
    line-height: 1.35;
}

.settings-results-count {
    flex: 0 0 auto;
}

.settings-results-list {
    padding: 0 20px 20px;
}

.settings-empty {
    display: none;
    padding: 28px 20px;
    color: var(--set-muted);
    text-align: center;
    font-size: 13px;
    font-weight: 800;
}

.settings-empty.show {
    display: block;
}

.settings-group {
    margin-top: 18px;
}

.settings-group:first-child {
    margin-top: 0;
}

.settings-group-label {
    margin: 0;
    color: var(--set-text);
    font-size: 15px;
    font-weight: 900;
    letter-spacing: -.02em;
}

.settings-group-desc {
    margin: 4px 0 12px;
    color: var(--set-muted);
    font-size: 12.5px;
    line-height: 1.45;
}

@media (max-width: 720px) {
    .settings-page {
        padding: 14px;
    }

    .settings-title {
        font-size: 24px;
    }

    .settings-most-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .settings-most-grid {
        grid-template-columns: 1fr;
    }

    .settings-most-card {
        min-height: 118px;
    }

    .settings-panel {
        border-radius: 22px;
    }

    .settings-panel-head,
    .settings-section,
    .settings-results-head,
    .settings-results-list {
        padding-left: 16px;
        padding-right: 16px;
    }

    .settings-row {
        align-items: flex-start;
    }

    .settings-row-value {
        display: none;
    }

    .settings-results-head {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<div class="settings-page">
    <div class="settings-wrap">
        <div class="settings-top">
          

            <label class="settings-search" for="settingsSearch">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="M20 20L16.6 16.6"></path>
                </svg>

                <input
                    type="search"
                    id="settingsSearch"
                    placeholder="Search settings"
                    autocomplete="off"
                    aria-label="Search settings"
                >
            </label>
        </div>

        <section class="settings-most">
            <div class="settings-most-head">
                <div>
                    <h2 class="settings-most-title">Most Visited Settings</h2>
                    <p class="settings-most-desc">Quick access to the settings you open most often.</p>
                </div>
            </div>

            <div class="settings-most-grid" id="mostVisitedList">
                <?php foreach ($mostVisitedDefaultKeys as $defaultKey): ?>
                    <?php if (!isset($sections[$defaultKey])) continue; ?>
                    <?php $sec = $sections[$defaultKey]; ?>

                    <a
                        href="<?= settings_e($baseUrl . '/admin/settings/' . $defaultKey) ?>"
                        class="settings-most-card si-visit-link"
                        data-settings-key="<?= settings_e($defaultKey) ?>"
                    >
                        <span class="settings-most-icon">
                            <?= settings_icon($defaultKey) ?>
                        </span>

                        <span>
                            <span class="settings-most-name"><?= settings_e($sec['title']) ?></span>
                            <span class="settings-most-meta"><?= settings_e($sec['description']) ?></span>
                        </span>

                        <span class="settings-most-count"><?= settings_e(settings_short_text($sec['meta'])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($flash_success || $flash_error): ?>
            <div class="settings-flashes">
                <?php if ($flash_success): ?>
                    <div class="settings-flash success">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9 12L11 14L15 10"></path>
                            <circle cx="12" cy="12" r="9"></circle>
                        </svg>
                        <span><?= settings_e($flash_success) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($flash_error): ?>
                    <div class="settings-flash error">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 8V12"></path>
                            <path d="M12 16H12.01"></path>
                        </svg>
                        <span><?= settings_e($flash_error) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="settings-results" id="settingsResultsPanel">
            <div class="settings-results-head">
                <div class="settings-results-title">
                    Search results for “<span id="settingsQueryLabel"></span>”
                </div>

                <span class="settings-pill teal settings-results-count" id="settingsResultCount">0 found</span>
            </div>

            <div class="settings-results-list">
                <div class="settings-list" id="settingsResultsList"></div>
                <div class="settings-empty" id="settingsNoResults">No settings match your search.</div>
            </div>
        </div>

        <div class="settings-default" id="settingsDefault">
            <section class="settings-panel">
                <div class="settings-panel-head">
                    <div class="settings-panel-icon">
                        <?= settings_icon('appointment-rules') ?>
                    </div>

                    <div>
                        <div class="settings-panel-title">System Status</div>
                        <div class="settings-panel-desc">Tap a row to manage the related setting.</div>
                    </div>
                </div>

                <div class="settings-section">
                    <div class="settings-list">
                        <?php foreach ($statusRows as $row): ?>
                            <?php
                                $isOn = !empty($row['on']);
                                $isWarn = !empty($row['warn']) && $isOn;
                                $settingKey = trim(str_replace($baseUrl . '/admin/settings/', '', (string) $row['url']), '/');

                                $statusText = $isOn ? 'On' : 'Off';
                                $statusClass = $isOn ? 'green' : 'red';

                                if ($isWarn) {
                                    $statusClass = 'amber';
                                }
                            ?>

                            <a
                                href="<?= settings_e((string) $row['url']) ?>"
                                class="settings-row si-visit-link"
                                data-settings-key="<?= settings_e($settingKey) ?>"
                            >
                                <span class="settings-row-icon">
                                    <?= settings_icon((string) ($row['icon'] ?? 'settings')) ?>
                                </span>

                                <span class="settings-row-main">
                                    <span class="settings-row-title"><?= settings_e($row['label']) ?></span>
                                    <span class="settings-row-desc"><?= settings_e($row['description']) ?></span>
                                </span>

                                <span class="settings-row-value">
                                    <span class="settings-pill <?= settings_e($statusClass) ?>">
                                        <?= settings_e($statusText) ?>
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
                </div>
            </section>

            <section class="settings-panel">
                <div class="settings-panel-head">
                    <div class="settings-panel-icon">
                        <?= settings_icon('settings') ?>
                    </div>

                    <div>
                        <div class="settings-panel-title">All Settings</div>
                        <div class="settings-panel-desc">Organized in a clean list so everything is easy to find.</div>
                    </div>
                </div>

                <div class="settings-section">
                    <?php foreach ($groups as $gKey => $gInfo): ?>
                        <?php
                            $gSections = array_filter($sections, static function ($s) use ($gKey): bool {
                                return ($s['group'] ?? '') === $gKey;
                            });
                        ?>

                        <div class="settings-group">
                            <h3 class="settings-group-label"><?= settings_e($gInfo['label']) ?></h3>
                            <p class="settings-group-desc"><?= settings_e($gInfo['description']) ?></p>

                            <div class="settings-list">
                                <?php foreach ($gSections as $sk => $sec): ?>
                                    <a
                                        href="<?= settings_e($baseUrl . '/admin/settings/' . $sk) ?>"
                                        class="settings-row si-visit-link"
                                        data-settings-key="<?= settings_e($sk) ?>"
                                    >
                                        <span class="settings-row-icon">
                                            <?= settings_icon($sk) ?>
                                        </span>

                                        <span class="settings-row-main">
                                            <span class="settings-row-title"><?= settings_e($sec['title']) ?></span>
                                            <span class="settings-row-desc"><?= settings_e($sec['description']) ?></span>
                                        </span>

                                        <span class="settings-row-value">
                                            <span class="settings-pill"><?= settings_e(settings_short_text($sec['meta'])) ?></span>
                                        </span>

                                        <span class="settings-arrow" aria-hidden="true">
                                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                                                <path d="M9 18L15 12L9 6"></path>
                                            </svg>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</div>

<script type="application/json" id="settingsSearchData">
<?= json_encode($settingsSearchItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
</script>

<script type="application/json" id="settingsMostVisitedDefaults">
<?= json_encode($mostVisitedDefaultKeys, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
</script>

<script>
(function () {
    'use strict';

    var input = document.getElementById('settingsSearch');
    var panel = document.getElementById('settingsResultsPanel');
    var defaultEl = document.getElementById('settingsDefault');
    var listEl = document.getElementById('settingsResultsList');
    var countEl = document.getElementById('settingsResultCount');
    var labelEl = document.getElementById('settingsQueryLabel');
    var noRes = document.getElementById('settingsNoResults');
    var mostList = document.getElementById('mostVisitedList');

    var items = [];
    var defaultKeys = [];
    var visitStorageKey = 'dental_settings_most_visited_grid_v4';

    try {
        items = JSON.parse(document.getElementById('settingsSearchData').textContent || '[]');
    } catch (e) {
        items = [];
    }

    try {
        defaultKeys = JSON.parse(document.getElementById('settingsMostVisitedDefaults').textContent || '[]');
    } catch (e) {
        defaultKeys = [];
    }

    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function readVisits() {
        try {
            var raw = localStorage.getItem(visitStorageKey);
            var parsed = raw ? JSON.parse(raw) : {};

            if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
                return {};
            }

            return parsed;
        } catch (e) {
            return {};
        }
    }

    function writeVisits(visits) {
        try {
            localStorage.setItem(visitStorageKey, JSON.stringify(visits || {}));
        } catch (e) {}
    }

    function buildMap() {
        var map = {};

        items.forEach(function (item) {
            if (item && item.key) {
                map[item.key] = item;
            }
        });

        return map;
    }

    function recordVisit(key) {
        key = String(key || '').trim();

        if (!key) {
            return;
        }

        var visits = readVisits();
        visits[key] = Math.max(0, parseInt(visits[key] || 0, 10)) + 1;
        writeVisits(visits);
    }

    function attachVisitHandlers(root) {
        var scope = root || document;
        var links = scope.querySelectorAll('.si-visit-link');

        links.forEach(function (link) {
            if (link.getAttribute('data-visit-bound') === '1') {
                return;
            }

            link.setAttribute('data-visit-bound', '1');

            link.addEventListener('click', function () {
                recordVisit(this.getAttribute('data-settings-key'));
            });
        });
    }

    function mostVisitedItems() {
        var visits = readVisits();
        var byKey = buildMap();
        var selected = [];

        var visitedKeys = Object.keys(visits).filter(function (key) {
            return byKey[key];
        });

        visitedKeys.sort(function (a, b) {
            var diff = parseInt(visits[b] || 0, 10) - parseInt(visits[a] || 0, 10);

            if (diff !== 0) {
                return diff;
            }

            return String(byKey[a].title || '').localeCompare(String(byKey[b].title || ''));
        });

        visitedKeys.forEach(function (key) {
            if (selected.length < 3 && selected.indexOf(key) === -1) {
                selected.push(key);
            }
        });

        defaultKeys.forEach(function (key) {
            if (selected.length < 3 && byKey[key] && selected.indexOf(key) === -1) {
                selected.push(key);
            }
        });

        items.forEach(function (item) {
            if (selected.length < 3 && item.key && selected.indexOf(item.key) === -1) {
                selected.push(item.key);
            }
        });

        return selected.map(function (key) {
            return byKey[key];
        }).filter(Boolean);
    }

    function renderResultRow(item, extraMeta) {
        var meta = extraMeta || item.meta || '';

        return '' +
            '<a href="' + esc(item.url || '#') + '" class="settings-row si-visit-link" data-settings-key="' + esc(item.key || '') + '">' +
                '<span class="settings-row-icon teal">' + (item.iconSvg || '') + '</span>' +
                '<span class="settings-row-main">' +
                    '<span class="settings-row-title">' + esc(item.title) + '</span>' +
                    '<span class="settings-row-desc">' + esc(item.description) + '</span>' +
                '</span>' +
                '<span class="settings-row-value">' +
                    '<span class="settings-pill teal">' + esc(meta) + '</span>' +
                '</span>' +
                '<span class="settings-arrow" aria-hidden="true">' +
                    '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">' +
                        '<path d="M9 18L15 12L9 6"></path>' +
                    '</svg>' +
                '</span>' +
            '</a>';
    }

    function renderMostVisited() {
        if (!mostList) {
            return;
        }

        var visits = readVisits();
        var topItems = mostVisitedItems();
        var html = '';

        topItems.forEach(function (item) {
            var count = parseInt(visits[item.key] || 0, 10);
            var meta = count > 0
                ? count + ' visit' + (count === 1 ? '' : 's')
                : item.meta;

            html += '' +
                '<a href="' + esc(item.url || '#') + '" class="settings-most-card si-visit-link" data-settings-key="' + esc(item.key || '') + '">' +
                    '<span class="settings-most-icon">' + (item.iconSvg || '') + '</span>' +
                    '<span>' +
                        '<span class="settings-most-name">' + esc(item.title) + '</span>' +
                        '<span class="settings-most-meta">' + esc(item.description) + '</span>' +
                    '</span>' +
                    '<span class="settings-most-count">' + esc(meta) + '</span>' +
                '</a>';
        });

        mostList.innerHTML = html;
        attachVisitHandlers(mostList);
    }

    function search(query) {
        query = String(query || '').trim();

        if (!query) {
            if (panel) {
                panel.classList.remove('open');
            }

            if (defaultEl) {
                defaultEl.classList.remove('hidden');
            }

            return;
        }

        if (defaultEl) {
            defaultEl.classList.add('hidden');
        }

        if (panel) {
            panel.classList.add('open');
        }

        if (labelEl) {
            labelEl.textContent = query;
        }

        var norm = query.toLowerCase();
        var hits = items.filter(function (item) {
            return String(item.search || '').includes(norm);
        });

        if (countEl) {
            countEl.textContent = hits.length + ' found';
        }

        if (!listEl) {
            return;
        }

        listEl.innerHTML = '';

        if (hits.length === 0) {
            if (noRes) {
                noRes.classList.add('show');
            }

            return;
        }

        if (noRes) {
            noRes.classList.remove('show');
        }

        var html = '';

        hits.forEach(function (item) {
            html += renderResultRow(item, item.meta);
        });

        listEl.innerHTML = html;
        attachVisitHandlers(listEl);
    }

    if (input) {
        input.addEventListener('input', function () {
            search(this.value);
        });

        input.addEventListener('search', function () {
            search(this.value);
        });
    }

    document.addEventListener('keydown', function (event) {
        if ((event.metaKey || event.ctrlKey) && String(event.key).toLowerCase() === 'k') {
            event.preventDefault();

            if (input) {
                input.focus();
                input.select();
            }
        }

        if (event.key === 'Escape' && input && input.value) {
            input.value = '';
            search('');
            input.blur();
        }
    });

    renderMostVisited();
    attachVisitHandlers(document);
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';