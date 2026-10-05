<?php
// "My Profile" and "Recent Requests" on the dashboard of staff who are residents too (Health Workers: My Household and
// Document Requests in their menu). Included by dashboard.php. Only the signed-in user's own linked profile is read.
require_once __DIR__ . '/../includes/documents.php';

$connection = db();
$my_profile = documents_resident_profile($connection);
$my_membership = $my_profile ? residents_current_membership($connection, (int) $my_profile['id']) : null;
$my_requests = $my_profile ? array_slice(documents_for_resident($connection, (int) $my_profile['id'], 4), 0, 4) : [];
$my_age = $my_profile ? residents_age($my_profile['birth_date']) : null;
$my_is_head = $my_membership !== null && (int) $my_membership['household_head_resident_id'] === (int) $my_profile['id'];
?>
<div class="resident-portal-grid">
    <section class="dashboard-panel" aria-labelledby="my-profile-heading">
        <div class="panel-heading"><h2 id="my-profile-heading">My Profile</h2><a href="my_household.php">My Household</a></div>
        <?php if ($my_profile === null): ?>
            <p class="resident-pending">Your account is not linked to a resident profile yet. Ask the System Administrator to fill in your Resident Details (User Management → Edit), or visit the Barangay Hall.</p>
        <?php else: ?>
            <dl class="activity-detail-list">
                <div><dt>Full name</dt><dd><?= e(residents_full_name($my_profile)) ?></dd></div>
                <div><dt>Age</dt><dd><?= $my_age === null ? 'Not recorded' : e((string) $my_age) ?></dd></div>
                <div><dt>Purok</dt><dd><?= e(residents_purok_label((string) $my_profile['purok'])) ?></dd></div>
                <div><dt>Address</dt><dd class="resident-wrap"><?= e($my_profile['address']) ?></dd></div>
                <div><dt>Household</dt><dd><?= $my_membership ? e($my_membership['household_no']) . ($my_is_head ? ' · Household Head' : '') : '<span class="activity-detail-muted">Not assigned</span>' ?></dd></div>
            </dl>
            <p class="resident-static">To correct your information, please visit the Barangay Hall.</p>
        <?php endif; ?>
    </section>
    <?php if (can_access_navigation('my_documents')): ?>
    <section class="dashboard-panel" aria-labelledby="my-requests-heading">
        <div class="panel-heading"><h2 id="my-requests-heading">My Document Requests</h2><a href="resident_documents.php">View All</a></div>
        <?php if ($my_requests === []): ?>
            <div class="dashboard-empty-state">No document requests yet.<?php if ($my_profile !== null): ?><a class="announcement-action" href="resident_documents.php">Request a Document</a><?php endif; ?></div>
        <?php else: ?>
            <ul class="document-request-list is-compact">
                <?php foreach ($my_requests as $request): ?>
                    <li><a class="activity-detail-link" href="document_view.php?id=<?= e((string) $request['id']) ?>"><div class="document-request-top"><strong><?= e($request['document_type']) ?></strong><?= documents_status_badge($request['status']) ?></div></a><span class="document-request-ref"><?= e($request['reference_code']) ?> · <?= e(documents_format_datetime($request['requested_at'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
