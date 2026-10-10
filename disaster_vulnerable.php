<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_prep.php';
require_once __DIR__ . '/includes/live_search.php';
disaster_require_view();
if (!disaster_can_view_vulnerable()) { http_response_code(403); exit('Access denied.'); }   // names of vulnerable residents: not the Treasurer
$connection = db();

// Vulnerable Residents: an automatic, read-only list from the Residents module (active residents only), grouped by
// Purok. Senior citizens (60+) and children under 5 come from the birth date; PWD and solo parents from the resident
// profile checkboxes; "Priority" from the Health records (the label only; the reason is shown only to Health Workers).
// Nothing is stored by this page.
$state = disaster_vulnerable_state($_GET);
$rows = disaster_vulnerable_rows($connection, $state);
$groups = disaster_vulnerable_grouped($rows);
$unknown = disaster_vulnerable_unknown_count($connection);
$query = http_build_query(array_filter($state, static fn ($v): bool => $v !== ''));
$filtered = $state['q'] !== '' || $state['purok'] !== '' || $state['group'] !== '';
$sector_ready = residents_sector_ready($connection);
$group_badge = static fn (array $row): string => implode(' ', array_map(static fn (string $group): string => '<span class="drr-group drr-group-' . e($group) . '">' . e(disaster_vulnerable_short_label($group)) . '</span>', $row['vulnerable_groups']));

$render_results = static function () use ($rows, $groups, $filtered, $group_badge, $query, $sector_ready): void { ?>
    <div class="drr-results-head">
        <p class="activity-history-meta"><?= e((string) count($rows)) ?> resident<?= count($rows) === 1 ? '' : 's' ?> · <?= e(disaster_vulnerable_summary(disaster_vulnerable_counts($rows))) ?></p>
        <a class="btn btn-sm btn-outline-secondary" href="disaster_vulnerable_print.php<?= $query !== '' ? '?' . e($query) : '' ?>" target="_blank" rel="noopener">Print List</a>
    </div>
    <?php if ($rows === []): ?>
        <div class="dashboard-empty-state"><?= $filtered ? 'No matching residents found.' : ($sector_ready ? 'No active senior citizens, children under 5, PWD or solo parents are registered.' : 'No active senior citizens or children under 5 are registered.') ?></div>
    <?php else: foreach ($groups as $purok => $group): ?>
        <section class="drr-section">
            <h2 class="drr-section-title"><?= e($purok) ?> <span class="drr-count"><?= e((string) count($group['rows'])) ?></span> <span class="drr-muted drr-section-sub"><?= e(disaster_vulnerable_summary($group['counts'])) ?></span></h2>
            <div class="resident-table-wrap">
                <table class="resident-table drr-table">
                    <thead><tr><th scope="col">Name</th><th scope="col">Category</th><th scope="col">Age</th><th scope="col">Sex</th><th scope="col">Household</th><th scope="col">Address</th><th scope="col">Contact</th></tr></thead>
                    <tbody>
                    <?php foreach ($group['rows'] as $row): ?>
                        <tr>
                            <td class="resident-name"><a class="activity-detail-link" href="resident_view.php?id=<?= e((string) $row['id']) ?>"><?= e(residents_full_name($row)) ?></a></td>
                            <td><span class="drr-group-cell"><?= $group_badge($row) ?></span></td>
                            <td class="drr-nowrap"><?= e(disaster_age_label($row['birth_date'])) ?></td>
                            <td><?= e(residents_sex_labels()[$row['sex']] ?? '—') ?></td>
                            <td><?= e($row['household_no'] ?: '—') ?></td>
                            <td><?= e(residents_collapse($row['address'])) ?></td>
                            <td class="drr-nowrap"><?= e($row['contact_number'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="resident-cards">
                <?php foreach ($group['rows'] as $row): ?>
                    <li class="resident-card">
                        <div class="resident-card-top"><strong><a class="activity-detail-link" href="resident_view.php?id=<?= e((string) $row['id']) ?>"><?= e(residents_full_name($row)) ?></a></strong><span class="drr-group-cell"><?= $group_badge($row) ?></span></div>
                        <p><?= e(disaster_age_label($row['birth_date'])) ?> · <?= e(residents_sex_labels()[$row['sex']] ?? '—') ?><?= $row['household_no'] ? ' · ' . e($row['household_no']) : '' ?></p>
                        <p><?= e(residents_collapse($row['address'])) ?><?= $row['contact_number'] ? ' · ' . e($row['contact_number']) : '' ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; endif;
};
if (live_search_is_request()) live_search_respond($render_results);

$page_title = 'Vulnerable Residents'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><a class="announcement-back" href="disaster_hazards.php"><span aria-hidden="true">&larr;</span> Back to Risk Map</a><h1>Disaster Management</h1><p>Vulnerable residents: who needs priority help during evacuation, taken automatically from the Residents records.</p></div>
    </div>
    <?= disaster_tabs('vulnerable') ?>
    <div class="dashboard-status drr-note" role="note">
        <?php if ($sector_ready): ?>Senior citizens (60 and above) and children under 5 are listed from each resident's birth date; PWD and solo parents from the checkboxes on the resident profile (active residents only). A resident in more than one group is listed once. <?= disaster_priority_visible() ? '"Priority" residents are flagged from the Health records for priority help; the reason is kept with the Health Workers and no health information is shown.' : '' ?><?php else: ?>Senior citizens (60 and above) and children under 5 are listed from each resident's birth date (active residents only).
        <strong>PWD and solo parents cannot be listed yet</strong> because resident profiles do not record them.<?php endif; ?>
        <?php if ($unknown > 0): ?><?= e((string) $unknown) ?> active resident<?= $unknown === 1 ? ' has' : 's have' ?> no birth date and cannot be checked.<?php endif; ?>
    </div>
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters drr-filters" method="get" action="disaster_vulnerable.php" data-live-search data-live-target="#drr-vulnerable-results">
            <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="vulnerable-search">Search</label><input class="activity-filter-input" type="search" id="vulnerable-search" name="q" value="<?= e($state['q']) ?>" maxlength="100" placeholder="Name or address" autocomplete="off" data-live-query></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="vulnerable-purok">Purok</label><select class="activity-filter-select" id="vulnerable-purok" name="purok"><option value="">All</option><?php foreach (disaster_vulnerable_puroks($connection) as $purok): ?><option value="<?= e($purok) ?>" <?= $state['purok'] === $purok ? 'selected' : '' ?>><?= e(residents_purok_label($purok)) ?></option><?php endforeach; ?></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="vulnerable-group">Category</label><select class="activity-filter-select" id="vulnerable-group" name="group"><option value="">All</option><?php foreach (disaster_vulnerable_groups() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['group'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button><a class="btn btn-outline-secondary btn-sm" href="disaster_vulnerable.php" data-live-reset>Reset</a></div>
        </form>
        <div id="drr-vulnerable-results" class="live-search-results"><?php $render_results(); ?></div>
    </section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
