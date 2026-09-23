<?php
use App\Core\Csrf;

$patient = is_array($patient ?? null) ? $patient : [];
$medicalHistory = is_array($medicalHistory ?? null) ? $medicalHistory : [];
$dentalHistory = is_array($dentalHistory ?? null) ? $dentalHistory : [];
$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

function patient_history_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function patient_history_checked(array $data, string $key): string
{
    return !empty($data[$key]) ? 'checked' : '';
}

function patient_history_selected(array $data, string $key, string $value): string
{
    return strtolower((string) ($data[$key] ?? '')) === strtolower($value) ? 'selected' : '';
}

ob_start();
?>
<style>
.patient-history-page{padding:28px 18px 48px;background:#f4f8fb;min-height:100vh;color:#1f2937}.patient-history-wrap{max-width:980px;margin:auto}.patient-history-card{background:#fff;border:1px solid #dbe5ef;border-radius:20px;padding:24px;box-shadow:0 12px 34px rgba(15,23,42,.06);margin-bottom:18px}.patient-history-title{margin:0;color:#10233f;font-size:28px}.patient-history-copy{color:#64748b;line-height:1.6}.patient-history-alert{padding:13px 15px;border-radius:12px;margin-bottom:16px;font-weight:700}.patient-history-alert.success{background:#ecfdf5;color:#065f46}.patient-history-alert.error{background:#fef2f2;color:#991b1b}.patient-history-section{border-top:1px solid #e5e7eb;padding-top:20px;margin-top:22px}.patient-history-section h2{font-size:18px;color:#10233f;margin:0 0 14px}.patient-history-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.patient-history-field.full{grid-column:1/-1}.patient-history-field label{display:block;font-size:12px;font-weight:800;color:#475569;margin-bottom:6px}.patient-history-field input,.patient-history-field select,.patient-history-field textarea{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:10px 12px;background:#fff;color:#1f2937;font:inherit}.patient-history-field textarea{min-height:86px;resize:vertical}.patient-history-checks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.patient-history-check{display:flex;gap:8px;align-items:center;padding:9px;border:1px solid #e2e8f0;border-radius:9px;font-size:13px}.patient-history-actions{display:flex;justify-content:flex-end;margin-top:22px}.patient-history-submit{border:0;border-radius:12px;padding:12px 18px;background:#0d9e8c;color:#fff;font-weight:900;cursor:pointer}@media(max-width:700px){.patient-history-grid,.patient-history-checks{grid-template-columns:1fr}.patient-history-card{padding:18px}.patient-history-title{font-size:23px}}
</style>
<div class="patient-history-page"><div class="patient-history-wrap">
    <div class="patient-history-card">
        <h1 class="patient-history-title">Medical &amp; Dental History</h1>
        <p class="patient-history-copy">Complete your information accurately. Clinic staff will review it before approving your patient record.</p>
        <?php if ($flash_success): ?><div class="patient-history-alert success"><?= patient_history_e($flash_success) ?></div><?php endif; ?>
        <?php if ($flash_error): ?><div class="patient-history-alert error"><?= patient_history_e($flash_error) ?></div><?php endif; ?>
        <form method="POST" action="/DentalClinic/public/patient/medical-history">
            <?= Csrf::inputField() ?>
            <section class="patient-history-section" style="border-top:0;padding-top:0;margin-top:0"><h2>Personal Information</h2><div class="patient-history-grid">
                <div class="patient-history-field"><label>First name</label><input name="first_name" required value="<?= patient_history_e($patient['first_name'] ?? '') ?>"></div>
                <div class="patient-history-field"><label>Last name</label><input name="last_name" required value="<?= patient_history_e($patient['last_name'] ?? '') ?>"></div>
                <div class="patient-history-field"><label>Middle name</label><input name="middle_name" value="<?= patient_history_e($patient['middle_name'] ?? '') ?>"></div>
                <div class="patient-history-field"><label>Date of birth</label><input type="date" name="birth_date" value="<?= patient_history_e($patient['birth_date'] ?? '') ?>"></div>
                <div class="patient-history-field"><label>Sex</label><select name="sex"><option value="">Select</option><option <?= patient_history_selected($patient,'sex','female') ?>>Female</option><option <?= patient_history_selected($patient,'sex','male') ?>>Male</option></select></div>
                <div class="patient-history-field"><label>Contact number</label><input name="contact_number" value="<?= patient_history_e($patient['contact_number'] ?? '') ?>"></div>
                <div class="patient-history-field full"><label>Email</label><input type="email" name="email" value="<?= patient_history_e($patient['email'] ?? '') ?>"></div>
                <div class="patient-history-field full"><label>Address</label><textarea name="address"><?= patient_history_e($patient['address'] ?? '') ?></textarea></div>
                <div class="patient-history-field"><label>Emergency contact name</label><input name="emergency_contact_name" value="<?= patient_history_e($patient['emergency_contact_name'] ?? '') ?>"></div>
                <div class="patient-history-field"><label>Emergency contact number</label><input name="emergency_contact_number" value="<?= patient_history_e($patient['emergency_contact_number'] ?? '') ?>"></div>
            </div></section>
            <section class="patient-history-section"><h2>Medical History</h2><div class="patient-history-grid">
                <div class="patient-history-field"><label>Under physician care?</label><select name="under_physician_care"><option value="">Select</option><option <?= patient_history_selected($medicalHistory,'under_physician_care','yes') ?>>Yes</option><option <?= patient_history_selected($medicalHistory,'under_physician_care','no') ?>>No</option></select></div>
                <div class="patient-history-field"><label>Currently taking medicine?</label><select name="taking_medicine"><option value="">Select</option><option <?= patient_history_selected($medicalHistory,'taking_medicine','yes') ?>>Yes</option><option <?= patient_history_selected($medicalHistory,'taking_medicine','no') ?>>No</option></select></div>
                <div class="patient-history-field full"><label>Medical conditions and physician details</label><textarea name="physician_care_details"><?= patient_history_e($medicalHistory['physician_care_details'] ?? '') ?></textarea></div>
                <div class="patient-history-field full"><label>Medication details and allergies</label><textarea name="medicine_details"><?= patient_history_e($medicalHistory['medicine_details'] ?? '') ?></textarea></div>
                <div class="patient-history-field"><label>Hospitalized before?</label><select name="hospitalized"><option value="">Select</option><option <?= patient_history_selected($medicalHistory,'hospitalized','yes') ?>>Yes</option><option <?= patient_history_selected($medicalHistory,'hospitalized','no') ?>>No</option></select></div>
                <div class="patient-history-field"><label>Hospitalization details</label><input name="hospitalization_when" value="<?= patient_history_e($medicalHistory['hospitalization_when'] ?? '') ?>"></div>
            </div><div class="patient-history-checks" style="margin-top:14px">
                <?php foreach (['condition_high_blood_pressure'=>'High blood pressure','condition_low_blood_pressure'=>'Low blood pressure','condition_asthma'=>'Asthma','condition_heart_disease'=>'Heart disease','condition_diabetes'=>'Diabetes','condition_tuberculosis'=>'Tuberculosis','condition_thyroid_problem'=>'Thyroid problem','condition_bleeding_problems'=>'Bleeding problems','condition_hepatitis'=>'Hepatitis','condition_others'=>'Other condition'] as $key => $label): ?><label class="patient-history-check"><input type="checkbox" name="<?= $key ?>" value="1" <?= patient_history_checked($medicalHistory,$key) ?>><?= patient_history_e($label) ?></label><?php endforeach; ?>
            </div></section>
            <section class="patient-history-section"><h2>Dental History</h2><div class="patient-history-grid">
                <div class="patient-history-field"><label>Previous dentist</label><input name="previous_dentist" value="<?= patient_history_e($dentalHistory['previous_dentist'] ?? '') ?>"></div>
                <div class="patient-history-field"><label>Last dental visit</label><input type="date" name="last_dental_visit" value="<?= patient_history_e($dentalHistory['last_dental_visit'] ?? '') ?>"></div>
                <div class="patient-history-field full"><label>Previous treatment or current concerns</label><textarea name="last_dental_visit_reason"><?= patient_history_e($dentalHistory['last_dental_visit_reason'] ?? '') ?></textarea></div>
                <div class="patient-history-field full"><label>Other dental information</label><textarea name="dental_notes"><?= patient_history_e($dentalHistory['notes'] ?? '') ?></textarea></div>
            </div><div class="patient-history-checks" style="margin-top:14px"><?php foreach (['worn_denture'=>'Wears dentures','gums_bleed'=>'Gums bleed','bad_breath'=>'Bad breath','loose_teeth'=>'Loose teeth','sensitive_teeth'=>'Sensitive teeth','clicking_jaw'=>'Clicking jaw'] as $key => $label): ?><label class="patient-history-check"><input type="checkbox" name="<?= $key ?>" value="yes" <?= patient_history_selected($dentalHistory,$key,'yes') ?>><?= patient_history_e($label) ?></label><?php endforeach; ?></div></section>
            <div class="patient-history-actions"><button class="patient-history-submit" type="submit">Submit Information for Review</button></div>
        </form>
    </div>
</div></div>
<?php
$content = ob_get_clean();
$title = 'Medical & Dental History';
require __DIR__ . '/layouts/app.php';
