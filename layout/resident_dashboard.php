<?php
// Resident dashboard (included by dashboard.php for the 'resident' role). Only the signed-in resident's own records are read.
require_once __DIR__ . '/../includes/documents.php';
require_once __DIR__ . '/../includes/notifications.php';

$dashboard_announcements ??= null; // provided by dashboard.php (resident-visible announcements)
$connection = db();
$profile = documents_resident_profile($connection);
$membership = $profile ? residents_current_membership($connection, (int) $profile['id']) : null;
$requests = $profile ? documents_for_resident($connection, (int) $profile['id'], 50) : [];
$request_counts = ['pending' => 0, 'approved' => 0, 'released' => 0, 'rejected' => 0];
foreach ($requests as $request) $request_counts[$request['status']] = ($request_counts[$request['status']] ?? 0) + 1;
$recent_requests = array_slice($requests, 0, 4);
$first_name = $profile['first_name'] ?? explode(' ', current_user()['name'] ?? 'Resident')[0];
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$age = $profile ? residents_age($profile['birth_date']) : null;
$is_head = $membership !== null && (int) $membership['household_head_resident_id'] === (int) $profile['id'];
require __DIR__ . '/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/topbar.php'; ?>
<main class="content">
    <section class="office-hero" aria-label="Resident Portal">
        <div class="office-hero-copy">
            <span class="office-hero-badge"><?= icon_svg('users') ?>Resident Portal</span>
            <h1><?= e($greeting) ?>, <?= e($first_name) ?>.</h1>
            <p><strong>Barangay San Jose</strong> · Request documents, track your requests, and stay updated with community announcements.</p>
        </div>
        <div class="office-hero-meta"><a class="btn resident-hero-cta" href="resident_documents.php"><?= icon_svg('file') ?>Request a Document</a></div>
    </section>

    <?php if ($profile === null): ?>
        <div class="dashboard-status warning" role="status">Your account is not linked to a resident profile yet. Please visit the Barangay Hall so staff can link your account; you can still read announcements meanwhile.</div>
    <?php endif; ?>

    <section class="stats-grid resident-stats" aria-label="My document requests">
        <a class="stat-card stat-card-link" href="resident_documents.php"><div class="stat-top"><span>Pending Review</span><span class="stat-icon"><?= icon_svg('history') ?></span></div><div class="stat-value"><?= e((string) $request_counts['pending']) ?></div><div class="stat-foot">Waiting for the Secretary</div></a>
        <a class="stat-card stat-card-link" href="resident_documents.php"><div class="stat-top"><span>Approved</span><span class="stat-icon"><?= icon_svg('file') ?></span></div><div class="stat-value"><?= e((string) $request_counts['approved']) ?></div><div class="stat-foot">Being prepared by the barangay</div></a>
        <a class="stat-card stat-card-link" href="resident_documents.php"><div class="stat-top"><span>Released</span><span class="stat-icon"><?= icon_svg('badge') ?></span></div><div class="stat-value"><?= e((string) $request_counts['released']) ?></div><div class="stat-foot">Completed requests</div></a>
        <a class="stat-card stat-card-link" href="notifications.php"><div class="stat-top"><span>Unread Notifications</span><span class="stat-icon"><?= icon_svg('megaphone') ?></span></div><div class="stat-value"><?= e((string) unread_notifications_count()) ?></div><div class="stat-foot">Announcements and case updates</div></a>
    </section>

    <div class="resident-portal-grid">
        <section class="dashboard-panel" aria-labelledby="profile-heading">
            <div class="panel-heading"><h2 id="profile-heading">My Profile</h2></div>
            <?php if ($profile === null): ?>
                <p class="resident-pending">No resident profile is linked to this account.</p>
            <?php else: ?>
                <dl class="activity-detail-list">
                    <div><dt>Full name</dt><dd><?= e(residents_full_name($profile)) ?></dd></div>
                    <div><dt>Age</dt><dd><?= $age === null ? 'Not recorded' : e((string) $age) ?></dd></div>
                    <div><dt>Purok</dt><dd><?= e($profile['purok']) ?></dd></div>
                    <div><dt>Address</dt><dd class="resident-wrap"><?= e($profile['address']) ?></dd></div>
                    <div><dt>Resident status</dt><dd><?= residents_status_badge($profile['status']) ?></dd></div>
                    <div><dt>Household</dt><dd><?= $membership ? e($membership['household_no']) . ($is_head ? ' · Household Head' : '') : '<span class="activity-detail-muted">Not assigned</span>' ?></dd></div>
                </dl>
                <p class="resident-static">To correct your information, please visit the Barangay Hall.</p>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel" aria-labelledby="requests-heading">
            <div class="panel-heading"><h2 id="requests-heading">Recent Requests</h2><a href="resident_documents.php">View All</a></div>
            <?php if ($recent_requests === []): ?>
                <div class="dashboard-empty-state">No document requests yet.<a class="announcement-action" href="resident_documents.php">Request a Document</a></div>
            <?php else: ?>
                <ul class="document-request-list is-compact">
                    <?php foreach ($recent_requests as $request): ?>
                        <li><div class="document-request-top"><strong><?= e($request['document_type']) ?></strong><?= documents_status_badge($request['status']) ?></div><span class="document-request-ref"><?= e($request['reference_code']) ?> · <?= e(documents_format_datetime($request['requested_at'])) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <section class="quick-actions" aria-labelledby="resident-actions-heading">
        <div class="section-heading"><div><h2 id="resident-actions-heading">Quick Actions</h2><p>Common services available online.</p></div></div>
        <div class="quick-actions-grid">
            <?php foreach (array_slice(array_keys(documents_types()), 0, 3) as $type): ?>
                <a class="quick-action" href="resident_documents.php?type=<?= e(rawurlencode($type)) ?>"><span class="quick-action-icon"><?= icon_svg('file') ?></span><span><?= e($type) ?></span></a>
            <?php endforeach; ?>
            <a class="quick-action" href="announcements.php"><span class="quick-action-icon"><?= icon_svg('megaphone') ?></span><span>Announcements</span></a>
            <a class="quick-action" href="notifications.php"><span class="quick-action-icon"><?= icon_svg('history') ?></span><span>Notifications</span></a>
        </div>
    </section>

    <section class="dashboard-panel announcements-panel" aria-labelledby="resident-news-heading">
        <div class="panel-heading"><h2 id="resident-news-heading">Latest Announcements</h2><a href="announcements.php">View All</a></div>
        <?php if ($dashboard_announcements === null): ?>
            <div class="dashboard-empty-state">Announcements are temporarily unavailable.</div>
        <?php elseif ($dashboard_announcements['published'] === []): ?>
            <div class="dashboard-empty-state">No announcements available.</div>
        <?php else: ?>
            <div class="announcement-list"><?php foreach ($dashboard_announcements['published'] as $announcement): ?><a class="announcement-item" href="announcement_view.php?id=<?= e((string) $announcement['id']) ?>"><strong><?= e($announcement['title']) ?></strong><span><?= e($announcement['preview']) ?></span><time><?= e(documents_format_datetime($announcement['date'])) ?></time></a><?php endforeach; ?></div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/footer.php'; ?></div></div>
