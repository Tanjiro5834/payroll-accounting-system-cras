-- Bi-weekly pay (every other Saturday) for the admins.

-- 1) Allow 'biweekly' (no-op if the columns are already VARCHAR).
ALTER TABLE employees       MODIFY pay_frequency VARCHAR(20) NOT NULL DEFAULT 'weekly';
ALTER TABLE payroll_periods MODIFY pay_frequency VARCHAR(20) NOT NULL;

-- 2) One known bi-weekly payday (a Saturday). Every payday is 14 days from it.
INSERT IGNORE INTO system_settings (setting_key, setting_value, description)
VALUES ('biweekly_anchor', '2026-11-21', 'Any one bi-weekly payday (Saturday); paydays repeat every 14 days');

-- 3) Move the admins to bi-weekly.
UPDATE employees SET pay_frequency = 'biweekly'
WHERE full_name LIKE 'Wilson%' OR full_name LIKE 'Janna%' OR full_name LIKE 'Joy%';