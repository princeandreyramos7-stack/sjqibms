<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health_programs.php';
$connection = db();
health_programs_require($connection);

// Referrals: consultations where the resident was referred to the RHU (reason, date) with their status — Pending until
// the Health Worker marks it Completed (date and outcome). Records referred before this tab existed count as Pending.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = (string) ($_POST['action'] ?? '');
    $back = health_program_back((string) ($_POST['back'] ?? ''), 'health_referrals.php');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('health_error', 'Your session expired. Please try again.'); redirect($back); }
    $record = $id ? health_find($connection, $id) : null;
    if ($record === null || (int) $record['referred_rhu'] !== 1 || $record['archived_at'] !== null) { http_response_code(404); exit('Referral not found.'); }
    if ($action === 'complete') {
        $date = health_valid_date(trim((string) ($_POST['completed_on'] ?? '')));
        $outcome = residents_collapse($_POST['outcome'] ?? '');
        if ($date === null || $date > new DateTimeImmutable('today') || $date->format('Y-m-d') < $record['service_date']) { flash('health_error', 'Enter the date the referral was completed (not before the referral, not in the future).'); redirect($back); }
        if (mb_strlen($outcome) > 255) { flash('health_error', 'Keep the outcome to 255 characters.'); redirect($back); }
        $connection->prepare("UPDATE health_records SET referral_status = 'completed', referral_completed_on = :d, referral_outcome = :o, updated_by = :u WHERE id = :id")->execute(['d' => $date->format('Y-m-d'), 'o' => $outcome === '' ? null : $outcome, 'u' => current_user()['id'], 'id' => $id]);
        health_audit($connection, (int) $id, 'health_referral_completed', ['record_no' => $record['record_no']]);
        flash('health_success', 'The referral of ' . $record['record_no'] . ' was marked Completed.');
    } elseif ($action === 'reopen') {
        $connection->prepare("UPDATE health_records SET referral_status = 'pending', referral_completed_on = NULL, referral_outcome = NULL, updated_by = :u WHERE id = :id")->execute(['u' => current_user()['id'], 'id' => $id]);
        health_audit($connection, (int) $id, 'health_referral_reopened', ['record_no' => $record['record_no']]);
        flash('health_success', 'The referral of ' . $record['record_no'] . ' is Pending again.');
    }
    redirect($back);
}

$status = in_array($_GET['status'] ?? '', ['pending', 'completed'], true) ? (string) $_GET['status'] : '';
$purok = (string) ($_GET['purok'] ?? '');
if ($purok !== '' && !in_array($purok, health_resident_puroks($connection), true)) $purok = '';
$from = health_valid_date((string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : '';
$to = health_valid_date((string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : '';
[$scope_sql, $params] = health_scope();
$where = [$scope_sql, 'h.archived_at IS NULL', 'h.referred_rhu = 1'];
if ($status !== '') { $where[] = "COALESCE(h.referral_status, 'pending') = :status"; $params['status'] = $status; }
if ($purok !== '') { $where[] = 'r.purok = :purok'; $params['purok'] = $purok; }
if ($from !== '') { $where[] = 'h.service_date >= :from'; $params['from'] = $from; }
if ($to !== '') { $where[] = 'h.service_date <= :to'; $params['to'] = $to; }
$statement = $connection->prepare('SELECT h.id, h.record_no, h.resident_id, h.service, h.service_details, h.service_date, h.health_worker, h.referral_reason, h.referral_status, h.referral_completed_on, h.referral_outcome, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.purok FROM health_records h INNER JOIN residents r ON r.id = h.resident_id WHERE ' . implode(' AND ', $where) . " ORDER BY (COALESCE(h.referral_status, 'pending') = 'pending') DESC, h.service_date DESC LIMIT 300");
$statement->execute($params);
$rows = $statement->fetchAll();
$back = 'health_referrals.php' . (($query = http_build_query(array_filter(['status' => $status, 'purok' => $purok, 'from' => $from, 'to' => $to]))) !== '' ? '?' . $query : '');
$page_title = 'Health · Referrals'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <?= health_page_heading('Residents referred to the RHU (set "Referred to the RHU? Yes" on a consultation).') ?>
    <?php if ($success = flash('health_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('health_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?= health_module_top($connection, 'referrals') ?>
    <section class="dashboard-panel resident-list-panel">
        <form class="resident-filters" method="get" action="health_referrals.php">
            <div class="resident-filter-group"><label class="activity-filter-label" for="r-status">Status</label><select class="activity-filter-select" id="r-status" name="status"><option value="">All</option><option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option><option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="r-purok">Purok</label><select class="activity-filter-select" id="r-purok" name="purok"><option value="">All</option><?php foreach (health_resident_puroks($connection) as $p): ?><option value="<?= e($p) ?>" <?= $purok === $p ? 'selected' : '' ?>><?= e(residents_purok_label($p)) ?></option><?php endforeach; ?></select></div>
            <div class="resident-filter-group"><label class="activity-filter-label" for="r-from">Referred between</label><div class="health-filter-dates"><input class="activity-filter-input" type="date" id="r-from" name="from" value="<?= e($from) ?>" aria-label="From"><span>to</span><input class="activity-filter-input" type="date" name="to" value="<?= e($to) ?>" aria-label="To"></div></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit">Apply</button><a class="btn btn-outline-secondary btn-sm" href="health_referrals.php">Reset</a></div>
        </form>
        <?php if ($rows === []): ?>
            <div class="dashboard-empty-state">No referrals match.</div>
        <?php else: ?>
            <p class="activity-history-meta"><?= e((string) count($rows)) ?> referral<?= count($rows) === 1 ? '' : 's' ?></p>
            <ul class="health-referral-list">
                <?php foreach ($rows as $row): $st = health_referral_status($row); ?>
                    <li class="dashboard-panel health-referral<?= $st === 'pending' ? ' is-pending' : '' ?>">
                        <div class="health-referral-top">
                            <div><strong><a class="activity-detail-link" href="health_history.php?resident=<?= e((string) $row['resident_id']) ?>"><?= e(residents_full_name($row)) ?></a></strong><span class="health-sub"><?= e(residents_purok_label((string) $row['purok'])) ?> · <a class="activity-detail-link" href="health_view.php?id=<?= e((string) $row['id']) ?>"><?= e($row['record_no']) ?></a> · referred <?= e(health_format_date($row['service_date'])) ?> by <?= e($row['health_worker']) ?></span></div>
                            <?= health_referral_badge($st) ?>
                        </div>
                        <p class="resident-wrap mb-2"><strong>Reason:</strong> <?= $row['referral_reason'] ? e($row['referral_reason']) : '<span class="activity-detail-muted">Not recorded</span>' ?></p>
                        <?php if ($st === 'completed'): ?>
                            <p class="mb-2">Completed <?= e(health_format_date($row['referral_completed_on'])) ?><?= $row['referral_outcome'] ? ' · ' . e($row['referral_outcome']) : '' ?></p>
                            <form method="post" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $row['id']) ?>"><input type="hidden" name="action" value="reopen"><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn btn-sm btn-outline-secondary" type="submit" data-form-confirm="custom" data-dialog-heading="Set back to Pending?" data-dialog-message="The completion date and outcome will be cleared." data-dialog-confirm="Set Pending" data-dialog-dismiss="Cancel">Set back to Pending</button></form>
                        <?php else: ?>
                            <form method="post" class="health-referral-complete"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $row['id']) ?>"><input type="hidden" name="action" value="complete"><input type="hidden" name="back" value="<?= e($back) ?>">
                                <input class="form-control form-control-sm" type="date" name="completed_on" value="<?= e(date('Y-m-d')) ?>" min="<?= e($row['service_date']) ?>" max="<?= e(date('Y-m-d')) ?>" aria-label="Date completed" required>
                                <input class="form-control form-control-sm" name="outcome" maxlength="255" placeholder="Outcome (e.g. seen by the RHU physician)" aria-label="Outcome">
                                <button class="btn btn-sm btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Mark this referral Completed?" data-dialog-message="Recorded in the audit log." data-dialog-confirm="Mark Completed" data-dialog-dismiss="Cancel">Mark Completed</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
