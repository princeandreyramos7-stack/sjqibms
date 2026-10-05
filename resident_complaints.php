<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/complaints.php';
require_auth();
if (!complaints_can_submit_online()) { http_response_code(403); exit('Access denied.'); }
$connection = db();
complaints_require_schema($connection);

// Online resident complaints. The complainant is ALWAYS the signed-in account's verified resident profile (never a
// browser-supplied ID). Ambiguous or unlinked accounts cannot submit. Residents see only their own complaints.
$link = complaints_resident_profile($connection);
$profile = $link['profile'];
$can_submit = $link['state'] === 'verified';
$values = ['category' => '', 'subject' => '', 'description' => '', 'incident_at' => '', 'incident_location' => '', 'supporting_note' => ''];
$state = ['respondent' => ['role' => 'respondent'], 'additional' => []];
$errors = [];
$person_errors = [];
$form_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = complaints_validate_details($_POST);
    $values = $validated['values'];
    $values['supporting_note'] = ''; // staff-only field; never accepted from residents
    $errors = $validated['errors'];
    // Residents enter respondents/witnesses by name only (no resident-registry links); the complainant comes from the profile.
    $collected = complaints_collect_persons($connection, (array) ($_POST['persons'] ?? []), ['respondent', 'witness', 'other'], false);
    $person_errors = $collected['errors'];
    $posted_state = complaints_person_form_state($collected['persons']);
    $state = ['respondent' => $posted_state['respondent'] ?? ['role' => 'respondent'], 'additional' => $posted_state['additional']];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif (!$can_submit) $form_error = 'Your account cannot submit complaints online. Please visit the Barangay Hall.';
    if ($can_submit) {
        $complainant = ['role' => 'complainant', 'resident_id' => (int) $profile['id'], 'full_name' => residents_full_name($profile), 'contact_number' => $profile['contact_number'] ?: null, 'address' => residents_collapse((string) $profile['address']) ?: null, 'identifying_details' => null];
        $persons = array_merge([$complainant], $collected['persons']);
        complaints_require_parties($persons, $person_errors);
        if ($form_error === null && $errors === [] && $person_errors === [] && complaints_is_duplicate_submission('complaint_online', [$values, $persons])) $form_error = 'This complaint was just submitted. Check "My Complaints" before submitting it again.';
        if ($form_error === null && $errors === [] && $person_errors === []) {
            try {
                $created = complaints_create($connection, $values, $persons, 'online');
                complaints_remember_submission('complaint_online', [$values, $persons]);
                flash('case_success', 'Complaint ' . $created['reference'] . ' submitted. It is confidential and will be reviewed by the Barangay Secretary.');
                redirect('resident_complaints.php');
            } catch (PDOException) { // before RuntimeException: PDOException extends it, and its message must never reach the user
                $form_error = 'Your complaint could not be submitted. Please try again.';
            } catch (RuntimeException $exception) {
                $form_error = $exception->getMessage();
            }
        }
    }
}

$mine = [];
$statement = $connection->prepare("SELECT c.id, c.case_number, c.subject, c.category, c.status, c.filed_at FROM complaint_cases c WHERE c.submitted_by_user_id = :user OR (:resident_a IS NOT NULL AND c.id IN (SELECT p.complaint_id FROM case_persons p WHERE p.person_role = 'complainant' AND p.resident_id = :resident_b)) ORDER BY c.filed_at DESC, c.id DESC LIMIT 50");
$resident_id = in_array($link['state'], ['verified', 'inactive'], true) ? (int) $profile['id'] : null;
$statement->bindValue('user', (int) current_user()['id'], PDO::PARAM_INT);
$statement->bindValue('resident_a', $resident_id, $resident_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
$statement->bindValue('resident_b', $resident_id, $resident_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
$statement->execute();
$mine = $statement->fetchAll();
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$next_index = 2 + count($state['additional']);
$blocked_message = match ($link['state']) {
    'unlinked' => 'Your account is not linked to a resident profile yet. Please visit the Barangay Hall to file a complaint or to link your account.',
    'ambiguous' => 'Your account link could not be verified. Please visit the Barangay Hall to file a complaint.',
    'inactive' => 'Only active resident profiles can file complaints online. Please visit the Barangay Hall.',
    default => null,
};
$page_title = 'My Complaints'; $active_page = 'my_complaints';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading"><div><h1>My Complaints</h1><p>File a confidential complaint and follow its status online.</p></div></div>
    <?php if ($success = flash('case_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>

    <div class="resident-portal-grid">
        <section class="dashboard-panel resident-form-panel" aria-labelledby="new-complaint-heading">
            <h2 class="resident-portal-title" id="new-complaint-heading">New Complaint</h2>
            <?php if ($blocked_message !== null): ?>
                <p class="resident-pending"><?= e($blocked_message) ?></p>
            <?php else: ?>
                <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== [] || $person_errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted information.<?php if ($person_errors !== []): ?><ul class="mb-0"><?php foreach ($person_errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul><?php endif; ?></div><?php endif; ?>
                <form method="post" action="resident_complaints.php">
                    <?= csrf_field() ?>
                    <p class="resident-static">Filed by: <strong><?= e(residents_full_name($profile)) ?></strong> · <?= e($profile['purok']) ?>. Your complaint is <strong>confidential</strong>. Evidence can be handed to the Barangay Secretary at the Barangay Hall.</p>
                    <fieldset class="resident-section"><legend>Respondent</legend><?= complaints_person_fields('1', $state['respondent'], 'respondent', false, [], 'Respondent') ?></fieldset>
                    <fieldset class="resident-section">
                        <legend>Complaint Details</legend>
                        <div class="case-form-grid">
                            <div><label class="form-label" for="category">Category</label><input class="form-control<?= $field_class('category') ?>" id="category" name="category" value="<?= e($values['category']) ?>" maxlength="80" placeholder="e.g. Noise, Property, Neighbor dispute" required data-summary-label="Category"><?= $field_error('category') ?></div>
                            <div><label class="form-label" for="incident_at">Incident date and time</label><input class="form-control<?= $field_class('incident_at') ?>" type="datetime-local" id="incident_at" name="incident_at" value="<?= e($values['incident_at']) ?>" max="<?= e(date('Y-m-d\TH:i')) ?>" required data-summary-label="Incident date"><?= $field_error('incident_at') ?></div>
                            <div class="case-form-wide"><label class="form-label" for="subject">Subject</label><input class="form-control<?= $field_class('subject') ?>" id="subject" name="subject" value="<?= e($values['subject']) ?>" maxlength="255" required data-summary-label="Subject"><?= $field_error('subject') ?></div>
                            <div class="case-form-wide"><label class="form-label" for="incident_location">Incident location</label><input class="form-control<?= $field_class('incident_location') ?>" id="incident_location" name="incident_location" value="<?= e($values['incident_location']) ?>" maxlength="255" required data-summary-label="Location"><?= $field_error('incident_location') ?></div>
                            <div class="case-form-wide"><label class="form-label" for="description">What happened?</label><textarea class="form-control<?= $field_class('description') ?>" id="description" name="description" rows="5" maxlength="5000" required><?= e($values['description']) ?></textarea><?= $field_error('description') ?></div>
                        </div>
                    </fieldset>
                    <fieldset class="resident-section">
                        <legend>Witnesses <span class="activity-detail-muted">(optional)</span></legend>
                        <div class="case-person-list" data-repeat-list data-repeat-next="<?= e((string) $next_index) ?>">
                            <?php foreach ($state['additional'] as $offset => $person): ?>
                                <div class="case-person-row" data-repeat-row><?= complaints_person_fields((string) (2 + $offset), $person, null, false, ['witness', 'respondent', 'other'], 'Additional person') ?><button class="btn btn-sm btn-link case-remove" type="button" data-repeat-remove>Remove this person</button></div>
                            <?php endforeach; ?>
                            <template data-repeat-template><div class="case-person-row" data-repeat-row><?= complaints_person_fields('__INDEX__', ['role' => 'witness'], null, false, ['witness', 'respondent', 'other'], 'Additional person') ?><button class="btn btn-sm btn-link case-remove" type="button" data-repeat-remove>Remove this person</button></div></template>
                        </div>
                        <button class="btn btn-light btn-sm case-add" type="button" data-repeat-add>+ Add witness</button>
                    </fieldset>
                    <div class="form-actions"><button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="Submit this complaint?" data-dialog-message="Please review the summary. Your complaint will be sent confidentially to the Barangay Secretary." data-dialog-confirm="Submit Complaint" data-dialog-dismiss="Review Again">Submit Complaint</button></div>
                </form>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel resident-form-panel" aria-labelledby="my-complaints-heading">
            <h2 class="resident-portal-title" id="my-complaints-heading">My Complaints</h2>
            <?php if ($mine === []): ?><div class="dashboard-empty-state">You have not filed any complaints.</div><?php else: ?>
                <ul class="document-request-list">
                    <?php foreach ($mine as $complaint): ?>
                        <li>
                            <div class="document-request-top"><strong><?= e($complaint['subject']) ?></strong><?= complaints_badge('complaint', $complaint['status']) ?></div>
                            <span class="document-request-ref"><a class="activity-detail-link" href="complaint_view.php?id=<?= e((string) $complaint['id']) ?>"><?= e($complaint['case_number']) ?></a> · Filed <?= e(complaints_format_datetime($complaint['filed_at'])) ?><?= $complaint['category'] ? ' · ' . e($complaint['category']) : '' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
