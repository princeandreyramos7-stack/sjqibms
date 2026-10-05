<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/assistance.php';
assistance_require_manage();
$connection = db();

// Record actions (POST only, with CSRF): Void (kept on record with a reason; items of a Given record return to stock) and
// Mark as Given (Scheduled → Given; items are deducted from stock now).
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$action = (string) ($_POST['action'] ?? 'void');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('assistance.php');
if (!assistance_ready($connection)) { flash('assistance_error', 'Relief & Assistance needs its database update first.'); redirect('assistance.php'); }
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('assistance_error', 'Your session expired. Please try again.'); redirect(preg_match('/^assistance_claims\.php(\?[A-Za-z0-9_=&%+.\-]*)?$/', (string) ($_POST['return'] ?? '')) ? (string) $_POST['return'] : ($id ? 'assistance_view.php?id=' . $id : 'assistance.php')); }
try {
    $message = match ($action) {
        'void' => assistance_void($connection, $id, (string) ($_POST['reason'] ?? '')),
        'mark_given' => assistance_mark_given($connection, $id, $_POST),
        default => throw new RuntimeException('This action is not available.'),
    };
    flash('assistance_success', $message);
} catch (PDOException) {
    flash('assistance_error', 'The record could not be updated. No changes were made.');
} catch (RuntimeException $exception) {
    flash('assistance_error', $exception->getMessage());
}
// The Claim List posts here and returns to itself (only that page is accepted as a return address).
$return = (string) ($_POST['return'] ?? '');
if (preg_match('/^assistance_claims\.php(\?[A-Za-z0-9_=&%+.\-]*)?$/', $return)) redirect($return);
redirect($id ? 'assistance_view.php?id=' . $id : 'assistance.php');
