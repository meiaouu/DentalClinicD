<?php
$pageTitle = 'Audit Trail';

$logs     = isset($logs)    && is_array($logs) ? $logs    : [];
$total    = isset($total)   ? (int) $total    : 0;
$page     = isset($page)    ? max(1, (int) $page)    : 1;
$perPage  = isset($perPage) ? max(1, (int) $perPage) : 25;
$search   = $search ?? '';

$baseUrl = '/DentalClinic/public';

if (!function_exists('admin_view_e')) {
    function admin_view_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_audit_action')) {
    function admin_audit_action(array $log): string
    {
        return (string) ($log['action'] ?? $log['action_name'] ?? 'Action');
    }
}

if (!function_exists('admin_audit_entity')) {
    function admin_audit_entity(array $log): string
    {
        return (string) (
            $log['entity_type'] ??
            $log['record_type'] ??
            $log['module_name'] ??
            'System'
        );
    }
}

if (!function_exists('admin_audit_user')) {
    function admin_audit_user(array $log): string
    {
        $userName = trim((string) (
            ($log['first_name'] ?? '') . ' ' .
            ($log['last_name'] ?? '')
        ));

        if ($userName === '') {
            $userName = trim((string) ($log['username'] ?? ''));
        }

        return $userName !== '' ? $userName : 'System';
    }
}

if (!function_exists('admin_audit_initials')) {
    function admin_audit_initials(string $name): string
    {
        $name = trim($name);

        if ($name === '' || strtolower($name) === 'system') {
            return 'SY';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $first = mb_strtoupper(mb_substr($parts[0] ?? 'U', 0, 1));
        $last  = mb_strtoupper(mb_substr($parts[count($parts) - 1] ?? '', 0, 1));

        return trim($first . $last) !== '' ? $first . $last : 'U';
    }
}

if (!function_exists('admin_audit_date')) {
    function admin_audit_date(?string $date): string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return '—';
        }

        $time = strtotime($date);

        return $time ? date('d F Y', $time) : $date;
    }
}

if (!function_exists('admin_audit_time')) {
    function admin_audit_time(?string $date): string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return '';
        }

        $time = strtotime($date);

        return $time ? date('h:i A', $time) : '';
    }
}

if (!function_exists('admin_audit_category')) {
    function admin_audit_category(array $log): string
    {
        $text = strtolower(admin_audit_action($log) . ' ' . admin_audit_entity($log) . ' ' . ($log['description'] ?? ''));

        if (
            str_contains($text, 'login') ||
            str_contains($text, 'logout') ||
            str_contains($text, 'password') ||
            str_contains($text, 'auth')
        ) {
            return 'security';
        }

        if (
            str_contains($text, 'delete') ||
            str_contains($text, 'remove') ||
            str_contains($text, 'deactivate') ||
            str_contains($text, 'cancel') ||
            str_contains($text, 'reject')
        ) {
            return 'critical';
        }

        if (
            str_contains($text, 'create') ||
            str_contains($text, 'add') ||
            str_contains($text, 'insert') ||
            str_contains($text, 'register')
        ) {
            return 'created';
        }

        if (
            str_contains($text, 'update') ||
            str_contains($text, 'edit') ||
            str_contains($text, 'change') ||
            str_contains($text, 'status') ||
            str_contains($text, 'approve')
        ) {
            return 'updated';
        }

        if (
            str_contains($text, 'payment') ||
            str_contains($text, 'billing') ||
            str_contains($text, 'invoice')
        ) {
            return 'billing';
        }

        return 'system';
    }
}

if (!function_exists('admin_audit_status_label')) {
    function admin_audit_status_label(string $category): string
    {
        return match ($category) {
            'security' => 'Security',
            'critical' => 'Critical',
            'created'  => 'Created',
            'updated'  => 'Updated',
            'billing'  => 'Billing',
            default    => 'System',
        };
    }
}

if (!function_exists('admin_audit_icon')) {
    function admin_audit_icon(string $name): string
    {
        $icons = [
            'search'   => '<circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.4-3.4"></path>',
            'refresh'  => '<path d="M20 12a8 8 0 1 1-2.3-5.6"></path><path d="M20 4v6h-6"></path>',
            'filter'   => '<path d="M3 5h18"></path><path d="M6 12h12"></path><path d="M10 19h4"></path>',
            'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"></path><path d="M19.4 15a1.8 1.8 0 0 0 .36 1.98l.05.05a2.1 2.1 0 0 1-2.97 2.97l-.05-.05A1.8 1.8 0 0 0 14.8 19.6a1.8 1.8 0 0 0-1 .57V20.3a2.1 2.1 0 0 1-4.2 0v-.08a1.8 1.8 0 0 0-1-.57 1.8 1.8 0 0 0-1.98.36l-.05.05a2.1 2.1 0 0 1-2.97-2.97l.05-.05A1.8 1.8 0 0 0 4 15.2a1.8 1.8 0 0 0-.57-1H3.3a2.1 2.1 0 0 1 0-4.2h.08a1.8 1.8 0 0 0 .57-1 1.8 1.8 0 0 0-.36-1.98l-.05-.05A2.1 2.1 0 0 1 6.5 4l.05.05A1.8 1.8 0 0 0 8.4 4.4a1.8 1.8 0 0 0 1-.57V3.7a2.1 2.1 0 0 1 4.2 0v.08a1.8 1.8 0 0 0 1 .57 1.8 1.8 0 0 0 1.98-.36l.05-.05a2.1 2.1 0 0 1 2.97 2.97l-.05.05A1.8 1.8 0 0 0 20 8.8a1.8 1.8 0 0 0 .57 1h.13a2.1 2.1 0 0 1 0 4.2h-.08a1.8 1.8 0 0 0-1 .57z"></path>',
            'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect>',
            'list'     => '<path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path>',
            'date'     => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path>',
            'clock'    => '<circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path>',
            'user'     => '<path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle>',
            'info'     => '<circle cx="12" cy="12" r="9"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path>',
            'x'        => '<path d="M18 6 6 18"></path><path d="M6 6l12 12"></path>',
            'check'    => '<path d="M20 6 9 17l-5-5"></path>',
            'shield'   => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"></path><path d="M9 12l2 2 4-4"></path>',
        ];

        $path = $icons[$name] ?? $icons['info'];

        return '
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2.1" stroke-linecap="round"
                stroke-linejoin="round" aria-hidden="true">
                ' . $path . '
            </svg>
        ';
    }
}

$totalPages = max(1, (int) ceil($total / $perPage));

$pageLinks = [];
if ($totalPages <= 7) {
    $pageLinks = range(1, $totalPages);
} else {
    $pageLinks = array_unique(array_filter([
        1,
        2,
        $page - 1,
        $page,
        $page + 1,
        $totalPages - 1,
        $totalPages,
    ], static fn($p) => $p >= 1 && $p <= $totalPages));

    sort($pageLinks);
}

$actionOptions = [];
$entityOptions = [];

foreach ($logs as $log) {
    $action = trim(admin_audit_action($log));
    $entity = trim(admin_audit_entity($log));

    if ($action !== '') {
        $actionOptions[strtolower($action)] = $action;
    }

    if ($entity !== '') {
        $entityOptions[strtolower($entity)] = $entity;
    }
}

ksort($actionOptions);
ksort($entityOptions);

$rangeFrom = $total > 0 ? (($page - 1) * $perPage + 1) : 0;
$rangeTo = min($page * $perPage, $total);

ob_start();
?>

<style>
.at-page,
.at-page * {
    box-sizing: border-box;
}

.at-page {
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

.at-shell {
    max-width: 1460px;
    margin: 0 auto;
}

.at-board {
    background: var(--panel);
    border: 1px solid rgba(16, 24, 40, .08);
    border-radius: 12px;
    box-shadow: 0 10px 28px rgba(16, 24, 40, .10);
    overflow: hidden;
}

/* Toolbar */
.at-toolbar {
    min-height: 58px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--line);
    background: #f7f9fc;
    display: grid;
    grid-template-columns: minmax(170px, 1fr) auto auto auto 1fr auto auto;
    gap: 9px;
    align-items: center;
}

.at-search {
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

.at-search input {
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

.at-select {
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
    min-width: 150px;
}

.at-select.small {
    min-width: 132px;
}

.at-btn {
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

.at-btn.primary {
    background: #ffffff;
    color: var(--brand);
    border-color: var(--brand);
}

.at-btn.primary:hover {
    background: var(--brand-soft);
}

.at-btn.filled {
    background: var(--brand);
    color: #ffffff;
    border-color: var(--brand);
    box-shadow: 0 6px 14px rgba(15, 118, 110, .18);
}

.at-btn.filled:hover {
    background: var(--brand-2);
    border-color: var(--brand-2);
}

.at-btn.ghost {
    background: #ffffff;
    color: var(--text-2);
    border-color: var(--line);
}

.at-btn.ghost:hover {
    background: var(--panel-soft);
}

.at-toolbar-spacer {
    min-width: 6px;
}

.at-settings {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: var(--text-2);
    font-size: 12px;
    font-weight: 850;
    white-space: nowrap;
}

.at-view-toggle {
    height: 34px;
    display: inline-flex;
    border: 1px solid var(--line);
    border-radius: 7px;
    overflow: hidden;
    background: #ffffff;
}

.at-view-toggle span {
    width: 34px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--muted);
}

.at-view-toggle span + span {
    border-left: 1px solid var(--line);
}

.at-view-toggle .active {
    background: var(--panel-soft);
    color: var(--brand);
}

/* Table */
.at-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.at-table {
    width: 100%;
    min-width: 1120px;
    border-collapse: separate;
    border-spacing: 0;
}

.at-table th {
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

.at-table th:last-child,
.at-table td:last-child {
    border-right: 0;
}

.at-th-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.at-sort {
    margin-left: 6px;
    color: #b0bac7;
    font-size: 10px;
}

.at-table td {
    height: 48px;
    padding: 8px 14px;
    border-bottom: 1px solid #edf0f4;
    border-right: 1px solid #edf0f4;
    color: var(--text);
    font-size: 12px;
    font-weight: 650;
    vertical-align: middle;
    background: #ffffff;
}

.at-table tbody tr:hover td {
    background: #fbfcfd;
}

.at-date {
    color: var(--text);
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

.at-time {
    display: block;
    margin-top: 2px;
    color: var(--faint);
    font-size: 11px;
    font-weight: 700;
}

.at-user {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 160px;
}

.at-avatar {
    width: 26px;
    height: 26px;
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

.at-user-name {
    color: var(--text);
    font-size: 12px;
    font-weight: 850;
    white-space: nowrap;
}

.at-pill {
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

.at-pill::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: currentColor;
}

.at-pill.security {
    background: var(--purple-soft);
    color: var(--purple);
}

.at-pill.critical {
    background: var(--red-soft);
    color: var(--red);
}

.at-pill.created {
    background: var(--green-soft);
    color: var(--green);
}

.at-pill.updated {
    background: var(--blue-soft);
    color: var(--blue);
}

.at-pill.billing {
    background: var(--amber-soft);
    color: var(--amber);
}

.at-pill.system {
    background: var(--brand-soft);
    color: var(--brand);
}

.at-entity {
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

.at-desc {
    max-width: 380px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text-2);
    font-size: 12px;
    font-weight: 650;
}

.at-ip {
    color: var(--text-2);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: 11px;
    font-weight: 750;
    white-space: nowrap;
}

.at-action-cell {
    display: flex;
    align-items: center;
    justify-content: flex-start;
}

.at-row-btn {
    height: 29px;
    border: 1px solid var(--brand);
    border-radius: 6px;
    background: #ffffff;
    color: var(--brand);
    padding: 0 10px;
    font: inherit;
    font-size: 11.5px;
    font-weight: 850;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.at-row-btn:hover {
    background: var(--brand-soft);
}

/* Empty */
.at-empty {
    padding: 44px 18px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
    font-weight: 750;
}

.at-hidden {
    display: none !important;
}

/* Footer */
.at-footer {
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

.at-page-info {
    color: var(--muted);
    font-size: 12px;
    font-weight: 800;
}

.at-pagination {
    display: flex;
    align-items: center;
    gap: 6px;
}

.at-page-btn,
.at-page-link,
.at-page-ellipsis {
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
    text-decoration: none;
}

.at-page-link:hover {
    border-color: var(--brand);
    color: var(--brand);
    background: var(--brand-soft);
}

.at-page-btn.active {
    border-color: var(--brand);
    color: var(--brand);
    box-shadow: 0 0 0 3px rgba(15, 118, 110, .10);
}

.at-page-btn.disabled {
    opacity: .45;
}

@media (max-width: 1200px) {
    .at-toolbar {
        grid-template-columns: 1fr 1fr 1fr;
    }

    .at-toolbar-spacer,
    .at-settings,
    .at-view-toggle {
        display: none;
    }
}

@media (max-width: 760px) {
    .at-page {
        padding: 10px;
    }

    .at-toolbar {
        grid-template-columns: 1fr;
    }

    .at-search,
    .at-select,
    .at-btn {
        width: 100%;
    }

    .at-footer {
        align-items: stretch;
        flex-direction: column;
    }

    .at-pagination {
        flex-wrap: wrap;
    }
}
</style>

<div class="at-page">
    <div class="at-shell">
        <section class="at-board">

            <form method="GET" action="<?= admin_view_e($baseUrl . '/admin/audit-trail') ?>" class="at-toolbar">
                <label class="at-search">
                    <?= admin_audit_icon('search') ?>
                    <input
                        type="search"
                        name="search"
                        id="auditSearchInput"
                        value="<?= admin_view_e((string) $search) ?>"
                        placeholder="Search audit logs"
                        autocomplete="off"
                    >
                </label>

                <select class="at-select small" id="auditActionFilter">
                    <option value="">All Actions</option>
                    <?php foreach ($actionOptions as $actionKey => $actionLabel): ?>
                        <option value="<?= admin_view_e($actionKey) ?>">
                            <?= admin_view_e($actionLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select class="at-select small" id="auditEntityFilter">
                    <option value="">All Modules</option>
                    <?php foreach ($entityOptions as $entityKey => $entityLabel): ?>
                        <option value="<?= admin_view_e($entityKey) ?>">
                            <?= admin_view_e($entityLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select class="at-select small" id="auditCategoryFilter">
                    <option value="">All Status</option>
                    <option value="security">Security</option>
                    <option value="critical">Critical</option>
                    <option value="created">Created</option>
                    <option value="updated">Updated</option>
                    <option value="billing">Billing</option>
                    <option value="system">System</option>
                </select>

                <button type="button" class="at-btn primary" id="auditAdvancedFilter">
                    <?= admin_audit_icon('filter') ?>
                    Apply Filter
                </button>

                <span class="at-toolbar-spacer"></span>

                <span class="at-settings">
                    <?= admin_audit_icon('settings') ?>
                    Audit Settings
                </span>

                <span class="at-view-toggle" aria-hidden="true">
                    <span class="active"><?= admin_audit_icon('list') ?></span>
                    <span><?= admin_audit_icon('grid') ?></span>
                </span>

                <button type="submit" class="at-btn filled">
                    <?= admin_audit_icon('search') ?>
                    Search
                </button>

                <a href="<?= admin_view_e($baseUrl . '/admin/audit-trail') ?>" class="at-btn ghost">
                    <?= admin_audit_icon('refresh') ?>
                    Reset
                </a>
            </form>

            <div class="at-table-wrap">
                <table class="at-table">
                    <thead>
                        <tr>
                            <th>
                                <span class="at-th-label">
                                    <?= admin_audit_icon('date') ?>
                                    Date
                                    <span class="at-sort">↕</span>
                                </span>
                            </th>
                            <th>
                                <span class="at-th-label">
                                    <?= admin_audit_icon('user') ?>
                                    User
                                    <span class="at-sort">↕</span>
                                </span>
                            </th>
                            <th>
                                <span class="at-th-label">
                                    <?= admin_audit_icon('check') ?>
                                    Action
                                    <span class="at-sort">↕</span>
                                </span>
                            </th>
                            <th>
                                <span class="at-th-label">
                                    <?= admin_audit_icon('grid') ?>
                                    Module
                                    <span class="at-sort">↕</span>
                                </span>
                            </th>
                            <th>
                                <span class="at-th-label">
                                    <?= admin_audit_icon('info') ?>
                                    Description
                                    <span class="at-sort">↕</span>
                                </span>
                            </th>
                            <th>
                                <span class="at-th-label">
                                    <?= admin_audit_icon('shield') ?>
                                    IP Address
                                    <span class="at-sort">↕</span>
                                </span>
                            </th>
                            <th style="width:120px;">
                                <span class="at-th-label">
                                    Action
                                </span>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="at-empty">
                                        No audit logs found.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($logs as $log): ?>
                            <?php
                                $userName = admin_audit_user($log);
                                $action = admin_audit_action($log);
                                $entity = admin_audit_entity($log);
                                $category = admin_audit_category($log);
                                $description = (string) ($log['description'] ?? '—');
                                $ipAddress = (string) ($log['ip_address'] ?? '—');
                                $createdAt = (string) ($log['created_at'] ?? '');

                                $rowSearch = strtolower(trim(implode(' ', [
                                    $createdAt,
                                    $userName,
                                    $action,
                                    $entity,
                                    $description,
                                    $ipAddress,
                                    $category,
                                    admin_audit_status_label($category),
                                ])));
                            ?>

                            <tr
                                class="at-log-row"
                                data-search="<?= admin_view_e($rowSearch) ?>"
                                data-action="<?= admin_view_e(strtolower(trim($action))) ?>"
                                data-entity="<?= admin_view_e(strtolower(trim($entity))) ?>"
                                data-category="<?= admin_view_e($category) ?>"
                            >
                                <td>
                                    <span class="at-date">
                                        <?= admin_view_e(admin_audit_date($createdAt)) ?>
                                        <span class="at-time"><?= admin_view_e(admin_audit_time($createdAt)) ?></span>
                                    </span>
                                </td>

                                <td>
                                    <div class="at-user">
                                        <span class="at-avatar"><?= admin_view_e(admin_audit_initials($userName)) ?></span>
                                        <span class="at-user-name"><?= admin_view_e($userName) ?></span>
                                    </div>
                                </td>

                                <td>
                                    <span class="at-pill <?= admin_view_e($category) ?>">
                                        <?= admin_view_e($action) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="at-entity"><?= admin_view_e($entity) ?></span>
                                </td>

                                <td>
                                    <div class="at-desc" title="<?= admin_view_e($description) ?>">
                                        <?= admin_view_e($description) ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="at-ip"><?= admin_view_e($ipAddress !== '' ? $ipAddress : '—') ?></span>
                                </td>

                                <td>
                                    <div class="at-action-cell">
                                        <button
                                            type="button"
                                            class="at-row-btn js-audit-details"
                                            data-date="<?= admin_view_e(admin_audit_date($createdAt) . ' ' . admin_audit_time($createdAt)) ?>"
                                            data-user="<?= admin_view_e($userName) ?>"
                                            data-action="<?= admin_view_e($action) ?>"
                                            data-entity="<?= admin_view_e($entity) ?>"
                                            data-description="<?= admin_view_e($description) ?>"
                                            data-ip="<?= admin_view_e($ipAddress) ?>"
                                        >
                                            View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <tr id="auditNoMatchRow" class="at-hidden">
                            <td colspan="7">
                                <div class="at-empty">
                                    No audit logs match the selected filters.
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="at-footer">
                <div class="at-page-info">
                    Showing <?= (int) $rangeFrom ?>–<?= (int) $rangeTo ?> of <?= number_format($total) ?> logs · Page <?= (int) $page ?> of <?= (int) $totalPages ?>
                </div>

                <div class="at-pagination">
                    <?php if ($page > 1): ?>
                        <a
                            class="at-page-link"
                            href="<?= admin_view_e($baseUrl . '/admin/audit-trail?page=' . ($page - 1) . '&search=' . urlencode((string) $search)) ?>"
                        >
                            ‹
                        </a>
                    <?php else: ?>
                        <span class="at-page-btn disabled">‹</span>
                    <?php endif; ?>

                    <?php
                        $previousPageLink = null;
                        foreach ($pageLinks as $pageLink):
                            if ($previousPageLink !== null && $pageLink - $previousPageLink > 1):
                    ?>
                        <span class="at-page-ellipsis">...</span>
                    <?php
                            endif;
                            $previousPageLink = $pageLink;
                    ?>

                        <?php if ($pageLink === $page): ?>
                            <span class="at-page-btn active"><?= (int) $pageLink ?></span>
                        <?php else: ?>
                            <a
                                class="at-page-link"
                                href="<?= admin_view_e($baseUrl . '/admin/audit-trail?page=' . $pageLink . '&search=' . urlencode((string) $search)) ?>"
                            >
                                <?= (int) $pageLink ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($page < $totalPages): ?>
                        <a
                            class="at-page-link"
                            href="<?= admin_view_e($baseUrl . '/admin/audit-trail?page=' . ($page + 1) . '&search=' . urlencode((string) $search)) ?>"
                        >
                            ›
                        </a>
                    <?php else: ?>
                        <span class="at-page-btn disabled">›</span>
                    <?php endif; ?>
                </div>
            </div>

        </section>
    </div>
</div>

<script>
(function () {
    'use strict';

    var searchInput = document.getElementById('auditSearchInput');
    var actionFilter = document.getElementById('auditActionFilter');
    var entityFilter = document.getElementById('auditEntityFilter');
    var categoryFilter = document.getElementById('auditCategoryFilter');
    var applyFilterButton = document.getElementById('auditAdvancedFilter');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.at-log-row'));
    var noMatchRow = document.getElementById('auditNoMatchRow');

    function normalize(value) {
        return String(value || '').toLowerCase().trim();
    }

    function filterAuditRows() {
        var query = normalize(searchInput ? searchInput.value : '');
        var action = normalize(actionFilter ? actionFilter.value : '');
        var entity = normalize(entityFilter ? entityFilter.value : '');
        var category = normalize(categoryFilter ? categoryFilter.value : '');
        var visible = 0;

        rows.forEach(function (row) {
            var rowSearch = normalize(row.getAttribute('data-search'));
            var rowAction = normalize(row.getAttribute('data-action'));
            var rowEntity = normalize(row.getAttribute('data-entity'));
            var rowCategory = normalize(row.getAttribute('data-category'));

            var matched = true;

            if (query !== '') {
                matched = matched && rowSearch.indexOf(query) !== -1;
            }

            if (action !== '') {
                matched = matched && rowAction === action;
            }

            if (entity !== '') {
                matched = matched && rowEntity === entity;
            }

            if (category !== '') {
                matched = matched && rowCategory === category;
            }

            row.classList.toggle('at-hidden', !matched);

            if (matched) {
                visible++;
            }
        });

        if (noMatchRow) {
            noMatchRow.classList.toggle('at-hidden', visible !== 0 || rows.length === 0);
        }
    }

    if (applyFilterButton) {
        applyFilterButton.addEventListener('click', filterAuditRows);
    }

    if (actionFilter) {
        actionFilter.addEventListener('change', filterAuditRows);
    }

    if (entityFilter) {
        entityFilter.addEventListener('change', filterAuditRows);
    }

    if (categoryFilter) {
        categoryFilter.addEventListener('change', filterAuditRows);
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            filterAuditRows();
        });

        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.target.form.submit();
            }
        });
    }

    document.querySelectorAll('.js-audit-details').forEach(function (button) {
        button.addEventListener('click', function () {
            var details = [
                'Date: ' + (button.getAttribute('data-date') || '—'),
                'User: ' + (button.getAttribute('data-user') || '—'),
                'Action: ' + (button.getAttribute('data-action') || '—'),
                'Module: ' + (button.getAttribute('data-entity') || '—'),
                'IP Address: ' + (button.getAttribute('data-ip') || '—'),
                '',
                'Description:',
                button.getAttribute('data-description') || '—'
            ].join('\n');

            alert(details);
        });
    });

    filterAuditRows();
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
?>