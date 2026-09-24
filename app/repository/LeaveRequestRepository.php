<?php
namespace App\Repository;

use App\Entity\LeaveRequest;
use PDO;
use Exception;
use RuntimeException;

class LeaveRequestRepository extends BaseRepository{
    public function create(array $data) {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, 
                 reason, status, approved_by, approved_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

            $stmt->execute([
                'employee_id' => $data['employee_id'],
                'leave_type' => $data['leave_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'],
                'status' => $data['status'],
                'approved_by' => $data['approved_by'],
                'approved_at' => $data['approved_at'],
            ]);

            $this->db->commit();
            return true;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            error_log("Failed to log leave request " . $e->getMessage());
            throw $e;
        }
    }

    public function update(int $id, array $data) {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "UPDATE leave_requests SET 
                employee_id = ?,
                leave_type = ?,
                start_date = ?,
                end_date = ?,
                reason = ?,
                status = ?,
                approved_by = ?,
                approved_at = ?
                WHERE id = ?
                ");

            $stmt->execute([
                'employee_id' => $data['employee_id'],
                'leave_type' => $data['leave_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'],
                'status' => $data['status'],
                'approved_by' => $data['approved_by'],
                'approved_at' => $data['approved_at'],
                'id' => $id,
            ]);

            $this->db->commit();
            return $stmt->rowCount();
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            error_log("Failed to update leave request " . $e->getMessage());
            throw $e;
        }
    }
    public function delete(int $id) {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("DELETE FROM leave_requests WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();
            return $stmt->rowCount() > 0;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            throw $e;
        }
    }

    public function findById(int $id) {
        $stmt = $this->db->prepare("SELECT * FROM leave_requests WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByEmployee(int $employeeId) {
        $stmt = $this->db->prepare("SELECT lr.*, e.full_name
            FROM leave_requests lr
            LEFT JOIN employees e ON e.id = lr.employee_id
            WHERE lr.employee_id = ?
            ORDER BY lr.start_date DESC");

        $stmt->execute([$employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEmployeeAndDateRange(int $employeeId, string $start, string $end) {
        $stmt = $this->db->prepare(
            "SELECT lr.*, e.full_name
            FROM leave_requests lr
            LEFT JOIN employees e ON e.id = lr.employee_id
            WHERE lr.employee_id = ?
            AND lr.start_date >= ? AND lr.end_date <= ?
            ORDER BY lr.start_date DESC"
        );

        $stmt->execute([$employeeId, $start, $end]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByStatus(string $status) {
        $stmt = $this->db->prepare("SELECT * FROM leave_requests WHERE status = ?");
        $stmt->execute([$status]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByDateRange(string $start, string $end) {
        $stmt = $this->db->prepare(
            "SELECT * FROM leave_requests WHERE start_date >= ? 
            AND end_date <= ? ORDER BY start_date DESC
            ");
        $stmt->execute([$start, $end]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAll() {
        $stmt = $this->db->prepare("SELECT * FROM leave_requests");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function approve(int $id, string $approvedBy) {
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "UPDATE leave_requests 
                SET approved_by = 'approved', 
                approved_at = NOW() 
                WHERE id = ?
                ");
            $stmt->execute([$approvedBy, $id]);

            $this->db->commit();
            return $stmt->rowCount() > 0;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            error_log("Failed to approve leave request " . $e->getMessage());
            throw $e;
        }
    }

    public function reject(int $id, string $approvedBy, string $reason) {
        try{    
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "UPDATE leave_requests SET 
                approved_by = ?, reason = ?, 
                approved_at = NOW() 
                WHERE id = ?
                ");
            $stmt->execute([$approvedBy, $reason, $id]);

            $this->db->commit();
            return $stmt->rowCount() > 0;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            error_log("Failed to reject leave request " . $e->getMessage());
            throw $e;
        }
    }

    public function cancel(int $id) {   
        try{
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT id, status FROM leave_requests WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$row) throw new RuntimeException("Leave request #$id not found.");

            if(in_array($row['status'], ['cancelled', 'rejected'], true)){
                throw new RuntimeException("Leave request #$id is already {$row['status']}");
            }

            $stmt = $this->db->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();
            return true;
        }catch(Exception $e){
            if($this->db->inTransaction()) $this->db->rollback();
            error_log("Failed to cancel leave_request #$id" . $e->getMessage());
            throw $e;
        }
    }

    public function hasApprovedLeave(int $employeeId, string $date): bool {
        $stmt = $this->db->prepare(
            "SELECT 1
            FROM leave_requests
            WHERE employee_id = ?
            AND status = 'approved'
            AND ? BETWEEN start_date AND end_date
            LIMIT 1"
        );
        $stmt->execute([$employeeId, $date]);

        return (bool) $stmt->fetchColumn();
    }

    public function countByEmployeeAndYear(int $employeeId, string $year) {
        $stmt = $this->db->prepare(
            "SELECT e.id, e.full_name, YEAR(lr.start_date) AS yr,
            COUNT(lr.id) AS request_count FROM employees e
            JOIN leave_requests lr ON lr.employee_id = e.id
            WHERE lr.employee_id = ? GROUP BY e.id, e.full_name, YEAR(lr.start_date)
            ORDER BY e.full_name, yr DESC"
        );

        $stmt->execute([$employeeId, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}