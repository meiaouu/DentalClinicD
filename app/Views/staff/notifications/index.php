<?php

$pageTitle = 'Notifications';
$notifications = isset($notifications) && is_array($notifications) ? $notifications : [];
$unreadCount = (int) ($unread_count ?? 0);

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('notificationTimeLabel')) {
    function notificationTimeLabel(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || strtotime($value) === false) {
            return 'No date';
        }

        return date('M d, Y h:i A', strtotime($value));
    }
}

ob_start();
?>

<style>
.notifications-page {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    padding: 16px;
    font-family: Arial, sans-serif;
}

.notifications-header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 12px;
    margin-bottom: 12px;
}

.notifications-title {
    margin: 0;
    color: #111827;
    font-size: 20px;
    font-weight: 900;
}

.notifications-count {
    color: #2563eb;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    padding: 7px 10px;
    font-size: 12px;
    font-weight: 900;
}

.notifications-list {
    display: grid;
    gap: 8px;
}

.notification-item {
    display: block;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    padding: 12px;
    color: #111827;
    text-decoration: none;
}

.notification-item.unread {
    background: #f8fbff;
    border-color: #bfdbfe;
}

.notification-title {
    font-size: 14px;
    font-weight: 900;
    margin-bottom: 4px;
}

.notification-message {
    color: #374151;
    font-size: 13px;
    line-height: 1.5;
}

.notification-time {
    display: block;
    margin-top: 6px;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
}

.empty-state {
    border: 1px dashed #cbd5e1;
    padding: 16px;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
}
</style>

<div class="notifications-page">
    <div class="notifications-header">
        <h1 class="notifications-title">Notifications</h1>
        <div class="notifications-count">
            <?= $unreadCount ?> unread
        </div>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            No notifications found.
        </div>
    <?php else: ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $item): ?>
                <?php
                    $isRead = (int) ($item['is_read'] ?? 0) === 1;
                    $link = trim((string) ($item['link_url'] ?? $item['target_url'] ?? '#'));

                    if ($link === '' || !str_starts_with($link, '/DentalClinic/public/')) {
                        $link = '#';
                    }
                ?>

                <a class="notification-item <?= $isRead ? '' : 'unread' ?>" href="<?= e($link) ?>">
                    <div class="notification-title">
                        <?= e((string) ($item['title'] ?? 'Notification')) ?>
                    </div>

                    <div class="notification-message">
                        <?= e((string) ($item['message'] ?? '')) ?>
                    </div>

                    <small class="notification-time">
                        <?= e(notificationTimeLabel((string) ($item['created_at'] ?? ''))) ?>
                    </small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$staffContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';