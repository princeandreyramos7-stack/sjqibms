<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/inventory.php';
inventory_require_view();
$connection = db();
$can_manage = inventory_can_manage();   // Health Workers: read-only, Medical items only
if (!inventory_ready($connection)) { flash('inventory_error', 'Inventory needs its database tables first.'); redirect('inventory.php'); }
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$inventory_item = $id ? inventory_find($connection, $id) : null;
if ($inventory_item === null || (inventory_medical_only() && (int) $inventory_item['category_id'] !== (int) inventory_medical_category_id($connection))) { http_response_code(404); exit('Inventory item not found.'); }

$archived = $inventory_item['archived_at'] !== null;
$muted = '<span class="activity-detail-muted">Not recorded</span>';
$show = static fn (?string $value): string => $value !== null && $value !== '' ? e($value) : $muted;
$expiring = $inventory_item['expiry_date'] !== null && $inventory_item['expiry_date'] <= date('Y-m-d', strtotime('+30 days'));
// Borrowing and movement history (available once the Phase 3 tables exist).
$tracking = inventory_borrowing_ready($connection);
$borrowed_out = $tracking ? inventory_borrowed_out($connection, (int) $inventory_item['id']) : 0;
$open_borrows = [];
$movements = [];
if ($tracking) {
    $statement = $connection->prepare('SELECT id, borrow_code, borrower_name, borrower_address, quantity, date_borrowed, expected_return_date, actual_return_date FROM inventory_borrow_records WHERE item_id = :id AND actual_return_date IS NULL ORDER BY expected_return_date, id');
    $statement->execute(['id' => $inventory_item['id']]);
    $open_borrows = $statement->fetchAll();
    $statement = $connection->prepare('SELECT m.movement_type, m.quantity_change, m.quantity_after, m.status_from, m.status_to, m.reason, m.recipient, m.created_at, u.name AS user_name FROM inventory_movements m LEFT JOIN users u ON u.id = m.performed_by WHERE m.item_id = :id ORDER BY m.created_at DESC, m.id DESC LIMIT 100');
    $statement->execute(['id' => $inventory_item['id']]);
    $movements = $statement->fetchAll();
}
$page_title = 'Inventory Item'; $active_page = 'inventory';
$page_styles = ['assets/css/inventory.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($success = flash('inventory_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if ($failure = flash('inventory_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
<article class="dashboard-panel resident-detail">
    <div class="page-heading">
        <div>
            <a class="announcement-back" href="inventory.php"><span aria-hidden="true">&larr;</span> Back to Inventory</a>
            <span class="eyebrow"><?= e($inventory_item['item_code']) ?> · <?= e(inventory_types()[$inventory_item['item_type']] ?? '') ?></span>
            <h1><?= e($inventory_item['name']) ?></h1>
            <p><?= $archived ? '<span class="resident-status resident-status-inactive">Archived</span>' : inventory_status_badge($inventory_item['status']) ?> <span class="resident-detail-meta"><?= e(inventory_quantity_label($inventory_item)) ?> · <?= e($inventory_item['location_name']) ?></span></p>
        </div>
        <?php if ($can_manage): ?><div class="resident-detail-actions">
            <?php if ($tracking && inventory_can_borrow($inventory_item)): ?><a class="btn btn-light resident-action-btn" href="inventory_borrow.php?id=<?= e((string) $inventory_item['id']) ?>">Borrow</a><?php endif; ?>
            <?php if ($tracking && inventory_can_issue($inventory_item)): ?><a class="btn btn-light resident-action-btn" href="inventory_issue.php?id=<?= e((string) $inventory_item['id']) ?>">Issue Supplies</a><?php endif; ?>
            <?php if (!$archived): ?><a class="btn announcement-edit-btn" href="inventory_form.php?id=<?= e((string) $inventory_item['id']) ?>">Edit Item</a><?php endif; ?>
            <form method="post" action="inventory_action.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $inventory_item['id']) ?>"><input type="hidden" name="back" value="view"><input type="hidden" name="action" value="<?= $archived ? 'restore' : 'archive' ?>"><button class="btn <?= $archived ? 'btn-light resident-action-btn' : 'btn-outline-danger' ?>" type="submit" data-form-confirm="custom" data-dialog-heading="<?= $archived ? 'Restore this item?' : 'Archive this item?' ?>" data-dialog-message="<?= e($archived ? 'The item will appear in the inventory list again.' : 'The item will be hidden from the inventory list. The record is kept and can be restored.') ?>" data-dialog-confirm="<?= $archived ? 'Restore' : 'Archive' ?>" data-dialog-dismiss="Cancel"<?= $archived ? '' : ' data-dialog-danger="true"' ?>><?= $archived ? 'Restore Item' : 'Archive Item' ?></button></form>
        </div><?php endif; ?>
    </div>
    <?php if ($archived): ?><p class="dashboard-status warning" role="status">Archived on <?= e(inventory_format_date($inventory_item['archived_at'])) ?><?= $inventory_item['archived_by_name'] ? ' by ' . e($inventory_item['archived_by_name']) : '' ?>. Restore the item to edit it.</p><?php endif; ?>

    <div class="resident-detail-grid">
        <section class="resident-detail-section">
            <h2>Item Information</h2>
            <dl class="activity-detail-list">
                <div><dt>Item code</dt><dd><?= e($inventory_item['item_code']) ?></dd></div>
                <div><dt>Name</dt><dd><?= e($inventory_item['name']) ?></dd></div>
                <div><dt>Type</dt><dd><?= e(inventory_types()[$inventory_item['item_type']] ?? '') ?></dd></div>
                <div><dt>Category</dt><dd><?= e($inventory_item['category_name']) ?></dd></div>
                <div><dt>Location</dt><dd><?= e($inventory_item['location_name']) ?></dd></div>
                <div><dt>Accountable person</dt><dd><?= $show($inventory_item['custodian']) ?></dd></div>
                <div><dt>Description</dt><dd class="resident-wrap"><?= $inventory_item['description'] ? nl2br(e($inventory_item['description'])) : $muted ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Quantity and Status</h2>
            <dl class="activity-detail-list">
                <div><dt><?= $tracking ? 'On hand' : 'Quantity' ?></dt><dd><?= e(inventory_quantity_label($inventory_item)) ?></dd></div>
                <?php if ($tracking && $inventory_item['item_type'] === 'equipment'): ?>
                    <div><dt>Borrowed out</dt><dd><?= e(number_format($borrowed_out) . ' ' . $inventory_item['unit']) ?></dd></div>
                    <div><dt>Total owned</dt><dd><?= e(number_format((int) $inventory_item['quantity'] + $borrowed_out) . ' ' . $inventory_item['unit']) ?></dd></div>
                <?php endif; ?>
                <div><dt>Status</dt><dd><?= inventory_status_badge($inventory_item['status']) ?></dd></div>
                <div><dt>Condition</dt><dd><?= e(inventory_conditions()[$inventory_item['item_condition']] ?? '') ?></dd></div>
                <?php if ($inventory_item['item_type'] === 'supply'): ?><div><dt>Reorder level</dt><dd><?= $inventory_item['reorder_level'] !== null ? e(number_format((int) $inventory_item['reorder_level']) . ' ' . $inventory_item['unit']) : $muted ?></dd></div><?php endif; ?>
                <div><dt>Expiry date</dt><dd><?= $inventory_item['expiry_date'] ? e(inventory_format_date($inventory_item['expiry_date'])) . ($expiring ? ' <span class="resident-status resident-status-pending">' . ($inventory_item['expiry_date'] < date('Y-m-d') ? 'Expired' : 'Expiring soon') . '</span>' : '') : $muted ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Identification and Acquisition</h2>
            <dl class="activity-detail-list">
                <div><dt>Serial number</dt><dd><?= $show($inventory_item['serial_number']) ?></dd></div>
                <div><dt>Property number</dt><dd><?= $show($inventory_item['property_number']) ?></dd></div>
                <div><dt>Date acquired</dt><dd><?= $inventory_item['date_acquired'] ? e(inventory_format_date($inventory_item['date_acquired'])) : $muted ?></dd></div>
                <div><dt>Unit cost</dt><dd><?= $inventory_item['unit_cost'] !== null ? e(inventory_money($inventory_item['unit_cost'])) : $muted ?></dd></div>
                <?php if ($inventory_item['unit_cost'] !== null): ?><div><dt>Total value</dt><dd><?= e(inventory_money((float) $inventory_item['unit_cost'] * ((int) $inventory_item['quantity'] + $borrowed_out))) ?></dd></div><?php endif; ?>
                <div><dt>Source of funds</dt><dd><?= $inventory_item['source_of_funds'] ? e(inventory_fund_sources()[$inventory_item['source_of_funds']] ?? '') : $muted ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Photo and Remarks</h2>
            <?php if (inventory_photo_path($inventory_item['photo_filename']) !== null): ?>
                <img class="inventory-photo" src="inventory_photo.php?id=<?= e((string) $inventory_item['id']) ?>" alt="Photo of <?= e($inventory_item['name']) ?>">
            <?php else: ?>
                <p class="resident-static">No photo uploaded.</p>
            <?php endif; ?>
            <dl class="activity-detail-list">
                <div><dt>Remarks</dt><dd class="resident-wrap"><?= $inventory_item['remarks'] ? nl2br(e($inventory_item['remarks'])) : $muted ?></dd></div>
                <div><dt>Recorded</dt><dd><?= e(inventory_format_date($inventory_item['created_at'])) ?><?= $inventory_item['created_by_name'] ? ' · ' . e($inventory_item['created_by_name']) : '' ?></dd></div>
                <div><dt>Last updated</dt><dd><?= e(inventory_format_date($inventory_item['updated_at'])) ?><?= $inventory_item['updated_by_name'] ? ' · ' . e($inventory_item['updated_by_name']) : '' ?></dd></div>
            </dl>
        </section>
    </div>

    <?php if ($can_manage): ?><section class="resident-detail-section inventory-history">
        <div class="inventory-history-head"><h2>QR Code</h2><a class="btn btn-sm btn-outline-primary" href="inventory_labels.php?id=<?= e((string) $inventory_item['id']) ?>" target="_blank" rel="noopener">Print Label</a></div>
        <div class="inventory-qr-box">
            <div class="inventory-qr" data-qr-url="<?= e(inventory_item_url((int) $inventory_item['id'])) ?>" role="img" aria-label="QR code for <?= e($inventory_item['item_code']) ?>"></div>
            <div><p>Scanning this code opens this item's details page (sign-in required).</p><p class="inventory-qr-url"><?= e(inventory_item_url((int) $inventory_item['id'])) ?></p></div>
        </div>
    </section><?php endif; ?>

    <?php if ($tracking): ?>
        <?php if ($can_manage && $inventory_item['item_type'] === 'equipment'): ?>
            <section class="resident-detail-section inventory-history">
                <div class="inventory-history-head"><h2>Currently Borrowed</h2><a class="activity-detail-link" href="inventory_borrowed.php?q=<?= e(rawurlencode($inventory_item['item_code'])) ?>&amp;show=all">All borrow records</a></div>
                <?php if ($open_borrows === []): ?>
                    <p class="resident-static">Nothing is borrowed right now.</p>
                <?php else: ?>
                    <div class="resident-table-wrap inventory-history-table"><table class="resident-table"><thead><tr><th scope="col">Reference</th><th scope="col">Borrower</th><th scope="col">Quantity</th><th scope="col">Borrowed</th><th scope="col">Return by</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead><tbody>
                        <?php foreach ($open_borrows as $borrow): ?><tr class="<?= inventory_is_overdue($borrow) ? 'inventory-row-attention' : '' ?>"><td class="resident-name"><?= e($borrow['borrow_code']) ?></td><td><?= e($borrow['borrower_name']) ?><span class="inventory-sub"><?= e($borrow['borrower_address']) ?></span></td><td><?= e(number_format((int) $borrow['quantity']) . ' ' . $inventory_item['unit']) ?></td><td><?= e(inventory_format_date($borrow['date_borrowed'])) ?></td><td><?= e(inventory_format_date($borrow['expected_return_date'])) ?><?= inventory_is_overdue($borrow) ? ' <span class="resident-status resident-status-deceased">Overdue</span>' : '' ?></td><td><a class="btn btn-sm btn-outline-primary" href="inventory_return.php?borrow=<?= e((string) $borrow['id']) ?>">Mark as Returned</a></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                    <ul class="resident-cards"><?php foreach ($open_borrows as $borrow): ?><li class="resident-card"><div class="resident-card-top"><strong><?= e($borrow['borrow_code'] . ' · ' . $borrow['borrower_name']) ?></strong><?= inventory_is_overdue($borrow) ? '<span class="resident-status resident-status-deceased">Overdue</span>' : '' ?></div><p><?= e(number_format((int) $borrow['quantity']) . ' ' . $inventory_item['unit']) ?> · Return by <?= e(inventory_format_date($borrow['expected_return_date'])) ?></p><div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="inventory_return.php?borrow=<?= e((string) $borrow['id']) ?>">Mark as Returned</a></div></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <section class="resident-detail-section inventory-history">
            <div class="inventory-history-head"><h2>Movement History</h2><span class="activity-detail-muted">Every change to the quantity on hand or status (latest 100)</span></div>
            <?php if ($movements === []): ?>
                <p class="resident-static">No movements recorded yet.</p>
            <?php else: ?>
                <div class="resident-table-wrap inventory-history-table"><table class="resident-table"><thead><tr><th scope="col">Date &amp; time</th><th scope="col">Movement</th><th scope="col">Change</th><th scope="col">On hand after</th><th scope="col">Details</th><th scope="col">By</th></tr></thead><tbody>
                    <?php foreach ($movements as $movement): $change = (int) $movement['quantity_change']; ?>
                        <tr>
                            <td class="audit-time"><?= e(date('M j, Y g:i A', strtotime((string) $movement['created_at']))) ?></td>
                            <td><strong><?= e(inventory_movement_types()[$movement['movement_type']] ?? $movement['movement_type']) ?></strong></td>
                            <td class="inventory-change <?= $change > 0 ? 'is-up' : ($change < 0 ? 'is-down' : '') ?>"><?= $change === 0 ? '—' : e(($change > 0 ? '+' : '−') . number_format(abs($change))) ?></td>
                            <td><?= e(number_format((int) $movement['quantity_after']) . ' ' . $inventory_item['unit']) ?></td>
                            <td><?= $movement['reason'] ? e($movement['reason']) : '' ?><?php if ($movement['recipient']): ?><span class="inventory-sub"><?= $movement['movement_type'] === 'issued' ? 'Issued to ' : 'Borrower: ' ?><?= e($movement['recipient']) ?></span><?php endif; ?><?php if ($movement['status_to']): ?><span class="inventory-sub">Status: <?= e(inventory_statuses()[$movement['status_from']] ?? 'New') ?> → <?= e(inventory_statuses()[$movement['status_to']] ?? $movement['status_to']) ?></span><?php endif; ?></td>
                            <td><?= $movement['user_name'] ? e($movement['user_name']) : '<span class="activity-detail-muted">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <ul class="resident-cards"><?php foreach ($movements as $movement): $change = (int) $movement['quantity_change']; ?><li class="resident-card"><div class="resident-card-top"><strong><?= e(inventory_movement_types()[$movement['movement_type']] ?? $movement['movement_type']) ?></strong><span><?= $change === 0 ? '' : e(($change > 0 ? '+' : '−') . number_format(abs($change))) ?></span></div><p><?= e(date('M j, Y g:i A', strtotime((string) $movement['created_at']))) ?><?= $movement['user_name'] ? ' · ' . e($movement['user_name']) : '' ?></p><?php if ($movement['reason']): ?><p><?= e($movement['reason']) ?></p><?php endif; ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</article>
<?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<?= inventory_qr_script_tags() ?>
