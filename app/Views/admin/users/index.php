<?php

use App\Core\Csrf;

$pageTitle = 'Users and Roles';

$users = isset($users) && is_array($users) ? $users : [];
$roles = isset($roles) && is_array($roles) ? $roles : [];
$search = $search ?? '';
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

$baseUrl = '/DentalClinic/public';

if (!function_exists('admin_view_e')) {
    function admin_view_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_user_name')) {
    function admin_user_name(array $user): string
    {
        $name = trim((string) (
            ($user['first_name'] ?? '') . ' ' .
            ($user['middle_name'] ?? '') . ' ' .
            ($user['last_name'] ?? '')
        ));

        return $name !== '' ? $name : 'Unnamed User';
    }
}

if (!function_exists('admin_user_initials')) {
    function admin_user_initials(array $user): string
    {
        $first = trim((string) ($user['first_name'] ?? ''));
        $last = trim((string) ($user['last_name'] ?? ''));

        $initials = '';

        if ($first !== '') {
            $initials .= mb_strtoupper(mb_substr($first, 0, 1));
        }

        if ($last !== '') {
            $initials .= mb_strtoupper(mb_substr($last, 0, 1));
        }

        return $initials !== '' ? $initials : 'U';
    }
}

if (!function_exists('admin_status_text')) {
    function admin_status_text($value): string
    {
        return (int) ($value ?? 1) === 1 ? 'Active' : 'Inactive';
    }
}

if (!function_exists('admin_role_name')) {
    function admin_role_name(array $role): string
    {
        return (string) ($role['role_name'] ?? $role['name'] ?? '');
    }
}

if (!function_exists('admin_role_id')) {
    function admin_role_id(array $role): int
    {
        return (int) ($role['role_id'] ?? $role['id'] ?? 0);
    }
}

if (!function_exists('admin_user_assigned_role_names')) {
    function admin_user_assigned_role_names(array $user): array
    {
        $names = [];

        if (!empty($user['roles_text'])) {
            foreach (explode(',', (string) $user['roles_text']) as $roleName) {
                $roleName = strtolower(trim($roleName));

                if ($roleName !== '') {
                    $names[$roleName] = $roleName;
                }
            }
        }

        if (!empty($user['role_name'])) {
            $roleName = strtolower(trim((string) $user['role_name']));
            $names[$roleName] = $roleName;
        }

        if (!empty($user['primary_role_name'])) {
            $roleName = strtolower(trim((string) $user['primary_role_name']));
            $names[$roleName] = $roleName;
        }

        if (!empty($user['primary_role'])) {
            $roleName = strtolower(trim((string) $user['primary_role']));
            $names[$roleName] = $roleName;
        }

        if (!empty($user['roles']) && is_array($user['roles'])) {
            foreach ($user['roles'] as $role) {
                if (is_array($role)) {
                    $roleName = strtolower(trim(admin_role_name($role)));
                } else {
                    $roleName = strtolower(trim((string) $role));
                }

                if ($roleName !== '') {
                    $names[$roleName] = $roleName;
                }
            }
        }

        return array_values($names);
    }
}

if (!function_exists('admin_user_assigned_role_ids')) {
    function admin_user_assigned_role_ids(array $user, array $roles): array
    {
        $ids = [];

        if (!empty($user['role_ids']) && is_array($user['role_ids'])) {
            foreach ($user['role_ids'] as $roleId) {
                $roleId = (int) $roleId;

                if ($roleId > 0) {
                    $ids[$roleId] = $roleId;
                }
            }
        }

        if (!empty($user['roles']) && is_array($user['roles'])) {
            foreach ($user['roles'] as $role) {
                if (is_array($role)) {
                    $roleId = admin_role_id($role);

                    if ($roleId > 0) {
                        $ids[$roleId] = $roleId;
                    }
                }
            }
        }

        $assignedNames = admin_user_assigned_role_names($user);

        foreach ($roles as $role) {
            $roleId = admin_role_id($role);
            $roleName = strtolower(trim(admin_role_name($role)));

            if ($roleId > 0 && in_array($roleName, $assignedNames, true)) {
                $ids[$roleId] = $roleId;
            }
        }

        return array_values($ids);
    }
}

if (!function_exists('admin_icon')) {
    function admin_icon(string $name): string
    {
        $icons = [
            'search'   => '<circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.4-3.4"></path>',
            'refresh'  => '<path d="M20 12a8 8 0 1 1-2.3-5.6"></path><path d="M20 4v6h-6"></path>',
            'filter'   => '<path d="M3 5h18"></path><path d="M6 12h12"></path><path d="M10 19h4"></path>',
            'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"></path><path d="M19.4 15a1.8 1.8 0 0 0 .36 1.98l.05.05a2.1 2.1 0 0 1-2.97 2.97l-.05-.05A1.8 1.8 0 0 0 14.8 19.6a1.8 1.8 0 0 0-1 .57V20.3a2.1 2.1 0 0 1-4.2 0v-.08a1.8 1.8 0 0 0-1-.57 1.8 1.8 0 0 0-1.98.36l-.05.05a2.1 2.1 0 0 1-2.97-2.97l.05-.05A1.8 1.8 0 0 0 4 15.2a1.8 1.8 0 0 0-.57-1H3.3a2.1 2.1 0 0 1 0-4.2h.08a1.8 1.8 0 0 0 .57-1 1.8 1.8 0 0 0-.36-1.98l-.05-.05A2.1 2.1 0 0 1 6.5 4l.05.05A1.8 1.8 0 0 0 8.4 4.4a1.8 1.8 0 0 0 1-.57V3.7a2.1 2.1 0 0 1 4.2 0v.08a1.8 1.8 0 0 0 1 .57 1.8 1.8 0 0 0 1.98-.36l.05-.05a2.1 2.1 0 0 1 2.97 2.97l-.05.05A1.8 1.8 0 0 0 20 8.8a1.8 1.8 0 0 0 .57 1h.13a2.1 2.1 0 0 1 0 4.2h-.08a1.8 1.8 0 0 0-1 .57z"></path>',
            'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect>',
            'list'     => '<path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path>',
            'user'     => '<path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle>',
            'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
            'shield'   => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"></path><path d="M9 12l2 2 4-4"></path>',
            'edit'     => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>',
            'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path>',
            'check'    => '<path d="M20 6 9 17l-5-5"></path>',
            'x'        => '<path d="M18 6 6 18"></path><path d="M6 6l12 12"></path>',
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

$totalUsers = count($users);

$activeUsers = count(array_filter($users, static function (array $user): bool {
    return (int) ($user['is_active'] ?? 1) === 1;
}));

$inactiveUsers = max(0, $totalUsers - $activeUsers);
$totalRoles = count($roles);

$roleOptions = [];

foreach ($roles as $role) {
    $roleName = trim(admin_role_name($role));

    if ($roleName !== '') {
        $roleOptions[strtolower($roleName)] = $roleName;
    }
}

ksort($roleOptions);

ob_start();
?>

<style>
.ur-page,
.ur-page * {
    box-sizing: border-box;
}

.ur-page {
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

.ur-shell {
    max-width: 1460px;
    margin: 0 auto;
}

.ur-board {
    background: var(--panel);
    border: 1px solid rgba(16, 24, 40, .08);
    border-radius: 1px;
    box-shadow: 0 10px 28px rgba(16, 24, 40, .10);
    overflow: hidden;
}

.ur-toolbar {
    min-height: 58px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--line);
    background: #f7f9fc;
    display: grid;
    grid-template-columns: minmax(170px, 1fr) auto auto auto 1fr auto auto auto;
    gap: 9px;
    align-items: center;
}

.ur-search {
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

.ur-search input {
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

.ur-select {
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

.ur-btn {
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

.ur-btn.primary {
    background: #ffffff;
    color: var(--brand);
    border-color: var(--brand);
}

.ur-btn.primary:hover {
    background: var(--brand-soft);
}

.ur-btn.filled {
    background: var(--brand);
    color: #ffffff;
    border-color: var(--brand);
    box-shadow: 0 6px 14px rgba(15, 118, 110, .18);
}

.ur-btn.filled:hover {
    background: var(--brand-2);
    border-color: var(--brand-2);
}

.ur-btn.ghost {
    background: #ffffff;
    color: var(--text-2);
    border-color: var(--line);
}

.ur-btn.ghost:hover {
    background: var(--panel-soft);
}

.ur-btn.danger {
    background: #ffffff;
    color: var(--red);
    border-color: #fca5a5;
}

.ur-btn.danger:hover {
    background: var(--red-soft);
}

.ur-toolbar-spacer {
    min-width: 6px;
}

.ur-settings {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: var(--text-2);
    font-size: 12px;
    font-weight: 850;
    white-space: nowrap;
}

.ur-view-toggle {
    height: 34px;
    display: inline-flex;
    border: 1px solid var(--line);
    border-radius: 7px;
    overflow: hidden;
    background: #ffffff;
}

.ur-view-toggle span {
    width: 34px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--muted);
}

.ur-view-toggle span + span {
    border-left: 1px solid var(--line);
}

.ur-view-toggle .active {
    background: var(--panel-soft);
    color: var(--brand);
}

.ur-flashes {
    display: grid;
    gap: 8px;
    padding: 10px 14px 0;
    background: #f7f9fc;
}

.ur-flash {
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

.ur-flash.success {
    background: var(--green-soft);
    color: var(--green);
    border-color: #bbf7d0;
}

.ur-flash.error {
    background: var(--red-soft);
    color: var(--red);
    border-color: #fecaca;
}

.ur-summary {
    min-height: 44px;
    padding: 8px 14px;
    border-bottom: 1px solid var(--line);
    background: #ffffff;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.ur-summary-card {
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

.ur-summary-card strong {
    color: var(--text);
}

.ur-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.ur-table {
    width: 100%;
    min-width: 1160px;
    border-collapse: separate;
    border-spacing: 0;
}

.ur-table th {
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

.ur-table th:last-child,
.ur-table td:last-child {
    border-right: 0;
}

.ur-th-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.ur-sort {
    margin-left: 6px;
    color: #b0bac7;
    font-size: 10px;
}

.ur-table td {
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

.ur-table tbody tr:hover td {
    background: #fbfcfd;
}

.ur-user {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 220px;
}

.ur-avatar {
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

.ur-user-name {
    color: var(--text);
    font-size: 12px;
    font-weight: 850;
    white-space: nowrap;
}

.ur-user-id {
    display: block;
    margin-top: 2px;
    color: var(--faint);
    font-size: 11px;
    font-weight: 700;
}

.ur-mono {
    color: var(--text-2);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: 11px;
    font-weight: 750;
    white-space: nowrap;
}

.ur-pill {
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

.ur-pill::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: currentColor;
}

.ur-pill.active {
    background: var(--green-soft);
    color: var(--green);
}

.ur-pill.inactive {
    background: var(--red-soft);
    color: var(--red);
}

.ur-pill.role {
    background: var(--blue-soft);
    color: var(--blue);
}

.ur-pill.role-secondary {
    background: #f2f4f7;
    color: #475467;
}

.ur-pill.no-role {
    background: var(--amber-soft);
    color: var(--amber);
}

.ur-role-stack {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
    max-width: 280px;
}

.ur-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.ur-row-btn {
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

.ur-row-btn:hover {
    background: var(--brand-soft);
}

.ur-row-btn.danger {
    border-color: #ef4444;
    color: var(--red);
}

.ur-row-btn.danger:hover {
    background: var(--red-soft);
}

.ur-row-btn.success {
    border-color: #10b981;
    color: var(--green);
}

.ur-row-btn.success:hover {
    background: var(--green-soft);
}

.ur-empty {
    padding: 44px 18px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
    font-weight: 750;
}

.ur-hidden {
    display: none !important;
}

.ur-footer {
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

.ur-page-info {
    color: var(--muted);
    font-size: 12px;
    font-weight: 800;
}

.ur-pagination {
    display: flex;
    align-items: center;
    gap: 6px;
}

.ur-page-btn {
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

.ur-page-btn:hover:not(:disabled) {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
}

.ur-page-btn.active {
    border-color: var(--brand);
    color: var(--brand);
    box-shadow: 0 0 0 3px rgba(15, 118, 110, .10);
}

.ur-page-btn:disabled {
    opacity: .45;
    cursor: default;
}

/* Modal */
.ur-modal-layer {
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

.ur-modal-layer.open {
    display: flex;
}

.ur-modal {
    width: min(720px, 100%);
    max-height: calc(100dvh - 24px);
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 28px 90px rgba(15, 23, 42, .30);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.ur-modal.sm {
    width: min(500px, 100%);
}

.ur-modal-head {
    flex: 0 0 auto;
    padding: 12px 14px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.ur-modal-title-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.ur-modal-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: var(--brand-soft);
    color: var(--brand);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ur-modal-icon.danger {
    background: var(--red-soft);
    color: var(--red);
}

.ur-modal-title {
    color: var(--text);
    font-size: 14.5px;
    font-weight: 900;
}

.ur-modal-subtitle {
    margin-top: 2px;
    color: var(--muted);
    font-size: 11.5px;
    font-weight: 650;
}

.ur-modal-close {
    width: 31px;
    height: 31px;
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

.ur-modal-close:hover {
    background: var(--panel-soft);
}

.ur-edit-scroll {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
}

.ur-edit-scroll::-webkit-scrollbar,
.ur-modal-body::-webkit-scrollbar {
    width: 8px;
}

.ur-edit-scroll::-webkit-scrollbar-track,
.ur-modal-body::-webkit-scrollbar-track {
    background: transparent;
}

.ur-edit-scroll::-webkit-scrollbar-thumb,
.ur-modal-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 999px;
}

.ur-modal-body {
    flex: 1 1 auto;
    min-height: 0;
    padding: 12px 14px;
    overflow-y: auto;
}

.ur-modal-foot {
    flex: 0 0 auto;
    padding: 10px 14px;
    border-top: 1px solid var(--line);
    background: #ffffff;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.ur-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.ur-field {
    display: grid;
    gap: 5px;
}

.ur-field.full {
    grid-column: 1 / -1;
}

.ur-label {
    color: var(--text);
    font-size: 11px;
    font-weight: 850;
}

.ur-input,
.ur-modal-select {
    width: 100%;
    min-height: 34px;
    border: 1px solid var(--line-2);
    border-radius: 7px;
    background: #ffffff;
    color: var(--text);
    padding: 0 9px;
    font: inherit;
    font-size: 12px;
    font-weight: 650;
    outline: none;
}

.ur-input:focus,
.ur-modal-select:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(15, 118, 110, .12);
}

.ur-help {
    color: var(--muted);
    font-size: 11.5px;
    line-height: 1.45;
    font-weight: 600;
}

.ur-role-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.ur-role-option {
    min-height: 36px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: #ffffff;
    padding: 8px 9px;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    color: var(--text);
    font-size: 12px;
    font-weight: 800;
}

.ur-role-option:hover {
    background: var(--panel-soft);
}

.ur-role-option input {
    width: 14px;
    height: 14px;
    accent-color: var(--brand);
}

.ur-confirm-copy {
    margin: 0;
    color: var(--text-2);
    font-size: 13px;
    line-height: 1.55;
    font-weight: 650;
}

.ur-confirm-user {
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

body.ur-modal-open {
    overflow: hidden;
}

body.ur-modal-open .ur-board {
    filter: blur(4px);
    opacity: .75;
    pointer-events: none;
}

@media (max-width: 1240px) {
    .ur-toolbar {
        grid-template-columns: 1fr 1fr 1fr;
    }

    .ur-toolbar-spacer,
    .ur-settings,
    .ur-view-toggle {
        display: none;
    }
}

@media (max-width: 760px) {
    .ur-page {
        padding: 10px;
    }

    .ur-toolbar {
        grid-template-columns: 1fr;
    }

    .ur-search,
    .ur-select,
    .ur-btn {
        width: 100%;
    }

    .ur-footer {
        align-items: stretch;
        flex-direction: column;
    }

    .ur-pagination {
        flex-wrap: wrap;
    }

    .ur-modal-layer {
        padding: 8px;
        align-items: center;
    }

    .ur-modal {
        width: 100%;
        max-height: calc(100dvh - 16px);
    }

    .ur-form-grid,
    .ur-role-grid {
        grid-template-columns: 1fr;
    }

    .ur-field.full {
        grid-column: auto;
    }

    .ur-modal-foot {
        flex-direction: column-reverse;
    }

    .ur-modal-foot .ur-btn {
        width: 100%;
    }
}

@media (max-height: 560px) {
    .ur-modal-layer {
        align-items: flex-start;
    }

    .ur-modal {
        max-height: calc(100dvh - 16px);
    }

    .ur-modal-head {
        padding: 9px 12px;
    }

    .ur-modal-body {
        padding: 10px 12px;
    }

    .ur-modal-foot {
        padding: 8px 12px;
    }
}
</style>

<div class="ur-page">
    <div class="ur-shell">
        <section class="ur-board">

            <form method="GET" action="<?= admin_view_e($baseUrl . '/admin/users') ?>" class="ur-toolbar">
                <label class="ur-search">
                    <?= admin_icon('search') ?>
                    <input
                        type="search"
                        name="search"
                        id="userSearchInput"
                        value="<?= admin_view_e((string) $search) ?>"
                        placeholder="Search users"
                        autocomplete="off"
                    >
                </label>

                <select class="ur-select" id="userRoleFilter">
                    <option value="">All Roles</option>
                    <?php foreach ($roleOptions as $roleKey => $roleName): ?>
                        <option value="<?= admin_view_e($roleKey) ?>">
                            <?= admin_view_e($roleName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select class="ur-select" id="userStatusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>

                <button type="button" class="ur-btn primary" id="userAdvancedFilter">
                    <?= admin_icon('filter') ?>
                    Apply Filter
                </button>

                <span class="ur-toolbar-spacer"></span>

                <span class="ur-settings">
                    <?= admin_icon('settings') ?>
                    User Settings
                </span>

                <span class="ur-view-toggle" aria-hidden="true">
                    <span class="active"><?= admin_icon('list') ?></span>
                    <span><?= admin_icon('grid') ?></span>
                </span>

                <button type="submit" class="ur-btn filled">
                    <?= admin_icon('search') ?>
                    Search
                </button>

                <a href="<?= admin_view_e($baseUrl . '/admin/users') ?>" class="ur-btn ghost">
                    <?= admin_icon('refresh') ?>
                    Reset
                </a>
            </form>

            <?php if ($flash_success || $flash_error): ?>
                <div class="ur-flashes">
                    <?php if ($flash_success): ?>
                        <div class="ur-flash success">
                            <?= admin_icon('check') ?>
                            <span><?= admin_view_e($flash_success) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($flash_error): ?>
                        <div class="ur-flash error">
                            <?= admin_icon('warning') ?>
                            <span><?= admin_view_e($flash_error) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="ur-summary">
                <span class="ur-summary-card">Total Users <strong><?= (int) $totalUsers ?></strong></span>
                <span class="ur-summary-card">Active <strong><?= (int) $activeUsers ?></strong></span>
                <span class="ur-summary-card">Inactive <strong><?= (int) $inactiveUsers ?></strong></span>
                <span class="ur-summary-card">System Roles <strong><?= (int) $totalRoles ?></strong></span>
            </div>

            <div class="ur-table-wrap">
                <table class="ur-table">
                    <thead>
                        <tr>
                            <th>
                                <span class="ur-th-label">
                                    <?= admin_icon('user') ?>
                                    User
                                    <span class="ur-sort">↕</span>
                                </span>
                            </th>
                            <th>Username <span class="ur-sort">↕</span></th>
                            <th>Email <span class="ur-sort">↕</span></th>
                            <th>Primary Role <span class="ur-sort">↕</span></th>
                            <th>All Roles <span class="ur-sort">↕</span></th>
                            <th>Status <span class="ur-sort">↕</span></th>
                            <th style="width:230px;">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="ur-empty">No users found.</div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($users as $user): ?>
                            <?php
                                $userId = (int) ($user['user_id'] ?? 0);
                                $isActive = (int) ($user['is_active'] ?? 1) === 1;
                                $fullName = admin_user_name($user);

                                $assignedRoleIds = admin_user_assigned_role_ids($user, $roles);
                                $assignedRoleNames = admin_user_assigned_role_names($user);

                                $primaryRole = trim((string) ($user['primary_role_name'] ?? $user['primary_role'] ?? $user['role_name'] ?? ''));
                                $primaryRole = $primaryRole !== '' ? $primaryRole : '—';

                                $rolesText = trim((string) ($user['roles_text'] ?? ''));
                                $rolesList = array_filter(array_map('trim', explode(',', $rolesText)));

                                if (empty($rolesList) && !empty($assignedRoleNames)) {
                                    $rolesList = $assignedRoleNames;
                                }

                                $roleKeys = [];

                                foreach ($rolesList as $roleItem) {
                                    $roleKey = strtolower(trim((string) $roleItem));

                                    if ($roleKey !== '') {
                                        $roleKeys[$roleKey] = $roleKey;
                                    }
                                }

                                if ($primaryRole !== '—') {
                                    $roleKeys[strtolower($primaryRole)] = strtolower($primaryRole);
                                }

                                $roleKeyText = implode('|', array_values($roleKeys));
                                $statusKey = $isActive ? 'active' : 'inactive';

                                $searchText = strtolower(trim(implode(' ', [
                                    $fullName,
                                    $user['username'] ?? '',
                                    $user['email'] ?? '',
                                    $primaryRole,
                                    $rolesText,
                                    implode(' ', $rolesList),
                                    $statusKey,
                                ])));

                                $editPayload = [
                                    'user_id' => $userId,
                                    'first_name' => (string) ($user['first_name'] ?? ''),
                                    'middle_name' => (string) ($user['middle_name'] ?? ''),
                                    'last_name' => (string) ($user['last_name'] ?? ''),
                                    'username' => (string) ($user['username'] ?? ''),
                                    'email' => (string) ($user['email'] ?? ''),
                                    'contact_number' => (string) ($user['contact_number'] ?? ''),
                                    'is_active' => $isActive ? 1 : 0,
                                    'name' => $fullName,
                                    'role_ids' => $assignedRoleIds,
                                    'role_names' => $assignedRoleNames,
                                ];
                            ?>

                            <tr
                                class="ur-user-row"
                                data-search="<?= admin_view_e($searchText) ?>"
                                data-status="<?= admin_view_e($statusKey) ?>"
                                data-roles="<?= admin_view_e($roleKeyText) ?>"
                            >
                                <td>
                                    <div class="ur-user">
                                        <span class="ur-avatar"><?= admin_view_e(admin_user_initials($user)) ?></span>

                                        <span>
                                            <span class="ur-user-name"><?= admin_view_e($fullName) ?></span>
                                            <span class="ur-user-id">User ID #<?= (int) $userId ?></span>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span class="ur-mono"><?= admin_view_e($user['username'] ?? '—') ?></span>
                                </td>

                                <td>
                                    <span class="ur-mono"><?= admin_view_e($user['email'] ?? '—') ?></span>
                                </td>

                                <td>
                                    <?php if ($primaryRole !== '—'): ?>
                                        <span class="ur-pill role"><?= admin_view_e($primaryRole) ?></span>
                                    <?php else: ?>
                                        <span class="ur-pill no-role">No Role</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (!empty($rolesList)): ?>
                                        <div class="ur-role-stack">
                                            <?php foreach ($rolesList as $roleItem): ?>
                                                <span class="ur-pill role-secondary"><?= admin_view_e($roleItem) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="ur-pill no-role">No Roles</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="ur-pill <?= $isActive ? 'active' : 'inactive' ?>">
                                        <?= admin_view_e(admin_status_text($user['is_active'] ?? 1)) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="ur-actions">
                                        <button
                                            type="button"
                                            class="ur-row-btn js-open-edit-user"
                                            data-user='<?= admin_view_e((string) json_encode($editPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>'
                                        >
                                            <?= admin_icon('edit') ?>
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            class="ur-row-btn <?= $isActive ? 'danger' : 'success' ?> js-open-status-user"
                                            data-user-id="<?= (int) $userId ?>"
                                            data-user-name="<?= admin_view_e($fullName) ?>"
                                            data-next-status="<?= $isActive ? 0 : 1 ?>"
                                            data-action-label="<?= $isActive ? 'Deactivate' : 'Activate' ?>"
                                        >
                                            <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr id="userNoMatchRow" class="ur-hidden">
                            <td colspan="7">
                                <div class="ur-empty">No users match the selected filters.</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="ur-footer">
                <div class="ur-page-info" id="userPageInfo">
                    Showing 0–0 of <?= (int) $totalUsers ?> users
                </div>

                <div class="ur-pagination" id="userPagination">
                    <button type="button" class="ur-page-btn" id="userPrevPage">‹</button>
                    <button type="button" class="ur-page-btn active" id="userCurrentPage">1</button>
                    <button type="button" class="ur-page-btn" id="userNextPage">›</button>
                </div>
            </div>
        </section>

        <div class="ur-modal-layer" id="editUserModal" aria-hidden="true">
            <div class="ur-modal" role="dialog" aria-modal="true" aria-labelledby="editUserTitle">
                <div class="ur-modal-head">
                    <div class="ur-modal-title-wrap">
                        <div class="ur-modal-icon"><?= admin_icon('user') ?></div>

                        <div>
                            <div class="ur-modal-title" id="editUserTitle">Edit User</div>
                            <div class="ur-modal-subtitle" id="editUserSubtitle">Update account information and role access.</div>
                        </div>
                    </div>

                    <button type="button" class="ur-modal-close js-close-modal" aria-label="Close">
                        <?= admin_icon('close') ?>
                    </button>
                </div>

                <div class="ur-edit-scroll">
                    <form method="POST" action="<?= admin_view_e($baseUrl . '/admin/users/update') ?>" id="editUserForm">
                        <?= Csrf::inputField(); ?>

                        <input type="hidden" name="user_id" id="edit_user_id">

                        <div class="ur-modal-body">
                            <div class="ur-form-grid">
                                <div class="ur-field">
                                    <label class="ur-label" for="edit_first_name">First Name</label>
                                    <input id="edit_first_name" type="text" name="first_name" class="ur-input" maxlength="100" required>
                                </div>

                                <div class="ur-field">
                                    <label class="ur-label" for="edit_middle_name">Middle Name</label>
                                    <input id="edit_middle_name" type="text" name="middle_name" class="ur-input" maxlength="100">
                                </div>

                                <div class="ur-field">
                                    <label class="ur-label" for="edit_last_name">Last Name</label>
                                    <input id="edit_last_name" type="text" name="last_name" class="ur-input" maxlength="100" required>
                                </div>

                                <div class="ur-field">
                                    <label class="ur-label" for="edit_username">Username</label>
                                    <input id="edit_username" type="text" name="username" class="ur-input" maxlength="100" required>
                                </div>

                                <div class="ur-field">
                                    <label class="ur-label" for="edit_email">Email</label>
                                    <input id="edit_email" type="email" name="email" class="ur-input" maxlength="150">
                                </div>

                                <div class="ur-field">
                                    <label class="ur-label" for="edit_contact_number">Contact Number</label>
                                    <input id="edit_contact_number" type="text" name="contact_number" class="ur-input" maxlength="30" placeholder="09XXXXXXXXX">
                                </div>

                                <div class="ur-field full">
                                    <label class="ur-label" for="edit_is_active">Account Status</label>
                                    <select id="edit_is_active" name="is_active" class="ur-modal-select">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                    <span class="ur-help">Inactive users cannot access protected system pages.</span>
                                </div>
                            </div>
                        </div>

                        <div class="ur-modal-foot">
                            <button type="button" class="ur-btn ghost js-close-modal">Cancel</button>
                            <button type="submit" class="ur-btn filled">Save User Details</button>
                        </div>
                    </form>

                    <?php if (!empty($roles)): ?>
                        <form method="POST" action="<?= admin_view_e($baseUrl . '/admin/users/roles') ?>" id="editRolesForm">
                            <?= Csrf::inputField(); ?>

                            <input type="hidden" name="user_id" id="roles_user_id">

                            <div class="ur-modal-body" style="border-top:1px solid var(--line);">
                                <div class="ur-field full">
                                    <label class="ur-label">Assigned Roles</label>

                                    <div class="ur-role-grid">
                                        <?php foreach ($roles as $role): ?>
                                            <?php
                                                $roleId = admin_role_id($role);
                                                $roleName = admin_role_name($role);
                                            ?>

                                            <?php if ($roleId > 0): ?>
                                                <label class="ur-role-option">
                                                    <input
                                                        type="checkbox"
                                                        name="role_ids[]"
                                                        value="<?= (int) $roleId ?>"
                                                        data-role-checkbox
                                                        data-role-id="<?= (int) $roleId ?>"
                                                        data-role-name="<?= admin_view_e(strtolower($roleName)) ?>"
                                                    >
                                                    <span><?= admin_view_e($roleName) ?></span>
                                                </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>

                                    <span class="ur-help">Keep at least one role assigned before saving role changes.</span>
                                </div>
                            </div>

                            <div class="ur-modal-foot">
                                <button type="submit" class="ur-btn primary">
                                    <?= admin_icon('shield') ?>
                                    Save Roles
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="ur-modal-layer" id="statusUserModal" aria-hidden="true">
            <div class="ur-modal sm" role="dialog" aria-modal="true" aria-labelledby="statusUserTitle">
                <div class="ur-modal-head">
                    <div class="ur-modal-title-wrap">
                        <div class="ur-modal-icon danger"><?= admin_icon('warning') ?></div>

                        <div>
                            <div class="ur-modal-title" id="statusUserTitle">Confirm Status Change</div>
                            <div class="ur-modal-subtitle">This action updates the selected user's login access.</div>
                        </div>
                    </div>

                    <button type="button" class="ur-modal-close js-close-modal" aria-label="Close">
                        <?= admin_icon('close') ?>
                    </button>
                </div>

                <div class="ur-modal-body">
                    <p class="ur-confirm-copy" id="statusConfirmText">
                        Are you sure you want to update this user?
                    </p>

                    <div class="ur-confirm-user" id="statusConfirmUser">
                        Selected user
                    </div>
                </div>

                <form method="POST" action="<?= admin_view_e($baseUrl . '/admin/users/status') ?>" id="statusUserForm">
                    <?= Csrf::inputField(); ?>

                    <input type="hidden" name="user_id" id="status_user_id">
                    <input type="hidden" name="is_active" id="status_is_active">

                    <div class="ur-modal-foot">
                        <button type="button" class="ur-btn ghost js-close-modal">Cancel</button>
                        <button type="submit" class="ur-btn danger" id="statusConfirmButton">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var rows = Array.prototype.slice.call(document.querySelectorAll('.ur-user-row'));
    var searchInput = document.getElementById('userSearchInput');
    var roleFilter = document.getElementById('userRoleFilter');
    var statusFilter = document.getElementById('userStatusFilter');
    var applyFilter = document.getElementById('userAdvancedFilter');
    var noMatchRow = document.getElementById('userNoMatchRow');

    var pageInfo = document.getElementById('userPageInfo');
    var prevPageBtn = document.getElementById('userPrevPage');
    var nextPageBtn = document.getElementById('userNextPage');
    var currentPageBtn = document.getElementById('userCurrentPage');

    var currentPage = 1;
    var perPage = 10;
    var filteredRows = rows.slice();

    function normalize(value) {
        return String(value || '').toLowerCase().trim();
    }

    function applyFilters() {
        var query = normalize(searchInput ? searchInput.value : '');
        var role = normalize(roleFilter ? roleFilter.value : '');
        var status = normalize(statusFilter ? statusFilter.value : '');

        filteredRows = rows.filter(function (row) {
            var rowSearch = normalize(row.getAttribute('data-search'));
            var rowRoles = normalize(row.getAttribute('data-roles'));
            var rowStatus = normalize(row.getAttribute('data-status'));

            var matched = true;

            if (query !== '') {
                matched = matched && rowSearch.indexOf(query) !== -1;
            }

            if (role !== '') {
                matched = matched && rowRoles.split('|').indexOf(role) !== -1;
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
            row.classList.add('ur-hidden');
        });

        filteredRows.slice(start, end).forEach(function (row) {
            row.classList.remove('ur-hidden');
        });

        if (noMatchRow) {
            noMatchRow.classList.toggle('ur-hidden', total !== 0 || rows.length === 0);
        }

        if (pageInfo) {
            var from = total > 0 ? start + 1 : 0;
            var to = Math.min(end, total);
            pageInfo.textContent = 'Showing ' + from + '–' + to + ' of ' + total + ' users';
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

        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.target.form.submit();
            }
        });
    }

    if (roleFilter) {
        roleFilter.addEventListener('change', applyFilters);
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilters);
    }

    if (applyFilter) {
        applyFilter.addEventListener('click', applyFilters);
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

    var editModal = document.getElementById('editUserModal');
    var statusModal = document.getElementById('statusUserModal');

    function openModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ur-modal-open');

        var scrollArea = modal.querySelector('.ur-edit-scroll, .ur-modal-body');

        if (scrollArea) {
            scrollArea.scrollTop = 0;
        }
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.ur-modal-layer.open')) {
            document.body.classList.remove('ur-modal-open');
        }
    }

    function closeAllModals() {
        document.querySelectorAll('.ur-modal-layer.open').forEach(function (modal) {
            closeModal(modal);
        });
    }

    function setValue(id, value) {
        var el = document.getElementById(id);

        if (el) {
            el.value = value == null ? '' : String(value);
        }
    }

    function fillRoleCheckboxes(user) {
        var assignedIds = Array.isArray(user.role_ids)
            ? user.role_ids.map(function (id) { return String(id); })
            : [];

        var assignedNames = Array.isArray(user.role_names)
            ? user.role_names.map(function (name) { return String(name).toLowerCase().trim(); })
            : [];

        document.querySelectorAll('[data-role-checkbox]').forEach(function (checkbox) {
            var roleId = String(checkbox.getAttribute('data-role-id') || '');
            var roleName = String(checkbox.getAttribute('data-role-name') || '').toLowerCase().trim();

            checkbox.checked = assignedIds.indexOf(roleId) !== -1 || assignedNames.indexOf(roleName) !== -1;
        });
    }

    document.querySelectorAll('.js-open-edit-user').forEach(function (button) {
        button.addEventListener('click', function () {
            var raw = button.getAttribute('data-user') || '{}';
            var user = {};

            try {
                user = JSON.parse(raw);
            } catch (e) {
                user = {};
            }

            setValue('edit_user_id', user.user_id || '');
            setValue('roles_user_id', user.user_id || '');
            setValue('edit_first_name', user.first_name || '');
            setValue('edit_middle_name', user.middle_name || '');
            setValue('edit_last_name', user.last_name || '');
            setValue('edit_username', user.username || '');
            setValue('edit_email', user.email || '');
            setValue('edit_contact_number', user.contact_number || '');
            setValue('edit_is_active', String(parseInt(user.is_active || 0, 10) === 1 ? 1 : 0));

            var title = document.getElementById('editUserTitle');
            var subtitle = document.getElementById('editUserSubtitle');

            if (title) {
                title.textContent = 'Edit ' + (user.name || 'User');
            }

            if (subtitle) {
                subtitle.textContent = 'User ID #' + (user.user_id || '—') + ' · Update account information and role access.';
            }

            fillRoleCheckboxes(user);
            openModal(editModal);
        });
    });

    document.querySelectorAll('.js-open-status-user').forEach(function (button) {
        button.addEventListener('click', function () {
            var userId = button.getAttribute('data-user-id') || '';
            var userName = button.getAttribute('data-user-name') || 'Selected user';
            var nextStatus = button.getAttribute('data-next-status') || '0';
            var actionLabel = button.getAttribute('data-action-label') || 'Update';

            setValue('status_user_id', userId);
            setValue('status_is_active', nextStatus);

            var title = document.getElementById('statusUserTitle');
            var text = document.getElementById('statusConfirmText');
            var selectedUser = document.getElementById('statusConfirmUser');
            var confirmButton = document.getElementById('statusConfirmButton');

            if (title) {
                title.textContent = actionLabel + ' User';
            }

            if (text) {
                text.textContent = nextStatus === '1'
                    ? 'Are you sure you want to activate this account? This user may be able to log in again depending on their assigned role.'
                    : 'Are you sure you want to deactivate this account? This user will no longer be able to access protected system pages.';
            }

            if (selectedUser) {
                selectedUser.textContent = userName + ' · User ID #' + userId;
            }

            if (confirmButton) {
                confirmButton.textContent = actionLabel + ' User';
                confirmButton.classList.toggle('danger', nextStatus !== '1');
                confirmButton.classList.toggle('filled', nextStatus === '1');
            }

            openModal(statusModal);
        });
    });

    document.querySelectorAll('.js-close-modal').forEach(function (button) {
        button.addEventListener('click', closeAllModals);
    });

    document.querySelectorAll('.ur-modal-layer').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });

    var rolesForm = document.getElementById('editRolesForm');

    if (rolesForm) {
        rolesForm.addEventListener('submit', function (event) {
            var checked = rolesForm.querySelectorAll('[data-role-checkbox]:checked');

            if (checked.length < 1) {
                event.preventDefault();
                alert('Please assign at least one role to this user.');
            }
        });
    }

    var editForm = document.getElementById('editUserForm');

    if (editForm) {
        editForm.addEventListener('submit', function (event) {
            editForm.querySelectorAll('[data-copied-role-input]').forEach(function (input) {
                input.remove();
            });

            var checkedRoles = document.querySelectorAll('[data-role-checkbox]:checked');

            if (checkedRoles.length < 1) {
                event.preventDefault();
                alert('Please assign at least one role to this user.');
                return;
            }

            checkedRoles.forEach(function (checkbox) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'role_ids[]';
                hidden.value = checkbox.value;
                hidden.setAttribute('data-copied-role-input', '1');

                editForm.appendChild(hidden);
            });
        });
    }

    renderPage();
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
?>