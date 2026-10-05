<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/registrations.php';
require_once __DIR__ . '/includes/family_requests.php';
reg_require();
$connection = db();

// Approve or reject a family member added by a resident. POST only, with CSRF; the rules are in fam_decide().
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('registrations.php');
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('registrations_error', 'Your session expired. Please try again.'); redirect('family_request_view.php?id=' . $id); }
try {
    flash('registrations_success', fam_decide($connection, $id, (string) ($_POST['decision'] ?? ''), (string) ($_POST['notes'] ?? '')));
    redirect('registrations.php#family-members');
} catch (PDOException) { // before RuntimeException: PDOException extends it, and its message must never reach the user
    flash('registrations_error', 'The request could not be updated. No changes were made.');
} catch (RuntimeException $exception) {
    flash('registrations_error', $exception->getMessage());
}
redirect('family_request_view.php?id=' . $id);
