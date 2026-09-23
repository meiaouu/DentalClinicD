<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DentistUnavailableDateRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function paginateByDentistId(int $dentistId, int $limit = 15, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT
                unavailable_id,
                dentist_id,
                unavailable_date,
                start_time,
                end_time,
                reason,
                created_at
            FROM dentist_unavailable_dates
            WHERE dentist_id = :dentist_id
            ORDER BY unavailable_date DESC, unavailable_id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':dentist_id', $dentistId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countByDentistId(int $dentistId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dentist_unavailable_dates
            WHERE dentist_id = :dentist_id
        ");
        $stmt->execute([
            'dentist_id' => $dentistId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function getMonthMap(int $dentistId, string $monthStart, string $monthEnd): array
    {
        $stmt = $this->db->prepare("
            SELECT
                unavailable_id,
                dentist_id,
                unavailable_date,
                start_time,
                end_time,
                reason,
                created_at
            FROM dentist_unavailable_dates
            WHERE dentist_id = :dentist_id
              AND unavailable_date BETWEEN :month_start AND :month_end
        ");
        $stmt->execute([
            'dentist_id' => $dentistId,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
        ]);

        $items = $stmt->fetchAll();
        $map = [];

        foreach ($items as $item) {
            $map[$item['unavailable_date']] = $item;
        }

        return $map;
    }

    public function create(
        int $dentistId,
        string $date,
        ?string $startTime,
        ?string $endTime,
        ?string $reason
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO dentist_unavailable_dates (
                dentist_id,
                unavailable_date,
                start_time,
                end_time,
                reason,
                created_at
            ) VALUES (
                :dentist_id,
                :unavailable_date,
                :start_time,
                :end_time,
                :reason,
                NOW()
            )
        ");
        $stmt->execute([
            'dentist_id' => $dentistId,
            'unavailable_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'reason' => $reason,
        ]);
    }

    public function findById(int $unavailableId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM dentist_unavailable_dates
            WHERE unavailable_id = :unavailable_id
            LIMIT 1
        ");
        $stmt->execute([
            'unavailable_id' => $unavailableId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteById(int $unavailableId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM dentist_unavailable_dates
            WHERE unavailable_id = :unavailable_id
        ");
        $stmt->execute([
            'unavailable_id' => $unavailableId,
        ]);
    }
}