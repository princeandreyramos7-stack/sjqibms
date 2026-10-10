-- Migration check: which migrations in database/migrations/review are applied on THIS database.
-- READ-ONLY: it only SELECTs from information_schema; nothing is created, changed or deleted.
-- How to use (phpMyAdmin): open the database, go to the SQL tab, paste this whole file, click Go.
-- Each row is one migration. "MISSING" means that migration has not been applied here: import that file from
-- database/migrations/review (in date order) before the demo / defense. Back up the database first.
-- 20260925_documents_sample_template and 20261012_backfill_registration_user11 only add data, so they are not listed.

SELECT m.migration,
       m.needs,
       CASE WHEN m.kind = 'table'  AND EXISTS (SELECT 1 FROM information_schema.TABLES t  WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = m.tbl) THEN 'OK'
            WHEN m.kind = 'column' AND EXISTS (SELECT 1 FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = m.tbl AND c.COLUMN_NAME = m.col) THEN 'OK'
            WHEN m.kind = 'enum'   AND EXISTS (SELECT 1 FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME = m.tbl AND c.COLUMN_NAME = m.col AND c.COLUMN_TYPE LIKE CONCAT('%''', m.val, '''%')) THEN 'OK'
            ELSE 'MISSING' END AS status,
       m.if_missing
FROM (
    SELECT '20260925_announcement_staff_audiences' AS migration, 'enum' AS kind, 'announcements' AS tbl, 'audience' AS col, 'all_staff' AS val, 'announcements.audience = kagawads / all_staff' AS needs, 'No "Kagawads" / "All Staff" audiences' AS if_missing
    UNION ALL SELECT '20260925_announcement_barangay_officials_audience', 'enum', 'announcements', 'audience', 'barangay_officials', 'announcements.audience = barangay_officials', 'No "Barangay Officials" audience (option is hidden)'
    UNION ALL SELECT '20260925_documents_management', 'table', 'document_types', NULL, NULL, 'document_types, document_templates, document_signatories, document_releases', 'No document fees, templates, signatories or claimant release'
    UNION ALL SELECT '20260925_documents_management', 'column', 'document_requests', 'fee_amount', NULL, 'document_requests.fee_amount / payment_status', 'No fee or payment on document requests'
    UNION ALL SELECT '20260926_barangay_officials_details', 'column', 'barangay_personnel', 'term_start_year', NULL, 'barangay_personnel committee / term / contact', 'Officials have no committee, term or contact'
    UNION ALL SELECT '20260926_complaints_blotter_foundation', 'table', 'complaint_cases', NULL, NULL, 'complaint_cases, blotter_entries, case_hearings', 'Only matters if Complaints & Blotter is used'
    UNION ALL SELECT '20260926_resident_years_of_residency', 'column', 'residents', 'residency_start_year', NULL, 'residents.residency_start_year', 'No years of residency'
    UNION ALL SELECT '20260926_shared_notifications_account_link', 'table', 'user_notifications', NULL, NULL, 'user_notifications', 'Notification bell does not work'
    UNION ALL SELECT '20260927_inventory_foundation', 'table', 'inventory_items', NULL, NULL, 'inventory_items, inventory_categories, inventory_locations', 'Inventory does not work'
    UNION ALL SELECT '20260927_inventory_borrowing_movements', 'table', 'inventory_movements', NULL, NULL, 'inventory_borrow_records, inventory_movements', 'No lending, issuance or movement log'
    UNION ALL SELECT '20260928_health_records', 'table', 'health_records', NULL, NULL, 'health_records', 'Health module does not work'
    UNION ALL SELECT '20260929_disaster_records', 'table', 'drr_records', NULL, NULL, 'drr_areas, drr_records', 'Disaster Management does not work'
    UNION ALL SELECT '20260930_disaster_preparedness', 'table', 'drr_hazard_areas', NULL, NULL, 'drr_contacts, drr_evacuation_centers, drr_hazard_areas', 'No hazards, evacuation centers or hotlines'
    UNION ALL SELECT '20261001_disaster_response', 'table', 'drr_alerts', NULL, NULL, 'drr_alerts, drr_alert_areas, drr_evacuations', 'No disaster alerts or evacuees'
    UNION ALL SELECT '20261002_disaster_recovery', 'table', 'drr_relief_distributions', NULL, NULL, 'drr_relief_distributions, drr_relief_items, drr_damage_assessments', 'No relief or damage assessment'
    UNION ALL SELECT '20261003_role_punong_barangay', 'enum', 'users', 'role', 'punong_barangay', 'users.role = punong_barangay', 'Only needed for old Punong Barangay accounts'
    UNION ALL SELECT '20261004_finance_transactions', 'table', 'finance_transactions', NULL, NULL, 'finance_transactions, finance_categories, finance_attachments', 'Finance does not work'
    UNION ALL SELECT '20261005_finance_budgets', 'table', 'finance_budgets', NULL, NULL, 'finance_budgets', 'No budget'
    UNION ALL SELECT '20261006_finance_opening_balance', 'table', 'finance_opening_balances', NULL, NULL, 'finance_opening_balances', 'No opening balance'
    UNION ALL SELECT '20261007_resident_pwd_solo_parent', 'column', 'residents', 'is_pwd', NULL, 'residents.is_pwd / is_solo_parent', 'No PWD / Solo Parent (also missing from the vulnerable list)'
    UNION ALL SELECT '20261008_relief_assistance', 'table', 'assistance_distributions', NULL, NULL, 'assistance_distributions, assistance_items', 'Relief & Assistance does not work'
    UNION ALL SELECT '20261009_relief_assistance_complete', 'column', 'assistance_distributions', 'priority_groups', NULL, 'assistance_distributions.priority_groups / source / status', 'Relief & Assistance is incomplete'
    UNION ALL SELECT '20261010_sms_subscribers', 'table', 'sms_otp_requests', NULL, NULL, 'sms_otp_requests, sms_subscribers', 'Online registration (OTP) is not available'
    UNION ALL SELECT '20261011_users_username', 'column', 'users', 'username', NULL, 'users.username', 'Online registration is not available'
    UNION ALL SELECT '20261013_household_member_requests', 'table', 'household_member_requests', NULL, NULL, 'household_member_requests', 'Residents cannot request household changes'
    UNION ALL SELECT '20261014_household_details', 'column', 'households', 'zone', NULL, 'households house_no / street / zone / water / toilet', 'No household details'
    UNION ALL SELECT '20261015_registration_household', 'column', 'registration_applications', 'household_role', NULL, 'registration_applications.household_role', 'Registration cannot save the household step'
    UNION ALL SELECT '20261016_health_worker_portal', 'table', 'health_worker_puroks', NULL, NULL, 'health_worker_puroks, announcements.category', 'No Health Worker Purok assignment'
    UNION ALL SELECT '20261017_health_records_phase2', 'table', 'health_conditions', NULL, NULL, 'health_conditions, health_record_medicines, vital signs', 'No conditions, medicines or vital signs'
    UNION ALL SELECT '20261018_health_programs', 'table', 'health_pregnancies', NULL, NULL, 'health_pregnancies, immunizations, nutrition, chronic cases', 'No maternal, immunization, nutrition or chronic care'
    UNION ALL SELECT '20261019_users_must_change_password', 'column', 'users', 'must_change_password', NULL, 'users.must_change_password', 'No forced password change on first login'
) AS m
ORDER BY status = 'OK', m.migration;   -- MISSING rows first
