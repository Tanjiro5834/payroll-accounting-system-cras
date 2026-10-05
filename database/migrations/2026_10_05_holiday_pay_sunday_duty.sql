-- Holiday pay + Sunday duty roster.

-- 1) One team per Sunday: the owner picks a lead, then the lead's team.
CREATE TABLE IF NOT EXISTS sunday_duties (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    duty_date        DATE            NOT NULL,
    lead_employee_id BIGINT UNSIGNED NOT NULL,
    notes            VARCHAR(255)    NULL,
    created_by       BIGINT UNSIGNED NULL,
    created_at       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP       NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_duty_date (duty_date),
    CONSTRAINT fk_duty_lead FOREIGN KEY (lead_employee_id) REFERENCES employees (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- The lead is stored here too, so "who is on duty" is one lookup.
CREATE TABLE IF NOT EXISTS sunday_duty_members (
    duty_id     INT             NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (duty_id, employee_id),
    KEY idx_duty_member_employee (employee_id),
    CONSTRAINT fk_duty_member_duty     FOREIGN KEY (duty_id)     REFERENCES sunday_duties (id) ON DELETE CASCADE,
    CONSTRAINT fk_duty_member_employee FOREIGN KEY (employee_id) REFERENCES employees (id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Extra pay from Sunday/holiday rates, with a per-day breakdown for the payslip.
ALTER TABLE payroll_periods
    ADD COLUMN IF NOT EXISTS premium_pay     DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER hourly_rate,
    ADD COLUMN IF NOT EXISTS premium_details TEXT          NULL                  AFTER premium_pay;

-- 3) Unworked regular holiday: 0 = no work no pay (current policy), 1 = paid 100%.
INSERT IGNORE INTO system_settings (setting_key, setting_value, description)
VALUES ('pay_unworked_regular_holiday', '0', 'Pay employees for regular holidays they did not work (1 = yes)');
