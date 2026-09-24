<?php
namespace App\Entity;

class EmployeeDeduction
{
    private $id;
    private $employee_id;
    private $deduction_id;
    private $amount;
    private $effective_from;
    private $effective_to;
    private $is_active;
    private $created_at;
    private $updated_at;

    public function __construct(
        $id,
        $employee_id,
        $deduction_id,
        $amount,
        $effective_from,
        $effective_to,
        $is_active,
        $created_at,
        $updated_at
    ) {
        $this->id = $id;
        $this->employee_id = $employee_id;
        $this->deduction_id = $deduction_id;
        $this->amount = $amount;
        $this->effective_from = $effective_from;
        $this->effective_to = $effective_to;
        $this->is_active = $is_active;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            $row['id'] ?? null,
            $row['employee_id'] ?? null,
            $row['deduction_id'] ?? null,
            isset($row['amount']) ? (float) $row['amount'] : null,
            $row['effective_from'] ?? null,
            $row['effective_to'] ?? null,
            (bool) ($row['is_active'] ?? true),
            $row['created_at'] ?? null,
            $row['updated_at'] ?? null
        );
    }

    // Getters
    public function getId() { return $this->id; }
    public function getEmployeeId() { return $this->employee_id; }
    public function getDeductionId() { return $this->deduction_id; }
    public function getAmount() { return $this->amount; }
    public function getEffectiveFrom() { return $this->effective_from; }
    public function getEffectiveTo() { return $this->effective_to; }
    public function getIsActive() { return $this->is_active; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }
}