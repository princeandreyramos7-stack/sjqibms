<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_response.php';
disaster_require_manage();
$connection = db();

// Check-out (the family leaves the center now) and removal of a check-in made by mistake (archived, kept). Both free
// the family's space; a Full center becomes Open again when it has space. POST only, with CSRF.
$return = (string) ($_POST['return'] ?? '');
$back = 'disaster_evacuation.php' . (preg_match('/^[A-Za-z0-9_=&%+.\-]{1,300}$/', $return) ? '?' . $return : '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('disaster_evacuation.php');
if (!disaster_response_ready($connection)) { flash('disaster_error', 'Evacuation tracking needs its database tables first.'); redirect('disaster_evacuation.php'); }
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('disaster_error', 'Your session expired. Please try again.'); redirect($back); }

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$action = (string) ($_POST['action'] ?? '');
try {
    $connection->beginTransaction();
    $entry = disaster_evacuation_find($connection, $id, true);
    if ($entry === null) throw new RuntimeException('The check-in no longer exists.');
    if ($entry['archived_at'] !== null) throw new RuntimeException('This check-in was already removed.');
    $center = disaster_center_lock($connection, (int) $entry['center_id']);
    $user = current_user()['id'];
    if ($action === 'checkout') {
        if ($entry['departed_at'] !== null) throw new RuntimeException('This family was already checked out.');
        $connection->prepare('UPDATE drr_evacuations SET departed_at = GREATEST(NOW(), arrived_at), updated_by = :user WHERE id = :id')->execute(['user' => $user, 'id' => $id]);
        residents_audit($connection, 'disaster_evacuation', $id, 'disaster_evacuee_checked_out', ['name' => $entry['family_label'], 'center' => $entry['center_name'], 'persons' => (int) $entry['family_members']]);
        $message = $entry['family_label'] . ' was checked out of ' . $entry['center_name'] . '.';
    } elseif ($action === 'archive') {
        $connection->prepare('UPDATE drr_evacuations SET archived_at = NOW(), archived_by = :user, updated_by = :updater WHERE id = :id')->execute(['user' => $user, 'updater' => $user, 'id' => $id]);
        residents_audit($connection, 'disaster_evacuation', $id, 'disaster_evacuee_removed', ['name' => $entry['family_label'], 'center' => $entry['center_name']]);
        $message = 'The check-in of ' . $entry['family_label'] . ' was removed. It is kept under "Removed entries".';
    } else {
        throw new RuntimeException('This action is not available.');
    }
    $status = $center && $center['archived_at'] === null ? disaster_apply_capacity_rule($connection, $center) : null;
    $connection->commit();
    flash('disaster_success', $message . ($status === 'open' ? ' ' . $center['name'] . ' is Open again.' : ''));
} catch (PDOException) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('disaster_error', 'The check-in could not be updated. No changes were made.');
} catch (RuntimeException $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('disaster_error', $exception->getMessage());
}
redirect($back);
