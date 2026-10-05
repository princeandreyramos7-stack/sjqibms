<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/assistance.php';
assistance_require_view();
$connection = db();
if (!assistance_ready($connection)) { flash('assistance_error', 'Relief & Assistance needs its database update first.'); redirect('assistance.php'); }
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$entry = $id ? assistance_find($connection, $id) : null;
if ($entry === null) { http_response_code(404); exit('Record not found.'); }
$lines = assistance_items($connection, (int) $entry['id']);
$history = assistance_history($connection, $entry);
$name = assistance_beneficiary_label($entry);
$form = assistance_form_of($entry);
$can_manage = assistance_can_manage();
$finance_link = $entry['finance_transaction_id'] !== null && can_access_navigation('finance');
$muted = static fn (string $text = 'None'): string => '<span class="activity-detail-muted">' . e($text) . '</span>';
$page_title = 'Assistance ' . $entry['reference_no']; $active_page = 'assistance';
$page_styles = ['assets/css/disaster.css', 'assets/css/assistance.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($success = flash('assistance_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if ($failure = flash('assistance_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
<article class="dashboard-panel resident-detail">
    <div class="page-heading">
        <div>
            <a class="announcement-back" href="assistance.php"><span aria-hidden="true">&larr;</span> Back to Relief &amp; Assistance</a>
            <span class="eyebrow"><?= e($entry['reference_no']) ?></span>
            <h1><?= e($name) ?></h1>
            <p><?= assistance_status_badge($entry['status']) ?> <span class="resident-detail-meta"><?= e(assistance_category_label($entry['assistance_type'])) ?> · <?= e(disaster_format_date($entry['given_on'])) ?></span> <?= assistance_group_badges((string) $entry['priority_groups']) ?></p>
        </div>
        <div class="resident-detail-actions ast-view-actions">
            <?php if ($entry['status'] === 'given'): ?><a class="btn btn-outline-primary" href="assistance_print.php?id=<?= e((string) $entry['id']) ?>" target="_blank" rel="noopener">Print Acknowledgment</a><?php endif; ?>
            <?php if ($can_manage && $entry['status'] !== 'void'): ?><a class="btn btn-primary" href="assistance_form.php?id=<?= e((string) $entry['id']) ?>">Edit</a><?php endif; ?>
        </div>
    </div>
    <?php if ($entry['status'] === 'void'): ?><p class="dashboard-status warning" role="status">Void since <?= e(disaster_format_datetime($entry['archived_at'])) ?><?= $entry['archived_by_name'] ? ' by ' . e($entry['archived_by_name']) : '' ?>: <?= e((string) $entry['archive_reason']) ?>. The record is kept for history.</p><?php endif; ?>
    <?php if ($entry['status'] === 'scheduled'): ?><p class="dashboard-status drr-note" role="status">Scheduled for <?= e(disaster_format_date($entry['given_on'])) ?>. Nothing has been deducted from Inventory yet.<?php if ($can_manage): ?> <a class="activity-detail-link" href="assistance_claims.php?q=<?= e(rawurlencode(mb_substr($entry['purpose'], 0, 100))) ?>">Open the Claim List</a><?php endif; ?></p><?php endif; ?>
    <div class="resident-detail-grid">
        <section class="resident-detail-section">
            <h2>Assistance</h2>
            <dl class="activity-detail-list">
                <div><dt>Reference No.</dt><dd><?= e($entry['reference_no']) ?></dd></div>
                <div><dt>Status</dt><dd><?= assistance_status_badge($entry['status']) ?></dd></div>
                <div><dt>Category</dt><dd><?= e(assistance_category_label($entry['assistance_type'])) ?></dd></div>
                <div><dt>Form of assistance</dt><dd><?= e(assistance_forms()[$form] ?? '') ?></dd></div>
                <?php if ($form === 'cash'): ?><div><dt>Amount</dt><dd><?= $entry['cash_amount'] !== null ? e(assistance_peso($entry['cash_amount'])) : $muted('Not recorded') ?></dd></div><?php endif; ?>
                <div><dt>Source</dt><dd><?= $entry['source'] ? e(assistance_sources()[$entry['source']] ?? '') : $muted('Not recorded') ?><?= $entry['source_details'] ? ' · ' . e($entry['source_details']) : '' ?></dd></div>
                <div><dt>Purpose / reason</dt><dd><?= nl2br(e($entry['purpose'])) ?></dd></div>
                <div><dt>Related incident</dt><dd><?= $entry['incident_id'] !== null ? (can_access_navigation('disaster') ? '<a class="activity-detail-link" href="disaster_view.php?id=' . e((string) $entry['incident_id']) . '">' . e($entry['incident_reference'] . ' — ' . $entry['incident_title']) . '</a>' : e($entry['incident_reference'] . ' — ' . $entry['incident_title'])) : $muted('None (not disaster relief)') ?></dd></div>
                <?php if ($form === 'cash'): ?><div><dt>Financial record</dt><dd><?php if ($entry['finance_transaction_id'] === null): ?><?= $muted('Not linked') ?><?php elseif ($finance_link): ?><a class="activity-detail-link" href="finance_view.php?id=<?= e((string) $entry['finance_transaction_id']) ?>"><?= e($entry['finance_reference']) ?></a><?php else: ?><?= e($entry['finance_reference']) ?><?php endif; ?></dd></div><?php endif; ?>
            </dl>
            <?php if ($form === 'in_kind'): ?>
                <h3 class="ast-subheading">Items</h3>
                <?php if ($lines === []): ?><p class="drr-hint">No items recorded.</p><?php else: ?>
                <ul class="resident-history">
                    <?php foreach ($lines as $line): ?><li><strong><?= e(number_format((int) $line['quantity']) . ' ' . $line['unit']) ?> · <?= e($line['name']) ?></strong><span><?php if (inventory_can_manage()): ?><a class="activity-detail-link" href="inventory_view.php?id=<?= e((string) $line['item_id']) ?>"><?= e($line['item_code']) ?></a><?php else: ?><?= e($line['item_code']) ?><?php endif; ?> · <?= $line['inventory_movement_id'] !== null ? 'Inventory movement #' . e((string) $line['inventory_movement_id']) : 'Not yet deducted (Scheduled)' ?></span></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <section class="resident-detail-section">
            <h2>Beneficiary &amp; Receiving</h2>
            <dl class="activity-detail-list">
                <div><dt>Beneficiary</dt><dd><?= e($name) ?></dd></div>
                <div><dt><?= $entry['household_id'] !== null ? 'Household No.' : 'Resident ID' ?></dt><dd><?php if ($entry['household_id'] !== null): ?><?= can_access_navigation('households') ? '<a class="activity-detail-link" href="household_view.php?id=' . e((string) $entry['household_id']) . '">' . e($entry['household_no']) . '</a>' : e($entry['household_no']) ?><?php else: ?><?= can_access_navigation('residents') ? '<a class="activity-detail-link" href="resident_view.php?id=' . e((string) $entry['resident_id']) . '">#' . e((string) $entry['resident_id']) . '</a>' : '#' . e((string) $entry['resident_id']) ?><?php endif; ?></dd></div>
                <div><dt>Purok</dt><dd><?= e(assistance_beneficiary_purok($entry)) ?></dd></div>
                <div><dt>Priority group</dt><dd><?= e(assistance_group_text((string) $entry['priority_groups'])) ?></dd></div>
                <div><dt><?= $entry['status'] === 'scheduled' ? 'Scheduled date' : 'Date given' ?></dt><dd><?= e(disaster_format_date($entry['given_on'])) ?></dd></div>
                <div><dt>Received by</dt><dd><?= $entry['received_by'] !== '' ? e($entry['received_by']) : $muted('Not yet received') ?><?= $entry['receiver_relationship'] ? ' (' . e($entry['receiver_relationship']) . ')' : '' ?></dd></div>
                <div><dt>Reference / document no.</dt><dd><?= $entry['document_no'] ? e($entry['document_no']) : $muted() ?></dd></div>
                <div><dt>Remarks</dt><dd><?= $entry['remarks'] ? e($entry['remarks']) : $muted() ?></dd></div>
                <div><dt>Recorded by</dt><dd><?= $entry['created_by_name'] ? e($entry['created_by_name']) : $muted('Unknown') ?></dd></div>
                <div><dt>Date created</dt><dd><?= e(disaster_format_datetime($entry['created_at'])) ?></dd></div>
                <div><dt>Last updated</dt><dd><?= e(disaster_format_datetime($entry['updated_at'])) ?><?= $entry['updated_by_name'] ? ' · ' . e($entry['updated_by_name']) : '' ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Previous Assistance Received</h2>
            <?php if ($history === []): ?><p class="drr-hint">No other assistance recorded for this <?= $entry['household_id'] !== null ? 'household' : 'resident' ?>.</p><?php else: ?>
                <ul class="resident-history">
                    <?php foreach ($history as $row): ?><li><strong><a class="activity-detail-link" href="assistance_view.php?id=<?= e((string) $row['id']) ?>"><?= e($row['reference_no']) ?></a> · <?= e(assistance_category_label($row['assistance_type'])) ?><?= $row['cash_amount'] !== null ? ' · ' . e(assistance_peso($row['cash_amount'])) : '' ?></strong><span><?= e(disaster_format_date($row['given_on'])) ?> · <?= e(assistance_statuses()[$row['status']] ?? '') ?> · <?= e(mb_strimwidth($row['purpose'], 0, 80, '…')) ?></span></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php if ($can_manage && $entry['status'] === 'scheduled'): ?>
            <section class="resident-detail-section">
                <h2>Mark as Given</h2>
                <p class="drr-hint">Records that the assistance was received. <?= $form === 'in_kind' ? 'The items are deducted from Inventory stock now.' : '' ?></p>
                <form method="post" action="assistance_action.php" class="ast-action-form">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $entry['id']) ?>"><input type="hidden" name="action" value="mark_given">
                    <label class="form-label" for="mg-date">Date given <span class="resident-required" aria-hidden="true">*</span></label>
                    <input class="form-control" type="date" id="mg-date" name="given_on" value="<?= e(min(date('Y-m-d'), (string) $entry['given_on']) === (string) $entry['given_on'] ? (string) $entry['given_on'] : date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required>
                    <label class="form-label" for="mg-received">Received by <span class="resident-required" aria-hidden="true">*</span></label>
                    <input class="form-control" id="mg-received" name="received_by" maxlength="150" value="<?= e($entry['received_by'] !== '' ? $entry['received_by'] : $name) ?>" required>
                    <label class="form-label" for="mg-relationship">Relationship to beneficiary</label>
                    <input class="form-control" id="mg-relationship" name="receiver_relationship" maxlength="60" value="<?= e((string) ($entry['receiver_relationship'] ?? '')) ?>" placeholder="If a representative received it">
                    <button class="btn btn-primary drr-cancel-btn" type="submit" data-form-confirm="custom" data-dialog-heading="Mark this assistance as Given?" data-dialog-message="<?= $form === 'in_kind' ? 'The items will be deducted from Inventory stock now.' : 'The cash assistance will be recorded as received.' ?>" data-dialog-confirm="Mark as Given" data-dialog-dismiss="Cancel">Mark as Given</button>
                </form>
            </section>
        <?php endif; ?>
        <?php if ($can_manage && $entry['status'] !== 'void'): ?>
            <section class="resident-detail-section">
                <h2>Void This Record</h2>
                <p class="drr-hint">Only for a record made by mistake. The record is kept as Void<?= $entry['status'] === 'given' && $lines !== [] ? ' and its items are returned to Inventory stock' : '' ?>.</p>
                <form method="post" action="assistance_action.php" class="ast-action-form">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $entry['id']) ?>"><input type="hidden" name="action" value="void">
                    <label class="form-label" for="reason">Reason <span class="resident-required" aria-hidden="true">*</span></label>
                    <input class="form-control" id="reason" name="reason" minlength="5" maxlength="255" required placeholder="e.g. Recorded for the wrong household">
                    <button class="btn btn-outline-danger drr-cancel-btn" type="submit" data-form-confirm="custom" data-dialog-heading="Void this record?" data-dialog-message="The record is kept as Void and cannot be edited again.<?= $entry['status'] === 'given' && $lines !== [] ? ' Its items will be added back to Inventory stock.' : '' ?>" data-dialog-confirm="Void Record" data-dialog-dismiss="Keep" data-dialog-danger="true">Void Record</button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</article>
<?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
