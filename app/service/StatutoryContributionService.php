<?php
namespace App\Service;

use App\Entity\EmployeeDeduction;
use App\Repository\DeductionRepository;
use App\Repository\EmployeeDeductionRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Employee share of SSS / PhilHealth / Pag-IBIG.
 * Monthly share from a fixed monthly basis, split across that month's paydays;
 * the last payday absorbs rounding so the month totals exactly.
 * Rates: 2026. Update constants when agencies issue new circulars.
 */
class StatutoryContributionService {
    public const SSS        = 'SSS';
    public const PHILHEALTH = 'PHILHEALTH';
    public const PAGIBIG    = 'PAGIBIG';
    public const CODES      = [self::SSS, self::PHILHEALTH, self::PAGIBIG];

    private const TIMEZONE        = 'Asia/Manila';
    private const HOURS_PER_MONTH = '208.67';   // same as PayrollService

    private const SSS_EE_RATE  = '0.05';        // EE 5% of MSC
    private const SSS_MSC_MIN  = '5000';
    private const SSS_MSC_MAX  = '35000';
    private const SSS_MSC_STEP = '500';

    private const PHIC_RATE    = '0.05';        // 5%, split 50/50
    private const PHIC_FLOOR   = '10000';
    private const PHIC_CEILING = '100000';

    private const HDMF_LOW_LIMIT = '1500';      // ≤1,500 → 1%, else 2%
    private const HDMF_LOW_RATE  = '0.01';
    private const HDMF_RATE      = '0.02';
    private const HDMF_MFS       = '10000';     // max ₱200

    private DeductionRepository $deductions;
    private EmployeeDeductionRepository $employeeDeductions;

    public function __construct(
        ?DeductionRepository $deductions = null,
        ?EmployeeDeductionRepository $employeeDeductions = null
    ) {
        $this->deductions         = $deductions         ?? new DeductionRepository();
        $this->employeeDeductions = $employeeDeductions ?? new EmployeeDeductionRepository();
    }

    public function isStatutory(string $code): bool {
        return in_array($code, self::CODES, true);
    }

    public function monthlyBasis(array $employee): string {
        if (!empty($employee['monthly_rate']) && is_numeric($employee['monthly_rate'])) {
            return bcadd((string) $employee['monthly_rate'], '0', 2);
        }
        if (!empty($employee['hourly_rate']) && is_numeric($employee['hourly_rate'])) {
            return $this->round(bcmul((string) $employee['hourly_rate'], self::HOURS_PER_MONTH, 4));
        }
        throw new InvalidArgumentException("Employee {$employee['id']} has no rate to base contributions on.");
    }

    public function monthlyEmployeeShare(string $code, string $basis): string {
        return match ($code) {
            self::SSS        => $this->round(bcmul($this->sssMsc($basis), self::SSS_EE_RATE, 4)),
            self::PHILHEALTH => $this->round(bcdiv(bcmul($this->clamp($basis, self::PHIC_FLOOR, self::PHIC_CEILING), self::PHIC_RATE, 4), '2', 4)),
            self::PAGIBIG    => $this->round(bcmul(
                                    $this->min($basis, self::HDMF_MFS),
                                    bccomp($basis, self::HDMF_LOW_LIMIT, 2) <= 0 ? self::HDMF_LOW_RATE : self::HDMF_RATE,
                                    4)),
            default          => throw new InvalidArgumentException("Not a statutory code: {$code}"),
        };
    }

    public function shareForPeriod(string $code, array $employee, string $periodEnd): string {
        $monthly = $this->monthlyEmployeeShare($code, $this->monthlyBasis($employee));
        [$paydays, $index] = $this->paydaySlot((string) ($employee['pay_frequency'] ?? 'weekly'), $periodEnd);

        $perPayday = $this->round(bcdiv($monthly, (string) $paydays, 4));
        if ($index < $paydays) {
            return $perPayday;
        }
        return bcsub($monthly, bcmul($perPayday, (string) ($paydays - 1), 2), 2);
    }

    public function enroll(int $employeeId, string $effectiveFrom): int {
        $count = 0;
        foreach (self::CODES as $code) {
            $deduction = $this->deductions->findByCode($code);
            if (!$deduction || !$deduction->getIsActive()) continue;

            $deductionId = (int) $deduction->getId();
            if ($this->employeeDeductions->hasActiveForDeduction($employeeId, $deductionId, $effectiveFrom)) continue;

            $this->employeeDeductions->create(
                new EmployeeDeduction(null, $employeeId, $deductionId, null, $effectiveFrom, null, true, null, null)
            );
            $count++;
        }
        return $count;
    }

    /** [paydays in month, which payday this is] */
    private function paydaySlot(string $frequency, string $periodEnd): array {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $periodEnd, new DateTimeZone(self::TIMEZONE));
        if (!$date) throw new InvalidArgumentException("Invalid periodEnd: {$periodEnd}");
        $day = (int) $date->format('j');

        return match ($frequency) {
            'monthly'  => [1, 1],
            'kinsenas' => [2, $day <= 15 ? 1 : 2],
            default    => (function () use ($date, $day) {
                $index   = intdiv($day - 1, 7) + 1;
                $paydays = $index + intdiv((int) $date->format('t') - $day, 7);
                return [$paydays, $index];
            })(),
        };
    }

    private function sssMsc(string $basis): string {
        $steps = bcdiv(bcadd($basis, '250', 2), self::SSS_MSC_STEP, 0);
        return $this->clamp(bcmul($steps, self::SSS_MSC_STEP, 2), self::SSS_MSC_MIN, self::SSS_MSC_MAX);
    }

    private function clamp(string $v, string $lo, string $hi): string {
        if (bccomp($v, $lo, 2) < 0) return $lo;
        if (bccomp($v, $hi, 2) > 0) return $hi;
        return $v;
    }

    private function min(string $a, string $b): string {
        return bccomp($a, $b, 2) <= 0 ? $a : $b;
    }

    private function round(string $value): string {
        return bcadd($value, '0.005', 2);   // half-up, non-negative only
    }
}