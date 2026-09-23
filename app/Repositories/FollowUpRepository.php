<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class FollowUpRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO follow_ups (
                patient_id,
                dentist_id,
                treatment_id,
                recommended_date,
                reason,
                remarks,
                status,
                created_at,
                updated_at
            ) VALUES (
                :patient_id,
                :dentist_id,
                :treatment_id,
                :recommended_date,
                :reason,
                :remarks,
                :status,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            'patient_id' => $data['patient_id'],
            'dentist_id' => $data['dentist_id'],
            'treatment_id' => $data['treatment_id'],
            'recommended_date' => $data['recommended_date'],
            'reason' => $data['reason'],
            'remarks' => $data['remarks'],
            'status' => $data['status'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getByPatientId(int $patientId, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT
                follow_up_id,
                patient_id,
                dentist_id,
                treatment_id,
                recommended_date,
                reason,
                remarks,
                status,
                created_at,
                updated_at
            FROM follow_ups
            WHERE patient_id = :patient_id
            ORDER BY recommended_date DESC, follow_up_id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findDetailedById(int $followUpId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                f.*,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name,
                t.procedure_name
            FROM follow_ups f
            LEFT JOIN patients p ON p.patient_id = f.patient_id
            LEFT JOIN dentists d ON d.dentist_id = f.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            LEFT JOIN treatments t ON t.treatment_id = f.treatment_id
            WHERE f.follow_up_id = :follow_up_id
            LIMIT 1
        ");
        $stmt->execute([
            'follow_up_id' => $followUpId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function paginateForStaff(?string $status = null, int $limit = 15, int $offset = 0): array
    {
        $sql = "
            SELECT
                f.*,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name,
                t.procedure_name
            FROM follow_ups f
            LEFT JOIN patients p ON p.patient_id = f.patient_id
            LEFT JOIN treatments t ON t.treatment_id = f.treatment_id
        ";

        $params = [];

        if ($status !== null && $status !== '') {
            $sql .= " WHERE f.status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY f.recommended_date DESC, f.follow_up_id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        if (isset($params['status'])) {
            $stmt->bindValue(':status', $params['status']);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countForStaff(?string $status = null): int
    {
        $sql = "SELECT COUNT(*) FROM follow_ups";
        $stmt = null;

        if ($status !== null && $status !== '') {
            $sql .= " WHERE status = :status";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['status' => $status]);
        } else {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }

        return (int) $stmt->fetchColumn();
    }

    public function updateStatus(int $followUpId, string $status, ?string $remarks = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE follow_ups
            SET status = :status,
                remarks = CASE
                    WHEN :remarks IS NOT NULL AND :remarks <> ''
                    THEN CONCAT(COALESCE(remarks, ''), '\n\nUpdate: ', :remarks)
                    ELSE remarks
                END,
                updated_at = NOW()
            WHERE follow_up_id = :follow_up_id
        ");
        $stmt->execute([
            'status' => $status,
            'remarks' => $remarks,
            'follow_up_id' => $followUpId,
        ]);
    }
}