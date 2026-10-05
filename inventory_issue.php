<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/inventory.php';
inventory_require_manage();
$connection = db();
if (!inventory_ready($connection) || !inventory_borrowing_ready($connection)) { flash('inventory_error', 'Issuing supplies needs its database tables first.'); redirect('inventory.php'); }

// Issue supplies (for example 2 reams of bond paper to the Secretary). Lowers the quantity on hand for good; the Low
// Stock rule is applied afterwards.
$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$inv = inventory_find($connection, $id);
if ($inv === null) { http_response_code(404); exit('Inventory item not found.'); }
if (!inventory_can_issue($inv)) { flash('inventory_error', $inv['item_code'] . ' cannot be issued (only supplies with stock on hand can be issued).'); redirect('inventory_view.php?id=' . $id); }

$values = ['quantity' => '1', 'recipient' => '', 'reason' => ''];
$errors = [];
$form_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = inventory_validate_issue($_POST, (int) $inv['quantity']);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    if ($form_error === null && $errors === []) {
        try {
            $connection->beginTransaction();
            $locked = inventory_find($connection, $id, true);
            if ($locked === null || !inventory_can_issue($locked)) throw new RuntimeException('This supply can no longer be issued. Reload the page to see its current status.');
            if ($values['quantity'] > (int) $locked['quantity']) throw new RuntimeException('Only ' . number_format((int) $locked['quantity']) . ' ' . $locked['unit'] . ' are on hand now. Adjust the quantity.');
            $on_hand = (int) $locked['quantity'] - $values['quantity'];
            $status = inventory_status_after($locked, $on_hand, 0);
            $connection->prepare('UPDATE inventory_items SET quantity = :quantity, status = :status, updated_by = :user WHERE id = :id')->execute(['quantity' => $on_hand, 'status' => $status, 'user' => current_user()['id'], 'id' => $id]);
            inventory_log_movement($connection, $id, 'issued', -$values['quantity'], $on_hand, $values['reason'], $status !== $locked['status'] ? (string) $locked['status'] : null, $status !== $locked['status'] ? $status : null, $values['recipient']);
            inventory_audit($connection, $id, 'inventory_issued', ['item_code' => $locked['item_code'], 'quantity' => $values['quantity'], 'recipient' => $values['recipient']]);
            $connection->commit();
            flash('inventory_success', 'Issued ' . $values['quantity'] . ' ' . $locked['unit'] . ' of ' . $locked['name'] . ' to ' . $values['recipient'] . '. ' . number_format($on_hand) . ' left on hand' . ($status === 'low_stock' ? ' (Low Stock).' : '.'));
            redirect('inventory_view.php?id=' . $id);
        } catch (PDOException) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = 'The supplies could not be issued. No changes were made.';
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        }
    }
}
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
$page_title = 'Issue Supplies'; $active_page = 'inventory';
$page_styles = ['assets/css/inventory.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="inventory_view.php?id=<?= e((string) $id) ?>"><span aria-hidden="true">&larr;</span> Back to item</a>
    <div class="page-heading"><div><span class="eyebrow"><?= e($inv['item_code']) ?></span><h1>Issue: <?= e($inv['name']) ?></h1><p><?= e(number_format((int) $inv['quantity']) . ' ' . $inv['unit']) ?> on hand<?= $inv['reorder_level'] !== null ? ' · reorder level ' . e(number_format((int) $inv['reorder_level'])) : '' ?>.</p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $id) ?>">
        <fieldset class="resident-section">
            <legend>Issue Details</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="quantity">Quantity (<?= e($inv['unit']) ?>) <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('quantity') ?>" type="number" id="quantity" name="quantity" min="1" max="<?= e((string) $inv['quantity']) ?>" step="1" value="<?= e($val('quantity')) ?>" required data-summary-label="Quantity"><?= $field_error('quantity') ?></div>
                <div><label class="form-label" for="recipient">Issued to <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('recipient') ?>" id="recipient" name="recipient" maxlength="150" value="<?= e($val('recipient')) ?>" required placeholder="e.g. Barangay Secretary" data-summary-label="Issued to"><?= $field_error('recipient') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="reason">Purpose <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('reason') ?>" id="reason" name="reason" maxlength="255" value="<?= e($val('reason')) ?>" required placeholder="e.g. Printing of clearances" data-summary-label="Purpose"><?= $field_error('reason') ?></div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="Issue these supplies?" data-dialog-message="The quantity on hand will go down. This is recorded in the movement history." data-dialog-confirm="Issue Supplies" data-dialog-dismiss="Cancel">Issue Supplies</button>
            <a class="btn btn-light" href="inventory_view.php?id=<?= e((string) $id) ?>">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
