<?php

namespace App\Controllers\Dentist;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use PDO;
use Throwable;

class WorkspaceController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function dashboard(): void
    {
        Auth::requireRole('dentist');

        $authUser = Auth::user();
        $dentistId = $this->getDentistIdByUserId((int) ($authUser['user_id'] ?? 0));
        $today = date('Y-m-d');

        $stats = [
            'today_appointments' => 0,
            'completed_today' => 0,
            'my_patients' => 0,
            'followup_queue' => 0,
        ];

        $todayAppointments = [];
        $upcomingAppointments = [];
        $completedAppointments = [];

        if ($dentistId > 0) {
            $todayAppointments = $this->getTodayAppointments($dentistId, $today);
            $upcomingAppointments = $this->getUpcomingAppointments($dentistId, $today);
            $completedAppointments = $this->getCompletedAppointments($dentistId);

            $stats = [
                'today_appointments' => $this->countTodayAppointments($dentistId, $today),
                'completed_today' => $this->countCompletedToday($dentistId, $today),
                'my_patients' => $this->countMyPatients($dentistId),
                'followup_queue' => $this->countFollowUps($dentistId),
            ];
        }

        View::render('dentist.dashboard', [
            'pageTitle' => 'Dentist Dashboard',
            'stats' => $stats,
            'todayAppointments' => $todayAppointments,
            'upcomingAppointments' => $upcomingAppointments,
            'completedAppointments' => $completedAppointments,
        ]);
    }

    public function patients(): void
    {
        $this->renderPage(
            'Patients',
            'This page is the dentist patient workspace entry. You can later connect it to assigned and treated patients.',
            [
                [
                    'title' => 'Dental Records',
                    'description' => 'Review dental records and patient charts.',
                    'link' => '/DentalClinic/public/dentist/dental-records',
                    'link_label' => 'Open Dental Records',
                ],
                [
                    'title' => 'Clinical Record',
                    'description' => 'Open the existing clinical record workflow.',
                    'link' => '/DentalClinic/public/dentist/clinical-record',
                    'link_label' => 'Open Clinical Record',
                ],
            ]
        );
    }

    public function appointments(): void
    {
        $this->renderPage(
            'Appointments',
            'This page is reserved for the dentist appointment list, queue, and today schedule view.',
            [
                [
                    'title' => 'Dashboard',
                    'description' => 'Go back to dentist dashboard.',
                    'link' => '/DentalClinic/public/dentist/dashboard',
                    'link_label' => 'Open Dashboard',
                ],
                [
                    'title' => 'Availability',
                    'description' => 'Manage your working schedule.',
                    'link' => '/DentalClinic/public/dentist/availability',
                    'link_label' => 'Open Availability',
                ],
            ]
        );
    }

    public function dentalRecords(): void
    {
        $this->renderPage(
            'Dental Records',
            'This page acts as the dentist record hub for chart access, examination, and treatment flow.',
            [
                [
                    'title' => 'Clinical Record',
                    'description' => 'Open the working clinical record page you already have.',
                    'link' => '/DentalClinic/public/dentist/clinical-record',
                    'link_label' => 'Open Clinical Record',
                ],
                [
                    'title' => 'Treatments',
                    'description' => 'Go to the treatments page.',
                    'link' => '/DentalClinic/public/dentist/treatments',
                    'link_label' => 'Open Treatments',
                ],
            ]
        );
    }

    public function treatments(): void
    {
        $this->renderPage(
            'Treatments',
            'This page is reserved for performed procedures, treatment entries, and charge encoding.',
            [
                [
                    'title' => 'Clinical Record',
                    'description' => 'Use the current clinical record flow for treatment encoding.',
                    'link' => '/DentalClinic/public/dentist/clinical-record',
                    'link_label' => 'Open Clinical Record',
                ],
                [
                    'title' => 'Follow-ups',
                    'description' => 'Proceed to follow-up planning.',
                    'link' => '/DentalClinic/public/dentist/followups',
                    'link_label' => 'Open Follow-ups',
                ],
            ]
        );
    }

    public function followUps(): void
    {
        $this->renderPage(
            'Follow-ups',
            'This page is reserved for dentist follow-up recommendations and next-visit planning.',
            [
                [
                    'title' => 'Treatments',
                    'description' => 'Go back to treatment workflow.',
                    'link' => '/DentalClinic/public/dentist/treatments',
                    'link_label' => 'Open Treatments',
                ],
            ]
        );
    }

    public function messages(): void
    {
        $this->renderPage(
            'Messages',
            'This page is reserved for dentist-side clinic and patient communication.',
            [
                [
                    'title' => 'Dashboard',
                    'description' => 'Return to the dashboard.',
                    'link' => '/DentalClinic/public/dentist/dashboard',
                    'link_label' => 'Open Dashboard',
                ],
            ]
        );
    }

    private function getDentistIdByUserId(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare("
            SELECT dentist_id
            FROM dentists
            WHERE user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function getTodayAppointments(int $dentistId, string $today): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.appointment_id,
                a.appointment_code,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.status,
                a.remarks,

                COALESCE(p.first_name, ar.guest_first_name, '') AS patient_first_name,
                COALESCE(p.last_name, ar.guest_last_name, '') AS patient_last_name,

                s.service_name,

                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name
            FROM appointments a
            LEFT JOIN patients p
                ON p.patient_id = a.patient_id
            LEFT JOIN appointment_requests ar
                ON ar.request_id = a.request_id
            LEFT JOIN services s
                ON s.service_id = a.service_id
            LEFT JOIN dentists d
                ON d.dentist_id = a.dentist_id
            LEFT JOIN users du
                ON du.user_id = d.user_id
            WHERE a.dentist_id = :dentist_id
              AND a.appointment_date = :today
              AND a.status IN ('confirmed', 'checked_in', 'in_progress', 'rescheduled')
            ORDER BY a.start_time ASC, a.appointment_id ASC
        ");

        $stmt->execute([
            ':dentist_id' => $dentistId,
            ':today' => $today,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getUpcomingAppointments(int $dentistId, string $today): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.appointment_id,
                a.appointment_code,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.status,
                a.remarks,

                COALESCE(p.first_name, ar.guest_first_name, '') AS patient_first_name,
                COALESCE(p.last_name, ar.guest_last_name, '') AS patient_last_name,

                s.service_name,

                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name
            FROM appointments a
            LEFT JOIN patients p
                ON p.patient_id = a.patient_id
            LEFT JOIN appointment_requests ar
                ON ar.request_id = a.request_id
            LEFT JOIN services s
                ON s.service_id = a.service_id
            LEFT JOIN dentists d
                ON d.dentist_id = a.dentist_id
            LEFT JOIN users du
                ON du.user_id = d.user_id
            WHERE a.dentist_id = :dentist_id
              AND a.appointment_date > :today
              AND a.status IN ('confirmed', 'checked_in', 'in_progress', 'rescheduled')
            ORDER BY a.appointment_date ASC, a.start_time ASC
            LIMIT 20
        ");

        $stmt->execute([
            ':dentist_id' => $dentistId,
            ':today' => $today,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getCompletedAppointments(int $dentistId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.appointment_id,
                a.appointment_code,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.status,
                a.remarks,

                COALESCE(p.first_name, ar.guest_first_name, '') AS patient_first_name,
                COALESCE(p.last_name, ar.guest_last_name, '') AS patient_last_name,

                s.service_name,

                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name
            FROM appointments a
            LEFT JOIN patients p
                ON p.patient_id = a.patient_id
            LEFT JOIN appointment_requests ar
                ON ar.request_id = a.request_id
            LEFT JOIN services s
                ON s.service_id = a.service_id
            LEFT JOIN dentists d
                ON d.dentist_id = a.dentist_id
            LEFT JOIN users du
                ON du.user_id = d.user_id
            WHERE a.dentist_id = :dentist_id
              AND a.status = 'completed'
            ORDER BY a.appointment_date DESC, a.start_time DESC
            LIMIT 20
        ");

        $stmt->execute([
            ':dentist_id' => $dentistId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function countTodayAppointments(int $dentistId, string $today): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointments
            WHERE dentist_id = :dentist_id
              AND appointment_date = :today
              AND status IN ('confirmed', 'checked_in', 'in_progress', 'rescheduled')
        ");

        $stmt->execute([
            ':dentist_id' => $dentistId,
            ':today' => $today,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function countCompletedToday(int $dentistId, string $today): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointments
            WHERE dentist_id = :dentist_id
              AND appointment_date = :today
              AND status = 'completed'
        ");

        $stmt->execute([
            ':dentist_id' => $dentistId,
            ':today' => $today,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function countMyPatients(int $dentistId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT patient_id)
            FROM appointments
            WHERE dentist_id = :dentist_id
              AND patient_id IS NOT NULL
        ");

        $stmt->execute([
            ':dentist_id' => $dentistId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function countFollowUps(int $dentistId): int
    {
        if (!$this->tableExists('follow_ups')) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM follow_ups
                WHERE dentist_id = :dentist_id
            ");

            $stmt->execute([
                ':dentist_id' => $dentistId,
            ]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :table_name
        ");

        $stmt->execute([
            ':table_name' => $tableName,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function renderPage(string $pageTitle, string $pageSubtitle, array $cards = []): void
    {
        Auth::requireRole('dentist');

        View::render('dentist.workspace-page', [
            'pageTitle' => $pageTitle,
            'pageSubtitle' => $pageSubtitle,
            'cards' => $cards,
        ]);
    }
}