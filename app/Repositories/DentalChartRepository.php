<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class DentalChartRepository
{
    public const SURFACES = [
        'Distal',
        'Facial',
        'Labial',
        'Buccal',
        'Incisal',
        'Lingual',
        'Mesial',
        'Occlusal',
        'Proximal',
    ];

    public const PROCEDURES = [
        'Tooth Extraction',
        'Dental Cleaning',
        'Tooth Restoration',
        'Root Canal Treatment',
        'Dentures, Crowns and Fixed Bridges',
        'Orthodontics (Braces)',
        'Surgery',
        'Dental Implants',
        'Teeth Whitening',
    ];

    public const STATUSES = [
        'planned',
        'performed',
        'completed',
        'cancelled',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findDentistByUserId(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                d.*,
                u.first_name,
                u.last_name,
                u.email
            FROM dentists d
            LEFT JOIN users u ON u.user_id = d.user_id
            WHERE d.user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findOrCreateExamination(int $appointmentId, int $patientId, int $dentistId): int
    {
        if ($appointmentId <= 0 || $patientId <= 0 || $dentistId <= 0) {
            throw new RuntimeException('Invalid examination details.');
        }

        $stmt = $this->db->prepare("
            SELECT examination_id
            FROM examinations
            WHERE appointment_id = :appointment_id
            LIMIT 1
        ");

        $stmt->execute([
            'appointment_id' => $appointmentId,
        ]);

        $existingId = (int) ($stmt->fetchColumn() ?: 0);

        if ($existingId > 0) {
            return $existingId;
        }

        $stmt = $this->db->prepare("
            INSERT INTO examinations (
                appointment_id,
                patient_id,
                dentist_id,
                examination_date,
                chief_complaint,
                intraoral_exam_notes,
                clinical_findings,
                diagnosis,
                treatment_plan,
                recommendations,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :appointment_id,
                :patient_id,
                :dentist_id,
                CURDATE(),
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'appointment_id' => $appointmentId,
            'patient_id' => $patientId,
            'dentist_id' => $dentistId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function examinationBelongsToPatient(int $examinationId, int $patientId, int $dentistId): bool
    {
        if ($examinationId <= 0 || $patientId <= 0 || $dentistId <= 0) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM examinations
            WHERE examination_id = :examination_id
              AND patient_id = :patient_id
              AND dentist_id = :dentist_id
        ");

        $stmt->execute([
            'examination_id' => $examinationId,
            'patient_id' => $patientId,
            'dentist_id' => $dentistId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function createEntry(array $data): int
    {
        $patientId = (int) ($data['patient_id'] ?? 0);
        $appointmentId = (int) ($data['appointment_id'] ?? 0);
        $examinationId = (int) ($data['examination_id'] ?? 0);
        $dentistId = (int) ($data['dentist_id'] ?? 0);
        $toothNumber = (int) ($data['tooth_number'] ?? 0);

        if ($patientId <= 0) {
            throw new RuntimeException('Invalid patient record.');
        }

        if ($appointmentId <= 0) {
            throw new RuntimeException('Invalid appointment record.');
        }

        if ($examinationId <= 0) {
            throw new RuntimeException('Invalid examination record.');
        }

        if ($dentistId <= 0) {
            throw new RuntimeException('Invalid dentist record.');
        }

        if (!in_array($toothNumber, $this->validToothNumbers(), true)) {
            throw new RuntimeException('Invalid tooth number selected.');
        }

        $surface = trim((string) ($data['surface'] ?? ''));

        /*
            Surface is now optional.
            Empty surface is allowed and saved as an empty string.
        */
        if ($surface !== '' && !in_array($surface, self::SURFACES, true)) {
            throw new RuntimeException('Invalid tooth surface.');
        }

        $procedureName = trim((string) ($data['procedure_name'] ?? ''));

        if ($procedureName === '') {
            throw new RuntimeException('Please select a dental procedure.');
        }

        if (!in_array($procedureName, self::PROCEDURES, true)) {
            throw new RuntimeException('Invalid dental procedure.');
        }

        $status = trim((string) ($data['status'] ?? 'planned'));

        if (!in_array($status, self::STATUSES, true)) {
            $status = 'planned';
        }

        $notes = mb_substr(trim((string) ($data['notes'] ?? '')), 0, 2000);
        $createdBy = (int) ($data['created_by'] ?? 0);
        $updatedBy = (int) ($data['updated_by'] ?? 0);

        $stmt = $this->db->prepare("
            INSERT INTO dental_chart_entries (
                patient_id,
                appointment_id,
                examination_id,
                dentist_id,
                tooth_number,
                surface,
                procedure_name,
                status,
                notes,
                created_by,
                updated_by,
                created_at,
                updated_at
            ) VALUES (
                :patient_id,
                :appointment_id,
                :examination_id,
                :dentist_id,
                :tooth_number,
                :surface,
                :procedure_name,
                :status,
                :notes,
                :created_by,
                :updated_by,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
            'examination_id' => $examinationId,
            'dentist_id' => $dentistId,
            'tooth_number' => $toothNumber,
            'surface' => $surface,
            'procedure_name' => $procedureName,
            'status' => $status,
            'notes' => $notes,
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'updated_by' => $updatedBy > 0 ? $updatedBy : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findEntryById(int $entryId): ?array
    {
        if ($entryId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                chart_entry_id,
                patient_id,
                appointment_id,
                examination_id,
                dentist_id,
                tooth_number,
                surface,
                procedure_name,
                status,
                notes,
                created_by,
                updated_by,
                created_at,
                updated_at
            FROM dental_chart_entries
            WHERE chart_entry_id = :chart_entry_id
            LIMIT 1
        ");

        $stmt->execute([
            'chart_entry_id' => $entryId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getEntriesByPatientId(int $patientId): array
    {
        if ($patientId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                chart_entry_id,
                patient_id,
                appointment_id,
                examination_id,
                dentist_id,
                tooth_number,
                surface,
                procedure_name,
                status,
                notes,
                created_by,
                updated_by,
                created_at,
                updated_at
            FROM dental_chart_entries
            WHERE patient_id = :patient_id
            ORDER BY created_at DESC, chart_entry_id DESC
        ");

        $stmt->execute([
            'patient_id' => $patientId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function groupByTooth(array $entries): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $toothNumber = (int) ($entry['tooth_number'] ?? 0);

            if ($toothNumber <= 0) {
                continue;
            }

            if (!isset($grouped[$toothNumber])) {
                $grouped[$toothNumber] = [];
            }

            $grouped[$toothNumber][] = $entry;
        }

        return $grouped;
    }

    private function validToothNumbers(): array
    {
        return [
            18, 17, 16, 15, 14, 13, 12, 11,
            21, 22, 23, 24, 25, 26, 27, 28,
            48, 47, 46, 45, 44, 43, 42, 41,
            31, 32, 33, 34, 35, 36, 37, 38,
        ];
    }

public function deleteAllEntriesForPatient(int $patientId): int
{
    if ($patientId <= 0) {
        return 0;
    }

    $deleted = 0;

    $this->db->beginTransaction();

    try {
        /*
            Deletes records from your newer interactive chart table
            if your system uses dental_chart_entries.
        */
        if ($this->tableExists('dental_chart_entries') && $this->columnExists('dental_chart_entries', 'patient_id')) {
            $stmt = $this->db->prepare("
                DELETE FROM dental_chart_entries
                WHERE patient_id = :patient_id
            ");

            $stmt->execute([
                ':patient_id' => $patientId,
            ]);

            $deleted += $stmt->rowCount();
        }

        /*
            Deletes records from your legacy odontogram_entries table
            through examinations, because odontogram_entries only has examination_id.
        */
        if ($this->tableExists('odontogram_entries') && $this->tableExists('examinations')) {
            $stmt = $this->db->prepare("
                DELETE oe
                FROM odontogram_entries oe
                INNER JOIN examinations e
                    ON e.examination_id = oe.examination_id
                WHERE e.patient_id = :patient_id
            ");

            $stmt->execute([
                ':patient_id' => $patientId,
            ]);

            $deleted += $stmt->rowCount();
        }

        $this->db->commit();

        return $deleted;
    } catch (Throwable $e) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        throw $e;
    }
}

private function tableExists(string $tableName): bool
{
    $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
    ");

    $stmt->execute([
        ':table_name' => $tableName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

private function columnExists(string $tableName, string $columnName): bool
{
    $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
          AND COLUMN_NAME = :column_name
    ");

    $stmt->execute([
        ':table_name' => $tableName,
        ':column_name' => $columnName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}


}