<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_recovery.php';
disaster_require_view();
$connection = db();
if (!disaster_ready($connection)) { flash('disaster_error', 'Disaster Management needs its database tables first.'); redirect('disaster.php'); }

// Printable Incident Report (A4): barangay header, incident details, affected families and persons, evacuees per center,
// relief distributed, damage summary and signature lines. Everything is read from the module's records; sections
// without data say so. The header, title and signatories are edited in templates/disaster/report_settings.php.
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$incident = $id ? disaster_find($connection, $id) : null;
if ($incident === null || $incident['record_type'] !== 'incident') { http_response_code(404); exit('Incident not found.'); }
$settings = disaster_report_settings();
$data = disaster_recovery_ready($connection) ? disaster_incident_report_data($connection, (int) $incident['id']) : ['evacuees' => [], 'relief' => [], 'relief_cash' => ['entries' => 0, 'total' => 0], 'families_served' => 0, 'damage' => []];
$evac_families = array_sum(array_map(static fn (array $r): int => (int) $r['families'], $data['evacuees']));
$evac_persons = array_sum(array_map(static fn (array $r): int => (int) $r['persons'], $data['evacuees']));
$houses_partial = array_sum(array_map(static fn (array $r): int => (int) $r['houses_partial'], $data['damage']));
$houses_total = array_sum(array_map(static fn (array $r): int => (int) $r['houses_total'], $data['damage']));
$count = static fn ($value): string => $value === null ? 'Not recorded' : number_format((int) $value);
disaster_audit($connection, (int) $incident['id'], 'disaster_incident_report_printed', ['reference' => $incident['reference_no']]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Incident Report <?= e($incident['reference_no']) ?> | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/disaster_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/disaster_print.css')) ?>" rel="stylesheet">
</head>
<body class="drp-page">
<div class="drp-toolbar">
    <div class="drp-actions"><span><?= e($incident['reference_no'] . ' — ' . $incident['title']) ?></span></div>
    <div class="drp-actions">
        <a href="disaster_view.php?id=<?= e((string) $incident['id']) ?>">&larr; Record</a>
        <button class="is-primary" type="button" data-print>Print / Save as PDF</button>
    </div>
</div>
<main class="drp-stage">
    <article class="drp-sheet">
        <?php require __DIR__ . '/layout/disaster_print_header.php'; ?>
        <h1 class="drp-title"><?= e($settings['incident_title']) ?></h1>
        <p class="drp-meta"><?= e($incident['reference_no']) ?> · Prepared <?= e(date('F j, Y g:i A')) ?></p>
        <?php if ($settings['incident_intro'] !== ''): ?><p class="drp-intro"><?= e($settings['incident_intro']) ?></p><?php endif; ?>

        <h2 class="drp-heading">1. Incident Details</h2>
        <table class="drp-table drp-details">
            <tbody>
                <tr><th>Incident</th><td><?= e($incident['title']) ?></td><th>Reference No.</th><td><?= e($incident['reference_no']) ?></td></tr>
                <tr><th>Area</th><td><?= e($incident['area_name']) ?></td><th>Date</th><td><?= e(disaster_format_date($incident['record_date'])) ?></td></tr>
                <tr><th>Status</th><td><?= e($incident['archived_at'] !== null ? 'Archived' : (disaster_statuses()[$incident['status']] ?? '')) ?></td><th>Warning Level</th><td><?= e($incident['alert_level'] ? (disaster_alert_levels()[$incident['alert_level']] ?? '') : 'None') ?></td></tr>
                <tr><th>Description</th><td colspan="3"><?= $incident['description'] ? nl2br(e($incident['description'])) : 'None recorded' ?></td></tr>
                <tr><th>Details</th><td colspan="3"><?= $incident['incident_details'] ? nl2br(e($incident['incident_details'])) : 'None recorded' ?></td></tr>
            </tbody>
        </table>

        <h2 class="drp-heading">2. Affected Population</h2>
        <p class="drp-summary drp-summary-left"><span>Affected families: <strong><?= e($count($incident['affected_families'])) ?></strong></span><span>Affected persons: <strong><?= e($count($incident['affected_persons'])) ?></strong></span><span>Evacuated: <strong><?= e(number_format($evac_families)) ?></strong> families / <strong><?= e(number_format($evac_persons)) ?></strong> persons</span><span>Families given relief: <strong><?= e(number_format($data['families_served'])) ?></strong></span></p>

        <h2 class="drp-heading">3. Evacuees per Evacuation Center</h2>
        <?php if ($data['evacuees'] === []): ?><p class="drp-none">No families were checked in at an evacuation center for this incident.</p><?php else: ?>
            <table class="drp-table">
                <thead><tr><th>Evacuation Center</th><th class="num">Capacity</th><th class="num">Families (total)</th><th class="num">Persons (total)</th><th class="num">Still in center</th></tr></thead>
                <tbody>
                    <?php foreach ($data['evacuees'] as $row): ?><tr><td><?= e($row['name']) ?></td><td class="num"><?= e(number_format((int) $row['capacity'])) ?></td><td class="num"><?= e(number_format((int) $row['families'])) ?></td><td class="num"><?= e(number_format((int) $row['persons'])) ?></td><td class="num"><?= e(number_format((int) $row['families_now'])) ?> fam. / <?= e(number_format((int) $row['persons_now'])) ?> pers.</td></tr><?php endforeach; ?>
                    <tr class="drp-total"><td>Total</td><td></td><td class="num"><?= e(number_format($evac_families)) ?></td><td class="num"><?= e(number_format($evac_persons)) ?></td><td></td></tr>
                </tbody>
            </table>
        <?php endif; ?>

        <h2 class="drp-heading">4. Relief Distributed</h2>
        <?php if ($data['relief'] === [] && (int) $data['relief_cash']['entries'] === 0): ?><p class="drp-none">No relief was recorded for this incident.</p><?php else: ?>
            <?php if ((int) $data['relief_cash']['entries'] > 0): ?><p class="drp-summary drp-summary-left"><span>Cash assistance: <strong>₱<?= e(number_format((float) $data['relief_cash']['total'], 2)) ?></strong> (<?= e(number_format((int) $data['relief_cash']['entries'])) ?> record<?= (int) $data['relief_cash']['entries'] === 1 ? '' : 's' ?>)</span></p><?php endif; ?>
            <?php if ($data['relief'] !== []): ?>
            <table class="drp-table">
                <thead><tr><th>Item</th><th class="num">Quantity</th><th>Unit</th><th class="num">Distributions</th></tr></thead>
                <tbody><?php foreach ($data['relief'] as $row): ?><tr><td><?= e($row['name']) ?></td><td class="num"><?= e(number_format((int) $row['quantity'])) ?></td><td><?= e($row['unit']) ?></td><td class="num"><?= e(number_format((int) $row['distributions'])) ?></td></tr><?php endforeach; ?></tbody>
            </table>
            <?php endif; ?>
        <?php endif; ?>

        <h2 class="drp-heading">5. Damage Summary</h2>
        <?php if ($data['damage'] === []): ?><p class="drp-none">No damage assessment was recorded for this incident.</p><?php else: ?>
            <table class="drp-table">
                <thead><tr><th>Area</th><th class="num">Houses partially damaged</th><th class="num">Houses totally damaged</th><th>Other damage</th></tr></thead>
                <tbody>
                    <?php foreach ($data['damage'] as $row): $other = []; foreach (disaster_damage_other_fields() as $field => $label) if ($row[$field]) $other[] = $label . ': ' . $row[$field]; ?>
                        <tr><td><?= e($row['area_name']) ?><span class="drp-sub"><?= e(disaster_format_date($row['assessed_on'])) ?></span></td><td class="num"><?= e(number_format((int) $row['houses_partial'])) ?></td><td class="num"><?= e(number_format((int) $row['houses_total'])) ?></td><td><?= e($other === [] ? '—' : implode('; ', $other)) ?><?= $row['notes'] ? '<span class="drp-sub">' . e($row['notes']) . '</span>' : '' ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="drp-total"><td>Total</td><td class="num"><?= e(number_format($houses_partial)) ?></td><td class="num"><?= e(number_format($houses_total)) ?></td><td></td></tr>
                </tbody>
            </table>
        <?php endif; ?>

        <section class="drp-signatures">
            <?php foreach ($settings['signatories'] as $signatory): ?>
                <div class="drp-signature">
                    <p class="drp-signature-label"><?= e((string) ($signatory['label'] ?? '')) ?></p>
                    <div class="drp-signature-line"></div>
                    <p class="drp-signature-name"><?= e((string) ($signatory['name'] ?? '')) ?: '&nbsp;' ?></p>
                    <p class="drp-signature-position"><?= e((string) ($signatory['position'] ?? '')) ?: 'Signature over printed name' ?></p>
                </div>
            <?php endforeach; ?>
        </section>
    </article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
</script>
</body>
</html>
