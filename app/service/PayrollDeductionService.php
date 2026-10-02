<?php
namespace App\Service;

use App\Repository\DeductionRepository;
use App\Repository\EmployeeDeductionRepository;
use App\Repository\PayrollDeductionRepository;
use App\Repository\PayrollRepository;
use App\Service\StatutoryContributionService;
use App\Repository\DeductionCarryoverRepository;  
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;

class PayrollDeductionService {
    private const TIMEZONE = 'Asia/Manila';
    private const ALLOWED_PAYROLL_STATUSES = ['draft', 'computed'];

    private PayrollDeductionRepository $repository;
    private PayrollRepository $payrollRepository;
    private EmployeeDeductionRepository $employeeDeductionRepository;
    private DeductionRepository $deductionRepository;
    private DeductionCarryoverRepository $carryovers;

    private StatutoryContributionService $statutory;
    private AuditService $audit;

    public function __construct(
        ?PayrollDeductionRepository $repository = null,
        ?PayrollRepository $payrollRepository = null,
        ?EmployeeDeductionRepository $employeeDeductionRepository = null,
        ?DeductionRepository $deductionRepository = null,
        ?StatutoryContributionService $statutory = null,
        ?DeductionCarryoverRepository $carryovers = null,
        ?AuditService $audit = null
    ) {
        $this->repository = $repository ?? new PayrollDeductionRepository();
        $this->payrollRepository = $payrollRepository ?? new PayrollRepository();
        $this->employeeDeductionRepository = $employeeDeductionRepository ?? new EmployeeDeductionRepository();
        $this->deductionRepository = $deductionRepository ?? new DeductionRepository();
        $this->statutory = $statutory ?? new StatutoryContributionService();
        $this->carryovers = $carryovers ?? new DeductionCarryoverRepository();
        $this->audit = $audit ?? new AuditService();
    }

    public function getByPayrollPeriod(int $payrollPeriodId): array {
        $this->requirePayrollPeriod($payrollPeriodId);
        return $this->repository->findByPayrollPeriod($payrollPeriodId);
    }

    public function getById(int $id): ?array {
        return $this->repository->findById($id);
    }

    public function create(int $payrollPeriodId, int $deductionId, string $amount): int {
        $payroll = $this->requirePayrollPeriod($payrollPeriodId);
        $this->assertEditable($payroll);
        $this->requireDeduction($deductionId);

        $amount = $this->money($amount, 'amount');
        $id = $this->repository->create($payrollPeriodId, $deductionId, $amount);

        $row = $this->repository->findById($id);
        $this->audit->record('DEDUCTION_ADD', (int) $payroll['employee_id'], [
            'payroll_id'           => $payrollPeriodId,
            'payroll_deduction_id' => $id,
            'deduction'            => $row['deduction_code'] ?? $deductionId,
            'amount'               => $amount,
        ]);
        return $id;
    }

    public function update(int $id, string $amount): bool {
        $existing = $this->requirePayrollDeduction($id);
        $payroll  = $this->requirePayrollPeriod((int) $existing['payroll_period_id']);
        $this->assertEditable($payroll);

        $amount = $this->money($amount, 'amount');
        $ok = $this->repository->update($id, $amount);
        if ($ok) {
            $this->audit->record('DEDUCTION_UPDATE', (int) $payroll['employee_id'], $this->auditRef($existing) + [
                'amount' => ['from' => $existing['amount'], 'to' => $amount],
            ]);
        }
        return $ok;
    }

    public function delete(int $id): bool {
        $existing = $this->requirePayrollDeduction($id);
        $payroll  = $this->requirePayrollPeriod((int) $existing['payroll_period_id']);
        $this->assertEditable($payroll);

        $ok = $this->repository->delete($id);
        if ($ok) {
            $this->audit->record('DEDUCTION_DELETE', (int) $payroll['employee_id'], $this->auditRef($existing) + [
                'amount' => $existing['amount'],
            ]);
        }
        return $ok;
    }

    private function auditRef(array $payrollDeduction): array {
        return [
            'payroll_id'           => (int) $payrollDeduction['payroll_period_id'],
            'payroll_deduction_id' => (int) $payrollDeduction['id'],
            'deduction'            => $payrollDeduction['deduction_code'],
        ];
    }

    public function bulkInsert(int $payrollPeriodId, array $rows): int {
        $payroll = $this->requirePayrollPeriod($payrollPeriodId);
        $this->assertEditable($payroll);

        $clean = $this->validateBulkRows($rows);

        $count = $this->repository->bulkInsert($payrollPeriodId, $clean);
        $this->audit->record('DEDUCTION_BULK_ADD', (int) $payroll['employee_id'], ['payroll_id' => $payrollPeriodId, 'rows' => $count]);
        return $count;
    }

    public function deleteByPayrollPeriod(int $payrollPeriodId): int {
        $payroll = $this->requirePayrollPeriod($payrollPeriodId);
        $this->assertEditable($payroll);

        $count = $this->repository->deleteByPayrollPeriod($payrollPeriodId);
        $this->audit->record('DEDUCTION_CLEAR', (int) $payroll['employee_id'], ['payroll_id' => $payrollPeriodId, 'rows' => $count]);
        return $count;
    }

    public function sumByPayrollPeriod(int $payrollPeriodId): string {
        $this->requirePayrollPeriod($payrollPeriodId);
        return $this->repository->sumByPayrollPeriod($payrollPeriodId);
    }

    public function applyDeductionsToPayroll(int $payrollPeriodId, array $deductions): int {
        $payroll = $this->requirePayrollPeriod($payrollPeriodId);
        $this->assertEditable($payroll);

        $rows = $this->validateBulkRows($deductions);

        $count = $this->repository->replaceForPayrollPeriod($payrollPeriodId, $rows);
        $this->audit->record('DEDUCTION_REPLACE', (int) $payroll['employee_id'], [
            'payroll_id' => $payrollPeriodId,
            'rows'       => array_map(fn($r) => ['deduction_id' => $r['deduction_id'], 'amount' => $r['amount']], $rows),
        ]);
        return $count;
    }

    public function computeFromCatalog(
        int $employeeId,
        string $grossPay,
        string $periodStart,
        string $periodEnd,
        ?array $employee = null
    ): array {
        $grossPay = $this->money($grossPay, 'grossPay');
        $this->assertDate($periodStart, 'periodStart');
        $this->assertDate($periodEnd, 'periodEnd');

        if ($periodStart > $periodEnd) {
            throw new InvalidArgumentException('periodStart must be on or before periodEnd.');
        }

        $rows    = $this->employeeDeductionRepository->findByEmployeeAndPeriod($employeeId, $periodStart, $periodEnd);
        $carryIn = $this->carryovers->latestShortfalls($employeeId, $periodStart);

        $statutory = [];
        $others    = [];
        foreach ($rows as $row) {
            $code     = (string) $row['deduction_code'];
            $override = $row['amount'] !== null && $row['amount'] !== '' ? (string) $row['amount'] : null;
            $item = [
                'deduction_id' => (int) $row['deduction_id'],
                'code'         => $code,
                'name'         => (string) $row['deduction_name'],
                'type'         => (string) $row['deduction_type'],
            ];

            if ($this->statutory->isStatutory($code)) {
                if ($employee === null) {
                    throw new InvalidArgumentException("{$code} needs the employee row to compute.");
                }
                $share   = $override !== null
                    ? $this->money($override, 'amount')
                    : $this->statutory->shareForPeriod($code, $employee, $periodEnd);
                $carried = $carryIn[$item['deduction_id']] ?? '0.00';
                $item['carried_in'] = $carried;
                $item['amount_due'] = bcadd($share, $carried, 2);
                $statutory[array_search($code, StatutoryContributionService::CODES, true)] = $item;
            } else {
                $item['amount_due'] = $this->computeDeductionAmount(
                    $item['type'], (string) $row['deduction_value'], $override, $grossPay
                );
                $others[] = $item;
            }
        }
        ksort($statutory);   // SSS, PhilHealth, Pag-IBIG

        $available = $grossPay;
        $computed  = [];
        foreach ([...array_values($statutory), ...$others] as $item) {
            $take = bccomp($item['amount_due'], $available, 2) <= 0 ? $item['amount_due'] : $available;
            $available = bcsub($available, $take, 2);

            $item['amount'] = $take;
            if (isset($item['carried_in'])) {
                $item['shortfall'] = bcsub($item['amount_due'], $take, 2);
            }
            $computed[] = $item;
        }

        return $computed;
    }

    public function forgetCarryovers(int $payrollPeriodId): void {
        $this->carryovers->deleteByPayrollPeriod($payrollPeriodId);
    }

    public function snapshotForPayroll(int $payrollPeriodId, array $computedDeductions): int {
        $payroll = $this->requirePayrollPeriod($payrollPeriodId);
        $this->assertEditable($payroll);

        $rows  = [];
        $carry = [];
        foreach ($computedDeductions as $d) {
            if (!isset($d['deduction_id'], $d['amount'])) {
                throw new InvalidArgumentException('Each computed deduction needs deduction_id and amount.');
            }
            $amount = $this->money($d['amount'], 'amount');

            if (bccomp($amount, '0', 2) > 0) {
                $rows[] = ['deduction_id' => (int) $d['deduction_id'], 'amount' => $amount];
            }
            if (isset($d['shortfall'])) {
                $carry[] = [
                    'deduction_id'    => (int) $d['deduction_id'],
                    'amount_due'      => $this->money($d['amount_due'], 'amount_due'),
                    'amount_deducted' => $amount,
                    'shortfall'       => $this->money($d['shortfall'], 'shortfall'),
                ];
            }
        }

        $this->carryovers->replaceForPayrollPeriod(
            $payrollPeriodId,
            (int) $payroll['employee_id'],
            (string) $payroll['period_end'],
            $carry
        );
        return $this->repository->replaceForPayrollPeriod($payrollPeriodId, $rows);
    }

    private function validateBulkRows(array $rows): array {
        $clean = [];
        foreach ($rows as $i => $row) {
            if (!isset($row['deduction_id'], $row['amount'])) {
                throw new InvalidArgumentException("Row {$i}: deduction_id and amount are required.");
            }

            $deductionId = filter_var($row['deduction_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($deductionId === false) {
                throw new InvalidArgumentException("Row {$i}: deduction_id must be a positive integer.");
            }

            $this->requireDeduction((int) $deductionId);

            $clean[] = [
                'deduction_id' => (int) $deductionId,
                'amount'       => $this->money($row['amount'], "row {$i} amount"),
            ];
        }

        return $clean;
    }

    private function computeDeductionAmount(
        string $type,
        string $baseValue,
        ?string $override,
        string $grossPay
    ): string {
        if ($override !== null && $override !== '') {
            return $this->money($override, 'amount');
        }

        if ($type === 'fixed') {
            return $this->money($baseValue, 'value');
        }

        if ($type === 'percentage') {
            $rate   = bcdiv($baseValue, '100', 6);
            $amount = bcmul($grossPay, $rate, 4);
            return $this->roundHalfUp($amount);
        }

        throw new InvalidArgumentException("Unknown deduction type: {$type}");
    }

    private function requirePayrollPeriod(int $id): array {
        $row = $this->payrollRepository->findById($id);
        if (!$row) {
            throw new DomainException("Payroll period not found: {$id}");
        }
        return $row;
    }

    private function requirePayrollDeduction(int $id): array {
        $row = $this->repository->findById($id);
        if (!$row) {
            throw new DomainException("Payroll deduction not found: {$id}");
        }
        return $row;
    }

    private function requireDeduction(int $deductionId): void {
        if (!$this->deductionRepository->findById($deductionId)) {
            throw new DomainException("Deduction not found: {$deductionId}");
        }
    }

    private function assertEditable(array $payroll): void {
        if (!in_array($payroll['status'], self::ALLOWED_PAYROLL_STATUSES, true)) {
            throw new DomainException(
                "Payroll period is {$payroll['status']} and its deductions are locked."
            );
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

    private function assertDate(string $date, string $field): void {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone(self::TIMEZONE));
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Invalid date for {$field}: {$date}");
        }
    }
}