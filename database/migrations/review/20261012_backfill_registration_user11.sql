-- ============================================================================================
-- SJQIBMS — Backfill: the online sign-up made before Resident Registrations was connected
-- STATUS: ONE-TIME DATA FIX, APPROVED BY THE OWNER (2026-10-02). APPLY ONLY WITH A VERIFIED BACKUP.
--
-- Adds ONE row to registration_applications for the Pending resident account that signed up online (users.id 11,
-- linked resident profile) so it appears in Resident Registrations and on the dashboard. The row is copied from the
-- existing resident profile and account; no existing row is changed. Runs only while the account is still a Pending
-- resident account and has no application yet (otherwise it inserts nothing).
-- ============================================================================================

INSERT INTO registration_applications (application_type, first_name, middle_name, last_name, suffix, email, birth_date, sex, civil_status, contact_number, address, purok, user_id, resident_id, status, submitted_at)
SELECT 'resident', r.first_name, r.middle_name, r.last_name, r.suffix, u.email, r.birth_date, COALESCE(r.sex, 'unspecified'), COALESCE(r.civil_status, 'unspecified'), r.contact_number, r.address, r.purok, u.id, r.id, 'submitted', u.created_at
FROM users u
JOIN residents r ON r.id = u.resident_id
WHERE u.id = 11 AND u.role = 'resident' AND u.status = 'pending'
  AND NOT EXISTS (SELECT 1 FROM registration_applications a WHERE a.user_id = u.id);
