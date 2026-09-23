<?php

namespace App\Services;

use App\Repositories\AppointmentReminderRepository;
use App\Repositories\MessageLogRepository;
use App\Repositories\MessageTemplateRepository;
use RuntimeException;
use Throwable;

class AppointmentReminderService
{
    private AppointmentReminderRepository $appointments;
    private MessageTemplateRepository $templates;
    private MessageLogRepository $messageLogs;
    private MessageSenderService $sender;

    public function __construct()
    {
        $this->appointments = new AppointmentReminderRepository();
        $this->templates = new MessageTemplateRepository();
        $this->messageLogs = new MessageLogRepository();
        $this->sender = new MessageSenderService();

        $this->templates->seedDefaults();
    }

    public function sendAutomaticDueReminders(): array
    {
        $today = date('Y-m-d');

        $summary = [
            'total_found' => 0,
            'sent' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ([3, 2, 1] as $daysBefore) {
            $reminderType = $this->getReminderTypeByDaysBefore($daysBefore);
            $appointments = $this->appointments->findAppointmentsForReminderDate($today, $daysBefore);

            $summary['total_found'] += count($appointments);

            foreach ($appointments as $appointment) {
                $appointmentId = (int) ($appointment['appointment_id'] ?? 0);

                $result = $this->sendReminderForAppointment($appointmentId, $reminderType, null);

                if (($result['status'] ?? '') === 'failed') {
                    $summary['failed']++;
                } elseif (($result['status'] ?? '') === 'skipped') {
                    $summary['skipped']++;
                } else {
                    $summary['sent']++;
                }

                $summary['details'][] = $result;
            }
        }

        return $summary;
    }

    public function sendReminderForAppointment(int $appointmentId, string $reminderType, ?int $sentBy = null): array
    {
        $reminderType = trim($reminderType);

        if (!$this->isValidReminderType($reminderType)) {
            return [
                'status' => 'failed',
                'appointment_id' => $appointmentId,
                'reminder_type' => $reminderType,
                'message' => 'Invalid reminder type.',
            ];
        }

        $appointment = $this->appointments->findAppointmentById($appointmentId);

        if (!$appointment) {
            return [
                'status' => 'failed',
                'appointment_id' => $appointmentId,
                'reminder_type' => $reminderType,
                'message' => 'Appointment not found.',
            ];
        }

        $patientId = (int) ($appointment['patient_id'] ?? 0);
        $status = strtolower(trim((string) ($appointment['status'] ?? '')));

        if ($status !== 'confirmed') {
            return $this->logSkippedAttempt(
                $appointment,
                $reminderType,
                'Appointment is not confirmed.',
                $sentBy
            );
        }

        if ((int) ($appointment['notification_allowed'] ?? 1) !== 1) {
            return $this->logSkippedAttempt(
                $appointment,
                $reminderType,
                'Patient notification consent is not allowed.',
                $sentBy
            );
        }

        if ($this->appointments->hasReminderBeenSent($appointmentId, $reminderType)) {
            return [
                'status' => 'skipped',
                'appointment_id' => $appointmentId,
                'patient_id' => $patientId,
                'reminder_type' => $reminderType,
                'message' => 'Reminder already logged for this appointment and reminder type.',
            ];
        }

        $recipient = $this->resolveRecipient($appointment);

        if ($recipient === null) {
            return $this->logSkippedAttempt(
                $appointment,
                $reminderType,
                'Patient has no contact number or email.',
                $sentBy
            );
        }

        $message = $this->buildReminderMessage($appointment, $reminderType);
        $subject = $this->subjectForReminderType($reminderType);
        $uniqueKey = $this->appointments->buildUniqueKey($appointmentId, $reminderType);

        $messageId = $this->messageLogs->create([
            'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
            'sent_by' => $sentBy,
            'channel' => $recipient['channel'],
            'recipient' => $recipient['value'],
            'subject' => $recipient['channel'] === 'email' ? $subject : null,
            'message_body' => $message,
            'status' => 'queued',
        ]);

        $sendResult = match ($recipient['channel']) {
            'sms' => $this->sender->sendSms($recipient['value'], $message),
            'email' => $this->sender->sendEmail($recipient['value'], $subject, $message),
            'internal' => $this->sender->sendInternal($patientId, $message),
            default => [
                'status' => 'failed',
                'provider_response' => null,
                'error_message' => 'Unsupported message channel.',
            ],
        };

        $providerStatus = (string) ($sendResult['status'] ?? 'failed');
        $providerResponse = $sendResult['provider_response'] ?? null;
        $errorMessage = $sendResult['error_message'] ?? null;

        if ($providerStatus === 'sent') {
            $this->messageLogs->markSent($messageId, $providerResponse);
            $reminderLogStatus = 'sent';
            $resultStatus = 'sent';
        } elseif ($providerStatus === 'queued') {
            $reminderLogStatus = 'sent';
            $resultStatus = 'sent';
        } else {
            $this->messageLogs->markFailed($messageId, (string) ($errorMessage ?: 'Message provider failed.'));
            $reminderLogStatus = 'failed';
            $resultStatus = 'failed';
        }

        try {
            $this->appointments->createReminderLog([
                'appointment_id' => $appointmentId,
                'patient_id' => $patientId,
                'message_id' => $messageId,
                'reminder_type' => $reminderType,
                'reminder_date' => date('Y-m-d'),
                'status' => $reminderLogStatus,
                'unique_key' => $uniqueKey,
            ]);
        } catch (Throwable $e) {
            return [
                'status' => 'skipped',
                'appointment_id' => $appointmentId,
                'patient_id' => $patientId,
                'message_id' => $messageId,
                'reminder_type' => $reminderType,
                'message' => 'Reminder was already logged.',
            ];
        }

        return [
            'status' => $resultStatus,
            'message_status' => $providerStatus,
            'appointment_id' => $appointmentId,
            'patient_id' => $patientId,
            'message_id' => $messageId,
            'reminder_type' => $reminderType,
            'channel' => $recipient['channel'],
            'recipient' => $recipient['value'],
            'message' => $resultStatus === 'sent'
                ? 'Reminder processed successfully.'
                : (string) ($errorMessage ?: 'Reminder failed.'),
        ];
    }

    public function buildReminderMessage(array $appointment, string $reminderType): string
    {
        if (!$this->isValidReminderType($reminderType)) {
            throw new RuntimeException('Invalid reminder type.');
        }

        $template = $this->templates->findByKey($reminderType);
        $clinic = $this->appointments->getClinicSettings();

        $clinicName = trim((string) ($clinic['clinic_name'] ?? 'Dental Clinic'));
        $clinicContact = trim((string) (
            $clinic['clinic_contact'] ??
            $clinic['contact_number'] ??
            $clinic['phone_number'] ??
            ''
        ));

        $clinicContactText = $clinicContact !== ''
            ? 'For questions, contact us at ' . $clinicContact . '. '
            : '';

        $patientName = $this->personName(
            $appointment['patient_first_name'] ?? '',
            $appointment['patient_middle_name'] ?? '',
            $appointment['patient_last_name'] ?? '',
            'Patient'
        );

        $dentistName = $this->personName(
            $appointment['dentist_first_name'] ?? '',
            $appointment['dentist_middle_name'] ?? '',
            $appointment['dentist_last_name'] ?? '',
            'your dentist'
        );

        if ($dentistName !== 'your dentist') {
            $dentistName = 'Dr. ' . $dentistName;
        }

        $appointmentDate = $this->formatDate($appointment['appointment_date'] ?? '');
        $appointmentTime = $this->formatTimeRange(
            $appointment['start_time'] ?? '',
            $appointment['end_time'] ?? ''
        );

        $serviceName = trim((string) ($appointment['service_name'] ?? ''));

        if ($serviceName === '') {
            $serviceName = 'Dental service';
        }

        $body = is_array($template)
            ? (string) ($template['body'] ?? '')
            : $this->fallbackTemplate($reminderType);

        return strtr($body, [
            '{patient_name}' => $patientName,
            '{appointment_date}' => $appointmentDate,
            '{appointment_time}' => $appointmentTime,
            '{dentist_name}' => $dentistName,
            '{service_name}' => $serviceName,
            '{clinic_name}' => $clinicName,
            '{clinic_contact}' => $clinicContact,
            '{clinic_contact_text}' => $clinicContactText,
        ]);
    }

    public function getReminderTypeByDaysBefore(int $daysBefore): string
    {
        return match ($daysBefore) {
            3 => 'appointment_3_days',
            2 => 'appointment_2_days',
            1 => 'appointment_1_day',
            default => throw new RuntimeException('Invalid reminder day.'),
        };
    }

    private function logSkippedAttempt(array $appointment, string $reminderType, string $reason, ?int $sentBy = null): array
    {
        $appointmentId = (int) ($appointment['appointment_id'] ?? 0);
        $patientId = (int) ($appointment['patient_id'] ?? 0);

        if ($appointmentId <= 0 || $patientId <= 0) {
            return [
                'status' => 'skipped',
                'appointment_id' => $appointmentId,
                'patient_id' => $patientId,
                'reminder_type' => $reminderType,
                'message' => $reason,
            ];
        }

        if ($this->appointments->hasReminderBeenSent($appointmentId, $reminderType)) {
            return [
                'status' => 'skipped',
                'appointment_id' => $appointmentId,
                'patient_id' => $patientId,
                'reminder_type' => $reminderType,
                'message' => 'Reminder already logged for this appointment and reminder type.',
            ];
        }

        $messageId = $this->messageLogs->create([
            'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
            'sent_by' => $sentBy,
            'channel' => 'sms',
            'recipient' => 'not_sent',
            'subject' => null,
            'message_body' => $reason,
            'status' => 'skipped',
            'error_message' => $reason,
        ]);

        try {
            $this->appointments->createReminderLog([
                'appointment_id' => $appointmentId,
                'patient_id' => $patientId,
                'message_id' => $messageId,
                'reminder_type' => $reminderType,
                'reminder_date' => date('Y-m-d'),
                'status' => 'skipped',
                'unique_key' => $this->appointments->buildUniqueKey($appointmentId, $reminderType),
            ]);
        } catch (Throwable $e) {
        }

        return [
            'status' => 'skipped',
            'appointment_id' => $appointmentId,
            'patient_id' => $patientId,
            'message_id' => $messageId,
            'reminder_type' => $reminderType,
            'message' => $reason,
        ];
    }

    private function resolveRecipient(array $appointment): ?array
    {
        $contactNumber = trim((string) ($appointment['patient_contact_number'] ?? ''));
        $email = trim((string) ($appointment['patient_email'] ?? ''));

        if ($contactNumber !== '') {
            return [
                'channel' => 'sms',
                'value' => $contactNumber,
            ];
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'channel' => 'email',
                'value' => $email,
            ];
        }

        return null;
    }

    private function isValidReminderType(string $reminderType): bool
    {
        return in_array($reminderType, [
            'appointment_3_days',
            'appointment_2_days',
            'appointment_1_day',
        ], true);
    }

    private function subjectForReminderType(string $reminderType): string
    {
        return match ($reminderType) {
            'appointment_3_days' => 'Dental Appointment Reminder - In 3 Days',
            'appointment_2_days' => 'Dental Appointment Reminder - In 2 Days',
            'appointment_1_day' => 'Dental Appointment Reminder - Tomorrow',
            default => 'Dental Appointment Reminder',
        };
    }

    private function fallbackTemplate(string $reminderType): string
    {
        $lead = match ($reminderType) {
            'appointment_3_days' => 'Reminder: Your dental appointment is in 3 days.',
            'appointment_2_days' => 'Reminder: Your dental appointment is in 2 days.',
            'appointment_1_day' => 'Reminder: Your dental appointment is tomorrow.',
            default => 'Reminder: You have an upcoming dental appointment.',
        };

        return $lead . ' Hello {patient_name}, this is a reminder from {clinic_name}. You have a dental appointment on {appointment_date} at {appointment_time} with {dentist_name}. Service: {service_name}. Please arrive 10 minutes early. {clinic_contact_text}Thank you.';
    }

    private function personName($first, $middle, $last, string $fallback): string
    {
        $name = trim(implode(' ', array_filter([
            trim((string) $first),
            trim((string) $middle),
            trim((string) $last),
        ])));

        return $name !== '' ? $name : $fallback;
    }

    private function formatDate($date): string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return 'the scheduled date';
        }

        $timestamp = strtotime($date);

        return $timestamp ? date('M d, Y', $timestamp) : $date;
    }

    private function formatTimeRange($start, $end): string
    {
        $start = trim((string) $start);
        $end = trim((string) $end);

        if ($start === '' && $end === '') {
            return 'the scheduled time';
        }

        $startText = $start;
        $endText = $end;

        if ($start !== '') {
            $timestamp = strtotime($start);
            $startText = $timestamp ? date('h:i A', $timestamp) : $start;
        }

        if ($end !== '') {
            $timestamp = strtotime($end);
            $endText = $timestamp ? date('h:i A', $timestamp) : $end;
        }

        if ($startText !== '' && $endText !== '') {
            return $startText . ' - ' . $endText;
        }

        return $startText !== '' ? $startText : $endText;
    }
}