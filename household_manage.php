<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/households.php';
require_once __DIR__ . '/includes/live_search.php';
households_require_manage();
$connection = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$household = $id ? households_find($connection, $id) : null;
if (!$household) { http_response_code(404); exit('Household not found.'); }
$assignable_statuses = ['active', 'pending'];
$form_error = null;
$search = residents_collapse((string) ($_GET['q'] ?? ''));
if (mb_strlen($search) > 100) $search = mb_substr($search, 0, 100);

// Set household head: only an Active resident with a current membership in this household (same rules as
// resident_household.php). form_head_id guards against a change made by someone else after the page was opened.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_head') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session token expired. Please try again.';
    else {
        try {
            $connection->beginTransaction();
            $lock = $connection->prepare('SELECT household_head_resident_id FROM households WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $id]);
            $existing_head = $lock->fetchColumn();
            $existing_head = $existing_head === null || $existing_head === false ? null : (int) $existing_head;
            if ((string) $existing_head !== (string) ($_POST['form_head_id'] ?? '')) throw new RuntimeException('The household head was changed by another action. Review the current head and try again.');
            $new_head = (int) ($_POST['head_resident_id'] ?? 0);
            if ($new_head === 0) throw new RuntimeException('Please select a member to be the household head.');
            if ($new_head === $existing_head) throw new RuntimeException('This member is already the household head.');
            if (!residents_is_current_active_member($connection, $id, $new_head)) throw new RuntimeException('The household head must be an Active resident with a current membership in this household.');
            $connection->prepare('UPDATE households SET household_head_resident_id = :head WHERE id = :id')->execute(['head' => $new_head, 'id' => $id]);
            residents_audit($connection, 'household', $id, 'household_head_changed', ['previous_head_resident_id' => $existing_head, 'new_head_resident_id' => $new_head]);
            $connection->commit();
            $head = residents_find($connection, $new_head);
            flash('household_success', ($head ? residents_full_name($head) : 'The member') . ' is now the household head.');
            redirect('household_manage.php?id=' . $id . '#set-head');
        } catch (PDOException) { // before RuntimeException: PDOException extends it, and its message must never reach the user
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = 'The household head could not be changed. No changes were made.';
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        }
    }
}

// Case A only: assigning an unassigned resident. Transfers (case C) are routed to the existing resident_household.php workflow.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'set_head') {
    $resident_id = filter_var($_POST['resident_id'] ?? null, FILTER_VALIDATE_INT);
    $relationship = residents_collapse($_POST['relationship_to_head'] ?? '');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session token expired. Please try again.';
    elseif (($_POST['action'] ?? '') !== 'assign_member' || !$resident_id) $form_error = 'Invalid household action.';
    elseif ($relationship !== '' && !in_array($relationship, residents_relationships(), true)) $form_error = 'Select a valid relationship.';
    if ($form_error === null) {
        try {
            $connection->beginTransaction();
            $resident = residents_find($connection, $resident_id, true);
            if (!$resident) throw new RuntimeException('The selected resident no longer exists.');
            if (!in_array($resident['status'], $assignable_statuses, true)) throw new RuntimeException('Only Active or Pending residents can be assigned to a household. Change the resident status first.');
            // Re-checked under lock: a resident who gained a household after the search was shown must go through the transfer workflow instead.
            if (residents_current_membership($connection, $resident_id, true) !== null) throw new RuntimeException('This resident now has a current household. Search again and use the transfer option if needed.');
            $household_id = residents_open_membership($connection, $resident_id, ['mode' => 'existing', 'household_id' => $id, 'relationship' => $relationship === '' ? null : $relationship]);
            residents_audit($connection, 'resident', $resident_id, 'resident_household_assigned', ['household_id' => $household_id, 'relationship_to_head' => $relationship === '' ? null : $relationship]);
            $connection->commit();
            flash('household_success', residents_full_name($resident) . ' was added to this household.');
            redirect('household_manage.php?id=' . $id);
        } catch (RuntimeException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            $form_error = $exception->getCode() === '23000' ? 'This resident already has a current household. Search again and use the transfer option if needed.' : 'The member could not be added. No changes were made.';
        }
    }
}
$household = households_find($connection, $id) ?? $household;
$members = residents_household_members($connection, $id);
$head_id = $household['household_head_resident_id'] ? (int) $household['household_head_resident_id'] : null;
// Resident lookups start at 2 non-whitespace characters (the Live Search helper applies the same rule before sending a request).
$search_ready = mb_strlen(preg_replace('/\s+/u', '', $search) ?? '') >= 2;
$results = $search_ready ? households_search_residents($connection, $search, $id) : [];
// Resident lookup results shared by the full page and Live Search responses. Only minimal identifying fields are shown.
$render_results = static function () use ($search, $search_ready, $results, $id, $household, $assignable_statuses): void {
    if ($search !== '' && !$search_ready): ?>
            <p class="resident-static">Type at least 2 characters to search.</p>
        <?php elseif ($search !== '' && $results === []): ?>
            <p class="resident-pending">No matching records found.</p>
        <?php elseif ($results !== []): ?>
            <ul class="household-member-list">
                <?php foreach ($results as $result): $current = $result['current_household_id'] !== null ? (int) $result['current_household_id'] : null; $birth_year = $result['birth_date'] ? substr($result['birth_date'], 0, 4) : null; ?>
                    <li>
                        <div><strong><?= e(residents_full_name($result)) ?></strong><span>#<?= e((string) $result['id']) ?> · <?= $birth_year ? 'Born ' . e($birth_year) : 'Birth year unknown' ?> · <?= e($result['purok']) ?> · <?= residents_status_badge($result['status']) ?></span></div>
                        <?php if ($current === $id): ?>
                            <p class="household-result-note">Already a member of this household. <a class="activity-detail-link" href="resident_household.php?id=<?= e((string) $result['id']) ?>">Manage assignment</a></p>
                        <?php elseif ((int) $result['previous_member'] === 1): ?>
                            <p class="household-result-note">Previously a member of this household. Rejoining is not supported because it would overwrite the earlier membership history.</p>
                        <?php elseif ($current !== null): ?>
                            <p class="household-result-note">Currently in household <strong><?= e($result['current_household_no']) ?></strong>. <a class="btn btn-sm btn-outline-primary" href="resident_household.php?id=<?= e((string) $result['id']) ?>&amp;household_id=<?= e((string) $id) ?>">Transfer to <?= e($household['household_no']) ?></a></p>
                        <?php elseif (!in_array($result['status'], $assignable_statuses, true)): ?>
                            <p class="household-result-note">Only Active or Pending residents can be assigned. <a class="activity-detail-link" href="resident_status.php?id=<?= e((string) $result['id']) ?>">Review status</a></p>
                        <?php else: ?>
                            <form method="post" class="resident-inline-form household-assign-form">
                                <?= csrf_field() ?><input type="hidden" name="action" value="assign_member"><input type="hidden" name="resident_id" value="<?= e((string) $result['id']) ?>">
                                <div><label class="form-label" for="relationship-<?= e((string) $result['id']) ?>">Relationship to Household Head</label><select class="form-select" id="relationship-<?= e((string) $result['id']) ?>" name="relationship_to_head"><option value="">Not specified</option><?php foreach (residents_relationships() as $relationship): ?><option value="<?= e($relationship) ?>"><?= e($relationship) ?></option><?php endforeach; ?></select></div>
                                <button class="btn btn-primary resident-action-btn" type="submit" data-form-confirm="custom" data-dialog-heading="Add member to household?" data-dialog-message="<?= e('Add ' . residents_full_name($result) . ' to household ' . $household['household_no'] . '? The Household Head is not changed automatically.') ?>" data-dialog-confirm="Add Member">Add to Household</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($results) === 20): ?><p class="resident-static">Showing the first 20 matches. Refine the name to narrow the results.</p><?php endif; ?>
        <?php endif;
};
if (live_search_is_request()) live_search_respond($render_results);
$page_title = 'Manage Household'; $active_page = 'households';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($success = flash('household_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="household_view.php?id=<?= e((string) $id) ?>"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><span class="eyebrow">Household</span><h1>Manage Household <?= e($household['household_no']) ?></h1><p><?= e($household['address']) ?> · <?= e($household['purok']) ?> · <?= households_occupancy_badge((int) $household['active_members']) ?></p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php endif; ?>

    <fieldset class="resident-section" id="set-head">
        <legend>Household Head</legend>
        <p class="resident-static"><?= $head_id ? 'Current household head: <strong>' . e(residents_full_name($household)) . '</strong>.' : 'This household has no household head yet.' ?></p>
        <?php $eligible = array_values(array_filter($members, static fn (array $member): bool => $member['status'] === 'active')); ?>
        <?php if ($eligible === []): ?>
            <p class="resident-static">Add an Active resident as a member first; only Active current members can be the household head.</p>
        <?php else: ?>
            <form method="post" class="resident-inline-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="set_head"><input type="hidden" name="form_head_id" value="<?= e((string) ($head_id ?? '')) ?>">
                <div><label class="form-label" for="head_resident_id">Set household head</label><select class="form-select" id="head_resident_id" name="head_resident_id"><option value="0">Select a member</option><?php foreach ($eligible as $member): ?><option value="<?= e((string) $member['id']) ?>" <?= $head_id === (int) $member['id'] ? 'selected' : '' ?>><?= e(residents_full_name($member)) ?> (#<?= e((string) $member['id']) ?>)</option><?php endforeach; ?></select></div>
                <button class="btn btn-primary resident-action-btn" type="submit" data-form-confirm="custom" data-dialog-heading="Set household head?" data-dialog-message="The selected member becomes the household head. The change is recorded in the audit log." data-dialog-confirm="Set Household Head">Save Head</button>
            </form>
        <?php endif; ?>
    </fieldset>

    <fieldset class="resident-section">
        <legend>Current Members</legend>
        <?php if ($members === []): ?>
            <p class="resident-pending">This household has no current members.</p>
        <?php else: ?>
            <ul class="household-member-list">
                <?php foreach ($members as $member): ?>
                    <li>
                        <div><strong><?= e(residents_full_name($member)) ?></strong><span><?= (int) $member['id'] === $head_id ? 'Household Head' : e($member['relationship_to_head'] ?? 'Relationship not recorded') ?> · <?= residents_status_badge($member['status']) ?></span></div>
                        <div class="management-actions"><a class="btn btn-sm btn-outline-primary" href="resident_view.php?id=<?= e((string) $member['id']) ?>">Resident Details</a><a class="btn btn-sm btn-outline-primary" href="resident_household.php?id=<?= e((string) $member['id']) ?>">Manage Assignment</a></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </fieldset>

    <fieldset class="resident-section" id="add-member">
        <legend>Add Member</legend>
        <p class="resident-static">Search for a recorded resident. Someone not yet recorded must be added in Residents first.</p>
        <form class="resident-filters" method="get" action="household_manage.php" data-live-search data-live-target="#member-results" data-live-min="2" data-live-min-message="Type at least 2 characters to search.">
            <input type="hidden" name="id" value="<?= e((string) $id) ?>">
            <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="member-search">Search resident by name</label><input class="activity-filter-input" type="search" id="member-search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Type at least 2 characters" autocomplete="off" data-live-query></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Search</button></div>
        </form>
        <div id="member-results" class="live-search-results"><?php $render_results(); ?></div>
    </fieldset>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
