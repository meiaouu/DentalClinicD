<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class MessageTemplateRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByKey(string $templateKey): ?array
    {
        $templateKey = trim($templateKey);

        if ($templateKey === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM message_templates
            WHERE template_key = :template_key
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':template_key' => $templateKey,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function seedDefaults(): void
    {
        $templates = [
            [
                'template_key' => 'appointment_3_days',
                'title' => 'Appointment Reminder - 3 Days Before',
                'channel' => 'sms',
                'body' => 'Reminder: Your dental appointment is in 3 days. Hello {patient_name}, this is a reminder from {clinic_name}. You have a dental appointment on {appointment_date} at {appointment_time} with {dentist_name}. Service: {service_name}. Please arrive 10 minutes early. {clinic_contact_text}Thank you.',
            ],
            [
                'template_key' => 'appointment_2_days',
                'title' => 'Appointment Reminder - 2 Days Before',
                'channel' => 'sms',
                'body' => 'Reminder: Your dental appointment is in 2 days. Hello {patient_name}, this is a reminder from {clinic_name}. You have a dental appointment on {appointment_date} at {appointment_time} with {dentist_name}. Service: {service_name}. Please arrive 10 minutes early. {clinic_contact_text}Thank you.',
            ],
            [
                'template_key' => 'appointment_1_day',
                'title' => 'Appointment Reminder - 1 Day Before',
                'channel' => 'sms',
                'body' => 'Reminder: Your dental appointment is tomorrow. Hello {patient_name}, this is a reminder from {clinic_name}. You have a dental appointment on {appointment_date} at {appointment_time} with {dentist_name}. Service: {service_name}. Please arrive 10 minutes early. {clinic_contact_text}Thank you.',
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO message_templates (
                template_key,
                title,
                channel,
                body,
                is_active,
                created_at
            ) VALUES (
                :template_key,
                :title,
                :channel,
                :body,
                1,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                channel = VALUES(channel),
                body = VALUES(body),
                is_active = 1,
                updated_at = NOW()
        ");

        foreach ($templates as $template) {
            $stmt->execute([
                ':template_key' => $template['template_key'],
                ':title' => $template['title'],
                ':channel' => $template['channel'],
                ':body' => $template['body'],
            ]);
        }
    }
}