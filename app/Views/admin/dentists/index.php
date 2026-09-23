<?php

use App\Core\Csrf;

$pageTitle = 'Dentist Management';

$dentists = isset($dentists) && is_array($dentists) ? $dentists : [];
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

$baseUrl = '/DentalClinic/public';

if (!function_exists('admin_view_e')) {
    function admin_view_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_dentist_name')) {
    function admin_dentist_name(array $dentist): string
    {
        $name = trim((string) (
            ($dentist['first_name'] ?? '') . ' ' .
            ($dentist['middle_name'] ?? '') . ' ' .
            ($dentist['last_name'] ?? '')
        ));

        return $name !== '' ? 'Dr. ' . preg_replace('/\s+/', ' ', $name) : 'Unnamed Dentist';
    }
}

if (!function_exists('admin_dentist_initials')) {
    function admin_dentist_initials(array $dentist): string
    {
        $first = trim((string) ($dentist['first_name'] ?? ''));
        $last = trim((string) ($dentist['last_name'] ?? ''));

        $initials = '';

        if ($first !== '') {
            $initials .= strtoupper(substr($first, 0, 1));
        }

        if ($last !== '') {
            $initials .= strtoupper(substr($last, 0, 1));
        }

        return $initials !== '' ? $initials : 'DR';
    }
}

if (!function_exists('admin_dentist_json')) {
    function admin_dentist_json(array $payload): string
    {
        return htmlspecialchars(
            (string) json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('admin_dentist_icon')) {
    function admin_dentist_icon(string $name): string
    {
        $icons = [
            'search'   => '<circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.4-3.4"></path>',
            'refresh'  => '<path d="M20 12a8 8 0 1 1-2.3-5.6"></path><path d="M20 4v6h-6"></path>',
            'filter'   => '<path d="M3 5h18"></path><path d="M6 12h12"></path><path d="M10 19h4"></path>',
            'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"></path><path d="M19.4 15a1.8 1.8 0 0 0 .36 1.98l.05.05a2.1 2.1 0 0 1-2.97 2.97l-.05-.05A1.8 1.8 0 0 0 14.8 19.6a1.8 1.8 0 0 0-1 .57V20.3a2.1 2.1 0 0 1-4.2 0v-.08a1.8 1.8 0 0 0-1-.57 1.8 1.8 0 0 0-1.98.36l-.05.05a2.1 2.1 0 0 1-2.97-2.97l.05-.05A1.8 1.8 0 0 0 4 15.2a1.8 1.8 0 0 0-.57-1H3.3a2.1 2.1 0 0 1 0-4.2h.08a1.8 1.8 0 0 0 .57-1 1.8 1.8 0 0 0-.36-1.98l-.05-.05A2.1 2.1 0 0 1 6.5 4l.05.05A1.8 1.8 0 0 0 8.4 4.4a1.8 1.8 0 0 0 1-.57V3.7a2.1 2.1 0 0 1 4.2 0v.08a1.8 1.8 0 0 0 1 .57 1.8 1.8 0 0 0 1.98-.36l.05-.05a2.1 2.1 0 0 1 2.97 2.97l-.05.05A1.8 1.8 0 0 0 20 8.8a1.8 1.8 0 0 0 .57 1h.13a2.1 2.1 0 0 1 0 4.2h-.08a1.8 1.8 0 0 0-1 .57z"></path>',
            'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect>',
            'list'     => '<path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path>',
            'plus'     => '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
            'edit'     => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>',
            'user'     => '<path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle>',
            'license'  => '<rect x="4" y="4" width="16" height="16" rx="2"></rect><path d="M8 9h8"></path><path d="M8 13h8"></path><path d="M8 17h5"></path>',
            'check'    => '<path d="M20 6 9 17l-5-5"></path>',
            'warning'  => '<path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.3 3.6 2.4 17.2A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.8L13.7 3.6a2 2 0 0 0-3.4 0z"></path>',
            'close'    => '<path d="M18 6 6 18"></path><path d="M6 6l12 12"></path>',
        ];

        $path = $icons[$name] ?? $icons['user'];

        return '
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2.1" stroke-linecap="round"
                stroke-linejoin="round" aria-hidden="true">
                ' . $path . '
            </svg>
        ';
    }
}

$totalDentists = count($dentists);

$activeDentists = count(array_filter($dentists, static function (array $dentist): bool {
    return (int) ($dentist['is_active'] ?? 1) === 1;
}));

$inactiveDentists = max(0, $totalDentists - $activeDentists);

$ownerDentists = count(array_filter($dentists, static function (array $dentist): bool {
    return (int) ($dentist['is_owner'] ?? 0) === 1;
}));

$specializationOptions = [];

foreach ($dentists as $dentist) {
    $specialization = trim((string) ($dentist['specialization'] ?? ''));

    if ($specialization !== '') {
        $specializationOptions[strtolower($specialization)] = $specialization;
    }
}

ksort($specializationOptions);

ob_start();
?>

<style>
.dm-page,
.dm-page * {
    box-sizing: border-box;
}

.dm-page {
    --bg: #eef3f7;
    --panel: #ffffff;
    --panel-soft: #f8fafc;
    --thead: #eef2f7;
    --text: #162033;
    --text-2: #344054;
    --muted: #667085;
    --faint: #98a2b3;
    --line: #e4e7ec;
    --line-2: #d0d5dd;
    --brand: #0f766e;
    --brand-2: #115e59;
    --brand-soft: #e7f6f4;
    --green: #047857;
    --green-soft: #ecfdf5;
    --red: #dc2626;
    --red-soft: #fef2f2;
    --blue: #2563eb;
    --blue-soft: #eff6ff;
    --amber: #d97706;
    --amber-soft: #fffbeb;
    --purple: #6d28d9;
    --purple-soft: #f5f3ff;

    min-height: 100vh;
    padding: 14px;
    background: var(--bg);
    color: var(--text);
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.dm-shell {
    max-width: 1460px;
    margin: 0 auto;
}

.dm-board {
    background: var(--panel);
    border: 1px solid rgba(16, 24, 40, .08);
    border-radius: 1px;
    box-shadow: 0 10px 28px rgba(16, 24, 40, .10);
    overflow: hidden;
}

.dm-toolbar {
    min-height: 58px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--line);
    background: #f7f9fc;
    display: grid;
    grid-template-columns: minmax(170px, 1fr) auto auto auto 1fr auto auto auto;
    gap: 9px;
    align-items: center;
}

.dm-search {
    height: 34px;
    min-width: 180px;
    border: 1px solid var(--line);
    border-radius: 6px;
    background: #ffffff;
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 0 10px;
    color: var(--faint);
}

.dm-search input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--text);
    font: inherit;
    font-size: 12px;
    font-weight: 650;
}

.dm-select {
    height: 34px;
    border: 1px solid var(--line);
    border-radius: 6px;
    background: #ffffff;
    color: var(--text-2);
    padding: 0 30px 0 10px;
    font: inherit;
    font-size: 12px;
    font-weight: 750;
    outline: 0;
    cursor: pointer;
    min-width: 145px;
}

.dm-btn {
    height: 34px;
    border: 1px solid transparent;
    border-radius: 6px;
    padding: 0 12px;
    font: inherit;
    font-size: 12px;
    font-weight: 850;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    white-space: nowrap;
}

.dm-btn.primary {
    background: #ffffff;
    color: var(--brand);
    border-color: var(--brand);
}

.dm-btn.primary:hover {
    background: var(--brand-soft);
}

.dm-btn.filled {
    background: var(--brand);
    color: #ffffff;
    border-color: var(--brand);
    box-shadow: 0 6px 14px rgba(15, 118, 110, .18);
}

.dm-btn.filled:hover {
    background: var(--brand-2);
    border-color: var(--brand-2);
}

.dm-btn.ghost {
    background: #ffffff;
    color: var(--text-2);
    border-color: var(--line);
}

.dm-btn.ghost:hover {
    background: var(--panel-soft);
}

.dm-btn.danger {
    background: #ffffff;
    color: var(--red);
    border-color: #fca5a5;
}

.dm-btn.danger:hover {
    background: var(--red-soft);
}

.dm-toolbar-spacer {
    min-width: 6px;
}

.dm-settings {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: var(--text-2);
    font-size: 12px;
    font-weight: 850;
    white-space: nowrap;
}

.dm-view-toggle {
    height: 34px;
    display: inline-flex;
    border: 1px solid var(--line);
    border-radius: 7px;
    overflow: hidden;
    background: #ffffff;
}

.dm-view-toggle span {
    width: 34px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--muted);
}

.dm-view-toggle span + span {
    border-left: 1px solid var(--line);
}

.dm-view-toggle .active {
    background: var(--panel-soft);
    color: var(--brand);
}

.dm-flashes {
    display: grid;
    gap: 8px;
    padding: 10px 14px 0;
    background: #f7f9fc;
}

.dm-flash {
    min-height: 38px;
    border-radius: 8px;
    padding: 9px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 800;
    border: 1px solid transparent;
}

.dm-flash.success {
    background: var(--green-soft);
    color: var(--green);
    border-color: #bbf7d0;
}

.dm-flash.error {
    background: var(--red-soft);
    color: var(--red);
    border-color: #fecaca;
}

.dm-summary {
    min-height: 44px;
    padding: 8px 14px;
    border-bottom: 1px solid var(--line);
    background: #ffffff;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.dm-summary-card {
    min-height: 27px;
    padding: 0 10px;
    border: 1px solid var(--line);
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--panel-soft);
    color: var(--muted);
    font-size: 11.5px;
    font-weight: 850;
}

.dm-summary-card strong {
    color: var(--text);
}

.dm-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.dm-table {
    width: 100%;
    min-width: 1180px;
    border-collapse: separate;
    border-spacing: 0;
}

.dm-table th {
    height: 40px;
    padding: 0 14px;
    background: var(--thead);
    border-bottom: 1px solid var(--line);
    border-right: 1px solid var(--line);
    color: #5f6b7a;
    font-size: 11px;
    font-weight: 900;
    text-align: left;
    white-space: nowrap;
}

.dm-table th:last-child,
.dm-table td:last-child {
    border-right: 0;
}

.dm-th-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.dm-sort {
    margin-left: 6px;
    color: #b0bac7;
    font-size: 10px;
}

.dm-table td {
    height: 52px;
    padding: 8px 14px;
    border-bottom: 1px solid #edf0f4;
    border-right: 1px solid #edf0f4;
    color: var(--text);
    font-size: 12px;
    font-weight: 650;
    vertical-align: middle;
    background: #ffffff;
}

.dm-table tbody tr:hover td {
    background: #fbfcfd;
}

.dm-dentist {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 240px;
}

.dm-avatar {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: linear-gradient(135deg, #14b8a6, #0f766e);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 9.5px;
    font-weight: 900;
    flex-shrink: 0;
}

.dm-dentist-name {
    color: var(--text);
    font-size: 12px;
    font-weight: 850;
    white-space: nowrap;
}

.dm-dentist-code {
    display: block;
    margin-top: 2px;
    color: var(--faint);
    font-size: 11px;
    font-weight: 700;
}

.dm-mono {
    color: var(--text-2);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: 11px;
    font-weight: 750;
    white-space: nowrap;
}

.dm-pill {
    width: fit-content;
    min-height: 23px;
    padding: 0 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 850;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}

.dm-pill::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: currentColor;
}

.dm-pill.active {
    background: var(--green-soft);
    color: var(--green);
}

.dm-pill.inactive {
    background: var(--red-soft);
    color: var(--red);
}

.dm-pill.owner {
    background: var(--blue-soft);
    color: var(--blue);
}

.dm-pill.not-owner {
    background: #f2f4f7;
    color: #475467;
}

.dm-pill.license {
    background: var(--purple-soft);
    color: var(--purple);
}

.dm-pill.specialization {
    background: var(--amber-soft);
    color: var(--amber);
}

.dm-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.dm-row-btn {
    height: 29px;
    border: 1px solid var(--brand);
    border-radius: 6px;
    background: #ffffff;
    color: var(--brand);
    padding: 0 9px;
    font: inherit;
    font-size: 11.5px;
    font-weight: 850;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    cursor: pointer;
}

.dm-row-btn:hover {
    background: var(--brand-soft);
}

.dm-row-btn.danger {
    border-color: #ef4444;
    color: var(--red);
}

.dm-row-btn.danger:hover {
    background: var(--red-soft);
}

.dm-row-btn.success {
    border-color: #10b981;
    color: var(--green);
}

.dm-row-btn.success:hover {
    background: var(--green-soft);
}

.dm-empty {
    padding: 44px 18px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
    font-weight: 750;
}

.dm-hidden {
    display: none !important;
}

.dm-footer {
    min-height: 54px;
    padding: 10px 14px;
    border-top: 1px solid var(--line);
    background: #f7f9fc;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
}

.dm-page-info {
    color: var(--muted);
    font-size: 12px;
    font-weight: 800;
}

.dm-pagination {
    display: flex;
    align-items: center;
    gap: 6px;
}

.dm-page-btn {
    min-width: 31px;
    height: 31px;
    border: 1px solid var(--line);
    background: #ffffff;
    color: #98a2b3;
    border-radius: 6px;
    padding: 0 9px;
    font-size: 12px;
    font-weight: 850;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.dm-page-btn:hover:not(:disabled) {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
}

.dm-page-btn.active {
    border-color: var(--brand);
    color: var(--brand);
    box-shadow: 0 0 0 3px rgba(15, 118, 110, .10);
}

.dm-page-btn:disabled {
    opacity: .45;
    cursor: default;
}

/* General modal */
.dm-modal-layer {
    position: fixed;
    inset: 0;
    z-index: 9000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: clamp(8px, 2vw, 20px);
    background: rgba(15, 23, 42, .48);
    backdrop-filter: blur(10px);
    overflow-y: auto;
    overscroll-behavior: contain;
}

.dm-modal-layer.open {
    display: flex;
}

.dm-modal {
    width: min(500px, 100%);
    max-height: calc(100dvh - 24px);
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 28px 90px rgba(15, 23, 42, .30);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.dm-modal-head {
    flex: 0 0 auto;
    padding: 16px 18px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.dm-modal-title-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.dm-modal-icon {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: var(--brand-soft);
    color: var(--brand);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.dm-modal-icon.danger {
    background: var(--red-soft);
    color: var(--red);
}

.dm-modal-title {
    color: var(--text);
    font-size: 16px;
    font-weight: 900;
}

.dm-modal-subtitle {
    margin-top: 3px;
    color: var(--muted);
    font-size: 12.5px;
    font-weight: 650;
}

.dm-modal-close {
    width: 36px;
    height: 36px;
    border: 1px solid var(--line);
    border-radius: 999px;
    background: #ffffff;
    color: var(--text-2);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.dm-modal-close:hover {
    background: var(--panel-soft);
}

.dm-modal-body {
    flex: 1 1 auto;
    min-height: 0;
    padding: 18px;
    overflow-y: auto;
}

.dm-confirm-copy {
    margin: 0;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.55;
    font-weight: 650;
}

.dm-confirm-user {
    margin-top: 13px;
    padding: 12px;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: var(--panel-soft);
    color: var(--text);
    font-size: 12px;
    font-weight: 850;
    word-break: break-word;
}

.dm-modal-foot {
    flex: 0 0 auto;
    padding: 14px 18px;
    border-top: 1px solid var(--line);
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.dm-field {
    display: grid;
    gap: 7px;
}

.dm-label {
    color: var(--text-2);
    font-size: 12px;
    font-weight: 900;
}

.dm-input,
.dm-textarea {
    width: 100%;
    border: 1px solid var(--line-2);
    border-radius: 9px;
    background: #ffffff;
    color: var(--text);
    font: inherit;
    font-size: 13px;
    font-weight: 650;
    outline: none;
}

.dm-input {
    height: 38px;
    padding: 0 10px;
}

.dm-textarea {
    min-height: 80px;
    padding: 10px;
    resize: vertical;
}

.dm-input:focus,
.dm-textarea:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(15, 118, 110, .12);
}

.dm-check-card {
    min-height: 48px;
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 10px 12px;
    background: var(--panel-soft);
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.dm-check-card input {
    width: 17px;
    height: 17px;
    accent-color: var(--brand);
}

.dm-check-card span {
    color: var(--text);
    font-size: 13px;
    font-weight: 850;
}

/* Simple, responsive, scrollable Edit Dentist modal */
#editDentistModal.dm-modal-layer {
    padding: clamp(8px, 2vw, 18px);
}

#editDentistModal .dm-edit-modal {
    width: min(720px, 100%);
    max-height: calc(100dvh - 24px);
    height: auto;
}

#editDentistModal .dm-edit-form {
    display: flex;
    flex-direction: column;
    min-height: 0;
    max-height: inherit;
}

#editDentistModal .dm-modal-head {
    padding: 12px 14px;
}

#editDentistModal .dm-modal-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
}

#editDentistModal .dm-modal-title {
    font-size: 14.5px;
}

#editDentistModal .dm-modal-subtitle {
    font-size: 11.5px;
    margin-top: 1px;
}

#editDentistModal .dm-modal-close {
    width: 31px;
    height: 31px;
}

#editDentistModal .dm-edit-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 12px 14px;
    -webkit-overflow-scrolling: touch;
}

#editDentistModal .dm-edit-body::-webkit-scrollbar {
    width: 8px;
}

#editDentistModal .dm-edit-body::-webkit-scrollbar-track {
    background: transparent;
}

#editDentistModal .dm-edit-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 999px;
}

#editDentistModal .dm-simple-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

#editDentistModal .dm-full {
    grid-column: 1 / -1;
}

#editDentistModal .dm-field {
    gap: 4px;
}

#editDentistModal .dm-label {
    font-size: 11px;
    font-weight: 850;
}

#editDentistModal .dm-input {
    height: 34px;
    min-height: 34px;
    border-radius: 7px;
    padding: 0 9px;
    font-size: 12px;
}

#editDentistModal .dm-textarea.small {
    min-height: 58px;
    max-height: 120px;
    border-radius: 7px;
    padding: 8px 9px;
    font-size: 12px;
    line-height: 1.35;
    resize: vertical;
}

#editDentistModal .dm-check-card.compact {
    min-height: 36px;
    border-radius: 7px;
    padding: 7px 9px;
    gap: 7px;
}

#editDentistModal .dm-check-card.compact input {
    width: 14px;
    height: 14px;
}

#editDentistModal .dm-check-card.compact span {
    font-size: 12px;
    font-weight: 800;
}

#editDentistModal .dm-edit-foot {
    flex: 0 0 auto;
    padding: 10px 14px;
    border-top: 1px solid var(--line);
    background: #ffffff;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

#editDentistModal .dm-edit-foot .dm-btn {
    height: 34px;
    min-height: 34px;
    border-radius: 7px;
    padding: 0 12px;
    font-size: 12px;
}

body.dm-modal-open {
    overflow: hidden;
}

body.dm-modal-open .dm-board {
    filter: blur(4px);
    opacity: .75;
    pointer-events: none;
}

@media (max-width: 1240px) {
    .dm-toolbar {
        grid-template-columns: 1fr 1fr 1fr;
    }

    .dm-toolbar-spacer,
    .dm-settings,
    .dm-view-toggle {
        display: none;
    }
}

@media (max-width: 760px) {
    .dm-page {
        padding: 10px;
    }

    .dm-toolbar {
        grid-template-columns: 1fr;
    }

    .dm-search,
    .dm-select,
    .dm-btn {
        width: 100%;
    }

    .dm-footer {
        align-items: stretch;
        flex-direction: column;
    }

    .dm-pagination {
        flex-wrap: wrap;
    }

    .dm-modal-layer {
        align-items: center;
        padding: 8px;
    }

    .dm-modal {
        width: 100%;
        max-height: calc(100dvh - 16px);
    }

    .dm-modal-foot {
        flex-direction: column-reverse;
    }

    .dm-modal-foot .dm-btn {
        width: 100%;
    }

    #editDentistModal .dm-edit-modal {
        width: 100%;
        max-height: calc(100dvh - 16px);
    }

    #editDentistModal .dm-simple-grid {
        grid-template-columns: 1fr;
    }

    #editDentistModal .dm-full {
        grid-column: auto;
    }

    #editDentistModal .dm-edit-foot {
        flex-direction: column-reverse;
    }

    #editDentistModal .dm-edit-foot .dm-btn {
        width: 100%;
    }
}

@media (max-height: 560px) {
    .dm-modal-layer {
        align-items: flex-start;
    }

    #editDentistModal .dm-edit-modal,
    .dm-modal {
        max-height: calc(100dvh - 16px);
    }

    #editDentistModal .dm-modal-head {
        padding: 9px 12px;
    }

    #editDentistModal .dm-edit-body {
        padding: 10px 12px;
    }

    #editDentistModal .dm-edit-foot {
        padding: 8px 12px;
    }
}
</style>

<div class="dm-page">
    <div class="dm-shell">
        <section class="dm-board">

            <div class="dm-toolbar">
                <label class="dm-search">
                    <?= admin_dentist_icon('search') ?>
                    <input
                        type="search"
                        id="dentistSearchInput"
                        placeholder="Search dentists"
                        autocomplete="off"
                    >
                </label>

                <select class="dm-select" id="dentistSpecializationFilter">
                    <option value="">All Specializations</option>
                    <?php foreach ($specializationOptions as $specializationKey => $specializationName): ?>
                        <option value="<?= admin_view_e($specializationKey) ?>">
                            <?= admin_view_e($specializationName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select class="dm-select" id="dentistStatusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="owner">Owner</option>
                    <option value="not-owner">Not Owner</option>
                </select>

                <button type="button" class="dm-btn primary" id="dentistAdvancedFilter">
                    <?= admin_dentist_icon('filter') ?>
                    Apply Filter
                </button>

                <span class="dm-toolbar-spacer"></span>

                <span class="dm-settings">
                    <?= admin_dentist_icon('settings') ?>
                    Dentist Settings
                </span>

                <span class="dm-view-toggle" aria-hidden="true">
                    <span class="active"><?= admin_dentist_icon('list') ?></span>
                    <span><?= admin_dentist_icon('grid') ?></span>
                </span>

                <a href="<?= admin_view_e($baseUrl . '/admin/dentists/create') ?>" class="dm-btn filled">
                    <?= admin_dentist_icon('plus') ?>
                    Add Dentist
                </a>

                <button type="button" class="dm-btn ghost" id="dentistResetFilter">
                    <?= admin_dentist_icon('refresh') ?>
                    Reset
                </button>
            </div>

            <?php if ($flash_success || $flash_error): ?>
                <div class="dm-flashes">
                    <?php if ($flash_success): ?>
                        <div class="dm-flash success">
                            <?= admin_dentist_icon('check') ?>
                            <span><?= admin_view_e($flash_success) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($flash_error): ?>
                        <div class="dm-flash error">
                            <?= admin_dentist_icon('warning') ?>
                            <span><?= admin_view_e($flash_error) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="dm-summary">
                <span class="dm-summary-card">Total Dentists <strong><?= (int) $totalDentists ?></strong></span>
                <span class="dm-summary-card">Active <strong><?= (int) $activeDentists ?></strong></span>
                <span class="dm-summary-card">Inactive <strong><?= (int) $inactiveDentists ?></strong></span>
                <span class="dm-summary-card">Owners <strong><?= (int) $ownerDentists ?></strong></span>
            </div>

            <div class="dm-table-wrap">
                <table class="dm-table">
                    <thead>
                        <tr>
                            <th>
                                <span class="dm-th-label">
                                    <?= admin_dentist_icon('user') ?>
                                    Dentist
                                    <span class="dm-sort">↕</span>
                                </span>
                            </th>
                            <th>Username <span class="dm-sort">↕</span></th>
                            <th>Email <span class="dm-sort">↕</span></th>
                            <th>License <span class="dm-sort">↕</span></th>
                            <th>Specialization <span class="dm-sort">↕</span></th>
                            <th>Owner <span class="dm-sort">↕</span></th>
                            <th>Status <span class="dm-sort">↕</span></th>
                            <th style="width:230px;">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($dentists)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="dm-empty">No dentist profiles found.</div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($dentists as $dentist): ?>
                            <?php
                                $dentistId = (int) ($dentist['dentist_id'] ?? 0);
                                $userId = (int) ($dentist['user_id'] ?? 0);
                                $isActive = (int) ($dentist['is_active'] ?? 1) === 1;
                                $isOwner = (int) ($dentist['is_owner'] ?? 0) === 1;

                                $firstName = trim((string) ($dentist['first_name'] ?? ''));
                                $middleName = trim((string) ($dentist['middle_name'] ?? ''));
                                $lastName = trim((string) ($dentist['last_name'] ?? ''));

                                $dentistName = admin_dentist_name($dentist);
                                $dentistCode = trim((string) ($dentist['dentist_code'] ?? ('DEN-' . str_pad((string) $dentistId, 4, '0', STR_PAD_LEFT))));
                                $username = trim((string) ($dentist['username'] ?? ''));
                                $email = trim((string) ($dentist['email'] ?? ''));
                                $contactNumber = trim((string) ($dentist['contact_number'] ?? ''));
                                $license = trim((string) ($dentist['license_number'] ?? ''));
                                $specialization = trim((string) ($dentist['specialization'] ?? ''));
                                $consultationFee = (float) ($dentist['consultation_fee'] ?? 0);
                                $bio = trim((string) ($dentist['bio'] ?? ''));

                                $statusKey = $isActive ? 'active' : 'inactive';
                                $ownerKey = $isOwner ? 'owner' : 'not-owner';
                                $specializationKey = strtolower($specialization);

                                $searchText = strtolower(trim(implode(' ', [
                                    $dentistName,
                                    $dentistCode,
                                    $username,
                                    $email,
                                    $license,
                                    $specialization,
                                    $statusKey,
                                    $isOwner ? 'owner yes' : 'not owner no',
                                ])));

                                $editPayload = [
                                    'dentist_id' => $dentistId,
                                    'user_id' => $userId,
                                    'first_name' => $firstName,
                                    'middle_name' => $middleName,
                                    'last_name' => $lastName,
                                    'username' => $username,
                                    'email' => $email,
                                    'contact_number' => $contactNumber,
                                    'dentist_code' => $dentistCode,
                                    'license_number' => $license,
                                    'specialization' => $specialization,
                                    'consultation_fee' => number_format($consultationFee, 2, '.', ''),
                                    'bio' => $bio,
                                    'is_owner' => $isOwner ? 1 : 0,
                                    'is_active' => $isActive ? 1 : 0,
                                    'name' => $dentistName,
                                ];
                            ?>

                            <tr
                                class="dm-dentist-row"
                                data-search="<?= admin_view_e($searchText) ?>"
                                data-status="<?= admin_view_e($statusKey) ?>"
                                data-owner="<?= admin_view_e($ownerKey) ?>"
                                data-specialization="<?= admin_view_e($specializationKey) ?>"
                            >
                                <td>
                                    <div class="dm-dentist">
                                        <span class="dm-avatar"><?= admin_view_e(admin_dentist_initials($dentist)) ?></span>

                                        <span>
                                            <span class="dm-dentist-name"><?= admin_view_e($dentistName) ?></span>
                                            <span class="dm-dentist-code"><?= admin_view_e($dentistCode) ?></span>
                                        </span>
                                    </div>
                                </td>

                                <td><span class="dm-mono"><?= admin_view_e($username !== '' ? $username : '—') ?></span></td>
                                <td><span class="dm-mono"><?= admin_view_e($email !== '' ? $email : '—') ?></span></td>

                                <td>
                                    <?php if ($license !== ''): ?>
                                        <span class="dm-pill license"><?= admin_view_e($license) ?></span>
                                    <?php else: ?>
                                        <span class="dm-mono">—</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($specialization !== ''): ?>
                                        <span class="dm-pill specialization"><?= admin_view_e($specialization) ?></span>
                                    <?php else: ?>
                                        <span class="dm-mono">—</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="dm-pill <?= $isOwner ? 'owner' : 'not-owner' ?>">
                                        <?= $isOwner ? 'Yes' : 'No' ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="dm-pill <?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="dm-actions">
                                        <button
                                            type="button"
                                            class="dm-row-btn js-open-edit-dentist"
                                            data-dentist-id="<?= (int) $dentistId ?>"
                                            data-user-id="<?= (int) $userId ?>"
                                            data-dentist="<?= admin_dentist_json($editPayload) ?>"
                                        >
                                            <?= admin_dentist_icon('edit') ?>
                                            Edit
                                        </button>

                                        <form
                                            method="POST"
                                            action="<?= admin_view_e($baseUrl . '/admin/dentists/status') ?>"
                                            class="dm-status-form"
                                            data-dentist-id="<?= (int) $dentistId ?>"
                                            data-dentist-name="<?= admin_view_e($dentistName) ?>"
                                            data-next-status="<?= $isActive ? 0 : 1 ?>"
                                            data-action-label="<?= $isActive ? 'Deactivate' : 'Activate' ?>"
                                        >
                                            <?= Csrf::inputField(); ?>
                                            <input type="hidden" name="dentist_id" value="<?= (int) $dentistId ?>">
                                            <input type="hidden" name="is_active" value="<?= $isActive ? 0 : 1 ?>">

                                            <button
                                                type="submit"
                                                class="dm-row-btn <?= $isActive ? 'danger' : 'success' ?>"
                                            >
                                                <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr id="dentistNoMatchRow" class="dm-hidden">
                            <td colspan="8">
                                <div class="dm-empty">No dentist profiles match the selected filters.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="dm-footer">
                <div class="dm-page-info" id="dentistPageInfo">
                    Showing 0–0 of <?= (int) $totalDentists ?> dentists
                </div>

                <div class="dm-pagination" id="dentistPagination">
                    <button type="button" class="dm-page-btn" id="dentistPrevPage">‹</button>
                    <button type="button" class="dm-page-btn active" id="dentistCurrentPage">1</button>
                    <button type="button" class="dm-page-btn" id="dentistNextPage">›</button>
                </div>
            </div>
        </section>

        <div class="dm-modal-layer" id="editDentistModal" aria-hidden="true">
            <div class="dm-modal dm-edit-modal" role="dialog" aria-modal="true" aria-labelledby="editDentistTitle">
                <form
                    method="POST"
                    action="<?= admin_view_e($baseUrl . '/admin/dentists/update') ?>"
                    id="editDentistForm"
                    class="dm-edit-form"
                >
                    <?= Csrf::inputField(); ?>

                    <input type="hidden" name="dentist_id" id="editDentistId" value="">
                    <input type="hidden" name="user_id" id="editUserId" value="">
                    <input type="hidden" name="redirect_to" value="<?= admin_view_e($baseUrl . '/admin/dentists') ?>">

                    <div class="dm-modal-head">
                        <div class="dm-modal-title-wrap">
                            <div class="dm-modal-icon">
                                <?= admin_dentist_icon('edit') ?>
                            </div>

                            <div>
                                <div class="dm-modal-title" id="editDentistTitle">Edit Dentist</div>
                                <div class="dm-modal-subtitle" id="editDentistSubtitle">
                                    Update dentist profile and account details.
                                </div>
                            </div>
                        </div>

                        <button type="button" class="dm-modal-close js-close-dentist-modal" aria-label="Close">
                            <?= admin_dentist_icon('close') ?>
                        </button>
                    </div>

                    <div class="dm-modal-body dm-edit-body">
                        <div class="dm-simple-grid">
                            <div class="dm-field">
                                <label class="dm-label" for="edit_first_name">First Name</label>
                                <input id="edit_first_name" type="text" name="first_name" class="dm-input" maxlength="100" required>
                            </div>

                            <div class="dm-field">
                                <label class="dm-label" for="edit_middle_name">Middle Name</label>
                                <input id="edit_middle_name" type="text" name="middle_name" class="dm-input" maxlength="100">
                            </div>

                            <div class="dm-field">
                                <label class="dm-label" for="edit_last_name">Last Name</label>
                                <input id="edit_last_name" type="text" name="last_name" class="dm-input" maxlength="100" required>
                            </div>

                            <div class="dm-field">
                                <label class="dm-label" for="edit_username">Username</label>
                                <input id="edit_username" type="text" name="username" class="dm-input" maxlength="100" required>
                            </div>

                            <div class="dm-field">
                                <label class="dm-label" for="edit_email">Email</label>
                                <input id="edit_email" type="email" name="email" class="dm-input" maxlength="150" required>
                            </div>

                            <div class="dm-field">
                                <label class="dm-label" for="edit_contact_number">Contact Number</label>
                                <input id="edit_contact_number" type="text" name="contact_number" class="dm-input" maxlength="30" placeholder="09XXXXXXXXX">
                            </div>

                            <div class="dm-field">
                                <label class="dm-label" for="edit_dentist_code">Dentist Code</label>
                                <input id="edit_dentist_code" type="text" name="dentist_code" class="dm-input" maxlength="50" required>
                            </div>

                            <div class="dm-field">
                                <label class="dm-label" for="edit_license_number">License Number</label>
                                <input id="edit_license_number" type="text" name="license_number" class="dm-input" maxlength="100">
                            </div>

                            <div class="dm-field">
                                <label class="dm-label" for="edit_specialization">Specialization</label>
                                <input id="edit_specialization" type="text" name="specialization" class="dm-input" maxlength="150" placeholder="Example: Orthodontics">
                            </div>

                            <div class="dm-field">
                                <label class="dm-label" for="edit_consultation_fee">Consultation Fee</label>
                                <input id="edit_consultation_fee" type="number" name="consultation_fee" class="dm-input" min="0" step="0.01">
                            </div>

                            <div class="dm-field dm-full">
                                <label class="dm-label" for="edit_bio">Bio</label>
                                <textarea id="edit_bio" name="bio" class="dm-textarea small" placeholder="Short profile or dentist notes"></textarea>
                            </div>

                            <div class="dm-field">
                                <input type="hidden" name="is_owner" value="0">

                                <label class="dm-check-card compact" for="edit_is_owner">
                                    <input id="edit_is_owner" type="checkbox" name="is_owner" value="1">
                                    <span>Clinic Owner</span>
                                </label>
                            </div>

                            <div class="dm-field">
                                <input type="hidden" name="is_active" value="0">

                                <label class="dm-check-card compact" for="edit_is_active">
                                    <input id="edit_is_active" type="checkbox" name="is_active" value="1">
                                    <span>Active Dentist</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="dm-modal-foot dm-edit-foot">
                        <button type="button" class="dm-btn ghost js-close-dentist-modal">Cancel</button>
                        <button type="submit" class="dm-btn filled">Save Dentist</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="dm-modal-layer" id="statusDentistModal" aria-hidden="true">
            <div class="dm-modal" role="dialog" aria-modal="true" aria-labelledby="statusDentistTitle">
                <div class="dm-modal-head">
                    <div class="dm-modal-title-wrap">
                        <div class="dm-modal-icon danger">
                            <?= admin_dentist_icon('warning') ?>
                        </div>

                        <div>
                            <div class="dm-modal-title" id="statusDentistTitle">Confirm Status Change</div>
                            <div class="dm-modal-subtitle">This action updates the selected dentist account availability.</div>
                        </div>
                    </div>

                    <button type="button" class="dm-modal-close js-close-dentist-modal" aria-label="Close">
                        <?= admin_dentist_icon('close') ?>
                    </button>
                </div>

                <div class="dm-modal-body">
                    <p class="dm-confirm-copy" id="statusDentistText">
                        Are you sure you want to update this dentist profile?
                    </p>

                    <div class="dm-confirm-user" id="statusDentistUser">
                        Selected dentist
                    </div>
                </div>

                <div class="dm-modal-foot">
                    <button type="button" class="dm-btn ghost js-close-dentist-modal">Cancel</button>
                    <button type="button" class="dm-btn danger" id="statusDentistConfirm">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var rows = Array.prototype.slice.call(document.querySelectorAll('.dm-dentist-row'));

    var searchInput = document.getElementById('dentistSearchInput');
    var specializationFilter = document.getElementById('dentistSpecializationFilter');
    var statusFilter = document.getElementById('dentistStatusFilter');
    var applyFilter = document.getElementById('dentistAdvancedFilter');
    var resetFilter = document.getElementById('dentistResetFilter');
    var noMatchRow = document.getElementById('dentistNoMatchRow');

    var pageInfo = document.getElementById('dentistPageInfo');
    var prevPageBtn = document.getElementById('dentistPrevPage');
    var nextPageBtn = document.getElementById('dentistNextPage');
    var currentPageBtn = document.getElementById('dentistCurrentPage');

    var editModal = document.getElementById('editDentistModal');
    var editDentistForm = document.getElementById('editDentistForm');

    var statusModal = document.getElementById('statusDentistModal');
    var statusTitle = document.getElementById('statusDentistTitle');
    var statusText = document.getElementById('statusDentistText');
    var statusUser = document.getElementById('statusDentistUser');
    var statusConfirm = document.getElementById('statusDentistConfirm');

    var currentPage = 1;
    var perPage = 10;
    var filteredRows = rows.slice();
    var pendingStatusForm = null;

    function normalize(value) {
        return String(value || '').toLowerCase().trim();
    }

    function setInputValue(id, value) {
        var input = document.getElementById(id);

        if (input) {
            input.value = value === null || value === undefined ? '' : String(value);
        }
    }

    function setCheckboxValue(id, value) {
        var input = document.getElementById(id);

        if (input) {
            input.checked = String(value) === '1' || Number(value) === 1;
        }
    }

    function openModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('dm-modal-open');

        var body = modal.querySelector('.dm-edit-body, .dm-modal-body');

        if (body) {
            body.scrollTop = 0;
        }
    }

    function closeModal() {
        if (editModal) {
            editModal.classList.remove('open');
            editModal.setAttribute('aria-hidden', 'true');
        }

        if (statusModal) {
            statusModal.classList.remove('open');
            statusModal.setAttribute('aria-hidden', 'true');
        }

        document.body.classList.remove('dm-modal-open');
        pendingStatusForm = null;
    }

    function applyFilters() {
        var query = normalize(searchInput ? searchInput.value : '');
        var specialization = normalize(specializationFilter ? specializationFilter.value : '');
        var status = normalize(statusFilter ? statusFilter.value : '');

        filteredRows = rows.filter(function (row) {
            var rowSearch = normalize(row.getAttribute('data-search'));
            var rowSpecialization = normalize(row.getAttribute('data-specialization'));
            var rowStatus = normalize(row.getAttribute('data-status'));
            var rowOwner = normalize(row.getAttribute('data-owner'));

            var matched = true;

            if (query !== '') {
                matched = matched && rowSearch.indexOf(query) !== -1;
            }

            if (specialization !== '') {
                matched = matched && rowSpecialization === specialization;
            }

            if (status === 'active' || status === 'inactive') {
                matched = matched && rowStatus === status;
            }

            if (status === 'owner' || status === 'not-owner') {
                matched = matched && rowOwner === status;
            }

            return matched;
        });

        currentPage = 1;
        renderPage();
    }

    function renderPage() {
        var total = filteredRows.length;
        var totalPages = Math.max(1, Math.ceil(total / perPage));
        var start = (currentPage - 1) * perPage;
        var end = start + perPage;

        rows.forEach(function (row) {
            row.classList.add('dm-hidden');
        });

        filteredRows.slice(start, end).forEach(function (row) {
            row.classList.remove('dm-hidden');
        });

        if (noMatchRow) {
            noMatchRow.classList.toggle('dm-hidden', total !== 0 || rows.length === 0);
        }

        if (pageInfo) {
            var from = total > 0 ? start + 1 : 0;
            var to = Math.min(end, total);
            pageInfo.textContent = 'Showing ' + from + '–' + to + ' of ' + total + ' dentists';
        }

        if (currentPageBtn) {
            currentPageBtn.textContent = String(currentPage);
        }

        if (prevPageBtn) {
            prevPageBtn.disabled = currentPage <= 1;
        }

        if (nextPageBtn) {
            nextPageBtn.disabled = currentPage >= totalPages;
        }
    }

    document.querySelectorAll('.js-open-edit-dentist').forEach(function (button) {
        button.addEventListener('click', function () {
            var raw = button.getAttribute('data-dentist') || '{}';
            var data = {};

            try {
                data = JSON.parse(raw);
            } catch (error) {
                data = {};
            }

            var dentistId = data.dentist_id || button.getAttribute('data-dentist-id') || '';
            var userId = data.user_id || button.getAttribute('data-user-id') || '';

            setInputValue('editDentistId', dentistId);
            setInputValue('editUserId', userId);

            setInputValue('edit_first_name', data.first_name || '');
            setInputValue('edit_middle_name', data.middle_name || '');
            setInputValue('edit_last_name', data.last_name || '');
            setInputValue('edit_username', data.username || '');
            setInputValue('edit_email', data.email || '');
            setInputValue('edit_contact_number', data.contact_number || '');
            setInputValue('edit_dentist_code', data.dentist_code || '');
            setInputValue('edit_license_number', data.license_number || '');
            setInputValue('edit_specialization', data.specialization || '');
            setInputValue('edit_consultation_fee', data.consultation_fee || '0.00');
            setInputValue('edit_bio', data.bio || '');

            setCheckboxValue('edit_is_owner', data.is_owner || 0);
            setCheckboxValue('edit_is_active', data.is_active || 0);

            var editTitle = document.getElementById('editDentistTitle');
            var editSubtitle = document.getElementById('editDentistSubtitle');

            if (editTitle) {
                editTitle.textContent = 'Edit ' + (data.name || 'Dentist');
            }

            if (editSubtitle) {
                editSubtitle.textContent = 'Dentist ID #' + dentistId + ' · User ID #' + userId;
            }

            openModal(editModal);
        });
    });

    if (editDentistForm) {
        editDentistForm.addEventListener('submit', function (event) {
            var dentistId = document.getElementById('editDentistId');
            var userId = document.getElementById('editUserId');
            var firstName = document.getElementById('edit_first_name');
            var lastName = document.getElementById('edit_last_name');
            var email = document.getElementById('edit_email');

            if (!dentistId || parseInt(dentistId.value || '0', 10) <= 0) {
                event.preventDefault();
                alert('Dentist ID is missing. Please close the popup and click Edit again.');
                return;
            }

            if (!userId || parseInt(userId.value || '0', 10) <= 0) {
                event.preventDefault();
                alert('User ID is missing. Please close the popup and click Edit again.');
                return;
            }

            if (!firstName || firstName.value.trim() === '') {
                event.preventDefault();
                alert('First name is required.');
                firstName.focus();
                return;
            }

            if (!lastName || lastName.value.trim() === '') {
                event.preventDefault();
                alert('Last name is required.');
                lastName.focus();
                return;
            }

            if (!email || email.value.trim() === '') {
                event.preventDefault();
                alert('Email is required.');
                email.focus();
            }
        });
    }

    document.querySelectorAll('.dm-status-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            pendingStatusForm = form;

            var dentistId = form.getAttribute('data-dentist-id') || '';
            var dentistName = form.getAttribute('data-dentist-name') || 'Selected dentist';
            var nextStatus = form.getAttribute('data-next-status') || '0';
            var actionLabel = form.getAttribute('data-action-label') || 'Update';

            if (statusTitle) {
                statusTitle.textContent = actionLabel + ' Dentist';
            }

            if (statusText) {
                statusText.textContent = nextStatus === '1'
                    ? 'Are you sure you want to activate this dentist profile? This dentist may be assigned appointments again.'
                    : 'Are you sure you want to deactivate this dentist profile? This dentist should no longer be assigned new appointments.';
            }

            if (statusUser) {
                statusUser.textContent = dentistName + ' · Dentist ID #' + dentistId;
            }

            if (statusConfirm) {
                statusConfirm.textContent = actionLabel + ' Dentist';
                statusConfirm.classList.toggle('danger', nextStatus !== '1');
                statusConfirm.classList.toggle('filled', nextStatus === '1');
            }

            openModal(statusModal);
        });
    });

    if (statusConfirm) {
        statusConfirm.addEventListener('click', function () {
            if (pendingStatusForm) {
                var form = pendingStatusForm;
                pendingStatusForm = null;
                form.submit();
            }
        });
    }

    document.querySelectorAll('.js-close-dentist-modal').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    document.querySelectorAll('.dm-modal-layer').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    if (specializationFilter) {
        specializationFilter.addEventListener('change', applyFilters);
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilters);
    }

    if (applyFilter) {
        applyFilter.addEventListener('click', applyFilters);
    }

    if (resetFilter) {
        resetFilter.addEventListener('click', function () {
            if (searchInput) {
                searchInput.value = '';
            }

            if (specializationFilter) {
                specializationFilter.value = '';
            }

            if (statusFilter) {
                statusFilter.value = '';
            }

            applyFilters();
        });
    }

    if (prevPageBtn) {
        prevPageBtn.addEventListener('click', function () {
            if (currentPage > 1) {
                currentPage--;
                renderPage();
            }
        });
    }

    if (nextPageBtn) {
        nextPageBtn.addEventListener('click', function () {
            var totalPages = Math.max(1, Math.ceil(filteredRows.length / perPage));

            if (currentPage < totalPages) {
                currentPage++;
                renderPage();
            }
        });
    }

    renderPage();
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
?>