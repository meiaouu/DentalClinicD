<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use Throwable;

class AuditLogger
{
    public static function log(
        string $module,
        string $action,
        string $recordType,
        ?string $recordId,
        string $description
    ): void {
        try {
            $user = Auth::user();
            $db = Database::getConnection();

            $stmt = $db->prepare("
                INSERT INTO audit_logs (
                    user_id,
                    module_name,
                    action_name,
                    record_type,
                    record_id,
                    description,
                    created_at
                ) VALUES (
                    :user_id,
                    :module_name,
                    :action_name,
                    :record_type,
                    :record_id,
                    :description,
                    NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => (int) ($user['user_id'] ?? 0) ?: null,
                ':module_name' => $module,
                ':action_name' => $action,
                ':record_type' => $recordType,
                ':record_id' => $recordId,
                ':description' => $description,
            ]);
        } catch (Throwable $e) {
            // Do not stop the main action if audit logging fails.
        }
    }
}