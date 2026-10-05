<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/residents.php';
require_once __DIR__ . '/includes/accounts.php';
residents_require_manage();
$connection = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$record = $id ? residents_find($connection, $id) : null;
if ($id && !$record) { http_response_code(404); exit('Resident not found.'); }
$is_edit = $record !== null;
$identity_fields = ['first_name', 'middle_name', 'last_name', 'suffix', 'birth_date'];
$profile_fields = ['first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'sex', 'civil_status', 'contact_number', 'address', 'purok'];
$values = $record ? array_intersect_key($record, array_flip($profile_fields)) : array_fill_keys($profile_fields, null);
$status = $record['status'] ?? 'active';
// Years of residency is offered only once the residency column exists (review migration 20260926_resident_years_of_residency).
$residency_ready = residents_residency_ready($connection);
$residency = ['input' => $record && $residency_ready && $record['residency_start_year'] !== null ? (string) residents_years_of_residency($record['residency_start_year']) : '', 'start_year' => $record['residency_start_year'] ?? null, 'error' => null];
// PWD and Solo Parent checkboxes are offered only once their columns exist (review migration 20261007_resident_pwd_solo_parent).
$sector_ready = residents_sector_ready($connection);
$sector = array_fill_keys(array_keys(residents_sector_labels()), 0);
if ($record && $sector_ready) foreach (array_keys($sector) as $field) $sector[$field] = (int) $record[$field];
// "None" is pre-selected when editing a profile that has neither; a new resident starts with nothing selected (required).
$sector_none = $record !== null && array_sum($sector) === 0;
$household = ['mode' => 'later', 'household_id' => null, 'relationship' => null, 'new' => ['household_no' => '', 'address' => '', 'purok' => '']];
$errors = [];
$form_error = null;
$duplicates = ['exact' => [], 'possible' => []];
$show_duplicates = false;
$override_input = ['duplicate_override' => '', 'override_resident_id' => '', 'override_reason' => ''];
// Account fields — only used when creating a new resident (not on edit)
$account_values = ['email' => '', 'new_password' => '', 'confirm_password' => ''];
$account_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session token expired. Please review the form and submit again.';
    $validated = residents_validate($_POST, $record);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if ($residency_ready) {
        $residency = residents_validate_residency($_POST, isset($errors['birth_date']) ? null : $values['birth_date']);
        if ($residency['error'] !== null) $errors['years_of_residency'] = $residency['error'];
    }
    if ($sector_ready) {
        $sector = residents_sector_input($_POST);
        $sector_none = (string) ($_POST['sector_none'] ?? '') === '1';
        if (($sector_error = residents_sector_error($_POST, $sector)) !== null) $errors['sector'] = $sector_error;
    }
    $override_input = ['duplicate_override' => (string) ($_POST['duplicate_override'] ?? ''), 'override_resident_id' => (string) ($_POST['override_resident_id'] ?? ''), 'override_reason' => trim((string) ($_POST['override_reason'] ?? ''))];
    if (!$is_edit) {
        // Staff-encoded registrations are verified in person, so only Active or Pending are offered; other statuses use Status Management.
        $status = (string) ($_POST['status'] ?? 'active');
        if (!in_array($status, ['active', 'pending'], true)) $errors['status'] = 'Select Active or Pending.';
        $household_check = residents_validate_household($connection, $_POST);
        $household = $household_check['values'];
        $errors += $household_check['errors'];
        // Account credentials for the new resident user account
        $account_values = [
            'email'            => trim((string) ($_POST['email'] ?? '')),
            'new_password'     => (string) ($_POST['new_password'] ?? ''),
            'confirm_password' => (string) ($_POST['confirm_password'] ?? ''),
        ];
        if ($account_values['email'] === '') {
            $account_errors['email'] = 'Email is required for the resident account.';
        } elseif (!filter_var($account_values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($account_values['email']) > 190) {
            $account_errors['email'] = 'Enter a valid email address of up to 190 characters.';
        } else {
            // Uniqueness check — must be done outside the transaction so a clear error reaches the form
            $dup = $connection->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $dup->execute(['email' => $account_values['email']]);
            if ($dup->fetchColumn()) $account_errors['email'] = 'This email address is already used by another account.';
        }
        $account_errors += accounts_password_errors($account_values['new_password'], $account_values['confirm_password'], [$_POST['first_name'] ?? '', $_POST['last_name'] ?? '', $account_values['email']]);
        $errors += $account_errors;
    } elseif ((string) ($_POST['form_updated_at'] ?? '') !== (string) $record['updated_at']) {
        $form_error = 'This resident profile was changed by another action after you opened it. Reload the page to review the latest information.';
    }

    $identity_changed = !$is_edit;
    if ($is_edit) {
        foreach ($identity_fields as $field) {
            if (residents_normalize_name((string) $values[$field]) !== residents_normalize_name((string) $record[$field])) $identity_changed = true;
        }
    }
    $review = ['blocked' => false, 'override' => null, 'errors' => []];
    if ($identity_changed && !isset($errors['first_name']) && !isset($errors['last_name'])) {
        $duplicates = residents_duplicates($connection, $values, $record['id'] ?? null);
        $review = residents_duplicate_review($duplicates, $_POST);
        $errors += $review['errors'];
        $show_duplicates = $review['blocked'] || $review['errors'] !== [];
    }

    if ($form_error === null && $errors === [] && !$review['blocked']) {
        try {
            $connection->beginTransaction();
            if ($is_edit) {
                $locked = residents_find($connection, $id, true);
                if (!$locked || $locked['updated_at'] !== $record['updated_at']) throw new RuntimeException('This resident profile was changed by another action after you opened it. Reload the page to review the latest information.');
            }
            // Duplicate checking is repeated inside the transaction immediately before the write.
            if ($identity_changed) {
                $final_matches = residents_duplicates($connection, $values, $record['id'] ?? null);
                $final_review = residents_duplicate_review($final_matches, $_POST);
                if ($final_review['blocked']) { $duplicates = $final_matches; $show_duplicates = true; throw new RuntimeException('A matching resident profile was found. Review the possible duplicate before continuing.'); }
                $review = $final_review;
            }
            if ($is_edit) {
                $changed = array_values(array_filter($profile_fields, static fn (string $field): bool => (string) $values[$field] !== (string) $record[$field]));
                $residency_changed = $residency_ready && (string) $residency['start_year'] !== (string) $record['residency_start_year'];
                if ($changed !== []) {
                    $update = $connection->prepare('UPDATE residents SET first_name = :first_name, middle_name = :middle_name, last_name = :last_name, suffix = :suffix, birth_date = :birth_date, sex = :sex, civil_status = :civil_status, contact_number = :contact_number, address = :address, purok = :purok WHERE id = :id');
                    $update->execute($values + ['id' => $id]);
                }
                if ($sector_ready) {
                    $sector_changed = array_keys(array_filter($sector, static fn (int $value, string $field): bool => $value !== (int) $record[$field], ARRAY_FILTER_USE_BOTH));
                    if ($sector_changed !== []) {
                        $connection->prepare('UPDATE residents SET is_pwd = :is_pwd, is_solo_parent = :is_solo_parent WHERE id = :id')->execute($sector + ['id' => $id]);
                        array_push($changed, ...$sector_changed);
                    }
                }
                if ($residency_changed) {
                    $connection->prepare('UPDATE residents SET residency_start_year = :start_year WHERE id = :id')->execute(['start_year' => $residency['start_year'], 'id' => $id]);
                    $changed[] = 'years_of_residency';
                }
                if ($changed !== []) residents_audit($connection, 'resident', $id, 'resident_updated', ['changed_fields' => $changed]);
                $saved_id = $id;
                $message = $changed === [] ? 'No changes were made to this resident profile.' : 'Resident profile updated successfully.';
            } else {
                $insert = $connection->prepare('INSERT INTO residents (first_name, middle_name, last_name, suffix, birth_date, sex, civil_status, contact_number, address, purok, status) VALUES (:first_name, :middle_name, :last_name, :suffix, :birth_date, :sex, :civil_status, :contact_number, :address, :purok, :status)');
                $insert->execute($values + ['status' => $status]);
                $saved_id = (int) $connection->lastInsertId();
                if ($residency_ready && $residency['start_year'] !== null) $connection->prepare('UPDATE residents SET residency_start_year = :start_year WHERE id = :id')->execute(['start_year' => $residency['start_year'], 'id' => $saved_id]);
                if ($sector_ready && array_sum($sector) > 0) $connection->prepare('UPDATE residents SET is_pwd = :is_pwd, is_solo_parent = :is_solo_parent WHERE id = :id')->execute($sector + ['id' => $saved_id]);
                residents_audit($connection, 'resident', $saved_id, 'resident_created', ['status' => $status, 'household_option' => $household['mode']] + ($sector_ready ? $sector : []));
                if ($household['mode'] !== 'later') {
                    $household_id = residents_open_membership($connection, $saved_id, $household);
                    residents_audit($connection, 'resident', $saved_id, 'resident_household_assigned', ['household_id' => $household_id, 'relationship_to_head' => $household['relationship']]);
                }
                $message = 'Resident profile created successfully.';
                // Create the linked resident user account and set the bidirectional link.
                $user_insert = $connection->prepare("INSERT INTO users (name, email, password_hash, role, status, resident_id, approved_by, approved_at) VALUES (:name, :email, :hash, 'resident', 'active', :resident_id, :approved_by, NOW())");
                $user_insert->execute([
                    'name'        => residents_full_name($values),
                    'email'       => $account_values['email'],
                    'hash'        => password_hash($account_values['new_password'], PASSWORD_DEFAULT),
                    'resident_id' => $saved_id,
                    'approved_by' => current_user()['id'],
                ]);
                $new_user_id = (int) $connection->lastInsertId();
                accounts_set_must_change($connection, $new_user_id, true);   // the resident chooses their own password at first sign-in
                // Set residents.user_id to complete the bidirectional link
                $connection->prepare('UPDATE residents SET user_id = :user_id WHERE id = :resident_id')
                    ->execute(['user_id' => $new_user_id, 'resident_id' => $saved_id]);
                accounts_audit($connection, $new_user_id, 'account_created', ['name' => residents_full_name($values), 'account_role' => 'resident', 'linked_resident_id' => $saved_id]);
            }
            if ($review['override'] !== null) residents_audit($connection, 'resident', $saved_id, 'resident_duplicate_override', $review['override']);
            $connection->commit();
            flash('resident_success', $message);
            redirect('resident_view.php?id=' . $saved_id);
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getCode() === '23000' ? 'The household number is already registered, or the resident already has a current household. Review the household details and try again.' : 'The resident profile could not be saved. No changes were made.';
        }
    }
}
$households = $is_edit ? [] : residents_household_options($connection);
$duplicate_ids = residents_duplicate_ids($duplicates);
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$cancel_url = $is_edit ? 'resident_view.php?id=' . $record['id'] : 'residents.php';
$page_title = $is_edit ? 'Edit Resident' : 'Add Resident'; $active_page = 'residents';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="<?= e($cancel_url) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p><?= $is_edit ? 'Update the personal and contact information of resident #' . e((string) $record['id']) . '.' : 'Register a resident whose identity and residency were verified by barangay staff.' ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== [] && !$show_duplicates): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" data-resident-form>
        <?= csrf_field() ?>
        <?php if ($is_edit): ?><input type="hidden" name="form_updated_at" value="<?= e($record['updated_at']) ?>"><?php endif; ?>

        <?php if ($show_duplicates && $duplicate_ids !== []): ?>
        <section class="resident-duplicate-panel" aria-labelledby="duplicate-heading">
            <h2 id="duplicate-heading"><?= $duplicates['exact'] !== [] ? 'Possible duplicate resident found' : 'Similar resident records found' ?></h2>
            <p><?= $duplicates['exact'] !== [] ? 'An existing profile has the same name and birthdate. Registration has been stopped so the records can be reviewed.' : 'Existing profiles partially match this name or birthdate. Review them before continuing.' ?> Nothing has been saved.</p>
            <ul class="resident-duplicate-list">
                <?php foreach (['exact' => 'Exact match', 'possible' => 'Possible match'] as $kind => $kind_label): foreach ($duplicates[$kind] as $match): ?>
                    <li><span class="resident-duplicate-kind is-<?= e($kind) ?>"><?= e($kind_label) ?></span><strong><?= e($match['name']) ?></strong><span>#<?= e((string) $match['id']) ?> · Born <?= e($match['birth_date'] ? residents_format_date($match['birth_date']) : 'unknown') ?> · <?= e(residents_purok_label($match['purok'])) ?> ·<?= residents_status_badge($match['status']) ?></span><a class="activity-detail-link" href="resident_view.php?id=<?= e((string) $match['id']) ?>" target="_blank" rel="noopener">View existing profile</a></li>
                <?php endforeach; endforeach; ?>
            </ul>
            <input type="hidden" name="reviewed_match_ids" value="<?= e(implode(',', $duplicate_ids)) ?>">
            <div class="resident-override">
                <p class="resident-override-note">Proceed only if identity verification confirmed that this is a <strong>different person</strong> from every record listed. Existing profiles are never merged or changed.</p>
                <div class="form-check mb-3"><input class="form-check-input<?= $field_class('duplicate_override') ?>" type="checkbox" id="duplicate_override" name="duplicate_override" value="1" <?= $override_input['duplicate_override'] === '1' ? 'checked' : '' ?>><label class="form-check-label" for="duplicate_override">I verified this person's identity and confirm they are not any of the residents listed above.</label></div>
                <div class="resident-grid">
                    <div><label class="form-label" for="override_resident_id">Existing record reviewed</label><select class="form-select<?= $field_class('override_resident_id') ?>" id="override_resident_id" name="override_resident_id"><option value="">Select a record</option><?php foreach (array_merge($duplicates['exact'], $duplicates['possible']) as $match): ?><option value="<?= e((string) $match['id']) ?>" <?= $override_input['override_resident_id'] === (string) $match['id'] ? 'selected' : '' ?>>#<?= e((string) $match['id']) ?> — <?= e($match['name']) ?></option><?php endforeach; ?></select><?= $field_error('override_resident_id') ?></div>
                    <div><label class="form-label" for="override_reason">Verification details and reason</label><textarea class="form-control<?= $field_class('override_reason') ?>" id="override_reason" name="override_reason" rows="3" maxlength="500" placeholder="For example: presented a different government ID; different parents on birth certificate."><?= e($override_input['override_reason']) ?></textarea><?= $field_error('override_reason') ?></div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <fieldset class="resident-section">
            <legend>Personal Information</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="first_name">First name <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('first_name') ?>" id="first_name" name="first_name" maxlength="80" value="<?= e($values['first_name']) ?>" required autocomplete="given-name"><?= $field_error('first_name') ?></div>
                <div><label class="form-label" for="middle_name">Middle name</label><input class="form-control<?= $field_class('middle_name') ?>" id="middle_name" name="middle_name" maxlength="80" value="<?= e($values['middle_name']) ?>" autocomplete="additional-name"><?= $field_error('middle_name') ?></div>
                <div><label class="form-label" for="last_name">Last name <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('last_name') ?>" id="last_name" name="last_name" maxlength="80" value="<?= e($values['last_name']) ?>" required autocomplete="family-name"><?= $field_error('last_name') ?></div>
                <div><label class="form-label" for="suffix">Suffix</label><input class="form-control<?= $field_class('suffix') ?>" id="suffix" name="suffix" maxlength="20" value="<?= e($values['suffix']) ?>" placeholder="Jr., Sr., III"><?= $field_error('suffix') ?></div>
                <?php $birth_required = !$is_edit || !empty($record['birth_date']); $sex_required = !$is_edit || !empty($record['sex']); ?>
                <div><label class="form-label" for="birth_date">Birthdate<?php if ($birth_required): ?> <span class="resident-required" aria-hidden="true">*</span><?php endif; ?></label><input class="form-control<?= $field_class('birth_date') ?>" type="date" id="birth_date" name="birth_date" min="1900-01-01" max="<?= e(date('Y-m-d')) ?>" value="<?= e($values['birth_date']) ?>" <?= $birth_required ? 'required' : '' ?>><?= $field_error('birth_date') ?></div>
                <div><label class="form-label" for="sex">Sex<?php if ($sex_required): ?> <span class="resident-required" aria-hidden="true">*</span><?php endif; ?></label><select class="form-select<?= $field_class('sex') ?>" id="sex" name="sex" <?= $sex_required ? 'required' : '' ?>><option value="">Select sex</option><?php foreach (residents_sex_labels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $values['sex'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('sex') ?></div>
                <div><label class="form-label" for="civil_status">Civil status</label><select class="form-select<?= $field_class('civil_status') ?>" id="civil_status" name="civil_status"><option value="">Not specified</option><?php foreach (residents_civil_status_labels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $values['civil_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('civil_status') ?></div>
                <div>
                    <?php if ($is_edit): ?>
                        <span class="form-label d-block">Resident status</span><p class="resident-static"><?= residents_status_badge($status) ?> <a class="activity-detail-link" href="resident_status.php?id=<?= e((string) $record['id']) ?>">Change status</a></p>
                    <?php else: ?>
                        <label class="form-label" for="status">Resident status</label><select class="form-select<?= $field_class('status') ?>" id="status" name="status"><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option><option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option></select><?= $field_error('status') ?>
                    <?php endif; ?>
                </div>
            </div>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Contact and Address</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="contact_number">Contact number</label><input class="form-control<?= $field_class('contact_number') ?>" type="tel" id="contact_number" name="contact_number" maxlength="30" value="<?= e($values['contact_number']) ?>" placeholder="09171234567" autocomplete="tel"><?= $field_error('contact_number') ?></div>
                <div><label class="form-label" for="purok">Purok <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('purok') ?>" id="purok" name="purok" required><option value="">Select Purok</option><?php foreach (residents_purok_select_options($record['purok'] ?? null) as $value => $label): ?><option value="<?= e((string) $value) ?>" <?= (string) $values['purok'] === (string) $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('purok') ?></div>
                <?php if ($residency_ready): ?>
                    <div><label class="form-label" for="years_of_residency">Years of residency</label><input class="form-control<?= $field_class('years_of_residency') ?>" type="number" id="years_of_residency" name="years_of_residency" min="0" max="120" step="1" inputmode="numeric" value="<?= e($residency['input']) ?>" placeholder="e.g. 28" aria-describedby="years_of_residency_help"><div class="form-text" id="years_of_residency_help">How many years the resident has lived in Barangay San Jose. Updates automatically every year. Shown on the Barangay Clearance.</div><?= $field_error('years_of_residency') ?></div>
                <?php endif; ?>
                <div class="resident-grid-full"><label class="form-label" for="address">Address <span class="resident-required" aria-hidden="true">*</span></label><textarea class="form-control<?= $field_class('address') ?>" id="address" name="address" rows="2" maxlength="500" required><?= e($values['address']) ?></textarea><?= $field_error('address') ?></div>
            </div>
        </fieldset>

        <?php if ($is_edit): ?>
            <fieldset class="resident-section">
                <legend>Household Information</legend>
                <p class="resident-static">Household membership is changed through <a class="activity-detail-link" href="resident_household.php?id=<?= e((string) $record['id']) ?>">Manage Household Assignment</a>, so personal-information edits never move a resident between households.</p>
            </fieldset>
        <?php else: ?>
            <?php require __DIR__ . '/layout/resident_household_fields.php'; ?>
        <?php endif; ?>

        <?php if ($sector_ready): ?>
        <fieldset class="resident-section">
            <legend>Sector <span class="resident-required" aria-hidden="true">*</span></legend>
            <p class="resident-static">Select None, or tick PWD and/or Solo Parent (a resident can be both). Used in Vulnerable Residents (Disaster), the Dashboard and Reports.</p>
            <div class="resident-sector-checks<?= isset($errors['sector']) ? ' is-invalid' : '' ?>" data-sector-group>
                <div class="form-check"><input class="form-check-input<?= $field_class('sector') ?>" type="checkbox" id="sector_none" name="sector_none" value="1" <?= $sector_none ? 'checked' : '' ?> data-sector-none><label class="form-check-label" for="sector_none">None (not PWD or Solo Parent)</label></div>
                <?php foreach (residents_sector_labels() as $field => $label): ?>
                    <div class="form-check"><input class="form-check-input<?= $field_class('sector') ?>" type="checkbox" id="<?= e($field) ?>" name="<?= e($field) ?>" value="1" <?= $sector[$field] === 1 ? 'checked' : '' ?> data-sector-option><label class="form-check-label" for="<?= e($field) ?>"><?= e($field === 'is_pwd' ? 'PWD (person with disability)' : $label) ?></label></div>
                <?php endforeach; ?>
            </div>
            <?php if (isset($errors['sector'])): ?><div class="invalid-feedback d-block"><?= e($errors['sector']) ?></div><?php endif; ?>
        </fieldset>
        <script>
        // None and PWD / Solo Parent exclude each other; one choice is required before the form can be submitted.
        (() => {
            const group = document.querySelector('[data-sector-group]');
            if (!group) return;
            const none = group.querySelector('[data-sector-none]');
            const options = [...group.querySelectorAll('[data-sector-option]')];
            const validate = () => none.setCustomValidity(none.checked || options.some((o) => o.checked) ? '' : 'Select None, or tick PWD and/or Solo Parent.');
            none.addEventListener('change', () => { if (none.checked) options.forEach((o) => { o.checked = false; }); validate(); });
            options.forEach((o) => o.addEventListener('change', () => { if (o.checked) none.checked = false; validate(); }));
            validate();
        })();
        </script>
        <?php endif; ?>

        <fieldset class="resident-section">
            <legend>Additional Information</legend>
            <p class="resident-static">Additional details such as birthplace, citizenship, occupation, education, voter status and emergency contacts are not yet supported by the database and will be added in a future approved update.</p>
        </fieldset>

        <?php if (!$is_edit): ?>
        <fieldset class="resident-section">
            <legend>Resident Account</legend>
            <p class="resident-static">Create the login account for this resident. The email and password will be used to sign in to the portal.</p>
            <div class="resident-grid">
                <div class="resident-grid-full">
                    <label class="form-label" for="email">Email address <span class="resident-required" aria-hidden="true">*</span></label>
                    <input class="form-control<?= isset($account_errors['email']) ? ' is-invalid' : '' ?>" type="email" id="email" name="email" maxlength="190" value="<?= e($account_values['email']) ?>" required autocomplete="off">
                    <div class="form-text">Used to sign in. Must be unique across all accounts.</div>
                    <?php if (isset($account_errors['email'])): ?><div class="invalid-feedback d-block"><?= e($account_errors['email']) ?></div><?php endif; ?>
                </div>
                <div>
                    <label class="form-label" for="new_password">Password <span class="resident-required" aria-hidden="true">*</span></label>
                    <input class="form-control<?= isset($account_errors['new_password']) ? ' is-invalid' : '' ?>" type="password" id="new_password" name="new_password" minlength="<?= ACCOUNTS_PASSWORD_MIN ?>" maxlength="72" required autocomplete="new-password">
                    <div class="form-text"><?= e(accounts_password_rules_text()) ?></div>
                    <?php if (isset($account_errors['new_password'])): ?><div class="invalid-feedback d-block"><?= e($account_errors['new_password']) ?></div><?php endif; ?>
                </div>
                <div>
                    <label class="form-label" for="confirm_password">Confirm password <span class="resident-required" aria-hidden="true">*</span></label>
                    <input class="form-control<?= isset($account_errors['confirm_password']) ? ' is-invalid' : '' ?>" type="password" id="confirm_password" name="confirm_password" minlength="<?= ACCOUNTS_PASSWORD_MIN ?>" maxlength="72" required autocomplete="new-password">
                    <?php if (isset($account_errors['confirm_password'])): ?><div class="invalid-feedback d-block"><?= e($account_errors['confirm_password']) ?></div><?php endif; ?>
                </div>
            </div>
        </fieldset>
        <?php endif; ?>

        <div class="form-actions">
            <?php if ($show_duplicates): ?>
                <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Save despite possible duplicate?" data-dialog-message="Confirm that identity verification showed this is a different person from every listed resident. Your reason and the reviewed record will be recorded in the audit log." data-dialog-confirm="Confirm and Save" data-dialog-danger="true"><?= $is_edit ? 'Save Changes' : 'Create Resident' ?></button>
            <?php elseif ($is_edit): ?>
                <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Save resident changes?" data-dialog-message="Are you sure you want to save the changes to this resident profile? Status and household membership are not changed by this form." data-dialog-confirm="Save Changes">Save Changes</button>
            <?php else: ?>
                <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Create resident profile?" data-dialog-message="Are you sure you want to register this resident? Duplicate checking runs again before the profile is saved. A resident account will be created with the email and password you entered." data-dialog-confirm="Create Resident">Create Resident</button>
            <?php endif; ?>
            <a class="btn btn-light" href="<?= e($cancel_url) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
