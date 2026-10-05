<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
require_once __DIR__ . '/includes/live_search.php';
finance_require('view');
$connection = db();
$ready = finance_ready($connection);

// Cashbook: posted collections and released disbursements in date order with a running balance. The balance carried
// forward is everything posted and released before the period. Pending, approved, rejected and cancelled records are
// never included. Default period: the current month.
$from = finance_valid_date((string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : date('Y-m-01');
$to = finance_valid_date((string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : date('Y-m-t');
if ($from > $to) [$from, $to] = [$to, $from];
$book = $ready ? finance_cashbook($connection, $from, $to) : null;

$render_results = static function () use ($book, $from, $to): void {
    if ($book === null) return; ?>
    <p class="activity-history-meta"><?= e(finance_format_date($from)) ?> to <?= e(finance_format_date($to)) ?> · <?= e((string) count($book['rows'])) ?> entr<?= count($book['rows']) === 1 ? 'y' : 'ies' ?></p>
    <div class="resident-table-wrap">
        <table class="resident-table fin-table fin-cashbook">
            <thead><tr><th scope="col">Date</th><th scope="col">Reference</th><th scope="col">Particulars</th><th scope="col" class="fin-num">Collections</th><th scope="col" class="fin-num">Disbursements</th><th scope="col" class="fin-num">Balance</th></tr></thead>
            <tbody>
                <tr class="fin-carried"><td colspan="5">Balance carried forward (before <?= e(finance_format_date($from)) ?>)</td><td class="fin-num"><?= e(finance_peso($book['opening'])) ?></td></tr>
                <?php foreach ($book['rows'] as $row): ?>
                    <tr>
                        <td class="fin-nowrap"><?= e(finance_format_date($row['book_date'])) ?></td>
                        <td class="resident-name fin-nowrap"><a class="activity-detail-link" href="finance_view.php?id=<?= e((string) $row['id']) ?>"><?= e($row['reference_no']) ?></a></td>
                        <td><?= e($row['description']) ?><span class="fin-sub"><?= e($row['category_name']) ?> · <?= e($row['payor_or_payee']) ?></span></td>
                        <td class="fin-num"><?= $row['type'] === 'income' ? e(finance_peso($row['amount'])) : '' ?></td>
                        <td class="fin-num"><?= $row['type'] === 'expense' ? e(finance_peso($row['amount'])) : '' ?></td>
                        <td class="fin-num<?= finance_cents($row['balance']) < 0 ? ' fin-over' : '' ?>"><?= e(finance_peso($row['balance'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($book['rows'] === []): ?><tr><td colspan="6" class="fin-empty-row">No posted collections or released disbursements in this period.</td></tr><?php endif; ?>
            </tbody>
            <tbody class="fin-total-row"><tr><td colspan="3">Totals for the period</td><td class="fin-num"><?= e(finance_peso($book['total_in'])) ?></td><td class="fin-num"><?= e(finance_peso($book['total_out'])) ?></td><td class="fin-num"><?= e(finance_peso($book['closing'])) ?></td></tr></tbody>
        </table>
    </div>
    <ul class="resident-cards">
        <li class="resident-card"><div class="resident-card-top"><strong>Balance carried forward</strong><span><?= e(finance_peso($book['opening'])) ?></span></div></li>
        <?php foreach ($book['rows'] as $row): ?>
            <li class="resident-card"><div class="resident-card-top"><strong><?= e($row['reference_no']) ?> · <?= $row['type'] === 'income' ? '+' : '−' ?><?= e(finance_peso($row['amount'])) ?></strong><span><?= e(finance_peso($row['balance'])) ?></span></div><p><?= e(finance_format_date($row['book_date'])) ?> · <?= e($row['description']) ?></p></li>
        <?php endforeach; ?>
        <li class="resident-card"><div class="resident-card-top"><strong>Ending balance</strong><span><?= e(finance_peso($book['closing'])) ?></span></div></li>
    </ul>
<?php };
if ($ready && live_search_is_request()) live_search_respond($render_results);

$page_title = 'Cashbook'; $active_page = 'finance';
$page_styles = ['assets/css/finance.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Financial Management</h1><p>Posted collections and released disbursements in date order, with the running fund balance.</p></div>
    </div>
    <?= finance_nav('cashbook') ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Financial Management needs its database tables first. No records have been changed.</div>
    <?php else: ?>
        <section class="dashboard-panel resident-list-panel">
            <form class="resident-filters fin-filters" method="get" action="finance_cashbook.php" data-live-search data-live-target="#fin-cashbook-results" data-live-range="#cash-from,#cash-to">
                <div class="resident-filter-group"><label class="activity-filter-label" for="cash-from">From</label><input class="activity-filter-input" type="date" id="cash-from" name="from" value="<?= e($from) ?>"></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="cash-to">To</label><input class="activity-filter-input" type="date" id="cash-to" name="to" value="<?= e($to) ?>"></div>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="finance_cashbook.php" data-live-reset>This Month</a></div>
            </form>
            <div id="fin-cashbook-results" class="live-search-results"><?php $render_results(); ?></div>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
