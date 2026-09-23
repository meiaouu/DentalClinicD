<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ServiceOptionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getByServiceId(int $serviceId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                so.option_id,
                so.option_name,
                so.option_type,
                so.is_required,
                sov.value_id,
                sov.value_label,
                sov.value_code,
                sov.sort_order
            FROM service_options so
            LEFT JOIN service_option_values sov ON sov.option_id = so.option_id
            WHERE so.service_id = :service_id
            ORDER BY so.option_id ASC, sov.sort_order ASC, sov.value_id ASC
        ");
        $stmt->execute([
            'service_id' => $serviceId,
        ]);

        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $optionId = (int) $row['option_id'];

            if (!isset($grouped[$optionId])) {
                $grouped[$optionId] = [
                    'option_id' => $optionId,
                    'option_name' => $row['option_name'],
                    'option_type' => $row['option_type'],
                    'is_required' => (int) $row['is_required'] === 1,
                    'values' => [],
                ];
            }

            if (!empty($row['value_id'])) {
                $grouped[$optionId]['values'][] = [
                    'value_id' => (int) $row['value_id'],
                    'value_label' => $row['value_label'],
                    'value_code' => $row['value_code'],
                ];
            }
        }

        return array_values($grouped);
    }
}