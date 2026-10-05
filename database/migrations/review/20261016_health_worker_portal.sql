-- ============================================================================================
-- SJQIBMS — Health Worker portal (Phase 1): assigned Puroks and Health announcements
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP. (Approved by the owner 2026-10-03.)
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- 1. health_worker_puroks (new table): the Puroks a Health Worker account covers, set by the System Administrator in
--    User Management. A Health Worker with assigned Puroks sees only residents and health records of those Puroks;
--    with none assigned, all Puroks.
-- 2. announcements.category (new column, default 'general'): 'health' announcements are the ones Health Workers see
--    and post. Every existing announcement becomes 'general', so nothing changes for them.
-- No existing row is changed except that the new column is filled with its default.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'health_worker_puroks';   -- 0
--   SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'category';   -- 0
-- ============================================================================================

CREATE TABLE health_worker_puroks (
    user_id BIGINT UNSIGNED NOT NULL,
    purok VARCHAR(80) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, purok),
    CONSTRAINT fk_hwp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE announcements
    ADD COLUMN category ENUM('general', 'health') NOT NULL DEFAULT 'general' AFTER audience;
