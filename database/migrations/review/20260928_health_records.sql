-- ============================================================================================
-- SJQIBMS — Health: barangay health service records
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Creates ONE new table; no existing table is changed.
--   health_records: one service given to (or scheduled for) a registered resident. resident_id links to residents
--   (ON DELETE RESTRICT: a resident with health records cannot be deleted). Records are archived (archived_at), never
--   deleted. health_worker is the name of the health worker who gave the service (chosen from staff or typed).
--   follow_up_date is required by the application when status = 'follow_up'.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'health_records'; -- expected: 0
-- ============================================================================================

CREATE TABLE health_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_no VARCHAR(20) NOT NULL,
    resident_id BIGINT UNSIGNED NOT NULL,
    service ENUM('check_up', 'prenatal', 'postnatal', 'immunization', 'bp_monitoring', 'blood_sugar_monitoring', 'deworming', 'family_planning', 'tb_dots_monitoring', 'other') NOT NULL,
    service_details VARCHAR(150) NULL,
    health_worker VARCHAR(150) NOT NULL,
    service_date DATE NOT NULL,
    status ENUM('scheduled', 'completed', 'follow_up', 'cancelled') NOT NULL DEFAULT 'scheduled',
    follow_up_date DATE NULL,
    remarks TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_health_records_no (record_no),
    KEY idx_health_records_resident (resident_id),
    KEY idx_health_records_date (service_date),
    KEY idx_health_records_follow_up (status, follow_up_date),
    KEY idx_health_records_archived (archived_at),
    CONSTRAINT fk_health_records_resident FOREIGN KEY (resident_id) REFERENCES residents (id) ON DELETE RESTRICT,
    CONSTRAINT fk_health_records_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_health_records_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_health_records_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
