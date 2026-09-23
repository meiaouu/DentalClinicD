<style>
/* PAGE */
.appointment-page {
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px;
}

/* HEADER (LIKE PATIENT PROFILE) */
.appointment-header {
    background: linear-gradient(135deg, #0f172a, #1e293b);
    color: #fff;
    padding: 20px 24px 16px;
    border-radius: 16px;
    margin-bottom: 18px;
}

.appointment-header h1 {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
}

.appointment-header p {
    margin-top: 4px;
    color: #94a3b8;
    font-size: 13px;
}

/* CARD */
.appointment-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

/* GRID */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

/* FIELD */
.field {
    display: flex;
    flex-direction: column;
}

.field-label {
    font-size: 12px;
    font-weight: 700;
    color: #6b7280;
    margin-bottom: 4px;
}

.field-value {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
}

/* FULL WIDTH */
.full {
    grid-column: span 3;
}

/* STATUS BADGE */
.status-badge {
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
    background: #eef2ff;
}

/* FLASH */
.flash {
    margin-bottom: 12px;
    padding: 12px;
    border-radius: 10px;
    font-weight: 700;
}

.success {
    background: #ecfdf5;
    color: #065f46;
}

.error {
    background: #fef2f2;
    color: #991b1b;
}

/* HISTORY */
.history-list {
    margin-top: 10px;
    padding-left: 16px;
    color: #374151;
}

/* BUTTON */
.back-btn {
    margin-top: 16px;
    display: inline-block;
    padding: 10px 16px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #d1d5db;
    font-weight: 700;
    text-decoration: none;
    color: #374151;
}

/* MOBILE */
@media (max-width: 900px) {
    .detail-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .full {
        grid-column: span 2;
    }
}

@media (max-width: 600px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: span 1;
    }
}
</style>

<div class="appointment-page">

    <!-- HEADER -->
    <div class="appointment-header">
        <h1>Appointment Details</h1>
        <p>Review your confirmed appointment information</p>
    </div>

    <?php if ($flash_success): ?>
        <div class="flash success"><?= htmlspecialchars($flash_success) ?></div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div class="flash error"><?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>

    <?php if ($appointment): ?>
        <div class="appointment-card">

            <div class="detail-grid">

                <div class="field">
                    <span class="field-label">Appointment Code</span>
                    <span class="field-value"><?= htmlspecialchars($appointment['appointment_code'] ?? 'N/A') ?></span>
                </div>

                <div class="field">
                    <span class="field-label">Status</span>
                    <span class="field-value">
                        <span class="status-badge"><?= htmlspecialchars($appointment['status'] ?? 'N/A') ?></span>
                    </span>
                </div>

                <div class="field">
                    <span class="field-label">Service</span>
                    <span class="field-value"><?= htmlspecialchars($appointment['service_name'] ?? 'N/A') ?></span>
                </div>

                <div class="field">
                    <span class="field-label">Dentist</span>
                    <span class="field-value">
                        <?= htmlspecialchars(trim(($appointment['dentist_first_name'] ?? '') . ' ' . ($appointment['dentist_last_name'] ?? ''))) ?: 'Not assigned' ?>
                    </span>
                </div>

                <div class="field">
                    <span class="field-label">Date</span>
                    <span class="field-value"><?= htmlspecialchars($appointment['appointment_date'] ?? 'N/A') ?></span>
                </div>

                <div class="field">
                    <span class="field-label">Time</span>
                    <span class="field-value">
                        <?= htmlspecialchars($appointment['start_time'] ?? '') ?>
                        - <?= htmlspecialchars($appointment['end_time'] ?? '') ?>
                    </span>
                </div>

                <div class="field">
                    <span class="field-label">Sex</span>
                    <span class="field-value"><?= htmlspecialchars($appointment['patient_sex'] ?? 'N/A') ?></span>
                </div>

                <div class="field">
                    <span class="field-label">Birthdate</span>
                    <span class="field-value"><?= htmlspecialchars($birthDate ?: 'N/A') ?></span>
                </div>

                <div class="field">
                    <span class="field-label">Age</span>
                    <span class="field-value"><?= htmlspecialchars($age) ?></span>
                </div>

                <div class="field">
                    <span class="field-label">Civil Status</span>
                    <span class="field-value"><?= htmlspecialchars($appointment['patient_civil_status'] ?? 'N/A') ?></span>
                </div>

                <div class="field">
                    <span class="field-label">Occupation</span>
                    <span class="field-value"><?= htmlspecialchars($appointment['patient_occupation'] ?? 'N/A') ?></span>
                </div>

                <div class="field full">
                    <span class="field-label">Service Description</span>
                    <span class="field-value"><?= nl2br(htmlspecialchars($appointment['service_description'] ?? '')) ?></span>
                </div>

                <div class="field full">
                    <span class="field-label">Remarks</span>
                    <span class="field-value"><?= nl2br(htmlspecialchars($appointment['remarks'] ?? 'No remarks')) ?></span>
                </div>

            </div>

        </div>

        <!-- STATUS HISTORY -->
        <?php if (!empty($appointment['status_logs'])): ?>
            <div class="appointment-card">
                <div class="field-label">Status History</div>
                <ul class="history-list">
                    <?php foreach ($appointment['status_logs'] as $log): ?>
                        <li>
                            <?= htmlspecialchars(($log['old_status'] ?? '') . ' → ' . ($log['new_status'] ?? '')) ?>
                            (<?= htmlspecialchars($log['changed_at'] ?? '') ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <a href="/DentalClinic/public/patient/appointments" class="back-btn">
        ← Back to Appointments
    </a>

</div>