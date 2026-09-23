<?php
use App\Core\Auth;
use App\Core\Csrf;

$pageTitle = $pageTitle ?? 'Dentist Dashboard';

$stats = $stats ?? [
    'today_appointments' => 0,
    'completed_today' => 0,
    'my_patients' => 0,
    'followup_queue' => 0,
];

$todayAppointments = $todayAppointments ?? [];
$upcomingAppointments = $upcomingAppointments ?? [];
$completedAppointments = $completedAppointments ?? [];

$authUser = Auth::user();
$displayName = trim((string) (($authUser['first_name'] ?? '') . ' ' . ($authUser['last_name'] ?? '')));
$roleName = (string) ($authUser['role_name'] ?? 'dentist');

if ($displayName === '') {
    $displayName = 'Dentist';
}

$tabs = [
    'today' => [
        'label' => "Today (" . count($todayAppointments) . ")",
        'rows' => $todayAppointments,
        'title' => "Today's Appointment Schedule",
    ],
    'upcoming' => [
        'label' => 'Upcoming (' . count($upcomingAppointments) . ')',
        'rows' => $upcomingAppointments,
        'title' => 'Upcoming Appointments',
    ],
    'completed' => [
        'label' => 'Completed (' . count($completedAppointments) . ')',
        'rows' => $completedAppointments,
        'title' => 'Completed Appointments',
    ],
];

$activeTab = $_GET['tab'] ?? 'today';
if (!isset($tabs[$activeTab])) {
    $activeTab = 'today';
}

$activeRows = $tabs[$activeTab]['rows'];
$activeTitle = $tabs[$activeTab]['title'];

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function badgeClass(string $status): string
{
    $status = strtolower(trim($status));

    return match ($status) {
        'completed' => 'badge-completed',
        'rescheduled' => 'badge-rescheduled',
        'cancelled', 'rejected', 'no_show', 'no show' => 'badge-cancelled',
        'confirmed' => 'badge-confirmed',
        'checked_in', 'checked in' => 'badge-checked',
        'in_progress', 'in progress' => 'badge-progress',
        default => 'badge-default',
    };
}

function niceTime(?string $time): string
{
    if (!$time) {
        return '';
    }

    $timestamp = strtotime($time);
    return $timestamp ? date('h:i A', $timestamp) : $time;
}

function niceDate(?string $date): string
{
    if (!$date) {
        return '';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('M d, Y', $timestamp) : $date;
}

ob_start();
?>

<style>
:root {
    --dc-bg: #f5f5f5;
    --dc-card: #ffffff;
    --dc-soft: #f9fafb;
    --dc-soft-2: #f3f4f6;
    --dc-border: #e5e7eb;
    --dc-text: #0b0f14;
    --dc-muted: #667085;
    --dc-black: #060a11;
    --dc-green: #15803d;
    --dc-green-dark: #166534;
    --dc-green-soft: #f0fdf4;
    --dc-blue: #1d4ed8;
    --dc-danger: #b91c1c;
    --dc-warning: #92400e;
    --dc-purple: #6d28d9;
}

.dentist-dashboard-page {
    height: 100dvh;
    max-height: 100dvh;
    overflow-y: auto;
    overflow-x: hidden;
    background: var(--dc-bg);
    color: var(--dc-text);
    box-sizing: border-box;
    scrollbar-gutter: stable;
}

.dentist-dashboard-page::-webkit-scrollbar {
    width: 8px;
}

.dentist-dashboard-page::-webkit-scrollbar-track {
    background: var(--dc-bg);
}

.dentist-dashboard-page::-webkit-scrollbar-thumb {
    background: #c7c7c7;
    border-radius: 999px;
}

.dentist-dashboard-page::-webkit-scrollbar-thumb:hover {
    background: var(--dc-black);
}

/* Top section */
.dashboard-top {
    background: #ffffff;
    padding: 18px 22px 0;
    box-sizing: border-box;
}

.dashboard-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.hero-kicker {
    margin: 0 0 5px;
    color: var(--dc-green);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.hero-title {
    margin: 0;
    color: var(--dc-text);
    font-size: 22px;
    font-weight: 900;
    line-height: 1.2;
}

.hero-subtitle {
    margin: 4px 0 0;
    color: var(--dc-muted);
    font-size: 13px;
    line-height: 1.5;
}

.hero-date {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 0 12px;
    background: var(--dc-black);
    color: #ffffff;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

/* Stats */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    padding-bottom: 18px;
}

.stat-card {
    position: relative;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 12px;
    align-items: center;
    min-height: 84px;
    background: #ffffff;
    border: 1px solid var(--dc-border);
    padding: 14px;
    overflow: hidden;
}

.stat-card::after {
    content: "";
    position: absolute;
    width: 80px;
    height: 80px;
    right: -35px;
    top: -35px;
    border-radius: 999px;
    background: rgba(21, 128, 61, 0.08);
}

.stat-icon {
    width: 40px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    position: relative;
    z-index: 1;
    overflow: hidden;
    background: var(--dc-black);
}

.stat-card.teal .stat-icon {
    background: var(--dc-green);
}

.stat-icon svg {
    width: 21px;
    height: 21px;
    stroke: currentColor;
    stroke-width: 2;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.stat-icon .icon-pulse {
    animation: iconPulse 1.8s ease-in-out infinite;
    transform-origin: center;
}

.stat-icon .icon-float {
    animation: iconFloat 2.4s ease-in-out infinite;
}

.stat-icon .icon-draw {
    stroke-dasharray: 42;
    stroke-dashoffset: 42;
    animation: iconDraw 2.2s ease-in-out infinite;
}

.stat-icon .icon-spin-soft {
    animation: iconSpinSoft 3.5s linear infinite;
    transform-origin: center;
}

@keyframes iconPulse {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }

    50% {
        transform: scale(1.12);
        opacity: 0.82;
    }
}

@keyframes iconFloat {
    0%, 100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-3px);
    }
}

@keyframes iconDraw {
    0% {
        stroke-dashoffset: 42;
        opacity: 0.55;
    }

    45%, 70% {
        stroke-dashoffset: 0;
        opacity: 1;
    }

    100% {
        stroke-dashoffset: -42;
        opacity: 0.55;
    }
}

@keyframes iconSpinSoft {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.stat-body {
    position: relative;
    z-index: 1;
    min-width: 0;
}

.stat-value {
    margin: 0;
    color: var(--dc-text);
    font-size: 22px;
    font-weight: 900;
    line-height: 1;
}

.stat-label {
    margin: 5px 0 0;
    color: var(--dc-muted);
    font-size: 12px;
    font-weight: 700;
    line-height: 1.35;
}

/* Main content */
.dashboard-content {
    padding: 18px 22px 28px;
    box-sizing: border-box;
}

.dashboard-panel {
    width: 100%;
    background: #ffffff;
    padding: 14px;
    box-sizing: border-box;
}

.dashboard-tabs {
    display: flex;
    align-items: center;
    gap: 24px;
    background: #ffffff;
    overflow-x: auto;
    scrollbar-width: none;
    border-bottom: none;
}

.dashboard-tabs::-webkit-scrollbar {
    display: none;
}

.dashboard-tab {
    position: relative;
    display: inline-flex;
    align-items: center;
    min-height: 38px;
    padding: 0 0 11px;
    color: var(--dc-muted);
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.dashboard-tab:hover {
    color: var(--dc-text);
}

.dashboard-tab.active {
    color: var(--dc-text);
}

.dashboard-tab.active::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 3px;
    background: var(--dc-green);
    border-radius: 999px;
}

.dashboard-panel-header {
    padding: 14px 0 12px;
    background: #ffffff;
}

.dashboard-section-title {
    margin: 0;
    color: var(--dc-text);
    font-size: 16px;
    font-weight: 900;
    line-height: 1.3;
}

.dashboard-section-subtitle {
    margin: 4px 0 0;
    color: var(--dc-muted);
    font-size: 12px;
    line-height: 1.5;
}

/* Appointment table-style list */
.appointment-table {
    width: 100%;
    background: #ffffff;
    overflow-x: auto;
}

.appointment-table-header,
.appointment-card {
    display: grid;
    grid-template-columns:
        minmax(220px, 1.3fr)
        minmax(140px, 0.8fr)
        minmax(170px, 0.9fr)
        minmax(120px, 0.7fr)
        42px;
    gap: 12px;
    align-items: center;
}

.appointment-table-header {
    min-height: 36px;
    padding: 0 12px;
    background: var(--dc-soft);
    color: var(--dc-muted);
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.appointment-list {
    display: grid;
    gap: 0;
}

.appointment-card {
    min-height: 54px;
    padding: 9px 12px;
    background: #ffffff;
    color: var(--dc-text);
    border-bottom: 1px solid #edf2f7;
    transition: background 0.18s ease;
}

.appointment-card:last-child {
    border-bottom: none;
}

.appointment-card:hover {
    background: var(--dc-green-soft);
}

.patient-block {
    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
}

.patient-avatar {
    width: 32px;
    height: 32px;
    flex: 0 0 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--dc-black);
    color: #ffffff;
    font-size: 12px;
    font-weight: 900;
}

.appointment-card:hover .patient-avatar {
    background: var(--dc-green);
}

.patient-name,
.service-name {
    margin: 0;
    color: var(--dc-text);
    font-size: 13px;
    font-weight: 850;
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sub-text {
    margin: 2px 0 0;
    color: var(--dc-muted);
    font-size: 12px;
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.schedule-date {
    margin: 0;
    color: var(--dc-text);
    font-size: 13px;
    font-weight: 850;
    line-height: 1.35;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 24px;
    padding: 0 9px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 850;
    white-space: nowrap;
    text-transform: capitalize;
}

.badge-completed {
    background: #dbeafe;
    color: var(--dc-blue);
}

.badge-rescheduled {
    background: #fef3c7;
    color: var(--dc-warning);
}

.badge-cancelled {
    background: #fee2e2;
    color: var(--dc-danger);
}

.badge-confirmed {
    background: #dcfce7;
    color: var(--dc-green-dark);
}

.badge-checked {
    background: #e0f2fe;
    color: #0369a1;
}

.badge-progress {
    background: #ede9fe;
    color: var(--dc-purple);
}

.badge-default {
    background: #f1f5f9;
    color: #475569;
}

.appointment-action {
    display: flex;
    justify-content: flex-end;
}

.view-link {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    color: var(--dc-black);
    text-decoration: none;
    border-radius: 999px;
}

.view-link svg {
    width: 15px;
    height: 15px;
    stroke: currentColor;
    stroke-width: 2.2;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.view-link:hover {
    background: var(--dc-black);
    color: #ffffff;
}

.dashboard-empty {
    padding: 34px 18px;
    text-align: center;
    color: var(--dc-muted);
    font-size: 14px;
    line-height: 1.6;
    background: var(--dc-soft);
}

@media (max-width: 1180px) {
    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .appointment-table {
        overflow-x: auto;
    }

    .appointment-table-header,
    .appointment-card {
        min-width: 900px;
    }
}

@media (max-width: 760px) {
    .dashboard-top {
        padding: 14px 14px 0;
    }

    .dashboard-hero {
        display: grid;
        grid-template-columns: 1fr;
    }

    .hero-date {
        justify-content: flex-start;
        width: fit-content;
    }

    .dashboard-content {
        padding: 14px;
    }

    .dashboard-panel {
        padding: 12px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="dentist-dashboard-page">

    <div class="dashboard-top">
        <section class="dashboard-hero">
            <div>
                <p class="hero-kicker">Dentist Workspace</p>
                <h1 class="hero-title">Hello, Welcome back, Dr. <?= e($displayName) ?></h1>
                <p class="hero-subtitle">Your assigned appointments and clinic workflow overview.</p>
            </div>

            <div class="hero-date">
                <?= e(date('l, F d, Y')) ?>
            </div>
        </section>

        <section class="stats-grid">
            <div class="stat-card dark">
                <div class="stat-icon">
                    <svg class="icon-pulse" viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="17" rx="3"></rect>
                        <path d="M8 2v4"></path>
                        <path d="M16 2v4"></path>
                        <path d="M3 9h18"></path>
                        <path d="M8 14h3"></path>
                        <path d="M14 14h2"></path>
                        <path d="M8 17h2"></path>
                    </svg>
                </div>

                <div class="stat-body">
                    <p class="stat-value"><?= (int) ($stats['today_appointments'] ?? 0) ?></p>
                    <p class="stat-label">Today's Appointments</p>
                </div>
            </div>

            <div class="stat-card gray">
                <div class="stat-icon">
                    <svg class="icon-draw" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M8 12.5l2.5 2.5L16.5 9"></path>
                    </svg>
                </div>

                <div class="stat-body">
                    <p class="stat-value"><?= (int) ($stats['completed_today'] ?? 0) ?></p>
                    <p class="stat-label">Completed Today</p>
                </div>
            </div>

            <div class="stat-card teal">
                <div class="stat-icon">
                    <svg class="icon-float" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="10" cy="7" r="4"></circle>
                        <path d="M20 21v-2a3 3 0 0 0-2-2.83"></path>
                        <path d="M16.5 3.3a4 4 0 0 1 0 7.4"></path>
                    </svg>
                </div>

                <div class="stat-body">
                    <p class="stat-value"><?= (int) ($stats['my_patients'] ?? 0) ?></p>
                    <p class="stat-label">My Patients</p>
                </div>
            </div>

            <div class="stat-card blue">
                <div class="stat-icon">
                    <svg class="icon-spin-soft" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M21 12a9 9 0 1 1-2.64-6.36"></path>
                        <path d="M21 3v6h-6"></path>
                        <path d="M12 7v5l3 2"></path>
                    </svg>
                </div>

                <div class="stat-body">
                    <p class="stat-value"><?= (int) ($stats['followup_queue'] ?? 0) ?></p>
                    <p class="stat-label">Follow-up Queue</p>
                </div>
            </div>
        </section>
    </div>

    <div class="dashboard-content">
        <section class="dashboard-panel">
            <div class="dashboard-tabs">
                <?php foreach ($tabs as $key => $tab): ?>
                    <a href="?tab=<?= e($key) ?>" class="dashboard-tab <?= $activeTab === $key ? 'active' : '' ?>">
                        <?= e($tab['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="dashboard-panel-header">
                <div>
                    <h2 class="dashboard-section-title"><?= e($activeTitle) ?></h2>
                    <p class="dashboard-section-subtitle">
                        Confirmed and assigned appointments from the clinic appointment workflow.
                    </p>
                </div>
            </div>

            <?php if (empty($activeRows)): ?>
                <div class="dashboard-empty">
                    No appointment records found for this section.
                </div>
            <?php else: ?>
                <div class="appointment-table">
                    <div class="appointment-table-header">
                        <span>Patient</span>
                        <span>Schedule</span>
                        <span>Service</span>
                        <span>Status</span>
                        <span></span>
                    </div>

                    <div class="appointment-list">
                        <?php foreach ($activeRows as $row): ?>
                            <?php
                                $patientFullName = trim((string) (($row['patient_first_name'] ?? '') . ' ' . ($row['patient_last_name'] ?? '')));
                                $dentistFullName = trim((string) (($row['dentist_first_name'] ?? '') . ' ' . ($row['dentist_last_name'] ?? '')));
                                $status = (string) ($row['status'] ?? '');
                                $date = (string) ($row['appointment_date'] ?? '');
                                $start = (string) ($row['start_time'] ?? '');
                                $end = (string) ($row['end_time'] ?? '');
                                $code = (string) ($row['appointment_code'] ?? '');
                                $serviceName = (string) ($row['service_name'] ?? 'N/A');
                                $appointmentId = (int) ($row['appointment_id'] ?? 0);

                                $safePatientName = $patientFullName !== '' ? $patientFullName : 'Unknown Patient';
                                $initials = strtoupper(substr($safePatientName, 0, 1));
                            ?>

                            <article class="appointment-card">
                                <div class="patient-block">
                                    <div class="patient-avatar"><?= e($initials) ?></div>
                                    <div>
                                        <p class="patient-name"><?= e($safePatientName) ?></p>
                                        <p class="sub-text"><?= e($code !== '' ? $code : 'No appointment code') ?></p>
                                    </div>
                                </div>

                                <div>
                                    <p class="schedule-date"><?= e(niceDate($date)) ?></p>
                                    <p class="sub-text">
                                        <?= e(niceTime($start)) ?>
                                        <?= $end !== '' ? ' - ' . e(niceTime($end)) : '' ?>
                                    </p>
                                </div>

                                <div>
                                    <p class="service-name"><?= e($serviceName) ?></p>
                                    <p class="sub-text">
                                        <?= e($dentistFullName !== '' ? ('Dr. ' . $dentistFullName) : 'Assigned Dentist') ?>
                                    </p>
                                </div>

                                <div>
                                    <span class="status-badge <?= badgeClass($status) ?>">
                                        <?= e(str_replace('_', ' ', $status !== '' ? $status : 'N/A')) ?>
                                    </span>
                                </div>

                                <div class="appointment-action">
                                    <?php if ($appointmentId > 0): ?>
                                        <a class="view-link" href="/DentalClinic/public/dentist/clinical-record?appointment_id=<?= (int) $appointmentId ?>" aria-label="Open appointment">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M9 6l6 6-6 6"></path>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/app.php';
?>