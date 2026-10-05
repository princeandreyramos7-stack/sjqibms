-- SJQIBMS announcement notification storage
-- Review-only migration. Do not execute or import without explicit approval.
-- Target database: sjqibms
-- Dependencies: existing users and announcements tables.
-- No rows are inserted and no existing tables are altered.

USE `sjqibms`;

CREATE TABLE IF NOT EXISTS announcement_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id BIGINT UNSIGNED NOT NULL,
    announcement_id BIGINT UNSIGNED NOT NULL,
    notification_type VARCHAR(60) NOT NULL DEFAULT 'announcement_published',
    title VARCHAR(200) NOT NULL,
    message VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    CONSTRAINT fk_announcement_notifications_recipient FOREIGN KEY (recipient_user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_announcement_notifications_announcement FOREIGN KEY (announcement_id)
        REFERENCES announcements(id) ON DELETE CASCADE,
    UNIQUE KEY uq_announcement_notification_event (
        recipient_user_id,
        announcement_id,
        notification_type
    ),
    INDEX idx_announcement_notifications_recipient_read (
        recipient_user_id,
        read_at,
        created_at
    ),
    INDEX idx_announcement_notifications_announcement (
        announcement_id,
        created_at
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Future publishing workflow requirements:
-- 1. Create rows only after a draft-to-published transaction succeeds.
-- 2. Resolve recipients server-side from authorized target visibility plus
--    administrative notification eligibility (super_admin and secretary).
-- 3. Deduplicate recipient_user_id values before insert; the unique key also
--    prevents duplicate retries for the same announcement event.
-- 4. Do not create rows for selected_users until recipient relationships exist.
-- 5. Do not backfill historical announcements automatically.
-- 6. Use announcement_view.php?id=<announcement_id> as the protected target.
-- 7. Mark read_at only after the authenticated user passes announcement access
--    checks and the details page is successfully opened.
-- 8. Always constrain reads and read updates by recipient_user_id from the
--    authenticated session; never trust a submitted recipient ID.
--
-- Recovery notes:
-- This is additive DDL and contains no data writes. Back up sjqibms before
-- import. MySQL/MariaDB DDL may implicitly commit. If rollback is approved,
-- restore the backup or review DROP TABLE announcement_notifications separately;
-- do not run an automatic rollback.
