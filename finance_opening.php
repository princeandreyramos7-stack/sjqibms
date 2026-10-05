<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
finance_require('view');
$connection = db();
$ready = finance_ready($connection) && finance_opening_ready($connection);

// Beginning balance: the barangay's fund balance on the day recording starts in the system. Set once by the Treasurer;
// never edited or deleted — a wrong entry is cancelled with a reason and a new one is entered. Collections and
// disbursements cannot be dated before it. The System Administrator sees it read-only.
$errors = [];
$form_error = null;
$values = ['as_of_date' => '', 'amount' => '', 'remarks' => ''];

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    finance_require('budget');
    $action = (string) ($_POST['action'] ?? '');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('finance_error', 'Your session expired. Please try again.'); redirect('finance_opening.php'); }
    try {
        $connection->beginTransaction();
        $current = $connection->query('SELECT * FROM finance_opening_balances WHERE cancelled_at IS NULL FOR UPDATE')->fetch() ?: null;
        if ($action === 'set') {
            $values = ['as_of_date' => trim((string) ($_POST['as_of_date'] ?? '')), 'amount' => str_replace([',', ' ', '₱'], '', trim((string) ($_POST['amount'] ?? ''))), 'remarks' => residents_collapse((string) ($_POST['remarks'] ?? ''))];
            if ($current !== null) throw new RuntimeException('A beginning balance is already set. Cancel it first (with a reason) to enter a new one.');
            $date = finance_valid_date($values['as_of_date']);
            if ($date === null) $errors['as_of_date'] = 'Enter a valid date.';
            elseif ($date > new DateTimeImmutable('today')) $errors['as_of_date'] = 'The date cannot be in the future.';
            elseif ($date < new DateTimeImmutable('2000-01-01')) $errors['as_of_date'] = 'Enter a realistic date.';
            else {
                $earlier = $connection->prepare("SELECT reference_no FROM finance_transactions WHERE status NOT IN ('cancelled', 'rejected') AND (transaction_date < :d1 OR (release_date IS NOT NULL AND release_date < :d2)) ORDER BY transaction_date LIMIT 1");
                $earlier->execute(['d1' => $values['as_of_date'], 'd2' => $values['as_of_date']]);
                if (($reference = $earlier->fetchColumn()) !== false) $errors['as_of_date'] = 'Transactions are already recorded before this date (for example ' . $reference . '). Choose a date on or before the first transaction.';
            }
            if (!preg_match('/^-?\d{1,11}(\.\d{1,2})?$/', $values['amount'])) $errors['amount'] = 'Enter the amount with up to two decimals (use a minus sign only for a real deficit).';
            if (mb_strlen($values['remarks']) > 255) $errors['remarks'] = 'Keep the remarks to 255 characters.';
            if ($errors !== []) throw new DomainException('invalid');
            $amount = finance_from_cents(finance_cents($values['amount']));
            $connection->prepare('INSERT INTO finance_opening_balances (as_of_date, amount, remarks, created_by) VALUES (:date, :amount, :remarks, :user)')->execute(['date' => $values['as_of_date'], 'amount' => $amount, 'remarks' => $values['remarks'] === '' ? null : $values['remarks'], 'user' => current_user()['id']]);
            residents_audit($connection, 'finance_opening', (int) $connection->lastInsertId(), 'finance_opening_set', ['name' => 'Beginning balance', 'changes' => ['amount' => ['old' => null, 'new' => $amount], 'as_of_date' => ['old' => null, 'new' => $values['as_of_date']]]]);
            $message = 'The beginning balance of ' . finance_peso($amount) . ' as of ' . finance_format_date($values['as_of_date']) . ' was recorded.';
        } elseif ($action === 'cancel') {
            $reason = residents_collapse((string) ($_POST['reason'] ?? ''));
            if ($current === null) throw new RuntimeException('There is no beginning balance to cancel.');
            if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) { $errors['reason'] = 'Give the reason for cancelling (5 to 255 characters).'; throw new DomainException('invalid'); }
            $connection->prepare('UPDATE finance_opening_balances SET cancelled_at = NOW(), cancelled_by = :user, cancel_reason = :reason WHERE id = :id')->execute(['user' => current_user()['id'], 'reason' => $reason, 'id' => $current['id']]);
            residents_audit($connection, 'finance_opening', (int) $current['id'], 'finance_opening_cancelled', ['name' => 'Beginning balance', 'reason' => $reason, 'changes' => ['amount' => ['old' => $current['amount'], 'new' => null]]]);
            $message = 'The beginning balance was cancelled. Enter the correct one below.';
        } else {
            throw new RuntimeException('This action is not available.');
        }
        $connection->commit();
        flash('finance_success', $message);
        redirect('finance_opening.php');
    } catch (DomainException) {
        if ($connection->inTransaction()) $connection->rollBack();
        $form_error = 'Please correct the highlighted fields.';
    } catch (PDOException) {
        if ($connection->inTransaction()) $connection->rollBack();
        $form_error = 'The beginning balance could not be saved. No changes were made.';
    } catch (RuntimeException $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        $form_error = $exception->getMessage();
    }
}

$current = $ready ? finance_opening($connection) : null;
$history = $ready ? $connection->query('SELECT o.*, u.name AS cancelled_by_name FROM finance_opening_balances o LEFT JOIN users u ON u.id = o.cancelled_by WHERE o.cancelled_at IS NOT NULL ORDER BY o.cancelled_at DESC')->fetchAll() : [];
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
$page_title = 'Beginning Balance'; $active_page = 'finance';
$page_styles = ['assets/css/finance.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading"><div><h1>Financial Management</h1><p>The fund balance on the day the barangay started recording in the system.</p></div></div>
    <?= finance_nav('opening') ?>
    <?php if ($success = flash('finance_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('finance_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">The beginning balance needs its database table first. No records have been changed.</div>
    <?php else: ?>
        <section class="dashboard-panel resident-form-panel">
            <?php if ($current !== null): ?>
                <dl class="activity-detail-list fin-opening">
                    <div><dt>Beginning balance</dt><dd class="fin-amount-big"><?= e(finance_peso($current['amount'])) ?></dd></div>
                    <div><dt>As of</dt><dd><?= e(finance_format_date($current['as_of_date'])) ?></dd></div>
                    <div><dt>Remarks</dt><dd><?= $current['remarks'] ? e($current['remarks']) : '<span class="activity-detail-muted">None</span>' ?></dd></div>
                    <div><dt>Recorded</dt><dd><?= e(finance_format_datetime($current['created_at'])) ?><?= $current['created_by_name'] ? ' · ' . e($current['created_by_name']) : '' ?></dd></div>
                </dl>
                <?php if (finance_can('budget')): ?>
                    <form method="post" class="fin-opening-cancel">
                        <?= csrf_field() ?><input type="hidden" name="action" value="cancel">
                        <label class="form-label" for="reason">Wrong amount or date? Cancel it with a reason, then enter the correct one.</label>
                        <textarea class="form-control<?= $field_class('reason') ?>" id="reason" name="reason" rows="2" maxlength="255" required></textarea><?= $field_error('reason') ?>
                        <button class="btn btn-outline-danger fin-section-btn" type="submit" data-form-confirm="custom" data-dialog-heading="Cancel the beginning balance?" data-dialog-message="It stays on record as cancelled. The fund balance will exclude it until a new one is entered." data-dialog-confirm="Cancel Beginning Balance" data-dialog-dismiss="Keep" data-dialog-danger="true">Cancel Beginning Balance</button>
                    </form>
                <?php endif; ?>
            <?php elseif (finance_can('budget')): ?>
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="set">
                    <fieldset class="resident-section">
                        <legend>Set Beginning Balance</legend>
                        <p class="fin-hint">Enter the cash and bank balance of the barangay fund on the first day recorded in the system. Collections and disbursements cannot be dated before this day.</p>
                        <div class="resident-grid">
                            <div><label class="form-label" for="as_of_date">As of date <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('as_of_date') ?>" type="date" id="as_of_date" name="as_of_date" value="<?= e($values['as_of_date']) ?>" max="<?= e(date('Y-m-d')) ?>" required data-summary-label="As of"><?= $field_error('as_of_date') ?></div>
                            <div><label class="form-label" for="amount">Amount (₱) <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control fin-amount-input<?= $field_class('amount') ?>" id="amount" name="amount" inputmode="decimal" data-fin-amount="signed" maxlength="18" value="<?= e($values['amount']) ?>" required placeholder="0.00" data-summary-label="Amount"><?= $field_error('amount') ?></div>
                            <div class="resident-grid-full"><label class="form-label" for="remarks">Remarks</label><input class="form-control<?= $field_class('remarks') ?>" id="remarks" name="remarks" maxlength="255" value="<?= e($values['remarks']) ?>" placeholder="e.g. Per cash count and bank statement"><?= $field_error('remarks') ?></div>
                        </div>
                    </fieldset>
                    <div class="form-actions"><button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="Record this beginning balance?" data-dialog-message="It cannot be edited afterwards; a wrong entry is cancelled with a reason." data-dialog-confirm="Record" data-dialog-dismiss="Cancel">Record Beginning Balance</button></div>
                </form>
            <?php else: ?>
                <div class="dashboard-empty-state">No beginning balance has been recorded by the Treasurer yet. The fund balance starts at ₱0.00.</div>
            <?php endif; ?>
            <?php if ($history !== []): ?>
                <h2 class="fin-section-title">Cancelled entries</h2>
                <ul class="resident-history"><?php foreach ($history as $old): ?><li><strong><?= e(finance_peso($old['amount'])) ?> as of <?= e(finance_format_date($old['as_of_date'])) ?></strong><span>Cancelled <?= e(finance_format_datetime($old['cancelled_at'])) ?><?= $old['cancelled_by_name'] ? ' by ' . e($old['cancelled_by_name']) : '' ?>: <?= e((string) $old['cancel_reason']) ?></span></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/finance.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/finance.js')) ?>"></script>
