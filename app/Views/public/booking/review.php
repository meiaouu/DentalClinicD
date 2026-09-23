<?php

use App\Core\Csrf;

$booking = $booking ?? [];
$serviceName = $serviceName ?? '';
$serviceNames = isset($serviceNames) && is_array($serviceNames) ? $serviceNames : [];

$e = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$displayValue = static function ($value): string {
    $value = trim((string) $value);
    return $value !== '' ? $value : 'N/A';
};

$preferredTimeValue = substr((string) ($booking['preferred_start_time'] ?? ''), 0, 5);

$selectedServiceIds = isset($selectedServiceIds) && is_array($selectedServiceIds)
    ? array_values(array_unique(array_filter(array_map('intval', $selectedServiceIds))))
    : [];

if (empty($selectedServiceIds) && !empty($booking['service_ids']) && is_array($booking['service_ids'])) {
    $selectedServiceIds = array_values(array_unique(array_map('intval', $booking['service_ids'])));
}

if (empty($selectedServiceIds) && !empty($booking['service_id'])) {
    $selectedServiceIds = [(int) $booking['service_id']];
}

$finalServiceDisplay = '';

if (!empty($serviceNames)) {
    $finalServiceDisplay = implode(', ', $serviceNames);
} elseif (trim((string) $serviceName) !== '') {
    $finalServiceDisplay = (string) $serviceName;
} else {
    $finalServiceDisplay = 'Selected Service';
}

$addressParts = array_filter([
    $booking['address_line'] ?? '',
    $booking['barangay_id'] ?? '',
    $booking['city_id'] ?? '',
    $booking['province_id'] ?? '',
    $booking['region_id'] ?? '',
], static function ($part): bool {
    return trim((string) $part) !== '';
});

$address = implode(', ', $addressParts);

$handledKeys = [
    'first_name',
    'last_name',
    'birth_date',
    'sex',
    'civil_status',
    'occupation',
    'contact_number',
    'email',
    'address_line',
    'notes',
    'preferred_date',
    'preferred_start_time',
    'service_id',
    'service_ids',
    'service_names',
    'region_id',
    'province_id',
    'city_id',
    'barangay_id',
    'preferred_dentist_id',
    'privacy_consent',
'wants_patient_account',
];

ob_start();
?>

<style>
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
    * {
        box-sizing: border-box;
    }

    body {
         background: var(--cream);
        color: #111827;
        overflow-x: hidden;
    }

    .review-bg {
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        overflow: hidden;
    }

    .review-bg::before {
        content: '';
        position: absolute;
        inset: -40px;
        background-image:
            linear-gradient(rgba(15, 118, 110, 0.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(15, 118, 110, 0.04) 1px, transparent 1px);
        background-size: 48px 48px;
        animation: reviewGridDrift 26s linear infinite;
    }

    .review-bg::after {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 15% 18%, rgba(20, 184, 166, 0.10), transparent 35%),
            radial-gradient(circle at 85% 75%, rgba(37, 99, 235, 0.08), transparent 32%),
            radial-gradient(circle at 50% 8%, rgba(15, 118, 110, 0.06), transparent 28%);
    }

    @keyframes reviewGridDrift {
        from {
            transform: translate(0, 0);
        }

        to {
            transform: translate(48px, 48px);
        }
    }

    .review-page {
        position: relative;
        z-index: 1;
        min-height: 100vh;
        padding: 22px 12px 36px;
        background: transparent;
    }

    .review-container {
        max-width: 820px;
        margin: 0 auto;
    }

    .review-header {
        text-align: center;
        margin-bottom: 14px;
    }

    .review-badge {
        display: inline-block;
        padding: 5px 12px;
        border: 1px solid #dbe5ef;
        border-radius: 999px;
        background: #ffffff;
        color: #0f766e;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
    }

    .review-title {
        margin: 0 0 6px;
        font-size: 26px;
        line-height: 1.2;
        font-weight: 800;
        color: #111827;
    }

   /* Booking-style stepper copied from booking form */
.stepper {
    display: flex;
    align-items: flex-start;
    max-width: 460px;
    margin: 22px auto 0;
}

.step {
    flex: 1;
    text-align: center;
    position: relative;
}

.step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 16px;
    left: 50%;
    width: 100%;
    height: 2px;
    background: #e4e1da;
}

.step.done:not(:last-child)::after,
.step.active:not(:last-child)::after {
    background: #2ec4a5;
}

.step-node {
    position: relative;
    z-index: 1;
    width: 32px;
    height: 32px;
    margin: 0 auto;
    border-radius: 50%;
    border: 2px solid #e4e1da;
    background: #ffffff;
    color: #b0aa9e;
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 700;
    transition: all 0.25s ease;
}

.step.active .step-node {
    border-color: #2ec4a5;
    background: #2ec4a5;
    color: #ffffff;
    box-shadow: 0 0 0 4px rgba(46, 196, 165, 0.18);
}

.step.done .step-node {
    border-color: #1fa88c;
    background: #1fa88c;
    color: #ffffff;
}

.step-label {
    margin-top: 8px;
    font-size: 11px;
    font-weight: 600;
    color: #b0aa9e;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.step.active .step-label,
.step.done .step-label {
    color: #1fa88c;
}

    .review-card {
        background: #ffffff;
        border: 1px solid #dbe5ef;
        border-radius: 14px;
        padding: 14px;
        box-shadow: 0 18px 50px rgba(15, 23, 42, 0.10);
    }

    .review-edit-note {
        display: none;
        margin-bottom: 12px;
        padding: 9px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        background: #ffffff;
        color: #374151;
        font-size: 13px;
        font-weight: 700;
    }

    .review-card.is-editing .review-edit-note {
        display: block;
    }

    .review-section {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
        background: #ffffff;
    }

    .review-section + .review-section {
        margin-top: 10px;
    }

    .review-section-head {
        padding: 10px 12px;
        border-bottom: 1px solid #eeeeee;
        background: #f8fafc;
    }

    .review-section-title {
        margin: 0;
        color: #111827;
        font-size: 14px;
        font-weight: 800;
    }

    .review-section-body {
        padding: 12px;
    }

    .review-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .review-field {
        padding: 9px 10px;
        border: 1px solid #eeeeee;
        border-radius: 8px;
        background: #ffffff;
        min-width: 0;
    }

    .review-field.full {
        grid-column: 1 / -1;
    }

    .review-label {
        display: block;
        margin-bottom: 4px;
        color: #6b7280;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .review-locked,
    .review-value {
        color: #111827;
        font-size: 13px;
        line-height: 1.45;
        word-break: break-word;
    }

    .review-input,
    .review-textarea,
    .review-select {
        width: 100%;
        min-height: 34px;
        border: 1px solid transparent;
        border-radius: 6px;
        background: #ffffff;
        color: #111827;
        font-size: 13px;
        line-height: 1.45;
        padding: 0;
        outline: none;
    }

    .review-textarea {
        min-height: 58px;
        padding-top: 2px;
        resize: vertical;
    }

    .review-input[readonly],
    .review-textarea[readonly],
    .review-select:disabled {
        background: #ffffff;
        border-color: transparent;
        opacity: 1;
        color: #111827;
        cursor: default;
        appearance: none;
    }

    .review-card.is-editing .review-input:not([readonly]),
    .review-card.is-editing .review-textarea:not([readonly]),
    .review-card.is-editing .review-select:not(:disabled) {
        border-color: #d1d5db;
        background: #fafafa;
        padding: 0 8px;
    }

    .review-card.is-editing .review-textarea:not([readonly]) {
        padding: 8px;
    }

    .review-help {
        margin-top: 4px;
        color: #6b7280;
        font-size: 11px;
        line-height: 1.4;
    }

    .review-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 12px;
        flex-wrap: wrap;
    }

    .btn-primary,
    .btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 140px;
        min-height: 38px;
        padding: 0 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
        border: 1px solid transparent;
    }

    .btn-primary {
        background: linear-gradient(135deg, #0f766e, #2563eb);
        border-color: #0f766e;
        color: #ffffff;
    }

    .btn-primary:hover {
        filter: brightness(0.95);
    }

    .btn-secondary {
        background: #ffffff;
        border-color: #d1d5db;
        color: #374151;
    }

    .review-service-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.review-service-chip {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 5px 10px;
    border-radius: 999px;
    background: #f4f4f4;
    
    color: #2d2d2d;
    font-size: 12px;
    font-weight: 700;
}

    .btn-secondary:hover {
        background: #fafafa;
    }

    @media (max-width: 640px) {
        .review-title {
            font-size: 24px;
        }

        .review-grid {
            grid-template-columns: 1fr;
        }

        .review-card {
            padding: 12px;
        }

        .review-actions {
            flex-direction: column;
        }

        .btn-primary,
        .btn-secondary {
            width: 100%;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation: none !important;
            transition: none !important;
            scroll-behavior: auto !important;
        }
    }
</style>

<div class="review-bg"></div>

<div class="review-page">
    <div class="review-container">
        <div class="review-header">
           

            <h1 class="review-title">Review your booking request</h1>

           <div class="stepper">
    <div class="step done">
        <div class="step-node">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <div class="step-label">Schedule</div>
    </div>

    <div class="step done">
        <div class="step-node">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <div class="step-label">Your Info</div>
    </div>

    <div class="step active">
        <div class="step-node">3</div>
        <div class="step-label">Review</div>
    </div>

    <div class="step">
        <div class="step-node">4</div>
        <div class="step-label">Confirmed</div>
    </div>
</div>
        </div>

        <form method="POST" action="/DentalClinic/public/book/store" id="reviewStoreForm">
            <?= Csrf::inputField(); ?>

            <div class="review-card" id="reviewCard">
                <div class="review-edit-note">
                    Editing mode is active. Review the updated details before submitting.
                </div>

                <div class="review-section">
                    <div class="review-section-head">
                        <h2 class="review-section-title">Appointment Details</h2>
                    </div>

                    <div class="review-section-body">
                        <div class="review-grid">
                         <div class="review-field full">
    <span class="review-label">Selected Service(s)</span>

    <?php if (!empty($serviceNames)): ?>
        <div class="review-service-list">
            <?php foreach ($serviceNames as $name): ?>
                <span class="review-service-chip">
                    <?= $e($name) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="review-locked">
            <?= $e($displayValue($finalServiceDisplay)) ?>
        </div>
    <?php endif; ?>
</div>

                            <div class="review-field">
                                <label class="review-label" for="preferred_date">Preferred Date</label>
                                <input
                                    class="review-input"
                                    type="date"
                                    id="preferred_date"
                                    name="preferred_date"
                                    value="<?= $e($booking['preferred_date'] ?? '') ?>"
                                    readonly
                                    required
                                    data-review-editable
                                >
                            </div>

                            <div class="review-field">
                                <label class="review-label" for="preferred_start_time">Preferred Time</label>
                                <input
                                    class="review-input"
                                    type="time"
                                    id="preferred_start_time"
                                    name="preferred_start_time"
                                    value="<?= $e($preferredTimeValue) ?>"
                                    readonly
                                    required
                                    data-review-editable
                                >
                            </div>

                            <div class="review-field full">
                                <label class="review-label" for="notes">Notes / Concern</label>
                                <textarea
                                    class="review-textarea"
                                    id="notes"
                                    name="notes"
                                    readonly
                                    data-review-editable
                                ><?= $e($booking['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="review-section">
                    <div class="review-section-head">
                        <h2 class="review-section-title">Patient Information</h2>
                    </div>

                    <div class="review-section-body">
                        <div class="review-grid">
                            <div class="review-field">
                                <label class="review-label" for="first_name">First Name</label>
                                <input
                                    class="review-input"
                                    type="text"
                                    id="first_name"
                                    name="first_name"
                                    value="<?= $e($booking['first_name'] ?? '') ?>"
                                    readonly
                                    required
                                    data-review-editable
                                >
                            </div>

                            <div class="review-field">
                                <label class="review-label" for="last_name">Last Name</label>
                                <input
                                    class="review-input"
                                    type="text"
                                    id="last_name"
                                    name="last_name"
                                    value="<?= $e($booking['last_name'] ?? '') ?>"
                                    readonly
                                    required
                                    data-review-editable
                                >
                            </div>

                            <div class="review-field">
                                <label class="review-label" for="birth_date">Birthdate</label>
                                <input
                                    class="review-input"
                                    type="date"
                                    id="birth_date"
                                    name="birth_date"
                                    value="<?= $e($booking['birth_date'] ?? '') ?>"
                                    readonly
                                    required
                                    data-review-editable
                                >
                            </div>

                            <div class="review-field">
                                <label class="review-label" for="sex">Sex</label>
                                <select
                                    class="review-select"
                                    id="sex"
                                    name="sex"
                                    disabled
                                    required
                                    data-review-editable-select
                                >
                                    <option value="">Select sex</option>
                                    <option value="male" <?= (($booking['sex'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= (($booking['sex'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
                                </select>
                                <input type="hidden" name="sex" value="<?= $e($booking['sex'] ?? '') ?>" data-mirror-for="sex">
                            </div>

                            <div class="review-field">
                                <label class="review-label" for="civil_status">Civil Status</label>
                                <select
                                    class="review-select"
                                    id="civil_status"
                                    name="civil_status"
                                    disabled
                                    required
                                    data-review-editable-select
                                >
                                    <option value="">Select civil status</option>
                                    <option value="single" <?= (($booking['civil_status'] ?? '') === 'single') ? 'selected' : '' ?>>Single</option>
                                    <option value="married" <?= (($booking['civil_status'] ?? '') === 'married') ? 'selected' : '' ?>>Married</option>
                                    <option value="widowed" <?= (($booking['civil_status'] ?? '') === 'widowed') ? 'selected' : '' ?>>Widowed</option>
                                    <option value="separated" <?= (($booking['civil_status'] ?? '') === 'separated') ? 'selected' : '' ?>>Separated</option>
                                </select>
                                <input type="hidden" name="civil_status" value="<?= $e($booking['civil_status'] ?? '') ?>" data-mirror-for="civil_status">
                            </div>

                            <div class="review-field">
                                <label class="review-label" for="occupation">Occupation</label>
                                <input
                                    class="review-input"
                                    type="text"
                                    id="occupation"
                                    name="occupation"
                                    value="<?= $e($booking['occupation'] ?? '') ?>"
                                    readonly
                                    data-review-editable
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <div class="review-section">
                    <div class="review-section-head">
                        <h2 class="review-section-title">Contact Information</h2>
                    </div>

                    <div class="review-section-body">
                        <div class="review-grid">
                            <div class="review-field">
                                <label class="review-label" for="contact_number">Contact Number</label>
                                <input
                                    class="review-input"
                                    type="text"
                                    id="contact_number"
                                    name="contact_number"
                                    value="<?= $e($booking['contact_number'] ?? '') ?>"
                                    readonly
                                    required
                                    data-review-editable
                                >
                            </div>

                            <div class="review-field">
                                <label class="review-label" for="email">Email</label>
                                <input
                                    class="review-input"
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="<?= $e($booking['email'] ?? '') ?>"
                                    readonly
                                    data-review-editable
                                >
                            </div>

                            <div class="review-field full">
                                <label class="review-label" for="address_line">Address / Street</label>
                                <input
                                    class="review-input"
                                    type="text"
                                    id="address_line"
                                    name="address_line"
                                    value="<?= $e($booking['address_line'] ?? '') ?>"
                                    readonly
                                    data-review-editable
                                >
                                <div class="review-help">
                                    Current full address: <?= $e($displayValue($address)) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="service_id" value="<?= $e($booking['service_id'] ?? '') ?>">
                <input type="hidden" name="region_id" value="<?= $e($booking['region_id'] ?? '') ?>">
                <input type="hidden" name="province_id" value="<?= $e($booking['province_id'] ?? '') ?>">
                <input type="hidden" name="city_id" value="<?= $e($booking['city_id'] ?? '') ?>">
                <input type="hidden" name="barangay_id" value="<?= $e($booking['barangay_id'] ?? '') ?>">
                <input type="hidden" name="preferred_dentist_id" value="<?= $e($booking['preferred_dentist_id'] ?? '') ?>">

                

                <input type="hidden" name="privacy_consent" value="<?= (($booking['privacy_consent'] ?? '0') === '1') ? '1' : '0' ?>">
<input type="hidden" name="wants_patient_account" value="<?= (($booking['wants_patient_account'] ?? '0') === '1') ? '1' : '0' ?>">
                <?php foreach ($selectedServiceIds as $serviceId): ?>
                    <input type="hidden" name="service_ids[]" value="<?= (int) $serviceId ?>">
                <?php endforeach; ?>

                <?php foreach ($booking as $key => $value): ?>
                    <?php if (in_array((string) $key, $handledKeys, true)): ?>
                        <?php continue; ?>
                    <?php endif; ?>

                    <?php if (is_array($value)): ?>
                        <?php foreach ($value as $item): ?>
                            <input
                                type="hidden"
                                name="<?= $e($key) ?>[]"
                                value="<?= $e($item) ?>"
                            >
                        <?php endforeach; ?>
                    <?php else: ?>
                        <input
                            type="hidden"
                            name="<?= $e($key) ?>"
                            value="<?= $e($value) ?>"
                        >
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="review-actions">
                    <button type="button" class="btn-secondary" id="editReviewBtn">Edit Details</button>
                    <button type="submit" class="btn-primary">Submit Request</button>
                    <div style="margin-top:12px;padding:12px;border:1px solid #d7dde8;border-radius:8px;background:#f8fafc;color:#475569;font-size:13px;line-height:1.6;">
    You confirmed that you have read and understood the
    <a href="/DentalClinic/public/privacy-notice" target="_blank" rel="noopener" style="font-weight:800;color:#0d9e8c;">Privacy Notice</a>
    and consented to data processing for appointment scheduling and dental clinic services.
</div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('reviewStoreForm');
    const reviewCard = document.getElementById('reviewCard');
    const editBtn = document.getElementById('editReviewBtn');

    const editableFields = document.querySelectorAll('[data-review-editable]');
    const editableSelects = document.querySelectorAll('[data-review-editable-select]');
    const mirrorFields = document.querySelectorAll('[data-mirror-for]');

    let isEditing = false;

    function syncMirrorForSelect(select) {
        mirrorFields.forEach(function (mirror) {
            if (mirror.dataset.mirrorFor === select.name) {
                mirror.value = select.value;
            }
        });
    }

    function setEditMode(enabled) {
        isEditing = enabled;

        reviewCard.classList.toggle('is-editing', isEditing);

        editableFields.forEach(function (field) {
            field.readOnly = !isEditing;
        });

        editableSelects.forEach(function (select) {
            select.disabled = !isEditing;
            syncMirrorForSelect(select);
        });

        mirrorFields.forEach(function (mirror) {
            mirror.disabled = isEditing;
        });

        editBtn.textContent = isEditing ? 'Done Editing' : 'Edit Details';

        if (isEditing && editableFields.length > 0) {
            editableFields[0].focus();
        }
    }

    editableSelects.forEach(function (select) {
        select.addEventListener('change', function () {
            syncMirrorForSelect(select);
        });
    });

    editBtn.addEventListener('click', function () {
        setEditMode(!isEditing);
    });

    form.addEventListener('submit', function () {
        if (isEditing) {
            mirrorFields.forEach(function (mirror) {
                mirror.disabled = true;
            });

            editableSelects.forEach(function (select) {
                select.disabled = false;
            });

            return;
        }

        editableSelects.forEach(function (select) {
            syncMirrorForSelect(select);
            select.disabled = true;
        });

        mirrorFields.forEach(function (mirror) {
            mirror.disabled = false;
        });
    });

    setEditMode(false);
})();
</script>

<?php
$content = ob_get_clean();
$title = 'Review Booking';

require __DIR__ . '/../../layouts/main.php';