<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class AppointmentReminderRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAppointmentsForReminderDate(string $date, int $daysBefore): array
    {
        $date = $this->validDateOrToday($date);
        $daysBefore = $this->normalizeDaysBefore($daysBefore);
        $consentExpression = $this->notificationConsentExpression();

        $sql = "
            SELECT
                a.*,

                p.patient_id,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,
                p.email AS patient_email,
                {$consentExpression} AS notification_allowed,

                s.service_name,

                d.dentist_id,
                du.first_name AS dentist_first_name,
                du.middle_name AS dentist_middle_name,
                du.last_name AS dentist_last_name

            FROM appointments a
            INNER JOIN patients p ON p.patient_id = a.patient_id
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            WHERE a.status = 'confirmed'
              AND a.patient_id IS NOT NULL
              AND a.appointment_date = DATE_ADD(:run_date, INTERVAL {$daysBefore} DAY)
            ORDER BY
                a.appointment_date ASC,
                a.start_time ASC,
                a.appointment_id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':run_date' => $date,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findUpcomingConfirmedAppointments(array $filters): array
    {
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        if ($dateFrom === '') {
            $dateFrom = date('Y-m-d');
        }

        if ($dateTo === '') {
            $dateTo = date('Y-m-d', strtotime('+14 days'));
        }

        $dateFrom = $this->validDateOrToday($dateFrom);
        $dateTo = $this->validDateOrToday($dateTo);

        if ($dateTo < $dateFrom) {
            $dateTo = $dateFrom;
        }

        $dentistId = (int) ($filters['dentist_id'] ?? 0);
        $serviceId = (int) ($filters['service_id'] ?? 0);
        $reminderType = trim((string) ($filters['reminder_type'] ?? ''));

        $consentExpression = $this->notificationConsentExpression();

        $sql = "
            SELECT
                a.*,

                p.patient_id,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,
                p.email AS patient_email,
                {$consentExpression} AS notification_allowed,

                s.service_name,

                d.dentist_id,
                du.first_name AS dentist_first_name,
                du.middle_name AS dentist_middle_name,
                du.last_name AS dentist_last_name,

                reminder_summary.appointment_3_days_status,
                reminder_summary.appointment_2_days_status,
                reminder_summary.appointment_1_day_status

            FROM appointments a
            INNER JOIN patients p ON p.patient_id = a.patient_id
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            LEFT JOIN (
                SELECT
                    appointment_id,
                    MAX(CASE WHEN reminder_type = 'appointment_3_days' THEN status END) AS appointment_3_days_status,
                    MAX(CASE WHEN reminder_type = 'appointment_2_days' THEN status END) AS appointment_2_days_status,
                    MAX(CASE WHEN reminder_type = 'appointment_1_day' THEN status END) AS appointment_1_day_status
                FROM appointment_reminder_logs
                GROUP BY appointment_id
            ) reminder_summary ON reminder_summary.appointment_id = a.appointment_id
            WHERE a.status = 'confirmed'
              AND a.patient_id IS NOT NULL
              AND a.appointment_date BETWEEN :date_from AND :date_to
        ";

        $params = [
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo,
        ];

        if ($dentistId > 0) {
            $sql .= " AND a.dentist_id = :dentist_id ";
            $params[':dentist_id'] = $dentistId;
        }

        if ($serviceId > 0) {
            $sql .= " AND a.service_id = :service_id ";
            $params[':service_id'] = $serviceId;
        }

        if ($reminderType !== '') {
            $daysBefore = $this->daysBeforeFromReminderType($reminderType);

            if ($daysBefore > 0) {
                $sql .= " AND DATEDIFF(a.appointment_date, CURDATE()) = :days_before ";
                $params[':days_before'] = $daysBefore;
            }
        }

        $sql .= "
            ORDER BY
                a.appointment_date ASC,
                a.start_time ASC,
                a.appointment_id ASC
            LIMIT 500
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findAppointmentById(int $appointmentId): ?array
    {
        if ($appointmentId <= 0) {
            return null;
        }

        $consentExpression = $this->notificationConsentExpression();

        $stmt = $this->db->prepare("
            SELECT
                a.*,

                p.patient_id,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,
                p.email AS patient_email,
                {$consentExpression} AS notification_allowed,

                s.service_name,

                d.dentist_id,
                du.first_name AS dentist_first_name,
                du.middle_name AS dentist_middle_name,
                du.last_name AS dentist_last_name

            FROM appointments a
            INNER JOIN patients p ON p.patient_id = a.patient_id
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            WHERE a.appointment_id = :appointment_id
            LIMIT 1
        ");

        $stmt->execute([
            ':appointment_id' => $appointmentId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function hasReminderBeenSent(int $appointmentId, string $reminderType): bool
    {
        if ($appointmentId <= 0 || $reminderType === '') {
            return false;
        }

        $uniqueKey = $this->buildUniqueKey($appointmentId, $reminderType);

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointment_reminder_logs
            WHERE unique_key = :unique_key
            LIMIT 1
        ");

        $stmt->execute([
            ':unique_key' => $uniqueKey,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function createReminderLog(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO appointment_reminder_logs (
                appointment_id,
                patient_id,
                message_id,
                reminder_type,
                reminder_date,
                status,
                unique_key,
                created_at
            ) VALUES (
                :appointment_id,
                :patient_id,
                :message_id,
                :reminder_type,
                :reminder_date,
                :status,
                :unique_key,
                NOW()
            )
        ");

        $stmt->execute([
            ':appointment_id' => (int) $data['appointment_id'],
            ':patient_id' => (int) $data['patient_id'],
            ':message_id' => !empty($data['message_id']) ? (int) $data['message_id'] : null,
            ':reminder_type' => (string) $data['reminder_type'],
            ':reminder_date' => (string) $data['reminder_date'],
            ':status' => (string) $data['status'],
            ':unique_key' => (string) $data['unique_key'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getActiveDentists(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                d.dentist_id,
                d.dentist_code,
                u.first_name,
                u.middle_name,
                u.last_name
            FROM dentists d
            INNER JOIN users u ON u.user_id = d.user_id
            WHERE d.is_active = 1
            ORDER BY u.last_name ASC, u.first_name ASC
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getActiveServices(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                service_id,
                service_name
            FROM services
            WHERE is_active = 1
            ORDER BY service_name ASC
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getClinicSettings(): array
    {
        if (!$this->tableExists('clinic_settings')) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM clinic_settings
            ORDER BY setting_id ASC
            LIMIT 1
        ");

        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function buildUniqueKey(int $appointmentId, string $reminderType): string
    {
        $suffix = match ($reminderType) {
            'appointment_3_days' => '3_days',
            'appointment_2_days' => '2_days',
            'appointment_1_day' => '1_day',
            default => 'unknown',
        };

        return 'appointment_' . $appointmentId . '_' . $suffix;
    }

    private function validDateOrToday(string $date): string
    {
        $date = trim($date);

        if ($date === '') {
            return date('Y-m-d');
        }

        $parsed = date_create_from_format('Y-m-d', $date);
        $errors = date_get_last_errors();

        if (
            !$parsed ||
            $parsed->format('Y-m-d') !== $date ||
            ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return date('Y-m-d');
        }

        return $date;
    }

    private function normalizeDaysBefore(int $daysBefore): int
    {
        return in_array($daysBefore, [1, 2, 3], true)
            ? $daysBefore
            : 1;
    }

    private function daysBeforeFromReminderType(string $reminderType): int
    {
        return match ($reminderType) {
            'appointment_3_days' => 3,
            'appointment_2_days' => 2,
            'appointment_1_day' => 1,
            default => 0,
        };
    }

    private function notificationConsentExpression(): string
    {
        $candidateColumns = [
            'notification_consent',
            'notifications_allowed',
            'allow_notifications',
            'privacy_consent',
            'sms_consent',
            'email_consent',
        ];

        foreach ($candidateColumns as $column) {
            if ($this->tableHasColumn('patients', $column)) {
                $safeColumn = str_replace('`', '', $column);

                return "
                    CASE
                        WHEN p.`{$safeColumn}` IS NULL THEN 1
                        WHEN LOWER(TRIM(CAST(p.`{$safeColumn}` AS CHAR))) IN ('1','yes','true','allowed','accepted','agree','agreed') THEN 1
                        WHEN LOWER(TRIM(CAST(p.`{$safeColumn}` AS CHAR))) IN ('0','no','false','denied','declined','disagree') THEN 0
                        ELSE 1
                    END
                ";
            }
        }

        return '1';
    }

    private function tableExists(string $tableName): bool
    {
        static $cache = [];

        $key = strtolower($tableName);

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ");

            $stmt->execute([
                ':table_name' => $tableName,
            ]);

            $cache[$key] = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }

    private function tableHasColumn(string $tableName, string $columnName): bool
    {
        static $cache = [];

        $key = strtolower($tableName . '.' . $columnName);

        if (array_key_exists($key, $cache)) {
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
                ':table_name' => $tableName,
                ':column_name' => $columnName,
            ]);

            $cache[$key] = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }
}