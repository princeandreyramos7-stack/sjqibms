<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/accounts.php';
require_once __DIR__ . '/includes/security_log.php';
require_auth();

// Change your own password (every signed-in account: residents and barangay staff). The current password is required;
// after 5 wrong current passwords the form is locked for 15 minutes (this browser session) and the attempt is logged.
// The System Administrator uses account_password.php, which also sets passwords of staff accounts.
if (has_role('super_admin')) redirect('account_password.php');
$connection = db();
$user_id = (int) current_user()['id'];
const MY_PASSWORD_MAX_ATTEMPTS = 5;
const MY_PASSWORD_LOCK_SECONDS = 900;

$errors = [];
$form_error = null;
$lock = (array) ($_SESSION['my_password_lock'] ?? []);
$locked_until = (int) ($lock['until'] ?? 0);
if ($locked_until > 0 && $locked_until <= time()) { unset($_SESSION['my_password_lock']); $lock = []; $locked_until = 0; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = (string) ($_POST['new_password'] ?? '');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $form_error = 'Your session expired. Please try again.';
    } elseif ($locked_until > time()) {
        $form_error = 'Too many wrong current passwords. Try again after ' . date('g:i A', $locked_until) . '.';
    } else {
        $statement = $connection->prepare("SELECT password_hash, status, username FROM users WHERE id = :id LIMIT 1");
        $statement->execute(['id' => $user_id]);
        $account = $statement->fetch();
        if (!$account || $account['status'] !== 'active') {
            $form_error = 'This account cannot change its password right now. Contact the System Administrator.';
        } elseif (!password_verify((string) ($_POST['current_password'] ?? ''), $account['password_hash'])) {
            $failures = (int) ($lock['failures'] ?? 0) + 1;
            $_SESSION['my_password_lock'] = ['failures' => $failures, 'until' => $failures >= MY_PASSWORD_MAX_ATTEMPTS ? time() + MY_PASSWORD_LOCK_SECONDS : 0];
            security_log('password_change_failed', $user_id, ['reason' => 'wrong current password', 'attempt' => $failures]);
            $errors['current_password'] = $failures >= MY_PASSWORD_MAX_ATTEMPTS ? 'Too many wrong current passwords. The form is locked for 15 minutes.' : 'Your current password is incorrect.';
        } else {
            $errors = accounts_password_errors($new, (string) ($_POST['confirm_password'] ?? ''), [current_user()['name'] ?? '', current_user()['email'] ?? '', $account['username'] ?? '']);
            if (!isset($errors['new_password']) && password_verify($new, $account['password_hash'])) $errors['new_password'] = 'Choose a password different from the current one.';
            if ($errors === []) {
                try {
                    $connection->beginTransaction();
                    $connection->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')->execute(['hash' => password_hash($new, PASSWORD_DEFAULT), 'id' => $user_id]);
                    accounts_audit($connection, $user_id, 'account_password_changed_self', []);
                    accounts_set_must_change($connection, $user_id, false);
                    $connection->commit();
                    unset($_SESSION['my_password_lock']);
                    session_regenerate_id(true);
                    flash('profile_success', 'Your password was changed. Use the new password the next time you sign in.');
                    redirect('profile.php');
                } catch (PDOException) {
                    if ($connection->inTransaction()) $connection->rollBack();
                    $form_error = 'The password could not be changed. No changes were made.';
                }
            }
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
    <a class="announcement-back" href="profile.php"><span aria-hidden="true">&larr;</span> Back to Profile</a>
    <div class="page-heading"><div><h1>Change Password</h1><p>Change the password you use to sign in to SJQIBMS.</p></div></div>
    <?php if (!empty($_SESSION['user']['must_change_password'])): ?><div class="dashboard-status warning" role="status"><strong>Please choose your own password first.</strong> Your current password was set by the barangay staff; for your security, change it before using SJQIBMS. Enter the password you were given as the current password.</div><?php endif; ?>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <fieldset class="resident-section">
            <legend>Current Password</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="current_password">Current password <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('current_password') ?>" type="password" id="current_password" name="current_password" required autocomplete="current-password"><?= $field_error('current_password') ?></div>
            </div>
        </fieldset>
        <fieldset class="resident-section">
            <legend>New Password</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="new_password">New password <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('new_password') ?>" type="password" id="new_password" name="new_password" minlength="<?= ACCOUNTS_PASSWORD_MIN ?>" maxlength="72" required autocomplete="new-password" aria-describedby="password_rules"><div class="form-text" id="password_rules"><?= e(accounts_password_rules_text()) ?></div><?= $field_error('new_password') ?></div>
                <div><label class="form-label" for="confirm_password">Confirm new password <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('confirm_password') ?>" type="password" id="confirm_password" name="confirm_password" minlength="<?= ACCOUNTS_PASSWORD_MIN ?>" maxlength="72" required autocomplete="new-password"><?= $field_error('confirm_password') ?></div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Change your password?" data-dialog-message="Use the new password the next time you sign in." data-dialog-confirm="Change Password" data-dialog-dismiss="Cancel">Change Password</button>
            <a class="btn btn-light" href="profile.php">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
