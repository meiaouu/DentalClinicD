<?php

use App\Core\Auth;

$baseUrl = '/DentalClinic/public';

$clinic = $clinic ?? null;
$isLoggedIn = Auth::check();
$user = Auth::user();

$settings = isset($settings) && is_array($settings) ? $settings : [];

/*
|--------------------------------------------------------------------------
| Load settings if this partial is used on a page that did not pass settings
|--------------------------------------------------------------------------
*/
if (empty($settings) && class_exists(\App\Repositories\SystemSettingsRepository::class)) {
    try {
        $settingsRepository = new \App\Repositories\SystemSettingsRepository();
        $settings = $settingsRepository->getAllGrouped();
    } catch (Throwable $e) {
        $settings = [];
    }
}

$clinicProfile = isset($clinicProfile) && is_array($clinicProfile)
    ? $clinicProfile
    : (($settings['clinic_profile'] ?? []) ?: []);

if (!function_exists('navbar_e')) {
    function navbar_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('navbar_setting_value')) {
    function navbar_setting_value(array $group, string $key, string $default = ''): string
    {
        $value = $group[$key] ?? null;

        if (is_array($value)) {
            $value = $value['setting_value']
                ?? $value['value']
                ?? $value['setting_text']
                ?? null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('navbar_asset_url')) {
    function navbar_asset_url(string $path, string $fallback = ''): string
    {
        $baseUrl = '/DentalClinic/public';

        $path = trim($path);

        if ($path === '') {
            $path = $fallback;
        }

        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, '/DentalClinic/public/')) {
            return $path;
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }
}

$clinicObjectName = is_array($clinic)
    ? (string) ($clinic['clinic_name'] ?? '')
    : (string) ($clinic->clinic_name ?? '');

$clinicObjectPhone = is_array($clinic)
    ? (string) ($clinic['contact_number'] ?? '')
    : (string) ($clinic->contact_number ?? '');

$clinicObjectEmail = is_array($clinic)
    ? (string) ($clinic['clinic_email'] ?? '')
    : (string) ($clinic->clinic_email ?? '');

$clinicObjectAddress = is_array($clinic)
    ? (string) ($clinic['clinic_location'] ?? '')
    : (string) ($clinic->clinic_location ?? '');

$navbarClinicName = navbar_setting_value(
    $clinicProfile,
    'clinic_name',
    trim($clinicObjectName) !== '' ? $clinicObjectName : 'Dr. Brendalyn Wansi Calacat Dental Clinic'
);

$navbarClinicPhone = navbar_setting_value(
    $clinicProfile,
    'phone_number',
    trim($clinicObjectPhone) !== '' ? $clinicObjectPhone : '+63 900 123 4567'
);

$navbarClinicEmail = navbar_setting_value(
    $clinicProfile,
    'email',
    trim($clinicObjectEmail) !== '' ? $clinicObjectEmail : 'clinic@email.com'
);

$navbarClinicAddress = navbar_setting_value(
    $clinicProfile,
    'address',
    trim($clinicObjectAddress) !== '' ? $clinicObjectAddress : 'Clinic Address'
);

$navbarFacebookUrl = navbar_setting_value($clinicProfile, 'facebook_url', '#');

$logoUrl = navbar_asset_url(
    navbar_setting_value($clinicProfile, 'clinic_logo'),
    '/DentalClinic/public/images/clinic-logo.svg'
);

$dashboardUrl = '/DentalClinic/public/';

if ($isLoggedIn && $user) {
    $dashboardUrl = \App\Core\Auth::isAdminDentistAccount()
        ? '/DentalClinic/public/admin/dashboard'
        : match ($user['role_name'] ?? '') {
        'admin' => '/DentalClinic/public/admin/dashboard',
        'staff' => '/DentalClinic/public/staff/dashboard',
        'dentist' => '/DentalClinic/public/dentist/dashboard',
        'patient' => '/DentalClinic/public/patient/dashboard',
        default => '/DentalClinic/public/',
    };
}
?>
<style>

.clinic-topbar {
    position: fixed;
    top: 0;
    left: 0px;
    right: 0px;
    z-index: 10001;
    background: transparent;
}

.clinic-topbar-inner {
    position: relative;
    min-height: 38px;
    display: flex;
    align-items: stretch;
    overflow: hidden;
}

.clinic-topbar-left {
    flex: 1;
    background: #060b14;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 34px;
    padding: 0 22px;
    min-height: 38px;
}

.clinic-topbar-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #ffffff;
    font-size: 13px;
    font-weight: 500;
    line-height: 1;
    white-space: nowrap;
}

.clinic-topbar-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    color: #ffffff;
    opacity: 0.95;
    width: 15px;
}

.clinic-topbar-right {
    position: relative;
    min-width: 205px;
    background: #22b8ad;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    padding: 0 18px 0 36px;
}

.clinic-topbar-right::before {
    content: "";
    position: absolute;
    left: -28px;
    top: 0;
    width: 56px;
    height: 100%;
    background: #22b8ad;
    transform: skewX(-30deg);
}

.clinic-social {
    position: relative;
    z-index: 1;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    width: 25px;
    height: 25px;
}

.clinic-social:hover {
    opacity: 0.9;
}


.public-nav,
nav {
     top: 38px !important;
}

@media (max-width: 900px) {
    .clinic-topbar {
        left: 8px;
        right: 8px;
    }

    .clinic-topbar-left {
        gap: 16px;
        padding: 0 12px;
        justify-content: flex-start;
        overflow-x: auto;
    }

    .clinic-topbar-left::-webkit-scrollbar {
        display: none;
    }

    .clinic-topbar-right {
        min-width: 140px;
        padding-left: 28px;
        gap: 10px;
    }
}

@media (max-width: 640px) {
    .clinic-topbar {
        display: none;
    }

    .public-nav,
    nav {
        top: 0 !important;
    }
}



    .public-nav {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 10000;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    }

    .public-nav-shell {
        max-width: 1180px;
        margin: 0 auto;
        padding: 14px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
    }

    .public-nav-toggle {
        display: none;
        width: 42px;
        height: 42px;
        border: 1px solid rgba(15, 23, 42, 0.12);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.8);
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        transition: all 0.2s ease;
    }

    .public-nav-toggle:hover {
        background: rgba(20, 184, 166, 0.08);
        border-color: rgba(20, 184, 166, 0.3);
    }

    .public-nav-toggle-lines {
        width: 22px;
        height: 16px;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .public-nav-toggle-bar {
        display: block;
        width: 100%;
        height: 2px;
        border-radius: 999px;
        background: #0f172a;
        transition: transform 0.25s ease, opacity 0.25s ease;
    }

    .public-nav-toggle.is-open .public-nav-toggle-bar:nth-child(1) {
        transform: translateY(7px) rotate(45deg);
    }

    .public-nav-toggle.is-open .public-nav-toggle-bar:nth-child(2) {
        opacity: 0;
    }

    .public-nav-toggle.is-open .public-nav-toggle-bar:nth-child(3) {
        transform: translateY(-7px) rotate(-45deg);
    }

    .public-nav-brand {
        display: inline-flex;
        align-items: center;
        text-decoration: none;
        flex-shrink: 0;
    }

    .public-nav-logo {
        display: block;
        height: 58px;
        width: auto;
        max-width: 100%;
        object-fit: contain;
    }

    .public-nav-links {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        transition: opacity 0.22s ease, transform 0.22s ease;
    }

    .public-nav-link {
        color: #0f172a;
        text-decoration: none;
        font-size: 14px;
        font-weight: 700;
        padding: 8px 10px;
        border-radius: 8px;
        transition: 0.2s ease;
    }

    .public-nav-link:hover {
        background: rgba(15, 23, 42, 0.05);
    }

    .public-nav-btn,
    .public-nav-btn-outline {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 0 16px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 800;
        transition: 0.2s ease;
    }

    .public-nav-btn-outline {
        border: 1px solid rgba(15, 23, 42, 0.14);
        color: #0f172a;
        background: rgba(255, 255, 255, 0.58);
    }

    .public-nav-btn-outline:hover {
        background: rgba(15, 23, 42, 0.05);
    }

    .public-nav-btn {
        border: 1px solid #14b8a6;
        background: #14b8a6;
        color: #ffffff;
    }

    .public-nav-btn:hover {
        background: #0f9d8a;
        border-color: #0f9d8a;
    }

    .public-nav-logout-form {
        margin: 0;
    }

    .public-nav-logout-btn {
        min-height: 40px;
        padding: 0 16px;
        border-radius: 10px;
        border: 1px solid rgba(15, 23, 42, 0.14);
        color: #0f172a;
        background: rgba(255, 255, 255, 0.58);
        font-size: 14px;
        font-weight: 800;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .public-nav-logout-btn:hover {
        background: rgba(15, 23, 42, 0.05);
    }

    @media (max-width: 900px) {
        .public-nav-shell {
            justify-content: center;
        }

        .public-nav-links {
            justify-content: center;
        }

        .public-nav-logo {
            height: 50px;
        }
    }

    @media (max-width: 640px) {
        .public-nav-shell {
            padding: 12px 14px;
            gap: 12px;
        }

        .public-nav-toggle {
            display: inline-flex;
            margin-left: auto;
        }

        .public-nav-links {
            display: flex;
            width: 100%;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            padding-top: 6px;
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            pointer-events: none;
            transform: translateY(-8px);
        }

        .public-nav-links.is-open {
            max-height: 500px;
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }

        .public-nav-link {
            font-size: 13px;
            padding: 9px 10px;
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.02);
        }

        .public-nav-btn,
        .public-nav-btn-outline,
        .public-nav-logout-btn {
            min-height: 38px;
            padding: 0 14px;
            font-size: 13px;
            width: 100%;
            justify-content: center;
        }

        .public-nav-logo {
            height: 44px;
        }
    }
</style>

<div class="clinic-topbar">
    <div class="clinic-topbar-inner">
        <div class="clinic-topbar-left">
            <div class="clinic-topbar-item">
                <span class="clinic-topbar-icon">☎</span>
                <span><?= navbar_e($navbarClinicPhone) ?></span>
            </div>

            <div class="clinic-topbar-item">
                <span class="clinic-topbar-icon">✉</span>
                <span><?= navbar_e($navbarClinicEmail) ?></span>
            </div>

            <div class="clinic-topbar-item">
                <span class="clinic-topbar-icon">◉</span>
                <span><?= navbar_e($navbarClinicAddress) ?></span>
            </div>
        </div>

        <div class="clinic-topbar-right">
            <a href="<?= navbar_e($navbarFacebookUrl) ?>" class="clinic-social" aria-label="Facebook">
                <svg viewBox="0 0 24 24" width="25" height="25" fill="currentColor">
                    <path d="M22 12a10 10 0 10-11.5 9.9v-7h-2.2V12h2.2V9.8c0-2.2 1.3-3.4 3.3-3.4.96 0 1.96.17 1.96.17v2.16h-1.1c-1.08 0-1.42.67-1.42 1.36V12h2.42l-.39 2.9h-2.03v7A10 10 0 0022 12z"/>
                </svg>
            </a>

            <a href="#" class="clinic-social" aria-label="Instagram">
                <svg viewBox="0 0 24 24" width="25" height="25" fill="currentColor">
                    <path d="M7 2h10a5 5 0 015 5v10a5 5 0 01-5 5H7a5 5 0 01-5-5V7a5 5 0 015-5zm0 2a3 3 0 00-3 3v10a3 3 0 003 3h10a3 3 0 003-3V7a3 3 0 00-3-3H7zm5 3.8A5.2 5.2 0 1112 18.2 5.2 5.2 0 0112 7.8zm0 2A3.2 3.2 0 1015.2 13 3.2 3.2 0 0012 9.8zm5.4-3.3a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z"/>
                </svg>
            </a>

            <a href="#" class="clinic-social" aria-label="Messenger">
                <svg viewBox="0 0 24 24" width="25" height="25" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.15 2 11.27c0 2.92 1.46 5.53 3.75 7.24V22l3.15-1.73c.99.27 2.03.41 3.1.41 5.52 0 10-4.15 10-9.27S17.52 2 12 2zm1 12.55l-2.54-2.71-4.96 2.71 5.46-5.8 2.59 2.71 4.9-2.71L13 14.55z"/>
                </svg>
            </a>
        </div>
    </div>
</div>


<nav class="public-nav">
    <div class="public-nav-shell">
        <a href="/DentalClinic/public/" class="public-nav-brand" aria-label="<?= navbar_e($navbarClinicName) ?>">
            <img
                src="<?= navbar_e($logoUrl) ?>"
                alt="<?= navbar_e($navbarClinicName) ?>"
                class="public-nav-logo"
            >
        </a>

        <button
            type="button"
            class="public-nav-toggle"
            aria-label="Toggle navigation"
            aria-expanded="false"
        >
            <span class="public-nav-toggle-lines" aria-hidden="true">
                <span class="public-nav-toggle-bar"></span>
                <span class="public-nav-toggle-bar"></span>
                <span class="public-nav-toggle-bar"></span>
            </span>
        </button>

        <div class="public-nav-links">
            <a href="/DentalClinic/public/#home" class="public-nav-link">Home</a>
            <a href="/DentalClinic/public/#services" class="public-nav-link">Services</a>
            <a href="/DentalClinic/public/#gallery" class="public-nav-link">Gallery</a>
            <a href="/DentalClinic/public/#about" class="public-nav-link">About</a>
            <a href="/DentalClinic/public/#contact" class="public-nav-link">Contact</a>

            <?php if (!$isLoggedIn): ?>
                <a href="/DentalClinic/public/login" class="public-nav-btn-outline">Login</a>
                <a href="/DentalClinic/public/register" class="public-nav-btn">Register</a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="public-nav-btn">Dashboard</a>

                <form method="POST" action="/DentalClinic/public/logout" class="public-nav-logout-form">
                    <?= \App\Core\Csrf::inputField(); ?>
                    <button type="submit" class="public-nav-logout-btn">Logout</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('.public-nav-toggle');
    const navLinks = document.querySelector('.public-nav-links');

    if (!toggle || !navLinks) {
        return;
    }

    toggle.addEventListener('click', function () {
        const isOpen = navLinks.classList.toggle('is-open');
        toggle.classList.toggle('is-open', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
    });

    navLinks.querySelectorAll('a, button').forEach(function (element) {
        element.addEventListener('click', function () {
            if (window.innerWidth <= 640) {
                navLinks.classList.remove('is-open');
                toggle.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 640) {
            navLinks.classList.remove('is-open');
            toggle.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
});
</script>