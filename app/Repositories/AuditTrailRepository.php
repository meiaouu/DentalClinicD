<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AuditTrailRepository
{
    private PDO $db;
    private array $columns = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->columns = $this->columns('audit_logs');
    }

    public function paginate(string $search = '', int $limit = 25, int $offset = 0): array
    {
        $sql = "
            SELECT
                al.*,
                u.username,
                u.first_name,
                u.last_name
            FROM audit_logs al
            LEFT JOIN users u ON u.user_id = al.user_id
        ";

        $params = [];

        if ($search !== '') {
            $where = [];

            foreach (['action', 'action_name', 'module_name', 'description', 'entity_type', 'record_type'] as $column) {
                if (isset($this->columns[$column])) {
                    $where[] = "al.`$column` LIKE :search";
                }
            }

            if (!empty($where)) {
                $sql .= " WHERE " . implode(' OR ', $where);
                $params[':search'] = '%' . $search . '%';
            }
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(string $search = ''): int
    {
        $sql = "SELECT COUNT(*) FROM audit_logs al";
        $params = [];

        if ($search !== '') {
            $where = [];

            foreach (['action', 'action_name', 'module_name', 'description', 'entity_type', 'record_type'] as $column) {
                if (isset($this->columns[$column])) {
                    $where[] = "al.`$column` LIKE :search";
                }
            }

            if (!empty($where)) {
                $sql .= " WHERE " . implode(' OR ', $where);
                $params[':search'] = '%' . $search . '%';
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function columns(string $table): array
    {
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM `$table`");
            $columns = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $columns[(string) $row['Field']] = true;
            }

            return $columns;
        } catch (\Throwable $e) {
            return [];
        }
    }
}