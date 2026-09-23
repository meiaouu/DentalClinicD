<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;

$db = Database::getConnection();

echo "Running reminder sender...\n";

$stmt = $db->prepare("
    SELECT
        r.reminder_id,
        r.appointment_id,
        r.follow_up_id,
        r.patient_id,
        r.reminder_type,
        r.reminder_date,
        r.reminder_status,
        a.appointment_date,
        a.start_time,
        p.first_name,
        p.last_name,
        p.contact_number,
        p.email
    FROM reminders r
    LEFT JOIN appointments a ON a.appointment_id = r.appointment_id
    LEFT JOIN patients p ON p.patient_id = r.patient_id
    WHERE r.reminder_status = 'queued'
      AND r.reminder_date <= CURDATE()
    ORDER BY r.reminder_date ASC, r.reminder_id ASC
");
$stmt->execute();

$items = $stmt->fetchAll();

if (!$items) {
    echo "No queued reminders due.\n";
    exit(0);
}

$updateSent = $db->prepare("
    UPDATE reminders
    SET reminder_status = 'sent',
        sent_at = NOW()
    WHERE reminder_id = :reminder_id
");

$updateFailed = $db->prepare("
    UPDATE reminders
    SET reminder_status = 'failed'
    WHERE reminder_id = :reminder_id
");

$insertAudit = $db->prepare("
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

foreach ($items as $item) {
    try {
        $patientName = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
        $message = sprintf(
            'Reminder for %s: appointment on %s at %s.',
            $patientName !== '' ? $patientName : 'patient',
            $item['appointment_date'] ?? 'scheduled date',
            $item['start_time'] ?? 'scheduled time'
        );

        /*
         * Connect your real SMS/email sender here.
         * Example:
         * SmsService::send($item['contact_number'], $message);
         * MailService::send($item['email'], 'Appointment Reminder', $message);
         */

        $updateSent->execute([
            'reminder_id' => $item['reminder_id'],
        ]);

        $insertAudit->execute([
            'user_id' => null,
            'module_name' => 'reminders',
            'action_name' => 'send',
            'record_type' => 'reminder',
            'record_id' => (int) $item['reminder_id'],
            'description' => 'Reminder marked as sent. ' . $message,
        ]);

        echo 'Sent reminder #' . $item['reminder_id'] . "\n";
    } catch (\Throwable $e) {
        $updateFailed->execute([
            'reminder_id' => $item['reminder_id'],
        ]);

        $insertAudit->execute([
            'user_id' => null,
            'module_name' => 'reminders',
            'action_name' => 'fail',
            'record_type' => 'reminder',
            'record_id' => (int) $item['reminder_id'],
            'description' => 'Reminder sending failed: ' . $e->getMessage(),
        ]);

        echo 'Failed reminder #' . $item['reminder_id'] . ': ' . $e->getMessage() . "\n";
    }
}

echo "Reminder sender finished.\n";