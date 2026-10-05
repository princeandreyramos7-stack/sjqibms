<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster.php';
disaster_require_manage();
$connection = db();
if (!disaster_ready($connection)) { flash('disaster_error', 'Disaster Management needs its database tables first.'); redirect('disaster.php'); }

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$record = $id ? disaster_find($connection, $id) : null;
if ($id && !$record) { http_response_code(404); exit('DRR record not found.'); }
if ($record && $record['archived_at'] !== null) { flash('disaster_error', 'Restore this record before editing it.'); redirect('disaster_view.php?id=' . $id); }
$is_edit = $record !== null;
$fields = disaster_fields();
$me = current_user();
$values = $record ? array_intersect_key($record, array_flip($fields)) : ['title' => '', 'record_type' => '', 'area_id' => '', 'record_date' => date('Y-m-d'), 'status' => 'planned', 'description' => '', 'affected_families' => '', 'affected_persons' => '', 'alert_level' => '', 'incident_details' => ''];
$errors = [];
$form_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = disaster_validate($connection, $_POST, $record);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($is_edit && (string) ($_POST['form_updated_at'] ?? '') !== (string) $record['updated_at']) $form_error = 'This record was changed by another action after you opened it. Reload the page to review the latest information.';
    if ($form_error === null && $errors === []) {
        $params = array_intersect_key($values, array_flip($fields)) + ['user' => $me['id']];
        try {
            $connection->beginTransaction();
            if ($is_edit) {
                $locked = disaster_find($connection, $id, true);
                if (!$locked || $locked['updated_at'] !== $record['updated_at'] || $locked['archived_at'] !== null) throw new RuntimeException('This record was changed by another action after you opened it. Reload the page to review the latest information.');
                $changed = array_values(array_filter($fields, static fn (string $f): bool => (string) ($values[$f] ?? '') !== (string) ($locked[$f] ?? '')));
                if ($changed !== []) {
                    $set = implode(', ', array_map(static fn (string $f): string => "$f = :$f", $fields));
                    $connection->prepare("UPDATE drr_records SET $set, updated_by = :user WHERE id = :id")->execute($params + ['id' => $id]);
                    disaster_audit($connection, $id, 'disaster_record_updated', ['reference' => $locked['reference_no'], 'changed_fields' => $changed] + (in_array('status', $changed, true) ? ['status_from' => $locked['status'], 'status_to' => $values['status']] : []));
                }
                $saved_id = $id;
                $message = $changed === [] ? 'No changes were made to this record.' : $locked['reference_no'] . ' was updated.';
            } else {
                $reference = disaster_next_reference($connection);
                $columns = implode(', ', $fields);
                $placeholders = implode(', ', array_map(static fn (string $f): string => ":$f", $fields));
                $connection->prepare("INSERT INTO drr_records (reference_no, ref_year, ref_seq, $columns, created_by, updated_by) VALUES (:reference_no, :ref_year, :ref_seq, $placeholders, :user, :updater)")->execute($params + $reference + ['updater' => $me['id']]);
                $saved_id = (int) $connection->lastInsertId();
                disaster_audit($connection, $saved_id, 'disaster_record_created', ['reference' => $reference['reference_no'], 'record_type' => $values['record_type'], 'status' => $values['status']]);
                $message = $reference['reference_no'] . ' was added.';
            }
            $connection->commit();
            flash('disaster_success', $message);
            redirect('disaster_view.php?id=' . $saved_id);
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = ($exception->errorInfo[1] ?? 0) === 1062 ? 'Another record was saved at the same moment. Please submit again.' : 'The record could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        }
    }
}

$areas = disaster_areas($connection, $record ? (int) $record['area_id'] : null);
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
$cancel = $is_edit ? 'disaster_view.php?id=' . $record['id'] : 'disaster.php';
$page_title = $is_edit ? 'Edit DRR Record' : 'Add DRR Record'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="<?= e($cancel) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p><?= $is_edit ? 'Record ' . e($record['reference_no']) . '. The reference number never changes.' : 'The reference number (DRR-' . e(date('Y')) . '-NN) is assigned automatically when the record is saved.' ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" data-drr-record-form>
        <?= csrf_field() ?>
        <?php if ($is_edit): ?><input type="hidden" name="form_updated_at" value="<?= e($record['updated_at']) ?>"><?php endif; ?>

        <fieldset class="resident-section">
            <legend>Activity / Incident</legend>
            <div class="resident-grid">
                <div class="resident-grid-full"><label class="form-label" for="title">Title <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('title') ?>" id="title" name="title" maxlength="150" value="<?= e($val('title')) ?>" required placeholder="e.g. Typhoon preparedness briefing" data-summary-label="Title"><?= $field_error('title') ?></div>
                <div><label class="form-label" for="record_type">Type <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('record_type') ?>" id="record_type" name="record_type" required data-drr-type data-summary-label="Type"><option value="">Select type</option><?php foreach (disaster_types() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('record_type') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('record_type') ?></div>
                <div><label class="form-label" for="area_id">Area <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('area_id') ?>" id="area_id" name="area_id" required data-summary-label="Area"><option value="">Select area</option><?php foreach ($areas as $area): ?><option value="<?= e((string) $area['id']) ?>" <?= $val('area_id') === (string) $area['id'] ? 'selected' : '' ?>><?= e($area['name']) ?><?= (int) $area['is_active'] === 1 ? '' : ' (inactive)' ?></option><?php endforeach; ?></select><div class="form-text">Missing a place? Add it on the <a href="disaster_areas.php">Areas</a> page.</div><?= $field_error('area_id') ?></div>
                <div><label class="form-label" for="record_date">Date <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('record_date') ?>" type="date" id="record_date" name="record_date" value="<?= e($val('record_date')) ?>" required data-summary-label="Date"><?= $field_error('record_date') ?></div>
                <div><label class="form-label" for="status">Status <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('status') ?>" id="status" name="status" required data-summary-label="Status"><?php foreach (disaster_statuses() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('status') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('status') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="description">Description</label><textarea class="form-control<?= $field_class('description') ?>" id="description" name="description" rows="3" maxlength="2000" placeholder="What was done or what happened"><?= e($val('description')) ?></textarea><?= $field_error('description') ?></div>
            </div>
        </fieldset>

        <fieldset class="resident-section" data-drr-incident>
            <legend>Incident Details</legend>
            <p class="drr-hint">Shown for incidents only. Leave a count blank if it is not known yet.</p>
            <div class="resident-grid">
                <div><label class="form-label" for="affected_families">Affected families</label><input class="form-control<?= $field_class('affected_families') ?>" type="number" id="affected_families" name="affected_families" min="0" max="10000" step="1" inputmode="numeric" value="<?= e($val('affected_families')) ?>" data-summary-label="Affected families"><?= $field_error('affected_families') ?></div>
                <div><label class="form-label" for="affected_persons">Affected persons</label><input class="form-control<?= $field_class('affected_persons') ?>" type="number" id="affected_persons" name="affected_persons" min="0" max="100000" step="1" inputmode="numeric" value="<?= e($val('affected_persons')) ?>" data-summary-label="Affected persons"><?= $field_error('affected_persons') ?></div>
                <div><label class="form-label" for="alert_level">Warning level</label><select class="form-select<?= $field_class('alert_level') ?>" id="alert_level" name="alert_level" data-summary-label="Warning level"><option value="">None</option><?php foreach (disaster_alert_levels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('alert_level') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('alert_level') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="incident_details">Incident details</label><textarea class="form-control<?= $field_class('incident_details') ?>" id="incident_details" name="incident_details" rows="3" maxlength="2000" placeholder="Cause, extent of the damage, actions taken"><?= e($val('incident_details')) ?></textarea><?= $field_error('incident_details') ?></div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $is_edit ? 'Save changes to this record?' : 'Add this DRR record?' ?>" data-dialog-message="<?= $is_edit ? 'The changes will be recorded in the Audit Logs.' : 'A reference number will be assigned automatically.' ?>" data-dialog-confirm="<?= $is_edit ? 'Save Changes' : 'Add Record' ?>" data-dialog-dismiss="Cancel"><?= $is_edit ? 'Save Changes' : 'Add Record' ?></button>
            <a class="btn btn-light" href="<?= e($cancel) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/disaster.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/disaster.js')) ?>"></script>
