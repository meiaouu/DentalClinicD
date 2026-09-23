<?php

$baseUrl = $baseUrl ?? '/DentalClinic/public';
$pageTitle = $pageTitle ?? 'Patient Portal';
$patientUserName = $patientUserName ?? 'Patient';
$patientUserInitial = $patientUserInitial ?? strtoupper(substr($patientUserName, 0, 1));

if (!function_exists('patient_topbar_e')) {
    function patient_topbar_e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
?>

<header class="patient-topbar">
    <div class="patient-topbar-left">
        <button type="button" class="patient-menu-btn" data-patient-sidebar-toggle aria-label="Open menu">
            ☰
        </button>

        <div class="patient-topbar-title">
            <h1><?= patient_topbar_e($pageTitle) ?></h1>
            <p>Manage your appointments, records, documents, and clinic updates.</p>
        </div>
    </div>

    <div class="patient-topbar-actions">
        <a class="patient-topbar-btn primary" href="<?= patient_topbar_e($baseUrl . '/?open_booking=1') ?>">
            Book Appointment
        </a>

        <a class="patient-topbar-btn" href="<?= patient_topbar_e($baseUrl . '/patient/privacy-requests/create') ?>">
            Privacy Request
        </a>

        <div class="patient-topbar-profile">
            <div class="patient-user-avatar"><?= patient_topbar_e($patientUserInitial) ?></div>
            <span><?= patient_topbar_e($patientUserName) ?></span>
        </div>
    </div>
</header>