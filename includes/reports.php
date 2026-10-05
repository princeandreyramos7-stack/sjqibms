<?php
declare(strict_types=1);

require_once __DIR__ . '/hearings.php';

// ── SJQIBMS Reports ──────────────────────────────────────────────────────────────────────────────────
// One report builder (reports_build) produces a plain data structure; one renderer (reports_render_document) turns it
// into the official A4 document used by the on-screen preview and browser printing (and by PDF output once a PDF
// engine is approved). All queries are read-only SELECTs. Complaints, Blotter and Hearing reports are confidential and
// available to Super Admin and Barangay Secretary only ('complaints.manage'); no aggregate-only reporting permission
// exists for other roles, so none is granted.

const REPORTS_DETAIL_LIMIT = 2000; // detailed lists are capped; a visible notice is shown when the cap is reached

// Report sections shown on the Reports page, in order.
function reports_sections(): array
{
    return ['population' => 'Population', 'services' => 'Services', 'peace' => 'Peace and Order', 'disaster' => 'Disaster and Property', 'finance' => 'Finance'];
}

// Report categories. Module reports (module_report.php) are available once their module's tables exist; the
// confidential Complaints, Blotter and Hearing reports (report.php) are always available to their roles. 'visible'
// follows the module's own permission (checked on the server again by every report page and export).
function reports_categories(): array
{
    require_once __DIR__ . '/report_modules.php';
    $module = static fn (string $key, string $section, string $icon, string $description): array => ['label' => report_titles()[$key][0], 'icon' => $icon, 'description' => $description, 'section' => $section, 'confidential' => false, 'href' => 'module_report.php?report=' . $key, 'available' => static fn (): bool => report_module_ready(db(), $key), 'visible' => static fn (): bool => report_module_allowed($key)];
    return [
        'residents' => $module('residents', 'population', 'users', 'Population by Purok, sex and age group, and new residents.'),
        'households' => $module('households', 'population', 'home', 'Households by Purok, average household size and members.'),
        'documents' => $module('documents', 'services', 'file', 'Document requests by type and status, trend and processing time.'),
        'health' => $module('health', 'services', 'heart', 'Health services by type, Purok, status and health worker.'),
        'complaints' => ['label' => 'Complaints Report', 'icon' => 'case', 'description' => 'Complaint statistics, detailed lists and individual complaint reports.', 'section' => 'peace', 'confidential' => true, 'href' => 'report.php?category=complaints', 'available' => true, 'visible' => static fn (): bool => complaints_can_manage()],
        'blotter' => ['label' => 'Blotter Records Report', 'icon' => 'archive', 'description' => 'Blotter case statistics, detailed lists and individual blotter reports.', 'section' => 'peace', 'confidential' => true, 'href' => 'report.php?category=blotter', 'available' => true, 'visible' => static fn (): bool => complaints_can_manage()],
        'hearings' => ['label' => 'Hearing Schedule Report', 'icon' => 'history', 'description' => 'Hearing statistics, schedules, attendance and individual hearing reports.', 'section' => 'peace', 'confidential' => true, 'href' => 'report.php?category=hearings', 'available' => true, 'visible' => static fn (): bool => complaints_can_manage()],
        'disaster' => $module('disaster', 'disaster', 'shield', 'Activities and incidents, affected families, evacuees, relief and damage.'),
        'inventory' => $module('inventory', 'disaster', 'archive', 'Items by category, status and location, stock alerts, borrowing and value.'),
        'finance' => $module('finance', 'finance', 'wallet', 'Collections and disbursements, budget vs actual and fund balance.'),
    ];
}

// Whether a category's report can be opened now (module tables present); errors count as not available.
function reports_category_available(array $category): bool
{
    try {
        return is_callable($category['available']) ? (bool) ($category['available'])() : (bool) $category['available'];
    } catch (Throwable) {
        return false;
    }
}

// Categories the signed-in user may see (server-side). Restricted categories never appear, whatever the search.
function reports_visible_categories(): array
{
    return array_filter(reports_categories(), static fn (array $category): bool => ($category['visible'])());
}

// report.php serves the confidential Complaints, Blotter and Hearing reports only.
function reports_can_generate(string $category): bool
{
    $categories = reports_categories();
    return isset($categories[$category]) && $categories[$category]['confidential'] && reports_category_available($categories[$category]) && ($categories[$category]['visible'])();
}

function reports_type_labels(): array
{
    return ['summary' => 'Summary Report', 'detailed' => 'Detailed List Report', 'individual' => 'Individual Report'];
}

function reports_status_options(string $category): array
{
    return match ($category) {
        'blotter' => blotter_status_labels(),
        'hearings' => hearing_status_labels(),
        default => complaints_status_labels(true), // includes legacy values, each kept separate
    };
}

// Validates every filter server-side; unknown values are dropped, never passed to SQL.
function reports_validate_filters(PDO $connection, string $category, array $input): array
{
    $date = static fn (string $value): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && residents_valid_date($value) ? $value : '';
    $filters = [
        'type' => array_key_exists((string) ($input['type'] ?? ''), reports_type_labels()) ? (string) $input['type'] : 'summary',
        'from' => $date((string) ($input['from'] ?? '')),
        'to' => $date((string) ($input['to'] ?? '')),
        'status' => (string) ($input['status'] ?? ''),
        'group' => (string) ($input['group'] ?? ''),
        'q' => mb_substr(residents_collapse((string) ($input['q'] ?? '')), 0, 100),
        'id' => filter_var($input['id'] ?? null, FILTER_VALIDATE_INT) ?: null,
        'page' => max(1, (int) ($input['page'] ?? 1)),
    ];
    $errors = [];
    if ($filters['from'] !== '' && $filters['to'] !== '' && $filters['from'] > $filters['to']) {
        $errors[] = 'The start date must be on or before the end date.';
        $filters['to'] = '';
    }
    if (!array_key_exists($filters['status'], reports_status_options($category))) $filters['status'] = '';
    $groups = reports_group_options($connection, $category);
    if (!array_key_exists($filters['group'], $groups)) $filters['group'] = '';
    return ['filters' => $filters, 'errors' => $errors];
}

// Category-specific extra filter (label + options), from actual stored values only.
function reports_group_options(PDO $connection, string $category): array
{
    return match ($category) {
        'complaints' => array_combine($v = complaints_distinct_values($connection, 'category'), $v) + ['source:staff' => 'Source: Staff-assisted', 'source:online' => 'Source: Online'],
        'blotter' => array_combine($v = complaints_distinct_values($connection, 'incident_type'), $v) + ['link:linked' => 'Linked to a complaint', 'link:standalone' => 'Standalone entries'],
        'hearings' => array_column(array_map(static fn (array $row): array => ['id' => 'venue:' . $row['id'], 'name' => 'Venue: ' . $row['name']], hearings_venue_options($connection)), 'name', 'id'),
        default => [],
    };
}

function reports_group_label(string $category): string
{
    return match ($category) { 'complaints' => 'Category / source', 'blotter' => 'Incident type / link', 'hearings' => 'Venue', default => 'Group' };
}

// WHERE clause for the category's records (placeholders are unique per query; native prepares cannot reuse them).
function reports_where(string $category, array $filters): array
{
    $where = [];
    $params = [];
    [$alias, $date_column, $search] = match ($category) {
        'blotter' => ['b', 'b.recorded_at', ['b.blotter_number', 'b.incident_type', 'b.incident_location']],
        'hearings' => ['h', 'h.starts_at', ['h.hearing_number', 'h.hearing_type', 'v.name']],
        default => ['c', 'c.filed_at', ['c.case_number', 'c.subject', 'c.category']],
    };
    if ($filters['from'] !== '') { $where[] = "$date_column >= :f_from"; $params['f_from'] = $filters['from'] . ' 00:00:00'; }
    if ($filters['to'] !== '') { $where[] = "$date_column < DATE_ADD(:f_to, INTERVAL 1 DAY)"; $params['f_to'] = $filters['to']; }
    if ($filters['status'] !== '') { $where[] = "$alias.status = :f_status"; $params['f_status'] = $filters['status']; }
    if ($filters['q'] !== '') {
        $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
        $parts = [];
        foreach ($search as $index => $column) { $parts[] = "$column LIKE :f_q$index"; $params["f_q$index"] = $like; }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    $group = $filters['group'];
    if ($group !== '') {
        if ($category === 'complaints' && str_starts_with($group, 'source:')) { $where[] = 'c.submission_source = :f_group'; $params['f_group'] = substr($group, 7); }
        elseif ($category === 'complaints') { $where[] = 'c.category = :f_group'; $params['f_group'] = $group; }
        elseif ($category === 'blotter' && $group === 'link:linked') $where[] = 'b.complaint_id IS NOT NULL';
        elseif ($category === 'blotter' && $group === 'link:standalone') $where[] = 'b.complaint_id IS NULL';
        elseif ($category === 'blotter') { $where[] = 'b.incident_type = :f_group'; $params['f_group'] = $group; }
        elseif ($category === 'hearings' && str_starts_with($group, 'venue:')) { $where[] = 'h.venue_id = :f_group'; $params['f_group'] = (int) substr($group, 6); }
    }
    return [$where === [] ? '1 = 1' : implode(' AND ', $where), $params];
}

function reports_from_sql(string $category): string
{
    return match ($category) {
        'blotter' => 'FROM blotter_entries b LEFT JOIN complaint_cases c ON c.id = b.complaint_id LEFT JOIN users ru ON ru.id = b.recorded_by',
        'hearings' => 'FROM case_hearings h INNER JOIN hearing_venues v ON v.id = h.venue_id LEFT JOIN complaint_cases c ON c.id = h.complaint_id LEFT JOIN blotter_entries b ON b.id = h.blotter_id',
        default => 'FROM complaint_cases c',
    };
}

function reports_query(PDO $connection, string $sql, array $params): array
{
    $statement = $connection->prepare($sql);
    foreach ($params as $key => $value) $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $statement->execute();
    return $statement->fetchAll();
}

// Paginated, filtered record list for the Individual Report selector (server-side; 10 per page).
function reports_selection(PDO $connection, string $category, array $filters): array
{
    [$where, $params] = reports_where($category, $filters);
    $from = reports_from_sql($category);
    $total = (int) (reports_query($connection, "SELECT COUNT(*) AS n $from WHERE $where", $params)[0]['n'] ?? 0);
    $pages = max(1, (int) ceil($total / 10));
    $page = min($filters['page'], $pages);
    $select = match ($category) {
        'blotter' => "SELECT b.id, b.blotter_number AS reference, b.incident_type AS title, b.recorded_at AS listed_at, b.status $from WHERE $where ORDER BY b.recorded_at DESC, b.id DESC",
        'hearings' => "SELECT h.id, h.hearing_number AS reference, CONCAT(COALESCE(c.case_number, b.blotter_number), ' · ', h.hearing_type) AS title, h.starts_at AS listed_at, h.status $from WHERE $where ORDER BY h.starts_at DESC, h.id DESC",
        default => "SELECT c.id, c.case_number AS reference, c.subject AS title, c.filed_at AS listed_at, c.status $from WHERE $where ORDER BY c.filed_at DESC, c.id DESC",
    };
    $rows = reports_query($connection, "$select LIMIT :lim OFFSET :off", $params + ['lim' => 10, 'off' => ($page - 1) * 10]);
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function reports_period_label(array $filters): string
{
    if ($filters['from'] === '' && $filters['to'] === '') return 'All records to date';
    $fmt = static fn (string $d): string => date('F j, Y', strtotime($d));
    if ($filters['from'] !== '' && $filters['to'] !== '') return $fmt($filters['from']) . ' – ' . $fmt($filters['to']);
    return $filters['from'] !== '' ? 'From ' . $fmt($filters['from']) : 'Up to ' . $fmt($filters['to']);
}

function reports_filters_text(PDO $connection, string $category, array $filters): array
{
    $text = [];
    if ($filters['status'] !== '') $text[] = 'Status: ' . reports_status_options($category)[$filters['status']];
    if ($filters['group'] !== '') $text[] = reports_group_options($connection, $category)[$filters['group']];
    if ($filters['q'] !== '') $text[] = 'Search: "' . $filters['q'] . '"';
    return $text;
}

// Counts per key for every known label (zero rows are shown as 0, never hidden) plus any unexpected stored value.
// $labels are always listed (0 when empty); $all_labels names any other stored value (e.g. legacy statuses), which is
// listed separately — values are never merged.
function reports_count_by(PDO $connection, string $category, array $filters, string $expression, array $labels = [], array $all_labels = []): array
{
    [$where, $params] = reports_where($category, $filters);
    $rows = reports_query($connection, "SELECT $expression AS k, COUNT(*) AS n " . reports_from_sql($category) . " WHERE $where GROUP BY k ORDER BY n DESC, k", $params);
    $counts = array_fill_keys(array_keys($labels), 0);
    foreach ($rows as $row) $counts[(string) ($row['k'] ?? '')] = (int) $row['n'];
    $names = $labels + $all_labels;
    $out = [];
    foreach ($counts as $key => $count) $out[] = [$names[$key] ?? ($key === '' ? 'Not recorded' : (string) $key), (string) $count];
    return $out;
}

// ── Analytics for the confidential reports (trend and breakdown charts above the existing document) ────
// Same filters and WHERE clause as the report itself (reports_where), so every count matches the document below.
// Without a date range, the trend covers the first to the last record on file.
function reports_analytics(PDO $connection, string $category, array $filters): array
{
    require_once __DIR__ . '/report_stats.php';
    report_prepare($connection);
    [$where, $params] = reports_where($category, $filters);
    $from_sql = reports_from_sql($category);
    [$date_column, $type_expression, $type_title, $statuses] = match ($category) {
        'blotter' => ['b.recorded_at', 'b.incident_type', 'Blotter Entries by Incident Type', blotter_status_labels()],
        'hearings' => ['h.starts_at', 'h.hearing_type', 'Hearings by Type', hearing_status_labels()],
        default => ['c.filed_at', 'c.category', 'Complaints by Category', complaints_status_labels(true)],
    };
    $noun = ['complaints' => 'Complaints', 'blotter' => 'Blotter entries', 'hearings' => 'Hearings'][$category] ?? 'Records';
    $span = reports_query($connection, "SELECT DATE(MIN($date_column)) AS first_day, DATE(MAX($date_column)) AS last_day, COUNT(*) AS n $from_sql WHERE $where", $params)[0];
    if ((int) $span['n'] === 0) return ['total' => 0, 'charts' => []];
    $from = $filters['from'] !== '' ? $filters['from'] : (string) $span['first_day'];
    $to = $filters['to'] !== '' ? $filters['to'] : (string) $span['last_day'];
    $buckets = report_buckets($from, max($from, $to));
    $trend = report_series(reports_query($connection, "SELECT DATE_FORMAT($date_column, '{$buckets['sql']}') AS k, COUNT(*) AS n $from_sql WHERE $where GROUP BY k", $params), $buckets);
    $alias = explode('.', $date_column)[0];
    $by_status = report_breakdown(reports_query($connection, "SELECT $alias.status AS k, COUNT(*) AS n $from_sql WHERE $where GROUP BY k", $params), $statuses);
    $by_status = array_filter($by_status, static fn (int $n): bool => $n > 0);
    $by_type = report_breakdown(reports_query($connection, "SELECT $type_expression AS k, COUNT(*) AS n $from_sql WHERE $where GROUP BY k ORDER BY n DESC", $params));
    $one = static fn (string $label, array $values): array => [['label' => $label, 'data' => array_values($values)]];
    return ['total' => (int) $span['n'], 'charts' => [
        ['id' => 'trend', 'title' => $noun . ' Trend', 'type' => 'bar', 'labels' => $buckets['labels'], 'datasets' => $one($noun, $trend), 'x' => ucfirst($buckets['unit']), 'y' => $noun],
        ['id' => 'status', 'title' => $noun . ' by Status', 'type' => 'doughnut', 'labels' => array_keys($by_status), 'datasets' => $one($noun, $by_status)],
        ['id' => 'type', 'title' => $type_title, 'type' => 'bar', 'labels' => array_keys($by_type), 'datasets' => $one($noun, $by_type), 'x' => $category === 'blotter' ? 'Incident type' : ($category === 'hearings' ? 'Hearing type' : 'Category'), 'y' => $noun, 'horizontal' => true],
    ]];
}

// ── The report builder (single source of truth for preview, print and PDF) ─────────────────────────────
function reports_build(PDO $connection, string $category, array $filters): array
{
    $label = reports_categories()[$category]['label'];
    $report = [
        'category' => $category, 'category_label' => $label, 'type' => $filters['type'],
        'title' => str_replace(' Report', '', $label) . ' — ' . reports_type_labels()[$filters['type']],
        'period' => reports_period_label($filters), 'filters_text' => reports_filters_text($connection, $category, $filters),
        'reference' => null, 'orientation' => $filters['type'] === 'detailed' ? 'landscape' : 'portrait',
        'confidentiality' => 'CONFIDENTIAL — For authorized barangay personnel only',
        'generated_at' => date('F j, Y g:i A'),
        'prepared_by' => ['name' => (string) current_user()['name'], 'position' => role_title()],
        'sections' => [], 'notice' => null, 'record_count' => 0, 'missing' => false,
    ];
    if ($filters['type'] === 'individual') {
        $report['sections'] = $filters['id'] ? reports_individual($connection, $category, (int) $filters['id'], $report) : [];
        if ($filters['id'] === null || $report['missing']) $report['notice'] = $filters['id'] === null ? 'Select a record below to generate its Individual Report.' : 'The selected record was not found.';
        return $report;
    }
    [$where, $params] = reports_where($category, $filters);
    $from = reports_from_sql($category);
    $total = (int) (reports_query($connection, "SELECT COUNT(*) AS n $from WHERE $where", $params)[0]['n'] ?? 0);
    $report['record_count'] = $total;
    if ($filters['type'] === 'summary') {
        $report['sections'] = reports_summary_sections($connection, $category, $filters, $total);
    } else {
        $report['sections'] = [reports_detailed_section($connection, $category, $where, $params, $from, $total, $report)];
    }
    if ($total === 0) $report['notice'] = 'No records found for the selected reporting period.';
    return $report;
}

function reports_summary_sections(PDO $connection, string $category, array $filters, int $total): array
{
    if ($category === 'complaints') {
        return [
            ['kind' => 'stats', 'heading' => 'Overview', 'rows' => [['Total complaints', (string) $total], ['Reporting period', reports_period_label($filters)]]],
            ['kind' => 'stats', 'heading' => 'Complaints by status', 'rows' => reports_count_by($connection, 'complaints', $filters, 'c.status', complaints_status_labels(), complaints_status_labels(true))],
            ['kind' => 'stats', 'heading' => 'Complaints by category', 'rows' => reports_count_by($connection, 'complaints', $filters, 'c.category')],
            ['kind' => 'stats', 'heading' => 'Complaints by submission source', 'rows' => reports_count_by($connection, 'complaints', $filters, 'c.submission_source', ['staff' => 'Staff-assisted', 'online' => 'Online (resident account)'])],
            ['kind' => 'note', 'text' => 'Complaints are counted from complaint records only; linked blotter entries are not counted as additional complaints. Legacy statuses (Open, For Hearing, Settled, Dismissed) are listed separately when present.'],
        ];
    }
    if ($category === 'blotter') {
        return [
            ['kind' => 'stats', 'heading' => 'Overview', 'rows' => [['Total blotter entries', (string) $total], ['Reporting period', reports_period_label($filters)]]],
            ['kind' => 'stats', 'heading' => 'Blotter entries by status', 'rows' => reports_count_by($connection, 'blotter', $filters, 'b.status', blotter_status_labels())],
            ['kind' => 'stats', 'heading' => 'Linked and standalone entries', 'rows' => reports_count_by($connection, 'blotter', $filters, "IF(b.complaint_id IS NULL, 'standalone', 'linked')", ['linked' => 'Linked to a complaint', 'standalone' => 'Standalone'])],
            ['kind' => 'stats', 'heading' => 'Blotter entries by incident type', 'rows' => reports_count_by($connection, 'blotter', $filters, 'b.incident_type')],
            ['kind' => 'note', 'text' => 'Each blotter entry is counted once. Several entries linked to the same complaint are separate records with their own status and history.'],
        ];
    }
    [$where, $params] = reports_where('hearings', $filters);
    $from = reports_from_sql('hearings');
    $rescheduled = (int) (reports_query($connection, "SELECT COUNT(DISTINCT h.id) AS n $from WHERE $where AND EXISTS (SELECT 1 FROM case_hearing_schedule_history s WHERE s.hearing_id = h.id AND s.change_type = 'rescheduled')", $params)[0]['n'] ?? 0);
    $attendance = reports_query($connection, "SELECT p.attendance_status AS k, COUNT(*) AS n FROM case_hearing_participants p INNER JOIN case_hearings h ON h.id = p.hearing_id INNER JOIN hearing_venues v ON v.id = h.venue_id LEFT JOIN complaint_cases c ON c.id = h.complaint_id LEFT JOIN blotter_entries b ON b.id = h.blotter_id WHERE $where GROUP BY p.attendance_status", $params);
    $attendance_rows = array_fill_keys(array_keys(hearing_attendance_labels()), 0);
    foreach ($attendance as $row) $attendance_rows[$row['k']] = (int) $row['n'];
    return [
        ['kind' => 'stats', 'heading' => 'Overview', 'rows' => [['Total hearings', (string) $total], ['Hearings rescheduled at least once', (string) $rescheduled], ['Reporting period', reports_period_label($filters)]]],
        ['kind' => 'stats', 'heading' => 'Hearings by current status', 'rows' => reports_count_by($connection, 'hearings', $filters, 'h.status', hearing_status_labels())],
        ['kind' => 'stats', 'heading' => 'Recorded participant attendance', 'rows' => array_map(static fn (string $key, int $count): array => [hearing_attendance_labels()[$key], (string) $count], array_keys($attendance_rows), $attendance_rows)],
        ['kind' => 'note', 'text' => 'A rescheduled hearing remains one hearing; its earlier schedules are kept in its schedule history and are not counted as separate hearings.'],
    ];
}

function reports_detailed_section(PDO $connection, string $category, string $where, array $params, string $from, int $total, array &$report): array
{
    $limit = REPORTS_DETAIL_LIMIT;
    if ($total > $limit) $report['notice'] = "Showing the first $limit of $total matching records. Narrow the filters to include the rest.";
    if ($category === 'complaints') {
        $rows = reports_query($connection, "SELECT c.case_number, c.filed_at, c.category, c.subject, c.submission_source, c.status, c.reviewed_at, c.resolved_at, c.closed_at, (SELECT COUNT(*) FROM blotter_entries bx WHERE bx.complaint_id = c.id) AS blotters $from WHERE $where ORDER BY c.filed_at, c.id LIMIT $limit", $params);
        return ['kind' => 'table', 'heading' => 'Complaints', 'columns' => ['Reference', 'Submitted', 'Category', 'Subject', 'Source', 'Status', 'Review started', 'Resolved / Closed', 'Blotter entries'],
            'rows' => array_map(static fn (array $r): array => [$r['case_number'], complaints_format_datetime($r['filed_at']), $r['category'] ?? '—', $r['subject'], $r['submission_source'] === 'online' ? 'Online' : ($r['submission_source'] === 'staff' ? 'Staff-assisted' : '—'), complaints_status_labels(true)[$r['status']] ?? $r['status'], $r['reviewed_at'] ? complaints_format_datetime($r['reviewed_at']) : '—', $r['closed_at'] ? 'Closed ' . complaints_format_datetime($r['closed_at']) : ($r['resolved_at'] ? 'Resolved ' . complaints_format_datetime($r['resolved_at']) : '—'), (string) $r['blotters']], $rows)];
    }
    if ($category === 'blotter') {
        $rows = reports_query($connection, "SELECT b.blotter_number, b.incident_type, b.incident_at, b.recorded_at, c.case_number, b.status, ru.name AS recorder $from WHERE $where ORDER BY b.recorded_at, b.id LIMIT $limit", $params);
        return ['kind' => 'table', 'heading' => 'Blotter entries', 'columns' => ['Reference', 'Incident type', 'Incident date', 'Recorded', 'Related complaint', 'Status', 'Recorded by'],
            'rows' => array_map(static fn (array $r): array => [$r['blotter_number'], $r['incident_type'], complaints_format_datetime($r['incident_at']), complaints_format_datetime($r['recorded_at']), $r['case_number'] ?? 'Standalone', blotter_status_labels()[$r['status']] ?? $r['status'], $r['recorder'] ?? '—'], $rows)];
    }
    $rows = reports_query($connection, "SELECT h.hearing_number, COALESCE(c.case_number, b.blotter_number) AS case_reference, h.hearing_type, h.starts_at, h.ends_at, v.name AS venue, h.status, (SELECT GROUP_CONCAT(p.full_name ORDER BY p.full_name SEPARATOR ', ') FROM case_hearing_personnel a INNER JOIN barangay_personnel p ON p.id = a.personnel_id WHERE a.hearing_id = h.id AND a.removed_at IS NULL) AS personnel $from WHERE $where ORDER BY h.starts_at, h.id LIMIT $limit", $params);
    return ['kind' => 'table', 'heading' => 'Hearings', 'columns' => ['Hearing', 'Case', 'Type', 'Date and time', 'Venue', 'Assigned personnel', 'Status'],
        'rows' => array_map(static fn (array $r): array => [$r['hearing_number'], $r['case_reference'], $r['hearing_type'], complaints_format_time_range($r['starts_at'], $r['ends_at']), $r['venue'], $r['personnel'] ?? '—', hearing_status_labels()[$r['status']] ?? $r['status']], $rows)];
}

function reports_people_rows(array $persons, array $roles): array
{
    $rows = [];
    foreach ($persons as $person) {
        if (!in_array($person['person_role'], $roles, true)) continue;
        $details = array_filter([$person['contact_number'] ?? null, $person['address'] ?? null, $person['identifying_details'] ?? null]);
        $rows[] = [case_person_role_labels()[$person['person_role']], $person['full_name'] . (!empty($person['resident_id']) ? ' (Resident #' . $person['resident_id'] . ')' : ''), $details === [] ? '—' : implode(' · ', $details)];
    }
    return $rows;
}

function reports_history_rows(array $history): array
{
    return array_map(static fn (array $h): array => [complaints_format_datetime($h['acted_at']), complaints_history_label($h['action']) . ($h['from_status'] !== null && $h['from_status'] !== $h['to_status'] ? ' (' . $h['from_status'] . ' → ' . $h['to_status'] . ')' : ''), $h['actor_name'] ?? '—', $h['notes'] ?? '—'], $history);
}

function reports_hearing_ref_rows(array $hearings): array
{
    return array_map(static fn (array $h): array => [$h['hearing_number'], complaints_format_time_range($h['starts_at'], $h['ends_at']), $h['venue_name'], hearing_status_labels()[$h['status']] ?? $h['status']], $hearings);
}

// Individual reports: complete authorized information from actual records. Evidence files are never embedded.
function reports_individual(PDO $connection, string $category, int $id, array &$report): array
{
    $v = static fn ($value): string => ($value === null || $value === '') ? 'Not recorded' : (string) $value;
    $dt = static fn (?string $value): string => $value ? complaints_format_datetime($value) : 'Not recorded';
    if ($category === 'complaints') {
        $c = complaints_find($connection, $id);
        if (!$c) { $report['missing'] = true; return []; }
        $report['reference'] = $c['case_number'];
        $persons = complaints_persons($connection, 'complaint', $id);
        $blotters = reports_query($connection, 'SELECT blotter_number, incident_type, status, recorded_at FROM blotter_entries WHERE complaint_id = :id ORDER BY recorded_at', ['id' => $id]);
        $evidence = (int) (reports_query($connection, 'SELECT COUNT(*) AS n FROM case_attachments WHERE complaint_id = :id AND removed_at IS NULL', ['id' => $id])[0]['n'] ?? 0);
        $report['record_count'] = 1;
        return [
            ['kind' => 'fields', 'heading' => 'Complaint information', 'rows' => [['Reference', $c['case_number']], ['Current status', complaints_status_labels(true)[$c['status']] ?? $c['status']], ['Category', $v($c['category'])], ['Subject', $c['subject']], ['Incident date and time', $dt($c['incident_at'])], ['Incident location', $v($c['incident_location'])], ['Submitted', $dt($c['filed_at'])], ['Submission source', complaints_source_label($c['submission_source'])], ['Recorded by', $v($c['submitter_name'])]]],
            ['kind' => 'table', 'heading' => 'Complainant and respondent', 'columns' => ['Role', 'Name', 'Contact / address / details'], 'rows' => reports_people_rows($persons, ['complainant', 'respondent']), 'empty' => 'No parties recorded.'],
            ['kind' => 'table', 'heading' => 'Witnesses and other persons', 'columns' => ['Role', 'Name', 'Contact / address / details'], 'rows' => reports_people_rows($persons, ['witness', 'other']), 'empty' => 'None recorded.'],
            ['kind' => 'text', 'heading' => 'Complaint narrative', 'text' => $v($c['confidential_details'])],
            ['kind' => 'fields', 'heading' => 'Processing, resolution and closing', 'rows' => [['Review started', $c['reviewed_at'] ? $dt($c['reviewed_at']) . ' · ' . $v($c['reviewer_name']) : 'Not recorded'], ['Resolved', $c['resolved_at'] ? $dt($c['resolved_at']) . ' · ' . $v($c['resolver_name']) : 'Not recorded'], ['Resolution notes', $v($c['resolution_notes'])], ['Closed', $c['closed_at'] ? $dt($c['closed_at']) . ' · ' . $v($c['closer_name']) : 'Not recorded'], ['Closing information', $v($c['closing_notes'])]]],
            ['kind' => 'table', 'heading' => 'Documented processing history', 'columns' => ['Date', 'Action', 'By', 'Notes'], 'rows' => reports_history_rows(complaints_history($connection, 'complaint', $id)), 'empty' => 'No history recorded.'],
            ['kind' => 'table', 'heading' => 'Related blotter entries', 'columns' => ['Reference', 'Incident type', 'Recorded', 'Status'], 'rows' => array_map(static fn (array $b): array => [$b['blotter_number'], $b['incident_type'], complaints_format_datetime($b['recorded_at']), blotter_status_labels()[$b['status']]], $blotters), 'empty' => 'No linked blotter entries.'],
            ['kind' => 'table', 'heading' => 'Related hearings', 'columns' => ['Hearing', 'Schedule', 'Venue', 'Status'], 'rows' => reports_hearing_ref_rows(complaints_case_hearings($connection, 'complaint', $id)), 'empty' => 'No hearings scheduled.'],
            ['kind' => 'note', 'text' => "Confidential evidence files on record: $evidence. Evidence files are not included in reports."],
        ];
    }
    if ($category === 'blotter') {
        $b = blotter_find($connection, $id);
        if (!$b) { $report['missing'] = true; return []; }
        $report['reference'] = $b['blotter_number'];
        $persons = complaints_persons($connection, 'blotter', $id);
        $evidence = (int) (reports_query($connection, 'SELECT COUNT(*) AS n FROM case_attachments WHERE blotter_id = :id AND removed_at IS NULL', ['id' => $id])[0]['n'] ?? 0);
        $report['record_count'] = 1;
        return [
            ['kind' => 'fields', 'heading' => 'Blotter information', 'rows' => [['Reference', $b['blotter_number']], ['Current status', blotter_status_labels()[$b['status']]], ['Related complaint', $b['complaint_reference'] ?? 'Standalone entry'], ['Incident type', $b['incident_type']], ['Incident date and time', $dt($b['incident_at'])], ['Incident location', $b['incident_location']], ['Recorded', $dt($b['recorded_at'])], ['Recording personnel', $v($b['recorder_name'])]]],
            ['kind' => 'table', 'heading' => 'Complainant and respondent', 'columns' => ['Role', 'Name', 'Contact / address / details'], 'rows' => reports_people_rows($persons, ['complainant', 'respondent']), 'empty' => 'No parties recorded.'],
            ['kind' => 'table', 'heading' => 'Witnesses and other persons', 'columns' => ['Role', 'Name', 'Contact / address / details'], 'rows' => reports_people_rows($persons, ['witness', 'other']), 'empty' => 'None recorded.'],
            ['kind' => 'text', 'heading' => 'Incident narrative', 'text' => $b['narrative']],
            ['kind' => 'fields', 'heading' => 'Processing, resolution and closing', 'rows' => [['Processing started', $b['processing_started_at'] ? $dt($b['processing_started_at']) . ' · ' . $v($b['starter_name']) : 'Not recorded'], ['Resolved', $b['resolved_at'] ? $dt($b['resolved_at']) . ' · ' . $v($b['resolver_name']) : 'Not recorded'], ['Resolution notes', $v($b['resolution_notes'])], ['Closed', $b['closed_at'] ? $dt($b['closed_at']) . ' · ' . $v($b['closer_name']) : 'Not recorded'], ['Closing information', $v($b['closing_notes'])]]],
            ['kind' => 'table', 'heading' => 'Processing history', 'columns' => ['Date', 'Action', 'By', 'Notes'], 'rows' => reports_history_rows(complaints_history($connection, 'blotter', $id)), 'empty' => 'No history recorded.'],
            ['kind' => 'table', 'heading' => 'Related hearings', 'columns' => ['Hearing', 'Schedule', 'Venue', 'Status'], 'rows' => reports_hearing_ref_rows(complaints_case_hearings($connection, 'blotter', $id)), 'empty' => 'No hearings scheduled.'],
            ['kind' => 'note', 'text' => "Confidential evidence files on record: $evidence. Evidence files are not included in reports."],
        ];
    }
    $h = hearings_find($connection, $id);
    if (!$h) { $report['missing'] = true; return []; }
    $report['reference'] = $h['hearing_number'];
    $history = hearings_schedule_history($connection, $id);
    $initial = $history[0] ?? null;
    $report['record_count'] = 1;
    return [
        ['kind' => 'fields', 'heading' => 'Hearing information', 'rows' => [['Hearing reference', $h['hearing_number']], ['Related case', $h['complaint_reference'] ? 'Complaint ' . $h['complaint_reference'] : 'Blotter ' . $h['blotter_reference']], ['Hearing type', $h['hearing_type']], ['Current status', hearing_status_labels()[$h['status']]], ['Original schedule', $initial ? complaints_format_time_range($initial['new_starts_at'], $initial['new_ends_at']) . ' · ' . $initial['new_venue'] : 'Not recorded'], ['Current schedule', complaints_format_time_range($h['starts_at'], $h['ends_at'])], ['Venue', $h['venue_name']], ['Scheduled by', $v($h['creator_name'])], ['Scheduling notes', $v($h['notes'])]]],
        ['kind' => 'table', 'heading' => 'Assigned personnel', 'columns' => ['Name', 'Assignment role', 'Position', 'Assignment'], 'rows' => array_map(static fn (array $a): array => [$a['full_name'], $a['assignment_role'], $a['position'], 'Assigned ' . complaints_format_datetime($a['assigned_at']) . ($a['removed_at'] ? ' · Removed ' . complaints_format_datetime($a['removed_at']) . ' (' . $a['removal_reason'] . ')' : '')], hearings_personnel_assignments($connection, $id)), 'empty' => 'No personnel assigned.'],
        ['kind' => 'table', 'heading' => 'Participants and recorded attendance', 'columns' => ['Participant', 'Role', 'Attendance', 'Recorded', 'Notes'], 'rows' => array_map(static fn (array $p): array => [$p['name'], ucfirst($p['participant_role']), hearing_attendance_labels()[$p['attendance_status']], $p['attendance_recorded_at'] ? complaints_format_datetime($p['attendance_recorded_at']) . ($p['recorder_name'] ? ' · ' . $p['recorder_name'] : '') : '—', $p['attendance_notes'] ?? '—'], hearings_participants($connection, $id)), 'empty' => 'No participants recorded.'],
        ['kind' => 'fields', 'heading' => 'Outcome and cancellation', 'rows' => [['Documented outcome', $h['status'] === 'completed' ? $v($h['outcome_summary']) . ($h['outcome_recorded_at'] ? ' (recorded ' . $dt($h['outcome_recorded_at']) . ' · ' . $v($h['outcome_recorder_name']) . ')' : '') : 'No outcome recorded'], ['Cancellation', $h['status'] === 'cancelled' ? $dt($h['cancelled_at']) . ' · ' . $v($h['canceller_name']) . ' — ' . $v($h['cancellation_reason']) : 'Not cancelled']]],
        ['kind' => 'table', 'heading' => 'Schedule history', 'columns' => ['Changed', 'Change', 'Previous schedule', 'New schedule', 'Reason', 'By'], 'rows' => array_map(static fn (array $s): array => [complaints_format_datetime($s['changed_at']), $s['change_type'] === 'initial' ? 'Scheduled' : 'Rescheduled', $s['previous_starts_at'] ? complaints_format_time_range($s['previous_starts_at'], $s['previous_ends_at']) . ' · ' . $s['previous_venue'] : '—', complaints_format_time_range($s['new_starts_at'], $s['new_ends_at']) . ' · ' . $s['new_venue'], $s['reason'] ?? '—', $s['actor_name'] ?? '—'], $history), 'empty' => 'No schedule history recorded.'],
    ];
}

// ── Renderer: the official A4 document (preview and print share this exact markup) ─────────────────────
function reports_render_document(array $report): string
{
    $html = '<article class="rpt-sheet' . ($report['orientation'] === 'landscape' ? ' is-landscape' : '') . '" data-doc-sheet data-doc-width-mm="' . ($report['orientation'] === 'landscape' ? '297' : '210') . '">';
    $html .= '<header class="rpt-letterhead"><img class="rpt-logo" src="assets/img/barangay-san-jose-logo.jpg" alt="Barangay San Jose seal"><div class="rpt-letterhead-text"><p>Republic of the Philippines</p><p>Province of Isabela</p><p>Municipality of Quirino</p><p class="rpt-barangay">BARANGAY SAN JOSE</p><p class="rpt-office">Office of the Barangay Secretary</p></div></header>';
    $html .= '<div class="rpt-titleblock"><p class="rpt-mark">' . e($report['confidentiality']) . '</p><h1>' . e($report['title']) . '</h1>';
    $html .= '<table class="rpt-info"><tr><th>Report category</th><td>' . e($report['category_label']) . '</td><th>Reporting period</th><td>' . e($report['period']) . '</td></tr>';
    $html .= '<tr><th>Generated</th><td>' . e($report['generated_at']) . '</td><th>Prepared by</th><td>' . e($report['prepared_by']['name'] . ', ' . $report['prepared_by']['position']) . '</td></tr>';
    if ($report['reference'] !== null) $html .= '<tr><th>Record reference</th><td colspan="3">' . e($report['reference']) . '</td></tr>';
    if ($report['filters_text'] !== []) $html .= '<tr><th>Filters</th><td colspan="3">' . e(implode(' · ', $report['filters_text'])) . '</td></tr>';
    $html .= '</table></div>';
    if ($report['notice'] !== null) $html .= '<p class="rpt-notice">' . e($report['notice']) . '</p>';
    foreach ($report['sections'] as $section) {
        $heading = isset($section['heading']) ? '<h2>' . e($section['heading']) . '</h2>' : '';
        $html .= match ($section['kind']) {
            'stats', 'fields' => '<section class="rpt-section">' . $heading . '<table class="rpt-kv' . ($section['kind'] === 'stats' ? ' is-stats' : '') . '"><tbody>' . implode('', array_map(static fn (array $r): string => '<tr><th>' . e($r[0]) . '</th><td>' . nl2br(e($r[1])) . '</td></tr>', $section['rows'])) . ($section['rows'] === [] ? '<tr><td colspan="2">No records.</td></tr>' : '') . '</tbody></table></section>',
            'table' => '<section class="rpt-section">' . $heading . ($section['rows'] === [] ? '<p class="rpt-empty">' . e($section['empty'] ?? 'No records found for the selected reporting period.') . '</p>' : '<table class="rpt-table"><thead><tr>' . implode('', array_map(static fn (string $c): string => '<th>' . e($c) . '</th>', $section['columns'])) . '</tr></thead><tbody>' . implode('', array_map(static fn (array $r): string => '<tr>' . implode('', array_map(static fn ($cell): string => '<td>' . nl2br(e((string) $cell)) . '</td>', $r)) . '</tr>', $section['rows'])) . '</tbody></table>') . '</section>',
            'text' => '<section class="rpt-section">' . $heading . '<p class="rpt-text">' . nl2br(e($section['text'])) . '</p></section>',
            'note' => '<p class="rpt-note">' . e($section['text']) . '</p>',
            default => '',
        };
    }
    // Physical signing only: names come from verified sources; signature lines stay blank; no approval is implied.
    $html .= '<section class="rpt-signatures"><div class="rpt-sign"><p class="rpt-sign-label">Prepared by:</p><div class="rpt-sign-line"></div><p class="rpt-sign-name">' . e($report['prepared_by']['name']) . '</p><p class="rpt-sign-pos">' . e($report['prepared_by']['position']) . '</p><p class="rpt-sign-date">Date: ____________________</p></div>';
    $html .= '<div class="rpt-sign"><p class="rpt-sign-label">Reviewed / Approved by:</p><div class="rpt-sign-line"></div><p class="rpt-sign-name rpt-blank">Name: ______________________________</p><p class="rpt-sign-pos rpt-blank">Position: ___________________________</p><p class="rpt-sign-date">Date: ____________________</p></div></section>';
    $html .= '<footer class="rpt-footer"><p>' . e($report['confidentiality']) . '</p><p>System-generated by SJQIBMS on ' . e($report['generated_at']) . '. This report is not officially approved until it is physically signed above.</p></footer>';
    return $html . '</article>';
}
