<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/security_log.php';

// Audit Logs (System Administrator only): every record in audit_logs — sign-ins, sign-outs, failed attempts, refused
// access, and every change recorded by the modules. Read-only: records can be viewed and exported, never edited or deleted.

function audit_require_admin(): void
{
    require_role('super_admin');
}

// Security events written by includes/security_log.php and the module actions defined in includes/activity.php.
function audit_action_labels(): array
{
    $labels = [
        'auth_login' => 'Signed in',
        'auth_login_failed' => 'Failed sign-in attempt',
        'auth_login_locked' => 'Sign-in refused: too many failed attempts (locked 15 minutes)',
        'auth_logout' => 'Signed out',
        'admin_gate_passed' => 'Entered the correct Admin Access Gate code',
        'admin_gate_failed' => 'Entered a wrong Admin Access Gate code',
        'admin_gate_locked' => 'Tried the Admin Access Gate while it was locked',
        'access_denied' => 'Was refused access to a page',
        'password_change_failed' => 'Entered a wrong current password while changing their password',
        'sms_subscriber_registered' => 'Registered a mobile number for announcement texts (SMS OTP verified)',
        'resident_self_registered' => 'Created a Resident Portal account online (mobile number verified by SMS OTP; pending approval)',
        'document_previewed' => 'Previewed a document',
        'document_print_opened' => 'Opened a document for printing',
        'audit_log_exported' => 'Exported the audit logs',
        'inventory_created' => 'Added an inventory item',
        'inventory_updated' => 'Updated an inventory item',
        'inventory_archived' => 'Archived an inventory item',
        'inventory_restored' => 'Restored an inventory item',
        'inventory_borrowed' => 'Lent out an inventory item',
        'inventory_returned' => 'Recorded the return of borrowed items',
        'inventory_issued' => 'Issued supplies',
        'inventory_exported' => 'Exported the inventory list (Excel)',
        'inventory_report_generated' => 'Opened the printable Inventory Report',
        'inventory_labels_printed' => 'Opened the inventory label sheet',
        'report_printed' => 'Opened a printable module report',
        'report_exported' => 'Exported a module report (Excel)',
        'health_record_viewed' => 'Viewed a health record',
        'health_record_printed' => 'Printed a health record',
        'health_list_printed' => 'Printed a list of health records',
        'health_history_viewed' => 'Viewed a resident\'s health history',
        'health_pregnancy_created' => 'Added a pregnancy record (Maternal)',
        'health_pregnancy_updated' => 'Updated a pregnancy record (Maternal)',
        'health_pregnancy_archived' => 'Archived a pregnancy record',
        'health_pregnancy_restored' => 'Restored a pregnancy record',
        'health_vaccine_added' => 'Added a dose to the vaccine schedule',
        'health_vaccine_updated' => 'Updated a dose of the vaccine schedule',
        'health_vaccine_activated' => 'Activated a dose of the vaccine schedule',
        'health_vaccine_deactivated' => 'Set a dose of the vaccine schedule to inactive',
        'health_immunization_recorded' => 'Recorded a vaccine dose given',
        'health_immunization_archived' => 'Archived a vaccine dose',
        'health_immunization_restored' => 'Restored a vaccine dose',
        'health_nutrition_recorded' => 'Recorded a weighing (Operation Timbang)',
        'health_nutrition_archived' => 'Archived a weighing',
        'health_nutrition_restored' => 'Restored a weighing',
        'health_chronic_created' => 'Added a chronic care record',
        'health_chronic_updated' => 'Updated a chronic care record',
        'health_chronic_archived' => 'Archived a chronic care record',
        'health_chronic_restored' => 'Restored a chronic care record',
        'health_referral_completed' => 'Marked a referral to the RHU completed',
        'health_referral_reopened' => 'Set a referral to the RHU back to pending',
        'health_monthly_printed' => 'Printed the Monthly Health Report',
        'health_monthly_exported' => 'Exported the Monthly Health Report (Excel)',
        'health_morbidity_printed' => 'Printed the Morbidity Report',
        'health_condition_added' => 'Added a diagnosis to the health list',
        'health_condition_activated' => 'Activated a diagnosis in the health list',
        'health_condition_deactivated' => 'Set a diagnosis in the health list to inactive',
        'health_record_created' => 'Added a health record',
        'health_record_updated' => 'Updated a health record',
        'health_record_archived' => 'Archived a health record',
        'health_record_restored' => 'Restored a health record',
        'disaster_record_created' => 'Added a DRR record',
        'disaster_record_updated' => 'Updated a DRR record',
        'disaster_record_archived' => 'Archived a DRR record',
        'disaster_record_restored' => 'Restored a DRR record',
        'disaster_area_created' => 'Added a DRR area',
        'disaster_area_updated' => 'Renamed a DRR area',
        'disaster_area_status_changed' => 'Changed a DRR area status',
        'disaster_center_created' => 'Added an evacuation center',
        'disaster_center_updated' => 'Updated an evacuation center',
        'disaster_center_archived' => 'Archived an evacuation center',
        'disaster_center_restored' => 'Restored an evacuation center',
        'disaster_hazard_created' => 'Added a hazard-prone area',
        'disaster_hazard_updated' => 'Updated a hazard-prone area',
        'disaster_hazard_archived' => 'Archived a hazard-prone area',
        'disaster_hazard_restored' => 'Restored a hazard-prone area',
        'disaster_contact_created' => 'Added a BDRRMC member or hotline',
        'disaster_contact_updated' => 'Updated a BDRRMC member or hotline',
        'disaster_contact_archived' => 'Archived a BDRRMC member or hotline',
        'disaster_contact_restored' => 'Restored a BDRRMC member or hotline',
        'disaster_vulnerable_printed' => 'Opened the printable Vulnerable Residents list',
        'disaster_evacuee_checked_in' => 'Checked in an evacuee family',
        'disaster_evacuee_checked_out' => 'Checked out an evacuee family',
        'disaster_evacuee_removed' => 'Removed a mistaken evacuation check-in',
        'disaster_alert_issued' => 'Issued a disaster alert',
        'disaster_alert_lifted' => 'Lifted a disaster alert',
        'disaster_alert_withdrawn' => 'Withdrew a disaster alert',
        'finance_category_created' => 'Added a finance category',
        'finance_category_updated' => 'Renamed a finance category',
        'finance_category_status_changed' => 'Changed a finance category status',
        'finance_budget_updated' => 'Updated the annual budget',
        'finance_opening_set' => 'Recorded the beginning balance',
        'finance_opening_cancelled' => 'Cancelled the beginning balance',
        'finance_report_generated' => 'Opened the Monthly Financial Report',
        'finance_exported' => 'Exported financial data (Excel)',
        'finance_list_printed' => 'Opened the printable transaction list',
        'disaster_relief_distributed' => 'Gave relief to a family',
        'disaster_relief_cancelled' => 'Cancelled a relief entry (items returned to stock)',
        'disaster_damage_recorded' => 'Added a damage assessment',
        'disaster_damage_updated' => 'Updated a damage assessment',
        'disaster_damage_archived' => 'Archived a damage assessment',
        'disaster_damage_restored' => 'Restored a damage assessment',
        'disaster_records_exported' => 'Exported the DRR records (Excel)',
        'disaster_records_printed' => 'Opened the printable DRR records list',
        'disaster_incident_report_printed' => 'Opened a printable Incident Report',
        'inventory_category_created' => 'Added an inventory category',
        'inventory_category_updated' => 'Renamed an inventory category',
        'inventory_category_status_changed' => 'Changed an inventory category status',
        'inventory_location_created' => 'Added an inventory location',
        'inventory_location_updated' => 'Renamed an inventory location',
        'inventory_location_status_changed' => 'Changed an inventory location status',
    ];
    foreach (activity_definitions() as $action => $definition) $labels[$action] ??= ucfirst($definition['label']);
    return $labels;
}

function audit_action_label(string $action): string
{
    return audit_action_labels()[$action] ?? ucfirst(str_replace('_', ' ', $action));
}

// Filter categories: [label, SQL condition on audit_logs l]. Conditions contain no user input.
function audit_categories(): array
{
    return [
        'authentication' => ['Sign-in & Sign-out', "l.entity_type = 'security' AND l.action LIKE 'auth\\_%'"],
        'security' => ['Security & Access', "l.entity_type = 'security' AND l.action NOT LIKE 'auth\\_%'"],
        'accounts' => ['Accounts & Passwords', "l.entity_type = 'user'"],
        'residents' => ['Residents', "l.entity_type = 'resident'"],
        'households' => ['Households', "l.entity_type = 'household'"],
        'documents' => ['Documents', "l.entity_type = 'document'"],
        'announcements' => ['Announcements', "l.entity_type = 'announcement'"],
        'complaints' => ['Complaints, Blotter & Officials', "l.entity_type = 'case'"],
        'inventory' => ['Inventory', "l.entity_type IN ('inventory', 'inventory_list')"],
        'health' => ['Health', "l.entity_type = 'health'"],
        'assistance' => ['Relief & Assistance', "l.entity_type = 'assistance'"],
        'finance' => ['Financial Management', "l.entity_type IN ('finance', 'finance_category', 'finance_budget', 'finance_opening')"],
        'disaster' => ['Disaster Management', "l.entity_type IN ('disaster', 'disaster_area', 'disaster_center', 'disaster_hazard', 'disaster_contact', 'disaster_evacuation', 'disaster_alert', 'disaster_relief', 'disaster_damage')"],
    ];
}

function audit_category_of(array $row): string
{
    return match ((string) $row['entity_type']) {
        'security' => str_starts_with((string) $row['action'], 'auth_') ? 'Sign-in & Sign-out' : 'Security & Access',
        'user' => 'Accounts & Passwords',
        'resident' => 'Residents',
        'household' => 'Households',
        'document' => 'Documents',
        'announcement' => 'Announcements',
        'case' => 'Complaints, Blotter & Officials',
        'inventory', 'inventory_list' => 'Inventory',
        'health' => 'Health',
        'assistance' => 'Relief & Assistance',
        'finance', 'finance_category', 'finance_budget', 'finance_opening' => 'Financial Management',
        'disaster', 'disaster_area', 'disaster_center', 'disaster_hazard', 'disaster_contact', 'disaster_evacuation', 'disaster_alert', 'disaster_relief', 'disaster_damage' => 'Disaster Management',
        default => 'Other',
    };
}

function audit_failure_actions(): array
{
    return ['auth_login_failed', 'auth_login_locked', 'admin_gate_failed', 'admin_gate_locked', 'access_denied', 'password_change_failed'];
}

function audit_result(string $action): string
{
    return in_array($action, audit_failure_actions(), true) ? ($action === 'access_denied' ? 'Denied' : 'Failed') : 'Success';
}

function audit_result_badge(string $action): string
{
    $result = audit_result($action);
    return '<span class="resident-status resident-status-' . ($result === 'Success' ? 'active' : 'deceased') . '">' . e($result) . '</span>';
}

function audit_role_label(?string $role): string
{
    return ['super_admin' => 'System Administrator', 'punong_barangay' => 'Punong Barangay', 'secretary' => 'Barangay Secretary', 'treasurer' => 'Barangay Treasurer', 'health_worker' => 'Barangay Health Worker', 'official' => 'Barangay Official', 'resident' => 'Resident'][(string) $role] ?? '';
}

// Validated filters from the query string.
function audit_filters(array $input): array
{
    $date = static function (string $value): string {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $parsed && $parsed->format('Y-m-d') === $value ? $value : '';
    };
    $filters = [
        'q' => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($input['q'] ?? '')) ?? ''), 0, 100),
        'category' => (string) ($input['category'] ?? ''),
        'result' => (string) ($input['result'] ?? ''),
        'user' => filter_var($input['user'] ?? null, FILTER_VALIDATE_INT) ?: null,
        'from' => $date((string) ($input['from'] ?? '')),
        'to' => $date((string) ($input['to'] ?? '')),
    ];
    if (!array_key_exists($filters['category'], audit_categories())) $filters['category'] = '';
    if (!in_array($filters['result'], ['success', 'failed'], true)) $filters['result'] = '';
    if ($filters['from'] !== '' && $filters['to'] !== '' && $filters['from'] > $filters['to']) [$filters['from'], $filters['to']] = [$filters['to'], $filters['from']];
    return $filters;
}

// WHERE clause and parameters for the filters (named placeholders are unique; native prepares cannot reuse them).
function audit_where(array $filters): array
{
    $where = [];
    $params = [];
    if ($filters['q'] !== '') {
        $like = '%' . addcslashes(mb_strtolower($filters['q']), '%_\\') . '%';
        $parts = ['LOWER(u.name) LIKE :q_name', 'LOWER(u.email) LIKE :q_email', 'l.ip_address LIKE :q_ip', "((l.entity_type IS NULL OR (l.entity_type NOT LIKE 'finance%' AND l.entity_type <> 'health')) AND LOWER(CONVERT(l.details USING utf8mb4)) LIKE :q_details)"];   // finance and health details are not searchable here (see audit_visible_details)
        $params += ['q_name' => $like, 'q_email' => $like, 'q_ip' => $like, 'q_details' => $like];
        $matching = array_keys(array_filter(audit_action_labels(), static fn (string $label, string $action): bool => str_contains(mb_strtolower($label . ' ' . $action), mb_strtolower($filters['q'])), ARRAY_FILTER_USE_BOTH));
        foreach (array_values($matching) as $i => $action) { $parts[] = 'l.action = :q_action' . $i; $params['q_action' . $i] = $action; }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($filters['category'] !== '') $where[] = '(' . audit_categories()[$filters['category']][1] . ')';
    if ($filters['result'] !== '') {
        $placeholders = [];
        foreach (audit_failure_actions() as $i => $action) { $placeholders[] = ':fail' . $i; $params['fail' . $i] = $action; }
        $where[] = 'l.action ' . ($filters['result'] === 'failed' ? 'IN' : 'NOT IN') . ' (' . implode(', ', $placeholders) . ')';
    }
    if ($filters['user'] !== null) { $where[] = 'l.user_id = :user_id'; $params['user_id'] = $filters['user']; }
    if ($filters['from'] !== '') { $where[] = 'l.created_at >= :from'; $params['from'] = $filters['from'] . ' 00:00:00'; }
    if ($filters['to'] !== '') { $where[] = 'l.created_at < DATE_ADD(:to, INTERVAL 1 DAY)'; $params['to'] = $filters['to']; }
    return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $params];
}

function audit_fetch(PDO $connection, array $filters, int $limit, int $offset): array
{
    [$where, $params] = audit_where($filters);
    $statement = $connection->prepare('SELECT l.id, l.user_id, l.action, l.entity_type, l.entity_id, l.ip_address, l.user_agent, l.details, l.created_at, u.name AS user_name, u.email AS user_email, u.role AS user_role FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id' . $where . ' ORDER BY l.created_at DESC, l.id DESC LIMIT :limit OFFSET :offset');
    foreach ($params as $key => $value) $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->bindValue('offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function audit_count(PDO $connection, array $filters): int
{
    [$where, $params] = audit_where($filters);
    $statement = $connection->prepare('SELECT COUNT(*) FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id' . $where);
    $statement->execute($params);
    return (int) $statement->fetchColumn();
}

function audit_find(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare('SELECT l.id, l.user_id, l.action, l.entity_type, l.entity_id, l.ip_address, l.user_agent, l.details, l.created_at, u.name AS user_name, u.email AS user_email, u.role AS user_role FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id WHERE l.id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// Who performed the action; failed sign-ins without a matching account show the email that was typed.
function audit_actor(array $row): string
{
    if ($row['user_name']) return (string) $row['user_name'];
    $details = json_decode((string) $row['details'], true);
    if (is_array($details) && !empty($details['email'])) return 'Unknown account (' . $details['email'] . ')';
    return $row['action'] === 'access_denied' || str_starts_with((string) $row['action'], 'admin_gate_') ? 'Not signed in' : 'Unavailable account';
}

// Financial Management records: the System Administrator sees who did what and when, but not amounts, reasons or
// old/new values (those are shown to the Treasurer and the Punong Barangay on each transaction's page).
function audit_is_finance(array $row): bool
{
    return str_starts_with((string) $row['entity_type'], 'finance');
}

// Details that may be shown in Audit Logs (list, record page and CSV export).
function audit_visible_details(array $row): array
{
    $details = json_decode((string) $row['details'], true);
    if (!is_array($details)) return [];
    // Health records are for Health Workers only: the audit shows who did what and when, and the record number, but no
    // service, status or other health details.
    if (($row['entity_type'] ?? '') === 'health') return array_intersect_key($details, array_flip(['record_no'])) + ['note' => 'Health details are shown only to Health Workers in the Health module.'];
    if (!audit_is_finance($row)) return $details;
    $visible = array_intersect_key($details, array_flip(['reference', 'name', 'type', 'status', 'changed_fields']));
    return $visible + ['note' => 'Amounts, reasons and old/new values are shown only to the Barangay Treasurer and the Punong Barangay in Financial Management.'];
}

// Short summary of the recorded details for the list (page, portal, reason, reference, changed fields).
function audit_summary(array $row): string
{
    $details = audit_visible_details($row);
    if ($details === []) return '';
    $parts = [];
    foreach (['page' => 'Page', 'portal' => 'Portal', 'reason' => 'Reason', 'reference' => 'Reference', 'reference_code' => 'Reference', 'document_type' => 'Type', 'item_code' => 'Item', 'record_no' => 'Record', 'name' => 'Name'] as $key => $label) {
        if (isset($details[$key]) && is_scalar($details[$key]) && $details[$key] !== '') $parts[] = $label . ': ' . str_replace('_', ' ', (string) $details[$key]);
    }
    if (isset($details['changed_fields']) && is_array($details['changed_fields'])) $parts[] = 'Changed: ' . implode(', ', array_map(static fn ($f): string => str_replace('_', ' ', (string) $f), $details['changed_fields']));
    return implode(' · ', array_slice($parts, 0, 3));
}

function audit_format_datetime(string $value): string
{
    $date = date_create($value);
    return $date ? $date->format('M j, Y g:i:s A') : $value;
}

// Spreadsheet-safe CSV cell (a leading = + - @ is neutralised so the cell is never run as a formula).
function audit_csv_cell(string $value): string
{
    return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
}
