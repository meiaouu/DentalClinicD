<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PrivacyConsentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO privacy_consents (
                user_id,
                patient_id,
                appointment_request_id,
                full_name,
                email,
                contact_number,
                consent_type,
                consent_version,
                consent_text,
                accepted,
                source_form,
                ip_address,
                user_agent,
                created_at
            ) VALUES (
                :user_id,
                :patient_id,
                :appointment_request_id,
                :full_name,
                :email,
                :contact_number,
                :consent_type,
                :consent_version,
                :consent_text,
                :accepted,
                :source_form,
                :ip_address,
                :user_agent,
                NOW()
            )
        ");

        $stmt->execute([
            ':user_id' => $data['user_id'] ?? null,
            ':patient_id' => $data['patient_id'] ?? null,
            ':appointment_request_id' => $data['appointment_request_id'] ?? null,
            ':full_name' => $data['full_name'] ?? null,
            ':email' => $data['email'] ?? null,
            ':contact_number' => $data['contact_number'] ?? null,
            ':consent_type' => $data['consent_type'],
            ':consent_version' => $data['consent_version'],
            ':consent_text' => $data['consent_text'],
            ':accepted' => (int) $data['accepted'],
            ':source_form' => $data['source_form'],
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findByAppointmentRequestId(int $appointmentRequestId): ?array
    {
        if ($appointmentRequestId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM privacy_consents
            WHERE appointment_request_id = :appointment_request_id
            ORDER BY created_at DESC, consent_id DESC
            LIMIT 1
        ");

        $stmt->execute([
            ':appointment_request_id' => $appointmentRequestId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByPatientId(int $patientId): array
    {
        if ($patientId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM privacy_consents
            WHERE patient_id = :patient_id
            ORDER BY created_at DESC, consent_id DESC
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}