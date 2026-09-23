<?php

use App\Core\Csrf;

$pageTitle = 'Patient Record';
$baseUrl = '/DentalClinic/public';

$patient = isset($patient) && is_array($patient) ? $patient : [];
$medicalHistory = isset($medicalHistory) && is_array($medicalHistory) ? $medicalHistory : [];
$dentalHistory = isset($dentalHistory) && is_array($dentalHistory) ? $dentalHistory : [];
$appointments = isset($appointments) && is_array($appointments) ? $appointments : [];
$treatments = isset($treatments) && is_array($treatments) ? $treatments : [];
$odontogramEntries = isset($odontogramEntries) && is_array($odontogramEntries) ? $odontogramEntries : [];
$odontogramEntriesByTooth = isset($odontogramEntriesByTooth) && is_array($odontogramEntriesByTooth) ? $odontogramEntriesByTooth : [];
$documents = isset($documents) && is_array($documents) ? $documents : [];

$documentCount = isset($documentCount)
    ? (int) $documentCount
    : count($documents);

$latestDocument = isset($latestDocument) && is_array($latestDocument)
    ? $latestDocument
    : ($documents[0] ?? null);

$totalDocuments = $documentCount;

$latestDocumentId = is_array($latestDocument)
    ? (int) ($latestDocument['attachment_id'] ?? 0)
    : 0;

$latestDocumentPreviewUrl = $latestDocumentId > 0
    ? $baseUrl . '/dentist/patients/document?id=' . $latestDocumentId
    : '';

$openedFromStartNow = (string) ($_GET['from_start'] ?? '') === '1';
$requestedAppointmentId = (int) ($_GET['appointment_id'] ?? 0);

$flash_success = $flash_success ?? null;
$flash_error = $flash_error ?? null;

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
    $birthTimestamp = strtotime((string) $patient['birth_date']);
    if ($birthTimestamp !== false) {
        $today = new DateTime();
        $birth = new DateTime((string) $patient['birth_date']);
        $patientAge = (string) $today->diff($birth)->y;
    }
}

if (!function_exists('e')) {
    function e($value): string
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

if (!function_exists('formatMoneyText')) {
    function formatMoneyText($amount): string
    {
        return number_format((float) ($amount ?? 0), 2, '.', ',');
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
$totalTreatments = count($treatments);


$patientIdValue = (int) ($patient['patient_id'] ?? 0);
$canOpenDentalChart = $patientIdValue > 0;
$saveTreatmentUrl = $baseUrl . '/dentist/patients/save-treatment';
$updateTreatmentUrl = $baseUrl . '/dentist/patients/update-treatment';

$addProcedureUrl = '';

$upperTeeth = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
$lowerTeeth = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];

$primaryUpperTeeth = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];
$primaryLowerTeeth = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];

$latestAppointmentId = 0;

foreach ($appointments as $appointmentItem) {
    $candidateAppointmentId = (int) ($appointmentItem['appointment_id'] ?? 0);

    if ($candidateAppointmentId > 0) {
        $latestAppointmentId = $candidateAppointmentId;
        break;
    }
}
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


if (!function_exists('patientRecordToothImageFilename')) {
    function patientRecordToothImageFilename(int $toothNumber): string
    {
        $upperTeeth = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];

        $prefix = in_array($toothNumber, $upperTeeth, true)
            ? 'dentadura-sup-'
            : 'dentadura-inf-';

        return $prefix . $toothNumber . '.png';
    }
}

if (!function_exists('patientRecordToothImageCandidates')) {
    function patientRecordToothImageCandidates(int $toothNumber, string $baseUrl): array
    {
        $filename = patientRecordToothImageFilename($toothNumber);

        return [
            $baseUrl . '/assets/images/' . $filename,
            $baseUrl . '/assets/images/teeth/' . $filename,
            $baseUrl . '/images/' . $filename,
            '/DentalClinic/public/assets/images/' . $filename,
            '/DentalClinic/public/assets/images/teeth/' . $filename,
            '/assets/images/' . $filename,
            '/assets/images/teeth/' . $filename,
        ];
    }
}

if (!function_exists('patientRecordToothTypeLabel')) {
    function patientRecordToothTypeLabel(int $toothNumber): string
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

if (!function_exists('patientRecordChartStatusClass')) {
    function patientRecordChartStatusClass(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'performed' => 'performed',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'in_progress' => 'in-progress',
            default => 'planned',
        };
    }
}

if (!function_exists('patientRecordChartTooltip')) {
    function patientRecordChartTooltip(array $items): string
    {
        if (empty($items)) {
            return 'No intraoral examination record.';
        }

        $lines = [];

        foreach ($items as $item) {
            $procedure = trim((string) ($item['procedure_name'] ?? $item['condition_code'] ?? ''));
            $surface = trim((string) ($item['surface'] ?? ''));
            $status = statusText((string) ($item['status'] ?? ''));
            $notes = trim((string) ($item['notes'] ?? $item['remarks'] ?? ''));

            $line = trim(
                $procedure .
                ($surface !== '' ? ' - ' . $surface : '') .
                ($status !== 'N/A' ? ' - ' . $status : '')
            );

            if ($notes !== '') {
                $line .= ' | ' . $notes;
            }

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return !empty($lines) ? implode("\n", $lines) : 'No intraoral examination record.';
    }

    if (!function_exists('patientRecordLatestCondition')) {
    function patientRecordLatestCondition(array $items): string
    {
        if (empty($items)) {
            return '';
        }

        $first = $items[0] ?? [];

        return trim((string) ($first['condition_code'] ?? $first['procedure_name'] ?? ''));
    }
}

if (!function_exists('patientRecordConditionClass')) {
    function patientRecordConditionClass(string $condition): string
    {
        $condition = strtolower(trim($condition));

        return match ($condition) {
            'caries' => 'caries',
            'missing' => 'missing',
            'restoration' => 'restoration',
            'fractured' => 'fractured',
            'root canal treated' => 'root-canal',
            'impacted' => 'impacted',
            'for extraction' => 'extraction',
            'crown' => 'crown',
            'pontic' => 'pontic',
            'sealant' => 'sealant',
            'sound' => 'sound',
            default => $condition !== '' ? 'other' : 'none',
        };
    }
}
}

$latestAppointmentId = 0;

foreach ($appointments as $appointmentItem) {
    $candidateAppointmentId = (int) ($appointmentItem['appointment_id'] ?? 0);

    if ($candidateAppointmentId > 0) {
        $latestAppointmentId = $candidateAppointmentId;
        break;
    }
}


if (!function_exists('patientRecordLatestCondition')) {
    function patientRecordLatestCondition(array $items): string
    {
        if (empty($items)) {
            return '';
        }

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

if (!function_exists('patientRecordConditionClass')) {
    function patientRecordConditionClass(string $condition): string
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

if (!function_exists('patientRecordConditionLabel')) {
    function patientRecordConditionLabel(string $condition): string
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

if (!function_exists('patientDocumentCategoryLabel')) {
    function patientDocumentCategoryLabel(string $category): string
    {
        $labels = [
            'xray' => 'X-ray',
            'consent_form' => 'Consent Form',
            'prescription' => 'Prescription',
            'referral' => 'Referral Letter',
            'medical_certificate' => 'Medical Certificate',
            'lab_result' => 'Lab Result',
            'treatment_photo' => 'Treatment Photo',
            'other' => 'Other Document',
        ];

        return $labels[$category] ?? 'Other Document';
    }
}

if (!function_exists('patientDocumentSize')) {
    function patientDocumentSize($bytes): string
    {
        $bytes = (int) $bytes;

        if ($bytes <= 0) {
            return '—';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        return number_format($bytes / 1024, 2) . ' KB';
    }
}

if (!function_exists('patientDocumentUploaderName')) {
    function patientDocumentUploaderName(array $row): string
    {
        $name = trim(implode(' ', array_filter([
            $row['uploaded_by_first_name'] ?? '',
            $row['uploaded_by_middle_name'] ?? '',
            $row['uploaded_by_last_name'] ?? '',
        ])));

        return $name !== '' ? $name : 'Clinic User';
    }
}

if (!function_exists('patientDocumentPreviewable')) {
    function patientDocumentPreviewable(array $document): bool
    {
        $mime = (string) ($document['mime_type'] ?? '');

        return $mime === 'application/pdf' || str_starts_with($mime, 'image/');
    }
}

if (!function_exists('formatDateTimeText')) {
    function formatDateTimeText($value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return '—';
        }

        $timestamp = strtotime($text);

        return $timestamp ? e(date('M d, Y, h:i A', $timestamp)) : e($text);
    }
}

if (!function_exists('formatProcedureDurationText')) {
    function formatProcedureDurationText($startedAt, $completedAt): string
    {
        $startedText = trim((string) $startedAt);
        $completedText = trim((string) $completedAt);

        if (
            $startedText === '' ||
            $completedText === '' ||
            strtotime($startedText) === false ||
            strtotime($completedText) === false
        ) {
            return '—';
        }

        $seconds = strtotime($completedText) - strtotime($startedText);

        if ($seconds < 0) {
            return '—';
        }

        $minutes = (int) floor($seconds / 60);
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return e($hours . ' hr ' . $remainingMinutes . ' min');
        }

        if ($hours > 0) {
            return e($hours . ' hr');
        }

        return e($minutes . ' min');
    }
}

if (!function_exists('formatScheduledAppointmentText')) {
    function formatScheduledAppointmentText(array $appointment): string
    {
        $date = trim((string) ($appointment['appointment_date'] ?? ''));
        $start = trim((string) ($appointment['start_time'] ?? ''));
        $end = trim((string) ($appointment['end_time'] ?? ''));

        if ($date === '') {
            return '—';
        }

        $dateLabel = strtotime($date) ? date('M d, Y', strtotime($date)) : $date;
        $startLabel = $start !== '' && strtotime($start) ? date('h:i A', strtotime($start)) : $start;
        $endLabel = $end !== '' && strtotime($end) ? date('h:i A', strtotime($end)) : $end;

        $time = trim($startLabel . ($endLabel !== '' ? ' - ' . $endLabel : ''));

        return e($dateLabel . ($time !== '' ? ', ' . $time : ''));
    }
}


if (!function_exists('formatActualDateTimeText')) {
    function formatActualDateTimeText($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        $time = strtotime($value);

        return $time ? e(date('M d, Y, h:i A', $time)) : e($value);
    }
}

if (!function_exists('formatActualTimeOnly')) {
    function formatActualTimeOnly($value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        $time = strtotime($value);

        return $time ? e(date('h:i A', $time)) : e($value);
    }
}

if (!function_exists('formatActualDurationText')) {
    function formatActualDurationText($startedAt, $completedAt): string
    {
        $startedAt = trim((string) $startedAt);
        $completedAt = trim((string) $completedAt);

        if (
            $startedAt === '' ||
            $completedAt === '' ||
            strtotime($startedAt) === false ||
            strtotime($completedAt) === false
        ) {
            return '—';
        }

        $seconds = strtotime($completedAt) - strtotime($startedAt);

        if ($seconds < 0) {
            return '—';
        }

        $minutes = (int) floor($seconds / 60);
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return e($hours . ' hr ' . $remainingMinutes . ' min');
        }

        if ($hours > 0) {
            return e($hours . ' hr');
        }

        return e($minutes . ' min');
    }
}

if (!function_exists('formatScheduledDateTimeText')) {
    function formatScheduledDateTimeText(array $appointment): string
    {
        $date = trim((string) ($appointment['appointment_date'] ?? ''));
        $start = trim((string) ($appointment['start_time'] ?? ''));
        $end = trim((string) ($appointment['end_time'] ?? ''));

        if ($date === '') {
            return '—';
        }

        $dateLabel = strtotime($date) ? date('M d, Y', strtotime($date)) : $date;
        $startLabel = $start !== '' && strtotime($start) ? date('h:i A', strtotime($start)) : $start;
        $endLabel = $end !== '' && strtotime($end) ? date('h:i A', strtotime($end)) : $end;

        return e($dateLabel . ', ' . $startLabel . ($endLabel !== '' ? ' - ' . $endLabel : ''));
    }
}

if (!function_exists('renderActualProcedureBox')) {
    function renderActualProcedureBox(array $appointment): string
    {
        $startedAt = $appointment['actual_started_at'] ?? '';
        $completedAt = $appointment['actual_completed_at'] ?? '';

        $started = formatActualTimeOnly($startedAt);
        $completed = formatActualTimeOnly($completedAt);
        $duration = formatActualDurationText($startedAt, $completedAt);

        return '
            <div class="actual-procedure-box">
                <div><strong>Started:</strong> ' . $started . '</div>
                <div><strong>Completed:</strong> ' . $completed . '</div>
                <div><strong>Duration:</strong> ' . $duration . '</div>
            </div>
        ';
    }
}



ob_start();
?>

<style>

    .add-procedure-btn.pulse-highlight {
    border-color: #15803d;
    color: #15803d;
    background: #f0fdf4;
    animation: treatmentPulse 1.1s ease-in-out 4;
    box-shadow: 0 0 0 0 rgba(21, 128, 61, 0.35);
}

@keyframes treatmentPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(21, 128, 61, 0.35);
        transform: translateY(0);
    }

    50% {
        box-shadow: 0 0 0 8px rgba(21, 128, 61, 0);
        transform: translateY(-1px);
    }

    100% {
        box-shadow: 0 0 0 0 rgba(21, 128, 61, 0);
        transform: translateY(0);
    }
}

@media (max-width: 760px) {
    .record-top {
        padding: 64px 14px 0;
    }

    .record-main,
    .flash-wrap,
    .print-footer {
        padding-left: 10px;
        padding-right: 10px;
    }

    .record-card-header {
        padding: 14px;
    }

    .record-card-body {
        padding: 12px;
    }

    .treatment-toolbar {
        justify-content: stretch;
    }

    .treatment-add-procedure-wrap,
    .add-procedure-btn {
        width: 100%;
    }

    .appointment-wrap {
        overflow-x: auto;
        border: 1px solid #e5e7eb;
    }

    .treatment-record-table {
        min-width: 980px;
    }

    .odontogram-popup-backdrop {
        padding: 10px;
    }

    .odontogram-popup {
        width: 100%;
        max-height: calc(100dvh - 20px);
    }

    .odontogram-popup-body {
        grid-template-columns: 1fr;
        padding: 10px;
    }

    .odontogram-popup-strip {
        min-width: 760px;
        padding: 18px 14px;
    }

    .odo-popup-row {
        grid-template-columns: repeat(8, 48px);
    }

    .odo-popup-upper {
        margin-bottom: 22px;
    }
}
.dentist-record-page {
    min-height: calc(100dvh - 74px);
    background: #f3f3f3;
    color: #111827;
    padding: 0 0 40px;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

.record-shell {
    width: 100%;
    max-width: none;
    margin: 0;
}

.record-paper {
    width: 100%;
    background: transparent;
    padding: 0;
    box-sizing: border-box;
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
    min-width: 142px;
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
    flex: 0 0 auto;
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

.patient-toolbar-money {
    color: #7f1d1d;
    font-size: 12px;
    font-weight: 800;
}

.record-top {
    width: 100%;
    background: #ffffff;
    border: 0;
    border-bottom: 1px solid #d9d9d9;
    padding: 82px 56px 0;
    margin: 0;
    box-shadow: none;
    box-sizing: border-box;
}

.patient-profile-header {
    width: 100%;
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
    transition: background 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
}

.record-back:hover {
    background: #f3f4f6;
    border-color: #cfcfcf;
    transform: translateX(-1px);
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

.patient-profile-main {
    width: 100%;
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
    grid-template-columns: minmax(190px, 2fr) minmax(190px, 2fr);
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
    transition: background 0.16s ease, color 0.16s ease;
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

.record-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 20px 20px 16px;
    border-bottom: 1px solid #eeeeee;
    margin-bottom: 0;
}

.record-card-title {
    margin: 0;
    font-size: 21px;
    color: #111827;
    font-weight: 900;
    letter-spacing: 0.04em;
}

.record-card-body {
    padding: 16px 20px 20px;
}

.record-section {
    margin-bottom: 20px;
    transition: background 0.22s ease, opacity 0.22s ease;
}

.record-section:last-child {
    margin-bottom: 0;
}

.record-section-title {
    margin: 0;
    font-size: 15px;
    font-weight: 900;
    color: #111827;
}

.form-action-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}

.record-action-buttons {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.save-record-btn,
.cancel-edit-btn {
    width: 38px;
    min-width: 38px;
    height: 38px;
    min-height: 38px;
    padding: 0;
    border: 1px solid #111827;
    background: #ffffff;
    color: #111827;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition:
        background 0.18s ease,
        border-color 0.18s ease,
        color 0.18s ease,
        transform 0.18s ease,
        opacity 0.22s ease,
        visibility 0.22s ease;
}

.save-record-btn:hover {
    background: #f3f4f6;
}

.save-record-btn svg,
.cancel-edit-btn svg {
    width: 17px;
    height: 17px;
    stroke: currentColor;
    stroke-width: 2.2;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.save-record-btn .icon-save {
    display: none;
}

.record-section.is-editing .save-record-btn {
    background: #111827;
    border-color: #111827;
    color: #ffffff;
    transform: translateY(-1px);
}

.record-section.is-editing .save-record-btn:hover {
    background: #15803d;
    border-color: #15803d;
}

.record-section.is-editing .save-record-btn .icon-edit {
    display: none;
}

.record-section.is-editing .save-record-btn .icon-save {
    display: inline-flex;
}

.cancel-edit-btn {
    border-color: #d1d5db;
    color: #991b1b;
    opacity: 0;
    visibility: hidden;
    transform: translateX(8px);
    pointer-events: none;
}

.cancel-edit-btn:hover {
    background: #fef2f2;
    border-color: #fecaca;
    color: #b91c1c;
}

.record-section.is-editing .cancel-edit-btn {
    opacity: 1;
    visibility: visible;
    transform: translateX(0);
    pointer-events: auto;
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

.record-item.full {
    grid-column: span 3;
}

.record-item.two-full {
    grid-column: span 2;
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
    word-break: break-word;
    overflow-wrap: anywhere;
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
    transition:
        border-color 0.2s ease,
        background 0.2s ease,
        color 0.2s ease,
        opacity 0.2s ease;
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
    background: #ffffff;
}

.record-section:not(.is-editing) .editable-line,
.record-section:not(.is-editing) .editable-select,
.record-section:not(.is-editing) .editable-textarea {
    background: #fafafa;
    color: #374151;
    border-color: #e5e7eb;
    cursor: default;
}

.condition-check-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px 12px;
    padding: 4px 0;
    grid-column: span 2;
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

.record-section.is-editing .condition-check {
    background: #ffffff;
    border-color: #d9d9d9;
}

.record-section:not(.is-editing) .condition-check {
    background: #fafafa;
    color: #374151;
    opacity: 0.9;
}

.history-question {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 150px;
    gap: 12px;
    align-items: end;
}

.history-question.full {
    grid-column: span 2;
}

.history-question-text {
    font-size: 13px;
    font-weight: 700;
    color: #111827;
    line-height: 1.45;
}

.patient-chart-box,
.clinical-help-box,
.empty-state {
    border: 1px dashed #d1d5db;
    background: #fafafa;
    color: #6b7280;
    box-sizing: border-box;
}

.patient-chart-box {
    min-height: 420px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 800;
    text-align: center;
    padding: 20px;
}

.clinical-help-box {
    min-height: 220px;
    display: grid;
    align-content: center;
    justify-items: center;
    gap: 10px;
    text-align: center;
    padding: 24px;
}

.clinical-help-title {
    margin: 0;
    color: #111827;
    font-size: 18px;
    font-weight: 900;
}

.clinical-help-copy {
    max-width: 620px;
    margin: 0;
    color: #374151;
    font-size: 13px;
    line-height: 1.6;
}

.empty-state {
    padding: 18px;
    font-size: 13px;
    text-align: center;
}

.appointment-wrap {
    overflow-x: auto;
}

.appointment-table,
.treatment-record-table {
    width: 100%;
    min-width: 980px;
    border-collapse: collapse;
    background: #ffffff;
}

.treatment-record-table {
    min-width: 1120px;
}

.appointment-table th,
.appointment-table td,
.treatment-record-table th,
.treatment-record-table td {
    border-bottom: 1px solid #eeeeee;
    padding: 14px;
    text-align: left;
    vertical-align: middle;
}

.appointment-table th,
.treatment-record-table th {
    font-size: 12px;
    color: #111827;
    background: #fafafa;
    white-space: nowrap;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.treatment-record-table th {
    background: #bdbdbd;
    color: #222222;
    border-right: 1px solid #d8d8d8;
}

.appointment-table td,
.treatment-record-table td {
    font-size: 13px;
    color: #111827;
}

.treatment-record-table td {
    border-right: 1px solid #f1f5f9;
}

.appointment-table tbody tr,
.treatment-record-table tbody tr {
    transition: background 0.16s ease;
}

.appointment-table tbody tr:hover,
.treatment-record-table tbody tr:hover {
    background: #fafafa;
}

.status-badge,
.treatment-status {
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
.status-badge.completed,
.treatment-status.completed,
.treatment-status.performed {
    border-color: #bbf7d0;
    background: #dcfce7;
    color: #166534;
}

.status-badge.no_show,
.status-badge.cancelled,
.status-badge.rejected,
.treatment-status.cancelled {
    border-color: #fecaca;
    background: #fee2e2;
    color: #b91c1c;
}

.patient-toolbar-item.is-disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

.document-layout {
    display: grid;
    grid-template-columns: 360px minmax(0, 1fr);
    gap: 16px;
}

.document-upload-card,
.document-preview-card,
.document-list-card {
    border: 1px solid #e5e7eb;
    background: #ffffff;
    padding: 14px;
}

.document-form-grid {
    display: grid;
    gap: 12px;
}

.document-form-grid label {
    display: block;
    margin-bottom: 6px;
    color: #111827;
    font-size: 12px;
    font-weight: 900;
}

.document-form-grid input,
.document-form-grid select,
.document-form-grid textarea {
    width: 100%;
    min-height: 38px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    padding: 8px 10px;
    font-size: 13px;
    font-weight: 700;
    box-sizing: border-box;
    outline: none;
}

.document-form-grid textarea {
    min-height: 78px;
    resize: vertical;
    font-family: Arial, sans-serif;
}

.document-form-grid input:focus,
.document-form-grid select:focus,
.document-form-grid textarea:focus {
    border-color: #0f766e;
}

.document-preview-frame {
    width: 100%;
    min-height: 480px;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
}

.document-preview-empty {
    min-height: 220px;
    display: grid;
    place-items: center;
    border: 1px dashed #d1d5db;
    background: #fafafa;
    color: #6b7280;
    font-size: 13px;
    font-weight: 800;
    text-align: center;
    padding: 18px;
}

.document-actions {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}

.document-danger-btn {
    min-height: 34px;
    padding: 0 10px;
    border: 1px solid #fecaca;
    background: #ffffff;
    color: #991b1b;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
}

.document-danger-btn:hover {
    background: #fef2f2;
}

@media (max-width: 980px) {
    .document-layout {
        grid-template-columns: 1fr;
    }
}

.status-badge.rescheduled,
.status-badge.pending,
.treatment-status.planned {
    border-color: #fde68a;
    background: #fef3c7;
    color: #92400e;
}

.money-value {
    color: #111827;
    font-weight: 900;
    white-space: nowrap;
}

.table-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid #111827;
    background: #111827;
    color: #ffffff;
    text-decoration: none;
    font-size: 12px;
    font-weight: 900;
    white-space: nowrap;
    transition: background 0.16s ease, border-color 0.16s ease, color 0.16s ease;
}

.table-action:hover {
    background: #15803d;
    border-color: #15803d;
}

.print-footer {
    margin-top: 12px;
    padding: 0 56px;
    display: flex;
    justify-content: flex-end;
}

.print-page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 150px;
    min-height: 38px;
    border: 1px solid #15803d;
    background: #ffffff;
    color: #15803d;
    padding: 0 12px;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
    transition: background 0.16s ease, color 0.16s ease, border-color 0.16s ease;
}

.print-page-btn:hover {
    background: #f0fdf4;
}

.treatment-add-procedure-wrap {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 6px 0 26px;
}

.treatment-add-procedure-wrap::before {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    height: 1px;
    background: #d9d9d9;
}

.add-procedure-btn {
    width: 100%;
    position: relative;
    z-index: 1;
    min-height: 38px;
    padding: 0 28px;
    border: 1px dashed #bfc4cc;
    border-radius: 999px;
    background: #ffffff;
    color: #1d2c8f;
    font-size: 14px;
    font-weight: 1000;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    box-shadow: 0 1px 4px rgba(17, 24, 39, 0.08);
}

.add-procedure-btn:hover {
    background: #f8fafc;
    border-color: #0f766e;
    color: #0f766e;
}

.add-procedure-btn.is-disabled {
    color: #9ca3af;
    border-color: #d1d5db;
    pointer-events: none;
    cursor: not-allowed;
}

.add-procedure-plus {
    font-size: 18px;
    line-height: 1;
    font-weight: 1000;
}

.intraoral-exam-header-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    margin-bottom: 14px;
}

.intraoral-open-chart-btn {
    min-height: 36px;
    padding: 0 14px;
    border: 1px solid #0f766e;
    background: #ffffff;
    color: #0f766e;
    text-decoration: none;
    font-size: 12px;
    font-weight: 900;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.intraoral-open-chart-btn:hover {
    background: #f0fdfa;
}

.intraoral-open-chart-btn.is-disabled {
    color: #9ca3af;
    border-color: #d1d5db;
    pointer-events: none;
}

.intraoral-chart-wrap {
    width: 100%;
    overflow-x: auto;
    padding: 18px 0;
    background: #ffffff;
    border: 1px solid #eeeeee;
}

.intraoral-chart-strip {
    width: fit-content;
    min-width: 1120px;
    margin: 0 auto;
    padding: 0 18px;
    user-select: none;
}

.intraoral-upper-row,
.intraoral-lower-row {
    display: grid;
    grid-template-columns: repeat(16, 48px);
    gap: 24px;
    align-items: end;
}

.intraoral-upper-row {
    margin-bottom: 14px;
}

.intraoral-lower-row {
    align-items: start;
}

.intraoral-tooth {
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
    transition: background 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
}

.intraoral-lower-row .intraoral-tooth {
    grid-template-rows: 18px 68px 10px;
}

.intraoral-tooth:hover,
.intraoral-tooth.is-selected {
    background: #f0fdfa;
    border-color: #0f766e;
}

.intraoral-tooth.is-selected {
    box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.10);
}

.intraoral-tooth-image {
    width: 44px;
    height: 68px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.intraoral-tooth-image img {
    max-width: 44px;
    max-height: 68px;
    object-fit: contain;
    display: block;
    pointer-events: none;
}

.intraoral-tooth-number {
    color: #8c9198;
    font-size: 13px;
    font-weight: 900;
    line-height: 1;
}

.intraoral-tooth.is-selected .intraoral-tooth-number {
    color: #0f766e;
}

.intraoral-tooth-dots {
    min-height: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.intraoral-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    display: inline-block;
    background: #0f8b8d;
}

.intraoral-dot.planned,
.intraoral-dot.in-progress {
    background: #d97706;
}

.intraoral-dot.performed {
    background: #0891b2;
}

.intraoral-dot.completed {
    background: #0f8b5f;
}

.intraoral-dot.cancelled {
    background: #dc2626;
}

.intraoral-selected-box {
    margin-top: 14px;
    padding: 12px 14px;
    border: 1px solid #e5e7eb;
    background: #fafafa;
    color: #374151;
    font-size: 13px;
    font-weight: 800;
}

.intraoral-selected-box strong {
    color: #0f766e;
}

.intraoral-missing-image {
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

body.odontogram-modal-open {
    overflow: hidden;
}

.full-odontogram-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(17, 24, 39, 0.45);
    backdrop-filter: blur(7px);
    -webkit-backdrop-filter: blur(7px);
}

.full-odontogram-backdrop.is-open {
    display: flex;
}

.full-odontogram-modal {
    width: min(1180px, 100%);
    max-height: calc(100dvh - 40px);
    overflow: auto;
    background: #ffffff;
    border: 1px solid #d1d5db;
    box-shadow: 0 24px 70px rgba(17, 24, 39, 0.25);
}

.full-odontogram-header {
    position: sticky;
    top: 0;
    z-index: 2;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
}

.full-odontogram-title {
    margin: 0;
    color: #111827;
    font-size: 20px;
    font-weight: 900;
}

.full-odontogram-subtitle {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 12px;
    font-weight: 700;
}

.full-odontogram-close {
    width: 38px;
    height: 38px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    font-size: 22px;
    font-weight: 900;
    cursor: pointer;
}

.full-odontogram-close:hover {
    background: #f9fafb;
}

.full-odontogram-body {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 360px;
    gap: 18px;
    padding: 18px;
}

.full-odontogram-chart,
.full-odontogram-form-panel {
    border: 1px solid #e5e7eb;
    background: #ffffff;
}

.full-odontogram-section {
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
}

.full-odontogram-section:last-child {
    border-bottom: 0;
}

.full-odontogram-section-title {
    margin: 0 0 10px;
    color: #111827;
    font-size: 14px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.full-odontogram-help {
    margin: 0 0 12px;
    color: #6b7280;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.5;
}

.odo-board {
    display: grid;
    gap: 8px;
}

.odo-label-row,
.odo-tooth-row {
    display: grid;
    align-items: center;
    justify-items: center;
    gap: 8px;
}

.odo-label-row.permanent,
.odo-tooth-row.permanent {
    grid-template-columns: repeat(16, minmax(42px, 1fr));
}

.odo-label-row.primary,
.odo-tooth-row.primary {
    grid-template-columns: repeat(10, minmax(42px, 1fr));
}

.odo-number {
    color: #4b5563;
    font-size: 12px;
    font-weight: 900;
}

.odo-tooth-button {
    position: relative;
    width: 100%;
    min-height: 52px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    cursor: pointer;
    font-size: 13px;
    font-weight: 900;
    transition: background 0.16s ease, border-color 0.16s ease, transform 0.16s ease;
}

.odo-tooth-button:hover {
    background: #f0fdfa;
    border-color: #0f766e;
}

.odo-tooth-button.is-active {
    background: #ccfbf1;
    border-color: #0f766e;
    color: #0f766e;
    transform: translateY(-1px);
}

.odo-tooth-button.has-entry::after {
    content: "";
    position: absolute;
    top: 6px;
    right: 6px;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #0f766e;
}

.odo-selected-summary {
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    color: #374151;
    font-size: 13px;
    font-weight: 800;
}

.odo-selected-summary strong {
    color: #0f766e;
}

.odo-field {
    margin-bottom: 14px;
}

.odo-field:last-child {
    margin-bottom: 0;
}

.odo-label {
    display: block;
    margin-bottom: 6px;
    color: #111827;
    font-size: 12px;
    font-weight: 900;
}

.odo-control {
    width: 100%;
    min-height: 40px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    padding: 0 12px;
    font-size: 13px;
    font-weight: 700;
    box-sizing: border-box;
    outline: none;
}

textarea.odo-control {
    min-height: 90px;
    padding: 10px 12px;
    resize: vertical;
    font-family: Arial, sans-serif;
    line-height: 1.5;
}

.odo-control:focus {
    border-color: #0f766e;
}

.odo-surface-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
}

.odo-surface {
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 10px;
    border: 1px solid #e5e7eb;
    background: #fafafa;
    color: #111827;
    font-size: 12px;
    font-weight: 900;
}

.odo-surface input {
    margin: 0;
}

.odo-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 14px;
}

.odo-btn-secondary,
.odo-btn-primary {
    min-height: 40px;
    padding: 0 16px;
    border: 1px solid transparent;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
}

.odo-btn-secondary {
    background: #ffffff;
    border-color: #d1d5db;
    color: #111827;
}

.odo-btn-secondary:hover {
    background: #f9fafb;
}

.odo-btn-primary {
    background: #111827;
    border-color: #111827;
    color: #ffffff;
}

.odo-btn-primary:hover {
    background: #0f766e;
    border-color: #0f766e;
}

.odo-existing-list {
    display: grid;
    gap: 8px;
}

.odo-existing-empty {
    padding: 10px 12px;
    border: 1px dashed #d1d5db;
    background: #fafafa;
    color: #6b7280;
    font-size: 12px;
    font-weight: 700;
}

.odo-existing-item {
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
}

.odo-existing-main {
    color: #111827;
    font-size: 13px;
    font-weight: 900;
}

.odo-existing-sub {
    margin-top: 4px;
    color: #6b7280;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.45;
}

.intraoral-tooth.has-record {
    --tooth-mark-color: #0f766e;
    background: #f8fafc;
    border-color: var(--tooth-mark-color);
}

.intraoral-tooth.has-record::after {
    content: "";
    position: absolute;
    top: 4px;
    right: 4px;
    width: 11px;
    height: 11px;
    border-radius: 999px;
    background: var(--tooth-mark-color);
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 1px rgba(17, 24, 39, 0.08);
}

.condition-caries,
.intraoral-tooth.condition-caries,
.odo-popup-tooth.condition-caries {
    --tooth-mark-color: #dc2626;
}

.condition-missing,
.intraoral-tooth.condition-missing,
.odo-popup-tooth.condition-missing {
    --tooth-mark-color: #6b7280;
}

.condition-restoration,
.intraoral-tooth.condition-restoration,
.odo-popup-tooth.condition-restoration {
    --tooth-mark-color: #2563eb;
}

.condition-fractured,
.intraoral-tooth.condition-fractured,
.odo-popup-tooth.condition-fractured {
    --tooth-mark-color: #ea580c;
}

.condition-root-canal,
.intraoral-tooth.condition-root-canal,
.odo-popup-tooth.condition-root-canal {
    --tooth-mark-color: #7c3aed;
}

.condition-impacted,
.intraoral-tooth.condition-impacted,
.odo-popup-tooth.condition-impacted {
    --tooth-mark-color: #9333ea;
}

.condition-extraction,
.intraoral-tooth.condition-extraction,
.odo-popup-tooth.condition-extraction {
    --tooth-mark-color: #111827;
}

.condition-crown,
.intraoral-tooth.condition-crown,
.odo-popup-tooth.condition-crown {
    --tooth-mark-color: #ca8a04;
}

.condition-pontic,
.intraoral-tooth.condition-pontic,
.odo-popup-tooth.condition-pontic {
    --tooth-mark-color: #0891b2;
}

.condition-sealant,
.intraoral-tooth.condition-sealant,
.odo-popup-tooth.condition-sealant {
    --tooth-mark-color: #16a34a;
}

.condition-sound,
.intraoral-tooth.condition-sound,
.odo-popup-tooth.condition-sound {
    --tooth-mark-color: #0f766e;
}

.condition-other,
.intraoral-tooth.condition-other,
.odo-popup-tooth.condition-other {
    --tooth-mark-color: #64748b;
}

body.odontogram-popup-open {
    overflow: hidden;
}

.odontogram-popup-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 22px;
    background: rgba(17, 24, 39, 0.45);
    backdrop-filter: blur(7px);
    -webkit-backdrop-filter: blur(7px);
}

.odontogram-popup-backdrop.is-open {
    display: flex;
}

.odontogram-popup {
    width: min(1220px, 100%);
    max-height: calc(100dvh - 44px);
    overflow: auto;
    background: #ffffff;
    border: 1px solid #d1d5db;
    box-shadow: 0 24px 70px rgba(17, 24, 39, 0.26);
}

.odontogram-popup-header {
    position: sticky;
    top: 0;
    z-index: 3;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    padding: 16px 18px;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
}

.odontogram-popup-title {
    margin: 0;
    color: #111827;
    font-size: 18px;
    font-weight: 900;
}

.odontogram-popup-subtitle {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 12px;
    font-weight: 700;
}

.odontogram-popup-close {
    width: 36px;
    height: 36px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    font-size: 22px;
    font-weight: 900;
    cursor: pointer;
}

.odontogram-popup-close:hover {
    background: #f9fafb;
}

.odontogram-popup-body {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 330px;
    gap: 16px;
    padding: 16px;
}

.odontogram-popup-chart {
    border: 1px solid #e5e7eb;
    background: #ffffff;
    overflow-x: auto;
}

.odontogram-popup-strip {
    width: fit-content;
    min-width: 1080px;
    margin: 0 auto;
    padding: 26px 22px;
}

.odo-popup-row {
    display: grid;
    grid-template-columns: repeat(16, 48px);
    gap: 24px;
    align-items: center;
}

.odo-popup-upper {
    margin-bottom: 34px;
}

.odo-popup-tooth {
    position: relative;
    width: 48px;
    min-height: 106px;
    display: grid;
    grid-template-rows: 66px 20px;
    justify-items: center;
    align-items: center;
    border: 1px solid transparent;
    border-radius: 7px;
    background: transparent;
    cursor: pointer;
    padding: 2px;
    transition: background 0.16s ease, border-color 0.16s ease, transform 0.16s ease;
}

.odo-popup-tooth:hover,
.odo-popup-tooth.is-selected {
    background: #f0fdfa;
    border-color: #0f766e;
}

.odo-popup-tooth.is-selected {
    transform: translateY(-1px);
    box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.12);
}

.odo-popup-tooth.has-record {
    background: #f8fafc;
    border-color: var(--tooth-mark-color);
}

.odo-popup-tooth.has-record::after {
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

.odo-popup-tooth img {
    max-width: 44px;
    max-height: 66px;
    object-fit: contain;
    pointer-events: none;
}

.odo-popup-number {
    color: #8c9198;
    font-size: 13px;
    font-weight: 900;
}

.odo-popup-tooth.is-selected .odo-popup-number {
    color: #0f766e;
}

.odontogram-mark-panel {
    border: 1px solid #e5e7eb;
    background: #ffffff;
}

.odontogram-panel-section {
    padding: 14px;
    border-bottom: 1px solid #f1f5f9;
}

.odontogram-panel-section:last-child {
    border-bottom: 0;
}

.odontogram-panel-title {
    margin: 0 0 10px;
    color: #111827;
    font-size: 13px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.odontogram-selected-summary {
    padding: 11px 12px;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
    color: #374151;
    font-size: 13px;
    font-weight: 800;
}

.odontogram-selected-summary strong {
    color: #0f766e;
}

.odontogram-field {
    margin-bottom: 12px;
}

.odontogram-field:last-child {
    margin-bottom: 0;
}

.odontogram-label {
    display: block;
    margin-bottom: 6px;
    color: #111827;
    font-size: 12px;
    font-weight: 900;
}

.odontogram-control {
    width: 100%;
    min-height: 38px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    padding: 0 10px;
    font-size: 13px;
    font-weight: 700;
    box-sizing: border-box;
    outline: none;
}

textarea.odontogram-control {
    min-height: 84px;
    padding: 9px 10px;
    resize: vertical;
    font-family: Arial, sans-serif;
    line-height: 1.5;
}

.odontogram-control:focus {
    border-color: #0f766e;
}

.odontogram-surface-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 7px;
}

.odontogram-surface {
    min-height: 34px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 0 8px;
    border: 1px solid #e5e7eb;
    background: #fafafa;
    color: #111827;
    font-size: 12px;
    font-weight: 900;
}

.odontogram-surface input {
    margin: 0;
}

.odontogram-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 12px;
}

.odontogram-secondary-btn,
.odontogram-primary-btn {
    min-height: 38px;
    padding: 0 14px;
    border: 1px solid transparent;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
}

.odontogram-secondary-btn {
    background: #ffffff;
    border-color: #d1d5db;
    color: #111827;
}

.odontogram-primary-btn {
    background: #111827;
    border-color: #111827;
    color: #ffffff;
}

.odontogram-primary-btn:hover {
    background: #0f766e;
    border-color: #0f766e;
}

.odontogram-legend {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
}

.odontogram-legend-item {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #374151;
    font-size: 12px;
    font-weight: 800;
}

.odontogram-legend-dot {
    width: 11px;
    height: 11px;
    border-radius: 999px;
    background: var(--tooth-mark-color);
}

.odontogram-existing-list {
    display: grid;
    gap: 8px;
}

.odontogram-existing-empty,
.odontogram-existing-item {
    padding: 9px 10px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    color: #374151;
    font-size: 12px;
    font-weight: 700;
}

.odontogram-existing-empty {
    border-style: dashed;
    background: #fafafa;
    color: #6b7280;
}


.procedure-time-card {
    margin-bottom: 14px;
    border: 1px solid #d9d9d9;
    background: #ffffff;
    padding: 14px;
}

.procedure-time-title {
    margin: 0 0 12px;
    color: #111827;
    font-size: 14px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.procedure-time-grid.compact {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) minmax(320px, 1.4fr);
    gap: 12px;
}

.procedure-time-item {
    border: 1px solid #eeeeee;
    background: #fafafa;
    padding: 12px;
}

.procedure-time-item.actual-combined {
    background: #ffffff;
}

.procedure-time-label {
    display: block;
    margin-bottom: 7px;
    color: #6b7280;
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
}

.procedure-time-value {
    color: #111827;
    font-size: 13px;
    font-weight: 900;
    line-height: 1.4;
}

.actual-procedure-box {
    display: grid;
    gap: 4px;
    color: #111827;
    font-size: 12.5px;
    font-weight: 700;
    line-height: 1.45;
    min-width: 180px;
}

.actual-procedure-box strong {
    color: #374151;
    font-weight: 900;
}

.treatment-record-table {
    min-width: 1180px;
}

.treatment-record-table th:nth-child(3),
.treatment-record-table td:nth-child(3) {
    min-width: 210px;
}

@media (max-width: 760px) {
    .procedure-time-grid.compact {
        grid-template-columns: 1fr;
    }
}



.odontogram-existing-main {
    color: #111827;
    font-size: 13px;
    font-weight: 900;
}

.odontogram-existing-sub {
    margin-top: 4px;
    color: #6b7280;
    line-height: 1.45;
}




.intraoral-exam-header-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    margin-bottom: 14px;
}

.intraoral-open-chart-btn {
    min-height: 36px;
    padding: 0 14px;
    border: 1px solid #0f766e;
    background: #ffffff;
    color: #0f766e;
    text-decoration: none;
    font-size: 12px;
    font-weight: 900;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
}

.intraoral-open-chart-btn:hover {
    background: #f0fdfa;
}

.intraoral-open-chart-btn.is-disabled {
    color: #9ca3af;
    border-color: #d1d5db;
    pointer-events: none;
}

.intraoral-chart-wrap {
    width: 100%;
    overflow-x: auto;
    padding: 18px 0;
    background: #ffffff;
    border: 1px solid #eeeeee;
}

.intraoral-chart-strip {
    width: fit-content;
    min-width: 1120px;
    margin: 0 auto;
    padding: 0 18px;
    user-select: none;
}

.intraoral-upper-row,
.intraoral-lower-row {
    display: grid;
    grid-template-columns: repeat(16, 48px);
    gap: 24px;
    align-items: end;
}

.intraoral-upper-row {
    margin-bottom: 14px;
}

.intraoral-lower-row {
    align-items: start;
}

.intraoral-tooth {
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
    transition: background 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
}

.intraoral-lower-row .intraoral-tooth {
    grid-template-rows: 18px 68px 10px;
}

.intraoral-tooth:hover,
.intraoral-tooth.is-selected {
    background: #f0fdfa;
    border-color: #0f766e;
}

.intraoral-tooth.is-selected {
    box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.10);
    transform: translateY(-1px);
}

.intraoral-tooth.has-record {
    background: #f8fafc;
    border-color: var(--tooth-mark-color);
}

.intraoral-tooth.has-record::after {
    content: "";
    position: absolute;
    top: 4px;
    right: 4px;
    width: 11px;
    height: 11px;
    border-radius: 999px;
    background: var(--tooth-mark-color);
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 1px rgba(17, 24, 39, 0.08);
}

.intraoral-tooth-image {
    width: 44px;
    height: 68px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.intraoral-tooth-image img {
    max-width: 44px;
    max-height: 68px;
    object-fit: contain;
    display: block;
    pointer-events: none;
}

.intraoral-tooth-number {
    color: #8c9198;
    font-size: 13px;
    font-weight: 900;
    line-height: 1;
}

.intraoral-tooth.is-selected .intraoral-tooth-number {
    color: #0f766e;
}

.intraoral-tooth-dots {
    min-height: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.intraoral-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    display: inline-block;
    background: #cbd5e1;
}

/* condition colors */
.condition-caries,
.intraoral-tooth.condition-caries {
    --tooth-mark-color: #dc2626;
}

.condition-missing,
.intraoral-tooth.condition-missing {
    --tooth-mark-color: #6b7280;
}

.condition-restoration,
.intraoral-tooth.condition-restoration {
    --tooth-mark-color: #2563eb;
}

.condition-fractured,
.intraoral-tooth.condition-fractured {
    --tooth-mark-color: #ea580c;
}

.condition-root-canal,
.intraoral-tooth.condition-root-canal {
    --tooth-mark-color: #7c3aed;
}

.condition-impacted,
.intraoral-tooth.condition-impacted {
    --tooth-mark-color: #9333ea;
}

.condition-extraction,
.intraoral-tooth.condition-extraction {
    --tooth-mark-color: #111827;
}

.condition-crown,
.intraoral-tooth.condition-crown {
    --tooth-mark-color: #ca8a04;
}

.condition-pontic,
.intraoral-tooth.condition-pontic {
    --tooth-mark-color: #0891b2;
}

.condition-sealant,
.intraoral-tooth.condition-sealant {
    --tooth-mark-color: #16a34a;
}

.condition-sound,
.intraoral-tooth.condition-sound {
    --tooth-mark-color: #0f766e;
}

.condition-other,
.intraoral-tooth.condition-other {
    --tooth-mark-color: #64748b;
}

.condition-none,
.intraoral-tooth.condition-none {
    --tooth-mark-color: transparent;
}

/* dots also use same color */
.intraoral-dot.caries { background: #dc2626; }
.intraoral-dot.missing { background: #6b7280; }
.intraoral-dot.restoration { background: #2563eb; }
.intraoral-dot.fractured { background: #ea580c; }
.intraoral-dot.root-canal { background: #7c3aed; }
.intraoral-dot.impacted { background: #9333ea; }
.intraoral-dot.extraction { background: #111827; }
.intraoral-dot.crown { background: #ca8a04; }
.intraoral-dot.pontic { background: #0891b2; }
.intraoral-dot.sealant { background: #16a34a; }
.intraoral-dot.sound { background: #0f766e; }
.intraoral-dot.other { background: #64748b; }
.intraoral-dot.none { background: #cbd5e1; }

.intraoral-legend {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px 14px;
    margin-top: 14px;
    padding: 14px;
    border: 1px solid #e5e7eb;
    background: #fafafa;
}

.intraoral-legend-title {
    grid-column: 1 / -1;
    margin: 0 0 2px;
    color: #111827;
    font-size: 13px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.intraoral-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #374151;
    font-size: 12px;
    font-weight: 800;
}

.intraoral-legend-dot {
    width: 11px;
    height: 11px;
    border-radius: 999px;
    background: var(--tooth-mark-color);
    flex: 0 0 auto;
}

.intraoral-selected-box {
    margin-top: 14px;
    padding: 12px 14px;
    border: 1px solid #e5e7eb;
    background: #fafafa;
    color: #374151;
    font-size: 13px;
    font-weight: 800;
}

.intraoral-selected-box strong {
    color: #0f766e;
}

.intraoral-selected-meta {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.intraoral-condition-chip {
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
    white-space: nowrap;
}

.intraoral-condition-chip.caries {
    background: #fee2e2;
    border-color: #fecaca;
    color: #b91c1c;
}

.intraoral-condition-chip.missing {
    background: #f3f4f6;
    border-color: #d1d5db;
    color: #374151;
}

.intraoral-condition-chip.restoration {
    background: #dbeafe;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.intraoral-condition-chip.fractured {
    background: #ffedd5;
    border-color: #fed7aa;
    color: #c2410c;
}

.intraoral-condition-chip.root-canal {
    background: #ede9fe;
    border-color: #ddd6fe;
    color: #6d28d9;
}

.intraoral-condition-chip.impacted {
    background: #f3e8ff;
    border-color: #e9d5ff;
    color: #7e22ce;
}

.intraoral-condition-chip.extraction {
    background: #e5e7eb;
    border-color: #d1d5db;
    color: #111827;
}

.intraoral-condition-chip.crown {
    background: #fef3c7;
    border-color: #fde68a;
    color: #a16207;
}

.intraoral-condition-chip.pontic {
    background: #cffafe;
    border-color: #a5f3fc;
    color: #0e7490;
}

.intraoral-condition-chip.sealant {
    background: #dcfce7;
    border-color: #bbf7d0;
    color: #15803d;
}

.intraoral-condition-chip.sound {
    background: #ccfbf1;
    border-color: #99f6e4;
    color: #0f766e;
}

.intraoral-condition-chip.other {
    background: #e2e8f0;
    border-color: #cbd5e1;
    color: #475569;
}

.intraoral-condition-chip.none {
    background: #ffffff;
    border-color: #d1d5db;
    color: #6b7280;
}

.intraoral-missing-image {
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

.inline-treatment-row {
    background: #fffdf5;
}

.inline-treatment-row td {
    vertical-align: top;
}

.inline-treatment-input,
.inline-treatment-select {
    width: 100%;
    min-height: 36px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    font-size: 13px;
    font-weight: 700;
    padding: 7px 9px;
    box-sizing: border-box;
    outline: none;
}

.inline-treatment-input:focus,
.inline-treatment-select:focus {
    border-color: #0f766e;
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.10);
}

.inline-treatment-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.inline-treatment-save,
.inline-treatment-cancel {
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid #111827;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
    white-space: nowrap;
}

.inline-treatment-save {
    background: #111827;
    color: #ffffff;
}

.inline-treatment-cancel {
    background: #ffffff;
    color: #991b1b;
    border-color: #fecaca;
}

.inline-treatment-save:hover {
    background: #0f766e;
    border-color: #0f766e;
}

.inline-treatment-cancel:hover {
    background: #fef2f2;
}

.treatment-toolbar {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    margin-bottom: 14px;
}

.treatment-add-procedure-wrap {
    margin: 0;
    display: flex;
    justify-content: flex-end;
}

.treatment-add-procedure-wrap::before {
    display: none;
}

.treatment-record-table {
    width: 100%;
    min-width: 1220px;
    border-collapse: collapse;
    background: #ffffff;
}

.treatment-record-table th,
.treatment-record-table td {
    border-bottom: 1px solid #eeeeee;
    padding: 14px;
    text-align: left;
    vertical-align: middle;
}

.treatment-record-table th {
    background: #bdbdbd;
    color: #222222;
    border-right: 1px solid #d8d8d8;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
}

.treatment-record-table td {
    border-right: 1px solid #f1f5f9;
    font-size: 13px;
    color: #111827;
}

.treatment-record-table .action-cell {
    width: 92px;
    text-align: center;
    white-space: nowrap;
}

.inline-treatment-row,
.edit-treatment-row {
    background: #fffdf5;
}

.inline-treatment-input,
.inline-treatment-select {
    width: 100%;
    min-height: 36px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    font-size: 13px;
    font-weight: 700;
    padding: 7px 9px;
    box-sizing: border-box;
    outline: none;
}

.inline-treatment-input:focus,
.inline-treatment-select:focus {
    border-color: #0f766e;
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.10);
}

.treatment-action-group,
.inline-treatment-actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
}

.treatment-icon-btn {
    width: 34px;
    height: 34px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.treatment-icon-btn:hover {
    border-color: #0f766e;
    background: #f0fdfa;
    color: #0f766e;
}

.treatment-icon-btn svg {
    width: 16px;
    height: 16px;
    stroke: currentColor;
    stroke-width: 2.1;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.inline-treatment-save,
.inline-treatment-cancel {
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid #111827;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
    white-space: nowrap;
}

.inline-treatment-save {
    background: #111827;
    color: #ffffff;
}

.inline-treatment-save:hover {
    background: #0f766e;
    border-color: #0f766e;
}

.inline-treatment-cancel {
    background: #ffffff;
    color: #991b1b;
    border-color: #fecaca;
}

.inline-treatment-cancel:hover {
    background: #fef2f2;
}

.treatment-hidden-forms {
    display: none;
}

.treatment-row-hidden {
    display: none;
}

.treatment-hidden-forms {
    display: none;
}

.treatment-row-hidden {
    display: none;
}

.treatment-row-hidden {
    display: none;
}

.action-cell {
    text-align: center;
    white-space: nowrap;
}

.treatment-icon-btn {
    width: 34px;
    height: 34px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.treatment-icon-btn:hover {
    background: #f0fdfa;
    border-color: #0f766e;
    color: #0f766e;
}

.treatment-icon-btn svg {
    width: 16px;
    height: 16px;
    stroke: currentColor;
    stroke-width: 2;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.inline-treatment-row,
.edit-treatment-row {
    background: #fffdf5;
}

.inline-treatment-input,
.inline-treatment-select {
    width: 100%;
    min-height: 36px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    font-size: 13px;
    font-weight: 700;
    padding: 7px 9px;
    box-sizing: border-box;
    outline: none;
}

.inline-treatment-input:focus,
.inline-treatment-select:focus {
    border-color: #0f766e;
    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.10);
}

.inline-treatment-actions {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.inline-treatment-save,
.inline-treatment-cancel {
    min-height: 34px;
    padding: 0 12px;
    border: 1px solid #111827;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
    white-space: nowrap;
}

.inline-treatment-save {
    background: #111827;
    color: #ffffff;
}

.inline-treatment-save:hover {
    background: #0f766e;
    border-color: #0f766e;
}

.inline-treatment-cancel {
    background: #ffffff;
    color: #991b1b;
    border-color: #fecaca;
}

.inline-treatment-cancel:hover {
    background: #fef2f2;
}


@media (max-width: 980px) {
    .odontogram-popup-body {
        grid-template-columns: 1fr;
    }
}


@media (max-width: 980px) {
    .full-odontogram-body {
        grid-template-columns: 1fr;
    }

    .odo-surface-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 720px) {
    .odo-label-row.permanent,
    .odo-tooth-row.permanent {
        grid-template-columns: repeat(8, minmax(42px, 1fr));
    }

    .odo-label-row.primary,
    .odo-tooth-row.primary {
        grid-template-columns: repeat(5, minmax(42px, 1fr));
    }
}

@media (max-width: 1000px) {
    .record-top {
        padding: 64px 24px 0;
    }

    .record-main,
    .flash-wrap,
    .print-footer {
        padding-left: 24px;
        padding-right: 24px;
    }

    .patient-profile-grid,
    .patient-contact-strip {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .patient-profile-item,
    .patient-contact-item {
        grid-template-columns: 170px minmax(0, 1fr);
    }

    .record-title {
        font-size: 30px;
    }

    .record-grid,
    .record-grid.two-col {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .record-item.full,
    .record-item.two-full,
    .history-question.full,
    .condition-check-grid {
        grid-column: span 2;
    }

    .condition-check-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .record-top {
        padding: 58px 14px 0;
    }

    .record-main,
    .flash-wrap,
    .print-footer {
        padding-left: 14px;
        padding-right: 14px;
    }

    .record-title {
        font-size: 26px;
    }

    .patient-toolbar {
        justify-content: flex-start;
    }

    .patient-toolbar-item {
        min-width: 140px;
    }

    .patient-profile-item,
    .patient-contact-item {
        grid-template-columns: 1fr;
        gap: 4px;
    }

    .record-tabs {
        margin-top: 28px;
    }

    .record-tab {
        padding: 0 16px;
        font-size: 14px;
    }

    .record-grid,
    .record-grid.two-col {
        grid-template-columns: 1fr;
    }

    .record-item.full,
    .record-item.two-full,
    .history-question.full,
    .condition-check-grid {
        grid-column: span 1;
    }

    .condition-check-grid {
        grid-template-columns: 1fr;
    }

    .history-question {
        grid-template-columns: 1fr;
    }

    .print-footer {
        justify-content: stretch;
    }

    .print-page-btn {
        width: 100%;
    }
}

@media print {
    @page {
        size: A4;
        margin: 8mm;
    }

    html,
    body {
        background: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .staff-sidebar,
    .dentist-sidebar,
    .topbar,
    .sidebar,
    .app-sidebar,
    .layout-sidebar,
    .record-back,
    .record-tabs,
    .print-footer,
    .flash-wrap,
    .record-action-buttons,
    .patient-toolbar {
        display: none !important;
    }

    .dentist-record-page,
    .record-shell,
    .record-paper {
        background: #ffffff !important;
        padding: 0 !important;
        min-height: auto !important;
        max-width: none !important;
        margin: 0 !important;
    }

    .record-top {
        display: none !important;
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
        padding: 0 !important;
        page-break-inside: avoid;
    }

    .record-card-header,
    .record-card-body {
        padding: 0 !important;
    }
    .record-card + .record-card,
.merged-history-card {
    margin-top: 14px;
}
}
</style>

<div class="dentist-record-page">
    <div class="record-shell">
        <div class="record-paper" id="printableDentistPatientRecord">

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
            <span class="patient-toolbar-label">Intraoral Exam</span>
        </span>
    </button>

    <button type="button" class="patient-toolbar-item">
        <span class="patient-toolbar-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <rect x="3" y="6" width="18" height="12" rx="1.5"></rect>
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M6 9v6"></path>
                <path d="M18 9v6"></path>
            </svg>
        </span>
        <span class="patient-toolbar-text">
            <span class="patient-toolbar-money">Inv: 0.00</span>
            <span class="patient-toolbar-money">Due: 0.00</span>
        </span>
    </button>

    <button type="button" class="patient-toolbar-item" data-toolbar-tab="documents">
        <span class="patient-toolbar-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <path d="M8 3h8l4 4v14H8z"></path>
                <path d="M16 3v5h4"></path>
                <path d="M4 7h8l4 4v10H4z"></path>
            </svg>
        </span>
        <span class="patient-toolbar-text">
            <span class="patient-toolbar-count"><?= e((string) $documentCount) ?></span>
            <span class="patient-toolbar-label">Documents</span>
        </span>
    </button>

    <button
        type="button"
        class="patient-toolbar-item <?= $latestDocumentPreviewUrl !== '' ? '' : 'is-disabled' ?>"
        data-preview-latest-document="<?= e($latestDocumentPreviewUrl) ?>"
        <?= $latestDocumentPreviewUrl !== '' ? '' : 'disabled' ?>
    >
        <span class="patient-toolbar-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <path d="M8 3h8l4 4v14H8z"></path>
                <path d="M16 3v5h4"></path>
                <path d="M4 7h8l4 4v10H4z"></path>
                <path d="M8 15h7"></path>
            </svg>
        </span>
        <span class="patient-toolbar-text">
            <span class="patient-toolbar-label">Preview Documents</span>
        </span>
    </button>

    <button type="button" class="patient-toolbar-item" data-toolbar-tab="treatment-records">
        <span class="patient-toolbar-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="7" r="3"></circle>
                <path d="M6 21v-2a6 6 0 0 1 12 0v2"></path>
                <path d="M9 14l3 3 3-3"></path>
            </svg>
        </span>
        <span class="patient-toolbar-text">
            <span class="patient-toolbar-count"><?= e((string) $totalTreatments) ?></span>
            <span class="patient-toolbar-label">Treatments</span>
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
                        <a href="<?= e($baseUrl . '/dentist/patients') ?>" class="record-back" aria-label="Back to Patients" title="Back to Patients">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M15 6l-6 6 6 6"></path>
                            </svg>
                        </a>

                        <div class="patient-profile-main">
                            <h1 class="record-title">
                                <?= e($fullName !== '' ? $fullName : 'Unnamed Patient') ?>
                            </h1>

                            <div class="patient-profile-grid">

                            <div class="patient-profile-item">
                                  
                                    <span class="patient-profile-value">
                                        <?= $patientSince !== '' ? 'Patient since ' . e($patientSince) : '—' ?>
                                    </span>
                                </div>
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
    <div class="record-card">
        <div class="record-card-header">
            <h2 class="record-card-title">Patient Information Record</h2>
        </div>

        <div class="record-card-body">
            <form method="POST" action="<?= e($baseUrl . '/dentist/patients/update-profile') ?>" class="record-section editable-record-form" data-editable-form data-readonly="1">
                <?= Csrf::inputField(); ?>
                <input type="hidden" name="patient_id" value="<?= (int) ($patient['patient_id'] ?? 0) ?>">

                <div class="form-action-top">
                    <h3 class="record-section-title">General Information</h3>

                    <div class="record-action-buttons">
                        <button type="button" class="save-record-btn" data-edit-toggle data-edit-label="Edit Patient Info" data-save-label="Save Patient Info" aria-label="Edit Patient Info" title="Edit Patient Info">
                            <span class="icon-edit" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 20h9"></path>
                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"></path>
                                </svg>
                            </span>

                            <span class="icon-save" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M20 6 9 17l-5-5"></path>
                                </svg>
                            </span>
                        </button>

                        <button type="button" class="cancel-edit-btn" data-edit-cancel aria-label="Cancel Edit" title="Cancel Edit">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M18 6 6 18"></path>
                                <path d="M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="record-grid">
                    <div class="record-item">
                        <span class="record-label">Patient ID</span>
                        <span class="record-value">#<?= (int) ($patient['patient_id'] ?? 0) ?></span>
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="first_name">First Name</label>
                        <input class="editable-line" id="first_name" type="text" name="first_name" value="<?= e((string) ($patient['first_name'] ?? '')) ?>" required>
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="middle_name">Middle Name</label>
                        <input class="editable-line" id="middle_name" type="text" name="middle_name" value="<?= e((string) ($patient['middle_name'] ?? '')) ?>">
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="last_name">Last Name</label>
                        <input class="editable-line" id="last_name" type="text" name="last_name" value="<?= e((string) ($patient['last_name'] ?? '')) ?>" required>
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="contact_number">Contact Number</label>
                        <input class="editable-line" id="contact_number" type="tel" name="contact_number" value="<?= e((string) ($patient['contact_number'] ?? '')) ?>" placeholder="09XXXXXXXXX">
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="email">Email</label>
                        <input class="editable-line" id="email" type="email" name="email" value="<?= e((string) ($patient['email'] ?? '')) ?>">
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="birth_date">Birth Date</label>
                        <input class="editable-line" id="birth_date" type="date" name="birth_date" value="<?= e((string) ($patient['birth_date'] ?? '')) ?>" max="<?= e(date('Y-m-d')) ?>">
                    </div>

                    <div class="record-item">
                        <span class="record-label">Age</span>
                        <span class="record-value"><?= e($patientAge !== '—' ? $patientAge . ' yrs' : '—') ?></span>
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="sex">Sex</label>
                        <select class="editable-select" id="sex" name="sex">
                            <option value="">Select sex</option>
                            <option value="Male" <?= selectedText($patient['sex'] ?? '', 'Male') ?>>Male</option>
                            <option value="Female" <?= selectedText($patient['sex'] ?? '', 'Female') ?>>Female</option>
                        </select>
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="civil_status">Civil Status</label>
                        <select class="editable-select" id="civil_status" name="civil_status">
                            <option value="">Select civil status</option>
                            <option value="Single" <?= selectedText($patient['civil_status'] ?? '', 'Single') ?>>Single</option>
                            <option value="Married" <?= selectedText($patient['civil_status'] ?? '', 'Married') ?>>Married</option>
                            <option value="Widowed" <?= selectedText($patient['civil_status'] ?? '', 'Widowed') ?>>Widowed</option>
                            <option value="Separated" <?= selectedText($patient['civil_status'] ?? '', 'Separated') ?>>Separated</option>
                        </select>
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="occupation">Occupation</label>
                        <input class="editable-line" id="occupation" type="text" name="occupation" value="<?= e((string) ($patient['occupation'] ?? '')) ?>">
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="emergency_contact_name">Emergency Contact Name</label>
                        <input class="editable-line" id="emergency_contact_name" type="text" name="emergency_contact_name" value="<?= e((string) ($patient['emergency_contact_name'] ?? '')) ?>">
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="emergency_contact_number">Emergency Contact Number</label>
                        <input class="editable-line" id="emergency_contact_number" type="tel" name="emergency_contact_number" value="<?= e((string) ($patient['emergency_contact_number'] ?? '')) ?>">
                    </div>

                    <div class="record-item full">
                        <label class="record-label" for="address">Address</label>
                        <input class="editable-line" id="address" type="text" name="address" value="<?= e((string) ($patient['address'] ?? '')) ?>">
                    </div>

                    <div class="record-item full">
                        <label class="record-label" for="notes">Notes</label>
                        <textarea class="editable-textarea" id="notes" name="notes"><?= e((string) ($patient['notes'] ?? '')) ?></textarea>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="record-card merged-history-card">
        <div class="record-card-header">
            <h2 class="record-card-title">Dental History</h2>
        </div>

        <div class="record-card-body">
            <form method="POST" action="<?= e($baseUrl . '/dentist/patients/save-dental-history') ?>" class="record-section editable-record-form" data-editable-form data-readonly="1">
                <?= Csrf::inputField(); ?>
                <input type="hidden" name="patient_id" value="<?= (int) ($patient['patient_id'] ?? 0) ?>">

                <div class="form-action-top">
                    <h3 class="record-section-title">Dental History Information</h3>

                    <div class="record-action-buttons">
                        <button type="button" class="save-record-btn" data-edit-toggle data-edit-label="Edit Dental History" data-save-label="Save Dental History" aria-label="Edit Dental History" title="Edit Dental History">
                            <span class="icon-edit" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 20h9"></path>
                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"></path>
                                </svg>
                            </span>

                            <span class="icon-save" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M20 6 9 17l-5-5"></path>
                                </svg>
                            </span>
                        </button>

                        <button type="button" class="cancel-edit-btn" data-edit-cancel aria-label="Cancel Edit" title="Cancel Edit">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M18 6 6 18"></path>
                                <path d="M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="record-grid two-col">
                    <div class="history-question">
                        <span class="history-question-text">Have you worn any type of denture?</span>
                        <select class="editable-select" name="worn_denture">
                            <option value="">Select</option>
                            <option value="yes" <?= selectedAnswer($dentalHistory['worn_denture'] ?? '', 'yes') ?>>Yes</option>
                            <option value="no" <?= selectedAnswer($dentalHistory['worn_denture'] ?? '', 'no') ?>>No</option>
                        </select>
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="last_dental_visit">Last Dental Visit</label>
                        <input class="editable-line" id="last_dental_visit" type="date" name="last_dental_visit" value="<?= e((string) ($dentalHistory['last_dental_visit'] ?? '')) ?>">
                    </div>

                    <div class="record-item two-full">
                        <label class="record-label" for="last_dental_visit_reason">Reason for Last Dental Visit</label>
                        <textarea class="editable-textarea" id="last_dental_visit_reason" name="last_dental_visit_reason"><?= e((string) ($dentalHistory['last_dental_visit_reason'] ?? '')) ?></textarea>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="record-card merged-history-card">
        <div class="record-card-header">
            <h2 class="record-card-title">Medical History</h2>
        </div>

        <div class="record-card-body">
            <form method="POST" action="<?= e($baseUrl . '/dentist/patients/save-medical-history') ?>" class="record-section editable-record-form" data-editable-form data-readonly="1">
                <?= Csrf::inputField(); ?>
                <input type="hidden" name="patient_id" value="<?= (int) ($patient['patient_id'] ?? 0) ?>">

                <div class="form-action-top">
                    <h3 class="record-section-title">Medical History Information</h3>

                    <div class="record-action-buttons">
                        <button type="button" class="save-record-btn" data-edit-toggle data-edit-label="Edit Medical History" data-save-label="Save Medical History" aria-label="Edit Medical History" title="Edit Medical History">
                            <span class="icon-edit" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 20h9"></path>
                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"></path>
                                </svg>
                            </span>

                            <span class="icon-save" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M20 6 9 17l-5-5"></path>
                                </svg>
                            </span>
                        </button>

                        <button type="button" class="cancel-edit-btn" data-edit-cancel aria-label="Cancel Edit" title="Cancel Edit">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M18 6 6 18"></path>
                                <path d="M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="record-grid two-col">
                    <div class="history-question full">
                        <span class="history-question-text">1. Are you presently under physician’s care?</span>
                        <select class="editable-select" name="under_physician_care">
                            <option value="">Select</option>
                            <option value="yes" <?= selectedAnswer($medicalHistory['under_physician_care'] ?? '', 'yes') ?>>Yes</option>
                            <option value="no" <?= selectedAnswer($medicalHistory['under_physician_care'] ?? '', 'no') ?>>No</option>
                        </select>
                    </div>

                    <div class="record-item two-full">
                        <label class="record-label" for="physician_care_details">If yes, who or why?</label>
                        <textarea class="editable-textarea" id="physician_care_details" name="physician_care_details"><?= e((string) ($medicalHistory['physician_care_details'] ?? '')) ?></textarea>
                    </div>

                    <div class="history-question">
                        <span class="history-question-text">2. Are you pregnant?</span>
                        <select class="editable-select" name="is_pregnant">
                            <option value="">Select</option>
                            <option value="yes" <?= selectedAnswer($medicalHistory['is_pregnant'] ?? '', 'yes') ?>>Yes</option>
                            <option value="no" <?= selectedAnswer($medicalHistory['is_pregnant'] ?? '', 'no') ?>>No</option>
                        </select>
                    </div>

                    <div class="history-question">
                        <span class="history-question-text">3. Are you taking any medicine at present?</span>
                        <select class="editable-select" name="taking_medicine">
                            <option value="">Select</option>
                            <option value="yes" <?= selectedAnswer($medicalHistory['taking_medicine'] ?? '', 'yes') ?>>Yes</option>
                            <option value="no" <?= selectedAnswer($medicalHistory['taking_medicine'] ?? '', 'no') ?>>No</option>
                        </select>
                    </div>

                    <div class="record-item two-full">
                        <label class="record-label" for="medicine_details">If yes, what?</label>
                        <textarea class="editable-textarea" id="medicine_details" name="medicine_details"><?= e((string) ($medicalHistory['medicine_details'] ?? '')) ?></textarea>
                    </div>

                    <div class="record-item two-full">
                        <span class="record-label">4. Have you ever had?</span>
                    </div>

                    <div class="condition-check-grid">
                        <label class="condition-check">
                            <input type="checkbox" name="condition_high_blood_pressure" value="1" <?= checkedAttr($medicalHistory['condition_high_blood_pressure'] ?? 0) ?>>
                            High Blood Pressure
                        </label>

                        <label class="condition-check">
                            <input type="checkbox" name="condition_low_blood_pressure" value="1" <?= checkedAttr($medicalHistory['condition_low_blood_pressure'] ?? 0) ?>>
                            Low Blood Pressure
                        </label>

                        <label class="condition-check">
                            <input type="checkbox" name="condition_asthma" value="1" <?= checkedAttr($medicalHistory['condition_asthma'] ?? 0) ?>>
                            Asthma
                        </label>

                        <label class="condition-check">
                            <input type="checkbox" name="condition_heart_disease" value="1" <?= checkedAttr($medicalHistory['condition_heart_disease'] ?? 0) ?>>
                            Heart Disease
                        </label>

                        <label class="condition-check">
                            <input type="checkbox" name="condition_diabetes" value="1" <?= checkedAttr($medicalHistory['condition_diabetes'] ?? 0) ?>>
                            Diabetes
                        </label>

                        <label class="condition-check">
                            <input type="checkbox" name="condition_tuberculosis" value="1" <?= checkedAttr($medicalHistory['condition_tuberculosis'] ?? 0) ?>>
                            Tuberculosis
                        </label>

                        <label class="condition-check">
                            <input type="checkbox" name="condition_thyroid_problem" value="1" <?= checkedAttr($medicalHistory['condition_thyroid_problem'] ?? 0) ?>>
                            Thyroid Problem
                        </label>

                        <label class="condition-check">
                            <input type="checkbox" name="condition_bleeding_problems" value="1" <?= checkedAttr($medicalHistory['condition_bleeding_problems'] ?? 0) ?>>
                            Bleeding Problems
                        </label>

                        <label class="condition-check">
                            <input type="checkbox" name="condition_hiv_aids" value="1" <?= checkedAttr($medicalHistory['condition_hiv_aids'] ?? 0) ?>>
                            AIDS or HIV Infection
                        </label>

                        <label class="condition-check">
                            <input type="checkbox" name="condition_hepatitis" value="1" <?= checkedAttr($medicalHistory['condition_hepatitis'] ?? 0) ?>>
                            Hepatitis
                        </label>

                        <label class="condition-check">
                            <input type="checkbox" name="condition_others" value="1" <?= checkedAttr($medicalHistory['condition_others'] ?? 0) ?>>
                            Others
                        </label>
                    </div>

                    <div class="record-item two-full">
                        <span class="record-label">5. Have you ever had allergic reaction to?</span>
                    </div>

                    <div class="history-question">
                        <span class="history-question-text">Local Anesthesia</span>
                        <select class="editable-select" name="allergy_local_anesthesia">
                            <option value="">Select</option>
                            <option value="yes" <?= selectedAnswer($medicalHistory['allergy_local_anesthesia'] ?? '', 'yes') ?>>Yes</option>
                            <option value="no" <?= selectedAnswer($medicalHistory['allergy_local_anesthesia'] ?? '', 'no') ?>>No</option>
                        </select>
                    </div>

                    <div class="history-question">
                        <span class="history-question-text">Antibiotics</span>
                        <select class="editable-select" name="allergy_antibiotics">
                            <option value="">Select</option>
                            <option value="yes" <?= selectedAnswer($medicalHistory['allergy_antibiotics'] ?? '', 'yes') ?>>Yes</option>
                            <option value="no" <?= selectedAnswer($medicalHistory['allergy_antibiotics'] ?? '', 'no') ?>>No</option>
                        </select>
                    </div>

                    <div class="history-question">
                        <span class="history-question-text">Pain Killer</span>
                        <select class="editable-select" name="allergy_pain_killer">
                            <option value="">Select</option>
                            <option value="yes" <?= selectedAnswer($medicalHistory['allergy_pain_killer'] ?? '', 'yes') ?>>Yes</option>
                            <option value="no" <?= selectedAnswer($medicalHistory['allergy_pain_killer'] ?? '', 'no') ?>>No</option>
                        </select>
                    </div>

                    <div class="history-question">
                        <span class="history-question-text">Others</span>
                        <select class="editable-select" name="allergy_others">
                            <option value="">Select</option>
                            <option value="yes" <?= selectedAnswer($medicalHistory['allergy_others'] ?? '', 'yes') ?>>Yes</option>
                            <option value="no" <?= selectedAnswer($medicalHistory['allergy_others'] ?? '', 'no') ?>>No</option>
                        </select>
                    </div>

                    <div class="history-question full">
                        <span class="history-question-text">6. Have you been hospitalized?</span>
                        <select class="editable-select" name="hospitalized">
                            <option value="">Select</option>
                            <option value="yes" <?= selectedAnswer($medicalHistory['hospitalized'] ?? '', 'yes') ?>>Yes</option>
                            <option value="no" <?= selectedAnswer($medicalHistory['hospitalized'] ?? '', 'no') ?>>No</option>
                        </select>
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="hospitalization_when">If yes, when?</label>
                        <input class="editable-line" id="hospitalization_when" type="text" name="hospitalization_when" value="<?= e((string) ($medicalHistory['hospitalization_when'] ?? '')) ?>">
                    </div>

                    <div class="record-item">
                        <label class="record-label" for="hospitalization_why">Why?</label>
                        <input class="editable-line" id="hospitalization_why" type="text" name="hospitalization_why" value="<?= e((string) ($medicalHistory['hospitalization_why'] ?? '')) ?>">
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

                <section class="record-panel" data-panel="dental-history">
                    <div class="record-card">
                        <div class="record-card-header">
                            <h2 class="record-card-title">Dental History</h2>
                        </div>

                        <div class="record-card-body">
                            <form method="POST" action="<?= e($baseUrl . '/dentist/patients/save-dental-history') ?>" class="record-section editable-record-form" data-editable-form data-readonly="1">
                                <?= Csrf::inputField(); ?>
                                <input type="hidden" name="patient_id" value="<?= (int) ($patient['patient_id'] ?? 0) ?>">

                                <div class="form-action-top">
                                    <h3 class="record-section-title">Dental History Information</h3>

                                    <div class="record-action-buttons">
                                        <button type="button" class="save-record-btn" data-edit-toggle data-edit-label="Edit Dental History" data-save-label="Save Dental History" aria-label="Edit Dental History" title="Edit Dental History">
                                            <span class="icon-edit" aria-hidden="true">
                                                <svg viewBox="0 0 24 24">
                                                    <path d="M12 20h9"></path>
                                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"></path>
                                                </svg>
                                            </span>

                                            <span class="icon-save" aria-hidden="true">
                                                <svg viewBox="0 0 24 24">
                                                    <path d="M20 6 9 17l-5-5"></path>
                                                </svg>
                                            </span>
                                        </button>

                                        <button type="button" class="cancel-edit-btn" data-edit-cancel aria-label="Cancel Edit" title="Cancel Edit">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M18 6 6 18"></path>
                                                <path d="M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="record-grid two-col">
                                    <div class="history-question">
                                        <span class="history-question-text">Have you worn any type of denture?</span>
                                        <select class="editable-select" name="worn_denture">
                                            <option value="">Select</option>
                                            <option value="yes" <?= selectedAnswer($dentalHistory['worn_denture'] ?? '', 'yes') ?>>Yes</option>
                                            <option value="no" <?= selectedAnswer($dentalHistory['worn_denture'] ?? '', 'no') ?>>No</option>
                                        </select>
                                    </div>

                                    <div class="record-item">
                                        <label class="record-label" for="last_dental_visit">Last Dental Visit</label>
                                        <input class="editable-line" id="last_dental_visit" type="date" name="last_dental_visit" value="<?= e((string) ($dentalHistory['last_dental_visit'] ?? '')) ?>">
                                    </div>

                                    <div class="record-item two-full">
                                        <label class="record-label" for="last_dental_visit_reason">Why?</label>
                                        <textarea class="editable-textarea" id="last_dental_visit_reason" name="last_dental_visit_reason"><?= e((string) ($dentalHistory['last_dental_visit_reason'] ?? '')) ?></textarea>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="record-panel" data-panel="medical-history">
                    <div class="record-card">
                        <div class="record-card-header">
                            <h2 class="record-card-title">Medical History</h2>
                        </div>

                        <div class="record-card-body">
                            <form method="POST" action="<?= e($baseUrl . '/dentist/patients/save-medical-history') ?>" class="record-section editable-record-form" data-editable-form data-readonly="1">
                                <?= Csrf::inputField(); ?>
                                <input type="hidden" name="patient_id" value="<?= (int) ($patient['patient_id'] ?? 0) ?>">

                                <div class="form-action-top">
                                    <h3 class="record-section-title">Medical History Information</h3>

                                    <div class="record-action-buttons">
                                        <button type="button" class="save-record-btn" data-edit-toggle data-edit-label="Edit Medical History" data-save-label="Save Medical History" aria-label="Edit Medical History" title="Edit Medical History">
                                            <span class="icon-edit" aria-hidden="true">
                                                <svg viewBox="0 0 24 24">
                                                    <path d="M12 20h9"></path>
                                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"></path>
                                                </svg>
                                            </span>

                                            <span class="icon-save" aria-hidden="true">
                                                <svg viewBox="0 0 24 24">
                                                    <path d="M20 6 9 17l-5-5"></path>
                                                </svg>
                                            </span>
                                        </button>

                                        <button type="button" class="cancel-edit-btn" data-edit-cancel aria-label="Cancel Edit" title="Cancel Edit">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M18 6 6 18"></path>
                                                <path d="M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="record-grid two-col">
                                    <div class="history-question full">
                                        <span class="history-question-text">1. Are you presently under physician’s care?</span>
                                        <select class="editable-select" name="under_physician_care">
                                            <option value="">Select</option>
                                            <option value="yes" <?= selectedAnswer($medicalHistory['under_physician_care'] ?? '', 'yes') ?>>Yes</option>
                                            <option value="no" <?= selectedAnswer($medicalHistory['under_physician_care'] ?? '', 'no') ?>>No</option>
                                        </select>
                                    </div>

                                    <div class="record-item two-full">
                                        <label class="record-label" for="physician_care_details">If yes, who or why?</label>
                                        <textarea class="editable-textarea" id="physician_care_details" name="physician_care_details"><?= e((string) ($medicalHistory['physician_care_details'] ?? '')) ?></textarea>
                                    </div>

                                    <div class="history-question">
                                        <span class="history-question-text">2. Are you pregnant?</span>
                                        <select class="editable-select" name="is_pregnant">
                                            <option value="">Select</option>
                                            <option value="yes" <?= selectedAnswer($medicalHistory['is_pregnant'] ?? '', 'yes') ?>>Yes</option>
                                            <option value="no" <?= selectedAnswer($medicalHistory['is_pregnant'] ?? '', 'no') ?>>No</option>
                                        </select>
                                    </div>

                                    <div class="history-question">
                                        <span class="history-question-text">3. Are you taking any medicine at present?</span>
                                        <select class="editable-select" name="taking_medicine">
                                            <option value="">Select</option>
                                            <option value="yes" <?= selectedAnswer($medicalHistory['taking_medicine'] ?? '', 'yes') ?>>Yes</option>
                                            <option value="no" <?= selectedAnswer($medicalHistory['taking_medicine'] ?? '', 'no') ?>>No</option>
                                        </select>
                                    </div>

                                    <div class="record-item two-full">
                                        <label class="record-label" for="medicine_details">If yes, what?</label>
                                        <textarea class="editable-textarea" id="medicine_details" name="medicine_details"><?= e((string) ($medicalHistory['medicine_details'] ?? '')) ?></textarea>
                                    </div>

                                    <div class="record-item two-full">
                                        <span class="record-label">4. Have you ever had?</span>
                                    </div>

                                    <div class="condition-check-grid">
                                        <label class="condition-check">
                                            <input type="checkbox" name="condition_high_blood_pressure" value="1" <?= checkedAttr($medicalHistory['condition_high_blood_pressure'] ?? 0) ?>>
                                            High Blood Pressure
                                        </label>

                                        <label class="condition-check">
                                            <input type="checkbox" name="condition_low_blood_pressure" value="1" <?= checkedAttr($medicalHistory['condition_low_blood_pressure'] ?? 0) ?>>
                                            Low Blood Pressure
                                        </label>

                                        <label class="condition-check">
                                            <input type="checkbox" name="condition_asthma" value="1" <?= checkedAttr($medicalHistory['condition_asthma'] ?? 0) ?>>
                                            Asthma
                                        </label>

                                        <label class="condition-check">
                                            <input type="checkbox" name="condition_heart_disease" value="1" <?= checkedAttr($medicalHistory['condition_heart_disease'] ?? 0) ?>>
                                            Heart Disease
                                        </label>

                                        <label class="condition-check">
                                            <input type="checkbox" name="condition_diabetes" value="1" <?= checkedAttr($medicalHistory['condition_diabetes'] ?? 0) ?>>
                                            Diabetes
                                        </label>

                                        <label class="condition-check">
                                            <input type="checkbox" name="condition_tuberculosis" value="1" <?= checkedAttr($medicalHistory['condition_tuberculosis'] ?? 0) ?>>
                                            Tuberculosis
                                        </label>

                                        <label class="condition-check">
                                            <input type="checkbox" name="condition_thyroid_problem" value="1" <?= checkedAttr($medicalHistory['condition_thyroid_problem'] ?? 0) ?>>
                                            Thyroid Problem
                                        </label>

                                        <label class="condition-check">
                                            <input type="checkbox" name="condition_bleeding_problems" value="1" <?= checkedAttr($medicalHistory['condition_bleeding_problems'] ?? 0) ?>>
                                            Bleeding Problems
                                        </label>

                                        <label class="condition-check">
                                            <input type="checkbox" name="condition_hiv_aids" value="1" <?= checkedAttr($medicalHistory['condition_hiv_aids'] ?? 0) ?>>
                                            AIDS or HIV Infection
                                        </label>

                                        <label class="condition-check">
                                            <input type="checkbox" name="condition_hepatitis" value="1" <?= checkedAttr($medicalHistory['condition_hepatitis'] ?? 0) ?>>
                                            Hepatitis
                                        </label>

                                        <label class="condition-check">
                                            <input type="checkbox" name="condition_others" value="1" <?= checkedAttr($medicalHistory['condition_others'] ?? 0) ?>>
                                            Others
                                        </label>
                                    </div>

                                    <div class="record-item two-full">
                                        <span class="record-label">5. Have you ever had allergic reaction to?</span>
                                    </div>

                                    <div class="history-question">
                                        <span class="history-question-text">Local Anesthesia</span>
                                        <select class="editable-select" name="allergy_local_anesthesia">
                                            <option value="">Select</option>
                                            <option value="yes" <?= selectedAnswer($medicalHistory['allergy_local_anesthesia'] ?? '', 'yes') ?>>Yes</option>
                                            <option value="no" <?= selectedAnswer($medicalHistory['allergy_local_anesthesia'] ?? '', 'no') ?>>No</option>
                                        </select>
                                    </div>

                                    <div class="history-question">
                                        <span class="history-question-text">Antibiotics</span>
                                        <select class="editable-select" name="allergy_antibiotics">
                                            <option value="">Select</option>
                                            <option value="yes" <?= selectedAnswer($medicalHistory['allergy_antibiotics'] ?? '', 'yes') ?>>Yes</option>
                                            <option value="no" <?= selectedAnswer($medicalHistory['allergy_antibiotics'] ?? '', 'no') ?>>No</option>
                                        </select>
                                    </div>

                                    <div class="history-question">
                                        <span class="history-question-text">Pain Killer</span>
                                        <select class="editable-select" name="allergy_pain_killer">
                                            <option value="">Select</option>
                                            <option value="yes" <?= selectedAnswer($medicalHistory['allergy_pain_killer'] ?? '', 'yes') ?>>Yes</option>
                                            <option value="no" <?= selectedAnswer($medicalHistory['allergy_pain_killer'] ?? '', 'no') ?>>No</option>
                                        </select>
                                    </div>

                                    <div class="history-question">
                                        <span class="history-question-text">Others</span>
                                        <select class="editable-select" name="allergy_others">
                                            <option value="">Select</option>
                                            <option value="yes" <?= selectedAnswer($medicalHistory['allergy_others'] ?? '', 'yes') ?>>Yes</option>
                                            <option value="no" <?= selectedAnswer($medicalHistory['allergy_others'] ?? '', 'no') ?>>No</option>
                                        </select>
                                    </div>

                                    <div class="history-question full">
                                        <span class="history-question-text">6. Have you been hospitalized?</span>
                                        <select class="editable-select" name="hospitalized">
                                            <option value="">Select</option>
                                            <option value="yes" <?= selectedAnswer($medicalHistory['hospitalized'] ?? '', 'yes') ?>>Yes</option>
                                            <option value="no" <?= selectedAnswer($medicalHistory['hospitalized'] ?? '', 'no') ?>>No</option>
                                        </select>
                                    </div>

                                    <div class="record-item">
                                        <label class="record-label" for="hospitalization_when">If yes, when?</label>
                                        <input class="editable-line" id="hospitalization_when" type="text" name="hospitalization_when" value="<?= e((string) ($medicalHistory['hospitalization_when'] ?? '')) ?>">
                                    </div>

                                    <div class="record-item">
                                        <label class="record-label" for="hospitalization_why">Why?</label>
                                        <input class="editable-line" id="hospitalization_why" type="text" name="hospitalization_why" value="<?= e((string) ($medicalHistory['hospitalization_why'] ?? '')) ?>">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

              <?php
$todayTreatmentAppointment = isset($todayTreatmentAppointment) && is_array($todayTreatmentAppointment)
    ? $todayTreatmentAppointment
    : [];

$autoTreatmentAppointmentId = $requestedAppointmentId > 0
    ? $requestedAppointmentId
    : (int) (
        $latestAppointmentId
        ?? ($todayTreatmentAppointment['appointment_id'] ?? 0)
    );

$appointmentsById = [];

foreach ($appointments as $appointmentItem) {
    if (!is_array($appointmentItem)) {
        continue;
    }

    $mapAppointmentId = (int) ($appointmentItem['appointment_id'] ?? 0);

    if ($mapAppointmentId > 0) {
        $appointmentsById[$mapAppointmentId] = $appointmentItem;
    }
}

$currentProcedureAppointment = $appointmentsById[$autoTreatmentAppointmentId] ?? [];

$currentProcedureAppointment = $appointmentsById[$autoTreatmentAppointmentId] ?? [];    

$autoTreatmentDate = date('Y-m-d');

if (!empty($todayTreatmentAppointment['appointment_date'])) {
    $appointmentDateTimestamp = strtotime((string) $todayTreatmentAppointment['appointment_date']);

    if ($appointmentDateTimestamp !== false) {
        $autoTreatmentDate = date('Y-m-d', $appointmentDateTimestamp);
    }
}

$autoProcedureName = trim((string) ($todayTreatmentAppointment['service_name'] ?? ''));

$autoActualCharge = (float) (
    $todayTreatmentAppointment['estimated_price']
    ?? $todayTreatmentAppointment['service_estimated_price']
    ?? 0
);


$saveTreatmentUrl = $baseUrl . '/dentist/patients/save-treatment';
$updateTreatmentUrl = $baseUrl . '/dentist/patients/update-treatment';
?>

<section class="record-panel" data-panel="treatment-records">
    <div class="record-card">
        <div class="record-card-header">
            <h2 class="record-card-title">Patient Treatment Record</h2>
        </div>

        <div class="record-card-body">


            <div class="treatment-toolbar">
                <div class="treatment-add-procedure-wrap">
                    <button
                        type="button"
                        class="add-procedure-btn <?= $canOpenDentalChart ? '' : 'is-disabled' ?> <?= $openedFromStartNow ? 'pulse-highlight' : '' ?>"
                        id="addTreatmentInlineBtn"
                        data-auto-procedure="<?= e($autoProcedureName) ?>"
                        data-auto-charge="<?= e(number_format($autoActualCharge, 2, '.', '')) ?>"
                        data-auto-date="<?= e($autoTreatmentDate) ?>"
                        data-started="<?= !empty($currentProcedureAppointment['actual_started_at'] ?? '') ? '1' : '0' ?>"
                        <?= $canOpenDentalChart ? '' : 'disabled' ?>
                    >
                        <span class="add-procedure-plus">+</span>
                        <span>Add Procedure</span>
                    </button>
                </div>
            </div>

            <form
                id="addTreatmentForm"
                method="POST"
                action="<?= e($saveTreatmentUrl) ?>"
            >
                <?= Csrf::inputField(); ?>
                <input type="hidden" name="patient_id" value="<?= (int) $patientIdValue ?>">
                <input type="hidden" name="appointment_id" value="<?= (int) $autoTreatmentAppointmentId ?>">
            </form>

            <div class="treatment-hidden-forms" hidden>
                <?php foreach ($treatments as $treatment): ?>
                    <?php
                        $treatmentId = (int) ($treatment['treatment_id'] ?? 0);
                        $editFormId = 'updateTreatmentForm' . $treatmentId;
                    ?>

                    <?php if ($treatmentId > 0): ?>
                        <form
                            id="<?= e($editFormId) ?>"
                            method="POST"
                            action="<?= e($updateTreatmentUrl) ?>"
                        >
                            <?= Csrf::inputField(); ?>
                            <input type="hidden" name="patient_id" value="<?= (int) $patientIdValue ?>">
                            <input type="hidden" name="treatment_id" value="<?= (int) $treatmentId ?>">
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="appointment-wrap">
                <table class="treatment-record-table">
                  <thead>
    <tr>
        <th>Date</th>
       
        <th>Actual Procedure</th>
        <th>Tooth No/s</th>
        <th>Procedure</th>
        <th>Dentist/s</th>
        <th>Amount Charged</th>
        <th>Amount Paid</th>
        <th>Balance</th>
        <th>Status</th>
        <th class="action-cell">Action</th>
    </tr>
</thead>

                    <tbody>
                        <tr id="inlineTreatmentRow" class="inline-treatment-row" hidden>
                            <td>
                                <input
                                    class="inline-treatment-input"
                                    type="date"
                                    name="treatment_date"
                                    id="inlineTreatmentDate"
                                    value="<?= e($autoTreatmentDate) ?>"
                                    form="addTreatmentForm"
                                    required
                                >
                            </td>


<td>
    <?php if (!empty($currentProcedureAppointment)): ?>
        <?= renderActualProcedureBox($currentProcedureAppointment) ?>
    <?php else: ?>
        <div class="actual-procedure-box">
            <div><strong>Started:</strong> —</div>
            <div><strong>Completed:</strong> —</div>
            <div><strong>Duration:</strong> —</div>
        </div>
    <?php endif; ?>
</td>

                            <td>
                                <input
                                    class="inline-treatment-input"
                                    type="text"
                                    name="treated_tooth"
                                    placeholder="Example: 16, 26"
                                    form="addTreatmentForm"
                                >
                            </td>

                            <td>
                                <input
                                    class="inline-treatment-input"
                                    type="text"
                                    name="procedure_name"
                                    id="inlineProcedureName"
                                    value="<?= e($autoProcedureName) ?>"
                                    placeholder="Procedure"
                                    form="addTreatmentForm"
                                    required
                                >
                            </td>

                            <td>
                                <?= displayValue($primaryDentistName) ?>
                            </td>

                            <td>
                                <input
                                    class="inline-treatment-input"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="actual_charge"
                                    id="inlineActualCharge"
                                    value="<?= e(number_format($autoActualCharge, 2, '.', '')) ?>"
                                    form="addTreatmentForm"
                                    required
                                >
                            </td>

                            <td>
                                <input
                                    class="inline-treatment-input"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="amount_paid"
                                    id="inlineAmountPaid"
                                    value="0.00"
                                    form="addTreatmentForm"
                                    required
                                >
                            </td>

                            <td>
                                <input
                                    class="inline-treatment-input"
                                    type="text"
                                    id="inlineBalanceDisplay"
                                    value="<?= e(number_format($autoActualCharge, 2, '.', '')) ?>"
                                    readonly
                                >
                            </td>

                            <td>
                                <select
                                    class="inline-treatment-select"
                                    name="treatment_status"
                                    form="addTreatmentForm"
                                    required
                                >
                                    <option value="completed" selected>Completed</option>
                                    <option value="performed">Performed</option>
                                    <option value="planned">Planned</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </td>

                            <td class="action-cell">
                                <div class="inline-treatment-actions">
                                    <button
                                        type="submit"
                                        class="inline-treatment-save"
                                        form="addTreatmentForm"
                                    >
                                        Save
                                    </button>

                                    <button
                                        type="button"
                                        class="inline-treatment-cancel"
                                        id="cancelInlineTreatmentBtn"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <?php if (empty($treatments)): ?>
                            <tr id="noTreatmentRow">
                               <td colspan="11">
                                    <div class="empty-state">
                                        No treatment records yet. Click Add Procedure to create one.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($treatments as $treatment): ?>
                                <?php
                                    $treatmentId = (int) ($treatment['treatment_id'] ?? 0);
                                    $editFormId = 'updateTreatmentForm' . $treatmentId;

                                    $treatmentDate = $treatment['treatment_date'] ?? '';
                                    $treatmentDateValue = '';

                                    if (!empty($treatmentDate)) {
                                        $treatmentDateTimestamp = strtotime((string) $treatmentDate);
                                        $treatmentDateValue = $treatmentDateTimestamp ? date('Y-m-d', $treatmentDateTimestamp) : '';
                                    }

                                    $treatedTooth = $treatment['treated_tooth'] ?? '';
                                    $procedureName = $treatment['procedure_name'] ?? '';

                                    $dentistName = trim(
                                        (string) (($treatment['dentist_first_name'] ?? '') . ' ' . ($treatment['dentist_last_name'] ?? ''))
                                    );

                                    if ($dentistName !== '') {
                                        $dentistName = 'Dr. ' . $dentistName;
                                    }

                                    if ($dentistName === '') {
                                        $dentistName = $primaryDentistName;
                                    }

                                    $actualCharge = (float) ($treatment['actual_charge'] ?? 0);
                                    $amountPaid = (float) ($treatment['amount_paid'] ?? 0);

                                    $balance = isset($treatment['balance'])
                                        ? (float) $treatment['balance']
                                        : max(0, $actualCharge - $amountPaid);

                                    $treatmentStatus = trim((string) ($treatment['treatment_status'] ?? 'completed'));
                                    $treatmentStatusKey = strtolower(str_replace(' ', '_', $treatmentStatus));
                                 $treatmentAppointmentId = (int) ($treatment['appointment_id'] ?? 0);
$treatmentAppointment = $appointmentsById[$treatmentAppointmentId] ?? [];
                                ?>

                                <tr id="treatmentDisplayRow<?= (int) $treatmentId ?>">
                                    <td><?= formatDateText($treatmentDate) ?></td>
                                   

<td>
    <?php if (!empty($treatmentAppointment)): ?>
        <?= renderActualProcedureBox($treatmentAppointment) ?>
    <?php else: ?>
        <div class="actual-procedure-box">
            <div><strong>Started:</strong> —</div>
            <div><strong>Completed:</strong> —</div>
            <div><strong>Duration:</strong> —</div>
        </div>
    <?php endif; ?>
</td>
                                    <td><?= displayValue($treatedTooth) ?></td>
                                    <td><?= displayValue($procedureName) ?></td>
                                    <td><?= displayValue($dentistName) ?></td>

                                    <td>
                                        <span class="money-value"><?= e(formatMoneyText($actualCharge)) ?></span>
                                    </td>

                                    <td>
                                        <span class="money-value"><?= e(formatMoneyText($amountPaid)) ?></span>
                                    </td>

                                    <td>
                                        <span class="money-value"><?= e(formatMoneyText($balance)) ?></span>
                                    </td>

                                    <td>
                                        <span class="treatment-status <?= e($treatmentStatusKey) ?>">
                                            <?= e(statusText($treatmentStatus)) ?>
                                        </span>
                                    </td>

                                    <td class="action-cell">
                                        <?php if ($treatmentId > 0): ?>
                                            <button
                                                type="button"
                                                class="treatment-icon-btn"
                                                data-open-treatment-edit="<?= (int) $treatmentId ?>"
                                                aria-label="Edit treatment"
                                                title="Edit treatment"
                                            >
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M12 20h9"></path>
                                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"></path>
                                                </svg>
                                            </button>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <?php if ($treatmentId > 0): ?>
                                    <tr
                                        id="treatmentEditRow<?= (int) $treatmentId ?>"
                                        class="edit-treatment-row treatment-row-hidden"
                                    >
                                        <td>
                                            <input
                                                class="inline-treatment-input"
                                                type="date"
                                                name="treatment_date"
                                                value="<?= e($treatmentDateValue !== '' ? $treatmentDateValue : date('Y-m-d')) ?>"
                                                form="<?= e($editFormId) ?>"
                                                required
                                            >
                                        </td>

                                       

<td>
    <?php if (!empty($treatmentAppointment)): ?>
        <?= renderActualProcedureBox($treatmentAppointment) ?>
    <?php else: ?>
        <div class="actual-procedure-box">
            <div><strong>Started:</strong> —</div>
            <div><strong>Completed:</strong> —</div>
            <div><strong>Duration:</strong> —</div>
        </div>
    <?php endif; ?>
</td>




                                        <td>
                                            <input
                                                class="inline-treatment-input"
                                                type="text"
                                                name="treated_tooth"
                                                value="<?= e((string) $treatedTooth) ?>"
                                                form="<?= e($editFormId) ?>"
                                                placeholder="Example: 16, 26"
                                            >
                                        </td>

                                        <td>
                                            <input
                                                class="inline-treatment-input"
                                                type="text"
                                                name="procedure_name"
                                                value="<?= e((string) $procedureName) ?>"
                                                form="<?= e($editFormId) ?>"
                                                required
                                            >
                                        </td>

                                        <td><?= displayValue($dentistName) ?></td>

                                        <td>
                                            <input
                                                class="inline-treatment-input edit-actual-charge"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="actual_charge"
                                                value="<?= e(number_format($actualCharge, 2, '.', '')) ?>"
                                                form="<?= e($editFormId) ?>"
                                                data-treatment-id="<?= (int) $treatmentId ?>"
                                                required
                                            >
                                        </td>

                                        <td>
                                            <input
                                                class="inline-treatment-input edit-amount-paid"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="amount_paid"
                                                value="<?= e(number_format($amountPaid, 2, '.', '')) ?>"
                                                form="<?= e($editFormId) ?>"
                                                data-treatment-id="<?= (int) $treatmentId ?>"
                                                required
                                            >
                                        </td>

                                        <td>
                                            <input
                                                class="inline-treatment-input edit-balance-display"
                                                type="text"
                                                value="<?= e(number_format($balance, 2, '.', '')) ?>"
                                                data-treatment-id="<?= (int) $treatmentId ?>"
                                                readonly
                                            >
                                        </td>

                                        <td>
                                            <select
                                                class="inline-treatment-select"
                                                name="treatment_status"
                                                form="<?= e($editFormId) ?>"
                                                required
                                            >
                                                <option value="completed" <?= strtolower($treatmentStatus) === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="performed" <?= strtolower($treatmentStatus) === 'performed' ? 'selected' : '' ?>>Performed</option>
                                                <option value="planned" <?= strtolower($treatmentStatus) === 'planned' ? 'selected' : '' ?>>Planned</option>
                                                <option value="cancelled" <?= strtolower($treatmentStatus) === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </td>

                                        <td class="action-cell">
                                            <div class="inline-treatment-actions">
                                                <button
                                                    type="submit"
                                                    class="inline-treatment-save"
                                                    form="<?= e($editFormId) ?>"
                                                >
                                                    Save
                                                </button>

                                                <button
                                                    type="button"
                                                    class="inline-treatment-cancel"
                                                    data-cancel-treatment-edit="<?= (int) $treatmentId ?>"
                                                >
                                                    Cancel
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<section class="record-panel" data-panel="dental-chart">
    <div class="record-card">
        <div class="record-card-header">
            <h2 class="record-card-title">Intraoral Examination</h2>
        </div>

        <div class="record-card-body">
         
            <div class="intraoral-exam-header-actions">
                <button
                    type="button"
                    class="intraoral-open-chart-btn <?= $canOpenDentalChart ? '' : 'is-disabled' ?>"
                    data-open-odontogram-popup
                    <?= $canOpenDentalChart ? '' : 'disabled' ?>
                >
                    Open Full Odontogram
                </button>
            </div>

            <div class="intraoral-chart-wrap">
                <div class="intraoral-chart-strip" data-intraoral-chart>
                    <div class="intraoral-upper-row">
                        <?php foreach ($upperTeeth as $tooth): ?>
                            <?php
                                $toothEntries = $odontogramEntriesByTooth[$tooth] ?? $odontogramEntriesByTooth[(string) $tooth] ?? [];
                                $hasRecord = !empty($toothEntries);
                                $latestCondition = patientRecordLatestCondition($toothEntries);
                                $conditionClass = patientRecordConditionClass($latestCondition);
                                $conditionLabel = patientRecordConditionLabel($latestCondition);
                                $tooltip = patientRecordChartTooltip($toothEntries);
                                $imageCandidates = patientRecordToothImageCandidates((int) $tooth, $baseUrl);
                            ?>

                            <button
                                type="button"
                                class="intraoral-tooth <?= $hasRecord ? 'has-record condition-' . e($conditionClass) : 'condition-none' ?>"
                                data-intraoral-tooth="<?= (int) $tooth ?>"
                                data-intraoral-type="<?= e(patientRecordToothTypeLabel((int) $tooth)) ?>"
                                data-condition="<?= e($latestCondition) ?>"
                                data-condition-class="<?= e($conditionClass) ?>"
                                data-condition-label="<?= e($conditionLabel) ?>"
                                title="<?= e($tooltip) ?>"
                                aria-label="Tooth <?= (int) $tooth ?>"
                            >
                                <span class="intraoral-tooth-image">
                                    <img
                                        class="intraoral-tooth-img"
                                        src="<?= e($imageCandidates[0]) ?>"
                                        data-src-candidates="<?= e(json_encode($imageCandidates, JSON_UNESCAPED_SLASHES)) ?>"
                                        alt="Tooth <?= (int) $tooth ?>"
                                        loading="lazy"
                                    >
                                </span>

                                <span class="intraoral-tooth-number"><?= (int) $tooth ?></span>

                                <span class="intraoral-tooth-dots">
                                    <?php if (!empty($toothEntries)): ?>
                                        <?php foreach (array_slice($toothEntries, 0, 4) as $entry): ?>
                                            <?php
                                                $entryCondition = (string) ($entry['condition_code'] ?? $entry['procedure_name'] ?? '');
                                                $entryConditionClass = patientRecordConditionClass($entryCondition);
                                                $entryConditionLabel = patientRecordConditionLabel($entryCondition);
                                            ?>
                                            <i
                                                class="intraoral-dot <?= e($entryConditionClass) ?>"
                                                title="<?= e($entryConditionLabel) ?>"
                                            ></i>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="intraoral-lower-row">
                        <?php foreach ($lowerTeeth as $tooth): ?>
                            <?php
                                $toothEntries = $odontogramEntriesByTooth[$tooth] ?? $odontogramEntriesByTooth[(string) $tooth] ?? [];
                                $hasRecord = !empty($toothEntries);
                                $latestCondition = patientRecordLatestCondition($toothEntries);
                                $conditionClass = patientRecordConditionClass($latestCondition);
                                $conditionLabel = patientRecordConditionLabel($latestCondition);
                                $tooltip = patientRecordChartTooltip($toothEntries);
                                $imageCandidates = patientRecordToothImageCandidates((int) $tooth, $baseUrl);
                            ?>

                            <button
                                type="button"
                                class="intraoral-tooth <?= $hasRecord ? 'has-record condition-' . e($conditionClass) : 'condition-none' ?>"
                                data-intraoral-tooth="<?= (int) $tooth ?>"
                                data-intraoral-type="<?= e(patientRecordToothTypeLabel((int) $tooth)) ?>"
                                data-condition="<?= e($latestCondition) ?>"
                                data-condition-class="<?= e($conditionClass) ?>"
                                data-condition-label="<?= e($conditionLabel) ?>"
                                title="<?= e($tooltip) ?>"
                                aria-label="Tooth <?= (int) $tooth ?>"
                            >
                                <span class="intraoral-tooth-number"><?= (int) $tooth ?></span>

                                <span class="intraoral-tooth-image">
                                    <img
                                        class="intraoral-tooth-img"
                                        src="<?= e($imageCandidates[0]) ?>"
                                        data-src-candidates="<?= e(json_encode($imageCandidates, JSON_UNESCAPED_SLASHES)) ?>"
                                        alt="Tooth <?= (int) $tooth ?>"
                                        loading="lazy"
                                    >
                                </span>

                                <span class="intraoral-tooth-dots">
                                    <?php if (!empty($toothEntries)): ?>
                                        <?php foreach (array_slice($toothEntries, 0, 4) as $entry): ?>
                                            <?php
                                                $entryCondition = (string) ($entry['condition_code'] ?? $entry['procedure_name'] ?? '');
                                                $entryConditionClass = patientRecordConditionClass($entryCondition);
                                                $entryConditionLabel = patientRecordConditionLabel($entryCondition);
                                            ?>
                                            <i
                                                class="intraoral-dot <?= e($entryConditionClass) ?>"
                                                title="<?= e($entryConditionLabel) ?>"
                                            ></i>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="intraoral-legend">
                <div class="intraoral-legend-title">Color Coding Legend</div>

                <span class="intraoral-legend-item condition-caries">
                    <i class="intraoral-legend-dot"></i> Caries
                </span>

                <span class="intraoral-legend-item condition-restoration">
                    <i class="intraoral-legend-dot"></i> Restoration
                </span>

                <span class="intraoral-legend-item condition-missing">
                    <i class="intraoral-legend-dot"></i> Missing
                </span>

                <span class="intraoral-legend-item condition-fractured">
                    <i class="intraoral-legend-dot"></i> Fractured
                </span>

                <span class="intraoral-legend-item condition-root-canal">
                    <i class="intraoral-legend-dot"></i> Root Canal Treated
                </span>

                <span class="intraoral-legend-item condition-impacted">
                    <i class="intraoral-legend-dot"></i> Impacted
                </span>

                <span class="intraoral-legend-item condition-extraction">
                    <i class="intraoral-legend-dot"></i> For Extraction
                </span>

                <span class="intraoral-legend-item condition-crown">
                    <i class="intraoral-legend-dot"></i> Crown
                </span>

                <span class="intraoral-legend-item condition-pontic">
                    <i class="intraoral-legend-dot"></i> Pontic
                </span>

                <span class="intraoral-legend-item condition-sealant">
                    <i class="intraoral-legend-dot"></i> Sealant
                </span>

                <span class="intraoral-legend-item condition-sound">
                    <i class="intraoral-legend-dot"></i> Sound
                </span>

                <span class="intraoral-legend-item condition-other">
                    <i class="intraoral-legend-dot"></i> Other
                </span>
            </div>

            <div class="intraoral-selected-box">
                <div>
                    Selected tooth:
                    <strong id="intraoralSelectedTooth">None</strong>
                    <span id="intraoralSelectedType">—</span>
                </div>

                <div class="intraoral-selected-meta">
                    <span>Condition:</span>
                    <span id="intraoralSelectedCondition" class="intraoral-condition-chip none">No record</span>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="odontogram-popup-backdrop" id="odontogramPopupBackdrop" aria-hidden="true">
    <div class="odontogram-popup" role="dialog" aria-modal="true" aria-labelledby="odontogramPopupTitle">
        <div class="odontogram-popup-header">
            <div>
                <h3 class="odontogram-popup-title" id="odontogramPopupTitle">Full Odontogram</h3>
                <p class="odontogram-popup-subtitle">
                    Select a tooth, choose surface, mark condition, then save.
                </p>
            </div>

            <button type="button" class="odontogram-popup-close" data-close-odontogram-popup aria-label="Close">
                ×
            </button>
        </div>

        <div class="odontogram-popup-body">
            <div class="odontogram-popup-chart">
                <div class="odontogram-popup-strip" data-odontogram-popup-chart>
                    <div class="odo-popup-row odo-popup-upper">
                        <?php foreach ($upperTeeth as $tooth): ?>
                            <?php
                                $toothEntries = $odontogramEntriesByTooth[$tooth] ?? $odontogramEntriesByTooth[(string) $tooth] ?? [];
                                $hasRecord = !empty($toothEntries);
                                $latestCondition = patientRecordLatestCondition($toothEntries);
                                $conditionClass = patientRecordConditionClass($latestCondition);
                                $tooltip = patientRecordChartTooltip($toothEntries);
                                $imageCandidates = patientRecordToothImageCandidates((int) $tooth, $baseUrl);
                            ?>

                            <button
                                type="button"
                                class="odo-popup-tooth <?= $hasRecord ? 'has-record condition-' . e($conditionClass) : '' ?>"
                                data-popup-tooth="<?= (int) $tooth ?>"
                                data-popup-type="<?= e(patientRecordToothTypeLabel((int) $tooth)) ?>"
                                data-popup-condition="<?= e($latestCondition) ?>"
                                title="<?= e($tooltip) ?>"
                            >
                                <img
                                    class="intraoral-tooth-img"
                                    src="<?= e($imageCandidates[0]) ?>"
                                    data-src-candidates="<?= e(json_encode($imageCandidates, JSON_UNESCAPED_SLASHES)) ?>"
                                    alt="Tooth <?= (int) $tooth ?>"
                                    loading="lazy"
                                >
                                <span class="odo-popup-number"><?= (int) $tooth ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="odo-popup-row">
                        <?php foreach ($lowerTeeth as $tooth): ?>
                            <?php
                                $toothEntries = $odontogramEntriesByTooth[$tooth] ?? $odontogramEntriesByTooth[(string) $tooth] ?? [];
                                $hasRecord = !empty($toothEntries);
                                $latestCondition = patientRecordLatestCondition($toothEntries);
                                $conditionClass = patientRecordConditionClass($latestCondition);
                                $tooltip = patientRecordChartTooltip($toothEntries);
                                $imageCandidates = patientRecordToothImageCandidates((int) $tooth, $baseUrl);
                            ?>

                            <button
                                type="button"
                                class="odo-popup-tooth <?= $hasRecord ? 'has-record condition-' . e($conditionClass) : '' ?>"
                                data-popup-tooth="<?= (int) $tooth ?>"
                                data-popup-type="<?= e(patientRecordToothTypeLabel((int) $tooth)) ?>"
                                data-popup-condition="<?= e($latestCondition) ?>"
                                title="<?= e($tooltip) ?>"
                            >
                                <span class="odo-popup-number"><?= (int) $tooth ?></span>
                                <img
                                    class="intraoral-tooth-img"
                                    src="<?= e($imageCandidates[0]) ?>"
                                    data-src-candidates="<?= e(json_encode($imageCandidates, JSON_UNESCAPED_SLASHES)) ?>"
                                    alt="Tooth <?= (int) $tooth ?>"
                                    loading="lazy"
                                >
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <aside class="odontogram-mark-panel">
                <div class="odontogram-panel-section">
                    <div class="odontogram-selected-summary">
                        Selected tooth:
                        <strong id="popupSelectedTooth">None</strong>
                        <span id="popupSelectedType">—</span>
                    </div>
                </div>

                <div class="odontogram-panel-section">
                    <h4 class="odontogram-panel-title">Color Legend</h4>

                    <div class="odontogram-legend">
                        <span class="odontogram-legend-item condition-caries">
                            <i class="odontogram-legend-dot"></i> Caries
                        </span>
                        <span class="odontogram-legend-item condition-restoration">
                            <i class="odontogram-legend-dot"></i> Restoration
                        </span>
                        <span class="odontogram-legend-item condition-missing">
                            <i class="odontogram-legend-dot"></i> Missing
                        </span>
                        <span class="odontogram-legend-item condition-fractured">
                            <i class="odontogram-legend-dot"></i> Fractured
                        </span>
                        <span class="odontogram-legend-item condition-root-canal">
                            <i class="odontogram-legend-dot"></i> Root Canal
                        </span>
                        <span class="odontogram-legend-item condition-extraction">
                            <i class="odontogram-legend-dot"></i> Extraction
                        </span>
                        <span class="odontogram-legend-item condition-crown">
                            <i class="odontogram-legend-dot"></i> Crown
                        </span>
                        <span class="odontogram-legend-item condition-sound">
                            <i class="odontogram-legend-dot"></i> Sound
                        </span>
                    </div>
                </div>

                <div class="odontogram-panel-section">
                    <h4 class="odontogram-panel-title">Mark Tooth</h4>

                    <form method="POST" action="<?= e($baseUrl . '/dentist/patients/save-odontogram') ?>" id="odontogramPopupForm">
                        <?= Csrf::inputField(); ?>

                        <input type="hidden" name="patient_id" value="<?= (int) ($patient['patient_id'] ?? 0) ?>">
                        
                        <input type="hidden" name="appointment_id" value="<?= (int) $latestAppointmentId ?>">
                        <input type="hidden" name="tooth_number" id="popupToothNumber" value="">

                        <div class="odontogram-field">
                            <label class="odontogram-label">Tooth</label>
                            <input class="odontogram-control" id="popupToothReadonly" type="text" readonly>
                        </div>

                        <div class="odontogram-field">
                            <label class="odontogram-label">Surface</label>

                            <div class="odontogram-surface-grid">
                                <label class="odontogram-surface"><input type="checkbox" name="surfaces[]" value="M"> M</label>
                                <label class="odontogram-surface"><input type="checkbox" name="surfaces[]" value="D"> D</label>
                                <label class="odontogram-surface"><input type="checkbox" name="surfaces[]" value="F"> F</label>
                                <label class="odontogram-surface"><input type="checkbox" name="surfaces[]" value="L"> L</label>
                                <label class="odontogram-surface"><input type="checkbox" name="surfaces[]" value="B"> B</label>
                                <label class="odontogram-surface"><input type="checkbox" name="surfaces[]" value="P"> P</label>
                                <label class="odontogram-surface"><input type="checkbox" name="surfaces[]" value="I"> I</label>
                                <label class="odontogram-surface"><input type="checkbox" name="surfaces[]" value="O"> O</label>
                            </div>
                        </div>

                        <div class="odontogram-field">
                            <label class="odontogram-label" for="popupConditionCode">Condition</label>
                            <select class="odontogram-control" id="popupConditionCode" name="condition_code" required>
                                <option value="">Select condition</option>
                                <option value="Sound">Sound</option>
                                <option value="Caries">Caries</option>
                                <option value="Missing">Missing</option>
                                <option value="Restoration">Restoration</option>
                                <option value="Fractured">Fractured</option>
                                <option value="Root Canal Treated">Root Canal Treated</option>
                                <option value="Impacted">Impacted</option>
                                <option value="For Extraction">For Extraction</option>
                                <option value="Crown">Crown</option>
                                <option value="Pontic">Pontic</option>
                                <option value="Sealant">Sealant</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="odontogram-field">
                            <label class="odontogram-label" for="popupRemarks">Remarks</label>
                            <textarea
                                class="odontogram-control"
                                id="popupRemarks"
                                name="remarks"
                                maxlength="2000"
                                placeholder="Example: caries on occlusal surface"
                            ></textarea>
                        </div>

                        <div class="odontogram-actions">
                            <button type="button" class="odontogram-secondary-btn" data-close-odontogram-popup>
                                Cancel
                            </button>

                            <button type="submit" class="odontogram-primary-btn">
                                Save Mark
                            </button>
                        </div>
                    </form>
                </div>

                <div class="odontogram-panel-section">
                    <h4 class="odontogram-panel-title">Existing Marks</h4>
                    <div class="odontogram-existing-list" id="popupExistingMarks">
                        <div class="odontogram-existing-empty">Select a tooth to view saved marks.</div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

                <section class="record-panel" data-panel="appointment-history">
                    
                    <div class="record-card">
                        <div class="record-card-header">
                            <h2 class="record-card-title">Appointment History</h2>
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

                                                    if ($dentistName !== '') {
                                                        $dentistName = 'Dr. ' . $dentistName;
                                                    }
                                                ?>

                                                <tr>
                                                    <td><?= displayValue($appointment['appointment_code'] ?? '') ?></td>
                                                    <td><?= formatDateText($appointment['appointment_date'] ?? '') ?></td>
                                                    <td><?= formatAppointmentTime($appointment['start_time'] ?? '', $appointment['end_time'] ?? '') ?></td>
                                                    <td><?= displayValue($appointment['service_name'] ?? '') ?></td>
                                                    <td><?= displayValue($dentistName) ?></td>
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

<section class="record-panel" data-panel="documents">
    <div class="record-card">
        <div class="record-card-header">
            <h2 class="record-card-title">Patient Documents</h2>
        </div>

        <div class="record-card-body">
            <div class="document-layout">
                <div class="document-upload-card">
                    <h3 class="record-section-title">Upload Document</h3>

                    <form
                        method="POST"
                        action="<?= e($baseUrl . '/dentist/patients/upload-document') ?>"
                        enctype="multipart/form-data"
                        class="document-form-grid"
                    >
                        <?= Csrf::inputField(); ?>

                        <input type="hidden" name="patient_id" value="<?= (int) $patientIdValue ?>">
                        <input type="hidden" name="appointment_id" value="<?= (int) $latestAppointmentId ?>">

                        <div>
                            <label for="file_category">Document Type</label>
                            <select id="file_category" name="file_category" required>
                                <option value="xray">X-ray</option>
                                <option value="consent_form">Consent Form</option>
                                <option value="prescription">Prescription</option>
                                <option value="referral">Referral Letter</option>
                                <option value="medical_certificate">Medical Certificate</option>
                                <option value="lab_result">Lab Result</option>
                                <option value="treatment_photo">Treatment Photo</option>
                                <option value="other">Other Document</option>
                            </select>
                        </div>

                        <div>
                            <label for="document_file">File</label>
                            <input
                                id="document_file"
                                type="file"
                                name="document_file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                required
                            >
                        </div>

                        <div>
                            <label for="description">Description</label>
                            <textarea
                                id="description"
                                name="description"
                                maxlength="1000"
                                placeholder="Example: panoramic x-ray before braces treatment"
                            ></textarea>
                        </div>

                        <button type="submit" class="table-action">
                            Upload Document
                        </button>
                    </form>
                </div>

                <div class="document-preview-card">
                    <h3 class="record-section-title">Preview Document</h3>

                    <?php if ($latestDocumentId > 0 && is_array($latestDocument) && patientDocumentPreviewable($latestDocument)): ?>
                        <iframe
                            id="documentPreviewFrame"
                            class="document-preview-frame"
                            src="<?= e($latestDocumentPreviewUrl) ?>"
                            title="Document Preview"
                        ></iframe>
                    <?php else: ?>
                        <div id="documentPreviewEmpty" class="document-preview-empty">
                            No preview available. Upload or select a PDF/image document.
                        </div>

                        <iframe
                            id="documentPreviewFrame"
                            class="document-preview-frame"
                            src=""
                            title="Document Preview"
                            hidden
                        ></iframe>
                    <?php endif; ?>
                </div>
            </div>

            <div class="document-list-card" style="margin-top:16px;">
                <h3 class="record-section-title">Uploaded Documents</h3>

                <div class="appointment-wrap" style="margin-top:12px;">
                    <table class="appointment-table">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Uploaded By</th>
                                <th>Date</th>
                                <th>Size</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($documents)): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            No documents uploaded yet.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($documents as $document): ?>
                                <?php
                                    $attachmentId = (int) ($document['attachment_id'] ?? 0);
                                    $documentUrl = $baseUrl . '/dentist/patients/document?id=' . $attachmentId;
                                    $downloadUrl = $documentUrl . '&mode=download';
                                    $canPreviewDocument = patientDocumentPreviewable($document);
                                ?>

                                <tr>
                                    <td>
                                        <strong><?= e($document['original_file_name'] ?? 'Document') ?></strong>
                                        <small><?= e($document['mime_type'] ?? '') ?></small>
                                    </td>

                                    <td>
                                        <?= e(patientDocumentCategoryLabel((string) ($document['file_category'] ?? 'other'))) ?>
                                    </td>

                                    <td>
                                        <?= displayValue($document['description'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= e(patientDocumentUploaderName($document)) ?>
                                    </td>

                                    <td>
                                        <?= formatDateText($document['created_at'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= e(patientDocumentSize($document['file_size'] ?? 0)) ?>
                                    </td>

                                    <td>
                                        <div class="document-actions">
                                            <?php if ($canPreviewDocument): ?>
                                                <button
                                                    type="button"
                                                    class="table-action"
                                                    data-preview-document="<?= e($documentUrl) ?>"
                                                >
                                                    Preview
                                                </button>
                                            <?php endif; ?>

                                            <a
                                                class="table-action"
                                                href="<?= e($downloadUrl) ?>"
                                            >
                                                Download
                                            </a>

                                            <form
                                                method="POST"
                                                action="<?= e($baseUrl . '/dentist/patients/delete-document') ?>"
                                                onsubmit="return confirm('Delete this document?');"
                                            >
                                                <?= Csrf::inputField(); ?>
                                                <input type="hidden" name="patient_id" value="<?= (int) $patientIdValue ?>">
                                                <input type="hidden" name="attachment_id" value="<?= (int) $attachmentId ?>">

                                                <button type="submit" class="document-danger-btn">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>



            </main>

            <div class="print-footer">
                <button type="button" class="print-page-btn" onclick="window.print()">
                    Print Patient Record
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const tabs = document.querySelectorAll('[data-tab]');
    const panels = document.querySelectorAll('[data-panel]');
    const editableForms = document.querySelectorAll('[data-editable-form]');
    const toolbarTabs = document.querySelectorAll('[data-toolbar-tab]');

    const odontogramEntriesMap = <?= json_encode(
        $odontogramEntriesByTooth,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?> || {};

    function toNumber(value) {
        const number = parseFloat(String(value || '0').replace(/,/g, ''));
        return Number.isFinite(number) ? number : 0;
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function activateTab(target) {
        if (!target) {
            target = 'patient-record';
        }

        tabs.forEach(function (item) {
            item.classList.remove('active');
        });

        panels.forEach(function (panel) {
            panel.classList.remove('active');
        });

        const targetTab = document.querySelector('[data-tab="' + target + '"]');
        const targetPanel = document.querySelector('[data-panel="' + target + '"]');

        if (targetTab) {
            targetTab.classList.add('active');
        }

        if (targetPanel) {
            targetPanel.classList.add('active');
        }

        toolbarTabs.forEach(function (toolbarButton) {
            toolbarButton.classList.toggle(
                'active',
                toolbarButton.getAttribute('data-toolbar-tab') === target
            );
        });
    }

    function initializeTabs() {
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                const target = tab.getAttribute('data-tab');

                if (target) {
                    activateTab(target);
                }
            });
        });

        toolbarTabs.forEach(function (toolbarButton) {
            toolbarButton.addEventListener('click', function () {
                const target = toolbarButton.getAttribute('data-toolbar-tab');

                if (target) {
                    activateTab(target);
                }
            });
        });

        document.addEventListener('click', function (event) {
            const jumpButton = event.target.closest('[data-jump-tab]');

            if (!jumpButton) {
                return;
            }

            const target = jumpButton.getAttribute('data-jump-tab');

            if (target) {
                activateTab(target);
            }
        });

        const initialTab = new URLSearchParams(window.location.search).get('tab') || 'patient-record';
        activateTab(initialTab);
    }

    function setFormEditMode(form, isEditing) {
        const fields = form.querySelectorAll('input, select, textarea');
        const toggleButton = form.querySelector('[data-edit-toggle]');
        const cancelButton = form.querySelector('[data-edit-cancel]');

        form.dataset.readonly = isEditing ? '0' : '1';
        form.classList.toggle('is-editing', isEditing);

        fields.forEach(function (field) {
            if (field.type === 'hidden') {
                return;
            }

            field.disabled = !isEditing;
        });

        if (toggleButton) {
            const label = isEditing
                ? toggleButton.getAttribute('data-save-label')
                : toggleButton.getAttribute('data-edit-label');

            toggleButton.setAttribute('aria-label', label);
            toggleButton.setAttribute('title', label);
            toggleButton.setAttribute('aria-pressed', isEditing ? 'true' : 'false');
        }

        if (cancelButton) {
            cancelButton.setAttribute('aria-hidden', isEditing ? 'false' : 'true');
            cancelButton.tabIndex = isEditing ? 0 : -1;
        }
    }

    function initializeEditableForms() {
        editableForms.forEach(function (form) {
            const toggleButton = form.querySelector('[data-edit-toggle]');
            const cancelButton = form.querySelector('[data-edit-cancel]');

            setFormEditMode(form, false);

            if (toggleButton) {
                toggleButton.addEventListener('click', function () {
                    const isReadonly = form.dataset.readonly === '1';

                    if (isReadonly) {
                        setFormEditMode(form, true);

                        const firstEditable = form.querySelector(
                            'input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled)'
                        );

                        if (firstEditable) {
                            window.setTimeout(function () {
                                firstEditable.focus();
                            }, 120);
                        }

                        return;
                    }

                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                });
            }

            if (cancelButton) {
                cancelButton.addEventListener('click', function () {
                    form.reset();
                    setFormEditMode(form, false);
                });
            }
        });
    }

    function initializeStartNowHighlight() {
    const params = new URLSearchParams(window.location.search);
    const openedFromStart = params.get('from_start') === '1';

    if (!openedFromStart) {
        return;
    }

    const addButton = document.getElementById('addTreatmentInlineBtn');

    if (!addButton) {
        return;
    }

    window.setTimeout(function () {
        addButton.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

        addButton.focus({
            preventScroll: true
        });
    }, 350);
}

    function loadIntraoralImages() {
        document.querySelectorAll('.intraoral-tooth-img[data-src-candidates]').forEach(function (img) {
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
                    fallback.className = 'intraoral-missing-image';
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
    }

    function initializeIntraoralSelection() {
        const chart = document.querySelector('[data-intraoral-chart]');
        const selectedTooth = document.getElementById('intraoralSelectedTooth');
        const selectedType = document.getElementById('intraoralSelectedType');
        const selectedCondition = document.getElementById('intraoralSelectedCondition');

        if (!chart || !selectedTooth || !selectedType) {
            return;
        }

        function updateSelectedState(button) {
            const toothNumber = button.getAttribute('data-intraoral-tooth') || 'None';
            const toothType = button.getAttribute('data-intraoral-type') || '—';
            const conditionClass = button.getAttribute('data-condition-class') || 'none';
            const conditionLabel = button.getAttribute('data-condition-label') || 'No record';

            selectedTooth.textContent = toothNumber;
            selectedType.textContent = toothType;

            if (selectedCondition) {
                selectedCondition.textContent = conditionLabel;
                selectedCondition.className = 'intraoral-condition-chip ' + conditionClass;
            }
        }

        const toothButtons = chart.querySelectorAll('[data-intraoral-tooth]');

        toothButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                toothButtons.forEach(function (item) {
                    item.classList.remove('is-selected');
                });

                button.classList.add('is-selected');
                updateSelectedState(button);
            });
        });
    }

    function updateBalance(actualInput, paidInput, balanceInput) {
        if (!actualInput || !paidInput || !balanceInput) {
            return;
        }

        const balance = Math.max(0, toNumber(actualInput.value) - toNumber(paidInput.value));
        balanceInput.value = balance.toFixed(2);
    }

    function initializeTreatmentRows() {
        const addButton = document.getElementById('addTreatmentInlineBtn');
        const addRow = document.getElementById('inlineTreatmentRow');
        const noTreatmentRow = document.getElementById('noTreatmentRow');
        const cancelAddButton = document.getElementById('cancelInlineTreatmentBtn');

        const inlineTreatmentDate = document.getElementById('inlineTreatmentDate');
        const inlineProcedureName = document.getElementById('inlineProcedureName');
        const inlineActualCharge = document.getElementById('inlineActualCharge');
        const inlineAmountPaid = document.getElementById('inlineAmountPaid');
        const inlineBalanceDisplay = document.getElementById('inlineBalanceDisplay');

        if (addButton && addRow) {
            addButton.addEventListener('click', function () {
                addRow.hidden = false;

                if (noTreatmentRow) {
                    noTreatmentRow.hidden = true;
                }

                if (inlineTreatmentDate && addButton.dataset.autoDate) {
                    inlineTreatmentDate.value = addButton.dataset.autoDate;
                }

                if (inlineProcedureName && inlineProcedureName.value.trim() === '' && addButton.dataset.autoProcedure) {
                    inlineProcedureName.value = addButton.dataset.autoProcedure;
                }

                if (inlineActualCharge && toNumber(inlineActualCharge.value) <= 0 && addButton.dataset.autoCharge) {
                    inlineActualCharge.value = toNumber(addButton.dataset.autoCharge).toFixed(2);
                }

                updateBalance(inlineActualCharge, inlineAmountPaid, inlineBalanceDisplay);

                const firstInput = inlineProcedureName || addRow.querySelector('input, select');

                if (firstInput) {
                    window.setTimeout(function () {
                        firstInput.focus();
                    }, 80);
                }
            });
        }

        if (cancelAddButton && addRow) {
            cancelAddButton.addEventListener('click', function () {
                const addForm = document.getElementById('addTreatmentForm');

                if (addForm) {
                    addForm.reset();
                }

                addRow.hidden = true;

                if (noTreatmentRow) {
                    noTreatmentRow.hidden = false;
                }

                updateBalance(inlineActualCharge, inlineAmountPaid, inlineBalanceDisplay);
            });
        }

        if (inlineActualCharge) {
            inlineActualCharge.addEventListener('input', function () {
                updateBalance(inlineActualCharge, inlineAmountPaid, inlineBalanceDisplay);
            });
        }

        if (inlineAmountPaid) {
            inlineAmountPaid.addEventListener('input', function () {
                updateBalance(inlineActualCharge, inlineAmountPaid, inlineBalanceDisplay);
            });
        }

        document.querySelectorAll('[data-open-treatment-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                const treatmentId = button.getAttribute('data-open-treatment-edit');
                const displayRow = document.getElementById('treatmentDisplayRow' + treatmentId);
                const editRow = document.getElementById('treatmentEditRow' + treatmentId);

                if (!displayRow || !editRow) {
                    return;
                }

                displayRow.classList.add('treatment-row-hidden');
                editRow.classList.remove('treatment-row-hidden');

                const actualInput = editRow.querySelector('.edit-actual-charge');
                const paidInput = editRow.querySelector('.edit-amount-paid');
                const balanceInput = editRow.querySelector('.edit-balance-display');

                updateBalance(actualInput, paidInput, balanceInput);

                const firstInput = editRow.querySelector('input[name="procedure_name"], input, select');

                if (firstInput) {
                    window.setTimeout(function () {
                        firstInput.focus();
                    }, 80);
                }
            });
        });

        document.querySelectorAll('[data-cancel-treatment-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                const treatmentId = button.getAttribute('data-cancel-treatment-edit');
                const displayRow = document.getElementById('treatmentDisplayRow' + treatmentId);
                const editRow = document.getElementById('treatmentEditRow' + treatmentId);
                const form = document.getElementById('updateTreatmentForm' + treatmentId);

                if (form) {
                    form.reset();
                }

                if (editRow) {
                    editRow.classList.add('treatment-row-hidden');
                }

                if (displayRow) {
                    displayRow.classList.remove('treatment-row-hidden');
                }

                if (editRow) {
                    const actualInput = editRow.querySelector('.edit-actual-charge');
                    const paidInput = editRow.querySelector('.edit-amount-paid');
                    const balanceInput = editRow.querySelector('.edit-balance-display');

                    updateBalance(actualInput, paidInput, balanceInput);
                }
            });
        });

        document.querySelectorAll('.edit-treatment-row').forEach(function (row) {
            const actualInput = row.querySelector('.edit-actual-charge');
            const paidInput = row.querySelector('.edit-amount-paid');
            const balanceInput = row.querySelector('.edit-balance-display');

            if (actualInput) {
                actualInput.addEventListener('input', function () {
                    updateBalance(actualInput, paidInput, balanceInput);
                });
            }

            if (paidInput) {
                paidInput.addEventListener('input', function () {
                    updateBalance(actualInput, paidInput, balanceInput);
                });
            }

            updateBalance(actualInput, paidInput, balanceInput);
        });

        updateBalance(inlineActualCharge, inlineAmountPaid, inlineBalanceDisplay);
    }

    function initializeOdontogramPopup() {
        const popupBackdrop = document.getElementById('odontogramPopupBackdrop');
        const openPopupButton = document.querySelector('[data-open-odontogram-popup]');
        const closePopupButtons = document.querySelectorAll('[data-close-odontogram-popup]');
        const popupToothButtons = document.querySelectorAll('[data-popup-tooth]');
        const popupToothNumber = document.getElementById('popupToothNumber');
        const popupToothReadonly = document.getElementById('popupToothReadonly');
        const popupSelectedTooth = document.getElementById('popupSelectedTooth');
        const popupSelectedType = document.getElementById('popupSelectedType');
        const popupExistingMarks = document.getElementById('popupExistingMarks');
        const odontogramPopupForm = document.getElementById('odontogramPopupForm');

        if (!popupBackdrop) {
            return;
        }

        function clearPopupInputs() {
            if (!odontogramPopupForm) {
                return;
            }

            odontogramPopupForm.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
                checkbox.checked = false;
            });

            const condition = document.getElementById('popupConditionCode');
            const remarks = document.getElementById('popupRemarks');

            if (condition) {
                condition.value = '';
            }

            if (remarks) {
                remarks.value = '';
            }
        }

        function renderExistingMarks(toothNumber) {
            if (!popupExistingMarks) {
                return;
            }

            const items = odontogramEntriesMap[String(toothNumber)] || odontogramEntriesMap[toothNumber] || [];

            if (!items.length) {
                popupExistingMarks.innerHTML = '<div class="odontogram-existing-empty">No saved marks for this tooth yet.</div>';
                return;
            }

            popupExistingMarks.innerHTML = items.map(function (item) {
                const condition = escapeHtml(item.condition_code || item.procedure_name || 'Recorded finding');
                const surface = escapeHtml(item.surface || 'Whole tooth');
                const remarks = escapeHtml(item.remarks || item.notes || 'No remarks');
                const createdAt = escapeHtml(item.created_at || '');

                return `
                    <div class="odontogram-existing-item">
                        <div class="odontogram-existing-main">${condition}</div>
                        <div class="odontogram-existing-sub">
                            Surface: ${surface}<br>
                            Remarks: ${remarks}
                            ${createdAt ? '<br>Date: ' + createdAt : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }

        function selectPopupTooth(toothNumber) {
            toothNumber = String(toothNumber || '').trim();

            if (toothNumber === '') {
                return;
            }

            let selectedType = 'Tooth';

            popupToothButtons.forEach(function (button) {
                const isCurrent = button.getAttribute('data-popup-tooth') === toothNumber;

                button.classList.toggle('is-selected', isCurrent);

                if (isCurrent) {
                    selectedType = button.getAttribute('data-popup-type') || 'Tooth';
                }
            });

            if (popupToothNumber) {
                popupToothNumber.value = toothNumber;
            }

            if (popupToothReadonly) {
                popupToothReadonly.value = toothNumber + ' - ' + selectedType;
            }

            if (popupSelectedTooth) {
                popupSelectedTooth.textContent = toothNumber;
            }

            if (popupSelectedType) {
                popupSelectedType.textContent = selectedType;
            }

            clearPopupInputs();
            renderExistingMarks(toothNumber);
        }

        function openOdontogramPopup(preselectedTooth) {
            popupBackdrop.classList.add('is-open');
            popupBackdrop.setAttribute('aria-hidden', 'false');
            document.body.classList.add('odontogram-popup-open');

            if (preselectedTooth) {
                selectPopupTooth(preselectedTooth);
            }
        }

        function closeOdontogramPopup() {
            popupBackdrop.classList.remove('is-open');
            popupBackdrop.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('odontogram-popup-open');
        }

        if (openPopupButton) {
            openPopupButton.addEventListener('click', function () {
                const selectedToothText = document.getElementById('intraoralSelectedTooth');
                const selectedValue = selectedToothText ? selectedToothText.textContent.trim() : '';

                if (selectedValue && selectedValue !== 'None') {
                    openOdontogramPopup(selectedValue);
                    return;
                }

                openOdontogramPopup();
            });
        }

        closePopupButtons.forEach(function (button) {
            button.addEventListener('click', closeOdontogramPopup);
        });

        popupBackdrop.addEventListener('click', function (event) {
            if (event.target === popupBackdrop) {
                closeOdontogramPopup();
            }
        });

        popupToothButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                selectPopupTooth(button.getAttribute('data-popup-tooth'));
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeOdontogramPopup();
            }
        });

        if (odontogramPopupForm) {
            odontogramPopupForm.addEventListener('submit', function (event) {
                if (!popupToothNumber || popupToothNumber.value.trim() === '') {
                    event.preventDefault();
                    alert('Please select a tooth first.');
                    return;
                }

                const condition = document.getElementById('popupConditionCode');

                if (!condition || condition.value.trim() === '') {
                    event.preventDefault();
                    alert('Please select a condition.');
                }
            });
        }
    }

    initializeTabs();
    initializeEditableForms();
    initializeTreatmentRows();
    initializeOdontogramPopup();
    loadIntraoralImages();
    initializeIntraoralSelection();
    initializeStartNowHighlight();
    initializeDocumentPreview();



    function initializeDocumentPreview() {
    const previewFrame = document.getElementById('documentPreviewFrame');
    const previewEmpty = document.getElementById('documentPreviewEmpty');
    const latestPreviewButton = document.querySelector('[data-preview-latest-document]');

    function openDocumentPreview(url) {
        if (!url || !previewFrame) {
            return;
        }

        activateTab('documents');

        if (previewEmpty) {
            previewEmpty.hidden = true;
        }

        previewFrame.hidden = false;
        previewFrame.src = url;

        window.setTimeout(function () {
            previewFrame.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }, 120);
    }

    document.querySelectorAll('[data-preview-document]').forEach(function (button) {
        button.addEventListener('click', function () {
            openDocumentPreview(button.getAttribute('data-preview-document'));
        });
    });

    if (latestPreviewButton) {
        latestPreviewButton.addEventListener('click', function () {
            openDocumentPreview(latestPreviewButton.getAttribute('data-preview-latest-document'));
        });
    }
}
    
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const addBtn = document.getElementById('addTreatmentInlineBtn');
    const inlineRow = document.getElementById('inlineTreatmentRow');
    const noTreatmentRow = document.getElementById('noTreatmentRow');

    if (!addBtn || !inlineRow) {
        return;
    }

    function openInlineTreatmentRow() {
        inlineRow.hidden = false;

        if (noTreatmentRow) {
            noTreatmentRow.hidden = true;
        }

        const procedureInput = document.getElementById('inlineProcedureName');
        const chargeInput = document.getElementById('inlineActualCharge');
        const dateInput = document.getElementById('inlineTreatmentDate');

        if (procedureInput && procedureInput.value.trim() === '') {
            procedureInput.value = addBtn.dataset.autoProcedure || '';
        }

        if (chargeInput && parseFloat(chargeInput.value || '0') <= 0) {
            chargeInput.value = addBtn.dataset.autoCharge || '0.00';
        }

        if (dateInput && dateInput.value.trim() === '') {
            dateInput.value = addBtn.dataset.autoDate || '';
        }

        if (procedureInput) {
            procedureInput.focus();
        }
    }

    addBtn.addEventListener('click', function () {
        openInlineTreatmentRow();
    });

    const params = new URLSearchParams(window.location.search);

    if (params.get('from_start') === '1') {
        openInlineTreatmentRow();
    }
});
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Patient Record';
require __DIR__ . '/../layouts/app.php';
?>