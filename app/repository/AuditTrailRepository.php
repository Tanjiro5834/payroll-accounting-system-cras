<?php
namespace App\Repository;

use PDO;
use Throwable;

class AuditTrailRepository extends BaseRepository {
    private const DEFAULT_LIMIT = 500;
    private const MAX_LIMIT = 1000;

    private const SELECT = "SELECT at.id, at.employee_id, e.full_name, at.user_id, u.username AS actor_username,
                                   at.action_type, at.action_details, at.ip_address, at.performed_at
                            FROM audit_trail at
                            LEFT JOIN employees e ON e.id = at.employee_id
                            LEFT JOIN users u     ON u.id = at.user_id";

    public function log(?int $employeeId, string $actionType, mixed $details = null, ?string $ip = null, ?int $userId = null): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO audit_trail (employee_id, user_id, action_type, action_details, ip_address, performed_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );

        return $stmt->execute([
            $employeeId,
            $userId,
            $actionType,
            is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $details,
            $ip,
        ]);
    }

    public function findByDateRange(string $start, string $end, ?int $employeeId = null, int $limit = self::DEFAULT_LIMIT): array {
        $limit = $this->clampLimit($limit);
        $endExclusive = $this->nextDay($end);

        $sql = self::SELECT . "
                 WHERE at.performed_at >= :start
                  AND at.performed_at <  :end";
        $params = [':start' => $start, ':end' => $endExclusive];

        if ($employeeId !== null) {
            $sql .= " AND at.employee_id = :employee_id";
            $params[':employee_id'] = $employeeId;
        }

        $sql .= " ORDER BY at.performed_at DESC, at.id DESC LIMIT :lim";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByActionType(string $actionType, string $start, string $end, int $limit = self::DEFAULT_LIMIT): array {
        $limit = $this->clampLimit($limit);
        $endExclusive = $this->nextDay($end);

        $stmt = $this->db->prepare(
            self::SELECT . "
                 WHERE at.action_type = :action_type
               AND at.performed_at >= :start
               AND at.performed_at <  :end
             ORDER BY at.performed_at DESC, at.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':action_type', $actionType, PDO::PARAM_STR);
        $stmt->bindValue(':start', $start, PDO::PARAM_STR);
        $stmt->bindValue(':end', $endExclusive, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEmployee(int $employeeId, int $limit = 100): array {
        $limit = $this->clampLimit($limit);

        $stmt = $this->db->prepare(
            self::SELECT . "
                 WHERE at.employee_id = :employee_id
             ORDER BY at.performed_at DESC, at.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByActionType(string $actionType, string $start, string $end): int {
        $endExclusive = $this->nextDay($end);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM audit_trail
             WHERE action_type = :action_type
               AND performed_at >= :start
               AND performed_at <  :end"
        );
        $stmt->execute([
            ':action_type' => $actionType,
            ':start'       => $start,
            ':end'         => $endExclusive,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function purgeOlderThan(string $date): int {
        $stmt = $this->db->prepare("DELETE FROM audit_trail WHERE performed_at < ?");
        $stmt->execute([$date]);

        return $stmt->rowCount();
    }

    private function clampLimit(int $limit): int {
        return max(1, min(self::MAX_LIMIT, $limit));
    }

    private function nextDay(string $date): string {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('Asia/Manila'));
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException("Invalid date: {$date}");
        }
        return $dt->modify('+1 day')->format('Y-m-d');
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            self::SELECT . "
                 WHERE at.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}