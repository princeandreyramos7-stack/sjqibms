<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
require_once __DIR__ . '/includes/inventory_xlsx.php';   // shared built-in .xlsx writer (read-only use)
finance_require('reports');
$connection = db();
if (!finance_ready($connection)) { flash('finance_error', 'Financial Management needs its database tables first.'); redirect('finance.php'); }

// Excel (.xlsx) export of the currently filtered transaction list (same tab, filters and sort as the list, every page).
$state = finance_list_state($_GET);
$rows = finance_list_rows($connection, $state);
$columns = [
    ['label' => 'Reference', 'width' => 15, 'type' => 'text'],
    ['label' => 'Date', 'width' => 12, 'type' => 'text'],
    ['label' => 'Type', 'width' => 10, 'type' => 'text'],
    ['label' => 'Category', 'width' => 20, 'type' => 'text'],
    ['label' => 'Description', 'width' => 36, 'type' => 'text'],
    ['label' => 'Payor / Payee', 'width' => 26, 'type' => 'text'],
    ['label' => 'Amount (PHP)', 'width' => 16, 'type' => 'money'],
    ['label' => 'Status', 'width' => 16, 'type' => 'text'],
    ['label' => 'Mode of Payment', 'width' => 15, 'type' => 'text'],
    ['label' => 'Check No.', 'width' => 14, 'type' => 'text'],
    ['label' => 'Release Date', 'width' => 12, 'type' => 'text'],
    ['label' => 'Cancel / Reject Reason', 'width' => 30, 'type' => 'text'],
];
$data = array_map(static fn (array $row): array => [
    $row['reference_no'], $row['transaction_date'], finance_types()[$row['type']] ?? '', $row['category_name'], $row['description'], $row['payor_or_payee'],
    finance_cents($row['amount']) / 100, finance_statuses()[$row['status']] ?? '', $row['payment_mode'] ? (finance_payment_modes()[$row['payment_mode']] ?? '') : null, $row['check_no'], $row['release_date'],
    $row['cancel_reason'] ?? $row['reject_reason'],
], $rows);

$content = inventory_xlsx_build('Transactions', $columns, $data);
finance_audit($connection, 0, 'finance_exported', ['name' => 'Transaction list', 'type' => 'xlsx', 'status' => finance_filter_summary($connection, $state)]);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="sjqibms-finance-transactions-' . date('Ymd-His') . '.xlsx"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $content;
