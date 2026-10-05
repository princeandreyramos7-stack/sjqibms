-- ============================================================================================
-- SJQIBMS — Barangay Officials: details for the officials and staff registry
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- barangay_personnel (created by 20260926_complaints_blotter_foundation.sql) becomes the single list of barangay officials
-- and staff: the Barangay Officials page reads and edits it, and hearing assignments keep using it.
--   * status: 'on_leave' is APPENDED ('active', 'inactive' keep their meaning and positions; default stays 'active').
--     Hearing assignment only accepts 'active', so an official on leave is not assignable.
--   * committee, service_type (elected / appointed), term_start_year, term_end_year, contact_number: new NULL-able columns.
--
-- Additive only: no existing row, key, index or foreign key is changed.
--
-- PRE-FLIGHT (read-only):
--   SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
--    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'barangay_personnel' ORDER BY ORDINAL_POSITION;
--   -- expected: status = enum('active','inactive'); no committee/service_type/term_*/contact_number columns
-- ============================================================================================

ALTER TABLE barangay_personnel
    MODIFY COLUMN status ENUM('active', 'inactive', 'on_leave') NOT NULL DEFAULT 'active',
    ADD COLUMN committee VARCHAR(120) NULL DEFAULT NULL AFTER position,
    ADD COLUMN service_type ENUM('elected', 'appointed') NULL DEFAULT NULL AFTER committee,
    ADD COLUMN term_start_year SMALLINT UNSIGNED NULL DEFAULT NULL AFTER service_type,
    ADD COLUMN term_end_year SMALLINT UNSIGNED NULL DEFAULT NULL AFTER term_start_year,
    ADD COLUMN contact_number VARCHAR(30) NULL DEFAULT NULL AFTER term_end_year;
