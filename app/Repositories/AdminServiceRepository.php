<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class AdminServiceRepository
{
    private PDO $db;

    private array $allowedServices = [
        'Tooth Extraction',
        'Dental Cleaning',
        'Tooth Restoration',
        'Root Canal Treatment',
        'Dentures/Crowns/Fixed Bridges',
        'Orthodontics/Braces',
        'Surgery',
        'Dental Implants',
        'Teeth Whitening',
    ];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function allowedServices(): array
    {
        return $this->allowedServices;
    }

    public function all(): array
    {
        $columns = $this->columns('services');

        if (empty($columns)) {
            return [];
        }

        $orderBy = [];

        if (isset($columns['display_order'])) {
            $orderBy[] = 'display_order ASC';
        }

        if (isset($columns['service_name'])) {
            $orderBy[] = 'service_name ASC';
        }

        $sql = 'SELECT * FROM services';

        if (!empty($orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $orderBy);
        }

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): int
    {
        $serviceName = trim((string) ($data['service_name'] ?? ''));

        if ($serviceName === '') {
            throw new RuntimeException('Service name is required.');
        }

        if (!in_array($serviceName, $this->allowedServices, true)) {
            throw new RuntimeException('This service is not allowed.');
        }

        $columns = $this->columns('services');

        if (empty($columns)) {
            throw new RuntimeException('Services table does not exist.');
        }

        $insertColumns = [];
        $insertValues = [];
        $params = [];

        $this->addInsertColumn($insertColumns, $insertValues, $params, $columns, 'service_name', $serviceName);
        $this->addInsertColumn($insertColumns, $insertValues, $params, $columns, 'description', trim((string) ($data['description'] ?? '')));
        $this->addInsertColumn($insertColumns, $insertValues, $params, $columns, 'estimated_duration_minutes', max(5, (int) ($data['estimated_duration_minutes'] ?? 30)));
        $this->addInsertColumn($insertColumns, $insertValues, $params, $columns, 'estimated_price', max(0, (float) ($data['estimated_price'] ?? 0)));
        $this->addInsertColumn($insertColumns, $insertValues, $params, $columns, 'display_order', max(0, (int) ($data['display_order'] ?? 0)));

        if (!empty($data['service_image'])) {
            $this->addInsertColumn($insertColumns, $insertValues, $params, $columns, 'service_image', (string) $data['service_image']);
        }

        if (isset($columns['is_active'])) {
            $this->addInsertColumn($insertColumns, $insertValues, $params, $columns, 'is_active', (int) ($data['is_active'] ?? 1));
        } elseif (isset($columns['status'])) {
            $this->addInsertColumn(
                $insertColumns,
                $insertValues,
                $params,
                $columns,
                'status',
                ((int) ($data['is_active'] ?? 1) === 1 ? 'active' : 'inactive')
            );
        }

        if (isset($columns['created_at'])) {
            $insertColumns[] = '`created_at`';
            $insertValues[] = 'NOW()';
        }

        if (isset($columns['updated_at'])) {
            $insertColumns[] = '`updated_at`';
            $insertValues[] = 'NOW()';
        }

        if (empty($insertColumns)) {
            throw new RuntimeException('No valid service columns found.');
        }

        $sql = "
            INSERT INTO services (
                " . implode(', ', $insertColumns) . "
            ) VALUES (
                " . implode(', ', $insertValues) . "
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $serviceId, array $data): void
    {
        if ($serviceId <= 0) {
            throw new RuntimeException('Invalid service ID.');
        }

        $serviceName = trim((string) ($data['service_name'] ?? ''));

        if ($serviceName === '') {
            throw new RuntimeException('Service name is required.');
        }

        if (!in_array($serviceName, $this->allowedServices, true)) {
            throw new RuntimeException('This service is not allowed.');
        }

        $columns = $this->columns('services');

        if (empty($columns)) {
            throw new RuntimeException('Services table does not exist.');
        }

        $sets = [];
        $params = [
            ':service_id' => $serviceId,
        ];

        $this->addUpdateColumn($sets, $params, $columns, 'service_name', $serviceName);
        $this->addUpdateColumn($sets, $params, $columns, 'description', trim((string) ($data['description'] ?? '')));
        $this->addUpdateColumn($sets, $params, $columns, 'estimated_duration_minutes', max(5, (int) ($data['estimated_duration_minutes'] ?? 30)));
        $this->addUpdateColumn($sets, $params, $columns, 'estimated_price', max(0, (float) ($data['estimated_price'] ?? 0)));
        $this->addUpdateColumn($sets, $params, $columns, 'display_order', max(0, (int) ($data['display_order'] ?? 0)));

        if (!empty($data['service_image'])) {
            $this->addUpdateColumn($sets, $params, $columns, 'service_image', (string) $data['service_image']);
        }

        if (isset($columns['is_active'])) {
            $this->addUpdateColumn($sets, $params, $columns, 'is_active', (int) ($data['is_active'] ?? 1));
        } elseif (isset($columns['status'])) {
            $this->addUpdateColumn(
                $sets,
                $params,
                $columns,
                'status',
                ((int) ($data['is_active'] ?? 1) === 1 ? 'active' : 'inactive')
            );
        }

        if (isset($columns['updated_at'])) {
            $sets[] = 'updated_at = NOW()';
        }

        if (empty($sets)) {
            throw new RuntimeException('No valid service fields to update.');
        }

        $stmt = $this->db->prepare("
            UPDATE services
            SET " . implode(', ', $sets) . "
            WHERE service_id = :service_id
        ");

        $stmt->execute($params);
    }

    public function updateStatus(int $serviceId, int $isActive): void
    {
        if ($serviceId <= 0) {
            throw new RuntimeException('Invalid service ID.');
        }

        $columns = $this->columns('services');

        if (empty($columns)) {
            throw new RuntimeException('Services table does not exist.');
        }

        $sets = [];
        $params = [
            ':service_id' => $serviceId,
        ];

        if (isset($columns['is_active'])) {
            $sets[] = 'is_active = :is_active';
            $params[':is_active'] = $isActive === 1 ? 1 : 0;
        } elseif (isset($columns['status'])) {
            $sets[] = 'status = :status';
            $params[':status'] = $isActive === 1 ? 'active' : 'inactive';
        } else {
            throw new RuntimeException('No active/status column found in services table.');
        }

        if (isset($columns['updated_at'])) {
            $sets[] = 'updated_at = NOW()';
        }

        $stmt = $this->db->prepare("
            UPDATE services
            SET " . implode(', ', $sets) . "
            WHERE service_id = :service_id
        ");

        $stmt->execute($params);
    }

    public function toggleStatus(int $serviceId, int $isActive): void
    {
        $this->updateStatus($serviceId, $isActive);
    }

    public function setActive(int $serviceId, bool $active): void
    {
        $this->updateStatus($serviceId, $active ? 1 : 0);
    }

    private function addInsertColumn(
        array &$insertColumns,
        array &$insertValues,
        array &$params,
        array $columns,
        string $column,
        mixed $value
    ): void {
        if (!isset($columns[$column])) {
            return;
        }

        $placeholder = ':' . $column;

        $insertColumns[] = '`' . $column . '`';
        $insertValues[] = $placeholder;
        $params[$placeholder] = $value;
    }

    private function addUpdateColumn(
        array &$sets,
        array &$params,
        array $columns,
        string $column,
        mixed $value
    ): void {
        if (!isset($columns[$column])) {
            return;
        }

        $placeholder = ':' . $column;

        $sets[] = '`' . $column . '` = ' . $placeholder;
        $params[$placeholder] = $value;
    }

    private function columns(string $table): array
    {
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM `$table`");

            $columns = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $field = (string) ($row['Field'] ?? '');

                if ($field !== '') {
                    $columns[$field] = true;
                }
            }

            return $columns;
        } catch (Throwable $e) {
            return [];
        }
    }
}