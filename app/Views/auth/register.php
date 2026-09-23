<?php

use App\Core\Csrf;

$errors = $errors ?? [];
$old = $old ?? [];

if (!function_exists('register_e')) {
    function register_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('register_error')) {
    function register_error(array $errors, string $field): string
    {
        if (empty($errors[$field][0])) {
            return '';
        }

        return '<div class="field-error">' . register_e($errors[$field][0]) . '</div>';
    }
}

ob_start();
?>

<style>
    :root {
        --auth-primary: #0d9e8c;
        --auth-primary-dark: #08796c;
        --auth-navy: #10233f;
        --auth-text: #1f2937;
        --auth-muted: #64748b;
        --auth-line: rgba(148, 163, 184, 0.35);
        --auth-danger-bg: #fef2f2;
        --auth-danger-text: #991b1b;
        --auth-white: #ffffff;
    }

    html,
    body {
        min-height: 100%;
        overflow-x: hidden;
    }

    body {
        overflow-y: auto !important;
    }

    .auth-page {
        position: relative;
        min-height: 100vh;
        width: 100%;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 38px 18px 60px;
        overflow: visible;
    }

    .auth-hero {
        position: fixed;
        inset: 0;
        z-index: 0;
        width: 100%;
        height: 100vh;
    }

    .auth-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            linear-gradient(
                135deg,
                rgba(8, 20, 36, 0.84),
                rgba(13, 158, 140, 0.44)
            );
        z-index: 1;
    }

    .auth-hero-inner {
        position: fixed;
        left: 6%;
        bottom: 7%;
        z-index: 2;
        max-width: 440px;
        color: #ffffff;
        display: none;
    }

    .auth-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.25);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 14px;
        backdrop-filter: blur(12px);
    }

    .auth-title {
        font-size: clamp(28px, 4vw, 44px);
        line-height: 1.08;
        margin: 0 0 12px;
        font-weight: 800;
    }

    .auth-copy {
        font-size: 15px;
        line-height: 1.7;
        color: rgba(255, 255, 255, 0.82);
        margin: 0;
    }

    .auth-panel {
        position: relative;
        z-index: 3;
        width: 100%;
        max-width: 820px;
        max-height: none;
        overflow: visible;
        margin: 0 auto;
        padding: 0;
    }

    .auth-card {
        width: 100%;
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid rgba(255, 255, 255, 0.65);
        border-radius: 2px;
        padding: 32px;
        box-shadow:
            0 24px 70px rgba(15, 23, 42, 0.28),
            0 0 0 1px rgba(255, 255, 255, 0.25);
        backdrop-filter: blur(18px);
    }

    .register-logo {
        width: 70px;
        height: 70px;
        border-radius: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        background:
            linear-gradient(135deg, rgba(13, 158, 140, 0.14), rgba(16, 35, 63, 0.08));
        border: 1px solid rgba(13, 158, 140, 0.22);
        color: var(--auth-primary);
    }

    .register-logo svg {
        width: 38px;
        height: 38px;
    }

    .auth-card h1 {
        text-align: center;
        margin: 0;
        color: var(--auth-navy);
        font-size: 30px;
        font-weight: 800;
        letter-spacing: -0.03em;
    }

    .auth-subtitle {
        text-align: center;
        color: var(--auth-muted);
        font-size: 14px;
        line-height: 1.6;
        margin: 10px auto 24px;
        max-width: 520px;
    }

    .error-box {
        background: var(--auth-danger-bg);
        color: var(--auth-danger-text);
        border: 1px solid rgba(185, 28, 28, 0.18);
        border-radius: 14px;
        padding: 12px 14px;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 18px;
    }

    .form-grid {
        display: grid;
        gap: 16px;
    }

    .form-grid.two {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-group label {
        display: block;
        color: var(--auth-text);
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 7px;
    }

    .input-wrap,
    .password-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-icon {
        position: absolute;
        left: 14px;
        width: 18px;
        height: 18px;
        color: #94a3b8;
        pointer-events: none;
    }

    .input-wrap input,
    .input-wrap select,
    .password-wrap input,
    .form-group textarea {
        width: 100%;
        border: 1px solid var(--auth-line);
        border-radius: 14px;
        background: #ffffff;
        color: var(--auth-text);
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .input-wrap input,
    .input-wrap select,
    .password-wrap input {
        height: 48px;
    }

    .input-wrap input,
    .input-wrap select {
        padding: 0 14px 0 44px;
    }

    .password-wrap input {
        padding: 0 54px 0 44px;
    }

    .form-group textarea {
        min-height: 88px;
        resize: vertical;
        padding: 13px 14px;
    }

    .input-wrap input:focus,
    .input-wrap select:focus,
    .password-wrap input:focus,
    .form-group textarea:focus {
        border-color: rgba(13, 158, 140, 0.75);
        box-shadow: 0 0 0 4px rgba(13, 158, 140, 0.12);
    }

    .toggle-password {
        position: absolute;
        right: 9px;
        width: 38px;
        height: 38px;
        border: 0;
        border-radius: 11px;
        background: transparent;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s ease, color 0.2s ease;
    }

    .toggle-password:hover {
        background: rgba(13, 158, 140, 0.1);
        color: var(--auth-primary);
    }

    .toggle-password svg {
        width: 20px;
        height: 20px;
        pointer-events: none;
    }

    .password-strength {
        margin-top: 8px;
        font-size: 12px;
        color: var(--auth-muted);
        font-weight: 700;
    }

    .strength-meter {
        height: 6px;
        width: 100%;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
        margin-top: 7px;
    }

    .strength-fill {
        height: 100%;
        width: 20%;
        border-radius: 999px;
        background: #ef4444;
        transition: width 0.2s ease, background 0.2s ease;
    }

    .password-strength.is-medium .strength-fill {
        width: 65%;
        background: #f59e0b;
    }

    .password-strength.is-strong .strength-fill {
        width: 100%;
        background: var(--auth-primary);
    }

    .password-strength.is-medium #password_strength_text {
        color: #b45309;
    }

    .password-strength.is-strong #password_strength_text {
        color: var(--auth-primary-dark);
    }

    .field-error {
        margin-top: 6px;
        color: var(--auth-danger-text);
        font-size: 12px;
        font-weight: 600;
    }

    .auth-card button[type="submit"] {
        width: 100%;
        height: 50px;
        border: 0;
        border-radius: 15px;
        background: linear-gradient(135deg, var(--auth-primary), var(--auth-primary-dark));
        color: #ffffff;
        font-size: 15px;
        font-weight: 800;
        cursor: pointer;
        box-shadow: 0 12px 26px rgba(13, 158, 140, 0.28);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .auth-card button[type="submit"]:hover {
        transform: translateY(-1px);
        box-shadow: 0 16px 32px rgba(13, 158, 140, 0.34);
    }

    .auth-card button[type="submit"]:active {
        transform: translateY(0);
    }

    .form-footer {
        margin-top: 22px;
        text-align: center;
        color: var(--auth-muted);
        font-size: 14px;
    }

    .muted-link {
        color: var(--auth-primary-dark);
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
    }

    .muted-link:hover {
        text-decoration: underline;
    }

    .security-note {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        margin-top: 18px;
        color: #64748b;
        font-size: 12px;
    }

    .security-note svg {
        width: 15px;
        height: 15px;
        color: var(--auth-primary);
    }

    @media (min-width: 1100px) {
        .auth-hero-inner {
            display: block;
        }
    }

    @media (max-width: 760px) {
        .auth-page {
            padding: 24px 14px 50px;
        }

        .auth-card {
            padding: 28px 22px;
            border-radius: 20px;
        }

        .form-grid.two {
            grid-template-columns: 1fr;
            gap: 0;
        }
    }

    @media (max-width: 420px) {
        .auth-page {
            padding: 20px 12px 45px;
        }

        .auth-card h1 {
            font-size: 26px;
        }
    }
</style>

<div class="auth-page">
    <div class="auth-hero">
        <div class="auth-hero-inner">
            <div class="auth-badge">Patient Registration</div>
            <h2 class="auth-title">Create your clinic account</h2>
            <p class="auth-copy">
                Register once to book appointments faster, manage your dental records, and stay connected with the clinic.
            </p>
        </div>
    </div>

    <div class="auth-panel">
        <div class="auth-card">
            <div class="register-logo" aria-hidden="true">
                <svg viewBox="0 0 64 64" fill="none">
                    <path d="M21.5 8.5C16.2 8.5 12 13 12 19.1c0 4.7 1.8 8.3 3.6 12.2 1.7 3.7 3.4 7.7 4 13.2.6 5.4 2.5 11 6.1 11 2.9 0 3.5-5.2 4.4-10.2.5-2.9 1.1-5.6 2-5.6s1.5 2.7 2 5.6c.9 5 1.5 10.2 4.4 10.2 3.6 0 5.5-5.6 6.1-11 .6-5.5 2.3-9.5 4-13.2C50.2 27.4 52 23.8 52 19.1 52 13 47.8 8.5 42.5 8.5c-3.5 0-5.7 1.3-7.5 2.3-1.2.7-2.1 1.2-3 1.2s-1.8-.5-3-1.2c-1.8-1-4-2.3-7.5-2.3Z"
                          stroke="currentColor"
                          stroke-width="4"
                          stroke-linecap="round"
                          stroke-linejoin="round"/>
                    <path d="M24 21c2.5 1.8 5.1 2.7 8 2.7s5.5-.9 8-2.7"
                          stroke="currentColor"
                          stroke-width="4"
                          stroke-linecap="round"/>
                </svg>
            </div>

            <h1>Register</h1>
            <p class="auth-subtitle">
                Create your patient portal account to book appointments, manage your dental records, and stay connected with the clinic.
            </p>

            <?php if (!empty($errors['register'])): ?>
                <div class="error-box">
                    <?= register_e($errors['register'][0]) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/DentalClinic/public/register" autocomplete="on">
                <?= Csrf::inputField(); ?>

                <div class="form-grid two">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M4 20a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                value="<?= register_e($old['first_name'] ?? '') ?>"
                                required
                                autocomplete="given-name"
                            >
                        </div>
                        <?= register_error($errors, 'first_name') ?>
                    </div>

                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M4 20a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <input
                                type="text"
                                id="middle_name"
                                name="middle_name"
                                value="<?= register_e($old['middle_name'] ?? '') ?>"
                                autocomplete="additional-name"
                            >
                        </div>
                        <?= register_error($errors, 'middle_name') ?>
                    </div>
                </div>

                <div class="form-grid two">
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M4 20a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                value="<?= register_e($old['last_name'] ?? '') ?>"
                                required
                                autocomplete="family-name"
                            >
                        </div>
                        <?= register_error($errors, 'last_name') ?>
                    </div>

                    <div class="form-group">
                        <label for="sex">Sex</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 21s7-4.4 7-11a7 7 0 0 0-14 0c0 6.6 7 11 7 11Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M12 10.5h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                            </svg>
                            <select id="sex" name="sex" required>
                                <option value="">Select</option>
                                <option value="female" <?= (($old['sex'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
                                <option value="male" <?= (($old['sex'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
                            </select>
                        </div>
                        <?= register_error($errors, 'sex') ?>
                    </div>
                </div>

                <div class="form-grid two">
                    <div class="form-group">
                        <label for="birth_date">Birth Date</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M7 3v3M17 3v3M4 9h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M5.5 5h13A1.5 1.5 0 0 1 20 6.5v12A1.5 1.5 0 0 1 18.5 20h-13A1.5 1.5 0 0 1 4 18.5v-12A1.5 1.5 0 0 1 5.5 5Z" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <input
                                type="date"
                                id="birth_date"
                                name="birth_date"
                                value="<?= register_e($old['birth_date'] ?? '') ?>"
                                max="<?= date('Y-m-d') ?>"
                                required
                            >
                        </div>
                        <?= register_error($errors, 'birth_date') ?>
                    </div>

                    <div class="form-group">
                        <label for="civil_status">Civil Status</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM16 13a4 4 0 1 0 0-8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M2.5 21a6 6 0 0 1 11 0M14 21a5.5 5.5 0 0 1 7.5-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <select id="civil_status" name="civil_status">
    <option value="">Select Civil Status</option>
    <option value="Single" <?= (($old['civil_status'] ?? '') === 'Single') ? 'selected' : '' ?>>Single</option>
    <option value="Married" <?= (($old['civil_status'] ?? '') === 'Married') ? 'selected' : '' ?>>Married</option>
    <option value="Widowed" <?= (($old['civil_status'] ?? '') === 'Widowed') ? 'selected' : '' ?>>Widowed</option>
    <option value="Separated" <?= (($old['civil_status'] ?? '') === 'Separated') ? 'selected' : '' ?>>Separated</option>
</select>
                        </div>
                        <?= register_error($errors, 'civil_status') ?>
                    </div>
                </div>

                <div class="form-grid two">
                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M8 2h8a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M11 18h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <input
                                type="text"
                                id="contact_number"
                                name="contact_number"
                                value="<?= register_e($old['contact_number'] ?? '') ?>"
                                required
                                inputmode="numeric"
                                autocomplete="tel"
                                placeholder="09XXXXXXXXX"
                            >
                        </div>
                        <?= register_error($errors, 'contact_number') ?>
                    </div>

                    <div class="form-group">
                        <label for="occupation">Occupation</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M9 6V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1" stroke="currentColor" stroke-width="2"/>
                                <path d="M4 7h16v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M4 12h16" stroke="currentColor" stroke-width="2"/>
                            </svg>
                            <input
                                type="text"
                                id="occupation"
                                name="occupation"
                                value="<?= register_e($old['occupation'] ?? '') ?>"
                                autocomplete="organization-title"
                            >
                        </div>
                        <?= register_error($errors, 'occupation') ?>
                    </div>
                </div>

                <div class="form-group">
    <label for="address">Address</label>
    <div class="input-wrap">
        <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 21s7-4.4 7-11a7 7 0 0 0-14 0c0 6.6 7 11 7 11Z" stroke="currentColor" stroke-width="2"/>
            <path d="M12 10.5h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>

        <select id="address" name="address">
            <option value="">Select Address</option>
            <option value="Sinasajan, Peñaranda, Nueva Ecija" <?= (($old['address'] ?? '') === 'Sinasajan, Peñaranda, Nueva Ecija') ? 'selected' : '' ?>>
                Sinasajan, Peñaranda, Nueva Ecija
            </option>
            <option value="Poblacion I, Peñaranda, Nueva Ecija" <?= (($old['address'] ?? '') === 'Poblacion I, Peñaranda, Nueva Ecija') ? 'selected' : '' ?>>
                Poblacion I, Peñaranda, Nueva Ecija
            </option>
            <option value="Poblacion II, Peñaranda, Nueva Ecija" <?= (($old['address'] ?? '') === 'Poblacion II, Peñaranda, Nueva Ecija') ? 'selected' : '' ?>>
                Poblacion II, Peñaranda, Nueva Ecija
            </option>
            <option value="Poblacion III, Peñaranda, Nueva Ecija" <?= (($old['address'] ?? '') === 'Poblacion III, Peñaranda, Nueva Ecija') ? 'selected' : '' ?>>
                Poblacion III, Peñaranda, Nueva Ecija
            </option>
            <option value="Poblacion IV, Peñaranda, Nueva Ecija" <?= (($old['address'] ?? '') === 'Poblacion IV, Peñaranda, Nueva Ecija') ? 'selected' : '' ?>>
                Poblacion IV, Peñaranda, Nueva Ecija
            </option>
        </select>
    </div>
    <?= register_error($errors, 'address') ?>
</div>

                <div class="form-grid two">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5v-11Z" stroke="currentColor" stroke-width="2"/>
                                <path d="m5 7 7 6 7-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?= register_e($old['email'] ?? '') ?>"
                                required
                                autocomplete="email"
                                placeholder="example@email.com"
                            >
                        </div>
                        <?= register_error($errors, 'email') ?>
                    </div>

                    
                </div>

                <div class="form-grid two">
                    <div class="form-group">
                        <label for="register_password">Password</label>

                        <div class="password-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M6.5 10h11A1.5 1.5 0 0 1 19 11.5v7A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-7A1.5 1.5 0 0 1 6.5 10Z" stroke="currentColor" stroke-width="2"/>
                                <path d="M12 14v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>

                            <input
                                type="password"
                                id="register_password"
                                name="password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="At least 8 characters"
                            >

                            <button
                                type="button"
                                class="toggle-password"
                                data-target="register_password"
                                aria-label="Show password"
                                title="Show password"
                            >
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            </button>
                        </div>

                        <div class="password-strength" id="password_strength_box">
                            Strength: <span id="password_strength_text">Weak</span>
                            <div class="strength-meter">
                                <div class="strength-fill" id="password_strength_fill"></div>
                            </div>
                        </div>

                        <?= register_error($errors, 'password') ?>
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirm Password</label>

                        <div class="password-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M6.5 10h11A1.5 1.5 0 0 1 19 11.5v7A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-7A1.5 1.5 0 0 1 6.5 10Z" stroke="currentColor" stroke-width="2"/>
                                <path d="m9.5 15 1.7 1.7 3.7-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>

                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Re-enter password"
                            >

                            <button
                                type="button"
                                class="toggle-password"
                                data-target="password_confirmation"
                                aria-label="Show password"
                                title="Show password"
                            >
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            </button>
                        </div>

                        <?= register_error($errors, 'password_confirmation') ?>
                    </div>
                </div>


                <button type="submit">Create Account</button>
            </form>

            <div class="security-note">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 3 5 6v5c0 5 3 8.5 7 10 4-1.5 7-5 7-10V6l-7-3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="m9.5 12 1.7 1.7 3.5-3.7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Your account is protected with secure password hashing
            </div>

            <div class="form-footer">
                Already have an account?
                <a class="muted-link" href="/DentalClinic/public/login">Login here</a>
            </div>
        </div>
    </div>
</div>

<script>
function eyeIcon() {
    return `
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linejoin="round"/>
            <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                  stroke="currentColor"
                  stroke-width="2"/>
        </svg>
    `;
}

function eyeOffIcon() {
    return `
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M3 3l18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M10.6 10.6A2 2 0 0 0 13.4 13.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M9.9 5.3A10.3 10.3 0 0 1 12 5c6 0 9.5 7 9.5 7a17.8 17.8 0 0 1-3.1 4.1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M6.6 6.7C4 8.4 2.5 12 2.5 12s3.5 7 9.5 7c1.5 0 2.9-.4 4.1-1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    `;
}

document.addEventListener('click', function (event) {
    const button = event.target.closest('.toggle-password');

    if (!button) {
        return;
    }

    const input = document.getElementById(button.dataset.target);

    if (!input) {
        return;
    }

    const isHidden = input.type === 'password';

    input.type = isHidden ? 'text' : 'password';
    button.innerHTML = isHidden ? eyeOffIcon() : eyeIcon();
    button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
    button.setAttribute('title', isHidden ? 'Hide password' : 'Show password');
});

const passwordInput = document.getElementById('register_password');
const strengthBox = document.getElementById('password_strength_box');
const strengthText = document.getElementById('password_strength_text');

if (passwordInput && strengthBox && strengthText) {
    passwordInput.addEventListener('input', function () {
        const value = passwordInput.value;
        let score = 0;

        if (value.length >= 8) score++;
        if (/[A-Z]/.test(value)) score++;
        if (/[a-z]/.test(value)) score++;
        if (/[0-9]/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;

        strengthBox.classList.remove('is-medium', 'is-strong');

        if (score <= 2) {
            strengthText.textContent = 'Weak';
        } else if (score <= 4) {
            strengthText.textContent = 'Medium';
            strengthBox.classList.add('is-medium');
        } else {
            strengthText.textContent = 'Strong';
            strengthBox.classList.add('is-strong');
        }
    });
}
</script>

<?php
$content = ob_get_clean();
$title = 'Register';
require __DIR__ . '/../layouts/main.php';