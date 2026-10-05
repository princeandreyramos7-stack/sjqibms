-- ============================================================================================
-- SJQIBMS — Relief and Assistance (assistance given to one resident, not tied to a disaster incident)
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`). Requires the Inventory tables (inventory_items,
-- inventory_movements) and residents (with is_pwd / is_solo_parent from 20261007_resident_pwd_solo_parent).
--
-- Creates TWO new tables; no existing table is changed.
--   assistance_distributions: assistance given to ONE resident. reference_no is AST-YYYY-NNNN. Type of assistance
--     (food, medical, educational, financial, burial, other) and a purpose / program. It can be cash, Inventory items,
--     or both (at least one; application rule). The cash amount is recorded here only — it does not create a
--     Financial Management transaction. Cancelling an entry made by mistake archives it (kept on record) and returns
--     the items to Inventory stock.
--   assistance_items: the Inventory supplies in each entry. Stock is deducted from inventory_items and logged in
--     inventory_movements as 'issued' (existing movement type) by the application, in the same transaction.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
--     AND TABLE_NAME IN ('assistance_distributions', 'assistance_items'); -- expected: 0
-- ============================================================================================

CREATE TABLE assistance_distributions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(20) NOT NULL,
    resident_id BIGINT UNSIGNED NOT NULL,
    assistance_type ENUM('food', 'medical', 'educational', 'financial', 'burial', 'other') NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    cash_amount DECIMAL(12,2) NULL,
    received_by VARCHAR(150) NOT NULL,
    given_on DATE NOT NULL,
    remarks VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    archive_reason VARCHAR(255) NULL,
    UNIQUE KEY uq_assistance_reference (reference_no),
    KEY idx_assistance_resident (resident_id, archived_at),
    KEY idx_assistance_date (given_on),
    KEY idx_assistance_type (assistance_type),
    CONSTRAINT fk_assistance_resident FOREIGN KEY (resident_id) REFERENCES residents (id) ON DELETE RESTRICT,
    CONSTRAINT fk_assistance_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_assistance_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_assistance_cash CHECK (cash_amount IS NULL OR cash_amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE assistance_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    distribution_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit VARCHAR(30) NOT NULL,
    UNIQUE KEY uq_assistance_items (distribution_id, item_id),
    KEY idx_assistance_items_item (item_id),
    CONSTRAINT fk_assistance_items_distribution FOREIGN KEY (distribution_id) REFERENCES assistance_distributions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_assistance_items_item FOREIGN KEY (item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
