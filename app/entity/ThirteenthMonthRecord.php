<?php
namespace App\Entity;

class ThirteenthMonthRecord
{
    private $id;
    private $employee_id;
    private $year;
    private $months_worked;
    private $total_regular_hours;
    private $total_basic_salary;
    private $thirteenth_month_pay;
    private $status;
    private $computed_by;
    private $computed_at;
    private $paid_at;

    public function __construct(
        $id,
        $employee_id,
        $year,
        $months_worked,
        $total_regular_hours,
        $total_basic_salary,
        $thirteenth_month_pay,
        $status,
        $computed_by,
        $computed_at,
        $paid_at
    ) {
        $this->id = $id;
        $this->employee_id = $employee_id;
        $this->year = $year;
        $this->months_worked = $months_worked;
        $this->total_regular_hours = $total_regular_hours;
        $this->total_basic_salary = $total_basic_salary;
        $this->thirteenth_month_pay = $thirteenth_month_pay;
        $this->status = $status;
        $this->computed_by = $computed_by;
        $this->computed_at = $computed_at;
        $this->paid_at = $paid_at;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            $row['id'] ?? null,
            $row['employee_id'] ?? null,
            isset($row['year']) ? (int) $row['year'] : null,
            isset($row['months_worked']) ? (int) $row['months_worked'] : 0,
            isset($row['total_regular_hours']) ? (float) $row['total_regular_hours'] : 0.0,
            isset($row['total_basic_salary']) ? (float) $row['total_basic_salary'] : 0.0,
            isset($row['thirteenth_month_pay']) ? (float) $row['thirteenth_month_pay'] : 0.0,
            $row['status'] ?? 'draft',
            $row['computed_by'] ?? null,
            $row['computed_at'] ?? null,
            $row['paid_at'] ?? null
        );
    }

    // Getters
    public function getId() { return $this->id; }
    public function getEmployeeId() { return $this->employee_id; }
    public function getYear() { return $this->year; }
    public function getMonthsWorked() { return $this->months_worked; }
    public function getTotalRegularHours() { return $this->total_regular_hours; }
    public function getTotalBasicSalary() { return $this->total_basic_salary; }
    public function getThirteenthMonthPay() { return $this->thirteenth_month_pay; }
    public function getStatus() { return $this->status; }
    public function getComputedBy() { return $this->computed_by; }
    public function getComputedAt() { return $this->computed_at; }
    public function getPaidAt() { return $this->paid_at; }
}