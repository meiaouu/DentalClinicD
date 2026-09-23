<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ScheduleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getActiveDentists(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                d.dentist_id,
                d.user_id,
                d.dentist_code,
                d.is_active,
                u.first_name,
                u.last_name
            FROM dentists d
            INNER JOIN users u ON u.user_id = d.user_id
            WHERE d.is_active = 1
            ORDER BY u.first_name ASC, u.last_name ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getDentistScheduleByDay(int $dentistId, int $dayOfWeek): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                schedule_id,
                dentist_id,
                day_of_week,
                start_time,
                end_time,
                max_patients,
                is_available
            FROM dentist_schedules
            WHERE dentist_id = :dentist_id
              AND day_of_week = :day_of_week
            LIMIT 1
        ");
        $stmt->execute([
            'dentist_id' => $dentistId,
            'day_of_week' => $dayOfWeek,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getDentistDateOverride(int $dentistId, string $date): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                override_id,
                dentist_id,
                override_date,
                is_available,
                start_time,
                end_time,
                reason
            FROM dentist_date_overrides
            WHERE dentist_id = :dentist_id
              AND override_date = :override_date
            LIMIT 1
        ");
        $stmt->execute([
            'dentist_id' => $dentistId,
            'override_date' => $date,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getDentistUnavailableDate(int $dentistId, string $date): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                unavailable_id,
                dentist_id,
                unavailable_date,
                start_time,
                end_time,
                reason
            FROM dentist_unavailable_dates
            WHERE dentist_id = :dentist_id
              AND unavailable_date = :unavailable_date
            LIMIT 1
        ");
        $stmt->execute([
            'dentist_id' => $dentistId,
            'unavailable_date' => $date,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function hasDentistScheduleBlock(
        int $dentistId,
        string $date,
        string $startTime,
        string $endTime
    ): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dentist_schedule_blocks
            WHERE dentist_id = :dentist_id
              AND block_date = :block_date
              AND (
                    (start_time IS NULL AND end_time IS NULL)
                    OR (
                        start_time < :end_time
                        AND end_time > :start_time
                    )
                  )
        ");
        $stmt->execute([
            'dentist_id' => $dentistId,
            'block_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}