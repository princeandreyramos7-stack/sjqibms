<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_prep.php';
require_once __DIR__ . '/includes/live_search.php';
disaster_require_view();
$connection = db();

// Risk Map: for each Purok, the hazards recorded by the BDRRMC, the families living there (from Households) and the
// vulnerable residents (from Residents), then the list of hazard-prone areas. Families are counted automatically;
// nothing but the hazards is typed in. Kagawad and the System Administrator manage the hazards; the Secretary
// views only. Health Workers, the Treasurer and residents use Disaster Info instead.
$can_manage = disaster_can_manage();
$can_view_names = disaster_can_view_vulnerable();
$ready = disaster_prep_ready($connection);
$risk = $ready ? disaster_risk_by_purok($connection) : [];

// Hazard-prone areas: search, hazard type, risk level, area and (managers) Archived; highest risk first.
$state = ['q' => mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100), 'hazard' => (string) ($_GET['hazard'] ?? ''), 'risk' => (string) ($_GET['risk'] ?? ''), 'area' => filter_var($_GET['area'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null, 'archived' => $can_manage && (string) ($_GET['archived'] ?? '') === '1'];
if (!array_key_exists($state['hazard'], disaster_hazard_types())) $state['hazard'] = '';
if (!array_key_exists($state['risk'], disaster_risk_levels())) $state['risk'] = '';
$hazards = [];
if ($ready) {
    $where = [$state['archived'] ? 'h.archived_at IS NOT NULL' : 'h.archived_at IS NULL'];
    $params = [];
    if ($state['q'] !== '') {
        $like = '%' . addcslashes($state['q'], '%_\\') . '%';
        $where[] = '(a.name LIKE :q1 OR h.hazard_other LIKE :q2 OR h.notes LIKE :q3)';
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
    }
    if ($state['hazard'] !== '') { $where[] = 'h.hazard_type = :hazard'; $params['hazard'] = $state['hazard']; }
    if ($state['risk'] !== '') { $where[] = 'h.risk_level = :risk'; $params['risk'] = $state['risk']; }
    if ($state['area'] !== null) { $where[] = 'h.area_id = :area'; $params['area'] = $state['area']; }
    $statement = $connection->prepare("SELECT h.*, a.name AS area_name FROM drr_hazard_areas h INNER JOIN drr_areas a ON a.id = h.area_id WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(h.risk_level, 'high', 'medium', 'low'), a.sort_order, a.name, h.hazard_type");
    $statement->execute($params);
    $hazards = $statement->fetchAll();
}
$return = http_build_query(array_filter(['q' => $state['q'], 'hazard' => $state['hazard'], 'risk' => $state['risk'], 'area' => $state['area'], 'archived' => $state['archived'] ? '1' : ''], static fn ($v): bool => $v !== null && $v !== ''));
$filtered = $state['q'] !== '' || $state['hazard'] !== '' || $state['risk'] !== '' || $state['area'] !== null || $state['archived'];

$archive_button = static function (array $row) use ($return): string {
    $archived = $row['archived_at'] !== null;
    $label = $row['area_name'] . ' — ' . disaster_hazard_label($row);
    return '<form method="post" action="disaster_prep_action.php" class="doc-action-form">' . csrf_field()
        . '<input type="hidden" name="kind" value="hazard"><input type="hidden" name="id" value="' . e((string) $row['id']) . '"><input type="hidden" name="action" value="' . ($archived ? 'restore' : 'archive') . '"><input type="hidden" name="return" value="' . e($return) . '">'
        . '<button class="btn btn-sm ' . ($archived ? 'btn-outline-primary' : 'btn-outline-danger') . '" type="submit" data-form-confirm="custom" data-dialog-heading="' . ($archived ? 'Restore this entry?' : 'Archive this entry?') . '" data-dialog-message="' . e($archived ? $label . ' will appear in the list again.' : $label . ' will be hidden from the list. The record is kept and can be restored.') . '" data-dialog-confirm="' . ($archived ? 'Restore' : 'Archive') . '" data-dialog-dismiss="Cancel"' . ($archived ? '' : ' data-dialog-danger="true"') . '>' . ($archived ? 'Restore' : 'Archive') . '</button></form>';
};
$actions = static fn (array $row): string => '<div class="management-actions drr-actions">' . ($row['archived_at'] === null ? '<a class="btn btn-sm btn-outline-primary" href="disaster_hazard_form.php?id=' . e((string) $row['id']) . '">Edit</a>' : '') . $archive_button($row) . '</div>';
$families = static function (array $row) use ($risk): string {
    $count = disaster_hazard_families($risk, (string) $row['area_name']);
    return $count === null ? '<span class="activity-detail-muted">—</span>' : e(number_format($count));
};

$render_results = static function () use ($hazards, $filtered, $actions, $families, $can_manage): void {
    if ($hazards === []): ?>
        <div class="dashboard-empty-state"><?= $filtered ? 'No matching hazard-prone areas found.' : ($can_manage ? 'No hazard-prone areas yet. Select Add Hazard Area to record the first one.' : 'No hazard-prone areas have been recorded yet.') ?></div>
    <?php else: ?>
        <p class="activity-history-meta"><?= e((string) count($hazards)) ?> hazard-prone area<?= count($hazards) === 1 ? '' : 's' ?> · families are counted from the households in each Purok</p>
        <div class="resident-table-wrap">
            <table class="resident-table drr-table">
                <thead><tr><th scope="col">Area</th><th scope="col">Hazard</th><th scope="col">Risk Level</th><th scope="col">Families</th><th scope="col">Notes</th><?php if ($can_manage): ?><th scope="col" class="drr-actions-head">Actions</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($hazards as $row): ?>
                    <tr>
                        <td class="resident-name"><?= e($row['area_name']) ?><?= $row['archived_at'] !== null ? '<span class="drr-sub"><span class="resident-status resident-status-inactive">Archived</span></span>' : '' ?></td>
                        <td><?= e(disaster_hazard_label($row)) ?></td>
                        <td><?= disaster_risk_badge($row['risk_level']) ?></td>
                        <td><?= $families($row) ?></td>
                        <td class="drr-notes"><?= $row['notes'] ? e(mb_strimwidth($row['notes'], 0, 120, '…')) : '<span class="activity-detail-muted">—</span>' ?></td>
                        <?php if ($can_manage): ?><td><?= $actions($row) ?></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($hazards as $row): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><?= e($row['area_name'] . ' · ' . disaster_hazard_label($row)) ?></strong><?= disaster_risk_badge($row['risk_level']) ?></div>
                    <p>Families: <?= $families($row) ?></p>
                    <?php if ($row['notes']): ?><p><?= e(mb_strimwidth($row['notes'], 0, 160, '…')) ?></p><?php endif; ?>
                    <?= $can_manage ? $actions($row) : '' ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif;
};
if ($ready && live_search_is_request()) live_search_respond($render_results);

$areas = $ready ? disaster_areas($connection, null, true) : [];
$page_title = 'Risk Map'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Disaster Management</h1><p>Risk Map: the hazards, families and vulnerable residents of each Purok.<?= $can_manage ? '' : ' View only.' ?></p></div>
        <?php if ($ready && $can_manage): ?><div class="resident-detail-actions"><a class="btn btn-primary announcements-new-btn" href="disaster_hazard_form.php">Add Hazard Area</a></div><?php endif; ?>
    </div>
    <?= disaster_tabs('risk') ?>
    <?php if ($success = flash('disaster_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('disaster_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">The Risk Map needs its database tables first. No records have been changed.</div>
    <?php else: ?>
        <section class="dashboard-panel resident-list-panel">
            <div class="drr-results-head">
                <h2 class="drr-section-title">By Purok</h2>
                <?php if ($can_view_names): ?><a class="btn btn-sm btn-outline-secondary" href="disaster_vulnerable_print.php" target="_blank" rel="noopener">Print Vulnerable List (all Puroks)</a><?php endif; ?>
            </div>
            <div class="resident-table-wrap">
                <table class="resident-table drr-table">
                    <thead><tr><th scope="col">Purok</th><th scope="col">Hazards</th><th scope="col">Families</th><th scope="col">Vulnerable Residents</th><?php if ($can_view_names): ?><th scope="col" class="drr-actions-head">Names</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($risk as $purok => $entry): ?>
                        <tr>
                            <td class="resident-name"><?= e($entry['label']) ?></td>
                            <td><?= $entry['hazards'] === [] ? '<span class="activity-detail-muted">None recorded</span>' : implode(' ', array_map(static fn (array $h): string => '<span class="drr-sub-inline">' . e(disaster_hazard_label($h)) . ' ' . disaster_risk_badge($h['risk_level']) . '</span>', $entry['hazards'])) ?></td>
                            <td><?= e(number_format($entry['families'])) ?></td>
                            <td><?= $entry['vulnerable_total'] === 0 ? '<span class="activity-detail-muted">None</span>' : e((string) $entry['vulnerable_total']) . ' <span class="drr-muted">(' . e(disaster_vulnerable_summary($entry['vulnerable'])) . ')</span>' ?></td>
                            <?php if ($can_view_names): ?><td><div class="management-actions drr-actions"><a class="btn btn-sm btn-outline-primary" href="disaster_vulnerable.php?purok=<?= e((string) $purok) ?>">View</a><a class="btn btn-sm btn-outline-secondary" href="disaster_vulnerable_print.php?purok=<?= e((string) $purok) ?>" target="_blank" rel="noopener">Print</a></div></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="document-info-note">Families are the households in the Purok with at least one active member (Households). Vulnerable residents are active senior citizens (60+), children under 5, PWD and solo parents (Residents)<?= disaster_priority_visible() ? ', and residents flagged "Priority" from the Health records' : '' ?>; a resident in two groups is counted in both groups but once in the total.</p>
        </section>

        <section class="dashboard-panel resident-list-panel" style="margin-top: 24px;">
            <h2 class="drr-section-title">Hazard-prone Areas</h2>
            <form class="resident-filters drr-filters" method="get" action="disaster_hazards.php" data-live-search data-live-target="#drr-hazard-results">
                <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="hazard-search">Search</label><input class="activity-filter-input" type="search" id="hazard-search" name="q" value="<?= e($state['q']) ?>" maxlength="100" placeholder="Area or notes" autocomplete="off" data-live-query></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="hazard-type">Hazard</label><select class="activity-filter-select" id="hazard-type" name="hazard"><option value="">All</option><?php foreach (disaster_hazard_types() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['hazard'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="hazard-risk">Risk Level</label><select class="activity-filter-select" id="hazard-risk" name="risk"><option value="">All</option><?php foreach (disaster_risk_levels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['risk'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="hazard-area">Area</label><select class="activity-filter-select" id="hazard-area" name="area"><option value="">All</option><?php foreach ($areas as $area): ?><option value="<?= e((string) $area['id']) ?>" <?= $state['area'] === (int) $area['id'] ? 'selected' : '' ?>><?= e($area['name']) ?><?= (int) $area['is_active'] === 1 ? '' : ' (inactive)' ?></option><?php endforeach; ?></select></div>
                <?php if ($can_manage): ?><div class="resident-filter-group"><label class="activity-filter-label" for="hazard-archived">Show</label><select class="activity-filter-select" id="hazard-archived" name="archived"><option value="">Current</option><option value="1" <?= $state['archived'] ? 'selected' : '' ?>>Archived</option></select></div><?php endif; ?>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="disaster_hazards.php" data-live-reset>Reset</a></div>
            </form>
            <div id="drr-hazard-results" class="live-search-results"><?php $render_results(); ?></div>
        </section>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
