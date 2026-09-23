<?php

$authUser = $authUser ?? [];
$patient = $patient ?? null;
$stats = $stats ?? [];
$upcomingAppointments = $upcomingAppointments ?? [];
$recentRequests = $recentRequests ?? [];
$recentTreatments = $recentTreatments ?? [];
$billingSummary = $billingSummary ?? [];
$recentDocuments = $recentDocuments ?? [];
$recentNotifications = $recentNotifications ?? [];

if (!function_exists('pd_e')) {
    function pd_e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pd_money')) {
    function pd_money($value): string
    {
        return 'PHP ' . number_format((float) $value, 2);
    }
}

if (!function_exists('pd_date')) {
    function pd_date($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('M d, Y', $timestamp) : $value;
    }
}

if (!function_exists('pd_time')) {
    function pd_time($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('h:i A', $timestamp) : $value;
    }
}

if (!function_exists('pd_full_name')) {
    function pd_full_name(array $row, string $prefix = ''): string
    {
        return trim(implode(' ', array_filter([
            $row[$prefix . 'first_name'] ?? '',
            $row[$prefix . 'middle_name'] ?? '',
            $row[$prefix . 'last_name'] ?? '',
        ]))) ?: '—';
    }
}

if (!function_exists('pd_status')) {
    function pd_status($value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? ucwords(str_replace('_', ' ', $value)) : '—';
    }
}

ob_start();
?>

<style>
    :root {
        --pd-navy: #10233f;
        --pd-teal: #0d9e8c;
        --pd-teal-dark: #08796c;
        --pd-bg: #f4f8fb;
        --pd-card: #ffffff;
        --pd-text: #1f2937;
        --pd-muted: #64748b;
        --pd-line: #dbe5ef;
        --pd-soft: #e8f7f5;
        --pd-danger: #991b1b;
        --pd-success: #065f46;
    }

    body {
        background: var(--pd-bg);
        overflow-y: auto !important;
    }

    .patient-dashboard {
        min-height: 100vh;
        background:
            radial-gradient(circle at top left, rgba(13, 158, 140, 0.12), transparent 28%),
            linear-gradient(180deg, #f8fafc, #eef5f7);
        padding: 28px 18px 46px;
    }

    .pd-wrap {
        max-width: 1180px;
        margin: 0 auto;
    }

    .pd-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 22px;
    }

    .pd-title h1 {
        margin: 0;
        font-size: clamp(26px, 4vw, 38px);
        color: var(--pd-navy);
        letter-spacing: -0.04em;
    }

    .pd-title p {
        margin: 7px 0 0;
        color: var(--pd-muted);
    }

    .pd-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .pd-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        border-radius: 13px;
        padding: 0 16px;
        text-decoration: none;
        font-weight: 800;
        font-size: 13px;
        border: 1px solid transparent;
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .pd-btn:hover {
        transform: translateY(-1px);
    }

    .pd-btn-primary {
        background: var(--pd-teal);
        color: #fff;
        box-shadow: 0 12px 24px rgba(13, 158, 140, .22);
    }

    .pd-btn-light {
        background: #fff;
        color: var(--pd-navy);
        border-color: var(--pd-line);
    }

    .pd-alert {
        padding: 14px 16px;
        border-radius: 16px;
        margin-bottom: 18px;
        font-size: 14px;
        line-height: 1.5;
    }

    .pd-alert-error {
        background: #fef2f2;
        color: var(--pd-danger);
        border: 1px solid rgba(185, 28, 28, .16);
    }

    .pd-alert-success {
        background: #ecfdf5;
        color: var(--pd-success);
        border: 1px solid rgba(5, 150, 105, .16);
    }

    .pd-card {
        background: var(--pd-card);
        border: 1px solid rgba(219, 229, 239, .9);
        border-radius: 22px;
        box-shadow: 0 12px 34px rgba(15, 23, 42, .06);
    }

    .pd-unlinked {
        padding: 36px;
        text-align: center;
    }

    .pd-unlinked h2 {
        margin: 0 0 8px;
        color: var(--pd-navy);
    }

    .pd-unlinked p {
        color: var(--pd-muted);
        margin: 0 0 20px;
        line-height: 1.65;
    }

    .pd-stats {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 14px;
        margin-bottom: 18px;
    }

    .pd-stat {
        padding: 18px;
    }

    .pd-stat span {
        display: block;
        color: var(--pd-muted);
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .pd-stat strong {
        display: block;
        margin-top: 8px;
        color: var(--pd-navy);
        font-size: 24px;
        line-height: 1;
    }

    .pd-grid {
        display: grid;
        grid-template-columns: 360px 1fr;
        gap: 18px;
        align-items: start;
    }

    .pd-panel {
        padding: 22px;
        margin-bottom: 18px;
    }

    .pd-panel h2 {
        margin: 0 0 16px;
        color: var(--pd-navy);
        font-size: 19px;
        letter-spacing: -0.02em;
    }

    .pd-profile-list {
        display: grid;
        gap: 12px;
    }

    .pd-profile-item {
        border-bottom: 1px solid var(--pd-line);
        padding-bottom: 10px;
    }

    .pd-profile-item:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .pd-label {
        color: var(--pd-muted);
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 3px;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .pd-value {
        color: var(--pd-text);
        font-weight: 700;
        word-break: break-word;
    }

    .pd-table-wrap {
        overflow-x: auto;
    }

    .pd-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 720px;
    }

    .pd-table th,
    .pd-table td {
        padding: 12px 10px;
        text-align: left;
        border-bottom: 1px solid var(--pd-line);
        vertical-align: top;
        font-size: 13px;
    }

    .pd-table th {
        color: var(--pd-muted);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    .pd-table td {
        color: var(--pd-text);
    }

    .pd-empty {
        padding: 18px;
        border: 1px dashed var(--pd-line);
        border-radius: 16px;
        color: var(--pd-muted);
        text-align: center;
        background: #f8fafc;
    }

    .pd-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        background: var(--pd-soft);
        color: var(--pd-teal-dark);
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .pd-link {
        color: var(--pd-teal-dark);
        font-weight: 900;
        text-decoration: none;
    }

    .pd-link:hover {
        text-decoration: underline;
    }

    .pd-billing-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 14px;
    }

    .pd-billing-box {
        background: #f8fafc;
        border: 1px solid var(--pd-line);
        border-radius: 16px;
        padding: 14px;
    }

    .pd-billing-box span {
        display: block;
        color: var(--pd-muted);
        font-size: 12px;
        font-weight: 800;
    }

    .pd-billing-box strong {
        display: block;
        margin-top: 5px;
        color: var(--pd-navy);
    }

    .pd-quick {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }

    .pd-quick a {
        text-decoration: none;
        color: var(--pd-navy);
        background: #fff;
        border: 1px solid var(--pd-line);
        border-radius: 15px;
        padding: 14px;
        font-weight: 900;
        text-align: center;
    }

    @media (max-width: 1050px) {
        .pd-stats {
            grid-template-columns: repeat(3, 1fr);
        }

        .pd-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 700px) {
        .patient-dashboard {
            padding: 22px 12px 34px;
        }

        .pd-topbar {
            align-items: flex-start;
            flex-direction: column;
        }

        .pd-stats {
            grid-template-columns: repeat(2, 1fr);
        }

        .pd-billing-grid,
        .pd-quick {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="patient-dashboard">
    <div class="pd-wrap">
        

        <?php if (!empty($flash_error)): ?>
            <div class="pd-alert pd-alert-error"><?= pd_e($flash_error) ?></div>
        <?php endif; ?>

        <?php if (!empty($flash_success)): ?>
            <div class="pd-alert pd-alert-success"><?= pd_e($flash_success) ?></div>
        <?php endif; ?>

        <?php if (empty($patient)): ?>
            <div class="pd-card pd-unlinked">
                <h2>Your patient profile is not yet linked.</h2>
                <p>Please contact the clinic staff so they can link your patient record to your portal account.</p>
                <a class="pd-btn pd-btn-primary" href="/DentalClinic/public/">Go to Home</a>
            </div>
        <?php else: ?>
            <?php
    $hasMedicalHistory = !empty($patient['medical_history_completed']);
    $hasDentalHistory  = !empty($patient['dental_history_completed']);
        $verificationStatus = strtolower((string) ($patient['verification_status'] ?? 'incomplete'));

    $requirementsComplete = $hasMedicalHistory && $hasDentalHistory;
    ?>

        <?php if ($verificationStatus === 'submitted'): ?>
            <section class="pd-card pd-panel" style="margin-bottom:18px;background:#ecfdf5;border-color:#a7f3d0;">
                <h2>Your information is under review</h2>
                <p style="color:#065f46;">Your medical and dental history was submitted successfully. Clinic staff will review your patient record.</p>
            </section>
        <?php elseif ($verificationStatus === 'approved'): ?>
            <section class="pd-card pd-panel" style="margin-bottom:18px;background:#ecfdf5;border-color:#a7f3d0;">
                <h2>Your patient account has been approved</h2>
                <p style="color:#065f46;">Your information has been reviewed and your patient record is active.</p>
            </section>
        <?php elseif ($verificationStatus === 'requires_update' || $verificationStatus === 'rejected'): ?>
            <section class="pd-card pd-panel" style="margin-bottom:18px;background:#fffbeb;border-color:#fde68a;">
                <h2><?= $verificationStatus === 'rejected' ? 'Your information was not approved' : 'Your information requires an update' ?></h2>
                <p style="color:#92400e;"><?= pd_e($patient['verification_notes'] ?? 'Please review and resubmit your information.') ?></p>
                <a href="/DentalClinic/public/patient/medical-history" class="pd-btn pd-btn-primary">Update Medical &amp; Dental History</a>
            </section>
        <?php endif; ?>

    <?php if (!$requirementsComplete): ?>
        <section class="pd-card pd-panel" style="margin-bottom:18px;">
            <h2>Complete Your Patient Information</h2>

            <p style="color:#64748b; margin-bottom:20px;">
                Please complete the required information below to finish your patient profile.
            </p>

            <div style="display:grid; gap:14px;">

                <div style="border:1px solid #e5e7eb; border-radius:14px; padding:16px;">
                    <strong>Medical History</strong>

                    <p style="margin-top:8px;">
                        Status:
                        <?php if ($hasMedicalHistory): ?>
                            <span style="color:#047857;font-weight:700;">Completed</span>
                        <?php else: ?>
                            <span style="color:#b45309;font-weight:700;">Required</span>
                        <?php endif; ?>
                    </p>

                    <?php if (!$hasMedicalHistory): ?>
                        <a href="/DentalClinic/public/patient/medical-history"
                           class="pd-btn pd-btn-primary"
                           style="margin-top:10px;">
                            Fill Medical History
                        </a>
                    <?php endif; ?>
                </div>

                <div style="border:1px solid #e5e7eb; border-radius:14px; padding:16px;">
                    <strong>Dental History</strong>

                    <p style="margin-top:8px;">
                        Status:
                        <?php if ($hasDentalHistory): ?>
                            <span style="color:#047857;font-weight:700;">Completed</span>
                        <?php else: ?>
                            <span style="color:#b45309;font-weight:700;">Required</span>
                        <?php endif; ?>
                    </p>

                    <?php if (!$hasDentalHistory): ?>
                        <a href="/DentalClinic/public/patient/medical-history"
                           class="pd-btn pd-btn-primary"
                           style="margin-top:10px;">
                            Fill Dental History
                        </a>
                    <?php endif; ?>
                </div>

            </div>

            <div style="
                margin-top:16px;
                padding:14px;
                border-radius:12px;
                background:#fffbeb;
                border:1px solid #fde68a;
                color:#92400e;
            ">
                Please complete all required information.
            </div>
        </section>
    <?php endif; ?>

            <div class="pd-stats">
                <div class="pd-card pd-stat">
                    <span>Upcoming</span>
                    <strong><?= (int) ($stats['upcoming_appointments'] ?? 0) ?></strong>
                </div>

                <div class="pd-card pd-stat">
                    <span>Pending Requests</span>
                    <strong><?= (int) ($stats['pending_requests'] ?? 0) ?></strong>
                </div>

                <div class="pd-card pd-stat">
                    <span>Completed</span>
                    <strong><?= (int) ($stats['completed_appointments'] ?? 0) ?></strong>
                </div>

                <div class="pd-card pd-stat">
                    <span>Balance</span>
                    <strong><?= pd_money($stats['remaining_balance'] ?? 0) ?></strong>
                </div>

                <div class="pd-card pd-stat">
                    <span>Documents</span>
                    <strong><?= (int) ($stats['documents_count'] ?? 0) ?></strong>
                </div>

                <div class="pd-card pd-stat">
                    <span>Unread</span>
                    <strong><?= (int) ($stats['unread_notifications'] ?? 0) ?></strong>
                </div>
            </div>

            <div class="pd-grid">
                <aside>
                    <section class="pd-card pd-panel">
                        <h2>Profile Summary</h2>

                        <div class="pd-profile-list">
                            <div class="pd-profile-item">
                                <div class="pd-label">Patient Code</div>
                                <div class="pd-value"><?= pd_e($patient['patient_code'] ?? '—') ?></div>
                            </div>

                            <div class="pd-profile-item">
                                <div class="pd-label">Full Name</div>
                                <div class="pd-value"><?= pd_e(pd_full_name($patient)) ?></div>
                            </div>

                            <div class="pd-profile-item">
                                <div class="pd-label">Contact Number</div>
                                <div class="pd-value"><?= pd_e($patient['contact_number'] ?? '—') ?></div>
                            </div>

                            <div class="pd-profile-item">
                                <div class="pd-label">Email</div>
                                <div class="pd-value"><?= pd_e($patient['email'] ?? '—') ?></div>
                            </div>

                            <div class="pd-profile-item">
                                <div class="pd-label">Birthdate</div>
                                <div class="pd-value"><?= pd_e(pd_date($patient['birth_date'] ?? '')) ?></div>
                            </div>

                            <div class="pd-profile-item">
                                <div class="pd-label">Sex / Civil Status</div>
                                <div class="pd-value">
                                    <?= pd_e(pd_status($patient['sex'] ?? '')) ?> /
                                    <?= pd_e(pd_status($patient['civil_status'] ?? '')) ?>
                                </div>
                            </div>

                            <div class="pd-profile-item">
                                <div class="pd-label">Address</div>
                                <div class="pd-value"><?= pd_e($patient['address'] ?? '—') ?></div>
                            </div>

                            <div class="pd-profile-item">
                                <div class="pd-label">Profile Status</div>
                                <div class="pd-value">
                                    <span class="pd-badge"><?= pd_e(pd_status($patient['profile_status'] ?? 'active')) ?></span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="pd-card pd-panel">
                        <h2>Quick Actions</h2>

                        <div class="pd-quick">
                            <a href="/DentalClinic/public/?open_booking=1">Book</a>
                            <a href="/DentalClinic/public/patient/appointments">Appointments</a>
                            <a href="/DentalClinic/public/patient/documents">Documents</a>
                            <a href="#billing">Billing</a>
                            <a href="/DentalClinic/public/patient/privacy-requests/create">Privacy Request</a>
                            <a href="/DentalClinic/public/settings">Settings</a>
                        </div>
                    </section>
                </aside>

                <main>
                    <section class="pd-card pd-panel">
                        <h2>Upcoming Appointments</h2>

                        <?php if (empty($upcomingAppointments)): ?>
                            <div class="pd-empty">No upcoming appointments.</div>
                        <?php else: ?>
                            <div class="pd-table-wrap">
                                <table class="pd-table">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Service</th>
                                            <th>Dentist</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                            <th>Arrival</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcomingAppointments as $appointment): ?>
                                            <tr>
                                                <td><?= pd_e($appointment['appointment_code'] ?? '—') ?></td>
                                                <td><?= pd_e($appointment['service_name'] ?? 'Dental Service') ?></td>
                                                <td>
                                                    Dr.
                                                    <?= pd_e(trim(($appointment['dentist_first_name'] ?? '') . ' ' . ($appointment['dentist_last_name'] ?? '')) ?: '—') ?>
                                                </td>
                                                <td><?= pd_e(pd_date($appointment['appointment_date'] ?? '')) ?></td>
                                                <td><?= pd_e(pd_time($appointment['start_time'] ?? '')) ?> - <?= pd_e(pd_time($appointment['end_time'] ?? '')) ?></td>
                                                <td><span class="pd-badge"><?= pd_e(pd_status($appointment['status'] ?? '')) ?></span></td>
                                                <td><?= pd_e(pd_status($appointment['arrival_status'] ?? '')) ?></td>
                                                <td>
                                                    <a class="pd-link" href="/DentalClinic/public/patient/appointments/show?id=<?= (int) ($appointment['appointment_id'] ?? 0) ?>">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="pd-card pd-panel">
                        <h2>Recent Appointment Requests</h2>

                        <?php if (empty($recentRequests)): ?>
                            <div class="pd-empty">No appointment requests yet.</div>
                        <?php else: ?>
                            <div class="pd-table-wrap">
                                <table class="pd-table">
                                    <thead>
                                        <tr>
                                            <th>Request Code</th>
                                            <th>Service</th>
                                            <th>Preferred Schedule</th>
                                            <th>Status</th>
                                            <th>Staff Notes</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentRequests as $request): ?>
                                            <tr>
                                                <td><?= pd_e($request['request_code'] ?? '—') ?></td>
                                                <td><?= pd_e($request['service_name'] ?? 'Selected Service') ?></td>
                                                <td>
                                                    <?= pd_e(pd_date($request['preferred_date'] ?? '')) ?>
                                                    <?= pd_e(pd_time($request['preferred_start_time'] ?? '')) ?>
                                                </td>
                                                <td><span class="pd-badge"><?= pd_e(pd_status($request['request_status'] ?? '')) ?></span></td>
                                                <td><?= pd_e($request['staff_notes'] ?? '—') ?></td>
                                                <td>
                                                    <a class="pd-link" href="/DentalClinic/public/patient/appointments/request?id=<?= (int) ($request['request_id'] ?? 0) ?>">
                                                        Track
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="pd-card pd-panel">
                        <h2>Treatment History Summary</h2>

                        <?php if (empty($recentTreatments)): ?>
                            <div class="pd-empty">No treatment history yet.</div>
                        <?php else: ?>
                            <div class="pd-table-wrap">
                                <table class="pd-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Procedure</th>
                                            <th>Service</th>
                                            <th>Dentist</th>
                                            <th>Status</th>
                                            <th>Charge</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentTreatments as $treatment): ?>
                                            <tr>
                                                <td><?= pd_e(pd_date($treatment['treatment_date'] ?? '')) ?></td>
                                                <td><?= pd_e($treatment['procedure_name'] ?: 'Dental Treatment') ?></td>
                                                <td><?= pd_e($treatment['service_name'] ?? '—') ?></td>
                                                <td>
                                                    Dr.
                                                    <?= pd_e(trim(($treatment['dentist_first_name'] ?? '') . ' ' . ($treatment['dentist_last_name'] ?? '')) ?: '—') ?>
                                                </td>
                                                <td><span class="pd-badge"><?= pd_e(pd_status($treatment['treatment_status'] ?? '')) ?></span></td>
                                                <td><?= pd_money($treatment['actual_charge'] ?? 0) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="pd-card pd-panel" id="billing">
                        <h2>Billing Summary</h2>

                        <div class="pd-billing-grid">
                            <div class="pd-billing-box">
                                <span>Total Billed</span>
                                <strong><?= pd_money($billingSummary['total_billed'] ?? 0) ?></strong>
                            </div>
                            <div class="pd-billing-box">
                                <span>Total Paid</span>
                                <strong><?= pd_money($billingSummary['total_paid'] ?? 0) ?></strong>
                            </div>
                            <div class="pd-billing-box">
                                <span>Remaining Balance</span>
                                <strong><?= pd_money($billingSummary['remaining_balance'] ?? 0) ?></strong>
                            </div>
                        </div>

                        <?php if (empty($billingSummary['recent_records'])): ?>
                            <div class="pd-empty">No billing records yet.</div>
                        <?php else: ?>
                            <div class="pd-table-wrap">
                                <table class="pd-table">
                                    <thead>
                                        <tr>
                                            <th>Billing No.</th>
                                            <th>Total</th>
                                            <th>Paid</th>
                                            <th>Balance</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($billingSummary['recent_records'] as $billing): ?>
                                            <tr>
                                                <td><?= pd_e($billing['billing_number'] ?? '—') ?></td>
                                                <td><?= pd_money($billing['total_amount'] ?? 0) ?></td>
                                                <td><?= pd_money($billing['amount_paid'] ?? 0) ?></td>
                                                <td><?= pd_money($billing['balance'] ?? 0) ?></td>
                                                <td><span class="pd-badge"><?= pd_e(pd_status($billing['payment_status'] ?? '')) ?></span></td>
                                                <td><?= pd_e(pd_date($billing['created_at'] ?? '')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="pd-card pd-panel">
                        <h2>Documents</h2>

                        <?php if (empty($recentDocuments)): ?>
                            <div class="pd-empty">No documents uploaded yet.</div>
                        <?php else: ?>
                            <div class="pd-table-wrap">
                                <table class="pd-table">
                                    <thead>
                                        <tr>
                                            <th>Document</th>
                                            <th>Category</th>
                                            <th>Type</th>
                                            <th>Date</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentDocuments as $document): ?>
                                            <tr>
                                                <td><?= pd_e($document['original_file_name'] ?? 'Document') ?></td>
                                                <td><?= pd_e(pd_status($document['file_category'] ?? '')) ?></td>
                                                <td><?= pd_e($document['mime_type'] ?? '—') ?></td>
                                                <td><?= pd_e(pd_date($document['created_at'] ?? '')) ?></td>
                                                <td>
                                                    <a class="pd-link" href="/DentalClinic/public/patient/documents/file?id=<?= (int) ($document['attachment_id'] ?? 0) ?>" target="_blank" rel="noopener">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="pd-card pd-panel">
                        <h2>Notifications</h2>

                        <?php if (empty($recentNotifications)): ?>
                            <div class="pd-empty">No notifications yet.</div>
                        <?php else: ?>
                            <div class="pd-table-wrap">
                                <table class="pd-table">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Message</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentNotifications as $notification): ?>
                                            <tr>
                                                <td><?= pd_e($notification['title'] ?? 'Notification') ?></td>
                                                <td><?= pd_e($notification['message'] ?? '') ?></td>
                                                <td>
                                                    <span class="pd-badge">
                                                        <?= ((int) ($notification['is_read'] ?? 0) === 1) ? 'Read' : 'Unread' ?>
                                                    </span>
                                                </td>
                                                <td><?= pd_e(pd_date($notification['created_at'] ?? '')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </main>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Patient Dashboard';
$pageTitle = 'Patient Dashboard';
require __DIR__ . '/../layouts/app.php';