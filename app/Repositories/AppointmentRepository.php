<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AppointmentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function hasDentistConflict(
    int $dentistId,
    string $date,
    string $startTime,
    string $endTime
): bool {
    $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM appointments
        WHERE dentist_id = :dentist_id
          AND appointment_date = :appointment_date
          AND status IN ('confirmed', 'checked_in', 'in_progress')
          AND start_time < :end_time
          AND end_time > :start_time
    ");
    $stmt->execute([
        'dentist_id' => $dentistId,
        'appointment_date' => $date,
        'start_time' => $startTime,
        'end_time' => $endTime,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO appointments (
                appointment_code,
                request_id,
                dentist_id,
                patient_id,
                service_id,
                appointment_date,
                start_time,
                end_time,
                estimated_duration_minutes,
                estimated_price,
                status,
                arrival_status,
                grace_period_minutes,
                booked_by,
                confirmed_by,
                remarks,
                created_at,
                updated_at
            ) VALUES (
                :appointment_code,
                :request_id,
                :dentist_id,
                :patient_id,
                :service_id,
                :appointment_date,
                :start_time,
                :end_time,
                :estimated_duration_minutes,
                :estimated_price,
                :status,
                :arrival_status,
                :grace_period_minutes,
                :booked_by,
                :confirmed_by,
                :remarks,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'appointment_code' => $data['appointment_code'],
            'request_id' => $data['request_id'],
            'dentist_id' => $data['dentist_id'],
            'patient_id' => $data['patient_id'],
            'service_id' => $data['service_id'],
            'appointment_date' => $data['appointment_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'estimated_duration_minutes' => $data['estimated_duration_minutes'],
            'estimated_price' => $data['estimated_price'],
            'status' => $data['status'],
            'arrival_status' => $data['arrival_status'],
            'grace_period_minutes' => $data['grace_period_minutes'],
            'booked_by' => $data['booked_by'],
            'confirmed_by' => $data['confirmed_by'],
            'remarks' => $data['remarks'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function paginateByDate(string $date, ?string $status = null, int $limit = 20, int $offset = 0): array
{
    $sql = "
        SELECT
            a.*,
            s.service_name,
            COALESCE(NULLIF(TRIM(p.first_name), ''), ar.guest_first_name) AS patient_first_name,
            COALESCE(NULLIF(TRIM(p.last_name), ''), ar.guest_last_name) AS patient_last_name,
            COALESCE(NULLIF(TRIM(p.contact_number), ''), ar.guest_contact_number) AS patient_contact_number,
            COALESCE(NULLIF(TRIM(p.email), ''), ar.guest_email) AS patient_email,
            du.first_name AS dentist_first_name,
            du.last_name AS dentist_last_name
        FROM appointments a
        LEFT JOIN appointment_requests ar ON ar.request_id = a.request_id
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users du ON du.user_id = d.user_id
        WHERE a.appointment_date = :appointment_date
    ";

    if ($status !== null && $status !== '') {
        $sql .= " AND a.status = :status";
    }

    $sql .= " ORDER BY a.start_time ASC LIMIT :limit OFFSET :offset";

    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':appointment_date', $date);

    if ($status !== null && $status !== '') {
        $stmt->bindValue(':status', $status);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

    public function countByDate(string $date, ?string $status = null): int
{
    $sql = "
        SELECT COUNT(*)
        FROM appointments
        WHERE appointment_date = :appointment_date
    ";

    $params = [
        ':appointment_date' => $date,
    ];

    if ($status !== null && $status !== '') {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

public function countUpcomingFromDate(string $date): int
{
    $sql = "
        SELECT COUNT(*)
        FROM appointments
        WHERE appointment_date > :today
          AND status NOT IN ('completed', 'cancelled', 'no_show')
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':today' => $date,
    ]);

    return (int) $stmt->fetchColumn();
}

   public function findDetailedById(int $appointmentId): ?array
{
    $stmt = $this->db->prepare("
        SELECT
            a.*,
            s.service_name,
            s.description AS service_description,
            COALESCE(NULLIF(TRIM(p.first_name), ''), ar.guest_first_name) AS patient_first_name,
            COALESCE(NULLIF(TRIM(p.middle_name), ''), ar.guest_middle_name) AS patient_middle_name,
            COALESCE(NULLIF(TRIM(p.last_name), ''), ar.guest_last_name) AS patient_last_name,
            COALESCE(NULLIF(TRIM(p.contact_number), ''), ar.guest_contact_number) AS patient_contact_number,
COALESCE(NULLIF(TRIM(p.email), ''), ar.guest_email) AS patient_email,

COALESCE(NULLIF(TRIM(p.sex), ''), ar.sex) AS patient_sex,
COALESCE(NULLIF(p.birth_date, ''), ar.birth_date) AS patient_birth_date,

du.first_name AS dentist_first_name,
du.last_name AS dentist_last_name
        FROM appointments a
        LEFT JOIN appointment_requests ar ON ar.request_id = a.request_id
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users du ON du.user_id = d.user_id
        WHERE a.appointment_id = :appointment_id
        LIMIT 1
    ");
    $stmt->execute([
        'appointment_id' => $appointmentId,
    ]);

    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        return null;
    }

    $logStmt = $this->db->prepare("
        SELECT *
        FROM appointment_status_logs
        WHERE appointment_id = :appointment_id
        ORDER BY changed_at DESC, log_id DESC
    ");
    $logStmt->execute([
        'appointment_id' => $appointmentId,
    ]);

    $appointment['status_logs'] = $logStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return $appointment;
}

public function updateStatus(
    int $appointmentId,
    string $status,
    ?string $remarks = null,
    array $extraFields = []
): void {
    $allowedExtra = [
        'arrival_status',
        'checked_in_at',
        'actual_started_at',
        'actual_completed_at',
        'completed_at',
        'no_show_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    $sets = [
        "status = :status",
        "updated_at = NOW()",
    ];

    $params = [
        ':status' => $status,
        ':appointment_id' => $appointmentId,
    ];

    if ($remarks !== null) {
        $sets[] = "remarks = :remarks";
        $params[':remarks'] = $remarks;
    }

    foreach ($extraFields as $field => $value) {
        if (!in_array($field, $allowedExtra, true)) {
            continue;
        }

        $sets[] = "{$field} = :{$field}";
        $params[':' . $field] = $value;
    }

    $stmt = $this->db->prepare("
        UPDATE appointments
        SET " . implode(', ', $sets) . "
        WHERE appointment_id = :appointment_id
    ");

    $stmt->execute($params);
}

    public function updateArrivalStatus(int $appointmentId, string $arrivalStatus): void
    {
        $stmt = $this->db->prepare("
            UPDATE appointments
            SET arrival_status = :arrival_status,
                updated_at = NOW()
            WHERE appointment_id = :appointment_id
        ");
        $stmt->execute([
            'arrival_status' => $arrivalStatus,
            'appointment_id' => $appointmentId,
        ]);
    }



    public function getWaitingQueueByDate(string $date): array
{
    $sql = "
        SELECT
            a.*,
            s.service_name,
            p.first_name AS patient_first_name,
            p.last_name AS patient_last_name,
            du.first_name AS dentist_first_name,
            du.last_name AS dentist_last_name
        FROM appointments a
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users du ON du.user_id = d.user_id
        WHERE a.appointment_date = :appointment_date
          AND a.status = 'checked_in'
        ORDER BY
            CASE
                WHEN a.checked_in_at IS NOT NULL THEN 0
                ELSE 1
            END ASC,
            a.checked_in_at ASC,
            a.start_time ASC,
            a.appointment_id ASC
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':appointment_date' => $date,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

public function getInProgressQueueByDate(string $date): array
{
    $sql = "
        SELECT
            a.*,
            s.service_name,
            p.first_name AS patient_first_name,
            p.last_name AS patient_last_name,
            du.first_name AS dentist_first_name,
            du.last_name AS dentist_last_name
        FROM appointments a
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users du ON du.user_id = d.user_id
        WHERE a.appointment_date = :appointment_date
          AND a.status = 'in_progress'
        ORDER BY
            COALESCE(a.actual_started_at, a.checked_in_at, a.start_time) ASC,
            a.start_time ASC,
            a.appointment_id ASC
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':appointment_date' => $date,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


public function getByDateAndStatus(string $date, array $statuses): array
{
    if (empty($statuses)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($statuses), '?'));

    $sql = "
        SELECT
            a.*,
            COALESCE(NULLIF(TRIM(p.first_name), ''), ar.guest_first_name) AS patient_first_name,
            COALESCE(NULLIF(TRIM(p.middle_name), ''), ar.guest_middle_name) AS patient_middle_name,
            COALESCE(NULLIF(TRIM(p.last_name), ''), ar.guest_last_name) AS patient_last_name,
            COALESCE(NULLIF(TRIM(p.contact_number), ''), ar.guest_contact_number) AS patient_contact_number,
            du.first_name AS dentist_first_name,
            du.last_name AS dentist_last_name,
            s.service_name,
            ar.request_code,
            ar.is_guest
        FROM appointments a
        LEFT JOIN appointment_requests ar ON ar.request_id = a.request_id
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN dentists den ON den.dentist_id = a.dentist_id
        LEFT JOIN users du ON du.user_id = den.user_id
        LEFT JOIN services s ON s.service_id = a.service_id
        WHERE a.appointment_date = ?
          AND a.status IN ($placeholders)
        ORDER BY
            COALESCE(a.checked_in_at, a.start_time) ASC,
            a.start_time ASC,
            a.appointment_id ASC
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute(array_merge([$date], $statuses));

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


public function getByDate(string $date): array
{
    $stmt = $this->db->prepare("
        SELECT a.*, p.first_name AS patient_first_name, p.last_name AS patient_last_name
        FROM appointments a
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        WHERE a.appointment_date = :date
        ORDER BY a.start_time ASC
    ");

    $stmt->execute([':date' => $date]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

public function markProcedureStarted(int $appointmentId, string $actualStartedAt): void
{
    $stmt = $this->db->prepare("
        UPDATE appointments
        SET
            status = 'in_progress',
            actual_started_at = :actual_started_at,
            updated_at = :updated_at
        WHERE appointment_id = :appointment_id
          AND status IN ('confirmed', 'checked_in')
          AND actual_started_at IS NULL
    ");

    $stmt->execute([
        ':appointment_id' => $appointmentId,
        ':actual_started_at' => $actualStartedAt,
        ':updated_at' => $actualStartedAt,
    ]);
}

public function markProcedureCompleted(int $appointmentId, string $actualCompletedAt): void
{
    $stmt = $this->db->prepare("
        UPDATE appointments
        SET
            status = 'completed',
            actual_completed_at = :actual_completed_at,
            completed_at = :completed_at,
            updated_at = :updated_at
        WHERE appointment_id = :appointment_id
          AND status = 'in_progress'
          AND actual_started_at IS NOT NULL
          AND actual_completed_at IS NULL
    ");

    $stmt->execute([
        ':appointment_id' => $appointmentId,
        ':actual_completed_at' => $actualCompletedAt,
        ':completed_at' => $actualCompletedAt,
        ':updated_at' => $actualCompletedAt,
    ]);
}

public function getCompletedQueueByDate(string $date): array
{
    $sql = "
        SELECT
            a.*,
            s.service_name,
            p.first_name AS patient_first_name,
            p.last_name AS patient_last_name,
            du.first_name AS dentist_first_name,
            du.last_name AS dentist_last_name
        FROM appointments a
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users du ON du.user_id = d.user_id
        WHERE a.appointment_date = :appointment_date
          AND a.status = 'completed'
        ORDER BY
            a.completed_at DESC,
            a.start_time ASC,
            a.appointment_id ASC
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':appointment_date' => $date,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

public function getActiveScheduleByDate(string $date): array
{
    $sql = "
        SELECT
            a.*,
            s.service_name,
            p.first_name AS patient_first_name,
            p.last_name AS patient_last_name,
            du.first_name AS dentist_first_name,
            du.last_name AS dentist_last_name
        FROM appointments a
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users du ON du.user_id = d.user_id
        WHERE a.appointment_date = :appointment_date
        ORDER BY
            CASE
                WHEN a.status IN ('checked_in', 'in_progress') THEN 0
                WHEN a.status IN ('confirmed', 'rescheduled') THEN 1
                ELSE 2
            END ASC,
            a.start_time ASC,
            a.appointment_id ASC
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':appointment_date' => $date,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

public function paginateByPatientId(int $patientId, int $limit = 15, int $offset = 0): array
{
    $stmt = $this->db->prepare("
        SELECT
            a.*,
            s.service_name,
            du.first_name AS dentist_first_name,
            du.last_name AS dentist_last_name
        FROM appointments a
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users du ON du.user_id = d.user_id
        WHERE a.patient_id = :patient_id
        ORDER BY a.appointment_date DESC, a.start_time DESC, a.appointment_id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}




public function getByPatientId(int $patientId, int $limit = 10, int $offset = 0): array
{
    $stmt = $this->db->prepare("
        SELECT
            a.*,
            s.service_name,
            du.first_name AS dentist_first_name,
            du.last_name AS dentist_last_name
        FROM appointments a
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users du ON du.user_id = d.user_id
        WHERE a.patient_id = :patient_id
        ORDER BY a.appointment_date DESC, a.start_time DESC, a.appointment_id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

public function countByPatientId(int $patientId): int
{
    $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM appointments
        WHERE patient_id = :patient_id
    ");
    $stmt->execute([':patient_id' => $patientId]);

    return (int) $stmt->fetchColumn();
}

public function findByIdAndPatientId(int $appointmentId, int $patientId): ?array
{
    $stmt = $this->db->prepare("
        SELECT
            a.*,
            s.service_name,
            s.description AS service_description,
            du.first_name AS dentist_first_name,
            du.last_name AS dentist_last_name
        FROM appointments a
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users du ON du.user_id = d.user_id
        WHERE a.appointment_id = :appointment_id
          AND a.patient_id = :patient_id
        LIMIT 1
    ");
    $stmt->execute([
        'appointment_id' => $appointmentId,
        'patient_id' => $patientId,
    ]);

    $row = $stmt->fetch();
    return $row ?: null;
}

public function cancelPendingByPatient(int $appointmentId, int $patientId, ?string $reason = null): void
{
    $stmt = $this->db->prepare("
        UPDATE appointments
        SET status = 'cancelled',
            cancellation_reason = :cancellation_reason,
            updated_at = NOW()
        WHERE appointment_id = :appointment_id
          AND patient_id = :patient_id
          AND status = 'pending'
    ");
    $stmt->execute([
        'appointment_id' => $appointmentId,
        'patient_id' => $patientId,
        'cancellation_reason' => $reason,
    ]);

    if ($stmt->rowCount() < 1) {
        throw new \RuntimeException('This appointment can no longer be cancelled by the patient.');
    }
}



public function countActiveByDate(string $date): int
{
    $sql = "
        SELECT COUNT(*)
        FROM appointments
        WHERE appointment_date = :appointment_date
          AND status NOT IN ('completed', 'cancelled', 'no_show')
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':appointment_date' => $date,
    ]);

    return (int) $stmt->fetchColumn();
}

public function getActiveByDate(string $date, int $limit = 10): array
{
    $sql = "
        SELECT
            a.*,
            p.first_name AS patient_first_name,
            p.last_name AS patient_last_name,
            d.first_name AS dentist_first_name,
            d.last_name AS dentist_last_name,
            s.service_name
        FROM appointments a
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN dentists den ON den.dentist_id = a.dentist_id
        LEFT JOIN users d ON d.user_id = den.user_id
        LEFT JOIN services s ON s.service_id = a.service_id
        WHERE a.appointment_date = :appointment_date
          AND a.status NOT IN ('completed', 'cancelled', 'no_show')
        ORDER BY a.start_time ASC
        LIMIT {$limit}
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':appointment_date' => $date,
    ]);

    return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
}

public function getDentistAppointments(
    int $dentistId,
    ?string $date = null,
    ?string $status = null
): array {
    $sql = "
        SELECT
            a.*,
            s.service_name,
            p.patient_code,

            COALESCE(NULLIF(TRIM(p.first_name), ''), ar.guest_first_name) AS patient_first_name,
            COALESCE(NULLIF(TRIM(p.middle_name), ''), ar.guest_middle_name) AS patient_middle_name,
            COALESCE(NULLIF(TRIM(p.last_name), ''), ar.guest_last_name) AS patient_last_name,
            COALESCE(NULLIF(TRIM(p.contact_number), ''), ar.guest_contact_number) AS patient_contact_number,

            ar.guest_first_name,
            ar.guest_middle_name,
            ar.guest_last_name,
            ar.guest_contact_number,
            ar.notes AS request_notes,
            ar.is_guest,
            ar.request_code
        FROM appointments a
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN appointment_requests ar ON ar.request_id = a.request_id
        WHERE a.dentist_id = :dentist_id
        AND a.status IN ('confirmed', 'checked_in', 'in_progress', 'completed', 'no_show', 'cancelled', 'rescheduled')
    ";

    $params = [
        ':dentist_id' => $dentistId,
    ];

    if ($date !== null && $date !== '') {
        $sql .= " AND a.appointment_date = :appointment_date";
        $params[':appointment_date'] = $date;
    }

    if ($status !== null && $status !== '') {
        $sql .= " AND a.status = :status";
        $params[':status'] = $status;
    }

    $sql .= " ORDER BY a.appointment_date ASC, a.start_time ASC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

public function findDentistAppointmentById(int $appointmentId, int $dentistId): ?array
{
    $sql = "
        SELECT
            a.*,
            s.service_name,
            s.description AS service_description,
            p.patient_code,
            p.first_name AS patient_first_name,
            p.middle_name AS patient_middle_name,
            p.last_name AS patient_last_name,
            p.sex,
            p.birth_date,
            p.civil_status,
            p.address,
            p.occupation,
            p.contact_number AS patient_contact_number,
            p.email AS patient_email,
            ar.guest_first_name,
            ar.guest_middle_name,
            ar.guest_last_name,
            ar.guest_contact_number,
            ar.guest_email,
            ar.notes AS request_notes,
            ar.is_guest
        FROM appointments a
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN appointment_requests ar ON ar.request_id = a.request_id
        WHERE a.appointment_id = :appointment_id
        AND a.dentist_id = :dentist_id
        LIMIT 1
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':appointment_id' => $appointmentId,
        ':dentist_id' => $dentistId,
    ]);

    $appointment = $stmt->fetch(\PDO::FETCH_ASSOC);

    return $appointment ?: null;
}

public function getDentistDashboardStats(int $dentistId): array
{
    $today = date('Y-m-d');

    $stmt = $this->db->prepare("
        SELECT
            SUM(CASE WHEN appointment_date = :today THEN 1 ELSE 0 END) AS today_appointments,
            SUM(CASE WHEN appointment_date = :today AND status = 'completed' THEN 1 ELSE 0 END) AS completed_today,
            COUNT(DISTINCT patient_id) AS my_patients,
            SUM(CASE WHEN status IN ('confirmed', 'rescheduled', 'checked_in', 'in_progress') AND appointment_date >= :today THEN 1 ELSE 0 END) AS followup_queue
        FROM appointments
        WHERE dentist_id = :dentist_id
    ");

    $stmt->execute([
        ':today' => $today,
        ':dentist_id' => $dentistId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'today_appointments' => (int) ($row['today_appointments'] ?? 0),
        'completed_today' => (int) ($row['completed_today'] ?? 0),
        'my_patients' => (int) ($row['my_patients'] ?? 0),
        'followup_queue' => (int) ($row['followup_queue'] ?? 0),
    ];
}

public function getTodayAppointmentsForDentist(int $dentistId): array
{
    $stmt = $this->db->prepare("
        SELECT
            a.appointment_id,
            a.appointment_code,
            a.appointment_date,
            a.start_time,
            a.end_time,
            a.status,
            a.remarks,
            s.service_name,
            p.patient_id,
            p.first_name AS patient_first_name,
            p.last_name AS patient_last_name,
            d.dentist_id,
            u.first_name AS dentist_first_name,
            u.last_name AS dentist_last_name
        FROM appointments a
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users u ON u.user_id = d.user_id
        WHERE a.dentist_id = :dentist_id
          AND a.appointment_date = CURDATE()
          AND a.status IN ('confirmed', 'rescheduled', 'checked_in', 'in_progress')
        ORDER BY a.start_time ASC, a.appointment_id ASC
    ");

    $stmt->execute([
        ':dentist_id' => $dentistId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

public function getUpcomingAppointmentsForDentist(int $dentistId): array
{
    $stmt = $this->db->prepare("
        SELECT
            a.appointment_id,
            a.appointment_code,
            a.appointment_date,
            a.start_time,
            a.end_time,
            a.status,
            a.remarks,
            s.service_name,
            p.patient_id,
            p.first_name AS patient_first_name,
            p.last_name AS patient_last_name,
            d.dentist_id,
            u.first_name AS dentist_first_name,
            u.last_name AS dentist_last_name
        FROM appointments a
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users u ON u.user_id = d.user_id
        WHERE a.dentist_id = :dentist_id
          AND a.appointment_date > CURDATE()
          AND a.status IN ('confirmed', 'rescheduled')
        ORDER BY a.appointment_date ASC, a.start_time ASC
        LIMIT 20
    ");

    $stmt->execute([
        ':dentist_id' => $dentistId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

public function getCompletedAppointmentsForDentist(int $dentistId): array
{
    $stmt = $this->db->prepare("
        SELECT
            a.appointment_id,
            a.appointment_code,
            a.appointment_date,
            a.start_time,
            a.end_time,
            a.status,
            a.remarks,
            s.service_name,
            p.patient_id,
            p.first_name AS patient_first_name,
            p.last_name AS patient_last_name,
            d.dentist_id,
            u.first_name AS dentist_first_name,
            u.last_name AS dentist_last_name
        FROM appointments a
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users u ON u.user_id = d.user_id
        WHERE a.dentist_id = :dentist_id
          AND a.status = 'completed'
        ORDER BY a.appointment_date DESC, a.start_time DESC
        LIMIT 20
    ");

    $stmt->execute([
        ':dentist_id' => $dentistId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


public function findDentistIdByUserId(int $userId): ?int
{
    $stmt = $this->db->prepare("
        SELECT dentist_id
        FROM dentists
        WHERE user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $userId,
    ]);

    $dentistId = $stmt->fetchColumn();

    return $dentistId ? (int) $dentistId : null;
}


public function assignPatient(int $appointmentId, ?int $patientId): void
{
    $stmt = $this->db->prepare("
        UPDATE appointments
        SET patient_id = :patient_id,
            updated_at = NOW()
        WHERE appointment_id = :appointment_id
    ");

    if ($patientId === null || $patientId <= 0) {
        $stmt->bindValue(':patient_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
    }

    $stmt->bindValue(':appointment_id', $appointmentId, PDO::PARAM_INT);
    $stmt->execute();
}


public function findDentistAppointmentsBetween(int $dentistId, string $startDate, string $endDate): array
{
    $sql = "
        SELECT
            a.appointment_id,
            a.appointment_code,
            a.patient_id,
            a.appointment_date,
            a.start_time,
            a.end_time,
            a.status,
            a.remarks,
            a.guest_first_name,
            a.guest_middle_name,
            a.guest_last_name,
            p.first_name AS patient_first_name,
            p.middle_name AS patient_middle_name,
            p.last_name AS patient_last_name,
            s.service_name
        FROM appointments a
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN services s ON s.service_id = a.service_id
        WHERE a.dentist_id = :dentist_id
          AND a.appointment_date BETWEEN :start_date AND :end_date
          AND a.status NOT IN ('cancelled', 'rejected', 'no_show')
        ORDER BY a.appointment_date ASC, a.start_time ASC
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        'dentist_id' => $dentistId,
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



public function findDentistWeeklyAppointments(int $dentistId, string $startDate, string $endDate): array
{
    $sql = "
        SELECT
            a.appointment_id,
            a.appointment_code,
            a.dentist_id,
            a.patient_id,
            a.appointment_date,
            a.start_time,
            a.end_time,
            a.status,
            a.remarks,

            a.guest_first_name,
            a.guest_middle_name,
            a.guest_last_name,

            p.first_name AS patient_first_name,
            p.middle_name AS patient_middle_name,
            p.last_name AS patient_last_name,

            s.service_name
        FROM appointments a
        LEFT JOIN patients p ON p.patient_id = a.patient_id
        LEFT JOIN services s ON s.service_id = a.service_id
        WHERE a.dentist_id = :dentist_id
          AND a.appointment_date BETWEEN :start_date AND :end_date
          AND a.status NOT IN ('cancelled', 'rejected', 'no_show')
        ORDER BY a.appointment_date ASC, a.start_time ASC
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        'dentist_id' => $dentistId,
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}






public function getConnection(): PDO
{
    return $this->db;
}

public function findByIdForUpdate(int $appointmentId): ?array
{
    $stmt = $this->db->prepare("
        SELECT *
        FROM appointments
        WHERE appointment_id = :appointment_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':appointment_id' => $appointmentId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

public function updateProcedureStarted(
    int $appointmentId,
    string $actualStartedAt,
    ?string $remarks = null
): void {
    $sets = [
        "status = 'in_progress'",
        "actual_started_at = :actual_started_at",
        "updated_at = :updated_at",
    ];

    $params = [
        ':appointment_id' => $appointmentId,
        ':actual_started_at' => $actualStartedAt,
        ':updated_at' => $actualStartedAt,
    ];

    if ($remarks !== null && trim($remarks) !== '') {
        $sets[] = "remarks = :remarks";
        $params[':remarks'] = $remarks;
    }

    $stmt = $this->db->prepare("
        UPDATE appointments
        SET " . implode(', ', $sets) . "
        WHERE appointment_id = :appointment_id
    ");

    $stmt->execute($params);
}

public function updateProcedureCompleted(
    int $appointmentId,
    string $actualCompletedAt,
    ?string $remarks = null
): void {
    $sets = [
        "status = 'completed'",
        "actual_completed_at = :actual_completed_at",
        "completed_at = :completed_at",
        "updated_at = :updated_at",
    ];

    $params = [
        ':appointment_id' => $appointmentId,
        ':actual_completed_at' => $actualCompletedAt,
        ':completed_at' => $actualCompletedAt,
        ':updated_at' => $actualCompletedAt,
    ];

    if ($remarks !== null && trim($remarks) !== '') {
        $sets[] = "remarks = :remarks";
        $params[':remarks'] = $remarks;
    }

    $stmt = $this->db->prepare("
        UPDATE appointments
        SET " . implode(', ', $sets) . "
        WHERE appointment_id = :appointment_id
    ");

    $stmt->execute($params);
}

public function insertStatusLog(
    int $appointmentId,
    string $oldStatus,
    string $newStatus,
    int $changedBy,
    ?string $remarks = null,
    ?string $changedAt = null
): void {
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
            :changed_at
        )
    ");

    $stmt->execute([
        ':appointment_id' => $appointmentId,
        ':old_status' => $oldStatus,
        ':new_status' => $newStatus,
        ':changed_by' => $changedBy,
        ':remarks' => $remarks,
        ':changed_at' => $changedAt ?? date('Y-m-d H:i:s'),
    ]);
}





public function updateSchedule(
    int $appointmentId,
    string $appointmentDate,
    string $startTime,
    string $endTime,
    string $status = 'rescheduled',
    ?string $remarks = null
): bool {
    if ($appointmentId <= 0) {
        throw new \RuntimeException('Invalid appointment ID.');
    }

    if ($appointmentDate === '' || $startTime === '' || $endTime === '') {
        throw new \RuntimeException('Appointment date, start time, and end time are required.');
    }

    $stmt = $this->db->prepare("
        UPDATE appointments
        SET appointment_date = :appointment_date,
            start_time = :start_time,
            end_time = :end_time,
            status = :status,
            remarks = :remarks,
            updated_at = NOW()
        WHERE appointment_id = :appointment_id
        LIMIT 1
    ");

    $stmt->execute([
        ':appointment_id' => $appointmentId,
        ':appointment_date' => $appointmentDate,
        ':start_time' => $startTime,
        ':end_time' => $endTime,
        ':status' => $status,
        ':remarks' => $remarks,
    ]);

    return $stmt->rowCount() > 0;
}



}