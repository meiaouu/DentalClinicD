<?php
$authUser = $authUser ?? (\App\Core\Auth::user() ?? null);
$pageTitle = $pageTitle ?? 'Dentist Dashboard';

$displayName = trim((string) (
    ($authUser['first_name'] ?? '') . ' ' . ($authUser['last_name'] ?? '')
));

if ($displayName === '') {
    $displayName = 'Dentist User';
}

$roleName = (string) ($authUser['role_name'] ?? 'dentist');
$csrfToken = \App\Core\Csrf::token();

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$baseUrl = '/DentalClinic/public';

$canAccessOwnerAdmin = false;
$canAccessDentist = false;

try {
    $canAccessOwnerAdmin =
        method_exists(\App\Core\Auth::class, 'hasAnyRole') &&
        \App\Core\Auth::hasAnyRole(['owner', 'admin']);

    $canAccessDentist =
        method_exists(\App\Core\Auth::class, 'hasRole') &&
        \App\Core\Auth::hasRole('dentist');
} catch (\Throwable $e) {
    $canAccessOwnerAdmin = false;
    $canAccessDentist = false;
}

$isAdminArea = str_starts_with($currentPath, $baseUrl . '/admin');
$isDentistArea = str_starts_with($currentPath, $baseUrl . '/dentist');

$switchWorkspaceUrl = '';
$switchWorkspaceLabel = '';

if ($isDentistArea && $canAccessOwnerAdmin && (!$canAccessDentist || \App\Core\Auth::isAdminDentistAccount())) {
    $switchWorkspaceUrl = $baseUrl . '/admin/dashboard';
    $switchWorkspaceLabel = 'Switch to Owner/Admin Dashboard';
} elseif ($isAdminArea && $canAccessDentist && !$canAccessOwnerAdmin) {
    $switchWorkspaceUrl = $baseUrl . '/dentist/dashboard';
    $switchWorkspaceLabel = 'Switch to Dentist Dashboard';
}

$visibleRoleLabel = 'Dentist';

if ($isAdminArea && $canAccessOwnerAdmin) {
    $visibleRoleLabel = 'Owner/Admin';
} elseif ($canAccessDentist) {
    $visibleRoleLabel = 'Dentist';
} elseif ($canAccessOwnerAdmin) {
    $visibleRoleLabel = 'Owner/Admin';
}
?>




<style>
    .dentist-topbar {
        background: #ffffff;
        border-bottom: 1px solid #e5e7eb;
        padding: 12px 22px;
        min-height: 64px;
        position: fixed;
        top: 0;
        left: 248px;
        right: 0;
        z-index: 5000;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .dentist-topbar-left {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
    }

    .dentist-mobile-menu-btn {
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
    }

    .dentist-mobile-menu-btn:hover {
        background: #f3f4f6;
    }

    .dentist-mobile-menu-btn svg {
        width: 20px;
        height: 20px;
        stroke-width: 2.2;
    }

    .dentist-topbar-title {
        margin: 0;
        color: #111827;
        font-size: 20px;
        font-weight: 900;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .dentist-topbar-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        flex-shrink: 0;
    }

    .dentist-notify-box,
    .dentist-profile-box {
        position: relative;
        display: inline-flex;
        align-items: center;
    }

    .dentist-notify-btn,
    .dentist-profile-btn {
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
        transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
    }

    .dentist-notify-btn:hover,
    .dentist-notify-btn.is-open,
    .dentist-profile-btn:hover,
    .dentist-profile-btn.is-open {
        background: #eff6ff;
        color: #2563eb;
        border-color: #bfdbfe;
    }

    .dentist-notify-btn svg,
    .dentist-profile-btn svg {
        width: 19px;
        height: 19px;
        stroke-width: 2.2;
        pointer-events: none;
    }

    .dentist-notify-count {
        position: absolute;
        top: -5px;
        right: -5px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        background: #dc2626;
        color: #ffffff;
        border: 2px solid #ffffff;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 900;
        line-height: 14px;
        text-align: center;
        display: none;
    }

    .dentist-notify-dropdown,
.dentist-profile-dropdown {
    position: fixed;
    top: 76px;
    right: 22px;
    width: 405px;
    max-height: calc(100vh - 92px);
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 0 14px 35px rgba(15, 23, 42, 0.18);
    z-index: 6000;
    overflow: hidden;
    display: none;
}

    .dentist-notify-panel-head {
        padding: 16px 16px 8px;
    }

    .dentist-notify-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .dentist-notify-title {
        margin: 0;
        color: #111827;
        font-size: 21px;
        line-height: 1.1;
        font-weight: 800;
    }

    .dentist-notify-tabs {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 14px;
    }

    .dentist-notify-tab {
        border: 0;
        background: transparent;
        color: #374151;
        border-radius: 999px;
        padding: 8px 13px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .dentist-notify-tab.is-active {
        background: #eff6ff;
        color: #2563eb;
    }

    .dentist-notify-section-head {
        padding: 8px 16px 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .dentist-notify-section-head strong {
        color: #111827;
        font-size: 14px;
        font-weight: 800;
    }

    .dentist-notify-section-head a {
        color: #2563eb;
        font-size: 13px;
        text-decoration: none;
        font-weight: 800;
    }

    .dentist-notify-section-head a:hover {
        text-decoration: underline;
    }

    .dentist-notify-list {
        max-height: 460px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0 8px 8px;
    }

    .dentist-notify-list::-webkit-scrollbar {
        width: 8px;
    }

    .dentist-notify-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .dentist-notify-list::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 999px;
    }

    .dentist-notify-item {
        width: 100%;
        border: 0;
        background: #ffffff;
        display: grid;
        grid-template-columns: 52px minmax(0, 1fr) 12px;
        gap: 10px;
        align-items: start;
        text-align: left;
        padding: 10px 8px;
        border-radius: 8px;
        cursor: pointer;
    }

    .dentist-notify-item:hover {
        background: #f3f4f6;
    }

    .dentist-notify-item.unread {
        background: #f8fbff;
    }

    .dentist-profile-switch {
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
}

.dentist-profile-switch:hover {
    background: #d1fae5;
    color: #065f46;
}

.dentist-profile-switch svg {
    width: 18px;
    height: 18px;
    stroke-width: 2.2;
    flex-shrink: 0;
}

.dentist-notify-avatar {
    width: 56px;
    height: 56px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    flex-shrink: 0;
    border: 1px solid #e5e7eb;
}

.dentist-notify-avatar svg {
    width: 24px;
    height: 24px;
    stroke-width: 2.2;
}

.dentist-notify-avatar.icon-today {
    background: #f3f4f6;
    color: #374151;
    border-color: #d1d5db;
}

.dentist-notify-avatar.icon-confirmed {
    background: #f3f4f6;
    color: #374151;
    border-color: #d1d5db;
}

.dentist-notify-avatar.icon-cancelled {
    background: #eaeaea;
    color: #5c5c5c;
    border-color: #acacac;
}

.dentist-notify-avatar.icon-rescheduled {
    background: #f3f4f6;
    color: #374151;
    border-color: #d1d5db;
}

.dentist-notify-avatar.icon-rejected {
    background: #f3f4f6;
    color: #374151;
    border-color: #d1d5db;
}

.dentist-notify-avatar.icon-default {
    background: #f3f4f6;
    color: #374151;
    border-color: #d1d5db;
}

    .dentist-notify-avatar-badge {
        position: absolute;
        right: -1px;
        bottom: -1px;
        width: 22px;
        height: 22px;
        border-radius: 999px;
        border: 2px solid #ffffff;
        background: #2563eb;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .dentist-notify-avatar-badge svg {
        width: 12px;
        height: 12px;
        stroke-width: 2.5;
    }

    .dentist-notify-content {
        min-width: 0;
        padding-top: 2px;
    }

    .dentist-notify-content strong {
        display: inline;
        color: #111827;
        font-size: 14px;
        line-height: 1.35;
        font-weight: 700;
    }

    .dentist-notify-content span {
        display: inline;
        color: #374151;
        font-size: 14px;
        line-height: 1.35;
        font-weight: 500;
    }

    .dentist-notify-content small {
        display: block;
        margin-top: 4px;
        color: #2563eb;
        font-size: 12px;
        font-weight: 900;
    }

    .dentist-notify-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: #2563eb;
        margin-top: 20px;
        display: none;
    }

    .dentist-notify-item.unread .dentist-notify-dot {
        display: block;
    }

    .dentist-notify-empty {
        margin: 8px;
        padding: 18px;
        background: #f9fafb;
        border-radius: 8px;
        color: #64748b;
        font-size: 14px;
        line-height: 1.5;
        text-align: center;
    }

    .dentist-notify-footer {
        padding: 10px 16px 16px;
    }

    .dentist-notify-footer a {
        display: block;
        width: 100%;
        padding: 11px 12px;
        border-radius: 8px;
        background: #e5e7eb;
        color: #111827;
        text-align: center;
        text-decoration: none;
        font-size: 14px;
        font-weight: 700;
    }

    .dentist-notify-footer a:hover {
        background: #d1d5db;
    }

   /* PROFILE DROPDOWN */
.dentist-profile-dropdown {
    width: 405px;
}

.dentist-profile-head {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.dentist-profile-avatar {
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

.dentist-profile-avatar svg {
    width: 30px;
    height: 30px;
    stroke-width: 1.9;
}

.dentist-profile-info {
    min-width: 0;
    flex: 1;
}

.dentist-profile-name {
    display: block;
    color: #111827;
    font-size: 18px;
    font-weight: 900;
    line-height: 1.3;
    word-break: break-word;
}

.dentist-profile-role {
    display: block;
    margin-top: 4px;
    color: #6b7280;
    font-size: 14px;
    font-weight: 700;
}

.dentist-profile-link,
.dentist-profile-logout {
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
}

.dentist-profile-link:hover,
.dentist-profile-logout:hover {
    background: #f3f4f6;
}

.dentist-profile-logout {
    color: #dc2626;
}

.dentist-profile-form {
    margin: 0;
}

    .dentist-sidebar-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.42);
        z-index: 3999;
        display: none;
    }

    body.dentist-sidebar-open .dentist-sidebar-overlay {
        display: block;
    }

    @media (max-width: 900px) {
        .dentist-topbar {
            left: 0;
            right: 0;
            padding: 12px 16px;
        }

        .dentist-mobile-menu-btn {
            display: inline-flex;
        }

        .dentist-topbar-title {
            font-size: 18px;
        }

        .dentist-sidebar {
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

        body.dentist-sidebar-open .dentist-sidebar {
            transform: translateX(0);
        }

        .dentist-notify-dropdown,
    .dentist-profile-dropdown {
        top: 76px;
        right: 16px;
        left: auto;
        width: min(405px, calc(100vw - 32px));
        max-height: calc(100vh - 92px);
    }
    }

    @media (max-width: 520px) {
        .dentist-topbar-title {
            max-width: 170px;
            white-space: normal;
            overflow-wrap: anywhere;
            line-height: 1.2;
        }

        .dentist-topbar-actions {
            gap: 8px;
        }

        .dentist-notify-btn,
        .dentist-profile-btn,
        .dentist-mobile-menu-btn {
            width: 38px;
            height: 38px;
        }

        .dentist-profile-avatar {
            width: 50px;
            height: 50px;
            min-width: 50px;
        }

        .dentist-profile-avatar svg {
            width: 26px;
            height: 26px;
        }

        .dentist-profile-name {
            font-size: 16px;
        }

        .dentist-profile-role {
            font-size: 13px;
        }
    }
</style>

<header class="dentist-topbar">
    <div class="dentist-topbar-left">
        <button
            type="button"
            class="dentist-mobile-menu-btn"
            id="dentistSidebarToggle"
            aria-label="Open menu"
            aria-expanded="false"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path d="M4 6h16"></path>
                <path d="M4 12h16"></path>
                <path d="M4 18h16"></path>
            </svg>
        </button>

        <h2 class="dentist-topbar-title">
            <?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?>
        </h2>
    </div>

    <div class="dentist-topbar-actions">
        <div
            class="dentist-notify-box"
            data-index-url="/DentalClinic/public/dentist/notifications"
            data-mark-one-read-url="/DentalClinic/public/dentist/notifications/mark-one-read"
            data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
        >
            <button
                type="button"
                id="dentistNotificationButton"
                class="dentist-notify-btn"
                aria-label="Notifications"
                aria-expanded="false"
                title="Notifications"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="M15 17H9"></path>
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span class="dentist-notify-count" id="dentistNotificationCount">0</span>
            </button>

            <div class="dentist-notify-dropdown" id="dentistNotificationList">
                <div class="dentist-notify-panel-head">
                    <div class="dentist-notify-title-row">
                        <h3 class="dentist-notify-title">Notifications</h3>
                    </div>

                    <div class="dentist-notify-tabs">
                        <button type="button" class="dentist-notify-tab is-active" id="dentistNotifyTabAll" data-filter="all">
                            All
                        </button>
                        <button type="button" class="dentist-notify-tab" id="dentistNotifyTabUnread" data-filter="unread">
                            Unread
                        </button>
                    </div>
                </div>

                <div class="dentist-notify-section-head">
                    <strong>Earlier</strong>
                    <a href="/DentalClinic/public/dentist/dashboard">See all</a>
                </div>

                <div class="dentist-notify-list" id="dentistNotificationBody">
                    <div class="dentist-notify-empty">Loading notifications...</div>
                </div>

                <div class="dentist-notify-footer" id="dentistNotifyFooter">
                    <a href="/DentalClinic/public/dentist/dashboard">See previous notifications</a>
                </div>
            </div>
        </div>

        <div class="dentist-profile-box">
            <button
                type="button"
                class="dentist-profile-btn"
                id="dentistProfileButton"
                aria-label="Profile menu"
                aria-expanded="false"
                title="Profile"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="M20 21a8 8 0 0 0-16 0"></path>
                    <path d="M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"></path>
                </svg>
            </button>

           <div class="dentist-profile-dropdown" id="dentistProfileDropdown">
    <div class="dentist-profile-head">
        <div class="dentist-profile-avatar" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M12 14a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path>
                <path d="M5 21a7 7 0 0 1 14 0"></path>
                <path d="M18 8.5V5.8a1.8 1.8 0 0 0-1.8-1.8h-8.4A1.8 1.8 0 0 0 6 5.8v2.7"></path>
                <path d="M16.5 16.5v3"></path>
                <path d="M15 18h3"></path>
            </svg>
        </div>

        <div class="dentist-profile-info">
            <span class="dentist-profile-name">
                <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
            </span>

            <span class="dentist-profile-role">
    <?= htmlspecialchars($visibleRoleLabel, ENT_QUOTES, 'UTF-8') ?>
</span>
        </div>
    </div>

<?php if ($switchWorkspaceUrl !== ''): ?>
    <a href="<?= htmlspecialchars($switchWorkspaceUrl, ENT_QUOTES, 'UTF-8') ?>" class="dentist-profile-switch">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <path d="M7 7h11"></path>
            <path d="M15 3l4 4-4 4"></path>
            <path d="M17 17H6"></path>
            <path d="M9 13l-4 4 4 4"></path>
        </svg>

        <?= htmlspecialchars($switchWorkspaceLabel, ENT_QUOTES, 'UTF-8') ?>
    </a>
<?php endif; ?>

<a href="/DentalClinic/public/settings" class="dentist-profile-link">
    Settings
</a>

    <form method="POST" action="/DentalClinic/public/logout" class="logout-form">
    <?= \App\Core\Csrf::inputField(); ?>
    <button type="submit" class="logout-button">Logout</button>
</form>
</div>



        </div>




        
    </div>
</header>

<div class="dentist-sidebar-overlay" id="dentistSidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.__dentistTopbarInitialized === true) {
        return;
    }

    window.__dentistTopbarInitialized = true;

    const profileButton = document.getElementById('dentistProfileButton');
    const profileDropdown = document.getElementById('dentistProfileDropdown');

    const notificationButton = document.getElementById('dentistNotificationButton');
    const notificationDropdown = document.getElementById('dentistNotificationList');

    const sidebarToggle = document.getElementById('dentistSidebarToggle');
    const sidebarOverlay = document.getElementById('dentistSidebarOverlay');

    function closeProfileDropdown() {
        if (!profileButton || !profileDropdown) {
            return;
        }

        profileDropdown.style.display = 'none';
        profileButton.classList.remove('is-open');
        profileButton.setAttribute('aria-expanded', 'false');
    }

    function closeNotificationDropdown() {
        if (!notificationButton || !notificationDropdown) {
            return;
        }

        notificationDropdown.style.display = 'none';
        notificationButton.classList.remove('is-open');
        notificationButton.setAttribute('aria-expanded', 'false');
    }

    function closeSidebar() {
        document.body.classList.remove('dentist-sidebar-open');

        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', 'false');
        }
    }

    function closeAllTopbarMenus() {
        closeProfileDropdown();
        closeNotificationDropdown();
    }

    if (profileButton && profileDropdown) {
        profileButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const isOpen = profileDropdown.style.display === 'block';

            closeNotificationDropdown();
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

    if (notificationButton) {
        notificationButton.addEventListener('click', function () {
            closeProfileDropdown();
            closeSidebar();
        });
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const isOpen = document.body.classList.contains('dentist-sidebar-open');

            closeAllTopbarMenus();

            document.body.classList.toggle('dentist-sidebar-open', !isOpen);
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

        const clickedNotification =
            notificationButton &&
            notificationDropdown &&
            (
                notificationButton.contains(event.target) ||
                notificationDropdown.contains(event.target)
            );

        const clickedSidebarToggle =
            sidebarToggle &&
            sidebarToggle.contains(event.target);

        if (!clickedProfile && !clickedNotification && !clickedSidebarToggle) {
            closeAllTopbarMenus();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeAllTopbarMenus();
            closeSidebar();
        }
    });
});
</script>

<script src="/DentalClinic/public/assets/js/dentist-notification.js"></script>