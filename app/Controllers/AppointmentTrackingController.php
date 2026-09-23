<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AppointmentRequestRepository;
use App\Repositories\AuditLogRepository;

class AppointmentTrackingController
{
    private AppointmentRequestRepository $requests;
    private AuditLogRepository $auditLogs;

    public function __construct()
    {
        $this->requests = new AppointmentRequestRepository();
        $this->auditLogs = new AuditLogRepository();
    }

    public function form(): void
    {
        View::render('public.booking.track', [
            'errors' => Session::get('errors', []),
            'old' => Session::get('old', []),
            'result' => Session::get('tracking_result'),
            'answers' => Session::get('tracking_answers', []),
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('errors');
        Session::remove('old');
        Session::remove('tracking_result');
        Session::remove('tracking_answers');
        Session::remove('flash_success');
        Session::remove('flash_error');
    }

   public function search(): void
{
    if (!\App\Core\Csrf::verify($_POST['_csrf_token'] ?? '')) {
        \App\Core\View::render('public.booking.track', [
            'errors' => [],
            'old' => $_POST,
            'result' => null,
            'answers' => [],
            'flash_error' => 'Invalid CSRF token. Please try again.',
        ]);
        return;
    }

    $requestCode = strtoupper(trim((string) ($_POST['request_code'] ?? '')));
    $contactNumber = trim((string) ($_POST['contact_number'] ?? ''));

    $errors = [];

    if ($requestCode === '') {
        $errors['request_code'][] = 'Request code is required.';
    }

    if ($contactNumber === '') {
        $errors['contact_number'][] = 'Mobile number is required.';
    }

    if (!empty($errors)) {
        \App\Core\View::render('public.booking.track', [
            'errors' => $errors,
            'old' => $_POST,
            'result' => null,
            'answers' => [],
        ]);
        return;
    }

    $requests = new \App\Repositories\AppointmentRequestRepository();

    $result = $requests->findByRequestCodeAndContact($requestCode, $contactNumber);

    if (!$result) {
        \App\Core\View::render('public.booking.track', [
            'errors' => [],
            'old' => $_POST,
            'result' => null,
            'answers' => [],
            'flash_error' => 'No appointment request found using those details.',
        ]);
        return;
    }

    $answers = $requests->getAnswersByRequestId((int) $result['request_id']);

    \App\Core\View::render('public.booking.track', [
        'errors' => [],
        'old' => $_POST,
        'result' => $result,
        'answers' => $answers,
        'flash_success' => 'Appointment request found.',
    ]);
}

    public function cancel(): void
    {
        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $requestCode = strtoupper(trim((string) ($_POST['request_code'] ?? '')));
        $contactNumber = trim((string) ($_POST['contact_number'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($requestCode === '' || !$this->isValidPhilippineMobile($contactNumber)) {
            Session::set('flash_error', 'Invalid request cancellation attempt.');
            header('Location: /track-request');
            exit;
        }

        $normalizedContact = $this->normalizeMobile($contactNumber);
        $request = $this->requests->findByRequestCodeAndContact($requestCode, $normalizedContact);

        if (!$request) {
            Session::set('flash_error', 'Request not found.');
            header('Location: /track-request');
            exit;
        }

        try {
            $this->requests->cancelPendingByGuest(
                (int) $request['request_id'],
                $reason !== '' ? ('Cancelled by patient. Reason: ' . $reason) : 'Cancelled by patient.'
            );

            $this->auditLogs->create([
                'user_id' => null,
                'module_name' => 'appointment_requests',
                'action_name' => 'cancel_by_patient',
                'record_type' => 'appointment_request',
                'record_id' => (int) $request['request_id'],
                'description' => 'Guest/patient cancelled pending request via tracking page.',
            ]);

            Session::set('flash_success', 'Appointment request cancelled successfully.');
        } catch (\Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        header('Location: /track-request');
        exit;
    }

    private function isValidPhilippineMobile(string $value): bool
    {
        $normalized = preg_replace('/\D+/', '', $value ?? '');
        if ($normalized === null) {
            return false;
        }

        if (str_starts_with($normalized, '63')) {
            return strlen($normalized) === 12;
        }

        if (str_starts_with($normalized, '09')) {
            return strlen($normalized) === 11;
        }

        return false;
    }

    private function normalizeMobile(string $value): string
    {
        $normalized = preg_replace('/\D+/', '', $value ?? '') ?? '';

        if (str_starts_with($normalized, '09')) {
            return $normalized;
        }

        if (str_starts_with($normalized, '63')) {
            return $normalized;
        }

        return $normalized;
    }
}