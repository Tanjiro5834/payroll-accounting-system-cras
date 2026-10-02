-- Audit trail: WHO did it (users.id) separately from WHO it was about (employee_id).
-- employee_id = subject employee; user_id = logged-in actor, NULL for kiosk/system
ALTER TABLE audit_trail
    ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER employee_id,
    ADD INDEX  IF NOT EXISTS idx_audit_user (user_id);
