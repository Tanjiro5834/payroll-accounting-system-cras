<?php
namespace App\Repository;

use PDO;

class PayrollDeductionRepository extends BaseRepository {
    private const COLUMNS = 'pd.id, pd.payroll_period_id, pd.deduction_id, pd.amount, pd.created_at';

    private const WITH_DEDUCTION = self::COLUMNS . ',
                                   d.code AS deduction_code,
                                   d.name AS deduction_name,
                                   d.type AS deduction_type';

    private const BULK_CHUNK_SIZE = 2000;

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_DEDUCTION . "
             FROM payroll_deductions pd
             INNER JOIN deductions d ON d.id = pd.deduction_id
             WHERE pd.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByPayrollPeriod(int $payrollPeriodId): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_DEDUCTION . "
             FROM payroll_deductions pd
             INNER JOIN deductions d ON d.id = pd.deduction_id
             WHERE pd.payroll_period_id = ?
             ORDER BY d.name"
        );
        $stmt->execute([$payrollPeriodId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByDeduction(int $deductionId): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_DEDUCTION . "
             FROM payroll_deductions pd
             INNER JOIN deductions d ON d.id = pd.deduction_id
             WHERE pd.deduction_id = ?
             ORDER BY pd.payroll_period_id DESC, pd.id DESC"
        );
        $stmt->execute([$deductionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAll(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_DEDUCTION . "
             FROM payroll_deductions pd
             INNER JOIN deductions d ON d.id = pd.deduction_id
             ORDER BY pd.payroll_period_id DESC, d.name"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $payrollPeriodId, int $deductionId, string $amount): int {
        $stmt = $this->db->prepare(
            "INSERT INTO payroll_deductions (payroll_period_id, deduction_id, amount)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$payrollPeriodId, $deductionId, $amount]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $amount): bool {
        $stmt = $this->db->prepare(
            "UPDATE payroll_deductions SET amount = :amount WHERE id = :id"
        );
        $stmt->execute([
            ':amount' => $amount,
            ':id'     => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM payroll_deductions WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function deleteByPayrollPeriod(int $payrollPeriodId): int {
        $stmt = $this->db->prepare(
            "DELETE FROM payroll_deductions WHERE payroll_period_id = ?"
        );
        $stmt->execute([$payrollPeriodId]);

        return $stmt->rowCount();
    }

    public function sumByPayrollPeriod(int $payrollPeriodId): string {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM payroll_deductions
             WHERE payroll_period_id = ?"
        );
        $stmt->execute([$payrollPeriodId]);

        return (string) $stmt->fetchColumn();
    }

    public function bulkInsert(int $payrollPeriodId, array $rows): int {
        if (empty($rows)) {
            return 0;
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $affected = 0;
            $statements = [];

            foreach (array_chunk($rows, self::BULK_CHUNK_SIZE) as $chunk) {
                $count = count($chunk);
                $stmt  = $statements[$count] ??= $this->db->prepare($this->buildBulkInsertSql($count));

                $params = [];
                foreach ($chunk as $row) {
                    $params[] = $payrollPeriodId;
                    $params[] = (int) $row['deduction_id'];
                    $params[] = (string) $row['amount'];
                }

                $stmt->execute($params);
                $affected += $stmt->rowCount();
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $affected;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function replaceForPayrollPeriod(int $payrollPeriodId, array $rows): int {
        return $this->transaction(function () use ($payrollPeriodId, $rows) {
            $this->deleteByPayrollPeriod($payrollPeriodId);
            return $this->bulkInsert($payrollPeriodId, $rows);
        });
    }

    private function buildBulkInsertSql(int $rowCount): string {
        $tuple        = '(?, ?, ?)';
        $placeholders = implode(', ', array_fill(0, $rowCount, $tuple));

        return "INSERT INTO payroll_deductions (payroll_period_id, deduction_id, amount)
                VALUES {$placeholders}";
    }
}