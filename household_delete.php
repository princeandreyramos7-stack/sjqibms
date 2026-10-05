<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/households.php';
households_require_manage();
$connection = db();

// Delete a household. Only a household that nothing refers to is removed (households_can_delete()); for any other the
// reason and what to do instead are shown (households_delete_block_reason()) and nothing changes. POST only, with CSRF;
// the check is repeated under a lock right before the row is removed, and the household's number, Purok and address are
// kept in the audit log. return=view goes back to the household's page when it was not deleted.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('households.php');
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$back_to_view = ($_POST['return'] ?? '') === 'view';
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('household_error', 'Your session expired. Please try again.'); redirect('households.php'); }
try {
    $connection->beginTransaction();
    $household = households_find($connection, $id, true);
    if ($household === null) throw new RuntimeException('The household no longer exists.');
    $reason = households_delete_block_reason($connection, $household);
    if ($reason !== null) throw new RuntimeException($reason);
    $connection->prepare('DELETE FROM households WHERE id = :id')->execute(['id' => $id]);
    residents_audit($connection, 'household', $id, 'household_deleted', ['household_no' => $household['household_no'], 'purok' => $household['purok'], 'address' => $household['address']]);
    $connection->commit();
    flash('household_success', 'Household ' . $household['household_no'] . ' was deleted.');
    redirect('households.php');
} catch (PDOException) { // before RuntimeException: PDOException extends it, and its message must never reach the user
    if ($connection->inTransaction()) $connection->rollBack();
    flash('household_error', 'The household could not be deleted because other records still refer to it. No changes were made.');
} catch (RuntimeException $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('household_error', $exception->getMessage());
}
redirect($back_to_view && $id > 0 ? 'household_view.php?id=' . $id : 'households.php');
