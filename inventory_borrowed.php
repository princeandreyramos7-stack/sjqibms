<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/live_search.php';
inventory_require_manage();
$connection = db();
if (!inventory_ready($connection) || !inventory_borrowing_ready($connection)) { flash('inventory_error', 'Borrowing needs its database tables first.'); redirect('inventory.php'); }

// Borrowed Items: active borrows (default), overdue, returned or all, with search and paging.
$per_page = 25;
$show = (string) ($_GET['show'] ?? 'active');
if (!in_array($show, ['active', 'overdue', 'returned', 'all'], true)) $show = 'active';
$search = mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100);
$today = date('Y-m-d');
$where = [];
$params = [];
if ($show === 'active') $where[] = 'b.actual_return_date IS NULL';
if ($show === 'overdue') { $where[] = 'b.actual_return_date IS NULL AND b.expected_return_date < :today'; $params['today'] = $today; }
if ($show === 'returned') $where[] = 'b.actual_return_date IS NOT NULL';
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $parts = [];
    foreach (['b.borrow_code', 'b.borrower_name', 'b.borrower_address', 'i.item_code', 'i.name'] as $index => $column) { $parts[] = "$column LIKE :search$index"; $params['search' . $index] = $like; }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}
$from = ' FROM inventory_borrow_records b INNER JOIN inventory_items i ON i.id = b.item_id' . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where));
$count = $connection->prepare('SELECT COUNT(*)' . $from);
$count->execute($params);
$total = (int) $count->fetchColumn();
$total_pages = max(1, (int) ceil($total / $per_page));
$page = max(1, min($total_pages, (int) ($_GET['page'] ?? 1)));
$statement = $connection->prepare('SELECT b.*, i.item_code, i.name AS item_name, i.unit' . $from . ' ORDER BY (b.actual_return_date IS NULL) DESC, b.expected_return_date ASC, b.id DESC LIMIT :limit OFFSET :offset');
foreach ($params as $key => $value) $statement->bindValue($key, $value);
$statement->bindValue('limit', $per_page, PDO::PARAM_INT);
$statement->bindValue('offset', ($page - 1) * $per_page, PDO::PARAM_INT);
$statement->execute();
$records = $statement->fetchAll();
$counts = $connection->prepare('SELECT SUM(actual_return_date IS NULL) AS active, SUM(actual_return_date IS NULL AND expected_return_date < :today) AS overdue FROM inventory_borrow_records');
$counts->execute(['today' => $today]);
$counts = array_map('intval', $counts->fetch() ?: []);
$first = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last = min($total, $page * $per_page);
$page_url = static fn (int $target): string => 'inventory_borrowed.php?' . http_build_query(array_filter(['show' => $show === 'active' ? '' : $show, 'q' => $search, 'page' => $target > 1 ? $target : null], static fn ($v): bool => $v !== null && $v !== ''));

$status_badge = static function (array $borrow): string {
    if ($borrow['actual_return_date'] !== null) return '<span class="resident-status resident-status-active">Returned</span>';
    return inventory_is_overdue($borrow) ? '<span class="resident-status resident-status-deceased">Overdue</span>' : '<span class="resident-status resident-status-moved">Borrowed</span>';
};
$render_results = static function () use ($records, $total, $first, $last, $total_pages, $page, $page_url, $status_badge, $show, $search): void {
    if ($records === []): ?>
        <div class="dashboard-empty-state"><?= $search !== '' || $show !== 'active' ? 'No matching records found.' : 'No items are borrowed right now.' ?></div>
    <?php else: ?>
        <p class="activity-history-meta">Showing <?= e((string) $first) ?>–<?= e((string) $last) ?> of <?= e((string) $total) ?> record<?= $total === 1 ? '' : 's' ?></p>
        <div class="resident-table-wrap">
            <table class="resident-table inventory-table">
                <thead><tr><th scope="col">Reference</th><th scope="col">Item</th><th scope="col">Borrower</th><th scope="col">Quantity</th><th scope="col">Borrowed</th><th scope="col">Return by</th><th scope="col">Status</th><th scope="col" class="inventory-actions-head">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($records as $row): ?>
                    <tr class="<?= inventory_is_overdue($row) ? 'inventory-row-attention' : '' ?>">
                        <td class="resident-name"><?= e($row['borrow_code']) ?></td>
                        <td><a class="activity-detail-link" href="inventory_view.php?id=<?= e((string) $row['item_id']) ?>"><?= e($row['item_code']) ?></a> <?= e($row['item_name']) ?></td>
                        <td><?= e($row['borrower_name']) ?><span class="inventory-sub"><?= e($row['borrower_address']) ?><?= $row['borrower_contact'] ? ' · ' . e($row['borrower_contact']) : '' ?></span></td>
                        <td><?= e(number_format((int) $row['quantity']) . ' ' . $row['unit']) ?><?php if ($row['actual_return_date'] !== null && ((int) $row['missing_quantity'] > 0 || (int) $row['damaged_quantity'] > 0)): ?><span class="inventory-sub"><?= (int) $row['missing_quantity'] > 0 ? e($row['missing_quantity']) . ' missing ' : '' ?><?= (int) $row['damaged_quantity'] > 0 ? e($row['damaged_quantity']) . ' damaged' : '' ?></span><?php endif; ?></td>
                        <td><?= e(inventory_format_date($row['date_borrowed'])) ?></td>
                        <td><?= e(inventory_format_date($row['expected_return_date'])) ?><?php if ($row['actual_return_date'] !== null): ?><span class="inventory-sub">Returned <?= e(inventory_format_date($row['actual_return_date'])) ?></span><?php endif; ?></td>
                        <td><?= $status_badge($row) ?></td>
                        <td><div class="management-actions inventory-actions"><?php if ($row['actual_return_date'] === null): ?><a class="btn btn-sm btn-outline-primary" href="inventory_return.php?borrow=<?= e((string) $row['id']) ?>">Mark as Returned</a><?php else: ?><span class="activity-detail-muted">Closed</span><?php endif; ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($records as $row): ?>
                <li class="resident-card<?= inventory_is_overdue($row) ? ' inventory-row-attention' : '' ?>">
                    <div class="resident-card-top"><strong><?= e($row['borrow_code'] . ' · ' . $row['item_name']) ?></strong><?= $status_badge($row) ?></div>
                    <p><?= e($row['borrower_name']) ?> · <?= e(number_format((int) $row['quantity']) . ' ' . $row['unit']) ?></p>
                    <p>Borrowed <?= e(inventory_format_date($row['date_borrowed'])) ?> · Return by <?= e(inventory_format_date($row['expected_return_date'])) ?></p>
                    <?php if ($row['actual_return_date'] === null): ?><div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="inventory_return.php?borrow=<?= e((string) $row['id']) ?>">Mark as Returned</a></div><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <nav class="activity-pagination" aria-label="Borrowed items pagination">
                <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($page_url($page - 1)) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($page_url($page + 1)) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif;
};
if (live_search_is_request()) live_search_respond($render_results);

$page_title = 'Borrowed Items'; $active_page = 'inventory';
$page_styles = ['assets/css/inventory.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <a class="announcement-back" href="inventory.php"><span aria-hidden="true">&larr;</span> Back to Inventory</a>
    <div class="page-heading"><div><h1>Borrowed Items</h1><p><?= e((string) ($counts['active'] ?? 0)) ?> active borrow<?= ($counts['active'] ?? 0) === 1 ? '' : 's' ?><?= ($counts['overdue'] ?? 0) > 0 ? ' · <strong>' . e((string) $counts['overdue']) . ' overdue</strong>' : '' ?>. To lend an item, open it in Inventory and select Borrow.</p></div></div>
    <?php if ($success = flash('inventory_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('inventory_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters" method="get" action="inventory_borrowed.php" data-live-search data-live-target="#borrowed-results">
            <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="borrowed-search">Search</label><input class="activity-filter-input" type="search" id="borrowed-search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Reference, borrower, address or item" autocomplete="off" data-live-query></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="borrowed-show">Show</label><select class="activity-filter-select" id="borrowed-show" name="show"><?php foreach (['active' => 'Currently borrowed', 'overdue' => 'Overdue only', 'returned' => 'Returned', 'all' => 'All records'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $show === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="inventory_borrowed.php" data-live-reset>Reset</a></div>
        </form>
        <div id="borrowed-results" class="live-search-results"><?php $render_results(); ?></div>
    </section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
