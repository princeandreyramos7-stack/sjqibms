<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_prep.php';
disaster_require_view();
$connection = db();

// Printable emergency hotlines poster (A4) for the barangay hall and the evacuation centers: the current hotlines and
// the open evacuation centers. Only office and hotline numbers are printed, not the personal numbers of BDRRMC members.
$ready = disaster_prep_ready($connection);
$hotlines = $ready ? disaster_current_hotlines($connection) : [];
$centers = $ready ? array_values(array_filter(disaster_current_centers($connection), static fn (array $c): bool => $c['status'] !== 'closed')) : [];
$settings = disaster_report_settings();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Emergency Hotlines | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/disaster_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/disaster_print.css')) ?>" rel="stylesheet">
    <style>
        .drp-poster-title { margin: 8px 0 18px; font-size: 30px; letter-spacing: 1px; text-align: center; }
        .drp-hotline { display: flex; justify-content: space-between; gap: 16px; padding: 12px 4px; border-bottom: 1px solid #bbb; font-size: 18px; }
        .drp-hotline strong { font-size: 22px; white-space: nowrap; }
        .drp-hotline small { display: block; color: #555; font-size: 13px; }
        .drp-poster h2 { margin: 26px 0 6px; font-size: 17px; }
    </style>
</head>
<body class="drp-page">
<div class="drp-toolbar">
    <div class="drp-actions">
        <a href="disaster_contacts.php">&larr; BDRRMC &amp; Hotlines</a>
        <button class="is-primary" type="button" data-print>Print / Save as PDF</button>
    </div>
</div>
<main class="drp-stage">
    <article class="drp-sheet drp-poster">
        <header class="drp-header">
            <?php if ($settings['logo'] !== ''): ?><img class="drp-logo" src="<?= e($settings['logo']) ?>" alt="Logo"><?php else: ?><span class="drp-logo is-placeholder" aria-hidden="true">LOGO</span><?php endif; ?>
            <div class="drp-header-text">
                <?php foreach ($settings['header_lines'] as $line): ?><p><?= e($line) ?></p><?php endforeach; ?>
                <p class="drp-barangay"><?= e($settings['barangay_name']) ?></p>
            </div>
            <span class="drp-logo" aria-hidden="true"></span>
        </header>
        <h1 class="drp-poster-title">EMERGENCY HOTLINES</h1>
        <?php if ($hotlines === []): ?>
            <p class="drp-empty">No hotlines are recorded yet. Add them in Disaster Management → BDRRMC &amp; Hotlines.</p>
        <?php else: foreach ($hotlines as $hotline): ?>
            <div class="drp-hotline">
                <span><?= e($hotline['name']) ?><small><?= e(disaster_hotline_categories()[$hotline['hotline_category']] ?? '') ?><?= $hotline['notes'] ? ' · ' . e($hotline['notes']) : '' ?></small></span>
                <strong><?= e($hotline['contact_number']) ?><?= $hotline['alternate_number'] ? ' / ' . e($hotline['alternate_number']) : '' ?></strong>
            </div>
        <?php endforeach; endif; ?>
        <?php if ($centers !== []): ?>
            <h2>Evacuation centers</h2>
            <?php foreach ($centers as $center): ?>
                <div class="drp-hotline"><span><?= e($center['name']) ?><small><?= e((string) $center['address']) ?></small></span><strong><?= e(disaster_center_statuses()[$center['status']] ?? '') ?></strong></div>
            <?php endforeach; ?>
        <?php endif; ?>
        <p class="drp-footnote">As of <?= e(date('F j, Y')) ?>. In an emergency, go to the nearest open evacuation center and follow the instructions of the BDRRMC.</p>
    </article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
</script>
</body>
</html>
