<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
finance_require('view');
$connection = db();
$ready = finance_ready($connection) && finance_budget_ready($connection);

// Annual budget per Expense category: appropriated (set by the Treasurer), released, awaiting approval or release,
// remaining and percentage used. The System Administrator sees the same figures read-only.
$current_year = (int) date('Y');
$year = filter_var($_GET['year'] ?? $_POST['year'] ?? $current_year, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => $current_year + 1]]) ?: $current_year;
$errors = [];
$inputs = [];
$form_error = null;

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    finance_require('budget');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('finance_error', 'Your session expired. Please try again.'); redirect('finance_budget.php?year=' . $year); }
    $inputs = array_map(static fn ($v): string => trim((string) $v), (array) ($_POST['appropriated'] ?? []));
    $categories = [];
    foreach (finance_categories($connection, 'expense', null, true) as $category) $categories[(int) $category['id']] = $category;
    $amounts = [];
    foreach ($inputs as $category_id => $raw) {
        $category_id = (int) $category_id;
        if (!isset($categories[$category_id])) continue;
        if ($raw === '' || preg_match('/^0+(\.0{1,2})?$/', str_replace(',', '', $raw))) { $amounts[$category_id] = '0.00'; continue; }
        $amount = finance_parse_amount($raw);
        if ($amount === null) $errors[$category_id] = 'Enter an amount of zero or more, with up to two decimals.';
        else $amounts[$category_id] = $amount;
    }
    if ($errors === []) {
        try {
            $connection->beginTransaction();
            $existing = $connection->prepare('SELECT category_id, appropriated FROM finance_budgets WHERE budget_year = :year FOR UPDATE');
            $existing->execute(['year' => $year]);
            $old = $existing->fetchAll(PDO::FETCH_KEY_PAIR);
            $changes = [];
            $upsert = $connection->prepare('INSERT INTO finance_budgets (budget_year, category_id, appropriated, created_by, updated_by) VALUES (:year, :category, :amount, :user, :updater) ON DUPLICATE KEY UPDATE appropriated = VALUES(appropriated), updated_by = VALUES(updated_by)');
            foreach ($amounts as $category_id => $amount) {
                $before = $old[$category_id] ?? null;
                if ($before === null && $amount === '0.00') continue;
                if ($before !== null && finance_cents($before) === finance_cents($amount)) continue;
                $upsert->execute(['year' => $year, 'category' => $category_id, 'amount' => $amount, 'user' => current_user()['id'], 'updater' => current_user()['id']]);
                $changes[$categories[$category_id]['name']] = ['old' => $before, 'new' => $amount];
            }
            if ($changes !== []) residents_audit($connection, 'finance_budget', $year, 'finance_budget_updated', ['name' => 'Budget ' . $year, 'changed_fields' => array_keys($changes), 'changes' => $changes]);
            $connection->commit();
            flash('finance_success', $changes === [] ? 'No changes were made to the ' . $year . ' budget.' : 'The ' . $year . ' budget was saved (' . count($changes) . ' categor' . (count($changes) === 1 ? 'y' : 'ies') . ' changed).');
            redirect('finance_budget.php?year=' . $year);
        } catch (PDOException) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = 'The budget could not be saved. No changes were made.';
        }
    }
}

$rows = $ready ? finance_budget_rows($connection, $year) : [];
$totals = ['appropriated' => 0, 'released' => 0, 'awaiting' => 0];
foreach ($rows as $row) foreach ($totals as $key => $value) $totals[$key] += finance_cents($row[$key]);
$editable = $ready && finance_can('budget');
$years = range($current_year + 1, min($current_year - 5, $year));
$page_title = 'Budget ' . $year; $active_page = 'finance';
$page_styles = ['assets/css/finance.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Financial Management</h1><p>Annual budget per expense category. Released amounts count only disbursements that were paid out.</p></div>
    </div>
    <?= finance_nav('budget') ?>
    <?php if ($success = flash('finance_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('finance_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted amounts.</div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">The budget needs its database table before it can be recorded. No records have been changed.</div>
    <?php else: ?>
        <section class="dashboard-panel resident-list-panel">
            <form class="resident-filters fin-filters" method="get" action="finance_budget.php">
                <div class="resident-filter-group"><label class="activity-filter-label" for="budget-year">Year</label><select class="activity-filter-select" id="budget-year" name="year" data-fin-autosubmit><?php foreach ($years as $option): ?><option value="<?= e((string) $option) ?>" <?= $option === $year ? 'selected' : '' ?>><?= e((string) $option) ?></option><?php endforeach; ?></select></div>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-fin-autosubmit-button>Show</button></div>
            </form>
            <form method="post" action="finance_budget.php">
                <?= csrf_field() ?><input type="hidden" name="year" value="<?= e((string) $year) ?>">
                <div class="resident-table-wrap fin-budget-wrap">
                    <table class="resident-table fin-table fin-budget-table">
                        <thead><tr><th scope="col">Expense Category</th><th scope="col" class="fin-num">Appropriated</th><th scope="col" class="fin-num">Released</th><th scope="col" class="fin-num">Awaiting Release</th><th scope="col" class="fin-num">Remaining</th><th scope="col">Used</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): $percent = $row['percent']; $over = finance_cents($row['remaining']) < 0; ?>
                            <tr class="<?= $over ? 'fin-row-over' : '' ?>">
                                <td class="resident-name"><?= e($row['name']) ?><?= (int) $row['is_active'] === 1 ? '' : '<span class="fin-sub">Inactive category</span>' ?></td>
                                <td class="fin-num"><?php if ($editable): ?><input class="form-control form-control-sm fin-budget-input<?= isset($errors[(int) $row['id']]) ? ' is-invalid' : '' ?>" name="appropriated[<?= e((string) $row['id']) ?>]" inputmode="decimal" data-fin-amount maxlength="17" value="<?= e($inputs[(string) $row['id']] ?? $inputs[(int) $row['id']] ?? ($row['budget_id'] !== null ? $row['appropriated'] : '')) ?>" placeholder="0.00" aria-label="Appropriated for <?= e($row['name']) ?>"><?php if (isset($errors[(int) $row['id']])): ?><div class="invalid-feedback d-block"><?= e($errors[(int) $row['id']]) ?></div><?php endif; ?><?php else: ?><?= $row['budget_id'] !== null ? e(finance_peso($row['appropriated'])) : '<span class="activity-detail-muted">Not set</span>' ?><?php endif; ?></td>
                                <td class="fin-num"><?= e(finance_peso($row['released'])) ?></td>
                                <td class="fin-num"><?= e(finance_peso($row['awaiting'])) ?></td>
                                <td class="fin-num<?= $over ? ' fin-over' : '' ?>"><?= e(finance_peso($row['remaining'])) ?></td>
                                <td class="fin-used"><?php if ($percent === null): ?><span class="fin-over">No budget</span><?php else: ?><span class="fin-bar<?= $percent >= 100 ? ' is-over' : ($percent >= 80 ? ' is-high' : '') ?>"><span style="width: <?= e((string) min(100, $percent)) ?>%"></span></span><span class="fin-used-label"><?= e(number_format($percent, 1)) ?>%</span><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tbody class="fin-total-row"><tr><td>Total</td><td class="fin-num"><?= e(finance_peso(finance_from_cents($totals['appropriated']))) ?></td><td class="fin-num"><?= e(finance_peso(finance_from_cents($totals['released']))) ?></td><td class="fin-num"><?= e(finance_peso(finance_from_cents($totals['awaiting']))) ?></td><td class="fin-num"><?= e(finance_peso(finance_from_cents($totals['appropriated'] - $totals['released']))) ?></td><td><?= $totals['appropriated'] > 0 ? e(number_format($totals['released'] / $totals['appropriated'] * 100, 1)) . '%' : '' ?></td></tr></tbody>
                    </table>
                </div>
                <p class="fin-hint">Remaining = appropriated − released. "Awaiting Release" is money in disbursements that are pending approval or approved but not yet paid; a new disbursement gets a warning when it does not fit in what is left after these.</p>
                <?php if ($editable): ?><div class="form-actions"><button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Save the <?= e((string) $year) ?> budget?" data-dialog-message="Changes are recorded in the audit log with the old and new amounts." data-dialog-confirm="Save Budget" data-dialog-dismiss="Cancel">Save Budget</button></div><?php endif; ?>
            </form>
        </section>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/finance.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/finance.js')) ?>"></script>
