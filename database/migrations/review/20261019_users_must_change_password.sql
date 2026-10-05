-- ============================================================================================
-- SJQIBMS — Force a password change at the first sign-in after an administrator sets the password.
-- STATUS: APPROVED BY THE OWNER (2026-10-04). Apply only with a verified backup.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- users.must_change_password (new, NOT NULL DEFAULT 0): 1 when the System Administrator (or staff) created the account or
-- reset its password; the account must choose its own password before using SJQIBMS. Existing accounts get 0, so
-- nobody is forced to change today. NO EXISTING ROW IS OTHERWISE CHANGED.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'must_change_password';  -- 0
-- ============================================================================================

ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash;
