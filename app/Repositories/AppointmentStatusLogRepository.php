<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AppointmentStatusLogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO appointment_status_logs (
                appointment_id,
                old_status,
                new_status,
                changed_by,
                remarks,
                changed_at
            ) VALUES (
                :appointment_id,
                :old_status,
                :new_status,
                :changed_by,
                :remarks,
                NOW()
            )
        ");
        $stmt->execute([
            'appointment_id' => $data['appointment_id'],
            'old_status' => $data['old_status'],
            'new_status' => $data['new_status'],
            'changed_by' => $data['changed_by'],
            'remarks' => $data['remarks'],
        ]);

        return (int) $this->db->lastInsertId();
    }
}