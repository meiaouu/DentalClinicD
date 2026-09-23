<?php

$authUser = $authUser ?? (\App\Core\Auth::user() ?? null);
$pageTitle = $pageTitle ?? 'Staff Dashboard';

$displayName = trim((string) (
    ($authUser['first_name'] ?? '') . ' ' . ($authUser['last_name'] ?? '')
));

if ($displayName === '') {
    $displayName = 'Staff User';
}

$roleName = (string) ($authUser['role_name'] ?? 'staff');
$csrfToken = \App\Core\Csrf::token();
?>

<style>
    .staff-topbar {
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

    .staff-topbar-spacer {
        height: 64px;
        flex-shrink: 0;
    }

    .staff-topbar-left {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
    }

    .staff-mobile-menu-btn {
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

    .staff-mobile-menu-btn:hover {
        background: #f3f4f6;
    }

    .staff-mobile-menu-btn svg {
        width: 20px;
        height: 20px;
        stroke-width: 2.2;
    }

    .staff-topbar-title {
        margin: 0;
        color: #111827;
        font-size: 20px;
        font-weight: 800;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .staff-topbar-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        flex-shrink: 0;
    }

    .staff-notify-box,
    .staff-profile-box {
        position: relative;
        display: inline-flex;
        align-items: center;
    }

    .staff-notify-btn,
    .staff-profile-btn {
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

    .staff-notify-btn:hover,
    .staff-notify-btn.is-open,
    .staff-profile-btn:hover,
    .staff-profile-btn.is-open {
        background: #eff6ff;
        color: #2563eb;
        border-color: #bfdbfe;
    }

    .staff-notify-btn svg,
    .staff-profile-btn svg {
        width: 19px;
        height: 19px;
        stroke-width: 2.2;
        pointer-events: none;
    }

    .staff-notify-count {
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

    .staff-notify-dropdown,
    .staff-profile-dropdown {
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

    .staff-notify-panel-head {
        padding: 16px 16px 8px;
    }

    .staff-notify-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .staff-notify-title {
        margin: 0;
        color: #111827;
        font-size: 21px;
        line-height: 1.1;
        font-weight: 800;
    }

    .staff-notify-tabs {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 14px;
    }

    .staff-notify-tab {
        border: 0;
        background: transparent;
        color: #374151;
        border-radius: 999px;
        padding: 8px 13px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .staff-notify-tab.is-active {
        background: #eff6ff;
        color: #2563eb;
    }

    .staff-notify-section-head {
        padding: 8px 16px 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .staff-notify-section-head strong {
        color: #111827;
        font-size: 14px;
        font-weight: 800;
    }

    .staff-notify-section-head a {
        color: #2563eb;
        font-size: 13px;
        text-decoration: none;
        font-weight: 800;
    }

    .staff-notify-section-head a:hover {
        text-decoration: underline;
    }

    .staff-notify-list {
        max-height: 460px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0 8px 8px;
    }

    .staff-notify-list::-webkit-scrollbar {
        width: 8px;
    }

    .staff-notify-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .staff-notify-list::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 999px;
    }

    .staff-notify-item {
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

    .staff-notify-item:hover {
        background: #f3f4f6;
    }

    .staff-notify-item.unread {
        background: #f8fbff;
    }

    .staff-notify-avatar {
        width: 52px;
        height: 52px;
        border-radius: 999px;
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #d1d5db;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        position: relative;
        flex-shrink: 0;
    }

    .staff-notify-avatar svg {
        width: 23px;
        height: 23px;
        stroke-width: 2.2;
    }

    .staff-notify-avatar.icon-new-request,
    .staff-notify-avatar.icon-patient-request,
    .staff-notify-avatar.icon-confirmed-today,
    .staff-notify-avatar.icon-checked-in,
    .staff-notify-avatar.icon-message,
    .staff-notify-avatar.icon-availability,
    .staff-notify-avatar.icon-rescheduled,
    .staff-notify-avatar.icon-rejected,
    .staff-notify-avatar.icon-cancelled,
    .staff-notify-avatar.icon-default {
        background: #f3f4f6;
        color: #374151;
        border-color: #d1d5db;
    }

    .staff-notify-avatar-badge {
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

    .staff-notify-avatar-badge svg {
        width: 12px;
        height: 12px;
        stroke-width: 2.5;
    }

    .staff-notify-content {
        min-width: 0;
        padding-top: 2px;
    }

    .staff-notify-content strong {
        display: inline;
        color: #111827;
        font-size: 14px;
        line-height: 1.35;
        font-weight: 700;
    }

    .staff-notify-content span {
        display: inline;
        color: #374151;
        font-size: 14px;
        line-height: 1.35;
        font-weight: 500;
    }

    .staff-notify-content small {
        display: block;
        margin-top: 4px;
        color: #2563eb;
        font-size: 12px;
        font-weight: 900;
    }

    .staff-notify-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: #2563eb;
        margin-top: 20px;
        display: none;
    }

    .staff-notify-item.unread .staff-notify-dot {
        display: block;
    }

    .staff-notify-empty {
        margin: 8px;
        padding: 18px;
        background: #f9fafb;
        border-radius: 8px;
        color: #64748b;
        font-size: 14px;
        line-height: 1.5;
        text-align: center;
    }

    .staff-notify-footer {
        padding: 10px 16px 16px;
    }

    .staff-notify-footer a {
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

    .staff-notify-footer a:hover {
        background: #d1d5db;
    }

    .staff-profile-head {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px;
        border-bottom: 1px solid #e5e7eb;
        background: #f9fafb;
    }

    .staff-profile-avatar {
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

    .staff-profile-avatar svg {
        width: 30px;
        height: 30px;
        stroke-width: 1.9;
    }

    .staff-profile-info {
        min-width: 0;
        flex: 1;
    }

    .staff-profile-name {
        display: block;
        color: #111827;
        font-size: 18px;
        font-weight: 900;
        line-height: 1.3;
        word-break: break-word;
    }

    .staff-profile-role {
        display: block;
        margin-top: 4px;
        color: #6b7280;
        font-size: 14px;
        font-weight: 700;
    }

    .staff-profile-link,
    .staff-profile-logout {
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

    .staff-profile-link:hover,
    .staff-profile-logout:hover {
        background: #f3f4f6;
    }

    .staff-profile-logout {
        color: #dc2626;
    }

    .staff-profile-form {
        margin: 0;
    }

    .staff-sidebar-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.42);
        z-index: 3999;
        display: none;
    }

    body.staff-sidebar-open .staff-sidebar-overlay {
        display: block;
    }

    @media (max-width: 900px) {
        .staff-topbar {
            left: 0;
            right: 0;
            padding: 12px 16px;
        }

        .staff-mobile-menu-btn {
            display: inline-flex;
        }

        .staff-topbar-title {
            font-size: 18px;
        }

        .staff-sidebar {
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

        body.staff-sidebar-open .staff-sidebar {
            transform: translateX(0);
        }

        .staff-notify-dropdown,
        .staff-profile-dropdown {
            top: 76px;
            right: 16px;
            left: auto;
            width: min(405px, calc(100vw - 32px));
            max-height: calc(100vh - 92px);
        }
    }

    @media (max-width: 520px) {
        .staff-topbar-title {
            max-width: 170px;
            white-space: normal;
            overflow-wrap: anywhere;
            line-height: 1.2;
        }

        .staff-topbar-actions {
            gap: 8px;
        }

        .staff-notify-btn,
        .staff-profile-btn,
        .staff-mobile-menu-btn {
            width: 38px;
            height: 38px;
        }

        .staff-profile-avatar {
            width: 50px;
            height: 50px;
            min-width: 50px;
        }

        .staff-profile-avatar svg {
            width: 26px;
            height: 26px;
        }

        .staff-profile-name {
            font-size: 16px;
        }

        .staff-profile-role {
            font-size: 13px;
        }
    }
</style>

<header class="staff-topbar">
    <div class="staff-topbar-left">
        <button
            type="button"
            class="staff-mobile-menu-btn"
            id="staffSidebarToggle"
            aria-label="Open menu"
            aria-expanded="false"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                <path d="M4 6h16"></path>
                <path d="M4 12h16"></path>
                <path d="M4 18h16"></path>
            </svg>
        </button>

        <h2 class="staff-topbar-title">
            <?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?>
        </h2>
    </div>

    <div class="staff-topbar-actions">
        <div
            class="staff-notify-box"
            data-latest-url="/DentalClinic/public/staff/notifications/latest"
            data-read-url="/DentalClinic/public/staff/notifications/read"
            data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
        >
            <button
                type="button"
                class="staff-notify-btn"
                id="staffNotifyButton"
                aria-label="Notifications"
                aria-expanded="false"
                title="Notifications"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="M15 17H9"></path>
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 8h18s-3-1-3-8"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>

                <span class="staff-notify-count" id="staffNotifyCount">0</span>
            </button>

            <div class="staff-notify-dropdown" id="staffNotifyDropdown">
                <div class="staff-notify-panel-head">
                    <div class="staff-notify-title-row">
                        <h3 class="staff-notify-title">Notifications</h3>
                    </div>

                    <div class="staff-notify-tabs">
                        <button type="button" class="staff-notify-tab is-active" id="staffNotifyTabAll" data-filter="all">
                            All
                        </button>

                        <button type="button" class="staff-notify-tab" id="staffNotifyTabUnread" data-filter="unread">
                            Unread
                        </button>
                    </div>
                </div>

                <div class="staff-notify-section-head">
                    <strong>Earlier</strong>
                    <a href="/DentalClinic/public/staff/notifications">See all</a>
                </div>

                <div class="staff-notify-list" id="staffNotifyList">
                    <div class="staff-notify-empty">Loading notifications...</div>
                </div>

                <div class="staff-notify-footer" id="staffNotifyFooter">
                    <a href="/DentalClinic/public/staff/notifications">See previous notifications</a>
                </div>
            </div>
        </div>

        <div class="staff-profile-box">
            <button
                type="button"
                class="staff-profile-btn"
                id="staffProfileButton"
                aria-label="Profile menu"
                aria-expanded="false"
                title="Profile"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path d="M20 21a8 8 0 0 0-16 0"></path>
                    <path d="M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"></path>
                </svg>
            </button>

            <div class="staff-profile-dropdown" id="staffProfileDropdown">
                <div class="staff-profile-head">
                    <div class="staff-profile-avatar" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M12 14a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path>
                            <path d="M5 21a7 7 0 0 1 14 0"></path>
                            <path d="M16 11h4"></path>
                            <path d="M18 9v4"></path>
                        </svg>
                    </div>

                    <div class="staff-profile-info">
                        <span class="staff-profile-name">
                            <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                        </span>

                        <span class="staff-profile-role">
                            <?= htmlspecialchars(ucfirst($roleName), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                </div>

                <a href="/DentalClinic/public/staff/account-settings" class="staff-profile-link">
                    Account Settings
                </a>

                <form method="POST" action="/DentalClinic/public/logout" class="logout-form">
    <?= \App\Core\Csrf::inputField(); ?>
    <button type="submit" class="logout-button">Logout</button>
</form>
            </div>
        </div>
    </div>
</header>

<div class="staff-topbar-spacer"></div>
<div class="staff-sidebar-overlay" id="staffSidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.__staffTopbarInitialized === true) {
        return;
    }

    window.__staffTopbarInitialized = true;

    const profileButton = document.getElementById('staffProfileButton');
    const profileDropdown = document.getElementById('staffProfileDropdown');

    const notificationButton = document.getElementById('staffNotifyButton');
    const notificationDropdown = document.getElementById('staffNotifyDropdown');

    const sidebarToggle = document.getElementById('staffSidebarToggle');
    const sidebarOverlay = document.getElementById('staffSidebarOverlay');

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
        document.body.classList.remove('staff-sidebar-open');

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

            const isOpen = document.body.classList.contains('staff-sidebar-open');

            closeAllTopbarMenus();

            document.body.classList.toggle('staff-sidebar-open', !isOpen);
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

<script src="/DentalClinic/public/assets/js/staff-notifications.js"></script>