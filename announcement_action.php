<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/announcements.php';
require_once __DIR__ . '/includes/sms.php';
announcements_require_manage();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) { http_response_code(400); exit('Invalid request.'); }
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT); $action = (string) ($_POST['action'] ?? ''); if (!$id || !in_array($action, ['publish', 'archive', 'unarchive', 'delete'], true)) { http_response_code(400); exit('Invalid request.'); }
$connection = db();
// Confirmed list actions. The row is locked and its status re-read inside the transaction, so a confirmation opened on a stale page cannot perform a transition that is no longer valid.
// Every outcome redirects (Post/Redirect/Get) with a flash message, so refreshing the destination never repeats the action.
$required_status = ['publish' => 'draft', 'archive' => 'published', 'unarchive' => 'archived', 'delete' => 'archived'][$action];
$return_tab = ['publish' => 'drafts', 'archive' => 'published', 'unarchive' => 'archived', 'delete' => 'archived'][$action];
$fail = static function (string $message) use ($connection, $return_tab): never { if ($connection->inTransaction()) $connection->rollBack(); flash('announcement_error', $message); redirect('announcements.php?tab=' . $return_tab); };
try {
    $connection->beginTransaction();
    $statement = $connection->prepare('SELECT id, title, body, audience, target_purok, status, published_at' . (announcements_category_ready($connection) ? ', category' : '') . ' FROM announcements WHERE id = :id LIMIT 1 FOR UPDATE'); $statement->execute(['id' => $id]); $record = $statement->fetch();
    if (!$record || $record['status'] !== $required_status) $fail('This announcement is no longer available for that action. The list has been refreshed.');
    // Health Workers act only on Health announcements, whatever the posted ID.
    if (announcements_health_only() && ($record['category'] ?? 'general') !== 'health') $fail('This announcement is no longer available for that action. The list has been refreshed.');
    if ($action === 'publish') {
        if (announcements_validate($record)['errors'] !== []) $fail('This draft cannot be published because its content is incomplete. Edit the draft and try again.');
        $update = $connection->prepare("UPDATE announcements SET status = 'published', published_at = :published_at WHERE id = :id AND status = 'draft'"); $update->execute(['published_at' => date('Y-m-d H:i:s'), 'id' => $id]);
        if ($update->rowCount() !== 1) $fail('The announcement could not be published.');
        $record['status'] = 'published';
        announcements_history($connection, $id, 'published', 'draft', 'published'); announcements_audit($connection, $id, 'announcement_published');
        // Same transaction: notifications exist only if the publication commits; the unique key prevents duplicate rows per recipient.
        create_announcement_notifications($connection, $record);
        $message = 'Announcement published successfully.';
    } elseif ($action === 'archive') {
        $update = $connection->prepare("UPDATE announcements SET status = 'archived' WHERE id = :id AND status = 'published'"); $update->execute(['id' => $id]);
        if ($update->rowCount() !== 1) $fail('The announcement could not be archived.');
        announcements_history($connection, $id, 'archived', 'published', 'archived'); announcements_audit($connection, $id, 'announcement_archived');
        $message = 'Announcement archived successfully.';
    } elseif ($action === 'unarchive') {
        $restore = announcements_status_before_archive($connection, $record);
        if ($restore === null) $fail('The previous status of this announcement cannot be determined, so it was not unarchived.');
        // published_at is deliberately left untouched, and no notifications are created: recipients were already notified when it was first published.
        $update = $connection->prepare("UPDATE announcements SET status = :status WHERE id = :id AND status = 'archived'"); $update->execute(['status' => $restore, 'id' => $id]);
        if ($update->rowCount() !== 1) $fail('The announcement could not be unarchived.');
        announcements_history($connection, $id, $restore === 'published' ? 'published' : 'unpublished', 'archived', $restore, 'Unarchived');
        announcements_audit($connection, $id, 'announcement_unarchived', ['restored_status' => $restore]);
        $message = 'Announcement unarchived successfully.';
    } else {
        // audit_logs has no foreign key to announcements, so this entry outlives the row; announcement_history and announcement_notifications rows cascade by schema design.
        announcements_audit($connection, $id, 'announcement_deleted', ['title' => $record['title'], 'status' => 'archived', 'published_at' => $record['published_at']]);
        $delete = $connection->prepare("DELETE FROM announcements WHERE id = :id AND status = 'archived'"); $delete->execute(['id' => $id]);
        if ($delete->rowCount() !== 1) $fail('The announcement could not be deleted.');
        $message = 'Announcement deleted successfully.';
    }
    $connection->commit();
} catch (Throwable) { $fail('The announcement action failed. No changes were saved.'); }
// Fire SMS notification after the transaction is committed so a slow or
// failing API call cannot roll back the publish. sms_send_announcement()
// is fire-and-forget and never throws.
if ($action === 'publish') {
    sms_send_announcement($connection, $record);
}
flash('announcement_success', $message);
redirect('announcements.php?tab=' . $return_tab);
