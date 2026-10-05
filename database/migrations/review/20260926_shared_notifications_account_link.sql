-- ============================================================================================
-- SJQIBMS — Shared: general user notifications + one account per resident profile
-- STATUS: REVIEW ONLY. NOT IMPORTED. DO NOT RUN WITHOUT EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Why a separate file: both Documents Management and Complaints & Blotter need these two items.
-- They were moved here from 20260925_documents_management.sql (sections 8 and 9, unchanged in design)
-- so neither module has to import the other's migration. Import THIS file first.
--
-- Additive only: one new table and one new unique key on users.resident_id. No data is inserted or changed.
--
-- PRE-FLIGHT (read-only; both must return 0 before importing):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_notifications';
--   SELECT COUNT(*) FROM (SELECT resident_id FROM users WHERE resident_id IS NOT NULL
--                         GROUP BY resident_id HAVING COUNT(*) > 1) AS duplicates;
-- ============================================================================================

-- 1. General, recipient-specific notifications (documents, complaints, hearings). The announcement bell
-- keeps its own announcement_notifications table. Messages must never contain confidential case details.
-- Event uniqueness: (recipient, entity_type, entity_id, category). Repeatable events use a distinct entity
-- (for example a hearing reschedule references its case_hearing_schedule_history row).
CREATE TABLE user_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id BIGINT UNSIGNED NOT NULL,
    category VARCHAR(40) NOT NULL,                    -- e.g. document_approved, complaint_submitted, hearing_scheduled
    entity_type VARCHAR(40) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    message VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    UNIQUE KEY uq_user_notification_event (recipient_user_id, entity_type, entity_id, category),
    KEY idx_user_notifications_unread (recipient_user_id, read_at, created_at),
    CONSTRAINT fk_user_notifications_recipient FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. One account per resident profile (safe ownership for online document requests and complaints).
-- Requires the duplicate pre-flight check above to return 0.
ALTER TABLE users ADD UNIQUE KEY uq_users_resident (resident_id);
