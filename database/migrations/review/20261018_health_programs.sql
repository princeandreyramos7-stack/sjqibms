-- ============================================================================================
-- SJQIBMS — Health module, Phases 3–5: Maternal, Immunization, Nutrition, Chronic Care, Referrals
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Every record points to an existing resident (residents.id); names, birthdates, sex and addresses are never re-typed.
-- Records are archived, never deleted. Only new tables and new NULL-able columns: NO EXISTING ROW IS CHANGED.
--
-- 1. health_pregnancies   — Maternal: one row per pregnancy (LMP, expected delivery date, delivery outcome and place).
--                           Prenatal visits are the existing health_records with service 'prenatal'.
-- 2. health_vaccines      — the vaccine schedule (vaccine, dose, due age). Created EMPTY: filled only from the official
--                           DOH routine immunization schedule, after the owner approves the list.
-- 3. health_immunizations — vaccines given to a resident (one row per dose).
-- 4. health_growth_reference — WHO Child Growth Standards (LMS values). Created EMPTY: filled only from the official
--                           WHO tables, after the owner approves the source. Until then the Health Worker chooses the
--                           nutritional status from the official chart (status_source = 'manual').
-- 5. health_nutrition     — weighings of children 0–59 months (Operation Timbang) and their nutritional status.
-- 6. health_chronic_cases — residents with hypertension, diabetes or TB; maintenance medicines. Readings come from the
--                           existing health_records (BP / blood sugar / TB-DOTS monitoring).
-- 7. health_records (new NULL-able columns): blood sugar reading; referral status, completion date and outcome.
--    Existing referred records keep NULL and are treated as Pending by the application.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN
--     ('health_pregnancies','health_vaccines','health_immunizations','health_growth_reference','health_nutrition','health_chronic_cases');   -- 0
--   SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'health_records'
--     AND COLUMN_NAME IN ('blood_sugar_mgdl','referral_status','referral_completed_on','referral_outcome');                            -- 0
-- ============================================================================================

CREATE TABLE health_pregnancies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id BIGINT UNSIGNED NOT NULL,
    lmp_date DATE NULL,
    expected_delivery_date DATE NULL,
    gravida TINYINT UNSIGNED NULL,
    para TINYINT UNSIGNED NULL,
    status ENUM('active', 'delivered', 'ended') NOT NULL DEFAULT 'active',
    delivery_date DATE NULL,
    delivery_outcome ENUM('live_birth', 'stillbirth', 'miscarriage', 'other') NULL,
    delivery_place ENUM('health_facility', 'home', 'other') NULL,
    delivery_place_name VARCHAR(150) NULL,
    remarks TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    KEY idx_hp_resident (resident_id),
    KEY idx_hp_status_edd (status, expected_delivery_date),
    CONSTRAINT fk_hp_resident FOREIGN KEY (resident_id) REFERENCES residents (id),
    CONSTRAINT fk_hp_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_hp_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_hp_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE health_vaccines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    name VARCHAR(120) NOT NULL,
    dose_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
    due_age_days SMALLINT UNSIGNED NOT NULL,
    late_after_days SMALLINT UNSIGNED NOT NULL DEFAULT 28,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    source_note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_health_vaccines_code_dose (code, dose_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE health_immunizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id BIGINT UNSIGNED NOT NULL,
    vaccine_id INT UNSIGNED NOT NULL,
    date_given DATE NOT NULL,
    given_by VARCHAR(150) NOT NULL,
    lot_no VARCHAR(50) NULL,
    remarks VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    KEY idx_hi_resident (resident_id, vaccine_id),
    KEY idx_hi_date (date_given),
    CONSTRAINT fk_hi_resident FOREIGN KEY (resident_id) REFERENCES residents (id),
    CONSTRAINT fk_hi_vaccine FOREIGN KEY (vaccine_id) REFERENCES health_vaccines (id),
    CONSTRAINT fk_hi_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_hi_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE health_growth_reference (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    indicator ENUM('wfa', 'lhfa', 'wfl', 'wfh') NOT NULL,
    sex ENUM('male', 'female') NOT NULL,
    x_value DECIMAL(6,1) NOT NULL,
    l_value DECIMAL(10,6) NOT NULL,
    m_value DECIMAL(10,6) NOT NULL,
    s_value DECIMAL(10,6) NOT NULL,
    UNIQUE KEY uq_hgr (indicator, sex, x_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE health_nutrition (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id BIGINT UNSIGNED NOT NULL,
    weigh_date DATE NOT NULL,
    age_months SMALLINT UNSIGNED NOT NULL,
    weight_kg DECIMAL(5,2) NOT NULL,
    height_cm DECIMAL(5,1) NOT NULL,
    measured_lying TINYINT(1) NOT NULL DEFAULT 0,
    wfa_status ENUM('severely_underweight', 'underweight', 'normal', 'overweight') NULL,
    hfa_status ENUM('severely_stunted', 'stunted', 'normal', 'tall') NULL,
    wfh_status ENUM('severely_wasted', 'wasted', 'normal', 'overweight', 'obese') NULL,
    status_source ENUM('who_auto', 'manual') NOT NULL DEFAULT 'manual',
    remarks VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    KEY idx_hn_resident (resident_id, weigh_date),
    KEY idx_hn_date (weigh_date),
    CONSTRAINT fk_hn_resident FOREIGN KEY (resident_id) REFERENCES residents (id),
    CONSTRAINT fk_hn_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_hn_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE health_chronic_cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id BIGINT UNSIGNED NOT NULL,
    condition_type ENUM('hypertension', 'diabetes', 'tb') NOT NULL,
    diagnosed_on DATE NULL,
    status ENUM('active', 'completed', 'inactive') NOT NULL DEFAULT 'active',
    maintenance_medicines VARCHAR(500) NULL,
    is_serious TINYINT(1) NOT NULL DEFAULT 0,
    remarks VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    KEY idx_hcc_resident (resident_id, condition_type),
    KEY idx_hcc_status (status),
    CONSTRAINT fk_hcc_resident FOREIGN KEY (resident_id) REFERENCES residents (id),
    CONSTRAINT fk_hcc_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_hcc_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_hcc_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE health_records
    ADD COLUMN blood_sugar_mgdl DECIMAL(5,1) NULL AFTER temperature_c,
    ADD COLUMN referral_status ENUM('pending', 'completed') NULL AFTER referral_reason,
    ADD COLUMN referral_completed_on DATE NULL AFTER referral_status,
    ADD COLUMN referral_outcome VARCHAR(255) NULL AFTER referral_completed_on,
    ADD KEY idx_health_records_referral (referred_rhu, referral_status);
