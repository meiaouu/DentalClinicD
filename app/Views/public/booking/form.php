<?php

use App\Core\Csrf;

$errors = $errors ?? [];
$old = $old ?? [];
$services = $services ?? [];
$isGuest = $isGuest ?? true;
$regions = $regions ?? [];

$prefillContact = $prefillContact ?? ($old['contact_number'] ?? '');
$prefillEmail = $prefillEmail ?? ($old['email'] ?? '');

$contactValue = (string) ($old['contact_number'] ?? $prefillContact);
$emailValue = (string) ($old['email'] ?? $prefillEmail);

$preferredDateValue = (string) ($old['preferred_date'] ?? '');
$preferredStartTimeValue = (string) ($old['preferred_start_time'] ?? '');

$showPatientDetails = $preferredDateValue !== '' && $preferredStartTimeValue !== '';

if (!function_exists('booking_e')) {
    function booking_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$selectedServiceIds = isset($selectedServiceIds) && is_array($selectedServiceIds) ? $selectedServiceIds : [];

if (empty($selectedServiceIds) && !empty($old['service_ids']) && is_array($old['service_ids'])) {
    $selectedServiceIds = $old['service_ids'];
}

if (empty($selectedServiceIds) && !empty($old['service_id'])) {
    $selectedServiceIds = [(int) $old['service_id']];
}

if (empty($selectedServiceIds) && !empty($selectedServiceId)) {
    $selectedServiceIds = [(int) $selectedServiceId];
}

$selectedServiceIds = array_values(array_unique(array_filter(array_map('intval', $selectedServiceIds))));
$primaryServiceId = $selectedServiceIds[0] ?? 0;

$oldOptionValues = [];

foreach ($old as $key => $value) {
    if (strpos((string) $key, 'option_') === 0) {
        $oldOptionValues[$key] = $value;
    }
}

ob_start();
?>

<style>
*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html {
    scroll-behavior: smooth;
}

:root {
  

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

    --sidebar-w: 240px;
    --slots-w: 185px;
    --r-sm: 5px;
    --r-md: 8px;
    --r-lg: 12px;
    --r-xl: 2px;
    --sh-card: 0 6px 26px rgba(11, 31, 58, .08), 0 1px 3px rgba(11, 31, 58, .05);
    --sh-sm: 0 2px 8px rgba(11, 31, 58, .07);
}

body {
 
    background: var(--cream);
    color: var(--stone-800);
    min-height: 100vh;
    font-size: 14px;
    line-height: 1.6;
    overflow-x: hidden;
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

.bk-bg {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    background:
        radial-gradient(ellipse 60% 50% at 10% 5%, rgba(46, 196, 165, .07) 0%, transparent 60%),
        radial-gradient(ellipse 50% 40% at 90% 90%, rgba(11, 31, 58, .06) 0%, transparent 55%);
}

.bk-bg-pattern {
    position: absolute;
    inset: 0;
    opacity: .025;
    background-image:
        linear-gradient(var(--navy) 1px, transparent 1px),
        linear-gradient(90deg, var(--navy) 1px, transparent 1px);
    background-size: 32px 32px;
}

.bk-page {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    padding: 26px 16px 56px;
}

.bk-wrap {
    max-width: 920px;
    margin: 0 auto;
}

.clinic-header {
    margin-bottom: 22px;
}

.booking-hero {
    margin-bottom: 16px;
    text-align: left;
}

.booking-hero-title {
    font-family: var(--serif);
    font-size: clamp(25px, 4vw, 36px);
    font-weight: 600;
    color: var(--navy);
    letter-spacing: -.02em;
    line-height: 1.15;
    margin-bottom: 6px;
}

.booking-hero-title em {
    font-style: italic;
    color: var(--mint-dark);
}

.booking-hero-sub {
    font-size: 14px;
    color: var(--stone-600);
    line-height: 1.7;
}

.stepper {
    display: flex;
    align-items: flex-start;
    max-width: 430px;
    margin: 18px auto 24px;
}

.step {
    flex: 1;
    text-align: center;
    position: relative;
}

.step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 15px;
    left: 50%;
    width: 100%;
    height: 2px;
    background: var(--stone-200);
}

.step.done:not(:last-child)::after,
.step.active:not(:last-child)::after {
    background: var(--mint);
}

.step-node {
    position: relative;
    z-index: 1;
    width: 30px;
    height: 30px;
    margin: 0 auto;
    border-radius: 50%;
    border: 2px solid var(--stone-200);
    background: var(--warm-white);
    color: var(--stone-400);
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 800;
    transition: all .25s;
}

.step.active .step-node {
    border-color: var(--mint);
    background: var(--mint);
    color: #fff;
    box-shadow: 0 0 0 4px rgba(46, 196, 165, .18);
}

.step.done .step-node {
    border-color: var(--mint-dark);
    background: var(--mint-dark);
    color: #fff;
}

.step-label {
    margin-top: 7px;
    font-size: 10px;
    font-weight: 700;
    color: var(--stone-400);
    text-transform: uppercase;
    letter-spacing: .06em;
}

.step.active .step-label,
.step.done .step-label {
    color: var(--mint-dark);
}

.bk-card {
    background: var(--warm-white);
    border: 1px solid var(--stone-200);
    border-radius: var(--r-xl);
    box-shadow: var(--sh-card);
    overflow: visible;
    animation: fadeUp .45s ease .1s both;
}

.bk-layout {
    display: grid;
    grid-template-columns: var(--sidebar-w) minmax(0, 1fr) var(--slots-w);
    min-height: 440px;
}

.bk-col {
    padding: 20px 18px;
}

.bk-col + .bk-col {
    border-left: 1px solid var(--stone-200);
}

.card-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 16px;
    margin-bottom: 16px;
    border-bottom: 1px solid var(--stone-200);
}

.card-brand-tooth {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: var(--mint-soft);
    border: 1px solid var(--mint-border);
    display: grid;
    place-items: center;
}

.card-brand-tooth svg {
    color: var(--mint-dark);
}

.card-brand-text .name {
    font-family: var(--serif);
    font-size: 14px;
    font-weight: 700;
    color: var(--navy);
    line-height: 1.2;
}

.card-brand-text .tag {
    font-size: 11px;
    color: var(--stone-600);
}

.field {
    margin-bottom: 14px;
}

.field:last-child {
    margin-bottom: 0;
}

.field-label {
    display: block;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--stone-600);
    margin-bottom: 7px;
}

.field-label .opt {
    font-weight: 500;
    text-transform: none;
    letter-spacing: 0;
    color: var(--stone-400);
    font-size: 11px;
}

.form-control,
.form-select {
    width: 100%;
    min-height: 40px;
    border: 1.5px solid var(--stone-200);
    border-radius: var(--r-md);
    background: var(--warm-white);
    color: var(--stone-800);
    padding: 0 12px;
    outline: none;
    transition: border-color .18s, box-shadow .18s;
    font-family: var(--sans);
    font-size: 13px;
}

textarea.form-control {
    min-height: 86px;
    padding: 10px 12px;
    resize: vertical;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--mint);
    box-shadow: 0 0 0 3px rgba(46, 196, 165, .14);
}

.form-control[readonly] {
    background: var(--stone-100);
    color: var(--stone-600);
}

.form-control.has-error,
.form-select.has-error,
.multi-trigger.has-error {
    border-color: var(--red) !important;
    box-shadow: 0 0 0 3px rgba(229, 62, 62, .12) !important;
}

.form-select {
    appearance: none;
    -webkit-appearance: none;
    padding-right: 34px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236e6860' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
}

.field-error {
    margin-top: 5px;
    color: var(--red);
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 4px;
}

.multi-wrap {
    position: relative;
}

.multi-trigger {
    width: 100%;
    min-height: 40px;
    border: 1.5px solid var(--stone-200);
    border-radius: var(--r-md);
    background: var(--warm-white);
    padding: 0 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    text-align: left;
    font-family: var(--sans);
    font-size: 13px;
    color: var(--stone-800);
    transition: border-color .18s, box-shadow .18s;
}

.multi-trigger:hover,
.multi-wrap.open .multi-trigger {
    border-color: var(--mint);
    box-shadow: 0 0 0 3px rgba(46, 196, 165, .13);
}

.multi-trigger-label {
    flex: 1;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    color: var(--stone-400);
}

.multi-trigger-label.has-val {
    color: var(--stone-800);
}

.multi-badge {
    display: none;
    min-width: 20px;
    height: 20px;
    border-radius: 999px;
    background: var(--mint);
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    align-items: center;
    justify-content: center;
}

.multi-badge.show {
    display: inline-flex;
}

.multi-chevron {
    color: var(--stone-400);
    transition: transform .2s;
    flex-shrink: 0;
}

.multi-wrap.open .multi-chevron {
    transform: rotate(180deg);
}

.multi-menu {
    display: none;
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    z-index: 60;
    background: var(--warm-white);
    border: 1.5px solid var(--stone-200);
    border-radius: var(--r-lg);
    box-shadow: 0 16px 40px rgba(11, 31, 58, .16);
    padding: 6px;
    max-height: 230px;
    overflow-y: auto;
}

.multi-wrap.open .multi-menu {
    display: block;
}

.service-opt {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 10px;
    border-radius: var(--r-sm);
    cursor: pointer;
    transition: background .12s;
    font-size: 13px;
    font-weight: 600;
    color: var(--stone-800);
    border: 1px solid transparent;
}

.service-opt:hover {
    background: var(--stone-100);
}

.service-opt.selected {
    background: var(--mint-soft);
    border-color: var(--mint-border);
    color: var(--navy);
}

.service-opt input[type=checkbox] {
    width: 16px;
    height: 16px;
    accent-color: var(--mint-dark);
    flex-shrink: 0;
}

.service-opt-check {
    width: 16px;
    height: 16px;
    border: 1.5px solid var(--stone-200);
    border-radius: 4px;
    background: var(--warm-white);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all .15s;
}

.service-opt.selected .service-opt-check {
    background: var(--mint-dark);
    border-color: var(--mint-dark);
}

.service-opt-check svg {
    display: none;
    color: #fff;
}

.service-opt.selected .service-opt-check svg {
    display: block;
}

.dyn-wrap {
    display: none;
    margin-top: 14px;
    padding: 12px;
    background: var(--stone-100);
    border: 1px solid var(--stone-200);
    border-radius: var(--r-md);
}

.dyn-wrap.show {
    display: block;
}

.dyn-label {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--mint-dark);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.cal-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.cal-month {
    font-family: var(--serif);
    font-size: 18px;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -.01em;
}

.cal-nav {
    display: flex;
    gap: 6px;
}

.cal-nav-btn {
    width: 50px;
    height: 30px;
    
    border-radius: var(--r-sm);
    background: var(--warm-white);
    color: var(--stone-600);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .15s;
}

.cal-nav-btn:hover {
    border-color: var(--mint);
    background: var(--mint-soft);
    color: var(--mint-dark);
}

.cal-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    margin-bottom: 6px;
    gap: 2px;
}

.cal-weekday {
    text-align: center;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--stone-400);
    padding: 3px 0;
}

.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}

.cal-day {
    aspect-ratio: 1;
    border: 0;
    border-radius: var(--r-sm);
    background: transparent;
    color: var(--stone-800);
    font-size: 12px;
    font-weight: 600;
    position: relative;
    transition: all .15s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cal-day:hover:not(:disabled) {
    background: var(--mint-soft);
    color: var(--mint-dark);
}

.cal-day.today {
    outline: 2px solid var(--mint);
    outline-offset: -2px;
    font-weight: 800;
}

.cal-day.available {
    font-weight: 800;
    color: var(--navy);
}

.cal-day.available::after {
    content: '';
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--mint);
}

.cal-day.selected {
    background: var(--navy);
    color: #fff;
    font-weight: 800;
    box-shadow: 0 2px 10px rgba(11, 31, 58, .25);
}

.cal-day.selected::after {
    background: rgba(255, 255, 255, .7);
}

.cal-day.other-month {
    color: var(--stone-200);
}

.cal-day:disabled {
    cursor: not-allowed;
    opacity: .4;
}

.date-status-box {
    margin-top: 5px;
    padding: 1px 12px;
    border-radius: var(--r-xl);
    background: var(--stone-100);
    border: 1px solid var(--stone-200);
}

.date-status-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--stone-800);
    margin-bottom: 3px;
}

.date-status-sub {
    font-size: 12px;
    color: var(--stone-600);
    line-height: 1.5;
}

.date-status-box.available {
    background: #f2f2f2;
    
}

.date-status-box.available .date-status-title {
    color: var(--mint-dark);
}

.cal-legend {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 12px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: var(--stone-600);
}

.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.legend-dot.available {
    background: var(--mint);
}

.legend-dot.selected {
    background: var(--navy);
}

.legend-dot.today {
    background: transparent;
    border: 2px solid var(--mint);
}

.info-box {
    padding: 12px 14px;
    border-radius: var(--r-md);
    background: var(--stone-100);
    border: 1px dashed var(--stone-200);
    color: var(--stone-600);
    font-size: 13px;
    line-height: 1.6;
    text-align: center;
}

.slots-header {
    margin-bottom: 14px;
}

.slots-header-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}

.slots-title {
    font-family: var(--serif);
    font-size: 16px;
    font-weight: 700;
    color: var(--navy);
}

.time-fmt {
    display: inline-flex;
    align-items: center;
    background: var(--stone-100);
    border: 1px solid var(--stone-200);
    border-radius: 6px;
    padding: 2px;
}

.time-fmt-btn {
    padding: 3px 7px;
    border: 0;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 800;
    color: var(--stone-600);
    background: transparent;
    transition: all .15s;
}

.time-fmt-btn.on {
    background: var(--warm-white);
    color: var(--navy);
    box-shadow: var(--sh-sm);
}

.slots-date-label {
    font-size: 12px;
    color: var(--stone-400);
}

.slots-list {
    display: grid;
    gap: 6px;
    max-height: 310px;
    overflow-y: auto;
    padding-right: 2px;
}

.slots-list::-webkit-scrollbar {
    width: 4px;
}

.slots-list::-webkit-scrollbar-track {
    background: var(--stone-100);
    border-radius: 4px;
}

.slots-list::-webkit-scrollbar-thumb {
    background: var(--stone-200);
    border-radius: 4px;
}

.slot-btn {
    width: 100%;
    min-height: 36px;
    border: 1.5px solid var(--stone-200);
    border-radius: var(--r-sm);
    background: var(--warm-white);
    color: var(--stone-800);
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all .15s;
    position: relative;
    overflow: hidden;
}

.slot-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--mint-soft);
    opacity: 0;
    transition: opacity .15s;
}

.slot-btn:hover:not(:disabled)::before {
    opacity: 1;
}

.slot-btn:hover:not(:disabled) {
    border-color: var(--mint);
    color: var(--mint-dark);
}

.slot-btn.on {
    border-color: var(--mint-dark);
    background: var(--mint-dark);
    color: #fff;
    box-shadow: 0 3px 12px rgba(31, 168, 140, .3);
}

.slot-btn.on::before {
    display: none;
}

.slot-btn:disabled {
    opacity: .35;
    cursor: not-allowed;
}

.continue-cta {
    display: none;
    margin-top: 14px;
    padding: 12px;
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    border-radius: var(--r-md);
    text-align: center;
    animation: fadeUp .3s ease both;
}

.continue-cta.show {
    display: block;
}

.continue-cta-text {
    font-size: 12px;
    color: rgba(255, 255, 255, .7);
    margin-bottom: 8px;
}

.continue-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: 100%;
    justify-content: center;
    padding: 9px 14px;
    background: var(--mint);
    color: #fff;
    border: none;
    border-radius: var(--r-sm);
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    transition: background .15s;
}

.continue-cta-btn:hover {
    background: var(--mint-dark);
}

.details-flow {
    margin-top: 16px;
    animation: fadeUp .4s ease both;
}

.details-flow.hidden {
    display: none;
}

.details-card {
    background: var(--warm-white);
    border: 1px solid var(--stone-200);
    border-radius: var(--r-xl);
    box-shadow: var(--sh-card);
    overflow: hidden;
    display: grid;
    grid-template-columns: 260px 1fr;
}

.details-sidebar {
    padding: 24px 22px;
    background: var(--navy);
    color: #fff;
}

.details-sidebar-title {
    font-family: var(--serif);
    font-size: 21px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 14px;
    letter-spacing: -.01em;
}

.booking-summary-card {
    background: rgba(255, 255, 255, .07);
    border: 1px solid rgba(255, 255, 255, .12);
    border-radius: var(--r-md);
    padding: 14px;
}

.bsc-row {
    padding: 8px 0;
    border-bottom: 1px solid rgba(255, 255, 255, .08);
}

.bsc-row:last-child {
    border-bottom: none;
}

.bsc-key {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, .45);
    margin-bottom: 3px;
}

.bsc-val {
    font-size: 13px;
    font-weight: 600;
    color: #fff;
    line-height: 1.35;
}

.bsc-val .na {
    color: rgba(255, 255, 255, .35);
    font-style: italic;
    font-weight: 400;
}

.details-trust {
    margin-top: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.trust-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: rgba(255, 255, 255, .55);
}

.trust-item svg {
    color: var(--mint);
    flex-shrink: 0;
}

.details-main-col {
    padding: 26px 28px;
}

.form-section {
    margin-bottom: 22px;
}

.form-section + .form-section {
    padding-top: 18px;
    border-top: 1px solid var(--stone-100);
}

.form-section-title {
    font-family: var(--serif);
    font-size: 17px;
    font-weight: 700;
    color: var(--navy);
    letter-spacing: -.01em;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-section-title svg {
    color: var(--mint-dark);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
}

.form-grid .full {
    grid-column: 1 / -1;
}

.form-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--stone-100);
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 42px;
    min-width: 128px;
    padding: 0 20px;
    border-radius: var(--r-md);
    font-family: var(--sans);
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    border: none;
    transition: all .18s;
    cursor: pointer;
}

.btn-primary {
    background: var(--navy);
    color: #fff;
    box-shadow: 0 4px 14px rgba(11, 31, 58, .25);
}

.btn-primary:hover {
    background: var(--navy-light);
    box-shadow: 0 6px 20px rgba(11, 31, 58, .32);
    transform: translateY(-1px);
}

.btn-ghost {
    background: transparent;
    color: var(--stone-600);
    border: 1.5px solid var(--stone-200);
}

.btn-ghost:hover {
    border-color: var(--stone-400);
    color: var(--stone-800);
}

.bk-footer {
    text-align: center;
    margin-top: 22px;
    font-size: 12px;
    color: var(--stone-400);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    flex-wrap: wrap;
}

.bk-footer-sep {
    color: var(--stone-200);
}

@keyframes fadeDown {
    from {
        opacity: 0;
        transform: translateY(-14px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(16px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 980px) {
    .bk-wrap {
        max-width: 760px;
    }

    .bk-layout {
        grid-template-columns: 1fr 1fr;
    }

    .col-service {
        grid-column: 1 / -1;
    }

    .col-calendar {
        border-left: none !important;
        border-top: 1px solid var(--stone-200);
    }

    .col-slots {
        border-top: 1px solid var(--stone-200);
    }
}

@media (max-width: 760px) {
    .bk-layout {
        grid-template-columns: 1fr;
    }

    .bk-col + .bk-col {
        border-left: none;
        border-top: 1px solid var(--stone-200);
    }

    .details-card {
        grid-template-columns: 1fr;
    }

    .details-sidebar {
        order: 1;
    }

    .details-main-col {
        order: 0;
    }
}

@media (max-width: 560px) {
    .bk-page {
        padding: 20px 10px 56px;
    }

    .booking-hero-title {
        font-size: 25px;
    }

    .stepper {
        max-width: 100%;
    }

    .step-label {
        font-size: 9px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-grid .full {
        grid-column: auto;
    }

    .form-actions {
        flex-direction: column-reverse;
    }

    .btn {
        width: 100%;
    }
}




body.privacy-modal-open {
    overflow: hidden;
}

.privacy-consent-modal {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;

    background: rgba(11, 31, 58, .42);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
}

.privacy-consent-modal.show {
    display: flex;
}

.privacy-consent-card {
    position: relative;
    z-index: 100000;
    width: min(680px, 100%);
    max-height: min(86vh, 760px);
    background: var(--warm-white);
    border: 1px solid var(--stone-200);
    border-radius: 12px;
    box-shadow: 0 24px 80px rgba(11, 31, 58, .28);
    overflow: hidden;
    filter: none;
    transform: translateZ(0);
    animation: consentPop .18s ease both;
}

@keyframes consentPop {
    from {
        opacity: 0;
        transform: translateY(10px) scale(.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.privacy-consent-head {
    padding: 18px 20px;
    border-bottom: 1px solid var(--stone-200);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
}

.privacy-consent-title {
    margin: 0;
    color: var(--navy);
    font-family: var(--serif);
    font-size: 22px;
    line-height: 1.2;
}

.privacy-consent-sub {
    margin-top: 5px;
    color: var(--stone-600);
    font-size: 13px;
    line-height: 1.5;
}

.privacy-consent-close {
    width: 34px;
    height: 34px;
    border: 1px solid var(--stone-200);
    border-radius: 8px;
    background: var(--warm-white);
    color: var(--stone-600);
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
}

.privacy-consent-body {
    padding: 18px 20px;
}

.privacy-consent-scroll {
    height: min(370px, 46vh);
    overflow-y: auto;
    padding-right: 10px;
    color: var(--stone-800);
    line-height: 1.7;
    font-size: 13.5px;
}

.privacy-consent-scroll h3 {
    margin: 18px 0 8px;
    color: var(--navy);
    font-size: 15px;
}

.privacy-consent-scroll h3:first-child {
    margin-top: 0;
}

.privacy-consent-scroll p {
    margin: 0 0 10px;
}

.privacy-consent-scroll ul {
    margin: 0 0 12px 18px;
}

.privacy-consent-scroll li {
    margin-bottom: 5px;
}

.privacy-consent-end-note {
    margin-top: 14px;
    padding: 12px;
    border: 1px dashed var(--mint-border);
    border-radius: 8px;
    background: var(--mint-soft);
    color: var(--navy);
    font-weight: 700;
}

.privacy-consent-actions {
    padding: 16px 20px 20px;
    border-top: 1px solid var(--stone-200);
    background: #fbfaf7;
}

.privacy-consent-read-hint {
    margin-bottom: 12px;
    font-size: 12px;
    color: var(--stone-600);
    line-height: 1.5;
}

.privacy-consent-check-wrap {
    display: none;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 14px;
    padding: 12px;
    border-radius: 8px;
    background: var(--warm-white);
    border: 1px solid var(--stone-200);
}

.privacy-consent-check-wrap.show {
    display: flex;
}

.privacy-consent-check-wrap input {
    width: 17px;
    height: 17px;
    margin-top: 3px;
    accent-color: var(--mint-dark);
    flex-shrink: 0;
}

.privacy-consent-check-wrap span {
    font-size: 13px;
    line-height: 1.55;
    color: var(--stone-800);
}

.privacy-consent-check-wrap a {
    color: var(--mint-dark);
    font-weight: 800;
}

.privacy-consent-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
}

.privacy-consent-btn {
    min-height: 40px;
    border-radius: 8px;
    padding: 0 16px;
    font-size: 13px;
    font-weight: 800;
    border: 0;
    cursor: pointer;
}

.privacy-consent-btn.cancel {
    background: var(--warm-white);
    border: 1px solid var(--stone-200);
    color: var(--stone-600);
}

.privacy-consent-btn.continue {
    background: var(--navy);
    color: #ffffff;
}

.privacy-consent-btn.continue:disabled {
    opacity: .45;
    cursor: not-allowed;
}

@media (max-width: 560px) {
    .privacy-consent-modal {
        padding: 10px;
    }

    .privacy-consent-card {
        max-height: 92vh;
    }

    .privacy-consent-head,
    .privacy-consent-body,
    .privacy-consent-actions {
        padding-left: 14px;
        padding-right: 14px;
    }

    .privacy-consent-scroll {
        height: 48vh;
    }

    .privacy-consent-footer {
        flex-direction: column-reverse;
    }

    .privacy-consent-btn {
        width: 100%;
    }
}

@media (max-width: 560px) {
    .privacy-consent-modal {
        padding: 10px;
    }

    .privacy-consent-card {
        max-height: 92vh;
    }

    .privacy-consent-head,
    .privacy-consent-body,
    .privacy-consent-actions {
        padding-left: 14px;
        padding-right: 14px;
    }

    .privacy-consent-scroll {
        height: 48vh;
    }

    .privacy-consent-footer {
        flex-direction: column-reverse;
    }

    .privacy-consent-btn {
        width: 100%;
    }
}

</style>

<div class="bk-bg">
    <div class="bk-bg-pattern"></div>
</div>

<div class="bk-page">
    <div class="bk-wrap">
        <header class="clinic-header">
            <div class="booking-hero">
                <h1 class="booking-hero-title">Schedule Your <em>Visit</em></h1>
             
            </div>

            <div class="stepper">
                <div class="step <?= $showPatientDetails ? 'done' : 'active' ?>">
                    <div class="step-node">
                        <?php if ($showPatientDetails): ?>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        <?php else: ?>
                            1
                        <?php endif; ?>
                    </div>
                    <div class="step-label">Schedule</div>
                </div>

                <div class="step <?= $showPatientDetails ? 'active' : '' ?>">
                    <div class="step-node">2</div>
                    <div class="step-label">Your Info</div>
                </div>

                <div class="step">
                    <div class="step-node">3</div>
                    <div class="step-label">Review</div>
                </div>

                <div class="step">
                    <div class="step-node">4</div>
                    <div class="step-label">Confirmed</div>
                </div>
            </div>
        </header>

        <form method="POST" action="/DentalClinic/public/book/review" id="bookingForm" novalidate>
            <?= Csrf::inputField(); ?>

            <input type="hidden" name="service_id" id="service_id" value="<?= (int) $primaryServiceId ?>">
            <input type="hidden" name="preferred_date" id="preferred_date" value="<?= booking_e($preferredDateValue) ?>">
            <input type="hidden" name="preferred_start_time" id="preferred_start_time" value="<?= booking_e($preferredStartTimeValue) ?>">
            <input type="hidden" name="preferred_dentist_id" id="preferred_dentist_id" value="">
            <input
    type="hidden"
    name="privacy_consent"
    id="privacy_consent"
    value="<?= !empty($old['privacy_consent']) ? '1' : '0' ?>"
>

            <div class="bk-card">
                <div class="bk-layout">
                    <div class="bk-col col-service">
                        <div class="card-brand">
                        

                           
                        </div>

                        <div class="field">
                            <label class="field-label">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline;margin-right:4px;color:var(--mint-dark);">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                </svg>
                                Dental Service
                            </label>

                            <div class="multi-wrap" id="serviceWrap">
                                <button type="button" class="multi-trigger" id="serviceTrigger" aria-haspopup="listbox" aria-expanded="false">
                                    <span class="multi-trigger-label" id="triggerLabel">Choose a service…</span>
                                    <span class="multi-badge" id="serviceBadge">0</span>
                                    <svg class="multi-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 12 15 18 9"/>
                                    </svg>
                                </button>

                                <div class="multi-menu" id="serviceMenu" role="listbox" aria-multiselectable="true">
                                    <?php if (!empty($services)): ?>
                                        <?php foreach ($services as $service): ?>
                                            <?php
                                                $sid = is_array($service) ? (int) ($service['service_id'] ?? 0) : (int) ($service->service_id ?? 0);
                                                $sname = is_array($service) ? (string) ($service['service_name'] ?? '') : (string) ($service->service_name ?? '');
                                                $checked = in_array($sid, $selectedServiceIds, true);
                                            ?>

                                            <label class="service-opt <?= $checked ? 'selected' : '' ?>" data-svc role="option" aria-selected="<?= $checked ? 'true' : 'false' ?>">
                                                <input type="checkbox" name="service_ids[]" value="<?= $sid ?>" data-svc-cb <?= $checked ? 'checked' : '' ?>>

                                               

                                                <span><?= booking_e($sname) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="info-box">No services available at this time.</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($errors['service_id'])): ?>
                                <div class="field-error">
                                    <?= booking_e($errors['service_id'][0]) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="dyn-wrap" id="dynWrap">
                            <div class="dyn-label">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                Service Questions
                            </div>

                            <div id="dynGrid" class="form-grid"></div>
                        </div>
                    </div>

                    <div class="bk-col col-calendar">
                        <div class="cal-top">
                            <h2 class="cal-month" id="calMonth">Loading…</h2>

                            <div class="cal-nav">
                                <button type="button" class="cal-nav-btn" id="prevMonth" aria-label="Previous month">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="15 18 9 12 15 6"/>
                                    </svg>
                                </button>

                                <button type="button" class="cal-nav-btn" id="nextMonth" aria-label="Next month">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="cal-weekdays" aria-hidden="true">
                            <div class="cal-weekday">Mon</div>
                            <div class="cal-weekday">Tue</div>
                            <div class="cal-weekday">Wed</div>
                            <div class="cal-weekday">Thu</div>
                            <div class="cal-weekday">Fri</div>
                            <div class="cal-weekday">Sat</div>
                            <div class="cal-weekday">Sun</div>
                        </div>

                        <div class="cal-grid" id="calGrid" role="grid" aria-label="Select appointment date">
                            <div class="info-box" style="grid-column:1/-1">Loading calendar…</div>
                        </div>

                        <?php if (!empty($errors['preferred_date'])): ?>
                            <div class="field-error" style="margin-top:8px;">
                                <?= booking_e($errors['preferred_date'][0]) ?>
                            </div>
                        <?php endif; ?>

                        <div class="date-status-box" id="dateStatusBox">
                            <div class="date-status-title" id="dateStatusTitle">No date selected</div>
                            <div class="date-status-sub" id="dateStatusSub">Tap an available date on the calendar above.</div>
                        </div>

                      
                    </div>

                    <div class="bk-col col-slots">
                        <div class="slots-header">
                            <div class="slots-header-top">
                                <h3 class="slots-title">Time Slots</h3>

                                <div class="time-fmt" role="group" aria-label="Time format">
                                    <button type="button" class="time-fmt-btn on" data-fmt="12">12h</button>
                                    <button type="button" class="time-fmt-btn" data-fmt="24">24h</button>
                                </div>
                            </div>

                        </div>

                        <?php if (!empty($errors['preferred_start_time'])): ?>
                            <div class="field-error" style="margin-bottom:10px;">
                                <?= booking_e($errors['preferred_start_time'][0]) ?>
                            </div>
                        <?php endif; ?>

                        <div class="slots-list" id="slotsList">
                            <div class="info-box">Pick a date &amp; service first.</div>
                        </div>

                      
                    </div>
                </div>
            </div>

            <div id="patientStep" class="details-flow <?= $showPatientDetails ? '' : 'hidden' ?>">
                <div class="details-card">
                    <aside class="details-sidebar">
                        <div class="details-sidebar-title">Your Booking</div>

                        <div class="booking-summary-card">
                            <div class="bsc-row">
                                <div class="bsc-key">Service</div>
                                <div class="bsc-val" id="dSumService"><span class="na">Not selected</span></div>
                            </div>

                            <div class="bsc-row">
                                <div class="bsc-key">Date</div>
                                <div class="bsc-val" id="dSumDate"><span class="na">Not chosen</span></div>
                            </div>

                            <div class="bsc-row">
                                <div class="bsc-key">Time</div>
                                <div class="bsc-val" id="dSumTime"><span class="na">Not chosen</span></div>
                            </div>

                            <div class="bsc-row">
                                <div class="bsc-key">Status</div>
                                <div class="bsc-val">Awaiting clinic confirmation</div>
                            </div>
                        </div>

                        <div class="details-trust">
                            <div class="trust-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                                Your data is kept private &amp; secure
                            </div>

                            <div class="trust-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.78a16 16 0 0 0 6.31 6.31l.94-.94a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7a2 2 0 0 1 1.72 2.02z"/>
                                </svg>
                                We'll confirm via call or SMS
                            </div>

                            <div class="trust-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                Response within 24 hours
                            </div>
                        </div>
                    </aside>

                    <div class="details-main-col">
                        <div class="form-section">
                            <div class="form-section-title">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                Patient Information
                            </div>

                            <div class="form-grid">
                                <div class="field">
                                    <label class="field-label">First Name</label>
                                    <input class="form-control <?= !empty($errors['first_name']) ? 'has-error' : '' ?>" type="text" name="first_name" value="<?= booking_e($old['first_name'] ?? '') ?>" placeholder="e.g. Maria" required>
                                    <?php if (!empty($errors['first_name'])): ?>
                                        <div class="field-error"><?= booking_e($errors['first_name'][0]) ?></div>
                                    <?php endif; ?>
                                </div>
                                  <div class="field">
                                    <label class="field-label">Middle Name</label>
                                    <input class="form-control <?= !empty($errors['middle_name']) ? 'has-error' : '' ?>" type="text" name="middle_name" value="<?= booking_e($old['middle_name'] ?? '') ?>" placeholder="e.g. Maria" required>
                                    <?php if (!empty($errors['middle_name'])): ?>
                                        <div class="field-error"><?= booking_e($errors['middle_name'][0]) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="field">
                                    <label class="field-label">Last Name</label>
                                    <input class="form-control <?= !empty($errors['last_name']) ? 'has-error' : '' ?>" type="text" name="last_name" value="<?= booking_e($old['last_name'] ?? '') ?>" placeholder="e.g. Santos" required>
                                    <?php if (!empty($errors['last_name'])): ?>
                                        <div class="field-error"><?= booking_e($errors['last_name'][0]) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="field">
                                    <label class="field-label">Date of Birth</label>
                                    <input class="form-control <?= !empty($errors['birth_date']) ? 'has-error' : '' ?>" type="date" name="birth_date" id="birthDate" value="<?= booking_e($old['birth_date'] ?? '') ?>" required>
                                    <?php if (!empty($errors['birth_date'])): ?>
                                        <div class="field-error"><?= booking_e($errors['birth_date'][0]) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="field">
                                    <label class="field-label">Age <span class="opt">(auto-computed)</span></label>
                                    <input class="form-control" type="text" id="ageDisplay" readonly placeholder="—">
                                </div>

                                <div class="field">
                                    <label class="field-label">Sex</label>
                                    <select class="form-select <?= !empty($errors['sex']) ? 'has-error' : '' ?>" name="sex" required>
                                        <option value="">Select sex…</option>
                                        <option value="male" <?= ($old['sex'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= ($old['sex'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                    </select>
                                    <?php if (!empty($errors['sex'])): ?>
                                        <div class="field-error"><?= booking_e($errors['sex'][0]) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="field">
                                    <label class="field-label">Civil Status</label>
                                    <select class="form-select" name="civil_status">
                                        <option value="">Select status…</option>
                                        <option value="single" <?= ($old['civil_status'] ?? '') === 'single' ? 'selected' : '' ?>>Single</option>
                                        <option value="married" <?= ($old['civil_status'] ?? '') === 'married' ? 'selected' : '' ?>>Married</option>
                                        <option value="widowed" <?= ($old['civil_status'] ?? '') === 'widowed' ? 'selected' : '' ?>>Widowed</option>
                                        <option value="separated" <?= ($old['civil_status'] ?? '') === 'separated' ? 'selected' : '' ?>>Separated</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label class="field-label">Contact Number</label>
                                    <input class="form-control <?= !empty($errors['contact_number']) ? 'has-error' : '' ?>" type="text" name="contact_number" value="<?= booking_e($contactValue) ?>" placeholder="09XXXXXXXXX" required>
                                    <?php if (!empty($errors['contact_number'])): ?>
                                        <div class="field-error"><?= booking_e($errors['contact_number'][0]) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="field">
                                    <label class="field-label">Email Address <span class="opt">(Optional)</span></label>
                                    <input class="form-control <?= !empty($errors['email']) ? 'has-error' : '' ?>" type="email" name="email" value="<?= booking_e($emailValue) ?>" placeholder="you@example.com">
                                    <?php if (!empty($errors['email'])): ?>
                                        <div class="field-error"><?= booking_e($errors['email'][0]) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="field full">
                                    <label class="field-label">Occupation <span class="opt">(Optional)</span></label>
                                    <input class="form-control" type="text" name="occupation" value="<?= booking_e($old['occupation'] ?? '') ?>" placeholder="e.g. Teacher, Nurse, Engineer">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                    <circle cx="12" cy="10" r="3"/>
                                </svg>
                                Address
                            </div>

                            <div class="form-grid">
                                <div class="field">
                                    <label class="field-label">Region</label>
                                    <select class="form-select <?= !empty($errors['region_id']) ? 'has-error' : '' ?>" name="region_id" id="region_id" required>
                                        <option value="">Select region…</option>
                                        <?php foreach ($regions as $region): ?>
                                            <option value="<?= (int) $region['region_id'] ?>" <?= ((string) ($old['region_id'] ?? '') === (string) $region['region_id']) ? 'selected' : '' ?>>
                                                <?= booking_e($region['region_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($errors['region_id'])): ?>
                                        <div class="field-error"><?= booking_e($errors['region_id'][0]) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="field">
                                    <label class="field-label">Province</label>
                                    <select class="form-select <?= !empty($errors['province_id']) ? 'has-error' : '' ?>" name="province_id" id="province_id" required>
                                        <option value="">Select province…</option>
                                    </select>
                                    <?php if (!empty($errors['province_id'])): ?>
                                        <div class="field-error"><?= booking_e($errors['province_id'][0]) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="field">
                                    <label class="field-label">City / Municipality</label>
                                    <select class="form-select <?= !empty($errors['city_id']) ? 'has-error' : '' ?>" name="city_id" id="city_id" required>
                                        <option value="">Select city…</option>
                                    </select>
                                    <?php if (!empty($errors['city_id'])): ?>
                                        <div class="field-error"><?= booking_e($errors['city_id'][0]) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="field">
                                    <label class="field-label">Barangay</label>
                                    <select class="form-select <?= !empty($errors['barangay_id']) ? 'has-error' : '' ?>" name="barangay_id" id="barangay_id" required>
                                        <option value="">Select barangay…</option>
                                    </select>
                                    <?php if (!empty($errors['barangay_id'])): ?>
                                        <div class="field-error"><?= booking_e($errors['barangay_id'][0]) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="field full">
                                    <label class="field-label">Street / House No. / Purok</label>
                                    <input class="form-control" type="text" name="address_line" value="<?= booking_e($old['address_line'] ?? '') ?>" placeholder="House no., street, subdivision, purok">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                                Notes &amp; Concerns
                            </div>

                            <div class="field">
                                <label class="field-label">Additional Notes <span class="opt">(Optional)</span></label>
                                <textarea class="form-control" name="notes" placeholder="Share any concerns, allergies, or special requests for our dentist…"><?= booking_e($old['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

  <div class="field full">
    <label style="display:flex;align-items:flex-start;gap:10px;font-size:13px;color:var(--stone-600);line-height:1.5;">
        <input
            type="checkbox"
            name="wants_patient_account"
            value="1"
            <?= !empty($old['wants_patient_account']) ? 'checked' : '' ?>
            style="width:16px;height:16px;margin-top:3px;accent-color:var(--mint-dark);flex-shrink:0;"
        >
        <span>Create an account so I can view my appointments and records online.</span>
    </label>

    <div style="margin-top:6px;font-size:12px;color:var(--stone-400);line-height:1.5;">
        Your account will not be activated immediately. After clinic confirmation, you will receive a password setup link or verification instructions.
    </div>
</div>




                        <div class="form-actions">
                            <a href="/DentalClinic/public/book" class="btn btn-ghost">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="15 18 9 12 15 6"/>
                                </svg>
                                Back
                            </a>

                            <button type="submit" class="btn btn-primary">
                                Review Booking
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

                <div
            class="privacy-consent-modal"
            id="privacyConsentModal"
            aria-hidden="true"
            role="dialog"
            aria-modal="true"
            aria-labelledby="privacyConsentTitle"
        >
            <div class="privacy-consent-card">
                <div class="privacy-consent-head">
                    <div>
                        <h2 class="privacy-consent-title" id="privacyConsentTitle">
                            Privacy Notice and Consent
                        </h2>
                        <div class="privacy-consent-sub">
                            Please read until the end before continuing to review your booking.
                        </div>
                    </div>

                    <button type="button" class="privacy-consent-close" id="privacyConsentClose" aria-label="Close">
                        ×
                    </button>
                </div>

                <div class="privacy-consent-body">
                    <div class="privacy-consent-scroll" id="privacyConsentScroll" tabindex="0">
                        <h3>Data We Collect</h3>
                        <p>
                            The clinic collects personal, contact, appointment, and health-related information needed to process your appointment request and provide dental clinic services.
                        </p>

                        <ul>
                            <li>Name, sex, birth date, civil status, address, occupation, contact number, and email address.</li>
                            <li>Preferred dental service, appointment date, appointment time, dentist selection, notes, and service-specific answers.</li>
                            <li>Medical and dental information that may be needed for safe dental consultation and treatment.</li>
                        </ul>

                        <h3>Purpose of Processing</h3>
                        <p>
                            Your information will be used for appointment scheduling, clinic review, dentist assignment, treatment preparation, patient record management, reminders, billing, follow-up care, and clinic communication.
                        </p>

                        <h3>Who May Access Your Information</h3>
                        <p>
                            Authorized clinic staff may access your information for scheduling and clinic operations. Dentists may access patient information related to their assigned appointment or treatment. Patient records are protected by role-based access control.
                        </p>

                        <h3>Protection and Security</h3>
                        <p>
                            The system uses login access control, CSRF protection, prepared database statements, audit logs, and limited role-based access to reduce unauthorized access, misuse, alteration, or disclosure.
                        </p>

                        <h3>Retention and Patient Rights</h3>
                        <p>
                            Patient and clinical records may be retained as needed for dental care continuity, legal compliance, clinic operations, and recordkeeping. You may request access, correction, deletion/blocking review, objection, or other privacy-related assistance.
                        </p>

                        <h3>Consent</h3>
                        <p>
                            By continuing, you consent to the collection and processing of your personal and health information for appointment scheduling and dental clinic services.
                        </p>

                        <div class="privacy-consent-end-note">
                            You have reached the end of the Privacy Notice. Please read the privacy notice carefully before proceeding.
                        </div>
                    </div>
                </div>

                <div class="privacy-consent-actions">
                    <div class="privacy-consent-read-hint" id="privacyConsentHint">
                        Scroll to the end of the Privacy Notice to show the consent checkbox.
                    </div>

                    <label class="privacy-consent-check-wrap" id="privacyConsentCheckWrap">
                        <input type="checkbox" id="privacyConsentCheck" value="1">
                        <span>
                            I have read and understood the
                            <a href="/DentalClinic/public/privacy-notice" target="_blank" rel="noopener">
                                Privacy Notice
                            </a>
                            and consent to the collection and processing of my personal and health information for appointment scheduling and dental clinic services.
                        </span>
                    </label>

                    <div class="privacy-consent-footer">
                        <button type="button" class="privacy-consent-btn cancel" id="privacyConsentCancel">
                            Cancel
                        </button>

                        <button type="button" class="privacy-consent-btn continue" id="privacyConsentContinue" disabled>
                            Continue to Review Booking
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="bk-footer">
            <span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline;margin-right:3px;color:var(--mint-dark);">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Secure Booking
            </span>

            <span class="bk-footer-sep">|</span>
            <span>Dr. Brendalyn Wansi Calacat Dental Clinic</span>
            <span class="bk-footer-sep">|</span>
            <span>Specialized Dentistry With Personalized Care</span>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    function esc(v) {
        const d = document.createElement('div');
        d.textContent = v ?? '';
        return d.innerHTML;
    }

    function qs(sel, ctx) {
        return (ctx || document).querySelector(sel);
    }

    function qsa(sel, ctx) {
        return Array.from((ctx || document).querySelectorAll(sel));
    }

    

    function fmtDate(v) {
        if (!v) return '';

        const p = String(v).split('-');
        const d = new Date(+p[0], +p[1] - 1, +p[2]);

        return d.toLocaleDateString('en-PH', {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function fmtDateShort(v) {
        if (!v) return '';

        const p = String(v).split('-');
        const d = new Date(+p[0], +p[1] - 1, +p[2]);

        return d.toLocaleDateString('en-PH', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function fmtTime(v, fmt) {
        if (!v) return '';

        const parts = String(v).split(':');
        let h = parseInt(parts[0] || 0, 10);
        const m = parts[1] || '00';

        if (fmt === '24') {
            return String(h).padStart(2, '0') + ':' + m;
        }

        const suf = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;

        return h + ':' + m + ' ' + suf;
    }

    function localDateStr(date) {
        return date.getFullYear() + '-' +
            String(date.getMonth() + 1).padStart(2, '0') + '-' +
            String(date.getDate()).padStart(2, '0');
    }

    function todayStr() {
        return localDateStr(new Date());
    }

    function getMonday(date) {
        const d = new Date(date);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1);

        d.setDate(diff);
        d.setHours(0, 0, 0, 0);

        return d;
    }

    function addDays(date, n) {
        const d = new Date(date);
        d.setDate(d.getDate() + n);
        return d;
    }

    const oldOptionValues = <?= json_encode($oldOptionValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const STORE_KEY = 'bwc_dental_booking_v2';

    let selDate = '';
    let selTime = '';
    let timeFmt = '12';
    let avMap = new Map();
    let curMonth = new Date();

    curMonth.setDate(1);
    curMonth.setHours(0, 0, 0, 0);

    try {
        const raw = localStorage.getItem(STORE_KEY);

        if (raw) {
            const d = JSON.parse(raw);

            if (d.date) selDate = d.date;
            if (d.time) selTime = d.time;
        }
    } catch (e) {}

    const phpDate = <?= json_encode($preferredDateValue) ?>;
    const phpTime = <?= json_encode($preferredStartTimeValue) ?>;

    if (phpDate) selDate = phpDate;
    if (phpTime) selTime = phpTime;

    const svcWrap = qs('#serviceWrap');
    const svcTrigger = qs('#serviceTrigger');
    const triggerLabel = qs('#triggerLabel');
    const svcBadge = qs('#serviceBadge');
    const svcCBs = qsa('[data-svc-cb]');
    const svcOpts = qsa('[data-svc]');
    const svcIdInput = qs('#service_id');
    const dynWrap = qs('#dynWrap');
    const dynGrid = qs('#dynGrid');

    const calGrid = qs('#calGrid');
    const calMonth = qs('#calMonth');
    const prevMonthBtn = qs('#prevMonth');
    const nextMonthBtn = qs('#nextMonth');
    const dateStatusBox = qs('#dateStatusBox');
    const dateStatusT = qs('#dateStatusTitle');
    const dateStatusS = qs('#dateStatusSub');

    const slotsList = qs('#slotsList');
    const slotsDateLbl = qs('#slotsDateLabel');
    const continueCta = qs('#continueCta');
    const scrollBtn = qs('#scrollToDetails');

    const dSumService = qs('#dSumService');
    const dSumDate = qs('#dSumDate');
    const dSumTime = qs('#dSumTime');

    const prefDateInp = qs('#preferred_date');
    const prefTimeInp = qs('#preferred_start_time');

    const patientStep = qs('#patientStep');
    const bookingForm = qs('#bookingForm');
    const privacyConsentInput = qs('#privacy_consent');
const privacyModal = qs('#privacyConsentModal');
const privacyScrollEl = qs('#privacyConsentScroll');
const privacyCheckWrapEl = qs('#privacyConsentCheckWrap');
const privacyCheckEl = qs('#privacyConsentCheck');
const privacyContinueBtn = qs('#privacyConsentContinue');
const privacyCancelBtn = qs('#privacyConsentCancel');
const privacyCloseBtn = qs('#privacyConsentClose');
const privacyHintEl = qs('#privacyConsentHint');

let privacyConsentModalConfirmed = false;
    const birthDate = qs('#birthDate');
    const ageDisplay = qs('#ageDisplay');



function openPrivacyConsentModal() {
    if (!privacyModal) {
        return;
    }

    privacyModal.classList.add('show');
    privacyModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('privacy-modal-open');

    privacyConsentModalConfirmed = false;

    if (privacyCheckEl) {
        privacyCheckEl.checked = false;
    }

    if (privacyContinueBtn) {
        privacyContinueBtn.disabled = true;
    }

    if (privacyCheckWrapEl) {
        privacyCheckWrapEl.classList.remove('show');
    }

    if (privacyHintEl) {
        privacyHintEl.textContent = 'Scroll to the end of the Privacy Notice to show the consent checkbox.';
    }

    if (privacyScrollEl) {
        privacyScrollEl.scrollTop = 0;

        setTimeout(function () {
            privacyScrollEl.focus();
        }, 120);
    }
}

function closePrivacyConsentModal() {
    if (!privacyModal) {
        return;
    }

    privacyModal.classList.remove('show');
    privacyModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('privacy-modal-open');
}

function hasReachedConsentEnd() {
    if (!privacyScrollEl) {
        return false;
    }

    return privacyScrollEl.scrollTop + privacyScrollEl.clientHeight >= privacyScrollEl.scrollHeight - 8;
}

function revealConsentCheckboxIfAtEnd() {
    if (!hasReachedConsentEnd()) {
        return;
    }

    if (privacyCheckWrapEl) {
        privacyCheckWrapEl.classList.add('show');
    }

    if (privacyHintEl) {
        privacyHintEl.textContent = 'Please tick the consent checkbox to continue.';
    }
}

function acceptPrivacyConsentAndSubmit() {
    if (!privacyCheckEl || !privacyCheckEl.checked) {
        return;
    }

    if (privacyConsentInput) {
        privacyConsentInput.value = '1';
    }

    privacyConsentModalConfirmed = true;
    closePrivacyConsentModal();

    if (!bookingForm) {
        return;
    }

    if (typeof bookingForm.requestSubmit === 'function') {
        bookingForm.requestSubmit();
        return;
    }

    const submitEvent = new Event('submit', {
        cancelable: true,
        bubbles: true
    });

    bookingForm.dispatchEvent(submitEvent);

    if (!submitEvent.defaultPrevented) {
        bookingForm.submit();
    }
}

if (privacyScrollEl) {
    privacyScrollEl.addEventListener('scroll', revealConsentCheckboxIfAtEnd);
}

if (privacyCheckEl) {
    privacyCheckEl.addEventListener('change', function () {
        if (privacyContinueBtn) {
            privacyContinueBtn.disabled = !privacyCheckEl.checked;
        }
    });
}

if (privacyContinueBtn) {
    privacyContinueBtn.addEventListener('click', acceptPrivacyConsentAndSubmit);
}

if (privacyCancelBtn) {
    privacyCancelBtn.addEventListener('click', closePrivacyConsentModal);
}

if (privacyCloseBtn) {
    privacyCloseBtn.addEventListener('click', closePrivacyConsentModal);
}

if (privacyModal) {
    privacyModal.addEventListener('click', function (e) {
        if (e.target === privacyModal) {
            closePrivacyConsentModal();
        }
    });
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && privacyModal && privacyModal.classList.contains('show')) {
        closePrivacyConsentModal();
    }
});





    function getSelSvcIds() {
        return svcCBs.filter(c => c.checked).map(c => c.value);
    }

    function getSelSvcNames() {
        return svcCBs.filter(c => c.checked).map(c => {
            const opt = c.closest('[data-svc]');
            const nameNode = opt ? opt.querySelector('span:last-child') : null;

            return nameNode ? nameNode.textContent.trim() : '';
        }).filter(Boolean);
    }

    function setEl(el, val) {
        if (!el) return;

        if (val) {
            el.textContent = val;
            el.classList.remove('empty');
            return;
        }

        el.innerHTML = '<span class="na">Not selected</span>';
        el.classList.add('empty');
    }

    function updateSummary() {
        const svcNames = getSelSvcNames();
        const svcTxt = svcNames.length ? svcNames.join(', ') : null;
        const dateTxt = selDate ? fmtDate(selDate) : null;
        const timeTxt = selTime ? fmtTime(selTime, timeFmt) : null;

        setEl(dSumService, svcTxt);
        setEl(dSumDate, dateTxt);
        setEl(dSumTime, timeTxt);
    }

    function save() {
        try {
            localStorage.setItem(STORE_KEY, JSON.stringify({
                date: selDate,
                time: selTime
            }));
        } catch (e) {}
    }

    function setScheduleInputs() {
        if (prefDateInp) prefDateInp.value = selDate;
        if (prefTimeInp) prefTimeInp.value = selTime;

        save();
    }

    function syncService(reset) {
        const ids = getSelSvcIds();
        const names = getSelSvcNames();

        if (svcIdInput) {
            svcIdInput.value = ids.length ? ids[0] : '';
        }

        svcOpts.forEach(opt => {
            const cb = opt.querySelector('[data-svc-cb]');
            opt.classList.toggle('selected', cb && cb.checked);
            opt.setAttribute('aria-selected', cb && cb.checked ? 'true' : 'false');
        });

        if (!names.length) {
            triggerLabel.textContent = 'Choose a service…';
            triggerLabel.classList.remove('has-val');
        } else if (names.length === 1) {
            triggerLabel.textContent = names[0];
            triggerLabel.classList.add('has-val');
        } else {
            triggerLabel.textContent = names.length + ' services selected';
            triggerLabel.classList.add('has-val');
        }

        svcBadge.textContent = String(ids.length);
        svcBadge.classList.toggle('show', ids.length > 0);

        if (reset) {
            selTime = '';

            if (prefTimeInp) {
                prefTimeInp.value = '';
            }

            updateSummary();
            loadDynamic();

            if (selDate && svcIdInput && svcIdInput.value) {
                loadSlots(selDate);
            } else {
                showSlotsMsg('Select a date and service to view available slots.');
            }
        }

        updateSummary();
        save();
    }

    if (svcTrigger && svcWrap) {
        svcTrigger.addEventListener('click', () => {
            const open = svcWrap.classList.toggle('open');
            svcTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', e => {
            if (!svcWrap.contains(e.target)) {
                svcWrap.classList.remove('open');
                svcTrigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    svcCBs.forEach(cb => cb.addEventListener('change', () => syncService(true)));

    qsa('[data-fmt]').forEach(btn => {
        btn.addEventListener('click', () => {
            qsa('[data-fmt]').forEach(b => b.classList.remove('on'));

            btn.classList.add('on');
            timeFmt = btn.dataset.fmt;

            if (selDate && svcIdInput && svcIdInput.value) {
                loadSlots(selDate);
            }

            updateSummary();
        });
    });

    async function loadMonth() {
        avMap = new Map();

        if (!calMonth || !calGrid) return;

        calMonth.textContent = curMonth.toLocaleDateString('en-PH', {
            month: 'long',
            year: 'numeric'
        });

        calGrid.innerHTML = '<div class="info-box" style="grid-column:1/-1">Loading calendar…</div>';

        const firstDay = new Date(curMonth);
        firstDay.setDate(1);

        const calStart = getMonday(firstDay);
        const weekStarts = [];

        for (let i = 0; i < 6; i++) {
            weekStarts.push(localDateStr(addDays(calStart, i * 7)));
        }

        try {
            const results = await Promise.all(weekStarts.map(ws =>
                fetch('/DentalClinic/public/booking/calendar-availability?week_start=' + encodeURIComponent(ws), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(r => r.ok ? r.json() : Promise.reject())
            ));

            results.forEach(data => {
                (Array.isArray(data.week) ? data.week : []).forEach(day => avMap.set(day.date, day));
            });

            renderCal();
        } catch (e) {
            calGrid.innerHTML = '<div class="info-box" style="grid-column:1/-1">Unable to load calendar. Please try again.</div>';
        }
    }

    function renderCal() {
        if (!calGrid) return;

        calGrid.innerHTML = '';

        const today = todayStr();
        const firstDay = new Date(curMonth);

        firstDay.setDate(1);

        const calStart = getMonday(firstDay);

        for (let i = 0; i < 42; i++) {
            const date = addDays(calStart, i);
            const ds = localDateStr(date);
            const day = avMap.get(ds) || {
                date: ds,
                is_available: false,
                is_past: ds < today
            };

            const isOther = date.getMonth() !== curMonth.getMonth();
            const isPast = !!day.is_past || ds < today;
            const isAvail = !!day.is_available;
            const disabled = isOther || isPast || !isAvail;

            const btn = document.createElement('button');

            btn.type = 'button';
            btn.className = 'cal-day';
            btn.textContent = String(date.getDate());
            btn.setAttribute('role', 'gridcell');
            btn.setAttribute('aria-label', (isAvail && !disabled ? 'Available: ' : '') + ds);
            btn.setAttribute('aria-selected', selDate === ds ? 'true' : 'false');

            if (isOther) btn.classList.add('other-month');
            if (ds === today) btn.classList.add('today');
            if (isAvail && !isPast && !isOther) btn.classList.add('available');
            if (selDate === ds && !disabled) btn.classList.add('selected');

            if (disabled) {
                btn.disabled = true;
            } else {
                btn.addEventListener('click', () => {
                    selDate = ds;
                    selTime = '';

                    setScheduleInputs();
                    updateSummary();
                    renderCal();
                    updateDateStatus(day);

                    if (slotsDateLbl) {
                        slotsDateLbl.textContent = fmtDateShort(ds);
                    }

                    if (svcIdInput && svcIdInput.value) {
                        loadSlots(ds);
                    } else {
                        showSlotsMsg('Please choose a service first.');
                    }
                });
            }

            calGrid.appendChild(btn);
        }

        if (selDate && avMap.has(selDate)) {
            updateDateStatus(avMap.get(selDate));

            if (svcIdInput && svcIdInput.value) {
                loadSlots(selDate);
            }
        } else if (!selDate) {
            updateDateStatus(null);
        }
    }

    function updateDateStatus(day) {
        if (!dateStatusBox || !dateStatusT || !dateStatusS) return;

        if (!day) {
            dateStatusBox.className = 'date-status-box';
            dateStatusT.textContent = 'No date selected';
            dateStatusS.textContent = 'Tap an available date on the calendar above.';
            return;
        }

        if (day.is_available) {
            dateStatusBox.className = 'date-status-box available';
            dateStatusT.textContent = 'Available ' + fmtDateShort(day.date);
            dateStatusS.textContent = 'Great choice. Now pick a time slot on the right.';
            return;
        }

        dateStatusBox.className = 'date-status-box';
        dateStatusT.textContent = 'Unavailable  ' + fmtDateShort(day.date);
        dateStatusS.textContent = 'This date cannot be booked. Please choose another.';
    }

    if (prevMonthBtn) {
        prevMonthBtn.addEventListener('click', () => {
            curMonth.setMonth(curMonth.getMonth() - 1);
            selTime = '';
            setScheduleInputs();
            updateSummary();
            loadMonth();
        });
    }

    if (nextMonthBtn) {
        nextMonthBtn.addEventListener('click', () => {
            curMonth.setMonth(curMonth.getMonth() + 1);
            selTime = '';
            setScheduleInputs();
            updateSummary();
            loadMonth();
        });
    }

    function showSlotsMsg(msg) {
        if (!slotsList) return;

        slotsList.innerHTML = '<div class="info-box">' + esc(msg) + '</div>';
    }

    async function loadSlots(date) {
        const svcId = svcIdInput ? svcIdInput.value : '';

        if (!date || !svcId) {
            showSlotsMsg('Select a date and service to view available slots.');
            return;
        }

        showSlotsMsg('Loading time slots…');

        try {
            const r = await fetch('/DentalClinic/public/booking/available-slots?date=' + encodeURIComponent(date) + '&service_id=' + encodeURIComponent(svcId), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!r.ok) {
                throw new Error();
            }

            const data = await r.json();
            const slots = Array.isArray(data.slots) ? data.slots : (Array.isArray(data.available_slots) ? data.available_slots : []);

            if (!slots.length) {
                showSlotsMsg('No available slots for this date.');
                return;
            }

            slotsList.innerHTML = '';

            slots.forEach(slot => {
                const st = slot.start_time || '';
                const disabled = !!slot.is_past || slot.is_available === false;
                const btn = document.createElement('button');

                btn.type = 'button';
                btn.className = 'slot-btn' + (selTime === st && !disabled ? ' on' : '');
                btn.textContent = fmtTime(st, timeFmt) || slot.label || st;
                btn.setAttribute('aria-pressed', selTime === st ? 'true' : 'false');

                if (disabled) {
                    btn.disabled = true;
                } else {
                    btn.addEventListener('click', () => {
                        qsa('.slot-btn').forEach(b => {
                            b.classList.remove('on');
                            b.setAttribute('aria-pressed', 'false');
                        });

                        btn.classList.add('on');
                        btn.setAttribute('aria-pressed', 'true');

                        selTime = st;

                        setScheduleInputs();
                        updateSummary();

                        if (continueCta) {
                            continueCta.classList.add('show');
                        }

                        revealDetails(true);
                    });
                }

                slotsList.appendChild(btn);
            });
        } catch (e) {
            showSlotsMsg('Unable to load slots. Please try again.');
        }
    }

    function revealDetails(scroll) {
        if (!patientStep) return;

        patientStep.classList.remove('hidden');

        const steps = qsa('.step');

        if (steps[0]) {
            steps[0].classList.remove('active');
            steps[0].classList.add('done');

            const node = steps[0].querySelector('.step-node');

            if (node) {
                node.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
            }
        }

        if (steps[1]) {
            steps[1].classList.add('active');
        }

        updateSummary();

        if (scroll) {
            setTimeout(() => patientStep.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            }), 100);
        }
    }

    if (scrollBtn) {
        scrollBtn.addEventListener('click', () => revealDetails(true));
    }

    async function loadDynamic() {
        if (!dynGrid || !dynWrap) return;

        dynGrid.innerHTML = '';
        dynWrap.classList.remove('show');

        const svcId = svcIdInput ? svcIdInput.value : '';

        if (!svcId) return;

        try {
            const r = await fetch('/DentalClinic/public/booking/service-questions?service_id=' + encodeURIComponent(svcId), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!r.ok) {
                throw new Error();
            }

            const data = await r.json();
            const questions = Array.isArray(data.questions) ? data.questions : [];

            if (!questions.length) return;

            dynWrap.classList.add('show');

            questions.forEach(q => {
                const wrap = document.createElement('div');
                wrap.className = 'field';

                const lbl = document.createElement('label');
                lbl.className = 'field-label';
                lbl.textContent = q.option_name + (q.is_required ? ' *' : '');

                wrap.appendChild(lbl);

                const fname = 'option_' + q.option_id;
                const old = oldOptionValues[fname] || '';
                const type = String(q.option_type || 'text').toLowerCase();

                if (type === 'textarea') {
                    const ta = document.createElement('textarea');

                    ta.className = 'form-control';
                    ta.name = fname;
                    ta.value = old;

                    wrap.classList.add('full');

                    if (q.is_required) {
                        ta.required = true;
                    }

                    wrap.appendChild(ta);
                } else if (type === 'select' || type === 'radio') {
                    const sel = document.createElement('select');
                    const def = document.createElement('option');

                    sel.className = 'form-select';
                    sel.name = fname;

                    def.value = '';
                    def.textContent = 'Select…';

                    sel.appendChild(def);

                    (q.values || []).forEach(v => {
                        const o = document.createElement('option');

                        o.value = v.value_id;
                        o.textContent = v.value_label;

                        if (String(old) === String(v.value_id)) {
                            o.selected = true;
                        }

                        sel.appendChild(o);
                    });

                    if (q.is_required) {
                        sel.required = true;
                    }

                    wrap.appendChild(sel);
                } else {
                    const inp = document.createElement('input');

                    inp.className = 'form-control';
                    inp.type = type === 'number' ? 'number' : 'text';
                    inp.name = fname;
                    inp.value = old;

                    if (q.is_required) {
                        inp.required = true;
                    }

                    wrap.appendChild(inp);
                }

                dynGrid.appendChild(wrap);
            });
        } catch (e) {
            dynWrap.classList.add('show');
            dynGrid.innerHTML = '<div class="field-error">Unable to load questions.</div>';
        }
    }

    function computeAge() {
        if (!birthDate || !ageDisplay) return;

        if (!birthDate.value) {
            ageDisplay.value = '';
            return;
        }

        const birth = new Date(birthDate.value + 'T00:00:00');
        const today = new Date();

        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();

        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
            age--;
        }

        ageDisplay.value = age >= 0 ? String(age) : '';
    }

    if (birthDate) {
        birthDate.addEventListener('change', computeAge);
    }

    computeAge();

    function setupAddress() {
        const reg = qs('#region_id');
        const prov = qs('#province_id');
        const city = qs('#city_id');
        const brgy = qs('#barangay_id');

        if (!reg || !prov || !city || !brgy) return;

        const oldProv = <?= json_encode((string) ($old['province_id'] ?? '')) ?>;
        const oldCity = <?= json_encode((string) ($old['city_id'] ?? '')) ?>;
        const oldBrgy = <?= json_encode((string) ($old['barangay_id'] ?? '')) ?>;

        function fill(sel, items, vk, lk, ph, selVal) {
            sel.innerHTML = '<option value="">' + ph + '</option>';

            (items || []).forEach(item => {
                const o = document.createElement('option');

                o.value = String(item[vk]);
                o.textContent = item[lk];

                if (String(item[vk]) === String(selVal)) {
                    o.selected = true;
                }

                sel.appendChild(o);
            });
        }

        async function loadProv(rid, selProv) {
            fill(prov, [], 'province_id', 'province_name', 'Select province…', '');
            fill(city, [], 'city_id', 'city_name', 'Select city…', '');
            fill(brgy, [], 'barangay_id', 'barangay_name', 'Select barangay…', '');

            if (!rid) return;

            const d = await fetch('/DentalClinic/public/booking/provinces?region_id=' + encodeURIComponent(rid), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(r => r.json());

            fill(prov, d.provinces || [], 'province_id', 'province_name', 'Select province…', selProv);

            if (selProv) {
                await loadCity(selProv, oldCity);
            }
        }

        async function loadCity(pid, selCity) {
            fill(city, [], 'city_id', 'city_name', 'Select city…', '');
            fill(brgy, [], 'barangay_id', 'barangay_name', 'Select barangay…', '');

            if (!pid) return;

            const d = await fetch('/DentalClinic/public/booking/cities?province_id=' + encodeURIComponent(pid), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(r => r.json());

            fill(city, d.cities || [], 'city_id', 'city_name', 'Select city…', selCity);

            if (selCity) {
                await loadBrgy(selCity, oldBrgy);
            }
        }

        async function loadBrgy(cid, selBrgy) {
            fill(brgy, [], 'barangay_id', 'barangay_name', 'Select barangay…', '');

            if (!cid) return;

            const d = await fetch('/DentalClinic/public/booking/barangays?city_id=' + encodeURIComponent(cid), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(r => r.json());

            fill(brgy, d.barangays || [], 'barangay_id', 'barangay_name', 'Select barangay…', selBrgy);
        }

        reg.addEventListener('change', () => loadProv(reg.value, ''));
        prov.addEventListener('change', () => loadCity(prov.value, ''));
        city.addEventListener('change', () => loadBrgy(city.value, ''));

        if (reg.value) {
            loadProv(reg.value, oldProv);
        }
    }

    setupAddress();

    function clearErrors() {
        qsa('.has-error').forEach(f => f.classList.remove('has-error'));
    }

    function focusErr(field, msg) {
        if (!field) return;

        field.classList.add('has-error');
        field.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

        setTimeout(() => field.focus(), 250);

        alert(msg);
    }

    function validPH(v) {
        const p = String(v || '').replace(/[\s-]/g, '');

        return /^09\d{9}$/.test(p) || /^639\d{9}$/.test(p) || /^\+639\d{9}$/.test(p);
    }

    function validateDetails() {
        clearErrors();

        const checks = [
            {sel: '[name="first_name"]', msg: 'Please enter your first name.'},
            {sel: '[name="last_name"]', msg: 'Please enter your last name.'},
            {sel: '[name="birth_date"]', msg: 'Please enter your date of birth.'},
            {sel: '[name="sex"]', msg: 'Please select your sex.'},
            {sel: '[name="contact_number"]', msg: 'Please enter your contact number.'},
            {sel: '[name="region_id"]', msg: 'Please select your region.'},
            {sel: '[name="province_id"]', msg: 'Please select your province.'},
            {sel: '[name="city_id"]', msg: 'Please select your city or municipality.'},
            {sel: '[name="barangay_id"]', msg: 'Please select your barangay.'}
        ];

        for (const c of checks) {
            const f = qs(c.sel);

            if (!f || !String(f.value || '').trim()) {
                focusErr(f, c.msg);
                return false;
            }
        }

        const cf = qs('[name="contact_number"]');

        if (cf && !validPH(cf.value)) {
            focusErr(cf, 'Please enter a valid Philippine mobile number (e.g. 09XXXXXXXXX).');
            return false;
        }

        const ef = qs('[name="email"]');

        if (ef && String(ef.value || '').trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(ef.value.trim())) {
            focusErr(ef, 'Please enter a valid email address.');
            return false;
        }

        return true;
    }

    if (bookingForm) {
    bookingForm.addEventListener('submit', function (e) {
        clearErrors();

        const ids = getSelSvcIds();

        if (!ids.length) {
            e.preventDefault();

            if (svcTrigger) {
                svcTrigger.classList.add('has-error');
                svcTrigger.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });

                setTimeout(() => svcTrigger.focus(), 250);
            }

            if (svcWrap) {
                svcWrap.classList.add('open');
            }

            alert('Please select at least one dental service.');
            return;
        }

        if (!prefDateInp || !String(prefDateInp.value || '').trim()) {
            e.preventDefault();

            if (calGrid) {
                calGrid.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }

            alert('Please select a preferred appointment date.');
            return;
        }

        if (!prefTimeInp || !String(prefTimeInp.value || '').trim()) {
            e.preventDefault();

            if (slotsList) {
                slotsList.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }

            alert('Please select a preferred time slot.');
            return;
        }

        if (patientStep && patientStep.classList.contains('hidden')) {
            e.preventDefault();
            revealDetails(true);
            return;
        }

        /*
            Critical fix:
            If birth date or any patient detail is missing,
            stop here. Do not continue to privacy consent.
        */
       if (!validateDetails()) {
    e.preventDefault();
    return;
}

if (!privacyConsentModalConfirmed && privacyConsentInput && privacyConsentInput.value !== '1') {
    e.preventDefault();
    openPrivacyConsentModal();
    return;
}
    });
}



if (bookingForm && privacyConsentInput) {
    bookingForm.addEventListener('input', function (e) {
        const target = e.target;

        if (!target) {
            return;
        }

        if (
            target.id === 'privacy_consent' ||
            target.id === 'privacyConsentCheck'
        ) {
            return;
        }

        privacyConsentInput.value = '0';
        privacyConsentModalConfirmed = false;
    });

    bookingForm.addEventListener('change', function (e) {
        const target = e.target;

        if (!target) {
            return;
        }

        if (
            target.id === 'privacy_consent' ||
            target.id === 'privacyConsentCheck'
        ) {
            return;
        }

        privacyConsentInput.value = '0';
        privacyConsentModalConfirmed = false;
    });
}




    if (selDate) {
        const parts = selDate.split('-');
        curMonth = new Date(parts[0], +parts[1] - 1, 1);
    }

    syncService(false);
    updateSummary();
    loadDynamic();
    loadMonth();

    if (selDate && svcIdInput && svcIdInput.value) {
        loadSlots(selDate);
    }

    if (phpDate && phpTime) {
        revealDetails(false);
    } else if (selDate && selTime) {
        revealDetails(false);
    }
})();








</script>

<?php
$content = ob_get_clean();
$title = 'Book Appointment — Dr. Brendalyn Wansi Calacat Dental Clinic';

require __DIR__ . '/../../layouts/main.php';
?>