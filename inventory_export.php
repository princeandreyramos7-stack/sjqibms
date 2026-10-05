<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/inventory_xlsx.php';
inventory_require_manage();
$connection = db();
if (!inventory_ready($connection)) { flash('inventory_error', 'Inventory needs its database tables first.'); redirect('inventory.php'); }

// Excel (.xlsx) export of the currently filtered inventory list (same filters and sort as the list, every page).
$state = inventory_list_state($_GET, $connection);
$dates = inventory_report_dates($_GET);
$rows = inventory_report_rows($connection, $state, $dates, 5000, false); // same order as the list
$tracking = inventory_borrowing_ready($connection);

$columns = [
    ['label' => 'Item Code', 'width' => 11, 'type' => 'text'],
    ['label' => 'Item', 'width' => 28, 'type' => 'text'],
    ['label' => 'Type', 'width' => 11, 'type' => 'text'],
    ['label' => 'Category', 'width' => 18, 'type' => 'text'],
    ['label' => $tracking ? 'On Hand' : 'Quantity', 'width' => 10, 'type' => 'number'],
    ['label' => 'Borrowed Out', 'width' => 12, 'type' => 'number'],
    ['label' => 'Unit', 'width' => 8, 'type' => 'text'],
    ['label' => 'Location', 'width' => 18, 'type' => 'text'],
    ['label' => 'Status', 'width' => 13, 'type' => 'text'],
    ['label' => 'Condition', 'width' => 12, 'type' => 'text'],
    ['label' => 'Accountable Person', 'width' => 22, 'type' => 'text'],
    ['label' => 'Serial No.', 'width' => 16, 'type' => 'text'],
    ['label' => 'Property No.', 'width' => 16, 'type' => 'text'],
    ['label' => 'Date Acquired', 'width' => 13, 'type' => 'text'],
    ['label' => 'Unit Cost (PHP)', 'width' => 14, 'type' => 'money'],
    ['label' => 'Total Value (PHP)', 'width' => 16, 'type' => 'money'],
    ['label' => 'Source of Funds', 'width' => 15, 'type' => 'text'],
    ['label' => 'Reorder Level', 'width' => 12, 'type' => 'number'],
    ['label' => 'Expiry Date', 'width' => 12, 'type' => 'text'],
    ['label' => 'Remarks', 'width' => 30, 'type' => 'text'],
];
$data = array_map(static function (array $row): array {
    $owned = (int) $row['quantity'] + (int) $row['borrowed_out'];
    return [
        $row['item_code'], $row['name'], inventory_types()[$row['item_type']] ?? '', $row['category_name'], (int) $row['quantity'], (int) $row['borrowed_out'], $row['unit'], $row['location_name'],
        $row['archived_at'] !== null ? 'Archived' : (inventory_statuses()[$row['status']] ?? $row['status']), inventory_conditions()[$row['item_condition']] ?? '', $row['custodian'], $row['serial_number'], $row['property_number'],
        $row['date_acquired'], $row['unit_cost'], $row['unit_cost'] !== null ? round((float) $row['unit_cost'] * $owned, 2) : null, $row['source_of_funds'] ? (inventory_fund_sources()[$row['source_of_funds']] ?? '') : null,
        $row['reorder_level'], $row['expiry_date'], $row['remarks'],
    ];
}, $rows);

$content = inventory_xlsx_build('Inventory', $columns, $data);
inventory_audit($connection, 0, 'inventory_exported', ['format' => 'xlsx', 'rows' => count($rows), 'filters' => inventory_filter_summary($connection, $state, $dates)]);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="sjqibms-inventory-' . date('Ymd-His') . '.xlsx"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $content;
