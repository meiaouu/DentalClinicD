<?php use App\Core\Csrf; ?>

<link rel="stylesheet" href="/DentalClinic/public/assets/css/dentist-notifications.css">

<meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

<div class="dentist-notification-wrapper">
    <button type="button" id="notificationBell" class="notification-bell">
        Notifications
        <span id="notificationCount" class="notification-count">0</span>
    </button>

    <div id="notificationDropdown" class="notification-dropdown">
        <div class="notification-header">Notifications</div>
        <div id="notificationList" class="notification-list">
            <div class="notification-empty">No new notifications.</div>
        </div>
    </div>
</div>

<script src="/DentalClinic/public/assets/js/dentist-notifications.js"></script>