<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_prep.php';
require_once __DIR__ . '/includes/live_search.php';
disaster_require_view();
$can_manage = disaster_can_manage();   // Secretary views only (Health Workers, the Treasurer and residents use Disaster Info)
$connection = db();
$ready = disaster_prep_ready($connection);

// Evacuation centers: search, status (with Archived) and area; all applied in SQL and kept in the URL.
$state = ['q' => mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100), 'status' => (string) ($_GET['status'] ?? ''), 'area' => filter_var($_GET['area'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null];
if ($state['status'] !== 'archived' && !array_key_exists($state['status'], disaster_center_statuses())) $state['status'] = '';
$centers = [];
if ($ready) {
    $where = [$state['status'] === 'archived' ? 'c.archived_at IS NOT NULL' : 'c.archived_at IS NULL'];
    $params = [];
    if ($state['q'] !== '') {
        $like = '%' . addcslashes($state['q'], '%_\\') . '%';
        $where[] = '(c.name LIKE :q1 OR c.address LIKE :q2 OR c.contact_person LIKE :q3 OR a.name LIKE :q4)';
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
    }
    if ($state['status'] !== '' && $state['status'] !== 'archived') { $where[] = 'c.status = :status'; $params['status'] = $state['status']; }
    if ($state['area'] !== null) { $where[] = 'c.area_id = :area'; $params['area'] = $state['area']; }
    $statement = $connection->prepare("SELECT c.*, a.name AS area_name FROM drr_evacuation_centers c INNER JOIN drr_areas a ON a.id = c.area_id WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(c.status, 'open', 'full', 'closed'), c.name");
    $statement->execute($params);
    $centers = $statement->fetchAll();
}
$return = http_build_query(array_filter($state, static fn ($v): bool => $v !== null && $v !== ''));
$filtered = $state['q'] !== '' || $state['status'] !== '' || $state['area'] !== null;

$archive_button = static function (array $row) use ($return): string {
    $archived = $row['archived_at'] !== null;
    return '<form method="post" action="disaster_prep_action.php" class="doc-action-form">' . csrf_field()
        . '<input type="hidden" name="kind" value="center"><input type="hidden" name="id" value="' . e((string) $row['id']) . '"><input type="hidden" name="action" value="' . ($archived ? 'restore' : 'archive') . '"><input type="hidden" name="return" value="' . e($return) . '">'
        . '<button class="btn btn-sm ' . ($archived ? 'btn-outline-primary' : 'btn-outline-danger') . '" type="submit" data-form-confirm="custom" data-dialog-heading="' . ($archived ? 'Restore this center?' : 'Archive this center?') . '" data-dialog-message="' . e($archived ? $row['name'] . ' will appear in the list again.' : $row['name'] . ' will be hidden from the list. The record is kept and can be restored.') . '" data-dialog-confirm="' . ($archived ? 'Restore' : 'Archive') . '" data-dialog-dismiss="Cancel"' . ($archived ? '' : ' data-dialog-danger="true"') . '>' . ($archived ? 'Restore' : 'Archive') . '</button></form>';
};
$facilities = static function (array $row): string {
    $html = '<span class="drr-facilities">';
    foreach (disaster_facilities() as $field => $label) $html .= '<span class="drr-facility' . ((int) $row[$field] === 1 ? ' is-yes' : '') . '">' . ((int) $row[$field] === 1 ? '✓ ' : '✗ ') . e($label) . '</span>';
    return $html . '</span>';
};
$status_cell = static fn (array $row): string => $row['archived_at'] !== null ? '<span class="resident-status resident-status-inactive">Archived</span>' : disaster_center_status_badge($row['status']);
$contact = static fn (array $row): string => $row['contact_person'] || $row['contact_number'] ? e(trim(($row['contact_person'] ?? '') . ($row['contact_person'] && $row['contact_number'] ? ' · ' : '') . ($row['contact_number'] ?? ''))) : '<span class="activity-detail-muted">Not recorded</span>';
$actions = static fn (array $row): string => '<div class="management-actions drr-actions">' . (!$can_manage ? '' : ($row['archived_at'] === null ? '<a class="btn btn-sm btn-outline-primary" href="disaster_center_form.php?id=' . e((string) $row['id']) . '">Edit</a>' : '') . $archive_button($row)) . '</div>';

$render_results = static function () use ($centers, $filtered, $facilities, $status_cell, $contact, $actions): void {
    if ($centers === []): ?>
        <div class="dashboard-empty-state"><?= $filtered ? 'No matching evacuation centers found.' : 'No evacuation centers yet. Select Add Center to record the first one.' ?></div>
    <?php else: ?>
        <p class="activity-history-meta"><?= e((string) count($centers)) ?> evacuation center<?= count($centers) === 1 ? '' : 's' ?> · total capacity <?= e(number_format(array_sum(array_map(static fn (array $r): int => (int) $r['capacity'], $centers)))) ?> persons</p>
        <div class="resident-table-wrap">
            <table class="resident-table drr-table">
                <thead><tr><th scope="col">Center</th><th scope="col">Area</th><th scope="col">Capacity</th><th scope="col">Facilities</th><th scope="col">Contact</th><th scope="col">Status</th><th scope="col" class="drr-actions-head">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($centers as $row): ?>
                    <tr>
                        <td class="resident-name"><?= e($row['name']) ?><span class="drr-sub drr-muted"><?= e($row['address']) ?></span></td>
                        <td><?= e($row['area_name']) ?></td>
                        <td class="drr-nowrap"><?= e(number_format((int) $row['capacity'])) ?> persons</td>
                        <td><?= $facilities($row) ?></td>
                        <td><?= $contact($row) ?></td>
                        <td><?= $status_cell($row) ?></td>
                        <td><?= $actions($row) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($centers as $row): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><?= e($row['name']) ?></strong><?= $status_cell($row) ?></div>
                    <p><?= e($row['address']) ?> · <?= e($row['area_name']) ?></p>
                    <p><?= e(number_format((int) $row['capacity'])) ?> persons · <?= $contact($row) ?></p>
                    <p><?= $facilities($row) ?></p>
                    <?= $actions($row) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif;
};
if ($ready && live_search_is_request()) live_search_respond($render_results);

$areas = $ready ? disaster_areas($connection, null, true) : [];
$page_title = 'Evacuation Centers'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Disaster Management</h1><p>Evacuation centers, their capacity and facilities.</p></div>
        <?php if ($ready && $can_manage): ?><div class="resident-detail-actions"><a class="btn btn-primary announcements-new-btn" href="disaster_center_form.php">Add Center</a></div><?php endif; ?>
    </div>
    <?= disaster_tabs('centers') ?>
    <?php if ($success = flash('disaster_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('disaster_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Evacuation centers need their database table before they can be recorded. No records have been changed.</div>
    <?php else: ?>
        <section class="dashboard-panel resident-list-panel">
            <form class="resident-filters drr-filters" method="get" action="disaster_centers.php" data-live-search data-live-target="#drr-center-results">
                <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="center-search">Search</label><input class="activity-filter-input" type="search" id="center-search" name="q" value="<?= e($state['q']) ?>" maxlength="100" placeholder="Center, address or contact person" autocomplete="off" data-live-query></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="center-status">Status</label><select class="activity-filter-select" id="center-status" name="status"><option value="">All</option><?php foreach (disaster_center_statuses() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?><option value="archived" <?= $state['status'] === 'archived' ? 'selected' : '' ?>>Archived</option></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="center-area">Area</label><select class="activity-filter-select" id="center-area" name="area"><option value="">All</option><?php foreach ($areas as $area): ?><option value="<?= e((string) $area['id']) ?>" <?= $state['area'] === (int) $area['id'] ? 'selected' : '' ?>><?= e($area['name']) ?><?= (int) $area['is_active'] === 1 ? '' : ' (inactive)' ?></option><?php endforeach; ?></select></div>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="disaster_centers.php" data-live-reset>Reset</a></div>
            </form>
            <div id="drr-center-results" class="live-search-results"><?php $render_results(); ?></div>
        </section>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
