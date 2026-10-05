-- ============================================================================================
-- SJQIBMS — Disaster Management (BDRRMC), Phase 1: areas and DRR records
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Creates TWO new tables; no existing table is changed.
--   drr_areas: places a DRR record can refer to. area_type 'all_puroks' (the whole barangay), 'purok' (purok holds
--     the same value stored on residents, '1'–'4') or 'place' (a named place added by staff). Entries are deactivated,
--     never deleted, so existing records always keep their area.
--   drr_records: preparedness activities, incidents, responses, drills and mitigation work. reference_no is
--     DRR-YYYY-NN; ref_year/ref_seq restart at 1 every year (unique per year). The incident fields
--     (affected_families, affected_persons, alert_level, incident_details) are filled only when record_type =
--     'incident'. Records are archived (archived_at), never deleted.
--
-- Starter areas: All Puroks, Purok 1–4 and three common places. Staff can add more on the Areas page.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('drr_areas', 'drr_records'); -- expected: 0
-- ============================================================================================

CREATE TABLE drr_areas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    area_type ENUM('all_puroks', 'purok', 'place') NOT NULL DEFAULT 'place',
    purok VARCHAR(10) NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_drr_areas_name (name),
    KEY idx_drr_areas_active (is_active, sort_order),
    CONSTRAINT fk_drr_areas_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO drr_areas (name, area_type, purok, sort_order) VALUES
    ('All Puroks', 'all_puroks', NULL, 1),
    ('Purok 1', 'purok', '1', 11),
    ('Purok 2', 'purok', '2', 12),
    ('Purok 3', 'purok', '3', 13),
    ('Purok 4', 'purok', '4', 14),
    ('Barangay Hall', 'place', NULL, 100),
    ('Community Center', 'place', NULL, 100),
    ('San Jose Elementary School', 'place', NULL, 100);

CREATE TABLE drr_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(20) NOT NULL,
    ref_year SMALLINT UNSIGNED NOT NULL,
    ref_seq INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    record_type ENUM('preparedness', 'incident', 'response', 'drill', 'mitigation') NOT NULL,
    area_id INT UNSIGNED NOT NULL,
    record_date DATE NOT NULL,
    status ENUM('planned', 'ongoing', 'monitoring', 'completed', 'cancelled') NOT NULL DEFAULT 'planned',
    description TEXT NULL,
    affected_families INT UNSIGNED NULL,
    affected_persons INT UNSIGNED NULL,
    alert_level ENUM('advisory', 'yellow', 'orange', 'red') NULL,
    incident_details TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_drr_records_reference (reference_no),
    UNIQUE KEY uq_drr_records_year_seq (ref_year, ref_seq),
    KEY idx_drr_records_type (record_type),
    KEY idx_drr_records_status (status),
    KEY idx_drr_records_area (area_id),
    KEY idx_drr_records_date (record_date),
    KEY idx_drr_records_archived (archived_at),
    CONSTRAINT fk_drr_records_area FOREIGN KEY (area_id) REFERENCES drr_areas (id) ON DELETE RESTRICT,
    CONSTRAINT fk_drr_records_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_records_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_records_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
