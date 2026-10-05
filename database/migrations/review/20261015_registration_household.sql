-- ============================================================================================
-- SJQIBMS — Household answer on the online Resident Portal sign-up
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP. (Approved by the owner 2026-10-02.)
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Adds three NULL-able columns to registration_applications; no existing column or row is changed.
--   household_role         : what the applicant said: head of the household, member of a household, or unsure.
--   household_head_name    : for a member, the name of the household head as the applicant wrote it.
--   household_relationship : for a member, the relationship to the household head.
-- The existing household_no column keeps the household number the applicant gave, if any. These are the applicant's
-- own statements; staff confirm the household when they approve the registration.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registration_applications'
--     AND COLUMN_NAME IN ('household_role', 'household_head_name', 'household_relationship');   -- expected: 0
-- ============================================================================================

ALTER TABLE registration_applications
    ADD COLUMN household_role ENUM('head', 'member', 'unsure') NULL AFTER household_no,
    ADD COLUMN household_head_name VARCHAR(150) NULL AFTER household_role,
    ADD COLUMN household_relationship VARCHAR(50) NULL AFTER household_head_name;
