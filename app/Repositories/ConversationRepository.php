<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ConversationRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findOrCreatePatientConversation(int $patientId, ?int $handledBy = null): int
    {
        $findStmt = $this->db->prepare("
            SELECT conversation_id
            FROM conversations
            WHERE patient_id = :patient_id
              AND conversation_status IN ('open', 'pending_staff', 'bot_only')
            ORDER BY conversation_id DESC
            LIMIT 1
        ");
        $findStmt->execute([
            'patient_id' => $patientId,
        ]);

        $existingId = $findStmt->fetchColumn();

        if ($existingId) {
            return (int) $existingId;
        }

        $insertStmt = $this->db->prepare("
            INSERT INTO conversations (
                patient_id,
                handled_by,
                conversation_status,
                created_at,
                updated_at
            ) VALUES (
                :patient_id,
                :handled_by,
                :conversation_status,
                NOW(),
                NOW()
            )
        ");
        $insertStmt->execute([
            'patient_id' => $patientId,
            'handled_by' => $handledBy,
            'conversation_status' => 'open',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function paginateForStaff(int $limit = 15, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.conversation_id,
                c.patient_id,
                c.handled_by,
                c.conversation_status,
                c.created_at,
                c.updated_at,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name
            FROM conversations c
            LEFT JOIN patients p ON p.patient_id = c.patient_id
            ORDER BY c.updated_at DESC, c.conversation_id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countForStaff(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM conversations");
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findDetailedById(int $conversationId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.*,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                p.contact_number,
                p.email
            FROM conversations c
            LEFT JOIN patients p ON p.patient_id = c.patient_id
            WHERE c.conversation_id = :conversation_id
            LIMIT 1
        ");
        $stmt->execute([
            'conversation_id' => $conversationId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function touch(int $conversationId, ?int $handledBy = null, ?string $status = null): void
    {
        $sets = ['updated_at = NOW()'];
        $params = ['conversation_id' => $conversationId];

        if ($handledBy !== null) {
            $sets[] = 'handled_by = :handled_by';
            $params['handled_by'] = $handledBy;
        }

        if ($status !== null) {
            $sets[] = 'conversation_status = :conversation_status';
            $params['conversation_status'] = $status;
        }

        $sql = "UPDATE conversations SET " . implode(', ', $sets) . " WHERE conversation_id = :conversation_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
}