<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/inventory.php';
inventory_require_manage();
$connection = db();
if (!inventory_ready($connection)) { flash('inventory_error', 'Inventory needs its database tables first.'); redirect('inventory.php'); }

// Printable Inventory Report (A4). Filters: type, category, location and date acquired range; filters chosen on the
// Inventory list (status, condition, search, expiring) are carried over when the report is opened from there.
// "Print / Save as PDF" uses the browser's print dialog (choose "Save as PDF" to create the PDF file).
$state = inventory_list_state($_GET, $connection);
$dates = inventory_report_dates($_GET);
$settings = inventory_report_settings();
$rows = inventory_report_rows($connection, $state, $dates);
$tracking = inventory_borrowing_ready($connection);
$groups = [];
foreach ($rows as $row) $groups[$row['category_name']][] = $row;
$categories = inventory_list($connection, 'categories', $state['category']);
$locations = inventory_list($connection, 'locations', $state['location']);
$summary = inventory_filter_summary($connection, $state, $dates);
$carried = array_filter(['status' => $state['status'], 'condition' => $state['condition'], 'q' => $state['q'], 'expiring' => $state['expiring'] ? '1' : '', 'sort' => $state['sort'] === 'code' ? '' : $state['sort'], 'dir' => $state['dir'] === 'asc' ? '' : $state['dir']], static fn ($v): bool => $v !== '');
$clear_extra = 'inventory_report.php?' . http_build_query(array_filter(['type' => $state['type'], 'category' => $state['category'], 'location' => $state['location']] + $dates, static fn ($v): bool => $v !== null && $v !== ''));
$owned = static fn (array $row): int => (int) $row['quantity'] + (int) $row['borrowed_out'];
$value = static fn (array $row) => $row['unit_cost'] !== null ? (float) $row['unit_cost'] * $owned($row) : null;
$grand_value = array_sum(array_map(static fn (array $row): float => (float) ($value($row) ?? 0), $rows));
inventory_audit($connection, 0, 'inventory_report_generated', ['rows' => count($rows), 'filters' => $summary]);
$auto_print = ($_GET['print'] ?? '') === '1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Inventory Report | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/inventory_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/inventory_print.css')) ?>" rel="stylesheet">
</head>
<body class="invp-page">
<div class="invp-toolbar">
    <form class="invp-filters" method="get" action="inventory_report.php">
        <?php foreach ($carried as $name => $carried_value): ?><input type="hidden" name="<?= e($name) ?>" value="<?= e((string) $carried_value) ?>"><?php endforeach; ?>
        <label>Type<select name="type"><option value="">All</option><?php foreach (inventory_types() as $key => $label): ?><option value="<?= e($key) ?>" <?= $state['type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Category<select name="category"><option value="">All</option><?php foreach ($categories as $row): ?><option value="<?= e((string) $row['id']) ?>" <?= $state['category'] === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option><?php endforeach; ?></select></label>
        <label>Location<select name="location"><option value="">All</option><?php foreach ($locations as $row): ?><option value="<?= e((string) $row['id']) ?>" <?= $state['location'] === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option><?php endforeach; ?></select></label>
        <label>Acquired from<input type="date" name="acquired_from" value="<?= e($dates['acquired_from']) ?>"></label>
        <label>to<input type="date" name="acquired_to" value="<?= e($dates['acquired_to']) ?>"></label>
        <button type="submit">Apply</button>
    </form>
    <div class="invp-actions">
        <a href="inventory.php">&larr; Inventory</a>
        <a href="inventory_labels.php?<?= e(inventory_query_string($state)) ?>">Label Sheet</a>
        <button class="is-primary" type="button" data-print>Print / Save as PDF</button>
    </div>
    <?php if ($carried !== []): ?><p class="invp-note">Also using the filters chosen on the Inventory list. <a href="<?= e($clear_extra) ?>">Remove them</a></p><?php endif; ?>
</div>

<main class="invp-stage">
    <article class="invp-sheet">
        <header class="invp-header">
            <?php if ($settings['logo'] !== ''): ?><img class="invp-logo" src="<?= e($settings['logo']) ?>" alt="Logo"><?php else: ?><span class="invp-logo is-placeholder" aria-hidden="true">LOGO</span><?php endif; ?>
            <div class="invp-header-text">
                <?php foreach ($settings['header_lines'] as $line): ?><p><?= e($line) ?></p><?php endforeach; ?>
                <p class="invp-barangay"><?= e($settings['barangay_name']) ?></p>
                <?php if ($settings['address'] !== ''): ?><p><?= e($settings['address']) ?></p><?php endif; ?>
            </div>
            <span class="invp-logo is-spacer" aria-hidden="true"></span>
        </header>
        <h1 class="invp-title"><?= e($settings['title']) ?></h1>
        <p class="invp-meta">As of <?= e(date('F j, Y g:i A')) ?> · <?= e($summary) ?> · <?= e((string) count($rows)) ?> item<?= count($rows) === 1 ? '' : 's' ?></p>

        <?php if ($rows === []): ?>
            <p class="invp-empty">No items match the selected filters.</p>
        <?php else: ?>
            <table class="invp-table">
                <thead><tr><th>Code</th><th class="invp-col-item">Item</th><th class="num"><?= $tracking ? 'Qty (owned)' : 'Qty' ?></th><th>Unit</th><th>Location</th><th>Status</th><th>Condition</th><th>Acquired</th><th class="num">Unit Cost</th><th class="num">Total Value</th></tr></thead>
                <?php foreach ($groups as $category => $items): $subtotal = array_sum(array_map(static fn (array $row): float => (float) ($value($row) ?? 0), $items)); ?>
                    <tbody>
                        <tr class="invp-group"><th colspan="10"><?= e($category) ?> <span>(<?= e((string) count($items)) ?> item<?= count($items) === 1 ? '' : 's' ?>)</span></th></tr>
                        <?php foreach ($items as $row): ?>
                            <tr>
                                <td class="nowrap"><?= e($row['item_code']) ?></td>
                                <td><?= e($row['name']) ?><?php if ((int) $row['borrowed_out'] > 0): ?><span class="invp-sub"><?= e(number_format((int) $row['borrowed_out'])) ?> borrowed out</span><?php endif; ?></td>
                                <td class="num"><?= e(number_format($owned($row))) ?></td>
                                <td><?= e($row['unit']) ?></td>
                                <td><?= e($row['location_name']) ?></td>
                                <td><?= e($row['archived_at'] !== null ? 'Archived' : (inventory_statuses()[$row['status']] ?? '')) ?></td>
                                <td><?= e(inventory_conditions()[$row['item_condition']] ?? '') ?></td>
                                <td class="nowrap"><?= e($row['date_acquired'] ? date('M j, Y', strtotime($row['date_acquired'])) : '—') ?></td>
                                <td class="num"><?= $row['unit_cost'] !== null ? e(number_format((float) $row['unit_cost'], 2)) : '—' ?></td>
                                <td class="num"><?= $value($row) !== null ? e(number_format($value($row), 2)) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="invp-subtotal"><td colspan="9">Subtotal — <?= e($category) ?></td><td class="num"><?= e(number_format($subtotal, 2)) ?></td></tr>
                    </tbody>
                <?php endforeach; ?>
                <tbody class="invp-grand"><tr><td colspan="9">GRAND TOTAL (<?= e((string) count($rows)) ?> items)</td><td class="num">₱<?= e(number_format($grand_value, 2)) ?></td></tr></tbody>
            </table>
            <p class="invp-footnote">Quantities are the total owned (on hand plus borrowed out). Total value = unit cost × quantity owned; items without a unit cost are shown as —.</p>
        <?php endif; ?>

        <section class="invp-signatures">
            <?php foreach ($settings['signatories'] as $signatory): ?>
                <div class="invp-signature">
                    <p class="invp-signature-label"><?= e((string) ($signatory['label'] ?? '')) ?></p>
                    <div class="invp-signature-line"></div>
                    <p class="invp-signature-name"><?= e((string) ($signatory['name'] ?? '')) ?: '&nbsp;' ?></p>
                    <p class="invp-signature-position"><?= e((string) ($signatory['position'] ?? '')) ?: 'Signature over printed name' ?></p>
                </div>
            <?php endforeach; ?>
        </section>
    </article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
<?php if ($auto_print): ?>window.addEventListener('load', () => window.print());<?php endif; ?>
</script>
</body>
</html>
