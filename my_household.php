<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/family_requests.php';
require_auth();
if (!can_access_navigation('my_household')) { http_response_code(403); exit('Access denied.'); }
$connection = db();

// Resident Portal → My Household: the signed-in resident's household and its members, and a form to add a household
// member who has no account of their own (rules in includes/family_requests.php). Additions wait for approval.
$ready = fam_ready($connection);
$link = fam_requester($connection);
$profile = $link['profile'];
$can_add = $ready && $link['state'] === 'verified';
$fields = ['first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'sex', 'civil_status', 'relationship'];
$values = array_fill_keys($fields, '');
$errors = [];
$form_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$checked, $errors] = fam_validate($_POST);
    $values = array_merge($values, array_map(static fn ($v): string => (string) $v, $checked));
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif (!$can_add) $form_error = 'Your account cannot add household members online. Please visit the Barangay Hall.';
    elseif ($errors === []) {
        try {
            flash('household_request_success', fam_create($connection, $profile, $checked));
            redirect('my_household.php');
        } catch (PDOException) { // before RuntimeException: PDOException extends it, and its message must never reach the user
            $form_error = 'The family member could not be added. Please try again.';
        } catch (RuntimeException $exception) {
            $form_error = $exception->getMessage();
        }
    }
}

$membership = $profile ? residents_current_membership($connection, (int) $profile['id']) : null;
$members = $membership ? residents_household_members($connection, (int) $membership['household_id']) : [];
$requests = $ready ? fam_requests_of($connection, (int) current_user()['id']) : [];
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$page_title = 'My Household'; $active_page = 'my_household';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading"><div><h1>My Household</h1><p>Your household and the family members you added.</p></div></div>
    <?php if ($success = flash('household_request_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>

    <section class="dashboard-panel resident-detail-section">
        <h2>Household</h2>
        <?php if ($membership === null): ?>
            <p class="activity-detail-muted">You are not yet assigned to a household. The Barangay Hall will assign your household. Family members you add below will be placed in it.</p>
        <?php else: ?>
            <dl class="activity-detail-list">
                <div><dt>Household number</dt><dd><?= e($membership['household_no']) ?></dd></div>
                <div><dt>Address</dt><dd class="resident-wrap"><?= e($membership['address']) ?> (<?= e(residents_purok_label($membership['purok'])) ?>)</dd></div>
                <div><dt>Household Head</dt><dd><?= $membership['household_head_resident_id'] ? e(residents_full_name($membership)) : '<span class="activity-detail-muted">Not designated</span>' ?></dd></div>
            </dl>
            <h3 class="mt-3">Members</h3>
            <ul class="resident-history">
                <?php foreach ($members as $member): ?>
                    <li><strong><?= e(residents_full_name($member)) ?></strong><span><?= (int) $member['id'] === (int) $membership['household_head_resident_id'] ? 'Household Head' : e($member['relationship_to_head'] ?? 'Relationship not recorded') ?><?= (int) $member['id'] === (int) $profile['id'] ? ' · You' : '' ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="dashboard-panel resident-detail-section" style="margin-top: 24px;">
        <h2>Family members you added</h2>
        <?php if ($requests === []): ?>
            <p class="activity-detail-muted">You have not added anyone yet.</p>
        <?php else: ?>
            <div class="resident-table-wrap">
                <table class="resident-table">
                    <thead><tr><th scope="col">Name</th><th scope="col">Relationship to you</th><th scope="col">Added</th><th scope="col">Status</th><th scope="col">Note from the Barangay Hall</th></tr></thead>
                    <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td class="resident-name"><?= e(residents_full_name($request)) ?></td>
                            <td><?= e($request['relationship_to_requester']) ?></td>
                            <td class="users-nowrap"><?= e(residents_format_date($request['created_at'])) ?></td>
                            <td><?= fam_status_badge($request['status']) ?></td>
                            <td class="resident-wrap"><?= $request['review_notes'] ? e($request['review_notes']) : '<span class="activity-detail-muted">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="resident-cards">
                <?php foreach ($requests as $request): ?>
                    <li class="resident-card"><div class="resident-card-top"><strong><?= e(residents_full_name($request)) ?></strong><?= fam_status_badge($request['status']) ?></div><p><?= e($request['relationship_to_requester']) ?> · Added <?= e(residents_format_date($request['created_at'])) ?></p><?php if ($request['review_notes']): ?><p><?= e($request['review_notes']) ?></p><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="dashboard-panel resident-form-panel" style="margin-top: 24px;" aria-labelledby="add-member-title">
        <h2 id="add-member-title">Add a family member</h2>
        <?php if (!$ready): ?>
            <p class="activity-detail-muted">Adding family members online is not available yet. Please visit the Barangay Hall.</p>
        <?php elseif (!$can_add): ?>
            <p class="activity-detail-muted"><?= $link['state'] === 'inactive' ? 'Your resident profile is not active, so you cannot add family members online.' : 'Your account is not linked to a resident profile yet.' ?> Please visit the Barangay Hall.</p>
        <?php else: ?>
            <p class="resident-static">For household members who do not have their own account, such as your children, or elders without a mobile phone. The Barangay Hall reviews each one before it is added to your household. Members who are 18 or older may also create their own account later.</p>
            <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="resident-grid">
                    <div><label class="form-label" for="first_name">First name <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('first_name') ?>" id="first_name" name="first_name" maxlength="80" value="<?= e($values['first_name']) ?>" required autocomplete="off"><?= $field_error('first_name') ?></div>
                    <div><label class="form-label" for="middle_name">Middle name</label><input class="form-control<?= $field_class('middle_name') ?>" id="middle_name" name="middle_name" maxlength="80" value="<?= e($values['middle_name']) ?>" autocomplete="off"><?= $field_error('middle_name') ?></div>
                    <div><label class="form-label" for="last_name">Last name <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('last_name') ?>" id="last_name" name="last_name" maxlength="80" value="<?= e($values['last_name']) ?>" required autocomplete="off"><?= $field_error('last_name') ?></div>
                    <div><label class="form-label" for="suffix">Suffix</label><input class="form-control<?= $field_class('suffix') ?>" id="suffix" name="suffix" maxlength="20" value="<?= e($values['suffix']) ?>" placeholder="Jr., Sr., III" autocomplete="off"><?= $field_error('suffix') ?></div>
                    <div><label class="form-label" for="birth_date">Birthdate <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('birth_date') ?>" type="date" id="birth_date" name="birth_date" min="1900-01-01" max="<?= e(date('Y-m-d')) ?>" value="<?= e($values['birth_date']) ?>" required><?= $field_error('birth_date') ?></div>
                    <div><label class="form-label" for="sex">Gender <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('sex') ?>" id="sex" name="sex" required><option value="">Select gender</option><?php foreach (['male' => 'Male', 'female' => 'Female'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $values['sex'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('sex') ?></div>
                    <div><label class="form-label" for="civil_status">Civil status</label><select class="form-select<?= $field_class('civil_status') ?>" id="civil_status" name="civil_status"><option value="">Not specified</option><?php foreach (residents_civil_status_labels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $values['civil_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('civil_status') ?></div>
                    <div><label class="form-label" for="relationship">Relationship to you <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('relationship') ?>" id="relationship" name="relationship" required><option value="">Select relationship</option><?php foreach (residents_relationships() as $option): ?><option value="<?= e($option) ?>" <?= $values['relationship'] === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><?= $field_error('relationship') ?></div>
                </div>
                <div class="resident-form-actions mt-3"><button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Add this family member?" data-dialog-message="The Barangay Hall will review the details before this person is added to your household." data-dialog-confirm="Add Family Member" data-dialog-dismiss="Cancel">Add Family Member</button></div>
            </form>
        <?php endif; ?>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
