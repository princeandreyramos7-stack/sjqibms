<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
require_once __DIR__ . '/includes/inventory_xlsx.php';   // shared built-in .xlsx writer (read-only use)
finance_require('reports');
$connection = db();
if (!finance_ready($connection)) { flash('finance_error', 'Financial Management needs its database tables first.'); redirect('finance.php'); }

// Excel (.xlsx) version of the Monthly Financial Report: the same figures as finance_report.php, one row per line.
$month = preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', (string) ($_GET['month'] ?? ''), $m) && (string) $_GET['month'] <= date('Y-m') ? (string) $_GET['month'] : date('Y-m');
[$year, $month_no] = array_map('intval', explode('-', $month));
$report = finance_monthly_report($connection, $year, $month_no);
$settings = finance_report_settings();
$amount = static fn ($value): float => finance_cents($value) / 100;
$columns = [
    ['label' => 'Section', 'width' => 16, 'type' => 'text'],
    ['label' => 'Category', 'width' => 22, 'type' => 'text'],
    ['label' => 'Reference', 'width' => 15, 'type' => 'text'],
    ['label' => 'Date', 'width' => 12, 'type' => 'text'],
    ['label' => 'Particulars', 'width' => 44, 'type' => 'text'],
    ['label' => 'Amount (PHP)', 'width' => 16, 'type' => 'money'],
];
$rows = [
    ['Report', $settings['monthly_title'] . ' — ' . $report['label'], null, null, $settings['barangay_name'], null],
    ['Balance', 'Beginning balance', null, $report['from'], null, $amount($report['beginning'])],
];
foreach ([['Collections', $report['collections'], $report['collection_entries'], $report['total_collections']], ['Disbursements', $report['disbursements'], $report['disbursement_entries'], $report['total_disbursements']]] as [$section, $groups, $entries, $total]) {
    foreach ($groups as $group) {
        $rows[] = [$section, $group['name'], null, null, 'Subtotal (' . $group['entries'] . ' entries)', $amount($group['total'])];
        foreach ($entries as $entry) if ($entry['category_name'] === $group['name']) $rows[] = [$section, $group['name'], $entry['reference_no'], $entry['book_date'], $entry['description'] . ' — ' . $entry['payor_or_payee'], $amount($entry['amount'])];
    }
    $rows[] = [$section, 'TOTAL ' . strtoupper($section), null, null, null, $amount($total)];
}
$rows[] = ['Balance', 'Ending balance', null, $report['to'], null, $amount($report['ending'])];

$content = inventory_xlsx_build('Monthly Report', $columns, $rows);
finance_audit($connection, 0, 'finance_exported', ['name' => 'Monthly Financial Report ' . $report['label'], 'type' => 'xlsx']);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="sjqibms-financial-report-' . $month . '.xlsx"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $content;
