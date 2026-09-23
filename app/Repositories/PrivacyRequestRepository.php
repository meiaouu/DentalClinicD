<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PrivacyRequestRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO privacy_requests (
                user_id,
                patient_id,
                full_name,
                email,
                contact_number,
                request_type,
                request_details,
                status,
                response_notes,
                handled_by_user_id,
                handled_at,
                created_at,
                updated_at
            ) VALUES (
                :user_id,
                :patient_id,
                :full_name,
                :email,
                :contact_number,
                :request_type,
                :request_details,
                :status,
                :response_notes,
                :handled_by_user_id,
                :handled_at,
                NOW(),
                NULL
            )
        ");

        $stmt->execute([
            ':user_id' => $data['user_id'] ?? null,
            ':patient_id' => $data['patient_id'] ?? null,
            ':full_name' => $data['full_name'],
            ':email' => $data['email'] ?? null,
            ':contact_number' => $data['contact_number'] ?? null,
            ':request_type' => $data['request_type'],
            ':request_details' => $data['request_details'],
            ':status' => $data['status'] ?? 'pending',
            ':response_notes' => $data['response_notes'] ?? null,
            ':handled_by_user_id' => $data['handled_by_user_id'] ?? null,
            ':handled_at' => $data['handled_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                pr.*,
                hu.first_name AS handled_by_first_name,
                hu.last_name AS handled_by_last_name
            FROM privacy_requests pr
            LEFT JOIN users hu ON hu.user_id = pr.handled_by_user_id
            WHERE pr.privacy_request_id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByPatientId(int $patientId): array
    {
        if ($patientId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM privacy_requests
            WHERE patient_id = :patient_id
            ORDER BY created_at DESC, privacy_request_id DESC
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getAll(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        $status = trim((string) ($filters['status'] ?? ''));
        $type = trim((string) ($filters['request_type'] ?? ''));
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        if ($status !== '') {
            $conditions[] = 'pr.status = :status';
            $params[':status'] = $status;
        }

        if ($type !== '') {
            $conditions[] = 'pr.request_type = :request_type';
            $params[':request_type'] = $type;
        }

        if ($keyword !== '') {
            $conditions[] = "(
                pr.full_name LIKE :keyword
                OR pr.email LIKE :keyword
                OR pr.contact_number LIKE :keyword
                OR pr.request_details LIKE :keyword
            )";

            $params[':keyword'] = '%' . $keyword . '%';
        }

        $whereSql = !empty($conditions)
            ? 'WHERE ' . implode(' AND ', $conditions)
            : '';

        $stmt = $this->db->prepare("
            SELECT
                pr.*,
                hu.first_name AS handled_by_first_name,
                hu.last_name AS handled_by_last_name
            FROM privacy_requests pr
            LEFT JOIN users hu ON hu.user_id = pr.handled_by_user_id
            $whereSql
            ORDER BY
                CASE pr.status
                    WHEN 'pending' THEN 1
                    WHEN 'reviewing' THEN 2
                    WHEN 'approved' THEN 3
                    WHEN 'completed' THEN 4
                    WHEN 'rejected' THEN 5
                    ELSE 6
                END ASC,
                pr.created_at DESC,
                pr.privacy_request_id DESC
            LIMIT 300
        ");

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateStatus(int $id, string $status, string $responseNotes, int $handledByUserId): bool
    {
        if ($id <= 0 || $handledByUserId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE privacy_requests
            SET status = :status,
                response_notes = :response_notes,
                handled_by_user_id = :handled_by_user_id,
                handled_at = NOW(),
                updated_at = NOW()
            WHERE privacy_request_id = :privacy_request_id
            LIMIT 1
        ");

        $stmt->execute([
            ':privacy_request_id' => $id,
            ':status' => $status,
            ':response_notes' => $responseNotes !== '' ? $responseNotes : null,
            ':handled_by_user_id' => $handledByUserId,
        ]);

        return $stmt->rowCount() > 0;
    }
}