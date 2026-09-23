<?php

namespace App\Services;

use App\Repositories\PrivacyConsentRepository;
use RuntimeException;

class PrivacyConsentService
{
    public const CONSENT_VERSION = 'DPA-RA10173-v1-2026-05';
    public const BOOKING_CONSENT_TYPE = 'booking_privacy_notice';

    public const BOOKING_CONSENT_TEXT =
        'I have read and understood the Privacy Notice and consent to the collection and processing of my personal and health information for appointment scheduling and dental clinic services.';

    private PrivacyConsentRepository $consents;

    public function __construct()
    {
        $this->consents = new PrivacyConsentRepository();
    }

    public function recordConsent(array $data): int
    {
        $accepted = (int) ($data['accepted'] ?? 0);

        if ($accepted !== 1) {
            throw new RuntimeException('Privacy consent is required before booking.');
        }

        $consentText = trim((string) ($data['consent_text'] ?? ''));

        if ($consentText === '') {
            throw new RuntimeException('Consent text is required.');
        }

        $consentType = trim((string) ($data['consent_type'] ?? self::BOOKING_CONSENT_TYPE));
        $consentVersion = trim((string) ($data['consent_version'] ?? self::CONSENT_VERSION));
        $sourceForm = trim((string) ($data['source_form'] ?? ''));

        if ($consentType === '') {
            throw new RuntimeException('Consent type is required.');
        }

        if ($consentVersion === '') {
            throw new RuntimeException('Consent version is required.');
        }

        if ($sourceForm === '') {
            throw new RuntimeException('Consent source form is required.');
        }

        return $this->consents->create([
            'user_id' => $this->nullableInt($data['user_id'] ?? null),
            'patient_id' => $this->nullableInt($data['patient_id'] ?? null),
            'appointment_request_id' => $this->nullableInt($data['appointment_request_id'] ?? null),
            'full_name' => $this->nullableText($data['full_name'] ?? null),
            'email' => $this->nullableText($data['email'] ?? null),
            'contact_number' => $this->nullableText($data['contact_number'] ?? null),
            'consent_type' => $consentType,
            'consent_version' => $consentVersion,
            'consent_text' => $consentText,
            'accepted' => 1,
            'source_form' => $sourceForm,
            'ip_address' => $this->ipAddress(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = (int) $value;
        return $value > 0 ? $value : null;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function ipAddress(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}