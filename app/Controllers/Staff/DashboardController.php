<?php

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use PDO;
use Throwable;

class DashboardController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function index(): void
    {
        Auth::requireRole('staff');

        $data = $this->getDashboardData();

        View::render('staff.dashboard', array_merge($data, [
            'authUser' => Auth::user(),
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]));

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function stats(): void
    {
        Auth::requireRole('staff');

        header('Content-Type: application/json; charset=utf-8');

        try {
            $data = $this->getDashboardData();

            echo json_encode([
                'success' => true,
                'pending_requests_count' => $data['pendingRequestsCount'],
                'today_appointments_count' => $data['todayAppointmentsCount'],
                'patients_count' => $data['patientsCount'],
                'pending_followups_count' => $data['pendingFollowUpsCount'],
                'upcoming_appointments_count' => $data['upcomingAppointmentsCount'],
                'pending_bills_count' => $data['pendingBillsCount'],
                'recent_requests' => $data['recentRequests'],
                'pending_bills' => $data['pendingBills'],
            ]);
        } catch (Throwable $exception) {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Unable to load dashboard stats.',
            ]);
        }

        exit;
    }

    private function getDashboardData(): array
    {
        $today = date('Y-m-d');

        return [
            'pendingRequestsCount' => $this->countPendingRequests(),
            'todayAppointmentsCount' => $this->countTodayAppointments($today),
            'patientsCount' => $this->countPatients(),
            'pendingFollowUpsCount' => $this->countPendingFollowUps(),
            'upcomingAppointmentsCount' => $this->countUpcomingAppointments($today),
            'pendingBillsCount' => $this->countPendingBills(),

            'todayAppointments' => $this->getTodayAppointments($today),
            'recentRequests' => $this->getRecentRequests(),
            'recentPatients' => $this->getRecentPatients(),
            'pendingBills' => $this->getPendingBills(),
        ];
    }

    private function countPendingRequests(): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointment_requests
            WHERE request_status IN ('pending', 'under_review', 'rescheduled')
        ");

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function countTodayAppointments(string $today): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointments
            WHERE appointment_date = :today
              AND status NOT IN ('cancelled', 'no_show')
        ");

        $stmt->execute([
            ':today' => $today,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function countPatients(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*)
            FROM patients
            WHERE COALESCE(profile_status, 'active') <> 'archived'
        ");

        return (int) $stmt->fetchColumn();
    }

    private function countPendingFollowUps(): int
    {
        if (!$this->tableExists('follow_ups')) {
            return 0;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM follow_ups
            WHERE status IN ('pending', 'scheduled')
        ");

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function countUpcomingAppointments(string $today): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointments
            WHERE appointment_date > :today
              AND status IN ('confirmed', 'checked_in', 'in_progress', 'rescheduled')
        ");

        $stmt->execute([
            ':today' => $today,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function countPendingBills(): int
    {
        if ($this->tableExists('billings')) {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM billings
                WHERE COALESCE(balance, 0) > 0
                  AND LOWER(COALESCE(payment_status, 'unpaid')) NOT IN ('paid', 'cancelled', 'void')
            ");

            $stmt->execute();

            return (int) $stmt->fetchColumn();
        }

        if ($this->tableExists('treatments')) {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM treatments
                WHERE COALESCE(balance, 0) > 0
                  AND LOWER(COALESCE(treatment_status, 'active')) NOT IN ('cancelled', 'void')
            ");

            $stmt->execute();

            return (int) $stmt->fetchColumn();
        }

        return 0;
    }

    private function getTodayAppointments(string $today): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.appointment_id,
                a.appointment_code,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.status,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                s.service_name
            FROM appointments a
            LEFT JOIN patients p ON p.patient_id = a.patient_id
            LEFT JOIN services s ON s.service_id = a.service_id
            WHERE a.appointment_date = :today
            ORDER BY a.start_time ASC, a.appointment_id ASC
            LIMIT 8
        ");

        $stmt->execute([
            ':today' => $today,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getRecentRequests(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ar.request_id,
                ar.request_code,
                ar.patient_id,
                ar.guest_first_name,
                ar.guest_middle_name,
                ar.guest_last_name,
                ar.preferred_date,
                ar.preferred_start_time,
                ar.request_status,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                s.service_name
            FROM appointment_requests ar
            LEFT JOIN patients p ON p.patient_id = ar.patient_id
            LEFT JOIN services s ON s.service_id = ar.service_id
            WHERE ar.request_status IN ('pending', 'under_review', 'rescheduled')
            ORDER BY ar.created_at DESC, ar.request_id DESC
            LIMIT 10
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getRecentPatients(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                patient_id,
                patient_code,
                first_name,
                middle_name,
                last_name,
                contact_number,
                address,
                created_at
            FROM patients
            WHERE COALESCE(profile_status, 'active') <> 'archived'
            ORDER BY created_at DESC, patient_id DESC
            LIMIT 6
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getPendingBills(): array
    {
        if ($this->tableExists('billings')) {
            $stmt = $this->db->prepare("
                SELECT
                    b.billing_id,
                    b.billing_number,
                    b.treatment_id,
                    b.appointment_id,
                    b.patient_id,
                    b.payment_status,
                    b.total_amount,
                    b.amount_paid,
                    b.balance,
                    b.created_at,
                    p.patient_code,
                    p.first_name AS patient_first_name,
                    p.middle_name AS patient_middle_name,
                    p.last_name AS patient_last_name,
                    a.appointment_code,
                    COALESCE(s.service_name, t.procedure_name, 'Manual Billing') AS service_name,
                    t.procedure_name
                FROM billings b
                LEFT JOIN patients p ON p.patient_id = b.patient_id
                LEFT JOIN appointments a ON a.appointment_id = b.appointment_id
                LEFT JOIN services s ON s.service_id = a.service_id
                LEFT JOIN treatments t ON t.treatment_id = b.treatment_id
                WHERE COALESCE(b.balance, 0) > 0
                  AND LOWER(COALESCE(b.payment_status, 'unpaid')) NOT IN ('paid', 'cancelled', 'void')
                ORDER BY b.created_at DESC, b.billing_id DESC
                LIMIT 10
            ");

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if ($this->tableExists('treatments')) {
            $stmt = $this->db->prepare("
                SELECT
                    NULL AS billing_id,
                    NULL AS billing_number,
                    t.treatment_id,
                    t.appointment_id,
                    t.patient_id,
                    CASE
                        WHEN COALESCE(t.amount_paid, 0) > 0 THEN 'partial'
                        ELSE 'unpaid'
                    END AS payment_status,
                    t.actual_charge AS total_amount,
                    t.amount_paid,
                    t.balance,
                    t.created_at,
                    p.patient_code,
                    p.first_name AS patient_first_name,
                    p.middle_name AS patient_middle_name,
                    p.last_name AS patient_last_name,
                    a.appointment_code,
                    COALESCE(s.service_name, t.procedure_name, 'Treatment') AS service_name,
                    t.procedure_name
                FROM treatments t
                LEFT JOIN patients p ON p.patient_id = t.patient_id
                LEFT JOIN appointments a ON a.appointment_id = t.appointment_id
                LEFT JOIN services s ON s.service_id = a.service_id
                WHERE COALESCE(t.balance, 0) > 0
                  AND LOWER(COALESCE(t.treatment_status, 'active')) NOT IN ('cancelled', 'void')
                ORDER BY t.created_at DESC, t.treatment_id DESC
                LIMIT 10
            ");

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return [];
    }

    private function tableExists(string $table): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }

        $stmt = $this->db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);

        return (bool) $stmt->fetchColumn();
    }
}