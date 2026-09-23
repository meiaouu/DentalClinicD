<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

class PatientDashboardRepository
{
    private PDO $db;
    private array $tableCache = [];
    private array $columnCache = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findPatientByUser(array $authUser): ?array
    {
        if (!$this->tableExists('patients')) {
            return null;
        }

        $userId = (int) ($authUser['user_id'] ?? 0);
        $email = strtolower(trim((string) ($authUser['email'] ?? '')));
        $contact = trim((string) ($authUser['contact_number'] ?? ''));

        if ($userId > 0 && $this->tableHasColumn('patients', 'user_id')) {
            $stmt = $this->db->prepare("
                SELECT *
                FROM patients
                WHERE user_id = :user_id
                LIMIT 1
            ");

            $stmt->execute([
                ':user_id' => $userId,
            ]);

            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($patient) {
                return $this->withHistoryStatus($patient);
            }
        }

        /*
            Safe fallback only:
            If no direct user_id link exists, match by email/contact.
            If multiple patient records match, return null to avoid showing another patient's data.
        */
        $patient = $this->findUniquePatientByEmailContact($email, $contact);

        return $patient ? $this->withHistoryStatus($patient) : null;
    }

    private function withHistoryStatus(array $patient): array
    {
        $patientId = (int) ($patient['patient_id'] ?? 0);

        if ($patientId <= 0) {
            return $patient;
        }

        $medical = $this->db->prepare('SELECT COUNT(*) FROM patient_medical_histories WHERE patient_id = :patient_id');
        $medical->execute([':patient_id' => $patientId]);
        $dental = $this->db->prepare('SELECT COUNT(*) FROM patient_dental_histories WHERE patient_id = :patient_id');
        $dental->execute([':patient_id' => $patientId]);

        $patient['medical_history_completed'] = (int) $medical->fetchColumn() > 0;
        $patient['dental_history_completed'] = (int) $dental->fetchColumn() > 0;
        $patient['verification_status'] = (string) ($patient['verification_status'] ?? 'incomplete');

        return $patient;
    }

    public function getDashboardStats(int $patientId, int $userId): array
    {
        $billing = $this->getBillingSummary($patientId);

        return [
            'upcoming_appointments' => $this->countUpcomingAppointments($patientId),
            'pending_requests' => $this->countPendingRequests($patientId),
            'completed_appointments' => $this->countCompletedAppointments($patientId),
            'remaining_balance' => (float) ($billing['remaining_balance'] ?? 0),
            'documents_count' => $this->countDocuments($patientId),
            'unread_notifications' => $this->countUnreadNotifications($userId),
        ];
    }

    public function getUpcomingAppointments(int $patientId, int $limit = 5): array
    {
        if ($patientId <= 0 || !$this->tableExists('appointments')) {
            return [];
        }

        $limit = max(1, min(20, $limit));

        $sql = "
            SELECT
                a.appointment_id,
                a.appointment_code,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.status,
                a.arrival_status,
                s.service_name,
                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name
            FROM appointments a
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            WHERE a.patient_id = :patient_id
              AND a.appointment_date >= CURDATE()
              AND a.status NOT IN ('cancelled', 'rejected', 'completed', 'no_show')
            ORDER BY a.appointment_date ASC, a.start_time ASC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRecentAppointmentRequests(int $patientId, int $limit = 5): array
    {
        if ($patientId <= 0 || !$this->tableExists('appointment_requests')) {
            return [];
        }

        $limit = max(1, min(20, $limit));

        $sql = "
            SELECT
                ar.request_id,
                ar.request_code,
                ar.preferred_date,
                ar.preferred_start_time,
                ar.request_status,
                ar.staff_notes,
                ar.notes,
                s.service_name
            FROM appointment_requests ar
            LEFT JOIN services s ON s.service_id = ar.service_id
            WHERE ar.patient_id = :patient_id
            ORDER BY ar.created_at DESC, ar.request_id DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRecentTreatments(int $patientId, int $limit = 5): array
    {
        if ($patientId <= 0 || !$this->tableExists('treatments')) {
            return [];
        }

        $limit = max(1, min(20, $limit));

        $sql = "
            SELECT
                t.treatment_id,
                t.appointment_id,
                t.treatment_date,
                t.procedure_name,
                t.treated_tooth,
                t.actual_charge,
                t.treatment_status,
                a.appointment_code,
                s.service_name,
                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name
            FROM treatments t
            LEFT JOIN appointments a ON a.appointment_id = t.appointment_id
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN dentists d ON d.dentist_id = t.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            WHERE t.patient_id = :patient_id
            ORDER BY t.treatment_date DESC, t.treatment_id DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getBillingSummary(int $patientId): array
    {
        $summary = [
            'total_billed' => 0.00,
            'total_paid' => 0.00,
            'remaining_balance' => 0.00,
            'recent_records' => [],
        ];

        if ($patientId <= 0 || !$this->tableExists('billings')) {
            return $summary;
        }

        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(total_amount), 0) AS total_billed,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                COALESCE(SUM(balance), 0) AS remaining_balance
            FROM billings
            WHERE patient_id = :patient_id
              AND payment_status <> 'cancelled'
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $summary['total_billed'] = (float) ($row['total_billed'] ?? 0);
        $summary['total_paid'] = (float) ($row['total_paid'] ?? 0);
        $summary['remaining_balance'] = (float) ($row['remaining_balance'] ?? 0);

        $records = $this->db->prepare("
            SELECT
                billing_id,
                billing_number,
                total_amount,
                amount_paid,
                balance,
                payment_status,
                created_at
            FROM billings
            WHERE patient_id = :patient_id
            ORDER BY created_at DESC, billing_id DESC
            LIMIT 5
        ");

        $records->execute([
            ':patient_id' => $patientId,
        ]);

        $summary['recent_records'] = $records->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $summary;
    }

    public function getRecentDocuments(int $patientId, int $limit = 5): array
    {
        if ($patientId <= 0 || !$this->tableExists('attachments')) {
            return [];
        }

        $limit = max(1, min(20, $limit));

        $sql = "
            SELECT
                attachment_id,
                file_category,
                original_file_name,
                mime_type,
                file_size,
                description,
                is_sensitive,
                access_level,
                created_at
            FROM attachments
            WHERE patient_id = :patient_id
              AND COALESCE(is_sensitive, 0) = 0
            ORDER BY created_at DESC, attachment_id DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRecentNotifications(int $userId, int $limit = 5): array
    {
        if ($userId <= 0 || !$this->tableExists('notifications')) {
            return [];
        }

        $limit = max(1, min(20, $limit));

        $stmt = $this->db->prepare("
            SELECT
                notification_id,
                title,
                message,
                link_url,
                target_url,
                is_read,
                created_at
            FROM notifications
            WHERE recipient_user_id = :user_id
               OR user_id = :user_id
            ORDER BY created_at DESC, notification_id DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function logAudit(
        int $userId,
        string $module,
        string $action,
        string $targetType,
        ?int $targetId,
        string $description,
        string $ipAddress,
        string $userAgent
    ): void {
        if (!$this->tableExists('audit_logs')) {
            return;
        }

        try {
            $data = [];

            if ($this->tableHasColumn('audit_logs', 'user_id')) {
                $data['user_id'] = $userId > 0 ? $userId : null;
            }

            if ($this->tableHasColumn('audit_logs', 'action')) {
                $data['action'] = $module . '.' . $action;
            }

            if ($this->tableHasColumn('audit_logs', 'entity_type')) {
                $data['entity_type'] = $targetType;
            }

            if ($this->tableHasColumn('audit_logs', 'entity_id')) {
                $data['entity_id'] = $targetId;
            }

            if ($this->tableHasColumn('audit_logs', 'module_name')) {
                $data['module_name'] = $module;
            }

            if ($this->tableHasColumn('audit_logs', 'action_name')) {
                $data['action_name'] = $action;
            }

            if ($this->tableHasColumn('audit_logs', 'record_type')) {
                $data['record_type'] = $targetType;
            }

            if ($this->tableHasColumn('audit_logs', 'record_id')) {
                $data['record_id'] = $targetId !== null ? (string) $targetId : null;
            }

            if ($this->tableHasColumn('audit_logs', 'description')) {
                $data['description'] = $description;
            }

            if ($this->tableHasColumn('audit_logs', 'ip_address')) {
                $data['ip_address'] = $ipAddress;
            }

            if ($this->tableHasColumn('audit_logs', 'user_agent')) {
                $data['user_agent'] = $userAgent;
            }

            if (empty($data)) {
                return;
            }

            $columns = array_keys($data);
            $placeholders = array_map(static fn ($column) => ':' . $column, $columns);

            $sql = 'INSERT INTO audit_logs (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

            $stmt = $this->db->prepare($sql);

            foreach ($data as $column => $value) {
                $stmt->bindValue(':' . $column, $value);
            }

            $stmt->execute();
        } catch (Throwable $e) {
            error_log('[PatientDashboardRepository::logAudit] ' . $e->getMessage());
        }
    }

    private function findUniquePatientByEmailContact(string $email, string $contact): ?array
    {
        if ($email === '' || !$this->tableHasColumn('patients', 'email')) {
            return null;
        }

        $params = [
            ':email' => $email,
        ];

        $where = 'LOWER(email) = :email';

        if ($contact !== '' && $this->tableHasColumn('patients', 'contact_number')) {
            $phones = array_values(array_unique(array_filter([
                $contact,
                $this->normalizePhilippineMobile($contact),
                $this->toLocalPhilippineMobile($contact),
            ])));

            if (!empty($phones)) {
                $phonePlaceholders = [];

                foreach ($phones as $index => $phone) {
                    $key = ':phone_' . $index;
                    $phonePlaceholders[] = $key;
                    $params[$key] = $phone;
                }

                $where .= ' AND contact_number IN (' . implode(', ', $phonePlaceholders) . ')';
            }
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM patients
            WHERE {$where}
            ORDER BY patient_id DESC
            LIMIT 2
        ");

        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return count($rows) === 1 ? $rows[0] : null;
    }

    private function countUpcomingAppointments(int $patientId): int
    {
        if (!$this->tableExists('appointments')) {
            return 0;
        }

        return $this->countBySql("
            SELECT COUNT(*)
            FROM appointments
            WHERE patient_id = :patient_id
              AND appointment_date >= CURDATE()
              AND status NOT IN ('cancelled', 'rejected', 'completed', 'no_show')
        ", [
            ':patient_id' => $patientId,
        ]);
    }

    private function countPendingRequests(int $patientId): int
    {
        if (!$this->tableExists('appointment_requests')) {
            return 0;
        }

        return $this->countBySql("
            SELECT COUNT(*)
            FROM appointment_requests
            WHERE patient_id = :patient_id
              AND request_status = 'pending'
        ", [
            ':patient_id' => $patientId,
        ]);
    }

    private function countCompletedAppointments(int $patientId): int
    {
        if (!$this->tableExists('appointments')) {
            return 0;
        }

        return $this->countBySql("
            SELECT COUNT(*)
            FROM appointments
            WHERE patient_id = :patient_id
              AND status = 'completed'
        ", [
            ':patient_id' => $patientId,
        ]);
    }

    private function countDocuments(int $patientId): int
    {
        if (!$this->tableExists('attachments')) {
            return 0;
        }

        return $this->countBySql("
            SELECT COUNT(*)
            FROM attachments
            WHERE patient_id = :patient_id
              AND COALESCE(is_sensitive, 0) = 0
        ", [
            ':patient_id' => $patientId,
        ]);
    }

    private function countUnreadNotifications(int $userId): int
    {
        if ($userId <= 0 || !$this->tableExists('notifications')) {
            return 0;
        }

        return $this->countBySql("
            SELECT COUNT(*)
            FROM notifications
            WHERE (recipient_user_id = :user_id OR user_id = :user_id)
              AND is_read = 0
        ", [
            ':user_id' => $userId,
        ]);
    }

    private function countBySql(string $sql, array $params): int
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[PatientDashboardRepository::countBySql] ' . $e->getMessage());
            return 0;
        }
    }

    private function normalizePhilippineMobile(string $phone): string
    {
        $phone = preg_replace('/[\s-]+/', '', trim($phone)) ?? '';

        if (preg_match('/^09\d{9}$/', $phone)) {
            return '+63' . substr($phone, 1);
        }

        if (preg_match('/^639\d{9}$/', $phone)) {
            return '+' . $phone;
        }

        return $phone;
    }

    private function toLocalPhilippineMobile(string $phone): string
    {
        $phone = $this->normalizePhilippineMobile($phone);

        if (preg_match('/^\+639\d{9}$/', $phone)) {
            return '0' . substr($phone, 3);
        }

        return $phone;
    }

    private function tableExists(string $table): bool
    {
        if (isset($this->tableCache[$table])) {
            return $this->tableCache[$table];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ");

            $stmt->execute([
                ':table_name' => $table,
            ]);

            return $this->tableCache[$table] = ((int) $stmt->fetchColumn() > 0);
        } catch (Throwable $e) {
            error_log('[PatientDashboardRepository::tableExists] ' . $e->getMessage());

            return $this->tableCache[$table] = false;
        }
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;

        if (isset($this->columnCache[$cacheKey])) {
            return $this->columnCache[$cacheKey];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
                  AND COLUMN_NAME = :column_name
            ");

            $stmt->execute([
                ':table_name' => $table,
                ':column_name' => $column,
            ]);

            return $this->columnCache[$cacheKey] = ((int) $stmt->fetchColumn() > 0);
        } catch (Throwable $e) {
            error_log('[PatientDashboardRepository::tableHasColumn] ' . $e->getMessage());

            return $this->columnCache[$cacheKey] = false;
        }
    }
}