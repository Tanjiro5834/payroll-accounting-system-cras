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
     * - Late (owner's rule), from TIME IN vs work start (four-punch days: also PM_IN vs lunch end):
     *     under late_threshold (10 min)  → on time, paid in full
     *     from the threshold             → charged whole hours, at least 1: 10–119 min = 1 h, 120–179 = 2 h, …
     *   The charged hours are unpaid even if they arrived partway into the hour (20 min late = 1 h off),
     *   and minutes past the charged hours are forgiven (9:10 for an 8:00 start = 1 h off, not 1 h 10 m).
     *   late_minutes stores the CHARGED minutes, i.e. what came off their pay.
     * - Undertime = minutes left before the scheduled end.
     * - Normal day = 2 punches (TIME IN = AM_IN, TIME OUT = PM_OUT); lunch is deducted automatically, unpaid.
     *   A day with only a TIME IN (no TIME OUT) counts 0 hours until the punch is corrected.
     * - Four-punch days (AM_OUT/PM_IN present, older data): a half with a missing IN or OUT counts 0 hours.
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
        $threshold  = $this->settings->getLateThreshold();
        $lunch      = [$lunchStart, $lunchEnd];

        $ot = [$punch('OT_IN'), $punch('OT_OUT')];

        // Two-punch day (the normal case, like the old bundy): TIME IN = AM_IN, TIME OUT = PM_OUT, no lunch punches.
        // Lunch is always unpaid, so it is cut out of the span automatically.
        // Days that do have AM_OUT / PM_IN (older data) keep the four-punch calculation.
        $fourPunch = isset($times['AM_OUT']) || isset($times['PM_IN']);

        if ($fourPunch) {
            [$am, $amLate] = $this->chargeLate([$punch('AM_IN'), $punch('AM_OUT')], $workStart, $lunchStart, $threshold);
            [$pm, $pmLate] = $this->chargeLate([$punch('PM_IN'), $punch('PM_OUT')], $lunchEnd, $workEnd, $threshold);
            $regularMinutes = $this->overlapMinutes($am, $workStart, $lunchStart)
                            + $this->overlapMinutes($pm, $lunchEnd, $workEnd);
            $late      = $amLate + $pmLate;
            $undertime = $this->earlyMinutes($punch('AM_OUT'), $lunchStart)
                       + $this->earlyMinutes($punch('PM_OUT'), $workEnd);
            $worked    = [$am, $pm, $ot];
        } else {
            [$span, $late] = $this->chargeLate([$punch('AM_IN'), $punch('PM_OUT')], $workStart, $workEnd, $threshold);
            $regularMinutes = $this->overlapMinutes($span, $workStart, $workEnd)
                            - $this->overlapMinutes($span, $lunchStart, $lunchEnd);
            $out = $punch('PM_OUT');
            $undertime = $out && $out < $workEnd
                ? $this->minutesBetween($out, $workEnd) - $this->overlapMinutes([$out, $workEnd], $lunchStart, $lunchEnd)
                : 0;
            $worked = [$span, $ot];
        }

        $regularMinutes = max(0, min($regularMinutes, (int) round($this->settings->getRegularHours() * 60)));

        $nightMinutes = 0;
        foreach ($worked as $interval) {
            $nightMinutes += $this->nightMinutes($interval, $date);
        }

        return [
            'employee_id'       => $employeeId,
            'work_date'         => $date,
            'regular_hours'     => round($regularMinutes / 60, 2),
            'overtime_hours'    => round($this->durationMinutes($ot) / 60, 2),
            'night_diff_hours'  => round($nightMinutes / 60, 2),
            'late_minutes'      => $late,
            'undertime_minutes' => $undertime,
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

    /**
     * Moves a late IN to the end of the charged hours, so overlapMinutes() pays from there.
     * @return array{0: array, 1: int} [adjusted interval, charged minutes]
     */
    private function chargeLate(array $interval, DateTimeImmutable $scheduled, DateTimeImmutable $halfEnd, int $threshold): array {
        [$in, $out] = $interval;
        if (!$in || $in <= $scheduled) {
            return [$interval, 0]; // absent half, on time, or early (early minutes aren't paid anyway)
        }

        $late = $this->minutesBetween($scheduled, $in);
        if ($late === 0 || $late < $threshold) {
            return [[$scheduled, $out], 0];
        }

        $charged = min(max(1, intdiv($late, 60)) * 60, $this->minutesBetween($scheduled, $halfEnd));
        return [[$scheduled->modify("+{$charged} minutes"), $out], $charged];
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
