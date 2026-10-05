<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/assistance.php';
assistance_require_view();
$connection = db();
if (!assistance_ready($connection)) { flash('assistance_error', 'Relief & Assistance needs its database update first.'); redirect('assistance.php'); }

// Acknowledgment Receipt (A4) of a Given record, signed by hand: Received by (the person who received it), Prepared by
// (the user who recorded it) and Noted by (templates/assistance/print_settings.php). "Print / Save as PDF" uses the
// browser's print dialog. Scheduled and Void records have no acknowledgment.
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$entry = $id ? assistance_find($connection, $id) : null;
if ($entry === null) { http_response_code(404); exit('Record not found.'); }
if ($entry['status'] !== 'given') { flash('assistance_error', 'Only a Given record has an acknowledgment to print.'); redirect('assistance_view.php?id=' . $entry['id']); }
$lines = assistance_items($connection, (int) $entry['id']);
$settings = assistance_print_settings();
$name = assistance_beneficiary_label($entry);
$form = assistance_form_of($entry);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Acknowledgment <?= e($entry['reference_no']) ?> | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/disaster_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/disaster_print.css')) ?>" rel="stylesheet">
    <link href="assets/css/assistance_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/assistance_print.css')) ?>" rel="stylesheet">
</head>
<body class="drp-page">
<div class="drp-toolbar">
    <div class="drp-actions"><span><?= e($entry['reference_no'] . ' — ' . $name) ?></span></div>
    <div class="drp-actions">
        <a href="assistance_view.php?id=<?= e((string) $entry['id']) ?>">&larr; Record</a>
        <button class="is-primary" type="button" data-print>Print / Save as PDF</button>
    </div>
</div>
<main class="drp-stage">
    <article class="drp-sheet ast-sheet">
        <?php require __DIR__ . '/layout/disaster_print_header.php'; ?>
        <p class="ast-system">SJQIBMS — Barangay Management System</p>
        <h1 class="drp-title"><?= e($settings['title']) ?></h1>
        <p class="drp-meta">Reference No. <strong><?= e($entry['reference_no']) ?></strong> · Printed <?= e(date('F j, Y g:i A')) ?></p>
        <table class="drp-table drp-details">
            <tbody>
                <tr><th>Beneficiary</th><td><?= e($name) ?></td><th><?= $entry['household_id'] !== null ? 'Household No.' : 'Resident ID' ?></th><td><?= $entry['household_id'] !== null ? e($entry['household_no']) : '#' . e((string) $entry['resident_id']) ?></td></tr>
                <tr><th>Purok</th><td><?= e(assistance_beneficiary_purok($entry)) ?></td><th>Priority group</th><td><?= e(assistance_group_text((string) $entry['priority_groups'])) ?></td></tr>
                <tr><th>Assistance</th><td><?= e(assistance_category_label($entry['assistance_type'])) ?> (<?= e(assistance_forms()[$form] ?? '') ?>)</td><th>Date given</th><td><?= e(disaster_format_date($entry['given_on'])) ?></td></tr>
                <tr><th>Source</th><td><?= e($entry['source'] ? (assistance_sources()[$entry['source']] ?? '') : 'Not recorded') ?><?= $entry['source_details'] ? ' · ' . e($entry['source_details']) : '' ?></td><th>Document no.</th><td><?= $entry['document_no'] ? e($entry['document_no']) : '—' ?></td></tr>
                <?php if ($entry['incident_id'] !== null): ?><tr><th>Related incident</th><td colspan="3"><?= e($entry['incident_reference'] . ' — ' . $entry['incident_title']) ?></td></tr><?php endif; ?>
                <tr><th>Purpose</th><td colspan="3"><?= nl2br(e($entry['purpose'])) ?></td></tr>
            </tbody>
        </table>
        <h2 class="drp-heading">Assistance Provided</h2>
        <table class="drp-table">
            <thead><tr><th>Description</th><th class="num">Amount / Quantity</th></tr></thead>
            <tbody>
                <?php if ($form === 'cash'): ?><tr><td>Cash assistance</td><td class="num"><?= e($entry['cash_amount'] !== null ? assistance_peso($entry['cash_amount']) : '—') ?></td></tr><?php endif; ?>
                <?php foreach ($lines as $line): ?><tr><td><?= e($line['name']) ?></td><td class="num"><?= e(number_format((int) $line['quantity']) . ' ' . $line['unit']) ?></td></tr><?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($settings['statement'] !== ''): ?><p class="ast-statement"><?= e($settings['statement']) ?></p><?php endif; ?>
        <div class="drp-signatures ast-signatures">
            <div class="drp-signature">
                <p class="drp-signature-label">Received by:</p>
                <p class="drp-signature-line"></p>
                <p class="drp-signature-name"><?= e($entry['received_by']) ?></p>
                <p class="drp-signature-position"><?= $entry['receiver_relationship'] ? e($entry['receiver_relationship']) . ' of the beneficiary' : 'Beneficiary' ?></p>
            </div>
            <div class="drp-signature">
                <p class="drp-signature-label">Prepared by:</p>
                <p class="drp-signature-line"></p>
                <p class="drp-signature-name"><?= e((string) ($entry['created_by_name'] ?? '')) ?></p>
                <p class="drp-signature-position">Recorded in SJQIBMS</p>
            </div>
            <div class="drp-signature">
                <p class="drp-signature-label">Noted by:</p>
                <p class="drp-signature-line"></p>
                <p class="drp-signature-name"><?= e((string) ($settings['noted_by']['name'] ?? '')) ?></p>
                <p class="drp-signature-position"><?= e((string) ($settings['noted_by']['position'] ?? '')) ?></p>
            </div>
        </div>
    </article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
</script>
</body>
</html>
