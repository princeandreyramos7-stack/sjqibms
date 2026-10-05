-- ============================================================================================
-- SJQIBMS — Family members added by residents (Resident Portal → My Household)
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP. (Approved by the owner 2026-10-02.)
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Creates ONE new table; no existing table or row is changed.
--   household_member_requests: a signed-in, active resident asks to add a household member who has no account of
--   their own (children, or elders without a phone). The member is saved as a Pending resident profile (resident_id)
--   and the request waits for the Secretary or the System Administrator. Approving makes the profile Active and adds
--   it to the requester's household; rejecting (with a reason) makes the profile Inactive. Nothing is deleted.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
--     AND TABLE_NAME = 'household_member_requests';   -- expected: 0
-- ============================================================================================

CREATE TABLE household_member_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requested_by BIGINT UNSIGNED NOT NULL,
    resident_id BIGINT UNSIGNED NULL,
    relationship_to_requester VARCHAR(50) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    decided_by BIGINT UNSIGNED NULL,
    decided_at DATETIME NULL,
    review_notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hmr_requester (requested_by, status),
    KEY idx_hmr_status (status, created_at),
    CONSTRAINT fk_hmr_requester FOREIGN KEY (requested_by) REFERENCES users (id),
    CONSTRAINT fk_hmr_resident FOREIGN KEY (resident_id) REFERENCES residents (id) ON DELETE SET NULL,
    CONSTRAINT fk_hmr_decider FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
