<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/registrations.php';
reg_require();
$connection = db();

// Approve or reject an online registration. POST only, with CSRF; the rules are in reg_decide().
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('registrations.php');
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('registrations_error', 'Your session expired. Please try again.'); redirect('registration_view.php?id=' . $id); }
try {
    flash('registrations_success', reg_decide($connection, $id, (string) ($_POST['decision'] ?? ''), (string) ($_POST['notes'] ?? ''), array_intersect_key($_POST, array_flip(['household_action', 'household_id', 'household_relationship']))));
    redirect('registrations.php');
} catch (PDOException) { // before RuntimeException: PDOException extends it, and its message must never reach the user
    flash('registrations_error', 'The registration could not be updated. No changes were made.');
} catch (RuntimeException $exception) {
    flash('registrations_error', $exception->getMessage());
}
redirect('registration_view.php?id=' . $id);
