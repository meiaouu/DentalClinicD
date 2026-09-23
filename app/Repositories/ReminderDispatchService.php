<?php

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\ReminderQueueRepository;

class ReminderDispatchService
{
    private ReminderQueueRepository $reminders;
    private AuditLogRepository $auditLogs;

    public function __construct()
    {
        $this->reminders = new ReminderQueueRepository();
        $this->auditLogs = new AuditLogRepository();
    }

    public function processDueReminders(int $limit = 50): array
    {
        $items = $this->reminders->getDueQueuedReminders($limit);

        $result = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

        foreach ($items as $item) {
            $result['processed']++;

            try {
                $this->sendReminder($item);
                $this->reminders->markSent((int) $item['reminder_id']);
                $result['sent']++;

                $this->auditLogs->create([
                    'user_id' => null,
                    'module_name' => 'reminders',
                    'action_name' => 'sent',
                    'record_type' => 'reminder',
                    'record_id' => (int) $item['reminder_id'],
                    'description' => 'Reminder marked as sent for patient #' . (int) $item['patient_id'],
                ]);
            } catch (\Throwable $e) {
                $this->reminders->markFailed((int) $item['reminder_id']);
                $result['failed']++;

                $this->auditLogs->create([
                    'user_id' => null,
                    'module_name' => 'reminders',
                    'action_name' => 'failed',
                    'record_type' => 'reminder',
                    'record_id' => (int) $item['reminder_id'],
                    'description' => 'Reminder failed: ' . $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    private function sendReminder(array $item): void
    {
        $contact = trim((string) ($item['patient_contact_number'] ?? ''));
        $email = trim((string) ($item['patient_email'] ?? ''));

        if ($contact === '' && $email === '') {
            throw new \RuntimeException('No patient contact details available.');
        }

        $message = sprintf(
            'Reminder: You have an appointment for %s on %s at %s.',
            (string) ($item['service_name'] ?? 'Dental Service'),
            (string) ($item['appointment_date'] ?? ''),
            (string) ($item['start_time'] ?? '')
        );

        /*
         * Safe placeholder:
         * Integrate real SMS/email provider here.
         * Example:
         * $smsGateway->send($contact, $message);
         * $mailer->send($email, 'Appointment Reminder', $message);
         */

        if ($message === '') {
            throw new \RuntimeException('Unable to build reminder message.');
        }
    }
}