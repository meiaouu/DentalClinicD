<?php

use App\Core\Auth;

$title = $title ?? 'Patient Portal';
$pageTitle = $pageTitle ?? $title;
$content = $content ?? '';

$baseUrl = '/DentalClinic/public';

$authUser = $authUser ?? [];

if (empty($authUser) && class_exists(Auth::class)) {
    $authUser = Auth::user() ?? [];
}

if (!function_exists('patient_layout_e')) {
    function patient_layout_e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$patientUserName = trim(
    implode(' ', array_filter([
        $authUser['first_name'] ?? '',
        $authUser['last_name'] ?? '',
    ]))
);

if ($patientUserName === '') {
    $patientUserName = (string) ($authUser['username'] ?? 'Patient');
}

$patientUserInitial = strtoupper(substr($patientUserName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= patient_layout_e($title) ?> | Dental Clinic</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        :root {
            --patient-navy: #10233f;
            --patient-navy-dark: #0b1628;
            --patient-teal: #0d9e8c;
            --patient-teal-dark: #08796c;
            --patient-teal-soft: #e6f7f5;
            --patient-bg: #f4f8fb;
            --patient-card: #ffffff;
            --patient-text: #1f2937;
            --patient-muted: #64748b;
            --patient-line: #dbe5ef;
            --patient-danger: #dc2626;
            --patient-sidebar-width: 280px;
            --patient-topbar-height: 74px;
            --patient-shadow: 0 18px 46px rgba(15, 23, 42, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--patient-bg);
            color: var(--patient-text);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            overflow-x: hidden;
        }

        body.patient-sidebar-open {
            overflow: hidden;
        }

        a {
            color: inherit;
        }

        .patient-app-shell {
            min-height: 100vh;
            display: flex;
            background:
                radial-gradient(circle at top left, rgba(13, 158, 140, 0.14), transparent 28%),
                linear-gradient(135deg, #f8fafc, #eef7f6);
        }

        .patient-main {
            min-width: 0;
            flex: 1;
            margin-left: var(--patient-sidebar-width);
            display: flex;
            flex-direction: column;
        }

        .patient-content {
            min-width: 0;
            flex: 1;
        }

        .patient-sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1050;
            background: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(2px);
        }

        .patient-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--patient-sidebar-width);
            height: 100vh;
            z-index: 1100;
            background: linear-gradient(180deg, var(--patient-navy) 0%, var(--patient-navy-dark) 100%);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            transition: transform 0.25s ease;
        }

        .patient-sidebar-brand {
            min-height: var(--patient-topbar-height);
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .patient-brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: rgba(13, 158, 140, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7ef0e3;
            font-weight: 900;
            border: 1px solid rgba(13, 158, 140, 0.35);
            flex-shrink: 0;
        }

        .patient-brand-text strong {
            display: block;
            font-size: 14px;
            line-height: 1.25;
        }

        .patient-brand-text span {
            display: block;
            margin-top: 2px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.58);
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .patient-sidebar-user {
            margin: 18px;
            padding: 14px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .patient-user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--patient-teal);
            color: #ffffff;
            font-weight: 900;
            flex-shrink: 0;
        }

        .patient-user-meta {
            min-width: 0;
        }

        .patient-user-meta strong {
            display: block;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .patient-user-meta span {
            display: block;
            margin-top: 2px;
            color: rgba(255, 255, 255, 0.55);
            font-size: 11px;
            font-weight: 700;
        }

        .patient-sidebar-nav {
            padding: 4px 14px 16px;
            display: grid;
            gap: 6px;
            overflow-y: auto;
            flex: 1;
        }

        .patient-nav-label {
            margin: 14px 10px 6px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgba(255, 255, 255, 0.42);
            font-weight: 900;
        }

        .patient-nav-link {
            min-height: 46px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 12px;
            border-radius: 14px;
            color: rgba(255, 255, 255, 0.72);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 800;
            transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
        }

        .patient-nav-link:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            transform: translateX(2px);
        }

        .patient-nav-link.is-active {
            background: var(--patient-teal);
            color: #ffffff;
            box-shadow: 0 10px 24px rgba(13, 158, 140, 0.26);
        }

        .patient-nav-icon {
            width: 22px;
            text-align: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .patient-sidebar-footer {
            padding: 16px 18px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .patient-logout-form {
            margin: 0;
        }

        .patient-logout-btn {
            width: 100%;
            min-height: 44px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
            font-weight: 900;
            cursor: pointer;
            transition: background 0.18s ease, transform 0.18s ease;
        }

        .patient-logout-btn:hover {
            background: rgba(220, 38, 38, 0.22);
            transform: translateY(-1px);
        }

        .patient-topbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            min-height: var(--patient-topbar-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 26px;
            background: rgba(255, 255, 255, 0.86);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(219, 229, 239, 0.9);
        }

        .patient-topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .patient-menu-btn {
            display: none;
            width: 42px;
            height: 42px;
            border: 1px solid var(--patient-line);
            border-radius: 14px;
            background: #ffffff;
            color: var(--patient-navy);
            cursor: pointer;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 900;
        }

        .patient-topbar-title {
            min-width: 0;
        }

        .patient-topbar-title h1 {
            margin: 0;
            color: var(--patient-navy);
            font-size: 20px;
            letter-spacing: -0.03em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .patient-topbar-title p {
            margin: 3px 0 0;
            color: var(--patient-muted);
            font-size: 12px;
        }

        .patient-topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .patient-topbar-btn {
            min-height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            padding: 0 14px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 900;
            border: 1px solid var(--patient-line);
            background: #ffffff;
            color: var(--patient-navy);
        }

        .patient-topbar-btn.primary {
            background: var(--patient-teal);
            color: #ffffff;
            border-color: var(--patient-teal);
            box-shadow: 0 10px 22px rgba(13, 158, 140, 0.22);
        }

        .patient-topbar-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 42px;
            padding: 5px 10px 5px 5px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid var(--patient-line);
        }

        .patient-topbar-profile .patient-user-avatar {
            width: 32px;
            height: 32px;
            font-size: 13px;
        }

        .patient-topbar-profile span {
            font-size: 13px;
            font-weight: 900;
            color: var(--patient-navy);
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width: 1024px) {
            .patient-sidebar {
                transform: translateX(-100%);
            }

            body.patient-sidebar-open .patient-sidebar {
                transform: translateX(0);
            }

            body.patient-sidebar-open .patient-sidebar-overlay {
                display: block;
            }

            .patient-main {
                margin-left: 0;
            }

            .patient-menu-btn {
                display: inline-flex;
            }
        }

        @media (max-width: 700px) {
            .patient-topbar {
                padding: 12px 14px;
            }

            .patient-topbar-btn {
                display: none;
            }

            .patient-topbar-profile span {
                display: none;
            }

            .patient-sidebar {
                width: 84vw;
                max-width: 310px;
            }
        }

        @media (max-width: 520px) {
            .patient-topbar {
                gap: 8px;
            }

            .patient-topbar-title h1 {
                font-size: 16px;
                white-space: normal;
                line-height: 1.3;
            }

            .patient-topbar-title p {
                display: none;
            }

            .patient-user-avatar {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }

            .patient-topbar-profile {
                padding: 4px 8px 4px 4px;
            }
        }
    </style>
</head>
<body>
<div class="patient-app-shell">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <div class="patient-sidebar-overlay" data-patient-sidebar-close></div>

    <div class="patient-main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>

        <main class="patient-content">
            <?= $content ?>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const openButtons = document.querySelectorAll('[data-patient-sidebar-toggle]');
    const closeButtons = document.querySelectorAll('[data-patient-sidebar-close]');

    function openSidebar() {
        document.body.classList.add('patient-sidebar-open');
    }

    function closeSidebar() {
        document.body.classList.remove('patient-sidebar-open');
    }

    openButtons.forEach(function (button) {
        button.addEventListener('click', openSidebar);
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', closeSidebar);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });
});
</script>
</body>
</html>