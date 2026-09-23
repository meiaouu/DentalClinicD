<?php

use App\Core\Csrf;

$pageTitle = $pageTitle ?? 'Account Settings & Privacy';
$authUser = isset($authUser) && is_array($authUser) ? $authUser : [];
$settings = isset($settings) && is_array($settings) ? $settings : [];

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$firstName = (string) ($authUser['first_name'] ?? '');
$lastName = (string) ($authUser['last_name'] ?? '');
$email = (string) ($authUser['email'] ?? '');
$contactNumber = (string) ($authUser['contact_number'] ?? '');

ob_start();
?>

<style>
.settings-page {
    padding: 24px;
    background: #f5f7fb;
    min-height: calc(100vh - 84px);
}

.settings-card {
    max-width: 900px;
    margin: 0 auto;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    padding: 22px;
}

.settings-title {
    margin: 0 0 6px;
    color: #111827;
    font-size: 24px;
    font-weight: 900;
}

.settings-subtitle {
    margin: 0 0 22px;
    color: #6b7280;
    font-size: 14px;
}

.settings-section {
    border-top: 1px solid #e5e7eb;
    padding-top: 18px;
    margin-top: 18px;
}

.settings-section h3 {
    margin: 0 0 14px;
    color: #111827;
    font-size: 17px;
    font-weight: 900;
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.settings-field {
    display: grid;
    gap: 6px;
}

.settings-field label {
    color: #374151;
    font-size: 13px;
    font-weight: 800;
}

.settings-field input,
.settings-field select {
    width: 100%;
    min-height: 40px;
    border: 1px solid #d1d5db;
    padding: 8px 10px;
    outline: none;
    background: #ffffff;
}

.settings-field input:focus,
.settings-field select:focus {
    border-color: #0f9d8a;
}

.settings-check {
    display: flex;
    gap: 10px;
    align-items: center;
    color: #374151;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 10px;
}

.settings-check input {
    width: 16px;
    height: 16px;
}

.settings-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 18px;
}

.settings-btn {
    min-height: 40px;
    padding: 0 16px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    cursor: pointer;
    font-weight: 800;
}

.settings-btn.primary {
    background: #0f9d8a;
    border-color: #0f9d8a;
    color: #ffffff;
}

.settings-alert {
    display: none;
    margin-bottom: 16px;
    padding: 10px 12px;
    font-size: 13px;
    font-weight: 700;
}

.settings-alert.success {
    display: block;
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.settings-alert.error {
    display: block;
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

@media (max-width: 760px) {
    .settings-page {
        padding: 14px;
    }

    .settings-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="settings-page">
    <div class="settings-card">
        <h1 class="settings-title">Account Settings & Privacy</h1>
        <p class="settings-subtitle">
            Manage your account information, privacy preferences, language, and appearance settings.
        </p>

        <div class="settings-alert" id="settingsAlert"></div>

        <form id="accountSettingsForm">
            <?= Csrf::inputField(); ?>

            <div class="settings-section">
                <h3>Account Information</h3>

                <div class="settings-grid">
                    <div class="settings-field">
                        <label for="firstName">First Name</label>
                        <input type="text" name="first_name" id="firstName" value="<?= e($firstName) ?>" required>
                    </div>

                    <div class="settings-field">
                        <label for="lastName">Last Name</label>
                        <input type="text" name="last_name" id="lastName" value="<?= e($lastName) ?>" required>
                    </div>

                    <div class="settings-field">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" value="<?= e($email) ?>" required>
                    </div>

                    <div class="settings-field">
                        <label for="contactNumber">Contact Number</label>
                        <input type="text" name="contact_number" id="contactNumber" value="<?= e($contactNumber) ?>">
                    </div>
                </div>

                <div class="settings-actions">
                    <button type="submit" class="settings-btn primary">Save Account</button>
                </div>
            </div>
        </form>

        <form id="privacySettingsForm">
            <?= Csrf::inputField(); ?>

            <div class="settings-section">
                <h3>Privacy & Notifications</h3>

                <label class="settings-check">
                    <input type="checkbox" name="show_profile_name" value="1" <?= (int) ($settings['show_profile_name'] ?? 1) === 1 ? 'checked' : '' ?>>
                    Show profile name
                </label>

                <label class="settings-check">
                    <input type="checkbox" name="allow_appointment_reminders" value="1" <?= (int) ($settings['allow_appointment_reminders'] ?? 1) === 1 ? 'checked' : '' ?>>
                    Allow appointment reminders
                </label>

                <label class="settings-check">
                    <input type="checkbox" name="allow_message_notifications" value="1" <?= (int) ($settings['allow_message_notifications'] ?? 1) === 1 ? 'checked' : '' ?>>
                    Allow message notifications
                </label>

                <label class="settings-check">
                    <input type="checkbox" name="allow_document_notifications" value="1" <?= (int) ($settings['allow_document_notifications'] ?? 1) === 1 ? 'checked' : '' ?>>
                    Allow document notifications
                </label>

                <label class="settings-check">
                    <input type="checkbox" name="hide_contact_from_non_staff" value="1" <?= (int) ($settings['hide_contact_from_non_staff'] ?? 0) === 1 ? 'checked' : '' ?>>
                    Hide contact number from non-staff users
                </label>

                <div class="settings-actions">
                    <button type="submit" class="settings-btn primary">Save Privacy</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const baseUrl = '/DentalClinic/public';
    const alertBox = document.getElementById('settingsAlert');

    function showAlert(type, message) {
        if (!alertBox) {
            return;
        }

        alertBox.className = 'settings-alert ' + type;
        alertBox.textContent = message;
    }

    function submitAjax(form, url) {
        const formData = new FormData(form);

        fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.success) {
                    showAlert('error', data.message || 'Unable to save settings.');
                    return;
                }

                showAlert('success', data.message || 'Settings saved successfully.');
            })
            .catch(function () {
                showAlert('error', 'Request failed. Please try again.');
            });
    }

    const accountForm = document.getElementById('accountSettingsForm');
    const privacyForm = document.getElementById('privacySettingsForm');

    if (accountForm) {
        accountForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAjax(accountForm, baseUrl + '/settings/account');
        });
    }

    if (privacyForm) {
        privacyForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitAjax(privacyForm, baseUrl + '/settings/privacy');
        });
    }
});
</script>

<?php
$content = ob_get_clean();

if (isset($dentistContent)) {
    $dentistContent = $content;
}

if (file_exists(__DIR__ . '/../layouts/dentist.php')) {
    require __DIR__ . '/../layouts/dentist.php';
} elseif (file_exists(__DIR__ . '/../layouts/app.php')) {
    $staffContent = $content;
    require __DIR__ . '/../layouts/app.php';
} else {
    echo $content;
}