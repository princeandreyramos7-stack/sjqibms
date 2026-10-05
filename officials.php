<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/officials.php';
require_once __DIR__ . '/includes/live_search.php';
officials_require_manage();
$connection = db();
$ready = officials_ready($connection);

// Barangay Officials and staff (barangay_personnel), listed by position rank. Search and status filter run in SQL.
$search = mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100);
$status_filter = (string) ($_GET['status'] ?? '');
if (!array_key_exists($status_filter, officials_status_labels())) $status_filter = '';
$records = [];
if ($ready) {
    $where = [];
    $params = [];
    if ($search !== '') {
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where[] = '(full_name LIKE :search_name OR position LIKE :search_position OR committee LIKE :search_committee)';
        $params += ['search_name' => $like, 'search_position' => $like, 'search_committee' => $like];
    }
    if ($status_filter !== '') { $where[] = 'status = :status'; $params['status'] = $status_filter; }
    $statement = $connection->prepare('SELECT id, full_name, position, committee, service_type, term_start_year, term_end_year, contact_number, status FROM barangay_personnel' . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where)) . ' ORDER BY ' . officials_order_sql($connection) . ' LIMIT 300');
    $statement->execute($params);
    $records = $statement->fetchAll();
}
$filtered = $search !== '' || $status_filter !== '';
$muted = static fn (string $text): string => '<span class="activity-detail-muted">' . e($text) . '</span>';

$render_results = static function () use ($records, $filtered, $muted): void {
    if ($records === []): ?>
        <div class="dashboard-empty-state"><?= $filtered ? 'No matching records found.' : 'No barangay officials are recorded yet. Select Add Official to add the first one.' ?></div>
    <?php else: ?>
        <p class="activity-history-meta"><?= e((string) count($records)) ?> official<?= count($records) === 1 ? '' : 's' ?> and staff</p>
        <div class="resident-table-wrap">
            <table class="resident-table">
                <thead><tr><th scope="col">Name</th><th scope="col">Position</th><th scope="col">Committee</th><th scope="col">Term</th><th scope="col">Contact</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($records as $row): $term = officials_term_label($row); ?>
                    <tr>
                        <td class="resident-name"><?= e(officials_display_name($row)) ?></td>
                        <td><?= e($row['position']) ?></td>
                        <td><?= $row['committee'] ? e($row['committee']) : $muted('—') ?></td>
                        <td><?= $term !== '' ? e($term) : $muted('Not recorded') ?></td>
                        <td><?= $row['contact_number'] ? e($row['contact_number']) : $muted('Not recorded') ?></td>
                        <td><?= officials_status_badge($row['status']) ?></td>
                        <td><div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="official_form.php?id=<?= e((string) $row['id']) ?>" aria-label="Edit <?= e($row['full_name']) ?>">Edit</a></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($records as $row): $term = officials_term_label($row); ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><?= e(officials_display_name($row)) ?></strong><?= officials_status_badge($row['status']) ?></div>
                    <p><?= e($row['position']) ?><?= $row['committee'] ? ' · ' . e($row['committee']) : '' ?></p>
                    <p><?= $term !== '' ? e($term) : 'Term not recorded' ?> · <?= $row['contact_number'] ? e($row['contact_number']) : 'No contact recorded' ?></p>
                    <div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="official_form.php?id=<?= e((string) $row['id']) ?>">Edit</a></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif;
};
if ($ready && live_search_is_request()) live_search_respond($render_results);

$page_title = 'Barangay Officials'; $active_page = 'officials';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Barangay Officials</h1><p>Elected barangay officials and appointed staff.</p></div>
        <?php if ($ready): ?><a class="btn btn-primary announcements-new-btn" href="official_form.php">Add Official</a><?php endif; ?>
    </div>
    <?php if ($success = flash('official_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('official_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Barangay Officials needs a database update (committee, term, contact and On Leave status) before records can be listed and edited. No records have been changed.</div>
    <?php else: ?>
        <section class="dashboard-panel resident-list-panel">
            <form class="resident-filters" method="get" action="officials.php" data-live-search data-live-target="#official-results">
                <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="official-search">Search</label><input class="activity-filter-input" type="search" id="official-search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Name, position or committee" autocomplete="off" data-live-query></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="official-status">Status</label><select class="activity-filter-select" id="official-status" name="status"><option value="">All</option><?php foreach (officials_status_labels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $status_filter === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="officials.php" data-live-reset>Reset</a></div>
            </form>
            <div id="official-results" class="live-search-results"><?php $render_results(); ?></div>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
