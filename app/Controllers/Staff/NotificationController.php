<?php

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use PDO;
use Throwable;

class NotificationController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    

    public function latest(): void
    {
        Auth::requireRole('staff');

        header('Content-Type: application/json; charset=utf-8');

        try {
            if (!$this->tableExists('notifications')) {
                echo json_encode([
                    'notifications' => [],
                    'unread_count' => 0,
                ]);
                return;
            }

            $user = Auth::user();
            $userId = (int) ($user['user_id'] ?? 0);
            $roleName = strtolower(trim((string) ($user['role_name'] ?? 'staff')));

            if ($userId <= 0) {
                http_response_code(403);
                echo json_encode([
                    'notifications' => [],
                    'unread_count' => 0,
                    'message' => 'Invalid staff account.',
                ]);
                return;
            }

            $notifications = $this->getNotificationsForUser($userId, $roleName, 12);
            $unreadCount = $this->countUnreadForUser($userId, $roleName);

            echo json_encode([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        } catch (Throwable $e) {
            http_response_code(500);

            echo json_encode([
                'notifications' => [],
                'unread_count' => 0,
                'message' => 'Unable to load notifications.',
            ]);
        }
    }

    public function read(): void
    {
        Auth::requireRole('staff');

        header('Content-Type: application/json; charset=utf-8');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);

            echo json_encode([
                'success' => false,
                'message' => 'Invalid CSRF token.',
            ]);
            return;
        }

        try {
            if (!$this->tableExists('notifications')) {
                echo json_encode([
                    'success' => false,
                    'unread_count' => 0,
                    'message' => 'Notifications table not found.',
                ]);
                return;
            }

            $notificationId = (int) ($_POST['notification_id'] ?? 0);

            if ($notificationId <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid notification.',
                ]);
                return;
            }

            $user = Auth::user();
            $userId = (int) ($user['user_id'] ?? 0);
            $roleName = strtolower(trim((string) ($user['role_name'] ?? 'staff')));

            $notification = $this->findNotificationForUser($notificationId, $userId, $roleName);

            if (!$notification) {
                http_response_code(404);

                echo json_encode([
                    'success' => false,
                    'message' => 'Notification not found.',
                ]);
                return;
            }

            $this->markAsRead($notificationId);

            echo json_encode([
                'success' => true,
                'unread_count' => $this->countUnreadForUser($userId, $roleName),
                'link_url' => $this->notificationLink($notification),
            ]);
        } catch (Throwable $e) {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Unable to update notification.',
            ]);
        }
    }

    public function index(): void
    {
        Auth::requireRole('staff');

        if (!$this->tableExists('notifications')) {
            View::render('staff.notifications.index', [
                'notifications' => [],
                'unread_count' => 0,
            ]);
            return;
        }

        $user = Auth::user();
        $userId = (int) ($user['user_id'] ?? 0);
        $roleName = strtolower(trim((string) ($user['role_name'] ?? 'staff')));

        View::render('staff.notifications.index', [
            'notifications' => $this->getNotificationsForUser($userId, $roleName, 50),
            'unread_count' => $this->countUnreadForUser($userId, $roleName),
        ]);
    }

    private function getNotificationsForUser(int $userId, string $roleName, int $limit = 12): array
    {
        $columns = $this->getTableColumns('notifications');
        $primaryKey = $this->notificationPrimaryKey();

        if ($primaryKey === null) {
            return [];
        }

        $selects = [
            "`{$primaryKey}` AS notification_id",
            $this->selectColumn($columns, ['type', 'notification_type'], 'type'),
            $this->selectColumn($columns, ['title'], 'title'),
            $this->selectColumn($columns, ['message'], 'message'),
            $this->selectColumn($columns, ['link_url', 'target_url'], 'link_url'),
            $this->selectColumn($columns, ['target_url', 'link_url'], 'target_url'),
            $this->selectColumn($columns, ['created_at'], 'created_at'),
            $this->selectReadColumn($columns),
        ];

        $where = $this->notificationRecipientWhere($columns, $userId, $roleName);

        if ($where['sql'] === '') {
            return [];
        }

        $sql = "
            SELECT
                " . implode(",\n                ", $selects) . "
            FROM notifications
            WHERE {$where['sql']}
            ORDER BY " . (isset($columns['created_at']) ? 'created_at DESC,' : '') . " `{$primaryKey}` DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($where['params'] as $param => $value) {
            $stmt->bindValue($param, $value);
        }

        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function countUnreadForUser(int $userId, string $roleName): int
    {
        $columns = $this->getTableColumns('notifications');
        $where = $this->notificationRecipientWhere($columns, $userId, $roleName);

        if ($where['sql'] === '') {
            return 0;
        }

        $unreadSql = $this->unreadWhereSql($columns);

        if ($unreadSql === '') {
            return 0;
        }

        $sql = "
            SELECT COUNT(*)
            FROM notifications
            WHERE {$where['sql']}
              AND {$unreadSql}
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($where['params'] as $param => $value) {
            $stmt->bindValue($param, $value);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function findNotificationForUser(int $notificationId, int $userId, string $roleName): ?array
    {
        $columns = $this->getTableColumns('notifications');
        $primaryKey = $this->notificationPrimaryKey();

        if ($primaryKey === null) {
            return null;
        }

        $where = $this->notificationRecipientWhere($columns, $userId, $roleName);

        if ($where['sql'] === '') {
            return null;
        }

        $sql = "
            SELECT *
            FROM notifications
            WHERE `{$primaryKey}` = :notification_id
              AND {$where['sql']}
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':notification_id', $notificationId, PDO::PARAM_INT);

        foreach ($where['params'] as $param => $value) {
            $stmt->bindValue($param, $value);
        }

        $stmt->execute();

        $notification = $stmt->fetch(PDO::FETCH_ASSOC);

        return $notification ?: null;
    }

    private function markAsRead(int $notificationId): void
    {
        $columns = $this->getTableColumns('notifications');
        $primaryKey = $this->notificationPrimaryKey();

        if ($primaryKey === null) {
            return;
        }

        $sets = [];

        if (isset($columns['is_read'])) {
            $sets[] = 'is_read = 1';
        }

        if (isset($columns['read_at'])) {
            $sets[] = 'read_at = COALESCE(read_at, NOW())';
        }

        if (isset($columns['updated_at'])) {
            $sets[] = 'updated_at = NOW()';
        }

        if (empty($sets)) {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE notifications
            SET " . implode(', ', $sets) . "
            WHERE `{$primaryKey}` = :notification_id
            LIMIT 1
        ");

        $stmt->execute([
            ':notification_id' => $notificationId,
        ]);
    }

    private function notificationRecipientWhere(array $columns, int $userId, string $roleName): array
    {
        $parts = [];
        $params = [];

        if (isset($columns['user_id'])) {
            $parts[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        if (isset($columns['recipient_user_id'])) {
            $parts[] = 'recipient_user_id = :recipient_user_id';
            $params[':recipient_user_id'] = $userId;
        }

        if (isset($columns['recipient_role'])) {
            $parts[] = 'LOWER(recipient_role) = :recipient_role';
            $params[':recipient_role'] = $roleName !== '' ? $roleName : 'staff';
        }

        if (isset($columns['target_role'])) {
            $parts[] = 'LOWER(target_role) = :target_role';
            $params[':target_role'] = $roleName !== '' ? $roleName : 'staff';
        }

        if (empty($parts)) {
            return [
                'sql' => '',
                'params' => [],
            ];
        }

        return [
            'sql' => '(' . implode(' OR ', $parts) . ')',
            'params' => $params,
        ];
    }

    private function unreadWhereSql(array $columns): string
    {
        if (isset($columns['is_read'])) {
            return '(is_read = 0 OR is_read IS NULL)';
        }

        if (isset($columns['read_at'])) {
            return 'read_at IS NULL';
        }

        return '';
    }

    private function selectColumn(array $columns, array $candidates, string $alias): string
    {
        foreach ($candidates as $candidate) {
            if (isset($columns[$candidate])) {
                return "`{$candidate}` AS `{$alias}`";
            }
        }

        return "NULL AS `{$alias}`";
    }

    private function selectReadColumn(array $columns): string
    {
        if (isset($columns['is_read'])) {
            return 'COALESCE(is_read, 0) AS is_read';
        }

        if (isset($columns['read_at'])) {
            return 'CASE WHEN read_at IS NULL THEN 0 ELSE 1 END AS is_read';
        }

        return '0 AS is_read';
    }

    private function notificationPrimaryKey(): ?string
    {
        $columns = $this->getTableColumns('notifications');

        foreach (['notification_id', 'id'] as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    private function notificationLink(array $notification): string
    {
        $link = trim((string) (
            $notification['link_url']
            ?? $notification['target_url']
            ?? ''
        ));

        if ($link === '') {
            return '';
        }

        if (!str_starts_with($link, '/DentalClinic/public/')) {
            return '';
        }

        return $link;
    }

    private function tableExists(string $tableName): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ");

            $stmt->execute([
                ':table_name' => $tableName,
            ]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function getTableColumns(string $tableName): array
    {
        static $cache = [];

        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        $cache[$tableName] = [];

        try {
            if (!$this->tableExists($tableName)) {
                return $cache[$tableName];
            }

            $stmt = $this->db->prepare("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ");

            $stmt->execute([
                ':table_name' => $tableName,
            ]);

            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            foreach ($columns as $column) {
                $cache[$tableName][strtolower((string) $column)] = true;
            }
        } catch (Throwable $e) {
            $cache[$tableName] = [];
        }

        return $cache[$tableName];
    }
}