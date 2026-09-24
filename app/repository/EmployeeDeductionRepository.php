<?php
namespace App\Repository;

class EmployeeDeductionRepository extends BaseRepository{
    public function findByEmployee(int $employeeId): array {
        $stmt = $this->db->prepare("SELECT * FROM employee_deductions WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function findByEmployeeAndPeriod(int $employeeId, string $start, string $end): array {
        $stmt = $this->db->prepare("SELECT ed.*, d.name AS deduction_name, d.code AS deduction_code
            FROM employee_deductions ed
            INNER JOIN deductions d ON d.id = ed.deduction_id
            WHERE ed.employee_id = ?
              AND ed.is_active = 1
              AND ed.effective_from <= ?
              AND (ed.effective_to IS NULL OR ed.effective_to >= ?)
            ORDER BY d.name ASC");

        $stmt->execute([$employeeId, $start, $end]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): int {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO employee_deductions (
                        employee_id,
                        deduction_id,
                        amount,
                        effective_from,
                        effective_to,
                        is_active
                    ) VALUES (
                        :employee_id,
                        :deduction_id,
                        :amount,
                        :effective_from,
                        :effective_to,
                        :is_active
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'employee_id'    => $data['employee_id'],
                'deduction_id'   => $data['deduction_id'],
                'amount'         => $data['amount'] ?? null,
                'effective_from' => $data['effective_from'],
                'effective_to'   => $data['effective_to'] ?? null,
                'is_active'      => $data['is_active'] ?? 1,
            ]);

            $newId = (int) $this->db->lastInsertId();
            $this->db->commit();

            return $newId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function update(int $id, array $data): bool {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE employee_deductions 
                    SET deduction_id   = :deduction_id,
                        amount         = :amount,
                        effective_from = :effective_from,
                        effective_to   = :effective_to,
                        is_active      = :is_active
                    WHERE id = :id";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                'id'             => $id,
                'deduction_id'   => $data['deduction_id'],
                'amount'         => $data['amount'] ?? null,
                'effective_from' => $data['effective_from'],
                'effective_to'   => $data['effective_to'] ?? null,
                'is_active'      => $data['is_active'] ?? 1,
            ]);

            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            if($this->db->inTransaction()) $this->db->rollback();
            error_log("Error updating employee deduction {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool {
        try {
            $stmt = $this->db->prepare("DELETE FROM employee_deductions WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (\PDOException $e) {
            error_log("Error deleting employee deduction {$id}: " . $e->getMessage());
            return false;
        }
    }

    public function sumByEmployeeAndPeriod(int $employeeId, string $start, string $end): float {
        $sql = "SELECT SUM(amount) AS total_deductions
                FROM employee_deductions
                WHERE employee_id = ?
                AND is_active = 1
                AND effective_from <= ?
                AND (effective_to IS NULL OR effective_to >= ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $start, $end]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (float) ($result['total_deductions'] ?? 0.00);
    }
}