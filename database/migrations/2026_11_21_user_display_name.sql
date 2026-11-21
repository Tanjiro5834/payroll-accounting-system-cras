-- Display name for logins that aren't linked to an employee record (e.g. the owner).
-- Set it per account: UPDATE users SET display_name = '<name>' WHERE id = <id>;
ALTER TABLE users ADD COLUMN IF NOT EXISTS display_name VARCHAR(100) NULL AFTER username;