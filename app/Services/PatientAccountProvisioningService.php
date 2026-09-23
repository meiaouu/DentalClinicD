<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\PatientRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

class PatientAccountProvisioningService
{
    private UserRepository $users;
    private PatientRepository $patients;
    private PDO $db;
    private string $baseUrl = '/DentalClinic/public';

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->patients = new PatientRepository();
        $this->db = Database::getConnection();
    }

    public function createPendingAccountForPatient(array $patient): array
    {
        $patientId = (int) ($patient['patient_id'] ?? 0);

        if ($patientId <= 0) {
            throw new RuntimeException('Invalid patient record.');
        }

        if (!empty($patient['user_id'])) {
            return [
                'created' => false,
                'user_id' => (int) $patient['user_id'],
                'token' => null,
                'setup_url' => null,
                'expires_at' => null,
                'sent' => false,
                'message' => 'Patient account already exists.',
            ];
        }

        $email = trim((string) ($patient['email'] ?? ''));
        $phone = trim((string) ($patient['contact_number'] ?? ''));

        if ($email === '' && $phone === '') {
            return [
                'created' => false,
                'user_id' => null,
                'token' => null,
                'setup_url' => null,
                'expires_at' => null,
                'sent' => false,
                'message' => 'Patient has no email or phone for account setup.',
            ];
        }

        if ($email !== '') {
            $existingUser = $this->users->findByEmail($email);

            if ($existingUser) {
                throw new RuntimeException('Patient account already exists.');
            }
        }

        if ($phone !== '' && $this->users->findByContactNumber($phone)) {
            throw new RuntimeException('An existing user account was found using this contact number. Connect it instead.');
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))
            ->modify('+24 hours')
            ->format('Y-m-d H:i:s');

        $userId = $this->users->createPendingPatientAccount($patient, $tokenHash, $expiresAt);

        if ($userId <= 0) {
            throw new RuntimeException('Unable to create pending patient account.');
        }

        $this->patients->linkUserAccount($patientId, $userId);

        $setupUrl = $this->baseUrl . '/setup-password?token=' . rawurlencode($token);
        $sent = false;

        if ($email !== '') {
            try {
                $this->sendPasswordSetupLink($email, $token);
                $sent = true;
            } catch (Throwable $mailError) {
                error_log('[PatientAccountProvisioningService] Password setup email failed: ' . $mailError->getMessage());
            }
        } else {
            error_log('[PatientAccountProvisioningService] SMS delivery is not configured. Setup URL: ' . $setupUrl);
        }

        return [
            'created' => true,
            'user_id' => $userId,
            'token' => $token,
            'setup_url' => $setupUrl,
            'expires_at' => $expiresAt,
            'sent' => $sent,
            'message' => $sent
                ? 'Password setup link was sent.'
                : 'Password setup token was created. Configure email/SMS delivery to send it automatically.',
        ];
    }

    public function validatePasswordSetupToken(string $token): ?array
    {
        $token = trim($token);

        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return null;
        }

        $tokenHash = hash('sha256', $token);
        $user = $this->users->findByPasswordSetupTokenHash($tokenHash);

        if (!$user) {
            return null;
        }

        if ((string) ($user['account_status'] ?? '') !== 'pending_verification') {
            return null;
        }

        $expiresAt = trim((string) ($user['password_setup_expires_at'] ?? ''));

        if ($expiresAt === '') {
            return null;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $expiry = new DateTimeImmutable($expiresAt, new DateTimeZone('Asia/Manila'));

        if ($expiry < $now) {
            return null;
        }

        return $user;
    }

    public function activateAccount(string $token, string $password, string $passwordConfirmation): void
    {
        $user = $this->validatePasswordSetupToken($token);

        if (!$user) {
            throw new RuntimeException('Setup link is invalid or expired.');
        }

        if ($password !== $passwordConfirmation) {
            throw new RuntimeException('Password confirmation does not match.');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('Password must be at least 8 characters long.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $this->db->beginTransaction();

        try {
            $activated = $this->users->activateAccountWithPassword((int) $user['user_id'], $passwordHash);

            if (!$activated) {
                throw new RuntimeException('Unable to activate account.');
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function sendPasswordSetupLink(string $email, string $token): void
    {
        $email = trim($email);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $setupUrl = $this->baseUrl . '/setup-password?token=' . rawurlencode($token);
        $subject = 'Set up your dental clinic patient portal account';

        $message =
            "Hello,\n\n" .
            "Your dental clinic appointment was confirmed. Set up your patient portal password using this link:\n\n" .
            $setupUrl . "\n\n" .
            "This link expires in 24 hours. If you did not request this, please ignore this message.\n";

        $headers = "Content-Type: text/plain; charset=UTF-8\r\n";

        if (function_exists('mail')) {
            $sent = @mail($email, $subject, $message, $headers);

            if ($sent) {
                return;
            }
        }

        error_log('[PatientAccountProvisioningService] Configure email service. Password setup URL for ' . $email . ': ' . $setupUrl);
    }
}