<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/complaints.php';
complaints_require_manage();
$connection = db();
complaints_require_schema($connection);

// Staff-assisted complaint (recorded at the Barangay Hall). Same centralized recording as online complaints.
$values = ['category' => '', 'subject' => '', 'description' => '', 'incident_at' => '', 'incident_location' => '', 'supporting_note' => ''];
$state = ['complainant' => ['role' => 'complainant'], 'respondent' => ['role' => 'respondent'], 'additional' => []];
$errors = [];
$person_errors = [];
$form_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = complaints_validate_details($_POST);
    $values = $validated['values'];
    $errors = $validated['errors'];
    $collected = complaints_collect_persons($connection, (array) ($_POST['persons'] ?? []), ['complainant', 'respondent', 'witness', 'other']);
    $person_errors = $collected['errors'];
    complaints_require_parties($collected['persons'], $person_errors);
    $state = complaints_person_form_state($collected['persons']);
    $state['complainant'] ??= ['role' => 'complainant'];
    $state['respondent'] ??= ['role' => 'respondent'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($errors === [] && $person_errors === [] && complaints_is_duplicate_submission('complaint_staff', [$values, $collected['persons']])) $form_error = 'This complaint was just submitted. Check the Complaints list before submitting it again.';
    if ($form_error === null && $errors === [] && $person_errors === []) {
        try {
            $created = complaints_create($connection, $values, $collected['persons'], 'staff');
            complaints_remember_submission('complaint_staff', [$values, $collected['persons']]);
            flash('case_success', 'Complaint ' . $created['reference'] . ' recorded as Pending Review.');
            redirect('complaint_view.php?id=' . $created['id']);
        } catch (PDOException) { // before RuntimeException: PDOException extends it, and its message must never reach the user
            $form_error = 'The complaint could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            $form_error = $exception->getMessage();
        }
    }
}
$categories = complaints_distinct_values($connection, 'category');
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$next_index = 2 + count($state['additional']);
$page_title = 'New Complaint'; $active_page = 'complaints';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="complaints.php" data-form-confirm="custom" data-dialog-heading="Leave this complaint?" data-dialog-message="The complaint has not been saved. Any information you entered will be lost." data-dialog-confirm="Leave Page" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1>New Complaint</h1><p>Record a complaint received at the Barangay Hall. Every complaint is confidential and starts as Pending Review.</p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== [] || $person_errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted information.<?php if ($person_errors !== []): ?><ul class="mb-0"><?php foreach ($person_errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul><?php endif; ?></div><?php endif; ?>

    <form method="post" action="complaint_form.php">
        <?= csrf_field() ?>
        <fieldset class="resident-section">
            <legend>A. Complainant Information</legend>
            <?= complaints_person_fields('0', $state['complainant'], 'complainant', true, [], 'Complainant') ?>
        </fieldset>
        <fieldset class="resident-section">
            <legend>B. Respondent Information</legend>
            <?= complaints_person_fields('1', $state['respondent'], 'respondent', true, [], 'Respondent') ?>
        </fieldset>
        <fieldset class="resident-section">
            <legend>C. Complaint Details</legend>
            <div class="case-form-grid">
                <div><label class="form-label" for="category">Category</label><input class="form-control<?= $field_class('category') ?>" id="category" name="category" value="<?= e($values['category']) ?>" maxlength="80" list="category-options" required data-summary-label="Category"><datalist id="category-options"><?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"><?php endforeach; ?></datalist><?= $field_error('category') ?></div>
                <div><label class="form-label" for="incident_at">Incident date and time</label><input class="form-control<?= $field_class('incident_at') ?>" type="datetime-local" id="incident_at" name="incident_at" value="<?= e($values['incident_at']) ?>" max="<?= e(date('Y-m-d\TH:i')) ?>" required data-summary-label="Incident date"><?= $field_error('incident_at') ?></div>
                <div class="case-form-wide"><label class="form-label" for="subject">Subject</label><input class="form-control<?= $field_class('subject') ?>" id="subject" name="subject" value="<?= e($values['subject']) ?>" maxlength="255" required data-summary-label="Subject"><?= $field_error('subject') ?></div>
                <div class="case-form-wide"><label class="form-label" for="incident_location">Incident location</label><input class="form-control<?= $field_class('incident_location') ?>" id="incident_location" name="incident_location" value="<?= e($values['incident_location']) ?>" maxlength="255" required data-summary-label="Location"><?= $field_error('incident_location') ?></div>
                <div class="case-form-wide"><label class="form-label" for="description">Complaint description <span class="activity-detail-muted">(confidential)</span></label><textarea class="form-control<?= $field_class('description') ?>" id="description" name="description" rows="5" maxlength="5000" required><?= e($values['description']) ?></textarea><?= $field_error('description') ?></div>
            </div>
        </fieldset>
        <fieldset class="resident-section">
            <legend>D. Witnesses and Supporting Information</legend>
            <div class="case-person-list" data-repeat-list data-repeat-next="<?= e((string) $next_index) ?>">
                <?php foreach ($state['additional'] as $offset => $person): ?>
                    <div class="case-person-row" data-repeat-row><?= complaints_person_fields((string) (2 + $offset), $person, null, true, ['witness', 'complainant', 'respondent', 'other'], 'Additional person') ?><button class="btn btn-sm btn-link case-remove" type="button" data-repeat-remove>Remove this person</button></div>
                <?php endforeach; ?>
                <template data-repeat-template><div class="case-person-row" data-repeat-row><?= complaints_person_fields('__INDEX__', ['role' => 'witness'], null, true, ['witness', 'complainant', 'respondent', 'other'], 'Additional person') ?><button class="btn btn-sm btn-link case-remove" type="button" data-repeat-remove>Remove this person</button></div></template>
            </div>
            <button class="btn btn-light btn-sm case-add" type="button" data-repeat-add>+ Add witness or other person</button>
            <div class="mt-3"><label class="form-label" for="supporting_note">Supporting notes <span class="activity-detail-muted">(optional, staff only)</span></label><textarea class="form-control" id="supporting_note" name="supporting_note" rows="3" maxlength="2000"><?= e($values['supporting_note']) ?></textarea></div>
            <p class="resident-static">Evidence files can be attached from the complaint's details page after it is saved.</p>
        </fieldset>
        <fieldset class="resident-section">
            <legend>E. Review and Submission</legend>
            <p class="resident-static">Submitting shows a summary for review. The complaint is recorded as <strong>Confidential</strong> and <strong>Pending Review</strong>.</p>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="Record this complaint?" data-dialog-message="Please review the summary. The complaint will be saved as confidential and Pending Review." data-dialog-confirm="Record Complaint" data-dialog-dismiss="Review Again">Record Complaint</button>
                <a class="btn btn-light" href="complaints.php" data-form-confirm="custom" data-dialog-heading="Leave this complaint?" data-dialog-message="The complaint has not been saved. Any information you entered will be lost." data-dialog-confirm="Leave Page" data-dialog-danger="true">Cancel</a>
            </div>
        </fieldset>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
