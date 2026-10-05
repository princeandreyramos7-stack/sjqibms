<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/complaints.php';
// Resident lookup for complaint/blotter forms: Super Admin and Secretary only (residents never search the registry).
require_auth();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (!complaints_can_manage()) { http_response_code(403); echo json_encode(['error' => 'Access denied.']); exit; }
$query = residents_collapse((string) ($_GET['q'] ?? ''));
if (mb_strlen(preg_replace('/\s+/u', '', $query) ?? '') < 2) { echo json_encode(['results' => []]); exit; }
$query = mb_substr($query, 0, 100);
$like = '%' . addcslashes($query, '%_\\') . '%';
$statement = db()->prepare("SELECT id, first_name, middle_name, last_name, suffix, birth_date, purok, status, address, contact_number FROM residents WHERE CONCAT_WS(' ', first_name, middle_name, last_name, suffix) LIKE :a OR CONCAT_WS(' ', first_name, last_name) LIKE :b OR CONCAT_WS(', ', last_name, first_name) LIKE :c ORDER BY last_name, first_name, id LIMIT 10");
$statement->execute(['a' => $like, 'b' => $like, 'c' => $like]);
$results = array_map(static fn (array $r): array => [
    'id' => (int) $r['id'],
    'name' => residents_full_name($r),
    'meta' => '#' . $r['id'] . ' · ' . ($r['birth_date'] ? 'Born ' . substr($r['birth_date'], 0, 4) : 'Birth year unknown') . ' · ' . $r['purok'] . ' · ' . (residents_status_labels()[$r['status']] ?? $r['status']),
    'address' => residents_collapse((string) $r['address']),
    'contact' => (string) ($r['contact_number'] ?? ''),
], $statement->fetchAll());
echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
