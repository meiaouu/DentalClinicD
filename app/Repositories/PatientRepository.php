<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

class PatientRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAll(): array
{
    $stmt = $this->db->prepare("
        SELECT
            patient_id,
            patient_code,
            first_name,
            middle_name,
            last_name,
            birth_date,
            sex,
            contact_number,
            email,
            profile_status,
            created_at
        FROM patients
        WHERE profile_status = 'active'
        ORDER BY last_name ASC, first_name ASC
    ");

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

   public function paginateForStaff(?string $keyword, int $limit, int $offset): array
{
    $sql = "
        SELECT
            p.patient_id,
            p.patient_code,
            p.first_name,
            p.middle_name,
            p.last_name,
            p.birth_date,
            p.sex,
            p.contact_number,
            p.email,
            p.profile_status,
            p.created_at,
            COUNT(a.appointment_id) AS appointment_count
        FROM patients p
        LEFT JOIN appointments a
            ON a.patient_id = p.patient_id
        WHERE
            p.profile_status = 'active'
            AND (
                :keyword = ''
                OR p.patient_code LIKE :like_keyword
                OR p.first_name LIKE :like_keyword
                OR p.middle_name LIKE :like_keyword
                OR p.last_name LIKE :like_keyword
                OR p.contact_number LIKE :like_keyword
                OR p.email LIKE :like_keyword
            )
        GROUP BY
            p.patient_id,
            p.patient_code,
            p.first_name,
            p.middle_name,
            p.last_name,
            p.birth_date,
            p.sex,
            p.contact_number,
            p.email,
            p.profile_status,
            p.created_at
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $this->db->prepare($sql);
    $like = '%' . ($keyword ?? '') . '%';

    $stmt->bindValue(':keyword', $keyword ?? '');
    $stmt->bindValue(':like_keyword', $like);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

public function updateProfile(int $patientId, array $data): void
{
    if ($patientId <= 0) {
        throw new RuntimeException('Invalid patient ID.');
    }

    $data = $this->filterTableColumns('patients', $data);

    if (empty($data)) {
        throw new RuntimeException('No valid patient fields to update.');
    }

    if ($this->tableHasColumn('patients', 'updated_at')) {
        $data['updated_at'] = date('Y-m-d H:i:s');
    }

    $sets = [];
    $params = [
        ':patient_id' => $patientId,
    ];

    foreach ($data as $column => $value) {
        $sets[] = "`{$column}` = :{$column}";
        $params[":{$column}"] = $value;
    }

    $sql = "
        UPDATE patients
        SET " . implode(', ', $sets) . "
        WHERE patient_id = :patient_id
        LIMIT 1
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
}

public function saveDentalHistory(int $patientId, array $data): void
{
    $this->upsertPatientRelatedRecord('patient_dental_histories', $patientId, $data);
}

public function saveMedicalHistory(int $patientId, array $data): void
{
    $this->upsertPatientRelatedRecord('patient_medical_histories', $patientId, $data);
}

private function upsertPatientRelatedRecord(string $table, int $patientId, array $data): void
{
    if ($patientId <= 0) {
        throw new RuntimeException('Invalid patient ID.');
    }

    $allowedTables = [
        'patient_dental_histories',
        'patient_medical_histories',
    ];

    if (!in_array($table, $allowedTables, true)) {
        throw new RuntimeException('Invalid table.');
    }

    $data['patient_id'] = $patientId;

    if ($this->tableHasColumn($table, 'updated_at')) {
        $data['updated_at'] = date('Y-m-d H:i:s');
    }

    $data = $this->filterTableColumns($table, $data);

    if (!array_key_exists('patient_id', $data)) {
        throw new RuntimeException('The table must contain patient_id.');
    }

    $stmt = $this->db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE patient_id = :patient_id");
    $stmt->execute([
        ':patient_id' => $patientId,
    ]);

    $exists = (int) $stmt->fetchColumn() > 0;

    if ($exists) {
        $sets = [];
        $params = [
            ':patient_id' => $patientId,
        ];

        foreach ($data as $column => $value) {
            if ($column === 'patient_id') {
                continue;
            }

            $sets[] = "`{$column}` = :{$column}";
            $params[":{$column}"] = $value;
        }

        if (empty($sets)) {
            return;
        }

        $sql = "
            UPDATE `{$table}`
            SET " . implode(', ', $sets) . "
            WHERE patient_id = :patient_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return;
    }

    if ($this->tableHasColumn($table, 'created_at')) {
        $data['created_at'] = date('Y-m-d H:i:s');
    }

    $data = $this->filterTableColumns($table, $data);

    $columns = array_keys($data);
    $columnSql = implode(', ', array_map(fn ($column) => "`{$column}`", $columns));
    $valueSql = implode(', ', array_map(fn ($column) => ":{$column}", $columns));

    $params = [];
    foreach ($data as $column => $value) {
        $params[":{$column}"] = $value;
    }

    $sql = "
        INSERT INTO `{$table}` ({$columnSql})
        VALUES ({$valueSql})
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
}

private function filterTableColumns(string $table, array $data): array
{
    $columns = $this->getTableColumns($table);
    $filtered = [];

    foreach ($data as $key => $value) {
        if (in_array($key, $columns, true)) {
            $filtered[$key] = $value;
        }
    }

    return $filtered;
}

private function tableHasColumn(string $table, string $column): bool
{
    return in_array($column, $this->getTableColumns($table), true);
}

private function getTableColumns(string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $allowedTables = [
        'patients',
        'patient_dental_histories',
        'patient_medical_histories',
    ];

    if (!in_array($table, $allowedTables, true)) {
        throw new RuntimeException('Invalid table.');
    }

    $stmt = $this->db->query("SHOW COLUMNS FROM `{$table}`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cache[$table] = array_map(
        fn ($row) => (string) $row['Field'],
        $rows ?: []
    );

    return $cache[$table];
}


    public function countForStaff(?string $keyword): int
{
    $sql = "
        SELECT COUNT(*) 
        FROM patients p
        WHERE
            p.profile_status = 'active'
            AND (
                :keyword = ''
                OR p.patient_code LIKE :like_keyword
                OR p.first_name LIKE :like_keyword
                OR p.middle_name LIKE :like_keyword
                OR p.last_name LIKE :like_keyword
                OR p.contact_number LIKE :like_keyword
                OR p.email LIKE :like_keyword
            )
    ";

    $stmt = $this->db->prepare($sql);
    $like = '%' . ($keyword ?? '') . '%';

    $stmt->bindValue(':keyword', $keyword ?? '');
    $stmt->bindValue(':like_keyword', $like);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

    public function findById(int $patientId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM patients
            WHERE patient_id = :patient_id
            LIMIT 1
        ");
        $stmt->execute([':patient_id' => $patientId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM patients
            WHERE user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute([
            ':user_id' => $userId,
        ]);

        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        return $patient ?: null;
    }

    public function findMedicalHistory(int $patientId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM patient_medical_histories
            WHERE patient_id = :patient_id
            ORDER BY medical_history_id DESC
            LIMIT 1
        ");
        $stmt->execute([':patient_id' => $patientId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDentalHistory(int $patientId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM patient_dental_histories
            WHERE patient_id = :patient_id
            ORDER BY dental_history_id DESC
            LIMIT 1
        ");
        $stmt->execute([':patient_id' => $patientId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

   public function getAppointmentHistory(int $patientId): array
{
    if ($patientId <= 0) {
        return [];
    }

    if ($this->tableHasColumnsForPatientMultipleServices('appointment_services', ['appointment_id', 'service_id'])) {
        return $this->getAppointmentHistoryWithMultipleServices($patientId);
    }

    return $this->getAppointmentHistoryLegacyService($patientId);
}

private function getAppointmentHistoryWithMultipleServices(int $patientId): array
{
    $stmt = $this->db->prepare("
        SELECT
            a.*,

            COALESCE(
                NULLIF(TRIM(ms.service_names_text), ''),
                NULLIF(TRIM(s.service_name), ''),
                'Dental Service'
            ) AS service_name,

            ms.service_names_text,
            ms.service_ids_text,

            du.first_name AS dentist_first_name,
            du.last_name AS dentist_last_name
        FROM appointments a
        LEFT JOIN (
            SELECT
                aps.appointment_id,
                GROUP_CONCAT(DISTINCT s2.service_name ORDER BY s2.service_name SEPARATOR ', ') AS service_names_text,
                GROUP_CONCAT(DISTINCT aps.service_id ORDER BY aps.service_id SEPARATOR ',') AS service_ids_text
            FROM appointment_services aps
            INNER JOIN services s2 ON s2.service_id = aps.service_id
            GROUP BY aps.appointment_id
        ) ms ON ms.appointment_id = a.appointment_id
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN dentists d ON d.dentist_id = a.dentist_id
        LEFT JOIN users du ON du.user_id = d.user_id
        WHERE a.patient_id = :patient_id
        ORDER BY a.appointment_date DESC, a.start_time DESC, a.appointment_id DESC
    ");

    $stmt->execute([
        ':patient_id' => $patientId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

private function getAppointmentHistoryLegacyService(int $patientId): array
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
    ");

    $stmt->execute([
        ':patient_id' => $patientId,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

private function tableHasColumnsForPatientMultipleServices(string $tableName, array $requiredColumns): bool
{
    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);

    if ($tableName === '') {
        return false;
    }

    $tableStmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
    ");

    $tableStmt->execute([
        ':table_name' => $tableName,
    ]);

    if ((int) $tableStmt->fetchColumn() < 1) {
        return false;
    }

    $placeholders = [];
    $params = [
        ':table_name' => $tableName,
    ];

    foreach ($requiredColumns as $index => $columnName) {
        $key = ':column_' . $index;
        $placeholders[] = $key;
        $params[$key] = $columnName;
    }

    $columnStmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
          AND COLUMN_NAME IN (" . implode(',', $placeholders) . ")
    ");

    $columnStmt->execute($params);

    return (int) $columnStmt->fetchColumn() === count($requiredColumns);
}

    public function findDuplicateByContact(string $contactNumber): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM patients
            WHERE contact_number = :contact_number
            LIMIT 1
        ");
        $stmt->execute([':contact_number' => $contactNumber]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByContactNumber(string $contactNumber): ?array
    {
        return $this->findDuplicateByContact($contactNumber);
    }

    public function findDuplicateByEmail(string $email): ?array
    {
        if ($email === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM patients
            WHERE LOWER(email) = LOWER(:email)
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDuplicateByNameAndBirthDate(
        string $firstName,
        string $middleName,
        string $lastName,
        ?string $birthDate
    ): ?array {
        if (!$birthDate) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM patients
            WHERE
                LOWER(first_name) = LOWER(:first_name)
                AND LOWER(COALESCE(middle_name, '')) = LOWER(:middle_name)
                AND LOWER(last_name) = LOWER(:last_name)
                AND birth_date = :birth_date
            LIMIT 1
        ");
        $stmt->execute([
            ':first_name' => $firstName,
            ':middle_name' => $middleName,
            ':last_name' => $lastName,
            ':birth_date' => $birthDate,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

   public function create(array $data): int
{
    $stmt = $this->db->prepare("
        INSERT INTO patients (
            user_id,
            patient_code,
            first_name,
            middle_name,
            last_name,
            sex,
            birth_date,
            civil_status,
            address,
            occupation,
            contact_number,
            email,
            emergency_contact_name,
            emergency_contact_number,
            notes,
            profile_status,
            created_by,
            created_at,
            updated_at
        ) VALUES (
            :user_id,
            :patient_code,
            :first_name,
            :middle_name,
            :last_name,
            :sex,
            :birth_date,
            :civil_status,
            :address,
            :occupation,
            :contact_number,
            :email,
            :emergency_contact_name,
            :emergency_contact_number,
            :notes,
            :profile_status,
            :created_by,
            NOW(),
            NOW()
        )
    ");

    $stmt->execute([
        'user_id' => $data['user_id'],
        'patient_code' => $data['patient_code'],
        'first_name' => $data['first_name'],
        'middle_name' => $data['middle_name'],
        'last_name' => $data['last_name'],
        'sex' => $data['sex'],
        'birth_date' => $data['birth_date'],
        'civil_status' => $data['civil_status'],
        'address' => $data['address'],
        'occupation' => $data['occupation'],
        'contact_number' => $data['contact_number'],
        'email' => $data['email'],
        'emergency_contact_name' => $data['emergency_contact_name'],
        'emergency_contact_number' => $data['emergency_contact_number'],
        'notes' => $data['notes'],
        'profile_status' => $data['profile_status'],
        'created_by' => $data['created_by'],
    ]);

    return (int) $this->db->lastInsertId();
}
public function countAll(): int
{
    $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM patients
        WHERE profile_status = 'active'
    ");

    $stmt->execute();

    return (int) $stmt->fetchColumn();
}



    public function update(int $patientId, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE patients SET
                first_name = :first_name,
                middle_name = :middle_name,
                last_name = :last_name,
                sex = :sex,
                birth_date = :birth_date,
                civil_status = :civil_status,
                address = :address,
                occupation = :occupation,
                contact_number = :contact_number,
                email = :email,
                emergency_contact_name = :emergency_contact_name,
                emergency_contact_number = :emergency_contact_number,
                notes = :notes,
                profile_status = :profile_status
            WHERE patient_id = :patient_id
        ");

        $stmt->execute([
            ':patient_id' => $patientId,
            ':first_name' => $data['first_name'],
            ':middle_name' => $data['middle_name'] ?? null,
            ':last_name' => $data['last_name'],
            ':sex' => $data['sex'] ?? null,
            ':birth_date' => $data['birth_date'] ?? null,
            ':civil_status' => $data['civil_status'] ?? null,
            ':address' => $data['address'] ?? null,
            ':occupation' => $data['occupation'] ?? null,
            ':contact_number' => $data['contact_number'] ?? null,
            ':email' => $data['email'] ?? null,
            ':emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            ':emergency_contact_number' => $data['emergency_contact_number'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':profile_status' => $data['profile_status'] ?? 'active',
        ]);
    }

    public function upsertMedicalHistory(int $patientId, array $data): void
    {
        $existing = $this->findMedicalHistory($patientId);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE patient_medical_histories SET
                    physician_name = :physician_name,
                    physician_contact = :physician_contact,
                    allergies = :allergies,
                    medications = :medications,
                    blood_pressure = :blood_pressure,
                    diabetes = :diabetes,
                    pregnancy_status = :pregnancy_status,
                    bleeding_disorder = :bleeding_disorder,
                    notes = :notes,
                    updated_by = :updated_by
                WHERE patient_id = :patient_id
            ");
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO patient_medical_histories (
                    patient_id, physician_name, physician_contact, allergies,
                    medications, blood_pressure, diabetes, pregnancy_status,
                    bleeding_disorder, notes, updated_by
                ) VALUES (
                    :patient_id, :physician_name, :physician_contact, :allergies,
                    :medications, :blood_pressure, :diabetes, :pregnancy_status,
                    :bleeding_disorder, :notes, :updated_by
                )
            ");
        }

        $stmt->execute([
            ':patient_id' => $patientId,
            ':physician_name' => $data['physician_name'] ?? null,
            ':physician_contact' => $data['physician_contact'] ?? null,
            ':allergies' => $data['allergies'] ?? null,
            ':medications' => $data['medications'] ?? null,
            ':blood_pressure' => $data['blood_pressure'] ?? null,
            ':diabetes' => $data['diabetes'] ?? null,
            ':pregnancy_status' => $data['pregnancy_status'] ?? null,
            ':bleeding_disorder' => $data['bleeding_disorder'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':updated_by' => $data['updated_by'],
        ]);
    }

public function createFromAppointmentRequest(array $request, int $staffUserId = 0): int
{
    $data = [
        'user_id' => null,
        'patient_code' => $this->generatePatientCode(),
        'first_name' => $this->nullIfBlank($request['guest_first_name'] ?? $request['first_name'] ?? null),
        'middle_name' => $this->nullIfBlank($request['guest_middle_name'] ?? $request['middle_name'] ?? null),
        'last_name' => $this->nullIfBlank($request['guest_last_name'] ?? $request['last_name'] ?? null),
        'sex' => $this->nullIfBlank($request['sex'] ?? null),
        'birth_date' => $this->nullIfBlank($request['birth_date'] ?? null),
        'civil_status' => $this->nullIfBlank($request['civil_status'] ?? null),
        'address' => $this->nullIfBlank($request['address'] ?? null),
        'occupation' => $this->nullIfBlank($request['occupation'] ?? null),
        'contact_number' => $this->nullIfBlank($request['guest_contact_number'] ?? $request['contact_number'] ?? null),
        'email' => $this->nullIfBlank($request['guest_email'] ?? $request['email'] ?? null),
        'emergency_contact_name' => $this->nullIfBlank($request['emergency_contact_name'] ?? null),
        'emergency_contact_number' => $this->nullIfBlank($request['emergency_contact_number'] ?? null),
        'notes' => 'Auto-created from appointment request #' . (int) ($request['request_id'] ?? 0),
        'profile_status' => 'active',
        'created_by' => $staffUserId > 0 ? $staffUserId : null,
    ];

    if ($this->tableHasColumn('patients', 'created_at')) {
        $data['created_at'] = date('Y-m-d H:i:s');
    }

    if ($this->tableHasColumn('patients', 'updated_at')) {
        $data['updated_at'] = date('Y-m-d H:i:s');
    }

    $data = $this->filterTableColumns('patients', $data);

    if (empty($data['first_name']) || empty($data['last_name'])) {
        throw new RuntimeException('Patient first name and last name are required.');
    }

    $columns = array_keys($data);
    $columnSql = implode(', ', array_map(fn ($column) => "`{$column}`", $columns));
    $valueSql = implode(', ', array_map(fn ($column) => ":{$column}", $columns));

    $params = [];
    foreach ($data as $column => $value) {
        $params[":{$column}"] = $value;
    }

    $sql = "
        INSERT INTO patients ({$columnSql})
        VALUES ({$valueSql})
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return (int) $this->db->lastInsertId();
}




    public function upsertDentalHistory(int $patientId, array $data): void
    {
        $existing = $this->findDentalHistory($patientId);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE patient_dental_histories SET
                    previous_dentist = :previous_dentist,
                    last_dental_visit = :last_dental_visit,
                    gums_bleed = :gums_bleed,
                    bad_breath = :bad_breath,
                    loose_teeth = :loose_teeth,
                    sensitive_teeth = :sensitive_teeth,
                    clicking_jaw = :clicking_jaw,
                    notes = :notes,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE patient_id = :patient_id
            ");
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO patient_dental_histories (
                    patient_id, previous_dentist, last_dental_visit, gums_bleed,
                    bad_breath, loose_teeth, sensitive_teeth, clicking_jaw,
                    notes, updated_by, created_at, updated_at
                ) VALUES (
                    :patient_id, :previous_dentist, :last_dental_visit, :gums_bleed,
                    :bad_breath, :loose_teeth, :sensitive_teeth, :clicking_jaw,
                    :notes, :updated_by, NOW(), NOW()
                )
            ");
        }

        $stmt->execute([
            ':patient_id' => $patientId,
            ':previous_dentist' => $data['previous_dentist'] ?? null,
            ':last_dental_visit' => $data['last_dental_visit'] ?? null,
            ':gums_bleed' => $data['gums_bleed'] ?? null,
            ':bad_breath' => $data['bad_breath'] ?? null,
            ':loose_teeth' => $data['loose_teeth'] ?? null,
            ':sensitive_teeth' => $data['sensitive_teeth'] ?? null,
            ':clicking_jaw' => $data['clicking_jaw'] ?? null,
            ':notes' => $data['notes'] ?? null,
            ':updated_by' => $data['updated_by'],
        ]);
    }




public function findStrongMatchFromRequest(array $request): ?array
{
    $firstName = $this->cleanMatchValue($request['guest_first_name'] ?? $request['first_name'] ?? '');
    $middleName = $this->cleanMatchValue($request['guest_middle_name'] ?? $request['middle_name'] ?? '');
    $lastName = $this->cleanMatchValue($request['guest_last_name'] ?? $request['last_name'] ?? '');
    $birthDate = $this->cleanMatchValue($request['birth_date'] ?? '');

    $contactNumber = $this->cleanMatchValue(
        $request['guest_contact_number'] ?? $request['contact_number'] ?? ''
    );

    $email = strtolower($this->cleanMatchValue(
        $request['guest_email'] ?? $request['email'] ?? ''
    ));

    $sex = $this->cleanMatchValue($request['sex'] ?? '');
    $address = $this->cleanMatchValue($request['address'] ?? '');
    $emergencyContactName = $this->cleanMatchValue($request['emergency_contact_name'] ?? '');
    $emergencyContactNumber = $this->cleanMatchValue($request['emergency_contact_number'] ?? '');

    /*
        Existing patient_id from request is only trusted if name and
        another strong field match. This prevents unsafe linking.
    */
    if (!empty($request['patient_id'])) {
        $candidate = $this->findById((int) $request['patient_id']);

        if ($candidate && $this->sameFullName($candidate, $firstName, $middleName, $lastName)) {
            $candidateBirthDate = $this->cleanMatchValue($candidate['birth_date'] ?? '');
            $candidateContact = $this->cleanMatchValue($candidate['contact_number'] ?? '');

            if ($birthDate !== '' && $candidateBirthDate === $birthDate) {
                return $candidate;
            }

            if ($contactNumber !== '' && $this->contactsMatch($candidateContact, $contactNumber)) {
                return $candidate;
            }
        }
    }

    /*
        Strong Rule A:
        contact number + full name + birth date

        Fallback Rule B:
        contact number + full name + email

        Fallback Rule D:
        contact number + full name + at least one supporting field
    */
    $contactCandidates = $this->findCandidatesByContactNumber($contactNumber);

    foreach ($contactCandidates as $candidate) {
        if (!$this->sameFullName($candidate, $firstName, $middleName, $lastName)) {
            continue;
        }

        $candidateBirthDate = $this->cleanMatchValue($candidate['birth_date'] ?? '');
        $candidateEmail = strtolower($this->cleanMatchValue($candidate['email'] ?? ''));

        if ($birthDate !== '' && $candidateBirthDate !== '' && $candidateBirthDate === $birthDate) {
            return $candidate;
        }

        if ($email !== '' && $candidateEmail !== '' && $candidateEmail === $email) {
            return $candidate;
        }

        if ($this->hasSupportingMatch(
            $candidate,
            $sex,
            $address,
            $emergencyContactName,
            $emergencyContactNumber
        )) {
            return $candidate;
        }
    }

    /*
        Fallback Rule C:
        full name + birth date + email

        Important:
        This is not email-only matching.
    */
    if ($firstName !== '' && $lastName !== '' && $birthDate !== '' && $email !== '') {
        $stmt = $this->db->prepare("
            SELECT *
            FROM patients
            WHERE LOWER(first_name) = LOWER(:first_name)
              AND LOWER(COALESCE(last_name, '')) = LOWER(:last_name)
              AND birth_date = :birth_date
              AND LOWER(email) = LOWER(:email)
            LIMIT 10
        ");

        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':birth_date' => $birthDate,
            ':email' => $email,
        ]);

        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($candidates as $candidate) {
            if ($this->sameFullName($candidate, $firstName, $middleName, $lastName)) {
                return $candidate;
            }
        }
    }

    return null;
}

public function linkUserAccount(int $patientId, int $userId): bool
{
    if ($patientId <= 0 || $userId <= 0) {
        return false;
    }

    if (!$this->tableHasColumn('patients', 'user_id')) {
        return false;
    }

    $patient = $this->findById($patientId);

    if (!$patient) {
        return false;
    }

    $existingUserId = (int) ($patient['user_id'] ?? 0);

    if ($existingUserId === $userId) {
        return true;
    }

    if ($existingUserId > 0 && $existingUserId !== $userId) {
        return false;
    }

    $sets = [
        'user_id = :user_id',
    ];

    $params = [
        ':user_id' => $userId,
        ':patient_id' => $patientId,
    ];

    if ($this->tableHasColumn('patients', 'updated_at')) {
        $sets[] = 'updated_at = NOW()';
    }

    $stmt = $this->db->prepare("
        UPDATE patients
        SET " . implode(', ', $sets) . "
        WHERE patient_id = :patient_id
          AND (user_id IS NULL OR user_id = 0)
        LIMIT 1
    ");

    $stmt->execute($params);

    return $stmt->rowCount() > 0;
}

private function findCandidatesByContactNumber(string $contactNumber): array
{
    $variants = $this->contactNumberVariants($contactNumber);

    if (empty($variants)) {
        return [];
    }

    $placeholders = [];
    $params = [];

    foreach ($variants as $index => $variant) {
        $key = ':contact_' . $index;
        $placeholders[] = $key;
        $params[$key] = $variant;
    }

    $stmt = $this->db->prepare("
        SELECT *
        FROM patients
        WHERE contact_number IN (" . implode(', ', $placeholders) . ")
        LIMIT 30
    ");

    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

private function sameFullName(array $patient, string $firstName, string $middleName, string $lastName): bool
{
    $patientFirst = strtolower($this->cleanMatchValue($patient['first_name'] ?? ''));
    $patientMiddle = strtolower($this->cleanMatchValue($patient['middle_name'] ?? ''));
    $patientLast = strtolower($this->cleanMatchValue($patient['last_name'] ?? ''));

    $firstName = strtolower($this->cleanMatchValue($firstName));
    $middleName = strtolower($this->cleanMatchValue($middleName));
    $lastName = strtolower($this->cleanMatchValue($lastName));

    if ($patientFirst === '' || $patientLast === '' || $firstName === '' || $lastName === '') {
        return false;
    }

    if ($patientFirst !== $firstName || $patientLast !== $lastName) {
        return false;
    }

    /*
        Middle name is checked only when both records have it.
        This avoids rejecting a strong match just because one old record
        has no middle name saved.
    */
    if ($patientMiddle !== '' && $middleName !== '' && $patientMiddle !== $middleName) {
        return false;
    }

    return true;
}

private function hasSupportingMatch(
    array $patient,
    string $sex,
    string $address,
    string $emergencyContactName,
    string $emergencyContactNumber
): bool {
    $hits = 0;

    if ($sex !== '' && strtolower($this->cleanMatchValue($patient['sex'] ?? '')) === strtolower($sex)) {
        $hits++;
    }

    if ($address !== '' && strtolower($this->cleanMatchValue($patient['address'] ?? '')) === strtolower($address)) {
        $hits++;
    }

    if (
        $emergencyContactName !== ''
        && strtolower($this->cleanMatchValue($patient['emergency_contact_name'] ?? '')) === strtolower($emergencyContactName)
    ) {
        $hits++;
    }

    if (
        $emergencyContactNumber !== ''
        && $this->contactsMatch(
            $this->cleanMatchValue($patient['emergency_contact_number'] ?? ''),
            $emergencyContactNumber
        )
    ) {
        $hits++;
    }

    return $hits >= 1;
}

private function contactsMatch(string $left, string $right): bool
{
    $leftVariants = $this->contactNumberVariants($left);
    $rightVariants = $this->contactNumberVariants($right);

    return count(array_intersect($leftVariants, $rightVariants)) > 0;
}

private function contactNumberVariants(string $contactNumber): array
{
    $raw = trim($contactNumber);

    if ($raw === '') {
        return [];
    }

    $digits = preg_replace('/\D+/', '', $raw);

    if ($digits === '') {
        return [$raw];
    }

    $variants = [$raw, $digits];

    if (preg_match('/^09\d{9}$/', $digits)) {
        $variants[] = '63' . substr($digits, 1);
        $variants[] = '+63' . substr($digits, 1);
    }

    if (preg_match('/^639\d{9}$/', $digits)) {
        $variants[] = '0' . substr($digits, 2);
        $variants[] = '+' . $digits;
    }

    return array_values(array_unique(array_filter($variants)));
}

private function cleanMatchValue(mixed $value): string
{
    return trim(preg_replace('/\s+/', ' ', (string) $value));
}

private function nullIfBlank(mixed $value): ?string
{
    $value = trim((string) $value);
    return $value !== '' ? $value : null;
}

private function generatePatientCode(): string
{
    do {
        $code = 'PAT-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        if (!$this->tableHasColumn('patients', 'patient_code')) {
            return $code;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM patients
            WHERE patient_code = :patient_code
        ");

        $stmt->execute([
            ':patient_code' => $code,
        ]);

        $exists = (int) $stmt->fetchColumn() > 0;
    } while ($exists);

    return $code;
}

    
}