<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/residents.php';
require_once __DIR__ . '/includes/live_search.php';
residents_require_view();
$connection = db();
$per_page = 10;
$search = residents_collapse((string) ($_GET['q'] ?? ''));
$purok_filter = residents_collapse((string) ($_GET['purok'] ?? ''));
$status_filter = (string) ($_GET['status'] ?? '');
if (!array_key_exists($status_filter, residents_status_labels())) $status_filter = '';
$age_filter = (string) ($_GET['age'] ?? '');
if ($age_filter !== 'unknown' && !array_key_exists($age_filter, residents_age_groups())) $age_filter = '';
$sex_filter = (string) ($_GET['sex'] ?? '');
if (!array_key_exists($sex_filter, residents_sex_labels())) $sex_filter = '';
// Sector: PWD / Solo Parent / neither (only once the columns exist).
$sector_ready = residents_sector_ready($connection);
$sector_options = ['pwd' => 'PWD', 'solo_parent' => 'Solo Parent', 'none' => 'Neither'];
$sector_filter = (string) ($_GET['sector'] ?? '');
if (!$sector_ready || !array_key_exists($sector_filter, $sector_options)) $sector_filter = '';
// Which folder group is shown (By Purok / By Age Group). Display only; it never changes which records match.
$view = (string) ($_GET['view'] ?? '');
if (!in_array($view, ['purok', 'age', 'sector'], true) || ($view === 'sector' && !$sector_ready)) $view = $age_filter !== '' && $purok_filter === '' ? 'age' : 'purok';
if (mb_strlen($search) > 100) $search = mb_substr($search, 0, 100);

// Filters are applied in SQL before counting and paging, so totals and pages only ever reflect matching records.
$filters = [];
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $filters['search'] = ["CONCAT_WS(' ', first_name, middle_name, last_name, suffix) LIKE :search_full OR CONCAT_WS(' ', first_name, last_name) LIKE :search_short OR CONCAT_WS(', ', last_name, first_name) LIKE :search_reverse", ['search_full' => $like, 'search_short' => $like, 'search_reverse' => $like]];
}
if ($purok_filter !== '') $filters['purok'] = ['purok = :purok', ['purok' => $purok_filter]];
if ($status_filter !== '') $filters['status'] = ['status = :status', ['status' => $status_filter]];
if ($age_filter !== '') $filters['age'] = [residents_age_group_condition($connection, $age_filter), []];
if ($sex_filter !== '') $filters['sex'] = ['sex = :sex', ['sex' => $sex_filter]];
if ($sector_filter !== '') $filters['sector'] = [['pwd' => 'is_pwd = 1', 'solo_parent' => 'is_solo_parent = 1', 'none' => 'is_pwd = 0 AND is_solo_parent = 0'][$sector_filter], []];
// Safeguard: a Health Worker with assigned Puroks would see only residents of those Puroks, also in the folder counts (they no longer open this page).
[$scope_sql, $scope_params] = residents_purok_scope_sql($connection, 'purok');
if ($scope_params !== []) $filters['scope'] = [$scope_sql, $scope_params];
// WHERE clause and parameters for every active filter except those named in $except (used for folder counts).
$where_for = static function (array $except = []) use ($filters): array {
    $active = array_diff_key($filters, array_flip($except));
    if ($active === []) return ['', []];
    return [' WHERE (' . implode(') AND (', array_column($active, 0)) . ')', array_merge([], ...array_column($active, 1))];
};
[$where_sql, $params] = $where_for();

$count = $connection->prepare('SELECT COUNT(*) FROM residents' . $where_sql);
$count->execute($params);
$total = (int) $count->fetchColumn();
$total_pages = max(1, (int) ceil($total / $per_page));
$page = max(1, min($total_pages, (int) ($_GET['page'] ?? 1)));
$statement = $connection->prepare('SELECT id, first_name, middle_name, last_name, suffix, birth_date, sex, purok, status' . ($sector_ready ? ', is_pwd, is_solo_parent' : '') . ' FROM residents' . $where_sql . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset');
foreach ($params as $key => $value) $statement->bindValue($key, $value);
$statement->bindValue('limit', $per_page, PDO::PARAM_INT);
$statement->bindValue('offset', ($page - 1) * $per_page, PDO::PARAM_INT);
$statement->execute();
$records = $statement->fetchAll();
// Purok filter options: Purok 1–4 plus any older stored values, which are also counted for the data-quality note (all records).
$purok_counts = $connection->query('SELECT purok, COUNT(*) FROM residents GROUP BY purok')->fetchAll(PDO::FETCH_KEY_PAIR);
$purok_filter_options = residents_purok_options();
$other_purok_total = 0;
foreach ($purok_counts as $value => $count) {
    $value = (string) $value;
    if (array_key_exists($value, $purok_filter_options)) continue;
    $other_purok_total += (int) $count;
    if ($value !== '') $purok_filter_options[$value] = $value;
}
// Folder counts follow the other active filters (search, sex, status and the other folder group), so a folder shows how many
// residents it would list right now. Each group ignores its own filter so every folder in it stays comparable.
[$purok_where, $purok_params] = $where_for(['purok']);
$statement = $connection->prepare('SELECT purok, COUNT(*) FROM residents' . $purok_where . ' GROUP BY purok');
$statement->execute($purok_params);
$purok_folder_counts = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
$purok_folders = [];
foreach (residents_purok_options() as $value => $label) $purok_folders[(string) $value] = ['label' => $label, 'count' => (int) ($purok_folder_counts[$value] ?? 0)];
[$age_where, $age_params] = $where_for(['age']);
$age_folder_counts = residents_age_group_counts($connection, $age_where, $age_params);
$age_folders = [];
foreach (residents_age_groups() as $key => $group) $age_folders[$key] = ['label' => $group['label'], 'detail' => $group['range'], 'count' => $age_folder_counts[$key] ?? 0];
// Sector folders: PWD, Solo Parent, Neither (counts follow the other filters).
$sector_folders = [];
if ($sector_ready) {
    [$sector_where, $sector_params] = $where_for(['sector']);
    $statement = $connection->prepare('SELECT COALESCE(SUM(is_pwd = 1), 0) AS pwd, COALESCE(SUM(is_solo_parent = 1), 0) AS solo_parent, COALESCE(SUM(is_pwd = 0 AND is_solo_parent = 0), 0) AS none FROM residents' . $sector_where);
    $statement->execute($sector_params);
    $sector_counts = $statement->fetch();
    foreach ($sector_options as $key => $label) $sector_folders[$key] = ['label' => $label, 'count' => (int) $sector_counts[$key]];
}
$folder_counts = ['purok' => array_map(static fn (array $f): int => $f['count'], $purok_folders), 'age' => array_map(static fn (array $f): int => $f['count'], $age_folders), 'sector' => array_map(static fn (array $f): int => $f['count'], $sector_folders)];
// Profiles without a birthdate (all records) for the data-quality note.
$unknown_age_total = residents_age_group_counts($connection)['unknown'] ?? 0;
// Plain-language list of the active filters, shown above the results so combined filters are never hidden by the VIEW switch.
$active_filter_labels = array_values(array_filter([
    $search !== '' ? 'Name contains "' . $search . '"' : '',
    $purok_filter !== '' ? residents_purok_label($purok_filter) : '',
    $age_filter !== '' ? ($age_filter === 'unknown' ? 'Unknown age' : residents_age_groups()[$age_filter]['label'] . ' (' . residents_age_groups()[$age_filter]['range'] . ')') : '',
    $sex_filter !== '' ? residents_sex_labels()[$sex_filter] : '',
    $status_filter !== '' ? residents_status_labels()[$status_filter] : '',
    $sector_filter !== '' ? ($sector_filter === 'none' ? 'Neither PWD nor Solo Parent' : $sector_options[$sector_filter]) : '',
]));
$can_manage = residents_can_manage();
$page_url = static fn (int $target): string => 'residents.php?' . http_build_query(array_filter(['q' => $search, 'purok' => $purok_filter, 'status' => $status_filter, 'age' => $age_filter, 'sex' => $sex_filter, 'sector' => $sector_filter, 'view' => $view === 'purok' ? '' : $view, 'page' => $target > 1 ? $target : null], static fn ($value): bool => $value !== null && $value !== ''));
$first_shown = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last_shown = min($total, $page * $per_page);

// Results markup shared by the full page and Live Search responses (same authorized query and dataset for table and cards).
$render_results = static function () use ($records, $active_filter_labels, $folder_counts, $first_shown, $last_shown, $total, $can_manage, $total_pages, $page, $page_url): void { ?>
        <?php // Updated folder counts travel with every Live Search response; app.js applies them to the folders (data-folder-counts). ?>
        <div hidden data-folder-counts="<?= e(json_encode($folder_counts, JSON_THROW_ON_ERROR)) ?>"></div>
        <?php if ($active_filter_labels !== []): ?>
            <p class="resident-active-filters"><span>Filtered by:</span><?php foreach ($active_filter_labels as $label): ?> <strong><?= e($label) ?></strong><?php endforeach; ?></p>
        <?php endif; ?>
    <?php if ($records === []): ?>
            <div class="dashboard-empty-state"><?= $active_filter_labels !== [] ? ($total === 0 && count($active_filter_labels) > 1 ? 'No residents match all of the selected filters together. Remove a filter or select Reset to see more.' : 'No matching records found.') : 'No resident profiles have been registered yet.' ?></div>
        <?php else: ?>
            <p class="activity-history-meta">Showing <?= e((string) $first_shown) ?>–<?= e((string) $last_shown) ?> of <?= e((string) $total) ?> resident<?= $total === 1 ? '' : 's' ?></p>
            <div class="resident-table-wrap">
                <table class="resident-table">
                    <thead><tr><th scope="col">Resident ID</th><th scope="col">Full Name</th><th scope="col">Sex</th><th scope="col">Age</th><th scope="col">Purok</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($records as $record): $age = residents_age($record['birth_date']); ?>
                        <tr>
                            <td class="resident-id">#<?= e((string) $record['id']) ?></td>
                            <td class="resident-name"><?= e(residents_full_name($record)) ?><?= residents_sector_tags($record) ?></td>
                            <td><?= e(residents_sex_labels()[$record['sex']] ?? 'Unspecified') ?></td>
                            <td><?= $age === null ? '<span class="activity-detail-muted">Unknown</span>' : e((string) $age) ?></td>
                            <td><?= e(residents_purok_label($record['purok'])) ?></td>
                            <td><?= residents_status_badge($record['status']) ?></td>
                            <td><div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="resident_view.php?id=<?= e((string) $record['id']) ?>">View</a><?php if ($can_manage): ?><a class="btn btn-sm btn-outline-primary" href="resident_form.php?id=<?= e((string) $record['id']) ?>">Edit</a><?php endif; ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="resident-cards">
                <?php foreach ($records as $record): $age = residents_age($record['birth_date']); ?>
                    <li class="resident-card">
                        <div class="resident-card-top"><strong><?= e(residents_full_name($record)) ?><?= residents_sector_tags($record) ?></strong><?= residents_status_badge($record['status']) ?></div>
                        <p>#<?= e((string) $record['id']) ?> · <?= e(residents_sex_labels()[$record['sex']] ?? 'Unspecified') ?> · <?= $age === null ? 'Age unknown' : e((string) $age) . ' yrs' ?></p>
                        <p><?= e(residents_purok_label($record['purok'])) ?></p>
                        <div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="resident_view.php?id=<?= e((string) $record['id']) ?>">View</a><?php if ($can_manage): ?><a class="btn btn-sm btn-outline-primary" href="resident_form.php?id=<?= e((string) $record['id']) ?>">Edit</a><?php endif; ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($total_pages > 1): ?>
                <nav class="activity-pagination" aria-label="Resident list pagination">
                    <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($page_url($page - 1)) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                    <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                    <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($page_url($page + 1)) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif;
};
if (live_search_is_request()) live_search_respond($render_results);

$page_title = 'Residents'; $active_page = 'residents';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Residents</h1><p>Resident profiles registered in Barangay San Jose.</p></div>
        <?php if ($can_manage): ?><a class="btn btn-primary announcements-new-btn" href="resident_form.php">Add Resident</a><?php endif; ?>
    </div>
    <?php if ($success = flash('resident_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('resident_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters" method="get" action="residents.php" data-live-search data-live-target="#resident-results">
            <input type="hidden" name="view" value="<?= e($view) ?>" data-folder-view-input>
            <div class="resident-filter-group resident-filter-search">
                <label class="activity-filter-label" for="resident-search">Search name</label>
                <input class="activity-filter-input" type="search" id="resident-search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="First or last name" autocomplete="off" data-live-query>
            </div>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="resident-purok">Purok</label>
                <select class="activity-filter-select" id="resident-purok" name="purok">
                    <option value="">All Puroks</option>
                    <?php foreach ($purok_filter_options as $value => $label): ?><option value="<?= e((string) $value) ?>" <?= $purok_filter === (string) $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="resident-age">Age group</label>
                <select class="activity-filter-select" id="resident-age" name="age">
                    <option value="">All Ages</option>
                    <?php foreach (residents_age_groups() as $value => $group): ?><option value="<?= e($value) ?>" <?= $age_filter === $value ? 'selected' : '' ?>><?= e($group['label'] . ' (' . $group['range'] . ')') ?></option><?php endforeach; ?>
                    <option value="unknown" <?= $age_filter === 'unknown' ? 'selected' : '' ?>>Unknown age (no birthdate)</option>
                </select>
            </div>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="resident-sex">Sex</label>
                <select class="activity-filter-select" id="resident-sex" name="sex">
                    <option value="">All</option>
                    <?php foreach (residents_sex_labels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $sex_filter === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="resident-status">Status</label>
                <select class="activity-filter-select" id="resident-status" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach (residents_status_labels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $status_filter === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php if ($sector_ready): ?>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="resident-sector">Sector</label>
                <select class="activity-filter-select" id="resident-sector" name="sector">
                    <option value="">All</option>
                    <?php foreach ($sector_options as $value => $label): ?><option value="<?= e($value) ?>" <?= $sector_filter === $value ? 'selected' : '' ?>><?= e($value === 'none' ? 'Neither PWD nor Solo Parent' : $label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="activity-filter-actions">
                <button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button>
                <a class="btn btn-outline-secondary btn-sm" href="residents.php" data-live-reset>Reset</a>
            </div>
        </form>
        <?php
        // Folder groups (Purok, Age Group), one shown at a time through the VIEW switch. Each folder sets its filter; the active folder
        // clears it. The links keep the other filters, so they also work without JavaScript; with Live Search the list reloads in place
        // (see app.js, data-filter-folders and data-folder-view-switch).
        $folder_url = static function (array $changes) use ($search, $purok_filter, $status_filter, $age_filter, $sex_filter, $sector_filter, $view): string {
            $query = array_filter(array_merge(['q' => $search, 'purok' => $purok_filter, 'status' => $status_filter, 'age' => $age_filter, 'sex' => $sex_filter, 'sector' => $sector_filter, 'view' => $view], $changes), static fn ($item): bool => $item !== '');
            if (($query['view'] ?? '') === 'purok') unset($query['view']); // the default view needs no parameter
            return 'residents.php' . ($query === [] ? '' : '?' . http_build_query($query));
        };
        $render_folders = static function (string $param, string $select_id, string $heading, string $modifier, array $folders, string $active_value) use ($folder_url): void { ?>
            <nav class="filter-folders <?= e($modifier) ?>" aria-label="<?= e($heading) ?>" data-filter-folders="#<?= e($select_id) ?>">
                <?php foreach ($folders as $value => $folder): $value = (string) $value; $active = $active_value === $value; ?>
                    <a class="filter-folder<?= $active ? ' is-active' : '' ?>" href="<?= e($folder_url([$param => $active ? '' : $value])) ?>" data-folder-value="<?= e($value) ?>" <?= $active ? 'aria-current="true"' : '' ?>>
                        <span class="filter-folder-icon" aria-hidden="true"><?= icon_svg('folder') ?></span>
                        <span class="filter-folder-text"><strong><?= e($folder['label']) ?></strong><?php if (isset($folder['detail'])): ?><em><?= e($folder['detail']) ?></em><?php endif; ?><small><?= e((string) $folder['count']) ?> resident<?= $folder['count'] === 1 ? '' : 's' ?></small></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php };
        $views = ['purok' => ['label' => 'By Purok', 'icon' => 'map-pin'], 'age' => ['label' => 'By Age Group', 'icon' => 'layers']] + ($sector_ready ? ['sector' => ['label' => 'By Sector', 'icon' => 'badge']] : []);
        ?>
        <div class="folder-view-bar">
            <div class="folder-view-switch" role="tablist" aria-label="Organize residents" data-folder-view-switch>
                <span class="folder-view-label" aria-hidden="true">View:</span>
                <?php foreach ($views as $key => $option): $selected = $view === $key; ?>
                    <a class="folder-view-btn<?= $selected ? ' is-active' : '' ?>" href="<?= e($folder_url(['view' => $key])) ?>" role="tab" id="folder-view-tab-<?= e($key) ?>" aria-controls="folder-view-<?= e($key) ?>" aria-selected="<?= $selected ? 'true' : 'false' ?>" data-folder-view="<?= e($key) ?>"><span aria-hidden="true"><?= icon_svg($option['icon']) ?></span><?= e($option['label']) ?></a>
                <?php endforeach; ?>
            </div>
            <span class="folder-view-hint">Select a folder to show its residents; select it again to show all.</span>
        </div>
        <div class="folder-view-panel" id="folder-view-purok" role="tabpanel" aria-labelledby="folder-view-tab-purok" data-folder-view-panel="purok" <?= $view === 'purok' ? '' : 'hidden' ?>>
            <?php $render_folders('purok', 'resident-purok', 'Residents by Purok', 'is-purok', $purok_folders, $purok_filter); ?>
            <?php if ($other_purok_total > 0): ?><p class="filter-folders-note"><?= e((string) $other_purok_total) ?> resident<?= $other_purok_total === 1 ? ' has' : 's have' ?> a Purok outside Purok 1–4. Edit <?= $other_purok_total === 1 ? 'that profile' : 'those profiles' ?> to choose the correct Purok.</p><?php endif; ?>
        </div>
        <div class="folder-view-panel" id="folder-view-age" role="tabpanel" aria-labelledby="folder-view-tab-age" data-folder-view-panel="age" <?= $view === 'age' ? '' : 'hidden' ?>>
            <?php $render_folders('age', 'resident-age', 'Residents by age group', 'is-age', $age_folders, $age_filter); ?>
            <?php if ($unknown_age_total > 0): ?><p class="filter-folders-note"><?= e((string) $unknown_age_total) ?> resident<?= $unknown_age_total === 1 ? ' has' : 's have' ?> no recorded birthdate and <?= $unknown_age_total === 1 ? 'is' : 'are' ?> not in any age group. Choose "Unknown age" in the Age group filter to list <?= $unknown_age_total === 1 ? 'that profile' : 'those profiles' ?>.</p><?php endif; ?>
        </div>
        <?php if ($sector_ready): ?>
        <div class="folder-view-panel" id="folder-view-sector" role="tabpanel" aria-labelledby="folder-view-tab-sector" data-folder-view-panel="sector" <?= $view === 'sector' ? '' : 'hidden' ?>>
            <?php $render_folders('sector', 'resident-sector', 'Residents by sector', 'is-sector', $sector_folders, $sector_filter); ?>
            <p class="filter-folders-note">PWD and Solo Parent come from the Sector checkboxes on each resident profile. A resident can be in both.</p>
        </div>
        <?php endif; ?>
        <div id="resident-results" class="live-search-results"><?php $render_results(); ?></div>
    </section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
