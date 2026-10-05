<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health_programs.php';
$connection = db();
health_programs_require($connection);

// Chronic Care: residents with hypertension, diabetes or TB, their maintenance medicines and the latest reading.
// Readings are the BP / Blood Sugar / TB-DOTS monitoring consultations, so they stay in one history.
$condition = array_key_exists((string) ($_GET['condition'] ?? ''), health_chronic_conditions()) ? (string) $_GET['condition'] : '';
$status = (string) ($_GET['status'] ?? 'active');
if (!array_key_exists($status, health_chronic_statuses()) && !in_array($status, ['all', 'archived'], true)) $status = 'active';
$purok = (string) ($_GET['purok'] ?? '');
if ($purok !== '' && !in_array($purok, health_resident_puroks($connection), true)) $purok = '';
$q = mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100);
[$scope_sql, $params] = health_scope();
$where = [$scope_sql, $status === 'archived' ? 'c.archived_at IS NOT NULL' : 'c.archived_at IS NULL'];
if (!in_array($status, ['all', 'archived'], true)) { $where[] = 'c.status = :status'; $params['status'] = $status; }
if ($condition !== '') { $where[] = 'c.condition_type = :condition'; $params['condition'] = $condition; }
if ($purok !== '') { $where[] = 'r.purok = :purok'; $params['purok'] = $purok; }
if ($q !== '') { $where[] = "CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name, r.suffix) LIKE :q"; $params['q'] = '%' . addcslashes($q, '%_\\') . '%'; }
$statement = $connection->prepare('SELECT c.*, ' . health_chronic_reading_sql() . ', r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.sex, r.purok FROM health_chronic_cases c INNER JOIN residents r ON r.id = c.resident_id WHERE ' . implode(' AND ', $where) . ' ORDER BY c.condition_type, r.last_name, r.first_name LIMIT 300');
$statement->execute($params);
$rows = $statement->fetchAll();
$page_title = 'Health · Chronic Care'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <?= health_page_heading('Patients with hypertension, diabetes or TB.', 'health_chronic_form.php', 'Add Patient') ?>
    <?php if ($success = flash('health_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('health_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?= health_module_top($connection, 'chronic') ?>
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters" method="get" action="health_chronic.php">
            <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="c-q">Search</label><input class="activity-filter-input" type="search" id="c-q" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Resident name"></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="c-condition">Condition</label><select class="activity-filter-select" id="c-condition" name="condition"><option value="">All</option><?php foreach (health_chronic_conditions() as $k => $l): ?><option value="<?= e($k) ?>" <?= $condition === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="c-status">Status</label><select class="activity-filter-select" id="c-status" name="status"><?php foreach (health_chronic_statuses() + ['all' => 'All', 'archived' => 'Archived'] as $k => $l): ?><option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="c-purok">Purok</label><select class="activity-filter-select" id="c-purok" name="purok"><option value="">All</option><?php foreach (health_resident_puroks($connection) as $p): ?><option value="<?= e($p) ?>" <?= $purok === $p ? 'selected' : '' ?>><?= e(residents_purok_label($p)) ?></option><?php endforeach; ?></select></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit">Apply</button><a class="btn btn-outline-secondary btn-sm" href="health_chronic.php">Reset</a></div>
        </form>
        <?php if ($rows === []): ?>
            <div class="dashboard-empty-state">No chronic care records match.</div>
        <?php else: ?>
            <p class="activity-history-meta"><?= e((string) count($rows)) ?> record<?= count($rows) === 1 ? '' : 's' ?></p>
            <div class="resident-table-wrap">
                <table class="resident-table">
                    <thead><tr><th scope="col">Resident</th><th scope="col" class="num">Age</th><th scope="col">Purok</th><th scope="col">Condition</th><th scope="col">Latest reading</th><th scope="col">Maintenance medicines</th><th scope="col">Status</th><th scope="col" class="health-actions-head">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="resident-name"><a class="activity-detail-link" href="health_history.php?resident=<?= e((string) $row['resident_id']) ?>"><?= e(residents_full_name($row)) ?></a><?php if ((int) $row['is_serious'] === 1): ?><span class="health-sub is-due">Serious</span><?php endif; ?></td>
                            <td class="num"><?= ($age = residents_age($row['birth_date'])) === null ? '—' : e((string) $age) ?></td>
                            <td class="health-nowrap"><?= e(residents_purok_label((string) $row['purok'])) ?></td>
                            <td><?= e(health_chronic_conditions()[$row['condition_type']]) ?><?php if ($row['diagnosed_on']): ?><span class="health-sub">Since <?= e(health_format_date($row['diagnosed_on'])) ?></span><?php endif; ?></td>
                            <td><?= e(health_chronic_reading_label($row)) ?></td>
                            <td class="resident-wrap"><?= $row['maintenance_medicines'] ? e($row['maintenance_medicines']) : '<span class="activity-detail-muted">—</span>' ?></td>
                            <td><?= $row['archived_at'] !== null ? '<span class="resident-status resident-status-inactive">Archived</span>' : '<span class="resident-status resident-status-' . ($row['status'] === 'active' ? 'pending' : 'active') . '">' . e(health_chronic_statuses()[$row['status']]) . '</span>' ?></td>
                            <td><div class="management-actions health-actions"><?php if ($row['archived_at'] === null): ?><a class="btn btn-sm btn-outline-primary" href="health_form.php?resident=<?= e((string) $row['resident_id']) ?>&amp;service=<?= e(health_chronic_service($row['condition_type'])) ?>">Record Reading</a><a class="btn btn-sm btn-outline-primary" href="health_chronic_form.php?id=<?= e((string) $row['id']) ?>">Edit</a><?php endif; ?><?= health_program_archive_button('chronic', $row) ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="resident-cards">
                <?php foreach ($rows as $row): ?>
                    <li class="resident-card"><div class="resident-card-top"><strong><a class="activity-detail-link" href="health_history.php?resident=<?= e((string) $row['resident_id']) ?>"><?= e(residents_full_name($row)) ?></a></strong><span class="resident-status resident-status-pending"><?= e(health_chronic_conditions()[$row['condition_type']]) ?></span></div><p><?= e(health_chronic_reading_label($row)) ?></p><?php if ($row['maintenance_medicines']): ?><p><?= e($row['maintenance_medicines']) ?></p><?php endif; ?><div class="management-actions health-actions"><a class="btn btn-sm btn-outline-primary" href="health_form.php?resident=<?= e((string) $row['resident_id']) ?>&amp;service=<?= e(health_chronic_service($row['condition_type'])) ?>">Record Reading</a><a class="btn btn-sm btn-outline-primary" href="health_chronic_form.php?id=<?= e((string) $row['id']) ?>">Edit</a></div></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="activity-history-meta mt-2">"Record Reading" opens a monitoring consultation (BP, Blood Sugar or TB-DOTS). All readings appear in the resident's Health History.</p>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
