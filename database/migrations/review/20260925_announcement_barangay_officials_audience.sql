-- ============================================================================================
-- SJQIBMS — Announcements: "Barangay Officials" target audience
-- STATUS: REVIEW ONLY. NOT IMPORTED. DO NOT RUN WITHOUT EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Appends 'barangay_officials' to announcements.audience. Recipients (enforced in application code): active accounts
-- with role super_admin (Barangay Captain / System Administrator), secretary, treasurer or official (Barangay Kagawads;
-- SK Chairman and SK Kagawads use the same role). Health Workers and residents are excluded.
--
-- Additive only: the six existing values ('public','all_residents','purok','selected_users','kagawads','all_staff'),
-- their order and the default ('public') are unchanged; no announcement or notification row is modified.
-- 'kagawads' and 'all_staff' are no longer offered for new announcements but stay valid for any record that uses them.
--
-- PRE-FLIGHT (read-only):
--   SELECT COLUMN_TYPE, COLUMN_DEFAULT FROM information_schema.COLUMNS
--    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'audience';
--   -- expected: enum('public','all_residents','purok','selected_users','kagawads','all_staff') | 'public'
-- ============================================================================================

ALTER TABLE announcements
    MODIFY COLUMN audience ENUM('public', 'all_residents', 'purok', 'selected_users', 'kagawads', 'all_staff', 'barangay_officials') NOT NULL DEFAULT 'public';
