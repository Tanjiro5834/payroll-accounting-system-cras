<?php
namespace App\Entity;

class DailySummary
{
    private $id;
    private $employee_id;
    private $work_date;
    private $regular_hours;
    private $overtime_hours;
    private $night_diff_hours;
    private $late_minutes;
    private $undertime_minutes;
    private $is_rest_day;
    private $computed_at;

    public function __construct(
        $id,
        $employee_id,
        $work_date,
        $regular_hours,
        $overtime_hours,
        $night_diff_hours,
        $late_minutes,
        $undertime_minutes,
        $is_rest_day,
        $computed_at
    ) {
        $this->id = $id;
        $this->employee_id = $employee_id;
        $this->work_date = $work_date;
        $this->regular_hours = $regular_hours;
        $this->overtime_hours = $overtime_hours;
        $this->night_diff_hours = $night_diff_hours;
        $this->late_minutes = $late_minutes;
        $this->undertime_minutes = $undertime_minutes;
        $this->is_rest_day = $is_rest_day;
        $this->computed_at = $computed_at;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            $row['id'] ?? null,
            $row['employee_id'] ?? null,
            $row['work_date'] ?? null,
            isset($row['regular_hours']) ? (float) $row['regular_hours'] : 0.0,
            isset($row['overtime_hours']) ? (float) $row['overtime_hours'] : 0.0,
            isset($row['night_diff_hours']) ? (float) $row['night_diff_hours'] : 0.0,
            isset($row['late_minutes']) ? (int) $row['late_minutes'] : 0,
            isset($row['undertime_minutes']) ? (int) $row['undertime_minutes'] : 0,
            (bool) ($row['is_rest_day'] ?? false),
            $row['computed_at'] ?? null
        );
    }

    // Getters
    public function getId() { return $this->id; }
    public function getEmployeeId() { return $this->employee_id; }
    public function getWorkDate() { return $this->work_date; }
    public function getRegularHours() { return $this->regular_hours; }
    public function getOvertimeHours() { return $this->overtime_hours; }
    public function getNightDiffHours() { return $this->night_diff_hours; }
    public function getLateMinutes() { return $this->late_minutes; }
    public function getUndertimeMinutes() { return $this->undertime_minutes; }
    public function getIsRestDay() { return $this->is_rest_day; }
    public function getComputedAt() { return $this->computed_at; }
}