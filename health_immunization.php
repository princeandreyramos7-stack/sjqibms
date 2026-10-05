<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health_programs.php';
$connection = db();
health_programs_require($connection);

// Immunization: the vaccines given, as recorded by the Health Workers (no schedule). Search, vaccine, Purok and dates.
$q = mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100);
$vaccine = mb_substr(residents_collapse((string) ($_GET['vaccine'] ?? '')), 0, 120);
$purok = (string) ($_GET['purok'] ?? '');
if ($purok !== '' && !in_array($purok, health_resident_puroks($connection), true)) $purok = '';
$from = health_valid_date((string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : '';
$to = health_valid_date((string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : '';
$archived = (string) ($_GET['archived'] ?? '') === '1';
[$scope_sql, $params] = health_scope();
$where = [$scope_sql, $archived ? 'i.archived_at IS NOT NULL' : 'i.archived_at IS NULL'];
if ($q !== '') { $where[] = "CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name, r.suffix) LIKE :q"; $params['q'] = '%' . addcslashes($q, '%_\\') . '%'; }
if ($vaccine !== '') { $where[] = 'v.name = :vaccine'; $params['vaccine'] = $vaccine; }
if ($purok !== '') { $where[] = 'r.purok = :purok'; $params['purok'] = $purok; }
if ($from !== '') { $where[] = 'i.date_given >= :from'; $params['from'] = $from; }
if ($to !== '') { $where[] = 'i.date_given <= :to'; $params['to'] = $to; }
$statement = $connection->prepare('SELECT i.*, v.name, v.dose_no, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.sex, r.purok FROM health_immunizations i INNER JOIN health_vaccines v ON v.id = i.vaccine_id INNER JOIN residents r ON r.id = i.resident_id WHERE ' . implode(' AND ', $where) . ' ORDER BY i.date_given DESC, i.id DESC LIMIT 300');
$statement->execute($params);
$rows = $statement->fetchAll();
$names = health_vaccine_names($connection);
$page_title = 'Health · Immunization'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <?= health_page_heading('Vaccines given at the health station.', 'health_immunization_form.php', 'Record Vaccine') ?>
    <?php if ($success = flash('health_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('health_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?= health_module_top($connection, 'immunization') ?>
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters" method="get" action="health_immunization.php">
            <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="i-q">Search</label><input class="activity-filter-input" type="search" id="i-q" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Resident name"></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="i-vaccine">Vaccine</label><select class="activity-filter-select" id="i-vaccine" name="vaccine"><option value="">All</option><?php foreach ($names as $name): ?><option value="<?= e($name) ?>" <?= $vaccine === $name ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="i-purok">Purok</label><select class="activity-filter-select" id="i-purok" name="purok"><option value="">All</option><?php foreach (health_resident_puroks($connection) as $p): ?><option value="<?= e($p) ?>" <?= $purok === $p ? 'selected' : '' ?>><?= e(residents_purok_label($p)) ?></option><?php endforeach; ?></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="i-from">Date given</label><div class="health-filter-dates"><input class="activity-filter-input" type="date" id="i-from" name="from" value="<?= e($from) ?>" aria-label="From"><span>to</span><input class="activity-filter-input" type="date" name="to" value="<?= e($to) ?>" aria-label="To"></div></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit">Apply</button><a class="btn btn-outline-secondary btn-sm" href="health_immunization.php">Reset</a></div>
        </form>
        <?php if ($rows === []): ?>
            <div class="dashboard-empty-state"><?= $archived ? 'No archived vaccine records.' : 'No vaccines recorded yet. Select Record Vaccine to add one.' ?></div>
        <?php else: ?>
            <p class="activity-history-meta"><?= e((string) count($rows)) ?> record<?= count($rows) === 1 ? '' : 's' ?><?= count($rows) === 300 ? ' (latest 300)' : '' ?></p>
            <div class="resident-table-wrap">
                <table class="resident-table">
                    <thead><tr><th scope="col">Date given</th><th scope="col">Resident</th><th scope="col">Age</th><th scope="col">Purok</th><th scope="col">Vaccine</th><th scope="col">Given by</th><th scope="col" class="health-actions-head"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="health-nowrap"><?= e(health_format_date($row['date_given'])) ?></td>
                            <td class="resident-name"><a class="activity-detail-link" href="health_history.php?resident=<?= e((string) $row['resident_id']) ?>"><?= e(residents_full_name($row)) ?></a></td>
                            <td class="health-nowrap"><?= e(health_age_label($row['birth_date'], $row['date_given'])) ?></td>
                            <td class="health-nowrap"><?= e(residents_purok_label((string) $row['purok'])) ?></td>
                            <td><?= e(health_vaccine_label($row)) ?><?php if ($row['lot_no']): ?><span class="health-sub">Lot <?= e($row['lot_no']) ?></span><?php endif; ?></td>
                            <td><?= e($row['given_by']) ?></td>
                            <td><div class="management-actions health-actions"><?= health_program_archive_button('immunization', $row) ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="resident-cards">
                <?php foreach ($rows as $row): ?>
                    <li class="resident-card"><div class="resident-card-top"><strong><a class="activity-detail-link" href="health_history.php?resident=<?= e((string) $row['resident_id']) ?>"><?= e(residents_full_name($row)) ?></a></strong><span class="resident-status resident-status-active"><?= e(health_vaccine_label($row)) ?></span></div><p><?= e(health_format_date($row['date_given'])) ?> · <?= e($row['given_by']) ?> · <?= e(residents_purok_label((string) $row['purok'])) ?></p></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="activity-history-meta mt-2"><a class="activity-detail-link" href="health_immunization.php?archived=<?= $archived ? '0' : '1' ?>"><?= $archived ? 'Show current records' : 'Show archived records' ?></a></p>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
