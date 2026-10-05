-- ============================================================================================
-- SJQIBMS — Relief & Assistance, complete (all relief and assistance in one module, including disaster relief)
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`). Requires 20261008_relief_assistance (both tables are empty on
-- live when this was written), households, drr_records, finance_transactions and inventory_movements.
--
-- ADDITIVE ONLY: no table or column is dropped and no existing value is removed.
-- assistance_distributions:
--   ref_year / ref_seq        safe per-year sequence for AST-YYYY-NNNN (UNIQUE; same pattern as Financial Management)
--   beneficiary_type          resident or household; resident_id becomes NULL-able and household_id is added
--                             (exactly one of the two is set — CHECK)
--   priority_groups           snapshot of the beneficiary's groups on the day it was recorded, from the resident profile
--                             (pwd, solo_parent); '' = All residents (no priority group)
--   incident_id               optional link to a Disaster Management incident (bagyo, baha, lindol ...)
--   assistance_type           ENUM extended with cash, rice, hygiene, medicine (existing values kept)
--   assistance_form           cash or in_kind
--   source / source_details   Barangay Fund, Barangay Inventory, Donation, Municipal / Provincial Government,
--                             National Government Agency, NGO, Other (+ details)
--   finance_transaction_id    optional link to an existing Financial Management disbursement (no new transaction is
--                             ever created by this module)
--   status                    scheduled, given or void (void keeps the record; archived_at / archived_by /
--                             archive_reason hold who, when and why)
--   receiver_relationship, document_no, updated_by
-- assistance_items:
--   inventory_movement_id     the Inventory movement ('issued') created for the line
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
--     AND TABLE_NAME = 'assistance_distributions' AND COLUMN_NAME IN ('household_id', 'incident_id', 'status'); -- expected: 0
-- ============================================================================================

ALTER TABLE assistance_distributions
    ADD COLUMN ref_year SMALLINT UNSIGNED NULL AFTER reference_no,
    ADD COLUMN ref_seq INT UNSIGNED NULL AFTER ref_year,
    ADD COLUMN beneficiary_type ENUM('resident', 'household') NOT NULL DEFAULT 'resident' AFTER ref_seq,
    MODIFY COLUMN resident_id BIGINT UNSIGNED NULL,
    ADD COLUMN household_id BIGINT UNSIGNED NULL AFTER resident_id,
    ADD COLUMN priority_groups SET('pwd', 'solo_parent') NOT NULL DEFAULT '' AFTER household_id,
    ADD COLUMN incident_id BIGINT UNSIGNED NULL AFTER priority_groups,
    MODIFY COLUMN assistance_type ENUM('food', 'medical', 'educational', 'financial', 'burial', 'other', 'cash', 'rice', 'hygiene', 'medicine') NOT NULL,
    ADD COLUMN assistance_form ENUM('cash', 'in_kind') NULL AFTER assistance_type,
    ADD COLUMN source ENUM('barangay_fund', 'barangay_inventory', 'donation', 'municipal_government', 'provincial_government', 'national_agency', 'ngo', 'other') NULL AFTER assistance_form,
    ADD COLUMN source_details VARCHAR(150) NULL AFTER source,
    ADD COLUMN finance_transaction_id BIGINT UNSIGNED NULL AFTER cash_amount,
    ADD COLUMN status ENUM('scheduled', 'given', 'void') NOT NULL DEFAULT 'given' AFTER finance_transaction_id,
    ADD COLUMN receiver_relationship VARCHAR(60) NULL AFTER received_by,
    ADD COLUMN document_no VARCHAR(60) NULL AFTER receiver_relationship,
    ADD COLUMN updated_by BIGINT UNSIGNED NULL AFTER created_by,
    ADD UNIQUE KEY uq_assistance_sequence (ref_year, ref_seq),
    ADD KEY idx_assistance_household (household_id, archived_at),
    ADD KEY idx_assistance_incident (incident_id, status),
    ADD KEY idx_assistance_status (status, given_on),
    ADD KEY idx_assistance_finance (finance_transaction_id),
    ADD CONSTRAINT fk_assistance_household FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_assistance_incident FOREIGN KEY (incident_id) REFERENCES drr_records (id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_assistance_finance FOREIGN KEY (finance_transaction_id) REFERENCES finance_transactions (id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_assistance_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    ADD CONSTRAINT chk_assistance_beneficiary CHECK ((resident_id IS NULL) <> (household_id IS NULL));

ALTER TABLE assistance_items
    ADD COLUMN inventory_movement_id BIGINT UNSIGNED NULL AFTER unit,
    ADD KEY idx_assistance_items_movement (inventory_movement_id),
    ADD CONSTRAINT fk_assistance_items_movement FOREIGN KEY (inventory_movement_id) REFERENCES inventory_movements (id) ON DELETE RESTRICT;
