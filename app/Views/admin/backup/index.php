<?php

use App\Core\Csrf;

$pageTitle = 'Backup and Restore';

$backups = isset($backups) && is_array($backups) ? $backups : [];
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

/*
|--------------------------------------------------------------------------
| Optional controller variables
|--------------------------------------------------------------------------
| If your controller already has these, this view will use them.
| If not, safe defaults are used.
*/
$restoreEnabled = isset($restoreEnabled) ? (bool) $restoreEnabled : true;
$retentionDays = isset($retentionDays) ? (int) $retentionDays : null;

$baseUrl = '/DentalClinic/public';

if (!function_exists('admin_view_e')) {
    function admin_view_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('backup_id')) {
    function backup_id(array $backup): int
    {
        return (int) ($backup['backup_id'] ?? $backup['id'] ?? 0);
    }
}

if (!function_exists('backup_file_name')) {
    function backup_file_name(array $backup): string
    {
        return (string) (
            $backup['backup_file'] ??
            $backup['file_name'] ??
            $backup['filename'] ??
            $backup['name'] ??
            'backup.sql'
        );
    }
}

if (!function_exists('backup_path')) {
    function backup_path(array $backup): string
    {
        return (string) (
            $backup['backup_path'] ??
            $backup['file_path'] ??
            $backup['path'] ??
            ''
        );
    }
}

if (!function_exists('backup_created_by')) {
    function backup_created_by(array $backup): string
    {
        $name = trim((string) (
            ($backup['first_name'] ?? '') . ' ' .
            ($backup['last_name'] ?? '')
        ));

        if ($name !== '') {
            return $name;
        }

        $username = trim((string) ($backup['username'] ?? ''));

        if ($username !== '') {
            return $username;
        }

        $createdByName = trim((string) ($backup['created_by_name'] ?? ''));

        if ($createdByName !== '') {
            return $createdByName;
        }

        $createdBy = (int) ($backup['created_by'] ?? 0);

        return $createdBy > 0 ? 'User #' . $createdBy : 'System';
    }
}

if (!function_exists('backup_size_bytes')) {
    function backup_size_bytes(array $backup): int
    {
        return (int) (
            $backup['file_size'] ??
            $backup['size'] ??
            $backup['backup_size'] ??
            0
        );
    }
}

if (!function_exists('format_backup_size')) {
    function format_backup_size(int $bytes): string
    {
        if ($bytes <= 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = (float) $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return number_format($size, $unitIndex === 0 ? 0 : 2) . ' ' . $units[$unitIndex];
    }
}

if (!function_exists('backup_date')) {
    function backup_date(?string $date): string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return '—';
        }

        $time = strtotime($date);

        return $time ? date('d F Y', $time) : $date;
    }
}

if (!function_exists('backup_time')) {
    function backup_time(?string $date): string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return '';
        }

        $time = strtotime($date);

        return $time ? date('h:i A', $time) : '';
    }
}

if (!function_exists('backup_extension')) {
    function backup_extension(string $fileName): string
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return $ext !== '' ? $ext : 'sql';
    }
}

if (!function_exists('backup_status')) {
    function backup_status(array $backup): string
    {
        if (!empty($backup['deleted_at'])) {
            return 'deleted';
        }

        $path = backup_path($backup);

        if ($path !== '' && !is_file($path)) {
            return 'missing';
        }

        return 'available';
    }
}

if (!function_exists('backup_status_label')) {
    function backup_status_label(string $status): string
    {
        return match ($status) {
            'deleted' => 'Deleted',
            'missing' => 'Missing',
            default => 'Available',
        };
    }
}

if (!function_exists('backup_icon')) {
    function backup_icon(string $name): string
    {
        $icons = [
            'search' => '<circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.4-3.4"></path>',
            'refresh' => '<path d="M20 12a8 8 0 1 1-2.3-5.6"></path><path d="M20 4v6h-6"></path>',
            'filter' => '<path d="M3 5h18"></path><path d="M6 12h12"></path><path d="M10 19h4"></path>',
            'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"></path><path d="M19.4 15a1.8 1.8 0 0 0 .36 1.98l.05.05a2.1 2.1 0 0 1-2.97 2.97l-.05-.05A1.8 1.8 0 0 0 14.8 19.6a1.8 1.8 0 0 0-1 .57V20.3a2.1 2.1 0 0 1-4.2 0v-.08a1.8 1.8 0 0 0-1-.57 1.8 1.8 0 0 0-1.98.36l-.05.05a2.1 2.1 0 0 1-2.97-2.97l.05-.05A1.8 1.8 0 0 0 4 15.2a1.8 1.8 0 0 0-.57-1H3.3a2.1 2.1 0 0 1 0-4.2h.08a1.8 1.8 0 0 0 .57-1 1.8 1.8 0 0 0-.36-1.98l-.05-.05A2.1 2.1 0 0 1 6.5 4l.05.05A1.8 1.8 0 0 0 8.4 4.4a1.8 1.8 0 0 0 1-.57V3.7a2.1 2.1 0 0 1 4.2 0v.08a1.8 1.8 0 0 0 1 .57 1.8 1.8 0 0 0 1.98-.36l.05-.05a2.1 2.1 0 0 1 2.97 2.97l-.05.05A1.8 1.8 0 0 0 20 8.8a1.8 1.8 0 0 0 .57 1h.13a2.1 2.1 0 0 1 0 4.2h-.08a1.8 1.8 0 0 0-1 .57z"></path>',
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect>',
            'list' => '<path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path>',
            'date' => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path>',
            'file' => '<path d="M7 3h7l5 5v13H7z"></path><path d="M14 3v5h5"></path><path d="M10 13h6M10 17h4"></path>',
            'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"></ellipse><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"></path><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"></path>',
            'download' => '<path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path>',
            'upload' => '<path d="M12 21V9"></path><path d="m7 14 5-5 5 5"></path><path d="M5 3h14"></path>',
            'trash' => '<path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 15H6L5 6"></path>',
            'restore' => '<path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 4v6h6"></path>',
            'check' => '<path d="M20 6 9 17l-5-5"></path>',
            'x' => '<path d="M18 6 6 18"></path><path d="M6 6l12 12"></path>',
            'warning' => '<path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.3 3.6 2.4 17.2A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.8L13.7 3.6a2 2 0 0 0-3.4 0z"></path>',
            'shield' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"></path><path d="M9 12l2 2 4-4"></path>',
        ];

        $path = $icons[$name] ?? $icons['file'];

        return '
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2.1" stroke-linecap="round"
                stroke-linejoin="round" aria-hidden="true">
                ' . $path . '
            </svg>
        ';
    }
}

$totalBackups = count($backups);
$availableBackups = 0;
$missingBackups = 0;
$deletedBackups = 0;
$totalBytes = 0;
$typeOptions = [];

foreach ($backups as $backup) {
    $status = backup_status($backup);

    if ($status === 'available') {
        $availableBackups++;
    } elseif ($status === 'missing') {
        $missingBackups++;
    } elseif ($status === 'deleted') {
        $deletedBackups++;
    }

    $fileName = backup_file_name($backup);
    $extension = backup_extension($fileName);
    $typeOptions[$extension] = strtoupper($extension);

    if ($status !== 'deleted') {
        $totalBytes += backup_size_bytes($backup);
    }
}

ksort($typeOptions);

ob_start();
?>

<style>
.br-page,
.br-page * {
    box-sizing: border-box;
}

.br-page {
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

.br-shell {
    max-width: 1460px;
    margin: 0 auto;
}

.br-board {
    background: var(--panel);
    border: 1px solid rgba(16, 24, 40, .08);
    border-radius: 12px;
    box-shadow: 0 10px 28px rgba(16, 24, 40, .10);
    overflow: hidden;
}

.br-toolbar {
    min-height: 58px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--line);
    background: #f7f9fc;
    display: grid;
    grid-template-columns: minmax(170px, 1fr) auto auto auto 1fr auto auto auto;
    gap: 9px;
    align-items: center;
}

.br-search {
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

.br-search input {
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

.br-select {
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

.br-btn {
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

.br-btn.primary {
    background: #ffffff;
    color: var(--brand);
    border-color: var(--brand);
}

.br-btn.primary:hover {
    background: var(--brand-soft);
}

.br-btn.filled {
    background: var(--brand);
    color: #ffffff;
    border-color: var(--brand);
    box-shadow: 0 6px 14px rgba(15, 118, 110, .18);
}

.br-btn.filled:hover {
    background: var(--brand-2);
    border-color: var(--brand-2);
}

.br-btn.ghost {
    background: #ffffff;
    color: var(--text-2);
    border-color: var(--line);
}

.br-btn.ghost:hover {
    background: var(--panel-soft);
}

.br-btn.danger {
    background: #ffffff;
    color: var(--red);
    border-color: #fca5a5;
}

.br-btn.danger:hover {
    background: var(--red-soft);
}

.br-toolbar-spacer {
    min-width: 6px;
}

.br-settings {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: var(--text-2);
    font-size: 12px;
    font-weight: 850;
    white-space: nowrap;
}

.br-view-toggle {
    height: 34px;
    display: inline-flex;
    border: 1px solid var(--line);
    border-radius: 7px;
    overflow: hidden;
    background: #ffffff;
}

.br-view-toggle span {
    width: 34px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--muted);
}

.br-view-toggle span + span {
    border-left: 1px solid var(--line);
}

.br-view-toggle .active {
    background: var(--panel-soft);
    color: var(--brand);
}

/* Flash */
.br-flashes {
    display: grid;
    gap: 8px;
    padding: 10px 14px 0;
    background: #f7f9fc;
}

.br-flash {
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

.br-flash.success {
    background: var(--green-soft);
    color: var(--green);
    border-color: #bbf7d0;
}

.br-flash.error {
    background: var(--red-soft);
    color: var(--red);
    border-color: #fecaca;
}

/* Summary */
.br-summary {
    min-height: 44px;
    padding: 8px 14px;
    border-bottom: 1px solid var(--line);
    background: #ffffff;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.br-summary-card {
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

.br-summary-card strong {
    color: var(--text);
}

/* Table */
.br-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.br-table {
    width: 100%;
    min-width: 1160px;
    border-collapse: separate;
    border-spacing: 0;
}

.br-table th {
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

.br-table th:last-child,
.br-table td:last-child {
    border-right: 0;
}

.br-th-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.br-sort {
    margin-left: 6px;
    color: #b0bac7;
    font-size: 10px;
}

.br-table td {
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

.br-table tbody tr:hover td {
    background: #fbfcfd;
}

.br-date {
    color: var(--text);
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

.br-time {
    display: block;
    margin-top: 2px;
    color: var(--faint);
    font-size: 11px;
    font-weight: 700;
}

.br-file {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 240px;
}

.br-file-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: var(--brand-soft);
    color: var(--brand);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.br-file-name {
    color: var(--text);
    font-size: 12px;
    font-weight: 850;
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.br-file-meta {
    margin-top: 2px;
    color: var(--faint);
    font-size: 11px;
    font-weight: 750;
}

.br-mono {
    color: var(--text-2);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: 11px;
    font-weight: 750;
    white-space: nowrap;
}

.br-pill {
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

.br-pill::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: currentColor;
}

.br-pill.available {
    background: var(--green-soft);
    color: var(--green);
}

.br-pill.missing {
    background: var(--amber-soft);
    color: var(--amber);
}

.br-pill.deleted {
    background: var(--red-soft);
    color: var(--red);
}

.br-type {
    display: inline-flex;
    width: fit-content;
    min-height: 23px;
    align-items: center;
    padding: 0 8px;
    border-radius: 6px;
    background: #f2f4f7;
    color: #475467;
    font-size: 11px;
    font-weight: 850;
    white-space: nowrap;
}

.br-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.br-row-btn {
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

.br-row-btn:hover {
    background: var(--brand-soft);
}

.br-row-btn.danger {
    border-color: #ef4444;
    color: var(--red);
}

.br-row-btn.danger:hover {
    background: var(--red-soft);
}

.br-row-btn.warning {
    border-color: #f59e0b;
    color: var(--amber);
}

.br-row-btn.warning:hover {
    background: var(--amber-soft);
}

.br-row-btn:disabled,
.br-row-btn.disabled {
    opacity: .45;
    pointer-events: none;
}

.br-empty {
    padding: 44px 18px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
    font-weight: 750;
}

.br-hidden {
    display: none !important;
}

/* Footer */
.br-footer {
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

.br-page-info {
    color: var(--muted);
    font-size: 12px;
    font-weight: 800;
}

.br-pagination {
    display: flex;
    align-items: center;
    gap: 6px;
}

.br-page-btn {
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

.br-page-btn:hover:not(:disabled) {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
}

.br-page-btn.active {
    border-color: var(--brand);
    color: var(--brand);
    box-shadow: 0 0 0 3px rgba(15, 118, 110, .10);
}

.br-page-btn:disabled {
    opacity: .45;
    cursor: default;
}

/* Modal */
.br-modal-layer {
    position: fixed;
    inset: 0;
    z-index: 9000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, .48);
    backdrop-filter: blur(10px);
}

.br-modal-layer.open {
    display: flex;
}

.br-modal {
    width: min(500px, 100%);
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 28px 90px rgba(15, 23, 42, .30);
    overflow: hidden;
}

.br-modal-head {
    padding: 16px 18px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    gap: 12px;
}

.br-modal-icon {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: var(--amber-soft);
    color: var(--amber);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.br-modal-title {
    color: var(--text);
    font-size: 16px;
    font-weight: 900;
}

.br-modal-subtitle {
    margin-top: 3px;
    color: var(--muted);
    font-size: 12.5px;
    font-weight: 650;
}

.br-modal-body {
    padding: 18px;
}

.br-modal-copy {
    margin: 0;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.55;
    font-weight: 650;
}

.br-modal-file {
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

.br-modal-foot {
    padding: 14px 18px;
    border-top: 1px solid var(--line);
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

body.br-modal-open .br-board {
    filter: blur(4px);
    opacity: .75;
    pointer-events: none;
}

@media (max-width: 1240px) {
    .br-toolbar {
        grid-template-columns: 1fr 1fr 1fr;
    }

    .br-toolbar-spacer,
    .br-settings,
    .br-view-toggle {
        display: none;
    }
}

@media (max-width: 760px) {
    .br-page {
        padding: 10px;
    }

    .br-toolbar {
        grid-template-columns: 1fr;
    }

    .br-search,
    .br-select,
    .br-btn {
        width: 100%;
    }

    .br-footer {
        align-items: stretch;
        flex-direction: column;
    }

    .br-pagination {
        flex-wrap: wrap;
    }

    .br-modal-foot {
        flex-direction: column-reverse;
    }

    .br-modal-foot .br-btn {
        width: 100%;
    }
}
</style>

<div class="br-page">
    <div class="br-shell">
        <section class="br-board">

            <div class="br-toolbar">
                <label class="br-search">
                    <?= backup_icon('search') ?>
                    <input
                        type="search"
                        id="backupSearchInput"
                        placeholder="Search backups"
                        autocomplete="off"
                    >
                </label>

                <select class="br-select" id="backupTypeFilter">
                    <option value="">All Backup Types</option>
                    <?php foreach ($typeOptions as $typeKey => $typeLabel): ?>
                        <option value="<?= admin_view_e($typeKey) ?>">
                            <?= admin_view_e($typeLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select class="br-select" id="backupStatusFilter">
                    <option value="">All Status</option>
                    <option value="available">Available</option>
                    <option value="missing">Missing File</option>
                    <option value="deleted">Deleted</option>
                </select>

                <button type="button" class="br-btn primary" id="backupAdvancedFilter">
                    <?= backup_icon('filter') ?>
                    Apply Filter
                </button>

                <span class="br-toolbar-spacer"></span>

                <span class="br-settings">
                    <?= backup_icon('settings') ?>
                    Recovery Setting
                </span>

                <span class="br-view-toggle" aria-hidden="true">
                    <span class="active"><?= backup_icon('list') ?></span>
                    <span><?= backup_icon('grid') ?></span>
                </span>

                <form
                    method="POST"
                    action="<?= admin_view_e($baseUrl . '/admin/backup/create') ?>"
                    class="br-create-form"
                    onsubmit="return confirm('Create a new database backup now?');"
                >
                    <?= Csrf::inputField(); ?>
                    <button type="submit" class="br-btn filled">
                        <?= backup_icon('database') ?>
                        Create Backup
                    </button>
                </form>

                <button type="button" class="br-btn ghost" id="backupResetFilter">
                    <?= backup_icon('refresh') ?>
                    Reset
                </button>
            </div>

            <?php if ($flash_success || $flash_error): ?>
                <div class="br-flashes">
                    <?php if ($flash_success): ?>
                        <div class="br-flash success">
                            <?= backup_icon('check') ?>
                            <span><?= admin_view_e($flash_success) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($flash_error): ?>
                        <div class="br-flash error">
                            <?= backup_icon('warning') ?>
                            <span><?= admin_view_e($flash_error) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="br-summary">
                <span class="br-summary-card">Total <strong><?= (int) $totalBackups ?></strong></span>
                <span class="br-summary-card">Available <strong><?= (int) $availableBackups ?></strong></span>
                <span class="br-summary-card">Missing <strong><?= (int) $missingBackups ?></strong></span>
                <span class="br-summary-card">Deleted <strong><?= (int) $deletedBackups ?></strong></span>
                <span class="br-summary-card">Storage <strong><?= admin_view_e(format_backup_size($totalBytes)) ?></strong></span>

                <?php if ($retentionDays !== null): ?>
                    <span class="br-summary-card">Retention <strong><?= (int) $retentionDays ?> days</strong></span>
                <?php endif; ?>

                <span class="br-summary-card">Restore <strong><?= $restoreEnabled ? 'Enabled' : 'Disabled' ?></strong></span>
            </div>

            <div class="br-table-wrap">
                <table class="br-table">
                    <thead>
                        <tr>
                            <th>
                                <span class="br-th-label">
                                    <?= backup_icon('date') ?>
                                    Backup Date
                                    <span class="br-sort">↕</span>
                                </span>
                            </th>
                            <th>
                                <span class="br-th-label">
                                    <?= backup_icon('file') ?>
                                    Backup File
                                    <span class="br-sort">↕</span>
                                </span>
                            </th>
                            <th>
                                <span class="br-th-label">
                                    Type
                                    <span class="br-sort">↕</span>
                                </span>
                            </th>
                            <th>
                                <span class="br-th-label">
                                    File Size
                                    <span class="br-sort">↕</span>
                                </span>
                            </th>
                            <th>
                                <span class="br-th-label">
                                    Created By
                                    <span class="br-sort">↕</span>
                                </span>
                            </th>
                            <th>
                                <span class="br-th-label">
                                    Storage
                                    <span class="br-sort">↕</span>
                                </span>
                            </th>
                            <th>
                                <span class="br-th-label">
                                    Status
                                    <span class="br-sort">↕</span>
                                </span>
                            </th>
                            <th style="width:260px;">
                                <span class="br-th-label">Action</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($backups)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="br-empty">No backup records found.</div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($backups as $backup): ?>
                            <?php
                                $backupId = backup_id($backup);
                                $fileName = backup_file_name($backup);
                                $extension = backup_extension($fileName);
                                $createdAt = (string) ($backup['created_at'] ?? '');
                                $createdBy = backup_created_by($backup);
                                $sizeBytes = backup_size_bytes($backup);
                                $sizeLabel = format_backup_size($sizeBytes);
                                $status = backup_status($backup);
                                $statusLabel = backup_status_label($status);
                                $isAvailable = $status === 'available';

                                $rowSearch = strtolower(trim(implode(' ', [
                                    $fileName,
                                    $extension,
                                    $createdAt,
                                    $createdBy,
                                    $sizeLabel,
                                    $status,
                                    $statusLabel,
                                ])));
                            ?>

                            <tr
                                class="br-backup-row"
                                data-search="<?= admin_view_e($rowSearch) ?>"
                                data-type="<?= admin_view_e($extension) ?>"
                                data-status="<?= admin_view_e($status) ?>"
                            >
                                <td>
                                    <span class="br-date">
                                        <?= admin_view_e(backup_date($createdAt)) ?>
                                        <span class="br-time"><?= admin_view_e(backup_time($createdAt)) ?></span>
                                    </span>
                                </td>

                                <td>
                                    <div class="br-file">
                                        <span class="br-file-icon"><?= backup_icon('database') ?></span>
                                        <div>
                                            <div class="br-file-name" title="<?= admin_view_e($fileName) ?>">
                                                <?= admin_view_e($fileName) ?>
                                            </div>
                                            <div class="br-file-meta">Backup ID #<?= (int) $backupId ?></div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="br-type"><?= admin_view_e(strtoupper($extension)) ?></span>
                                </td>

                                <td>
                                    <span class="br-mono"><?= admin_view_e($sizeLabel) ?></span>
                                </td>

                                <td>
                                    <span class="br-mono"><?= admin_view_e($createdBy) ?></span>
                                </td>

                                <td>
                                    <span class="br-mono">Protected backup directory</span>
                                </td>

                                <td>
                                    <span class="br-pill <?= admin_view_e($status) ?>">
                                        <?= admin_view_e($statusLabel) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="br-actions">
                                        <a
                                            class="br-row-btn <?= $isAvailable ? '' : 'disabled' ?>"
                                            href="<?= admin_view_e($baseUrl . '/admin/backup/download?id=' . $backupId . '&backup_id=' . $backupId) ?>"
                                        >
                                            <?= backup_icon('download') ?>
                                            Download
                                        </a>

                                        <button
                                            type="button"
                                            class="br-row-btn warning js-open-restore <?= ($isAvailable && $restoreEnabled) ? '' : 'disabled' ?>"
                                            data-backup-id="<?= (int) $backupId ?>"
                                            data-backup-file="<?= admin_view_e($fileName) ?>"
                                        >
                                            <?= backup_icon('restore') ?>
                                            Restore
                                        </button>

                                        <button
                                            type="button"
                                            class="br-row-btn danger js-open-delete <?= $status === 'deleted' ? 'disabled' : '' ?>"
                                            data-backup-id="<?= (int) $backupId ?>"
                                            data-backup-file="<?= admin_view_e($fileName) ?>"
                                        >
                                            <?= backup_icon('trash') ?>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr id="backupNoMatchRow" class="br-hidden">
                            <td colspan="8">
                                <div class="br-empty">No backup records match the selected filters.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="br-footer">
                <div class="br-page-info" id="backupPageInfo">
                    Showing 0–0 of <?= (int) $totalBackups ?> backups
                </div>

                <div class="br-pagination" id="backupPagination">
                    <button type="button" class="br-page-btn" id="backupPrevPage">‹</button>
                    <button type="button" class="br-page-btn active" id="backupCurrentPage">1</button>
                    <button type="button" class="br-page-btn" id="backupNextPage">›</button>
                </div>
            </div>
        </section>

        <div class="br-modal-layer" id="backupConfirmModal" aria-hidden="true">
            <div class="br-modal" role="dialog" aria-modal="true" aria-labelledby="backupConfirmTitle">
                <div class="br-modal-head">
                    <div class="br-modal-icon" id="backupConfirmIcon">
                        <?= backup_icon('warning') ?>
                    </div>

                    <div>
                        <div class="br-modal-title" id="backupConfirmTitle">Confirm Action</div>
                        <div class="br-modal-subtitle" id="backupConfirmSubtitle">Please review before continuing.</div>
                    </div>
                </div>

                <div class="br-modal-body">
                    <p class="br-modal-copy" id="backupConfirmCopy">
                        Are you sure you want to continue?
                    </p>

                    <div class="br-modal-file" id="backupConfirmFile">
                        Selected backup
                    </div>
                </div>

                <div class="br-modal-foot">
                    <button type="button" class="br-btn ghost js-close-backup-modal">Cancel</button>
                    <button type="button" class="br-btn danger" id="backupConfirmSubmit">Confirm</button>
                </div>
            </div>
        </div>

        <form method="POST" action="<?= admin_view_e($baseUrl . '/admin/backup/delete') ?>" id="deleteBackupForm" class="br-hidden">
            <?= Csrf::inputField(); ?>
            <input type="hidden" name="backup_id" id="delete_backup_id">
            <input type="hidden" name="id" id="delete_id">
        </form>

        <form method="POST" action="<?= admin_view_e($baseUrl . '/admin/backup/restore') ?>" id="restoreBackupForm" class="br-hidden">
            <?= Csrf::inputField(); ?>
            <input type="hidden" name="backup_id" id="restore_backup_id">
            <input type="hidden" name="id" id="restore_id">
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var rows = Array.prototype.slice.call(document.querySelectorAll('.br-backup-row'));
    var searchInput = document.getElementById('backupSearchInput');
    var typeFilter = document.getElementById('backupTypeFilter');
    var statusFilter = document.getElementById('backupStatusFilter');
    var applyFilter = document.getElementById('backupAdvancedFilter');
    var resetFilter = document.getElementById('backupResetFilter');
    var noMatchRow = document.getElementById('backupNoMatchRow');

    var pageInfo = document.getElementById('backupPageInfo');
    var prevPageBtn = document.getElementById('backupPrevPage');
    var nextPageBtn = document.getElementById('backupNextPage');
    var currentPageBtn = document.getElementById('backupCurrentPage');

    var currentPage = 1;
    var perPage = 10;
    var filteredRows = rows.slice();

    function normalize(value) {
        return String(value || '').toLowerCase().trim();
    }

    function applyFilters() {
        var query = normalize(searchInput ? searchInput.value : '');
        var type = normalize(typeFilter ? typeFilter.value : '');
        var status = normalize(statusFilter ? statusFilter.value : '');

        filteredRows = rows.filter(function (row) {
            var rowSearch = normalize(row.getAttribute('data-search'));
            var rowType = normalize(row.getAttribute('data-type'));
            var rowStatus = normalize(row.getAttribute('data-status'));

            var matched = true;

            if (query !== '') {
                matched = matched && rowSearch.indexOf(query) !== -1;
            }

            if (type !== '') {
                matched = matched && rowType === type;
            }

            if (status !== '') {
                matched = matched && rowStatus === status;
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
            row.classList.add('br-hidden');
        });

        filteredRows.slice(start, end).forEach(function (row) {
            row.classList.remove('br-hidden');
        });

        if (noMatchRow) {
            noMatchRow.classList.toggle('br-hidden', total !== 0 || rows.length === 0);
        }

        if (pageInfo) {
            var from = total > 0 ? start + 1 : 0;
            var to = Math.min(end, total);
            pageInfo.textContent = 'Showing ' + from + '–' + to + ' of ' + total + ' backups';
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

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    if (typeFilter) {
        typeFilter.addEventListener('change', applyFilters);
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilters);
    }

    if (applyFilter) {
        applyFilter.addEventListener('click', applyFilters);
    }

    if (resetFilter) {
        resetFilter.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (typeFilter) typeFilter.value = '';
            if (statusFilter) statusFilter.value = '';

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

    var modal = document.getElementById('backupConfirmModal');
    var modalTitle = document.getElementById('backupConfirmTitle');
    var modalSubtitle = document.getElementById('backupConfirmSubtitle');
    var modalCopy = document.getElementById('backupConfirmCopy');
    var modalFile = document.getElementById('backupConfirmFile');
    var confirmSubmit = document.getElementById('backupConfirmSubmit');

    var deleteForm = document.getElementById('deleteBackupForm');
    var restoreForm = document.getElementById('restoreBackupForm');

    var pendingForm = null;
    var pendingType = '';

    function openModal() {
        if (!modal) return;

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('br-modal-open');
    }

    function closeModal() {
        if (!modal) return;

        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('br-modal-open');

        pendingForm = null;
        pendingType = '';
    }

    function setHiddenValue(id, value) {
        var input = document.getElementById(id);

        if (input) {
            input.value = value;
        }
    }

    document.querySelectorAll('.js-open-delete').forEach(function (button) {
        button.addEventListener('click', function () {
            var backupId = button.getAttribute('data-backup-id') || '';
            var backupFile = button.getAttribute('data-backup-file') || 'Selected backup';

            pendingForm = deleteForm;
            pendingType = 'delete';

            setHiddenValue('delete_backup_id', backupId);
            setHiddenValue('delete_id', backupId);

            if (modalTitle) modalTitle.textContent = 'Delete Backup';
            if (modalSubtitle) modalSubtitle.textContent = 'This action removes the backup record and file if allowed.';
            if (modalCopy) modalCopy.textContent = 'Are you sure you want to delete this backup? Keep at least one recent backup before deleting old files.';
            if (modalFile) modalFile.textContent = backupFile + ' · Backup ID #' + backupId;

            if (confirmSubmit) {
                confirmSubmit.textContent = 'Delete Backup';
                confirmSubmit.classList.remove('filled');
                confirmSubmit.classList.add('danger');
            }

            openModal();
        });
    });

    document.querySelectorAll('.js-open-restore').forEach(function (button) {
        button.addEventListener('click', function () {
            var backupId = button.getAttribute('data-backup-id') || '';
            var backupFile = button.getAttribute('data-backup-file') || 'Selected backup';

            pendingForm = restoreForm;
            pendingType = 'restore';

            setHiddenValue('restore_backup_id', backupId);
            setHiddenValue('restore_id', backupId);

            if (modalTitle) modalTitle.textContent = 'Restore Backup';
            if (modalSubtitle) modalSubtitle.textContent = 'This action may overwrite current database records.';
            if (modalCopy) modalCopy.textContent = 'Restore only if you are sure this backup is correct. Create a fresh backup first before restoring.';
            if (modalFile) modalFile.textContent = backupFile + ' · Backup ID #' + backupId;

            if (confirmSubmit) {
                confirmSubmit.textContent = 'Restore Backup';
                confirmSubmit.classList.remove('danger');
                confirmSubmit.classList.add('filled');
            }

            openModal();
        });
    });

    if (confirmSubmit) {
        confirmSubmit.addEventListener('click', function () {
            if (pendingForm) {
                var form = pendingForm;
                pendingForm = null;
                form.submit();
            }
        });
    }

    document.querySelectorAll('.js-close-backup-modal').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    renderPage();
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
?>