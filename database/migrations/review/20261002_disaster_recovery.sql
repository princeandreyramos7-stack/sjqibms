-- ============================================================================================
-- SJQIBMS — Disaster Management (BDRRMC), Phase 4: recovery (relief distribution and damage assessment)
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`). Requires the Phase 1–3 disaster migrations and the Inventory
-- migrations (inventory_items, inventory_movements).
--
-- Creates THREE new tables; no existing table is changed.
--   drr_relief_distributions: relief given to one family (household, or a resident alone) for an incident.
--     reference_no is RLF-YYYY-NNNN. Cancelling an entry made by mistake archives it and returns the items to stock.
--   drr_relief_items: the Inventory supplies in each distribution. Stock is deducted from inventory_items and logged
--     in inventory_movements as 'issued' (existing movement type) by the application, in the same transaction.
--   drr_damage_assessments: damage per incident and area (houses partially / totally damaged, roads, bridges, crops,
--     public facilities, notes, optional photo). One current assessment per incident and area (application rule).
--     Photos are stored privately in storage/disaster/ (served only through disaster_photo.php).
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('drr_relief_distributions', 'drr_relief_items', 'drr_damage_assessments'); -- expected: 0
-- ============================================================================================

CREATE TABLE drr_relief_distributions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(20) NOT NULL,
    incident_id BIGINT UNSIGNED NOT NULL,
    household_id BIGINT UNSIGNED NULL,
    resident_id BIGINT UNSIGNED NULL,
    received_by VARCHAR(150) NOT NULL,
    distributed_on DATE NOT NULL,
    remarks VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    archive_reason VARCHAR(255) NULL,
    UNIQUE KEY uq_drr_relief_reference (reference_no),
    KEY idx_drr_relief_incident (incident_id, archived_at),
    KEY idx_drr_relief_household (household_id),
    KEY idx_drr_relief_resident (resident_id),
    KEY idx_drr_relief_date (distributed_on),
    CONSTRAINT fk_drr_relief_incident FOREIGN KEY (incident_id) REFERENCES drr_records (id) ON DELETE RESTRICT,
    CONSTRAINT fk_drr_relief_household FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE RESTRICT,
    CONSTRAINT fk_drr_relief_resident FOREIGN KEY (resident_id) REFERENCES residents (id) ON DELETE RESTRICT,
    CONSTRAINT fk_drr_relief_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_relief_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_drr_relief_family CHECK ((household_id IS NULL) <> (resident_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE drr_relief_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    distribution_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit VARCHAR(30) NOT NULL,
    UNIQUE KEY uq_drr_relief_items (distribution_id, item_id),
    KEY idx_drr_relief_items_item (item_id),
    CONSTRAINT fk_drr_relief_items_distribution FOREIGN KEY (distribution_id) REFERENCES drr_relief_distributions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_drr_relief_items_item FOREIGN KEY (item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE drr_damage_assessments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    incident_id BIGINT UNSIGNED NOT NULL,
    area_id INT UNSIGNED NOT NULL,
    assessed_on DATE NOT NULL,
    houses_partial INT UNSIGNED NOT NULL DEFAULT 0,
    houses_total INT UNSIGNED NOT NULL DEFAULT 0,
    roads VARCHAR(255) NULL,
    bridges VARCHAR(255) NULL,
    crops VARCHAR(255) NULL,
    public_facilities VARCHAR(255) NULL,
    notes TEXT NULL,
    photo_path VARCHAR(100) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    KEY idx_drr_damage_incident (incident_id, archived_at),
    KEY idx_drr_damage_area (area_id),
    CONSTRAINT fk_drr_damage_incident FOREIGN KEY (incident_id) REFERENCES drr_records (id) ON DELETE RESTRICT,
    CONSTRAINT fk_drr_damage_area FOREIGN KEY (area_id) REFERENCES drr_areas (id) ON DELETE RESTRICT,
    CONSTRAINT fk_drr_damage_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_damage_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_drr_damage_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
