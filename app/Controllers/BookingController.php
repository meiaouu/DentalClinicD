<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AddressHierarchyRepository;
use App\Repositories\AppointmentRequestRepository;
use App\Repositories\DentistLookupRepository;
use App\Repositories\ServiceOptionRepository;
use App\Repositories\ServiceRepository;
use App\Services\BookingAvailabilityService;
use App\Services\PrivacyConsentService;
use App\Services\SystemSettingService;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

class BookingController
{
    private ServiceRepository $services;
    private AppointmentRequestRepository $appointmentRequests;
    private BookingAvailabilityService $availability;
    private DentistLookupRepository $dentists;
    private ServiceOptionRepository $serviceOptions;
    private AddressHierarchyRepository $addressHierarchy;
    private PrivacyConsentService $privacyConsent;

    public function __construct()
    {
        $this->services = new ServiceRepository();
        $this->appointmentRequests = new AppointmentRequestRepository();
        $this->availability = new BookingAvailabilityService();
        $this->dentists = new DentistLookupRepository();
        $this->serviceOptions = new ServiceOptionRepository();
        $this->addressHierarchy = new AddressHierarchyRepository();
        $this->privacyConsent = new PrivacyConsentService();
    }

    public function entry(): void
    {
        $this->requireOnlineBookingEnabled();

        $errors = Session::get('errors', []);
        $old = Session::get('old', []);

        View::render('public.booking.form', [
            'isGuest' => true,
            'prefillContact' => (string) ($old['contact_number'] ?? ($_GET['contact_number'] ?? '')),
            'prefillEmail' => (string) ($old['email'] ?? ($_GET['email'] ?? '')),
            'services' => $this->services->getActiveServices(),
            'regions' => $this->addressHierarchy->getRegions(),
            'errors' => $errors,
            'old' => $old,
        ]);

        Session::remove('errors');
        Session::remove('old');
    }

    public function guestForm(): void
    {
        $contact = $this->normalizePhilippineMobile((string) ($_GET['contact_number'] ?? ''));
        $email = strtolower(trim((string) ($_GET['email'] ?? '')));

        $selectedServiceIds = $this->normalizeServiceIds(
            $_GET['service_ids'] ?? [],
            (int) ($_GET['service_id'] ?? 0)
        );

        $selectedServiceId = $selectedServiceIds[0] ?? 0;

        $errors = [];

        if ($contact === '') {
            $errors['contact_number'][] = 'Mobile number is required.';
        } elseif (!$this->isValidPhilippineMobile($contact)) {
            $errors['contact_number'][] = 'Please enter a valid Philippine mobile number.';
        }

        if ($email === '') {
            $errors['email'][] = 'Email is required for appointment verification.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please enter a valid email address.';
        } elseif (!$this->emailDomainLooksValid($email)) {
            $errors['email'][] = 'Please enter an email address with a valid email domain.';
        }

        if (empty($selectedServiceIds)) {
            $errors['service_id'][] = 'Please select at least one dental service.';
        }

        if (!empty($errors)) {
            Session::set('errors', $errors);
            Session::set('old', [
                'contact_number' => $contact,
                'email' => $email,
                'service_id' => $selectedServiceId,
                'service_ids' => $selectedServiceIds,
            ]);

            header('Location: ' . $this->url('/?open_booking=1'));
            exit;
        }

        if (!$this->bookingContactIsVerified($contact, $email, $selectedServiceIds)) {
            Session::set('errors', [
                'verification' => ['Please verify your email or phone number before continuing.'],
            ]);

            header('Location: ' . $this->contactOptionsUrl([
                'contact_number' => $contact,
                'email' => $email,
                'service_id' => $selectedServiceId,
                'service_ids' => $selectedServiceIds,
                'privacy_consent' => '1',
                'wants_patient_account' => (string) ($_GET['wants_patient_account'] ?? '0'),
            ], (string) Session::get('delivery_method', 'phone')));
            exit;
        }

        View::render('public.booking.form', [
            'isGuest' => true,
            'prefillContact' => $contact,
            'prefillEmail' => $email,
            'selectedServiceId' => $selectedServiceId,
            'selectedServiceIds' => $selectedServiceIds,
            'services' => $this->services->getActiveServices(),
            'regions' => $this->addressHierarchy->getRegions(),
            'errors' => Session::get('errors', []),
            'old' => Session::get('old', []),
        ]);

        Session::remove('errors');
        Session::remove('old');
    }

    public function calendarAvailability(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!SystemSettingService::enabled('appointment_rules', 'enable_online_booking', true)) {
            echo json_encode([
                'week' => [],
                'success' => false,
                'message' => 'Online booking is disabled.',
            ]);
            return;
        }

        $weekStart = trim((string) ($_GET['week_start'] ?? date('Y-m-d')));

        echo json_encode([
            'week' => $this->availability->getWeekCalendarAvailability($weekStart),
        ]);
    }

    public function availableSlots(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!SystemSettingService::enabled('appointment_rules', 'enable_online_booking', true)) {
            echo json_encode([
                'available_slots' => [],
                'success' => false,
                'message' => 'Online booking is disabled.',
            ]);
            return;
        }

        $date = trim((string) ($_GET['date'] ?? ''));
        $serviceId = (int) ($_GET['service_id'] ?? 0);

        if ($date === '' || $serviceId <= 0 || !$this->isValidDate($date)) {
            echo json_encode([
                'available_slots' => [],
            ]);
            return;
        }

        $service = $this->services->findById($serviceId);

        if (!$service || !$this->isServiceActive($service)) {
            echo json_encode([
                'available_slots' => [],
            ]);
            return;
        }

        $allSlots = [];

        foreach ($this->dentists->getActiveDentists() as $dentist) {
            $dentistId = (int) $dentist['dentist_id'];
            $slots = $this->availability->getAvailableSlots($date, $serviceId, $dentistId, true);

            foreach ($slots as $slot) {
                $key = $slot['start_time'] ?? '';

                if ($key === '') {
                    continue;
                }

                $isPast = !empty($slot['is_past']);
                $isAvailable = !empty($slot['is_available']);

                if (!isset($allSlots[$key])) {
                    $allSlots[$key] = [
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time'] ?? null,
                        'label' => $slot['label'] ?? $slot['start_time'],
                        'is_past' => $isPast,
                        'is_available' => $isAvailable,
                    ];

                    continue;
                }

                if ($isAvailable) {
                    $allSlots[$key]['is_past'] = false;
                    $allSlots[$key]['is_available'] = true;
                }
            }
        }

        ksort($allSlots);

        echo json_encode([
            'available_slots' => array_values($allSlots),
        ]);
    }

    public function serviceQuestions(): void
    {
        $serviceId = (int) ($_GET['service_id'] ?? 0);

        header('Content-Type: application/json; charset=utf-8');

        if ($serviceId <= 0) {
            echo json_encode([
                'questions' => [],
            ]);
            return;
        }

        $service = $this->services->findById($serviceId);

        if (!$service || !$this->isServiceActive($service)) {
            echo json_encode([
                'questions' => [],
            ]);
            return;
        }

        echo json_encode([
            'questions' => $this->serviceOptions->getByServiceId($serviceId),
        ]);
    }

    public function serviceMeta(): void
    {
        $serviceId = (int) ($_GET['service_id'] ?? 0);
        $service = $this->services->findById($serviceId);

        header('Content-Type: application/json; charset=utf-8');

        if (!$service || !$this->isServiceActive($service)) {
            echo json_encode([
                'service' => null,
            ]);
            return;
        }

        echo json_encode([
            'service' => $service,
        ]);
    }

    public function availableDentists(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'dentists' => $this->dentists->getActiveDentists(),
        ]);
    }

    public function provinces(): void
    {
        $regionId = (int) ($_GET['region_id'] ?? 0);

        header('Content-Type: application/json; charset=utf-8');

        if ($regionId <= 0) {
            echo json_encode([
                'provinces' => [],
            ]);
            return;
        }

        echo json_encode([
            'provinces' => $this->addressHierarchy->getProvincesByRegion($regionId),
        ]);
    }

    public function cities(): void
    {
        $provinceId = (int) ($_GET['province_id'] ?? 0);

        header('Content-Type: application/json; charset=utf-8');

        if ($provinceId <= 0) {
            echo json_encode([
                'cities' => [],
            ]);
            return;
        }

        echo json_encode([
            'cities' => $this->addressHierarchy->getCitiesByProvince($provinceId),
        ]);
    }

    public function barangays(): void
    {
        $cityId = (int) ($_GET['city_id'] ?? 0);

        header('Content-Type: application/json; charset=utf-8');

        if ($cityId <= 0) {
            echo json_encode([
                'barangays' => [],
            ]);
            return;
        }

        echo json_encode([
            'barangays' => $this->addressHierarchy->getBarangaysByCity($cityId),
        ]);
    }

    public function review(): void
    {
        $this->requireGuestBookingEnabled();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        [$booking, $selectedServices, $errors] = $this->validateBookingInput($_POST);

        if (!$this->bookingContactIsVerified($booking['contact_number'], $booking['email'], $booking['service_ids'])) {
            $errors['verification'][] = 'Please verify your email or phone number before continuing.';
        }

        if (!empty($errors)) {
            Session::set('errors', $errors);
            Session::set('old', $booking);

            header('Location: ' . $this->guestFormUrl($booking));
            exit;
        }

        $serviceNames = [];

        foreach ($selectedServices as $service) {
            $serviceNames[] = $this->getServiceName($service);
        }

        View::render('public.booking.review', [
            'booking' => $booking,
            'serviceName' => $serviceNames[0] ?? 'Selected Service',
            'serviceNames' => $serviceNames,
            'selectedServiceIds' => $booking['service_ids'],
            'contact_number' => $booking['contact_number'],
            'email' => $booking['email'],
        ]);
    }

    public function store(): void
    {
        $this->requireGuestBookingEnabled();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }

        [$booking, $selectedServices, $errors] = $this->validateBookingInput($_POST);

        if (!$this->bookingContactIsVerified($booking['contact_number'], $booking['email'], $booking['service_ids'])) {
            $errors['verification'][] = 'Please verify your email or phone number before submitting your appointment request.';
        }

        if (!empty($errors)) {
            Session::set('errors', $errors);
            Session::set('old', $booking);

            header('Location: ' . $this->guestFormUrl($booking));
            exit;
        }

        $serviceId = (int) ($booking['service_id'] ?? 0);
        $service = $this->services->findById($serviceId);

        if (!$service || !$this->isServiceActive($service)) {
            Session::set('errors', [
                'service_id' => ['Selected service is invalid or inactive.'],
            ]);

            Session::set('old', $booking);

            header('Location: ' . $this->guestFormUrl($booking));
            exit;
        }

        $db = Database::getConnection();
        $startedTransaction = !$db->inTransaction();

        if ($startedTransaction) {
            $db->beginTransaction();
        }

        try {
            $requestCode = 'REQ-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

            $address = trim(
                implode(', ', array_filter([
                    $booking['address_line'],
                    'Barangay ID: ' . $booking['barangay_id'],
                    'City ID: ' . $booking['city_id'],
                    'Province ID: ' . $booking['province_id'],
                    'Region ID: ' . $booking['region_id'],
                ]))
            );

            $requestData = [
                'request_code' => $requestCode,
                'patient_id' => null,
                'is_guest' => 1,
                'source_channel' => 'web',
                'guest_first_name' => $booking['first_name'],
                'guest_middle_name' => $booking['middle_name'] !== '' ? $booking['middle_name'] : null,
                'guest_last_name' => $booking['last_name'],
                'guest_contact_number' => $this->normalizePhilippineMobile((string) $booking['contact_number']),
                'guest_email' => strtolower(trim((string) $booking['email'])),
                'sex' => $booking['sex'] !== '' ? $booking['sex'] : null,
                'birth_date' => $booking['birth_date'] !== '' ? $booking['birth_date'] : null,
                'civil_status' => $booking['civil_status'] !== '' ? $booking['civil_status'] : null,
                'occupation' => $booking['occupation'] !== '' ? $booking['occupation'] : null,
                'address' => $address,
                'emergency_contact_name' => $booking['emergency_contact_name'] !== '' ? $booking['emergency_contact_name'] : null,
                'emergency_contact_number' => $booking['emergency_contact_number'] !== '' ? $this->normalizePhilippineMobile((string) $booking['emergency_contact_number']) : null,
                'preferred_dentist_id' => $booking['preferred_dentist_id'] > 0 ? $booking['preferred_dentist_id'] : null,
                'service_id' => $serviceId,
                'preferred_date' => $booking['preferred_date'],
                'preferred_start_time' => $booking['preferred_start_time'],
                'notes' => $booking['notes'],
                'request_status' => 'pending',
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'converted_appointment_id' => null,
                'staff_notes' => null,
                'wants_patient_account' => $booking['wants_patient_account'] === '1' ? 1 : 0,
            ];

            if ($requestData['guest_contact_number'] === '') {
                throw new RuntimeException('Contact number is required.');
            }

            if (!$this->isValidPhilippineMobile($requestData['guest_contact_number'])) {
                throw new RuntimeException('Please enter a valid Philippine mobile number.');
            }

            if ($requestData['guest_email'] === '') {
                throw new RuntimeException('Email is required for appointment verification.');
            }

            if (!filter_var($requestData['guest_email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid email address.');
            }

            if (!$this->emailDomainLooksValid($requestData['guest_email'])) {
                throw new RuntimeException('Please enter an email address with a valid email domain.');
            }

            if (method_exists($this->appointmentRequests, 'createWithServices')) {
                $requestId = $this->appointmentRequests->createWithServices($requestData, $booking['service_ids']);
            } else {
                $requestId = $this->appointmentRequests->create($requestData);
            }

            $fullName = trim(
                implode(' ', array_filter([
                    $requestData['guest_first_name'],
                    $requestData['guest_middle_name'],
                    $requestData['guest_last_name'],
                ]))
            );

            $this->privacyConsent->recordConsent([
                'user_id' => null,
                'patient_id' => null,
                'appointment_request_id' => $requestId,
                'full_name' => $fullName,
                'email' => $requestData['guest_email'],
                'contact_number' => $requestData['guest_contact_number'],
                'consent_type' => PrivacyConsentService::BOOKING_CONSENT_TYPE,
                'consent_version' => PrivacyConsentService::CONSENT_VERSION,
                'consent_text' => PrivacyConsentService::BOOKING_CONSENT_TEXT,
                'accepted' => 1,
                'source_form' => 'guest_booking',
            ]);

            if ($startedTransaction) {
                $db->commit();
            }

            header('Location: ' . $this->url('/book/success?request_code=' . urlencode($requestCode)));
            exit;
        } catch (RuntimeException $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }

            error_log('[BookingController::store RuntimeException] ' . $e->getMessage());

            Session::set('errors', [
                'booking' => [$e->getMessage()],
            ]);

            Session::set('old', $booking);

            header('Location: ' . $this->guestFormUrl([
                'contact_number' => $this->normalizePhilippineMobile((string) ($_POST['contact_number'] ?? '')),
                'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
                'service_id' => (int) ($_POST['service_id'] ?? 0),
                'service_ids' => $_POST['service_ids'] ?? [],
                'privacy_consent' => (string) ($_POST['privacy_consent'] ?? '0'),
                'wants_patient_account' => (string) ($_POST['wants_patient_account'] ?? '0'),
            ]));
            exit;
        } catch (Throwable $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }

            error_log('[BookingController::store] ' . $e->getMessage());

            Session::set('errors', [
                'booking' => ['Something went wrong. Please try again.'],
            ]);

            Session::set('old', $booking);

            header('Location: ' . $this->guestFormUrl([
                'contact_number' => $this->normalizePhilippineMobile((string) ($_POST['contact_number'] ?? '')),
                'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
                'service_id' => (int) ($_POST['service_id'] ?? 0),
                'service_ids' => $_POST['service_ids'] ?? [],
                'privacy_consent' => (string) ($_POST['privacy_consent'] ?? '0'),
                'wants_patient_account' => (string) ($_POST['wants_patient_account'] ?? '0'),
            ]));
            exit;
        }
    }

    public function success(): void
    {
        $requestCode = trim((string) ($_GET['request_code'] ?? ''));

        View::render('public.booking.success', [
            'requestCode' => $requestCode,
        ]);
    }

    public function contactOptions(): void
    {
        $this->requireGuestBookingEnabled();

        $contact = $this->normalizePhilippineMobile((string) ($_GET['contact_number'] ?? ''));
        $email = strtolower(trim((string) ($_GET['email'] ?? '')));

        $selectedServiceIds = $this->normalizeServiceIds(
            $_GET['service_ids'] ?? [],
            (int) ($_GET['service_id'] ?? 0)
        );

        $selectedServiceId = $selectedServiceIds[0] ?? 0;

        $errors = [];

        if ($contact === '') {
            $errors['contact_number'][] = 'Mobile number is required.';
        } elseif (!$this->isValidPhilippineMobile($contact)) {
            $errors['contact_number'][] = 'Please enter a valid Philippine mobile number.';
        }

        if ($email === '') {
            $errors['email'][] = 'Email is required for appointment verification.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please enter a valid email address.';
        } elseif (!$this->emailDomainLooksValid($email)) {
            $errors['email'][] = 'Please enter an email address with a valid email domain.';
        }

        if (empty($selectedServiceIds)) {
            $errors['service_id'][] = 'Please select at least one dental service.';
        }

        if ((string) ($_GET['privacy_consent'] ?? '') !== '1') {
            $errors['privacy_consent'][] = 'Privacy consent is required before booking.';
        }

        if (!empty($errors)) {
            Session::set('errors', $errors);
            Session::set('old', [
                'contact_number' => $contact,
                'email' => $email,
                'service_id' => $selectedServiceId,
                'service_ids' => $selectedServiceIds,
                'privacy_consent' => (string) ($_GET['privacy_consent'] ?? '0'),
                'wants_patient_account' => (string) ($_GET['wants_patient_account'] ?? '0'),
            ]);

            header('Location: ' . $this->url('/?open_booking=1'));
            exit;
        }

        Session::set('booking_contact_verification', [
            'contact_number' => $contact,
            'email' => $email,
            'service_id' => $selectedServiceId,
            'service_ids' => $selectedServiceIds,
            'privacy_consent' => '1',
            'wants_patient_account' => (string) ($_GET['wants_patient_account'] ?? '0'),
            'expires_at' => time() + 900,
        ]);

        Session::remove('booking_contact_otp');
        Session::remove('booking_contact_verified');

        $selectedMethod = strtolower(trim((string) ($_GET['delivery_method'] ?? Session::get('delivery_method', 'phone'))));

        if (!in_array($selectedMethod, ['phone', 'email'], true)) {
            $selectedMethod = 'phone';
        }

        View::render('public.booking.contact-options', [
            'contactNumber' => $contact,
            'email' => $email,
            'contactNumberMasked' => $this->maskPhone($contact),
            'emailMasked' => $this->maskEmail($email),
            'selectedMethod' => $selectedMethod,
            'otpCooldownSeconds' => (int) Session::get('otp_cooldown_seconds', 0),
            'errors' => Session::get('errors', []),
            'success' => Session::get('success'),
        ]);

        Session::remove('otp_cooldown_seconds');
        Session::remove('delivery_method');
        Session::remove('errors');
        Session::remove('success');
    }

    public function sendVerificationOtp(): void
    {
        $this->requireGuestBookingEnabled();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::set('errors', [
                'verification' => ['Invalid request. Please refresh the page and try again.'],
            ]);

            header('Location: ' . $this->url('/?open_booking=1'));
            exit;
        }

        $pending = Session::get('booking_contact_verification');

        if (!$this->validPendingContactVerification($pending)) {
            Session::set('errors', [
                'verification' => ['Your verification session expired. Please start again.'],
            ]);

            header('Location: ' . $this->url('/?open_booking=1'));
            exit;
        }

        $method = strtolower(trim((string) ($_POST['delivery_method'] ?? '')));

        if (!in_array($method, ['email', 'phone'], true)) {
            Session::set('errors', [
                'verification' => ['Please choose where to receive your OTP.'],
            ]);

            header('Location: ' . $this->contactOptionsUrl($pending, 'phone'));
            exit;
        }

        $contact = $this->normalizePhilippineMobile((string) ($_POST['contact_number'] ?? $pending['contact_number'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? $pending['email'] ?? '')));

        $errors = [];

        if ($contact === '') {
            $errors['verification'][] = 'Phone number is required.';
        } elseif (!$this->isValidPhilippineMobile($contact)) {
            $errors['verification'][] = 'Please enter a valid Philippine mobile number.';
        }

        if ($email === '') {
            $errors['verification'][] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['verification'][] = 'Please enter a valid email address.';
        } elseif (!$this->emailDomainLooksValid($email)) {
            $errors['verification'][] = 'Please enter an email address with a valid email domain.';
        }

        $pending['contact_number'] = $contact;
        $pending['email'] = $email;
        $pending['expires_at'] = time() + 900;

        Session::set('booking_contact_verification', $pending);
        Session::set('delivery_method', $method);

        if (!empty($errors)) {
            Session::set('errors', $errors);

            header('Location: ' . $this->contactOptionsUrl($pending, $method));
            exit;
        }

        $ipAddress = $this->ipAddress();
        $userAgent = $this->userAgent();

        if ($this->countRecentBookingOtpSends($contact, $email, $ipAddress) >= 3) {
            Session::set('otp_cooldown_seconds', 30);
            Session::set('errors', [
                'verification' => ['Too many OTP requests. Please try again after 30 seconds.'],
            ]);

            header('Location: ' . $this->contactOptionsUrl($pending, $method));
            exit;
        }

        $destination = $method === 'email' ? $email : $contact;
        $destinationMasked = $method === 'email'
            ? $this->maskEmail($email)
            : $this->maskPhone($contact);

        $otp = (string) random_int(100000, 999999);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);

        try {
            $db = Database::getConnection();

            $stmt = $db->prepare("
                INSERT INTO booking_contact_otps (
                    contact_number,
                    email,
                    delivery_method,
                    destination_masked,
                    otp_hash,
                    expires_at,
                    verified_at,
                    used_at,
                    failed_attempts,
                    locked_until,
                    ip_address,
                    user_agent,
                    created_at
                ) VALUES (
                    :contact_number,
                    :email,
                    :delivery_method,
                    :destination_masked,
                    :otp_hash,
                    DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                    NULL,
                    NULL,
                    0,
                    NULL,
                    :ip_address,
                    :user_agent,
                    NOW()
                )
            ");

            $stmt->execute([
                ':contact_number' => $contact,
                ':email' => $email,
                ':delivery_method' => $method,
                ':destination_masked' => $destinationMasked,
                ':otp_hash' => $otpHash,
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
            ]);

            $otpId = (int) $db->lastInsertId();

            $sent = $this->sendBookingVerificationOtp($method, $destination, $otp);
            $this->logBookingOtpDelivery($method, $destination, $sent);

            unset($otp);

            if (!$sent) {
                Session::set('errors', [
                    'verification' => [
                        $method === 'phone'
    ? 'Phone OTP could not be sent right now. Please try Email or contact the clinic.'
    : 'Email OTP could not be sent. Please check your mail settings.'
                    ],
                ]);

                Session::set('delivery_method', $method);

                header('Location: ' . $this->contactOptionsUrl($pending, $method));
                exit;
            }

            Session::set('booking_contact_otp', [
                'otp_id' => $otpId,
                'contact_number' => $contact,
                'email' => $email,
                'service_id' => (int) $pending['service_id'],
                'service_ids' => $pending['service_ids'],
                'privacy_consent' => '1',
                'wants_patient_account' => (string) ($pending['wants_patient_account'] ?? '0'),
                'expires_at' => time() + 600,
            ]);

            Session::set('success', 'OTP sent to your selected verification method.');

            header('Location: ' . $this->url('/book/verify-contact'));
            exit;
        } catch (Throwable $e) {
            error_log('[BookingController::sendVerificationOtp] ' . $e->getMessage());

            Session::set('errors', [
                'verification' => ['Unable to send OTP. Please try again.'],
            ]);

            header('Location: ' . $this->contactOptionsUrl($pending, $method));
            exit;
        }
    }

    public function showVerifyContactOtp(): void
    {
        $this->requireGuestBookingEnabled();

        $otpSession = Session::get('booking_contact_otp');

        if (!$this->validBookingOtpSession($otpSession)) {
            Session::set('errors', [
                'verification' => ['Your OTP session expired. Please start again.'],
            ]);

            header('Location: ' . $this->url('/?open_booking=1'));
            exit;
        }

        View::render('public.booking.verify-contact', [
            'errors' => Session::get('errors', []),
            'success' => Session::get('success'),
        ]);

        Session::remove('errors');
        Session::remove('success');
    }

    public function verifyContactOtp(): void
    {
        $this->requireGuestBookingEnabled();

        if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
            Session::set('errors', [
                'otp' => ['Invalid request. Please refresh the page and try again.'],
            ]);

            header('Location: ' . $this->url('/book/verify-contact'));
            exit;
        }

        $otpSession = Session::get('booking_contact_otp');

        if (!$this->validBookingOtpSession($otpSession)) {
            Session::set('errors', [
                'verification' => ['Your OTP session expired. Please start again.'],
            ]);

            header('Location: ' . $this->url('/?open_booking=1'));
            exit;
        }

        $otp = trim((string) ($_POST['otp'] ?? ''));

        if (!preg_match('/^\d{6}$/', $otp)) {
            Session::set('errors', [
                'otp' => ['Please enter a valid 6-digit OTP.'],
            ]);

            header('Location: ' . $this->url('/book/verify-contact'));
            exit;
        }

        try {
            $db = Database::getConnection();

            $stmt = $db->prepare("
                SELECT *
                FROM booking_contact_otps
                WHERE otp_id = :otp_id
                  AND contact_number = :contact_number
                  AND email = :email
                  AND used_at IS NULL
                LIMIT 1
            ");

            $stmt->execute([
                ':otp_id' => (int) $otpSession['otp_id'],
                ':contact_number' => (string) $otpSession['contact_number'],
                ':email' => (string) $otpSession['email'],
            ]);

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                Session::set('errors', [
                    'otp' => ['Invalid or expired OTP.'],
                ]);

                header('Location: ' . $this->url('/book/verify-contact'));
                exit;
            }

            if (!empty($row['locked_until']) && strtotime((string) $row['locked_until']) > time()) {
                Session::set('errors', [
                    'otp' => ['Too many wrong OTP attempts. Please try again after 15 minutes.'],
                ]);

                header('Location: ' . $this->url('/book/verify-contact'));
                exit;
            }

            if (strtotime((string) $row['expires_at']) < time()) {
                Session::set('errors', [
                    'otp' => ['Invalid or expired OTP.'],
                ]);

                header('Location: ' . $this->url('/book/verify-contact'));
                exit;
            }

            if (!password_verify($otp, (string) $row['otp_hash'])) {
                $failedAttempts = (int) $row['failed_attempts'] + 1;

                $lockedSql = $failedAttempts >= 5
                    ? 'DATE_ADD(NOW(), INTERVAL 15 MINUTE)'
                    : 'NULL';

                $failStmt = $db->prepare("
                    UPDATE booking_contact_otps
                    SET failed_attempts = :failed_attempts,
                        locked_until = {$lockedSql}
                    WHERE otp_id = :otp_id
                    LIMIT 1
                ");

                $failStmt->execute([
                    ':failed_attempts' => $failedAttempts,
                    ':otp_id' => (int) $otpSession['otp_id'],
                ]);

                Session::set('errors', [
                    'otp' => [
                        $failedAttempts >= 5
                            ? 'Too many wrong OTP attempts. Please try again after 15 minutes.'
                            : 'Invalid OTP.'
                    ],
                ]);

                header('Location: ' . $this->url('/book/verify-contact'));
                exit;
            }

            $verifyStmt = $db->prepare("
                UPDATE booking_contact_otps
                SET verified_at = NOW(),
                    used_at = NOW()
                WHERE otp_id = :otp_id
                  AND used_at IS NULL
                LIMIT 1
            ");

            $verifyStmt->execute([
                ':otp_id' => (int) $otpSession['otp_id'],
            ]);

            Session::set('booking_contact_verified', [
                'contact_number' => (string) $otpSession['contact_number'],
                'email' => (string) $otpSession['email'],
                'service_id' => (int) $otpSession['service_id'],
                'service_ids' => $otpSession['service_ids'],
                'privacy_consent' => '1',
                'wants_patient_account' => (string) ($otpSession['wants_patient_account'] ?? '0'),
                'expires_at' => time() + 1800,
            ]);

            Session::remove('booking_contact_otp');

            header('Location: ' . $this->guestFormUrl([
                'contact_number' => (string) $otpSession['contact_number'],
                'email' => (string) $otpSession['email'],
                'service_id' => (int) $otpSession['service_id'],
                'service_ids' => $otpSession['service_ids'],
                'privacy_consent' => '1',
                'wants_patient_account' => (string) ($otpSession['wants_patient_account'] ?? '0'),
            ]));
            exit;
        } catch (Throwable $e) {
            error_log('[BookingController::verifyContactOtp] ' . $e->getMessage());

            Session::set('errors', [
                'otp' => ['Unable to verify OTP. Please try again.'],
            ]);

            header('Location: ' . $this->url('/book/verify-contact'));
            exit;
        }
    }

    private function contactOptionsUrl(array $pending, string $method = 'phone'): string
    {
        return $this->url('/book/contact-options?' . http_build_query([
            'contact_number' => $this->normalizePhilippineMobile((string) ($pending['contact_number'] ?? '')),
            'email' => strtolower(trim((string) ($pending['email'] ?? ''))),
            'service_id' => (int) ($pending['service_id'] ?? 0),
            'service_ids' => $pending['service_ids'] ?? [],
            'privacy_consent' => '1',
            'wants_patient_account' => (string) ($pending['wants_patient_account'] ?? '0'),
            'delivery_method' => $method,
        ]));
    }

    private function validateBookingInput(array $source): array
    {
        $selectedServiceIds = $this->normalizeServiceIds(
            $source['service_ids'] ?? [],
            (int) ($source['service_id'] ?? 0)
        );

        $primaryServiceId = $selectedServiceIds[0] ?? 0;

        $booking = [
            'first_name' => $this->cleanText($source['first_name'] ?? '', 80),
            'middle_name' => $this->cleanText($source['middle_name'] ?? '', 80),
            'last_name' => $this->cleanText($source['last_name'] ?? '', 80),
            'contact_number' => $this->normalizePhilippineMobile((string) ($source['contact_number'] ?? '')),
            'email' => strtolower($this->cleanText($source['email'] ?? '', 120)),
            'birth_date' => trim((string) ($source['birth_date'] ?? '')),
            'sex' => $this->cleanText($source['sex'] ?? '', 20),
            'civil_status' => $this->cleanText($source['civil_status'] ?? '', 40),
            'occupation' => $this->cleanText($source['occupation'] ?? '', 120),
            'region_id' => (string) max(0, (int) ($source['region_id'] ?? 0)),
            'province_id' => (string) max(0, (int) ($source['province_id'] ?? 0)),
            'city_id' => (string) max(0, (int) ($source['city_id'] ?? 0)),
            'barangay_id' => (string) max(0, (int) ($source['barangay_id'] ?? 0)),
            'address_line' => $this->cleanText($source['address_line'] ?? '', 255),
            'emergency_contact_name' => $this->cleanText($source['emergency_contact_name'] ?? '', 120),
            'emergency_contact_number' => $this->normalizePhilippineMobile((string) ($source['emergency_contact_number'] ?? '')),
            'service_id' => (string) $primaryServiceId,
            'service_ids' => $selectedServiceIds,
            'preferred_date' => trim((string) ($source['preferred_date'] ?? '')),
            'preferred_start_time' => $this->normalizeTime((string) ($source['preferred_start_time'] ?? '')),
            'preferred_dentist_id' => max(0, (int) ($source['preferred_dentist_id'] ?? 0)),
            'notes' => $this->cleanText($source['notes'] ?? '', 1000),
            'privacy_consent' => isset($source['privacy_consent']) && (string) $source['privacy_consent'] === '1' ? '1' : '0',
            'wants_patient_account' => isset($source['wants_patient_account']) && (string) $source['wants_patient_account'] === '1' ? '1' : '0',
        ];

        foreach ($source as $key => $value) {
            if (strpos((string) $key, 'option_') === 0) {
                $booking[$key] = is_array($value) ? $value : $this->cleanText($value, 500);
            }
        }

        $errors = [];
        $selectedServices = [];

        if (!SystemSettingService::enabled('appointment_rules', 'enable_online_booking', true)) {
            $errors['booking'][] = 'Online booking is currently disabled.';
        }

        if (!SystemSettingService::enabled('appointment_rules', 'enable_guest_booking', true)) {
            $errors['booking'][] = 'Guest booking is currently disabled.';
        }

        if ($booking['first_name'] === '') {
            $errors['first_name'][] = 'First name is required.';
        } elseif (!$this->isValidPersonName($booking['first_name'])) {
            $errors['first_name'][] = 'First name contains invalid characters.';
        }

        if ($booking['middle_name'] !== '' && !$this->isValidPersonName($booking['middle_name'])) {
            $errors['middle_name'][] = 'Middle name contains invalid characters.';
        }

        if ($booking['last_name'] === '') {
            $errors['last_name'][] = 'Last name is required.';
        } elseif (!$this->isValidPersonName($booking['last_name'])) {
            $errors['last_name'][] = 'Last name contains invalid characters.';
        }

        if ($booking['contact_number'] === '') {
            $errors['contact_number'][] = 'Contact number is required.';
        } elseif (!$this->isValidPhilippineMobile($booking['contact_number'])) {
            $errors['contact_number'][] = 'Please enter a valid Philippine mobile number.';
        }

        if ($booking['email'] === '') {
            $errors['email'][] = 'Email is required for appointment verification.';
        } elseif (!filter_var($booking['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Please enter a valid email address.';
        } elseif (!$this->emailDomainLooksValid($booking['email'])) {
            $errors['email'][] = 'Please enter an email address with a valid email domain.';
        }

        if ($booking['birth_date'] === '') {
            $errors['birth_date'][] = 'Birthdate is required.';
        } elseif (!$this->isValidDate($booking['birth_date'])) {
            $errors['birth_date'][] = 'Birthdate is invalid.';
        } else {
            $birthTimestamp = strtotime($booking['birth_date']);

            if ($birthTimestamp === false) {
                $errors['birth_date'][] = 'Birthdate is invalid.';
            } elseif ($birthTimestamp > time()) {
                $errors['birth_date'][] = 'Birthdate cannot be in the future.';
            } elseif ($birthTimestamp < strtotime('-120 years')) {
                $errors['birth_date'][] = 'Birthdate is too old. Please check the date.';
            }
        }

        $allowedSex = ['male', 'female', 'other'];

        if ($booking['sex'] === '') {
            $errors['sex'][] = 'Sex is required.';
        } elseif (!in_array(strtolower($booking['sex']), $allowedSex, true)) {
            $errors['sex'][] = 'Please select a valid sex.';
        }

        if ($booking['civil_status'] === '') {
            $errors['civil_status'][] = 'Civil status is required.';
        }

        if ((int) $booking['region_id'] <= 0) {
            $errors['region_id'][] = 'Please select a region.';
        }

        if ((int) $booking['province_id'] <= 0) {
            $errors['province_id'][] = 'Please select a province.';
        }

        if ((int) $booking['city_id'] <= 0) {
            $errors['city_id'][] = 'Please select a city or municipality.';
        }

        if ((int) $booking['barangay_id'] <= 0) {
            $errors['barangay_id'][] = 'Please select a barangay.';
        }

        if ($booking['address_line'] === '') {
            $errors['address_line'][] = 'Street, house number, or address line is required.';
        }

        if ($booking['emergency_contact_name'] !== '' && !$this->isValidPersonName($booking['emergency_contact_name'])) {
            $errors['emergency_contact_name'][] = 'Emergency contact name contains invalid characters.';
        }

        if ($booking['emergency_contact_number'] !== '' && !$this->isValidPhilippineMobile($booking['emergency_contact_number'])) {
            $errors['emergency_contact_number'][] = 'Please enter a valid emergency contact number.';
        }

        if (empty($selectedServiceIds)) {
            $errors['service_id'][] = 'Please select at least one service.';
        }

        foreach ($selectedServiceIds as $serviceId) {
            $service = $this->services->findById((int) $serviceId);

            if (!$service || !$this->isServiceActive($service)) {
                $errors['service_id'][] = 'One or more selected services are invalid or inactive.';
                break;
            }

            $selectedServices[] = $service;
        }

        if ($booking['preferred_dentist_id'] > 0 && !$this->isActiveDentist((int) $booking['preferred_dentist_id'])) {
            $errors['preferred_dentist_id'][] = 'Selected dentist is invalid or inactive.';
        }

        if ($booking['preferred_date'] === '') {
            $errors['preferred_date'][] = 'Preferred date is required.';
        } elseif (!$this->isValidDate($booking['preferred_date'])) {
            $errors['preferred_date'][] = 'Preferred date is invalid.';
        }

        if ($booking['preferred_start_time'] === '') {
            $errors['preferred_start_time'][] = 'Preferred time is required.';
        } elseif (!$this->isValidTime($booking['preferred_start_time'])) {
            $errors['preferred_start_time'][] = 'Preferred time is invalid.';
        }

        if (
            empty($errors['preferred_date']) &&
            empty($errors['preferred_start_time']) &&
            $booking['preferred_date'] !== '' &&
            $booking['preferred_start_time'] !== ''
        ) {
            $this->validateAppointmentSchedule($booking, $selectedServices, $errors);
        }

        if ($booking['privacy_consent'] !== '1') {
            $errors['privacy_consent'][] = 'Privacy consent is required before booking.';
        }

        return [$booking, $selectedServices, $errors];
    }

    private function validateAppointmentSchedule(array $booking, array $selectedServices, array &$errors): void
    {
        $appointmentTimestamp = strtotime($booking['preferred_date'] . ' ' . $booking['preferred_start_time']);

        if ($appointmentTimestamp === false) {
            $errors['preferred_date'][] = 'Preferred appointment schedule is invalid.';
            return;
        }

        $allowSameDay = SystemSettingService::enabled('appointment_rules', 'allow_same_day_booking', false);
        $minimumNoticeHours = SystemSettingService::int('appointment_rules', 'minimum_booking_notice_hours', 24);
        $maximumAdvanceDays = SystemSettingService::int('appointment_rules', 'maximum_advance_booking_days', 30);
        $maximumAppointmentsPerDay = SystemSettingService::int('appointment_rules', 'maximum_appointments_per_day', 30);

        if ($appointmentTimestamp < time()) {
            $errors['preferred_date'][] = 'Appointment schedule cannot be in the past.';
        }

        if (!$allowSameDay && date('Y-m-d', $appointmentTimestamp) === date('Y-m-d')) {
            $errors['preferred_date'][] = 'Same-day booking is currently not allowed.';
        }

        if ($appointmentTimestamp < strtotime('+' . max(0, $minimumNoticeHours) . ' hours')) {
            $errors['preferred_date'][] = 'Appointment must be booked at least ' . $minimumNoticeHours . ' hour/s before the schedule.';
        }

        if (strtotime($booking['preferred_date']) > strtotime('+' . max(1, $maximumAdvanceDays) . ' days')) {
            $errors['preferred_date'][] = 'Appointment cannot be booked more than ' . $maximumAdvanceDays . ' day/s in advance.';
        }

        $this->validateClinicHours(
            $booking['preferred_date'],
            $booking['preferred_start_time'],
            $errors
        );

        if ($maximumAppointmentsPerDay > 0) {
            $currentCount = $this->countAppointmentRequestsForDate($booking['preferred_date']);

            if ($currentCount >= $maximumAppointmentsPerDay) {
                $errors['preferred_date'][] = 'This date already reached the maximum number of appointment requests.';
            }
        }

        if (!empty($selectedServices)) {
            $totalDuration = $this->totalServiceDuration($selectedServices);

            if ($totalDuration <= 0) {
                $totalDuration = SystemSettingService::int('appointment_rules', 'default_appointment_duration', 30);
            }

            if ($totalDuration <= 0) {
                $errors['service_id'][] = 'Selected service duration is invalid.';
            }
        }
    }

    private function validateClinicHours(string $date, string $time, array &$errors): void
    {
        try {
            $db = Database::getConnection();
            $dayOfWeek = (int) date('w', strtotime($date));

            $stmt = $db->prepare("
                SELECT is_open, opening_time, closing_time, break_start, break_end
                FROM clinic_hours
                WHERE day_of_week = :day_of_week
                LIMIT 1
            ");

            $stmt->execute([
                ':day_of_week' => $dayOfWeek,
            ]);

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row || (int) ($row['is_open'] ?? 0) !== 1) {
                $errors['preferred_date'][] = 'The clinic is closed on the selected date.';
                return;
            }

            $selected = strtotime($date . ' ' . $time);
            $opening = strtotime($date . ' ' . substr((string) ($row['opening_time'] ?? ''), 0, 5));
            $closing = strtotime($date . ' ' . substr((string) ($row['closing_time'] ?? ''), 0, 5));

            if ($selected === false || $opening === false || $closing === false) {
                $errors['preferred_start_time'][] = 'Clinic hours are not properly configured.';
                return;
            }

            if ($selected < $opening || $selected >= $closing) {
                $errors['preferred_start_time'][] = 'Selected time is outside clinic hours.';
            }

            $breakStartRaw = trim((string) ($row['break_start'] ?? ''));
            $breakEndRaw = trim((string) ($row['break_end'] ?? ''));

            if ($breakStartRaw !== '' && $breakEndRaw !== '') {
                $breakStart = strtotime($date . ' ' . substr($breakStartRaw, 0, 5));
                $breakEnd = strtotime($date . ' ' . substr($breakEndRaw, 0, 5));

                if ($breakStart !== false && $breakEnd !== false && $selected >= $breakStart && $selected < $breakEnd) {
                    $errors['preferred_start_time'][] = 'Selected time is during clinic break time.';
                }
            }
        } catch (Throwable $e) {
            error_log('[BookingController::validateClinicHours] ' . $e->getMessage());
        }
    }

    private function countAppointmentRequestsForDate(string $date): int
    {
        try {
            $db = Database::getConnection();

            $total = 0;

            try {
                $stmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM appointment_requests
                    WHERE preferred_date = :preferred_date
                      AND request_status NOT IN ('cancelled', 'rejected')
                ");

                $stmt->execute([
                    ':preferred_date' => $date,
                ]);

                $total += (int) $stmt->fetchColumn();
            } catch (Throwable $e) {
                error_log('[BookingController::countAppointmentRequestsForDate appointment_requests] ' . $e->getMessage());
            }

            try {
                $stmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM appointments
                    WHERE appointment_date = :appointment_date
                      AND status NOT IN ('cancelled', 'rejected', 'no_show')
                ");

                $stmt->execute([
                    ':appointment_date' => $date,
                ]);

                $total += (int) $stmt->fetchColumn();
            } catch (Throwable $e) {
                error_log('[BookingController::countAppointmentRequestsForDate appointments] ' . $e->getMessage());
            }

            return $total;
        } catch (Throwable $e) {
            error_log('[BookingController::countAppointmentRequestsForDate] ' . $e->getMessage());
            return 0;
        }
    }

    private function totalServiceDuration(array $services): int
    {
        $total = 0;

        foreach ($services as $service) {
            if (is_array($service)) {
                $total += (int) ($service['estimated_duration_minutes'] ?? 0);
            } elseif (is_object($service)) {
                $total += (int) ($service->estimated_duration_minutes ?? 0);
            }
        }

        return $total;
    }

    private function isActiveDentist(int $dentistId): bool
    {
        if ($dentistId <= 0) {
            return false;
        }

        foreach ($this->dentists->getActiveDentists() as $dentist) {
            if ((int) ($dentist['dentist_id'] ?? 0) === $dentistId) {
                return true;
            }
        }

        return false;
    }

    private function isValidPersonName(string $name): bool
    {
        return (bool) preg_match("/^[a-zA-ZÀ-ÿÑñ .'-]+$/u", $name);
    }

    private function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $date;
    }

    private function isValidTime(string $time): bool
    {
        return (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time);
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);

        if ($time === '') {
            return '';
        }

        return substr($time, 0, 5);
    }

    private function cleanText($value, int $maxLength): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        $value = strip_tags($value);

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength);
        }

        return substr($value, 0, $maxLength);
    }

    private function cleanPhone($value): string
    {
        return preg_replace('/[^\d+]/', '', trim((string) $value)) ?? '';
    }

    private function normalizePhilippineMobile(string $phone): string
    {
        $phone = preg_replace('/[\s-]+/', '', trim($phone)) ?? '';

        if (preg_match('/^09\d{9}$/', $phone)) {
            return '+63' . substr($phone, 1);
        }

        if (preg_match('/^639\d{9}$/', $phone)) {
            return '+' . $phone;
        }

        if (preg_match('/^\+639\d{9}$/', $phone)) {
            return $phone;
        }

        return $phone;
    }

    private function isValidPhilippineMobile(string $phone): bool
    {
        $phone = $this->normalizePhilippineMobile($phone);

        return preg_match('/^\+639\d{9}$/', $phone) === 1;
    }

    private function emailDomainLooksValid(string $email): bool
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = substr(strrchr($email, '@') ?: '', 1);

        if ($domain === '') {
            return false;
        }

        if (function_exists('checkdnsrr')) {
            return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
        }

        return true;
    }

    private function guestFormUrl(array $booking): string
    {
        $selectedServiceIds = $this->normalizeServiceIds(
            $booking['service_ids'] ?? [],
            (int) ($booking['service_id'] ?? 0)
        );

        $query = http_build_query([
            'step' => 'schedule',
            'contact_number' => $this->normalizePhilippineMobile((string) ($booking['contact_number'] ?? '')),
            'email' => strtolower(trim((string) ($booking['email'] ?? ''))),
            'service_id' => $selectedServiceIds[0] ?? '',
            'service_ids' => $selectedServiceIds,
            'privacy_consent' => (($booking['privacy_consent'] ?? '0') === '1') ? '1' : '0',
            'wants_patient_account' => (($booking['wants_patient_account'] ?? '0') === '1') ? '1' : '0',
        ]);

        return $this->url('/book/guest?' . $query);
    }

    private function normalizeServiceIds($serviceIds, int $fallbackServiceId = 0): array
    {
        $ids = [];

        if (is_array($serviceIds)) {
            foreach ($serviceIds as $serviceId) {
                $serviceId = (int) $serviceId;

                if ($serviceId > 0) {
                    $ids[$serviceId] = $serviceId;
                }
            }
        } else {
            $serviceId = (int) $serviceIds;

            if ($serviceId > 0) {
                $ids[$serviceId] = $serviceId;
            }
        }

        if (empty($ids) && $fallbackServiceId > 0) {
            $ids[$fallbackServiceId] = $fallbackServiceId;
        }

        return array_values($ids);
    }

    private function isServiceActive($service): bool
    {
        if (is_array($service)) {
            return !isset($service['is_active']) || (int) $service['is_active'] === 1;
        }

        if (is_object($service)) {
            return !isset($service->is_active) || (int) $service->is_active === 1;
        }

        return false;
    }

    private function getServiceName($service): string
    {
        if (is_array($service)) {
            return (string) ($service['service_name'] ?? 'Service');
        }

        if (is_object($service)) {
            return (string) ($service->service_name ?? 'Service');
        }

        return 'Service';
    }

    private function requireOnlineBookingEnabled(): void
    {
        if (!SystemSettingService::enabled('appointment_rules', 'enable_online_booking', true)) {
            Session::set('errors', [
                'booking' => ['Online booking is currently disabled. Please contact the clinic.'],
            ]);

            header('Location: ' . $this->url('/?booking_disabled=1#contact'));
            exit;
        }
    }

    private function requireGuestBookingEnabled(): void
    {
        if (!SystemSettingService::enabled('appointment_rules', 'enable_online_booking', true)) {
            Session::set('errors', [
                'booking' => ['Online booking is currently disabled. Please contact the clinic.'],
            ]);

            header('Location: ' . $this->url('/?booking_disabled=1#contact'));
            exit;
        }

        if (!SystemSettingService::enabled('appointment_rules', 'enable_guest_booking', true)) {
            Session::set('errors', [
                'booking' => ['Guest booking is currently disabled. Please contact the clinic.'],
            ]);

            header('Location: ' . $this->url('/?guest_booking_disabled=1#contact'));
            exit;
        }
    }

    private function url(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }

    private function validPendingContactVerification($pending): bool
    {
        return is_array($pending)
            && !empty($pending['contact_number'])
            && !empty($pending['email'])
            && !empty($pending['service_ids'])
            && (int) ($pending['expires_at'] ?? 0) >= time();
    }

    private function validBookingOtpSession($session): bool
    {
        return is_array($session)
            && !empty($session['otp_id'])
            && !empty($session['contact_number'])
            && !empty($session['email'])
            && !empty($session['service_ids'])
            && (int) ($session['expires_at'] ?? 0) >= time();
    }

    private function bookingContactIsVerified(string $contact, string $email, array $serviceIds): bool
    {
        $verified = Session::get('booking_contact_verified');

        if (!is_array($verified) || (int) ($verified['expires_at'] ?? 0) < time()) {
            return false;
        }

        $verifiedServiceIds = $this->normalizeServiceIds(
            $verified['service_ids'] ?? [],
            (int) ($verified['service_id'] ?? 0)
        );

        $serviceIds = $this->normalizeServiceIds($serviceIds, 0);

        sort($verifiedServiceIds);
        sort($serviceIds);

        return $this->normalizePhilippineMobile((string) $verified['contact_number']) === $this->normalizePhilippineMobile($contact)
            && strtolower(trim((string) $verified['email'])) === strtolower(trim($email))
            && $verifiedServiceIds === $serviceIds;
    }

    private function countRecentBookingOtpSends(string $contact, string $email, string $ipAddress): int
    {
        try {
            $db = Database::getConnection();

            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM booking_contact_otps
                WHERE contact_number = :contact_number
                  AND email = :email
                  AND ip_address = :ip_address
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");

            $stmt->execute([
                ':contact_number' => $contact,
                ':email' => $email,
                ':ip_address' => $ipAddress,
            ]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[BookingController::countRecentBookingOtpSends] ' . $e->getMessage());
            return 0;
        }
    }

    private function sendBookingVerificationOtp(string $method, string $destination, string $otp): bool
{
    $method = strtolower(trim($method));

    if ($method === 'email') {
        return $this->sendBookingOtpEmail($destination, $otp);
    }

    if ($method === 'phone') {
        return $this->sendBookingOtpSms($destination, $otp);
    }

    return false;
}

private function sendBookingOtpSms(string $phone, string $otp): bool
{

    error_log('[Booking OTP SMS] SMS sending method is ready, but no real SMS sender is connected yet.');

    return false;
}

    private function sendBookingOtpEmail(string $email, string $otp): bool
{
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log('[Booking OTP Email] Invalid recipient email.');
        return false;
    }

    $config = $this->otpConfig();
    $emailConfig = $config['email'] ?? [];

    $host = trim((string) ($emailConfig['host'] ?? ''));
    $port = (int) ($emailConfig['port'] ?? 0);
    $username = trim((string) ($emailConfig['username'] ?? ''));
    $password = trim((string) ($emailConfig['password'] ?? ''));
    $fromEmail = trim((string) ($emailConfig['from_email'] ?? $username));
    $fromName = trim((string) ($emailConfig['from_name'] ?? 'Dental Clinic'));

    if (empty($emailConfig['enabled'])) {
        error_log('[Booking OTP Email] Email sending is disabled in otp.php.');
        return false;
    }

    if (
        $host === '' ||
        $port <= 0 ||
        !filter_var($username, FILTER_VALIDATE_EMAIL) ||
        $password === '' ||
        !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
    ) {
        error_log('[Booking OTP Email] Invalid SMTP config. Host=' . $host . ' Port=' . $port . ' Username=' . $username . ' From=' . $fromEmail);
        return false;
    }

    error_log('[Booking OTP Email] SMTP config loaded. Host=' . $host . ' Port=' . $port . ' Username=' . $username . ' OpenSSL=' . (extension_loaded('openssl') ? 'yes' : 'no'));

    $subject = 'Dental Clinic Appointment Verification OTP';

    $body = "Your Dental Clinic appointment verification OTP is: {$otp}\n\n"
        . "This code expires in 10 minutes.\n"
        . "Do not share this OTP with anyone.\n\n"
        . "If you did not request this code, please ignore this message.";

    return $this->sendSmtpEmail(
        $host,
        $port,
        $username,
        $password,
        $fromEmail,
        $fromName,
        $email,
        $subject,
        $body
    );
}

private function sendSmtpEmail(
    string $host,
    int $port,
    string $username,
    string $password,
    string $fromEmail,
    string $fromName,
    string $toEmail,
    string $subject,
    string $body
): bool {
    $socket = null;

    try {
        $socket = stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            error_log("[SMTP] Connection failed: {$errno} {$errstr}");
            return false;
        }

        stream_set_timeout($socket, 30);

        if (!$this->smtpExpect($socket, [220])) {
            fclose($socket);
            return false;
        }

        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';

        $this->smtpCommand($socket, "EHLO {$serverName}");
        if (!$this->smtpExpect($socket, [250])) {
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, 'STARTTLS');
        if (!$this->smtpExpect($socket, [220])) {
            fclose($socket);
            return false;
        }

        $cryptoMethods = 0;

        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $cryptoMethods |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cryptoMethods |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }

        if ($cryptoMethods === 0) {
            $cryptoMethods = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        }

        if (!stream_socket_enable_crypto($socket, true, $cryptoMethods)) {
            error_log('[SMTP] Failed to enable TLS encryption. Check extension=openssl and restart Apache.');
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, "EHLO {$serverName}");
        if (!$this->smtpExpect($socket, [250])) {
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, 'AUTH LOGIN');
        if (!$this->smtpExpect($socket, [334])) {
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, base64_encode($username));
        if (!$this->smtpExpect($socket, [334])) {
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, base64_encode($password));
        if (!$this->smtpExpect($socket, [235])) {
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>');
        if (!$this->smtpExpect($socket, [250])) {
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, 'RCPT TO:<' . $toEmail . '>');
        if (!$this->smtpExpect($socket, [250, 251])) {
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, 'DATA');
        if (!$this->smtpExpect($socket, [354])) {
            fclose($socket);
            return false;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'From: ' . $this->encodeMailName($fromName) . ' <' . $fromEmail . '>',
            'To: <' . $toEmail . '>',
            'Subject: ' . $this->encodeMailSubject($subject),
            'Date: ' . date('r'),
        ];

        $message = implode("\r\n", $headers)
            . "\r\n\r\n"
            . $this->smtpDotStuff($body)
            . "\r\n.";

        $this->smtpCommand($socket, $message);

        if (!$this->smtpExpect($socket, [250])) {
            fclose($socket);
            return false;
        }

        $this->smtpCommand($socket, 'QUIT');
        $this->smtpExpect($socket, [221]);

        fclose($socket);

        return true;
    } catch (Throwable $e) {
        error_log('[SMTP] ' . $e->getMessage());

        if (is_resource($socket)) {
            fclose($socket);
        }

        return false;
    }
}

private function smtpCommand($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

private function smtpExpect($socket, array $expectedCodes): bool
{
    $response = $this->smtpReadResponse($socket);

    if ($response === '') {
        error_log('[SMTP] Empty response from server.');
        return false;
    }

    $code = (int) substr($response, 0, 3);

    if (!in_array($code, $expectedCodes, true)) {
        error_log('[SMTP] Unexpected response: ' . trim($response));
        return false;
    }

    return true;
}

private function smtpReadResponse($socket): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

private function smtpDotStuff(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);

    foreach ($lines as &$line) {
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
    }

    return implode("\r\n", $lines);
}

private function encodeMailSubject(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

private function encodeMailName(string $name): string
{
    return '=?UTF-8?B?' . base64_encode($name) . '?=';
}

    private function otpConfig(): array
{
    $paths = [
        dirname(__DIR__, 2) . '/config/otp.php',
        dirname(__DIR__) . '/Config/otp.php',
        dirname(__DIR__, 2) . '/app/Config/otp.php',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            error_log('[OTP Config] Loaded config file at: ' . $path);

            $config = require $path;

            return is_array($config) ? $config : [];
        }

        error_log('[OTP Config] Missing config file at: ' . $path);
    }

    return [];
}
    private function logBookingOtpDelivery(string $method, string $destination, bool $sent): void
    {
        try {
            $db = Database::getConnection();

            $stmt = $db->prepare("
                INSERT INTO message_logs (
                    patient_id,
                    appointment_id,
                    sent_by,
                    channel,
                    recipient,
                    subject,
                    message_body,
                    status,
                    provider_response,
                    error_message,
                    sent_at,
                    created_at
                ) VALUES (
                    NULL,
                    NULL,
                    NULL,
                    :channel,
                    :recipient,
                    :subject,
                    :message_body,
                    :status,
                    NULL,
                    :error_message,
                    CASE WHEN :status_for_sent = 'sent' THEN NOW() ELSE NULL END,
                    NOW()
                )
            ");

            $status = $sent ? 'sent' : 'skipped';

            $stmt->execute([
                ':channel' => $method === 'email' ? 'email' : 'sms',
                ':recipient' => $destination,
                ':subject' => 'Appointment Verification OTP',
                ':message_body' => 'Appointment verification OTP delivery processed. OTP value is not stored in logs.',
                ':status' => $status,
                ':error_message' => $sent ? null : 'Delivery provider is not configured or sending failed.',
                ':status_for_sent' => $status,
            ]);
        } catch (Throwable $e) {
            error_log('[BookingController::logBookingOtpDelivery] ' . $e->getMessage());
        }
    }

    private function maskEmail(string $email): string
    {
        $email = trim($email);

        if (strpos($email, '@') === false) {
            return '********';
        }

        [$local, $domain] = explode('@', $email, 2);
        $length = strlen($local);

        if ($length <= 2) {
            return substr($local, 0, 1) . '***@' . $domain;
        }

        return substr($local, 0, 1)
            . str_repeat('*', max(3, $length - 2))
            . substr($local, -1)
            . '@'
            . $domain;
    }

    private function maskPhone(string $phone): string
    {
        $phone = trim($phone);
        $length = strlen($phone);

        if ($length <= 5) {
            return str_repeat('*', $length);
        }

        return substr($phone, 0, 4)
            . str_repeat('*', max(4, $length - 7))
            . substr($phone, -3);
    }

    private function ipAddress(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    private function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}