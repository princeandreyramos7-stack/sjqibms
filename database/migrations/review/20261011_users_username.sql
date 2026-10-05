-- ============================================================================================
-- SJQIBMS — Optional username for sign-in (resident self-registration, "Email or username")
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Changes the users table only; no row is changed:
--   * adds users.username (NULL for every existing account, which keeps signing in with its email)
--   * makes users.email NULL-able so a resident may register with a username only. The existing UNIQUE key on email
--     still applies (several NULLs are allowed by a UNIQUE key).
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
--     AND TABLE_NAME = 'users' AND COLUMN_NAME = 'username';   -- expected: 0
-- ============================================================================================

ALTER TABLE users
    ADD COLUMN username VARCHAR(50) NULL AFTER email,
    ADD UNIQUE KEY uq_users_username (username),
    MODIFY email VARCHAR(190) NULL;
