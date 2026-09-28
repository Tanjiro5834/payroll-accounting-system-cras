<?php
namespace App\Repository;

use PDO;

class DailySummaryRepository extends BaseRepository {
    private const COLUMNS = 'id, employee_id, work_date, regular_hours, overtime_hours,
                             night_diff_hours, late_minutes, undertime_minutes, is_rest_day, computed_at';

    public function upsert(array $data): void {
        $stmt = $this->db->prepare(
            "INSERT INTO daily_summary
                (employee_id, work_date, regular_hours, overtime_hours,
                 night_diff_hours, late_minutes, undertime_minutes, is_rest_day)
             VALUES
                (:employee_id, :work_date, :regular_hours, :overtime_hours,
                 :night_diff_hours, :late_minutes, :undertime_minutes, :is_rest_day)
             ON DUPLICATE KEY UPDATE
                regular_hours     = VALUES(regular_hours),
                overtime_hours    = VALUES(overtime_hours),
                night_diff_hours  = VALUES(night_diff_hours),
                late_minutes      = VALUES(late_minutes),
                undertime_minutes = VALUES(undertime_minutes),
                is_rest_day       = VALUES(is_rest_day)"
        );

        $stmt->execute([
            ':employee_id'      => $data['employee_id'],
            ':work_date'        => $data['work_date'],
            ':regular_hours'    => $data['regular_hours']     ?? 0,
            ':overtime_hours'   => $data['overtime_hours']    ?? 0,
            ':night_diff_hours' => $data['night_diff_hours']  ?? 0,
            ':late_minutes'     => $data['late_minutes']      ?? 0,
            ':undertime_minutes'=> $data['undertime_minutes'] ?? 0,
            ':is_rest_day'      => $data['is_rest_day']       ?? 0,
        ]);
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT " . self::COLUMNS . " FROM daily_summary WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByEmployeeAndDate(int $employeeId, string $date): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM daily_summary
             WHERE employee_id = ? AND work_date = ?"
        );
        $stmt->execute([$employeeId, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByPeriod(int $employeeId, string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM daily_summary
             WHERE employee_id = :employee_id
               AND work_date >= :start
               AND work_date <  :end
             ORDER BY work_date"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $this->nextDay($end),
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByDate(string $date): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM daily_summary WHERE work_date = ? ORDER BY employee_id"
        );
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sumByYear(int $employeeId, int $year): array {
        $start = sprintf('%04d-01-01', $year);
        $end   = sprintf('%04d-01-01', $year + 1);

        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(regular_hours), 0)    AS total_regular,
                    COALESCE(SUM(overtime_hours), 0)   AS total_overtime,
                    COALESCE(SUM(night_diff_hours), 0) AS total_night_diff,
                    COUNT(DISTINCT work_date)          AS days_worked
             FROM daily_summary
             WHERE employee_id = :employee_id
               AND work_date >= :start
               AND work_date <  :end"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $end,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_regular'    => '0',
            'total_overtime'   => '0',
            'total_night_diff' => '0',
            'days_worked'      => 0,
        ];
    }

    public function sumByPeriod(int $employeeId, string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(regular_hours), 0)    AS total_regular,
                    COALESCE(SUM(overtime_hours), 0)   AS total_overtime,
                    COALESCE(SUM(night_diff_hours), 0) AS total_night_diff,
                    COUNT(DISTINCT work_date)          AS days_worked
             FROM daily_summary
             WHERE employee_id = :employee_id
               AND work_date >= :start
               AND work_date <  :end"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $this->nextDay($end),
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_regular'    => '0',
            'total_overtime'   => '0',
            'total_night_diff' => '0',
            'days_worked'      => 0,
        ];
    }

    public function countDaysWorked(int $employeeId, string $start, string $end): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM daily_summary
             WHERE employee_id = :employee_id
               AND work_date >= :start
               AND work_date <  :end
               AND regular_hours > 0
               AND is_rest_day = 0"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $this->nextDay($end),
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function countDistinctMonthsWorked(int $employeeId, int $year): int {
        $start = sprintf('%04d-01-01', $year);
        $end   = sprintf('%04d-01-01', $year + 1);

        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT DATE_FORMAT(work_date, '%Y-%m'))
             FROM daily_summary
             WHERE employee_id = :employee_id
               AND work_date >= :start
               AND work_date <  :end
               AND regular_hours > 0
               AND is_rest_day = 0"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $end,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function countMonthsWorked(int $employeeId, int $year): array {
        $start = sprintf('%04d-01-01', $year);
        $end   = sprintf('%04d-01-01', $year + 1);

        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(work_date, '%Y-%m') AS month_year,
                    COUNT(*)                        AS days_worked,
                    COALESCE(SUM(regular_hours), 0) AS total_regular_hours,
                    COALESCE(SUM(overtime_hours), 0) AS total_overtime_hours
             FROM daily_summary
             WHERE employee_id = :employee_id
               AND work_date >= :start
               AND work_date <  :end
               AND regular_hours > 0
               AND is_rest_day = 0
             GROUP BY month_year
             ORDER BY month_year DESC"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $end,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteByEmployeeAndDate(int $employeeId, string $date): bool {
        $stmt = $this->db->prepare(
            "DELETE FROM daily_summary WHERE employee_id = ? AND work_date = ?"
        );
        $stmt->execute([$employeeId, $date]);

        return $stmt->rowCount() > 0;
    }

    public function deleteByYear(int $year): int {
        $start = sprintf('%04d-01-01', $year);
        $end   = sprintf('%04d-01-01', $year + 1);

        $stmt = $this->db->prepare(
            "DELETE FROM daily_summary WHERE work_date >= ? AND work_date < ?"
        );
        $stmt->execute([$start, $end]);

        return $stmt->rowCount();
    }

    private function nextDay(string $date): string {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('Asia/Manila'));
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException("Invalid date: {$date}");
        }
        return $dt->modify('+1 day')->format('Y-m-d');
    }
}