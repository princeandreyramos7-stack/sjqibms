<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/inventory.php';
require_once __DIR__ . '/includes/live_search.php';
inventory_require_view();
$connection = db();
$ready = inventory_ready($connection);
// Health Workers: read-only, and only the items of the "Medical" category (forced on the server, whatever the URL says).
$can_manage = inventory_can_manage();
$medical_only = inventory_medical_only();

// Inventory list: type tabs, summary cards, filters, sortable columns and pagination. Every setting lives in the URL
// query string, and filtering, sorting and paging all run in SQL (quantity sorts as a number).
$state = inventory_list_state($_GET, $ready ? $connection : null);
if ($medical_only) $state['category'] = $ready ? (inventory_medical_category_id($connection) ?? -1) : -1;
$records = [];
$total = 0;
$page = 1;
$total_pages = 1;
$cards = [];
if ($ready) {
    [$where, $params] = inventory_list_where($state, $connection);
    $from = ' FROM inventory_items i INNER JOIN inventory_categories c ON c.id = i.category_id INNER JOIN inventory_locations l ON l.id = i.location_id WHERE ' . implode(' AND ', $where);
    $count = $connection->prepare('SELECT COUNT(*)' . $from);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $total_pages = max(1, (int) ceil($total / $state['per_page']));
    $page = max(1, min($total_pages, $state['page']));
    $statement = $connection->prepare('SELECT i.id, i.item_code, i.name, i.item_type, i.quantity, i.unit, i.status, i.expiry_date, i.archived_at, c.name AS category_name, l.name AS location_name' . $from . ' ORDER BY ' . inventory_sort_sql($state['sort'], $state['dir']) . ' LIMIT :limit OFFSET :offset');
    foreach ($params as $key => $value) $statement->bindValue($key, $value);
    $statement->bindValue('limit', $state['per_page'], PDO::PARAM_INT);
    $statement->bindValue('offset', ($page - 1) * $state['per_page'], PDO::PARAM_INT);
    $statement->execute();
    $records = $statement->fetchAll();
    $cards = $medical_only ? [] : inventory_summary_counts($connection, $state['type']);
}
$first = $total === 0 ? 0 : ($page - 1) * $state['per_page'] + 1;
$last = min($total, $page * $state['per_page']);
$url = static fn (array $changes = []): string => 'inventory.php' . (($query = inventory_query_string(array_merge($state, ['page' => $page], $changes))) !== '' ? '?' . $query : '');
$return = inventory_query_string(array_merge($state, ['page' => $page]));

// Archive / Restore button (POST with confirmation; handled by inventory_action.php).
$archive_button = static function (array $row) use ($return): string {
    $archived = $row['archived_at'] !== null;
    return '<form method="post" action="inventory_action.php" class="doc-action-form">' . csrf_field()
        . '<input type="hidden" name="id" value="' . e((string) $row['id']) . '"><input type="hidden" name="action" value="' . ($archived ? 'restore' : 'archive') . '"><input type="hidden" name="return" value="' . e($return) . '">'
        . '<button class="btn btn-sm ' . ($archived ? 'btn-outline-primary' : 'btn-outline-danger') . '" type="submit" data-form-confirm="custom" data-dialog-heading="' . ($archived ? 'Restore this item?' : 'Archive this item?') . '" data-dialog-message="' . e($archived ? $row['item_code'] . ' — ' . $row['name'] . ' will appear in the inventory list again.' : $row['item_code'] . ' — ' . $row['name'] . ' will be hidden from the inventory list. The record is kept and can be restored.') . '" data-dialog-confirm="' . ($archived ? 'Restore' : 'Archive') . '" data-dialog-dismiss="Cancel"' . ($archived ? '' : ' data-dialog-danger="true"') . '>' . ($archived ? 'Restore' : 'Archive') . '</button></form>';
};

// Borrow (equipment) or Issue (supplies) shortcut for eligible items, once the borrowing tables exist.
$tracking = $ready && inventory_borrowing_ready($connection);
$extra_action = static function (array $row) use ($tracking): string {
    if (!$tracking) return '';
    if (inventory_can_borrow($row)) return '<a class="btn btn-sm btn-outline-primary" href="inventory_borrow.php?id=' . e((string) $row['id']) . '">Borrow</a>';
    if (inventory_can_issue($row)) return '<a class="btn btn-sm btn-outline-primary" href="inventory_issue.php?id=' . e((string) $row['id']) . '">Issue</a>';
    return '';
};
$overdue = 0;
if ($tracking) {
    $statement = $connection->prepare('SELECT COUNT(*) FROM inventory_borrow_records WHERE actual_return_date IS NULL AND expected_return_date < :today');
    $statement->execute(['today' => date('Y-m-d')]);
    $overdue = (int) $statement->fetchColumn();
}
// Sortable header: clicking the active column flips the direction; a new column starts ascending.
$sort_header = static function (string $key, string $label) use ($state, $url): string {
    $active = $state['sort'] === $key;
    $dir = $active && $state['dir'] === 'asc' ? 'desc' : 'asc';
    $aria = $active ? ($state['dir'] === 'asc' ? 'ascending' : 'descending') : 'none';
    $arrow = $active ? ($state['dir'] === 'asc' ? '▲' : '▼') : '↕';
    return '<th scope="col" aria-sort="' . $aria . '"><a class="inventory-sort' . ($active ? ' is-active' : '') . '" href="' . e($url(['sort' => $key, 'dir' => $dir, 'page' => 1])) . '" data-live-page>' . e($label) . ' <span aria-hidden="true">' . $arrow . '</span></a></th>';
};

$render_results = static function () use ($records, $total, $first, $last, $total_pages, $page, $state, $url, $cards, $archive_button, $sort_header, $extra_action, $can_manage, $medical_only): void {
    $today = date('Y-m-d');
    $soon = date('Y-m-d', strtotime('+30 days'));
    ?>
    <div hidden data-inventory-state="<?= e(json_encode(['type' => $state['type'], 'sort' => $state['sort'], 'dir' => $state['dir'], 'expiring' => $state['expiring'] ? '1' : '', 'status' => $state['status']], JSON_THROW_ON_ERROR)) ?>"></div>
    <nav class="announcement-tabs page-tabs inventory-tabs" aria-label="Item type">
        <?php foreach (['' => 'All', 'equipment' => 'Equipment', 'supply' => 'Supplies'] as $type => $label): ?>
            <a class="announcement-tab <?= $state['type'] === $type ? 'active' : '' ?>" href="<?= e($url(['type' => $type, 'page' => 1])) ?>" data-live-page <?= $state['type'] === $type ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if (!$medical_only): ?><div class="inventory-cards" role="list">
        <?php foreach (inventory_card_definitions() as $key => $card):
            $active = $key === 'total' ? ($state['status'] === '' && !$state['expiring']) : ($key === 'expiring' ? $state['expiring'] : $state['status'] === $key);
            $changes = $key === 'total' ? ['status' => '', 'expiring' => false] : ($key === 'expiring' ? ['status' => '', 'expiring' => true] : ['status' => $key, 'expiring' => false]); ?>
            <a class="inventory-card tone-<?= e($card['tone']) ?><?= $active ? ' is-selected' : '' ?>" role="listitem" href="<?= e($url($changes + ['page' => 1])) ?>" data-live-page <?= $active ? 'aria-current="true"' : '' ?>>
                <span class="inventory-card-count"><?= e(number_format($cards[$key] ?? 0)) ?></span>
                <span class="inventory-card-label"><?= e($card['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div><?php endif; ?>
    <?php if ($records === []): ?>
        <div class="dashboard-empty-state"><?= inventory_state_filtered($state) ? 'No matching records found.' : ($medical_only ? 'No medicines or medical supplies are recorded yet.' : 'No inventory items yet. Select Add Item to record the first one.') ?></div>
    <?php else: ?>
        <?php $export_query = inventory_query_string(array_merge($state, ['page' => 1])); ?>
        <div class="inventory-results-head">
            <p class="activity-history-meta">Showing <?= e((string) $first) ?>–<?= e((string) $last) ?> of <?= e((string) $total) ?> record<?= $total === 1 ? '' : 's' ?></p>
            <?php if ($can_manage): ?><div class="inventory-exports" aria-label="Export the filtered list">
                <a class="btn btn-sm btn-outline-secondary" href="inventory_export.php<?= $export_query !== '' ? '?' . e($export_query) : '' ?>">Export Excel</a>
                <a class="btn btn-sm btn-outline-secondary" href="inventory_report.php?<?= e($export_query !== '' ? $export_query . '&' : '') ?>print=1" target="_blank" rel="noopener">Export PDF</a>
                <a class="btn btn-sm btn-outline-secondary" href="inventory_report.php<?= $export_query !== '' ? '?' . e($export_query) : '' ?>" target="_blank" rel="noopener">Print Report</a>
                <a class="btn btn-sm btn-outline-secondary" href="inventory_labels.php<?= $export_query !== '' ? '?' . e($export_query) : '' ?>" target="_blank" rel="noopener">QR Labels</a>
            </div><?php endif; ?>
        </div>
        <div class="resident-table-wrap">
            <table class="resident-table inventory-table">
                <thead><tr><?= $sort_header('code', 'Item Code') . $sort_header('name', 'Item') . $sort_header('category', 'Category') . $sort_header('quantity', 'Quantity') . $sort_header('location', 'Location') . $sort_header('status', 'Status') ?><th scope="col" class="inventory-actions-head">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($records as $row):
                    $expiring = $row['expiry_date'] !== null && $row['expiry_date'] <= $soon;
                    $attention = $row['archived_at'] === null && (in_array($row['status'], ['low_stock', 'for_repair'], true) || $expiring); ?>
                    <tr class="<?= $attention ? 'inventory-row-attention' : '' ?>">
                        <td class="resident-name"><?= e($row['item_code']) ?></td>
                        <td><?= e($row['name']) ?><span class="inventory-sub"><?= e(inventory_types()[$row['item_type']] ?? '') ?><?= $expiring ? ' · ' . ($row['expiry_date'] < $today ? 'Expired ' : 'Expires ') . e(inventory_format_date($row['expiry_date'])) : '' ?></span></td>
                        <td><?= e($row['category_name']) ?></td>
                        <td><?= e(inventory_quantity_label($row)) ?></td>
                        <td><?= e($row['location_name']) ?></td>
                        <td><?= $row['archived_at'] !== null ? '<span class="resident-status resident-status-inactive">Archived</span>' : inventory_status_badge($row['status']) ?></td>
                        <td><div class="management-actions inventory-actions"><a class="btn btn-sm btn-outline-primary" href="inventory_view.php?id=<?= e((string) $row['id']) ?>">View</a><?php if ($can_manage): ?><?php if ($row['archived_at'] === null): ?><a class="btn btn-sm btn-outline-primary" href="inventory_form.php?id=<?= e((string) $row['id']) ?>">Edit</a><?php endif; ?><?= $extra_action($row) ?><?= $archive_button($row) ?><?php endif; ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($records as $row):
                $expiring = $row['expiry_date'] !== null && $row['expiry_date'] <= $soon;
                $attention = $row['archived_at'] === null && (in_array($row['status'], ['low_stock', 'for_repair'], true) || $expiring); ?>
                <li class="resident-card<?= $attention ? ' inventory-row-attention' : '' ?>">
                    <div class="resident-card-top"><strong><?= e($row['item_code'] . ' · ' . $row['name']) ?></strong><?= $row['archived_at'] !== null ? '<span class="resident-status resident-status-inactive">Archived</span>' : inventory_status_badge($row['status']) ?></div>
                    <p><?= e($row['category_name']) ?> · <?= e(inventory_quantity_label($row)) ?> · <?= e($row['location_name']) ?></p>
                    <?php if ($expiring): ?><p><?= $row['expiry_date'] < $today ? 'Expired ' : 'Expires ' ?><?= e(inventory_format_date($row['expiry_date'])) ?></p><?php endif; ?>
                    <div class="management-actions inventory-actions"><a class="btn btn-sm btn-outline-primary" href="inventory_view.php?id=<?= e((string) $row['id']) ?>">View</a><?php if ($can_manage): ?><?php if ($row['archived_at'] === null): ?><a class="btn btn-sm btn-outline-primary" href="inventory_form.php?id=<?= e((string) $row['id']) ?>">Edit</a><?php endif; ?><?= $extra_action($row) ?><?= $archive_button($row) ?><?php endif; ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <nav class="activity-pagination" aria-label="Inventory pagination">
                <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page - 1])) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page + 1])) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif;
};
if ($ready && live_search_is_request()) live_search_respond($render_results);

$categories = $ready ? inventory_list($connection, 'categories') : [];
$locations = $ready ? inventory_list($connection, 'locations') : [];
$page_title = 'Inventory'; $active_page = 'inventory';
$page_styles = ['assets/css/inventory.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Inventory</h1><p><?= $medical_only ? 'Medicines and medical supplies of the barangay (view only).' : 'Barangay property, equipment, and supplies.' ?></p></div>
        <?php if ($ready && $can_manage): ?><div class="resident-detail-actions"><a class="btn btn-primary announcements-new-btn" href="inventory_form.php">Add Item</a><?php if ($tracking): ?><a class="btn btn-light resident-action-btn" href="inventory_borrowed.php">Borrowed Items<?= $overdue > 0 ? ' <span class="inventory-overdue-count">' . e((string) $overdue) . ' overdue</span>' : '' ?></a><?php endif; ?><a class="btn btn-light resident-action-btn" href="inventory_lists.php">Categories &amp; Locations</a></div><?php endif; ?>
    </div>
    <?php if ($success = flash('inventory_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('inventory_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Inventory needs its database tables before items can be recorded. No records have been changed.</div>
    <?php else: ?>
        <section class="dashboard-panel resident-list-panel">
            <form class="resident-filters inventory-filters" method="get" action="inventory.php" data-live-search data-live-target="#inventory-results" data-inventory-form>
                <?php // Tab, card and sort choices are kept in hidden fields so typing a search keeps them (see assets/js/inventory.js). ?>
                <input type="hidden" name="type" value="<?= e($state['type']) ?>" data-inventory-field="type">
                <input type="hidden" name="expiring" value="<?= $state['expiring'] ? '1' : '' ?>" data-inventory-field="expiring">
                <input type="hidden" name="sort" value="<?= e($state['sort']) ?>" data-inventory-field="sort">
                <input type="hidden" name="dir" value="<?= e($state['dir']) ?>" data-inventory-field="dir">
                <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="inventory-search">Search</label><input class="activity-filter-input" type="search" id="inventory-search" name="q" value="<?= e($state['q']) ?>" maxlength="100" placeholder="Item code, name, custodian or serial no." autocomplete="off" data-live-query></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="inventory-status">Status</label><select class="activity-filter-select" id="inventory-status" name="status" data-inventory-field="status"><option value="">All</option><?php foreach (inventory_statuses() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?><option value="archived" <?= $state['status'] === 'archived' ? 'selected' : '' ?>>Archived</option></select></div>
                <?php if (!$medical_only): ?><div class="resident-filter-group"><label class="activity-filter-label" for="inventory-category">Category</label><select class="activity-filter-select" id="inventory-category" name="category"><option value="">All</option><?php foreach ($categories as $row): ?><option value="<?= e((string) $row['id']) ?>" <?= $state['category'] === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
                <div class="resident-filter-group"><label class="activity-filter-label" for="inventory-location">Location</label><select class="activity-filter-select" id="inventory-location" name="location"><option value="">All</option><?php foreach ($locations as $row): ?><option value="<?= e((string) $row['id']) ?>" <?= $state['location'] === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="inventory-condition">Condition</label><select class="activity-filter-select" id="inventory-condition" name="condition"><option value="">All</option><?php foreach (inventory_conditions() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['condition'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="inventory-per-page">Rows</label><select class="activity-filter-select" id="inventory-per-page" name="per_page"><?php foreach ([10, 25, 50] as $size): ?><option value="<?= $size ?>" <?= $state['per_page'] === $size ? 'selected' : '' ?>><?= $size ?> per page</option><?php endforeach; ?></select></div>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="inventory.php" data-live-reset>Reset</a></div>
            </form>
            <div id="inventory-results" class="live-search-results"><?php $render_results(); ?></div>
        </section>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/inventory.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/inventory.js')) ?>"></script>
