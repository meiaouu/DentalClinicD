<?php
$pageTitle = $pageTitle ?? 'Dentist Panel';
$content = $content ?? '';
$dentistContent = $dentistContent ?? '';
$authUser = $authUser ?? (\App\Core\Auth::user() ?? null);

$baseUrl = '/DentalClinic/public';

/*
    This assumes this file is located at:
    app/Views/dentist/layouts/app.php

    dirname(__DIR__, 4) goes back to your project root.
*/
$projectRoot = dirname(__DIR__, 4);

$notificationCssPath = $projectRoot . '/public/assets/css/dentist-notification.css';
$notificationJsPath = $projectRoot . '/public/assets/js/dentist-notification.js';

$userThemeCssPath = $projectRoot . '/public/assets/css/user-theme.css';
$userSettingsJsPath = $projectRoot . '/public/assets/js/user-settings.js';

$notificationCssVersion = is_file($notificationCssPath) ? filemtime($notificationCssPath) : time();
$notificationJsVersion = is_file($notificationJsPath) ? filemtime($notificationJsPath) : time();

$userThemeCssVersion = is_file($userThemeCssPath) ? filemtime($userThemeCssPath) : time();
$userSettingsJsVersion = is_file($userSettingsJsPath) ? filemtime($userSettingsJsPath) : time();

$userSettings = [];
$bodyClasses = 'theme-light accent-teal font-normal density-comfortable radius-small sidebar-expanded';
$htmlLang = 'en';

if (is_array($authUser) && !empty($authUser['user_id'])) {
    try {
        $settingsRepository = new \App\Repositories\UserSettingsRepository();
        $userSettings = $settingsRepository->findByUserId((int) $authUser['user_id']);
        $bodyClasses = $settingsRepository->bodyClasses($userSettings);

        if (($userSettings['language'] ?? 'english') === 'filipino') {
            $htmlLang = 'fil';
        }
    } catch (\Throwable $e) {
        $userSettings = [];
        $bodyClasses = 'theme-light accent-teal font-normal density-comfortable radius-small sidebar-expanded';
        $htmlLang = 'en';
    }
}

$mainContent = '';

foreach ([
    $dentistContent ?? null,
    $content ?? null,
    $pageContent ?? null,
    $body ?? null,
    $viewContent ?? null,
] as $candidate) {
    if (is_string($candidate) && trim($candidate) !== '') {
        $mainContent = $candidate;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="<?= $baseUrl ?>/assets/css/user-theme.css?v=<?= (int) $userThemeCssVersion ?>"
    >

    <link
        rel="stylesheet"
        href="<?= $baseUrl ?>/assets/css/dentist-notification.css?v=<?= (int) $notificationCssVersion ?>"
    >

    <style>
        :root {
            --font-ui: 'DM Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --font-mono: 'DM Mono', Consolas, "Liberation Mono", monospace;

            --dentist-sidebar-w: 248px;

            --dentist-navy: #0b1f3a;
            --dentist-navy-light: #1e3f6e;
            --dentist-mint: #2ec4a5;
            --dentist-mint-dark: #1fa88c;
            --dentist-mint-soft: #e6f9f5;
            --dentist-mint-border: #b2ede3;
            --dentist-cream: #ffffff;
            --dentist-warm-white: #ffffff;
            --dentist-stone-100: #f2f0ec;
            --dentist-stone-200: #e4e1da;
            --dentist-stone-400: #b0aa9e;
            --dentist-stone-600: #6e6860;
            --dentist-stone-800: #3a3630;
            --dentist-red: #e53e3e;

            --dentist-r-sm: 5px;
            --dentist-r-md: 8px;
            --dentist-r-lg: 12px;
            --dentist-r-xl: 2px;
            --dentist-sh-card: 0 6px 26px rgba(11, 31, 58, .08), 0 1px 3px rgba(11, 31, 58, .05);
            --dentist-sh-sm: 0 2px 8px rgba(11, 31, 58, .07);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            width: 100%;
            font-family: var(--font-ui);
            background: var(--dentist-cream);
            color: var(--ui-text, #0f172a);
            overflow-x: hidden;
        }

        body {
            min-height: 100vh;
            font-size: 14px;
            line-height: 1.6;
        }

        html,
        body,
        .app-shell,
        .dentist-shell,
        .dentist-layout,
        .dentist-main,
        .dentist-content,
        .dentist-body,
        main,
        section,
        div,
        p,
        span,
        a,
        label,
        input,
        select,
        textarea,
        button,
        table,
        th,
        td {
            font-family: var(--font-ui) !important;
        }

        code,
        pre,
        .request-code,
        .patient-code,
        .billing-code,
        .appointment-code,
        .mono {
            font-family: var(--font-mono) !important;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        button {
            cursor: pointer;
        }

        .dentist-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
            background:
                radial-gradient(ellipse 60% 50% at 10% 5%, rgba(46, 196, 165, .07) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 90% 90%, rgba(11, 31, 58, .06) 0%, transparent 55%),
                var(--dentist-cream);
        }

        .dentist-bg-pattern {
            position: absolute;
            inset: -40px;
            opacity: .04;
            background-image:
                linear-gradient(var(--dentist-navy) 1px, transparent 1px),
                linear-gradient(90deg, var(--dentist-navy) 1px, transparent 1px);
            background-size: 32px 32px;
            animation: dentistGridMove 28s linear infinite;
        }

        .dentist-bg::before {
            content: "";
            position: absolute;
            width: 420px;
            height: 420px;
            top: -170px;
            left: 18%;
            border-radius: 999px;
            background: rgba(46, 196, 165, 0.08);
            filter: blur(16px);
        }

        .dentist-bg::after {
            content: "";
            position: absolute;
            width: 500px;
            height: 500px;
            right: -220px;
            bottom: -180px;
            border-radius: 999px;
            background: rgba(11, 31, 58, 0.07);
            filter: blur(18px);
        }

        @keyframes dentistGridMove {
            from {
                transform: translate(0, 0);
            }

            to {
                transform: translate(32px, 32px);
            }
        }

        .dentist-shell {
            position: relative;
            z-index: 1;
            min-height: 100dvh;
            width: 100%;
            background: transparent;
            overflow-x: hidden;
            overflow-y: visible;
        }

        .dentist-main {
            position: relative;
            z-index: 1;
            min-width: 0;
            min-height: 100dvh;
            margin-left: var(--dentist-sidebar-w);
            width: calc(100% - var(--dentist-sidebar-w));
            display: flex;
            flex-direction: column;
            background: transparent;
            overflow-x: visible;
            overflow-y: visible;
        }

        .dentist-body {
            position: relative;
            z-index: 1;
            min-width: 0;
            flex: 1;
            padding: 82px 18px 18px;
            width: 100%;
            background: transparent;
            color: var(--ui-text, #0f172a);
            overflow-x: hidden;
        }

        /*
            These make the global grid visible when old dentist pages
            still use solid page backgrounds.
        */
        .dentist-dashboard-page,
        .dentist-appointments-page,
        .dentist-calendar-page,
        .dentist-workspace-page,
        .dentist-availability-page,
        .dentist-clinical-record-page,
        .dentist-settings-page,
        .dentist-page,
        .dashboard-page,
        .calendar-page,
        .workspace-page {
            background: transparent !important;
        }

        .dentist-sidebar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: var(--dentist-sidebar-w) !important;
            min-width: var(--dentist-sidebar-w) !important;
            max-width: var(--dentist-sidebar-w) !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            z-index: 1000;
        }

        .dentist-sidebar::-webkit-scrollbar {
            width: 7px;
        }

        .dentist-sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .dentist-sidebar::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.35);
            border-radius: 999px;
        }

        .dentist-sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.65);
        }

        .dentist-topbar {
            position: relative;
            z-index: 3000;
            overflow: visible !important;
            font-family: var(--font-ui) !important;
        }

        .dentist-topbar-right,
        .dentist-topbar-actions {
            position: relative;
            z-index: 3001;
            overflow: visible !important;
        }

        .dentist-notification-box,
        .dentist-notify-box {
            position: relative;
            z-index: 3002;
            overflow: visible !important;
        }

        #dentistNotificationList {
            z-index: 5000 !important;
        }

        @media (max-width: 900px) {
            .dentist-main {
                margin-left: 0;
                width: 100%;
            }

            .dentist-sidebar {
                position: relative !important;
                width: 100% !important;
                min-width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
            }

            .dentist-body {
                padding: 82px 14px 14px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .dentist-bg-pattern {
                animation: none;
            }
        }
    </style>
</head>

<body class="<?= htmlspecialchars($bodyClasses, ENT_QUOTES, 'UTF-8') ?>">
    <div class="dentist-bg" aria-hidden="true">
        <div class="dentist-bg-pattern"></div>
    </div>

    <div class="dentist-shell">
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="dentist-main">
            <?php require __DIR__ . '/../partials/topbar.php'; ?>

            <div class="dentist-body">
                <?= $mainContent ?>
            </div>
        </main>
    </div>

    <script
        src="<?= $baseUrl ?>/assets/js/user-settings.js?v=<?= (int) $userSettingsJsVersion ?>"
        defer
    ></script>

    <script
        src="<?= $baseUrl ?>/assets/js/dentist-notification.js?v=<?= (int) $notificationJsVersion ?>"
        defer
    ></script>
</body>
</html>