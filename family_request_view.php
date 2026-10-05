<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/registrations.php';
require_once __DIR__ . '/includes/family_requests.php';
reg_require();
$connection = db();

// One family member added by a resident: the details, who added them and the household they will join, and, while
// pending, Approve / Reject (family_request_action.php).
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$request = fam_find($connection, $id);
if ($request === null) { http_response_code(404); exit('Request not found.'); }
$household = $request['requester_resident_id'] !== null ? residents_current_membership($connection, (int) $request['requester_resident_id']) : null;
$matches = $request['resident_id'] !== null ? residents_duplicates($connection, $request, (int) $request['resident_id']) : ['exact' => [], 'possible' => []];
$similar = array_merge($matches['exact'], $matches['possible']);
$pending = $request['status'] === 'pending';
$age = residents_age($request['birth_date']);
$can_view_residents = can_access_navigation('residents');
$not_recorded = '<span class="activity-detail-muted">Not recorded</span>';
$page_title = 'Family Member ' . fam_reference($request); $active_page = 'registrations';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($failure = flash('registrations_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
<article class="dashboard-panel resident-detail">
    <div class="page-heading">
        <div>
            <a class="announcement-back" href="registrations.php#family-members"><span aria-hidden="true">&larr;</span> Back</a>
            <span class="eyebrow">Family member added by a resident · <?= e(fam_reference($request)) ?></span>
            <h1><?= e(residents_full_name($request)) ?></h1>
            <p><?= fam_status_badge($request['status']) ?> <span class="resident-detail-meta">Submitted <?= e(reg_format_datetime($request['created_at'])) ?></span></p>
        </div>
        <?php if ($can_view_residents && $request['requester_resident_id'] !== null): ?><div class="resident-detail-actions"><a class="btn btn-light resident-action-btn" href="resident_view.php?id=<?= e((string) $request['requester_resident_id']) ?>">Open requester's profile</a></div><?php endif; ?>
    </div>

    <div class="resident-detail-grid">
        <section class="resident-detail-section">
            <h2>Family Member</h2>
            <dl class="activity-detail-list">
                <div><dt>Full name</dt><dd><?= e(residents_full_name($request)) ?></dd></div>
                <div><dt>Birthdate</dt><dd><?= $request['birth_date'] ? e(residents_format_date($request['birth_date'])) : $not_recorded ?></dd></div>
                <div><dt>Age</dt><dd><?= $age === null ? $not_recorded : e((string) $age) ?></dd></div>
                <div><dt>Gender</dt><dd><?= e(residents_sex_labels()[$request['sex']] ?? 'Unspecified') ?></dd></div>
                <div><dt>Civil status</dt><dd><?= isset(residents_civil_status_labels()[$request['civil_status'] ?? '']) ? e(residents_civil_status_labels()[$request['civil_status']]) : $not_recorded ?></dd></div>
                <div><dt>Profile status</dt><dd><?= $request['member_status'] ? residents_status_badge($request['member_status']) : $not_recorded ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Added By</h2>
            <dl class="activity-detail-list">
                <div><dt>Resident</dt><dd><?= e((string) $request['requester_name']) ?> <?= $request['requester_resident_status'] ? residents_status_badge($request['requester_resident_status']) : '' ?></dd></div>
                <div><dt>Relationship to the resident</dt><dd><?= e($request['relationship_to_requester']) ?></dd></div>
                <div><dt>Household</dt><dd><?php if ($household): ?><?= e($household['household_no']) ?> · <?= e($household['address']) ?><?php else: ?><span class="activity-detail-muted">The resident has no household yet; after approval, assign one under Households → Residents without a household.</span><?php endif; ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Similar Resident Records</h2>
            <?php if ($similar === []): ?>
                <p class="activity-detail-muted">No other resident with the same name or birthdate.</p>
            <?php else: ?>
                <p class="resident-detail-meta">Check that this is not the same person as an existing record.</p>
                <ul class="resident-history"><?php foreach ($similar as $match): ?><li><strong><?= $can_view_residents ? '<a class="activity-detail-link" href="resident_view.php?id=' . e((string) $match['id']) . '">' . e($match['name']) . '</a>' : e($match['name']) ?></strong><span>#<?= e((string) $match['id']) ?> · <?= e($match['birth_date'] ? residents_format_date($match['birth_date']) : 'No birthdate') ?> · <?= e(residents_purok_label($match['purok'])) ?></span></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
        <section class="resident-detail-section">
            <h2>Decision</h2>
            <?php if ($pending): ?>
                <p class="activity-detail-muted">Waiting for review.</p>
            <?php else: ?>
                <dl class="activity-detail-list">
                    <div><dt><?= e(ucfirst($request['status'])) ?></dt><dd><?= e(reg_format_datetime($request['decided_at'])) ?> · <?= e($request['decided_by_name'] ?? 'Unknown user') ?><?= $request['review_notes'] ? '<br><span class="resident-wrap">' . e($request['review_notes']) . '</span>' : '' ?></dd></div>
                </dl>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($pending): ?>
        <section class="resident-detail-section">
            <h2>Review</h2>
            <div class="resident-detail-grid">
                <form method="post" action="family_request_action.php">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $request['id']) ?>"><input type="hidden" name="decision" value="approve">
                    <label class="form-label" for="approve-notes">Notes <span class="activity-detail-muted">(optional)</span></label>
                    <textarea class="form-control" id="approve-notes" name="notes" rows="2" maxlength="500"></textarea>
                    <button class="btn btn-primary mt-2" type="submit" data-form-confirm="custom" data-dialog-heading="Approve this family member?" data-dialog-message="<?= e(residents_full_name($request) . ' becomes an Active resident' . ($household ? ' and is added to household ' . $household['household_no'] : '') . '.') ?>" data-dialog-confirm="Approve" data-dialog-dismiss="Cancel">Approve</button>
                </form>
                <form method="post" action="family_request_action.php">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $request['id']) ?>"><input type="hidden" name="decision" value="reject">
                    <label class="form-label" for="reject-notes">Reason for rejecting <span class="resident-required" aria-hidden="true">*</span> <span class="activity-detail-muted">(the resident will see this)</span></label>
                    <textarea class="form-control" id="reject-notes" name="notes" rows="2" minlength="10" maxlength="500" required></textarea>
                    <button class="btn btn-outline-danger mt-2" type="submit" data-form-confirm="custom" data-dialog-heading="Reject this family member?" data-dialog-message="The profile becomes Inactive. Nothing is deleted." data-dialog-confirm="Reject" data-dialog-dismiss="Cancel" data-dialog-danger="true">Reject</button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</article>
<?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
