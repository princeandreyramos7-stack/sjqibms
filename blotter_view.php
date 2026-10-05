<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/hearings.php';
require_once __DIR__ . '/includes/evidence.php';
complaints_require_manage();
$connection = db();
complaints_require_schema($connection);
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$blotter = $id ? blotter_find($connection, $id) : null;
if (!$blotter) { http_response_code(404); exit('Blotter entry not found.'); }
$persons = complaints_persons($connection, 'blotter', (int) $blotter['id']);
$history = complaints_history($connection, 'blotter', (int) $blotter['id']);
$hearings = complaints_case_hearings($connection, 'blotter', (int) $blotter['id']);
$attachments = complaints_attachments($connection, 'blotter', (int) $blotter['id']);
$status = $blotter['status'];
$return = (string) ($_GET['return'] ?? '');
$back = 'complaints.php' . (preg_match('/^[A-Za-z0-9_=&%+.\-]{1,300}$/', $return) ? '?' . $return : '?tab=blotter');
$not_recorded = '<span class="activity-detail-muted">Not recorded</span>';
$by_role = static fn (string $role): array => array_values(array_filter($persons, static fn (array $p): bool => $p['person_role'] === $role));
$details = e(json_encode([['Reference', $blotter['blotter_number']], ['Incident type', $blotter['incident_type']], ['Current status', blotter_status_labels()[$status]]], JSON_UNESCAPED_UNICODE));
$page_title = 'Blotter Details'; $active_page = 'complaints';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($success = flash('case_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if ($failure = flash('case_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>

<div class="document-detail-head">
    <div>
        <a class="announcement-back" href="<?= e($back) ?>"><span aria-hidden="true">&larr;</span> Back to Blotter Records</a>
        <h1>Blotter Details</h1>
        <p class="document-detail-sub"><strong><?= e($blotter['blotter_number']) ?></strong> <?= complaints_badge('blotter', $status) ?> <span class="case-confidential">Confidential</span></p>
    </div>
    <div class="doc-action-group is-large document-detail-actions">
        <?php if ($status === 'recorded'): ?>
            <form method="post" action="blotter_action.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $blotter['id']) ?>"><input type="hidden" name="action" value="start"><button class="btn doc-action-btn is-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Start blotter processing?" data-dialog-message="The blotter entry will become Active." data-dialog-details="<?= $details ?>" data-dialog-confirm="Start Processing" data-dialog-dismiss="Cancel">Start Processing</button></form>
        <?php elseif ($status === 'active'): ?>
            <a class="btn doc-action-btn is-primary" href="#process-panel">Resolve</a>
            <a class="btn doc-action-btn is-view" href="hearing_form.php?case=blotter:<?= e((string) $blotter['id']) ?>">Schedule Hearing</a>
        <?php elseif ($status === 'resolved'): ?>
            <a class="btn doc-action-btn is-primary" href="#process-panel">Close Case</a>
        <?php endif; ?>
    </div>
</div>

<div class="document-detail-layout">
    <div class="document-detail-info">
        <section class="dashboard-panel case-panel">
            <h2>Incident Information</h2>
            <dl class="activity-detail-list">
                <div><dt>Reference</dt><dd><?= e($blotter['blotter_number']) ?></dd></div>
                <div><dt>Related complaint</dt><dd><?= $blotter['complaint_id'] ? '<a class="activity-detail-link" href="complaint_view.php?id=' . e((string) $blotter['complaint_id']) . '">' . e($blotter['complaint_reference']) . '</a> ' . complaints_badge('complaint', (string) $blotter['complaint_status']) : '<span class="activity-detail-muted">Standalone entry</span>' ?></dd></div>
                <div><dt>Incident type</dt><dd><?= e($blotter['incident_type']) ?></dd></div>
                <div><dt>Incident date</dt><dd><?= e(complaints_format_datetime($blotter['incident_at'])) ?></dd></div>
                <div><dt>Incident location</dt><dd class="resident-wrap"><?= e($blotter['incident_location']) ?></dd></div>
                <div><dt>Recorded</dt><dd><?= e(complaints_format_datetime($blotter['recorded_at'])) ?><?= $blotter['recorder_name'] ? ' · ' . e($blotter['recorder_name']) : '' ?></dd></div>
            </dl>
            <h3 class="resident-subheading">Narrative</h3>
            <p class="case-narrative"><?= nl2br(e($blotter['narrative'])) ?></p>
        </section>
        <section class="dashboard-panel case-panel">
            <h2>Involved Persons</h2>
            <?php foreach (['complainant' => 'Complainant', 'respondent' => 'Respondent', 'witness' => 'Witness', 'other' => 'Other involved person'] as $role => $label): if ($by_role($role) === [] && in_array($role, ['witness', 'other'], true)) continue; ?>
                <h3 class="resident-subheading"><?= e($label) ?><?= count($by_role($role)) > 1 ? 's' : '' ?></h3>
                <?php if ($by_role($role) === []): ?><p class="resident-static">Not recorded.</p><?php else: ?><ul class="case-person-summary"><?php foreach ($by_role($role) as $person): ?><li><?= complaints_person_summary($person) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php endforeach; ?>
        </section>
    </div>
    <div class="document-detail-info">
        <section class="dashboard-panel case-panel" id="process-panel">
            <h2>Processing</h2>
            <dl class="activity-detail-list">
                <div><dt>Processing started</dt><dd><?= $blotter['processing_started_at'] ? e(complaints_format_datetime($blotter['processing_started_at'])) . ' · ' . e($blotter['starter_name'] ?? '') : $not_recorded ?></dd></div>
                <div><dt>Resolved</dt><dd><?= $blotter['resolved_at'] ? e(complaints_format_datetime($blotter['resolved_at'])) . ' · ' . e($blotter['resolver_name'] ?? '') : $not_recorded ?></dd></div>
                <?php if ($blotter['resolution_notes']): ?><div><dt>Resolution notes</dt><dd class="resident-wrap"><?= nl2br(e($blotter['resolution_notes'])) ?></dd></div><?php endif; ?>
                <div><dt>Closed</dt><dd><?= $blotter['closed_at'] ? e(complaints_format_datetime($blotter['closed_at'])) . ' · ' . e($blotter['closer_name'] ?? '') : $not_recorded ?></dd></div>
                <?php if ($blotter['closing_notes']): ?><div><dt>Closing information</dt><dd class="resident-wrap"><?= nl2br(e($blotter['closing_notes'])) ?></dd></div><?php endif; ?>
            </dl>
            <?php if ($status === 'active' || $status === 'resolved'): $action = $status === 'active' ? 'resolve' : 'close'; ?>
                <form class="case-process-form" method="post" action="blotter_action.php">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $blotter['id']) ?>"><input type="hidden" name="action" value="<?= e($action) ?>">
                    <label class="form-label" for="process-notes"><?= $action === 'resolve' ? 'Resolution notes' : 'Closing information' ?> <span class="activity-detail-muted">(required)</span></label>
                    <textarea class="form-control" id="process-notes" name="notes" rows="3" minlength="5" maxlength="2000" required></textarea>
                    <div class="doc-action-group"><button class="btn doc-action-btn is-primary" type="submit" data-form-confirm="custom" data-dialog-heading="<?= $action === 'resolve' ? 'Resolve this blotter case?' : 'Close this blotter case?' ?>" data-dialog-message="<?= $action === 'resolve' ? 'The blotter entry will be marked Resolved with your notes. Linked complaints are not changed.' : 'The blotter entry will be Closed and become read-only.' ?>" data-dialog-details="<?= $details ?>" data-dialog-confirm="<?= $action === 'resolve' ? 'Resolve Case' : 'Close Case' ?>" data-dialog-dismiss="Cancel"><?= $action === 'resolve' ? 'Resolve Case' : 'Close Case' ?></button></div>
                </form>
            <?php endif; ?>
        </section>
        <section class="dashboard-panel case-panel">
            <h2>Processing History</h2>
            <ol class="document-steps is-history">
                <?php foreach ($history as $entry): ?>
                    <li class="is-done"><strong><?= e(complaints_history_label($entry['action'])) ?></strong> · <?= e(complaints_format_datetime($entry['acted_at'])) ?><?= $entry['actor_name'] ? ' · ' . e($entry['actor_name']) : '' ?><?php if ($entry['notes']): ?><br><span class="activity-detail-muted"><?= nl2br(e($entry['notes'])) ?></span><?php endif; ?></li>
                <?php endforeach; ?>
            </ol>
            <?php if ($status !== 'closed'): ?>
                <form class="case-process-form" method="post" action="blotter_action.php">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $blotter['id']) ?>"><input type="hidden" name="action" value="note">
                    <label class="form-label" for="case-note">Add confidential note</label>
                    <textarea class="form-control" id="case-note" name="notes" rows="2" minlength="5" maxlength="2000" required></textarea>
                    <div class="doc-action-group"><button class="btn doc-action-btn is-view" type="submit" data-form-confirm="custom" data-dialog-heading="Add this note?" data-dialog-message="The note is added to the confidential processing history. It cannot be edited later." data-dialog-confirm="Add Note" data-dialog-dismiss="Cancel">Add Note</button></div>
                </form>
            <?php endif; ?>
        </section>
        <section class="dashboard-panel case-panel">
            <h2>Hearings and Outcomes</h2>
            <?php if ($hearings === []): ?><p class="resident-static">No hearings are scheduled for this blotter entry.</p><?php else: ?>
                <ul class="case-link-list"><?php foreach ($hearings as $hearing): ?><li><a class="activity-detail-link" href="hearing_view.php?id=<?= e((string) $hearing['id']) ?>"><?= e($hearing['hearing_number']) ?></a> <?= complaints_badge('hearing', $hearing['status']) ?><span class="activity-detail-muted"><?= e(complaints_format_time_range($hearing['starts_at'], $hearing['ends_at'])) ?> · <?= e($hearing['venue_name']) ?></span><?php if ($hearing['outcome_summary']): ?><span class="case-outcome">Outcome: <?= e(mb_strimwidth($hearing['outcome_summary'], 0, 160, '…')) ?></span><?php endif; ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
        <?php $evidence_kind = 'blotter'; $evidence_case_id = (int) $blotter['id']; $evidence_rows = $attachments; $evidence_closed = $status === 'closed'; $evidence_upload_ready = evidence_storage_ready();
        require __DIR__ . '/layout/case_evidence_panel.php'; ?>
    </div>
</div>
<?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
