<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PublicChatRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function createGuestConversation(): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO conversations (
                patient_id,
                handled_by,
                conversation_status,
                created_at,
                updated_at
            ) VALUES (
                NULL,
                NULL,
                'bot_only',
                NOW(),
                NOW()
            )
        ");

        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }

    public function conversationExists(int $conversationId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM conversations
            WHERE conversation_id = :conversation_id
            LIMIT 1
        ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function addMessage(
        int $conversationId,
        ?int $senderUserId,
        string $senderType,
        string $messageText,
        int $isBotReply = 0
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO messages (
                conversation_id,
                sender_user_id,
                sender_type,
                message_text,
                is_bot_reply,
                sent_at
            ) VALUES (
                :conversation_id,
                :sender_user_id,
                :sender_type,
                :message_text,
                :is_bot_reply,
                NOW()
            )
        ");

        $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);

        if ($senderUserId === null) {
            $stmt->bindValue(':sender_user_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':sender_user_id', $senderUserId, PDO::PARAM_INT);
        }

        $stmt->bindValue(':sender_type', $senderType);
        $stmt->bindValue(':message_text', $messageText);
        $stmt->bindValue(':is_bot_reply', $isBotReply, PDO::PARAM_INT);

        $stmt->execute();

        $this->touchConversation($conversationId);

        return (int) $this->db->lastInsertId();
    }

    public function getMessages(int $conversationId, int $limit = 80): array
    {
        $stmt = $this->db->prepare("
            SELECT
                message_id,
                conversation_id,
                sender_user_id,
                sender_type,
                message_text,
                is_bot_reply,
                sent_at
            FROM messages
            WHERE conversation_id = :conversation_id
            ORDER BY sent_at ASC, message_id ASC
            LIMIT :limit
        ");

        $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findMessageById(int $messageId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                message_id,
                conversation_id,
                sender_user_id,
                sender_type,
                message_text,
                is_bot_reply,
                sent_at
            FROM messages
            WHERE message_id = :message_id
            LIMIT 1
        ");

        $stmt->execute([
            ':message_id' => $messageId,
        ]);

        $message = $stmt->fetch(PDO::FETCH_ASSOC);

        return $message ?: null;
    }

    public function markPendingStaff(int $conversationId): void
    {
        $stmt = $this->db->prepare("
            UPDATE conversations
            SET conversation_status = 'pending_staff',
                updated_at = NOW()
            WHERE conversation_id = :conversation_id
        ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
        ]);
    }

    private function touchConversation(int $conversationId): void
    {
        $stmt = $this->db->prepare("
            UPDATE conversations
            SET updated_at = NOW()
            WHERE conversation_id = :conversation_id
        ");

        $stmt->execute([
            ':conversation_id' => $conversationId,
        ]);
    }
}