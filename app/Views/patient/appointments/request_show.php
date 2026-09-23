<?php
$request = $request ?? null;
$answers = $answers ?? [];
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

ob_start();
?>

<style>
    .request-page {
        max-width: 920px;
        margin: 28px auto;
        padding: 0 16px 28px;
    }

    .request-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 22px;
        margin-bottom: 16px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
    }

    .request-title {
        margin: 0 0 6px;
        font-size: 26px;
        font-weight: 800;
        color: #111827;
    }

    .request-copy {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
        line-height: 1.6;
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
    }

    .answers-list {
        margin: 0;
        padding-left: 18px;
        display: grid;
        gap: 8px;
        color: #374151;
        font-size: 14px;
        line-height: 1.7;
    }

    .btn-light {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 0 16px;
        border-radius: 12px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 700;
        border: 1px solid #d1d5db;
        color: #374151;
        background: #fff;
    }

    @media (max-width: 768px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="request-page">
    <?php if ($flash_success): ?>
        <div class="flash-box flash-success"><?= htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div class="flash-box flash-error"><?= htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="request-card">
        <h1 class="request-title">Appointment Request Details</h1>
        <p class="request-copy">Review the details of your submitted request.</p>
    </section>

    <?php if ($request): ?>
        <section class="request-card">
            <div class="detail-grid">
                <div>
                    <div class="field-label">Request Code</div>
                    <div class="field-value"><?= htmlspecialchars((string) ($request['request_code'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div>
                    <div class="field-label">Status</div>
                    <div class="field-value"><?= htmlspecialchars((string) ($request['request_status'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div>
                    <div class="field-label">Service</div>
                    <div class="field-value"><?= htmlspecialchars((string) ($request['service_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div>
                    <div class="field-label">Preferred Dentist</div>
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
                    <div class="field-label">Preferred Date</div>
                    <div class="field-value"><?= htmlspecialchars((string) ($request['preferred_date'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div>
                    <div class="field-label">Preferred Time</div>
                    <div class="field-value"><?= htmlspecialchars((string) ($request['preferred_start_time'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div style="grid-column: 1 / -1;">
                    <div class="field-label">Notes</div>
                    <div class="field-value"><?= nl2br(htmlspecialchars((string) ($request['notes'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                </div>
            </div>
        </section>

        <?php if (!empty($answers)): ?>
            <section class="request-card">
                <div class="field-label" style="margin-bottom:10px;">Service Answers</div>
                <ul class="answers-list">
                    <?php foreach ($answers as $answer): ?>
                        <li>
                            <strong><?= htmlspecialchars((string) ($answer['option_name'] ?? 'Question'), ENT_QUOTES, 'UTF-8') ?>:</strong>
                            <?= htmlspecialchars((string) (($answer['value_label'] ?? '') ?: ($answer['answer_text'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <a href="/DentalClinic/public/patient/appointments" class="btn-light">Back to My Appointments</a>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Appointment Request Details';
require __DIR__ . '/../layouts/app.php';