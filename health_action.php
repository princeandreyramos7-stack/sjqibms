<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health.php';
health_require_manage();
$connection = db();

// Archive (soft delete) and Restore. POST only, with CSRF.
$return = (string) ($_POST['return'] ?? '');
$back = (string) ($_POST['back'] ?? '') === 'view' ? 'health_view.php?id=' . (int) ($_POST['id'] ?? 0) : 'health.php' . (preg_match('/^[A-Za-z0-9_=&%+.\-]{1,300}$/', $return) ? '?' . $return : '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('health.php');
if (!health_ready($connection)) { flash('health_error', 'Health records need their database table first.'); redirect('health.php'); }
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('health_error', 'Your session expired. Please try again.'); redirect($back); }

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$action = (string) ($_POST['action'] ?? '');
try {
    $connection->beginTransaction();
    $record = health_find($connection, $id, true);
    if ($record === null) throw new RuntimeException('The record no longer exists.');
    if ($action === 'archive') {
        if ($record['archived_at'] !== null) throw new RuntimeException('This record is already archived.');
        $connection->prepare('UPDATE health_records SET archived_at = NOW(), archived_by = :user, updated_by = :updater WHERE id = :id')->execute(['user' => current_user()['id'], 'updater' => current_user()['id'], 'id' => $id]);
        health_audit($connection, $id, 'health_record_archived', ['record_no' => $record['record_no']]);
        $message = $record['record_no'] . ' was archived. It can be restored from the Archived status filter.';
    } elseif ($action === 'restore') {
        if ($record['archived_at'] === null) throw new RuntimeException('This record is not archived.');
        $connection->prepare('UPDATE health_records SET archived_at = NULL, archived_by = NULL, updated_by = :user WHERE id = :id')->execute(['user' => current_user()['id'], 'id' => $id]);
        health_audit($connection, $id, 'health_record_restored', ['record_no' => $record['record_no']]);
        $message = $record['record_no'] . ' was restored.';
    } else {
        throw new RuntimeException('This action is not available.');
    }
    $connection->commit();
    flash('health_success', $message);
} catch (PDOException) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('health_error', 'The record could not be updated. No changes were made.');
} catch (RuntimeException $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('health_error', $exception->getMessage());
}
redirect($back);
