-- ============================================================================================
-- SJQIBMS — Disaster Management (BDRRMC), Phase 3: response (evacuation tracking and disaster alerts)
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`). Requires 20260929_disaster_records and 20260930_disaster_preparedness.
--
-- Creates THREE new tables; no existing table is changed.
--   drr_evacuations: one family checked in at an evacuation center for an incident. The family is a household
--     (household_id) or, for a resident without a household, the resident alone (resident_id). family_members is the
--     number of persons checked in. departed_at is set on check-out. open_family_key is filled only while the family is
--     checked in (not departed, not archived) and is UNIQUE, so the same family can never be checked in at two centers
--     at once. Entries made by mistake are archived, never deleted.
--   drr_alerts: disaster alerts (title, message, warning level, optional incident). Lifted alerts keep their history.
--   drr_alert_areas: the areas each alert covers.
-- Alerts are delivered through the existing user_notifications table (category 'disaster_alert', entity_type
-- 'disaster_alert'); that table is not changed.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('drr_evacuations', 'drr_alerts', 'drr_alert_areas'); -- expected: 0
-- ============================================================================================

CREATE TABLE drr_evacuations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    incident_id BIGINT UNSIGNED NOT NULL,
    center_id INT UNSIGNED NOT NULL,
    household_id BIGINT UNSIGNED NULL,
    resident_id BIGINT UNSIGNED NULL,
    family_members SMALLINT UNSIGNED NOT NULL,
    arrived_at DATETIME NOT NULL,
    departed_at DATETIME NULL,
    remarks VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    open_family_key VARCHAR(30) AS (CASE WHEN departed_at IS NULL AND archived_at IS NULL THEN CONCAT(IF(household_id IS NULL, 'r', 'h'), COALESCE(household_id, resident_id)) END) STORED,
    UNIQUE KEY uq_drr_evacuations_open_family (open_family_key),
    KEY idx_drr_evacuations_incident (incident_id),
    KEY idx_drr_evacuations_center (center_id, departed_at, archived_at),
    KEY idx_drr_evacuations_household (household_id),
    KEY idx_drr_evacuations_resident (resident_id),
    CONSTRAINT fk_drr_evacuations_incident FOREIGN KEY (incident_id) REFERENCES drr_records (id) ON DELETE RESTRICT,
    CONSTRAINT fk_drr_evacuations_center FOREIGN KEY (center_id) REFERENCES drr_evacuation_centers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_drr_evacuations_household FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE RESTRICT,
    CONSTRAINT fk_drr_evacuations_resident FOREIGN KEY (resident_id) REFERENCES residents (id) ON DELETE RESTRICT,
    CONSTRAINT fk_drr_evacuations_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_evacuations_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_evacuations_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_drr_evacuations_family CHECK ((household_id IS NULL) <> (resident_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE drr_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    alert_level ENUM('advisory', 'yellow', 'orange', 'red') NOT NULL,
    incident_id BIGINT UNSIGNED NULL,
    recipients INT UNSIGNED NOT NULL DEFAULT 0,
    lifted_at DATETIME NULL,
    lifted_by BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    KEY idx_drr_alerts_active (archived_at, lifted_at, created_at),
    KEY idx_drr_alerts_incident (incident_id),
    CONSTRAINT fk_drr_alerts_incident FOREIGN KEY (incident_id) REFERENCES drr_records (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_alerts_lifted_by FOREIGN KEY (lifted_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_alerts_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_alerts_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE drr_alert_areas (
    alert_id BIGINT UNSIGNED NOT NULL,
    area_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (alert_id, area_id),
    KEY idx_drr_alert_areas_area (area_id),
    CONSTRAINT fk_drr_alert_areas_alert FOREIGN KEY (alert_id) REFERENCES drr_alerts (id) ON DELETE CASCADE,
    CONSTRAINT fk_drr_alert_areas_area FOREIGN KEY (area_id) REFERENCES drr_areas (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
