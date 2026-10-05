<?php
declare(strict_types=1);

require_once __DIR__ . '/report_stats.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/csrf.php';

// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────
// Module reports (Residents, Households, Documents, Health, Disaster Management, Inventory, Financial). One definition
// per report builds ONE data structure (cards, charts, summary tables, detail table) from the shared statistics in
// includes/report_stats.php. The screen page, the print / PDF page and the Excel export all render that same structure,
// so they always show the same filtered figures. Access follows each module's own permission, checked on the server.
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────

const REPORT_PAGE_SIZE = 15;
const REPORT_EXPORT_LIMIT = 5000;

function report_module_keys(): array
{
    return ['residents', 'households', 'documents', 'health', 'disaster', 'inventory', 'finance'];
}

// Whether the signed-in user may open a module report (same rule as the module itself).
function report_module_allowed(string $key): bool
{
    return match ($key) {
        'residents' => can_access_navigation('residents'),
        'households' => can_access_navigation('households'),
        'documents' => can_access_navigation('documents'),
        'health' => can_access_navigation('health'),
        'disaster' => can_access_navigation('disaster'),
        'inventory' => can_access_navigation('inventory'),
        'finance' => (static function (): bool { require_once __DIR__ . '/finance.php'; return finance_can('reports'); })(),
        default => false,
    };
}

// Whether the module's tables exist (a missing module shows "Not yet available").
function report_module_ready(PDO $connection, string $key): bool
{
    try {
        return match ($key) {
            'residents' => report_table_exists($connection, 'residents'),
            'households' => report_table_exists($connection, 'households', 'resident_households', 'residents'),
            'documents' => report_table_exists($connection, 'document_requests'),
            'health' => report_table_exists($connection, 'health_records'),
            'disaster' => report_table_exists($connection, 'drr_records', 'drr_areas'),
            'inventory' => report_table_exists($connection, 'inventory_items', 'inventory_categories', 'inventory_locations'),
            'finance' => report_table_exists($connection, 'finance_transactions', 'finance_categories'),
            default => false,
        };
    } catch (PDOException) {
        return false;
    }
}

function report_require(string $key): void
{
    require_auth();
    // The Health Report is for Health Workers only (health records are confidential); they open it from the Health module,
    // not from Reports. Every other report needs the Reports menu item as well as its module's permission.
    $via_reports = $key === 'health' ? true : can_access_navigation('reports');
    if (!in_array($key, report_module_keys(), true) || !$via_reports || !report_module_allowed($key)) { http_response_code(403); exit('Access denied.'); }
}

function report_titles(): array
{
    return [
        'residents' => ['Residents Report', 'Population by Purok, sex and age group, and new residents.'],
        'households' => ['Households Report', 'Households by Purok, average household size and members.'],
        'documents' => ['Documents Report', 'Document requests by type and status, trend and processing time.'],
        'health' => ['Health Report', 'Health services by type, Purok, status and health worker.'],
        'disaster' => ['Disaster Management Report', 'Activities and incidents, affected families, evacuees, relief and damage.'],
        'inventory' => ['Inventory Report', 'Items by category, status and location, stock alerts, borrowing and value.'],
        'finance' => ['Financial Report', 'Collections and disbursements, budget vs actual and fund balance.'],
    ];
}

function report_peso(mixed $value): string
{
    $amount = (float) $value;
    return ($amount < 0 ? '-' : '') . '₱' . number_format(abs($amount), 2);
}

function report_number(int|float|null $value): string
{
    return $value === null ? '—' : number_format((float) $value, fmod((float) $value, 1.0) === 0.0 ? 0 : 1);
}

function report_percent_label(?float $percent): string
{
    return $percent === null ? '—' : number_format($percent, 1) . '%';
}

// ── Filters ────────────────────────────────────────────────────────────────────────────────────────────────

// Filter definitions per report: [name => [label, options]] (dates are common to all). Options come from stored values
// or fixed lists only; anything else in the request is ignored.
function report_filter_definitions(PDO $connection, string $key): array
{
    $puroks = static function () use ($connection): array {
        $options = residents_purok_options();
        foreach (report_rows($connection, "SELECT DISTINCT purok FROM residents WHERE purok <> '' ORDER BY purok") as $row) $options[(string) $row['purok']] ??= (string) $row['purok'];
        return $options;
    };
    return match ($key) {
        'residents' => ['purok' => ['Purok', $puroks()], 'sex' => ['Sex', ['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'unrecorded' => 'Not recorded']]],
        'households' => ['purok' => ['Purok', $puroks()]],
        // Status is shown as its own breakdown (not a filter), so every card, chart and table uses the same records.
        'documents' => ['type' => ['Document type', array_combine($t = report_documents_types($connection), $t) ?: []]],
        'health' => (static function () use ($connection, $puroks): array {
            require_once __DIR__ . '/health.php';
            $workers = health_worker_filter_options($connection);   // within the Health Worker's assigned Puroks
            // Puroks: only the Health Worker's assigned Puroks (all when none are assigned).
            $scoped = array_intersect_key($puroks(), array_flip(health_resident_puroks($connection)));
            return ['service' => ['Service', health_services()], 'purok' => ['Purok', $scoped], 'worker' => ['Health worker', $workers === [] ? [] : array_combine($workers, $workers)]];
        })(),
        'disaster' => (static function () use ($connection): array {
            require_once __DIR__ . '/disaster.php';
            return ['type' => ['Type', disaster_types()], 'area' => ['Area', array_column(report_rows($connection, 'SELECT id, name FROM drr_areas ORDER BY sort_order, name'), 'name', 'id')]];
        })(),
        'inventory' => (static function () use ($connection): array {
            require_once __DIR__ . '/inventory.php';
            return ['item_type' => ['Item type', inventory_types()], 'category' => ['Category', array_column(report_rows($connection, 'SELECT id, name FROM inventory_categories ORDER BY name'), 'name', 'id')], 'location' => ['Location', array_column(report_rows($connection, 'SELECT id, name FROM inventory_locations ORDER BY name'), 'name', 'id')], 'status' => ['Status', inventory_statuses()]];
        })(),
        'finance' => ['category' => ['Category', array_column(report_rows($connection, "SELECT id, CONCAT(name, ' (', IF(type = 'income', 'Income', 'Expense'), ')') AS label FROM finance_categories ORDER BY type, sort_order, name"), 'label', 'id')]],
        default => [],
    };
}

// Validated filters (dates + report-specific selects + page) from the query string.
function report_filters(PDO $connection, string $key, array $input): array
{
    $filters = report_range($input);
    foreach (report_filter_definitions($connection, $key) as $name => [, $options]) {
        $value = (string) ($input[$name] ?? '');
        $filters[$name] = array_key_exists($value, $options) ? $value : '';
    }
    $filters['page'] = max(1, (int) ($input['page'] ?? 1));
    return $filters;
}

// Query string of the filters (for links, exports and pagination). Dates are always included.
function report_query(string $key, array $filters, array $changes = []): string
{
    $values = array_merge($filters, $changes);
    $out = ['report' => $key, 'from' => $values['from'], 'to' => $values['to']];
    foreach ($values as $name => $value) if (!in_array($name, ['from', 'to', 'to_next', 'page', 'report'], true) && $value !== '' && $value !== null) $out[$name] = $value;
    if (($values['page'] ?? 1) > 1) $out['page'] = $values['page'];
    return http_build_query($out);
}

// Human-readable filters for printouts and exports.
function report_filters_text(PDO $connection, string $key, array $filters): array
{
    $text = ['Period: ' . report_range_label($filters['from'], $filters['to'])];
    foreach (report_filter_definitions($connection, $key) as $name => [$label, $options]) if (($filters[$name] ?? '') !== '') $text[] = $label . ': ' . ($options[$filters[$name]] ?? $filters[$name]);
    return $text;
}

// ── Report builder (the one structure shared by screen, print and Excel) ───────────────────────────────────

// $detail_limit / $detail_offset page the detail table on screen; exports pass REPORT_EXPORT_LIMIT and 0.
function report_build(PDO $connection, string $key, array $filters, int $detail_limit, int $detail_offset): array
{
    [$title, $description] = report_titles()[$key];
    $report = ['key' => $key, 'title' => $title, 'description' => $description, 'cards' => [], 'charts' => [], 'tables' => [], 'detail' => null, 'notes' => [], 'empty' => false];
    $chart = static fn (string $id, string $chart_title, string $type, array $labels, array $datasets, array $extra = []): array => ['id' => $id, 'title' => $chart_title, 'type' => $type, 'labels' => array_values($labels), 'datasets' => $datasets] + $extra;
    $one = static fn (string $label, array $values): array => [['label' => $label, 'data' => array_values($values)]];
    $kv = static fn (string $table_title, string $label, array $counts, ?int $total = null): array => ['title' => $table_title, 'columns' => [$label, 'Count', 'Share'], 'rows' => array_map(static fn (string $name, int $count): array => [$name, report_number($count), report_percent_label(report_percent($count, $total ?? array_sum($counts)))], array_keys($counts), array_values($counts))];

    if ($key === 'residents') {
        $s = report_residents_stats($connection, $filters);
        $report['empty'] = $s['total'] === 0 && $s['new_in_period'] === 0;
        $report['cards'] = [['Total Population', report_number($s['total']), 'Active residents registered by ' . date('M j, Y', strtotime($filters['to']))], ['Senior Citizens (60+)', report_number($s['seniors']), 'Age as of ' . date('M j, Y', strtotime($s['as_of']))], ['Male / Female', report_number($s['by_sex']['Male']) . ' / ' . report_number($s['by_sex']['Female']), 'Other: ' . report_number($s['by_sex']['Other']) . ' · Not recorded: ' . report_number($s['by_sex']['Not recorded'])], ['New Residents', report_number($s['new_in_period']), 'Registered in the period']];
        $report['charts'] = [
            $chart('purok', 'Population per Purok', 'bar', array_keys($s['by_purok']), $one('Residents', $s['by_purok']), ['x' => 'Purok', 'y' => 'Residents']),
            $chart('sex', 'Population by Sex', 'doughnut', array_keys($s['by_sex']), $one('Residents', $s['by_sex'])),
            $chart('age', 'Population by Age Group', 'bar', array_keys($s['by_age']), $one('Residents', $s['by_age']), ['x' => 'Age group (years)', 'y' => 'Residents']),
            $chart('new', 'New Residents Registered', 'bar', $s['buckets']['labels'], $one('New residents', $s['new_series']), ['x' => ucfirst($s['buckets']['unit']), 'y' => 'Residents']),
        ];
        $report['tables'] = [$kv('Population per Purok', 'Purok', $s['by_purok'], $s['total']), $kv('Population by Sex', 'Sex', $s['by_sex'], $s['total']), $kv('Population by Age Group', 'Age group', $s['by_age'], $s['total'])];
        if ($s['sectors'] !== null) {
            $report['cards'][] = ['PWD / Solo Parents', report_number($s['sectors']['PWD']) . ' / ' . report_number($s['sectors']['Solo Parents']), 'From the resident profiles'];
            $report['tables'][] = $kv('Population by Sector', 'Sector', $s['sectors'], $s['total']);
            $report['notes'][] = 'PWD and solo parents are counted from the current checkboxes on each resident profile. A resident can be in both.';
        }
        foreach ($s['unavailable'] as $name => $why) $report['notes'][] = $name . ': not available — ' . $why;
        $report['notes'][] = 'Population counts active resident profiles registered on or before the end date. Ages are computed from the birth date as of ' . date('M j, Y', strtotime($s['as_of'])) . '.';
        $rows = report_residents_rows($connection, $filters, $detail_limit, $detail_offset);
        $report['detail'] = ['title' => 'Residents', 'columns' => ['Name', 'Purok', 'Sex', 'Age', 'Registered'], 'total' => $s['total'], 'rows' => array_map(static fn (array $r): array => [residents_full_name($r), residents_purok_label((string) $r['purok']), ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'][(string) $r['sex']] ?? 'Not recorded', report_age($r['birth_date'], $s['as_of']) ?? '—', date('M j, Y', strtotime($r['created_at']))], $rows)];
        return $report;
    }

    if ($key === 'households') {
        $s = report_households_stats($connection, $filters);
        $report['empty'] = $s['total'] === 0 && $s['without_members'] === 0;
        $report['cards'] = [['Total Households', report_number($s['total']), 'With at least one current active member'], ['Household Members', report_number($s['members']), 'Current active members'], ['Average Household Size', $s['average'] === null ? '—' : number_format($s['average'], 2), 'Members per household'], ['Records Without Members', report_number($s['without_members']), 'Not counted as households']];
        $report['charts'] = [$chart('purok', 'Households per Purok', 'bar', array_keys($s['by_purok']), [['label' => 'Households', 'data' => array_values($s['by_purok'])], ['label' => 'Members', 'data' => array_values($s['members_by_purok'])]], ['x' => 'Purok', 'y' => 'Count'])];
        $report['tables'] = [['title' => 'Households per Purok', 'columns' => ['Purok', 'Households', 'Members', 'Average size'], 'rows' => array_map(static fn (string $purok, int $count) => [$purok, report_number($count), report_number($s['members_by_purok'][$purok] ?? 0), $count > 0 ? number_format(($s['members_by_purok'][$purok] ?? 0) / $count, 2) : '—'], array_keys($s['by_purok']), array_values($s['by_purok']))]];
        $report['notes'][] = 'Counts households registered on or before the end date that have at least one current active member (the same definition as the Dashboard). New household records added in the period: ' . report_number($s['new_in_period']) . '.';
        $rows = report_households_rows($connection, $filters, $detail_limit, $detail_offset);
        $report['detail'] = ['title' => 'Households', 'columns' => ['Household no.', 'Head', 'Purok', 'Address', 'Members'], 'total' => $s['total'], 'rows' => array_map(static fn (array $r): array => [$r['household_no'], $r['first_name'] ? residents_full_name($r) : 'No designated head', residents_purok_label((string) $r['purok']), residents_collapse($r['address']), (int) $r['members']], $rows)];
        return $report;
    }

    if ($key === 'documents') {
        $s = report_documents_stats($connection, $filters);
        $report['empty'] = array_sum($s['by_status']) === 0 && $s['released_in_period'] === 0;
        $report['cards'] = [['Requests', report_number($s['total']), 'Requested in the period (excluding rejected)'], ['Released', report_number($s['released_in_period']), 'Released in the period'], ['Rejected', report_number($s['rejected']), 'Requested in the period'], ['Average Processing Time', $s['avg_processing_days'] === null ? '—' : number_format($s['avg_processing_days'], 1) . ' days', 'Request to release (' . report_number($s['processing_count']) . ' released)']];
        $report['charts'] = [
            $chart('type', 'Requests per Document Type', 'bar', array_keys($s['by_type']), $one('Requests', $s['by_type']), ['x' => 'Document type', 'y' => 'Requests', 'horizontal' => true]),
            $chart('status', 'Requests per Status', 'doughnut', array_keys($s['by_status']), $one('Requests', $s['by_status'])),
            $chart('trend', 'Document Requests Trend', 'line', $s['buckets']['labels'], $one('Requests', $s['trend']), ['x' => ucfirst($s['buckets']['unit']), 'y' => 'Requests']),
        ];
        $report['tables'] = [$kv('Requests per Document Type', 'Document type', $s['by_type'], $s['total']), $kv('Requests per Status', 'Status', $s['by_status'])];
        foreach ($s['unavailable'] as $name => $why) $report['notes'][] = $name . ': not available — ' . $why;
        $report['notes'][] = 'Rejected requests are shown as their own status and are not included in the other totals.';
        $rows = report_documents_rows($connection, $filters, $detail_limit, $detail_offset);
        $report['detail'] = ['title' => 'Document requests', 'columns' => ['Reference', 'Resident', 'Document', 'Status', 'Requested', 'Released'], 'total' => report_documents_count_rows($connection, $filters), 'rows' => array_map(static fn (array $r): array => [$r['reference_code'], $r['first_name'] ? residents_full_name($r) : '—', $r['document_type'], report_document_statuses()[$r['status']] ?? $r['status'], date('M j, Y g:i A', strtotime($r['requested_at'])), $r['released_at'] ? date('M j, Y g:i A', strtotime($r['released_at'])) : '—'], $rows)];
        return $report;
    }

    if ($key === 'health') {
        $s = report_health_stats($connection, $filters);
        $report['empty'] = array_sum($s['by_status']) === 0 && $s['due_today'] === 0 && $s['overdue'] === 0;
        $report['cards'] = [['Health Services', report_number($s['total']), 'In the period (excluding cancelled)'], ['Completed', report_number($s['completed']), 'In the period'], ['Due Today', report_number($s['due_today']), 'Scheduled visits and follow-ups, as of today'], ['Overdue', report_number($s['overdue']), 'Scheduled visits and follow-ups, as of today']];
        $datasets = [];
        foreach ($s['trend'] as $label => $data) $datasets[] = ['label' => $label, 'data' => $data];
        $report['charts'] = [
            $chart('trend', 'Services by Type', 'bar', $s['buckets']['labels'], $datasets === [] ? $one('Services', array_fill(0, count($s['buckets']['keys']), 0)) : $datasets, ['x' => ucfirst($s['buckets']['unit']), 'y' => 'Services', 'stacked' => true]),
            $chart('purok', 'Services per Purok', 'bar', array_keys($s['by_purok']), $one('Services', $s['by_purok']), ['x' => 'Purok', 'y' => 'Services']),
            $chart('status', 'Records by Status', 'doughnut', array_keys($s['by_status']), $one('Records', $s['by_status'])),
            $chart('worker', 'Services per Health Worker', 'bar', array_keys($s['by_worker']), $one('Services', $s['by_worker']), ['x' => 'Health worker', 'y' => 'Services', 'horizontal' => true]),
        ];
        $report['tables'] = [$kv('Services by Type', 'Service', $s['by_service'], $s['total']), $kv('Records by Status', 'Status', $s['by_status']), $kv('Services per Purok', 'Purok', $s['by_purok'], $s['total']), $kv('Services per Health Worker', 'Health worker', $s['by_worker'], $s['total'])];
        $report['notes'][] = 'Archived records are excluded. Cancelled services are shown only as their own status. Due and overdue follow-ups are counted as of today for all dates (the other filters apply).';
        $rows = report_health_rows($connection, $filters, $detail_limit, $detail_offset);
        $report['detail'] = ['title' => 'Health records', 'columns' => ['Record', 'Resident', 'Purok', 'Service', 'Health worker', 'Date', 'Status'], 'total' => report_health_count_rows($connection, $filters), 'rows' => array_map(static fn (array $r): array => [$r['record_no'], residents_full_name($r), residents_purok_label((string) $r['purok']), (health_services()[$r['service']] ?? $r['service']) . ($r['service_details'] ? ' — ' . $r['service_details'] : ''), $r['health_worker'], date('M j, Y', strtotime($r['service_date'])), (health_statuses()[$r['status']] ?? $r['status']) . ($r['status'] === 'follow_up' && $r['follow_up_date'] ? ' (' . date('M j, Y', strtotime($r['follow_up_date'])) . ')' : '')], $rows)];
        return $report;
    }

    if ($key === 'disaster') {
        $s = report_disaster_stats($connection, $filters);
        $report['empty'] = array_sum($s['by_status']) === 0 && ($s['evacuees'] ?? []) === [] && ($s['relief'] ?? []) === [] && ($s['damage'] ?? []) === [];
        $report['cards'] = [['Activities and Incidents', report_number($s['total']), 'In the period (excluding cancelled)'], ['Active Incidents', report_number($s['active_incidents']), 'Planned, ongoing or monitoring (current)'], ['Affected Families', report_number($s['affected_families']), report_number($s['incidents']) . ' incident' . ($s['incidents'] === 1 ? '' : 's') . ' in the period'], ['Affected Persons', report_number($s['affected_persons']), 'Recorded on the incidents']];
        $datasets = [];
        foreach ($s['trend'] as $label => $data) $datasets[] = ['label' => $label, 'data' => $data];
        $report['charts'] = [
            $chart('trend', 'Activities and Incidents by Type', 'bar', $s['buckets']['labels'], $datasets === [] ? $one('Records', array_fill(0, count($s['buckets']['keys']), 0)) : $datasets, ['x' => ucfirst($s['buckets']['unit']), 'y' => 'Records', 'stacked' => true]),
            $chart('type', 'Records by Type', 'doughnut', array_keys($s['by_type']), $one('Records', $s['by_type'])),
            $chart('area', 'Incidents per Area', 'bar', array_keys($s['incidents_by_area']), $one('Incidents', $s['incidents_by_area']), ['x' => 'Area', 'y' => 'Incidents', 'horizontal' => true]),
        ];
        $report['tables'] = [$kv('Records by Type', 'Type', $s['by_type'], $s['total']), $kv('Records by Status', 'Status', $s['by_status']), $kv('Incidents per Area', 'Area', $s['incidents_by_area'])];
        if ($s['evacuees'] !== null) {
            $report['charts'][] = $chart('evacuees', 'Evacuees per Evacuation Center', 'bar', array_column($s['evacuees'], 'name'), [['label' => 'Families', 'data' => array_map('intval', array_column($s['evacuees'], 'families'))], ['label' => 'Persons', 'data' => array_map('intval', array_column($s['evacuees'], 'persons'))]], ['x' => 'Evacuation center', 'y' => 'Count']);
            $report['tables'][] = ['title' => 'Evacuees per Evacuation Center (checked in during the period)', 'columns' => ['Evacuation center', 'Families', 'Persons', 'Still in center'], 'rows' => array_map(static fn (array $r): array => [$r['name'], report_number((int) $r['families']), report_number((int) $r['persons']), report_number((int) $r['families_now']) . ' families / ' . report_number((int) $r['persons_now']) . ' persons'], $s['evacuees'])];
        }
        if ($s['relief'] !== null) {
            $report['tables'][] = ['title' => 'Relief Distributed per Incident (in the period)', 'columns' => ['Incident', 'Records', 'Families served', 'Cash'], 'rows' => array_map(static fn (array $r): array => [$r['reference_no'] . ' — ' . $r['title'], report_number((int) $r['distributions']), report_number((int) $r['families']), report_peso($r['cash'])], $s['relief'])];
            $report['tables'][] = ['title' => 'Relief Items Distributed (in the period)', 'columns' => ['Item', 'Quantity', 'Unit'], 'rows' => array_map(static fn (array $r): array => [$r['name'], report_number((int) $r['quantity']), $r['unit']], $s['relief_items'])];
        }
        if ($s['damage'] !== null) $report['tables'][] = ['title' => 'Damage Summary (assessments dated in the period)', 'columns' => ['Area', 'Assessments', 'Houses partially damaged', 'Houses totally damaged'], 'rows' => array_map(static fn (array $r): array => [$r['name'], report_number((int) $r['assessments']), report_number((int) $r['partial']), report_number((int) $r['total'])], $s['damage'])];
        $report['notes'][] = 'Archived records are excluded; cancelled records appear only as their own status.' . ($s['incidents_without_counts'] > 0 ? ' ' . $s['incidents_without_counts'] . ' incident(s) in the period have no affected-family count recorded.' : '');
        $rows = report_disaster_rows($connection, $filters, $detail_limit, $detail_offset);
        $report['detail'] = ['title' => 'DRR records', 'columns' => ['Reference', 'Activity / incident', 'Type', 'Area', 'Date', 'Status', 'Affected (fam. / pers.)'], 'total' => report_disaster_count_rows($connection, $filters), 'rows' => array_map(static fn (array $r): array => [$r['reference_no'], $r['title'], disaster_types()[$r['record_type']] ?? $r['record_type'], $r['area_name'], date('M j, Y', strtotime($r['record_date'])), disaster_statuses()[$r['status']] ?? $r['status'], $r['record_type'] === 'incident' ? ($r['affected_families'] ?? '—') . ' / ' . ($r['affected_persons'] ?? '—') : '—'], $rows)];
        return $report;
    }

    if ($key === 'inventory') {
        $s = report_inventory_stats($connection, $filters);
        $report['empty'] = $s['total'] === 0;
        $report['cards'] = [['Items', report_number($s['total']), 'Not archived (current)'], ['Low Stock', report_number($s['low_stock']), 'Supplies at or below reorder level'], ['Expiring in 30 Days', report_number($s['expiring']), report_number($s['expired']) . ' already expired'], ['Total Value', report_peso($s['total_value']), $s['items_without_cost'] > 0 ? report_number($s['items_without_cost']) . ' item(s) without unit cost' : 'Unit cost × quantity owned']];
        if ($s['borrowed_now'] !== null) $report['cards'][] = ['Borrowed Out', report_number($s['borrowed_now']), report_number($s['borrowed_pieces']) . ' piece(s) · ' . report_number($s['overdue']) . ' overdue'];
        $report['charts'] = [
            $chart('category', 'Items by Category', 'bar', array_keys($s['by_category']), $one('Items', $s['by_category']), ['x' => 'Category', 'y' => 'Items']),
            $chart('status', 'Items by Status', 'doughnut', array_keys($s['by_status']), $one('Items', $s['by_status'])),
            $chart('location', 'Items by Location', 'bar', array_keys($s['by_location']), $one('Items', $s['by_location']), ['x' => 'Location', 'y' => 'Items', 'horizontal' => true]),
            $chart('value', 'Total Value per Category', 'bar', array_keys($s['value_by_category']), $one('Value (₱)', array_map('floatval', $s['value_by_category'])), ['x' => 'Category', 'y' => 'Pesos', 'money' => true]),
        ];
        $report['tables'] = [
            ['title' => 'Items and Value per Category', 'columns' => ['Category', 'Items', 'Total value', 'Items without unit cost'], 'rows' => array_map(static fn (string $name, int $count): array => [$name, report_number($count), report_peso($s['value_by_category'][$name] ?? 0), report_number($s['no_cost_by_category'][$name] ?? 0)], array_keys($s['by_category']), array_values($s['by_category']))],
            $kv('Items by Status', 'Status', $s['by_status'], $s['total']), $kv('Items by Location', 'Location', $s['by_location'], $s['total']),
        ];
        if ($s['borrowed_in_period'] !== null) $report['notes'][] = 'Borrow records dated in the period: ' . report_number($s['borrowed_in_period']) . '.';
        $report['notes'][] = 'Stock figures are current (as of today) for items that are not archived; the date range applies to borrowing only. Low stock counts supplies whose quantity on hand is at or below their reorder level.';
        $rows = report_inventory_rows($connection, $filters, $detail_limit, $detail_offset);
        $report['detail'] = ['title' => 'Items', 'columns' => ['Code', 'Item', 'Category', 'Location', 'On hand', 'Status', 'Reorder level', 'Expiry', 'Unit cost', 'Value'], 'total' => $s['total'], 'rows' => array_map(static fn (array $r): array => [$r['item_code'], $r['name'], $r['category_name'], $r['location_name'], number_format((int) $r['quantity']) . ' ' . $r['unit'] . ((int) $r['borrowed_out'] > 0 ? ' (+' . $r['borrowed_out'] . ' out)' : ''), inventory_statuses()[$r['status']] ?? $r['status'], $r['reorder_level'] ?? '—', $r['expiry_date'] ? date('M j, Y', strtotime($r['expiry_date'])) : '—', $r['unit_cost'] !== null ? report_peso($r['unit_cost']) : '—', $r['unit_cost'] !== null ? report_peso((float) $r['unit_cost'] * ((int) $r['quantity'] + (int) $r['borrowed_out'])) : '—'], $rows)];
        return $report;
    }

    // finance
    $s = report_finance_stats($connection, $filters);
    $report['empty'] = $s['count'] === 0;
    $report['cards'] = [['Collections', report_peso($s['collections']), 'Posted in the period'], ['Disbursements', report_peso($s['disbursements']), 'Released in the period'], ['Net', report_peso($s['net']), 'Collections less disbursements'], ['Current Fund Balance', report_peso($s['fund_balance']), 'As of today' . ($s['opening'] ? ' (incl. beginning balance)' : '')], ['Pending Approvals', report_number($s['pending']), 'Current']];
    $report['charts'] = [
        $chart('trend', 'Collections vs Disbursements', 'bar', $s['buckets']['labels'], [['label' => 'Collections', 'data' => $s['collections_series']], ['label' => 'Disbursements', 'data' => $s['disbursements_series']]], ['x' => ucfirst($s['buckets']['unit']), 'y' => 'Pesos', 'money' => true]),
        $chart('income', 'Collections per Category', 'doughnut', array_column($s['income_by_category'], 'k'), $one('Collections (₱)', array_map('floatval', array_column($s['income_by_category'], 'total'))), ['money' => true]),
        $chart('expense', 'Disbursements per Category', 'doughnut', array_column($s['expense_by_category'], 'k'), $one('Disbursements (₱)', array_map('floatval', array_column($s['expense_by_category'], 'total'))), ['money' => true]),
    ];
    $category_rows = static fn (array $rows, string $total): array => array_merge(array_map(static fn (array $r): array => [$r['k'], report_number((int) $r['entries']), report_peso($r['total'])], $rows), [['Total', report_number(array_sum(array_map(static fn (array $r): int => (int) $r['entries'], $rows))), report_peso($total)]]);
    $report['tables'] = [
        ['title' => 'Collections per Category', 'columns' => ['Category', 'Entries', 'Amount'], 'rows' => $category_rows($s['income_by_category'], $s['collections'])],
        ['title' => 'Disbursements per Category', 'columns' => ['Category', 'Entries', 'Amount'], 'rows' => $category_rows($s['expense_by_category'], $s['disbursements'])],
    ];
    if ($s['budget'] !== null) {
        $report['tables'][] = ['title' => 'Budget vs Actual ' . $s['budget_year'] . ' (released disbursements)', 'columns' => ['Expense category', 'Appropriated', 'Released', 'Remaining', 'Used'], 'rows' => array_map(static fn (array $r): array => [$r['name'], $r['budget_id'] !== null ? report_peso($r['appropriated']) : 'Not set', report_peso($r['released']), report_peso($r['remaining']), report_percent_label($r['percent'] === null ? null : (float) $r['percent'])], $s['budget'])];
        $budgeted = array_values(array_filter($s['budget'], static fn (array $r): bool => $r['budget_id'] !== null || (float) $r['released'] > 0));
        $report['charts'][] = $chart('budget', 'Budget vs Actual ' . $s['budget_year'], 'bar', array_column($budgeted, 'name'), [['label' => 'Appropriated', 'data' => array_map('floatval', array_column($budgeted, 'appropriated'))], ['label' => 'Released', 'data' => array_map('floatval', array_column($budgeted, 'released'))]], ['x' => 'Expense category', 'y' => 'Pesos', 'money' => true]);
    }
    $report['notes'][] = 'Only posted collections (by transaction date) and released disbursements (by release date) are counted; pending, approved, rejected and cancelled records are excluded. The fund balance and pending approvals are current figures.';
    $rows = report_finance_rows($connection, $filters, $detail_limit, $detail_offset);
    $report['detail'] = ['title' => 'Counted transactions', 'columns' => ['Date', 'Reference', 'Type', 'Category', 'Description', 'Payor / payee', 'Amount'], 'total' => $s['count'], 'rows' => array_map(static fn (array $r): array => [date('M j, Y', strtotime($r['book_date'])), $r['reference_no'], $r['type'] === 'income' ? 'Collection' : 'Disbursement', $r['category_name'], $r['description'], $r['payor_or_payee'], report_peso($r['amount'])], $rows)];
    return $report;
}

// ── Chart helpers (the system palette) ─────────────────────────────────────────────────────────────────────

function report_palette(): array
{
    return ['#246b55', '#e5b45c', '#7fbf9f', '#a9c1e6', '#e3a3a6', '#153f35', '#f0cf8f', '#5b8f7f', '#c9d8d2', '#8a6f3f'];
}

// Chart data for assets/js/report_charts.js (JSON in a data attribute; rendered as text, never as HTML).
function report_chart_json(array $chart): string
{
    $palette = report_palette();
    $datasets = [];
    foreach ($chart['datasets'] as $i => $dataset) {
        $colors = $chart['type'] === 'doughnut' ? array_map(static fn (int $n): string => $palette[$n % count($palette)], array_keys($dataset['data'])) : $palette[$i % count($palette)];
        $datasets[] = ['label' => $dataset['label'], 'data' => array_values($dataset['data']), 'backgroundColor' => $colors, 'borderColor' => $chart['type'] === 'line' ? $palette[$i % count($palette)] : ($chart['type'] === 'doughnut' ? '#ffffff' : $colors), 'borderWidth' => $chart['type'] === 'line' ? 2 : 1, 'fill' => false, 'tension' => 0.25];
    }
    return (string) json_encode(['type' => $chart['type'], 'title' => $chart['title'], 'labels' => $chart['labels'], 'datasets' => $datasets, 'x' => $chart['x'] ?? '', 'y' => $chart['y'] ?? '', 'stacked' => !empty($chart['stacked']), 'horizontal' => !empty($chart['horizontal']), 'money' => !empty($chart['money'])], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function report_chart_has_data(array $chart): bool
{
    foreach ($chart['datasets'] as $dataset) foreach ($dataset['data'] as $value) if ((float) $value !== 0.0) return true;
    return false;
}

// One chart card: a canvas for Chart.js plus the same figures as a small table (shown when JavaScript or the chart
// library is unavailable, and read by screen readers).
function report_chart_html(array $chart): string
{
    if (!report_chart_has_data($chart)) return '<figure class="rpa-chart is-empty"><figcaption>' . e($chart['title']) . '</figcaption><p class="rpa-chart-empty">No data for the selected filters.</p></figure>';
    $money = !empty($chart['money']);
    $html = '<figure class="rpa-chart"><figcaption>' . e($chart['title']) . '</figcaption><div class="rpa-canvas-wrap"><canvas role="img" aria-label="' . e($chart['title']) . '" data-report-chart="' . e(report_chart_json($chart)) . '"></canvas></div>';
    $html .= '<table class="rpa-chart-data"><thead><tr><th scope="col">' . e($chart['x'] ?? '') . '</th>';
    foreach ($chart['datasets'] as $dataset) $html .= '<th scope="col">' . e($dataset['label']) . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($chart['labels'] as $i => $label) {
        $html .= '<tr><th scope="row">' . e((string) $label) . '</th>';
        foreach ($chart['datasets'] as $dataset) $html .= '<td>' . e($money ? report_peso($dataset['data'][$i] ?? 0) : report_number($dataset['data'][$i] ?? 0)) . '</td>';
        $html .= '</tr>';
    }
    return $html . '</tbody></table></figure>';
}

// The Chart.js library (pinned version, integrity-checked) and the chart renderer.
function report_chart_scripts(): string
{
    return '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.js" integrity="sha384-G436+Z2nlA8+PNoeRvWdxKbvOf8E/y+lYxqht2iBwNHTQDV5CJr3+AGVj8fGZi5t" crossorigin="anonymous" referrerpolicy="no-referrer"></script>'
        . '<script src="assets/js/report_charts.js?v=' . e((string) @filemtime(__DIR__ . '/../assets/js/report_charts.js')) . '"></script>';
}

// Audit record for printed and exported reports (screen views are not logged).
function report_audit(PDO $connection, string $key, string $action, array $filters): void
{
    try {
        residents_audit($connection, 'report', 0, $action, ['name' => report_titles()[$key][0], 'status' => implode(' · ', report_filters_text($connection, $key, $filters))]);
    } catch (PDOException) {
        // Logging must never block a report.
    }
}
