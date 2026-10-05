<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
finance_require('reports');
$connection = db();
if (!finance_ready($connection)) { flash('finance_error', 'Financial Management needs its database tables first.'); redirect('finance.php'); }

// Printable Monthly Financial Report (A4): barangay header, period, collections and disbursements grouped by category,
// totals, beginning and ending balance, and signature lines. Only posted collections and released disbursements count.
// "Print / Save as PDF" uses the browser's print dialog; ?print=1 opens it automatically (Export PDF).
$month = preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', (string) ($_GET['month'] ?? ''), $m) && (string) $_GET['month'] <= date('Y-m') ? (string) $_GET['month'] : date('Y-m');
[$year, $month_no] = array_map('intval', explode('-', $month));
$report = finance_monthly_report($connection, $year, $month_no);
$settings = finance_report_settings();
$entries_in = [];
foreach ($report['collection_entries'] as $entry) $entries_in[$entry['category_name']][] = $entry;
$entries_out = [];
foreach ($report['disbursement_entries'] as $entry) $entries_out[$entry['category_name']][] = $entry;
finance_audit($connection, 0, 'finance_report_generated', ['name' => 'Monthly Financial Report ' . $report['label']]);
$auto_print = ($_GET['print'] ?? '') === '1';
$section = static function (string $title, array $groups, array $entries, string $total, bool $details): void { ?>
    <h2 class="fnp-heading"><?= e($title) ?></h2>
    <?php if ($groups === []): ?><p class="fnp-none">None this month.</p><?php else: ?>
        <table class="fnp-table">
            <thead><tr><th>Category / particulars</th><th>Reference</th><th>Date</th><th class="fnp-num">Amount</th></tr></thead>
            <?php foreach ($groups as $group): ?>
                <tbody>
                    <tr class="fnp-group"><th colspan="3"><?= e($group['name']) ?> <span>(<?= e((string) $group['entries']) ?> entr<?= (int) $group['entries'] === 1 ? 'y' : 'ies' ?>)</span></th><th class="fnp-num"><?= e(finance_peso($group['total'])) ?></th></tr>
                    <?php if ($details): foreach ($entries[$group['name']] ?? [] as $entry): ?>
                        <tr class="fnp-detail"><td><?= e($entry['description']) ?> — <?= e($entry['payor_or_payee']) ?></td><td class="nowrap"><?= e($entry['reference_no']) ?></td><td class="nowrap"><?= e(finance_format_date($entry['book_date'])) ?></td><td class="fnp-num"><?= e(finance_peso($entry['amount'])) ?></td></tr>
                    <?php endforeach; endif; ?>
                </tbody>
            <?php endforeach; ?>
            <tbody><tr class="fnp-total"><td colspan="3">Total</td><td class="fnp-num"><?= e(finance_peso($total)) ?></td></tr></tbody>
        </table>
    <?php endif;
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Monthly Financial Report <?= e($report['label']) ?> | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/finance_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/finance_print.css')) ?>" rel="stylesheet">
</head>
<body class="fnp-page">
<div class="fnp-toolbar">
    <form class="fnp-filters" method="get" action="finance_report.php">
        <label>Month<input type="month" name="month" value="<?= e($month) ?>" max="<?= e(date('Y-m')) ?>"></label>
        <button type="submit">Show</button>
    </form>
    <div class="fnp-actions">
        <a href="finance_reports.php">&larr; Reports</a>
        <a href="finance_report_export.php?month=<?= e($month) ?>">Export Excel</a>
        <button class="is-primary" type="button" data-print>Print / Save as PDF</button>
    </div>
</div>
<main class="fnp-stage">
    <article class="fnp-sheet">
        <?php require __DIR__ . '/layout/finance_print_header.php'; ?>
        <h1 class="fnp-title"><?= e($settings['monthly_title']) ?></h1>
        <p class="fnp-meta">For the month of <?= e($report['label']) ?> (<?= e(finance_format_date($report['from'])) ?> – <?= e(finance_format_date($report['to'])) ?>) · Prepared <?= e(date('F j, Y g:i A')) ?></p>

        <table class="fnp-table fnp-summary-table">
            <tbody>
                <tr class="fnp-balance"><td>Beginning balance (<?= e(finance_format_date($report['from'])) ?>)</td><td class="fnp-num"><?= e(finance_peso($report['beginning'])) ?></td></tr>
                <tr><td>Add: Total collections</td><td class="fnp-num"><?= e(finance_peso($report['total_collections'])) ?></td></tr>
                <tr><td>Less: Total disbursements</td><td class="fnp-num">(<?= e(finance_peso($report['total_disbursements'])) ?>)</td></tr>
                <tr class="fnp-balance"><td>Ending balance (<?= e(finance_format_date($report['to'])) ?>)</td><td class="fnp-num"><?= e(finance_peso($report['ending'])) ?></td></tr>
            </tbody>
        </table>
        <?php if ($report['opening'] === null): ?><p class="fnp-footnote">No beginning balance has been recorded in the system; balances start from ₱0.00.</p><?php else: ?><p class="fnp-footnote">Includes the beginning balance of <?= e(finance_peso($report['opening']['amount'])) ?> as of <?= e(finance_format_date($report['opening']['as_of_date'])) ?>.</p><?php endif; ?>

        <?php $section('Collections', $report['collections'], $entries_in, $report['total_collections'], (bool) $settings['show_details']); ?>
        <?php $section('Disbursements', $report['disbursements'], $entries_out, $report['total_disbursements'], (bool) $settings['show_details']); ?>
        <p class="fnp-footnote">Collections are posted official receipts and allotments dated in the month; disbursements are vouchers released (paid) in the month. Cancelled, rejected and unreleased records are excluded.</p>

        <section class="fnp-signatures">
            <?php foreach ($settings['signatories'] as $signatory): ?>
                <div class="fnp-signature">
                    <p class="fnp-signature-label"><?= e((string) ($signatory['label'] ?? '')) ?></p>
                    <div class="fnp-signature-line"></div>
                    <p class="fnp-signature-name"><?= e((string) ($signatory['name'] ?? '')) ?: '&nbsp;' ?></p>
                    <p class="fnp-signature-position"><?= e((string) ($signatory['position'] ?? '')) ?: 'Signature over printed name' ?></p>
                </div>
            <?php endforeach; ?>
        </section>
    </article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
<?php if ($auto_print): ?>window.addEventListener('load', () => window.print());<?php endif; ?>
</script>
</body>
</html>
