<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/households.php';
require_once __DIR__ . '/includes/live_search.php';
households_require_view();
$connection = db();
$per_page = 10;
$search = residents_collapse((string) ($_GET['q'] ?? ''));
if (mb_strlen($search) > 100) $search = mb_substr($search, 0, 100);
$purok_filter = residents_collapse((string) ($_GET['purok'] ?? ''));
$occupancy_filter = (string) ($_GET['occupancy'] ?? '');
if (!in_array($occupancy_filter, ['occupied', 'unoccupied'], true)) $occupancy_filter = '';

// Filters are applied in SQL before counting and paging; occupancy is derived, never stored.
$where = [];
$params = [];
if ($search !== '') {
    $where[] = 'h.household_no LIKE :search_no OR h.address LIKE :search_address';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $params += ['search_no' => $like, 'search_address' => $like];
}
if ($purok_filter !== '') { $where[] = 'h.purok = :purok'; $params['purok'] = $purok_filter; }
// Safeguard: a Health Worker with assigned Puroks would see only households of those Puroks (they no longer open this page).
[$scope_sql, $scope_params] = residents_purok_scope_sql($connection, 'h.purok');
if ($scope_params !== []) { $where[] = $scope_sql; $params += $scope_params; }
if ($occupancy_filter !== '') $where[] = ($occupancy_filter === 'occupied' ? '' : 'NOT ') . "EXISTS (SELECT 1 FROM resident_households rh_o INNER JOIN residents r_o ON r_o.id = rh_o.resident_id WHERE rh_o.household_id = h.id AND rh_o.is_primary = 1 AND rh_o.left_at IS NULL AND r_o.status = 'active')";
$where_sql = $where === [] ? '' : ' WHERE (' . implode(') AND (', $where) . ')';

$count = $connection->prepare('SELECT COUNT(*) FROM households h' . $where_sql);
$count->execute($params);
$total = (int) $count->fetchColumn();
$total_pages = max(1, (int) ceil($total / $per_page));
$page = max(1, min($total_pages, (int) ($_GET['page'] ?? 1)));
$statement = $connection->prepare('SELECT h.id, h.household_no, h.purok, h.address, h.household_head_resident_id, hr.first_name, hr.middle_name, hr.last_name, hr.suffix, ' . households_active_members_sql() . ' AS active_members FROM households h LEFT JOIN residents hr ON hr.id = h.household_head_resident_id' . $where_sql . ' ORDER BY h.household_no ASC, h.id ASC LIMIT :limit OFFSET :offset');
foreach ($params as $key => $value) $statement->bindValue($key, $value);
$statement->bindValue('limit', $per_page, PDO::PARAM_INT);
$statement->bindValue('offset', ($page - 1) * $per_page, PDO::PARAM_INT);
$statement->execute();
$records = $statement->fetchAll();
$puroks = $connection->query("SELECT DISTINCT purok FROM households WHERE purok <> '' ORDER BY purok")->fetchAll(PDO::FETCH_COLUMN);
$any_households = $total > 0 || (int) $connection->query('SELECT COUNT(*) FROM households')->fetchColumn() > 0;
$can_manage = households_can_manage();
$page_url = static fn (int $target): string => 'households.php?' . http_build_query(array_filter(['q' => $search, 'purok' => $purok_filter, 'occupancy' => $occupancy_filter, 'page' => $target > 1 ? $target : null], static fn ($value): bool => $value !== null && $value !== ''));
$head_name = static fn (array $row): string => $row['household_head_resident_id'] ? e(residents_full_name($row)) : '<span class="resident-status resident-status-pending">No household head</span>';
// Delete is offered only for households that nothing refers to (households_can_delete()).
$delete_button = static function (array $row) use ($can_manage, $connection): string {
    if (!$can_manage) return '';
    $form = '<form method="post" action="household_delete.php" class="doc-action-form">' . csrf_field() . '<input type="hidden" name="id" value="' . e((string) $row['id']) . '">';
    // A household that cannot be deleted posts without a confirmation; the page then shows why and what to do instead.
    if (!households_can_delete($connection, $row)) return $form . '<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form>';
    return $form . '<button class="btn btn-sm btn-outline-danger" type="submit" data-form-confirm="custom" data-dialog-heading="Delete this household?" data-dialog-message="' . e('Household ' . $row['household_no'] . ' has no members or records. It will be removed; the deletion is kept in the audit log.') . '" data-dialog-confirm="Delete Household" data-dialog-dismiss="Cancel" data-dialog-danger="true">Delete</button></form>';
};
$first_shown = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last_shown = min($total, $page * $per_page);

// Results markup shared by the full page and Live Search responses (same authorized query and dataset for table and cards).
$render_results = static function () use ($records, $any_households, $first_shown, $last_shown, $total, $can_manage, $total_pages, $page, $page_url, $head_name, $delete_button): void {
    if ($records === []): ?>
            <div class="dashboard-empty-state"><?= $any_households ? 'No matching records found.' : 'No households have been registered yet.' ?></div>
        <?php else: ?>
            <p class="activity-history-meta">Showing <?= e((string) $first_shown) ?>–<?= e((string) $last_shown) ?> of <?= e((string) $total) ?> household<?= $total === 1 ? '' : 's' ?></p>
            <div class="resident-table-wrap">
                <table class="resident-table">
                    <thead><tr><th scope="col">Household No.</th><th scope="col">Purok</th><th scope="col">Address</th><th scope="col">Household Head</th><th scope="col">Active Members</th><th scope="col">Occupancy</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td class="resident-name"><?= e($record['household_no']) ?></td>
                            <td><?= e($record['purok']) ?></td>
                            <td class="household-address"><?= e($record['address']) ?></td>
                            <td><?= $head_name($record) ?></td>
                            <td><?= e((string) $record['active_members']) ?></td>
                            <td><?= households_occupancy_badge((int) $record['active_members']) ?></td>
                            <td><div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="household_view.php?id=<?= e((string) $record['id']) ?>">View</a><?php if ($can_manage): ?><a class="btn btn-sm btn-outline-primary" href="household_form.php?id=<?= e((string) $record['id']) ?>">Edit</a><?php endif; ?><?= $delete_button($record) ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="resident-cards">
                <?php foreach ($records as $record): ?>
                    <li class="resident-card">
                        <div class="resident-card-top"><strong><?= e($record['household_no']) ?></strong><?= households_occupancy_badge((int) $record['active_members']) ?></div>
                        <p><?= e($record['purok']) ?> · <?= e($record['address']) ?></p>
                        <p>Head: <?= $head_name($record) ?> · <?= e((string) $record['active_members']) ?> active member<?= (int) $record['active_members'] === 1 ? '' : 's' ?></p>
                        <div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="household_view.php?id=<?= e((string) $record['id']) ?>">View</a><?php if ($can_manage): ?><a class="btn btn-sm btn-outline-primary" href="household_form.php?id=<?= e((string) $record['id']) ?>">Edit</a><?php endif; ?><?= $delete_button($record) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($total_pages > 1): ?>
                <nav class="activity-pagination" aria-label="Household list pagination">
                    <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($page_url($page - 1)) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                    <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                    <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($page_url($page + 1)) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif;
};
if (live_search_is_request()) live_search_respond($render_results);

// Residents without a household: Active or Pending residents with no current primary membership (for example new
// online registrations). Staff assign them through Manage Household (resident_household.php). 10 per page (?np=).
$no_household_where = "r.status IN ('active', 'pending') AND NOT EXISTS (SELECT 1 FROM resident_households rh_n WHERE rh_n.resident_id = r.id AND rh_n.is_primary = 1 AND rh_n.left_at IS NULL)";
$no_household_scope = residents_purok_scope($connection);
if ($no_household_scope !== null) $no_household_where .= ' AND r.purok IN (' . implode(', ', array_map([$connection, 'quote'], $no_household_scope)) . ')';
$no_household_total = (int) $connection->query("SELECT COUNT(*) FROM residents r WHERE $no_household_where")->fetchColumn();
$no_household_pages = max(1, (int) ceil($no_household_total / $per_page));
$no_household_page = max(1, min($no_household_pages, (int) ($_GET['np'] ?? 1)));
$statement = $connection->prepare("SELECT r.id, r.first_name, r.middle_name, r.last_name, r.suffix, r.purok, r.address, r.status FROM residents r WHERE $no_household_where ORDER BY r.status = 'pending' DESC, r.purok, r.last_name, r.first_name, r.id LIMIT :limit OFFSET :offset");
$statement->bindValue('limit', $per_page, PDO::PARAM_INT);
$statement->bindValue('offset', ($no_household_page - 1) * $per_page, PDO::PARAM_INT);
$statement->execute();
$no_household = $statement->fetchAll();
$can_view_residents = can_access_navigation('residents');
$no_household_url = static fn (int $target): string => 'households.php?' . http_build_query(array_filter(['q' => $search, 'purok' => $purok_filter, 'occupancy' => $occupancy_filter, 'page' => $page > 1 ? $page : null, 'np' => $target > 1 ? $target : null], static fn ($value): bool => $value !== null && $value !== '')) . '#no-household';
$no_household_action = static fn (array $row): string => '<div class="management-actions">' . ($can_manage ? '<a class="btn btn-sm btn-outline-primary" href="resident_household.php?id=' . e((string) $row['id']) . '">Add to Household</a>' : '') . ($can_view_residents ? '<a class="btn btn-sm btn-outline-primary" href="resident_view.php?id=' . e((string) $row['id']) . '">View</a>' : '') . '</div>';

$page_title = 'Households'; $active_page = 'households';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Households</h1><p>Registered households in Barangay San Jose and their current occupancy.</p></div>
        <?php if ($can_manage): ?><a class="btn btn-primary announcements-new-btn" href="household_form.php">Add Household</a><?php endif; ?>
    </div>
    <?php if ($success = flash('household_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('household_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters" method="get" action="households.php" data-live-search data-live-target="#household-results">
            <div class="resident-filter-group resident-filter-search">
                <label class="activity-filter-label" for="household-search">Search</label>
                <input class="activity-filter-input" type="search" id="household-search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Household number or address" autocomplete="off" data-live-query>
            </div>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="household-purok">Purok</label>
                <select class="activity-filter-select" id="household-purok" name="purok">
                    <option value="">All Puroks</option>
                    <?php foreach ($puroks as $purok): ?><option value="<?= e($purok) ?>" <?= $purok_filter === $purok ? 'selected' : '' ?>><?= e($purok) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="household-occupancy">Occupancy</label>
                <select class="activity-filter-select" id="household-occupancy" name="occupancy">
                    <option value="">All</option>
                    <option value="occupied" <?= $occupancy_filter === 'occupied' ? 'selected' : '' ?>>Occupied</option>
                    <option value="unoccupied" <?= $occupancy_filter === 'unoccupied' ? 'selected' : '' ?>>Unoccupied</option>
                </select>
            </div>
            <div class="activity-filter-actions">
                <button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button>
                <a class="btn btn-outline-secondary btn-sm" href="households.php" data-live-reset>Reset</a>
            </div>
        </form>

        <div id="household-results" class="live-search-results"><?php $render_results(); ?></div>
    </section>

    <section class="dashboard-panel resident-list-panel" id="no-household" aria-labelledby="no-household-title" style="margin-top: 24px;">
        <div class="panel-heading"><h2 id="no-household-title">Residents without a household <span class="resident-tag"><?= e((string) $no_household_total) ?></span></h2></div>
        <p class="activity-history-meta">Active and Pending residents who are not yet a member of any household, such as new online registrations.<?= $can_manage ? ' Select Add to Household to choose an existing household or create a new one.' : '' ?></p>
        <?php if ($no_household === []): ?>
            <div class="dashboard-empty-state">Every active and pending resident belongs to a household.</div>
        <?php else: ?>
            <div class="resident-table-wrap">
                <table class="resident-table">
                    <thead><tr><th scope="col">Resident ID</th><th scope="col">Full Name</th><th scope="col">Purok</th><th scope="col">Address</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($no_household as $row): ?>
                        <tr>
                            <td>#<?= e((string) $row['id']) ?></td>
                            <td class="resident-name"><?= e(residents_full_name($row)) ?></td>
                            <td><?= e(residents_purok_label($row['purok'])) ?></td>
                            <td class="household-address"><?= e($row['address']) ?></td>
                            <td><?= residents_status_badge($row['status']) ?></td>
                            <td><?= $no_household_action($row) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="resident-cards">
                <?php foreach ($no_household as $row): ?>
                    <li class="resident-card">
                        <div class="resident-card-top"><strong><?= e(residents_full_name($row)) ?></strong><?= residents_status_badge($row['status']) ?></div>
                        <p>#<?= e((string) $row['id']) ?> · <?= e(residents_purok_label($row['purok'])) ?> · <?= e($row['address']) ?></p>
                        <?= $no_household_action($row) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($no_household_pages > 1): ?>
                <nav class="activity-pagination" aria-label="Residents without a household pagination">
                    <?php if ($no_household_page > 1): ?><a class="activity-page-btn" href="<?= e($no_household_url($no_household_page - 1)) ?>">&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                    <span class="activity-page-info">Page <?= e((string) $no_household_page) ?> of <?= e((string) $no_household_pages) ?></span>
                    <?php if ($no_household_page < $no_household_pages): ?><a class="activity-page-btn" href="<?= e($no_household_url($no_household_page + 1)) ?>">Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
