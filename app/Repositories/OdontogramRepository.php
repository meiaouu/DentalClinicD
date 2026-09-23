<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class OdontogramRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function replaceByExaminationId(int $examinationId, array $entries): void
    {
        if ($examinationId <= 0) {
            throw new \RuntimeException('Invalid examination record.');
        }

        $deleteStmt = $this->db->prepare("
            DELETE FROM odontogram_entries
            WHERE examination_id = :examination_id
        ");

        $deleteStmt->execute([
            ':examination_id' => $examinationId,
        ]);

        if (empty($entries)) {
            return;
        }

        $insertStmt = $this->db->prepare("
            INSERT INTO odontogram_entries (
                examination_id,
                tooth_number,
                condition_code,
                surface,
                remarks,
                created_at
            ) VALUES (
                :examination_id,
                :tooth_number,
                :condition_code,
                :surface,
                :remarks,
                NOW()
            )
        ");

        foreach ($entries as $entry) {
            $toothNumber = trim((string) ($entry['tooth_number'] ?? ''));
            $conditionCode = trim((string) ($entry['condition_code'] ?? ''));
            $surface = trim((string) ($entry['surface'] ?? ''));
            $remarks = trim((string) ($entry['remarks'] ?? ''));

            if ($toothNumber === '' || $conditionCode === '') {
                continue;
            }

            $insertStmt->execute([
                ':examination_id' => $examinationId,
                ':tooth_number' => $toothNumber,
                ':condition_code' => $conditionCode,
                ':surface' => $surface !== '' ? $surface : null,
                ':remarks' => $remarks !== '' ? $remarks : null,
            ]);
        }
    }

    public function findByExaminationId(int $examinationId): array
    {
        return $this->getByExaminationId($examinationId);
    }

    public function getByExaminationId(int $examinationId): array
    {
        if ($examinationId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                odontogram_id,
                examination_id,
                tooth_number,
                condition_code,
                surface,
                remarks,
                created_at
            FROM odontogram_entries
            WHERE examination_id = :examination_id
            ORDER BY tooth_number ASC, odontogram_id ASC
        ");

        $stmt->execute([
            ':examination_id' => $examinationId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}