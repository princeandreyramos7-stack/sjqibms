<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/accounts.php';
require_auth();

// Every account can change its own password (my_password.php). Only the System Administrator edits account details and sets
// passwords of barangay staff accounts (account_password.php).
$is_admin = has_role('super_admin');
$staff = [];
if ($is_admin) {
    try { $staff = accounts_staff(db()); } catch (PDOException) { $staff = []; }
}
$account = current_user();
try {
    $statement = db()->prepare('SELECT name, email, username, role, status, created_at, last_login_at FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $account['id']]);
    $account = $statement->fetch() ?: $account;
} catch (PDOException) {
    $account['status'] = 'active';
}

$page_title = 'Profile';
$active_page = '';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/layout/sidebar.php'; ?>
    <div class="app-main">
        <?php require __DIR__ . '/layout/topbar.php'; ?>
        <main class="content">
            <div class="page-heading">
                <div><h1>Profile</h1><p>Account information currently available in SJQIBMS.</p></div>
                <?php if ($is_admin): ?><div class="resident-detail-actions"><a class="btn announcement-edit-btn" href="account_edit.php">Edit Profile</a><a class="btn btn-light resident-action-btn" href="account_password.php">Change Password</a></div><?php else: ?><div class="resident-detail-actions"><a class="btn announcement-edit-btn" href="my_password.php">Change Password</a></div><?php endif; ?>
            </div>
            <?php if ($success = flash('profile_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
            <section class="profile-layout">
                <article class="dashboard-panel profile-card"><div class="profile-avatar"><?= e(strtoupper(substr($account['name'] ?? 'A', 0, 1))) ?></div><h2><?= e($account['name'] ?? 'Account holder') ?></h2><span class="role-pill"><?= e(ucwords(str_replace('_', ' ', $account['role'] ?? ''))) ?></span></article>
                <article class="dashboard-panel profile-details"><div class="panel-heading"><h2>Account details</h2></div><dl><div><dt>Full name</dt><dd><?= e($account['name'] ?? '') ?></dd></div><div><dt>Email address</dt><dd><?= e($account['email'] ?? '') ?: '<span class="activity-detail-muted">Not set</span>' ?></dd></div><?php if (!empty($account['username'])): ?><div><dt>Username</dt><dd><?= e($account['username']) ?></dd></div><?php endif; ?><div><dt>Assigned role</dt><dd><?= e(ucwords(str_replace('_', ' ', $account['role'] ?? ''))) ?></dd></div><div><dt>Account status</dt><dd><span class="status-pill"><?= e(ucwords((string) ($account['status'] ?? ''))) ?></span></dd></div><?php if (!empty($account['created_at'])): ?><div><dt>Account created</dt><dd><?= e((string) $account['created_at']) ?></dd></div><?php endif; ?></dl></article>
            </section>
            <?php if ($is_admin): ?>
                <section class="dashboard-panel profile-staff-panel">
                    <div class="panel-heading"><h2>Barangay Staff Accounts</h2></div>
                    <p class="resident-static">Only the System Administrator can set a new password or assign the role (office) of a staff account, including the Barangay Treasurer and the Punong Barangay. Give a new password to the account holder privately.</p>
                    <?php if ($staff === []): ?>
                        <div class="dashboard-empty-state">No barangay staff accounts found.</div>
                    <?php else: ?>
                        <div class="resident-table-wrap"><table class="resident-table"><thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead><tbody>
                            <?php foreach ($staff as $user): ?><tr><td class="resident-name"><?= e($user['name']) ?></td><td><?= e($user['email']) ?></td><td><?= e(accounts_role_label($user['role'])) ?></td><td><span class="status-pill"><?= e(ucfirst($user['status'])) ?></span></td><td><div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="account_password.php?id=<?= e((string) $user['id']) ?>">Change Password</a><a class="btn btn-sm btn-outline-primary" href="account_role.php?id=<?= e((string) $user['id']) ?>">Assign Role</a></div></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                        <ul class="resident-cards"><?php foreach ($staff as $user): ?><li class="resident-card"><div class="resident-card-top"><strong><?= e($user['name']) ?></strong><span class="status-pill"><?= e(ucfirst($user['status'])) ?></span></div><p><?= e(accounts_role_label($user['role'])) ?> · <?= e($user['email']) ?></p><div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="account_password.php?id=<?= e((string) $user['id']) ?>">Change Password</a><a class="btn btn-sm btn-outline-primary" href="account_role.php?id=<?= e((string) $user['id']) ?>">Assign Role</a></div></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <p class="resident-static profile-admin-note">To change your name or email, contact the System Administrator. You can change your own password with Change Password.</p>
            <?php endif; ?>
        </main>
        <?php require __DIR__ . '/layout/footer.php'; ?>
    </div>
</div>
