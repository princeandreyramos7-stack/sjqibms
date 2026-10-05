<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/reports.php';
require_once __DIR__ . '/includes/live_search.php';
require_auth();
if (!can_access_navigation('reports')) { http_response_code(403); exit('Access denied.'); }

// Reports Dashboard: only the categories this role may see are listed (and searched) — server-side. Categories are
// grouped into sections; confidential ones carry a lock badge. A report whose module has no tables yet is shown as
// "Not yet available" (not clickable).
$search = mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100);
$visible = reports_visible_categories();
$categories = $visible;
if ($search !== '') {
    $needle = mb_strtolower($search);
    $categories = array_filter($categories, static fn (array $c): bool => str_contains(mb_strtolower($c['label'] . ' ' . $c['description'] . ' ' . reports_sections()[$c['section']]), $needle));
}
$has_any = $visible !== [];
$lock = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>';

$render_results = static function () use ($categories, $has_any, $search, $lock): void {
    if (!$has_any): ?>
        <div class="dashboard-empty-state">No reports are available for your role yet.</div>
    <?php elseif ($categories === []): ?>
        <div class="dashboard-empty-state">No report matches "<?= e($search) ?>".</div>
    <?php else: foreach (reports_sections() as $section => $section_label):
        $items = array_filter($categories, static fn (array $c): bool => $c['section'] === $section);
        if ($items === []) continue; ?>
        <section class="report-section" aria-labelledby="report-section-<?= e($section) ?>">
            <h2 class="report-section-title" id="report-section-<?= e($section) ?>"><?= e($section_label) ?><?= $section === 'peace' ? ' <span class="report-lock">' . $lock . ' Confidential</span>' : '' ?></h2>
            <div class="report-card-grid">
                <?php foreach ($items as $key => $category):
                    $badge = $category['confidential'] ? '<span class="report-lock" title="Confidential: Secretary and Super Admin only">' . $lock . ' Confidential</span>' : '';
                    $inner = '<span class="report-card-icon" aria-hidden="true">' . icon_svg($category['icon']) . '</span><span class="report-card-text"><strong>' . e($category['label']) . $badge . '</strong><small>' . e($category['description']) . '</small></span>'; ?>
                    <?php if (reports_category_available($category)): ?>
                        <a class="report-card" href="<?= e($category['href']) ?>"><?= $inner ?><span class="report-card-action">View Report <span aria-hidden="true">&rarr;</span></span></a>
                    <?php else: ?>
                        <div class="report-card is-unavailable" aria-disabled="true"><?= $inner ?><span class="report-card-action">Not yet available</span></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; endif;
};
if (live_search_is_request()) live_search_respond($render_results);

$page_title = 'Reports'; $active_page = 'reports';
$page_styles = ['assets/css/report.css', 'assets/css/report_analytics.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Reports</h1><p>Generate and manage official barangay reports.</p></div>
        <?php if ($has_any): ?><div class="resident-detail-actions"><a class="btn btn-primary announcements-new-btn report-analytics-btn" href="analytics.php"><?= icon_svg('chart') ?> Analytics Overview</a></div><?php endif; ?>
    </div>
    <section class="dashboard-panel">
        <form class="resident-filters report-dashboard-search" method="get" action="reports.php" data-live-search data-live-target="#report-categories">
            <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="report-category-search">Search reports</label><input class="activity-filter-input" type="search" id="report-category-search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="e.g. population, hearing, budget" autocomplete="off" data-live-query></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Search</button><a class="btn btn-outline-secondary btn-sm" href="reports.php" data-live-reset>Reset</a></div>
        </form>
        <div id="report-categories" class="live-search-results"><?php $render_results(); ?></div>
    </section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
