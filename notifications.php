<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';
require_auth();

$notifications = notifications_for_current_user(50);
$page_title = 'Notifications';
$active_page = '';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/layout/sidebar.php'; ?>
    <div class="app-main">
        <?php require __DIR__ . '/layout/topbar.php'; ?>
        <main class="content">
            <div class="page-heading"><div><h1>Notifications</h1><p>Recent updates belonging to your account.</p></div></div>
            <?php if ($info = flash('notification_info')): ?><div class="dashboard-status" role="status"><?= e($info) ?></div><?php endif; ?>
            <section class="dashboard-panel notifications-page">
                <?php if ($notifications === []): ?><div class="empty-state"><div class="empty-state-icon"><?= icon_svg('megaphone') ?></div><h2>No unread notifications</h2><p>Read notifications remain stored but no longer appear in this list.</p></div><?php else: ?><?php foreach ($notifications as $notification): ?><a class="notification-row is-unread" href="<?= e($notification['href']) ?>"><div><h2><?= e($notification['title']) ?></h2><p><?= e($notification['message']) ?></p></div><time><?= e($notification['created_at']) ?></time></a><?php endforeach; ?><?php endif; ?>
            </section>
        </main>
        <?php require __DIR__ . '/layout/footer.php'; ?>
    </div>
</div>
