-- Migration: Add legacy/expected status values to competition_entries.status
-- Date: 2025-12-04
-- WARNING: Run only after backing up the database.
-- Adds 'submitted','pending','approved' to the enum so older code paths will work.

-- Check current definition (run manually):
-- SHOW COLUMNS FROM competition_entries LIKE 'status';

-- Migration: expand enum to include the legacy states
ALTER TABLE competition_entries
    MODIFY COLUMN status ENUM('active','disqualified','completed','submitted','pending','approved') NOT NULL DEFAULT 'active';

-- Optionally, if you want to normalize existing rows that have unexpected values, run updates prior to changing the enum.
-- Rollback (if needed): change back to original values (ensure no rows use the removed values).
-- ALTER TABLE competition_entries
--     MODIFY COLUMN status ENUM('active','disqualified','completed') NOT NULL DEFAULT 'active';

-- Backup suggestion:
-- mysqldump -u <user> -p <database> competition_entries > competition_entries_backup_20251204.sql

-- After migration, run application tests and check competition listings.
