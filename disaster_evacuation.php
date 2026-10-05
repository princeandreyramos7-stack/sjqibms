<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_response.php';
require_once __DIR__ . '/includes/live_search.php';
disaster_require_view();
$can_manage = disaster_can_manage();   // Secretary views only (Health Workers, the Treasurer and residents use Disaster Info)
$connection = db();
$ready = disaster_response_ready($connection);

// Evacuation tracking: evacuees per center against capacity, and the check-in list (current by default) with search,
// incident, center and status filters. Check-out and removal of a mistaken entry are POST actions (disaster_evac_action.php).
$per_page = 15;
$state = [
    'q' => mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100),
    'incident' => filter_var($_GET['incident'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
    'center' => filter_var($_GET['center'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
    'status' => in_array($_GET['status'] ?? '', ['departed', 'all', 'archived'], true) ? (string) $_GET['status'] : '',
    'page' => max(1, (int) ($_GET['page'] ?? 1)),
];
$rows = [];
$centers = [];
$occupancy = [];
$total = 0;
$page = 1;
$total_pages = 1;
if ($ready) {
    [$family, $joins] = disaster_family_sql('e');
    $where = [match ($state['status']) { 'departed' => 'e.departed_at IS NOT NULL AND e.archived_at IS NULL', 'all' => 'e.archived_at IS NULL', 'archived' => 'e.archived_at IS NOT NULL', default => 'e.departed_at IS NULL AND e.archived_at IS NULL' }];
    $params = [];
    if ($state['q'] !== '') {
        $like = '%' . addcslashes($state['q'], '%_\\') . '%';
        $where[] = "($family LIKE :q1 OR fh.household_no LIKE :q2 OR e.remarks LIKE :q3)";
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
    }
    if ($state['incident'] !== null) { $where[] = 'e.incident_id = :incident'; $params['incident'] = $state['incident']; }
    if ($state['center'] !== null) { $where[] = 'e.center_id = :center'; $params['center'] = $state['center']; }
    $from = " FROM drr_evacuations e INNER JOIN drr_evacuation_centers c ON c.id = e.center_id INNER JOIN drr_records d ON d.id = e.incident_id$joins WHERE " . implode(' AND ', $where);
    $count = $connection->prepare('SELECT COUNT(*)' . $from);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $total_pages = max(1, (int) ceil($total / $per_page));
    $page = max(1, min($total_pages, $state['page']));
    $statement = $connection->prepare("SELECT e.id, e.family_members, e.arrived_at, e.departed_at, e.archived_at, e.remarks, e.household_id, $family AS family_label, c.name AS center_name, d.reference_no" . $from . ' ORDER BY e.departed_at IS NULL DESC, e.arrived_at DESC, e.id DESC LIMIT :limit OFFSET :offset');
    foreach ($params as $key => $value) $statement->bindValue($key, $value);
    $statement->bindValue('limit', $per_page, PDO::PARAM_INT);
    $statement->bindValue('offset', ($page - 1) * $per_page, PDO::PARAM_INT);
    $statement->execute();
    $rows = $statement->fetchAll();
    $centers = $connection->query("SELECT id, name, capacity, status FROM drr_evacuation_centers WHERE archived_at IS NULL ORDER BY FIELD(status, 'open', 'full', 'closed'), name")->fetchAll();
    $occupancy = disaster_center_occupancy($connection);
}
$query = static function (array $changes = []) use ($state, $page): string {
    $values = array_merge($state, ['page' => $page], $changes);
    if ((int) $values['page'] <= 1) $values['page'] = '';
    return http_build_query(array_filter($values, static fn ($v): bool => $v !== null && $v !== ''));
};
$url = static fn (array $changes = []): string => 'disaster_evacuation.php' . (($q = $query($changes)) !== '' ? '?' . $q : '');
$return = $query();
$filtered = $state['q'] !== '' || $state['incident'] !== null || $state['center'] !== null || $state['status'] !== '';
$first = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last = min($total, $page * $per_page);

$action_form = static function (array $row, string $action) use ($return): string {
    $checkout = $action === 'checkout';
    return '<form method="post" action="disaster_evac_action.php" class="doc-action-form">' . csrf_field()
        . '<input type="hidden" name="id" value="' . e((string) $row['id']) . '"><input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="return" value="' . e($return) . '">'
        . '<button class="btn btn-sm ' . ($checkout ? 'btn-outline-primary' : 'btn-outline-danger') . '" type="submit" data-form-confirm="custom" data-dialog-heading="' . ($checkout ? 'Check out this family?' : 'Remove this check-in?') . '" data-dialog-message="' . e($checkout ? $row['family_label'] . ' will be recorded as leaving ' . $row['center_name'] . ' now.' : 'Use this only for an entry made by mistake. ' . $row['family_label'] . ' will be removed from ' . $row['center_name'] . '. The entry is kept in the archive.') . '" data-dialog-confirm="' . ($checkout ? 'Check Out' : 'Remove') . '" data-dialog-dismiss="Cancel"' . ($checkout ? '' : ' data-dialog-danger="true"') . '>' . ($checkout ? 'Check Out' : 'Remove') . '</button></form>';
};
$status_cell = static fn (array $row): string => $row['archived_at'] !== null ? '<span class="resident-status resident-status-inactive">Removed</span>' : ($row['departed_at'] === null ? '<span class="resident-status resident-status-moved">In center</span>' : '<span class="resident-status resident-status-active">Departed</span><span class="drr-sub drr-muted">' . e(disaster_format_datetime($row['departed_at'])) . '</span>');
$actions = static fn (array $row): string => $can_manage && $row['archived_at'] === null ? '<div class="management-actions drr-actions">' . ($row['departed_at'] === null ? $action_form($row, 'checkout') : '') . $action_form($row, 'archive') . '</div>' : '';

$render_results = static function () use ($rows, $centers, $occupancy, $total, $first, $last, $page, $total_pages, $url, $filtered, $status_cell, $actions): void {
    $in_centers = array_sum(array_column($occupancy, 'persons')); ?>
    <div class="drr-occupancy" role="list" aria-label="Evacuees per center">
        <?php foreach ($centers as $center):
            $persons = $occupancy[(int) $center['id']]['persons'] ?? 0;
            $families = $occupancy[(int) $center['id']]['families'] ?? 0;
            $percent = min(100, (int) round($persons / max(1, (int) $center['capacity']) * 100)); ?>
            <div class="drr-occupancy-card" role="listitem">
                <div class="drr-occupancy-top"><strong><?= e($center['name']) ?></strong><?= disaster_center_status_badge($center['status']) ?></div>
                <div class="drr-occupancy-bar<?= $percent >= 100 ? ' is-full' : ($percent >= 80 ? ' is-high' : '') ?>" role="progressbar" aria-valuemin="0" aria-valuemax="<?= e((string) $center['capacity']) ?>" aria-valuenow="<?= e((string) $persons) ?>"><span style="width: <?= e((string) $percent) ?>%"></span></div>
                <p><strong><?= e(number_format($persons)) ?></strong> of <?= e(number_format((int) $center['capacity'])) ?> persons · <?= e((string) $families) ?> famil<?= $families === 1 ? 'y' : 'ies' ?></p>
            </div>
        <?php endforeach; ?>
        <?php if ($centers === []): ?><div class="dashboard-empty-state">No evacuation centers yet. Add them in the Evacuation Centers tab.</div><?php endif; ?>
    </div>
    <p class="activity-history-meta"><strong><?= e(number_format($in_centers)) ?></strong> evacuee<?= $in_centers === 1 ? '' : 's' ?> in centers now.<?= $total > 0 ? ' Showing ' . e((string) $first) . '–' . e((string) $last) . ' of ' . e((string) $total) . ' check-in' . ($total === 1 ? '' : 's') . '.' : '' ?></p>
    <?php if ($rows === []): ?>
        <div class="dashboard-empty-state"><?= $filtered ? 'No matching check-ins found.' : 'No families are checked in. Select Check In Family when evacuees arrive.' ?></div>
    <?php else: ?>
        <div class="resident-table-wrap">
            <table class="resident-table drr-table">
                <thead><tr><th scope="col">Family</th><th scope="col">Persons</th><th scope="col">Center</th><th scope="col">Incident</th><th scope="col">Arrived</th><th scope="col">Status</th><th scope="col" class="drr-actions-head">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="resident-name"><?= e((string) $row['family_label']) ?><span class="drr-sub drr-muted"><?= $row['household_id'] ? 'Household' : 'Individual' ?><?= $row['remarks'] ? ' · ' . e($row['remarks']) : '' ?></span></td>
                        <td><?= e((string) $row['family_members']) ?></td>
                        <td><?= e($row['center_name']) ?></td>
                        <td class="drr-nowrap"><?= e($row['reference_no']) ?></td>
                        <td class="drr-nowrap"><?= e(disaster_format_datetime($row['arrived_at'])) ?></td>
                        <td><?= $status_cell($row) ?></td>
                        <td><?= $actions($row) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($rows as $row): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><?= e((string) $row['family_label']) ?></strong><?= $status_cell($row) ?></div>
                    <p><?= e((string) $row['family_members']) ?> person<?= (int) $row['family_members'] === 1 ? '' : 's' ?> · <?= e($row['center_name']) ?> · <?= e($row['reference_no']) ?></p>
                    <p>Arrived <?= e(disaster_format_datetime($row['arrived_at'])) ?></p>
                    <?= $actions($row) ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <nav class="activity-pagination" aria-label="Check-ins pagination">
                <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page - 1])) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page + 1])) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif;
};
if ($ready && live_search_is_request()) live_search_respond($render_results);

$incidents = $ready ? disaster_all_incidents($connection) : [];
$all_centers = $ready ? $connection->query('SELECT id, name, archived_at FROM drr_evacuation_centers ORDER BY name')->fetchAll() : [];
$page_title = 'Evacuation'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Disaster Management</h1><p>Families in evacuation centers, against each center's capacity.</p></div>
        <?php if ($ready): ?><div class="resident-detail-actions"><?php if ($can_manage): ?><a class="btn btn-primary announcements-new-btn" href="disaster_checkin.php<?= $state['incident'] ?? null ? '?incident=' . e((string) $state['incident']) : '' ?>">Check In Family</a><?php endif; ?><a class="btn btn-light resident-action-btn" href="disaster_logbook_print.php<?= $state['incident'] ?? null ? '?incident=' . e((string) $state['incident']) : '' ?>" target="_blank" rel="noopener">Print Logbook</a></div><?php endif; ?>
    </div>
    <?= disaster_tabs('evacuation') ?>
    <?php if ($success = flash('disaster_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('disaster_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Evacuation tracking needs its database tables before families can be checked in. No records have been changed.</div>
    <?php else: ?>
        <section class="dashboard-panel resident-list-panel">
            <form class="resident-filters drr-filters" method="get" action="disaster_evacuation.php" data-live-search data-live-target="#drr-evac-results">
                <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="evac-search">Search</label><input class="activity-filter-input" type="search" id="evac-search" name="q" value="<?= e($state['q']) ?>" maxlength="100" placeholder="Family, household no. or remarks" autocomplete="off" data-live-query></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="evac-incident">Incident</label><select class="activity-filter-select" id="evac-incident" name="incident"><option value="">All</option><?php foreach ($incidents as $incident): ?><option value="<?= e((string) $incident['id']) ?>" <?= $state['incident'] === (int) $incident['id'] ? 'selected' : '' ?>><?= e(disaster_incident_label($incident)) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="evac-center">Center</label><select class="activity-filter-select" id="evac-center" name="center"><option value="">All</option><?php foreach ($all_centers as $center): ?><option value="<?= e((string) $center['id']) ?>" <?= $state['center'] === (int) $center['id'] ? 'selected' : '' ?>><?= e($center['name']) ?><?= $center['archived_at'] !== null ? ' (archived)' : '' ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="evac-status">Show</label><select class="activity-filter-select" id="evac-status" name="status"><option value="">In centers now</option><option value="departed" <?= $state['status'] === 'departed' ? 'selected' : '' ?>>Departed</option><option value="all" <?= $state['status'] === 'all' ? 'selected' : '' ?>>All check-ins</option><option value="archived" <?= $state['status'] === 'archived' ? 'selected' : '' ?>>Removed entries</option></select></div>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="disaster_evacuation.php" data-live-reset>Reset</a></div>
            </form>
            <div id="drr-evac-results" class="live-search-results"><?php $render_results(); ?></div>
        </section>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
