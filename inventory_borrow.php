<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/inventory.php';
inventory_require_manage();
$connection = db();
if (!inventory_ready($connection) || !inventory_borrowing_ready($connection)) { flash('inventory_error', 'Borrowing needs its database tables first.'); redirect('inventory.php'); }

// Lend equipment (for example chairs and tables lent to residents). The quantity on hand is re-checked inside the
// transaction with the item row locked, so two staff members can never lend the same pieces twice.
$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$inv = inventory_find($connection, $id);
if ($inv === null) { http_response_code(404); exit('Inventory item not found.'); }
if (!inventory_can_borrow($inv)) { flash('inventory_error', $inv['item_code'] . ' cannot be borrowed right now (only available equipment with pieces on hand can be lent).'); redirect('inventory_view.php?id=' . $id); }

$values = ['borrower_name' => '', 'borrower_contact' => '', 'borrower_address' => '', 'quantity' => '1', 'purpose' => '', 'date_borrowed' => date('Y-m-d'), 'expected_return_date' => date('Y-m-d', strtotime('+1 day')), 'remarks' => ''];
$errors = [];
$form_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = inventory_validate_borrow($_POST, (int) $inv['quantity']);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    if ($form_error === null && $errors === []) {
        try {
            $connection->beginTransaction();
            $locked = inventory_find($connection, $id, true);
            if ($locked === null || !inventory_can_borrow($locked)) throw new RuntimeException('This item can no longer be borrowed. Reload the page to see its current status.');
            if ($values['quantity'] > (int) $locked['quantity']) throw new RuntimeException('Only ' . number_format((int) $locked['quantity']) . ' ' . $locked['unit'] . ' are available now. Adjust the quantity.');
            $code = inventory_next_borrow_code($connection);
            $connection->prepare('INSERT INTO inventory_borrow_records (borrow_code, item_id, borrower_name, borrower_contact, borrower_address, quantity, purpose, date_borrowed, expected_return_date, remarks, processed_by) VALUES (:code, :item, :name, :contact, :address, :quantity, :purpose, :borrowed, :expected, :remarks, :user)')
                ->execute(['code' => $code, 'item' => $id, 'name' => $values['borrower_name'], 'contact' => $values['borrower_contact'], 'address' => $values['borrower_address'], 'quantity' => $values['quantity'], 'purpose' => $values['purpose'], 'borrowed' => $values['date_borrowed'], 'expected' => $values['expected_return_date'], 'remarks' => $values['remarks'], 'user' => current_user()['id']]);
            $borrow_id = (int) $connection->lastInsertId();
            $on_hand = (int) $locked['quantity'] - $values['quantity'];
            $status = inventory_status_after($locked, $on_hand, inventory_borrowed_out($connection, $id));
            $connection->prepare('UPDATE inventory_items SET quantity = :quantity, status = :status, updated_by = :user WHERE id = :id')->execute(['quantity' => $on_hand, 'status' => $status, 'user' => current_user()['id'], 'id' => $id]);
            inventory_log_movement($connection, $id, 'borrowed', -$values['quantity'], $on_hand, $code . ' — ' . $values['purpose'], $status !== $locked['status'] ? (string) $locked['status'] : null, $status !== $locked['status'] ? $status : null, $values['borrower_name'], $borrow_id);
            inventory_audit($connection, $id, 'inventory_borrowed', ['item_code' => $locked['item_code'], 'reference' => $code, 'quantity' => $values['quantity']]);
            $connection->commit();
            flash('inventory_success', $code . ': ' . $values['quantity'] . ' ' . $locked['unit'] . ' of ' . $locked['name'] . ' lent to ' . $values['borrower_name'] . '.');
            redirect('inventory_view.php?id=' . $id);
        } catch (PDOException) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = 'The borrow record could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        }
    }
}
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
$page_title = 'Borrow Item'; $active_page = 'inventory';
$page_styles = ['assets/css/inventory.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="inventory_view.php?id=<?= e((string) $id) ?>"><span aria-hidden="true">&larr;</span> Back to item</a>
    <div class="page-heading"><div><span class="eyebrow"><?= e($inv['item_code']) ?></span><h1>Borrow: <?= e($inv['name']) ?></h1><p><?= e(number_format((int) $inv['quantity']) . ' ' . $inv['unit']) ?> available to lend.</p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $id) ?>">
        <fieldset class="resident-section">
            <legend>Borrower</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="borrower_name">Borrower's full name <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('borrower_name') ?>" id="borrower_name" name="borrower_name" maxlength="150" value="<?= e($val('borrower_name')) ?>" required data-summary-label="Borrower"><?= $field_error('borrower_name') ?></div>
                <div><label class="form-label" for="borrower_contact">Contact number</label><input class="form-control<?= $field_class('borrower_contact') ?>" type="tel" id="borrower_contact" name="borrower_contact" maxlength="30" value="<?= e($val('borrower_contact')) ?>" placeholder="09171234567"><?= $field_error('borrower_contact') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="borrower_address">Address / Purok <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('borrower_address') ?>" id="borrower_address" name="borrower_address" maxlength="255" value="<?= e($val('borrower_address')) ?>" required placeholder="e.g. Purok 2, San Jose"><?= $field_error('borrower_address') ?></div>
            </div>
        </fieldset>
        <fieldset class="resident-section">
            <legend>Borrow Details</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="quantity">Quantity (<?= e($inv['unit']) ?>) <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('quantity') ?>" type="number" id="quantity" name="quantity" min="1" max="<?= e((string) $inv['quantity']) ?>" step="1" value="<?= e($val('quantity')) ?>" required data-summary-label="Quantity"><div class="form-text">Up to <?= e(number_format((int) $inv['quantity'])) ?>.</div><?= $field_error('quantity') ?></div>
                <div><label class="form-label" for="purpose">Purpose <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('purpose') ?>" id="purpose" name="purpose" maxlength="255" value="<?= e($val('purpose')) ?>" required placeholder="e.g. Birthday celebration" data-summary-label="Purpose"><?= $field_error('purpose') ?></div>
                <div><label class="form-label" for="date_borrowed">Date borrowed <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('date_borrowed') ?>" type="date" id="date_borrowed" name="date_borrowed" max="<?= e(date('Y-m-d')) ?>" value="<?= e($val('date_borrowed')) ?>" required><?= $field_error('date_borrowed') ?></div>
                <div><label class="form-label" for="expected_return_date">Expected return date <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('expected_return_date') ?>" type="date" id="expected_return_date" name="expected_return_date" value="<?= e($val('expected_return_date')) ?>" required data-summary-label="Return by"><?= $field_error('expected_return_date') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="remarks">Remarks</label><textarea class="form-control<?= $field_class('remarks') ?>" id="remarks" name="remarks" rows="2" maxlength="1000"><?= e($val('remarks')) ?></textarea><?= $field_error('remarks') ?></div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="Record this borrow?" data-dialog-message="The quantity on hand will go down until the items are returned." data-dialog-confirm="Record Borrow" data-dialog-dismiss="Cancel">Record Borrow</button>
            <a class="btn btn-light" href="inventory_view.php?id=<?= e((string) $id) ?>">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
