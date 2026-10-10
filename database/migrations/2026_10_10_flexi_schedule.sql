-- Flexible schedule (owner's rule for Nathaniel only): any Time In, full day = 8 hours on the clock
-- (9:30 → 5:30, 10:00 → 6:00). No late deduction; OT starts after 8 hours.
ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS flexi_schedule TINYINT(1) NOT NULL DEFAULT 0 AFTER pay_frequency;

UPDATE employees SET flexi_schedule = 1 WHERE full_name = 'Nathaniel Coronacion';