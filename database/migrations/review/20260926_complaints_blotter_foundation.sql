-- ============================================================================================
-- SJQIBMS — Complaints & Blotter Management foundation
-- STATUS: REVIEW ONLY. NOT IMPORTED. DO NOT RUN WITHOUT EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`), InnoDB, utf8mb4 / utf8mb4_unicode_ci.
--
-- Additive only:
--   * complaint_cases (existing, 0 rows at review time) gains columns, indexes, foreign keys and two
--     appended ENUM values. Nothing is dropped, renamed or rewritten; no existing row is updated.
--   * Eleven new tables. No other existing table is altered.
--   * No sample, test or fabricated data is inserted. No permissions or accounts are changed.
--
-- MariaDB executes DDL with implicit commits, so this file cannot be rolled back as one transaction.
-- Take a full backup (phpMyAdmin Export or mysqldump) immediately before importing.
--
-- DEPENDENCIES (see the readiness report):
--   * Private notifications reuse `user_notifications` and online resident complaints rely on one account
--     per resident profile (uq_users_resident). Both are defined in the shared review migration
--     20260926_shared_notifications_account_link.sql — import that file first. This file deliberately does
--     NOT create or alter them. Nothing in THIS file depends on the Documents migration.
--
-- PRE-FLIGHT (read-only; run first and review the results):
--   SELECT COUNT(*) FROM complaint_cases;                                   -- expected 0 at review time
--   SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN
--     ('barangay_personnel','hearing_venues','blotter_entries','case_persons','case_status_history',
--      'case_hearings','case_hearing_schedule_history','case_hearing_participants',
--      'case_hearing_personnel','case_attachments','case_attachment_access_log');  -- expected 0 rows
--   SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
--     AND TABLE_NAME = 'complaint_cases' AND COLUMN_NAME = 'status';
--     -- expected: enum('open','under_review','for_hearing','settled','dismissed','closed')
-- ============================================================================================


-- 1. Extend complaint_cases ------------------------------------------------------------------
-- Status: the six original values keep their names, meanings and positions; 'pending_review' and
-- 'resolved' are APPENDED (existing stored values are unaffected). New complaints default to
-- 'pending_review'. 'settled' and 'dismissed' are NOT repurposed.
-- Existing columns kept as-is: case_number = complaint reference; confidential_details = complaint
-- description; complainant_/respondent_ columns = primary-party snapshot (case_persons is the full,
-- authoritative list); resolved_at is reused; hearing_at becomes legacy (hearings live in case_hearings).
ALTER TABLE complaint_cases
    MODIFY COLUMN status ENUM('open', 'under_review', 'for_hearing', 'settled', 'dismissed', 'closed',
                              'pending_review', 'resolved') NOT NULL DEFAULT 'pending_review',
    ADD COLUMN category VARCHAR(80) NULL,                       -- barangay-approved category list pending
    ADD COLUMN incident_at DATETIME NULL,
    ADD COLUMN incident_location VARCHAR(255) NULL,
    ADD COLUMN submission_source ENUM('staff', 'online') NULL,  -- required by the application
    ADD COLUMN submitted_by_user_id BIGINT UNSIGNED NULL,     -- account that recorded/submitted it
    ADD COLUMN is_confidential TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN reviewed_by BIGINT UNSIGNED NULL,
    ADD COLUMN reviewed_at DATETIME NULL,
    ADD COLUMN resolved_by BIGINT UNSIGNED NULL,
    ADD COLUMN resolution_notes TEXT NULL,
    ADD COLUMN closed_by BIGINT UNSIGNED NULL,
    ADD COLUMN closed_at DATETIME NULL,
    ADD COLUMN closing_notes TEXT NULL,
    ADD KEY idx_complaints_category_filed (category, filed_at),
    ADD KEY idx_complaints_source_filed (submission_source, filed_at),
    ADD KEY idx_complaints_submitted_by (submitted_by_user_id, filed_at),
    ADD KEY idx_complaints_filed (filed_at),
    ADD CONSTRAINT fk_complaints_submitted_by FOREIGN KEY (submitted_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_complaints_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_complaints_resolved_by FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_complaints_closed_by FOREIGN KEY (closed_by) REFERENCES users (id) ON DELETE SET NULL,
    ADD CONSTRAINT ck_complaints_confidential CHECK (is_confidential IN (0, 1));


-- 2. Barangay personnel (people, with or without an SJQIBMS account) -------------------------
-- Administrative record only: a personnel row or hearing assignment NEVER grants application access.
CREATE TABLE barangay_personnel (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(180) NOT NULL,
    position VARCHAR(120) NOT NULL,                   -- official position (barangay-approved wording)
    user_id BIGINT UNSIGNED NULL,                     -- optional linked account; NULL for personnel without accounts
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_barangay_personnel_user (user_id),  -- an account maps to at most one personnel record (NULLs allowed)
    KEY idx_barangay_personnel_status_name (status, full_name),
    CONSTRAINT fk_barangay_personnel_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_barangay_personnel_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 3. Hearing venues (normalized, so venue conflicts match reliably and can be row-locked) -----
CREATE TABLE hearing_venues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    name_normalized VARCHAR(150) NOT NULL,            -- lower-cased, single-spaced (set by the application)
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hearing_venues_name (name_normalized),
    CONSTRAINT fk_hearing_venues_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT ck_hearing_venues_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 4. Blotter entries (one complaint -> many entries; standalone entries have complaint_id NULL) --
-- complaint_id is indexed but deliberately NOT unique. ON DELETE RESTRICT: a complaint with blotter
-- entries cannot be deleted, so case records are never silently orphaned or cascaded away.
CREATE TABLE blotter_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blotter_number VARCHAR(50) NOT NULL,
    complaint_id BIGINT UNSIGNED NULL,
    incident_type VARCHAR(80) NOT NULL,               -- barangay-approved incident types pending
    incident_at DATETIME NOT NULL,
    incident_location VARCHAR(255) NOT NULL,
    narrative TEXT NOT NULL,
    status ENUM('recorded', 'active', 'resolved', 'closed') NOT NULL DEFAULT 'recorded',
    is_confidential TINYINT(1) NOT NULL DEFAULT 1,
    recorded_by BIGINT UNSIGNED NULL,
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processing_started_by BIGINT UNSIGNED NULL,
    processing_started_at DATETIME NULL,
    resolved_by BIGINT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    resolution_notes TEXT NULL,
    closed_by BIGINT UNSIGNED NULL,
    closed_at DATETIME NULL,
    closing_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_blotter_number (blotter_number),
    KEY idx_blotter_complaint (complaint_id),
    KEY idx_blotter_status_recorded (status, recorded_at),
    KEY idx_blotter_type_incident (incident_type, incident_at),
    KEY idx_blotter_recorded (recorded_at),
    CONSTRAINT fk_blotter_complaint FOREIGN KEY (complaint_id) REFERENCES complaint_cases (id) ON DELETE RESTRICT,
    CONSTRAINT fk_blotter_recorded_by FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_blotter_started_by FOREIGN KEY (processing_started_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_blotter_resolved_by FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_blotter_closed_by FOREIGN KEY (closed_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT ck_blotter_confidential CHECK (is_confidential IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 5. Involved persons (complainants, respondents, witnesses, others) --------------------------
-- Each row belongs to EXACTLY ONE complaint or ONE blotter entry (CHECK). A linked blotter copies the
-- complaint's persons into NEW rows, so editing blotter parties never changes the complaint.
-- resident_id is optional: non-residents need no resident profile. full_name is a snapshot.
CREATE TABLE case_persons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    complaint_id BIGINT UNSIGNED NULL,
    blotter_id BIGINT UNSIGNED NULL,
    person_role ENUM('complainant', 'respondent', 'witness', 'other') NOT NULL,
    resident_id BIGINT UNSIGNED NULL,
    full_name VARCHAR(180) NOT NULL,
    contact_number VARCHAR(30) NULL,
    address TEXT NULL,
    identifying_details VARCHAR(255) NULL,            -- available identifying information (e.g. age, description)
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_case_persons_complaint (complaint_id, person_role),
    KEY idx_case_persons_blotter (blotter_id, person_role),
    KEY idx_case_persons_resident (resident_id),
    CONSTRAINT fk_case_persons_complaint FOREIGN KEY (complaint_id) REFERENCES complaint_cases (id) ON DELETE RESTRICT,
    CONSTRAINT fk_case_persons_blotter FOREIGN KEY (blotter_id) REFERENCES blotter_entries (id) ON DELETE RESTRICT,
    CONSTRAINT fk_case_persons_resident FOREIGN KEY (resident_id) REFERENCES residents (id) ON DELETE SET NULL,
    CONSTRAINT fk_case_persons_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT ck_case_persons_one_parent CHECK ((complaint_id IS NULL) <> (blotter_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 6. Case processing history (append-only; confidential notes stay here, not in audit_logs) ----
-- The application only INSERTs into this table; rows are never updated or deleted.
CREATE TABLE case_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    complaint_id BIGINT UNSIGNED NULL,
    blotter_id BIGINT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,                      -- e.g. submitted, review_started, resolved, closed, blotter_recorded
    from_status VARCHAR(30) NULL,
    to_status VARCHAR(30) NOT NULL,
    notes TEXT NULL,                                  -- resolution/closing notes or reason for this action
    actor_user_id BIGINT UNSIGNED NULL,
    acted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_case_history_complaint (complaint_id, acted_at),
    KEY idx_case_history_blotter (blotter_id, acted_at),
    KEY idx_case_history_actor (actor_user_id, acted_at),
    CONSTRAINT fk_case_history_complaint FOREIGN KEY (complaint_id) REFERENCES complaint_cases (id) ON DELETE RESTRICT,
    CONSTRAINT fk_case_history_blotter FOREIGN KEY (blotter_id) REFERENCES blotter_entries (id) ON DELETE RESTRICT,
    CONSTRAINT fk_case_history_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT ck_case_history_one_parent CHECK ((complaint_id IS NULL) <> (blotter_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 7. Hearings (each belongs to exactly one complaint or one blotter entry) --------------------
-- Conflict checks consider status IN ('scheduled','rescheduled') and the overlap rule
-- existing.starts_at < proposed.ends_at AND existing.ends_at > proposed.starts_at, per venue and per
-- assigned personnel. DATETIME values are stored in the application timezone (Asia/Manila).
CREATE TABLE case_hearings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hearing_number VARCHAR(50) NOT NULL,
    complaint_id BIGINT UNSIGNED NULL,
    blotter_id BIGINT UNSIGNED NULL,
    hearing_type VARCHAR(80) NOT NULL,                -- barangay-approved hearing types pending
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    venue_id BIGINT UNSIGNED NOT NULL,
    status ENUM('scheduled', 'rescheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    notes TEXT NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancellation_reason TEXT NULL,
    outcome_summary TEXT NULL,                        -- recorded only after the hearing actually took place
    outcome_recorded_by BIGINT UNSIGNED NULL,
    outcome_recorded_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hearing_number (hearing_number),
    KEY idx_hearings_venue_time (venue_id, starts_at, ends_at),
    KEY idx_hearings_status_time (status, starts_at),
    KEY idx_hearings_complaint (complaint_id, starts_at),
    KEY idx_hearings_blotter (blotter_id, starts_at),
    CONSTRAINT fk_hearings_complaint FOREIGN KEY (complaint_id) REFERENCES complaint_cases (id) ON DELETE RESTRICT,
    CONSTRAINT fk_hearings_blotter FOREIGN KEY (blotter_id) REFERENCES blotter_entries (id) ON DELETE RESTRICT,
    CONSTRAINT fk_hearings_venue FOREIGN KEY (venue_id) REFERENCES hearing_venues (id) ON DELETE RESTRICT,
    CONSTRAINT fk_hearings_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_hearings_outcome_by FOREIGN KEY (outcome_recorded_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_hearings_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_hearings_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT ck_hearings_one_parent CHECK ((complaint_id IS NULL) <> (blotter_id IS NULL)),
    CONSTRAINT ck_hearings_time_order CHECK (ends_at > starts_at),
    CONSTRAINT ck_hearings_outcome_only_completed CHECK (status = 'completed' OR outcome_recorded_at IS NULL),
    CONSTRAINT ck_hearings_completed_has_outcome CHECK (status <> 'completed' OR (outcome_summary IS NOT NULL AND outcome_recorded_at IS NOT NULL)),
    CONSTRAINT ck_hearings_cancelled_has_reason CHECK (status <> 'cancelled' OR (cancelled_at IS NOT NULL AND cancellation_reason IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 8. Hearing schedule history (original schedule is preserved; one row per schedule event) ------
-- change_type 'initial' records the first schedule (previous_* NULL); each reschedule adds a row with
-- the previous and new times/venue. Hearing notifications for a reschedule can reference this row's id.
CREATE TABLE case_hearing_schedule_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hearing_id BIGINT UNSIGNED NOT NULL,
    change_type ENUM('initial', 'rescheduled') NOT NULL,
    previous_starts_at DATETIME NULL,
    previous_ends_at DATETIME NULL,
    previous_venue_id BIGINT UNSIGNED NULL,
    new_starts_at DATETIME NOT NULL,
    new_ends_at DATETIME NOT NULL,
    new_venue_id BIGINT UNSIGNED NOT NULL,
    reason TEXT NULL,                                 -- required by the application for 'rescheduled'
    changed_by BIGINT UNSIGNED NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_schedule_history_hearing (hearing_id, changed_at),
    CONSTRAINT fk_schedule_history_hearing FOREIGN KEY (hearing_id) REFERENCES case_hearings (id) ON DELETE RESTRICT,
    CONSTRAINT fk_schedule_history_prev_venue FOREIGN KEY (previous_venue_id) REFERENCES hearing_venues (id) ON DELETE RESTRICT,
    CONSTRAINT fk_schedule_history_new_venue FOREIGN KEY (new_venue_id) REFERENCES hearing_venues (id) ON DELETE RESTRICT,
    CONSTRAINT fk_schedule_history_changed_by FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT ck_schedule_history_order CHECK (new_ends_at > new_starts_at),
    CONSTRAINT ck_schedule_history_previous CHECK (
        (change_type = 'initial' AND previous_starts_at IS NULL AND previous_ends_at IS NULL AND previous_venue_id IS NULL)
        OR (change_type = 'rescheduled' AND previous_starts_at IS NOT NULL AND previous_ends_at IS NOT NULL AND previous_venue_id IS NOT NULL AND reason IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 9. Hearing participants and attendance (per hearing session) --------------------------------
-- A participant is an involved person of the case (case_person_id) or an additional authorized
-- participant identified by name. Attendance is recorded by staff for that session only; it is never
-- inferred from account logins. The same person keeps separate rows (and attendance) per hearing.
CREATE TABLE case_hearing_participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hearing_id BIGINT UNSIGNED NOT NULL,
    case_person_id BIGINT UNSIGNED NULL,
    participant_name VARCHAR(180) NULL,               -- for additional participants not in case_persons
    participant_role ENUM('complainant', 'respondent', 'witness', 'representative', 'other') NOT NULL,
    resident_id BIGINT UNSIGNED NULL,
    attendance_status ENUM('not_recorded', 'present', 'absent', 'excused') NOT NULL DEFAULT 'not_recorded',
    attendance_recorded_at DATETIME NULL,
    attendance_recorded_by BIGINT UNSIGNED NULL,
    attendance_notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hearing_participant_person (hearing_id, case_person_id),   -- NULL case_person_id rows are not constrained
    KEY idx_hearing_participants_attendance (hearing_id, attendance_status),
    KEY idx_hearing_participants_person (case_person_id),
    KEY idx_hearing_participants_resident (resident_id),
    CONSTRAINT fk_hearing_participants_hearing FOREIGN KEY (hearing_id) REFERENCES case_hearings (id) ON DELETE RESTRICT,
    CONSTRAINT fk_hearing_participants_person FOREIGN KEY (case_person_id) REFERENCES case_persons (id) ON DELETE RESTRICT,
    CONSTRAINT fk_hearing_participants_resident FOREIGN KEY (resident_id) REFERENCES residents (id) ON DELETE SET NULL,
    CONSTRAINT fk_hearing_participants_recorded_by FOREIGN KEY (attendance_recorded_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_hearing_participants_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT ck_hearing_participants_identity CHECK (case_person_id IS NOT NULL OR participant_name IS NOT NULL),
    CONSTRAINT ck_hearing_participants_recorded CHECK (attendance_status = 'not_recorded' OR attendance_recorded_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 10. Hearing personnel assignments (with history; grants NO access) ---------------------------
-- Removal/replacement ends an assignment (removed_at) instead of deleting it. active_key is 1 while the
-- assignment is active and NULL once removed, so the unique key blocks duplicate ACTIVE assignments of the
-- same person to the same hearing while keeping every past assignment.
CREATE TABLE case_hearing_personnel (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hearing_id BIGINT UNSIGNED NOT NULL,
    personnel_id BIGINT UNSIGNED NOT NULL,
    assignment_role VARCHAR(80) NOT NULL,             -- e.g. presiding officer, secretary (barangay-approved wording)
    assigned_by BIGINT UNSIGNED NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    removed_by BIGINT UNSIGNED NULL,
    removed_at DATETIME NULL,
    removal_reason VARCHAR(255) NULL,
    replaced_by_assignment_id BIGINT UNSIGNED NULL,
    active_key TINYINT AS (IF(removed_at IS NULL, 1, NULL)) PERSISTENT,
    UNIQUE KEY uq_hearing_personnel_active (hearing_id, personnel_id, active_key),
    KEY idx_hearing_personnel_hearing (hearing_id),
    KEY idx_hearing_personnel_conflict (personnel_id, removed_at, hearing_id),
    CONSTRAINT fk_hearing_personnel_hearing FOREIGN KEY (hearing_id) REFERENCES case_hearings (id) ON DELETE RESTRICT,
    CONSTRAINT fk_hearing_personnel_personnel FOREIGN KEY (personnel_id) REFERENCES barangay_personnel (id) ON DELETE RESTRICT,
    CONSTRAINT fk_hearing_personnel_assigned_by FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_hearing_personnel_removed_by FOREIGN KEY (removed_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_hearing_personnel_replaced_by FOREIGN KEY (replaced_by_assignment_id) REFERENCES case_hearing_personnel (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 11. Evidence attachment metadata (files live in a protected private directory, never in the DB) --
-- stored_filename is a generated random name; storage_path is a RELATIVE folder under the private
-- evidence root (no public URLs, no user-supplied path segments). Removal is recorded, not deleted.
CREATE TABLE case_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    complaint_id BIGINT UNSIGNED NULL,
    blotter_id BIGINT UNSIGNED NULL,
    original_filename VARCHAR(255) NOT NULL,          -- display only; never used as a path
    stored_filename VARCHAR(100) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,                  -- verified server-side (finfo), not the browser value
    file_size BIGINT UNSIGNED NOT NULL,
    sha256_checksum CHAR(64) NOT NULL,
    description VARCHAR(255) NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    removed_by BIGINT UNSIGNED NULL,
    removed_at DATETIME NULL,
    removal_reason VARCHAR(255) NULL,
    UNIQUE KEY uq_case_attachments_stored (stored_filename),
    KEY idx_case_attachments_complaint (complaint_id, removed_at),
    KEY idx_case_attachments_blotter (blotter_id, removed_at),
    KEY idx_case_attachments_checksum (sha256_checksum),
    CONSTRAINT fk_case_attachments_complaint FOREIGN KEY (complaint_id) REFERENCES complaint_cases (id) ON DELETE RESTRICT,
    CONSTRAINT fk_case_attachments_blotter FOREIGN KEY (blotter_id) REFERENCES blotter_entries (id) ON DELETE RESTRICT,
    CONSTRAINT fk_case_attachments_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_case_attachments_removed_by FOREIGN KEY (removed_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT ck_case_attachments_one_parent CHECK ((complaint_id IS NULL) <> (blotter_id IS NULL)),
    CONSTRAINT ck_case_attachments_size CHECK (file_size > 0),
    CONSTRAINT ck_case_attachments_path CHECK (storage_path NOT LIKE '%..%' AND storage_path NOT LIKE '/%' AND storage_path NOT LIKE '%:%')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 12. Evidence access log (who viewed/downloaded which attachment, when) -----------------------
CREATE TABLE case_attachment_access_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attachment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    access_type ENUM('view', 'download') NOT NULL,
    ip_address VARCHAR(45) NULL,
    accessed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attachment_access_attachment (attachment_id, accessed_at),
    KEY idx_attachment_access_user (user_id, accessed_at),
    CONSTRAINT fk_attachment_access_attachment FOREIGN KEY (attachment_id) REFERENCES case_attachments (id) ON DELETE RESTRICT,
    CONSTRAINT fk_attachment_access_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- POST-IMPORT VERIFICATION (read-only):
--   SHOW CREATE TABLE complaint_cases;
--   SELECT COUNT(*) FROM complaint_cases;          -- unchanged
--   SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (...);  -- 11 rows
--   SELECT CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE();
