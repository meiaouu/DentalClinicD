<?php
$appointment = $appointment ?? [];

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$patientName = trim(
    (string) ($appointment['patient_first_name'] ?? '') . ' ' .
    (string) ($appointment['patient_middle_name'] ?? '') . ' ' .
    (string) ($appointment['patient_last_name'] ?? '')
);

if ($patientName === '') {
    $patientName = trim(
        (string) ($appointment['guest_first_name'] ?? '') . ' ' .
        (string) ($appointment['guest_middle_name'] ?? '') . ' ' .
        (string) ($appointment['guest_last_name'] ?? '')
    );
}

if ($patientName === '') {
    $patientName = 'Unnamed Patient';
}

$notes = trim((string) ($appointment['request_notes'] ?? $appointment['remarks'] ?? ''));

ob_start();
?>

<style>
.detail-page {
    padding: 22px;
    background: #f8fafc;
    min-height: 100vh;
}

.detail-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 18px;
    max-width: 900px;
}

.detail-title {
    margin: 0 0 4px;
    font-size: 24px;
    color: #111827;
}

.detail-subtitle {
    margin: 0 0 18px;
    color: #6b7280;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.detail-item {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px;
}

.detail-label {
    font-size: 12px;
    color: #6b7280;
    font-weight: 700;
    margin-bottom: 5px;
}

.detail-value {
    color: #111827;
    font-weight: 700;
}

.back-btn {
    display: inline-flex;
    margin-top: 16px;
    height: 40px;
    padding: 0 14px;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: #111827;
    color: #ffffff;
    text-decoration: none;
    font-weight: 700;
}

@media (max-width: 700px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="detail-page">
    <div class="detail-card">
        <h1 class="detail-title">Appointment Details</h1>
        <p class="detail-subtitle">Assigned appointment information.</p>

        <div class="detail-grid">
            <div class="detail-item">
                <div class="detail-label">Patient Name</div>
                <div class="detail-value"><?= e($patientName) ?></div>
            </div>

            <div class="detail-item">
                <div class="detail-label">Service</div>
                <div class="detail-value"><?= e((string) ($appointment['service_name'] ?? 'Service')) ?></div>
            </div>

            <div class="detail-item">
                <div class="detail-label">Date</div>
                <div class="detail-value"><?= e(date('M d, Y', strtotime((string) $appointment['appointment_date']))) ?></div>
            </div>

            <div class="detail-item">
                <div class="detail-label">Time</div>
                <div class="detail-value">
                    <?= e(date('h:i A', strtotime((string) $appointment['start_time']))) ?>
                    -
                    <?= e(date('h:i A', strtotime((string) $appointment['end_time']))) ?>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-label">Status</div>
                <div class="detail-value"><?= e(ucwords(str_replace('_', ' ', (string) $appointment['status']))) ?></div>
            </div>

            <div class="detail-item">
                <div class="detail-label">Patient Type</div>
                <div class="detail-value"><?= empty($appointment['patient_id']) ? 'New' : 'Returning' ?></div>
            </div>

            <div class="detail-item">
                <div class="detail-label">Contact Number</div>
                <div class="detail-value">
                    <?= e((string) ($appointment['patient_contact_number'] ?? $appointment['guest_contact_number'] ?? 'N/A')) ?>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-label">Email</div>
                <div class="detail-value">
                    <?= e((string) ($appointment['patient_email'] ?? $appointment['guest_email'] ?? 'N/A')) ?>
                </div>
            </div>

            <div class="detail-item" style="grid-column:1/-1;">
                <div class="detail-label">Notes / Concern</div>
                <div class="detail-value"><?= e($notes !== '' ? $notes : 'No notes provided.') ?></div>
            </div>
        </div>

        <a href="/DentalClinic/public/dentist/appointments" class="back-btn">Back to Appointments</a>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Appointment Details';
require __DIR__ . '/../../layouts/main.php';