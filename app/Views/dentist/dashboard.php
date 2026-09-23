<?php
use App\Core\Auth;

$pageTitle = $pageTitle ?? 'Dentist Dashboard';

$stats = $stats ?? [
    'today_appointments' => 0,
    'completed_today'    => 0,
    'my_patients'        => 0,
    'followup_queue'     => 0,
];

$todayAppointments     = isset($todayAppointments)     && is_array($todayAppointments)     ? $todayAppointments     : [];
$upcomingAppointments  = isset($upcomingAppointments)  && is_array($upcomingAppointments)  ? $upcomingAppointments  : [];
$completedAppointments = isset($completedAppointments) && is_array($completedAppointments) ? $completedAppointments : [];

$authUser    = Auth::user();
$displayName = trim((string)(($authUser['first_name'] ?? '') . ' ' . ($authUser['last_name'] ?? '')));
if ($displayName === '') {
    $displayName = 'Dentist';
}

$nameParts = explode(' ', $displayName);
$initials  = strtoupper(substr($nameParts[0] ?? 'D', 0, 1) . substr($nameParts[count($nameParts) - 1] ?? '', 0, 1));

$baseUrl = '/DentalClinic/public';

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('dashboardDate')) {
    function dashboardDate(?string $date): string
    {
        if (!$date) {
            return '—';
        }

        $time = strtotime($date);

        return $time ? date('M d, Y', $time) : e($date);
    }
}

if (!function_exists('dashboardTime')) {
    function dashboardTime(?string $time): string
    {
        if (!$time) {
            return '';
        }

        $parsed = strtotime($time);

        return $parsed ? date('h:i A', $parsed) : e($time);
    }
}

if (!function_exists('dashboardFullName')) {
    function dashboardFullName(array $row, string $prefix = 'patient'): string
    {
        $name = trim((string)(
            ($row[$prefix . '_first_name']  ?? '') . ' ' .
            ($row[$prefix . '_middle_name'] ?? '') . ' ' .
            ($row[$prefix . '_last_name']   ?? '')
        ));

        return $name !== '' ? $name : 'Unknown Patient';
    }
}

if (!function_exists('patientInitials')) {
    function patientInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = strtoupper(substr($parts[0] ?? '?', 0, 1));
        $last  = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));

        return trim($first . $last) !== '' ? $first . $last : '??';
    }
}

if (!function_exists('statusClass')) {
    function statusClass(string $status): string
    {
        return match (strtolower(trim($status))) {
            'confirmed'                     => 'b-teal',
            'checked_in', 'checked in'      => 'b-amber',
            'in_progress', 'in progress'    => 'b-purple',
            'completed'                     => 'b-blue',
            'rescheduled'                   => 'b-amber',
            'cancelled', 'rejected',
            'no_show', 'no show'            => 'b-rose',
            default                         => 'b-gray',
        };
    }
}

if (!function_exists('avatarColor')) {
    function avatarColor(string $name): string
    {
        $colors = ['#0f766e', '#1d4ed8', '#6d28d9', '#be185d', '#b45309', '#0e7490'];

        return $colors[abs(crc32($name)) % count($colors)];
    }
}

if (!function_exists('dashboardIcon')) {
    function dashboardIcon(string $name): string
    {
        $icons = [
            'tooth' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8.5 3.5c1.5 0 2.1.8 3.5.8s2-.8 3.5-.8c2.5 0 4 2.1 4 4.5 0 1.7-.7 3.1-1.3 4.4-.7 1.5-.9 3.2-1.2 4.7-.3 1.8-.9 3.4-2.2 3.4-1.1 0-1.3-1.2-1.7-3-.3-1.2-.5-2.3-1.1-2.3s-.8 1.1-1.1 2.3c-.4 1.8-.6 3-1.7 3-1.3 0-1.9-1.6-2.2-3.4-.3-1.5-.5-3.2-1.2-4.7C5.7 11.1 5 9.7 5 8c0-2.4 1.5-4.5 3.5-4.5z"/></svg>',

            'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 3v3M17 3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"/></svg>',

            'check' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg>',

            'users' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',

            'schedule' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 2v4M16 2v4M3 10h18"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>',

            'upcoming' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/><path d="M5 5v14"/></svg>',

            'award' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="5"/><path d="m8.5 12.5-2 8 5.5-3 5.5 3-2-8"/></svg>',
        ];

        return $icons[$name] ?? $icons['tooth'];
    }
}

$hour = (int)date('G');
$timeGreet = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

$todayJson     = json_encode($todayAppointments,     $jsonFlags) ?: '[]';
$upcomingJson  = json_encode($upcomingAppointments,  $jsonFlags) ?: '[]';
$completedJson = json_encode($completedAppointments, $jsonFlags) ?: '[]';

ob_start();
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<style>
.ddb,
.ddb * {
    box-sizing: border-box;
}

.ddb {
    --ink: #111827;
    --text: #374151;
    --muted: #6b7280;
    --faint: #9ca3af;

    --page: #fcffff52;
    --card: #ffffff;
    --soft: #f9fafb;
    --soft-2: #f3f4f6;
    --line: #d8dde5;
    --line-soft: #edf0f4;

    --cyan: #2698a0;
    --cyan-dark: #1b9090;

    --teal: #0f766e;
    --teal-soft: #e8f6f3;

    --amber: #b7791f;
    --amber-soft: #fff5df;

    --blue: #2563a9;
    --blue-soft: #eef5ff;

    --rose: #be185d;
    --rose-soft: #fff0f5;

    --purple: #6d28d9;
    --purple-soft: #f3efff;

    --radius: 12px;
    --radius-sm: 9px;
    --shadow: 0 2px 10px rgba(15, 23, 42, .07);
    --pricing-shadow: 0 4px 8px rgba(31, 41, 55, .25);

    min-height: 100%;
    padding: 22px;
    background: var(--page);
    color: var(--ink);
    font-family: 'DM Sans', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
}

.ddb-page {
    width: 100%;
    max-width: 1280px;
    margin: 0 auto;
}

.ddb-page-head {
    margin-bottom: 18px;
}

.ddb-title-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
}

.ddb-title {
    font-size: 28px;
    line-height: 1.15;
    letter-spacing: -.035em;
    font-weight: 700;
    margin: 0;
    color: var(--ink);
}

.ddb-subtitle {
    color: var(--muted);
    font-size: 14px;
    line-height: 1.6;
    margin-top: 6px;
}

.ddb-date-card {
    min-width: 132px;
    border: 1px solid rgba(15, 23, 42, .08);
    background: #ffffff;
    color: var(--ink);
    padding: 11px 14px;
    text-align: center;
    border-radius: var(--radius);
    box-shadow: var(--pricing-shadow);
}

.ddb-date-day {
    display: block;
    font-family: 'DM Mono', monospace;
    font-size: 27px;
    line-height: 1;
    font-weight: 600;
    color: var(--cyan);
}

.ddb-date-month {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    font-weight: 700;
    color: var(--text);
    letter-spacing: .04em;
}

.ddb-date-weekday {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    color: var(--muted);
}

/* Icons */
.ddb-stat-icon,
.ddb-panel-icon,
.ddb-title-icon,
.ddb-empty-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: currentColor;
}

.ddb-stat-icon svg,
.ddb-panel-icon svg,
.ddb-title-icon svg,
.ddb-empty-icon svg {
    width: 100%;
    height: 100%;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.85;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.ddb-title-icon {
    width: 22px;
    height: 22px;
    color: var(--cyan);
    margin-left: 6px;
    vertical-align: -4px;
}


.ddb-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 50px;
    margin-bottom: 20px;
    padding: 8px 0px 12px;
}

.ddb-stat {
    min-height: 150px;
    padding: 30px 24px 26px;
    border-radius: 10px;
    box-shadow: var(--pricing-shadow);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;

    position: relative;
    overflow: hidden;

    border: 1px solid rgba(15, 23, 42, .08);
    background: #ffffff;
    text-align: center;
}

.ddb-stat-top {
    display: contents;
}

.ddb-stat-icon {
    order: 1;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 0;
    background: transparent;
    color: var(--cyan);
    margin-bottom: 8px;
}

.ddb-stat-icon svg {
    width: 24px;
    height: 24px;
    stroke-width: 2;
}

.ddb-stat-value {
    order: 2;
    font-family: 'DM Sans', system-ui, sans-serif;
    font-size: 44px;
    line-height: 1;
    font-weight: 800;
    letter-spacing: -.035em;
    color: var(--cyan);
    margin-bottom: 15px;
}

.ddb-stat-label {
    order: 3;
    font-size: 18px;
    line-height: 1.35;
    font-weight: 700;
    color: #4b5f78;
    text-transform: none;
    letter-spacing: 0;
    max-width: 220px;
    margin-bottom: 4px;
}

.ddb-stat-link {
    order: 4;
    margin-top: 8px;
    display: inline-flex;
    width: fit-content;
    color: #40506a;
    text-decoration: none;
    font-size: 15px;
    line-height: 1.35;
    font-weight: 500;
}

.ddb-stat-link:hover {
    color: var(--cyan-dark);
    text-decoration: underline;
}

/* Main grid */
.ddb-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 12px;
}

.ddb-panel {
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.ddb-panel.full {
    grid-column: 1 / -1;
}

.ddb-panel-head {
    min-height: 56px;
    padding: 12px 15px;
    border-bottom: 1px solid var(--line);
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.ddb-panel-head-left {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.ddb-panel-icon {
    width: 31px;
    height: 31px;
    border: 1px solid var(--line);
    background: var(--soft);
    color: var(--muted);
    border-radius: var(--radius-sm);
    flex-shrink: 0;
}

.ddb-panel-icon svg {
    width: 16px;
    height: 16px;
}

.ddb-panel-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--ink);
}

.ddb-view-all {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--line);
    background: #ffffff;
    color: var(--text);
    height: 31px;
    padding: 0 10px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
}

.ddb-view-all:hover {
    background: var(--soft);
    color: var(--teal);
}

/* Timeline */
.ddb-tl-row {
    display: flex;
    align-items: stretch;
    min-height: 68px;
    border-bottom: 1px solid var(--line-soft);
    background: #ffffff;
}

.ddb-tl-row:last-child {
    border-bottom: 0;
}

.ddb-tl-row:hover {
    background: #fbfbfc;
}

.ddb-tl-time {
    width: 78px;
    flex-shrink: 0;
    padding: 12px 8px;
    background: #fafafa;
    border-right: 1px solid var(--line-soft);
    color: var(--muted);
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    line-height: 1.35;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.ddb-tl-body {
    flex: 1;
    min-width: 0;
    padding: 13px 14px;
}

.ddb-tl-name {
    color: var(--ink);
    font-size: 13.5px;
    font-weight: 700;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.ddb-tl-svc {
    color: var(--muted);
    font-size: 12px;
    margin-top: 4px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.ddb-tl-side {
    display: flex;
    align-items: center;
    padding: 0 10px;
    flex-shrink: 0;
}

.ddb-tl-open {
    border-left: 1px solid var(--line-soft);
    background: #ffffff;
    color: var(--text);
    padding: 0 13px;
    display: flex;
    align-items: center;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.ddb-tl-open:hover {
    background: var(--soft);
    color: var(--teal);
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 23px;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
    white-space: nowrap;
}

.badge-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    flex-shrink: 0;
}

.b-teal { background: var(--teal-soft); color: var(--teal); }
.b-teal .badge-dot { background: var(--teal); }

.b-amber { background: var(--amber-soft); color: var(--amber); }
.b-amber .badge-dot { background: var(--amber); }

.b-blue { background: var(--blue-soft); color: var(--blue); }
.b-blue .badge-dot { background: var(--blue); }

.b-rose { background: var(--rose-soft); color: var(--rose); }
.b-rose .badge-dot { background: var(--rose); }

.b-purple { background: var(--purple-soft); color: var(--purple); }
.b-purple .badge-dot { background: var(--purple); }

.b-gray { background: #f1f5f9; color: var(--muted); }
.b-gray .badge-dot { background: var(--faint); }

/* Table */
.ddb-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
}

.ddb-table th {
    text-align: left;
    background: #fafafa;
    color: var(--muted);
    border-bottom: 1px solid var(--line);
    padding: 10px 14px;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
}

.ddb-table td {
    border-bottom: 1px solid var(--line-soft);
    padding: 11px 14px;
    color: var(--text);
    vertical-align: middle;
}

.ddb-table tbody tr:last-child td {
    border-bottom: 0;
}

.ddb-table tbody tr:hover {
    background: #fbfbfc;
}

.ddb-av {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 8px;
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .03em;
    vertical-align: middle;
}

.ddb-pt-name {
    display: inline-block;
    vertical-align: middle;
    color: var(--ink);
    font-weight: 700;
    font-size: 13px;
}

.ddb-pt-code {
    padding-left: 36px;
    margin-top: 2px;
    color: var(--muted);
    font-family: 'DM Mono', monospace;
    font-size: 10px;
}

.ddb-mono {
    font-family: 'DM Mono', monospace;
    color: var(--text);
    font-size: 11px;
    line-height: 1.55;
}

.ddb-open {
    color: var(--text);
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.ddb-open:hover {
    color: var(--teal);
    text-decoration: underline;
}

/* Pager */
.ddb-pager {
    min-height: 45px;
    padding: 8px 14px;
    border-top: 1px solid var(--line);
    background: #fafafa;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.ddb-pager-info {
    color: var(--muted);
    font-family: 'DM Mono', monospace;
    font-size: 11px;
}

.ddb-pager-btns {
    display: flex;
    align-items: center;
    gap: 4px;
}

.ddb-pager-btn {
    height: 28px;
    min-width: 29px;
    border: 1px solid var(--line);
    background: #ffffff;
    color: var(--text);
    border-radius: var(--radius-sm);
    padding: 0 8px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.ddb-pager-btn:hover:not(:disabled) {
    background: var(--soft);
}

.ddb-pager-btn:disabled {
    opacity: .45;
    cursor: default;
}

.ddb-pager-btn.active {
    background: var(--ink);
    border-color: var(--ink);
    color: #ffffff;
}

/* Empty state */
.ddb-empty {
    min-height: 144px;
    padding: 32px 18px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
    font-weight: 600;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.ddb-empty-icon {
    width: 34px;
    height: 34px;
    margin-bottom: 9px;
    color: var(--faint);
}

/* Responsive */
@media (max-width: 1180px) {
    .ddb-stats {
        gap: 28px;
        padding-left: 24px;
        padding-right: 24px;
    }
}

@media (max-width: 1024px) {
    .ddb-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        padding: 6px 0 10px;
    }
}

@media (max-width: 860px) {
    .ddb-grid {
        grid-template-columns: 1fr;
    }

    .ddb-panel.full {
        grid-column: span 1;
    }

    .ddb-stats {
        grid-template-columns: 1fr;
        max-width: 420px;
        margin-left: auto;
        margin-right: auto;
    }
}

@media (max-width: 640px) {
    .ddb {
        padding: 14px;
    }

    .ddb-title-row {
        flex-direction: column;
    }

    .ddb-date-card {
        width: 100%;
        text-align: center;
    }

    .ddb-tl-row {
        flex-wrap: wrap;
    }

    .ddb-tl-time {
        width: 100%;
        justify-content: flex-start;
        border-right: 0;
        border-bottom: 1px solid var(--line-soft);
        padding: 9px 14px;
    }

    .ddb-tl-side {
        padding: 10px 14px;
    }

    .ddb-tl-open {
        width: 100%;
        border-left: 0;
        border-top: 1px solid var(--line-soft);
        padding: 10px 14px;
    }

    .ddb-table {
        min-width: 720px;
    }

    #ddb-upcoming-body,
    #ddb-completed-body {
        overflow-x: auto;
    }
}
</style>

<div class="ddb">
    <div class="ddb-page">

        <header class="ddb-page-head">
            <div class="ddb-title-row">
                <div>
                    <h1 class="ddb-title">
                        Good <?= e($timeGreet) ?>, Dr. <?= e($displayName) ?>
                        <span class="ddb-title-icon"><?= dashboardIcon('tooth') ?></span>
                    </h1>

                    <div class="ddb-subtitle">
                        <?php if ((int)($stats['today_appointments'] ?? 0) > 0): ?>
                            You have <?= (int)$stats['today_appointments'] ?> appointment<?= (int)$stats['today_appointments'] !== 1 ? 's' : '' ?> today
                            <?php if ((int)($stats['completed_today'] ?? 0) > 0): ?>
                                · <?= (int)$stats['completed_today'] ?> completed
                            <?php endif; ?>
                        <?php else: ?>
                            No appointments scheduled for today.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="ddb-date-card">
                    <span class="ddb-date-day"><?= date('d') ?></span>
                    <span class="ddb-date-month"><?= date('M Y') ?></span>
                    <span class="ddb-date-weekday"><?= date('l') ?></span>
                </div>
            </div>
        </header>

        <section class="ddb-stats">
            <div class="ddb-stat s-teal">
                <div class="ddb-stat-top">
                    <div class="ddb-stat-label">Today's Appointments</div>
                    <div class="ddb-stat-icon"><?= dashboardIcon('calendar') ?></div>
                </div>

                <div class="ddb-stat-value"><?= (int)($stats['today_appointments'] ?? 0) ?></div>

                <a class="ddb-stat-link" href="<?= e($baseUrl . '/dentist/appointments?date=' . date('Y-m-d')) ?>">View schedule →</a>
            </div>

            <div class="ddb-stat s-amber">
                <div class="ddb-stat-top">
                    <div class="ddb-stat-label">Completed Today</div>
                    <div class="ddb-stat-icon"><?= dashboardIcon('check') ?></div>
                </div>

                <div class="ddb-stat-value"><?= (int)($stats['completed_today'] ?? 0) ?></div>

                <a class="ddb-stat-link" href="<?= e($baseUrl . '/dentist/appointments?status=completed') ?>">View completed →</a>
            </div>

            <div class="ddb-stat s-blue">
                <div class="ddb-stat-top">
                    <div class="ddb-stat-label">My Patients</div>
                    <div class="ddb-stat-icon"><?= dashboardIcon('users') ?></div>
                </div>

                <div class="ddb-stat-value"><?= (int)($stats['my_patients'] ?? 0) ?></div>

                <a class="ddb-stat-link" href="<?= e($baseUrl . '/dentist/patients') ?>">View patients →</a>
            </div>
        </section>

        <section class="ddb-grid">

            <div class="ddb-panel">
                <div class="ddb-panel-head">
                    <div class="ddb-panel-head-left">
                        <div class="ddb-panel-icon"><?= dashboardIcon('schedule') ?></div>
                        <span class="ddb-panel-title">Today's Schedule</span>
                    </div>

                    <a class="ddb-view-all" href="<?= e($baseUrl . '/dentist/appointments?date=' . date('Y-m-d')) ?>">View all</a>
                </div>

                <?php if (empty($todayAppointments)): ?>
                    <div class="ddb-empty">
                        <div class="ddb-empty-icon"><?= dashboardIcon('tooth') ?></div>
                        <div>No appointments assigned today.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($todayAppointments as $apt):
                        $aid     = (int)($apt['appointment_id'] ?? 0);
                        $pname   = dashboardFullName($apt, 'patient');
                        $status  = (string)($apt['status'] ?? 'pending');
                        $timeStr = dashboardTime((string)($apt['start_time'] ?? ''));
                    ?>
                        <div class="ddb-tl-row">
                            <div class="ddb-tl-time"><?= e($timeStr) ?></div>

                            <div class="ddb-tl-body">
                                <div class="ddb-tl-name"><?= e($pname) ?></div>
                                <div class="ddb-tl-svc">
                                    <?= e((string)($apt['service_name'] ?? 'N/A')) ?>
                                    &middot; <?= e((string)($apt['appointment_code'] ?? '')) ?>
                                </div>
                            </div>

                            <div class="ddb-tl-side">
                                <span class="badge <?= e(statusClass($status)) ?>">
                                    <span class="badge-dot"></span>
                                    <?= e(str_replace('_', ' ', $status)) ?>
                                </span>
                            </div>

                            <?php if ($aid > 0): ?>
                                <a class="ddb-tl-open" href="<?= e($baseUrl . '/dentist/clinical-record?appointment_id=' . $aid) ?>">Open ↗</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="ddb-panel">
                <div class="ddb-panel-head">
                    <div class="ddb-panel-head-left">
                        <div class="ddb-panel-icon"><?= dashboardIcon('upcoming') ?></div>
                        <span class="ddb-panel-title">Upcoming Appointments</span>
                    </div>

                    <a class="ddb-view-all" href="<?= e($baseUrl . '/dentist/appointments') ?>">View all</a>
                </div>

                <div id="ddb-upcoming-body"></div>
                <div class="ddb-pager" id="ddb-upcoming-pager"></div>
            </div>

            <div class="ddb-panel full">
                <div class="ddb-panel-head">
                    <div class="ddb-panel-head-left">
                        <div class="ddb-panel-icon"><?= dashboardIcon('award') ?></div>
                        <span class="ddb-panel-title">Recently Completed</span>
                    </div>

                    <a class="ddb-view-all" href="<?= e($baseUrl . '/dentist/appointments?status=completed') ?>">View all</a>
                </div>

                <div id="ddb-completed-body"></div>
                <div class="ddb-pager" id="ddb-completed-pager"></div>
            </div>

        </section>
    </div>
</div>

<script>
(function () {
    const UPCOMING  = <?= $upcomingJson ?>;
    const COMPLETED = <?= $completedJson ?>;
    const BASE      = <?= json_encode($baseUrl, $jsonFlags) ?>;

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function avatarColor(name) {
        const palette = ['#0f766e', '#1d4ed8', '#6d28d9', '#be185d', '#b45309', '#0e7490'];
        let h = 0;

        for (let c of (name || '')) {
            h = (Math.imul(31, h) + c.charCodeAt(0)) | 0;
        }

        return palette[Math.abs(h) % palette.length];
    }

    function patientInitials(name) {
        const p = (name || '').trim().split(/\s+/);
        return ((p[0]?.[0] ?? '?') + (p[p.length - 1]?.[0] ?? '')).toUpperCase();
    }

    function fullName(row, prefix = 'patient') {
        const n = [
            row[prefix + '_first_name']  ?? '',
            row[prefix + '_middle_name'] ?? '',
            row[prefix + '_last_name']   ?? '',
        ].join(' ').replace(/\s+/g, ' ').trim();

        return n || 'Unknown Patient';
    }

    function fmtDate(d) {
        if (!d) return '—';

        const t = new Date(d + (d.includes('T') ? '' : 'T00:00:00'));

        return isNaN(t)
            ? d
            : t.toLocaleDateString('en-US', {
                month: 'short',
                day: '2-digit',
                year: 'numeric'
            });
    }

    function fmtTime(t) {
        if (!t) return '';

        const d = new Date('1970-01-01T' + t);

        return isNaN(d)
            ? t
            : d.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
    }

    function statusBadge(s) {
        const map = {
            confirmed:   ['b-teal',   'confirmed'],
            checked_in:  ['b-amber',  'checked in'],
            in_progress: ['b-purple', 'in progress'],
            completed:   ['b-blue',   'completed'],
            rescheduled: ['b-amber',  'rescheduled'],
            cancelled:   ['b-rose',   'cancelled'],
            rejected:    ['b-rose',   'rejected'],
            no_show:     ['b-rose',   'no show'],
        };

        const [cls, lbl] = map[(s || '').toLowerCase()] ?? ['b-gray', (s || '').replace(/_/g, ' ')];

        return `<span class="badge ${cls}"><span class="badge-dot"></span>${esc(lbl)}</span>`;
    }

    function avatarCell(name) {
        return `<span class="ddb-av" style="background:${avatarColor(name)}">${esc(patientInitials(name))}</span>`;
    }

    function emptyState(message = 'No records found.') {
        return `
            <div class="ddb-empty">
                <div class="ddb-empty-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M8.5 3.5c1.5 0 2.1.8 3.5.8s2-.8 3.5-.8c2.5 0 4 2.1 4 4.5 0 1.7-.7 3.1-1.3 4.4-.7 1.5-.9 3.2-1.2 4.7-.3 1.8-.9 3.4-2.2 3.4-1.1 0-1.3-1.2-1.7-3-.3-1.2-.5-2.3-1.1-2.3s-.8 1.1-1.1 2.3c-.4 1.8-.6 3-1.7 3-1.3 0-1.9-1.6-2.2-3.4-.3-1.5-.5-3.2-1.2-4.7C5.7 11.1 5 9.7 5 8c0-2.4 1.5-4.5 3.5-4.5z"/>
                    </svg>
                </div>
                ${esc(message)}
            </div>`;
    }

    function makePager({ data, bodyId, pagerId, columns, rowFn, perPage = 4 }) {
        let page = 0;
        const total = Array.isArray(data) ? data.length : 0;
        const pages = Math.max(1, Math.ceil(total / perPage));

        function render() {
            const body  = document.getElementById(bodyId);
            const pager = document.getElementById(pagerId);

            if (!body || !pager) return;

            if (!total) {
                body.innerHTML = emptyState('No records found.');
                pager.style.display = 'none';
                return;
            }

            const slice = data.slice(page * perPage, (page + 1) * perPage);

            body.innerHTML = `
                <table class="ddb-table">
                    <thead>
                        <tr>${columns.map(c => `<th>${esc(c)}</th>`).join('')}</tr>
                    </thead>
                    <tbody>${slice.map(rowFn).join('')}</tbody>
                </table>`;

            const from = page * perPage + 1;
            const to   = Math.min((page + 1) * perPage, total);

            const pageBtns = Array.from({ length: pages }, (_, i) =>
                `<button type="button" class="ddb-pager-btn${i === page ? ' active' : ''}" data-pg="${i}">${i + 1}</button>`
            ).join('');

            pager.style.display = 'flex';
            pager.innerHTML = `
                <span class="ddb-pager-info">${from}–${to} of ${total}</span>
                <div class="ddb-pager-btns">
                    <button type="button" class="ddb-pager-btn" id="${pagerId}-prev" ${page === 0 ? 'disabled' : ''}>‹</button>
                    ${pageBtns}
                    <button type="button" class="ddb-pager-btn" id="${pagerId}-next" ${page >= pages - 1 ? 'disabled' : ''}>›</button>
                </div>`;

            const prevBtn = document.getElementById(`${pagerId}-prev`);
            const nextBtn = document.getElementById(`${pagerId}-next`);

            if (prevBtn) {
                prevBtn.onclick = () => {
                    if (page > 0) {
                        page--;
                        render();
                    }
                };
            }

            if (nextBtn) {
                nextBtn.onclick = () => {
                    if (page < pages - 1) {
                        page++;
                        render();
                    }
                };
            }

            pager.querySelectorAll('[data-pg]').forEach(btn => {
                btn.addEventListener('click', () => {
                    page = Number(btn.dataset.pg || 0);
                    render();
                });
            });
        }

        render();
    }

    makePager({
        data: UPCOMING,
        bodyId: 'ddb-upcoming-body',
        pagerId: 'ddb-upcoming-pager',
        columns: ['Patient', 'Service', 'Date & Time', 'Status'],
        perPage: 4,
        rowFn(apt) {
            const name   = fullName(apt, 'patient');
            const code   = apt.appointment_code ?? '';
            const svc    = apt.service_name ?? 'N/A';
            const date   = fmtDate(apt.appointment_date ?? '');
            const start  = fmtTime(apt.start_time ?? '');
            const end    = apt.end_time ? ' – ' + fmtTime(apt.end_time) : '';
            const status = apt.status ?? 'pending';

            return `<tr>
                <td>
                    ${avatarCell(name)}<span class="ddb-pt-name">${esc(name)}</span>
                    <div class="ddb-pt-code">${esc(code)}</div>
                </td>
                <td>${esc(svc)}</td>
                <td><span class="ddb-mono">${esc(date)}<br>${esc(start)}${esc(end)}</span></td>
                <td>${statusBadge(status)}</td>
            </tr>`;
        },
    });

    makePager({
        data: COMPLETED,
        bodyId: 'ddb-completed-body',
        pagerId: 'ddb-completed-pager',
        columns: ['Patient', 'Service', 'Date', 'Time', 'Status', 'Record'],
        perPage: 5,
        rowFn(apt) {
            const name   = fullName(apt, 'patient');
            const code   = apt.appointment_code ?? '';
            const svc    = apt.service_name ?? 'N/A';
            const date   = fmtDate(apt.appointment_date ?? '');
            const start  = fmtTime(apt.start_time ?? '');
            const end    = apt.end_time ? ' – ' + fmtTime(apt.end_time) : '';
            const status = apt.status ?? 'completed';
            const aid    = parseInt(apt.appointment_id ?? 0, 10);

            const recordLink = aid > 0
                ? `<a class="ddb-open" href="${esc(BASE + '/dentist/clinical-record?appointment_id=' + aid)}">Open ↗</a>`
                : `<span style="color:var(--faint)">—</span>`;

            return `<tr>
                <td>
                    ${avatarCell(name)}<span class="ddb-pt-name">${esc(name)}</span>
                    <div class="ddb-pt-code">${esc(code)}</div>
                </td>
                <td>${esc(svc)}</td>
                <td><span class="ddb-mono">${esc(date)}</span></td>
                <td><span class="ddb-mono">${esc(start)}${esc(end)}</span></td>
                <td>${statusBadge(status)}</td>
                <td>${recordLink}</td>
            </tr>`;
        },
    });
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/app.php';
?>