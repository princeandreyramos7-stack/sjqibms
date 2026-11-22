<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/projects.php';
require_once __DIR__ . '/includes/live_search.php';
projects_require_view();
$connection = db();
$ready = projects_ready($connection);
$details = $ready && projects_details_ready($connection);
$can_manage = projects_can_manage();

// Projects list: search and status filter run in SQL; Ongoing and Planned first, then by start date.
$per_page = 10;
$search = mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100);
$status_filter = (string) ($_GET['status'] ?? '');
if (!array_key_exists($status_filter, projects_status_labels())) $status_filter = '';
$records = [];
$total = 0;
$counts = array_fill_keys(array_keys(projects_status_labels()), 0);
$page = 1;
$total_pages = 1;
if ($ready) {
    $where = [];
    $params = [];
    if ($search !== '') {
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where[] = '(title LIKE :q1 OR project_code LIKE :q2 OR description LIKE :q3' . ($details ? ' OR category LIKE :q4' : '') . ')';
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like] + ($details ? ['q4' => $like] : []);
    }
    if ($status_filter !== '') { $where[] = 'status = :status'; $params['status'] = $status_filter; }
    $where_sql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $count = $connection->prepare('SELECT COUNT(*) FROM barangay_projects' . $where_sql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $total_pages = max(1, (int) ceil($total / $per_page));
    $page = max(1, min($total_pages, (int) ($_GET['page'] ?? 1)));
    $statement = $connection->prepare('SELECT * FROM barangay_projects' . $where_sql . " ORDER BY FIELD(status, 'ongoing', 'planned', 'suspended', 'completed', 'cancelled'), start_date IS NULL, start_date DESC, id DESC LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) $statement->bindValue($key, $value);
    $statement->bindValue('limit', $per_page, PDO::PARAM_INT);
    $statement->bindValue('offset', ($page - 1) * $per_page, PDO::PARAM_INT);
    $statement->execute();
    $records = $statement->fetchAll();
    foreach ($connection->query('SELECT status, COUNT(*) n FROM barangay_projects GROUP BY status') as $row) $counts[$row['status']] = (int) $row['n'];
}
$filtered = $search !== '' || $status_filter !== '';
$state = array_filter(['q' => $search, 'status' => $status_filter], static fn ($v): bool => $v !== '');
$page_url = static fn (int $target): string => 'projects.php?' . http_build_query($state + ($target > 1 ? ['page' => $target] : []));
$muted = static fn (string $text): string => '<span class="activity-detail-muted">' . e($text) . '</span>';
$first_shown = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last_shown = min($total, $page * $per_page);

$render_results = static function () use ($records, $filtered, $muted, $details, $can_manage, $total, $first_shown, $last_shown, $page, $total_pages, $page_url): void {
    if ($records === []): ?>
        <div class="dashboard-empty-state"><?= $filtered ? 'No matching projects found.' : ($can_manage ? 'No projects are recorded yet. Select Add Project to add the first one.' : 'No projects are recorded yet.') ?></div>
    <?php else: ?>
        <p class="activity-history-meta">Showing <?= e((string) $first_shown) ?>–<?= e((string) $last_shown) ?> of <?= e((string) $total) ?> project<?= $total === 1 ? '' : 's' ?></p>
        <div class="resident-table-wrap">
            <table class="resident-table">
                <thead><tr><th scope="col">Project</th><?php if ($details): ?><th scope="col">Category</th><th scope="col">Budget</th><?php endif; ?><th scope="col">Start</th><th scope="col">Target End</th><th scope="col">Status</th><?php if ($can_manage): ?><th scope="col"><span class="visually-hidden">Actions</span></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td class="resident-name"><?= e($row['title']) ?><br><span class="activity-detail-muted"><?= e($row['project_code']) ?></span></td>
                        <?php if ($details): ?>
                            <td><?= $row['category'] ? e($row['category']) : $muted('—') ?></td>
                            <td><?= $row['budget'] !== null ? e(projects_peso($row['budget'])) : $muted('—') ?></td>
                        <?php endif; ?>
                        <td><?= $row['start_date'] ? e(projects_date($row['start_date'])) : $muted('—') ?></td>
                        <td><?= $row['target_end_date'] ? e(projects_date($row['target_end_date'])) : $muted('—') ?></td>
                        <td><?= projects_status_badge($row['status']) ?><?= $row['status'] === 'completed' && $row['completed_at'] ? '<br>' . $muted(projects_date($row['completed_at'])) : '' ?></td>
                        <?php if ($can_manage): ?><td><div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="project_form.php?id=<?= e((string) $row['id']) ?>" aria-label="Edit <?= e($row['title']) ?>">Edit</a></div></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($records as $row): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><?= e($row['title']) ?></strong><?= projects_status_badge($row['status']) ?></div>
                    <p><?= e($row['project_code']) ?><?= $details && $row['category'] ? ' · ' . e($row['category']) : '' ?><?= $details && $row['budget'] !== null ? ' · ' . e(projects_peso($row['budget'])) : '' ?></p>
                    <p><?= $row['start_date'] ? e(projects_date($row['start_date'])) : 'No start date' ?> – <?= $row['target_end_date'] ? e(projects_date($row['target_end_date'])) : 'no target end' ?></p>
                    <?php if ($can_manage): ?><div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="project_form.php?id=<?= e((string) $row['id']) ?>">Edit</a></div><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <nav class="activity-pagination" aria-label="Projects pagination">
                <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($page_url($page - 1)) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($page_url($page + 1)) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif;
};
if ($ready && live_search_is_request()) live_search_respond($render_results);

$page_title = 'Projects'; $active_page = 'projects';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div>
            <h1>Projects</h1><p>Barangay development projects and programs.</p>
            <?php // Special Project: its destination (another website) is added later. ?>
            <button class="btn btn-primary special-project-btn" type="button" data-special-project>Special Project</button>
        </div>
        <?php if ($ready && $can_manage): ?><a class="btn btn-primary announcements-new-btn" href="project_form.php">Add Project</a><?php endif; ?>
    </div>
    <?php if ($success = flash('project_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('project_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Projects needs its database table (migration 20260922_dashboard_foundation) before projects can be recorded.</div>
    <?php else: ?>
        <section class="document-summary" aria-label="Projects by status">
            <?php foreach (['ongoing' => 'Ongoing', 'planned' => 'Planned', 'completed' => 'Completed', 'suspended' => 'Suspended'] as $key => $label): ?>
                <a class="document-summary-card is-<?= e($key === 'ongoing' ? 'approved' : ($key === 'planned' ? 'pending' : ($key === 'completed' ? 'released' : 'rejected'))) ?>" href="projects.php?status=<?= e($key) ?>"><span><?= e($label) ?></span><strong><?= e((string) $counts[$key]) ?></strong></a>
            <?php endforeach; ?>
        </section>
        <section class="dashboard-panel resident-list-panel">
            <form class="resident-filters" method="get" action="projects.php" data-live-search data-live-target="#project-results">
                <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="project-search">Search</label><input class="activity-filter-input" type="search" id="project-search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Project name, code<?= $details ? ', category' : '' ?> or description" autocomplete="off" data-live-query></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="project-status">Status</label><select class="activity-filter-select" id="project-status" name="status"><option value="">All</option><?php foreach (projects_status_labels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $status_filter === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="projects.php" data-live-reset>Reset</a></div>
            </form>
            <div id="project-results" class="live-search-results"><?php $render_results(); ?></div>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
