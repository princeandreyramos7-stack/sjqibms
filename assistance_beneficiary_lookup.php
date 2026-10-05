<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/assistance.php';
// Beneficiary list for Relief & Assistance (staff who record assistance only).
//   type=resident  : active residents (A–Z) — search by name.
//   type=household : households with at least one current active member — search by household no., head or member name.
// Choose a Purok or "All Puroks"; a priority group (PWD / Solo Parent) narrows the list. First 50 results; each carries the
// priority groups (for a household, from its current members). The server reads the beneficiary again on save.
require_auth();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (!assistance_can_manage()) { http_response_code(403); echo json_encode(['error' => 'Access denied.']); exit; }
$connection = db();
$empty = ['results' => [], 'total' => 0, 'searching' => false, 'purok' => null, 'group' => null];
if (!assistance_ready($connection)) { echo json_encode($empty); exit; }
$type = (string) ($_GET['type'] ?? 'resident') === 'household' ? 'household' : 'resident';
$group = (string) ($_GET['group'] ?? '');
if (!array_key_exists($group, assistance_priority_groups())) $group = '';
$purok = residents_collapse((string) ($_GET['purok'] ?? ''));
$all_puroks = $purok === 'all';
if (!$all_puroks && ($purok === '' || !in_array($purok, disaster_vulnerable_puroks($connection), true))) { echo json_encode($empty); exit; }
$query = mb_substr(residents_collapse((string) ($_GET['q'] ?? '')), 0, 100);
$searching = mb_strlen(preg_replace('/\s+/u', '', $query) ?? '') >= 2;
$params = [];
$like = '%' . addcslashes($query, '%_\\') . '%';
$flag = ['pwd' => 'is_pwd', 'solo_parent' => 'is_solo_parent'][$group] ?? null;
if ($type === 'resident') {
    $where = "r.status = 'active'";
    if (!$all_puroks) { $where .= ' AND r.purok = :purok'; $params['purok'] = $purok; }
    if ($flag !== null) $where .= " AND r.$flag = 1";
    if ($searching) { $where .= " AND (CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name, r.suffix) LIKE :a OR CONCAT_WS(' ', r.first_name, r.last_name) LIKE :b OR CONCAT_WS(', ', r.last_name, r.first_name) LIKE :c)"; $params += ['a' => $like, 'b' => $like, 'c' => $like]; }
    $count = $connection->prepare("SELECT COUNT(*) FROM residents r WHERE $where");
    $count->execute($params);
    $statement = $connection->prepare("SELECT r.id, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.purok, r.is_pwd, r.is_solo_parent FROM residents r WHERE $where ORDER BY r.last_name, r.first_name, r.id LIMIT 50");
    $statement->execute($params);
    $results = array_map(static function (array $row): array {
        $age = residents_age($row['birth_date']);
        $name = residents_full_name($row);
        return ['id' => (int) $row['id'], 'name' => $name, 'receiver' => $name, 'meta' => residents_purok_label((string) $row['purok']) . ($age !== null ? ' · ' . $age . ' yr' . ($age === 1 ? '' : 's') : ''), 'groups' => assistance_lookup_groups(assistance_beneficiary_groups('resident', $row))];
    }, $statement->fetchAll());
} else {
    $member = "FROM resident_households rh INNER JOIN residents m ON m.id = rh.resident_id WHERE rh.household_id = h.id AND rh.left_at IS NULL AND m.status = 'active'";
    $where = "EXISTS (SELECT 1 $member)";
    if (!$all_puroks) { $where .= ' AND h.purok = :purok'; $params['purok'] = $purok; }
    if ($flag !== null) $where .= " AND EXISTS (SELECT 1 $member AND m.$flag = 1)";
    if ($searching) { $where .= " AND (h.household_no LIKE :a OR CONCAT_WS(' ', hr.first_name, hr.last_name) LIKE :b OR EXISTS (SELECT 1 $member AND CONCAT_WS(' ', m.first_name, m.last_name) LIKE :c))"; $params += ['a' => $like, 'b' => $like, 'c' => $like]; }
    $from = "FROM households h LEFT JOIN residents hr ON hr.id = h.household_head_resident_id WHERE $where";
    $count = $connection->prepare("SELECT COUNT(*) $from");
    $count->execute($params);
    $statement = $connection->prepare("SELECT h.id $from ORDER BY h.household_no, h.id LIMIT 50");
    $statement->execute($params);
    $results = [];
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $household_id) {
        $household = assistance_household($connection, (int) $household_id);
        if ($household === null) continue;
        $results[] = ['id' => (int) $household['id'], 'name' => assistance_household_label($household), 'receiver' => trim(residents_full_name($household)), 'members' => assistance_household_members($connection, (int) $household['id']), 'meta' => residents_purok_label((string) $household['purok']) . ' · ' . (int) $household['members'] . ' member' . ((int) $household['members'] === 1 ? '' : 's'), 'groups' => assistance_lookup_groups(assistance_beneficiary_groups('household', $household))];
    }
}
echo json_encode(['results' => $results, 'total' => (int) $count->fetchColumn(), 'searching' => $searching, 'purok' => $all_puroks ? 'all Puroks' : residents_purok_label($purok), 'group' => $group !== '' ? assistance_priority_groups()[$group] : null], JSON_UNESCAPED_UNICODE);

function assistance_lookup_groups(array $groups): array
{
    return array_map(static fn (string $key): array => ['key' => $key, 'label' => assistance_priority_groups()[$key]], $groups);
}
