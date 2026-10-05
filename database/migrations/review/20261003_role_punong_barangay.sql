-- ============================================================================================
-- SJQIBMS — Financial Management, Phase 1: Punong Barangay role
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Adds ONE value, 'punong_barangay', at the END of users.role. The existing values keep their order and meaning, the
-- column stays NOT NULL with no default (as before), and no existing account is changed. Accounts get the new role only
-- when the System Administrator assigns it (account_role.php).
--
-- PRE-FLIGHT (read-only):
--   SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role';
--   -- expected: enum('super_admin','secretary','treasurer','health_worker','official','resident') | NO | NULL
-- ============================================================================================

ALTER TABLE users
    MODIFY role ENUM('super_admin', 'secretary', 'treasurer', 'health_worker', 'official', 'resident', 'punong_barangay') NOT NULL;
