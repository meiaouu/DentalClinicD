<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

class AdminDentistRepository
{
    private PDO $db;
    private array $dentistColumns = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->dentistColumns = $this->columns('dentists');
    }

    public function all(): array
    {
        $stmt = $this->db->query("
            SELECT
                d.*,
                u.first_name,
                u.middle_name,
                u.last_name,
                u.email,
                u.contact_number,
                u.username
            FROM dentists d
            LEFT JOIN users u ON u.user_id = d.user_id
            ORDER BY d.dentist_id DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $dentistId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                d.*,
                u.first_name,
                u.middle_name,
                u.last_name,
                u.email,
                u.contact_number,
                u.username
            FROM dentists d
            LEFT JOIN users u ON u.user_id = d.user_id
            WHERE d.dentist_id = :dentist_id
            LIMIT 1
        ");

        $stmt->execute([
            ':dentist_id' => $dentistId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function availableUsers(?int $currentUserId = null): array
    {
        $sql = "
            SELECT
                u.user_id,
                u.first_name,
                u.middle_name,
                u.last_name,
                u.username,
                u.email
            FROM users u
            LEFT JOIN dentists d ON d.user_id = u.user_id
            WHERE d.dentist_id IS NULL
        ";

        $params = [];

        if ($currentUserId !== null && $currentUserId > 0) {
            $sql .= " OR u.user_id = :current_user_id";
            $params[':current_user_id'] = $currentUserId;
        }

        $sql .= " ORDER BY u.first_name ASC, u.last_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): int
    {
        if ($this->userAlreadyDentist((int) $data['user_id'])) {
            throw new RuntimeException('This user already has a dentist profile.');
        }

        $data['dentist_code'] = $data['dentist_code'] !== ''
            ? $data['dentist_code']
            : 'DEN-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));

        $insert = $this->filterColumns($data);

        if (empty($insert['user_id'])) {
            throw new RuntimeException('User account is required.');
        }

        $columns = array_keys($insert);
        $placeholders = array_map(static fn ($col) => ':' . $col, $columns);

        $stmt = $this->db->prepare("
            INSERT INTO dentists (" . implode(', ', array_map(fn ($c) => "`$c`", $columns)) . ")
            VALUES (" . implode(', ', $placeholders) . ")
        ");

        $params = [];

        foreach ($insert as $column => $value) {
            $params[':' . $column] = $value;
        }

        $stmt->execute($params);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $dentistId, array $data): void
    {
        $insert = $this->filterColumns($data);

        unset($insert['dentist_id']);

        if (empty($insert)) {
            return;
        }

        $sets = [];
        $params = [
            ':dentist_id' => $dentistId,
        ];

        foreach ($insert as $column => $value) {
            $sets[] = "`$column` = :$column";
            $params[':' . $column] = $value;
        }

        $stmt = $this->db->prepare("
            UPDATE dentists
            SET " . implode(', ', $sets) . "
            WHERE dentist_id = :dentist_id
        ");

        $stmt->execute($params);
    }

    public function setActive(int $dentistId, bool $active): void
    {
        if (!isset($this->dentistColumns['is_active'])) {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE dentists
            SET is_active = :is_active
            WHERE dentist_id = :dentist_id
        ");

        $stmt->execute([
            ':is_active' => $active ? 1 : 0,
            ':dentist_id' => $dentistId,
        ]);
    }

    private function userAlreadyDentist(int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dentists
            WHERE user_id = :user_id
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function filterColumns(array $data): array
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            if (isset($this->dentistColumns[$key])) {
                $filtered[$key] = $value === '' ? null : $value;
            }
        }

        return $filtered;
    }

    private function columns(string $table): array
    {
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM `$table`");
            $columns = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $columns[(string) $row['Field']] = true;
            }

            return $columns;
        } catch (\Throwable $e) {
            return [];
        }
    }
}