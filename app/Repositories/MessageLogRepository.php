<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class MessageLogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $channel = $this->allowedChannel($data['channel'] ?? 'sms');
        $status = $this->allowedStatus($data['status'] ?? 'queued');

        $stmt = $this->db->prepare("
            INSERT INTO message_logs (
                patient_id,
                appointment_id,
                sent_by,
                channel,
                recipient,
                subject,
                message_body,
                status,
                provider_response,
                error_message,
                sent_at,
                created_at
            ) VALUES (
                :patient_id,
                :appointment_id,
                :sent_by,
                :channel,
                :recipient,
                :subject,
                :message_body,
                :status,
                :provider_response,
                :error_message,
                :sent_at,
                NOW()
            )
        ");

        $sentAt = $data['sent_at'] ?? null;

        if ($status === 'sent' && $sentAt === null) {
            $sentAt = date('Y-m-d H:i:s');
        }

        $stmt->execute([
            ':patient_id' => $this->nullableInt($data['patient_id'] ?? null),
            ':appointment_id' => $this->nullableInt($data['appointment_id'] ?? null),
            ':sent_by' => $this->nullableInt($data['sent_by'] ?? null),
            ':channel' => $channel,
            ':recipient' => trim((string) ($data['recipient'] ?? '')),
            ':subject' => $this->nullableText($data['subject'] ?? null, 180),
            ':message_body' => (string) ($data['message_body'] ?? ''),
            ':status' => $status,
            ':provider_response' => $this->nullableText($data['provider_response'] ?? null),
            ':error_message' => $this->nullableText($data['error_message'] ?? null),
            ':sent_at' => $sentAt,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function markSent(int $messageId, ?string $providerResponse = null): bool
    {
        if ($messageId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE message_logs
            SET
                status = 'sent',
                provider_response = :provider_response,
                error_message = NULL,
                sent_at = NOW()
            WHERE message_id = :message_id
            LIMIT 1
        ");

        $stmt->execute([
            ':provider_response' => $providerResponse,
            ':message_id' => $messageId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markFailed(int $messageId, string $errorMessage): bool
    {
        if ($messageId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE message_logs
            SET
                status = 'failed',
                error_message = :error_message,
                sent_at = NULL
            WHERE message_id = :message_id
            LIMIT 1
        ");

        $stmt->execute([
            ':error_message' => $errorMessage,
            ':message_id' => $messageId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markSkipped(int $messageId, string $reason): bool
    {
        if ($messageId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE message_logs
            SET
                status = 'skipped',
                error_message = :reason,
                sent_at = NULL
            WHERE message_id = :message_id
            LIMIT 1
        ");

        $stmt->execute([
            ':reason' => $reason,
            ':message_id' => $messageId,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function allowedChannel($channel): string
    {
        $channel = strtolower(trim((string) $channel));

        return in_array($channel, ['sms', 'email', 'internal'], true)
            ? $channel
            : 'sms';
    }

    private function allowedStatus($status): string
    {
        $status = strtolower(trim((string) $status));

        return in_array($status, ['queued', 'sent', 'failed', 'skipped'], true)
            ? $status
            : 'queued';
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function nullableText($value, int $maxLength = 2000): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLength)
            : substr($value, 0, $maxLength);
    }
}