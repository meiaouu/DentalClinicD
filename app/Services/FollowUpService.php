<?php

namespace App\Services;

use App\Core\Database;
use App\Repositories\FollowUpRepository;
use App\Repositories\ReminderRepository;
use App\Repositories\TreatmentRepository;
use RuntimeException;

class FollowUpService
{
    private FollowUpRepository $followUps;
    private ReminderRepository $reminders;
    private TreatmentRepository $treatments;

    public function __construct()
    {
        $this->followUps = new FollowUpRepository();
        $this->reminders = new ReminderRepository();
        $this->treatments = new TreatmentRepository();
    }

    public function createFromTreatment(
        int $treatmentId,
        string $recommendedDate,
        string $reason,
        ?string $remarks = null
    ): int {
        $treatment = $this->treatments->findDetailedById($treatmentId);

        if (!$treatment) {
            throw new RuntimeException('Treatment not found.');
        }

        if ($recommendedDate === '') {
            throw new RuntimeException('Recommended date is required.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $followUpId = $this->followUps->create([
                'patient_id' => (int) $treatment['patient_id'],
                'dentist_id' => (int) $treatment['dentist_id'],
                'treatment_id' => (int) $treatmentId,
                'recommended_date' => $recommendedDate,
                'reason' => $reason,
                'remarks' => $remarks,
                'status' => 'scheduled',
            ]);

            $this->queueFollowUpReminders(
                $followUpId,
                (int) $treatment['patient_id'],
                $recommendedDate
            );

            $db->commit();

            return $followUpId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function queueFollowUpReminders(int $followUpId, int $patientId, string $recommendedDate): void
    {
        $days = [3, 2, 1];

        foreach ($days as $day) {
            $reminderDate = date('Y-m-d', strtotime($recommendedDate . " -{$day} days"));

            $this->reminders->create([
                'appointment_id' => null,
                'follow_up_id' => $followUpId,
                'patient_id' => $patientId,
                'reminder_type' => "follow_up_{$day}_day_before",
                'reminder_date' => $reminderDate,
                'reminder_status' => 'queued',
                'sent_at' => null,
            ]);
        }
    }

    public function getStaffList(?string $status = null, int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;

        return [
            'items' => $this->followUps->paginateForStaff($status, $perPage, $offset),
            'total' => $this->followUps->countForStaff($status),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function getDetailed(int $followUpId): array
    {
        $followUp = $this->followUps->findDetailedById($followUpId);

        if (!$followUp) {
            throw new RuntimeException('Follow-up not found.');
        }

        $reminders = $this->reminders->getByFollowUpId($followUpId);

        return [
            'follow_up' => $followUp,
            'reminders' => $reminders,
        ];
    }

    public function updateStatus(int $followUpId, string $status, ?string $remarks = null): void
    {
        $allowed = ['scheduled', 'completed', 'cancelled', 'missed'];

        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('Invalid follow-up status.');
        }

        $this->followUps->updateStatus($followUpId, $status, $remarks);
    }
}