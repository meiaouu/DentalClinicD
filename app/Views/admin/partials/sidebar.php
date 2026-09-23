<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$baseUrl = '/DentalClinic/public';

if (!function_exists('adminSidebarEscape')) {
    function adminSidebarEscape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function adminSidebarUrl(string $baseUrl, string $path): string
{
    if ($path === '/') {
        return rtrim($baseUrl, '/') . '/';
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

function adminSidebarActive(string $currentPath, string $baseUrl, string $path): bool
{
    $fullPath = adminSidebarUrl($baseUrl, $path);

    if ($path === '/') {
        return $currentPath === rtrim($baseUrl, '/') || $currentPath === rtrim($baseUrl, '/') . '/';
    }

    return $currentPath === $fullPath || str_starts_with($currentPath, $fullPath . '/');
}

$canSwitchDentist = false;

try {
    $canSwitchDentist =
        class_exists(\App\Core\Auth::class) &&
        method_exists(\App\Core\Auth::class, 'hasRole') &&
        \App\Core\Auth::hasRole('dentist') &&
        !\App\Core\Auth::hasRole('admin');
} catch (\Throwable $e) {
    $canSwitchDentist = false;
}

$navItemsPrimary = [
    [
        'path' => '/admin/dashboard',
        'label' => 'Dashboard',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 13.2c0-.7.3-1.4.9-1.8l6.4-5.1a2.8 2.8 0 0 1 3.4 0l6.4 5.1c.6.5.9 1.1.9 1.8v6.2c0 1-.8 1.8-1.8 1.8h-4.4v-5.7H8.2v5.7H3.8c-1 0-1.8-.8-1.8-1.8v-6.2Z"/>
            </svg>
        ',
    ],
    [
        'path' => '/admin/settings',
        'label' => 'Settings',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.7 1.7 0 0 0 .3-1.9l-.9-1.5a1.7 1.7 0 0 1 0-1.2l.9-1.5a1.7 1.7 0 0 0-.3-1.9L17 4.6a1.7 1.7 0 0 0-1.9-.3l-1.5.9a1.7 1.7 0 0 1-1.2 0l-1.5-.9A1.7 1.7 0 0 0 9 4.6L6.6 7a1.7 1.7 0 0 0-.3 1.9l.9 1.5a1.7 1.7 0 0 1 0 1.2l-.9 1.5a1.7 1.7 0 0 0 .3 1.9L9 17.4a1.7 1.7 0 0 0 1.9.3l1.5-.9a1.7 1.7 0 0 1 1.2 0l1.5.9a1.7 1.7 0 0 0 1.9-.3Z"/>
            </svg>
        ',
    ],
    [
        'path' => '/admin/users',
        'label' => 'Users and Roles',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 12.2a4.1 4.1 0 1 0 0-8.2 4.1 4.1 0 0 0 0 8.2Zm0 2.1c-4.2 0-7.6 2.4-8.4 5.8-.1.6.4 1.1 1 1.1h15c.6 0 1.1-.5 1-1.1-.8-3.4-4.2-5.8-8.6-5.8Z"/>
            </svg>
        ',
    ],
    [
        'path' => '/admin/dentists',
        'label' => 'Dentist Management',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M8.5 4C5.8 4 4 6.3 4 9.2C4 14.2 7 21 9.2 21C10.7 21 10 15.5 12 15.5C14 15.5 13.3 21 14.8 21C17 21 20 14.2 20 9.2C20 6.3 18.2 4 15.5 4C14 4 13.1 4.8 12 4.8C10.9 4.8 10 4 8.5 4Z"/>
                <path d="M9 10H15"/>
                <path d="M12 7V13"/>
            </svg>
        ',
    ],
];

$navItemsSecurity = [
    [
        'path' => '/admin/audit-trail',
        'label' => 'Audit Trail',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="6" y="3" width="12" height="18" rx="3"/>
                <path d="M9 8H15"/>
                <path d="M9 13L11 15L15 11"/>
            </svg>
        ',
    ],
    [
        'path' => '/admin/backup',
        'label' => 'Backup and Recovery',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="5" y="5" width="14" height="14" rx="3"/>
                <path d="M8 5V11H16V5"/>
                <path d="M9 16H15"/>
            </svg>
        ',
    ],
];

$navItemsSystem = [
    [
        'path' => '/',
        'label' => 'Home Page',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3.8 12.8 12 5.7l8.2 7.1M6.2 10.8v8.5h11.6v-8.5"/>
            </svg>
        ',
    ],
];

$navGroups = [
    [
        'label' => 'Menu',
        'items' => $navItemsPrimary,
    ],
    [
        'label' => 'Security',
        'items' => $navItemsSecurity,
    ],
    [
        'label' => 'System',
        'items' => $navItemsSystem,
    ],
];
?>

<style>
    html,
    body {
        margin: 0;
        overflow-x: hidden;
    }

    .admin-sidebar {
        width: 248px;
        min-width: 248px;
        max-width: 248px;
        height: 100vh;
        min-height: 100vh;
        background: linear-gradient(180deg, #030509 0%, #040b15 100%);
        color: #dbe7f3;
        padding: 18px 6px 16px;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        gap: 16px;
        border-right: 1px solid rgba(255, 255, 255, 0.04);
        overflow: hidden;
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        z-index: 1000;
    }

    .admin-sidebar + .admin-main,
    .admin-sidebar ~ .admin-main,
    .admin-sidebar + .admin-content,
    .admin-sidebar ~ .admin-content,
    .admin-sidebar + main,
    .admin-sidebar ~ main {
        margin-left: 248px;
        width: calc(100% - 248px);
    }

    .admin-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 6px 10px 14px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        flex-shrink: 0;
    }

    .admin-brand-logo {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: linear-gradient(135deg, #34d399 0%, #14b8a6 100%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-weight: 900;
        font-size: 14px;
        flex-shrink: 0;
        box-shadow: 0 8px 20px rgba(20, 184, 166, 0.28);
    }

    .admin-brand-logo::before {
        content: "A";
    }

    .admin-brand-copy {
        min-width: 0;
    }

    .admin-brand-title {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        color: #ffffff;
        line-height: 1.2;
    }

    .admin-brand-subtitle {
        margin-top: 2px;
        font-size: 11px;
        color: #8fa4bb;
        line-height: 1.3;
    }

    .admin-sidebar-menu {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        display: grid;
        align-content: start;
        gap: 16px;
        padding-right: 2px;
        scrollbar-width: none;
    }

    .admin-sidebar-menu::-webkit-scrollbar {
        width: 0;
        height: 0;
    }

    .admin-nav-group {
        display: grid;
        gap: 6px;
    }

    .admin-nav-label {
        padding: 0 10px;
        margin-bottom: 4px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #6f8297;
    }

    .admin-nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 11px 12px;
        border-radius: 12px;
        color: #c7d5e4;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: 0.2s ease;
        box-sizing: border-box;
        width: 100%;
        overflow: hidden;
        flex-shrink: 0;
    }

    .admin-nav-link:hover {
        background: rgba(255, 255, 255, 0.06);
        color: #ffffff;
    }

    .admin-nav-link.active {
        background: linear-gradient(90deg, rgba(20, 184, 166, 0.22) 0%, rgba(20, 184, 166, 0.12) 100%);
        color: #ffffff;
    }

    .admin-nav-link.special-link {
        color: #d1fae5;
    }

    .admin-nav-link.special-link.active {
        background: linear-gradient(90deg, rgba(34, 197, 94, 0.22) 0%, rgba(20, 184, 166, 0.12) 100%);
        color: #ffffff;
    }

    .admin-nav-link.logout-link {
        color: #fecaca;
    }

    .admin-nav-link.logout-link:hover {
        background: rgba(239, 68, 68, 0.14);
        color: #ffffff;
    }

    .admin-nav-link.logout-link.active {
        background: rgba(239, 68, 68, 0.2);
        color: #ffffff;
    }

    .admin-nav-icon {
        width: 18px;
        height: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: inherit;
        flex-shrink: 0;
    }

    .admin-nav-icon svg {
        width: 18px;
        height: 18px;
        display: block;
        fill: none;
    }

    .admin-nav-icon svg path,
    .admin-nav-icon svg rect,
    .admin-nav-icon svg circle {
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .admin-nav-icon svg path:not([fill="none"]) {
        fill: currentColor;
        stroke: none;
    }

    .admin-nav-text {
        min-width: 0;
        line-height: 1.3;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .admin-sidebar-footer {
        margin-top: auto;
        padding: 12px 10px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        font-size: 11px;
        color: #7f93a8;
        line-height: 1.5;
        flex-shrink: 0;
    }

    @media (max-width: 900px) {
        .admin-sidebar {
            position: relative;
            top: auto;
            left: auto;
            bottom: auto;
            z-index: 1;
            width: 100%;
            min-width: 100%;
            max-width: 100%;
            height: auto;
            min-height: auto;
            border-right: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            overflow: visible;
        }

        .admin-sidebar + .admin-main,
        .admin-sidebar ~ .admin-main,
        .admin-sidebar + .admin-content,
        .admin-sidebar ~ .admin-content,
        .admin-sidebar + main,
        .admin-sidebar ~ main {
            margin-left: 0;
            width: 100%;
        }

        .admin-sidebar-menu {
            overflow: visible;
            scrollbar-width: auto;
        }
    }

    @media (max-width: 520px) {
        .admin-sidebar {
            width: min(82vw, 290px);
            min-width: min(82vw, 290px);
            max-width: min(82vw, 290px);
            padding-left: 10px;
            padding-right: 10px;
        }

        .admin-brand-title {
            font-size: 14px;
        }

        .admin-nav-link {
            padding: 10px 10px;
        }
    }
</style>

<aside class="admin-sidebar">
    <div class="admin-brand">
        <?php if (!empty($systemLogoUrl ?? '')): ?>
            <img class="admin-brand-logo" src="<?= adminSidebarEscape($systemLogoUrl) ?>" alt="Clinic logo">
        <?php else: ?>
            <div class="admin-brand-logo"></div>
        <?php endif; ?>

        <div class="admin-brand-copy">
            <h1 class="admin-brand-title">DentaLink</h1>
            <div class="admin-brand-subtitle">Owner/Admin workspace</div>
        </div>
    </div>

    <div class="admin-sidebar-menu">
        <?php foreach ($navGroups as $group): ?>
            <div class="admin-nav-group">
                <div class="admin-nav-label"><?= adminSidebarEscape((string) $group['label']) ?></div>

                <?php foreach ($group['items'] as $item): ?>
                    <?php
                        $path = (string) ($item['path'] ?? '#');
                        $label = (string) ($item['label'] ?? '');
                        $icon = (string) ($item['icon'] ?? '');
                        $isActive = adminSidebarActive($currentPath, $baseUrl, $path);
                        $isSpecial = !empty($item['isSpecial']);
                        $isLogout = !empty($item['isLogout']);
                        $fullPath = adminSidebarUrl($baseUrl, $path);

                        $classes = 'admin-nav-link';
                        $classes .= $isActive ? ' active' : '';
                        $classes .= $isSpecial ? ' special-link' : '';
                        $classes .= $isLogout ? ' logout-link' : '';
                    ?>

                    <a href="<?= adminSidebarEscape($fullPath) ?>" class="<?= adminSidebarEscape($classes) ?>">
                        <span class="admin-nav-icon"><?= $icon ?></span>
                        <span class="admin-nav-text"><?= adminSidebarEscape($label) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="admin-sidebar-footer">
        Admin Panel<br>
        Dental Clinic Management System
    </div>
</aside>