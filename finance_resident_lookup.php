<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/finance.php';
// Resident search for linking a transaction to a resident (Treasurer only). Needs 2+ typed characters; returns at most
// 15 matches with minimal details. The server re-reads the resident when the transaction is saved.
require_auth();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (!finance_can('create')) { http_response_code(403); echo json_encode(['error' => 'Access denied.']); exit; }
$query = mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100);
if (mb_strlen(preg_replace('/\s+/u', '', $query) ?? '') < 2) { echo json_encode(['results' => []]); exit; }
$like = '%' . addcslashes($query, '%_\\') . '%';
$statement = db()->prepare("SELECT id, first_name, middle_name, last_name, suffix, purok FROM residents WHERE status = 'active' AND (CONCAT_WS(' ', first_name, middle_name, last_name, suffix) LIKE :a OR CONCAT_WS(' ', first_name, last_name) LIKE :b OR CONCAT_WS(', ', last_name, first_name) LIKE :c) ORDER BY last_name, first_name, id LIMIT 15");
$statement->execute(['a' => $like, 'b' => $like, 'c' => $like]);
$results = array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => residents_full_name($r), 'meta' => residents_purok_label((string) $r['purok'])], $statement->fetchAll());
echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
