<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ClinicalRecordRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listForDentistUser(
        int $userId,
        ?string $date = null,
        ?string $status = null,
        string $search = '',
        int $limit = 100
    ): array {
        $limit = max(1, min($limit, 200));

        $sql = "
            SELECT
                a.appointment_id,
                a.appointment_code,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.status,
                a.patient_id,
                a.dentist_id,

                p.patient_code,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                p.contact_number,
                p.email,

                s.service_name,

                e.examination_id,
                e.examination_date,

                t.treatment_id,
                t.treatment_date,
                t.procedure_name,
                t.treatment_status,
                t.actual_charge,
                t.amount_paid,
                t.balance

            FROM appointments a
            INNER JOIN dentists d 
                ON d.dentist_id = a.dentist_id

            LEFT JOIN patients p 
                ON p.patient_id = a.patient_id

            LEFT JOIN services s 
                ON s.service_id = a.service_id

            LEFT JOIN examinations e 
                ON e.appointment_id = a.appointment_id

            LEFT JOIN treatments t 
                ON t.appointment_id = a.appointment_id

            WHERE d.user_id = :user_id
        ";

        $params = [
            ':user_id' => $userId,
        ];

        if ($date !== null && trim($date) !== '') {
            $sql .= " AND a.appointment_date = :appointment_date";
            $params[':appointment_date'] = trim($date);
        }

        if ($status !== null && trim($status) !== '') {
            $sql .= " AND a.status = :status";
            $params[':status'] = trim($status);
        }

        if (trim($search) !== '') {
            $sql .= "
                AND (
                    a.appointment_code LIKE :search
                    OR p.patient_code LIKE :search
                    OR p.first_name LIKE :search
                    OR p.middle_name LIKE :search
                    OR p.last_name LIKE :search
                    OR p.contact_number LIKE :search
                    OR p.email LIKE :search
                    OR s.service_name LIKE :search
                )
            ";

            $params[':search'] = '%' . trim($search) . '%';
        }

        $sql .= "
            ORDER BY 
                a.appointment_date DESC,
                a.start_time DESC,
                a.appointment_id DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            if ($key === ':user_id') {
                $stmt->bindValue($key, (int) $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}