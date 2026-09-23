<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

class BackupRepository
{
    private PDO $db;
    private string $backupDirectory;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->backupDirectory = dirname(__DIR__, 2) . '/storage/backups';
    }

    public function all(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM system_backups
            WHERE deleted_at IS NULL
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $backupId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM system_backups
            WHERE backup_id = :backup_id
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            ':backup_id' => $backupId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(int $userId): int
    {
        if (!is_dir($this->backupDirectory)) {
            mkdir($this->backupDirectory, 0755, true);
        }

        if (!is_writable($this->backupDirectory)) {
            throw new RuntimeException('Backup directory is not writable.');
        }

        $databaseName = (string) $this->db->query('SELECT DATABASE()')->fetchColumn();

        if ($databaseName === '') {
            throw new RuntimeException('Database name not found.');
        }

        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $path = $this->backupDirectory . '/' . $filename;

        $handle = fopen($path, 'w');

        if (!$handle) {
            throw new RuntimeException('Unable to create backup file.');
        }

        fwrite($handle, "-- Dental Clinic Database Backup\n");
        fwrite($handle, "-- Created: " . date('Y-m-d H:i:s') . "\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = $this->db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];

        foreach ($tables as $table) {
            $table = (string) $table;

            $createStmt = $this->db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $createSql = $createStmt['Create Table'] ?? array_values($createStmt)[1] ?? '';

            fwrite($handle, "\n-- Table structure for `$table`\n");
            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($handle, $createSql . ";\n\n");

            fwrite($handle, "-- Data for `$table`\n");

            $rows = $this->db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $row) {
                $columns = array_map(static fn ($column) => "`$column`", array_keys($row));

                $values = array_map(function ($value) {
                    return $value === null ? 'NULL' : $this->db->quote((string) $value);
                }, array_values($row));

                fwrite(
                    $handle,
                    "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n"
                );
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        $stmt = $this->db->prepare("
            INSERT INTO system_backups (
                backup_file,
                backup_path,
                file_size,
                created_by,
                created_at
            ) VALUES (
                :backup_file,
                :backup_path,
                :file_size,
                :created_by,
                NOW()
            )
        ");

        $stmt->execute([
            ':backup_file' => $filename,
            ':backup_path' => $path,
            ':file_size' => filesize($path) ?: 0,
            ':created_by' => $userId > 0 ? $userId : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function delete(int $backupId): void
    {
        $backup = $this->find($backupId);

        if (!$backup) {
            throw new RuntimeException('Backup not found.');
        }

        $path = (string) ($backup['backup_path'] ?? '');

        if ($path !== '' && is_file($path)) {
            unlink($path);
        }

        $stmt = $this->db->prepare("
            UPDATE system_backups
            SET deleted_at = NOW()
            WHERE backup_id = :backup_id
        ");

        $stmt->execute([
            ':backup_id' => $backupId,
        ]);
    }

    public function audit(int $userId, string $action, string $entityType, ?int $entityId, string $description): void
    {
        $columns = $this->columns('audit_logs');

        if (isset($columns['action'])) {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address, created_at)
                VALUES (:user_id, :action, :entity_type, :entity_id, :description, :ip_address, NOW())
            ");

            $stmt->execute([
                ':user_id' => $userId ?: null,
                ':action' => $action,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':description' => $description,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO audit_logs (user_id, module_name, action_name, record_type, record_id, description, created_at)
            VALUES (:user_id, 'admin', :action_name, :record_type, :record_id, :description, NOW())
        ");

        $stmt->execute([
            ':user_id' => $userId ?: null,
            ':action_name' => $action,
            ':record_type' => $entityType,
            ':record_id' => $entityId,
            ':description' => $description,
        ]);
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