<?php
$pageTitle = $pageTitle ?? 'Staff Panel';
$authUser = $authUser ?? (\App\Core\Auth::user() ?? null);

$mainContent = '';

foreach ([
    $staffContent ?? null,
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">

    <style>
        :root {
            --font-ui: 'DM Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --font-mono: 'DM Mono', Consolas, "Liberation Mono", monospace;

            --navy: #0b1f3a;
            --navy-light: #1e3f6e;
            --mint: #2ec4a5;
            --mint-dark: #1fa88c;
            --mint-soft: #e6f9f5;
            --mint-border: #b2ede3;
            --cream: #ffffff;
            --warm-white: #ffffff;
            --stone-100: #f2f0ec;
            --stone-200: #e4e1da;
            --stone-400: #b0aa9e;
            --stone-600: #6e6860;
            --stone-800: #3a3630;
            --red: #e53e3e;

            --staff-sidebar-w: 248px;
            --r-sm: 5px;
            --r-md: 8px;
            --r-lg: 12px;
            --r-xl: 2px;
            --sh-card: 0 6px 26px rgba(11, 31, 58, .08), 0 1px 3px rgba(11, 31, 58, .05);
            --sh-sm: 0 2px 8px rgba(11, 31, 58, .07);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            font-family: var(--font-ui);
            background: var(--cream);
            color: #0f172a;
            overflow-x: hidden;
        }

        body {
            min-height: 100vh;
            font-size: 14px;
            line-height: 1.6;
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

        html,
        body,
        .app-shell,
        .staff-shell,
        .staff-layout,
        .staff-main,
        .staff-content,
        .staff-body,
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
            text-decoration: none;
        }

        .staff-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
            background:
                radial-gradient(ellipse 60% 50% at 10% 5%, rgba(46, 196, 165, .08) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 90% 90%, rgba(11, 31, 58, .07) 0%, transparent 55%),
                var(--cream);
        }

        .staff-bg-pattern {
            position: absolute;
            inset: -40px;
            opacity: .055;
            background-image:
                linear-gradient(rgba(11, 31, 58, .32) 1px, transparent 1px),
                linear-gradient(90deg, rgba(11, 31, 58, .32) 1px, transparent 1px);
            background-size: 32px 32px;
            animation: staffGridMove 28s linear infinite;
        }

        .staff-bg::before {
            content: "";
            position: absolute;
            width: 420px;
            height: 420px;
            top: -170px;
            left: 18%;
            border-radius: 999px;
            background: rgba(46, 196, 165, 0.09);
            filter: blur(16px);
        }

        .staff-bg::after {
            content: "";
            position: absolute;
            width: 500px;
            height: 500px;
            right: -220px;
            bottom: -180px;
            border-radius: 999px;
            background: rgba(11, 31, 58, 0.08);
            filter: blur(18px);
        }

        @keyframes staffGridMove {
            from {
                transform: translate(0, 0);
            }

            to {
                transform: translate(32px, 32px);
            }
        }

        .staff-shell {
            position: relative;
            z-index: 1;
            min-height: 100dvh;
            background: transparent;
        }

        .staff-main {
            position: relative;
            z-index: 1;
            min-width: 0;
            min-height: 100dvh;
            margin-left: var(--staff-sidebar-w);
            display: flex;
            flex-direction: column;
            background: transparent;
        }

        .staff-topbar {
            position: sticky;
            top: 0;
            z-index: 900;
            flex-shrink: 0;
            background: rgba(255, 255, 255, 0.92);
            border-bottom: 1px solid rgba(228, 225, 218, 0.85);
            padding: 14px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 18px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .staff-topbar-title {
            margin: 0;
            font-size: 20px;
            font-weight: 900;
            color: #111827;
            letter-spacing: -0.03em;
        }

        .staff-topbar-copy {
            margin: 4px 0 0;
            color: #667085;
            font-size: 13px;
        }

        .staff-topbar-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .staff-user-pill {
            padding: 9px 12px;
            background: rgba(243, 244, 246, 0.9);
            color: #111827;
            font-size: 12px;
            font-weight: 800;
            border: 1px solid rgba(229, 231, 235, 0.8);
        }

        .staff-logout-btn {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #374151;
            padding: 9px 12px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }

        .staff-logout-btn:hover {
            background: #111827;
            color: #ffffff;
            border-color: #111827;
        }

        .staff-body {
            position: relative;
            z-index: 1;
            min-width: 0;
            flex: 1;
            padding: 18px;
            background: transparent;
        }

        .staff-dashboard-page,
        .staff-billing-page,
        .patient-create-page,
        .staff-patient-record-page,
        .staff-patient-shell,
        .staff-patient-paper,
        .billing-page,
        .dashboard-page {
            background: transparent !important;
        }

        .staff-sidebar {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: var(--staff-sidebar-w) !important;
            min-width: var(--staff-sidebar-w) !important;
            max-width: var(--staff-sidebar-w) !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            z-index: 1000;
        }

        .staff-sidebar::-webkit-scrollbar {
            width: 7px;
        }

        .staff-sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .staff-sidebar::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.35);
            border-radius: 999px;
        }

        .staff-sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.65);
        }

        .dataTables_wrapper,
        .dataTables_wrapper * {
            font-family: var(--font-ui) !important;
        }

        .dataTables_wrapper .dt-button {
            font-family: var(--font-ui) !important;
        }

        @media (max-width: 900px) {
            .staff-main {
                margin-left: 0;
            }

            .staff-sidebar {
                position: relative !important;
                width: 100% !important;
                min-width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
            }

            .staff-body {
                padding: 14px;
            }

            .staff-topbar {
                padding: 12px 14px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .staff-bg-pattern {
                animation: none;
            }
        }
    </style>
</head>

<body>
    <div class="staff-bg" aria-hidden="true">
        <div class="staff-bg-pattern"></div>
    </div>

    <div class="staff-shell">
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="staff-main">
            <?php require __DIR__ . '/../partials/topbar.php'; ?>

            <div class="staff-body">
                <?= $mainContent ?>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
</body>
</html>
