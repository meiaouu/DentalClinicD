<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AttachmentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getByPatientId(int $patientId, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT
                attachment_id,
                patient_id,
                appointment_id,
                examination_id,
                treatment_id,
                uploaded_by,
                file_category,
                original_file_name,
                stored_file_name,
                file_path,
                mime_type,
                file_size,
                description,
                created_at
            FROM attachments
            WHERE patient_id = :patient_id
            ORDER BY created_at DESC, attachment_id DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}