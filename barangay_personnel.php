<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/complaints.php';
require_once __DIR__ . '/includes/officials.php';
complaints_require_manage();
$connection = db();
complaints_require_schema($connection);

// Barangay Personnel (hearing assignments) and Hearing Venues. Personnel records are administrative references only:
// they never create accounts or grant permissions. An existing staff account may optionally be linked.
$tab = ($_GET['tab'] ?? $_POST['tab'] ?? 'personnel') === 'venues' ? 'venues' : 'personnel';
$errors = [];
$form_error = null;
$edit_id = filter_var($_GET['edit'] ?? $_POST['edit_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('case_error', 'The action could not be completed. Please try again.'); redirect('barangay_personnel.php?tab=' . $tab); }
    try {
        if ($action === 'save_personnel') {
            $name = residents_collapse((string) ($_POST['full_name'] ?? ''));
            $position = residents_collapse((string) ($_POST['position'] ?? ''));
            $user_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            if (mb_strlen($name) < 2 || mb_strlen($name) > 180) $errors['full_name'] = 'Enter a full name of 2 to 180 characters.';
            if (mb_strlen($position) < 2 || mb_strlen($position) > 120) $errors['position'] = 'Enter the official position (2 to 120 characters).';
            if ($user_id !== null) {
                $check = $connection->prepare("SELECT 1 FROM users WHERE id = :id AND role <> 'resident' AND status = 'active'");
                $check->execute(['id' => $user_id]);
                if (!$check->fetchColumn()) $errors['user_id'] = 'Select an active staff account, or leave the account empty.';
            }
            if ($errors === []) {
                if ($edit_id) {
                    $statement = $connection->prepare('UPDATE barangay_personnel SET full_name = :name, position = :position, user_id = :user WHERE id = :id');
                    $statement->execute(['name' => $name, 'position' => $position, 'user' => $user_id, 'id' => $edit_id]);
                    complaints_audit($connection, $edit_id, 'personnel_updated');
                    flash('case_success', 'Personnel record updated.');
                } else {
                    $statement = $connection->prepare('INSERT INTO barangay_personnel (full_name, position, user_id, created_by) VALUES (:name, :position, :user, :created_by)');
                    $statement->execute(['name' => $name, 'position' => $position, 'user' => $user_id, 'created_by' => current_user()['id']]);
                    complaints_audit($connection, (int) $connection->lastInsertId(), 'personnel_created');
                    flash('case_success', 'Personnel record added.');
                }
                redirect('barangay_personnel.php');
            }
        } elseif ($action === 'toggle_personnel' && $edit_id) {
            $statement = $connection->prepare("UPDATE barangay_personnel SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id");
            $statement->execute(['id' => $edit_id]);
            complaints_audit($connection, $edit_id, 'personnel_status_changed');
            flash('case_success', 'Personnel status updated. Existing hearing assignments are kept as history.');
            redirect('barangay_personnel.php');
        } elseif ($action === 'save_venue') {
            $name = residents_collapse((string) ($_POST['name'] ?? ''));
            if (mb_strlen($name) < 2 || mb_strlen($name) > 150) $errors['name'] = 'Enter a venue name of 2 to 150 characters.';
            if ($errors === []) {
                if ($edit_id) {
                    $connection->prepare('UPDATE hearing_venues SET name = :name, name_normalized = :norm WHERE id = :id')->execute(['name' => $name, 'norm' => residents_normalize_name($name), 'id' => $edit_id]);
                    complaints_audit($connection, $edit_id, 'venue_updated');
                    flash('case_success', 'Venue updated.');
                } else {
                    $connection->prepare('INSERT INTO hearing_venues (name, name_normalized, created_by) VALUES (:name, :norm, :user)')->execute(['name' => $name, 'norm' => residents_normalize_name($name), 'user' => current_user()['id']]);
                    complaints_audit($connection, (int) $connection->lastInsertId(), 'venue_created');
                    flash('case_success', 'Venue added.');
                }
                redirect('barangay_personnel.php?tab=venues');
            }
        } elseif ($action === 'toggle_venue' && $edit_id) {
            $connection->prepare('UPDATE hearing_venues SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $edit_id]);
            complaints_audit($connection, $edit_id, 'venue_status_changed');
            flash('case_success', 'Venue status updated. Existing hearings keep their venue.');
            redirect('barangay_personnel.php?tab=venues');
        }
    } catch (PDOException $exception) {
        // Unique keys: one personnel record per account; one venue per normalized name.
        $form_error = ($exception->errorInfo[1] ?? 0) === 1062 ? ($tab === 'venues' ? 'A venue with this name already exists.' : 'That account is already linked to another personnel record.') : 'The record could not be saved. No changes were made.';
    }
}

$personnel = $connection->query('SELECT p.id, p.full_name, p.position, p.status, p.user_id, u.name AS account_name, u.role AS account_role, (SELECT COUNT(*) FROM case_hearing_personnel a WHERE a.personnel_id = p.id AND a.removed_at IS NULL) AS active_assignments FROM barangay_personnel p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.status, p.full_name')->fetchAll();
$venues = $connection->query("SELECT v.id, v.name, v.is_active, (SELECT COUNT(*) FROM case_hearings h WHERE h.venue_id = v.id AND h.status IN ('scheduled', 'rescheduled')) AS active_hearings FROM hearing_venues v ORDER BY v.is_active DESC, v.name")->fetchAll();
$accounts = $connection->query("SELECT id, name, role FROM users WHERE role <> 'resident' AND status = 'active' ORDER BY name")->fetchAll();
$editing = null;
if ($edit_id) {
    foreach ($tab === 'venues' ? $venues : $personnel as $row) if ((int) $row['id'] === $edit_id) $editing = $row;
}
$form = $editing ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($errors !== [] || $form_error !== null)) $form = array_merge($form, $_POST);
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$role_label = static fn (string $role): string => ['super_admin' => 'System Administrator', 'punong_barangay' => 'Punong Barangay', 'secretary' => 'Barangay Secretary', 'treasurer' => 'Treasurer', 'health_worker' => 'Health Worker', 'official' => 'Barangay Official'][$role] ?? $role;
$page_title = 'Personnel & Venues'; $active_page = 'complaints';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <a class="announcement-back" href="complaints.php?tab=hearings"><span aria-hidden="true">&larr;</span> Back to Complaints &amp; Blotter</a>
    <div class="page-heading"><div><h1>Personnel &amp; Venues</h1><p>Barangay personnel and venues used for hearing schedules. Assignments never grant access to confidential case records.</p></div></div>
    <?php if ($success = flash('case_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
    <?php if ($failure = flash('case_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php endif; ?>

    <section class="dashboard-panel announcements-page">
        <nav class="announcement-tabs page-tabs" aria-label="Personnel and venues">
            <a class="announcement-tab <?= $tab === 'personnel' ? 'active' : '' ?>" href="barangay_personnel.php">Barangay Personnel</a>
            <a class="announcement-tab <?= $tab === 'venues' ? 'active' : '' ?>" href="barangay_personnel.php?tab=venues">Hearing Venues</a>
        </nav>

        <?php if ($tab === 'personnel'): ?>
            <form class="case-inline-add case-admin-form" method="post" action="barangay_personnel.php">
                <?= csrf_field() ?><input type="hidden" name="action" value="save_personnel"><?php if ($editing): ?><input type="hidden" name="edit_id" value="<?= e((string) $editing['id']) ?>"><?php endif; ?>
                <div><label class="form-label" for="p-name">Full name</label><input class="form-control<?= $field_class('full_name') ?>" id="p-name" name="full_name" value="<?= e((string) ($form['full_name'] ?? '')) ?>" maxlength="180" required data-summary-label="Name"><?= $field_error('full_name') ?></div>
                <div><label class="form-label" for="p-position">Official position</label><input class="form-control<?= $field_class('position') ?>" id="p-position" name="position" value="<?= e((string) ($form['position'] ?? '')) ?>" maxlength="120" required data-summary-label="Position"><?= $field_error('position') ?></div>
                <div><label class="form-label" for="p-user">Linked staff account <span class="activity-detail-muted">(optional)</span></label><select class="form-select<?= $field_class('user_id') ?>" id="p-user" name="user_id"><option value="">No account</option><?php foreach ($accounts as $account): ?><option value="<?= e((string) $account['id']) ?>" <?= (string) ($form['user_id'] ?? '') === (string) $account['id'] ? 'selected' : '' ?>><?= e($account['name'] . ' — ' . $role_label($account['role'])) ?></option><?php endforeach; ?></select><?= $field_error('user_id') ?></div>
                <div class="doc-action-group"><button class="btn doc-action-btn is-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $editing ? 'Save personnel changes?' : 'Add this personnel record?' ?>" data-dialog-message="This is an administrative record only. No account or permission is created or changed." data-dialog-confirm="<?= $editing ? 'Save Changes' : 'Add Personnel' ?>" data-dialog-dismiss="Cancel"><?= $editing ? 'Save Changes' : 'Add Personnel' ?></button><?php if ($editing): ?><a class="btn doc-action-btn is-view" href="barangay_personnel.php">Cancel</a><?php endif; ?></div>
            </form>
            <?php if ($personnel === []): ?><div class="dashboard-empty-state">No barangay personnel are recorded yet.</div><?php else: ?>
                <div class="resident-table-wrap"><table class="resident-table"><thead><tr><th scope="col">Name</th><th scope="col">Position</th><th scope="col">Linked account</th><th scope="col">Active assignments</th><th scope="col">Status</th><th scope="col" class="document-actions-cell">Actions</th></tr></thead><tbody>
                    <?php foreach ($personnel as $row): ?><tr><td class="resident-name"><?= e($row['full_name']) ?></td><td><?= e($row['position']) ?></td><td><?= $row['account_name'] ? e($row['account_name']) : '<span class="activity-detail-muted">None</span>' ?></td><td><?= e((string) $row['active_assignments']) ?></td><td><?= officials_status_badge($row['status']) ?></td><td class="document-actions-cell"><div class="doc-action-group"><a class="btn doc-action-btn is-view" href="barangay_personnel.php?edit=<?= e((string) $row['id']) ?>">Edit</a><form method="post" action="barangay_personnel.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_personnel"><input type="hidden" name="edit_id" value="<?= e((string) $row['id']) ?>"><button class="btn doc-action-btn <?= $row['status'] === 'active' ? 'is-danger' : 'is-primary' ?>" type="submit" data-form-confirm="custom" data-dialog-heading="<?= $row['status'] === 'active' ? 'Deactivate this person?' : 'Activate this person?' ?>" data-dialog-message="<?= $row['status'] === 'active' ? 'Inactive personnel cannot be assigned to new hearings. Existing assignments are kept.' : 'The person can be assigned to hearings again.' ?>" data-dialog-confirm="<?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?>" data-dialog-dismiss="Cancel" data-dialog-danger="<?= $row['status'] === 'active' ? 'true' : 'false' ?>"><?= $row['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form></div></td></tr><?php endforeach; ?>
                </tbody></table></div>
                <ul class="resident-cards"><?php foreach ($personnel as $row): ?><li class="resident-card"><div class="resident-card-top"><strong><?= e($row['full_name']) ?></strong><?= officials_status_badge($row['status']) ?></div><p><?= e($row['position']) ?></p><p>Account: <?= $row['account_name'] ? e($row['account_name']) : 'None' ?> · Active assignments: <?= e((string) $row['active_assignments']) ?></p><div class="doc-action-group"><a class="btn doc-action-btn is-view" href="barangay_personnel.php?edit=<?= e((string) $row['id']) ?>">Edit</a></div></li><?php endforeach; ?></ul>
            <?php endif; ?>
        <?php else: ?>
            <form class="case-inline-add case-admin-form" method="post" action="barangay_personnel.php">
                <?= csrf_field() ?><input type="hidden" name="tab" value="venues"><input type="hidden" name="action" value="save_venue"><?php if ($editing): ?><input type="hidden" name="edit_id" value="<?= e((string) $editing['id']) ?>"><?php endif; ?>
                <div><label class="form-label" for="v-name">Venue name</label><input class="form-control<?= $field_class('name') ?>" id="v-name" name="name" value="<?= e((string) ($form['name'] ?? '')) ?>" maxlength="150" required data-summary-label="Venue"><?= $field_error('name') ?></div>
                <div class="doc-action-group"><button class="btn doc-action-btn is-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $editing ? 'Save venue changes?' : 'Add this venue?' ?>" data-dialog-message="Venues are used for hearing schedules and conflict checks." data-dialog-confirm="<?= $editing ? 'Save Changes' : 'Add Venue' ?>" data-dialog-dismiss="Cancel"><?= $editing ? 'Save Changes' : 'Add Venue' ?></button><?php if ($editing): ?><a class="btn doc-action-btn is-view" href="barangay_personnel.php?tab=venues">Cancel</a><?php endif; ?></div>
            </form>
            <?php if ($venues === []): ?><div class="dashboard-empty-state">No hearing venues are recorded yet.</div><?php else: ?>
                <ul class="case-link-list case-venue-list"><?php foreach ($venues as $row): ?><li><div><strong><?= e($row['name']) ?></strong> <?= residents_status_badge((int) $row['is_active'] === 1 ? 'active' : 'inactive') ?><br><span class="activity-detail-muted">Upcoming or active hearings: <?= e((string) $row['active_hearings']) ?></span></div><div class="doc-action-group"><a class="btn doc-action-btn is-view" href="barangay_personnel.php?tab=venues&amp;edit=<?= e((string) $row['id']) ?>">Edit</a><form method="post" action="barangay_personnel.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="tab" value="venues"><input type="hidden" name="action" value="toggle_venue"><input type="hidden" name="edit_id" value="<?= e((string) $row['id']) ?>"><button class="btn doc-action-btn <?= (int) $row['is_active'] === 1 ? 'is-danger' : 'is-primary' ?>" type="submit" data-form-confirm="custom" data-dialog-heading="<?= (int) $row['is_active'] === 1 ? 'Deactivate this venue?' : 'Activate this venue?' ?>" data-dialog-message="<?= (int) $row['is_active'] === 1 ? 'Inactive venues cannot be used for new or rescheduled hearings.' : 'The venue can be used for hearings again.' ?>" data-dialog-confirm="<?= (int) $row['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>" data-dialog-dismiss="Cancel" data-dialog-danger="<?= (int) $row['is_active'] === 1 ? 'true' : 'false' ?>"><?= (int) $row['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button></form></div></li><?php endforeach; ?></ul>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
