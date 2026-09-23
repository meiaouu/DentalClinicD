<?php

use App\Core\Csrf;

$appointment = $appointment ?? [];
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

$birthDate = (string) ($appointment['patient_birth_date'] ?? '');
$age = 'N/A';

if ($birthDate !== '') {
    $birthTimestamp = strtotime($birthDate);
    if ($birthTimestamp !== false) {
        $today = new DateTime();
        $birth = new DateTime($birthDate);
        $age = (string) $today->diff($birth)->y;
    }
}

ob_start();
?>
<style>
    .appointment-page {
        display: grid;
        gap: 16px;
    }

    .appointment-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 20px;
    }

    .appointment-title {
        margin: 0 0 6px;
        font-size: 26px;
        font-weight: 800;
        color: #111827;
    }

    .appointment-copy {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
        line-height: 1.6;
    }

    .flash-box {
        border-radius: 12px;
        padding: 12px 14px;
        font-size: 14px;
        font-weight: 700;
        border: 1px solid transparent;
    }

    .flash-success {
        background: #ecfdf5;
        color: #065f46;
        border-color: #a7f3d0;
    }

    .flash-error {
        background: #fef2f2;
        color: #991b1b;
        border-color: #fecaca;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px 18px;
    }

    .field-label {
        font-size: 12px;
        font-weight: 800;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: 4px;
    }

    .field-value {
        color: #111827;
        font-size: 14px;
        line-height: 1.6;
        font-weight: 600;
    }

    .form-group {
        display: grid;
        gap: 6px;
        margin-bottom: 12px;
    }

    .form-group label {
        font-size: 13px;
        font-weight: 700;
        color: #374151;
    }

    .form-group textarea {
        width: 100%;
        min-height: 84px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 14px;
        outline: none;
        resize: vertical;
        box-sizing: border-box;
    }

    .form-group textarea:focus {
        border-color: #0f766e;
        box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.10);
    }

    .actions-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }

    .action-card {
        border: 1px solid #eef2f7;
        border-radius: 14px;
        padding: 16px;
        background: #fafafa;
    }

    .action-title {
        margin: 0 0 6px;
        font-size: 16px;
        font-weight: 800;
        color: #111827;
    }

    .action-copy {
        margin: 0 0 12px;
        color: #6b7280;
        font-size: 13px;
        line-height: 1.6;
    }

    .btn-primary,
    .btn-warning,
    .btn-danger,
    .btn-light {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 0 14px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
        border: 1px solid transparent;
        cursor: pointer;
    }

    .btn-primary {
        background: #0f9d8a;
        color: #fff;
        border-color: #0f9d8a;
    }

    .btn-warning {
        background: #f59e0b;
        color: #fff;
        border-color: #f59e0b;
    }

    .btn-danger {
        background: #dc2626;
        color: #fff;
        border-color: #dc2626;
    }

    .btn-light {
        background: #fff;
        color: #374151;
        border-color: #d1d5db;
    }

    .status-list {
        margin: 0;
        padding-left: 18px;
        display: grid;
        gap: 8px;
        color: #374151;
        font-size: 14px;
        line-height: 1.7;
    }

    @media (max-width: 900px) {
        .actions-grid,
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="appointment-page">
    <?php if ($flash_success): ?>
        <div class="flash-box flash-success"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div class="flash-box flash-error"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="appointment-card">
        <h1 class="appointment-title">Appointment Details</h1>
        <p class="appointment-copy">Review appointment, patient details, and manage clinic day actions.</p>
    </section>

    <section class="appointment-card">
        <div class="detail-grid">
            <div>
                <div class="field-label">Appointment Code</div>
                <div class="field-value"><?= htmlspecialchars((string) ($appointment['appointment_code'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div>
                <div class="field-label">Status</div>
                <div class="field-value"><?= htmlspecialchars((string) ($appointment['status'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div>
                <div class="field-label">Patient</div>
                <div class="field-value">
                    <?= htmlspecialchars(trim((string) (($appointment['patient_first_name'] ?? '') . ' ' . ($appointment['patient_middle_name'] ?? '') . ' ' . ($appointment['patient_last_name'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>

            <div>
                <div class="field-label">Contact Number</div>
                <div class="field-value"><?= htmlspecialchars((string) ($appointment['patient_contact_number'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div>
                <div class="field-label">Sex</div>
                <div class="field-value"><?= htmlspecialchars((string) (($appointment['patient_sex'] ?? '') !== '' ? $appointment['patient_sex'] : 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div>
                <div class="field-label">Birthdate</div>
                <div class="field-value"><?= htmlspecialchars($birthDate !== '' ? $birthDate : 'N/A', ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div>
                <div class="field-label">Age</div>
                <div class="field-value"><?= htmlspecialchars($age, ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div>
                <div class="field-label">Civil Status</div>
                <div class="field-value"><?= htmlspecialchars((string) (($appointment['patient_civil_status'] ?? '') !== '' ? $appointment['patient_civil_status'] : 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div>
                <div class="field-label">Occupation</div>
                <div class="field-value"><?= htmlspecialchars((string) (($appointment['patient_occupation'] ?? '') !== '' ? $appointment['patient_occupation'] : 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div>
                <div class="field-label">Dentist</div>
                <div class="field-value"><?= htmlspecialchars(trim((string) (($appointment['dentist_first_name'] ?? '') . ' ' . ($appointment['dentist_last_name'] ?? ''))), ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div>
                <div class="field-label">Service</div>
                <div class="field-value"><?= htmlspecialchars((string) ($appointment['service_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div>
                <div class="field-label">Date</div>
                <div class="field-value"><?= htmlspecialchars((string) ($appointment['appointment_date'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div>
                <div class="field-label">Time</div>
                <div class="field-value">
                    <?= htmlspecialchars((string) ($appointment['start_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    -
                    <?= htmlspecialchars((string) ($appointment['end_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>

            <div>
                <div class="field-label">Arrival Status</div>
                <div class="field-value"><?= htmlspecialchars((string) (($appointment['arrival_status'] ?? '') !== '' ? $appointment['arrival_status'] : 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div style="grid-column: 1 / -1;">
                <div class="field-label">Remarks</div>
                <div class="field-value"><?= nl2br(htmlspecialchars((string) (($appointment['remarks'] ?? '') !== '' ? $appointment['remarks'] : 'No remarks'), ENT_QUOTES, 'UTF-8')) ?></div>
            </div>
        </div>
    </section>

    <section class="appointment-card">
        <h2 class="appointment-title" style="font-size:20px;">Clinic Day Actions</h2>
        <p class="appointment-copy" style="margin-bottom:16px;">Keep only the main actions for a simpler workflow.</p>

        <div class="actions-grid">
            <div class="action-card">
                <h3 class="action-title">Complete</h3>
                <p class="action-copy">Mark this appointment as completed.</p>
                <form method="POST" action="/DentalClinic/public/staff/appointments/complete">
                    <?= Csrf::inputField(); ?>
                    <input type="hidden" name="appointment_id" value="<?= (int) ($appointment['appointment_id'] ?? 0) ?>">

                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks"></textarea>
                    </div>

                    <button type="submit" class="btn-primary">Complete</button>
                </form>
            </div>

            <div class="action-card">
                <h3 class="action-title">No Show</h3>
                <p class="action-copy">Mark the patient as no-show if they did not arrive.</p>
                <form method="POST" action="/DentalClinic/public/staff/appointments/no-show" onsubmit="return confirm('Are you sure you want to mark this appointment as no-show?');">
                    <?= Csrf::inputField(); ?>
                    <input type="hidden" name="appointment_id" value="<?= (int) ($appointment['appointment_id'] ?? 0) ?>">

                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks"></textarea>
                    </div>

                    <button type="submit" class="btn-warning">Mark No Show</button>
                </form>
            </div>

            <div class="action-card">
                <h3 class="action-title">Cancel</h3>
                <p class="action-copy">Cancel this appointment if needed.</p>
                <form method="POST" action="/DentalClinic/public/staff/appointments/cancel" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                    <?= Csrf::inputField(); ?>
                    <input type="hidden" name="appointment_id" value="<?= (int) ($appointment['appointment_id'] ?? 0) ?>">

                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks"></textarea>
                    </div>

                    <button type="submit" class="btn-danger">Cancel Appointment</button>
                </form>
            </div>
        </div>
    </section>

    <section class="appointment-card">
        <div class="field-label" style="margin-bottom:10px;">Status History</div>

        <?php if (empty($appointment['status_logs'])): ?>
            <div class="field-value">No status logs yet.</div>
        <?php else: ?>
            <ul class="status-list">
                <?php foreach ($appointment['status_logs'] as $log): ?>
                    <li>
                        <strong><?= htmlspecialchars((string) ($log['old_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                        →
                        <strong><?= htmlspecialchars((string) ($log['new_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                        at <?= htmlspecialchars((string) ($log['changed_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($log['remarks'])): ?>
                            <br><?= nl2br(htmlspecialchars((string) $log['remarks'], ENT_QUOTES, 'UTF-8')) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <a href="/DentalClinic/public/staff/appointments?date=<?= urlencode((string) ($appointment['appointment_date'] ?? date('Y-m-d'))) ?>" class="btn-light">
        Back to Appointments
    </a>
</div>

<?php
$staffContent = ob_get_clean();
$pageTitle = 'Appointment Details';
require __DIR__ . '/../layouts/app.php';