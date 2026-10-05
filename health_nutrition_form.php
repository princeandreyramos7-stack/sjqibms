<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health_programs.php';
$connection = db();
health_programs_require($connection);

// Record a weighing (Operation Timbang) of a child 0–59 months. The status is computed from the WHO Child Growth
// Standards when the WHO tables are loaded; otherwise the Health Worker picks it from the official chart.
$who = health_growth_reference_ready($connection);
$values = ['resident_id' => filter_var($_GET['resident'] ?? null, FILTER_VALIDATE_INT) ?: '', 'weigh_date' => date('Y-m-d'), 'weight_kg' => '', 'height_cm' => '', 'measured_lying' => '', 'wfa_status' => '', 'hfa_status' => '', 'wfh_status' => '', 'remarks' => ''];
$errors = [];
$form_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$checked, $errors] = health_nutrition_validate($connection, $_POST);
    $values = array_merge($values, array_map(static fn ($v) => is_array($v) ? $v : (string) ($v ?? ''), array_intersect_key($checked, $values)));
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    if ($form_error === null && $errors === []) {
        $connection->prepare('INSERT INTO health_nutrition (resident_id, weigh_date, age_months, weight_kg, height_cm, measured_lying, wfa_status, hfa_status, wfh_status, status_source, remarks, created_by) VALUES (:r, :d, :age, :w, :h, :lying, :wfa, :hfa, :wfh, :src, :remarks, :user)')
            ->execute(['r' => $checked['resident_id'], 'd' => $checked['weigh_date'], 'age' => $checked['age_months'], 'w' => $checked['weight_kg'], 'h' => $checked['height_cm'], 'lying' => $checked['measured_lying'], 'wfa' => $checked['wfa_status'], 'hfa' => $checked['hfa_status'], 'wfh' => $checked['wfh_status'], 'src' => $checked['status_source'], 'remarks' => $checked['remarks'], 'user' => current_user()['id']]);
        $saved = (int) $connection->lastInsertId();
        health_program_audit($connection, $saved, 'health_nutrition_recorded', ['resident_id' => $checked['resident_id']]);
        $labels = array_filter([health_wfa_statuses()[$checked['wfa_status']] ?? null, isset(health_hfa_statuses()[$checked['hfa_status'] ?? '']) ? 'height-for-age ' . strtolower(health_hfa_statuses()[$checked['hfa_status']]) : null, isset(health_wfh_statuses()[$checked['wfh_status'] ?? '']) ? 'weight-for-height ' . strtolower(health_wfh_statuses()[$checked['wfh_status']]) : null]);
        flash('health_success', 'The weighing was recorded. Status: ' . implode(', ', $labels) . '.');
        redirect('health_nutrition.php');
    }
}
$resident = (int) $values['resident_id'] > 0 ? health_program_resident($connection, (int) $values['resident_id'], 'under5') : null;
$field_class = static fn (string $f): string => isset($errors[$f]) ? ' is-invalid' : '';
$field_error = static fn (string $f): string => isset($errors[$f]) ? '<div class="invalid-feedback d-block">' . e($errors[$f]) . '</div>' : '';
$val = static fn (string $f): string => (string) ($values[$f] ?? '');
$page_title = 'Record Weighing'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="health_nutrition.php"><span aria-hidden="true">&larr;</span> Back to Nutrition</a>
    <div class="page-heading"><div><h1>Record Weighing</h1><p>Operation Timbang: children 0–59 months. <?= $who ? 'The nutritional status is computed from the WHO Child Growth Standards when you save.' : 'Choose the status from the official growth chart.' ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <fieldset class="resident-section">
            <legend>Child</legend>
            <?= health_resident_picker($resident, 'under5', $errors['resident_id'] ?? '', 'Select the Purok, then pick the child (0–59 months).') ?>
        </fieldset>
        <fieldset class="resident-section">
            <legend>Measurement</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="weigh_date">Date of weighing <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('weigh_date') ?>" type="date" id="weigh_date" name="weigh_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($val('weigh_date')) ?>" required data-summary-label="Date"><?= $field_error('weigh_date') ?></div>
                <div><label class="form-label" for="weight_kg">Weight (kg) <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('weight_kg') ?>" type="number" inputmode="decimal" id="weight_kg" name="weight_kg" min="1" max="40" step="0.01" value="<?= e($val('weight_kg')) ?>" required data-summary-label="Weight (kg)"><?= $field_error('weight_kg') ?></div>
                <div><label class="form-label" for="height_cm">Length / height (cm) <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('height_cm') ?>" type="number" inputmode="decimal" id="height_cm" name="height_cm" min="40" max="130" step="0.1" value="<?= e($val('height_cm')) ?>" required data-summary-label="Length / height (cm)"><?= $field_error('height_cm') ?></div>
                <div><span class="form-label d-block">How measured</span><div class="health-yesno"><label><input type="radio" name="measured_lying" value="1" <?= $val('measured_lying') === '1' ? 'checked' : '' ?>> Lying down (length)</label><label><input type="radio" name="measured_lying" value="0" <?= $val('measured_lying') !== '1' ? 'checked' : '' ?>> Standing (height)</label></div><div class="form-text">WHO: length lying down under 2 years, height standing from 2 years (adjusted by 0.7 cm otherwise).</div></div>
            </div>
        </fieldset>
        <?php if (!$who): ?>
        <fieldset class="resident-section">
            <legend>Nutritional status (from the official chart)</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="wfa_status">Weight-for-age <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('wfa_status') ?>" id="wfa_status" name="wfa_status"><option value="">Select</option><?php foreach (health_wfa_statuses() as $k => $l): ?><option value="<?= e($k) ?>" <?= $val('wfa_status') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $field_error('wfa_status') ?></div>
                <div><label class="form-label" for="hfa_status">Height-for-age</label><select class="form-select<?= $field_class('hfa_status') ?>" id="hfa_status" name="hfa_status"><option value="">Not assessed</option><?php foreach (health_hfa_statuses() as $k => $l): ?><option value="<?= e($k) ?>" <?= $val('hfa_status') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div><label class="form-label" for="wfh_status">Weight-for-height</label><select class="form-select<?= $field_class('wfh_status') ?>" id="wfh_status" name="wfh_status"><option value="">Not assessed</option><?php foreach (health_wfh_statuses() as $k => $l): ?><option value="<?= e($k) ?>" <?= $val('wfh_status') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
            </div>
        </fieldset>
        <?php endif; ?>
        <fieldset class="resident-section">
            <legend>Remarks</legend>
            <input class="form-control<?= $field_class('remarks') ?>" name="remarks" maxlength="255" value="<?= e($val('remarks')) ?>" aria-label="Remarks"><?= $field_error('remarks') ?>
        </fieldset>
        <div class="form-actions"><button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="Record this weighing?" data-dialog-message="Recorded in the audit log." data-dialog-confirm="Record" data-dialog-dismiss="Cancel">Record Weighing</button><a class="btn btn-light" href="health_nutrition.php">Cancel</a></div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/health.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/health.js')) ?>"></script>
