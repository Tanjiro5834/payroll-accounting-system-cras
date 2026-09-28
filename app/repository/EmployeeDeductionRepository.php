<?php
namespace App\Repository;

use App\Entity\EmployeeDeduction;
use PDO;

class EmployeeDeductionRepository extends BaseRepository {
    private const COLUMNS = 'ed.id, ed.employee_id, ed.deduction_id, ed.amount,
                             ed.effective_from, ed.effective_to, ed.is_active,
                             ed.created_at, ed.updated_at';

    private const WITH_DEDUCTION = self::COLUMNS . ',
                                   d.code AS deduction_code,
                                   d.name AS deduction_name,
                                   d.type AS deduction_type,
                                   d.value AS deduction_value,
                                   d.is_mandatory,
                                   d.is_active AS deduction_is_active';

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM employee_deductions ed WHERE ed.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByEmployee(int $employeeId): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_DEDUCTION . "
             FROM employee_deductions ed
             INNER JOIN deductions d ON d.id = ed.deduction_id
             WHERE ed.employee_id = ?
             ORDER BY d.name"
        );
        $stmt->execute([$employeeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActiveByEmployee(int $employeeId, string $onDate): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_DEDUCTION . "
             FROM employee_deductions ed
             INNER JOIN deductions d ON d.id = ed.deduction_id
             WHERE ed.employee_id = :employee_id
               AND ed.is_active = 1
               AND ed.effective_from <= :on_date
               AND (ed.effective_to IS NULL OR ed.effective_to >= :on_date)
             ORDER BY d.name"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':on_date'     => $onDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEmployeeAndPeriod(int $employeeId, string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_DEDUCTION . "
             FROM employee_deductions ed
             INNER JOIN deductions d ON d.id = ed.deduction_id
             WHERE ed.employee_id = :employee_id
               AND ed.is_active = 1
               AND ed.effective_from <= :end
               AND (ed.effective_to IS NULL OR ed.effective_to >= :start)
             ORDER BY d.name"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $end,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByDeduction(int $deductionId): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM employee_deductions ed
             WHERE ed.deduction_id = ?
             ORDER BY ed.effective_from DESC"
        );
        $stmt->execute([$deductionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAll(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_DEDUCTION . "
             FROM employee_deductions ed
             INNER JOIN deductions d ON d.id = ed.deduction_id
             ORDER BY ed.employee_id, d.name"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(EmployeeDeduction $employeeDeduction): int {
        $stmt = $this->db->prepare(
            "INSERT INTO employee_deductions
                (employee_id, deduction_id, amount, effective_from, effective_to, is_active)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $employeeDeduction->getEmployeeId(),
            $employeeDeduction->getDeductionId(),
            $employeeDeduction->getAmount(),
            $employeeDeduction->getEffectiveFrom(),
            $employeeDeduction->getEffectiveTo(),
            $employeeDeduction->getIsActive() ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, EmployeeDeduction $employeeDeduction): bool {
        $stmt = $this->db->prepare(
            "UPDATE employee_deductions SET
                deduction_id   = :deduction_id,
                amount         = :amount,
                effective_from = :effective_from,
                effective_to   = :effective_to,
                is_active      = :is_active
             WHERE id = :id"
        );
        $stmt->execute([
            ':deduction_id'   => $employeeDeduction->getDeductionId(),
            ':amount'         => $employeeDeduction->getAmount(),
            ':effective_from' => $employeeDeduction->getEffectiveFrom(),
            ':effective_to'   => $employeeDeduction->getEffectiveTo(),
            ':is_active'      => $employeeDeduction->getIsActive() ? 1 : 0,
            ':id'             => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deactivate(int $id): bool {
        $stmt = $this->db->prepare(
            "UPDATE employee_deductions SET is_active = 0 WHERE id = ? AND is_active = 1"
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM employee_deductions WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function sumByEmployeeAndPeriod(int $employeeId, string $start, string $end): string {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM employee_deductions
             WHERE employee_id = :employee_id
               AND is_active = 1
               AND effective_from <= :end
               AND (effective_to IS NULL OR effective_to >= :start)"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $end,
        ]);

        return (string) $stmt->fetchColumn();
    }

    public function hasActiveForDeduction(int $employeeId, int $deductionId, string $onDate): bool {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM employee_deductions
             WHERE employee_id = :employee_id
               AND deduction_id = :deduction_id
               AND is_active = 1
               AND effective_from <= :on_date
               AND (effective_to IS NULL OR effective_to >= :on_date)
             LIMIT 1"
        );
        $stmt->execute([
            ':employee_id'  => $employeeId,
            ':deduction_id' => $deductionId,
            ':on_date'      => $onDate,
        ]);

        return $stmt->fetchColumn() !== false;
    }
}