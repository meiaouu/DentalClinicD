<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\BackupRepository;
use RuntimeException;
use Throwable;

class BackupController
{
    private BackupRepository $backups;

    public function __construct()
    {
        $this->backups = new BackupRepository();
    }

    public function index(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);

        View::render('admin.backup.index', [
            'backups' => $this->backups->all(),
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function create(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);
        $this->verifyCsrf();

        $user = Auth::user();

        try {
            $backupId = $this->backups->create((int) ($user['user_id'] ?? 0));
            $this->backups->audit((int) ($user['user_id'] ?? 0), 'backup.create', 'system_backups', $backupId, 'Created database backup.');

            Session::set('flash_success', 'Backup created successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: /DentalClinic/public/admin/backup');
        exit;
    }

    public function download(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);

        $backupId = (int) ($_GET['id'] ?? 0);
        $backup = $this->backups->find($backupId);

        if (!$backup) {
            http_response_code(404);
            exit('Backup not found.');
        }

        $path = (string) ($backup['backup_path'] ?? '');

        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            exit('Backup file missing.');
        }

        $filename = basename($path);

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));

        readfile($path);
        exit;
    }

    public function delete(): void
    {
        Auth::requireAnyRole(['owner', 'admin']);
        $this->verifyCsrf();

        $user = Auth::user();
        $backupId = (int) ($_POST['backup_id'] ?? 0);

        try {
            if ($backupId <= 0) {
                throw new RuntimeException('Invalid backup.');
            }

            $this->backups->delete($backupId);
            $this->backups->audit((int) ($user['user_id'] ?? 0), 'backup.delete', 'system_backups', $backupId, 'Deleted database backup.');

            Session::set('flash_success', 'Backup deleted successfully.');
        } catch (Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: /DentalClinic/public/admin/backup');
        exit;
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}