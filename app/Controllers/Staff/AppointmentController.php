<?php

namespace App\Controllers\Staff;

use RuntimeException;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AppointmentRepository;
use App\Repositories\PatientRepository;
use App\Repositories\ScheduleRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\AppointmentRequestRepository;
use App\Repositories\NotificationRepository;
use App\Services\AppointmentStatusService;
use App\Services\BookingAvailabilityService;
use App\Services\PatientConversionService;
use DateInterval;
use DateTime;
use App\Services\AppointmentProcedureWorkflowService;



class AppointmentController
{
    private AppointmentRepository $appointments;
    private PatientRepository $patients;
    private ServiceRepository $services;
    private ScheduleRepository $schedules;
    private BookingAvailabilityService $availability;
    private AppointmentStatusService $statusService;
    private PatientConversionService $patientConversion;
    private AppointmentRequestRepository $requests;
    private NotificationRepository $notifications;
    private AppointmentProcedureWorkflowService $procedureWorkflow;

    public function __construct()
    {
        $this->appointments = new AppointmentRepository();
        $this->patients = new PatientRepository();
        $this->services = new ServiceRepository();
        $this->schedules = new ScheduleRepository();
        $this->availability = new BookingAvailabilityService();
        $this->statusService = new AppointmentStatusService();
        $this->patientConversion = new PatientConversionService();
        $this->requests = new AppointmentRequestRepository();
        $this->notifications = new NotificationRepository();
        $this->procedureWorkflow = new AppointmentProcedureWorkflowService($this->appointments);
    }

    public function index(): void
    {
        Auth::requireRole('staff');

        $date = trim((string) ($_GET['date'] ?? date('Y-m-d')));
        $status = trim((string) ($_GET['status'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $staffUser = Auth::user();
        $this->autoMarkMissedAppointmentsAsNoShow((int) ($staffUser['user_id'] ?? 0));

        $appointments = $this->appointments->paginateByDate(
            $date,
            $status !== '' ? $status : null,
            $perPage,
            $offset
        );

        $total = $this->appointments->countByDate(
            $date,
            $status !== '' ? $status : null
        );

        $waitingQueue = $this->appointments->getByDateAndStatus($date, ['checked_in']);
        $inProgress = $this->appointments->getByDateAndStatus($date, ['in_progress']);
        $completed = $this->appointments->getByDateAndStatus($date, ['completed']);

        $nextPatient = !empty($waitingQueue) ? $waitingQueue[0] : null;

        $statusCounts = [
            '' => $this->appointments->countByDate($date, null),
            'rescheduled' => $this->appointments->countByDate($date, 'rescheduled'),
            'completed' => $this->appointments->countByDate($date, 'completed'),
            'no_show' => $this->appointments->countByDate($date, 'no_show'),
            'cancelled' => $this->appointments->countByDate($date, 'cancelled'),
        ];

        View::render('staff.appointments.index', [
            'appointments' => $appointments,
            'date' => $date,
            'status' => $status,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'statusCounts' => $statusCounts,
            'waitingQueue' => $waitingQueue,
            'inProgress' => $inProgress,
            'completed' => $completed,
            'nextPatient' => $nextPatient,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function queue(): void
    {
        Auth::requireRole('staff');

        $today = date('Y-m-d');

        $waitingQueue = $this->appointments->getByDateAndStatus($today, ['checked_in']);
        $inProgress = $this->appointments->getByDateAndStatus($today, ['in_progress']);
        $completed = $this->appointments->getByDateAndStatus($today, ['completed']);
        $nextPatient = !empty($waitingQueue) ? $waitingQueue[0] : null;

        View::render('staff/appointments/queue', [
            'waitingQueue' => $waitingQueue,
            'inProgress' => $inProgress,
            'completed' => $completed,
            'nextPatient' => $nextPatient,
        ]);
    }

   public function create(): void
{
    Auth::requireRole('staff');

    View::render('staff.appointments.create', [
        'patients' => $this->patients->getAll(),
        'services' => $this->services->getActiveServices(),
        'dentists' => $this->schedules->getActiveDentists(),
        'old' => Session::get('old', []),
        'flash_error' => Session::get('flash_error'),
    ]);

    Session::remove('old');
    Session::remove('flash_error');
}

public function store(): void
{
    Auth::requireRole('staff');

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $staffUser = Auth::user();
    $staffUserId = (int) ($staffUser['user_id'] ?? 0);

    $patientMode = trim((string) ($_POST['patient_mode'] ?? 'existing'));
    $patientId = (int) ($_POST['patient_id'] ?? 0);

    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $dentistId = (int) ($_POST['dentist_id'] ?? 0);
    $appointmentDate = trim((string) ($_POST['appointment_date'] ?? ''));
    $startTime = trim((string) ($_POST['start_time'] ?? ''));
    $remarks = trim((string) ($_POST['remarks'] ?? ''));

    $db = \App\Core\Database::getConnection();

    try {
        $db->beginTransaction();

        $service = $this->services->findById($serviceId);

        if (!$service) {
            throw new RuntimeException('Service not found.');
        }

        if ($serviceId <= 0 || $dentistId <= 0 || $appointmentDate === '' || $startTime === '') {
            throw new RuntimeException('Please complete all required appointment fields.');
        }

        if ($patientMode === 'walk_in') {
            $patientId = $this->createWalkInPatientFromAppointment($_POST, $staffUserId);

            $this->saveWalkInMedicalHistory($patientId, $_POST, $staffUserId);
            $this->saveWalkInDentalHistory($patientId, $_POST, $staffUserId);
        } else {
            if ($patientId <= 0) {
                throw new RuntimeException('Please select an existing patient.');
            }
        }

        $normalizedStart = $this->normalizeTime($startTime);

        $this->availability->ensureSlotStillAvailable(
            $appointmentDate,
            $normalizedStart,
            $serviceId,
            $dentistId
        );

        $start = new DateTime($appointmentDate . ' ' . $normalizedStart);
        $duration = max(1, (int) ($service['estimated_duration_minutes'] ?? 30));

        $end = clone $start;
        $end->add(new DateInterval('PT' . $duration . 'M'));

        $this->appointments->create([
            'appointment_code' => 'APT-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))),
            'request_id' => null,
            'patient_id' => $patientId,
            'dentist_id' => $dentistId,
            'service_id' => $serviceId,
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
            'remarks' => $remarks !== '' ? $remarks : null,
        ]);

        $db->commit();

        Session::set('flash_success', 'Appointment created successfully.');
        header('Location: ' . $this->url('/staff/appointments?date=' . urlencode($appointmentDate)));
        exit;
    } catch (\Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        Session::set('flash_error', $e->getMessage());
        Session::set('old', $_POST);

        header('Location: ' . $this->url('/staff/appointments/create'));
        exit;
    }
}

   

    public function show(): void
    {
        Auth::requireRole('staff');

        $appointmentId = (int) ($_GET['id'] ?? 0);
        $appointment = $this->appointments->findDetailedById($appointmentId);

        if (!$appointment) {
            http_response_code(404);
            exit('Appointment not found.');
        }

        View::render('staff.appointments.show', [
            'appointment' => $appointment,
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function availableSlots(): void
    {
        Auth::requireRole('staff');

        $date = trim((string) ($_GET['date'] ?? ''));
        $serviceId = (int) ($_GET['service_id'] ?? 0);
        $dentistId = (int) ($_GET['dentist_id'] ?? 0);

        header('Content-Type: application/json');

        echo json_encode([
            'available_slots' => $this->availability->getAvailableSlots($date, $serviceId, $dentistId),
        ]);
    }

    public function markArrived(): void
    {
        $this->handleStatusAction('markArrived');
    }

    public function checkIn(): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $staffUser = Auth::user();
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $redirectTo = $this->sanitizeRedirect((string) ($_POST['redirect_to'] ?? ''));

        $appointment = $this->appointments->findDetailedById($appointmentId);

        if (!$appointment) {
            Session::set('flash_error', 'Appointment not found.');
            header('Location: ' . $this->url('/staff/appointments'));
            exit;
        }

        try {
            if (!in_array((string) ($appointment['status'] ?? ''), ['confirmed', 'rescheduled'], true)) {
                throw new RuntimeException('Only confirmed or rescheduled appointments can be checked-in.');
            }

            if (!empty($appointment['request_id'])) {
                $request = $this->requests->findDetailedById((int) $appointment['request_id']);

                if (!$request) {
                    throw new RuntimeException('Original appointment request was not found.');
                }

                $safePatientId = $this->resolvePatientIdForGuestRequest(
                    $request,
                    (int) ($staffUser['user_id'] ?? 0)
                );

                $this->appointments->assignPatient($appointmentId, $safePatientId);

                if (method_exists($this->requests, 'assignPatient')) {
                    $this->requests->assignPatient((int) $request['request_id'], $safePatientId);
                }

                $appointment['patient_id'] = $safePatientId;
            }

            $now = date('Y-m-d H:i:s');

            $this->appointments->updateStatus(
                $appointmentId,
                'checked_in',
                $remarks !== '' ? $remarks : null,
                [
                    'arrival_status' => 'checked_in',
                    'checked_in_at' => $now,
                ]
            );

            $patientName = $this->appointmentPatientName($appointment);
            $appointmentTime = $this->appointmentTimeLabel($appointment);

            $this->notifyDentistSafely(
                (int) ($appointment['dentist_id'] ?? 0),
                $appointmentId,
                'patient_checked_in',
                'Patient checked in',
                $patientName . ' has checked in and is waiting. Appointment time: ' . $appointmentTime . '.',
                (int) ($staffUser['user_id'] ?? 0)
            );

            Session::set('flash_success', 'Patient checked in successfully.');
        } catch (\Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        if ($redirectTo !== '') {
            header('Location: ' . $redirectTo);
            exit;
        }

        header('Location: ' . $this->url('/staff/appointments?date=' . urlencode((string) ($appointment['appointment_date'] ?? date('Y-m-d')))));
        exit;
    }

 public function inProgress(): void
{
    Auth::requireRole('staff');

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $staffUser = Auth::user();
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    $remarks = trim((string) ($_POST['remarks'] ?? ''));
    $redirectTo = $this->sanitizeRedirect((string) ($_POST['redirect_to'] ?? ''));

    if ($redirectTo === '') {
        $redirectTo = $this->url('/staff/appointments?date=' . urlencode(date('Y-m-d')));
    }

    try {
        $this->procedureWorkflow->startProcedure(
            $appointmentId,
            (int) ($staffUser['user_id'] ?? 0),
            null,
            $remarks !== '' ? $remarks : null
        );

        Session::set('flash_success', 'Procedure started successfully.');
    } catch (\Throwable $e) {
        Session::set('flash_error', $e->getMessage());
    }

    header('Location: ' . $redirectTo);
    exit;
}

public function complete(): void
{
    Auth::requireRole('staff');

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $staffUser = Auth::user();
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    $remarks = trim((string) ($_POST['remarks'] ?? ''));
    $redirectTo = $this->sanitizeRedirect((string) ($_POST['redirect_to'] ?? ''));

    if ($redirectTo === '') {
        $redirectTo = $this->url('/staff/appointments?date=' . urlencode(date('Y-m-d')));
    }

    try {
        $this->procedureWorkflow->completeProcedure(
            $appointmentId,
            (int) ($staffUser['user_id'] ?? 0),
            null,
            $remarks !== '' ? $remarks : null
        );

        Session::set('flash_success', 'Procedure completed successfully.');
    } catch (\Throwable $e) {
        Session::set('flash_error', $e->getMessage());
    }

    header('Location: ' . $redirectTo);
    exit;
}

    public function markNoShow(): void
    {
        $this->handleStatusAction('markNoShow');
    }

    public function noShow(): void
    {
        $this->handleStatusAction('markNoShow');
    }

    public function cancel(): void
    {
        $this->handleStatusAction('cancel');
    }

    private function handleStatusAction(string $method): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $staffUser = Auth::user();
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $redirectTo = $this->sanitizeRedirect((string) ($_POST['redirect_to'] ?? ''));

        $appointment = $this->appointments->findDetailedById($appointmentId);

        if (!$appointment) {
            Session::set('flash_error', 'Appointment not found.');
            header('Location: ' . $this->url('/staff/appointments'));
            exit;
        }

        try {
            $this->statusService->{$method}(
                $appointment,
                (int) ($staffUser['user_id'] ?? 0),
                $remarks !== '' ? $remarks : null
            );

            $patientName = $this->appointmentPatientName($appointment);
            $appointmentDate = $this->appointmentDateLabel($appointment);
            $appointmentTime = $this->appointmentTimeLabel($appointment);

            switch ($method) {
                case 'cancel':
                    $this->notifyDentistSafely(
                        (int) ($appointment['dentist_id'] ?? 0),
                        (int) ($appointment['appointment_id'] ?? 0),
                        'appointment_cancelled',
                        'Appointment cancelled',
                        $patientName . '\'s appointment on ' . $appointmentDate . ' at ' . $appointmentTime . ' was cancelled.',
                        (int) ($staffUser['user_id'] ?? 0)
                    );
                    break;

                case 'markNoShow':
                    $this->notifyDentistSafely(
                        (int) ($appointment['dentist_id'] ?? 0),
                        (int) ($appointment['appointment_id'] ?? 0),
                        'appointment_no_show',
                        'Appointment marked no-show',
                        $patientName . '\'s appointment on ' . $appointmentDate . ' at ' . $appointmentTime . ' was marked as no-show.',
                        (int) ($staffUser['user_id'] ?? 0)
                    );
                    break;
            }

            Session::set('flash_success', 'Appointment updated successfully.');
        } catch (\Throwable $e) {
            Session::set('flash_error', $e->getMessage());
        }

        if ($redirectTo !== '') {
            header('Location: ' . $redirectTo);
            exit;
        }

        header('Location: ' . $this->url('/staff/appointments?date=' . urlencode((string) ($appointment['appointment_date'] ?? date('Y-m-d')))));
        exit;
    }

    private function patientMatchesRequest(array $patient, array $request): bool
    {
        $patientFirstName = trim((string) ($patient['first_name'] ?? ''));
        $patientMiddleName = trim((string) ($patient['middle_name'] ?? ''));
        $patientLastName = trim((string) ($patient['last_name'] ?? ''));

        $requestFirstName = trim((string) ($request['guest_first_name'] ?? ''));
        $requestMiddleName = trim((string) ($request['guest_middle_name'] ?? ''));
        $requestLastName = trim((string) ($request['guest_last_name'] ?? ''));

        $sameName =
            strcasecmp($patientFirstName, $requestFirstName) === 0 &&
            strcasecmp($patientMiddleName, $requestMiddleName) === 0 &&
            strcasecmp($patientLastName, $requestLastName) === 0;

        if (!$sameName) {
            return false;
        }

        $patientBirthDate = trim((string) ($patient['birth_date'] ?? ''));
        $requestBirthDate = trim((string) ($request['birth_date'] ?? ''));

        if ($patientBirthDate !== '' && $requestBirthDate !== '' && $patientBirthDate === $requestBirthDate) {
            return true;
        }

        $patientEmail = trim((string) ($patient['email'] ?? ''));
        $requestEmail = trim((string) ($request['guest_email'] ?? ''));

        if ($patientEmail !== '' && $requestEmail !== '' && strcasecmp($patientEmail, $requestEmail) === 0) {
            return true;
        }

        $patientContact = trim((string) ($patient['contact_number'] ?? ''));
        $requestContact = trim((string) ($request['guest_contact_number'] ?? ''));

        if ($patientContact !== '' && $requestContact !== '' && $patientContact === $requestContact) {
            return true;
        }

        return false;
    }

    private function normalizePersonText(?string $value): string
    {
        return strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $value)));
    }

    private function namesMatch(array $patient, array $request): bool
    {
        $patientFirst = $this->normalizePersonText($patient['first_name'] ?? '');
        $patientMiddle = $this->normalizePersonText($patient['middle_name'] ?? '');
        $patientLast = $this->normalizePersonText($patient['last_name'] ?? '');

        $requestFirst = $this->normalizePersonText($request['guest_first_name'] ?? '');
        $requestMiddle = $this->normalizePersonText($request['guest_middle_name'] ?? '');
        $requestLast = $this->normalizePersonText($request['guest_last_name'] ?? '');

        if ($patientFirst === '' || $requestFirst === '' || $patientFirst !== $requestFirst) {
            return false;
        }

        if ($patientLast === '' || $requestLast === '' || $patientLast !== $requestLast) {
            return false;
        }

        if ($patientMiddle !== '' && $requestMiddle !== '' && $patientMiddle !== $requestMiddle) {
            return false;
        }

        return true;
    }

    private function hasSupportingMatch(array $patient, array $request): bool
    {
        $supportHits = 0;

        $patientSex = $this->normalizePersonText($patient['sex'] ?? '');
        $requestSex = $this->normalizePersonText($request['sex'] ?? '');

        if ($patientSex !== '' && $requestSex !== '' && $patientSex === $requestSex) {
            $supportHits++;
        }

        $patientAddress = $this->normalizePersonText($patient['address'] ?? '');
        $requestAddress = $this->normalizePersonText($request['address'] ?? '');

        if ($patientAddress !== '' && $requestAddress !== '' && $patientAddress === $requestAddress) {
            $supportHits++;
        }

        $patientEmergencyName = $this->normalizePersonText($patient['emergency_contact_name'] ?? '');
        $requestEmergencyName = $this->normalizePersonText($request['emergency_contact_name'] ?? '');

        if ($patientEmergencyName !== '' && $requestEmergencyName !== '' && $patientEmergencyName === $requestEmergencyName) {
            $supportHits++;
        }

        $patientEmergencyNumber = trim((string) ($patient['emergency_contact_number'] ?? ''));
        $requestEmergencyNumber = trim((string) ($request['emergency_contact_number'] ?? ''));

        if ($patientEmergencyNumber !== '' && $requestEmergencyNumber !== '' && $patientEmergencyNumber === $requestEmergencyNumber) {
            $supportHits++;
        }

        return $supportHits >= 1;
    }

    private function patientMatchesGuestRequest(array $patient, array $request): bool
    {
        if (!$this->namesMatch($patient, $request)) {
            return false;
        }

        $patientBirthDate = trim((string) ($patient['birth_date'] ?? ''));
        $requestBirthDate = trim((string) ($request['birth_date'] ?? ''));

        if ($patientBirthDate !== '' && $requestBirthDate !== '' && $patientBirthDate === $requestBirthDate) {
            return true;
        }

        $patientEmail = $this->normalizePersonText($patient['email'] ?? '');
        $requestEmail = $this->normalizePersonText($request['guest_email'] ?? '');

        if ($patientEmail !== '' && $requestEmail !== '' && $patientEmail === $requestEmail) {
            return true;
        }

        $patientContact = trim((string) ($patient['contact_number'] ?? ''));
        $requestContact = trim((string) ($request['guest_contact_number'] ?? ''));

        if (
            $patientContact !== '' &&
            $requestContact !== '' &&
            $patientContact === $requestContact &&
            $this->hasSupportingMatch($patient, $request)
        ) {
            return true;
        }

        return false;
    }

    private function resolvePatientIdForGuestRequest(array $request, int $staffUserId): int
    {
        if (!empty($request['patient_id'])) {
            $patient = $this->patients->findById((int) $request['patient_id']);

            if ($patient && $this->patientMatchesGuestRequest($patient, $request)) {
                return (int) $patient['patient_id'];
            }
        }

        $guestContact = trim((string) ($request['guest_contact_number'] ?? ''));

        if ($guestContact !== '') {
            $patient = $this->patients->findByContactNumber($guestContact);

            if ($patient && $this->patientMatchesGuestRequest($patient, $request)) {
                return (int) $patient['patient_id'];
            }
        }

        $firstName = trim((string) ($request['guest_first_name'] ?? ''));
        $middleName = trim((string) ($request['guest_middle_name'] ?? ''));
        $lastName = trim((string) ($request['guest_last_name'] ?? ''));
        $birthDate = trim((string) ($request['birth_date'] ?? ''));

        if (
            $firstName !== '' &&
            $lastName !== '' &&
            $birthDate !== '' &&
            method_exists($this->patients, 'findDuplicateByNameAndBirthDate')
        ) {
            $patient = $this->patients->findDuplicateByNameAndBirthDate(
                $firstName,
                $middleName,
                $lastName,
                $birthDate
            );

            if ($patient && $this->patientMatchesGuestRequest($patient, $request)) {
                return (int) $patient['patient_id'];
            }
        }

        $patientId = $this->patients->create([
            'user_id' => null,
            'patient_code' => 'PAT-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
            'first_name' => $firstName,
            'middle_name' => $middleName !== '' ? $middleName : null,
            'last_name' => $lastName,
            'sex' => !empty($request['sex']) ? $request['sex'] : null,
            'birth_date' => $birthDate !== '' ? $birthDate : null,
            'civil_status' => !empty($request['civil_status']) ? $request['civil_status'] : null,
            'address' => !empty($request['address']) ? $request['address'] : null,
            'occupation' => !empty($request['occupation']) ? $request['occupation'] : null,
            'contact_number' => $guestContact !== '' ? $guestContact : null,
            'email' => !empty($request['guest_email']) ? $request['guest_email'] : null,
            'emergency_contact_name' => !empty($request['emergency_contact_name']) ? $request['emergency_contact_name'] : null,
            'emergency_contact_number' => !empty($request['emergency_contact_number']) ? $request['emergency_contact_number'] : null,
            'notes' => 'Auto-created on patient check-in from appointment request #' . (int) ($request['request_id'] ?? 0),
            'profile_status' => 'active',
            'created_by' => $staffUserId,
        ]);

        $patientId = (int) $patientId;

        if ($patientId <= 0) {
            throw new RuntimeException('Failed to create patient record for this appointment request.');
        }

        return $patientId;
    }

    private function notifyDentistSafely(
        int $dentistId,
        int $appointmentId,
        string $type,
        string $title,
        string $message,
        ?int $actorUserId = null
    ): void {
        try {
            if ($dentistId <= 0 || $appointmentId <= 0) {
                return;
            }

            $this->notifications->createForDentistAppointment(
                $dentistId,
                $appointmentId,
                $type,
                $title,
                $message,
                $actorUserId
            );
        } catch (\Throwable $e) {
            // Notification failure must not break appointment workflow.
        }
    }

    private function appointmentPatientName(array $appointment): string
    {
        $patientName = trim((string) (
            ($appointment['patient_first_name'] ?? '') . ' ' .
            ($appointment['patient_last_name'] ?? '')
        ));

        if ($patientName !== '') {
            return $patientName;
        }

        $guestName = trim((string) (
            ($appointment['guest_first_name'] ?? '') . ' ' .
            ($appointment['guest_last_name'] ?? '')
        ));

        return $guestName !== '' ? $guestName : 'A patient';
    }

    private function appointmentTimeLabel(array $appointment): string
    {
        $time = trim((string) ($appointment['start_time'] ?? ''));

        if ($time === '') {
            return 'the scheduled time';
        }

        $timestamp = strtotime($time);

        return $timestamp !== false ? date('h:i A', $timestamp) : $time;
    }

    private function appointmentDateLabel(array $appointment): string
    {
        $date = trim((string) ($appointment['appointment_date'] ?? ''));

        if ($date === '') {
            return 'the scheduled date';
        }

        $timestamp = strtotime($date);

        return $timestamp !== false ? date('M d, Y', $timestamp) : $date;
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }

    private function autoMarkMissedAppointmentsAsNoShow(int $staffUserId): void
    {
        try {
            $db = \App\Core\Database::getConnection();

            $stmt = $db->prepare("
                SELECT
                    appointment_id,
                    status
                FROM appointments
                WHERE appointment_date < CURDATE()
                  AND checked_in_at IS NULL
                  AND status IN ('confirmed', 'rescheduled')
            ");

            $stmt->execute();

            $missedAppointments = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            if (empty($missedAppointments)) {
                return;
            }

            $db->beginTransaction();

            $appointmentIds = array_map(
                static fn (array $row): int => (int) $row['appointment_id'],
                $missedAppointments
            );

            $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));

            $update = $db->prepare("
                UPDATE appointments
                SET
                    status = 'no_show',
                    no_show_at = NOW(),
                    updated_at = NOW()
                WHERE appointment_id IN ($placeholders)
                  AND checked_in_at IS NULL
                  AND status IN ('confirmed', 'rescheduled')
            ");

            foreach ($appointmentIds as $index => $appointmentId) {
                $update->bindValue($index + 1, $appointmentId, \PDO::PARAM_INT);
            }

            $update->execute();

            $log = $db->prepare("
                INSERT INTO appointment_status_logs (
                    appointment_id,
                    old_status,
                    new_status,
                    changed_by,
                    remarks,
                    changed_at
                ) VALUES (
                    :appointment_id,
                    :old_status,
                    'no_show',
                    :changed_by,
                    :remarks,
                    NOW()
                )
            ");

            foreach ($missedAppointments as $appointment) {
                $log->execute([
                    ':appointment_id' => (int) $appointment['appointment_id'],
                    ':old_status' => (string) $appointment['status'],
                    ':changed_by' => $staffUserId > 0 ? $staffUserId : null,
                    ':remarks' => 'Auto-marked as no-show because the appointment date passed without check-in.',
                ]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
        }
    }

    private function sanitizeRedirect(string $redirect): string
    {
        $redirect = trim($redirect);

        if ($redirect === '') {
            return '';
        }

        if (!str_starts_with($redirect, '/DentalClinic/public/')) {
            return '';
        }

        return $redirect;
    }



    private function createWalkInPatientFromAppointment(array $input, int $staffUserId): int
{
    $firstName = trim((string) ($input['walkin_first_name'] ?? ''));
    $middleName = trim((string) ($input['walkin_middle_name'] ?? ''));
    $lastName = trim((string) ($input['walkin_last_name'] ?? ''));
    $contactNumber = trim((string) ($input['walkin_contact_number'] ?? ''));
    $email = trim((string) ($input['walkin_email'] ?? ''));
    $birthDate = $this->walkinDateOrNull($input['walkin_birth_date'] ?? null);

    if ($firstName === '' || $lastName === '') {
        throw new RuntimeException('Walk-in first name and last name are required.');
    }

    if ($contactNumber === '') {
        throw new RuntimeException('Walk-in contact number is required.');
    }

    if (!$this->isValidWalkinPhilippineMobile($contactNumber)) {
        throw new RuntimeException('Invalid contact number. Use 09XXXXXXXXX, 639XXXXXXXXX, or +639XXXXXXXXX.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Invalid email address.');
    }

    if ($birthDate !== null && strtotime($birthDate) > time()) {
        throw new RuntimeException('Birth date cannot be in the future.');
    }

    $patientId = $this->patients->create([
        'user_id' => null,
        'patient_code' => 'PAT-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
        'first_name' => $firstName,
        'middle_name' => $middleName !== '' ? $middleName : null,
        'last_name' => $lastName,
        'sex' => $this->walkinAllowedOrNull($input['walkin_sex'] ?? null, ['Male', 'Female']),
        'birth_date' => $birthDate,
        'civil_status' => $this->walkinAllowedOrNull($input['walkin_civil_status'] ?? null, ['Single', 'Married', 'Widowed', 'Separated']),
        'address' => $this->walkinTextOrNull($input['walkin_address'] ?? null),
        'occupation' => $this->walkinTextOrNull($input['walkin_occupation'] ?? null),
        'contact_number' => $contactNumber,
        'email' => $email !== '' ? $email : null,
        'emergency_contact_name' => null,
        'emergency_contact_number' => null,
        'notes' => 'Created from staff walk-in appointment.',
        'profile_status' => 'active',
        'created_by' => $staffUserId,
    ]);

    $patientId = (int) $patientId;

    if ($patientId <= 0) {
        throw new RuntimeException('Failed to create walk-in patient record.');
    }

    return $patientId;
}

private function saveWalkInMedicalHistory(int $patientId, array $input, int $staffUserId): void
{
    if ($patientId <= 0) {
        return;
    }

    $allergyText = implode(', ', array_filter([
        isset($input['allergy_local_anesthesia']) ? 'Local anesthesia' : '',
        isset($input['allergy_antibiotics']) ? 'Antibiotics' : '',
        isset($input['allergy_pain_killer']) ? 'Pain killer' : '',
        trim((string) ($input['medical_allergy_others'] ?? '')),
    ]));

    $data = [
        'under_physician_care' => $this->walkinYesNoOrNull($input['medical_under_physician_care'] ?? null),
        'physician_care_details' => $this->walkinTextOrNull($input['medical_physician_name'] ?? null),
        'physician_name' => $this->walkinTextOrNull($input['medical_physician_name'] ?? null),
        'physician_contact' => $this->walkinTextOrNull($input['medical_physician_contact'] ?? null),

        'is_pregnant' => $this->walkinYesNoOrNull($input['medical_is_pregnant'] ?? null),
        'pregnancy_status' => $this->walkinYesNoOrNull($input['medical_is_pregnant'] ?? null),

        'taking_medicine' => $this->walkinYesNoOrNull($input['medical_taking_medicine'] ?? null),
        'medicine_details' => $this->walkinTextOrNull($input['medical_medicine_details'] ?? null),
        'medications' => $this->walkinTextOrNull($input['medical_medicine_details'] ?? null),

        'blood_pressure' => $this->walkinTextOrNull($input['medical_blood_pressure'] ?? null),

        'condition_high_blood_pressure' => isset($input['condition_high_blood_pressure']) ? 1 : 0,
        'condition_low_blood_pressure' => isset($input['condition_low_blood_pressure']) ? 1 : 0,
        'condition_asthma' => isset($input['condition_asthma']) ? 1 : 0,
        'condition_heart_disease' => isset($input['condition_heart_disease']) ? 1 : 0,
        'condition_diabetes' => isset($input['condition_diabetes']) ? 1 : 0,
        'diabetes' => isset($input['condition_diabetes']) ? 1 : 0,
        'condition_tuberculosis' => isset($input['condition_tuberculosis']) ? 1 : 0,
        'condition_thyroid_problem' => isset($input['condition_thyroid_problem']) ? 1 : 0,
        'condition_bleeding_problems' => isset($input['condition_bleeding_problems']) ? 1 : 0,
        'bleeding_disorder' => isset($input['condition_bleeding_problems']) ? 1 : 0,
        'condition_hiv_aids' => isset($input['condition_hiv_aids']) ? 1 : 0,
        'condition_hepatitis' => isset($input['condition_hepatitis']) ? 1 : 0,
        'condition_others' => $this->walkinTextOrNull($input['medical_condition_others'] ?? null),

        'allergy_local_anesthesia' => isset($input['allergy_local_anesthesia']) ? 1 : 0,
        'allergy_antibiotics' => isset($input['allergy_antibiotics']) ? 1 : 0,
        'allergy_pain_killer' => isset($input['allergy_pain_killer']) ? 1 : 0,
        'allergy_others' => $this->walkinTextOrNull($input['medical_allergy_others'] ?? null),
        'allergies' => $allergyText !== '' ? $allergyText : null,

        'hospitalized' => $this->walkinYesNoOrNull($input['medical_hospitalized'] ?? null),
        'hospitalization_when' => $this->walkinTextOrNull($input['medical_hospitalization_when'] ?? null),
        'hospitalization_why' => $this->walkinTextOrNull($input['medical_hospitalization_why'] ?? null),

        'notes' => $this->walkinTextOrNull($input['medical_notes'] ?? null),
    ];

    $this->walkinUpsertHistoryRow(
        'patient_medical_histories',
        'medical_history_id',
        $patientId,
        $data,
        $staffUserId
    );
}

private function saveWalkInDentalHistory(int $patientId, array $input, int $staffUserId): void
{
    if ($patientId <= 0) {
        return;
    }

    $data = [
        'previous_dentist' => $this->walkinTextOrNull($input['dental_previous_dentist'] ?? null),
        'last_dental_visit' => $this->walkinDateOrNull($input['dental_last_dental_visit'] ?? null),
        'last_dental_visit_reason' => $this->walkinTextOrNull($input['dental_last_dental_visit_reason'] ?? null),

        'worn_denture' => $this->walkinYesNoOrNull($input['dental_worn_denture'] ?? null),
        'gums_bleed' => $this->walkinYesNoOrNull($input['dental_gums_bleed'] ?? null),
        'bad_breath' => $this->walkinYesNoOrNull($input['dental_bad_breath'] ?? null),
        'loose_teeth' => $this->walkinYesNoOrNull($input['dental_loose_teeth'] ?? null),
        'sensitive_teeth' => $this->walkinYesNoOrNull($input['dental_sensitive_teeth'] ?? null),
        'clicking_jaw' => $this->walkinYesNoOrNull($input['dental_clicking_jaw'] ?? null),

        'notes' => $this->walkinTextOrNull($input['dental_notes'] ?? null),
    ];

    $this->walkinUpsertHistoryRow(
        'patient_dental_histories',
        'dental_history_id',
        $patientId,
        $data,
        $staffUserId
    );
}

private function walkinUpsertHistoryRow(
    string $table,
    string $primaryKey,
    int $patientId,
    array $data,
    int $staffUserId
): void {
    $allowedTables = [
        'patient_medical_histories',
        'patient_dental_histories',
    ];

    if (!in_array($table, $allowedTables, true)) {
        return;
    }

    $db = \App\Core\Database::getConnection();
    $columns = $this->walkinTableColumns($table);

    if (empty($columns) || !isset($columns['patient_id'])) {
        return;
    }

    $usable = [];

    foreach ($data as $column => $value) {
        if (isset($columns[$column])) {
            $usable[$column] = $value;
        }
    }

    if (isset($columns['updated_by'])) {
        $usable['updated_by'] = $staffUserId;
    }

    $whereColumn = isset($columns[$primaryKey]) ? $primaryKey : 'patient_id';

    $stmt = $db->prepare("
        SELECT `$whereColumn`
        FROM `$table`
        WHERE patient_id = :patient_id
        LIMIT 1
    ");

    $stmt->execute([
        ':patient_id' => $patientId,
    ]);

    $existingId = (int) ($stmt->fetchColumn() ?: 0);

    if ($existingId > 0) {
        $sets = [];
        $params = [
            ':patient_id' => $patientId,
        ];

        foreach ($usable as $column => $value) {
            $sets[] = "`$column` = :$column";
            $params[":$column"] = $value;
        }

        if (isset($columns['updated_at'])) {
            $sets[] = 'updated_at = NOW()';
        }

        if (empty($sets)) {
            return;
        }

        $sql = "
            UPDATE `$table`
            SET " . implode(', ', $sets) . "
            WHERE patient_id = :patient_id
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return;
    }

    $insertColumns = ['patient_id'];
    $insertValues = [':patient_id'];
    $params = [
        ':patient_id' => $patientId,
    ];

    foreach ($usable as $column => $value) {
        $insertColumns[] = "`$column`";
        $insertValues[] = ":$column";
        $params[":$column"] = $value;
    }

    if (isset($columns['created_at'])) {
        $insertColumns[] = 'created_at';
        $insertValues[] = 'NOW()';
    }

    if (isset($columns['updated_at'])) {
        $insertColumns[] = 'updated_at';
        $insertValues[] = 'NOW()';
    }

    $sql = "
        INSERT INTO `$table` (
            " . implode(', ', $insertColumns) . "
        ) VALUES (
            " . implode(', ', $insertValues) . "
        )
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
}

private function walkinTableColumns(string $table): array
{
    try {
        $db = \App\Core\Database::getConnection();
        $stmt = $db->query("SHOW COLUMNS FROM `$table`");

        $columns = [];

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $field = (string) ($row['Field'] ?? '');

            if ($field !== '') {
                $columns[$field] = true;
            }
        }

        return $columns;
    } catch (\Throwable $e) {
        return [];
    }
}

private function walkinTextOrNull($value): ?string
{
    $text = trim((string) $value);

    return $text !== '' ? $text : null;
}

private function walkinDateOrNull($value): ?string
{
    $date = trim((string) $value);

    if ($date === '') {
        return null;
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d', $timestamp);
}

private function walkinYesNoOrNull($value): ?string
{
    $value = strtolower(trim((string) $value));

    if (in_array($value, ['yes', 'no'], true)) {
        return $value;
    }

    return null;
}

private function walkinAllowedOrNull($value, array $allowed): ?string
{
    $value = trim((string) $value);

    return in_array($value, $allowed, true) ? $value : null;
}

private function isValidWalkinPhilippineMobile(string $phone): bool
{
    $phone = preg_replace('/[\s-]+/', '', $phone);

    return (bool) preg_match('/^(09\d{9}|639\d{9}|\+639\d{9})$/', $phone);
}

    private function url(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }




private function safeDentistRedirect(string $url): string
{
    $url = trim($url);

    if ($url === '') {
        return '';
    }

    if (str_starts_with($url, '/DentalClinic/public/dentist/')) {
        return $url;
    }

    return '';
}



}