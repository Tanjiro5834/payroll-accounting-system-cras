<?php
namespace App\Entity;

class LeaveRequest
{
    private $id;
    private $employee_id;
    private $leave_type;
    private $start_date;
    private $end_date;
    private $reason;
    private $status;
    private $approved_by;
    private $approved_at;
    private $created_at;

    public function __construct(
        $id,
        $employee_id,
        $leave_type,
        $start_date,
        $end_date,
        $reason,
        $status,
        $approved_by,
        $approved_at,
        $created_at
    ) {
        $this->id = $id;
        $this->employee_id = $employee_id;
        $this->leave_type = $leave_type;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->reason = $reason;
        $this->status = $status;
        $this->approved_by = $approved_by;
        $this->approved_at = $approved_at;
        $this->created_at = $created_at;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            $row['id'] ?? null,
            $row['employee_id'] ?? null,
            $row['leave_type'] ?? null,
            $row['start_date'] ?? null,
            $row['end_date'] ?? null,
            $row['reason'] ?? null,
            $row['status'] ?? 'pending',
            $row['approved_by'] ?? null,
            $row['approved_at'] ?? null,
            $row['created_at'] ?? null
        );
    }

    // Getters
    public function getId() { return $this->id; }
    public function getEmployeeId() { return $this->employee_id; }
    public function getLeaveType() { return $this->leave_type; }
    public function getStartDate() { return $this->start_date; }
    public function getEndDate() { return $this->end_date; }
    public function getReason() { return $this->reason; }
    public function getStatus() { return $this->status; }
    public function getApprovedBy() { return $this->approved_by; }
    public function getApprovedAt() { return $this->approved_at; }
    public function getCreatedAt() { return $this->created_at; }
}