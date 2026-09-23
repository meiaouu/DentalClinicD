<?php
$patient = $patient ?? null;
$requests = $requests ?? [];
$appointments = $appointments ?? [];
$requestPage = $requestPage ?? 1;
$appointmentPage = $appointmentPage ?? 1;
$requestTotal = $requestTotal ?? 0;
$appointmentTotal = $appointmentTotal ?? 0;
$perPage = $perPage ?? 10;
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

$requestPages = max(1, (int) ceil($requestTotal / $perPage));
$appointmentPages = max(1, (int) ceil($appointmentTotal / $perPage));

use App\Core\Csrf;

ob_start();
?>

<style>
    .patient-appt-page {
        max-width: 1100px;
        margin: 28px auto;
        padding: 0 16px 32px;
    }

    .patient-appt-header {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 22px;
        margin-bottom: 18px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
    }

    .patient-appt-title {
        margin: 0 0 6px;
        font-size: 28px;
        font-weight: 800;
        color: #111827;
    }

    .patient-appt-subtitle {
        margin: 0;
        color: #6b7280;
        line-height: 1.6;
        font-size: 14px;
    }

    .flash-box {
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 14px;
        font-size: 14px;
        font-weight: 600;
    }

    .flash-success {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .flash-error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    .quick-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 16px;
    }

    .btn-main,
    .btn-light,
    .btn-danger,
    .btn-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 0 16px;
        border-radius: 12px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 700;
        border: none;
        cursor: pointer;
    }

    .btn-main {
        background: #0f766e;
        color: #fff;
    }

    .btn-light {
        background: #fff;
        color: #374151;
        border: 1px solid #d1d5db;
    }

    .btn-danger {
        background: #dc2626;
        color: #fff;
    }

    .section-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 18px;
        margin-bottom: 18px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
    }

    .section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }

    .section-title {
        margin: 0;
        font-size: 20px;
        font-weight: 800;
        color: #111827;
    }

    .section-copy {
        margin: 4px 0 0;
        color: #6b7280;
        font-size: 14px;
    }

    .appointment-list {
        display: grid;
        gap: 14px;
    }

    .appointment-card {
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 16px;
        background: #fcfcfd;
    }

    .appointment-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }

    .appointment-code {
        margin: 0 0 4px;
        font-size: 16px;
        font-weight: 800;
        color: #111827;
    }

    .appointment-service {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .status-pill.pending,
    .status-pill.under_review,
    .status-pill.rescheduled {
        background: #fff7ed;
        color: #9a3412;
    }

    .status-pill.confirmed,
    .status-pill.checked_in,
    .status-pill.in_progress,
    .status-pill.completed {
        background: #ecfdf5;
        color: #065f46;
    }

    .status-pill.cancelled_by_patient,
    .status-pill.cancelled,
    .status-pill.rejected,
    .status-pill.no_show {
        background: #fef2f2;
        color: #991b1b;
    }

    .appointment-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px 18px;
        margin-bottom: 14px;
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
        word-break: break-word;
    }

    .card-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }

    .cancel-form {
        display: grid;
        gap: 10px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px dashed #d1d5db;
    }

    .cancel-form textarea {
        width: 100%;
        min-height: 80px;
        resize: vertical;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        padding: 10px 12px;
        font-size: 14px;
        outline: none;
        background: #fff;
    }

    .cancel-form textarea:focus {
        border-color: #0f766e;
        box-shadow: 0 0 0 3px rgba(15,118,110,0.10);
    }

    .empty-box {
        border: 1px dashed #d1d5db;
        border-radius: 14px;
        padding: 20px;
        text-align: center;
        color: #6b7280;
        background: #fafafa;
    }

    .pagination {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 16px;
    }

    .pagination a,
    .pagination span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 38px;
        height: 38px;
        padding: 0 12px;
        border-radius: 10px;
        border: 1px solid #d1d5db;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
        color: #374151;
        background: #fff;
    }

    .pagination .active {
        background: #0f766e;
        color: #fff;
        border-color: #0f766e;
    }

    @media (max-width: 768px) {
        .appointment-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="patient-appt-page">
    <section class="patient-appt-header">
        <h1 class="patient-appt-title">My Appointments</h1>
        <p class="patient-appt-subtitle">
            View your appointment requests, confirmed schedules, and manage pending requests.
        </p>

        <?php if ($patient): ?>
            <div class="quick-actions">
                <a href="/DentalClinic/public/book/patient" class="btn-main">Book Appointment</a>
                <span class="btn-light" style="cursor:default;">
                    <?= htmlspecialchars(trim(
                        ($patient['first_name'] ?? '') . ' ' .
                        ($patient['middle_name'] ?? '') . ' ' .
                        ($patient['last_name'] ?? '')
                    ), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($flash_success): ?>
        <div class="flash-box flash-success"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div class="flash-box flash-error"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="section-card">
        <div class="section-head">
            <div>
                <h2 class="section-title">My Appointment Requests</h2>
                <p class="section-copy">These are waiting for clinic review, confirmation, or rescheduling.</p>
            </div>
        </div>

        <?php if (empty($requests)): ?>
            <div class="empty-box">You do not have any appointment requests yet.</div>
        <?php else: ?>
            <div class="appointment-list">
                <?php foreach ($requests as $request): ?>
                    <?php $requestStatus = (string) ($request['request_status'] ?? 'pending'); ?>
                    <article class="appointment-card">
                        <div class="appointment-card-head">
                            <div>
                                <h3 class="appointment-code">
                                    <?= htmlspecialchars((string) ($request['request_code'] ?? 'Request'), ENT_QUOTES, 'UTF-8') ?>
                                </h3>
                                <p class="appointment-service">
                                    <?= htmlspecialchars((string) ($request['service_name'] ?? 'Dental Service'), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>

                            <span class="status-pill <?= htmlspecialchars($requestStatus, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', $requestStatus), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <div class="appointment-grid">
                            <div>
                                <div class="field-label">Preferred Date</div>
                                <div class="field-value"><?= htmlspecialchars((string) ($request['preferred_date'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>

                            <div>
                                <div class="field-label">Preferred Time</div>
                                <div class="field-value"><?= htmlspecialchars((string) ($request['preferred_start_time'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>

                            <div>
                                <div class="field-label">Dentist</div>
                                <div class="field-value">
                                    <?=
                                        htmlspecialchars(
                                            trim((string) (($request['dentist_first_name'] ?? '') . ' ' . ($request['dentist_last_name'] ?? ''))),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?: 'Not assigned yet'
                                    ?>
                                </div>
                            </div>

                            <div>
                                <div class="field-label">Notes</div>
                                <div class="field-value">
                                    <?= nl2br(htmlspecialchars((string) ($request['notes'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-actions">
                            <a href="/DentalClinic/public/patient/appointments/request?id=<?= urlencode((string) ($request['request_id'] ?? 0)) ?>" class="btn-light">
                                View Request
                            </a>
                        </div>

                        <?php if (in_array($requestStatus, ['pending', 'under_review', 'rescheduled'], true)): ?>
                            <form method="POST" action="/DentalClinic/public/patient/appointments/request/cancel" class="cancel-form">
                                <?= Csrf::inputField(); ?>
                                <input type="hidden" name="request_id" value="<?= (int) ($request['request_id'] ?? 0) ?>">

                                <div>
                                    <div class="field-label">Cancellation Reason</div>
                                    <textarea name="reason" placeholder="Optional reason"></textarea>
                                </div>

                                <div>
                                    <button
                                        type="submit"
                                        class="btn-danger"
                                        onclick="return confirm('Are you sure you want to cancel this appointment request?');">
                                        Cancel Request
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($requestPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $requestPages; $i++): ?>
                        <?php if ($i === (int) $requestPage): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="/DentalClinic/public/patient/appointments?request_page=<?= $i ?>&appointment_page=<?= (int) $appointmentPage ?>">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="section-card">
        <div class="section-head">
            <div>
                <h2 class="section-title">My Confirmed Appointments</h2>
                <p class="section-copy">These are already approved or recorded by the clinic.</p>
            </div>
        </div>

        <?php if (empty($appointments)): ?>
            <div class="empty-box">You do not have any confirmed appointments yet.</div>
        <?php else: ?>
            <div class="appointment-list">
                <?php foreach ($appointments as $appointment): ?>
                    <?php $appointmentStatus = (string) ($appointment['status'] ?? 'confirmed'); ?>
                    <article class="appointment-card">
                        <div class="appointment-card-head">
                            <div>
                                <h3 class="appointment-code">
                                    <?= htmlspecialchars((string) ($appointment['appointment_code'] ?? 'Appointment'), ENT_QUOTES, 'UTF-8') ?>
                                </h3>
                                <p class="appointment-service">
                                    <?= htmlspecialchars((string) ($appointment['service_name'] ?? 'Dental Service'), ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            </div>

                            <span class="status-pill <?= htmlspecialchars($appointmentStatus, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', $appointmentStatus), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <div class="appointment-grid">
                            <div>
                                <div class="field-label">Appointment Date</div>
                                <div class="field-value"><?= htmlspecialchars((string) ($appointment['appointment_date'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>

                            <div>
                                <div class="field-label">Time</div>
                                <div class="field-value">
                                    <?= htmlspecialchars((string) ($appointment['start_time'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!empty($appointment['end_time'])): ?>
                                        - <?= htmlspecialchars((string) $appointment['end_time'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <div class="field-label">Dentist</div>
                                <div class="field-value">
                                    <?=
                                        htmlspecialchars(
                                            trim((string) (($appointment['dentist_first_name'] ?? '') . ' ' . ($appointment['dentist_last_name'] ?? ''))),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?: 'Not assigned yet'
                                    ?>
                                </div>
                            </div>

                            <div>
                                <div class="field-label">Remarks</div>
                                <div class="field-value">
                                    <?= nl2br(htmlspecialchars((string) ($appointment['remarks'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </div>
                        </div>

                        <div class="card-actions">
                            <a href="/DentalClinic/public/patient/appointments/show?id=<?= urlencode((string) ($appointment['appointment_id'] ?? 0)) ?>" class="btn-light">
                                View Appointment
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($appointmentPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $appointmentPages; $i++): ?>
                        <?php if ($i === (int) $appointmentPage): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="/DentalClinic/public/patient/appointments?request_page=<?= (int) $requestPage ?>&appointment_page=<?= $i ?>">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'My Appointments';
require __DIR__ . '/../layouts/app.php';