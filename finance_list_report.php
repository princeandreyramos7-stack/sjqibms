<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
finance_require('reports');
$connection = db();
if (!finance_ready($connection)) { flash('finance_error', 'Financial Management needs its database tables first.'); redirect('finance.php'); }

// Printable (PDF) transaction list with the tab, filters and sort chosen on the list. "Print / Save as PDF" uses the
// browser's print dialog; ?print=1 opens it automatically (the list's Export PDF button). Cancelled and rejected
// records are listed (they are part of the record) but not added to the totals.
$state = finance_list_state($_GET);
$rows = finance_list_rows($connection, $state);
$settings = finance_report_settings();
$summary = finance_filter_summary($connection, $state);
$counted = static fn (array $r): bool => !in_array($r['status'], ['cancelled', 'rejected'], true);
$total_in = array_sum(array_map(static fn (array $r): int => $r['type'] === 'income' && $counted($r) ? finance_cents($r['amount']) : 0, $rows));
$total_out = array_sum(array_map(static fn (array $r): int => $r['type'] === 'expense' && $counted($r) ? finance_cents($r['amount']) : 0, $rows));
finance_audit($connection, 0, 'finance_list_printed', ['name' => 'Transaction list', 'status' => $summary]);
$auto_print = ($_GET['print'] ?? '') === '1';
$back = finance_query_string(array_merge($state, ['page' => 1]));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Financial Transactions | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/finance_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/finance_print.css')) ?>" rel="stylesheet">
</head>
<body class="fnp-page">
<div class="fnp-toolbar">
    <div class="fnp-actions"><span><?= e($summary) ?> · <?= e((string) count($rows)) ?> transaction<?= count($rows) === 1 ? '' : 's' ?></span></div>
    <div class="fnp-actions">
        <a href="finance.php<?= $back !== '' ? '?' . e($back) : '' ?>">&larr; Transactions</a>
        <button class="is-primary" type="button" data-print>Print / Save as PDF</button>
    </div>
</div>
<main class="fnp-stage">
    <article class="fnp-sheet">
        <?php require __DIR__ . '/layout/finance_print_header.php'; ?>
        <h1 class="fnp-title"><?= e($settings['list_title']) ?></h1>
        <p class="fnp-meta">As of <?= e(date('F j, Y g:i A')) ?> · <?= e($summary) ?> · <?= e((string) count($rows)) ?> transaction<?= count($rows) === 1 ? '' : 's' ?></p>
        <?php if ($rows === []): ?>
            <p class="fnp-empty">No transactions match the selected filters.</p>
        <?php else: ?>
            <table class="fnp-table">
                <thead><tr><th>Reference</th><th>Date</th><th>Description</th><th>Category</th><th>Status</th><th class="fnp-num">Collections</th><th class="fnp-num">Disbursements</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="nowrap"><?= e($row['reference_no']) ?></td>
                        <td class="nowrap"><?= e(finance_format_date($row['transaction_date'])) ?></td>
                        <td><?= e($row['description']) ?><span class="fnp-sub"><?= e($row['payor_or_payee']) ?></span></td>
                        <td><?= e($row['category_name']) ?></td>
                        <td><?= e(finance_statuses()[$row['status']] ?? '') ?></td>
                        <td class="fnp-num"><?= $row['type'] === 'income' ? ($counted($row) ? '' : '(') . e(finance_peso($row['amount'])) . ($counted($row) ? '' : ')') : '' ?></td>
                        <td class="fnp-num"><?= $row['type'] === 'expense' ? ($counted($row) ? '' : '(') . e(finance_peso($row['amount'])) . ($counted($row) ? '' : ')') : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tbody><tr class="fnp-total"><td colspan="5">Totals (excluding cancelled and rejected)</td><td class="fnp-num"><?= e(finance_peso(finance_from_cents($total_in))) ?></td><td class="fnp-num"><?= e(finance_peso(finance_from_cents($total_out))) ?></td></tr></tbody>
            </table>
            <p class="fnp-footnote">Amounts in parentheses are cancelled or rejected records, shown for completeness and not added to the totals. Disbursement totals include records not yet released when they are in the list.</p>
        <?php endif; ?>
    </article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
<?php if ($auto_print): ?>window.addEventListener('load', () => window.print());<?php endif; ?>
</script>
</body>
</html>
