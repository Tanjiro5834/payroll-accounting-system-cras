<?php

namespace App\Repository;

use App\Entity\TimePunch;
use PDO;
use Exception;

class TimePunchRepository extends BaseRepository
{
    public function createTimePunch(TimePunch $timePunch): int {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO time_punches 
                    (employee_id, work_date, punch_type, punch_time, ip_address,
                     gps_lat, gps_lng, gps_accuracy, device_fingerprint)
                    VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $timePunch->getEmployeeId(),
                $timePunch->getWorkDate(),
                $timePunch->getPunchType(),
                $timePunch->getIpAddress(),
                $timePunch->getGpsLat(),
                $timePunch->getGpsLng(),
                $timePunch->getGpsAccuracy(),
                $timePunch->getDeviceFingerprint(),
            ]);

            $newId = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $newId;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollback();
            error_log("TimePunch create failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function findById(int $id): ?TimePunch{
        $stmt = $this->db->prepare("SELECT * FROM time_punches WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? TimePunch::fromArray($row) : null;
    }

    public function findLatestByEmployee(int $employeeId): ?TimePunch
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM time_punches 
             WHERE employee_id = ? 
             ORDER BY punch_time DESC 
             LIMIT 1"
        );
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? TimePunch::fromArray($row) : null;
    }

    public function getFirstPunchOfDay(int $employeeId, string $date): ?TimePunch
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM time_punches 
             WHERE employee_id = ? AND work_date = ? 
             ORDER BY punch_time ASC 
             LIMIT 1"
        );
        $stmt->execute([$employeeId, $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? TimePunch::fromArray($row) : null;
    }

    public function getLastPunchOfDay(int $employeeId, string $date): ?TimePunch
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM time_punches 
             WHERE employee_id = ? AND work_date = ? 
             ORDER BY punch_time DESC 
             LIMIT 1"
        );
        $stmt->execute([$employeeId, $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? TimePunch::fromArray($row) : null;
    }

    public function findByEmployeeAndDate(int $employeeId, string $date): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM time_punches 
             WHERE employee_id = ? AND work_date = ? 
             ORDER BY punch_time"
        );
        $stmt->execute([$employeeId, $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => TimePunch::fromArray($row), $rows);
    }

    public function findByEmployeeAndDateRange(int $employeeId, string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM time_punches 
             WHERE employee_id = ? AND work_date BETWEEN ? AND ? 
             ORDER BY work_date, punch_time"
        );
        $stmt->execute([$employeeId, $start, $end]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => TimePunch::fromArray($row), $rows);
    }

    public function findByDateRange(string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM time_punches 
             WHERE work_date BETWEEN ? AND ? 
             ORDER BY work_date, punch_time"
        );

        $stmt->execute([$start, $end]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => TimePunch::fromArray($row), $rows);
    }

    public function findByDate(string $date): array{
        $stmt = $this->db->prepare(
            "SELECT * FROM time_punches 
             WHERE work_date = ? 
             ORDER BY punch_time"
        );

        $stmt->execute([$date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => TimePunch::fromArray($row), $rows);
    }

    public function hasPunchedToday(int $employeeId, string $punchType, string $date): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM time_punches 
             WHERE employee_id = ? AND work_date = ? AND punch_type = ?"
        );
        $stmt->execute([$employeeId, $date, $punchType]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function countPunchesByEmployeeAndDate(int $employeeId, string $date): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM time_punches 
             WHERE employee_id = ? AND work_date = ?"
        );
        $stmt->execute([$employeeId, $date]);
        return (int) $stmt->fetchColumn();
    }

    public function findFlagged(?string $date = null): array{
        $sql = "SELECT tp.*, e.full_name 
                FROM time_punches tp
                JOIN employees e ON e.id = tp.employee_id
                WHERE tp.is_flagged = 1";
        $params = [];

        if ($date) {
            $sql .= " AND tp.work_date = ?";
            $params[] = $date;
        }

        $sql .= " ORDER BY tp.work_date DESC, tp.punch_time DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findFlaggedByEmployee(int $employeeId, string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM time_punches 
             WHERE employee_id = ? 
               AND work_date BETWEEN ? AND ? 
               AND is_flagged = 1 
             ORDER BY work_date DESC, punch_time DESC"
        );
        $stmt->execute([$employeeId, $start, $end]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => TimePunch::fromArray($row), $rows);
    }

    public function flagById(int $id, string $reason): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE time_punches 
             SET is_flagged = 1, flag_reason = ? 
             WHERE id = ?"
        );
        return $stmt->execute([$reason, $id]);
    }

    public function unflagById(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE time_punches 
             SET is_flagged = 0, flag_reason = NULL 
             WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    public function flagOutFromDifferentIp(string $date): int
    {
        $sql = "UPDATE time_punches p_out
                JOIN time_punches p_in 
                  ON p_in.employee_id = p_out.employee_id 
                 AND p_in.work_date = p_out.work_date
                 AND p_in.punch_type = 'AM_IN'
                SET p_out.is_flagged = 1,
                    p_out.flag_reason = CONCAT('OUT from different IP than IN (IN: ', p_in.ip_address, ')')
                WHERE p_out.work_date = ?
                  AND p_out.punch_type IN ('PM_OUT', 'OT_OUT')
                  AND p_out.ip_address <> p_in.ip_address
                  AND p_out.is_flagged = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        return $stmt->rowCount();
    }

    public function flagMissingPunch(string $date): int
    {
        $sql = "UPDATE time_punches p1
                SET p1.is_flagged = 1,
                    p1.flag_reason = 'Missing paired punch'
                WHERE p1.work_date = ?
                  AND p1.is_flagged = 0
                  AND (
                    (p1.punch_type = 'AM_IN' AND NOT EXISTS (
                        SELECT 1 FROM time_punches p2
                        WHERE p2.employee_id = p1.employee_id
                          AND p2.work_date = p1.work_date
                          AND p2.punch_type = 'AM_OUT'
                    ))
                    OR
                    (p1.punch_type = 'PM_IN' AND NOT EXISTS (
                        SELECT 1 FROM time_punches p2
                        WHERE p2.employee_id = p1.employee_id
                          AND p2.work_date = p1.work_date
                          AND p2.punch_type = 'PM_OUT'
                    ))
                  )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        return $stmt->rowCount();
    }

    public function flagShortLunch(string $date, int $minMinutes = 50): int
    {
        $sql = "UPDATE time_punches p_in
                JOIN time_punches p_out 
                  ON p_out.employee_id = p_in.employee_id 
                 AND p_out.work_date = p_in.work_date
                 AND p_out.punch_type = 'AM_OUT'
                SET p_in.is_flagged = 1,
                    p_in.flag_reason = CONCAT('Short lunch: ', TIMESTAMPDIFF(MINUTE, p_out.punch_time, p_in.punch_time), ' minutes')
                WHERE p_in.work_date = ?
                  AND p_in.punch_type = 'PM_IN'
                  AND p_in.is_flagged = 0
                  AND TIMESTAMPDIFF(MINUTE, p_out.punch_time, p_in.punch_time) < ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date, $minMinutes]);
        return $stmt->rowCount();
    }

    public function flagLongLunch(string $date, int $maxMinutes = 70): int
    {
        $sql = "UPDATE time_punches p_in
                JOIN time_punches p_out 
                  ON p_out.employee_id = p_in.employee_id 
                 AND p_out.work_date = p_in.work_date
                 AND p_out.punch_type = 'AM_OUT'
                SET p_in.is_flagged = 1,
                    p_in.flag_reason = CONCAT('Long lunch: ', TIMESTAMPDIFF(MINUTE, p_out.punch_time, p_in.punch_time), ' minutes')
                WHERE p_in.work_date = ?
                  AND p_in.punch_type = 'PM_IN'
                  AND p_in.is_flagged = 0
                  AND TIMESTAMPDIFF(MINUTE, p_out.punch_time, p_in.punch_time) > ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date, $maxMinutes]);
        return $stmt->rowCount();
    }

    public function flagEarlyOut(string $date): int {
        $sql = "UPDATE time_punches
                SET is_flagged = 1,
                    flag_reason = CONCAT('Early out at ', DATE_FORMAT(punch_time, '%h:%i %p'))
                WHERE work_date = ?
                  AND punch_type = 'PM_OUT'
                  AND is_flagged = 0
                  AND TIME(punch_time) < '17:00:00'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        return $stmt->rowCount();
    }

    public function flagHabitualLate(int $employeeId, string $month): int {
        $sql = "UPDATE time_punches
                SET is_flagged = 1,
                    flag_reason = 'Habitual late'
                WHERE employee_id = ?
                  AND DATE_FORMAT(work_date, '%Y-%m') = ?
                  AND punch_type = 'AM_IN'
                  AND TIME(punch_time) > '08:15:00'
                  AND is_flagged = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$employeeId, $month]);
        return $stmt->rowCount();
    }

    public function flagSameDeviceMultipleEmployees(string $date): int {
        $sql = "UPDATE time_punches p1
                JOIN (
                    SELECT device_fingerprint, work_date
                    FROM time_punches
                    WHERE work_date = ?
                      AND device_fingerprint IS NOT NULL
                    GROUP BY device_fingerprint, work_date
                    HAVING COUNT(DISTINCT employee_id) > 1
                ) dup 
                  ON dup.device_fingerprint = p1.device_fingerprint
                 AND dup.work_date = p1.work_date
                SET p1.is_flagged = 1,
                    p1.flag_reason = 'Same device used by multiple employees'
                WHERE p1.work_date = ?
                  AND p1.is_flagged = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date, $date]);
        return $stmt->rowCount();
    }

    public function deleteById(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM time_punches WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function deleteByEmployeeAndDate(int $employeeId, string $date): bool {
        $stmt = $this->db->prepare(
            "DELETE FROM time_punches 
             WHERE employee_id = ? AND work_date = ?"
        );
        return $stmt->execute([$employeeId, $date]);
    }
}