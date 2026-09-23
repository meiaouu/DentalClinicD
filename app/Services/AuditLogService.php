<?php

namespace App\Services;

use App\Repositories\AuditLogRepository;

class AuditLogService
{
    private AuditLogRepository $logs;

    public function __construct()
    {
        $this->logs = new AuditLogRepository();
    }

    public function log(
        ?int $userId,
        string $moduleName,
        string $actionName,
        string $recordType,
        int|string|null $recordId,
        string $description
    ): void {
        $this->logs->create([
            'user_id' => $userId,
            'module_name' => $moduleName,
            'action_name' => $actionName,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'description' => $description,
        ]);
    }
}