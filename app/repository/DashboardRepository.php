<?php
namespace App\Repository;

use PDO;

class DashboardRepository extends BaseRepository {
    private const EMPLOYEE_COLUMNS = 'e.id, e.full_name, e.role, e.profile_photo_url';

    public function getKpiSummary(string $date, string $lateAfter): array {
        $stmt = $this->db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM employees WHERE is_active = 1) AS total_active,

                (SELECT COUNT(DISTINCT tp.employee_id)
                 FROM time_punches tp
                 JOIN employees e ON e.id = tp.employee_id AND e.is_active = 1
                 WHERE tp.work_date = :d_present AND tp.punch_type = 'AM_IN') AS present,

                (SELECT COUNT(*) FROM (
                     SELECT MIN(TIME(tp.punch_time)) AS first_in
                     FROM time_punches tp
                     JOIN employees e ON e.id = tp.employee_id AND e.is_active = 1
                     WHERE tp.work_date = :d_late AND tp.punch_type = 'AM_IN'
                     GROUP BY tp.employee_id
                 ) f
                 WHERE f.first_in > :late_after) AS late,

                (SELECT COUNT(*) FROM time_punches
                 WHERE work_date = :d_flagged AND is_flagged = 1 AND reviewed_at IS NULL) AS flagged,

                (SELECT COUNT(DISTINCT lr.employee_id)
                 FROM leave_requests lr
                 JOIN employees e ON e.id = lr.employee_id AND e.is_active = 1
                 WHERE lr.status = 'approved'
                   AND :d_leave BETWEEN lr.start_date AND lr.end_date) AS on_leave,

                (SELECT COUNT(*) FROM employees e
                 WHERE e.is_active = 1
                   AND NOT EXISTS (
                       SELECT 1 FROM time_punches tp
                       WHERE tp.employee_id = e.id
                         AND tp.work_date = :d_absent_punch
                         AND tp.punch_type = 'AM_IN')
                   AND NOT EXISTS (
                       SELECT 1 FROM leave_requests lr
                       WHERE lr.employee_id = e.id
                         AND lr.status = 'approved'
                         AND :d_absent_leave BETWEEN lr.start_date AND lr.end_date)
                ) AS absent"
        );

        $stmt->execute([
            ':d_present'      => $date,
            ':d_late'         => $date,
            ':late_after'     => $lateAfter,
            ':d_flagged'      => $date,
            ':d_leave'        => $date,
            ':d_absent_punch' => $date,
            ':d_absent_leave' => $date,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'date'         => $date,
            'total_active' => (int) $row['total_active'],
            'present'      => (int) $row['present'],
            'absent'       => (int) $row['absent'],
            'late'         => (int) $row['late'],
            'flagged'      => (int) $row['flagged'],
            'on_leave'     => (int) $row['on_leave'],
        ];
    }

    public function getTodayActivity(string $date, int $limit = 20): array {
        $stmt = $this->db->prepare(
            "SELECT HOUR(punch_time) AS h, COUNT(*) AS c
             FROM time_punches
             WHERE work_date = :d
             GROUP BY HOUR(punch_time)
             ORDER BY h"
        );
        $stmt->execute([':d' => $date]);

        $buckets = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $buckets[sprintf('%02d', $row['h'])] = (int) $row['c'];
        }

        $latest = $this->db->prepare(
            "SELECT tp.id, tp.employee_id, e.full_name, tp.work_date, tp.punch_type,
                    tp.punch_time, tp.ip_address, tp.gps_lat, tp.gps_lng,
                    tp.is_flagged, tp.flag_reason
             FROM time_punches tp
             LEFT JOIN employees e ON e.id = tp.employee_id
             WHERE tp.work_date = :d
             ORDER BY tp.punch_time DESC, tp.id DESC
             LIMIT :lim"
        );
        $latest->bindValue(':d', $date);
        $latest->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $latest->execute();

        return [
            'date'    => $date,
            'buckets' => $buckets,
            'latest'  => $latest->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function getFlaggedPunches(string $date): array {
        $stmt = $this->db->prepare(
            "SELECT tp.id, tp.employee_id, e.full_name, tp.work_date, tp.punch_type, tp.punch_time,
                    tp.ip_address, tp.gps_lat, tp.gps_lng, tp.gps_accuracy, tp.device_fingerprint,
                    tp.flag_reason, tp.reviewed_by, tp.reviewed_at
             FROM time_punches tp
             LEFT JOIN employees e ON e.id = tp.employee_id
             WHERE tp.work_date = :d AND tp.is_flagged = 1 AND tp.reviewed_at IS NULL
             ORDER BY tp.punch_time ASC"
        );
        $stmt->execute([':d' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmployeesPunchedIn(string $date): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::EMPLOYEE_COLUMNS . "
             FROM employees e
             WHERE EXISTS (
                 SELECT 1 FROM time_punches tp
                 WHERE tp.employee_id = e.id
                   AND tp.work_date = :d
                   AND tp.punch_type = 'AM_IN'
             )
             ORDER BY e.full_name"
        );
        $stmt->execute([':d' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEmployeesNotPunchedIn(string $date): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::EMPLOYEE_COLUMNS . "
             FROM employees e
             WHERE e.is_active = 1
               AND NOT EXISTS (
                   SELECT 1 FROM time_punches tp
                   WHERE tp.employee_id = e.id
                     AND tp.work_date = :d
                     AND tp.punch_type = 'AM_IN'
               )
             ORDER BY e.full_name"
        );
        $stmt->execute([':d' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPayrollPending(): array {
        $stmt = $this->db->query(
            "SELECT pp.id, pp.employee_id, e.full_name, pp.period_start, pp.period_end,
                    pp.pay_frequency, pp.gross_pay, pp.total_deductions, pp.net_pay,
                    pp.status, pp.computed_at
             FROM payroll_periods pp
             LEFT JOIN employees e ON e.id = pp.employee_id
             WHERE pp.status IN ('draft', 'computed')
             ORDER BY pp.period_start DESC, pp.id DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentActivity(int $limit = 20): array {
        $stmt = $this->db->prepare(
            "SELECT at.id, at.employee_id, e.full_name, at.action_type, at.action_details,
                    at.ip_address, at.performed_at
             FROM audit_trail at
             LEFT JOIN employees e ON e.id = at.employee_id
             ORDER BY at.performed_at DESC, at.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}