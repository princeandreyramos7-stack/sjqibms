-- SJQIBMS additive dashboard foundation migration
-- Target database: sjqibms
-- Prepared for review only. Do not execute until the live schema has been verified.
--
-- This migration intentionally does not create a database, alter authentication
-- tables, insert records, or modify existing rows.

USE `sjqibms`;

-- Existing tables reused by this migration:
-- users, residents, document_requests, announcements, audit_logs.
-- The existing push_subscriptions table is unrelated to this migration.

CREATE TABLE IF NOT EXISTS households (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_no VARCHAR(50) NOT NULL UNIQUE,
    household_head_resident_id BIGINT UNSIGNED NULL,
    address TEXT NOT NULL,
    purok VARCHAR(80) NOT NULL,
    housing_type VARCHAR(80) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_households_head FOREIGN KEY (household_head_resident_id)
        REFERENCES residents(id) ON DELETE SET NULL,
    INDEX idx_households_purok (purok),
    INDEX idx_households_head (household_head_resident_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- residents.household_no is retained as legacy source data. This migration
-- does not create household rows from it or rewrite resident records. Before
-- household features go live, staff must reconcile distinct legacy values to
-- households.id and create resident_households links in a separately reviewed
-- data migration. New code should use resident_households as the canonical
-- relationship and household_no as a compatibility field until reconciliation.

-- A link table avoids changing existing resident records and supports future
-- household history or a resident belonging to more than one household.
CREATE TABLE IF NOT EXISTS resident_households (
    resident_id BIGINT UNSIGNED NOT NULL,
    household_id BIGINT UNSIGNED NOT NULL,
    relationship_to_head VARCHAR(60) NULL,
    is_primary BOOLEAN NOT NULL DEFAULT TRUE,
    joined_at DATE NULL,
    left_at DATE NULL,
    current_primary_resident_id BIGINT UNSIGNED AS (
        CASE WHEN is_primary = TRUE AND left_at IS NULL THEN resident_id ELSE NULL END
    ) STORED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (resident_id, household_id),
    CONSTRAINT fk_resident_households_resident FOREIGN KEY (resident_id)
        REFERENCES residents(id) ON DELETE CASCADE,
    CONSTRAINT fk_resident_households_household FOREIGN KEY (household_id)
        REFERENCES households(id) ON DELETE CASCADE,
    INDEX idx_resident_households_household (household_id),
    INDEX idx_resident_households_primary (resident_id, is_primary),
    UNIQUE KEY uq_resident_current_primary (current_primary_resident_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS complaint_cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(50) NOT NULL UNIQUE,
    complainant_resident_id BIGINT UNSIGNED NULL,
    respondent_resident_id BIGINT UNSIGNED NULL,
    complainant_name VARCHAR(180) NULL,
    respondent_name VARCHAR(180) NULL,
    subject VARCHAR(255) NOT NULL,
    confidential_details TEXT NULL,
    status ENUM('open', 'under_review', 'for_hearing', 'settled', 'dismissed', 'closed') NOT NULL DEFAULT 'open',
    filed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    hearing_at DATETIME NULL,
    resolved_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_complaints_complainant FOREIGN KEY (complainant_resident_id)
        REFERENCES residents(id) ON DELETE SET NULL,
    CONSTRAINT fk_complaints_respondent FOREIGN KEY (respondent_resident_id)
        REFERENCES residents(id) ON DELETE SET NULL,
    CONSTRAINT fk_complaints_created_by FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_complaints_updated_by FOREIGN KEY (updated_by)
        REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_complaints_status_filed (status, filed_at),
    INDEX idx_complaints_complainant (complainant_resident_id),
    INDEX idx_complaints_respondent (respondent_resident_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS barangay_projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_code VARCHAR(50) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    status ENUM('planned', 'ongoing', 'completed', 'suspended', 'cancelled') NOT NULL DEFAULT 'planned',
    start_date DATE NULL,
    target_end_date DATE NULL,
    completed_at DATE NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_projects_created_by FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_updated_by FOREIGN KEY (updated_by)
        REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_projects_status (status),
    INDEX idx_projects_dates (start_date, target_end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id BIGINT UNSIGNED NOT NULL,
    action ENUM('created', 'updated', 'published', 'unpublished', 'archived') NOT NULL,
    actor_id BIGINT UNSIGNED NULL,
    previous_status ENUM('draft', 'published', 'archived') NULL,
    new_status ENUM('draft', 'published', 'archived') NULL,
    change_summary VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcement_history_announcement FOREIGN KEY (announcement_id)
        REFERENCES announcements(id) ON DELETE CASCADE,
    CONSTRAINT fk_announcement_history_actor FOREIGN KEY (actor_id)
        REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_announcement_history_announcement (announcement_id, created_at),
    INDEX idx_announcement_history_actor (actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stores the submitted application before a resident profile exists. The
-- application is the current workflow record; history is stored separately.
CREATE TABLE IF NOT EXISTS registration_applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_type ENUM('personnel', 'resident') NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    suffix VARCHAR(20) NULL,
    email VARCHAR(190) NULL,
    birth_date DATE NULL,
    sex ENUM('male', 'female', 'other', 'unspecified') NOT NULL DEFAULT 'unspecified',
    civil_status ENUM('single', 'married', 'widowed', 'separated', 'other', 'unspecified') NOT NULL DEFAULT 'unspecified',
    contact_number VARCHAR(30) NULL,
    address TEXT NULL,
    purok VARCHAR(80) NULL,
    household_no VARCHAR(50) NULL,
    user_id BIGINT UNSIGNED NULL,
    resident_id BIGINT UNSIGNED NULL,
    status ENUM('submitted', 'verified', 'awaiting_final_approval', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'submitted',
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_by BIGINT UNSIGNED NULL,
    verified_at DATETIME NULL,
    decided_by BIGINT UNSIGNED NULL,
    decided_at DATETIME NULL,
    review_notes VARCHAR(500) NULL,
    CONSTRAINT fk_registration_application_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_registration_application_resident FOREIGN KEY (resident_id)
        REFERENCES residents(id) ON DELETE SET NULL,
    CONSTRAINT fk_registration_application_verifier FOREIGN KEY (verified_by)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_registration_application_decider FOREIGN KEY (decided_by)
        REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_registration_application_queue (application_type, status, submitted_at),
    INDEX idx_registration_application_user (user_id),
    INDEX idx_registration_application_resident (resident_id),
    INDEX idx_registration_application_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable workflow history for verification, approval, and rejection.
CREATE TABLE IF NOT EXISTS registration_approvals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL,
    action ENUM('verified', 'approved', 'rejected', 'cancelled') NOT NULL,
    from_status ENUM('submitted', 'verified', 'awaiting_final_approval', 'approved', 'rejected', 'cancelled') NULL,
    to_status ENUM('submitted', 'verified', 'awaiting_final_approval', 'approved', 'rejected', 'cancelled') NOT NULL,
    actor_id BIGINT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_registration_approval_application FOREIGN KEY (application_id)
        REFERENCES registration_applications(id) ON DELETE RESTRICT,
    CONSTRAINT fk_registration_approval_actor FOREIGN KEY (actor_id)
        REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_registration_approval_application (application_id, created_at),
    INDEX idx_registration_approval_actor (actor_id),
    INDEX idx_registration_approval_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing structures intentionally reused without alteration:
-- 1. document_requests already supports resident_id, status, requested_at,
--    approved_by, and an index on status for pending-request dashboards.
-- 2. announcements already supports author_id, draft/published/archived status,
--    audience targeting, target_purok, and publication timestamps.
-- 3. audit_logs already supports actor user_id, action, entity_type, entity_id,
--    IP/user-agent metadata, JSON details, and created_at. Future activity
--    queries should apply the caller's role permissions before displaying rows.

-- Registration workflow rules for future transactional implementation:
-- * registration_applications.status is the current source of workflow truth.
-- * registration_approvals is append-only history, not a second current status.
-- * Verification may set an application to verified/awaiting_final_approval and
--   create a pending resident profile in one transaction.
-- * Final approval must atomically set application approved, resident active,
--   and linked user active while recording the staff actor and timestamp.
-- * Rejection leaves an existing resident pending, keeps the application
--   rejected, and never activates the linked user.
-- * Future backend code must enforce Secretary/Super Admin permissions and
--   prevent transitions from approved or rejected back to pending states.

-- Resident status expansion is intentionally kept in a separate migration so
-- it can be reviewed and applied independently after live-schema verification.

-- ROLLBACK / RECOVERY:
-- This migration is additive and contains no DROP, TRUNCATE, DELETE, or UPDATE.
-- Take a verified backup before import. If rollback is approved after import,
-- drop only the newly created tables in reverse dependency order, after checking
-- that no application code or records depend on them:
--
-- DROP TABLE registration_approvals;
-- DROP TABLE registration_applications;
-- DROP TABLE announcement_history;
-- DROP TABLE barangay_projects;
-- DROP TABLE complaint_cases;
-- DROP TABLE resident_households;
-- DROP TABLE households;
--
-- MySQL DDL may implicitly commit, so rollback is not equivalent to a
-- transaction rollback. Restoring the pre-migration backup is the safest
-- recovery path. Do not run the DROP statements automatically.
