<?php
$pageTitle = $pageTitle ?? 'Owner/Admin Panel';
$authUser = \App\Core\Auth::user() ?? [];

$baseUrl = '/DentalClinic/public';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

$displayName = trim((string) (
    ($authUser['first_name'] ?? '') . ' ' .
    ($authUser['last_name'] ?? '')
));

if ($displayName === '') {
    $displayName = 'Owner/Admin';
}

$canAccessOwnerAdmin = false;
$canAccessDentist = false;

try {
    if (method_exists(\App\Core\Auth::class, 'hasAnyRole')) {
        $canAccessOwnerAdmin = \App\Core\Auth::hasAnyRole(['owner', 'admin']);
    }

    if (!$canAccessOwnerAdmin && method_exists(\App\Core\Auth::class, 'hasRole')) {
        $canAccessOwnerAdmin =
            \App\Core\Auth::hasRole('owner') ||
            \App\Core\Auth::hasRole('admin');
    }

    $canAccessDentist =
        method_exists(\App\Core\Auth::class, 'hasRole') &&
        \App\Core\Auth::hasRole('dentist');
} catch (\Throwable $e) {
    $canAccessOwnerAdmin = false;
    $canAccessDentist = false;
}

$isAdminArea = str_starts_with($currentPath, $baseUrl . '/admin')
    || str_starts_with($currentPath, $baseUrl . '/index.php/admin');

$isDentistArea = str_starts_with($currentPath, $baseUrl . '/dentist')
    || str_starts_with($currentPath, $baseUrl . '/index.php/dentist');

$switchWorkspaceUrl = '';
$switchWorkspaceLabel = '';

if ($isAdminArea && $canAccessDentist && (!$canAccessOwnerAdmin || \App\Core\Auth::isAdminDentistAccount())) {
    $switchWorkspaceUrl = $baseUrl . '/dentist/dashboard';
    $switchWorkspaceLabel = 'Switch to Dentist Dashboard';
} elseif ($isDentistArea && $canAccessOwnerAdmin && !$canAccessDentist) {
    $switchWorkspaceUrl = $baseUrl . '/admin/dashboard';
    $switchWorkspaceLabel = 'Switch to Owner/Admin Dashboard';
}

$visibleRoleLabel = 'Owner/Admin';

if ($isDentistArea && $canAccessDentist) {
    $visibleRoleLabel = 'Dentist';
} elseif ($canAccessOwnerAdmin) {
    $visibleRoleLabel = 'Owner/Admin';
} elseif ($canAccessDentist) {
    $visibleRoleLabel = 'Dentist';
}
?>

<style>
    .admin-topbar {
        background: #ffffff;
        border-bottom: 1px solid #e5e7eb;
        padding: 12px 22px;
        min-height: 64px;
        height: 64px;
        position: fixed;
        top: 0;
        left: 248px;
        right: 0;
        z-index: 5000;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        box-sizing: border-box;
    }

    .admin-topbar-left {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1 1 auto;
        overflow: hidden;
    }

    .admin-mobile-menu-btn {
        width: 40px;
        height: 40px;
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #111827;
        border-radius: 8px;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex: 0 0 auto;
        padding: 0;
    }

    .admin-mobile-menu-btn:hover {
        background: #f3f4f6;
    }

    .admin-mobile-menu-btn svg {
        width: 20px;
        height: 20px;
        stroke-width: 2.2;
    }

    .admin-topbar-title {
        margin: 0;
        color: #111827;
        font-size: 20px;
        font-weight: 900;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
        max-width: 100%;
    }

    .admin-topbar-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        flex: 0 0 auto;
        min-width: 0;
    }

    .admin-profile-box {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }

    .admin-profile-btn {
        position: relative;
        width: 40px;
        height: 40px;
        border: 1px solid #d1d5db;
        background: #f9fafb;
        color: #111827;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    }

    .admin-profile-btn:hover,
    .admin-profile-btn.is-open {
        background: #eff6ff;
        color: #2563eb;
        border-color: #bfdbfe;
    }

    .admin-profile-btn svg {
        width: 19px;
        height: 19px;
        stroke-width: 2.2;
        pointer-events: none;
    }

    .admin-profile-dropdown {
        position: fixed;
        top: 76px;
        right: 22px;
        width: 405px;
        max-width: calc(100vw - 32px);
        max-height: calc(100vh - 92px);
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        box-shadow: 0 14px 35px rgba(15, 23, 42, 0.18);
        z-index: 6000;
        overflow: hidden;
        display: none;
    }

    .admin-profile-head {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px;
        border-bottom: 1px solid #e5e7eb;
        background: #f9fafb;
    }

    .admin-profile-avatar {
        width: 58px;
        height: 58px;
        min-width: 58px;
        border-radius: 999px;
        background: #eef2ff;
        color: #1e3a8a;
        border: 1px solid #c7d2fe;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .admin-profile-avatar svg {
        width: 30px;
        height: 30px;
        stroke-width: 1.9;
    }

    .admin-profile-info {
        min-width: 0;
        flex: 1;
    }

    .admin-profile-name {
        display: block;
        color: #111827;
        font-size: 18px;
        font-weight: 900;
        line-height: 1.3;
        word-break: break-word;
    }

    .admin-profile-role {
        display: block;
        margin-top: 4px;
        color: #6b7280;
        font-size: 14px;
        font-weight: 700;
    }

    .admin-profile-switch {
        width: 100%;
        min-height: 48px;
        border: 0;
        background: #ecfdf5;
        color: #047857;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 16px;
        font-size: 15px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
        box-sizing: border-box;
    }

    .admin-profile-switch:hover {
        background: #d1fae5;
        color: #065f46;
    }

    .admin-profile-switch svg {
        width: 18px;
        height: 18px;
        stroke-width: 2.2;
        flex-shrink: 0;
    }

    .admin-profile-link,
    .admin-profile-logout {
        width: 100%;
        min-height: 48px;
        border: 0;
        background: #ffffff;
        color: #111827;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 16px;
        font-size: 15px;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        text-align: left;
        box-sizing: border-box;
        font-family: inherit;
    }

    .admin-profile-link:hover,
    .admin-profile-logout:hover {
        background: #f3f4f6;
    }

    .admin-profile-logout {
        color: #dc2626;
    }

    .admin-profile-form {
        margin: 0;
    }

    .admin-sidebar-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.42);
        z-index: 3999;
        display: none;
    }

    body.admin-sidebar-open .admin-sidebar-overlay {
        display: block;
    }

    @media (max-width: 900px) {
        .admin-topbar {
            left: 0;
            right: 0;
            width: 100%;
            max-width: 100vw;
            padding: 12px 16px;
        }

        .admin-mobile-menu-btn {
            display: inline-flex;
        }

        .admin-topbar-title {
            font-size: 18px;
            max-width: calc(100vw - 132px);
        }

        .admin-sidebar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            height: 100dvh !important;
            width: 248px !important;
            max-width: 248px !important;
            min-width: 248px !important;
            z-index: 7000 !important;
            transform: translateX(-100%);
            transition: transform 0.22s ease;
        }

        body.admin-sidebar-open .admin-sidebar {
            transform: translateX(0);
        }

        .admin-profile-dropdown {
            top: 76px;
            right: 16px;
            left: auto;
            width: min(405px, calc(100vw - 32px));
            max-height: calc(100vh - 92px);
        }
    }

    @media (max-width: 520px) {
        .admin-topbar {
            padding: 10px 12px;
            gap: 8px;
        }

        .admin-topbar-title {
            font-size: 16px;
            max-width: calc(100vw - 112px);
            white-space: normal;
            overflow-wrap: anywhere;
            line-height: 1.2;
        }

        .admin-topbar-actions {
            gap: 8px;
        }

        .admin-profile-btn,
        .admin-mobile-menu-btn {
            width: 38px;
            height: 38px;
        }

        .admin-profile-avatar {
            width: 50px;
            height: 50px;
            min-width: 50px;
        }

        .admin-profile-avatar svg {
            width: 26px;
            height: 26px;
        }

        .admin-profile-name {
            font-size: 16px;
        }

        .admin-profile-role {
            font-size: 13px;
        }

        .admin-profile-dropdown {
            right: 10px;
            width: calc(100vw - 20px);
        }
    }

    /* Strong override for old admin.css */
    body .admin-topbar {
        position: fixed !important;
        top: 0 !important;
        left: 248px !important;
        right: 0 !important;
        width: auto !important;
        height: 64px !important;
        min-height: 64px !important;
        padding: 12px 22px !important;
        background: #ffffff !important;
        border-bottom: 1px solid #e5e7eb !important;
        z-index: 5000 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-direction: row !important;
        gap: 16px !important;
        box-sizing: border-box !important;
    }

    body .admin-topbar-left {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
        overflow: hidden !important;
    }

    body .admin-topbar-title,
    body .admin-topbar h2 {
        display: block !important;
        margin: 0 !important;
        color: #111827 !important;
        font-size: 20px !important;
        font-weight: 900 !important;
        line-height: 1.25 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        min-width: 0 !important;
    }

    body .admin-topbar-actions {
        margin-left: auto !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-end !important;
        flex: 0 0 auto !important;
        width: auto !important;
        min-width: 0 !important;
    }

    body .admin-profile-box {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: auto !important;
        flex: 0 0 auto !important;
    }

    body .admin-profile-btn {
        margin: 0 !important;
    }

    @media (min-width: 901px) {
        body .admin-mobile-menu-btn {
            display: none !important;
        }
    }

    @media (max-width: 900px) {
        body .admin-topbar {
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            max-width: 100vw !important;
            padding: 12px 16px !important;
        }

        body .admin-mobile-menu-btn {
            display: inline-flex !important;
            flex-shrink: 0 !important;
        }

        body .admin-topbar-title,
        body .admin-topbar h2 {
            font-size: 18px !important;
            max-width: calc(100vw - 132px) !important;
        }

        body .admin-profile-dropdown {
            top: 76px !important;
            right: 16px !important;
            left: auto !important;
            width: min(405px, calc(100vw - 32px)) !important;
            max-height: calc(100vh - 92px) !important;
        }
    }

    @media (max-width: 520px) {
        body .admin-topbar {
            padding: 10px 12px !important;
            gap: 8px !important;
        }

        body .admin-topbar-title,
        body .admin-topbar h2 {
            font-size: 16px !important;
            max-width: calc(100vw - 112px) !important;
        }

        body .admin-profile-btn,
        body .admin-mobile-menu-btn {
            width: 38px !important;
            height: 38px !important;
        }

        body .admin-profile-dropdown {
            right: 10px !important;
            width: calc(100vw - 20px) !important;
        }
    }
</style>

<header class="admin-topbar">
    <div class="admin-topbar-left">
        <button
            type="button"
            class="admin-mobile-menu-btn"
            id="adminSidebarToggle"
            aria-label="Open menu"
            aria-expanded="false"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path d="M4 6h16"></path>
                <path d="M4 12h16"></path>
                <path d="M4 18h16"></path>
            </svg>
        </button>

        <h2 class="admin-topbar-title">
            <?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?>
        </h2>
    </div>

    <div class="admin-topbar-actions">
        <div class="admin-profile-box">
            <button
                type="button"
                class="admin-profile-btn"
                id="adminProfileButton"
                aria-label="Profile menu"
                aria-expanded="false"
                title="Profile"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="M20 21a8 8 0 0 0-16 0"></path>
                    <path d="M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"></path>
                </svg>
            </button>

            <div class="admin-profile-dropdown" id="adminProfileDropdown">
                <div class="admin-profile-head">
                    <div class="admin-profile-avatar" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M12 14a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path>
                            <path d="M5 21a7 7 0 0 1 14 0"></path>
                            <path d="M18 8.5V5.8a1.8 1.8 0 0 0-1.8-1.8h-8.4A1.8 1.8 0 0 0 6 5.8v2.7"></path>
                            <path d="M16.5 16.5v3"></path>
                            <path d="M15 18h3"></path>
                        </svg>
                    </div>

                    <div class="admin-profile-info">
                        <span class="admin-profile-name">
                            <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                        </span>

                        <span class="admin-profile-role">
                            <?= htmlspecialchars($visibleRoleLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>

                <?php if ($switchWorkspaceUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($switchWorkspaceUrl, ENT_QUOTES, 'UTF-8') ?>" class="admin-profile-switch">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path d="M7 7h11"></path>
                            <path d="M15 3l4 4-4 4"></path>
                            <path d="M17 17H6"></path>
                            <path d="M9 13l-4 4 4 4"></path>
                        </svg>

                        <?= htmlspecialchars($switchWorkspaceLabel, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endif; ?>

                <a href="<?= htmlspecialchars($baseUrl . '/admin/settings', ENT_QUOTES, 'UTF-8') ?>" class="admin-profile-link">
                    Settings
                </a>

                <form method="POST" action="<?= htmlspecialchars($baseUrl . '/logout', ENT_QUOTES, 'UTF-8') ?>" class="admin-profile-form">
                    <?= \App\Core\Csrf::inputField(); ?>

                    <button type="submit" class="admin-profile-logout">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>

<div class="admin-sidebar-overlay" id="adminSidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.__adminTopbarInitialized === true) {
        return;
    }

    window.__adminTopbarInitialized = true;

    const profileButton = document.getElementById('adminProfileButton');
    const profileDropdown = document.getElementById('adminProfileDropdown');

    const sidebarToggle = document.getElementById('adminSidebarToggle');
    const sidebarOverlay = document.getElementById('adminSidebarOverlay');

    function closeProfileDropdown() {
        if (!profileButton || !profileDropdown) {
            return;
        }

        profileDropdown.style.display = 'none';
        profileButton.classList.remove('is-open');
        profileButton.setAttribute('aria-expanded', 'false');
    }

    function closeSidebar() {
        document.body.classList.remove('admin-sidebar-open');

        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', 'false');
        }
    }

    if (profileButton && profileDropdown) {
        profileButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const isOpen = profileDropdown.style.display === 'block';

            closeSidebar();

            if (isOpen) {
                closeProfileDropdown();
                return;
            }

            profileDropdown.style.display = 'block';
            profileButton.classList.add('is-open');
            profileButton.setAttribute('aria-expanded', 'true');
        });
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const isOpen = document.body.classList.contains('admin-sidebar-open');

            closeProfileDropdown();

            document.body.classList.toggle('admin-sidebar-open', !isOpen);
            sidebarToggle.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            closeSidebar();
        });
    }

    document.addEventListener('click', function (event) {
        const clickedProfile =
            profileButton &&
            profileDropdown &&
            (
                profileButton.contains(event.target) ||
                profileDropdown.contains(event.target)
            );

        const clickedSidebarToggle =
            sidebarToggle &&
            sidebarToggle.contains(event.target);

        if (!clickedProfile && !clickedSidebarToggle) {
            closeProfileDropdown();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeProfileDropdown();
            closeSidebar();
        }
    });
});
</script>