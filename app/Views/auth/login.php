<?php

use App\Core\Csrf;

$errors = $errors ?? [];
$old = $old ?? [];

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

    .auth-page {
        position: relative;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px 18px;
        overflow: hidden;
    }

    /*
        This keeps your existing background image unchanged.
        It only turns the hero section into the full-page background layer.
    */
    .auth-hero {
        position: absolute;
        inset: 0;
        z-index: 0;
        width: 100%;
        height: 100%;
    }

    .auth-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            linear-gradient(
                135deg,
                rgba(8, 20, 36, 0.82),
                rgba(13, 158, 140, 0.45)
            );
        z-index: 1;
    }

    .auth-hero-inner {
        position: absolute;
        left: 7%;
        bottom: 8%;
        z-index: 2;
        max-width: 460px;
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
        font-size: clamp(28px, 4vw, 46px);
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
        max-width: 440px;
    }

    .auth-card {
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid rgba(255, 255, 255, 0.65);
        border-radius: 2px;
        padding: 34px 32px;
        box-shadow:
            0 24px 70px rgba(15, 23, 42, 0.28),
            0 0 0 1px rgba(255, 255, 255, 0.25);
        backdrop-filter: blur(18px);
    }

    .login-logo {
        width: 74px;
        height: 74px;
        border-radius: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 18px;
        background:
            linear-gradient(135deg, rgba(13, 158, 140, 0.14), rgba(16, 35, 63, 0.08));
        border: 1px solid rgba(13, 158, 140, 0.22);
        color: var(--auth-primary);
    }

    .login-logo svg {
        width: 40px;
        height: 40px;
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
        margin: 10px 0 24px;
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

    .form-group {
        margin-bottom: 17px;
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
    .password-wrap input {
        width: 100%;
        height: 48px;
        border: 1px solid var(--auth-line);
        border-radius: 14px;
        background: #ffffff;
        color: var(--auth-text);
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .input-wrap input {
        padding: 0 14px 0 44px;
    }

    .password-wrap input {
        padding: 0 54px 0 44px;
    }

    .input-wrap input:focus,
    .password-wrap input:focus {
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

    .remember-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        margin-top: -2px;
        margin-bottom: 22px;
    }

    .remember-row label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        font-size: 13px;
        color: var(--auth-muted);
        font-weight: 600;
        cursor: pointer;
    }

    .remember-row input[type="checkbox"] {
        width: 15px;
        height: 15px;
        accent-color: var(--auth-primary);
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

    @media (min-width: 980px) {
        .auth-hero-inner {
            display: block;
        }

        .auth-panel {
            max-width: 450px;
        }
    }

    @media (max-width: 520px) {
        .auth-page {
            padding: 22px 14px;
        }

        .auth-card {
            padding: 28px 22px;
            border-radius: 20px;
        }

        .remember-row {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<div class="auth-page">
    <div class="auth-hero">
        <div class="auth-hero-inner">
            <div class="auth-badge">Dental Clinic Appointment System</div>
            <h2 class="auth-title">Welcome back to your clinic portal</h2>
            <p class="auth-copy">
                Access appointments, patient records, clinic messages, and daily workflow tools in one secure system.
            </p>
        </div>
    </div>

    <div class="auth-panel">
        <div class="auth-card">
            <div class="login-logo" aria-hidden="true">
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

            <h1>Login</h1>
            <p class="auth-subtitle">
                Sign in using your username, email, or mobile number to continue to your clinic dashboard.
            </p>

            <?php if (!empty($errors['login'])): ?>
                <div class="error-box">
                    <?= htmlspecialchars((string) $errors['login'][0], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/DentalClinic/public/login" autocomplete="on">
                <?= Csrf::inputField(); ?>

                <div class="form-group">
                    <label for="login">Username, Email, or Mobile Number</label>

                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5v-11Z"
                                  stroke="currentColor"
                                  stroke-width="2"/>
                            <path d="M8 9h8M8 13h5"
                                  stroke="currentColor"
                                  stroke-width="2"
                                  stroke-linecap="round"/>
                        </svg>

                        <input
                            type="text"
                            id="login"
                            name="login"
                            value="<?= htmlspecialchars((string) ($old['login'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            required
                            autocomplete="username"
                            placeholder="Username, email, or 09XXXXXXXXX"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>

                    <div class="password-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 10V8a5 5 0 0 1 10 0v2"
                                  stroke="currentColor"
                                  stroke-width="2"
                                  stroke-linecap="round"/>
                            <path d="M6.5 10h11A1.5 1.5 0 0 1 19 11.5v7A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-7A1.5 1.5 0 0 1 6.5 10Z"
                                  stroke="currentColor"
                                  stroke-width="2"/>
                            <path d="M12 14v2"
                                  stroke="currentColor"
                                  stroke-width="2"
                                  stroke-linecap="round"/>
                        </svg>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            data-target="password"
                            aria-label="Show password"
                            title="Show password"
                        >
                            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"
                                      stroke="currentColor"
                                      stroke-width="2"
                                      stroke-linejoin="round"/>
                                <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                                      stroke="currentColor"
                                      stroke-width="2"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group remember-row">
                    <label>
                        <input type="checkbox" name="remember_me" value="1">
                        Remember me
                    </label>

                    <a class="muted-link" href="/DentalClinic/public/forgot-password">
    Forgot password?
</a>
                </div>

                <button type="submit">Login</button>
            </form>

            <div class="security-note">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 3 5 6v5c0 5 3 8.5 7 10 4-1.5 7-5 7-10V6l-7-3Z"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linejoin="round"/>
                    <path d="m9.5 12 1.7 1.7 3.5-3.7"
                          stroke="currentColor"
                          stroke-width="2"
                          stroke-linecap="round"
                          stroke-linejoin="round"/>
                </svg>
                Protected clinic portal access
            </div>

            <div class="form-footer">
                Don’t have an account?
                <a class="muted-link" href="/DentalClinic/public/register">Create one here</a>
            </div>
        </div>
    </div>
</div>

<script>
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

    button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
    button.setAttribute('title', isHidden ? 'Hide password' : 'Show password');

    button.innerHTML = isHidden
        ? `
            <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M3 3l18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M10.6 10.6A2 2 0 0 0 13.4 13.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M9.9 5.3A10.3 10.3 0 0 1 12 5c6 0 9.5 7 9.5 7a17.8 17.8 0 0 1-3.1 4.1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M6.6 6.7C4 8.4 2.5 12 2.5 12s3.5 7 9.5 7c1.5 0 2.9-.4 4.1-1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        `
        : `
            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linejoin="round"/>
                <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                      stroke="currentColor"
                      stroke-width="2"/>
            </svg>
        `;
});
</script>

<?php
$content = ob_get_clean();
$title = 'Login';
require __DIR__ . '/../layouts/main.php';