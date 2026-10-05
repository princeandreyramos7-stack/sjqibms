<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/inventory.php';
inventory_require_manage();
$connection = db();
if (!inventory_ready($connection)) { flash('inventory_error', 'Inventory needs its database tables first.'); redirect('inventory.php'); }

// Categories and Locations used by inventory items. Entries are renamed or deactivated, never deleted, so existing
// items always keep their category and location.
$tab = ($_GET['tab'] ?? $_POST['tab'] ?? 'categories') === 'locations' ? 'locations' : 'categories';
$table = $tab === 'locations' ? 'inventory_locations' : 'inventory_categories';
$column = $tab === 'locations' ? 'location_id' : 'category_id';
$singular = $tab === 'locations' ? 'location' : 'category';
$max = $tab === 'locations' ? 120 : 80;
$edit_id = filter_var($_GET['edit'] ?? $_POST['edit_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$errors = [];
$form_error = null;
$name_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('inventory_error', 'Your session expired. Please try again.'); redirect('inventory_lists.php?tab=' . $tab); }
    try {
        if ($action === 'save') {
            $name_input = residents_collapse((string) ($_POST['name'] ?? ''));
            if (mb_strlen($name_input) < 2 || mb_strlen($name_input) > $max || !preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\s&.,\'()\/\-]*$/u', $name_input)) $errors['name'] = "Enter a $singular name of 2 to $max characters.";
            if ($errors === []) {
                if ($edit_id) {
                    $connection->prepare("UPDATE $table SET name = :name WHERE id = :id")->execute(['name' => $name_input, 'id' => $edit_id]);
                    residents_audit($connection, 'inventory_list', $edit_id, "inventory_{$singular}_updated", ['name' => $name_input]);
                    flash('inventory_success', ucfirst($singular) . ' renamed. Items using it show the new name.');
                } else {
                    $connection->prepare("INSERT INTO $table (name, created_by) VALUES (:name, :user)")->execute(['name' => $name_input, 'user' => current_user()['id']]);
                    residents_audit($connection, 'inventory_list', (int) $connection->lastInsertId(), "inventory_{$singular}_created", ['name' => $name_input]);
                    flash('inventory_success', ucfirst($singular) . ' added.');
                }
                redirect('inventory_lists.php?tab=' . $tab);
            }
        } elseif ($action === 'toggle' && $edit_id) {
            $connection->prepare("UPDATE $table SET is_active = 1 - is_active WHERE id = :id")->execute(['id' => $edit_id]);
            residents_audit($connection, 'inventory_list', $edit_id, "inventory_{$singular}_status_changed");
            flash('inventory_success', ucfirst($singular) . ' status updated. Items that already use it keep it.');
            redirect('inventory_lists.php?tab=' . $tab);
        }
    } catch (PDOException $exception) {
        $form_error = ($exception->errorInfo[1] ?? 0) === 1062 ? "A $singular with this name already exists." : 'The change could not be saved. No changes were made.';
    }
}

$rows = $connection->query("SELECT t.id, t.name, t.is_active, (SELECT COUNT(*) FROM inventory_items i WHERE i.$column = t.id AND i.archived_at IS NULL) AS items FROM $table t ORDER BY t.is_active DESC, t.name")->fetchAll();
$editing = null;
foreach ($rows as $row) if ($edit_id && (int) $row['id'] === $edit_id) $editing = $row;
$name_value = $_SERVER['REQUEST_METHOD'] === 'POST' ? $name_input : (string) ($editing['name'] ?? '');
$page_title = 'Categories & Locations'; $active_page = 'inventory';
$page_styles = ['assets/css/inventory.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <a class="announcement-back" href="inventory.php"><span aria-hidden="true">&larr;</span> Back to Inventory</a>
    <div class="page-heading"><div><h1>Categories &amp; Locations</h1><p>Lists used when recording inventory items. Entries can be renamed or deactivated; they are never deleted.</p></div></div>
    <?php if ($success = flash('inventory_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('inventory_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php endif; ?>
    <section class="dashboard-panel announcements-page">
        <nav class="announcement-tabs page-tabs" aria-label="Inventory lists">
            <a class="announcement-tab <?= $tab === 'categories' ? 'active' : '' ?>" href="inventory_lists.php">Categories</a>
            <a class="announcement-tab <?= $tab === 'locations' ? 'active' : '' ?>" href="inventory_lists.php?tab=locations">Locations</a>
        </nav>
        <form class="case-inline-add case-admin-form" method="post" action="inventory_lists.php">
            <?= csrf_field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><input type="hidden" name="action" value="save"><?php if ($editing): ?><input type="hidden" name="edit_id" value="<?= e((string) $editing['id']) ?>"><?php endif; ?>
            <div><label class="form-label" for="list-name"><?= $editing ? 'Rename ' . e($singular) : 'New ' . e($singular) ?></label><input class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="list-name" name="name" value="<?= e($name_value) ?>" maxlength="<?= e((string) $max) ?>" required><?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?></div>
            <div class="doc-action-group"><button class="btn doc-action-btn is-primary" type="submit"><?= $editing ? 'Save Name' : 'Add ' . e(ucfirst($singular)) ?></button><?php if ($editing): ?><a class="btn doc-action-btn is-view" href="inventory_lists.php?tab=<?= e($tab) ?>">Cancel</a><?php endif; ?></div>
        </form>
        <?php if ($rows === []): ?><div class="dashboard-empty-state">No entries yet.</div><?php else: ?>
            <ul class="case-link-list inventory-list-entries">
                <?php foreach ($rows as $row): ?>
                    <li>
                        <div><strong><?= e($row['name']) ?></strong> <?= (int) $row['is_active'] === 1 ? '<span class="resident-status resident-status-active">Active</span>' : '<span class="resident-status resident-status-inactive">Inactive</span>' ?><br><span class="activity-detail-muted"><?= e((string) $row['items']) ?> item<?= (int) $row['items'] === 1 ? '' : 's' ?></span></div>
                        <div class="doc-action-group">
                            <a class="btn doc-action-btn is-view" href="inventory_lists.php?tab=<?= e($tab) ?>&amp;edit=<?= e((string) $row['id']) ?>">Rename</a>
                            <form method="post" action="inventory_lists.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="edit_id" value="<?= e((string) $row['id']) ?>"><button class="btn doc-action-btn <?= (int) $row['is_active'] === 1 ? 'is-danger' : 'is-primary' ?>" type="submit" data-form-confirm="custom" data-dialog-heading="<?= (int) $row['is_active'] === 1 ? 'Deactivate ' . e($row['name']) . '?' : 'Activate ' . e($row['name']) . '?' ?>" data-dialog-message="<?= (int) $row['is_active'] === 1 ? 'It will no longer be offered for new items. Items that already use it keep it.' : 'It will be offered again when recording items.' ?>" data-dialog-confirm="<?= (int) $row['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>" data-dialog-dismiss="Cancel"<?= (int) $row['is_active'] === 1 ? ' data-dialog-danger="true"' : '' ?>><?= (int) $row['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
