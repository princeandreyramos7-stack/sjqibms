<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_prep.php';
disaster_require_view();
if (!disaster_can_view_vulnerable()) { http_response_code(403); exit('Access denied.'); }   // names of vulnerable residents: not the Treasurer
$connection = db();

// Printable Vulnerable Residents list (A4), grouped by Purok, with the same filters as the list page.
// "Print / Save as PDF" uses the browser's print dialog.
$state = disaster_vulnerable_state($_GET);
$rows = disaster_vulnerable_rows($connection, $state);
$groups = disaster_vulnerable_grouped($rows);
$settings = disaster_report_settings();
$counts = disaster_vulnerable_counts($rows);
$sector_ready = residents_sector_ready($connection);
$summary = implode(' · ', array_filter([$state['purok'] !== '' ? residents_purok_label($state['purok']) : 'All Puroks', $state['group'] !== '' ? disaster_vulnerable_groups()[$state['group']] : ($sector_ready ? 'Senior citizens, children under 5, PWD and solo parents' : 'Senior citizens and children under 5'), $state['q'] !== '' ? 'Search: ' . $state['q'] : '']));
residents_audit($connection, 'disaster', 0, 'disaster_vulnerable_printed', ['rows' => count($rows), 'filters' => $summary]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Vulnerable Residents | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/disaster_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/disaster_print.css')) ?>" rel="stylesheet">
</head>
<body class="drp-page">
<div class="drp-toolbar">
    <form class="drp-filters" method="get" action="disaster_vulnerable_print.php">
        <?php if ($state['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($state['q']) ?>"><?php endif; ?>
        <label>Purok<select name="purok"><option value="">All</option><?php foreach (disaster_vulnerable_puroks($connection) as $purok): ?><option value="<?= e($purok) ?>" <?= $state['purok'] === $purok ? 'selected' : '' ?>><?= e(residents_purok_label($purok)) ?></option><?php endforeach; ?></select></label>
        <label>Category<select name="group"><option value="">All</option><?php foreach (disaster_vulnerable_groups() as $value => $label): ?><option value="<?= e($value) ?>" <?= $state['group'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <button type="submit">Apply</button>
    </form>
    <div class="drp-actions">
        <a href="disaster_vulnerable.php">&larr; Vulnerable Residents</a>
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
                <?php if ($settings['address'] !== ''): ?><p><?= e($settings['address']) ?></p><?php endif; ?>
            </div>
            <span class="drp-logo" aria-hidden="true"></span>
        </header>
        <h1 class="drp-title">VULNERABLE RESIDENTS</h1>
        <p class="drp-meta">As of <?= e(date('F j, Y g:i A')) ?> · <?= e($summary) ?></p>
        <p class="drp-summary"><span><strong><?= e((string) count($rows)) ?></strong> residents</span><?php foreach ($counts as $group => $count): ?><span><strong><?= e((string) $count) ?></strong> <?= e(disaster_vulnerable_groups()[$group]) ?></span><?php endforeach; ?></p>
        <?php if ($rows === []): ?>
            <p class="drp-empty">No residents match the selected filters.</p>
        <?php else: ?>
            <table class="drp-table">
                <thead><tr><th>#</th><th>Name</th><th>Category</th><th class="num">Age</th><th>Sex</th><th>Household</th><th>Address</th><th>Contact</th></tr></thead>
                <?php foreach ($groups as $purok => $group): $number = 0; ?>
                    <tbody>
                        <tr class="drp-group"><th colspan="8"><?= e($purok) ?> <span>(<?= e((string) count($group['rows'])) ?> — <?= e(disaster_vulnerable_summary($group['counts'])) ?>)</span></th></tr>
                        <?php foreach ($group['rows'] as $row): ?>
                            <tr>
                                <td class="nowrap"><?= e((string) ++$number) ?></td>
                                <td><?= e(residents_full_name($row)) ?></td>
                                <td><?= e(implode(', ', array_map('disaster_vulnerable_short_label', $row['vulnerable_groups']))) ?></td>
                                <td class="num"><?= e(disaster_age_label($row['birth_date'])) ?></td>
                                <td><?= e(residents_sex_labels()[$row['sex']] ?? '—') ?></td>
                                <td class="nowrap"><?= e($row['household_no'] ?: '—') ?></td>
                                <td><?= e(residents_collapse($row['address'])) ?></td>
                                <td class="nowrap"><?= e($row['contact_number'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
        <p class="drp-footnote">Taken from the Residents records (active residents only). Ages are computed from the birth date on the day of printing.<?= $sector_ready ? ' PWD and solo parents are taken from the resident profiles. A resident in more than one group is listed once.' . (disaster_priority_visible() ? ' "Priority" residents are flagged from the Health records for priority help (no health information is shown).' : '') : ' PWD and solo parents are not included because resident profiles do not record them.' ?></p>
    </article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
</script>
</body>
</html>
