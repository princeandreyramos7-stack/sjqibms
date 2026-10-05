<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health.php';
health_require_manage();
$connection = db();
if (!health_ready($connection) || !health_phase2_ready($connection)) { flash('health_error', 'The Morbidity Report needs the diagnosis list first.'); redirect('health.php'); }

// Morbidity Report: how many consultations per diagnosis in a day, week or month, by sex and age group. Health Workers
// only, within their assigned Puroks. Counts only; no names.
$state = health_morbidity_state($connection, $_GET);
$report = health_morbidity($connection, $state);
$query = health_morbidity_query($state);
$without = $report['without']['visits'] ?? 0;
$page_title = 'Morbidity Report'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <section class="dashboard-panel resident-list-panel">
        <a class="announcement-back" href="health.php"><span aria-hidden="true">&larr;</span> Back to Health</a>
        <div class="page-heading">
            <div><h1>Morbidity Report</h1><p>Consultations per diagnosis (Completed and Follow-up visits) for the chosen period.</p></div>
            <div class="resident-detail-actions"><a class="btn btn-light resident-action-btn" href="health_morbidity_print.php?<?= e($query) ?>" target="_blank" rel="noopener">Print Report</a></div>
        </div>
        <?php if (($scope = residents_purok_scope($connection)) !== null): ?><p class="activity-history-meta">Your assigned Puroks: <?= e(implode(', ', array_map('residents_purok_label', $scope))) ?>.</p><?php endif; ?>
        <form class="health-report-filters" method="get" action="health_morbidity.php" data-health-report>
            <div><label class="activity-filter-label" for="report-period">Period</label><select class="activity-filter-select" id="report-period" name="period" data-health-report-period><?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['period'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label class="activity-filter-label" for="report-date"><?= $state['period'] === 'month' ? 'Month' : ($state['period'] === 'week' ? 'Any day in the week' : 'Date') ?></label><input class="activity-filter-input" id="report-date" name="date" type="<?= $state['period'] === 'month' ? 'month' : 'date' ?>" value="<?= e($state['period'] === 'month' ? substr($state['date'], 0, 7) : $state['date']) ?>" required></div>
            <div><label class="activity-filter-label" for="report-purok">Purok</label><select class="activity-filter-select" id="report-purok" name="purok"><option value="">All<?= $scope !== null ? ' my Puroks' : '' ?></option><?php foreach (health_resident_puroks($connection) as $purok_value): ?><option value="<?= e($purok_value) ?>" <?= $state['purok'] === $purok_value ? 'selected' : '' ?>><?= e(residents_purok_label($purok_value)) ?></option><?php endforeach; ?></select></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit">Show</button></div>
        </form>
        <h2 class="h5 mb-3"><?= e($state['label']) ?><?= $state['purok'] !== '' ? ' · ' . e(residents_purok_label($state['purok'])) : '' ?></h2>
        <div class="health-report-cards">
            <div class="health-report-card"><strong><?= e(number_format($report['totals']['visits'])) ?></strong><span>Consultations with a diagnosis</span></div>
            <div class="health-report-card"><strong><?= e(number_format($report['totals']['residents'])) ?></strong><span>Residents seen</span></div>
            <div class="health-report-card"><strong><?= e(number_format(count($report['rows']))) ?></strong><span>Different diagnoses</span></div>
            <div class="health-report-card"><strong><?= e(number_format($without)) ?></strong><span>Visits without a diagnosis</span></div>
        </div>
        <?php if ($report['rows'] === []): ?>
            <div class="dashboard-empty-state">No consultations with a diagnosis in this period.<?= $without > 0 ? ' ' . e(number_format($without)) . ' visit' . ($without === 1 ? ' has' : 's have') . ' no diagnosis chosen.' : '' ?></div>
        <?php else: ?>
            <div class="resident-table-wrap health-keep-table"><?= health_morbidity_table($report, 'resident-table health-report-table') ?></div>
            <p class="activity-history-meta mt-2">Age is the age on the visit date (years). "Residents" in the total counts each resident once.<?= $without > 0 ? ' Not counted above: ' . e(number_format($without)) . ' visit' . ($without === 1 ? '' : 's') . ' without a diagnosis.' : '' ?></p>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script>
// Changing the period switches the date box (Month picker for Month) and shows the report again.
document.querySelector('[data-health-report-period]').addEventListener('change', (event) => event.target.form.submit());
</script>
