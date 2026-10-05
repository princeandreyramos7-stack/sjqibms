<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_prep.php';
disaster_require_view();
$connection = db();

// Printable blank evacuation logbook (A4) for the evacuation center: the staff on duty write each family in by hand,
// and the entries are typed into the incident's Evacuees afterwards. ?incident= fills in the incident; ?center= the
// center. Nothing personal is printed. "Print / Save as PDF" uses the browser's print dialog.
$incident_id = filter_var($_GET['incident'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$center_id = filter_var($_GET['center'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$incident = null;
if ($incident_id !== null) {
    $statement = $connection->prepare('SELECT id, reference_no, title FROM drr_records WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $incident_id]);
    $incident = $statement->fetch() ?: null;
}
$centers = disaster_prep_ready($connection) ? disaster_current_centers($connection) : [];
$center = null;
foreach ($centers as $option) if ((int) $option['id'] === $center_id) $center = $option;
$rows = max(10, min(40, (int) ($_GET['rows'] ?? 20)));
$settings = disaster_report_settings();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Evacuation Logbook | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/disaster_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/disaster_print.css')) ?>" rel="stylesheet">
    <style>
        .drp-blank td { height: 30px; }
        .drp-fill { display: inline-block; min-width: 220px; border-bottom: 1px solid #333; }
        .drp-lines p { margin: 6px 0; }
    </style>
</head>
<body class="drp-page">
<div class="drp-toolbar">
    <form class="drp-filters" method="get" action="disaster_logbook_print.php">
        <?php if ($incident !== null): ?><input type="hidden" name="incident" value="<?= e((string) $incident['id']) ?>"><?php endif; ?>
        <label>Center<select name="center"><option value="">Write by hand</option><?php foreach ($centers as $option): ?><option value="<?= e((string) $option['id']) ?>" <?= $center_id === (int) $option['id'] ? 'selected' : '' ?>><?= e($option['name']) ?></option><?php endforeach; ?></select></label>
        <label>Rows<select name="rows"><?php foreach ([15, 20, 25, 30] as $count): ?><option value="<?= $count ?>" <?= $rows === $count ? 'selected' : '' ?>><?= $count ?></option><?php endforeach; ?></select></label>
        <button type="submit">Apply</button>
    </form>
    <div class="drp-actions">
        <a href="<?= $incident !== null ? 'disaster_view.php?id=' . e((string) $incident['id']) : 'disaster.php' ?>">&larr; Back</a>
        <button class="is-primary" type="button" data-print>Print / Save as PDF</button>
    </div>
</div>
<main class="drp-stage">
    <article class="drp-sheet">
        <header class="drp-header">
            <?php if ($settings['logo'] !== ''): ?><img class="drp-logo" src="<?= e($settings['logo']) ?>" alt="Logo"><?php else: ?><span class="drp-logo is-placeholder" aria-hidden="true">LOGO</span><?php endif; ?>
            <div class="drp-header-text">
                <?php foreach ($settings['header_lines'] as $line): ?><p><?= e($line) ?></p><?php endforeach; ?>
                <p class="drp-barangay"><?= e($settings['barangay_name']) ?></p>
                <?php if ($settings['office'] !== ''): ?><p class="drp-office"><?= e($settings['office']) ?></p><?php endif; ?>
            </div>
            <span class="drp-logo" aria-hidden="true"></span>
        </header>
        <h1 class="drp-title">EVACUATION LOGBOOK</h1>
        <div class="drp-lines">
            <p>Incident: <span class="drp-fill"><?= $incident !== null ? e($incident['reference_no'] . ' — ' . $incident['title']) : '&nbsp;' ?></span></p>
            <p>Evacuation center: <span class="drp-fill"><?= $center !== null ? e($center['name']) : '&nbsp;' ?></span> &nbsp; Date: <span class="drp-fill" style="min-width: 140px;">&nbsp;</span></p>
            <p>Staff on duty: <span class="drp-fill">&nbsp;</span></p>
        </div>
        <table class="drp-table drp-blank">
            <thead><tr><th>#</th><th>Time in</th><th>Name of household head / evacuee</th><th>Household no.</th><th>Purok</th><th class="num">No. of persons</th><th>Contact no.</th><th>Time out</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php for ($i = 1; $i <= $rows; $i++): ?>
                <tr><td class="nowrap"><?= $i ?></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            <?php endfor; ?>
            </tbody>
        </table>
        <p class="drp-footnote">Write one family per line. Afterwards, type each entry into the incident's Evacuees in SJQIBMS (Disaster Management → Incidents).</p>
    </article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
</script>
</body>
</html>
