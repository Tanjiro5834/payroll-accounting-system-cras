<?php
namespace App\Repository;

use PDO;

class PayrollRepository extends BaseRepository {
    private const COLUMNS = 'pp.id, pp.employee_id, pp.period_start, pp.period_end, pp.pay_frequency,
                             pp.total_regular_hours, pp.total_overtime_hours, pp.total_night_diff_hours,
                             pp.total_late_minutes, pp.total_undertime_minutes, pp.hourly_rate,
                             pp.premium_pay, pp.premium_details,
                             pp.gross_pay, pp.total_deductions, pp.net_pay, pp.status,
                             pp.computed_by, pp.computed_at, pp.paid_at, pp.notes';

    private const WITH_EMPLOYEE = self::COLUMNS . ',
                                   e.full_name,
                                   e.role AS employee_role';

    private const INSERTABLE = [
        'employee_id', 'period_start', 'period_end', 'pay_frequency',
        'total_regular_hours', 'total_overtime_hours', 'total_night_diff_hours',
        'total_late_minutes', 'total_undertime_minutes', 'hourly_rate',
        'premium_pay', 'premium_details', 'gross_pay', 'total_deductions', 'net_pay',
        'status', 'computed_by', 'computed_at', 'paid_at', 'notes',
    ];

    public function createPeriod(array $data): int {
        $columns      = implode(', ', self::INSERTABLE);
        $placeholders = implode(', ', array_map(fn(string $c) => ':' . $c, self::INSERTABLE));

        $params = [];
        foreach (self::INSERTABLE as $column) {
            $params[':' . $column] = $data[$column] ?? $this->defaultFor($column);
        }

        $stmt = $this->db->prepare(
            "INSERT INTO payroll_periods ({$columns}) VALUES ({$placeholders})"
        );
        $stmt->execute($params);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_EMPLOYEE . "
             FROM payroll_periods pp
             LEFT JOIN employees e ON e.id = pp.employee_id
             WHERE pp.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByEmployee(int $employeeId, int $limit = 100): array {
        $limit = $this->clampLimit($limit);

        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM payroll_periods pp
             WHERE pp.employee_id = :employee_id
             ORDER BY pp.period_start DESC, pp.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByPeriod(string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_EMPLOYEE . "
             FROM payroll_periods pp
             LEFT JOIN employees e ON e.id = pp.employee_id
             WHERE pp.period_start >= :start
               AND pp.period_start <  :end
             ORDER BY pp.period_start ASC, e.full_name"
        );
        $stmt->execute([
            ':start' => $start,
            ':end'   => $this->nextDay($end),
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByDateRange(string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_EMPLOYEE . "
             FROM payroll_periods pp
             LEFT JOIN employees e ON e.id = pp.employee_id
             WHERE pp.period_start <= :end
               AND pp.period_end   >= :start
             ORDER BY pp.period_start DESC, pp.id DESC"
        );
        $stmt->execute([
            ':start' => $start,
            ':end'   => $end,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByStatus(string $status, int $limit = 200): array {
        $limit = $this->clampLimit($limit);

        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_EMPLOYEE . "
             FROM payroll_periods pp
             LEFT JOIN employees e ON e.id = pp.employee_id
             WHERE pp.status = :status
             ORDER BY pp.period_start DESC, pp.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPendingApproval(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_EMPLOYEE . "
             FROM payroll_periods pp
             LEFT JOIN employees e ON e.id = pp.employee_id
             WHERE pp.status IN ('draft', 'computed')
             ORDER BY pp.period_start ASC, e.full_name"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findExactPeriod(string $start, string $end, int $employeeId = 0, string $frequency = ''): array {
        $sql    = "SELECT " . self::WITH_EMPLOYEE . "
                   FROM payroll_periods pp
                   LEFT JOIN employees e ON e.id = pp.employee_id
                   WHERE pp.period_start = ? AND pp.period_end = ?";
        $params = [$start, $end];

        if ($employeeId > 0) {
            $sql     .= " AND pp.employee_id = ?";
            $params[] = $employeeId;
        }
        if ($frequency !== '') {
            $sql     .= " AND pp.pay_frequency = ?";
            $params[] = $frequency;
        }

        $stmt = $this->db->prepare($sql . " ORDER BY e.full_name");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findDuplicate(int $employeeId, string $start, string $end, ?int $exceptId = null): ?array {
        $sql = "SELECT " . self::COLUMNS . "
                FROM payroll_periods pp
                WHERE pp.employee_id = :employee_id
                  AND pp.period_start = :start
                  AND pp.period_end   = :end";
        $params = [
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $end,
        ];

        if ($exceptId !== null) {
            $sql .= " AND pp.id <> :except_id";
            $params[':except_id'] = $exceptId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getLatestByEmployee(int $employeeId): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM payroll_periods pp
             WHERE pp.employee_id = ?
             ORDER BY pp.period_start DESC, pp.id DESC
             LIMIT 1"
        );
        $stmt->execute([$employeeId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function approve(int $id, ?int $approvedBy): bool {
        $stmt = $this->db->prepare(
            "UPDATE payroll_periods
             SET status = 'approved', approved_by = :approved_by, approved_at = NOW()
             WHERE id = :id
               AND status = 'computed'"
        );
        $stmt->bindValue(':approved_by', $approvedBy, $approvedBy === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function updateStatus(int $id, string $fromStatus, string $toStatus, ?int $computedBy = null): bool {
        $stmt = $this->db->prepare(
            "UPDATE payroll_periods
             SET status = :to_status,
                 computed_by = COALESCE(:computed_by, computed_by)
             WHERE id = :id
               AND status = :from_status"
        );
        $stmt->bindValue(':to_status', $toStatus, PDO::PARAM_STR);
        $stmt->bindValue(':computed_by', $computedBy, $computedBy === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':from_status', $fromStatus, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function markAsPaid(int $id, string $paidAt): bool {
        $stmt = $this->db->prepare(
            "UPDATE payroll_periods
             SET status = 'paid', paid_at = :paid_at
             WHERE id = :id
               AND status = 'approved'"
        );
        $stmt->execute([
            ':paid_at' => $paidAt,
            ':id'      => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateAmounts(int $id, array $amounts, string $fromStatus = 'draft'): bool {
        $updatable = ['gross_pay', 'total_deductions', 'net_pay'];
        $fields    = array_intersect_key($amounts, array_flip($updatable));

        if (!$fields) {
            return false;
        }

        $set = implode(', ', array_map(fn(string $c) => "{$c} = :{$c}", array_keys($fields)));

        $params = [':id' => $id, ':from_status' => $fromStatus];
        foreach ($fields as $column => $value) {
            $params[':' . $column] = $value;
        }

        $stmt = $this->db->prepare(
            "UPDATE payroll_periods
             SET {$set}
             WHERE id = :id
               AND status = :from_status"
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function deleteById(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM payroll_periods WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function deleteDraftById(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM payroll_periods WHERE id = ? AND status = 'draft'");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function summarizeByYear(int $year): array {
        $start = sprintf('%04d-01-01', $year);
        $end   = sprintf('%04d-01-01', $year + 1);

        $stmt = $this->db->prepare(
            "SELECT employee_id,
                    SUM(ROUND(total_regular_hours * hourly_rate, 2)) AS total_basic,
                    SUM(total_regular_hours)                         AS total_hours,
                    COUNT(DISTINCT MONTH(period_end))                AS months_worked
             FROM payroll_periods
             WHERE period_end >= :start
               AND period_end <  :end
               AND status IN ('approved', 'paid')
             GROUP BY employee_id"
        );
        $stmt->execute([
            ':start' => $start,
            ':end'   => $end,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[(int) $row['employee_id']] = [
                'employee_id'   => (int) $row['employee_id'],
                'total_basic'   => (string) $row['total_basic'],
                'total_hours'   => (string) $row['total_hours'],
                'months_worked' => (int) $row['months_worked'],
            ];
        }

        return $keyed;
    }

    public function sumPaidByYear(int $year): string {
        $start = sprintf('%04d-01-01', $year);
        $end   = sprintf('%04d-01-01', $year + 1);

        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(net_pay), 0)
             FROM payroll_periods
             WHERE period_end >= :start
               AND period_end <  :end
               AND status = 'paid'"
        );
        $stmt->execute([
            ':start' => $start,
            ':end'   => $end,
        ]);

        return (string) $stmt->fetchColumn();
    }

    private function clampLimit(int $limit): int {
        return max(1, min(500, $limit));
    }

    private function nextDay(string $date): string {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('Asia/Manila'));
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException("Invalid date: {$date}");
        }
        return $dt->modify('+1 day')->format('Y-m-d');
    }

    private function defaultFor(string $column): mixed {
        return match ($column) {
            'total_regular_hours',
            'total_overtime_hours',
            'total_night_diff_hours',
            'total_late_minutes',
            'total_undertime_minutes',
            'hourly_rate',
            'premium_pay',
            'gross_pay',
            'total_deductions',
            'net_pay'        => '0.00',
            'status'         => 'draft',
            'computed_at'    => date('Y-m-d H:i:s'),
            default          => null,
        };
    }
}