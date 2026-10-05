<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_recovery.php';
require_once __DIR__ . '/includes/live_search.php';
disaster_require_view();
$can_manage = disaster_can_manage();   // Secretary views only (Health Workers, the Treasurer and residents use Disaster Info)
$connection = db();
$ready = disaster_recovery_ready($connection);

// Damage assessments per incident and area, with totals for the filtered list.
$state = [
    'incident' => filter_var($_GET['incident'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
    'area' => filter_var($_GET['area'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
    'archived' => (string) ($_GET['archived'] ?? '') === '1',
];
$rows = [];
if ($ready) {
    $where = [$state['archived'] ? 'g.archived_at IS NOT NULL' : 'g.archived_at IS NULL'];
    $params = [];
    if ($state['incident'] !== null) { $where[] = 'g.incident_id = :incident'; $params['incident'] = $state['incident']; }
    if ($state['area'] !== null) { $where[] = 'g.area_id = :area'; $params['area'] = $state['area']; }
    $statement = $connection->prepare('SELECT g.*, a.name AS area_name, d.reference_no FROM drr_damage_assessments g INNER JOIN drr_areas a ON a.id = g.area_id INNER JOIN drr_records d ON d.id = g.incident_id WHERE ' . implode(' AND ', $where) . ' ORDER BY g.assessed_on DESC, d.reference_no DESC, a.sort_order, a.name LIMIT 200');
    $statement->execute($params);
    $rows = $statement->fetchAll();
}
$return = http_build_query(array_filter(['incident' => $state['incident'], 'area' => $state['area'], 'archived' => $state['archived'] ? '1' : ''], static fn ($v): bool => $v !== null && $v !== ''));
$other = static function (array $row): string {
    $parts = [];
    foreach (disaster_damage_other_fields() as $field => $label) if ($row[$field]) $parts[] = $label . ': ' . $row[$field];
    return implode(' · ', $parts);
};
$archive_button = static function (array $row) use ($return): string {
    $archived = $row['archived_at'] !== null;
    return '<form method="post" action="disaster_damage_action.php" class="doc-action-form">' . csrf_field()
        . '<input type="hidden" name="id" value="' . e((string) $row['id']) . '"><input type="hidden" name="action" value="' . ($archived ? 'restore' : 'archive') . '"><input type="hidden" name="return" value="' . e($return) . '">'
        . '<button class="btn btn-sm ' . ($archived ? 'btn-outline-primary' : 'btn-outline-danger') . '" type="submit" data-form-confirm="custom" data-dialog-heading="' . ($archived ? 'Restore this assessment?' : 'Archive this assessment?') . '" data-dialog-message="' . e($archived ? 'It will count in the totals and reports again.' : 'It will be hidden from the list, totals and reports. It is kept and can be restored.') . '" data-dialog-confirm="' . ($archived ? 'Restore' : 'Archive') . '" data-dialog-dismiss="Cancel"' . ($archived ? '' : ' data-dialog-danger="true"') . '>' . ($archived ? 'Restore' : 'Archive') . '</button></form>';
};
$actions = static fn (array $row): string => '<div class="management-actions drr-actions">' . ($row['photo_path'] ? '<a class="btn btn-sm btn-outline-secondary" href="disaster_photo.php?id=' . e((string) $row['id']) . '" target="_blank" rel="noopener">Photo</a>' : '') . (!$can_manage ? '' : ($row['archived_at'] === null ? '<a class="btn btn-sm btn-outline-primary" href="disaster_damage_form.php?id=' . e((string) $row['id']) . '">Edit</a>' : '') . $archive_button($row)) . '</div>';

$render_results = static function () use ($rows, $state, $other, $actions): void {
    $partial = array_sum(array_map(static fn (array $r): int => (int) $r['houses_partial'], $rows));
    $totally = array_sum(array_map(static fn (array $r): int => (int) $r['houses_total'], $rows)); ?>
    <p class="activity-history-meta"><?= e((string) count($rows)) ?> assessment<?= count($rows) === 1 ? '' : 's' ?> · <strong><?= e(number_format($partial)) ?></strong> houses partially damaged · <strong><?= e(number_format($totally)) ?></strong> totally damaged</p>
    <?php if ($rows === []): ?>
        <div class="dashboard-empty-state"><?= $state['incident'] !== null || $state['area'] !== null || $state['archived'] ? 'No matching assessments found.' : 'No damage assessments yet. Select Add Assessment after an incident.' ?></div>
    <?php else: ?>
        <div class="resident-table-wrap">
            <table class="resident-table drr-table">
                <thead><tr><th scope="col">Incident</th><th scope="col">Area</th><th scope="col">Houses (partial / total)</th><th scope="col">Other Damage</th><th scope="col">Assessed</th><th scope="col" class="drr-actions-head">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="resident-name drr-nowrap"><?= e($row['reference_no']) ?></td>
                        <td><?= e($row['area_name']) ?></td>
                        <td class="drr-nowrap"><?= e(number_format((int) $row['houses_partial'])) ?> / <?= e(number_format((int) $row['houses_total'])) ?></td>
                        <td class="drr-notes"><?= $other($row) !== '' ? e($other($row)) : '<span class="activity-detail-muted">None recorded</span>' ?></td>
                        <td class="drr-nowrap"><?= e(disaster_format_date($row['assessed_on'])) ?></td>
                        <td><?= $actions($row) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($rows as $row): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><?= e($row['reference_no'] . ' · ' . $row['area_name']) ?></strong><span class="drr-muted"><?= e(disaster_format_date($row['assessed_on'])) ?></span></div>
                    <p>Houses: <?= e(number_format((int) $row['houses_partial'])) ?> partial / <?= e(number_format((int) $row['houses_total'])) ?> total</p>
                    <?php if ($other($row) !== ''): ?><p><?= e($other($row)) ?></p><?php endif; ?>
                    <?= $actions($row) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif;
};
if ($ready && live_search_is_request()) live_search_respond($render_results);

$incidents = $ready ? disaster_all_incidents($connection) : [];
$areas = $ready ? disaster_areas($connection, null, true) : [];
$page_title = 'Damage Assessment'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Disaster Management</h1><p>Damage to houses, roads, bridges, crops and public facilities per incident and area.</p></div>
        <?php if ($ready && $can_manage): ?><div class="resident-detail-actions"><a class="btn btn-primary announcements-new-btn" href="disaster_damage_form.php<?= $state['incident'] ? '?incident=' . e((string) $state['incident']) : '' ?>">Add Assessment</a></div><?php endif; ?>
    </div>
    <?= disaster_tabs('damage') ?>
    <?php if ($success = flash('disaster_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('disaster_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Damage assessment needs its database tables before assessments can be recorded. No records have been changed.</div>
    <?php else: ?>
        <section class="dashboard-panel resident-list-panel">
            <form class="resident-filters drr-filters" method="get" action="disaster_damage.php" data-live-search data-live-target="#drr-damage-results">
                <div class="resident-filter-group"><label class="activity-filter-label" for="damage-incident">Incident</label><select class="activity-filter-select" id="damage-incident" name="incident"><option value="">All</option><?php foreach ($incidents as $incident): ?><option value="<?= e((string) $incident['id']) ?>" <?= $state['incident'] === (int) $incident['id'] ? 'selected' : '' ?>><?= e(disaster_incident_label($incident)) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="damage-area">Area</label><select class="activity-filter-select" id="damage-area" name="area"><option value="">All</option><?php foreach ($areas as $area): ?><option value="<?= e((string) $area['id']) ?>" <?= $state['area'] === (int) $area['id'] ? 'selected' : '' ?>><?= e($area['name']) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="damage-archived">Show</label><select class="activity-filter-select" id="damage-archived" name="archived"><option value="">Current</option><option value="1" <?= $state['archived'] ? 'selected' : '' ?>>Archived</option></select></div>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="disaster_damage.php" data-live-reset>Reset</a></div>
            </form>
            <div id="drr-damage-results" class="live-search-results"><?php $render_results(); ?></div>
        </section>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
