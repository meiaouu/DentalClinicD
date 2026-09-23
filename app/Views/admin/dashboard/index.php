<?php
$pageTitle = 'Owner/Admin Dashboard';

$baseUrl = '/DentalClinic/public';

$stats = isset($stats) && is_array($stats) ? $stats : [];
$recentAppointments = isset($recentAppointments) && is_array($recentAppointments) ? $recentAppointments : [];
$recentAuditLogs = isset($recentAuditLogs) && is_array($recentAuditLogs) ? $recentAuditLogs : [];

$flash_success = $flash_success ?? null;
$flash_error   = $flash_error   ?? null;

if (!function_exists('e')) {
    function e(?string $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('adminDashDate')) {
    function adminDashDate(?string $d): string {
        $d = trim((string) $d);
        if ($d === '') return '—';
        $ts = strtotime($d);
        return $ts ? date('M d, Y', $ts) : $d;
    }
}
if (!function_exists('adminDashShortDate')) {
    function adminDashShortDate(?string $d): string {
        $d = trim((string) $d);
        if ($d === '') return '—';
        $ts = strtotime($d);
        return $ts ? date('M d', $ts) : $d;
    }
}
if (!function_exists('adminDashTime')) {
    function adminDashTime(?string $t): string {
        $t = trim((string) $t);
        if ($t === '') return '';
        $ts = strtotime($t);
        return $ts ? date('h:i A', $ts) : $t;
    }
}
if (!function_exists('adminDashTimeRange')) {
    function adminDashTimeRange(?string $s, ?string $e): string {
        $st = adminDashTime($s);
        $et = adminDashTime($e);
        if ($st === '') return '—';
        return $et !== '' ? $st . ' – ' . $et : $st;
    }
}
if (!function_exists('adminDashStatusClass')) {
    function adminDashStatusClass(?string $status): string {
        return match(strtolower(trim((string) $status))) {
            'confirmed','checked_in','in_progress' => 'active',
            'completed'                             => 'completed',
            'pending','rescheduled'                 => 'pending',
            'cancelled','rejected','no_show'        => 'danger',
            default                                 => 'neutral',
        };
    }
}
if (!function_exists('adminDashStatusText')) {
    function adminDashStatusText(?string $status): string {
        $status = trim((string) $status);
        return $status === '' ? 'Unknown' : ucwords(str_replace('_', ' ', $status));
    }
}
if (!function_exists('adminDashInitial')) {
    function adminDashInitial(?string $name): string {
        $name = trim((string) $name);
        return $name === '' ? 'P' : strtoupper(substr($name, 0, 1));
    }
}
if (!function_exists('adminMoney')) {
    function adminMoney(float $v): string {
        return '₱' . number_format($v, 2);
    }
}

$totalPatients      = (int)   ($stats['total_patients']      ?? 0);
$totalDentists      = (int)   ($stats['total_dentists']      ?? 0);
$totalStaff         = (int)   ($stats['total_staff']         ?? 0);
$todayAppointments  = (int)   ($stats['today_appointments']  ?? 0);
$pendingRequests    = (int)   ($stats['pending_requests']    ?? 0);
$activeServices     = (int)   ($stats['active_services']     ?? 0);
$totalOperations    = (int)   ($stats['total_operations']    ?? $stats['completed_appointments'] ?? 0);
$totalIncome        = (float) ($stats['total_income']        ?? $stats['total_earning'] ?? $stats['earning'] ?? $stats['income'] ?? 0);

if ($totalOperations <= 0) {
    foreach ($recentAppointments as $a) {
        if (strtolower((string)($a['status'] ?? '')) === 'completed') $totalOperations++;
    }
}
if ($totalIncome <= 0) {
    foreach ($recentAppointments as $a) {
        $totalIncome += (float)($a['estimated_price'] ?? $a['actual_charge'] ?? $a['amount_paid'] ?? 0);
    }
}

/* Chart data */
$monthlyAppointments = array_fill(1, 12, 0);
$monthlyCompleted    = array_fill(1, 12, 0);
foreach ($recentAppointments as $a) {
    $d = trim((string)($a['appointment_date'] ?? ''));
    if ($d === '') continue;
    $ts = strtotime($d);
    if (!$ts) continue;
    $m = (int)date('n', $ts);
    $monthlyAppointments[$m]++;
    if (strtolower((string)($a['status'] ?? '')) === 'completed') $monthlyCompleted[$m]++;
}
$chartMax         = max(1, max($monthlyAppointments), max($monthlyCompleted));
$chartW           = 700; $chartH = 220;
$chartPX          = 30;  $chartPY = 20;
$chartIW          = $chartW - ($chartPX * 2);
$chartIH          = $chartH - ($chartPY * 2);
$ptsAppt = []; $ptsDone = [];
for ($m = 1; $m <= 12; $m++) {
    $x   = $chartPX + (($m - 1) * ($chartIW / 11));
    $yA  = $chartPY + ($chartIH - (($monthlyAppointments[$m] / $chartMax) * $chartIH));
    $yD  = $chartPY + ($chartIH - (($monthlyCompleted[$m]    / $chartMax) * $chartIH));
    $ptsAppt[] = round($x, 2) . ',' . round($yA, 2);
    $ptsDone[]  = round($x, 2) . ',' . round($yD, 2);
}
$ptsApptStr = implode(' ', $ptsAppt);
$ptsDoneStr = implode(' ', $ptsDone);

/* Donut */
$completedCount = 0;
foreach ($recentAppointments as $a) {
    if (strtolower((string)($a['status'] ?? '')) === 'completed') $completedCount++;
}
$donutPercent = !empty($recentAppointments)
    ? (int)round(($completedCount / max(1, count($recentAppointments))) * 100)
    : 0;
$donutPercent = max(0, min(100, $donutPercent));
$donutOffset  = 314 - (314 * ($donutPercent / 100));

ob_start();
?>

<style>


:root {
    --teal:        #0f766e;
    --teal-2:      #14b8a6;
    --teal-light:  #e6f9f7;
    --teal-mid:    #99e6df;

    --bg:          #ffffff;
    --surface:     #ffffff;
    --surface-2:   #f8fafc;

    --border:      #e2e8f0;
    --border-lt:   #f0f4f7;

    --txt-1:       #0f172a;
    --txt-2:       #475569;
    --txt-3:       #94a3b8;

    --green:  #059669; --green-bg:  #ecfdf5;
    --amber:  #d97706; --amber-bg:  #fffbeb;
    --red:    #dc2626; --red-bg:    #fef2f2;
    --blue:   #2563eb; --blue-bg:   #eff6ff;

    --sh-xs:  0 1px 4px rgba(15,23,42,.06);
    --sh-sm:  0 2px 10px rgba(15,23,42,.07);
    --sh-md:  0 6px 24px rgba(15,23,42,.09);
    --sh-pop: 0 12px 40px rgba(15,118,110,.18);

    --r-sm:  8px;
    --r-md:  14px;
    --r-lg:  20px;


}

/* ── Keyframes ── */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}
@keyframes scaleIn {
    from { opacity: 0; transform: scale(.92); }
    to   { opacity: 1; transform: scale(1); }
}
@keyframes slideRight {
    from { transform: scaleX(0); }
    to   { transform: scaleX(1); }
}
@keyframes countUp {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes shimmer {
    0%   { background-position: -400px 0; }
    100% { background-position: 400px 0; }
}
@keyframes spin-slow {
    from { stroke-dashoffset: 314; }
    to   { stroke-dashoffset: <?= $donutOffset ?>; }
}
@keyframes pulse-dot {
    0%,100% { transform: scale(1); opacity: 1; }
    50%      { transform: scale(1.4); opacity: .6; }
}


.adx, .adx * { box-sizing: border-box; font-family: var(--f); }
.adx { background: var(--bg); min-height: 100vh; padding: 28px 28px 60px; }


.adx-flash-stack { display: grid; gap: 10px; margin-bottom: 20px; animation: fadeUp .4s ease both; }
.adx-flash {
    padding: 12px 16px; border-radius: var(--r-sm);
    font-size: 13.5px; font-weight: 600;
}
.adx-flash.success { background: var(--green-bg); color: #047857; border: 1px solid #a7f3d0; }
.adx-flash.error   { background: var(--red-bg);   color: #b91c1c; border: 1px solid #fecaca; }


.adx-header {
    display: flex; align-items: flex-start;
    justify-content: space-between; gap: 18px;
    margin-bottom: 26px; flex-wrap: wrap;
    animation: fadeUp .45s ease both;
}
.adx-header-kicker {
    display: flex; align-items: center; gap: 7px;
    font-size: 11px; font-weight: 700;
    letter-spacing: .12em; text-transform: uppercase;
    color: var(--teal); margin-bottom: 5px;
}
.adx-header-kicker-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--teal);
    animation: pulse-dot 2s ease infinite;
}
.adx-header h1 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3.5vw, 30px);
    font-weight: 400;
    color: var(--txt-1);
    letter-spacing: -.01em;
    margin: 0 0 5px;
    line-height: 1.15;
}
.adx-header-sub {
    font-size: 13px; font-weight: 400;
    color: var(--txt-3); margin: 0;
    max-width: 520px; line-height: 1.6;
}
.adx-header-actions { display: flex; gap: 9px; align-items: center; flex-shrink: 0; padding-top: 4px; }

.adx-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 17px; border-radius: var(--r-sm);
    font-family: var(--f); font-size: 13px; font-weight: 600;
    cursor: pointer; border: none; text-decoration: none;
    transition: all .2s cubic-bezier(.34,1.56,.64,1);
    white-space: nowrap;
}
.adx-btn svg { width: 14px; height: 14px; flex-shrink: 0; }
.adx-btn-ghost {
    background: var(--surface); color: var(--txt-2);
    border: 1px solid var(--border); box-shadow: var(--sh-xs);
}
.adx-btn-ghost:hover {
    background: var(--surface-2); color: var(--txt-1);
    transform: translateY(-1px); box-shadow: var(--sh-sm);
}
.adx-btn-teal {
    background: var(--teal); color: #fff;
    box-shadow: 0 3px 12px rgba(15,118,110,.3);
}
.adx-btn-teal:hover {
    background: #0d6b63; transform: translateY(-2px);
    box-shadow: var(--sh-pop);
}


.adx-quick {
    display: flex; gap: 7px; margin-bottom: 22px;
    flex-wrap: wrap;
    animation: fadeUp .5s .05s ease both;
}
.adx-quick-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 30px;
    font-family: var(--f); font-size: 12px; font-weight: 600;
    color: var(--txt-2); cursor: pointer;
    transition: all .2s cubic-bezier(.34,1.56,.64,1);
    box-shadow: var(--sh-xs);
}
.adx-quick-btn svg { width: 13px; height: 13px; opacity: .7; }
.adx-quick-btn:hover {
    color: var(--teal); border-color: var(--teal-mid);
    background: var(--teal-light);
    transform: translateY(-2px); box-shadow: var(--sh-sm);
}
.adx-quick-btn:hover svg { opacity: 1; }


.adx-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 16px; margin-bottom: 20px;
}
.adx-stat {
    border-radius: 10px;
    padding: 22px 20px;
    color: #fff;
    display: flex; align-items: center;
    justify-content: space-between;
    position: relative; overflow: hidden;
    box-shadow: var(--sh-md);
    opacity: 0;
    animation: scaleIn .5s cubic-bezier(.34,1.56,.64,1) both;
    transition: transform .2s ease, box-shadow .2s ease;
}
.adx-stat:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(15,23,42,.13); }

/* Stagger delays */
.adx-stat:nth-child(1) { animation-delay: .08s; }
.adx-stat:nth-child(2) { animation-delay: .14s; }
.adx-stat:nth-child(3) { animation-delay: .20s; }
.adx-stat:nth-child(4) { animation-delay: .26s; }

/* Gradients */
.adx-stat-1 { background: linear-gradient(135deg,#0f766e 0%,#14b8a6 100%); }
.adx-stat-2 { background: linear-gradient(135deg,#0f766e 0%,#14b8a6 100%); }
.adx-stat-3 { background: linear-gradient(135deg,#0f766e 0%,#14b8a6 100%); }
.adx-stat-4 { background: linear-gradient(135deg,#0f766e 0%,#14b8a6 100%); }

/* Decorative orb */


.adx-stat-left {
    display: flex; align-items: center; gap: 14px;
    position: relative; z-index: 1;
}
.adx-stat-icon {
    width: 50px; height: 50px; border-radius: 14px;
    background: rgba(255,255,255,.2);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    backdrop-filter: blur(4px);
}
.adx-stat-icon svg {
    width: 22px; height: 22px;
    fill: none; stroke: #fff;
    stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round;
}
.adx-stat-label {
    display: block; font-size: 11.5px; font-weight: 600;
    opacity: .85; margin-bottom: 4px; letter-spacing: .03em;
}
.adx-stat-value {
    display: block; font-family: var(--f-display);
    font-size: 28px; line-height: 1; font-weight: 400;
    letter-spacing: -.01em;
    animation: countUp .6s ease both;
}
.adx-stat-menu {
    position: relative; z-index: 1;
    color: rgba(255,255,255,.6); font-size: 22px;
    cursor: pointer; padding: 4px;
    transition: color .15s;
}
.adx-stat-menu:hover { color: #fff; }

/* ══════════════════════════════
   MAIN 2-COL
══════════════════════════════ */
.adx-grid-main {
    display: grid;
    grid-template-columns: minmax(0,1fr) 310px;
    gap: 18px; margin-bottom: 18px;
    animation: fadeUp .55s .18s ease both;
}

/* ══════════════════════════════
   CARD / PANEL
══════════════════════════════ */
.adx-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 2px;
    box-shadow: var(--sh-sm);
    overflow: hidden;
    transition: box-shadow .2s ease;
}
.adx-card:hover { box-shadow: var(--sh-md); }

.adx-card-head {
    display: flex; align-items: flex-start;
    justify-content: space-between; gap: 14px;
    padding: 20px 22px 12px;
    border-bottom: 1px solid var(--border-lt);
}
.adx-card-title {
    display: flex; align-items: center; gap: 9px;
    font-size: 15px; font-weight: 700; color: var(--txt-1); margin: 0;
}
.adx-card-dot {
    width: 8px; height: 8px; border-radius: 50%;
}
.adx-card-sub {
    margin: 3px 0 0; font-size: 11.5px;
    font-weight: 500; color: var(--txt-3);
}
.adx-card-ctrl {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 12px; border-radius: 7px;
    background: var(--surface-2); border: 1px solid var(--border);
    color: var(--txt-2); font-family: var(--f);
    font-size: 12px; font-weight: 600;
    text-decoration: none; cursor: pointer;
    transition: all .15s ease;
}
.adx-card-ctrl:hover { background: var(--teal-light); color: var(--teal); border-color: var(--teal-mid); }

/* ══════════════════════════════
   SVG CHART
══════════════════════════════ */
.adx-chart-wrap { padding: 6px 18px 18px; }
.adx-chart-svg { width: 100%; height: 230px; display: block; overflow: visible; }

.adx-grid-l { stroke: #edf2f7; stroke-width: 1; }

/* Smooth curve via filter */
.adx-line-a {
    fill: none; stroke: var(--teal);
    stroke-width: 3; stroke-linecap: round; stroke-linejoin: round;
    stroke-dasharray: 2000;
    stroke-dashoffset: 2000;
    animation: drawLine 1.4s .4s cubic-bezier(.4,0,.2,1) forwards;
}
.adx-line-b {
    fill: none; stroke: var(--teal-2);
    stroke-width: 3; stroke-linecap: round; stroke-linejoin: round;
    stroke-dasharray: 2000;
    stroke-dashoffset: 2000;
    animation: drawLine 1.4s .6s cubic-bezier(.4,0,.2,1) forwards;
}
@keyframes drawLine {
    to { stroke-dashoffset: 0; }
}
.adx-chart-dot-a {
    fill: var(--teal); stroke: #fff; stroke-width: 2.5;
    transition: r .15s ease;
}
.adx-chart-dot-b {
    fill: var(--teal-2); stroke: #fff; stroke-width: 2.5;
}
.adx-chart-lbl {
    fill: var(--txt-3); font-size: 11px; font-weight: 600;
    font-family: 'Outfit', sans-serif;
}

.adx-legend {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
}
.adx-legend-item {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 600; color: var(--txt-2);
}
.adx-legend-dot {
    width: 8px; height: 8px; border-radius: 50%;
}

/* ══════════════════════════════
   INCOME / DONUT CARD
══════════════════════════════ */
.adx-income-body { padding: 16px 20px 20px; }

.adx-donut-wrap {
    position: relative; width: 160px; height: 160px;
    margin: 8px auto 14px; display: grid; place-items: center;
}
.adx-donut {
    width: 160px; height: 160px;
    transform: rotate(-90deg);
}
.adx-donut-bg   { fill: none; stroke: #eef2f7; stroke-width: 14; }
.adx-donut-fill {
    fill: none; stroke: var(--teal); stroke-width: 14;
    stroke-linecap: round;
    stroke-dasharray: 314;
    stroke-dashoffset: 314;
    animation: spin-slow 1.2s .6s cubic-bezier(.4,0,.2,1) forwards;
}
.adx-donut-center {
    position: absolute; text-align: center; pointer-events: none;
}
.adx-donut-center-label { font-size: 11px; font-weight: 600; color: var(--txt-3); }
.adx-donut-center-val {
    display: block; margin-top: 2px;
    font-family: var(--f-display); font-size: 22px;
    color: var(--txt-1); line-height: 1;
}

.adx-income-meta {
    display: grid; grid-template-columns: 1fr 1fr; gap: 9px;
    margin-top: 4px;
}
.adx-income-meta-item {
    padding: 10px; border-radius: 10px;
    background: var(--surface-2); border: 1px solid var(--border-lt);
    text-align: center;
}
.adx-income-meta-label { font-size: 10.5px; font-weight: 600; color: var(--txt-3); }
.adx-income-meta-val   { font-size: 15px; font-weight: 700; color: var(--txt-1); margin-top: 2px; }

/* ══════════════════════════════
   BOTTOM ROW
══════════════════════════════ */
.adx-grid-bottom {
    display: grid;
    grid-template-columns: minmax(0,1fr) 350px;
    gap: 18px;
    animation: fadeUp .55s .28s ease both;
}

/* TABLE */
.adx-table-wrap { overflow-x: auto; padding: 0 0 4px; }
.adx-table {
    width: 100%; border-collapse: collapse;
    font-size: 13px; min-width: 720px;
}
.adx-table thead th {
    padding: 11px 14px;
    font-size: 10px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    color: var(--txt-3); background: var(--surface-2);
    border-bottom: 1px solid var(--border);
    text-align: left; white-space: nowrap;
}
.adx-table tbody tr {
    border-bottom: 1px solid var(--border-lt);
    transition: background .12s ease;
}
.adx-table tbody tr:last-child { border-bottom: none; }
.adx-table tbody tr:hover { background: #f8fcfb; }
.adx-table td {
    padding: 11px 14px;
    color: var(--txt-2); vertical-align: middle; white-space: nowrap;
}

/* Animate rows in */
.adx-table tbody tr {
    opacity: 0;
    animation: fadeUp .35s ease both;
}
<?php for ($ri = 1; $ri <= 8; $ri++): ?>
.adx-table tbody tr:nth-child(<?= $ri ?>) { animation-delay: <?= (.35 + $ri * .05) ?>s; }
<?php endfor; ?>

.adx-patient-cell { display: flex; align-items: center; gap: 9px; }
.adx-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--teal-light); color: var(--teal);
    font-size: 12px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; border: 1.5px solid var(--teal-mid);
}
.adx-patient-name { font-weight: 700; color: var(--txt-1); }

.adx-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px;
    font-size: 10.5px; font-weight: 700; white-space: nowrap;
}
.adx-badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; }
.adx-badge.active    { background: var(--green-bg);   color: var(--green);  }
.adx-badge.active::before    { background: var(--green); }
.adx-badge.completed { background: var(--blue-bg);    color: var(--blue);   }
.adx-badge.completed::before { background: var(--blue); }
.adx-badge.pending   { background: var(--amber-bg);   color: var(--amber);  }
.adx-badge.pending::before   { background: var(--amber); }
.adx-badge.danger    { background: var(--red-bg);     color: var(--red);    }
.adx-badge.danger::before    { background: var(--red); }
.adx-badge.neutral   { background: var(--surface-2);  color: var(--txt-3);  }
.adx-badge.neutral::before   { background: var(--txt-3); }

.adx-action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 7px;
    background: transparent; border: 1px solid transparent;
    color: var(--txt-3); cursor: pointer; font-size: 18px; font-weight: 700;
    transition: all .15s;
}
.adx-action-btn:hover { background: var(--surface-2); border-color: var(--border); color: var(--txt-1); }

/* AUDIT FEED */
.adx-feed { padding: 4px 0 6px; }
.adx-feed-item {
    display: grid;
    grid-template-columns: 36px 1fr;
    gap: 12px; padding: 12px 18px;
    border-bottom: 1px solid var(--border-lt);
    transition: background .12s ease;
    text-decoration: none; color: inherit;
    opacity: 0;
    animation: fadeUp .35s ease both;
}
<?php for ($fi = 1; $fi <= 8; $fi++): ?>
.adx-feed-item:nth-child(<?= $fi ?>) { animation-delay: <?= (.4 + $fi * .06) ?>s; }
<?php endfor; ?>
.adx-feed-item:last-child { border-bottom: none; }
.adx-feed-item:hover { background: var(--teal-light); }

.adx-feed-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--teal-light); color: var(--teal);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: background .15s;
}
.adx-feed-item:hover .adx-feed-icon { background: var(--teal-mid); }
.adx-feed-icon svg {
    width: 15px; height: 15px;
    fill: none; stroke: currentColor;
    stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round;
}
.adx-feed-action { font-size: 13px; font-weight: 700; color: var(--txt-1); margin-bottom: 2px; line-height: 1.3; }
.adx-feed-desc   { font-size: 11.5px; color: var(--txt-2); line-height: 1.4; margin-bottom: 4px; }
.adx-feed-time   {
    font-size: 10.5px; color: var(--txt-3);
    display: flex; align-items: center; gap: 4px;
}

/* EMPTY */
.adx-empty {
    padding: 36px 20px; text-align: center;
    color: var(--txt-3); font-size: 13px; font-weight: 500;
}
.adx-empty-ico { font-size: 28px; margin-bottom: 8px; }

@media (max-width: 1180px) {
    .adx-stats        { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .adx-grid-main    { grid-template-columns: 1fr; }
    .adx-grid-bottom  { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .adx { padding: 16px 14px 48px; }
    .adx-header       { flex-direction: column; }
    .adx-header-actions { width: 100%; }
    .adx-btn          { flex: 1; justify-content: center; }
    .adx-stats        { grid-template-columns: 1fr; }
    .adx-stat         { min-height: 80px; }
    .adx-income-meta  { grid-template-columns: 1fr; }
}
</style>

<div class="adx">


  <?php if ($flash_success || $flash_error): ?>
    <div class="adx-flash-stack">
      <?php if ($flash_success): ?><div class="adx-flash success"><?= e($flash_success) ?></div><?php endif; ?>
      <?php if ($flash_error):   ?><div class="adx-flash error"><?= e($flash_error) ?></div><?php endif; ?>
    </div>
  <?php endif; ?>



  <!-- ── QUICK NAV ── -->
  <div class="adx-quick">
    <a href="<?= e($baseUrl . '/admin/users') ?>" class="adx-quick-btn">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      Users &amp; Roles
    </a>
    <a href="<?= e($baseUrl . '/admin/appointments') ?>" class="adx-quick-btn">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Appointments
    </a>
    <a href="<?= e($baseUrl . '/admin/dentists') ?>" class="adx-quick-btn">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
      Dentist Management
    </a>
    <a href="<?= e($baseUrl . '/admin/services') ?>" class="adx-quick-btn">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      Services
    </a>
    <a href="<?= e($baseUrl . '/admin/audit-trail') ?>" class="adx-quick-btn">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Audit Trail
    </a>
    <a href="<?= e($baseUrl . '/admin/settings') ?>" class="adx-quick-btn">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
      Settings
    </a>
  </div>

  <!-- ── STAT TILES ── -->
  <div class="adx-stats">

    <div class="adx-stat adx-stat-1">
      <div class="adx-stat-left">
        <span class="adx-stat-icon">
          <svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg>
        </span>
        <span>
          <span class="adx-stat-label">Today's Appointments</span>
          <span class="adx-stat-value"><?= number_format($todayAppointments) ?></span>
        </span>
      </div>
      <span class="adx-stat-menu">⋮</span>
    </div>

    <div class="adx-stat adx-stat-2">
      <div class="adx-stat-left">
        <span class="adx-stat-icon">
          <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
        </span>
        <span>
          <span class="adx-stat-label">Total Patients</span>
          <span class="adx-stat-value"><?= number_format($totalPatients) ?></span>
        </span>
      </div>
      <span class="adx-stat-menu">⋮</span>
    </div>

    <div class="adx-stat adx-stat-3">
      <div class="adx-stat-left">
        <span class="adx-stat-icon">
          <svg viewBox="0 0 24 24"><path d="M12 21s-7-4.4-7-11a7 7 0 0114 0c0 6.6-7 11-7 11z"/><path d="M9 10h6M12 7v6"/></svg>
        </span>
        <span>
          <span class="adx-stat-label">Completed Operations</span>
          <span class="adx-stat-value"><?= number_format($totalOperations) ?></span>
        </span>
      </div>
      <span class="adx-stat-menu">⋮</span>
    </div>

    <div class="adx-stat adx-stat-4">
      <div class="adx-stat-left">
        <span class="adx-stat-icon">
          <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7H14a3.5 3.5 0 010 7H6"/></svg>
        </span>
        <span>
          <span class="adx-stat-label">Total Earning</span>
          <span class="adx-stat-value"><?= e(adminMoney($totalIncome)) ?></span>
        </span>
      </div>
      <span class="adx-stat-menu">⋮</span>
    </div>

  </div>

  <!-- ── MAIN ROW: Chart + Donut ── -->
  <div class="adx-grid-main">

    <!-- LINE CHART -->
    <div class="adx-card">
      <div class="adx-card-head">
        <div>
          <h2 class="adx-card-title">
            <span class="adx-card-dot" style="background:var(--teal)"></span>
            Patient Visit
          </h2>
          <p class="adx-card-sub">Monthly appointments vs completed treatments</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <div class="adx-legend">
            <span class="adx-legend-item">
              <span class="adx-legend-dot" style="background:var(--teal)"></span>
              Appointments
            </span>
            <span class="adx-legend-item">
              <span class="adx-legend-dot" style="background:var(--teal-2)"></span>
              Completed
            </span>
          </div>
          <span class="adx-card-ctrl">Yearly</span>
        </div>
      </div>
      <div class="adx-chart-wrap">
        <svg class="adx-chart-svg" viewBox="0 0 <?= $chartW ?> <?= $chartH ?>" preserveAspectRatio="none" role="img" aria-label="Patient visit chart">
          <!-- Grid lines -->
          <?php
          $gridSteps = 4;
          for ($g = 0; $g <= $gridSteps; $g++):
            $gy = $chartPY + ($chartIH / $gridSteps) * $g;
          ?>
            <line class="adx-grid-l" x1="<?= $chartPX ?>" y1="<?= round($gy,1) ?>" x2="<?= $chartW - $chartPX ?>" y2="<?= round($gy,1) ?>"/>
          <?php endfor; ?>

          <!-- Fill areas -->
          <defs>
            <linearGradient id="gradA" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%"   stop-color="#0f766e" stop-opacity=".15"/>
              <stop offset="100%" stop-color="#0f766e" stop-opacity="0"/>
            </linearGradient>
            <linearGradient id="gradB" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%"   stop-color="#14b8a6" stop-opacity=".1"/>
              <stop offset="100%" stop-color="#14b8a6" stop-opacity="0"/>
            </linearGradient>
          </defs>

          <!-- Area fills -->
          <?php
          $floorY = $chartPY + $chartIH;
          $allAppt = implode(' ', $ptsAppt);
          $allDone = implode(' ', $ptsDone);
          $firstApptX = $chartPX;
          $lastApptX  = $chartPX + $chartIW;
          ?>
          <polygon fill="url(#gradA)" points="<?= $chartPX ?>,<?= $floorY ?> <?= $allAppt ?> <?= $chartW - $chartPX ?>,<?= $floorY ?>"/>
          <polygon fill="url(#gradB)" points="<?= $chartPX ?>,<?= $floorY ?> <?= $allDone ?> <?= $chartW - $chartPX ?>,<?= $floorY ?>"/>

          <!-- Lines -->
          <polyline class="adx-line-b" points="<?= e($ptsDoneStr) ?>"/>
          <polyline class="adx-line-a" points="<?= e($ptsApptStr) ?>"/>

          <!-- Dots + month labels -->
          <?php
          $moNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
          foreach ($ptsAppt as $mi => $pt):
            [$px,$py] = explode(',', $pt);
            [$px2,$py2] = explode(',', $ptsDone[$mi]);
            $lx = $chartPX + ($mi * ($chartIW / 11));
          ?>
            <circle class="adx-chart-dot-a" cx="<?= round($px,1) ?>" cy="<?= round($py,1) ?>" r="4"/>
            <circle class="adx-chart-dot-b" cx="<?= round($px2,1) ?>" cy="<?= round($py2,1) ?>" r="4"/>
            <text class="adx-chart-lbl" x="<?= round($lx,1) ?>" y="<?= $chartH - 4 ?>" text-anchor="middle"><?= $moNames[$mi] ?></text>
          <?php endforeach; ?>
        </svg>
      </div>
    </div>

    <!-- INCOME / DONUT -->
    <div class="adx-card">
      <div class="adx-card-head">
        <div>
          <h2 class="adx-card-title">
            <span class="adx-card-dot" style="background:var(--teal-2)"></span>
            Total Income
          </h2>
          <p class="adx-card-sub">Based on dashboard data</p>
        </div>
      </div>
      <div class="adx-income-body">
        <div class="adx-donut-wrap">
          <svg class="adx-donut" viewBox="0 0 120 120">
            <circle class="adx-donut-bg"   cx="60" cy="60" r="50"/>
            <circle class="adx-donut-fill" cx="60" cy="60" r="50"/>
          </svg>
          <div class="adx-donut-center">
            <span class="adx-donut-center-label">Income</span>
            <strong class="adx-donut-center-val"><?= e(adminMoney($totalIncome)) ?></strong>
          </div>
        </div>
        <div class="adx-income-meta">
          <div class="adx-income-meta-item">
            <div class="adx-income-meta-label">Completed</div>
            <div class="adx-income-meta-val" style="color:var(--green)"><?= $donutPercent ?>%</div>
          </div>
          <div class="adx-income-meta-item">
            <div class="adx-income-meta-label">Pending</div>
            <div class="adx-income-meta-val" style="color:var(--amber)"><?= number_format($pendingRequests) ?></div>
          </div>
          <div class="adx-income-meta-item">
            <div class="adx-income-meta-label">Dentists</div>
            <div class="adx-income-meta-val" style="color:var(--blue)"><?= $totalDentists ?></div>
          </div>
          <div class="adx-income-meta-item">
            <div class="adx-income-meta-label">Services</div>
            <div class="adx-income-meta-val" style="color:var(--teal)"><?= $activeServices ?></div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /.adx-grid-main -->

  <!-- ── BOTTOM ROW: Table + Audit ── -->
  <div class="adx-grid-bottom">

    <!-- APPOINTMENTS TABLE -->
    <div class="adx-card">
      <div class="adx-card-head">
        <div>
          <h2 class="adx-card-title">
            <span class="adx-card-dot" style="background:var(--blue)"></span>
            Appointments
          </h2>
          <p class="adx-card-sub">Recent appointment records</p>
        </div>
        <a href="<?= e($baseUrl . '/admin/appointments') ?>" class="adx-card-ctrl">
          View all
          <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
      </div>
      <div class="adx-table-wrap">
        <table class="adx-table">
          <thead>
            <tr>
              <th>Patient</th>
              <th>Dentist</th>
              <th>Service</th>
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentAppointments)): ?>
              <tr>
                <td colspan="7">
                  <div class="adx-empty">
                    <div class="adx-empty-ico">📅</div>
                    No recent appointments found.
                  </div>
                </td>
              </tr>
            <?php endif; ?>
            <?php foreach (array_slice($recentAppointments, 0, 8) as $apt):
              $pName  = trim((string)($apt['patient_name'] ?? 'Patient'));
              $status = strtolower(trim((string)($apt['status'] ?? '')));
            ?>
              <tr>
                <td>
                  <div class="adx-patient-cell">
                    <span class="adx-avatar"><?= e(adminDashInitial($pName)) ?></span>
                    <span class="adx-patient-name"><?= e($pName) ?></span>
                  </div>
                </td>
                <td><?= e($apt['dentist_name'] ?? '—') ?></td>
                <td><?= e($apt['service_name'] ?? '—') ?></td>
                <td><?= e(adminDashShortDate($apt['appointment_date'] ?? '')) ?></td>
                <td style="color:var(--txt-3);font-size:12px"><?= e(adminDashTimeRange($apt['start_time'] ?? '', $apt['end_time'] ?? '')) ?></td>
                <td><span class="adx-badge <?= e(adminDashStatusClass($status)) ?>"><?= e(adminDashStatusText($status)) ?></span></td>
                <td><button class="adx-action-btn" title="More">⋯</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- AUDIT FEED -->
    <div class="adx-card">
      <div class="adx-card-head">
        <div>
          <h2 class="adx-card-title">
            <span class="adx-card-dot" style="background:#7c3aed"></span>
            System Activity
          </h2>
          <p class="adx-card-sub">Latest audit trail actions</p>
        </div>
        <a href="<?= e($baseUrl . '/admin/audit-trail') ?>" class="adx-card-ctrl">View all</a>
      </div>
      <div class="adx-feed">
        <?php if (empty($recentAuditLogs)): ?>
          <div class="adx-empty">
            <div class="adx-empty-ico">🔍</div>
            No audit log entries found.
          </div>
        <?php endif; ?>
        <?php foreach (array_slice($recentAuditLogs, 0, 8) as $log): ?>
          <a href="<?= e($baseUrl . '/admin/audit-trail') ?>" class="adx-feed-item">
            <div class="adx-feed-icon">
              <svg viewBox="0 0 24 24"><path d="M7 3h7l5 5v13H7z"/><path d="M14 3v6h5"/><path d="M10 13h6M10 17h4"/></svg>
            </div>
            <div>
              <div class="adx-feed-action"><?= e($log['action'] ?? 'System action') ?></div>
              <?php if (!empty($log['description'])): ?>
                <div class="adx-feed-desc"><?= e($log['description']) ?></div>
              <?php endif; ?>
              <div class="adx-feed-time">
                <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= e(adminDashDate($log['created_at'] ?? '')) ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

  </div><!-- /.adx-grid-bottom -->

</div><!-- /.adx -->

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';