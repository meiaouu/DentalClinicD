<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\ConversationRepository;
use App\Repositories\MessageRepository;
use PDO;
use RuntimeException;
use Throwable;

class MessagingService
{
    private ConversationRepository $conversations;
    private MessageRepository $messages;

    public function __construct()
    {
        $this->conversations = new ConversationRepository();
        $this->messages = new MessageRepository();
    }

    public function startOrGetPatientConversation(int $patientId, ?int $handledBy = null): int
    {
        if ($patientId <= 0) {
            throw new RuntimeException('Invalid patient record.');
        }

        return $this->conversations->findOrCreatePatientConversation($patientId, $handledBy);
    }

    public function replyAsStaff(int $conversationId, int $staffUserId, string $messageBody): int
    {
        $messageBody = trim($messageBody);

        if ($conversationId <= 0) {
            throw new RuntimeException('Invalid conversation.');
        }

        if ($staffUserId <= 0) {
            throw new RuntimeException('Invalid staff account.');
        }

        if ($messageBody === '') {
            throw new RuntimeException('Message body is required.');
        }

        $conversation = $this->conversations->findDetailedById($conversationId);

        if (!$conversation) {
            throw new RuntimeException('Conversation not found.');
        }

        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            $messageId = $this->messages->create([
                'conversation_id' => $conversationId,
                'sender_user_id' => $staffUserId,
                'sender_type' => 'staff',
                'message_text' => $messageBody,
                'is_bot_reply' => 0,
                'sent_at' => date('Y-m-d H:i:s'),
            ]);

            $this->touchConversationSafely($conversationId, $staffUserId, 'open');

            $db->commit();

            return $messageId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public function addBotReply(int $conversationId, string $messageBody): int
    {
        $messageBody = trim($messageBody);

        if ($conversationId <= 0) {
            throw new RuntimeException('Invalid conversation.');
        }

        if ($messageBody === '') {
            throw new RuntimeException('Bot message is required.');
        }

        $conversation = $this->conversations->findDetailedById($conversationId);

        if (!$conversation) {
            throw new RuntimeException('Conversation not found.');
        }

        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            $messageId = $this->messages->create([
                'conversation_id' => $conversationId,
                'sender_user_id' => null,
                'sender_type' => 'bot',
                'message_text' => $messageBody,
                'is_bot_reply' => 1,
                'sent_at' => date('Y-m-d H:i:s'),
            ]);

            $this->touchConversationSafely($conversationId, null, 'bot_only');

            $db->commit();

            return $messageId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public function getConversationThread(int $conversationId): array
    {
        if ($conversationId <= 0) {
            throw new RuntimeException('Invalid conversation.');
        }

        $conversation = $this->conversations->findDetailedById($conversationId);

        if (!$conversation) {
            throw new RuntimeException('Conversation not found.');
        }

        $messages = $this->messages->getByConversationId($conversationId);

        return [
            'conversation' => $conversation,
            'messages' => is_array($messages) ? $messages : [],
        ];
    }

    public function getStaffInbox(int $page = 1, int $perPage = 15): array
    {
        return $this->getStaffInboxByType($page, $perPage, 'all');
    }

    public function getStaffInboxByType(int $page = 1, int $perPage = 15, string $type = 'all'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $type = strtolower(trim($type));

        if (!in_array($type, ['all', 'patients', 'guests', 'unread'], true)) {
            $type = 'all';
        }

        $db = Database::getConnection();

        if (!$this->tableExists('conversations')) {
            return $this->emptyInbox($page, $perPage);
        }

        if (!$this->tableExists('messages')) {
            return $this->getInboxWithoutMessages($page, $perPage, $type);
        }

        $where = [];

        if ($type === 'patients') {
            $where[] = 'c.patient_id IS NOT NULL AND c.patient_id > 0';
        }

        if ($type === 'guests') {
            $where[] = '(c.patient_id IS NULL OR c.patient_id = 0)';
        }

        if ($type === 'unread') {
            $where[] = "latest.sender_type IN ('patient', 'guest')";
        }

        $whereSql = !empty($where)
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $countSql = "
            SELECT COUNT(*)
            FROM conversations c
            LEFT JOIN (
                SELECT m1.*
                FROM messages m1
                INNER JOIN (
                    SELECT conversation_id, MAX(message_id) AS latest_message_id
                    FROM messages
                    GROUP BY conversation_id
                ) latest_ids ON latest_ids.latest_message_id = m1.message_id
            ) latest ON latest.conversation_id = c.conversation_id
            {$whereSql}
        ";

        $countStmt = $db->prepare($countSql);
        $countStmt->execute();

        $total = (int) $countStmt->fetchColumn();

        $sql = "
            SELECT
                c.conversation_id,
                c.patient_id,
                c.handled_by,
                c.conversation_status,
                c.created_at,
                c.updated_at,

                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,

                latest.message_text AS last_message,
                latest.sender_type AS last_sender_type,
                latest.sent_at AS last_message_at,

                CASE
                    WHEN latest.sender_type IN ('patient', 'guest') THEN 1
                    ELSE 0
                END AS unread_count

            FROM conversations c

            LEFT JOIN patients p ON p.patient_id = c.patient_id

            LEFT JOIN (
                SELECT m1.*
                FROM messages m1
                INNER JOIN (
                    SELECT conversation_id, MAX(message_id) AS latest_message_id
                    FROM messages
                    GROUP BY conversation_id
                ) latest_ids ON latest_ids.latest_message_id = m1.message_id
            ) latest ON latest.conversation_id = c.conversation_id

            {$whereSql}

            ORDER BY
                COALESCE(latest.sent_at, c.updated_at, c.created_at) DESC,
                c.conversation_id DESC

            LIMIT :limit OFFSET :offset
        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function markConversationAsRead(int $conversationId, int $staffUserId): void
    {
        if ($conversationId <= 0 || $staffUserId <= 0) {
            return;
        }

        if (!$this->tableExists('messages')) {
            return;
        }

        $db = Database::getConnection();

        try {
            $columns = $this->getTableColumns('messages');

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

            $stmt = $db->prepare("
                UPDATE messages
                SET " . implode(', ', $sets) . "
                WHERE conversation_id = :conversation_id
                  AND LOWER(COALESCE(sender_type, '')) IN ('patient', 'guest')
            ");

            $stmt->execute([
                ':conversation_id' => $conversationId,
            ]);
        } catch (Throwable $e) {
            return;
        }
    }

    private function getInboxWithoutMessages(int $page, int $perPage, string $type): array
    {
        $db = Database::getConnection();

        $offset = ($page - 1) * $perPage;
        $where = [];

        if ($type === 'patients') {
            $where[] = 'c.patient_id IS NOT NULL AND c.patient_id > 0';
        }

        if ($type === 'guests') {
            $where[] = '(c.patient_id IS NULL OR c.patient_id = 0)';
        }

        if ($type === 'unread') {
            return $this->emptyInbox($page, $perPage);
        }

        $whereSql = !empty($where)
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        $countStmt = $db->prepare("
            SELECT COUNT(*)
            FROM conversations c
            {$whereSql}
        ");

        $countStmt->execute();

        $total = (int) $countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT
                c.conversation_id,
                c.patient_id,
                c.handled_by,
                c.conversation_status,
                c.created_at,
                c.updated_at,

                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,

                NULL AS last_message,
                NULL AS last_sender_type,
                NULL AS last_message_at,
                0 AS unread_count

            FROM conversations c
            LEFT JOIN patients p ON p.patient_id = c.patient_id
            {$whereSql}
            ORDER BY
                c.updated_at DESC,
                c.created_at DESC,
                c.conversation_id DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    private function emptyInbox(int $page, int $perPage): array
    {
        return [
            'items' => [],
            'total' => 0,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    private function touchConversationSafely(int $conversationId, ?int $handledBy, string $status): void
    {
        try {
            if (method_exists($this->conversations, 'touch')) {
                $this->conversations->touch($conversationId, $handledBy, $status);
                return;
            }

            $db = Database::getConnection();

            $columns = $this->getTableColumns('conversations');

            if (empty($columns)) {
                return;
            }

            $sets = [];

            if (isset($columns['updated_at'])) {
                $sets[] = 'updated_at = NOW()';
            }

            if (isset($columns['handled_by']) && $handledBy !== null) {
                $sets[] = 'handled_by = :handled_by';
            }

            if (isset($columns['conversation_status'])) {
                $sets[] = 'conversation_status = :conversation_status';
            }

            if (empty($sets)) {
                return;
            }

            $sql = "
                UPDATE conversations
                SET " . implode(', ', $sets) . "
                WHERE conversation_id = :conversation_id
                LIMIT 1
            ";

            $stmt = $db->prepare($sql);

            $params = [
                ':conversation_id' => $conversationId,
            ];

            if (isset($columns['handled_by']) && $handledBy !== null) {
                $params[':handled_by'] = $handledBy;
            }

            if (isset($columns['conversation_status'])) {
                $params[':conversation_status'] = $status;
            }

            $stmt->execute($params);
        } catch (Throwable $e) {
            return;
        }
    }

    private function tableExists(string $tableName): bool
    {
        static $cache = [];

        $key = strtolower($tableName);

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $db = Database::getConnection();

            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ");

            $stmt->execute([
                ':table_name' => $tableName,
            ]);

            $cache[$key] = (int) $stmt->fetchColumn() > 0;

            return $cache[$key];
        } catch (Throwable $e) {
            $cache[$key] = false;

            return false;
        }
    }

    private function getTableColumns(string $tableName): array
    {
        static $cache = [];

        $key = strtolower($tableName);

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $cache[$key] = [];

        try {
            if (!$this->tableExists($tableName)) {
                return $cache[$key];
            }

            $db = Database::getConnection();

            $stmt = $db->prepare("
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
                $cache[$key][strtolower((string) $column)] = true;
            }
        } catch (Throwable $e) {
            $cache[$key] = [];
        }

        return $cache[$key];
    }
}