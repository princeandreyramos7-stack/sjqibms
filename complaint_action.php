<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/complaints.php';
// Complaint processing handler (Super Admin / Secretary): start_review, resolve, close, note. Every transition is
// re-validated here against the locked database row; buttons on the pages are never trusted.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
complaints_require_manage();
$connection = db();
complaints_require_schema($connection);
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$action = (string) ($_POST['action'] ?? '');
$return = (string) ($_POST['return'] ?? '');
$back = $return !== '' && preg_match('/^[A-Za-z0-9_=&%+.\-]{1,300}$/', $return) ? 'complaints.php?' . $return : ($id ? 'complaint_view.php?id=' . $id : 'complaints.php');
if (!verify_csrf_token($_POST['csrf_token'] ?? null) || !$id) { flash('case_error', 'The action could not be completed. Please try again.'); redirect($back); }
$notes = complaints_text($_POST['notes'] ?? '', 2000);
try {
    if ($action === 'note') {
        $reference = complaints_add_note($connection, 'complaint', $id, $notes);
        flash('case_success', "Note added to complaint $reference.");
    } else {
        $reference = complaints_apply_transition($connection, 'complaint', $id, $action, $notes);
        $label = ['start_review' => 'is now Under Review', 'resolve' => 'was resolved', 'close' => 'was closed'][$action];
        flash('case_success', "Complaint $reference $label.");
    }
} catch (PDOException) { // before RuntimeException: PDOException extends it, and its message must never reach the user
    flash('case_error', 'The action could not be completed. No changes were made.');
} catch (RuntimeException $exception) {
    flash('case_error', $exception->getMessage());
}
redirect($back);
