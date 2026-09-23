<?php
$pageTitle = $pageTitle ?? 'Owner/Admin Panel';
$content = $content ?? '';
$baseUrl = '/DentalClinic/public';

$systemTheme = 'light';
$systemAccent = '#0f766e';
$systemLogo = '';
$systemFavicon = '';

try {
    $systemTheme = strtolower(trim(\App\Services\SystemSettingService::get('appearance', 'default_theme_mode', 'light')));
    $systemAccent = trim(\App\Services\SystemSettingService::get('appearance', 'default_accent_color', '#0f766e'));
    $systemLogo = trim(\App\Services\SystemSettingService::get('appearance', 'system_logo', ''));
    $systemFavicon = trim(\App\Services\SystemSettingService::get('appearance', 'favicon', ''));
} catch (\Throwable $e) {
    $systemTheme = 'light';
}

if (!in_array($systemTheme, ['light', 'dark'], true)) {
    $systemTheme = 'light';
}

if (!preg_match('/^#[0-9a-fA-F]{6}$/', $systemAccent)) {
    $systemAccent = '#0f766e';
}

$systemAssetUrl = static function (string $path) use ($baseUrl): string {
    if ($path === '') {
        return '';
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
        return $path;
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
};

$systemLogoUrl = $systemAssetUrl($systemLogo);
$systemFaviconUrl = $systemAssetUrl($systemFavicon);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <?php if ($systemFaviconUrl !== ''): ?>
        <link rel="icon" href="<?= htmlspecialchars($systemFaviconUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl . '/assets/css/admin.css', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl . '/assets/css/admin-settings.css', ENT_QUOTES, 'UTF-8') ?>">

    <style>
        :root {
            --admin-sidebar-width: 248px;
            --admin-topbar-height: 64px;
            --admin-page-bg: #f7f8fa;
            --admin-border: #e5e7eb;
            --admin-accent: <?= htmlspecialchars($systemAccent, ENT_QUOTES, 'UTF-8') ?>;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            width: 100%;
            overflow-x: hidden;
            background: var(--admin-page-bg);
        }

        body {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #111827;
        }

        body.theme-dark {
            --admin-page-bg: #111827;
            --admin-border: #374151;
            background: #111827;
            color: #f3f4f6;
        }

        body.theme-dark .admin-topbar,
        body.theme-dark .admin-content,
        body.theme-dark .admin-main,
        body.theme-dark .admin-shell {
            background: #111827;
            color: #f3f4f6;
        }

        body.theme-dark .admin-topbar {
            border-color: #374151;
        }

        body.theme-dark .admin-topbar-title,
        body.theme-dark h1,
        body.theme-dark h2,
        body.theme-dark h3,
        body.theme-dark label,
        body.theme-dark .settings-title,
        body.theme-dark .settings-heading,
        body.theme-dark .settings-card-title,
        body.theme-dark .settings-row-title {
            color: #f9fafb !important;
        }

        body.theme-dark .settings-panel,
        body.theme-dark .settings-card,
        body.theme-dark .settings-most-card,
        body.theme-dark input,
        body.theme-dark select,
        body.theme-dark textarea {
            background: #1f2937 !important;
            border-color: #374151 !important;
            color: #f9fafb !important;
        }

        body.theme-dark .settings-page,
        body.theme-dark .admin-settings-page {
            background: #111827 !important;
            color: #f3f4f6 !important;
        }

        body.theme-dark .settings-row-description,
        body.theme-dark .settings-card-subtitle,
        body.theme-dark .settings-subheading,
        body.theme-dark .settings-muted {
            color: #9ca3af !important;
        }

        .admin-accent,
        .settings-row:hover .settings-row-title {
            color: var(--admin-accent) !important;
        }

        .admin-accent-bg {
            background-color: var(--admin-accent) !important;
        }

        .admin-shell {
            width: 100%;
            min-height: 100vh;
            background: var(--admin-page-bg);
        }

        .admin-main {
            min-height: 100vh;
            margin-left: var(--admin-sidebar-width);
            padding-top: var(--admin-topbar-height);
            width: calc(100% - var(--admin-sidebar-width));
            background: var(--admin-page-bg);
            transition: margin-left 0.22s ease, width 0.22s ease;
        }

        .admin-content {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            min-height: calc(100vh - var(--admin-topbar-height));
            padding: 0;
            background: var(--admin-page-bg);
            overflow-x: hidden;
        }

        .admin-content > * {
            max-width: 100%;
        }

        body .admin-topbar {
            left: var(--admin-sidebar-width);
            height: var(--admin-topbar-height);
        }

        body .admin-sidebar {
            width: var(--admin-sidebar-width);
            min-width: var(--admin-sidebar-width);
            max-width: var(--admin-sidebar-width);
        }

        img,
        svg,
        video,
        canvas {
            max-width: 100%;
        }

        table {
            max-width: 100%;
        }

        @media (max-width: 900px) {
            .admin-main {
                margin-left: 0;
                width: 100%;
                padding-top: var(--admin-topbar-height);
            }

            body .admin-topbar {
                left: 0;
                right: 0;
            }

            .admin-content {
                min-height: calc(100vh - var(--admin-topbar-height));
            }
        }

        @media (max-width: 520px) {
            .admin-content {
                width: 100%;
                overflow-x: hidden;
            }
        }
    </style>
</head>

<body class="theme-<?= htmlspecialchars($systemTheme, ENT_QUOTES, 'UTF-8') ?>" style="--admin-accent: <?= htmlspecialchars($systemAccent, ENT_QUOTES, 'UTF-8') ?>;">
    <div class="admin-shell">
        <?php require __DIR__ . '/../partials/sidebar.php'; ?>

        <main class="admin-main">
            <?php require __DIR__ . '/../partials/topbar.php'; ?>

            <section class="admin-content">
                <?= $content ?>
            </section>
        </main>
    </div>

    <script src="<?= htmlspecialchars($baseUrl . '/assets/js/admin.js', ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>