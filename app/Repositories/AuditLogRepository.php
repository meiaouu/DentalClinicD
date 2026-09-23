<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AuditLogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs (
                user_id,
                module_name,
                action_name,
                record_type,
                record_id,
                description,
                created_at
            ) VALUES (
                :user_id,
                :module_name,
                :action_name,
                :record_type,
                :record_id,
                :description,
                NOW()
            )
        ");
        $stmt->execute([
            'user_id' => $data['user_id'],
            'module_name' => $data['module_name'],
            'action_name' => $data['action_name'],
            'record_type' => $data['record_type'],
            'record_id' => $data['record_id'],
            'description' => $data['description'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function paginate(int $limit = 30, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT
                audit_id,
                user_id,
                module_name,
                action_name,
                record_type,
                record_id,
                description,
                created_at
            FROM audit_logs
            ORDER BY created_at DESC, audit_id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM audit_logs");
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}