<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health_programs.php';
$connection = db();
health_programs_require($connection);

// Record a vaccine given: resident, vaccine name (typed or picked from names used before), dose, date, given by, lot.
$values = ['resident_id' => filter_var($_GET['resident'] ?? null, FILTER_VALIDATE_INT) ?: '', 'vaccine_name' => '', 'dose_no' => '', 'date_given' => date('Y-m-d'), 'given_by' => current_user()['name'], 'lot_no' => '', 'remarks' => ''];
$errors = [];
$form_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$checked, $errors] = health_immunization_validate($connection, $_POST);
    $values = array_merge($values, array_map(static fn ($v) => (string) ($v ?? ''), $checked));
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    if ($form_error === null && $errors === []) {
        try {
            $connection->beginTransaction();
            $vaccine_id = health_vaccine_id($connection, $checked['vaccine_name'], (int) $checked['dose_no']);
            $connection->prepare('INSERT INTO health_immunizations (resident_id, vaccine_id, date_given, given_by, lot_no, remarks, created_by) VALUES (:r, :v, :d, :by, :lot, :remarks, :user)')
                ->execute(['r' => $checked['resident_id'], 'v' => $vaccine_id, 'd' => $checked['date_given'], 'by' => $checked['given_by'], 'lot' => $checked['lot_no'], 'remarks' => $checked['remarks'], 'user' => current_user()['id']]);
            health_program_audit($connection, (int) $connection->lastInsertId(), 'health_immunization_recorded', ['resident_id' => $checked['resident_id']]);
            $connection->commit();
            flash('health_success', 'The vaccine was recorded.');
            redirect(isset($_POST['again']) ? 'health_immunization_form.php' : 'health_immunization.php');
        } catch (PDOException) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = 'The vaccine could not be saved. No changes were made.';
        }
    }
}
$resident = (int) $values['resident_id'] > 0 ? health_program_resident($connection, (int) $values['resident_id']) : null;
$field_class = static fn (string $f): string => isset($errors[$f]) ? ' is-invalid' : '';
$field_error = static fn (string $f): string => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$val = static fn (string $f): string => (string) ($values[$f] ?? '');
$page_title = 'Record Vaccine'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="health_immunization.php"><span aria-hidden="true">&larr;</span> Back to Immunization</a>
    <div class="page-heading"><div><h1>Record Vaccine</h1></div></div>
    <?php if ($success = flash('health_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <fieldset class="resident-section">
            <legend>Resident</legend>
            <?= health_resident_picker($resident, '', $errors['resident_id'] ?? '') ?>
        </fieldset>
        <fieldset class="resident-section">
            <legend>Vaccine</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="vaccine_name">Vaccine <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('vaccine_name') ?>" id="vaccine_name" name="vaccine_name" maxlength="120" list="vaccine-names" value="<?= e($val('vaccine_name')) ?>" placeholder="Type the vaccine name" autocomplete="off" required data-summary-label="Vaccine"><datalist id="vaccine-names"><?php foreach (health_vaccine_names($connection) as $name): ?><option value="<?= e($name) ?>"></option><?php endforeach; ?></datalist><?= $field_error('vaccine_name') ?></div>
                <div><label class="form-label" for="dose_no">Dose <span class="activity-detail-muted">(optional)</span></label><input class="form-control<?= $field_class('dose_no') ?>" type="number" inputmode="numeric" id="dose_no" name="dose_no" min="1" max="10" value="<?= e($val('dose_no') === '1' && !isset($_POST['dose_no']) ? '' : $val('dose_no')) ?>" placeholder="e.g. 1" data-summary-label="Dose"><?= $field_error('dose_no') ?></div>
                <div><label class="form-label" for="date_given">Date given <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('date_given') ?>" type="date" id="date_given" name="date_given" max="<?= e(date('Y-m-d')) ?>" value="<?= e($val('date_given')) ?>" required data-summary-label="Date given"><?= $field_error('date_given') ?></div>
                <div><label class="form-label" for="given_by">Given by <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('given_by') ?>" id="given_by" name="given_by" maxlength="150" value="<?= e($val('given_by')) ?>" required data-summary-label="Given by"><?= $field_error('given_by') ?></div>
                <div><label class="form-label" for="lot_no">Lot number <span class="activity-detail-muted">(optional)</span></label><input class="form-control<?= $field_class('lot_no') ?>" id="lot_no" name="lot_no" maxlength="50" value="<?= e($val('lot_no')) ?>"><?= $field_error('lot_no') ?></div>
                <div><label class="form-label" for="remarks">Remarks <span class="activity-detail-muted">(optional)</span></label><input class="form-control<?= $field_class('remarks') ?>" id="remarks" name="remarks" maxlength="255" value="<?= e($val('remarks')) ?>"><?= $field_error('remarks') ?></div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="Record this vaccine?" data-dialog-message="Recorded in the audit log." data-dialog-confirm="Record" data-dialog-dismiss="Cancel">Record Vaccine</button>
            <button class="btn btn-outline-primary" type="submit" name="again" value="1">Record and add another</button>
            <a class="btn btn-light" href="health_immunization.php">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/health.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/health.js')) ?>"></script>
