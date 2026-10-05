<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_response.php';
// A disaster alert, opened from the notification bell. Alerts are public safety notices sent to every active account,
// so any signed-in user may read one (read-only). Disaster Management users also see who issued it and a link back.
require_auth();
$connection = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$alert = $id && disaster_response_ready($connection) ? disaster_alert_find($connection, $id) : null;
if ($alert === null) { http_response_code(404); exit('Alert not found.'); }
$areas = array_column(disaster_alert_areas($connection, (int) $alert['id']), 'name');
$status = disaster_alert_status($alert);
$staff = disaster_can_manage();
$page_title = 'Disaster Alert'; $active_page = $staff ? 'disaster' : '';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<article class="dashboard-panel resident-detail">
    <div class="page-heading">
        <div>
            <a class="announcement-back" href="<?= $staff ? 'disaster_alerts.php' : 'notifications.php' ?>"><span aria-hidden="true">&larr;</span> <?= $staff ? 'Back to Alerts' : 'Back to Notifications' ?></a>
            <span class="eyebrow">Disaster Alert</span>
            <h1><?= e($alert['title']) ?></h1>
            <p><?= disaster_alert_badge($alert['alert_level']) ?> <?= disaster_alert_status_badge($alert) ?> <span class="resident-detail-meta">Issued <?= e(disaster_format_datetime($alert['created_at'])) ?></span></p>
        </div>
    </div>
    <?php if ($status === 'withdrawn'): ?><p class="dashboard-status warning" role="status">This alert was withdrawn by the barangay. Please disregard it.</p><?php elseif ($status === 'lifted'): ?><p class="dashboard-status" role="status">This alert was lifted on <?= e(disaster_format_datetime($alert['lifted_at'])) ?>. Follow further instructions from the barangay.</p><?php endif; ?>
    <div class="drr-alert-card level-<?= e($alert['alert_level']) ?>">
        <p class="drr-alert-meta">Affected areas: <strong><?= e(implode(', ', $areas)) ?></strong></p>
        <p class="resident-wrap"><?= nl2br(e($alert['message'])) ?></p>
        <?php if ($staff): ?><p class="drr-alert-meta"><?= $alert['reference_no'] ? 'Incident ' . e($alert['reference_no'] . ' — ' . $alert['incident_title']) . ' · ' : '' ?>Issued by <?= e((string) ($alert['created_by_name'] ?? 'unavailable account')) ?> · Sent to <?= e((string) $alert['recipients']) ?> accounts</p><?php endif; ?>
    </div>
</article>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
