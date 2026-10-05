-- SJQIBMS resident status expansion migration
-- Target database: sjqibms
-- Prepared for review only. Do not execute until live-schema checks pass.
-- Dependency: the existing residents table. This file is independent of
-- 20260922_dashboard_foundation.sql and contains one existing-table alteration.

USE `sjqibms`;

-- Confirm before execution:
-- SHOW CREATE TABLE residents;
-- SELECT DISTINCT status FROM residents;
-- SHOW INDEX FROM residents;
--
-- The application inspection found no PHP code that assumes the current
-- residents.status ENUM values. Live SQL consumers, views, triggers, reports,
-- and other deployment files must still be checked manually.

ALTER TABLE residents
    MODIFY status ENUM('active', 'moved', 'deceased', 'inactive', 'pending')
    NOT NULL DEFAULT 'active';

-- No resident rows are updated. Existing values remain valid and retain their
-- current meaning. Future verified applications may create a resident row with
-- status = 'pending'; final approval may later change that row to 'active' in
-- the same transaction as the linked users.status change.

-- Compatibility and recovery notes:
-- 1. ENUM modification rebuilds or copies the table in some MariaDB versions;
--    plan for a maintenance window and confirm table size/locking behavior.
-- 2. Any unexpected status value or strict-mode issue must be resolved before
--    execution. This script intentionally does not coerce or update rows.
-- 3. Take a verified backup first. MySQL DDL may implicitly commit, so this
--    cannot be safely rolled back with a normal transaction rollback.
-- 4. Recovery requires restoring the backup or a reviewed reverse ALTER TABLE;
--    do not automatically remove 'pending' after application code uses it.
