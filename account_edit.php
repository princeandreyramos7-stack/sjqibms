<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/accounts.php';
accounts_require_admin();
$connection = db();
$account = accounts_find($connection, (int) current_user()['id']);
if ($account === null) { http_response_code(404); exit('Account not found.'); }

// The System Administrator's own name and email. Role and status are not editable here.
$values = ['name' => $account['name'], 'email' => $account['email']];
$errors = [];
$form_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = ['name' => residents_collapse((string) ($_POST['name'] ?? '')), 'email' => mb_strtolower(trim((string) ($_POST['email'] ?? '')))];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 150) $errors['name'] = 'Enter a full name of 2 to 150 characters.';
    elseif (!preg_match("/^\p{L}[\p{L}\p{M}\s.,'\-]*$/u", $values['name'])) $errors['name'] = 'The name may contain only letters, spaces, periods, commas, apostrophes and hyphens.';
    if (mb_strlen($values['email']) > 190 || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) $errors['email'] = 'Enter a valid email address.';
    if (!isset($errors['email'])) {
        $taken = $connection->prepare('SELECT 1 FROM users WHERE email = :email AND id <> :id');
        $taken->execute(['email' => $values['email'], 'id' => $account['id']]);
        if ($taken->fetchColumn()) $errors['email'] = 'This email address is already used by another account.';
    }
    if ($form_error === null && $errors === [] && !accounts_verify_admin_password($connection, (string) ($_POST['current_password'] ?? ''))) $errors['current_password'] = 'The current password is incorrect.';
    if ($form_error === null && $errors === []) {
        $changed = array_keys(array_filter(['name' => $values['name'] !== $account['name'], 'email' => $values['email'] !== $account['email']]));
        try {
            if ($changed !== []) {
                $connection->beginTransaction();
                $connection->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id')->execute($values + ['id' => $account['id']]);
                accounts_audit($connection, (int) $account['id'], 'account_profile_updated', ['changed_fields' => $changed]);
                $connection->commit();
                // Keep the signed-in session in step with the saved account.
                $_SESSION['user']['name'] = $values['name'];
                $_SESSION['user']['email'] = $values['email'];
            }
            flash('profile_success', $changed === [] ? 'No changes were made to your profile.' : 'Your profile was updated.');
            redirect('profile.php');
        } catch (PDOException) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = 'Your profile could not be saved. No changes were made.';
        }
    }
}
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$page_title = 'Edit Profile'; $active_page = '';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="profile.php"><span aria-hidden="true">&larr;</span> Back to Profile</a>
    <div class="page-heading"><div><h1>Edit Profile</h1><p>Update the System Administrator's name and email address. Your role and account status are not changed.</p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <fieldset class="resident-section">
            <legend>Account Details</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="name">Full name <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('name') ?>" id="name" name="name" maxlength="150" value="<?= e($values['name']) ?>" required autocomplete="name"><?= $field_error('name') ?></div>
                <div><label class="form-label" for="email">Email address <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('email') ?>" type="email" id="email" name="email" maxlength="190" value="<?= e($values['email']) ?>" required autocomplete="email"><div class="form-text">Used to sign in.</div><?= $field_error('email') ?></div>
            </div>
        </fieldset>
        <fieldset class="resident-section">
            <legend>Confirm Your Identity</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="current_password">Current password <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('current_password') ?>" type="password" id="current_password" name="current_password" required autocomplete="current-password"><?= $field_error('current_password') ?></div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Save profile changes?" data-dialog-message="If you change the email address, use the new address the next time you sign in." data-dialog-confirm="Save Changes" data-dialog-dismiss="Cancel">Save Changes</button>
            <a class="btn btn-light" href="profile.php">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
