<?php

use App\Core\Csrf;

$old = $old ?? [];
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

$baseUrl = '/DentalClinic/public';

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('oldValue')) {
    function oldValue(array $old, string $key, string $default = ''): string
    {
        return e((string) ($old[$key] ?? $default));
    }
}

if (!function_exists('oldSelected')) {
    function oldSelected(array $old, string $key, string $value): string
    {
        return (string) ($old[$key] ?? '') === $value ? 'selected' : '';
    }
}

if (!function_exists('oldChecked')) {
    function oldChecked(array $old, string $key): string
    {
        return isset($old[$key]) && (string) $old[$key] !== '' ? 'checked' : '';
    }
}

ob_start();
?>

<style>
.patient-create-page,
.patient-create-page * {
    box-sizing: border-box;
}

.patient-create-page {
    min-height: calc(100dvh - 74px);
    background: #f5f6f8;
    color: #111827;
    padding: 18px 16px 40px;
    font-family: var(--font-ui, Arial, sans-serif);
}

.patient-create-shell {
    max-width: 900px;
    margin: 0 auto;
}

.patient-create-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 14px;
}

.patient-create-title {
    margin: 0;
    font-size: 22px;
    font-weight: 900;
    color: #111827;
    letter-spacing: -0.03em;
}

.patient-create-subtitle {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.5;
}

.back-link {
    min-height: 36px;
    padding: 0 13px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #374151;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.back-link:hover {
    background: #f9fafb;
    color: #111827;
}

.flash-box {
    margin-bottom: 12px;
    padding: 11px 13px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    font-size: 13px;
    font-weight: 700;
}

.flash-box.success {
    color: #166534;
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.flash-box.error {
    color: #991b1b;
    background: #fef2f2;
    border-color: #fecaca;
}

.patient-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    box-shadow: 0 8px 26px rgba(15, 23, 42, 0.04);
}

.patient-card-header {
    padding: 16px 18px;
    border-bottom: 1px solid #e5e7eb;
}

.patient-card-title {
    margin: 0;
    font-size: 16px;
    font-weight: 900;
    color: #111827;
}

.patient-card-body {
    padding: 18px;
}

.form-section {
    margin-bottom: 22px;
}

.form-section:last-child {
    margin-bottom: 0;
}

.form-section-title {
    margin: 0 0 12px;
    font-size: 14px;
    font-weight: 900;
    color: #111827;
}

.form-section-subtitle {
    margin: -6px 0 14px;
    color: #6b7280;
    font-size: 12px;
    line-height: 1.5;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.form-field {
    min-width: 0;
}

.form-field.full {
    grid-column: 1 / -1;
}

.form-label {
    display: block;
    margin-bottom: 6px;
    color: #374151;
    font-size: 12px;
    font-weight: 800;
}

.required {
    color: #b91c1c;
}

.form-control {
    width: 100%;
    min-height: 40px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    padding: 8px 10px;
    font-size: 13px;
    font-family: var(--font-ui, Arial, sans-serif);
    outline: none;
}

.form-control:focus {
    border-color: #9ca3af;
    box-shadow: 0 0 0 3px rgba(156, 163, 175, 0.14);
}

.form-control::placeholder {
    color: #9ca3af;
}

textarea.form-control {
    min-height: 82px;
    resize: vertical;
    line-height: 1.5;
}

.form-help {
    margin-top: 5px;
    color: #6b7280;
    font-size: 11px;
    line-height: 1.4;
}

.form-divider {
    height: 1px;
    background: #e5e7eb;
    margin: 22px 0;
}

.check-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.check-item {
    min-height: 38px;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #e5e7eb;
    background: #fafafa;
    padding: 9px 10px;
    color: #374151;
    font-size: 12px;
    font-weight: 700;
}

.check-item input {
    width: 15px;
    height: 15px;
    accent-color: #111827;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 24px;
    padding-top: 18px;
    border-top: 1px solid #e5e7eb;
}

.btn {
    min-height: 40px;
    padding: 0 16px;
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 900;
    font-family: var(--font-ui, Arial, sans-serif);
    text-decoration: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-light {
    background: #ffffff;
    border-color: #d1d5db;
    color: #374151;
}

.btn-light:hover {
    background: #f9fafb;
}

.btn-primary {
    background: #111827;
    border-color: #111827;
    color: #ffffff;
}

.btn-primary:hover {
    background: #000000;
    border-color: #000000;
}

@media (max-width: 760px) {
    .patient-create-top {
        display: grid;
    }

    .form-grid,
    .check-grid {
        grid-template-columns: 1fr;
    }

    .form-field.full {
        grid-column: auto;
    }

    .form-actions {
        display: grid;
        grid-template-columns: 1fr;
    }

    .btn,
    .back-link {
        width: 100%;
    }
}
</style>

<div class="patient-create-page">
    <div class="patient-create-shell">

        <div class="patient-create-top">
            <div>
                <h1 class="patient-create-title">Add Patient</h1>
                <p class="patient-create-subtitle">
                    Create a patient profile that can be used for appointments, clinical records, and billing.
                </p>
            </div>

            <a href="<?= e($baseUrl . '/staff/patients') ?>" class="back-link">
                Back
            </a>
        </div>

        <?php if ($flash_success): ?>
            <div class="flash-box success"><?= e((string) $flash_success) ?></div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="flash-box error"><?= e((string) $flash_error) ?></div>
        <?php endif; ?>

        <section class="patient-card">
            <div class="patient-card-header">
                <h2 class="patient-card-title">Patient Information</h2>
            </div>

            <div class="patient-card-body">
                <form method="POST" action="<?= e($baseUrl . '/staff/patients/store') ?>" autocomplete="off">
                    <?= Csrf::inputField(); ?>

                    <section class="form-section">
                        <h3 class="form-section-title">Basic Details</h3>

                        <div class="form-grid">
                            <div class="form-field">
                                <label for="first_name" class="form-label">First Name <span class="required">*</span></label>
                                <input type="text" name="first_name" id="first_name" class="form-control" value="<?= oldValue($old, 'first_name') ?>" placeholder="First Name" required maxlength="100">
                            </div>

                            <div class="form-field">
                                <label for="last_name" class="form-label">Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name" id="last_name" class="form-control" value="<?= oldValue($old, 'last_name') ?>" placeholder="Last Name" required maxlength="100">
                            </div>

                            <div class="form-field">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" name="middle_name" id="middle_name" class="form-control" value="<?= oldValue($old, 'middle_name') ?>" placeholder="Middle Name" maxlength="100">
                            </div>

                            <div class="form-field">
                                <label for="sex" class="form-label">Sex</label>
                                <select name="sex" id="sex" class="form-control">
                                    <option value="">Select sex</option>
                                    <option value="Male" <?= oldSelected($old, 'sex', 'Male') ?>>Male</option>
                                    <option value="Female" <?= oldSelected($old, 'sex', 'Female') ?>>Female</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="birth_date" class="form-label">Birth Date</label>
                                <input type="date" name="birth_date" id="birth_date" class="form-control" value="<?= oldValue($old, 'birth_date') ?>" max="<?= e(date('Y-m-d')) ?>">
                            </div>

                            <div class="form-field">
                                <label for="civil_status" class="form-label">Civil Status</label>
                                <select name="civil_status" id="civil_status" class="form-control">
                                    <option value="">Select civil status</option>
                                    <option value="Single" <?= oldSelected($old, 'civil_status', 'Single') ?>>Single</option>
                                    <option value="Married" <?= oldSelected($old, 'civil_status', 'Married') ?>>Married</option>
                                    <option value="Widowed" <?= oldSelected($old, 'civil_status', 'Widowed') ?>>Widowed</option>
                                    <option value="Separated" <?= oldSelected($old, 'civil_status', 'Separated') ?>>Separated</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="occupation" class="form-label">Occupation</label>
                                <input type="text" name="occupation" id="occupation" class="form-control" value="<?= oldValue($old, 'occupation') ?>" placeholder="Occupation" maxlength="150">
                            </div>

                            <div class="form-field">
                                <label for="contact_number" class="form-label">Contact Number <span class="required">*</span></label>
                                <input type="text" name="contact_number" id="contact_number" class="form-control" value="<?= oldValue($old, 'contact_number') ?>" placeholder="09XXXXXXXXX or +639XXXXXXXXX" required maxlength="20">
                                <div class="form-help">Use a valid Philippine mobile number format.</div>
                            </div>

                            <div class="form-field">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control" value="<?= oldValue($old, 'email') ?>" placeholder="Email" maxlength="150">
                            </div>

                            <div class="form-field full">
                                <label for="address" class="form-label">Address</label>
                                <textarea name="address" id="address" class="form-control" placeholder="Complete address"><?= oldValue($old, 'address') ?></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="form-section">
                        <h3 class="form-section-title">Emergency Contact</h3>

                        <div class="form-grid">
                            <div class="form-field">
                                <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                                <input type="text" name="emergency_contact_name" id="emergency_contact_name" class="form-control" value="<?= oldValue($old, 'emergency_contact_name') ?>" placeholder="Emergency Contact Name" maxlength="150">
                            </div>

                            <div class="form-field">
                                <label for="emergency_contact_number" class="form-label">Emergency Contact Number</label>
                                <input type="text" name="emergency_contact_number" id="emergency_contact_number" class="form-control" value="<?= oldValue($old, 'emergency_contact_number') ?>" placeholder="Emergency Contact Number" maxlength="20">
                            </div>
                        </div>
                    </section>

                    <div class="form-divider"></div>

                    <section class="form-section">
                        <h3 class="form-section-title">Medical History</h3>
                        <p class="form-section-subtitle">Optional medical information to help the clinic review patient risk before treatment.</p>

                        <div class="form-grid">
                            <div class="form-field">
                                <label for="under_physician_care" class="form-label">Under physician care?</label>
                                <select name="under_physician_care" id="under_physician_care" class="form-control">
                                    <option value="">Select answer</option>
                                    <option value="yes" <?= oldSelected($old, 'under_physician_care', 'yes') ?>>Yes</option>
                                    <option value="no" <?= oldSelected($old, 'under_physician_care', 'no') ?>>No</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="physician_name" class="form-label">Physician Name</label>
                                <input type="text" name="physician_name" id="physician_name" class="form-control" value="<?= oldValue($old, 'physician_name') ?>" placeholder="Physician name">
                            </div>

                            <div class="form-field">
                                <label for="physician_contact" class="form-label">Physician Contact</label>
                                <input type="text" name="physician_contact" id="physician_contact" class="form-control" value="<?= oldValue($old, 'physician_contact') ?>" placeholder="Physician contact">
                            </div>

                            <div class="form-field">
                                <label for="blood_pressure" class="form-label">Blood Pressure</label>
                                <input type="text" name="blood_pressure" id="blood_pressure" class="form-control" value="<?= oldValue($old, 'blood_pressure') ?>" placeholder="e.g. 120/80">
                            </div>

                            <div class="form-field full">
                                <label for="physician_care_details" class="form-label">Physician Care Details</label>
                                <textarea name="physician_care_details" id="physician_care_details" class="form-control" placeholder="Reason or details"><?= oldValue($old, 'physician_care_details') ?></textarea>
                            </div>

                            <div class="form-field">
                                <label for="is_pregnant" class="form-label">Pregnant?</label>
                                <select name="is_pregnant" id="is_pregnant" class="form-control">
                                    <option value="">Select answer</option>
                                    <option value="yes" <?= oldSelected($old, 'is_pregnant', 'yes') ?>>Yes</option>
                                    <option value="no" <?= oldSelected($old, 'is_pregnant', 'no') ?>>No</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="taking_medicine" class="form-label">Currently taking medicine?</label>
                                <select name="taking_medicine" id="taking_medicine" class="form-control">
                                    <option value="">Select answer</option>
                                    <option value="yes" <?= oldSelected($old, 'taking_medicine', 'yes') ?>>Yes</option>
                                    <option value="no" <?= oldSelected($old, 'taking_medicine', 'no') ?>>No</option>
                                </select>
                            </div>

                            <div class="form-field full">
                                <label for="medicine_details" class="form-label">Medicine Details</label>
                                <textarea name="medicine_details" id="medicine_details" class="form-control" placeholder="Medicine name or details"><?= oldValue($old, 'medicine_details') ?></textarea>
                            </div>

                            <div class="form-field full">
                                <label class="form-label">Medical Conditions</label>

                                <div class="check-grid">
                                    <label class="check-item"><input type="checkbox" name="condition_high_blood_pressure" value="1" <?= oldChecked($old, 'condition_high_blood_pressure') ?>> High blood pressure</label>
                                    <label class="check-item"><input type="checkbox" name="condition_low_blood_pressure" value="1" <?= oldChecked($old, 'condition_low_blood_pressure') ?>> Low blood pressure</label>
                                    <label class="check-item"><input type="checkbox" name="condition_asthma" value="1" <?= oldChecked($old, 'condition_asthma') ?>> Asthma</label>
                                    <label class="check-item"><input type="checkbox" name="condition_heart_disease" value="1" <?= oldChecked($old, 'condition_heart_disease') ?>> Heart disease</label>
                                    <label class="check-item"><input type="checkbox" name="condition_diabetes" value="1" <?= oldChecked($old, 'condition_diabetes') ?>> Diabetes</label>
                                    <label class="check-item"><input type="checkbox" name="condition_tuberculosis" value="1" <?= oldChecked($old, 'condition_tuberculosis') ?>> Tuberculosis</label>
                                    <label class="check-item"><input type="checkbox" name="condition_thyroid_problem" value="1" <?= oldChecked($old, 'condition_thyroid_problem') ?>> Thyroid problem</label>
                                    <label class="check-item"><input type="checkbox" name="condition_bleeding_problems" value="1" <?= oldChecked($old, 'condition_bleeding_problems') ?>> Bleeding problems</label>
                                    <label class="check-item"><input type="checkbox" name="condition_hiv_aids" value="1" <?= oldChecked($old, 'condition_hiv_aids') ?>> HIV / AIDS</label>
                                    <label class="check-item"><input type="checkbox" name="condition_hepatitis" value="1" <?= oldChecked($old, 'condition_hepatitis') ?>> Hepatitis</label>
                                    <label class="check-item"><input type="checkbox" name="condition_others" value="1" <?= oldChecked($old, 'condition_others') ?>> Other condition</label>
                                </div>
                            </div>

                            <div class="form-field full">
                                <label for="condition_others_details" class="form-label">Other Medical Condition Details</label>
                                <input type="text" name="condition_others_details" id="condition_others_details" class="form-control" value="<?= oldValue($old, 'condition_others_details') ?>" placeholder="Other medical condition">
                            </div>

                            <div class="form-field full">
                                <label class="form-label">Allergies</label>

                                <div class="check-grid">
                                    <label class="check-item"><input type="checkbox" name="allergy_local_anesthesia" value="yes" <?= oldChecked($old, 'allergy_local_anesthesia') ?>> Local anesthesia</label>
                                    <label class="check-item"><input type="checkbox" name="allergy_antibiotics" value="yes" <?= oldChecked($old, 'allergy_antibiotics') ?>> Antibiotics</label>
                                    <label class="check-item"><input type="checkbox" name="allergy_pain_killer" value="yes" <?= oldChecked($old, 'allergy_pain_killer') ?>> Pain killer</label>
                                    <label class="check-item"><input type="checkbox" name="allergy_others" value="yes" <?= oldChecked($old, 'allergy_others') ?>> Other allergies</label>
                                </div>
                            </div>

                            <div class="form-field full">
                                <label for="allergy_others_details" class="form-label">Other Allergy Details</label>
                                <input type="text" name="allergy_others_details" id="allergy_others_details" class="form-control" value="<?= oldValue($old, 'allergy_others_details') ?>" placeholder="Other allergies">
                            </div>

                            <div class="form-field">
                                <label for="hospitalized" class="form-label">Previously hospitalized?</label>
                                <select name="hospitalized" id="hospitalized" class="form-control">
                                    <option value="">Select answer</option>
                                    <option value="yes" <?= oldSelected($old, 'hospitalized', 'yes') ?>>Yes</option>
                                    <option value="no" <?= oldSelected($old, 'hospitalized', 'no') ?>>No</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="hospitalization_when" class="form-label">Hospitalized When?</label>
                                <input type="text" name="hospitalization_when" id="hospitalization_when" class="form-control" value="<?= oldValue($old, 'hospitalization_when') ?>" placeholder="When">
                            </div>

                            <div class="form-field full">
                                <label for="hospitalization_why" class="form-label">Hospitalized Why?</label>
                                <textarea name="hospitalization_why" id="hospitalization_why" class="form-control" placeholder="Reason"><?= oldValue($old, 'hospitalization_why') ?></textarea>
                            </div>

                            <div class="form-field full">
                                <label for="medical_notes" class="form-label">Medical Notes</label>
                                <textarea name="medical_notes" id="medical_notes" class="form-control" placeholder="Other medical notes"><?= oldValue($old, 'medical_notes') ?></textarea>
                            </div>
                        </div>
                    </section>

                    <div class="form-divider"></div>

                    <section class="form-section">
                        <h3 class="form-section-title">Dental History</h3>
                        <p class="form-section-subtitle">Optional dental background that will be attached to the patient record.</p>

                        <div class="form-grid">
                            <div class="form-field">
                                <label for="previous_dentist" class="form-label">Previous Dentist</label>
                                <input type="text" name="previous_dentist" id="previous_dentist" class="form-control" value="<?= oldValue($old, 'previous_dentist') ?>" placeholder="Previous dentist">
                            </div>

                            <div class="form-field">
                                <label for="last_dental_visit" class="form-label">Last Dental Visit</label>
                                <input type="date" name="last_dental_visit" id="last_dental_visit" class="form-control" value="<?= oldValue($old, 'last_dental_visit') ?>" max="<?= e(date('Y-m-d')) ?>">
                            </div>

                            <div class="form-field full">
                                <label for="last_dental_visit_reason" class="form-label">Reason for Last Dental Visit</label>
                                <input type="text" name="last_dental_visit_reason" id="last_dental_visit_reason" class="form-control" value="<?= oldValue($old, 'last_dental_visit_reason') ?>" placeholder="Reason">
                            </div>

                            <div class="form-field">
                                <label for="worn_denture" class="form-label">Worn denture?</label>
                                <select name="worn_denture" id="worn_denture" class="form-control">
                                    <option value="">Select answer</option>
                                    <option value="yes" <?= oldSelected($old, 'worn_denture', 'yes') ?>>Yes</option>
                                    <option value="no" <?= oldSelected($old, 'worn_denture', 'no') ?>>No</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="gums_bleed" class="form-label">Gums bleed?</label>
                                <select name="gums_bleed" id="gums_bleed" class="form-control">
                                    <option value="">Select answer</option>
                                    <option value="yes" <?= oldSelected($old, 'gums_bleed', 'yes') ?>>Yes</option>
                                    <option value="no" <?= oldSelected($old, 'gums_bleed', 'no') ?>>No</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="bad_breath" class="form-label">Bad breath?</label>
                                <select name="bad_breath" id="bad_breath" class="form-control">
                                    <option value="">Select answer</option>
                                    <option value="yes" <?= oldSelected($old, 'bad_breath', 'yes') ?>>Yes</option>
                                    <option value="no" <?= oldSelected($old, 'bad_breath', 'no') ?>>No</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="loose_teeth" class="form-label">Loose teeth?</label>
                                <select name="loose_teeth" id="loose_teeth" class="form-control">
                                    <option value="">Select answer</option>
                                    <option value="yes" <?= oldSelected($old, 'loose_teeth', 'yes') ?>>Yes</option>
                                    <option value="no" <?= oldSelected($old, 'loose_teeth', 'no') ?>>No</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="sensitive_teeth" class="form-label">Sensitive teeth?</label>
                                <select name="sensitive_teeth" id="sensitive_teeth" class="form-control">
                                    <option value="">Select answer</option>
                                    <option value="yes" <?= oldSelected($old, 'sensitive_teeth', 'yes') ?>>Yes</option>
                                    <option value="no" <?= oldSelected($old, 'sensitive_teeth', 'no') ?>>No</option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="clicking_jaw" class="form-label">Clicking jaw?</label>
                                <select name="clicking_jaw" id="clicking_jaw" class="form-control">
                                    <option value="">Select answer</option>
                                    <option value="yes" <?= oldSelected($old, 'clicking_jaw', 'yes') ?>>Yes</option>
                                    <option value="no" <?= oldSelected($old, 'clicking_jaw', 'no') ?>>No</option>
                                </select>
                            </div>

                            <div class="form-field full">
                                <label for="dental_notes" class="form-label">Dental Notes</label>
                                <textarea name="dental_notes" id="dental_notes" class="form-control" placeholder="Other dental history notes"><?= oldValue($old, 'dental_notes') ?></textarea>
                            </div>
                        </div>
                    </section>

                    <div class="form-divider"></div>

                    <section class="form-section">
                        <h3 class="form-section-title">Notes</h3>

                        <div class="form-grid">
                            <div class="form-field full">
                                <label for="notes" class="form-label">Patient Notes</label>
                                <textarea name="notes" id="notes" class="form-control" placeholder="Patient notes"><?= oldValue($old, 'notes') ?></textarea>
                            </div>
                        </div>
                    </section>

                    <div class="form-actions">
                        <a href="<?= e($baseUrl . '/staff/patients') ?>" class="btn btn-light">
                            Cancel
                        </a>

                        <button type="submit" class="btn btn-primary">
                            Save Patient
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<?php
$staffContent = ob_get_clean();
$content = $staffContent;

$pageTitle = 'Add Patient';
$title = 'Add Patient';

require __DIR__ . '/../layouts/app.php';