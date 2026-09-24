<?php
namespace App\Entity;

class PayrollPeriod
{
    private $id;
    private $employee_id;
    private $period_start;
    private $period_end;
    private $pay_frequency;
    private $total_regular_hours;
    private $total_overtime_hours;
    private $total_night_diff_hours;
    private $total_late_minutes;
    private $total_undertime_minutes;
    private $hourly_rate;
    private $gross_pay;
    private $total_deductions;
    private $net_pay;
    private $status;
    private $computed_by;
    private $computed_at;
    private $paid_at;
    private $notes;

    public function __construct(
        $id,
        $employee_id,
        $period_start,
        $period_end,
        $pay_frequency,
        $total_regular_hours,
        $total_overtime_hours,
        $total_night_diff_hours,
        $total_late_minutes,
        $total_undertime_minutes,
        $hourly_rate,
        $gross_pay,
        $total_deductions,
        $net_pay,
        $status,
        $computed_by,
        $computed_at,
        $paid_at,
        $notes
    ) {
        $this->id = $id;
        $this->employee_id = $employee_id;
        $this->period_start = $period_start;
        $this->period_end = $period_end;
        $this->pay_frequency = $pay_frequency;
        $this->total_regular_hours = $total_regular_hours;
        $this->total_overtime_hours = $total_overtime_hours;
        $this->total_night_diff_hours = $total_night_diff_hours;
        $this->total_late_minutes = $total_late_minutes;
        $this->total_undertime_minutes = $total_undertime_minutes;
        $this->hourly_rate = $hourly_rate;
        $this->gross_pay = $gross_pay;
        $this->total_deductions = $total_deductions;
        $this->net_pay = $net_pay;
        $this->status = $status;
        $this->computed_by = $computed_by;
        $this->computed_at = $computed_at;
        $this->paid_at = $paid_at;
        $this->notes = $notes;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            $row['id'] ?? null,
            $row['employee_id'] ?? null,
            $row['period_start'] ?? null,
            $row['period_end'] ?? null,
            $row['pay_frequency'] ?? null,
            isset($row['total_regular_hours']) ? (float) $row['total_regular_hours'] : 0.0,
            isset($row['total_overtime_hours']) ? (float) $row['total_overtime_hours'] : 0.0,
            isset($row['total_night_diff_hours']) ? (float) $row['total_night_diff_hours'] : 0.0,
            isset($row['total_late_minutes']) ? (int) $row['total_late_minutes'] : 0,
            isset($row['total_undertime_minutes']) ? (int) $row['total_undertime_minutes'] : 0,
            isset($row['hourly_rate']) ? (float) $row['hourly_rate'] : 0.0,
            isset($row['gross_pay']) ? (float) $row['gross_pay'] : 0.0,
            isset($row['total_deductions']) ? (float) $row['total_deductions'] : 0.0,
            isset($row['net_pay']) ? (float) $row['net_pay'] : 0.0,
            $row['status'] ?? 'draft',
            $row['computed_by'] ?? null,
            $row['computed_at'] ?? null,
            $row['paid_at'] ?? null,
            $row['notes'] ?? null
        );
    }

    // Getters
    public function getId() { return $this->id; }
    public function getEmployeeId() { return $this->employee_id; }
    public function getPeriodStart() { return $this->period_start; }
    public function getPeriodEnd() { return $this->period_end; }
    public function getPayFrequency() { return $this->pay_frequency; }
    public function getTotalRegularHours() { return $this->total_regular_hours; }
    public function getTotalOvertimeHours() { return $this->total_overtime_hours; }
    public function getTotalNightDiffHours() { return $this->total_night_diff_hours; }
    public function getTotalLateMinutes() { return $this->total_late_minutes; }
    public function getTotalUndertimeMinutes() { return $this->total_undertime_minutes; }
    public function getHourlyRate() { return $this->hourly_rate; }
    public function getGrossPay() { return $this->gross_pay; }
    public function getTotalDeductions() { return $this->total_deductions; }
    public function getNetPay() { return $this->net_pay; }
    public function getStatus() { return $this->status; }
    public function getComputedBy() { return $this->computed_by; }
    public function getComputedAt() { return $this->computed_at; }
    public function getPaidAt() { return $this->paid_at; }
    public function getNotes() { return $this->notes; }
}