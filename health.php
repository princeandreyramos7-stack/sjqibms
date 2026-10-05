<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health_programs.php';
require_once __DIR__ . '/includes/live_search.php';
health_require_manage();
$connection = db();
$ready = health_ready($connection);

// Health service records: search, status, service, health worker, date range and Due Today & Overdue, all applied in SQL
// before counting and paging, and kept in the URL.
$per_page = 10;
$state = health_list_state($_GET);
$records = [];
$total = 0;
$page = 1;
$total_pages = 1;
$workers = [];
$phase2 = $ready && health_phase2_ready($connection);
$conditions = $phase2 ? health_conditions($connection, false) : [];
if ($ready) {
    [$where, $params] = health_list_where($state);
    $from = ' FROM health_records h INNER JOIN residents r ON r.id = h.resident_id WHERE ' . implode(' AND ', $where);
    $count = $connection->prepare('SELECT COUNT(*)' . $from);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $total_pages = max(1, (int) ceil($total / $per_page));
    $page = max(1, min($total_pages, $state['page']));
    $order = health_list_order($state['due']);
    $statement = $connection->prepare('SELECT h.id, h.record_no, h.service, h.service_details, h.health_worker, h.service_date, h.status, h.follow_up_date, h.archived_at, h.resident_id, ' . health_due_sql('h') . ' AS is_due, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.sex, r.purok' . ($phase2 ? ', hc.name AS condition_name' : '') . ' FROM health_records h INNER JOIN residents r ON r.id = h.resident_id' . ($phase2 ? ' LEFT JOIN health_conditions hc ON hc.id = h.condition_id' : '') . ' WHERE ' . implode(' AND ', $where) . " ORDER BY $order LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) $statement->bindValue($key, $value);
    $statement->bindValue('limit', $per_page, PDO::PARAM_INT);
    $statement->bindValue('offset', ($page - 1) * $per_page, PDO::PARAM_INT);
    $statement->execute();
    $records = $statement->fetchAll();
    $workers = health_worker_filter_options($connection);
}
$first = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last = min($total, $page * $per_page);
$page_url = static fn (int $target): string => 'health.php?' . health_query_string(array_merge($state, ['page' => $target]));
$return = health_query_string(array_merge($state, ['page' => $page]));
$filtered = $state['q'] !== '' || $state['status'] !== '' || $state['service'] !== '' || $state['worker'] !== '' || $state['from'] !== '' || $state['to'] !== '' || $state['purok'] !== '' || $state['condition'] > 0 || $state['due'];

$archive_button = static function (array $row) use ($return): string {
    $archived = $row['archived_at'] !== null;
    return '<form method="post" action="health_action.php" class="doc-action-form">' . csrf_field()
        . '<input type="hidden" name="id" value="' . e((string) $row['id']) . '"><input type="hidden" name="action" value="' . ($archived ? 'restore' : 'archive') . '"><input type="hidden" name="return" value="' . e($return) . '">'
        . '<button class="btn btn-sm ' . ($archived ? 'btn-outline-primary' : 'btn-outline-danger') . '" type="submit" data-form-confirm="custom" data-dialog-heading="' . ($archived ? 'Restore this record?' : 'Archive this record?') . '" data-dialog-message="' . e($archived ? $row['record_no'] . ' will appear in the health records list again.' : $row['record_no'] . ' will be hidden from the list. The record is kept and can be restored.') . '" data-dialog-confirm="' . ($archived ? 'Restore' : 'Archive') . '" data-dialog-dismiss="Cancel"' . ($archived ? '' : ' data-dialog-danger="true"') . '>' . ($archived ? 'Restore' : 'Archive') . '</button></form>';
};

$render_results = static function () use ($records, $total, $first, $last, $total_pages, $page, $page_url, $filtered, $archive_button, $state): void {
    if ($records === []): ?>
        <div class="dashboard-empty-state"><?= $state['due'] ? 'Nothing is due today or overdue.' : ($filtered ? 'No matching records found.' : ($state['show'] === 'all' ? 'No health records yet. Select Add Record to record the first one.' : 'You have not recorded any health records yet. Select Add Record, or open All records.')) ?></div>
    <?php else: ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><p class="activity-history-meta mb-0">Showing <?= e((string) $first) ?>–<?= e((string) $last) ?> of <?= e((string) $total) ?> record<?= $total === 1 ? '' : 's' ?></p><a class="btn btn-sm btn-outline-secondary" href="health_list_print.php<?= ($print_query = health_query_string(array_merge($state, ['page' => 1]))) !== '' ? '?' . e($print_query) : '' ?>" target="_blank" rel="noopener">Print List</a></div>
        <div class="resident-table-wrap">
            <table class="resident-table health-table">
                <thead><tr><th scope="col">Record No.</th><th scope="col">Resident</th><th scope="col" class="num">Age</th><th scope="col">Sex</th><th scope="col">Purok</th><th scope="col">Diagnosis</th><th scope="col">Date</th><th scope="col">Status</th><th scope="col" class="health-actions-head">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($records as $row): $due = health_is_due($row); $due_visit = $due && $row['status'] === 'scheduled' && $row['service_date'] <= date('Y-m-d'); $age = residents_age($row['birth_date']); ?>
                    <tr class="<?= $due ? 'health-row-due' : '' ?>">
                        <td class="resident-name"><?= e($row['record_no']) ?></td>
                        <td><a class="activity-detail-link" href="health_history.php?resident=<?= e((string) $row['resident_id']) ?>" title="Health history"><?= e(residents_full_name($row)) ?></a></td>
                        <td class="num"><?= $age === null ? '—' : e((string) $age) ?></td>
                        <td><?= e(residents_sex_labels()[$row['sex']] ?? '—') ?></td>
                        <td class="health-nowrap"><?= e(residents_purok_label((string) $row['purok'])) ?></td>
                        <td><?= !empty($row['condition_name']) ? e($row['condition_name']) : '<span class="activity-detail-muted">—</span>' ?></td>
                        <td class="health-nowrap"><?= e(health_format_date($row['service_date'])) ?><span class="health-sub"><?= e($row['health_worker']) ?></span></td>
                        <td><?= $row['archived_at'] !== null ? '<span class="resident-status resident-status-inactive">Archived</span>' : health_status_badge($row['status']) ?><?php if ($due_visit): ?><span class="health-sub is-due"><?= $row['service_date'] < date('Y-m-d') ? 'Overdue visit' : 'Visit today' ?></span><?php elseif ($row['follow_up_date'] && $row['status'] !== 'cancelled'): ?><span class="health-sub<?= $due ? ' is-due' : '' ?>"><?= $due ? ($row['follow_up_date'] < date('Y-m-d') ? 'Overdue · ' : 'Due today · ') : 'Follow-up ' ?><?= e(health_format_date($row['follow_up_date'])) ?></span><?php endif; ?></td>
                        <td><div class="management-actions health-actions"><a class="btn btn-sm btn-outline-primary" href="health_view.php?id=<?= e((string) $row['id']) ?>">View</a><?php if ($row['archived_at'] === null): ?><a class="btn btn-sm btn-outline-primary" href="health_form.php?id=<?= e((string) $row['id']) ?>">Edit</a><?php endif; ?><?= $archive_button($row) ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($records as $row): $due = health_is_due($row); ?>
                <li class="resident-card<?= $due ? ' health-row-due' : '' ?>">
                    <div class="resident-card-top"><strong><?= e($row['record_no']) ?> · <a class="activity-detail-link" href="health_history.php?resident=<?= e((string) $row['resident_id']) ?>"><?= e(residents_full_name($row)) ?></a></strong><?= $row['archived_at'] !== null ? '<span class="resident-status resident-status-inactive">Archived</span>' : health_status_badge($row['status']) ?></div>
                    <p><?php $age = residents_age($row['birth_date']); ?><?= e(implode(' · ', array_filter([$age === null ? '' : $age . ' yrs', residents_sex_labels()[$row['sex']] ?? '', residents_purok_label((string) $row['purok'])]))) ?></p>
                    <?php if (!empty($row['condition_name'])): ?><p>Diagnosis: <?= e($row['condition_name']) ?></p><?php endif; ?>
                    <p><?= e($row['health_worker']) ?> · <?= e(health_format_date($row['service_date'])) ?><?= $row['follow_up_date'] && $row['status'] !== 'cancelled' ? ' · Follow-up ' . e(health_format_date($row['follow_up_date'])) : '' ?></p>
                    <div class="management-actions health-actions"><a class="btn btn-sm btn-outline-primary" href="health_view.php?id=<?= e((string) $row['id']) ?>">View</a><?php if ($row['archived_at'] === null): ?><a class="btn btn-sm btn-outline-primary" href="health_form.php?id=<?= e((string) $row['id']) ?>">Edit</a><?php endif; ?><?= $archive_button($row) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <nav class="activity-pagination" aria-label="Health records pagination">
                <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($page_url($page - 1)) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($page_url($page + 1)) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif;
};
if ($ready && live_search_is_request()) live_search_respond($render_results);

$page_title = 'Health'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <?= $ready ? health_page_heading('Check-ups and other consultations at the health station.', 'health_form.php', 'Add Record') : '<div class="page-heading"><div><h1>Health</h1></div></div>' ?>
    <?php if ($success = flash('health_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('health_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Health records need their database table before records can be saved. No records have been changed.</div>
    <?php else: ?>
        <?= health_module_top($connection, 'consultations') ?>
        <section class="dashboard-panel resident-list-panel">
            <?php $scope = residents_purok_scope($connection); ?>
            <nav class="announcement-tabs page-tabs" aria-label="Whose records"><?php foreach (['mine' => 'My records', 'all' => $scope === null ? 'All records' : 'All records in my Puroks'] as $key => $label): ?><a class="announcement-tab <?= $state['show'] === $key ? 'active' : '' ?>" href="health.php?<?= e(http_build_query(array_filter(['show' => $key === 'all' ? 'all' : '', 'due' => $state['due'] ? '1' : ''], static fn ($v): bool => $v !== ''))) ?>"<?= $state['show'] === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
            <p class="activity-history-meta health-meta-line"><?= $scope !== null ? 'Your assigned Puroks: ' . e(implode(', ', array_map('residents_purok_label', $scope))) . '.' : '' ?><?php if ($phase2): ?> <a class="activity-detail-link" href="health_conditions.php">⚙ Diagnosis list</a><?php endif; ?></p>
            <?php if ($state['due']): ?><div class="dashboard-status warning" role="status">Showing only what is <strong><?= $state['when'] === 'today' ? 'due today' : ($state['when'] === 'overdue' ? 'overdue' : 'due today or overdue') ?></strong>: follow-ups whose date has come and scheduled visits on or before today. <a class="activity-detail-link" href="health.php">Show all records</a></div><?php endif; ?>
            <form class="resident-filters health-filters" method="get" action="health.php" data-live-search data-live-target="#health-results" data-live-range="#health-from,#health-to">
                <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="health-search">Search</label><input class="activity-filter-input" type="search" id="health-search" name="q" value="<?= e($state['q']) ?>" maxlength="100" placeholder="Resident, record no. or remarks" autocomplete="off" data-live-query></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="health-status">Status</label><select class="activity-filter-select" id="health-status" name="status"><option value="">All</option><?php foreach (health_statuses() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?><option value="archived" <?= $state['status'] === 'archived' ? 'selected' : '' ?>>Archived</option></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="health-worker">Health Worker</label><select class="activity-filter-select" id="health-worker" name="worker"><option value="">All</option><?php foreach ($workers as $worker): ?><option value="<?= e($worker) ?>" <?= $state['worker'] === $worker ? 'selected' : '' ?>><?= e($worker) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="health-purok">Purok</label><select class="activity-filter-select" id="health-purok" name="purok"><option value="">All</option><?php foreach (health_resident_puroks($connection) as $purok_value): ?><option value="<?= e($purok_value) ?>" <?= $state['purok'] === $purok_value ? 'selected' : '' ?>><?= e(residents_purok_label($purok_value)) ?></option><?php endforeach; ?></select></div>
                <?php if ($phase2): ?><div class="resident-filter-group"><label class="activity-filter-label" for="health-condition">Diagnosis</label><select class="activity-filter-select" id="health-condition" name="condition"><option value="">All</option><?php foreach ($conditions as $condition): ?><option value="<?= e((string) $condition['id']) ?>" <?= $state['condition'] === (int) $condition['id'] ? 'selected' : '' ?>><?= e($condition['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                <div class="resident-filter-group"><label class="activity-filter-label" for="health-from">Date range</label><div class="health-filter-dates"><input class="activity-filter-input" type="date" id="health-from" name="from" value="<?= e($state['from']) ?>" aria-label="From"><span>to</span><input class="activity-filter-input" type="date" id="health-to" name="to" value="<?= e($state['to']) ?>" aria-label="To"></div></div>
                <?php // Due today & overdue is opened with the button at the top; the filters below narrow that list. ?><?php if ($state['due']): ?><input type="hidden" name="due" value="1"><?php if ($state['when'] !== ''): ?><input type="hidden" name="when" value="<?= e($state['when']) ?>"><?php endif; ?><?php endif; ?><?php if ($state['show'] === 'all'): ?><input type="hidden" name="show" value="all"><?php endif; ?>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="health.php" data-live-reset>Reset</a></div>
            </form>
            <div id="health-results" class="live-search-results"><?php $render_results(); ?></div>
        </section>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
