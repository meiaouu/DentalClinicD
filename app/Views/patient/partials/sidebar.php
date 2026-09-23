<?php

use App\Core\Csrf;

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$base = $baseUrl ?? '/DentalClinic/public';

$patientUserName = $patientUserName ?? 'Patient';
$patientUserInitial = $patientUserInitial ?? strtoupper(substr(trim((string) $patientUserName), 0, 1));

if (!function_exists('patientSidebarEscape')) {
    function patientSidebarEscape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('patientSidebarUrl')) {
    function patientSidebarUrl(string $base, string $path): string
    {
        if ($path === '/') {
            return rtrim($base, '/') . '/';
        }

        if (strpos($path, '#') === 0) {
            return rtrim($base, '/') . '/patient/dashboard' . $path;
        }

        if (strpos($path, '?') === 0) {
            return rtrim($base, '/') . '/' . ltrim($path, '?');
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

$navItemsPrimary = [
    [
        'path' => '/patient/dashboard',
        'label' => 'Dashboard',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 13.2c0-.7.3-1.4.9-1.8l6.4-5.1a2.8 2.8 0 0 1 3.4 0l6.4 5.1c.6.5.9 1.1.9 1.8v6.2c0 1-.8 1.8-1.8 1.8h-4.4v-5.7H8.2v5.7H3.8c-1 0-1.8-.8-1.8-1.8v-6.2Z"/>
            </svg>
        ',
    ],
    [
        'path' => '/patient/medical-history',
        'label' => 'Medical & History Form',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3.8h8l4 4V20H6a2 2 0 0 1-2-2V5.8a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M14 4v4h4M8 12h8M8 16h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    ],
    [
        'path' => '/patient/appointments',
        'label' => 'My Appointments',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 2.8v2.1M17 2.8v2.1M4.8 7.1h14.4M6.1 4.9h11.8a1.7 1.7 0 0 1 1.7 1.7v12a1.7 1.7 0 0 1-1.7 1.7H6.1a1.7 1.7 0 0 1-1.7-1.7v-12A1.7 1.7 0 0 1 6.1 4.9Zm2.6 5.2h2.5v2.5H8.7v-2.5Zm4.1 0h2.5v2.5h-2.5v-2.5Z"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        ',
    ],
];

$navItemsRecords = [
    [
        'path' => '#treatments',
        'label' => 'Treatment History',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M8.3 3.7c1.4-.7 2.5.1 3.7.1s2.3-.8 3.7-.1c2.1 1 3 3.6 2.2 6.7-.7 2.7-1.9 5.6-3.2 8.1-.6 1.2-2.3.9-2.4-.5l-.2-2.3c-.1-1.1-1.9-1.1-2 0L9.9 18c-.1 1.4-1.8 1.7-2.4.5-1.3-2.5-2.5-5.4-3.2-8.1-.8-3.1.1-5.7 2.2-6.7Z"
                      fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            </svg>
        ',
    ],
    [
        'path' => '#billing',
        'label' => 'Billing Summary',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7.5 5.5h8.2a3.3 3.3 0 0 1 0 6.6H7.5M7.5 5.5v13M7.5 12.1h8.2"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        ',
    ],
    [
        'path' => '/patient/documents',
        'label' => 'My Documents',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M7 3.8h6.3l3.7 3.8v12.6H7a1.7 1.7 0 0 1-1.7-1.7v-13A1.7 1.7 0 0 1 7 3.8Z"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M13.1 3.8v4h3.9M8.8 12h6.4M8.8 15.6h5"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        ',
    ],
];

$navItemsAccount = [
    [
        'path' => '#notifications',
        'label' => 'Notifications',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M18 10.8a6 6 0 1 0-12 0c0 3.6-1.4 4.9-2.1 5.7-.4.5 0 1.2.7 1.2h14.8c.7 0 1.1-.8.7-1.2-.7-.8-2.1-2.1-2.1-5.7Z"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                <path d="M9.8 20.1a2.5 2.5 0 0 0 4.4 0"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        ',
    ],
    [
        'path' => '/patient/privacy-requests',
        'label' => 'Privacy Requests',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 3.2 5.2 6.1v5.2c0 4.7 2.8 8.1 6.8 9.5 4-1.4 6.8-4.8 6.8-9.5V6.1L12 3.2Z"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                <path d="m9.2 12 1.8 1.8 3.9-4.1"
                      fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        ',
    ],
    [
        'path' => '/settings',
        'label' => 'Settings',
        'icon' => '
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 8.4a3.6 3.6 0 1 1 0 7.2 3.6 3.6 0 0 1 0-7.2Z"
                      fill="none" stroke="currentColor" stroke-width="1.8"/>
                <path d="M19.4 13.4c.1-.5.1-.9.1-1.4s0-.9-.1-1.4l2-1.5-2-3.4-2.4 1a8 8 0 0 0-2.3-1.3L14.4 3h-4l-.4 2.4a8 8 0 0 0-2.3 1.3l-2.4-1-2 3.4 2 1.5c-.1.5-.1.9-.1 1.4s0 .9.1 1.4l-2 1.5 2 3.4 2.4-1a8 8 0 0 0 2.3 1.3l.4 2.4h4l.4-2.4a8 8 0 0 0 2.3-1.3l2.4 1 2-3.4-2.1-1.5Z"
                      fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
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

?>

<style>
    html,
    body {
        margin: 0;
        overflow-x: hidden;
    }

    .patient-sidebar {
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

    .patient-sidebar + .patient-main,
    .patient-sidebar ~ .patient-main,
    .patient-sidebar + .patient-content,
    .patient-sidebar ~ .patient-content {
        margin-left: 248px;
        width: calc(100% - 248px);
    }

    .patient-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 6px 10px 14px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        flex-shrink: 0;
    }

    .patient-brand-logo {
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

    .patient-brand-copy {
        min-width: 0;
    }

    .patient-brand-title {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        color: #ffffff;
        line-height: 1.2;
    }

    .patient-brand-subtitle {
        margin-top: 2px;
        font-size: 11px;
        color: #8fa4bb;
        line-height: 1.3;
    }

    .patient-sidebar-menu {
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

    .patient-sidebar-menu::-webkit-scrollbar {
        width: 0;
        height: 0;
    }

    .patient-nav-group {
        display: grid;
        gap: 6px;
    }

    .patient-nav-label {
        padding: 0 10px;
        margin-bottom: 4px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #6f8297;
    }

    .patient-nav-link {
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
        background: transparent;
    }

    .patient-nav-link:hover {
        background: linear-gradient(90deg, rgba(20, 184, 166, 0.22) 0%, rgba(20, 184, 166, 0.12) 100%);
        color: #ffffff;
    }

    .patient-nav-icon {
        width: 18px;
        height: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: inherit;
        flex-shrink: 0;
    }

    .patient-nav-icon svg {
        width: 18px;
        height: 18px;
        display: block;
        fill: none;
    }

    .patient-nav-icon svg path,
    .patient-nav-icon svg rect,
    .patient-nav-icon svg circle {
        stroke: currentColor;
    }

    .patient-nav-icon svg path:not([fill="none"]) {
        fill: currentColor;
        stroke: none;
    }

    .patient-nav-text {
        min-width: 0;
        line-height: 1.3;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .patient-sidebar-footer {
        margin-top: auto;
        padding: 12px 10px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        font-size: 11px;
        color: #7f93a8;
        line-height: 1.5;
        flex-shrink: 0;
    }

    .patient-logout-form {
        margin: 10px 0 0;
    }

    .patient-logout-btn {
        width: 100%;
        border: 0;
        border-radius: 12px;
        padding: 10px 12px;
        background: rgba(239, 68, 68, 0.12);
        color: #fecaca;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        transition: 0.2s ease;
        text-align: left;
    }

    .patient-logout-btn:hover {
        background: rgba(239, 68, 68, 0.2);
        color: #ffffff;
    }

    @media (max-width: 900px) {
        .patient-sidebar {
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

        .patient-sidebar + .patient-main,
        .patient-sidebar ~ .patient-main,
        .patient-sidebar + .patient-content,
        .patient-sidebar ~ .patient-content {
            margin-left: 0;
            width: 100%;
        }

        .patient-sidebar-menu {
            overflow: visible;
            scrollbar-width: auto;
        }
    }

    @media (max-width: 520px) {
        .patient-sidebar {
            width: min(82vw, 290px);
            min-width: min(82vw, 290px);
            max-width: min(82vw, 290px);
            padding-left: 10px;
            padding-right: 10px;
        }

        .patient-brand-title {
            font-size: 14px;
        }

        .patient-nav-link {
            padding: 10px 10px;
        }
    }
</style>

<aside class="patient-sidebar" aria-label="Patient navigation">
    <div class="patient-brand">
        <div class="patient-brand-logo">DL</div>

        <div class="patient-brand-copy">
            <h1 class="patient-brand-title">DentaLink</h1>
            <div class="patient-brand-subtitle">Patient portal</div>
        </div>
    </div>

    <div class="patient-sidebar-menu">
        <div class="patient-nav-group">
            <div class="patient-nav-label">Menu</div>

            <?php foreach ($navItemsPrimary as $item): ?>
                <a href="<?= patientSidebarEscape(patientSidebarUrl($base, $item['path'])) ?>" class="patient-nav-link">
                    <span class="patient-nav-icon"><?= $item['icon'] ?></span>
                    <span class="patient-nav-text"><?= patientSidebarEscape($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="patient-nav-group">
            <div class="patient-nav-label">Records</div>

            <?php foreach ($navItemsRecords as $item): ?>
                <a href="<?= patientSidebarEscape(patientSidebarUrl($base, $item['path'])) ?>" class="patient-nav-link">
                    <span class="patient-nav-icon"><?= $item['icon'] ?></span>
                    <span class="patient-nav-text"><?= patientSidebarEscape($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="patient-nav-group">
            <div class="patient-nav-label">Account</div>

            <?php foreach ($navItemsAccount as $item): ?>
                <a href="<?= patientSidebarEscape(patientSidebarUrl($base, $item['path'])) ?>" class="patient-nav-link">
                    <span class="patient-nav-icon"><?= $item['icon'] ?></span>
                    <span class="patient-nav-text"><?= patientSidebarEscape($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="patient-nav-group">
            <div class="patient-nav-label">System</div>

            <?php foreach ($navItemsSystem as $item): ?>
                <a href="<?= patientSidebarEscape(patientSidebarUrl($base, $item['path'])) ?>" class="patient-nav-link">
                    <span class="patient-nav-icon"><?= $item['icon'] ?></span>
                    <span class="patient-nav-text"><?= patientSidebarEscape($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="patient-sidebar-footer">
        Patient Portal<br>
        Dental Clinic Management System

        <form method="POST" action="<?= patientSidebarEscape(rtrim($base, '/') . '/logout') ?>" class="patient-logout-form">
            <?= Csrf::inputField(); ?>
            <button type="submit" class="patient-logout-btn">Logout</button>
        </form>
    </div>
</aside>