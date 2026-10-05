<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/registrations.php';
require_once __DIR__ . '/includes/family_requests.php';
require_once __DIR__ . '/includes/live_search.php';
reg_require();
$connection = db();

// Resident Registrations: online Resident Portal sign-ups from registration_applications (no sample data). Pending ones
// first; search by name or mobile number; 15 per page. Approve / Reject is on the registration's own page.
$per_page = 15;
$state = reg_list_state($_GET);
[$where, $params] = reg_list_where($state);
$count = $connection->prepare("SELECT COUNT(*) FROM registration_applications a WHERE $where");
$count->execute($params);
$total = (int) $count->fetchColumn();
$total_pages = max(1, (int) ceil($total / $per_page));
$page = max(1, min($total_pages, $state['page']));
$statement = $connection->prepare("SELECT a.id, a.first_name, a.middle_name, a.last_name, a.suffix, a.purok, a.contact_number, a.status, a.submitted_at, a.decided_at, a.user_id, a.resident_id, u.email AS user_email, u.username, r.user_id AS resident_user_id FROM registration_applications a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN residents r ON r.id = a.resident_id WHERE $where ORDER BY a.status IN ('submitted', 'verified', 'awaiting_final_approval') DESC, a.submitted_at DESC, a.id DESC LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) $statement->bindValue($key, $value);
$statement->bindValue('limit', $per_page, PDO::PARAM_INT);
$statement->bindValue('offset', ($page - 1) * $per_page, PDO::PARAM_INT);
$statement->execute();
$rows = $statement->fetchAll();
$counts = reg_counts($connection);
$first = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last = min($total, $page * $per_page);
$query = static fn (array $s): string => http_build_query(array_filter(['q' => $s['q'], 'status' => $s['status'] === 'pending' ? '' : $s['status'], 'page' => ($s['page'] ?? 1) > 1 ? $s['page'] : ''], static fn ($v): bool => $v !== ''));
$url = static fn (array $changes = []): string => 'registrations.php' . (($q = $query(array_merge($state, ['page' => $page], $changes))) !== '' ? '?' . $q : '');

$render_results = static function () use ($rows, $total, $first, $last, $page, $total_pages, $url, $state): void {
    if ($rows === []): ?>
        <div class="dashboard-empty-state"><?= $state['q'] !== '' ? 'No matching registrations found.' : ($state['status'] === 'pending' ? 'No registrations are waiting for approval.' : 'No registrations found.') ?></div>
    <?php else: ?>
        <p class="activity-history-meta">Showing <?= e((string) $first) ?>–<?= e((string) $last) ?> of <?= e((string) $total) ?> registration<?= $total === 1 ? '' : 's' ?></p>
        <div class="resident-table-wrap">
            <table class="resident-table">
                <thead><tr><th scope="col">Reference</th><th scope="col">Applicant</th><th scope="col">Purok</th><th scope="col">Mobile</th><th scope="col">Sign-in</th><th scope="col">Submitted</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="users-nowrap"><?= e(reg_reference($row)) ?></td>
                        <td class="resident-name"><?= e(residents_full_name($row)) ?><?= reg_created_profile($row) ? '' : ' <span class="resident-tag">Existing record</span>' ?></td>
                        <td><?= e(residents_purok_label($row['purok'])) ?></td>
                        <td class="users-nowrap"><?= e((string) $row['contact_number']) ?></td>
                        <td><?= e(reg_login_label($row)) ?></td>
                        <td class="users-nowrap"><?= e(reg_format_datetime($row['submitted_at'])) ?></td>
                        <td><?= reg_status_badge($row['status']) ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="registration_view.php?id=<?= e((string) $row['id']) ?>"><?= in_array($row['status'], reg_pending_statuses(), true) ? 'Review' : 'View' ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($rows as $row): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><?= e(residents_full_name($row)) ?></strong><?= reg_status_badge($row['status']) ?></div>
                    <p><?= e(reg_reference($row)) ?> · <?= e(residents_purok_label($row['purok'])) ?> · <?= e((string) $row['contact_number']) ?></p>
                    <p>Submitted <?= e(reg_format_datetime($row['submitted_at'])) ?></p>
                    <div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="registration_view.php?id=<?= e((string) $row['id']) ?>"><?= in_array($row['status'], reg_pending_statuses(), true) ? 'Review' : 'View' ?></a></div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <nav class="activity-pagination" aria-label="Registrations pagination">
                <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page - 1])) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page + 1])) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif;
};
if (live_search_is_request()) live_search_respond($render_results);

// Family members added by residents in the Resident Portal (household_member_requests): pending first, 15 per page (?fp=).
$family_ready = fam_ready($connection);
$family = []; $family_counts = ['pending' => 0, 'all' => 0]; $family_pages = 1; $family_page = 1;
if ($family_ready) {
    $family_counts = fam_counts($connection);
    $family_pages = max(1, (int) ceil((int) $family_counts['all'] / $per_page));
    $family_page = max(1, min($family_pages, (int) ($_GET['fp'] ?? 1)));
    $statement = $connection->prepare("SELECT q.id, q.relationship_to_requester, q.status, q.created_at, m.first_name, m.middle_name, m.last_name, m.suffix, m.birth_date, m.purok, u.name AS requester_name FROM household_member_requests q LEFT JOIN residents m ON m.id = q.resident_id LEFT JOIN users u ON u.id = q.requested_by ORDER BY q.status = 'pending' DESC, q.created_at DESC, q.id DESC LIMIT :limit OFFSET :offset");
    $statement->bindValue('limit', $per_page, PDO::PARAM_INT);
    $statement->bindValue('offset', ($family_page - 1) * $per_page, PDO::PARAM_INT);
    $statement->execute();
    $family = $statement->fetchAll();
}
$family_url = static function (int $target) use ($url): string {
    $base = $url();
    return $base . ($target > 1 ? (str_contains($base, '?') ? '&' : '?') . 'fp=' . $target : '') . '#family-members';
};

$page_title = 'Resident Registrations'; $active_page = 'registrations';
$page_styles = ['assets/css/users.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Resident Registrations</h1><p>Online Resident Portal sign-ups. Review each one, then approve or reject it.</p></div>
    </div>
    <?php if ($success = flash('registrations_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('registrations_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <div class="users-cards" role="list">
        <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All registrations'] as $key => $label): ?>
            <a class="users-card<?= $state['status'] === $key ? ' is-selected' : '' ?>" role="listitem" href="<?= e($url(['status' => $key, 'page' => 1])) ?>"><span class="users-card-value"><?= e((string) (int) $counts[$key]) ?></span><span class="users-card-label"><?= e($label) ?></span></a>
        <?php endforeach; ?>
    </div>
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters" method="get" action="registrations.php" data-live-search data-live-target="#registrations-results">
            <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="registrations-search">Search</label><input class="activity-filter-input" type="search" id="registrations-search" name="q" value="<?= e($state['q']) ?>" maxlength="100" placeholder="Name or mobile number" autocomplete="off" data-live-query></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="registrations-status">Status</label><select class="activity-filter-select" id="registrations-status" name="status"><?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="registrations.php" data-live-reset>Reset</a></div>
        </form>
        <div id="registrations-results" class="live-search-results"><?php $render_results(); ?></div>
        <p class="document-info-note">Approving activates the Resident Portal account and a Pending resident profile. Rejecting keeps every record: the account is suspended and a resident profile created by the sign-up becomes Inactive. "Existing record" means the sign-up matched a resident profile that was already in the system; that profile is not changed.</p>
    </section>

    <?php if ($family_ready): ?>
    <section class="dashboard-panel resident-list-panel" id="family-members" aria-labelledby="family-members-title" style="margin-top: 24px;">
        <div class="panel-heading"><h2 id="family-members-title">Family members added by residents <span class="resident-tag"><?= e((string) (int) $family_counts['pending']) ?> pending</span></h2></div>
        <p class="activity-history-meta">Household members without their own account (children, elders) that residents added in My Household. Approving makes the profile Active and adds it to the resident's household.</p>
        <?php if ($family === []): ?>
            <div class="dashboard-empty-state">No family members have been added by residents yet.</div>
        <?php else: ?>
            <div class="resident-table-wrap">
                <table class="resident-table">
                    <thead><tr><th scope="col">Reference</th><th scope="col">Family member</th><th scope="col">Age</th><th scope="col">Added by</th><th scope="col">Relationship</th><th scope="col">Submitted</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($family as $row): ?>
                        <tr>
                            <td class="users-nowrap"><?= e(fam_reference($row)) ?></td>
                            <td class="resident-name"><?= e(residents_full_name($row)) ?></td>
                            <td><?= e((string) (residents_age($row['birth_date']) ?? '')) ?></td>
                            <td><?= e((string) $row['requester_name']) ?></td>
                            <td><?= e($row['relationship_to_requester']) ?></td>
                            <td class="users-nowrap"><?= e(reg_format_datetime($row['created_at'])) ?></td>
                            <td><?= fam_status_badge($row['status']) ?></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="family_request_view.php?id=<?= e((string) $row['id']) ?>"><?= $row['status'] === 'pending' ? 'Review' : 'View' ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="resident-cards">
                <?php foreach ($family as $row): ?>
                    <li class="resident-card">
                        <div class="resident-card-top"><strong><?= e(residents_full_name($row)) ?></strong><?= fam_status_badge($row['status']) ?></div>
                        <p><?= e(fam_reference($row)) ?> · <?= e($row['relationship_to_requester']) ?> of <?= e((string) $row['requester_name']) ?></p>
                        <div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="family_request_view.php?id=<?= e((string) $row['id']) ?>"><?= $row['status'] === 'pending' ? 'Review' : 'View' ?></a></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($family_pages > 1): ?>
                <nav class="activity-pagination" aria-label="Family members pagination">
                    <?php if ($family_page > 1): ?><a class="activity-page-btn" href="<?= e($family_url($family_page - 1)) ?>">&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                    <span class="activity-page-info">Page <?= e((string) $family_page) ?> of <?= e((string) $family_pages) ?></span>
                    <?php if ($family_page < $family_pages): ?><a class="activity-page-btn" href="<?= e($family_url($family_page + 1)) ?>">Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
