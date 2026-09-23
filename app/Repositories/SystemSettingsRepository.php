<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class SystemSettingsRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ensureSystemSettingsTable();
        $this->ensureClinicHoursTable();
    }

    public function getAllGrouped(): array
    {
        $this->ensureSystemSettingsTable();

        $columns = $this->columns('system_settings');

        $groupColumn = isset($columns['setting_group']) ? 'setting_group' : null;
        $keyColumn = isset($columns['setting_key']) ? 'setting_key' : null;
        $valueColumn = isset($columns['setting_value']) ? 'setting_value' : null;
        $typeColumn = isset($columns['setting_type']) ? 'setting_type' : null;

        if ($groupColumn === null && isset($columns['group_name'])) {
            $groupColumn = 'group_name';
        }

        if ($keyColumn === null && isset($columns['setting_name'])) {
            $keyColumn = 'setting_name';
        }

        if ($keyColumn === null && isset($columns['name'])) {
            $keyColumn = 'name';
        }

        if ($valueColumn === null && isset($columns['value'])) {
            $valueColumn = 'value';
        }

        if ($groupColumn === null || $keyColumn === null || $valueColumn === null) {
            return [];
        }

        $select = [
            "`$groupColumn` AS setting_group",
            "`$keyColumn` AS setting_key",
            "`$valueColumn` AS setting_value",
        ];

        if ($typeColumn !== null) {
            $select[] = "`$typeColumn` AS setting_type";
        } else {
            $select[] = "'string' AS setting_type";
        }

        $stmt = $this->db->query("
            SELECT " . implode(', ', $select) . "
            FROM system_settings
            ORDER BY `$groupColumn` ASC, `$keyColumn` ASC
        ");

        $grouped = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $group = trim((string) ($row['setting_group'] ?? ''));
            $key = trim((string) ($row['setting_key'] ?? ''));

            if ($group === '' || $key === '') {
                continue;
            }

            $grouped[$group][$key] = (string) ($row['setting_value'] ?? '');
        }

        return $grouped;
    }

    public function get(string $group, string $key, string $default = ''): string
    {
        $this->ensureSystemSettingsTable();

        $stmt = $this->db->prepare("
            SELECT setting_value
            FROM system_settings
            WHERE setting_group = :setting_group
              AND setting_key = :setting_key
            LIMIT 1
        ");

        $stmt->execute([
            ':setting_group' => $group,
            ':setting_key' => $key,
        ]);

        $value = $stmt->fetchColumn();

        return $value !== false ? (string) $value : $default;
    }

    public function bool(string $group, string $key, bool $default = false): bool
    {
        $value = strtolower(trim($this->get($group, $key, $default ? '1' : '0')));

        return in_array($value, ['1', 'yes', 'true', 'on', 'enabled'], true);
    }

    public function int(string $group, string $key, int $default = 0): int
    {
        $value = $this->get($group, $key, (string) $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function saveGroup(
        string $group,
        array $data,
        int $updatedBy = 0,
        array $publicKeys = []
    ): void {
        $this->ensureSystemSettingsTable();

        $group = trim($group);

        if ($group === '') {
            throw new RuntimeException('Invalid settings group.');
        }

        $this->db->beginTransaction();

        try {
            foreach ($data as $key => $value) {
                $key = trim((string) $key);

                if ($key === '') {
                    continue;
                }

                $isPublic = in_array($key, $publicKeys, true) ? 1 : 0;

                $this->saveSingleSetting(
                    $group,
                    $key,
                    (string) $value,
                    'string',
                    $updatedBy > 0 ? $updatedBy : null,
                    $isPublic
                );
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function saveSingleSetting(
        string $group,
        string $key,
        string $value,
        string $type = 'string',
        ?int $updatedBy = null,
        int $isPublic = 0
    ): void {
        $this->ensureSystemSettingsTable();

        $group = trim($group);
        $key = trim($key);
        $type = trim($type) !== '' ? trim($type) : 'string';

        if ($group === '' || $key === '') {
            throw new RuntimeException('Invalid setting.');
        }

        if (!preg_match('/^[a-z0-9_]+$/', $group) || !preg_match('/^[a-z0-9_]+$/', $key)) {
            throw new RuntimeException('Invalid setting name.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO system_settings (
                setting_group,
                setting_key,
                setting_value,
                setting_type,
                is_public,
                updated_by,
                created_at,
                updated_at
            ) VALUES (
                :setting_group,
                :setting_key,
                :setting_value,
                :setting_type,
                :is_public,
                :updated_by,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                setting_type = VALUES(setting_type),
                is_public = VALUES(is_public),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
        ");

        $stmt->execute([
            ':setting_group' => $group,
            ':setting_key' => $key,
            ':setting_value' => $value,
            ':setting_type' => $type,
            ':is_public' => $isPublic,
            ':updated_by' => $updatedBy,
        ]);
    }

    public function clinicHours(): array
    {
        $this->ensureClinicHoursTable();

        $stmt = $this->db->query("
            SELECT *
            FROM clinic_hours
            ORDER BY FIELD(day_of_week, 1, 2, 3, 4, 5, 6, 0)
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function clinicHourByDay(int $dayOfWeek): ?array
    {
        $this->ensureClinicHoursTable();

        $stmt = $this->db->prepare("
            SELECT *
            FROM clinic_hours
            WHERE day_of_week = :day_of_week
            LIMIT 1
        ");

        $stmt->execute([
            ':day_of_week' => $dayOfWeek,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function saveClinicHours(array $days, ?int $updatedBy = null): void
    {
        $this->ensureClinicHoursTable();

        $stmt = $this->db->prepare("
            INSERT INTO clinic_hours (
                day_of_week,
                is_open,
                opening_time,
                closing_time,
                break_start,
                break_end,
                created_at,
                updated_at
            ) VALUES (
                :day_of_week,
                :is_open,
                :opening_time,
                :closing_time,
                :break_start,
                :break_end,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                is_open = VALUES(is_open),
                opening_time = VALUES(opening_time),
                closing_time = VALUES(closing_time),
                break_start = VALUES(break_start),
                break_end = VALUES(break_end),
                updated_at = NOW()
        ");

        $this->db->beginTransaction();

        try {
            foreach ($days as $dayOfWeek => $row) {
                $dayOfWeek = (int) $dayOfWeek;

                if (!in_array($dayOfWeek, [0, 1, 2, 3, 4, 5, 6], true)) {
                    continue;
                }

                $row = is_array($row) ? $row : [];

                $stmt->execute([
                    ':day_of_week' => $dayOfWeek,
                    ':is_open' => !empty($row['is_open']) ? 1 : 0,
                    ':opening_time' => $this->timeOrNull($row['opening_time'] ?? null),
                    ':closing_time' => $this->timeOrNull($row['closing_time'] ?? null),
                    ':break_start' => $this->timeOrNull($row['break_start'] ?? null),
                    ':break_end' => $this->timeOrNull($row['break_end'] ?? null),
                ]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function audit(
        int $userId,
        string $action,
        string $entityType,
        ?int $entityId,
        string $description
    ): void {
        try {
            $columns = $this->columns('audit_logs');

            if (empty($columns)) {
                return;
            }

            if (isset($columns['action'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO audit_logs (
                        user_id,
                        action,
                        entity_type,
                        entity_id,
                        description,
                        ip_address,
                        created_at
                    ) VALUES (
                        :user_id,
                        :action,
                        :entity_type,
                        :entity_id,
                        :description,
                        :ip_address,
                        NOW()
                    )
                ");

                $stmt->execute([
                    ':user_id' => $userId > 0 ? $userId : null,
                    ':action' => $action,
                    ':entity_type' => $entityType,
                    ':entity_id' => $entityId,
                    ':description' => $description,
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);

                return;
            }

            if (isset($columns['action_name'])) {
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
                    ':user_id' => $userId > 0 ? $userId : null,
                    ':module_name' => 'admin',
                    ':action_name' => $action,
                    ':record_type' => $entityType,
                    ':record_id' => $entityId,
                    ':description' => $description,
                ]);
            }
        } catch (Throwable $e) {
            // Audit logging must not break the main system flow.
        }
    }

    private function ensureSystemSettingsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                setting_id INT AUTO_INCREMENT PRIMARY KEY,
                setting_group VARCHAR(80) NOT NULL,
                setting_key VARCHAR(120) NOT NULL,
                setting_value TEXT NULL,
                setting_type VARCHAR(30) NOT NULL DEFAULT 'string',
                is_public TINYINT(1) NOT NULL DEFAULT 0,
                updated_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_system_settings_group_key (setting_group, setting_key),
                INDEX idx_system_settings_group (setting_group),
                INDEX idx_system_settings_key (setting_key)
            )
        ");

        $this->ensureColumn('system_settings', 'setting_type', "ALTER TABLE system_settings ADD COLUMN setting_type VARCHAR(30) NOT NULL DEFAULT 'string'");
        $this->ensureColumn('system_settings', 'is_public', "ALTER TABLE system_settings ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0");
        $this->ensureColumn('system_settings', 'updated_by', "ALTER TABLE system_settings ADD COLUMN updated_by INT NULL");
        $this->ensureColumn('system_settings', 'created_at', "ALTER TABLE system_settings ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        $this->ensureColumn('system_settings', 'updated_at', "ALTER TABLE system_settings ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

        $this->ensureIndex('system_settings', 'uq_system_settings_group_key', "
            ALTER TABLE system_settings
            ADD UNIQUE KEY uq_system_settings_group_key (setting_group, setting_key)
        ");
    }

    private function ensureClinicHoursTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clinic_hours (
                clinic_hour_id INT AUTO_INCREMENT PRIMARY KEY,
                day_of_week TINYINT NOT NULL,
                is_open TINYINT(1) NOT NULL DEFAULT 1,
                opening_time TIME NULL,
                closing_time TIME NULL,
                break_start TIME NULL,
                break_end TIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_clinic_hours_day (day_of_week)
            )
        ");
    }

    private function ensureColumn(string $table, string $column, string $alterSql): void
    {
        $columns = $this->columns($table);

        if (isset($columns[$column])) {
            return;
        }

        try {
            $this->db->exec($alterSql);
        } catch (Throwable $e) {
            // Ignore duplicate column or restricted ALTER errors.
        }
    }

    private function ensureIndex(string $table, string $index, string $alterSql): void
    {
        try {
            $stmt = $this->db->prepare("
                SHOW INDEX FROM `$table`
                WHERE Key_name = :index_name
            ");

            $stmt->execute([
                ':index_name' => $index,
            ]);

            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                return;
            }

            $this->db->exec($alterSql);
        } catch (Throwable $e) {
            // Ignore duplicate index or restricted ALTER errors.
        }
    }

    private function columns(string $table): array
    {
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM `$table`");

            $columns = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $field = (string) ($row['Field'] ?? '');

                if ($field !== '') {
                    $columns[$field] = true;
                }
            }

            return $columns;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function timeOrNull($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('H:i:s', $timestamp) : null;
    }
}