<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AddressRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function search(string $term): array
    {
        $stmt = $this->db->prepare("
            SELECT name
            FROM addresses
            WHERE name LIKE :term
            LIMIT 10
        ");

        $stmt->execute([
            'term' => '%' . $term . '%'
        ]);

        return $stmt->fetchAll();
    }
}