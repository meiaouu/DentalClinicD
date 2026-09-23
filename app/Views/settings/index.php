<?php
$pageTitle = 'Owner/Admin Settings';

$baseUrl = '/DentalClinic/public';

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$sections = [
    'clinic-profile' => [
        'title' => 'Clinic Profile',
        'description' => 'Manage clinic name, logo, address, contact details, owner name, and about text.',
    ],
    'public-website' => [
        'title' => 'Public Website',
        'description' => 'Manage homepage title, subtitle, hero image, about section, gallery, and call-to-action.',
    ],
    'appointment-rules' => [
        'title' => 'Appointment Rules',
        'description' => 'Control online booking, guest booking, approval rules, cancellation, and rescheduling.',
    ],
    'clinic-hours' => [
        'title' => 'Clinic Hours',
        'description' => 'Set weekly open days, opening time, closing time, breaks, and clinic schedule.',
    ],
    'services' => [
        'title' => 'Services',
        'description' => 'Add, edit, activate, or deactivate offered dental services.',
    ],
    'notifications' => [
        'title' => 'Notifications and Reminders',
        'description' => 'Manage appointment reminders and notification settings for patients, staff, and dentists.',
    ],
    'messaging' => [
        'title' => 'Messaging and Chatbot',
        'description' => 'Configure chatbot, guest chat, patient messaging, and file upload permissions.',
    ],
    'billing' => [
        'title' => 'Billing and Receipt',
        'description' => 'Configure billing module, payment methods, receipt prefix, discount rules, and footer message.',
    ],
    'documents' => [
        'title' => 'Documents',
        'description' => 'Manage document upload permissions, allowed file types, file size limit, and document audit.',
    ],
    'security' => [
        'title' => 'Security',
        'description' => 'Configure password rules, 2FA, session timeout, login attempts, and maintenance mode.',
    ],
    'audit' => [
        'title' => 'Audit Trail',
        'description' => 'Control which system actions are recorded in the audit trail.',
    ],
    'backup' => [
        'title' => 'Backup and Recovery',
        'description' => 'Manage backup retention, storage path, backup download, and restore permissions.',
    ],
    'appearance' => [
        'title' => 'Appearance and Branding',
        'description' => 'Manage logo, favicon, theme mode, accent color, and public homepage branding.',
    ],
    'maintenance' => [
        'title' => 'Maintenance',
        'description' => 'Enable maintenance mode, set maintenance message, and manage cleanup options.',
    ],
];

ob_start();
?>

<style>
    .settings-menu-page {
        max-width: 1180px;
        margin: 0 auto;
        padding: 34px 24px;
    }

    .settings-menu-header {
        margin-bottom: 24px;
    }

    .settings-menu-eyebrow {
        margin: 0 0 8px;
        color: #0f766e;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .14em;
        text-transform: uppercase;
    }

    .settings-menu-title {
        margin: 0;
        color: #0f172a;
        font-size: 30px;
        font-weight: 900;
        letter-spacing: .04em;
    }

    .settings-menu-subtitle {
        margin: 8px 0 0;
        color: #64748b;
        font-size: 15px;
        line-height: 1.6;
    }

    .settings-menu-grid {
        display: grid;
        gap: 14px;
    }

    .settings-menu-card {
        min-height: 96px;
        border: 1px solid #dbe3ea;
        border-radius: 12px;
        background: #ffffff;
        padding: 22px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        color: #0f172a;
        text-decoration: none;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .05);
    }

    .settings-menu-card:hover {
        border-color: #0f766e;
        background: #f8fffc;
    }

    .settings-menu-card strong {
        display: block;
        color: #0f172a;
        font-size: 18px;
        font-weight: 900;
        letter-spacing: .04em;
    }

    .settings-menu-card span {
        display: block;
        margin-top: 6px;
        color: #64748b;
        font-size: 14px;
        line-height: 1.5;
    }

    .settings-menu-arrow {
        width: 42px;
        height: 42px;
        border-radius: 999px;
        background: #ecfdf5;
        color: #0f766e;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        font-weight: 900;
        flex-shrink: 0;
    }

    @media (max-width: 700px) {
        .settings-menu-page {
            padding: 24px 16px;
        }

        .settings-menu-card {
            padding: 18px;
            align-items: flex-start;
        }
    }
</style>

<div class="settings-menu-page">
    <div class="settings-menu-header">
        <p class="settings-menu-eyebrow">System Configuration</p>
        <h1 class="settings-menu-title">Owner/Admin Settings</h1>
        <p class="settings-menu-subtitle">
            Choose a settings area to configure. Each setting category opens as a separate page.
        </p>
    </div>

    <div class="settings-menu-grid">
        <?php foreach ($sections as $sectionKey => $section): ?>
            <a href="<?= e($baseUrl . '/admin/settings/' . $sectionKey) ?>" class="settings-menu-card">
                <div>
                    <strong><?= e($section['title']) ?></strong>
                    <span><?= e($section['description']) ?></span>
                </div>

                <span class="settings-menu-arrow">›</span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';