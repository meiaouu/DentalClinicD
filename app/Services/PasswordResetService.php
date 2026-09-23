<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class PasswordResetService
{
    private const OTP_EXPIRY_MINUTES = 10;
    private const RESET_EXPIRY_MINUTES = 15;
    private const MAX_OTP_SENDS = 3;
    private const OTP_SEND_WINDOW_MINUTES = 15;
    private const MAX_OTP_VERIFY_ATTEMPTS = 5;
    private const OTP_LOCKOUT_MINUTES = 15;

    private PDO $db;
    private PasswordResetDeliveryService $delivery;

    /** @var array<string, array<string, bool>> */
    private array $tableColumnsCache = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->delivery = new PasswordResetDeliveryService();
    }

    public function lookupAccount(string $identifier, string $ipAddress, string $userAgent): ?array
{
    $identifier = trim($identifier);

    if ($identifier === '') {
        return null;
    }

    $email = strtolower($identifier);
    $phoneRaw = preg_replace('/[\s-]+/', '', $identifier) ?? '';
    $phoneNormalized = $this->normalizePhilippineMobile($identifier);

    try {
        $db = Database::getConnection();

        /*
         * Important:
         * We search users first, then linked patient profiles only.
         * This prevents an unlinked duplicate patient row with user_id NULL
         * from blocking forgot password.
         */
        $stmt = $db->prepare("
            SELECT
                u.user_id,
                u.username,
                u.email AS user_email,
                u.contact_number AS user_contact_number,
                u.is_active,
                p.patient_id,
                p.email AS patient_email,
                p.contact_number AS patient_contact_number
            FROM users u
            LEFT JOIN patients p
                ON p.user_id = u.user_id
            WHERE
                LOWER(u.username) = LOWER(:identifier)
                OR LOWER(u.email) = LOWER(:email)
                OR u.contact_number = :phone_raw_user
                OR u.contact_number = :phone_normalized_user
                OR (
                    p.user_id IS NOT NULL
                    AND (
                        LOWER(p.email) = LOWER(:patient_email)
                        OR p.contact_number = :phone_raw_patient
                        OR p.contact_number = :phone_normalized_patient
                    )
                )
            ORDER BY
                CASE
                    WHEN LOWER(u.email) = LOWER(:email_order) THEN 1
                    WHEN LOWER(u.username) = LOWER(:identifier_order) THEN 2
                    WHEN p.user_id IS NOT NULL THEN 3
                    ELSE 4
                END
            LIMIT 1
        ");

        $stmt->execute([
            ':identifier' => $identifier,
            ':email' => $email,
            ':phone_raw_user' => $phoneRaw,
            ':phone_normalized_user' => $phoneNormalized,
            ':patient_email' => $email,
            ':phone_raw_patient' => $phoneRaw,
            ':phone_normalized_patient' => $phoneNormalized,
            ':email_order' => $email,
            ':identifier_order' => $identifier,
        ]);

        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->auditPasswordResetAction(
            $user ? (int) $user['user_id'] : null,
            'forgot_password_lookup_submitted',
            'Forgot password lookup submitted.',
            $ipAddress
        );

        if (!$user) {
            return null;
        }

        if (isset($user['is_active']) && (int) $user['is_active'] !== 1) {
            return null;
        }

        $recoveryEmail = strtolower(trim((string) ($user['user_email'] ?: $user['patient_email'] ?: '')));
        $recoveryPhone = trim((string) ($user['user_contact_number'] ?: $user['patient_contact_number'] ?: ''));

        $options = [];

        if ($recoveryEmail !== '' && filter_var($recoveryEmail, FILTER_VALIDATE_EMAIL)) {
            $options['email'] = $this->maskEmail($recoveryEmail);
        }

        if ($recoveryPhone !== '') {
            $options['phone'] = $this->maskPhone($recoveryPhone);
        }

        if (empty($options)) {
            return null;
        }

        return [
            'user_id' => (int) $user['user_id'],
            'options' => $options,
        ];
    } catch (Throwable $e) {
        error_log('[PasswordResetService::lookupAccount] ' . $e->getMessage());
        return null;
    }
}

private function normalizePhilippineMobile(string $phone): string
{
    $phone = preg_replace('/[\s-]+/', '', trim($phone)) ?? '';

    if (preg_match('/^09\d{9}$/', $phone)) {
        return '+63' . substr($phone, 1);
    }

    if (preg_match('/^639\d{9}$/', $phone)) {
        return '+' . $phone;
    }

    if (preg_match('/^\+639\d{9}$/', $phone)) {
        return $phone;
    }

    return $phone;
}

private function isValidPhilippineMobile(string $phone): bool
{
    return preg_match('/^\+639\d{9}$/', $this->normalizePhilippineMobile($phone)) === 1;
}

private function maskEmail(string $email): string
{
    $email = trim($email);

    if (strpos($email, '@') === false) {
        return '********';
    }

    [$local, $domain] = explode('@', $email, 2);
    $length = strlen($local);

    if ($length <= 2) {
        return substr($local, 0, 1) . '***@' . $domain;
    }

    return substr($local, 0, 1)
        . str_repeat('*', max(3, $length - 2))
        . substr($local, -1)
        . '@'
        . $domain;
}

private function maskPhone(string $phone): string
{
    $phone = trim($phone);
    $length = strlen($phone);

    if ($length <= 5) {
        return str_repeat('*', $length);
    }

    return substr($phone, 0, 4)
        . str_repeat('*', max(4, $length - 7))
        . substr($phone, -3);
}

    public function sendOtp(int $userId, string $method, string $ipAddress, string $userAgent): int
    {
        $method = strtolower(trim($method));

        if (!in_array($method, ['email', 'phone'], true)) {
            throw new RuntimeException('Invalid OTP delivery method.');
        }

        $user = $this->findUserById($userId);

        if (!$user) {
            throw new RuntimeException('Unable to process request.');
        }

        $sendCount = $this->countRecentOtpSends($userId, $ipAddress);

        if ($sendCount >= self::MAX_OTP_SENDS) {
            $this->writeAuthAudit(
                $userId,
                'otp_request_limited',
                'OTP request blocked by resend limit.',
                $ipAddress,
                $userAgent
            );

            throw new RuntimeException('Too many OTP requests. Please try again later.');
        }

        $destination = $method === 'email'
            ? trim((string) ($user['email'] ?? ''))
            : trim((string) ($user['contact_number'] ?? ''));

        if ($destination === '') {
            throw new RuntimeException('Selected recovery method is unavailable.');
        }

        $otp = (string) random_int(100000, 999999);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);
        $masked = $method === 'email'
            ? $this->maskEmail($destination)
            : $this->maskPhone($destination);

        $stmt = $this->db->prepare("
            INSERT INTO password_reset_otps (
                user_id,
                delivery_method,
                destination_masked,
                otp_hash,
                expires_at,
                verified_at,
                reset_expires_at,
                used_at,
                resend_count,
                failed_attempts,
                locked_until,
                ip_address,
                user_agent,
                created_at
            ) VALUES (
                :user_id,
                :delivery_method,
                :destination_masked,
                :otp_hash,
                DATE_ADD(NOW(), INTERVAL " . self::OTP_EXPIRY_MINUTES . " MINUTE),
                NULL,
                NULL,
                NULL,
                :resend_count,
                0,
                NULL,
                :ip_address,
                :user_agent,
                NOW()
            )
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':delivery_method' => $method,
            ':destination_masked' => $masked,
            ':otp_hash' => $otpHash,
            ':resend_count' => $sendCount + 1,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
        ]);

        $otpId = (int) $this->db->lastInsertId();

        $this->writeAuthAudit(
            $userId,
            'otp_requested',
            'Password reset OTP was requested using ' . $method . '.',
            $ipAddress,
            $userAgent
        );

        if ($method === 'email') {
            $this->delivery->sendEmailOtp($destination, $otp);
        } else {
            $this->delivery->sendPhoneOtp($destination, $otp);
        }

        $this->writeAuthAudit(
            $userId,
            'otp_sent',
            'Password reset OTP delivery was processed using ' . $method . '.',
            $ipAddress,
            $userAgent
        );

        unset($otp);

        return $otpId;
    }

    public function verifyOtp(int $otpId, int $userId, string $otp, string $ipAddress, string $userAgent): void
    {
        $otp = trim($otp);

        if ($otp === '' || !preg_match('/^\d{6}$/', $otp)) {
            throw new RuntimeException('Invalid OTP.');
        }

        $row = $this->findOtpForUser($otpId, $userId);

        if (!$row) {
            throw new RuntimeException('Invalid or expired OTP.');
        }

        if (!empty($row['used_at'])) {
            $this->writeAuthAudit(
                $userId,
                'password_reset_reused_attempt',
                'Used OTP was submitted again.',
                $ipAddress,
                $userAgent
            );

            throw new RuntimeException('Invalid or expired OTP.');
        }

        if (!empty($row['locked_until']) && strtotime((string) $row['locked_until']) > time()) {
            throw new RuntimeException('Too many wrong OTP attempts. Please try again after 15 minutes.');
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            $this->writeAuthAudit(
                $userId,
                'password_reset_otp_expired',
                'Expired OTP was submitted.',
                $ipAddress,
                $userAgent
            );

            throw new RuntimeException('Invalid or expired OTP.');
        }

        if (!password_verify($otp, (string) $row['otp_hash'])) {
            $failedAttempts = (int) $row['failed_attempts'] + 1;
            $lockedUntilSql = $failedAttempts >= self::MAX_OTP_VERIFY_ATTEMPTS
                ? 'DATE_ADD(NOW(), INTERVAL ' . self::OTP_LOCKOUT_MINUTES . ' MINUTE)'
                : 'NULL';

            $stmt = $this->db->prepare("
                UPDATE password_reset_otps
                SET failed_attempts = :failed_attempts,
                    locked_until = {$lockedUntilSql}
                WHERE otp_id = :otp_id
                  AND user_id = :user_id
                LIMIT 1
            ");

            $stmt->execute([
                ':failed_attempts' => $failedAttempts,
                ':otp_id' => $otpId,
                ':user_id' => $userId,
            ]);

            $this->writeAuthAudit(
                $userId,
                'otp_verification_failed',
                'Wrong password reset OTP was submitted.',
                $ipAddress,
                $userAgent
            );

            throw new RuntimeException(
                $failedAttempts >= self::MAX_OTP_VERIFY_ATTEMPTS
                    ? 'Too many wrong OTP attempts. Please try again after 15 minutes.'
                    : 'Invalid OTP.'
            );
        }

        $stmt = $this->db->prepare("
            UPDATE password_reset_otps
            SET verified_at = NOW(),
                reset_expires_at = DATE_ADD(NOW(), INTERVAL " . self::RESET_EXPIRY_MINUTES . " MINUTE)
            WHERE otp_id = :otp_id
              AND user_id = :user_id
              AND used_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            ':otp_id' => $otpId,
            ':user_id' => $userId,
        ]);

        $this->writeAuthAudit(
            $userId,
            'otp_verification_successful',
            'Password reset OTP was verified successfully.',
            $ipAddress,
            $userAgent
        );
    }

    public function canResetPassword(int $otpId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT otp_id
            FROM password_reset_otps
            WHERE otp_id = :otp_id
              AND user_id = :user_id
              AND verified_at IS NOT NULL
              AND used_at IS NULL
              AND reset_expires_at IS NOT NULL
              AND reset_expires_at > NOW()
            LIMIT 1
        ");

        $stmt->execute([
            ':otp_id' => $otpId,
            ':user_id' => $userId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function resetPassword(
        int $otpId,
        int $userId,
        string $newPassword,
        string $confirmPassword,
        string $ipAddress,
        string $userAgent
    ): void {
        if (!$this->canResetPassword($otpId, $userId)) {
            $this->writeAuthAudit(
                $userId,
                'password_reset_token_expired_or_reused',
                'Password reset page was accessed with expired or used verification.',
                $ipAddress,
                $userAgent
            );

            throw new RuntimeException('Password reset session expired. Please request a new OTP.');
        }

        if (strlen($newPassword) < 8) {
            throw new RuntimeException('New password must be at least 8 characters.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('Password confirmation does not match.');
        }

        $user = $this->findUserById($userId);

        if (!$user) {
            throw new RuntimeException('Unable to reset password.');
        }

        $currentHash = (string) ($user['password'] ?? '');

        if ($currentHash !== '' && password_verify($newPassword, $currentHash)) {
            throw new RuntimeException('New password must be different from the current password.');
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                UPDATE users
                SET password = :password,
                    updated_at = NOW()
                WHERE user_id = :user_id
                LIMIT 1
            ");

            $stmt->execute([
                ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':user_id' => $userId,
            ]);

            $usedStmt = $this->db->prepare("
                UPDATE password_reset_otps
                SET used_at = NOW()
                WHERE otp_id = :otp_id
                  AND user_id = :user_id
                  AND used_at IS NULL
                LIMIT 1
            ");

            $usedStmt->execute([
                ':otp_id' => $otpId,
                ':user_id' => $userId,
            ]);

            $this->db->commit();

            $this->writeAuthAudit(
                $userId,
                'password_reset_completed',
                'User password was reset using OTP verification.',
                $ipAddress,
                $userAgent
            );
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('Password reset failed: ' . $e->getMessage());

            throw new RuntimeException('Unable to reset password. Please try again.');
        }
    }

    public function createManualRecoveryRequest(array $data, string $ipAddress, string $userAgent): int
    {
        $requesterType = strtolower(trim((string) ($data['requester_type'] ?? 'unknown')));
        $allowedTypes = ['patient', 'staff', 'dentist', 'admin', 'owner', 'unknown'];

        if (!in_array($requesterType, $allowedTypes, true)) {
            $requesterType = 'unknown';
        }

        $fullName = trim((string) ($data['full_name'] ?? ''));

        if ($fullName === '') {
            throw new RuntimeException('Full name is required.');
        }

        if (empty($data['privacy_consent'])) {
            throw new RuntimeException('Privacy consent is required.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO account_recovery_requests (
                requester_type,
                full_name,
                birth_date,
                last_known_email,
                last_known_phone,
                appointment_or_request_code,
                message,
                privacy_consent,
                status,
                ip_address,
                user_agent,
                created_at,
                updated_at
            ) VALUES (
                :requester_type,
                :full_name,
                :birth_date,
                :last_known_email,
                :last_known_phone,
                :appointment_or_request_code,
                :message,
                1,
                'pending',
                :ip_address,
                :user_agent,
                NOW(),
                NULL
            )
        ");

        $stmt->execute([
            ':requester_type' => $requesterType,
            ':full_name' => $fullName,
            ':birth_date' => trim((string) ($data['birth_date'] ?? '')) ?: null,
            ':last_known_email' => trim((string) ($data['last_known_email'] ?? '')) ?: null,
            ':last_known_phone' => trim((string) ($data['last_known_phone'] ?? '')) ?: null,
            ':appointment_or_request_code' => trim((string) ($data['appointment_or_request_code'] ?? '')) ?: null,
            ':message' => trim((string) ($data['message'] ?? '')) ?: null,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
        ]);

        $requestId = (int) $this->db->lastInsertId();

        $this->writeAuthAudit(
            null,
            'manual_account_recovery_request_created',
            'Manual account recovery request was created.',
            $ipAddress,
            $userAgent
        );

        return $requestId;
    }

    private function findUserByIdentifier(string $identifier): ?array
    {
        $variants = $this->identifierVariants($identifier);

        if (empty($variants)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($variants), '?'));

        $stmt = $this->db->prepare("
            SELECT u.*, r.role_name
            FROM users u
            INNER JOIN roles r ON r.role_id = u.role_id
            WHERE u.username IN ($placeholders)
               OR u.email IN ($placeholders)
               OR u.contact_number IN ($placeholders)
            LIMIT 1
        ");

        $stmt->execute(array_merge($variants, $variants, $variants));

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    private function findUserById(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.role_name
            FROM users u
            INNER JOIN roles r ON r.role_id = u.role_id
            WHERE u.user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    private function identifierVariants(string $identifier): array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return [];
        }

        $variants = [
            $identifier,
            strtolower($identifier),
        ];

        $digits = preg_replace('/\D+/', '', $identifier);

        if ($digits !== '') {
            if (preg_match('/^09\d{9}$/', $digits)) {
                $variants[] = $digits;
                $variants[] = '63' . substr($digits, 1);
                $variants[] = '+63' . substr($digits, 1);
            } elseif (preg_match('/^639\d{9}$/', $digits)) {
                $variants[] = $digits;
                $variants[] = '+' . $digits;
                $variants[] = '0' . substr($digits, 2);
            }
        }

        return array_values(array_unique(array_filter($variants, static function ($value): bool {
            return trim((string) $value) !== '';
        })));
    }

    private function countRecentOtpSends(int $userId, string $ipAddress): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM password_reset_otps
            WHERE user_id = :user_id
              AND ip_address = :ip_address
              AND created_at >= DATE_SUB(NOW(), INTERVAL " . self::OTP_SEND_WINDOW_MINUTES . " MINUTE)
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':ip_address' => $ipAddress,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function findOtpForUser(int $otpId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM password_reset_otps
            WHERE otp_id = :otp_id
              AND user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':otp_id' => $otpId,
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function writeAuthAudit(
        ?int $userId,
        string $action,
        string $description,
        string $ipAddress,
        string $userAgent
    ): void {
        try {
            $columns = $this->tableColumns('audit_logs');

            if (empty($columns)) {
                return;
            }

            $data = [];

            if (isset($columns['user_id'])) {
                $data['user_id'] = $userId;
            }

            if (isset($columns['action'])) {
                $data['action'] = $action;
            }

            if (isset($columns['entity_type'])) {
                $data['entity_type'] = 'authentication';
            }

            if (isset($columns['entity_id'])) {
                $data['entity_id'] = $userId;
            }

            if (isset($columns['module_name'])) {
                $data['module_name'] = 'authentication';
            }

            if (isset($columns['action_name'])) {
                $data['action_name'] = $action;
            }

            if (isset($columns['record_type'])) {
                $data['record_type'] = 'user';
            }

            if (isset($columns['record_id'])) {
                $data['record_id'] = $userId !== null ? (string) $userId : null;
            }

            if (isset($columns['description'])) {
                $data['description'] = $description;
            }

            if (isset($columns['ip_address'])) {
                $data['ip_address'] = substr($ipAddress, 0, 45);
            }

            if (isset($columns['user_agent'])) {
                $data['user_agent'] = substr($userAgent, 0, 255);
            }

            if (empty($data)) {
                return;
            }

            $insertColumns = array_keys($data);
            $placeholders = array_map(static fn (string $column): string => ':' . $column, $insertColumns);

            if (isset($columns['created_at'])) {
                $insertColumns[] = 'created_at';
                $placeholders[] = 'NOW()';
            }

            $sql = "
                INSERT INTO audit_logs (" . implode(', ', $insertColumns) . ")
                VALUES (" . implode(', ', $placeholders) . ")
            ";

            $stmt = $this->db->prepare($sql);

            foreach ($data as $column => $value) {
                if ($value === null) {
                    $stmt->bindValue(':' . $column, null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':' . $column, $value);
                }
            }

            $stmt->execute();
        } catch (Throwable $e) {
            error_log('Password reset audit failed: ' . $e->getMessage());
        }
    }

    private function tableColumns(string $table): array
    {
        if (isset($this->tableColumnsCache[$table])) {
            return $this->tableColumnsCache[$table];
        }

        try {
            $stmt = $this->db->query("DESCRIBE `{$table}`");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $columns = [];

            foreach ($rows as $row) {
                $field = strtolower((string) ($row['Field'] ?? ''));

                if ($field !== '') {
                    $columns[$field] = true;
                }
            }

            $this->tableColumnsCache[$table] = $columns;

            return $columns;
        } catch (Throwable $e) {
            $this->tableColumnsCache[$table] = [];

            return [];
        }
    }

private function auditPasswordResetAction(
    ?int $userId,
    string $action,
    string $description,
    string $ipAddress
): void {
    try {
        $db = Database::getConnection();

        $stmt = $db->prepare("
            INSERT INTO audit_logs (
                user_id,
                module_name,
                action_name,
                record_type,
                record_id,
                description,
                ip_address,
                created_at
            ) VALUES (
                :user_id,
                'authentication',
                :action_name,
                'password_reset',
                :record_id,
                :description,
                :ip_address,
                NOW()
            )
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':action_name' => $action,
            ':record_id' => $userId !== null ? (string) $userId : null,
            ':description' => $description,
            ':ip_address' => $ipAddress,
        ]);
    } catch (Throwable $e) {
        error_log('[PasswordResetService::auditPasswordResetAction] ' . $e->getMessage());
    }
}





}