<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/audit_logs.php';
audit_require_admin();
$connection = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$row = $id ? audit_find($connection, $id) : null;
if ($row === null) { http_response_code(404); exit('Audit record not found.'); }

$details = audit_visible_details($row);   // finance amounts and old/new values are not shown here
$format = static function ($value): string {
    if (is_bool($value)) return $value ? 'Yes' : 'No';
    if (is_array($value)) return array_is_list($value) ? implode(', ', array_map(static fn ($v): string => is_scalar($v) ? str_replace('_', ' ', (string) $v) : (string) json_encode($v), $value)) : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return str_replace('_', ' ', (string) $value);
};
$muted = '<span class="activity-detail-muted">Not recorded</span>';
$page_title = 'Audit Record'; $active_page = 'audit';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<article class="dashboard-panel resident-detail">
    <a class="announcement-back" href="audit_logs.php"><span aria-hidden="true">&larr;</span> Back to Audit Logs</a>
    <div class="page-heading"><div><span class="eyebrow">Audit record #<?= e((string) $row['id']) ?></span><h1><?= e(audit_action_label($row['action'])) ?></h1><p><?= audit_result_badge($row['action']) ?> <span class="resident-detail-meta"><?= e(audit_format_datetime((string) $row['created_at'])) ?></span></p></div></div>
    <div class="resident-detail-grid">
        <section class="resident-detail-section">
            <h2>Who and When</h2>
            <dl class="activity-detail-list">
                <div><dt>User</dt><dd><?= e(audit_actor($row)) ?></dd></div>
                <div><dt>Email</dt><dd><?= $row['user_email'] ? e($row['user_email']) : $muted ?></dd></div>
                <div><dt>Role</dt><dd><?= $row['user_role'] ? e(audit_role_label($row['user_role'])) : $muted ?></dd></div>
                <div><dt>Date and time</dt><dd><?= e(audit_format_datetime((string) $row['created_at'])) ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Where From</h2>
            <dl class="activity-detail-list">
                <div><dt>IP address</dt><dd><?= $row['ip_address'] ? e($row['ip_address']) : $muted ?></dd></div>
                <div><dt>Browser / device</dt><dd class="resident-wrap"><?= $row['user_agent'] ? e($row['user_agent']) : $muted ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>What Happened</h2>
            <dl class="activity-detail-list">
                <div><dt>Action</dt><dd><?= e(audit_action_label($row['action'])) ?></dd></div>
                <div><dt>Action code</dt><dd><code><?= e($row['action']) ?></code></dd></div>
                <div><dt>Category</dt><dd><?= e(audit_category_of($row)) ?></dd></div>
                <div><dt>Record</dt><dd><?= $row['entity_type'] ? e($row['entity_type'] . ($row['entity_id'] !== null ? ' #' . $row['entity_id'] : '')) : $muted ?></dd></div>
                <div><dt>Result</dt><dd><?= audit_result_badge($row['action']) ?></dd></div>
            </dl>
        </section>
        <section class="resident-detail-section">
            <h2>Recorded Details</h2>
            <?php if ($details === []): ?>
                <p class="resident-static">No additional details were recorded.</p>
            <?php else: ?>
                <dl class="activity-detail-list">
                    <?php foreach ($details as $key => $value): ?><div><dt><?= e(ucfirst(str_replace('_', ' ', (string) $key))) ?></dt><dd class="resident-wrap"><?= e($format($value)) ?></dd></div><?php endforeach; ?>
                </dl>
            <?php endif; ?>
            <p class="document-info-note">Passwords, access codes and confidential case content are never recorded.</p>
        </section>
    </div>
</article>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
