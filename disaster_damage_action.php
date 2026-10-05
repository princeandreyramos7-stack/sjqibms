<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_recovery.php';
disaster_require_manage();
$connection = db();

// Archive (soft delete) and Restore of damage assessments. POST only, with CSRF.
$return = (string) ($_POST['return'] ?? '');
$back = 'disaster_damage.php' . (preg_match('/^[A-Za-z0-9_=&%+.\-]{1,300}$/', $return) ? '?' . $return : '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('disaster_damage.php');
if (!disaster_recovery_ready($connection)) { flash('disaster_error', 'Damage assessment needs its database tables first.'); redirect('disaster_damage.php'); }
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('disaster_error', 'Your session expired. Please try again.'); redirect($back); }

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$action = (string) ($_POST['action'] ?? '');
try {
    $connection->beginTransaction();
    $record = disaster_damage_find($connection, $id, true);
    if ($record === null) throw new RuntimeException('The assessment no longer exists.');
    $user = current_user()['id'];
    if ($action === 'archive') {
        if ($record['archived_at'] !== null) throw new RuntimeException('This assessment is already archived.');
        $connection->prepare('UPDATE drr_damage_assessments SET archived_at = NOW(), archived_by = :user, updated_by = :updater WHERE id = :id')->execute(['user' => $user, 'updater' => $user, 'id' => $id]);
        residents_audit($connection, 'disaster_damage', $id, 'disaster_damage_archived', ['reference' => $record['reference_no'], 'name' => $record['area_name']]);
        $message = 'The assessment for ' . $record['area_name'] . ' was archived.';
    } elseif ($action === 'restore') {
        if ($record['archived_at'] === null) throw new RuntimeException('This assessment is not archived.');
        $duplicate = $connection->prepare('SELECT COUNT(*) FROM drr_damage_assessments WHERE incident_id = :incident AND area_id = :area AND archived_at IS NULL AND id <> :id');
        $duplicate->execute(['incident' => $record['incident_id'], 'area' => $record['area_id'], 'id' => $id]);
        if ((int) $duplicate->fetchColumn() > 0) throw new RuntimeException('This area already has a current assessment for this incident. Archive that one first.');
        $connection->prepare('UPDATE drr_damage_assessments SET archived_at = NULL, archived_by = NULL, updated_by = :user WHERE id = :id')->execute(['user' => $user, 'id' => $id]);
        residents_audit($connection, 'disaster_damage', $id, 'disaster_damage_restored', ['reference' => $record['reference_no'], 'name' => $record['area_name']]);
        $message = 'The assessment for ' . $record['area_name'] . ' was restored.';
    } else {
        throw new RuntimeException('This action is not available.');
    }
    $connection->commit();
    flash('disaster_success', $message);
} catch (PDOException) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('disaster_error', 'The assessment could not be updated. No changes were made.');
} catch (RuntimeException $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('disaster_error', $exception->getMessage());
}
redirect($back);
