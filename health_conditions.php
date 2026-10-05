<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health.php';
health_require_manage();
$connection = db();
if (!health_ready($connection) || !health_phase2_ready($connection)) { flash('health_error', 'The diagnosis list needs its database table first.'); redirect('health.php'); }

// Diagnosis list (health_conditions): the illnesses Health Workers choose on a record, counted in the Morbidity Report.
// Entries are never deleted (records point to them); an entry no longer used is set to Inactive and leaves the form.
$errors = [];
$name = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please try again.';
    } elseif ($action === 'add') {
        $name = residents_collapse($_POST['name'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors['name'] = 'Enter the diagnosis name (2 to 120 characters).';
        elseif (!preg_match("/^[\p{L}\p{N}][\p{L}\p{M}\p{N}\s.,'()\/&+\-]*$/u", $name)) $errors['name'] = 'Use letters, numbers and simple punctuation only.';
        else {
            $existing = $connection->prepare('SELECT name, is_active FROM health_conditions WHERE LOWER(name) = LOWER(:name) LIMIT 1');
            $existing->execute(['name' => $name]);
            $found = $existing->fetch();
            if ($found && (int) $found['is_active'] === 1) $errors['name'] = '"' . $found['name'] . '" is already in the list.';
            else {
                try {
                    $connection->beginTransaction();
                    health_condition_find_or_add($connection, $name);
                    $connection->commit();
                    flash('health_success', $found ? '"' . $found['name'] . '" is active again.' : '"' . $name . '" was added to the diagnosis list.');
                    redirect('health_conditions.php');
                } catch (PDOException $exception) {
                    if ($connection->inTransaction()) $connection->rollBack();
                    $errors['name'] = 'The diagnosis could not be added. Please try again.';
                }
            }
        }
    } elseif ($action === 'toggle') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $row = null;
        if ($id) { $find = $connection->prepare('SELECT id, name, is_active FROM health_conditions WHERE id = :id'); $find->execute(['id' => $id]); $row = $find->fetch() ?: null; }
        if ($row === null) $errors['form'] = 'That diagnosis was not found.';
        else {
            $active = (int) $row['is_active'] === 1 ? 0 : 1;
            $connection->prepare('UPDATE health_conditions SET is_active = :active WHERE id = :id')->execute(['active' => $active, 'id' => $row['id']]);
            health_audit($connection, 0, $active ? 'health_condition_activated' : 'health_condition_deactivated', ['condition' => $row['name']]);
            flash('health_success', '"' . $row['name'] . '" is now ' . ($active ? 'active and can be chosen on records.' : 'inactive. Records that already use it keep it.'));
            redirect('health_conditions.php');
        }
    }
}

// Each entry with the number of (not archived) records using it.
$conditions = $connection->query('SELECT c.id, c.name, c.is_active, c.created_at, u.name AS created_by_name, (SELECT COUNT(*) FROM health_records h WHERE h.condition_id = c.id AND h.archived_at IS NULL) AS used FROM health_conditions c LEFT JOIN users u ON u.id = c.created_by ORDER BY c.is_active DESC, c.name')->fetchAll();
$page_title = 'Diagnosis List'; $active_page = 'health';
$page_styles = ['assets/css/health.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <?php if ($success = flash('health_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if (isset($errors['form'])): ?><div class="dashboard-status warning" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
    <section class="dashboard-panel resident-list-panel">
        <a class="announcement-back" href="health.php"><span aria-hidden="true">&larr;</span> Back to Health</a>
        <div class="page-heading"><div><h1>Diagnosis List</h1><p>The illnesses chosen on health records and counted in the Morbidity Report. Entries are not deleted; set one to Inactive to remove it from the form.</p></div></div>
        <form method="post" class="health-condition-add">
            <?= csrf_field() ?><input type="hidden" name="action" value="add">
            <label class="form-label" for="condition-name">New diagnosis</label>
            <div class="health-condition-row"><input class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="condition-name" name="name" maxlength="120" value="<?= e($name) ?>" placeholder="e.g. Hypertension" required autocomplete="off"><button class="btn btn-primary" type="submit">Add</button></div>
            <?php if (isset($errors['name'])): ?><div class="invalid-feedback d-block"><?= e($errors['name']) ?></div><?php endif; ?>
        </form>
        <?php if ($conditions === []): ?>
            <div class="dashboard-empty-state">The list is empty. Add the illnesses your health station records.</div>
        <?php else: ?>
            <div class="resident-table-wrap health-keep-table">
                <table class="resident-table">
                    <thead><tr><th scope="col">Diagnosis</th><th scope="col" class="num">Records</th><th scope="col">Status</th><th scope="col">Added</th><th scope="col" class="health-actions-head">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($conditions as $row): ?>
                        <tr>
                            <td class="resident-name"><?= e($row['name']) ?></td>
                            <td class="num"><?= e(number_format((int) $row['used'])) ?></td>
                            <td><span class="resident-status resident-status-<?= (int) $row['is_active'] === 1 ? 'active' : 'inactive' ?>"><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                            <td><?= e(health_format_date($row['created_at'])) ?><?= $row['created_by_name'] ? '<span class="health-sub">' . e($row['created_by_name']) . '</span>' : '' ?></td>
                            <td><div class="management-actions health-actions"><form method="post" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= e((string) $row['id']) ?>"><button class="btn btn-sm <?= (int) $row['is_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-primary' ?>" type="submit" data-form-confirm="custom" data-dialog-heading="<?= (int) $row['is_active'] === 1 ? 'Set this diagnosis to Inactive?' : 'Make this diagnosis active?' ?>" data-dialog-message="<?= e((int) $row['is_active'] === 1 ? '"' . $row['name'] . '" will no longer be offered on the form. Records that already use it keep it.' : '"' . $row['name'] . '" will be offered on the form again.') ?>" data-dialog-confirm="<?= (int) $row['is_active'] === 1 ? 'Set Inactive' : 'Activate' ?>" data-dialog-dismiss="Cancel"<?= (int) $row['is_active'] === 1 ? ' data-dialog-danger="true"' : '' ?>><?= (int) $row['is_active'] === 1 ? 'Set Inactive' : 'Activate' ?></button></form></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
