<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health.php';
// Resident list for the health record form (System Administrator and Health Worker only). A Purok must be chosen
// first: only active residents registered in that Purok are listed (A–Z, first 50), filtered by name when 2+
// characters are typed. Without a Purok nothing is listed. Returns minimal details; the server re-reads the resident on save.
require_auth();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (!health_can_manage()) { http_response_code(403); echo json_encode(['error' => 'Access denied.']); exit; }
$connection = db();
$purok = residents_collapse((string) ($_GET['purok'] ?? ''));
if ($purok === '' || !in_array($purok, health_resident_puroks($connection), true)) { echo json_encode(['results' => [], 'total' => 0, 'searching' => false, 'purok' => null]); exit; }
$limit = 50;
$query = mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100);
$searching = mb_strlen(preg_replace('/\s+/u', '', $query) ?? '') >= 2;
$where = "status = 'active' AND purok = :purok";
$params = ['purok' => $purok];
// Program forms narrow the list: 'female' (10 years or older, Maternal), 'under5' (0–59 months, Immunization and
// Operation Timbang). The server checks the resident again on save.
$filter = (string) ($_GET['filter'] ?? '');
if ($filter === 'female') $where .= " AND sex = 'female' AND birth_date IS NOT NULL AND birth_date <= DATE_SUB(CURDATE(), INTERVAL 10 YEAR)";
elseif ($filter === 'under5') $where .= ' AND birth_date IS NOT NULL AND birth_date <= CURDATE() AND birth_date > DATE_SUB(CURDATE(), INTERVAL 60 MONTH)';
if ($searching) {
    $like = '%' . addcslashes($query, '%_\\') . '%';
    $where .= " AND (CONCAT_WS(' ', first_name, middle_name, last_name, suffix) LIKE :a OR CONCAT_WS(' ', first_name, last_name) LIKE :b OR CONCAT_WS(', ', last_name, first_name) LIKE :c)";
    $params += ['a' => $like, 'b' => $like, 'c' => $like];
}
$count = $connection->prepare("SELECT COUNT(*) FROM residents WHERE $where");
$count->execute($params);
$total = (int) $count->fetchColumn();
$statement = $connection->prepare("SELECT id, first_name, middle_name, last_name, suffix, birth_date, sex, purok, status FROM residents WHERE $where ORDER BY last_name, first_name, id LIMIT $limit");
$statement->execute($params);
$results = array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => residents_full_name($r), 'meta' => health_resident_meta($r)], $statement->fetchAll());
echo json_encode(['results' => $results, 'total' => $total, 'searching' => $searching, 'purok' => residents_purok_label($purok)], JSON_UNESCAPED_UNICODE);
