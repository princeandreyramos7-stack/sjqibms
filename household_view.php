<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/households.php';
households_require_view();
$connection = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$household = $id ? households_find($connection, $id) : null;
if (!$household || !residents_in_scope($connection, $household['purok'])) { http_response_code(404); exit('Household not found.'); }   // safeguard for Health Workers (assigned Puroks only)
$members = residents_household_members($connection, $id);
$timeline = households_membership_timeline($connection, $id);
$head_id = $household['household_head_resident_id'] ? (int) $household['household_head_resident_id'] : null;
$active_members = (int) $household['active_members'];
$can_manage = households_can_manage();
// Resident Details links follow the Residents module permission, which is checked again on that page.
$can_view_residents = residents_can_view();
$resident_link = static fn (array $row, int $resident_id): string => $can_view_residents ? '<a class="activity-detail-link" href="resident_view.php?id=' . e((string) $resident_id) . '">' . e(residents_full_name($row)) . '</a>' : e(residents_full_name($row));
$not_recorded = '<span class="activity-detail-muted">Not recorded</span>';
$page_title = 'Household Details'; $active_page = 'households';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($success = flash('household_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if ($failure = flash('household_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>
<article class="dashboard-panel resident-detail">
    <div class="page-heading">
        <div>
            <a class="announcement-back" href="households.php"><span aria-hidden="true">&larr;</span> Back</a>
            <span class="eyebrow">Household</span>
            <h1><?= e($household['household_no']) ?></h1>
            <p><?= households_occupancy_badge($active_members) ?> <span class="resident-detail-meta">Registered <?= e(residents_format_date($household['created_at'])) ?></span></p>
        </div>
        <?php if ($can_manage): ?>
            <div class="resident-detail-actions">
                <a class="btn announcement-edit-btn" href="household_manage.php?id=<?= e((string) $household['id']) ?>#add-member">Add member</a>
                <a class="btn btn-light resident-action-btn" href="household_manage.php?id=<?= e((string) $household['id']) ?>#set-head">Set household head</a>
                <a class="btn btn-light resident-action-btn" href="household_form.php?id=<?= e((string) $household['id']) ?>">Edit Household</a>
                <form method="post" action="household_delete.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $household['id']) ?>"><input type="hidden" name="return" value="view">
                    <?php if (households_can_delete($connection, $household)): ?>
                        <button class="btn btn-outline-danger" type="submit" data-form-confirm="custom" data-dialog-heading="Delete this household?" data-dialog-message="<?= e('Household ' . $household['household_no'] . ' has no members or records. It will be removed; the deletion is kept in the audit log.') ?>" data-dialog-confirm="Delete Household" data-dialog-dismiss="Cancel" data-dialog-danger="true">Delete</button>
                    <?php else: ?>
                        <button class="btn btn-outline-danger" type="submit">Delete</button>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php if (!$head_id): ?><div class="dashboard-status warning" role="status">This household has no household head yet.<?= $can_manage ? ($members === [] ? ' Add members first, then set the household head.' : ' Use Set household head to choose one of its members.') : '' ?></div><?php endif; ?>

    <div class="resident-detail-grid">
        <section class="resident-detail-section">
            <h2>Household Information</h2>
            <?php $detail = static fn (string $key): string => isset($household[$key]) && $household[$key] !== null && $household[$key] !== '' ? e((string) $household[$key]) : $not_recorded; ?>
            <dl class="activity-detail-list">
                <div><dt>Household number</dt><dd><?= e($household['household_no']) ?></dd></div>
                <div><dt>Purok</dt><dd><?= e(residents_purok_label($household['purok'])) ?></dd></div>
                <div><dt>Address</dt><dd class="resident-wrap"><?= nl2br(e($household['address'])) ?></dd></div>
                <?php if (array_key_exists('house_no', $household)): ?>
                    <div><dt>House number</dt><dd><?= $detail('house_no') ?></dd></div>
                    <div><dt>Street</dt><dd><?= $detail('street') ?></dd></div>
                    <div><dt>Zone</dt><dd><?= $detail('zone') ?></dd></div>
                <?php endif; ?>
                <div><dt>Housing type</dt><dd><?= $household['housing_type'] ? e($household['housing_type']) : $not_recorded ?></dd></div>
                <?php if (array_key_exists('house_ownership', $household)): ?>
                    <div><dt>House ownership</dt><dd><?= $detail('house_ownership') ?></dd></div>
                    <div><dt>Water source</dt><dd><?= $detail('water_source') ?></dd></div>
                    <div><dt>Toilet facility</dt><dd><?= $detail('toilet_facility') ?></dd></div>
                    <div><dt>Has electricity</dt><dd><?= $household['has_electricity'] === null ? $not_recorded : ((int) $household['has_electricity'] === 1 ? 'Yes' : 'No') ?></dd></div>
                <?php endif; ?>
            </dl>
            <?php if ($household['notes']): ?><h3 class="resident-subheading">Notes</h3><p class="resident-static resident-wrap"><?= nl2br(e($household['notes'])) ?></p><?php endif; ?>
        </section>
        <section class="resident-detail-section">
            <h2>Occupancy</h2>
            <dl class="activity-detail-list">
                <div><dt>Household head</dt><dd><?= $head_id ? $resident_link($household, $head_id) : '<span class="activity-detail-muted">No designated Household Head.</span>' ?></dd></div>
                <div><dt>Active members</dt><dd><?= e((string) $active_members) ?></dd></div>
                <div><dt>Occupancy status</dt><dd><?= households_occupancy_badge($active_members) ?></dd></div>
            </dl>
        </section>
    </div>

    <section class="resident-detail-section household-members">
        <h2>Current Members</h2>
        <?php if ($members === []): ?>
            <p class="resident-pending">This household has no current members.</p>
        <?php else: ?>
            <div class="resident-table-wrap">
                <table class="resident-table">
                    <thead><tr><th scope="col">Full Name</th><th scope="col">Age</th><th scope="col">Sex</th><th scope="col">Relationship to Head</th><th scope="col">Status</th><th scope="col">Member Since</th></tr></thead>
                    <tbody>
                    <?php foreach ($members as $member): $age = residents_age($member['birth_date']); $is_head = (int) $member['id'] === $head_id; ?>
                        <tr>
                            <td class="resident-name"><?= $resident_link($member, (int) $member['id']) ?></td>
                            <td><?= $age === null ? '<span class="activity-detail-muted">Unknown</span>' : e((string) $age) ?></td>
                            <td><?= e(residents_sex_labels()[$member['sex']] ?? 'Unspecified') ?></td>
                            <td><?= $is_head ? '<strong>Household Head</strong>' : ($member['relationship_to_head'] ? e($member['relationship_to_head']) : $not_recorded) ?></td>
                            <td><?= residents_status_badge($member['status']) ?></td>
                            <td><?= $member['joined_at'] ? e(residents_format_date($member['joined_at'])) : $not_recorded ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <ul class="resident-cards">
                <?php foreach ($members as $member): $age = residents_age($member['birth_date']); $is_head = (int) $member['id'] === $head_id; ?>
                    <li class="resident-card">
                        <div class="resident-card-top"><strong><?= $resident_link($member, (int) $member['id']) ?></strong><?= residents_status_badge($member['status']) ?></div>
                        <p><?= $is_head ? 'Household Head' : e($member['relationship_to_head'] ?? 'Relationship not recorded') ?> · <?= e(residents_sex_labels()[$member['sex']] ?? 'Sex unspecified') ?> · <?= $age === null ? 'Age unknown' : e((string) $age) . ' yrs' ?></p>
                        <p>Member since <?= e($member['joined_at'] ? residents_format_date($member['joined_at']) : 'not recorded') ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="resident-detail-section">
        <h2>Household Membership History</h2>
        <p class="resident-static">Relationships are shown as recorded. The database does not keep a history of past Household Head designations, so only the current designation is shown.</p>
        <?php if ($timeline === []): ?>
            <p class="resident-pending">No membership records exist for this household.</p>
        <?php else: ?>
            <ol class="household-timeline">
                <?php foreach ($timeline as $entry): $is_current = (int) $entry['is_primary'] === 1 && $entry['left_at'] === null; ?>
                    <li class="<?= $is_current ? 'is-current' : '' ?>">
                        <div class="household-timeline-top">
                            <strong><?= $resident_link($entry, (int) $entry['resident_id']) ?></strong>
                            <span class="resident-status <?= $is_current ? 'resident-status-active' : 'resident-status-inactive' ?>"><?= $is_current ? 'Current' : ((int) $entry['is_primary'] === 1 ? 'Historical' : ($entry['left_at'] === null ? 'Secondary' : 'Historical (secondary)')) ?></span>
                        </div>
                        <span>Recorded relationship: <?= $entry['relationship_to_head'] ? e($entry['relationship_to_head']) : 'not recorded' ?><?= $is_current && (int) $entry['resident_id'] === $head_id ? ' · <strong>Currently designated Household Head</strong>' : '' ?></span>
                        <span>Joined <?= $entry['joined_at'] ? e(residents_format_date($entry['joined_at'])) : 'date not recorded' ?> · <?= $entry['left_at'] ? 'Left ' . e(residents_format_date($entry['left_at'])) : ($is_current ? 'Present' : 'Leave date not recorded') ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</article>
<?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
