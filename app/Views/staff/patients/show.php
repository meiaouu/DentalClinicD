<?php

use App\Core\Csrf;

$pageTitle = 'Patient Details';
$baseUrl = '/DentalClinic/public';

$patient = isset($patient) && is_array($patient) ? $patient : [];
$medicalHistory = isset($medicalHistory) && is_array($medicalHistory) ? $medicalHistory : [];
$dentalHistory = isset($dentalHistory) && is_array($dentalHistory) ? $dentalHistory : [];
$appointments = isset($appointments) && is_array($appointments) ? $appointments : [];

$odontogramEntries = isset($odontogramEntries) && is_array($odontogramEntries) ? $odontogramEntries : [];
$odontogramEntriesByTooth = isset($odontogramEntriesByTooth) && is_array($odontogramEntriesByTooth) ? $odontogramEntriesByTooth : [];
$latestIntraoralExam = isset($latestIntraoralExam) && is_array($latestIntraoralExam) ? $latestIntraoralExam : [];

$upperTeeth = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
$lowerTeeth = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];

if (empty($odontogramEntriesByTooth) && !empty($odontogramEntries)) {
    foreach ($odontogramEntries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $toothNumber = (int) ($entry['tooth_number'] ?? 0);

        if ($toothNumber > 0) {
            $odontogramEntriesByTooth[$toothNumber][] = $entry;
        }
    }
}

$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('displayValue')) {
    function displayValue($value, string $fallback = '—'): string
    {
        $text = trim((string) $value);
        return $text !== '' ? e($text) : $fallback;
    }
}

if (!function_exists('statusText')) {
    function statusText(string $status): string
    {
        return $status !== '' ? ucwords(str_replace('_', ' ', $status)) : 'N/A';
    }
}

if (!function_exists('normalizeAnswer')) {
    function normalizeAnswer($value): string
    {
        $value = strtolower(trim((string) $value));

        return match ($value) {
            '1', 'yes', 'y', 'true' => 'yes',
            '0', 'no', 'n', 'false' => 'no',
            default => '',
        };
    }
}

if (!function_exists('selectedAnswer')) {
    function selectedAnswer($value, string $target): string
    {
        return normalizeAnswer($value) === $target ? 'selected' : '';
    }
}

if (!function_exists('checkedAttr')) {
    function checkedAttr($value): string
    {
        return (int) $value === 1 ? 'checked' : '';
    }
}

if (!function_exists('selectedText')) {
    function selectedText($value, string $target): string
    {
        return strcasecmp(trim((string) $value), $target) === 0 ? 'selected' : '';
    }
}

if (!function_exists('formatDateText')) {
    function formatDateText($date): string
    {
        $text = trim((string) $date);

        if ($text === '') {
            return '—';
        }

        $timestamp = strtotime($text);

        return $timestamp ? e(date('M d, Y', $timestamp)) : e($text);
    }
}

if (!function_exists('formatAppointmentTime')) {
    function formatAppointmentTime($start, $end): string
    {
        $startText = trim((string) $start);
        $endText = trim((string) $end);

        if ($startText === '' && $endText === '') {
            return '—';
        }

        $formattedStart = $startText;
        $formattedEnd = $endText;

        if ($startText !== '') {
            $startTimestamp = strtotime($startText);
            $formattedStart = $startTimestamp ? date('h:i A', $startTimestamp) : $startText;
        }

        if ($endText !== '') {
            $endTimestamp = strtotime($endText);
            $formattedEnd = $endTimestamp ? date('h:i A', $endTimestamp) : $endText;
        }

        if ($formattedStart !== '' && $formattedEnd !== '') {
            return e($formattedStart . ' - ' . $formattedEnd);
        }

        return e($formattedStart !== '' ? $formattedStart : $formattedEnd);
    }

    if (!function_exists('appointmentServiceDisplay')) {
    function appointmentServiceDisplay(array $appointment): string
    {
        if (!empty($appointment['service_names']) && is_array($appointment['service_names'])) {
            $names = array_filter(array_map('trim', array_map('strval', $appointment['service_names'])));
            return !empty($names) ? implode(', ', $names) : '—';
        }

        foreach (['service_names_text', 'services_text', 'service_name'] as $key) {
            $value = trim((string) ($appointment[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '—';
    }
}
}


if (!function_exists('patientHistoryHasData')) {
    function patientHistoryHasData(array $history, array $checkboxFields = []): bool
    {
        if (empty($history)) {
            return false;
        }

        $ignoredFields = [
            'medical_history_id',
            'dental_history_id',
            'history_id',
            'patient_id',
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
        ];

        foreach ($history as $field => $value) {
            if (in_array((string) $field, $ignoredFields, true)) {
                continue;
            }

            if (in_array((string) $field, $checkboxFields, true)) {
                if ((int) $value === 1) {
                    return true;
                }

                continue;
            }

            $text = trim((string) $value);

            if ($text !== '' && $text !== '0000-00-00') {
                return true;
            }
        }

        return false;
    }
}


if (!function_exists('staffPatientToothImageFilename')) {
    function staffPatientToothImageFilename(int $toothNumber): string
    {
        $upperTeeth = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];

        $prefix = in_array($toothNumber, $upperTeeth, true)
            ? 'dentadura-sup-'
            : 'dentadura-inf-';

        return $prefix . $toothNumber . '.png';
    }
}

if (!function_exists('staffPatientToothImageCandidates')) {
    function staffPatientToothImageCandidates(int $toothNumber, string $baseUrl): array
    {
        $filename = staffPatientToothImageFilename($toothNumber);

        return [
            $baseUrl . '/assets/images/' . $filename,
            $baseUrl . '/assets/images/teeth/' . $filename,
            $baseUrl . '/images/' . $filename,
            '/DentalClinic/public/assets/images/' . $filename,
            '/DentalClinic/public/assets/images/teeth/' . $filename,
        ];
    }
}

if (!function_exists('staffPatientToothTypeLabel')) {
    function staffPatientToothTypeLabel(int $toothNumber): string
    {
        $lastDigit = (int) substr((string) $toothNumber, -1);

        return match ($lastDigit) {
            1, 2 => 'Incisor',
            3 => 'Canine',
            4, 5 => 'Premolar',
            default => 'Molar',
        };
    }
}

if (!function_exists('staffPatientLatestCondition')) {
    function staffPatientLatestCondition(array $items): string
    {
        foreach ($items as $item) {
            $condition = trim((string) ($item['condition_code'] ?? ''));

            if ($condition !== '') {
                return $condition;
            }
        }

        $first = $items[0] ?? [];

        return trim((string) ($first['procedure_name'] ?? ''));
    }
}

if (!function_exists('staffPatientConditionClass')) {
    function staffPatientConditionClass(string $condition): string
    {
        $condition = strtolower(trim($condition));

        return match ($condition) {
            'caries', 'carries' => 'caries',
            'missing' => 'missing',
            'restoration', 'restored' => 'restoration',
            'fractured', 'fracture' => 'fractured',
            'root canal treated', 'root canal', 'rct' => 'root-canal',
            'impacted' => 'impacted',
            'for extraction', 'extraction' => 'extraction',
            'crown' => 'crown',
            'pontic' => 'pontic',
            'sealant' => 'sealant',
            'sound', 'healthy' => 'sound',
            '' => 'none',
            default => 'other',
        };
    }
}

if (!function_exists('staffPatientConditionLabel')) {
    function staffPatientConditionLabel(string $condition): string
    {
        $condition = strtolower(trim($condition));

        return match ($condition) {
            'caries', 'carries' => 'Caries',
            'missing' => 'Missing',
            'restoration', 'restored' => 'Restoration',
            'fractured', 'fracture' => 'Fractured',
            'root canal treated', 'root canal', 'rct' => 'Root Canal Treated',
            'impacted' => 'Impacted',
            'for extraction', 'extraction' => 'For Extraction',
            'crown' => 'Crown',
            'pontic' => 'Pontic',
            'sealant' => 'Sealant',
            'sound', 'healthy' => 'Sound',
            '' => 'No record',
            default => ucwords($condition),
        };
    }
}

if (!function_exists('staffPatientChartTooltip')) {
    function staffPatientChartTooltip(array $items): string
    {
        if (empty($items)) {
            return 'No intraoral examination record.';
        }

        $lines = [];

        foreach ($items as $item) {
            $condition = trim((string) ($item['condition_code'] ?? ''));
            $procedure = trim((string) ($item['procedure_name'] ?? ''));
            $surface = trim((string) ($item['surface'] ?? ''));
            $notes = trim((string) ($item['notes'] ?? $item['remarks'] ?? ''));

            $line = $condition !== '' ? $condition : $procedure;

            if ($surface !== '') {
                $line .= ' - Surface: ' . $surface;
            }

            if ($notes !== '') {
                $line .= ' | ' . $notes;
            }

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return !empty($lines) ? implode("\n", $lines) : 'No intraoral examination record.';
    }
}

if (!function_exists('staffPatientDentistNameFromExam')) {
    function staffPatientDentistNameFromExam(array $row): string
    {
        $name = trim(
            (string) (($row['dentist_first_name'] ?? '') . ' ' .
            ($row['dentist_middle_name'] ?? '') . ' ' .
            ($row['dentist_last_name'] ?? ''))
        );

        return $name !== '' ? 'Dr. ' . $name : '—';
    }
}

$fullName = trim(
    (string) (($patient['first_name'] ?? '') . ' ' . ($patient['middle_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''))
);

$patientSince = '';

if (!empty($patient['created_at'])) {
    $timestamp = strtotime((string) $patient['created_at']);
    if ($timestamp !== false) {
        $patientSince = date('F Y', $timestamp);
    }
}

$patientAge = '—';

if (!empty($patient['birth_date'])) {
    try {
        $today = new DateTime();
        $birth = new DateTime((string) $patient['birth_date']);
        $patientAge = (string) $today->diff($birth)->y;
    } catch (Throwable $e) {
        $patientAge = '—';
    }
}

$patientCode = trim((string) ($patient['patient_code'] ?? ('PAT-' . (int) ($patient['patient_id'] ?? 0))));
$patientGender = trim((string) ($patient['sex'] ?? ''));
$patientBirthDate = formatDateText($patient['birth_date'] ?? '');
$patientPhone = trim((string) ($patient['contact_number'] ?? ''));
$patientEmail = trim((string) ($patient['email'] ?? ''));
$patientAddress = trim((string) ($patient['address'] ?? ''));

$primaryDentistName = '';

foreach ($appointments as $appointmentItem) {
    $dentistNameValue = trim(
        (string) (($appointmentItem['dentist_first_name'] ?? '') . ' ' . ($appointmentItem['dentist_last_name'] ?? ''))
    );

    if ($dentistNameValue !== '') {
        $primaryDentistName = 'Dr. ' . $dentistNameValue;
        break;
    }
}

$totalAppointments = count($appointments);

$latestExamDentist = staffPatientDentistNameFromExam($latestIntraoralExam);
$latestExamDate = formatDateText($latestIntraoralExam['examination_date'] ?? '');
$latestExamNotes = trim((string) (
    $latestIntraoralExam['intraoral_exam_notes']
    ?? $latestIntraoralExam['clinical_findings']
    ?? $latestIntraoralExam['diagnosis']
    ?? $latestIntraoralExam['notes']
    ?? ''
));

$medicalCheckboxFields = [
    'condition_high_blood_pressure',
    'condition_low_blood_pressure',
    'condition_asthma',
    'condition_heart_disease',
    'condition_diabetes',
    'condition_tuberculosis',
    'condition_thyroid_problem',
    'condition_bleeding_problems',
    'condition_hiv_aids',
    'condition_hepatitis',
    'condition_others',
];

$hasMedicalHistory = patientHistoryHasData($medicalHistory, $medicalCheckboxFields);
$hasDentalHistory = patientHistoryHasData($dentalHistory);

$missingHistoryLabels = [];

if (!$hasMedicalHistory) {
    $missingHistoryLabels[] = 'medical history';
}

if (!$hasDentalHistory) {
    $missingHistoryLabels[] = 'dental history';
}

ob_start();
?>

<style>
.staff-patient-record-page {
    min-height: calc(100dvh - 74px);
    background: #f3f3f3;
    color: #111827;
    padding: 0 0 40px;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

.staff-patient-shell,
.staff-patient-paper {
    width: 100%;
    margin: 0;
}

.patient-toolbar {
    width: 100%;
    min-height: 50px;
    display: flex;
    align-items: stretch;
    justify-content: center;
    background: #ffffff;
    border-top: 1px solid #d8d8d8;
    border-bottom: 1px solid #d8d8d8;
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: none;
}

.patient-toolbar::-webkit-scrollbar {
    display: none;
}

.patient-toolbar-item {
    min-width: 150px;
    min-height: 50px;
    padding: 5px 10px;
    border: 0;
    border-left: 1px solid #d8d8d8;
    background: #ffffff;
    color: #4b5563;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-family: Arial, sans-serif;
    cursor: pointer;
    box-sizing: border-box;
    text-decoration: none;
    transition: background 0.16s ease, color 0.16s ease;
}

.patient-toolbar-item:last-child {
    border-right: 1px solid #d8d8d8;
}

.patient-toolbar-item:hover,
.patient-toolbar-item.active {
    background: #f5f5f5;
    color: #111827;
}

.patient-toolbar-icon {
    width: 26px;
    height: 26px;
    color: #4b5563;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.patient-toolbar-icon svg {
    width: 24px;
    height: 24px;
    stroke: currentColor;
    stroke-width: 1.9;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.patient-toolbar-text {
    display: grid;
    line-height: 1.05;
    text-align: left;
}

.patient-toolbar-count {
    color: #111827;
    font-size: 12px;
    font-weight: 900;
}

.patient-toolbar-label {
    color: #4b5563;
    font-size: 13px;
    font-weight: 700;
}

.record-top {
    width: 100%;
    background: #ffffff;
    border-bottom: 1px solid #d9d9d9;
    padding: 82px 56px 0;
    margin: 0;
    box-shadow: none;
    box-sizing: border-box;
}

.patient-header-top {
    display: block;
    position: relative;
}

.record-back {
    position: absolute;
    top: -46px;
    left: 0;
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #374151;
    background: #ffffff;
    border: 1px solid #d9d9d9;
    text-decoration: none;
    box-sizing: border-box;
}

.record-back:hover {
    background: #f3f4f6;
}

.record-back svg {
    width: 17px;
    height: 17px;
    stroke: currentColor;
    stroke-width: 2.1;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.record-title {
    margin: 0 0 22px;
    color: #111827;
    font-size: 38px;
    line-height: 1.1;
    font-weight: 400;
    letter-spacing: 0.08em;
}

.patient-profile-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    column-gap: 160px;
    row-gap: 15px;
    max-width: 1040px;
}

.patient-profile-item {
    min-height: 22px;
    display: grid;
    grid-template-columns: 190px minmax(0, 1fr);
    gap: 18px;
    align-items: center;
}

.patient-profile-label {
    color: #374151;
    font-size: 15px;
    font-weight: 900;
    letter-spacing: 0.01em;
}

.patient-profile-value {
    color: #4b5563;
    font-size: 15px;
    font-weight: 700;
    word-break: break-word;
}

.patient-profile-question {
    color: #0f766e;
    font-weight: 900;
}

.record-tabs {
    display: flex;
    align-items: stretch;
    gap: 0;
    margin-top: 42px;
    padding-left: 0;
    border-bottom: 1px solid #d9d9d9;
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: none;
}

.record-tabs::-webkit-scrollbar {
    display: none;
}

.record-tab {
    min-height: 44px;
    padding: 0 18px;
    border: 1px solid #d9d9d9;
    border-bottom: 0;
    border-left: 0;
    background: #fafafa;
    color: #4b5563;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
}

.record-tab:first-child {
    border-left: 1px solid #d9d9d9;
}

.record-tab:hover,
.record-tab.active {
    background: #ffffff;
    color: #111827;
}

.patient-contact-strip {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 18px 160px;
    padding: 20px 0 18px;
    background: #ffffff;
    max-width: 1200px;
}

.patient-contact-item {
    min-height: 28px;
    display: grid;
    grid-template-columns: 150px minmax(0, 1fr);
    gap: 28px;
    align-items: start;
}

.patient-contact-label {
    color: #374151;
    font-size: 15px;
    font-weight: 900;
}

.patient-contact-value {
    color: #4b5563;
    font-size: 15px;
    font-weight: 700;
    line-height: 1.55;
    word-break: break-word;
}

.flash-wrap {
    display: grid;
    gap: 8px;
    padding: 14px 56px 0;
    margin-bottom: 0;
}

.flash-message {
    padding: 11px 13px;
    font-size: 13px;
    font-weight: 700;
    background: #ffffff;
    border: 1px solid #dddddd;
}

.flash-message.success {
    color: #166534;
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.flash-message.error {
    color: #b91c1c;
    background: #fef2f2;
    border-color: #fecaca;
}

.record-main {
    width: 100%;
    padding: 14px 56px 0;
    background: transparent;
    box-sizing: border-box;
}

.record-panel {
    display: none;
    opacity: 0;
    transform: translateY(5px);
}

.record-panel.active {
    display: block;
    animation: recordPanelFade 0.22s ease forwards;
}

@keyframes recordPanelFade {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.record-card {
    background: #ffffff;
    border: 1px solid #dddddd;
    padding: 0;
    box-sizing: border-box;
    box-shadow: none;
}

.record-card + .record-card {
    margin-top: 14px;
}

.record-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 20px 20px 16px;
    border-bottom: 1px solid #eeeeee;
}

.record-card-title {
    margin: 0;
    font-size: 21px;
    color: #111827;
    font-weight: 900;
    letter-spacing: 0.04em;
}

.record-card-note {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 12px;
    font-weight: 700;
}

.record-card-body {
    padding: 16px 20px 20px;
}

.record-form-actions {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.record-action-btn {
    min-height: 38px;
    border: 1px solid #111827;
    background: #ffffff;
    color: #111827;
    padding: 0 13px;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
}

.record-action-btn.primary {
    background: #111827;
    color: #ffffff;
}

.record-action-btn.danger {
    border-color: #b91c1c;
    color: #b91c1c;
}

.record-action-btn[hidden] {
    display: none !important;
}

.record-section {
    margin-bottom: 26px;
}

.record-section:last-child {
    margin-bottom: 0;
}

.record-section-title {
    margin: 0 0 14px;
    font-size: 15px;
    font-weight: 900;
    color: #0f766e;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.record-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.record-grid.two-col {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.record-item {
    min-width: 0;
    box-sizing: border-box;
}

.record-item.full,
.record-item.two-full {
    grid-column: 1 / -1;
}

.record-label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    color: #111827;
    font-weight: 700;
}

.record-value {
    display: block;
    min-height: 40px;
    padding: 10px 12px;
    border: 1px solid #d9d9d9;
    background: #fafafa;
    color: #111827;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.35;
    box-sizing: border-box;
}

.editable-line,
.editable-select,
.editable-textarea {
    width: 100%;
    min-height: 40px;
    border: 1px solid #d9d9d9;
    background: #ffffff;
    color: #111827;
    font-size: 13px;
    font-weight: 600;
    padding: 0 12px;
    box-sizing: border-box;
    outline: none;
    font-family: Arial, sans-serif;
}

.editable-textarea {
    min-height: 78px;
    padding: 10px 12px;
    resize: vertical;
    line-height: 1.5;
}

.editable-line:focus,
.editable-select:focus,
.editable-textarea:focus {
    border-color: #111827;
}

.record-form.is-readonly .editable-line,
.record-form.is-readonly .editable-select,
.record-form.is-readonly .editable-textarea {
    background: #fafafa;
    color: #374151;
    border-color: #e5e7eb;
    cursor: default;
}

.history-question {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 150px;
    gap: 12px;
    align-items: end;
}

.history-question.full {
    grid-column: 1 / -1;
}

.history-question-text {
    font-size: 13px;
    font-weight: 700;
    color: #111827;
    line-height: 1.45;
}

.condition-check-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px 12px;
    grid-column: 1 / -1;
}

.condition-check {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 36px;
    border: 1px solid #e5e7eb;
    background: #fafafa;
    padding: 0 10px;
    font-size: 13px;
    font-weight: 700;
    color: #111827;
    box-sizing: border-box;
}

.condition-check input {
    width: 14px;
    height: 14px;
    margin: 0;
}

.record-form.is-readonly .condition-check {
    background: #fafafa;
    color: #374151;
    opacity: 0.9;
}

.empty-state {
    border: 1px dashed #d1d5db;
    background: #fafafa;
    color: #6b7280;
    padding: 18px;
    font-size: 13px;
    text-align: center;
    font-weight: 800;
}

.appointment-wrap {
    overflow-x: auto;
}

.appointment-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
    background: #ffffff;
}

.appointment-table th,
.appointment-table td {
    border-bottom: 1px solid #eeeeee;
    padding: 14px;
    text-align: left;
    vertical-align: middle;
}

.appointment-table th {
    font-size: 12px;
    color: #111827;
    background: #fafafa;
    white-space: nowrap;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.appointment-table td {
    font-size: 13px;
    color: #111827;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 24px;
    padding: 0 10px;
    border: 1px solid #d1d5db;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
    white-space: nowrap;
    text-transform: capitalize;
    background: #ffffff;
    color: #111827;
}

.status-badge.confirmed,
.status-badge.checked_in,
.status-badge.in_progress,
.status-badge.completed {
    border-color: #bbf7d0;
    background: #dcfce7;
    color: #166534;
}

.status-badge.no_show,
.status-badge.cancelled,
.status-badge.rejected {
    border-color: #fecaca;
    background: #fee2e2;
    color: #b91c1c;
}

.status-badge.rescheduled,
.status-badge.pending {
    border-color: #fde68a;
    background: #fef3c7;
    color: #92400e;
}

.staff-intraoral-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.staff-intraoral-summary-card {
    border: 1px solid #e5e7eb;
    background: #fafafa;
    padding: 12px;
}

.staff-intraoral-summary-label {
    display: block;
    margin-bottom: 5px;
    color: #64748b;
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
}

.staff-intraoral-summary-value {
    color: #111827;
    font-size: 13px;
    font-weight: 800;
    line-height: 1.45;
    word-break: break-word;
}

.staff-intraoral-chart-wrap {
    width: 100%;
    overflow-x: auto;
    padding: 18px 0;
    background: #ffffff;
    border: 1px solid #eeeeee;
}

.staff-intraoral-chart-strip {
    width: fit-content;
    min-width: 1120px;
    margin: 0 auto;
    padding: 0 18px;
    user-select: none;
}

.staff-intraoral-upper-row,
.staff-intraoral-lower-row {
    display: grid;
    grid-template-columns: repeat(16, 48px);
    gap: 24px;
    align-items: end;
}

.staff-intraoral-upper-row {
    margin-bottom: 14px;
}

.staff-intraoral-lower-row {
    align-items: start;
}

.staff-intraoral-tooth {
    --tooth-mark-color: transparent;
    position: relative;
    width: 48px;
    min-height: 112px;
    border: 1px solid transparent;
    border-radius: 7px;
    background: transparent;
    padding: 2px 1px 4px;
    cursor: pointer;
    display: grid;
    grid-template-rows: 68px 18px 10px;
    align-items: center;
    justify-items: center;
}

.staff-intraoral-lower-row .staff-intraoral-tooth {
    grid-template-rows: 18px 68px 10px;
}

.staff-intraoral-tooth:hover,
.staff-intraoral-tooth.is-selected {
    background: #f0fdfa;
    border-color: #0f766e;
}

.staff-intraoral-tooth.has-record {
    background: #f8fafc;
    border-color: var(--tooth-mark-color);
}

.staff-intraoral-tooth.has-record::after {
    content: "";
    position: absolute;
    top: 4px;
    right: 4px;
    width: 11px;
    height: 11px;
    border-radius: 999px;
    background: var(--tooth-mark-color);
    border: 2px solid #ffffff;
}

.staff-intraoral-tooth-image {
    width: 44px;
    height: 68px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.staff-intraoral-tooth-image img {
    max-width: 44px;
    max-height: 68px;
    object-fit: contain;
    display: block;
    pointer-events: none;
}

.staff-intraoral-tooth-number {
    color: #8c9198;
    font-size: 13px;
    font-weight: 900;
    line-height: 1;
}

.staff-intraoral-dots {
    min-height: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.staff-intraoral-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    display: inline-block;
    background: #cbd5e1;
}

.staff-intraoral-missing-image {
    width: 44px;
    height: 68px;
    border: 1px dashed #d1d5db;
    border-radius: 999px 999px 18px 18px;
    background: #f9fafb;
    color: #9ca3af;
    font-size: 9px;
    font-weight: 900;
    display: flex;
    align-items: center;
    justify-content: center;
}

.condition-caries,
.staff-intraoral-tooth.condition-caries {
    --tooth-mark-color: #dc2626;
}

.condition-missing,
.staff-intraoral-tooth.condition-missing {
    --tooth-mark-color: #6b7280;
}

.condition-restoration,
.staff-intraoral-tooth.condition-restoration {
    --tooth-mark-color: #2563eb;
}

.condition-fractured,
.staff-intraoral-tooth.condition-fractured {
    --tooth-mark-color: #ea580c;
}

.condition-root-canal,
.staff-intraoral-tooth.condition-root-canal {
    --tooth-mark-color: #7c3aed;
}

.condition-impacted,
.staff-intraoral-tooth.condition-impacted {
    --tooth-mark-color: #9333ea;
}

.condition-extraction,
.staff-intraoral-tooth.condition-extraction {
    --tooth-mark-color: #111827;
}

.condition-crown,
.staff-intraoral-tooth.condition-crown {
    --tooth-mark-color: #ca8a04;
}

.condition-pontic,
.staff-intraoral-tooth.condition-pontic {
    --tooth-mark-color: #0891b2;
}

.condition-sealant,
.staff-intraoral-tooth.condition-sealant {
    --tooth-mark-color: #16a34a;
}

.condition-sound,
.staff-intraoral-tooth.condition-sound {
    --tooth-mark-color: #0f766e;
}

.condition-other,
.staff-intraoral-tooth.condition-other {
    --tooth-mark-color: #64748b;
}

.staff-intraoral-dot.caries { background: #dc2626; }
.staff-intraoral-dot.missing { background: #6b7280; }
.staff-intraoral-dot.restoration { background: #2563eb; }
.staff-intraoral-dot.fractured { background: #ea580c; }
.staff-intraoral-dot.root-canal { background: #7c3aed; }
.staff-intraoral-dot.impacted { background: #9333ea; }
.staff-intraoral-dot.extraction { background: #111827; }
.staff-intraoral-dot.crown { background: #ca8a04; }
.staff-intraoral-dot.pontic { background: #0891b2; }
.staff-intraoral-dot.sealant { background: #16a34a; }
.staff-intraoral-dot.sound { background: #0f766e; }
.staff-intraoral-dot.other { background: #64748b; }

.staff-intraoral-selected-box {
    margin-top: 14px;
    padding: 12px 14px;
    border: 1px solid #e5e7eb;
    background: #fafafa;
    color: #374151;
    font-size: 13px;
    font-weight: 800;
}

.staff-intraoral-selected-box strong {
    color: #0f766e;
}

.staff-intraoral-selected-meta {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.staff-intraoral-condition-chip {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 0 10px;
    border-radius: 999px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    font-size: 11px;
    font-weight: 900;
}


.history-notice-container {
    padding: 14px 56px 0;
    box-sizing: border-box;
}

.history-notice {
    width: 100%;
    background-color: rgb(254 252 232);
    border-left: 4px solid rgb(250 204 21);
    padding: 14px 16px;
    box-sizing: border-box;
}

.history-notice-flex {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.history-notice-icon {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    color: rgb(250 204 21);
    margin-top: 1px;
}

.history-notice-body {
    color: rgb(202 138 4);
    font-size: 13px;
    font-weight: 700;
    line-height: 1.5;
}

.history-notice-body p {
    margin: 0;
}

.history-notice-link {
    color: rgb(141, 56, 0);
    font-weight: 900;
    text-decoration: underline;
}

.history-notice-link:hover {
    color: rgb(202 138 4);
}

@media (max-width: 1000px) {
    .record-top,
    .record-main,
    .flash-wrap {
        padding-left: 24px;
        padding-right: 24px;
    }

    .patient-profile-grid,
    .patient-contact-strip,
    .staff-intraoral-summary {
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .patient-profile-item,
    .patient-contact-item {
        grid-template-columns: 170px minmax(0, 1fr);
    }

    .record-grid,
    .record-grid.two-col {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .condition-check-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .record-top,
    .record-main,
    .flash-wrap,
    .history-notice-container {
        padding-left: 14px;
        padding-right: 14px;
    }

    .record-top {
        padding-top: 58px;
    }

    .record-title {
        font-size: 26px;
    }

    .patient-toolbar {
        justify-content: flex-start;
    }

    .patient-profile-grid,
    .patient-contact-strip,
    .staff-intraoral-summary,
    .record-grid,
    .record-grid.two-col,
    .condition-check-grid {
        grid-template-columns: 1fr;
    }

    .patient-profile-item,
    .patient-contact-item,
    .history-question {
        grid-template-columns: 1fr;
        gap: 4px;
    }

    .record-card-header {
        display: block;
    }

    .record-form-actions {
        margin-top: 12px;
        width: 100%;
    }

    .record-action-btn {
        flex: 1;
    }
}

@media print {
    .staff-sidebar,
    .dentist-sidebar,
    .topbar,
    .sidebar,
    .app-sidebar,
    .layout-sidebar,
    .record-back,
    .patient-toolbar,
    .record-tabs,
    .flash-wrap,
    .record-form-actions {
        display: none !important;
    }

    .staff-patient-record-page,
    .record-top,
    .record-main {
        background: #ffffff !important;
        padding: 0 !important;
    }

    .record-panel {
        display: none !important;
    }

    .record-panel.active {
        display: block !important;
        opacity: 1 !important;
        transform: none !important;
        animation: none !important;
    }

    .record-card {
        border: none !important;
        page-break-inside: avoid;
    }
}
</style>

<div class="staff-patient-record-page">
    <div class="staff-patient-shell">
        <div class="staff-patient-paper" id="printableStaffPatientRecord">

            <div class="patient-toolbar" aria-label="Patient quick menu">
    <button type="button" class="patient-toolbar-item active" data-toolbar-tab="patient-record">
        <span class="patient-toolbar-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <path d="M5 4h14v16H5z"></path>
                <path d="M8 8h8"></path>
                <path d="M8 12h8"></path>
                <path d="M8 16h5"></path>
            </svg>
        </span>

        <span class="patient-toolbar-text">
            <span class="patient-toolbar-label">Patient Record</span>
        </span>
    </button>

    <button type="button" class="patient-toolbar-item" data-toolbar-tab="dental-chart">
        <span class="patient-toolbar-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="16" rx="1.5"></rect>
                <path d="M7 15l3-3 2 2 3-4 2 5"></path>
                <circle cx="8" cy="8" r="1.2"></circle>
            </svg>
        </span>

        <span class="patient-toolbar-text">
            <span class="patient-toolbar-label">Dental Chart</span>
        </span>
    </button>

    <button type="button" class="patient-toolbar-item" data-toolbar-tab="appointment-history">
        <span class="patient-toolbar-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <path d="M6 4v5a4 4 0 0 0 8 0V4"></path>
                <path d="M4 4h4"></path>
                <path d="M12 4h4"></path>
                <path d="M14 9v4a4 4 0 0 0 8 0v-1"></path>
                <circle cx="22" cy="12" r="1"></circle>
            </svg>
        </span>

        <span class="patient-toolbar-text">
            <span class="patient-toolbar-count"><?= e((string) $totalAppointments) ?></span>
            <span class="patient-toolbar-label">Appointments</span>
        </span>
    </button>
</div>

            <div class="record-top">
                <div class="patient-profile-header">
                    <div class="patient-header-top">
                        <a href="<?= e($baseUrl . '/staff/patients') ?>" class="record-back" aria-label="Back to Patients" title="Back to Patients">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M15 6l-6 6 6 6"></path>
                            </svg>
                        </a>

                        <div class="patient-profile-main">
                            <h1 class="record-title">
                                <?= e($fullName !== '' ? $fullName : 'Unnamed Patient') ?>
                            </h1>

                             <div class="patient-profile-item">
                                    <span class="patient-profile-label"></span>
                                    <span class="patient-profile-value">
                                        <?= $patientSince !== '' ? 'Patient since ' . e($patientSince) : '—' ?>
                                    </span>
                                </div>


                                <br>

                            <div class="patient-profile-grid">
                                <div class="patient-profile-item">
                                    <span class="patient-profile-label">
                                        Identification Code <span class="patient-profile-question">?</span>
                                    </span>
                                    <span class="patient-profile-value"><?= displayValue($patientCode) ?></span>
                                </div>

                                <div class="patient-profile-item">
                                    <span class="patient-profile-label">Date of Birth</span>
                                    <span class="patient-profile-value"><?= e($patientBirthDate) ?></span>
                                </div>

                                <div class="patient-profile-item">
                                    <span class="patient-profile-label">Gender</span>
                                    <span class="patient-profile-value"><?= displayValue($patientGender) ?></span>
                                </div>

                                <div class="patient-profile-item">
                                    <span class="patient-profile-label">Age</span>
                                    <span class="patient-profile-value"><?= e($patientAge !== '—' ? $patientAge . ' Year' : '—') ?></span>
                                </div>

                               
                            </div>
                        </div>
                    </div>

                   

                    <div class="patient-contact-strip">
                        <div class="patient-contact-item">
                            <span class="patient-contact-label">Address</span>
                            <span class="patient-contact-value"><?= displayValue($patientAddress) ?></span>
                        </div>

                        <div>
                            <div class="patient-contact-item">
                                <span class="patient-contact-label">Phone</span>
                                <span class="patient-contact-value"><?= displayValue($patientPhone) ?></span>
                            </div>

                            <div class="patient-contact-item">
                                <span class="patient-contact-label">Mobile</span>
                                <span class="patient-contact-value"><?= displayValue($patientPhone) ?></span>
                            </div>

                            <div class="patient-contact-item">
                                <span class="patient-contact-label">Email</span>
                                <span class="patient-contact-value"><?= displayValue($patientEmail) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <?php if (!$hasMedicalHistory || !$hasDentalHistory): ?>
    <div class="history-notice-container">
        <div class="history-notice" role="alert">
            <div class="history-notice-flex">
                <svg aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" class="history-notice-icon">
                    <path clip-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" fill-rule="evenodd"></path>
                </svg>

                <div class="history-notice-body">
                    <p>
                        This patient still has no <?= e(implode(' and ', $missingHistoryLabels)) ?> on record.

                        <?php if (!$hasMedicalHistory): ?>
                            <a href="#medical-history-section" class="history-notice-link" data-history-jump="medical-history-section">
                                Add medical history
                            </a>
                        <?php endif; ?>

                        <?php if (!$hasMedicalHistory && !$hasDentalHistory): ?>
                            <span> / </span>
                        <?php endif; ?>

                        <?php if (!$hasDentalHistory): ?>
                            <a href="#dental-history-section" class="history-notice-link" data-history-jump="dental-history-section">
                                Add dental history
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
            <?php if ($flash_success || $flash_error): ?>
                <div class="flash-wrap">
                    <?php if ($flash_success): ?>
                        <div class="flash-message success"><?= e($flash_success) ?></div>
                    <?php endif; ?>

                    <?php if ($flash_error): ?>
                        <div class="flash-message error"><?= e($flash_error) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <main class="record-main">
                <section class="record-panel active" data-panel="patient-record">
                    <form method="POST" action="<?= e($baseUrl . '/staff/patients/update-record') ?>" class="record-form is-readonly" id="patientRecordForm">
                        <?= Csrf::inputField(); ?>
                        <input type="hidden" name="patient_id" value="<?= (int) ($patient['patient_id'] ?? 0) ?>">

                        <div class="record-card">
                            <div class="record-card-header">
                                <div>
                                    <h2 class="record-card-title">Patient Information Record</h2>
                                  
                                </div>

                                <div class="record-form-actions">
                                    <button type="button" class="record-action-btn" id="editRecordBtn">Edit Record</button>
                                    <button type="submit" class="record-action-btn primary" id="saveRecordBtn" hidden>Save Changes</button>
                                    <button type="button" class="record-action-btn danger" id="cancelRecordBtn" hidden>Cancel</button>
                                </div>
                            </div>

                            <div class="record-card-body">
                                <div class="record-section">
                                    <h3 class="record-section-title">General Information</h3>

                                    <div class="record-grid">
                                        <div class="record-item">
                                            <span class="record-label">Patient ID</span>
                                            <span class="record-value">#<?= (int) ($patient['patient_id'] ?? 0) ?></span>
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="first_name">First Name</label>
                                            <input class="editable-line" data-record-field id="first_name" type="text" name="first_name" value="<?= e((string) ($patient['first_name'] ?? '')) ?>" required>
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="middle_name">Middle Name</label>
                                            <input class="editable-line" data-record-field id="middle_name" type="text" name="middle_name" value="<?= e((string) ($patient['middle_name'] ?? '')) ?>">
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="last_name">Last Name</label>
                                            <input class="editable-line" data-record-field id="last_name" type="text" name="last_name" value="<?= e((string) ($patient['last_name'] ?? '')) ?>" required>
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="contact_number">Contact Number</label>
                                            <input class="editable-line" data-record-field id="contact_number" type="tel" name="contact_number" value="<?= e((string) ($patient['contact_number'] ?? '')) ?>" placeholder="09XXXXXXXXX">
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="email">Email</label>
                                            <input class="editable-line" data-record-field id="email" type="email" name="email" value="<?= e((string) ($patient['email'] ?? '')) ?>">
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="birth_date">Birth Date</label>
                                            <input class="editable-line" data-record-field id="birth_date" type="date" name="birth_date" value="<?= e((string) ($patient['birth_date'] ?? '')) ?>" max="<?= e(date('Y-m-d')) ?>">
                                        </div>

                                        <div class="record-item">
                                            <span class="record-label">Age</span>
                                            <span class="record-value"><?= e($patientAge !== '—' ? $patientAge . ' yrs' : '—') ?></span>
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="sex">Sex</label>
                                            <select class="editable-select" data-record-field id="sex" name="sex">
                                                <option value="">Select sex</option>
                                                <option value="Male" <?= selectedText($patient['sex'] ?? '', 'Male') ?>>Male</option>
                                                <option value="Female" <?= selectedText($patient['sex'] ?? '', 'Female') ?>>Female</option>
                                            </select>
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="civil_status">Civil Status</label>
                                            <select class="editable-select" data-record-field id="civil_status" name="civil_status">
                                                <option value="">Select civil status</option>
                                                <option value="Single" <?= selectedText($patient['civil_status'] ?? '', 'Single') ?>>Single</option>
                                                <option value="Married" <?= selectedText($patient['civil_status'] ?? '', 'Married') ?>>Married</option>
                                                <option value="Widowed" <?= selectedText($patient['civil_status'] ?? '', 'Widowed') ?>>Widowed</option>
                                                <option value="Separated" <?= selectedText($patient['civil_status'] ?? '', 'Separated') ?>>Separated</option>
                                            </select>
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="occupation">Occupation</label>
                                            <input class="editable-line" data-record-field id="occupation" type="text" name="occupation" value="<?= e((string) ($patient['occupation'] ?? '')) ?>">
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="emergency_contact_name">Emergency Contact Name</label>
                                            <input class="editable-line" data-record-field id="emergency_contact_name" type="text" name="emergency_contact_name" value="<?= e((string) ($patient['emergency_contact_name'] ?? '')) ?>">
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="emergency_contact_number">Emergency Contact Number</label>
                                            <input class="editable-line" data-record-field id="emergency_contact_number" type="tel" name="emergency_contact_number" value="<?= e((string) ($patient['emergency_contact_number'] ?? '')) ?>">
                                        </div>

                                        <div class="record-item full">
                                            <label class="record-label" for="address">Address</label>
                                            <input class="editable-line" data-record-field id="address" type="text" name="address" value="<?= e((string) ($patient['address'] ?? '')) ?>">
                                        </div>

                                        <div class="record-item full">
                                            <label class="record-label" for="notes">Notes</label>
                                            <textarea class="editable-textarea" data-record-field id="notes" name="notes"><?= e((string) ($patient['notes'] ?? '')) ?></textarea>
                                        </div>
                                    </div>
                                </div>

                              <div class="record-section" id="dental-history-section">
    <h3 class="record-section-title">Dental History</h3>

                                    <div class="record-grid two-col">
                                        <div class="history-question">
                                            <span class="history-question-text">Have you worn any type of denture?</span>
                                            <select class="editable-select" data-record-field name="worn_denture">
                                                <option value="">Select</option>
                                                <option value="yes" <?= selectedAnswer($dentalHistory['worn_denture'] ?? '', 'yes') ?>>Yes</option>
                                                <option value="no" <?= selectedAnswer($dentalHistory['worn_denture'] ?? '', 'no') ?>>No</option>
                                            </select>
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="last_dental_visit">Last Dental Visit</label>
                                            <input class="editable-line" data-record-field id="last_dental_visit" type="date" name="last_dental_visit" value="<?= e((string) ($dentalHistory['last_dental_visit'] ?? '')) ?>">
                                        </div>

                                        <div class="record-item two-full">
                                            <label class="record-label" for="last_dental_visit_reason">Reason for Last Dental Visit</label>
                                            <textarea class="editable-textarea" data-record-field id="last_dental_visit_reason" name="last_dental_visit_reason"><?= e((string) ($dentalHistory['last_dental_visit_reason'] ?? '')) ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="record-section" id="medical-history-section">
    <h3 class="record-section-title">Medical History</h3>

                                    <div class="record-grid two-col">
                                        <div class="history-question full">
                                            <span class="history-question-text">Are you presently under physician’s care?</span>
                                            <select class="editable-select" data-record-field name="under_physician_care">
                                                <option value="">Select</option>
                                                <option value="yes" <?= selectedAnswer($medicalHistory['under_physician_care'] ?? '', 'yes') ?>>Yes</option>
                                                <option value="no" <?= selectedAnswer($medicalHistory['under_physician_care'] ?? '', 'no') ?>>No</option>
                                            </select>
                                        </div>

                                        <div class="record-item two-full">
                                            <label class="record-label" for="physician_care_details">If yes, who or why?</label>
                                            <textarea class="editable-textarea" data-record-field id="physician_care_details" name="physician_care_details"><?= e((string) ($medicalHistory['physician_care_details'] ?? '')) ?></textarea>
                                        </div>

                                        <div class="history-question">
                                            <span class="history-question-text">Are you pregnant?</span>
                                            <select class="editable-select" data-record-field name="is_pregnant">
                                                <option value="">Select</option>
                                                <option value="yes" <?= selectedAnswer($medicalHistory['is_pregnant'] ?? '', 'yes') ?>>Yes</option>
                                                <option value="no" <?= selectedAnswer($medicalHistory['is_pregnant'] ?? '', 'no') ?>>No</option>
                                            </select>
                                        </div>

                                        <div class="history-question">
                                            <span class="history-question-text">Are you taking any medicine at present?</span>
                                            <select class="editable-select" data-record-field name="taking_medicine">
                                                <option value="">Select</option>
                                                <option value="yes" <?= selectedAnswer($medicalHistory['taking_medicine'] ?? '', 'yes') ?>>Yes</option>
                                                <option value="no" <?= selectedAnswer($medicalHistory['taking_medicine'] ?? '', 'no') ?>>No</option>
                                            </select>
                                        </div>

                                        <div class="record-item two-full">
                                            <label class="record-label" for="medicine_details">If yes, what?</label>
                                            <textarea class="editable-textarea" data-record-field id="medicine_details" name="medicine_details"><?= e((string) ($medicalHistory['medicine_details'] ?? '')) ?></textarea>
                                        </div>

                                        <div class="record-item two-full">
                                            <span class="record-label">Have you ever had?</span>
                                        </div>

                                        <div class="condition-check-grid">
                                            <?php
                                            $conditions = [
                                                'condition_high_blood_pressure' => 'High Blood Pressure',
                                                'condition_low_blood_pressure' => 'Low Blood Pressure',
                                                'condition_asthma' => 'Asthma',
                                                'condition_heart_disease' => 'Heart Disease',
                                                'condition_diabetes' => 'Diabetes',
                                                'condition_tuberculosis' => 'Tuberculosis',
                                                'condition_thyroid_problem' => 'Thyroid Problem',
                                                'condition_bleeding_problems' => 'Bleeding Problems',
                                                'condition_hiv_aids' => 'AIDS or HIV Infection',
                                                'condition_hepatitis' => 'Hepatitis',
                                                'condition_others' => 'Others',
                                            ];
                                            ?>

                                            <?php foreach ($conditions as $fieldName => $label): ?>
                                                <label class="condition-check">
                                                    <input data-record-field type="checkbox" name="<?= e($fieldName) ?>" value="1" <?= checkedAttr($medicalHistory[$fieldName] ?? 0) ?>>
                                                    <?= e($label) ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="record-item two-full">
                                            <span class="record-label">Have you ever had allergic reaction to?</span>
                                        </div>

                                        <div class="history-question">
                                            <span class="history-question-text">Local Anesthesia</span>
                                            <select class="editable-select" data-record-field name="allergy_local_anesthesia">
                                                <option value="">Select</option>
                                                <option value="yes" <?= selectedAnswer($medicalHistory['allergy_local_anesthesia'] ?? '', 'yes') ?>>Yes</option>
                                                <option value="no" <?= selectedAnswer($medicalHistory['allergy_local_anesthesia'] ?? '', 'no') ?>>No</option>
                                            </select>
                                        </div>

                                        <div class="history-question">
                                            <span class="history-question-text">Antibiotics</span>
                                            <select class="editable-select" data-record-field name="allergy_antibiotics">
                                                <option value="">Select</option>
                                                <option value="yes" <?= selectedAnswer($medicalHistory['allergy_antibiotics'] ?? '', 'yes') ?>>Yes</option>
                                                <option value="no" <?= selectedAnswer($medicalHistory['allergy_antibiotics'] ?? '', 'no') ?>>No</option>
                                            </select>
                                        </div>

                                        <div class="history-question">
                                            <span class="history-question-text">Pain Killer</span>
                                            <select class="editable-select" data-record-field name="allergy_pain_killer">
                                                <option value="">Select</option>
                                                <option value="yes" <?= selectedAnswer($medicalHistory['allergy_pain_killer'] ?? '', 'yes') ?>>Yes</option>
                                                <option value="no" <?= selectedAnswer($medicalHistory['allergy_pain_killer'] ?? '', 'no') ?>>No</option>
                                            </select>
                                        </div>

                                        <div class="history-question">
                                            <span class="history-question-text">Others</span>
                                            <select class="editable-select" data-record-field name="allergy_others">
                                                <option value="">Select</option>
                                                <option value="yes" <?= selectedAnswer($medicalHistory['allergy_others'] ?? '', 'yes') ?>>Yes</option>
                                                <option value="no" <?= selectedAnswer($medicalHistory['allergy_others'] ?? '', 'no') ?>>No</option>
                                            </select>
                                        </div>

                                        <div class="history-question full">
                                            <span class="history-question-text">Have you been hospitalized?</span>
                                            <select class="editable-select" data-record-field name="hospitalized">
                                                <option value="">Select</option>
                                                <option value="yes" <?= selectedAnswer($medicalHistory['hospitalized'] ?? '', 'yes') ?>>Yes</option>
                                                <option value="no" <?= selectedAnswer($medicalHistory['hospitalized'] ?? '', 'no') ?>>No</option>
                                            </select>
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="hospitalization_when">If yes, when?</label>
                                            <input class="editable-line" data-record-field id="hospitalization_when" type="text" name="hospitalization_when" value="<?= e((string) ($medicalHistory['hospitalization_when'] ?? '')) ?>">
                                        </div>

                                        <div class="record-item">
                                            <label class="record-label" for="hospitalization_why">Why?</label>
                                            <input class="editable-line" data-record-field id="hospitalization_why" type="text" name="hospitalization_why" value="<?= e((string) ($medicalHistory['hospitalization_why'] ?? '')) ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </section>

                <section class="record-panel" data-panel="dental-chart">
                    <div class="record-card">
                        <div class="record-card-header">
                            <div>
                                <h2 class="record-card-title">Dental Chart / Intraoral Examination</h2>
                              
                            </div>
                        </div>

                        <div class="record-card-body">
                            <div class="staff-intraoral-summary">
                                <div class="staff-intraoral-summary-card">
                                    <span class="staff-intraoral-summary-label">Latest Exam Date</span>
                                    <span class="staff-intraoral-summary-value"><?= e($latestExamDate) ?></span>
                                </div>

                                <div class="staff-intraoral-summary-card">
                                    <span class="staff-intraoral-summary-label">Updated By</span>
                                    <span class="staff-intraoral-summary-value"><?= e($latestExamDentist) ?></span>
                                </div>

                                <div class="staff-intraoral-summary-card">
                                    <span class="staff-intraoral-summary-label">Recorded Marks</span>
                                    <span class="staff-intraoral-summary-value"><?= e((string) count($odontogramEntries)) ?> mark(s)</span>
                                </div>

                                <div class="staff-intraoral-summary-card">
                                    <span class="staff-intraoral-summary-label">Notes / Findings</span>
                                    <span class="staff-intraoral-summary-value">
                                        <?= $latestExamNotes !== '' ? e($latestExamNotes) : 'No updated notes recorded.' ?>
                                    </span>
                                </div>
                            </div>

                            <div class="staff-intraoral-chart-wrap">
                                <div class="staff-intraoral-chart-strip" data-staff-intraoral-chart>
                                    <div class="staff-intraoral-upper-row">
                                        <?php foreach ($upperTeeth as $tooth): ?>
                                            <?php
                                                $toothEntries = $odontogramEntriesByTooth[$tooth] ?? $odontogramEntriesByTooth[(string) $tooth] ?? [];
                                                $hasRecord = !empty($toothEntries);
                                                $latestCondition = staffPatientLatestCondition($toothEntries);
                                                $conditionClass = staffPatientConditionClass($latestCondition);
                                                $conditionLabel = staffPatientConditionLabel($latestCondition);
                                                $tooltip = staffPatientChartTooltip($toothEntries);
                                                $imageCandidates = staffPatientToothImageCandidates((int) $tooth, $baseUrl);
                                            ?>

                                            <button
                                                type="button"
                                                class="staff-intraoral-tooth <?= $hasRecord ? 'has-record condition-' . e($conditionClass) : 'condition-none' ?>"
                                                data-staff-intraoral-tooth="<?= (int) $tooth ?>"
                                                data-staff-intraoral-type="<?= e(staffPatientToothTypeLabel((int) $tooth)) ?>"
                                                data-condition-class="<?= e($conditionClass) ?>"
                                                data-condition-label="<?= e($conditionLabel) ?>"
                                                title="<?= e($tooltip) ?>"
                                                aria-label="Tooth <?= (int) $tooth ?>"
                                            >
                                                <span class="staff-intraoral-tooth-image">
                                                    <img
                                                        class="staff-intraoral-tooth-img"
                                                        src="<?= e($imageCandidates[0]) ?>"
                                                        data-src-candidates="<?= e(json_encode($imageCandidates, JSON_UNESCAPED_SLASHES)) ?>"
                                                        alt="Tooth <?= (int) $tooth ?>"
                                                        loading="lazy"
                                                    >
                                                </span>

                                                <span class="staff-intraoral-tooth-number"><?= (int) $tooth ?></span>

                                                <span class="staff-intraoral-dots">
                                                    <?php foreach (array_slice($toothEntries, 0, 4) as $entry): ?>
                                                        <?php
                                                            $entryCondition = (string) ($entry['condition_code'] ?? $entry['procedure_name'] ?? '');
                                                            $entryClass = staffPatientConditionClass($entryCondition);
                                                            $entryLabel = staffPatientConditionLabel($entryCondition);
                                                        ?>
                                                        <i class="staff-intraoral-dot <?= e($entryClass) ?>" title="<?= e($entryLabel) ?>"></i>
                                                    <?php endforeach; ?>
                                                </span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="staff-intraoral-lower-row">
                                        <?php foreach ($lowerTeeth as $tooth): ?>
                                            <?php
                                                $toothEntries = $odontogramEntriesByTooth[$tooth] ?? $odontogramEntriesByTooth[(string) $tooth] ?? [];
                                                $hasRecord = !empty($toothEntries);
                                                $latestCondition = staffPatientLatestCondition($toothEntries);
                                                $conditionClass = staffPatientConditionClass($latestCondition);
                                                $conditionLabel = staffPatientConditionLabel($latestCondition);
                                                $tooltip = staffPatientChartTooltip($toothEntries);
                                                $imageCandidates = staffPatientToothImageCandidates((int) $tooth, $baseUrl);
                                            ?>

                                            <button
                                                type="button"
                                                class="staff-intraoral-tooth <?= $hasRecord ? 'has-record condition-' . e($conditionClass) : 'condition-none' ?>"
                                                data-staff-intraoral-tooth="<?= (int) $tooth ?>"
                                                data-staff-intraoral-type="<?= e(staffPatientToothTypeLabel((int) $tooth)) ?>"
                                                data-condition-class="<?= e($conditionClass) ?>"
                                                data-condition-label="<?= e($conditionLabel) ?>"
                                                title="<?= e($tooltip) ?>"
                                                aria-label="Tooth <?= (int) $tooth ?>"
                                            >
                                                <span class="staff-intraoral-tooth-number"><?= (int) $tooth ?></span>

                                                <span class="staff-intraoral-tooth-image">
                                                    <img
                                                        class="staff-intraoral-tooth-img"
                                                        src="<?= e($imageCandidates[0]) ?>"
                                                        data-src-candidates="<?= e(json_encode($imageCandidates, JSON_UNESCAPED_SLASHES)) ?>"
                                                        alt="Tooth <?= (int) $tooth ?>"
                                                        loading="lazy"
                                                    >
                                                </span>

                                                <span class="staff-intraoral-dots">
                                                    <?php foreach (array_slice($toothEntries, 0, 4) as $entry): ?>
                                                        <?php
                                                            $entryCondition = (string) ($entry['condition_code'] ?? $entry['procedure_name'] ?? '');
                                                            $entryClass = staffPatientConditionClass($entryCondition);
                                                            $entryLabel = staffPatientConditionLabel($entryCondition);
                                                        ?>
                                                        <i class="staff-intraoral-dot <?= e($entryClass) ?>" title="<?= e($entryLabel) ?>"></i>
                                                    <?php endforeach; ?>
                                                </span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="staff-intraoral-selected-box">
                                <div>
                                    Selected tooth:
                                    <strong id="staffIntraoralSelectedTooth">None</strong>
                                    <span id="staffIntraoralSelectedType">—</span>
                                </div>

                                <div class="staff-intraoral-selected-meta">
                                    <span>Condition:</span>
                                    <span id="staffIntraoralSelectedCondition" class="staff-intraoral-condition-chip none">
                                        No record
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="record-panel" data-panel="appointment-history">
                    <div class="record-card">
                        <div class="record-card-header">
                            <div>
                                <h2 class="record-card-title">Appointment History</h2>
                           
                            </div>
                        </div>

                        <div class="record-card-body">
                            <?php if (empty($appointments)): ?>
                                <div class="empty-state">No appointment history found.</div>
                            <?php else: ?>
                                <div class="appointment-wrap">
                                    <table class="appointment-table">
                                        <thead>
                                            <tr>
                                                <th>Appointment Code</th>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Service</th>
                                                <th>Dentist</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <?php foreach ($appointments as $appointment): ?>
                                                <?php
                                                    $appointmentStatus = (string) ($appointment['status'] ?? '');
                                                    $dentistName = trim((string) (($appointment['dentist_first_name'] ?? '') . ' ' . ($appointment['dentist_last_name'] ?? '')));
                                                ?>

                                                <tr>
                                                    <td><?= displayValue($appointment['appointment_code'] ?? '') ?></td>
                                                    <td><?= formatDateText($appointment['appointment_date'] ?? '') ?></td>
                                                    <td><?= formatAppointmentTime($appointment['start_time'] ?? '', $appointment['end_time'] ?? '') ?></td>
                                                    <td><?= e(appointmentServiceDisplay($appointment)) ?></td>
                                                    <td><?= displayValue($dentistName !== '' ? 'Dr. ' . $dentistName : '') ?></td>
                                                    <td>
                                                        <span class="status-badge <?= e($appointmentStatus) ?>">
                                                            <?= e(statusText($appointmentStatus)) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('[data-tab]');
    const toolbarTabs = document.querySelectorAll('[data-toolbar-tab]');
    const panels = document.querySelectorAll('[data-panel]');

    const form = document.getElementById('patientRecordForm');
    const editBtn = document.getElementById('editRecordBtn');
    const saveBtn = document.getElementById('saveRecordBtn');
    const cancelBtn = document.getElementById('cancelRecordBtn');
    const historyJumpLinks = document.querySelectorAll('[data-history-jump]');

    function activatePanel(target) {
        panels.forEach(function (panel) {
            panel.classList.remove('active');
        });

        tabs.forEach(function (tab) {
            tab.classList.toggle('active', tab.getAttribute('data-tab') === target);
        });

        toolbarTabs.forEach(function (toolbarButton) {
            toolbarButton.classList.toggle('active', toolbarButton.getAttribute('data-toolbar-tab') === target);
        });

        const panel = document.querySelector('[data-panel="' + target + '"]');

        if (panel) {
            panel.classList.add('active');
        }
    }

    function setReadonly(isReadonly) {
        if (!form) {
            return;
        }

        form.classList.toggle('is-readonly', isReadonly);

        form.querySelectorAll('[data-record-field]').forEach(function (field) {
            field.disabled = isReadonly;
        });

        if (editBtn) {
            editBtn.hidden = !isReadonly;
        }

        if (saveBtn) {
            saveBtn.hidden = isReadonly;
        }

        if (cancelBtn) {
            cancelBtn.hidden = isReadonly;
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const target = tab.getAttribute('data-tab');

            if (target) {
                activatePanel(target);
            }
        });
    });

    toolbarTabs.forEach(function (toolbarButton) {
        toolbarButton.addEventListener('click', function () {
            const target = toolbarButton.getAttribute('data-toolbar-tab');

            if (target) {
                activatePanel(target);
            }
        });
    });

    setReadonly(true);

    historyJumpLinks.forEach(function (link) {
    link.addEventListener('click', function (event) {
        event.preventDefault();

        const sectionId = link.getAttribute('data-history-jump');

        if (sectionId) {
            jumpToHistorySection(sectionId);
        }
    });
});

    if (editBtn) {
        editBtn.addEventListener('click', function () {
            activatePanel('patient-record');
            setReadonly(false);

            const firstField = form.querySelector('[data-record-field]:not(:disabled)');

            if (firstField) {
                window.setTimeout(function () {
                    firstField.focus();
                }, 80);
            }
        });
    }


    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            form.reset();
            setReadonly(true);
        });
    }

    if (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('[data-record-field]').forEach(function (field) {
                field.disabled = false;
            });
        });
    }

    document.querySelectorAll('.staff-intraoral-tooth-img[data-src-candidates]').forEach(function (img) {
        let candidates = [];

        try {
            candidates = JSON.parse(img.getAttribute('data-src-candidates') || '[]');
        } catch (error) {
            candidates = [];
        }

        if (!candidates.length) {
            return;
        }

        let index = 0;

        function tryNextImage() {
            if (index >= candidates.length) {
                const fallback = document.createElement('span');
                fallback.className = 'staff-intraoral-missing-image';
                fallback.textContent = 'Missing';
                fallback.title = 'Tooth image not found.';
                img.replaceWith(fallback);
                return;
            }

            img.src = candidates[index];
            index++;
        }

        img.onerror = tryNextImage;
        tryNextImage();
    });



    function jumpToHistorySection(sectionId) {
    activatePanel('patient-record');
    setReadonly(false);

    const section = document.getElementById(sectionId);

    if (!section) {
        return;
    }

    window.setTimeout(function () {
        section.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

        const firstField = section.querySelector('[data-record-field]:not(:disabled)');

        if (firstField) {
            window.setTimeout(function () {
                firstField.focus();
            }, 350);
        }
    }, 80);
}

    const chart = document.querySelector('[data-staff-intraoral-chart]');
    const selectedTooth = document.getElementById('staffIntraoralSelectedTooth');
    const selectedType = document.getElementById('staffIntraoralSelectedType');
    const selectedCondition = document.getElementById('staffIntraoralSelectedCondition');

    if (chart && selectedTooth && selectedType && selectedCondition) {
        const toothButtons = chart.querySelectorAll('[data-staff-intraoral-tooth]');

        toothButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                toothButtons.forEach(function (item) {
                    item.classList.remove('is-selected');
                });

                button.classList.add('is-selected');

                selectedTooth.textContent = button.getAttribute('data-staff-intraoral-tooth') || 'None';
                selectedType.textContent = button.getAttribute('data-staff-intraoral-type') || '—';
                selectedCondition.textContent = button.getAttribute('data-condition-label') || 'No record';
            });
        });
    }
});
</script>

<?php
$staffContent = ob_get_clean();
$pageTitle = 'Patient Details';
require __DIR__ . '/../layouts/app.php';
?>