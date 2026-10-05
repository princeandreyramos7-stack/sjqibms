<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/hearings.php';
require_once __DIR__ . '/includes/evidence.php';
require_auth();
$connection = db();
complaints_require_schema($connection);
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$complaint = $id ? complaints_find($connection, $id) : null;
$is_staff = complaints_can_manage();
// Same 404 for "missing" and "not yours", so complaint IDs of other residents are never confirmed.
if (!$complaint || (!$is_staff && (!complaints_can_submit_online() || !complaints_resident_owns($connection, $complaint)))) { http_response_code(404); exit('Complaint not found.'); }

$persons = complaints_persons($connection, 'complaint', (int) $complaint['id']);
$history = complaints_history($connection, 'complaint', (int) $complaint['id']);
$hearings = complaints_case_hearings($connection, 'complaint', (int) $complaint['id']);
$status = $complaint['status'];
$return = (string) ($_GET['return'] ?? '');
$back = $is_staff ? 'complaints.php' . (preg_match('/^[A-Za-z0-9_=&%+.\-]{1,300}$/', $return) ? '?' . $return : '') : 'resident_complaints.php';
$not_recorded = '<span class="activity-detail-muted">Not recorded</span>';
if ($is_staff) {
    $blotters = $connection->prepare('SELECT id, blotter_number, incident_type, incident_at, status FROM blotter_entries WHERE complaint_id = :id ORDER BY recorded_at DESC');
    $blotters->execute(['id' => $complaint['id']]);
    $blotters = $blotters->fetchAll();
    $attachments = complaints_attachments($connection, 'complaint', (int) $complaint['id']);
}
$by_role = static fn (string $role): array => array_values(array_filter($persons, static fn (array $p): bool => $p['person_role'] === $role));
$details = e(json_encode([['Reference', $complaint['case_number']], ['Subject', $complaint['subject']], ['Current status', complaints_status_labels(true)[$status] ?? $status]], JSON_UNESCAPED_UNICODE));
$page_title = 'Complaint Details'; $active_page = $is_staff ? 'complaints' : 'my_complaints';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($success = flash('case_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if ($failure = flash('case_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>

<div class="document-detail-head">
    <div>
        <a class="announcement-back" href="<?= e($back) ?>"><span aria-hidden="true">&larr;</span> <?= $is_staff ? 'Back to Complaints' : 'Back to My Complaints' ?></a>
        <h1>Complaint Details</h1>
        <p class="document-detail-sub"><strong><?= e($complaint['case_number']) ?></strong> <?= complaints_badge('complaint', $status) ?> <span class="case-confidential">Confidential</span></p>
    </div>
    <?php if ($is_staff): ?>
        <div class="doc-action-group is-large document-detail-actions">
            <?php if (in_array($status, ['pending_review', 'open'], true)): ?>
                <form method="post" action="complaint_action.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $complaint['id']) ?>"><input type="hidden" name="action" value="start_review"><button class="btn doc-action-btn is-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Start complaint review?" data-dialog-message="The complaint will move to Under Review." data-dialog-details="<?= $details ?>" data-dialog-confirm="Start Review" data-dialog-dismiss="Cancel">Start Review</button></form>
            <?php elseif ($status === 'under_review'): ?>
                <a class="btn doc-action-btn is-primary" href="#process-panel">Resolve</a>
                <a class="btn doc-action-btn is-view" href="blotter_form.php?complaint=<?= e((string) $complaint['id']) ?>">Record Blotter</a>
                <a class="btn doc-action-btn is-view" href="hearing_form.php?case=complaint:<?= e((string) $complaint['id']) ?>">Schedule Hearing</a>
            <?php elseif ($status === 'resolved'): ?>
                <a class="btn doc-action-btn is-primary" href="#process-panel">Close Complaint</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="document-detail-layout<?= $is_staff ? '' : ' is-single' ?>">
    <div class="document-detail-info">
        <section class="dashboard-panel case-panel">
            <h2>Complaint Information</h2>
            <dl class="activity-detail-list">
                <div><dt>Reference</dt><dd><?= e($complaint['case_number']) ?></dd></div>
                <div><dt>Category</dt><dd><?= $complaint['category'] ? e($complaint['category']) : $not_recorded ?></dd></div>
                <div><dt>Subject</dt><dd class="resident-wrap"><?= e($complaint['subject']) ?></dd></div>
                <div><dt>Incident date</dt><dd><?= $complaint['incident_at'] ? e(complaints_format_datetime($complaint['incident_at'])) : $not_recorded ?></dd></div>
                <div><dt>Incident location</dt><dd class="resident-wrap"><?= $complaint['incident_location'] ? e($complaint['incident_location']) : $not_recorded ?></dd></div>
                <div><dt>Submitted</dt><dd><?= e(complaints_format_datetime($complaint['filed_at'])) ?></dd></div>
                <div><dt>Submission source</dt><dd><?= e(complaints_source_label($complaint['submission_source'])) ?></dd></div>
                <?php if ($is_staff): ?><div><dt>Recorded by</dt><dd><?= $complaint['submitter_name'] ? e($complaint['submitter_name']) : $not_recorded ?></dd></div><?php endif; ?>
            </dl>
            <h3 class="resident-subheading">Description</h3>
            <p class="case-narrative"><?= $complaint['confidential_details'] ? nl2br(e($complaint['confidential_details'])) : $not_recorded ?></p>
        </section>

        <section class="dashboard-panel case-panel">
            <h2>Parties</h2>
            <?php foreach (['complainant' => 'Complainant', 'respondent' => 'Respondent'] as $role => $label): ?>
                <h3 class="resident-subheading"><?= e($label) ?><?= count($by_role($role)) > 1 ? 's' : '' ?></h3>
                <?php if ($by_role($role) === []): ?><p class="resident-static">Not recorded.</p><?php else: ?>
                    <ul class="case-person-summary"><?php foreach ($by_role($role) as $person): ?><li><?= $is_staff ? complaints_person_summary($person) : '<strong>' . e($person['full_name']) . '</strong>' ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($is_staff): ?>
                <h3 class="resident-subheading">Witnesses and other persons</h3>
                <?php $others = array_merge($by_role('witness'), $by_role('other')); ?>
                <?php if ($others === []): ?><p class="resident-static">None recorded.</p><?php else: ?>
                    <ul class="case-person-summary"><?php foreach ($others as $person): ?><li><span class="case-role-tag"><?= e(case_person_role_labels()[$person['person_role']]) ?></span> <?= complaints_person_summary($person) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>

    <div class="document-detail-info">
        <?php if ($is_staff): ?>
            <section class="dashboard-panel case-panel" id="process-panel">
                <h2>Processing</h2>
                <dl class="activity-detail-list">
                    <div><dt>Review started</dt><dd><?= $complaint['reviewed_at'] ? e(complaints_format_datetime($complaint['reviewed_at'])) . ' · ' . e($complaint['reviewer_name'] ?? '') : $not_recorded ?></dd></div>
                    <div><dt>Resolved</dt><dd><?= $complaint['resolved_at'] ? e(complaints_format_datetime($complaint['resolved_at'])) . ' · ' . e($complaint['resolver_name'] ?? '') : $not_recorded ?></dd></div>
                    <?php if ($complaint['resolution_notes']): ?><div><dt>Resolution notes</dt><dd class="resident-wrap"><?= nl2br(e($complaint['resolution_notes'])) ?></dd></div><?php endif; ?>
                    <div><dt>Closed</dt><dd><?= $complaint['closed_at'] ? e(complaints_format_datetime($complaint['closed_at'])) . ' · ' . e($complaint['closer_name'] ?? '') : $not_recorded ?></dd></div>
                    <?php if ($complaint['closing_notes']): ?><div><dt>Closing information</dt><dd class="resident-wrap"><?= nl2br(e($complaint['closing_notes'])) ?></dd></div><?php endif; ?>
                </dl>
                <?php if ($status === 'under_review' || $status === 'resolved'): $action = $status === 'under_review' ? 'resolve' : 'close'; ?>
                    <form class="case-process-form" method="post" action="complaint_action.php">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $complaint['id']) ?>"><input type="hidden" name="action" value="<?= e($action) ?>">
                        <label class="form-label" for="process-notes"><?= $action === 'resolve' ? 'Resolution notes' : 'Closing information' ?> <span class="activity-detail-muted">(required)</span></label>
                        <textarea class="form-control" id="process-notes" name="notes" rows="3" minlength="5" maxlength="2000" required></textarea>
                        <div class="doc-action-group"><button class="btn doc-action-btn is-primary" type="submit" data-form-confirm="custom" data-dialog-heading="<?= $action === 'resolve' ? 'Resolve this complaint?' : 'Close this complaint?' ?>" data-dialog-message="<?= $action === 'resolve' ? 'The complaint will be marked Resolved with your notes.' : 'The complaint will be Closed and become read-only.' ?>" data-dialog-details="<?= $details ?>" data-dialog-confirm="<?= $action === 'resolve' ? 'Resolve Complaint' : 'Close Complaint' ?>" data-dialog-dismiss="Cancel"><?= $action === 'resolve' ? 'Resolve Complaint' : 'Close Complaint' ?></button></div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="dashboard-panel case-panel">
            <h2><?= $is_staff ? 'Processing History' : 'Status Updates' ?></h2>
            <ol class="document-steps is-history">
                <?php foreach ($history as $entry): if (!$is_staff && !in_array($entry['action'], ['submitted', 'review_started', 'resolved', 'closed'], true)) continue; ?>
                    <li class="is-done"><strong><?= e(complaints_history_label($entry['action'])) ?></strong> · <?= e(complaints_format_datetime($entry['acted_at'])) ?><?= $is_staff && $entry['actor_name'] ? ' · ' . e($entry['actor_name']) : '' ?><?php if ($is_staff && $entry['notes']): ?><br><span class="activity-detail-muted"><?= nl2br(e($entry['notes'])) ?></span><?php endif; ?></li>
                <?php endforeach; ?>
            </ol>
            <?php if ($is_staff && $status !== 'closed'): ?>
                <form class="case-process-form" method="post" action="complaint_action.php">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $complaint['id']) ?>"><input type="hidden" name="action" value="note">
                    <label class="form-label" for="case-note">Add confidential note</label>
                    <textarea class="form-control" id="case-note" name="notes" rows="2" minlength="5" maxlength="2000" required></textarea>
                    <div class="doc-action-group"><button class="btn doc-action-btn is-view" type="submit" data-form-confirm="custom" data-dialog-heading="Add this note?" data-dialog-message="The note is added to the confidential processing history. It cannot be edited later." data-dialog-confirm="Add Note" data-dialog-dismiss="Cancel">Add Note</button></div>
                </form>
            <?php endif; ?>
        </section>

        <?php if ($is_staff): ?>
            <section class="dashboard-panel case-panel">
                <h2>Linked Blotter Entries</h2>
                <?php if ($blotters === []): ?><p class="resident-static">No blotter entries are linked to this complaint.</p><?php else: ?>
                    <ul class="case-link-list"><?php foreach ($blotters as $blotter): ?><li><a class="activity-detail-link" href="blotter_view.php?id=<?= e((string) $blotter['id']) ?>"><?= e($blotter['blotter_number']) ?></a> <?= complaints_badge('blotter', $blotter['status']) ?><span class="activity-detail-muted"><?= e($blotter['incident_type']) ?> · <?= e(complaints_format_datetime($blotter['incident_at'])) ?></span></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="dashboard-panel case-panel">
            <h2>Hearings</h2>
            <?php if ($hearings === []): ?><p class="resident-static">No hearings are scheduled for this complaint.</p><?php else: ?>
                <ul class="case-link-list"><?php foreach ($hearings as $hearing): ?><li><?= $is_staff ? '<a class="activity-detail-link" href="hearing_view.php?id=' . e((string) $hearing['id']) . '">' . e($hearing['hearing_number']) . '</a>' : '<strong>' . e($hearing['hearing_number']) . '</strong>' ?> <?= complaints_badge('hearing', $hearing['status']) ?><span class="activity-detail-muted"><?= e(complaints_format_time_range($hearing['starts_at'], $hearing['ends_at'])) ?> · <?= e($hearing['venue_name']) ?></span></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>

        <?php if ($is_staff):
            $evidence_kind = 'complaint'; $evidence_case_id = (int) $complaint['id']; $evidence_rows = $attachments; $evidence_closed = $status === 'closed'; $evidence_upload_ready = evidence_storage_ready();
            require __DIR__ . '/layout/case_evidence_panel.php';
        endif; ?>
    </div>
</div>
<?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
