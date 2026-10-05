<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/reports.php';
require_once __DIR__ . '/includes/live_search.php';
require_auth();
// One report page for every implemented category. Authorization is checked here on every request (full page and
// live preview alike); unavailable or unauthorized categories answer "not found" so nothing is disclosed.
$category = (string) ($_GET['category'] ?? '');
if (!reports_can_generate($category)) { http_response_code(404); exit('Report not found.'); }
$connection = db();
complaints_require_schema($connection);
['filters' => $filters, 'errors' => $filter_errors] = reports_validate_filters($connection, $category, $_GET);
$report = reports_build($connection, $category, $filters);
$selection = $filters['type'] === 'individual' ? reports_selection($connection, $category, $filters) : null;
$state = array_filter(['category' => $category, 'type' => $filters['type'], 'from' => $filters['from'], 'to' => $filters['to'], 'status' => $filters['status'], 'group' => $filters['group'], 'q' => $filters['q']], static fn ($v): bool => $v !== '' && $v !== null);
$url = static fn (array $extra): string => 'report.php?' . http_build_query(array_merge($state, $extra));

// Trend and breakdown charts (same filters as the document; shown on screen only, above the unchanged document).
try { $analytics = $filters['type'] !== 'individual' ? reports_analytics($connection, $category, $filters) : null; } catch (Throwable) { $analytics = null; }

$render_results = static function () use ($report, $selection, $filters, $filter_errors, $url, $analytics): void { ?>
    <?php foreach ($filter_errors as $message): ?><div class="dashboard-status warning report-screen" role="alert"><?= e($message) ?></div><?php endforeach; ?>
    <?php if ($analytics !== null): ?>
        <section class="dashboard-panel rpa-confidential report-screen" aria-label="Trend and breakdown">
            <?php if ($analytics['total'] === 0): ?><div class="dashboard-empty-state">No data for the selected filters.</div><?php else: ?>
                <div class="rpa-charts"><?php foreach ($analytics['charts'] as $chart): ?><?= report_chart_html($chart) ?><?php endforeach; ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if ($selection !== null): ?>
        <section class="report-selection report-screen" aria-label="Select a record">
            <h2>Select a record <span class="activity-detail-muted">(<?= e((string) $selection['total']) ?> matching)</span></h2>
            <?php if ($selection['rows'] === []): ?><p class="resident-static">No records match the selected filters.</p><?php else: ?>
                <ul class="report-pick-list">
                    <?php foreach ($selection['rows'] as $row): ?>
                        <li class="<?= (int) $filters['id'] === (int) $row['id'] ? 'is-selected' : '' ?>"><a href="<?= e($url(['id' => $row['id'], 'page' => $selection['page'] > 1 ? $selection['page'] : null])) ?>" data-live-page><strong><?= e($row['reference']) ?></strong><span><?= e((string) $row['title']) ?> · <?= e(complaints_format_datetime($row['listed_at'])) ?></span></a><?= complaints_badge(['complaints' => 'complaint', 'blotter' => 'blotter', 'hearings' => 'hearing'][$report['category']], $row['status']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($selection['pages'] > 1): ?>
                    <nav class="activity-pagination" aria-label="Record pages">
                        <?php if ($selection['page'] > 1): ?><a class="activity-page-btn" href="<?= e($url(['page' => $selection['page'] - 1, 'id' => $filters['id']])) ?>" data-live-page>&larr; Previous</a><?php endif; ?>
                        <span class="activity-page-info">Page <?= e((string) $selection['page']) ?> of <?= e((string) $selection['pages']) ?></span>
                        <?php if ($selection['page'] < $selection['pages']): ?><a class="activity-page-btn" href="<?= e($url(['page' => $selection['page'] + 1, 'id' => $filters['id']])) ?>" data-live-page>Next &rarr;</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <p class="report-summary-line report-screen"><strong><?= e($report['title']) ?></strong> · <?= e($report['period']) ?><?= $report['type'] !== 'individual' ? ' · ' . e((string) $report['record_count']) . ' matching record' . ($report['record_count'] === 1 ? '' : 's') : ($report['reference'] ? ' · ' . e($report['reference']) : '') ?></p>
    <div class="document-viewer report-viewer" data-doc-viewer>
        <div class="document-viewer-stage report-stage" data-doc-stage><?= reports_render_document($report) ?></div>
    </div>
<?php };
if (live_search_is_request()) live_search_respond($render_results);

// Access logging for confidential detailed/individual reports (full page loads only; reference and period only).
if ($report['type'] !== 'summary' && !($report['type'] === 'individual' && $report['reference'] === null)) {
    complaints_audit($connection, 0, 'case_report_generated', array_filter(['category' => $category, 'type' => $report['type'], 'reference' => $report['reference'], 'from' => $filters['from'], 'to' => $filters['to']]));
}
$category_label = reports_categories()[$category]['label'];
$page_title = $category_label; $active_page = 'reports';
$page_styles = ['assets/css/report.css', 'assets/css/report_analytics.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content report-page">
    <div class="report-screen">
        <a class="announcement-back" href="reports.php"><span aria-hidden="true">&larr;</span> Back to Reports</a>
        <div class="page-heading report-heading">
            <div><h1><?= e($category_label) ?></h1><p><span class="case-confidential">Confidential</span> Secretary and Super Admin only. Generated from actual records.</p></div>
            <div class="doc-action-group is-large report-actions">
                <button class="btn doc-action-btn is-primary" type="button" data-print-report>Print A4</button>
                <button class="btn doc-action-btn is-view" type="button" disabled aria-describedby="pdf-status">Download PDF</button>
            </div>
        </div>
        <p class="document-viewer-note" id="pdf-status">Direct PDF download needs an approved PDF engine (not installed). Use Print A4 in the meantime.</p>
        <form class="resident-filters dashboard-panel report-filters" method="get" action="report.php" data-live-search data-live-target="#report-results" data-live-range="#report-from,#report-to">
            <input type="hidden" name="category" value="<?= e($category) ?>">
            <div class="resident-filter-group"><label class="activity-filter-label" for="report-type">Report type</label><select class="activity-filter-select" id="report-type" name="type"><?php foreach (reports_type_labels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="report-from">Start date</label><input class="activity-filter-input" type="date" id="report-from" name="from" value="<?= e($filters['from']) ?>"></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="report-to">End date</label><input class="activity-filter-input" type="date" id="report-to" name="to" value="<?= e($filters['to']) ?>"></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="report-status">Status</label><select class="activity-filter-select" id="report-status" name="status"><option value="">All</option><?php foreach (reports_status_options($category) as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="report-group"><?= e(reports_group_label($category)) ?></label><select class="activity-filter-select" id="report-group" name="group"><option value="">All</option><?php foreach (reports_group_options($connection, $category) as $value => $label): ?><option value="<?= e((string) $value) ?>" <?= $filters['group'] === (string) $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="report-q">Search</label><input class="activity-filter-input" type="search" id="report-q" name="q" value="<?= e($filters['q']) ?>" maxlength="100" placeholder="Reference, subject or type" autocomplete="off" data-live-query></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="report.php?category=<?= e($category) ?>" data-live-reset>Reset Filters</a></div>
        </form>
    </div>
    <div id="report-results" class="live-search-results"><?php $render_results(); ?></div>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<?= report_chart_scripts() ?>
