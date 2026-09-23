<?php

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\PrivacyRequestRepository;
use App\Services\PrivacyAuditService;
use App\Services\PrivacyRequestService;
use RuntimeException;
use Throwable;

class PrivacyRequestController
{
    private PrivacyRequestRepository $requests;
    private PrivacyRequestService $service;
    private PrivacyAuditService $audit;

    public function __construct()
    {
        $this->requests = new PrivacyRequestRepository();
        $this->service = new PrivacyRequestService();
        $this->audit = new PrivacyAuditService();
    }

    public function index(): void
    {
        $user = $this->requireStaffOrAdmin();

        $filters = [
            'status' => trim((string) ($_GET['status'] ?? '')),
            'request_type' => trim((string) ($_GET['request_type'] ?? '')),
            'keyword' => trim((string) ($_GET['keyword'] ?? '')),
        ];

        $this->audit->log(
            (int) $user['user_id'],
            'privacy_requests',
            'view_list',
            'privacy_request',
            null,
            'Viewed privacy request list.'
        );

        View::render('staff.privacy_requests.index', [
            'requests' => $this->requests->getAll($filters),
            'filters' => $filters,
            'success' => Session::get('success'),
            'errors' => Session::get('errors', []),
        ]);

        Session::remove('success');
        Session::remove('errors');
    }

    public function show(): void
    {
        $user = $this->requireStaffOrAdmin();

        $id = (int) ($_GET['id'] ?? 0);
        $request = $this->requests->findById($id);

        if (!$request) {
            http_response_code(404);
            exit('Privacy request not found.');
        }

        $this->audit->log(
            (int) $user['user_id'],
            'privacy_requests',
            'view_detail',
            'privacy_request',
            $id,
            'Viewed privacy request details.'
        );

        View::render('staff.privacy_requests.show', [
            'request' => $request,
            'success' => Session::get('success'),
            'errors' => Session::get('errors', []),
        ]);

        Session::remove('success');
        Session::remove('errors');
    }

    public function update(): void
    {
        $user = $this->requireStaffOrAdmin();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $id = (int) ($_POST['privacy_request_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));
        $responseNotes = trim((string) ($_POST['response_notes'] ?? ''));

        try {
            $this->service->updateRequestStatus(
                $id,
                $status,
                $responseNotes,
                (int) $user['user_id']
            );

            Session::set('success', 'Privacy request updated successfully.');
        } catch (RuntimeException $e) {
            Session::set('errors', [
                'privacy_request' => [$e->getMessage()],
            ]);
        } catch (Throwable $e) {
            error_log('[Staff\PrivacyRequestController] ' . $e->getMessage());

            Session::set('errors', [
                'privacy_request' => ['Something went wrong. Please try again.'],
            ]);
        }

        header('Location: ' . $this->url('/staff/privacy-requests/show?id=' . urlencode((string) $id)));
        exit;
    }

    private function requireStaffOrAdmin(): array
    {
        if (!Auth::check()) {
            header('Location: ' . $this->url('/login'));
            exit;
        }

        $user = Auth::user();
        $role = strtolower((string) ($user['role_name'] ?? ''));

        if (!in_array($role, ['staff', 'admin'], true)) {
            http_response_code(403);
            exit('Forbidden.');
        }

        return $user;
    }

    private function url(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }
}