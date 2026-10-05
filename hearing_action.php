<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/hearings.php';
// Hearing handler (Super Admin / Secretary): reschedule, cancel, outcome, attendance, assign_personnel, remove_personnel.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
complaints_require_manage();
$connection = db();
complaints_require_schema($connection);
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$action = (string) ($_POST['action'] ?? '');
$back = $id ? 'hearing_view.php?id=' . $id : 'complaints.php?tab=hearings';
if (!verify_csrf_token($_POST['csrf_token'] ?? null) || !$id) { flash('case_error', 'The action could not be completed. Please try again.'); redirect($back); }
try {
    switch ($action) {
        case 'reschedule':
            $schedule = hearings_validate_schedule((string) ($_POST['date'] ?? ''), (string) ($_POST['start_time'] ?? ''), (string) ($_POST['end_time'] ?? ''));
            if ($schedule['errors'] !== []) throw new RuntimeException(reset($schedule['errors']));
            $venue_id = filter_var($_POST['venue_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
            $reference = hearings_reschedule($connection, $id, ['start' => $schedule['start'], 'end' => $schedule['end'], 'venue_id' => $venue_id], complaints_text($_POST['reason'] ?? '', 1000));
            flash('case_success', "Hearing $reference rescheduled. The previous schedule is kept in the history.");
            break;
        case 'cancel':
            $reference = hearings_cancel($connection, $id, complaints_text($_POST['reason'] ?? '', 1000));
            flash('case_success', "Hearing $reference cancelled.");
            break;
        case 'outcome':
            $reference = hearings_record_outcome($connection, $id, complaints_text($_POST['outcome'] ?? '', 5000));
            flash('case_success', "Outcome recorded. Hearing $reference is Completed; the related case status was not changed.");
            break;
        case 'attendance':
            $reference = hearings_record_attendance($connection, $id, (array) ($_POST['attendance'] ?? []));
            flash('case_success', "Attendance saved for hearing $reference.");
            break;
        case 'assign_personnel':
            $reference = hearings_assign_personnel($connection, $id, filter_var($_POST['personnel_id'] ?? null, FILTER_VALIDATE_INT) ?: 0, residents_collapse((string) ($_POST['assignment_role'] ?? '')));
            flash('case_success', "Personnel assigned to hearing $reference.");
            break;
        case 'remove_personnel':
            $reference = hearings_remove_personnel($connection, $id, filter_var($_POST['assignment_id'] ?? null, FILTER_VALIDATE_INT) ?: 0, residents_collapse((string) ($_POST['reason'] ?? '')));
            flash('case_success', "Assignment removed from hearing $reference. It remains in the assignment history.");
            break;
        default:
            flash('case_error', 'This action is not available.');
    }
} catch (PDOException) { // before RuntimeException: PDOException extends it, and its message must never reach the user
    flash('case_error', 'The action could not be completed. No changes were made.');
} catch (RuntimeException $exception) {
    flash('case_error', $exception->getMessage());
}
redirect($back);
