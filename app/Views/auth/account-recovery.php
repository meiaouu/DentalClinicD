<?php

use App\Core\Csrf;

$errors = $errors ?? [];
$old = $old ?? [];
$success = $success ?? null;

function ar_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

ob_start();
?>

<style>
    body { overflow-y: auto !important; }
    .auth-simple-page {
        min-height: 100vh;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 48px 16px;
        background: linear-gradient(135deg, rgba(8,20,36,.92), rgba(13,158,140,.55));
    }
    .auth-simple-card {
        width: 100%;
        max-width: 680px;
        background: rgba(255,255,255,.97);
        border-radius: 24px;
        padding: 32px;
        box-shadow: 0 24px 70px rgba(15,23,42,.28);
    }
    h1 { text-align:center;color:#10233f;margin:0 0 10px; }
    p { text-align:center;color:#64748b;line-height:1.6; }
    .form-grid { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
    .form-group { margin-bottom:16px; }
    label { display:block;font-weight:700;margin-bottom:7px;color:#1f2937; }
    input, select, textarea {
        width: 100%;
        border: 1px solid rgba(148,163,184,.45);
        border-radius: 14px;
        padding: 0 14px;
        font-size: 14px;
    }
    input, select { height:48px; }
    textarea { min-height:110px;padding-top:12px;resize:vertical; }
    button {
        width: 100%;
        height: 48px;
        border: 0;
        border-radius: 14px;
        background: linear-gradient(135deg, #0d9e8c, #08796c);
        color: #fff;
        font-weight: 800;
        cursor: pointer;
    }
    .error-box, .success-box, .notice-box {
        border-radius: 14px;
        padding: 12px 14px;
        font-size: 14px;
        margin-bottom: 16px;
    }
    .error-box { background:#fef2f2;color:#991b1b; }
    .success-box { background:#ecfdf5;color:#065f46; }
    .notice-box { background:#eff6ff;color:#1e3a8a; }
    .links { text-align:center;margin-top:18px; }
    .links a { color:#08796c;font-weight:700;text-decoration:none; }
    @media(max-width:700px){ .form-grid{grid-template-columns:1fr;} }
</style>

<div class="auth-simple-page">
    <div class="auth-simple-card">
        <h1>Manual Account Recovery</h1>
        <p>
            Use this only if you cannot access your registered email or phone.
            This does not automatically reset your password.
        </p>

        <div class="notice-box">
            Staff, dentist, admin, and owner accounts must contact the Owner/Admin directly for identity verification.
        </div>

        <?php if (!empty($errors['recovery'])): ?>
            <div class="error-box"><?= ar_e($errors['recovery'][0]) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-box"><?= ar_e($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="/DentalClinic/public/account-recovery">
            <?= Csrf::inputField(); ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="requester_type">Account Type</label>
                    <select id="requester_type" name="requester_type" required>
                        <?php $type = $old['requester_type'] ?? 'patient'; ?>
                        <option value="patient" <?= $type === 'patient' ? 'selected' : '' ?>>Patient</option>
                        <option value="staff" <?= $type === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="dentist" <?= $type === 'dentist' ? 'selected' : '' ?>>Dentist</option>
                        <option value="admin" <?= $type === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="owner" <?= $type === 'owner' ? 'selected' : '' ?>>Owner</option>
                        <option value="unknown" <?= $type === 'unknown' ? 'selected' : '' ?>>Not sure</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= ar_e($old['full_name'] ?? '') ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="birth_date">Birthdate</label>
                    <input
                        type="date"
                        id="birth_date"
                        name="birth_date"
                        value="<?= ar_e($old['birth_date'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="appointment_or_request_code">Recent Appointment / Request Code</label>
                    <input
                        type="text"
                        id="appointment_or_request_code"
                        name="appointment_or_request_code"
                        value="<?= ar_e($old['appointment_or_request_code'] ?? '') ?>"
                    >
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="last_known_email">Last Known Email</label>
                    <input
                        type="email"
                        id="last_known_email"
                        name="last_known_email"
                        value="<?= ar_e($old['last_known_email'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="last_known_phone">Last Known Phone</label>
                    <input
                        type="text"
                        id="last_known_phone"
                        name="last_known_phone"
                        value="<?= ar_e($old['last_known_phone'] ?? '') ?>"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="message">Message</label>
                <textarea
                    id="message"
                    name="message"
                    placeholder="Explain your concern. Do not include passwords."
                ><?= ar_e($old['message'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="privacy_consent" value="1" required>
                    I consent to the clinic reviewing these details for account recovery.
                </label>
            </div>

            <button type="submit">Submit Recovery Request</button>
        </form>

        <div class="links">
            <a href="/DentalClinic/public/forgot-password">Back to forgot password</a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Account Recovery';
require __DIR__ . '/../layouts/main.php';