<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/residents.php';

// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────
// Report statistics — the single source of truth for every figure shown by the Reports module, the Analytics
// Overview, report exports and the matching Dashboard cards. Every figure is an aggregate SQL query (COUNT / SUM /
// GROUP BY) on the live tables; nothing is hardcoded. Rules applied everywhere:
//   • archived, cancelled and rejected records are excluded from totals; where a status breakdown is shown, those
//     statuses appear as their own rows (never merged into the totals);
//   • date ranges are inclusive (the end date counts until 23:59:59) and use Asia/Manila time;
//   • money is summed in SQL from DECIMAL columns;
//   • percentages are null (shown as "—") when the total is 0.
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────

// Report queries group and filter by Manila dates: the session time zone is set explicitly (UTC+8, no DST).
function report_prepare(PDO $connection): PDO
{
    static $done = [];
    $key = spl_object_id($connection);
    if (!isset($done[$key])) { $connection->exec("SET time_zone = '+08:00'"); $done[$key] = true; }
    return $connection;
}

function report_rows(PDO $connection, string $sql, array $params = []): array
{
    $statement = report_prepare($connection)->prepare($sql);
    foreach ($params as $key => $value) $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $statement->execute();
    return $statement->fetchAll();
}

function report_value(PDO $connection, string $sql, array $params = []): mixed
{
    $rows = report_rows($connection, $sql, $params);
    return $rows === [] ? null : array_values($rows[0])[0];
}

function report_table_exists(PDO $connection, string ...$tables): bool
{
    static $cache = [];
    $missing = array_diff($tables, array_keys(array_filter($cache)));
    if ($missing !== []) {
        $placeholders = implode(', ', array_map(static fn (int $i): string => ':t' . $i, array_keys(array_values($missing))));
        $params = [];
        foreach (array_values($missing) as $i => $table) $params['t' . $i] = $table;
        $found = array_column(report_rows($connection, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($placeholders)", $params), 'TABLE_NAME');
        foreach ($missing as $table) $cache[$table] = in_array($table, $found, true);
    }
    foreach ($tables as $table) if (empty($cache[$table])) return false;
    return true;
}

// ── Dates ──────────────────────────────────────────────────────────────────────────────────────────────────

function report_valid_date(string $value): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $parsed !== false && $parsed->format('Y-m-d') === $value && $value >= '2000-01-01' && $value <= '2100-12-31';
}

// Validated inclusive date range from the query string; default = the current month. A reversed range is swapped.
function report_range(array $input): array
{
    $from = (string) ($input['from'] ?? '');
    $to = (string) ($input['to'] ?? '');
    $from = report_valid_date($from) ? $from : date('Y-m-01');
    $to = report_valid_date($to) ? $to : date('Y-m-t');
    if ($from > $to) [$from, $to] = [$to, $from];
    return ['from' => $from, 'to' => $to, 'to_next' => date('Y-m-d', strtotime($to . ' +1 day'))];
}

function report_range_label(string $from, string $to): string
{
    $fmt = static fn (string $d): string => date('M j, Y', strtotime($d));
    return $from === $to ? $fmt($from) : $fmt($from) . ' – ' . $fmt($to);
}

// Time buckets for trend charts: days for ranges up to 62 days, months up to 36 months, otherwise years.
function report_buckets(string $from, string $to): array
{
    $start = new DateTimeImmutable($from);
    $end = new DateTimeImmutable($to);
    $days = (int) $start->diff($end)->days + 1;
    $months = ((int) $end->format('Y') - (int) $start->format('Y')) * 12 + (int) $end->format('n') - (int) $start->format('n') + 1;
    if ($days <= 62) { $unit = 'day'; $format = '%Y-%m-%d'; $step = '+1 day'; $key = 'Y-m-d'; $label = 'M j'; $cursor = $start; }
    elseif ($months <= 36) { $unit = 'month'; $format = '%Y-%m'; $step = '+1 month'; $key = 'Y-m'; $label = 'M Y'; $cursor = $start->modify('first day of this month'); }
    else { $unit = 'year'; $format = '%Y'; $step = '+1 year'; $key = 'Y'; $label = 'Y'; $cursor = $start->modify('first day of january this year'); }
    $keys = [];
    $labels = [];
    while ($cursor <= $end) { $keys[] = $cursor->format($key); $labels[] = $cursor->format($label); $cursor = $cursor->modify($step); }
    return ['unit' => $unit, 'sql' => $format, 'keys' => $keys, 'labels' => $labels];
}

// [key => count] rows placed on the bucket keys (missing buckets are 0).
function report_series(array $rows, array $buckets, string $key = 'k', string $value = 'n'): array
{
    $map = [];
    foreach ($rows as $row) $map[(string) $row[$key]] = ($map[(string) $row[$key]] ?? 0) + (float) $row[$value];
    return array_map(static fn (string $k): float|int => isset($map[$k]) ? (fmod($map[$k], 1.0) === 0.0 ? (int) $map[$k] : $map[$k]) : 0, $buckets['keys']);
}

function report_percent(int|float $part, int|float $total): ?float
{
    return $total > 0 ? round($part / $total * 100, 1) : null;
}

// Rows [label => count] for GROUP BY results, keeping known labels (0 when empty) and any other stored value.
function report_breakdown(array $rows, array $labels = [], string $empty_label = 'Not recorded'): array
{
    $out = array_fill_keys(array_values($labels), 0);
    foreach ($rows as $row) {
        $key = (string) ($row['k'] ?? '');
        $name = $labels[$key] ?? ($key === '' ? $empty_label : $key);
        $out[$name] = ($out[$name] ?? 0) + (int) $row['n'];
    }
    return $out;
}

function report_age(?string $birth_date, string $as_of): ?int
{
    if (!$birth_date) return null;
    $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $birth_date);
    $date = new DateTimeImmutable($as_of);
    return $birth && $birth <= $date ? $birth->diff($date)->y : null;
}

// ── Residents ──────────────────────────────────────────────────────────────────────────────────────────────
// Population = ACTIVE resident profiles registered on or before the end date (the same definition as the Dashboard's
// "Active Residents"). Ages are computed as of the end date (or today, when the end date is in the future).

function report_resident_age_groups(): array
{
    return ['0_4' => '0–4', '5_12' => '5–12', '13_17' => '13–17', '18_59' => '18–59', '60_plus' => '60+', 'unknown' => 'Birth date not recorded'];
}

function report_residents_where(array $f, string $prefix = 'r'): array
{
    $where = ["r.status = 'active'", "r.created_at < :{$prefix}_to"];
    $params = [$prefix . '_to' => $f['to_next'] . ' 00:00:00'];
    if (($f['purok'] ?? '') !== '') { $where[] = "r.purok = :{$prefix}_purok"; $params[$prefix . '_purok'] = $f['purok']; }
    if (($f['sex'] ?? '') !== '') {
        if ($f['sex'] === 'unrecorded') $where[] = '(r.sex IS NULL OR r.sex = \'\')';
        else { $where[] = "r.sex = :{$prefix}_sex"; $params[$prefix . '_sex'] = $f['sex']; }
    }
    return [implode(' AND ', $where), $params];
}

// Total population (used by the Residents Report, the Analytics Overview and the Dashboard "Active Residents" card).
function report_residents_population(PDO $connection, ?array $f = null): int
{
    $f ??= ['to_next' => date('Y-m-d', strtotime('+1 day'))];
    [$where, $params] = report_residents_where($f);
    return (int) report_value($connection, "SELECT COUNT(*) FROM residents r WHERE $where", $params);
}

function report_residents_stats(PDO $connection, array $f): array
{
    [$where, $params] = report_residents_where($f);
    $as_of = min($f['to'], date('Y-m-d'));
    $age = "CASE WHEN r.birth_date IS NULL OR r.birth_date > :a0 THEN 'unknown' WHEN TIMESTAMPDIFF(YEAR, r.birth_date, :a1) < 5 THEN '0_4' WHEN TIMESTAMPDIFF(YEAR, r.birth_date, :a2) < 13 THEN '5_12' WHEN TIMESTAMPDIFF(YEAR, r.birth_date, :a3) < 18 THEN '13_17' WHEN TIMESTAMPDIFF(YEAR, r.birth_date, :a4) < 60 THEN '18_59' ELSE '60_plus' END";
    $age_params = ['a0' => $as_of, 'a1' => $as_of, 'a2' => $as_of, 'a3' => $as_of, 'a4' => $as_of];
    $total = (int) report_value($connection, "SELECT COUNT(*) FROM residents r WHERE $where", $params);
    $purok_rows = report_rows($connection, "SELECT r.purok AS k, COUNT(*) AS n FROM residents r WHERE $where GROUP BY r.purok ORDER BY r.purok", $params);
    $by_purok = report_breakdown($purok_rows, residents_purok_options());
    $by_sex = report_breakdown(report_rows($connection, "SELECT COALESCE(r.sex, '') AS k, COUNT(*) AS n FROM residents r WHERE $where GROUP BY k", $params), ['male' => 'Male', 'female' => 'Female', 'other' => 'Other', '' => 'Not recorded']);
    $by_age = report_breakdown(report_rows($connection, "SELECT $age AS k, COUNT(*) AS n FROM residents r WHERE $where GROUP BY k", $params + $age_params), report_resident_age_groups());
    $buckets = report_buckets($f['from'], $f['to']);
    [$new_where, $new_params] = report_residents_where($f, 'n');
    $new_params += ['n_from' => $f['from'] . ' 00:00:00'];
    $new_rows = report_rows($connection, "SELECT DATE_FORMAT(r.created_at, '{$buckets['sql']}') AS k, COUNT(*) AS n FROM residents r WHERE $new_where AND r.created_at >= :n_from GROUP BY k", $new_params);
    // PWD and solo parents: the current checkboxes on the resident profile (null until migration 20261007 is applied).
    $sectors = null;
    if (residents_sector_ready($connection)) {
        $sector_row = report_rows($connection, "SELECT COALESCE(SUM(r.is_pwd = 1), 0) AS pwd, COALESCE(SUM(r.is_solo_parent = 1), 0) AS solo FROM residents r WHERE $where", $params)[0] ?? ['pwd' => 0, 'solo' => 0];
        $sectors = ['PWD' => (int) $sector_row['pwd'], 'Solo Parents' => (int) $sector_row['solo']];
    }
    return [
        'total' => $total, 'as_of' => $as_of, 'by_purok' => $by_purok, 'by_sex' => $by_sex, 'by_age' => $by_age,
        'seniors' => $by_age[report_resident_age_groups()['60_plus']] ?? 0,
        'new_in_period' => array_sum(array_column($new_rows, 'n')), 'new_series' => report_series($new_rows, $buckets), 'buckets' => $buckets,
        'sectors' => $sectors,
        'unavailable' => ($sectors === null ? ['PWD and solo parents' => 'Resident profiles have no PWD or solo parent field yet.'] : []) + ['Registered voters' => 'Resident profiles have no voter field.'],
    ];
}

function report_residents_rows(PDO $connection, array $f, int $limit, int $offset): array
{
    [$where, $params] = report_residents_where($f);
    return report_rows($connection, "SELECT r.id, r.first_name, r.middle_name, r.last_name, r.suffix, r.purok, r.sex, r.birth_date, r.created_at FROM residents r WHERE $where ORDER BY r.last_name, r.first_name, r.id LIMIT :lim OFFSET :off", $params + ['lim' => $limit, 'off' => $offset]);
}

// ── Households ─────────────────────────────────────────────────────────────────────────────────────────────
// A household counts when it has at least one current ACTIVE member whose primary household it is (the same definition
// as the Dashboard's "Total Households"). Members = current active residents whose primary household it is.

function report_households_where(array $f, string $prefix = 'h'): array
{
    $where = ["h.created_at < :{$prefix}_to"];
    $params = [$prefix . '_to' => $f['to_next'] . ' 00:00:00'];
    if (($f['purok'] ?? '') !== '') { $where[] = "h.purok = :{$prefix}_purok"; $params[$prefix . '_purok'] = $f['purok']; }
    return [implode(' AND ', $where), $params];
}

function report_household_members_sql(): string
{
    return "(SELECT COUNT(*) FROM resident_households rh INNER JOIN residents mr ON mr.id = rh.resident_id WHERE rh.household_id = h.id AND rh.is_primary = 1 AND rh.left_at IS NULL AND mr.status = 'active')";
}

function report_households_total(PDO $connection, ?array $f = null): int
{
    $f ??= ['to_next' => date('Y-m-d', strtotime('+1 day'))];
    [$where, $params] = report_households_where($f);
    return (int) report_value($connection, 'SELECT COUNT(*) FROM households h WHERE ' . $where . ' AND ' . report_household_members_sql() . ' > 0', $params);
}

function report_households_stats(PDO $connection, array $f): array
{
    [$where, $params] = report_households_where($f);
    $members = report_household_members_sql();
    $rows = report_rows($connection, "SELECT h.purok AS k, COUNT(*) AS n, SUM($members) AS m FROM households h WHERE $where AND $members > 0 GROUP BY h.purok ORDER BY h.purok", $params);
    $total = array_sum(array_map(static fn (array $r): int => (int) $r['n'], $rows));
    $member_total = array_sum(array_map(static fn (array $r): int => (int) $r['m'], $rows));
    $empty = (int) report_value($connection, "SELECT COUNT(*) FROM households h WHERE $where AND $members = 0", $params);
    [$new_where, $new_params] = report_households_where($f, 'n');
    $new = (int) report_value($connection, "SELECT COUNT(*) FROM households h WHERE $new_where AND h.created_at >= :n_from", $new_params + ['n_from' => $f['from'] . ' 00:00:00']);
    return [
        'total' => $total, 'members' => $member_total, 'average' => $total > 0 ? round($member_total / $total, 2) : null,
        'by_purok' => report_breakdown($rows, residents_purok_options()), 'members_by_purok' => report_breakdown(array_map(static fn (array $r): array => ['k' => $r['k'], 'n' => $r['m']], $rows), residents_purok_options()),
        'without_members' => $empty, 'new_in_period' => $new,
    ];
}

function report_households_rows(PDO $connection, array $f, int $limit, int $offset): array
{
    [$where, $params] = report_households_where($f);
    $members = report_household_members_sql();
    return report_rows($connection, "SELECT h.id, h.household_no, h.purok, h.address, $members AS members, hr.first_name, hr.middle_name, hr.last_name, hr.suffix FROM households h LEFT JOIN residents hr ON hr.id = h.household_head_resident_id WHERE $where AND $members > 0 ORDER BY h.purok, h.household_no LIMIT :lim OFFSET :off", $params + ['lim' => $limit, 'off' => $offset]);
}

// ── Documents ──────────────────────────────────────────────────────────────────────────────────────────────
// Requests are counted by their request date. Rejected requests are listed as their own status only; every other figure
// excludes them. "Released in the period" counts releases dated in the period. No fee data exists.

function report_document_statuses(): array
{
    return ['pending' => 'Pending Review', 'approved' => 'Approved', 'released' => 'Released', 'rejected' => 'Rejected'];
}

function report_documents_where(array $f, string $prefix = 'd', string $column = 'd.requested_at'): array
{
    $where = ["$column >= :{$prefix}_from", "$column < :{$prefix}_to"];
    $params = [$prefix . '_from' => $f['from'] . ' 00:00:00', $prefix . '_to' => $f['to_next'] . ' 00:00:00'];
    if (($f['type'] ?? '') !== '') { $where[] = "d.document_type = :{$prefix}_type"; $params[$prefix . '_type'] = $f['type']; }
    return [implode(' AND ', $where), $params];
}

function report_documents_released(PDO $connection, array $f): int
{
    [$where, $params] = report_documents_where($f, 'r', 'd.released_at');
    return (int) report_value($connection, "SELECT COUNT(*) FROM document_requests d WHERE $where AND d.status = 'released'", $params);
}

function report_documents_stats(PDO $connection, array $f): array
{
    [$where, $params] = report_documents_where($f);
    $status_filter = ($f['status'] ?? '') !== '' ? ' AND d.status = :d_status' : '';
    $sp = $status_filter !== '' ? ['d_status' => $f['status']] : [];
    $by_status = report_breakdown(report_rows($connection, "SELECT d.status AS k, COUNT(*) AS n FROM document_requests d WHERE $where$status_filter GROUP BY d.status", $params + $sp), report_document_statuses());
    $counted = "$where AND d.status <> 'rejected'$status_filter";
    $total = (int) report_value($connection, "SELECT COUNT(*) FROM document_requests d WHERE $counted", $params + $sp);
    $by_type = report_breakdown(report_rows($connection, "SELECT d.document_type AS k, COUNT(*) AS n FROM document_requests d WHERE $counted GROUP BY d.document_type ORDER BY n DESC, k", $params + $sp));
    $buckets = report_buckets($f['from'], $f['to']);
    $trend = report_series(report_rows($connection, "SELECT DATE_FORMAT(d.requested_at, '{$buckets['sql']}') AS k, COUNT(*) AS n FROM document_requests d WHERE $counted GROUP BY k", $params + $sp), $buckets);
    [$rel_where, $rel_params] = report_documents_where($f, 'r', 'd.released_at');
    $processing = report_rows($connection, "SELECT COUNT(*) AS n, AVG(TIMESTAMPDIFF(MINUTE, d.requested_at, d.released_at)) AS minutes FROM document_requests d WHERE $rel_where AND d.status = 'released' AND d.released_at >= d.requested_at", $rel_params)[0];
    return [
        'total' => $total, 'rejected' => $by_status[report_document_statuses()['rejected']] ?? 0, 'by_status' => $by_status, 'by_type' => $by_type,
        'trend' => $trend, 'buckets' => $buckets, 'released_in_period' => report_documents_released($connection, $f),
        'avg_processing_days' => (int) $processing['n'] > 0 ? round((float) $processing['minutes'] / 1440, 1) : null, 'processing_count' => (int) $processing['n'],
        'unavailable' => ['Fees collected' => 'Document requests have no fee or payment fields yet.'],
    ];
}

function report_documents_types(PDO $connection): array
{
    return array_column(report_rows($connection, 'SELECT DISTINCT document_type FROM document_requests ORDER BY document_type'), 'document_type');
}

function report_documents_rows(PDO $connection, array $f, int $limit, int $offset): array
{
    [$where, $params] = report_documents_where($f);
    $status_filter = ($f['status'] ?? '') !== '' ? ' AND d.status = :d_status' : '';
    return report_rows($connection, "SELECT d.reference_code, d.document_type, d.status, d.requested_at, d.released_at, r.first_name, r.middle_name, r.last_name, r.suffix FROM document_requests d LEFT JOIN residents r ON r.id = d.resident_id WHERE $where$status_filter ORDER BY d.requested_at DESC, d.id DESC LIMIT :lim OFFSET :off", $params + ($status_filter !== '' ? ['d_status' => $f['status']] : []) + ['lim' => $limit, 'off' => $offset]);
}

function report_documents_count_rows(PDO $connection, array $f): int
{
    [$where, $params] = report_documents_where($f);
    $status_filter = ($f['status'] ?? '') !== '' ? ' AND d.status = :d_status' : '';
    return (int) report_value($connection, "SELECT COUNT(*) FROM document_requests d WHERE $where$status_filter", $params + ($status_filter !== '' ? ['d_status' => $f['status']] : []));
}

// ── Health ─────────────────────────────────────────────────────────────────────────────────────────────────
// Services are counted by service date; archived records never count and cancelled ones appear only as their own status.
// Due and overdue follow-ups are "as of today" (they do not depend on the date range; the other filters apply).

function report_health_where(array $f, bool $with_dates = true, string $prefix = 'h'): array
{
    $where = ['h.archived_at IS NULL'];
    $params = [];
    if ($with_dates) { $where[] = "h.service_date BETWEEN :{$prefix}_from AND :{$prefix}_to"; $params += [$prefix . '_from' => $f['from'], $prefix . '_to' => $f['to']]; }
    foreach (['service' => 'h.service', 'worker' => 'h.health_worker', 'purok' => 'r.purok'] as $key => $column) {
        if (($f[$key] ?? '') !== '') { $where[] = "$column = :{$prefix}_$key"; $params[$prefix . '_' . $key] = $f[$key]; }
    }
    // A Health Worker with assigned Puroks counts only residents of those Puroks (same as the Health list).
    require_once __DIR__ . '/health.php';
    [$scope_sql, $scope_params] = residents_purok_scope_sql(db(), 'r.purok', $prefix . '_scope');
    if ($scope_params !== []) { $where[] = $scope_sql; $params += $scope_params; }
    return [implode(' AND ', $where), $params];
}

function report_health_stats(PDO $connection, array $f): array
{
    require_once __DIR__ . '/health.php';
    [$where, $params] = report_health_where($f);
    $from = 'FROM health_records h INNER JOIN residents r ON r.id = h.resident_id';
    $by_status = report_breakdown(report_rows($connection, "SELECT h.status AS k, COUNT(*) AS n $from WHERE $where GROUP BY h.status", $params), health_statuses());
    $counted = "$where AND h.status <> 'cancelled'";
    $total = (int) report_value($connection, "SELECT COUNT(*) $from WHERE $counted", $params);
    $by_service = report_breakdown(report_rows($connection, "SELECT h.service AS k, COUNT(*) AS n $from WHERE $counted GROUP BY h.service", $params), health_services());
    $by_purok = report_breakdown(report_rows($connection, "SELECT r.purok AS k, COUNT(*) AS n $from WHERE $counted GROUP BY r.purok ORDER BY r.purok", $params), residents_purok_options());
    $by_worker = report_breakdown(report_rows($connection, "SELECT h.health_worker AS k, COUNT(*) AS n $from WHERE $counted GROUP BY h.health_worker ORDER BY n DESC, k", $params));
    $buckets = report_buckets($f['from'], $f['to']);
    $trend_rows = report_rows($connection, "SELECT h.service AS s, DATE_FORMAT(h.service_date, '{$buckets['sql']}') AS k, COUNT(*) AS n $from WHERE $counted GROUP BY s, k", $params);
    $series = [];
    foreach (health_services() as $key => $label) {
        $rows = array_values(array_filter($trend_rows, static fn (array $row): bool => $row['s'] === $key));
        if ($rows !== []) $series[$label] = report_series($rows, $buckets);
    }
    [$due_where, $due_params] = report_health_where($f, false, 'u');
    // Due today / overdue: same rule as the Health list (health_due_sql), by the date that is due.
    $due_date = "CASE WHEN h.status = 'scheduled' AND h.service_date <= CURDATE() THEN h.service_date ELSE h.follow_up_date END";
    $due = report_rows($connection, "SELECT SUM($due_date = CURDATE()) AS due_today, SUM($due_date < CURDATE()) AS overdue $from WHERE $due_where AND " . health_due_sql('h'), $due_params)[0];
    return [
        'total' => $total, 'by_status' => $by_status, 'by_service' => $by_service, 'by_purok' => $by_purok, 'by_worker' => $by_worker,
        'trend' => $series, 'buckets' => $buckets, 'due_today' => (int) $due['due_today'], 'overdue' => (int) $due['overdue'],
        'completed' => $by_status[health_statuses()['completed']] ?? 0,
    ];
}

function report_health_rows(PDO $connection, array $f, int $limit, int $offset): array
{
    [$where, $params] = report_health_where($f);
    $status = ($f['status'] ?? '') !== '' ? ' AND h.status = :h_status' : '';
    return report_rows($connection, "SELECT h.record_no, h.service, h.service_details, h.health_worker, h.service_date, h.status, h.follow_up_date, r.first_name, r.middle_name, r.last_name, r.suffix, r.purok FROM health_records h INNER JOIN residents r ON r.id = h.resident_id WHERE $where$status ORDER BY h.service_date DESC, h.id DESC LIMIT :lim OFFSET :off", $params + ($status !== '' ? ['h_status' => $f['status']] : []) + ['lim' => $limit, 'off' => $offset]);
}

function report_health_count_rows(PDO $connection, array $f): int
{
    [$where, $params] = report_health_where($f);
    $status = ($f['status'] ?? '') !== '' ? ' AND h.status = :h_status' : '';
    return (int) report_value($connection, "SELECT COUNT(*) FROM health_records h INNER JOIN residents r ON r.id = h.resident_id WHERE $where$status", $params + ($status !== '' ? ['h_status' => $f['status']] : []));
}

// ── Disaster Management ────────────────────────────────────────────────────────────────────────────────────
// DRR records are counted by record date; archived records never count and cancelled ones appear only as their own
// status. Evacuations count check-ins (not removed) that arrived in the period; relief counts distributions (not
// cancelled) given in the period; damage counts current assessments dated in the period.

function report_disaster_where(array $f, string $prefix = 'd'): array
{
    $where = ['d.archived_at IS NULL', "d.record_date BETWEEN :{$prefix}_from AND :{$prefix}_to"];
    $params = [$prefix . '_from' => $f['from'], $prefix . '_to' => $f['to']];
    if (($f['type'] ?? '') !== '') { $where[] = "d.record_type = :{$prefix}_type"; $params[$prefix . '_type'] = $f['type']; }
    if (($f['area'] ?? '') !== '') { $where[] = "d.area_id = :{$prefix}_area"; $params[$prefix . '_area'] = (int) $f['area']; }
    return [implode(' AND ', $where), $params];
}

// Incidents that are active right now (planned, ongoing or monitoring; not archived).
function report_disaster_active_incidents(PDO $connection): int
{
    return (int) report_value($connection, "SELECT COUNT(*) FROM drr_records WHERE record_type = 'incident' AND archived_at IS NULL AND status IN ('planned', 'ongoing', 'monitoring')");
}

function report_disaster_stats(PDO $connection, array $f): array
{
    require_once __DIR__ . '/disaster.php';
    [$where, $params] = report_disaster_where($f);
    $from = 'FROM drr_records d INNER JOIN drr_areas a ON a.id = d.area_id';
    $by_status = report_breakdown(report_rows($connection, "SELECT d.status AS k, COUNT(*) AS n $from WHERE $where GROUP BY d.status", $params), disaster_statuses());
    $counted = "$where AND d.status <> 'cancelled'";
    $total = (int) report_value($connection, "SELECT COUNT(*) $from WHERE $counted", $params);
    $by_type = report_breakdown(report_rows($connection, "SELECT d.record_type AS k, COUNT(*) AS n $from WHERE $counted GROUP BY d.record_type", $params), disaster_types());
    $incidents_by_area = report_breakdown(report_rows($connection, "SELECT a.name AS k, COUNT(*) AS n $from WHERE $counted AND d.record_type = 'incident' GROUP BY a.id, a.name ORDER BY n DESC, a.name", $params));
    $affected = report_rows($connection, "SELECT COUNT(*) AS incidents, COALESCE(SUM(d.affected_families), 0) AS families, COALESCE(SUM(d.affected_persons), 0) AS persons, SUM(d.affected_families IS NULL) AS no_families $from WHERE $counted AND d.record_type = 'incident'", $params)[0];
    $buckets = report_buckets($f['from'], $f['to']);
    $trend_rows = report_rows($connection, "SELECT d.record_type AS s, DATE_FORMAT(d.record_date, '{$buckets['sql']}') AS k, COUNT(*) AS n $from WHERE $counted GROUP BY s, k", $params);
    $series = [];
    foreach (disaster_types() as $key => $label) {
        $rows = array_values(array_filter($trend_rows, static fn (array $row): bool => $row['s'] === $key));
        if ($rows !== []) $series[$label] = report_series($rows, $buckets);
    }
    $stats = [
        'total' => $total, 'by_status' => $by_status, 'by_type' => $by_type, 'incidents_by_area' => $incidents_by_area,
        'incidents' => (int) $affected['incidents'], 'affected_families' => (int) $affected['families'], 'affected_persons' => (int) $affected['persons'], 'incidents_without_counts' => (int) $affected['no_families'],
        'trend' => $series, 'buckets' => $buckets, 'active_incidents' => report_disaster_active_incidents($connection),
        'evacuees' => null, 'relief' => null, 'relief_items' => null, 'damage' => null,
    ];
    $range = ['r_from' => $f['from'] . ' 00:00:00', 'r_to' => $f['to_next'] . ' 00:00:00'];
    if (report_table_exists($connection, 'drr_evacuations', 'drr_evacuation_centers')) {
        $stats['evacuees'] = report_rows($connection, 'SELECT c.name, COUNT(*) AS families, SUM(e.family_members) AS persons, SUM(e.departed_at IS NULL) AS families_now, SUM(IF(e.departed_at IS NULL, e.family_members, 0)) AS persons_now FROM drr_evacuations e INNER JOIN drr_evacuation_centers c ON c.id = e.center_id WHERE e.archived_at IS NULL AND e.arrived_at >= :r_from AND e.arrived_at < :r_to GROUP BY c.id, c.name ORDER BY c.name', $range);
    }
    // Relief for incidents: Given records of Relief & Assistance linked to an incident (dated in the period).
    if (report_table_exists($connection, 'assistance_distributions', 'assistance_items', 'inventory_items') && (int) report_value($connection, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assistance_distributions' AND COLUMN_NAME = 'incident_id'") === 1) {
        $dr = ['r_from' => $f['from'], 'r_to' => $f['to']];
        $stats['relief'] = report_rows($connection, "SELECT d.reference_no, d.title, COUNT(DISTINCT a.id) AS distributions, COUNT(DISTINCT CONCAT(IF(a.household_id IS NULL, 'r', 'h'), COALESCE(a.household_id, a.resident_id))) AS families, COALESCE(SUM(a.cash_amount), 0) AS cash FROM assistance_distributions a INNER JOIN drr_records d ON d.id = a.incident_id WHERE a.status = 'given' AND a.given_on BETWEEN :r_from AND :r_to GROUP BY d.id, d.reference_no, d.title ORDER BY d.reference_no", $dr);
        $stats['relief_items'] = report_rows($connection, "SELECT i.name, x.unit, SUM(x.quantity) AS quantity FROM assistance_items x INNER JOIN assistance_distributions a ON a.id = x.distribution_id INNER JOIN inventory_items i ON i.id = x.item_id WHERE a.incident_id IS NOT NULL AND a.status = 'given' AND a.given_on BETWEEN :r_from AND :r_to GROUP BY i.id, i.name, x.unit ORDER BY i.name", $dr);
    }
    if (report_table_exists($connection, 'drr_damage_assessments')) {
        $stats['damage'] = report_rows($connection, 'SELECT a.name, COUNT(*) AS assessments, SUM(g.houses_partial) AS partial, SUM(g.houses_total) AS total FROM drr_damage_assessments g INNER JOIN drr_areas a ON a.id = g.area_id WHERE g.archived_at IS NULL AND g.assessed_on BETWEEN :r_from AND :r_to GROUP BY a.id, a.name ORDER BY a.name', ['r_from' => $f['from'], 'r_to' => $f['to']]);
    }
    return $stats;
}

function report_disaster_rows(PDO $connection, array $f, int $limit, int $offset): array
{
    [$where, $params] = report_disaster_where($f);
    return report_rows($connection, "SELECT d.reference_no, d.title, d.record_type, d.record_date, d.status, d.affected_families, d.affected_persons, a.name AS area_name FROM drr_records d INNER JOIN drr_areas a ON a.id = d.area_id WHERE $where ORDER BY d.record_date DESC, d.id DESC LIMIT :lim OFFSET :off", $params + ['lim' => $limit, 'off' => $offset]);
}

function report_disaster_count_rows(PDO $connection, array $f): int
{
    [$where, $params] = report_disaster_where($f);
    return (int) report_value($connection, "SELECT COUNT(*) FROM drr_records d WHERE $where", $params);
}

// ── Inventory ──────────────────────────────────────────────────────────────────────────────────────────────
// Stock figures are current (as of today) for items that are not archived. Low stock = supplies whose quantity on
// hand is at or below their reorder level. Value = unit cost × quantity owned (on hand + borrowed out); items without a
// unit cost are counted separately. Borrowing in the period counts borrow records dated in the date range.

function report_inventory_where(array $f, string $prefix = 'i'): array
{
    $where = ['i.archived_at IS NULL'];
    $params = [];
    foreach (['category' => 'i.category_id', 'location' => 'i.location_id'] as $key => $column) if (($f[$key] ?? '') !== '') { $where[] = "$column = :{$prefix}_$key"; $params[$prefix . '_' . $key] = (int) $f[$key]; }
    foreach (['item_type' => 'i.item_type', 'status' => 'i.status'] as $key => $column) if (($f[$key] ?? '') !== '') { $where[] = "$column = :{$prefix}_$key"; $params[$prefix . '_' . $key] = $f[$key]; }
    return [implode(' AND ', $where), $params];
}

function report_inventory_borrowed_sql(): string
{
    return '(SELECT COALESCE(SUM(b.quantity), 0) FROM inventory_borrow_records b WHERE b.item_id = i.id AND b.actual_return_date IS NULL)';
}

// Low-stock items (used by the Inventory Report and the Analytics Overview).
function report_inventory_low_stock(PDO $connection, array $f = []): int
{
    [$where, $params] = report_inventory_where($f);
    return (int) report_value($connection, "SELECT COUNT(*) FROM inventory_items i WHERE $where AND i.item_type = 'supply' AND i.reorder_level IS NOT NULL AND i.quantity <= i.reorder_level", $params);
}

function report_inventory_stats(PDO $connection, array $f): array
{
    require_once __DIR__ . '/inventory.php';
    [$where, $params] = report_inventory_where($f);
    $tracking = report_table_exists($connection, 'inventory_borrow_records');
    $owned = $tracking ? '(i.quantity + ' . report_inventory_borrowed_sql() . ')' : 'i.quantity';
    $from = 'FROM inventory_items i INNER JOIN inventory_categories c ON c.id = i.category_id INNER JOIN inventory_locations l ON l.id = i.location_id';
    $total = (int) report_value($connection, "SELECT COUNT(*) $from WHERE $where", $params);
    $by_category = report_rows($connection, "SELECT c.name AS k, COUNT(*) AS n, SUM(IF(i.unit_cost IS NULL, 0, i.unit_cost * $owned)) AS value, SUM(i.unit_cost IS NULL) AS no_cost $from WHERE $where GROUP BY c.id, c.name ORDER BY c.name", $params);
    $by_status = report_breakdown(report_rows($connection, "SELECT i.status AS k, COUNT(*) AS n $from WHERE $where GROUP BY i.status", $params), inventory_statuses());
    $by_location = report_breakdown(report_rows($connection, "SELECT l.name AS k, COUNT(*) AS n $from WHERE $where GROUP BY l.id, l.name ORDER BY n DESC, l.name", $params));
    $today = date('Y-m-d');
    $soon = date('Y-m-d', strtotime('+30 days'));
    $expiry = report_rows($connection, "SELECT SUM(i.expiry_date BETWEEN :t1 AND :s1) AS expiring, SUM(i.expiry_date < :t2) AS expired $from WHERE $where AND i.expiry_date IS NOT NULL", $params + ['t1' => $today, 's1' => $soon, 't2' => $today])[0];
    $value = report_rows($connection, "SELECT COALESCE(SUM(i.unit_cost * $owned), 0) AS value, SUM(i.unit_cost IS NULL) AS no_cost $from WHERE $where", $params)[0];
    $stats = [
        'total' => $total, 'by_category' => report_breakdown($by_category), 'value_by_category' => array_column(array_map(static fn (array $r): array => [$r['k'], $r['value']], $by_category), 1, 0),
        'no_cost_by_category' => array_column(array_map(static fn (array $r): array => [$r['k'], (int) $r['no_cost']], $by_category), 1, 0),
        'by_status' => $by_status, 'by_location' => $by_location, 'low_stock' => report_inventory_low_stock($connection, $f),
        'expiring' => (int) $expiry['expiring'], 'expired' => (int) $expiry['expired'], 'total_value' => (string) $value['value'], 'items_without_cost' => (int) $value['no_cost'],
        'borrowed_now' => null, 'overdue' => null, 'borrowed_in_period' => null,
    ];
    if ($tracking) {
        $borrow = report_rows($connection, "SELECT COUNT(*) AS open_records, COALESCE(SUM(b.quantity), 0) AS pieces, SUM(b.expected_return_date < :t3) AS overdue FROM inventory_borrow_records b INNER JOIN inventory_items i ON i.id = b.item_id WHERE b.actual_return_date IS NULL AND $where", $params + ['t3' => $today])[0];
        $stats['borrowed_now'] = (int) $borrow['open_records'];
        $stats['borrowed_pieces'] = (int) $borrow['pieces'];
        $stats['overdue'] = (int) $borrow['overdue'];
        $stats['borrowed_in_period'] = (int) report_value($connection, "SELECT COUNT(*) FROM inventory_borrow_records b INNER JOIN inventory_items i ON i.id = b.item_id WHERE b.date_borrowed BETWEEN :b_from AND :b_to AND $where", $params + ['b_from' => $f['from'], 'b_to' => $f['to']]);
    }
    return $stats;
}

function report_inventory_rows(PDO $connection, array $f, int $limit, int $offset): array
{
    [$where, $params] = report_inventory_where($f);
    $borrowed = report_table_exists($connection, 'inventory_borrow_records') ? report_inventory_borrowed_sql() : '0';
    return report_rows($connection, "SELECT i.item_code, i.name, i.item_type, i.quantity, i.unit, i.status, i.reorder_level, i.expiry_date, i.unit_cost, $borrowed AS borrowed_out, c.name AS category_name, l.name AS location_name FROM inventory_items i INNER JOIN inventory_categories c ON c.id = i.category_id INNER JOIN inventory_locations l ON l.id = i.location_id WHERE $where ORDER BY c.name, i.name, i.id LIMIT :lim OFFSET :off", $params + ['lim' => $limit, 'off' => $offset]);
}

// ── Financial Management ───────────────────────────────────────────────────────────────────────────────────
// Only posted collections (by transaction date) and released disbursements (by release date) count. The fund balance
// and pending approvals are current figures from includes/finance.php (the same functions as the module's cards).

function report_finance_where(array $f, string $prefix = 'f'): array
{
    $where = [];
    $params = [];
    if (($f['category'] ?? '') !== '') { $where[] = "t.category_id = :{$prefix}_category"; $params[$prefix . '_category'] = (int) $f['category']; }
    return [$where === [] ? '1 = 1' : implode(' AND ', $where), $params];
}

// SQL condition for transactions that count in the period (placeholders are unique per prefix).
function report_finance_counted(string $prefix): string
{
    return "((t.type = 'income' AND t.status = 'posted' AND t.transaction_date BETWEEN :{$prefix}_f1 AND :{$prefix}_t1) OR (t.type = 'expense' AND t.status = 'released' AND t.release_date BETWEEN :{$prefix}_f2 AND :{$prefix}_t2))";
}

function report_finance_counted_params(array $f, string $prefix): array
{
    return [$prefix . '_f1' => $f['from'], $prefix . '_t1' => $f['to'], $prefix . '_f2' => $f['from'], $prefix . '_t2' => $f['to']];
}

// Collections and disbursements in the period (used by the Financial Report and the Analytics Overview).
function report_finance_totals(PDO $connection, array $f): array
{
    [$where, $params] = report_finance_where($f);
    $row = report_rows($connection, "SELECT COALESCE(SUM(IF(t.type = 'income', t.amount, 0)), 0) AS collections, COALESCE(SUM(IF(t.type = 'expense', t.amount, 0)), 0) AS disbursements, COUNT(*) AS n FROM finance_transactions t WHERE $where AND " . report_finance_counted('c'), $params + report_finance_counted_params($f, 'c'))[0];
    return ['collections' => (string) $row['collections'], 'disbursements' => (string) $row['disbursements'], 'count' => (int) $row['n']];
}

function report_finance_stats(PDO $connection, array $f): array
{
    require_once __DIR__ . '/finance.php';
    [$where, $params] = report_finance_where($f);
    $totals = report_finance_totals($connection, $f);
    $buckets = report_buckets($f['from'], $f['to']);
    $date = "CASE WHEN t.type = 'income' THEN t.transaction_date ELSE t.release_date END";
    $trend = report_rows($connection, "SELECT t.type AS s, DATE_FORMAT($date, '{$buckets['sql']}') AS k, SUM(t.amount) AS n FROM finance_transactions t WHERE $where AND " . report_finance_counted('m') . ' GROUP BY s, k', $params + report_finance_counted_params($f, 'm'));
    $by_category = report_rows($connection, "SELECT c.type, c.name AS k, COUNT(*) AS entries, SUM(t.amount) AS total FROM finance_transactions t INNER JOIN finance_categories c ON c.id = t.category_id WHERE $where AND " . report_finance_counted('g') . ' GROUP BY c.id, c.type, c.name ORDER BY c.type, c.sort_order, c.name', $params + report_finance_counted_params($f, 'g'));
    $summary = finance_summary($connection);
    $year = (int) substr($f['to'], 0, 4);
    return [
        'collections' => $totals['collections'], 'disbursements' => $totals['disbursements'], 'net' => finance_from_cents(finance_cents($totals['collections']) - finance_cents($totals['disbursements'])), 'count' => $totals['count'],
        'collections_series' => report_series(array_filter($trend, static fn (array $r): bool => $r['s'] === 'income'), $buckets), 'disbursements_series' => report_series(array_filter($trend, static fn (array $r): bool => $r['s'] === 'expense'), $buckets), 'buckets' => $buckets,
        'income_by_category' => array_values(array_filter($by_category, static fn (array $r): bool => $r['type'] === 'income')), 'expense_by_category' => array_values(array_filter($by_category, static fn (array $r): bool => $r['type'] === 'expense')),
        'fund_balance' => $summary['balance'], 'pending' => $summary['pending'], 'opening' => finance_opening($connection),
        'budget_year' => $year, 'budget' => finance_budget_ready($connection) ? finance_budget_rows($connection, $year) : null,
    ];
}

function report_finance_rows(PDO $connection, array $f, int $limit, int $offset): array
{
    [$where, $params] = report_finance_where($f);
    return report_rows($connection, "SELECT t.reference_no, t.type, t.description, t.payor_or_payee, t.amount, t.status, c.name AS category_name, CASE WHEN t.type = 'income' THEN t.transaction_date ELSE t.release_date END AS book_date FROM finance_transactions t INNER JOIN finance_categories c ON c.id = t.category_id WHERE $where AND " . report_finance_counted('r') . ' ORDER BY book_date DESC, t.id DESC LIMIT :lim OFFSET :off', $params + report_finance_counted_params($f, 'r') + ['lim' => $limit, 'off' => $offset]);
}

// ── Peace and Order (confidential; Secretary and Super Admin) ──────────────────────────────────────────────
// Counts for the Analytics Overview, using the Reports module's own filter builder (includes/reports.php).

function report_peace_counts(PDO $connection, array $f): array
{
    require_once __DIR__ . '/reports.php';
    $filters = ['from' => $f['from'], 'to' => $f['to'], 'status' => '', 'group' => '', 'q' => ''];
    $out = [];
    foreach (['complaints', 'blotter', 'hearings'] as $category) {
        [$where, $params] = reports_where($category, $filters);
        $out[$category] = (int) (reports_query(report_prepare($connection), 'SELECT COUNT(*) AS n ' . reports_from_sql($category) . " WHERE $where", $params)[0]['n'] ?? 0);
    }
    return $out;
}
