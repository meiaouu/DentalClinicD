<?php

use App\Core\Csrf;

$pageTitle = $pageTitle ?? 'Settings';
$authUser = isset($authUser) && is_array($authUser) ? $authUser : [];
$settings = isset($settings) && is_array($settings) ? $settings : [];
$activeTab = strtolower(trim((string) ($activeTab ?? 'account')));

$allowedTabs = ['account', 'privacy', 'language', 'appearance'];

if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'account';
}

$baseUrl = '/DentalClinic/public';

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('selected')) {
    function selected(mixed $actual, string $expected): string
    {
        return (string) $actual === $expected ? 'selected' : '';
    }
}

if (!function_exists('checkedSetting')) {
    function checkedSetting(array $settings, string $key): string
    {
        return (int) ($settings[$key] ?? 0) === 1 ? 'checked' : '';
    }
}

if (!function_exists('csrfField')) {
    function csrfField(): string
    {
        if (method_exists(Csrf::class, 'inputField')) {
            return Csrf::inputField();
        }

        return '<input type="hidden" name="_csrf_token" value="' . e(Csrf::token()) . '">';
    }
}

$firstName = (string) ($authUser['first_name'] ?? '');
$lastName = (string) ($authUser['last_name'] ?? '');
$email = (string) ($authUser['email'] ?? '');
$contactNumber = (string) ($authUser['contact_number'] ?? '');

ob_start();
?>

<link rel="stylesheet" href="<?= e($baseUrl) ?>/assets/css/user-settings.css">

<div class="settings-page" data-settings-page>
    <div class="settings-header">
        <div>
            <p class="settings-eyebrow">Global Preferences</p>
            <h1>Settings</h1>
            <p>Manage your account, privacy, language, and appearance settings.</p>
        </div>
    </div>

    <?php if (!empty($flash_success)): ?>
        <div class="settings-alert settings-alert-success">
            <?= e($flash_success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($flash_error)): ?>
        <div class="settings-alert settings-alert-error">
            <?= e($flash_error) ?>
        </div>
    <?php endif; ?>

    <div class="settings-shell">
        <aside class="settings-menu" aria-label="Settings menu">
            <button type="button" class="settings-menu-item <?= $activeTab === 'account' ? 'is-active' : '' ?>" data-settings-tab="account">
                Account Settings
            </button>
            <button type="button" class="settings-menu-item <?= $activeTab === 'privacy' ? 'is-active' : '' ?>" data-settings-tab="privacy">
                Privacy
            </button>
            <button type="button" class="settings-menu-item <?= $activeTab === 'language' ? 'is-active' : '' ?>" data-settings-tab="language">
                Language
            </button>
            <button type="button" class="settings-menu-item <?= $activeTab === 'appearance' ? 'is-active' : '' ?>" data-settings-tab="appearance">
                Appearance
            </button>
        </aside>

        <main class="settings-content">
            <section class="settings-panel <?= $activeTab === 'account' ? 'is-active' : '' ?>" data-settings-panel="account">
                <div class="settings-card">
                    <div class="settings-card-head">
                        <h2>Account Settings</h2>
                        <p>Update your basic profile information and password.</p>
                    </div>

                    <form method="POST" action="<?= e($baseUrl) ?>/settings/account" class="settings-form">
                        <?= csrfField(); ?>

                        <div class="settings-grid">
                            <label class="settings-field">
                                <span>First name</span>
                                <input type="text" name="first_name" value="<?= e($firstName) ?>" required maxlength="255">
                            </label>

                            <label class="settings-field">
                                <span>Last name</span>
                                <input type="text" name="last_name" value="<?= e($lastName) ?>" required maxlength="255">
                            </label>

                            <label class="settings-field">
                                <span>Email</span>
                                <input type="email" name="email" value="<?= e($email) ?>" required maxlength="255">
                            </label>

                            <label class="settings-field">
                                <span>Contact number</span>
                                <input type="text" name="contact_number" value="<?= e($contactNumber) ?>" required maxlength="20" placeholder="09XXXXXXXXX">
                            </label>
                        </div>

                        <div class="settings-divider"></div>

                        <div class="settings-grid">
                            <label class="settings-field">
                                <span>Current password</span>
                                <input type="password" name="current_password" autocomplete="current-password">
                            </label>

                            <label class="settings-field">
                                <span>New password</span>
                                <input type="password" name="new_password" autocomplete="new-password">
                            </label>

                            <label class="settings-field">
                                <span>Confirm new password</span>
                                <input type="password" name="confirm_new_password" autocomplete="new-password">
                            </label>
                        </div>

                        <p class="settings-help">Leave password fields blank if you do not want to change your password.</p>

                        <div class="settings-actions">
                            <button type="submit" class="settings-btn settings-btn-primary">Save Account</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="settings-panel <?= $activeTab === 'privacy' ? 'is-active' : '' ?>" data-settings-panel="privacy">
                <div class="settings-card">
                    <div class="settings-card-head">
                        <h2>Privacy</h2>
                        <p>Control how your profile and notifications behave.</p>
                    </div>

                    <form method="POST" action="<?= e($baseUrl) ?>/settings/privacy" class="settings-form">
                        <?= csrfField(); ?>

                        <div class="settings-toggle-list">
                            <label class="settings-toggle-row">
                                <span>
                                    <strong>Show profile name in system</strong>
                                    <small>Your display name can appear in dashboard areas where needed.</small>
                                </span>
                                <input type="checkbox" name="show_profile_name" value="1" <?= checkedSetting($settings, 'show_profile_name') ?>>
                                <i></i>
                            </label>

                            <label class="settings-toggle-row">
                                <span>
                                    <strong>Allow appointment notifications</strong>
                                    <small>Receive reminders and appointment-related notices.</small>
                                </span>
                                <input type="checkbox" name="allow_appointment_notifications" value="1" <?= checkedSetting($settings, 'allow_appointment_notifications') ?>>
                                <i></i>
                            </label>

                            <label class="settings-toggle-row">
                                <span>
                                    <strong>Allow message notifications</strong>
                                    <small>Receive notifications for patient-clinic messages.</small>
                                </span>
                                <input type="checkbox" name="allow_message_notifications" value="1" <?= checkedSetting($settings, 'allow_message_notifications') ?>>
                                <i></i>
                            </label>

                            <label class="settings-toggle-row">
                                <span>
                                    <strong>Allow document notifications</strong>
                                    <small>Receive notices for uploaded files, x-rays, and documents.</small>
                                </span>
                                <input type="checkbox" name="allow_document_notifications" value="1" <?= checkedSetting($settings, 'allow_document_notifications') ?>>
                                <i></i>
                            </label>

                            <label class="settings-toggle-row">
                                <span>
                                    <strong>Hide contact number from non-staff users</strong>
                                    <small>Protect contact details from users who do not need to see them.</small>
                                </span>
                                <input type="checkbox" name="hide_contact_from_non_staff" value="1" <?= checkedSetting($settings, 'hide_contact_from_non_staff') ?>>
                                <i></i>
                            </label>
                        </div>

                        <div class="settings-actions">
                            <button type="submit" class="settings-btn settings-btn-primary">Save Privacy</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="settings-panel <?= $activeTab === 'language' ? 'is-active' : '' ?>" data-settings-panel="language">
                <div class="settings-card">
                    <div class="settings-card-head">
                        <h2>Language</h2>
                        <p>Prepare the system for future translation support.</p>
                    </div>

                    <form method="POST" action="<?= e($baseUrl) ?>/settings/language" class="settings-form">
                        <?= csrfField(); ?>

                        <label class="settings-field">
                            <span>Preferred language</span>
                            <select name="language">
                                <option value="english" <?= selected($settings['language'] ?? 'english', 'english') ?>>English</option>
                                <option value="filipino" <?= selected($settings['language'] ?? 'english', 'filipino') ?>>Filipino</option>
                            </select>
                        </label>

                        <p class="settings-help">This stores your preference globally. Full translation can be added later using language files.</p>

                        <div class="settings-actions">
                            <button type="submit" class="settings-btn settings-btn-primary">Save Language</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="settings-panel <?= $activeTab === 'appearance' ? 'is-active' : '' ?>" data-settings-panel="appearance">
                <div class="settings-card">
                    <div class="settings-card-head">
                        <h2>Appearance</h2>
                        <p>Customize how your dashboard looks after login.</p>
                    </div>

                    <form method="POST" action="<?= e($baseUrl) ?>/settings/appearance" class="settings-form" data-appearance-form>
                        <?= csrfField(); ?>

                        <div class="settings-grid">
                            <label class="settings-field">
                                <span>Theme mode</span>
                                <select name="theme_mode" data-appearance-control>
                                    <option value="light" <?= selected($settings['theme_mode'] ?? 'light', 'light') ?>>Light</option>
                                    <option value="dark" <?= selected($settings['theme_mode'] ?? 'light', 'dark') ?>>Dark</option>
                                    <option value="system" <?= selected($settings['theme_mode'] ?? 'light', 'system') ?>>System</option>
                                </select>
                            </label>

                            <label class="settings-field">
                                <span>Accent color</span>
                                <select name="accent_color" data-appearance-control>
                                    <option value="teal" <?= selected($settings['accent_color'] ?? 'teal', 'teal') ?>>Teal</option>
                                    <option value="blue" <?= selected($settings['accent_color'] ?? 'teal', 'blue') ?>>Blue</option>
                                    <option value="green" <?= selected($settings['accent_color'] ?? 'teal', 'green') ?>>Green</option>
                                    <option value="purple" <?= selected($settings['accent_color'] ?? 'teal', 'purple') ?>>Purple</option>
                                    <option value="gray" <?= selected($settings['accent_color'] ?? 'teal', 'gray') ?>>Gray</option>
                                </select>
                            </label>

                            <label class="settings-field">
                                <span>Font size</span>
                                <select name="font_size" data-appearance-control>
                                    <option value="small" <?= selected($settings['font_size'] ?? 'normal', 'small') ?>>Small</option>
                                    <option value="normal" <?= selected($settings['font_size'] ?? 'normal', 'normal') ?>>Normal</option>
                                    <option value="large" <?= selected($settings['font_size'] ?? 'normal', 'large') ?>>Large</option>
                                </select>
                            </label>

                            <label class="settings-field">
                                <span>Layout density</span>
                                <select name="layout_density" data-appearance-control>
                                    <option value="comfortable" <?= selected($settings['layout_density'] ?? 'comfortable', 'comfortable') ?>>Comfortable</option>
                                    <option value="compact" <?= selected($settings['layout_density'] ?? 'comfortable', 'compact') ?>>Compact</option>
                                </select>
                            </label>

                            <label class="settings-field">
                                <span>Border radius</span>
                                <select name="border_radius" data-appearance-control>
                                    <option value="none" <?= selected($settings['border_radius'] ?? 'small', 'none') ?>>None</option>
                                    <option value="small" <?= selected($settings['border_radius'] ?? 'small', 'small') ?>>Small</option>
                                    <option value="medium" <?= selected($settings['border_radius'] ?? 'small', 'medium') ?>>Medium</option>
                                </select>
                            </label>

                            <label class="settings-field">
                                <span>Sidebar mode</span>
                                <select name="sidebar_mode" data-appearance-control>
                                    <option value="expanded" <?= selected($settings['sidebar_mode'] ?? 'expanded', 'expanded') ?>>Expanded</option>
                                    <option value="compact" <?= selected($settings['sidebar_mode'] ?? 'expanded', 'compact') ?>>Compact</option>
                                </select>
                            </label>
                        </div>

                        <div class="settings-preview">
                            <strong>Live preview</strong>
                            <span>Changes preview immediately. Click save to keep them after refresh or login.</span>
                        </div>

                        <div class="settings-actions">
                            <button type="submit" class="settings-btn settings-btn-primary">Save Appearance</button>
                        </div>
                    </form>

                    <form method="POST" action="<?= e($baseUrl) ?>/settings/reset-appearance" class="settings-reset-form" data-reset-appearance-form>
                        <?= csrfField(); ?>
                        <button type="submit" class="settings-btn settings-btn-secondary">Reset Appearance</button>
                    </form>
                </div>
            </section>
        </main>
    </div>
</div>

<script src="<?= e($baseUrl) ?>/assets/js/user-settings.js"></script>

<?php
$content = ob_get_clean();

$roleName = strtolower((string) ($authUser['role_name'] ?? $authUser['role'] ?? ''));

$layoutCandidates = [
    'staff' => __DIR__ . '/../layouts/staff.php',
    'dentist' => __DIR__ . '/../layouts/dentist.php',
    'patient' => __DIR__ . '/../layouts/patient.php',
    'admin' => __DIR__ . '/../layouts/admin.php',
    'common' => __DIR__ . '/../layouts/app.php',
];

$layoutFile = $layoutCandidates[$roleName] ?? $layoutCandidates['common'];

if (is_file($layoutFile)) {
    require $layoutFile;
} else {
    echo $content;
}