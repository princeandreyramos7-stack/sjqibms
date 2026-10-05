-- ============================================================================================
-- SJQIBMS — Disaster Management sample records. TEST DATABASE ONLY (sjqibms_test). NEVER RUN ON THE LIVE DATABASE.
-- These are the 12 former sample rows of the Disaster Management page, adapted to the new lists:
--   Training → Preparedness, Planning → Mitigation, Monitoring (type) → Incident with status Monitoring,
--   Resolved → Completed, Purok 5 / Purok 6 → Purok 4 (the barangay has Purok 1–4).
-- The affected counts on DRR-2026-11 are made-up test values, only to exercise the incident fields.
-- Requires migration 20260929_disaster_records (starter areas).
-- ============================================================================================

INSERT INTO drr_records (reference_no, ref_year, ref_seq, title, record_type, area_id, record_date, status, description, affected_families, affected_persons, alert_level, incident_details) VALUES
    ('DRR-2026-01', 2026, 1, 'Emergency kit inventory', 'preparedness', (SELECT id FROM drr_areas WHERE name = 'Barangay Hall'), '2026-09-01', 'completed', 'TEST SAMPLE', NULL, NULL, NULL, NULL),
    ('DRR-2026-02', 2026, 2, 'Landslide monitoring', 'incident', (SELECT id FROM drr_areas WHERE name = 'Purok 4'), '2026-09-03', 'monitoring', 'TEST SAMPLE', NULL, NULL, 'advisory', 'TEST SAMPLE'),
    ('DRR-2026-03', 2026, 3, 'Early warning siren test', 'preparedness', (SELECT id FROM drr_areas WHERE name = 'Barangay Hall'), '2026-09-05', 'completed', 'TEST SAMPLE', NULL, NULL, NULL, NULL),
    ('DRR-2026-04', 2026, 4, 'Hazard map update', 'mitigation', (SELECT id FROM drr_areas WHERE name = 'All Puroks'), '2026-09-08', 'ongoing', 'TEST SAMPLE', NULL, NULL, NULL, NULL),
    ('DRR-2026-05', 2026, 5, 'First aid training', 'preparedness', (SELECT id FROM drr_areas WHERE name = 'Barangay Hall'), '2026-09-10', 'completed', 'TEST SAMPLE', NULL, NULL, NULL, NULL),
    ('DRR-2026-06', 2026, 6, 'Fallen tree on road', 'incident', (SELECT id FROM drr_areas WHERE name = 'Purok 4'), '2026-09-12', 'completed', 'TEST SAMPLE', NULL, NULL, NULL, 'TEST SAMPLE'),
    ('DRR-2026-07', 2026, 7, 'Canal declogging', 'mitigation', (SELECT id FROM drr_areas WHERE name = 'Purok 1'), '2026-09-15', 'ongoing', 'TEST SAMPLE', NULL, NULL, NULL, NULL),
    ('DRR-2026-08', 2026, 8, 'Earthquake drill', 'drill', (SELECT id FROM drr_areas WHERE name = 'San Jose Elementary School'), '2026-09-18', 'completed', 'TEST SAMPLE', NULL, NULL, NULL, NULL),
    ('DRR-2026-09', 2026, 9, 'Evacuation center inspection', 'preparedness', (SELECT id FROM drr_areas WHERE name = 'Community Center'), '2026-09-20', 'completed', 'TEST SAMPLE', NULL, NULL, NULL, NULL),
    ('DRR-2026-10', 2026, 10, 'Relief goods distribution', 'response', (SELECT id FROM drr_areas WHERE name = 'Purok 3'), '2026-09-22', 'completed', 'TEST SAMPLE', NULL, NULL, NULL, NULL),
    ('DRR-2026-11', 2026, 11, 'Flooding along creek', 'incident', (SELECT id FROM drr_areas WHERE name = 'Purok 3'), '2026-09-22', 'monitoring', 'TEST SAMPLE', 12, 45, 'orange', 'TEST SAMPLE'),
    ('DRR-2026-12', 2026, 12, 'Typhoon preparedness briefing', 'preparedness', (SELECT id FROM drr_areas WHERE name = 'All Puroks'), '2026-09-26', 'planned', 'TEST SAMPLE', NULL, NULL, NULL, NULL);
