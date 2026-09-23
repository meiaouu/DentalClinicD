<?php
$title = $title ?? 'Dental Clinic';
$content = $content ?? '';
$success = \App\Core\Session::get('success');
if ($success) {
    \App\Core\Session::remove('success');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        a { color: inherit; }
        .container {
            max-width: 1180px;
            margin: 0 auto;
            padding: 24px;
        }
        .page-shell {
            min-height: 100vh;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05);
        }
        .auth-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            background: linear-gradient(135deg, #e6fffb 0%, #f8fafc 45%, #ecfeff 100%);
        }
        .auth-hero {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px;
            background:
                linear-gradient(rgba(15,23,42,0.55), rgba(15,23,42,0.65)),
                url('/DentalClinic/public/images/dentalimg.jpg') center/cover no-repeat;
            color: #ffffff;
        }
        .auth-hero-inner {
            max-width: 520px;
        }
        .auth-badge {
            display: inline-block;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.2);
            color: #ccfbf1;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 18px;
        }
        .auth-title {
            font-size: clamp(32px, 5vw, 54px);
            line-height: 1.08;
            margin: 0 0 16px;
            font-weight: 800;
        }
        .auth-copy {
            font-size: 16px;
            line-height: 1.8;
            color: #e5e7eb;
            margin: 0;
        }
        .auth-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 36px 24px;
        }
        .auth-card {
            width: 100%;
            max-width: 520px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            padding: 30px;
        }
        .auth-card h1 {
            margin: 0 0 10px;
            font-size: 30px;
            font-weight: 800;
            color: #0f172a;
        }
        .auth-subtitle {
            margin: 0 0 24px;
            color: #64748b;
            line-height: 1.7;
            font-size: 14px;
        }
        .form-grid {
            display: grid;
            gap: 16px;
        }
        .form-grid.two {
            grid-template-columns: 1fr 1fr;
        }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-weight: 700;
            margin-bottom: 8px;
            color: #0f172a;
            font-size: 14px;
        }
        input, select, textarea, button {
            width: 100%;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            padding: 12px 14px;
            font-size: 14px;
            outline: none;
            transition: 0.2s ease;
            background: #ffffff;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #14b8a6;
            box-shadow: 0 0 0 4px rgba(20,184,166,0.12);
        }
        textarea {
            resize: vertical;
            min-height: 90px;
        }
        button {
            border: none;
            background: #14b8a6;
            color: #ffffff;
            font-weight: 800;
            cursor: pointer;
        }
        button:hover {
            background: #0f9d8a;
        }
        .muted-link {
            color: #0f9d8a;
            font-weight: 700;
            text-decoration: none;
        }
        .error-box, .success-box {
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 14px;
            line-height: 1.6;
        }
        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .success-box {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        .form-footer {
            margin-top: 18px;
            color: #64748b;
            font-size: 14px;
            text-align: center;
        }
        @media (max-width: 900px) {
            .auth-page {
                grid-template-columns: 1fr;
            }
            .auth-hero {
                min-height: 280px;
                padding: 32px 24px;
            }
        }
        @media (max-width: 640px) {
            .form-grid.two {
                grid-template-columns: 1fr;
            }
            .auth-card {
                padding: 22px;
                border-radius: 18px;
            }
        }
    </style>
</head>
<body>
    <?php if ($success): ?>
        <div class="container" style="padding-bottom:0;">
            <div class="success-box"><?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endif; ?>

    <div class="page-shell">
        <?= $content ?>
    </div>
</body>
</html>