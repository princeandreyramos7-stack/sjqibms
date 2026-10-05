<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/residents.php';
residents_require_manage();
$connection = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$resident = $id ? residents_find($connection, $id) : null;
if (!$resident) { http_response_code(404); exit('Resident not found.'); }
$departed = ['moved', 'deceased'];
$input = ['new_status' => '', 'reason' => '', 'effective_date' => date('Y-m-d'), 'reverified' => '', 'verification_details' => ''];
$errors = [];
$form_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = ['new_status' => (string) ($_POST['new_status'] ?? ''), 'reason' => trim((string) ($_POST['reason'] ?? '')), 'effective_date' => trim((string) ($_POST['effective_date'] ?? '')), 'reverified' => (string) ($_POST['reverified'] ?? ''), 'verification_details' => trim((string) ($_POST['verification_details'] ?? ''))];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session token expired. Please review the form and submit again.';
    try {
        $connection->beginTransaction();
        // Lock and re-read: the transition is validated against the stored status, never the status shown when the form was opened.
        $locked = residents_find($connection, $id, true);
        if (!$locked || $locked['status'] !== (string) ($_POST['form_status'] ?? '')) throw new RuntimeException('This resident\'s status was changed by another action after you opened the form. Review the current status and try again.');
        $from = $locked['status'];
        $to = $input['new_status'];
        if (!array_key_exists($to, residents_status_labels()) || $to === $from) $errors['new_status'] = 'Select a new status different from the current status.';
        if (mb_strlen($input['reason']) < 10 || mb_strlen($input['reason']) > 500) $errors['reason'] = 'Enter a reason of 10 to 500 characters.';
        $effective = null;
        if (in_array($to, $departed, true)) {
            $effective = residents_valid_date($input['effective_date']);
            if ($effective === null) $errors['effective_date'] = 'Enter a valid effective date.';
            elseif ($effective > new DateTimeImmutable('today')) $errors['effective_date'] = 'The effective date cannot be in the future.';
            elseif ($locked['birth_date'] !== null && $input['effective_date'] < $locked['birth_date']) $errors['effective_date'] = 'The effective date cannot be earlier than the birthdate.';
        }
        // Reactivating a Moved or Deceased profile requires explicit identity and residency re-verification.
        $reactivation = in_array($from, $departed, true) && !in_array($to, $departed, true);
        if ($reactivation) {
            if ($input['reverified'] !== '1') $errors['reverified'] = 'Confirm that identity and residency were re-verified.';
            if (mb_strlen($input['verification_details']) < 10 || mb_strlen($input['verification_details']) > 500) $errors['verification_details'] = 'Describe the re-verification (10 to 500 characters).';
        }
        if ($form_error !== null || $errors !== []) throw new RuntimeException('');

        $update = $connection->prepare('UPDATE residents SET status = :to WHERE id = :id AND status = :from');
        $update->execute(['to' => $to, 'id' => $id, 'from' => $from]);
        if ($update->rowCount() !== 1) throw new RuntimeException('The resident status could not be changed. No changes were saved.');
        $details = ['from' => $from, 'to' => $to, 'reason' => $input['reason']];
        if ($effective !== null) {
            // Moved/Deceased: close the current primary membership as history and release any Household Head designation. No replacement head is chosen.
            $details['effective_date'] = $input['effective_date'];
            $details += residents_close_membership($connection, $id, $input['effective_date']);
        }
        if ($reactivation) $details['reverification'] = $input['verification_details'];
        residents_audit($connection, 'resident', $id, 'resident_status_changed', $details);
        $connection->commit();
        $message = 'Resident status changed to ' . residents_status_labels()[$to] . '.';
        if (!empty($details['closed_household_id'])) $message .= ' The current household membership was closed.';
        if (!empty($details['cleared_head_household_ids'])) $message .= ' The Household Head designation was cleared; assign a new head when ready.';
        if ($reactivation) $message .= ' Household assignment must be completed separately.';
        flash('resident_success', $message);
        redirect('resident_view.php?id=' . $id);
    } catch (RuntimeException $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        if ($exception->getMessage() !== '') $form_error = $exception->getMessage();
    } catch (PDOException) {
        if ($connection->inTransaction()) $connection->rollBack();
        $form_error = 'The resident status could not be changed. No changes were saved.';
    }
    $resident = residents_find($connection, $id) ?? $resident;
}
$is_departed = in_array($resident['status'], $departed, true);
$membership = residents_current_membership($connection, $id);
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$page_title = 'Change Resident Status'; $active_page = 'residents';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="resident_view.php?id=<?= e((string) $id) ?>"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><span class="eyebrow">Resident #<?= e((string) $id) ?></span><h1>Change Resident Status</h1><p><?= e(residents_full_name($resident)) ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" data-status-form>
        <?= csrf_field() ?>
        <input type="hidden" name="form_status" value="<?= e($resident['status']) ?>">
        <fieldset class="resident-section">
            <legend>Status Change</legend>
            <div class="resident-grid">
                <div><span class="form-label d-block">Current status</span><p class="resident-static"><?= residents_status_badge($resident['status']) ?></p></div>
                <div><label class="form-label" for="new_status">New status</label><select class="form-select<?= $field_class('new_status') ?>" id="new_status" name="new_status" required data-status-select><option value="">Select new status</option><?php foreach (residents_status_labels() as $value => $label): if ($value === $resident['status']) continue; ?><option value="<?= e($value) ?>" <?= $input['new_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('new_status') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="reason">Reason</label><textarea class="form-control<?= $field_class('reason') ?>" id="reason" name="reason" rows="3" minlength="10" maxlength="500" required><?= e($input['reason']) ?></textarea><?= $field_error('reason') ?></div>
            </div>
        </fieldset>

        <fieldset class="resident-section" data-status-panel="moved deceased">
            <legend>Household Handling</legend>
            <p class="resident-static"><?= $membership !== null ? 'The current membership in household <strong>' . e($membership['household_no']) . '</strong> will be closed on the effective date and kept as history.' : 'This resident has no current household membership to close.' ?><?= $membership !== null && (int) $membership['household_head_resident_id'] === $id ? ' This resident is the Household Head; the designation will be cleared and no replacement is assigned automatically.' : '' ?></p>
            <div class="resident-grid"><div><label class="form-label" for="effective_date">Effective date</label><input class="form-control<?= $field_class('effective_date') ?>" type="date" id="effective_date" name="effective_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($input['effective_date']) ?>" required><?= $field_error('effective_date') ?></div></div>
        </fieldset>

        <?php if ($is_departed): ?>
        <fieldset class="resident-section" data-status-panel="active pending inactive">
            <legend>Re-verification</legend>
            <p class="resident-static">Reactivating a <?= e(strtolower(residents_status_labels()[$resident['status']])) ?> profile requires identity and residency re-verification. Previous household memberships and Household Head designations are <strong>not</strong> restored.</p>
            <div class="form-check mb-3"><input class="form-check-input<?= $field_class('reverified') ?>" type="checkbox" id="reverified" name="reverified" value="1" <?= $input['reverified'] === '1' ? 'checked' : '' ?> required><label class="form-check-label" for="reverified">I re-verified this person's identity and current residency in Barangay San Jose.</label><?= $field_error('reverified') ?></div>
            <label class="form-label" for="verification_details">Re-verification details</label><textarea class="form-control<?= $field_class('verification_details') ?>" id="verification_details" name="verification_details" rows="2" minlength="10" maxlength="500" required placeholder="For example: presented barangay ID and proof of residence dated this month."><?= e($input['verification_details']) ?></textarea><?= $field_error('verification_details') ?>
        </fieldset>
        <?php endif; ?>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Change resident status?" data-dialog-message="Are you sure you want to change this resident's status? The change and your reason will be recorded in the audit log. Linked user accounts are not changed." data-dialog-confirm="Change Status">Change Status</button>
            <a class="btn btn-light" href="resident_view.php?id=<?= e((string) $id) ?>">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
