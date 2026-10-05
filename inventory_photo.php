<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/inventory.php';
inventory_require_view();
$connection = db();

// Serves an item photo from private storage to authorized users only. The file name comes from the database record,
// never from the request, and the type is re-checked before sending.
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$item = $id && inventory_ready($connection) ? inventory_find($connection, $id) : null;
// Health Workers: Medical items only.
if ($item && inventory_medical_only() && (int) $item['category_id'] !== (int) inventory_medical_category_id($connection)) $item = null;
$path = $item ? inventory_photo_path($item['photo_filename']) : null;
if ($path === null) { http_response_code(404); exit('Photo not found.'); }
$mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!array_key_exists($mime, inventory_photo_types())) { http_response_code(404); exit('Photo not found.'); }
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
header("Content-Security-Policy: default-src 'none'");
readfile($path);
