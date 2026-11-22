<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/projects.php';
projects_require_manage();
$connection = db();
if (!projects_ready($connection)) { flash('project_error', 'Projects needs its database table before projects can be recorded.'); redirect('projects.php'); }
$details = projects_details_ready($connection);

// Add or edit a project. The code (PRJ-YYYY-NNN) is assigned when the project is added and never changes. Choosing
// Completed records the completion date (today unless another past date is entered); other statuses clear it.
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$record = $id ? projects_find($connection, $id) : null;
if ($id && !$record) { http_response_code(404); exit('Project not found.'); }
$is_edit = $record !== null;
$fields = array_merge(['title', 'description', 'status', 'start_date', 'target_end_date', 'completed_at'], $details ? ['category', 'budget'] : []);
$values = $record ? array_intersect_key($record, array_flip($fields)) : ['status' => 'planned'] + array_fill_keys($fields, null);
$errors = [];
$form_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = projects_validate($_POST, $details);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($is_edit && (string) ($_POST['form_updated_at'] ?? '') !== (string) $record['updated_at']) $form_error = 'This project was changed by another action after you opened it. Reload the page to review the latest information.';
    if ($form_error === null && $errors === []) {
        $params = array_intersect_key($values, array_flip($fields));
        $set = implode(', ', array_map(static fn (string $field): string => "$field = :$field", $fields));
        try {
            $connection->beginTransaction();
            if ($is_edit) {
                $locked = projects_find($connection, $id, true);
                if (!$locked || $locked['updated_at'] !== $record['updated_at']) throw new RuntimeException('This project was changed by another action after you opened it. Reload the page to review the latest information.');
                $changed = array_values(array_filter($fields, static fn (string $field): bool => (string) $values[$field] !== (string) $locked[$field]));
                if ($changed !== []) {
                    $connection->prepare("UPDATE barangay_projects SET $set, updated_by = :user WHERE id = :id")->execute($params + ['user' => current_user()['id'], 'id' => $id]);
                    projects_audit($connection, $id, 'project_updated', ['code' => $locked['project_code'], 'changed_fields' => $changed] + ($locked['status'] !== $values['status'] ? ['status' => $locked['status'] . ' → ' . $values['status']] : []));
                }
                $message = $changed === [] ? 'No changes were made to this project.' : 'Project ' . $locked['project_code'] . ' updated.';
            } else {
                $code = projects_next_code($connection);
                $columns = implode(', ', $fields);
                $placeholders = implode(', ', array_map(static fn (string $field): string => ":$field", $fields));
                $connection->prepare("INSERT INTO barangay_projects (project_code, $columns, created_by, updated_by) VALUES (:code, $placeholders, :created_by, :updated_by)")->execute($params + ['code' => $code, 'created_by' => current_user()['id'], 'updated_by' => current_user()['id']]);
                projects_audit($connection, (int) $connection->lastInsertId(), 'project_created', ['code' => $code, 'title' => $values['title'], 'status' => $values['status']]);
                $message = 'Project ' . $code . ' added.';
            }
            $connection->commit();
            flash('project_success', $message);
            redirect('projects.php');
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = ($exception->errorInfo[1] ?? 0) === 1062 ? 'Another project was added at the same moment. Please submit again.' : 'The project could not be saved. No changes were made.';
        }
    }
}

$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
$page_title = $is_edit ? 'Edit Project' : 'Add Project'; $active_page = 'projects';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="projects.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p><?= $is_edit ? 'Update ' . e($record['project_code']) . ' · ' . e($record['title']) . '.' : 'Record a barangay development project or program.' ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <?php if ($is_edit): ?><input type="hidden" name="form_updated_at" value="<?= e($record['updated_at']) ?>"><?php endif; ?>

        <fieldset class="resident-section">
            <legend>Project Information</legend>
            <div class="resident-grid">
                <div class="resident-grid-full"><label class="form-label" for="title">Project name <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('title') ?>" id="title" name="title" maxlength="200" value="<?= e($val('title')) ?>" placeholder="e.g. Purok 3 drainage improvement" required data-summary-label="Project"><?= $field_error('title') ?></div>
                <?php if ($details): ?>
                    <div><label class="form-label" for="category">Category</label><input class="form-control<?= $field_class('category') ?>" id="category" name="category" maxlength="80" value="<?= e($val('category')) ?>" list="project-categories" placeholder="e.g. Infrastructure" data-summary-label="Category"><datalist id="project-categories"><?php foreach (projects_categories() as $category): ?><option value="<?= e($category) ?>"></option><?php endforeach; ?></datalist><?= $field_error('category') ?></div>
                    <div><label class="form-label" for="budget">Budget (₱)</label><input class="form-control<?= $field_class('budget') ?>" id="budget" name="budget" maxlength="17" inputmode="decimal" value="<?= e($val('budget')) ?>" placeholder="e.g. 150000" data-summary-label="Budget"><div class="form-text">Leave empty if the budget is not yet set.</div><?= $field_error('budget') ?></div>
                <?php endif; ?>
                <div class="resident-grid-full"><label class="form-label" for="description">Description <span class="activity-detail-muted">(optional)</span></label><textarea class="form-control<?= $field_class('description') ?>" id="description" name="description" rows="3" maxlength="2000" placeholder="Location, beneficiaries, funding source, or other details"><?= e($val('description')) ?></textarea><?= $field_error('description') ?></div>
            </div>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Schedule and Status</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="start_date">Start date</label><input class="form-control<?= $field_class('start_date') ?>" type="date" id="start_date" name="start_date" value="<?= e($val('start_date')) ?>" data-summary-label="Start"><?= $field_error('start_date') ?></div>
                <div><label class="form-label" for="target_end_date">Target end date</label><input class="form-control<?= $field_class('target_end_date') ?>" type="date" id="target_end_date" name="target_end_date" value="<?= e($val('target_end_date')) ?>" data-summary-label="Target end"><?= $field_error('target_end_date') ?></div>
                <div><label class="form-label" for="status">Status <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('status') ?>" id="status" name="status" required data-summary-label="Status" data-project-status><?php foreach (projects_status_labels() as $value => $label): ?><option value="<?= e($value) ?>" <?= ($val('status') ?: 'planned') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><div class="form-text">Projects are never deleted: use Suspended or Cancelled for a project that stopped.</div><?= $field_error('status') ?></div>
                <div data-completed-field <?= $val('status') === 'completed' ? '' : 'hidden' ?>><label class="form-label" for="completed_at">Completion date</label><input class="form-control<?= $field_class('completed_at') ?>" type="date" id="completed_at" name="completed_at" max="<?= e(date('Y-m-d')) ?>" value="<?= e($val('completed_at')) ?>" data-summary-label="Completed"><div class="form-text">Leave empty to use today's date.</div><?= $field_error('completed_at') ?></div>
            </div>
        </fieldset>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $is_edit ? 'Save changes to this project?' : 'Add this project?' ?>" data-dialog-message="Review the details before saving." data-dialog-confirm="<?= $is_edit ? 'Save Changes' : 'Add Project' ?>" data-dialog-dismiss="Cancel"><?= $is_edit ? 'Save Changes' : 'Add Project' ?></button>
            <a class="btn btn-light" href="projects.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
<script>
// The completion date is asked only when the status is Completed.
document.querySelector('[data-project-status]').addEventListener('change', (event) => {
    document.querySelector('[data-completed-field]').hidden = event.target.value !== 'completed';
});
</script>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
