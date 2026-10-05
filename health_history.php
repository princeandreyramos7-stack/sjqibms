<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health_programs.php';
health_require_manage();
$connection = db();
if (!health_ready($connection)) { flash('health_error', 'Health records need their database table first.'); redirect('health.php'); }

// Health history of one resident: every visit, newest first, with vital signs, diagnosis and medicines given. Health
// Workers only, within their assigned Puroks (health_resident()). Each opening is kept in the audit log (no details).
$resident_id = filter_var($_GET['resident'] ?? null, FILTER_VALIDATE_INT);
$resident = $resident_id ? health_resident($connection, $resident_id) : null;
if ($resident === null) { http_response_code(404); exit('Resident not found.'); }
$show_archived = (string) ($_GET['archived'] ?? '') === '1';
$phase2 = health_phase2_ready($connection);

$statement = $connection->prepare('SELECT h.*' . ($phase2 ? ', hc.name AS condition_name' : '') . ' FROM health_records h' . ($phase2 ? ' LEFT JOIN health_conditions hc ON hc.id = h.condition_id' : '') . ' WHERE h.resident_id = :resident' . ($show_archived ? '' : ' AND h.archived_at IS NULL') . ' ORDER BY h.service_date DESC, h.id DESC');
$statement->execute(['resident' => $resident['id']]);
$records = $statement->fetchAll();
$medicines = [];
if ($phase2 && $records !== []) {
    $ids = array_map(static fn (array $r): int => (int) $r['id'], $records);
    $lines = $connection->prepare('SELECT m.health_record_id, m.quantity, m.unit, i.name FROM health_record_medicines m INNER JOIN inventory_items i ON i.id = m.item_id WHERE m.health_record_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY i.name');
    $lines->execute($ids);
    foreach ($lines->fetchAll() as $line) $medicines[(int) $line['health_record_id']][] = $line['name'] . ' — ' . number_format((int) $line['quantity']) . ' ' . $line['unit'];
}
// Entity id 0 as for other Health entries that are not one record (list and report prints); the resident is in the details.
health_audit($connection, 0, 'health_history_viewed', ['resident_id' => (int) $resident['id'], 'records' => count($records)]);

// Program records of the resident (Immunization, Nutrition, Chronic Care).
$programs = health_programs_ready($connection);
$doses = $weighings = $chronic = [];
if ($programs) {
    $q = static function (string $sql) use ($connection, $resident): array { $s = $connection->prepare($sql); $s->execute(['r' => $resident['id']]); return $s->fetchAll(); };
    $doses = $q('SELECT i.date_given, i.given_by, v.name, v.dose_no FROM health_immunizations i INNER JOIN health_vaccines v ON v.id = i.vaccine_id WHERE i.resident_id = :r AND i.archived_at IS NULL ORDER BY i.date_given, v.name');
    $weighings = $q('SELECT * FROM health_nutrition WHERE resident_id = :r AND archived_at IS NULL ORDER BY weigh_date DESC, id DESC');
    $chronic = $q('SELECT * FROM health_chronic_cases WHERE resident_id = :r AND archived_at IS NULL ORDER BY status, condition_type');
}
$is_child = $resident['birth_date'] !== null && ($m = health_age_months($resident['birth_date'], date('Y-m-d'))) >= 0 && $m <= 59;

$active = array_values(array_filter($records, static fn (array $r): bool => $r['archived_at'] === null && $r['status'] !== 'cancelled' && $r['status'] !== 'scheduled'));
$last_visit = $active[0]['service_date'] ?? null;
$age = residents_age($resident['birth_date']);
$page_title = 'Health History'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<article class="dashboard-panel resident-detail">
    <div class="page-heading">
        <div>
            <a class="announcement-back" href="health.php"><span aria-hidden="true">&larr;</span> Back to Health</a>
            <span class="eyebrow">Health History</span>
            <h1><?= e(residents_full_name($resident)) ?></h1>
            <p class="resident-detail-meta"><?= e(implode(' · ', array_filter([$age === null ? 'Age unknown' : $age . ' yrs', residents_sex_labels()[$resident['sex']] ?? '', residents_purok_label((string) $resident['purok'])]))) ?></p>
        </div>
        <div class="resident-detail-actions">
            <a class="btn btn-primary announcements-new-btn" href="health_form.php?resident=<?= e((string) $resident['id']) ?>">New Record</a>
            <a class="btn btn-light resident-action-btn" href="health_history.php?resident=<?= e((string) $resident['id']) ?><?= $show_archived ? '' : '&amp;archived=1' ?>"><?= $show_archived ? 'Hide archived' : 'Include archived' ?></a>
        </div>
    </div>
    <p class="activity-history-meta"><?= e((string) count($active)) ?> visit<?= count($active) === 1 ? '' : 's' ?> recorded<?= $last_visit ? ' · last visit ' . e(health_format_date($last_visit)) : '' ?>.</p>

    <?php if ($records === []): ?>
        <div class="dashboard-empty-state">No health records for this resident yet.</div>
    <?php else: ?>
        <div class="resident-table-wrap">
            <table class="resident-table health-table">
                <thead><tr><th scope="col">Date</th><th scope="col">Record No.</th><th scope="col">Service</th><?php if ($phase2): ?><th scope="col">Complaint / Diagnosis</th><th scope="col">Vital Signs</th><th scope="col">Medicines</th><?php endif; ?><th scope="col">Status</th></tr></thead>
                <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td class="health-nowrap"><?= e(health_format_date($row['service_date'])) ?><span class="health-sub"><?= e($row['health_worker']) ?></span></td>
                        <td class="resident-name"><a class="activity-detail-link" href="health_view.php?id=<?= e((string) $row['id']) ?>"><?= e($row['record_no']) ?></a></td>
                        <td><?= e(health_service_label($row)) ?></td>
                        <?php if ($phase2): ?>
                        <td><?= $row['chief_complaint'] ? e($row['chief_complaint']) : '<span class="activity-detail-muted">—</span>' ?><?php if ($row['condition_name']): ?><span class="health-sub">Dx: <?= e($row['condition_name']) ?></span><?php endif; ?><?php if ((int) $row['referred_rhu'] === 1): ?><span class="health-sub">Referred to RHU</span><?php endif; ?></td>
                        <td><?= ($vitals = health_vitals_label($row)) !== '' ? e($vitals) : '<span class="activity-detail-muted">—</span>' ?></td>
                        <td><?= isset($medicines[(int) $row['id']]) ? implode('<br>', array_map('e', $medicines[(int) $row['id']])) : '<span class="activity-detail-muted">—</span>' ?></td>
                        <?php endif; ?>
                        <td><?= $row['archived_at'] !== null ? '<span class="resident-status resident-status-inactive">Archived</span>' : health_status_badge($row['status']) ?><?php if ($row['follow_up_date'] && $row['status'] !== 'cancelled'): ?><span class="health-sub">Follow-up <?= e(health_format_date($row['follow_up_date'])) ?></span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <ul class="resident-cards">
            <?php foreach ($records as $row): ?>
                <li class="resident-card">
                    <div class="resident-card-top"><strong><a class="activity-detail-link" href="health_view.php?id=<?= e((string) $row['id']) ?>"><?= e($row['record_no']) ?></a> · <?= e(health_format_date($row['service_date'])) ?></strong><?= $row['archived_at'] !== null ? '<span class="resident-status resident-status-inactive">Archived</span>' : health_status_badge($row['status']) ?></div>
                    <p><?= e(health_service_label($row)) ?><?= $phase2 && $row['condition_name'] ? ' · Dx: ' . e($row['condition_name']) : '' ?></p>
                    <?php if ($phase2 && ($vitals = health_vitals_label($row)) !== ''): ?><p><?= e($vitals) ?></p><?php endif; ?>
                    <?php if (isset($medicines[(int) $row['id']])): ?><p><?= e(implode('; ', $medicines[(int) $row['id']])) ?></p><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</article>
<?php if ($programs && ($doses !== [] || $weighings !== [] || $chronic !== [] || $is_child)): ?>
<div class="health-history-programs">
    <?php if ($chronic !== []): ?>
    <section class="dashboard-panel resident-detail-section">
        <h2>Chronic Care</h2>
        <ul class="resident-history"><?php foreach ($chronic as $c): ?><li><strong><?= e(health_chronic_conditions()[$c['condition_type']]) ?> · <?= e(health_chronic_statuses()[$c['status']]) ?></strong><span><?= $c['diagnosed_on'] ? 'Since ' . e(health_format_date($c['diagnosed_on'])) . ' · ' : '' ?><?= $c['maintenance_medicines'] ? e($c['maintenance_medicines']) : 'No maintenance medicines recorded' ?><?= (int) $c['is_serious'] === 1 ? ' · Serious' : '' ?></span></li><?php endforeach; ?></ul>
        <a class="btn btn-sm btn-light resident-action-btn" href="health_chronic.php?q=<?= e(rawurlencode((string) $resident['last_name'])) ?>">Open Chronic Care</a>
    </section>
    <?php endif; ?>
    <?php if ($doses !== [] || $is_child): ?>
    <section class="dashboard-panel resident-detail-section">
        <h2>Immunization</h2>
        <?php if ($doses === []): ?><p class="resident-static">No vaccines recorded yet.</p><?php else: ?>
        <ul class="resident-history"><?php foreach ($doses as $d): ?><li><strong><?= e(health_vaccine_label($d)) ?></strong><span><?= e(health_format_date($d['date_given'])) ?> · <?= e($d['given_by']) ?></span></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <a class="btn btn-sm btn-light resident-action-btn" href="health_immunization_form.php?resident=<?= e((string) $resident['id']) ?>">Record vaccine</a>
    </section>
    <?php endif; ?>
    <?php if ($weighings !== [] || $is_child): ?>
    <section class="dashboard-panel resident-detail-section">
        <h2>Nutrition (Operation Timbang)</h2>
        <?php if ($weighings === []): ?><p class="resident-static">No weighing recorded yet.</p><?php else: ?>
        <div class="resident-table-wrap health-keep-table"><table class="resident-table"><thead><tr><th scope="col">Date</th><th scope="col">Age</th><th scope="col">Weight / height</th><th scope="col">Weight-for-age</th><th scope="col">Height-for-age</th><th scope="col">Weight-for-height</th></tr></thead><tbody>
            <?php foreach ($weighings as $w): ?><tr><td class="health-nowrap"><?= e(health_format_date($w['weigh_date'])) ?></td><td><?= e((string) $w['age_months']) ?> mo</td><td class="health-nowrap"><?= e(rtrim(rtrim((string) $w['weight_kg'], '0'), '.') . ' kg · ' . rtrim(rtrim((string) $w['height_cm'], '0'), '.') . ' cm') ?></td><td><?= health_nutrition_badge($w['wfa_status'], health_wfa_statuses()) ?></td><td><?= health_nutrition_badge($w['hfa_status'], health_hfa_statuses()) ?></td><td><?= health_nutrition_badge($w['wfh_status'], health_wfh_statuses()) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
        <?php if ($is_child): ?><a class="btn btn-sm btn-light resident-action-btn" href="health_nutrition_form.php?resident=<?= e((string) $resident['id']) ?>">Record weighing</a><?php endif; ?>
    </section>
    <?php endif; ?>
</div>
<?php endif; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
