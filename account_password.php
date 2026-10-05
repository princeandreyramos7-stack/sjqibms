<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/accounts.php';
accounts_require_admin();
$connection = db();

// Without ?id= (or with the administrator's own id): the administrator's own password.
// With the id of a barangay staff account: sets a new password for that account. Other administrators and resident
// accounts are refused. The administrator's current password is always required.
$self_id = (int) current_user()['id'];
$target_id = filter_var($_GET['id'] ?? $_POST['id'] ?? $self_id, FILTER_VALIDATE_INT) ?: $self_id;
$target = accounts_find($connection, $target_id);
$is_self = $target !== null && (int) $target['id'] === $self_id;
if ($target === null || (!$is_self && !in_array($target['role'], accounts_staff_roles(), true))) { http_response_code(404); exit('Account not found.'); }

$errors = [];
$form_error = null;
// Opened from User Management (?from=users): return there instead of the Profile page.
$from_users = ($_GET['from'] ?? $_POST['from'] ?? '') === 'users';
$back = $from_users ? 'users.php' : 'profile.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = (string) ($_POST['new_password'] ?? '');
    $errors = accounts_password_errors($new, (string) ($_POST['confirm_password'] ?? ''), [$target['name'] ?? '', $target['email'] ?? '', $target['username'] ?? '']);
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please try again.';
    elseif (!accounts_verify_admin_password($connection, (string) ($_POST['current_password'] ?? ''))) $errors['current_password'] = 'Your current password is incorrect.';
    if (!isset($errors['new_password']) && !isset($errors['current_password']) && password_verify($new, $target['password_hash'])) $errors['new_password'] = 'Choose a password different from the current one.';
    if ($form_error === null && $errors === []) {
        try {
            $connection->beginTransaction();
            $connection->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')->execute(['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => $target['id']]);
            accounts_audit($connection, (int) $target['id'], $is_self ? 'account_password_changed' : 'account_password_reset', $is_self ? [] : ['account_role' => $target['role']]);
            // A password set for someone else must be changed by them at the next sign-in; one's own new password clears it.
            accounts_set_must_change($connection, (int) $target['id'], !$is_self);
            $connection->commit();
            if ($is_self) session_regenerate_id(true);
            flash($from_users ? 'users_success' : 'profile_success', $is_self ? 'Your password was changed. Use the new password the next time you sign in.' : 'The password for ' . $target['name'] . ' was changed. Give the new password to the account holder privately.');
            redirect($back);
        } catch (PDOException) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = 'The password could not be changed. No changes were made.';
        }
    }
}
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$page_title = 'Change Password'; $active_page = '';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="<?= e($back) ?>"><span aria-hidden="true">&larr;</span> Back to <?= $from_users ? 'User Management' : 'Profile' ?></a>
    <div class="page-heading"><div><h1>Change Password</h1><p><?= $is_self ? 'Change the System Administrator password.' : 'Set a new password for ' . e($target['name']) . ' (' . e(accounts_role_label($target['role'])) . ').' ?></p></div></div>
    <?php if ($is_self && !empty($_SESSION['user']['must_change_password'])): ?><div class="dashboard-status warning" role="status"><strong>Please choose your own password first.</strong> For your security, change the password that was set for this account before using SJQIBMS.</div><?php elseif (!$is_self): ?><p class="resident-static">The account holder will be asked to choose their own password the next time they sign in.</p><?php endif; ?>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e((string) $target['id']) ?>"><?php if ($from_users): ?><input type="hidden" name="from" value="users"><?php endif; ?>
        <fieldset class="resident-section">
            <legend>New Password</legend>
            <?php if (!$is_self): ?><p class="resident-static">Account: <strong><?= e($target['name']) ?></strong> · <?= e($target['email']) ?> · <?= e(accounts_role_label($target['role'])) ?></p><?php endif; ?>
            <div class="resident-grid">
                <div><label class="form-label" for="new_password">New password <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('new_password') ?>" type="password" id="new_password" name="new_password" minlength="<?= ACCOUNTS_PASSWORD_MIN ?>" maxlength="72" required autocomplete="new-password" aria-describedby="password_rules"><div class="form-text" id="password_rules"><?= e(accounts_password_rules_text()) ?></div><?= $field_error('new_password') ?></div>
                <div><label class="form-label" for="confirm_password">Confirm new password <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('confirm_password') ?>" type="password" id="confirm_password" name="confirm_password" minlength="<?= ACCOUNTS_PASSWORD_MIN ?>" maxlength="72" required autocomplete="new-password"><?= $field_error('confirm_password') ?></div>
            </div>
        </fieldset>
        <fieldset class="resident-section">
            <legend>Confirm Your Identity</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="current_password">Your current password (System Administrator) <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('current_password') ?>" type="password" id="current_password" name="current_password" required autocomplete="current-password"><?= $field_error('current_password') ?></div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Change this password?" data-dialog-message="<?= e($is_self ? 'Use the new password the next time you sign in.' : 'The account holder must use the new password the next time they sign in.') ?>" data-dialog-confirm="Change Password" data-dialog-dismiss="Cancel">Change Password</button>
            <a class="btn btn-light" href="<?= e($back) ?>">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
