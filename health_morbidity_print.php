<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health.php';
require_once __DIR__ . '/config/site.php';
health_require_manage();
$connection = db();

// Printable Morbidity Report (A4): consultations per diagnosis for a day, week or month, by sex and age group. Health
// Workers only, within their assigned Puroks. Counts only; no names. The print is kept in the audit log.
if (!health_ready($connection) || !health_phase2_ready($connection)) { http_response_code(404); exit('The Morbidity Report is not available yet.'); }
$state = health_morbidity_state($connection, $_GET);
$report = health_morbidity($connection, $state);
$without = $report['without']['visits'] ?? 0;
$scope = residents_purok_scope($connection);
$coverage = $state['purok'] !== '' ? residents_purok_label($state['purok']) : ($scope !== null ? implode(', ', array_map('residents_purok_label', $scope)) : 'All Puroks');
health_audit($connection, 0, 'health_morbidity_printed', ['period' => $state['period'], 'from' => $state['from'], 'to' => $state['to']]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Morbidity Report | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/disaster_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/disaster_print.css')) ?>" rel="stylesheet">
</head>
<body class="drp-page">
<div class="drp-toolbar">
    <div class="drp-actions">
        <a href="health_morbidity.php?<?= e(health_morbidity_query($state)) ?>">&larr; Back to the report</a>
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
        <h1 class="drp-title">MORBIDITY REPORT</h1>
        <p class="drp-meta"><?= e($state['label']) ?> · <?= e($coverage) ?> · Printed <?= e(date('F j, Y g:i A')) ?></p>

        <table class="drp-table drp-details">
            <tr><th>Consultations with a diagnosis</th><td><?= e(number_format($report['totals']['visits'])) ?></td><th>Residents seen</th><td><?= e(number_format($report['totals']['residents'])) ?></td></tr>
            <tr><th>Different diagnoses</th><td><?= e(number_format(count($report['rows']))) ?></td><th>Visits without a diagnosis</th><td><?= e(number_format($without)) ?></td></tr>
        </table>

        <?php if ($report['rows'] === []): ?>
            <p class="drp-empty">No consultations with a diagnosis in this period.</p>
        <?php else: ?>
            <?= health_morbidity_table($report, 'drp-table') ?>
        <?php endif; ?>
        <p class="drp-footnote">Counted: Completed and Follow-up visits, not archived. Age is the age on the visit date (years). "Residents" in the total counts each resident once.</p>

        <div class="drp-signatures">
            <div class="drp-signature">
                <p class="drp-signature-label">Prepared by:</p>
                <p class="drp-signature-line"></p>
                <p class="drp-signature-name"><?= e((string) (current_user()['name'] ?? '')) ?></p>
                <p class="drp-signature-position">Barangay Health Worker</p>
            </div>
        </div>
        <p class="drp-footnote">Summary counts only. Health information is protected under the Data Privacy Act of 2012.</p>
    </article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
</script>
</body>
</html>
