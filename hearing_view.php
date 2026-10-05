<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/hearings.php';
complaints_require_manage();
$connection = db();
complaints_require_schema($connection);
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$hearing = $id ? hearings_find($connection, $id) : null;
if (!$hearing) { http_response_code(404); exit('Hearing not found.'); }
$participants = hearings_participants($connection, (int) $hearing['id']);
$assignments = hearings_personnel_assignments($connection, (int) $hearing['id']);
$schedule_history = hearings_schedule_history($connection, (int) $hearing['id']);
$is_active = in_array($hearing['status'], hearings_active_statuses(), true);
$has_started = strtotime($hearing['starts_at']) <= time();
$venues = hearings_active_venues($connection);
$assigned_ids = hearings_active_personnel_ids($connection, (int) $hearing['id']);
$available_personnel = array_values(array_filter(hearings_active_personnel($connection), static fn (array $p): bool => !in_array((int) $p['id'], $assigned_ids, true)));
$case_link = $hearing['complaint_id'] ? ['complaint_view.php?id=' . $hearing['complaint_id'], 'Complaint ' . $hearing['complaint_reference'], complaints_badge('complaint', (string) $hearing['complaint_status'])] : ['blotter_view.php?id=' . $hearing['blotter_id'], 'Blotter ' . $hearing['blotter_reference'], complaints_badge('blotter', (string) $hearing['blotter_status'])];
$return = (string) ($_GET['return'] ?? '');
$back = 'complaints.php' . (preg_match('/^[A-Za-z0-9_=&%+.\-]{1,300}$/', $return) ? '?' . $return : '?tab=hearings');
$details = e(json_encode([['Hearing', $hearing['hearing_number']], ['Schedule', complaints_format_time_range($hearing['starts_at'], $hearing['ends_at'])], ['Venue', $hearing['venue_name']]], JSON_UNESCAPED_UNICODE));
$hidden = csrf_field() . '<input type="hidden" name="id" value="' . e((string) $hearing['id']) . '">';
$page_title = 'Hearing Details'; $active_page = 'complaints';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($success = flash('case_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if ($failure = flash('case_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>

<div class="document-detail-head">
    <div>
        <a class="announcement-back" href="<?= e($back) ?>"><span aria-hidden="true">&larr;</span> Back to Hearing Schedule</a>
        <h1>Hearing Details</h1>
        <p class="document-detail-sub"><strong><?= e($hearing['hearing_number']) ?></strong> <?= complaints_badge('hearing', $hearing['status']) ?> <span class="case-confidential">Confidential</span></p>
    </div>
    <?php if ($is_active): ?>
        <div class="doc-action-group is-large document-detail-actions">
            <?php if ($has_started): ?><a class="btn doc-action-btn is-primary" href="#outcome-panel">Record Outcome</a><?php endif; ?>
            <a class="btn doc-action-btn is-view" href="#reschedule-panel">Reschedule</a>
            <form method="post" action="hearing_action.php" class="doc-action-form"><?= $hidden ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="reason" value=""><button class="btn doc-action-btn is-danger" type="submit" data-form-confirm="custom" data-dialog-heading="Cancel this hearing?" data-dialog-message="The hearing will be cancelled. The related case is not changed and the schedule history is kept." data-dialog-details="<?= $details ?>" data-dialog-reason="Reason for cancellation" data-dialog-confirm="Cancel Hearing" data-dialog-dismiss="Keep Hearing" data-dialog-danger="true">Cancel Hearing</button></form>
        </div>
    <?php endif; ?>
</div>

<div class="document-detail-layout">
    <div class="document-detail-info">
        <section class="dashboard-panel case-panel">
            <h2>Hearing Information</h2>
            <dl class="activity-detail-list">
                <div><dt>Related case</dt><dd><a class="activity-detail-link" href="<?= e($case_link[0]) ?>"><?= e($case_link[1]) ?></a> <?= $case_link[2] ?></dd></div>
                <div><dt>Hearing type</dt><dd><?= e($hearing['hearing_type']) ?></dd></div>
                <div><dt>Schedule</dt><dd><?= e(complaints_format_time_range($hearing['starts_at'], $hearing['ends_at'])) ?></dd></div>
                <div><dt>Venue</dt><dd><?= e($hearing['venue_name']) ?></dd></div>
                <div><dt>Scheduled by</dt><dd><?= e($hearing['creator_name'] ?? '—') ?> · <?= e(complaints_format_datetime($hearing['created_at'])) ?></dd></div>
                <?php if ($hearing['notes']): ?><div><dt>Notes</dt><dd class="resident-wrap"><?= nl2br(e($hearing['notes'])) ?></dd></div><?php endif; ?>
                <?php if ($hearing['status'] === 'cancelled'): ?><div><dt>Cancelled</dt><dd class="resident-wrap"><?= e(complaints_format_datetime($hearing['cancelled_at'])) ?> · <?= e($hearing['canceller_name'] ?? '') ?><br><?= e($hearing['cancellation_reason']) ?></dd></div><?php endif; ?>
                <?php if ($hearing['status'] === 'completed'): ?><div><dt>Outcome</dt><dd class="resident-wrap"><?= nl2br(e($hearing['outcome_summary'])) ?><br><span class="activity-detail-muted">Recorded <?= e(complaints_format_datetime($hearing['outcome_recorded_at'])) ?> · <?= e($hearing['outcome_recorder_name'] ?? '') ?></span></dd></div><?php endif; ?>
            </dl>
            <p class="document-info-note">Completing a hearing does not resolve or close the related case.</p>
        </section>

        <section class="dashboard-panel case-panel">
            <h2>Participants and Attendance</h2>
            <?php if ($participants === []): ?><p class="resident-static">No participants are recorded.</p><?php else: ?>
                <?php $can_record = $has_started && $hearing['status'] !== 'cancelled'; ?>
                <form method="post" action="hearing_action.php"><?= $hidden ?><input type="hidden" name="action" value="attendance">
                    <ul class="case-attendance-list">
                        <?php foreach ($participants as $participant): $pid = (string) $participant['id']; ?>
                            <li>
                                <div><strong><?= e($participant['name']) ?></strong> <span class="case-role-tag"><?= e(ucfirst($participant['participant_role'])) ?></span><?php if ($participant['attendance_recorded_at']): ?><br><span class="activity-detail-muted">Recorded <?= e(complaints_format_datetime($participant['attendance_recorded_at'])) ?><?= $participant['recorder_name'] ? ' · ' . e($participant['recorder_name']) : '' ?></span><?php endif; ?></div>
                                <?php if ($can_record): ?>
                                    <div class="case-attendance-fields"><select class="form-select form-select-sm" name="attendance[<?= e($pid) ?>][status]" aria-label="Attendance for <?= e($participant['name']) ?>"><?php foreach (hearing_attendance_labels() as $value => $label): ?><option value="<?= e($value) ?>" <?= $participant['attendance_status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><input class="form-control form-control-sm" name="attendance[<?= e($pid) ?>][notes]" value="<?= e($participant['attendance_notes'] ?? '') ?>" maxlength="500" placeholder="Notes (optional)" aria-label="Attendance notes for <?= e($participant['name']) ?>"></div>
                                <?php else: ?>
                                    <div><?= complaints_badge('attendance', $participant['attendance_status']) ?><?= $participant['attendance_notes'] ? '<br><span class="activity-detail-muted">' . e($participant['attendance_notes']) . '</span>' : '' ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($can_record): ?><div class="doc-action-group"><button class="btn doc-action-btn is-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Save attendance?" data-dialog-message="Attendance is recorded for this hearing session only." data-dialog-details="<?= $details ?>" data-dialog-confirm="Save Attendance" data-dialog-dismiss="Cancel">Save Attendance</button></div>
                    <?php elseif ($hearing['status'] !== 'cancelled'): ?><p class="resident-static">Attendance can be recorded once the hearing session starts.</p><?php endif; ?>
                </form>
            <?php endif; ?>
        </section>

        <?php if ($is_active && $has_started): ?>
            <section class="dashboard-panel case-panel" id="outcome-panel">
                <h2>Record Hearing Outcome</h2>
                <form method="post" action="hearing_action.php"><?= $hidden ?><input type="hidden" name="action" value="outcome">
                    <label class="form-label" for="outcome">Documented outcome <span class="activity-detail-muted">(required)</span></label>
                    <textarea class="form-control" id="outcome" name="outcome" rows="4" minlength="10" maxlength="5000" required></textarea>
                    <div class="doc-action-group mt-2"><button class="btn doc-action-btn is-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Record the hearing outcome?" data-dialog-message="The hearing will be marked Completed. The related case keeps its current status." data-dialog-details="<?= $details ?>" data-dialog-confirm="Record Outcome" data-dialog-dismiss="Cancel">Record Outcome</button></div>
                </form>
            </section>
        <?php endif; ?>
    </div>

    <div class="document-detail-info">
        <section class="dashboard-panel case-panel">
            <h2>Assigned Personnel</h2>
            <p class="document-info-note">Administrative record only — assignment does not grant access to case records.</p>
            <?php if ($assignments === []): ?><p class="resident-static">No personnel assigned.</p><?php else: ?>
                <ul class="case-link-list">
                    <?php foreach ($assignments as $assignment): ?>
                        <li class="<?= $assignment['removed_at'] ? 'is-removed' : '' ?>">
                            <div><strong><?= e($assignment['full_name']) ?></strong> <span class="activity-detail-muted"><?= e($assignment['assignment_role']) ?> · <?= e($assignment['position']) ?></span><br><span class="activity-detail-muted">Assigned <?= e(complaints_format_datetime($assignment['assigned_at'])) ?><?= $assignment['assigner_name'] ? ' by ' . e($assignment['assigner_name']) : '' ?><?php if ($assignment['removed_at']): ?> · Removed <?= e(complaints_format_datetime($assignment['removed_at'])) ?><?= $assignment['remover_name'] ? ' by ' . e($assignment['remover_name']) : '' ?> — <?= e($assignment['removal_reason']) ?><?php endif; ?></span></div>
                            <?php if ($is_active && !$assignment['removed_at']): ?>
                                <form method="post" action="hearing_action.php" class="doc-action-form"><?= $hidden ?><input type="hidden" name="action" value="remove_personnel"><input type="hidden" name="assignment_id" value="<?= e((string) $assignment['id']) ?>"><input type="hidden" name="reason" value=""><button class="btn doc-action-btn is-danger" type="submit" data-form-confirm="custom" data-dialog-heading="Remove this assignment?" data-dialog-message="The assignment ends now and remains in the assignment history." data-dialog-reason="Reason for removal" data-dialog-confirm="Remove Assignment" data-dialog-dismiss="Cancel" data-dialog-danger="true">Remove</button></form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($is_active && $available_personnel !== []): ?>
                <form class="case-inline-add" method="post" action="hearing_action.php"><?= $hidden ?><input type="hidden" name="action" value="assign_personnel">
                    <div><label class="form-label" for="assign-personnel">Assign personnel</label><select class="form-select" id="assign-personnel" name="personnel_id" required data-summary-label="Personnel"><option value="">Select…</option><?php foreach ($available_personnel as $person): ?><option value="<?= e((string) $person['id']) ?>"><?= e($person['full_name'] . ' — ' . $person['position']) ?></option><?php endforeach; ?></select></div>
                    <div><label class="form-label" for="assign-role">Assignment role</label><input class="form-control" id="assign-role" name="assignment_role" maxlength="80" required data-summary-label="Role"></div>
                    <div class="doc-action-group"><button class="btn doc-action-btn is-view" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="Assign this person?" data-dialog-message="The assignment is checked for schedule conflicts before saving." data-dialog-confirm="Assign" data-dialog-dismiss="Cancel">Assign</button></div>
                </form>
            <?php endif; ?>
        </section>

        <?php if ($is_active): ?>
            <section class="dashboard-panel case-panel" id="reschedule-panel">
                <h2>Reschedule Hearing</h2>
                <form method="post" action="hearing_action.php"><?= $hidden ?><input type="hidden" name="action" value="reschedule">
                    <div class="case-form-grid">
                        <div><label class="form-label" for="r-date">New date</label><input class="form-control" type="date" id="r-date" name="date" min="<?= e(date('Y-m-d')) ?>" value="<?= e(substr($hearing['starts_at'], 0, 10)) ?>" required data-summary-label="Date"></div>
                        <div><label class="form-label" for="r-venue">Venue</label><select class="form-select" id="r-venue" name="venue_id" required data-summary-label="Venue"><?php foreach ($venues as $venue): ?><option value="<?= e((string) $venue['id']) ?>" <?= (int) $venue['id'] === (int) $hearing['venue_id'] ? 'selected' : '' ?>><?= e($venue['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="case-time-pair"><div><label class="form-label" for="r-start">Start</label><input class="form-control" type="time" id="r-start" name="start_time" value="<?= e(substr($hearing['starts_at'], 11, 5)) ?>" required data-summary-label="Start"></div><div><label class="form-label" for="r-end">End</label><input class="form-control" type="time" id="r-end" name="end_time" value="<?= e(substr($hearing['ends_at'], 11, 5)) ?>" required data-summary-label="End"></div></div>
                        <div class="case-form-wide"><label class="form-label" for="r-reason">Reason for rescheduling <span class="activity-detail-muted">(required)</span></label><textarea class="form-control" id="r-reason" name="reason" rows="2" minlength="5" maxlength="1000" required></textarea></div>
                    </div>
                    <div class="doc-action-group mt-2"><button class="btn doc-action-btn is-primary" type="submit" data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="Reschedule this hearing?" data-dialog-message="The previous schedule is kept in the schedule history. Conflicts are checked before saving." data-dialog-confirm="Reschedule" data-dialog-dismiss="Cancel">Reschedule</button></div>
                </form>
            </section>
        <?php endif; ?>

        <section class="dashboard-panel case-panel">
            <h2>Schedule History</h2>
            <ol class="document-steps is-history">
                <?php foreach ($schedule_history as $entry): ?>
                    <li class="is-done"><strong><?= $entry['change_type'] === 'initial' ? 'Scheduled' : 'Rescheduled' ?></strong> · <?= e(complaints_format_datetime($entry['changed_at'])) ?><?= $entry['actor_name'] ? ' · ' . e($entry['actor_name']) : '' ?><br>
                        <span class="activity-detail-muted"><?php if ($entry['change_type'] === 'rescheduled'): ?>From <?= e(complaints_format_time_range($entry['previous_starts_at'], $entry['previous_ends_at'])) ?> at <?= e($entry['previous_venue']) ?> — to <?php endif; ?><?= e(complaints_format_time_range($entry['new_starts_at'], $entry['new_ends_at'])) ?> at <?= e($entry['new_venue']) ?><?= $entry['reason'] ? '. Reason: ' . e($entry['reason']) : '' ?></span></li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>
</div>
<?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
