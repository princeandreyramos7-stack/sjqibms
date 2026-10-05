<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_prep.php';
disaster_require_manage();
$connection = db();
if (!disaster_prep_ready($connection)) { flash('disaster_error', 'Members and hotlines need their database table first.'); redirect('disaster_contacts.php'); }

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$record = $id ? disaster_prep_find($connection, 'contact', $id) : null;
if ($id && !$record) { http_response_code(404); exit('Contact not found.'); }
if ($record && $record['archived_at'] !== null) { flash('disaster_error', 'Restore this contact before editing it.'); redirect('disaster_contacts.php?archived=1'); }
$is_edit = $record !== null;
$fields = disaster_contact_fields();
$values = $record ? array_intersect_key($record, array_flip($fields)) : ['contact_type' => ($_GET['type'] ?? '') === 'hotline' ? 'hotline' : 'member', 'name' => '', 'member_role' => '', 'hotline_category' => '', 'position' => '', 'contact_number' => '', 'alternate_number' => '', 'notes' => ''];
$errors = [];
$form_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = disaster_contact_validate($_POST);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($is_edit && (string) ($_POST['form_updated_at'] ?? '') !== (string) $record['updated_at']) $form_error = 'This record was changed by another action after you opened it. Reload the page to review the latest information.';
    if ($form_error === null && $errors === []) {
        try {
            [, $message] = disaster_prep_save($connection, 'contact', $fields, $values, $record, $values['name']);
            flash('disaster_success', $message);
            redirect('disaster_contacts.php');
        } catch (PDOException) {
            $form_error = 'The contact could not be saved. No changes were made.';
        } catch (RuntimeException $exception) {
            $form_error = $exception->getMessage();
        }
    }
}

$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => (string) ($values[$field] ?? '');
$page_title = $is_edit ? 'Edit Contact' : 'Add Contact'; $active_page = 'disaster';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="disaster_contacts.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p>A BDRRMC member with a committee role, or an emergency hotline.</p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" data-drr-contact-form>
        <?= csrf_field() ?>
        <?php if ($is_edit): ?><input type="hidden" name="form_updated_at" value="<?= e($record['updated_at']) ?>"><?php endif; ?>
        <fieldset class="resident-section">
            <legend>Contact</legend>
            <div class="resident-grid">
                <div><label class="form-label" for="contact_type">Type <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('contact_type') ?>" id="contact_type" name="contact_type" required data-drr-contact-type data-summary-label="Type"><option value="member" <?= $val('contact_type') === 'member' ? 'selected' : '' ?>>BDRRMC Member</option><option value="hotline" <?= $val('contact_type') === 'hotline' ? 'selected' : '' ?>>Emergency Hotline</option></select><?= $field_error('contact_type') ?></div>
                <div><label class="form-label" for="name"><span data-drr-member-only>Member's name</span><span data-drr-hotline-only>Office / hotline name</span> <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('name') ?>" id="name" name="name" maxlength="150" value="<?= e($val('name')) ?>" required data-summary-label="Name"><?= $field_error('name') ?></div>
                <div data-drr-member-only><label class="form-label" for="member_role">Role <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('member_role') ?>" id="member_role" name="member_role" data-summary-label="Role"><option value="">Select role</option><?php foreach (disaster_member_roles() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('member_role') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('member_role') ?></div>
                <div data-drr-member-only><label class="form-label" for="position">Position</label><input class="form-control<?= $field_class('position') ?>" id="position" name="position" maxlength="100" value="<?= e($val('position')) ?>" placeholder="e.g. Team Leader, Kagawad" data-summary-label="Position"><?= $field_error('position') ?></div>
                <div data-drr-hotline-only><label class="form-label" for="hotline_category">Category <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('hotline_category') ?>" id="hotline_category" name="hotline_category" data-summary-label="Category"><option value="">Select category</option><?php foreach (disaster_hotline_categories() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('hotline_category') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('hotline_category') ?></div>
                <div><label class="form-label" for="contact_number">Contact number <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('contact_number') ?>" type="tel" id="contact_number" name="contact_number" maxlength="30" value="<?= e($val('contact_number')) ?>" required placeholder="e.g. 0917 123 4567 or 911" data-summary-label="Contact number"><?= $field_error('contact_number') ?></div>
                <div><label class="form-label" for="alternate_number">Alternate number</label><input class="form-control<?= $field_class('alternate_number') ?>" type="tel" id="alternate_number" name="alternate_number" maxlength="30" value="<?= e($val('alternate_number')) ?>" data-summary-label="Alternate number"><?= $field_error('alternate_number') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="notes">Notes</label><input class="form-control<?= $field_class('notes') ?>" id="notes" name="notes" maxlength="255" value="<?= e($val('notes')) ?>" placeholder="e.g. Available 24/7, radio channel"><?= $field_error('notes') ?></div>
            </div>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $is_edit ? 'Save changes to this contact?' : 'Add this contact?' ?>" data-dialog-message="The change will be recorded in the Audit Logs." data-dialog-confirm="<?= $is_edit ? 'Save Changes' : 'Add Contact' ?>" data-dialog-dismiss="Cancel"><?= $is_edit ? 'Save Changes' : 'Add Contact' ?></button>
            <a class="btn btn-light" href="disaster_contacts.php" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/disaster.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/disaster.js')) ?>"></script>
