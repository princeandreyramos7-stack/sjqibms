-- ============================================================================================
-- SJQIBMS — Inventory SAMPLE items (the 12 rows of the former sample page)
-- FOR THE DISPOSABLE TEST DATABASE ONLY (sjqibms_test). DO NOT RUN ON THE LIVE DATABASE:
-- these are made-up records and would appear as real barangay property.
-- Requires 20260927_inventory_foundation.sql (categories and locations are looked up by name).
-- ============================================================================================

INSERT INTO inventory_items (item_code, name, item_type, category_id, location_id, quantity, unit, status, item_condition, reorder_level, expiry_date, remarks)
SELECT s.item_code, s.name, s.item_type, c.id, l.id, s.quantity, s.unit, s.status, s.item_condition, s.reorder_level, s.expiry_date, 'Sample record for testing.'
FROM (
    SELECT 'INV-001' AS item_code, 'Monobloc chairs' AS name, 'equipment' AS item_type, 'Furniture' AS category, 'Community Center' AS location, 150 AS quantity, 'pcs' AS unit, 'available' AS status, 'good' AS item_condition, NULL AS reorder_level, NULL AS expiry_date
    UNION ALL SELECT 'INV-002', 'Folding tables', 'equipment', 'Furniture', 'Community Center', 20, 'pcs', 'available', 'good', NULL, NULL
    UNION ALL SELECT 'INV-003', 'Desktop computer', 'equipment', 'IT Equipment', 'Barangay Hall', 3, 'units', 'in_use', 'good', NULL, NULL
    UNION ALL SELECT 'INV-004', 'Laser printer', 'equipment', 'IT Equipment', 'Barangay Hall', 2, 'units', 'in_use', 'good', NULL, NULL
    UNION ALL SELECT 'INV-005', 'Portable generator', 'equipment', 'Equipment', 'Storage Room', 1, 'unit', 'for_repair', 'poor', NULL, NULL
    UNION ALL SELECT 'INV-006', 'Bond paper (A4)', 'supply', 'Supplies', 'Supply Cabinet', 4, 'reams', 'low_stock', 'good', 5, NULL
    UNION ALL SELECT 'INV-007', 'First aid kits', 'supply', 'Medical', 'Health Center', 12, 'kits', 'available', 'good', 5, DATE_ADD(CURDATE(), INTERVAL 20 DAY)
    UNION ALL SELECT 'INV-008', 'Rescue life vests', 'equipment', 'Disaster Equipment', 'DRRM Storage', 25, 'pcs', 'available', 'good', NULL, NULL
    UNION ALL SELECT 'INV-009', 'Handheld radios', 'equipment', 'Communication', 'Tanod Outpost', 8, 'units', 'in_use', 'good', NULL, NULL
    UNION ALL SELECT 'INV-010', 'Sound system set', 'equipment', 'Equipment', 'Community Center', 1, 'set', 'available', 'good', NULL, NULL
    UNION ALL SELECT 'INV-011', 'Flashlights', 'supply', 'Disaster Equipment', 'DRRM Storage', 6, 'pcs', 'low_stock', 'good', 10, NULL
    UNION ALL SELECT 'INV-012', 'Service motorcycle', 'equipment', 'Vehicle', 'Barangay Hall', 1, 'unit', 'in_use', 'fair', NULL, NULL
) AS s
INNER JOIN inventory_categories c ON c.name = s.category
INNER JOIN inventory_locations l ON l.name = s.location;
