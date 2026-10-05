<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/users.php';
require_once __DIR__ . '/includes/registrations.php';
users_require_admin();
$connection = db();

// Suspend or (re)activate an account. POST only, with CSRF. The System Administrator account and your own account are
// never suspended here; a resident account is activated only when it is linked to a resident profile; the Treasurer and
// Punong Barangay roles keep one active account each.
$return = (string) ($_POST['return'] ?? '');
$back = 'users.php' . (preg_match('/^[A-Za-z0-9_=&%+.@\-]{1,300}$/', $return) ? '?' . $return : '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('users.php');
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('users_error', 'Your session expired. Please try again.'); redirect($back); }
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$action = (string) ($_POST['action'] ?? '');
try {
    $connection->beginTransaction();
    $account = users_find($connection, $id, true);
    if ($account === null) throw new RuntimeException('The account no longer exists.');
    $can = users_allowed_actions($account);
    if ($action === 'suspend') {
        if (!$can['suspend']) throw new RuntimeException($can['self'] ? 'You cannot suspend your own account.' : 'This account cannot be suspended.');
        $connection->prepare("UPDATE users SET status = 'suspended' WHERE id = :id")->execute(['id' => $id]);
        accounts_audit($connection, $id, 'account_status_changed', ['name' => $account['name'], 'status_from' => $account['status'], 'status_to' => 'suspended']);
        $message = $account['name'] . ' was suspended and can no longer sign in.';
    } elseif ($action === 'activate') {
        if (!$can['activate']) throw new RuntimeException($account['role'] === 'resident' && $account['resident_id'] === null ? 'This resident account is not linked to a resident profile yet, so it cannot be activated here.' : 'This account cannot be activated here.');
        if (in_array($account['role'], accounts_single_holder_roles(), true)) {
            $connection->query("SELECT id FROM users WHERE role IN ('treasurer', 'punong_barangay') FOR UPDATE")->fetchAll();
            $holder = accounts_role_holder($connection, $account['role'], $id);
            if ($holder !== null) throw new RuntimeException($holder['name'] . ' is already the active ' . accounts_role_label($account['role']) . '. Suspend that account or assign it another role first.');
        }
        $connection->prepare("UPDATE users SET status = 'active', approved_by = COALESCE(approved_by, :admin), approved_at = COALESCE(approved_at, NOW()) WHERE id = :id")->execute(['admin' => current_user()['id'], 'id' => $id]);
        accounts_audit($connection, $id, 'account_status_changed', ['name' => $account['name'], 'status_from' => $account['status'], 'status_to' => 'active']);
        $message = $account['name'] . ' can sign in again.';
        // A resident who signed up online (register.php) has a Pending profile; approving the account approves it too.
        if ($account['role'] === 'resident' && $account['resident_status'] === 'pending') {
            $update = $connection->prepare("UPDATE residents SET status = 'active' WHERE id = :id AND status = 'pending'");
            $update->execute(['id' => $account['resident_id']]);
            if ($update->rowCount() === 1) {
                residents_audit($connection, 'resident', (int) $account['resident_id'], 'resident_status_changed', ['from' => 'pending', 'to' => 'active', 'reason' => 'Resident Portal account approved in User Management']);
                $message = $account['name'] . ' was approved. The account and the resident profile are now active.';
            }
        }
        if ($account['role'] === 'resident') {
            reg_link_profile($connection, $id, $account['resident_id'] === null ? null : (int) $account['resident_id']);
            reg_approve_for_user($connection, $id);
        }
    } else {
        throw new RuntimeException('This action is not available.');
    }
    $connection->commit();
    flash('users_success', $message);
} catch (PDOException) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('users_error', 'The account could not be updated. No changes were made.');
} catch (RuntimeException $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('users_error', $exception->getMessage());
}
redirect($back);
