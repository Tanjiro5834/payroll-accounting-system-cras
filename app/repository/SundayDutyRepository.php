<?php
namespace App\Repository;

use PDO;

class SundayDutyRepository extends BaseRepository {

    // Duties in [start, end] with lead name and members (lead included), oldest first.
    public function findByDateRange(string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT d.id, d.duty_date, d.lead_employee_id, d.notes, d.created_at,
                    l.full_name AS lead_name
             FROM sunday_duties d
             JOIN employees l ON l.id = d.lead_employee_id
             WHERE d.duty_date BETWEEN :start AND :end
             ORDER BY d.duty_date"
        );
        $stmt->execute([':start' => $start, ':end' => $end]);
        return $this->withMembers($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT d.id, d.duty_date, d.lead_employee_id, d.notes, d.created_at,
                    l.full_name AS lead_name
             FROM sunday_duties d
             JOIN employees l ON l.id = d.lead_employee_id
             WHERE d.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->withMembers([$row])[0] : null;
    }

    public function findIdByDate(string $date): ?int {
        $stmt = $this->db->prepare("SELECT id FROM sunday_duties WHERE duty_date = ?");
        $stmt->execute([$date]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function isAssigned(int $employeeId, string $date): bool {
        $stmt = $this->db->prepare(
            "SELECT 1
             FROM sunday_duty_members m
             JOIN sunday_duties d ON d.id = m.duty_id
             WHERE m.employee_id = ? AND d.duty_date = ?
             LIMIT 1"
        );
        $stmt->execute([$employeeId, $date]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return array<string, true> duty dates in range this employee is rostered on */
    public function assignedDates(int $employeeId, string $start, string $end): array {
        $stmt = $this->db->prepare(
            "SELECT d.duty_date
             FROM sunday_duty_members m
             JOIN sunday_duties d ON d.id = m.duty_id
             WHERE m.employee_id = ? AND d.duty_date BETWEEN ? AND ?"
        );
        $stmt->execute([$employeeId, $start, $end]);
        return array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
    }

    // Upcoming duties for one employee, with lead + teammates.
    public function findUpcomingForEmployee(int $employeeId, string $from, int $limit = 4): array {
        $stmt = $this->db->prepare(
            "SELECT d.id, d.duty_date, d.lead_employee_id, d.notes, l.full_name AS lead_name
             FROM sunday_duty_members m
             JOIN sunday_duties d ON d.id = m.duty_id
             JOIN employees l     ON l.id = d.lead_employee_id
             WHERE m.employee_id = :employee_id AND d.duty_date >= :from
             ORDER BY d.duty_date
             LIMIT :limit"
        );
        $stmt->bindValue(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':limit', max(1, min($limit, 20)), PDO::PARAM_INT);
        $stmt->execute();
        return $this->withMembers($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create(string $date, int $leadId, ?string $notes, ?int $createdBy): int {
        $stmt = $this->db->prepare(
            "INSERT INTO sunday_duties (duty_date, lead_employee_id, notes, created_by) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$date, $leadId, $notes, $createdBy]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $date, int $leadId, ?string $notes): void {
        $stmt = $this->db->prepare(
            "UPDATE sunday_duties SET duty_date = ?, lead_employee_id = ?, notes = ? WHERE id = ?"
        );
        $stmt->execute([$date, $leadId, $notes, $id]);
    }

    /** @param int[] $employeeIds */
    public function replaceMembers(int $dutyId, array $employeeIds): void {
        $this->db->prepare("DELETE FROM sunday_duty_members WHERE duty_id = ?")->execute([$dutyId]);
        if (!$employeeIds) {
            return;
        }

        $tuples = implode(', ', array_fill(0, count($employeeIds), '(?, ?)'));
        $params = [];
        foreach ($employeeIds as $employeeId) {
            $params[] = $dutyId;
            $params[] = $employeeId;
        }
        $this->db->prepare("INSERT INTO sunday_duty_members (duty_id, employee_id) VALUES {$tuples}")->execute($params);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM sunday_duties WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    // One query for all members of the given duties.
    private function withMembers(array $duties): array {
        if (!$duties) {
            return [];
        }

        $ids = array_map(fn(array $d) => (int) $d['id'], $duties);
        $in  = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT m.duty_id, e.id, e.full_name, e.role
             FROM sunday_duty_members m
             JOIN employees e ON e.id = m.employee_id
             WHERE m.duty_id IN ({$in})
             ORDER BY e.full_name"
        );
        $stmt->execute($ids);

        $byDuty = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $byDuty[(int) $m['duty_id']][] = ['id' => (int) $m['id'], 'name' => $m['full_name'], 'role' => $m['role']];
        }

        return array_map(function (array $d) use ($byDuty) {
            $d['id']               = (int) $d['id'];
            $d['lead_employee_id'] = (int) $d['lead_employee_id'];
            $d['members']          = $byDuty[$d['id']] ?? [];
            return $d;
        }, $duties);
    }
}
