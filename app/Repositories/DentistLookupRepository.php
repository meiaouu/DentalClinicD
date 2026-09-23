<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DentistLookupRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }
    public function findByUserId(int $userId): ?array
{
    $stmt = $this->db->prepare("
        SELECT *
        FROM dentists
        WHERE user_id = :user_id
        AND is_active = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $userId,
    ]);

    $dentist = $stmt->fetch(\PDO::FETCH_ASSOC);

    return $dentist ?: null;
}

    public function getActiveDentists(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                d.dentist_id,
                d.user_id,
                d.specialization,
                d.is_active,
                u.first_name,
                u.last_name
            FROM dentists d
            INNER JOIN users u ON u.user_id = d.user_id
            WHERE d.is_active = 1
            ORDER BY u.last_name ASC, u.first_name ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll();
    }
}