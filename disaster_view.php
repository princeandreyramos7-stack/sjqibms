<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster.php';
disaster_require_view();
$connection = db();
$can_manage = disaster_can_manage();   // Secretary views only (Health Workers, the Treasurer and residents use Disaster Info)
if (!disaster_ready($connection)) { flash('disaster_error', 'Disaster Management needs its database tables first.'); redirect('disaster.php'); }
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$drr_record = $id ? disaster_find($connection, $id) : null;
if ($drr_record === null) { http_response_code(404); exit('DRR record not found.'); }

$archived = $drr_record['archived_at'] !== null;
$incident = $drr_record['record_type'] === 'incident';
$muted = '<span class="activity-detail-muted">Not recorded</span>';
$count = static fn ($value): string => $value === null ? $muted : e(number_format((int) $value));
$page_title = 'DRR Record'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($success = flash('disaster_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if ($failure = flash('disaster_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
<article class="dashboard-panel resident-detail">
    <div class="page-heading">
        <div>
            <a class="announcement-back" href="disaster.php"><span aria-hidden="true">&larr;</span> Back to Incidents</a>
            <span class="eyebrow"><?= e($drr_record['reference_no']) ?></span>
            <h1><?= e($drr_record['title']) ?></h1>
            <p><?= $archived ? '<span class="resident-status resident-status-inactive">Archived</span>' : disaster_status_badge($drr_record['status']) ?> <?= $incident ? disaster_alert_badge($drr_record['alert_level']) : '' ?> <span class="resident-detail-meta"><?= e(disaster_types()[$drr_record['record_type']] ?? '') ?> · <?= e($drr_record['area_name']) ?> · <?= e(disaster_format_date($drr_record['record_date'])) ?></span></p>
        </div>
        <?php if ($can_manage): ?><div class="resident-detail-actions">
            <?php if (!$archived): ?><a class="btn announcement-edit-btn" href="disaster_form.php?id=<?= e((string) $drr_record['id']) ?>">Edit Record</a><?php endif; ?>
            <form method="post" action="disaster_action.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $drr_record['id']) ?>"><input type="hidden" name="back" value="view"><input type="hidden" name="action" value="<?= $archived ? 'restore' : 'archive' ?>"><button class="btn <?= $archived ? 'btn-light resident-action-btn' : 'btn-outline-danger' ?>" type="submit" data-form-confirm="custom" data-dialog-heading="<?= $archived ? 'Restore this record?' : 'Archive this record?' ?>" data-dialog-message="<?= e($archived ? 'The record will appear in the records list again.' : 'The record will be hidden from the list. It is kept and can be restored.') ?>" data-dialog-confirm="<?= $archived ? 'Restore' : 'Archive' ?>" data-dialog-dismiss="Cancel"<?= $archived ? '' : ' data-dialog-danger="true"' ?>><?= $archived ? 'Restore Record' : 'Archive Record' ?></button></form>
        </div><?php endif; ?>
    </div>
    <?php if ($archived): ?><p class="dashboard-status warning" role="status">Archived on <?= e(disaster_format_date($drr_record['archived_at'])) ?><?= $drr_record['archived_by_name'] ? ' by ' . e($drr_record['archived_by_name']) : '' ?>.<?= $can_manage ? ' Restore the record to edit it.' : '' ?></p><?php endif; ?>

    <div class="resident-detail-grid">
        <section class="resident-detail-section">
            <h2>Record</h2>
            <dl class="activity-detail-list">
                <div><dt>Reference no.</dt><dd><?= e($drr_record['reference_no']) ?></dd></div>
                <div><dt>Type</dt><dd><?= e(disaster_types()[$drr_record['record_type']] ?? '') ?></dd></div>
                <div><dt>Area</dt><dd><?= e($drr_record['area_name']) ?><?= (int) $drr_record['area_active'] === 1 ? '' : ' <span class="activity-detail-muted">(inactive area)</span>' ?></dd></div>
                <div><dt>Date</dt><dd><?= e(disaster_format_date($drr_record['record_date'])) ?></dd></div>
                <div><dt>Status</dt><dd><?= disaster_status_badge($drr_record['status']) ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Description</h2>
            <p class="resident-wrap drr-text"><?= $drr_record['description'] ? nl2br(e($drr_record['description'])) : '<span class="activity-detail-muted">No description.</span>' ?></p>
            <dl class="activity-detail-list">
                <div><dt>Recorded</dt><dd><?= e(disaster_format_date($drr_record['created_at'])) ?><?= $drr_record['created_by_name'] ? ' · ' . e($drr_record['created_by_name']) : '' ?></dd></div>
                <div><dt>Last updated</dt><dd><?= e(disaster_format_date($drr_record['updated_at'])) ?><?= $drr_record['updated_by_name'] ? ' · ' . e($drr_record['updated_by_name']) : '' ?></dd></div>
            </dl>
        </section>
        <?php if ($incident): ?>
            <section class="resident-detail-section">
                <h2>Incident Details</h2>
                <dl class="activity-detail-list">
                    <div><dt>Affected families</dt><dd><?= $count($drr_record['affected_families']) ?></dd></div>
                    <div><dt>Affected persons</dt><dd><?= $count($drr_record['affected_persons']) ?></dd></div>
                    <div><dt>Warning level</dt><dd><?= $drr_record['alert_level'] ? disaster_alert_badge($drr_record['alert_level']) : '<span class="activity-detail-muted">None</span>' ?></dd></div>
                </dl>
                <p class="resident-wrap drr-text"><?= $drr_record['incident_details'] ? nl2br(e($drr_record['incident_details'])) : '<span class="activity-detail-muted">No incident details.</span>' ?></p>
                <div class="drr-related">
                    <a class="btn btn-sm btn-primary" href="disaster_incident_report.php?id=<?= e((string) $drr_record['id']) ?>" target="_blank" rel="noopener">Incident Report</a>
                    <a class="btn btn-sm btn-outline-primary" href="disaster_evacuation.php?incident=<?= e((string) $drr_record['id']) ?>">Evacuees</a>
                    <a class="btn btn-sm btn-outline-primary" href="disaster_damage.php?incident=<?= e((string) $drr_record['id']) ?>">Damage</a>
                    <?php if (can_access_navigation('assistance')): ?><a class="btn btn-sm btn-outline-primary" href="assistance.php?incident=<?= e((string) $drr_record['id']) ?>">Relief &amp; Assistance</a><?php endif; ?>
                    <a class="btn btn-sm btn-outline-primary" href="disaster_logbook_print.php?incident=<?= e((string) $drr_record['id']) ?>" target="_blank" rel="noopener">Print Evacuation Logbook</a>
                    <?php if (!$archived && $can_manage): ?>
                        <a class="btn btn-sm btn-outline-primary" href="disaster_checkin.php?incident=<?= e((string) $drr_record['id']) ?>">Check In Family</a>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</article>
<?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
