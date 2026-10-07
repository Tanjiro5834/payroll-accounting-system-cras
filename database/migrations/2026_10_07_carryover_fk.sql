-- Deleting a payroll now deletes its deduction carryovers too, so manual deletes can't leave orphans
-- that get charged on a later payday.

-- 1) Remove existing orphans (carryovers whose payroll is already gone).
DELETE dc FROM deduction_carryovers dc
LEFT JOIN payroll_periods pp ON pp.id = dc.payroll_period_id
WHERE pp.id IS NULL;

-- 2) A foreign key needs both columns to have the exact same type, so copy payroll_periods.id's type.
SET @pp_type = (
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payroll_periods' AND COLUMN_NAME = 'id'
);
SET @sql = CONCAT('ALTER TABLE deduction_carryovers MODIFY payroll_period_id ', @pp_type, ' NOT NULL');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Add the foreign key (skipped if it already exists, so this file is safe to run twice).
SET @has_fk = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'deduction_carryovers'
      AND CONSTRAINT_NAME = 'fk_carry_payroll' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql = IF(@has_fk = 0,
    'ALTER TABLE deduction_carryovers ADD CONSTRAINT fk_carry_payroll FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods (id) ON DELETE CASCADE',
    'SELECT ''fk_carry_payroll already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
