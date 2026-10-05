<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health.php';
health_require_manage();
$connection = db();
if (!health_ready($connection)) { flash('health_error', 'Health records need their database table first.'); redirect('health.php'); }

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$record = $id ? health_find($connection, $id) : null;
if ($id && !$record) { http_response_code(404); exit('Health record not found.'); }
if ($record && $record['archived_at'] !== null) { flash('health_error', 'Restore this record before editing it.'); redirect('health_view.php?id=' . $id); }
$is_edit = $record !== null;
$phase2 = health_phase2_ready($connection);
$fields = health_fields($connection);
$me = current_user();
$values = $record ? array_intersect_key($record, array_flip($fields)) : array_fill_keys($fields, '');
// From the Chronic Care tab: the service is chosen already (for example ?service=bp_monitoring).
$preset_service = array_key_exists((string) ($_GET['service'] ?? ''), health_services()) ? (string) $_GET['service'] : '';
if (!$record) $values = ['service' => $preset_service, 'resident_id' => filter_var($_GET['resident'] ?? null, FILTER_VALIDATE_INT) ?: '', 'health_worker' => $me['role'] === 'health_worker' ? $me['name'] : '', 'service_date' => date('Y-m-d'), 'status' => 'completed', 'referred_rhu' => 0] + $values;
$errors = [];
$form_error = null;
$medicine_lines = [];   // posted lines, shown again after an error
// Same value? Numbers are compared as numbers (55.00 kg in the database equals 55 typed in the form).
$same = static fn ($a, $b): bool => is_numeric($a) && is_numeric($b) ? (float) $a === (float) $b : (string) ($a ?? '') === (string) ($b ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = health_validate($connection, $_POST, $record);
    $values = $validated['values'];
    $errors = $validated['errors'];
    $wanted = [];
    if ($phase2) {
        foreach ((array) ($_POST['medicine_item'] ?? []) as $index => $item) $medicine_lines[] = ['item' => (string) $item, 'qty' => (string) (((array) ($_POST['medicine_qty'] ?? []))[$index] ?? '')];
        [$wanted, $medicine_errors] = health_medicine_input($connection, $_POST);
        $errors += $medicine_errors;
        if ($wanted !== [] && !in_array($values['status'], ['completed', 'follow_up'], true)) $errors['medicines'] = 'Medicines can be given only on a Completed or Follow-up visit.';
    }
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($is_edit && (string) ($_POST['form_updated_at'] ?? '') !== (string) $record['updated_at']) $form_error = 'This record was changed by another action after you opened it. Reload the page to review the latest information.';
    if ($form_error === null && $errors === []) {
        $params = array_intersect_key($values, array_flip($fields)) + ['user' => $me['id']];
        try {
            $connection->beginTransaction();
            $medicines = ['medicines_given' => count($wanted)];
            if ($is_edit) {
                $locked = health_find($connection, $id, true);
                if (!$locked || $locked['updated_at'] !== $record['updated_at'] || $locked['archived_at'] !== null) throw new RuntimeException('This record was changed by another action after you opened it. Reload the page to review the latest information.');
                $changed = array_values(array_filter($fields, static fn (string $f): bool => !$same($values[$f] ?? null, $locked[$f] ?? null)));
                if ($changed !== [] || $wanted !== []) {
                    $set = implode(', ', array_map(static fn (string $f): string => "$f = :$f", $fields));
                    $connection->prepare("UPDATE health_records SET $set, updated_by = :user, updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute($params + ['id' => $id]);
                    health_give_medicines($connection, $id, (string) $locked['record_no'], $wanted);
                    health_audit($connection, $id, 'health_record_updated', ['record_no' => $locked['record_no'], 'changed_fields' => $changed] + (in_array('status', $changed, true) ? ['status_from' => $locked['status'], 'status_to' => $values['status']] : []) + ($wanted !== [] ? $medicines : []));
                }
                $saved_id = $id;
                $message = $changed === [] && $wanted === [] ? 'No changes were made to this record.' : $locked['record_no'] . ' was updated.';
            } else {
                $number = health_next_no($connection);
                $columns = implode(', ', $fields);
                $placeholders = implode(', ', array_map(static fn (string $f): string => ":$f", $fields));
                $connection->prepare("INSERT INTO health_records (record_no, $columns, created_by, updated_by) VALUES (:record_no, $placeholders, :user, :updater)")->execute($params + ['record_no' => $number, 'updater' => $me['id']]);
                $saved_id = (int) $connection->lastInsertId();
                health_give_medicines($connection, $saved_id, $number, $wanted);
                health_audit($connection, $saved_id, 'health_record_created', ['record_no' => $number, 'service' => $values['service'], 'status' => $values['status']] + ($wanted !== [] ? $medicines : []));
                $message = $number . ' was added.';
            }
            $connection->commit();
            flash('health_success', $message . ($wanted !== [] ? ' The medicines given were deducted from the Inventory.' : ''));
            redirect('health_view.php?id=' . $saved_id);
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = ($exception->errorInfo[1] ?? 0) === 1062 ? 'Another record was saved at the same moment. Please submit again.' : 'The record could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage() . ' No changes were made.';
        }
    }
}

$resident = is_int($values['resident_id']) || ctype_digit((string) $values['resident_id']) ? health_resident($connection, (int) $values['resident_id']) : null;
$workers = health_worker_options($connection);
$conditions = $phase2 ? health_conditions($connection) : [];
// A diagnosis no longer in the active list stays selectable on the record that already uses it.
if ($phase2 && $is_edit && $record['condition_id'] !== null && !in_array((int) $record['condition_id'], array_map('intval', array_column($conditions, 'id')), true)) $conditions[] = ['id' => $record['condition_id'], 'name' => $record['condition_name'] . ' (no longer in the list)'];
$medicine_options = $phase2 ? health_medicine_options($connection) : [];
$given = $phase2 && $is_edit ? health_record_medicines($connection, (int) $record['id']) : [];
if ($medicine_lines === []) $medicine_lines[] = ['item' => '', 'qty' => ''];
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
// Decimals without trailing zeros (55.00 → 55).
$num = static fn (string $field): string => is_numeric($values[$field] ?? null) && str_contains((string) $values[$field], '.') ? rtrim(rtrim((string) $values[$field], '0'), '.') : $val($field);
$medicine_select = static function (string $selected) use ($medicine_options): string {
    $html = '<option value="">Select medicine</option>';
    foreach ($medicine_options as $option) $html .= '<option value="' . e((string) $option['id']) . '"' . ((string) $option['id'] === $selected ? ' selected' : '') . '>' . e($option['name'] . ' — ' . number_format((int) $option['quantity']) . ' ' . $option['unit'] . ' on hand' . ($option['expiry_date'] ? ' (exp. ' . health_format_date($option['expiry_date']) . ')' : '')) . '</option>';
    return $html;
};
$cancel = $is_edit ? 'health_view.php?id=' . $record['id'] : 'health.php';
$page_title = $is_edit ? 'Edit Health Record' : 'Add Health Record'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="<?= e($cancel) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p><?= $is_edit ? 'Record ' . e($record['record_no']) . '. The record number never changes.' : 'The record number (HLT-####) is assigned automatically when the record is saved.' ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" data-health-form>
        <?= csrf_field() ?>
        <?php if ($is_edit): ?><input type="hidden" name="form_updated_at" value="<?= e($record['updated_at']) ?>"><?php endif; ?>

        <fieldset class="resident-section">
            <legend>Resident</legend>
            <div class="health-resident-picker" data-health-resident>
                <input type="hidden" name="resident_id" value="<?= e($resident ? (string) $resident['id'] : '') ?>" data-health-resident-id>
                <div class="health-selected<?= $resident ? '' : ' is-empty' ?>" data-health-selected>
                    <div><strong data-health-selected-name><?= $resident ? e(residents_full_name($resident)) : 'No resident selected' ?></strong><span data-health-selected-meta><?= $resident ? e(health_resident_meta($resident)) : 'Select the Purok, then choose the resident.' ?></span></div>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-health-change <?= $resident ? '' : 'hidden' ?>>Change</button>
                </div>
                <div class="health-search" data-health-search <?= $resident ? 'hidden' : '' ?>>
                    <div class="health-search-grid">
                        <div><label class="form-label" for="health-purok">Purok <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('resident_id') ?>" id="health-purok" data-health-purok data-summary-skip><option value="">Select Purok</option><?php foreach (health_resident_puroks($connection) as $purok_value): ?><option value="<?= e($purok_value) ?>"><?= e(residents_purok_label($purok_value)) ?></option><?php endforeach; ?></select></div>
                        <div class="health-search-box">
                            <label class="form-label" for="health-resident-search">Resident <span class="resident-required" aria-hidden="true">*</span></label>
                            <input class="form-control<?= $field_class('resident_id') ?>" type="search" id="health-resident-search" placeholder="Select a Purok first" autocomplete="off" maxlength="100" disabled data-health-query data-summary-skip aria-controls="health-resident-list" aria-expanded="false">
                            <div class="case-lookup-results health-dropdown" id="health-resident-list" role="listbox" aria-label="Residents in the selected Purok" data-health-results hidden></div>
                        </div>
                    </div>
                    <p class="health-hint" data-health-hint>Select the resident's Purok, then pick the resident from the list.</p>
                    <noscript><p class="resident-static">Choosing a resident needs JavaScript. Open the resident's Health History and use "New Record" there, or enable JavaScript.</p></noscript>
                </div>
                <?= $field_error('resident_id') ?>
            </div>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Service</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="service">Service <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('service') ?>" id="service" name="service" required data-summary-label="Service"><option value="">Select service</option><?php foreach (health_services() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('service') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('service') ?></div>
                <div><label class="form-label" for="service_details">Details <span class="activity-detail-muted">(required for "Other")</span></label><input class="form-control<?= $field_class('service_details') ?>" id="service_details" name="service_details" maxlength="150" value="<?= e($val('service_details')) ?>" placeholder="e.g. Measles vaccine, wound dressing"><?= $field_error('service_details') ?></div>
                <div><label class="form-label" for="health_worker">Health worker <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('health_worker') ?>" id="health_worker" name="health_worker" maxlength="150" value="<?= e($val('health_worker')) ?>" list="health-workers" required autocomplete="off" data-summary-label="Health worker"><datalist id="health-workers"><?php foreach ($workers as $worker): ?><option value="<?= e($worker) ?>"></option><?php endforeach; ?></datalist><div class="form-text">Choose from the list or type the name (e.g. a nurse from the Rural Health Unit).</div><?= $field_error('health_worker') ?></div>
                <div><label class="form-label" for="service_date">Date <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('service_date') ?>" type="date" id="service_date" name="service_date" value="<?= e($val('service_date')) ?>" required data-summary-label="Date"><?= $field_error('service_date') ?></div>
                <div><label class="form-label" for="status">Status <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('status') ?>" id="status" name="status" required data-health-status data-summary-label="Status"><?php foreach (health_statuses() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('status') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('status') ?></div>
            </div>
        </fieldset>

        <?php if ($phase2): ?>
        <fieldset class="resident-section">
            <legend>Vital Signs <span class="activity-detail-muted">(optional)</span></legend>
            <div class="health-vitals">
                <div class="health-vital-bp"><label class="form-label" for="bp_systolic">Blood pressure <span class="activity-detail-muted">(mmHg)</span></label><div class="health-bp"><input class="form-control<?= $field_class('bp') ?>" type="number" inputmode="numeric" id="bp_systolic" name="bp_systolic" min="50" max="300" step="1" value="<?= e($val('bp_systolic')) ?>" placeholder="120" aria-label="Upper number (systolic)" data-summary-label="BP upper"><span aria-hidden="true">/</span><input class="form-control<?= $field_class('bp') ?>" type="number" inputmode="numeric" id="bp_diastolic" name="bp_diastolic" min="30" max="200" step="1" value="<?= e($val('bp_diastolic')) ?>" placeholder="80" aria-label="Lower number (diastolic)" data-summary-label="BP lower"></div><?= $field_error('bp') ?></div>
                <div><label class="form-label" for="weight_kg">Weight <span class="activity-detail-muted">(kg)</span></label><input class="form-control<?= $field_class('weight_kg') ?>" type="number" inputmode="decimal" id="weight_kg" name="weight_kg" min="0.5" max="300" step="0.01" value="<?= e($num('weight_kg')) ?>" placeholder="55.5" data-summary-label="Weight (kg)"><?= $field_error('weight_kg') ?></div>
                <div><label class="form-label" for="height_cm">Height <span class="activity-detail-muted">(cm)</span></label><input class="form-control<?= $field_class('height_cm') ?>" type="number" inputmode="decimal" id="height_cm" name="height_cm" min="30" max="250" step="0.1" value="<?= e($num('height_cm')) ?>" placeholder="160" data-summary-label="Height (cm)"><?= $field_error('height_cm') ?></div>
                <div><label class="form-label" for="temperature_c">Temperature <span class="activity-detail-muted">(°C)</span></label><input class="form-control<?= $field_class('temperature_c') ?>" type="number" inputmode="decimal" id="temperature_c" name="temperature_c" min="30" max="45" step="0.1" value="<?= e($num('temperature_c')) ?>" placeholder="36.5" data-summary-label="Temperature (°C)"><?= $field_error('temperature_c') ?></div>
                <?php if (health_programs_columns_ready($connection)): ?><div><label class="form-label" for="blood_sugar_mgdl">Blood sugar <span class="activity-detail-muted">(mg/dL)</span></label><input class="form-control<?= $field_class('blood_sugar_mgdl') ?>" type="number" inputmode="decimal" id="blood_sugar_mgdl" name="blood_sugar_mgdl" min="20" max="900" step="0.1" value="<?= e($num('blood_sugar_mgdl')) ?>" placeholder="110" data-summary-label="Blood sugar (mg/dL)"><?= $field_error('blood_sugar_mgdl') ?></div><?php endif; ?>
            </div>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Consultation</legend>
            <div class="resident-grid">
                <div class="resident-grid-full"><label class="form-label" for="chief_complaint">Chief complaint</label><input class="form-control<?= $field_class('chief_complaint') ?>" id="chief_complaint" name="chief_complaint" maxlength="255" value="<?= e($val('chief_complaint')) ?>" placeholder="What the resident came in for, in their own words" data-summary-label="Chief complaint"><?= $field_error('chief_complaint') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="findings">Findings</label><textarea class="form-control<?= $field_class('findings') ?>" id="findings" name="findings" rows="3" maxlength="2000" placeholder="What the health worker observed or found"><?= e($val('findings')) ?></textarea><?= $field_error('findings') ?></div>
                <div class="resident-grid-full">
                    <label class="form-label" for="condition_id">Diagnosis / illness <span class="activity-detail-muted">(counted in the Morbidity Report)</span></label>
                    <select class="form-select<?= $field_class('condition_id') ?>" id="condition_id" name="condition_id" data-summary-label="Diagnosis"><option value="">None / not applicable</option><?php foreach ($conditions as $condition): ?><option value="<?= e((string) $condition['id']) ?>" <?= $val('condition_id') === (string) $condition['id'] ? 'selected' : '' ?>><?= e($condition['name']) ?></option><?php endforeach; ?></select>
                    <div class="form-text"><?= $conditions === [] ? 'The diagnosis list is empty. ' : 'Not in the list? ' ?><a href="health_conditions.php" target="_blank" rel="noopener">Open the Diagnosis List</a> to add it, then reload this page.</div><?= $field_error('condition_id') ?>
                </div>
            </div>
        </fieldset>

        <fieldset class="resident-section" data-health-medicines>
            <legend>Medicines Given <span class="activity-detail-muted">(from the Inventory)</span></legend>
            <?php if ($given !== []): ?>
                <p class="form-label mb-1">Already given on this record</p>
                <ul class="health-given"><?php foreach ($given as $line): ?><li><?= e($line['name']) ?> — <?= e(number_format((int) $line['quantity']) . ' ' . $line['unit']) ?></li><?php endforeach; ?></ul>
                <p class="form-text">Medicines already given cannot be changed here. Add only the ones given additionally.</p>
            <?php endif; ?>
            <?php if ($medicine_options === []): ?>
                <p class="resident-static">No medicines with stock in the Inventory (Medical category). Ask the System Administrator to add the stock.</p>
            <?php else: ?>
                <div class="health-medicine-lines" data-health-medicine-lines>
                    <?php foreach ($medicine_lines as $line): ?>
                    <div class="health-medicine-line" data-health-medicine-line>
                        <select class="form-select<?= $field_class('medicines') ?>" name="medicine_item[]" aria-label="Medicine" data-summary-skip><?= $medicine_select($line['item']) ?></select>
                        <input class="form-control<?= $field_class('medicines') ?>" type="number" inputmode="numeric" name="medicine_qty[]" min="1" max="10000" step="1" value="<?= e($line['qty']) ?>" placeholder="Qty" aria-label="Quantity" data-summary-skip>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-health-medicine-remove aria-label="Remove this medicine">&times;</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-sm btn-outline-primary mt-2" type="button" data-health-medicine-add>+ Add another medicine</button>
                <div class="form-text">The quantity is deducted from the Inventory when the record is saved. Only for Completed or Follow-up visits.</div>
            <?php endif; ?>
            <?= $field_error('medicines') ?>
        </fieldset>
        <?php endif; ?>

        <fieldset class="resident-section">
            <legend>Next Steps</legend>
            <div class="resident-grid">
                <div data-health-follow-up><label class="form-label" for="follow_up_date">Follow-up date <span class="resident-required" aria-hidden="true" data-health-follow-up-required>*</span></label><input class="form-control<?= $field_class('follow_up_date') ?>" type="date" id="follow_up_date" name="follow_up_date" value="<?= e($val('follow_up_date')) ?>" data-summary-label="Follow-up"><div class="form-text">When the resident should come back. Optional; required when the status is Follow-up.</div><?= $field_error('follow_up_date') ?></div>
                <?php if ($phase2): ?>
                <div>
                    <span class="form-label d-block">Referred to the RHU?</span>
                    <div class="health-yesno"><label><input type="radio" name="referred_rhu" value="0" <?= (int) $val('referred_rhu') === 1 ? '' : 'checked' ?> data-health-referred> No</label><label><input type="radio" name="referred_rhu" value="1" <?= (int) $val('referred_rhu') === 1 ? 'checked' : '' ?> data-health-referred data-summary-label="Referred to RHU"> Yes</label></div>
                </div>
                <div class="resident-grid-full" data-health-referral><label class="form-label" for="referral_reason">Reason for referral <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('referral_reason') ?>" id="referral_reason" name="referral_reason" maxlength="255" value="<?= e($val('referral_reason')) ?>" placeholder="Why the resident is sent to the Rural Health Unit" data-summary-label="Referral reason"><?= $field_error('referral_reason') ?></div>
                <?php endif; ?>
                <div class="resident-grid-full"><label class="form-label" for="remarks">Remarks</label><textarea class="form-control<?= $field_class('remarks') ?>" id="remarks" name="remarks" rows="3" maxlength="1000" placeholder="Short notes for the health worker"><?= e($val('remarks')) ?></textarea><?= $field_error('remarks') ?></div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $is_edit ? 'Save changes to this record?' : 'Add this health record?' ?>" data-dialog-message="<?= $is_edit ? 'The changes will be recorded in the Audit Logs. Medicines added are deducted from the Inventory.' : 'A record number will be assigned automatically. Medicines added are deducted from the Inventory.' ?>" data-dialog-confirm="<?= $is_edit ? 'Save Changes' : 'Add Record' ?>" data-dialog-dismiss="Cancel"><?= $is_edit ? 'Save Changes' : 'Add Record' ?></button>
            <a class="btn btn-light" href="<?= e($cancel) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/health.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/health.js')) ?>"></script>
