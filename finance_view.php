<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
finance_require('view');
$connection = db();
if (!finance_ready($connection)) { flash('finance_error', 'Financial Management needs its database tables first.'); redirect('finance.php'); }
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$transaction = $id ? finance_find($connection, $id) : null;
if ($transaction === null) { http_response_code(404); exit('Transaction not found.'); }

$can = finance_allowed_actions($transaction);
$income = $transaction['type'] === 'income';
$attachments = finance_attachments($connection, (int) $transaction['id']);
$muted = '<span class="activity-detail-muted">Not recorded</span>';
$form_errors = $_SESSION['finance_form_errors'] ?? [];
$form_values = $_SESSION['finance_form_values'] ?? [];
unset($_SESSION['finance_form_errors'], $_SESSION['finance_form_values']);
$field_error = static fn (string $field): string => isset($form_errors[$field]) ? '<div class="invalid-feedback d-block">' . e($form_errors[$field]) . '</div>' : '';
$field_class = static fn (string $field): string => isset($form_errors[$field]) ? ' is-invalid' : '';
$page_title = $transaction['reference_no']; $active_page = 'finance';
$page_styles = ['assets/css/finance.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($success = flash('finance_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if ($failure = flash('finance_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
<article class="dashboard-panel resident-detail">
    <div class="page-heading">
        <div>
            <a class="announcement-back" href="finance.php"><span aria-hidden="true">&larr;</span> Back to Financial Management</a>
            <span class="eyebrow"><?= e($transaction['reference_no']) ?> · <?= e(finance_types()[$transaction['type']]) ?></span>
            <h1><?= e($transaction['description']) ?></h1>
            <p><?= finance_status_badge($transaction['status']) ?> <span class="fin-amount-big<?= in_array($transaction['status'], ['cancelled', 'rejected'], true) ? ' fin-void' : '' ?>"><?= e(finance_peso($transaction['amount'])) ?></span></p>
        </div>
        <div class="resident-detail-actions">
            <?php if ($can['edit']): ?><a class="btn announcement-edit-btn" href="finance_form.php?id=<?= e((string) $transaction['id']) ?>">Edit</a><?php endif; ?>
            <?php if ($can['approve']): ?><form method="post" action="finance_action.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $transaction['id']) ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="back" value="view"><button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Approve this disbursement?" data-dialog-message="<?= e(finance_peso($transaction['amount']) . ' to ' . $transaction['payor_or_payee'] . '. The Treasurer will be notified and can release the payment.') ?>" data-dialog-confirm="Approve" data-dialog-dismiss="Cancel">Approve</button></form><?php endif; ?>
        </div>
    </div>
    <?php if ($transaction['status'] === 'pending_approval'): ?><p class="dashboard-status warning" role="status">Waiting for the System Administrator's approval.<?= $can['approve'] ? ' Approve it above, or reject it below with a reason.' : '' ?></p><?php endif; ?>
    <?php if (!$income && in_array($transaction['status'], ['pending_approval', 'approved'], true) && ($budget_warning = finance_budget_check($connection, (int) $transaction['category_id'], (int) substr((string) $transaction['transaction_date'], 0, 4), (string) $transaction['amount'], (int) $transaction['id'])) !== null): ?>
        <p class="dashboard-status warning fin-budget-warning" role="alert"><strong>Budget warning.</strong> <?= e(finance_budget_message($budget_warning, $transaction['category_name'])) ?><?= $can['approve'] ? ' You may still approve it.' : '' ?></p>
    <?php endif; ?>
    <?php if ($transaction['status'] === 'rejected'): ?><p class="dashboard-status warning" role="status">Rejected on <?= e(finance_format_datetime($transaction['rejected_at'])) ?><?= $transaction['rejected_by_name'] ? ' by ' . e($transaction['rejected_by_name']) : '' ?>: <?= e((string) $transaction['reject_reason']) ?></p><?php endif; ?>
    <?php if ($transaction['status'] === 'cancelled'): ?><p class="dashboard-status warning" role="status">Cancelled on <?= e(finance_format_datetime($transaction['cancelled_at'])) ?><?= $transaction['cancelled_by_name'] ? ' by ' . e($transaction['cancelled_by_name']) : '' ?>: <?= e((string) $transaction['cancel_reason']) ?>. It is excluded from all totals.</p><?php endif; ?>

    <div class="resident-detail-grid">
        <section class="resident-detail-section">
            <h2>Transaction</h2>
            <dl class="activity-detail-list">
                <div><dt>Reference no.</dt><dd><?= e($transaction['reference_no']) ?></dd></div>
                <div><dt>Type</dt><dd><?= e(finance_types()[$transaction['type']]) ?></dd></div>
                <div><dt>Date</dt><dd><?= e(finance_format_date($transaction['transaction_date'])) ?></dd></div>
                <div><dt>Category</dt><dd><?= e($transaction['category_name']) ?></dd></div>
                <div><dt>Amount</dt><dd><?= e(finance_peso($transaction['amount'])) ?></dd></div>
                <div><dt><?= $income ? 'Payor' : 'Payee' ?></dt><dd><?= e($transaction['payor_or_payee']) ?></dd></div>
                <div><dt>Resident</dt><dd><?= $transaction['resident_id'] ? e(residents_full_name($transaction)) . ' (#' . e((string) $transaction['resident_id']) . ')' : '<span class="activity-detail-muted">Not linked</span>' ?></dd></div>
                <div><dt>Mode of payment</dt><dd><?= $transaction['payment_mode'] ? e(finance_payment_modes()[$transaction['payment_mode']] ?? '') . ($transaction['check_no'] ? ' · Check no. ' . e($transaction['check_no']) : '') : ($income ? $muted : '<span class="activity-detail-muted">Recorded on release</span>') ?></dd></div>
                <div><dt>Remarks</dt><dd><?= $transaction['remarks'] ? nl2br(e($transaction['remarks'])) : '<span class="activity-detail-muted">None</span>' ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Approval Trail</h2>
            <dl class="activity-detail-list">
                <div><dt><?= $income ? 'Posted' : 'Prepared' ?></dt><dd><?= e(finance_format_datetime($transaction['created_at'])) ?><?= $transaction['created_by_name'] ? ' · ' . e($transaction['created_by_name']) : '' ?></dd></div>
                <?php if (!$income): ?>
                    <div><dt>Approved</dt><dd><?= $transaction['approved_at'] ? e(finance_format_datetime($transaction['approved_at'])) . ($transaction['approved_by_name'] ? ' · ' . e($transaction['approved_by_name']) : '') : '<span class="activity-detail-muted">—</span>' ?></dd></div>
                    <div><dt>Released</dt><dd><?= $transaction['released_at'] ? e(finance_format_date($transaction['release_date'])) . ($transaction['released_by_name'] ? ' · ' . e($transaction['released_by_name']) : '') : '<span class="activity-detail-muted">—</span>' ?></dd></div>
                <?php endif; ?>
                <?php if ($transaction['status'] === 'cancelled'): ?><div><dt>Cancelled</dt><dd><?= e(finance_format_datetime($transaction['cancelled_at'])) ?><?= $transaction['cancelled_by_name'] ? ' · ' . e($transaction['cancelled_by_name']) : '' ?></dd></div><?php endif; ?>
            </dl>
        </section>

        <section class="resident-detail-section">
            <h2>Attachments</h2>
            <?php if ($attachments === []): ?><p class="resident-static">No receipts or supporting documents attached.</p><?php else: ?>
                <ul class="resident-history fin-attachments">
                    <?php foreach ($attachments as $attachment): ?>
                        <li><strong><a class="activity-detail-link" href="finance_attachment.php?id=<?= e((string) $attachment['id']) ?>" target="_blank" rel="noopener"><?= e($attachment['original_name']) ?></a></strong><span><?= e(strtoupper(finance_attachment_types()[$attachment['mime_type']] ?? '')) ?> · <?= e(number_format($attachment['file_size'] / 1024, 0)) ?> KB · <?= e(finance_format_datetime($attachment['created_at'])) ?><?= $attachment['uploaded_by_name'] ? ' · ' . e($attachment['uploaded_by_name']) : '' ?></span>
                            <?php if ($can['attach']): ?><form method="post" action="finance_action.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $transaction['id']) ?>"><input type="hidden" name="attachment_id" value="<?= e((string) $attachment['id']) ?>"><input type="hidden" name="action" value="remove_attachment"><button class="btn btn-sm btn-link fin-link-danger" type="submit" data-form-confirm="custom" data-dialog-heading="Remove this attachment?" data-dialog-message="It will no longer be listed. The file is kept in the records." data-dialog-confirm="Remove" data-dialog-dismiss="Cancel" data-dialog-danger="true">Remove</button></form><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($can['attach']): ?>
                <form method="post" action="finance_action.php" enctype="multipart/form-data" class="fin-upload">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $transaction['id']) ?>"><input type="hidden" name="action" value="attach"><input type="hidden" name="MAX_FILE_SIZE" value="<?= e((string) FINANCE_ATTACHMENT_MAX_BYTES) ?>">
                    <label class="form-label" for="attachment">Add a receipt or supporting document <span class="activity-detail-muted">(JPG, PNG, WebP or PDF, up to 10 MB)</span></label>
                    <div class="fin-upload-row"><input class="form-control<?= $field_class('attachment') ?>" type="file" id="attachment" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf" required><button class="btn btn-outline-primary" type="submit">Upload</button></div>
                    <?= $field_error('attachment') ?>
                </form>
            <?php endif; ?>
        </section>

        <?php if ($can['reject']): ?>
            <section class="resident-detail-section" id="reject">
                <h2>Reject Disbursement</h2>
                <form method="post" action="finance_action.php">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $transaction['id']) ?>"><input type="hidden" name="action" value="reject">
                    <label class="form-label" for="reject_reason">Reason <span class="resident-required" aria-hidden="true">*</span></label>
                    <textarea class="form-control<?= $field_class('reject_reason') ?>" id="reject_reason" name="reason" rows="2" maxlength="255" required><?= e((string) ($form_values['reject_reason'] ?? '')) ?></textarea><?= $field_error('reject_reason') ?>
                    <button class="btn btn-outline-danger fin-section-btn" type="submit" data-form-confirm="custom" data-dialog-heading="Reject this disbursement?" data-dialog-message="The Treasurer will be notified with your reason. A rejected disbursement cannot be released." data-dialog-confirm="Reject" data-dialog-dismiss="Cancel" data-dialog-danger="true">Reject</button>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($can['release']): ?>
            <section class="resident-detail-section" id="release">
                <h2>Release Payment</h2>
                <form method="post" action="finance_action.php" data-fin-release-form>
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $transaction['id']) ?>"><input type="hidden" name="action" value="release">
                    <div class="resident-grid">
                        <div><label class="form-label" for="release_mode">Mode of payment</label><input class="form-control fin-fixed-field" id="release_mode" value="Cash" readonly tabindex="-1"><input type="hidden" name="payment_mode" value="cash"><div class="form-text">Disbursements are released in cash only.</div><?= $field_error('payment_mode') ?></div>
                        <div><label class="form-label" for="release_date">Release date <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('release_date') ?>" type="date" id="release_date" name="release_date" value="<?= e((string) ($form_values['release_date'] ?? date('Y-m-d'))) ?>" max="<?= e(date('Y-m-d')) ?>" required><?= $field_error('release_date') ?></div>
                    </div>
                    <button class="btn btn-primary fin-section-btn" type="submit" data-form-confirm="custom" data-dialog-heading="Release this payment?" data-dialog-message="<?= e(finance_peso($transaction['amount']) . ' to ' . $transaction['payor_or_payee'] . ' will be recorded as paid.') ?>" data-dialog-confirm="Release" data-dialog-dismiss="Cancel">Mark as Released</button>
                </form>
            </section>
        <?php endif; ?>

        <?php if (finance_can('audit')): $history = finance_history($connection, (int) $transaction['id']); ?>
            <section class="resident-detail-section fin-history" id="history">
                <h2>History</h2>
                <?php if ($history === []): ?><p class="resident-static">No history recorded.</p><?php else: ?>
                    <ol class="fin-history-list">
                        <?php foreach ($history as $entry): ?>
                            <li>
                                <div class="fin-history-head"><strong><?= e($entry['label']) ?></strong><span><?= e(finance_format_datetime($entry['at'])) ?> · <?= e($entry['user']) ?><?= $entry['role'] !== '' ? ' (' . e($entry['role']) . ')' : '' ?></span></div>
                                <?php foreach ($entry['notes'] as $note): ?><p class="fin-history-note"><?= e($note) ?></p><?php endforeach; ?>
                                <?php if ($entry['changes'] !== []): ?>
                                    <table class="fin-history-table"><thead><tr><th scope="col">Field</th><th scope="col">Old value</th><th scope="col">New value</th></tr></thead><tbody>
                                        <?php foreach ($entry['changes'] as [$field, $old, $new]): ?><tr><td><?= e($field) ?></td><td><?= $old === '' ? '<span class="activity-detail-muted">—</span>' : e($old) ?></td><td><?= e($new) ?></td></tr><?php endforeach; ?>
                                    </tbody></table>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($can['cancel']): ?>
            <section class="resident-detail-section" id="cancel">
                <h2>Cancel Transaction</h2>
                <p class="fin-hint">Financial records are never deleted. A cancelled transaction stays on record and is excluded from all totals.</p>
                <form method="post" action="finance_action.php">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $transaction['id']) ?>"><input type="hidden" name="action" value="cancel">
                    <label class="form-label" for="cancel_reason">Reason <span class="resident-required" aria-hidden="true">*</span></label>
                    <textarea class="form-control<?= $field_class('cancel_reason') ?>" id="cancel_reason" name="reason" rows="2" maxlength="255" required><?= e((string) ($form_values['cancel_reason'] ?? '')) ?></textarea><?= $field_error('cancel_reason') ?>
                    <button class="btn btn-outline-danger fin-section-btn" type="submit" data-form-confirm="custom" data-dialog-heading="Cancel this transaction?" data-dialog-message="This cannot be undone. The record stays with the status Cancelled." data-dialog-confirm="Cancel Transaction" data-dialog-dismiss="Keep" data-dialog-danger="true">Cancel Transaction</button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</article>
<?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/finance.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/finance.js')) ?>"></script>
