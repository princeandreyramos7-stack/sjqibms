-- ============================================================================================
-- SJQIBMS — Residents: PWD and Solo Parent
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Adds TWO columns to residents; no row values are changed (every existing resident starts as "not PWD" and
-- "not a solo parent" until staff tick the boxes on the resident profile). No table is created or dropped.
--   is_pwd          1 = person with disability (checkbox on the resident profile)
--   is_solo_parent  1 = solo parent (checkbox on the resident profile)
-- Used by: resident profile and list, Disaster > Vulnerable Residents, Dashboard, Reports (Residents).
-- Until this is applied, every page behaves as before (the columns are detected at run time).
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'residents'
--     AND COLUMN_NAME IN ('is_pwd', 'is_solo_parent'); -- expected: 0
-- ============================================================================================

ALTER TABLE residents
    ADD COLUMN is_pwd TINYINT(1) NOT NULL DEFAULT 0 AFTER civil_status,
    ADD COLUMN is_solo_parent TINYINT(1) NOT NULL DEFAULT 0 AFTER is_pwd;
