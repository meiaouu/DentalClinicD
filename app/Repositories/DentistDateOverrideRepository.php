<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DentistDateOverrideRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function paginateByDentistId(int $dentistId, int $limit = 10, int $offset = 0): array
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
            ORDER BY override_date DESC, override_id DESC
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
            FROM dentist_date_overrides
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
                override_id,
                dentist_id,
                override_date,
                is_available,
                start_time,
                end_time,
                reason
            FROM dentist_date_overrides
            WHERE dentist_id = :dentist_id
              AND override_date BETWEEN :month_start AND :month_end
        ");
        $stmt->execute([
            'dentist_id' => $dentistId,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
        ]);

        $items = $stmt->fetchAll();
        $map = [];

        foreach ($items as $item) {
            $map[$item['override_date']] = $item;
        }

        return $map;
    }

    public function updateOrCreate(
        int $dentistId,
        string $date,
        bool $isAvailable,
        ?string $startTime,
        ?string $endTime,
        ?string $reason
    ): void {
        $findStmt = $this->db->prepare("
            SELECT override_id
            FROM dentist_date_overrides
            WHERE dentist_id = :dentist_id
              AND override_date = :override_date
            LIMIT 1
        ");
        $findStmt->execute([
            'dentist_id' => $dentistId,
            'override_date' => $date,
        ]);

        $existingId = $findStmt->fetchColumn();

        if ($existingId) {
            $stmt = $this->db->prepare("
                UPDATE dentist_date_overrides
                SET is_available = :is_available,
                    start_time = :start_time,
                    end_time = :end_time,
                    reason = :reason
                WHERE override_id = :override_id
            ");
            $stmt->execute([
                'is_available' => $isAvailable ? 1 : 0,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'reason' => $reason,
                'override_id' => $existingId,
            ]);
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO dentist_date_overrides (
                    dentist_id,
                    override_date,
                    is_available,
                    start_time,
                    end_time,
                    reason
                ) VALUES (
                    :dentist_id,
                    :override_date,
                    :is_available,
                    :start_time,
                    :end_time,
                    :reason
                )
            ");
            $stmt->execute([
                'dentist_id' => $dentistId,
                'override_date' => $date,
                'is_available' => $isAvailable ? 1 : 0,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'reason' => $reason,
            ]);
        }
    }

    public function findById(int $overrideId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM dentist_date_overrides
            WHERE override_id = :override_id
            LIMIT 1
        ");
        $stmt->execute([
            'override_id' => $overrideId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteById(int $overrideId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM dentist_date_overrides
            WHERE override_id = :override_id
        ");
        $stmt->execute([
            'override_id' => $overrideId,
        ]);
    }

    public function countAvailableOverrides(int $dentistId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dentist_date_overrides
            WHERE dentist_id = :dentist_id
              AND is_available = 1
        ");
        $stmt->execute([
            'dentist_id' => $dentistId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function countUnavailableOverrides(int $dentistId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dentist_date_overrides
            WHERE dentist_id = :dentist_id
              AND is_available = 0
        ");
        $stmt->execute([
            'dentist_id' => $dentistId,
        ]);

        return (int) $stmt->fetchColumn();
    }
}