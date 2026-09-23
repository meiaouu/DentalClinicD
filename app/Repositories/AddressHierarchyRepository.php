<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AddressHierarchyRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getRegions(): array
    {
        $stmt = $this->db->prepare("
            SELECT region_id, region_name
            FROM regions
            ORDER BY region_name ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getProvincesByRegion(int $regionId): array
    {
        $stmt = $this->db->prepare("
            SELECT province_id, province_name
            FROM provinces
            WHERE region_id = :region_id
            ORDER BY province_name ASC
        ");
        $stmt->execute([
            'region_id' => $regionId,
        ]);

        return $stmt->fetchAll();
    }

    public function getCitiesByProvince(int $provinceId): array
    {
        $stmt = $this->db->prepare("
            SELECT city_id, city_name
            FROM cities_municipalities
            WHERE province_id = :province_id
            ORDER BY city_name ASC
        ");
        $stmt->execute([
            'province_id' => $provinceId,
        ]);

        return $stmt->fetchAll();
    }

    public function getBarangaysByCity(int $cityId): array
    {
        $stmt = $this->db->prepare("
            SELECT barangay_id, barangay_name
            FROM barangays
            WHERE city_id = :city_id
            ORDER BY barangay_name ASC
        ");
        $stmt->execute([
            'city_id' => $cityId,
        ]);

        return $stmt->fetchAll();
    }
}