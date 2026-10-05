<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_prep.php';
require_once __DIR__ . '/includes/live_search.php';
disaster_require_view();
$can_manage = disaster_can_manage();   // Secretary views only (Health Workers, the Treasurer and residents use Disaster Info)
$connection = db();
$ready = disaster_prep_ready($connection);

// BDRRMC members (by committee role) and emergency hotlines, on one page. Search, role and Archived filters.
$state = ['q' => mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100), 'role' => (string) ($_GET['role'] ?? ''), 'archived' => (string) ($_GET['archived'] ?? '') === '1'];
if (!array_key_exists($state['role'], disaster_member_roles())) $state['role'] = '';
$members = [];
$hotlines = [];
if ($ready) {
    $where = [$state['archived'] ? 'archived_at IS NOT NULL' : 'archived_at IS NULL'];
    $params = [];
    if ($state['q'] !== '') {
        $like = '%' . addcslashes($state['q'], '%_\\') . '%';
        $where[] = '(name LIKE :q1 OR position LIKE :q2 OR contact_number LIKE :q3 OR notes LIKE :q4)';
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
    }
    $member_where = $where;
    if ($state['role'] !== '') { $member_where[] = 'member_role = :role'; }
    $statement = $connection->prepare("SELECT * FROM drr_contacts WHERE contact_type = 'member' AND " . implode(' AND ', $member_where) . " ORDER BY FIELD(member_role, 'rescue', 'relief', 'medical', 'communication', 'security', 'other'), name");
    $statement->execute($params + ($state['role'] !== '' ? ['role' => $state['role']] : []));
    $members = $statement->fetchAll();
    if ($state['role'] === '') {
        $statement = $connection->prepare("SELECT * FROM drr_contacts WHERE contact_type = 'hotline' AND " . implode(' AND ', $where) . " ORDER BY FIELD(hotline_category, 'mdrrmo', 'bfp', 'pnp', 'hospital', 'other'), name");
        $statement->execute($params);
        $hotlines = $statement->fetchAll();
    }
}
$return = http_build_query(array_filter(['q' => $state['q'], 'role' => $state['role'], 'archived' => $state['archived'] ? '1' : ''], static fn ($v): bool => $v !== ''));
$filtered = $state['q'] !== '' || $state['role'] !== '' || $state['archived'];

$archive_button = static function (array $row) use ($return): string {
    $archived = $row['archived_at'] !== null;
    return '<form method="post" action="disaster_prep_action.php" class="doc-action-form">' . csrf_field()
        . '<input type="hidden" name="kind" value="contact"><input type="hidden" name="id" value="' . e((string) $row['id']) . '"><input type="hidden" name="action" value="' . ($archived ? 'restore' : 'archive') . '"><input type="hidden" name="return" value="' . e($return) . '">'
        . '<button class="btn btn-sm ' . ($archived ? 'btn-outline-primary' : 'btn-outline-danger') . '" type="submit" data-form-confirm="custom" data-dialog-heading="' . ($archived ? 'Restore this contact?' : 'Archive this contact?') . '" data-dialog-message="' . e($archived ? $row['name'] . ' will appear in the list again.' : $row['name'] . ' will be hidden from the list. The record is kept and can be restored.') . '" data-dialog-confirm="' . ($archived ? 'Restore' : 'Archive') . '" data-dialog-dismiss="Cancel"' . ($archived ? '' : ' data-dialog-danger="true"') . '>' . ($archived ? 'Restore' : 'Archive') . '</button></form>';
};
$actions = static fn (array $row): string => '<div class="management-actions drr-actions">' . (!$can_manage ? '' : ($row['archived_at'] === null ? '<a class="btn btn-sm btn-outline-primary" href="disaster_contact_form.php?id=' . e((string) $row['id']) . '">Edit</a>' : '') . $archive_button($row)) . '</div>';
$numbers = static fn (array $row): string => '<a class="activity-detail-link drr-nowrap" href="tel:' . e(preg_replace('/[^0-9+]/', '', $row['contact_number']) ?? '') . '">' . e($row['contact_number']) . '</a>' . ($row['alternate_number'] ? '<span class="drr-sub drr-muted">Alt: ' . e($row['alternate_number']) . '</span>' : '');

$render_table = static function (array $rows, string $kind) use ($actions, $numbers): void {
    $member = $kind === 'member'; ?>
    <div class="resident-table-wrap">
        <table class="resident-table drr-table">
            <thead><tr><th scope="col"><?= $member ? 'Name' : 'Office / Hotline' ?></th><th scope="col"><?= $member ? 'Role' : 'Category' ?></th><th scope="col"><?= $member ? 'Position' : 'Notes' ?></th><th scope="col">Contact Number</th><th scope="col" class="drr-actions-head">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="resident-name"><?= e($row['name']) ?><?= $row['archived_at'] !== null ? '<span class="drr-sub"><span class="resident-status resident-status-inactive">Archived</span></span>' : '' ?></td>
                    <td><?= e($member ? (disaster_member_roles()[$row['member_role']] ?? '') : (disaster_hotline_categories()[$row['hotline_category']] ?? '')) ?></td>
                    <td><?= e((string) ($member ? $row['position'] : $row['notes'])) ?><?= $member && $row['notes'] ? '<span class="drr-sub drr-muted">' . e($row['notes']) . '</span>' : '' ?></td>
                    <td><?= $numbers($row) ?></td>
                    <td><?= $actions($row) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <ul class="resident-cards">
        <?php foreach ($rows as $row): ?>
            <li class="resident-card">
                <div class="resident-card-top"><strong><?= e($row['name']) ?></strong><span class="drr-muted"><?= e($member ? (disaster_member_roles()[$row['member_role']] ?? '') : (disaster_hotline_categories()[$row['hotline_category']] ?? '')) ?></span></div>
                <?php if ($member ? $row['position'] : $row['notes']): ?><p><?= e((string) ($member ? $row['position'] : $row['notes'])) ?></p><?php endif; ?>
                <p><?= $numbers($row) ?></p>
                <?= $actions($row) ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php };

$render_results = static function () use ($members, $hotlines, $filtered, $state, $render_table): void { ?>
    <section class="drr-section">
        <h2 class="drr-section-title">BDRRMC Members <span class="drr-count"><?= e((string) count($members)) ?></span></h2>
        <?php if ($members === []): ?><div class="dashboard-empty-state"><?= $filtered ? 'No matching members found.' : 'No BDRRMC members yet. Select Add Contact to record the first one.' ?></div><?php else: $render_table($members, 'member'); endif; ?>
    </section>
    <?php if ($state['role'] === ''): ?>
        <section class="drr-section">
            <h2 class="drr-section-title">Emergency Hotlines <span class="drr-count"><?= e((string) count($hotlines)) ?></span></h2>
            <?php if ($hotlines === []): ?><div class="dashboard-empty-state"><?= $filtered ? 'No matching hotlines found.' : 'No hotlines yet. Add MDRRMO, BFP, PNP, hospitals and other emergency numbers.' ?></div><?php else: $render_table($hotlines, 'hotline'); endif; ?>
        </section>
    <?php endif;
};
if ($ready && live_search_is_request()) live_search_respond($render_results);

$page_title = 'Members & Hotlines'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Disaster Management</h1><p>BDRRMC members and emergency contact numbers.</p></div>
        <?php if ($ready): ?><div class="resident-detail-actions"><?php if ($can_manage): ?><a class="btn btn-primary announcements-new-btn" href="disaster_contact_form.php">Add Contact</a><?php endif; ?><a class="btn btn-light resident-action-btn" href="disaster_hotlines_print.php" target="_blank" rel="noopener">Print Hotlines Poster</a></div><?php endif; ?>
    </div>
    <?= disaster_tabs('contacts') ?>
    <?php if ($success = flash('disaster_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('disaster_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Members and hotlines need their database table before they can be recorded. No records have been changed.</div>
    <?php else: ?>
        <section class="dashboard-panel resident-list-panel">
            <form class="resident-filters drr-filters" method="get" action="disaster_contacts.php" data-live-search data-live-target="#drr-contact-results">
                <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="contact-search">Search</label><input class="activity-filter-input" type="search" id="contact-search" name="q" value="<?= e($state['q']) ?>" maxlength="100" placeholder="Name, position or number" autocomplete="off" data-live-query></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="contact-role">Member Role</label><select class="activity-filter-select" id="contact-role" name="role"><option value="">All (members and hotlines)</option><?php foreach (disaster_member_roles() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="contact-archived">Show</label><select class="activity-filter-select" id="contact-archived" name="archived"><option value="">Current</option><option value="1" <?= $state['archived'] ? 'selected' : '' ?>>Archived</option></select></div>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="disaster_contacts.php" data-live-reset>Reset</a></div>
            </form>
            <div id="drr-contact-results" class="live-search-results"><?php $render_results(); ?></div>
        </section>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
