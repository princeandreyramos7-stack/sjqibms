<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/users.php';
require_once __DIR__ . '/includes/live_search.php';
users_require_admin();
$connection = db();

// User Management: every account from the users table (no sample data). Search by name or email, filter by role and
// status, 15 per page. Actions follow users_allowed_actions() and are enforced again on the server.
$per_page = 15;
$state = users_list_state($_GET);
[$where, $params] = users_list_where($state);
$count = $connection->prepare("SELECT COUNT(*) FROM users u WHERE $where");
$count->execute($params);
$total = (int) $count->fetchColumn();
$total_pages = max(1, (int) ceil($total / $per_page));
$page = max(1, min($total_pages, $state['page']));
$statement = $connection->prepare("SELECT u.id, u.name, u.email, u.username, u.role, u.status, u.resident_id, r.status AS resident_status, u.last_login_at, u.created_at FROM users u LEFT JOIN residents r ON r.id = u.resident_id WHERE $where ORDER BY FIELD(u.role, 'super_admin', 'punong_barangay', 'secretary', 'treasurer', 'official', 'health_worker', 'resident'), u.name, u.id LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) $statement->bindValue($key, $value);
$statement->bindValue('limit', $per_page, PDO::PARAM_INT);
$statement->bindValue('offset', ($page - 1) * $per_page, PDO::PARAM_INT);
$statement->execute();
$users = $statement->fetchAll();
$counts = $connection->query("SELECT COUNT(*) AS total, SUM(status = 'active') AS active, SUM(status = 'pending') AS pending, SUM(status = 'suspended') AS suspended FROM users")->fetch();
$first = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last = min($total, $page * $per_page);
$url = static fn (array $changes = []): string => 'users.php' . (($q = users_query_string(array_merge($state, ['page' => $page], $changes))) !== '' ? '?' . $q : '');
$return = users_query_string(array_merge($state, ['page' => $page]));

$actions = static function (array $user) use ($return): string {
    $can = users_allowed_actions($user);
    $id = e((string) $user['id']);
    $html = '<div class="management-actions users-actions">';
    if ($can['self']) $html .= '<a class="btn btn-sm btn-outline-primary" href="account_edit.php">Edit Profile</a><a class="btn btn-sm btn-outline-primary" href="account_password.php?from=users">Password</a>';
    if ($can['edit']) $html .= '<a class="btn btn-sm btn-outline-primary" href="user_form.php?id=' . $id . '">Edit</a>';
    if ($can['role']) $html .= '<a class="btn btn-sm btn-outline-primary" href="account_role.php?id=' . $id . '&amp;from=users">Role</a>';
    if ($can['password'] && !$can['self']) $html .= '<a class="btn btn-sm btn-outline-primary" href="account_password.php?id=' . $id . '&amp;from=users">Password</a>';
    // Self-registered residents: review the resident profile before activating.
    if ($user['role'] === 'resident' && $user['resident_id'] !== null && can_access_navigation('residents')) $html .= '<a class="btn btn-sm btn-outline-primary" href="resident_view.php?id=' . e((string) $user['resident_id']) . '">Resident Profile</a>';
    $activate_message = ($user['resident_status'] ?? null) === 'pending' ? 'Review the resident profile first. Activating also makes the Pending resident profile Active, and the resident can then sign in to the Resident Portal.' : 'The account holder can sign in again through their portal.';
    foreach (['suspend' => ['Suspend', 'btn-outline-danger', 'Suspend this account?', 'The account can no longer sign in until it is reactivated. Nothing is deleted.', true], 'activate' => [$user['status'] === 'pending' ? 'Activate' : 'Reactivate', 'btn-outline-primary', 'Activate this account?', $activate_message, false]] as $action => [$label, $class, $heading, $message, $danger]) {
        if (!$can[$action]) continue;
        $html .= '<form method="post" action="user_action.php" class="doc-action-form">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="return" value="' . e($return) . '"><button class="btn btn-sm ' . $class . '" type="submit" data-form-confirm="custom" data-dialog-heading="' . e($heading) . '" data-dialog-message="' . e($user['name'] . ' (' . users_login_label($user) . '). ' . $message) . '" data-dialog-confirm="' . e($label) . '" data-dialog-dismiss="Cancel"' . ($danger ? ' data-dialog-danger="true"' : '') . '>' . e($label) . '</button></form>';
    }
    return $html . '</div>';
};

$render_results = static function () use ($users, $total, $first, $last, $page, $total_pages, $url, $actions, $state): void {
    if ($users === []): ?>
        <div class="dashboard-empty-state"><?= $state['q'] !== '' || $state['role'] !== '' || $state['status'] !== '' ? 'No matching accounts found.' : 'No accounts found.' ?></div>
    <?php else: ?>
        <p class="activity-history-meta">Showing <?= e((string) $first) ?>–<?= e((string) $last) ?> of <?= e((string) $total) ?> account<?= $total === 1 ? '' : 's' ?></p>
        <div class="resident-table-wrap">
            <table class="resident-table users-table">
                <thead><tr><th scope="col">Name</th><th scope="col">Email / Username</th><th scope="col">Role</th><th scope="col">Last Login</th><th scope="col">Created</th><th scope="col">Status</th><th scope="col" class="users-actions-head">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="resident-name"><?= e($user['name']) ?><?= (int) $user['id'] === (int) current_user()['id'] ? ' <span class="users-you">You</span>' : '' ?></td>
                        <td class="users-email"><?= e(users_login_label($user)) ?></td>
                        <td><?= e(accounts_role_label($user['role'])) ?></td>
                        <td class="users-nowrap"><?= $user['last_login_at'] ? e(users_format_datetime($user['last_login_at'])) : '<span class="activity-detail-muted">Never</span>' ?></td>
                        <td class="users-nowrap"><?= e(date('M j, Y', strtotime($user['created_at']))) ?></td>
                        <td><?= users_status_badge($user['status']) ?></td>
                        <td><?= $actions($user) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($users as $user): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><?= e($user['name']) ?></strong><?= users_status_badge($user['status']) ?></div>
                    <p><?= e(accounts_role_label($user['role'])) ?> · <?= e(users_login_label($user)) ?></p>
                    <p>Last login: <?= $user['last_login_at'] ? e(users_format_datetime($user['last_login_at'])) : 'Never' ?></p>
                    <?= $actions($user) ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <nav class="activity-pagination" aria-label="Accounts pagination">
                <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page - 1])) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page + 1])) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif;
};
if (live_search_is_request()) live_search_respond($render_results);

$page_title = 'User Management'; $active_page = 'users';
$page_styles = ['assets/css/users.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>User Management</h1><p>System accounts, roles, and account status.</p></div>
        <div class="resident-detail-actions"><a class="btn btn-primary announcements-new-btn" href="user_form.php">Add Staff Account</a></div>
    </div>
    <?php if ($success = flash('users_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('users_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <div class="users-cards" role="list">
        <?php foreach (['total' => ['All accounts', ''], 'active' => ['Active', 'active'], 'pending' => ['Pending', 'pending'], 'suspended' => ['Suspended', 'suspended']] as $key => [$label, $status]): ?>
            <a class="users-card<?= $state['status'] === $status ? ' is-selected' : '' ?>" role="listitem" href="<?= e($url(['status' => $status, 'page' => 1])) ?>"><span class="users-card-value"><?= e((string) (int) $counts[$key]) ?></span><span class="users-card-label"><?= e($label) ?></span></a>
        <?php endforeach; ?>
    </div>
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters" method="get" action="users.php" data-live-search data-live-target="#users-results">
            <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="users-search">Search</label><input class="activity-filter-input" type="search" id="users-search" name="q" value="<?= e($state['q']) ?>" maxlength="100" placeholder="Name, email or username" autocomplete="off" data-live-query></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="users-role">Role</label><select class="activity-filter-select" id="users-role" name="role"><option value="">All</option><?php foreach (users_all_roles() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="users-status">Status</label><select class="activity-filter-select" id="users-status" name="status"><option value="">All</option><?php foreach (users_statuses() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="users.php" data-live-reset>Reset</a></div>
        </form>
        <div id="users-results" class="live-search-results"><?php $render_results(); ?></div>
        <p class="document-info-note">Resident accounts are created through resident registration and are linked to a resident profile; here they can be suspended or reactivated. Staff sign in through the portal of their role.</p>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
