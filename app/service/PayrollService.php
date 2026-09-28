<?php
namespace App\Service;

use App\Entity\PayrollPeriod;
use App\Repository\DailySummaryRepository;
use App\Repository\EmployeeRepository;
use App\Repository\PayrollRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

class PayrollService {
    private const TIMEZONE = 'Asia/Manila';

    private const PAY_FREQUENCIES = ['weekly', 'kinsenas', 'monthly'];

    private const HOURS_PER_MONTH = '173.33';       // DOLE convention for monthly → hourly
    private const OT_MULTIPLIER   = '1.25';
    private const NIGHT_MULTIPLIER = '1.10';
    private const REST_MULTIPLIER  = '1.30';

    private PayrollRepository $repository;
    private EmployeeRepository $employees;
    private DailySummaryRepository $dailySummary;
    private PayrollDeductionService $deductions;

    public function __construct(
        ?PayrollRepository $repository = null,
        ?EmployeeRepository $employees = null,
        ?DailySummaryRepository $dailySummary = null,
        ?PayrollDeductionService $deductions = null
    ) {
        $this->repository   = $repository   ?? new PayrollRepository();
        $this->employees    = $employees    ?? new EmployeeRepository();
        $this->dailySummary = $dailySummary ?? new DailySummaryRepository();
        $this->deductions   = $deductions   ?? new PayrollDeductionService();
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
            'total_late_minutes'      => 0,
            'total_undertime_minutes' => 0,
        ];

        $gross = $this->computeGrossPay($totals, $employee, $hourly);
        $computed = $this->deductions->computeFromCatalog($employeeId, $gross, $start, $end);

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
            'gross_pay'               => $gross,
            'total_deductions'        => $deductTotal,
            'net_pay'                 => $net,
            'status'                  => 'computed',
            'computed_by'             => $computedBy,
            'computed_at'             => $this->now(),
            'paid_at'                 => null,
            'notes'                   => null,
        ];
    }

    public function computeForAllEmployees(string $start, string $end, int $computedBy): array {
        $this->assertDateRange($start, $end);

        $results = [];
        foreach ($this->employees->findAllActive() as $employee) {
            $employeeId = (int) $employee['id'];
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
        return $this->deductions->computeFromCatalog($employeeId, $grossPay, $start, $end);
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

        return $this->repository->markAsPaid($payrollId, $paidAt);
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
            'Hourly Rate', 'Gross Pay', 'Deductions', 'Net Pay', 'Status', 'Paid At',
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