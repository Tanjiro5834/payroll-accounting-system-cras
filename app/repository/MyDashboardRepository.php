<?php
namespace App\Repository;

use PDO;

// Read-only queries scoped to ONE employee. Every method takes employee_id from the session,
// never from the request, so an employee can only ever see their own records.
class MyDashboardRepository extends BaseRepository {
    private const PAYSLIP_COLUMNS = 'pp.id, pp.period_start, pp.period_end, pp.pay_frequency,
                                     pp.total_regular_hours, pp.total_overtime_hours, pp.total_night_diff_hours,
                                     pp.total_late_minutes, pp.total_undertime_minutes, pp.hourly_rate,
                                     pp.gross_pay, pp.total_deductions, pp.net_pay, pp.status,
                                     pp.approved_at, pp.paid_at, u.username AS approved_by';

    // Only approved/paid payrolls: computed ones can still change on recompute.
    public function findReleasedPayslips(int $employeeId, int $limit = 52): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::PAYSLIP_COLUMNS . "
             FROM payroll_periods pp
             LEFT JOIN users u ON u.id = pp.approved_by
             WHERE pp.employee_id = :employee_id
               AND pp.status IN ('approved', 'paid')
             ORDER BY pp.period_end DESC, pp.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findReleasedPayslip(int $employeeId, int $payrollId): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::PAYSLIP_COLUMNS . "
             FROM payroll_periods pp
             LEFT JOIN users u ON u.id = pp.approved_by
             WHERE pp.id = ?
               AND pp.employee_id = ?
               AND pp.status IN ('approved', 'paid')
             LIMIT 1"
        );
        $stmt->execute([$payrollId, $employeeId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function sumPaidNetForYear(int $employeeId, int $year): string {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(net_pay), 0)
             FROM payroll_periods
             WHERE employee_id = ?
               AND status = 'paid'
               AND period_end >= ? AND period_end <= ?"
        );
        $stmt->execute([$employeeId, "{$year}-01-01", "{$year}-12-31"]);

        return (string) $stmt->fetchColumn();
    }

    public function findThirteenthMonth(int $employeeId, int $year): ?array {
        $stmt = $this->db->prepare(
            "SELECT t.id, t.year, t.months_worked, t.total_basic_salary, t.thirteenth_month_pay,
                    t.status, t.approved_at, t.paid_at, u.username AS approved_by
             FROM thirteenth_month_records t
             LEFT JOIN users u ON u.id = t.approved_by
             WHERE t.employee_id = ?
               AND t.year = ?
               AND t.status IN ('approved', 'paid')
             LIMIT 1"
        );
        $stmt->execute([$employeeId, $year]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // Per-month attendance aggregates from daily_summary, oldest first.
    public function monthlyTrend(int $employeeId, string $fromDate): array {
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(work_date, '%Y-%m')               AS month,
                    SUM(regular_hours > 0 OR overtime_hours > 0)   AS days_worked,
                    SUM(late_minutes > 0)                          AS late_days,
                    COALESCE(SUM(late_minutes), 0)                 AS late_minutes,
                    SUM(overtime_hours > 0)                        AS ot_days,
                    COALESCE(SUM(overtime_hours), 0)               AS ot_hours,
                    SUM(undertime_minutes > 0)                     AS undertime_days,
                    COALESCE(SUM(undertime_minutes), 0)            AS undertime_minutes
             FROM daily_summary
             WHERE employee_id = ?
               AND work_date >= ?
             GROUP BY DATE_FORMAT(work_date, '%Y-%m')
             ORDER BY month"
        );
        $stmt->execute([$employeeId, $fromDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
