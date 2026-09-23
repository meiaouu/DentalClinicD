<?php

namespace App\Controllers\Auth;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Services\PasswordResetService;
use PDO;
use Throwable;

class ForgotPasswordController
{
    private PasswordResetService $passwordReset;

    public function __construct()
    {
        $this->passwordReset = new PasswordResetService();
    }

    public function show(): void
{
    Session::remove('password_reset_options');

    View::render('auth.forgot-password.index', [
        'errors' => Session::get('errors', []),
        'success' => Session::get('success'),
        'old' => Session::get('old', []),
    ]);

    Session::remove('errors');
    Session::remove('success');
    Session::remove('old');
}

    public function lookup(): void
    {
        if (!$this->verifyCsrf()) {
            $this->backToForgot([
                'forgot' => ['Invalid request. Please refresh the page and try again.'],
            ]);
        }

        $identifier = trim((string) ($_POST['identifier'] ?? ''));

        if ($identifier === '') {
            Session::set('old', ['identifier' => $identifier]);

            $this->backToForgot([
                'forgot' => ['Please enter your username, email, or mobile number.'],
            ]);
        }

        try {
            $result = $this->passwordReset->lookupAccount(
                $identifier,
                $this->ipAddress(),
                $this->userAgent()
            );

            if (!$result) {
                Session::remove('password_reset_lookup');
                Session::remove('password_reset_options');
                Session::remove('forgot_password_user_id');

                Session::set('success', 'If the account exists, recovery options will be available.');
                Session::set('old', ['identifier' => $identifier]);

                header('Location: ' . $this->url('/forgot-password'));
                exit;
            }

            $userId = (int) ($result['user_id'] ?? 0);
            $options = is_array($result['options'] ?? null) ? $result['options'] : [];

            if ($userId <= 0 || empty($options)) {
                Session::remove('password_reset_lookup');
                Session::remove('password_reset_options');
                Session::remove('forgot_password_user_id');

                Session::set('success', 'If the account exists, recovery options will be available.');
                Session::set('old', ['identifier' => $identifier]);

                header('Location: ' . $this->url('/forgot-password'));
                exit;
            }

            Session::set('forgot_password_user_id', $userId);

            Session::set('password_reset_lookup', [
                'user_id' => $userId,
                'methods' => $options,
                'expires_at' => time() + 600,
            ]);

            Session::set('password_reset_options', $options);

header('Location: ' . $this->url('/forgot-password/choose-method'));
exit;
        } catch (Throwable $e) {
            error_log('[ForgotPasswordController::lookup] ' . $e->getMessage());

            Session::remove('password_reset_lookup');
            Session::remove('password_reset_options');
            Session::remove('forgot_password_user_id');

            Session::set('success', 'If the account exists, recovery options will be available.');
            Session::set('old', ['identifier' => $identifier]);

            header('Location: ' . $this->url('/forgot-password'));
            exit;
        }
    }

    public function sendResetLink(): void
    {
        if (!$this->verifyCsrf()) {
            $this->backToForgot([
                'forgot' => ['Invalid request. Please refresh the page and try again.'],
            ], true);
        }

        $lookup = Session::get('password_reset_lookup');

        if (
            !is_array($lookup)
            || empty($lookup['user_id'])
            || (int) ($lookup['expires_at'] ?? 0) < time()
        ) {
            Session::remove('password_reset_lookup');
            Session::remove('password_reset_options');
            Session::remove('forgot_password_user_id');

            $this->backToForgot([
                'forgot' => ['Your recovery session expired. Please try again.'],
            ]);
        }

        $method = strtolower(trim((string) ($_POST['delivery_method'] ?? '')));
        $methods = is_array($lookup['methods'] ?? null) ? $lookup['methods'] : [];

        if (!in_array($method, ['email', 'phone'], true) || !isset($methods[$method])) {
            $this->backToForgot([
                'forgot' => ['Please choose a valid recovery option.'],
            ], true);
        }

        $userId = (int) $lookup['user_id'];

        try {
            $db = Database::getConnection();

            $stmt = $db->prepare("
                SELECT user_id, email, contact_number, username, first_name, last_name, password
                FROM users
                WHERE user_id = :user_id
                LIMIT 1
            ");

            $stmt->execute([
                ':user_id' => $userId,
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                Session::remove('password_reset_lookup');
                Session::remove('password_reset_options');
                Session::remove('forgot_password_user_id');

                Session::set('success', 'If the account exists, a reset link will be sent.');

                header('Location: ' . $this->url('/forgot-password'));
                exit;
            }

            $email = strtolower(trim((string) ($user['email'] ?? '')));
            $phone = $this->normalizePhilippineMobile((string) ($user['contact_number'] ?? ''));

            $destination = $method === 'email' ? $email : $phone;

            if ($destination === '') {
                $this->backToForgot([
                    'forgot' => ['Selected recovery method is not available for this account.'],
                ], true);
            }

            if ($method === 'email' && !filter_var($destination, FILTER_VALIDATE_EMAIL)) {
                $this->backToForgot([
                    'forgot' => ['The account email is invalid. Please contact the clinic.'],
                ], true);
            }

            if ($method === 'phone' && !$this->isValidPhilippineMobile($destination)) {
                $this->backToForgot([
                    'forgot' => ['The account phone number is invalid. Please contact the clinic.'],
                ], true);
            }

            if ($this->countRecentResetLinks($userId, $this->ipAddress()) >= 3) {
                $this->backToForgot([
                    'forgot' => ['Too many reset requests. Please try again after 15 minutes.'],
                ], true);
            }

            $plainToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $plainToken);

            $destinationMasked = $method === 'email'
                ? $this->maskEmail($destination)
                : $this->maskPhone($destination);

            $insert = $db->prepare("
                INSERT INTO password_reset_tokens (
                    user_id,
                    delivery_method,
                    destination_masked,
                    token_hash,
                    expires_at,
                    used_at,
                    ip_address,
                    user_agent,
                    created_at
                ) VALUES (
                    :user_id,
                    :delivery_method,
                    :destination_masked,
                    :token_hash,
                    DATE_ADD(NOW(), INTERVAL 15 MINUTE),
                    NULL,
                    :ip_address,
                    :user_agent,
                    NOW()
                )
            ");

            $insert->execute([
                ':user_id' => $userId,
                ':delivery_method' => $method,
                ':destination_masked' => $destinationMasked,
                ':token_hash' => $tokenHash,
                ':ip_address' => $this->ipAddress(),
                ':user_agent' => $this->userAgent(),
            ]);

            $resetLink = $this->absoluteUrl('/forgot-password/reset?token=' . urlencode($plainToken));

            $sent = $method === 'email'
                ? $this->sendPasswordResetEmail($destination, $resetLink)
                : $this->sendPasswordResetSms($destination, $resetLink);

            unset($plainToken);

            if (!$sent) {
                Session::set('errors', [
                    'forgot' => [
                        $method === 'email'
                            ? 'Reset email could not be sent. Please check your Gmail SMTP settings.'
                            : 'Reset SMS could not be sent right now. Please try email or contact the clinic.',
                    ],
                ]);

                header('Location: ' . $this->url('/forgot-password?options=1'));
                exit;
            }

            Session::remove('password_reset_lookup');
            Session::remove('password_reset_options');
            Session::remove('forgot_password_user_id');
            Session::remove('password_reset_otp');
            Session::remove('password_reset_verified');

            Session::set(
                'success',
                'A password reset link was sent to your selected recovery option. The link expires in 15 minutes.'
            );

            header('Location: ' . $this->url('/login'));
            exit;
        } catch (Throwable $e) {
            error_log('[ForgotPasswordController::sendResetLink] ' . $e->getMessage());

            $this->backToForgot([
                'forgot' => ['Something went wrong. Please try again.'],
            ], true);
        }
    }

    public function sendOtp(): void
    {
        if (!$this->verifyCsrf()) {
            $this->backToForgot([
                'forgot' => ['Invalid request. Please refresh the page and try again.'],
            ], true);
        }

        $lookup = Session::get('password_reset_lookup');

        if (
            !is_array($lookup)
            || empty($lookup['user_id'])
            || (int) ($lookup['expires_at'] ?? 0) < time()
        ) {
            Session::remove('password_reset_lookup');
            Session::remove('password_reset_options');
            Session::remove('forgot_password_user_id');

            $this->backToForgot([
                'forgot' => ['Your recovery session expired. Please try again.'],
            ]);
        }

        $method = strtolower(trim((string) ($_POST['delivery_method'] ?? '')));
        $methods = is_array($lookup['methods'] ?? null) ? $lookup['methods'] : [];

        if (!in_array($method, ['email', 'phone'], true) || !isset($methods[$method])) {
            $this->backToForgot([
                'forgot' => ['Please choose a valid recovery option.'],
            ], true);
        }

        try {
            $otpId = $this->passwordReset->sendOtp(
                (int) $lookup['user_id'],
                $method,
                $this->ipAddress(),
                $this->userAgent()
            );

            Session::set('password_reset_otp', [
                'otp_id' => $otpId,
                'user_id' => (int) $lookup['user_id'],
                'expires_at' => time() + 600,
            ]);

            Session::remove('password_reset_options');
            Session::remove('forgot_password_user_id');

            Session::set('success', 'OTP request processed. Please check your selected recovery option.');

            header('Location: ' . $this->url('/forgot-password/verify-otp'));
            exit;
        } catch (Throwable $e) {
            Session::set('errors', [
                'forgot' => [$e->getMessage()],
            ]);

            header('Location: ' . $this->url('/forgot-password?options=1'));
            exit;
        }
    }

    public function showVerifyOtp(): void
    {
        if (!$this->validOtpSession()) {
            Session::set('errors', [
                'forgot' => ['Your OTP session expired. Please request a new OTP.'],
            ]);

            header('Location: ' . $this->url('/forgot-password'));
            exit;
        }

        View::render('auth.forgot-password.verify-otp', [
            'errors' => Session::get('errors', []),
            'success' => Session::get('success'),
        ]);

        Session::remove('errors');
        Session::remove('success');
    }

    public function verifyOtp(): void
    {
        if (!$this->verifyCsrf()) {
            $this->backToVerify([
                'otp' => ['Invalid request. Please refresh the page and try again.'],
            ]);
        }

        if (!$this->validOtpSession()) {
            Session::set('errors', [
                'forgot' => ['Your OTP session expired. Please request a new OTP.'],
            ]);

            header('Location: ' . $this->url('/forgot-password'));
            exit;
        }

        $otpSession = Session::get('password_reset_otp');
        $otp = trim((string) ($_POST['otp'] ?? ''));

        try {
            $this->passwordReset->verifyOtp(
                (int) $otpSession['otp_id'],
                (int) $otpSession['user_id'],
                $otp,
                $this->ipAddress(),
                $this->userAgent()
            );

            Session::set('password_reset_verified', [
                'otp_id' => (int) $otpSession['otp_id'],
                'user_id' => (int) $otpSession['user_id'],
                'expires_at' => time() + 900,
            ]);

            Session::remove('password_reset_otp');

            Session::set('success', 'OTP verified. You may now reset your password.');

            header('Location: ' . $this->url('/forgot-password/reset'));
            exit;
        } catch (Throwable $e) {
            $this->backToVerify([
                'otp' => [$e->getMessage()],
            ]);
        }
    }

    public function showReset(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));

        if ($token !== '') {
            if (!$this->validResetToken($token)) {
                Session::set('errors', [
                    'forgot' => ['Reset link is invalid or expired. Please request a new one.'],
                ]);

                header('Location: ' . $this->url('/forgot-password'));
                exit;
            }

            View::render('auth.forgot-password.reset', [
                'token' => $token,
                'errors' => Session::get('errors', []),
                'success' => Session::get('success'),
            ]);

            Session::remove('errors');
            Session::remove('success');
            return;
        }

        if (!$this->validResetSession()) {
            Session::set('errors', [
                'forgot' => ['Your reset session expired. Please request a new OTP or reset link.'],
            ]);

            header('Location: ' . $this->url('/forgot-password'));
            exit;
        }

        View::render('auth.forgot-password.reset', [
            'token' => '',
            'errors' => Session::get('errors', []),
            'success' => Session::get('success'),
        ]);

        Session::remove('errors');
        Session::remove('success');
    }

    public function reset(): void
    {
        if (!$this->verifyCsrf()) {
            Session::set('errors', [
                'password' => ['Invalid request. Please refresh the page and try again.'],
            ]);

            header('Location: ' . $this->url('/forgot-password'));
            exit;
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        if ($token !== '') {
            $this->resetUsingToken($token, $password, $passwordConfirmation);
            return;
        }

        $this->resetUsingVerifiedOtp($password, $passwordConfirmation);
    }

    private function resetUsingToken(string $token, string $password, string $passwordConfirmation): void
    {
        try {
            $tokenRow = $this->getValidResetTokenRow($token);

            if (!$tokenRow) {
                Session::set('errors', [
                    'forgot' => ['Reset link is invalid or expired. Please request a new one.'],
                ]);

                header('Location: ' . $this->url('/forgot-password'));
                exit;
            }

            $error = $this->passwordValidationError(
                $password,
                $passwordConfirmation,
                (string) ($tokenRow['password'] ?? '')
            );

            if ($error !== null) {
                $this->backToResetWithToken($token, $error);
            }

            $db = Database::getConnection();
            $db->beginTransaction();

            $updateUser = $db->prepare("
                UPDATE users
                SET password = :password,
                    updated_at = NOW()
                WHERE user_id = :user_id
                LIMIT 1
            ");

            $updateUser->execute([
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':user_id' => (int) $tokenRow['user_id'],
            ]);

            $markUsed = $db->prepare("
                UPDATE password_reset_tokens
                SET used_at = NOW()
                WHERE reset_id = :reset_id
                LIMIT 1
            ");

            $markUsed->execute([
                ':reset_id' => (int) $tokenRow['reset_id'],
            ]);

            $db->commit();

            Session::remove('password_reset_lookup');
            Session::remove('password_reset_options');
            Session::remove('forgot_password_user_id');
            Session::remove('password_reset_otp');
            Session::remove('password_reset_verified');

            Session::set('success', 'Password reset successful. You can now log in.');

            header('Location: ' . $this->url('/login'));
            exit;
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }

            error_log('[ForgotPasswordController::resetUsingToken] ' . $e->getMessage());

            Session::set('errors', [
                'password' => ['Something went wrong. Please try again.'],
            ]);

            header('Location: ' . $this->url('/forgot-password/reset?token=' . urlencode($token)));
            exit;
        }
    }

    private function resetUsingVerifiedOtp(string $password, string $passwordConfirmation): void
    {
        if (!$this->validResetSession()) {
            Session::set('errors', [
                'forgot' => ['Your reset session expired. Please request a new OTP or reset link.'],
            ]);

            header('Location: ' . $this->url('/forgot-password'));
            exit;
        }

        $session = Session::get('password_reset_verified');
        $userId = (int) ($session['user_id'] ?? 0);
        $otpId = (int) ($session['otp_id'] ?? 0);

        try {
            $db = Database::getConnection();

            $stmt = $db->prepare("
                SELECT user_id, password
                FROM users
                WHERE user_id = :user_id
                LIMIT 1
            ");

            $stmt->execute([
                ':user_id' => $userId,
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                Session::set('errors', [
                    'forgot' => ['Your reset session expired. Please request a new OTP or reset link.'],
                ]);

                header('Location: ' . $this->url('/forgot-password'));
                exit;
            }

            $error = $this->passwordValidationError(
                $password,
                $passwordConfirmation,
                (string) ($user['password'] ?? '')
            );

            if ($error !== null) {
                $this->backToReset($error);
            }

            $db->beginTransaction();

            $updateUser = $db->prepare("
                UPDATE users
                SET password = :password,
                    updated_at = NOW()
                WHERE user_id = :user_id
                LIMIT 1
            ");

            $updateUser->execute([
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':user_id' => $userId,
            ]);

            $this->markOtpAsUsed($db, $otpId, $userId);

            $db->commit();

            Session::remove('password_reset_verified');
            Session::remove('password_reset_otp');
            Session::remove('password_reset_lookup');
            Session::remove('password_reset_options');
            Session::remove('forgot_password_user_id');

            Session::set('success', 'Password reset successful. You can now log in.');

            header('Location: ' . $this->url('/login'));
            exit;
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }

            error_log('[ForgotPasswordController::resetUsingVerifiedOtp] ' . $e->getMessage());

            Session::set('errors', [
                'password' => ['Something went wrong. Please try again.'],
            ]);

            header('Location: ' . $this->url('/forgot-password/reset'));
            exit;
        }
    }

    private function validOtpSession(): bool
    {
        $session = Session::get('password_reset_otp');

        return is_array($session)
            && !empty($session['otp_id'])
            && !empty($session['user_id'])
            && (int) ($session['expires_at'] ?? 0) >= time();
    }

    private function validResetSession(): bool
    {
        $session = Session::get('password_reset_verified');

        if (
            !is_array($session)
            || empty($session['otp_id'])
            || empty($session['user_id'])
            || (int) ($session['expires_at'] ?? 0) < time()
        ) {
            return false;
        }

        return $this->passwordReset->canResetPassword(
            (int) $session['otp_id'],
            (int) $session['user_id']
        );
    }

    private function validResetToken(string $token): bool
    {
        return (bool) $this->getValidResetTokenRow($token);
    }

    private function getValidResetTokenRow(string $token): ?array
    {
        $token = trim($token);

        if ($token === '' || strlen($token) < 40) {
            return null;
        }

        try {
            $db = Database::getConnection();

            $stmt = $db->prepare("
                SELECT prt.*, u.password
                FROM password_reset_tokens prt
                INNER JOIN users u ON u.user_id = prt.user_id
                WHERE prt.token_hash = :token_hash
                  AND prt.used_at IS NULL
                  AND prt.expires_at > NOW()
                LIMIT 1
            ");

            $stmt->execute([
                ':token_hash' => hash('sha256', $token),
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (Throwable $e) {
            error_log('[ForgotPasswordController::getValidResetTokenRow] ' . $e->getMessage());
            return null;
        }
    }

    private function countRecentResetLinks(int $userId, string $ipAddress): int
    {
        try {
            $db = Database::getConnection();

            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM password_reset_tokens
                WHERE user_id = :user_id
                  AND ip_address = :ip_address
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':ip_address' => $ipAddress,
            ]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[ForgotPasswordController::countRecentResetLinks] ' . $e->getMessage());
            return 0;
        }
    }

    private function passwordValidationError(string $password, string $passwordConfirmation, string $currentHash): ?string
    {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters.';
        }

        if ($password !== $passwordConfirmation) {
            return 'Password confirmation does not match.';
        }

        if ($currentHash !== '' && password_verify($password, $currentHash)) {
            return 'New password must be different from the old password.';
        }

        return null;
    }

    private function sendPasswordResetEmail(string $email, string $resetLink): bool
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = 'Dental Clinic Password Reset Link';

        $body = "You requested a password reset for your Dental Clinic account.\n\n"
            . "Reset your password using this link:\n"
            . $resetLink . "\n\n"
            . "This link expires in 15 minutes.\n"
            . "If you did not request this, please ignore this message.";

        return $this->sendSmtpEmail($email, $subject, $body);
    }

    private function sendPasswordResetSms(string $phone, string $resetLink): bool
    {
        error_log('[Forgot Password SMS] SMS reset link requested for ' . $this->maskPhone($phone));

        return false;
    }

    private function sendSmtpEmail(string $toEmail, string $subject, string $body): bool
    {
        $config = $this->otpConfig();
        $emailConfig = $config['email'] ?? [];

        if (empty($emailConfig['enabled'])) {
            error_log('[Forgot Password Email] Email sending is disabled.');
            return false;
        }

        $host = trim((string) ($emailConfig['host'] ?? 'smtp.gmail.com'));
        $port = (int) ($emailConfig['port'] ?? 587);
        $username = trim((string) ($emailConfig['username'] ?? ''));
        $password = trim((string) ($emailConfig['password'] ?? ''));
        $fromEmail = trim((string) ($emailConfig['from_email'] ?? $username));
        $fromName = trim((string) ($emailConfig['from_name'] ?? 'Dental Clinic'));

        if (
            $host === ''
            || $port <= 0
            || !filter_var($username, FILTER_VALIDATE_EMAIL)
            || $password === ''
            || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
            || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)
        ) {
            error_log('[Forgot Password Email] Invalid SMTP config.');
            return false;
        }

        return $this->sendRawSmtpEmail(
            $host,
            $port,
            $username,
            $password,
            $fromEmail,
            $fromName,
            $toEmail,
            $subject,
            $body
        );
    }

    private function sendRawSmtpEmail(
        string $host,
        int $port,
        string $username,
        string $password,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $body
    ): bool {
        $socket = null;

        try {
            $socket = stream_socket_client(
                "tcp://{$host}:{$port}",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT
            );

            if (!$socket) {
                error_log("[SMTP] Connection failed: {$errno} {$errstr}");
                return false;
            }

            stream_set_timeout($socket, 30);

            if (!$this->smtpExpect($socket, [220])) {
                fclose($socket);
                return false;
            }

            $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';

            $this->smtpCommand($socket, "EHLO {$serverName}");

            if (!$this->smtpExpect($socket, [250])) {
                fclose($socket);
                return false;
            }

            $this->smtpCommand($socket, 'STARTTLS');

            if (!$this->smtpExpect($socket, [220])) {
                fclose($socket);
                return false;
            }

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log('[SMTP] Failed to enable TLS encryption.');
                fclose($socket);
                return false;
            }

            $this->smtpCommand($socket, "EHLO {$serverName}");

            if (!$this->smtpExpect($socket, [250])) {
                fclose($socket);
                return false;
            }

            $this->smtpCommand($socket, 'AUTH LOGIN');

            if (!$this->smtpExpect($socket, [334])) {
                fclose($socket);
                return false;
            }

            $this->smtpCommand($socket, base64_encode($username));

            if (!$this->smtpExpect($socket, [334])) {
                fclose($socket);
                return false;
            }

            $this->smtpCommand($socket, base64_encode($password));

            if (!$this->smtpExpect($socket, [235])) {
                fclose($socket);
                return false;
            }

            $this->smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>');

            if (!$this->smtpExpect($socket, [250])) {
                fclose($socket);
                return false;
            }

            $this->smtpCommand($socket, 'RCPT TO:<' . $toEmail . '>');

            if (!$this->smtpExpect($socket, [250, 251])) {
                fclose($socket);
                return false;
            }

            $this->smtpCommand($socket, 'DATA');

            if (!$this->smtpExpect($socket, [354])) {
                fclose($socket);
                return false;
            }

            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'From: ' . $this->encodeMailName($fromName) . ' <' . $fromEmail . '>',
                'To: <' . $toEmail . '>',
                'Subject: ' . $this->encodeMailSubject($subject),
                'Date: ' . date('r'),
            ];

            $message = implode("\r\n", $headers)
                . "\r\n\r\n"
                . $this->smtpDotStuff($body)
                . "\r\n.";

            $this->smtpCommand($socket, $message);

            if (!$this->smtpExpect($socket, [250])) {
                fclose($socket);
                return false;
            }

            $this->smtpCommand($socket, 'QUIT');
            $this->smtpExpect($socket, [221]);

            fclose($socket);
            return true;
        } catch (Throwable $e) {
            error_log('[SMTP] ' . $e->getMessage());

            if (is_resource($socket)) {
                fclose($socket);
            }

            return false;
        }
    }

    private function smtpCommand($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    private function smtpExpect($socket, array $expectedCodes): bool
    {
        $response = $this->smtpReadResponse($socket);

        if ($response === '') {
            error_log('[SMTP] Empty response from server.');
            return false;
        }

        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            error_log('[SMTP] Unexpected response: ' . trim($response));
            return false;
        }

        return true;
    }

    private function smtpReadResponse($socket): string
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;

            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        return $response;
    }

    private function smtpDotStuff(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $body);

        foreach ($lines as &$line) {
            if (isset($line[0]) && $line[0] === '.') {
                $line = '.' . $line;
            }
        }

        return implode("\r\n", $lines);
    }

    private function encodeMailSubject(string $subject): string
    {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    private function encodeMailName(string $name): string
    {
        return '=?UTF-8?B?' . base64_encode($name) . '?=';
    }

    private function otpConfig(): array
{
    $paths = [
        dirname(__DIR__, 3) . '/config/otp.php',
        dirname(__DIR__, 2) . '/Config/otp.php',
        dirname(__DIR__, 3) . '/app/Config/otp.php',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            error_log('[Forgot Password Email] Loaded config file at: ' . $path);

            $config = require $path;

            return is_array($config) ? $config : [];
        }

        error_log('[Forgot Password Email] Missing config file at: ' . $path);
    }

    return [];
}

    private function markOtpAsUsed(PDO $db, int $otpId, int $userId): void
    {
        try {
            $stmt = $db->prepare("
                UPDATE password_reset_otps
                SET used_at = NOW()
                WHERE otp_id = :otp_id
                  AND user_id = :user_id
                LIMIT 1
            ");

            $stmt->execute([
                ':otp_id' => $otpId,
                ':user_id' => $userId,
            ]);
        } catch (Throwable $e) {
            error_log('[ForgotPasswordController::markOtpAsUsed] ' . $e->getMessage());
        }
    }

    private function verifyCsrf(): bool
    {
        return Csrf::verify($_POST['_csrf_token'] ?? null);
    }

    private function backToForgot(array $errors, bool $keepOptions = false): void
    {
        Session::set('errors', $errors);

        header('Location: ' . $this->url($keepOptions ? '/forgot-password?options=1' : '/forgot-password'));
        exit;
    }

    private function backToVerify(array $errors): void
    {
        Session::set('errors', $errors);

        header('Location: ' . $this->url('/forgot-password/verify-otp'));
        exit;
    }

    private function backToReset(string $message): void
    {
        Session::set('errors', [
            'password' => [$message],
        ]);

        header('Location: ' . $this->url('/forgot-password/reset'));
        exit;
    }

    private function backToResetWithToken(string $token, string $message): void
    {
        Session::set('errors', [
            'password' => [$message],
        ]);

        header('Location: ' . $this->url('/forgot-password/reset?token=' . urlencode($token)));
        exit;
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

    public function chooseMethod(): void
{
    $lookup = Session::get('password_reset_lookup');
    $options = Session::get('password_reset_options');

    if (
        !is_array($lookup)
        || empty($lookup['user_id'])
        || (int) ($lookup['expires_at'] ?? 0) < time()
        || !is_array($options)
        || empty($options)
    ) {
        Session::remove('password_reset_lookup');
        Session::remove('password_reset_options');
        Session::remove('forgot_password_user_id');

        Session::set('errors', [
            'forgot' => ['Your recovery session expired. Please try again.'],
        ]);

        header('Location: ' . $this->url('/forgot-password'));
        exit;
    }

    View::render('auth.forgot-password.choose-method', [
        'errors' => Session::get('errors', []),
        'success' => Session::get('success'),
        'options' => $options,
    ]);

    Session::remove('errors');
    Session::remove('success');
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

    private function ipAddress(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    private function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    private function url(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }

    private function absoluteUrl(string $path): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . $this->url($path);
    }
}