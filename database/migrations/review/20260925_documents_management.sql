-- ============================================================================================
-- SJQIBMS — Documents Management database update
-- STATUS: REVIEW ONLY. NOT IMPORTED. DO NOT RUN WITHOUT EXPLICIT APPROVAL.
-- Target: MariaDB 10.4+ (live database `sjqibms`). Additive only: no tables or columns are dropped,
-- no existing values are changed except the backfills clearly marked below.
-- Take a full backup (phpMyAdmin Export) before importing.
-- ============================================================================================

-- DEPENDENCY: import 20260926_shared_notifications_account_link.sql first (user_notifications and the
-- one-account-per-resident key, formerly steps 8 and 9 of this file).

START TRANSACTION;

-- 1. Configurable document types ------------------------------------------------------------
CREATE TABLE document_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_normalized VARCHAR(100) NOT NULL,            -- lower-cased, single-spaced; prevents duplicate names
    description TEXT NULL,
    requirements TEXT NULL,                           -- one requirement per line
    fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,   -- 0.00 = no applicable fee; set only from the approved fee schedule
    is_active TINYINT(1) NOT NULL DEFAULT 1,          -- inactive types accept no new requests; history is kept
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_document_types_name (name_normalized),
    KEY idx_document_types_active (is_active),
    CONSTRAINT fk_document_types_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_document_types_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill only the type names already used by existing requests (no invented descriptions or fees).
INSERT INTO document_types (name, name_normalized)
SELECT DISTINCT document_type, LOWER(TRIM(document_type)) FROM document_requests;

-- 2. Versioned templates (approved versions are never edited; editing creates a new draft version) --------
CREATE TABLE document_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_type_id BIGINT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    body MEDIUMTEXT NOT NULL,                         -- sanitized HTML with {{controlled_placeholders}} only
    page_size ENUM('A4', 'Legal', 'Letter') NOT NULL DEFAULT 'A4',
    margin_mm DECIMAL(4,1) NOT NULL DEFAULT 20.0,
    status ENUM('draft', 'for_review', 'approved', 'rejected', 'archived') NOT NULL DEFAULT 'draft',
    is_development_sample TINYINT(1) NOT NULL DEFAULT 0, -- samples can never be approved for issuance (enforced in code)
    rejection_reason VARCHAR(500) NULL,
    content_hash CHAR(64) NULL,                       -- SHA-256 of body at approval
    created_by BIGINT UNSIGNED NULL,
    submitted_by BIGINT UNSIGNED NULL,
    submitted_at DATETIME NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    archived_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_document_template_version (document_type_id, version_no),
    KEY idx_document_templates_status (document_type_id, status),
    CONSTRAINT fk_document_templates_type FOREIGN KEY (document_type_id) REFERENCES document_types(id),
    CONSTRAINT fk_document_templates_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_document_templates_submitted_by FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_document_templates_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_document_templates_archived_by FOREIGN KEY (archived_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Frequently Used (featured) templates: at most four positions, one entry per template ---------
CREATE TABLE document_featured_templates (
    position TINYINT UNSIGNED NOT NULL PRIMARY KEY,   -- 1..4, display order
    document_template_id BIGINT UNSIGNED NOT NULL,
    featured_by BIGINT UNSIGNED NULL,
    featured_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_document_featured_template (document_template_id),
    CONSTRAINT ck_document_featured_position CHECK (position BETWEEN 1 AND 4),
    CONSTRAINT fk_document_featured_template FOREIGN KEY (document_template_id) REFERENCES document_templates(id) ON DELETE CASCADE,
    CONSTRAINT fk_document_featured_by FOREIGN KEY (featured_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Approved signatories (filled only from barangay-approved information) -----------------------
CREATE TABLE document_signatories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    position VARCHAR(150) NOT NULL,
    document_type_id BIGINT UNSIGNED NULL,            -- NULL = applies to every type
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_document_signatories_active (is_active, effective_from, effective_to),
    CONSTRAINT fk_document_signatories_type FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON DELETE SET NULL,
    CONSTRAINT fk_document_signatories_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

-- 5. document_requests: workflow, source, fees (ALTER statements auto-commit in MariaDB) ----------
-- Status values are APPENDED so existing values keep their stored positions; existing rows are unchanged.
ALTER TABLE document_requests
    MODIFY status ENUM('pending', 'approved', 'released', 'rejected', 'processing', 'ready_for_release', 'cancelled') NOT NULL DEFAULT 'pending',
    ADD COLUMN document_type_id BIGINT UNSIGNED NULL AFTER document_type,
    ADD COLUMN document_template_id BIGINT UNSIGNED NULL AFTER document_type_id,
    ADD COLUMN request_source ENUM('online', 'staff') NULL AFTER purpose,
    ADD COLUMN requested_by_user_id BIGINT UNSIGNED NULL AFTER request_source,
    ADD COLUMN reviewed_by BIGINT UNSIGNED NULL,
    ADD COLUMN reviewed_at DATETIME NULL,
    ADD COLUMN rejected_by BIGINT UNSIGNED NULL,
    ADD COLUMN rejected_at DATETIME NULL,
    ADD COLUMN rejection_reason VARCHAR(500) NULL,
    ADD COLUMN cancelled_by BIGINT UNSIGNED NULL,
    ADD COLUMN cancelled_at DATETIME NULL,
    ADD COLUMN cancellation_reason VARCHAR(500) NULL,
    ADD COLUMN prepared_by BIGINT UNSIGNED NULL,
    ADD COLUMN prepared_at DATETIME NULL,
    ADD COLUMN signing_confirmed_by BIGINT UNSIGNED NULL,
    ADD COLUMN signing_confirmed_at DATETIME NULL,
    ADD COLUMN ready_by BIGINT UNSIGNED NULL,
    ADD COLUMN ready_at DATETIME NULL,
    ADD COLUMN fee_amount DECIMAL(10,2) NULL,         -- copied from the type when the request is created
    ADD COLUMN payment_status ENUM('not_applicable', 'pending', 'verified', 'exempt') NULL,
    ADD COLUMN payment_reference VARCHAR(100) NULL,   -- a reference alone never marks a payment verified
    ADD COLUMN payment_verified_by BIGINT UNSIGNED NULL,
    ADD COLUMN payment_verified_at DATETIME NULL,
    ADD COLUMN exemption_reason VARCHAR(500) NULL,
    ADD COLUMN exemption_by BIGINT UNSIGNED NULL,
    ADD COLUMN exemption_at DATETIME NULL,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD KEY idx_document_requests_status_date (status, requested_at),
    ADD KEY idx_document_requests_type (document_type_id),
    ADD KEY idx_document_requests_requested_by (requested_by_user_id),
    ADD CONSTRAINT fk_document_requests_type FOREIGN KEY (document_type_id) REFERENCES document_types(id),
    ADD CONSTRAINT fk_document_requests_template FOREIGN KEY (document_template_id) REFERENCES document_templates(id),
    ADD CONSTRAINT fk_document_requests_requested_by FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_document_requests_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_document_requests_rejected_by FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_document_requests_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_document_requests_prepared_by FOREIGN KEY (prepared_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_document_requests_signing_by FOREIGN KEY (signing_confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_document_requests_ready_by FOREIGN KEY (ready_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_document_requests_payment_by FOREIGN KEY (payment_verified_by) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_document_requests_exemption_by FOREIGN KEY (exemption_by) REFERENCES users(id) ON DELETE SET NULL;

-- Backfills for existing rows (derived from existing records only):
UPDATE document_requests d INNER JOIN document_types t ON t.name_normalized = LOWER(TRIM(d.document_type)) SET d.document_type_id = t.id WHERE d.document_type_id IS NULL;
UPDATE document_requests d INNER JOIN audit_logs l ON l.entity_type = 'document' AND l.entity_id = d.id AND l.action = 'document_requested'
    INNER JOIN users u ON u.id = l.user_id
    SET d.requested_by_user_id = u.id, d.request_source = IF(u.role = 'resident', 'online', 'staff')
    WHERE d.request_source IS NULL;
-- Existing requests predate fee configuration: record them explicitly as "no applicable fee".
UPDATE document_requests SET payment_status = 'not_applicable', fee_amount = 0.00 WHERE payment_status IS NULL;

-- 6. Immutable generated document snapshots (preview, print and PDF all render from this) ----------
CREATE TABLE generated_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    document_template_id BIGINT UNSIGNED NOT NULL,
    template_version INT UNSIGNED NOT NULL,
    signatory_id BIGINT UNSIGNED NULL,
    signatory_name VARCHAR(150) NULL,                 -- copied at generation time
    signatory_position VARCHAR(150) NULL,
    rendered_html MEDIUMTEXT NOT NULL,
    content_hash CHAR(64) NOT NULL,
    generated_by BIGINT UNSIGNED NULL,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_generated_documents_request (request_id, generated_at),
    CONSTRAINT fk_generated_documents_request FOREIGN KEY (request_id) REFERENCES document_requests(id),
    CONSTRAINT fk_generated_documents_template FOREIGN KEY (document_template_id) REFERENCES document_templates(id),
    CONSTRAINT fk_generated_documents_signatory FOREIGN KEY (signatory_id) REFERENCES document_signatories(id) ON DELETE SET NULL,
    CONSTRAINT fk_generated_documents_generated_by FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Release record with claimant verification (one per request; required before status 'released') ---
CREATE TABLE document_releases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    claimant_name VARCHAR(150) NOT NULL,
    claimant_type ENUM('requester', 'representative') NOT NULL,
    verification_result ENUM('verified') NOT NULL,    -- only a verified claimant can be released to
    verification_notes VARCHAR(255) NULL,             -- e.g. type of ID presented; no ID image is stored
    authorization_reference VARCHAR(150) NULL,        -- required for representatives (enforced in code)
    released_by BIGINT UNSIGNED NOT NULL,
    released_at DATETIME NOT NULL,
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_document_release_request (request_id),
    CONSTRAINT ck_document_release_authorization CHECK (claimant_type = 'requester' OR authorization_reference IS NOT NULL),
    CONSTRAINT fk_document_releases_request FOREIGN KEY (request_id) REFERENCES document_requests(id),
    CONSTRAINT fk_document_releases_released_by FOREIGN KEY (released_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. and 9. MOVED (2026-09-26): the general user_notifications table and the users.resident_id unique key are
-- now in 20260926_shared_notifications_account_link.sql, shared with Complaints & Blotter. Import that file
-- BEFORE this one. Their design is unchanged.
