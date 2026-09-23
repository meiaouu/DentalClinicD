<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\AppointmentRepository;
use App\Repositories\AppointmentRequestRepository;
use App\Repositories\AppointmentStatusLogRepository;
use App\Repositories\ServiceRepository;
use DateInterval;
use DateTime;
use RuntimeException;

class AppointmentRequestReviewService
{
    private AppointmentRequestRepository $requests;
    private AppointmentRepository $appointments;
    private AppointmentStatusLogRepository $statusLogs;
    private ServiceRepository $services;
    private BookingAvailabilityService $availability;

    public function __construct()
    {
        $this->requests = new AppointmentRequestRepository();
        $this->appointments = new AppointmentRepository();
        $this->statusLogs = new AppointmentStatusLogRepository();
        $this->services = new ServiceRepository();
        $this->availability = new BookingAvailabilityService();
    }

    public function confirm(
        int $requestId,
        int $dentistId,
        string $appointmentDate,
        string $startTime,
        int $staffUserId,
        ?string $staffNotes = null
    ): array {
        $request = $this->requests->findDetailedById($requestId);

        if (!$request) {
            throw new RuntimeException('Appointment request not found.');
        }

        $currentStatus = (string) ($request['request_status'] ?? '');

        if (!in_array($currentStatus, ['pending', 'under_review', 'rescheduled'], true)) {
            throw new RuntimeException('Only pending or reviewable requests can be confirmed.');
        }

        $service = $this->services->findById((int) $request['service_id']);

        if (!$service) {
            throw new RuntimeException('Service not found.');
        }

        $duration = (int) ($service['estimated_duration_minutes'] ?? 0);

        if ($duration <= 0) {
            throw new RuntimeException('Invalid service duration.');
        }

        $normalizedStart = $this->normalizeTime($startTime);

        $this->availability->ensureSlotStillAvailable(
            $appointmentDate,
            $normalizedStart,
            (int) $request['service_id'],
            $dentistId
        );

        $start = new DateTime($appointmentDate . ' ' . $normalizedStart);
        $end = clone $start;
        $end->add(new DateInterval('PT' . $duration . 'M'));

        $appointmentCode = 'APT-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $appointmentId = $this->appointments->create([
                'appointment_code' => $appointmentCode,
                'request_id' => (int) $request['request_id'],
                'dentist_id' => $dentistId,
                'patient_id' => !empty($request['patient_id']) ? (int) $request['patient_id'] : null,
                'service_id' => (int) $request['service_id'],
                'appointment_date' => $appointmentDate,
                'start_time' => $start->format('H:i:s'),
                'end_time' => $end->format('H:i:s'),
                'estimated_duration_minutes' => $duration,
                'estimated_price' => (float) ($service['estimated_price'] ?? 0),
                'status' => 'confirmed',
                'arrival_status' => 'pending',
                'grace_period_minutes' => 30,
                'booked_by' => $staffUserId,
                'confirmed_by' => $staffUserId,
                'remarks' => $staffNotes,
            ]);

            $this->statusLogs->create([
                'appointment_id' => $appointmentId,
                'old_status' => $currentStatus,
                'new_status' => 'confirmed',
                'changed_by' => $staffUserId,
                'remarks' => $staffNotes ?: 'Appointment request confirmed.',
            ]);

            $this->requests->updateAfterConfirmation(
                (int) $request['request_id'],
                $appointmentId,
                $staffUserId,
                $staffNotes
            );

            $db->commit();

            return [
                'appointment_id' => $appointmentId,
                'appointment_code' => $appointmentCode,
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function reject(
        int $requestId,
        int $staffUserId,
        ?string $staffNotes = null
    ): void {
        $request = $this->requests->findDetailedById($requestId);

        if (!$request) {
            throw new RuntimeException('Appointment request not found.');
        }

        $currentStatus = (string) ($request['request_status'] ?? '');

        if (!in_array($currentStatus, ['pending', 'under_review', 'rescheduled'], true)) {
            throw new RuntimeException('This request can no longer be rejected.');
        }

        $this->requests->updateStatus(
            $requestId,
            'rejected',
            $staffUserId,
            $staffNotes
        );
    }

    public function reschedule(
        int $requestId,
        string $preferredDate,
        string $preferredStartTime,
        int $staffUserId,
        ?string $staffNotes = null
    ): void {
        $request = $this->requests->findDetailedById($requestId);

        if (!$request) {
            throw new RuntimeException('Appointment request not found.');
        }

        $currentStatus = (string) ($request['request_status'] ?? '');

        if (!in_array($currentStatus, ['pending', 'under_review', 'rescheduled'], true)) {
            throw new RuntimeException('This request can no longer be rescheduled.');
        }

        $normalizedStart = $this->normalizeTime($preferredStartTime);

        $this->availability->ensureSlotStillAvailable(
            $preferredDate,
            $normalizedStart,
            (int) $request['service_id'],
            null
        );

        $this->requests->updateReschedule(
            $requestId,
            $preferredDate,
            $normalizedStart,
            $staffUserId,
            $staffNotes
        );
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }
}