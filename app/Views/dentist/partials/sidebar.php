<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$base = '/DentalClinic/public';

if (!function_exists('dentistSidebarEscape')) {
    function dentistSidebarEscape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$canAccessAdmin = false;

try {
    $canAccessAdmin =
        class_exists(\App\Core\Auth::class) &&
        method_exists(\App\Core\Auth::class, 'hasRole') &&
        \App\Core\Auth::hasRole('admin');
} catch (\Throwable $e) {
    $canAccessAdmin = false;
}

$navItemsPrimary = [
    [
        'path' => '/dentist/dashboard',
        'label' => 'Dashboard',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 13.2c0-.7.3-1.4.9-1.8l6.4-5.1a2.8 2.8 0 0 1 3.4 0l6.4 5.1c.6.5.9 1.1.9 1.8v6.2c0 1-.8 1.8-1.8 1.8h-4.4v-5.7H8.2v5.7H3.8c-1 0-1.8-.8-1.8-1.8v-6.2Z"/>
            </svg>
        ',
    ],
    [
        'path' => '/dentist/patients',
        'label' => 'Patient Records',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 12.2a4.1 4.1 0 1 0 0-8.2 4.1 4.1 0 0 0 0 8.2Zm0 2.1c-4.2 0-7.6 2.4-8.4 5.8-.1.6.4 1.1 1 1.1h15c.6 0 1.1-.5 1-1.1-.8-3.4-4.2-5.8-8.6-5.8Z"/>
            </svg>
        ',
    ],
    [
        'path' => '/dentist/appointments?date=' . date('Y-m-d'),
        'label' => "Today's Appointments",
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 2.8v2.1M17 2.8v2.1M4.8 7.1h14.4M6.1 4.9h11.8a1.7 1.7 0 0 1 1.7 1.7v12a1.7 1.7 0 0 1-1.7 1.7H6.1a1.7 1.7 0 0 1-1.7-1.7v-12A1.7 1.7 0 0 1 6.1 4.9Zm3.1 8.1 1.5 1.5 3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        ',
    ],
    [
        'path' => '/dentist/appointments',
        'label' => 'Appointments',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 2.8v2.1M17 2.8v2.1M4.8 7.1h14.4M6.1 4.9h11.8a1.7 1.7 0 0 1 1.7 1.7v12a1.7 1.7 0 0 1-1.7 1.7H6.1a1.7 1.7 0 0 1-1.7-1.7v-12A1.7 1.7 0 0 1 6.1 4.9Zm2.6 5.2h2.5v2.5H8.7v-2.5Zm4.1 0h2.5v2.5h-2.5v-2.5Z"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        ',
    ],
];

$navItemsSecondary = [
    [
        'path' => '/dentist/notifications',
        'label' => 'Notifications from Staff',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M18 10.8a6 6 0 1 0-12 0c0 3.6-1.4 4.9-2.1 5.7-.4.5 0 1.2.7 1.2h14.8c.7 0 1.1-.8.7-1.2-.7-.8-2.1-2.1-2.1-5.7Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M9.8 20.1a2.5 2.5 0 0 0 4.4 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        ',
    ],
    [
        'path' => '/dentist/availability',
        'label' => 'Availability',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 2.8v2.1M17 2.8v2.1M4.8 7.1h14.4M6.1 4.9h11.8a1.7 1.7 0 0 1 1.7 1.7v12a1.7 1.7 0 0 1-1.7 1.7H6.1a1.7 1.7 0 0 1-1.7-1.7v-12A1.7 1.7 0 0 1 6.1 4.9Zm3.1 8.1 1.4 1.4 3.4-3.4"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
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
                <path d="M3.8 12.8 12 5.7l8.2 7.1M6.2 10.8v8.5h11.6v-8.5"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        ',
    ],
];

if ($canAccessAdmin) {
    $navItemsSystem[] = [
        'path' => '/admin/dashboard',
        'label' => 'Admin Workspace',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v13a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 18.5v-13Z" fill="none" stroke="currentColor" stroke-width="1.8"/>
                <path d="M8 8h8M8 12h8M8 16h5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        ',
    ];
}


function dentistSidebarUrl(string $base, string $path): string
{
    if ($path === '/') {
        return rtrim($base, '/') . '/';
    }

    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function dentistSidebarActive(string $currentPath, string $base, string $path): bool
{
    $pathOnly = (string) (parse_url($path, PHP_URL_PATH) ?: $path);
    $fullPath = dentistSidebarUrl($base, $pathOnly);

    $pathQuery = (string) (parse_url($path, PHP_URL_QUERY) ?: '');
    $currentQuery = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?: '');

    if ($pathQuery !== '') {
        return $currentPath === $fullPath && $currentQuery === $pathQuery;
    }

    if ($path === '/') {
        return $currentPath === rtrim($base, '/') || $currentPath === rtrim($base, '/') . '/';
    }

    return str_starts_with($currentPath, $fullPath);
}
?>

<style>
    html,
    body {
        margin: 0;
        overflow-x: hidden;
    }

    .dentist-sidebar {
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

    .dentist-sidebar + .availability-main,
    .dentist-sidebar ~ .availability-main {
        margin-left: 248px;
        width: calc(100% - 248px);
    }

    .dentist-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 6px 10px 14px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        flex-shrink: 0;
    }

    .dentist-brand-logo {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: linear-gradient(135deg, #34d399 0%, #14b8a6 100%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-weight: 800;
        font-size: 14px;
        flex-shrink: 0;
        box-shadow: 0 8px 20px rgba(20, 184, 166, 0.28);
    }

    .dentist-brand-copy {
        min-width: 0;
    }

    .dentist-brand-title {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        color: #ffffff;
        line-height: 1.2;
    }

    .dentist-brand-subtitle {
        margin-top: 2px;
        font-size: 11px;
        color: #8fa4bb;
        line-height: 1.3;
    }

    .dentist-sidebar-menu {
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

    .dentist-sidebar-menu::-webkit-scrollbar {
        width: 0;
        height: 0;
    }

    .dentist-nav-group {
        display: grid;
        gap: 6px;
    }

    .dentist-nav-label {
        padding: 0 10px;
        margin-bottom: 4px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #6f8297;
    }

    .dentist-nav-link {
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

    .dentist-nav-link:hover {
        background: rgba(255, 255, 255, 0.06);
        color: #ffffff;
    }

    .dentist-nav-link.active {
        background: linear-gradient(90deg, rgba(20, 184, 166, 0.22) 0%, rgba(20, 184, 166, 0.12) 100%);
        color: #ffffff;
    }

    .dentist-nav-link.admin-link {
        color: #d1fae5;
    }

    .dentist-nav-link.admin-link.active {
        background: linear-gradient(90deg, rgba(34, 197, 94, 0.22) 0%, rgba(20, 184, 166, 0.12) 100%);
        color: #ffffff;
    }

    .dentist-nav-icon {
        width: 18px;
        height: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: inherit;
        flex-shrink: 0;
    }

    .dentist-nav-icon svg {
        width: 18px;
        height: 18px;
        display: block;
        fill: none;
    }

    .dentist-nav-icon svg path,
    .dentist-nav-icon svg rect,
    .dentist-nav-icon svg circle {
        stroke: currentColor;
    }

    .dentist-nav-icon svg path:not([fill="none"]) {
        fill: currentColor;
        stroke: none;
    }

    .dentist-nav-text {
        min-width: 0;
        line-height: 1.3;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dentist-sidebar-footer {
        margin-top: auto;
        padding: 12px 10px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        font-size: 11px;
        color: #7f93a8;
        line-height: 1.5;
        flex-shrink: 0;
    }

    @media (max-width: 900px) {
        .dentist-sidebar {
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

        .dentist-sidebar + .availability-main,
        .dentist-sidebar ~ .availability-main {
            margin-left: 0;
            width: 100%;
        }

        .dentist-sidebar-menu {
            overflow: visible;
            scrollbar-width: auto;
        }
    }

    @media (max-width: 520px) {
        .dentist-sidebar {
            width: min(82vw, 290px);
            min-width: min(82vw, 290px);
            max-width: min(82vw, 290px);
            padding-left: 10px;
            padding-right: 10px;
        }

        .dentist-brand-title {
            font-size: 14px;
        }

        .dentist-nav-link {
            padding: 10px 10px;
        }
    }
</style>

<aside class="dentist-sidebar">
    <div class="dentist-brand">
        <div class="dentist-brand-logo"></div>

        <div class="dentist-brand-copy">
            <h1 class="dentist-brand-title">DentaLink</h1>
            <div class="dentist-brand-subtitle">Dentist workspace</div>
        </div>
    </div>

    <div class="dentist-sidebar-menu">
        <div class="dentist-nav-group">
            <div class="dentist-nav-label">Menu</div>

            <?php foreach ($navItemsPrimary as $item): ?>
                <?php
                    $fullPath = dentistSidebarUrl($base, $item['path']);
                    $isActive = dentistSidebarActive($currentPath, $base, $item['path']);
                ?>

                <a href="<?= dentistSidebarEscape($fullPath) ?>"
                   class="dentist-nav-link<?= $isActive ? ' active' : '' ?>">
                    <span class="dentist-nav-icon"><?= $item['icon'] ?></span>
                    <span class="dentist-nav-text"><?= dentistSidebarEscape($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="dentist-nav-group">
            <div class="dentist-nav-label">Workflow</div>

            <?php foreach ($navItemsSecondary as $item): ?>
                <?php
                    $fullPath = dentistSidebarUrl($base, $item['path']);
                    $isActive = dentistSidebarActive($currentPath, $base, $item['path']);
                ?>

                <a href="<?= dentistSidebarEscape($fullPath) ?>"
                   class="dentist-nav-link<?= $isActive ? ' active' : '' ?>">
                    <span class="dentist-nav-icon"><?= $item['icon'] ?></span>
                    <span class="dentist-nav-text"><?= dentistSidebarEscape($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="dentist-nav-group">
            <div class="dentist-nav-label">System</div>

            <?php foreach ($navItemsSystem as $item): ?>
                <?php
                    $fullPath = dentistSidebarUrl($base, $item['path']);
                    $isActive = dentistSidebarActive($currentPath, $base, $item['path']);
                    $isAdminLink = $item['path'] === '/admin/dashboard';
                ?>

                <a href="<?= dentistSidebarEscape($fullPath) ?>"
                   class="dentist-nav-link<?= $isActive ? ' active' : '' ?><?= $isAdminLink ? ' admin-link' : '' ?>">
                    <span class="dentist-nav-icon"><?= $item['icon'] ?></span>
                    <span class="dentist-nav-text"><?= dentistSidebarEscape($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="dentist-sidebar-footer">
        Dentist Panel<br>
        Dental Clinic Management System
    </div>
</aside>