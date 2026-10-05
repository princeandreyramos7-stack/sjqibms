<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/residents.php';
require_once __DIR__ . '/includes/documents.php';
require_once __DIR__ . '/includes/health.php';
residents_require_view();
$connection = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$resident = $id ? residents_find($connection, $id) : null;
if (!$resident || !residents_in_scope($connection, $resident['purok'])) { http_response_code(404); exit('Resident not found.'); }   // safeguard for Health Workers (assigned Puroks only)
$membership = residents_current_membership($connection, $id);
$history = residents_membership_history($connection, $id);
$age = residents_age($resident['birth_date']);
$can_manage = residents_can_manage();
$is_head = $membership !== null && (int) $membership['household_head_resident_id'] === $id;
$not_recorded = '<span class="activity-detail-muted">Not recorded</span>';
// Document requests and issued documents of this resident: shown only to offices that process documents.
$can_see_documents = documents_can_process();
$documents = $can_see_documents ? documents_for_resident($connection, $id, 100) : [];
$issued_count = count(array_filter($documents, static fn (array $d): bool => $d['status'] === 'released'));
// Health records of this resident: shown only to the Health module's role (Health Worker; health records are
// confidential). Due today / overdue follows the same rule as the Health list (health_due_sql()).
$can_see_health = health_can_manage() && health_ready($connection);
$health_records = [];
if ($can_see_health) {
    $statement = $connection->prepare('SELECT h.id, h.record_no, h.service, h.service_details, h.health_worker, h.service_date, h.status, h.follow_up_date, h.archived_at, h.resident_id, ' . health_due_sql('h') . ' AS is_due FROM health_records h WHERE h.resident_id = :id AND h.archived_at IS NULL ORDER BY h.service_date DESC, h.id DESC LIMIT 100');
    $statement->execute(['id' => $id]);
    $health_records = $statement->fetchAll();
}
$page_title = 'Resident Details'; $active_page = 'residents';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($success = flash('resident_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
<article class="dashboard-panel resident-detail">
    <div class="page-heading">
        <div>
            <a class="announcement-back" href="residents.php"><span aria-hidden="true">&larr;</span> Back</a>
            <span class="eyebrow">Resident #<?= e((string) $resident['id']) ?></span>
            <h1><?= e(residents_full_name($resident)) ?></h1>
            <p><?= residents_status_badge($resident['status']) ?> <span class="resident-detail-meta">Profile created <?= e(residents_format_date($resident['created_at'])) ?></span></p>
        </div>
        <?php if ($can_manage): ?>
            <div class="resident-detail-actions">
                <a class="btn announcement-edit-btn" href="resident_form.php?id=<?= e((string) $resident['id']) ?>">Edit Resident</a>
                <a class="btn btn-light resident-action-btn" href="resident_status.php?id=<?= e((string) $resident['id']) ?>">Change Status</a>
                <a class="btn btn-light resident-action-btn" href="resident_household.php?id=<?= e((string) $resident['id']) ?>">Manage Household</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="resident-detail-grid">
        <section class="resident-detail-section">
            <h2>Personal Information</h2>
            <dl class="activity-detail-list">
                <div><dt>Full name</dt><dd><?= e(residents_full_name($resident)) ?></dd></div>
                <div><dt>Birthdate</dt><dd><?= $resident['birth_date'] ? e(residents_format_date($resident['birth_date'])) : $not_recorded ?></dd></div>
                <div><dt>Age</dt><dd><?= $age === null ? '<span class="activity-detail-muted">Unknown</span>' : e((string) $age) . ($age >= 60 ? ' <span class="resident-tag">Senior Citizen</span>' : '') ?></dd></div>
                <div><dt>Sex</dt><dd><?= isset(residents_sex_labels()[$resident['sex']]) ? e(residents_sex_labels()[$resident['sex']]) : '<span class="activity-detail-muted">Unspecified</span>' ?></dd></div>
                <div><dt>Civil status</dt><dd><?= isset(residents_civil_status_labels()[$resident['civil_status']]) ? e(residents_civil_status_labels()[$resident['civil_status']]) : $not_recorded ?></dd></div>
                <?php if (residents_sector_ready($connection)): $sector_tags = residents_sector_tags($resident); ?><div><dt>Sector</dt><dd><?= $sector_tags !== '' ? ltrim($sector_tags) : '<span class="activity-detail-muted">None</span>' ?></dd></div><?php endif; ?>
            </dl>
        </section>

        <section class="resident-detail-section">
            <h2>Contact and Address</h2>
            <dl class="activity-detail-list">
                <div><dt>Contact number</dt><dd><?= $resident['contact_number'] ? e($resident['contact_number']) : $not_recorded ?></dd></div>
                <div><dt>Address</dt><dd class="resident-wrap"><?= nl2br(e($resident['address'])) ?></dd></div>
                <div><dt>Purok</dt><dd><?= e(residents_purok_label($resident['purok'])) ?></dd></div>
                <?php if (array_key_exists('residency_start_year', $resident)): $years = residents_years_of_residency($resident['residency_start_year']); ?>
                    <div><dt>Years of residency</dt><dd><?= $years === null ? $not_recorded : e($years . ' year' . ($years === 1 ? '' : 's') . ' (since ' . $resident['residency_start_year'] . ')') ?></dd></div>
                <?php endif; ?>
            </dl>
        </section>

        <section class="resident-detail-section">
            <h2>Household Information</h2>
            <?php if ($membership === null): ?>
                <p class="resident-pending">Household assignment pending.</p>
            <?php else: ?>
                <dl class="activity-detail-list">
                    <div><dt>Household number</dt><dd><?= e($membership['household_no']) ?></dd></div>
                    <div><dt>Household head</dt><dd><?= $membership['household_head_resident_id'] ? ($is_head ? 'This resident' : '<a class="activity-detail-link" href="resident_view.php?id=' . e((string) $membership['household_head_resident_id']) . '">' . e(residents_full_name($membership)) . '</a>') : '<span class="activity-detail-muted">No designated head</span>' ?></dd></div>
                    <div><dt>Relationship to head</dt><dd><?= $is_head ? 'Household Head' : ($membership['relationship_to_head'] ? e($membership['relationship_to_head']) : $not_recorded) ?></dd></div>
                    <div><dt>Member since</dt><dd><?= $membership['joined_at'] ? e(residents_format_date($membership['joined_at'])) : $not_recorded ?></dd></div>
                </dl>
            <?php endif; ?>
            <?php if ($history !== []): ?>
                <h3 class="resident-subheading">Previous households</h3>
                <ul class="resident-history">
                    <?php foreach ($history as $past): ?><li><strong><?= e($past['household_no']) ?></strong><span><?= $past['is_primary'] ? '' : 'Secondary · ' ?><?= e($past['joined_at'] ? residents_format_date($past['joined_at']) : 'Unknown') ?> – <?= e($past['left_at'] ? residents_format_date($past['left_at']) : 'Open') ?></span></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="resident-detail-section">
            <h2>Resident Status</h2>
            <dl class="activity-detail-list">
                <div><dt>Current status</dt><dd><?= residents_status_badge($resident['status']) ?></dd></div>
                <div><dt>Linked user account</dt><dd><?= $resident['user_id'] ? 'Linked' : '<span class="activity-detail-muted">None</span>' ?></dd></div>
                <div><dt>Last updated</dt><dd><?= e(residents_format_date($resident['updated_at'])) ?></dd></div>
            </dl>
        </section>
    </div>

    <?php if ($can_see_documents): ?>
        <section class="resident-detail-section resident-documents" aria-labelledby="resident-documents-heading">
            <div class="resident-documents-head">
                <div>
                    <h2 id="resident-documents-heading">Documents</h2>
                    <p class="resident-detail-meta"><?= e((string) count($documents)) ?> request<?= count($documents) === 1 ? '' : 's' ?> · <?= e((string) $issued_count) ?> issued (released)</p>
                </div>
                <?php if ($resident['status'] === 'active' && role_can('documents.request.create')): ?><a class="btn btn-sm btn-outline-primary" href="document_request_form.php?resident=<?= e((string) $resident['id']) ?>">New Request</a><?php endif; ?>
            </div>
            <?php if ($documents === []): ?>
                <p class="resident-static">No documents have been requested or issued for this resident.</p>
            <?php else: ?>
                <div class="resident-table-wrap">
                    <table class="resident-table">
                        <thead><tr><th scope="col">Reference</th><th scope="col">Document</th><th scope="col">Purpose</th><th scope="col">Requested</th><th scope="col">Issued</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                        <tbody>
                        <?php foreach ($documents as $document): ?>
                            <tr>
                                <td class="resident-id"><?= e($document['reference_code']) ?></td>
                                <td class="resident-name"><?= e($document['document_type']) ?></td>
                                <td class="resident-wrap"><?= e($document['purpose']) ?></td>
                                <td><?= e(documents_format_datetime($document['requested_at'])) ?></td>
                                <td><?= $document['status'] === 'released' && $document['released_at'] ? e(documents_format_datetime($document['released_at'])) : '<span class="activity-detail-muted">—</span>' ?></td>
                                <td><?= documents_status_badge($document['status']) ?></td>
                                <td><a class="btn btn-sm btn-outline-primary" href="document_view.php?id=<?= e((string) $document['id']) ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <ul class="resident-cards">
                    <?php foreach ($documents as $document): ?>
                        <li class="resident-card">
                            <div class="resident-card-top"><strong><?= e($document['document_type']) ?></strong><?= documents_status_badge($document['status']) ?></div>
                            <p><?= e($document['reference_code']) ?> · Requested <?= e(documents_format_datetime($document['requested_at'])) ?></p>
                            <?php if ($document['status'] === 'released' && $document['released_at']): ?><p>Issued <?= e(documents_format_datetime($document['released_at'])) ?></p><?php endif; ?>
                            <p><?= e($document['purpose']) ?></p>
                            <div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="document_view.php?id=<?= e((string) $document['id']) ?>">View</a></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($can_see_health): ?>
        <section class="resident-detail-section resident-documents" aria-labelledby="resident-health-heading">
            <div class="resident-documents-head">
                <div>
                    <h2 id="resident-health-heading">Health Records</h2>
                    <p class="resident-detail-meta"><?= e((string) count($health_records)) ?> record<?= count($health_records) === 1 ? '' : 's' ?><?php $due_here = count(array_filter($health_records, 'health_is_due')); ?><?= $due_here > 0 ? ' · <strong>' . e((string) $due_here) . ' due today or overdue</strong>' : '' ?></p>
                </div>
                <?php if ($resident['status'] === 'active'): ?><a class="btn btn-sm btn-outline-primary" href="health_form.php?resident=<?= e((string) $resident['id']) ?>">Add Health Record</a><?php endif; ?>
            </div>
            <?php if ($health_records === []): ?>
                <p class="resident-static">No health records for this resident yet.</p>
            <?php else: ?>
                <div class="resident-table-wrap">
                    <table class="resident-table">
                        <thead><tr><th scope="col">Record No.</th><th scope="col">Service</th><th scope="col">Health Worker</th><th scope="col">Date</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                        <tbody>
                        <?php foreach ($health_records as $health): ?>
                            <tr>
                                <td class="resident-id"><?= e($health['record_no']) ?></td>
                                <td class="resident-name"><?= e(health_service_label($health)) ?></td>
                                <td><?= e($health['health_worker']) ?></td>
                                <td><?= e(health_format_date($health['service_date'])) ?></td>
                                <td><?= health_status_badge($health['status']) ?><?php $due_visit = health_is_due($health) && $health['status'] === 'scheduled' && $health['service_date'] <= date('Y-m-d'); ?><?php if ($due_visit): ?> <span class="activity-detail-muted"><strong><?= $health['service_date'] < date('Y-m-d') ? 'Overdue visit' : 'Visit today' ?></strong></span><?php elseif ($health['follow_up_date'] && $health['status'] !== 'cancelled'): ?> <span class="activity-detail-muted"><?= health_is_due($health) ? '<strong>' . ($health['follow_up_date'] < date('Y-m-d') ? 'Overdue' : 'Due today') . '</strong> · ' : 'Follow-up ' ?><?= e(health_format_date($health['follow_up_date'])) ?></span><?php endif; ?></td>
                                <td><a class="btn btn-sm btn-outline-primary" href="health_view.php?id=<?= e((string) $health['id']) ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <ul class="resident-cards">
                    <?php foreach ($health_records as $health): ?>
                        <li class="resident-card">
                            <div class="resident-card-top"><strong><?= e(health_service_label($health)) ?></strong><?= health_status_badge($health['status']) ?></div>
                            <p><?= e($health['record_no']) ?> · <?= e(health_format_date($health['service_date'])) ?> · <?= e($health['health_worker']) ?><?= $health['follow_up_date'] && $health['status'] !== 'cancelled' ? ' · ' . (health_is_due($health) ? 'Due ' : 'Follow-up ') . e(health_format_date($health['follow_up_date'])) : '' ?></p>
                            <div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="health_view.php?id=<?= e((string) $health['id']) ?>">View</a></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</article>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
