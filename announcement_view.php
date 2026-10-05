<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/announcements.php';
require_once __DIR__ . '/includes/notifications.php';
require_auth();
require_once __DIR__ . '/includes/navigation.php';
if (!can_access_navigation('announcements')) { http_response_code(403); exit('Access denied.'); }
$id =filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$record = $id ? announcements_find(db(), $id) : null;
if (!$record || ($record['status'] !== 'published' && !announcements_can_manage())) { http_response_code(404); exit('Announcement not found.'); }
$notification_id = filter_var($_GET['notification_id'] ?? null, FILTER_VALIDATE_INT);
if ($notification_id && $id) {
	mark_announcement_notification_read($notification_id, $id);
}
$page_title = 'Announcement Details'; $active_page = 'announcements';
require __DIR__ . '/layout/header.php';
?><div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?><main class="content"><article class="dashboard-panel announcement-detail"><div class="page-heading"><div><a class="announcement-back" href="announcements.php"><span aria-hidden="true">&larr;</span> Back</a><span class="eyebrow"><?= e(announcements_audience_label($record['audience'])) ?> · <?= e(ucwords($record['status'])) ?></span><h1><?= e($record['title']) ?></h1><p><?= e($record['status'] === 'published' ? 'Published ' . announcements_format_date($record['published_at']) : 'Created ' . announcements_format_date($record['created_at'])) ?><?php if (announcements_can_manage()): ?> · Author: <?= e($record['author_name']) ?><?php endif; ?></p></div><?php if (announcements_can_manage() && $record['status'] !== 'archived'): ?><a class="btn announcement-edit-btn" href="announcement_form.php?id=<?= e((string) $record['id']) ?>">Edit</a><?php endif; ?><?php if (announcements_can_manage() && in_array($record['status'], ['published', 'archived'], true)): ?><form method="post" action="announcement_resend_sms.php" style="display:inline" onsubmit="return confirm('Resend SMS notification for this announcement to all eligible residents?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $record['id']) ?>"><button class="btn btn-outline-success" type="submit">Resend SMS</button></form><?php endif; ?></div><div class="announcement-body"><?= nl2br(e($record['body'])) ?></div></article></main><?php require __DIR__ . '/layout/footer.php'; ?></div></div>
