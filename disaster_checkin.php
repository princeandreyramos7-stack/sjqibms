<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_response.php';
disaster_require_manage();
$connection = db();
if (!disaster_response_ready($connection)) { flash('disaster_error', 'Evacuation tracking needs its database tables first.'); redirect('disaster_evacuation.php'); }

// Check in a family at an evacuation center for an incident. The family comes from the resident chosen (their household,
// or the resident alone). Capacity, Closed/Full centers and double check-ins are enforced on the server.
$values = ['incident_id' => (string) ($_GET['incident'] ?? ''), 'center_id' => (string) ($_GET['center'] ?? ''), 'resident_id' => '', 'family_members' => '', 'arrived_at' => date('Y-m-d\TH:i'), 'remarks' => ''];
$errors = [];
$form_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $form_error = 'Your session expired. Please review the form and submit again.';
        $values = array_merge($values, array_intersect_key(array_map(static fn ($v): string => is_string($v) ? $v : '', $_POST), $values));
    } else {
        try {
            $result = disaster_checkin($connection, $_POST);
            if (isset($result['id'])) { flash('disaster_success', $result['message']); redirect('disaster_evacuation.php'); }
            $errors = $result['errors'];
            $values = $result['values'];
        } catch (PDOException) {
            $form_error = 'The check-in could not be saved. No changes were made.';
        }
    }
}

$incidents = disaster_open_incidents($connection);
$centers = $connection->query("SELECT id, name, capacity, status FROM drr_evacuation_centers WHERE archived_at IS NULL AND status <> 'closed' ORDER BY FIELD(status, 'open', 'full'), name")->fetchAll();
$occupancy = disaster_center_occupancy($connection);
$family = ctype_digit((string) $values['resident_id']) ? disaster_family_for_resident($connection, (int) $values['resident_id']) : null;
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
$page_title = 'Check In Family'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="disaster_evacuation.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1>Check In Family</h1><p>Choose any member of the family: the whole household is checked in together. A resident without a household is checked in alone.</p></div></div>
    <?php if ($incidents === []): ?><div class="dashboard-status warning" role="status">There is no ongoing incident. Record the incident first in the Records tab (Type: Incident).</div><?php endif; ?>
    <?php if ($centers === []): ?><div class="dashboard-status warning" role="status">No evacuation center is open. Open a center first in the Evacuation Centers tab.</div><?php endif; ?>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" data-drr-family-form>
        <?= csrf_field() ?>
        <fieldset class="resident-section">
            <legend>Incident &amp; Center</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="incident_id">Incident <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('incident_id') ?>" id="incident_id" name="incident_id" required data-summary-label="Incident"><option value="">Select incident</option><?php foreach ($incidents as $incident): ?><option value="<?= e((string) $incident['id']) ?>" <?= $val('incident_id') === (string) $incident['id'] ? 'selected' : '' ?>><?= e(disaster_incident_label($incident)) ?></option><?php endforeach; ?></select><?= $field_error('incident_id') ?></div>
                <div><label class="form-label" for="center_id">Evacuation center <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('center_id') ?>" id="center_id" name="center_id" required data-summary-label="Center"><option value="">Select center</option><?php foreach ($centers as $center): $space = (int) $center['capacity'] - ($occupancy[(int) $center['id']]['persons'] ?? 0); ?><option value="<?= e((string) $center['id']) ?>" <?= $val('center_id') === (string) $center['id'] ? 'selected' : '' ?> <?= $center['status'] === 'full' || $space <= 0 ? 'disabled' : '' ?>><?= e($center['name']) ?> — <?= $center['status'] === 'full' || $space <= 0 ? 'Full' : e((string) $space) . ' of ' . e((string) $center['capacity']) . ' spaces left' ?></option><?php endforeach; ?></select><?= $field_error('center_id') ?></div>
            </div>
        </fieldset>
        <fieldset class="resident-section">
            <legend>Family</legend>
            <?php $picker_selected = $family ? ['name' => residents_full_name($family['resident']), 'meta' => $family['household_id'] ? 'Family: ' . $family['label'] . ' · ' . $family['members'] . ' members' : 'No household · checked in alone'] : null; require __DIR__ . '/layout/disaster_family_picker.php'; ?>
            <div class="resident-grid drr-family-extra">
                <div><label class="form-label" for="family_members">Family members checked in <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('family_members') ?>" type="number" id="family_members" name="family_members" min="1" max="50" step="1" inputmode="numeric" value="<?= e($val('family_members')) ?>" required data-drr-family-members data-summary-label="Persons"><div class="form-text">Filled in from the household's members; change it if not everyone came.</div><?= $field_error('family_members') ?></div>
                <div><label class="form-label" for="arrived_at">Arrival date and time <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('arrived_at') ?>" type="datetime-local" id="arrived_at" name="arrived_at" value="<?= e($val('arrived_at')) ?>" max="<?= e(date('Y-m-d\TH:i')) ?>" required data-summary-label="Arrived"><?= $field_error('arrived_at') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="remarks">Remarks</label><input class="form-control<?= $field_class('remarks') ?>" id="remarks" name="remarks" maxlength="500" value="<?= e($val('remarks')) ?>" placeholder="e.g. With a senior citizen needing a wheelchair"><?= $field_error('remarks') ?></div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="Check in this family?" data-dialog-message="The center is marked Full automatically when it reaches its capacity." data-dialog-confirm="Check In" data-dialog-dismiss="Cancel">Check In</button>
            <a class="btn btn-light" href="disaster_evacuation.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/disaster.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/disaster.js')) ?>"></script>
