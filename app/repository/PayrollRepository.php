<?php
namespace App\Repository;

class PayrollRepository extends BaseRepository {
    public function createPeriod(array $data): int {
        try{
            $stmt = $this->db->prepare("INSERT INTO payroll_periods (
                employee_id,
                period_start,
                period_end,
                pay_frequency,
                total_regular_hours,
                total_overtime_hours,
                total_night_diff_hours,
                total_late_minutes,
                total_undertime_minutes,
                hourly_rate,
                gross_pay,
                total_deductions,
                net_pay,
                status,
                computed_by,
                computed_at,
                paid_at,
                notes
            ) VALUES (
                :employee_id,
                :period_start,
                :period_end,
                :pay_frequency,
                :total_regular_hours,
                :total_overtime_hours,
                :total_night_diff_hours,
                :total_late_minutes,
                :total_undertime_minutes,
                :hourly_rate,
                :gross_pay,
                :total_deductions,
                :net_pay,
                :status,
                :computed_by,
                :computed_at,
                :paid_at,
                :notes
            )");

            $stmt->execute([
                'employee_id'             => $data['employee_id'],
                'period_start'            => $data['period_start'],
                'period_end'              => $data['period_end'],
                'pay_frequency'           => $data['pay_frequency'], // 'weekly', 'kinsenas', or 'monthly'
                'total_regular_hours'     => $data['total_regular_hours'] ?? 0.00,
                'total_overtime_hours'    => $data['total_overtime_hours'] ?? 0.00,
                'total_night_diff_hours'  => $data['total_night_diff_hours'] ?? 0.00,
                'total_late_minutes'      => $data['total_late_minutes'] ?? 0,
                'total_undertime_minutes' => $data['total_undertime_minutes'] ?? 0,
                'hourly_rate'             => $data['hourly_rate'] ?? 0.00,
                'gross_pay'               => $data['gross_pay'] ?? 0.00,
                'total_deductions'        => $data['total_deductions'] ?? 0.00,
                'net_pay'                 => $data['net_pay'] ?? 0.00,
                'status'                  => $data['status'] ?? 'draft', // 'draft', 'computed', 'approved', or 'paid'
                'computed_by'             => $data['computed_by'] ?? null,
                'computed_at'             => $data['computed_at'] ?? date('Y-m-d H:i:s'),
                'paid_at'                 => $data['paid_at'] ?? null,
                'notes'                   => $data['notes'] ?? null,
            ]);

            $newId = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $newId;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            error_log("Failed to create payroll" . $e->getMessage());
            throw $e;
        }
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM payroll_periods WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByEmployee(int $employeeId): array {
         $sql = "SELECT pp.*, e.first_name, e.last_name
            FROM payroll_periods pp
            LEFT JOIN employees e ON e.id = pp.employee_id
            WHERE pp.employee_id = :employee_id
            ORDER BY pp.period_start DESC";

        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByDateRange(string $start, string $end): array {
        $sql = "SELECT pp.*, e.first_name, e.last_name 
                FROM payroll_periods pp
                LEFT JOIN employees e ON e.id = pp.employee_id
                WHERE pp.period_start >= :start
                AND pp.period_end <= :end
                ORDER BY pp.period_start DESC";

        $stmt->execute([$start, $end]);
        $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByStatus(string $status): array {
        $stmt = "SELECT * FROM payroll_periods WHERE status = ?";
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus(int $id, string $status): bool {
        try{
            $this->db->beginTransaction();
            $stmt = "UPDATE payroll_periods SET status = :status WHERE id = ?";
            $stmt->execute([$id, $status]);
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }

    public function markAsPaid(int $id, string $paidAt): bool {
        try{
            $this->db->beginTransaction();

            $sql = "UPDATE payroll_periods 
                SET status = 'paid', paid_at = ? 
                WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$paidAt, $id]);

            $affectedRows = $stmt->rowCount();
            
            $this->db->commit();

            return $affectedRows > 0;
        }catch(Exception $e){
            if ($this->db->inTransaction()) $this->db->rollBack();
            return false;
        }
    }

    public function deleteById(int $id): bool {
        try{
            $stmt = $this->db->prepare("DELETE FROM payroll_periods WHERE id = ?");
            $stmt->execute([$id]);
            $this->db->commit();
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }

    public function getLatestByEmployee(int $employeeId): ?array {
        $sql = "SELECT * FROM payroll_periods 
            WHERE employee_id = :employee_id 
            ORDER BY period_start DESC, id DESC 
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['employee_id' => $employeeId]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}