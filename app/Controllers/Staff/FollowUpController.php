<?php

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Services\FollowUpService;

class FollowUpController
{
    private FollowUpService $followUps;

    public function __construct()
    {
        $this->followUps = new FollowUpService();
    }

    public function index(): void
    {
        Auth::requireRole('staff');

        $status = trim($_GET['status'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $data = $this->followUps->getStaffList($status !== '' ? $status : null, $page, 15);

        View::render('staff.followups.show', [
            'mode' => 'list',
            'followUps' => $data['items'],
            'total' => $data['total'],
            'page' => $data['page'],
            'perPage' => $data['perPage'],
            'status' => $status,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function show(): void
    {
        Auth::requireRole('staff');

        $followUpId = (int) ($_GET['id'] ?? 0);

        try {
            $data = $this->followUps->getDetailed($followUpId);

            View::render('staff.followups.show', [
                'mode' => 'detail',
                'followUp' => $data['follow_up'],
                'reminders' => $data['reminders'],
                'flash_success' => Session::get('flash_success'),
                'flash_error' => Session::get('flash_error'),
            ]);
        } catch (\Throwable $e) {
            http_response_code(404);
            exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function createFromTreatment(): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $treatmentId = (int) ($_POST['treatment_id'] ?? 0);

        try {
            $followUpId = $this->followUps->createFromTreatment(
                $treatmentId,
                trim($_POST['recommended_date'] ?? ''),
                trim($_POST['reason'] ?? ''),
                trim($_POST['remarks'] ?? '') ?: null
            );

            Session::set('flash_success', 'Follow-up created successfully.');
            header('Location: /staff/followups/show?id=' . urlencode((string) $followUpId));
            exit;
        } catch (\Throwable $e) {
            Session::set('flash_error', $e->getMessage());
            header('Location: /staff/billing/show?treatment_id=' . urlencode((string) $treatmentId));
            exit;
        }
    }

    public function updateStatus(): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $followUpId = (int) ($_POST['follow_up_id'] ?? 0);

        try {
            $this->followUps->updateStatus(
                $followUpId,
                trim($_POST['status'] ?? ''),
                trim($_POST['remarks'] ?? '') ?: null
            );

            Session::set('flash_success', 'Follow-up status updated successfully.');
        } catch (\Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: /staff/followups/show?id=' . urlencode((string) $followUpId));
        exit;
    }
}