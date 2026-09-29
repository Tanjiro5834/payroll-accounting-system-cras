CREATE TABLE IF NOT EXISTS deduction_carryovers (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    payroll_period_id INT           NOT NULL,
    employee_id       INT           NOT NULL,
    deduction_id      INT           NOT NULL,
    period_end        DATE          NOT NULL,
    amount_due        DECIMAL(12,2) NOT NULL,
    amount_deducted   DECIMAL(12,2) NOT NULL,
    shortfall         DECIMAL(12,2) NOT NULL,
    created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_carry_period_deduction (payroll_period_id, deduction_id),
    KEY idx_carry_lookup (employee_id, deduction_id, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;