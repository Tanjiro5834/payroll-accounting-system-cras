<?php

namespace App\Entity;

class PayrollDeduction{
    private $payrollPeriodId;
    private $deductionId;
    private $amount;
    
    public function __construct(
        $payrollPeriodId,
        $deductionId,
        $amount
    ) {
        $this->payrollPeriodId = $payrollPeriodId;
        $this->deductionId = $deductionId;
        $this->amount = $amount;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            $row['payroll_period_id'] ?? null,
            $row['deduction_id'] ?? null,
            isset($row['amount']) ? (float) $row['amount'] : 0.0
        );
    }

    // Getters
    public function getPayrollPeriodId() { return $this->payrollPeriodId; }
    public function getDeductionId() { return $this->deductionId; }
    public function getAmount() { return $this->amount; }

    // Setters (fluent)
    public function setPayrollPeriodId($payrollPeriodId): self
    {
        $this->payrollPeriodId = $payrollPeriodId;
        return $this;
    }

    public function setDeductionId($deductionId): self
    {
        $this->deductionId = $deductionId;
        return $this;
    }

    public function setAmount($amount): self
    {
        $this->amount = $amount;
        return $this;
    }
}