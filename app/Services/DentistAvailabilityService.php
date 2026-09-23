<?php

namespace App\Services;

use App\Repositories\ClinicRepository;
use App\Repositories\DentistDateOverrideRepository;
use App\Repositories\DentistScheduleRepository;
use App\Repositories\DentistUnavailableDateRepository;
use DateTime;
use RuntimeException;

class DentistAvailabilityService
{
    private DentistScheduleRepository $schedules;
    private DentistUnavailableDateRepository $unavailableDates;
    private DentistDateOverrideRepository $overrides;
    private ClinicRepository $clinic;

    private array $dayLabels = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public function __construct()
    {
        $this->schedules = new DentistScheduleRepository();
        $this->unavailableDates = new DentistUnavailableDateRepository();
        $this->overrides = new DentistDateOverrideRepository();
        $this->clinic = new ClinicRepository();
    }

    public function getDayLabels(): array
    {
        return $this->dayLabels;
    }

    public function getMergedSchedules(int $dentistId): array
    {
        $rawSchedules = $this->schedules->getByDentistId($dentistId);
        $indexed = [];

        foreach ($rawSchedules as $row) {
            $indexed[(int) $row['day_of_week']] = $row;
        }

        $defaults = [
            0 => ['day_of_week' => 0, 'is_available' => 0, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'max_patients' => 20],
            1 => ['day_of_week' => 1, 'is_available' => 1, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'max_patients' => 20],
            2 => ['day_of_week' => 2, 'is_available' => 1, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'max_patients' => 20],
            3 => ['day_of_week' => 3, 'is_available' => 1, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'max_patients' => 20],
            4 => ['day_of_week' => 4, 'is_available' => 1, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'max_patients' => 20],
            5 => ['day_of_week' => 5, 'is_available' => 1, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'max_patients' => 20],
            6 => ['day_of_week' => 6, 'is_available' => 1, 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'max_patients' => 20],
        ];

        foreach ($indexed as $day => $row) {
            $defaults[$day] = $row;
        }

        ksort($defaults);

        return $defaults;
    }

    public function saveWeeklySchedule(int $dentistId, array $days): void
    {
        foreach ($this->dayLabels as $day => $label) {
            $row = $days[$day] ?? [];

            $isAvailable = isset($row['is_available']) && (string) $row['is_available'] === '1';
            $startTime = $row['start_time'] ?? null;
            $endTime = $row['end_time'] ?? null;
            $maxPatients = isset($row['max_patients']) ? max(1, (int) $row['max_patients']) : 20;

            if ($isAvailable) {
                if (!$startTime || !$endTime) {
                    throw new RuntimeException($label . ' must have both start and end time when marked available.');
                }

                if (strtotime($endTime) <= strtotime($startTime)) {
                    throw new RuntimeException($label . ' end time must be after start time.');
                }
            }

            $this->schedules->updateOrCreate(
                $dentistId,
                $day,
                $isAvailable,
                $isAvailable ? $this->normalizeTime($startTime) : null,
                $isAvailable ? $this->normalizeTime($endTime) : null,
                $maxPatients
            );
        }
    }

    public function saveUnavailableDate(
        int $dentistId,
        string $date,
        ?string $startTime,
        ?string $endTime,
        ?string $reason
    ): void {
        $this->ensureDateTodayOrFuture($date);

        if ($startTime && $endTime && strtotime($endTime) <= strtotime($startTime)) {
            throw new RuntimeException('End time must be after start time.');
        }

        $this->unavailableDates->create(
            $dentistId,
            $date,
            $startTime ? $this->normalizeTime($startTime) : null,
            $endTime ? $this->normalizeTime($endTime) : null,
            $reason ?: 'Dentist unavailable'
        );
    }

    public function deleteUnavailableDate(int $dentistId, int $unavailableId): void
    {
        $item = $this->unavailableDates->findById($unavailableId);

        if (!$item || (int) $item['dentist_id'] !== $dentistId) {
            throw new RuntimeException('Unavailable date not found or not allowed.');
        }

        $this->unavailableDates->deleteById($unavailableId);
    }

    public function saveDateOverride(
        int $dentistId,
        string $date,
        bool $isAvailable,
        ?string $mode,
        ?string $startTime,
        ?string $endTime,
        ?string $reason
    ): void {
        $this->ensureDateTodayOrFuture($date);

        $finalStart = null;
        $finalEnd = null;

        if ($isAvailable) {
            if ($mode === 'custom') {
                if (!$startTime || !$endTime) {
                    throw new RuntimeException('Custom available override must include both start and end time.');
                }

                if (strtotime($endTime) <= strtotime($startTime)) {
                    throw new RuntimeException('End time must be after start time.');
                }

                $finalStart = $this->normalizeTime($startTime);
                $finalEnd = $this->normalizeTime($endTime);
            } else {
                [$finalStart, $finalEnd] = $this->resolveQuickAvailabilityRange($dentistId, $date, $mode ?: 'full_day');

                if (!$finalStart || !$finalEnd) {
                    throw new RuntimeException('No valid working range found for that date.');
                }
            }
        }

        $this->overrides->updateOrCreate(
            $dentistId,
            $date,
            $isAvailable,
            $finalStart,
            $finalEnd,
            $reason ?: $this->buildOverrideReason($isAvailable, $mode)
        );
    }

    public function deleteDateOverride(int $dentistId, int $overrideId): void
    {
        $item = $this->overrides->findById($overrideId);

        if (!$item || (int) $item['dentist_id'] !== $dentistId) {
            throw new RuntimeException('Date override not found or not allowed.');
        }

        $this->overrides->deleteById($overrideId);
    }

    public function buildMonthlySummary(int $dentistId, array $schedules, string $month): array
    {
        $monthStart = new DateTime($month . '-01');
        $monthEnd = new DateTime($monthStart->format('Y-m-t'));

        $overrideMap = $this->overrides->getMonthMap(
            $dentistId,
            $monthStart->format('Y-m-d'),
            $monthEnd->format('Y-m-d')
        );

        $unavailableMap = $this->unavailableDates->getMonthMap(
            $dentistId,
            $monthStart->format('Y-m-d'),
            $monthEnd->format('Y-m-d')
        );

        $availableDays = 0;
        $unavailableDays = 0;

        $cursor = clone $monthStart;

        while ($cursor <= $monthEnd) {
            $dateKey = $cursor->format('Y-m-d');
            $dayOfWeek = (int) $cursor->format('w');

            if (isset($overrideMap[$dateKey])) {
                if ((int) $overrideMap[$dateKey]['is_available'] === 1) {
                    $availableDays++;
                } else {
                    $unavailableDays++;
                }
            } elseif (isset($unavailableMap[$dateKey])) {
                $unavailableDays++;
            } else {
                $weekly = $schedules[$dayOfWeek] ?? null;

                if ($weekly && (int) ($weekly['is_available'] ?? 0) === 1) {
                    $availableDays++;
                } else {
                    $unavailableDays++;
                }
            }

            $cursor->modify('+1 day');
        }

        return [
            'month' => $monthStart->format('F Y'),
            'available_days' => $availableDays,
            'unavailable_days' => $unavailableDays,
            'date_blocks' => count($unavailableMap),
            'weekly_entries' => count($schedules),
            'available_overrides' => $this->overrides->countAvailableOverrides($dentistId),
            'unavailable_overrides' => $this->overrides->countUnavailableOverrides($dentistId),
        ];
    }

    public function getMonthMaps(int $dentistId, string $month): array
    {
        $monthStart = new DateTime($month . '-01');
        $monthEnd = new DateTime($monthStart->format('Y-m-t'));

        return [
            'monthlyUnavailableDates' => $this->unavailableDates->getMonthMap(
                $dentistId,
                $monthStart->format('Y-m-d'),
                $monthEnd->format('Y-m-d')
            ),
            'monthlyDateOverrides' => $this->overrides->getMonthMap(
                $dentistId,
                $monthStart->format('Y-m-d'),
                $monthEnd->format('Y-m-d')
            ),
        ];
    }

    public function paginateUnavailableDates(int $dentistId, int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;

        return [
            'items' => $this->unavailableDates->paginateByDentistId($dentistId, $perPage, $offset),
            'total' => $this->unavailableDates->countByDentistId($dentistId),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function paginateOverrides(int $dentistId, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;

        return [
            'items' => $this->overrides->paginateByDentistId($dentistId, $perPage, $offset),
            'total' => $this->overrides->countByDentistId($dentistId),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

public function findNextAvailableDate(int $dentistId, array $schedules, int $daysAhead = 90): ?string
{
    $start = new DateTime(date('Y-m-d'));
    $end = (clone $start)->modify('+' . $daysAhead . ' day');

    $maps = $this->getMonthMaps($dentistId, $start->format('Y-m'));

    $cursor = clone $start;

    while ($cursor <= $end) {
        $dateKey = $cursor->format('Y-m-d');
        $dayOfWeek = (int) $cursor->format('w');

        $monthKey = $cursor->format('Y-m');
        $monthMaps = $this->getMonthMaps($dentistId, $monthKey);

        $overrideMap = $monthMaps['monthlyDateOverrides'];
        $unavailableMap = $monthMaps['monthlyUnavailableDates'];

        if (isset($overrideMap[$dateKey])) {
            if ((int) ($overrideMap[$dateKey]['is_available'] ?? 0) === 1) {
                return $dateKey;
            }

            $cursor->modify('+1 day');
            continue;
        }

        if (isset($unavailableMap[$dateKey])) {
            $cursor->modify('+1 day');
            continue;
        }

        $weekly = $schedules[$dayOfWeek] ?? null;

        if ($weekly && (int) ($weekly['is_available'] ?? 0) === 1) {
            return $dateKey;
        }

        $cursor->modify('+1 day');
    }

    return null;
}




    private function resolveQuickAvailabilityRange(int $dentistId, string $date, string $mode): array
    {
        $schedules = $this->getMergedSchedules($dentistId);
        $dayOfWeek = (int) (new DateTime($date))->format('w');
        $schedule = $schedules[$dayOfWeek] ?? null;

        $range = $this->resolveBaseWorkingRange($date, $schedule);

        if (!$range) {
            return [null, null];
        }

        [$start, $end] = $range;

        if ($mode === 'full_day') {
            return [$start, $end];
        }

        $morningEnd = $this->resolveMorningEnd($date, $start, $end);
        $afternoonStart = $this->resolveAfternoonStart($date, $start, $end, $morningEnd);

        return match ($mode) {
            'morning' => [$start, $morningEnd],
            'afternoon' => [$afternoonStart, $end],
            default => [null, null],
        };
    }

    private function resolveBaseWorkingRange(string $date, ?array $schedule): ?array
    {
        if ($schedule && !empty($schedule['start_time']) && !empty($schedule['end_time']) && (int) ($schedule['is_available'] ?? 0) === 1) {
            if (strtotime($schedule['end_time']) > strtotime($schedule['start_time'])) {
                return [$schedule['start_time'], $schedule['end_time']];
            }
        }

        $clinic = $this->clinic->getClinicSettings();

        if ($clinic && !empty($clinic['open_time']) && !empty($clinic['close_time'])) {
            if (strtotime($clinic['close_time']) > strtotime($clinic['open_time'])) {
                return [$clinic['open_time'], $clinic['close_time']];
            }
        }

        return ['08:00:00', '17:00:00'];
    }

    private function resolveMorningEnd(string $date, string $start, string $end): string
    {
        $default = '12:00:00';

        if (strtotime($default) > strtotime($start) && strtotime($default) < strtotime($end)) {
            return $default;
        }

        $startTs = strtotime($date . ' ' . $start);
        $endTs = strtotime($date . ' ' . $end);
        $mid = (int) floor(($startTs + $endTs) / 2);

        return date('H:i:s', $mid);
    }

    private function resolveAfternoonStart(string $date, string $start, string $end, string $morningEnd): string
    {
        $default = '13:00:00';

        if (strtotime($default) > strtotime($start) && strtotime($default) < strtotime($end)) {
            return $default;
        }

        return $morningEnd;
    }

    private function buildOverrideReason(bool $isAvailable, ?string $mode): string
    {
        if (!$isAvailable) {
            return 'Unavailable override';
        }

        return match ($mode) {
            'morning' => 'Half day - morning',
            'afternoon' => 'Half day - afternoon',
            'custom' => 'Custom available override',
            default => 'Available override',
        };
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }

    private function ensureDateTodayOrFuture(string $date): void
    {
        $today = date('Y-m-d');

        if ($date < $today) {
            throw new RuntimeException('Date must be today or later.');
        }
    }
}