<?php
require_once __DIR__ . '/../includes/notifications.php';
$notifications = notifications_for_current_user();
$unread_notifications = unread_notifications_count();
?>
<header class="topbar">
    <button class="menu-toggle" type="button" aria-label="Open navigation" data-sidebar-toggle>☰</button>
    <div class="topbar-context">
        <span class="eyebrow">Barangay San Jose</span>
        <strong><?= e($page_title ?? 'Dashboard') ?></strong><span class="local-clock" data-local-clock></span>
    </div>
    <div class="topbar-actions">
        <div class="notification-wrap">
            <button class="icon-button" type="button" aria-label="Notifications" aria-expanded="false" data-notification-toggle><?= icon_svg('megaphone') ?><span class="notification-count" <?= $unread_notifications > 0 ? '' : 'hidden' ?>><?= e($unread_notifications) ?></span></button>
            <div class="notification-dropdown" data-notification-dropdown>
                <div class="dropdown-heading"><strong>Notifications</strong><a href="notifications.php">View all</a></div>
                <?php if ($notifications === []): ?><p class="notification-empty">You have no unread notifications.</p><?php else: ?><?php foreach ($notifications as $notification): ?><a class="notification-item is-unread" href="<?= e($notification['href']) ?>"><strong><?= e($notification['title']) ?></strong><span><?= e($notification['message']) ?></span></a><?php endforeach; ?><?php endif; ?>
            </div>
        </div>
        <a class="user-chip" href="profile.php" aria-label="Open profile">
            <div class="avatar"><?= e(strtoupper(substr(current_user()['name'] ?? 'A', 0, 1))) ?></div>
            <div class="user-details"><strong><?= e(current_user()['name'] ?? 'Administrator') ?></strong><span><?= e(role_title()) ?></span></div>
        </a>
        <a class="logout-link" href="logout.php">Sign out</a>
    </div>
</header>
