<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/households.php';
households_require_manage();
$connection = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$record = $id ? households_find($connection, $id) : null;
if ($id && !$record) { http_response_code(404); exit('Household not found.'); }
$is_edit = $record !== null;
$ready = households_details_ready($connection);
// Database columns saved by this form (address is composed from the parts in households_validate()).
$columns = ['household_no', 'purok', 'address', 'house_no', 'street', 'zone', 'housing_type', 'house_ownership', 'water_source', 'toilet_facility', 'has_electricity', 'notes'];
$form = households_form_values($record);
// A household recorded before the address was split into parts: its old address is shown as a guide while editing.
// (Street / sitio is always filled in on the new form; the house number may be blank, so it cannot tell.)
$legacy_address = $is_edit && (string) ($record['street'] ?? '') === '' ? (string) $record['address'] : null;
$errors = [];
$form_error = $ready ? null : 'The household form needs the database update 20261014_household_details. Please contact the System Administrator.';
$duplicate_number = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session token expired. Please review the form and submit again.';
    elseif ($is_edit && (string) ($_POST['form_updated_at'] ?? '') !== (string) $record['updated_at']) $form_error = 'This household was changed by another action after you opened it. Reload the page to review the latest information.';
    $validated = households_validate($connection, $_POST, $id, $record['purok'] ?? null);
    $values = $validated['values'];
    $form = $validated['form'];
    $errors = $validated['errors'];
    $duplicate_number = $validated['duplicate'];
    if ($form_error === null && $errors === []) {
        try {
            $connection->beginTransaction();
            if ($is_edit) {
                $locked = households_find($connection, $id, true);
                if (!$locked || $locked['updated_at'] !== $record['updated_at']) throw new RuntimeException('This household was changed by another action after you opened it. Reload the page to review the latest information.');
                // Only household columns change: members' own addresses, memberships and the Household Head are untouched.
                $changed = array_values(array_filter($columns, static fn (string $column): bool => (string) $values[$column] !== (string) $locked[$column]));
                if ($changed !== []) {
                    $update = $connection->prepare('UPDATE households SET household_no = :household_no, purok = :purok, address = :address, house_no = :house_no, street = :street, zone = :zone, housing_type = :housing_type, house_ownership = :house_ownership, water_source = :water_source, toilet_facility = :toilet_facility, has_electricity = :has_electricity, notes = :notes WHERE id = :id');
                    $update->execute($values + ['id' => $id]);
                    residents_audit($connection, 'household', $id, 'household_updated', ['changed_fields' => $changed]);
                }
                $saved_id = $id;
                $message = $changed === [] ? 'No changes were made to this household.' : 'Household updated successfully.';
            } else {
                // An empty household is created: no members, no Household Head, and therefore Unoccupied.
                $insert = $connection->prepare('INSERT INTO households (household_no, purok, address, house_no, street, zone, housing_type, house_ownership, water_source, toilet_facility, has_electricity, notes) VALUES (:household_no, :purok, :address, :house_no, :street, :zone, :housing_type, :house_ownership, :water_source, :toilet_facility, :has_electricity, :notes)');
                $insert->execute($values);
                $saved_id = (int) $connection->lastInsertId();
                residents_audit($connection, 'household', $saved_id, 'household_created', ['household_no' => $values['household_no']]);
                $message = 'Household ' . $values['household_no'] . ' has been created. Add members and set the household head.';
            }
            $connection->commit();
            flash('household_success', $message);
            redirect('household_view.php?id=' . $saved_id);
        } catch (PDOException $exception) { // before RuntimeException: PDOException extends it, and its message must never reach the user
            if ($connection->inTransaction()) $connection->rollBack();
            // The unique key still catches a number registered by someone else between validation and saving.
            if ($exception->getCode() === '23000') { $errors['household_no'] = 'This household number is already in use.'; $duplicate_number = true; }
            else $form_error = 'The household could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        }
    }
}
// A new household starts with the next number of the chosen Purok (also filled in by the page when the Purok changes).
$auto_number = !$is_edit && $form['household_no'] === '' && $form['purok'] !== '' ? households_next_number($connection, $form['purok']) : null;
if ($auto_number !== null) $form['household_no'] = $auto_number;
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => '<div class="invalid-feedback" data-error-for="' . e($field) . '"' . (isset($errors[$field]) ? '' : ' hidden') . '>' . e($errors[$field] ?? '') . '</div>';
$select = static function (string $name, string $label, array $options, string $placeholder, bool $required = false) use ($form, $field_class, $field_error): string {
    $html = '<div><label class="form-label" for="' . e($name) . '">' . e($label) . ($required ? ' <span class="resident-required" aria-hidden="true">*</span>' : '') . '</label><select class="form-select' . $field_class($name) . '" id="' . e($name) . '" name="' . e($name) . '"' . ($required ? ' data-required="' . e($name === 'purok' ? 'Please select a purok.' : 'Please select an option.') . '"' : '') . '><option value="">' . e($placeholder) . '</option>';
    foreach ($options as $value => $text) $html .= '<option value="' . e((string) $value) . '"' . ((string) $form[$name] === (string) $value ? ' selected' : '') . '>' . e($text) . '</option>';
    return $html . '</select>' . $field_error($name) . '</div>';
};
$list = static fn (array $options): array => array_combine($options, $options);
$cancel_url = $is_edit ? 'household_view.php?id=' . $record['id'] : 'households.php';
$page_title = $is_edit ? 'Edit Household' : 'Add Household'; $active_page = 'households';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="<?= e($cancel_url) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p><?= $is_edit ? 'Update the information of household ' . e($record['household_no']) . '. Members and the Household Head are managed separately.' : 'Register a household. Members and the Household Head are assigned afterwards.' ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <?php if ($ready): ?>
    <form method="post" novalidate data-household-form data-next-url="household_next_no.php" data-exclude="<?= e((string) ($id ?? '')) ?>" data-edit="<?= $is_edit ? '1' : '0' ?>">
        <?= csrf_field() ?>
        <?php if ($is_edit): ?><input type="hidden" name="form_updated_at" value="<?= e($record['updated_at']) ?>"><?php endif; ?>

        <fieldset class="resident-section">
            <legend>Household Information</legend>
            <div class="resident-grid">
                <?= $select('purok', 'Purok', residents_purok_select_options($record['purok'] ?? null), 'Select Purok', true) ?>
                <div><label class="form-label" for="household_no">Household number <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('household_no') ?>" id="household_no" name="household_no" maxlength="50" value="<?= e($form['household_no']) ?>" placeholder="Select a Purok first" autocomplete="off" data-required="Household number is required." data-auto="<?= $auto_number !== null ? '1' : '0' ?>"><?= $field_error('household_no') ?><div class="form-text">Filled in automatically as P[purok]-[number]. You may change it, for example to an old RBI number.</div></div>
            </div>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Address</legend>
            <?php if ($legacy_address !== null): ?><p class="resident-static">Address on record: <strong><?= e($legacy_address) ?></strong>. Enter it in the fields below.</p><?php endif; ?>
            <div class="resident-grid">
                <div><label class="form-label" for="house_no">House number <span class="activity-detail-muted">(optional)</span></label><input class="form-control<?= $field_class('house_no') ?>" id="house_no" name="house_no" maxlength="20" value="<?= e($form['house_no']) ?>" placeholder="e.g. 123 or 12-B" autocomplete="off"><div class="form-text">Leave blank if the house has no number.</div><?= $field_error('house_no') ?></div>
                <div><label class="form-label" for="street">Street / Sitio <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('street') ?>" id="street" name="street" maxlength="150" value="<?= e($form['street']) ?>" placeholder="e.g. Centro, Rizal St." autocomplete="off" data-required="Street / sitio is required."><?= $field_error('street') ?></div>
                <div><label class="form-label" for="zone">Zone <span class="activity-detail-muted">(optional)</span></label><input class="form-control<?= $field_class('zone') ?>" id="zone" name="zone" maxlength="40" value="<?= e($form['zone']) ?>" placeholder="e.g. Zone 2" autocomplete="off"><?= $field_error('zone') ?></div>
                <div><label class="form-label" for="barangay">Barangay</label><input class="form-control" id="barangay" value="<?= e(households_barangay_label()) ?>" readonly tabindex="-1"></div>
            </div>
            <?php if ($is_edit): ?><p class="resident-static">Editing the household address or Purok does not change the personal address or Purok of its members.</p><?php endif; ?>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Housing</legend>
            <div class="resident-grid">
                <div>
                    <label class="form-label" for="housing_type">Housing type <span class="activity-detail-muted">(optional)</span></label>
                    <select class="form-select<?= $field_class('housing_type') ?>" id="housing_type" name="housing_type" data-other-toggle="housing-type-other"><option value="">Select housing type</option><?php foreach (households_housing_types() as $option): ?><option value="<?= e($option) ?>" <?= $form['housing_type'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><?= $field_error('housing_type') ?>
                    <div id="housing-type-other" class="mt-2"<?= $form['housing_type'] === 'Other' ? '' : ' hidden' ?>><label class="form-label" for="housing_type_other">Please specify <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('housing_type_other') ?>" id="housing_type_other" name="housing_type_other" maxlength="70" value="<?= e($form['housing_type_other']) ?>" autocomplete="off" data-required-when="housing_type=Other" data-required="Please specify the housing type."><?= $field_error('housing_type_other') ?></div>
                </div>
                <?= $select('house_ownership', 'House ownership (optional)', $list(households_ownership_options()), 'Select ownership') ?>
            </div>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Additional Information</legend>
            <div class="resident-grid">
                <?= $select('water_source', 'Water source (optional)', $list(households_water_sources()), 'Select water source') ?>
                <?= $select('toilet_facility', 'Toilet facility (optional)', $list(households_toilet_options()), 'Select toilet facility') ?>
                <?= $select('has_electricity', 'Has electricity (optional)', ['1' => 'Yes', '0' => 'No'], 'Select') ?>
                <div class="resident-grid-full"><label class="form-label" for="notes">Notes <span class="activity-detail-muted">(optional)</span></label><textarea class="form-control<?= $field_class('notes') ?>" id="notes" name="notes" rows="3" maxlength="1000"><?= e($form['notes']) ?></textarea><?= $field_error('notes') ?></div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save household</button>
            <a class="btn btn-light" href="<?= e($cancel_url) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<script src="assets/js/household_form.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/household_form.js') ?>"></script>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
