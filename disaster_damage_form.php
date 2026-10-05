<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_recovery.php';
disaster_require_manage();
$connection = db();
if (!disaster_recovery_ready($connection)) { flash('disaster_error', 'Damage assessment needs its database tables first.'); redirect('disaster_damage.php'); }

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$record = $id ? disaster_damage_find($connection, $id) : null;
if ($id && !$record) { http_response_code(404); exit('Assessment not found.'); }
if ($record && $record['archived_at'] !== null) { flash('disaster_error', 'Restore this assessment before editing it.'); redirect('disaster_damage.php?archived=1'); }
$is_edit = $record !== null;
$fields = disaster_damage_fields();
$values = $record ? array_intersect_key($record, array_flip($fields)) : ['incident_id' => (string) ($_GET['incident'] ?? ''), 'area_id' => '', 'assessed_on' => date('Y-m-d'), 'houses_partial' => '0', 'houses_total' => '0', 'roads' => '', 'bridges' => '', 'crops' => '', 'public_facilities' => '', 'notes' => ''];
$errors = [];
$form_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = disaster_damage_validate($connection, $_POST, $record);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($is_edit && (string) ($_POST['form_updated_at'] ?? '') !== (string) $record['updated_at']) $form_error = 'This assessment was changed by another action after you opened it. Reload the page to review the latest information.';
    $photo = null;
    if ($form_error === null && $errors === []) {
        try { $photo = disaster_store_photo($_FILES['photo'] ?? []); } catch (RuntimeException $exception) { $errors['photo'] = $exception->getMessage(); }
    }
    if ($form_error === null && $errors === []) {
        $remove_photo = (string) ($_POST['remove_photo'] ?? '') === '1';
        $photo_path = $photo ?? ($remove_photo ? null : ($record['photo_path'] ?? null));
        $params = array_intersect_key($values, array_flip($fields)) + ['photo_path' => $photo_path, 'user' => current_user()['id']];
        try {
            $connection->beginTransaction();
            $area_name = disaster_area($connection, (int) $values['area_id'])['name'];
            if ($is_edit) {
                $locked = disaster_damage_find($connection, $id, true);
                if (!$locked || $locked['updated_at'] !== $record['updated_at'] || $locked['archived_at'] !== null) throw new RuntimeException('This assessment was changed by another action after you opened it. Reload the page to review the latest information.');
                $changed = array_values(array_filter([...$fields, 'photo_path'], static fn (string $f): bool => (string) ($params[$f] ?? '') !== (string) ($locked[$f] ?? '')));
                if ($changed !== []) {
                    $set = implode(', ', array_map(static fn (string $f): string => "$f = :$f", [...$fields, 'photo_path']));
                    $connection->prepare("UPDATE drr_damage_assessments SET $set, updated_by = :user WHERE id = :id")->execute($params + ['id' => $id]);
                    residents_audit($connection, 'disaster_damage', $id, 'disaster_damage_updated', ['reference' => $locked['reference_no'], 'name' => $area_name, 'changed_fields' => $changed]);
                }
                $message = $changed === [] ? 'No changes were made.' : 'The assessment for ' . $area_name . ' was updated.';
            } else {
                $columns = implode(', ', [...$fields, 'photo_path']);
                $placeholders = implode(', ', array_map(static fn (string $f): string => ":$f", [...$fields, 'photo_path']));
                $connection->prepare("INSERT INTO drr_damage_assessments ($columns, created_by, updated_by) VALUES ($placeholders, :user, :updater)")->execute($params + ['updater' => current_user()['id']]);
                $new_id = (int) $connection->lastInsertId();
                residents_audit($connection, 'disaster_damage', $new_id, 'disaster_damage_recorded', ['name' => $area_name, 'houses_partial' => $values['houses_partial'], 'houses_total' => $values['houses_total']]);
                $message = 'The damage assessment for ' . $area_name . ' was added.';
            }
            $connection->commit();
            flash('disaster_success', $message);
            redirect('disaster_damage.php?incident=' . $values['incident_id']);
        } catch (PDOException) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = 'The assessment could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        }
    }
}

$incidents = disaster_recovery_incidents($connection, $record ? (int) $record['incident_id'] : null);
$areas = disaster_areas($connection, $record ? (int) $record['area_id'] : null);
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
$page_title = $is_edit ? 'Edit Damage Assessment' : 'Add Damage Assessment'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="disaster_damage.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p>One assessment per incident and area. Update it as more information comes in.</p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= e((string) DISASTER_PHOTO_MAX_BYTES) ?>">
        <?php if ($is_edit): ?><input type="hidden" name="form_updated_at" value="<?= e($record['updated_at']) ?>"><?php endif; ?>
        <fieldset class="resident-section">
            <legend>Assessment</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="incident_id">Incident <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('incident_id') ?>" id="incident_id" name="incident_id" required data-summary-label="Incident"><option value="">Select incident</option><?php foreach ($incidents as $incident): ?><option value="<?= e((string) $incident['id']) ?>" <?= $val('incident_id') === (string) $incident['id'] ? 'selected' : '' ?>><?= e(disaster_incident_label($incident)) ?></option><?php endforeach; ?></select><?= $field_error('incident_id') ?></div>
                <div><label class="form-label" for="area_id">Area <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('area_id') ?>" id="area_id" name="area_id" required data-summary-label="Area"><option value="">Select area</option><?php foreach ($areas as $area): ?><option value="<?= e((string) $area['id']) ?>" <?= $val('area_id') === (string) $area['id'] ? 'selected' : '' ?>><?= e($area['name']) ?></option><?php endforeach; ?></select><?= $field_error('area_id') ?></div>
                <div><label class="form-label" for="assessed_on">Date assessed <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('assessed_on') ?>" type="date" id="assessed_on" name="assessed_on" value="<?= e($val('assessed_on')) ?>" max="<?= e(date('Y-m-d')) ?>" required data-summary-label="Assessed"><?= $field_error('assessed_on') ?></div>
                <div></div>
                <div><label class="form-label" for="houses_partial">Houses partially damaged</label><input class="form-control<?= $field_class('houses_partial') ?>" type="number" id="houses_partial" name="houses_partial" min="0" max="10000" step="1" inputmode="numeric" value="<?= e($val('houses_partial')) ?>" data-summary-label="Partially damaged"><?= $field_error('houses_partial') ?></div>
                <div><label class="form-label" for="houses_total">Houses totally damaged</label><input class="form-control<?= $field_class('houses_total') ?>" type="number" id="houses_total" name="houses_total" min="0" max="10000" step="1" inputmode="numeric" value="<?= e($val('houses_total')) ?>" data-summary-label="Totally damaged"><?= $field_error('houses_total') ?></div>
            </div>
        </fieldset>
        <fieldset class="resident-section">
            <legend>Other Damage</legend>
            <div class="resident-grid">
                <?php foreach (disaster_damage_other_fields() as $field => $label): ?>
                    <div><label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label><input class="form-control<?= $field_class($field) ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" maxlength="255" value="<?= e($val($field)) ?>" placeholder="Leave blank if none"><?= $field_error($field) ?></div>
                <?php endforeach; ?>
                <div class="resident-grid-full"><label class="form-label" for="notes">Notes</label><textarea class="form-control<?= $field_class('notes') ?>" id="notes" name="notes" rows="3" maxlength="2000"><?= e($val('notes')) ?></textarea><?= $field_error('notes') ?></div>
                <div class="resident-grid-full">
                    <label class="form-label" for="photo">Photo <span class="activity-detail-muted">(optional · JPG, PNG or WebP, up to 5 MB)</span></label>
                    <input class="form-control<?= $field_class('photo') ?>" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp"><?= $field_error('photo') ?>
                    <?php if ($is_edit && $record['photo_path']): ?><div class="inventory-current-photo drr-current-photo"><a href="disaster_photo.php?id=<?= e((string) $record['id']) ?>" target="_blank" rel="noopener">View current photo</a><label class="form-check"><input class="form-check-input" type="checkbox" name="remove_photo" value="1"> <span class="form-check-label">Remove the photo</span></label></div><?php endif; ?>
                </div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $is_edit ? 'Save changes to this assessment?' : 'Add this assessment?' ?>" data-dialog-message="The change will be recorded in the Audit Logs." data-dialog-confirm="<?= $is_edit ? 'Save Changes' : 'Add Assessment' ?>" data-dialog-dismiss="Cancel"><?= $is_edit ? 'Save Changes' : 'Add Assessment' ?></button>
            <a class="btn btn-light" href="disaster_damage.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
