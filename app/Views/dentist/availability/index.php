<?php
use App\Core\Csrf;

$dentist = $dentist ?? [];
$schedules = $schedules ?? [];
$unavailableDates = $unavailableDates ?? [];
$dateOverrides = $dateOverrides ?? [];
$monthlyUnavailableDates = $monthlyUnavailableDates ?? [];
$monthlyDateOverrides = $monthlyDateOverrides ?? [];
$dayLabels = $dayLabels ?? [];
$summary = $summary ?? [];
$calendarMonth = $calendarMonth ?? date('Y-m');
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

$base = '/DentalClinic/public';

$currentMonthDate = DateTime::createFromFormat('Y-m', $calendarMonth) ?: new DateTime(date('Y-m-01'));
$monthTitle = $currentMonthDate->format('F Y');

$prevMonth = (clone $currentMonthDate)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $currentMonthDate)->modify('+1 month')->format('Y-m');

$startOfMonth = (clone $currentMonthDate)->modify('first day of this month');
$endOfMonth = (clone $currentMonthDate)->modify('last day of this month');

$calendarStart = clone $startOfMonth;
$calendarStartDay = (int) $calendarStart->format('w');

if ($calendarStartDay > 0) {
    $calendarStart->modify('-' . $calendarStartDay . ' day');
}

$calendarEnd = clone $endOfMonth;
$calendarEndDay = (int) $calendarEnd->format('w');

if ($calendarEndDay < 6) {
    $calendarEnd->modify('+' . (6 - $calendarEndDay) . ' day');
}

$today = date('Y-m-d');
$weeks = [];
$cursor = clone $calendarStart;

while ($cursor <= $calendarEnd) {
    $week = [];

    for ($i = 0; $i < 7; $i++) {
        $week[] = clone $cursor;
        $cursor->modify('+1 day');
    }

    $weeks[] = $week;
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('availabilityScheduleValue')) {
    function availabilityScheduleValue(array $row, string $key, $default = '')
    {
        return $row[$key] ?? $default;
    }
}

if (!function_exists('availabilityTimeLabel')) {
    function availabilityTimeLabel(?string $time): string
    {
        if (empty($time)) {
            return '';
        }

        $timestamp = strtotime($time);

        return $timestamp !== false ? date('g:i A', $timestamp) : '';
    }
}

if (!function_exists('availabilityCalendarStatus')) {
    function availabilityCalendarStatus(
        string $date,
        array $weeklySchedules,
        array $overrideMap,
        array $unavailableMap,
        string $today
    ): array {
        $isPast = $date < $today;

        $buildStatus = static function (
            string $realStatus,
            string $realLabel,
            string $realClass,
            ?string $startTime = null,
            ?string $endTime = null
        ) use ($isPast): array {
            return [
                'status' => $isPast ? 'past' : $realStatus,
                'label' => $realLabel,
                'class' => $isPast ? 'is-past' : $realClass,
                'real_status' => $realStatus,
                'real_class' => $realClass,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ];
        };

        $isHalfDay = static function (string $start, string $end) use ($date): bool {
            $startTimestamp = strtotime($date . ' ' . $start);
            $endTimestamp = strtotime($date . ' ' . $end);

            if (!$startTimestamp || !$endTimestamp || $endTimestamp <= $startTimestamp) {
                return false;
            }

            $hours = ($endTimestamp - $startTimestamp) / 3600;

            return $hours > 0 && $hours <= 6;
        };

        if (isset($overrideMap[$date])) {
            $override = $overrideMap[$date];
            $isAvailable = (int) ($override['is_available'] ?? 0) === 1;
            $start = $override['start_time'] ?? null;
            $end = $override['end_time'] ?? null;
            $mode = strtolower(trim((string) ($override['availability_mode'] ?? '')));

            if (!$isAvailable) {
                return $buildStatus('unavailable', 'Unavailable', 'is-unavailable');
            }

            if (in_array($mode, ['half_day', 'morning', 'afternoon'], true)) {
                return $buildStatus('half_day', 'Half Day', 'is-half', $start, $end);
            }

            if (!empty($start) && !empty($end) && $isHalfDay((string) $start, (string) $end)) {
                return $buildStatus('half_day', 'Half Day', 'is-half', $start, $end);
            }

            return $buildStatus('available', 'Available', 'is-available', $start, $end);
        }

        if (isset($unavailableMap[$date])) {
            $block = $unavailableMap[$date];
            $start = $block['start_time'] ?? null;
            $end = $block['end_time'] ?? null;

            if (!empty($start) && !empty($end)) {
                return $buildStatus('half_day', 'Half Day', 'is-half', $start, $end);
            }

            return $buildStatus('unavailable', 'Unavailable', 'is-unavailable');
        }

        $dayOfWeek = (int) date('w', strtotime($date));
        $weekly = $weeklySchedules[$dayOfWeek] ?? null;

        if ($weekly && (int) ($weekly['is_available'] ?? 0) === 1) {
            $start = $weekly['start_time'] ?? null;
            $end = $weekly['end_time'] ?? null;

            if (!empty($start) && !empty($end) && $isHalfDay((string) $start, (string) $end)) {
                return $buildStatus('half_day', 'Half Day', 'is-half', $start, $end);
            }

            return $buildStatus('available', 'Available', 'is-available', $start, $end);
        }

        return $buildStatus('unavailable', 'Unavailable', 'is-unavailable');
    }
}

$availableDaysCount = 0;
$halfDaysCount = 0;
$unavailableDaysCount = 0;
$nextAvailableDate = null;

foreach ($weeks as $week) {
    foreach ($week as $dateObj) {
        $dateKey = $dateObj->format('Y-m-d');

        if ($dateObj->format('Y-m') !== $calendarMonth) {
            continue;
        }

        $status = availabilityCalendarStatus(
            $dateKey,
            $schedules,
            $monthlyDateOverrides,
            $monthlyUnavailableDates,
            $today
        );

        $realStatus = $status['real_status'] ?? $status['status'] ?? 'unavailable';

        if ($realStatus === 'available') {
            $availableDaysCount++;

            if ($nextAvailableDate === null && $dateKey >= $today) {
                $nextAvailableDate = $dateKey;
            }
        } elseif ($realStatus === 'half_day') {
            $halfDaysCount++;

            if ($nextAvailableDate === null && $dateKey >= $today) {
                $nextAvailableDate = $dateKey;
            }
        } elseif ($realStatus === 'unavailable') {
            $unavailableDaysCount++;
        }
    }
}

$totalMonthDays = $availableDaysCount + $halfDaysCount + $unavailableDaysCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dentist Availability</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet"
    >

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
        }

        :root {
            --dentist-sidebar-width: 248px;

            --teal-900: #0f3f3a;
            --teal-800: #115e59;
            --teal-700: #0f766e;
            --teal-600: #0d9488;
            --teal-100: #ccfbf1;
            --teal-50: #f0fdfa;

            --green-700: #15803d;
            --green-600: #16a34a;
            --green-100: #dcfce7;
            --green-50: #f0fdf4;

            --amber-700: #b45309;
            --amber-600: #d97706;
            --amber-100: #fef3c7;
            --amber-50: #fffbeb;

            --rose-700: #be123c;
            --rose-600: #e11d48;
            --rose-100: #ffe4e6;
            --rose-50: #fff1f2;

            --blue-700: #1d4ed8;
            --blue-600: #2563eb;
            --blue-100: #dbeafe;
            --blue-50: #eff6ff;

            --slate-950: #020617;
            --slate-900: #0f172a;
            --slate-800: #1e293b;
            --slate-700: #334155;
            --slate-600: #475569;
            --slate-500: #64748b;
            --slate-400: #94a3b8;
            --slate-300: #cbd5e1;
            --slate-200: #e2e8f0;
            --slate-100: #f1f5f9;
            --slate-50: #f8fafc;
            --white: #ffffff;

            --shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.06);
            --shadow-md: 0 8px 24px rgba(15, 23, 42, 0.08);
            --shadow-lg: 0 18px 50px rgba(15, 23, 42, 0.16);

            --radius-xs: 4px;
            --radius-sm: 6px;
            --radius-md: 1px;
            --radius-lg: 1px;

            --transition: 180ms cubic-bezier(.4, 0, .2, 1);
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            background: var(--slate-50);
            color: var(--slate-900);
            overflow-x: hidden;
        }

        body {
            font-family: 'DM Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
            line-height: 1.5;
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

        a {
            color: inherit;
            text-decoration: none;
        }

        .page-layout {
            width: 100%;
            min-height: 100vh;
            background: var(--slate-50);
        }

        .page-main {
            width: calc(100% - var(--dentist-sidebar-width));
            min-height: 100vh;
            margin-left: var(--dentist-sidebar-width);
            background: var(--slate-50);
        }

        .availability-page {
            width: 100%;
            min-height: 100vh;
            padding: 96px 20px 36px;
            font-family: 'DM Sans', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .availability-page *,
        .availability-page *::before,
        .availability-page *::after {
            font-family: inherit;
        }

        .availability-page code,
        .availability-page pre,
        .availability-page .mono,
        .availability-page .appointment-code {
            font-family: 'DM Mono', Consolas, monospace;
        }

        .availability-container {
            width: 100%;
            max-width: 1040px;
            margin: 0 auto;
        }

        .availability-flash-stack {
            width: 100%;
            max-width: 1040px;
            margin: 0 auto 14px;
            display: grid;
            gap: 8px;
        }

        .availability-flash {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 700;
            animation: flashIn 300ms cubic-bezier(.4, 0, .2, 1) both;
        }

        @keyframes flashIn {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .availability-flash.success {
            border: 1px solid var(--green-100);
            background: var(--green-50);
            color: var(--green-700);
        }

        .availability-flash.error {
            border: 1px solid var(--rose-100);
            background: var(--rose-50);
            color: var(--rose-700);
        }

        .availability-layout {
            display: grid;
            grid-template-columns: minmax(0, 740px) 270px;
            justify-content: center;
            align-items: start;
            gap: 14px;
            width: 100%;
        }

        .availability-calendar-card {
            width: 100%;
            max-width: 740px;
            min-width: 0;
            background: var(--white);
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .calendar-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 11px 12px;
            border-bottom: 1px solid var(--slate-100);
            background: var(--white);
        }

        .calendar-toolbar-left,
        .calendar-toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-shrink: 0;
        }

        .calendar-nav-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 29px;
            height: 29px;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-sm);
            background: var(--white);
            color: var(--slate-500);
            transition:
                color var(--transition),
                border-color var(--transition),
                background var(--transition),
                transform var(--transition);
        }

        .calendar-nav-btn:hover {
            color: var(--teal-700);
            border-color: var(--teal-100);
            background: var(--teal-50);
            transform: translateY(-1px);
        }

        .calendar-nav-btn svg {
            width: 15px;
            height: 15px;
        }

        .calendar-title {
            display: flex;
            align-items: center;
            gap: 7px;
            min-width: 0;
            color: var(--slate-900);
            font-size: 16px;
            font-weight: 900;
            letter-spacing: -0.02em;
            white-space: nowrap;
        }

        .calendar-title-badge {
            display: inline-flex;
            align-items: center;
            height: 20px;
            padding: 0 7px;
            border-radius: 999px;
            background: var(--teal-50);
            color: var(--teal-700);
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0.04em;
        }

        .calendar-tabs {
            display: flex;
            align-items: center;
            gap: 2px;
            padding: 3px;
            border-radius: var(--radius-md);
            background: var(--slate-100);
        }

        .calendar-tab {
            border: 0;
            border-radius: var(--radius-sm);
            background: transparent;
            color: var(--slate-500);
            padding: 6px 13px;
            font-size: 12px;
            font-weight: 900;
            transition:
                background var(--transition),
                color var(--transition),
                box-shadow var(--transition);
        }

        .calendar-tab:hover {
            color: var(--slate-700);
        }

        .calendar-tab.active {
            color: var(--teal-700);
            background: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .manage-schedule-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            height: 31px;
            border: 0;
            border-radius: var(--radius-sm);
            background: #020617;
            color: var(--white);
            padding: 0 12px;
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
            transition:
                background var(--transition),
                transform var(--transition),
                box-shadow var(--transition);
        }

        .manage-schedule-btn:hover {
            background: var(--teal-900);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(15, 118, 110, .22);
        }

        .manage-schedule-btn svg {
            width: 14px;
            height: 14px;
        }

        .calendar-panel {
            display: none;
        }

        .calendar-panel.active {
            display: block;
            animation: panelIn 200ms cubic-bezier(.4, 0, .2, 1) both;
        }

        @keyframes panelIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .calendar-weekdays,
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }

        .calendar-weekdays {
            background: var(--slate-50);
            border-bottom: 1px solid var(--slate-100);
        }

        .calendar-weekday {
            padding: 7px 4px;
            text-align: center;
            color: var(--slate-400);
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .calendar-cell {
            min-height: 58px;
            padding: 21px;
            border-right: 1px solid var(--slate-100);
            border-bottom: 1px solid var(--slate-100);
            background: var(--white);
            position: relative;
            overflow: hidden;
            transition:
                background var(--transition),
                box-shadow var(--transition);
        }

        .calendar-cell:nth-child(7n) {
            border-right: 0;
        }

        .calendar-cell:not(.is-disabled) {
            cursor: pointer;
        }

        .calendar-cell::after {
            content: "";
            position: absolute;
            inset: 0;
            border: 2px solid transparent;
            pointer-events: none;
            transition: border-color var(--transition);
        }

        .calendar-cell:not(.is-disabled):hover::after,
        .calendar-cell.selected::after {
            border-color: var(--teal-600);
        }

        .calendar-cell:not(.is-disabled):hover {
            background: var(--teal-50);
        }

        .calendar-cell.is-other,
        .calendar-cell.is-past {
            background: #fbfbfc;
            color: var(--slate-300);
        }

        .calendar-cell.is-available:not(.is-other):not(.is-past) {
            background: #ffffff;
        }

        .calendar-cell.is-half:not(.is-other):not(.is-past) {
            background: #fffdf7;
        }

        .calendar-cell.is-unavailable:not(.is-other):not(.is-past) {
            background: #fff9fa;
        }

        .calendar-cell-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 4px;
        }

        .calendar-day-number {
            color: var(--slate-900);
            font-size: 12px;
            font-weight: 900;
            line-height: 1;
        }

        .calendar-cell.is-other .calendar-day-number,
        .calendar-cell.is-past .calendar-day-number {
            color: var(--slate-300);
        }

        .calendar-cell.is-today .calendar-day-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            margin-top: -3px;
            border-radius: 999px;
            background: var(--teal-700);
            color: var(--white);
            font-size: 11px;
        }

        .calendar-status {
            display: grid;
            gap: 3px;
            margin-top: 8px;
        }

        .calendar-pill {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            gap: 4px;
            max-width: 100%;
            padding: 2px 6px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 900;
            line-height: 1.2;
            white-space: nowrap;
        }

        .calendar-pill-dot {
            width: 5px;
            height: 5px;
            border-radius: 999px;
            flex-shrink: 0;
        }

        .pill-available {
            color: var(--green-700);
            background: var(--green-100);
        }

        .pill-half_day {
            color: var(--amber-700);
            background: var(--amber-100);
        }

        .pill-unavailable {
            color: var(--rose-700);
            background: var(--rose-100);
        }

        .pill-past {
            color: var(--slate-400);
            background: var(--slate-100);
        }

        .calendar-time {
            color: var(--slate-500);
            font-family: 'DM Mono', Consolas, monospace !important;
            font-size: 8px;
            font-weight: 800;
            white-space: nowrap;
        }

        .calendar-legend {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 9px 12px;
            border-top: 1px solid var(--slate-100);
            background: var(--slate-50);
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--slate-500);
            font-size: 11px;
            font-weight: 800;
        }

        .legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
        }

        .weekly-shell {
            padding: 12px;
        }

        .weekly-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .weekly-title {
            color: var(--slate-900);
            font-size: 15px;
            font-weight: 900;
            letter-spacing: -0.02em;
        }

        .weekly-subtitle {
            margin-top: 2px;
            color: var(--slate-400);
            font-size: 11px;
            font-weight: 700;
        }

        .weekly-nav {
            display: flex;
            gap: 6px;
        }

        .weekly-nav-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            min-height: 30px;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-sm);
            background: var(--white);
            color: var(--slate-600);
            padding: 0 10px;
            font-size: 11px;
            font-weight: 900;
            transition:
                color var(--transition),
                border-color var(--transition),
                background var(--transition);
        }

        .weekly-nav-btn:hover {
            color: var(--teal-700);
            border-color: var(--teal-100);
            background: var(--teal-50);
        }

        .weekly-nav-btn svg {
            width: 13px;
            height: 13px;
        }

        .weekly-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-md);
        }

        .weekly-grid {
            min-width: 690px;
            display: grid;
            grid-template-columns: 70px repeat(7, minmax(84px, 1fr));
        }

        .wg-corner,
        .wg-dayhead,
        .wg-time,
        .wg-slot {
            border-right: 1px solid var(--slate-100);
            border-bottom: 1px solid var(--slate-100);
        }

        .wg-corner,
        .wg-dayhead,
        .wg-time {
            background: var(--slate-50);
        }

        .wg-corner {
            min-height: 46px;
        }

        .wg-dayhead {
            min-height: 46px;
            padding: 8px;
        }

        .wg-day-name {
            color: var(--slate-700);
            font-size: 11px;
            font-weight: 900;
        }

        .wg-day-date {
            margin-top: 2px;
            color: var(--slate-400);
            font-size: 10px;
            font-weight: 800;
        }

        .wg-time {
            min-height: 54px;
            padding: 8px;
            color: var(--slate-400);
            font-family: 'DM Mono', Consolas, monospace !important;
            font-size: 9.5px;
            font-weight: 800;
        }

        .wg-slot {
            min-height: 54px;
            padding: 4px;
            background: var(--white);
        }

        .slot-open {
            height: 100%;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        
            
            color: var(--slate-300);
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 0.04em;
            transition:
                background var(--transition),
                color var(--transition),
                border-color var(--transition);
        }

        .wg-slot:hover .slot-open {
            color: var(--teal-600);
            border-color: var(--teal-100);
            background: var(--teal-50);
        }

        .slot-appt {
            min-height: 48px;
            display: grid;
            gap: 2px;
            padding: 15px 7px;
         
            border-radius: var(--radius-sm);
            background: var(--green-50);
        }

        .slot-appt.is-scheduled {
            border-left-color: var(--blue-600);
            background: var(--blue-50);
        }

        .slot-appt.is-actual {
            border-left-color: var(--green-600);
            background: var(--green-50);
        }


        /* Unified appointment card: no Scheduled/Actual separation */
.slot-appt,
.slot-appt.is-scheduled,
.slot-appt.is-actual {
    border-left-color: var(--teal-700) !important;
    background: var(--teal-50) !important;
}

.appt-badge,
.appt-meta,
.appt-status {
    display: none !important;
}

.appt-time {
    color: var(--teal-800) !important;
    font-family: 'DM Mono', Consolas, monospace !important;
    font-size: 10.5px;
    font-weight: 900;
}

.appt-service {
    color: var(--slate-900);
    font-size: 10px;
    font-weight: 900;
    line-height: 1.25;
}

.appt-patient {
    color: var(--slate-600);
    font-size: 9px;
    font-weight: 800;
    line-height: 1.25;
}


        .appt-badge {
            width: fit-content;
            padding: 1px 5px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .9);
            color: var(--teal-700);
            font-size: 7.5px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .appt-time {
            color: var(--green-700);
            font-family: 'DM Mono', Consolas, monospace !important;
            font-size: 8.5px;
            font-weight: 900;
        }

        .appt-service {
            color: var(--slate-900);
            font-size: 10px;
            font-weight: 900;
            line-height: 1.25;
        }

        .appt-patient,
        .appt-meta,
        .appt-status {
            color: var(--slate-500);
            font-size: 9px;
            font-weight: 800;
            line-height: 1.25;
        }

        .appt-meta {
            font-family: 'DM Mono', Consolas, monospace !important;
        }

        .appt-status {
            color: var(--slate-700);
            text-transform: capitalize;
        }

        .weekly-empty {
            grid-column: 1 / -1;
            padding: 34px 16px;
            color: var(--slate-400);
            text-align: center;
            font-size: 13px;
            font-weight: 800;
        }

        .availability-side {
            width: 100%;
            max-width: 270px;
            display: grid;
            gap: 10px;
            position: sticky;
            top: 96px;
        }

        .side-card {
            background: var(--white);
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .side-card-header {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 12px;
            border-bottom: 1px solid var(--slate-100);
        }

        .side-icon {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            background: var(--teal-50);
            color: var(--teal-700);
            flex-shrink: 0;
        }

        .side-icon svg {
            width: 15px;
            height: 15px;
        }

        .side-card-title {
            color: var(--slate-900);
            font-size: 13px;
            font-weight: 900;
            line-height: 1.2;
        }

        .side-card-subtitle {
            margin-top: 1px;
            color: var(--slate-400);
            font-size: 11px;
            font-weight: 700;
        }

        .side-card-body {
            padding: 12px;
        }

        .side-note {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 10px;
            
            border-radius: var(--radius-md);
            background: #f4f4f4;
            color: var(--teal-900);
            font-size: 11px;
            font-weight: 800;
            line-height: 1.45;
        }

        .side-note svg {
            width: 14px;
            height: 14px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .next-card {
            padding: 13px;
            border-radius: var(--radius-lg);
            background: #020617;
            color: var(--white);
            box-shadow: 0 8px 20px rgba(15, 118, 110, .18);
        }

        .next-label {
            color: rgba(255, 255, 255, .74);
            font-size: 9.5px;
            font-weight: 900;
            letter-spacing: 0.10em;
            text-transform: uppercase;
        }

        .next-date {
            margin-top: 4px;
            font-size: 17px;
            font-weight: 900;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        .side-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .side-stat {
            min-height: 70px;
            padding: 11px 12px;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-lg);
            background: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .side-stat-value {
            color: var(--slate-900);
            font-size: 20px;
            font-weight: 900;
            line-height: 1;
        }

        .side-stat-value.green {
            color: var(--green-700);
        }

        .side-stat-value.amber {
            color: var(--amber-700);
        }

        .side-stat-value.rose {
            color: var(--rose-700);
        }

        .side-stat-label {
            margin-top: 5px;
            color: var(--slate-400);
            font-size: 10px;
            font-weight: 800;
            line-height: 1.25;
        }

        .availability-popup {
            position: fixed;
            z-index: 1800;
            width: 220px;
            animation: popupIn 150ms cubic-bezier(.34, 1.56, .64, 1) both;
        }

        @keyframes popupIn {
            from {
                opacity: 0;
                transform: scale(.94) translateY(4px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .popup-card {
            overflow: hidden;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-lg);
            background: var(--white);
            box-shadow: var(--shadow-lg);
        }

        .popup-header {
            padding: 11px 12px;
            border-bottom: 1px solid var(--slate-100);
            background: var(--slate-50);
        }

        .popup-label {
            color: var(--slate-400);
            font-size: 9.5px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.09em;
        }

        .popup-date {
            margin-top: 3px;
            color: var(--slate-900);
            font-size: 13px;
            font-weight: 900;
        }

        .popup-actions {
            display: grid;
            gap: 4px;
            padding: 8px;
        }

        .popup-action-btn {
            width: 100%;
            min-height: 34px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid transparent;
            border-radius: var(--radius-md);
            background: var(--slate-50);
            color: var(--slate-700);
            padding: 0 10px;
            text-align: left;
            font-size: 12px;
            font-weight: 900;
            transition:
                background var(--transition),
                color var(--transition),
                border-color var(--transition);
        }

        .popup-action-btn:hover {
            border-color: currentColor;
        }

        .popup-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: currentColor;
            flex-shrink: 0;
        }

        .popup-action-btn.available {
            color: black;
        }

        .popup-action-btn.unavailable {
            color: var(--rose-700);
        }

        .popup-action-btn.half {
            color: black;
        }

        .drawer-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1500;
            background: rgba(15, 23, 42, .30);
            backdrop-filter: blur(2px);
            opacity: 0;
            pointer-events: none;
            transition: opacity var(--transition);
        }

        .drawer-backdrop.show {
            opacity: 1;
            pointer-events: auto;
        }

        .schedule-drawer {
            position: fixed;
            top: 0;
            right: 0;
            z-index: 1600;
            width: min(500px, 96vw);
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--white);
            border-left: 1px solid var(--slate-200);
            box-shadow: var(--shadow-lg);
            transform: translateX(100%);
            transition: transform 260ms cubic-bezier(.4, 0, .2, 1);
        }

        .schedule-drawer.open {
            transform: translateX(0);
        }

        .drawer-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 20px;
            border-bottom: 1px solid var(--slate-100);
        }

        .drawer-eyebrow {
            color: var(--teal-700);
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0.10em;
            text-transform: uppercase;
        }

        .drawer-title {
            margin-top: 3px;
            color: var(--slate-900);
            font-size: 19px;
            font-weight: 900;
            letter-spacing: -0.02em;
        }

        .drawer-subtitle {
            margin-top: 2px;
            color: var(--slate-400);
            font-size: 12px;
            font-weight: 700;
        }

        .drawer-close {
            width: 33px;
            height: 33px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-md);
            background: var(--white);
            color: var(--slate-500);
            transition:
                color var(--transition),
                border-color var(--transition),
                background var(--transition);
        }

        .drawer-close:hover {
            color: var(--rose-700);
            border-color: var(--rose-100);
            background: var(--rose-50);
        }

        .drawer-close svg {
            width: 16px;
            height: 16px;
        }

        .drawer-body {
            flex: 1;
            overflow-y: auto;
            padding: 18px 20px;
        }

        .schedule-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px;
            margin-bottom: 18px;
            border: 1px solid var(--teal-100);
            border-radius: var(--radius-lg);
            background: var(--teal-50);
        }

        .schedule-banner-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            background: var(--teal-700);
            color: var(--white);
            flex-shrink: 0;
        }

        .schedule-banner-icon svg {
            width: 19px;
            height: 19px;
        }

        .schedule-banner-title {
            color: var(--teal-900);
            font-size: 13px;
            font-weight: 900;
        }

        .schedule-banner-subtitle {
            margin-top: 2px;
            color: var(--teal-700);
            font-size: 11.5px;
            font-weight: 700;
        }

        .schedule-section-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: var(--slate-400);
            font-size: 10.5px;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .schedule-section-label::after {
            content: "";
            height: 1px;
            flex: 1;
            background: var(--slate-100);
        }

        .schedule-table-wrap {
            overflow-x: auto;
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .schedule-table th {
            padding: 8px;
            border-bottom: 2px solid var(--slate-100);
            color: var(--slate-400);
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .schedule-table th:nth-child(1) {
            width: 22%;
        }

        .schedule-table th:nth-child(2) {
            width: 16%;
            text-align: center;
        }

        .schedule-table th:nth-child(3),
        .schedule-table th:nth-child(4) {
            width: 21%;
        }

        .schedule-table th:nth-child(5) {
            width: 20%;
            text-align: right;
        }

        .schedule-row {
            border-bottom: 1px solid var(--slate-100);
            transition: background var(--transition);
        }

        .schedule-row:hover {
            background: var(--slate-50);
        }

        .schedule-table td {
            padding: 10px 8px;
            color: var(--slate-700);
            font-size: 12.5px;
            font-weight: 800;
            vertical-align: middle;
            white-space: nowrap;
        }

        .schedule-day {
            color: var(--slate-800);
            font-weight: 900;
        }

        .toggle-wrap {
            display: flex;
            justify-content: center;
        }

        .schedule-toggle {
            position: relative;
            width: 37px;
            height: 22px;
            border: 0;
            border-radius: 999px;
            background: var(--slate-200);
            transition: background var(--transition);
        }

        .schedule-toggle::after {
            content: "";
            position: absolute;
            top: 3px;
            left: 3px;
            width: 16px;
            height: 16px;
            border-radius: 999px;
            background: var(--white);
            box-shadow: 0 1px 3px rgba(15, 23, 42, .20);
            transition: left var(--transition);
        }

        .schedule-toggle.on {
            background: var(--teal-600);
        }

        .schedule-toggle.on::after {
            left: 18px;
        }

        .time-display {
            color: var(--slate-600);
            font-family: 'DM Mono', Consolas, monospace !important;
            font-size: 11.5px;
            font-weight: 800;
        }

        .time-input {
            display: none;
            width: 100%;
            max-width: 105px;
            height: 31px;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-sm);
            background: var(--white);
            color: var(--slate-900);
            padding: 0 8px;
            outline: none;
            font-family: 'DM Mono', Consolas, monospace !important;
            font-size: 11.5px;
            font-weight: 800;
            transition:
                border-color var(--transition),
                box-shadow var(--transition);
        }

        .time-input:focus {
            border-color: var(--teal-600);
            box-shadow: 0 0 0 3px rgba(13, 148, 136, .12);
        }

        .schedule-row.editing .time-display {
            display: none;
        }

        .schedule-row.editing .time-input {
            display: inline-block;
        }

        .row-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-sm);
            background: var(--white);
            color: var(--slate-600);
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 900;
            transition:
                color var(--transition),
                border-color var(--transition),
                background var(--transition);
        }

        .row-action-btn:hover {
            color: var(--teal-700);
            border-color: var(--teal-100);
            background: var(--teal-50);
        }

        .row-action-btn.done {
            color: var(--white);
            border-color: var(--teal-700);
            background: var(--teal-700);
        }

        .row-action-btn.done:hover {
            background: var(--teal-900);
        }

        .row-action-btn svg {
            width: 12px;
            height: 12px;
        }

        .schedule-row .inline-save {
            display: none;
        }

        .schedule-row.editing .inline-save {
            display: inline-flex;
        }

        .schedule-row.editing .inline-edit {
            display: none;
        }

        .weekly-status-select {
            display: none !important;
        }

        .drawer-footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 14px 20px;
            border-top: 1px solid var(--slate-100);
            background: var(--white);
        }

        .btn-ghost,
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border-radius: var(--radius-md);
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 900;
            transition:
                background var(--transition),
                color var(--transition),
                border-color var(--transition),
                box-shadow var(--transition),
                transform var(--transition);
        }

        .btn-ghost {
            border: 1px solid var(--slate-200);
            background: var(--white);
            color: var(--slate-600);
        }

        .btn-ghost:hover {
            background: var(--slate-50);
            border-color: var(--slate-300);
        }

        .btn-primary {
            display: none;
            border: 0;
            background: var(--teal-700);
            color: var(--white);
        }

        .btn-primary.show {
            display: inline-flex;
        }

        .btn-primary:hover {
            background: var(--teal-900);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(15, 118, 110, .22);
        }

        .btn-ghost svg,
        .btn-primary svg {
            width: 13px;
            height: 13px;
        }

        .calendar-cell.is-past,
.calendar-cell.is-other,
.cal-cell.is-past,
.cal-cell.is-other {
    background: #f3f4f6 !important;
    color: #9ca3af !important;
    cursor: not-allowed !important;
    opacity: 0.72;
}

.calendar-cell.is-past:hover,
.calendar-cell.is-other:hover,
.cal-cell.is-past:hover,
.cal-cell.is-other:hover {
    background: #f3f4f6 !important;
}

.calendar-cell.is-past::after,
.calendar-cell.is-other::after,
.cal-cell.is-past::after,
.cal-cell.is-other::after {
    display: none !important;
}

.calendar-cell.is-past .calendar-day-number,
.calendar-cell.is-other .calendar-day-number,
.cal-cell.is-past .cell-num,
.cal-cell.is-other .cell-num {
    color: #9ca3af !important;
}

.calendar-cell.is-past .calendar-pill,
.calendar-cell.is-other .calendar-pill,
.cal-cell.is-past .cell-pill,
.cal-cell.is-other .cell-pill {
    background: #e5e7eb !important;
    color: #9ca3af !important;
}

.calendar-cell.is-past .calendar-pill-dot,
.calendar-cell.is-other .calendar-pill-dot,
.cal-cell.is-past .cell-pill-dot,
.cal-cell.is-other .cell-pill-dot {
    background: #9ca3af !important;
}

.calendar-cell.is-past .calendar-time,
.calendar-cell.is-other .calendar-time,
.cal-cell.is-past .cell-time,
.cal-cell.is-other .cell-time {
    display: none !important;
}

        @media (max-width: 1180px) {
            .availability-container {
                max-width: 990px;
            }

            .availability-layout {
                grid-template-columns: minmax(0, 710px) 260px;
            }

            .availability-calendar-card {
                max-width: 710px;
            }

            .availability-side {
                max-width: 260px;
            }
        }

        @media (max-width: 1060px) {
            .availability-container {
                max-width: 760px;
            }

            .availability-layout {
                grid-template-columns: 1fr;
            }

            .availability-calendar-card {
                max-width: 760px;
            }

            .availability-side {
                position: static;
                max-width: 760px;
                grid-template-columns: 1fr 1fr;
                align-items: stretch;
            }

            .side-card {
                grid-row: span 2;
            }

            .next-card {
                min-height: 80px;
            }
        }

        @media (max-width: 900px) {
            .page-main {
                width: 100%;
                margin-left: 0;
            }

            .availability-page {
                padding: 84px 12px 32px;
            }

            .availability-container {
                max-width: 100%;
            }
        }

        @media (max-width: 720px) {
            .calendar-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .calendar-toolbar-left,
            .calendar-toolbar-right {
                width: 100%;
                justify-content: space-between;
            }

            .calendar-tabs {
                flex: 1;
            }

            .calendar-tab {
                flex: 1;
            }

            .manage-schedule-btn {
                width: 100%;
            }

            .calendar-panel[data-panel="monthly"] {
                overflow-x: auto;
            }

            .calendar-weekdays,
            .calendar-grid,
            .calendar-legend {
                min-width: 600px;
            }

            .availability-side {
                grid-template-columns: 1fr;
            }

            .side-card {
                grid-row: auto;
            }

            .weekly-header {
                flex-direction: column;
            }

            .weekly-nav {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .weekly-nav-btn {
                width: 100%;
            }

            .schedule-drawer {
                width: 100vw;
            }

            .drawer-footer {
                flex-direction: column;
            }

            .btn-ghost,
            .btn-primary {
                width: 100%;
            }

            .schedule-table {
                min-width: 520px;
            }
        }

        @media (max-width: 480px) {
            .availability-page {
                padding: 78px 10px 28px;
            }

            .calendar-weekdays,
            .calendar-grid,
            .calendar-legend {
                min-width: 560px;
            }

            .calendar-cell {
                min-height: 54px;
            }

            .calendar-time {
                display: none;
            }

            .side-stats {
                grid-template-columns: 1fr;
            }

            .availability-popup {
                width: calc(100vw - 20px);
                left: 10px !important;
                right: 10px !important;
            }
        }
    </style>
</head>

<body>
<div class="page-layout">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <main class="page-main">
        <?php
            $pageTitle = 'Dentist Availability';
            include __DIR__ . '/../partials/topbar.php';
        ?>

        <div class="availability-page">
            <?php if ($flash_success || $flash_error): ?>
                <div class="availability-flash-stack">
                    <?php if ($flash_success): ?>
                        <div class="availability-flash success">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 6 9 17l-5-5"></path>
                            </svg>
                            <?= e($flash_success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($flash_error): ?>
                        <div class="availability-flash error">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M15 9l-6 6"></path>
                                <path d="M9 9l6 6"></path>
                            </svg>
                            <?= e($flash_error) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="availability-container">
                <div class="availability-layout">
                    <section class="availability-calendar-card" aria-label="Dentist availability calendar">
                        <div class="calendar-toolbar">
                            <div class="calendar-toolbar-left">
                                <div class="calendar-nav">
                                    <a
                                        class="calendar-nav-btn"
                                        href="<?= e($base . '/dentist/availability?month=' . $prevMonth) ?>"
                                        aria-label="Previous month"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M15 18l-6-6 6-6"></path>
                                        </svg>
                                    </a>

                                    <a
                                        class="calendar-nav-btn"
                                        href="<?= e($base . '/dentist/availability?month=' . $nextMonth) ?>"
                                        aria-label="Next month"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M9 18l6-6-6-6"></path>
                                        </svg>
                                    </a>
                                </div>

                                <div class="calendar-title">
                                    <?= e($monthTitle) ?>
                                    <span class="calendar-title-badge"><?= e($currentMonthDate->format('Y')) ?></span>
                                </div>
                            </div>

                            <div class="calendar-toolbar-right">
                                <div class="calendar-tabs" aria-label="Calendar view">
                                    <button type="button" class="calendar-tab active" data-view="monthly">
                                        Monthly
                                    </button>
                                    <button type="button" class="calendar-tab" data-view="weekly">
                                        Weekly
                                    </button>
                                </div>

                                <button type="button" class="manage-schedule-btn" id="openDrawerBtn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                        <path d="M16 2v4M8 2v4M3 10h18"></path>
                                    </svg>
                                    Manage
                                </button>
                            </div>
                        </div>

                        <div class="calendar-panel active" id="panelMonthly" data-panel="monthly">
                            <div class="calendar-weekdays">
                                <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
                                    <div class="calendar-weekday"><?= e($weekday) ?></div>
                                <?php endforeach; ?>
                            </div>

                            <div class="calendar-grid">
                                <?php foreach ($weeks as $week): ?>
                                    <?php foreach ($week as $dateObj): ?>
                                        <?php
                                            $dateKey = $dateObj->format('Y-m-d');
                                            $isOtherMonth = $dateObj->format('Y-m') !== $calendarMonth;
                                            $isToday = $dateKey === $today;
                                            $isPast = $dateKey < $today;

                                            $status = availabilityCalendarStatus(
                                                $dateKey,
                                                $schedules,
                                                $monthlyDateOverrides,
                                                $monthlyUnavailableDates,
                                                $today
                                            );

                                            $realStatus = $status['real_status'] ?? $status['status'] ?? 'unavailable';
                                            $isDisabled = $isOtherMonth || $isPast;

                                           if ($isPast || $isOtherMonth) {
    $pillClass = 'pill-past';
    $pillLabel = 'Past';
    $dotColor = 'var(--slate-300)';
} else {
    $pillClass = match ($realStatus) {
        'available' => 'pill-available',
        'half_day' => 'pill-half_day',
        'unavailable' => 'pill-unavailable',
        default => 'pill-past',
    };

    $pillLabel = match ($realStatus) {
        'available' => 'Available',
        'half_day' => 'Half Day',
        'unavailable' => 'Unavailable',
        default => 'Past',
    };

    $dotColor = match ($realStatus) {
        'available' => 'var(--green-600)',
        'half_day' => 'var(--amber-600)',
        'unavailable' => 'var(--rose-600)',
        default => 'var(--slate-300)',
    };
}

                                            $cellClasses = [
                                                'calendar-cell',
                                                (string) ($status['class'] ?? ''),
                                            ];

                                            if ($isOtherMonth) {
                                                $cellClasses[] = 'is-other';
                                            }

                                            if ($isToday) {
                                                $cellClasses[] = 'is-today';
                                            }

                                            if ($isPast) {
                                                $cellClasses[] = 'is-past';
                                            }

                                            if ($isDisabled) {
                                                $cellClasses[] = 'is-disabled';
                                            }

                                            $startLabel = availabilityTimeLabel($status['start_time'] ?? null);
                                            $endLabel = availabilityTimeLabel($status['end_time'] ?? null);
                                        ?>

                                        <div
                                            class="<?= e(implode(' ', array_filter($cellClasses))) ?>"
                                            data-date="<?= e($dateKey) ?>"
                                            <?= $isDisabled ? 'data-disabled="1"' : '' ?>
                                        >
                                            <div class="calendar-cell-top">
                                                <span class="calendar-day-number">
                                                    <?= e($dateObj->format('j')) ?>
                                                </span>
                                            </div>

                                            <div class="calendar-status">
                                                <span class="calendar-pill <?= e($pillClass) ?>">
                                                    <span
                                                        class="calendar-pill-dot"
                                                        style="background: <?= e($dotColor) ?>"
                                                        aria-hidden="true"
                                                    ></span>
                                                    <?= e($pillLabel) ?>
                                                </span>

                                                <?php if (!$isOtherMonth && !$isDisabled && $startLabel !== '' && $endLabel !== ''): ?>
                                                   
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>

                            <div class="calendar-legend">
                                <span class="legend-item">
                                    <span class="legend-dot" style="background: var(--green-600)"></span>
                                    Available
                                </span>

                                <span class="legend-item">
                                    <span class="legend-dot" style="background: var(--amber-600)"></span>
                                    Half Day
                                </span>

                                <span class="legend-item">
                                    <span class="legend-dot" style="background: var(--rose-600)"></span>
                                    Unavailable
                                </span>

                                <span class="legend-item">
                                    <span class="legend-dot" style="background: var(--slate-300)"></span>
                                    Past
                                </span>
                            </div>
                        </div>

                        <div class="calendar-panel" id="panelWeekly" data-panel="weekly">
                            <div class="weekly-shell">
                                <div class="weekly-header">
                                    <div>
                                        <div class="weekly-title" id="weeklyRangeLabel">
                                            Loading schedule...
                                        </div>
                                      
                                    </div>

                                    <div class="weekly-nav">
                                        <button type="button" class="weekly-nav-btn" id="weeklyPrevBtn">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M15 18l-6-6 6-6"></path>
                                            </svg>
                                            Prev
                                        </button>

                                        <button type="button" class="weekly-nav-btn" id="weeklyNextBtn">
                                            Next
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M9 18l6-6-6-6"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="weekly-table-wrap">
                                    <div class="weekly-grid" id="weeklyTimetable"></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <aside class="availability-side" aria-label="Availability summary">
                        <div class="side-card">
                            <div class="side-card-header">
                                <span class="side-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <path d="M12 16v-4"></path>
                                        <path d="M12 8h.01"></path>
                                    </svg>
                                </span>

                                <div>
                                    <div class="side-card-title"><?= e($monthTitle) ?> Overview</div>
                                    <div class="side-card-subtitle">Availability summary</div>
                                </div>
                            </div>

                            <div class="side-card-body">
                                <div class="side-note">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                        <path d="M16 2v4M8 2v4M3 10h18"></path>
                                    </svg>
                                    <span>
                                        Click a future date to set availability. Use Manage to update default weekly working hours.
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="next-card">
                            <div class="next-label">Next Available Date</div>

                            <div class="next-date">
                                <?= $nextAvailableDate ? e(date('l, M j', strtotime($nextAvailableDate))) : 'No upcoming availability' ?>
                            </div>
                        </div>

                        <div class="side-stats">
                            <div class="side-stat">
                                <div class="side-stat-value green"><?= (int) $availableDaysCount ?></div>
                                <div class="side-stat-label">Available Days</div>
                            </div>

                            <div class="side-stat">
                                <div class="side-stat-value amber"><?= (int) $halfDaysCount ?></div>
                                <div class="side-stat-label">Half Days</div>
                            </div>

                            <div class="side-stat">
                                <div class="side-stat-value rose"><?= (int) $unavailableDaysCount ?></div>
                                <div class="side-stat-label">Unavailable</div>
                            </div>

                            <div class="side-stat">
                                <div class="side-stat-value"><?= (int) $totalMonthDays ?></div>
                                <div class="side-stat-label">Total Days</div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="availabilityPopup" class="availability-popup" hidden>
    <div class="popup-card">
        <div class="popup-header">
            <div class="popup-label">Set Availability</div>
            <div class="popup-date" id="popupDateValue">—</div>
        </div>

        <div class="popup-actions">
            <form method="POST" action="<?= e($base . '/dentist/availability/override/store') ?>">
                <input type="hidden" name="_csrf_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="month" value="<?= e($calendarMonth) ?>">
                <input type="hidden" name="override_date" class="popup-date-field">
                <input type="hidden" name="is_available" value="1">
                <input type="hidden" name="availability_mode" value="full_day">
                <input type="hidden" name="start_time" value="">
                <input type="hidden" name="end_time" value="">
                <input type="hidden" name="reason" value="Marked as available from calendar">

                <button type="submit" class="popup-action-btn available">
                    
                    Mark Available
                </button>
            </form>

            <form method="POST" action="<?= e($base . '/dentist/availability/override/store') ?>">
                <input type="hidden" name="_csrf_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="month" value="<?= e($calendarMonth) ?>">
                <input type="hidden" name="override_date" class="popup-date-field">
                <input type="hidden" name="is_available" value="0">
                <input type="hidden" name="availability_mode" value="full_day">
                <input type="hidden" name="start_time" value="">
                <input type="hidden" name="end_time" value="">
                <input type="hidden" name="reason" value="Marked as unavailable from calendar">

                <button type="submit" class="popup-action-btn unavailable">
                 
                    Mark Unavailable
                </button>
            </form>

            <form method="POST" action="<?= e($base . '/dentist/availability/override/store') ?>">
                <input type="hidden" name="_csrf_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="month" value="<?= e($calendarMonth) ?>">
                <input type="hidden" name="override_date" class="popup-date-field">
                <input type="hidden" name="is_available" value="1">
                <input type="hidden" name="availability_mode" value="morning">
                <input type="hidden" name="start_time" value="">
                <input type="hidden" name="end_time" value="">
                <input type="hidden" name="reason" value="Marked as morning half day from calendar">

                <button type="submit" class="popup-action-btn half">
                   
                    Half Day: Morning
                </button>
            </form>

            <form method="POST" action="<?= e($base . '/dentist/availability/override/store') ?>">
                <input type="hidden" name="_csrf_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="month" value="<?= e($calendarMonth) ?>">
                <input type="hidden" name="override_date" class="popup-date-field">
                <input type="hidden" name="is_available" value="1">
                <input type="hidden" name="availability_mode" value="afternoon">
                <input type="hidden" name="start_time" value="">
                <input type="hidden" name="end_time" value="">
                <input type="hidden" name="reason" value="Marked as afternoon half day from calendar">

                <button type="submit" class="popup-action-btn half">
                   
                    Half Day: Afternoon
                </button>
            </form>
        </div>
    </div>
</div>

<div class="drawer-backdrop" id="drawerBackdrop"></div>

<aside class="schedule-drawer" id="weeklyDrawer" aria-hidden="true">
    <div class="drawer-header">
        <div>
            <div class="drawer-eyebrow">Schedule Settings</div>
            <div class="drawer-title">Manage Schedule</div>
            <div class="drawer-subtitle">Set default working hours per day.</div>
        </div>

        <button type="button" class="drawer-close" id="closeDrawerBtn" aria-label="Close schedule drawer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 6 6 18"></path>
                <path d="M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <div class="drawer-body">
        <div class="schedule-banner">
            <span class="schedule-banner-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                    <path d="M16 2v4M8 2v4M3 10h18"></path>
                </svg>
            </span>

            <div>
                <div class="schedule-banner-title">Weekly Working Hours</div>
                <div class="schedule-banner-subtitle">Applies as the default schedule each week.</div>
            </div>
        </div>

        <div class="schedule-section-label">
            Working Days &amp; Hours
        </div>

        <form method="POST" action="<?= e($base . '/dentist/availability/weekly') ?>" id="weeklyForm">
            <input type="hidden" name="_csrf_token" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="month" value="<?= e($calendarMonth) ?>">

            <div class="schedule-table-wrap">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>On</th>
                            <th>Start</th>
                            <th>End</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($dayLabels as $dayIndex => $dayLabel): ?>
                            <?php
                                $row = $schedules[$dayIndex] ?? [];
                                $isAvailable = (int) availabilityScheduleValue($row, 'is_available', 0) === 1;
                                $startValue = substr((string) availabilityScheduleValue($row, 'start_time', ''), 0, 5);
                                $endValue = substr((string) availabilityScheduleValue($row, 'end_time', ''), 0, 5);
                                $displayStart = $startValue !== '' ? date('g:i A', strtotime($startValue)) : '—';
                                $displayEnd = $endValue !== '' ? date('g:i A', strtotime($endValue)) : '—';
                            ?>

                            <tr class="schedule-row" data-day="<?= (int) $dayIndex ?>">
                                <td class="schedule-day"><?= e($dayLabel) ?></td>

                                <td>
                                    <div class="toggle-wrap">
                                        <button
                                            type="button"
                                            class="schedule-toggle <?= $isAvailable ? 'on' : '' ?>"
                                            data-day="<?= (int) $dayIndex ?>"
                                            aria-label="Toggle <?= e($dayLabel) ?>"
                                        ></button>

                                        <input
                                            type="hidden"
                                            name="days[<?= (int) $dayIndex ?>][is_available]"
                                            value="<?= $isAvailable ? '1' : '0' ?>"
                                            class="schedule-hidden-status"
                                            data-day="<?= (int) $dayIndex ?>"
                                        >

                                        <select class="weekly-status-select" data-day="<?= (int) $dayIndex ?>">
                                            <option value="0" <?= !$isAvailable ? 'selected' : '' ?>>Off</option>
                                            <option value="1" <?= $isAvailable ? 'selected' : '' ?>>On</option>
                                        </select>
                                    </div>
                                </td>

                                <td>
                                    <span class="time-display"><?= e($displayStart) ?></span>

                                    <input
                                        type="time"
                                        class="time-input"
                                        name="days[<?= (int) $dayIndex ?>][start_time]"
                                        value="<?= e($startValue) ?>"
                                    >
                                </td>

                                <td>
                                    <span class="time-display"><?= e($displayEnd) ?></span>

                                    <input
                                        type="time"
                                        class="time-input"
                                        name="days[<?= (int) $dayIndex ?>][end_time]"
                                        value="<?= e($endValue) ?>"
                                    >
                                </td>

                                <td style="text-align:right;">
                                    <button type="button" class="row-action-btn inline-edit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M12 20h9"></path>
                                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                                        </svg>
                                        Edit
                                    </button>

                                    <button type="button" class="row-action-btn done inline-save">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M20 6 9 17l-5-5"></path>
                                        </svg>
                                        Done
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <div class="drawer-footer">
        <button type="button" class="btn-ghost" id="editAllBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M12 20h9"></path>
                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
            </svg>
            Edit All
        </button>

        <button type="submit" form="weeklyForm" class="btn-primary" id="saveWeeklyBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 6 9 17l-5-5"></path>
            </svg>
            Save Schedule
        </button>
    </div>
</aside>

<script>
(function () {
    'use strict';

    var today = new Date().toISOString().slice(0, 10);
    var weeklyLoadedDate = today;
    var selectedDate = today;

    var dayCells = document.querySelectorAll('.calendar-cell[data-date]');
    var viewTabs = document.querySelectorAll('.calendar-tab');
    var panels = document.querySelectorAll('.calendar-panel');
    var popup = document.getElementById('availabilityPopup');
    var popupDateValue = document.getElementById('popupDateValue');
    var popupDateFields = document.querySelectorAll('.popup-date-field');

    var weeklyGrid = document.getElementById('weeklyTimetable');
    var weeklyRangeLabel = document.getElementById('weeklyRangeLabel');
    var weeklyPrevBtn = document.getElementById('weeklyPrevBtn');
    var weeklyNextBtn = document.getElementById('weeklyNextBtn');

    var drawer = document.getElementById('weeklyDrawer');
    var drawerBackdrop = document.getElementById('drawerBackdrop');
    var openDrawerBtn = document.getElementById('openDrawerBtn');
    var closeDrawerBtn = document.getElementById('closeDrawerBtn');

    var editAllBtn = document.getElementById('editAllBtn');
    var saveWeeklyBtn = document.getElementById('saveWeeklyBtn');
    var scheduleRows = document.querySelectorAll('.schedule-row');
    var scheduleToggles = document.querySelectorAll('.schedule-toggle');

    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function openDrawer() {
        if (!drawer || !drawerBackdrop) {
            return;
        }

        drawer.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
        drawerBackdrop.classList.add('show');
    }

    function closeDrawer() {
        if (!drawer || !drawerBackdrop) {
            return;
        }

        drawer.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
        drawerBackdrop.classList.remove('show');
    }

    function closePopup() {
        if (popup) {
            popup.hidden = true;
        }

        dayCells.forEach(function (cell) {
            cell.classList.remove('selected');
        });
    }

    function openPopup(cell) {
        if (!popup) {
            return;
        }

        var date = cell.dataset.date || '';
        var rect = cell.getBoundingClientRect();

        selectedDate = date;

        popupDateFields.forEach(function (field) {
            field.value = date;
        });

        if (popupDateValue) {
            var displayDate = new Date(date + 'T00:00:00');
            popupDateValue.textContent = displayDate.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'long',
                day: 'numeric'
            });
        }

        var popupWidth = 220;
        var popupHeight = 220;
        var top = rect.top + window.scrollY;
        var left = rect.right + window.scrollX + 8;

        if (left + popupWidth > window.scrollX + window.innerWidth - 12) {
            left = rect.left + window.scrollX - popupWidth - 8;
        }

        if (left < 12) {
            left = 12;
        }

        if (top + popupHeight > window.scrollY + window.innerHeight - 12) {
            top = window.scrollY + window.innerHeight - popupHeight - 12;
        }

        if (top < window.scrollY + 12) {
            top = window.scrollY + 12;
        }

        popup.style.top = top + 'px';
        popup.style.left = left + 'px';
        popup.hidden = false;
    }

    function switchView(viewName) {
        closePopup();

        viewTabs.forEach(function (tab) {
            tab.classList.toggle('active', tab.dataset.view === viewName);
        });

        panels.forEach(function (panel) {
            panel.classList.toggle('active', panel.dataset.panel === viewName);
        });

        if (viewName === 'weekly') {
            loadWeekly(selectedDate || today);
        }
    }

    function addDays(date, amount) {
        var dt = new Date(date + 'T00:00:00');
        dt.setDate(dt.getDate() + amount);

        return dt.toISOString().slice(0, 10);
    }

    function formatDateShort(date) {
        return new Date(date + 'T00:00:00').toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });
    }

    function formatTime(time) {
        if (!time) {
            return '';
        }

        var parts = String(time).split(':');
        var hour = parseInt(parts[0] || '0', 10);
        var minute = parts[1] || '00';

        return (hour % 12 || 12) + ':' + minute + ' ' + (hour >= 12 ? 'PM' : 'AM');
    }

    function normalizeTime(time) {
        var parts = String(time || '').split(':');

        return [
            parts[0] || '00',
            parts[1] || '00',
            parts[2] || '00'
        ].map(function (part) {
            return String(part).padStart(2, '0');
        }).join(':');
    }

    function buildSlots() {
        var slots = [];

        for (var hour = 7; hour < 20; hour++) {
            var start = String(hour).padStart(2, '0') + ':00:00';
            var end = String(hour + 1).padStart(2, '0') + ':00:00';

            slots.push({
                start: start,
                end: end,
                label: formatTime(start) + ' - ' + formatTime(end)
            });
        }

        return slots;
    }

    function appointmentDate(appointment) {
    return appointment.appointment_date || '';
}

function appointmentStartTime(appointment) {
    return appointment.start_time || '';
}

function appointmentEndTime(appointment) {
    return appointment.end_time || appointment.start_time || '';
}

function appointmentEndLabel(appointment) {
    if (String(appointment.is_ongoing || '0') === '1') {
        return 'Ongoing';
    }

    return formatTime(appointmentEndTime(appointment));
}

function appointmentOverlapsSlot(appointment, slot) {
    return normalizeTime(appointmentStartTime(appointment)) < slot.end
        && normalizeTime(appointmentEndTime(appointment)) > slot.start;
}

   function renderWeekly(data) {
    if (!weeklyGrid) {
        return;
    }

    weeklyGrid.innerHTML = '';

    var days = data.days || [];
    var appointments = data.appointments || [];
    var slots = buildSlots();

    if (!days.length) {
        weeklyGrid.innerHTML = '<div class="weekly-empty">No schedule data found for this week.</div>';
        return;
    }

    if (weeklyRangeLabel) {
        weeklyRangeLabel.textContent = formatDateShort(data.week_start) + ' - ' + formatDateShort(data.week_end);
    }

    weeklyGrid.insertAdjacentHTML('beforeend', '<div class="wg-corner"></div>');

    days.forEach(function (day) {
        weeklyGrid.insertAdjacentHTML(
            'beforeend',
            '<div class="wg-dayhead">' +
                '<div class="wg-day-name">' + esc(day.day_name) + '</div>' +
                '<div class="wg-day-date">' + esc(day.day_label) + '</div>' +
            '</div>'
        );
    });

    slots.forEach(function (slot) {
        weeklyGrid.insertAdjacentHTML(
            'beforeend',
            '<div class="wg-time">' + esc(slot.label) + '</div>'
        );

        days.forEach(function (day) {
            var matching = appointments.filter(function (appointment) {
                return appointmentDate(appointment) === day.date
                    && appointmentOverlapsSlot(appointment, slot);
            });

            var html = '';

            if (matching.length) {
                matching.forEach(function (appointment) {
                    html += '<div class="slot-appt">' +
                        '<div class="appt-time">' +
                            esc(formatTime(appointmentStartTime(appointment))) +
                            ' - ' +
                            esc(appointmentEndLabel(appointment)) +
                        '</div>' +
                        '<div class="appt-service">' +
                            esc(appointment.service_name || 'Dental Service') +
                        '</div>' +
                        '<div class="appt-patient">' +
                            esc(appointment.patient_name || 'Patient') +
                        '</div>' +
                    '</div>';
                });
            } else {
                html = '<div class="slot-open"></div>';
            }

            weeklyGrid.insertAdjacentHTML(
                'beforeend',
                '<div class="wg-slot" data-date="' + esc(day.date) + '">' + html + '</div>'
            );
        });
    });
}

    function loadWeekly(date) {
        if (!weeklyGrid) {
            return;
        }

        weeklyLoadedDate = date;
        weeklyGrid.innerHTML = '<div class="weekly-empty">Loading weekly schedule...</div>';

        fetch('/DentalClinic/public/dentist/availability/weekly-schedule?date=' + encodeURIComponent(date), {
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    return {
                        ok: response.ok,
                        data: JSON.parse(text)
                    };
                });
            })
            .then(function (response) {
                if (!response.ok || !response.data.success) {
                    weeklyGrid.innerHTML = '<div class="weekly-empty">' + esc(response.data.message || 'No weekly data available.') + '</div>';
                    return;
                }

                renderWeekly(response.data);
            })
            .catch(function () {
                weeklyGrid.innerHTML = '<div class="weekly-empty">Unable to load weekly schedule.</div>';
            });
    }

    function showSaveButton() {
        if (saveWeeklyBtn) {
            saveWeeklyBtn.classList.add('show');
        }
    }

    function syncToggle(day, value) {
        var hidden = document.querySelector('.schedule-hidden-status[data-day="' + day + '"]');
        var select = document.querySelector('.weekly-status-select[data-day="' + day + '"]');
        var button = document.querySelector('.schedule-toggle[data-day="' + day + '"]');

        if (hidden) {
            hidden.value = value;
        }

        if (select) {
            select.value = value;
        }

        if (button) {
            button.classList.toggle('on', value === '1');
        }
    }

    if (openDrawerBtn) {
        openDrawerBtn.addEventListener('click', openDrawer);
    }

    if (closeDrawerBtn) {
        closeDrawerBtn.addEventListener('click', closeDrawer);
    }

    if (drawerBackdrop) {
        drawerBackdrop.addEventListener('click', closeDrawer);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePopup();
            closeDrawer();
        }
    });

    viewTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            switchView(tab.dataset.view || 'monthly');
        });
    });

    dayCells.forEach(function (cell) {
        if (cell.dataset.disabled === '1') {
            return;
        }

        cell.addEventListener('click', function (event) {
            event.stopPropagation();

            var wasSelected = cell.classList.contains('selected');

            closePopup();

            if (!wasSelected) {
                cell.classList.add('selected');
                openPopup(cell);
            }
        });
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('#availabilityPopup') && !event.target.closest('.calendar-cell')) {
            closePopup();
        }
    });

    window.addEventListener('resize', closePopup);
    window.addEventListener('scroll', closePopup, { passive: true });

    if (weeklyPrevBtn) {
        weeklyPrevBtn.addEventListener('click', function () {
            loadWeekly(addDays(weeklyLoadedDate, -7));
        });
    }

    if (weeklyNextBtn) {
        weeklyNextBtn.addEventListener('click', function () {
            loadWeekly(addDays(weeklyLoadedDate, 7));
        });
    }

    if (editAllBtn) {
        editAllBtn.addEventListener('click', function () {
            scheduleRows.forEach(function (row) {
                row.classList.add('editing');
            });

            showSaveButton();
        });
    }

    scheduleRows.forEach(function (row) {
        var editBtn = row.querySelector('.inline-edit');
        var saveBtn = row.querySelector('.inline-save');
        var select = row.querySelector('.weekly-status-select');

        if (editBtn) {
            editBtn.addEventListener('click', function () {
                row.classList.add('editing');
                showSaveButton();
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                if (select) {
                    syncToggle(select.dataset.day, select.value);
                }

                row.classList.remove('editing');
            });
        }

        if (select) {
            select.addEventListener('change', function () {
                syncToggle(select.dataset.day, select.value);
                showSaveButton();
            });
        }
    });

    scheduleToggles.forEach(function (button) {
        button.addEventListener('click', function () {
            var day = button.dataset.day;
            var hidden = document.querySelector('.schedule-hidden-status[data-day="' + day + '"]');
            var row = document.querySelector('.schedule-row[data-day="' + day + '"]');
            var nextValue = hidden && hidden.value === '1' ? '0' : '1';

            syncToggle(day, nextValue);

            if (row) {
                row.classList.add('editing');
            }

            showSaveButton();
        });
    });
})();
</script>
</body>
</html>