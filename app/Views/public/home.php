<?php

use App\Core\Csrf;

$baseUrl = '/DentalClinic/public';

$services = isset($services) && is_array($services) ? $services : [];
$settings = isset($settings) && is_array($settings) ? $settings : [];

$clinicProfile = isset($clinicProfile) && is_array($clinicProfile)
    ? $clinicProfile
    : ($settings['clinic_profile'] ?? []);

$publicWebsite = isset($publicWebsite) && is_array($publicWebsite)
    ? $publicWebsite
    : ($settings['public_website'] ?? []);

$appointmentRules = isset($appointmentRules) && is_array($appointmentRules)
    ? $appointmentRules
    : ($settings['appointment_rules'] ?? []);

$messagingSettings = isset($messagingSettings) && is_array($messagingSettings)
    ? $messagingSettings
    : ($settings['messaging'] ?? []);

$clinicHours = isset($clinicHours) && is_array($clinicHours) ? $clinicHours : [];

if (!function_exists('home_e')) {
    function home_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('home_setting_value')) {
    function home_setting_value(array $group, string $key, string $default = ''): string
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

if (!function_exists('home_setting_enabled')) {
    function home_setting_enabled(array $group, string $key, bool $default = true): bool
    {
        $value = home_setting_value($group, $key, $default ? '1' : '0');

        return in_array(strtolower($value), ['1', 'yes', 'true', 'on'], true);
    }
}

if (!function_exists('home_asset_url')) {
    function home_asset_url(string $path, string $fallback = ''): string
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

if (!function_exists('home_format_time')) {
    function home_format_time(?string $time): string
    {
        $time = trim((string) $time);

        if ($time === '') {
            return '';
        }

        $timestamp = strtotime($time);

        return $timestamp ? date('h:i A', $timestamp) : $time;
    }
}

if (!function_exists('home_clinic_hours_text')) {
    function home_clinic_hours_text(array $clinicHours): string
    {
        foreach ($clinicHours as $row) {
            if ((int) ($row['is_open'] ?? 0) !== 1) {
                continue;
            }

            $open = home_format_time((string) ($row['opening_time'] ?? ''));
            $close = home_format_time((string) ($row['closing_time'] ?? ''));

            if ($open !== '' && $close !== '') {
                return $open . ' – ' . $close;
            }
        }

        return '08:00 AM – 06:00 PM';
    }
}

/*
|--------------------------------------------------------------------------
| Clinic Profile Settings
|--------------------------------------------------------------------------
*/

$clinicName = home_setting_value(
    $clinicProfile,
    'clinic_name',
    'Dr. Brendalyn Wansi Calacat Dental Clinic'
);

$clinicTagline = home_setting_value(
    $clinicProfile,
    'tagline',
    'Specialized dentistry with personalized care.'
);

$clinicPhone = home_setting_value(
    $clinicProfile,
    'phone_number',
    '+63 900 123 4567'
);

$clinicEmail = home_setting_value(
    $clinicProfile,
    'email',
    'clinic@email.com'
);

$clinicAddress = home_setting_value(
    $clinicProfile,
    'address',
    'Purok 5, Barangay Mapalad, Sta. Rosa, Nueva Ecija'
);

$clinicFacebookUrl = home_setting_value(
    $clinicProfile,
    'facebook_url',
    '#'
);

$clinicGoogleMapsUrl = home_setting_value(
    $clinicProfile,
    'google_maps_url',
    '#'
);

$clinicHoursText = home_clinic_hours_text($clinicHours);

/*
|--------------------------------------------------------------------------
| Public Website Settings
|--------------------------------------------------------------------------
*/

$heroTitle = home_setting_value(
    $publicWebsite,
    'homepage_hero_title',
    'Your Smile, Our Priority'
);

$heroSubtitle = home_setting_value(
    $publicWebsite,
    'homepage_subtitle',
    $clinicTagline
);

$aboutSection = home_setting_value(
    $publicWebsite,
    'about_section',
    'Our system is designed to reduce confusion and make every step from booking to visit smooth and well-organized.'
);

$featuredServicesText = home_setting_value(
    $publicWebsite,
    'featured_services',
    'Explore the treatments offered at the clinic before making your appointment.'
);

$contactSection = home_setting_value(
    $publicWebsite,
    'contact_section',
    'Have questions before booking? Find everything you need to get in touch below.'
);

$homepageCta = home_setting_value(
    $publicWebsite,
    'homepage_cta',
    'Book Appointment'
);

$heroMainImage = home_asset_url(
    home_setting_value($publicWebsite, 'hero_image'),
    '/DentalClinic/public/images/dentalimg.jpg'
);

$galleryVisible = home_setting_enabled(
    $publicWebsite,
    'gallery_visibility',
    true
);

/*
|--------------------------------------------------------------------------
| Feature Toggles
|--------------------------------------------------------------------------
*/

$onlineBookingEnabled = home_setting_enabled(
    $appointmentRules,
    'enable_online_booking',
    true
);

$guestBookingEnabled = home_setting_enabled(
    $appointmentRules,
    'enable_guest_booking',
    true
);

$chatbotEnabled = home_setting_enabled(
    $messagingSettings,
    'enable_chatbot',
    true
);

$guestChatbotEnabled = home_setting_enabled(
    $messagingSettings,
    'enable_guest_chatbot',
    true
);

$chatbotWelcomeMessage = home_setting_value(
    $messagingSettings,
    'chatbot_welcome_message',
    'Ask about services, clinic hours, prices, or appointments.'
);

$galleryImages = [
    '/DentalClinic/public/images/gallery1.jpg',
    '/DentalClinic/public/images/dentalimg2.jpg',
    '/DentalClinic/public/images/dentalimg3.jpg',
    '/DentalClinic/public/images/gallery.jpg',
    '/DentalClinic/public/images/gallery3.jpg',
    '/DentalClinic/public/images/gallery4.jpg',
    '/DentalClinic/public/images/gallery5.jpg',
    '/DentalClinic/public/images/gallery6.jpg',
];

ob_start();
?>
 <?php
$services = isset($services) && is_array($services) ? $services : [];
$baseUrl = '/DentalClinic/public';

if (!function_exists('home_e')) {
    function home_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,800;0,900;1,700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
 
<style>
/* ── Tokens ──────────────────────────────────────────────────────────────── */
:root{
  --navy:#0b1628;
  --navy-mid:#1a2a44;
  --teal:#0d9e8c;
  --teal-dark:#0a7d6e;
  --teal-light:#e6f7f5;
  --teal-soft:#ccf0eb;
  --gold:#c9a84c;
  --white:#ffffff;
  --off:#f7f9fc;
  --slate:#64748b;
  --line:#e1e8f0;
  --text:#0d1b2a;
  --font-display:'Playfair Display',Georgia,serif;
  --font-body:'DM Sans',system-ui,sans-serif;
  --r:14px;
  --r-lg:22px;
  --shadow-card:0 2px 8px rgba(11,22,40,.06),0 12px 32px rgba(11,22,40,.08);
  --shadow-float:0 24px 80px rgba(11,22,40,.22);
  --ease:cubic-bezier(.4,0,.2,1);
}
 
html{scroll-behavior:smooth;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
 
body{
  font-family:var(--font-body);
  color:var(--text);
  background:#fff;
  -webkit-font-smoothing:antialiased;
}
 
body.modal-open{overflow:hidden;}
 
#homeContentWrap{
  transition:filter .24s var(--ease),opacity .24s var(--ease);
}
body.modal-open #homeContentWrap{
  filter:blur(6px);
  opacity:.92;
  pointer-events:none;
  user-select:none;
}
 
img{display:block;max-width:100%;}
a{text-decoration:none;color:inherit;}
 
/* ── Layout utils ─────────────────────────────────────────────────────────── */
.wrap{max-width:1160px;margin:0 auto;padding:0 24px;}
 
.section-eyebrow{
  display:inline-flex;align-items:center;gap:8px;
  font-family:var(--font-body);
  font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  color:var(--teal);
  margin-bottom:14px;
}
.section-eyebrow::before{
  content:'';display:block;width:28px;height:2px;background:var(--teal);border-radius:2px;
}
 
.section-title{
  font-family:var(--font-display);
  font-size:clamp(32px,4.5vw,52px);
  font-weight:800;
  line-height:1.1;
  letter-spacing:-.02em;
  color:var(--navy);
}
.section-title em{font-style:italic;color:var(--teal);}
 
.section-lead{
  margin-top:14px;
  font-size:16px;line-height:1.8;
  color:var(--slate);
  max-width:620px;
  
}
 
/* ── Reveal animations ────────────────────────────────────────────────────── */
.reveal{
  opacity:0;
  transform:translateY(28px);
  transition:opacity .7s var(--ease),transform .7s var(--ease);
}
.reveal.visible{opacity:1;transform:none;}
.reveal-delay-1{transition-delay:.1s;}
.reveal-delay-2{transition-delay:.2s;}
.reveal-delay-3{transition-delay:.3s;}
.reveal-delay-4{transition-delay:.4s;}
 
/* ── Buttons ─────────────────────────────────────────────────────────────── */
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:8px;
  min-height:50px;padding:0 26px;border-radius:var(--r);
  font-family:var(--font-body);font-size:14px;font-weight:700;
  border:2px solid transparent;cursor:pointer;
  transition:background .2s var(--ease),color .2s var(--ease),
             border-color .2s var(--ease),transform .18s var(--ease),
             box-shadow .2s var(--ease);
  white-space:nowrap;
}
.btn:active{transform:scale(.97);}
 
.btn-primary{
  background:var(--teal);color:#fff;border-color:var(--teal);
}
.btn-primary:hover{
  background:var(--teal-dark);border-color:var(--teal-dark);
  box-shadow:0 8px 28px rgba(13,158,140,.3);
}
 
.btn-outline-white{
  background:transparent;color:#fff;border-color:rgba(255,255,255,.55);
}
.btn-outline-white:hover{background:rgba(255,255,255,.1);border-color:#fff;}
 
.btn-outline-navy{
  background:transparent;color:var(--navy);border-color:var(--navy);
}
.btn-outline-navy:hover{background:var(--navy);color:#fff;}
 
.btn-ghost{
  background:transparent;color:var(--teal);border-color:var(--teal-soft);
  padding:0 20px;min-height:44px;
}
.btn-ghost:hover{background:var(--teal-light);}
 
.btn-sm{min-height:42px;padding:0 20px;font-size:13px;}
 
/* ── HERO ─────────────────────────────────────────────────────────────────── */
.hero{
  position:relative;
  min-height:100vh;
  overflow:hidden;
  display:flex;align-items:center;
  padding:140px 24px 80px;
  color:#fff;
  background:var(--navy);
}

 
.hero-bg{
  position:absolute;inset:0;z-index:0;
}
.hero-bg img{
  width:100%;height:100%;object-fit:cover;object-position:center;
  filter:brightness(.32) saturate(1.1);
}
.hero-bg::after{
  content:'';
  position:absolute;inset:0;
  background:linear-gradient(
    110deg,
    rgba(11,22,40,.92) 0%,
    rgba(11,22,40,.7) 45%,
    rgba(11,22,40,.35) 100%
  );
}
 
/* Diagonal accent line */
.hero-bg::before{
  content:'';
  position:absolute;
  top:0;right:36%;
  width:1px;height:100%;
  background:linear-gradient(180deg,transparent 0%,rgba(13,158,140,.3) 50%,transparent 100%);
  z-index:1;
}
 
.hero-inner{
  position:relative;z-index:2;
  max-width:1160px;margin:0 auto;
  display:grid;
  grid-template-columns:1fr 420px;
  gap:60px;align-items:center;
  width:100%;
}
 
.hero-badge{
  display:inline-flex;align-items:center;gap:8px;
  background:rgba(13,158,140,.18);
  border:1px solid rgba(13,158,140,.35);
  border-radius:999px;
  padding:6px 16px;
  font-size:11.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
  color:#7ef0e3;
  margin-bottom:22px;
}
.hero-badge-dot{
  width:6px;height:6px;border-radius:50%;
  background:#2dd4bf;
  box-shadow:0 0 6px #2dd4bf;
}
 
.hero-h1{
  font-family:var(--font-display);
  font-size:clamp(42px,6vw,78px);
  font-weight:900;
  line-height:1.04;
  letter-spacing:-.03em;
  margin-bottom:10px;
}
.hero-h1-sub{
  font-family:var(--font-display);
  font-size:clamp(18px,2.5vw,28px);
  font-weight:400;
  font-style:italic;
  color:rgba(255,255,255,.7);
  display:block;
  margin-bottom:20px;
  letter-spacing:-.01em;
}
.hero-h1 .teal-word{color:#2dd4bf;}
 
.hero-desc{
  font-size:16px;line-height:1.8;
  color:rgba(248,250,252,.82);
  max-width:560px;
  margin-bottom:32px;
}
 
.hero-actions{
  display:flex;gap:12px;flex-wrap:wrap;
  margin-bottom:40px;
}
 
.hero-stats{
  display:flex;gap:32px;
  padding-top:32px;
  border-top:1px solid rgba(255,255,255,.1);
}
.hero-stat-num{
  font-family:var(--font-display);
  font-size:32px;font-weight:800;
  color:#fff;
  line-height:1;
}
.hero-stat-label{
  font-size:12px;color:rgba(255,255,255,.55);
  margin-top:4px;font-weight:500;
}
 
/* Hero floating card */
.hero-card{
  background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.12);
  border-radius:var(--r-lg);
  backdrop-filter:blur(24px);
  padding:28px;
  display:grid;gap:14px;
}
 
.hero-card-label{
  font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
  color:rgba(255,255,255,.5);
}
 
.hero-card-item{
  display:flex;align-items:flex-start;gap:12px;
  padding:14px;
  background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.08);
  border-radius:12px;
}
.hero-card-item-icon{
  width:38px;height:38px;border-radius:10px;
  background:rgba(13,158,140,.25);
  border:1px solid rgba(13,158,140,.3);
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.hero-card-item-icon svg{width:18px;height:18px;fill:none;stroke:#2dd4bf;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;}
.hero-card-item-title{
  font-size:13.5px;font-weight:700;color:#fff;margin-bottom:2px;
}
.hero-card-item-text{font-size:12px;color:rgba(255,255,255,.55);line-height:1.5;}
 
.hero-card-cta{
  display:block;
  text-align:center;
  padding:14px;
  background:var(--teal);
  border-radius:10px;
  font-size:14px;font-weight:700;color:#fff;
  transition:background .2s;
}
.hero-card-cta:hover{background:var(--teal-dark);}
 
/* ── PROCESS SECTION ─────────────────────────────────────────────────────── */
.process-section{
  padding:100px 0;
  background:#fff;
  overflow:hidden;
}
 
.process-header{
  display:grid;
  grid-template-columns:1fr auto;
  align-items:flex-end;
  gap:24px;
  margin-bottom:56px;
}
 
.process-steps{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:0;
  position:relative;
}
.process-steps::before{
  content:'';
  position:absolute;
  top:28px;left:calc(12.5% + 14px);right:calc(12.5% + 14px);
  height:1px;
  background:linear-gradient(90deg,var(--teal-soft),var(--teal-soft));
  z-index:0;
}
 
.process-step{
  padding:0 16px;
  position:relative;
  z-index:1;
}
 
.process-step-num{
  width:56px;height:56px;border-radius:50%;
  background:#fff;
  border:2px solid var(--teal-soft);
  display:flex;align-items:center;justify-content:center;
  margin-bottom:20px;
  transition:background .2s,border-color .2s,box-shadow .2s;
}
.process-step-num span{
  font-family:var(--font-display);
  font-size:20px;font-weight:800;color:var(--teal);
}
.process-step:hover .process-step-num{
  background:var(--teal);
  border-color:var(--teal);
  box-shadow:0 8px 24px rgba(13,158,140,.3);
}
.process-step:hover .process-step-num span{color:#fff;}
 
.process-step-title{
  font-family:var(--font-display);
  font-size:19px;font-weight:700;color:var(--navy);
  margin-bottom:8px;
  line-height:1.25;
}
 
.process-step-text{
  font-size:13.5px;line-height:1.7;color:var(--slate);
}
 
/* ── SERVICES SECTION ────────────────────────────────────────────────────── */
.services-section{
  padding:100px 0;
  background:var(--off);
  position:relative;
  overflow:hidden;
}
 
/* Background decoration */
.services-section::before{
  content:'';
  position:absolute;
  top:-80px;right:-80px;
  width:400px;height:400px;
  border-radius:50%;
  background:radial-gradient(circle,rgba(13,158,140,.06) 0%,transparent 70%);
  pointer-events:none;
}
 
.services-header{
  display:grid;
  grid-template-columns:1fr auto;
  align-items:flex-end;
  gap:24px;
  margin-bottom:48px;
}
 
.services-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(272px,1fr));
  gap:22px;
}
 
.service-card{
  background:#fff;
  border:1px solid var(--line);
  border-radius:var(--r-lg);
  overflow:hidden;
  display:flex;flex-direction:column;
  transition:transform .28s var(--ease),box-shadow .28s var(--ease),border-color .28s var(--ease);
}
.service-card:hover{
  transform:translateY(-6px);
  box-shadow:var(--shadow-float);
  border-color:var(--teal-soft);
}
 
.service-card-img{
  height:200px;overflow:hidden;background:#e5eaf2;
  position:relative;
}
.service-card-img img{
  width:100%;height:100%;object-fit:cover;
  transition:transform .5s var(--ease);
}
.service-card:hover .service-card-img img{transform:scale(1.05);}
 
.service-card-img-overlay{
  position:absolute;inset:0;
  background:linear-gradient(180deg,transparent 50%,rgba(11,22,40,.2) 100%);
}
 
.service-card-body{
  padding:20px 22px 22px;
  display:flex;flex-direction:column;flex:1;gap:10px;
}
 
.service-card-title{
  font-family:var(--font-display);
  font-size:20px;font-weight:700;color:var(--navy);
  line-height:1.25;
}
 
.service-card-desc{
  font-size:13.5px;line-height:1.75;color:var(--slate);
  flex:1;
}
 
.service-card-footer{
  display:flex;align-items:center;justify-content:flex-end;
  padding-top:12px;
  border-top:1px solid var(--line);
  margin-top:4px;
}

.service-card-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 4px;
}

.service-card-meta span {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 5px 10px;
  background: var(--teal-light);
  color: var(--teal-dark);
  border: 1px solid var(--teal-soft);
  font-size: 12px;
  font-weight: 700;
}
 
/* ── WHY SECTION ─────────────────────────────────────────────────────────── */
.why-section{
  padding:100px 0;
  background:#fff;
}
 
.why-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:24px;
  align-items:start;
}
 
.why-left{
  padding-right:24px;
}
 
.why-features{
  display:grid;gap:14px;
  margin-top:32px;
}
.why-feature{
  display:flex;align-items:flex-start;gap:14px;
  padding:16px 18px;
  background:var(--off);
  border:1px solid var(--line);
  border-radius:var(--r);
  transition:border-color .2s,box-shadow .2s;
}
.why-feature:hover{
  border-color:var(--teal-soft);
  box-shadow:0 4px 20px rgba(13,158,140,.08);
}
.why-feature-icon{
  width:40px;height:40px;border-radius:10px;
  background:var(--teal-light);
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.why-feature-icon svg{width:18px;height:18px;fill:none;stroke:var(--teal);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.why-feature-title{
  font-size:14px;font-weight:700;color:var(--navy);
  margin-bottom:3px;
}
.why-feature-text{font-size:13px;line-height:1.6;color:var(--slate);}
 
/* Info cards (right side) */
.why-right{display:grid;gap:16px;}
 
.info-card{
  background:var(--off);
  border:1px solid var(--line);
  border-radius:var(--r-lg);
  padding:24px;
  transition:border-color .2s;
}
.info-card:hover{border-color:var(--teal-soft);}
 
.info-card.dark{
  background:var(--navy);
  border-color:var(--navy-mid);
}
 
.info-card-eyebrow{
  font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
  color:var(--teal);margin-bottom:10px;
}
.info-card.dark .info-card-eyebrow{color:#2dd4bf;}
 
.info-card-title{
  font-family:var(--font-display);
  font-size:22px;font-weight:700;color:var(--navy);
  line-height:1.25;margin-bottom:8px;
}
.info-card.dark .info-card-title{color:#fff;}
 
.info-card-text{font-size:13.5px;line-height:1.75;color:var(--slate);}
.info-card.dark .info-card-text{color:rgba(248,250,252,.65);}
 
.schedule-list{display:grid;gap:10px;margin-top:14px;}
.schedule-item{
  display:flex;align-items:flex-start;gap:10px;
  padding:12px 14px;
  background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.08);
  border-radius:10px;
}
.schedule-item-label{
  font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
  color:rgba(255,255,255,.4);margin-bottom:3px;
}
.schedule-item-value{font-size:13px;color:rgba(255,255,255,.85);font-weight:500;line-height:1.5;}
 
/* ── GALLERY SECTION ─────────────────────────────────────────────────────── */
.gallery-section{
  padding:100px 0;
  background:var(--navy);
  overflow:hidden;
}
 
.gallery-header{margin-bottom:48px;}
.gallery-header .section-title{color:#fff;}
.gallery-header .section-eyebrow{color:#2dd4bf;}
.gallery-header .section-eyebrow::before{background:#2dd4bf;}
.gallery-header .section-lead{color:rgba(255,255,255,.55);}
 
.gallery-mosaic{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  grid-template-rows:auto auto;
  gap:14px;
}
 
.gallery-item{
  border-radius:var(--r);
  overflow:hidden;
  background:#1a2a44;
  position:relative;
}
 
.gallery-item:nth-child(1){
  grid-column:1/3;
  grid-row:1;
  height:280px;
}
.gallery-item:nth-child(2){
  grid-column:3;
  grid-row:1;
  height:280px;
}
.gallery-item:nth-child(3){
  grid-column:4;
  grid-row:1;
  height:280px;
}
.gallery-item:nth-child(4){
  grid-column:1;
  grid-row:2;
  height:220px;
}
.gallery-item:nth-child(5){
  grid-column:2/4;
  grid-row:2;
  height:220px;
}
.gallery-item:nth-child(6){
  grid-column:4;
  grid-row:2;
  height:220px;
}
/* remaining items */
.gallery-item:nth-child(7),
.gallery-item:nth-child(8){
  display:none;
}
 
.gallery-item img{
  width:100%;height:100%;object-fit:cover;
  transition:transform .6s var(--ease),filter .4s var(--ease);
}
.gallery-item:hover img{
  transform:scale(1.07);
  filter:brightness(1.1);
}
 
.gallery-item::after{
  content:'';
  position:absolute;inset:0;
  background:linear-gradient(180deg,transparent 40%,rgba(11,22,40,.5) 100%);
  pointer-events:none;
  opacity:0;
  transition:opacity .3s;
}
.gallery-item:hover::after{opacity:1;}
 
/* ── CONTACT SECTION ─────────────────────────────────────────────────────── */
.contact-section{
  padding:100px 0;
  background:var(--off);
}
 
.contact-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:24px;
  margin-top:56px;
}
 
.contact-card{
  background:#fff;
  border:1px solid var(--line);
  border-radius:var(--r-lg);
  padding:32px;
  box-shadow:var(--shadow-card);
}
 
.contact-card-title{
  font-family:var(--font-display);
  font-size:24px;font-weight:700;color:var(--navy);
  margin-bottom:20px;
}
 
.contact-info-list{display:grid;gap:12px;}
.contact-info-row{
  display:flex;align-items:flex-start;gap:14px;
  padding:14px 16px;
  background:var(--off);
  border:1px solid var(--line);
  border-radius:var(--r);
}
.contact-info-icon{
  width:38px;height:38px;border-radius:10px;
  background:var(--teal-light);
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.contact-info-icon svg{width:17px;height:17px;fill:none;stroke:var(--teal);stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
.contact-info-label{
  font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
  color:var(--slate);margin-bottom:3px;
}
.contact-info-value{font-size:13.5px;color:var(--navy);font-weight:500;line-height:1.5;}
 
/* CTA card */
.cta-card{
  background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 100%);
  border:1px solid rgba(255,255,255,.06);
  border-radius:var(--r-lg);
  padding:32px;
  display:flex;flex-direction:column;justify-content:space-between;
  position:relative;overflow:hidden;
}
.cta-card::before{
  content:'';
  position:absolute;
  top:-40px;right:-40px;
  width:220px;height:220px;
  border-radius:50%;
  background:radial-gradient(circle,rgba(13,158,140,.18) 0%,transparent 70%);
  pointer-events:none;
}
.cta-card-eyebrow{
  font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  color:#2dd4bf;margin-bottom:14px;
}
.cta-card-title{
  font-family:var(--font-display);
  font-size:32px;font-weight:800;color:#fff;
  line-height:1.15;margin-bottom:14px;
}
.cta-card-text{font-size:14px;line-height:1.8;color:rgba(248,250,252,.6);margin-bottom:28px;}
.cta-card-actions{display:flex;flex-wrap:wrap;gap:10px;}
 
/* ── BOOKING MODAL ───────────────────────────────────────────────────────── */
.modal-overlay{
  position:fixed;inset:0;z-index:10000;
  display:none;align-items:center;justify-content:center;
  padding:20px;
  background:rgba(11,22,40,.55);
  backdrop-filter:blur(4px);
}
.modal-overlay.is-open{display:flex;}
 
.modal-overlay{
  position:fixed;
  inset:0;
  z-index:10000;
  display:none;
  align-items:center;
  justify-content:center;
  padding:32px;
  background:rgba(11,22,40,.55);
  backdrop-filter:blur(4px);
}

.modal-overlay.is-open{
  display:flex;
}


.phone-input-wrap {
  display: flex;
  align-items: center;
  width: 100%;
  height: 46px;
  border: 1.5px solid var(--line);
  border-radius: 10px;
  background: #ffffff;
  overflow: hidden;
  transition: border-color .2s, box-shadow .2s;
}

.phone-input-wrap:focus-within {
  border-color: var(--teal);
  box-shadow: 0 0 0 3px rgba(13,158,140,.14);
}

.phone-prefix {
  height: 100%;
  min-width: 58px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--teal-light);
  color: var(--teal-dark);
  border-right: 1px solid var(--teal-soft);
  font-size: 14px;
  font-weight: 800;
}

.phone-local-input {
  border: 0 !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  height: 100%;
  flex: 1;
}

.booking-modal-shell{
  position:relative;
  width:100%;
  max-width:500px;
}

.booking-modal{
  width:100%;
  max-width:500px;
  background:#fff;
  border-radius:2px;
  box-shadow:var(--shadow-float);
  overflow:hidden;
  animation:modalIn .22s var(--ease) both;
}
@keyframes modalIn{
  from{opacity:0;transform:translateY(18px) scale(.97);}
  to{opacity:1;transform:none;}
}
 
.booking-modal-top{
  display:none;
}

.booking-modal-top::before{
  content:'';position:absolute;
  bottom:-30px;right:-30px;
  width:120px;height:120px;border-radius:50%;
 
}
 
.booking-modal-title{
  font-family:var(--font-display);
  font-size:22px;font-weight:800;color:#fff;
  line-height:1.2;
}
.booking-modal-subtitle{
  font-size:13px;color:rgba(255,255,255,.6);
  margin-top:5px;
}
 
.booking-modal-top{
  display:none !important;
}

.booking-modal-close{
  position:absolute;
  top:-18px;
  right:-18px;
  z-index:10001;
  width:42px;
  height:42px;
  border-radius:999px;
  border:1px solid rgba(255,255,255,.35);
  background:rgba(11,22,40,.92);
  color:#ffffff;
  font-size:26px;
  line-height:1;
  cursor:pointer;
  display:flex;
  align-items:center;
  justify-content:center;
  box-shadow:0 14px 38px rgba(11,22,40,.32);
  transition:
    background .2s var(--ease),
    color .2s var(--ease),
    transform .18s var(--ease),
    box-shadow .2s var(--ease);
}

.booking-modal-close:hover{
  background:var(--teal);
  color:#ffffff;
  transform:translateY(-2px) scale(1.04);
  box-shadow:0 18px 46px rgba(13,158,140,.35);
}

.booking-form{
  padding:22px 26px 24px;
  display:grid;
  gap:16px;
}
 
.form-group{display:grid;gap:6px;}
.form-label{
  font-size:12.5px;font-weight:700;color:var(--navy);
  display:flex;align-items:center;gap:5px;
}
.form-required{color:#e11d48;font-size:13px;}
.form-optional{color:var(--slate);font-weight:400;font-size:12px;}
 
.form-input,
.form-select{
  width:100%;height:46px;
  border:1.5px solid var(--line);border-radius:10px;
  padding:0 14px;
  background:#fff;color:var(--text);
  font-family:var(--font-body);font-size:14px;
  outline:none;
  transition:border-color .2s,box-shadow .2s;
}
.form-input:focus,.form-select:focus{
  border-color:var(--teal);
  box-shadow:0 0 0 3px rgba(13,158,140,.14);
}
 
.form-consent{
  display:flex;align-items:flex-start;gap:10px;
  font-size:12.5px;line-height:1.6;color:var(--slate);
}
.form-consent input{
  width:16px;height:16px;margin-top:2px;
  accent-color:var(--teal);flex-shrink:0;
}
.form-consent a{color:var(--teal);font-weight:700;}
 
.form-submit{
  width:100%;height:50px;
  background:var(--teal);color:#fff;
  border:none;border-radius:10px;
  font-family:var(--font-body);font-size:14px;font-weight:700;
  cursor:pointer;
  transition:background .2s,box-shadow .2s;
}
.form-submit:hover{
  background:var(--teal-dark);
  box-shadow:0 8px 28px rgba(13,158,140,.3);
}
.form-submit.loading{opacity:.75;cursor:wait;}
 
.form-footer{
  text-align:center;font-size:12px;color:var(--slate);
}
.form-footer a{color:var(--teal);font-weight:700;}
 
/* ── CHAT WIDGET ─────────────────────────────────────────────────────────── */
.chat-toggle{
  position:fixed;right:24px;bottom:24px;
  width:60px;height:60px;border-radius:50%;
  border:none;
  background:var(--navy);
  color:#fff;
  cursor:pointer;
  box-shadow:0 8px 32px rgba(11,22,40,.3),0 2px 8px rgba(11,22,40,.2);
  z-index:9999;
  display:flex;align-items:center;justify-content:center;
  transition:transform .2s var(--ease),box-shadow .2s var(--ease),background .2s;
}
.chat-toggle:hover{
  transform:translateY(-3px);
  background:var(--navy-mid);
  box-shadow:0 16px 48px rgba(11,22,40,.35);
}
.chat-toggle svg{width:26px;height:26px;}
 
.chat-badge{
  position:absolute;top:-3px;right:-3px;
  width:20px;height:20px;border-radius:50%;
  background:#e11d48;color:#fff;
  border:2.5px solid #fff;
  font-size:10px;font-weight:800;
  display:flex;align-items:center;justify-content:center;
}
 
.chat-widget{
  position:fixed;right:24px;bottom:100px;
  width:380px;max-width:calc(100vw - 32px);
  height:540px;
  background:#fff;
  border:1px solid var(--line);
  border-radius:20px;
  box-shadow:var(--shadow-float);
  display:none;flex-direction:column;
  overflow:hidden;
  z-index:9998;
}
.chat-widget.is-open{
  display:flex;
  animation:chatWidgetIn .22s var(--ease) both;
}
@keyframes chatWidgetIn{
  from{opacity:0;transform:translateY(14px) scale(.97);}
  to{opacity:1;transform:none;}
}
 
.chat-header{
  background:linear-gradient(135deg,var(--navy) 0%,var(--navy-mid) 100%);
  padding:16px 18px;
  display:flex;align-items:center;justify-content:space-between;
  gap:12px;
}
.chat-header-profile{display:flex;align-items:center;gap:12px;min-width:0;}
.chat-header-avatar{
  width:42px;height:42px;border-radius:50%;
  background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.2);
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.chat-header-avatar svg{width:22px;height:22px;}
.chat-header-name{font-size:14.5px;font-weight:700;color:#fff;}
.chat-header-status{
  font-size:11.5px;color:rgba(255,255,255,.65);
  display:flex;align-items:center;gap:5px;margin-top:2px;
}
.chat-online{
  width:7px;height:7px;border-radius:50%;
  background:#4ade80;
  box-shadow:0 0 5px #4ade80;
}
.chat-close{
  width:32px;height:32px;border-radius:8px;
  border:1px solid rgba(255,255,255,.18);
  background:rgba(255,255,255,.1);
  color:#fff;font-size:20px;
  cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:background .2s;
}
.chat-close:hover{background:rgba(255,255,255,.2);}
 
.chat-intro{
  padding:12px 16px;
  background:#f7f9fc;
  border-bottom:1px solid var(--line);
}
.chat-intro strong{display:block;font-size:13px;font-weight:700;color:var(--navy);margin-bottom:2px;}
.chat-intro span{font-size:12px;color:var(--slate);line-height:1.5;}
 
.chat-messages{
  flex:1;overflow-y:auto;
  padding:16px;
  background:#fafbfd;
  display:flex;flex-direction:column;gap:10px;
}
.chat-messages::-webkit-scrollbar{width:4px;}
.chat-messages::-webkit-scrollbar-thumb{background:var(--line);border-radius:4px;}
 
.chat-msg-row{display:flex;}
.chat-msg-row.out{justify-content:flex-end;}
.chat-msg-row.in{justify-content:flex-start;}
 
.chat-bubble{
  max-width:80%;
  padding:10px 14px;
  font-size:13.5px;line-height:1.55;
  word-break:break-word;
}
.chat-bubble.out{
  background:var(--navy);color:#fff;
  border-radius:16px 16px 4px 16px;
}
.chat-bubble.in{
  background:#fff;color:var(--text);
  border:1px solid var(--line);
  border-radius:16px 16px 16px 4px;
}
.chat-bubble.bot{
  background:#fff;color:var(--text);
  border:1px solid var(--teal-soft);
  border-radius:16px 16px 16px 4px;
}
.chat-bubble-sender{
  font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
  color:var(--slate);margin-bottom:4px;
}
.chat-bubble.out .chat-bubble-sender{color:rgba(255,255,255,.5);text-align:right;}
.chat-bubble-text{white-space:pre-wrap;}
.chat-bubble-time{
  font-size:10px;color:var(--slate);margin-top:5px;text-align:right;
}
.chat-bubble.out .chat-bubble-time{color:rgba(255,255,255,.45);}
 
.chat-empty-msg{
  padding:14px;
  background:#fff;
  border:1.5px dashed var(--line);
  border-radius:12px;
  font-size:13px;color:var(--slate);
  line-height:1.6;text-align:center;
}
 
.chat-form{
  border-top:1px solid var(--line);
  padding:12px;
  background:#fff;
}
.chat-input-wrap{
  display:flex;align-items:center;gap:8px;
  background:#f7f9fc;
  border:1.5px solid var(--line);
  border-radius:12px;
  padding:5px 5px 5px 12px;
  transition:border-color .2s,box-shadow .2s;
}
.chat-input-wrap:focus-within{
  border-color:var(--teal);
  box-shadow:0 0 0 3px rgba(13,158,140,.12);
}
.chat-input{
  flex:1;min-width:0;border:none;background:transparent;
  font-family:var(--font-body);font-size:13.5px;color:var(--text);
  padding:8px 0;outline:none;
}
.chat-input::placeholder{color:#b0b8c8;}
.chat-send{
  width:38px;height:38px;border-radius:9px;
  border:none;background:var(--navy);color:#fff;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;flex-shrink:0;
  transition:background .2s;
}
.chat-send:hover{background:var(--navy-mid);}
.chat-send svg{width:17px;height:17px;}
.chat-note{
  font-size:11px;color:var(--slate);
  margin-top:8px;line-height:1.5;padding:0 2px;
}


.booking-service-dropdown {
  position: relative;
  width: 100%;
}

.booking-service-trigger {
  width: 100%;
  min-height: 46px;
  border: 1.5px solid var(--line);
  border-radius: 10px;
  padding: 0 14px;
  background: #ffffff;
  color: var(--text);
  font-family: var(--font-body);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  text-align: left;
  transition:
    border-color .2s var(--ease),
    box-shadow .2s var(--ease),
    background .2s var(--ease);
}

.booking-service-trigger:hover,
.booking-service-dropdown.is-open .booking-service-trigger {
  border-color: var(--teal);
  box-shadow: 0 0 0 3px rgba(13,158,140,.12);
  background: #f7fffd;
}

.booking-service-trigger-text {
  min-width: 0;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.booking-service-trigger-right {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.booking-service-count {
  display: none;
  min-width: 22px;
  height: 22px;
  padding: 0 7px;
  border-radius: 999px;
  background: var(--teal);
  color: #ffffff;
  font-size: 11px;
  font-weight: 800;
  align-items: center;
  justify-content: center;
}

.booking-service-count.is-visible {
  display: inline-flex;
}

.booking-service-arrow {
  width: 16px;
  height: 16px;
  color: var(--slate);
  transition: transform .2s var(--ease);
}

.booking-service-dropdown.is-open .booking-service-arrow {
  transform: rotate(180deg);
}

.booking-service-menu {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 8px);
  z-index: 10020;
  display: none;
  max-height: 230px;
  overflow-y: auto;
  padding: 8px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #ffffff;
  box-shadow: 0 18px 42px rgba(11,22,40,.18);
}

.booking-service-dropdown.is-open .booking-service-menu {
  display: grid;
  gap: 6px;
  animation: bookingServiceDrop .18s var(--ease) both;
}

@keyframes bookingServiceDrop {
  from {
    opacity: 0;
    transform: translateY(-6px) scale(.98);
  }

  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.booking-service-menu::-webkit-scrollbar {
  width: 6px;
}

.booking-service-menu::-webkit-scrollbar-track {
  background: #f3f6fa;
  border-radius: 999px;
}

.booking-service-menu::-webkit-scrollbar-thumb {
  background: var(--line);
  border-radius: 999px;
}

.booking-service-option {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 42px;
  padding: 9px 10px;
  border: 1.5px solid transparent;
  border-radius: 10px;
  background: #ffffff;
  color: var(--text);
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  transition:
    border-color .2s var(--ease),
    background .2s var(--ease),
    transform .18s var(--ease);
}

.booking-service-option:hover {
  background: #f7fffd;
  border-color: var(--teal-soft);
}

.booking-service-option.is-selected {
  background: var(--teal-light);
  border-color: var(--teal);
}

.booking-service-option input {
  width: 16px;
  height: 16px;
  accent-color: var(--teal);
  flex: 0 0 auto;
}

.booking-service-empty {
  padding: 12px;
  border: 1px dashed var(--line);
  border-radius: 10px;
  color: var(--slate);
  font-size: 13px;
  text-align: center;
}

.form-help {
  margin-top: 6px;
  font-size: 12px;
  color: var(--slate);
  line-height: 1.5;
}
 
/* ── Responsive ──────────────────────────────────────────────────────────── */
@media(max-width:980px){
  .hero-inner{grid-template-columns:1fr;gap:40px;}
  .hero-card{display:none;}
  .why-grid{grid-template-columns:1fr;}
  .why-left{padding-right:0;}
  .process-steps{grid-template-columns:1fr 1fr;gap:24px;}
  .process-steps::before{display:none;}
  .contact-grid{grid-template-columns:1fr;}
}
 
@media(max-width:700px){
  .hero{padding:120px 20px 70px;}
  .hero-h1{font-size:clamp(36px,10vw,52px);}
  .gallery-mosaic{
    grid-template-columns:1fr 1fr;
    grid-template-rows:none;
  }
  .gallery-item{height:160px !important;grid-column:auto !important;grid-row:auto !important;}
  .gallery-item:nth-child(7),.gallery-item:nth-child(8){display:block;}
  .process-steps{grid-template-columns:1fr;}
  .process-header,.services-header{grid-template-columns:1fr;}
  .services-grid{grid-template-columns:1fr;}
  .booking-modal-top{padding:20px 18px 16px;}
  .booking-form{padding:18px;}
  .chat-widget{right:12px;left:12px;width:auto;height:72vh;bottom:90px;border-radius:16px;}
}
</style>
 
<div id="homeContentWrap">
  <?php $homeClinicName = $clinicName; ?>
    <?php require __DIR__ . '/../layouts/partials/public-navbar.php'; ?>

    <section id="home" class="hero">
        <div class="hero-bg">
            <img src="<?= home_e($heroMainImage) ?>" alt="Dental clini">
        </div>

        <div class="hero-inner">
            <div class="hero-content">

                <div class="hero-badge">
                    <span class="hero-badge-dot"></span>
                    <?= home_e($clinicName) ?>
                </div>

                <?php
                $heroParts = explode(',', $heroTitle, 2);
                $heroFirstLine = trim($heroParts[0] ?? $heroTitle);
                $heroSecondLine = trim($heroParts[1] ?? '');
                ?>

                <h1 class="hero-h1">
                    <?= home_e($heroFirstLine) ?><?= $heroSecondLine !== '' ? ',' : '' ?><br>
                    <span class="teal-word">
                        <?= home_e($heroSecondLine !== '' ? $heroSecondLine : 'Our Priority') ?>
                    </span>
                </h1>

                <span class="hero-h1-sub">
                    <?= home_e($heroSubtitle) ?>
                </span>

                <p class="hero-desc">
                    <?= home_e($aboutSection) ?>
                </p>

                <div class="hero-actions">
                    <?php if ($onlineBookingEnabled && $guestBookingEnabled): ?>
                        <a href="<?= home_e($baseUrl . '/book') ?>" class="btn btn-primary js-open-booking-modal">
                            <?= home_e($homepageCta) ?>
                        </a>
                    <?php else: ?>
                        <a href="#contact" class="btn btn-primary">
                            Contact Clinic
                        </a>
                    <?php endif; ?>

                    <a href="#services" class="btn btn-outline-white">
                        Browse Services
                    </a>
                </div>

                <div class="hero-stats">
                    <div>
                        <div class="hero-stat-num"><?= max(0, count($services)) ?></div>
                        <div class="hero-stat-label">Dental Services</div>
                    </div>
                    <div>
                        <div class="hero-stat-num">Easy</div>
                        <div class="hero-stat-label">Online Booking</div>
                    </div>
                    <div>
                        <div class="hero-stat-num">Fast</div>
                        <div class="hero-stat-label">Confirmation</div>
                    </div>
                </div>

            </div>
        </div>
    </section>
 
  <!-- ════════════════════════════════ PROCESS ═══════════════════════════ -->
  <section class="process-section">
    <div class="wrap">
      <div class="process-header reveal">
        <div>
          <div class="section-eyebrow">How it works</div>
          <h2 class="section-title">Book in four <em>simple steps</em></h2>
        </div>
        <a href="/DentalClinic/public/book" class="btn btn-ghost js-open-booking-modal">Get started →</a>
      </div>
 
      <div class="process-steps">
        <div class="process-step reveal reveal-delay-1">
          <div class="process-step-num"><span>01</span></div>
          <div class="process-step-title">Choose a service</div>
          <p class="process-step-text">Browse available dental treatments and select the one that fits your need before starting your appointment request.</p>
        </div>
        <div class="process-step reveal reveal-delay-2">
          <div class="process-step-num"><span>02</span></div>
          <div class="process-step-title">Select a schedule</div>
          <p class="process-step-text">Pick your preferred date and available time slot based on the clinic's open hours and current availability.</p>
        </div>
        <div class="process-step reveal reveal-delay-3">
          <div class="process-step-num"><span>03</span></div>
          <div class="process-step-title">Fill in your details</div>
          <p class="process-step-text">Enter your contact information and any notes to help the clinic prepare for your visit efficiently.</p>
        </div>
        <div class="process-step reveal reveal-delay-4">
          <div class="process-step-num"><span>04</span></div>
          <div class="process-step-title">Await confirmation</div>
          <p class="process-step-text">The clinic reviews your request and sends a final confirmation with your appointment details.</p>
        </div>
      </div>
    </div>
  </section>
 
 <!-- ════════════════════════════════ SERVICES ══════════════════════════ -->
<section id="services" class="services-section">
  <div class="wrap">
    <div class="services-header reveal">
      <div>
        <div class="section-eyebrow">What we offer</div>
        <h2 class="section-title">Available <em>dental services</em></h2>
        <p class="section-lead"><?= home_e($featuredServicesText) ?></p>
      </div>
    </div>

    <div class="services-grid">
      <?php if (!empty($services)): ?>
        <?php foreach ($services as $service): ?>
          <?php
            $serviceId = is_array($service)
                ? (int) ($service['service_id'] ?? 0)
                : (int) ($service->service_id ?? 0);

            $serviceName = is_array($service)
                ? (string) ($service['service_name'] ?? 'Dental Service')
                : (string) ($service->service_name ?? 'Dental Service');

            $serviceDescription = is_array($service)
                ? trim((string) ($service['description'] ?? ''))
                : trim((string) ($service->description ?? ''));

            $serviceDuration = is_array($service)
                ? (int) ($service['estimated_duration_minutes'] ?? 30)
                : (int) ($service->estimated_duration_minutes ?? 30);

            $servicePrice = is_array($service)
                ? (float) ($service['estimated_price'] ?? 0)
                : (float) ($service->estimated_price ?? 0);

            $serviceImagePath = is_array($service)
                ? trim((string) ($service['service_image'] ?? ''))
                : trim((string) ($service->service_image ?? ''));

            $serviceImage = $serviceImagePath !== ''
                ? $baseUrl . '/' . ltrim($serviceImagePath, '/')
                : $baseUrl . '/images/services/default-service.jpg';
          ?>

          <div class="service-card reveal">
            <div class="service-card-img">
              <img
                src="<?= home_e($serviceImage) ?>"
                alt="<?= home_e($serviceName) ?>"
                loading="lazy"
              >
              <div class="service-card-img-overlay"></div>
            </div>

            <div class="service-card-body">
              <h3 class="service-card-title"><?= home_e($serviceName) ?></h3>

              <p class="service-card-desc">
                <?= home_e($serviceDescription !== '' ? $serviceDescription : 'Professional dental care tailored to your individual needs and comfort.') ?>
              </p>

              <div class="service-card-meta">
                <span><?= (int) $serviceDuration ?> min</span>
                <span>PHP <?= number_format($servicePrice, 2) ?></span>
              </div>

              <div class="service-card-footer">
                <a
                  href="<?= home_e($baseUrl . '/book?service_id=' . $serviceId) ?>"
                  class="btn btn-sm btn-ghost js-open-booking-modal"
                  data-service-id="<?= home_e((string) $serviceId) ?>"
                >
                  Book this service →
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

      <?php else: ?>
        <div class="service-card reveal">
          <div class="service-card-img">
            <img src="<?= home_e($baseUrl . '/images/services/default-service.jpg') ?>" alt="Dental Services">
            <div class="service-card-img-overlay"></div>
          </div>

          <div class="service-card-body">
            <h3 class="service-card-title">Dental Services</h3>
            <p class="service-card-desc">Services will appear here once they are configured in the clinic system.</p>

            <div class="service-card-footer">
              <a href="<?= home_e($baseUrl . '/book') ?>" class="btn btn-sm btn-ghost js-open-booking-modal">
                Book Now →
              </a>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
 
  <!-- ══════════════════════════════════ WHY ════════════════════════════ -->
  <section class="why-section">
    <div class="wrap">
      <div class="why-grid">
        <div class="why-left reveal">
          <div class="section-eyebrow">Why choose us</div>
          <h2 class="section-title">Built for a <em>better</em> patient experience</h2>
          <p class="section-lead" style="margin-bottom:0">
  <?= home_e($aboutSection) ?>
</p>
 
          <div class="why-features">
            <div class="why-feature">
              <div class="why-feature-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              </div>
              <div>
                <div class="why-feature-title">Guided booking process</div>
                <p class="why-feature-text">Step-by-step appointment request that's easy to follow for both new and returning patients.</p>
              </div>
            </div>
            <div class="why-feature">
              <div class="why-feature-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 3"/></svg>
              </div>
              <div>
                <div class="why-feature-title">Clear service information</div>
                <p class="why-feature-text">Review treatment details, pricing, and duration before you commit to an appointment.</p>
              </div>
            </div>
            <div class="why-feature">
              <div class="why-feature-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              </div>
              <div>
                <div class="why-feature-title">Direct clinic support</div>
                <p class="why-feature-text">Use the chat to ask questions about services, schedules, or anything before your visit.</p>
              </div>
            </div>
            <div class="why-feature">
              <div class="why-feature-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              </div>
              <div>
                <div class="why-feature-title">Organized scheduling</div>
                <p class="why-feature-text">Every confirmed appointment is tracked and organized so both parties are always prepared.</p>
              </div>
            </div>
          </div>
        </div>
 
        <div class="why-right">
          <div class="info-card reveal reveal-delay-1">
            <div class="info-card-eyebrow">Appointment review</div>
            <div class="info-card-title">Staff-confirmed scheduling</div>
            <p class="info-card-text">Every request is reviewed by our team before confirmation, ensuring your slot is properly prepared and ready.</p>
          </div>
 
          <div class="info-card dark reveal reveal-delay-2">
            <div class="info-card-eyebrow">What to expect</div>
            <div class="info-card-title" style="color:#fff">Before your visit</div>
            <div class="schedule-list">
              <div class="schedule-item">
                <div>
                  <div class="schedule-item-label">Service guidance</div>
                  <div class="schedule-item-value">Review service details and estimated costs before selecting.</div>
                </div>
              </div>
              <div class="schedule-item">
                <div>
                  <div class="schedule-item-label">Schedule flexibility</div>
                  <div class="schedule-item-value">Choose a preferred date and time based on available clinic hours.</div>
                </div>
              </div>
              <div class="schedule-item">
                <div>
                  <div class="schedule-item-label">Clinic support</div>
                  <div class="schedule-item-value">Use our chat assistant if you need help before booking.</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
 
  <!-- ══════════════════════════════════ GALLERY ════════════════════════ -->
  <?php if ($galleryVisible): ?>
  <section id="gallery" class="gallery-section">
    <div class="wrap">
      <div class="gallery-header reveal">
        <div class="section-eyebrow">Our clinic</div>
        <h2 class="section-title" style="color:#fff">A space built for <em>comfort</em></h2>
        <p class="section-lead" style="color:rgba(255,255,255,.55)">A look inside the clinic where patients receive personalized care and treatment.</p>
      </div>

      <div class="gallery-mosaic">
        <?php foreach (array_slice($galleryImages, 0, 6) as $galleryImage): ?>
          <div class="gallery-item reveal">
            <img src="<?= home_e($galleryImage) ?>" alt="Clinic gallery" loading="lazy">
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>
 
  <!-- ════════════════════════════════ CONTACT ══════════════════════════ -->
  <section id="contact" class="contact-section">
    <div class="wrap">
      <div class="reveal" style="text-align:center;margin-bottom:0">
        <div class="section-eyebrow" style="justify-content:center">Contact us</div>
        <h2 class="section-title" style="text-align:center">Reach the clinic <em>directly</em></h2>
        <p class="section-lead" style="margin:14px auto 0;text-align:center">
  <?= home_e($contactSection) ?>
</p>
      </div>
 
      <div class="contact-grid">
        <div class="contact-card reveal">
          <div class="contact-card-title">Clinic Information</div>
          <div class="contact-info-list">
            <div class="contact-info-row">
              <div class="contact-info-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.73a16 16 0 0 0 6.29 6.29l.93-.93a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7a2 2 0 0 1 1.72 2.03z"/></svg>
              </div>
              <div>
                <div class="contact-info-label">Phone</div>
                <div class="contact-info-value"><?= home_e($clinicPhone) ?></div>
              </div>
            </div>
            <div class="contact-info-row">
              <div class="contact-info-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              </div>
              <div>
                <div class="contact-info-label">Email</div>
                <div class="contact-info-value"><?= home_e($clinicEmail) ?></div>
              </div>
            </div>
            <div class="contact-info-row">
              <div class="contact-info-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              </div>
              <div>
                <div class="contact-info-label">Location</div>
                <div class="contact-info-value"><?= home_e($clinicAddress) ?></div>
              </div>
            </div>
            <div class="contact-info-row">
              <div class="contact-info-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
              </div>
              <div>
                <div class="contact-info-label">Clinic Hours</div>
                <div class="contact-info-value">
                  <?= home_e($clinicHoursText) ?>
                </div>
              </div>
            </div>
          </div>
        </div>
 
        <div class="cta-card reveal reveal-delay-2">
          <div>
            <div class="cta-card-eyebrow">Ready to visit us?</div>
            <div class="cta-card-title">Book your appointment today</div>
            <p class="cta-card-text">Let us handle the scheduling so you can focus on getting the care you need. Quick, simple, and confirmation guaranteed.</p>
          </div>
          <div class="cta-card-actions">
           <?php if ($onlineBookingEnabled && $guestBookingEnabled): ?>
    <a href="<?= home_e($baseUrl . '/book') ?>" class="btn btn-primary js-open-booking-modal">
        <?= home_e($homepageCta) ?>
    </a>
<?php else: ?>
    <a href="#contact" class="btn btn-primary">
        Contact Clinic
    </a>
<?php endif; ?>
            <a href="/DentalClinic/public/track-request" class="btn btn-outline-white btn-sm">Track Request</a>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
 
<!-- ═══════════════════════════ BOOKING MODAL ════════════════════════════ -->
<div id="bookingEntryModal" class="modal-overlay" aria-hidden="true">
  <div class="booking-modal-shell">
    <button type="button" class="booking-modal-close" id="bookingModalClose" aria-label="Close">×</button>

    <div class="booking-modal" role="dialog" aria-modal="true" aria-labelledby="bookingTitle">
      <form method="GET" action="/DentalClinic/public/book/contact-options" class="booking-form" id="bookingEntryForm">
      <input type="hidden" name="step" value="schedule">
 
     <div class="form-group">
  <label class="form-label" for="booking_contact_number_display">
    Phone number <span class="form-required">*</span>
  </label>

  <input
    type="hidden"
    id="booking_contact_number"
    name="contact_number"
    value=""
  >

  <div class="phone-input-wrap">
    <span class="phone-prefix">+63</span>
    <input
      class="form-input phone-local-input"
      type="text"
      id="booking_contact_number_display"
      placeholder="9XXXXXXXXX"
      required
      inputmode="numeric"
      autocomplete="tel"
      maxlength="10"
      pattern="^9\d{9}$"
    >
  </div>

  <div class="form-help">
    Enter Philippine mobile number only. Example: 9123456789 will be saved as +639123456789.
  </div>
</div>

<div class="form-group">
  <label class="form-label" for="booking_email">
    Email <span class="form-required">*</span>
  </label>
  <input
    class="form-input"
    type="email"
    id="booking_email"
    name="email"
    placeholder="example@gmail.com"
    required
    autocomplete="email"
  >
  <div class="form-help">
    Required for appointment verification and confirmation.
  </div>
</div>
 
    <div class="form-group">
  <label class="form-label" for="booking_service_trigger">
    Select service(s) <span class="form-required">*</span>
  </label>

  <input type="hidden" name="service_id" id="booking_primary_service_id" value="">
  <div id="booking_service_ids_hidden"></div>

  <div class="booking-service-dropdown" id="booking_service_dropdown">
    <button
      type="button"
      class="booking-service-trigger"
      id="booking_service_trigger"
      aria-haspopup="listbox"
      aria-expanded="false"
    >
      <span class="booking-service-trigger-text" id="booking_service_trigger_text">
        Choose dental service(s)
      </span>

      <span class="booking-service-trigger-right">
        <span class="booking-service-count" id="booking_service_count">0</span>

        <svg class="booking-service-arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
    </button>

    <div class="booking-service-menu" id="booking_service_menu" role="listbox" aria-multiselectable="true">
      <?php if (!empty($services)): ?>
        <?php foreach ($services as $service): ?>
          <?php
            $modalServiceId = is_array($service)
                ? ($service['service_id'] ?? '')
                : ($service->service_id ?? '');

            $modalServiceName = is_array($service)
                ? ($service['service_name'] ?? 'Service')
                : ($service->service_name ?? 'Service');
          ?>

          <label class="booking-service-option" data-booking-service-option>
            <input
              type="checkbox"
              value="<?= htmlspecialchars((string) $modalServiceId, ENT_QUOTES, 'UTF-8') ?>"
              data-booking-service-checkbox
            >

            <span>
              <?= htmlspecialchars((string) $modalServiceName, ENT_QUOTES, 'UTF-8') ?>
            </span>
          </label>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="booking-service-empty">
          No services are available right now.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="form-help">
    You may select one or more services for this appointment request.
  </div>
</div>
 
      <label class="form-consent" for="booking_privacy_consent">
        <input type="checkbox" id="booking_privacy_consent" name="privacy_consent" value="1" required>
        <span>
          I agree to the clinic's
          <a href="/DentalClinic/public/privacy_notice" target="_blank" rel="noopener">Privacy Policy</a>
          and allow the clinic to use my information for appointment verification and scheduling.
        </span>
      </label>
 
      <button type="submit" class="form-submit" id="bookingEntrySubmitBtn">Send Verification</button>
 
      <div class="form-footer">
        Already submitted?
        <a href="/DentalClinic/public/track-request">Track your appointment</a>
      </div>
      </form>
    </div>
  </div>
</div>
 <?php if ($chatbotEnabled && $guestChatbotEnabled): ?>
<!-- ═══════════════════════════ CHAT WIDGET ══════════════════════════════ -->
<button type="button" id="clinicChatToggle" class="chat-toggle"
        data-start-url="/DentalClinic/public/chat/widget/start"
        data-send-url="/DentalClinic/public/chat/widget/send"
        data-fetch-url="/DentalClinic/public/chat/widget/fetch"
        aria-label="Open clinic chat" aria-controls="clinicChatWidget" aria-expanded="false">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
  </svg>
  <span class="chat-badge" id="clinicChatBadge">1</span>
</button>
 
<div id="clinicChatWidget" class="chat-widget" aria-hidden="true">
  <div class="chat-header">
    <div class="chat-header-profile">
      <div class="chat-header-avatar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="22" height="22" aria-hidden="true">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
      </div>
      <div>
        <div class="chat-header-name">Clinic Assistant</div>
        <div class="chat-header-status">
          <span class="chat-online"></span>
          Online now
        </div>
      </div>
    </div>
    <button type="button" id="clinicChatClose" class="chat-close" aria-label="Close chat">×</button>
  </div>
 
  <div class="chat-intro">
    <strong>How can we help you?</strong>
    <span><?= home_e($chatbotWelcomeMessage) ?></span>
  </div>
 
  <div id="clinicChatMessages" class="chat-messages">
    <div class="chat-empty-msg">Start a conversation — we're here to help.</div>
  </div>
 
  <form id="clinicChatForm" class="chat-form">
    <?= Csrf::inputField(); ?>
    <div class="chat-input-wrap">
      <input type="text" id="clinicChatInput" class="chat-input" name="message_text"
             placeholder="Type a message…" autocomplete="off" maxlength="1000" required>
      <button type="submit" class="chat-send" id="clinicChatSendBtn" aria-label="Send">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
        </svg>
      </button>
    </div>
    <div class="chat-note">Guest chat is text-only. Registered patients get full messaging after login.</div>
  </form>
</div>
 <?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
 
  /* ── Scroll reveal ─────────────────────────────────────────────────── */
  const revealEls = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if(entry.isIntersecting){
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    },{threshold:.14});
    revealEls.forEach(function(el){ observer.observe(el); });
  } else {
    revealEls.forEach(function(el){ el.classList.add('visible'); });
  }
 
 /* ── Booking modal ─────────────────────────────────────────────────── */
const bookingModal = document.getElementById('bookingEntryModal');
const bookingCloseBtn = document.getElementById('bookingModalClose');
const bookingForm = document.getElementById('bookingEntryForm');
const bookingSubmitBtn = document.getElementById('bookingEntrySubmitBtn');
const contactInput = document.getElementById('booking_contact_number');
const contactDisplayInput = document.getElementById('booking_contact_number_display');
const emailInput = document.getElementById('booking_email');
const privacyCheck = document.getElementById('booking_privacy_consent');

const serviceDropdown = document.getElementById('booking_service_dropdown');
const serviceTrigger = document.getElementById('booking_service_trigger');
const serviceTriggerText = document.getElementById('booking_service_trigger_text');
const serviceMenu = document.getElementById('booking_service_menu');
const serviceCount = document.getElementById('booking_service_count');
const serviceChecks = document.querySelectorAll('[data-booking-service-checkbox]');
const serviceOptions = document.querySelectorAll('[data-booking-service-option]');
const serviceHiddenWrap = document.getElementById('booking_service_ids_hidden');
const primaryServiceInput = document.getElementById('booking_primary_service_id');

const bookingTriggers = document.querySelectorAll('.js-open-booking-modal, a[href="/DentalClinic/public/book"]');

function normalizePHLocal(value) {
  let phone = String(value || '').replace(/[^\d]/g, '');

  if (phone.startsWith('63')) {
    phone = phone.substring(2);
  }

  if (phone.startsWith('0')) {
    phone = phone.substring(1);
  }

  if (phone.length > 10) {
    phone = phone.substring(0, 10);
  }

  return phone;
}

function syncPHPhone() {
  if (!contactDisplayInput || !contactInput) {
    return '';
  }

  const localNumber = normalizePHLocal(contactDisplayInput.value);
  contactDisplayInput.value = localNumber;

  contactInput.value = localNumber !== '' ? '+63' + localNumber : '';

  return contactInput.value;
}

function isValidPH() {
  const phone = syncPHPhone();

  return /^\+639\d{9}$/.test(phone);
}

function isValidEmail(value) {
  const email = String(value || '').trim();

  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
}

function getServiceId(element) {
  if (element.dataset.serviceId) {
    return element.dataset.serviceId;
  }

  try {
    return new URL(element.href, location.origin).searchParams.get('service_id') || '';
  } catch (error) {
    return '';
  }
}

function getSelectedServiceIds() {
  return Array.from(serviceChecks)
    .filter(function (checkbox) {
      return checkbox.checked;
    })
    .map(function (checkbox) {
      return checkbox.value;
    });
}

function getSelectedServiceNames() {
  return Array.from(serviceChecks)
    .filter(function (checkbox) {
      return checkbox.checked;
    })
    .map(function (checkbox) {
      const option = checkbox.closest('[data-booking-service-option]');
      const label = option ? option.querySelector('span') : null;

      return label ? label.textContent.trim() : '';
    })
    .filter(Boolean);
}

function syncSelectedServices() {
  const selectedIds = getSelectedServiceIds();
  const selectedNames = getSelectedServiceNames();

  if (primaryServiceInput) {
    primaryServiceInput.value = selectedIds.length > 0 ? selectedIds[0] : '';
  }

  if (serviceHiddenWrap) {
    serviceHiddenWrap.innerHTML = '';

    selectedIds.forEach(function (serviceId) {
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'service_ids[]';
      hidden.value = serviceId;
      serviceHiddenWrap.appendChild(hidden);
    });
  }

  serviceOptions.forEach(function (option) {
    const checkbox = option.querySelector('[data-booking-service-checkbox]');
    option.classList.toggle('is-selected', checkbox && checkbox.checked);
  });

  if (serviceTriggerText) {
    if (selectedNames.length === 0) {
      serviceTriggerText.textContent = 'Choose dental service(s)';
    } else if (selectedNames.length === 1) {
      serviceTriggerText.textContent = selectedNames[0];
    } else {
      serviceTriggerText.textContent = selectedNames.length + ' services selected';
    }
  }

  if (serviceCount) {
    serviceCount.textContent = String(selectedIds.length);
    serviceCount.classList.toggle('is-visible', selectedIds.length > 0);
  }

  return selectedIds;
}

function openServiceDropdown() {
  if (!serviceDropdown || !serviceTrigger) {
    return;
  }

  serviceDropdown.classList.add('is-open');
  serviceTrigger.setAttribute('aria-expanded', 'true');
}

function closeServiceDropdown() {
  if (!serviceDropdown || !serviceTrigger) {
    return;
  }

  serviceDropdown.classList.remove('is-open');
  serviceTrigger.setAttribute('aria-expanded', 'false');
}

function toggleServiceDropdown() {
  if (!serviceDropdown) {
    return;
  }

  if (serviceDropdown.classList.contains('is-open')) {
    closeServiceDropdown();
  } else {
    openServiceDropdown();
  }
}

function selectModalService(serviceId) {
  if (!serviceId) {
    return;
  }

  serviceChecks.forEach(function (checkbox) {
    if (String(checkbox.value) === String(serviceId)) {
      checkbox.checked = true;
    }
  });

  syncSelectedServices();
}

function openBooking(serviceId) {
  if (!bookingModal) {
    return;
  }

  bookingModal.classList.add('is-open');
  bookingModal.setAttribute('aria-hidden', 'false');
  document.body.classList.add('modal-open');

  if (serviceId) {
    selectModalService(serviceId);
  }

 window.setTimeout(function () {
  if (contactDisplayInput) {
    contactDisplayInput.focus();
  }
}, 150);
}

function closeBooking() {
  if (!bookingModal) {
    return;
  }

  bookingModal.classList.remove('is-open');
  bookingModal.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('modal-open');
  closeServiceDropdown();
}

if (serviceTrigger) {
  serviceTrigger.addEventListener('click', function () {
    toggleServiceDropdown();
  });
}

serviceChecks.forEach(function (checkbox) {
  checkbox.addEventListener('change', syncSelectedServices);
});

document.addEventListener('click', function (event) {
  if (!serviceDropdown) {
    return;
  }

  if (!serviceDropdown.contains(event.target)) {
    closeServiceDropdown();
  }
});

bookingTriggers.forEach(function (element) {
  element.addEventListener('click', function (event) {
    event.preventDefault();
    openBooking(getServiceId(element));
  });
});
const homeParams = new URLSearchParams(window.location.search);

if (homeParams.get('open_booking') === '1') {
  window.setTimeout(function () {
    openBooking('');

    const cleanUrl = window.location.origin + window.location.pathname;
    window.history.replaceState({}, document.title, cleanUrl);
  }, 150);
}

if (bookingCloseBtn) {
  bookingCloseBtn.addEventListener('click', closeBooking);
}

if (bookingModal) {
  bookingModal.addEventListener('click', function (event) {
    if (event.target === bookingModal) {
      closeBooking();
    }
  });
}

document.addEventListener('keydown', function (event) {
  if (event.key === 'Escape') {
    if (bookingModal && bookingModal.classList.contains('is-open')) {
      closeBooking();
    }

    closeServiceDropdown();
  }
});

if (contactDisplayInput) {
  contactDisplayInput.addEventListener('input', syncPHPhone);

  contactDisplayInput.addEventListener('blur', syncPHPhone);

  contactDisplayInput.addEventListener('paste', function () {
    window.setTimeout(syncPHPhone, 0);
  });
}
if (bookingForm && bookingSubmitBtn) {
  bookingForm.addEventListener('submit', function (event) {
  syncPHPhone();

if (!contactInput || !isValidPH()) {
  event.preventDefault();
  alert('Please enter a valid Philippine mobile number. Example: 9123456789.');

  if (contactDisplayInput) {
    contactDisplayInput.focus();
  }

  return;
}

contactInput.value = normalizePHPhone(contactInput.value);

contactInput.value = normalizePHPhone(contactInput.value);

if (!emailInput || !isValidEmail(emailInput.value)) {
  event.preventDefault();
  alert('Please enter a valid email address for verification.');

  if (emailInput) {
    emailInput.focus();
  }

  return;
}

    const selectedServiceIds = syncSelectedServices();

    if (selectedServiceIds.length === 0) {
      event.preventDefault();
      alert('Please select at least one dental service.');

      openServiceDropdown();

      if (serviceDropdown) {
        serviceDropdown.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        });
      }

      return;
    }

    if (!privacyCheck || !privacyCheck.checked) {
      event.preventDefault();
      alert('Please agree to the Privacy Policy to continue.');

      if (privacyCheck) {
        privacyCheck.focus();
      }

      return;
    }

    bookingSubmitBtn.classList.add('loading');
    bookingSubmitBtn.textContent = 'Sending…';
    bookingSubmitBtn.disabled = true;
  });
}

syncSelectedServices();
 
  /* ── Chat widget ───────────────────────────────────────────────────── */
  const chatToggle    = document.getElementById('clinicChatToggle');
  const chatWidget    = document.getElementById('clinicChatWidget');
  const chatClose     = document.getElementById('clinicChatClose');
  const chatForm      = document.getElementById('clinicChatForm');
  const chatInput     = document.getElementById('clinicChatInput');
  const chatMessages  = document.getElementById('clinicChatMessages');
  const chatBadge     = document.getElementById('clinicChatBadge');
  const chatSend      = document.getElementById('clinicChatSendBtn');
 
  if(!chatToggle||!chatWidget) return;
 
  const startUrl  = chatToggle.dataset.startUrl;
  const sendUrl   = chatToggle.dataset.sendUrl;
  const fetchUrl  = chatToggle.dataset.fetchUrl;
  const csrfEl    = document.querySelector('#clinicChatForm input[name="_csrf_token"]');
  const csrf      = csrfEl?csrfEl.value:'';
 
  let started=false,loading=false;
 
  chatToggle.addEventListener('click',async function(){
    if(chatWidget.classList.contains('is-open')){ closeChat(); return; }
    openChat();
    if(!started) await startChat();
    await loadMessages();
  });
 
  chatClose&&chatClose.addEventListener('click',closeChat);
 
  function openChat(){
    chatWidget.classList.add('is-open');
    chatWidget.setAttribute('aria-hidden','false');
    chatToggle.setAttribute('aria-expanded','true');
    if(chatBadge) chatBadge.style.display='none';
    setTimeout(function(){ chatInput&&chatInput.focus(); },150);
  }
  function closeChat(){
    chatWidget.classList.remove('is-open');
    chatWidget.setAttribute('aria-hidden','true');
    chatToggle.setAttribute('aria-expanded','false');
  }
 
  async function startChat(){
    if(started) return;
    try{
      const r=await fetch(startUrl,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
      const d=await safeJson(r);
      started=true;
      if(!r.ok||!d.success){ renderSystem('Chat is available but could not start a session right now.'); return; }
      renderMessages(d.messages||[]);
    }catch(err){
      started=true;
      renderSystem('Chat is temporarily unavailable. Please try again later.');
    }
  }
 
  async function loadMessages(){
    if(loading) return; loading=true;
    try{
      const r=await fetch(fetchUrl,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
      const d=await safeJson(r);
      if(r.ok&&d.success) renderMessages(d.messages||[]);
    }catch(e){}
    finally{ loading=false; }
  }
 
  function renderMessages(msgs){
    chatMessages.innerHTML='';
    if(!msgs.length){ renderSystem('Start a conversation — we\'re here to help.'); return; }
    msgs.forEach(function(m){ appendBubble(m.sender_type,m.message_text||'',m.sent_at,Number(m.is_bot_reply||0)); });
    scrollDown();
  }
 
  function renderSystem(text){
    chatMessages.innerHTML='';
    const d=document.createElement('div');
    d.className='chat-empty-msg';d.textContent=text;
    chatMessages.appendChild(d);
  }
 
  function appendBubble(senderType,text,sentAt,isBot){
    const type=normType(senderType,isBot);
    const row=document.createElement('div');
    row.className='chat-msg-row '+(type==='guest'?'out':'in');
 
    const bubble=document.createElement('div');
    bubble.className='chat-bubble '+(type==='guest'?'out':type==='bot'?'bot':'in');
 
    const sender=document.createElement('div');
    sender.className='chat-bubble-sender';
    sender.textContent=type==='guest'?'You':type==='bot'?'Clinic Assistant':'Clinic Staff';
 
    const body=document.createElement('div');
    body.className='chat-bubble-text';
    body.textContent=text;
 
    const time=document.createElement('div');
    time.className='chat-bubble-time';
    time.textContent=fmtTime(sentAt);
 
    bubble.appendChild(sender);bubble.appendChild(body);bubble.appendChild(time);
    row.appendChild(bubble);chatMessages.appendChild(row);
  }
 
  function normType(t,isBot){
    if(t==='guest'||t==='patient') return 'guest';
    if(Number(isBot)===1||t==='bot') return 'bot';
    return 'staff';
  }
 
  chatForm&&chatForm.addEventListener('submit',async function(e){
    e.preventDefault();
    const text=chatInput.value.trim();
    if(!text) return;
    if(!started) await startChat();
    appendBubble('guest',text,new Date().toISOString(),0);
    scrollDown();
    chatInput.value='';chatInput.disabled=true;
    if(chatSend) chatSend.disabled=true;
    try{
      const r=await fetch(sendUrl,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json','Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({message_text:text})});
      const d=await safeJson(r);
      if(!r.ok||!d.success){
        appendBubble('bot',d.message||'Your message could not be sent. Please try again.',new Date().toISOString(),1);
      } else if(d.bot_message){
        appendBubble(d.bot_message.sender_type||'bot',d.bot_message.message_text||'',d.bot_message.sent_at,1);
      } else { await loadMessages(); }
      scrollDown();
    }catch(err){
      appendBubble('bot','Could not send your message. Please try again later.',new Date().toISOString(),1);
      scrollDown();
    }finally{
      chatInput.disabled=false;
      if(chatSend) chatSend.disabled=false;
      chatInput.focus();
    }
  });
 
  async function safeJson(r){ try{ return await r.json(); }catch(e){ return {}; } }
  function fmtTime(v){ if(!v) return ''; const d=new Date(v); if(isNaN(d)) return ''; return d.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}); }
  function scrollDown(){ chatMessages.scrollTop=chatMessages.scrollHeight; }
 
  setInterval(function(){
    if(chatWidget.classList.contains('is-open')&&started) loadMessages();
  },3000);
});


document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);

    if (params.get('open') === 'booking') {
        window.location.href = '/DentalClinic/public/book';
    }
});
</script>
 
<?php
$content = ob_get_clean();
$title = 'Home';
require __DIR__ . '/../layouts/main.php';