<?php
namespace App\Service;

use App\Entity\PayrollPeriod;
use App\Repository\DailySummaryRepository;
use App\Repository\EmployeeRepository;
use App\Repository\HolidayRepository;
use App\Repository\PayrollRepository;
use App\Repository\SundayDutyRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

class PayrollService {
    private const TIMEZONE = 'Asia/Manila';

    private const PAY_FREQUENCIES = ['weekly', 'kinsenas', 'monthly'];

    private const HOURS_PER_MONTH = '208.67';       // DOLE convention for monthly → hourly
    private const OT_MULTIPLIER   = '1.25';
    // Night diff is a 10% PREMIUM on top of the hours already paid as regular/OT (Labor Code Art. 86),
    // not 110% of the rate, which would pay those hours twice.
    private const NIGHT_MULTIPLIER = '0.10';
    private const REST_MULTIPLIER  = '1.30';
    // OT on a Sunday/holiday is 130% of that day's rate (DOLE), not the ordinary-day 125%.
    private const PREMIUM_DAY_OT_MULTIPLIER = '1.30';
    // Day rate multipliers (DOLE). Sunday duty = 130%, special non-working worked = 130%, regular holiday worked = 200%.
    private const DAY_MULTIPLIERS = [
        'ordinary'            => '1.00',
        'sunday'              => '1.30',
        'special_non_working' => '1.30',
        'special_sunday'      => '1.50',
        'regular'             => '2.00',
        'regular_sunday'      => '2.60',
    ];
    public const SETTING_PAY_UNWORKED_REGULAR = 'pay_unworked_regular_holiday';

    private PayrollRepository $repository;
    private EmployeeRepository $employees;
    private DailySummaryRepository $dailySummary;
    private PayrollDeductionService $deductions;
    private TimesheetService $timesheet;
    private AuditService $audit;
    private HolidayRepository $holidays;
    private SundayDutyRepository $sundayDuty;
    private SystemSettingService $settings;

    public function __construct(
        ?PayrollRepository $repository = null,
        ?EmployeeRepository $employees = null,
        ?DailySummaryRepository $dailySummary = null,
        ?PayrollDeductionService $deductions = null,
        ?TimesheetService $timesheet = null,
        ?AuditService $audit = null,
        ?HolidayRepository $holidays = null,
        ?SundayDutyRepository $sundayDuty = null,
        ?SystemSettingService $settings = null
    ) {
        $this->repository   = $repository   ?? new PayrollRepository();
        $this->employees    = $employees    ?? new EmployeeRepository();
        $this->dailySummary = $dailySummary ?? new DailySummaryRepository();
        $this->deductions   = $deductions   ?? new PayrollDeductionService();
        $this->timesheet    = $timesheet    ?? new TimesheetService();
        $this->audit        = $audit        ?? new AuditService();
        $this->holidays     = $holidays     ?? new HolidayRepository();
        $this->sundayDuty   = $sundayDuty   ?? new SundayDutyRepository();
        $this->settings     = $settings     ?? new SystemSettingService();
    }

    // Compute + save payroll for a period, then return the saved rows (with employee names).
    // Punches are summarized first, so hours always reflect the latest punches.
    // Approved/paid periods are left untouched; draft/computed ones are replaced.
    public function computeAndSave(string $start, string $end, int $computedBy, int $employeeId = 0, string $frequency = ''): array {
        $this->assertDateRange($start, $end);
        $this->assertFrequency($frequency);

        $this->timesheet->summarizePeriod($start, $end);

        $employees = $this->employeesToPay($employeeId, $frequency);
        $this->repository->transaction(function () use ($employees, $start, $end, $computedBy) {
            foreach ($employees as $employee) {
                $this->saveComputed($this->computeForPeriod((int) $employee['id'], $start, $end, $computedBy));
            }
        });

        $this->audit->record('PAYROLL_COMPUTE', $employeeId ?: null, [
            'period_start'  => $start,
            'period_end'    => $end,
            'pay_frequency' => $frequency ?: 'all',
            'employees'     => count($employees),
        ]);

        return $this->getPeriod($start, $end, $employeeId, $frequency);
    }

    // Saved payroll rows for exactly this period.
    public function getPeriod(string $start, string $end, int $employeeId = 0, string $frequency = ''): array {
        $this->assertDateRange($start, $end);
        $this->assertFrequency($frequency);
        return $this->repository->findExactPeriod($start, $end, $employeeId, $frequency);
    }

    // computed → approved. The SQL WHERE on status makes a double click or a race a no-op.
    public function approve(int $payrollId, ?int $approvedBy = null): bool {
        $record = $this->repository->findById($payrollId);
        if (!$record) {
            throw new DomainException("Payroll period not found: {$payrollId}");
        }
        if ($record['status'] !== 'computed') {
            throw new DomainException("Only computed payrolls can be approved (current: {$record['status']}).");
        }
        (new ApprovalPolicy())->assertCanApprove($approvedBy, (int) $record['employee_id'], 'payroll');
        if (!$this->repository->approve($payrollId, $approvedBy)) {
            throw new DomainException('Payroll was modified by another user. Reload and try again.');
        }
        $this->audit->record('PAYROLL_APPROVE', (int) $record['employee_id'], $this->auditRef($record));
        return true;
    }

    private function auditRef(array $record): array {
        return [
            'payroll_id' => (int) $record['id'],
            'period'     => $record['period_start'] . ' to ' . $record['period_end'],
            'net_pay'    => $record['net_pay'],
        ];
    }

    private function saveComputed(array $row): void {
        $existing = $this->repository->findDuplicate($row['employee_id'], $row['period_start'], $row['period_end']);
        if ($existing && !in_array($existing['status'], ['draft', 'computed'], true)) {
            return; // approved/paid payroll is locked
        }
        
        if ($existing) {
            $this->repository->deleteById((int) $existing['id']); // its deduction snapshot cascades
            $this->deductions->forgetCarryovers((int) $existing['id']);
        }

        $id = $this->repository->createPeriod($row);
        $this->deductions->snapshotForPayroll($id, $row['deductions']);
    }

    /** @return array<int, array> employee rows as arrays */
    private function employeesToPay(int $employeeId, string $frequency): array {
        $employees = $employeeId > 0
            ? [$this->requireEmployee($employeeId)]
            : array_map(fn($e) => $e->toArray(), $this->employees->findAllActive());

        if ($frequency === '') {
            return $employees;
        }
        return array_values(array_filter($employees, fn(array $e) => $e['pay_frequency'] === $frequency));
    }

    private function assertFrequency(string $frequency): void {
        if ($frequency !== '' && !in_array($frequency, self::PAY_FREQUENCIES, true)) {
            throw new InvalidArgumentException('pay_frequency must be one of: ' . implode(', ', self::PAY_FREQUENCIES) . '.');
        }
    }

    public function computeForPeriod(int $employeeId, string $start, string $end, int $computedBy): array {
        $this->assertDateRange($start, $end);

        $employee = $this->requireEmployee($employeeId);
        $hourly = $this->getHourlyRate($employee);

        $summary = $this->dailySummary->sumByPeriod($employeeId, $start, $end);

        $totals = [
            'total_regular_hours'     => (string) $summary['total_regular'],
            'total_overtime_hours'    => (string) $summary['total_overtime'],
            'total_night_diff_hours'  => (string) $summary['total_night_diff'],
            'total_late_minutes'      => (int) $summary['total_late'],
            'total_undertime_minutes' => (int) $summary['total_undertime'],
        ];

        $base    = $this->computeGrossPay($totals, $employee, $hourly);
        $premium = $this->computePremiums(
            $employeeId, $employee, $hourly, $start, $end,
            $this->dailySummary->findByPeriod($employeeId, $start, $end)
        );
        $gross = bcadd($base, $premium['amount'], 2);
        $computed = $this->deductions->computeFromCatalog($employeeId, $gross, $start, $end, $employee);

        $deductTotal = '0.00';
        foreach ($computed as $d) {
            $deductTotal = bcadd($deductTotal, $d['amount'], 2);
        }

        $net = $this->computeNetPay($gross, $computed);

        return [
            'employee_id'             => $employeeId,
            'period_start'            => $start,
            'period_end'              => $end,
            'pay_frequency'           => (string) $employee['pay_frequency'],
            'total_regular_hours'     => $totals['total_regular_hours'],
            'total_overtime_hours'    => $totals['total_overtime_hours'],
            'total_night_diff_hours'  => $totals['total_night_diff_hours'],
            'total_late_minutes'      => $totals['total_late_minutes'],
            'total_undertime_minutes' => $totals['total_undertime_minutes'],
            'hourly_rate'             => $hourly,
            'premium_pay'             => $premium['amount'],
            'premium_details'         => $premium['lines'] ? json_encode($premium['lines']) : null,
            'gross_pay'               => $gross,
            'total_deductions'        => $deductTotal,
            'net_pay'                 => $net,
            'status'                  => 'computed',
            'computed_by'             => $computedBy,
            'computed_at'             => $this->now(),
            'paid_at'                 => null,
            'notes'                   => null,
            'deductions'              => $computed,
        ];
    }

    public function computeForAllEmployees(string $start, string $end, int $computedBy): array {
        $this->assertDateRange($start, $end);

        $results = [];
        foreach ($this->employees->findAllActive() as $employee) {
            $employeeId = (int) $employee->getId();
            $results[]  = $this->computeForPeriod($employeeId, $start, $end, $computedBy);
        }

        return $results;
    }

    public function computeGrossPay(array $totals, array $employee, string $hourlyRate): string {
        $hourlyRate = $this->money($hourlyRate, 'hourlyRate');

        $regular = bcmul((string) $totals['total_regular_hours'], $hourlyRate, 4);

        $ot = $this->computeOvertimePay((string) $totals['total_overtime_hours'], $hourlyRate);
        $night = $this->computeNightDiffPay((string) $totals['total_night_diff_hours'], $hourlyRate);
        $rest = '0.00';

        $total = bcadd($regular, $ot, 4);
        $total = bcadd($total, $night, 4);
        $total = bcadd($total, $rest, 4);

        return $this->roundHalfUp($total);
    }

    /**
     * Extra pay for Sundays and holidays, added on top of computeGrossPay() (which pays every hour at the ordinary rate).
     * Per worked day: extra = (hours at that day's rate) − (same hours at the ordinary rate), where at multiplier m:
     *   regular hours × rate × m  +  OT × rate × m × 1.30  +  night diff × rate × m × 0.10
     * Unworked regular holidays on a work day add one day at 100% only when the pay_unworked_regular_holiday setting is 1.
     *
     * @param array<int, array> $days daily_summary rows for the period
     * @return array{amount: string, lines: array<int, array{date: string, label: string, rate: string, hours: string, amount: string}>}
     */
    public function computePremiums(int $employeeId, array $employee, string $hourly, string $start, string $end, array $days): array {
        $holidays = [];
        foreach ($this->holidays->findByDateRange($start, $end) as $h) {
            $holidays[$h->getHolidayDate()] = $h;
        }
        $rostered = $this->sundayDuty->assignedDates($employeeId, $start, $end);

        $lines  = [];
        $worked = [];

        foreach ($days as $d) {
            $reg   = bcadd((string) $d['regular_hours'], '0', 2);
            $ot    = bcadd((string) $d['overtime_hours'], '0', 2);
            $night = bcadd((string) $d['night_diff_hours'], '0', 2);
            if (bccomp($reg, '0', 2) <= 0 && bccomp($ot, '0', 2) <= 0) {
                continue;
            }

            $date    = (string) $d['work_date'];
            $holiday = $holidays[$date] ?? null;
            $sunday  = (bool) $d['is_rest_day'];
            $worked[$date] = true;

            $key = $this->dayKey($holiday?->getType(), $sunday);
            if ($key === 'ordinary') {
                continue;
            }
            $m = self::DAY_MULTIPLIERS[$key];

            $atDayRate = bcadd(
                bcadd(bcmul(bcmul($reg, $hourly, 4), $m, 4), bcmul(bcmul(bcmul($ot, $hourly, 4), $m, 4), self::PREMIUM_DAY_OT_MULTIPLIER, 4), 4),
                bcmul(bcmul(bcmul($night, $hourly, 4), $m, 4), self::NIGHT_MULTIPLIER, 4),
                4
            );
            $atOrdinary = bcadd(
                bcadd(bcmul($reg, $hourly, 4), bcmul(bcmul($ot, $hourly, 4), self::OT_MULTIPLIER, 4), 4),
                bcmul(bcmul($night, $hourly, 4), self::NIGHT_MULTIPLIER, 4),
                4
            );

            $lines[] = [
                'date'   => $date,
                'label'  => $this->dayLabel($key, $holiday?->getName(), $sunday && !isset($rostered[$date])),
                'rate'   => bcmul($m, '100', 0) . '%',
                'hours'  => bcadd($reg, $ot, 2),
                'amount' => $this->roundHalfUp(bcsub($atDayRate, $atOrdinary, 4)),
            ];
        }

        if ($this->settings->getValue(self::SETTING_PAY_UNWORKED_REGULAR, '0') === '1') {
            $workDays = $this->settings->getWorkDays();
            $hired    = (string) ($employee['date_hired'] ?? '');
            $dayPay   = $this->roundHalfUp(bcmul((string) $this->settings->getRegularHours(), $hourly, 4));

            foreach ($holidays as $date => $holiday) {
                $isWorkDay = in_array(strtolower((new DateTimeImmutable($date))->format('D')), $workDays, true);
                if ($holiday->getType() !== 'regular' || isset($worked[$date]) || !$isWorkDay || ($hired !== '' && $date < $hired)) {
                    continue;
                }
                $lines[] = [
                    'date'   => $date,
                    'label'  => 'Regular holiday, not worked: ' . $holiday->getName(),
                    'rate'   => '100%',
                    'hours'  => '0.00',
                    'amount' => $dayPay,
                ];
            }
        }

        usort($lines, fn(array $a, array $b) => strcmp($a['date'], $b['date']));

        $amount = '0.00';
        foreach ($lines as $line) {
            $amount = bcadd($amount, $line['amount'], 2);
        }

        return ['amount' => $amount, 'lines' => $lines];
    }

    private function dayKey(?string $holidayType, bool $sunday): string {
        return match ($holidayType) {
            'regular'             => $sunday ? 'regular_sunday' : 'regular',
            'special_non_working' => $sunday ? 'special_sunday' : 'special_non_working',
            default               => $sunday ? 'sunday' : 'ordinary',
        };
    }

    private function dayLabel(string $key, ?string $holidayName, bool $notRostered): string {
        $label = match ($key) {
            'sunday'                    => 'Sunday duty',
            'special_non_working'       => 'Special non-working day: ' . $holidayName,
            'special_sunday'            => 'Special non-working day on a Sunday: ' . $holidayName,
            'regular'                   => 'Regular holiday: ' . $holidayName,
            'regular_sunday'            => 'Regular holiday on a Sunday: ' . $holidayName,
        };
        return $notRostered ? $label . ' (not on the duty roster)' : $label;
    }

    public function computeOvertimePay(string $hours, string $hourlyRate): string {
        $base = bcmul($this->money($hours, 'hours'), $this->money($hourlyRate, 'hourlyRate'), 4);
        return $this->roundHalfUp(bcmul($base, self::OT_MULTIPLIER, 4));
    }

    public function computeNightDiffPay(string $hours, string $hourlyRate): string {
        $base = bcmul($this->money($hours, 'hours'), $this->money($hourlyRate, 'hourlyRate'), 4);
        return $this->roundHalfUp(bcmul($base, self::NIGHT_MULTIPLIER, 4));
    }

    public function computeRestDayPay(string $hours, string $hourlyRate): string {
        $base = bcmul($this->money($hours, 'hours'), $this->money($hourlyRate, 'hourlyRate'), 4);
        return $this->roundHalfUp(bcmul($base, self::REST_MULTIPLIER, 4));
    }

    public function computeDeductions(int $employeeId, string $grossPay, string $start, string $end): array {
        return $this->deductions->computeFromCatalog($employeeId, $grossPay, $start, $end, $this->requireEmployee($employeeId));
    }

    public function computeNetPay(string $grossPay, array $deductions): string {
        $gross = $this->money($grossPay, 'grossPay');
        $total = '0.00';

        foreach ($deductions as $d) {
            $total = bcadd($total, $this->money($d['amount'] ?? '0', 'deduction amount'), 2);
        }

        $net = bcsub($gross, $total, 2);

        return bccomp($net, '0', 2) >= 0 ? $net : '0.00';
    }

    public function getHourlyRate(array $employee): string {
        if (!empty($employee['hourly_rate']) && is_numeric($employee['hourly_rate'])) {
            return bcadd((string) $employee['hourly_rate'], '0', 2);
        }

        if (!empty($employee['monthly_rate']) && is_numeric($employee['monthly_rate'])) {
            return $this->roundHalfUp(bcdiv((string) $employee['monthly_rate'], self::HOURS_PER_MONTH, 4));
        }

        throw new DomainException("Employee {$employee['id']} has no hourly or monthly rate.");
    }

    public function savePayrollPeriod(array $data): int {
        $clean = $this->normalizeForSave($data);
        return $this->repository->createPeriod($clean);
    }

    public function getPayrollHistory(int $employeeId): array {
        return $this->repository->findByEmployee($employeeId);
    }

    public function getById(int $id): ?array {
        return $this->repository->findById($id);
    }

    public function getByDateRange(string $start, string $end): array {
        $this->assertDateRange($start, $end);
        return $this->repository->findByDateRange($start, $end);
    }

    public function markAsPaid(int $payrollId, string $paidAt): bool {
        $this->assertDateTime($paidAt, 'paidAt');

        $record = $this->repository->findById($payrollId);
        if (!$record) {
            throw new DomainException("Payroll period not found: {$payrollId}");
        }
        if ($record['status'] !== 'approved') {
            throw new DomainException(
                "Only approved payrolls can be marked as paid (current: {$record['status']})."
            );
        }

        $ok = $this->repository->markAsPaid($payrollId, $paidAt);
        if ($ok) {
            $this->audit->record('PAYROLL_PAID', (int) $record['employee_id'], $this->auditRef($record) + ['paid_at' => $paidAt]);
        }
        return $ok;
    }

    public function updateStatus(int $payrollId, string $from, string $to, int $actorId): bool {
        $this->assertStatus($from);
        $this->assertStatus($to);

        return $this->repository->updateStatus($payrollId, $from, $to, $actorId);
    }

    public function exportToCsv(array $payrollRows): string {
        $fh = fopen('php://temp', 'r+');

        fputcsv($fh, [
            'Employee ID', 'Employee', 'Period Start', 'Period End', 'Pay Frequency',
            'Regular Hours', 'OT Hours', 'Night Diff Hours',
            'Hourly Rate', 'Sunday/Holiday Pay', 'Gross Pay', 'Deductions', 'Net Pay', 'Status', 'Paid At',
        ]);

        foreach ($payrollRows as $row) {
            fputcsv($fh, [
                $row['employee_id']             ?? '',
                $this->csvSafe($row['full_name'] ?? ''),
                $row['period_start']            ?? '',
                $row['period_end']              ?? '',
                $row['pay_frequency']           ?? '',
                $row['total_regular_hours']     ?? '0.00',
                $row['total_overtime_hours']    ?? '0.00',
                $row['total_night_diff_hours']  ?? '0.00',
                $row['hourly_rate']             ?? '0.00',
                $row['premium_pay']             ?? '0.00',
                $row['gross_pay']               ?? '0.00',
                $row['total_deductions']        ?? '0.00',
                $row['net_pay']                 ?? '0.00',
                $row['status']                  ?? '',
                $row['paid_at']                 ?? '',
            ]);
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return "\xEF\xBB\xBF" . $csv;
    }

    private function normalizeForSave(array $data): array {
        foreach (['employee_id', 'period_start', 'period_end', 'pay_frequency'] as $key) {
            if (!isset($data[$key]) || $data[$key] === '') {
                throw new InvalidArgumentException("Missing field: {$key}");
            }
        }

        $this->assertDateRange($data['period_start'], $data['period_end']);
        $this->requireEmployee((int) $data['employee_id']);

        if (!in_array($data['pay_frequency'], self::PAY_FREQUENCIES, true)) {
            throw new InvalidArgumentException('pay_frequency must be one of: ' . implode(', ', self::PAY_FREQUENCIES) . '.');
        }

        $clean = [
            'employee_id'             => (int) $data['employee_id'],
            'period_start'            => $data['period_start'],
            'period_end'              => $data['period_end'],
            'pay_frequency'           => $data['pay_frequency'],
            'total_regular_hours'     => $this->money($data['total_regular_hours']     ?? '0', 'total_regular_hours'),
            'total_overtime_hours'    => $this->money($data['total_overtime_hours']    ?? '0', 'total_overtime_hours'),
            'total_night_diff_hours'  => $this->money($data['total_night_diff_hours']  ?? '0', 'total_night_diff_hours'),
            'total_late_minutes'      => (int) ($data['total_late_minutes']      ?? 0),
            'total_undertime_minutes' => (int) ($data['total_undertime_minutes'] ?? 0),
            'hourly_rate'             => $this->money($data['hourly_rate']             ?? '0', 'hourly_rate'),
            'gross_pay'               => $this->money($data['gross_pay']               ?? '0', 'gross_pay'),
            'total_deductions'        => $this->money($data['total_deductions']        ?? '0', 'total_deductions'),
            'net_pay'                 => $this->money($data['net_pay']                 ?? '0', 'net_pay'),
            'status'                  => $data['status'] ?? 'draft',
            'computed_by'             => isset($data['computed_by']) ? (int) $data['computed_by'] : null,
            'computed_at'             => $data['computed_at'] ?? $this->now(),
            'paid_at'                 => $data['paid_at']     ?? null,
            'notes'                   => isset($data['notes']) ? trim((string) $data['notes']) : null,
        ];

        $this->assertStatus($clean['status']);

        return $clean;
    }

    private function requireEmployee(int $employeeId): array {
        $employee = $this->employees->findById($employeeId);
        if (!$employee) {
            throw new DomainException("Employee not found: {$employeeId}");
        }
        return is_array($employee) ? $employee : $employee->toArray();
    }

    private function assertStatus(string $status): void {
        if (!in_array($status, ['draft', 'computed', 'approved', 'paid'], true)) {
            throw new InvalidArgumentException("Invalid payroll status: {$status}");
        }
    }

    private function assertDateRange(string $start, string $end): void {
        $tz = new DateTimeZone(self::TIMEZONE);

        $s = DateTimeImmutable::createFromFormat('!Y-m-d', $start, $tz);
        if (!$s || $s->format('Y-m-d') !== $start) {
            throw new InvalidArgumentException("Invalid start date: {$start}");
        }

        $e = DateTimeImmutable::createFromFormat('!Y-m-d', $end, $tz);
        if (!$e || $e->format('Y-m-d') !== $end) {
            throw new InvalidArgumentException("Invalid end date: {$end}");
        }

        if ($s > $e) {
            throw new InvalidArgumentException('Start date must be on or before end date.');
        }
    }

    private function assertDateTime(string $value, string $field): void {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone(self::TIMEZONE));
        if (!$dt || $dt->format('Y-m-d H:i:s') !== $value) {
            throw new InvalidArgumentException("{$field} must be a valid Y-m-d H:i:s.");
        }
    }

    private function money(mixed $value, string $field): string {
        if (!is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException("{$field} must be a non-negative number.");
        }
        return bcadd((string) $value, '0', 2);
    }

    private function roundHalfUp(string $value): string {
        return bccomp($value, '0', 4) >= 0
            ? bcadd($value, '0.005', 2)
            : bcsub($value, '0.005', 2);
    }

    private function now(): string {
        return (new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE)))->format('Y-m-d H:i:s');
    }

    private function csvSafe(?string $value): string {
        $value = (string) $value;
        return ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) ? "'" . $value : $value;
    }

    public function getByStatus(string $status): array {
        return $this->repository->findByStatus($status);
    }
}