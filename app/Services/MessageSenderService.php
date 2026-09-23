<?php

namespace App\Services;

use Throwable;

class MessageSenderService
{
    public function sendSms(string $contactNumber, string $message): array
    {
        $contactNumber = trim($contactNumber);
        $message = trim($message);

        if ($contactNumber === '') {
            return [
                'status' => 'failed',
                'provider_response' => null,
                'error_message' => 'SMS recipient contact number is empty.',
            ];
        }

        if ($message === '') {
            return [
                'status' => 'failed',
                'provider_response' => null,
                'error_message' => 'SMS message body is empty.',
            ];
        }

        /*
            Development mode:
            No real SMS provider is configured yet.

            Later, replace this block with Semaphore, Twilio, or another provider.
            Never expose the provider API key in JavaScript.
        */
        return [
            'status' => 'sent',
            'provider_response' => 'Simulated SMS sent to ' . $contactNumber,
            'error_message' => null,
        ];
    }

    public function sendEmail(string $email, string $subject, string $message): array
    {
        $email = trim($email);
        $subject = trim($subject);
        $message = trim($message);

        if ($email === '') {
            return [
                'status' => 'failed',
                'provider_response' => null,
                'error_message' => 'Email recipient is empty.',
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 'failed',
                'provider_response' => null,
                'error_message' => 'Email recipient is invalid.',
            ];
        }

        $mailEnabled = strtolower((string) getenv('MAIL_ENABLED')) === 'true';

        if (!$mailEnabled) {
            return [
                'status' => 'queued',
                'provider_response' => 'Email queued. MAIL_ENABLED is not true.',
                'error_message' => null,
            ];
        }

        try {
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/plain; charset=UTF-8',
                'From: Dental Clinic <no-reply@localhost>',
            ];

            $sent = mail($email, $subject, $message, implode("\r\n", $headers));

            if (!$sent) {
                return [
                    'status' => 'failed',
                    'provider_response' => null,
                    'error_message' => 'PHP mail() returned false.',
                ];
            }

            return [
                'status' => 'sent',
                'provider_response' => 'Email sent using PHP mail().',
                'error_message' => null,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'failed',
                'provider_response' => null,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    public function sendInternal(int $patientId, string $message): array
    {
        if ($patientId <= 0) {
            return [
                'status' => 'failed',
                'provider_response' => null,
                'error_message' => 'Invalid patient reference for internal message.',
            ];
        }

        if (trim($message) === '') {
            return [
                'status' => 'failed',
                'provider_response' => null,
                'error_message' => 'Internal message body is empty.',
            ];
        }

        return [
            'status' => 'sent',
            'provider_response' => 'Internal message simulated for patient #' . $patientId,
            'error_message' => null,
        ];
    }
}