<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/documents.php';
require_auth();

// Central processing handler: pending → approved, pending → rejected (reason required) and approved → released (the name
// of the person who received the document is required and kept in the audit trail with the releasing staff member).
// Processing, Ready for Release, Cancel, payment and fee actions need the review-only migration and are refused here.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
if (!documents_can_process()) { http_response_code(403); exit('Access denied.'); }

$request_id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$action = (string) ($_POST['action'] ?? '');
// Actions started from the list return to the list with its filters; otherwise to the request's details page.
$return = (string) ($_POST['return'] ?? '');
if ($return === 'list') $back = 'documents.php';
elseif ($return !== '' && preg_match('/^[A-Za-z0-9_=&%+.\-]{1,300}$/', $return)) $back = 'documents.php?' . $return;
else $back = $request_id ? 'document_view.php?id=' . $request_id : 'documents.php';
$transitions = [
    'approve' => ['from' => 'pending', 'to' => 'approved', 'audit' => 'document_approved', 'message' => 'approved'],
    'reject' => ['from' => 'pending', 'to' => 'rejected', 'audit' => 'document_rejected', 'message' => 'rejected'],
    'release' => ['from' => 'approved', 'to' => 'released', 'audit' => 'document_released', 'message' => 'released'],
];

if (!verify_csrf_token($_POST['csrf_token'] ?? null) || !$request_id) {
    flash('document_error', 'The action could not be completed. Please try again.');
    redirect($back);
}
// Development test release / reset: separate from the official release, allowed only when documents_test_release_enabled()
// (server configuration + local machine + listed account). It reads the request but writes nothing to the database; the
// result is kept in this user's session only, so no status, issuance, payment, signing, claimant or audit record is created.
if ($action === 'test_release' || $action === 'test_release_reset') {
    if (!documents_test_release_enabled()) {
        flash('document_error', 'Test release is not available on this system. Nothing was changed.');
        redirect($back);
    }
    $lookup = db()->prepare('SELECT id, reference_code, status FROM document_requests WHERE id = :id');
    $lookup->execute(['id' => $request_id]);
    $request = $lookup->fetch();
    if (!$request) {
        flash('document_error', 'This document request no longer exists.');
        redirect('documents.php');
    }
    if ($request['status'] !== 'approved') {
        flash('document_error', 'Only Approved requests can be test released. Its current status is ' . (documents_status_labels()[$request['status']] ?? $request['status']) . '.');
        redirect($back);
    }
    if ($action === 'test_release') {
        if (isset($_SESSION['documents_test_releases'][$request_id])) {
            flash('document_error', 'This request is already test released in your session.');
            redirect($back);
        }
        $_SESSION['documents_test_releases'][$request_id] = ['at' => date('Y-m-d H:i:s'), 'by' => (string) current_user()['name']];
        flash('document_success', 'Test release completed for ' . $request['reference_code'] . ' (development only). No official issuance was recorded; the saved status is still Approved.');
    } else {
        unset($_SESSION['documents_test_releases'][$request_id]);
        flash('document_success', 'Test release cleared for ' . $request['reference_code'] . '. The request is shown as Approved again.');
    }
    redirect($back);
}
// Post-approval cancellation stays refused: the current database has no Cancelled status for document requests.
if ($action === 'cancel') {
    flash('document_error', documents_blocked_message('cancel'));
    redirect($back);
}
if (!isset($transitions[$action])) {
    flash('document_error', documents_blocked_message($action));
    redirect($back);
}
$reason = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['reason'] ?? '')) ?? '');
if ($action === 'reject' && (mb_strlen($reason) < 5 || mb_strlen($reason) > 500)) {
    flash('document_error', 'Enter a rejection reason of 5 to 500 characters.');
    redirect($back);
}
if ($action === 'release' && (mb_strlen($reason) < 2 || mb_strlen($reason) > 150 || !preg_match("/^\p{L}[\p{L}\p{M}\s.,'\-]*$/u", $reason))) {
    flash('document_error', 'Enter the full name of the person who received the document (letters only, up to 150 characters).');
    redirect($back);
}

$connection = db();
$transition = $transitions[$action];
try {
    $connection->beginTransaction();
    // Lock and re-read: forged or stale submissions cannot skip or repeat a transition.
    $lock = $connection->prepare('SELECT d.id, d.reference_code, d.status, d.document_type, d.purpose, r.status AS resident_status FROM document_requests d INNER JOIN residents r ON r.id = d.resident_id WHERE d.id = :id FOR UPDATE');
    $lock->execute(['id' => $request_id]);
    $request = $lock->fetch();
    if (!$request) throw new RuntimeException('This document request no longer exists.');
    if ($request['status'] !== $transition['from']) throw new RuntimeException('This request was already updated by someone else. Its current status is ' . (documents_status_labels()[$request['status']] ?? $request['status']) . '.');
    if ($action === 'approve') {
        // Required request information must still be valid at approval time.
        if (trim((string) $request['document_type']) === '' || mb_strlen(trim((string) $request['purpose'])) < 5) throw new RuntimeException('This request is missing its document type or purpose and cannot be approved. Reject it with a reason instead.');
        if ($request['resident_status'] !== 'active') throw new RuntimeException('The resident profile for this request is no longer active, so it cannot be approved.');
    }
    if ($action === 'approve') {
        $update = $connection->prepare("UPDATE document_requests SET status = 'approved', approved_at = NOW(), approved_by = :user WHERE id = :id AND status = 'pending'");
        $update->execute(['user' => current_user()['id'], 'id' => $request_id]);
    } elseif ($action === 'release') {
        $update = $connection->prepare("UPDATE document_requests SET status = 'released', released_at = NOW() WHERE id = :id AND status = 'approved'");
        $update->execute(['id' => $request_id]);
    } else {
        $update = $connection->prepare("UPDATE document_requests SET status = 'rejected' WHERE id = :id AND status = 'pending'");
        $update->execute(['id' => $request_id]);
    }
    if ($update->rowCount() !== 1) throw new RuntimeException('This request was already updated by someone else. Its current status is shown below.');
    $details = ['reference_code' => $request['reference_code'], 'from' => $transition['from'], 'to' => $transition['to']];
    if ($action === 'reject') $details['reason'] = $reason;
    if ($action === 'release') $details['received_by'] = $reason;
    documents_audit($connection, $request_id, $transition['audit'], $details);
    $connection->commit();
    flash('document_success', 'Request ' . $request['reference_code'] . ' ' . $transition['message'] . '.');
} catch (RuntimeException $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('document_error', $exception->getMessage());
} catch (PDOException) {
    if ($connection->inTransaction()) $connection->rollBack();
    flash('document_error', 'The action could not be completed. No changes were made.');
}
redirect($back);
