<?php
namespace App\Repository;

use PDO;

class RateHistoryRepository extends BaseRepository {
    public function record(int $employeeId, string $payFrequency, ?string $hourly, ?string $monthly, string $effectiveDate, ?int $changedBy): int {
        $stmt = $this->db->prepare(
            "INSERT INTO employee_rate_history
                (employee_id, pay_frequency, hourly_rate, monthly_rate, effective_date, changed_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$employeeId, $payFrequency, $hourly, $monthly, $effectiveDate, $changedBy]);

        return (int) $this->db->lastInsertId();
    }

    // Oldest first, so the client can draw the progression left to right.
    public function findByEmployee(int $employeeId): array {
        $stmt = $this->db->prepare(
            "SELECT h.id, h.pay_frequency, h.hourly_rate, h.monthly_rate, h.effective_date,
                    h.is_baseline, u.username AS changed_by_username
             FROM employee_rate_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.employee_id = ?
             ORDER BY h.effective_date ASC, h.id ASC"
        );
        $stmt->execute([$employeeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
