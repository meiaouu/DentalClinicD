<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

class PasswordResetDeliveryService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function sendEmailOtp(string $email, string $otp): bool
    {
        $subject = 'Dental Clinic Password Reset OTP';
        $message = "Your Dental Clinic password reset OTP is: {$otp}\n\n"
            . "This OTP expires in 10 minutes. If you did not request this, please ignore this message.";

        $sent = false;

        try {
            $sent = @mail($email, $subject, $message);
        } catch (Throwable $e) {
            error_log('Email OTP sending failed: ' . $e->getMessage());
            $sent = false;
        }

        $this->logDelivery('email', $email, $subject, $sent ? 'sent' : 'skipped');

        return $sent;
    }

    public function sendPhoneOtp(string $phone, string $otp): bool
    {
        /*
         * SMS provider is not configured yet.
         * Do NOT log/store the OTP here.
         * Connect this method to your SMS gateway later.
         */

        $this->logDelivery(
            'sms',
            $phone,
            'Password Reset OTP',
            'skipped',
            'SMS provider is not configured. OTP value was not logged.'
        );

        return false;
    }

    private function logDelivery(
        string $channel,
        string $recipient,
        string $subject,
        string $status,
        ?string $errorMessage = null
    ): void {
        try {
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
                    NULL,
                    NULL,
                    NULL,
                    :channel,
                    :recipient,
                    :subject,
                    :message_body,
                    :status,
                    NULL,
                    :error_message,
                    CASE WHEN :status_for_sent = 'sent' THEN NOW() ELSE NULL END,
                    NOW()
                )
            ");

            $stmt->execute([
                ':channel' => $channel,
                ':recipient' => $recipient,
                ':subject' => $subject,
                ':message_body' => 'Password reset OTP delivery processed. OTP value is not stored in logs.',
                ':status' => $status,
                ':error_message' => $errorMessage,
                ':status_for_sent' => $status,
            ]);
        } catch (Throwable $e) {
            error_log('OTP delivery log failed: ' . $e->getMessage());
        }
    }
}