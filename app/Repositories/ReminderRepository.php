<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ReminderRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO reminders (
                appointment_id,
                follow_up_id,
                patient_id,
                reminder_type,
                reminder_date,
                reminder_status,
                sent_at,
                created_at
            ) VALUES (
                :appointment_id,
                :follow_up_id,
                :patient_id,
                :reminder_type,
                :reminder_date,
                :reminder_status,
                :sent_at,
                NOW()
            )
        ");

        $stmt->execute([
            'appointment_id' => $data['appointment_id'],
            'follow_up_id' => $data['follow_up_id'],
            'patient_id' => $data['patient_id'],
            'reminder_type' => $data['reminder_type'],
            'reminder_date' => $data['reminder_date'],
            'reminder_status' => $data['reminder_status'],
            'sent_at' => $data['sent_at'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createAppointmentReminderSet(int $appointmentId, int $patientId, string $appointmentDate): void
    {
        $dates = [
            '3_days_before' => date('Y-m-d', strtotime($appointmentDate . ' -3 days')),
            '2_days_before' => date('Y-m-d', strtotime($appointmentDate . ' -2 days')),
            '1_day_before'  => date('Y-m-d', strtotime($appointmentDate . ' -1 day')),
        ];

        foreach ($dates as $type => $date) {
            if ($date > date('Y-m-d')) {
                $this->create([
                    'appointment_id' => $appointmentId,
                    'follow_up_id' => null,
                    'patient_id' => $patientId,
                    'reminder_type' => $type,
                    'reminder_date' => $date,
                    'reminder_status' => 'queued',
                    'sent_at' => null,
                ]);
            }
        }
    }
}