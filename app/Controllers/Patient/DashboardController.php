<?php

namespace App\Controllers\Patient;

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Repositories\PatientDashboardRepository;
use App\Repositories\PatientRepository;
use Throwable;

class DashboardController
{
    private PatientDashboardRepository $dashboard;

    public function __construct()
    {
        $this->dashboard = new PatientDashboardRepository();
    }

    public function index(): void
    {
        Auth::requireRole('patient');

        $user = Auth::user();

        if (!$user || empty($user['user_id'])) {
            header('Location: /DentalClinic/public/login');
            exit;
        }

        $accountStatus = strtolower((string) ($user['account_status'] ?? 'active'));

        $patient = null;
        $stats = [];
        $upcomingAppointments = [];
        $recentRequests = [];
        $recentTreatments = [];
        $billingSummary = [];
        $recentDocuments = [];
        $recentNotifications = [];

        try {
            $patient = $this->dashboard->findPatientByUser($user);

            if ($patient) {
                $patientId = (int) $patient['patient_id'];
                $userId = (int) $user['user_id'];

                $stats = $this->dashboard->getDashboardStats($patientId, $userId);
                $upcomingAppointments = $this->dashboard->getUpcomingAppointments($patientId, 5);
                $recentRequests = $this->dashboard->getRecentAppointmentRequests($patientId, 5);
                $recentTreatments = $this->dashboard->getRecentTreatments($patientId, 5);
                $billingSummary = $this->dashboard->getBillingSummary($patientId);
                $recentDocuments = $this->dashboard->getRecentDocuments($patientId, 5);
                $recentNotifications = $this->dashboard->getRecentNotifications($userId, 5);

                $this->dashboard->logAudit(
                    $userId,
                    'patient_dashboard',
                    'view',
                    'patient',
                    $patientId,
                    'Patient viewed their dashboard.',
                    $this->ipAddress(),
                    $this->userAgent()
                );
            }
        } catch (Throwable $e) {
            error_log('[Patient Dashboard] ' . $e->getMessage());

            Session::set('flash_error', 'Dashboard data could not be loaded right now. Please try again later.');
        }

        View::render('patient.dashboard.index', [
            'authUser' => $user,
            'patient' => $patient,
            'stats' => $stats,
            'upcomingAppointments' => $upcomingAppointments,
            'recentRequests' => $recentRequests,
            'recentTreatments' => $recentTreatments,
            'billingSummary' => $billingSummary,
            'recentDocuments' => $recentDocuments,
            'recentNotifications' => $recentNotifications,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function pendingReview(): void
{
    if (!Auth::check()) {
        header('Location: /DentalClinic/public/login');
        exit;
    }

    $user = Auth::user();
    $role = strtolower((string) ($user['role'] ?? $user['role_name'] ?? ''));

    if ($role !== 'patient') {
        header('Location: /DentalClinic/public/login');
        exit;
    }

    $patientRepository = new PatientRepository();

    $patient = $patientRepository->findByUserId((int) $user['user_id']);

    $medicalHistory = null;
    $dentalHistory = null;

    if ($patient && !empty($patient['patient_id'])) {
        $patientId = (int) $patient['patient_id'];

        $medicalHistory = $patientRepository->findMedicalHistory($patientId);
        $dentalHistory = $patientRepository->findDentalHistory($patientId);
    }

    View::render('patient.pending-review', [
        'patient' => $patient,
        'hasMedicalHistory' => !empty($medicalHistory),
        'hasDentalHistory' => !empty($dentalHistory),
        'accountStatus' => strtolower((string) ($user['account_status'] ?? 'pending_review')),
    ]);
}

    private function ipAddress(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    private function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}