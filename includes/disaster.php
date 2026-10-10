<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/residents.php';

// Disaster Management / BDRRMC (drr_areas, drr_records; migration 20260929_disaster_records). The Barangay Kagawad
// (BDRRMC) and the System Administrator add and edit; the Secretary views only (the sidebar item's other role). Health
// Workers, the Treasurer and residents see Disaster Info (disaster_info.php) instead. Records are archived, never deleted.

function disaster_can_manage(): bool
{
    return has_role('super_admin', 'official');
}

function disaster_can_view(): bool
{
    return can_access_navigation('disaster');
}

// Names of vulnerable residents (seniors, young children, PWD, solo parents, health priority): System Administrator,
// BDRRMC and Secretary.
function disaster_can_view_vulnerable(): bool
{
    return disaster_can_view() && !has_role('treasurer');
}

function disaster_require_manage(): void
{
    require_auth();
    if (!disaster_can_manage()) { http_response_code(403); exit('Access denied.'); }
}

function disaster_require_view(): void
{
    require_auth();
    if (!disaster_can_view()) { http_response_code(403); exit('Access denied.'); }
}

function disaster_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $check = $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('drr_areas', 'drr_records')");
        $ready = (int) $check->fetchColumn() === 2;
    }
    return $ready;
}

// ── Labels ─────────────────────────────────────────────────────────────────────

function disaster_types(): array
{
    return ['preparedness' => 'Preparedness', 'incident' => 'Incident', 'response' => 'Response', 'drill' => 'Drill', 'mitigation' => 'Mitigation'];
}

function disaster_statuses(): array
{
    return ['planned' => 'Planned', 'ongoing' => 'Ongoing', 'monitoring' => 'Monitoring', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
}

// Warning levels used for incidents (PAGASA-style colour-coded warnings).
function disaster_alert_levels(): array
{
    return ['advisory' => 'Advisory', 'yellow' => 'Yellow Warning', 'orange' => 'Orange Warning', 'red' => 'Red Warning'];
}

// Badge colours reuse the existing tones (same as the former sample page).
function disaster_status_badge(string $status): string
{
    $tone = ['planned' => 'pending', 'ongoing' => 'moved', 'monitoring' => 'pending', 'completed' => 'active', 'cancelled' => 'inactive'][$status] ?? 'inactive';
    return '<span class="resident-status resident-status-' . e($tone) . '">' . e(disaster_statuses()[$status] ?? ucfirst($status)) . '</span>';
}

function disaster_alert_badge(?string $level): string
{
    if ($level === null || !isset(disaster_alert_levels()[$level])) return '';
    return '<span class="drr-alert drr-alert-' . e($level) . '">' . e(disaster_alert_levels()[$level]) . '</span>';
}

function disaster_format_date(?string $value): string
{
    if (!$value) return '';
    $date = date_create($value);
    return $date ? $date->format('M j, Y') : $value;
}

function disaster_valid_date(string $value): ?DateTimeImmutable
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $parsed && $parsed->format('Y-m-d') === $value ? $parsed : null;
}

// ── Areas ──────────────────────────────────────────────────────────────────────

function disaster_area_types(): array
{
    return ['all_puroks' => 'Whole barangay', 'purok' => 'Purok', 'place' => 'Place'];
}

// Areas offered in the form: active ones, plus the record's current area even when it was deactivated.
function disaster_areas(PDO $connection, ?int $include_id = null, bool $all = false): array
{
    $statement = $connection->prepare('SELECT id, name, area_type, purok, is_active FROM drr_areas' . ($all ? '' : ' WHERE is_active = 1 OR id = :include') . ' ORDER BY sort_order, name');
    $statement->execute($all ? [] : ['include' => $include_id ?? 0]);
    return $statement->fetchAll();
}

function disaster_area(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare('SELECT id, name, area_type, purok, is_active FROM drr_areas WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// ── Records ────────────────────────────────────────────────────────────────────

function disaster_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $statement = $connection->prepare('SELECT d.*, a.name AS area_name, a.is_active AS area_active, cu.name AS created_by_name, uu.name AS updated_by_name, au.name AS archived_by_name FROM drr_records d INNER JOIN drr_areas a ON a.id = d.area_id LEFT JOIN users cu ON cu.id = d.created_by LEFT JOIN users uu ON uu.id = d.updated_by LEFT JOIN users au ON au.id = d.archived_by WHERE d.id = :id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// Next reference number for the current year: DRR-2026-01, DRR-2026-02, … (restarts at 01 every January).
// Called inside the saving transaction; the unique keys are the final guard.
function disaster_next_reference(PDO $connection): array
{
    $year = (int) date('Y');
    $statement = $connection->prepare('SELECT COALESCE(MAX(ref_seq), 0) FROM drr_records WHERE ref_year = :year FOR UPDATE');
    $statement->execute(['year' => $year]);
    $seq = (int) $statement->fetchColumn() + 1;
    return ['reference_no' => sprintf('DRR-%d-%02d', $year, $seq), 'ref_year' => $year, 'ref_seq' => $seq];
}

function disaster_fields(): array
{
    return ['title', 'record_type', 'area_id', 'record_date', 'status', 'description', 'affected_families', 'affected_persons', 'alert_level', 'incident_details'];
}

function disaster_incident_fields(): array
{
    return ['affected_families', 'affected_persons', 'alert_level', 'incident_details'];
}

// Server-side validation. $existing is the stored record when editing (a deactivated area already on the record is kept).
function disaster_validate(PDO $connection, array $input, ?array $existing = null): array
{
    $values = [
        'title' => residents_collapse($input['title'] ?? ''),
        'record_type' => (string) ($input['record_type'] ?? ''),
        'area_id' => trim((string) ($input['area_id'] ?? '')),
        'record_date' => trim((string) ($input['record_date'] ?? '')),
        'status' => (string) ($input['status'] ?? ''),
        'description' => trim((string) ($input['description'] ?? '')),
        'affected_families' => trim((string) ($input['affected_families'] ?? '')),
        'affected_persons' => trim((string) ($input['affected_persons'] ?? '')),
        'alert_level' => (string) ($input['alert_level'] ?? ''),
        'incident_details' => trim((string) ($input['incident_details'] ?? '')),
    ];
    $errors = [];
    $today = new DateTimeImmutable('today');

    if (mb_strlen($values['title']) < 3 || mb_strlen($values['title']) > 150) $errors['title'] = 'Enter a title of 3 to 150 characters.';
    if (!array_key_exists($values['record_type'], disaster_types())) $errors['record_type'] = 'Select a type.';

    $area_id = filter_var($values['area_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $area = $area_id ? disaster_area($connection, $area_id) : null;
    if ($area === null) $errors['area_id'] = 'Select an area.';
    elseif ((int) $area['is_active'] !== 1 && (int) ($existing['area_id'] ?? 0) !== (int) $area_id) $errors['area_id'] = 'This area is no longer in use. Select another area.';
    else $values['area_id'] = (int) $area_id;

    $date = disaster_valid_date($values['record_date']);
    if ($date === null) $errors['record_date'] = 'Enter a valid date.';
    elseif ($date < new DateTimeImmutable('2000-01-01')) $errors['record_date'] = 'Enter a realistic date.';
    elseif ($date > $today->modify('+1 year')) $errors['record_date'] = 'Enter a date within the next year.';
    if (!array_key_exists($values['status'], disaster_statuses())) $errors['status'] = 'Select a status.';
    elseif ($values['status'] === 'completed' && $date !== null && $date > $today) $errors['status'] = 'A record dated in the future cannot be Completed yet. Use Planned.';
    if (mb_strlen($values['description']) > 2000) $errors['description'] = 'The description must not exceed 2,000 characters.';

    if ($values['record_type'] === 'incident') {
        foreach (['affected_families' => [10000, 'families'], 'affected_persons' => [100000, 'persons']] as $field => [$max, $noun]) {
            if ($values[$field] === '') continue;
            $number = filter_var($values[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $max]]);
            if ($number === false) $errors[$field] = 'Enter the number of affected ' . $noun . ' (0 to ' . number_format($max) . ').';
            else $values[$field] = $number;
        }
        if (!isset($errors['affected_families']) && !isset($errors['affected_persons']) && is_int($values['affected_families']) && is_int($values['affected_persons']) && $values['affected_persons'] < $values['affected_families']) $errors['affected_persons'] = 'Affected persons cannot be fewer than affected families.';
        if ($values['alert_level'] !== '' && !array_key_exists($values['alert_level'], disaster_alert_levels())) $errors['alert_level'] = 'Select a warning level.';
        if (mb_strlen($values['incident_details']) > 2000) $errors['incident_details'] = 'Incident details must not exceed 2,000 characters.';
    } else {
        foreach (disaster_incident_fields() as $field) $values[$field] = '';   // only kept for incidents
    }

    foreach (['description', 'affected_families', 'affected_persons', 'alert_level', 'incident_details'] as $nullable) if ($values[$nullable] === '') $values[$nullable] = null;
    return ['values' => $values, 'errors' => $errors];
}

function disaster_audit(PDO $connection, int $id, string $action, array $details = []): void
{
    residents_audit($connection, 'disaster', $id, $action, $details);
}

// ── List state (search, filters, sorting, paging — all kept in the URL) ──────────

function disaster_sort_columns(): array
{
    return [
        'reference' => 'd.ref_year %1$s, d.ref_seq',
        'title' => 'd.title',
        'type' => "FIELD(d.record_type, 'preparedness', 'incident', 'response', 'drill', 'mitigation')",
        'area' => 'a.name',
        'date' => 'd.record_date',
        'status' => "FIELD(d.status, 'planned', 'ongoing', 'monitoring', 'completed', 'cancelled')",
    ];
}

// Validated list settings from the query string (unknown values fall back to the defaults: newest date first).
function disaster_list_state(array $input): array
{
    $date = static fn (string $value): string => disaster_valid_date($value) ? $value : '';
    $state = [
        'q' => mb_substr(residents_collapse((string) ($input['q'] ?? '')), 0, 100),
        'status' => (string) ($input['status'] ?? ''),
        'type' => (string) ($input['type'] ?? ''),
        'area' => filter_var($input['area'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
        'from' => $date((string) ($input['from'] ?? '')),
        'to' => $date((string) ($input['to'] ?? '')),
        'sort' => (string) ($input['sort'] ?? 'date'),
        'dir' => (string) ($input['dir'] ?? 'desc'),
        'page' => max(1, (int) ($input['page'] ?? 1)),
    ];
    if ($state['status'] !== 'archived' && !array_key_exists($state['status'], disaster_statuses())) $state['status'] = '';
    if (!array_key_exists($state['type'], disaster_types())) $state['type'] = '';
    if ($state['from'] !== '' && $state['to'] !== '' && $state['from'] > $state['to']) [$state['from'], $state['to']] = [$state['to'], $state['from']];
    if (!array_key_exists($state['sort'], disaster_sort_columns())) $state['sort'] = 'date';
    if (!in_array($state['dir'], ['asc', 'desc'], true)) $state['dir'] = 'desc';
    return $state;
}

// Query string of the non-default settings (so shared links stay short).
function disaster_query_string(array $state): string
{
    return http_build_query(array_filter([
        'q' => $state['q'], 'status' => $state['status'], 'type' => $state['type'], 'area' => $state['area'], 'from' => $state['from'], 'to' => $state['to'],
        'sort' => $state['sort'] === 'date' ? '' : $state['sort'], 'dir' => $state['dir'] === 'desc' ? '' : $state['dir'],
        'page' => ($state['page'] ?? 1) > 1 ? $state['page'] : '',
    ], static fn ($value): bool => $value !== null && $value !== ''));
}

function disaster_state_filtered(array $state): bool
{
    return $state['q'] !== '' || $state['status'] !== '' || $state['type'] !== '' || $state['area'] !== null || $state['from'] !== '' || $state['to'] !== '';
}

// WHERE conditions and parameters for the list. Archived records appear only under the Archived status filter.
function disaster_list_where(array $state): array
{
    $where = [$state['status'] === 'archived' ? 'd.archived_at IS NOT NULL' : 'd.archived_at IS NULL'];
    $params = [];
    if ($state['q'] !== '') {
        $like = '%' . addcslashes($state['q'], '%_\\') . '%';
        $parts = [];
        foreach (['d.reference_no', 'd.title', 'd.description', 'd.incident_details', 'a.name'] as $index => $column) { $parts[] = "$column LIKE :search$index"; $params['search' . $index] = $like; }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($state['status'] !== '' && $state['status'] !== 'archived') { $where[] = 'd.status = :status'; $params['status'] = $state['status']; }
    if ($state['type'] !== '') { $where[] = 'd.record_type = :type'; $params['type'] = $state['type']; }
    if ($state['area'] !== null) { $where[] = 'd.area_id = :area'; $params['area'] = $state['area']; }
    if ($state['from'] !== '') { $where[] = 'd.record_date >= :from'; $params['from'] = $state['from']; }
    if ($state['to'] !== '') { $where[] = 'd.record_date <= :to'; $params['to'] = $state['to']; }
    return [$where, $params];
}

// Every record matching the list filters, in the list's order (for the Excel and PDF exports).
function disaster_records_rows(PDO $connection, array $state, int $limit = 5000): array
{
    [$where, $params] = disaster_list_where($state);
    $statement = $connection->prepare('SELECT d.*, a.name AS area_name FROM drr_records d INNER JOIN drr_areas a ON a.id = d.area_id WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . disaster_sort_sql($state['sort'], $state['dir']) . ' LIMIT ' . max(1, $limit));
    $statement->execute($params);
    return $statement->fetchAll();
}

// One-line description of the active filters, for printouts and the audit log.
function disaster_filter_summary(PDO $connection, array $state): string
{
    $parts = [];
    if ($state['status'] !== '') $parts[] = $state['status'] === 'archived' ? 'Archived' : (disaster_statuses()[$state['status']] ?? '');
    if ($state['type'] !== '') $parts[] = disaster_types()[$state['type']] ?? '';
    if ($state['area'] !== null && ($area = disaster_area($connection, $state['area']))) $parts[] = $area['name'];
    if ($state['from'] !== '' || $state['to'] !== '') $parts[] = ($state['from'] !== '' ? disaster_format_date($state['from']) : 'start') . ' to ' . ($state['to'] !== '' ? disaster_format_date($state['to']) : 'today');
    if ($state['q'] !== '') $parts[] = 'Search: ' . $state['q'];
    return $parts === [] ? 'All records' : implode(' · ', array_filter($parts));
}

function disaster_sort_sql(string $sort, string $dir): string
{
    $direction = $dir === 'asc' ? 'ASC' : 'DESC';
    $column = sprintf(disaster_sort_columns()[$sort] ?? disaster_sort_columns()['date'], $direction);
    return "$column $direction, d.ref_year $direction, d.ref_seq $direction, d.id";
}
