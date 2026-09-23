<?php

namespace App\Services;

use App\Repositories\NotificationRepository;

class NotificationService
{
    private NotificationRepository $notifications;

    public function __construct()
    {
        $this->notifications = new NotificationRepository();
    }

    public function notifyStaffNewAppointmentRequest(array $request): void
    {
        $name = $this->personName($request);
        $serviceName = trim((string) ($request['service_name'] ?? 'a service'));
        $preferredDate = trim((string) ($request['preferred_date'] ?? 'the selected date'));

        $this->notifications->notifyStaff(
            'appointment_request_created',
            'New Appointment Request',
            $name . ' submitted a request for ' . $serviceName . ' on ' . $preferredDate . '.',
            '/DentalClinic/public/staff/appointment-requests',
            'appointment_requests',
            (int) ($request['request_id'] ?? 0)
        );
    }

    public function notifyStaffRequestCancelled(array $request): void
    {
        $requestCode = trim((string) ($request['request_code'] ?? 'An appointment request'));

        $this->notifications->notifyStaff(
            'appointment_request_cancelled',
            'Appointment Request Cancelled',
            $requestCode . ' was cancelled by the requester.',
            '/DentalClinic/public/staff/appointment-requests',
            'appointment_requests',
            (int) ($request['request_id'] ?? 0)
        );
    }

    public function notifyStaffRequestUpdated(array $request): void
    {
        $requestCode = trim((string) ($request['request_code'] ?? 'An appointment request'));

        $this->notifications->notifyStaff(
            'appointment_request_updated',
            'Appointment Request Updated',
            $requestCode . ' was updated or rescheduled.',
            '/DentalClinic/public/staff/appointment-requests',
            'appointment_requests',
            (int) ($request['request_id'] ?? 0)
        );
    }

    public function notifyStaffNewMessage(?int $messageId = null): void
    {
        $this->notifications->notifyStaff(
            'clinic_message_created',
            'New Message',
            'A patient or guest sent a new clinic message.',
            '/DentalClinic/public/staff/messages',
            'messages',
            $messageId
        );
    }

    public function notifyStaffAppointmentToday(array $appointment): void
    {
        $appointmentId = (int) ($appointment['appointment_id'] ?? 0);

        $this->notifications->notifyStaff(
            'appointment_confirmed_today',
            'Appointment Confirmed for Today',
            'An appointment was confirmed for today.',
            '/DentalClinic/public/staff/appointments?date=' . date('Y-m-d'),
            'appointments',
            $appointmentId
        );
    }

    public function notifyStaffPatientCheckedIn(array $appointment): void
    {
        $patientName = $this->personName($appointment);

        $this->notifications->notifyStaff(
            'patient_checked_in',
            'Patient Checked In',
            $patientName . ' has checked in.',
            '/DentalClinic/public/staff/appointments?date=' . date('Y-m-d'),
            'appointments',
            (int) ($appointment['appointment_id'] ?? 0)
        );
    }

    public function notifyUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $linkUrl = null,
        ?string $relatedTable = null,
        ?int $relatedId = null,
        ?int $actorUserId = null
    ): void {
        $this->notifications->notifyUser(
            $userId,
            $type,
            $title,
            $message,
            $linkUrl,
            $relatedTable,
            $relatedId,
            $actorUserId
        );
    }

    private function personName(array $data): string
    {
        $name = trim((string) (
            ($data['patient_first_name'] ?? $data['guest_first_name'] ?? $data['first_name'] ?? '') . ' ' .
            ($data['patient_last_name'] ?? $data['guest_last_name'] ?? $data['last_name'] ?? '')
        ));

        return $name !== '' ? $name : 'A patient or guest';
    }
}