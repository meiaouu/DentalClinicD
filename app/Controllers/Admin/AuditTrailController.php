<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Repositories\AuditTrailRepository;

class AuditTrailController
{
    private AuditTrailRepository $auditTrail;

    public function __construct()
    {
        $this->auditTrail = new AuditTrailRepository();
    }

    public function index(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);

        $search = trim((string) ($_GET['search'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        View::render('admin.audit-trail.index', [
            'logs' => $this->auditTrail->paginate($search, $perPage, $offset),
            'total' => $this->auditTrail->count($search),
            'page' => $page,
            'perPage' => $perPage,
            'search' => $search,
        ]);
    }
}