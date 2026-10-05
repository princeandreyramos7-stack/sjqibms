-- ============================================================================================
-- SJQIBMS — Residents: years of residency
-- STATUS: REVIEW ONLY. NOT IMPORTED. DO NOT RUN WITHOUT EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Adds residents.residency_start_year: the year the resident began residing in Barangay San Jose. Staff enter
-- "Years of residency" on the resident form; the application stores the starting year so the number of years stays
-- correct as time passes (years = current year - residency_start_year). It fills the "known resident of this barangay
-- for __ years" line of the Barangay Clearance.
--
-- Additive only: one new NULL-able column with no default value. No existing row, index, key or column is changed;
-- existing residents keep NULL ("Not recorded") until staff enter it. The application checks for this column and
-- behaves exactly as before while it is absent.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.COLUMNS
--    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'residents' AND COLUMN_NAME = 'residency_start_year';
--   -- expected: 0
-- ROLLBACK (only if needed, loses entered values): ALTER TABLE residents DROP COLUMN residency_start_year;
-- ============================================================================================

ALTER TABLE residents
    ADD COLUMN residency_start_year SMALLINT UNSIGNED NULL DEFAULT NULL AFTER purok;
