<?php

use App\Core\Auth;
use App\Core\Csrf;

$user = Auth::user();

$patient = $patient ?? null;
$hasMedicalHistory = $hasMedicalHistory ?? false;
$hasDentalHistory = $hasDentalHistory ?? false;
$accountStatus = strtolower((string) ($accountStatus ?? ($user['account_status'] ?? 'pending_review')));

$completedRequirements = $hasMedicalHistory && $hasDentalHistory;

if (!function_exists('pending_e')) {
    function pending_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

ob_start();
?>

<div class="container" style="max-width: 860px; margin: 50px auto; padding: 0 16px;">
    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 30px; box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);">
        <?php if ($accountStatus === 'rejected'): ?>
            <h1 style="margin-top: 0; color: #991b1b;">Registration Not Approved</h1>
            <p>Your registration was not approved. Please contact the clinic.</p>

        <?php else: ?>
            <h1 style="margin-top: 0; color: #10233f;">Complete Your Patient Information</h1>

            <p style="line-height: 1.6; color: #374151;">
                Your account has been created. Before full dashboard access is enabled, please complete the required patient information below.
                Clinic staff will review your registration after your details are complete.
            </p>

            <div style="display: grid; gap: 14px; margin: 24px 0;">
                <div style="border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px;">
                    <strong>Medical History</strong>
                    <p style="margin: 8px 0; color: #64748b;">
                        Status:
                        <?php if ($hasMedicalHistory): ?>
                            <span style="color: #047857; font-weight: 700;">Completed</span>
                        <?php else: ?>
                            <span style="color: #b45309; font-weight: 700;">Required</span>
                        <?php endif; ?>
                    </p>

                    <?php if (!$hasMedicalHistory): ?>
                        <a href="/DentalClinic/public/patient/medical-history" style="display: inline-block; padding: 10px 14px; border-radius: 10px; background: #0d9e8c; color: #fff; text-decoration: none; font-weight: 700;">
                            Fill Medical History
                        </a>
                    <?php endif; ?>
                </div>

                <div style="border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px;">
                    <strong>Dental History</strong>
                    <p style="margin: 8px 0; color: #64748b;">
                        Status:
                        <?php if ($hasDentalHistory): ?>
                            <span style="color: #047857; font-weight: 700;">Completed</span>
                        <?php else: ?>
                            <span style="color: #b45309; font-weight: 700;">Required</span>
                        <?php endif; ?>
                    </p>

                    <?php if (!$hasDentalHistory): ?>
                        <a href="/DentalClinic/public/patient/medical-history" style="display: inline-block; padding: 10px 14px; border-radius: 10px; background: #0d9e8c; color: #fff; text-decoration: none; font-weight: 700;">
                            Fill Dental History
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($completedRequirements): ?>
                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 14px; padding: 14px; margin-bottom: 20px;">
                    Your required patient information is complete. Please wait for staff approval.
                </div>
            <?php else: ?>
                <div style="background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 14px; padding: 14px; margin-bottom: 20px;">
                    Please complete both Medical History and Dental History before staff verification.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="/DentalClinic/public/logout">
            <?= Csrf::inputField(); ?>
            <button type="submit" style="width: 100%; padding: 12px 18px; border: 0; border-radius: 12px; background: #0d9e8c; color: #fff; font-weight: 800;">
                Logout
            </button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Complete Patient Information';
require __DIR__ . '/../layouts/main.php';