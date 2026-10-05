<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_prep.php';
disaster_require_manage();
$connection = db();
if (!disaster_prep_ready($connection)) { flash('disaster_error', 'Evacuation centers need their database table first.'); redirect('disaster_centers.php'); }

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$record = $id ? disaster_prep_find($connection, 'center', $id) : null;
if ($id && !$record) { http_response_code(404); exit('Evacuation center not found.'); }
if ($record && $record['archived_at'] !== null) { flash('disaster_error', 'Restore this center before editing it.'); redirect('disaster_centers.php?status=archived'); }
$is_edit = $record !== null;
$fields = disaster_center_fields();
$values = $record ? array_intersect_key($record, array_flip($fields)) : ['name' => '', 'address' => '', 'area_id' => '', 'capacity' => '', 'has_toilets' => 0, 'has_water' => 0, 'has_electricity' => 0, 'has_kitchen' => 0, 'contact_person' => '', 'contact_number' => '', 'status' => 'closed'];
$errors = [];
$form_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = disaster_center_validate($connection, $_POST, $record);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($is_edit && (string) ($_POST['form_updated_at'] ?? '') !== (string) $record['updated_at']) $form_error = 'This record was changed by another action after you opened it. Reload the page to review the latest information.';
    if ($form_error === null && $errors === []) {
        try {
            [, $message] = disaster_prep_save($connection, 'center', $fields, $values, $record, $values['name']);
            flash('disaster_success', $message);
            redirect('disaster_centers.php');
        } catch (PDOException $exception) {
            $form_error = ($exception->errorInfo[1] ?? 0) === 1062 ? 'An evacuation center with this name already exists.' : 'The center could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            $form_error = $exception->getMessage();
        }
    }
}

$areas = disaster_areas($connection, $record ? (int) $record['area_id'] : null);
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
$page_title = $is_edit ? 'Edit Evacuation Center' : 'Add Evacuation Center'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="disaster_centers.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p>Capacity is the number of persons the center can shelter.</p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <?php if ($is_edit): ?><input type="hidden" name="form_updated_at" value="<?= e($record['updated_at']) ?>"><?php endif; ?>
        <fieldset class="resident-section">
            <legend>Center</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="name">Name <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('name') ?>" id="name" name="name" maxlength="150" value="<?= e($val('name')) ?>" required placeholder="e.g. San Jose Elementary School" data-summary-label="Name"><?= $field_error('name') ?></div>
                <div><label class="form-label" for="area_id">Area <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('area_id') ?>" id="area_id" name="area_id" required data-summary-label="Area"><option value="">Select area</option><?php foreach ($areas as $area): ?><option value="<?= e((string) $area['id']) ?>" <?= $val('area_id') === (string) $area['id'] ? 'selected' : '' ?>><?= e($area['name']) ?><?= (int) $area['is_active'] === 1 ? '' : ' (inactive)' ?></option><?php endforeach; ?></select><?= $field_error('area_id') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="address">Address <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('address') ?>" id="address" name="address" maxlength="255" value="<?= e($val('address')) ?>" required data-summary-label="Address"><?= $field_error('address') ?></div>
                <div><label class="form-label" for="capacity">Capacity (persons) <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('capacity') ?>" type="number" id="capacity" name="capacity" min="1" max="10000" step="1" inputmode="numeric" value="<?= e($val('capacity')) ?>" required data-summary-label="Capacity"><?= $field_error('capacity') ?></div>
                <div><label class="form-label" for="status">Status <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('status') ?>" id="status" name="status" required data-summary-label="Status"><?php foreach (disaster_center_statuses() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('status') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('status') ?></div>
                <div class="resident-grid-full"><span class="form-label d-block">Facilities</span><div class="drr-checks"><?php foreach (disaster_facilities() as $field => $label): ?><label class="form-check"><input class="form-check-input" type="checkbox" name="<?= e($field) ?>" value="1" <?= $val($field) === '1' ? 'checked' : '' ?>> <span class="form-check-label"><?= e($label) ?></span></label><?php endforeach; ?></div></div>
            </div>
        </fieldset>
        <fieldset class="resident-section">
            <legend>Contact</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="contact_person">Contact person</label><input class="form-control<?= $field_class('contact_person') ?>" id="contact_person" name="contact_person" maxlength="150" value="<?= e($val('contact_person')) ?>" data-summary-label="Contact person"><?= $field_error('contact_person') ?></div>
                <div><label class="form-label" for="contact_number">Contact number</label><input class="form-control<?= $field_class('contact_number') ?>" type="tel" id="contact_number" name="contact_number" maxlength="30" value="<?= e($val('contact_number')) ?>" placeholder="e.g. 0917 123 4567" data-summary-label="Contact number"><?= $field_error('contact_number') ?></div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $is_edit ? 'Save changes to this center?' : 'Add this evacuation center?' ?>" data-dialog-message="The change will be recorded in the Audit Logs." data-dialog-confirm="<?= $is_edit ? 'Save Changes' : 'Add Center' ?>" data-dialog-dismiss="Cancel"><?= $is_edit ? 'Save Changes' : 'Add Center' ?></button>
            <a class="btn btn-light" href="disaster_centers.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
