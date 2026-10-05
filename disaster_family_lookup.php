<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_response.php';
// Resident list for evacuation check-in and relief (Disaster Management users only). A Purok must be chosen first
// (or "All Puroks" when a priority group is chosen): active residents are listed (A–Z, first 50), filtered by name when
// 2+ characters are typed. Each result names the resident's family (household or the resident alone), its member count,
// a current check-in if any, and the priority groups the resident belongs to (Senior, Under 5, PWD, Solo Parent).
// The server re-reads everything on save.
require_auth();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (!disaster_can_manage()) { http_response_code(403); echo json_encode(['error' => 'Access denied.']); exit; }
$connection = db();
if (!disaster_response_ready($connection)) { echo json_encode(['results' => [], 'total' => 0, 'searching' => false, 'purok' => null]); exit; }
// Optional priority group (Senior, Under 5, PWD, Solo Parent): lists only residents in that group and allows
// purok=all to search every Purok. Without a group a Purok is required.
$group = (string) ($_GET['group'] ?? '');
if ($group !== '' && !array_key_exists($group, disaster_vulnerable_groups())) $group = '';
$purok = residents_collapse((string) ($_GET['purok'] ?? ''));
$all_puroks = $purok === 'all' && $group !== '';
if (!$all_puroks && ($purok === '' || !in_array($purok, disaster_vulnerable_puroks($connection), true))) { echo json_encode(['results' => [], 'total' => 0, 'searching' => false, 'purok' => null]); exit; }
$limit = 50;
$query = mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100);
$searching = mb_strlen(preg_replace('/\s+/u', '', $query) ?? '') >= 2;
$where = "r.status = 'active'";
$params = [];
if (!$all_puroks) { $where .= ' AND r.purok = :purok'; $params['purok'] = $purok; }
if ($group !== '') $where .= ' AND (' . disaster_vulnerable_condition($connection, $group) . ')';
if ($searching) {
    $like = '%' . addcslashes($query, '%_\\') . '%';
    $where .= " AND (CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name, r.suffix) LIKE :a OR CONCAT_WS(' ', r.first_name, r.last_name) LIKE :b OR CONCAT_WS(', ', r.last_name, r.first_name) LIKE :c)";
    $params += ['a' => $like, 'b' => $like, 'c' => $like];
}
$count = $connection->prepare("SELECT COUNT(*) FROM residents r WHERE $where");
$count->execute($params);
$total = (int) $count->fetchColumn();
// Every group each listed resident belongs to, shown as tags.
$group_keys = array_keys(disaster_vulnerable_groups());
$flags = implode(', ', array_map(static fn (string $key): string => '(' . disaster_vulnerable_condition($connection, $key) . ") AS g_$key", $group_keys));
$statement = $connection->prepare("SELECT r.id, r.purok, $flags FROM residents r WHERE $where ORDER BY r.last_name, r.first_name, r.id LIMIT $limit");
$statement->execute($params);
$results = [];
foreach ($statement->fetchAll() as $row) {
    $family = disaster_family_for_resident($connection, (int) $row['id']);
    if ($family === null) continue;
    $open = disaster_open_checkin($connection, $family['key']);
    $meta = $family['household_id'] ? 'Family: ' . $family['label'] . ' · ' . $family['members'] . ' member' . ($family['members'] === 1 ? '' : 's') : 'No household · checked in alone';
    $results[] = [
        'id' => (int) $row['id'],
        'name' => residents_full_name($family['resident']),
        'meta' => ($all_puroks ? residents_purok_label((string) $row['purok']) . ' · ' : '') . $meta,
        'members' => $family['members'],
        'checked_in' => $open ? 'Already checked in at ' . $open['center_name'] : null,
        'groups' => array_values(array_map(static fn (string $key): array => ['key' => $key, 'label' => disaster_vulnerable_short_label($key)], array_filter($group_keys, static fn (string $key): bool => (int) $row['g_' . $key] === 1))),
    ];
}
$scope = $all_puroks ? 'all Puroks' : residents_purok_label($purok);echo json_encode(['results' => $results, 'total' => $total, 'searching' => $searching, 'purok' => $scope, 'group' => $group !== '' ? disaster_vulnerable_short_label($group) : null], JSON_UNESCAPED_UNICODE);
