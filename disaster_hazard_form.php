<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_prep.php';
disaster_require_manage();
$connection = db();
if (!disaster_prep_ready($connection)) { flash('disaster_error', 'Hazard areas need their database table first.'); redirect('disaster_hazards.php'); }

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$record = $id ? disaster_prep_find($connection, 'hazard', $id) : null;
if ($id && !$record) { http_response_code(404); exit('Hazard area not found.'); }
if ($record && $record['archived_at'] !== null) { flash('disaster_error', 'Restore this entry before editing it.'); redirect('disaster_hazards.php?archived=1'); }
$is_edit = $record !== null;
$fields = disaster_hazard_fields();
$values = $record ? array_intersect_key($record, array_flip($fields)) : ['area_id' => '', 'hazard_type' => '', 'hazard_other' => '', 'risk_level' => '', 'families_at_risk' => '', 'notes' => ''];
$errors = [];
$form_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = disaster_hazard_validate($connection, $_POST, $record);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($is_edit && (string) ($_POST['form_updated_at'] ?? '') !== (string) $record['updated_at']) $form_error = 'This record was changed by another action after you opened it. Reload the page to review the latest information.';
    if ($form_error === null && $errors === []) {
        try {
            $label = disaster_area($connection, (int) $values['area_id'])['name'] . ' — ' . disaster_hazard_label($values);
            [, $message] = disaster_prep_save($connection, 'hazard', $fields, $values, $record, $label);
            flash('disaster_success', $message);
            redirect('disaster_hazards.php');
        } catch (PDOException) {
            $form_error = 'The entry could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            $form_error = $exception->getMessage();
        }
    }
}

$areas = disaster_areas($connection, $record ? (int) $record['area_id'] : null);
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
$page_title = $is_edit ? 'Edit Hazard Area' : 'Add Hazard Area'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="disaster_hazards.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p>Each hazard is recorded once per area.</p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" data-drr-hazard-form>
        <?= csrf_field() ?>
        <?php if ($is_edit): ?><input type="hidden" name="form_updated_at" value="<?= e($record['updated_at']) ?>"><?php endif; ?>
        <fieldset class="resident-section">
            <legend>Hazard</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="area_id">Area <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('area_id') ?>" id="area_id" name="area_id" required data-summary-label="Area"><option value="">Select area</option><?php foreach ($areas as $area): ?><option value="<?= e((string) $area['id']) ?>" <?= $val('area_id') === (string) $area['id'] ? 'selected' : '' ?>><?= e($area['name']) ?><?= (int) $area['is_active'] === 1 ? '' : ' (inactive)' ?></option><?php endforeach; ?></select><div class="form-text">Missing a place? Add it on the <a href="disaster_areas.php">Areas</a> page.</div><?= $field_error('area_id') ?></div>
                <div><label class="form-label" for="hazard_type">Hazard type <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('hazard_type') ?>" id="hazard_type" name="hazard_type" required data-drr-hazard-type data-summary-label="Hazard"><option value="">Select hazard</option><?php foreach (disaster_hazard_types() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('hazard_type') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('hazard_type') ?></div>
                <div data-drr-hazard-other><label class="form-label" for="hazard_other">Describe the hazard <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('hazard_other') ?>" id="hazard_other" name="hazard_other" maxlength="100" value="<?= e($val('hazard_other')) ?>" placeholder="e.g. Strong winds, drought" data-summary-label="Other hazard"><?= $field_error('hazard_other') ?></div>
                <div><label class="form-label" for="risk_level">Risk level <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('risk_level') ?>" id="risk_level" name="risk_level" required data-summary-label="Risk level"><option value="">Select risk level</option><?php foreach (disaster_risk_levels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('risk_level') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('risk_level') ?></div>
                <?php // Families are now counted automatically from Households on the Risk Map; an older typed value is kept as it is. ?>
                <input type="hidden" name="families_at_risk" value="<?= e($val('families_at_risk')) ?>">
                <div><span class="form-label d-block">Families</span><p class="resident-static">Counted automatically from the households in the Purok (see the Risk Map).</p></div>
                <div class="resident-grid-full"><label class="form-label" for="notes">Notes</label><textarea class="form-control<?= $field_class('notes') ?>" id="notes" name="notes" rows="3" maxlength="1000" placeholder="Exact location, warning signs, safe routes"><?= e($val('notes')) ?></textarea><?= $field_error('notes') ?></div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $is_edit ? 'Save changes to this entry?' : 'Add this hazard area?' ?>" data-dialog-message="The change will be recorded in the Audit Logs." data-dialog-confirm="<?= $is_edit ? 'Save Changes' : 'Add Hazard Area' ?>" data-dialog-dismiss="Cancel"><?= $is_edit ? 'Save Changes' : 'Add Hazard Area' ?></button>
            <a class="btn btn-light" href="disaster_hazards.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/disaster.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/disaster.js')) ?>"></script>
