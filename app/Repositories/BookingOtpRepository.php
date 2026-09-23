<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class BookingOtpRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO booking_otps (
                verification_channel,
                destination,
                contact_number,
                email,
                service_id,
                service_ids_json,
                otp_hash,
                attempts,
                max_attempts,
                expires_at,
                last_sent_at,
                ip_address,
                user_agent,
                created_at,
                updated_at
            ) VALUES (
                :verification_channel,
                :destination,
                :contact_number,
                :email,
                :service_id,
                :service_ids_json,
                :otp_hash,
                0,
                :max_attempts,
                :expires_at,
                NOW(),
                :ip_address,
                :user_agent,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'verification_channel' => $data['verification_channel'],
            'destination' => $data['destination'],
            'contact_number' => $data['contact_number'],
            'email' => $data['email'],
            'service_id' => $data['service_id'],
            'service_ids_json' => $data['service_ids_json'],
            'otp_hash' => $data['otp_hash'],
            'max_attempts' => $data['max_attempts'],
            'expires_at' => $data['expires_at'],
            'ip_address' => $data['ip_address'],
            'user_agent' => $data['user_agent'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findPendingById(int $otpId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM booking_otps
            WHERE otp_id = :otp_id
              AND consumed_at IS NULL
              AND expires_at > NOW()
            LIMIT 1
        ");

        $stmt->execute([
            'otp_id' => $otpId,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function incrementAttempts(int $otpId): void
    {
        $stmt = $this->db->prepare("
            UPDATE booking_otps
            SET attempts = attempts + 1,
                updated_at = NOW()
            WHERE otp_id = :otp_id
        ");

        $stmt->execute([
            'otp_id' => $otpId,
        ]);
    }

    public function markConsumed(int $otpId): void
    {
        $stmt = $this->db->prepare("
            UPDATE booking_otps
            SET consumed_at = NOW(),
                updated_at = NOW()
            WHERE otp_id = :otp_id
              AND consumed_at IS NULL
        ");

        $stmt->execute([
            'otp_id' => $otpId,
        ]);
    }

    public function updateForResend(int $otpId, string $otpHash, string $expiresAt): void
    {
        $stmt = $this->db->prepare("
            UPDATE booking_otps
            SET otp_hash = :otp_hash,
                attempts = 0,
                expires_at = :expires_at,
                last_sent_at = NOW(),
                updated_at = NOW()
            WHERE otp_id = :otp_id
              AND consumed_at IS NULL
        ");

        $stmt->execute([
            'otp_hash' => $otpHash,
            'expires_at' => $expiresAt,
            'otp_id' => $otpId,
        ]);
    }

    public function countRecentByDestination(string $destination, int $minutes = 10): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM booking_otps
            WHERE destination = :destination
              AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");

        $stmt->bindValue(':destination', $destination);
        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}