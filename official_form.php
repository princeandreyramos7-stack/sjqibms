<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/officials.php';
officials_require_manage();
$connection = db();
if (!officials_ready($connection)) { flash('official_error', 'Barangay Officials needs a database update before records can be edited.'); redirect('officials.php'); }

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$record = $id ? officials_find($connection, $id) : null;
if ($id && !$record) { http_response_code(404); exit('Official not found.'); }
$is_edit = $record !== null;
$fields = ['full_name', 'position', 'committee', 'service_type', 'term_start_year', 'term_end_year', 'contact_number', 'status', 'user_id'];
$values = $record ? array_intersect_key($record, array_flip($fields)) : ['status' => 'active'] + array_fill_keys($fields, null);
$errors = [];
$form_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = officials_validate($connection, $_POST);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($is_edit && (string) ($_POST['form_updated_at'] ?? '') !== (string) $record['updated_at']) $form_error = 'This record was changed by another action after you opened it. Reload the page to review the latest information.';
    if ($form_error === null && $errors === []) {
        try {
            $connection->beginTransaction();
            $params = array_intersect_key($values, array_flip($fields));
            if ($is_edit) {
                $locked = officials_find($connection, $id, true);
                if (!$locked || $locked['updated_at'] !== $record['updated_at']) throw new RuntimeException('This record was changed by another action after you opened it. Reload the page to review the latest information.');
                $changed = array_values(array_filter($fields, static fn (string $field): bool => (string) $values[$field] !== (string) $locked[$field]));
                if ($changed !== []) {
                    $connection->prepare('UPDATE barangay_personnel SET full_name = :full_name, position = :position, committee = :committee, service_type = :service_type, term_start_year = :term_start_year, term_end_year = :term_end_year, contact_number = :contact_number, status = :status, user_id = :user_id WHERE id = :id')->execute($params + ['id' => $id]);
                    officials_audit($connection, $id, 'personnel_updated', ['changed_fields' => $changed]);
                }
                $saved_id = $id;
                $message = $changed === [] ? 'No changes were made to this record.' : 'Official record updated successfully.';
            } else {
                $connection->prepare('INSERT INTO barangay_personnel (full_name, position, committee, service_type, term_start_year, term_end_year, contact_number, status, user_id, created_by) VALUES (:full_name, :position, :committee, :service_type, :term_start_year, :term_end_year, :contact_number, :status, :user_id, :created_by)')->execute($params + ['created_by' => current_user()['id']]);
                $saved_id = (int) $connection->lastInsertId();
                officials_audit($connection, $saved_id, 'personnel_created');
                $message = 'Official added successfully.';
            }
            $connection->commit();
            flash('official_success', $message);
            redirect('officials.php');
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            // Unique key: a staff account can be linked to only one personnel record.
            if (($exception->errorInfo[1] ?? 0) === 1062) $errors['user_id'] = 'That account is already linked to another official.';
            else $form_error = 'The record could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        }
    }
}

$accounts = officials_linkable_accounts($connection);
$role_label = static fn (string $role): string => ['super_admin' => 'System Administrator', 'punong_barangay' => 'Punong Barangay', 'secretary' => 'Barangay Secretary', 'treasurer' => 'Treasurer', 'health_worker' => 'Health Worker', 'official' => 'Barangay Official'][$role] ?? $role;
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
$page_title = $is_edit ? 'Edit Official' : 'Add Official'; $active_page = 'officials';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="officials.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p><?= $is_edit ? 'Update the information of ' . e(officials_display_name($record)) . '.' : 'Add a barangay official or staff member.' ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <?php if ($is_edit): ?><input type="hidden" name="form_updated_at" value="<?= e($record['updated_at']) ?>"><?php endif; ?>

        <fieldset class="resident-section">
            <legend>Official Information</legend>
            <div class="resident-grid">
                <div class="resident-grid-full"><label class="form-label" for="full_name">Full name <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('full_name') ?>" id="full_name" name="full_name" maxlength="180" value="<?= e($val('full_name')) ?>" placeholder="e.g. Wilson I. Tabuyo" required data-summary-label="Name"><div class="form-text">Without "Hon." — the title is added automatically for elected officials.</div><?= $field_error('full_name') ?></div>
                <div><label class="form-label" for="position">Position <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('position') ?>" id="position" name="position" maxlength="120" value="<?= e($val('position')) ?>" list="official-positions" placeholder="e.g. Barangay Kagawad" required data-summary-label="Position"><datalist id="official-positions"><?php foreach (officials_positions() as $position): ?><option value="<?= e($position) ?>"></option><?php endforeach; ?></datalist><?= $field_error('position') ?></div>
                <div><label class="form-label" for="committee">Committee</label><input class="form-control<?= $field_class('committee') ?>" id="committee" name="committee" maxlength="120" value="<?= e($val('committee')) ?>" placeholder="e.g. Peace & Order" data-summary-label="Committee"><?= $field_error('committee') ?></div>
            </div>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Term of Office</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="service_type">Elected or appointed <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('service_type') ?>" id="service_type" name="service_type" required data-summary-label="Type"><option value="">Select</option><?php foreach (officials_service_types() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('service_type') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('service_type') ?></div>
                <div></div>
                <div><label class="form-label" for="term_start_year">Term start year</label><input class="form-control<?= $field_class('term_start_year') ?>" type="number" id="term_start_year" name="term_start_year" min="1950" max="2100" step="1" inputmode="numeric" value="<?= e($val('term_start_year')) ?>" placeholder="e.g. 2023" data-summary-label="Term start"><div class="form-text">Required for elected officials. For appointed staff, the year appointed (optional).</div><?= $field_error('term_start_year') ?></div>
                <div><label class="form-label" for="term_end_year">Term end year</label><input class="form-control<?= $field_class('term_end_year') ?>" type="number" id="term_end_year" name="term_end_year" min="1950" max="2100" step="1" inputmode="numeric" value="<?= e($val('term_end_year')) ?>" placeholder="e.g. 2026" data-summary-label="Term end"><div class="form-text">Required for elected officials.</div><?= $field_error('term_end_year') ?></div>
            </div>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Contact and Status</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="contact_number">Contact number</label><input class="form-control<?= $field_class('contact_number') ?>" type="tel" id="contact_number" name="contact_number" maxlength="30" value="<?= e($val('contact_number')) ?>" placeholder="09171234567" autocomplete="off" data-summary-label="Contact"><?= $field_error('contact_number') ?></div>
                <div><label class="form-label" for="status">Status <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('status') ?>" id="status" name="status" required data-summary-label="Status"><?php foreach (officials_status_labels() as $value => $label): ?><option value="<?= e($value) ?>" <?= ($val('status') ?: 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><div class="form-text">Only Active officials can be assigned to new hearings.</div><?= $field_error('status') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="user_id">Linked staff account <span class="activity-detail-muted">(optional)</span></label><select class="form-select<?= $field_class('user_id') ?>" id="user_id" name="user_id"><option value="">No account</option><?php foreach ($accounts as $account): ?><option value="<?= e((string) $account['id']) ?>" <?= $val('user_id') === (string) $account['id'] ? 'selected' : '' ?>><?= e($account['name'] . ' — ' . $role_label($account['role'])) ?></option><?php endforeach; ?></select><div class="form-text">Linking never creates an account or changes permissions.</div><?= $field_error('user_id') ?></div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $is_edit ? 'Save changes to this official?' : 'Add this official?' ?>" data-dialog-message="Review the details before saving." data-dialog-confirm="<?= $is_edit ? 'Save Changes' : 'Add Official' ?>" data-dialog-dismiss="Cancel"><?= $is_edit ? 'Save Changes' : 'Add Official' ?></button>
            <a class="btn btn-light" href="officials.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
