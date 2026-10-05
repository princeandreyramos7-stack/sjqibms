<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/hearings.php';
complaints_require_manage();
$connection = db();
complaints_require_schema($connection);

// Schedule Hearing: A. case → B. schedule → C. participants → D. personnel → E. review. The final conflict check runs
// on the server inside the saving transaction (hearings_create), after locking the venue and personnel rows.
$case_key = hearings_parse_case_key((string) ($_POST['case'] ?? $_GET['case'] ?? ''));
$case = $case_key ? hearings_eligible_case($connection, $case_key['kind'], $case_key['id']) : null;
if ($case_key && !$case) { http_response_code(404); exit('Case not found.'); }
$case_error = $case && !$case['eligible'] ? 'Hearings can be scheduled only for complaints Under Review or blotter entries that are Active. ' . $case['reference'] . ' is not eligible.' : null;
$persons = $case ? complaints_persons($connection, $case['kind'], (int) $case['id']) : [];
$venues = hearings_active_venues($connection);
$personnel = hearings_active_personnel($connection);
$values = ['hearing_type' => '', 'date' => '', 'start_time' => '', 'end_time' => '', 'venue_id' => '', 'notes' => '', 'extra' => ''];
$selected_persons = array_map(static fn (array $p): int => (int) $p['id'], array_filter($persons, static fn (array $p): bool => in_array($p['person_role'], ['complainant', 'respondent'], true)));
$selected_personnel = [];
$personnel_roles = [];
$errors = [];
$form_error = $case_error;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $case && $case_error === null) {
    $values = [
        'hearing_type' => residents_collapse((string) ($_POST['hearing_type'] ?? '')),
        'date' => (string) ($_POST['date'] ?? ''), 'start_time' => (string) ($_POST['start_time'] ?? ''), 'end_time' => (string) ($_POST['end_time'] ?? ''),
        'venue_id' => (string) ($_POST['venue_id'] ?? ''), 'notes' => complaints_text($_POST['notes'] ?? '', 2000), 'extra' => complaints_text($_POST['extra'] ?? '', 2000),
    ];
    $selected_persons = array_map('intval', (array) ($_POST['participants'] ?? []));
    $selected_personnel = array_map('intval', (array) ($_POST['personnel'] ?? []));
    $personnel_roles = array_map(static fn ($v): string => residents_collapse((string) $v), (array) ($_POST['personnel_role'] ?? []));
    if (mb_strlen($values['hearing_type']) < 2 || mb_strlen($values['hearing_type']) > 80) $errors['hearing_type'] = 'Enter a hearing type of 2 to 80 characters.';
    $schedule = hearings_validate_schedule($values['date'], $values['start_time'], $values['end_time']);
    $errors += $schedule['errors'];
    $venue_id = filter_var($values['venue_id'], FILTER_VALIDATE_INT) ?: 0;
    if (!in_array($venue_id, array_map('intval', array_column($venues, 'id')), true)) $errors['venue_id'] = 'Select an active venue.';
    $extra = [];
    foreach (preg_split('/\n+/', $values['extra']) ?: [] as $line) {
        $name = residents_collapse($line);
        if ($name === '') continue;
        if (mb_strlen($name) < 2 || mb_strlen($name) > 180) { $errors['extra'] = 'Each additional participant name must be 2 to 180 characters.'; break; }
        $extra[] = ['name' => $name, 'role' => 'other'];
    }
    if (count($extra) > 20) $errors['extra'] = 'Add at most 20 additional participants.';
    if ($selected_persons === [] && $extra === []) $errors['participants'] = 'Select at least one participant.';
    $assignments = [];
    foreach ($selected_personnel as $person_id) {
        $role = $personnel_roles[$person_id] ?? '';
        if (mb_strlen($role) < 2 || mb_strlen($role) > 80) { $errors['personnel'] = 'Enter an assignment role (2 to 80 characters) for each selected person.'; break; }
        $assignments[$person_id] = $role;
    }
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($errors === [] && complaints_is_duplicate_submission('hearing', [$case_key, $values, $selected_persons, $assignments])) $form_error = 'This hearing was just scheduled. Check the Hearing Schedule before saving it again.';
    if ($form_error === null && $errors === []) {
        try {
            $created = hearings_create($connection, $case_key, ['hearing_type' => $values['hearing_type'], 'start' => $schedule['start'], 'end' => $schedule['end'], 'venue_id' => $venue_id, 'notes' => $values['notes']], $selected_persons, $extra, $assignments);
            complaints_remember_submission('hearing', [$case_key, $values, $selected_persons, $assignments]);
            flash('case_success', 'Hearing ' . $created['reference'] . ' scheduled.');
            redirect('hearing_view.php?id=' . $created['id']);
        } catch (PDOException) { // before RuntimeException: PDOException extends it, and its message must never reach the user
            $form_error = 'The hearing could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            $form_error = $exception->getMessage();
        }
    }
}
$eligible = $case ? [] : hearings_eligible_cases($connection);
$hearing_types = complaints_distinct_values($connection, 'hearing_type');
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
$back = $case ? ($case['kind'] === 'blotter' ? 'blotter_view.php' : 'complaint_view.php') . '?id=' . $case['id'] : 'complaints.php?tab=hearings';
$page_title = 'Schedule Hearing'; $active_page = 'complaints';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="<?= e($back) ?>"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1>Schedule Hearing</h1><p>Hearings are scheduled only for complaints Under Review or Active blotter entries, following the barangay's procedures.</p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted information.</div><?php endif; ?>

    <fieldset class="resident-section">
        <legend>A. Case Information</legend>
        <?php if ($case): ?>
            <dl class="activity-detail-list case-compact-list">
                <div><dt>Case</dt><dd><?= e($case['kind'] === 'blotter' ? 'Blotter entry' : 'Complaint') ?> <strong><?= e($case['reference']) ?></strong></dd></div>
                <div><dt>Status</dt><dd><?= complaints_badge($case['kind'], $case['status']) ?></dd></div>
                <div><dt><?= $case['kind'] === 'blotter' ? 'Incident type' : 'Subject' ?></dt><dd><?= e($case['title']) ?></dd></div>
            </dl>
        <?php elseif ($eligible === []): ?>
            <p class="resident-static">No cases are currently eligible. Start the review of a complaint or start processing a blotter entry first.</p>
        <?php else: ?>
            <form method="get" action="hearing_form.php" class="case-select-form">
                <label class="form-label" for="case-select">Related complaint or blotter entry</label>
                <select class="form-select" id="case-select" name="case" data-auto-submit required><option value="">Select a case…</option><?php foreach ($eligible as $option): ?><option value="<?= e($option['case_key']) ?>"><?= e($option['kind_label'] . ' ' . $option['reference'] . ' — ' . $option['title']) ?></option><?php endforeach; ?></select>
                <noscript><button class="btn btn-primary btn-sm mt-2" type="submit">Continue</button></noscript>
            </form>
        <?php endif; ?>
    </fieldset>

    <?php if ($case && $case_error === null): ?>
    <?php if ($venues === []): ?>
        <div class="dashboard-status warning">No active hearing venues are recorded yet. <a class="activity-detail-link" href="barangay_personnel.php?tab=venues">Add a venue</a> before scheduling.</div>
    <?php else: ?>
    <form method="post" action="hearing_form.php">
        <?= csrf_field() ?><input type="hidden" name="case" value="<?= e($case['kind'] . ':' . $case['id']) ?>">
        <fieldset class="resident-section">
            <legend>B. Schedule Information</legend>
            <div class="case-form-grid">
                <div><label class="form-label" for="hearing_type">Hearing type</label><input class="form-control<?= $field_class('hearing_type') ?>" id="hearing_type" name="hearing_type" value="<?= e($values['hearing_type']) ?>" maxlength="80" list="hearing-type-options" required data-summary-label="Hearing type"><datalist id="hearing-type-options"><?php foreach ($hearing_types as $type): ?><option value="<?= e($type) ?>"><?php endforeach; ?></datalist><?= $field_error('hearing_type') ?></div>
                <div><label class="form-label" for="venue_id">Venue</label><select class="form-select<?= $field_class('venue_id') ?>" id="venue_id" name="venue_id" required data-summary-label="Venue"><option value="">Select venue…</option><?php foreach ($venues as $venue): ?><option value="<?= e((string) $venue['id']) ?>" <?= $values['venue_id'] === (string) $venue['id'] ? 'selected' : '' ?>><?= e($venue['name']) ?></option><?php endforeach; ?></select><?= $field_error('venue_id') ?></div>
                <div><label class="form-label" for="date">Hearing date</label><input class="form-control<?= $field_class('schedule') ?>" type="date" id="date" name="date" value="<?= e($values['date']) ?>" min="<?= e(date('Y-m-d')) ?>" required data-summary-label="Date"></div>
                <div class="case-time-pair"><div><label class="form-label" for="start_time">Start time</label><input class="form-control<?= $field_class('schedule') ?>" type="time" id="start_time" name="start_time" value="<?= e($values['start_time']) ?>" required data-summary-label="Start"></div><div><label class="form-label" for="end_time">End time</label><input class="form-control<?= $field_class('schedule') ?>" type="time" id="end_time" name="end_time" value="<?= e($values['end_time']) ?>" required data-summary-label="End"></div></div>
                <div class="case-form-wide"><?= $field_error('schedule') ?></div>
                <div class="case-form-wide"><label class="form-label" for="notes">Scheduling notes <span class="activity-detail-muted">(optional)</span></label><textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000"><?= e($values['notes']) ?></textarea></div>
            </div>
        </fieldset>
        <fieldset class="resident-section">
            <legend>C. Participants</legend>
            <?php if ($persons === []): ?><p class="resident-static">No persons are recorded on this case.</p><?php else: ?>
                <ul class="case-check-list"><?php foreach ($persons as $person): ?><li><label><input class="form-check-input" type="checkbox" name="participants[]" value="<?= e((string) $person['id']) ?>" <?= in_array((int) $person['id'], $selected_persons, true) ? 'checked' : '' ?>> <strong><?= e($person['full_name']) ?></strong> <span class="case-role-tag"><?= e(case_person_role_labels()[$person['person_role']]) ?></span></label></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?= $field_error('participants') ?>
            <label class="form-label mt-2" for="extra">Other authorized participants <span class="activity-detail-muted">(optional, one name per line)</span></label>
            <textarea class="form-control<?= $field_class('extra') ?>" id="extra" name="extra" rows="2" maxlength="2000"><?= e($values['extra']) ?></textarea><?= $field_error('extra') ?>
        </fieldset>
        <fieldset class="resident-section">
            <legend>D. Assigned Personnel</legend>
            <p class="resident-static">Assignment is an administrative record only. It does not give the person access to confidential case records.</p>
            <?php if ($personnel === []): ?><p class="resident-static">No active barangay personnel are recorded. <a class="activity-detail-link" href="barangay_personnel.php">Add personnel</a>.</p><?php else: ?>
                <ul class="case-check-list case-personnel-list"><?php foreach ($personnel as $person): $pid = (int) $person['id']; ?><li><label><input class="form-check-input" type="checkbox" name="personnel[]" value="<?= e((string) $pid) ?>" <?= in_array($pid, $selected_personnel, true) ? 'checked' : '' ?>> <strong><?= e($person['full_name']) ?></strong> <span class="activity-detail-muted"><?= e($person['position']) ?></span></label><input class="form-control form-control-sm" name="personnel_role[<?= e((string) $pid) ?>]" value="<?= e($personnel_roles[$pid] ?? $person['position']) ?>" maxlength="80" aria-label="Assignment role for <?= e($person['full_name']) ?>"></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?= $field_error('personnel') ?>
        </fieldset>
        <fieldset class="resident-section">
            <legend>E. Review and Confirmation</legend>
            <p class="resident-static">The server checks for overlapping hearings in the same venue or with the same personnel before saving.</p>
            <div class="form-actions"><button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="Schedule this hearing?" data-dialog-message="Please review the schedule. Participants with verified accounts will receive a private notification." data-dialog-confirm="Schedule Hearing" data-dialog-dismiss="Review Again">Schedule Hearing</button><a class="btn btn-light" href="<?= e($back) ?>">Cancel</a></div>
        </fieldset>
    </form>
    <?php endif; ?>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
