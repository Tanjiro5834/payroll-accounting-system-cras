<?php
namespace App\Service;

use App\Repository\DailySummaryRepository;
use App\Repository\EmployeeRepository;
use App\Repository\TimePunchRepository;
use DateTimeImmutable;
use DateInterval;
use Exception;

class DailySummaryService{
    private DailySummaryRepository $repository;
    private TimePunchRepository $punchRepository;
    private EmployeeRepository $employeeRepository;

    private const SHIFT_START   = '09:00:00';
    private const SHIFT_END     = '18:00:00';
    private const LUNCH_START   = '12:00:00';
    private const LUNCH_END     = '13:00:00';
    private const NIGHT_START   = '22:00:00';
    private const NIGHT_END     = '06:00:00';
    private const STANDARD_DAY  = 8.0;

    public function __construct(
        ?DailySummaryRepository $repository = null,
        ?TimePunchRepository    $punchRepository = null,
        ?EmployeeRepository     $employeeRepository = null
    ) {
        $this->repository  = $repository  ?? new DailySummaryRepository();
        $this->punchRepository  = $punchRepository ?? new TimePunchRepository();
        $this->employeeRepository = $employeeRepository ?? new EmployeeRepository();
    }

    public function computeForDate(int $employeeId, string $date): array {
        $punches = $this->repository->findByEmployeeAndDate($employeeId, $date);
        $summary = $this->buildSummary($employeeId, $date, $punches);

        $this->repository->upsert($summary);
        return $summary;
    }

    public function computeForDateRange(int $employeeId, string $start, string $end): int {
        $this->assertDateRange($start, $end);
        $count = 0;
        $currentDate = new DateTimeImmutable($start);
        $endDate = new DateTimeImmutable($end);

        while($currentDate <= $endDate){
            $this->computeForDate($employeeId, $current->format('Y-m-d'));
            $count++;
            $currentDate = $currenDate->add(new DateInterval('P1D'));
        }

        return $count;
    }

    public function computeForAllEmployees(string $date): int {
        $employees = $this->employeeRepository->findAllActive();
        $count = 0;

        foreach ($employees as $emp) {
            $this->computeForDate((int) $emp['id'], $date);
            $count++;
        }

        return $count;
    }

    public function computeForAllEmployeesInRange(string $start, string $end): int {
        $this->assertDateRange($start, $end);

        $employees = $this->employeeRepository->findAllActive();
        $count = 0;
        $current = new DateTimeImmutable($start);
        $endDt = new DateTimeImmutable($end);

        while ($current <= $endDt) {
            $date = $current->format('Y-m-d');
            foreach ($employees as $emp) {
                $this->computeForDate((int) $emp['id'], $date);
                $count++;
            }
            $current = $current->add(new DateInterval('P1D'));
        }

        return $count;
    }

    public function recompute(int $employeeId, string $date): array {
        try {
            $this->repository->deleteByEmployeeAndDate($employeeId, $date);
        } catch (Exception $e) {
            // Non-fatal if no row existed — log and continue
            error_log("recompute: delete skipped for {$employeeId}/{$date}: " . $e->getMessage());
        }

        return $this->computeForDate($employeeId, $date);
    }

    public function calculateRegularHours(array $punches): float {
        $pairs = $this->pairPunches($punches);
        if (empty($pairs)) return 0.0;

        $totalSeconds = 0;

        foreach ($pairs as [$in, $out]) {
            $shiftStart = new DateTimeImmutable($in->format('Y-m-d') . ' ' . self::SHIFT_START);
            $shiftEnd   = new DateTimeImmutable($in->format('Y-m-d') . ' ' . self::SHIFT_END);

            $start = max($in,  $shiftStart);
            $end   = min($out, $shiftEnd);

            if ($end <= $start) continue;

            $seconds = $end->getTimestamp() - $start->getTimestamp();

            // Subtract overlap with unpaid lunch
            $lunchStart = new DateTimeImmutable($in->format('Y-m-d') . ' ' . self::LUNCH_START);
            $lunchEnd   = new DateTimeImmutable($in->format('Y-m-d') . ' ' . self::LUNCH_END);

            $overlapStart = max($start, $lunchStart);
            $overlapEnd   = min($end,   $lunchEnd);

            if ($overlapEnd > $overlapStart) {
                $seconds -= ($overlapEnd->getTimestamp() - $overlapStart->getTimestamp());
            }

            $totalSeconds += max(0, $seconds);
        }

        return round($totalSeconds / 3600, 2);
    }

    public function calculateOvertimeHours(array $punches): float {
        $pairs = $this->pairPunches($punches);
        if (empty($pairs)) {
            return 0.0;
        }

        $totalSeconds = 0;

        foreach ($pairs as [$in, $out]) {
            $shiftEnd = new DateTimeImmutable($in->format('Y-m-d') . ' ' . self::SHIFT_END);

            $start = max($in, $shiftEnd);
            $end   = $out;

            if ($end > $start) {
                $totalSeconds += ($end->getTimestamp() - $start->getTimestamp());
            }
        }

        return round($totalSeconds / 3600, 2);
    }

    public function calculateNightDiffHours(array $punches): float {
        $pairs = $this->pairPunches($punches);
        if (empty($pairs)) {
            return 0.0;
        }

        $totalSeconds = 0;
        foreach ($pairs as [$in, $out]) {
            $totalSeconds += $this->overlapSecondsWithNightWindow($in, $out);
        }

        return round($totalSeconds / 3600, 2);
    }

    public function calculateLateMinutes(array $punches, string $date): int {
        $firstIn = $this->firstPunchOfType($punches, 'in');
        if ($firstIn === null) {
            return 0;   // no punches = absent, not late
        }

        $shiftStart = new DateTimeImmutable($date . ' ' . self::SHIFT_START);
        if ($firstIn <= $shiftStart) {
            return 0;
        }

        $diff = $firstIn->getTimestamp() - $shiftStart->getTimestamp();
        return (int) floor($diff / 60);
    }

    public function calculateUndertimeMinutes(array $punches, string $date): int {
        $firstIn = $this->firstPunchOfType($punches, 'in');
        if ($firstIn === null) {
            return 0;   // absent, not undertime
        }

        $lastOut = $this->lastPunchOfType($punches, 'out');
        if ($lastOut === null) {
            return 0;   // still clocked in / missing punch — handle separately
        }

        $shiftEnd = new DateTimeImmutable($date . ' ' . self::SHIFT_END);
        if ($lastOut >= $shiftEnd) {
            return 0;
        }

        $diff = $shiftEnd->getTimestamp() - $lastOut->getTimestamp();
        return (int) floor($diff / 60);
    }

    public function getSummary(int $employeeId, string $date): ?array {
        $rows = $this->repository->findByPeriod($employeeId, $date, $date);
        return $rows[0] ?? null;
    }

    public function getSummaryRange(int $employeeId, string $start, string $end): array {
        $this->assertDateRange($start, $end);
        return $this->repository->findByPeriod($employeeId, $start, $end);
    }

    private function pairPunches(array $punches): array{
        usort($punches, fn($a, $b) => strtotime($a['punch_time']) <=> strtotime($b['punch_time']));

        $pairs = [];
        $currentIn = null;

        foreach ($punches as $p) {
            $time = new DateTimeImmutable($p['punch_time']);

            if ($p['punch_type'] === 'in') {
                $currentIn = $time;   // overwrite if in-in without out
            } elseif ($p['punch_type'] === 'out' && $currentIn !== null) {
                // Handle cross-midnight: if out <= in, assume next day
                if ($time <= $currentIn) {
                    $time = $time->add(new DateInterval('P1D'));
                }
                $pairs[] = [$currentIn, $time];
                $currentIn = null;
            }
        }

        return $pairs;
    }

    private function firstPunchOfType(array $punches, string $type): ?DateTimeImmutable{
        $times = [];
        foreach ($punches as $p) {
            if ($p['punch_type'] === $type) {
                $times[] = new DateTimeImmutable($p['punch_time']);
            }
        }
        if (empty($times)) return null;
        sort($times);
        return $times[0];
    }

    private function lastPunchOfType(array $punches, string $type): ?DateTimeImmutable{
        $times = [];
        foreach ($punches as $p) {
            if ($p['punch_type'] === $type) {
                $times[] = new DateTimeImmutable($p['punch_time']);
            }
        }
        if (empty($times)) return null;
        sort($times);
        return $times[count($times) - 1];
    }
    /**
     * Seconds of overlap between [$in, $out] and the night window
     * (22:00 → 06:00 next day). Handles spans across multiple midnights.
     */
    private function overlapSecondsWithNightWindow(DateTimeImmutable $in, DateTimeImmutable $out): int
    {
        if ($out <= $in) {
            return 0;
        }

        $total = 0;

        // Walk day by day, checking 22:00→06:00 window for each day boundary
        $cursor = $in->setTime(0, 0, 0);
        $endDay = $out->setTime(0, 0, 0);

        while ($cursor <= $endDay) {
            // Night window that starts on $cursor at 22:00 and ends next day 06:00
            $nightStart = $cursor->setTime(22, 0, 0);
            $nightEnd   = $cursor->add(new DateInterval('P1D'))->setTime(6, 0, 0);

            $overlapStart = max($in,  $nightStart);
            $overlapEnd   = min($out, $nightEnd);

            if ($overlapEnd > $overlapStart) {
                $total += $overlapEnd->getTimestamp() - $overlapStart->getTimestamp();
            }

            // Also count the 00:00–06:00 window that "belongs" to this day
            $earlyStart = $cursor->setTime(0, 0, 0);
            $earlyEnd   = $cursor->setTime(6, 0, 0);

            $oStart = max($in,  $earlyStart);
            $oEnd   = min($out, $earlyEnd);

            if ($oEnd > $oStart) {
                $total += $oEnd->getTimestamp() - $oStart->getTimestamp();
            }

            $cursor = $cursor->add(new DateInterval('P1D'));
        }

        return $total;
    }

    private function assertDateRange(string $start, string $end): void
    {
        $s = DateTimeImmutable::createFromFormat('Y-m-d', $start);
        $e = DateTimeImmutable::createFromFormat('Y-m-d', $end);

        if (!$s || $s->format('Y-m-d') !== $start) {
            throw new \InvalidArgumentException("Invalid start date: {$start}");
        }
        if (!$e || $e->format('Y-m-d') !== $end) {
            throw new \InvalidArgumentException("Invalid end date: {$end}");
        }
        if ($s > $e) {
            throw new \InvalidArgumentException("Start date {$start} is after end date {$end}");
        }
    }

    private function buildSummary(int $employeeId, string $date, array $punches): array {
        return [
            'employee_id'       => $employeeId,
            'work_date'         => $date,
            'regular_hours'     => $this->calculateRegularHours($punches),
            'overtime_hours'    => $this->calculateOvertimeHours($punches),
            'night_diff_hours'  => $this->calculateNightDiffHours($punches),
            'late_minutes'      => $this->calculateLateMinutes($punches, $date),
            'undertime_minutes' => $this->calculateUndertimeMinutes($punches, $date),
            'is_rest_day'       => 0,   // caller/roster can override later
        ];
    }
}