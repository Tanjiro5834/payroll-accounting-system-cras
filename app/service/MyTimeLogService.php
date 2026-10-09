<?php
namespace App\Service;

use App\Repository\DailySummaryRepository;
use App\Repository\EmployeeRepository;
use App\Repository\HolidayRepository;
use App\Repository\SundayDutyRepository;
use App\Repository\TimePunchRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

// One employee's month, day by day: punches, hours, what was charged as late and why, and any flags.
// Every day of the month up to today is listed, so absences show up as rows too.
class MyTimeLogService {
    private const TIMEZONE = 'Asia/Manila';
    private const PUNCH_ORDER = ['AM_IN', 'AM_OUT', 'PM_IN', 'PM_OUT', 'OT_IN', 'OT_OUT'];

    private EmployeeRepository $employees;
    private TimePunchRepository $punches;
    private DailySummaryRepository $daily;
    private HolidayRepository $holidays;
    private SundayDutyRepository $duty;
    private TimesheetService $timesheet;
    private SystemSettingService $settings;
    private DateTimeZone $tz;

    public function __construct() {
        $this->employees = new EmployeeRepository();
        $this->punches   = new TimePunchRepository();
        $this->daily     = new DailySummaryRepository();
        $this->holidays  = new HolidayRepository();
        $this->duty      = new SundayDutyRepository();
        $this->timesheet = new TimesheetService();
        $this->settings  = new SystemSettingService();
        $this->tz        = new DateTimeZone(self::TIMEZONE);
    }

    public function month(int $employeeId, string $month): array {
        $employee = $this->employees->findById($employeeId);
        if (!$employee) {
            throw new DomainException('Employee record not found.');
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m', $month, $this->tz);
        if (!$start || $start->format('Y-m') !== $month) {
            throw new InvalidArgumentException('month must be YYYY-MM.');
        }

        $today = new DateTimeImmutable('today', $this->tz);
        $hired = $employee->getDateHired() ? new DateTimeImmutable($employee->getDateHired(), $this->tz) : null;
        $first = $hired && $hired > $start ? $hired : $start;
        $last  = min($start->modify('last day of this month'), $today);

        $schedule = [
            'work_start'  => $this->settings->getWorkStart(),
            'lunch_start' => $this->settings->getLunchStart(),
            'lunch_end'   => $this->settings->getLunchEnd(),
            'work_end'    => $this->settings->getWorkEnd(),
            'threshold'   => $this->settings->getLateThreshold(),
        ];

        if ($last < $first) {
            return ['month' => $month, 'schedule' => $schedule, 'totals' => $this->totals([]), 'days' => []];
        }
        [$from, $to] = [$first->format('Y-m-d'), $last->format('Y-m-d')];

        // Same numbers payroll uses: rebuild this employee's daily totals from their punches first.
        $this->timesheet->summarizeEmployeePeriod($employeeId, $from, $to);

        $summaries = [];
        foreach ($this->daily->findByPeriod($employeeId, $from, $to) as $row) {
            $summaries[$row['work_date']] = $row;
        }
        $punches = [];
        foreach ($this->punches->findLogRows($employeeId, $from, $to) as $row) {
            $punches[$row['work_date']][$row['punch_type']] ??= $row; // first of each type, like the timesheet
        }
        $holidays = [];
        foreach ($this->holidays->findByDateRange($from, $to) as $h) {
            $holidays[$h->getHolidayDate()] = ['name' => $h->getName(), 'type' => $h->getType()];
        }
        $rostered = $this->duty->assignedDates($employeeId, $from, $to);
        $workDays = $this->settings->getWorkDays();
        $todayYmd = $today->format('Y-m-d');

        $days = [];
        for ($d = $last; $d >= $first; $d = $d->modify('-1 day')) {
            $date = $d->format('Y-m-d');
            $days[] = $this->day(
                $date,
                in_array(strtolower($d->format('D')), $workDays, true),
                $date === $todayYmd,
                $punches[$date] ?? [],
                $summaries[$date] ?? null,
                $holidays[$date] ?? null,
                isset($rostered[$date]),
                $schedule
            );
        }

        return ['month' => $month, 'schedule' => $schedule, 'totals' => $this->totals($days), 'days' => $days];
    }

    private function day(string $date, bool $isWorkDay, bool $isToday, array $punches, ?array $summary,
                         ?array $holiday, bool $onDuty, array $schedule): array {
        $times = [];
        $flags = [];
        foreach (self::PUNCH_ORDER as $type) {
            $p = $punches[$type] ?? null;
            $times[$type] = $p ? substr((string) $p['punch_time'], 11, 5) : null;
            if ($p && (int) $p['is_flagged'] === 1) {
                $flags[] = [
                    'punch'    => $type,
                    'reason'   => $p['flag_reason'],
                    'reviewed' => $p['reviewed_at'] !== null,
                ];
            }
        }

        $worked = $punches !== [];
        $status = match (true) {
            $worked && $isToday                                                   => 'today',
            $worked && $holiday !== null && $holiday['type'] !== 'special_working' => 'holiday_worked',
            $worked && !$isWorkDay                                                => 'sunday_duty',
            $worked                                                               => 'worked',
            $holiday !== null && $holiday['type'] !== 'special_working'           => 'holiday',
            !$isWorkDay && $onDuty                                                => 'missed_duty',
            !$isWorkDay                                                           => 'rest_day',
            $isToday                                                              => 'today',
            default                                                               => 'absent',
        };

        $late = (int) ($summary['late_minutes'] ?? 0);

        return [
            'date'          => $date,
            'status'        => $status,
            'holiday'       => $holiday,
            'on_duty'       => $onDuty,
            'times'         => $times,
            'regular_hours' => $summary ? (float) $summary['regular_hours'] : 0.0,
            'ot_hours'      => $summary ? (float) $summary['overtime_hours'] : 0.0,
            'late_charged'  => $late,
            'undertime'     => (int) ($summary['undertime_minutes'] ?? 0),
            'notes'         => $worked ? $this->notes($times, $late, (int) ($summary['undertime_minutes'] ?? 0), $schedule, $isToday) : [],
            'flags'         => $flags,
        ];
    }

    // Plain-language reasons behind the numbers, e.g. "In at 8:20, 20 min late → 1 hr deducted".
    private function notes(array $times, int $lateCharged, int $undertime, array $schedule, bool $isToday): array {
        $notes = [];
        foreach ([['AM_IN', 'work_start', 'Time in'], ['PM_IN', 'lunch_end', 'After lunch']] as [$type, $key, $label]) {
            if (!$times[$type]) {
                continue;
            }
            $late = $this->minutesBetween($schedule[$key], $times[$type]);
            if ($late <= 0) {
                continue;
            }
            $notes[] = $late < $schedule['threshold']
                ? "{$label}: in at {$this->clock($times[$type])}, {$late} min late. Under {$schedule['threshold']} min, so not counted."
                : "{$label}: in at {$this->clock($times[$type])}, {$this->duration($late)} late → " . $this->hours($this->charged($late)) . ' deducted.';
        }

        if ($isToday) {
            $notes[] = 'Today is still in progress. Hours are final after your last time-out.';
            return $notes;
        }
        if ($undertime > 0) {
            $notes[] = "Left {$this->duration($undertime)} before the end of the schedule (unpaid).";
        }
        $otAfter = $times['PM_OUT'] ? $this->minutesBetween($schedule['work_end'], $times['PM_OUT']) : 0;
        if ($otAfter > 0 && !$times['AM_OUT'] && !$times['PM_IN']) {
            $notes[] = "Out at {$this->clock($times['PM_OUT'])}: {$this->duration($otAfter)} overtime.";
        }
        // Two-punch days pair TIME IN with TIME OUT; older four-punch days pair each half.
        $pairs = ($times['AM_OUT'] || $times['PM_IN'])
            ? [['AM_IN', 'AM_OUT', 'Morning'], ['PM_IN', 'PM_OUT', 'Afternoon']]
            : [['AM_IN', 'PM_OUT', 'Day']];
        $pairs[] = ['OT_IN', 'OT_OUT', 'Overtime'];
        foreach ($pairs as [$in, $out, $label]) {
            if ($times[$in] xor $times[$out]) {
                $notes[] = "{$label}: " . ($times[$in] ? 'no time-out' : 'no time-in') . ' recorded, so it counts 0 hours until corrected.';
            }
        }
        if ($times['AM_IN'] && $times['PM_OUT'] && !$times['AM_OUT'] && !$times['PM_IN']) {
            $notes[] = 'Lunch (1 hr) is unpaid and deducted automatically.';
        }
        if ($lateCharged === 0 && !$notes) {
            $notes[] = 'On time.';
        }
        return $notes;
    }

    private function charged(int $late): int {
        return max(1, intdiv($late, 60)) * 60;
    }

    private function totals(array $days): array {
        $t = ['days_worked' => 0, 'absent' => 0, 'late_days' => 0, 'late_charged' => 0,
              'ot_hours' => 0.0, 'undertime' => 0, 'regular_hours' => 0.0, 'open_flags' => 0];
        foreach ($days as $d) {
            $t['days_worked']   += in_array($d['status'], ['worked', 'sunday_duty', 'holiday_worked'], true) ? 1 : 0;
            $t['absent']        += in_array($d['status'], ['absent', 'missed_duty'], true) ? 1 : 0;
            $t['late_days']     += $d['late_charged'] > 0 ? 1 : 0;
            $t['late_charged']  += $d['late_charged'];
            $t['ot_hours']      += $d['ot_hours'];
            $t['undertime']     += $d['undertime'];
            $t['regular_hours'] += $d['regular_hours'];
            foreach ($d['flags'] as $f) {
                $t['open_flags'] += $f['reviewed'] ? 0 : 1;
            }
        }
        $t['ot_hours']      = round($t['ot_hours'], 2);
        $t['regular_hours'] = round($t['regular_hours'], 2);
        return $t;
    }

    private function minutesBetween(string $fromHm, string $toHm): int {
        [$fh, $fm] = array_map('intval', explode(':', $fromHm));
        [$th, $tm] = array_map('intval', explode(':', $toHm));
        return ($th * 60 + $tm) - ($fh * 60 + $fm);
    }

    private function clock(string $hm): string {
        return DateTimeImmutable::createFromFormat('!H:i', $hm)->format('g:i A');
    }

    private function duration(int $min): string {
        $h = intdiv($min, 60);
        $m = $min % 60;
        return trim(($h ? "{$h} hr " : '') . ($m ? "{$m} min" : ''));
    }

    private function hours(int $min): string {
        $h = intdiv($min, 60);
        return $h . ' hr' . ($h === 1 ? '' : 's');
    }
}
