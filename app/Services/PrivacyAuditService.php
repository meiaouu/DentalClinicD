<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

class PrivacyAuditService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function log(
        ?int $userId,
        string $moduleName,
        string $actionName,
        string $recordType,
        ?int $recordId,
        string $description
    ): void {
        try {
            if (!$this->tableExists('audit_logs')) {
                return;
            }

            $data = [
                'user_id' => $userId,
                'module_name' => $moduleName,
                'action_name' => $actionName,
                'record_type' => $recordType,
                'record_id' => $recordId,
                'description' => $description,
                'ip_address' => $this->ipAddress(),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $data = $this->filterColumns('audit_logs', $data);

            if (empty($data)) {
                return;
            }

            $columns = array_keys($data);
            $columnSql = implode(', ', array_map(fn (string $column): string => "`{$column}`", $columns));
            $valueSql = implode(', ', array_map(fn (string $column): string => ":{$column}", $columns));

            $params = [];
            foreach ($data as $column => $value) {
                $params[":{$column}"] = $value;
            }

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs ({$columnSql})
                VALUES ({$valueSql})
            ");

            $stmt->execute($params);
        } catch (Throwable $e) {
            error_log('[PrivacyAuditService] ' . $e->getMessage());
        }
    }

    private function ipAddress(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
        ");

        $stmt->execute([
            ':table_name' => $table,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function filterColumns(string $table, array $data): array
    {
        $columns = $this->columns($table);
        $filtered = [];

        foreach ($data as $column => $value) {
            if (in_array($column, $columns, true)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    private function columns(string $table): array
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

        $stmt = $this->db->query("SHOW COLUMNS FROM `{$safeTable}`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $cache[$table] = array_map(
            fn (array $row): string => (string) $row['Field'],
            $rows
        );

        return $cache[$table];
    }
}