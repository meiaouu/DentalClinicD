<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class AppointmentRequestRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int
{
    /*
        Column-safe create method.

        This keeps your existing appointment_requests structure working,
        but also supports new optional columns like wants_patient_account
        without crashing older databases.
    */

    $allowedColumns = [
        'request_code',
        'patient_id',
        'is_guest',
        'source_channel',
        'wants_patient_account',

        'guest_first_name',
        'guest_middle_name',
        'guest_last_name',
        'guest_contact_number',
        'guest_email',

        'sex',
        'birth_date',
        'civil_status',
        'address',
        'occupation',
        'emergency_contact_name',
        'emergency_contact_number',

        'preferred_dentist_id',
        'service_id',
        'preferred_date',
        'preferred_start_time',
        'notes',

        'request_status',
        'reviewed_by_user_id',
        'reviewed_at',
        'converted_appointment_id',
        'staff_notes',
    ];

    $defaults = [
        'patient_id' => null,
        'is_guest' => 1,
        'source_channel' => 'web',
        'wants_patient_account' => 0,

        'guest_first_name' => null,
        'guest_middle_name' => null,
        'guest_last_name' => null,
        'guest_contact_number' => null,
        'guest_email' => null,

        'sex' => null,
        'birth_date' => null,
        'civil_status' => null,
        'address' => null,
        'occupation' => null,
        'emergency_contact_name' => null,
        'emergency_contact_number' => null,

        'preferred_dentist_id' => null,
        'notes' => null,

        'request_status' => 'pending',
        'reviewed_by_user_id' => null,
        'reviewed_at' => null,
        'converted_appointment_id' => null,
        'staff_notes' => null,
    ];

    $data = array_merge($defaults, $data);

    if (empty($data['request_code'])) {
        throw new RuntimeException('Request code is required.');
    }

    if (empty($data['service_id'])) {
        throw new RuntimeException('Service is required.');
    }

    if (empty($data['preferred_date'])) {
        throw new RuntimeException('Preferred date is required.');
    }

    if (empty($data['preferred_start_time'])) {
        throw new RuntimeException('Preferred time is required.');
    }

    $columns = [];
    $placeholders = [];
    $params = [];

    foreach ($allowedColumns as $column) {
        if (!$this->appointmentRequestColumnExists($column)) {
            continue;
        }

        $columns[] = "`{$column}`";
        $placeholders[] = ":{$column}";
        $params[":{$column}"] = $data[$column] ?? null;
    }

    if ($this->appointmentRequestColumnExists('created_at')) {
        $columns[] = '`created_at`';
        $placeholders[] = 'NOW()';
    }

    if ($this->appointmentRequestColumnExists('updated_at')) {
        $columns[] = '`updated_at`';
        $placeholders[] = 'NOW()';
    }

    if (empty($columns)) {
        throw new RuntimeException('Appointment request table is not ready.');
    }

    $sql = "
        INSERT INTO appointment_requests (
            " . implode(', ', $columns) . "
        ) VALUES (
            " . implode(', ', $placeholders) . "
        )
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return (int) $this->db->lastInsertId();
}

    public function createWithServices(array $data, array $serviceIds): int
    {
        $serviceIds = $this->normalizeServiceIds($serviceIds, (int) ($data['service_id'] ?? 0));

        if (empty($serviceIds)) {
            throw new RuntimeException('Please select at least one service.');
        }

        $data['service_id'] = $serviceIds[0];

        $startedTransaction = !$this->db->inTransaction();

        if ($startedTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $requestId = $this->create($data);

            $this->replaceServices($requestId, $serviceIds);

            if ($startedTransaction) {
                $this->db->commit();
            }

            return $requestId;
        } catch (Throwable $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function replaceServices(int $requestId, array $serviceIds): void
    {
        if (!$this->hasRequestServicesTable()) {
            return;
        }

        $cleanServiceIds = $this->normalizeServiceIds($serviceIds);

        $delete = $this->db->prepare("
            DELETE FROM appointment_request_services
            WHERE request_id = :request_id
        ");

        $delete->execute([
            'request_id' => $requestId,
        ]);

        if (empty($cleanServiceIds)) {
            return;
        }

        $insert = $this->db->prepare("
            INSERT IGNORE INTO appointment_request_services (
                request_id,
                service_id,
                created_at
            ) VALUES (
                :request_id,
                :service_id,
                NOW()
            )
        ");

        foreach ($cleanServiceIds as $serviceId) {
            $insert->execute([
                'request_id' => $requestId,
                'service_id' => $serviceId,
            ]);
        }
    }

    public function paginateForStaff(?int $serviceId, string $sort, int $limit, int $offset): array
    {
        $conditions = [];
        $params = [];

        if ($serviceId !== null && $serviceId > 0) {
            if ($this->hasRequestServicesTable()) {
                $conditions[] = "(
                    ar.service_id = :service_id
                    OR EXISTS (
                        SELECT 1
                        FROM appointment_request_services ars_filter
                        WHERE ars_filter.request_id = ar.request_id
                          AND ars_filter.service_id = :service_id
                    )
                )";
            } else {
                $conditions[] = 'ar.service_id = :service_id';
            }

            $params['service_id'] = $serviceId;
        }

        $whereSql = !empty($conditions)
            ? 'WHERE ' . implode(' AND ', $conditions)
            : '';

        $orderSql = $sort === 'oldest'
            ? 'ORDER BY ar.created_at ASC, ar.request_id ASC'
            : 'ORDER BY ar.created_at DESC, ar.request_id DESC';

        $sql = "
            SELECT
                ar.request_id,
                ar.request_code,
                ar.patient_id,
                ar.is_guest,
                ar.guest_first_name,
                ar.guest_middle_name,
                ar.guest_last_name,
                ar.guest_contact_number,
                ar.guest_email,
                ar.preferred_dentist_id,
                ar.service_id,
                ar.preferred_date,
                ar.preferred_start_time,
                ar.notes,
                ar.request_status,
                ar.reviewed_at,
                ar.converted_appointment_id,
                ar.created_at,

                s.service_name,

                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,

                COALESCE(a_confirmed.dentist_id, a_by_request.dentist_id, ar.preferred_dentist_id) AS dentist_id,
                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name

            FROM appointment_requests ar

            LEFT JOIN appointments a_confirmed
                ON a_confirmed.appointment_id = ar.converted_appointment_id

            LEFT JOIN (
                SELECT request_id, MIN(appointment_id) AS appointment_id
                FROM appointments
                WHERE request_id IS NOT NULL
                GROUP BY request_id
            ) appointment_match
                ON appointment_match.request_id = ar.request_id

            LEFT JOIN appointments a_by_request
                ON a_by_request.appointment_id = appointment_match.appointment_id

            LEFT JOIN services s
                ON s.service_id = ar.service_id

            LEFT JOIN patients p
                ON p.patient_id = ar.patient_id

            LEFT JOIN dentists d
                ON d.dentist_id = COALESCE(a_confirmed.dentist_id, a_by_request.dentist_id, ar.preferred_dentist_id)

            LEFT JOIN users du
                ON du.user_id = d.user_id

            $whereSql
            $orderSql

            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $this->attachServiceDataToRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function countForStaff(?int $serviceId): int
    {
        $conditions = [];
        $params = [];

        if ($serviceId !== null && $serviceId > 0) {
            if ($this->hasRequestServicesTable()) {
                $conditions[] = "(
                    ar.service_id = :service_id
                    OR EXISTS (
                        SELECT 1
                        FROM appointment_request_services ars_filter
                        WHERE ars_filter.request_id = ar.request_id
                          AND ars_filter.service_id = :service_id
                    )
                )";
            } else {
                $conditions[] = 'ar.service_id = :service_id';
            }

            $params['service_id'] = $serviceId;
        }

        $whereSql = !empty($conditions)
            ? 'WHERE ' . implode(' AND ', $conditions)
            : '';

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointment_requests ar
            $whereSql
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findDetailedById(int $requestId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                ar.*,

                s.service_name,

                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,

                COALESCE(a_confirmed.dentist_id, a_by_request.dentist_id, ar.preferred_dentist_id) AS dentist_id,
                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name

            FROM appointment_requests ar

            LEFT JOIN appointments a_confirmed
                ON a_confirmed.appointment_id = ar.converted_appointment_id

            LEFT JOIN (
                SELECT request_id, MIN(appointment_id) AS appointment_id
                FROM appointments
                WHERE request_id IS NOT NULL
                GROUP BY request_id
            ) appointment_match
                ON appointment_match.request_id = ar.request_id

            LEFT JOIN appointments a_by_request
                ON a_by_request.appointment_id = appointment_match.appointment_id

            LEFT JOIN services s
                ON s.service_id = ar.service_id

            LEFT JOIN patients p
                ON p.patient_id = ar.patient_id

            LEFT JOIN dentists d
                ON d.dentist_id = COALESCE(a_confirmed.dentist_id, a_by_request.dentist_id, ar.preferred_dentist_id)

            LEFT JOIN users du
                ON du.user_id = d.user_id

            WHERE ar.request_id = :request_id
            LIMIT 1
        ");

        $stmt->execute([
            'request_id' => $requestId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->attachServiceDataToRow($row);
    }

    public function getAnswersByRequestId(int $requestId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ara.*,
                so.option_name,
                sov.value_label
            FROM appointment_request_answers ara
            LEFT JOIN service_options so ON so.option_id = ara.option_id
            LEFT JOIN service_option_values sov ON sov.value_id = ara.selected_value_id
            WHERE ara.request_id = :request_id
            ORDER BY ara.request_answer_id ASC
        ");

        $stmt->execute([
            'request_id' => $requestId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markAsReviewed(int $requestId, int $staffUserId): void
    {
        $stmt = $this->db->prepare("
            UPDATE appointment_requests
            SET request_status = 'under_review',
                reviewed_by_user_id = :reviewed_by_user_id,
                reviewed_at = NOW(),
                updated_at = NOW()
            WHERE request_id = :request_id
              AND request_status = 'pending'
              AND reviewed_at IS NULL
        ");

        $stmt->execute([
            'request_id' => $requestId,
            'reviewed_by_user_id' => $staffUserId,
        ]);
    }

    public function updateAfterConfirmation(
        int $requestId,
        int $appointmentId,
        int $staffUserId,
        ?string $staffNotes = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE appointment_requests
            SET request_status = 'confirmed',
                reviewed_by_user_id = :reviewed_by_user_id,
                reviewed_at = NOW(),
                converted_appointment_id = :converted_appointment_id,
                preferred_dentist_id = COALESCE(
                    (
                        SELECT a.dentist_id
                        FROM appointments a
                        WHERE a.appointment_id = :appointment_id_for_dentist
                        LIMIT 1
                    ),
                    preferred_dentist_id
                ),
                staff_notes = :staff_notes,
                updated_at = NOW()
            WHERE request_id = :request_id
        ");

        $stmt->execute([
            'reviewed_by_user_id' => $staffUserId,
            'converted_appointment_id' => $appointmentId,
            'appointment_id_for_dentist' => $appointmentId,
            'staff_notes' => $staffNotes,
            'request_id' => $requestId,
        ]);
    }

    public function updateStatus(
        int $requestId,
        string $status,
        int $staffUserId,
        ?string $staffNotes = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE appointment_requests
            SET request_status = :request_status,
                reviewed_by_user_id = :reviewed_by_user_id,
                reviewed_at = NOW(),
                staff_notes = :staff_notes,
                updated_at = NOW()
            WHERE request_id = :request_id
        ");

        $stmt->execute([
            'request_status' => $status,
            'reviewed_by_user_id' => $staffUserId,
            'staff_notes' => $staffNotes,
            'request_id' => $requestId,
        ]);
    }

    public function updateReschedule(
        int $requestId,
        string $preferredDate,
        string $preferredStartTime,
        int $staffUserId,
        ?string $staffNotes = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE appointment_requests
            SET preferred_date = :preferred_date,
                preferred_start_time = :preferred_start_time,
                request_status = 'rescheduled',
                reviewed_by_user_id = :reviewed_by_user_id,
                reviewed_at = NOW(),
                staff_notes = :staff_notes,
                updated_at = NOW()
            WHERE request_id = :request_id
        ");

        $stmt->execute([
            'preferred_date' => $preferredDate,
            'preferred_start_time' => $preferredStartTime,
            'reviewed_by_user_id' => $staffUserId,
            'staff_notes' => $staffNotes,
            'request_id' => $requestId,
        ]);
    }

    public function findByRequestCodeAndContact(string $requestCode, string $contactNumber): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                ar.*,

                s.service_name,

                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,

                COALESCE(a_confirmed.dentist_id, a_by_request.dentist_id, ar.preferred_dentist_id) AS dentist_id,
                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name

            FROM appointment_requests ar

            LEFT JOIN appointments a_confirmed
                ON a_confirmed.appointment_id = ar.converted_appointment_id

            LEFT JOIN (
                SELECT request_id, MIN(appointment_id) AS appointment_id
                FROM appointments
                WHERE request_id IS NOT NULL
                GROUP BY request_id
            ) appointment_match
                ON appointment_match.request_id = ar.request_id

            LEFT JOIN appointments a_by_request
                ON a_by_request.appointment_id = appointment_match.appointment_id

            LEFT JOIN services s
                ON s.service_id = ar.service_id

            LEFT JOIN patients p
                ON p.patient_id = ar.patient_id

            LEFT JOIN dentists d
                ON d.dentist_id = COALESCE(a_confirmed.dentist_id, a_by_request.dentist_id, ar.preferred_dentist_id)

            LEFT JOIN users du
                ON du.user_id = d.user_id

            WHERE ar.request_code = :request_code
              AND (
                    ar.guest_contact_number = :contact_number
                    OR EXISTS (
                        SELECT 1
                        FROM patients px
                        WHERE px.patient_id = ar.patient_id
                          AND px.contact_number = :contact_number
                    )
                  )
            LIMIT 1
        ");

        $stmt->execute([
            'request_code' => $requestCode,
            'contact_number' => $contactNumber,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->attachServiceDataToRow($row);
    }

    public function cancelPendingByGuest(int $requestId, ?string $notes = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE appointment_requests
            SET request_status = 'cancelled_by_patient',
                staff_notes = CASE
                    WHEN :notes IS NOT NULL AND :notes <> ''
                    THEN :notes
                    ELSE staff_notes
                END,
                updated_at = NOW()
            WHERE request_id = :request_id
              AND request_status IN ('pending', 'under_review', 'rescheduled')
        ");

        $stmt->execute([
            'request_id' => $requestId,
            'notes' => $notes,
        ]);

        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('This appointment request can no longer be cancelled.');
        }
    }

    public function createForPatient(int $patientId, array $data): int
    {
        $serviceIds = $this->normalizeServiceIds(
            $data['service_ids'] ?? [],
            (int) ($data['service_id'] ?? 0)
        );

        if (!empty($serviceIds)) {
            $data['service_id'] = $serviceIds[0];
        }

        $stmt = $this->db->prepare("
            INSERT INTO appointment_requests (
                request_code,
                patient_id,
                is_guest,
                source_channel,
                guest_first_name,
                guest_middle_name,
                guest_last_name,
                guest_contact_number,
                guest_email,
                sex,
                birth_date,
                civil_status,
                address,
                occupation,
                emergency_contact_name,
                emergency_contact_number,
                preferred_dentist_id,
                service_id,
                preferred_date,
                preferred_start_time,
                notes,
                request_status,
                reviewed_by_user_id,
                reviewed_at,
                converted_appointment_id,
                staff_notes,
                created_at,
                updated_at
            ) VALUES (
                :request_code,
                :patient_id,
                :is_guest,
                :source_channel,
                :guest_first_name,
                :guest_middle_name,
                :guest_last_name,
                :guest_contact_number,
                :guest_email,
                :sex,
                :birth_date,
                :civil_status,
                :address,
                :occupation,
                :emergency_contact_name,
                :emergency_contact_number,
                :preferred_dentist_id,
                :service_id,
                :preferred_date,
                :preferred_start_time,
                :notes,
                :request_status,
                :reviewed_by_user_id,
                :reviewed_at,
                :converted_appointment_id,
                :staff_notes,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'request_code' => $data['request_code'],
            'patient_id' => $patientId,
            'is_guest' => 0,
            'source_channel' => $data['source_channel'] ?? 'patient_portal',
            'guest_first_name' => null,
            'guest_middle_name' => null,
            'guest_last_name' => null,
            'guest_contact_number' => null,
            'guest_email' => null,
            'sex' => $data['sex'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'civil_status' => $data['civil_status'] ?? null,
            'address' => $data['address'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_number' => $data['emergency_contact_number'] ?? null,
            'preferred_dentist_id' => $data['preferred_dentist_id'] ?? null,
            'service_id' => $data['service_id'],
            'preferred_date' => $data['preferred_date'],
            'preferred_start_time' => $data['preferred_start_time'],
            'notes' => $data['notes'] ?? null,
            'request_status' => $data['request_status'] ?? 'pending',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'converted_appointment_id' => null,
            'staff_notes' => null,
        ]);

        $requestId = (int) $this->db->lastInsertId();

        if (!empty($serviceIds)) {
            $this->replaceServices($requestId, $serviceIds);
        }

        return $requestId;
    }

    public function getByPatientId(int $patientId, int $limit = 10, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ar.*,
                s.service_name,

                COALESCE(a_confirmed.dentist_id, a_by_request.dentist_id, ar.preferred_dentist_id) AS dentist_id,
                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name

            FROM appointment_requests ar

            LEFT JOIN appointments a_confirmed
                ON a_confirmed.appointment_id = ar.converted_appointment_id

            LEFT JOIN (
                SELECT request_id, MIN(appointment_id) AS appointment_id
                FROM appointments
                WHERE request_id IS NOT NULL
                GROUP BY request_id
            ) appointment_match
                ON appointment_match.request_id = ar.request_id

            LEFT JOIN appointments a_by_request
                ON a_by_request.appointment_id = appointment_match.appointment_id

            LEFT JOIN services s
                ON s.service_id = ar.service_id

            LEFT JOIN dentists d
                ON d.dentist_id = COALESCE(a_confirmed.dentist_id, a_by_request.dentist_id, ar.preferred_dentist_id)

            LEFT JOIN users du
                ON du.user_id = d.user_id

            WHERE ar.patient_id = :patient_id
            ORDER BY ar.created_at DESC, ar.request_id DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $this->attachServiceDataToRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function countByPatientId(int $patientId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM appointment_requests
            WHERE patient_id = :patient_id
        ");

        $stmt->execute([
            'patient_id' => $patientId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findByIdAndPatientId(int $requestId, int $patientId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                ar.*,

                s.service_name,

                COALESCE(a_confirmed.dentist_id, a_by_request.dentist_id, ar.preferred_dentist_id) AS dentist_id,
                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name

            FROM appointment_requests ar

            LEFT JOIN appointments a_confirmed
                ON a_confirmed.appointment_id = ar.converted_appointment_id

            LEFT JOIN (
                SELECT request_id, MIN(appointment_id) AS appointment_id
                FROM appointments
                WHERE request_id IS NOT NULL
                GROUP BY request_id
            ) appointment_match
                ON appointment_match.request_id = ar.request_id

            LEFT JOIN appointments a_by_request
                ON a_by_request.appointment_id = appointment_match.appointment_id

            LEFT JOIN services s
                ON s.service_id = ar.service_id

            LEFT JOIN dentists d
                ON d.dentist_id = COALESCE(a_confirmed.dentist_id, a_by_request.dentist_id, ar.preferred_dentist_id)

            LEFT JOIN users du
                ON du.user_id = d.user_id

            WHERE ar.request_id = :request_id
              AND ar.patient_id = :patient_id
            LIMIT 1
        ");

        $stmt->execute([
            'request_id' => $requestId,
            'patient_id' => $patientId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->attachServiceDataToRow($row);
    }

    public function cancelPendingByPatient(int $requestId, int $patientId, ?string $notes = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE appointment_requests
            SET request_status = 'cancelled_by_patient',
                staff_notes = CASE
                    WHEN :notes IS NOT NULL AND :notes <> ''
                    THEN :notes
                    ELSE staff_notes
                END,
                updated_at = NOW()
            WHERE request_id = :request_id
              AND patient_id = :patient_id
              AND request_status IN ('pending', 'under_review', 'rescheduled')
        ");

        $stmt->execute([
            'request_id' => $requestId,
            'patient_id' => $patientId,
            'notes' => $notes,
        ]);

        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('This appointment request can no longer be cancelled.');
        }
    }

    public function countPendingRequests(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*)
            FROM appointment_requests
            WHERE request_status = 'pending'
              AND reviewed_at IS NULL
        ");

        return (int) $stmt->fetchColumn();
    }

    public function getPendingRequests(int $limit = 10, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ar.*,
                s.service_name,
                p.first_name AS patient_first_name,
                p.middle_name AS patient_middle_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,

                COALESCE(a_confirmed.dentist_id, a_by_request.dentist_id, ar.preferred_dentist_id) AS dentist_id,
                du.first_name AS dentist_first_name,
                du.last_name AS dentist_last_name

            FROM appointment_requests ar

            LEFT JOIN appointments a_confirmed
                ON a_confirmed.appointment_id = ar.converted_appointment_id

            LEFT JOIN (
                SELECT request_id, MIN(appointment_id) AS appointment_id
                FROM appointments
                WHERE request_id IS NOT NULL
                GROUP BY request_id
            ) appointment_match
                ON appointment_match.request_id = ar.request_id

            LEFT JOIN appointments a_by_request
                ON a_by_request.appointment_id = appointment_match.appointment_id

            LEFT JOIN services s
                ON s.service_id = ar.service_id

            LEFT JOIN patients p
                ON p.patient_id = ar.patient_id

            LEFT JOIN dentists d
                ON d.dentist_id = COALESCE(a_confirmed.dentist_id, a_by_request.dentist_id, ar.preferred_dentist_id)

            LEFT JOIN users du
                ON du.user_id = d.user_id

            WHERE ar.request_status = 'pending'
              AND ar.reviewed_at IS NULL

            ORDER BY ar.preferred_date ASC, ar.preferred_start_time ASC

            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $this->attachServiceDataToRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
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

    public function getServiceIdsByRequestId(int $requestId): array
    {
        if (!$this->hasRequestServicesTable()) {
            $request = $this->findDetailedById($requestId);
            $serviceId = (int) ($request['service_id'] ?? 0);

            return $serviceId > 0 ? [$serviceId] : [];
        }

        $stmt = $this->db->prepare("
            SELECT service_id
            FROM appointment_request_services
            WHERE request_id = :request_id
            ORDER BY request_service_id ASC
        ");

        $stmt->execute([
            'request_id' => $requestId,
        ]);

        return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    public function getServiceNamesByRequestId(int $requestId): array
    {
        if (!$this->hasRequestServicesTable()) {
            $request = $this->findDetailedById($requestId);
            $serviceName = trim((string) ($request['service_name'] ?? ''));

            return $serviceName !== '' ? [$serviceName] : [];
        }

        $stmt = $this->db->prepare("
            SELECT s.service_name
            FROM appointment_request_services ars
            INNER JOIN services s ON s.service_id = ars.service_id
            WHERE ars.request_id = :request_id
            ORDER BY ars.request_service_id ASC
        ");

        $stmt->execute([
            'request_id' => $requestId,
        ]);

        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
    }

    private function attachServiceDataToRows(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $requestIds = [];

        foreach ($rows as $row) {
            $requestId = (int) ($row['request_id'] ?? 0);

            if ($requestId > 0) {
                $requestIds[] = $requestId;
            }
        }

        $serviceMap = $this->getServiceMapForRequests($requestIds);

        foreach ($rows as &$row) {
            $requestId = (int) ($row['request_id'] ?? 0);
            $services = $serviceMap[$requestId] ?? [];

            if (empty($services) && !empty($row['service_id'])) {
                $services[] = [
                    'service_id' => (int) $row['service_id'],
                    'service_name' => (string) ($row['service_name'] ?? 'Service'),
                ];
            }

            $serviceIds = [];
            $serviceNames = [];

            foreach ($services as $service) {
                $serviceId = (int) ($service['service_id'] ?? 0);
                $serviceName = trim((string) ($service['service_name'] ?? ''));

                if ($serviceId > 0) {
                    $serviceIds[$serviceId] = $serviceId;
                }

                if ($serviceName !== '') {
                    $serviceNames[] = $serviceName;
                }
            }

            $row['service_ids'] = array_values($serviceIds);
            $row['service_names'] = array_values(array_unique($serviceNames));
            $row['service_names_text'] = implode(', ', $row['service_names']);
        }

        unset($row);

        return $rows;
    }

    private function attachServiceDataToRow(array $row): array
    {
        $rows = $this->attachServiceDataToRows([$row]);

        return $rows[0] ?? $row;
    }

    private function getServiceMapForRequests(array $requestIds): array
    {
        $requestIds = array_values(array_unique(array_filter(array_map('intval', $requestIds))));

        if (empty($requestIds) || !$this->hasRequestServicesTable()) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($requestIds), '?'));

        $stmt = $this->db->prepare("
            SELECT
                ars.request_id,
                ars.service_id,
                s.service_name
            FROM appointment_request_services ars
            INNER JOIN services s ON s.service_id = ars.service_id
            WHERE ars.request_id IN ($placeholders)
            ORDER BY ars.request_service_id ASC
        ");

        foreach ($requestIds as $index => $requestId) {
            $stmt->bindValue($index + 1, $requestId, PDO::PARAM_INT);
        }

        $stmt->execute();

        $map = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $requestId = (int) ($row['request_id'] ?? 0);

            if (!isset($map[$requestId])) {
                $map[$requestId] = [];
            }

            $map[$requestId][] = [
                'service_id' => (int) ($row['service_id'] ?? 0),
                'service_name' => (string) ($row['service_name'] ?? ''),
            ];
        }

        return $map;
    }

    private function normalizeServiceIds(array $serviceIds, int $fallbackServiceId = 0): array
    {
        $ids = [];

        foreach ($serviceIds as $serviceId) {
            $serviceId = (int) $serviceId;

            if ($serviceId > 0) {
                $ids[$serviceId] = $serviceId;
            }
        }

        if ($fallbackServiceId > 0) {
            $ids[$fallbackServiceId] = $fallbackServiceId;
        }

        return array_values($ids);
    }

    private function hasRequestServicesTable(): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'appointment_request_services'
        ");

        $stmt->execute();

        $exists = (int) $stmt->fetchColumn() > 0;

        return $exists;
    }


public function findById(int $requestId): ?array
{
    return $this->findDetailedById($requestId);
}

public function findByIdForUpdate(int $requestId): ?array
{
    if ($requestId <= 0) {
        return null;
    }

    $stmt = $this->db->prepare("
        SELECT *
        FROM appointment_requests
        WHERE request_id = :request_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':request_id' => $requestId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return $row;
}

public function markConfirmed(
    int $requestId,
    int $appointmentId,
    int $patientId,
    int $staffUserId,
    ?string $staffNotes = null
): bool {
    if ($requestId <= 0 || $appointmentId <= 0 || $patientId <= 0) {
        return false;
    }

    $sets = [
        "request_status = 'confirmed'",
    ];

    $params = [
        ':request_id' => $requestId,
    ];

    if ($this->appointmentRequestColumnExists('patient_id')) {
        $sets[] = 'patient_id = :patient_id';
        $params[':patient_id'] = $patientId;
    }

    if ($this->appointmentRequestColumnExists('converted_appointment_id')) {
        $sets[] = 'converted_appointment_id = :converted_appointment_id';
        $params[':converted_appointment_id'] = $appointmentId;
    }

    if ($this->appointmentRequestColumnExists('reviewed_by_user_id')) {
        $sets[] = 'reviewed_by_user_id = :reviewed_by_user_id';
        $params[':reviewed_by_user_id'] = $staffUserId;
    }

    if ($this->appointmentRequestColumnExists('reviewed_at')) {
        $sets[] = 'reviewed_at = NOW()';
    }

    if ($this->appointmentRequestColumnExists('staff_notes')) {
        $sets[] = 'staff_notes = :staff_notes';
        $params[':staff_notes'] = $staffNotes;
    }

    if ($this->appointmentRequestColumnExists('preferred_dentist_id')) {
        $sets[] = "preferred_dentist_id = COALESCE(
            (
                SELECT a.dentist_id
                FROM appointments a
                WHERE a.appointment_id = :appointment_id_for_dentist
                LIMIT 1
            ),
            preferred_dentist_id
        )";

        $params[':appointment_id_for_dentist'] = $appointmentId;
    }

    if ($this->appointmentRequestColumnExists('updated_at')) {
        $sets[] = 'updated_at = NOW()';
    }

    $sql = "
        UPDATE appointment_requests
        SET " . implode(', ', $sets) . "
        WHERE request_id = :request_id
        LIMIT 1
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount() > 0;
}

private function appointmentRequestColumnExists(string $column): bool
{
    static $columns = null;

    if ($columns === null) {
        $stmt = $this->db->query("SHOW COLUMNS FROM appointment_requests");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $columns = array_map(
            fn (array $row): string => (string) $row['Field'],
            $rows
        );
    }

    return in_array($column, $columns, true);
}






}