<?php

use App\Core\Csrf;

$authUser = $authUser ?? [];
$settings = $settings ?? [];
$pageTitle = $pageTitle ?? 'Account Settings & Privacy';
$csrfToken = $csrfToken ?? Csrf::token();

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('settingChecked')) {
    function settingChecked(array $settings, string $key): string
    {
        return ((int) ($settings[$key] ?? 0) === 1) ? 'checked' : '';
    }
}

ob_start();
?>

<link rel="stylesheet" href="/DentalClinic/public/assets/css/account-settings-privacy.css">

<script>
    window.DENTAL_ACCOUNT_PRIVACY = {
        baseUrl: '/DentalClinic/public',
        csrfToken: '<?= e($csrfToken) ?>'
    };
</script>

<div class="account-settings-page">
    <div class="account-settings-container">
        <div class="account-settings-header">
            <div>
                <h1>Account Settings & Privacy</h1>
                <p>Manage your profile information, password, visibility, and notification preferences.</p>
            </div>

            <a href="/DentalClinic/public/dentist/dashboard" class="account-settings-back">
                Back to Dashboard
            </a>
        </div>

        <div class="settings-alert is-hidden" id="accountPrivacyMessage"></div>

        <div class="account-settings-grid">
            <section class="account-settings-card">
                <div class="settings-card-header">
                    <h2>Account Settings</h2>
                    <p>Update your name, email, contact number, or password.</p>
                </div>

                <form id="accountSettingsForm" autocomplete="off">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

                    <div class="settings-form-grid">
                        <div class="settings-form-group">
                            <label for="first_name">First name</label>
                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                value="<?= e($authUser['first_name'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="settings-form-group">
                            <label for="last_name">Last name</label>
                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                value="<?= e($authUser['last_name'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="settings-form-group settings-full">
                            <label for="email">Email</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?= e($authUser['email'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="settings-form-group settings-full">
                            <label for="contact_number">Contact number</label>
                            <input
                                type="text"
                                id="contact_number"
                                name="contact_number"
                                value="<?= e($authUser['contact_number'] ?? '') ?>"
                            >
                        </div>

                        <div class="settings-section-divider settings-full"></div>

                        <div class="settings-form-group settings-full">
                            <label for="current_password">Current password</label>
                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                autocomplete="current-password"
                            >
                            <small>Required only if you want to change your password.</small>
                        </div>

                        <div class="settings-form-group">
                            <label for="new_password">New password</label>
                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                autocomplete="new-password"
                            >
                        </div>

                        <div class="settings-form-group">
                            <label for="confirm_new_password">Confirm new password</label>
                            <input
                                type="password"
                                id="confirm_new_password"
                                name="confirm_new_password"
                                autocomplete="new-password"
                            >
                        </div>
                    </div>

                    <div class="settings-actions">
                        <button type="submit" class="settings-primary-button">
                            Save Account Settings
                        </button>
                    </div>
                </form>
            </section>

            <section class="account-settings-card">
                <div class="settings-card-header">
                    <h2>Privacy</h2>
                    <p>Control profile visibility and notification preferences.</p>
                </div>

                <form id="privacySettingsForm">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">

                    <label class="settings-toggle-row">
                        <span>
                            <strong>Show profile name in system</strong>
                            <small>Allow your profile name to appear in system areas.</small>
                        </span>
                        <input
                            type="checkbox"
                            name="show_profile_name"
                            value="1"
                            <?= settingChecked($settings, 'show_profile_name') ?>
                        >
                    </label>

                    <label class="settings-toggle-row">
                        <span>
                            <strong>Allow appointment reminders</strong>
                            <small>Receive appointment reminder notifications.</small>
                        </span>
                        <input
                            type="checkbox"
                            name="allow_appointment_reminders"
                            value="1"
                            <?= settingChecked($settings, 'allow_appointment_reminders') ?>
                        >
                    </label>

                    <label class="settings-toggle-row">
                        <span>
                            <strong>Allow message notifications</strong>
                            <small>Receive message alerts from the system.</small>
                        </span>
                        <input
                            type="checkbox"
                            name="allow_message_notifications"
                            value="1"
                            <?= settingChecked($settings, 'allow_message_notifications') ?>
                        >
                    </label>

                    <label class="settings-toggle-row">
                        <span>
                            <strong>Allow file/document notifications</strong>
                            <small>Receive alerts when documents are uploaded or updated.</small>
                        </span>
                        <input
                            type="checkbox"
                            name="allow_document_notifications"
                            value="1"
                            <?= settingChecked($settings, 'allow_document_notifications') ?>
                        >
                    </label>

                    <label class="settings-toggle-row">
                        <span>
                            <strong>Hide contact number from non-staff users</strong>
                            <small>Keep your contact number hidden from users who should not view it.</small>
                        </span>
                        <input
                            type="checkbox"
                            name="hide_contact_from_non_staff"
                            value="1"
                            <?= settingChecked($settings, 'hide_contact_from_non_staff') ?>
                        >
                    </label>

                    <div class="settings-actions">
                        <button type="submit" class="settings-primary-button">
                            Save Privacy Settings
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>

<script src="/DentalClinic/public/assets/js/account-settings-privacy.js"></script>

<?php
$content = ob_get_clean();
$title = 'Account Settings & Privacy';
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
?>