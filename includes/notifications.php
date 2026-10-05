<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/roles.php';

function notification_visibility_clause(array &$params): string
{
    $user = current_user();
    if (in_array($user['role'] ?? '', ['super_admin', 'secretary'], true)) {
        return "a.audience <> 'selected_users'";
    }

    $params[':published_status'] = 'published';
    $params[':public_audience'] = 'public';
    $params[':all_audience'] = 'all_residents';
    $params[':purok_audience'] = 'purok';
    // Staff-only audiences (Kagawads; All Barangay Staff) for the signed-in role only.
    $staff = '';
    foreach (role_staff_announcement_audiences() as $index => $audience) {
        $params[':staff_audience_' . $index] = $audience;
        $staff .= ', :staff_audience_' . $index;
    }
    return "a.status = :published_status AND (a.audience IN (:public_audience, :all_audience$staff) OR (a.audience = :purok_audience AND r.status = 'active' AND r.purok IS NOT NULL AND r.purok <> '' AND r.purok = a.target_purok))";
}

// Unread notifications for the signed-in user: announcement notifications (visibility rules above) plus private,
// recipient-only user_notifications (complaints, hearings). Each item carries its own safe link ('href').
function notifications_for_current_user(int $limit = 5): array
{
    $notifications = [];
    try {
        $params = [':user_id' => current_user()['id']];
        $visibility = notification_visibility_clause($params);
        $statement = db()->prepare("SELECT n.id, n.announcement_id, n.title, n.message, n.created_at, n.read_at, a.status AS announcement_status FROM announcement_notifications n INNER JOIN announcements a ON a.id = n.announcement_id INNER JOIN users u ON u.id = n.recipient_user_id LEFT JOIN residents r ON r.id = u.resident_id WHERE n.recipient_user_id = :user_id AND n.read_at IS NULL AND $visibility ORDER BY n.created_at DESC LIMIT $limit");
        $statement->execute($params);
        foreach ($statement->fetchAll() as $row) {
            $row['is_read'] = false;
            $row['href'] = 'announcement_view.php?id=' . (int) $row['announcement_id'] . '&notification_id=' . (int) $row['id'];
            $notifications[] = $row;
        }
    } catch (PDOException) {
    }
    try {
        $statement = db()->prepare("SELECT id, title, message, created_at FROM user_notifications WHERE recipient_user_id = :user_id AND read_at IS NULL ORDER BY created_at DESC, id DESC LIMIT $limit");
        $statement->execute(['user_id' => current_user()['id']]);
        foreach ($statement->fetchAll() as $row) {
            $row['is_read'] = false;
            $row['href'] = 'notification_open.php?id=' . (int) $row['id'];
            $notifications[] = $row;
        }
    } catch (PDOException) {
        // user_notifications not installed: announcement notifications still work.
    }
    usort($notifications, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
    return array_slice($notifications, 0, $limit);
}

function unread_notifications_count(): int
{
    $count = 0;
    try {
        $params = [':user_id' => current_user()['id']];
        $visibility = notification_visibility_clause($params);
        $statement = db()->prepare("SELECT COUNT(*) FROM announcement_notifications n INNER JOIN announcements a ON a.id = n.announcement_id INNER JOIN users u ON u.id = n.recipient_user_id LEFT JOIN residents r ON r.id = u.resident_id WHERE n.recipient_user_id = :user_id AND n.read_at IS NULL AND $visibility");
        $statement->execute($params);
        $count += (int) $statement->fetchColumn();
    } catch (PDOException) {
    }
    try {
        $statement = db()->prepare('SELECT COUNT(*) FROM user_notifications WHERE recipient_user_id = :user_id AND read_at IS NULL');
        $statement->execute(['user_id' => current_user()['id']]);
        $count += (int) $statement->fetchColumn();
    } catch (PDOException) {
    }
    return $count;
}

function mark_announcement_notification_read(int $notification_id, int $announcement_id): void
{
    $statement = db()->prepare('UPDATE announcement_notifications SET read_at = COALESCE(read_at, NOW()) WHERE id = :id AND announcement_id = :announcement_id AND recipient_user_id = :user_id');
    $statement->execute([
        'id' => $notification_id,
        'announcement_id' => $announcement_id,
        'user_id' => current_user()['id'],
    ]);
}
