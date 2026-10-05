<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/inventory.php';
inventory_require_manage();
$connection = db();
if (!inventory_ready($connection) || !inventory_borrowing_ready($connection)) { flash('inventory_error', 'Borrowing needs its database tables first.'); redirect('inventory.php'); }

// Mark a borrow as returned. Returned pieces (including damaged ones) go back on hand; missing pieces do not.
$borrow_id = filter_var($_GET['borrow'] ?? $_POST['borrow'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$find = static function (bool $lock = false) use ($connection, $borrow_id): ?array {
    $statement = $connection->prepare('SELECT b.*, i.item_code, i.name AS item_name, i.unit FROM inventory_borrow_records b INNER JOIN inventory_items i ON i.id = b.item_id WHERE b.id = :id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $borrow_id]);
    return $statement->fetch() ?: null;
};
$borrow = $find();
if ($borrow === null) { http_response_code(404); exit('Borrow record not found.'); }
if ($borrow['actual_return_date'] !== null) { flash('inventory_error', $borrow['borrow_code'] . ' was already returned.'); redirect('inventory_borrowed.php'); }

$values = ['actual_return_date' => date('Y-m-d'), 'missing_quantity' => '0', 'damaged_quantity' => '0', 'return_condition' => 'good', 'return_remarks' => ''];
$errors = [];
$form_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = inventory_validate_return($_POST, $borrow);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    if ($form_error === null && $errors === []) {
        try {
            $connection->beginTransaction();
            $locked_borrow = $find(true);
            if ($locked_borrow === null || $locked_borrow['actual_return_date'] !== null) throw new RuntimeException('This borrow was already returned by another action.');
            $stock = inventory_find($connection, (int) $locked_borrow['item_id'], true);
            $returned = (int) $locked_borrow['quantity'] - $values['missing_quantity'];
            $connection->prepare('UPDATE inventory_borrow_records SET actual_return_date = :date, returned_quantity = :returned, damaged_quantity = :damaged, missing_quantity = :missing, return_condition = :condition, return_remarks = :remarks, returned_to = :user WHERE id = :id')
                ->execute(['date' => $values['actual_return_date'], 'returned' => $returned, 'damaged' => $values['damaged_quantity'], 'missing' => $values['missing_quantity'], 'condition' => $values['return_condition'], 'remarks' => $values['return_remarks'], 'user' => current_user()['id'], 'id' => $borrow_id]);
            $on_hand = (int) $stock['quantity'] + $returned;
            $status = inventory_status_after($stock, $on_hand, inventory_borrowed_out($connection, (int) $stock['id']));
            $connection->prepare('UPDATE inventory_items SET quantity = :quantity, status = :status, updated_by = :user WHERE id = :id')->execute(['quantity' => $on_hand, 'status' => $status, 'user' => current_user()['id'], 'id' => $stock['id']]);
            $note = $locked_borrow['borrow_code'] . ' returned' . ($values['damaged_quantity'] > 0 ? ' · ' . $values['damaged_quantity'] . ' damaged' : '') . ($values['missing_quantity'] > 0 ? ' · ' . $values['missing_quantity'] . ' missing' : '');
            inventory_log_movement($connection, (int) $stock['id'], 'returned', $returned, $on_hand, $note, $status !== $stock['status'] ? (string) $stock['status'] : null, $status !== $stock['status'] ? $status : null, $locked_borrow['borrower_name'], $borrow_id);
            inventory_audit($connection, (int) $stock['id'], 'inventory_returned', ['item_code' => $stock['item_code'], 'reference' => $locked_borrow['borrow_code'], 'returned' => $returned, 'damaged' => $values['damaged_quantity'], 'missing' => $values['missing_quantity']]);
            $connection->commit();
            flash('inventory_success', $locked_borrow['borrow_code'] . ' marked as returned: ' . $returned . ' ' . $stock['unit'] . ' back on hand' . ($values['missing_quantity'] > 0 ? ', ' . $values['missing_quantity'] . ' missing' : '') . ($values['damaged_quantity'] > 0 ? ', ' . $values['damaged_quantity'] . ' damaged' : '') . '.');
            redirect('inventory_borrowed.php');
        } catch (PDOException) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = 'The return could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        }
    }
}
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
$page_title = 'Return Borrowed Items'; $active_page = 'inventory';
$page_styles = ['assets/css/inventory.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="inventory_borrowed.php"><span aria-hidden="true">&larr;</span> Back to Borrowed Items</a>
    <div class="page-heading"><div><span class="eyebrow"><?= e($borrow['borrow_code']) ?> · <?= e($borrow['item_code']) ?></span><h1>Mark as Returned</h1><p><?= e($borrow['borrower_name']) ?> borrowed <?= e(number_format((int) $borrow['quantity']) . ' ' . $borrow['unit']) ?> of <?= e($borrow['item_name']) ?> on <?= e(inventory_format_date($borrow['date_borrowed'])) ?> (due <?= e(inventory_format_date($borrow['expected_return_date'])) ?>)<?= inventory_is_overdue($borrow) ? ' — <strong>overdue</strong>' : '' ?>.</p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="borrow" value="<?= e((string) $borrow_id) ?>">
        <fieldset class="resident-section">
            <legend>Return Details</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="actual_return_date">Date returned <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('actual_return_date') ?>" type="date" id="actual_return_date" name="actual_return_date" min="<?= e($borrow['date_borrowed']) ?>" max="<?= e(date('Y-m-d')) ?>" value="<?= e($val('actual_return_date')) ?>" required><?= $field_error('actual_return_date') ?></div>
                <div><label class="form-label" for="return_condition">Condition of returned items <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('return_condition') ?>" id="return_condition" name="return_condition"><?php foreach (inventory_return_conditions() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('return_condition') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('return_condition') ?></div>
                <div><label class="form-label" for="missing_quantity">Missing (not returned)</label><input class="form-control<?= $field_class('missing_quantity') ?>" type="number" id="missing_quantity" name="missing_quantity" min="0" max="<?= e((string) $borrow['quantity']) ?>" step="1" value="<?= e($val('missing_quantity')) ?>"><div class="form-text">Missing pieces are not added back to the quantity on hand.</div><?= $field_error('missing_quantity') ?></div>
                <div><label class="form-label" for="damaged_quantity">Returned but damaged</label><input class="form-control<?= $field_class('damaged_quantity') ?>" type="number" id="damaged_quantity" name="damaged_quantity" min="0" max="<?= e((string) $borrow['quantity']) ?>" step="1" value="<?= e($val('damaged_quantity')) ?>"><div class="form-text">Counted as returned. Update the item's condition or status if repairs are needed.</div><?= $field_error('damaged_quantity') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="return_remarks">Remarks <span class="activity-detail-muted">(required when items are damaged or missing)</span></label><textarea class="form-control<?= $field_class('return_remarks') ?>" id="return_remarks" name="return_remarks" rows="2" maxlength="1000"><?= e($val('return_remarks')) ?></textarea><?= $field_error('return_remarks') ?></div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Mark as returned?" data-dialog-message="Returned pieces go back on hand. This closes the borrow record." data-dialog-confirm="Mark as Returned" data-dialog-dismiss="Cancel">Mark as Returned</button>
            <a class="btn btn-light" href="inventory_borrowed.php">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
