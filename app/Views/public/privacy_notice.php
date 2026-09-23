<?php

if (!function_exists('privacy_notice_e')) {
    function privacy_notice_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$baseUrl = '/DentalClinic/public';

ob_start();
?>

<style>
.privacy-page {
    min-height: 100vh;
    background: #faf8f4;
    padding: 48px 16px;
    color: #243044;
}

.privacy-wrap {
    max-width: 960px;
    margin: 0 auto;
    background: #ffffff;
    border: 1px solid #e4e1da;
    border-radius: 10px;
    box-shadow: 0 8px 28px rgba(11, 31, 58, .08);
    padding: 34px;
}

.privacy-title {
    margin: 0 0 10px;
    font-size: 32px;
    color: #0b1f3a;
    line-height: 1.2;
}

.privacy-meta {
    color: #6e6860;
    font-size: 13px;
    margin-bottom: 26px;
}

.privacy-section {
    margin-top: 28px;
}

.privacy-section h2 {
    font-size: 20px;
    color: #0b1f3a;
    margin-bottom: 10px;
}

.privacy-section p,
.privacy-section li {
    font-size: 14px;
    line-height: 1.8;
    color: #3a3630;
}

.privacy-section ul {
    padding-left: 22px;
}

.privacy-actions {
    margin-top: 30px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.privacy-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 18px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 800;
    font-size: 13px;
}

.privacy-btn.primary {
    background: #0b1f3a;
    color: #ffffff;
}

.privacy-btn.secondary {
    border: 1px solid #d7dde8;
    color: #0b1f3a;
}
</style>

<div class="privacy-page">
    <main class="privacy-wrap">
        <h1 class="privacy-title">Privacy Notice</h1>
        <div class="privacy-meta">
            Data Privacy Act of 2012 / Republic Act No. 10173 Compliance Notice
        </div>

        <section class="privacy-section">
            <h2>1. Information We Collect</h2>
            <p>
                The clinic may collect personal, contact, appointment, medical, dental, billing, and communication information necessary to provide dental clinic services.
            </p>
            <ul>
                <li>Name, sex, birth date, civil status, address, occupation, contact number, and email address</li>
                <li>Appointment request details, selected dental services, preferred schedule, and dentist assignment</li>
                <li>Medical history, dental history, clinical findings, treatment records, prescriptions, recommendations, and uploaded attachments such as x-rays</li>
                <li>Billing, payment, balance, receipt, follow-up, reminder, and message records</li>
            </ul>
        </section>

        <section class="privacy-section">
            <h2>2. Purpose of Collection and Processing</h2>
            <p>
                Your information is collected and processed for appointment scheduling, identity verification, patient record management, dental examination and treatment, billing, follow-up care, reminders, clinic communications, security, audit logging, and legal or regulatory compliance.
            </p>
        </section>

        <section class="privacy-section">
            <h2>3. Who May Access Your Information</h2>
            <p>
                Access is limited according to user role and clinic responsibility.
            </p>
            <ul>
                <li>Staff may access information needed for appointment handling, patient assistance, billing, follow-ups, and clinic operations.</li>
                <li>Dentists may access records connected to their assigned patients, appointments, examinations, and treatments.</li>
                <li>Patients may access their own allowed portal information only.</li>
                <li>Administrators may access information needed for authorized system, security, and compliance management.</li>
            </ul>
        </section>

        <section class="privacy-section">
            <h2>4. Data Protection Measures</h2>
            <p>
                The system uses role-based access control, authenticated sessions, CSRF protection, prepared database statements, audit logs, and access restrictions to help protect patient data from unauthorized access, misuse, alteration, or disclosure.
            </p>
        </section>

        <section class="privacy-section">
            <h2>5. Data Retention and Archiving</h2>
            <p>
                Patient and clinical records are retained as needed for dental care continuity, clinic administration, legal compliance, dispute handling, and recordkeeping obligations. Clinical records are not automatically hard-deleted. When appropriate, records may be archived or blocked from routine use after review.
            </p>
        </section>

        <section class="privacy-section">
            <h2>6. Your Rights</h2>
            <p>
                As a data subject, you may request access, correction, deletion or blocking where applicable, object to certain processing, and submit privacy-related concerns. Requests are reviewed based on identity verification, clinic policy, applicable laws, and patient safety or medical recordkeeping requirements.
            </p>
        </section>

        <section class="privacy-section">
            <h2>7. How to Submit a Request</h2>
            <p>
                You may submit a privacy request online or contact the clinic directly. The clinic may ask for verification before acting on a request to protect patient confidentiality.
            </p>
        </section>

        <section class="privacy-section">
            <h2>8. Privacy Contact</h2>
            <p>
                For privacy concerns, contact the clinic staff or Data Protection Officer through the clinic’s official contact number, email, or front desk.
            </p>
        </section>

        <div class="privacy-actions">
            <a class="privacy-btn primary" href="<?= privacy_notice_e($baseUrl . '/privacy-request') ?>">Submit Privacy Request</a>
            <a class="privacy-btn secondary" href="<?= privacy_notice_e($baseUrl . '/') ?>">Back to Home</a>
        </div>
    </main>
</div>

<?php
$content = ob_get_clean();
$title = 'Privacy Notice';

$layout = __DIR__ . '/../layouts/main.php';

if (is_file($layout)) {
    require $layout;
} else {
    echo $content;
}