<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/residents.php';
residents_require_manage();
$connection = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$resident = $id ? residents_find($connection, $id) : null;
if (!$resident) { http_response_code(404); exit('Resident not found.'); }
$assignable_statuses = ['active', 'pending'];
// Household Management links here with ?household_id= to pre-select a destination; the choice is still validated and confirmed as usual.
$preselected_household = filter_var($_GET['household_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$household = ['mode' => 'existing', 'household_id' => $preselected_household, 'relationship' => null, 'new' => ['household_no' => '', 'address' => '', 'purok' => '']];
$errors = [];
$form_error = null;
$action = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session token expired. Please try again.';
    elseif (!in_array($action, ['assign', 'transfer', 'remove', 'relationship', 'head'], true)) $form_error = 'Invalid household action.';
    if (in_array($action, ['assign', 'transfer'], true)) {
        $check = residents_validate_household($connection, $_POST, false);
        $household = $check['values'];
        $errors = $check['errors'];
    }
    if ($form_error === null && $errors === []) {
        try {
            $connection->beginTransaction();
            $locked = residents_find($connection, $id, true);
            $current = residents_current_membership($connection, $id, true);
            // The membership the form was built from must still be the current one.
            if ((string) ($current['household_id'] ?? '') !== (string) ($_POST['form_household_id'] ?? '')) throw new RuntimeException('This resident\'s household assignment was changed by another action. Review the current assignment and try again.');
            $today = date('Y-m-d');
            if ($action === 'assign' || $action === 'transfer') {
                if (!in_array($locked['status'], $assignable_statuses, true)) throw new RuntimeException('Only Active or Pending residents can be assigned to a household. Change the resident status first.');
                if ($action === 'assign' && $current !== null) throw new RuntimeException('This resident already has a current household. Use Transfer instead.');
                if ($action === 'transfer' && $current === null) throw new RuntimeException('This resident has no current household to transfer from.');
                if ($action === 'transfer' && $household['mode'] === 'existing' && (int) $household['household_id'] === (int) $current['household_id']) throw new RuntimeException('Select a different household for the transfer.');
                $closed = $action === 'transfer' ? residents_close_membership($connection, $id, $today) : [];
                $household_id = residents_open_membership($connection, $id, $household);
                residents_audit($connection, 'resident', $id, $action === 'assign' ? 'resident_household_assigned' : 'resident_household_transferred', ['household_id' => $household_id, 'relationship_to_head' => $household['relationship']] + ($closed === [] ? [] : ['previous_household_id' => $closed['closed_household_id'], 'cleared_head_household_ids' => $closed['cleared_head_household_ids']]));
                $message = $action === 'assign' ? 'Household assigned successfully.' : 'Resident transferred to the new household. The previous membership was kept as history.';
            } elseif ($action === 'remove') {
                if ($current === null) throw new RuntimeException('This resident has no current household.');
                $closed = residents_close_membership($connection, $id, $today);
                residents_audit($connection, 'resident', $id, 'resident_household_removed', $closed);
                $message = 'Resident removed from the household. The membership was kept as history; household assignment is now pending.';
            } elseif ($action === 'relationship') {
                if ($current === null) throw new RuntimeException('This resident has no current household.');
                // households.household_head_resident_id is authoritative: the designated head's relationship is always "Household Head",
                // so it cannot be edited, and a form opened before a head change is rejected.
                $head_lock = $connection->prepare('SELECT household_head_resident_id FROM households WHERE id = :id FOR UPDATE');
                $head_lock->execute(['id' => $current['household_id']]);
                $designated_head = $head_lock->fetchColumn();
                $designated_head = $designated_head === null || $designated_head === false ? null : (int) $designated_head;
                if ((string) $designated_head !== (string) ($_POST['form_head_id'] ?? '')) throw new RuntimeException('The Household Head was changed by another action. Review the current household and try again.');
                if ($designated_head === $id) throw new RuntimeException('This resident is the designated Household Head, so their relationship is Household Head. Assign a different head first to record another relationship.');
                $relationship = residents_collapse($_POST['relationship_to_head'] ?? '');
                if ($relationship !== '' && !in_array($relationship, residents_relationships(), true)) throw new RuntimeException('Select a valid relationship.');
                $update = $connection->prepare('UPDATE resident_households SET relationship_to_head = :relationship WHERE resident_id = :resident_id AND household_id = :household_id AND is_primary = 1 AND left_at IS NULL');
                $update->execute(['relationship' => $relationship === '' ? null : $relationship, 'resident_id' => $id, 'household_id' => $current['household_id']]);
                residents_audit($connection, 'resident', $id, 'resident_household_updated', ['household_id' => (int) $current['household_id'], 'relationship_to_head' => $relationship === '' ? null : $relationship]);
                $message = 'Relationship to Household Head updated.';
            } else {
                // Manual Household Head designation for the resident's current household (never taken from the request).
                if ($current === null) throw new RuntimeException('This resident has no current household.');
                $household_id = (int) $current['household_id'];
                $lock = $connection->prepare('SELECT household_head_resident_id FROM households WHERE id = :id FOR UPDATE');
                $lock->execute(['id' => $household_id]);
                $existing_head = $lock->fetchColumn();
                $existing_head = $existing_head === null || $existing_head === false ? null : (int) $existing_head;
                if ((string) $existing_head !== (string) ($_POST['form_head_id'] ?? '')) throw new RuntimeException('The Household Head was changed by another action. Review the current head and try again.');
                if ($existing_head !== null) {
                    $member = $connection->prepare('SELECT 1 FROM resident_households WHERE household_id = :household_id AND resident_id = :resident_id AND is_primary = 1 AND left_at IS NULL');
                    $member->execute(['household_id' => $household_id, 'resident_id' => $existing_head]);
                    if (!$member->fetchColumn()) throw new RuntimeException('The current Household Head (resident #' . $existing_head . ') has no current membership in this household. The records are inconsistent, so the head cannot be changed here. Please report this for review.');
                }
                $new_head = (int) ($_POST['head_resident_id'] ?? 0);
                if ($new_head === ($existing_head ?? 0)) throw new RuntimeException('Select a different Household Head.');
                if ($new_head !== 0 && !residents_is_current_active_member($connection, $household_id, $new_head)) throw new RuntimeException('The Household Head must be an Active resident with a current membership in this household.');
                $update = $connection->prepare('UPDATE households SET household_head_resident_id = :head WHERE id = :id');
                $update->execute(['head' => $new_head === 0 ? null : $new_head, 'id' => $household_id]);
                residents_audit($connection, 'household', $household_id, 'household_head_changed', ['previous_head_resident_id' => $existing_head, 'new_head_resident_id' => $new_head === 0 ? null : $new_head]);
                $message = $new_head === 0 ? 'Household Head designation cleared.' : 'Household Head updated.';
            }
            $connection->commit();
            flash('resident_success', $message);
            redirect('resident_household.php?id=' . $id);
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getCode() === '23000' ? 'The household number is already registered, or the resident already has a current household.' : 'The household change could not be saved. No changes were made.';
        }
    }
    $resident = residents_find($connection, $id) ?? $resident;
}
$membership = residents_current_membership($connection, $id);
$members = $membership ? residents_household_members($connection, (int) $membership['household_id']) : [];
$history = residents_membership_history($connection, $id);
$households = residents_household_options($connection);
if ($membership) $households = array_values(array_filter($households, static fn (array $option): bool => (int) $option['id'] !== (int) $membership['household_id']));
$can_assign = in_array($resident['status'], $assignable_statuses, true);
$head_id = $membership && $membership['household_head_resident_id'] ? (int) $membership['household_head_resident_id'] : null;
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$household_allow_later = false;
$preselected_target = null;
foreach ($households as $option) {
    if ((int) $option['id'] === (int) $household['household_id']) $preselected_target = $option;
}
$page_title = 'Manage Household'; $active_page = 'residents';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($success = flash('resident_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="resident_view.php?id=<?= e((string) $id) ?>"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><span class="eyebrow">Resident #<?= e((string) $id) ?></span><h1>Manage Household Assignment</h1><p><?= e(residents_full_name($resident)) ?> · <?= residents_status_badge($resident['status']) ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>

    <fieldset class="resident-section">
        <legend>Current Household</legend>
        <?php if ($membership === null): ?>
            <p class="resident-pending">Household assignment pending.</p>
        <?php else: ?>
            <dl class="activity-detail-list">
                <div><dt>Household number</dt><dd><?= e($membership['household_no']) ?></dd></div>
                <div><dt>Address</dt><dd class="resident-wrap"><?= e($membership['address']) ?> (<?= e(residents_purok_label($membership['purok'])) ?>)</dd></div>
                <div><dt>Household head</dt><dd><?= $head_id ? e(residents_full_name($membership)) : '<span class="activity-detail-muted">No designated head</span>' ?></dd></div>
                <div><dt>Member since</dt><dd><?= e($membership['joined_at'] ? residents_format_date($membership['joined_at']) : 'Not recorded') ?></dd></div>
            </dl>
            <?php if ($head_id === $id): ?>
                <dl class="activity-detail-list">
                    <div><dt>Relationship to Household Head</dt><dd>Household Head</dd></div>
                </dl>
                <p class="resident-static">This resident is the designated Household Head, so the relationship cannot be edited. To record another relationship, assign a different Household Head first.</p>
            <?php else: ?>
            <form method="post" class="resident-inline-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="relationship"><input type="hidden" name="form_household_id" value="<?= e((string) $membership['household_id']) ?>"><input type="hidden" name="form_head_id" value="<?= e((string) ($head_id ?? '')) ?>">
                <div><label class="form-label" for="current_relationship">Relationship to Household Head</label><select class="form-select" id="current_relationship" name="relationship_to_head"><option value="">Not specified</option><?php foreach (residents_relationships() as $relationship): ?><option value="<?= e($relationship) ?>" <?= $membership['relationship_to_head'] === $relationship ? 'selected' : '' ?>><?= e($relationship) ?></option><?php endforeach; ?></select></div>
                <button class="btn btn-light resident-action-btn" type="submit" data-form-confirm="custom" data-dialog-heading="Update relationship?" data-dialog-message="Save the resident's relationship to the Household Head?" data-dialog-confirm="Update Relationship">Update</button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </fieldset>

    <?php if ($membership !== null): ?>
    <fieldset class="resident-section">
        <legend>Household Head</legend>
        <p class="resident-static">Only Active residents with a current membership in this household can be designated. Adding members never replaces the head automatically.</p>
        <form method="post" class="resident-inline-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="head"><input type="hidden" name="form_household_id" value="<?= e((string) $membership['household_id']) ?>"><input type="hidden" name="form_head_id" value="<?= e((string) ($head_id ?? '')) ?>">
            <div><label class="form-label" for="head_resident_id">Household Head</label><select class="form-select" id="head_resident_id" name="head_resident_id"><option value="0" <?= $head_id === null ? 'selected' : '' ?>>No designated head</option><?php foreach ($members as $member): if ($member['status'] !== 'active') continue; ?><option value="<?= e((string) $member['id']) ?>" <?= $head_id === (int) $member['id'] ? 'selected' : '' ?>><?= e(residents_full_name($member)) ?> (#<?= e((string) $member['id']) ?>)</option><?php endforeach; ?></select></div>
            <button class="btn btn-primary resident-action-btn" type="submit" data-form-confirm="custom" data-dialog-heading="Change Household Head?" data-dialog-message="Are you sure you want to change the Household Head of this household? The change will be recorded in the audit log." data-dialog-confirm="Change Household Head">Save Head</button>
        </form>
        <?php if ($members !== []): ?>
            <ul class="resident-history"><?php foreach ($members as $member): ?><li><strong><?= e(residents_full_name($member)) ?></strong><span><?= (int) $member['id'] === $head_id ? 'Household Head' : e($member['relationship_to_head'] ?? 'Relationship not recorded') ?> · <?= residents_status_badge($member['status']) ?></span></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </fieldset>
    <?php endif; ?>

    <?php if (!$can_assign): ?>
        <p class="dashboard-status warning">Only Active or Pending residents can be assigned to a household. Change the resident status first<?= in_array($resident['status'], ['moved', 'deceased'], true) ? ' (re-verification is required)' : '' ?>.</p>
    <?php else: ?>
    <?php if ($preselected_target !== null && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        <p class="resident-transfer-notice" role="status"><?= $membership ? 'Transfer requested: from household <strong>' . e($membership['household_no']) . '</strong> to household <strong>' . e($preselected_target['household_no']) . '</strong>. Review the selection below and confirm to transfer.' : 'Household <strong>' . e($preselected_target['household_no']) . '</strong> is pre-selected. Review and confirm to assign.' ?></p>
    <?php endif; ?>
    <form method="post" data-household-form>
        <?= csrf_field() ?><input type="hidden" name="action" value="<?= $membership ? 'transfer' : 'assign' ?>"><input type="hidden" name="form_household_id" value="<?= e((string) ($membership['household_id'] ?? '')) ?>">
        <?php require __DIR__ . '/layout/resident_household_fields.php'; ?>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="<?= $membership ? 'Transfer to another household?' : 'Assign household?' ?>" data-dialog-message="<?= $membership ? e('The membership in household ' . $membership['household_no'] . ' will be closed today and kept as history, then the resident will join the selected household. If the resident is Household Head of ' . $membership['household_no'] . ', that designation is cleared and no new head is assigned.') : 'Assign this resident to the selected household? The Household Head is not changed automatically.' ?>" data-dialog-confirm="<?= $membership ? 'Transfer Resident' : 'Assign Household' ?>"><?= $membership ? 'Transfer Household' : 'Assign Household' ?></button>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($membership !== null): ?>
    <form method="post" class="resident-remove-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="form_household_id" value="<?= e((string) $membership['household_id']) ?>">
        <button class="btn btn-sm announcement-delete-btn" type="submit" data-form-confirm="custom" data-dialog-heading="Remove from household?" data-dialog-message="The current membership will be closed today and kept as history. The resident's status does not change, and household assignment becomes pending." data-dialog-confirm="Remove from Household" data-dialog-danger="true">Remove from household</button>
    </form>
    <?php endif; ?>

    <?php if ($history !== []): ?>
        <h3 class="resident-subheading">Previous households</h3>
        <ul class="resident-history"><?php foreach ($history as $past): ?><li><strong><?= e($past['household_no']) ?></strong><span><?= e($past['joined_at'] ? residents_format_date($past['joined_at']) : 'Unknown') ?> – <?= e($past['left_at'] ? residents_format_date($past['left_at']) : 'Open') ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
