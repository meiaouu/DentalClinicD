<?php

namespace App\Services;

use App\Repositories\PrivacyRequestRepository;
use RuntimeException;

class PrivacyRequestService
{
    public const ALLOWED_REQUEST_TYPES = [
        'access',
        'correction',
        'deletion_blocking',
        'objection',
        'other',
    ];

    public const ALLOWED_STATUSES = [
        'pending',
        'reviewing',
        'approved',
        'rejected',
        'completed',
    ];

    private PrivacyRequestRepository $requests;
    private PrivacyAuditService $audit;

    public function __construct()
    {
        $this->requests = new PrivacyRequestRepository();
        $this->audit = new PrivacyAuditService();
    }

    public function submitRequest(array $data): int
    {
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $contactNumber = trim((string) ($data['contact_number'] ?? ''));
        $requestType = trim((string) ($data['request_type'] ?? ''));
        $requestDetails = trim((string) ($data['request_details'] ?? ''));

        if ($fullName === '') {
            throw new RuntimeException('Full name is required.');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email address.');
        }

        if ($contactNumber === '' && $email === '') {
            throw new RuntimeException('Please provide an email address or contact number.');
        }

        if (!in_array($requestType, self::ALLOWED_REQUEST_TYPES, true)) {
            throw new RuntimeException('Invalid request type.');
        }

        if ($requestDetails === '') {
            throw new RuntimeException('Request details are required.');
        }

        $requestId = $this->requests->create([
            'user_id' => $this->nullableInt($data['user_id'] ?? null),
            'patient_id' => $this->nullableInt($data['patient_id'] ?? null),
            'full_name' => $fullName,
            'email' => $email !== '' ? $email : null,
            'contact_number' => $contactNumber !== '' ? $contactNumber : null,
            'request_type' => $requestType,
            'request_details' => $requestDetails,
            'status' => 'pending',
        ]);

        $this->audit->log(
            $this->nullableInt($data['user_id'] ?? null),
            'privacy_requests',
            'submit',
            'privacy_request',
            $requestId,
            'Submitted privacy request.'
        );

        return $requestId;
    }

    public function updateRequestStatus(int $id, string $status, string $responseNotes, int $staffUserId): void
    {
        if ($id <= 0) {
            throw new RuntimeException('Privacy request not found.');
        }

        if ($staffUserId <= 0) {
            throw new RuntimeException('Invalid staff user.');
        }

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new RuntimeException('Invalid request status.');
        }

        $existing = $this->requests->findById($id);

        if (!$existing) {
            throw new RuntimeException('Privacy request not found.');
        }

        $updated = $this->requests->updateStatus($id, $status, trim($responseNotes), $staffUserId);

        if (!$updated) {
            throw new RuntimeException('Something went wrong. Please try again.');
        }

        $this->audit->log(
            $staffUserId,
            'privacy_requests',
            'update_status',
            'privacy_request',
            $id,
            'Updated privacy request status to ' . $status . '.'
        );
    }

    public function ensurePatientOwnsRequest(int $requestId, int $patientId): void
    {
        if ($requestId <= 0 || $patientId <= 0) {
            throw new RuntimeException('Privacy request not found.');
        }

        $request = $this->requests->findById($requestId);

        if (!$request || (int) ($request['patient_id'] ?? 0) !== $patientId) {
            throw new RuntimeException('You are not allowed to view this request.');
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = (int) $value;
        return $value > 0 ? $value : null;
    }
}