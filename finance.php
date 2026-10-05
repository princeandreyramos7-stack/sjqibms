<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
require_once __DIR__ . '/includes/live_search.php';
finance_require('view');
$connection = db();
$ready = finance_ready($connection);

// Financial Management: collections and disbursements. Tabs (All, Collections, Disbursements, Pending Approval), search,
// status, type, category and date range, sortable columns and pagination — all applied in SQL and kept in the URL.
// Action buttons follow the signed-in role and the record's status; finance_action.php enforces the same rules again.
$per_page = 10;
$state = finance_list_state($_GET);
$records = [];
$total = 0;
$page = 1;
$total_pages = 1;
$pending = 0;
if ($ready) {
    [$where, $params] = finance_list_where($state);
    $from = ' FROM finance_transactions t INNER JOIN finance_categories c ON c.id = t.category_id WHERE ' . implode(' AND ', $where);
    $count = $connection->prepare('SELECT COUNT(*)' . $from);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $total_pages = max(1, (int) ceil($total / $per_page));
    $page = max(1, min($total_pages, $state['page']));
    $statement = $connection->prepare('SELECT t.id, t.reference_no, t.type, t.transaction_date, t.category_id, t.description, t.amount, t.payor_or_payee, t.status, c.name AS category_name' . $from . ' ORDER BY ' . finance_sort_sql($state['sort'], $state['dir']) . ' LIMIT :limit OFFSET :offset');
    foreach ($params as $key => $value) $statement->bindValue($key, $value);
    $statement->bindValue('limit', $per_page, PDO::PARAM_INT);
    $statement->bindValue('offset', ($page - 1) * $per_page, PDO::PARAM_INT);
    $statement->execute();
    $records = $statement->fetchAll();
    $pending = finance_pending_count($connection);
}
$first = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last = min($total, $page * $per_page);
$url = static fn (array $changes = []): string => 'finance.php' . (($query = finance_query_string(array_merge($state, ['page' => $page], $changes))) !== '' ? '?' . $query : '');
$return = finance_query_string(array_merge($state, ['page' => $page]));

// Sortable header: clicking the active column flips the direction; a new column starts ascending (Date starts newest first).
$sort_header = static function (string $key, string $label, string $class = '') use ($state, $url): string {
    $active = $state['sort'] === $key;
    $dir = $active ? ($state['dir'] === 'asc' ? 'desc' : 'asc') : ($key === 'date' || $key === 'amount' ? 'desc' : 'asc');
    $aria = $active ? ($state['dir'] === 'asc' ? 'ascending' : 'descending') : 'none';
    $arrow = $active ? ($state['dir'] === 'asc' ? '▲' : '▼') : '↕';
    return '<th scope="col" aria-sort="' . $aria . '"' . ($class !== '' ? ' class="' . $class . '"' : '') . '><a class="fin-sort' . ($active ? ' is-active' : '') . '" href="' . e($url(['sort' => $key, 'dir' => $dir, 'page' => 1])) . '" data-live-page>' . e($label) . ' <span aria-hidden="true">' . $arrow . '</span></a></th>';
};
$actions = static function (array $row) use ($return, $connection): string {
    $can = finance_allowed_actions($row);
    $id = e((string) $row['id']);
    // The System Administrator sees the budget warning in the approval dialog too (warning only; approving stays possible).
    $warning = $can['approve'] ? finance_budget_check($connection, (int) $row['category_id'], (int) substr((string) $row['transaction_date'], 0, 4), (string) $row['amount'], (int) $row['id']) : null;
    $warning_text = $warning !== null ? ' Budget warning: ' . finance_budget_message($warning, (string) $row['category_name']) : '';
    $html = '<div class="management-actions fin-actions"><a class="btn btn-sm btn-outline-primary" href="finance_view.php?id=' . $id . '">View</a>';
    if ($can['edit']) $html .= '<a class="btn btn-sm btn-outline-primary" href="finance_form.php?id=' . $id . '">Edit</a>';
    if ($can['approve']) $html .= '<form method="post" action="finance_action.php" class="doc-action-form">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="approve"><input type="hidden" name="return" value="' . e($return) . '"><button class="btn btn-sm btn-outline-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Approve this disbursement?" data-dialog-message="' . e($row['reference_no'] . ' — ' . finance_peso($row['amount']) . ' to ' . $row['payor_or_payee'] . '. The Treasurer will be notified and can release the payment.' . $warning_text) . '" data-dialog-confirm="Approve" data-dialog-dismiss="Cancel">Approve</button></form>';
    if ($can['reject']) $html .= '<a class="btn btn-sm btn-outline-danger" href="finance_view.php?id=' . $id . '#reject">Reject</a>';
    if ($can['release']) $html .= '<a class="btn btn-sm btn-outline-primary" href="finance_view.php?id=' . $id . '#release">Release</a>';
    if ($can['cancel']) $html .= '<a class="btn btn-sm btn-outline-danger" href="finance_view.php?id=' . $id . '#cancel">Cancel</a>';
    return $html . '</div>';
};

$render_results = static function () use ($records, $total, $first, $last, $total_pages, $page, $state, $url, $sort_header, $actions, $pending): void {
    ?>
    <div hidden data-fin-state="<?= e(json_encode(['tab' => $state['tab'], 'sort' => $state['sort'], 'dir' => $state['dir']], JSON_THROW_ON_ERROR)) ?>"></div>
    <nav class="announcement-tabs page-tabs fin-tabs" aria-label="Transaction type">
        <?php foreach (finance_tabs() as $tab => $label): ?>
            <a class="announcement-tab <?= $state['tab'] === $tab ? 'active' : '' ?>" href="<?= e($url(['tab' => $tab, 'page' => 1])) ?>" data-live-page <?= $state['tab'] === $tab ? 'aria-current="page"' : '' ?>><?= e($label) ?><?= $tab === 'pending' && $pending > 0 ? ' <span class="fin-pending-count">' . e((string) $pending) . '</span>' : '' ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if ($records === []): ?>
        <div class="dashboard-empty-state"><?= finance_state_filtered($state) || $state['tab'] !== '' ? 'No matching transactions found.' : 'No transactions yet.' . (finance_can('create') ? ' Select Record Collection or Record Disbursement to add the first one.' : '') ?></div>
    <?php else: ?>
        <?php $export_query = finance_query_string(array_merge($state, ['page' => 1])); ?>
        <div class="fin-results-head">
            <p class="activity-history-meta">Showing <?= e((string) $first) ?>–<?= e((string) $last) ?> of <?= e((string) $total) ?> transaction<?= $total === 1 ? '' : 's' ?></p>
            <?php if (finance_can('reports')): ?><div class="fin-exports" aria-label="Export the filtered list"><a class="btn btn-sm btn-outline-secondary" href="finance_export.php<?= $export_query !== '' ? '?' . e($export_query) : '' ?>">Export Excel</a><a class="btn btn-sm btn-outline-secondary" href="finance_list_report.php?<?= e($export_query !== '' ? $export_query . '&' : '') ?>print=1" target="_blank" rel="noopener">Export PDF</a></div><?php endif; ?>
        </div>
        <div class="resident-table-wrap">
            <table class="resident-table fin-table">
                <thead><tr><?= $sort_header('reference', 'Reference') . $sort_header('date', 'Date') . $sort_header('description', 'Description') . $sort_header('category', 'Category') . $sort_header('type', 'Type') . $sort_header('amount', 'Amount', 'fin-num') . $sort_header('status', 'Status') ?><th scope="col" class="fin-actions-head">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td class="resident-name fin-nowrap"><?= e($row['reference_no']) ?></td>
                        <td class="fin-nowrap"><?= e(finance_format_date($row['transaction_date'])) ?></td>
                        <td><?= e($row['description']) ?><span class="fin-sub"><?= e($row['payor_or_payee']) ?></span></td>
                        <td><?= e($row['category_name']) ?></td>
                        <td><span class="fin-type fin-type-<?= e($row['type']) ?>"><?= e(finance_types()[$row['type']] ?? '') ?></span></td>
                        <td class="fin-num fin-nowrap<?= $row['status'] === 'cancelled' || $row['status'] === 'rejected' ? ' fin-void' : '' ?>"><?= e(finance_peso($row['amount'])) ?></td>
                        <td><?= finance_status_badge($row['status']) ?></td>
                        <td><?= $actions($row) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($records as $row): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><?= e($row['reference_no']) ?> · <?= e(finance_peso($row['amount'])) ?></strong><?= finance_status_badge($row['status']) ?></div>
                    <p><?= e($row['description']) ?></p>
                    <p><?= e(finance_types()[$row['type']] ?? '') ?> · <?= e($row['category_name']) ?> · <?= e(finance_format_date($row['transaction_date'])) ?> · <?= e($row['payor_or_payee']) ?></p>
                    <?= $actions($row) ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <nav class="activity-pagination" aria-label="Transactions pagination">
                <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page - 1])) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page + 1])) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif;
};
if ($ready && live_search_is_request()) live_search_respond($render_results);

$categories = $ready ? finance_categories($connection, null, null, true) : [];
$page_title = 'Financial Management'; $active_page = 'finance';
$page_styles = ['assets/css/finance.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Financial Management</h1><p>Barangay collections, disbursements, and fund transactions.</p></div>
        <?php if ($ready && finance_can('create')): ?><div class="resident-detail-actions"><a class="btn btn-primary announcements-new-btn" href="finance_form.php?type=income">Record Collection</a><a class="btn btn-primary announcements-new-btn" href="finance_form.php?type=expense">Record Disbursement</a><a class="btn btn-light resident-action-btn" href="finance_categories.php">Categories</a></div><?php endif; ?>
    </div>
    <?= finance_nav('transactions') ?>
    <?php if ($ready): ?><?= finance_summary_cards(finance_summary($connection)) ?><?php endif; ?>
    <?php if ($success = flash('finance_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('finance_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Financial Management needs its database tables before transactions can be recorded. No records have been changed.</div>
    <?php else: ?>
        <?php if ($pending > 0 && finance_can('approve')): ?><div class="dashboard-status warning" role="status"><?= e((string) $pending) ?> disbursement<?= $pending === 1 ? ' is' : 's are' ?> waiting for your approval. <a class="activity-detail-link" href="finance.php?tab=pending">Review now</a></div><?php endif; ?>
        <section class="dashboard-panel resident-list-panel">
            <form class="resident-filters fin-filters" method="get" action="finance.php" data-live-search data-live-target="#fin-results" data-live-range="#fin-from,#fin-to" data-fin-form>
                <?php // Tab and sort are kept in hidden fields so typing a search keeps them (see assets/js/finance.js). ?>
                <input type="hidden" name="tab" value="<?= e($state['tab']) ?>" data-fin-field="tab">
                <input type="hidden" name="sort" value="<?= e($state['sort']) ?>" data-fin-field="sort">
                <input type="hidden" name="dir" value="<?= e($state['dir']) ?>" data-fin-field="dir">
                <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="fin-search">Search</label><input class="activity-filter-input" type="search" id="fin-search" name="q" value="<?= e($state['q']) ?>" maxlength="100" placeholder="Reference, description, payor / payee" autocomplete="off" data-live-query></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="fin-status">Status</label><select class="activity-filter-select" id="fin-status" name="status"><option value="">All</option><?php foreach (finance_statuses() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="fin-type">Type</label><select class="activity-filter-select" id="fin-type" name="type"><option value="">All</option><?php foreach (finance_types() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="fin-category">Category</label><select class="activity-filter-select" id="fin-category" name="category"><option value="">All</option><?php foreach ($categories as $category): ?><option value="<?= e((string) $category['id']) ?>" <?= $state['category'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?> (<?= e(finance_types()[$category['type']]) ?>)<?= (int) $category['is_active'] === 1 ? '' : ' — inactive' ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="fin-from">From</label><input class="activity-filter-input" type="date" id="fin-from" name="from" value="<?= e($state['from']) ?>"></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="fin-to">To</label><input class="activity-filter-input" type="date" id="fin-to" name="to" value="<?= e($state['to']) ?>"></div>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="finance.php" data-live-reset>Reset</a></div>
            </form>
            <div id="fin-results" class="live-search-results"><?php $render_results(); ?></div>
        </section>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/finance.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/finance.js')) ?>"></script>
