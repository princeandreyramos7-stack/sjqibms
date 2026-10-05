<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/assistance.php';
assistance_require_manage();
$connection = db();
if (!assistance_ready($connection)) { flash('assistance_error', 'Relief & Assistance needs its database update first.'); redirect('assistance.php'); }

// Claim List: every Scheduled record (for example the payout list made for all PWD or all Solo Parents). When a person —
// or a representative — claims it, enter who received it and select Given: the record becomes Given, the items are
// deducted from Inventory stock then, and the acknowledgment can be printed. People who do not come stay Scheduled
// (or are voided with a reason on their record). Filters: search (purpose, name, reference) and Purok.
$per_page = 50;
$state = [
    'q' => mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100),
    'purok' => residents_collapse((string) ($_GET['purok'] ?? '')),
    'page' => max(1, (int) ($_GET['page'] ?? 1)),
];
if ($state['purok'] !== '' && !in_array($state['purok'], disaster_vulnerable_puroks($connection), true)) $state['purok'] = '';
$where = ["a.status = 'scheduled'"];
$params = [];
if ($state['q'] !== '') {
    $like = '%' . addcslashes($state['q'], '%_\\') . '%';
    $where[] = "(a.purpose LIKE :q1 OR a.reference_no LIKE :q2 OR CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name, r.suffix) LIKE :q3 OR CONCAT_WS(' ', r.first_name, r.last_name) LIKE :q4 OR h.household_no LIKE :q5)";
    for ($i = 1; $i <= 5; $i++) $params["q$i"] = $like;
}
if ($state['purok'] !== '') { $where[] = 'COALESCE(r.purok, h.purok) = :purok'; $params['purok'] = $state['purok']; }
$from = substr(assistance_select(), strpos(assistance_select(), ' FROM ')) . ' WHERE ' . implode(' AND ', $where);
$count = $connection->prepare('SELECT COUNT(*)' . $from);
$count->execute($params);
$total = (int) $count->fetchColumn();
$total_pages = max(1, (int) ceil($total / $per_page));
$page = min($total_pages, $state['page']);
$statement = $connection->prepare(assistance_select() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY a.purpose, a.given_on, COALESCE(r.last_name, hr.last_name), COALESCE(r.first_name, hr.first_name), a.id LIMIT :limit OFFSET :offset');
foreach ($params as $key => $value) $statement->bindValue($key, $value);
$statement->bindValue('limit', $per_page, PDO::PARAM_INT);
$statement->bindValue('offset', ($page - 1) * $per_page, PDO::PARAM_INT);
$statement->execute();
$rows = $statement->fetchAll();
$lines = [];
if ($rows !== []) {
    $ids = implode(',', array_map(static fn (array $r): int => (int) $r['id'], $rows));
    foreach ($connection->query("SELECT x.distribution_id, x.quantity, x.unit, i.name FROM assistance_items x INNER JOIN inventory_items i ON i.id = x.item_id WHERE x.distribution_id IN ($ids) ORDER BY i.name")->fetchAll() as $line) $lines[(int) $line['distribution_id']][] = $line;
}
$query = http_build_query(array_filter(['q' => $state['q'], 'purok' => $state['purok'], 'page' => $page > 1 ? $page : ''], static fn ($v): bool => $v !== ''));
$return = 'assistance_claims.php' . ($query !== '' ? '?' . $query : '');
$today = date('Y-m-d');
$page_title = 'Claim List'; $active_page = 'assistance';
$page_styles = ['assets/css/disaster.css', 'assets/css/assistance.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><a class="announcement-back" href="assistance.php"><span aria-hidden="true">&larr;</span> Back to Relief &amp; Assistance</a><h1>Claim List</h1><p>Scheduled assistance waiting to be claimed. Mark each person as Given when they or their representative receive it.</p></div>
    </div>
    <?php if ($success = flash('assistance_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('assistance_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters drr-filters" method="get" action="assistance_claims.php">
            <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="claim-search">Search</label><input class="activity-filter-input" type="search" id="claim-search" name="q" value="<?= e($state['q']) ?>" maxlength="100" placeholder="Purpose, name, household or reference" autocomplete="off"></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="claim-purok">Purok</label><select class="activity-filter-select" id="claim-purok" name="purok"><option value="">All</option><?php foreach (disaster_vulnerable_puroks($connection) as $purok): ?><option value="<?= e($purok) ?>" <?= $state['purok'] === $purok ? 'selected' : '' ?>><?= e(residents_purok_label($purok)) ?></option><?php endforeach; ?></select></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit">Apply</button><a class="btn btn-outline-secondary btn-sm" href="assistance_claims.php">Reset</a></div>
        </form>
        <p class="activity-history-meta"><?= e(number_format($total)) ?> waiting to be claimed<?= $state['q'] !== '' || $state['purok'] !== '' ? ' (matching the filters)' : '' ?>.</p>
        <?php if ($rows === []): ?>
            <div class="dashboard-empty-state"><?= $state['q'] !== '' || $state['purok'] !== '' ? 'No matching scheduled assistance.' : 'No scheduled assistance is waiting to be claimed.' ?></div>
        <?php else: ?>
            <ul class="ast-claims">
                <?php foreach ($rows as $row): $household = $row['household_id'] !== null; $default = $household ? trim(residents_full_name(['first_name' => $row['head_first_name'], 'middle_name' => $row['head_middle_name'], 'last_name' => $row['head_last_name'], 'suffix' => $row['head_suffix']])) : residents_full_name($row); ?>
                    <li class="ast-claim">
                        <div class="ast-claim-info">
                            <strong><?= e(assistance_beneficiary_label($row)) ?></strong> <?= assistance_group_badges((string) $row['priority_groups'], false) ?>
                            <span><?= e(assistance_beneficiary_purok($row)) ?> · <?= e(assistance_category_label($row['assistance_type'])) ?> · <?= e(assistance_given_text(['cash_amount' => $row['cash_amount']], $lines[(int) $row['id']] ?? [])) ?></span>
                            <span class="activity-detail-muted"><?= e($row['reference_no']) ?> · <?= e(mb_strimwidth($row['purpose'], 0, 80, '…')) ?> · Scheduled <?= e(disaster_format_date($row['given_on'])) ?> · <a class="activity-detail-link" href="assistance_view.php?id=<?= e((string) $row['id']) ?>">View</a></span>
                        </div>
                        <form method="post" action="assistance_action.php" class="ast-claim-form">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $row['id']) ?>"><input type="hidden" name="action" value="mark_given"><input type="hidden" name="return" value="<?= e($return) ?>">
                            <label><span>Received by</span><input class="form-control form-control-sm" name="received_by" maxlength="150" value="<?= e($default) ?>" required aria-label="Received by for <?= e(assistance_beneficiary_label($row)) ?>"></label>
                            <label><span>Relationship <small>(if representative)</small></span><input class="form-control form-control-sm" name="receiver_relationship" maxlength="60" placeholder="e.g. Daughter" aria-label="Relationship"></label>
                            <label><span>Date given</span><input class="form-control form-control-sm" type="date" name="given_on" value="<?= e(min($today, max((string) $row['given_on'], date('Y-m-d', strtotime('-1 year'))))) ?>" max="<?= e($today) ?>" required aria-label="Date given"></label>
                            <button class="btn btn-sm btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Mark as Given?" data-dialog-message="<?= e(assistance_beneficiary_label($row)) ?> received <?= e(assistance_given_text(['cash_amount' => $row['cash_amount']], $lines[(int) $row['id']] ?? [])) ?>.<?= ($lines[(int) $row['id']] ?? []) !== [] ? ' The items are deducted from Inventory stock now.' : '' ?>" data-dialog-confirm="Mark as Given" data-dialog-dismiss="Cancel">Given</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($total_pages > 1): ?>
                <nav class="activity-pagination" aria-label="Claim list pagination">
                    <?php if ($page > 1): ?><a class="activity-page-btn" href="assistance_claims.php?<?= e(http_build_query(array_filter(['q' => $state['q'], 'purok' => $state['purok'], 'page' => $page - 1]))) ?>">&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                    <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                    <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="assistance_claims.php?<?= e(http_build_query(array_filter(['q' => $state['q'], 'purok' => $state['purok'], 'page' => $page + 1]))) ?>">Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
