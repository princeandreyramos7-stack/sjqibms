<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
finance_require('view');
$connection = db();

// Serves a transaction attachment from private storage to the Treasurer and the System Administrator only. The file name
// comes from the database record, never from the request; the type is re-checked. Images open in the browser inside a
// sandbox with no scripts; PDFs are downloaded (the same way as case evidence files).
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$attachment = null;
if ($id && finance_ready($connection)) {
    $statement = $connection->prepare('SELECT stored_name, original_name, mime_type FROM finance_attachments WHERE id = :id AND archived_at IS NULL LIMIT 1');
    $statement->execute(['id' => $id]);
    $attachment = $statement->fetch() ?: null;
}
$path = $attachment ? finance_attachment_path($attachment['stored_name']) : null;
if ($path === null) { http_response_code(404); exit('Attachment not found.'); }
$mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!array_key_exists($mime, finance_attachment_types())) { http_response_code(404); exit('Attachment not found.'); }
$download = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $attachment['original_name']) ?: 'attachment';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: ' . ($mime === 'application/pdf' ? 'attachment' : 'inline') . '; filename="' . $download . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; sandbox");
readfile($path);
