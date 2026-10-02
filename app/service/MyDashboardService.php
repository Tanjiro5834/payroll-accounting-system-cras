<?php
namespace App\Service;

use App\Repository\EmployeeRepository;
use App\Repository\MyDashboardRepository;
use App\Repository\PayrollDeductionRepository;
use App\Repository\RateHistoryRepository;
use App\Repository\DailySummaryRepository;
use App\Repository\TimePunchRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

// Everything an employee sees about themselves. $employeeId always comes from the session.
class MyDashboardService {
    private const TIMEZONE     = 'Asia/Manila';
    private const TREND_MONTHS = 6;

    private EmployeeRepository $employees;
    private MyDashboardRepository $repository;
    private PayrollDeductionRepository $deductions;
    private RateHistoryRepository $rates;
    private DailySummaryRepository $daily;
    private TimePunchRepository $punches;
    private TimesheetService $timesheet;
    private SystemSettingService $settings;
    private PayrollService $payroll;
    private DateTimeZone $tz;

    public function __construct() {
        $this->employees  = new EmployeeRepository();
        $this->repository = new MyDashboardRepository();
        $this->deductions = new PayrollDeductionRepository();
        $this->rates      = new RateHistoryRepository();
        $this->daily      = new DailySummaryRepository();
        $this->punches    = new TimePunchRepository();
        $this->timesheet  = new TimesheetService();
        $this->settings   = new SystemSettingService();
        $this->payroll    = new PayrollService();
        $this->tz         = new DateTimeZone(self::TIMEZONE);
    }

    public function summary(int $employeeId): array {
        $employee = $this->requireEmployee($employeeId);
        $today    = $this->today();

        // Refresh this employee's daily totals from raw punches so today's lates/OT show up immediately.
        $trendStart = $today->modify('first day of this month')->modify('-' . (self::TREND_MONTHS - 1) . ' months');
        $this->timesheet->summarizeEmployeePeriod($employeeId, $trendStart->format('Y-m-d'), $today->format('Y-m-d'));

        $thisMonth = $this->monthStats($employeeId, $today->modify('first day of this month'), $today);
        $lastStart = $today->modify('first day of last month');
        $lastMonth = $this->monthStats($employeeId, $lastStart, $lastStart->modify('last day of this month'));

        $payslips  = $this->repository->findReleasedPayslips($employeeId, 1);
        $latest    = $payslips[0] ?? null;
        $history   = $this->rates->findByEmployee($employeeId);
        $year      = (int) $today->format('Y');
        $thirteenth = $this->repository->findThirteenthMonth($employeeId, $year);
        $streak    = $this->onTimeStreak($employeeId, $today);

        $hourly = $this->hourlyRate($employee);
        $hours  = $this->settings->getRegularHours();

        return [
            'profile' => [
                'id'            => (int) $employee['id'],
                'full_name'     => $employee['full_name'],
                'role'          => $employee['role'],
                'photo'         => $employee['profile_photo_url'],
                'date_hired'    => $employee['date_hired'],
                'tenure'        => $this->tenure($employee['date_hired'], $today),
                'pay_frequency' => $employee['pay_frequency'],
                'is_active'     => (bool) $employee['is_active'],
                'gov_ids'       => [
                    'SSS'        => $this->mask($employee['sss_number']),
                    'PhilHealth' => $this->mask($employee['philhealth_number']),
                    'Pag-IBIG'   => $this->mask($employee['pagibig_number']),
                    'TIN'        => $this->mask($employee['tin_number']),
                ],
            ],
            'pay' => [
                'hourly_rate'   => $hourly,
                'daily_rate'    => $hourly === null ? null : bcmul($hourly, (string) $hours, 2),
                'monthly_rate'  => $employee['monthly_rate'],
                'regular_hours' => $hours,
                'next_payday'   => $this->nextPayday((string) $employee['pay_frequency'], $today),
            ],
            'month'          => $thisMonth + ['label' => $today->format('F Y')],
            'prev_month'     => $lastMonth + ['label' => $lastStart->format('F Y')],
            'on_time_streak' => $streak,
            'latest_payslip' => $latest,
            'ytd_paid'       => $this->repository->sumPaidNetForYear($employeeId, $year),
            'year'           => $year,
            'thirteenth'     => $thirteenth,
            'insights'       => $this->insights($thisMonth, $lastMonth, $streak, $latest, $history, $today),
        ];
    }

    // Day-by-day attendance for one month (YYYY-MM), with first IN / last OUT per day.
    public function attendance(int $employeeId, string $month): array {
        $this->requireEmployee($employeeId);
        $start = DateTimeImmutable::createFromFormat('!Y-m', $month, $this->tz);
        if (!$start || $start->format('Y-m') !== $month) {
            throw new InvalidArgumentException('month must be YYYY-MM.');
        }
        $end = min($start->modify('last day of this month'), $this->today());
        if ($end < $start) {
            return ['month' => $month, 'days' => []];
        }
        [$from, $to] = [$start->format('Y-m-d'), $end->format('Y-m-d')];

        $this->timesheet->summarizeEmployeePeriod($employeeId, $from, $to);

        $times = [];
        foreach ($this->punches->findByEmployeeAndDateRange($employeeId, $from, $to) as $p) {
            $date = $p->getWorkDate();
            $at   = substr((string) $p->getPunchTime(), 11, 5);
            $times[$date]['first_in'] ??= str_ends_with($p->getPunchType(), '_IN') ? $at : null;
            if (str_ends_with($p->getPunchType(), '_OUT')) {
                $times[$date]['last_out'] = $at;
            }
            $times[$date]['punches'][] = ['type' => $p->getPunchType(), 'time' => $at];
        }

        $days = array_map(fn(array $d) => [
            'date'              => $d['work_date'],
            'regular_hours'     => $d['regular_hours'],
            'overtime_hours'    => $d['overtime_hours'],
            'night_diff_hours'  => $d['night_diff_hours'],
            'late_minutes'      => (int) $d['late_minutes'],
            'undertime_minutes' => (int) $d['undertime_minutes'],
            'is_rest_day'       => (bool) $d['is_rest_day'],
            'first_in'          => $times[$d['work_date']]['first_in'] ?? null,
            'last_out'          => $times[$d['work_date']]['last_out'] ?? null,
            'punches'           => $times[$d['work_date']]['punches'] ?? [],
        ], $this->daily->findByPeriod($employeeId, $from, $to));

        return ['month' => $month, 'days' => $days];
    }

    // Last N months of late/OT/undertime totals, zero-filled so the chart has a bar per month.
    public function trend(int $employeeId): array {
        $this->requireEmployee($employeeId);
        $first = $this->today()->modify('first day of this month')->modify('-' . (self::TREND_MONTHS - 1) . ' months');

        $rows = [];
        foreach ($this->repository->monthlyTrend($employeeId, $first->format('Y-m-d')) as $r) {
            $rows[$r['month']] = $r;
        }

        $out = [];
        for ($i = 0; $i < self::TREND_MONTHS; $i++) {
            $m = $first->modify("+{$i} months");
            $r = $rows[$m->format('Y-m')] ?? [];
            $out[] = [
                'month'          => $m->format('Y-m'),
                'label'          => $m->format('M'),
                'days_worked'    => (int) ($r['days_worked'] ?? 0),
                'late_days'      => (int) ($r['late_days'] ?? 0),
                'late_minutes'   => (int) ($r['late_minutes'] ?? 0),
                'ot_days'        => (int) ($r['ot_days'] ?? 0),
                'ot_hours'       => (float) ($r['ot_hours'] ?? 0),
            ];
        }
        return $out;
    }

    public function payslips(int $employeeId): array {
        $this->requireEmployee($employeeId);
        return $this->repository->findReleasedPayslips($employeeId);
    }

    public function payslip(int $employeeId, int $payrollId): array {
        $slip = $this->repository->findReleasedPayslip($employeeId, $payrollId);
        if (!$slip) {
            throw new DomainException('Payslip not found.');
        }
        $slip['deductions'] = array_map(fn(array $d) => [
            'code'   => $d['deduction_code'],
            'name'   => $d['deduction_name'],
            'amount' => $d['amount'],
        ], $this->deductions->findByPayrollPeriod($payrollId));

        return $slip;
    }

    public function rateHistory(int $employeeId): array {
        $this->requireEmployee($employeeId);
        return $this->rates->findByEmployee($employeeId);
    }

    private function monthStats(int $employeeId, DateTimeImmutable $from, DateTimeImmutable $to): array {
        $stats = ['days_worked' => 0, 'late_days' => 0, 'late_minutes' => 0, 'ot_days' => 0,
                  'ot_hours' => 0.0, 'undertime_days' => 0, 'undertime_minutes' => 0];

        foreach ($this->daily->findByPeriod($employeeId, $from->format('Y-m-d'), $to->format('Y-m-d')) as $d) {
            $worked = (float) $d['regular_hours'] > 0 || (float) $d['overtime_hours'] > 0;
            $stats['days_worked']       += $worked ? 1 : 0;
            $stats['late_days']         += (int) $d['late_minutes'] > 0 ? 1 : 0;
            $stats['late_minutes']      += (int) $d['late_minutes'];
            $stats['ot_days']           += (float) $d['overtime_hours'] > 0 ? 1 : 0;
            $stats['ot_hours']          += (float) $d['overtime_hours'];
            $stats['undertime_days']    += (int) $d['undertime_minutes'] > 0 ? 1 : 0;
            $stats['undertime_minutes'] += (int) $d['undertime_minutes'];
        }
        $stats['ot_hours'] = round($stats['ot_hours'], 2);
        return $stats;
    }

    // Consecutive worked days without a late, counting back from the most recent worked day.
    private function onTimeStreak(int $employeeId, DateTimeImmutable $today): int {
        $rows = $this->daily->findByPeriod($employeeId, $today->modify('-90 days')->format('Y-m-d'), $today->format('Y-m-d'));
        $streak = 0;
        foreach (array_reverse($rows) as $d) {
            if ((float) $d['regular_hours'] <= 0 && (float) $d['overtime_hours'] <= 0) {
                continue;
            }
            if ((int) $d['late_minutes'] > 0) {
                break;
            }
            $streak++;
        }
        return $streak;
    }

    private function insights(array $month, array $prev, int $streak, ?array $latest, array $history, DateTimeImmutable $today): array {
        $out = [];
        $plural = fn(int $n, string $word) => $n . ' ' . $word . ($n === 1 ? '' : 's');

        if ($latest) {
            $period = $this->period($latest['period_start'], $latest['period_end']);
            if ($latest['status'] === 'paid' && $latest['paid_at']
                && new DateTimeImmutable($latest['paid_at'], $this->tz) >= $today->modify('-7 days')) {
                $out[] = ['tone' => 'good', 'text' => "Your pay for {$period} ({$this->peso($latest['net_pay'])}) was released on "
                    . $this->date($latest['paid_at']) . '.'];
            } elseif ($latest['status'] === 'approved') {
                $by = $latest['approved_by'] ? " by {$latest['approved_by']}" : '';
                $out[] = ['tone' => 'info', 'text' => "Your pay for {$period} ({$this->peso($latest['net_pay'])}) was approved{$by} and is waiting to be released."];
            }
        }

        if ($month['late_days'] > 0) {
            $text = "So far this month you've been late " . $plural($month['late_days'], 'day')
                . " ({$this->minutes($month['late_minutes'])} in total). Let's do better!";
            if ($prev['late_days'] > $month['late_days']) {
                $text .= " You're still under last month's {$prev['late_days']}.";
            }
            $out[] = ['tone' => 'warn', 'text' => $text];
        } elseif ($month['days_worked'] > 0) {
            $out[] = ['tone' => 'good', 'text' => 'No lates so far this month. Keep it up!'];
        }

        if ($streak >= 5) {
            $out[] = ['tone' => 'good', 'text' => "{$streak} on-time workdays in a row. Nice streak!"];
        }

        if ($month['ot_days'] > 0) {
            $out[] = ['tone' => 'info', 'text' => 'You put in ' . $this->hours($month['ot_hours']) . ' of overtime over '
                . $plural($month['ot_days'], 'day') . ' this month.'];
        }

        if ($month['undertime_days'] > 0) {
            $out[] = ['tone' => 'warn', 'text' => 'You left before the end of shift on ' . $plural($month['undertime_days'], 'day')
                . " this month ({$this->minutes($month['undertime_minutes'])})."];
        }

        $raise = $this->latestRaise($history, $today);
        if ($raise) {
            $out[] = ['tone' => 'good', 'text' => "Your rate went up from {$raise['from']} to {$raise['to']} on {$raise['date']}. Congrats!"];
        }

        if (!$out && $month['days_worked'] === 0) {
            $out[] = ['tone' => 'info', 'text' => 'No attendance recorded yet this month.'];
        }
        return $out;
    }

    // Most recent increase within 60 days, compared on the same basis (hourly or monthly).
    private function latestRaise(array $history, DateTimeImmutable $today): ?array {
        for ($i = count($history) - 1; $i > 0; $i--) {
            $cur = $history[$i];
            $prev = $history[$i - 1];
            if (new DateTimeImmutable($cur['effective_date'], $this->tz) < $today->modify('-60 days')) {
                return null;
            }
            foreach (['monthly_rate' => '/mo', 'hourly_rate' => '/hr'] as $field => $unit) {
                if ($cur[$field] !== null && $prev[$field] !== null && bccomp($cur[$field], $prev[$field], 2) > 0) {
                    return [
                        'from' => $this->peso($prev[$field]) . $unit,
                        'to'   => $this->peso($cur[$field]) . $unit,
                        'date' => $this->date($cur['effective_date']),
                    ];
                }
            }
        }
        return null;
    }

    private function nextPayday(string $frequency, DateTimeImmutable $today): string {
        switch ($frequency) {
            case 'weekly':
                $days = (6 - (int) $today->format('w') + 7) % 7; // 6 = Saturday
                return $today->modify("+{$days} days")->format('Y-m-d');
            case 'kinsenas':
                return (int) $today->format('j') <= 15
                    ? $today->format('Y-m-15')
                    : $today->modify('last day of this month')->format('Y-m-d');
            default:
                return $today->modify('last day of this month')->format('Y-m-d');
        }
    }

    private function hourlyRate(array $employee): ?string {
        try {
            return $this->payroll->getHourlyRate($employee);
        } catch (DomainException) {
            return null;
        }
    }

    private function tenure(?string $hired, DateTimeImmutable $today): ?string {
        if (!$hired) {
            return null;
        }
        $diff = (new DateTimeImmutable($hired, $this->tz))->diff($today);
        $parts = [];
        if ($diff->y) $parts[] = $diff->y . ' yr' . ($diff->y === 1 ? '' : 's');
        if ($diff->m) $parts[] = $diff->m . ' mo' . ($diff->m === 1 ? '' : 's');
        return $parts ? implode(' ', $parts) : 'Less than a month';
    }

    private function mask(?string $id): ?string {
        $digits = preg_replace('/\D/', '', (string) $id);
        return $digits === '' ? null : '•••• ' . substr($digits, -4);
    }

    private function requireEmployee(int $id): array {
        $employee = $this->employees->findById($id);
        if (!$employee) {
            throw new DomainException('Employee record not found.');
        }
        return $employee->toArray();
    }

    private function today(): DateTimeImmutable {
        return new DateTimeImmutable('today', $this->tz);
    }

    private function peso(string|float|null $amount): string {
        return '₱' . number_format((float) $amount, 2);
    }

    private function minutes(int $m): string {
        return $m >= 60 ? intdiv($m, 60) . 'h ' . ($m % 60) . 'm' : $m . ' min';
    }

    private function hours(float $h): string {
        return rtrim(rtrim(number_format($h, 2), '0'), '.') . ' hr' . ($h == 1.0 ? '' : 's');
    }

    private function date(string $value): string {
        return (new DateTimeImmutable($value, $this->tz))->format('M j, Y');
    }

    private function period(string $start, string $end): string {
        $s = new DateTimeImmutable($start, $this->tz);
        $e = new DateTimeImmutable($end, $this->tz);
        return $s->format('M j') . '–' . $e->format($s->format('M') === $e->format('M') ? 'j' : 'M j');
    }
}
