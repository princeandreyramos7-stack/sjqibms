<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster.php';
require_once __DIR__ . '/includes/inventory_xlsx.php';   // shared built-in .xlsx writer (read-only use)
disaster_require_view();
$connection = db();
if (!disaster_ready($connection)) { flash('disaster_error', 'Disaster Management needs its database tables first.'); redirect('disaster.php'); }

// Excel (.xlsx) export of the currently filtered DRR records list (same filters and sort as the list, every page).
$state = disaster_list_state($_GET);
$rows = disaster_records_rows($connection, $state);
$columns = [
    ['label' => 'Reference', 'width' => 13, 'type' => 'text'],
    ['label' => 'Activity / Incident', 'width' => 34, 'type' => 'text'],
    ['label' => 'Type', 'width' => 13, 'type' => 'text'],
    ['label' => 'Area', 'width' => 24, 'type' => 'text'],
    ['label' => 'Date', 'width' => 12, 'type' => 'text'],
    ['label' => 'Status', 'width' => 12, 'type' => 'text'],
    ['label' => 'Affected Families', 'width' => 15, 'type' => 'number'],
    ['label' => 'Affected Persons', 'width' => 15, 'type' => 'number'],
    ['label' => 'Warning Level', 'width' => 15, 'type' => 'text'],
    ['label' => 'Description', 'width' => 40, 'type' => 'text'],
    ['label' => 'Incident Details', 'width' => 40, 'type' => 'text'],
];
$data = array_map(static fn (array $row): array => [
    $row['reference_no'], $row['title'], disaster_types()[$row['record_type']] ?? '', $row['area_name'], $row['record_date'],
    $row['archived_at'] !== null ? 'Archived' : (disaster_statuses()[$row['status']] ?? ''),
    $row['affected_families'] !== null ? (int) $row['affected_families'] : null, $row['affected_persons'] !== null ? (int) $row['affected_persons'] : null,
    $row['alert_level'] ? (disaster_alert_levels()[$row['alert_level']] ?? '') : null, $row['description'], $row['incident_details'],
], $rows);

$content = inventory_xlsx_build('DRR Records', $columns, $data);
disaster_audit($connection, 0, 'disaster_records_exported', ['format' => 'xlsx', 'rows' => count($rows), 'filters' => disaster_filter_summary($connection, $state)]);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="sjqibms-drr-records-' . date('Ymd-His') . '.xlsx"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $content;
