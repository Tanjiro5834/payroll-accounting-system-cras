<?php
namespace App\Repository;

use PDO;

class DeductionCarryoverRepository extends BaseRepository {

    /** @return array<int, string> deduction_id => shortfall from the latest earlier payroll */
    public function latestShortfalls(int $employeeId, string $beforeDate): array {
        $stmt = $this->db->prepare(
            "SELECT deduction_id, shortfall FROM (
                SELECT dc.deduction_id, dc.shortfall,
                       ROW_NUMBER() OVER (PARTITION BY dc.deduction_id
                                          ORDER BY dc.period_end DESC, dc.id DESC) AS rn
                FROM deduction_carryovers dc
                INNER JOIN payroll_periods pp ON pp.id = dc.payroll_period_id
                WHERE dc.employee_id = :employee_id
                  AND dc.period_end  < :before_date
             ) latest
             WHERE rn = 1"
        );
        $stmt->execute([':employee_id' => $employeeId, ':before_date' => $beforeDate]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['deduction_id']] = (string) $row['shortfall'];
        }
        return $map;
    }

    public function findByPayrollPeriod(int $payrollPeriodId): array {
        $stmt = $this->db->prepare(
            "SELECT dc.id, dc.deduction_id, d.code, d.name, dc.amount_due, dc.amount_deducted, dc.shortfall
             FROM deduction_carryovers dc
             INNER JOIN deductions d ON d.id = dc.deduction_id
             WHERE dc.payroll_period_id = ?
             ORDER BY d.name"
        );
        $stmt->execute([$payrollPeriodId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function replaceForPayrollPeriod(int $payrollPeriodId, int $employeeId, string $periodEnd, array $rows): int {
        return $this->transaction(function () use ($payrollPeriodId, $employeeId, $periodEnd, $rows) {
            $this->deleteByPayrollPeriod($payrollPeriodId);
            if (!$rows) {
                return 0;
            }
            $stmt = $this->db->prepare(
                "INSERT INTO deduction_carryovers
                    (payroll_period_id, employee_id, deduction_id, period_end, amount_due, amount_deducted, shortfall)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($rows as $r) {
                $stmt->execute([
                    $payrollPeriodId, $employeeId, $r['deduction_id'], $periodEnd,
                    $r['amount_due'], $r['amount_deducted'], $r['shortfall'],
                ]);
            }
            return count($rows);
        });
    }

    public function deleteByPayrollPeriod(int $payrollPeriodId): int {
        $stmt = $this->db->prepare("DELETE FROM deduction_carryovers WHERE payroll_period_id = ?");
        $stmt->execute([$payrollPeriodId]);
        return $stmt->rowCount();
    }
}