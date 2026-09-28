-- Adds approval tracking to 13th month records.
-- Required by ThirteenthMonthRepository::approve().
ALTER TABLE thirteenth_month_records
  ADD COLUMN approved_by BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER status,
  ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL AFTER approved_by,
  ADD CONSTRAINT fk_13th_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL;

-- Covers the dashboard's per-day punch queries (present / late / not punched in).
ALTER TABLE time_punches
  ADD INDEX idx_date_type_emp (work_date, punch_type, employee_id, punch_time);
