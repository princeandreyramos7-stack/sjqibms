<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/audit_logs.php';
require_once __DIR__ . '/includes/live_search.php';
audit_require_admin();
$connection = db();

$filters = audit_filters($_GET);
$per_page = 25;
$query_for = static fn (array $extra = []): string => http_build_query(array_filter(array_merge($filters, $extra), static fn ($value): bool => $value !== null && $value !== ''));

// CSV export of the filtered records (newest first, up to 5,000 rows). The export itself is recorded.
if (($_GET['export'] ?? '') === 'csv') {
    $rows = audit_fetch($connection, $filters, 5000, 0);
    security_log('audit_log_exported', (int) current_user()['id'], ['rows' => count($rows), 'filters' => array_filter($filters, static fn ($value): bool => $value !== null && $value !== '')]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sjqibms-audit-logs-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Log ID', 'Date and time', 'User', 'Role', 'Action', 'Category', 'Result', 'Details', 'IP address', 'Browser / device']);
    foreach ($rows as $row) {
        fputcsv($out, array_map(static fn ($value): string => audit_csv_cell((string) $value), [$row['id'], $row['created_at'], audit_actor($row), audit_role_label($row['user_role']), audit_action_label($row['action']), audit_category_of($row), audit_result($row['action']), audit_summary($row), $row['ip_address'] ?? '', $row['user_agent'] ?? '']));
    }
    fclose($out);
    exit;
}

$total = audit_count($connection, $filters);
$total_pages = max(1, (int) ceil($total / $per_page));
$page = max(1, min($total_pages, (int) ($_GET['page'] ?? 1)));
$records = audit_fetch($connection, $filters, $per_page, ($page - 1) * $per_page);
$first = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last = min($total, $page * $per_page);
$page_url = static fn (int $target): string => 'audit_logs.php?' . $query_for(['page' => $target > 1 ? $target : null]);
$users = $connection->query('SELECT id, name, role FROM users ORDER BY name')->fetchAll();
$filtered = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '') !== [];

$render_results = static function () use ($records, $total, $first, $last, $total_pages, $page, $page_url, $filtered, $query_for): void { ?>
    <div class="audit-results-head">
        <p class="activity-history-meta"><?= $total === 0 ? '' : 'Showing ' . e((string) $first) . '–' . e((string) $last) . ' of ' . e((string) $total) . ' record' . ($total === 1 ? '' : 's') ?></p>
        <?php if ($total > 0): ?><a class="btn btn-outline-secondary btn-sm" href="audit_logs.php?<?= e($query_for(['export' => 'csv'])) ?>">Export CSV</a><?php endif; ?>
    </div>
    <?php if ($records === []): ?>
        <div class="dashboard-empty-state"><?= $filtered ? 'No matching records found.' : 'No audit records yet.' ?></div>
    <?php else: ?>
        <div class="resident-table-wrap">
            <table class="resident-table audit-table">
                <thead><tr><th scope="col">Date &amp; time</th><th scope="col">User</th><th scope="col">Action</th><th scope="col">Category</th><th scope="col">IP address</th><th scope="col">Result</th><th scope="col"><span class="visually-hidden">Details</span></th></tr></thead>
                <tbody>
                <?php foreach ($records as $row): $summary = audit_summary($row); ?>
                    <tr>
                        <td class="audit-time"><?= e(audit_format_datetime((string) $row['created_at'])) ?></td>
                        <td><strong class="audit-user"><?= e(audit_actor($row)) ?></strong><?php if ($row['user_role']): ?><span class="audit-sub"><?= e(audit_role_label($row['user_role'])) ?></span><?php endif; ?></td>
                        <td><?= e(audit_action_label($row['action'])) ?><?php if ($summary !== ''): ?><span class="audit-sub"><?= e($summary) ?></span><?php endif; ?></td>
                        <td><?= e(audit_category_of($row)) ?></td>
                        <td class="audit-ip"><?= $row['ip_address'] ? e($row['ip_address']) : '<span class="activity-detail-muted">—</span>' ?></td>
                        <td><?= audit_result_badge($row['action']) ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="audit_log_view.php?id=<?= e((string) $row['id']) ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($records as $row): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><?= e(audit_action_label($row['action'])) ?></strong><?= audit_result_badge($row['action']) ?></div>
                    <p><?= e(audit_actor($row)) ?> · <?= e(audit_format_datetime((string) $row['created_at'])) ?></p>
                    <p><?= e(audit_category_of($row)) ?><?= $row['ip_address'] ? ' · ' . e($row['ip_address']) : '' ?></p>
                    <div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="audit_log_view.php?id=<?= e((string) $row['id']) ?>">View</a></div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <nav class="activity-pagination" aria-label="Audit log pagination">
                <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($page_url($page - 1)) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($page_url($page + 1)) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif;
};
if (live_search_is_request()) live_search_respond($render_results);

$page_title = 'Audit Logs'; $active_page = 'audit';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading"><div><h1>Audit Logs</h1><p>Security record of every sign-in, sign-out, failed attempt, refused access and change in SJQIBMS. Visible to the System Administrator only. Records cannot be edited or deleted.</p></div></div>
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters audit-filters" method="get" action="audit_logs.php" data-live-search data-live-target="#audit-results" data-live-range="#audit-from,#audit-to">
            <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="audit-search">Search</label><input class="activity-filter-input" type="search" id="audit-search" name="q" value="<?= e($filters['q']) ?>" maxlength="100" placeholder="User, email, action or IP address" autocomplete="off" data-live-query></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="audit-category">Category</label><select class="activity-filter-select" id="audit-category" name="category"><option value="">All categories</option><?php foreach (audit_categories() as $key => [$label]): ?><option value="<?= e($key) ?>" <?= $filters['category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="audit-user">User</label><select class="activity-filter-select" id="audit-user" name="user"><option value="">All users</option><?php foreach ($users as $user): ?><option value="<?= e((string) $user['id']) ?>" <?= $filters['user'] === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['name'] . ' — ' . audit_role_label($user['role'])) ?></option><?php endforeach; ?></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="audit-result">Result</label><select class="activity-filter-select" id="audit-result" name="result"><option value="">All results</option><option value="success" <?= $filters['result'] === 'success' ? 'selected' : '' ?>>Success</option><option value="failed" <?= $filters['result'] === 'failed' ? 'selected' : '' ?>>Failed / Denied</option></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="audit-from">From</label><input class="activity-filter-input" type="date" id="audit-from" name="from" value="<?= e($filters['from']) ?>" max="<?= e(date('Y-m-d')) ?>"></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="audit-to">To</label><input class="activity-filter-input" type="date" id="audit-to" name="to" value="<?= e($filters['to']) ?>" max="<?= e(date('Y-m-d')) ?>"></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="audit_logs.php" data-live-reset>Reset</a></div>
        </form>
        <div id="audit-results" class="live-search-results"><?php $render_results(); ?></div>
    </section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
