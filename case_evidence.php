<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/evidence.php';
// Confidential evidence endpoint (not a page): upload / remove (POST, then redirect back — Post/Redirect/Get) and download
// (GET ?id=). Super Admin and Secretary only, on every request. Any failure — including an unavailable database — returns
// the user to the case with a safe message; details go to the PHP error log only.
complaints_require_manage();

// The case page to return to, derived only from validated identifiers (never from arbitrary URLs).
function evidence_return_page(): string
{
    $kind = (string) ($_POST['kind'] ?? '');
    $case_id = filter_var($_POST['case_id'] ?? null, FILTER_VALIDATE_INT);
    if (in_array($kind, ['complaint', 'blotter'], true) && $case_id) return ($kind === 'blotter' ? 'blotter_view.php' : 'complaint_view.php') . '?id=' . $case_id;
    // Fallback for requests whose form fields were lost (for example a file above post_max_size): same-site case page only.
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if (preg_match('#/(complaint_view|blotter_view)\.php\?id=(\d{1,18})(?:&|$)#', $referer, $m)) return $m[1] . '.php?id=' . $m[2];
    return 'complaints.php';
}

function evidence_fail(string $message, string $back, ?Throwable $error = null): never
{
    if ($error !== null) error_log('[SJQIBMS evidence] ' . get_class($error) . ': ' . $error->getMessage() . ' (user ' . (int) (current_user()['id'] ?? 0) . ')');
    flash('case_error', $message);
    redirect($back);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = evidence_return_page();
    // A body larger than post_max_size arrives with empty $_POST and $_FILES: report it instead of a misleading token error.
    if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) evidence_fail('The file is too large to upload. Evidence files may be up to ' . complaints_format_bytes(COMPLAINTS_EVIDENCE_MAX_BYTES) . '. Nothing was saved.', $back);
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) evidence_fail('The action could not be completed because the form expired. Please try again.', $back);
    $action = (string) ($_POST['action'] ?? '');
    $kind = (string) ($_POST['kind'] ?? '');
    $case_id = filter_var($_POST['case_id'] ?? null, FILTER_VALIDATE_INT);
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    try {
        $connection = db();
        complaints_require_schema($connection);
        if ($action === 'remove' && $id && ($attachment = evidence_find($connection, $id))) $back = $attachment['blotter_id'] ? 'blotter_view.php?id=' . $attachment['blotter_id'] : 'complaint_view.php?id=' . $attachment['complaint_id'];
        if ($action === 'upload' && in_array($kind, ['complaint', 'blotter'], true) && $case_id) {
            evidence_store($connection, $kind, $case_id, $_FILES['evidence'] ?? [], residents_collapse((string) ($_POST['description'] ?? '')));
            flash('case_success', 'Evidence file uploaded to protected storage.');
        } elseif ($action === 'remove' && $id) {
            $reference = evidence_remove($connection, $id, residents_collapse((string) ($_POST['reason'] ?? '')));
            flash('case_success', "Evidence file removed from $reference. Its record is kept as history.");
        } else {
            flash('case_error', 'This action is not available.');
        }
    } catch (PDOException $exception) { // before RuntimeException: PDOException extends it, and its message must never reach the user
        evidence_fail('The evidence file could not be saved because the database is not available right now. Nothing was saved. Please try again later.', $back, $exception);
    } catch (RuntimeException $exception) {
        evidence_fail($exception->getMessage(), $back);
    } catch (Throwable $exception) {
        evidence_fail('The evidence file could not be processed. Nothing was saved. Please try again.', $back, $exception);
    }
    redirect($back); // Post/Redirect/Get: refreshing the next page never re-submits the upload
}

// GET without a file id (for example opening this address directly): nothing to show here — go to the module.
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) redirect('complaints.php');

// Download: authorized staff only; removed files are not served; every download is logged.
try {
    $connection = db();
    complaints_require_schema($connection);
    $attachment = evidence_find($connection, $id);
    $path = $attachment && !$attachment['removed_at'] ? evidence_file_path($attachment) : null;
    if ($path === null) { http_response_code(404); exit('File not found.'); }
    $connection->prepare("INSERT INTO case_attachment_access_log (attachment_id, user_id, access_type, ip_address) VALUES (:attachment, :user, 'download', :ip)")
        ->execute(['attachment' => $attachment['id'], 'user' => current_user()['id'], 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
} catch (PDOException $exception) {
    evidence_fail('The file cannot be downloaded because the database is not available right now. Please try again later.', 'complaints.php', $exception);
}
$download_name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', (string) $attachment['original_filename']) ?: 'evidence';
header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $download_name . '"; filename*=UTF-8\'\'' . rawurlencode((string) $attachment['original_filename']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'none'; sandbox");
readfile($path);
exit;
