-- ============================================================================================
-- SJQIBMS — Health SAMPLE records (the 12 rows of the former sample page) and their sample residents
-- FOR THE DISPOSABLE TEST DATABASE ONLY (sjqibms_test). DO NOT RUN ON THE LIVE DATABASE:
-- the people and health records below are made up and would appear as real residents and real health records.
-- Requires 20260928_health_records.sql.
-- ============================================================================================

INSERT INTO residents (first_name, last_name, birth_date, sex, civil_status, address, purok, status) VALUES
    ('Maria', 'Santos', '1998-04-12', 'female', 'married', 'Sample address', '1', 'active'),
    ('Liam', 'Reyes', '2025-11-03', 'male', NULL, 'Sample address', '2', 'active'),
    ('Lolita', 'Garcia', '1956-02-20', 'female', 'widowed', 'Sample address', '3', 'active'),
    ('Andres', 'Bautista', '1970-08-15', 'male', 'married', 'Sample address', '4', 'active'),
    ('Sofia', 'Mendoza', '2018-06-01', 'female', NULL, 'Sample address', '1', 'active'),
    ('Ernesto', 'Aquino', '1965-12-09', 'male', 'married', 'Sample address', '2', 'active'),
    ('Mia', 'Castillo', '2026-03-18', 'female', NULL, 'Sample address', '3', 'active'),
    ('Corazon', 'Ramos', '1950-01-30', 'female', 'widowed', 'Sample address', '4', 'active'),
    ('Jomar', 'Villanueva', '1995-07-22', 'male', 'single', 'Sample address', '1', 'active'),
    ('Angelica', 'Torres', '1993-10-05', 'female', 'married', 'Sample address', '2', 'active'),
    ('Rodel', 'Flores', '1988-03-14', 'male', 'married', 'Sample address', '3', 'active'),
    ('Nena', 'Salazar', '2021-09-09', 'female', NULL, 'Sample address', '4', 'active');

INSERT INTO health_records (record_no, resident_id, service, service_details, health_worker, service_date, status, follow_up_date, remarks)
SELECT s.record_no, r.id, s.service, s.details, s.worker, s.service_date, s.status, s.follow_up, 'Sample record for testing.'
FROM (
    SELECT 'HLT-0112' AS record_no, 'Maria' AS first_name, 'Santos' AS last_name, 'prenatal' AS service, 'Prenatal check-up' AS details, 'Nurse Joy Alvarez' AS worker, '2026-09-24' AS service_date, 'scheduled' AS status, NULL AS follow_up
    UNION ALL SELECT 'HLT-0111', 'Liam', 'Reyes', 'immunization', 'Measles vaccination', 'BHW Carmen Dizon', '2026-09-23', 'completed', NULL
    UNION ALL SELECT 'HLT-0110', 'Lolita', 'Garcia', 'bp_monitoring', NULL, 'BHW Carmen Dizon', '2026-09-23', 'follow_up', '2026-09-30'
    UNION ALL SELECT 'HLT-0109', 'Andres', 'Bautista', 'blood_sugar_monitoring', 'Diabetes screening', 'Nurse Joy Alvarez', '2026-09-22', 'completed', NULL
    UNION ALL SELECT 'HLT-0108', 'Sofia', 'Mendoza', 'deworming', NULL, 'BHW Rina Pablo', '2026-09-21', 'completed', NULL
    UNION ALL SELECT 'HLT-0107', 'Ernesto', 'Aquino', 'tb_dots_monitoring', 'TB-DOTS medication', 'Nurse Joy Alvarez', '2026-09-20', 'follow_up', '2026-09-24'
    UNION ALL SELECT 'HLT-0106', 'Mia', 'Castillo', 'immunization', 'Pentavalent vaccine', 'BHW Rina Pablo', '2026-09-19', 'completed', NULL
    UNION ALL SELECT 'HLT-0105', 'Corazon', 'Ramos', 'check_up', 'Senior citizen check-up', 'BHW Carmen Dizon', '2026-09-18', 'completed', NULL
    UNION ALL SELECT 'HLT-0104', 'Jomar', 'Villanueva', 'other', 'Wound dressing', 'Nurse Joy Alvarez', '2026-09-17', 'completed', NULL
    UNION ALL SELECT 'HLT-0103', 'Angelica', 'Torres', 'family_planning', 'Family planning counseling', 'BHW Rina Pablo', '2026-09-16', 'cancelled', NULL
    UNION ALL SELECT 'HLT-0102', 'Rodel', 'Flores', 'check_up', 'Dengue follow-up', 'Nurse Joy Alvarez', '2026-09-15', 'follow_up', '2026-09-22'
    UNION ALL SELECT 'HLT-0101', 'Nena', 'Salazar', 'other', 'Nutrition assessment', 'BHW Carmen Dizon', '2026-09-14', 'completed', NULL
) AS s
INNER JOIN residents r ON r.first_name = s.first_name AND r.last_name = s.last_name AND r.address = 'Sample address';
