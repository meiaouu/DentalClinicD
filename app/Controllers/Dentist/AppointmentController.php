<?php

namespace App\Controllers\Dentist;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AppointmentRepository;
use App\Repositories\DentistLookupRepository;
use App\Repositories\NotificationRepository;
use DateTime;
use PDO;
use RuntimeException;
use Throwable;

class AppointmentController
{
    private const ALLOWED_STATUSES = [
        '',
        'pending',
        'confirmed',
        'checked_in',
        'in_progress',
        'completed',
        'no_show',
        'cancelled',
        'rejected',
        'rescheduled',
    ];

    private AppointmentRepository $appointments;
    private DentistLookupRepository $dentists;
    private NotificationRepository $notifications;
    private PDO $db;

    public function __construct()
    {
        $this->appointments = new AppointmentRepository();
        $this->dentists = new DentistLookupRepository();
        $this->notifications = new NotificationRepository();
        $this->db = Database::getConnection();
    }

    public function index(): void
    {
        Auth::requireRole('dentist');

        try {
            $dentist = $this->resolveCurrentDentist();
            $dentistId = (int) $dentist['dentist_id'];

            $date = $this->sanitizeDateFilter((string) ($_GET['date'] ?? ''));
            $status = $this->sanitizeStatusFilter((string) ($_GET['status'] ?? ''));
            $search = trim((string) ($_GET['search'] ?? ''));

            $appointments = $this->getAppointmentsForDentist(
                $dentistId,
                $date,
                $status !== '' ? $status : null,
                $search
            );

            $allAppointmentsForStats = $this->getAppointmentsForDentist(
                $dentistId,
                null,
                null,
                ''
            );

            $groups = $this->groupAppointments($appointments);
            $stats = $this->buildStats($dentistId, $allAppointmentsForStats);

            View::render('dentist.appointments.index', [
                'dentist' => $dentist,
                'appointments' => $appointments,

                'todayAppointments' => $groups['today'],
                'upcomingAppointments' => $groups['upcoming'],
                'pastAppointments' => $groups['past'],
                'completedAppointments' => $groups['completed'],

                'stats' => $stats,
                'date' => $date ?? '',
                'status' => $status,
                'search' => $search,
                'allowedStatuses' => self::ALLOWED_STATUSES,

                'flash_success' => Session::get('flash_success'),
                'flash_error' => Session::get('flash_error'),
            ]);

            Session::remove('flash_success');
            Session::remove('flash_error');
        } catch (RuntimeException $e) {
            http_response_code(403);
            exit($e->getMessage());
        }
    }

    public function show(): void
    {
        Auth::requireRole('dentist');

        try {
            $dentist = $this->resolveCurrentDentist();
            $dentistId = (int) $dentist['dentist_id'];

            $appointmentId = (int) ($_GET['id'] ?? ($_GET['appointment_id'] ?? 0));

            if ($appointmentId <= 0) {
                http_response_code(404);
                exit('Appointment not found.');
            }

            $appointment = $this->findAppointmentForDentist($appointmentId, $dentistId);

            if (!$appointment) {
                http_response_code(404);
                exit('Appointment not found.');
            }

            View::render('dentist.appointments.show', [
                'dentist' => $dentist,
                'appointment' => $appointment,
                'flash_success' => Session::get('flash_success'),
                'flash_error' => Session::get('flash_error'),
            ]);

            Session::remove('flash_success');
            Session::remove('flash_error');
        } catch (RuntimeException $e) {
            http_response_code(403);
            exit($e->getMessage());
        }
    }

   public function weeklySchedule(): void
{
    Auth::requireRole('dentist');

    header('Content-Type: application/json');

    try {
        $dentist = $this->resolveCurrentDentist();
        $dentistId = (int) ($dentist['dentist_id'] ?? 0);

        if ($dentistId <= 0) {
            throw new RuntimeException('Dentist profile not found.');
        }

        $date = trim((string) ($_GET['date'] ?? date('Y-m-d')));

        if ($date === '' || strtotime($date) === false) {
            $date = date('Y-m-d');
        }

        $target = new DateTime($date);
        $dayNumber = (int) $target->format('N');

        $weekStart = clone $target;
        $weekStart->modify('-' . ($dayNumber - 1) . ' days');

        $weekEnd = clone $weekStart;
        $weekEnd->modify('+6 days');

        $weekStartDate = $weekStart->format('Y-m-d');
        $weekEndDate = $weekEnd->format('Y-m-d');

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = clone $weekStart;
            $day->modify('+' . $i . ' days');

            $days[] = [
                'date' => $day->format('Y-m-d'),
                'day_name' => $day->format('D'),
                'day_label' => $day->format('M j'),
            ];
        }

        /*
            Unified schedule logic:

            1. If actual_started_at exists:
               - appointment_date becomes DATE(actual_started_at)
               - start_time becomes TIME(actual_started_at)

            2. If actual_completed_at exists:
               - end_time becomes TIME(actual_completed_at)

            3. If treatment started but not completed:
               - end_time is estimated only for calendar placement
               - frontend displays "Ongoing"

            4. If treatment has not started:
               - appointment_date/start_time/end_time remain the booked schedule

            This does NOT overwrite the database booked schedule.
            It only changes the JSON shown in dentist availability.
        */
        $stmt = $this->db->prepare("
            SELECT
                a.appointment_id,
                a.appointment_code,
                a.patient_id,
                a.dentist_id,
                a.status,
                a.service_id,
                a.estimated_duration_minutes,

                a.appointment_date AS booked_appointment_date,
                a.start_time AS booked_start_time,
                a.end_time AS booked_end_time,

                a.actual_started_at,
                a.actual_completed_at,
                a.completed_at,

                CASE
                    WHEN a.actual_started_at IS NOT NULL
                        THEN DATE(a.actual_started_at)
                    ELSE a.appointment_date
                END AS appointment_date,

                CASE
                    WHEN a.actual_started_at IS NOT NULL
                        THEN TIME(a.actual_started_at)
                    ELSE a.start_time
                END AS start_time,

                CASE
                    WHEN a.actual_completed_at IS NOT NULL
                        THEN TIME(a.actual_completed_at)
                    WHEN a.actual_started_at IS NOT NULL
                        THEN TIME(
                            DATE_ADD(
                                a.actual_started_at,
                                INTERVAL COALESCE(NULLIF(a.estimated_duration_minutes, 0), 30) MINUTE
                            )
                        )
                    ELSE a.end_time
                END AS end_time,

                CASE
                    WHEN a.actual_started_at IS NOT NULL
                         AND a.actual_completed_at IS NULL
                        THEN 1
                    ELSE 0
                END AS is_ongoing,

                s.service_name,

                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name)), ''),
                    NULLIF(TRIM(CONCAT_WS(' ', ar.guest_first_name, ar.guest_middle_name, ar.guest_last_name)), ''),
                    'Patient'
                ) AS patient_name

            FROM appointments a
            LEFT JOIN patients p ON p.patient_id = a.patient_id
            LEFT JOIN appointment_requests ar ON ar.request_id = a.request_id
            LEFT JOIN services s ON s.service_id = a.service_id

            WHERE a.dentist_id = :dentist_id
              AND a.status IN (
                    'confirmed',
                    'rescheduled',
                    'checked_in',
                    'in_progress',
                    'completed'
              )
              AND (
                    (
                        a.actual_started_at IS NOT NULL
                        AND DATE(a.actual_started_at) BETWEEN :actual_week_start AND :actual_week_end
                    )
                    OR
                    (
                        a.actual_started_at IS NULL
                        AND a.appointment_date BETWEEN :scheduled_week_start AND :scheduled_week_end
                    )
              )

            ORDER BY
                appointment_date ASC,
                start_time ASC,
                a.appointment_id ASC
        ");

        $stmt->execute([
            ':dentist_id' => $dentistId,
            ':actual_week_start' => $weekStartDate,
            ':actual_week_end' => $weekEndDate,
            ':scheduled_week_start' => $weekStartDate,
            ':scheduled_week_end' => $weekEndDate,
        ]);

        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'success' => true,
            'week_start' => $weekStartDate,
            'week_end' => $weekEndDate,
            'days' => $days,
            'appointments' => $appointments,
        ]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
        ]);
        exit;
    }
}

public function startTreatment(): void
{
    Auth::requireRole('dentist');

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $user = Auth::user();
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

    try {
        $dentist = $this->resolveCurrentDentist();

        if ($appointmentId <= 0) {
            throw new RuntimeException('Invalid appointment.');
        }

        $appointment = $this->appointments->findByIdForUpdate($appointmentId);

        if (!$appointment) {
            throw new RuntimeException('Appointment not found.');
        }

        if ((int) ($appointment['dentist_id'] ?? 0) !== (int) ($dentist['dentist_id'] ?? 0)) {
            throw new RuntimeException('You are not allowed to start this appointment.');
        }

        $oldStatus = strtolower(trim((string) ($appointment['status'] ?? '')));

        if (in_array($oldStatus, ['cancelled', 'rejected', 'no_show', 'completed'], true)) {
            throw new RuntimeException('This appointment cannot be started.');
        }

        if (!in_array($oldStatus, ['confirmed', 'rescheduled', 'checked_in', 'in_progress'], true)) {
            throw new RuntimeException('Only confirmed, rescheduled, checked-in, or in-progress appointments can be started.');
        }

        if (empty($appointment['actual_started_at'])) {
            $now = date('Y-m-d H:i:s');

            $this->appointments->updateStatus(
                $appointmentId,
                'in_progress',
                'Treatment record started by dentist.',
                [
                    'actual_started_at' => $now,
                ]
            );

            if ($oldStatus !== 'in_progress') {
                $this->appointments->insertStatusLog(
                    $appointmentId,
                    $oldStatus,
                    'in_progress',
                    (int) ($user['user_id'] ?? 0),
                    'Treatment record started by dentist.',
                    $now
                );
            }
        }

        $freshAppointment = $this->appointments->findDetailedById($appointmentId);

        $patientId = (int) ($freshAppointment['patient_id'] ?? $appointment['patient_id'] ?? 0);

        Session::set('flash_success', 'Treatment started. Actual start time has been recorded.');

        if ($patientId > 0) {
            header(
                'Location: ' .
                $this->url('/dentist/patients/show?id=' . $patientId .
                    '&tab=treatment-records' .
                    '&appointment_id=' . $appointmentId .
                    '&from_start=1'
                )
            );
            exit;
        }
    } catch (\Throwable $e) {
        Session::set('flash_error', $e->getMessage());
    }

    header('Location: ' . $this->url('/dentist/appointments'));
    exit;
}

    public function completeTreatment(): void
{
    Auth::requireRole('dentist');

    if (!Csrf::verify($_POST['_csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
    $redirectTo = $this->sanitizeRedirect((string) ($_POST['redirect_to'] ?? ''));
    $user = Auth::user();
    $actorUserId = (int) ($user['user_id'] ?? 0);

    try {
        if ($appointmentId <= 0) {
            throw new RuntimeException('Invalid appointment.');
        }

        $dentist = $this->resolveCurrentDentist();
        $dentistId = (int) $dentist['dentist_id'];

        $appointment = $this->findAppointmentForDentist($appointmentId, $dentistId);

        if (!$appointment) {
            throw new RuntimeException('Appointment not found.');
        }

        $status = strtolower(trim((string) ($appointment['status'] ?? '')));

        if ($status !== 'in_progress') {
            throw new RuntimeException('Only in-progress appointments can be completed.');
        }

        $this->markAppointmentCompleted($appointmentId, $actorUserId);

        $freshAppointment = $this->findAppointmentForDentist($appointmentId, $dentistId) ?: $appointment;

        $this->notifyStaffTreatmentCompleted($freshAppointment, $actorUserId);

        Session::set('flash_success', 'Treatment completed successfully.');

        if ($redirectTo !== '') {
            header('Location: ' . $redirectTo);
            exit;
        }

        header('Location: ' . $this->url('/dentist/appointments?date=' . urlencode((string) ($appointment['appointment_date'] ?? date('Y-m-d')))));
        exit;
    } catch (Throwable $e) {
        Session::set('flash_error', $e->getMessage());

        if ($redirectTo !== '') {
            header('Location: ' . $redirectTo);
            exit;
        }

        header('Location: ' . $this->url('/dentist/appointments?date=' . urlencode(date('Y-m-d'))));
        exit;
    }
}

    private function markAppointmentInProgress(int $appointmentId, int $actorUserId): void
    {
        if ($appointmentId <= 0) {
            throw new RuntimeException('Invalid appointment.');
        }

        $oldStatus = 'checked_in';
        $newStatus = 'in_progress';

        $sets = [
            'status = :new_status',
        ];

        if ($this->appointmentsTableHasColumn('arrival_status')) {
            $sets[] = "arrival_status = 'checked_in'";
        }

        if ($this->appointmentsTableHasColumn('checked_in_at')) {
            $sets[] = 'checked_in_at = COALESCE(checked_in_at, NOW())';
        }

        if ($this->appointmentsTableHasColumn('started_at')) {
            $sets[] = 'started_at = NOW()';
        }

        if ($this->appointmentsTableHasColumn('updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        try {
            $this->db->beginTransaction();

            $sql = "
                UPDATE appointments
                SET " . implode(",\n                    ", $sets) . "
                WHERE appointment_id = :appointment_id
                  AND status = :old_status
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':appointment_id' => $appointmentId,
                ':old_status' => $oldStatus,
                ':new_status' => $newStatus,
            ]);

            if ($stmt->rowCount() <= 0) {
                throw new RuntimeException('Unable to start treatment. The appointment may have already changed status.');
            }

            $this->insertStatusLogSafely(
                $appointmentId,
                $oldStatus,
                $newStatus,
                $actorUserId,
                'Treatment started by dentist.'
            );

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function markAppointmentCompleted(int $appointmentId, int $actorUserId): void
    {
        if ($appointmentId <= 0) {
            throw new RuntimeException('Invalid appointment.');
        }

        $oldStatus = 'in_progress';
        $newStatus = 'completed';

        $sets = [
            'status = :new_status',
        ];

        if ($this->appointmentsTableHasColumn('completed_at')) {
            $sets[] = 'completed_at = NOW()';
        }

        if ($this->appointmentsTableHasColumn('updated_at')) {
            $sets[] = 'updated_at = NOW()';
        }

        try {
            $this->db->beginTransaction();

            $sql = "
                UPDATE appointments
                SET " . implode(",\n                    ", $sets) . "
                WHERE appointment_id = :appointment_id
                  AND status = :old_status
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':appointment_id' => $appointmentId,
                ':old_status' => $oldStatus,
                ':new_status' => $newStatus,
            ]);

            if ($stmt->rowCount() <= 0) {
                throw new RuntimeException('Unable to complete treatment. The appointment may have already changed status.');
            }

            $this->insertStatusLogSafely(
                $appointmentId,
                $oldStatus,
                $newStatus,
                $actorUserId,
                'Treatment completed by dentist.'
            );

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function insertStatusLogSafely(
        int $appointmentId,
        string $oldStatus,
        string $newStatus,
        int $actorUserId,
        string $remarks
    ): void {
        try {
            if (!$this->tableExists('appointment_status_logs')) {
                return;
            }

            $stmt = $this->db->prepare("
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
                    :new_status,
                    :changed_by,
                    :remarks,
                    NOW()
                )
            ");

            $stmt->execute([
                ':appointment_id' => $appointmentId,
                ':old_status' => $oldStatus,
                ':new_status' => $newStatus,
                ':changed_by' => $actorUserId > 0 ? $actorUserId : null,
                ':remarks' => $remarks,
            ]);
        } catch (Throwable $e) {
            /*
                Do not stop dentist workflow if logging fails.
            */
        }
    }

    private function notifyStaffTreatmentStarted(array $appointment, int $actorUserId): void
    {
        $patientName = $this->appointmentPatientName($appointment);
        $dentistName = $this->appointmentDentistName($appointment);
        $appointmentDate = (string) ($appointment['appointment_date'] ?? date('Y-m-d'));
        $appointmentDateLabel = $this->appointmentDateLabel($appointment);
        $appointmentTime = $this->appointmentTimeLabel($appointment);

        $this->notifyStaffSafely(
            'treatment_started',
            'Treatment started',
            $dentistName . ' started treatment for ' . $patientName . ' on ' . $appointmentDateLabel . ' at ' . $appointmentTime . '.',
            $this->url('/staff/appointments?date=' . urlencode($appointmentDate)),
            'appointment',
            (int) ($appointment['appointment_id'] ?? 0),
            $actorUserId
        );
    }

   private function notifyStaffTreatmentCompleted(array $appointment, int $actorUserId): void
{
    $patientName = $this->appointmentPatientName($appointment);
    $dentistName = $this->appointmentDentistName($appointment);
    $appointmentDate = (string) ($appointment['appointment_date'] ?? date('Y-m-d'));
    $appointmentDateLabel = $this->appointmentDateLabel($appointment);
    $appointmentTime = $this->appointmentTimeLabel($appointment);

    $this->notifyStaffSafely(
        'treatment_completed',
        'Treatment completed',
        $dentistName . ' completed treatment for ' . $patientName . ' on ' . $appointmentDateLabel . ' at ' . $appointmentTime . '. Please review billing or payment if needed.',
        $this->url('/staff/appointments?date=' . urlencode($appointmentDate)),
        'appointment',
        (int) ($appointment['appointment_id'] ?? 0),
        $actorUserId
    );
}

    private function notifyStaffSafely(
        string $type,
        string $title,
        string $message,
        string $linkUrl,
        ?string $relatedTable,
        ?int $relatedId,
        ?int $actorUserId
    ): void {
        try {
            if (method_exists($this->notifications, 'notifyStaff')) {
                $this->notifications->notifyStaff(
                    $type,
                    $title,
                    $message,
                    $linkUrl,
                    $relatedTable,
                    $relatedId,
                    $actorUserId
                );

                return;
            }
        } catch (Throwable $e) {
            /*
                Fall back to direct insert below.
            */
        }

        try {
            if (!$this->tableExists('notifications')) {
                return;
            }

            $staffUserIds = $this->getStaffUserIds();

            foreach ($staffUserIds as $staffUserId) {
                $this->insertNotificationForUser(
                    $staffUserId,
                    $type,
                    $title,
                    $message,
                    $linkUrl,
                    $relatedTable,
                    $relatedId,
                    $actorUserId
                );
            }
        } catch (Throwable $e) {
            /*
                Do not crash appointment update if notification creation fails.
            */
        }
    }

    private function getStaffUserIds(): array
    {
        try {
            if (!$this->tableExists('users') || !$this->tableExists('roles')) {
                return [];
            }

            $userColumns = $this->getTableColumns('users');
            $roleColumns = $this->getTableColumns('roles');

            $roleNameColumn = null;

            foreach (['role_name', 'name', 'slug'] as $candidate) {
                if (isset($roleColumns[$candidate])) {
                    $roleNameColumn = $candidate;
                    break;
                }
            }

            if ($roleNameColumn === null) {
                return [];
            }

            $activeSql = isset($userColumns['is_active'])
                ? 'AND u.is_active = 1'
                : '';

            $stmt = $this->db->prepare("
                SELECT u.user_id
                FROM users u
                INNER JOIN roles r ON r.role_id = u.role_id
                WHERE LOWER(r.`$roleNameColumn`) IN ('staff', 'admin')
                $activeSql
            ");

            $stmt->execute();

            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable $e) {
            return [];
        }
    }

    private function insertNotificationForUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        string $linkUrl,
        ?string $relatedTable,
        ?int $relatedId,
        ?int $actorUserId
    ): void {
        if ($userId <= 0) {
            return;
        }

        $columns = $this->getTableColumns('notifications');

        if (empty($columns)) {
            return;
        }

        $data = [];

        if (isset($columns['user_id'])) {
            $data['user_id'] = $userId;
        }

        if (isset($columns['recipient_user_id'])) {
            $data['recipient_user_id'] = $userId;
        }

        if (!isset($data['user_id']) && !isset($data['recipient_user_id'])) {
            return;
        }

        if (isset($columns['recipient_role'])) {
            $data['recipient_role'] = null;
        }

        if (isset($columns['type'])) {
            $data['type'] = $type;
        }

        if (isset($columns['notification_type'])) {
            $data['notification_type'] = $type;
        }

        if (isset($columns['title'])) {
            $data['title'] = $title;
        }

        if (isset($columns['message'])) {
            $data['message'] = $message;
        }

        if (isset($columns['link_url'])) {
            $data['link_url'] = $linkUrl;
        }

        if (isset($columns['target_url'])) {
            $data['target_url'] = $linkUrl;
        }

        if (isset($columns['related_table'])) {
            $data['related_table'] = $relatedTable;
        }

        if (isset($columns['related_type'])) {
            $data['related_type'] = $relatedTable;
        }

        if (isset($columns['related_id'])) {
            $data['related_id'] = $relatedId;
        }

        if (isset($columns['appointment_id'])) {
            $data['appointment_id'] = $relatedId;
        }

        if (isset($columns['actor_user_id'])) {
            $data['actor_user_id'] = $actorUserId;
        }

        if (isset($columns['created_by'])) {
            $data['created_by'] = $actorUserId;
        }

        if (isset($columns['is_read'])) {
            $data['is_read'] = 0;
        }

        if (isset($columns['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        if (isset($columns['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        if (empty($data)) {
            return;
        }

        $fieldNames = array_keys($data);
        $placeholders = array_map(
            static fn (string $field): string => ':' . $field,
            $fieldNames
        );

        $sql = "
            INSERT INTO notifications (
                " . implode(', ', $fieldNames) . "
            ) VALUES (
                " . implode(', ', $placeholders) . "
            )
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($data as $field => $value) {
            $stmt->bindValue(':' . $field, $value);
        }

        $stmt->execute();
    }

    private function resolveCurrentDentist(): array
    {
        $user = Auth::user();
        $userId = (int) ($user['user_id'] ?? 0);

        if ($userId <= 0) {
            throw new RuntimeException('Invalid authenticated dentist account.');
        }

        $dentist = null;

        if (method_exists($this->dentists, 'findByUserId')) {
            $dentist = $this->dentists->findByUserId($userId);
        }

        if (!$dentist) {
            $stmt = $this->db->prepare("
                SELECT
                    d.*,
                    u.first_name,
                    u.middle_name,
                    u.last_name,
                    u.email,
                    u.contact_number
                FROM dentists d
                INNER JOIN users u ON u.user_id = d.user_id
                WHERE d.user_id = :user_id
                LIMIT 1
            ");

            $stmt->execute([
                ':user_id' => $userId,
            ]);

            $dentist = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$dentist || empty($dentist['dentist_id'])) {
            throw new RuntimeException('Dentist profile not found.');
        }

        if (isset($dentist['is_active']) && (int) $dentist['is_active'] !== 1) {
            throw new RuntimeException('Dentist profile is inactive.');
        }

        return $dentist;
    }

    private function getAppointmentsForDentist(
        int $dentistId,
        ?string $date = null,
        ?string $status = null,
        string $search = ''
    ): array {
        if ($dentistId <= 0) {
            return [];
        }

        if (
            $search === '' &&
            method_exists($this->appointments, 'getDentistAppointments')
        ) {
            $rows = $this->appointments->getDentistAppointments(
                $dentistId,
                $date,
                $status
            );

            $rows = is_array($rows) ? $rows : [];
            $rows = $this->attachPatientVisitFlags($rows);

            return $this->sortAppointments($rows);
        }

        $rows = $this->queryDentistAppointments($dentistId, $date, $status, $search);
        $rows = $this->attachPatientVisitFlags($rows);

        return $this->sortAppointments($rows);
    }

    private function findAppointmentForDentist(int $appointmentId, int $dentistId): ?array
    {
        if ($appointmentId <= 0 || $dentistId <= 0) {
            return null;
        }

        if (method_exists($this->appointments, 'findDentistAppointmentById')) {
            $appointment = $this->appointments->findDentistAppointmentById(
                $appointmentId,
                $dentistId
            );

            if ($appointment) {
                $rows = $this->attachPatientVisitFlags([$appointment]);
                return $rows[0] ?? $appointment;
            }
        }

        $rows = $this->queryDentistAppointments(
            $dentistId,
            null,
            null,
            '',
            $appointmentId
        );

        $rows = $this->attachPatientVisitFlags($rows);

        return $rows[0] ?? null;
    }

    private function queryDentistAppointments(
        int $dentistId,
        ?string $date = null,
        ?string $status = null,
        string $search = '',
        ?int $appointmentId = null
    ): array {
        if ($dentistId <= 0) {
            return [];
        }

        $sql = "
            SELECT
                a.*,

                s.service_name,
                s.estimated_duration_minutes AS service_duration_minutes,
                s.estimated_price AS service_estimated_price,

                p.patient_code,
                COALESCE(NULLIF(p.first_name, ''), ar.guest_first_name) AS patient_first_name,
                COALESCE(NULLIF(p.middle_name, ''), ar.guest_middle_name) AS patient_middle_name,
                COALESCE(NULLIF(p.last_name, ''), ar.guest_last_name) AS patient_last_name,
                COALESCE(NULLIF(p.contact_number, ''), ar.guest_contact_number) AS patient_contact_number,
                COALESCE(NULLIF(p.email, ''), ar.guest_email) AS patient_email,

                d.dentist_code,
                d.license_number,
                d.specialization,

                du.first_name AS dentist_first_name,
                du.middle_name AS dentist_middle_name,
                du.last_name AS dentist_last_name,
                du.email AS dentist_email,

                ar.request_code,
                ar.is_guest,
                ar.source_channel
            FROM appointments a
            INNER JOIN dentists d ON d.dentist_id = a.dentist_id
            LEFT JOIN users du ON du.user_id = d.user_id
            LEFT JOIN patients p ON p.patient_id = a.patient_id
            LEFT JOIN services s ON s.service_id = a.service_id
            LEFT JOIN appointment_requests ar ON ar.request_id = a.request_id
            WHERE a.dentist_id = :dentist_id
        ";

        $params = [
            ':dentist_id' => $dentistId,
        ];

        if ($appointmentId !== null && $appointmentId > 0) {
            $sql .= " AND a.appointment_id = :appointment_id";
            $params[':appointment_id'] = $appointmentId;
        }

        if ($date !== null && $date !== '') {
            $sql .= " AND a.appointment_date = :appointment_date";
            $params[':appointment_date'] = $date;
        }

        if ($status !== null && $status !== '') {
            $sql .= " AND a.status = :status";
            $params[':status'] = $status;
        }

        if ($search !== '') {
            $sql .= "
                AND (
                    a.appointment_code LIKE :search
                    OR ar.request_code LIKE :search
                    OR p.patient_code LIKE :search
                    OR p.first_name LIKE :search
                    OR p.middle_name LIKE :search
                    OR p.last_name LIKE :search
                    OR ar.guest_first_name LIKE :search
                    OR ar.guest_middle_name LIKE :search
                    OR ar.guest_last_name LIKE :search
                    OR p.contact_number LIKE :search
                    OR ar.guest_contact_number LIKE :search
                    OR p.email LIKE :search
                    OR ar.guest_email LIKE :search
                    OR s.service_name LIKE :search
                )
            ";

            $params[':search'] = '%' . $search . '%';
        }

        $sql .= "
            ORDER BY
                a.appointment_date ASC,
                a.start_time ASC,
                a.appointment_id ASC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            if ($key === ':appointment_id' || $key === ':dentist_id') {
                $stmt->bindValue($key, (int) $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue($key, (string) $value);
        }

        $stmt->bindValue(':limit', $appointmentId !== null ? 1 : 500, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function attachPatientVisitFlags(array $appointments): array
    {
        if (empty($appointments)) {
            return [];
        }

        $patientIds = [];

        foreach ($appointments as $appointment) {
            $patientId = (int) ($appointment['patient_id'] ?? 0);

            if ($patientId > 0) {
                $patientIds[$patientId] = $patientId;
            }
        }

        if (empty($patientIds)) {
            foreach ($appointments as &$appointment) {
                $appointment['prior_appointment_count'] = 0;
                $appointment['patient_total_appointments'] = 0;
                $appointment['has_past_appointment'] = 0;
                $appointment['patient_visit_type'] = 'New';
            }

            unset($appointment);

            return $appointments;
        }

        $historyByPatient = $this->loadPatientAppointmentHistory(array_values($patientIds));

        foreach ($appointments as &$appointment) {
            $patientId = (int) ($appointment['patient_id'] ?? 0);

            if ($patientId <= 0) {
                $appointment['prior_appointment_count'] = 0;
                $appointment['patient_total_appointments'] = 0;
                $appointment['has_past_appointment'] = 0;
                $appointment['patient_visit_type'] = 'New';
                continue;
            }

            $patientHistory = $historyByPatient[$patientId] ?? [];
            $priorCount = 0;

            foreach ($patientHistory as $historyItem) {
                if ($this->isAppointmentBefore($historyItem, $appointment)) {
                    $priorCount++;
                }
            }

            $appointment['prior_appointment_count'] = $priorCount;
            $appointment['patient_total_appointments'] = count($patientHistory);
            $appointment['has_past_appointment'] = $priorCount > 0 ? 1 : 0;
            $appointment['patient_visit_type'] = $priorCount > 0 ? 'Returning' : 'New';
        }

        unset($appointment);

        return $appointments;
    }

    private function loadPatientAppointmentHistory(array $patientIds): array
    {
        $patientIds = array_values(array_unique(array_filter(array_map('intval', $patientIds))));

        if (empty($patientIds)) {
            return [];
        }

        $placeholders = [];

        foreach ($patientIds as $index => $patientId) {
            $placeholders[] = ':patient_id_' . $index;
        }

        $sql = "
            SELECT
                appointment_id,
                patient_id,
                appointment_date,
                start_time,
                status
            FROM appointments
            WHERE patient_id IN (" . implode(', ', $placeholders) . ")
            ORDER BY
                patient_id ASC,
                appointment_date ASC,
                start_time ASC,
                appointment_id ASC
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($patientIds as $index => $patientId) {
            $stmt->bindValue(':patient_id_' . $index, $patientId, PDO::PARAM_INT);
        }

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $grouped = [];

        foreach ($rows as $row) {
            $patientId = (int) ($row['patient_id'] ?? 0);

            if ($patientId > 0) {
                $grouped[$patientId][] = $row;
            }
        }

        return $grouped;
    }

    private function isAppointmentBefore(array $historyItem, array $currentAppointment): bool
    {
        $historyAppointmentId = (int) ($historyItem['appointment_id'] ?? 0);
        $currentAppointmentId = (int) ($currentAppointment['appointment_id'] ?? 0);

        if (
            $historyAppointmentId > 0 &&
            $currentAppointmentId > 0 &&
            $historyAppointmentId === $currentAppointmentId
        ) {
            return false;
        }

        $historyDate = (string) ($historyItem['appointment_date'] ?? '');
        $currentDate = (string) ($currentAppointment['appointment_date'] ?? '');

        if ($historyDate === '' || $currentDate === '') {
            return false;
        }

        if ($historyDate < $currentDate) {
            return true;
        }

        if ($historyDate > $currentDate) {
            return false;
        }

        $historyTime = (string) ($historyItem['start_time'] ?? '');
        $currentTime = (string) ($currentAppointment['start_time'] ?? '');

        if ($historyTime !== '' && $currentTime !== '') {
            if ($historyTime < $currentTime) {
                return true;
            }

            if ($historyTime > $currentTime) {
                return false;
            }
        }

        return $historyAppointmentId > 0 &&
            $currentAppointmentId > 0 &&
            $historyAppointmentId < $currentAppointmentId;
    }

    private function groupAppointments(array $appointments): array
    {
        $today = date('Y-m-d');

        $groups = [
            'today' => [],
            'upcoming' => [],
            'past' => [],
            'completed' => [],
        ];

        foreach ($appointments as $appointment) {
            $appointmentDate = (string) ($appointment['appointment_date'] ?? '');
            $status = (string) ($appointment['status'] ?? '');

            if ($status === 'completed') {
                $groups['completed'][] = $appointment;
            }

            if ($appointmentDate === $today) {
                $groups['today'][] = $appointment;
                continue;
            }

            if ($appointmentDate > $today) {
                $groups['upcoming'][] = $appointment;
                continue;
            }

            if ($appointmentDate !== '' && $appointmentDate < $today) {
                $groups['past'][] = $appointment;
            }
        }

        foreach ($groups as $key => $rows) {
            $groups[$key] = $this->sortAppointments($rows);
        }

        return $groups;
    }

    private function buildStats(int $dentistId, array $appointments): array
    {
        $today = date('Y-m-d');

        $todayAppointments = 0;
        $completedToday = 0;
        $patientIds = [];

        foreach ($appointments as $appointment) {
            $appointmentDate = (string) ($appointment['appointment_date'] ?? '');
            $status = (string) ($appointment['status'] ?? '');
            $patientId = (int) ($appointment['patient_id'] ?? 0);

            if ($appointmentDate === $today) {
                $todayAppointments++;
            }

            if ($appointmentDate === $today && $status === 'completed') {
                $completedToday++;
            }

            if ($patientId > 0) {
                $patientIds[$patientId] = true;
            }
        }

        return [
            'today_appointments' => $todayAppointments,
            'completed_today' => $completedToday,
            'my_patients' => count($patientIds),
            'followup_queue' => $this->countFollowUpQueue($dentistId),
        ];
    }

    private function countFollowUpQueue(int $dentistId): int
    {
        try {
            if (!$this->tableExists('follow_ups')) {
                return 0;
            }

            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM follow_ups
                WHERE dentist_id = :dentist_id
                  AND (
                        status IS NULL
                        OR status = ''
                        OR status IN ('pending', 'scheduled', 'open')
                  )
            ");

            $stmt->execute([
                ':dentist_id' => $dentistId,
            ]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function weeklyPatientName(array $appointment): string
    {
        $patientName = trim(
            (string) ($appointment['patient_first_name'] ?? '') . ' ' .
            (string) ($appointment['patient_middle_name'] ?? '') . ' ' .
            (string) ($appointment['patient_last_name'] ?? '')
        );

        if ($patientName !== '') {
            return $patientName;
        }

        $guestName = trim(
            (string) ($appointment['guest_first_name'] ?? '') . ' ' .
            (string) ($appointment['guest_middle_name'] ?? '') . ' ' .
            (string) ($appointment['guest_last_name'] ?? '')
        );

        return $guestName !== '' ? $guestName : 'Patient';
    }

    private function queryWeeklyAppointments(int $dentistId, string $startDate, string $endDate): array
    {
        if ($dentistId <= 0) {
            return [];
        }

        $sql = "
            SELECT
                a.appointment_id,
                a.appointment_code,
                a.dentist_id,
                a.patient_id,
                a.request_id,
                a.appointment_date,
                a.start_time,
                a.end_time,
                a.status,
                a.remarks,

                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,

                ar.guest_first_name,
                ar.guest_middle_name,
                ar.guest_last_name,

                s.service_name
            FROM appointments a
            LEFT JOIN patients p ON p.patient_id = a.patient_id
            LEFT JOIN appointment_requests ar ON ar.request_id = a.request_id
            LEFT JOIN services s ON s.service_id = a.service_id
            WHERE a.dentist_id = :dentist_id
              AND a.appointment_date BETWEEN :start_date AND :end_date
              AND a.status NOT IN ('cancelled', 'rejected', 'no_show')
            ORDER BY
                a.appointment_date ASC,
                a.start_time ASC,
                a.appointment_id ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':dentist_id' => $dentistId,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function appointmentPatientName(array $appointment): string
    {
        $patientName = trim(
            (string) ($appointment['patient_first_name'] ?? '') . ' ' .
            (string) ($appointment['patient_middle_name'] ?? '') . ' ' .
            (string) ($appointment['patient_last_name'] ?? '')
        );

        if ($patientName !== '') {
            return $patientName;
        }

        $guestName = trim(
            (string) ($appointment['guest_first_name'] ?? '') . ' ' .
            (string) ($appointment['guest_middle_name'] ?? '') . ' ' .
            (string) ($appointment['guest_last_name'] ?? '')
        );

        return $guestName !== '' ? $guestName : 'the patient';
    }

    private function appointmentDentistName(array $appointment): string
    {
        $dentistName = trim(
            (string) ($appointment['dentist_first_name'] ?? '') . ' ' .
            (string) ($appointment['dentist_last_name'] ?? '')
        );

        return $dentistName !== '' ? 'Dr. ' . $dentistName : 'The dentist';
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

    private function appointmentTimeLabel(array $appointment): string
    {
        $time = trim((string) ($appointment['start_time'] ?? ''));

        if ($time === '') {
            return 'the scheduled time';
        }

        $timestamp = strtotime($time);

        return $timestamp !== false ? date('h:i A', $timestamp) : $time;
    }

    private function sanitizeDateFilter(string $date): ?string
    {
        $date = trim($date);

        if ($date === '') {
            return null;
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        $errors = DateTime::getLastErrors();

        if (
            !$parsed ||
            $parsed->format('Y-m-d') !== $date ||
            ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return null;
        }

        return $date;
    }

    private function sanitizeStatusFilter(string $status): string
    {
        $status = strtolower(trim($status));

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return '';
        }

        return $status;
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

    private function sortAppointments(array $appointments): array
    {
        usort($appointments, function (array $a, array $b): int {
            $aDate = (string) ($a['appointment_date'] ?? '');
            $bDate = (string) ($b['appointment_date'] ?? '');

            if ($aDate !== $bDate) {
                return strcmp($aDate, $bDate);
            }

            $aTime = (string) ($a['start_time'] ?? '');
            $bTime = (string) ($b['start_time'] ?? '');

            if ($aTime !== $bTime) {
                return strcmp($aTime, $bTime);
            }

            return (int) ($a['appointment_id'] ?? 0) <=> (int) ($b['appointment_id'] ?? 0);
        });

        return $appointments;
    }

    private function appointmentsTableHasColumn(string $columnName): bool
    {
        $allowedColumns = [
            'arrival_status',
            'checked_in_at',
            'started_at',
            'completed_at',
            'updated_at',
        ];

        if (!in_array($columnName, $allowedColumns, true)) {
            return false;
        }

        $columns = $this->getTableColumns('appointments');

        return isset($columns[$columnName]);
    }

    private function tableExists(string $tableName): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ");

            $stmt->execute([
                ':table_name' => $tableName,
            ]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function getTableColumns(string $tableName): array
    {
        static $cache = [];

        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        $cache[$tableName] = [];

        try {
            if (!$this->tableExists($tableName)) {
                return $cache[$tableName];
            }

            $stmt = $this->db->prepare("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table_name
            ");

            $stmt->execute([
                ':table_name' => $tableName,
            ]);

            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            foreach ($columns as $column) {
                $cache[$tableName][strtolower((string) $column)] = true;
            }
        } catch (Throwable $e) {
            $cache[$tableName] = [];
        }

        return $cache[$tableName];
    }

    private function url(string $path): string
    {
        return '/DentalClinic/public' . $path;
    }
}