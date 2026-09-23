<?php

namespace App\Controllers\Dentist;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\DentistRepository;
use App\Services\DentistAvailabilityService;

class AvailabilityController
{
    private DentistRepository $dentists;
    private DentistAvailabilityService $availability;

    public function __construct()
    {
        $this->dentists = new DentistRepository();
        $this->availability = new DentistAvailabilityService();
    }

    public function index(): void
    {
        Auth::requireRole('dentist');

        $user = Auth::user();
        $dentist = $this->dentists->findByUserId((int) $user['user_id']);

        if (!$dentist) {
            http_response_code(403);
            exit('Dentist profile not found.');
        }

        $month = trim((string) ($_GET['month'] ?? date('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $schedules = $this->availability->getMergedSchedules((int) $dentist['dentist_id']);
        $unavailablePage = max(1, (int) ($_GET['blocked_page'] ?? 1));
        $overridePage = max(1, (int) ($_GET['override_page'] ?? 1));

        $unavailableData = $this->availability->paginateUnavailableDates(
            (int) $dentist['dentist_id'],
            $unavailablePage,
            15
        );

        $overrideData = $this->availability->paginateOverrides(
            (int) $dentist['dentist_id'],
            $overridePage,
            10
        );

        $maps = $this->availability->getMonthMaps((int) $dentist['dentist_id'], $month);
        $summary = $this->availability->buildMonthlySummary((int) $dentist['dentist_id'], $schedules, $month);

        View::render('dentist.availability.index', [
            'dentist' => $dentist,
            'schedules' => $schedules,
            'unavailableDates' => $unavailableData['items'],
            'unavailableTotal' => $unavailableData['total'],
            'unavailablePage' => $unavailableData['page'],
            'unavailablePerPage' => $unavailableData['perPage'],
            'dateOverrides' => $overrideData['items'],
            'overrideTotal' => $overrideData['total'],
            'overridePage' => $overrideData['page'],
            'overridePerPage' => $overrideData['perPage'],
            'monthlyUnavailableDates' => $maps['monthlyUnavailableDates'],
            'monthlyDateOverrides' => $maps['monthlyDateOverrides'],
            'dayLabels' => $this->availability->getDayLabels(),
            'summary' => $summary,
            'calendarMonth' => $month,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function storeWeekly(): void
    {
        Auth::requireRole('dentist');
        $this->ensureCsrf();

        $user = Auth::user();
        $dentist = $this->dentists->findByUserId((int) $user['user_id']);

        if (!$dentist) {
            Session::set('flash_error', 'Dentist profile not found.');
            header('Location: /dentist/availability');
            exit;
        }

        try {
            $this->availability->saveWeeklySchedule(
                (int) $dentist['dentist_id'],
                $_POST['days'] ?? []
            );

            Session::set('flash_success', 'Weekly availability updated successfully.');
        } catch (\Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: /dentist/availability?month=' . urlencode((string) ($_POST['month'] ?? date('Y-m'))));
        exit;
    }

    public function storeUnavailableDate(): void
    {
        Auth::requireRole('dentist');
        $this->ensureCsrf();

        $user = Auth::user();
        $dentist = $this->dentists->findByUserId((int) $user['user_id']);

        if (!$dentist) {
            Session::set('flash_error', 'Dentist profile not found.');
            header('Location: /dentist/availability');
            exit;
        }

        try {
            $this->availability->saveUnavailableDate(
                (int) $dentist['dentist_id'],
                trim((string) ($_POST['unavailable_date'] ?? '')),
                trim((string) ($_POST['start_time'] ?? '')) ?: null,
                trim((string) ($_POST['end_time'] ?? '')) ?: null,
                trim((string) ($_POST['reason'] ?? '')) ?: null
            );

            Session::set('flash_success', 'Blocked time saved successfully.');
        } catch (\Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: /dentist/availability?month=' . urlencode((string) ($_POST['month'] ?? date('Y-m'))));
        exit;
    }

    public function deleteUnavailableDate(): void
    {
        Auth::requireRole('dentist');
        $this->ensureCsrf();

        $user = Auth::user();
        $dentist = $this->dentists->findByUserId((int) $user['user_id']);

        if (!$dentist) {
            Session::set('flash_error', 'Dentist profile not found.');
            header('Location: /dentist/availability');
            exit;
        }

        try {
            $this->availability->deleteUnavailableDate(
                (int) $dentist['dentist_id'],
                (int) ($_POST['unavailable_id'] ?? 0)
            );

            Session::set('flash_success', 'Blocked time removed successfully.');
        } catch (\Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: /dentist/availability?month=' . urlencode((string) ($_POST['month'] ?? date('Y-m'))));
        exit;
    }

    public function storeDateOverride(): void
    {
        Auth::requireRole('dentist');
        $this->ensureCsrf();

        $user = Auth::user();
        $dentist = $this->dentists->findByUserId((int) $user['user_id']);

        if (!$dentist) {
            Session::set('flash_error', 'Dentist profile not found.');
            header('Location: /dentist/availability');
            exit;
        }

        try {
            $this->availability->saveDateOverride(
                (int) $dentist['dentist_id'],
                trim((string) ($_POST['override_date'] ?? '')),
                isset($_POST['is_available']) && (string) $_POST['is_available'] === '1',
                trim((string) ($_POST['availability_mode'] ?? '')) ?: null,
                trim((string) ($_POST['start_time'] ?? '')) ?: null,
                trim((string) ($_POST['end_time'] ?? '')) ?: null,
                trim((string) ($_POST['reason'] ?? '')) ?: null
            );

            Session::set('flash_success', 'Date override saved successfully.');
        } catch (\Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: /dentist/availability?month=' . urlencode((string) ($_POST['month'] ?? date('Y-m'))));
        exit;
    }

    public function weeklySchedule(): void
{
    $controller = new \App\Controllers\Dentist\AppointmentController();
    $controller->weeklySchedule();
}

    public function deleteDateOverride(): void
    {
        Auth::requireRole('dentist');
        $this->ensureCsrf();

        $user = Auth::user();
        $dentist = $this->dentists->findByUserId((int) $user['user_id']);

        if (!$dentist) {
            Session::set('flash_error', 'Dentist profile not found.');
            header('Location: /dentist/availability');
            exit;
        }

        try {
            $this->availability->deleteDateOverride(
                (int) $dentist['dentist_id'],
                (int) ($_POST['override_id'] ?? 0)
            );

            Session::set('flash_success', 'Date override removed successfully.');
        } catch (\Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: /dentist/availability?month=' . urlencode((string) ($_POST['month'] ?? date('Y-m'))));
        exit;
    }

    private function ensureCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}