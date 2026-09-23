<?php

namespace App\Services;

use App\Repositories\AppointmentRepository;
use App\Repositories\ClinicRepository;
use App\Repositories\DentistDateOverrideRepository;
use App\Repositories\DentistLookupRepository;
use App\Repositories\DentistScheduleRepository;
use App\Repositories\DentistUnavailableDateRepository;
use App\Repositories\ServiceRepository;
use DateInterval;
use DateTime;
use RuntimeException;

class BookingAvailabilityService
{
    private ClinicRepository $clinic;
    private ServiceRepository $services;
    private DentistScheduleRepository $dentistSchedules;
    private DentistUnavailableDateRepository $unavailableDates;
    private DentistDateOverrideRepository $dateOverrides;
    private AppointmentRepository $appointments;
    private DentistLookupRepository $dentists;

    public function __construct()
{
    $this->clinic = new ClinicRepository();
    $this->services = new ServiceRepository();
    $this->dentistSchedules = new DentistScheduleRepository();
    $this->unavailableDates = new DentistUnavailableDateRepository();
    $this->dateOverrides = new DentistDateOverrideRepository();
    $this->appointments = new AppointmentRepository();
    $this->dentists = new DentistLookupRepository();
}

    public function getAvailableSlots(string $date, int $serviceId, int $dentistId, bool $includePastSlots = false): array
    {
        if ($date === '' || $serviceId <= 0 || $dentistId <= 0) {
            return [];
        }

        $service = $this->services->findById($serviceId);
        if (!$service) {
            return [];
        }

        $durationMinutes = max(1, (int) ($service['estimated_duration_minutes'] ?? 30));
        $clinicSettings = $this->clinic->getClinicSettings();

        if (!$clinicSettings) {
            return [];
        }

        $slotInterval = max(5, (int) ($clinicSettings['slot_interval_minutes'] ?? 30));

        $dayOfWeek = (int) date('N', strtotime($date));
        $clinicRule = $this->clinic->getClinicScheduleRuleByDay($dayOfWeek);

        if ($clinicRule && (int) ($clinicRule['is_open'] ?? 1) !== 1) {
            return [];
        }

        $clinicOpen = $clinicRule['open_time'] ?? $clinicSettings['open_time'] ?? null;
        $clinicClose = $clinicRule['close_time'] ?? $clinicSettings['close_time'] ?? null;

        if (!$clinicOpen || !$clinicClose) {
            return [];
        }

        [$windowStart, $windowEnd] = $this->resolveDentistWindow($dentistId, $date);
        if ($windowStart === null || $windowEnd === null) {
            return [];
        }

        $finalStart = max($clinicOpen, $windowStart);
        $finalEnd = min($clinicClose, $windowEnd);

        $slots = [];
        $cursor = new DateTime($date . ' ' . $finalStart);
        $endBoundary = new DateTime($date . ' ' . $finalEnd);

        while ($cursor < $endBoundary) {
            $slotStart = clone $cursor;
            $slotEnd = clone $slotStart;
            $slotEnd->add(new DateInterval('PT' . $durationMinutes . 'M'));

            if ($slotEnd > $endBoundary) {
                break;
            }

            $startTime = $slotStart->format('H:i:s');
            $endTime = $slotEnd->format('H:i:s');

            if ($this->isSlotAvailable($date, $startTime, $endTime, $dentistId)) {
    $isPastSlot = !$this->isNotPastSlot($date, $startTime);

    if (!$isPastSlot || $includePastSlots) {
        $slots[] = [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'label' => $slotStart->format('h:i A'),
            'is_past' => $isPastSlot,
            'is_available' => !$isPastSlot,
        ];
    }
}

            $cursor->add(new DateInterval('PT' . $slotInterval . 'M'));
        }

        return $slots;
    }

    public function ensureSlotStillAvailable(
    string $date,
    string $startTime,
    int $serviceId,
    ?int $dentistId
): void {
        $service = $this->services->findById($serviceId);

        if (!$service) {
            throw new RuntimeException('Service not found.');
        }

        $durationMinutes = max(1, (int) ($service['estimated_duration_minutes'] ?? 30));

        $start = new DateTime($date . ' ' . $startTime);
        $end = clone $start;
        $end->add(new DateInterval('PT' . $durationMinutes . 'M'));

        if (!$this->isNotPastSlot($date, $start->format('H:i:s'))) {
            throw new RuntimeException('Selected slot is already in the past.');
        }

        if (!$this->isSlotAvailable(
            $date,
            $start->format('H:i:s'),
            $end->format('H:i:s'),
            $dentistId
        )) {
            throw new RuntimeException('Selected slot is no longer available.');
        }
    }

    public function getCalendarAvailability(string $date, int $serviceId): array
    {
        $result = [];
        $dentists = $this->dentists->getActiveDentists();

        foreach ($dentists as $dentist) {
            $slots = $this->getAvailableSlots($date, $serviceId, (int) $dentist['dentist_id']);
            $result[] = [
                'dentist_id' => (int) $dentist['dentist_id'],
                'dentist_name' => trim($dentist['first_name'] . ' ' . $dentist['last_name']),
                'available_count' => count($slots),
                'has_availability' => count($slots) > 0,
            ];
        }

        return $result;
    }

    private function isSlotAvailable(string $date, string $startTime, string $endTime, int $dentistId): bool
    {
        if ($this->clinic->hasClinicTimeBlock($date, $startTime, $endTime)) {
            return false;
        }

        if (!$this->isInsideDentistWindow($dentistId, $date, $startTime, $endTime)) {
            return false;
        }

        if ($this->hasUnavailableDateConflict($dentistId, $date, $startTime, $endTime)) {
            return false;
        }

        if ($this->appointments->hasDentistConflict($dentistId, $date, $startTime, $endTime)) {
            return false;
        }

        return true;
    }

    

    private function resolveDentistWindow(int $dentistId, string $date): array
    {
        $overrideMap = $this->dateOverrides->getMonthMap($dentistId, $date, $date);

        if (isset($overrideMap[$date])) {
            $override = $overrideMap[$date];

            if ((int) $override['is_available'] !== 1) {
                return [null, null];
            }

            return [
                $override['start_time'] ?: '00:00:00',
                $override['end_time'] ?: '23:59:59',
            ];
        }

        $dayOfWeek = (int) date('N', strtotime($date));
        $schedules = $this->dentistSchedules->getByDentistId($dentistId);

        foreach ($schedules as $schedule) {
            if ((int) $schedule['day_of_week'] === $dayOfWeek && (int) $schedule['is_available'] === 1) {
                return [
                    $schedule['start_time'],
                    $schedule['end_time'],
                ];
            }
        }

        return [null, null];
    }

    private function isInsideDentistWindow(int $dentistId, string $date, string $startTime, string $endTime): bool
    {
        [$windowStart, $windowEnd] = $this->resolveDentistWindow($dentistId, $date);

        if ($windowStart === null || $windowEnd === null) {
            return false;
        }

        return $startTime >= $windowStart && $endTime <= $windowEnd;
    }

    private function hasUnavailableDateConflict(int $dentistId, string $date, string $startTime, string $endTime): bool
    {
        $map = $this->unavailableDates->getMonthMap($dentistId, $date, $date);

        if (!isset($map[$date])) {
            return false;
        }

        $item = $map[$date];
        $blockStart = $item['start_time'];
        $blockEnd = $item['end_time'];

        if ($blockStart === null || $blockEnd === null) {
            return true;
        }

        return $blockStart < $endTime && $blockEnd > $startTime;
    }

    private function isNotPastSlot(string $date, string $startTime): bool
    {
       $tz = new \DateTimeZone('Asia/Manila');

$slot = new \DateTime($date . ' ' . $startTime, $tz);
$now = new \DateTime('now', $tz);

return $slot >= $now;
    }


    public function getWeekCalendarAvailability(string $weekStartDate): array
{
    $week = [];
    $start = new \DateTime($weekStartDate);

    for ($i = 0; $i < 7; $i++) {
        $date = clone $start;
        $date->modify("+{$i} day");

        $formattedDate = $date->format('Y-m-d');
        $dayOfWeek = (int) $date->format('N');

        $week[] = $this->buildDateAvailability($formattedDate, $dayOfWeek);
    }

    return $week;
}

private function buildDateAvailability(string $date, int $dayOfWeek): array
{
    $rule = $this->clinic->getClinicScheduleRuleByDay($dayOfWeek);

    if (!$rule || (int) ($rule['is_open'] ?? 0) !== 1) {
        return [
            'date' => $date,
            'label' => date('D d', strtotime($date)),
            'is_available' => false,
            'is_past' => $date < date('Y-m-d'),
            'tag' => 'Unavailable',
        ];
    }

    if ($date < date('Y-m-d')) {
        return [
            'date' => $date,
            'label' => date('D d', strtotime($date)),
            'is_available' => false,
            'is_past' => true,
            'tag' => 'Past',
        ];
    }

    $dentists = $this->dentists->getActiveDentists();

    foreach ($dentists as $dentist) {
        $dentistId = (int) $dentist['dentist_id'];

        if ($this->isDentistAvailableOnDate($dentistId, $date, $dayOfWeek)) {
            return [
                'date' => $date,
                'label' => date('D d', strtotime($date)),
                'is_available' => true,
                'is_past' => false,
                'tag' => 'Available',
            ];
        }
    }

    return [
        'date' => $date,
        'label' => date('D d', strtotime($date)),
        'is_available' => false,
        'is_past' => false,
        'tag' => 'Unavailable',
    ];
}

private function isDentistAvailableOnDate(int $dentistId, string $date, int $dayOfWeek): bool
{
    $overrideMap = $this->dateOverrides->getMonthMap($dentistId, $date, $date);

    if (isset($overrideMap[$date])) {
        return (int) $overrideMap[$date]['is_available'] === 1;
    }

    $unavailableMap = $this->unavailableDates->getMonthMap($dentistId, $date, $date);

    if (isset($unavailableMap[$date])) {
        return false;
    }

    $schedules = $this->dentistSchedules->getByDentistId($dentistId);

    foreach ($schedules as $schedule) {
        if (
            (int) $schedule['day_of_week'] === $dayOfWeek &&
            (int) $schedule['is_available'] === 1 &&
            !empty($schedule['start_time']) &&
            !empty($schedule['end_time'])
        ) {
            return true;
        }
    }

    return false;
}




public function availableSlots(): void
{
    $date = trim($_GET['date'] ?? '');
    $serviceId = (int) ($_GET['service_id'] ?? 0);

    header('Content-Type: application/json');

    if ($date === '' || $serviceId <= 0) {
        echo json_encode(['available_slots' => []]);
        return;
    }

    // get all active dentists
    $dentists = (new \App\Repositories\DentistLookupRepository())->getActiveDentists();

    $slots = [];

    foreach ($dentists as $dentist) {
    $dentistId = (int) $dentist['dentist_id'];

    $dentistSlots = $this->getAvailableSlots(
        $date,
        $serviceId,
        $dentistId
    );

    foreach ($dentistSlots as $slot) {
        $key = $slot['start_time'];

        if (!isset($slots[$key])) {
            $slots[$key] = $slot;
        }
    }
}

    // reindex array
    $slots = array_values($slots);

    echo json_encode([
        'available_slots' => $slots
    ]);
}

}