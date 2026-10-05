<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health.php';
require_once __DIR__ . '/config/site.php';
health_require_manage();
$connection = db();

// Printable list of health services (A4) with the same filters as the Health list (My records / All records in my
// Puroks, Due today & overdue, search, status, service, health worker, dates). Health Workers only, within their
// assigned Puroks. Up to 500 rows. The print is kept in the audit log (count and filters only).
if (!health_ready($connection)) { http_response_code(404); exit('Health records are not available yet.'); }
$state = health_list_state($_GET);
[$where, $params] = health_list_where($state);
$order = health_list_order($state['due'], false);
$phase2 = health_phase2_ready($connection);
$statement = $connection->prepare('SELECT h.*, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.sex, r.purok' . ($phase2 ? ', hc.name AS condition_name' : '') . ' FROM health_records h INNER JOIN residents r ON r.id = h.resident_id' . ($phase2 ? ' LEFT JOIN health_conditions hc ON hc.id = h.condition_id' : '') . ' WHERE ' . implode(' AND ', $where) . " ORDER BY $order LIMIT 500");
$statement->execute($params);
$rows = $statement->fetchAll();
$summary = implode(' · ', array_filter([
    $state['show'] === 'all' ? 'All records' . (residents_purok_scope($connection) !== null ? ' in my Puroks' : '') : 'My records',
    $state['due'] ? 'Due today & overdue' : '',
    $state['from'] !== '' || $state['to'] !== '' ? 'Date: ' . ($state['from'] !== '' ? health_format_date($state['from']) : 'start') . ' to ' . ($state['to'] !== '' ? health_format_date($state['to']) : 'today') : '',
    $state['purok'] !== '' ? residents_purok_label($state['purok']) : '',
    $state['condition'] > 0 && $phase2 ? 'Diagnosis: ' . (string) (array_column(health_conditions($connection, false), 'name', 'id')[$state['condition']] ?? '') : '',
    $state['service'] !== '' ? health_services()[$state['service']] : '',
    $state['status'] !== '' ? ($state['status'] === 'archived' ? 'Archived' : (health_statuses()[$state['status']] ?? '')) : '',
    $state['worker'] !== '' ? 'Health worker: ' . $state['worker'] : '',
    $state['q'] !== '' ? 'Search: ' . $state['q'] : '',
]));
health_audit($connection, 0, 'health_list_printed', ['rows' => count($rows)]);
$back = 'health.php' . (($query = health_query_string(array_merge($state, ['page' => 1]))) !== '' ? '?' . $query : '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Health Services List | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/disaster_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/disaster_print.css')) ?>" rel="stylesheet">
</head>
<body class="drp-page">
<div class="drp-toolbar">
    <div class="drp-actions">
        <a href="<?= e($back) ?>">&larr; Back to Health</a>
        <button class="is-primary" type="button" data-print>Print / Save as PDF</button>
    </div>
</div>
<main class="drp-stage">
    <article class="drp-sheet">
        <header class="drp-header">
            <img class="drp-logo" src="assets/img/barangay-san-jose-logo.jpg" alt="Logo">
            <div class="drp-header-text">
                <p>Republic of the Philippines</p>
                <p><?= e(BARANGAY_LOCATION) ?></p>
                <p class="drp-barangay"><?= e(mb_strtoupper(BARANGAY_NAME)) ?></p>
                <p class="drp-office">Barangay Health Station</p>
            </div>
            <span class="drp-logo" aria-hidden="true"></span>
        </header>
        <h1 class="drp-title">HEALTH SERVICES</h1>
        <p class="drp-meta">Printed <?= e(date('F j, Y g:i A')) ?> · <?= e($summary) ?> · <?= e((string) count($rows)) ?> record<?= count($rows) === 1 ? '' : 's' ?><?= count($rows) === 500 ? ' (first 500)' : '' ?></p>
        <?php if ($rows === []): ?>
            <p class="drp-empty">No health records match the selected filters.</p>
        <?php else: ?>
            <table class="drp-table">
                <thead><tr><th>#</th><th>Date</th><th>Record No.</th><th>Resident</th><th class="num">Age</th><th>Sex</th><th>Purok</th><th>Diagnosis</th><th>Status</th><th>Health Worker</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $index => $row): $age = residents_age($row['birth_date']); ?>
                    <tr>
                        <td class="nowrap"><?= e((string) ($index + 1)) ?></td>
                        <td class="nowrap"><?= e(health_format_date($row['service_date'])) ?></td>
                        <td class="nowrap"><?= e($row['record_no']) ?></td>
                        <td><?= e(residents_full_name($row)) ?></td>
                        <td class="num"><?= $age === null ? '—' : e((string) $age) ?></td>
                        <td><?= e(residents_sex_labels()[$row['sex']] ?? '—') ?></td>
                        <td class="nowrap"><?= e(residents_purok_label((string) $row['purok'])) ?></td>
                        <td><?= !empty($row['condition_name']) ? e($row['condition_name']) : '—' ?></td>
                        <td><?= $row['archived_at'] !== null ? 'Archived' : e(health_statuses()[$row['status']] ?? $row['status']) ?><?= $row['status'] !== 'cancelled' && $row['follow_up_date'] ? '<span class="drp-sub">Follow-up ' . e(health_format_date($row['follow_up_date'])) . '</span>' : '' ?></td>
                        <td><?= e($row['health_worker']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <div class="drp-signatures">
            <div class="drp-signature">
                <p class="drp-signature-label">Prepared by:</p>
                <p class="drp-signature-line"></p>
                <p class="drp-signature-name"><?= e((string) (current_user()['name'] ?? '')) ?></p>
                <p class="drp-signature-position">Barangay Health Worker</p>
            </div>
        </div>
        <p class="drp-footnote">Confidential health information under the Data Privacy Act of 2012. For the Barangay Health Station only.</p>
    </article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
</script>
</body>
</html>
