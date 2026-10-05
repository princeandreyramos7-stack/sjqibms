<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health.php';
require_once __DIR__ . '/config/site.php';
health_require_manage();
$connection = db();

// Printable health service record (A4), for the resident's copy or the health station file. Health Workers only, within
// their assigned Puroks (health_find()). Every print is kept in the audit log. "Print / Save as PDF" uses the browser.
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$record = $id && health_ready($connection) ? health_find($connection, $id) : null;
if ($record === null) { http_response_code(404); exit('Health record not found.'); }
health_audit($connection, (int) $record['id'], 'health_record_printed', ['record_no' => $record['record_no']]);
$age = residents_age($record['birth_date']);
$dash = '—';
$phase2 = health_phase2_ready($connection);
$medicines = $phase2 ? health_record_medicines($connection, (int) $record['id']) : [];
$vitals = $phase2 ? health_vitals_label($record) : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($record['record_no']) ?> | Health Record</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/disaster_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/disaster_print.css')) ?>" rel="stylesheet">
</head>
<body class="drp-page">
<div class="drp-toolbar">
    <div class="drp-actions">
        <a href="health_view.php?id=<?= e((string) $record['id']) ?>">&larr; Back to the record</a>
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
        <h1 class="drp-title">HEALTH SERVICE RECORD</h1>
        <p class="drp-meta">Record no. <?= e($record['record_no']) ?> · Printed <?= e(date('F j, Y g:i A')) ?></p>

        <h2 class="drp-heading">Resident</h2>
        <table class="drp-table drp-details">
            <tr><th>Name</th><td><?= e(residents_full_name($record)) ?></td><th>Resident ID</th><td>#<?= e((string) $record['resident_id']) ?></td></tr>
            <tr><th>Age</th><td><?= $age === null ? $dash : e((string) $age) ?></td><th>Sex</th><td><?= e(residents_sex_labels()[$record['sex']] ?? $dash) ?></td></tr>
            <tr><th>Purok</th><td colspan="3"><?= e(residents_purok_label((string) $record['purok'])) ?></td></tr>
        </table>

        <h2 class="drp-heading">Service</h2>
        <table class="drp-table drp-details">
            <tr><th>Service</th><td><?= e(health_services()[$record['service']] ?? '') ?></td><th>Date</th><td><?= e(health_format_date($record['service_date'])) ?></td></tr>
            <tr><th>Details</th><td colspan="3"><?= $record['service_details'] ? e($record['service_details']) : $dash ?></td></tr>
            <tr><th>Status</th><td><?= e(health_statuses()[$record['status']] ?? $record['status']) ?></td><th>Follow-up date</th><td><?= $record['follow_up_date'] ? e(health_format_date($record['follow_up_date'])) : $dash ?></td></tr>
            <tr><th>Health worker</th><td colspan="3"><?= e($record['health_worker']) ?></td></tr>
        </table>

        <?php if ($phase2): ?>
        <h2 class="drp-heading">Consultation</h2>
        <table class="drp-table drp-details">
            <tr><th>Vital signs</th><td colspan="3"><?= $vitals !== '' ? e($vitals) : $dash ?></td></tr>
            <tr><th>Chief complaint</th><td colspan="3"><?= $record['chief_complaint'] ? e($record['chief_complaint']) : $dash ?></td></tr>
            <tr><th>Findings</th><td colspan="3" class="resident-wrap"><?= $record['findings'] ? nl2br(e($record['findings'])) : $dash ?></td></tr>
            <tr><th>Diagnosis</th><td colspan="3"><?= $record['condition_name'] ? e($record['condition_name']) : $dash ?></td></tr>
            <tr><th>Medicines given</th><td colspan="3"><?php if ($medicines === []): ?><?= $dash ?><?php else: ?><?= e(implode('; ', array_map(static fn (array $m): string => $m['name'] . ' — ' . number_format((int) $m['quantity']) . ' ' . $m['unit'], $medicines))) ?><?php endif; ?></td></tr>
            <tr><th>Referred to RHU</th><td colspan="3"><?= (int) $record['referred_rhu'] === 1 ? 'Yes' . ($record['referral_reason'] ? ' — ' . e($record['referral_reason']) : '') : 'No' ?></td></tr>
        </table>
        <?php endif; ?>

        <h2 class="drp-heading">Remarks</h2>
        <p class="resident-wrap"><?= $record['remarks'] ? nl2br(e($record['remarks'])) : '<span class="drp-none">No remarks.</span>' ?></p>

        <div class="drp-signatures">
            <div class="drp-signature">
                <p class="drp-signature-label">Attended by:</p>
                <p class="drp-signature-line"></p>
                <p class="drp-signature-name"><?= e($record['health_worker']) ?></p>
                <p class="drp-signature-position">Barangay Health Worker</p>
            </div>
            <div class="drp-signature">
                <p class="drp-signature-label">Received by (resident or representative):</p>
                <p class="drp-signature-line"></p>
                <p class="drp-signature-name"></p>
                <p class="drp-signature-position">Signature over printed name</p>
            </div>
        </div>
        <p class="drp-footnote">Confidential health information under the Data Privacy Act of 2012. For the resident and the Barangay Health Station only.</p>
    </article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
</script>
</body>
</html>
