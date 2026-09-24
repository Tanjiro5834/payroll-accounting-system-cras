<?php
namespace App\Repository;

use PDO;

class AuditTrailRepository extends BaseRepository{
    public function log(?int $employeeId, string $actionType, $details = null, ?string $ip = null){
        try{
            $stmt = $this->db->prepare(
                "INSERT INTO audit_trail (employee_id, action_type, action_details, ip_address, performed_at)
                VALUES (?, ?, ?, ?, NOW())"
            );

            $success = $stmt->execute([
                $employeeId,
                $actionType,
                is_array($details) ? json_encode($details) : $details,
                $ip,
            ]);

            if(!$success){
                $this->db->rollback();
                return false;
            }

            $this->db->commit();
            return true;
        }catch(Exception $e){
            error_log("Logging audit trail failed" . $e->getMessage());
            throw $e;
        }
    }

    public function findByDateRange(string $start, string $end, ?int $employeeId = null): array{
        $sql = "SELECT at.*, e.full_name 
                FROM audit_trail at
                LEFT JOIN employees e ON e.id = at.employee_id
                WHERE DATE(at.performed_at) BETWEEN ? AND ?";
        $params = [$start, $end];
        if ($employeeId) {
            $sql .= " AND at.employee_id = ?";
            $params[] = $employeeId;
        }
        $sql .= " ORDER BY at.performed_at DESC LIMIT 500";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByActionType(string $actionType, string $start, string $end) : array{
        $sql = "SELECT at.*, e.full_name 
                FROM audit_trail at
                LEFT JOIN employees e ON e.id = at.employee_id
                WHERE action_type = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$actionType, $start, $end]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEmployee(int $employeeId, int $limit = 100) : array {
        $stmt = $this->db->prepare("SELECT at.*, e.full_name
                FROM audit_trail at 
                LEFT JOIN employees e ON e.id = at.employee_id
                WHERE at.employee_id = ?
                ORDER BY at.created_at DESC
                LIMIT ?");
        $stmt->bindValue(1, $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByActionType(string $actionType, string $start, string $end): int {
        $sql = "SELECT COUNT(*) 
                FROM audit_trail 
                WHERE action_type = ?
                  AND DATE(performed_at) BETWEEN ? AND ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$actionType, $start, $end]);

        return (int) $stmt->fetchColumn();
    }

    public function purgeOlderThan(string $date): int {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("DELETE FROM audit_trail WHERE performed_at < ?");
            $stmt->execute([$date]);

            $deleted = $stmt->rowCount();

            $this->db->commit();

            return $deleted;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Audit trail purge failed: " . $e->getMessage());
            throw $e;
        }
    }
}