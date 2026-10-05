-- ============================================================================================
-- SJQIBMS — Announcements: staff-only target audiences
-- STATUS: REVIEW ONLY. NOT IMPORTED. DO NOT RUN WITHOUT EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Appends two values to announcements.audience:
--   'kagawads'  = Barangay Kagawads only (users.role 'official')
--   'all_staff' = All Barangay Staff: Kagawads, Secretary and Treasurer (Health Workers excluded)
-- The four existing values keep their names, meanings, order and default ('public'); existing rows are not changed.
-- The application shows the two new options only after this change is applied.
--
-- PRE-FLIGHT (read-only):
--   SELECT COLUMN_TYPE, COLUMN_DEFAULT FROM information_schema.COLUMNS
--    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'audience';
--   -- expected: enum('public','all_residents','purok','selected_users') | 'public'
-- ============================================================================================

ALTER TABLE announcements
    MODIFY COLUMN audience ENUM('public', 'all_residents', 'purok', 'selected_users', 'kagawads', 'all_staff') NOT NULL DEFAULT 'public';
