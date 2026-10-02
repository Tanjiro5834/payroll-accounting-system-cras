<?php
namespace App\Service;

use App\Entity\TimePunch;
use App\Repository\DailySummaryRepository;
use App\Repository\TimePunchRepository;
use DateTimeImmutable;

// Turns raw punches into daily_summary rows (hours, late, undertime) — the input payroll reads.
class TimesheetService {
    private TimePunchRepository $punches;
    private DailySummaryRepository $summaries;
    private SystemSettingService $settings;

    public function __construct(
        ?TimePunchRepository $punches = null,
        ?DailySummaryRepository $summaries = null,
        ?SystemSettingService $settings = null
    ) {
        $this->punches   = $punches   ?? new TimePunchRepository();
        $this->summaries = $summaries ?? new DailySummaryRepository();
        $this->settings  = $settings  ?? new SystemSettingService();
    }

    // Rebuilds daily_summary for every employee-day with punches in the range.
    // 1 read for all punches + 1 write per 500 rows. Returns the number of days summarized.
    public function summarizePeriod(string $start, string $end): int {
        $rows = [];
        foreach ($this->groupByEmployeeDay($this->punches->findByDateRange($start, $end)) as $employeeId => $days) {
            foreach ($days as $date => $times) {
                $rows[] = $this->summarizeDay($employeeId, $date, $times);
            }
        }

        if ($rows) {
            $this->summaries->upsertMany($rows);
        }
        return count($rows);
    }

    // Same as summarizePeriod() for one employee — cheap enough to run on every dashboard load,
    // so the employee's stats reflect today's punches without waiting for payroll to be computed.
    public function summarizeEmployeePeriod(int $employeeId, string $start, string $end): int {
        $rows = [];
        $grouped = $this->groupByEmployeeDay($this->punches->findByEmployeeAndDateRange($employeeId, $start, $end));
        foreach ($grouped[$employeeId] ?? [] as $date => $times) {
            $rows[] = $this->summarizeDay($employeeId, $date, $times);
        }

        if ($rows) {
            $this->summaries->upsertMany($rows);
        }
        return count($rows);
    }

    /**
     * Rules (schedule from system settings):
     * - Regular hours = time worked INSIDE the schedule (AM: work start → lunch start, PM: lunch end → work end),
     *   capped at regular_hours. Coming early or staying late is not paid unless punched as OT.
     * - Late = minutes after the scheduled start, counted only when beyond the grace threshold.
     *   Arriving within the grace period counts as on time and is paid in full.
     * - Undertime = minutes left before the scheduled end.
     * - A half-day with a missing IN or OUT punch counts 0 hours for that half.
     * - OT = OT_OUT − OT_IN. Night diff = any worked minutes between 22:00 and 06:00.
     *
     * @param array<string,string> $times punch_type => 'Y-m-d H:i:s'
     */
    public function summarizeDay(int $employeeId, string $date, array $times): array {
        $at = fn(string $hhmm) => new DateTimeImmutable("{$date} {$hhmm}");
        $punch = fn(string $type) => isset($times[$type]) ? new DateTimeImmutable($times[$type]) : null;

        $workStart  = $at($this->settings->getWorkStart());
        $lunchStart = $at($this->settings->getLunchStart());
        $lunchEnd   = $at($this->settings->getLunchEnd());
        $workEnd    = $at($this->settings->getWorkEnd());
        $grace      = $this->settings->getLateThreshold();

        $intervals = [
            'am' => [$punch('AM_IN'), $punch('AM_OUT')],
            'pm' => [$punch('PM_IN'), $punch('PM_OUT')],
            'ot' => [$punch('OT_IN'), $punch('OT_OUT')],
        ];

        $regularMinutes = $this->overlapMinutes($this->forgiveGrace($intervals['am'], $workStart, $grace), $workStart, $lunchStart)
                        + $this->overlapMinutes($this->forgiveGrace($intervals['pm'], $lunchEnd, $grace), $lunchEnd, $workEnd);
        $regularMinutes = min($regularMinutes, (int) round($this->settings->getRegularHours() * 60));

        $nightMinutes = 0;
        foreach ($intervals as $interval) {
            $nightMinutes += $this->nightMinutes($interval, $date);
        }

        return [
            'employee_id'       => $employeeId,
            'work_date'         => $date,
            'regular_hours'     => round($regularMinutes / 60, 2),
            'overtime_hours'    => round($this->durationMinutes($intervals['ot']) / 60, 2),
            'night_diff_hours'  => round($nightMinutes / 60, 2),
            'late_minutes'      => $this->lateMinutes($punch('AM_IN'), $workStart, $grace)
                                 + $this->lateMinutes($punch('PM_IN'), $lunchEnd, $grace),
            'undertime_minutes' => $this->earlyMinutes($punch('AM_OUT'), $lunchStart)
                                 + $this->earlyMinutes($punch('PM_OUT'), $workEnd),
            'is_rest_day'       => $this->isRestDay($date) ? 1 : 0,
        ];
    }

    /** @param TimePunch[] $punches  @return array<int, array<string, array<string,string>>> */
    private function groupByEmployeeDay(array $punches): array {
        $grouped = [];
        foreach ($punches as $p) {
            // Punches arrive ordered by time; keep the first of each type.
            $grouped[(int) $p->getEmployeeId()][$p->getWorkDate()][$p->getPunchType()] ??= $p->getPunchTime();
        }
        return $grouped;
    }

    // An IN within the grace period is treated as the scheduled start, so those minutes are paid.
    private function forgiveGrace(array $interval, DateTimeImmutable $scheduled, int $grace): array {
        [$in, $out] = $interval;
        if ($in && $in > $scheduled && $this->minutesBetween($scheduled, $in) <= $grace) {
            $in = $scheduled;
        }
        return [$in, $out];
    }

    private function overlapMinutes(array $interval, DateTimeImmutable $from, DateTimeImmutable $to): int {
        [$in, $out] = $interval;
        if (!$in || !$out) {
            return 0;
        }
        return $this->minutesBetween(max($in, $from), min($out, $to));
    }

    private function durationMinutes(array $interval): int {
        [$in, $out] = $interval;
        return ($in && $out) ? $this->minutesBetween($in, $out) : 0;
    }

    // Night window 22:00–06:00: the early-morning part of this date and the late-night part.
    private function nightMinutes(array $interval, string $date): int {
        [$in, $out] = $interval;
        if (!$in || !$out) {
            return 0;
        }

        $dayStart = new DateTimeImmutable("{$date} 00:00");
        return $this->minutesBetween(max($in, $dayStart), min($out, $dayStart->modify('+6 hours')))
             + $this->minutesBetween(max($in, $dayStart->modify('+22 hours')), min($out, $dayStart->modify('+30 hours')));
    }

    private function lateMinutes(?DateTimeImmutable $in, DateTimeImmutable $scheduled, int $grace): int {
        if (!$in) {
            return 0;
        }
        $late = $this->minutesBetween($scheduled, $in);
        return $late > $grace ? $late : 0;
    }

    private function earlyMinutes(?DateTimeImmutable $out, DateTimeImmutable $scheduledEnd): int {
        return $out ? $this->minutesBetween($out, $scheduledEnd) : 0;
    }

    // Whole minutes from $from to $to; 0 when $to is not after $from.
    private function minutesBetween(DateTimeImmutable $from, DateTimeImmutable $to): int {
        return $to > $from ? intdiv($to->getTimestamp() - $from->getTimestamp(), 60) : 0;
    }

    private function isRestDay(string $date): bool {
        $day = strtolower((new DateTimeImmutable($date))->format('D'));
        return !in_array($day, $this->settings->getWorkDays(), true);
    }
}
