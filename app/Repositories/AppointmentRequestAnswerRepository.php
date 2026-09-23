<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AppointmentRequestAnswerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO appointment_request_answers (
                request_id,
                option_id,
                selected_value_id,
                answer_text,
                created_at
            ) VALUES (
                :request_id,
                :option_id,
                :selected_value_id,
                :answer_text,
                NOW()
            )
        ");

        $stmt->execute([
            'request_id' => $data['request_id'],
            'option_id' => $data['option_id'],
            'selected_value_id' => $data['selected_value_id'],
            'answer_text' => $data['answer_text'],
        ]);
    }

    public function createForPatient(array $data): int
{
    $stmt = $this->db->prepare("
        INSERT INTO appointment_requests (
            request_code,
            patient_id,
            is_guest,
            source_channel,
            guest_first_name,
            guest_middle_name,
            guest_last_name,
            guest_contact_number,
            guest_email,
            sex,
            birth_date,
            civil_status,
            address,
            occupation,
            emergency_contact_name,
            emergency_contact_number,
            preferred_dentist_id,
            service_id,
            preferred_date,
            preferred_start_time,
            notes,
            request_status,
            reviewed_by_user_id,
            reviewed_at,
            converted_appointment_id,
            staff_notes,
            created_at,
            updated_at
        ) VALUES (
            :request_code,
            :patient_id,
            :is_guest,
            :source_channel,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            :preferred_dentist_id,
            :service_id,
            :preferred_date,
            :preferred_start_time,
            :notes,
            :request_status,
            NULL,
            NULL,
            NULL,
            NULL,
            NOW(),
            NOW()
        )
    ");
    $stmt->execute([
        'request_code' => $data['request_code'],
        'patient_id' => $data['patient_id'],
        'is_guest' => 0,
        'source_channel' => $data['source_channel'],
        'preferred_dentist_id' => $data['preferred_dentist_id'],
        'service_id' => $data['service_id'],
        'preferred_date' => $data['preferred_date'],
        'preferred_start_time' => $data['preferred_start_time'],
        'notes' => $data['notes'],
        'request_status' => $data['request_status'],
    ]);

    return (int) $this->db->lastInsertId();
}

public function getByPatientId(int $patientId, int $limit = 20, int $offset = 0): array
{
    $stmt = $this->db->prepare("
        SELECT
            ar.*,
            s.service_name,
            du.first_name AS dentist_first_name,
            du.last_name AS dentist_last_name
        FROM appointment_requests ar
        LEFT JOIN services s ON s.service_id = ar.service_id
        LEFT JOIN dentists d ON d.dentist_id = ar.preferred_dentist_id
        LEFT JOIN users du ON du.user_id = d.user_id
        WHERE ar.patient_id = :patient_id
        ORDER BY ar.created_at DESC, ar.request_id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

public function countByPatientId(int $patientId): int
{
    $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM appointment_requests
        WHERE patient_id = :patient_id
    ");
    $stmt->execute([
        'patient_id' => $patientId,
    ]);

    return (int) $stmt->fetchColumn();
}

public function findByIdAndPatientId(int $requestId, int $patientId): ?array
{
    $stmt = $this->db->prepare("
        SELECT
            ar.*,
            s.service_name,
            du.first_name AS dentist_first_name,
            du.last_name AS dentist_last_name
        FROM appointment_requests ar
        LEFT JOIN services s ON s.service_id = ar.service_id
        LEFT JOIN dentists d ON d.dentist_id = ar.preferred_dentist_id
        LEFT JOIN users du ON du.user_id = d.user_id
        WHERE ar.request_id = :request_id
          AND ar.patient_id = :patient_id
        LIMIT 1
    ");
    $stmt->execute([
        'request_id' => $requestId,
        'patient_id' => $patientId,
    ]);

    $row = $stmt->fetch();
    return $row ?: null;
}

public function cancelPendingByPatient(int $requestId, int $patientId, ?string $notes = null): void
{
    $stmt = $this->db->prepare("
        UPDATE appointment_requests
        SET request_status = 'cancelled_by_patient',
            staff_notes = CASE
                WHEN :notes IS NOT NULL AND :notes <> ''
                THEN :notes
                ELSE staff_notes
            END,
            updated_at = NOW()
        WHERE request_id = :request_id
          AND patient_id = :patient_id
          AND request_status IN ('pending', 'under_review', 'rescheduled')
    ");
    $stmt->execute([
        'request_id' => $requestId,
        'patient_id' => $patientId,
        'notes' => $notes,
    ]);

    if ($stmt->rowCount() < 1) {
        throw new \RuntimeException('This request can no longer be cancelled.');
    }
}
}