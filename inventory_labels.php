<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/inventory.php';
inventory_require_manage();
$connection = db();
if (!inventory_ready($connection)) { flash('inventory_error', 'Inventory needs its database tables first.'); redirect('inventory.php'); }

// Printable item label sheet (A4, 3 × 7 labels of 63.5 × 38.1 mm — standard 21-up label paper): item code, name and a
// QR code that opens the item's View Details page. ?id= prints one item's label; otherwise the Inventory list filters apply.
$settings = inventory_report_settings();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$state = inventory_list_state($_GET, $connection);
if ($id !== null) {
    $one = inventory_find($connection, $id);
    if ($one === null) { http_response_code(404); exit('Inventory item not found.'); }
    $labels = [$one];
    $copies = max(1, min(21, (int) ($_GET['copies'] ?? 1)));
    $labels = array_fill(0, $copies, $one);
} else {
    $labels = inventory_report_rows($connection, $state, ['acquired_from' => '', 'acquired_to' => ''], 300, false);
}
inventory_audit($connection, $id ?? 0, 'inventory_labels_printed', ['labels' => count($labels)]);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Inventory Labels | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/inventory_print.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/inventory_print.css')) ?>" rel="stylesheet">
</head>
<body class="invp-page">
<div class="invp-toolbar">
    <div class="invp-toolbar-title"><strong>Item Labels</strong> <span><?= e((string) count($labels)) ?> label<?= count($labels) === 1 ? '' : 's' ?> · A4, 3 × 7 (63.5 × 38.1 mm)<?= $id === null ? ' · ' . e(inventory_filter_summary($connection, $state)) : '' ?></span></div>
    <div class="invp-actions">
        <a href="<?= $id !== null ? 'inventory_view.php?id=' . e((string) $id) : 'inventory.php' ?>">&larr; Back</a>
        <?php if ($id !== null): ?><form method="get" class="invp-copies"><input type="hidden" name="id" value="<?= e((string) $id) ?>"><label>Copies <input type="number" name="copies" min="1" max="21" value="<?= e((string) count($labels)) ?>"></label><button type="submit">Update</button></form><?php endif; ?>
        <button class="is-primary" type="button" data-print>Print Labels</button>
    </div>
    <p class="invp-note">Print at 100% scale ("Actual size"), with no margins added by the browser, on A4 label paper or plain paper.</p>
</div>
<main class="invp-stage">
    <?php if ($labels === []): ?>
        <p class="invp-empty">No items match the selected filters.</p>
    <?php else: foreach (array_chunk($labels, 21) as $page): ?>
        <section class="invp-label-sheet">
            <?php foreach ($page as $label): ?>
                <div class="invp-label">
                    <div class="invp-qr" data-qr-url="<?= e(inventory_item_url((int) $label['id'])) ?>" role="img" aria-label="QR code for <?= e($label['item_code']) ?>"></div>
                    <div class="invp-label-text">
                        <span class="invp-label-org"><?= e($settings['barangay_name']) ?></span>
                        <strong class="invp-label-code"><?= e($label['item_code']) ?></strong>
                        <span class="invp-label-name"><?= e($label['name']) ?></span>
                        <span class="invp-label-meta"><?= e($label['location_name'] ?? '') ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endforeach; endif; ?>
</main>
<?= inventory_qr_script_tags() ?>
<script>document.querySelector('[data-print]').addEventListener('click', () => window.print());</script>
</body>
</html>
