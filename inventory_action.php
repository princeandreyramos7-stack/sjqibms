<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/inventory.php';
inventory_require_manage();
$connection = db();

// Archive (soft delete) and Restore. POST only, with CSRF; the list or details page is shown again afterwards.
$return = (string) ($_POST['return'] ?? '');
$back = (string) ($_POST['back'] ?? '') === 'view' ? 'inventory_view.php?id=' . (int) ($_POST['id'] ?? 0) : 'inventory.php' . (preg_match('/^[A-Za-z0-9_=&%+.\-]{1,300}$/', $return) ? '?' . $return : '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('inventory.php');
if (!inventory_ready($connection)) { flash('inventory_error', 'Inventory needs its database tables first.'); redirect('inventory.php'); }
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('inventory_error', 'Your session expired. Please try again.'); redirect($back); }

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$action = (string) ($_POST['action'] ?? '');
try {
    $connection->beginTransaction();
    $item = inventory_find($connection, $id, true);
    if ($item === null) throw new RuntimeException('The item no longer exists.');
    if ($action === 'archive') {
        if ($item['archived_at'] !== null) throw new RuntimeException('This item is already archived.');
        if (inventory_borrowed_out($connection, $id) > 0) throw new RuntimeException($item['item_code'] . ' has items that are still borrowed. Record their return before archiving.');
        $connection->prepare('UPDATE inventory_items SET archived_at = NOW(), archived_by = :user, updated_by = :updater WHERE id = :id')->execute(['user' => current_user()['id'], 'updater' => current_user()['id'], 'id' => $id]);
        inventory_log_movement($connection, $id, 'archived', 0, (int) $item['quantity'], 'Item archived');
        inventory_audit($connection, $id, 'inventory_archived', ['item_code' => $item['item_code']]);
        $message = $item['item_code'] . ' was archived. It can be restored from the Archived filter.';
    } elseif ($action === 'restore') {
        if ($item['archived_at'] === null) throw new RuntimeException('This item is not archived.');
        $connection->prepare('UPDATE inventory_items SET archived_at = NULL, archived_by = NULL, updated_by = :user WHERE id = :id')->execute(['user' => current_user()['id'], 'id' => $id]);
        inventory_log_movement($connection, $id, 'restored', 0, (int) $item['quantity'], 'Item restored');
        inventory_audit($connection, $id, 'inventory_restored', ['item_code' => $item['item_code']]);
        $message = $item['item_code'] . ' was restored.';
    } else {
        throw new RuntimeException('This action is not available.');
    }
    $connection->commit();
    flash('inventory_success', $message);
} catch (PDOException) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('inventory_error', 'The item could not be updated. No changes were made.');
} catch (RuntimeException $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('inventory_error', $exception->getMessage());
}
redirect($back);
