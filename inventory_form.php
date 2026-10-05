<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/inventory.php';
inventory_require_manage();
$connection = db();
if (!inventory_ready($connection)) { flash('inventory_error', 'Inventory needs its database tables before items can be recorded.'); redirect('inventory.php'); }

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$record = $id ? inventory_find($connection, $id) : null;
if ($id && !$record) { http_response_code(404); exit('Inventory item not found.'); }
if ($record && $record['archived_at'] !== null) { flash('inventory_error', 'Restore this item before editing it.'); redirect('inventory_view.php?id=' . $id); }
$is_edit = $record !== null;
$fields = inventory_fields();
$values = $record ? array_intersect_key($record, array_flip($fields)) : ['item_type' => 'equipment', 'status' => 'available', 'item_condition' => 'good', 'unit' => 'pcs'] + array_fill_keys($fields, null);
$errors = [];
$form_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A request larger than post_max_size arrives with empty $_POST; explain instead of showing field errors.
    if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $form_error = 'The upload was too large. Photos must be 5 MB or smaller.';
    } else {
        $validated = inventory_validate($connection, $_POST, $record);
        $values = $validated['values'];
        $errors = $validated['errors'];
        if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
        elseif ($is_edit && (string) ($_POST['form_updated_at'] ?? '') !== (string) $record['updated_at']) $form_error = 'This item was changed by another action after you opened it. Reload the page to review the latest information.';
    }
    $new_photo = null;
    if ($form_error === null && $errors === []) {
        try {
            $new_photo = inventory_store_photo($_FILES['photo'] ?? []);
        } catch (RuntimeException $exception) {
            $errors['photo'] = $exception->getMessage();
        }
    }
    if ($form_error === null && $errors === []) {
        $remove_photo = $is_edit && ($_POST['remove_photo'] ?? '') === '1';
        $old_photo = $record['photo_filename'] ?? null;
        $photo = $new_photo ?? ($remove_photo ? null : $old_photo);
        $params = array_intersect_key($values, array_flip($fields)) + ['photo_filename' => $photo, 'user' => current_user()['id']];
        try {
            $connection->beginTransaction();
            if ($is_edit) {
                $locked = inventory_find($connection, $id, true);
                if (!$locked || $locked['updated_at'] !== $record['updated_at'] || $locked['archived_at'] !== null) throw new RuntimeException('This item was changed by another action after you opened it. Reload the page to review the latest information.');
                $changed = inventory_changed_fields($values, $locked);
                if ($photo !== $old_photo) $changed[] = 'photo';
                if ($changed !== []) {
                    $set = implode(', ', array_map(static fn (string $f): string => "$f = :$f", $fields));
                    $connection->prepare("UPDATE inventory_items SET $set, photo_filename = :photo_filename, updated_by = :user WHERE id = :id")->execute($params + ['id' => $id]);
                    // Movement log: quantity corrections and status changes made in the form.
                    $change_reason = mb_substr(residents_collapse((string) ($_POST['change_reason'] ?? '')), 0, 255);
                    if (in_array('quantity', $changed, true)) inventory_log_movement($connection, $id, 'adjusted', (int) $values['quantity'] - (int) $locked['quantity'], (int) $values['quantity'], $change_reason !== '' ? $change_reason : 'Quantity corrected in the item form');
                    if (in_array('status', $changed, true)) inventory_log_movement($connection, $id, inventory_status_movement_type((string) $locked['status'], (string) $values['status']), 0, (int) $values['quantity'], $change_reason !== '' ? $change_reason : 'Status changed in the item form', (string) $locked['status'], (string) $values['status']);
                    inventory_audit($connection, $id, 'inventory_updated', ['item_code' => $locked['item_code'], 'changed_fields' => $changed] + (in_array('status', $changed, true) ? ['status_from' => $locked['status'], 'status_to' => $values['status']] : []) + (in_array('quantity', $changed, true) ? ['quantity_from' => (int) $locked['quantity'], 'quantity_to' => (int) $values['quantity']] : []));
                }
                $saved_id = $id;
                $message = $changed === [] ? 'No changes were made to this item.' : $locked['item_code'] . ' was updated.';
            } else {
                $code = inventory_next_code($connection);
                $columns = implode(', ', $fields);
                $placeholders = implode(', ', array_map(static fn (string $f): string => ":$f", $fields));
                $connection->prepare("INSERT INTO inventory_items (item_code, $columns, photo_filename, created_by, updated_by) VALUES (:item_code, $placeholders, :photo_filename, :user, :updater)")->execute($params + ['item_code' => $code, 'updater' => current_user()['id']]);
                $saved_id = (int) $connection->lastInsertId();
                inventory_log_movement($connection, $saved_id, 'added', (int) $values['quantity'], (int) $values['quantity'], 'New item recorded', null, (string) $values['status']);
                inventory_audit($connection, $saved_id, 'inventory_created', ['item_code' => $code, 'item_type' => $values['item_type'], 'quantity' => (int) $values['quantity'], 'status' => $values['status']]);
                $message = $code . ' — ' . $values['name'] . ' was added.';
            }
            $connection->commit();
            if ($is_edit && $photo !== $old_photo) inventory_delete_photo($old_photo);
            flash('inventory_success', $message);
            redirect('inventory_view.php?id=' . $saved_id);
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            inventory_delete_photo($new_photo);
            $form_error = ($exception->errorInfo[1] ?? 0) === 1062 ? 'Another item was saved at the same moment. Please submit again.' : 'The item could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            inventory_delete_photo($new_photo);
            $form_error = $exception->getMessage();
        }
    }
}

$categories = inventory_list($connection, 'categories', $record ? (int) $record['category_id'] : null);
$locations = inventory_list($connection, 'locations', $record ? (int) $record['location_id'] : null);
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
$select = static function (string $name, array $options, string $current, string $placeholder = '') use ($field_class): string {
    $html = '<select class="form-select' . $field_class($name) . '" id="' . e($name) . '" name="' . e($name) . '">' . ($placeholder !== '' ? '<option value="">' . e($placeholder) . '</option>' : '');
    foreach ($options as $value => $label) $html .= '<option value="' . e((string) $value) . '"' . ((string) $value === $current ? ' selected' : '') . '>' . e($label) . '</option>';
    return $html . '</select>';
};
$cancel = $is_edit ? 'inventory_view.php?id=' . $record['id'] : 'inventory.php';
$page_title = $is_edit ? 'Edit Inventory Item' : 'Add Inventory Item'; $active_page = 'inventory';
$page_styles = ['assets/css/inventory.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="<?= e($cancel) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p><?= $is_edit ? 'Item code ' . e($record['item_code']) . '. The item code never changes.' : 'The item code (INV-###) is assigned automatically when the item is saved.' ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($is_edit): ?><input type="hidden" name="form_updated_at" value="<?= e($record['updated_at']) ?>"><?php endif; ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= INVENTORY_PHOTO_MAX_BYTES ?>">

        <fieldset class="resident-section">
            <legend>Item Information</legend>
            <div class="resident-grid">
                <div class="resident-grid-full"><label class="form-label" for="name">Item name <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('name') ?>" id="name" name="name" maxlength="150" value="<?= e($val('name')) ?>" required placeholder="e.g. Monobloc chairs"><?= $field_error('name') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="description">Description</label><textarea class="form-control<?= $field_class('description') ?>" id="description" name="description" rows="2" maxlength="2000"><?= e($val('description')) ?></textarea><?= $field_error('description') ?></div>
                <div><label class="form-label" for="item_type">Type <span class="resident-required" aria-hidden="true">*</span></label><?= $select('item_type', inventory_types(), $val('item_type')) ?><div class="form-text">Supplies are used up (e.g. bond paper); equipment is kept and reused.</div><?= $field_error('item_type') ?></div>
                <div><label class="form-label" for="category_id">Category <span class="resident-required" aria-hidden="true">*</span></label><?= $select('category_id', array_column($categories, 'name', 'id'), $val('category_id'), 'Select category') ?><?= $field_error('category_id') ?></div>
                <div><label class="form-label" for="location_id">Location <span class="resident-required" aria-hidden="true">*</span></label><?= $select('location_id', array_column($locations, 'name', 'id'), $val('location_id'), 'Select location') ?><?= $field_error('location_id') ?></div>
                <div><label class="form-label" for="custodian">Accountable person / custodian</label><input class="form-control<?= $field_class('custodian') ?>" id="custodian" name="custodian" maxlength="150" value="<?= e($val('custodian')) ?>"><?= $field_error('custodian') ?></div>
            </div>
            <p class="resident-static inventory-form-note">Need another category or location? Add it in <a class="activity-detail-link" href="inventory_lists.php">Categories &amp; Locations</a>.</p>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Quantity and Status</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="quantity">Quantity <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('quantity') ?>" type="number" id="quantity" name="quantity" min="0" max="1000000" step="1" inputmode="numeric" value="<?= e($val('quantity')) ?>" required><?= $field_error('quantity') ?></div>
                <div><label class="form-label" for="unit">Unit <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('unit') ?>" id="unit" name="unit" maxlength="30" value="<?= e($val('unit')) ?>" list="inventory-units" required><datalist id="inventory-units"><?php foreach (inventory_units() as $unit): ?><option value="<?= e($unit) ?>"></option><?php endforeach; ?></datalist><?= $field_error('unit') ?></div>
                <div><label class="form-label" for="status">Status <span class="resident-required" aria-hidden="true">*</span></label><?= $select('status', inventory_statuses(), $val('status')) ?><div class="form-text">For supplies, Low Stock is set automatically when the quantity reaches the reorder level.</div><?= $field_error('status') ?></div>
                <div><label class="form-label" for="item_condition">Condition <span class="resident-required" aria-hidden="true">*</span></label><?= $select('item_condition', inventory_conditions(), $val('item_condition')) ?><?= $field_error('item_condition') ?></div>
                <div><label class="form-label" for="reorder_level">Reorder level <span class="activity-detail-muted">(supplies)</span></label><input class="form-control<?= $field_class('reorder_level') ?>" type="number" id="reorder_level" name="reorder_level" min="0" step="1" inputmode="numeric" value="<?= e($val('reorder_level')) ?>"><div class="form-text">Ignored for equipment.</div><?= $field_error('reorder_level') ?></div>
                <div><label class="form-label" for="expiry_date">Expiry date <span class="activity-detail-muted">(e.g. medicines)</span></label><input class="form-control<?= $field_class('expiry_date') ?>" type="date" id="expiry_date" name="expiry_date" value="<?= e($val('expiry_date')) ?>"><?= $field_error('expiry_date') ?></div>
            </div>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Identification and Acquisition</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="serial_number">Serial number</label><input class="form-control<?= $field_class('serial_number') ?>" id="serial_number" name="serial_number" maxlength="100" value="<?= e($val('serial_number')) ?>"><?= $field_error('serial_number') ?></div>
                <div><label class="form-label" for="property_number">Property number</label><input class="form-control<?= $field_class('property_number') ?>" id="property_number" name="property_number" maxlength="100" value="<?= e($val('property_number')) ?>"><?= $field_error('property_number') ?></div>
                <div><label class="form-label" for="date_acquired">Date acquired</label><input class="form-control<?= $field_class('date_acquired') ?>" type="date" id="date_acquired" name="date_acquired" max="<?= e(date('Y-m-d')) ?>" value="<?= e($val('date_acquired')) ?>"><?= $field_error('date_acquired') ?></div>
                <div><label class="form-label" for="unit_cost">Unit cost (₱)</label><input class="form-control<?= $field_class('unit_cost') ?>" id="unit_cost" name="unit_cost" inputmode="decimal" maxlength="14" value="<?= e($val('unit_cost')) ?>" placeholder="0.00"><?= $field_error('unit_cost') ?></div>
                <div><label class="form-label" for="source_of_funds">Source of funds</label><?= $select('source_of_funds', inventory_fund_sources(), $val('source_of_funds'), 'Not specified') ?><?= $field_error('source_of_funds') ?></div>
            </div>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Photo and Remarks</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="photo">Photo <span class="activity-detail-muted">(optional)</span></label><input class="form-control<?= $field_class('photo') ?>" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp"><div class="form-text">JPG, PNG or WebP, up to 5 MB.<?= $is_edit && $record['photo_filename'] ? ' Choosing a new photo replaces the current one.' : '' ?></div><?= $field_error('photo') ?>
                    <?php if ($is_edit && $record['photo_filename']): ?><div class="inventory-current-photo"><img src="inventory_photo.php?id=<?= e((string) $record['id']) ?>" alt="Current photo of <?= e($record['name']) ?>"><label class="form-check-label"><input class="form-check-input" type="checkbox" name="remove_photo" value="1"> Remove current photo</label></div><?php endif; ?>
                </div>
                <?php if ($is_edit && inventory_borrowing_ready($connection)): ?>
                    <div class="resident-grid-full"><label class="form-label" for="change_reason">Reason for quantity or status change <span class="activity-detail-muted">(optional)</span></label><input class="form-control" id="change_reason" name="change_reason" maxlength="255" value="<?= e((string) ($_POST['change_reason'] ?? '')) ?>" placeholder="e.g. Physical count on Sep 30, repaired by technician"><div class="form-text">Saved in the item's movement history when the quantity or status changes.</div></div>
                <?php endif; ?>
                <div><label class="form-label" for="remarks">Remarks</label><textarea class="form-control<?= $field_class('remarks') ?>" id="remarks" name="remarks" rows="4" maxlength="2000"><?= e($val('remarks')) ?></textarea><?= $field_error('remarks') ?></div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="<?= $is_edit ? 'Save changes to this item?' : 'Add this item?' ?>" data-dialog-message="<?= $is_edit ? 'The changes will be recorded in the Audit Logs.' : 'An item code will be assigned automatically.' ?>" data-dialog-confirm="<?= $is_edit ? 'Save Changes' : 'Add Item' ?>" data-dialog-dismiss="Cancel"><?= $is_edit ? 'Save Changes' : 'Add Item' ?></button>
            <a class="btn btn-light" href="<?= e($cancel) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
