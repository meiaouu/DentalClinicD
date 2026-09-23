<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DentistScheduleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getByDentistId(int $dentistId): array
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
            ORDER BY day_of_week ASC
        ");
        $stmt->execute([
            'dentist_id' => $dentistId,
        ]);

        return $stmt->fetchAll();
    }

    public function updateOrCreate(
        int $dentistId,
        int $dayOfWeek,
        bool $isAvailable,
        ?string $startTime,
        ?string $endTime,
        int $maxPatients
    ): void {
        $existsStmt = $this->db->prepare("
            SELECT schedule_id
            FROM dentist_schedules
            WHERE dentist_id = :dentist_id
              AND day_of_week = :day_of_week
            LIMIT 1
        ");
        $existsStmt->execute([
            'dentist_id' => $dentistId,
            'day_of_week' => $dayOfWeek,
        ]);

        $existingId = $existsStmt->fetchColumn();

        if ($existingId) {
            $stmt = $this->db->prepare("
                UPDATE dentist_schedules
                SET is_available = :is_available,
                    start_time = :start_time,
                    end_time = :end_time,
                    max_patients = :max_patients
                WHERE schedule_id = :schedule_id
            ");
            $stmt->execute([
                'is_available' => $isAvailable ? 1 : 0,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'max_patients' => $maxPatients,
                'schedule_id' => $existingId,
            ]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO dentist_schedules (
                    dentist_id,
                    day_of_week,
                    start_time,
                    end_time,
                    max_patients,
                    is_available
                ) VALUES (
                    :dentist_id,
                    :day_of_week,
                    :start_time,
                    :end_time,
                    :max_patients,
                    :is_available
                )
            ");
            $stmt->execute([
                'dentist_id' => $dentistId,
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'max_patients' => $maxPatients,
                'is_available' => $isAvailable ? 1 : 0,
            ]);
        }
    }
}