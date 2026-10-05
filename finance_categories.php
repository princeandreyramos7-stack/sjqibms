<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
finance_require('categories');
$connection = db();
if (!finance_ready($connection)) { flash('finance_error', 'Financial Management needs its database tables first.'); redirect('finance.php'); }

// Income and Expense categories (Treasurer only). Categories are renamed or deactivated, never deleted, so existing
// transactions always keep their category. The type (Income / Expense) of a category never changes.
$edit_id = filter_var($_GET['edit'] ?? $_POST['edit_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$errors = [];
$form_error = null;
$name_input = '';
$type_input = (string) ($_POST['type'] ?? 'income');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('finance_error', 'Your session expired. Please try again.'); redirect('finance_categories.php'); }
    $target = $edit_id ? finance_category($connection, $edit_id) : null;
    try {
        if ($edit_id && $target === null) throw new RuntimeException('The category no longer exists.');
        if ($action === 'save') {
            $name_input = residents_collapse((string) ($_POST['name'] ?? ''));
            if (mb_strlen($name_input) < 2 || mb_strlen($name_input) > 80 || !preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\s&.,\'()\/\-]*$/u', $name_input)) $errors['name'] = 'Enter a category name of 2 to 80 characters.';
            if (!$target && !array_key_exists($type_input, finance_types())) $errors['type'] = 'Select Income or Expense.';
            if ($errors === []) {
                if ($target) {
                    $connection->prepare('UPDATE finance_categories SET name = :name WHERE id = :id')->execute(['name' => $name_input, 'id' => $edit_id]);
                    residents_audit($connection, 'finance_category', $edit_id, 'finance_category_updated', ['name' => $name_input, 'changes' => ['name' => ['old' => $target['name'], 'new' => $name_input]]]);
                    flash('finance_success', 'Category renamed. Transactions using it show the new name.');
                } else {
                    $connection->prepare('INSERT INTO finance_categories (name, type, created_by) VALUES (:name, :type, :user)')->execute(['name' => $name_input, 'type' => $type_input, 'user' => current_user()['id']]);
                    residents_audit($connection, 'finance_category', (int) $connection->lastInsertId(), 'finance_category_created', ['name' => $name_input, 'type' => $type_input]);
                    flash('finance_success', 'Category added.');
                }
                redirect('finance_categories.php');
            }
        } elseif ($action === 'toggle' && $target) {
            if ((int) $target['is_allotment'] === 1 && (int) $target['is_active'] === 1) throw new RuntimeException('The Allotment category is used for NTA numbers and cannot be deactivated.');
            $connection->prepare('UPDATE finance_categories SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $edit_id]);
            residents_audit($connection, 'finance_category', $edit_id, 'finance_category_status_changed', ['name' => $target['name'], 'active' => (int) $target['is_active'] === 1 ? 'no' : 'yes']);
            flash('finance_success', 'Category status updated. Transactions that already use it keep it.');
            redirect('finance_categories.php');
        }
    } catch (PDOException $exception) {
        $form_error = ($exception->errorInfo[1] ?? 0) === 1062 ? 'A category with this name already exists.' : 'The change could not be saved. No changes were made.';
    } catch (RuntimeException $exception) {
        $form_error = $exception->getMessage();
    }
}

$rows = $connection->query('SELECT c.id, c.name, c.type, c.is_allotment, c.is_active, (SELECT COUNT(*) FROM finance_transactions t WHERE t.category_id = c.id) AS transactions FROM finance_categories c ORDER BY c.type, c.is_active DESC, c.sort_order, c.name')->fetchAll();
$editing = null;
foreach ($rows as $row) if ($edit_id && (int) $row['id'] === $edit_id) $editing = $row;
$name_value = $_SERVER['REQUEST_METHOD'] === 'POST' ? $name_input : (string) ($editing['name'] ?? '');
$page_title = 'Finance Categories'; $active_page = 'finance';
$page_styles = ['assets/css/finance.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <a class="announcement-back" href="finance.php"><span aria-hidden="true">&larr;</span> Back to Financial Management</a>
    <div class="page-heading"><div><h1>Categories</h1><p>Income and Expense categories. Categories can be renamed or deactivated; they are never deleted.</p></div></div>
    <?php if ($success = flash('finance_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('finance_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php endif; ?>
    <section class="dashboard-panel announcements-page">
        <form class="case-inline-add case-admin-form fin-category-form" method="post" action="finance_categories.php">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><?php if ($editing): ?><input type="hidden" name="edit_id" value="<?= e((string) $editing['id']) ?>"><?php endif; ?>
            <div><label class="form-label" for="category-name"><?= $editing ? 'Rename category' : 'New category' ?></label><input class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="category-name" name="name" value="<?= e($name_value) ?>" maxlength="80" required><?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?></div>
            <?php if (!$editing): ?><div><label class="form-label" for="category-type">Type</label><select class="form-select<?= isset($errors['type']) ? ' is-invalid' : '' ?>" id="category-type" name="type"><?php foreach (finance_types() as $value => $label): ?><option value="<?= e($value) ?>" <?= $type_input === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <div class="doc-action-group"><button class="btn doc-action-btn is-primary" type="submit"><?= $editing ? 'Save Name' : 'Add Category' ?></button><?php if ($editing): ?><a class="btn doc-action-btn is-view" href="finance_categories.php">Cancel</a><?php endif; ?></div>
        </form>
        <?php foreach (finance_types() as $type => $type_label): ?>
            <h2 class="fin-section-title"><?= e($type_label) ?></h2>
            <ul class="case-link-list fin-category-list">
                <?php foreach (array_filter($rows, static fn (array $r): bool => $r['type'] === $type) as $row): $active = (int) $row['is_active'] === 1; ?>
                    <li>
                        <div><strong><?= e($row['name']) ?></strong> <?= $active ? '<span class="resident-status resident-status-active">Active</span>' : '<span class="resident-status resident-status-inactive">Inactive</span>' ?><?= (int) $row['is_allotment'] === 1 ? ' <span class="activity-detail-muted">· NTA numbers</span>' : '' ?><br><span class="activity-detail-muted"><?= e((string) $row['transactions']) ?> transaction<?= (int) $row['transactions'] === 1 ? '' : 's' ?></span></div>
                        <div class="doc-action-group">
                            <a class="btn doc-action-btn is-view" href="finance_categories.php?edit=<?= e((string) $row['id']) ?>">Rename</a>
                            <?php if (!((int) $row['is_allotment'] === 1 && $active)): ?><form method="post" action="finance_categories.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="edit_id" value="<?= e((string) $row['id']) ?>"><button class="btn doc-action-btn <?= $active ? 'is-danger' : 'is-primary' ?>" type="submit" data-form-confirm="custom" data-dialog-heading="<?= $active ? 'Deactivate ' . e($row['name']) . '?' : 'Activate ' . e($row['name']) . '?' ?>" data-dialog-message="<?= $active ? 'It will no longer be offered for new transactions. Existing transactions keep it.' : 'It will be offered again for new transactions.' ?>" data-dialog-confirm="<?= $active ? 'Deactivate' : 'Activate' ?>" data-dialog-dismiss="Cancel"<?= $active ? ' data-dialog-danger="true"' : '' ?>><?= $active ? 'Deactivate' : 'Activate' ?></button></form><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
