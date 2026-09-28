<?php
namespace App\Repository;

use App\Entity\LeaveRequest;
use PDO;
use RuntimeException;

class LeaveRequestRepository extends BaseRepository {
    private const COLUMNS = 'lr.id, lr.employee_id, lr.leave_type, lr.start_date, lr.end_date,
                             lr.reason, lr.status, lr.approved_by, lr.approved_at, lr.created_at';

    private const WITH_EMPLOYEE = self::COLUMNS . ',
                                   e.full_name,
                                   u.username AS approved_by_username';

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_EMPLOYEE . "
             FROM leave_requests lr
             LEFT JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN users     u ON u.id = lr.approved_by
             WHERE lr.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_EMPLOYEE . "
             FROM leave_requests lr
             LEFT JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN users     u ON u.id = lr.approved_by
             ORDER BY lr.start_date DESC, lr.id DESC"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEmployee(int $employeeId): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_EMPLOYEE . "
             FROM leave_requests lr
             LEFT JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN users     u ON u.id = lr.approved_by
             WHERE lr.employee_id = ?
             ORDER BY lr.start_date DESC, lr.id DESC"
        );
        $stmt->execute([$employeeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEmployeeAndDateRange(int $employeeId, string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_EMPLOYEE . "
             FROM leave_requests lr
             LEFT JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN users     u ON u.id = lr.approved_by
             WHERE lr.employee_id = :employee_id
               AND lr.start_date <= :end
               AND lr.end_date   >= :start
             ORDER BY lr.start_date DESC, lr.id DESC"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $end,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByStatus(string $status, int $limit = 200): array {
        $limit = $this->clampLimit($limit);

        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_EMPLOYEE . "
             FROM leave_requests lr
             LEFT JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN users     u ON u.id = lr.approved_by
             WHERE lr.status = :status
             ORDER BY lr.start_date DESC, lr.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByDateRange(string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::WITH_EMPLOYEE . "
             FROM leave_requests lr
             LEFT JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN users     u ON u.id = lr.approved_by
             WHERE lr.start_date <= :end
               AND lr.end_date   >= :start
             ORDER BY lr.start_date DESC, lr.id DESC"
        );
        $stmt->execute([
            ':start' => $start,
            ':end'   => $end,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPendingByEmployee(int $employeeId): array {
        $stmt = $this->db->prepare(
            "SELECT " . self::COLUMNS . "
             FROM leave_requests lr
             WHERE lr.employee_id = ? AND lr.status = 'pending'
             ORDER BY lr.start_date ASC"
        );
        $stmt->execute([$employeeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(LeaveRequest $leaveRequest): int {
        $stmt = $this->db->prepare(
            "INSERT INTO leave_requests
                (employee_id, leave_type, start_date, end_date, reason, status, approved_by, approved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $leaveRequest->getEmployeeId(),
            $leaveRequest->getLeaveType(),
            $leaveRequest->getStartDate(),
            $leaveRequest->getEndDate(),
            $leaveRequest->getReason(),
            $leaveRequest->getStatus(),
            $leaveRequest->getApprovedBy(),
            $leaveRequest->getApprovedAt(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, LeaveRequest $leaveRequest): bool {
        $stmt = $this->db->prepare(
            "UPDATE leave_requests SET
                leave_type  = :leave_type,
                start_date  = :start_date,
                end_date    = :end_date,
                reason      = :reason
             WHERE id = :id
               AND status = 'pending'"
        );
        $stmt->execute([
            ':leave_type' => $leaveRequest->getLeaveType(),
            ':start_date' => $leaveRequest->getStartDate(),
            ':end_date'   => $leaveRequest->getEndDate(),
            ':reason'     => $leaveRequest->getReason(),
            ':id'         => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function approve(int $id, int $approvedBy): bool {
        $stmt = $this->db->prepare(
            "UPDATE leave_requests
             SET status = 'approved', approved_by = ?, approved_at = NOW()
             WHERE id = ?
               AND status = 'pending'"
        );
        $stmt->execute([$approvedBy, $id]);

        return $stmt->rowCount() > 0;
    }

    public function reject(int $id, int $approvedBy, ?string $reason): bool {
        $stmt = $this->db->prepare(
            "UPDATE leave_requests
             SET status = 'rejected',
                 approved_by = :approved_by,
                 approved_at = NOW(),
                 reason = COALESCE(:reason, reason)
             WHERE id = :id
               AND status = 'pending'"
        );
        $stmt->execute([
            ':approved_by' => $approvedBy,
            ':reason'      => $reason,
            ':id'          => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function cancel(int $id): bool {
        $stmt = $this->db->prepare(
            "UPDATE leave_requests
             SET status = 'cancelled'
             WHERE id = ?
               AND status = 'pending'"
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM leave_requests WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    public function hasApprovedLeave(int $employeeId, string $date): bool {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM leave_requests
             WHERE employee_id = ?
               AND status = 'approved'
               AND ? BETWEEN start_date AND end_date
             LIMIT 1"
        );
        $stmt->execute([$employeeId, $date]);

        return $stmt->fetchColumn() !== false;
    }

    public function hasOverlappingRequest(
        int $employeeId,
        string $start,
        string $end,
        array $blockingStatuses = ['pending', 'approved'],
        ?int $exceptId = null
    ): bool {
        if (empty($blockingStatuses)) {
            return false;
        }

        $placeholders = implode(', ', array_fill(0, count($blockingStatuses), '?'));

        $sql = "SELECT 1 FROM leave_requests
                WHERE employee_id = ?
                  AND status IN ({$placeholders})
                  AND start_date <= ?
                  AND end_date   >= ?";
        $params = array_merge([$employeeId], array_values($blockingStatuses), [$end, $start]);

        if ($exceptId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $exceptId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public function countByEmployeeAndYear(int $employeeId, int $year): int {
        $start = sprintf('%04d-01-01', $year);
        $end   = sprintf('%04d-01-01', $year + 1);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM leave_requests
             WHERE employee_id = :employee_id
               AND start_date >= :start
               AND start_date <  :end"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $end,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function countByEmployeeYearAndType(int $employeeId, int $year): array {
        $start = sprintf('%04d-01-01', $year);
        $end   = sprintf('%04d-01-01', $year + 1);

        $stmt = $this->db->prepare(
            "SELECT leave_type, COUNT(*) AS request_count
             FROM leave_requests
             WHERE employee_id = :employee_id
               AND start_date >= :start
               AND start_date <  :end
               AND status IN ('approved', 'pending')
             GROUP BY leave_type"
        );
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start'       => $start,
            ':end'         => $end,
        ]);

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    private function clampLimit(int $limit): int {
        return max(1, min(500, $limit));
    }
}