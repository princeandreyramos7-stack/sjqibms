-- ============================================================================================
-- SJQIBMS — Disaster Management (BDRRMC), Phase 2: preparedness
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`). Requires 20260929_disaster_records (drr_areas).
--
-- Creates THREE new tables; no existing table is changed.
--   drr_evacuation_centers: name, address, area, capacity (persons), facilities, contact person and number, and status
--     (open / closed / full). Phase 3 marks a center Full automatically when its evacuees reach the capacity.
--   drr_hazard_areas: hazard-prone areas with hazard type, risk level, families at risk and notes.
--   drr_contacts: BDRRMC members (contact_type 'member', with a committee role) and emergency hotlines
--     (contact_type 'hotline', with an agency category).
-- All three are archived (archived_at), never deleted.
-- Vulnerable Residents needs no table: it is read from residents (birth_date, purok, status).
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('drr_evacuation_centers', 'drr_hazard_areas', 'drr_contacts'); -- expected: 0
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'drr_areas'; -- expected: 1
-- ============================================================================================

CREATE TABLE drr_evacuation_centers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NOT NULL,
    area_id INT UNSIGNED NOT NULL,
    capacity INT UNSIGNED NOT NULL,
    has_toilets TINYINT(1) NOT NULL DEFAULT 0,
    has_water TINYINT(1) NOT NULL DEFAULT 0,
    has_electricity TINYINT(1) NOT NULL DEFAULT 0,
    has_kitchen TINYINT(1) NOT NULL DEFAULT 0,
    contact_person VARCHAR(150) NULL,
    contact_number VARCHAR(30) NULL,
    status ENUM('open', 'closed', 'full') NOT NULL DEFAULT 'closed',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_drr_centers_name (name),
    KEY idx_drr_centers_area (area_id),
    KEY idx_drr_centers_status (status, archived_at),
    CONSTRAINT fk_drr_centers_area FOREIGN KEY (area_id) REFERENCES drr_areas (id) ON DELETE RESTRICT,
    CONSTRAINT fk_drr_centers_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_centers_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_centers_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE drr_hazard_areas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    area_id INT UNSIGNED NOT NULL,
    hazard_type ENUM('flood', 'landslide', 'storm_surge', 'fire', 'other') NOT NULL,
    hazard_other VARCHAR(100) NULL,
    risk_level ENUM('low', 'medium', 'high') NOT NULL,
    families_at_risk INT UNSIGNED NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    KEY idx_drr_hazards_area (area_id),
    KEY idx_drr_hazards_type (hazard_type, risk_level),
    KEY idx_drr_hazards_archived (archived_at),
    CONSTRAINT fk_drr_hazards_area FOREIGN KEY (area_id) REFERENCES drr_areas (id) ON DELETE RESTRICT,
    CONSTRAINT fk_drr_hazards_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_hazards_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_hazards_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE drr_contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_type ENUM('member', 'hotline') NOT NULL,
    name VARCHAR(150) NOT NULL,
    member_role ENUM('rescue', 'relief', 'medical', 'communication', 'security', 'other') NULL,
    hotline_category ENUM('mdrrmo', 'bfp', 'pnp', 'hospital', 'other') NULL,
    position VARCHAR(100) NULL,
    contact_number VARCHAR(30) NOT NULL,
    alternate_number VARCHAR(30) NULL,
    notes VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    KEY idx_drr_contacts_type (contact_type, archived_at),
    CONSTRAINT fk_drr_contacts_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_contacts_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_contacts_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
