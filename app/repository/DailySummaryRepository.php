<?php
namespace App\Repository;

use PDO;
use Exception;

class DailySummaryRepository extends BaseRepository
{
    public function upsert(array $data): void
    {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO daily_summary
                    (employee_id, work_date, regular_hours, overtime_hours,
                     night_diff_hours, late_minutes, undertime_minutes, is_rest_day)
                    VALUES (:employee_id, :work_date, :regular_hours, :overtime_hours,
                            :night_diff_hours, :late_minutes, :undertime_minutes, :is_rest_day)
                    ON DUPLICATE KEY UPDATE
                        regular_hours     = VALUES(regular_hours),
                        overtime_hours    = VALUES(overtime_hours),
                        night_diff_hours  = VALUES(night_diff_hours),
                        late_minutes      = VALUES(late_minutes),
                        undertime_minutes = VALUES(undertime_minutes),
                        is_rest_day       = VALUES(is_rest_day)";

            $this->db->prepare($sql)->execute([
                'employee_id'      => $data['employee_id'],
                'work_date'        => $data['work_date'],
                'regular_hours'    => $data['regular_hours']    ?? 0,
                'overtime_hours'   => $data['overtime_hours']   ?? 0,
                'night_diff_hours' => $data['night_diff_hours'] ?? 0,
                'late_minutes'     => $data['late_minutes']     ?? 0,
                'undertime_minutes'=> $data['undertime_minutes']?? 0,
                'is_rest_day'      => $data['is_rest_day']      ?? 0,
            ]);

            $this->db->commit();
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function sumByYear(int $employeeId, int $year): array
    {
        $start = sprintf('%04d-01-01', $year);
        $end   = sprintf('%04d-01-01', $year + 1);

        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(regular_hours), 0)     AS total_regular,
                    COALESCE(SUM(overtime_hours), 0)    AS total_overtime,
                    COALESCE(SUM(night_diff_hours), 0)  AS total_night_diff,
                    COUNT(DISTINCT work_date)           AS days_worked
             FROM daily_summary
             WHERE employee_id = :employee_id
               AND work_date >= :start
               AND work_date <  :end"
        );
        $stmt->execute([$employeeId, $start, $end]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function sumByPeriod(int $employeeId, string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(regular_hours), 0)     AS total_regular,
                    COALESCE(SUM(overtime_hours), 0)    AS total_overtime,
                    COALESCE(SUM(night_diff_hours), 0)  AS total_night_diff,
                    COUNT(DISTINCT work_date)           AS days_worked
             FROM daily_summary
             WHERE employee_id = ?
               AND work_date BETWEEN ? AND ?"
        );
        $stmt->execute([
            'employee_id' => $employeeId,
            'start'       => $start,
            'end'         => $end,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByPeriod(int $employeeId, string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM daily_summary
             WHERE employee_id = ?
               AND work_date BETWEEN ? AND ?
             ORDER BY work_date"
        );
        $stmt->execute([$employeeId, $start, $end]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByDate(string $date): array
    {
        $stmt = $this->db->prepare("SELECT * FROM daily_summary WHERE work_date = ?");
        $stmt->execute(['date' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countDaysWorked(int $employeeId, string $start, string $end): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS days_worked
             FROM daily_summary
             WHERE employee_id = ?
               AND work_date BETWEEN ? AND ?
               AND regular_hours > 0
               AND is_rest_day = 0"
        );
        $stmt->execute([
            'employee_id' => $employeeId,
            'start'       => $start,
            'end'         => $end,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['days_worked'] ?? 0);
    }

    public function countMonthsWorked(int $employeeId, int $year): array
    {
        $start = sprintf('%04d-01-01', $year);
        $end   = sprintf('%04d-01-01', $year + 1);

        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(work_date, '%Y-%m') AS month_year,
                    COUNT(*)           AS days_worked,
                    SUM(regular_hours) AS total_regular_hours,
                    SUM(overtime_hours) AS total_overtime_hours
             FROM daily_summary
             WHERE employee_id = :employee_id
               AND work_date >= :start
               AND work_date <  :end
               AND regular_hours > 0
               AND is_rest_day = 0
             GROUP BY month_year
             ORDER BY month_year DESC"
        );

        $stmt->execute([$employeeId, $start, $end]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteByEmployeeAndDate(int $employeeId, string $date): bool
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                "DELETE FROM daily_summary
                 WHERE employee_id = ? AND work_date = ?"
            );
            $stmt->execute([
                'employee_id' => $employeeId,
                'work_date'   => $date,
            ]);
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteByYear(int $year): int
    {
        try {
            $this->db->beginTransaction();
            $start = sprintf('%04d-01-01', $year);
            $end = sprintf('%04d-01-01', $year + 1);

            $stmt = $this->db->prepare(
                "DELETE FROM daily_summary
                 WHERE work_date >= ? AND work_date < ?"
            );

            $stmt->execute([$start, $end]);
            $deleted = $stmt->rowCount();

            $this->db->commit();
            return $deleted;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}