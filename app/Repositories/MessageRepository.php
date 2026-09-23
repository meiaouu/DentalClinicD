<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class MessageRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
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
                :sent_at
            )
        ");
        $stmt->execute([
            'conversation_id' => $data['conversation_id'],
            'sender_user_id' => $data['sender_user_id'],
            'sender_type' => $data['sender_type'],
            'message_text' => $data['message_text'],
            'is_bot_reply' => $data['is_bot_reply'],
            'sent_at' => $data['sent_at'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getByConversationId(int $conversationId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                m.message_id,
                m.conversation_id,
                m.sender_user_id,
                m.sender_type,
                m.message_text,
                m.is_bot_reply,
                m.sent_at
            FROM messages m
            WHERE m.conversation_id = :conversation_id
            ORDER BY m.sent_at ASC, m.message_id ASC
        ");
        $stmt->execute([
            'conversation_id' => $conversationId,
        ]);

        return $stmt->fetchAll();
    }
}