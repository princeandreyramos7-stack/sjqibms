<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/report_modules.php';

// Module reports (Residents, Households, Documents, Health, Disaster Management, Inventory, Financial). One page with
// the shared report layout: filters, summary cards, charts, summary tables and a paginated detail table, plus Print,
// Export Excel and Export PDF — all built from the same report structure (includes/report_modules.php).
$key = (string) ($_GET['report'] ?? '');
report_require($key);
$connection = db();
$error = null;
$report = null;
$filters = null;
$definitions = [];
$ready = false;
try {
    $ready = report_module_ready($connection, $key);
    if ($ready) {
        $filters = report_filters($connection, $key, $_GET);
        $report = report_build($connection, $key, $filters, REPORT_PAGE_SIZE, ($filters['page'] - 1) * REPORT_PAGE_SIZE);
        $pages = max(1, (int) ceil($report['detail']['total'] / REPORT_PAGE_SIZE));
        if ($filters['page'] > $pages) {
            $filters['page'] = $pages;
            $report = report_build($connection, $key, $filters, REPORT_PAGE_SIZE, ($pages - 1) * REPORT_PAGE_SIZE);
        }
        $definitions = report_filter_definitions($connection, $key);
    }
} catch (Throwable) {
    $error = 'This report could not be generated right now. Please try again later.';
    $report = null;
}
[$title, $description] = report_titles()[$key];
$url = static fn (string $page, array $changes = []): string => $page . '?' . report_query($key, array_merge((array) $filters, ['page' => 1], $changes));
$page_title = $title; $active_page = $key === 'health' ? 'health' : 'reports';
// The Health Report is opened from the Health module (Health Workers only), so its back link returns there.
[$back_href, $back_label] = $key === 'health' ? ['health.php', 'Health'] : ['reports.php', 'Reports'];
$page_styles = ['assets/css/report.css', 'assets/css/report_analytics.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <a class="announcement-back" href="<?= e($back_href) ?>"><span aria-hidden="true">&larr;</span> <?= e($back_label) ?></a>
    <div class="page-heading">
        <div><h1><?= e($title) ?></h1><p><?= e($description) ?></p></div>
        <?php if ($report !== null): ?>
            <div class="resident-detail-actions rpa-exports">
                <a class="btn btn-light resident-action-btn" href="<?= e($url('module_report_print.php')) ?>" target="_blank" rel="noopener">Print</a>
                <a class="btn btn-light resident-action-btn" href="<?= e($url('module_report_export.php')) ?>">Export Excel</a>
                <a class="btn btn-light resident-action-btn" href="<?= e($url('module_report_print.php', ['print' => '1'])) ?>" target="_blank" rel="noopener">Export PDF</a>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($error !== null): ?>
        <div class="dashboard-status warning" role="alert"><?= e($error) ?></div>
    <?php elseif (!$ready): ?>
        <div class="dashboard-status warning" role="status">This report is not yet available: the module's records are not set up in the database.</div>
    <?php else: ?>
        <section class="dashboard-panel rpa-panel">
            <form class="resident-filters rpa-filters" method="get" action="module_report.php">
                <input type="hidden" name="report" value="<?= e($key) ?>">
                <div class="resident-filter-group"><label class="activity-filter-label" for="rpa-from">From</label><input class="activity-filter-input" type="date" id="rpa-from" name="from" value="<?= e($filters['from']) ?>" required></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="rpa-to">To</label><input class="activity-filter-input" type="date" id="rpa-to" name="to" value="<?= e($filters['to']) ?>" required></div>
                <?php foreach ($definitions as $name => [$label, $options]): ?>
                    <div class="resident-filter-group"><label class="activity-filter-label" for="rpa-<?= e($name) ?>"><?= e($label) ?></label><select class="activity-filter-select" id="rpa-<?= e($name) ?>" name="<?= e($name) ?>"><option value="">All</option><?php foreach ($options as $value => $option): ?><option value="<?= e((string) $value) ?>" <?= (string) $filters[$name] === (string) $value ? 'selected' : '' ?>><?= e((string) $option) ?></option><?php endforeach; ?></select></div>
                <?php endforeach; ?>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit">Apply</button><a class="btn btn-outline-secondary btn-sm" href="module_report.php?report=<?= e($key) ?>">Reset</a></div>
            </form>
            <p class="rpa-period">Showing <strong><?= e(report_range_label($filters['from'], $filters['to'])) ?></strong><?php foreach (array_slice(report_filters_text($connection, $key, $filters), 1) as $text): ?> · <?= e($text) ?><?php endforeach; ?></p>

            <?php if ($report['empty']): ?>
                <div class="dashboard-empty-state rpa-empty">No data for the selected filters.</div>
            <?php else: ?>
                <div class="rpa-cards" role="list">
                    <?php foreach ($report['cards'] as [$label, $value, $hint]): ?>
                        <div class="rpa-card" role="listitem"><span class="rpa-card-value"><?= e($value) ?></span><span class="rpa-card-label"><?= e($label) ?></span><span class="rpa-card-hint"><?= e($hint) ?></span></div>
                    <?php endforeach; ?>
                </div>
                <div class="rpa-charts">
                    <?php foreach ($report['charts'] as $chart): ?><?= report_chart_html($chart) ?><?php endforeach; ?>
                </div>
                <div class="rpa-tables">
                    <?php foreach ($report['tables'] as $table): ?>
                        <section class="rpa-table-card<?= count($table['columns']) > 3 ? ' is-wide' : '' ?>">
                            <h2><?= e($table['title']) ?></h2>
                            <?php if ($table['rows'] === []): ?><p class="rpa-chart-empty">No data for the selected filters.</p><?php else: ?>
                                <div class="rpa-table-wrap"><table class="resident-table rpa-table"><thead><tr><?php foreach ($table['columns'] as $i => $column): ?><th scope="col"<?= $i > 0 ? ' class="rpa-num"' : '' ?>><?= e($column) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($table['rows'] as $row): ?><tr<?= ($row[0] ?? '') === 'Total' ? ' class="rpa-total"' : '' ?>><?php foreach ($row as $i => $cell): ?><td<?= $i > 0 ? ' class="rpa-num"' : '' ?>><?= e((string) $cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($report['notes'] !== []): ?><ul class="rpa-notes"><?php foreach ($report['notes'] as $note): ?><li><?= e($note) ?></li><?php endforeach; ?></ul><?php endif; ?>

            <?php $detail = $report['detail']; $pages = max(1, (int) ceil($detail['total'] / REPORT_PAGE_SIZE)); ?>
            <section class="rpa-detail">
                <h2><?= e($detail['title']) ?> <span class="rpa-count"><?= e(report_number($detail['total'])) ?></span></h2>
                <?php if ($detail['rows'] === []): ?>
                    <div class="dashboard-empty-state">No data for the selected filters.</div>
                <?php else: ?>
                    <div class="resident-table-wrap"><table class="resident-table rpa-table"><thead><tr><?php foreach ($detail['columns'] as $column): ?><th scope="col"><?= e($column) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($detail['rows'] as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= e((string) $cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
                    <ul class="resident-cards rpa-detail-cards"><?php foreach ($detail['rows'] as $row): ?><li class="resident-card"><?php foreach ($row as $i => $cell): ?><p><span class="rpa-card-key"><?= e($detail['columns'][$i]) ?>:</span> <?= e((string) $cell) ?></p><?php endforeach; ?></li><?php endforeach; ?></ul>
                    <?php if ($pages > 1): ?>
                        <nav class="activity-pagination" aria-label="Detail pagination">
                            <?php if ($filters['page'] > 1): ?><a class="activity-page-btn" href="<?= e($url('module_report.php', ['page' => $filters['page'] - 1])) ?>">&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                            <span class="activity-page-info">Page <?= e((string) $filters['page']) ?> of <?= e((string) $pages) ?></span>
                            <?php if ($filters['page'] < $pages): ?><a class="activity-page-btn" href="<?= e($url('module_report.php', ['page' => $filters['page'] + 1])) ?>">Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<?php if ($report !== null && !$report['empty']): ?><?= report_chart_scripts() ?><?php endif; ?>
