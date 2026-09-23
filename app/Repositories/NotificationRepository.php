<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class NotificationRepository
{
    private PDO $db;

    private array $columnCache = [];
    private array $tableCache = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $recipientUserId = !empty($data['recipient_user_id'])
            ? (int) $data['recipient_user_id']
            : (!empty($data['user_id']) ? (int) $data['user_id'] : null);

        $recipientRole = !empty($data['recipient_role'])
            ? (string) $data['recipient_role']
            : null;

        $linkUrl = $this->safeInternalUrl((string) ($data['link_url'] ?? $data['target_url'] ?? ''));

        $relatedTable = !empty($data['related_table'])
            ? (string) $data['related_table']
            : (!empty($data['related_type']) ? (string) $data['related_type'] : null);

        $relatedType = !empty($data['related_type'])
            ? (string) $data['related_type']
            : $relatedTable;

        $relatedId = !empty($data['related_id']) ? (int) $data['related_id'] : null;

        $fields = [];
        $params = [];

        $this->addInsertField($fields, $params, 'recipient_user_id', $recipientUserId);
        $this->addInsertField($fields, $params, 'recipient_role', $recipientRole);
        $this->addInsertField($fields, $params, 'user_id', $recipientUserId);
        $this->addInsertField($fields, $params, 'actor_user_id', !empty($data['actor_user_id']) ? (int) $data['actor_user_id'] : null);

        $this->addInsertField($fields, $params, 'type', (string) ($data['type'] ?? 'general'));
        $this->addInsertField($fields, $params, 'title', (string) ($data['title'] ?? 'Notification'));
        $this->addInsertField($fields, $params, 'message', (string) ($data['message'] ?? ''));

        $this->addInsertField($fields, $params, 'link_url', $linkUrl !== '' ? $linkUrl : null);
        $this->addInsertField($fields, $params, 'target_url', $linkUrl !== '' ? $linkUrl : null);

        $this->addInsertField($fields, $params, 'related_table', $relatedTable);
        $this->addInsertField($fields, $params, 'related_type', $relatedType);
        $this->addInsertField($fields, $params, 'related_id', $relatedId);

        $this->addInsertField($fields, $params, 'is_read', 0);

        if ($this->hasColumn('notifications', 'created_at')) {
            $fields[] = 'created_at';
            $params[':created_at'] = date('Y-m-d H:i:s');
        }

        if (empty($fields)) {
            return 0;
        }

        $columns = implode(', ', $fields);
        $placeholders = implode(', ', array_keys($params));

        $stmt = $this->db->prepare("
            INSERT INTO notifications ($columns)
            VALUES ($placeholders)
        ");

        $stmt->execute($params);

        return (int) $this->db->lastInsertId();
    }

    public function notifyStaff(
        string $type,
        string $title,
        string $message,
        ?string $linkUrl = null,
        ?string $relatedTable = null,
        ?int $relatedId = null,
        ?int $actorUserId = null
    ): int {
        if ($this->hasColumn('notifications', 'recipient_role')) {
            if ($this->existsForRole('staff', $type, $relatedTable, $relatedId)) {
                return 0;
            }

            return $this->create([
                'recipient_user_id' => null,
                'recipient_role' => 'staff',
                'actor_user_id' => $actorUserId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link_url' => $linkUrl,
                'related_table' => $relatedTable,
                'related_type' => $relatedTable,
                'related_id' => $relatedId,
            ]);
        }

        /*
            Fallback for older notifications table:
            if recipient_role column does not exist, create one notification
            for each staff user using user_id.
        */
        $createdCount = 0;

        foreach ($this->getStaffUserIds() as $staffUserId) {
            if ($this->notifyUser(
                $staffUserId,
                $type,
                $title,
                $message,
                $linkUrl,
                $relatedTable,
                $relatedId,
                $actorUserId
            ) > 0) {
                $createdCount++;
            }
        }

        return $createdCount;
    }

    public function notifyUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $linkUrl = null,
        ?string $relatedTable = null,
        ?int $relatedId = null,
        ?int $actorUserId = null
    ): int {
        if ($userId <= 0) {
            return 0;
        }

        if ($this->existsForUser($userId, $type, $relatedTable, $relatedId)) {
            return 0;
        }

        return $this->create([
            'recipient_user_id' => $userId,
            'recipient_role' => null,
            'user_id' => $userId,
            'actor_user_id' => $actorUserId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link_url' => $linkUrl,
            'target_url' => $linkUrl,
            'related_table' => $relatedTable,
            'related_type' => $relatedTable,
            'related_id' => $relatedId,
        ]);
    }

    public function createForDentistAppointment(
        int $dentistId,
        int $appointmentId,
        string $type,
        string $title,
        string $message,
        ?int $actorUserId = null
    ): void {
        if ($dentistId <= 0 || $appointmentId <= 0) {
            return;
        }

        $stmt = $this->db->prepare("
            SELECT user_id
            FROM dentists
            WHERE dentist_id = :dentist_id
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':dentist_id' => $dentistId,
        ]);

        $dentistUserId = (int) $stmt->fetchColumn();

        if ($dentistUserId <= 0) {
            return;
        }

        $this->notifyUser(
            $dentistUserId,
            $type,
            $title,
            $message,
            '/DentalClinic/public/dentist/appointments/show?id=' . $appointmentId,
            'appointment',
            $appointmentId,
            $actorUserId
        );
    }

    public function createOrUpdateTodayAppointmentSummaryForDentistUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $dentistId = $this->findActiveDentistIdByUserId($userId);

        if ($dentistId <= 0) {
            return 0;
        }

        $today = date('Y-m-d');
        $todayKey = (int) date('Ymd');

        $appointmentCount = $this->countTodayAppointmentsForDentist($dentistId);

        if ($appointmentCount <= 0) {
            $this->deleteTodaySummaryNotification($userId, $todayKey);
            return 0;
        }

        $title = "Today's appointments";

        $message = $appointmentCount === 1
            ? 'You have 1 appointment for today.'
            : 'You have ' . $appointmentCount . ' appointments for today.';

        $targetUrl = '/DentalClinic/public/dentist/appointments?date=' . urlencode($today);

        $existing = $this->findTodaySummaryNotification($userId, $todayKey);

        if ($existing) {
            $notificationId = (int) $existing['notification_id'];
            $oldMessage = (string) ($existing['message'] ?? '');
            $oldTitle = (string) ($existing['title'] ?? '');

            $shouldUnreadAgain = ($oldMessage !== $message || $oldTitle !== $title);

            $this->updateTodaySummaryNotification(
                $userId,
                $notificationId,
                $title,
                $message,
                $targetUrl,
                $shouldUnreadAgain
            );

            return 0;
        }

        return $this->create([
            'recipient_user_id' => $userId,
            'user_id' => $userId,
            'actor_user_id' => null,
            'type' => 'today_appointments_summary',
            'title' => $title,
            'message' => $message,
            'link_url' => $targetUrl,
            'target_url' => $targetUrl,
            'related_table' => 'today_appointments',
            'related_type' => 'today_appointments',
            'related_id' => $todayKey,
        ]);
    }

    public function latestForUser(int $userId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM notifications
            WHERE " . $this->privateUserWhereSql() . "
            ORDER BY created_at DESC, notification_id DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min($limit, 20)), PDO::PARAM_INT);
        $stmt->execute();

        return $this->normalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function unreadForUser(int $userId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM notifications
            WHERE " . $this->privateUserWhereSql() . "
              AND is_read = 0
            ORDER BY created_at DESC, notification_id DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min($limit, 20)), PDO::PARAM_INT);
        $stmt->execute();

        return $this->normalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function unreadCountForUser(int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE " . $this->privateUserWhereSql() . "
              AND is_read = 0
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function markOneReadForUser(int $userId, int $notificationId): bool
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return false;
        }

        $sets = [
            'is_read = 1',
            'read_at = NOW()',
        ];

        if ($this->hasColumn('notifications', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        $stmt = $this->db->prepare("
            UPDATE notifications
            SET " . implode(', ', $sets) . "
            WHERE notification_id = :notification_id
              AND " . $this->privateUserWhereSql() . "
        ");

        $stmt->execute([
            ':notification_id' => $notificationId,
            ':user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markAllReadForUser(int $userId): int
    {
        $sets = [
            'is_read = 1',
            'read_at = NOW()',
        ];

        if ($this->hasColumn('notifications', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        $stmt = $this->db->prepare("
            UPDATE notifications
            SET " . implode(', ', $sets) . "
            WHERE " . $this->privateUserWhereSql() . "
              AND is_read = 0
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return $stmt->rowCount();
    }

    public function getUnreadCountForStaff(int $staffUserId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE " . $this->staffWhereSql() . "
              AND is_read = 0
        ");

        $stmt->execute([
            ':staff_user_id' => $staffUserId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function getLatestForStaff(int $staffUserId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM notifications
            WHERE " . $this->staffWhereSql() . "
            ORDER BY created_at DESC, notification_id DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min($limit, 20)), PDO::PARAM_INT);
        $stmt->execute();

        return $this->normalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function getAllForStaff(int $staffUserId, int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM notifications
            WHERE " . $this->staffWhereSql() . "
            ORDER BY created_at DESC, notification_id DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', max(1, min($limit, 50)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return $this->normalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function countAllForStaff(int $staffUserId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE " . $this->staffWhereSql() . "
        ");

        $stmt->execute([
            ':staff_user_id' => $staffUserId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function markAsReadForStaff(int $notificationId, int $staffUserId): ?string
    {
        if ($notificationId <= 0 || $staffUserId <= 0) {
            return null;
        }

        $notification = $this->findAccessibleForStaff($notificationId, $staffUserId);

        if (!$notification) {
            return null;
        }

        $sets = [
            'is_read = 1',
            'read_at = NOW()',
        ];

        if ($this->hasColumn('notifications', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        $stmt = $this->db->prepare("
            UPDATE notifications
            SET " . implode(', ', $sets) . "
            WHERE notification_id = :notification_id
        ");

        $stmt->execute([
            ':notification_id' => $notificationId,
        ]);

        return (string) ($notification['link_url'] ?? $notification['target_url'] ?? '');
    }

    public function markAllAsReadForStaff(int $staffUserId): int
    {
        $sets = [
            'is_read = 1',
            'read_at = NOW()',
        ];

        if ($this->hasColumn('notifications', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        $stmt = $this->db->prepare("
            UPDATE notifications
            SET " . implode(', ', $sets) . "
            WHERE " . $this->staffWhereSql() . "
              AND is_read = 0
        ");

        $stmt->execute([
            ':staff_user_id' => $staffUserId,
        ]);

        return $stmt->rowCount();
    }


public function notifyStaffUsers(
    string $type,
    string $title,
    string $message,
    string $linkUrl = '',
    ?int $actorUserId = null,
    ?int $appointmentId = null
): void {
    $staffUsers = $this->getStaffUserIds();

    foreach ($staffUsers as $staffUserId) {
        $this->createForUser(
            (int) $staffUserId,
            $type,
            $title,
            $message,
            $linkUrl,
            $actorUserId,
            $appointmentId
        );
    }
}

private function getStaffUserIds(): array
{
    $stmt = $this->db->prepare("
        SELECT u.user_id
        FROM users u
        INNER JOIN roles r ON r.role_id = u.role_id
        WHERE LOWER(r.role_name) IN ('staff', 'admin')
    ");

    $stmt->execute();

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

public function createForUser(
    int $userId,
    string $type,
    string $title,
    string $message,
    string $linkUrl = '',
    ?int $actorUserId = null,
    ?int $appointmentId = null
): int {
    if ($userId <= 0) {
        return 0;
    }

    /*
        This dynamic insert prevents crashing if your notifications table
        uses link_url OR target_url, and if some optional columns are missing.
    */
    $existingColumns = $this->getNotificationColumns();

    $data = [];

    if (isset($existingColumns['user_id'])) {
        $data['user_id'] = $userId;
    }

    if (isset($existingColumns['recipient_user_id'])) {
        $data['recipient_user_id'] = $userId;
    }

    if (isset($existingColumns['type'])) {
        $data['type'] = $type;
    }

    if (isset($existingColumns['notification_type'])) {
        $data['notification_type'] = $type;
    }

    if (isset($existingColumns['title'])) {
        $data['title'] = $title;
    }

    if (isset($existingColumns['message'])) {
        $data['message'] = $message;
    }

    if (isset($existingColumns['link_url'])) {
        $data['link_url'] = $linkUrl;
    }

    if (isset($existingColumns['target_url'])) {
        $data['target_url'] = $linkUrl;
    }

    if (isset($existingColumns['actor_user_id'])) {
        $data['actor_user_id'] = $actorUserId;
    }

    if (isset($existingColumns['created_by'])) {
        $data['created_by'] = $actorUserId;
    }

    if (isset($existingColumns['appointment_id'])) {
        $data['appointment_id'] = $appointmentId;
    }

    if (isset($existingColumns['is_read'])) {
        $data['is_read'] = 0;
    }

    if (isset($existingColumns['created_at'])) {
        $data['created_at'] = date('Y-m-d H:i:s');
    }

    if (isset($existingColumns['updated_at'])) {
        $data['updated_at'] = date('Y-m-d H:i:s');
    }

    if (empty($data)) {
        return 0;
    }

    $columns = array_keys($data);
    $placeholders = array_map(
        static fn (string $column): string => ':' . $column,
        $columns
    );

    $sql = "
        INSERT INTO notifications (
            " . implode(', ', $columns) . "
        ) VALUES (
            " . implode(', ', $placeholders) . "
        )
    ";

    $stmt = $this->db->prepare($sql);

    foreach ($data as $column => $value) {
        $stmt->bindValue(':' . $column, $value);
    }

    $stmt->execute();

    return (int) $this->db->lastInsertId();
}

private function getNotificationColumns(): array
{
    static $columns = null;

    if ($columns !== null) {
        return $columns;
    }

    $columns = [];

    $stmt = $this->db->query("SHOW COLUMNS FROM notifications");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $field = strtolower((string) ($row['Field'] ?? ''));

        if ($field !== '') {
            $columns[$field] = true;
        }
    }

    return $columns;
}




    private function findAccessibleForStaff(int $notificationId, int $staffUserId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM notifications
            WHERE notification_id = :notification_id
              AND " . $this->staffWhereSql() . "
            LIMIT 1
        ");

        $stmt->execute([
            ':notification_id' => $notificationId,
            ':staff_user_id' => $staffUserId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeRow($row) : null;
    }

    private function notificationExistsForUser(
        int $userId,
        string $type,
        string $relatedType,
        int $relatedId
    ): bool {
        return $this->existsForUser($userId, $type, $relatedType, $relatedId);
    }

    private function existsForUser(
        int $userId,
        string $type,
        ?string $relatedTable,
        ?int $relatedId
    ): bool {
        if ($userId <= 0 || $relatedTable === null || $relatedId === null || $relatedId <= 0) {
            return false;
        }

        $relatedSql = $this->relatedWhereSql();

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE " . $this->privateUserWhereSql() . "
              AND type = :type
              AND $relatedSql
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':related_value' => $relatedTable,
            ':related_id' => $relatedId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function existsForRole(
        string $role,
        string $type,
        ?string $relatedTable,
        ?int $relatedId
    ): bool {
        if (!$this->hasColumn('notifications', 'recipient_role')) {
            return false;
        }

        if ($relatedTable === null || $relatedId === null || $relatedId <= 0) {
            return false;
        }

        $relatedSql = $this->relatedWhereSql();

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE recipient_role = :recipient_role
              AND type = :type
              AND $relatedSql
        ");

        $stmt->execute([
            ':recipient_role' => $role,
            ':type' => $type,
            ':related_value' => $relatedTable,
            ':related_id' => $relatedId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function findTodaySummaryNotification(int $userId, int $todayKey): ?array
    {
        $relatedSql = $this->relatedWhereSql();

        $stmt = $this->db->prepare("
            SELECT *
            FROM notifications
            WHERE " . $this->privateUserWhereSql() . "
              AND type = 'today_appointments_summary'
              AND $relatedSql
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':related_value' => 'today_appointments',
            ':related_id' => $todayKey,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeRow($row) : null;
    }

    private function updateTodaySummaryNotification(
        int $userId,
        int $notificationId,
        string $title,
        string $message,
        string $targetUrl,
        bool $shouldUnreadAgain
    ): void {
        $sets = [
            'title = :title',
            'message = :message',
        ];

        if ($this->hasColumn('notifications', 'target_url')) {
            $sets[] = 'target_url = :target_url';
        }

        if ($this->hasColumn('notifications', 'link_url')) {
            $sets[] = 'link_url = :target_url';
        }

        if ($shouldUnreadAgain) {
            $sets[] = 'is_read = 0';
            $sets[] = 'read_at = NULL';
        }

        if ($this->hasColumn('notifications', 'updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        $stmt = $this->db->prepare("
            UPDATE notifications
            SET " . implode(', ', $sets) . "
            WHERE notification_id = :notification_id
              AND " . $this->privateUserWhereSql() . "
        ");

        $stmt->execute([
            ':title' => $title,
            ':message' => $message,
            ':target_url' => $targetUrl,
            ':notification_id' => $notificationId,
            ':user_id' => $userId,
        ]);
    }

    private function deleteTodaySummaryNotification(int $userId, int $todayKey): void
    {
        $relatedSql = $this->relatedWhereSql();

        $stmt = $this->db->prepare("
            DELETE FROM notifications
            WHERE " . $this->privateUserWhereSql() . "
              AND type = 'today_appointments_summary'
              AND $relatedSql
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':related_value' => 'today_appointments',
            ':related_id' => $todayKey,
        ]);
    }

    private function findActiveDentistIdByUserId(int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT dentist_id
            FROM dentists
            WHERE user_id = :user_id
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function countTodayAppointmentsForDentist(int $dentistId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointments
            WHERE dentist_id = :dentist_id
              AND appointment_date = CURDATE()
              AND status IN ('confirmed', 'rescheduled', 'checked_in', 'in_progress')
        ");

        $stmt->execute([
            ':dentist_id' => $dentistId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    

    private function resolveRoleNameColumn(): ?string
    {
        foreach (['role_name', 'name', 'slug'] as $column) {
            if ($this->hasColumn('roles', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function privateUserWhereSql(): string
    {
        $conditions = [];

        if ($this->hasColumn('notifications', 'recipient_user_id')) {
            $conditions[] = 'recipient_user_id = :user_id';
        }

        if ($this->hasColumn('notifications', 'user_id')) {
            $conditions[] = 'user_id = :user_id';
        }

        if (empty($conditions)) {
            return '1 = 0';
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    private function staffWhereSql(): string
    {
        $conditions = [];

        if ($this->hasColumn('notifications', 'recipient_user_id')) {
            $conditions[] = 'recipient_user_id = :staff_user_id';
        }

        if ($this->hasColumn('notifications', 'user_id')) {
            $conditions[] = 'user_id = :staff_user_id';
        }

        if ($this->hasColumn('notifications', 'recipient_role')) {
            $conditions[] = "(recipient_user_id IS NULL AND recipient_role = 'staff')";
        }

        if (empty($conditions)) {
            return '1 = 0';
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    private function relatedWhereSql(): string
    {
        $conditions = [];

        if ($this->hasColumn('notifications', 'related_table')) {
            $conditions[] = 'related_table = :related_value';
        }

        if ($this->hasColumn('notifications', 'related_type')) {
            $conditions[] = 'related_type = :related_value';
        }

        if (empty($conditions)) {
            return 'related_id = :related_id';
        }

        return '(' . implode(' OR ', $conditions) . ') AND related_id = :related_id';
    }

    private function addInsertField(array &$fields, array &$params, string $column, mixed $value): void
    {
        if (!$this->hasColumn('notifications', $column)) {
            return;
        }

        $fields[] = $column;
        $params[':' . $column] = $value;
    }

    private function normalizeRows(array $rows): array
    {
        return array_map(function (array $row): array {
            return $this->normalizeRow($row);
        }, $rows);
    }

    private function normalizeRow(array $row): array
    {
        $row['link_url'] = $row['link_url'] ?? $row['target_url'] ?? '';
        $row['target_url'] = $row['target_url'] ?? $row['link_url'] ?? '';

        $row['related_table'] = $row['related_table'] ?? $row['related_type'] ?? null;
        $row['related_type'] = $row['related_type'] ?? $row['related_table'] ?? null;

        $row['is_read'] = (int) ($row['is_read'] ?? 0);

        return $row;
    }

    private function safeInternalUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '/DentalClinic/public')) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return '/DentalClinic/public' . $url;
        }

        return '';
    }

    private function hasTable(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ");

            $stmt->execute([
                ':table_name' => $table,
            ]);

            $this->tableCache[$table] = (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            $this->tableCache[$table] = false;
        }

        return $this->tableCache[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name
            ");

            $stmt->execute([
                ':table_name' => $table,
                ':column_name' => $column,
            ]);

            $this->columnCache[$key] = (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            $this->columnCache[$key] = false;
        }

        return $this->columnCache[$key];
    }
}