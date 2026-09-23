<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

class OtpDeliveryService
{
    private array $config;

    public function __construct()
    {
        $configPath = dirname(__DIR__, 2) . '/config/otp.php';

        $this->config = is_file($configPath)
            ? require $configPath
            : [];
    }

    public function send(string $channel, string $destination, string $otp): bool
    {
        if ($channel === 'sms') {
            return $this->sendSms($destination, $otp);
        }

        if ($channel === 'email') {
            return $this->sendEmail($destination, $otp);
        }

        throw new RuntimeException('Unsupported OTP channel.');
    }

    private function sendSms(string $number, string $otp): bool
    {
        $smsConfig = $this->config['sms'] ?? [];

        if (empty($smsConfig['enabled'])) {
            throw new RuntimeException('SMS OTP is disabled.');
        }

        $apiKey = trim((string) ($smsConfig['api_key'] ?? ''));

        if ($apiKey === '' || $apiKey === 'PUT_YOUR_SEMAPHORE_API_KEY_HERE') {
            throw new RuntimeException('Semaphore API key is not configured.');
        }

        $message = 'Your dental clinic verification code is ' . $otp . '. This code expires in 10 minutes. Do not share this code.';

        $payload = [
            'apikey' => $apiKey,
            'number' => $number,
            'message' => $message,
        ];

        $senderName = trim((string) ($smsConfig['sender_name'] ?? ''));

        if ($senderName !== '') {
            $payload['sendername'] = $senderName;
        }

        $ch = curl_init('https://api.semaphore.co/api/v4/messages');

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('SMS sending failed. ' . ($curlError !== '' ? $curlError : 'Please check your Semaphore account and API key.'));
        }

        return true;
    }

    private function sendEmail(string $email, string $otp): bool
    {
        $emailConfig = $this->config['email'] ?? [];

        if (empty($emailConfig['enabled'])) {
            throw new RuntimeException('Email OTP is disabled.');
        }

        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException('PHPMailer is not installed. Run: composer require phpmailer/phpmailer');
        }

        $username = trim((string) ($emailConfig['username'] ?? ''));
        $password = trim((string) ($emailConfig['password'] ?? ''));

        if ($username === '' || $password === '' || $password === 'PUT_YOUR_GMAIL_APP_PASSWORD_HERE') {
            throw new RuntimeException('Gmail SMTP credentials are not configured.');
        }

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = (string) ($emailConfig['host'] ?? 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = (string) ($emailConfig['encryption'] ?? 'tls');
        $mail->Port = (int) ($emailConfig['port'] ?? 587);

        $mail->setFrom(
            (string) ($emailConfig['from_email'] ?? $username),
            (string) ($emailConfig['from_name'] ?? 'Dental Clinic')
        );

        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your Dental Clinic Verification Code';

        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        $mail->Body = '
            <div style="font-family:Arial,sans-serif;line-height:1.6;color:#111827">
                <h2>Dental Clinic Verification</h2>
                <p>Your verification code is:</p>
                <p style="font-size:28px;font-weight:700;letter-spacing:4px">' . $safeOtp . '</p>
                <p>This code expires in 10 minutes. Do not share this code.</p>
            </div>
        ';

        $mail->AltBody = 'Your dental clinic verification code is ' . $otp . '. This code expires in 10 minutes.';

        $mail->send();

        return true;
    }
}