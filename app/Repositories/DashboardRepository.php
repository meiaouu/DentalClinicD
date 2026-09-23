<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DashboardRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function countPendingAppointmentRequests(): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointment_requests
            WHERE request_status IN ('pending', 'under_review', 'rescheduled')
        ");
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function countTodayAppointments(): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointments
            WHERE appointment_date = CURDATE()
              AND status IN ('confirmed', 'checked_in', 'in_progress')
        ");
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function countPatients(): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM patients
        ");
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function countPendingFollowUps(): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM follow_ups
            WHERE status = 'scheduled'
        ");
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function getTodayAppointments(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.appointment_id,
                a.appointment_code,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.status,
                s.service_name,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name,
                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name
            FROM appointments a
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN patients p ON p.patient_id = a.patient_id
            LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            WHERE a.appointment_date = CURDATE()
            ORDER BY a.start_time ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getRecentAppointmentRequests(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ar.request_id,
                ar.request_code,
                ar.guest_first_name,
                ar.guest_last_name,
                ar.guest_contact_number,
                ar.request_status,
                ar.preferred_date,
                ar.preferred_start_time,
                s.service_name,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name
            FROM appointment_requests ar
            LEFT JOIN services s ON s.service_id = ar.service_id
            LEFT JOIN patients p ON p.patient_id = ar.patient_id
            ORDER BY ar.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}