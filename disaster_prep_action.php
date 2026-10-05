<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_prep.php';
disaster_require_manage();
$connection = db();

// Archive (soft delete) and Restore of evacuation centers, hazard-prone areas and contacts. POST only, with CSRF.
$kinds = disaster_prep_kinds();
$kind = (string) ($_POST['kind'] ?? '');
if (!isset($kinds[$kind])) redirect('disaster.php');
$return = (string) ($_POST['return'] ?? '');
$back = $kinds[$kind]['list'] . (preg_match('/^[A-Za-z0-9_=&%+.\-]{1,300}$/', $return) ? '?' . $return : '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect($kinds[$kind]['list']);
if (!disaster_prep_ready($connection)) { flash('disaster_error', 'Disaster Management needs its database tables first.'); redirect($kinds[$kind]['list']); }
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('disaster_error', 'Your session expired. Please try again.'); redirect($back); }

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$action = (string) ($_POST['action'] ?? '');
$table = $kinds[$kind]['table'];
try {
    $connection->beginTransaction();
    $record = disaster_prep_find($connection, $kind, $id, true);
    if ($record === null) throw new RuntimeException('The record no longer exists.');
    $label = $kind === 'hazard' ? $record['area_name'] . ' — ' . disaster_hazard_label($record) : $record['name'];
    $user = current_user()['id'];
    if ($action === 'archive') {
        if ($record['archived_at'] !== null) throw new RuntimeException('This record is already archived.');
        $connection->prepare("UPDATE $table SET archived_at = NOW(), archived_by = :user, updated_by = :updater WHERE id = :id")->execute(['user' => $user, 'updater' => $user, 'id' => $id]);
        disaster_prep_audit($connection, $kind, $id, $kinds[$kind]['audit'] . '_archived', ['name' => $label]);
        $message = $label . ' was archived. It can be restored from the Archived filter.';
    } elseif ($action === 'restore') {
        if ($record['archived_at'] === null) throw new RuntimeException('This record is not archived.');
        if ($kind === 'hazard') {
            $duplicate = $connection->prepare('SELECT COUNT(*) FROM drr_hazard_areas WHERE area_id = :area AND hazard_type = :type AND COALESCE(hazard_other, \'\') = :other AND archived_at IS NULL AND id <> :id');
            $duplicate->execute(['area' => $record['area_id'], 'type' => $record['hazard_type'], 'other' => (string) $record['hazard_other'], 'id' => $id]);
            if ((int) $duplicate->fetchColumn() > 0) throw new RuntimeException('This hazard is already recorded for this area. Edit the current entry instead.');
        }
        $connection->prepare("UPDATE $table SET archived_at = NULL, archived_by = NULL, updated_by = :user WHERE id = :id")->execute(['user' => $user, 'id' => $id]);
        disaster_prep_audit($connection, $kind, $id, $kinds[$kind]['audit'] . '_restored', ['name' => $label]);
        $message = $label . ' was restored.';
    } else {
        throw new RuntimeException('This action is not available.');
    }
    $connection->commit();
    flash('disaster_success', $message);
} catch (PDOException $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('disaster_error', ($exception->errorInfo[1] ?? 0) === 1062 ? 'Another record with the same name exists. Rename it before restoring this one.' : 'The record could not be updated. No changes were made.');
} catch (RuntimeException $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('disaster_error', $exception->getMessage());
}
redirect($back);
