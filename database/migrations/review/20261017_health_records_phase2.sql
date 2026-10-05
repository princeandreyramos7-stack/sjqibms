-- ============================================================================================
-- SJQIBMS — Health records, Phase 2: vital signs, complaint, diagnosis, findings, medicines, referral
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP. (Approved by the owner 2026-10-03.)
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- 1. health_conditions (new): the list of illnesses / diagnoses Health Workers pick from, so the Morbidity Report can
--    count consultations per illness. Starts empty; Health Workers add entries.
-- 2. health_records (new NULL-able columns; existing rows keep NULL / 0): blood pressure, weight, height, temperature,
--    chief complaint, findings, diagnosis (condition_id), referred to RHU and the reason.
-- 3. health_record_medicines (new): medicines given at a visit, picked from Inventory (Medical). The stock is deducted
--    when the record is saved; the Inventory movement id is kept on the line.
-- No existing row is changed.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('health_conditions', 'health_record_medicines');   -- 0
--   SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'health_records' AND COLUMN_NAME = 'condition_id';   -- 0
-- ============================================================================================

CREATE TABLE health_conditions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_health_conditions_name (name),
    CONSTRAINT fk_health_conditions_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE health_records
    ADD COLUMN bp_systolic SMALLINT UNSIGNED NULL AFTER service_details,
    ADD COLUMN bp_diastolic SMALLINT UNSIGNED NULL AFTER bp_systolic,
    ADD COLUMN weight_kg DECIMAL(5,2) NULL AFTER bp_diastolic,
    ADD COLUMN height_cm DECIMAL(5,1) NULL AFTER weight_kg,
    ADD COLUMN temperature_c DECIMAL(4,1) NULL AFTER height_cm,
    ADD COLUMN chief_complaint VARCHAR(255) NULL AFTER temperature_c,
    ADD COLUMN findings TEXT NULL AFTER chief_complaint,
    ADD COLUMN condition_id INT UNSIGNED NULL AFTER findings,
    ADD COLUMN referred_rhu TINYINT(1) NOT NULL DEFAULT 0 AFTER follow_up_date,
    ADD COLUMN referral_reason VARCHAR(255) NULL AFTER referred_rhu,
    ADD KEY idx_health_records_condition (condition_id, service_date),
    ADD CONSTRAINT fk_health_records_condition FOREIGN KEY (condition_id) REFERENCES health_conditions (id);

CREATE TABLE health_record_medicines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    health_record_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit VARCHAR(30) NULL,
    inventory_movement_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hrm_record (health_record_id),
    KEY idx_hrm_item (item_id),
    CONSTRAINT fk_hrm_record FOREIGN KEY (health_record_id) REFERENCES health_records (id),
    CONSTRAINT fk_hrm_item FOREIGN KEY (item_id) REFERENCES inventory_items (id),
    CONSTRAINT fk_hrm_movement FOREIGN KEY (inventory_movement_id) REFERENCES inventory_movements (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
