<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/users.php';
users_require_admin();
$connection = db();

// Add a staff account (name, email, role, first password) or edit a staff account's name and email. Role changes and
// new passwords for existing accounts use the Role and Password pages. The administrator's current password is required.
$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$account = $id ? users_find($connection, $id) : null;
if ($id && ($account === null || !users_allowed_actions($account)['edit'])) { http_response_code(404); exit('Account not found.'); }
$is_edit = $account !== null;
$values = $account ? ['name' => $account['name'], 'email' => $account['email'], 'role' => $account['role']] : ['name' => '', 'email' => '', 'role' => ''];
$errors = [];
$form_error = null;
// Health Worker accounts: the Puroks they cover (health_worker_puroks). None ticked = all Puroks.
$current_puroks = $account && $account['role'] === 'health_worker' ? health_worker_puroks($connection, (int) $account['id']) : [];
$puroks = $current_puroks;
// Health Workers live in the barangay too: their account is linked to a resident profile (My Household, Document
// Requests, Disaster Info). Required for a new Health Worker; for an existing one not yet linked, filled in when known.
$linked = $account && in_array($account['role'], users_resident_staff_roles(), true) ? users_linked_resident($connection, (int) $account['id']) : null;
$resident_fields = ['first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'sex', 'civil_status', 'contact_number', 'purok', 'address'];
$resident_values = array_fill_keys($resident_fields, '');
$show_resident = !$is_edit || (in_array($account['role'], users_resident_staff_roles(), true) && $linked === null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $puroks = array_values(array_intersect(array_map('strval', array_keys(residents_purok_options())), array_map('strval', (array) ($_POST['puroks'] ?? []))));
    // New Health Worker: the account name is composed from the Resident Details (first, middle, last name, suffix), so it is
    // typed only once. A name problem is reported on those fields.
    $input = $_POST;
    $new_hw = !$is_edit && in_array((string) ($input['role'] ?? ''), users_resident_staff_roles(), true);
    if ($new_hw) $input['name'] = residents_collapse(implode(' ', array_map(static fn (string $f): string => trim((string) ($_POST[$f] ?? '')), ['first_name', 'middle_name', 'last_name', 'suffix'])));
    $validated = users_validate($connection, $input, $account);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if ($new_hw && isset($errors['name'])) {
        if (trim((string) ($_POST['first_name'] ?? '')) !== '' && trim((string) ($_POST['last_name'] ?? '')) !== '') $errors['first_name'] = $errors['name'];
        unset($errors['name']);
    }
    if (!$is_edit) $errors += accounts_password_errors((string) ($_POST['new_password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''), [$values['name'] ?? '', $values['email'] ?? '']);
    $is_hw = in_array($is_edit ? $account['role'] : $values['role'], users_resident_staff_roles(), true);
    $resident_entered = array_filter($resident_fields, static fn (string $f): bool => trim((string) ($_POST[$f] ?? '')) !== '') !== [];
    $link_resident = $is_hw && $show_resident && (!$is_edit || $resident_entered);
    foreach ($resident_fields as $field) $resident_values[$field] = trim((string) ($_POST[$field] ?? ''));
    if ($link_resident) {
        $checked = residents_validate($_POST);
        $resident_values = array_map(static fn ($v): string => (string) $v, $checked['values']);
        $errors += $checked['errors'];
    }
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif (!accounts_verify_admin_password($connection, (string) ($_POST['current_password'] ?? ''))) $errors['current_password'] = 'Your current password is incorrect.';
    if ($form_error === null && $errors === []) {
        try {
            $connection->beginTransaction();
            $admin = (int) current_user()['id'];
            if ($is_edit) {
                $locked = users_find($connection, $id, true);
                if ($locked === null || !users_is_staff($locked)) throw new RuntimeException('This account was changed by another action. Reload the page and try again.');
                $changes = [];
                foreach (['name', 'email'] as $field) if ((string) $locked[$field] !== (string) $values[$field]) $changes[$field] = ['old' => $locked[$field], 'new' => $values[$field]];
                if ($changes !== []) {
                    $connection->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id')->execute(['name' => $values['name'], 'email' => $values['email'], 'id' => $id]);
                    accounts_audit($connection, $id, 'account_updated', ['name' => $values['name'], 'changed_fields' => array_keys($changes), 'changes' => $changes]);
                }
                if ($locked['role'] === 'health_worker' && $puroks !== $current_puroks) {
                    users_save_health_puroks($connection, $id, $puroks);
                    accounts_audit($connection, $id, 'account_updated', ['name' => $values['name'], 'changed_fields' => ['assigned_puroks'], 'changes' => ['assigned_puroks' => ['old' => $current_puroks, 'new' => $puroks]]]);
                    $changes['assigned_puroks'] = true;
                }
                if ($link_resident) {
                    [$resident_id, $created] = users_link_hw_resident($connection, $id, $checked['values']);
                    accounts_audit($connection, $id, 'account_updated', ['name' => $values['name'], 'changed_fields' => ['linked_resident'], 'resident_id' => $resident_id, 'resident_profile' => $created ? 'created' : 'existing']);
                    $changes['linked_resident'] = true;
                }
                $message = $changes === [] ? 'No changes were made.' : 'The account of ' . $values['name'] . ' was updated.' . ($link_resident ? ' It is now linked to ' . ($created ? 'a new' : 'the existing') . ' resident profile.' : '');
            } else {
                if (in_array($values['role'], accounts_single_holder_roles(), true)) {
                    $connection->query("SELECT id FROM users WHERE role IN ('treasurer', 'punong_barangay') FOR UPDATE")->fetchAll();
                    $holder = accounts_role_holder($connection, $values['role'], 0);
                    if ($holder !== null) throw new RuntimeException($holder['name'] . ' is already the active ' . accounts_role_label($values['role']) . '. Suspend that account or assign it another role first.');
                }
                $connection->prepare("INSERT INTO users (name, email, password_hash, role, status, approved_by, approved_at) VALUES (:name, :email, :hash, :role, 'active', :admin, NOW())")
                    ->execute(['name' => $values['name'], 'email' => $values['email'], 'hash' => password_hash((string) $_POST['new_password'], PASSWORD_DEFAULT), 'role' => $values['role'], 'admin' => $admin]);
                $id = (int) $connection->lastInsertId();
                accounts_set_must_change($connection, $id, true);   // the account holder chooses their own password at first sign-in
                accounts_audit($connection, $id, 'account_created', ['name' => $values['name'], 'account_role' => $values['role']] + ($values['role'] === 'health_worker' ? ['assigned_puroks' => $puroks] : []));
                if ($values['role'] === 'health_worker' && $puroks !== []) users_save_health_puroks($connection, $id, $puroks);
                $created = false;
                if ($link_resident) {
                    [$resident_id, $created] = users_link_hw_resident($connection, $id, $checked['values']);
                    accounts_audit($connection, $id, 'account_updated', ['name' => $values['name'], 'changed_fields' => ['linked_resident'], 'resident_id' => $resident_id, 'resident_profile' => $created ? 'created' : 'existing']);
                }
                $message = ($link_resident ? ($created ? 'A resident profile was created and linked. ' : 'The existing resident profile was linked. ') : '') .'The ' . accounts_role_label($values['role']) . ' account of ' . $values['name'] . ' was created. Give the password to the account holder privately; they sign in through the ' . accounts_role_label($values['role']) . ' portal.';
            }
            $connection->commit();
            flash('users_success', $message);
            redirect('users.php');
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = ($exception->errorInfo[1] ?? 0) === 1062 ? 'This email address is already used by another account.' : 'The account could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        }
    }
}
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
$page_title = $is_edit ? 'Edit Account' : 'Add Staff Account'; $active_page = 'users';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="users.php"><span aria-hidden="true">&larr;</span> Back to User Management</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p><?= $is_edit ? 'Change the name or email of ' . e($account['name']) . ' (' . e(accounts_role_label($account['role'])) . '). Use Role or Password on the list for those changes.' : 'Create an account for a barangay official or staff member. It is active immediately.' ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <?php if ($is_edit): ?><input type="hidden" name="id" value="<?= e((string) $id) ?>"><?php endif; ?>
        <fieldset class="resident-section">
            <legend>Account</legend>
            <div class="resident-grid">
                <?php // Role first: a new Health Worker's name comes from the Resident Details below, so the name box is then hidden. ?>
                <?php if (!$is_edit): ?>
                    <div class="resident-grid-full"><label class="form-label" for="role">Role <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('role') ?>" id="role" name="role" required data-summary-label="Role"><option value="">Select role</option><?php foreach (accounts_assignable_roles($connection) as $role): ?><option value="<?= e($role) ?>" <?= $values['role'] === $role ? 'selected' : '' ?>><?= e(accounts_role_label($role)) ?></option><?php endforeach; ?></select><div class="form-text">The Barangay Treasurer is held by one active account.</div><?= $field_error('role') ?></div>
                <?php endif; ?>
                <div<?= $is_edit ? '' : ' data-hw-name' ?>><label class="form-label" for="name">Full name <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('name') ?>" id="name" name="name" maxlength="150" value="<?= e($values['name']) ?>" required data-summary-label="Name"><?= $field_error('name') ?></div>
                <div><label class="form-label" for="email">Email <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('email') ?>" type="email" id="email" name="email" maxlength="190" value="<?= e($values['email']) ?>" required autocomplete="off" data-summary-label="Email"><div class="form-text">Used to sign in.</div><?= $field_error('email') ?></div>
            </div>
        </fieldset>
        <?php if (!$is_edit || $account['role'] === 'health_worker'): ?>
            <fieldset class="resident-section" data-hw-puroks<?= !$is_edit && $values['role'] !== 'health_worker' ? ' hidden' : '' ?>>
                <legend>Assigned Puroks (Health Worker)</legend>
                <p class="resident-static">The Puroks this Health Worker serves: they see only health records (and Health Report counts) of residents in the ticked Puroks. Tick none for all Puroks. This is separate from the Purok where they live (their resident profile).</p>
                <div class="d-flex flex-wrap gap-3"><?php foreach (residents_purok_options() as $value => $label): ?><label class="form-check"><input class="form-check-input" type="checkbox" name="puroks[]" value="<?= e((string) $value) ?>" <?= in_array((string) $value, $puroks, true) ? 'checked' : '' ?>> <span class="form-check-label"><?= e($label) ?></span></label><?php endforeach; ?></div>
            </fieldset>
        <?php endif; ?>
        <?php if ($is_edit && $linked !== null): ?>
            <fieldset class="resident-section">
                <legend>Resident Profile</legend>
                <p class="resident-static">Linked to <strong><?= e(residents_full_name($linked)) ?></strong> (#<?= e((string) $linked['id']) ?> · <?= e(residents_purok_label((string) $linked['purok'])) ?>). Changes to the profile are made in Residents.</p>
            </fieldset>
        <?php elseif ($show_resident): ?>
            <?php $rv = static fn (string $f): string => (string) ($resident_values[$f] ?? ''); $hw_required = !$is_edit; ?>
            <fieldset class="resident-section" data-hw-resident<?= !$is_edit && !in_array($values['role'], users_resident_staff_roles(), true) ? ' hidden' : '' ?>>
                <legend>Resident Details (Health Worker and Treasurer)</legend>
                <p class="resident-static">Health Workers and the Treasurer live in the barangay too, so the account is also their resident profile (My Household; for Health Workers also Document Requests and Disaster Info). The account name is taken from these details. If they are already in the Residents list with the same name and birthdate, that profile is linked instead of making a new one.<?= $is_edit ? ' Leave blank to fill in later.' : '' ?></p>
                <div class="resident-grid">
                    <div><label class="form-label" for="first_name">First name <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('first_name') ?>" id="first_name" name="first_name" maxlength="80" value="<?= e($rv('first_name')) ?>" <?= $hw_required ? 'required' : '' ?> data-summary-label="First name"><?= $field_error('first_name') ?></div>
                    <div><label class="form-label" for="middle_name">Middle name</label><input class="form-control<?= $field_class('middle_name') ?>" id="middle_name" name="middle_name" maxlength="80" value="<?= e($rv('middle_name')) ?>" data-summary-label="Middle name"><?= $field_error('middle_name') ?></div>
                    <div><label class="form-label" for="last_name">Last name <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('last_name') ?>" id="last_name" name="last_name" maxlength="80" value="<?= e($rv('last_name')) ?>" <?= $hw_required ? 'required' : '' ?> data-summary-label="Last name"><?= $field_error('last_name') ?></div>
                    <div><label class="form-label" for="suffix">Suffix</label><input class="form-control<?= $field_class('suffix') ?>" id="suffix" name="suffix" maxlength="20" value="<?= e($rv('suffix')) ?>" placeholder="Jr., Sr., III"><?= $field_error('suffix') ?></div>
                    <div><label class="form-label" for="birth_date">Birthdate <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('birth_date') ?>" type="date" id="birth_date" name="birth_date" min="1900-01-01" max="<?= e(date('Y-m-d')) ?>" value="<?= e($rv('birth_date')) ?>" <?= $hw_required ? 'required' : '' ?> data-summary-label="Birthdate"><?= $field_error('birth_date') ?></div>
                    <div><label class="form-label" for="sex">Sex <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('sex') ?>" id="sex" name="sex" <?= $hw_required ? 'required' : '' ?> data-summary-label="Sex"><option value="">Select</option><?php foreach (['male' => 'Male', 'female' => 'Female'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $rv('sex') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('sex') ?></div>
                    <div><label class="form-label" for="civil_status">Civil status</label><select class="form-select<?= $field_class('civil_status') ?>" id="civil_status" name="civil_status"><option value="">Not specified</option><?php foreach (residents_civil_status_labels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $rv('civil_status') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('civil_status') ?></div>
                    <div><label class="form-label" for="contact_number">Contact number</label><input class="form-control<?= $field_class('contact_number') ?>" id="contact_number" name="contact_number" maxlength="30" value="<?= e($rv('contact_number')) ?>" inputmode="tel" placeholder="09XXXXXXXXX"><?= $field_error('contact_number') ?></div>
                    <div><label class="form-label" for="res_purok">Purok <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('purok') ?>" id="res_purok" name="purok" <?= $hw_required ? 'required' : '' ?> data-summary-label="Purok"><option value="">Select Purok</option><?php foreach (residents_purok_options() as $value => $label): ?><option value="<?= e((string) $value) ?>" <?= $rv('purok') === (string) $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('purok') ?></div>
                    <div class="resident-grid-full"><label class="form-label" for="address">Address <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('address') ?>" id="address" name="address" maxlength="500" value="<?= e($rv('address')) ?>" placeholder="House no., street / sitio" <?= $hw_required ? 'required' : '' ?> data-summary-label="Address"><?= $field_error('address') ?></div>
                </div>
            </fieldset>
        <?php endif; ?>
        <?php if (!$is_edit): ?>
            <fieldset class="resident-section">
                <legend>First Password</legend>
                <div class="resident-grid">
                    <div><label class="form-label" for="new_password">Password <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('new_password') ?>" type="password" id="new_password" name="new_password" minlength="<?= ACCOUNTS_PASSWORD_MIN ?>" maxlength="72" required autocomplete="new-password"><div class="form-text"><?= e(accounts_password_rules_text()) ?></div><?= $field_error('new_password') ?></div>
                    <div><label class="form-label" for="confirm_password">Confirm password <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('confirm_password') ?>" type="password" id="confirm_password" name="confirm_password" minlength="<?= ACCOUNTS_PASSWORD_MIN ?>" maxlength="72" required autocomplete="new-password"><?= $field_error('confirm_password') ?></div>
                </div>
            </fieldset>
        <?php endif; ?>
        <fieldset class="resident-section">
            <legend>Confirm Your Identity</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="current_password">Your current password (System Administrator) <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('current_password') ?>" type="password" id="current_password" name="current_password" required autocomplete="current-password"><?= $field_error('current_password') ?></div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $is_edit ? 'Save changes to this account?' : 'Create this account?' ?>" data-dialog-message="The change is recorded in the audit log. Passwords are never shown or logged." data-dialog-confirm="<?= $is_edit ? 'Save Changes' : 'Create Account' ?>" data-dialog-dismiss="Cancel"><?= $is_edit ? 'Save Changes' : 'Create Account' ?></button>
            <a class="btn btn-light" href="users.php">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
<script>
// Assigned Puroks and Resident Details are shown only when the new account is a Health Worker (hidden fields are
// disabled so their "required" does not block other roles).
(() => {
    const role = document.getElementById('role');
    const puroks = document.querySelector('[data-hw-puroks]');
    const resident = document.querySelector('[data-hw-resident]');
    if (!role) return;
    const residentRoles = <?= json_encode(users_resident_staff_roles()) ?>;
    // A new Health Worker's or Treasurer's full name is taken from the Resident Details, so the name box is hidden for them.
    const nameBox = document.querySelector('[data-hw-name]');
    const show = (box, visible) => { if (!box) return; box.hidden = !visible; box.querySelectorAll('input, select').forEach((field) => { field.disabled = !visible; }); };
    const sync = () => {
        show(puroks, role.value === 'health_worker');
        show(resident, residentRoles.includes(role.value));
        if (nameBox) {
            nameBox.hidden = residentRoles.includes(role.value);
            nameBox.querySelector('input').disabled = nameBox.hidden;
        }
    };
    role.addEventListener('change', sync);
    sync();
})();
</script>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
