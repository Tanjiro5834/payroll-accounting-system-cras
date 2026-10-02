-- Employee self-service dashboard.
-- 1) Who approved each weekly payroll (13th month already has approved_by/approved_at).
ALTER TABLE payroll_periods
    ADD COLUMN IF NOT EXISTS approved_by BIGINT UNSIGNED NULL AFTER computed_at,
    ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL AFTER approved_by;

-- Backfill from the audit trail for payrolls approved since audit logging went live.
UPDATE payroll_periods pp
JOIN (
    SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(action_details, '$.payroll_id')) AS UNSIGNED) AS payroll_id,
           MAX(user_id)      AS user_id,
           MAX(performed_at) AS performed_at
    FROM audit_trail
    WHERE action_type = 'PAYROLL_APPROVE'
    GROUP BY payroll_id
) a ON a.payroll_id = pp.id
SET pp.approved_by = a.user_id,
    pp.approved_at = a.performed_at
WHERE pp.approved_by IS NULL;

-- 2) Salary progression. One row per rate change; the first row per employee is the
--    rate on record when this table was created (is_baseline = 1), not necessarily their starting pay.
CREATE TABLE IF NOT EXISTS employee_rate_history (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    employee_id    BIGINT UNSIGNED NOT NULL,
    pay_frequency  VARCHAR(20)     NOT NULL,
    hourly_rate    DECIMAL(12,2)   NULL,
    monthly_rate   DECIMAL(12,2)   NULL,
    effective_date DATE            NOT NULL,
    changed_by     BIGINT UNSIGNED NULL,
    is_baseline    TINYINT(1)      NOT NULL DEFAULT 0,
    created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rate_employee (employee_id, effective_date),
    CONSTRAINT fk_rate_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO employee_rate_history (employee_id, pay_frequency, hourly_rate, monthly_rate, effective_date, is_baseline)
SELECT e.id, e.pay_frequency, e.hourly_rate, e.monthly_rate, CURDATE(), 1
FROM employees e
WHERE NOT EXISTS (SELECT 1 FROM employee_rate_history h WHERE h.employee_id = e.id);
