<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster.php';
disaster_require_manage();
$connection = db();
if (!disaster_ready($connection)) { flash('disaster_error', 'Disaster Management needs its database tables first.'); redirect('disaster.php'); }

// Areas used by DRR records. "All Puroks" and Purok 1–4 are fixed (they match the Puroks on resident profiles); places
// can be added, renamed or deactivated, never deleted, so existing records always keep their area.
$edit_id = filter_var($_GET['edit'] ?? $_POST['edit_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$errors = [];
$form_error = null;
$name_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('disaster_error', 'Your session expired. Please try again.'); redirect('disaster_areas.php'); }
    $target = $edit_id ? disaster_area($connection, $edit_id) : null;
    try {
        if ($edit_id && ($target === null || $target['area_type'] !== 'place')) throw new RuntimeException('Only places can be renamed or deactivated. "All Puroks" and the Puroks are fixed.');
        if ($action === 'save') {
            $name_input = residents_collapse((string) ($_POST['name'] ?? ''));
            if (mb_strlen($name_input) < 2 || mb_strlen($name_input) > 120 || !preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\s&.,\'()\/\-]*$/u', $name_input)) $errors['name'] = 'Enter a place name of 2 to 120 characters.';
            if ($errors === []) {
                if ($target) {
                    $connection->prepare('UPDATE drr_areas SET name = :name WHERE id = :id')->execute(['name' => $name_input, 'id' => $edit_id]);
                    residents_audit($connection, 'disaster_area', $edit_id, 'disaster_area_updated', ['name' => $name_input, 'previous_name' => $target['name']]);
                    flash('disaster_success', 'Area renamed. Records using it show the new name.');
                } else {
                    $connection->prepare("INSERT INTO drr_areas (name, area_type, created_by) VALUES (:name, 'place', :user)")->execute(['name' => $name_input, 'user' => current_user()['id']]);
                    residents_audit($connection, 'disaster_area', (int) $connection->lastInsertId(), 'disaster_area_created', ['name' => $name_input]);
                    flash('disaster_success', 'Area added.');
                }
                redirect('disaster_areas.php');
            }
        } elseif ($action === 'toggle' && $target) {
            $connection->prepare('UPDATE drr_areas SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $edit_id]);
            residents_audit($connection, 'disaster_area', $edit_id, 'disaster_area_status_changed', ['name' => $target['name'], 'active' => (int) $target['is_active'] === 1 ? 'no' : 'yes']);
            flash('disaster_success', 'Area status updated. Records that already use it keep it.');
            redirect('disaster_areas.php');
        }
    } catch (PDOException $exception) {
        $form_error = ($exception->errorInfo[1] ?? 0) === 1062 ? 'An area with this name already exists.' : 'The change could not be saved. No changes were made.';
    } catch (RuntimeException $exception) {
        $form_error = $exception->getMessage();
    }
}

$rows = $connection->query('SELECT a.id, a.name, a.area_type, a.is_active, (SELECT COUNT(*) FROM drr_records d WHERE d.area_id = a.id AND d.archived_at IS NULL) AS records FROM drr_areas a ORDER BY a.is_active DESC, a.sort_order, a.name')->fetchAll();
$editing = null;
foreach ($rows as $row) if ($edit_id && (int) $row['id'] === $edit_id && $row['area_type'] === 'place') $editing = $row;
$name_value = $_SERVER['REQUEST_METHOD'] === 'POST' ? $name_input : (string) ($editing['name'] ?? '');
$page_title = 'DRR Areas'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <a class="announcement-back" href="disaster.php"><span aria-hidden="true">&larr;</span> Back to Disaster Management</a>
    <div class="page-heading"><div><h1>Areas</h1><p>Areas used by DRR records. "All Puroks" and the Puroks are fixed; places can be added, renamed or deactivated. Nothing is deleted.</p></div></div>
    <?php if ($success = flash('disaster_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('disaster_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php endif; ?>
    <section class="dashboard-panel announcements-page">
        <form class="case-inline-add case-admin-form" method="post" action="disaster_areas.php">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><?php if ($editing): ?><input type="hidden" name="edit_id" value="<?= e((string) $editing['id']) ?>"><?php endif; ?>
            <div><label class="form-label" for="area-name"><?= $editing ? 'Rename place' : 'New place' ?></label><input class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="area-name" name="name" value="<?= e($name_value) ?>" maxlength="120" required placeholder="e.g. Riverside, Covered Court"><?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?></div>
            <div class="doc-action-group"><button class="btn doc-action-btn is-primary" type="submit"><?= $editing ? 'Save Name' : 'Add Place' ?></button><?php if ($editing): ?><a class="btn doc-action-btn is-view" href="disaster_areas.php">Cancel</a><?php endif; ?></div>
        </form>
        <ul class="case-link-list drr-area-list">
            <?php foreach ($rows as $row): $active = (int) $row['is_active'] === 1; $place = $row['area_type'] === 'place'; ?>
                <li>
                    <div><strong><?= e($row['name']) ?></strong> <?= $active ? '<span class="resident-status resident-status-active">Active</span>' : '<span class="resident-status resident-status-inactive">Inactive</span>' ?><br><span class="activity-detail-muted"><?= e(disaster_area_types()[$row['area_type']] ?? '') ?> · <?= e((string) $row['records']) ?> record<?= (int) $row['records'] === 1 ? '' : 's' ?></span></div>
                    <?php if ($place): ?>
                        <div class="doc-action-group">
                            <a class="btn doc-action-btn is-view" href="disaster_areas.php?edit=<?= e((string) $row['id']) ?>">Rename</a>
                            <form method="post" action="disaster_areas.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="edit_id" value="<?= e((string) $row['id']) ?>"><button class="btn doc-action-btn <?= $active ? 'is-danger' : 'is-primary' ?>" type="submit" data-form-confirm="custom" data-dialog-heading="<?= $active ? 'Deactivate ' . e($row['name']) . '?' : 'Activate ' . e($row['name']) . '?' ?>" data-dialog-message="<?= $active ? 'It will no longer be offered for new records. Records that already use it keep it.' : 'It will be offered again when recording.' ?>" data-dialog-confirm="<?= $active ? 'Deactivate' : 'Activate' ?>" data-dialog-dismiss="Cancel"<?= $active ? ' data-dialog-danger="true"' : '' ?>><?= $active ? 'Deactivate' : 'Activate' ?></button></form>
                        </div>
                    <?php else: ?>
                        <span class="activity-detail-muted drr-fixed">Fixed</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
