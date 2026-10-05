<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health.php';
health_require_manage();
$connection = db();
if (!health_ready($connection)) { flash('health_error', 'Health records need their database table first.'); redirect('health.php'); }
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$health_record = $id ? health_find($connection, $id) : null;
// Every opening of a health record is kept in the audit log (who and when; no health details).
if ($health_record !== null) health_audit($connection, (int) $health_record['id'], 'health_record_viewed', ['record_no' => $health_record['record_no']]);
if ($health_record === null) { http_response_code(404); exit('Health record not found.'); }

$archived = $health_record['archived_at'] !== null;
$due = health_is_due($health_record);
$muted = '<span class="activity-detail-muted">Not recorded</span>';
$age = residents_age($health_record['birth_date']);
// Other records of the same resident (latest 10), for context.
$statement = $connection->prepare('SELECT id, record_no, service, service_details, service_date, status, follow_up_date, archived_at FROM health_records WHERE resident_id = :resident AND id <> :id AND archived_at IS NULL ORDER BY service_date DESC, id DESC LIMIT 10');
$statement->execute(['resident' => $health_record['resident_id'], 'id' => $health_record['id']]);
$history = $statement->fetchAll();
$phase2 = health_phase2_ready($connection);
$medicines = $phase2 ? health_record_medicines($connection, (int) $health_record['id']) : [];
$vitals = $phase2 ? health_vitals_label($health_record) : '';
// What is due: a Scheduled visit whose date has come, or the follow-up date.
$due_scheduled = $due && $health_record['status'] === 'scheduled' && $health_record['service_date'] <= date('Y-m-d');
$due_date = $due_scheduled ? $health_record['service_date'] : $health_record['follow_up_date'];
$page_title = 'Health Record'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($success = flash('health_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if ($failure = flash('health_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
<article class="dashboard-panel resident-detail">
    <div class="page-heading">
        <div>
            <a class="announcement-back" href="health.php"><span aria-hidden="true">&larr;</span> Back to Health</a>
            <span class="eyebrow"><?= e($health_record['record_no']) ?></span>
            <h1><?= e(health_service_label($health_record)) ?></h1>
            <p><?= $archived ? '<span class="resident-status resident-status-inactive">Archived</span>' : health_status_badge($health_record['status']) ?> <span class="resident-detail-meta"><?= e(residents_full_name($health_record)) ?> · <?= e(health_format_date($health_record['service_date'])) ?></span></p>
        </div>
        <div class="resident-detail-actions">
            <a class="btn btn-light resident-action-btn" href="health_print.php?id=<?= e((string) $health_record['id']) ?>" target="_blank" rel="noopener">Print</a>
            <?php if (!$archived): ?><a class="btn announcement-edit-btn" href="health_form.php?id=<?= e((string) $health_record['id']) ?>">Edit Record</a><?php endif; ?>
            <form method="post" action="health_action.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $health_record['id']) ?>"><input type="hidden" name="back" value="view"><input type="hidden" name="action" value="<?= $archived ? 'restore' : 'archive' ?>"><button class="btn <?= $archived ? 'btn-light resident-action-btn' : 'btn-outline-danger' ?>" type="submit" data-form-confirm="custom" data-dialog-heading="<?= $archived ? 'Restore this record?' : 'Archive this record?' ?>" data-dialog-message="<?= e($archived ? 'The record will appear in the health records list again.' : 'The record will be hidden from the list. It is kept and can be restored.') ?>" data-dialog-confirm="<?= $archived ? 'Restore' : 'Archive' ?>" data-dialog-dismiss="Cancel"<?= $archived ? '' : ' data-dialog-danger="true"' ?>><?= $archived ? 'Restore Record' : 'Archive Record' ?></button></form>
        </div>
    </div>
    <?php if ($archived): ?><p class="dashboard-status warning" role="status">Archived on <?= e(health_format_date($health_record['archived_at'])) ?><?= $health_record['archived_by_name'] ? ' by ' . e($health_record['archived_by_name']) : '' ?>. Restore the record to edit it.</p><?php endif; ?>
    <?php if ($due && $due_date): ?><p class="dashboard-status warning" role="status"><?= $due_scheduled ? 'Scheduled visit' : 'Follow-up' ?> <?= $due_date < date('Y-m-d') ? 'overdue since' : 'due today,' ?> <?= e(health_format_date($due_date)) ?>.</p><?php endif; ?>

    <div class="resident-detail-grid">
        <section class="resident-detail-section">
            <h2>Resident</h2>
            <dl class="activity-detail-list">
                <div><dt>Name</dt><dd><a class="activity-detail-link" href="health_history.php?resident=<?= e((string) $health_record['resident_id']) ?>" title="Health history"><?= e(residents_full_name($health_record)) ?></a></dd></div>
                <div><dt>Resident ID</dt><dd>#<?= e((string) $health_record['resident_id']) ?></dd></div>
                <div><dt>Age</dt><dd><?= $age === null ? $muted : e((string) $age) ?></dd></div>
                <div><dt>Sex</dt><dd><?= isset(residents_sex_labels()[$health_record['sex']]) ? e(residents_sex_labels()[$health_record['sex']]) : $muted ?></dd></div>
                <div><dt>Purok</dt><dd><?= e(residents_purok_label((string) $health_record['purok'])) ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Service</h2>
            <dl class="activity-detail-list">
                <div><dt>Record no.</dt><dd><?= e($health_record['record_no']) ?></dd></div>
                <div><dt>Service</dt><dd><?= e(health_services()[$health_record['service']] ?? '') ?></dd></div>
                <div><dt>Details</dt><dd><?= $health_record['service_details'] ? e($health_record['service_details']) : $muted ?></dd></div>
                <div><dt>Health worker</dt><dd><?= e($health_record['health_worker']) ?></dd></div>
                <div><dt>Date</dt><dd><?= e(health_format_date($health_record['service_date'])) ?></dd></div>
                <div><dt>Status</dt><dd><?= health_status_badge($health_record['status']) ?></dd></div>
                <?php if ($health_record['follow_up_date']): ?><div><dt>Follow-up date</dt><dd><?= e(health_format_date($health_record['follow_up_date'])) ?></dd></div><?php endif; ?>
                <?php if ($phase2): ?><div><dt>Referred to RHU</dt><dd><?= (int) $health_record['referred_rhu'] === 1 ? 'Yes' . ($health_record['referral_reason'] ? ' — ' . e($health_record['referral_reason']) : '') : 'No' ?></dd></div><?php endif; ?>
            </dl>
        </section>
        <?php if ($phase2): ?>
        <section class="resident-detail-section">
            <h2>Consultation</h2>
            <dl class="activity-detail-list">
                <div><dt>Vital signs</dt><dd><?= $vitals !== '' ? '<span class="health-vitals-line">' . e($vitals) . '</span>' : $muted ?></dd></div>
                <div><dt>Chief complaint</dt><dd><?= $health_record['chief_complaint'] ? e($health_record['chief_complaint']) : $muted ?></dd></div>
                <div><dt>Diagnosis</dt><dd><?= $health_record['condition_name'] ? e($health_record['condition_name']) : $muted ?></dd></div>
                <div><dt>Findings</dt><dd class="resident-wrap"><?= $health_record['findings'] ? nl2br(e($health_record['findings'])) : $muted ?></dd></div>
                <div><dt>Medicines given</dt><dd><?php if ($medicines === []): ?><?= $muted ?><?php else: ?><?php foreach ($medicines as $line): ?><span class="d-block"><?= e($line['name']) ?> — <?= e(number_format((int) $line['quantity']) . ' ' . $line['unit']) ?></span><?php endforeach; ?><?php endif; ?></dd></div>
            </dl>
        </section>
        <?php endif; ?>
        <section class="resident-detail-section">
            <h2>Remarks</h2>
            <p class="resident-wrap health-remarks"><?= $health_record['remarks'] ? nl2br(e($health_record['remarks'])) : '<span class="activity-detail-muted">No remarks.</span>' ?></p>
            <dl class="activity-detail-list">
                <div><dt>Recorded</dt><dd><?= e(health_format_date($health_record['created_at'])) ?><?= $health_record['created_by_name'] ? ' · ' . e($health_record['created_by_name']) : '' ?></dd></div>
                <div><dt>Last updated</dt><dd><?= e(health_format_date($health_record['updated_at'])) ?><?= $health_record['updated_by_name'] ? ' · ' . e($health_record['updated_by_name']) : '' ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Other Records of This Resident</h2>
            <?php if ($history === []): ?>
                <p class="resident-static">No other health records.</p>
            <?php else: ?>
                <ul class="resident-history">
                    <?php foreach ($history as $other): ?><li><strong><a class="activity-detail-link" href="health_view.php?id=<?= e((string) $other['id']) ?>"><?= e($other['record_no']) ?></a> · <?= e(health_service_label($other)) ?></strong><span><?= e(health_format_date($other['service_date'])) ?> · <?= e(health_statuses()[$other['status']] ?? '') ?></span></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <a class="btn btn-sm btn-light resident-action-btn health-new-for" href="health_history.php?resident=<?= e((string) $health_record['resident_id']) ?>">Full health history</a>
            <a class="btn btn-sm btn-outline-primary health-new-for" href="health_form.php?resident=<?= e((string) $health_record['resident_id']) ?>">New record for this resident</a>
        </section>
    </div>
</article>
<?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
