<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/registrations.php';
reg_require();
$connection = db();

// One online registration: the details the applicant gave, the linked account and resident profile, the decision history
// and, while it is pending, Approve / Reject (registration_action.php).
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$application = reg_find($connection, $id);
if ($application === null) { http_response_code(404); exit('Registration not found.'); }
$history = reg_history($connection, $id);
$pending = in_array($application['status'], reg_pending_statuses(), true);$not_recorded = '<span class="activity-detail-muted">Not recorded</span>';
$age = residents_age($application['birth_date']);
$can_view_resident = $application['resident_id'] !== null && can_access_navigation('residents');
// Household: the applicant's statement, and the choice staff confirm on approval (pre-selected from the statement).
$current_household = $application['resident_id'] !== null ? residents_current_membership($connection, (int) $application['resident_id']) : null;
$households = $pending && $current_household === null ? $connection->query('SELECT id, household_no, purok, address FROM households ORDER BY household_no')->fetchAll() : [];
$claimed_household_id = null;
foreach ($households as $option) if ($application['household_no'] !== null && strcasecmp((string) $option['household_no'], (string) $application['household_no']) === 0) $claimed_household_id = (int) $option['id'];
$default_action = match ($application['household_role']) { 'head' => 'create', 'member' => 'existing', default => 'later' };
$next_number = households_next_number($connection, (string) $application['purok']);
if ($default_action === 'create' && $next_number === null) $default_action = 'later';
$role_labels = ['head' => 'Head of the household', 'member' => 'Member of a household', 'unsure' => 'Not sure'];
$page_title = 'Registration ' . reg_reference($application); $active_page = 'registrations';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($failure = flash('registrations_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
<article class="dashboard-panel resident-detail">
    <div class="page-heading">
        <div>
            <a class="announcement-back" href="registrations.php"><span aria-hidden="true">&larr;</span> Back</a>
            <span class="eyebrow">Online registration <?= e(reg_reference($application)) ?></span>
            <h1><?= e(residents_full_name($application)) ?></h1>
            <p><?= reg_status_badge($application['status']) ?> <span class="resident-detail-meta">Submitted <?= e(reg_format_datetime($application['submitted_at'])) ?></span></p>
        </div>
        <?php if ($can_view_resident): ?><div class="resident-detail-actions"><a class="btn btn-light resident-action-btn" href="resident_view.php?id=<?= e((string) $application['resident_id']) ?>">Open Resident Profile</a></div><?php endif; ?>
    </div>

    <div class="resident-detail-grid">
        <section class="resident-detail-section">
            <h2>Personal Information</h2>
            <dl class="activity-detail-list">
                <div><dt>Full name</dt><dd><?= e(residents_full_name($application)) ?></dd></div>
                <div><dt>Birthdate</dt><dd><?= $application['birth_date'] ? e(residents_format_date($application['birth_date'])) : $not_recorded ?></dd></div>
                <div><dt>Age</dt><dd><?= $age === null ? $not_recorded : e((string) $age) ?></dd></div>
                <div><dt>Gender</dt><dd><?= e(residents_sex_labels()[$application['sex']] ?? 'Unspecified') ?></dd></div>
                <div><dt>Civil status</dt><dd><?= e(residents_civil_status_labels()[$application['civil_status']] ?? 'Unspecified') ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Contact and Address</h2>
            <dl class="activity-detail-list">
                <div><dt>Mobile number</dt><dd><?= e((string) $application['contact_number']) ?> <span class="resident-tag">Verified by SMS</span></dd></div>
                <div><dt>Address</dt><dd class="resident-wrap"><?= e((string) $application['address']) ?></dd></div>
                <div><dt>Purok</dt><dd><?= e(residents_purok_label($application['purok'])) ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Household (as stated by the applicant)</h2>
            <dl class="activity-detail-list">
                <div><dt>Stated role</dt><dd><?= $application['household_role'] ? e($role_labels[$application['household_role']] ?? $application['household_role']) : $not_recorded ?></dd></div>
                <?php if ($application['household_role'] === 'member'): ?>
                    <div><dt>Household head</dt><dd><?= e((string) $application['household_head_name']) ?></dd></div>
                    <div><dt>Relationship to head</dt><dd><?= e((string) $application['household_relationship']) ?></dd></div>
                    <div><dt>Household number</dt><dd><?= $application['household_no'] ? e($application['household_no']) . ($pending && $current_household === null ? ($claimed_household_id ? ' <span class="resident-tag">Found</span>' : ' <span class="resident-tag">Not found</span>') : '') : $not_recorded ?></dd></div>
                <?php endif; ?>
                <div><dt>Current household</dt><dd><?= $current_household ? e($current_household['household_no']) : '<span class="activity-detail-muted">None</span>' ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Account and Resident Profile</h2>
            <dl class="activity-detail-list">
                <div><dt>Signs in with</dt><dd><?= reg_login_label($application) !== '' ? e(reg_login_label($application)) : $not_recorded ?></dd></div>
                <div><dt>Account status</dt><dd><?= $application['user_status'] ? e(ucfirst($application['user_status'])) : '<span class="activity-detail-muted">No account</span>' ?></dd></div>
                <div><dt>Resident profile</dt><dd><?php if ($application['resident_id'] === null): ?><span class="activity-detail-muted">None</span><?php else: ?><?= reg_created_profile($application) ? 'New profile from this sign-up' : 'Existing profile (same name and birthdate; not changed by the sign-up)' ?> · <?= residents_status_badge((string) $application['resident_status']) ?><?php endif; ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Decision</h2>
            <?php if ($history === []): ?>
                <p class="activity-detail-muted">Waiting for review.</p>
            <?php else: ?>
                <dl class="activity-detail-list">
                    <?php foreach ($history as $entry): ?>
                        <div><dt><?= e(ucfirst($entry['action'])) ?></dt><dd><?= e(reg_format_datetime($entry['created_at'])) ?> · <?= e($entry['actor_name'] ?? 'Unknown user') ?><?= $entry['notes'] ? '<br><span class="resident-wrap">' . e($entry['notes']) . '</span>' : '' ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($pending): ?>
        <section class="resident-detail-section">
            <h2>Review</h2>
            <p class="resident-detail-meta">Check that the applicant lives in <?= e(BARANGAY_NAME) ?>, for example against barangay records or with the Purok leader.<?= reg_created_profile($application) ? '' : ' This sign-up matched an existing resident profile; make sure it is the same person.' ?></p>
            <div class="resident-detail-grid">
                <form method="post" action="registration_action.php">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $application['id']) ?>"><input type="hidden" name="decision" value="approve">
                    <?php if ($current_household === null && $application['resident_id'] !== null): ?>
                        <fieldset class="mb-3" data-household-choice>
                            <legend class="form-label">Household</legend>
                            <?php if ($next_number !== null): ?><div class="form-check"><input class="form-check-input" type="radio" name="household_action" id="hh-create" value="create" <?= $default_action === 'create' ? 'checked' : '' ?>><label class="form-check-label" for="hh-create">Create household <strong><?= e($next_number) ?></strong> with this resident as household head</label></div><?php endif; ?>
                            <div class="form-check"><input class="form-check-input" type="radio" name="household_action" id="hh-existing" value="existing" <?= $default_action === 'existing' ? 'checked' : '' ?> <?= $households === [] ? 'disabled' : '' ?>><label class="form-check-label" for="hh-existing">Add to an existing household<?= $households === [] ? ' (no households yet)' : '' ?></label></div>
                            <?php if ($households !== []): ?>
                                <div class="resident-grid mt-2 mb-2" data-household-existing<?= $default_action === 'existing' ? '' : ' hidden' ?>>
                                    <div><label class="form-label" for="hh-id">Household</label><select class="form-select" id="hh-id" name="household_id"><option value="">Select household</option><?php foreach ($households as $option): ?><option value="<?= e((string) $option['id']) ?>" <?= $claimed_household_id === (int) $option['id'] ? 'selected' : '' ?>><?= e($option['household_no'] . ' · ' . residents_purok_label($option['purok'])) ?></option><?php endforeach; ?></select></div>
                                    <div><label class="form-label" for="hh-rel">Relationship to head</label><select class="form-select" id="hh-rel" name="household_relationship"><option value="">Not specified</option><?php foreach (residents_relationships() as $relationship): ?><option value="<?= e($relationship) ?>" <?= $application['household_relationship'] === $relationship ? 'selected' : '' ?>><?= e($relationship) ?></option><?php endforeach; ?></select></div>
                                </div>
                            <?php endif; ?>
                            <div class="form-check"><input class="form-check-input" type="radio" name="household_action" id="hh-later" value="later" <?= $default_action === 'later' ? 'checked' : '' ?>><label class="form-check-label" for="hh-later">Assign later</label></div>
                        </fieldset>
                    <?php endif; ?>
                    <label class="form-label" for="approve-notes">Notes <span class="activity-detail-muted">(optional)</span></label>
                    <textarea class="form-control" id="approve-notes" name="notes" rows="2" maxlength="500"></textarea>
                    <button class="btn btn-primary mt-2" type="submit" data-form-confirm="custom" data-dialog-heading="Approve this registration?" data-dialog-message="<?= e(residents_full_name($application) . ' will be able to sign in to the Resident Portal, and the resident profile becomes Active.') ?>" data-dialog-confirm="Approve" data-dialog-dismiss="Cancel">Approve</button>
                </form>
                <form method="post" action="registration_action.php">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $application['id']) ?>"><input type="hidden" name="decision" value="reject">
                    <label class="form-label" for="reject-notes">Reason for rejecting <span class="resident-required" aria-hidden="true">*</span></label>
                    <textarea class="form-control" id="reject-notes" name="notes" rows="2" minlength="10" maxlength="500" required></textarea>
                    <button class="btn btn-outline-danger mt-2" type="submit" data-form-confirm="custom" data-dialog-heading="Reject this registration?" data-dialog-message="The account will be suspended and cannot sign in. Nothing is deleted." data-dialog-confirm="Reject" data-dialog-dismiss="Cancel" data-dialog-danger="true">Reject</button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</article>
<?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
<script>
// Household and Relationship apply only to "Add to an existing household"; they are hidden for the other choices.
document.querySelectorAll('[data-household-choice]').forEach((group) => {
    const existing = group.querySelector('[data-household-existing]');
    if (!existing) return;
    const sync = () => { existing.hidden = !group.querySelector('input[name="household_action"][value="existing"]:checked'); };
    group.querySelectorAll('input[name="household_action"]').forEach((radio) => radio.addEventListener('change', sync));
    sync();
});
</script>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
