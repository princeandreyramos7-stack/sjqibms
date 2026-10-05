<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/accounts.php';
accounts_require_admin();
$connection = db();

// Assign a role to a barangay staff account (System Administrator only), for example the Barangay Treasurer or the
// Punong Barangay. Only staff roles can be assigned; the System Administrator and resident accounts are never changed.
// The Treasurer and the Punong Barangay are held by one active account each. The administrator's current password is
// required. Financial Management applies the new role at once (it re-reads the role from the database); other pages use
// it from the account holder's next sign-in.
$target_id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$target = accounts_find($connection, $target_id);
if ($target === null || !in_array($target['role'], accounts_staff_roles(), true)) { http_response_code(404); exit('Account not found.'); }
$roles = accounts_assignable_roles($connection);
$role = (string) ($_POST['role'] ?? $target['role']);
$errors = [];
$form_error = null;
// Opened from User Management (?from=users): return there instead of the Profile page.
$from_users = ($_GET['from'] ?? $_POST['from'] ?? '') === 'users';
$back = $from_users ? 'users.php' : 'profile.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($role, $roles, true)) $errors['role'] = 'Select a staff role.';
    elseif ($role === $target['role']) $errors['role'] = 'This account already has this role.';
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please try again.';
    elseif (!accounts_verify_admin_password($connection, (string) ($_POST['current_password'] ?? ''))) $errors['current_password'] = 'Your current password is incorrect.';
    if ($form_error === null && $errors === []) {
        try {
            $connection->beginTransaction();
            $locked = $connection->prepare('SELECT id, role, status, updated_at FROM users WHERE id = :id FOR UPDATE');
            $locked->execute(['id' => $target['id']]);
            $current = $locked->fetch();
            if (!$current || $current['role'] !== $target['role'] || !in_array($current['role'], accounts_staff_roles(), true)) throw new RuntimeException('This account was changed by another action. Reload the page and try again.');
            if (in_array($role, accounts_single_holder_roles(), true) && $current['status'] === 'active') {
                $connection->query("SELECT id FROM users WHERE role IN ('treasurer', 'punong_barangay') FOR UPDATE")->fetchAll();
                $holder = accounts_role_holder($connection, $role, (int) $target['id']);
                if ($holder !== null) throw new RuntimeException($holder['name'] . ' is already the active ' . accounts_role_label($role) . '. Assign another role to that account first.');
            }
            $connection->prepare('UPDATE users SET role = :role WHERE id = :id')->execute(['role' => $role, 'id' => $target['id']]);
            accounts_audit($connection, (int) $target['id'], 'account_role_changed', ['name' => $target['name'], 'role_from' => $target['role'], 'role_to' => $role]);
            $connection->commit();
            flash($from_users ? 'users_success' : 'profile_success', $target['name'] . ' is now ' . accounts_role_label($role) . '. Financial Management uses the new role immediately; other pages from their next sign-in.');
            redirect($back);
        } catch (PDOException) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = 'The role could not be changed. No changes were made.';
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        }
    }
}
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$page_title = 'Assign Role'; $active_page = '';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="<?= e($back) ?>"><span aria-hidden="true">&larr;</span> Back to <?= $from_users ? 'User Management' : 'Profile' ?></a>
    <div class="page-heading"><div><h1>Assign Role</h1><p>Change the office of <?= e($target['name']) ?> (currently <?= e(accounts_role_label($target['role'])) ?>).</p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e((string) $target['id']) ?>"><?php if ($from_users): ?><input type="hidden" name="from" value="users"><?php endif; ?>
        <fieldset class="resident-section">
            <legend>Role</legend>
            <p class="resident-static">Account: <strong><?= e($target['name']) ?></strong> · <?= e($target['email']) ?> · <?= e(ucfirst($target['status'])) ?></p>
            <div class="resident-grid">
                <div><label class="form-label" for="role">New role <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('role') ?>" id="role" name="role" required data-summary-label="Role"><?php foreach ($roles as $option): ?><option value="<?= e($option) ?>" <?= $role === $option ? 'selected' : '' ?>><?= e(accounts_role_label($option)) ?><?= $option === $target['role'] ? ' (current)' : '' ?></option><?php endforeach; ?></select><div class="form-text">The Barangay Treasurer and the Punong Barangay are held by one active account each.</div><?= $field_error('role') ?></div>
            </div>
        </fieldset>
        <fieldset class="resident-section">
            <legend>Confirm Your Identity</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="current_password">Your current password (System Administrator) <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('current_password') ?>" type="password" id="current_password" name="current_password" required autocomplete="current-password"><?= $field_error('current_password') ?></div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="Change this account's role?" data-dialog-message="The account holder signs in through the portal of the new role from now on." data-dialog-confirm="Assign Role" data-dialog-dismiss="Cancel">Assign Role</button>
            <a class="btn btn-light" href="<?= e($back) ?>">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
