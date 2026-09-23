<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\AppointmentRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

class AppointmentProcedureWorkflowService
{
    private PDO $db;
    private AppointmentRepository $appointments;

    public function __construct(?AppointmentRepository $appointments = null)
    {
        $this->db = Database::getConnection();
        $this->appointments = $appointments ?? new AppointmentRepository();
    }

    public function startProcedure(
        int $appointmentId,
        int $userId,
        ?int $dentistId = null,
        ?string $remarks = null
    ): array {
        if ($appointmentId <= 0) {
            throw new RuntimeException('Invalid appointment.');
        }

        if ($userId <= 0) {
            throw new RuntimeException('Invalid user.');
        }

        try {
            $this->db->beginTransaction();

            $appointment = $this->appointments->findByIdForUpdate($appointmentId);

            if (!$appointment) {
                throw new RuntimeException('Appointment not found.');
            }

            if ($dentistId !== null && $dentistId > 0 && (int) ($appointment['dentist_id'] ?? 0) !== $dentistId) {
                throw new RuntimeException('You are not allowed to start this appointment.');
            }

            $oldStatus = strtolower(trim((string) ($appointment['status'] ?? '')));

            if (!in_array($oldStatus, ['confirmed', 'checked_in'], true)) {
                throw new RuntimeException('Only confirmed or checked-in appointments can be started.');
            }

            if (!empty($appointment['actual_started_at'])) {
                throw new RuntimeException('This procedure has already been started.');
            }

            if (in_array($oldStatus, ['cancelled', 'rejected', 'no_show', 'completed'], true)) {
                throw new RuntimeException('This appointment can no longer be started.');
            }

            $now = $this->serverDateTime();

            $this->appointments->updateProcedureStarted(
                $appointmentId,
                $now,
                $remarks !== null && trim($remarks) !== '' ? $remarks : null
            );

            $this->appointments->insertStatusLog(
                $appointmentId,
                $oldStatus,
                'in_progress',
                $userId,
                $remarks !== null && trim($remarks) !== '' ? $remarks : 'Procedure started.',
                $now
            );

            $this->writeAudit(
                $userId,
                'appointments',
                'start_procedure',
                'appointment',
                $appointmentId,
                'Procedure started at ' . $now
            );

            $this->db->commit();

            return $this->appointments->findDetailedById($appointmentId) ?: [];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function completeProcedure(
        int $appointmentId,
        int $userId,
        ?int $dentistId = null,
        ?string $remarks = null
    ): array {
        if ($appointmentId <= 0) {
            throw new RuntimeException('Invalid appointment.');
        }

        if ($userId <= 0) {
            throw new RuntimeException('Invalid user.');
        }

        try {
            $this->db->beginTransaction();

            $appointment = $this->appointments->findByIdForUpdate($appointmentId);

            if (!$appointment) {
                throw new RuntimeException('Appointment not found.');
            }

            if ($dentistId !== null && $dentistId > 0 && (int) ($appointment['dentist_id'] ?? 0) !== $dentistId) {
                throw new RuntimeException('You are not allowed to complete this appointment.');
            }

            $oldStatus = strtolower(trim((string) ($appointment['status'] ?? '')));

            if ($oldStatus !== 'in_progress') {
                throw new RuntimeException('Only in-progress appointments can be completed.');
            }

            if (empty($appointment['actual_started_at'])) {
                throw new RuntimeException('This procedure has no recorded start time.');
            }

            if (!empty($appointment['actual_completed_at'])) {
                throw new RuntimeException('This procedure has already been completed.');
            }

            $now = $this->serverDateTime();

            $this->appointments->updateProcedureCompleted(
                $appointmentId,
                $now,
                $remarks !== null && trim($remarks) !== '' ? $remarks : null
            );

            $this->appointments->insertStatusLog(
                $appointmentId,
                $oldStatus,
                'completed',
                $userId,
                $remarks !== null && trim($remarks) !== '' ? $remarks : 'Procedure completed.',
                $now
            );

            $this->writeAudit(
                $userId,
                'appointments',
                'complete_procedure',
                'appointment',
                $appointmentId,
                'Procedure completed at ' . $now
            );

            $this->db->commit();

            return $this->appointments->findDetailedById($appointmentId) ?: [];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function serverDateTime(): string
    {
        $timezone = new DateTimeZone(date_default_timezone_get() ?: 'Asia/Manila');

        return (new DateTimeImmutable('now', $timezone))->format('Y-m-d H:i:s');
    }

    private function writeAudit(
        int $userId,
        string $moduleName,
        string $actionName,
        string $recordType,
        int $recordId,
        string $description
    ): void {
        try {
            $exists = $this->db
                ->query("SHOW TABLES LIKE 'audit_logs'")
                ->fetchColumn();

            if (!$exists) {
                return;
            }

            $stmt = $this->db->prepare("
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
                ':user_id' => $userId,
                ':module_name' => $moduleName,
                ':action_name' => $actionName,
                ':record_type' => $recordType,
                ':record_id' => $recordId,
                ':description' => $description,
            ]);
        } catch (Throwable $e) {
            // Audit logging should never break the appointment workflow.
        }
    }
}