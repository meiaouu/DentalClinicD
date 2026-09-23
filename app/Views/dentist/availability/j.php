<style>
* {
    box-sizing: border-box;
}

:root {
    --dc-bg: #f5f5f5;
    --dc-card: #ffffff;
    --dc-soft: #f9fafb;
    --dc-soft-2: #f3f4f6;
    --dc-border: #e5e7eb;
    --dc-border-dark: #d1d5db;

    --dc-text: #111827;
    --dc-muted: #6b7280;
    --dc-light: #9ca3af;

    --dc-black: #060a11;
    --dc-black-soft: #111827;

    --dc-green: #15803d;
    --dc-green-dark: #166534;
    --dc-green-soft: #f0fdf4;
    --dc-green-border: #bbf7d0;

    --dc-yellow-soft: #fffbeb;
    --dc-yellow: #92400e;

    --dc-red-soft: #fef2f2;
    --dc-red: #b91c1c;

    --dc-blue-soft: #eff6ff;
    --dc-blue: #1d4ed8;
}

body {
    margin: 0;
    font-family: Inter, Arial, Helvetica, sans-serif;
    background: var(--dc-bg);
    color: var(--dc-text);
}

/* Main layout */
.availability-layout {
    min-height: 100vh;
    display: flex;
    background: var(--dc-bg);
}

.availability-main {
    flex: 1;
    min-width: 0;
    background: var(--dc-bg);
}

.availability-content {
    height: 100dvh;
    max-height: 100dvh;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 16px 20px 28px;
    background: var(--dc-bg);
    scrollbar-gutter: stable;
}

.availability-content::-webkit-scrollbar {
    width: 8px;
}

.availability-content::-webkit-scrollbar-track {
    background: var(--dc-bg);
}

.availability-content::-webkit-scrollbar-thumb {
    background: #c7c7c7;
    border-radius: 999px;
}

.availability-content::-webkit-scrollbar-thumb:hover {
    background: var(--dc-black);
}

.availability-shell {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 330px;
    gap: 14px;
    align-items: start;
}

.availability-left,
.availability-right {
    min-width: 0;
    display: grid;
    gap: 12px;
}

.card {
    background: var(--dc-card);
    border: none;
    border-radius: 0;
    box-shadow: none;
}

/* Flash messages */
.flash-wrap {
    display: grid;
    gap: 8px;
    margin-bottom: 12px;
}

.flash {
    padding: 10px 12px;
    font-size: 13px;
    font-weight: 800;
    border: none;
}

.flash.success {
    background: var(--dc-green-soft);
    color: var(--dc-green-dark);
}

.flash.error {
    background: var(--dc-red-soft);
    color: var(--dc-red);
}

/* Summary cards */
.summary-card {
    padding: 10px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
}

.summary-box {
    min-height: 62px;
    padding: 10px 12px;
    background: var(--dc-soft);
    border: none;
    border-left: 4px solid var(--dc-black);
    display: flex;
    align-items: center;
    gap: 10px;
}

.summary-box.green {
    border-left-color: var(--dc-green);
}

.summary-box.yellow {
    border-left-color: var(--dc-yellow);
}

.summary-box.red {
    border-left-color: var(--dc-red);
}

.summary-box.blue {
    border-left-color: var(--dc-blue);
}

.summary-icon {
    width: 32px;
    height: 32px;
    background: #ffffff;
    color: var(--dc-black);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.summary-icon.green {
    color: var(--dc-green);
}

.summary-icon.yellow {
    color: var(--dc-yellow);
}

.summary-icon.red {
    color: var(--dc-red);
}

.summary-icon.blue {
    color: var(--dc-blue);
}

.summary-icon svg {
    width: 16px;
    height: 16px;
}

.summary-number {
    margin: 0;
    font-size: 19px;
    font-weight: 900;
    color: var(--dc-text);
    line-height: 1;
}

.summary-number.date-value {
    font-size: 13px;
    line-height: 1.3;
}

.summary-label-text {
    margin-top: 4px;
    font-size: 11px;
    font-weight: 800;
    color: var(--dc-muted);
}

/* Calendar */
.calendar-card {
    background: #ffffff;
    overflow: hidden;
}

.calendar-header {
    padding: 13px 15px;
    background: #ffffff;
    border-bottom: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.calendar-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.calendar-nav-group {
    display: flex;
    align-items: center;
    gap: 6px;
}

.nav-square {
    width: 32px;
    height: 32px;
    border: none;
    background: var(--dc-soft-2);
    color: var(--dc-text);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.nav-square:hover {
    background: var(--dc-black);
    color: #ffffff;
}

.nav-square svg {
    width: 15px;
    height: 15px;
}

.month-heading {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 19px;
    font-weight: 900;
    color: var(--dc-text);
    letter-spacing: -0.03em;
}

.month-heading svg {
    width: 16px;
    height: 16px;
    color: var(--dc-green);
}

.calendar-view-pill {
    min-height: 30px;
    padding: 0 11px;
    border: none;
    background: var(--dc-black);
    color: #ffffff;
    font-size: 11px;
    font-weight: 900;
    display: inline-flex;
    align-items: center;
}

.calendar-wrap {
    width: 100%;
    overflow: hidden;
}

.calendar-inner {
    width: 100%;
    min-width: 0;
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    background: var(--dc-soft);
}

.weekday {
    min-height: 32px;
    padding: 9px 11px;
    font-size: 10px;
    font-weight: 900;
    color: var(--dc-muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    border-right: 1px solid #edf2f7;
}

.weekday:last-child {
    border-right: none;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
}

.day-card {
    min-height: 88px;
    border: none;
    border-right: 1px solid #edf2f7;
    border-bottom: 1px solid #edf2f7;
    padding: 10px 11px;
    cursor: pointer;
    transition: 0.16s ease;
    outline: none;
    text-align: left;
    position: relative;
    width: 100%;
    min-width: 0;
    background: #ffffff;
    overflow: hidden;
}

.day-card:nth-child(7n) {
    border-right: none;
}

.day-card:hover:not(.disabled-card) {
    background: var(--dc-green-soft);
}

.day-card.selected {
    background: var(--dc-green-soft);
    box-shadow: inset 0 0 0 2px var(--dc-green);
}

.day-card.disabled-card {
    cursor: default;
}

/* Other month: neutral, not colored */
.day-card.other-month {
    background: #ffffff !important;
    color: inherit !important;
    opacity: 0.45;
}

.day-card.other-month:hover {
    background: #ffffff !important;
    box-shadow: none !important;
    transform: none !important;
}

/* Past date: no status color, no gray background */
.day-card.is-past {
    background: #ffffff !important;
    color: inherit !important;
    opacity: 0.55;
}

.day-card.is-past:hover {
    background: #ffffff !important;
    box-shadow: none !important;
    transform: none !important;
}

.day-card.is-past .day-number,
.day-card.other-month .day-number {
    color: inherit !important;
}

.day-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
}

.day-number {
    font-size: 13px;
    font-weight: 900;
    color: var(--dc-text);
}

.day-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    display: inline-flex;
    flex-shrink: 0;
    margin-top: 4px;
}

.day-dot.available {
    background: var(--dc-green);
}

.day-dot.half {
    background: #f59e0b;
}

.day-dot.unavailable {
    background: var(--dc-red);
}

/* Past dot removed */
.day-card.is-past .day-dot,
.day-dot.past {
    display: none !important;
}

.day-status {
    margin-top: 22px;
    display: inline-flex;
    align-items: center;
    min-height: 22px;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 900;
    line-height: 1;
    background: #ffffff;
}

/* Normal active status colors */
.status-available {
    color: var(--dc-green);
    background: var(--dc-green-soft);
}

.status-half {
    color: var(--dc-yellow);
    background: var(--dc-yellow-soft);
}

.status-unavailable {
    color: var(--dc-red);
    background: var(--dc-red-soft);
}

/* Past status: completely neutral */
.status-past,
.day-card.is-past .day-status,
.day-card.is-past .status-available,
.day-card.is-past .status-half,
.day-card.is-past .status-unavailable {
    color: inherit !important;
    background: transparent !important;
    box-shadow: none !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

/* Other month status: also neutral */
.day-card.other-month .day-status,
.day-card.other-month .status-available,
.day-card.other-month .status-half,
.day-card.other-month .status-unavailable {
    color: inherit !important;
    background: transparent !important;
    box-shadow: none !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

.day-time {
    margin-top: 8px;
    font-size: 10px;
    font-weight: 700;
    color: var(--dc-muted);
}

.day-card.is-past .day-time,
.day-card.other-month .day-time {
    display: none;
}

.past-availability-indicator {
    display: none !important;
}

/* Legend */
.legend-row {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    padding: 12px 15px 15px;
    background: #ffffff;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: var(--dc-muted);
    font-size: 11px;
    font-weight: 800;
}

.legend-dot {
    width: 9px;
    height: 9px;
    border-radius: 999px;
}

.dot-green {
    background: var(--dc-green);
}

.dot-yellow {
    background: #f59e0b;
}

.dot-red {
    background: var(--dc-red);
}

.dot-gray {
    background: var(--dc-muted);
}

/* Weekly panel */
.weekly-card {
    padding: 12px;
    background: #ffffff;
}

.weekly-calendar-hero {
    margin-bottom: 14px;
    padding: 13px;
    background: var(--dc-soft);
    display: flex;
    align-items: center;
    gap: 12px;
    overflow: hidden;
    position: relative;
}

.mini-calendar-widget {
    width: 60px;
    min-width: 60px;
    background: #ffffff;
    overflow: hidden;
    animation: calendarFloat 2.8s ease-in-out infinite;
    position: relative;
    z-index: 1;
}

.mini-calendar-top {
    height: 16px;
    background: var(--dc-green);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
}

.mini-calendar-top span {
    width: 7px;
    height: 7px;
    margin-top: -8px;
    border-radius: 999px;
    background: #ffffff;
}

.mini-calendar-body {
    padding: 8px;
    text-align: center;
}

.mini-calendar-month {
    font-size: 8px;
    font-weight: 900;
    color: var(--dc-green);
    margin-bottom: 8px;
    white-space: nowrap;
}

.mini-calendar-dots {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 4px;
    margin-bottom: 8px;
    justify-items: center;
}

.mini-calendar-dots span {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: #d1fae5;
}

.mini-calendar-dots span:nth-child(2),
.mini-calendar-dots span:nth-child(5) {
    background: #86efac;
}

.mini-calendar-check {
    width: 24px;
    height: 24px;
    margin: 0 auto;
    border-radius: 999px;
    background: var(--dc-green);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: checkPulse 1.8s ease-in-out infinite;
}

.mini-calendar-check svg {
    width: 14px;
    height: 14px;
}

.weekly-calendar-text {
    min-width: 0;
}

.weekly-calendar-label {
    font-size: 14px;
    font-weight: 900;
    color: var(--dc-text);
    margin-bottom: 4px;
}

.weekly-calendar-subtitle {
    font-size: 11px;
    line-height: 1.5;
    color: var(--dc-muted);
}

.weekly-title {
    margin: 0 0 12px;
    font-size: 16px;
    font-weight: 900;
    color: var(--dc-text);
}

.weekly-form {
    margin: 0;
}

.weekly-table-wrap {
    overflow-x: auto;
}

.weekly-table {
    width: 100%;
    border-collapse: collapse;
}

.weekly-table th,
.weekly-table td {
    padding: 10px 0;
    border-bottom: 1px solid var(--dc-border);
    text-align: left;
    font-size: 12px;
    vertical-align: middle;
    white-space: nowrap;
}

.weekly-table th {
    font-size: 10px;
    font-weight: 900;
    color: var(--dc-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.weekly-day-name {
    color: var(--dc-text);
    font-weight: 900;
}

.switch-btn {
    position: relative;
    width: 38px;
    height: 22px;
    border-radius: 999px;
    border: none;
    cursor: pointer;
    background: #d1d5db;
    padding: 0;
    transition: background 0.18s ease;
    display: inline-block;
    vertical-align: middle;
}

.switch-btn::after {
    content: '';
    width: 16px;
    height: 16px;
    border-radius: 999px;
    background: #ffffff;
    position: absolute;
    top: 3px;
    left: 3px;
    transition: left 0.18s ease;
}

.switch-btn.on {
    background: var(--dc-green);
}

.switch-btn.on::after {
    left: 19px;
}

.weekly-time-input,
.weekly-status-select {
    min-height: 32px;
    padding: 6px 8px;
    border: 1px solid var(--dc-border-dark);
    background: #ffffff;
    font-size: 12px;
    color: var(--dc-text);
    outline: none;
}

.weekly-time-input {
    width: 90px;
}

.weekly-status-select {
    width: 98px;
}

.weekly-time-input:focus,
.weekly-status-select:focus {
    border-color: var(--dc-green);
}

.weekly-time-text {
    color: var(--dc-text);
    font-weight: 900;
    font-size: 12px;
}

.weekly-time-dash {
    color: var(--dc-light);
    font-size: 12px;
}

.weekly-row .weekly-status-select,
.weekly-row .weekly-time-input {
    display: none;
}

.weekly-row .weekly-time-text,
.weekly-row .weekly-time-dash {
    display: inline;
}

.weekly-row.editing .weekly-status-select,
.weekly-row.editing .weekly-time-input {
    display: inline-block;
}

.weekly-row.editing .weekly-time-text,
.weekly-row.editing .weekly-time-dash {
    display: none;
}

.weekly-row .weekly-inline-save {
    display: none;
}

.weekly-row.editing .weekly-inline-save {
    display: inline-block;
}

.weekly-row.editing .weekly-inline-edit {
    display: none;
}

.weekly-action-btn {
    min-height: 30px;
    padding: 0 11px;
    border: none;
    background: var(--dc-soft-2);
    color: var(--dc-text);
    font-size: 11px;
    font-weight: 900;
    cursor: pointer;
}

.weekly-action-btn:hover {
    background: var(--dc-black);
    color: #ffffff;
}

.weekly-action-btn.save {
    background: var(--dc-green);
    color: #ffffff;
}

.weekly-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 14px;
}

.edit-schedule-btn,
.save-weekly-btn {
    min-height: 38px;
    padding: 0 14px;
    border: none;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.edit-schedule-btn {
    background: var(--dc-soft-2);
    color: var(--dc-text);
    display: inline-flex;
}

.edit-schedule-btn:hover {
    background: var(--dc-black);
    color: #ffffff;
}

.edit-schedule-btn svg {
    width: 14px;
    height: 14px;
}

.save-weekly-btn {
    background: var(--dc-green);
    color: #ffffff;
    display: none;
}

.save-weekly-btn.show {
    display: inline-flex;
}

.save-weekly-btn:hover {
    background: var(--dc-green-dark);
}

/* Calendar popup */
.calendar-action-popup {
    position: fixed;
    z-index: 1000;
    width: 210px;
}

.calendar-action-popup-inner {
    background: #ffffff;
    border: none;
    box-shadow: 0 22px 50px rgba(15, 23, 42, 0.18);
    padding: 9px;
    display: grid;
    gap: 7px;
}

.calendar-action-popup-title {
    font-size: 12px;
    font-weight: 900;
    color: var(--dc-text);
    padding: 5px 5px 9px;
    border-bottom: 1px solid var(--dc-border);
    margin-bottom: 2px;
}

.mini-action-form {
    margin: 0;
}

.mini-action-btn {
    width: 100%;
    min-height: 34px;
    border: none;
    padding: 0 12px;
    font-size: 11px;
    font-weight: 900;
    cursor: pointer;
    text-align: left;
    background: var(--dc-soft);
    color: var(--dc-text);
    transition: 0.16s ease;
}

.mini-action-btn.available {
    background: var(--dc-green-soft);
    color: var(--dc-green-dark);
}

.mini-action-btn.unavailable {
    background: var(--dc-red-soft);
    color: var(--dc-red);
}

.mini-action-btn.morning,
.mini-action-btn.afternoon {
    background: var(--dc-yellow-soft);
    color: var(--dc-yellow);
}

.mini-action-btn:hover {
    background: var(--dc-black);
    color: #ffffff;
}

/* Animations */
@keyframes calendarFloat {
    0% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-4px);
    }

    100% {
        transform: translateY(0);
    }
}

@keyframes checkPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(21, 128, 61, 0.28);
    }

    70% {
        box-shadow: 0 0 0 8px rgba(21, 128, 61, 0);
    }

    100% {
        box-shadow: 0 0 0 0 rgba(21, 128, 61, 0);
    }
}

/* Responsive */
@media (max-width: 1300px) {
    .availability-shell {
        grid-template-columns: minmax(0, 1fr) 320px;
    }

    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 1100px) {
    .availability-shell {
        grid-template-columns: 1fr;
    }

    .availability-right {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {
    .availability-layout {
        flex-direction: column;
    }

    .availability-content {
        height: auto;
        max-height: none;
        overflow-y: visible;
        padding: 14px;
    }

    .calendar-wrap {
        overflow-x: auto;
    }

    .calendar-inner {
        min-width: 720px;
    }

    .weekly-table {
        min-width: 560px;
    }
}

@media (max-width: 640px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }

    .calendar-header {
        align-items: flex-start;
    }

    .calendar-header-left {
        align-items: flex-start;
        flex-direction: column;
    }

    .month-heading {
        font-size: 18px;
    }

    .weekly-actions {
        flex-direction: column;
    }

    .edit-schedule-btn,
    .save-weekly-btn {
        width: 100%;
    }
}
</style>