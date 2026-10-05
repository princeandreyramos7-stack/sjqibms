<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health_programs.php';
require_once __DIR__ . '/config/site.php';
$connection = db();
health_programs_require($connection);

// Printable Monthly Health Report (A4); ?print=1 opens the browser's print dialog (Save as PDF). Counts only.
$state = health_monthly_state($connection, $_GET);
$sections = health_monthly_data($connection, $state);
$scope = residents_purok_scope($connection);
$coverage = $state['purok'] !== '' ? residents_purok_label($state['purok']) : ($scope !== null ? implode(', ', array_map('residents_purok_label', $scope)) : 'All Puroks');
health_audit($connection, 0, 'health_monthly_printed', ['month' => $state['month'], 'purok' => $state['purok']]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Monthly Health Report | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/disaster_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/disaster_print.css')) ?>" rel="stylesheet">
</head>
<body class="drp-page">
<div class="drp-toolbar"><div class="drp-actions"><a href="health_monthly.php?<?= e(health_monthly_query($state)) ?>">&larr; Back to the report</a><button class="is-primary" type="button" data-print>Print / Save as PDF</button></div></div>
<main class="drp-stage">
    <article class="drp-sheet">
        <header class="drp-header">
            <img class="drp-logo" src="assets/img/barangay-san-jose-logo.jpg" alt="Logo">
            <div class="drp-header-text"><p>Republic of the Philippines</p><p><?= e(BARANGAY_LOCATION) ?></p><p class="drp-barangay"><?= e(mb_strtoupper(BARANGAY_NAME)) ?></p><p class="drp-office">Barangay Health Station</p></div>
            <span class="drp-logo" aria-hidden="true"></span>
        </header>
        <h1 class="drp-title">MONTHLY HEALTH REPORT</h1>
        <p class="drp-meta"><?= e($state['label']) ?> · <?= e($coverage) ?> · Printed <?= e(date('F j, Y g:i A')) ?></p>
        <?php foreach ($sections as [$title, $rows, $note]): ?>
            <h2 class="drp-heading"><?= e($title) ?></h2>
            <table class="drp-table"><tbody>
                <?php foreach ($rows as $i => [$label, $value]): ?><tr><td<?= $i === 0 ? ' style="font-weight:700"' : '' ?>><?= e($label) ?></td><td class="num"<?= $i === 0 ? ' style="font-weight:700"' : '' ?>><?= e(number_format((int) $value)) ?></td></tr><?php endforeach; ?>
            </tbody></table>
            <p class="drp-footnote"><?= e($note) ?></p>
        <?php endforeach; ?>
        <div class="drp-signatures">
            <div class="drp-signature"><p class="drp-signature-label">Prepared by:</p><p class="drp-signature-line"></p><p class="drp-signature-name"><?= e((string) (current_user()['name'] ?? '')) ?></p><p class="drp-signature-position">Barangay Health Worker</p></div>
        </div>
        <p class="drp-footnote">Summary counts only. Health information is protected under the Data Privacy Act of 2012.</p>
    </article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
<?php if (($_GET['print'] ?? '') === '1'): ?>window.addEventListener('load', () => window.print());<?php endif; ?>
</script>
</body>
</html>
