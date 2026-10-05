<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health_programs.php';
$connection = db();
health_programs_require($connection);

// Nutrition (Operation Timbang): the latest weighing of each child 0–59 months with the nutritional status from the WHO
// Child Growth Standards; "Needs follow-up" lists every child whose latest status is not Normal.
$status_filters = ['' => 'All weighed children', 'followup' => 'Needs follow-up (any not normal)', 'underweight' => 'Underweight (incl. severe)', 'stunted' => 'Stunted (incl. severe)', 'wasted' => 'Wasted (incl. severe)', 'overweight' => 'Overweight / obese', 'not_weighed' => 'Not yet weighed'];
$status = (string) ($_GET['status'] ?? '');
if (!array_key_exists($status, $status_filters)) $status = '';
$purok = (string) ($_GET['purok'] ?? '');
if ($purok !== '' && !in_array($purok, health_resident_puroks($connection), true)) $purok = '';
$q = mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100);
[$scope_sql, $params] = health_scope();
$where = [$scope_sql, health_under5_sql()];
if ($purok !== '') { $where[] = 'r.purok = :purok'; $params['purok'] = $purok; }
if ($q !== '') { $where[] = "CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name, r.suffix) LIKE :q"; $params['q'] = '%' . addcslashes($q, '%_\\') . '%'; }
$conditions = [
    'followup' => "(n.wfa_status <> 'normal' OR n.hfa_status IN ('severely_stunted', 'stunted') OR n.wfh_status <> 'normal')",
    'underweight' => "n.wfa_status IN ('underweight', 'severely_underweight')",
    'stunted' => "n.hfa_status IN ('stunted', 'severely_stunted')",
    'wasted' => "n.wfh_status IN ('wasted', 'severely_wasted')",
    'overweight' => "(n.wfa_status = 'overweight' OR n.wfh_status IN ('overweight', 'obese'))",
];
if ($status === 'not_weighed') {
    $sql = 'SELECT r.id AS resident_id, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.sex, r.purok, NULL AS id FROM residents r WHERE ' . implode(' AND ', $where) . ' AND NOT EXISTS (SELECT 1 FROM health_nutrition n0 WHERE n0.resident_id = r.id AND n0.archived_at IS NULL) ORDER BY r.purok, r.last_name LIMIT 300';
} else {
    $sql = 'SELECT n.*, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.sex, r.purok FROM health_nutrition n INNER JOIN residents r ON r.id = n.resident_id WHERE ' . implode(' AND ', $where) . ' AND ' . health_latest_weighing_sql() . (isset($conditions[$status]) ? ' AND ' . $conditions[$status] : '') . ' ORDER BY n.weigh_date DESC, r.last_name LIMIT 300';
}
$statement = $connection->prepare($sql);
$statement->execute($params);
$rows = $statement->fetchAll();
$who = health_growth_reference_ready($connection);
$page_title = 'Health · Nutrition'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <?= health_page_heading('Weighing of children 0–59 months (Operation Timbang).', 'health_nutrition_form.php', 'Record Weighing') ?>
    <?php if ($success = flash('health_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('health_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?= health_module_top($connection, 'nutrition') ?>
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters" method="get" action="health_nutrition.php">
            <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="n-q">Search</label><input class="activity-filter-input" type="search" id="n-q" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Child's name"></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="n-status">Show</label><select class="activity-filter-select" id="n-status" name="status"><?php foreach ($status_filters as $value => $label): ?><option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="n-purok">Purok</label><select class="activity-filter-select" id="n-purok" name="purok"><option value="">All</option><?php foreach (health_resident_puroks($connection) as $p): ?><option value="<?= e($p) ?>" <?= $purok === $p ? 'selected' : '' ?>><?= e(residents_purok_label($p)) ?></option><?php endforeach; ?></select></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit">Apply</button><a class="btn btn-outline-secondary btn-sm" href="health_nutrition.php">Reset</a></div>
        </form>
        <?php if ($rows === []): ?>
            <div class="dashboard-empty-state"><?= $status === 'not_weighed' ? 'Every child 0–59 months has a weighing.' : 'No weighings match.' ?></div>
        <?php elseif ($status === 'not_weighed'): ?>
            <p class="activity-history-meta"><?= e((string) count($rows)) ?> child<?= count($rows) === 1 ? '' : 'ren' ?> not yet weighed</p>
            <div class="resident-table-wrap health-keep-table"><table class="resident-table"><thead><tr><th scope="col">Child</th><th scope="col">Age</th><th scope="col">Sex</th><th scope="col">Purok</th><th scope="col" class="health-actions-head">Actions</th></tr></thead><tbody>
                <?php foreach ($rows as $row): ?><tr><td class="resident-name"><a class="activity-detail-link" href="health_history.php?resident=<?= e((string) $row['resident_id']) ?>"><?= e(residents_full_name($row)) ?></a></td><td><?= e(health_age_label($row['birth_date'])) ?></td><td><?= e(residents_sex_labels()[$row['sex']] ?? '—') ?></td><td><?= e(residents_purok_label((string) $row['purok'])) ?></td><td><a class="btn btn-sm btn-outline-primary" href="health_nutrition_form.php?resident=<?= e((string) $row['resident_id']) ?>">Record Weighing</a></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <p class="activity-history-meta"><?= e((string) count($rows)) ?> child<?= count($rows) === 1 ? '' : 'ren' ?> · latest weighing of each child</p>
            <div class="resident-table-wrap">
                <table class="resident-table">
                    <thead><tr><th scope="col">Child</th><th scope="col">Age at weighing</th><th scope="col">Purok</th><th scope="col">Weighed</th><th scope="col">Weight / height</th><th scope="col">Weight-for-age</th><th scope="col">Height-for-age</th><th scope="col">Weight-for-height</th><th scope="col" class="health-actions-head">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): $flagged = $row['wfa_status'] !== 'normal' || in_array($row['hfa_status'], ['stunted', 'severely_stunted'], true) || ($row['wfh_status'] !== null && $row['wfh_status'] !== 'normal'); ?>
                        <tr class="<?= $flagged ? 'health-row-due' : '' ?>">
                            <td class="resident-name"><a class="activity-detail-link" href="health_history.php?resident=<?= e((string) $row['resident_id']) ?>"><?= e(residents_full_name($row)) ?></a><span class="health-sub"><?= e(residents_sex_labels()[$row['sex']] ?? '') ?></span></td>
                            <td class="health-nowrap"><?= e((string) $row['age_months']) ?> mo</td>
                            <td class="health-nowrap"><?= e(residents_purok_label((string) $row['purok'])) ?></td>
                            <td class="health-nowrap"><?= e(health_format_date($row['weigh_date'])) ?></td>
                            <td class="health-nowrap"><?= e(rtrim(rtrim((string) $row['weight_kg'], '0'), '.') . ' kg · ' . rtrim(rtrim((string) $row['height_cm'], '0'), '.') . ' cm') ?></td>
                            <td><?= health_nutrition_badge($row['wfa_status'], health_wfa_statuses()) ?></td>
                            <td><?= health_nutrition_badge($row['hfa_status'], health_hfa_statuses()) ?></td>
                            <td><?= health_nutrition_badge($row['wfh_status'], health_wfh_statuses()) ?><?php if ($row['status_source'] === 'manual'): ?><span class="health-sub">From the chart</span><?php endif; ?></td>
                            <td><div class="management-actions health-actions"><a class="btn btn-sm btn-outline-primary" href="health_nutrition_form.php?resident=<?= e((string) $row['resident_id']) ?>">Weigh Again</a><?= health_program_archive_button('nutrition', $row) ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="resident-cards">
                <?php foreach ($rows as $row): ?>
                    <li class="resident-card"><div class="resident-card-top"><strong><a class="activity-detail-link" href="health_history.php?resident=<?= e((string) $row['resident_id']) ?>"><?= e(residents_full_name($row)) ?></a></strong><?= health_nutrition_badge($row['wfa_status'], health_wfa_statuses()) ?></div><p><?= e($row['age_months'] . ' mo · ' . health_format_date($row['weigh_date']) . ' · ' . rtrim(rtrim((string) $row['weight_kg'], '0'), '.') . ' kg · ' . rtrim(rtrim((string) $row['height_cm'], '0'), '.') . ' cm') ?></p><p>Height-for-age: <?= e(health_hfa_statuses()[$row['hfa_status']] ?? '—') ?> · Weight-for-height: <?= e(health_wfh_statuses()[$row['wfh_status']] ?? '—') ?></p><div class="management-actions health-actions"><a class="btn btn-sm btn-outline-primary" href="health_nutrition_form.php?resident=<?= e((string) $row['resident_id']) ?>">Weigh Again</a></div></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="activity-history-meta mt-2"><?= $who ? 'Nutritional status is computed from the WHO Child Growth Standards (z-scores; below −2 SD underweight / stunted / wasted, below −3 SD severe; above +2 SD overweight, above +3 SD obese for weight-for-height).' : 'The WHO growth tables are not loaded, so the status is chosen by the Health Worker from the official chart.' ?></p>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
