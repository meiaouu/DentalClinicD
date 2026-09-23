<?php $patient = $patient ?? []; ?>
<div class="card">
    <h1 style="margin-top:0;">Edit Patient</h1>
    <form method="post" action="/DentalClinic/public/staff/patients/update">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <input type="hidden" name="patient_id" value="<?= (int) $patient['patient_id'] ?>">
        <input type="text" name="first_name" value="<?= htmlspecialchars((string) ($patient['first_name'] ?? '')) ?>" required>
        <input type="text" name="middle_name" value="<?= htmlspecialchars((string) ($patient['middle_name'] ?? '')) ?>">
        <input type="text" name="last_name" value="<?= htmlspecialchars((string) ($patient['last_name'] ?? '')) ?>" required>
        <input type="text" name="sex" value="<?= htmlspecialchars((string) ($patient['sex'] ?? '')) ?>">
        <input type="date" name="birth_date" value="<?= htmlspecialchars((string) ($patient['birth_date'] ?? '')) ?>">
        <input type="text" name="civil_status" value="<?= htmlspecialchars((string) ($patient['civil_status'] ?? '')) ?>">
        <textarea name="address"><?= htmlspecialchars((string) ($patient['address'] ?? '')) ?></textarea>
        <input type="text" name="occupation" value="<?= htmlspecialchars((string) ($patient['occupation'] ?? '')) ?>">
        <input type="text" name="contact_number" value="<?= htmlspecialchars((string) ($patient['contact_number'] ?? '')) ?>" required>
        <input type="email" name="email" value="<?= htmlspecialchars((string) ($patient['email'] ?? '')) ?>">
        <input type="text" name="emergency_contact_name" value="<?= htmlspecialchars((string) ($patient['emergency_contact_name'] ?? '')) ?>">
        <input type="text" name="emergency_contact_number" value="<?= htmlspecialchars((string) ($patient['emergency_contact_number'] ?? '')) ?>">
        <textarea name="notes"><?= htmlspecialchars((string) ($patient['notes'] ?? '')) ?></textarea>
        <input type="hidden" name="profile_status" value="<?= htmlspecialchars((string) ($patient['profile_status'] ?? 'active')) ?>">
        <button type="submit">Update Patient</button>
    </form>
</div>