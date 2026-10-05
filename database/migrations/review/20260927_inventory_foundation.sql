-- ============================================================================================
-- SJQIBMS — Inventory: categories, locations and items (Phase 1)
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Creates three NEW tables; no existing table is changed.
--   inventory_categories / inventory_locations: lists the System Administrator can extend (deactivated, never deleted).
--   inventory_items: barangay property and supplies. Records are archived (archived_at), never deleted.
--     quantity = total quantity owned; quantity and unit are separate fields.
--     status is kept by the application: supplies become 'low_stock' when quantity <= reorder_level.
--     item_condition is used because CONDITION is a reserved word in MariaDB.
--     photo_filename is a generated name inside storage/inventory/ (private; served only by inventory_photo.php).
-- The starter categories and locations below are generic lists taken from the former sample page; no items are inserted.
--
-- PRE-FLIGHT (read-only):
--   SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
--    AND TABLE_NAME IN ('inventory_categories', 'inventory_locations', 'inventory_items');   -- expected: no rows
-- ============================================================================================

CREATE TABLE inventory_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inventory_categories_name (name),
    CONSTRAINT fk_inventory_categories_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT ck_inventory_categories_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inventory_locations_name (name),
    CONSTRAINT fk_inventory_locations_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT ck_inventory_locations_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(20) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    item_type ENUM('equipment', 'supply') NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    unit VARCHAR(30) NOT NULL,
    status ENUM('available', 'in_use', 'borrowed', 'for_repair', 'low_stock', 'unserviceable') NOT NULL DEFAULT 'available',
    item_condition ENUM('good', 'fair', 'poor', 'unserviceable') NOT NULL DEFAULT 'good',
    custodian VARCHAR(150) NULL,
    serial_number VARCHAR(100) NULL,
    property_number VARCHAR(100) NULL,
    date_acquired DATE NULL,
    unit_cost DECIMAL(12, 2) NULL,
    source_of_funds ENUM('barangay_fund', 'donation', 'lgu_grant', 'other') NULL,
    reorder_level INT UNSIGNED NULL,
    expiry_date DATE NULL,
    photo_filename VARCHAR(100) NULL,
    remarks TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_inventory_items_code (item_code),
    KEY idx_inventory_items_type (item_type),
    KEY idx_inventory_items_status (status),
    KEY idx_inventory_items_archived (archived_at),
    KEY idx_inventory_items_name (name),
    CONSTRAINT fk_inventory_items_category FOREIGN KEY (category_id) REFERENCES inventory_categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_items_location FOREIGN KEY (location_id) REFERENCES inventory_locations (id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_items_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_items_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_inventory_items_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT ck_inventory_items_unit_cost CHECK (unit_cost IS NULL OR unit_cost >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Starter lists (generic names; the System Administrator can add, rename or deactivate them).
INSERT INTO inventory_categories (name) VALUES
    ('Furniture'), ('IT Equipment'), ('Equipment'), ('Supplies'), ('Medical'), ('Disaster Equipment'), ('Communication'), ('Vehicle');
INSERT INTO inventory_locations (name) VALUES
    ('Barangay Hall'), ('Community Center'), ('Storage Room'), ('Supply Cabinet'), ('Health Center'), ('DRRM Storage'), ('Tanod Outpost');
