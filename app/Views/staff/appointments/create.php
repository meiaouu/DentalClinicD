<?php

use App\Core\Csrf;

$patients = $patients ?? [];
$services = $services ?? [];
$dentists = $dentists ?? [];
$old = $old ?? [];
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
        return isset($old[$key]) ? 'checked' : '';
    }
}

$patientMode = (string) ($old['patient_mode'] ?? 'existing');

ob_start();
?>


<style>


* {
    box-sizing: border-box;
}

.simple-appointment-page,
.simple-appointment-page * {
    box-sizing: border-box;
}

.simple-appointment-page {
    min-height: calc(100dvh - 74px);
    background: #ffffff;
    color: #222222;
    font-family: var(--font-ui);
    padding: 28px 16px 40px;
}

.simple-appointment-page input,
.simple-appointment-page select,
.simple-appointment-page textarea,
.simple-appointment-page button {
    font-family: var(--font-ui);
}

.simple-appointment-shell {
    max-width: 880px;
    margin: 0 auto;
}

.simple-title {
    margin: 0 0 18px;
    font-size: 18px;
    font-weight: 700;
    color: #222222;
    letter-spacing: -0.02em;
}

.simple-section {
    margin-bottom: 22px;
}

.simple-subsection {
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #eeeeee;
}

.simple-section-title {
    margin: 0 0 12px;
    font-size: 14px;
    font-weight: 700;
    color: #222222;
    letter-spacing: -0.01em;
}

.simple-subtitle {
    margin: -6px 0 14px;
    color: #777777;
    font-size: 12px;
    line-height: 1.5;
}

.simple-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.simple-field {
    min-width: 0;
}

.simple-field.full {
    grid-column: 1 / -1;
}

.simple-label {
    display: block;
    margin-bottom: 5px;
    font-size: 11px;
    font-weight: 600;
    color: #555555;
}

.simple-label span {
    color: #999999;
    font-weight: 400;
}

.required {
    color: #b91c1c;
}

.simple-control {
    width: 100%;
    height: 42px;
    border: 1px solid #dddddd;
    border-radius: 2px;
    background: #ffffff;
    color: #222222;
    padding: 0 10px;
    font-size: 13px;
    font-family: var(--font-ui);
    outline: none;
}

.simple-control:focus {
    border-color: #9ca3af;
}

.simple-control::placeholder {
    color: #999999;
}

textarea.simple-control {
    height: auto;
    min-height: 76px;
    padding-top: 10px;
    resize: vertical;
    line-height: 1.5;
}

select.simple-control {
    cursor: pointer;
}

.simple-check-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.simple-check {
    min-height: 38px;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #eeeeee;
    background: #fafafa;
    padding: 9px 10px;
    font-size: 12px;
    color: #333333;
}

.simple-check input {
    width: 15px;
    height: 15px;
    accent-color: #111827;
}

.simple-help {
    margin-top: 5px;
    color: #888888;
    font-size: 11px;
    line-height: 1.4;
}

.simple-divider {
    height: 1px;
    background: #eeeeee;
    margin: 22px 0;
}

.simple-error {
    margin-bottom: 16px;
    padding: 10px 12px;
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #b91c1c;
    font-size: 13px;
    font-weight: 600;
}

.hidden {
    display: none !important;
}

.simple-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 24px;
}

.simple-back,
.simple-submit {
    min-height: 40px;
    padding: 0 18px;
    border-radius: 2px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.simple-back {
    border: 1px solid #dddddd;
    background: #ffffff;
    color: #333333;
}

.simple-submit {
    border: 1px solid #111827;
    background: #111827;
    color: #ffffff;
    cursor: pointer;
}

.simple-submit:hover {
    background: #000000;
}

@media (max-width: 700px) {
    .simple-grid,
    .simple-check-grid {
        grid-template-columns: 1fr;
    }

    .simple-field.full {
        grid-column: 1;
    }

    .simple-actions {
        display: grid;
        grid-template-columns: 1fr;
    }

    .simple-back,
    .simple-submit {
        width: 100%;
    }
}
</style>

<div class="simple-appointment-page">
    <div class="simple-appointment-shell">
        <h1 class="simple-title">Create Appointment</h1>

        <?php if ($flash_error): ?>
            <div class="simple-error"><?= e((string) $flash_error) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= e($baseUrl . '/staff/appointments/store') ?>" id="walkinAppointmentForm">
            <?= Csrf::inputField(); ?>

            <section class="simple-section">
                <h2 class="simple-section-title">Patient Type</h2>

                <div class="simple-grid">
                    <div class="simple-field full">
                        <label class="simple-label" for="patient_mode">
                            Select Patient Type <span class="required">*</span>
                        </label>
                        <select class="simple-control" name="patient_mode" id="patient_mode" required>
                            <option value="existing" <?= $patientMode !== 'walk_in' ? 'selected' : '' ?>>Existing Patient</option>
                            <option value="walk_in" <?= $patientMode === 'walk_in' ? 'selected' : '' ?>>Walk-in / New Patient</option>
                        </select>
                    </div>
                </div>
            </section>

            <div class="simple-divider"></div>

            <section class="simple-section" id="existingPatientSection">
                <h2 class="simple-section-title">Existing Patient</h2>

                <div class="simple-grid">
                    <div class="simple-field full">
                        <label class="simple-label" for="patient_id">
                            Patient <span class="required">*</span>
                        </label>
                        <select class="simple-control" name="patient_id" id="patient_id">
                            <option value="">Select patient</option>

                            <?php foreach ($patients as $patient): ?>
                                <?php
                                    $patientId = (int) ($patient['patient_id'] ?? 0);
                                    $name = trim(
                                        (string) (($patient['last_name'] ?? '') . ', ' . ($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? ''))
                                    );
                                    $code = (string) ($patient['patient_code'] ?? '');
                                    $contact = (string) ($patient['contact_number'] ?? '');
                                    $label = trim($name . ($code !== '' ? ' • ' . $code : '') . ($contact !== '' ? ' • ' . $contact : ''));
                                ?>
                                <option value="<?= $patientId ?>" <?= oldSelected($old, 'patient_id', (string) $patientId) ?>>
                                    <?= e($label !== '' ? $label : ('Patient #' . $patientId)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <section class="simple-section" id="walkInPatientSection">
                <h2 class="simple-section-title">Patient Information</h2>

                <div class="simple-grid">
                    <div class="simple-field">
                        <label class="simple-label" for="walkin_first_name">
                            First Name <span class="required">*</span>
                        </label>
                        <input class="simple-control walkin-required" type="text" name="walkin_first_name" id="walkin_first_name" value="<?= oldValue($old, 'walkin_first_name') ?>" placeholder="First Name" autocomplete="given-name">
                    </div>

                    <div class="simple-field">
                        <label class="simple-label" for="walkin_last_name">
                            Last Name <span class="required">*</span>
                        </label>
                        <input class="simple-control walkin-required" type="text" name="walkin_last_name" id="walkin_last_name" value="<?= oldValue($old, 'walkin_last_name') ?>" placeholder="Last Name" autocomplete="family-name">
                    </div>

                    <div class="simple-field">
                        <label class="simple-label" for="walkin_middle_name">
                            Middle Name <span>(Optional)</span>
                        </label>
                        <input class="simple-control" type="text" name="walkin_middle_name" id="walkin_middle_name" value="<?= oldValue($old, 'walkin_middle_name') ?>" placeholder="Middle Name" autocomplete="additional-name">
                    </div>

                    <div class="simple-field">
                        <label class="simple-label" for="walkin_contact_number">
                            Phone Number <span class="required">*</span>
                        </label>
                        <input class="simple-control walkin-required" type="tel" name="walkin_contact_number" id="walkin_contact_number" value="<?= oldValue($old, 'walkin_contact_number') ?>" placeholder="09XXXXXXXXX" autocomplete="tel">
                    </div>

                    <div class="simple-field">
                        <label class="simple-label" for="walkin_email">
                            Email <span>(Optional)</span>
                        </label>
                        <input class="simple-control" type="email" name="walkin_email" id="walkin_email" value="<?= oldValue($old, 'walkin_email') ?>" placeholder="Email" autocomplete="email">
                    </div>

                    <div class="simple-field">
                        <label class="simple-label" for="walkin_birth_date">Birth Date</label>
                        <input class="simple-control" type="date" name="walkin_birth_date" id="walkin_birth_date" value="<?= oldValue($old, 'walkin_birth_date') ?>" max="<?= e(date('Y-m-d')) ?>">
                    </div>

                    <div class="simple-field">
                        <label class="simple-label" for="walkin_sex">Sex</label>
                        <select class="simple-control" name="walkin_sex" id="walkin_sex">
                            <option value="">Select sex</option>
                            <option value="Male" <?= oldSelected($old, 'walkin_sex', 'Male') ?>>Male</option>
                            <option value="Female" <?= oldSelected($old, 'walkin_sex', 'Female') ?>>Female</option>
                        </select>
                    </div>

                    <div class="simple-field">
                        <label class="simple-label" for="walkin_civil_status">Civil Status</label>
                        <select class="simple-control" name="walkin_civil_status" id="walkin_civil_status">
                            <option value="">Select civil status</option>
                            <option value="Single" <?= oldSelected($old, 'walkin_civil_status', 'Single') ?>>Single</option>
                            <option value="Married" <?= oldSelected($old, 'walkin_civil_status', 'Married') ?>>Married</option>
                            <option value="Widowed" <?= oldSelected($old, 'walkin_civil_status', 'Widowed') ?>>Widowed</option>
                            <option value="Separated" <?= oldSelected($old, 'walkin_civil_status', 'Separated') ?>>Separated</option>
                        </select>
                    </div>

                    <div class="simple-field full">
                        <label class="simple-label" for="walkin_address">Address</label>
                        <input class="simple-control" type="text" name="walkin_address" id="walkin_address" value="<?= oldValue($old, 'walkin_address') ?>" placeholder="Address">
                    </div>

                    <div class="simple-field full">
                        <label class="simple-label" for="walkin_occupation">
                            Occupation <span>(Optional)</span>
                        </label>
                        <input class="simple-control" type="text" name="walkin_occupation" id="walkin_occupation" value="<?= oldValue($old, 'walkin_occupation') ?>" placeholder="Occupation">
                    </div>
                </div>

                <div class="simple-subsection">
                    <h2 class="simple-section-title">Medical History</h2>
                    <p class="simple-subtitle">These answers will be saved to the new patient's medical history record.</p>

                    <div class="simple-grid">
                        <div class="simple-field">
                            <label class="simple-label" for="medical_under_physician_care">Under physician care?</label>
                            <select class="simple-control" name="medical_under_physician_care" id="medical_under_physician_care">
                                <option value="">Select answer</option>
                                <option value="yes" <?= oldSelected($old, 'medical_under_physician_care', 'yes') ?>>Yes</option>
                                <option value="no" <?= oldSelected($old, 'medical_under_physician_care', 'no') ?>>No</option>
                            </select>
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="medical_physician_name">Physician Name <span>(Optional)</span></label>
                            <input class="simple-control" type="text" name="medical_physician_name" id="medical_physician_name" value="<?= oldValue($old, 'medical_physician_name') ?>" placeholder="Physician name">
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="medical_physician_contact">Physician Contact <span>(Optional)</span></label>
                            <input class="simple-control" type="text" name="medical_physician_contact" id="medical_physician_contact" value="<?= oldValue($old, 'medical_physician_contact') ?>" placeholder="Physician contact">
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="medical_is_pregnant">Pregnant? <span>(If applicable)</span></label>
                            <select class="simple-control" name="medical_is_pregnant" id="medical_is_pregnant">
                                <option value="">Select answer</option>
                                <option value="yes" <?= oldSelected($old, 'medical_is_pregnant', 'yes') ?>>Yes</option>
                                <option value="no" <?= oldSelected($old, 'medical_is_pregnant', 'no') ?>>No</option>
                            </select>
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="medical_taking_medicine">Currently taking medicine?</label>
                            <select class="simple-control" name="medical_taking_medicine" id="medical_taking_medicine">
                                <option value="">Select answer</option>
                                <option value="yes" <?= oldSelected($old, 'medical_taking_medicine', 'yes') ?>>Yes</option>
                                <option value="no" <?= oldSelected($old, 'medical_taking_medicine', 'no') ?>>No</option>
                            </select>
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="medical_medicine_details">Medicine Details</label>
                            <input class="simple-control" type="text" name="medical_medicine_details" id="medical_medicine_details" value="<?= oldValue($old, 'medical_medicine_details') ?>" placeholder="Medicine name/details">
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="medical_blood_pressure">Blood Pressure <span>(Optional)</span></label>
                            <input class="simple-control" type="text" name="medical_blood_pressure" id="medical_blood_pressure" value="<?= oldValue($old, 'medical_blood_pressure') ?>" placeholder="e.g. 120/80">
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="medical_hospitalized">Previously hospitalized?</label>
                            <select class="simple-control" name="medical_hospitalized" id="medical_hospitalized">
                                <option value="">Select answer</option>
                                <option value="yes" <?= oldSelected($old, 'medical_hospitalized', 'yes') ?>>Yes</option>
                                <option value="no" <?= oldSelected($old, 'medical_hospitalized', 'no') ?>>No</option>
                            </select>
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="medical_hospitalization_when">Hospitalized When?</label>
                            <input class="simple-control" type="text" name="medical_hospitalization_when" id="medical_hospitalization_when" value="<?= oldValue($old, 'medical_hospitalization_when') ?>" placeholder="When">
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="medical_hospitalization_why">Hospitalized Why?</label>
                            <input class="simple-control" type="text" name="medical_hospitalization_why" id="medical_hospitalization_why" value="<?= oldValue($old, 'medical_hospitalization_why') ?>" placeholder="Reason">
                        </div>

                        <div class="simple-field full">
                            <label class="simple-label">Medical Conditions</label>

                            <div class="simple-check-grid">
                                <label class="simple-check"><input type="checkbox" name="condition_high_blood_pressure" value="1" <?= oldChecked($old, 'condition_high_blood_pressure') ?>> High blood pressure</label>
                                <label class="simple-check"><input type="checkbox" name="condition_low_blood_pressure" value="1" <?= oldChecked($old, 'condition_low_blood_pressure') ?>> Low blood pressure</label>
                                <label class="simple-check"><input type="checkbox" name="condition_asthma" value="1" <?= oldChecked($old, 'condition_asthma') ?>> Asthma</label>
                                <label class="simple-check"><input type="checkbox" name="condition_heart_disease" value="1" <?= oldChecked($old, 'condition_heart_disease') ?>> Heart disease</label>
                                <label class="simple-check"><input type="checkbox" name="condition_diabetes" value="1" <?= oldChecked($old, 'condition_diabetes') ?>> Diabetes</label>
                                <label class="simple-check"><input type="checkbox" name="condition_tuberculosis" value="1" <?= oldChecked($old, 'condition_tuberculosis') ?>> Tuberculosis</label>
                                <label class="simple-check"><input type="checkbox" name="condition_thyroid_problem" value="1" <?= oldChecked($old, 'condition_thyroid_problem') ?>> Thyroid problem</label>
                                <label class="simple-check"><input type="checkbox" name="condition_bleeding_problems" value="1" <?= oldChecked($old, 'condition_bleeding_problems') ?>> Bleeding problems</label>
                                <label class="simple-check"><input type="checkbox" name="condition_hiv_aids" value="1" <?= oldChecked($old, 'condition_hiv_aids') ?>> HIV / AIDS</label>
                                <label class="simple-check"><input type="checkbox" name="condition_hepatitis" value="1" <?= oldChecked($old, 'condition_hepatitis') ?>> Hepatitis</label>
                            </div>
                        </div>

                        <div class="simple-field full">
                            <label class="simple-label" for="medical_condition_others">Other Medical Condition</label>
                            <input class="simple-control" type="text" name="medical_condition_others" id="medical_condition_others" value="<?= oldValue($old, 'medical_condition_others') ?>" placeholder="Other condition">
                        </div>

                        <div class="simple-field full">
                            <label class="simple-label">Allergies</label>

                            <div class="simple-check-grid">
                                <label class="simple-check"><input type="checkbox" name="allergy_local_anesthesia" value="1" <?= oldChecked($old, 'allergy_local_anesthesia') ?>> Local anesthesia</label>
                                <label class="simple-check"><input type="checkbox" name="allergy_antibiotics" value="1" <?= oldChecked($old, 'allergy_antibiotics') ?>> Antibiotics</label>
                                <label class="simple-check"><input type="checkbox" name="allergy_pain_killer" value="1" <?= oldChecked($old, 'allergy_pain_killer') ?>> Pain killer</label>
                            </div>
                        </div>

                        <div class="simple-field full">
                            <label class="simple-label" for="medical_allergy_others">Other Allergies</label>
                            <input class="simple-control" type="text" name="medical_allergy_others" id="medical_allergy_others" value="<?= oldValue($old, 'medical_allergy_others') ?>" placeholder="Other allergies">
                        </div>

                        <div class="simple-field full">
                            <label class="simple-label" for="medical_notes">Medical Notes</label>
                            <textarea class="simple-control" name="medical_notes" id="medical_notes" rows="3" placeholder="Other medical notes"><?= oldValue($old, 'medical_notes') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="simple-subsection">
                    <h2 class="simple-section-title">Dental History</h2>
                    <p class="simple-subtitle">These answers will be saved to the new patient's dental history record.</p>

                    <div class="simple-grid">
                        <div class="simple-field">
                            <label class="simple-label" for="dental_previous_dentist">Previous Dentist <span>(Optional)</span></label>
                            <input class="simple-control" type="text" name="dental_previous_dentist" id="dental_previous_dentist" value="<?= oldValue($old, 'dental_previous_dentist') ?>" placeholder="Previous dentist">
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="dental_last_dental_visit">Last Dental Visit</label>
                            <input class="simple-control" type="date" name="dental_last_dental_visit" id="dental_last_dental_visit" value="<?= oldValue($old, 'dental_last_dental_visit') ?>" max="<?= e(date('Y-m-d')) ?>">
                        </div>

                        <div class="simple-field full">
                            <label class="simple-label" for="dental_last_dental_visit_reason">Reason for Last Dental Visit</label>
                            <input class="simple-control" type="text" name="dental_last_dental_visit_reason" id="dental_last_dental_visit_reason" value="<?= oldValue($old, 'dental_last_dental_visit_reason') ?>" placeholder="Reason">
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="dental_worn_denture">Worn denture?</label>
                            <select class="simple-control" name="dental_worn_denture" id="dental_worn_denture">
                                <option value="">Select answer</option>
                                <option value="yes" <?= oldSelected($old, 'dental_worn_denture', 'yes') ?>>Yes</option>
                                <option value="no" <?= oldSelected($old, 'dental_worn_denture', 'no') ?>>No</option>
                            </select>
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="dental_gums_bleed">Gums bleed?</label>
                            <select class="simple-control" name="dental_gums_bleed" id="dental_gums_bleed">
                                <option value="">Select answer</option>
                                <option value="yes" <?= oldSelected($old, 'dental_gums_bleed', 'yes') ?>>Yes</option>
                                <option value="no" <?= oldSelected($old, 'dental_gums_bleed', 'no') ?>>No</option>
                            </select>
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="dental_bad_breath">Bad breath?</label>
                            <select class="simple-control" name="dental_bad_breath" id="dental_bad_breath">
                                <option value="">Select answer</option>
                                <option value="yes" <?= oldSelected($old, 'dental_bad_breath', 'yes') ?>>Yes</option>
                                <option value="no" <?= oldSelected($old, 'dental_bad_breath', 'no') ?>>No</option>
                            </select>
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="dental_loose_teeth">Loose teeth?</label>
                            <select class="simple-control" name="dental_loose_teeth" id="dental_loose_teeth">
                                <option value="">Select answer</option>
                                <option value="yes" <?= oldSelected($old, 'dental_loose_teeth', 'yes') ?>>Yes</option>
                                <option value="no" <?= oldSelected($old, 'dental_loose_teeth', 'no') ?>>No</option>
                            </select>
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="dental_sensitive_teeth">Sensitive teeth?</label>
                            <select class="simple-control" name="dental_sensitive_teeth" id="dental_sensitive_teeth">
                                <option value="">Select answer</option>
                                <option value="yes" <?= oldSelected($old, 'dental_sensitive_teeth', 'yes') ?>>Yes</option>
                                <option value="no" <?= oldSelected($old, 'dental_sensitive_teeth', 'no') ?>>No</option>
                            </select>
                        </div>

                        <div class="simple-field">
                            <label class="simple-label" for="dental_clicking_jaw">Clicking jaw?</label>
                            <select class="simple-control" name="dental_clicking_jaw" id="dental_clicking_jaw">
                                <option value="">Select answer</option>
                                <option value="yes" <?= oldSelected($old, 'dental_clicking_jaw', 'yes') ?>>Yes</option>
                                <option value="no" <?= oldSelected($old, 'dental_clicking_jaw', 'no') ?>>No</option>
                            </select>
                        </div>

                        <div class="simple-field full">
                            <label class="simple-label" for="dental_notes">Dental Notes</label>
                            <textarea class="simple-control" name="dental_notes" id="dental_notes" rows="3" placeholder="Other dental history notes"><?= oldValue($old, 'dental_notes') ?></textarea>
                        </div>
                    </div>
                </div>
            </section>

            <div class="simple-divider"></div>

            <section class="simple-section">
                <h2 class="simple-section-title">Appointment Information</h2>

                <div class="simple-grid">
                    <div class="simple-field">
                        <label class="simple-label" for="service_id">
                            Treatment <span class="required">*</span>
                        </label>
                        <select class="simple-control" name="service_id" id="service_id" required>
                            <option value="">Select treatment</option>

                            <?php foreach ($services as $service): ?>
                                <?php $serviceId = (int) ($service['service_id'] ?? 0); ?>
                                <option value="<?= $serviceId ?>" <?= oldSelected($old, 'service_id', (string) $serviceId) ?>>
                                    <?= e((string) ($service['service_name'] ?? 'Unnamed Service')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="simple-field">
                        <label class="simple-label" for="dentist_id">
                            Dentist <span class="required">*</span>
                        </label>
                        <select class="simple-control" name="dentist_id" id="dentist_id" required>
                            <option value="">Select dentist</option>

                            <?php foreach ($dentists as $dentist): ?>
                                <?php
                                    $dentistId = (int) ($dentist['dentist_id'] ?? 0);
                                    $dentistName = trim((string) (($dentist['first_name'] ?? '') . ' ' . ($dentist['last_name'] ?? '')));
                                ?>
                                <option value="<?= $dentistId ?>" <?= oldSelected($old, 'dentist_id', (string) $dentistId) ?>>
                                    <?= e($dentistName !== '' ? ('Dr. ' . $dentistName) : ('Dentist #' . $dentistId)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="simple-field">
                        <label class="simple-label" for="appointment_date">
                            Date <span class="required">*</span>
                        </label>
                        <input class="simple-control" type="date" name="appointment_date" id="appointment_date" value="<?= oldValue($old, 'appointment_date') ?>" min="<?= e(date('Y-m-d')) ?>" required>
                    </div>

                    <div class="simple-field">
                        <label class="simple-label" for="start_time">
                            Time <span class="required">*</span>
                        </label>
                        <select class="simple-control" name="start_time" id="start_time" required>
                            <option value="">Select available time</option>

                            <?php if (!empty($old['start_time'])): ?>
                                <option value="<?= oldValue($old, 'start_time') ?>" selected>
                                    <?= oldValue($old, 'start_time') ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="simple-field full">
                        <label class="simple-label" for="remarks">
                            Appointment Notes <span>(Optional)</span>
                        </label>
                        <textarea class="simple-control" name="remarks" id="remarks" rows="3" placeholder="Appointment notes"><?= oldValue($old, 'remarks') ?></textarea>
                    </div>
                </div>
            </section>

            <div class="simple-actions">
                <a class="simple-back" href="<?= e($baseUrl . '/staff/appointments') ?>">Back</a>
                <button type="submit" class="simple-submit">Create Appointment</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const baseUrl = <?= json_encode($baseUrl) ?>;

    const patientModeInput = document.getElementById('patient_mode');
    const existingPatientSection = document.getElementById('existingPatientSection');
    const walkInPatientSection = document.getElementById('walkInPatientSection');
    const patientSelect = document.getElementById('patient_id');
    const walkInFields = walkInPatientSection.querySelectorAll('input, select, textarea');
    const walkInRequiredFields = document.querySelectorAll('.walkin-required');

    const serviceInput = document.getElementById('service_id');
    const dentistInput = document.getElementById('dentist_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSelect = document.getElementById('start_time');

    function togglePatientMode() {
        const mode = patientModeInput.value || 'existing';

        if (mode === 'walk_in') {
            existingPatientSection.classList.add('hidden');
            walkInPatientSection.classList.remove('hidden');

            patientSelect.required = false;
            patientSelect.disabled = true;

            walkInFields.forEach(function (field) {
                field.disabled = false;
            });

            walkInRequiredFields.forEach(function (field) {
                field.required = true;
            });
        } else {
            existingPatientSection.classList.remove('hidden');
            walkInPatientSection.classList.add('hidden');

            patientSelect.disabled = false;
            patientSelect.required = true;

            walkInFields.forEach(function (field) {
                field.disabled = true;
            });

            walkInRequiredFields.forEach(function (field) {
                field.required = false;
            });
        }
    }

    function resetTimeSlots(message) {
        timeSelect.innerHTML = '';

        const option = document.createElement('option');
        option.value = '';
        option.textContent = message || 'Select available time';

        timeSelect.appendChild(option);
    }

    async function loadSlots() {
        const serviceId = serviceInput.value;
        const dentistId = dentistInput.value;
        const date = dateInput.value;

        resetTimeSlots('Select available time');

        if (!serviceId || !dentistId || !date) {
            return;
        }

        resetTimeSlots('Loading slots...');

        try {
            const url = `${baseUrl}/staff/appointments/available-slots?service_id=${encodeURIComponent(serviceId)}&dentist_id=${encodeURIComponent(dentistId)}&date=${encodeURIComponent(date)}`;

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Unable to load available slots.');
            }

            const data = await response.json();
            const slots = data.available_slots || data.slots || [];

            timeSelect.innerHTML = '';

            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select available time';
            timeSelect.appendChild(defaultOption);

            if (!slots.length) {
                resetTimeSlots('No available slots');
                return;
            }

            slots.forEach(function (slot) {
                if (slot.is_available === false) {
                    return;
                }

                const startTime = slot.start_time || '';
                const label = slot.label || startTime;

                if (startTime === '') {
                    return;
                }

                const option = document.createElement('option');
                option.value = startTime;
                option.textContent = label;
                timeSelect.appendChild(option);
            });

            if (timeSelect.options.length <= 1) {
                resetTimeSlots('No available slots');
            }
        } catch (error) {
            console.error(error);
            resetTimeSlots('Failed to load slots');
        }
    }

    patientModeInput.addEventListener('change', togglePatientMode);

    serviceInput.addEventListener('change', loadSlots);
    dentistInput.addEventListener('change', loadSlots);
    dateInput.addEventListener('change', loadSlots);

    togglePatientMode();
});
</script>

<?php
$staffContent = ob_get_clean();
$content = $staffContent;

$pageTitle = 'Create Appointment';
$title = 'Create Appointment';

require __DIR__ . '/../layouts/app.php';
?>