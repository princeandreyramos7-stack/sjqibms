<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/assistance.php';
require_once __DIR__ . '/includes/live_search.php';
assistance_require_view();
$connection = db();
$ready = assistance_ready($connection);

// Relief & Assistance: every relief and assistance record (disaster relief linked to an incident, and regular ayuda).
// Filters: search, form (cash / in-kind), priority group (snapshot on the record), category, source, incident, date range
// and status (All = Scheduled and Given; Void only when chosen). The cards follow the filters (see assistance_summary()).
// When an incident is chosen, the evacuated families that have no record for it yet are listed first.
$per_page = 15;
$state = assistance_list_state($_GET);
$can_manage = assistance_can_manage();
$rows = [];
$lines = [];
$summary = ['records' => 0, 'beneficiaries' => 0, 'cash' => 0, 'items' => 0];
$waiting_summary = null;
$unserved = [];
$total = 0;
$page = 1;
$total_pages = 1;
if ($ready) {
    [$where, $params] = assistance_list_where($state);
    $from = substr(assistance_select(), strpos(assistance_select(), ' FROM ')) . " WHERE $where";
    $count = $connection->prepare('SELECT COUNT(*)' . $from);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $total_pages = max(1, (int) ceil($total / $per_page));
    $page = max(1, min($total_pages, $state['page']));
    $statement = $connection->prepare(assistance_select() . " WHERE $where ORDER BY a.given_on DESC, a.id DESC LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) $statement->bindValue($key, $value);
    $statement->bindValue('limit', $per_page, PDO::PARAM_INT);
    $statement->bindValue('offset', ($page - 1) * $per_page, PDO::PARAM_INT);
    $statement->execute();
    $rows = $statement->fetchAll();
    if ($rows !== []) {
        $ids = implode(',', array_map(static fn (array $r): int => (int) $r['id'], $rows));
        foreach ($connection->query("SELECT x.distribution_id, x.quantity, x.unit, i.name FROM assistance_items x INNER JOIN inventory_items i ON i.id = x.item_id WHERE x.distribution_id IN ($ids) ORDER BY i.name")->fetchAll() as $line) $lines[(int) $line['distribution_id']][] = $line;
    }
    $summary = assistance_summary($connection, $state);
    // With Status "All", the cards count Given records; the Scheduled ones still waiting are shown under the cards.
    $waiting_summary = $state['status'] === '' ? assistance_summary($connection, array_merge($state, ['status' => 'scheduled'])) : null;
    if (ctype_digit($state['incident'])) $unserved = assistance_unserved($connection, (int) $state['incident']);
}
$query = static function (array $changes = []) use ($state, $page): string {
    $values = array_merge($state, ['page' => $page], $changes);
    if ((int) $values['page'] <= 1) $values['page'] = '';
    return http_build_query(array_filter($values, static fn ($v): bool => $v !== null && $v !== ''));
};
$url = static fn (array $changes = []): string => 'assistance.php' . (($q = $query($changes)) !== '' ? '?' . $q : '');
$filtered = array_filter(array_diff_key($state, ['page' => 1]), static fn ($v): bool => $v !== '') !== [];
$first = $total === 0 ? 0 : ($page - 1) * $per_page + 1;
$last = min($total, $page * $per_page);
$amount_text = static fn (array $row): string => assistance_given_text(['cash_amount' => $row['cash_amount']], $lines[(int) $row['id']] ?? []);
$card_scope = $state['status'] === '' ? 'Given records' : (assistance_statuses()[$state['status']] ?? '') . ' records';

$card_word = $state['status'] === '' ? 'Given' : (assistance_statuses()[$state['status']] ?? 'Given');
$render_results = static function () use ($rows, $summary, $waiting_summary, $card_word, $unserved, $state, $total, $first, $last, $page, $total_pages, $url, $filtered, $amount_text, $can_manage, $card_scope): void {
    if (ctype_digit($state['incident'])): ?>
        <section class="drr-section drr-unserved">
            <h2 class="drr-section-title">Evacuated families not yet served <span class="drr-count"><?= e((string) count($unserved)) ?></span></h2>
            <?php if ($unserved === []): ?>
                <p class="drr-hint">Every family checked in for this incident has a relief record (or no family was checked in).</p>
            <?php else: ?>
                <ul class="case-link-list drr-unserved-list">
                    <?php foreach ($unserved as $family): ?>
                        <li><div><strong><?= e((string) $family['family_label']) ?></strong><br><span class="activity-detail-muted"><?= e((string) $family['persons']) ?> person<?= (int) $family['persons'] === 1 ? '' : 's' ?> · <?= e((string) $family['centers']) ?><?= (int) $family['in_center'] === 1 ? ' · in center now' : ' · departed' ?></span></div><?php if ($can_manage): ?><a class="btn btn-sm btn-outline-primary" href="assistance_form.php?incident=<?= e($state['incident']) ?>&amp;<?= $family['household_id'] !== null ? 'household=' . e((string) $family['household_id']) : 'resident=' . e((string) $family['resident_id']) ?>">Give Relief</a><?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <div class="ast-summary" role="list" aria-label="Summary (<?= e($card_scope) ?>)">
        <div class="ast-summary-card" role="listitem"><span class="ast-summary-value"><?= e(number_format((int) $summary['records'])) ?></span><span class="ast-summary-label">Total <?= e($card_word) ?> Records</span></div>
        <div class="ast-summary-card" role="listitem"><span class="ast-summary-value"><?= e(number_format((int) $summary['beneficiaries'])) ?></span><span class="ast-summary-label">Residents / Households <?= $card_word === 'Given' ? 'Helped' : '(' . e($card_word) . ')' ?></span></div>
        <div class="ast-summary-card" role="listitem"><span class="ast-summary-value"><?= e(assistance_peso($summary['cash'])) ?></span><span class="ast-summary-label">Total Cash <?= e($card_word) ?></span></div>
        <div class="ast-summary-card" role="listitem"><span class="ast-summary-value"><?= e(number_format((int) $summary['items'])) ?></span><span class="ast-summary-label">Items <?= $card_word === 'Given' ? 'Distributed' : '(' . e($card_word) . ')' ?></span></div>
    </div>
    <?php if ($waiting_summary !== null && (int) $waiting_summary['records'] > 0): ?><p class="ast-waiting"><strong><?= e(number_format((int) $waiting_summary['records'])) ?> Scheduled</strong> waiting to be claimed<?= (float) $waiting_summary['cash'] > 0 ? ' · ' . e(assistance_peso($waiting_summary['cash'])) . ' cash' : '' ?><?= (int) $waiting_summary['items'] > 0 ? ' · ' . e(number_format((int) $waiting_summary['items'])) . ' items' : '' ?>. They are counted in the cards once marked as Given.<?php if ($can_manage): ?> <a class="activity-detail-link" href="assistance_claims.php">Open the Claim List</a><?php endif; ?></p><?php endif; ?>
    <?php if ($total > 0): ?><p class="activity-history-meta">Showing <?= e((string) $first) ?>–<?= e((string) $last) ?> of <?= e((string) $total) ?> record<?= $total === 1 ? '' : 's' ?>. The totals count <?= e(strtolower($card_scope)) ?><?= $filtered ? ' matching the filters' : '' ?>.</p><?php endif; ?>
    <?php if ($rows === []): ?>
        <div class="dashboard-empty-state"><?= $filtered ? 'No matching assistance records found.' : 'No assistance records found.' . ($can_manage ? ' Select Give Assistance to record the first assistance.' : '') ?></div>
    <?php else: ?>
        <div class="resident-table-wrap ast-table-wrap">
            <table class="resident-table drr-table ast-table">
                <thead><tr><th scope="col">Reference No.</th><th scope="col">Beneficiary</th><th scope="col">Priority Group</th><th scope="col">Assistance</th><th scope="col">Amount / Quantity</th><th scope="col">Source</th><th scope="col">Date</th><th scope="col">Received By</th><th scope="col">Status</th><th scope="col" class="drr-actions-head">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="resident-name drr-nowrap"><?= e($row['reference_no']) ?></td>
                        <td class="ast-beneficiary"><?= e(assistance_beneficiary_label($row)) ?><span class="drr-sub drr-muted"><?= e(assistance_beneficiary_purok($row)) ?></span><?php if ($row['incident_reference']): ?><span class="drr-sub drr-muted drr-nowrap"><?= e($row['incident_reference']) ?></span><?php endif; ?></td>
                        <td><span class="drr-group-cell"><?= assistance_group_badges((string) $row['priority_groups']) ?></span></td>
                        <td><?= e(assistance_category_label($row['assistance_type'])) ?><span class="drr-sub drr-muted"><?= e(mb_strimwidth($row['purpose'], 0, 70, '…')) ?></span></td>
                        <td><?= e($amount_text($row)) ?></td>
                        <td><?= e($row['source'] ? (assistance_sources()[$row['source']] ?? '') : '—') ?><?= $row['source_details'] ? '<span class="drr-sub drr-muted">' . e($row['source_details']) . '</span>' : '' ?></td>
                        <td class="drr-nowrap"><?= e(disaster_format_date($row['given_on'])) ?><?= $row['status'] === 'scheduled' ? '<span class="drr-sub drr-muted">Scheduled</span>' : ($row['status'] === 'given' ? '<span class="drr-sub drr-muted">Given</span>' : '') ?></td>
                        <td><?= $row['received_by'] !== '' ? e($row['received_by']) : '<span class="activity-detail-muted">—</span>' ?></td>
                        <td><?= assistance_status_badge($row['status']) ?></td>
                        <td><div class="management-actions drr-actions ast-actions">
                            <a class="btn btn-sm btn-outline-primary" href="assistance_view.php?id=<?= e((string) $row['id']) ?>">View</a>
                            <?php if ($can_manage && $row['status'] !== 'void'): ?><a class="btn btn-sm btn-outline-primary" href="assistance_form.php?id=<?= e((string) $row['id']) ?>">Edit</a><?php endif; ?>
                            <?php if ($row['status'] === 'given'): ?><a class="btn btn-sm btn-outline-secondary" href="assistance_print.php?id=<?= e((string) $row['id']) ?>" target="_blank" rel="noopener">Print</a><?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($rows as $row): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><?= e($row['reference_no']) ?> · <?= e(assistance_beneficiary_label($row)) ?></strong><?= assistance_status_badge($row['status']) ?></div>
                    <p><span class="drr-group-cell"><?= assistance_group_badges((string) $row['priority_groups']) ?></span> <?= e(assistance_category_label($row['assistance_type'])) ?> · <?= e($amount_text($row)) ?></p>
                    <p><?= e(disaster_format_date($row['given_on'])) ?> · <?= e($row['source'] ? (assistance_sources()[$row['source']] ?? '') : '—') ?><?= $row['received_by'] !== '' ? ' · Received by ' . e($row['received_by']) : '' ?></p>
                    <div class="management-actions drr-actions ast-actions">
                        <a class="btn btn-sm btn-outline-primary" href="assistance_view.php?id=<?= e((string) $row['id']) ?>">View</a>
                        <?php if ($can_manage && $row['status'] !== 'void'): ?><a class="btn btn-sm btn-outline-primary" href="assistance_form.php?id=<?= e((string) $row['id']) ?>">Edit</a><?php endif; ?>
                        <?php if ($row['status'] === 'given'): ?><a class="btn btn-sm btn-outline-secondary" href="assistance_print.php?id=<?= e((string) $row['id']) ?>" target="_blank" rel="noopener">Print</a><?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <nav class="activity-pagination" aria-label="Assistance pagination">
                <?php if ($page > 1): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page - 1])) ?>" data-live-page>&larr; Previous</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span><?php endif; ?>
                <span class="activity-page-info">Page <?= e((string) $page) ?> of <?= e((string) $total_pages) ?></span>
                <?php if ($page < $total_pages): ?><a class="activity-page-btn" href="<?= e($url(['page' => $page + 1])) ?>" data-live-page>Next &rarr;</a><?php else: ?><span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif;
};
if ($ready && live_search_is_request()) live_search_respond($render_results);

$incidents = $ready ? disaster_all_incidents($connection) : [];
$page_title = 'Relief & Assistance'; $active_page = 'assistance';
$page_styles = ['assets/css/disaster.css', 'assets/css/assistance.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><h1>Relief &amp; Assistance</h1><p>Manage and monitor relief and social assistance provided to qualified residents and households, including cash, food, medical, educational, and other forms of assistance.</p></div>
        <?php if ($ready && $can_manage): $waiting = (int) $connection->query("SELECT COUNT(*) FROM assistance_distributions WHERE status = 'scheduled'")->fetchColumn(); ?><div class="resident-detail-actions ast-head-actions"><a class="btn btn-outline-primary ast-head-btn" href="assistance_claims.php">Claim List<?= $waiting > 0 ? ' (' . e(number_format($waiting)) . ')' : '' ?></a><a class="btn btn-primary announcements-new-btn" href="assistance_form.php<?= ctype_digit($state['incident']) ? '?incident=' . e($state['incident']) : '' ?>">Give Assistance</a></div><?php endif; ?>
    </div>
    <?php if ($success = flash('assistance_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('assistance_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Relief &amp; Assistance needs its database update before records can be shown. No records have been changed.</div>
    <?php else: ?>
        <section class="dashboard-panel resident-list-panel">
            <form class="resident-filters drr-filters ast-filters" method="get" action="assistance.php" data-live-search data-live-target="#ast-results" data-live-range="#ast-from,#ast-to">
                <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="ast-search">Search</label><input class="activity-filter-input" type="search" id="ast-search" name="q" value="<?= e($state['q']) ?>" maxlength="100" placeholder="Name, household, reference, purpose or receiver" autocomplete="off" data-live-query></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="ast-form">Type</label><select class="activity-filter-select" id="ast-form" name="form"><option value="">All</option><option value="cash" <?= $state['form'] === 'cash' ? 'selected' : '' ?>>Cash</option><option value="in_kind" <?= $state['form'] === 'in_kind' ? 'selected' : '' ?>>Inventory Item / In-kind</option></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="ast-group-filter">Priority group</label><select class="activity-filter-select" id="ast-group-filter" name="group"><option value="">All</option><?php foreach (assistance_priority_groups() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['group'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?><option value="general" <?= $state['group'] === 'general' ? 'selected' : '' ?>>All residents (no priority group)</option></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="ast-category">Category</label><select class="activity-filter-select" id="ast-category" name="category"><option value="">All</option><?php foreach (assistance_categories() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['category'] === $value ? 'selected' : '' ?>><?= e(str_replace(' Assistance', '', $label)) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="ast-source">Source</label><select class="activity-filter-select" id="ast-source" name="source"><option value="">All</option><?php foreach (assistance_sources() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['source'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="ast-incident">Incident</label><select class="activity-filter-select" id="ast-incident" name="incident"><option value="">All</option><option value="none" <?= $state['incident'] === 'none' ? 'selected' : '' ?>>Not disaster relief</option><?php foreach ($incidents as $incident): ?><option value="<?= e((string) $incident['id']) ?>" <?= $state['incident'] === (string) $incident['id'] ? 'selected' : '' ?>><?= e(disaster_incident_label($incident)) ?></option><?php endforeach; ?></select></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="ast-from">From</label><input class="activity-filter-input" type="date" id="ast-from" name="from" value="<?= e($state['from']) ?>"></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="ast-to">To</label><input class="activity-filter-input" type="date" id="ast-to" name="to" value="<?= e($state['to']) ?>"></div>
                <div class="resident-filter-group"><label class="activity-filter-label" for="ast-status">Status</label><select class="activity-filter-select" id="ast-status" name="status"><option value="">All</option><?php foreach (assistance_statuses() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['status'] === $value ? 'selected' : '' ?>><?= e($value === 'void' ? 'Void / Archived' : $label) ?></option><?php endforeach; ?></select></div>
                <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="assistance.php" data-live-reset>Reset</a></div>
            </form>
            <div id="ast-results" class="live-search-results"><?php $render_results(); ?></div>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
