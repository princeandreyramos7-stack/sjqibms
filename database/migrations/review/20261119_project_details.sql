-- ============================================================================================
-- SJQIBMS — Projects: category and budget
-- Target: MariaDB 10.4+ (database `sjqibms`). Additive only: two empty, optional columns; no existing row is changed.
-- includes/projects.php applies this same change by itself the first time Projects is opened (if the database account
-- may change tables), so running this file by hand is needed only when that did not happen.
-- PRE-FLIGHT (read-only):
--   SELECT COLUMN_NAME FROM information_schema.COLUMNS
--    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'barangay_projects' AND COLUMN_NAME IN ('category', 'budget');
--   -- expected before: no rows
-- ============================================================================================

ALTER TABLE barangay_projects
    ADD COLUMN IF NOT EXISTS category VARCHAR(80) NULL AFTER description,
    ADD COLUMN IF NOT EXISTS budget DECIMAL(14,2) NULL AFTER category;
