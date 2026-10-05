<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/complaints.php';
// Blotter processing handler (Super Admin / Secretary): start, resolve, close, note — re-validated on the locked row.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
complaints_require_manage();
$connection = db();
complaints_require_schema($connection);
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$action = (string) ($_POST['action'] ?? '');
$return = (string) ($_POST['return'] ?? '');
$back = $return !== '' && preg_match('/^[A-Za-z0-9_=&%+.\-]{1,300}$/', $return) ? 'complaints.php?' . $return : ($id ? 'blotter_view.php?id=' . $id : 'complaints.php?tab=blotter');
if (!verify_csrf_token($_POST['csrf_token'] ?? null) || !$id) { flash('case_error', 'The action could not be completed. Please try again.'); redirect($back); }
$notes = complaints_text($_POST['notes'] ?? '', 2000);
try {
    if ($action === 'note') {
        $reference = complaints_add_note($connection, 'blotter', $id, $notes);
        flash('case_success', "Note added to blotter entry $reference.");
    } else {
        $reference = complaints_apply_transition($connection, 'blotter', $id, $action, $notes);
        $label = ['start' => 'is now Active', 'resolve' => 'was resolved', 'close' => 'was closed'][$action];
        flash('case_success', "Blotter entry $reference $label.");
    }
} catch (PDOException) { // before RuntimeException: PDOException extends it, and its message must never reach the user
    flash('case_error', 'The action could not be completed. No changes were made.');
} catch (RuntimeException $exception) {
    flash('case_error', $exception->getMessage());
}
redirect($back);
