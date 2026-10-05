<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_recovery.php';
disaster_require_view();
$connection = db();

// Serves a damage-assessment photo from private storage to Disaster Management users only. The file name comes from the
// database record, never from the request, and the type is re-checked before sending.
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$assessment = $id && disaster_recovery_ready($connection) ? disaster_damage_find($connection, $id) : null;
$path = $assessment ? disaster_photo_path($assessment['photo_path']) : null;
if ($path === null) { http_response_code(404); exit('Photo not found.'); }
$mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!array_key_exists($mime, inventory_photo_types())) { http_response_code(404); exit('Photo not found.'); }
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
header("Content-Security-Policy: default-src 'none'");
readfile($path);
