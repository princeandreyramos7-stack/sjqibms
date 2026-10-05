-- ============================================================================================
-- SJQIBMS — Inventory: borrowing records and stock movement log (Phase 3)
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Requires 20260927_inventory_foundation.sql. Target: MariaDB 10.4.32.
--
-- Creates two NEW tables; no existing table is changed.
--   inventory_borrow_records: equipment lent out (e.g. chairs and tables lent to residents). An open record has
--     actual_return_date NULL. On return: returned_quantity + missing_quantity = quantity; damaged_quantity is part of
--     the returned quantity.
--   inventory_movements: every change to an item's on-hand quantity or status (added, issued, borrowed, returned,
--     adjusted, repaired, status_changed, archived, restored). quantity_change is the signed change to the on-hand
--     quantity; quantity_after is the on-hand quantity after the change. Rows are never edited or deleted.
--
-- PRE-FLIGHT (read-only):
--   SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
--    AND TABLE_NAME IN ('inventory_borrow_records', 'inventory_movements');   -- expected: no rows
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_items'; -- expected: 1
-- ============================================================================================

CREATE TABLE inventory_borrow_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    borrow_code VARCHAR(20) NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    borrower_name VARCHAR(150) NOT NULL,
    borrower_contact VARCHAR(30) NULL,
    borrower_address VARCHAR(255) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    date_borrowed DATE NOT NULL,
    expected_return_date DATE NOT NULL,
    actual_return_date DATE NULL,
    returned_quantity INT UNSIGNED NULL,
    damaged_quantity INT UNSIGNED NULL,
    missing_quantity INT UNSIGNED NULL,
    return_condition ENUM('good', 'fair', 'poor', 'damaged') NULL,
    remarks TEXT NULL,
    return_remarks TEXT NULL,
    processed_by BIGINT UNSIGNED NULL,
    returned_to BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inventory_borrow_code (borrow_code),
    KEY idx_inventory_borrow_item (item_id),
    KEY idx_inventory_borrow_open (actual_return_date, expected_return_date),
    CONSTRAINT fk_inventory_borrow_item FOREIGN KEY (item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_borrow_processed_by FOREIGN KEY (processed_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_borrow_returned_to FOREIGN KEY (returned_to) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT ck_inventory_borrow_quantity CHECK (quantity > 0),
    CONSTRAINT ck_inventory_borrow_dates CHECK (expected_return_date >= date_borrowed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id BIGINT UNSIGNED NOT NULL,
    movement_type ENUM('added', 'issued', 'borrowed', 'returned', 'adjusted', 'repaired', 'status_changed', 'archived', 'restored') NOT NULL,
    quantity_change INT NOT NULL DEFAULT 0,
    quantity_after INT UNSIGNED NOT NULL,
    status_from VARCHAR(20) NULL,
    status_to VARCHAR(20) NULL,
    reason VARCHAR(255) NULL,
    recipient VARCHAR(150) NULL,
    borrow_id BIGINT UNSIGNED NULL,
    performed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inventory_movements_item (item_id, created_at),
    KEY idx_inventory_movements_type (movement_type),
    CONSTRAINT fk_inventory_movements_item FOREIGN KEY (item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_movements_borrow FOREIGN KEY (borrow_id) REFERENCES inventory_borrow_records (id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_movements_user FOREIGN KEY (performed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
