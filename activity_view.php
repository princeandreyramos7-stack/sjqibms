<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_auth();
require_once __DIR__ . '/includes/activity.php';
// The System Administrator reads the same record in Audit Logs.
if (has_role('super_admin')) {
    $audit_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    redirect($audit_id ? 'audit_log_view.php?id=' . $audit_id : 'audit_logs.php');
}
if (!role_can('activity.view')) {
    http_response_code(403);
    exit('Access denied.');
}

// Resolve and validate the requested ID
$raw_id = $_GET['id'] ?? '';
$activity_id = filter_var($raw_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$activity = null;
$load_error = null;

if ($activity_id === false || $activity_id === null) {
    $load_error = 'not_found';
} else {
    try {
        $activity = activity_find(db(), (int) $activity_id);
    } catch (PDOException) {
        $load_error = 'unavailable';
    }
    if ($activity === null && $load_error === null) {
        $load_error = 'not_found';
    }
}

// Safe entity link for admin users only — no raw JSON, IPs, user agents, or password data
$entity_link = null;
// A deleted announcement no longer exists, so it is shown as read-only text instead of a link.
$entity_deleted = $activity !== null && $activity['action_key'] === 'announcement_deleted';
if ($activity !== null && !$entity_deleted && activity_is_admin() && isset($activity['entity_id']) && $activity['entity_id'] !== null) {
    $entity_link = match ($activity['module']) {
        'announcements' => 'announcement_view.php?id=' . (int) $activity['entity_id'],
        'residents'     => 'resident_view.php?id=' . (int) $activity['entity_id'],
        'households'    => 'household_view.php?id=' . (int) $activity['entity_id'],
        'documents'     => 'document_view.php?id=' . (int) $activity['entity_id'],
        // Complaints module: the audit action identifies the record kind (the target page re-checks access).
        'complaints'    => match (true) {
            str_starts_with($activity['action_key'], 'complaint_') => 'complaint_view.php?id=' . (int) $activity['entity_id'],
            str_starts_with($activity['action_key'], 'blotter_') => 'blotter_view.php?id=' . (int) $activity['entity_id'],
            str_starts_with($activity['action_key'], 'hearing_') => 'hearing_view.php?id=' . (int) $activity['entity_id'],
            str_starts_with($activity['action_key'], 'personnel_') => 'barangay_personnel.php',
            str_starts_with($activity['action_key'], 'venue_') => 'barangay_personnel.php?tab=venues',
            default => null,
        },
        default         => null,
    };
}

$page_title = 'Activity Details';
$active_page = 'activity';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/layout/sidebar.php'; ?>
    <div class="app-main">
        <?php require __DIR__ . '/layout/topbar.php'; ?>
        <main class="content">

            <div class="page-heading">
                <div>
                    <h1>Activity Details</h1>
                    <p>Read-only view of a single audit log record.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="activity_history.php">&larr; Back to Activity History</a>
            </div>

            <?php if ($load_error === 'not_found'): ?>
                <div class="activity-detail-panel dashboard-panel">
                    <div class="activity-detail-empty">
                        <div class="empty-state">
                            <div class="empty-state-icon"><?= icon_svg('history') ?></div>
                            <h2>Activity not found</h2>
                            <p>This record does not exist or you do not have permission to view it.</p>
                        </div>
                    </div>
                </div>

            <?php elseif ($load_error === 'unavailable'): ?>
                <div class="dashboard-status warning" role="status">Activity details are temporarily unavailable. Please try again.</div>

            <?php else: ?>
                <div class="activity-detail-panel dashboard-panel">
                    <div class="activity-detail-heading">
                        <span class="activity-detail-icon"><?= icon_svg($activity['icon']) ?></span>
                        <div>
                            <h2 class="activity-detail-title"><?= e(ucfirst($activity['action'])) ?></h2>
                            <p class="activity-detail-subtitle"><?= e($activity['module_label']) ?></p>
                        </div>
                    </div>

                    <dl class="activity-detail-list">
                        <div>
                            <dt>Action</dt>
                            <dd><?= e(ucfirst($activity['action'])) ?></dd>
                        </div>
                        <div>
                            <dt>Performed by</dt>
                            <dd><?= e($activity['actor']) ?></dd>
                        </div>
                        <div>
                            <dt>Module</dt>
                            <dd><?= e($activity['module_label']) ?></dd>
                        </div>
                        <div>
                            <dt>Date &amp; Time</dt>
                            <dd><time datetime="<?= e($activity['created_at']) ?>"><?= e($activity['date']) ?></time></dd>
                        </div>
                        <?php if ($entity_link !== null): ?>
                            <div>
                                <dt>Related Record</dt>
                                <dd><a class="activity-detail-link" href="<?= e($entity_link) ?>">View <?= e($activity['module_label']) ?> record #<?= e((string) $activity['entity_id']) ?></a></dd>
                            </div>
                        <?php elseif (activity_is_admin() && isset($activity['entity_id']) && $activity['entity_id'] !== null): ?>
                            <div>
                                <dt>Related Record</dt>
                                <?php if ($entity_deleted): ?>
                                    <dd class="activity-detail-muted">Deleted announcement #<?= e((string) $activity['entity_id']) ?></dd>
                                <?php else: ?>
                                    <dd class="activity-detail-muted"><?= e($activity['module_label']) ?> record #<?= e((string) $activity['entity_id']) ?></dd>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </dl>

                    <div class="activity-detail-footer">
                        <a class="btn btn-outline-secondary btn-sm" href="activity_history.php">&larr; Back to Activity History</a>
                    </div>
                </div>
            <?php endif; ?>

        </main>
        <?php require __DIR__ . '/layout/footer.php'; ?>
    </div>
</div>
