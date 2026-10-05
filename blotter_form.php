<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/complaints.php';
complaints_require_manage();
$connection = db();
complaints_require_schema($connection);

// New blotter entry: standalone, or linked to a complaint (Under Review) with its information pre-filled for review.
// Saving creates an independent blotter record; the original complaint is never modified.
$complaint_id = filter_var($_POST['complaint_id'] ?? $_GET['complaint'] ?? null, FILTER_VALIDATE_INT) ?: null;
$complaint = $complaint_id ? complaints_find($connection, $complaint_id) : null;
if ($complaint_id && !$complaint) { http_response_code(404); exit('Complaint not found.'); }
$link_error = $complaint && $complaint['status'] !== 'under_review' ? 'A blotter entry can be recorded from a complaint only while it is Under Review. This complaint is ' . (complaints_status_labels(true)[$complaint['status']] ?? $complaint['status']) . '.' : null;

$values = ['incident_type' => '', 'incident_at' => '', 'incident_location' => '', 'narrative' => '', 'supporting_note' => ''];
$state = ['complainant' => ['role' => 'complainant'], 'respondent' => ['role' => 'respondent'], 'additional' => []];
if ($complaint && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Pre-fill from the complaint (copies for review; editing them never changes the complaint).
    $values = ['incident_type' => (string) $complaint['category'], 'incident_at' => complaints_datetime_local($complaint['incident_at']), 'incident_location' => (string) $complaint['incident_location'], 'narrative' => (string) $complaint['confidential_details'], 'supporting_note' => ''];
    $prefill = array_map(static fn (array $p): array => ['role' => $p['person_role'], 'resident_id' => $p['resident_id'], 'full_name' => $p['full_name'], 'contact_number' => $p['contact_number'], 'address' => $p['address'], 'identifying_details' => $p['identifying_details']], complaints_persons($connection, 'complaint', (int) $complaint['id']));
    $state = complaints_person_form_state($prefill);
    $state['complainant'] ??= ['role' => 'complainant'];
    $state['respondent'] ??= ['role' => 'respondent'];
}
$errors = [];
$person_errors = [];
$form_error = $link_error;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $link_error === null) {
    $validated = blotter_validate_details($_POST);
    $values = $validated['values'];
    $errors = $validated['errors'];
    $collected = complaints_collect_persons($connection, (array) ($_POST['persons'] ?? []), ['complainant', 'respondent', 'witness', 'other']);
    $person_errors = $collected['errors'];
    complaints_require_parties($collected['persons'], $person_errors);
    $state = complaints_person_form_state($collected['persons']);
    $state['complainant'] ??= ['role' => 'complainant'];
    $state['respondent'] ??= ['role' => 'respondent'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($errors === [] && $person_errors === [] && complaints_is_duplicate_submission('blotter', [$complaint_id, $values, $collected['persons']])) $form_error = 'This blotter entry was just saved. Check the Blotter Records list before saving it again.';
    if ($form_error === null && $errors === [] && $person_errors === []) {
        try {
            $created = blotter_create($connection, $values, $collected['persons'], $complaint_id);
            complaints_remember_submission('blotter', [$complaint_id, $values, $collected['persons']]);
            flash('case_success', 'Blotter entry ' . $created['reference'] . ' recorded.');
            redirect('blotter_view.php?id=' . $created['id']);
        } catch (PDOException) { // before RuntimeException: PDOException extends it, and its message must never reach the user
            $form_error = 'The blotter entry could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            $form_error = $exception->getMessage();
        }
    }
}
$types = complaints_distinct_values($connection, 'incident_type');
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$next_index = 2 + count($state['additional']);
$back = $complaint ? 'complaint_view.php?id=' . $complaint['id'] : 'complaints.php?tab=blotter';
$page_title = 'New Blotter Entry'; $active_page = 'complaints';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="<?= e($back) ?>" data-form-confirm="custom" data-dialog-heading="Leave this blotter entry?" data-dialog-message="The blotter entry has not been saved. Any information you entered will be lost." data-dialog-confirm="Leave Page" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1>New Blotter Entry</h1><p><?= $complaint ? 'Pre-filled from complaint ' . e($complaint['case_number']) . '. Review and edit the information — the original complaint is not changed.' : 'Standalone blotter entry (no related complaint). No complaint is created.' ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== [] || $person_errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted information.<?php if ($person_errors !== []): ?><ul class="mb-0"><?php foreach ($person_errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul><?php endif; ?></div><?php endif; ?>

    <?php if ($link_error === null): ?>
    <form method="post" action="blotter_form.php">
        <?= csrf_field() ?>
        <?php if ($complaint): ?><input type="hidden" name="complaint_id" value="<?= e((string) $complaint['id']) ?>"><p class="case-linked-note">Related complaint: <strong><?= e($complaint['case_number']) ?></strong> · <?= e($complaint['subject']) ?></p><?php endif; ?>
        <fieldset class="resident-section">
            <legend>Incident Information</legend>
            <div class="case-form-grid">
                <div><label class="form-label" for="incident_type">Incident type</label><input class="form-control<?= $field_class('incident_type') ?>" id="incident_type" name="incident_type" value="<?= e($values['incident_type']) ?>" maxlength="80" list="type-options" required data-summary-label="Incident type"><datalist id="type-options"><?php foreach ($types as $type): ?><option value="<?= e($type) ?>"><?php endforeach; ?></datalist><?= $field_error('incident_type') ?></div>
                <div><label class="form-label" for="incident_at">Incident date and time</label><input class="form-control<?= $field_class('incident_at') ?>" type="datetime-local" id="incident_at" name="incident_at" value="<?= e($values['incident_at']) ?>" max="<?= e(date('Y-m-d\TH:i')) ?>" required data-summary-label="Incident date"><?= $field_error('incident_at') ?></div>
                <div class="case-form-wide"><label class="form-label" for="incident_location">Incident location</label><input class="form-control<?= $field_class('incident_location') ?>" id="incident_location" name="incident_location" value="<?= e($values['incident_location']) ?>" maxlength="255" required data-summary-label="Location"><?= $field_error('incident_location') ?></div>
                <div class="case-form-wide"><label class="form-label" for="narrative">Incident narrative <span class="activity-detail-muted">(confidential)</span></label><textarea class="form-control<?= $field_class('narrative') ?>" id="narrative" name="narrative" rows="6" maxlength="10000" required><?= e($values['narrative']) ?></textarea><?= $field_error('narrative') ?></div>
            </div>
        </fieldset>
        <fieldset class="resident-section"><legend>Complainant</legend><?= complaints_person_fields('0', $state['complainant'], 'complainant', true, [], 'Complainant') ?></fieldset>
        <fieldset class="resident-section"><legend>Respondent</legend><?= complaints_person_fields('1', $state['respondent'], 'respondent', true, [], 'Respondent') ?></fieldset>
        <fieldset class="resident-section">
            <legend>Witnesses and Other Persons</legend>
            <div class="case-person-list" data-repeat-list data-repeat-next="<?= e((string) $next_index) ?>">
                <?php foreach ($state['additional'] as $offset => $person): ?>
                    <div class="case-person-row" data-repeat-row><?= complaints_person_fields((string) (2 + $offset), $person, null, true, ['witness', 'complainant', 'respondent', 'other'], 'Additional person') ?><button class="btn btn-sm btn-link case-remove" type="button" data-repeat-remove>Remove this person</button></div>
                <?php endforeach; ?>
                <template data-repeat-template><div class="case-person-row" data-repeat-row><?= complaints_person_fields('__INDEX__', ['role' => 'witness'], null, true, ['witness', 'complainant', 'respondent', 'other'], 'Additional person') ?><button class="btn btn-sm btn-link case-remove" type="button" data-repeat-remove>Remove this person</button></div></template>
            </div>
            <button class="btn btn-light btn-sm case-add" type="button" data-repeat-add>+ Add witness or other person</button>
            <div class="mt-3"><label class="form-label" for="supporting_note">Additional information <span class="activity-detail-muted">(optional, staff only)</span></label><textarea class="form-control" id="supporting_note" name="supporting_note" rows="3" maxlength="2000"><?= e($values['supporting_note']) ?></textarea></div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="Record this blotter entry?" data-dialog-message="Please review the summary. A new, independent blotter entry will be saved as Recorded." data-dialog-confirm="Record Blotter Entry" data-dialog-dismiss="Review Again">Record Blotter Entry</button>
            <a class="btn btn-light" href="<?= e($back) ?>" data-form-confirm="custom" data-dialog-heading="Leave this blotter entry?" data-dialog-message="The blotter entry has not been saved. Any information you entered will be lost." data-dialog-confirm="Leave Page" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
