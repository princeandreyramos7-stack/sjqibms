<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/documents.php';
require_once __DIR__ . '/includes/live_search.php';
require_auth();
if (!can_access_navigation('documents')) { http_response_code(403); exit('Access denied.'); }
$connection = db();
$can_create = role_can('documents.request.create');
$templates_installed = documents_templates_installed($connection);

// ── Filters (applied in SQL before counting and paging) ─────────────────────
$per_page = 10;
$search = residents_collapse((string) ($_GET['q'] ?? ''));
if (mb_strlen($search) > 100) $search = mb_substr($search, 0, 100);
$status_filter = (string) ($_GET['status'] ?? '');
if (!array_key_exists($status_filter, documents_status_labels())) $status_filter = '';
$type_options = array_values(array_unique(array_merge(array_keys(documents_types()), $connection->query('SELECT DISTINCT document_type FROM document_requests ORDER BY document_type')->fetchAll(PDO::FETCH_COLUMN))));
$type_filter = (string) ($_GET['type'] ?? '');
if (!in_array($type_filter, $type_options, true)) $type_filter = '';
$valid_date = static fn (string $value): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && residents_valid_date($value) ? $value : '';
$from = $valid_date((string) ($_GET['from'] ?? ''));
$to = $valid_date((string) ($_GET['to'] ?? ''));

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "d.reference_code LIKE :s1 OR d.document_type LIKE :s2 OR CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name, r.suffix) LIKE :s3 OR CONCAT_WS(' ', r.first_name, r.last_name) LIKE :s4";
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $params += ['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like];
}
if ($status_filter !== '') { $where[] = 'd.status = :status'; $params['status'] = $status_filter; }
if ($type_filter !== '') { $where[] = 'd.document_type = :type'; $params['type'] = $type_filter; }
if ($from !== '') { $where[] = 'd.requested_at >= :from'; $params['from'] = $from . ' 00:00:00'; }
if ($to !== '') { $where[] = 'd.requested_at < DATE_ADD(:to, INTERVAL 1 DAY)'; $params['to'] = $to; }
$where_sql = $where === [] ? '' : ' WHERE (' . implode(') AND (', $where) . ')';

$count = $connection->prepare('SELECT COUNT(*) FROM document_requests d INNER JOIN residents r ON r.id = d.resident_id' . $where_sql);
$count->execute($params);
$total = (int) $count->fetchColumn();
$total_pages = max(1, (int) ceil($total / $per_page));
$page = max(1, min($total_pages, (int) ($_GET['page'] ?? 1)));
$statement = $connection->prepare("SELECT d.id, d.reference_code, d.document_type, d.purpose, d.status, d.requested_at, r.first_name, r.middle_name, r.last_name, r.suffix FROM document_requests d INNER JOIN residents r ON r.id = d.resident_id$where_sql ORDER BY FIELD(d.status, 'pending', 'approved', 'released', 'rejected'), d.requested_at DESC, d.id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) $statement->bindValue($key, $value);
$statement->bindValue('limit', $per_page, PDO::PARAM_INT);
$statement->bindValue('offset', ($page - 1) * $per_page, PDO::PARAM_INT);
$statement->execute();
$records = $statement->fetchAll();
$counts = array_fill_keys(array_keys(documents_status_labels()), 0);
foreach ($connection->query('SELECT status, COUNT(*) n FROM document_requests GROUP BY status') as $row) $counts[$row['status']] = (int) $row['n'];

$state = array_filter(['q' => $search, 'status' => $status_filter, 'type' => $type_filter, 'from' => $from, 'to' => $to, 'page' => $page > 1 ? $page : null], static fn ($v): bool => $v !== null && $v !== '');
$page_url = static fn (int $target): string => 'documents.php?' . http_build_query(array_merge($state, ['page' => $target > 1 ? $target : null]));
$view_url = static fn (array $record): string => 'document_view.php?id=' . $record['id'] . ($state === [] ? '' : '&return=' . rawurlencode(http_build_query($state)));
$action_return = $state === [] ? 'list' : http_build_query($state);
$actions = static fn (array $record): string => documents_action_buttons($connection, $record, $action_return);
$first_shown = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last_shown = min($total, $page * $per_page);
$featured = documents_featured_templates($connection);

$render_results = static function () use ($records, $total, $first_shown, $last_shown, $total_pages, $page, $page_url, $view_url, $actions): void {
    if ($records === []): ?>
        <div class="dashboard-empty-state">No matching records found.</div>
    <?php else: ?>
        <p class="activity-history-meta">Showing <?= e((string) $first_shown) ?>–<?= e((string) $last_shown) ?> of <?= e((string) $total) ?> request<?= $total === 1 ? '' : 's' ?></p>
        <div class="resident-table-wrap">
            <table class="resident-table">
                <thead><tr><th scope="col">Reference</th><th scope="col">Resident</th><th scope="col">Document Type</th><th scope="col">Purpose</th><th scope="col">Requested</th><th scope="col">Status</th><th scope="col" class="document-actions-cell">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td class="resident-id"><a class="activity-detail-link" href="<?= e($view_url($record)) ?>"><?= e($record['reference_code']) ?></a></td>
                        <td class="resident-name"><?= e(residents_full_name($record)) ?></td>
                        <td><?= e($record['document_type']) ?></td>
                        <td class="document-purpose"><?= e($record['purpose']) ?></td>
                        <td><?= e(documents_format_datetime($record['requested_at'])) ?></td>
                        <td><?= documents_test_release($record) !== null ? documents_test_release_badge() : documents_status_badge($record['status']) ?></td>
                        <td class="document-actions-cell"><div class="doc-action-group"><a class="btn doc-action-btn is-view" href="<?= e($view_url($record)) ?>">View Details</a><?= $actions($record) ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($records as $record): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><a class="activity-detail-link" href="<?= e($view_url($record)) ?>"><?= e($record['reference_code']) ?></a></strong><?= documents_test_release($record) !== null ? documents_test_release_badge() : documents_status_badge($record['status']) ?></div>
                    <p><?= e($record['document_type']) ?> · <?= e(residents_full_name($record)) ?></p>
                    <p><?= e($record['purpose']) ?></p>
                    <p>Requested <?= e(documents_format_datetime($record['requested_at'])) ?></p>
                    <div class="doc-action-group"><a class="btn doc-action-btn is-view" href="<?= e($view_url($record)) ?>">View Details</a><?= $actions($record) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <nav class="activity-pagination" aria-label="Document requests pagination">
                <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($page_url($page - 1)) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($page_url($page + 1)) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif;
};
if (live_search_is_request()) live_search_respond($render_results);

$page_title = 'Documents'; $active_page = 'documents';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <!-- 1. Title and primary actions -->
    <div class="page-heading">
        <div><h1>Documents</h1><p>Document requests, templates, and processing for Barangay San Jose.</p></div>
        <?php if ($can_create): ?><a class="btn btn-primary announcements-new-btn" href="document_request_form.php">New Document Request</a><?php endif; ?>
    </div>
    <?php if ($success = flash('document_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('document_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>

    <!-- 2. Frequently Used Templates -->
    <section class="documents-section" aria-labelledby="featured-heading">
        <div class="section-heading documents-section-head">
            <div><h2 id="featured-heading">Frequently Used Templates</h2><p>Quickly create new document requests using the most commonly used templates.</p></div>
            <a class="btn btn-light btn-sm resident-action-btn" href="document_templates.php">View All Templates</a>
        </div>
        <?php if ($featured !== []): ?>
            <div class="document-quick-grid">
                <?php foreach ($featured as $template): ?>
                    <?php $card_body = '<span class="document-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></span><span class="document-quick-text"><strong>' . e($template['document_type']) . '</strong><small>' . e($template['title']) . '</small></span><span class="document-quick-arrow" aria-hidden="true">&rarr;</span>'; ?>
                    <?php if ($can_create): ?>
                        <a class="document-quick-card" href="document_request_form.php?template=<?= e((string) $template['id']) ?>" aria-label="New <?= e($template['document_type']) ?> request"><?= $card_body ?></a>
                    <?php else: ?>
                        <div class="document-quick-card is-static"><?= $card_body ?></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="document-quick-empty">
                <span class="document-quick-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg></span>
                <p><strong>No featured document templates are available yet.</strong><?php if (!$templates_installed): ?> <span>Approved templates appear here after the Documents database update is approved and imported.</span><?php endif; ?></p>
            </div>
        <?php endif; ?>
    </section>

    <!-- 4. Status summary -->
    <section class="document-summary" aria-label="Request summary">
        <?php foreach (['pending' => 'Pending Review', 'approved' => 'Approved', 'released' => 'Released', 'rejected' => 'Rejected'] as $key => $label): ?>
            <a class="document-summary-card is-<?= e($key) ?>" href="documents.php?status=<?= e($key) ?>"><span><?= e($label) ?></span><strong><?= e((string) $counts[$key]) ?></strong></a>
        <?php endforeach; ?>
    </section>

    <!-- 5. Search and filters · 6. Request list -->
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters" method="get" action="documents.php" data-live-search data-live-target="#document-results" data-live-range="#document-from,#document-to">
            <div class="resident-filter-group resident-filter-search">
                <label class="activity-filter-label" for="document-search">Search</label>
                <input class="activity-filter-input" type="search" id="document-search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Reference, resident, or document" autocomplete="off" data-live-query>
            </div>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="document-type">Document type</label>
                <select class="activity-filter-select" id="document-type" name="type">
                    <option value="">All types</option>
                    <?php foreach ($type_options as $type): ?><option value="<?= e($type) ?>" <?= $type_filter === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="document-status">Status</label>
                <select class="activity-filter-select" id="document-status" name="status">
                    <option value="">All</option>
                    <?php foreach (documents_status_labels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $status_filter === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="document-from">From</label>
                <input class="activity-filter-input" type="date" id="document-from" name="from" value="<?= e($from) ?>">
            </div>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="document-to">To</label>
                <input class="activity-filter-input" type="date" id="document-to" name="to" value="<?= e($to) ?>">
            </div>
            <div class="activity-filter-actions">
                <button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button>
                <a class="btn btn-outline-secondary btn-sm" href="documents.php" data-live-reset>Reset</a>
            </div>
        </form>
        <div id="document-results" class="live-search-results"><?php $render_results(); ?></div>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
