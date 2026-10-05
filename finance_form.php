<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
$connection = db();

// Record a collection (Income, posted at once) or a disbursement (Expense, sent to the System Administrator for approval),
// or edit a disbursement while it is still Pending Approval. Treasurer only; posted and approved records are never
// edited — they are cancelled with a reason instead.
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
finance_require($id ? 'edit' : 'create');
if (!finance_ready($connection)) { flash('finance_error', 'Financial Management needs its database tables first.'); redirect('finance.php'); }
$record = $id ? finance_find($connection, $id) : null;
if ($id && !$record) { http_response_code(404); exit('Transaction not found.'); }
if ($record && !finance_allowed_actions($record)['edit']) { flash('finance_error', 'Only disbursements that are still Pending Approval can be edited. Other records are cancelled with a reason instead.'); redirect('finance_view.php?id=' . $id); }
$is_edit = $record !== null;
$type = $record['type'] ?? ((string) ($_POST['type'] ?? $_GET['type'] ?? '') === 'expense' ? 'expense' : 'income');
$income = $type === 'income';
$fields = finance_fields();
$values = $record ? array_intersect_key($record, array_flip($fields)) + ['type' => $record['type'], 'or_number' => ''] : ['type' => $type, 'or_number' => '', 'transaction_date' => date('Y-m-d'), 'category_id' => '', 'description' => '', 'amount' => '', 'payor_or_payee' => '', 'resident_id' => '', 'payment_mode' => $income ? 'cash' : '', 'check_no' => '', 'remarks' => ''];
$errors = [];
$form_error = null;
$budget_warning = null;
$budget_confirmed = false;
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = finance_validate($connection, ['type' => $type] + $_POST, $record);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($is_edit && (string) ($_POST['form_updated_at'] ?? '') !== (string) $record['updated_at']) $form_error = 'This transaction was changed by another action after you opened it. Reload the page to review the latest information.';
    // Budget warning (not a block): a disbursement that does not fit the remaining budget needs a confirmation tick.
    $budget_confirmed = (string) ($_POST['confirm_budget'] ?? '') === '1';
    if (!$income && $form_error === null && $errors === []) {
        $budget_warning = finance_budget_check($connection, (int) $values['category_id'], (int) substr((string) $values['transaction_date'], 0, 4), (string) $values['amount'], (int) ($id ?? 0));
        if ($budget_warning !== null && !$budget_confirmed) $form_error = 'Please review the budget warning below.';
    }
    if ($form_error === null && $errors === []) {
        $params = array_intersect_key($values, array_flip($fields));
        try {
            $connection->beginTransaction();
            if ($is_edit) {
                $locked = finance_find($connection, $id, true);
                if (!$locked || $locked['updated_at'] !== $record['updated_at'] || $locked['status'] !== 'pending_approval') throw new RuntimeException('This disbursement was changed (or approved) after you opened it. Reload the page to review the latest information.');
                $changes = finance_changes($locked, $params, $fields);
                if ($changes !== []) {
                    $set = implode(', ', array_map(static fn (string $f): string => "$f = :$f", $fields));
                    $connection->prepare("UPDATE finance_transactions SET $set, updated_by = :user WHERE id = :id")->execute($params + ['user' => $me['id'], 'id' => $id]);
                    finance_audit($connection, $id, 'finance_updated', ['reference' => $locked['reference_no'], 'changed_fields' => array_keys($changes), 'changes' => $changes]);
                }
                $saved_id = $id;
                $message = $changes === [] ? 'No changes were made.' : $locked['reference_no'] . ' was updated. It is still waiting for approval.';
            } else {
                $year = (int) substr((string) $values['transaction_date'], 0, 4);
                $reference = $income && !$values['is_allotment'] ? ['reference_no' => $values['or_number'], 'ref_kind' => 'or', 'ref_year' => null, 'ref_seq' => null] : finance_next_reference($connection, $income ? 'nta' : 'dv', $year);
                $status = $income ? 'posted' : 'pending_approval';
                $columns = implode(', ', $fields);
                $placeholders = implode(', ', array_map(static fn (string $f): string => ":$f", $fields));
                $connection->prepare("INSERT INTO finance_transactions (reference_no, ref_kind, ref_year, ref_seq, type, status, $columns, created_by, updated_by) VALUES (:reference_no, :ref_kind, :ref_year, :ref_seq, :type, :status, $placeholders, :user, :updater)")
                    ->execute($params + $reference + ['type' => $type, 'status' => $status, 'user' => $me['id'], 'updater' => $me['id']]);
                $saved_id = (int) $connection->lastInsertId();
                finance_audit($connection, $saved_id, 'finance_created', ['reference' => $reference['reference_no'], 'type' => $type, 'status' => $status, 'values' => $params] + ($budget_warning !== null ? ['budget_warning' => $budget_warning['has_budget'] ? 'exceeds budget by ' . $budget_warning['excess'] : 'no budget set'] : []));
                if (!$income) finance_notify($connection, $saved_id, 'super_admin', 'finance_approval_needed', 'Disbursement for approval: ' . $reference['reference_no'], finance_peso($values['amount']) . ' to ' . $values['payor_or_payee'] . ' — ' . $values['description']);
                $message = $income ? $reference['reference_no'] . ' (' . finance_peso($values['amount']) . ') was posted.' : $reference['reference_no'] . ' (' . finance_peso($values['amount']) . ') was sent to the System Administrator for approval.';
            }
            $connection->commit();
            flash('finance_success', $message);
            redirect('finance_view.php?id=' . $saved_id);
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = ($exception->errorInfo[1] ?? 0) === 1062 ? ($income ? 'This OR number was recorded by another entry at the same moment. Check the number and submit again.' : 'Another record was saved at the same moment. Please submit again.') : 'The transaction could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        }
    }
}

$categories = finance_categories($connection, $type, $record ? (int) $record['category_id'] : null);
$resident = ctype_digit((string) ($values['resident_id'] ?? '')) ? finance_resident($connection, (int) $values['resident_id']) : null;
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
$cancel = $is_edit ? 'finance_view.php?id=' . $record['id'] : 'finance.php';
$page_title = $is_edit ? 'Edit Disbursement' : ($income ? 'Record Collection' : 'Record Disbursement'); $active_page = 'finance';
$page_styles = ['assets/css/finance.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="<?= e($cancel) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p><?= $is_edit ? e($record['reference_no']) . ' can be edited until the System Administrator approves or rejects it.' : ($income ? 'A collection is posted as soon as it is saved. Enter the official receipt (OR) number; allotments get an NTA number automatically.' : 'The disbursement voucher number (DV-YYYY-####) is assigned automatically. The disbursement waits for the System Administrator\'s approval before it can be released.') ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" data-fin-record-form>
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <?php if ($is_edit): ?><input type="hidden" name="form_updated_at" value="<?= e($record['updated_at']) ?>"><?php endif; ?>
        <fieldset class="resident-section">
            <legend><?= $income ? 'Collection' : 'Disbursement' ?></legend>
            <div class="resident-grid">
                <div><label class="form-label" for="category_id">Category <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('category_id') ?>" id="category_id" name="category_id" required data-fin-category data-summary-label="Category"><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= e((string) $category['id']) ?>" data-allotment="<?= (int) $category['is_allotment'] ?>" <?= $val('category_id') === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?><?= (int) $category['is_active'] === 1 ? '' : ' (inactive)' ?></option><?php endforeach; ?></select><?= $field_error('category_id') ?></div>
                <?php if ($income && !$is_edit): ?>
                    <div data-fin-or><label class="form-label" for="or_number">OR number <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('or_number') ?>" id="or_number" name="or_number" maxlength="30" value="<?= e($val('or_number')) ?>" placeholder="OR-<?= e(date('Y')) ?>-0001" autocomplete="off" data-summary-label="OR number"><div class="form-text">The number printed on the official receipt. Each OR number can be used only once.</div><?= $field_error('or_number') ?></div>
                    <div data-fin-nta hidden><span class="form-label d-block">Reference</span><p class="resident-static">NTA-<?= e(date('Y')) ?>-## is assigned automatically for allotments.</p></div>
                <?php else: ?>
                    <div><span class="form-label d-block">Reference</span><p class="resident-static"><?= $is_edit ? e($record['reference_no']) : 'DV-' . e(date('Y')) . '-#### (assigned on save)' ?></p></div>
                <?php endif; ?>
                <div><label class="form-label" for="transaction_date">Date <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('transaction_date') ?>" type="date" id="transaction_date" name="transaction_date" value="<?= e($val('transaction_date')) ?>" <?= $income ? 'max="' . e(date('Y-m-d')) . '"' : '' ?> required data-summary-label="Date"><?= $field_error('transaction_date') ?></div>
                <div><label class="form-label" for="amount">Amount (₱) <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control fin-amount-input<?= $field_class('amount') ?>" id="amount" name="amount" inputmode="decimal" data-fin-amount maxlength="17" value="<?= e($val('amount')) ?>" required placeholder="0.00" autocomplete="off" data-summary-label="Amount"<?= $income ? ' data-fin-max="' . e(finance_collection_max()) . '"' : '' ?>><?php if ($income): ?><div class="form-text">Maximum of <?= e(finance_peso(finance_collection_max())) ?> per collection.</div><?php endif; ?><?= $field_error('amount') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="description">Description <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('description') ?>" id="description" name="description" maxlength="255" value="<?= e($val('description')) ?>" required placeholder="<?= $income ? 'e.g. Barangay clearance fees (weekly)' : 'e.g. Streetlight repair materials' ?>" data-summary-label="Description"><?= $field_error('description') ?></div>
            </div>
        </fieldset>
        <fieldset class="resident-section">
            <legend><?= $income ? 'Payor' : 'Payee' ?></legend>
            <div class="resident-grid">
                <div><label class="form-label" for="payor_or_payee"><?= $income ? 'Received from (payor)' : 'Pay to (payee)' ?> <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('payor_or_payee') ?>" id="payor_or_payee" name="payor_or_payee" maxlength="150" value="<?= e($val('payor_or_payee')) ?>" required data-fin-payor data-summary-label="<?= $income ? 'Payor' : 'Payee' ?>"><?= $field_error('payor_or_payee') ?></div>
                <div class="fin-resident-box" data-fin-resident>
                    <label class="form-label" for="fin-resident-search">Link to a resident <span class="activity-detail-muted">(optional)</span></label>
                    <input type="hidden" name="resident_id" value="<?= e($resident ? (string) $resident['id'] : '') ?>" data-fin-resident-id>
                    <div class="fin-resident-selected" data-fin-resident-selected <?= $resident ? '' : 'hidden' ?>><span data-fin-resident-name><?= $resident ? e(residents_full_name($resident) . ' · ' . residents_purok_label((string) $resident['purok'])) : '' ?></span><button class="btn btn-sm btn-outline-secondary" type="button" data-fin-resident-clear>Remove</button></div>
                    <div data-fin-resident-search <?= $resident ? 'hidden' : '' ?>><input class="form-control<?= $field_class('resident_id') ?>" type="search" id="fin-resident-search" placeholder="Type 2 or more letters of the name" autocomplete="off" maxlength="100" data-fin-resident-query data-summary-skip><div class="case-lookup-results fin-dropdown" role="listbox" aria-label="Matching residents" data-fin-resident-results hidden></div></div>
                    <?= $field_error('resident_id') ?>
                </div>
                <?php if ($income): ?>
                    <div><label class="form-label" for="payment_mode_display">Mode of payment</label><input class="form-control fin-fixed-field" id="payment_mode_display" value="Cash" readonly tabindex="-1" data-summary-label="Payment"><input type="hidden" name="payment_mode" value="cash"><div class="form-text">Collections are received in cash only.</div><?= $field_error('payment_mode') ?></div>
                <?php endif; ?>
                <div class="resident-grid-full"><label class="form-label" for="remarks">Remarks</label><textarea class="form-control<?= $field_class('remarks') ?>" id="remarks" name="remarks" rows="2" maxlength="1000"><?= e($val('remarks')) ?></textarea><?= $field_error('remarks') ?></div>
            </div>
        </fieldset>
        <?php if (!$income): ?><p class="fin-hint">The mode of payment and check number are recorded when the approved disbursement is released.</p><?php endif; ?>
        <?php if ($budget_warning !== null): $category_name = (string) (finance_category($connection, (int) $values['category_id'])['name'] ?? ''); ?>
            <div class="dashboard-status warning fin-budget-warning" role="alert">
                <p><strong>Budget warning.</strong> <?= e(finance_budget_message($budget_warning, $category_name)) ?></p>
                <label class="form-check"><input class="form-check-input" type="checkbox" name="confirm_budget" value="1" <?= $budget_confirmed ? 'checked' : '' ?>> <span class="form-check-label">I understand; submit it anyway. The System Administrator will see this warning when approving.</span></label>
            </div>
        <?php endif; ?>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $is_edit ? 'Save changes to this disbursement?' : ($income ? 'Post this collection?' : 'Submit this disbursement for approval?') ?>" data-dialog-message="<?= $income ? 'A posted collection cannot be edited afterwards; it can only be cancelled with a reason.' : 'The System Administrator will be notified to approve or reject it.' ?>" data-dialog-confirm="<?= $is_edit ? 'Save Changes' : ($income ? 'Post Collection' : 'Submit for Approval') ?>" data-dialog-dismiss="Cancel"><?= $is_edit ? 'Save Changes' : ($income ? 'Post Collection' : 'Submit for Approval') ?></button>
            <a class="btn btn-light" href="<?= e($cancel) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/finance.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/finance.js')) ?>"></script>
