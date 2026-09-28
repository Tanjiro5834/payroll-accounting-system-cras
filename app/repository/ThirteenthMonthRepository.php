<?php
namespace App\Repository;

use App\Entity\ThirteenthMonthRecord;
use PDO;
use Throwable;

class ThirteenthMonthRepository extends BaseRepository {
    private const UPSERT_CHUNK_SIZE = 500;

    private const COLUMNS = [
        'employee_id', 'year', 'months_worked', 'total_regular_hours', 'total_basic_salary',
        'thirteenth_month_pay', 'status', 'computed_by', 'computed_at', 'paid_at',
    ];

    private const REPORT_COLUMNS = 't.id, t.employee_id, e.full_name, e.role, e.pay_frequency,
                                    t.year, t.months_worked, t.total_regular_hours, t.total_basic_salary,
                                    t.thirteenth_month_pay, t.status, t.computed_by, t.computed_at,
                                    t.approved_by, t.approved_at, t.paid_at';

    private const REPORT_FROM = 'FROM thirteenth_month_records t
                                 INNER JOIN employees e ON e.id = t.employee_id';

    public function create(ThirteenthMonthRecord $record): int {
        $columns      = implode(', ', self::COLUMNS);
        $placeholders = implode(', ', array_fill(0, count(self::COLUMNS), '?'));

        $stmt = $this->db->prepare(
            "INSERT INTO thirteenth_month_records ({$columns}) VALUES ({$placeholders})"
        );
        $stmt->execute([
            $record->getEmployeeId(),
            $record->getYear(),
            $record->getMonthsWorked(),
            $record->getTotalRegularHours(),
            $record->getTotalBasicSalary(),
            $record->getThirteenthMonthSalary(),
            $record->getStatus(),
            $record->getComputedBy(),
            $record->getComputedAt(),
            $record->getPaidAt(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, ThirteenthMonthRecord $record): bool {
        $stmt = $this->db->prepare(
            "UPDATE thirteenth_month_records SET
                months_worked        = :months_worked,
                total_regular_hours  = :total_regular_hours,
                total_basic_salary   = :total_basic_salary,
                thirteenth_month_pay = :thirteenth_month_pay
             WHERE id = :id
               AND status = 'draft'"
        );
        $stmt->execute([
            ':months_worked'        => $record->getMonthsWorked(),
            ':total_regular_hours'  => $record->getTotalRegularHours(),
            ':total_basic_salary'   => $record->getTotalBasicSalary(),
            ':thirteenth_month_pay' => $record->getThirteenthMonthSalary(),
            ':id'                   => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function bulkUpsert(array $rows): int {
        if (empty($rows)) {
            return 0;
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $affected   = 0;
            $statements = [];

            foreach (array_chunk($rows, self::UPSERT_CHUNK_SIZE) as $chunk) {
                $count = count($chunk);
                $stmt  = $statements[$count] ??= $this->db->prepare($this->buildUpsertSql($count));

                $params = [];
                foreach ($chunk as $row) {
                    foreach (self::COLUMNS as $col) {
                        $params[] = $row[$col] ?? null;
                    }
                }

                $stmt->execute($params);
                $affected += $stmt->rowCount();
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $affected;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function approve(int $id, int $approvedBy): bool {
        $stmt = $this->db->prepare(
            "UPDATE thirteenth_month_records
             SET status = 'approved', approved_by = ?, approved_at = NOW()
             WHERE id = ?
               AND status = 'draft'"
        );
        $stmt->execute([$approvedBy, $id]);

        return $stmt->rowCount() > 0;
    }

    public function markAsPaid(int $id, string $paidAt): bool {
        $stmt = $this->db->prepare(
            "UPDATE thirteenth_month_records
             SET status = 'paid', paid_at = ?
             WHERE id = ?
               AND status = 'approved'"
        );
        $stmt->execute([$paidAt, $id]);

        return $stmt->rowCount() > 0;
    }

    public function deleteDraft(int $id): bool {
        $stmt = $this->db->prepare(
            "DELETE FROM thirteenth_month_records WHERE id = ? AND status = 'draft'"
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::REPORT_COLUMNS . " " . self::REPORT_FROM . " WHERE t.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByEmployee(int $employeeId): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::REPORT_COLUMNS . " " . self::REPORT_FROM . "
             WHERE t.employee_id = ?
             ORDER BY t.year DESC"
        );
        $stmt->execute([$employeeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEmployeeAndYear(int $employeeId, int $year): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::REPORT_COLUMNS . " " . self::REPORT_FROM . "
             WHERE t.employee_id = ? AND t.year = ?
             LIMIT 1"
        );
        $stmt->execute([$employeeId, $year]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByYear(int $year): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::REPORT_COLUMNS . " " . self::REPORT_FROM . "
             WHERE t.year = ?
             ORDER BY e.full_name"
        );
        $stmt->execute([$year]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByStatus(string $status): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::REPORT_COLUMNS . " " . self::REPORT_FROM . "
             WHERE t.status = ?
             ORDER BY t.year DESC, e.full_name"
        );
        $stmt->execute([$status]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findUnpaidByYear(int $year): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::REPORT_COLUMNS . " " . self::REPORT_FROM . "
             WHERE t.year = ? AND t.status <> 'paid'
             ORDER BY e.full_name"
        );
        $stmt->execute([$year]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAll(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::REPORT_COLUMNS . " " . self::REPORT_FROM . "
             ORDER BY t.year DESC, e.full_name"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sumByYear(int $year): string {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(thirteenth_month_pay), 0)
             FROM thirteenth_month_records
             WHERE year = ?"
        );
        $stmt->execute([$year]);

        return (string) $stmt->fetchColumn();
    }

    public function sumPaidByYear(int $year): string {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(thirteenth_month_pay), 0)
             FROM thirteenth_month_records
             WHERE year = ? AND status = 'paid'"
        );
        $stmt->execute([$year]);

        return (string) $stmt->fetchColumn();
    }

    public function countByYear(int $year): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM thirteenth_month_records WHERE year = ?"
        );
        $stmt->execute([$year]);

        return (int) $stmt->fetchColumn();
    }

    public function countByStatus(string $status): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM thirteenth_month_records WHERE status = ?"
        );
        $stmt->execute([$status]);

        return (int) $stmt->fetchColumn();
    }

    public function existsForEmployeeAndYear(int $employeeId, int $year, ?int $exceptId = null): bool {
        $sql    = "SELECT 1 FROM thirteenth_month_records WHERE employee_id = ? AND year = ?";
        $params = [$employeeId, $year];

        if ($exceptId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $exceptId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    private function buildUpsertSql(int $rowCount): string {
        $tuple        = '(' . implode(', ', array_fill(0, count(self::COLUMNS), '?')) . ')';
        $placeholders = implode(', ', array_fill(0, $rowCount, $tuple));

        return "
            INSERT INTO thirteenth_month_records (" . implode(', ', self::COLUMNS) . ")
            VALUES {$placeholders}
            ON DUPLICATE KEY UPDATE
                months_worked        = IF(status = 'draft', VALUES(months_worked),        months_worked),
                total_regular_hours  = IF(status = 'draft', VALUES(total_regular_hours),  total_regular_hours),
                total_basic_salary   = IF(status = 'draft', VALUES(total_basic_salary),   total_basic_salary),
                thirteenth_month_pay = IF(status = 'draft', VALUES(thirteenth_month_pay), thirteenth_month_pay),
                computed_by          = IF(status = 'draft', VALUES(computed_by),          computed_by),
                computed_at          = IF(status = 'draft', VALUES(computed_at),          computed_at)
        ";
    }
}