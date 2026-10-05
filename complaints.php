<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/hearings.php';
require_once __DIR__ . '/includes/live_search.php';
require_auth();
// Confidential module: Super Admin and Barangay Secretary only (server-side; hidden links are not relied on).
if (!complaints_can_manage()) { http_response_code(403); exit('Access denied.'); }
$connection = db();
complaints_require_schema($connection);
$counts = complaints_summary_counts($connection);

$tabs = ['complaints' => 'Complaints', 'blotter' => 'Blotter Records', 'hearings' => 'Hearing Schedule'];
$tab = (string) ($_GET['tab'] ?? 'complaints');
if (!array_key_exists($tab, $tabs)) $tab = 'complaints';

// ── Filters (validated, then applied in SQL before counting and paging) ──
$search = residents_collapse((string) ($_GET['q'] ?? ''));
if (mb_strlen($search) > 100) $search = mb_substr($search, 0, 100);
$valid_date = static fn (string $value): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && residents_valid_date($value) ? $value : '';
$from = $valid_date((string) ($_GET['from'] ?? ''));
$to = $valid_date((string) ($_GET['to'] ?? ''));
$status_options = match ($tab) { 'blotter' => blotter_status_labels(), 'hearings' => hearing_status_labels(), default => complaints_status_labels() };
$status_filter = (string) ($_GET['status'] ?? '');
if (!array_key_exists($status_filter, $status_options)) $status_filter = '';
$group_options = match ($tab) {
    'blotter' => complaints_distinct_values($connection, 'incident_type'),
    'hearings' => array_column(hearings_venue_options($connection), 'name', 'id'),
    default => complaints_distinct_values($connection, 'category'),
};
$group_filter = (string) ($_GET['group'] ?? '');
if ($tab === 'hearings') { if (!array_key_exists((int) $group_filter, $group_options)) $group_filter = ''; }
elseif (!in_array($group_filter, $group_options, true)) $group_filter = '';

$where = [];
$params = [];
$like = '%' . addcslashes($search, '%_\\') . '%';
if ($tab === 'complaints') {
    $base = 'FROM complaint_cases c';
    $date_column = 'c.filed_at';
    if ($search !== '') { $where[] = 'c.case_number LIKE :s1 OR c.subject LIKE :s2 OR c.category LIKE :s3 OR c.complainant_name LIKE :s4 OR c.respondent_name LIKE :s5'; $params += ['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like, 's5' => $like]; }
    if ($status_filter !== '') { $where[] = $status_filter === 'pending_review' ? "c.status IN ('pending_review', 'open')" : 'c.status = :status'; if ($status_filter !== 'pending_review') $params['status'] = $status_filter; }
    if ($group_filter !== '') { $where[] = 'c.category = :grp'; $params['grp'] = $group_filter; }
    $select = "SELECT c.id, c.case_number AS reference, c.subject, c.category, c.filed_at AS listed_at, c.status, c.submission_source";
    $order = "ORDER BY FIELD(c.status, 'pending_review', 'open', 'under_review', 'for_hearing', 'resolved', 'settled', 'closed', 'dismissed'), c.filed_at DESC, c.id DESC";
} elseif ($tab === 'blotter') {
    $base = 'FROM blotter_entries b LEFT JOIN complaint_cases c ON c.id = b.complaint_id';
    $date_column = 'b.recorded_at';
    if ($search !== '') { $where[] = 'b.blotter_number LIKE :s1 OR b.incident_type LIKE :s2 OR b.incident_location LIKE :s3 OR c.case_number LIKE :s4'; $params += ['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like]; }
    if ($status_filter !== '') { $where[] = 'b.status = :status'; $params['status'] = $status_filter; }
    if ($group_filter !== '') { $where[] = 'b.incident_type = :grp'; $params['grp'] = $group_filter; }
    $select = 'SELECT b.id, b.blotter_number AS reference, b.incident_type, b.incident_at, b.recorded_at AS listed_at, b.status, c.case_number AS complaint_reference';
    $order = "ORDER BY FIELD(b.status, 'recorded', 'active', 'resolved', 'closed'), b.recorded_at DESC, b.id DESC";
} else {
    $base = 'FROM case_hearings h INNER JOIN hearing_venues v ON v.id = h.venue_id LEFT JOIN complaint_cases c ON c.id = h.complaint_id LEFT JOIN blotter_entries b ON b.id = h.blotter_id';
    $date_column = 'h.starts_at';
    if ($search !== '') { $where[] = 'h.hearing_number LIKE :s1 OR h.hearing_type LIKE :s2 OR c.case_number LIKE :s3 OR b.blotter_number LIKE :s4 OR v.name LIKE :s5'; $params += ['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like, 's5' => $like]; }
    if ($status_filter !== '') { $where[] = 'h.status = :status'; $params['status'] = $status_filter; }
    if ($group_filter !== '') { $where[] = 'h.venue_id = :grp'; $params['grp'] = (int) $group_filter; }
    $select = 'SELECT h.id, h.hearing_number AS reference, h.hearing_type, h.starts_at, h.ends_at, h.starts_at AS listed_at, h.status, v.name AS venue_name, COALESCE(c.case_number, b.blotter_number) AS case_reference';
    // Upcoming active hearings first (soonest first), then the rest (most recent first).
    $order = "ORDER BY (h.status IN ('scheduled', 'rescheduled') AND h.ends_at >= NOW()) DESC, CASE WHEN h.status IN ('scheduled', 'rescheduled') AND h.ends_at >= NOW() THEN h.starts_at END ASC, h.starts_at DESC, h.id DESC";
}
if ($from !== '') { $where[] = "$date_column >= :from_date"; $params['from_date'] = $from . ' 00:00:00'; }
if ($to !== '') { $where[] = "$date_column < DATE_ADD(:to_date, INTERVAL 1 DAY)"; $params['to_date'] = $to; }
$where_sql = $where === [] ? '' : ' WHERE (' . implode(') AND (', $where) . ')';

$count = $connection->prepare("SELECT COUNT(*) $base$where_sql");
$count->execute($params);
$total = (int) $count->fetchColumn();
$total_pages = max(1, (int) ceil($total / COMPLAINTS_PER_PAGE));
$page = max(1, min($total_pages, (int) ($_GET['page'] ?? 1)));
$statement = $connection->prepare("$select $base$where_sql $order LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
$statement->bindValue('limit', COMPLAINTS_PER_PAGE, PDO::PARAM_INT);
$statement->bindValue('offset', ($page - 1) * COMPLAINTS_PER_PAGE, PDO::PARAM_INT);
$statement->execute();
$records = $statement->fetchAll();

$state = array_filter(['tab' => $tab, 'q' => $search, 'status' => $status_filter, 'group' => $group_filter, 'from' => $from, 'to' => $to, 'page' => $page > 1 ? $page : null], static fn ($v): bool => $v !== null && $v !== '');
$page_url = static fn (int $target): string => 'complaints.php?' . http_build_query(array_merge($state, ['page' => $target > 1 ? $target : null]));
$return = rawurlencode(http_build_query($state));
$view_url = static fn (array $r): string => match ($tab) { 'blotter' => 'blotter_view.php', 'hearings' => 'hearing_view.php', default => 'complaint_view.php' } . '?id=' . $r['id'] . '&return=' . $return;
$first_shown = $total === 0 ? 0 : ($page - 1) * COMPLAINTS_PER_PAGE + 1;
$last_shown = min($total, $page * COMPLAINTS_PER_PAGE);
$action_return = http_build_query($state);

// Quick status action for a row (confirmation dialog first; the handler re-validates everything).
$quick_action = static function (array $r) use ($tab, $action_return): string {
    [$handler, $action, $label, $heading, $message] = match (true) {
        $tab === 'complaints' && in_array($r['status'], ['pending_review', 'open'], true) => ['complaint_action.php', 'start_review', 'Start Review', 'Start complaint review?', 'The complaint will move to Under Review.'],
        $tab === 'blotter' && $r['status'] === 'recorded' => ['blotter_action.php', 'start', 'Start Processing', 'Start blotter processing?', 'The blotter entry will become Active.'],
        default => [null, null, null, null, null],
    };
    if ($handler === null) return '';
    $details = e(json_encode([['Reference', $r['reference']]], JSON_UNESCAPED_UNICODE));
    return '<form method="post" action="' . $handler . '" class="doc-action-form">' . csrf_field() . '<input type="hidden" name="id" value="' . e((string) $r['id']) . '"><input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="return" value="' . e($action_return) . '"><button class="btn doc-action-btn is-primary" type="submit" data-form-confirm="custom" data-dialog-heading="' . e($heading) . '" data-dialog-message="' . e($message) . '" data-dialog-details="' . $details . '" data-dialog-confirm="' . e($label) . '" data-dialog-dismiss="Cancel">' . e($label) . '</button></form>';
};

$render_results = static function () use ($tab, $records, $total, $first_shown, $last_shown, $total_pages, $page, $page_url, $view_url, $quick_action): void {
    if ($records === []): ?>
        <div class="dashboard-empty-state">No matching records found.</div>
    <?php return; endif; ?>
    <p class="activity-history-meta">Showing <?= e((string) $first_shown) ?>–<?= e((string) $last_shown) ?> of <?= e((string) $total) ?> record<?= $total === 1 ? '' : 's' ?></p>
    <div class="resident-table-wrap case-table-wrap">
        <table class="resident-table">
            <thead><tr>
                <?php if ($tab === 'complaints'): ?><th scope="col">Reference</th><th scope="col">Subject / Category</th><th scope="col">Submitted</th><th scope="col">Source</th><th scope="col">Status</th>
                <?php elseif ($tab === 'blotter'): ?><th scope="col">Reference</th><th scope="col">Incident Type</th><th scope="col">Incident Date</th><th scope="col">Related Complaint</th><th scope="col">Status</th>
                <?php else: ?><th scope="col">Hearing</th><th scope="col">Case</th><th scope="col">Schedule</th><th scope="col">Venue</th><th scope="col">Status</th><?php endif; ?>
                <th scope="col" class="document-actions-cell">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <tr>
                    <td class="resident-id"><a class="activity-detail-link" href="<?= e($view_url($r)) ?>"><?= e($r['reference']) ?></a></td>
                    <?php if ($tab === 'complaints'): ?>
                        <td class="case-subject"><strong><?= e($r['subject']) ?></strong><br><span class="activity-detail-muted"><?= e($r['category'] ?? '—') ?></span></td>
                        <td><?= e(complaints_format_datetime($r['listed_at'])) ?></td>
                        <td><?= e($r['submission_source'] === 'online' ? 'Online' : ($r['submission_source'] === 'staff' ? 'Staff-assisted' : '—')) ?></td>
                        <td><?= complaints_badge('complaint', $r['status']) ?></td>
                    <?php elseif ($tab === 'blotter'): ?>
                        <td><?= e($r['incident_type']) ?></td>
                        <td><?= e(complaints_format_datetime($r['incident_at'])) ?></td>
                        <td><?= $r['complaint_reference'] ? e($r['complaint_reference']) : '<span class="activity-detail-muted">Standalone</span>' ?></td>
                        <td><?= complaints_badge('blotter', $r['status']) ?></td>
                    <?php else: ?>
                        <td><?= e($r['case_reference']) ?><br><span class="activity-detail-muted"><?= e($r['hearing_type']) ?></span></td>
                        <td><?= e(complaints_format_time_range($r['starts_at'], $r['ends_at'])) ?></td>
                        <td><?= e($r['venue_name']) ?></td>
                        <td><?= complaints_badge('hearing', $r['status']) ?></td>
                    <?php endif; ?>
                    <td class="document-actions-cell"><div class="doc-action-group"><a class="btn doc-action-btn is-view" href="<?= e($view_url($r)) ?>">View Details</a><?= $quick_action($r) ?></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <ul class="resident-cards case-cards">
        <?php foreach ($records as $r): ?>
            <li class="resident-card">
                <div class="resident-card-top"><strong><a class="activity-detail-link" href="<?= e($view_url($r)) ?>"><?= e($r['reference']) ?></a></strong><?= complaints_badge($tab === 'complaints' ? 'complaint' : ($tab === 'blotter' ? 'blotter' : 'hearing'), $r['status']) ?></div>
                <?php if ($tab === 'complaints'): ?>
                    <p><strong><?= e($r['subject']) ?></strong></p><p><?= e($r['category'] ?? '—') ?> · <?= e($r['submission_source'] === 'online' ? 'Online' : 'Staff-assisted') ?></p><p>Submitted <?= e(complaints_format_datetime($r['listed_at'])) ?></p>
                <?php elseif ($tab === 'blotter'): ?>
                    <p><?= e($r['incident_type']) ?> · <?= $r['complaint_reference'] ? e($r['complaint_reference']) : 'Standalone' ?></p><p>Incident <?= e(complaints_format_datetime($r['incident_at'])) ?></p>
                <?php else: ?>
                    <p><?= e($r['case_reference']) ?> · <?= e($r['hearing_type']) ?></p><p><?= e(complaints_format_time_range($r['starts_at'], $r['ends_at'])) ?></p><p><?= e($r['venue_name']) ?></p>
                <?php endif; ?>
                <div class="doc-action-group"><a class="btn doc-action-btn is-view" href="<?= e($view_url($r)) ?>">View Details</a><?= $quick_action($r) ?></div>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($total_pages > 1): ?>
        <nav class="activity-pagination" aria-label="Pagination">
            <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($page_url($page - 1)) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
            <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
            <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($page_url($page + 1)) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
        </nav>
    <?php endif;
};
if (live_search_is_request()) live_search_respond($render_results);

$group_label = match ($tab) { 'blotter' => 'Incident type', 'hearings' => 'Venue', default => 'Category' };
$date_label = match ($tab) { 'blotter' => 'Recorded', 'hearings' => 'Hearing date', default => 'Submitted' };
$page_title = 'Complaints & Blotter'; $active_page = 'complaints';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Complaints &amp; Blotter</h1><p>Manage community complaints, blotter records, and hearing schedules.</p></div>
        <div class="doc-action-group is-large complaints-quick-actions">
            <a class="btn doc-action-btn is-primary" href="complaint_form.php">New Complaint</a>
            <a class="btn doc-action-btn is-view" href="blotter_form.php">New Blotter Entry</a>
            <a class="btn doc-action-btn is-view" href="hearing_form.php">Schedule Hearing</a>
        </div>
    </div>
    <?php if ($success = flash('case_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('case_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>

    <section class="document-summary" aria-label="Case summary">
        <?php foreach (['pending_complaints' => ['Pending Complaints', 'is-pending', 'complaints.php?tab=complaints&status=pending_review'], 'active_blotter' => ['Active Blotter Cases', 'is-approved', 'complaints.php?tab=blotter&status=active'], 'upcoming_hearings' => ['Upcoming Hearings', 'is-released', 'complaints.php?tab=hearings'], 'resolved_cases' => ['Resolved Cases', 'is-resolved', 'complaints.php?tab=complaints&status=resolved']] as $key => [$label, $tone, $href]): ?>
            <a class="document-summary-card <?= e($tone) ?>" href="<?= e($href) ?>"><span><?= e($label) ?></span><strong><?= e((string) $counts[$key]) ?></strong></a>
        <?php endforeach; ?>
    </section>

    <section class="dashboard-panel announcements-page">
        <div class="case-tabs-bar">
            <nav class="announcement-tabs page-tabs" aria-label="Complaints and blotter sections">
                <?php foreach ($tabs as $key => $label): ?><a class="announcement-tab <?= $tab === $key ? 'active' : '' ?>" href="complaints.php?tab=<?= e($key) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
            </nav>
            <div class="case-tabs-links"><a class="activity-detail-link" href="barangay_personnel.php">Personnel &amp; Venues</a><a class="activity-detail-link" href="reports.php">Reports</a></div>
        </div>
        <form class="resident-filters" method="get" action="complaints.php" data-live-search data-live-target="#case-results" data-live-range="#case-from,#case-to">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <div class="resident-filter-group resident-filter-search">
                <label class="activity-filter-label" for="case-search">Search</label>
                <input class="activity-filter-input" type="search" id="case-search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Reference, subject, type or name" autocomplete="off" data-live-query>
            </div>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="case-status">Status</label>
                <select class="activity-filter-select" id="case-status" name="status"><option value="">All</option><?php foreach ($status_options as $value => $label): ?><option value="<?= e($value) ?>" <?= $status_filter === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            </div>
            <div class="resident-filter-group">
                <label class="activity-filter-label" for="case-group"><?= e($group_label) ?></label>
                <select class="activity-filter-select" id="case-group" name="group"><option value="">All</option><?php foreach ($group_options as $value => $label): $option = $tab === 'hearings' ? (string) $value : (string) $label; ?><option value="<?= e($option) ?>" <?= $group_filter === $option ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            </div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="case-from"><?= e($date_label) ?> from</label><input class="activity-filter-input" type="date" id="case-from" name="from" value="<?= e($from) ?>"></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="case-to">To</label><input class="activity-filter-input" type="date" id="case-to" name="to" value="<?= e($to) ?>"></div>
            <div class="activity-filter-actions">
                <button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button>
                <a class="btn btn-outline-secondary btn-sm" href="complaints.php?tab=<?= e($tab) ?>" data-live-reset>Reset</a>
            </div>
        </form>
        <div id="case-results" class="live-search-results"><?php $render_results(); ?></div>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
