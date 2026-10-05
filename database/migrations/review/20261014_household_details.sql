-- ============================================================================================
-- SJQIBMS — Household address parts and housing details (Add / Edit Household form)
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP. (Approved by the owner 2026-10-02.)
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Adds seven NULL-able columns to households; no existing column or row is changed.
--   house_no, street, zone  : the address parts entered on the form. households.address stays and is composed from
--                              them (house number, street, zone, Purok, barangay) so every report keeps working.
--   house_ownership, water_source, toilet_facility, has_electricity : optional "Additional Information".
-- Existing households keep NULL in the new columns until they are edited.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households'
--     AND COLUMN_NAME IN ('house_no', 'street', 'zone', 'house_ownership', 'water_source', 'toilet_facility', 'has_electricity');   -- expected: 0
-- ============================================================================================

ALTER TABLE households
    ADD COLUMN house_no VARCHAR(20) NULL AFTER address,
    ADD COLUMN street VARCHAR(150) NULL AFTER house_no,
    ADD COLUMN zone VARCHAR(40) NULL AFTER street,
    ADD COLUMN house_ownership VARCHAR(40) NULL AFTER housing_type,
    ADD COLUMN water_source VARCHAR(60) NULL AFTER house_ownership,
    ADD COLUMN toilet_facility VARCHAR(40) NULL AFTER water_source,
    ADD COLUMN has_electricity TINYINT(1) NULL AFTER toilet_facility;
