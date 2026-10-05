<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/reports.php';
require_once __DIR__ . '/includes/report_modules.php';
require_auth();
if (!can_access_navigation('reports')) { http_response_code(403); exit('Access denied.'); }

// Analytics Overview: key figures and charts for one month or one whole year, from the modules the signed-in user may
// open. Every figure comes from the same statistics functions as the full reports (includes/report_stats.php), and every
// card and chart links to its full report with the same period applied.
$connection = db();
$this_year = (int) date('Y');
$year = filter_var($_GET['year'] ?? $this_year, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => $this_year + 1]]) ?: $this_year;
$month_input = (string) ($_GET['month'] ?? (isset($_GET['year']) ? '' : date('n')));
$month = $month_input === 'all' ? 0 : (filter_var($month_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) ?: (int) date('n'));
if ($month_input === 'all') $month = 0;
$from = $month === 0 ? sprintf('%04d-01-01', $year) : sprintf('%04d-%02d-01', $year, $month);
$to = $month === 0 ? sprintf('%04d-12-31', $year) : date('Y-m-t', strtotime($from));
$f = report_range(['from' => $from, 'to' => $to]);
$period = $month === 0 ? (string) $year : date('F Y', strtotime($from));
$link = static fn (string $key, array $extra = []): string => 'module_report.php?' . http_build_query(['report' => $key, 'from' => $f['from'], 'to' => $f['to']] + $extra);
$peace_link = static fn (string $category): string => 'report.php?' . http_build_query(['category' => $category, 'type' => 'summary', 'from' => $f['from'], 'to' => $f['to']]);
$open = static fn (string $key): bool => report_module_allowed($key) && report_module_ready($connection, $key);

// Build each section the user may see; a failing module is shown as unavailable, never as an error page.
$sections = [];
$try = static function (callable $build) {
    try { return $build(); } catch (Throwable) { return null; }
};
if ($open('residents') || $open('households')) {
    $sections['population'] = $try(static function () use ($connection, $f, $open, $link): array {
        $cards = [];
        $charts = [];
        if ($open('residents')) {
            $s = report_residents_stats($connection, $f + ['purok' => '', 'sex' => '']);
            $cards[] = ['Total Population', report_number(report_residents_population($connection, $f)), 'Active residents', $link('residents')];
            $charts[] = [['id' => 'purok', 'title' => 'Population per Purok', 'type' => 'bar', 'labels' => array_keys($s['by_purok']), 'datasets' => [['label' => 'Residents', 'data' => array_values($s['by_purok'])]], 'x' => 'Purok', 'y' => 'Residents'], $link('residents')];
        }
        if ($open('households')) $cards[] = ['Total Households', report_number(report_households_total($connection, $f)), 'With current active members', $link('households')];
        return ['title' => 'Population', 'cards' => $cards, 'charts' => $charts];
    });
}
if ($open('documents') || $open('health')) {
    $sections['services'] = $try(static function () use ($connection, $f, $open, $link): array {
        $cards = [];
        $charts = [];
        if ($open('documents')) {
            $s = report_documents_stats($connection, $f + ['type' => '']);
            $cards[] = ['Documents Processed', report_number(report_documents_released($connection, $f + ['type' => ''])), 'Released in the period', $link('documents')];
            $charts[] = [['id' => 'documents', 'title' => 'Document Requests Trend', 'type' => 'line', 'labels' => $s['buckets']['labels'], 'datasets' => [['label' => 'Requests', 'data' => $s['trend']]], 'x' => ucfirst($s['buckets']['unit']), 'y' => 'Requests'], $link('documents')];
        }
        if ($open('health')) {
            $s = report_health_stats($connection, $f + ['service' => '', 'purok' => '', 'worker' => '']);
            $total_series = array_fill(0, count($s['buckets']['keys']), 0);
            foreach ($s['trend'] as $series) foreach ($series as $i => $value) $total_series[$i] += $value;
            $cards[] = ['Health Services', report_number($s['total']), 'In the period (excluding cancelled)', $link('health')];
            $charts[] = [['id' => 'health', 'title' => 'Health Services Trend', 'type' => 'line', 'labels' => $s['buckets']['labels'], 'datasets' => [['label' => 'Services', 'data' => $total_series]], 'x' => ucfirst($s['buckets']['unit']), 'y' => 'Services'], $link('health')];
        }
        return ['title' => 'Services', 'cards' => $cards, 'charts' => $charts];
    });
}
if ($open('disaster') || $open('inventory')) {
    $sections['disaster'] = $try(static function () use ($connection, $f, $open, $link): array {
        $cards = [];
        $charts = [];
        if ($open('disaster')) {
            $s = report_disaster_stats($connection, $f + ['type' => '', 'area' => '']);
            $cards[] = ['Active Incidents', report_number(report_disaster_active_incidents($connection)), 'Planned, ongoing or monitoring (current)', $link('disaster')];
            $charts[] = [['id' => 'incidents', 'title' => 'Activities and Incidents by Type', 'type' => 'bar', 'labels' => array_keys($s['by_type']), 'datasets' => [['label' => 'Records', 'data' => array_values($s['by_type'])]], 'x' => 'Type', 'y' => 'Records'], $link('disaster')];
        }
        if ($open('inventory')) $cards[] = ['Low Stock Items', report_number(report_inventory_low_stock($connection)), 'Supplies at or below reorder level (current)', $link('inventory')];
        return ['title' => 'Disaster and Property', 'cards' => $cards, 'charts' => $charts];
    });
}
if ($open('finance')) {
    $sections['finance'] = $try(static function () use ($connection, $f, $link): array {
        $s = report_finance_stats($connection, $f + ['category' => '']);
        return ['title' => 'Finance', 'cards' => [['Collections', report_peso($s['collections']), 'Posted in the period', $link('finance')], ['Disbursements', report_peso($s['disbursements']), 'Released in the period', $link('finance')]],
            'charts' => [[['id' => 'finance', 'title' => 'Collections vs Disbursements', 'type' => 'bar', 'labels' => $s['buckets']['labels'], 'datasets' => [['label' => 'Collections', 'data' => $s['collections_series']], ['label' => 'Disbursements', 'data' => $s['disbursements_series']]], 'x' => ucfirst($s['buckets']['unit']), 'y' => 'Pesos', 'money' => true], $link('finance')]]];
    });
}
if (complaints_can_manage()) {
    $sections['peace'] = $try(static function () use ($connection, $f, $peace_link): array {
        complaints_require_schema($connection);
        $counts = report_peace_counts($connection, $f);
        return ['title' => 'Peace and Order (confidential)', 'cards' => [['Complaints', report_number($counts['complaints']), 'Submitted in the period', $peace_link('complaints')], ['Blotter Entries', report_number($counts['blotter']), 'Recorded in the period', $peace_link('blotter')], ['Hearings', report_number($counts['hearings']), 'Scheduled in the period', $peace_link('hearings')]], 'charts' => []];
    });
}
$has_charts = array_filter($sections, static fn ($s): bool => is_array($s) && $s['charts'] !== []) !== [];
$page_title = 'Analytics Overview'; $active_page = 'reports';
$page_styles = ['assets/css/report.css', 'assets/css/report_analytics.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <a class="announcement-back" href="reports.php"><span aria-hidden="true">&larr;</span> Reports</a>
    <div class="page-heading"><div><h1>Analytics Overview</h1><p>Key figures for <?= e($period) ?> from the modules you can open. Select a card or chart to open its full report for the same period.</p></div></div>
    <section class="dashboard-panel rpa-panel">
        <form class="resident-filters rpa-filters" method="get" action="analytics.php">
            <div class="resident-filter-group"><label class="activity-filter-label" for="rpa-year">Year</label><select class="activity-filter-select" id="rpa-year" name="year"><?php for ($y = $this_year + 1; $y >= $this_year - 6; $y--): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option><?php endfor; ?></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="rpa-month">Month</label><select class="activity-filter-select" id="rpa-month" name="month"><option value="all" <?= $month === 0 ? 'selected' : '' ?>>Whole year</option><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= e(date('F', mktime(0, 0, 0, $m, 1))) ?></option><?php endfor; ?></select></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit">Apply</button><a class="btn btn-outline-secondary btn-sm" href="analytics.php">Reset</a></div>
        </form>
        <p class="rpa-period">Period: <strong><?= e(report_range_label($f['from'], $f['to'])) ?></strong></p>
        <?php if ($sections === []): ?>
            <div class="dashboard-empty-state">No report data is available for your role yet.</div>
        <?php endif; ?>
        <?php foreach ($sections as $key => $section): ?>
            <section class="rpa-overview-section">
                <?php if ($section === null): ?>
                    <div class="dashboard-status warning" role="status">This section could not be loaded right now.</div>
                <?php else: ?>
                    <h2><?= e($section['title']) ?></h2>
                    <div class="rpa-cards" role="list">
                        <?php foreach ($section['cards'] as [$label, $value, $hint, $href]): ?><a class="rpa-card" role="listitem" href="<?= e($href) ?>"><span class="rpa-card-value"><?= e($value) ?></span><span class="rpa-card-label"><?= e($label) ?></span><span class="rpa-card-hint"><?= e($hint) ?></span></a><?php endforeach; ?>
                    </div>
                    <?php if ($section['charts'] !== []): ?>
                        <div class="rpa-charts">
                            <?php foreach ($section['charts'] as [$chart, $href]): ?><div><?= report_chart_html($chart) ?><a class="rpa-chart-link" href="<?= e($href) ?>">Open full report &rarr;</a></div><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<?php if ($has_charts): ?><?= report_chart_scripts() ?><?php endif; ?>
