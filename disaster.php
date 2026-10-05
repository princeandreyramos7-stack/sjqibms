<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_recovery.php';
require_once __DIR__ . '/includes/live_search.php';
disaster_require_view();
$connection = db();
$can_manage = disaster_can_manage();   // Secretary views only (Health Workers, the Treasurer and residents use Disaster Info)
$ready = disaster_ready($connection);

// DRR records: search, status, type, area, date range, sortable columns and pagination. Every setting lives in the URL
// query string, and filtering, sorting and paging all run in SQL.
$per_page = 10;
$state = disaster_list_state($_GET);
$records = [];
$total = 0;
$page = 1;
$total_pages = 1;
if ($ready) {
    [$where, $params] = disaster_list_where($state);
    $from = ' FROM drr_records d INNER JOIN drr_areas a ON a.id = d.area_id WHERE ' . implode(' AND ', $where);
    $count = $connection->prepare('SELECT COUNT(*)' . $from);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $total_pages = max(1, (int) ceil($total / $per_page));
    $page = max(1, min($total_pages, $state['page']));
    $statement = $connection->prepare('SELECT d.id, d.reference_no, d.title, d.record_type, d.record_date, d.status, d.alert_level, d.archived_at, a.name AS area_name' . $from . ' ORDER BY ' . disaster_sort_sql($state['sort'], $state['dir']) . ' LIMIT :limit OFFSET :offset');
    foreach ($params as $key => $value) $statement->bindValue($key, $value);
    $statement->bindValue('limit', $per_page, PDO::PARAM_INT);
    $statement->bindValue('offset', ($page - 1) * $per_page, PDO::PARAM_INT);
    $statement->execute();
    $records = $statement->fetchAll();
}
$first = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last = min($total, $page * $per_page);
$url = static fn (array $changes = []): string => 'disaster.php' . (($query = disaster_query_string(array_merge($state, ['page' => $page], $changes))) !== '' ? '?' . $query : '');
$return = disaster_query_string(array_merge($state, ['page' => $page]));

$archive_button = static function (array $row) use ($return): string {
    $archived = $row['archived_at'] !== null;
    return '<form method="post" action="disaster_action.php" class="doc-action-form">' . csrf_field()
        . '<input type="hidden" name="id" value="' . e((string) $row['id']) . '"><input type="hidden" name="action" value="' . ($archived ? 'restore' : 'archive') . '"><input type="hidden" name="return" value="' . e($return) . '">'
        . '<button class="btn btn-sm ' . ($archived ? 'btn-outline-primary' : 'btn-outline-danger') . '" type="submit" data-form-confirm="custom" data-dialog-heading="' . ($archived ? 'Restore this record?' : 'Archive this record?') . '" data-dialog-message="' . e($archived ? $row['reference_no'] . ' will appear in the records list again.' : $row['reference_no'] . ' will be hidden from the list. The record is kept and can be restored.') . '" data-dialog-confirm="' . ($archived ? 'Restore' : 'Archive') . '" data-dialog-dismiss="Cancel"' . ($archived ? '' : ' data-dialog-danger="true"') . '>' . ($archived ? 'Restore' : 'Archive') . '</button></form>';
};
// Sortable header: clicking the active column flips the direction; a new column starts ascending (Date starts newest first).
$sort_header = static function (string $key, string $label) use ($state, $url): string {
    $active = $state['sort'] === $key;
    $dir = $active ? ($state['dir'] === 'asc' ? 'desc' : 'asc') : ($key === 'date' ? 'desc' : 'asc');
    $aria = $active ? ($state['dir'] === 'asc' ? 'ascending' : 'descending') : 'none';
    $arrow = $active ? ($state['dir'] === 'asc' ? '▲' : '▼') : '↕';
    return '<th scope="col" aria-sort="' . $aria . '"><a class="drr-sort' . ($active ? ' is-active' : '') . '" href="' . e($url(['sort' => $key, 'dir' => $dir, 'page' => 1])) . '" data-live-page>' . e($label) . ' <span aria-hidden="true">' . $arrow . '</span></a></th>';
};
$status_cell = static fn (array $row): string => $row['archived_at'] !== null ? '<span class="resident-status resident-status-inactive">Archived</span>' : disaster_status_badge($row['status']);
$actions = static fn (array $row): string => '<div class="management-actions drr-actions"><a class="btn btn-sm btn-outline-primary" href="disaster_view.php?id=' . e((string) $row['id']) . '">View</a>' . (!$can_manage ? '' : ($row['archived_at'] === null ? '<a class="btn btn-sm btn-outline-primary" href="disaster_form.php?id=' . e((string) $row['id']) . '">Edit</a>' : '') . $archive_button($row)) . '</div>';

$render_results = static function () use ($records, $total, $first, $last, $total_pages, $page, $state, $url, $sort_header, $status_cell, $actions, $can_manage): void {
    ?>
    <div hidden data-drr-state="<?= e(json_encode(['sort' => $state['sort'], 'dir' => $state['dir']], JSON_THROW_ON_ERROR)) ?>"></div>
    <?php if ($records === []): ?>
        <div class="dashboard-empty-state"><?= disaster_state_filtered($state) ? 'No matching records found.' : ($can_manage ? 'No incidents yet. Select Add Incident to record the first one.' : 'No incidents have been recorded yet.') ?></div>
    <?php else: ?>
        <?php $export_query = disaster_query_string(array_merge($state, ['page' => 1])); ?>
        <div class="drr-results-head">
            <p class="activity-history-meta">Showing <?= e((string) $first) ?>–<?= e((string) $last) ?> of <?= e((string) $total) ?> record<?= $total === 1 ? '' : 's' ?></p>
            <div class="drr-exports" aria-label="Export the filtered list">
                <a class="btn btn-sm btn-outline-secondary" href="disaster_export.php<?= $export_query !== '' ? '?' . e($export_query) : '' ?>">Export Excel</a>
                <a class="btn btn-sm btn-outline-secondary" href="disaster_records_report.php?<?= e($export_query !== '' ? $export_query . '&' : '') ?>print=1" target="_blank" rel="noopener">Export PDF</a>
            </div>
        </div>
        <div class="resident-table-wrap">
            <table class="resident-table drr-table">
                <thead><tr><?= $sort_header('reference', 'Reference') . $sort_header('title', 'Activity / Incident') . $sort_header('type', 'Type') . $sort_header('area', 'Area') . $sort_header('date', 'Date') . $sort_header('status', 'Status') ?><th scope="col" class="drr-actions-head">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td class="resident-name drr-nowrap"><?= e($row['reference_no']) ?></td>
                        <td><?= e($row['title']) ?><?php if ($row['record_type'] === 'incident' && $row['alert_level']): ?><span class="drr-sub"><?= disaster_alert_badge($row['alert_level']) ?></span><?php endif; ?></td>
                        <td><?= e(disaster_types()[$row['record_type']] ?? '') ?></td>
                        <td><?= e($row['area_name']) ?></td>
                        <td class="drr-nowrap"><?= e(disaster_format_date($row['record_date'])) ?></td>
                        <td><?= $status_cell($row) ?></td>
                        <td><?= $actions($row) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($records as $row): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><?= e($row['reference_no'] . ' · ' . $row['title']) ?></strong><?= $status_cell($row) ?></div>
                    <p><?= e(disaster_types()[$row['record_type']] ?? '') ?> · <?= e($row['area_name']) ?> · <?= e(disaster_format_date($row['record_date'])) ?></p>
                    <?php if ($row['record_type'] === 'incident' && $row['alert_level']): ?><p><?= disaster_alert_badge($row['alert_level']) ?></p><?php endif; ?>
                    <?= $actions($row) ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <nav class="activity-pagination" aria-label="DRR records pagination">
                <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page - 1])) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page + 1])) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif;
};
if ($ready && live_search_is_request()) live_search_respond($render_results);

$areas = $ready ? disaster_areas($connection, null, true) : [];
$summary = $ready ? disaster_summary_counts($connection) : [];
$cards = [
    ['key' => 'incidents', 'label' => 'Active Incidents', 'href' => 'disaster.php?type=incident', 'tone' => 'deceased'],
    ['key' => 'centers', 'label' => 'Open Evacuation Centers', 'href' => 'disaster_centers.php', 'tone' => 'active'],
    ['key' => 'evacuees', 'label' => 'Current Evacuees', 'href' => 'disaster_evacuation.php', 'tone' => 'moved'],
    ['key' => 'served', 'label' => 'Families Served (active incidents)', 'href' => can_access_navigation('assistance') ? 'assistance.php' : 'disaster.php', 'tone' => 'pending'],
];
$page_title = 'Disaster Management'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Disaster Management</h1><p>Incidents, drills and other disaster risk reduction activities.<?= $can_manage ? '' : ' View only.' ?></p></div>
        <?php if ($ready && $can_manage): ?><div class="resident-detail-actions"><a class="btn btn-primary announcements-new-btn" href="disaster_form.php">Add Incident</a><a class="btn btn-light resident-action-btn" href="disaster_areas.php">Areas</a></div><?php endif; ?>
    </div>
    <?= disaster_tabs('records') ?>
    <?php if ($success = flash('disaster_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('disaster_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Disaster Management needs its database tables before records can be saved. No records have been changed.</div>
    <?php else: ?>
        <div class="drr-cards" role="list" aria-label="Disaster summary">
            <?php foreach ($cards as $card): ?>
                <a class="drr-card tone-<?= e($card['tone']) ?>" role="listitem" href="<?= e($card['href']) ?>"><span class="drr-card-count"><?= $summary[$card['key']] === null ? '—' : e(number_format($summary[$card['key']])) ?></span><span class="drr-card-label"><?= e($card['label']) ?></span></a>
            <?php endforeach; ?>
        </div>
        <section class="dashboard-panel resident-list-panel">
            <form class="resident-filters drr-filters" method="get" action="disaster.php" data-live-search data-live-target="#drr-results" data-live-range="#drr-from,#drr-to" data-drr-form>
                <?php // The chosen sort is kept in hidden fields so typing a search keeps it (see assets/js/disaster.js). ?>
                <input type="hidden" name="sort" value="<?= e($state['sort']) ?>" data-drr-field="sort">
                <input type="hidden" name="dir" value="<?= e($state['dir']) ?>" data-drr-field="dir">
                <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="drr-search">Search</label><input class="activity-filter-input" type="search" id="drr-search" name="q" value="<?= e($state['q']) ?>" maxlength="100" placeholder="Reference, title or details" autocomplete="off" data-live-query></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="drr-status">Status</label><select class="activity-filter-select" id="drr-status" name="status"><option value="">All</option><?php foreach (disaster_statuses() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?><option value="archived" <?= $state['status'] === 'archived' ? 'selected' : '' ?>>Archived</option></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="drr-type">Type</label><select class="activity-filter-select" id="drr-type" name="type"><option value="">All</option><?php foreach (disaster_types() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="drr-area">Area</label><select class="activity-filter-select" id="drr-area" name="area"><option value="">All</option><?php foreach ($areas as $area): ?><option value="<?= e((string) $area['id']) ?>" <?= $state['area'] === (int) $area['id'] ? 'selected' : '' ?>><?= e($area['name']) ?><?= (int) $area['is_active'] === 1 ? '' : ' (inactive)' ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="drr-from">From</label><input class="activity-filter-input" type="date" id="drr-from" name="from" value="<?= e($state['from']) ?>"></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="drr-to">To</label><input class="activity-filter-input" type="date" id="drr-to" name="to" value="<?= e($state['to']) ?>"></div>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="disaster.php" data-live-reset>Reset</a></div>
            </form>
            <div id="drr-results" class="live-search-results"><?php $render_results(); ?></div>
        </section>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/disaster.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/disaster.js')) ?>"></script>
