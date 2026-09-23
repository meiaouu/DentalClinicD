<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class RoleRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByRoleName(string $roleName): ?array
    {
        $stmt = $this->db->prepare("
            SELECT role_id, role_name, description
            FROM roles
            WHERE role_name = :role_name
            LIMIT 1
        ");
        $stmt->execute([
            'role_name' => $roleName,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }
}