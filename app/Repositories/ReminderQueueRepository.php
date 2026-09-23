<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ReminderQueueRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getDueQueuedReminders(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT
                r.reminder_id,
                r.appointment_id,
                r.follow_up_id,
                r.patient_id,
                r.reminder_type,
                r.reminder_date,
                r.reminder_status,
                r.sent_at,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,
                p.email AS patient_email,
                a.appointment_date,
                a.start_time,
                s.service_name
            FROM reminders r
            LEFT JOIN patients p ON p.patient_id = r.patient_id
            LEFT JOIN appointments a ON a.appointment_id = r.appointment_id
            LEFT JOIN services s ON s.service_id = a.service_id
            WHERE r.reminder_status = 'queued'
              AND r.reminder_date <= CURDATE()
            ORDER BY r.reminder_date ASC, r.reminder_id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function markSent(int $reminderId): void
    {
        $stmt = $this->db->prepare("
            UPDATE reminders
            SET reminder_status = 'sent',
                sent_at = NOW()
            WHERE reminder_id = :reminder_id
        ");
        $stmt->execute([
            'reminder_id' => $reminderId,
        ]);
    }

    public function markFailed(int $reminderId): void
    {
        $stmt = $this->db->prepare("
            UPDATE reminders
            SET reminder_status = 'failed'
            WHERE reminder_id = :reminder_id
        ");
        $stmt->execute([
            'reminder_id' => $reminderId,
        ]);
    }
}