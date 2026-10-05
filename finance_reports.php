<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
finance_require('reports');
$connection = db();
$ready = finance_ready($connection);

// Reports: choose a month for the Monthly Financial Report (print / PDF / Excel). The filtered transaction list is
// exported from the Transactions tab.
$month = preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', (string) ($_GET['month'] ?? ''), $m) && (string) $_GET['month'] <= date('Y-m') ? (string) $_GET['month'] : date('Y-m');
$page_title = 'Financial Reports'; $active_page = 'finance';
$page_styles = ['assets/css/finance.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading"><div><h1>Financial Management</h1><p>Monthly Financial Report and exports.</p></div></div>
    <?= finance_nav('reports') ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Financial Management needs its database tables first. No records have been changed.</div>
    <?php else: ?>
        <section class="dashboard-panel resident-form-panel">
            <h2 class="fin-section-title">Monthly Financial Report</h2>
            <p class="fin-hint">Collections and disbursements grouped by category, with totals and the beginning and ending balance of the month. The header and signature lines are edited in templates/finance/report_settings.php.</p>
            <form class="resident-filters fin-filters" method="get" action="finance_report.php" target="_blank">
                <div class="resident-filter-group"><label class="activity-filter-label" for="report-month">Month</label><input class="activity-filter-input" type="month" id="report-month" name="month" value="<?= e($month) ?>" max="<?= e(date('Y-m')) ?>" required></div>
                <div class="activity-filter-actions">
                    <button class="btn btn-primary btn-sm" type="submit">Open Report</button>
                    <button class="btn btn-outline-secondary btn-sm" type="submit" name="print" value="1">Export PDF</button>
                    <button class="btn btn-outline-secondary btn-sm" type="submit" formaction="finance_report_export.php" formtarget="_self">Export Excel</button>
                </div>
            </form>
            <h2 class="fin-section-title">Transaction List</h2>
            <p class="fin-hint">Use <strong>Export Excel</strong> or <strong>Export PDF</strong> above the list on the <a class="activity-detail-link" href="finance.php">Transactions</a> tab; the export follows the tab, search, filters and sort chosen there.</p>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
