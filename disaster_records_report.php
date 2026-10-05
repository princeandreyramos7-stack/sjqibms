<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_prep.php';
disaster_require_view();
$connection = db();
if (!disaster_ready($connection)) { flash('disaster_error', 'Disaster Management needs its database tables first.'); redirect('disaster.php'); }

// Printable DRR records list (A4), with the filters and sort chosen on the list. "Print / Save as PDF" uses the
// browser's print dialog; ?print=1 opens the dialog automatically (the list's Export PDF button).
$state = disaster_list_state($_GET);
$rows = disaster_records_rows($connection, $state);
$settings = disaster_report_settings();
$summary = disaster_filter_summary($connection, $state);
disaster_audit($connection, 0, 'disaster_records_printed', ['rows' => count($rows), 'filters' => $summary]);
$auto_print = ($_GET['print'] ?? '') === '1';
$back = disaster_query_string(array_merge($state, ['page' => 1]));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>DRR Records | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/disaster_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/disaster_print.css')) ?>" rel="stylesheet">
</head>
<body class="drp-page">
<div class="drp-toolbar">
    <div class="drp-actions"><span><?= e($summary) ?> · <?= e((string) count($rows)) ?> record<?= count($rows) === 1 ? '' : 's' ?></span></div>
    <div class="drp-actions">
        <a href="disaster.php<?= $back !== '' ? '?' . e($back) : '' ?>">&larr; Records</a>
        <button class="is-primary" type="button" data-print>Print / Save as PDF</button>
    </div>
</div>
<main class="drp-stage">
    <article class="drp-sheet">
        <?php require __DIR__ . '/layout/disaster_print_header.php'; ?>
        <h1 class="drp-title"><?= e($settings['records_title']) ?></h1>
        <p class="drp-meta">As of <?= e(date('F j, Y g:i A')) ?> · <?= e($summary) ?> · <?= e((string) count($rows)) ?> record<?= count($rows) === 1 ? '' : 's' ?></p>
        <?php if ($rows === []): ?>
            <p class="drp-empty">No records match the selected filters.</p>
        <?php else: ?>
            <table class="drp-table">
                <thead><tr><th>Reference</th><th>Activity / Incident</th><th>Type</th><th>Area</th><th>Date</th><th>Status</th><th class="num">Affected (fam. / pers.)</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="nowrap"><?= e($row['reference_no']) ?></td>
                        <td><?= e($row['title']) ?><?= $row['alert_level'] ? '<span class="drp-sub"> · ' . e(disaster_alert_levels()[$row['alert_level']] ?? '') . '</span>' : '' ?></td>
                        <td><?= e(disaster_types()[$row['record_type']] ?? '') ?></td>
                        <td><?= e($row['area_name']) ?></td>
                        <td class="nowrap"><?= e(disaster_format_date($row['record_date'])) ?></td>
                        <td><?= e($row['archived_at'] !== null ? 'Archived' : (disaster_statuses()[$row['status']] ?? '')) ?></td>
                        <td class="num"><?= $row['record_type'] === 'incident' ? e(($row['affected_families'] ?? '—') . ' / ' . ($row['affected_persons'] ?? '—')) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
<?php if ($auto_print): ?>window.addEventListener('load', () => window.print());<?php endif; ?>
</script>
</body>
</html>
