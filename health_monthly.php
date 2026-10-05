<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health_programs.php';
$connection = db();
health_programs_require($connection);

// Monthly Health Report: consultations (including prenatal check-ups), vaccines given, children weighed and their
// nutritional status, and referrals, for a month and Purok. Counts only. Print (and Save as PDF) and Excel.
$state = health_monthly_state($connection, $_GET);
$sections = health_monthly_data($connection, $state);
$query = health_monthly_query($state);
$page_title = 'Monthly Health Report'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <section class="dashboard-panel resident-list-panel">
        <a class="announcement-back" href="health.php"><span aria-hidden="true">&larr;</span> Back to Health</a>
        <div class="page-heading">
            <div><h1>Monthly Health Report</h1><p>Summary of the month's health services. Counts only; no names.</p></div>
            <div class="resident-detail-actions"><a class="btn btn-light resident-action-btn" href="health_monthly_print.php?<?= e($query) ?>" target="_blank" rel="noopener">Print</a><a class="btn btn-light resident-action-btn" href="health_monthly_print.php?<?= e($query) ?>&amp;print=1" target="_blank" rel="noopener">Export PDF</a><a class="btn btn-light resident-action-btn" href="health_monthly_export.php?<?= e($query) ?>">Export Excel</a></div>
        </div>
        <form class="health-report-filters" method="get" action="health_monthly.php">
            <div><label class="activity-filter-label" for="mm-month">Month</label><input class="activity-filter-input" type="month" id="mm-month" name="month" max="<?= e(date('Y-m')) ?>" value="<?= e($state['month']) ?>" required></div>
            <div><label class="activity-filter-label" for="mm-purok">Purok</label><select class="activity-filter-select" id="mm-purok" name="purok"><option value="">All<?= residents_purok_scope($connection) !== null ? ' my Puroks' : '' ?></option><?php foreach (health_resident_puroks($connection) as $p): ?><option value="<?= e($p) ?>" <?= $state['purok'] === $p ? 'selected' : '' ?>><?= e(residents_purok_label($p)) ?></option><?php endforeach; ?></select></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit">Show</button></div>
        </form>
        <h2 class="h5 mb-3"><?= e($state['label']) ?><?= $state['purok'] !== '' ? ' · ' . e(residents_purok_label($state['purok'])) : '' ?></h2>
        <div class="health-monthly-grid">
            <?php foreach ($sections as [$title, $rows, $note]): ?>
                <section class="health-monthly-section">
                    <h3><?= e($title) ?></h3>
                    <table class="resident-table health-monthly-table"><tbody>
                        <?php foreach ($rows as $i => [$label, $value]): ?><tr class="<?= $i === 0 ? 'is-total' : '' ?>"><td><?= e($label) ?></td><td class="num"><?= e(number_format((int) $value)) ?></td></tr><?php endforeach; ?>
                    </tbody></table>
                    <p class="activity-history-meta"><?= e($note) ?></p>
                </section>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
