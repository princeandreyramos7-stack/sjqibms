<?php
// TEMPORARY: read-only view of a module's sample data (see includes/sample_data.php). Expects $selected_module, $module_key, $sample.
require_once __DIR__ . '/../includes/live_search.php';

$columns = $sample['columns'];
$status_index = count($columns) - 1;
$rows = array_map(static fn (array $row): array => array_values($row), $sample['rows']);
$statuses = array_values(array_unique(array_column($rows, $status_index)));
$per_page = 10;
$search = trim(preg_replace('/\s+/u', ' ', (string) ($_GET['q'] ?? '')) ?? '');
if (mb_strlen($search) > 100) $search = mb_substr($search, 0, 100);
$status_filter = (string) ($_GET['status'] ?? '');
if (!in_array($status_filter, $statuses, true)) $status_filter = '';

// Filtering happens before counting and paging, the same as the database-backed lists.
$filtered = array_values(array_filter($rows, static function (array $row) use ($search, $status_filter, $status_index): bool {
    if ($status_filter !== '' && $row[$status_index] !== $status_filter) return false;
    return $search === '' || mb_stripos(implode(' ', $row), $search) !== false;
}));
$total = count($filtered);
$total_pages = max(1, (int) ceil($total / $per_page));
$page = max(1, min($total_pages, (int) ($_GET['page'] ?? 1)));
$records = array_slice($filtered, ($page - 1) * $per_page, $per_page);
$first_shown = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last_shown = min($total, $page * $per_page);
$page_url = static fn (int $target): string => 'module.php?' . http_build_query(array_filter(['module' => $module_key, 'q' => $search, 'status' => $status_filter, 'page' => $target > 1 ? $target : null], static fn ($value): bool => $value !== null && $value !== ''));
$badge = static fn (string $status): string => '<span class="resident-status resident-status-' . e($sample['tones'][$status] ?? 'inactive') . '">' . e($status) . '</span>';

$render_results = static function () use ($records, $columns, $status_index, $total, $first_shown, $last_shown, $total_pages, $page, $page_url, $badge, $search, $status_filter): void {
    if ($records === []): ?>
        <div class="dashboard-empty-state">No matching records found.</div>
    <?php else: ?>
        <p class="activity-history-meta">Showing <?= e((string) $first_shown) ?>–<?= e((string) $last_shown) ?> of <?= e((string) $total) ?> sample record<?= $total === 1 ? '' : 's' ?></p>
        <div class="resident-table-wrap">
            <table class="resident-table">
                <thead><tr><?php foreach ($columns as $column): ?><th scope="col"><?= e($column) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($records as $row): ?>
                    <tr><?php foreach ($row as $index => $value): ?><td class="<?= $index === 0 ? 'resident-name' : '' ?>"><?= $index === $status_index ? $badge($value) : e($value) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($records as $row): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><?= e($row[0]) ?></strong><?= $badge($row[$status_index]) ?></div>
                    <?php for ($index = 1; $index < $status_index; $index++): ?><p><span class="sample-card-label"><?= e($columns[$index]) ?>:</span> <?= e($row[$index]) ?></p><?php endfor; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <nav class="activity-pagination" aria-label="Sample records pagination">
                <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($page_url($page - 1)) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($page_url($page + 1)) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif;
};
if (live_search_is_request()) live_search_respond($render_results);

$page_title = $selected_module['label'];
$active_page = $selected_module['key'];
require __DIR__ . '/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1><?= e($selected_module['label']) ?></h1><p><?= e($sample['intro']) ?></p></div>
    </div>
    <div class="sample-notice" role="note">
        <span class="sample-notice-badge">Sample data</span>
        <span>Temporary records for testing the layout. They are not saved in the database, are not counted on the Dashboard, and cannot be edited.</span>
    </div>
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters" method="get" action="module.php" data-live-search data-live-target="#sample-results">
            <input type="hidden" name="module" value="<?= e($module_key) ?>">
            <div class="resident-filter-group resident-filter-search">
                <label class="activity-filter-label" for="sample-search">Search</label>
                <input class="activity-filter-input" type="search" id="sample-search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Search any field" autocomplete="off" data-live-query>
            </div>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="sample-status"><?= e($columns[$status_index]) ?></label>
                <select class="activity-filter-select" id="sample-status" name="status">
                    <option value="">All</option>
                    <?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= $status_filter === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="activity-filter-actions">
                <button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button>
                <a class="btn btn-outline-secondary btn-sm" href="module.php?module=<?= e($module_key) ?>" data-live-reset>Reset</a>
            </div>
        </form>
        <div id="sample-results" class="live-search-results"><?php $render_results(); ?></div>
    </section>
</main>
<?php require __DIR__ . '/footer.php'; ?></div></div>
