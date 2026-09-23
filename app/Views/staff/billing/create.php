<?php

use App\Core\Csrf;

$pageTitle = 'Create Billing';

$patients = $patients ?? [];
$appointments = $appointments ?? [];
$selectedPatientId = (int) ($selectedPatientId ?? 0);
$selectedAppointmentId = (int) ($selectedAppointmentId ?? 0);
$selectedAppointment = $selectedAppointment ?? null;
$existingBilling = $existingBilling ?? null;
$patientKeyword = $patientKeyword ?? '';
$old = $old ?? [];
$flash_error = $flash_error ?? null;
$flash_success = $flash_success ?? null;

$baseUrl = '/DentalClinic/public';

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('money')) {
    function money($value): string
    {
        return '₱' . number_format((float) $value, 2);
    }
}

if (!function_exists('patientLabel')) {
    function patientLabel(array $patient): string
    {
        $name = trim(implode(' ', array_filter([
            $patient['first_name'] ?? '',
            $patient['middle_name'] ?? '',
            $patient['last_name'] ?? '',
        ])));

        $code = trim((string) ($patient['patient_code'] ?? ''));
        $contact = trim((string) ($patient['contact_number'] ?? ''));

        return trim($name . ($code !== '' ? ' - ' . $code : '') . ($contact !== '' ? ' - ' . $contact : ''));
    }
}

if (!function_exists('appointmentLabel')) {
    function appointmentLabel(array $appointment): string
    {
        $date = $appointment['appointment_date'] ?? '';
        $time = $appointment['start_time'] ?? '';
        $service = $appointment['service_name'] ?? 'Appointment';
        $code = $appointment['appointment_code'] ?? '';

        $dateLabel = $date !== '' ? date('M d, Y', strtotime($date)) : '';
        $timeLabel = $time !== '' ? date('h:i A', strtotime($time)) : '';

        return trim($code . ' - ' . $dateLabel . ' ' . $timeLabel . ' - ' . $service);
    }
}

$appointmentPrice = 0.00;
if ($selectedAppointment) {
    $appointmentPrice = (float) ($selectedAppointment['estimated_price'] ?: ($selectedAppointment['service_estimated_price'] ?? 0));
}

ob_start();
?>

<link rel="stylesheet" href="<?= e($baseUrl) ?>/assets/css/staff-billing.css">

<div class="staff-billing-page">
    <div class="billing-header">
        <div>
            <p class="eyebrow">Staff Module</p>
            <h1>Create Billing</h1>
            <p class="muted">Create a bill for an existing patient and optionally connect it to an appointment.</p>
        </div>

        <a class="btn btn-light" href="<?= e($baseUrl) ?>/staff/billing">Back to Billing</a>
    </div>

    <?php if ($flash_success): ?>
        <div class="alert alert-success"><?= e($flash_success) ?></div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div class="alert alert-error"><?= e($flash_error) ?></div>
    <?php endif; ?>

    <?php if ($existingBilling): ?>
        <div class="alert alert-warning">
            This appointment already has billing record
            <strong><?= e($existingBilling['billing_number'] ?? '') ?></strong>.
            <a href="<?= e($baseUrl) ?>/staff/billing/show?id=<?= (int) $existingBilling['billing_id'] ?>">Open existing billing</a>
        </div>
    <?php endif; ?>

    <div class="billing-card">
        <form method="GET" action="<?= e($baseUrl) ?>/staff/billing/create" class="filter-form">
            <div class="form-group form-grow">
                <label for="patient_keyword">Find Patient</label>
                <input
                    type="text"
                    id="patient_keyword"
                    name="patient_keyword"
                    value="<?= e($patientKeyword) ?>"
                    placeholder="Search by name, code, contact number, or email"
                >
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary" type="submit">Search Patient</button>
                <a class="btn btn-light" href="<?= e($baseUrl) ?>/staff/patients/create">Create Patient</a>
            </div>
        </form>
    </div>

    <form method="POST" action="<?= e($baseUrl) ?>/staff/billing/store" id="billingCreateForm">
        <input type="hidden" name="_csrf_token" value="<?= e(Csrf::token()) ?>">

        <div class="billing-grid">
            <div class="billing-card">
                <h2>Patient and Appointment</h2>

                <div class="form-group">
                    <label for="patient_id">Patient <span class="required">*</span></label>
                    <select id="patient_id" name="patient_id" data-base-url="<?= e($baseUrl) ?>">
                        <option value="">Select patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <?php $pid = (int) ($patient['patient_id'] ?? 0); ?>
                            <option value="<?= $pid ?>" <?= $selectedPatientId === $pid ? 'selected' : '' ?>>
                                <?= e(patientLabel($patient)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Select a patient first to show their appointments.</small>
                </div>

                <div class="form-group">
                    <label for="appointment_id">Appointment Optional</label>
                    <select id="appointment_id" name="appointment_id" data-base-url="<?= e($baseUrl) ?>">
                        <option value="">No appointment / Manual billing</option>
                        <?php foreach ($appointments as $appointment): ?>
                            <?php
                            $aid = (int) ($appointment['appointment_id'] ?? 0);
                            $hasBilling = !empty($appointment['existing_billing_id']);
                            ?>
                            <option value="<?= $aid ?>" <?= $selectedAppointmentId === $aid ? 'selected' : '' ?> <?= $hasBilling ? 'disabled' : '' ?>>
                                <?= e(appointmentLabel($appointment)) ?><?= $hasBilling ? ' - Already billed' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($selectedAppointment): ?>
                    <div class="appointment-info">
                        <strong>Selected Appointment</strong>
                        <p><?= e(appointmentLabel($selectedAppointment)) ?></p>
                        <p>Dentist:
                            <?= e(trim(($selectedAppointment['dentist_first_name'] ?? '') . ' ' . ($selectedAppointment['dentist_last_name'] ?? '')) ?: 'Not assigned') ?>
                        </p>
                        <p>Status: <?= e(ucwords(str_replace('_', ' ', (string) ($selectedAppointment['status'] ?? '')))) ?></p>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="discount_amount">Discount Amount</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="discount_amount"
                        name="discount_amount"
                        value="<?= e($old['discount_amount'] ?? '0.00') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="4" placeholder="Optional billing notes"><?= e($old['remarks'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="billing-card">
                <h2>Billing Summary</h2>

                <div class="billing-preview">
                    <div>
                        <span>Subtotal</span>
                        <strong id="previewSubtotal">₱0.00</strong>
                    </div>
                    <div>
                        <span>Discount</span>
                        <strong id="previewDiscount">₱0.00</strong>
                    </div>
                    <div>
                        <span>Total Amount</span>
                        <strong id="previewTotal">₱0.00</strong>
                    </div>
                    <div>
                        <span>Initial Balance</span>
                        <strong id="previewBalance">₱0.00</strong>
                    </div>
                </div>

                <p class="muted small-note">
                    JavaScript only previews the amount. The final subtotal, total, and balance are recomputed by PHP when submitted.
                </p>

                <button class="btn btn-primary btn-full" type="submit" <?= $existingBilling ? 'disabled' : '' ?>>
                    Save Billing Record
                </button>
            </div>
        </div>

        <div class="billing-card">
            <div class="table-header">
                <div>
                    <h2>Billing Items</h2>
                    <p class="muted">Add service/procedure, quantity, and unit price.</p>
                </div>

                <button type="button" class="btn btn-light" id="addBillingItem">Add Item</button>
            </div>

            <div class="table-wrap">
                <table class="billing-table item-table">
                    <thead>
                        <tr>
                            <th>Service / Procedure</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Preview Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="billingItemsBody">
                        <?php if ($selectedAppointment && !empty($selectedAppointment['service_name'])): ?>
                            <tr class="billing-item-row">
                                <td>
                                    <input type="hidden" name="service_id[]" value="<?= (int) ($selectedAppointment['service_id'] ?? 0) ?>">
                                    <input type="text" name="item_name[]" value="<?= e($selectedAppointment['service_name']) ?>" required>
                                    <small>Auto-added from selected appointment</small>
                                </td>
                                <td>
                                    <input type="text" name="description[]" value="<?= e('Appointment service: ' . ($selectedAppointment['appointment_code'] ?? '')) ?>">
                                </td>
                                <td>
                                    <input class="item-qty" type="number" name="quantity[]" min="1" value="1" required>
                                </td>
                                <td>
                                    <input class="item-price" type="number" step="0.01" min="0" name="unit_price[]" value="<?= e(number_format($appointmentPrice, 2, '.', '')) ?>" required>
                                </td>
                                <td class="row-total">₱0.00</td>
                                <td>
                                    <button type="button" class="btn btn-small btn-danger remove-item">Remove</button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr class="billing-item-row">
                                <td>
                                    <input type="hidden" name="service_id[]" value="">
                                    <input type="text" name="item_name[]" placeholder="Example: Dental Cleaning" required>
                                </td>
                                <td>
                                    <input type="text" name="description[]" placeholder="Optional">
                                </td>
                                <td>
                                    <input class="item-qty" type="number" name="quantity[]" min="1" value="1" required>
                                </td>
                                <td>
                                    <input class="item-price" type="number" step="0.01" min="0" name="unit_price[]" value="0.00" required>
                                </td>
                                <td class="row-total">₱0.00</td>
                                <td>
                                    <button type="button" class="btn btn-small btn-danger remove-item">Remove</button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<script src="<?= e($baseUrl) ?>/assets/js/staff-billing.js"></script>

<?php
$staffContent = ob_get_clean();
require __DIR__ . '/../layouts/app.php';