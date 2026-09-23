<?php

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AppointmentRepository;
use App\Repositories\AppointmentRequestRepository;
use App\Repositories\AppointmentStatusLogRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\DentistLookupRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\PatientRepository;
use App\Repositories\ReminderRepository;
use App\Repositories\ServiceRepository;
use App\Services\BookingAvailabilityService;
use DateInterval;
use DateTime;
use PDO;
use RuntimeException;

class AppointmentRequestController
{
    private AppointmentRequestRepository $requests;
    private AppointmentRepository $appointments;
    private PatientRepository $patients;
    private ServiceRepository $services;
    private DentistLookupRepository $dentists;
    private ReminderRepository $reminders;
    private AppointmentStatusLogRepository $statusLogs;
    private AuditLogRepository $auditLogs;
    private BookingAvailabilityService $availability;
    private NotificationRepository $notifications;
    private PDO $db;

    public function __construct()
    {
        $this->requests = new AppointmentRequestRepository();
        $this->appointments = new AppointmentRepository();
        $this->patients = new PatientRepository();
        $this->services = new ServiceRepository();
        $this->dentists = new DentistLookupRepository();
        $this->reminders = new ReminderRepository();
        $this->statusLogs = new AppointmentStatusLogRepository();
        $this->auditLogs = new AuditLogRepository();
        $this->availability = new BookingAvailabilityService();
        $this->notifications = new NotificationRepository();
        $this->db = Database::getConnection();
    }

    public function index(): void
    {
        Auth::requireRole('staff');

        $selectedServiceId = isset($_GET['service_id']) && $_GET['service_id'] !== ''
            ? (int) $_GET['service_id']
            : null;

        $sort = trim((string) ($_GET['sort'] ?? 'latest'));

        if (!in_array($sort, ['latest', 'oldest'], true)) {
            $sort = 'latest';
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        View::render('staff.appointment_requests.index', [
            'requests' => $this->requests->paginateForStaff($selectedServiceId, $sort, $perPage, $offset),
            'services' => $this->services->getActiveServices(),
            'selectedServiceId' => $selectedServiceId,
            'sort' => $sort,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $this->requests->countForStaff($selectedServiceId),
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    public function show(): void
    {
        Auth::requireRole('staff');

        $requestId = (int) ($_GET['id'] ?? 0);
        $request = $this->requests->findDetailedById($requestId);

        if (!$request) {
            http_response_code(404);
            exit('Appointment request not found.');
        }

        View::render('staff.appointment_requests.show', [
            'request' => $request,
            'answers' => $this->requests->getAnswersByRequestId($requestId),
            'dentists' => $this->dentists->getActiveDentists(),
            'flash_success' => Session::get('flash_success'),
            'flash_error' => Session::get('flash_error'),
        ]);

        Session::remove('flash_success');
        Session::remove('flash_error');
    }

    private function resolvePatientIdForRequest(array $request, int $staffUserId): int
    {
        if (!empty($request['patient_id'])) {
            $patientId = (int) $request['patient_id'];
            $existingPatient = $this->patients->findById($patientId);

            if ($existingPatient) {
                $sameName =
                    strcasecmp(trim((string) ($existingPatient['first_name'] ?? '')), trim((string) ($request['guest_first_name'] ?? ''))) === 0 &&
                    strcasecmp(trim((string) ($existingPatient['last_name'] ?? '')), trim((string) ($request['guest_last_name'] ?? ''))) === 0;

                $sameBirthDate =
                    !empty($request['birth_date']) &&
                    !empty($existingPatient['birth_date']) &&
                    (string) $existingPatient['birth_date'] === (string) $request['birth_date'];

                if ($sameName && $sameBirthDate) {
                    return $patientId;
                }
            }
        }

        $firstName = trim((string) ($request['guest_first_name'] ?? ''));
        $middleName = trim((string) ($request['guest_middle_name'] ?? ''));
        $lastName = trim((string) ($request['guest_last_name'] ?? ''));
        $birthDate = trim((string) ($request['birth_date'] ?? ''));
        $contactNumber = trim((string) ($request['guest_contact_number'] ?? ''));
        $email = trim((string) ($request['guest_email'] ?? ''));

        $sex = trim((string) ($request['sex'] ?? ''));
        $address = trim((string) ($request['address'] ?? ''));
        $emergencyContactName = trim((string) ($request['emergency_contact_name'] ?? ''));
        $emergencyContactNumber = trim((string) ($request['emergency_contact_number'] ?? ''));

        $sameFullName = function (array $patient) use ($firstName, $middleName, $lastName): bool {
            $patientFirst = trim((string) ($patient['first_name'] ?? ''));
            $patientMiddle = trim((string) ($patient['middle_name'] ?? ''));
            $patientLast = trim((string) ($patient['last_name'] ?? ''));

            return
                strcasecmp($patientFirst, $firstName) === 0 &&
                strcasecmp($patientMiddle, $middleName) === 0 &&
                strcasecmp($patientLast, $lastName) === 0;
        };

        $supportsMatch = function (array $patient) use ($sex, $address, $emergencyContactName, $emergencyContactNumber): bool {
            $supportHits = 0;

            if ($sex !== '' && !empty($patient['sex']) && strcasecmp((string) $patient['sex'], $sex) === 0) {
                $supportHits++;
            }

            if ($address !== '' && !empty($patient['address']) && strcasecmp(trim((string) $patient['address']), $address) === 0) {
                $supportHits++;
            }

            if (
                $emergencyContactName !== '' &&
                !empty($patient['emergency_contact_name']) &&
                strcasecmp(trim((string) $patient['emergency_contact_name']), $emergencyContactName) === 0
            ) {
                $supportHits++;
            }

            if (
                $emergencyContactNumber !== '' &&
                !empty($patient['emergency_contact_number']) &&
                trim((string) $patient['emergency_contact_number']) === $emergencyContactNumber
            ) {
                $supportHits++;
            }

            return $supportHits >= 1;
        };

        if ($contactNumber !== '') {
            $candidate = $this->patients->findByContactNumber($contactNumber);

            if ($candidate && !empty($candidate['patient_id'])) {
                $candidateBirthDate = trim((string) ($candidate['birth_date'] ?? ''));

                if (
                    $sameFullName($candidate) &&
                    $birthDate !== '' &&
                    $candidateBirthDate !== '' &&
                    $candidateBirthDate === $birthDate
                ) {
                    return (int) $candidate['patient_id'];
                }

                $candidateEmail = trim((string) ($candidate['email'] ?? ''));

                if (
                    $sameFullName($candidate) &&
                    $email !== '' &&
                    $candidateEmail !== '' &&
                    strcasecmp($candidateEmail, $email) === 0
                ) {
                    return (int) $candidate['patient_id'];
                }

                if ($sameFullName($candidate) && $supportsMatch($candidate)) {
                    return (int) $candidate['patient_id'];
                }
            }
        }

        if ($firstName !== '' && $lastName !== '' && $birthDate !== '') {
            $candidate = $this->patients->findDuplicateByNameAndBirthDate(
                $firstName,
                $middleName,
                $lastName,
                $birthDate
            );

            if ($candidate && !empty($candidate['patient_id'])) {
                $candidateEmail = trim((string) ($candidate['email'] ?? ''));

                if (
                    $email !== '' &&
                    $candidateEmail !== '' &&
                    strcasecmp($candidateEmail, $email) === 0
                ) {
                    return (int) $candidate['patient_id'];
                }

                if (($email === '' || $candidateEmail === '') && $supportsMatch($candidate)) {
                    return (int) $candidate['patient_id'];
                }
            }
        }

        $createdPatientId = $this->patients->create([
            'user_id' => null,
            'patient_code' => 'PAT-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
            'first_name' => $firstName,
            'middle_name' => $middleName !== '' ? $middleName : null,
            'last_name' => $lastName,
            'sex' => $sex !== '' ? $sex : null,
            'birth_date' => $birthDate !== '' ? $birthDate : null,
            'civil_status' => ($request['civil_status'] ?? null) !== '' ? $request['civil_status'] : null,
            'address' => $address !== '' ? $address : null,
            'occupation' => ($request['occupation'] ?? null) !== '' ? $request['occupation'] : null,
            'contact_number' => $contactNumber !== '' ? $contactNumber : null,
            'email' => $email !== '' ? $email : null,
            'emergency_contact_name' => $emergencyContactName !== '' ? $emergencyContactName : null,
            'emergency_contact_number' => $emergencyContactNumber !== '' ? $emergencyContactNumber : null,
            'notes' => 'Auto-created from appointment request #' . (int) ($request['request_id'] ?? 0),
            'profile_status' => 'active',
            'created_by' => $staffUserId,
        ]);

        $createdPatientId = (int) $createdPatientId;

        if ($createdPatientId <= 0) {
            throw new RuntimeException('Failed to create or resolve patient record for this appointment request.');
        }

        return $createdPatientId;
    }

    public function confirm(): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $staff = Auth::user();
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $dentistId = (int) ($_POST['dentist_id'] ?? 0);
        $staffNotes = trim((string) ($_POST['staff_notes'] ?? ''));

        try {
            $request = $this->requests->findDetailedById($requestId);

            if (!$request) {
                throw new RuntimeException('Appointment request not found.');
            }

            if (!in_array((string) $request['request_status'], ['pending', 'under_review', 'rescheduled'], true)) {
                throw new RuntimeException('This request can no longer be confirmed.');
            }

            if ($dentistId <= 0) {
                throw new RuntimeException('Please select an available dentist.');
            }

            $appointmentDate = trim((string) ($request['preferred_date'] ?? ''));
            $startTime = trim((string) ($request['preferred_start_time'] ?? ''));
            $serviceId = (int) ($request['service_id'] ?? 0);

            if ($appointmentDate === '' || $startTime === '' || $serviceId <= 0) {
                throw new RuntimeException('The request schedule or service is incomplete.');
            }

            $service = $this->services->findById($serviceId);

            if (!$service) {
                throw new RuntimeException('Service not found.');
            }

            $normalizedStart = $this->normalizeTime($startTime);

            $this->availability->ensureSlotStillAvailable(
                $appointmentDate,
                $normalizedStart,
                $serviceId,
                $dentistId
            );

            $patientId = null;

            if (!empty($request['patient_id'])) {
                $existingPatient = $this->patients->findById((int) $request['patient_id']);

                if ($existingPatient) {
                    $sameName =
                        strcasecmp((string) ($existingPatient['first_name'] ?? ''), (string) ($request['guest_first_name'] ?? '')) === 0 &&
                        strcasecmp((string) ($existingPatient['last_name'] ?? ''), (string) ($request['guest_last_name'] ?? '')) === 0;

                    if ($sameName) {
                        $patientId = (int) $existingPatient['patient_id'];
                    }
                }
            }

            $start = new DateTime($appointmentDate . ' ' . $normalizedStart);
            $duration = max(1, (int) ($service['estimated_duration_minutes'] ?? 30));
            $end = clone $start;
            $end->add(new DateInterval('PT' . $duration . 'M'));

            $this->db->beginTransaction();

            $appointmentId = $this->appointments->create([
                'appointment_code' => 'APT-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'request_id' => $requestId,
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
                'booked_by' => (int) $staff['user_id'],
                'confirmed_by' => (int) $staff['user_id'],
                'remarks' => $staffNotes !== '' ? $staffNotes : null,
            ]);

            $this->requests->updateAfterConfirmation(
                $requestId,
                $appointmentId,
                (int) $staff['user_id'],
                $staffNotes !== '' ? $staffNotes : null
            );

            $this->statusLogs->create([
                'appointment_id' => $appointmentId,
                'old_status' => 'pending',
                'new_status' => 'confirmed',
                'changed_by' => (int) $staff['user_id'],
                'remarks' => $staffNotes !== '' ? $staffNotes : 'Appointment confirmed from request.',
            ]);

            if ($patientId !== null && $patientId > 0) {
                $this->reminders->createAppointmentReminderSet(
                    $appointmentId,
                    $patientId,
                    $appointmentDate
                );
            }

            $this->auditLogs->create([
                'user_id' => (int) $staff['user_id'],
                'module_name' => 'appointment_requests',
                'action_name' => 'confirm',
                'record_type' => 'appointment_request',
                'record_id' => $requestId,
                'description' => 'Confirmed appointment request and created appointment #' . $appointmentId,
            ]);

            $this->db->commit();

            $this->notifyConfirmedAppointment(
                $request,
                $dentistId,
                $appointmentId,
                $appointmentDate,
                $normalizedStart,
                (int) $staff['user_id']
            );

            Session::set('flash_success', 'Appointment request confirmed successfully.');
            header('Location: ' . $this->url('/staff/appointments?date=' . urlencode($appointmentDate)));
            exit;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            Session::set('flash_error', $e->getMessage());
            header('Location: ' . $this->url('/staff/appointment-requests/show?id=' . urlencode((string) $requestId)));
            exit;
        }
    }

    public function reject(): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $staff = Auth::user();
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $staffNotes = trim((string) ($_POST['staff_notes'] ?? ''));

        try {
            $request = $this->requests->findDetailedById($requestId);

            if (!$request) {
                throw new RuntimeException('Appointment request not found.');
            }

            if (!in_array((string) $request['request_status'], ['pending', 'under_review', 'rescheduled'], true)) {
                throw new RuntimeException('This request can no longer be rejected.');
            }

            $this->requests->updateStatus(
                $requestId,
                'rejected',
                (int) $staff['user_id'],
                $staffNotes !== '' ? $staffNotes : null
            );

            $this->auditLogs->create([
                'user_id' => (int) $staff['user_id'],
                'module_name' => 'appointment_requests',
                'action_name' => 'reject',
                'record_type' => 'appointment_request',
                'record_id' => $requestId,
                'description' => 'Rejected appointment request.',
            ]);

            Session::set('flash_success', 'Appointment request rejected successfully.');
            header('Location: ' . $this->url('/staff/appointment-requests'));
            exit;
        } catch (\Throwable $e) {
            Session::set('flash_error', $e->getMessage());
            header('Location: ' . $this->url('/staff/appointment-requests/show?id=' . urlencode((string) $requestId)));
            exit;
        }
    }

    public function reschedule(): void
    {
        Auth::requireRole('staff');

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        $staff = Auth::user();
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $preferredDate = trim((string) ($_POST['preferred_date'] ?? ''));
        $preferredStartTime = trim((string) ($_POST['preferred_start_time'] ?? ''));
        $postedDentistId = (int) ($_POST['dentist_id'] ?? 0);
        $staffNotes = trim((string) ($_POST['staff_notes'] ?? ''));

        $notificationAppointmentId = 0;
        $notificationDentistId = 0;
        $notificationDate = '';
        $notificationStartTime = '';

        try {
            $request = $this->requests->findDetailedById($requestId);

            if (!$request) {
                throw new RuntimeException('Appointment request not found.');
            }

            if (!in_array((string) $request['request_status'], ['pending', 'under_review', 'rescheduled', 'confirmed'], true)) {
                throw new RuntimeException('This request can no longer be rescheduled.');
            }

            if ($preferredDate === '' || $preferredStartTime === '') {
                throw new RuntimeException('New preferred date and time are required.');
            }

            $serviceId = (int) ($request['service_id'] ?? 0);
            $service = $this->services->findById($serviceId);

            if (!$service) {
                throw new RuntimeException('Service not found.');
            }

            $normalizedStart = $this->normalizeTime($preferredStartTime);
            $linkedAppointmentId = (int) ($request['converted_appointment_id'] ?? 0);

            $effectiveDentistId = 0;

            if ($linkedAppointmentId > 0) {
                $linkedAppointment = $this->appointments->findDetailedById($linkedAppointmentId);

                if (!$linkedAppointment) {
                    throw new RuntimeException('Linked appointment not found.');
                }

                $effectiveDentistId = (int) ($linkedAppointment['dentist_id'] ?? 0);
            } elseif ($postedDentistId > 0) {
                $effectiveDentistId = $postedDentistId;
            } elseif (!empty($request['preferred_dentist_id'])) {
                $effectiveDentistId = (int) $request['preferred_dentist_id'];
            }

            $this->db->beginTransaction();

            $this->requests->updateReschedule(
                $requestId,
                $preferredDate,
                $normalizedStart,
                (int) $staff['user_id'],
                $staffNotes !== '' ? $staffNotes : null
            );

            if ($effectiveDentistId > 0) {
                $this->availability->ensureSlotStillAvailable(
                    $preferredDate,
                    $normalizedStart,
                    $serviceId,
                    $effectiveDentistId
                );

                $start = new DateTime($preferredDate . ' ' . $normalizedStart);
                $duration = max(1, (int) ($service['estimated_duration_minutes'] ?? 30));
                $end = clone $start;
                $end->add(new DateInterval('PT' . $duration . 'M'));

                if ($linkedAppointmentId > 0) {
                    $currentAppointment = $this->appointments->findDetailedById($linkedAppointmentId);

                    if (!$currentAppointment) {
                        throw new RuntimeException('Linked appointment not found.');
                    }

                    $currentPatientId = !empty($currentAppointment['patient_id'])
                        ? (int) $currentAppointment['patient_id']
                        : 0;

                    if ($currentPatientId > 0) {
                        $this->requests->assignPatient($requestId, $currentPatientId);
                    }

                    $this->appointments->updateSchedule(
                        $linkedAppointmentId,
                        $preferredDate,
                        $start->format('H:i:s'),
                        $end->format('H:i:s'),
                        'rescheduled',
                        $staffNotes !== '' ? $staffNotes : null
                    );

                    $notificationAppointmentId = $linkedAppointmentId;
                    $notificationDentistId = $effectiveDentistId;
                    $notificationDate = $preferredDate;
                    $notificationStartTime = $start->format('H:i:s');

                    $this->statusLogs->create([
                        'appointment_id' => $linkedAppointmentId,
                        'old_status' => (string) ($currentAppointment['status'] ?? 'confirmed'),
                        'new_status' => 'rescheduled',
                        'changed_by' => (int) $staff['user_id'],
                        'remarks' => $staffNotes !== '' ? $staffNotes : 'Appointment rescheduled.',
                    ]);

                    if ($currentPatientId > 0) {
                        $this->reminders->createAppointmentReminderSet(
                            $linkedAppointmentId,
                            $currentPatientId,
                            $preferredDate
                        );
                    }
                } else {
                    $patientId = null;

                    if (!empty($request['patient_id'])) {
                        $existingPatient = $this->patients->findById((int) $request['patient_id']);

                        if ($existingPatient) {
                            $sameName =
                                strcasecmp((string) ($existingPatient['first_name'] ?? ''), (string) ($request['guest_first_name'] ?? '')) === 0 &&
                                strcasecmp((string) ($existingPatient['last_name'] ?? ''), (string) ($request['guest_last_name'] ?? '')) === 0;

                            if ($sameName) {
                                $patientId = (int) $existingPatient['patient_id'];
                            }
                        }
                    }

                    if ($patientId !== null && $patientId > 0) {
                        $this->requests->assignPatient($requestId, $patientId);
                    }

                    $appointmentId = $this->appointments->create([
                        'appointment_code' => 'APT-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))),
                        'request_id' => $requestId,
                        'patient_id' => $patientId,
                        'dentist_id' => $effectiveDentistId,
                        'service_id' => $serviceId,
                        'appointment_date' => $preferredDate,
                        'start_time' => $start->format('H:i:s'),
                        'end_time' => $end->format('H:i:s'),
                        'estimated_duration_minutes' => $duration,
                        'estimated_price' => (float) ($service['estimated_price'] ?? 0),
                        'status' => 'rescheduled',
                        'arrival_status' => 'pending',
                        'grace_period_minutes' => 30,
                        'booked_by' => (int) $staff['user_id'],
                        'confirmed_by' => (int) $staff['user_id'],
                        'remarks' => $staffNotes !== '' ? $staffNotes : null,
                    ]);

                    $notificationAppointmentId = $appointmentId;
                    $notificationDentistId = $effectiveDentistId;
                    $notificationDate = $preferredDate;
                    $notificationStartTime = $start->format('H:i:s');

                    $this->requests->updateAfterConfirmation(
                        $requestId,
                        $appointmentId,
                        (int) $staff['user_id'],
                        $staffNotes !== '' ? $staffNotes : null
                    );

                    $this->statusLogs->create([
                        'appointment_id' => $appointmentId,
                        'old_status' => 'pending',
                        'new_status' => 'rescheduled',
                        'changed_by' => (int) $staff['user_id'],
                        'remarks' => $staffNotes !== '' ? $staffNotes : 'Appointment rescheduled from request.',
                    ]);

                    if ($patientId !== null && $patientId > 0) {
                        $this->reminders->createAppointmentReminderSet(
                            $appointmentId,
                            $patientId,
                            $preferredDate
                        );
                    }
                }
            }

            $this->auditLogs->create([
                'user_id' => (int) $staff['user_id'],
                'module_name' => 'appointment_requests',
                'action_name' => 'reschedule',
                'record_type' => 'appointment_request',
                'record_id' => $requestId,
                'description' => 'Rescheduled appointment request.',
            ]);

            $this->db->commit();

            $this->notifyRescheduledAppointment(
                $request,
                $notificationDentistId,
                $notificationAppointmentId,
                $notificationDate,
                $notificationStartTime,
                (int) $staff['user_id']
            );

            if ($effectiveDentistId > 0) {
                Session::set('flash_success', 'Appointment rescheduled successfully.');
                header('Location: ' . $this->url('/staff/appointments?date=' . urlencode($preferredDate)));
                exit;
            }

            Session::set('flash_success', 'Appointment request rescheduled successfully. Assign a dentist to move it to Appointments.');
            header('Location: ' . $this->url('/staff/appointment-requests/show?id=' . urlencode((string) $requestId)));
            exit;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            Session::set('flash_error', $e->getMessage());
            header('Location: ' . $this->url('/staff/appointment-requests/show?id=' . urlencode((string) $requestId)));
            exit;
        }
    }

    public function assignPatient(int $requestId, ?int $patientId): void
    {
        $stmt = $this->db->prepare("
            UPDATE appointment_requests
            SET patient_id = :patient_id,
                updated_at = NOW()
            WHERE request_id = :request_id
        ");

        if ($patientId === null || $patientId <= 0) {
            $stmt->bindValue(':patient_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        }

        $stmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function availableDates(): void
    {
        Auth::requireRole('staff');

        header('Content-Type: application/json');

        $dentistId = (int) ($_GET['dentist_id'] ?? 0);
        $year = (int) ($_GET['year'] ?? date('Y'));
        $month = (int) ($_GET['month'] ?? date('m'));

        if ($dentistId <= 0 || $year <= 0 || $month <= 0) {
            echo json_encode(['available_dates' => []]);
            return;
        }

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $dates = [];

        $current = new DateTime($startDate);
        $end = new DateTime($endDate);

        while ($current <= $end) {
            $date = $current->format('Y-m-d');

            try {
                $slots = $this->availability->getAvailableSlots(
                    $date,
                    (int) ($_GET['service_id'] ?? 0),
                    $dentistId
                );

                if (!empty($slots)) {
                    $dates[] = $date;
                }
            } catch (\Throwable $e) {
                // Skip unavailable/error dates.
            }

            $current->modify('+1 day');
        }

        echo json_encode([
            'available_dates' => $dates,
        ]);
    }

    public function availableSlots(): void
    {
        Auth::requireRole('staff');

        header('Content-Type: application/json');

        $dentistId = (int) ($_GET['dentist_id'] ?? 0);
        $date = trim((string) ($_GET['date'] ?? ''));
        $serviceId = (int) ($_GET['service_id'] ?? 0);

        if ($dentistId <= 0 || $date === '' || $serviceId <= 0) {
            echo json_encode(['slots' => []]);
            return;
        }

        try {
            $slots = $this->availability->getAvailableSlots(
                $date,
                $serviceId,
                $dentistId
            );

            echo json_encode([
                'slots' => array_map(function ($slot) {
                    $start = is_array($slot) ? ($slot['start_time'] ?? '') : (string) $slot;

                    return [
                        'start_time' => $start,
                        'label' => date('h:i A', strtotime($start)),
                    ];
                }, $slots),
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['slots' => []]);
        }
    }

    private function notifyConfirmedAppointment(
        array $request,
        int $dentistId,
        int $appointmentId,
        string $appointmentDate,
        string $startTime,
        int $staffUserId
    ): void {
        try {
            if ($dentistId <= 0 || $appointmentId <= 0) {
                return;
            }

            $patientName = $this->requestPatientName($request);
            $formattedDate = date('M d, Y', strtotime($appointmentDate));
            $formattedTime = date('h:i A', strtotime($startTime));

            $this->notifications->createForDentistAppointment(
                $dentistId,
                $appointmentId,
                'appointment_confirmed',
                'New confirmed appointment',
                $patientName . ' has a confirmed appointment on ' . $formattedDate . ' at ' . $formattedTime . '.',
                $staffUserId
            );
        } catch (\Throwable $e) {
            // Do not stop successful confirmation if notification fails.
        }
    }

    private function notifyRescheduledAppointment(
        array $request,
        int $dentistId,
        int $appointmentId,
        string $appointmentDate,
        string $startTime,
        int $staffUserId
    ): void {
        try {
            if ($dentistId <= 0 || $appointmentId <= 0 || $appointmentDate === '' || $startTime === '') {
                return;
            }

            $patientName = $this->requestPatientName($request);
            $formattedDate = date('M d, Y', strtotime($appointmentDate));
            $formattedTime = date('h:i A', strtotime($startTime));

            $this->notifications->createForDentistAppointment(
                $dentistId,
                $appointmentId,
                'appointment_rescheduled',
                'Appointment rescheduled',
                $patientName . '\'s appointment was rescheduled to ' . $formattedDate . ' at ' . $formattedTime . '.',
                $staffUserId
            );
        } catch (\Throwable $e) {
            // Do not stop successful reschedule if notification fails.
        }
    }

    private function requestPatientName(array $request): string
    {
        $name = trim((string) (
            ($request['patient_first_name'] ?? $request['guest_first_name'] ?? '') . ' ' .
            ($request['patient_last_name'] ?? $request['guest_last_name'] ?? '')
        ));

        return $name !== '' ? $name : 'A patient';
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }

    private function url(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }
}