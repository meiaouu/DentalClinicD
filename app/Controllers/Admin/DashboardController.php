<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AdminDashboardRepository;
use Throwable;

class DashboardController
{
    private AdminDashboardRepository $dashboard;

    public function __construct()
    {
        $this->dashboard = new AdminDashboardRepository();
    }

    public function index(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);

        $loadErrors = [];

        $stats = $this->safeDashboardCall('stats', [], [], $loadErrors);
        $recentAppointments = $this->safeDashboardCall('recentAppointments', [8], [], $loadErrors);
        $recentAuditLogs = $this->safeDashboardCall('recentAuditLogs', [8], [], $loadErrors);
        $topServices = $this->safeDashboardCall('topServices', [5], [], $loadErrors);
        $dentistWorkload = $this->safeDashboardCall('dentistWorkload', [5], [], $loadErrors);

        if (!empty($loadErrors) && !Session::get('flash_error')) {
            Session::set('flash_error', 'Some dashboard data could not be loaded. Please refresh or check the system logs.');
        }

        View::render('admin.dashboard.index', [
            'stats' => is_array($stats) ? $stats : [],
            'recentAppointments' => is_array($recentAppointments) ? $recentAppointments : [],
            'recentAuditLogs' => is_array($recentAuditLogs) ? $recentAuditLogs : [],
            'topServices' => is_array($topServices) ? $topServices : [],
            'dentistWorkload' => is_array($dentistWorkload) ? $dentistWorkload : [],
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    private function safeDashboardCall(string $method, array $arguments, array $default, array &$loadErrors): array
    {
        try {
            if (!method_exists($this->dashboard, $method)) {
                $loadErrors[] = $method;

                error_log('[Admin Dashboard] Missing repository method: ' . $method);

                return $default;
            }

            $result = $this->dashboard->{$method}(...$arguments);

            return is_array($result) ? $result : $default;
        } catch (Throwable $e) {
            $loadErrors[] = $method;

            error_log('[Admin Dashboard] Failed loading ' . $method . ': ' . $e->getMessage());

            return $default;
        }
    }
}