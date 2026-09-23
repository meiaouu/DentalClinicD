<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DentistRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT 
                d.dentist_id,
                d.user_id,
                d.dentist_code,
                d.license_number,
                d.specialization,
                d.is_owner,
                d.consultation_fee,
                d.bio,
                d.is_active,
                u.first_name,
                u.middle_name,
                u.last_name,
                u.email
            FROM dentists d
            INNER JOIN users u ON u.user_id = d.user_id
            WHERE d.user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }
}