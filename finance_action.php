<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
finance_require('view');
$connection = db();

// Approve / reject (System Administrator), release / cancel / attachments (Treasurer). POST only, with CSRF. Every action is
// checked again here against the signed-in role (read from the database) and the record's current status, with the
// record locked, so hidden buttons can never be bypassed.
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$action = (string) ($_POST['action'] ?? '');
$return = (string) ($_POST['return'] ?? '');
$view = 'finance_view.php?id=' . $id;
$back = (string) ($_POST['back'] ?? '') === 'view' || $return === '' ? $view : 'finance.php' . (preg_match('/^[A-Za-z0-9_=&%+.\-]{1,300}$/', $return) ? '?' . $return : '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('finance.php');
if (!finance_ready($connection)) { flash('finance_error', 'Financial Management needs its database tables first.'); redirect('finance.php'); }
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('finance_error', 'Your session expired. Please try again.'); redirect($back); }

$permission = ['approve' => 'approve', 'reject' => 'approve', 'release' => 'release', 'cancel' => 'cancel', 'attach' => 'create', 'remove_attachment' => 'create'][$action] ?? null;
if ($permission === null) { flash('finance_error', 'This action is not available.'); redirect($back); }
finance_require($permission);

// Field errors for the forms on the view page (reason, payment details, file) are shown there after the redirect.
$field_errors = static function (array $errors, array $values, string $anchor) use ($view): never {
    $_SESSION['finance_form_errors'] = $errors;
    $_SESSION['finance_form_values'] = $values;
    flash('finance_error', 'Please correct the highlighted field.');
    redirect($view . '#' . $anchor);
};

$user = (int) current_user()['id'];
$stored_file = null;
try {
    // Uploads are validated and stored before the transaction; a failed save leaves no reference to the file.
    if ($action === 'attach') {
        try { [$stored_file, $mime, $size] = finance_store_attachment($_FILES['attachment'] ?? []); } catch (RuntimeException $exception) { $field_errors(['attachment' => $exception->getMessage()], [], 'attachment'); }
    }
    $connection->beginTransaction();
    $record = finance_find($connection, $id, true);
    if ($record === null) throw new RuntimeException('The transaction no longer exists.');
    $can = finance_allowed_actions($record);
    $reference = $record['reference_no'];

    if ($action === 'approve') {
        if (!$can['approve']) throw new RuntimeException($reference . ' is no longer waiting for approval.');
        $connection->prepare("UPDATE finance_transactions SET status = 'approved', approved_by = :user, approved_at = NOW() WHERE id = :id")->execute(['user' => $user, 'id' => $id]);
        finance_audit($connection, $id, 'finance_approved', ['reference' => $reference, 'changes' => ['status' => ['old' => 'pending_approval', 'new' => 'approved']]]);
        finance_notify($connection, $id, 'treasurer', 'finance_approved', 'Disbursement approved: ' . $reference, finance_peso($record['amount']) . ' to ' . $record['payor_or_payee'] . ' was approved and can now be released.');
        $message = $reference . ' was approved. The Treasurer was notified.';
    } elseif ($action === 'reject') {
        $reason = residents_collapse((string) ($_POST['reason'] ?? ''));
        if (!$can['reject']) throw new RuntimeException($reference . ' is no longer waiting for approval.');
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) { $connection->rollBack(); $field_errors(['reject_reason' => 'Give the reason for rejecting (5 to 255 characters).'], ['reject_reason' => $reason], 'reject'); }
        $connection->prepare("UPDATE finance_transactions SET status = 'rejected', rejected_by = :user, rejected_at = NOW(), reject_reason = :reason WHERE id = :id")->execute(['user' => $user, 'reason' => $reason, 'id' => $id]);
        finance_audit($connection, $id, 'finance_rejected', ['reference' => $reference, 'reason' => $reason, 'changes' => ['status' => ['old' => 'pending_approval', 'new' => 'rejected']]]);
        finance_notify($connection, $id, 'treasurer', 'finance_rejected', 'Disbursement rejected: ' . $reference, 'Reason: ' . $reason);
        $message = $reference . ' was rejected. The Treasurer was notified.';
    } elseif ($action === 'release') {
        if (!$can['release']) throw new RuntimeException($reference . ' can be released only after it is approved.');
        $mode = (string) ($_POST['payment_mode'] ?? '');
        $check = strtoupper(residents_collapse((string) ($_POST['check_no'] ?? '')));
        $date_input = trim((string) ($_POST['release_date'] ?? ''));
        $date = finance_valid_date($date_input);
        $errors = [];
        if (!array_key_exists($mode, finance_payment_mode_options())) $errors['payment_mode'] = 'Select the mode of payment.';
        elseif ($mode === 'check' && !preg_match('/^[A-Z0-9\-]{3,40}$/', $check)) $errors['check_no'] = 'Enter the check number (letters, numbers and dashes).';
        if ($date === null) $errors['release_date'] = 'Enter a valid date.';
        elseif ($date > new DateTimeImmutable('today')) $errors['release_date'] = 'The release date cannot be in the future.';
        elseif ($date_input < substr((string) $record['approved_at'], 0, 10)) $errors['release_date'] = 'The release date cannot be before the approval date.';
        elseif (($earliest = finance_earliest_date($connection)) !== null && $date_input < $earliest) $errors['release_date'] = 'The release date cannot be before the beginning balance date.';
        if ($errors !== []) { $connection->rollBack(); $field_errors($errors, ['payment_mode' => $mode, 'check_no' => $check, 'release_date' => $date_input], 'release'); }
        if ($mode !== 'check') $check = null;
        $connection->prepare("UPDATE finance_transactions SET status = 'released', payment_mode = :mode, check_no = :check, release_date = :date, released_by = :user, released_at = NOW() WHERE id = :id")->execute(['mode' => $mode, 'check' => $check, 'date' => $date_input, 'user' => $user, 'id' => $id]);
        finance_audit($connection, $id, 'finance_released', ['reference' => $reference, 'changes' => ['status' => ['old' => 'approved', 'new' => 'released'], 'payment_mode' => ['old' => null, 'new' => $mode], 'check_no' => ['old' => null, 'new' => $check], 'release_date' => ['old' => null, 'new' => $date_input]]]);
        $message = $reference . ' was released (' . finance_peso($record['amount']) . ').';
    } elseif ($action === 'cancel') {
        $reason = residents_collapse((string) ($_POST['reason'] ?? ''));
        if (!$can['cancel']) throw new RuntimeException($reference . ' cannot be cancelled.');
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) { $connection->rollBack(); $field_errors(['cancel_reason' => 'Give the reason for cancelling (5 to 255 characters).'], ['cancel_reason' => $reason], 'cancel'); }
        $connection->prepare("UPDATE finance_transactions SET status = 'cancelled', status_before_cancel = :before, cancelled_by = :user, cancelled_at = NOW(), cancel_reason = :reason WHERE id = :id")->execute(['before' => $record['status'], 'user' => $user, 'reason' => $reason, 'id' => $id]);
        finance_audit($connection, $id, 'finance_cancelled', ['reference' => $reference, 'reason' => $reason, 'changes' => ['status' => ['old' => $record['status'], 'new' => 'cancelled']]]);
        $message = $reference . ' was cancelled. It stays on record and is excluded from all totals.';
    } elseif ($action === 'attach') {
        if (!$can['attach']) throw new RuntimeException('Attachments cannot be added to a cancelled transaction.');
        $original = finance_clean_filename((string) ($_FILES['attachment']['name'] ?? 'attachment'));
        $connection->prepare('INSERT INTO finance_attachments (transaction_id, original_name, stored_name, mime_type, file_size, uploaded_by) VALUES (:id, :original, :stored, :mime, :size, :user)')->execute(['id' => $id, 'original' => $original, 'stored' => $stored_file, 'mime' => $mime, 'size' => $size, 'user' => $user]);
        finance_audit($connection, $id, 'finance_attachment_added', ['reference' => $reference, 'name' => $original]);
        $stored_file = null;
        $message = $original . ' was attached to ' . $reference . '.';
    } else {   // remove_attachment
        if (!$can['attach']) throw new RuntimeException('Attachments of a cancelled transaction cannot be changed.');
        $attachment_id = filter_var($_POST['attachment_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $statement = $connection->prepare('SELECT id, original_name FROM finance_attachments WHERE id = :attachment AND transaction_id = :id AND archived_at IS NULL FOR UPDATE');
        $statement->execute(['attachment' => $attachment_id, 'id' => $id]);
        $attachment = $statement->fetch();
        if (!$attachment) throw new RuntimeException('The attachment no longer exists.');
        $connection->prepare('UPDATE finance_attachments SET archived_at = NOW(), archived_by = :user WHERE id = :attachment')->execute(['user' => $user, 'attachment' => $attachment_id]);
        finance_audit($connection, $id, 'finance_attachment_removed', ['reference' => $reference, 'name' => $attachment['original_name']]);
        $message = $attachment['original_name'] . ' was removed from the list. The file is kept in the records.';
    }
    $connection->commit();
    flash('finance_success', $message);
} catch (PDOException) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('finance_error', 'The transaction could not be updated. No changes were made.');
} catch (RuntimeException $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('finance_error', $exception->getMessage());
} finally {
    // An upload that was not saved to a record is removed again (it was never referenced).
    if ($stored_file !== null && ($path = finance_attachment_path($stored_file)) !== null) @unlink($path);
}
redirect($back);
