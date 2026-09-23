<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ClinicRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getClinicSettings(): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                setting_id,
                clinic_name,
                clinic_email,
                contact_number,
                clinic_location,
                open_time,
                close_time,
                slot_interval_minutes,
                default_no_show_minutes,
                allow_patient_cancel_pending,
                allow_patient_cancel_confirmed
            FROM clinic_settings
            ORDER BY setting_id ASC
            LIMIT 1
        ");
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getClinicScheduleRuleByDay(int $dayOfWeek): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                day_of_week,
                is_open,
                open_time,
                close_time
            FROM clinic_schedule_rules
            WHERE day_of_week = :day_of_week
            LIMIT 1
        ");
        $stmt->execute([
            'day_of_week' => $dayOfWeek,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function hasClinicTimeBlock(
        string $date,
        string $startTime,
        string $endTime
    ): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM schedule_blocks
            WHERE scope = 'clinic'
              AND block_date = :block_date
              AND (
                    is_full_day = 1
                    OR (
                        start_time < :end_time
                        AND end_time > :start_time
                    )
                  )
        ");
        $stmt->execute([
            'block_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}