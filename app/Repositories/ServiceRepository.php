<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ServiceRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getActiveServices(): array
{
    $stmt = $this->db->prepare("
        SELECT
            service_id,
            service_name,
            description,
            estimated_duration_minutes,
            estimated_price,
            service_image,
            display_order,
            is_active
        FROM services
        WHERE is_active = 1
        ORDER BY display_order ASC, service_name ASC
    ");

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

    public function active(): array
    {
        return $this->getActiveServices();
    }

    public function findById(int $serviceId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                service_id,
                service_name,
                description,
                estimated_duration_minutes,
                estimated_price,
                service_image,
                display_order,
                is_active
            FROM services
            WHERE service_id = :service_id
            LIMIT 1
        ");

        $stmt->execute([
            'service_id' => $serviceId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getQuestionsByServiceId(int $serviceId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                so.option_id,
                so.service_id,
                so.option_name,
                so.option_type,
                so.is_required,
                sov.value_id,
                sov.value_label,
                sov.value_code,
                sov.sort_order
            FROM service_options so
            LEFT JOIN service_option_values sov
                ON sov.option_id = so.option_id
            WHERE so.service_id = :service_id
            ORDER BY so.option_id ASC, sov.sort_order ASC, sov.value_id ASC
        ");

        $stmt->execute([
            'service_id' => $serviceId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grouped = [];

        foreach ($rows as $row) {
            $optionId = (int) $row['option_id'];

            if (!isset($grouped[$optionId])) {
                $grouped[$optionId] = [
                    'option_id' => $optionId,
                    'service_id' => (int) $row['service_id'],
                    'option_name' => $row['option_name'],
                    'option_type' => $row['option_type'],
                    'is_required' => (int) $row['is_required'],
                    'values' => [],
                ];
            }

            if (!empty($row['value_id'])) {
                $grouped[$optionId]['values'][] = [
                    'value_id' => (int) $row['value_id'],
                    'value_label' => $row['value_label'],
                    'value_code' => $row['value_code'],
                    'sort_order' => (int) $row['sort_order'],
                ];
            }
        }

        return array_values($grouped);
    }
}