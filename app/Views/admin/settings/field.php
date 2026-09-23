<?php
use App\Core\Csrf;

$pageTitle = $field['label'] ?? 'Edit Setting';
$baseUrl   = '/DentalClinic/public';

$sectionKey = isset($sectionKey) ? (string) $sectionKey : '';
$fieldKey   = isset($fieldKey) ? (string) $fieldKey : '';
$field      = isset($field) && is_array($field) ? $field : [];
$settings   = isset($settings) && is_array($settings) ? $settings : [];

$flash_success = $flash_success ?? null;
$flash_error   = $flash_error ?? null;

if (!function_exists('fe')) {
    function fe(?string $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('setting_raw_value')) {
    function setting_raw_value(array $settings, string $group, string $key, string $default = ''): string
    {
        $value = $settings[$group][$key] ?? $default;

        if (is_array($value)) {
            $value = $value['setting_value']
                ?? $value['value']
                ?? $value['setting_text']
                ?? $default;
        }

        return (string) $value;
    }
}

if (!function_exists('fe_short')) {
    function fe_short(string $value, int $limit = 80): string
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

if (!function_exists('field_type_icon')) {
    function field_type_icon(string $type = 'text'): string
    {
        $type = strtolower($type);

        $paths = [
            'text' => '<rect x="4" y="6" width="16" height="12" rx="3"></rect><path d="M8 10H16"></path><path d="M8 14H13"></path>',
            'email' => '<rect x="4" y="6" width="16" height="12" rx="3"></rect><path d="M5 8L12 13L19 8"></path>',
            'url' => '<path d="M10 13A4 4 0 0 0 15.5 13.5L18 11A4 4 0 0 0 12.5 5.5L11.5 6.5"></path><path d="M14 11A4 4 0 0 0 8.5 10.5L6 13A4 4 0 0 0 11.5 18.5L12.5 17.5"></path>',
            'textarea' => '<rect x="4" y="5" width="16" height="14" rx="3"></rect><path d="M8 10H16"></path><path d="M8 14H15"></path>',
            'checkbox' => '<rect x="4" y="4" width="16" height="16" rx="5"></rect><path d="M8 12L11 15L16 9"></path>',
            'number' => '<path d="M9 4L7 20"></path><path d="M17 4L15 20"></path><path d="M5 9H20"></path><path d="M4 15H19"></path>',
            'file' => '<path d="M7 3H14L19 8V21H7V3Z"></path><path d="M14 3V8H19"></path><path d="M10 13H16"></path><path d="M10 17H14"></path>',
            'select' => '<rect x="4" y="6" width="16" height="12" rx="3"></rect><path d="M8 10H16"></path><path d="M8 14H12"></path><path d="M15 14L17 16L19 14"></path>',
            'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15A1.7 1.7 0 0 0 19.7 13.1L18.8 11.6A1.7 1.7 0 0 1 18.8 10.4L19.7 8.9A1.7 1.7 0 0 0 19.4 7L17 4.6A1.7 1.7 0 0 0 15.1 4.3L13.6 5.2A1.7 1.7 0 0 1 12.4 5.2L10.9 4.3A1.7 1.7 0 0 0 9 4.6L6.6 7A1.7 1.7 0 0 0 6.3 8.9L7.2 10.4A1.7 1.7 0 0 1 7.2 11.6L6.3 13.1A1.7 1.7 0 0 0 6.6 15L9 17.4A1.7 1.7 0 0 0 10.9 17.7L12.4 16.8A1.7 1.7 0 0 1 13.6 16.8L15.1 17.7A1.7 1.7 0 0 0 17 17.4Z"></path>',
        ];

        $path = $paths[$type] ?? $paths['settings'];

        return '
            <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.15" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                ' . $path . '
            </svg>
        ';
    }
}

if (!function_exists('section_icon')) {
    function section_icon(string $key = 'settings'): string
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

$group       = (string) ($field['group'] ?? '');
$label       = (string) ($field['label'] ?? $fieldKey);
$type        = (string) ($field['type'] ?? 'text');
$description = (string) ($field['description'] ?? '');
$default     = (string) ($field['default'] ?? '');
$options     = isset($field['options']) && is_array($field['options']) ? $field['options'] : [];

$value = setting_raw_value($settings, $group, $fieldKey, $default);

$isFile     = $type === 'file';
$isCheckbox = $type === 'checkbox';
$isTextarea = $type === 'textarea';
$isSelect   = $type === 'select';
$isNumber   = $type === 'number';

$formEnctype = $isFile ? ' enctype="multipart/form-data"' : '';
$sectionUrl  = $baseUrl . '/admin/settings/' . $sectionKey;
$currentUrl  = $baseUrl . '/admin/settings/field?section=' . urlencode($sectionKey) . '&field=' . urlencode($fieldKey);

$sectionLabels = [
    'clinic-profile'    => 'Clinic Profile',
    'public-website'    => 'Public Website',
    'appointment-rules' => 'Appointment Rules',
    'clinic-hours'      => 'Clinic Hours',
    'services'          => 'Services',
    'notifications'     => 'Notifications',
    'messaging'         => 'Messaging & Chatbot',
    'billing'           => 'Billing & Receipts',
    'documents'         => 'Documents',
    'security'          => 'Security',
    'audit'             => 'Audit Trail',
    'backup'            => 'Backup & Recovery',
    'appearance'        => 'Appearance & Branding',
    'maintenance'       => 'Maintenance',
];

$sectionLabel = $sectionLabels[$sectionKey] ?? ucwords(str_replace('-', ' ', $sectionKey));

$typeLabel = [
    'text'     => 'Text field',
    'email'    => 'Email field',
    'url'      => 'URL field',
    'textarea' => 'Long text field',
    'checkbox' => 'Checkbox setting',
    'number'   => 'Number field',
    'file'     => 'File upload',
    'select'   => 'Dropdown field',
];

$allowedTextInputTypes = ['text', 'email', 'url'];
$inputType = in_array($type, $allowedTextInputTypes, true) ? $type : 'text';

$isEnabled = in_array(strtolower(trim($value)), ['1', 'yes', 'true', 'on', 'enabled'], true);

ob_start();
?>

<style>
:root {
    --fe-bg: #f7f8fa;
    --fe-panel: #ffffff;
    --fe-text: #222222;
    --fe-muted: #6b7280;
    --fe-light: #9ca3af;
    --fe-line: #e5e7eb;
    --fe-line-strong: #d1d5db;
    --fe-soft: #f3f4f6;
    --fe-black: #222222;
    --fe-teal: #0f766e;
    --fe-teal-soft: #e7f6f4;
    --fe-green: #11845b;
    --fe-green-soft: #ecfdf3;
    --fe-red: #b42318;
    --fe-red-soft: #fef3f2;
    --fe-shadow: 0 16px 46px rgba(15, 23, 42, .08);
}

*,
*::before,
*::after {
    box-sizing: border-box;
}

.fe-page {
    min-height: 100vh;
    padding: 22px;
    background: var(--fe-bg);
    color: var(--fe-text);
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.fe-wrap {
    width: min(1200px, 100%);
    margin: 0 auto;
}

.fe-top {
    position: relative;
    top: auto;
    z-index: 1;
    padding: 8px 0 16px;
    background: transparent;
    backdrop-filter: none;
}

.fe-header {
    display: flex;
    align-items: center;
    gap: 13px;
}

.fe-back {
    width: 42px;
    height: 42px;
    border: 1px solid var(--fe-line);
    border-radius: 999px;
    background: var(--fe-panel);
    color: var(--fe-text);
    box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    flex: 0 0 auto;
}

.fe-back:hover {
    background: var(--fe-soft);
}

.fe-title {
    margin: 0;
    color: var(--fe-text);
    font-size: 27px;
    line-height: 1.1;
    font-weight: 900;
    letter-spacing: -.045em;
}

.fe-description {
    margin: 5px 0 0;
    color: var(--fe-muted);
    font-size: 13.5px;
    line-height: 1.45;
}

.fe-flashes {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 14px;
}

.fe-flash {
    min-height: 48px;
    border: 1px solid var(--fe-line);
    border-radius: 16px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 800;
}

.fe-flash.success {
    background: var(--fe-green-soft);
    border-color: #bbf7d0;
    color: var(--fe-green);
}

.fe-flash.error {
    background: var(--fe-red-soft);
    border-color: #fecaca;
    color: var(--fe-red);
}

.fe-panel {
    overflow: hidden;
    border: 1px solid var(--fe-line);
    border-radius: 1px;
    background: var(--fe-panel);
    box-shadow: var(--fe-shadow);
}

.fe-panel-head {
    padding: 20px;
    border-bottom: 1px solid var(--fe-line);
    display: flex;
    align-items: center;
    gap: 14px;
}

.fe-panel-icon {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    background: var(--fe-teal-soft);
    color: var(--fe-teal);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}

.fe-panel-title {
    color: var(--fe-text);
    font-size: 18px;
    line-height: 1.25;
    font-weight: 900;
    letter-spacing: -.02em;
}

.fe-panel-subtitle {
    margin-top: 3px;
    color: var(--fe-muted);
    font-size: 13px;
    line-height: 1.45;
}

.fe-body {
    padding: 20px;
}

.fe-layout {
    display: grid;
    grid-template-columns: 1fr 290px;
    gap: 18px;
    align-items: start;
}

.fe-form-card,
.fe-side-card {
    border: 1px solid var(--fe-line);
    border-radius: 22px;
    background: #ffffff;
    overflow: hidden;
}

.fe-form-head,
.fe-side-head {
    padding: 16px 18px;
    border-bottom: 1px solid var(--fe-line);
    background: #fbfcfd;
}

.fe-form-title,
.fe-side-title {
    color: var(--fe-text);
    font-size: 15px;
    font-weight: 900;
}

.fe-form-subtitle,
.fe-side-subtitle {
    margin-top: 3px;
    color: var(--fe-muted);
    font-size: 12.5px;
    line-height: 1.45;
}

.fe-form-body {
    padding: 18px;
}

.fe-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.fe-label {
    color: var(--fe-text);
    font-size: 13px;
    font-weight: 900;
}

.fe-input,
.fe-select,
.fe-textarea {
    width: 100%;
    border: 1px solid var(--fe-line-strong);
    border-radius: 14px;
    background: #ffffff;
    color: var(--fe-text);
    font: inherit;
    font-size: 14px;
    outline: none;
    transition: border-color .16s ease, box-shadow .16s ease;
}

.fe-input,
.fe-select {
    min-height: 46px;
    padding: 0 13px;
}

.fe-textarea {
    min-height: 170px;
    padding: 13px;
    resize: vertical;
    line-height: 1.55;
}

.fe-input:focus,
.fe-select:focus,
.fe-textarea:focus {
    border-color: var(--fe-black);
    box-shadow: 0 0 0 4px rgba(34, 34, 34, .07);
}

.fe-help {
    color: var(--fe-muted);
    font-size: 12px;
    line-height: 1.45;
}

.fe-select-wrap {
    position: relative;
}

.fe-select {
    appearance: none;
    padding-right: 42px;
}

.fe-select-wrap::after {
    content: "";
    position: absolute;
    right: 16px;
    top: 50%;
    width: 8px;
    height: 8px;
    border-right: 2px solid var(--fe-light);
    border-bottom: 2px solid var(--fe-light);
    transform: translateY(-70%) rotate(45deg);
    pointer-events: none;
}

.fe-check-card {
    border: 1px solid var(--fe-line);
    border-radius: 18px;
    background: #fbfcfd;
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 13px;
    cursor: pointer;
}

.fe-check-input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.fe-check-box {
    width: 24px;
    height: 24px;
    border: 2px solid var(--fe-line-strong);
    border-radius: 8px;
    background: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    margin-top: 1px;
}

.fe-check-box::after {
    content: "";
    width: 9px;
    height: 5px;
    border-left: 2px solid #fff;
    border-bottom: 2px solid #fff;
    transform: rotate(-45deg);
    display: none;
}

.fe-check-input:checked + .fe-check-box {
    background: var(--fe-teal);
    border-color: var(--fe-teal);
}

.fe-check-input:checked + .fe-check-box::after {
    display: block;
}

.fe-check-main {
    flex: 1 1 auto;
    min-width: 0;
}

.fe-check-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.fe-check-title {
    color: var(--fe-text);
    font-size: 15px;
    font-weight: 900;
}

.fe-check-desc {
    display: block;
    margin-top: 5px;
    color: var(--fe-muted);
    font-size: 12.5px;
    line-height: 1.45;
}

.fe-pill {
    min-height: 28px;
    padding: 0 11px;
    border-radius: 999px;
    background: var(--fe-soft);
    color: #4b5563;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 850;
    white-space: nowrap;
}

.fe-pill.green {
    background: var(--fe-green-soft);
    color: var(--fe-green);
}

.fe-pill.red {
    background: var(--fe-red-soft);
    color: var(--fe-red);
}

.fe-pill.teal {
    background: var(--fe-teal-soft);
    color: var(--fe-teal);
}

.fe-file-current {
    border: 1px solid var(--fe-line);
    border-radius: 16px;
    background: #fbfcfd;
    padding: 13px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--fe-muted);
    font-size: 12.5px;
    font-weight: 800;
    word-break: break-all;
}

.fe-file-current-icon {
    width: 38px;
    height: 38px;
    border-radius: 13px;
    background: var(--fe-teal-soft);
    color: var(--fe-teal);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}

.fe-file-area {
    position: relative;
    border: 2px dashed var(--fe-line-strong);
    border-radius: 18px;
    background: #fbfcfd;
    padding: 30px 18px;
    text-align: center;
    cursor: pointer;
    transition: border-color .16s ease, background .16s ease;
}

.fe-file-area:hover {
    border-color: var(--fe-teal);
    background: var(--fe-teal-soft);
}

.fe-file-area input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

.fe-file-icon {
    width: 52px;
    height: 52px;
    border-radius: 18px;
    background: var(--fe-teal-soft);
    color: var(--fe-teal);
    margin: 0 auto 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.fe-file-title {
    color: var(--fe-text);
    font-size: 14px;
    font-weight: 900;
}

.fe-file-hint {
    margin-top: 5px;
    color: var(--fe-muted);
    font-size: 12px;
    font-weight: 700;
}

.fe-footer {
    padding: 16px 18px;
    border-top: 1px solid var(--fe-line);
    background: #fbfcfd;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.fe-footer-note {
    color: var(--fe-muted);
    font-size: 12px;
    line-height: 1.45;
    font-weight: 700;
}

.fe-btn {
    min-height: 42px;
    border: 1px solid transparent;
    border-radius: 999px;
    padding: 0 17px;
    background: transparent;
    color: inherit;
    font: inherit;
    font-size: 13px;
    font-weight: 900;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: transform .16s ease, background .16s ease, border-color .16s ease;
}

.fe-btn:active {
    transform: scale(.98);
}

.fe-btn-primary {
    background: var(--fe-black);
    color: #fff;
}

.fe-btn-primary:hover {
    background: #000;
}

.fe-btn-ghost {
    background: #ffffff;
    color: var(--fe-text);
    border-color: var(--fe-line-strong);
}

.fe-btn-ghost:hover {
    background: #f9fafb;
}

.fe-btn.is-loading {
    opacity: .7;
    pointer-events: none;
}

.fe-side {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.fe-detail-list {
    display: flex;
    flex-direction: column;
}

.fe-detail-row {
    padding: 13px 16px;
    border-bottom: 1px solid var(--fe-line);
}

.fe-detail-row:last-child {
    border-bottom: 0;
}

.fe-detail-key {
    color: var(--fe-muted);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.fe-detail-value {
    margin-top: 4px;
    color: var(--fe-text);
    font-size: 12.5px;
    line-height: 1.45;
    font-weight: 800;
    word-break: break-word;
}

.fe-tip {
    padding: 16px;
    border: 1px solid var(--fe-line);
    border-radius: 22px;
    background: var(--fe-teal-soft);
}

.fe-tip-title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--fe-teal);
    font-size: 13px;
    font-weight: 900;
}

.fe-tip-text {
    margin: 7px 0 0;
    color: #115e59;
    font-size: 12.5px;
    line-height: 1.55;
    font-weight: 700;
}

.fe-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.fe-actions .fe-btn {
    width: 100%;
}

@media (max-width: 860px) {
    .fe-layout {
        grid-template-columns: 1fr;
    }

    .fe-side {
        order: -1;
    }
}

@media (max-width: 640px) {
    .fe-page {
        padding: 14px;
    }

    .fe-title {
        font-size: 24px;
    }

    .fe-panel {
        border-radius: 22px;
    }

    .fe-panel-head,
    .fe-body {
        padding-left: 16px;
        padding-right: 16px;
    }

    .fe-footer {
        align-items: stretch;
        flex-direction: column;
    }

    .fe-footer .fe-btn {
        width: 100%;
    }

    .fe-check-title-row {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<div class="fe-page">
    <div class="fe-wrap">
        <div class="fe-top">
            <div class="fe-header">
                <a href="<?= fe($sectionUrl) ?>" class="fe-back" aria-label="Back to <?= fe($sectionLabel) ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15 18L9 12L15 6"></path>
                    </svg>
                </a>

                <div>
                    <h1 class="fe-title"><?= fe($label) ?></h1>

                    <?php if ($description !== ''): ?>
                        <p class="fe-description"><?= fe($description) ?></p>
                    <?php else: ?>
                        <p class="fe-description">Update this setting and save your changes.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($flash_success || $flash_error): ?>
            <div class="fe-flashes">
                <?php if ($flash_success): ?>
                    <div class="fe-flash success">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M9 12L11 14L15 10"></path>
                            <circle cx="12" cy="12" r="9"></circle>
                        </svg>
                        <span><?= fe($flash_success) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($flash_error): ?>
                    <div class="fe-flash error">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 8V12"></path>
                            <path d="M12 16H12.01"></path>
                        </svg>
                        <span><?= fe($flash_error) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section class="fe-panel">
            <div class="fe-panel-head">
                
            </div>

            <div class="fe-body">
                <div class="fe-layout">
                    <form
                        method="POST"
                        action="<?= fe($baseUrl . '/admin/settings/save-one') ?>"
                        <?= $formEnctype ?>
                        class="fe-form-card"
                        id="settingEditForm"
                    >
                        <?= Csrf::inputField(); ?>

                        <input type="hidden" name="setting_group" value="<?= fe($group) ?>">
                        <input type="hidden" name="setting_key" value="<?= fe($fieldKey) ?>">
                        <input type="hidden" name="input_type" value="<?= fe($type) ?>">
                        <input type="hidden" name="redirect_to" value="<?= fe($currentUrl) ?>">

                        <div class="fe-form-head">
                            <div class="fe-form-title">Setting Value</div>
                            <div class="fe-form-subtitle">Update the value below, then click Save Setting.</div>
                        </div>

                        <div class="fe-form-body">
                            <?php if ($isCheckbox): ?>
                                <input type="hidden" name="setting_value" value="0">

    <label class="fe-check-card" for="fe_setting_value">
        <input
            type="checkbox"
            id="fe_setting_value"
            name="setting_value"
            value="1"
            class="fe-check-input"
            <?= $isEnabled ? 'checked' : '' ?>
        >

        <span class="fe-check-box"></span>

        <span class="fe-check-main">
            <span class="fe-check-title-row">
                <span class="fe-check-title"><?= fe($label) ?></span>
                <span class="fe-pill <?= $isEnabled ? 'green' : 'red' ?>" id="feStatusPill">
                    <?= $isEnabled ? 'Enabled' : 'Disabled' ?>
                </span>
            </span>

            <span class="fe-check-desc">
                <?= fe($description !== '' ? $description : 'Check this box to enable this setting.') ?>
            </span>
        </span>
    </label>

                            <?php elseif ($isTextarea): ?>
                                <div class="fe-field">
                                    <label class="fe-label" for="fe_setting_value"><?= fe($label) ?></label>

                                    <textarea
                                        id="fe_setting_value"
                                        name="setting_value"
                                        class="fe-textarea"
                                        placeholder="Enter <?= fe(strtolower($label)) ?>"
                                    ><?= fe($value) ?></textarea>

                                    <span class="fe-help" id="feCharCount"></span>
                                </div>

                            <?php elseif ($isSelect): ?>
                                <div class="fe-field">
                                    <label class="fe-label" for="fe_setting_value"><?= fe($label) ?></label>

                                    <div class="fe-select-wrap">
                                        <select id="fe_setting_value" name="setting_value" class="fe-select">
                                            <?php foreach ($options as $optionValue => $optionLabel): ?>
                                                <option
                                                    value="<?= fe((string) $optionValue) ?>"
                                                    <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>
                                                >
                                                    <?= fe((string) $optionLabel) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <?php if ($default !== ''): ?>
                                        <span class="fe-help">Default: <?= fe($default) ?></span>
                                    <?php endif; ?>
                                </div>

                            <?php elseif ($isFile): ?>
                                <div class="fe-field">
                                    <label class="fe-label">Upload File</label>

                                    <?php if (trim($value) !== ''): ?>
                                        <div class="fe-file-current">
                                            <span class="fe-file-current-icon">
                                                <?= field_type_icon('file') ?>
                                            </span>
                                            <span>Current file: <?= fe($value) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <label class="fe-file-area" for="fe_setting_file">
                                        <input
                                            id="fe_setting_file"
                                            type="file"
                                            name="setting_file"
                                            accept=".jpg,.jpeg,.png,.webp,.gif,.ico,.svg"
                                        >

                                        <span class="fe-file-icon">
                                            <?= field_type_icon('file') ?>
                                        </span>

                                        <div class="fe-file-title" id="feFileName">Choose a file to upload</div>
                                        <div class="fe-file-hint">Allowed formats: JPG, PNG, WEBP, GIF, ICO, SVG. Max file size depends on your backend limit.</div>
                                    </label>
                                </div>

                            <?php elseif ($isNumber): ?>
                                <div class="fe-field">
                                    <label class="fe-label" for="fe_setting_value"><?= fe($label) ?></label>

                                    <input
                                        id="fe_setting_value"
                                        type="number"
                                        name="setting_value"
                                        class="fe-input"
                                        value="<?= fe($value) ?>"
                                        placeholder="Enter numeric value"
                                    >

                                    <?php if ($default !== ''): ?>
                                        <span class="fe-help">Default: <?= fe($default) ?></span>
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>
                                <div class="fe-field">
                                    <label class="fe-label" for="fe_setting_value"><?= fe($label) ?></label>

                                    <input
                                        id="fe_setting_value"
                                        type="<?= fe($inputType) ?>"
                                        name="setting_value"
                                        class="fe-input"
                                        value="<?= fe($value) ?>"
                                        placeholder="Enter <?= fe(strtolower($label)) ?>"
                                    >

                                    <?php if ($default !== ''): ?>
                                        <span class="fe-help">Default: <?= fe($default) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="fe-footer">
                            <span class="fe-footer-note">Your changes will be saved to the system settings table.</span>

                            <button type="submit" class="fe-btn fe-btn-primary" id="saveSettingBtn">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M19 21H5A2 2 0 0 1 3 19V5A2 2 0 0 1 5 3H16L21 8V19A2 2 0 0 1 19 21Z"></path>
                                    <path d="M17 21V13H7V21"></path>
                                    <path d="M7 3V8H15"></path>
                                </svg>
                                Save Setting
                            </button>
                        </div>
                    </form>

                    <aside class="fe-side">
                        <div class="fe-side-card">
                            <div class="fe-side-head">
                                <div class="fe-side-title">Field Details</div>
                                <div class="fe-side-subtitle">This is the backend setting information.</div>
                            </div>

                            <div class="fe-detail-list">
                                <div class="fe-detail-row">
                                    <div class="fe-detail-key">Section</div>
                                    <div class="fe-detail-value"><?= fe($sectionLabel) ?></div>
                                </div>

                                <div class="fe-detail-row">
                                    <div class="fe-detail-key">Setting Group</div>
                                    <div class="fe-detail-value"><?= fe($group) ?></div>
                                </div>

                                <div class="fe-detail-row">
                                    <div class="fe-detail-key">Setting Key</div>
                                    <div class="fe-detail-value"><?= fe($fieldKey) ?></div>
                                </div>

                                <div class="fe-detail-row">
                                    <div class="fe-detail-key">Input Type</div>
                                    <div class="fe-detail-value">
                                        <span class="fe-pill teal"><?= fe($typeLabel[$type] ?? $type) ?></span>
                                    </div>
                                </div>

                                <?php if ($default !== ''): ?>
                                    <div class="fe-detail-row">
                                        <div class="fe-detail-key">Default</div>
                                        <div class="fe-detail-value"><?= fe($default) ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$isFile && trim($value) !== ''): ?>
                                    <div class="fe-detail-row">
                                        <div class="fe-detail-key">Current Value</div>
                                        <div class="fe-detail-value"><?= fe(fe_short($value, 90)) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="fe-tip">
                            <div class="fe-tip-title">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9"></circle>
                                    <path d="M12 16V12"></path>
                                    <path d="M12 8H12.01"></path>
                                </svg>
                                About this setting
                            </div>

                            <p class="fe-tip-text">
                                <?= fe($description !== '' ? $description : 'No additional description is available for this setting.') ?>
                            </p>
                        </div>

                        <div class="fe-actions">
                            <a href="<?= fe($sectionUrl) ?>" class="fe-btn fe-btn-ghost">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M15 18L9 12L15 6"></path>
                                </svg>
                                Back to <?= fe($sectionLabel) ?>
                            </a>

                            <a href="<?= fe($baseUrl . '/admin/settings') ?>" class="fe-btn fe-btn-ghost">
                                <?= section_icon($sectionKey) ?>
                                All Settings
                            </a>
                        </div>
                    </aside>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    'use strict';

    var checkbox = document.getElementById('fe_setting_value');
    var statusPill = document.getElementById('feStatusPill');

    if (checkbox && statusPill && checkbox.type === 'checkbox') {
        checkbox.addEventListener('change', function () {
            statusPill.textContent = checkbox.checked ? 'Enabled' : 'Disabled';
            statusPill.classList.toggle('green', checkbox.checked);
            statusPill.classList.toggle('red', !checkbox.checked);
        });
    }

    var valueInput = document.getElementById('fe_setting_value');
    var charCounter = document.getElementById('feCharCount');

    if (valueInput && charCounter && valueInput.tagName.toLowerCase() === 'textarea') {
        function updateCount() {
            var count = valueInput.value.length;
            charCounter.textContent = count + ' character' + (count === 1 ? '' : 's');
        }

        valueInput.addEventListener('input', updateCount);
        updateCount();
    }

    var fileInput = document.getElementById('fe_setting_file');
    var fileNameEl = document.getElementById('feFileName');

    if (fileInput && fileNameEl) {
        fileInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                fileNameEl.textContent = this.files[0].name;
            } else {
                fileNameEl.textContent = 'Choose a file to upload';
            }
        });
    }

    var form = document.getElementById('settingEditForm');
    var saveBtn = document.getElementById('saveSettingBtn');

    if (form && saveBtn) {
        form.addEventListener('submit', function () {
            saveBtn.classList.add('is-loading');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        });
    }
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';