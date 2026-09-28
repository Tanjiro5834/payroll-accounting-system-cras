<?php
namespace App\Repository;

use App\Entity\TimePunch;
use PDO;

class TimePunchRepository extends BaseRepository {
    private const COLUMNS = 'id, employee_id, work_date, punch_type, punch_time,
                             ip_address, gps_lat, gps_lng, gps_accuracy, device_fingerprint,
                             is_flagged, flag_reason, reviewed_by, reviewed_at, created_at';

    private const WITH_EMPLOYEE = 'tp.id, tp.employee_id, e.full_name, tp.work_date, tp.punch_type,
                                   tp.punch_time, tp.ip_address, tp.gps_lat, tp.gps_lng,
                                   tp.gps_accuracy, tp.device_fingerprint, tp.is_flagged,
                                   tp.flag_reason, tp.reviewed_by, tp.reviewed_at, tp.created_at';

    private const PAIRS = [
        'AM_IN'  => 'AM_OUT',
        'PM_IN'  => 'PM_OUT',
        'OT_IN'  => 'OT_OUT',
    ];

    public function create(TimePunch $timePunch): int {
        $stmt = $this->db->prepare(
            "INSERT INTO time_punches
                (employee_id, work_date, punch_type, punch_time, ip_address,
                 gps_lat, gps_lng, gps_accuracy, device_fingerprint)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $timePunch->getEmployeeId(),
            $timePunch->getWorkDate(),
            $timePunch->getPunchType(),
            $timePunch->getPunchTime() ?: date('Y-m-d H:i:s'),
            $timePunch->getIpAddress(),
            $timePunch->getGpsLat(),
            $timePunch->getGpsLng(),
            $timePunch->getGpsAccuracy(),
            $timePunch->getDeviceFingerprint(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?TimePunch {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . " FROM time_punches WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? TimePunch::fromArray($row) : null;
    }

    public function findLatestByEmployee(int $employeeId): ?TimePunch {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM time_punches
             WHERE employee_id = ?
             ORDER BY punch_time DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? TimePunch::fromArray($row) : null;
    }

    public function getFirstPunchOfDay(int $employeeId, string $date): ?TimePunch {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM time_punches
             WHERE employee_id = ? AND work_date = ?
             ORDER BY punch_time ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$employeeId, $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? TimePunch::fromArray($row) : null;
    }

    public function getLastPunchOfDay(int $employeeId, string $date): ?TimePunch {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM time_punches
             WHERE employee_id = ? AND work_date = ?
             ORDER BY punch_time DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$employeeId, $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? TimePunch::fromArray($row) : null;
    }

    public function findByEmployeeAndDate(int $employeeId, string $date): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM time_punches
             WHERE employee_id = ? AND work_date = ?
             ORDER BY punch_time ASC, id ASC"
        );
        $stmt->execute([$employeeId, $date]);

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByEmployeeAndDateRange(int $employeeId, string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM time_punches
             WHERE employee_id = :employee_id
               AND work_date >= :start
               AND work_date <  :end
             ORDER BY work_date ASC, punch_time ASC, id ASC"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $this->nextDay($end),
        ]);

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByDateRange(string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM time_punches
             WHERE work_date >= :start
               AND work_date <  :end
             ORDER BY work_date ASC, punch_time ASC, id ASC"
        );
        $stmt->execute([
            ':start' => $start,
            ':end'   => $this->nextDay($end),
        ]);

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByDate(string $date): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM time_punches
             WHERE work_date = ?
             ORDER BY punch_time ASC, id ASC"
        );
        $stmt->execute([$date]);

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function hasPunchedToday(int $employeeId, string $punchType, string $date): bool {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM time_punches
             WHERE employee_id = ? AND work_date = ? AND punch_type = ?
             LIMIT 1"
        );
        $stmt->execute([$employeeId, $date, $punchType]);

        return $stmt->fetchColumn() !== false;
    }

    public function countPunchesByEmployeeAndDate(int $employeeId, string $date): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM time_punches
             WHERE employee_id = ? AND work_date = ?"
        );
        $stmt->execute([$employeeId, $date]);

        return (int) $stmt->fetchColumn();
    }

    public function findFlagged(?string $date = null, int $limit = 500): array {
        $limit = $this->clampLimit($limit);

        $sql = "SELECT " . self::WITH_EMPLOYEE . "
                FROM time_punches tp
                LEFT JOIN employees e ON e.id = tp.employee_id
                WHERE tp.is_flagged = 1";
        $params = [];

        if ($date !== null) {
            $sql .= " AND tp.work_date = :d";
            $params[':d'] = $date;
        }

        $sql .= " ORDER BY tp.work_date DESC, tp.punch_time DESC, tp.id DESC LIMIT :lim";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findFlaggedByEmployee(int $employeeId, string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM time_punches
             WHERE employee_id = :employee_id
               AND work_date >= :start
               AND work_date <  :end
               AND is_flagged = 1
             ORDER BY work_date DESC, punch_time DESC, id DESC"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $this->nextDay($end),
        ]);

        return $this->hydrateAll($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function flagById(int $id, string $reason): bool {
        $stmt = $this->db->prepare(
            "UPDATE time_punches
             SET is_flagged = 1, flag_reason = :reason
             WHERE id = :id AND is_flagged = 0"
        );
        $stmt->execute([
            ':reason' => $reason,
            ':id'     => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function unflagById(int $id, int $reviewedBy): bool {
        $stmt = $this->db->prepare(
            "UPDATE time_punches
             SET is_flagged = 0,
                 flag_reason = NULL,
                 reviewed_by = :reviewed_by,
                 reviewed_at = NOW()
             WHERE id = :id AND is_flagged = 1"
        );
        $stmt->execute([
            ':reviewed_by' => $reviewedBy,
            ':id'          => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function flagOutFromDifferentIp(string $date): int {
        $stmt = $this->db->prepare(
            "UPDATE time_punches p_out
             JOIN time_punches p_in
               ON p_in.employee_id = p_out.employee_id
              AND p_in.work_date   = p_out.work_date
              AND p_in.punch_type  = 'AM_IN'
             SET p_out.is_flagged = 1,
                 p_out.flag_reason = CONCAT('OUT from different IP than IN (IN: ', p_in.ip_address, ')')
             WHERE p_out.work_date = :d
               AND p_out.punch_type IN ('PM_OUT', 'OT_OUT')
               AND p_out.ip_address <> p_in.ip_address
               AND p_out.is_flagged = 0"
        );
        $stmt->execute([':d' => $date]);

        return $stmt->rowCount();
    }

    public function flagMissingPunch(string $date): int {
        $stmt = $this->db->prepare(
            "UPDATE time_punches p1
             SET p1.is_flagged = 1,
                 p1.flag_reason = 'Missing paired punch'
             WHERE p1.work_date = :d
               AND p1.is_flagged = 0
               AND (
                   (p1.punch_type IN ('AM_IN', 'PM_IN', 'OT_IN') AND NOT EXISTS (
                       SELECT 1 FROM time_punches p2
                       WHERE p2.employee_id = p1.employee_id
                         AND p2.work_date   = p1.work_date
                         AND p2.punch_type = CASE p1.punch_type
                             WHEN 'AM_IN' THEN 'AM_OUT'
                             WHEN 'PM_IN' THEN 'PM_OUT'
                             WHEN 'OT_IN' THEN 'OT_OUT'
                         END
                   ))
                   OR
                   (p1.punch_type IN ('AM_OUT', 'PM_OUT', 'OT_OUT') AND NOT EXISTS (
                       SELECT 1 FROM time_punches p2
                       WHERE p2.employee_id = p1.employee_id
                         AND p2.work_date   = p1.work_date
                         AND p2.punch_type = CASE p1.punch_type
                             WHEN 'AM_OUT' THEN 'AM_IN'
                             WHEN 'PM_OUT' THEN 'PM_IN'
                             WHEN 'OT_OUT' THEN 'OT_IN'
                         END
                   ))
               )"
        );
        $stmt->execute([':d' => $date]);

        return $stmt->rowCount();
    }

    public function flagShortLunch(string $date, int $minMinutes = 50): int {
        $stmt = $this->db->prepare(
            "UPDATE time_punches p_in
             JOIN time_punches p_out
               ON p_out.employee_id = p_in.employee_id
              AND p_out.work_date   = p_in.work_date
              AND p_out.punch_type  = 'AM_OUT'
             SET p_in.is_flagged = 1,
                 p_in.flag_reason = CONCAT('Short lunch: ',
                     TIMESTAMPDIFF(MINUTE, p_out.punch_time, p_in.punch_time), ' minutes')
             WHERE p_in.work_date = :d
               AND p_in.punch_type = 'PM_IN'
               AND p_in.is_flagged = 0
               AND TIMESTAMPDIFF(MINUTE, p_out.punch_time, p_in.punch_time) < :min"
        );
        $stmt->execute([
            ':d'   => $date,
            ':min' => $minMinutes,
        ]);

        return $stmt->rowCount();
    }

    public function flagLongLunch(string $date, int $maxMinutes = 70): int {
        $stmt = $this->db->prepare(
            "UPDATE time_punches p_in
             JOIN time_punches p_out
               ON p_out.employee_id = p_in.employee_id
              AND p_out.work_date   = p_in.work_date
              AND p_out.punch_type  = 'AM_OUT'
             SET p_in.is_flagged = 1,
                 p_in.flag_reason = CONCAT('Long lunch: ',
                     TIMESTAMPDIFF(MINUTE, p_out.punch_time, p_in.punch_time), ' minutes')
             WHERE p_in.work_date = :d
               AND p_in.punch_type = 'PM_IN'
               AND p_in.is_flagged = 0
               AND TIMESTAMPDIFF(MINUTE, p_out.punch_time, p_in.punch_time) > :max"
        );
        $stmt->execute([
            ':d'   => $date,
            ':max' => $maxMinutes,
        ]);

        return $stmt->rowCount();
    }

    public function flagEarlyOut(string $date): int {
        $stmt = $this->db->prepare(
            "UPDATE time_punches
             SET is_flagged = 1,
                 flag_reason = CONCAT('Early out at ', DATE_FORMAT(punch_time, '%h:%i %p'))
             WHERE work_date = :d
               AND punch_type = 'PM_OUT'
               AND is_flagged = 0
               AND TIME(punch_time) < '17:00:00'"
        );
        $stmt->execute([':d' => $date]);

        return $stmt->rowCount();
    }

    public function flagHabitualLate(int $employeeId, int $year, int $month): int {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = (new \DateTimeImmutable($start, new \DateTimeZone('Asia/Manila')))
                    ->modify('first day of next month')
                    ->format('Y-m-d');

        $stmt = $this->db->prepare(
            "UPDATE time_punches
             SET is_flagged = 1,
                 flag_reason = 'Habitual late'
             WHERE employee_id = :employee_id
               AND work_date >= :start
               AND work_date <  :end
               AND punch_type = 'AM_IN'
               AND TIME(punch_time) > '08:15:00'
               AND is_flagged = 0"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $end,
        ]);

        return $stmt->rowCount();
    }

    public function flagSameDeviceMultipleEmployees(string $date): int {
        $stmt = $this->db->prepare(
            "UPDATE time_punches p1
             JOIN (
                 SELECT device_fingerprint, work_date
                 FROM time_punches
                 WHERE work_date = :d_inner
                   AND device_fingerprint IS NOT NULL
                 GROUP BY device_fingerprint, work_date
                 HAVING COUNT(DISTINCT employee_id) > 1
             ) dup
               ON dup.device_fingerprint = p1.device_fingerprint
              AND dup.work_date          = p1.work_date
             SET p1.is_flagged = 1,
                 p1.flag_reason = 'Same device used by multiple employees'
             WHERE p1.work_date = :d_outer
               AND p1.is_flagged = 0"
        );
        $stmt->execute([
            ':d_inner' => $date,
            ':d_outer' => $date,
        ]);

        return $stmt->rowCount();
    }

    public function reviewById(int $id, int $reviewedBy): bool {
        $stmt = $this->db->prepare(
            "UPDATE time_punches
             SET reviewed_by = :reviewed_by,
                 reviewed_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            ':reviewed_by' => $reviewedBy,
            ':id'          => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deleteById(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM time_punches WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function deleteByEmployeeAndDate(int $employeeId, string $date): int {
        $stmt = $this->db->prepare(
            "DELETE FROM time_punches WHERE employee_id = ? AND work_date = ?"
        );
        $stmt->execute([$employeeId, $date]);

        return $stmt->rowCount();
    }

    public function deleteByEmployeeAndDateRange(int $employeeId, string $start, string $end): int {
        $stmt = $this->db->prepare(
            "DELETE FROM time_punches
             WHERE employee_id = :employee_id
               AND work_date >= :start
               AND work_date <  :end"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $this->nextDay($end),
        ]);

        return $stmt->rowCount();
    }

    public function getLatestIpAddress(int $employeeId): ?string {
        $stmt = $this->db->prepare(
            "SELECT ip_address FROM time_punches
             WHERE employee_id = ?
             ORDER BY punch_time DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$employeeId]);
        $ip = $stmt->fetchColumn();

        return $ip === false ? null : (string) $ip;
    }

    public function countFlagged(?string $date = null): int {
        if ($date === null) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM time_punches WHERE is_flagged = 1");
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM time_punches WHERE is_flagged = 1 AND work_date = ?"
            );
            $stmt->execute([$date]);
        }

        return (int) $stmt->fetchColumn();
    }

    private function hydrateAll(array $rows): array {
        return array_map(fn(array $row) => TimePunch::fromArray($row), $rows);
    }

    private function clampLimit(int $limit): int {
        return max(1, min(1000, $limit));
    }

    private function nextDay(string $date): string {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('Asia/Manila'));
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException("Invalid date: {$date}");
        }
        return $dt->modify('+1 day')->format('Y-m-d');
    }
}