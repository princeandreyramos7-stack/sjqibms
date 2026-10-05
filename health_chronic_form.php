<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health_programs.php';
$connection = db();
health_programs_require($connection);

// Add / edit a Chronic Care record (hypertension, diabetes, TB). The resident and the condition are fixed once saved.
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$record = null;
if ($id) {
    $find = $connection->prepare('SELECT c.*, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.sex, r.purok FROM health_chronic_cases c INNER JOIN residents r ON r.id = c.resident_id WHERE c.id = :id');
    $find->execute(['id' => $id]);
    $record = $find->fetch() ?: null;
    if ($record === null || !residents_in_scope($connection, $record['purok'])) { http_response_code(404); exit('Record not found.'); }
    if ($record['archived_at'] !== null) { flash('health_error', 'Restore this record before editing it.'); redirect('health_chronic.php?status=archived'); }
}
$is_edit = $record !== null;
$fields = ['diagnosed_on', 'status', 'maintenance_medicines', 'is_serious', 'remarks'];
$values = $record ? array_intersect_key($record, array_flip(array_merge($fields, ['resident_id', 'condition_type']))) : ['resident_id' => filter_var($_GET['resident'] ?? null, FILTER_VALIDATE_INT) ?: '', 'condition_type' => '', 'diagnosed_on' => '', 'status' => 'active', 'maintenance_medicines' => '', 'is_serious' => 0, 'remarks' => ''];
$errors = [];
$form_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$checked, $errors] = health_chronic_validate($connection, $_POST, $record);
    $values = array_merge($values, array_map(static fn ($v) => $v ?? '', $checked));
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($is_edit && (string) ($_POST['form_updated_at'] ?? '') !== (string) $record['updated_at']) $form_error = 'This record was changed by another action after you opened it. Reload the page.';
    if ($form_error === null && $errors === []) {
        $params = array_intersect_key($checked, array_flip($fields)) + ['user' => current_user()['id']];
        if ($is_edit) {
            $connection->prepare('UPDATE health_chronic_cases SET diagnosed_on = :diagnosed_on, status = :status, maintenance_medicines = :maintenance_medicines, is_serious = :is_serious, remarks = :remarks, updated_by = :user, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute($params + ['id' => $id]);
            health_program_audit($connection, $id, 'health_chronic_updated', ['status' => $checked['status']]);
        } else {
            $connection->prepare('INSERT INTO health_chronic_cases (resident_id, condition_type, diagnosed_on, status, maintenance_medicines, is_serious, remarks, created_by, updated_by) VALUES (:resident_id, :condition_type, :diagnosed_on, :status, :maintenance_medicines, :is_serious, :remarks, :user, :updater)')->execute($params + ['resident_id' => $checked['resident_id'], 'condition_type' => $checked['condition_type'], 'updater' => current_user()['id']]);
            health_program_audit($connection, (int) $connection->lastInsertId(), 'health_chronic_created', ['condition' => $checked['condition_type']]);
        }
        flash('health_success', $is_edit ? 'The chronic care record was updated.' : 'The resident was added to Chronic Care.');
        redirect('health_chronic.php');
    }
}
$resident = $is_edit ? $record : ((int) $values['resident_id'] > 0 ? health_program_resident($connection, (int) $values['resident_id']) : null);
$field_class = static fn (string $f): string => isset($errors[$f]) ? ' is-invalid' : '';
$field_error = static fn (string $f): string => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$val = static fn (string $f): string => (string) ($values[$f] ?? '');
$page_title = $is_edit ? 'Edit Chronic Care Record' : 'Add Chronic Care Patient'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="health_chronic.php"><span aria-hidden="true">&larr;</span> Back to Chronic Care</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p>Readings are recorded as monitoring consultations (BP, Blood Sugar, TB-DOTS) from the Chronic Care list.</p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <?php if ($is_edit): ?><input type="hidden" name="form_updated_at" value="<?= e($record['updated_at']) ?>"><?php endif; ?>
        <fieldset class="resident-section">
            <legend>Resident and condition</legend>
            <?php if ($is_edit): ?>
                <p class="resident-static"><strong><?= e(residents_full_name($record)) ?></strong> · <?= e(health_chronic_conditions()[$record['condition_type']]) ?></p>
            <?php else: ?>
                <?= health_resident_picker($resident, '', $errors['resident_id'] ?? '') ?>
                <div class="mt-3"><label class="form-label" for="condition_type">Condition <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('condition_type') ?>" id="condition_type" name="condition_type" required data-summary-label="Condition"><option value="">Select</option><?php foreach (health_chronic_conditions() as $k => $l): ?><option value="<?= e($k) ?>" <?= $val('condition_type') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $field_error('condition_type') ?></div>
            <?php endif; ?>
        </fieldset>
        <fieldset class="resident-section">
            <legend>Care</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="diagnosed_on">Diagnosed on / treatment start</label><input class="form-control<?= $field_class('diagnosed_on') ?>" type="date" id="diagnosed_on" name="diagnosed_on" max="<?= e(date('Y-m-d')) ?>" value="<?= e($val('diagnosed_on')) ?>"><?= $field_error('diagnosed_on') ?></div>
                <div><label class="form-label" for="status">Status <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('status') ?>" id="status" name="status" data-summary-label="Status"><?php foreach (health_chronic_statuses() as $k => $l): ?><option value="<?= e($k) ?>" <?= $val('status') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $field_error('status') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="maintenance_medicines">Maintenance medicines</label><textarea class="form-control<?= $field_class('maintenance_medicines') ?>" id="maintenance_medicines" name="maintenance_medicines" rows="2" maxlength="500" placeholder="e.g. Losartan 50 mg once a day"><?= e($val('maintenance_medicines')) ?></textarea><?= $field_error('maintenance_medicines') ?></div>
                <div class="resident-grid-full"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_serious" value="1" <?= (int) $val('is_serious') === 1 ? 'checked' : '' ?>> <span class="form-check-label">Serious — needs closer follow-up by the Health Workers.</span></label></div>
                <div class="resident-grid-full"><label class="form-label" for="remarks">Remarks</label><textarea class="form-control<?= $field_class('remarks') ?>" id="remarks" name="remarks" rows="2" maxlength="500"><?= e($val('remarks')) ?></textarea><?= $field_error('remarks') ?></div>
            </div>
        </fieldset>
        <div class="form-actions"><button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $is_edit ? 'Save changes?' : 'Add to Chronic Care?' ?>" data-dialog-message="Recorded in the audit log." data-dialog-confirm="<?= $is_edit ? 'Save Changes' : 'Add' ?>" data-dialog-dismiss="Cancel"><?= $is_edit ? 'Save Changes' : 'Add Patient' ?></button><a class="btn btn-light" href="health_chronic.php">Cancel</a></div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/health.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/health.js')) ?>"></script>
