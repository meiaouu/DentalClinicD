<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

class AdminDashboardRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function stats(): array
    {
        return [
            'total_patients' => $this->countPatients(),
            'total_dentists' => $this->countDentists(),
            'total_staff' => $this->countUsersByRole(['staff']),
            'today_appointments' => $this->countTodayAppointments(),
            'pending_requests' => $this->countPendingRequests(),
            'active_services' => $this->countActiveServices(),
            'completed_today' => $this->countCompletedToday(),
            'total_users' => $this->countUsers(),
        ];
    }

    public function recentAppointments(int $limit = 8): array
    {
        if (!$this->tableExists('appointments')) {
            return [];
        }

        $limit = max(1, min(50, $limit));

        $sql = "
            SELECT
                a.appointment_id,
                a.appointment_code,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.status,
                a.created_at,
                s.service_name,

                TRIM(CONCAT(
                    COALESCE(NULLIF(p.first_name, ''), ar.guest_first_name, ''),
                    ' ',
                    COALESCE(NULLIF(p.last_name, ''), ar.guest_last_name, '')
                )) AS patient_name,

                TRIM(CONCAT(
                    COALESCE(du.first_name, ''),
                    ' ',
                    COALESCE(du.last_name, '')
                )) AS dentist_name
            FROM appointments a
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN patients p ON p.patient_id = a.patient_id
            LEFT JOIN appointment_requests ar ON ar.request_id = a.request_id
            LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            ORDER BY
                COALESCE(a.created_at, a.appointment_date) DESC,
                a.appointment_date DESC,
                a.start_time DESC,
                a.appointment_id DESC
            LIMIT :limit
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as &$row) {
                $row['patient_name'] = trim((string) ($row['patient_name'] ?? '')) !== ''
                    ? trim((string) $row['patient_name'])
                    : 'Unknown Patient';

                $row['dentist_name'] = trim((string) ($row['dentist_name'] ?? '')) !== ''
                    ? 'Dr. ' . trim((string) $row['dentist_name'])
                    : 'Unassigned Dentist';
            }

            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function recentAuditLogs(int $limit = 8): array
    {
        if (!$this->tableExists('audit_logs')) {
            return [];
        }

        $limit = max(1, min(50, $limit));

        $columns = $this->tableColumns('audit_logs');

        $actionExpr = isset($columns['action'])
            ? 'al.action'
            : (isset($columns['action_name']) ? 'al.action_name' : "''");

        $entityExpr = isset($columns['entity_type'])
            ? 'al.entity_type'
            : (isset($columns['record_type']) ? 'al.record_type' : "''");

        $entityIdExpr = isset($columns['entity_id'])
            ? 'al.entity_id'
            : (isset($columns['record_id']) ? 'al.record_id' : 'NULL');

        $moduleExpr = isset($columns['module_name'])
            ? 'al.module_name'
            : "''";

        $descriptionExpr = isset($columns['description'])
            ? 'al.description'
            : "''";

        $createdExpr = isset($columns['created_at'])
            ? 'al.created_at'
            : 'NULL';

        $idOrder = isset($columns['audit_id'])
            ? 'al.audit_id DESC'
            : 'al.created_at DESC';

        $sql = "
            SELECT
                {$actionExpr} AS action,
                {$entityExpr} AS entity_type,
                {$entityIdExpr} AS entity_id,
                {$moduleExpr} AS module_name,
                {$descriptionExpr} AS description,
                {$createdExpr} AS created_at,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS user_name
            FROM audit_logs al
            LEFT JOIN users u ON u.user_id = al.user_id
            ORDER BY
                al.created_at DESC,
                {$idOrder}
            LIMIT :limit
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function topServices(int $limit = 5): array
    {
        if (!$this->tableExists('services')) {
            return [];
        }

        $limit = max(1, min(20, $limit));

        $sql = "
            SELECT
                s.service_id,
                s.service_name,
                COUNT(a.appointment_id) AS appointment_count
            FROM services s
            LEFT JOIN appointments a ON a.service_id = s.service_id
            GROUP BY s.service_id, s.service_name
            ORDER BY appointment_count DESC, s.service_name ASC
            LIMIT :limit
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function dentistWorkload(int $limit = 5): array
    {
        if (!$this->tableExists('dentists') || !$this->tableExists('users')) {
            return [];
        }

        $limit = max(1, min(20, $limit));

        $activeCondition = $this->tableHasColumn('dentists', 'is_active')
            ? 'AND d.is_active = 1'
            : '';

        $sql = "
            SELECT
                d.dentist_id,
                d.specialization,
                TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS dentist_name,
                COUNT(CASE WHEN a.appointment_date = CURDATE() THEN 1 END) AS today_count,
                COUNT(CASE WHEN a.appointment_date >= CURDATE() THEN 1 END) AS upcoming_count
            FROM dentists d
            INNER JOIN users u ON u.user_id = d.user_id
            LEFT JOIN appointments a ON a.dentist_id = d.dentist_id
                AND a.status NOT IN ('cancelled', 'rejected', 'no_show')
            WHERE 1 = 1
            {$activeCondition}
            GROUP BY d.dentist_id, d.specialization, u.first_name, u.last_name
            ORDER BY today_count DESC, upcoming_count DESC, dentist_name ASC
            LIMIT :limit
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as &$row) {
                $name = trim((string) ($row['dentist_name'] ?? ''));
                $row['dentist_name'] = $name !== '' ? 'Dr. ' . $name : 'Unknown Dentist';
                $row['specialization'] = trim((string) ($row['specialization'] ?? '')) !== ''
                    ? trim((string) $row['specialization'])
                    : 'General Dentistry';
            }

            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function countPatients(): int
    {
        return $this->countTable('patients');
    }

    private function countDentists(): int
    {
        if (!$this->tableExists('dentists')) {
            return 0;
        }

        $where = $this->tableHasColumn('dentists', 'is_active')
            ? 'WHERE is_active = 1'
            : '';

        return $this->countSql("SELECT COUNT(*) FROM dentists {$where}");
    }

    private function countUsers(): int
    {
        return $this->countTable('users');
    }

    private function countTodayAppointments(): int
    {
        if (!$this->tableExists('appointments')) {
            return 0;
        }

        return $this->countSql("
            SELECT COUNT(*)
            FROM appointments
            WHERE appointment_date = CURDATE()
        ");
    }

    private function countCompletedToday(): int
    {
        if (!$this->tableExists('appointments')) {
            return 0;
        }

        return $this->countSql("
            SELECT COUNT(*)
            FROM appointments
            WHERE appointment_date = CURDATE()
              AND status = 'completed'
        ");
    }

    private function countPendingRequests(): int
    {
        if (!$this->tableExists('appointment_requests')) {
            return 0;
        }

        return $this->countSql("
            SELECT COUNT(*)
            FROM appointment_requests
            WHERE request_status IN ('pending', 'under_review')
        ");
    }

    private function countActiveServices(): int
    {
        if (!$this->tableExists('services')) {
            return 0;
        }

        if ($this->tableHasColumn('services', 'is_active')) {
            return $this->countSql("
                SELECT COUNT(*)
                FROM services
                WHERE is_active = 1
            ");
        }

        if ($this->tableHasColumn('services', 'status')) {
            return $this->countSql("
                SELECT COUNT(*)
                FROM services
                WHERE status = 'active'
            ");
        }

        return $this->countTable('services');
    }

    private function countUsersByRole(array $roleNames): int
    {
        if (!$this->tableExists('users') || !$this->tableExists('roles')) {
            return 0;
        }

        $roleNames = array_values(array_filter(array_map('trim', $roleNames)));

        if (empty($roleNames)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($roleNames), '?'));

        $activeUserCondition = $this->tableHasColumn('users', 'is_active')
            ? 'AND u.is_active = 1'
            : '';

        try {
            if ($this->tableExists('user_roles')) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(DISTINCT u.user_id)
                    FROM users u
                    INNER JOIN user_roles ur ON ur.user_id = u.user_id
                    INNER JOIN roles r ON r.role_id = ur.role_id
                    WHERE r.role_name IN ($placeholders)
                    {$activeUserCondition}
                ");

                $stmt->execute($roleNames);

                return (int) $stmt->fetchColumn();
            }

            if ($this->tableHasColumn('users', 'role_id')) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(DISTINCT u.user_id)
                    FROM users u
                    INNER JOIN roles r ON r.role_id = u.role_id
                    WHERE r.role_name IN ($placeholders)
                    {$activeUserCondition}
                ");

                $stmt->execute($roleNames);

                return (int) $stmt->fetchColumn();
            }

            return 0;
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function countTable(string $table): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        return $this->countSql("SELECT COUNT(*) FROM `$table`");
    }

    private function countSql(string $sql): int
    {
        try {
            return (int) $this->db->query($sql)->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
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

            $cache[$table] = (int) $stmt->fetchColumn() > 0;

            return $cache[$table];
        } catch (Throwable $e) {
            $cache[$table] = false;

            return false;
        }
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        static $cache = [];

        $key = $table . '.' . $column;

        if (isset($cache[$key])) {
            return $cache[$key];
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

            $cache[$key] = (int) $stmt->fetchColumn() > 0;

            return $cache[$key];
        } catch (Throwable $e) {
            $cache[$key] = false;

            return false;
        }
    }

    private function tableColumns(string $table): array
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
}