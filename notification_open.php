<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/complaints.php';
require_once __DIR__ . '/includes/notifications.php';
// Opens a private user notification: only its recipient can open it; it is marked read and the user is sent to the
// related page, which applies its own access checks (a notification never grants access).
require_auth();
$connection = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$statement = $connection->prepare('SELECT id, entity_type, entity_id FROM user_notifications WHERE id = :id AND recipient_user_id = :user');
$statement->execute(['id' => $id ?: 0, 'user' => current_user()['id']]);
$notification = $statement->fetch();
if (!$notification) redirect('notifications.php');
$connection->prepare('UPDATE user_notifications SET read_at = COALESCE(read_at, NOW()) WHERE id = :id AND recipient_user_id = :user')->execute(['id' => $notification['id'], 'user' => current_user()['id']]);

$target = 'notifications.php';
$entity_id = (int) $notification['entity_id'];
if ($notification['entity_type'] === 'hearing_schedule') {
    $lookup = $connection->prepare('SELECT hearing_id FROM case_hearing_schedule_history WHERE id = :id');
    $lookup->execute(['id' => $entity_id]);
    $entity_id = (int) $lookup->fetchColumn();
}
if ($notification['entity_type'] === 'finance_transaction') {
    $target = 'finance_view.php?id=' . $entity_id;   // finance_view.php re-checks the Treasurer / Punong Barangay role
} elseif ($notification['entity_type'] === 'disaster_alert') {
    $target = 'disaster_alert_view.php?id=' . $entity_id;   // public safety notice: readable by every signed-in user
} elseif ($notification['entity_type'] === 'complaint') {
    $target = complaints_can_manage() ? 'complaint_view.php?id=' . $entity_id : (has_role('resident') ? 'complaint_view.php?id=' . $entity_id : 'notifications.php');
} elseif (in_array($notification['entity_type'], ['hearing', 'hearing_schedule'], true) && $entity_id > 0) {
    if (complaints_can_manage()) {
        $target = 'hearing_view.php?id=' . $entity_id;
    } elseif (has_role('resident')) {
        // Residents follow the hearing through their own complaint (if it is theirs); otherwise the list.
        $lookup = $connection->prepare('SELECT complaint_id FROM case_hearings WHERE id = :id');
        $lookup->execute(['id' => $entity_id]);
        $complaint_id = (int) $lookup->fetchColumn();
        $complaint = $complaint_id ? complaints_find($connection, $complaint_id) : null;
        if ($complaint && complaints_resident_owns($connection, $complaint)) $target = 'complaint_view.php?id=' . $complaint_id;
    }
}
if ($target === 'notifications.php') flash('notification_info', 'The details of this update are available at the Barangay Hall.');
redirect($target);
