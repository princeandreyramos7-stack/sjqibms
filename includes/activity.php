<?php
declare(strict_types=1);

require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/../config/database.php';

function activity_is_admin(): bool
{
    return has_role('super_admin');
}

function activity_definitions(): array
{
    return [
        'announcement_created'   => ['label' => 'created an announcement',  'module' => 'announcements'],
        'announcement_updated'   => ['label' => 'updated an announcement',  'module' => 'announcements'],
        'announcement_published' => ['label' => 'published an announcement','module' => 'announcements'],
        'announcement_archived'  => ['label' => 'archived an announcement', 'module' => 'announcements'],
        'announcement_unarchived' => ['label' => 'unarchived an announcement', 'module' => 'announcements'],
        'announcement_deleted'   => ['label' => 'deleted an announcement',  'module' => 'announcements'],
        'resident_created'               => ['label' => 'registered a resident',                  'module' => 'residents'],
        'resident_updated'               => ['label' => 'updated a resident profile',             'module' => 'residents'],
        'resident_status_changed'        => ['label' => 'changed a resident status',              'module' => 'residents'],
        'resident_duplicate_override'    => ['label' => 'approved a duplicate-check override',    'module' => 'residents'],
        'resident_household_assigned'    => ['label' => 'assigned a resident to a household',     'module' => 'residents'],
        'resident_household_transferred' => ['label' => 'transferred a resident to a household',  'module' => 'residents'],
        'resident_household_removed'     => ['label' => 'removed a resident from a household',    'module' => 'residents'],
        'resident_household_updated'     => ['label' => 'updated a household relationship',       'module' => 'residents'],
        'document_requested'             => ['label' => 'submitted a document request',           'module' => 'documents'],
        'document_approved'              => ['label' => 'approved a document request',            'module' => 'documents'],
        'document_rejected'              => ['label' => 'rejected a document request',            'module' => 'documents'],
        'document_released'              => ['label' => 'released a requested document',          'module' => 'documents'],
        'household_created'              => ['label' => 'created a household',                    'module' => 'households'],
        'household_updated'              => ['label' => 'updated a household',                    'module' => 'households'],
        'household_head_changed'         => ['label' => 'changed a household head',               'module' => 'households'],
        'household_deleted'              => ['label' => 'deleted an unused household',            'module' => 'households'],
        // Complaints & Blotter (entity_type 'case'; labels never include case content)
        'complaint_submitted'            => ['label' => 'recorded a complaint',                   'module' => 'complaints'],
        'complaint_review_started'       => ['label' => 'started a complaint review',             'module' => 'complaints'],
        'complaint_resolved'             => ['label' => 'resolved a complaint',                   'module' => 'complaints'],
        'complaint_closed'               => ['label' => 'closed a complaint',                     'module' => 'complaints'],
        'complaint_note_added'           => ['label' => 'added a note to a complaint',            'module' => 'complaints'],
        'blotter_recorded'               => ['label' => 'recorded a blotter entry',               'module' => 'complaints'],
        'blotter_processing_started'     => ['label' => 'started blotter processing',             'module' => 'complaints'],
        'blotter_resolved'               => ['label' => 'resolved a blotter case',                'module' => 'complaints'],
        'blotter_closed'                 => ['label' => 'closed a blotter case',                  'module' => 'complaints'],
        'blotter_note_added'             => ['label' => 'added a note to a blotter entry',        'module' => 'complaints'],
        'hearing_scheduled'              => ['label' => 'scheduled a hearing',                    'module' => 'complaints'],
        'hearing_rescheduled'            => ['label' => 'rescheduled a hearing',                  'module' => 'complaints'],
        'hearing_cancelled'              => ['label' => 'cancelled a hearing',                    'module' => 'complaints'],
        'hearing_outcome_recorded'       => ['label' => 'recorded a hearing outcome',             'module' => 'complaints'],
        'hearing_attendance_recorded'    => ['label' => 'recorded hearing attendance',            'module' => 'complaints'],
        'hearing_personnel_assigned'     => ['label' => 'assigned hearing personnel',             'module' => 'complaints'],
        'hearing_personnel_removed'      => ['label' => 'removed a hearing personnel assignment', 'module' => 'complaints'],
        'evidence_uploaded'              => ['label' => 'uploaded a confidential evidence file',  'module' => 'complaints'],
        'evidence_removed'               => ['label' => 'removed a confidential evidence file',   'module' => 'complaints'],
        'personnel_created'              => ['label' => 'added a barangay personnel record',      'module' => 'complaints'],
        'personnel_updated'              => ['label' => 'updated a barangay personnel record',    'module' => 'complaints'],
        'personnel_status_changed'       => ['label' => 'changed a barangay personnel status',    'module' => 'complaints'],
        'venue_created'                  => ['label' => 'added a hearing venue',                  'module' => 'complaints'],
        'venue_updated'                  => ['label' => 'updated a hearing venue',                'module' => 'complaints'],
        'venue_status_changed'           => ['label' => 'changed a hearing venue status',         'module' => 'complaints'],
        'case_report_generated'          => ['label' => 'generated a confidential case report',   'module' => 'complaints'],
        // Accounts (entity_type 'user'; System Administrator only; never includes passwords)
        'account_profile_updated'        => ['label' => 'updated the administrator profile',      'module' => 'users'],
        'account_password_changed'       => ['label' => 'changed the administrator password',     'module' => 'users'],
        'account_password_reset'         => ['label' => 'set a new password for a staff account', 'module' => 'users'],
        'account_password_changed_self'  => ['label' => 'changed their own password',             'module' => 'users'],
        'account_role_changed'           => ['label' => 'assigned a role to a staff account',     'module' => 'users'],
        'account_created'                => ['label' => 'created a staff account',                'module' => 'users'],
        'account_updated'                => ['label' => 'updated a staff account',                'module' => 'users'],
        'account_status_changed'         => ['label' => 'changed an account status',              'module' => 'users'],
        // Resident Registrations (entity_type 'registration'; online Resident Portal sign-ups)
        'registration_approved'          => ['label' => 'approved an online resident registration', 'module' => 'registrations'],
        'registration_rejected'          => ['label' => 'rejected an online resident registration', 'module' => 'registrations'],
        'resident_account_linked'        => ['label' => 'linked a resident profile to its approved account', 'module' => 'registrations'],
        // Family members added by residents (entity_type 'household_request'; Resident Portal → My Household)
        'household_member_requested'     => ['label' => 'added a family member for approval',       'module' => 'registrations'],
        'household_member_approved'      => ['label' => 'approved a family member added by a resident', 'module' => 'registrations'],
        'household_member_rejected'      => ['label' => 'rejected a family member added by a resident', 'module' => 'registrations'],
        // Relief & Assistance (entity_type 'assistance'; Secretary's Activity History)
        'assistance_given'               => ['label' => 'gave relief or assistance to a resident', 'module' => 'assistance'],
        'assistance_cancelled'           => ['label' => 'cancelled a relief or assistance entry',   'module' => 'assistance'],
        'assistance_scheduled'           => ['label' => 'scheduled relief or assistance',           'module' => 'assistance'],
        'assistance_updated'             => ['label' => 'edited a relief or assistance record',     'module' => 'assistance'],
        'assistance_marked_given'        => ['label' => 'marked scheduled assistance as given',     'module' => 'assistance'],
        'assistance_voided'              => ['label' => 'voided a relief or assistance record',     'module' => 'assistance'],
        'assistance_finance_linked'      => ['label' => 'linked assistance to a disbursement',      'module' => 'assistance'],
        'assistance_finance_unlinked'    => ['label' => 'unlinked assistance from a disbursement',  'module' => 'assistance'],
        // Financial Management (entity_type 'finance' / 'finance_category'; Treasurer's Activity History)
        'finance_created'                => ['label' => 'recorded a financial transaction',       'module' => 'finance'],
        'finance_updated'                => ['label' => 'edited a pending disbursement',          'module' => 'finance'],
        'finance_approved'               => ['label' => 'approved a disbursement',                'module' => 'finance'],
        'finance_rejected'               => ['label' => 'rejected a disbursement',                'module' => 'finance'],
        'finance_released'               => ['label' => 'released a disbursement',                'module' => 'finance'],
        'finance_cancelled'              => ['label' => 'cancelled a financial transaction',      'module' => 'finance'],
        'finance_attachment_added'       => ['label' => 'attached a document to a transaction',   'module' => 'finance'],
        'finance_attachment_removed'     => ['label' => 'removed an attachment from a transaction', 'module' => 'finance'],
    ];
}

// audit_logs.entity_type recorded for each activity module; used by the module filter.
function activity_module_entity_type(string $module): ?string
{
    return ['announcements' => 'announcement', 'residents' => 'resident', 'households' => 'household', 'documents' => 'document', 'complaints' => 'case', 'users' => 'user', 'finance' => 'finance', 'assistance' => 'assistance'][$module] ?? null;
}

function activity_module_label(string $module): string
{
    return match ($module) {
        'announcements' => 'Announcements',
        'residents'     => 'Residents',
        'households'    => 'Households',
        'documents'     => 'Documents',
        'complaints'    => 'Complaints',
        'projects'      => 'Projects',
        'registrations' => 'Registrations',
        'finance'       => 'Financial Management',
        'assistance'    => 'Relief & Assistance',
        'users'         => 'User Management',
        'audit'         => 'Audit',
        default         => ucfirst($module),
    };
}

function activity_icon(string $module): string
{
    return match ($module) {
        'announcements' => 'megaphone',
        'residents'     => 'users',
        'households'    => 'home',
        'documents'     => 'file',
        'complaints'    => 'case',
        'projects'      => 'briefcase',
        'registrations' => 'user-plus',
        'finance'       => 'wallet',
        'assistance'    => 'gift',
        'users'         => 'user-cog',
        default         => 'history',
    };
}

function activity_accessible_modules(): array
{
    if (activity_is_admin()) {
        return array_keys(array_filter(
            array_column(activity_definitions(), 'module', 'module'),
            static fn (string $m): bool => true
        )) + ['audit'];
    }
    // Other staff see only the modules granted to their office (includes/roles.php); roles without activity access see none.
    if (!is_authenticated() || !role_can('activity.view')) {
        return [];
    }
    return array_values(array_filter(role_activity_modules(), static fn (string $module): bool => activity_module_entity_type($module) !== null));
}

function activity_authorized_where(array &$params, string $alias = 'l'): string
{
    if (activity_is_admin()) {
        // Sign-ins, refused access and document previews are security records shown in Audit Logs, not Activity History.
        return "($alias.entity_type IS NULL OR $alias.entity_type <> 'security') AND $alias.action NOT IN ('document_previewed', 'document_print_opened')";
    }
    // Only defined actions of the office's permitted modules are visible, matched on both action and entity type.
    $modules = activity_accessible_modules();
    $placeholders = [];
    foreach (activity_definitions() as $action => $definition) {
        if (!in_array($definition['module'], $modules, true)) continue;
        $placeholder = ':activity_action_' . (count($placeholders) + 1);
        $params[$placeholder] = $action;
        $placeholders[] = $placeholder;
    }
    if ($placeholders === []) {
        return '1 = 0';
    }
    $types = [];
    foreach ($modules as $index => $module) {
        $placeholder = ':activity_type_' . ($index + 1);
        $params[$placeholder] = activity_module_entity_type($module);
        $types[] = $placeholder;
    }
    return "$alias.entity_type IN (" . implode(', ', $types) . ") AND $alias.action IN (" . implode(', ', $placeholders) . ')';
}

function activity_normalize(array $row): array
{
    $definition = activity_definitions()[$row['action']] ?? null;
    $module = $definition['module'] ?? 'audit';
    return [
        'id'           => (int) $row['id'],
        'action'       => $definition['label'] ?? 'recorded an administrative activity',
        'module'       => $module,
        'module_label' => activity_module_label($module),
        'icon'         => activity_icon($module),
        'actor'        => $row['actor_name'] ?: 'Unavailable account',
        'created_at'   => (string) $row['created_at'],
        'date'         => date_create((string) $row['created_at'])?->format('M j, Y g:i A') ?? (string) $row['created_at'],
    ];
}

function activity_fetch(PDO $connection, int $limit = 3, int $offset = 0, ?string $module = null, ?string $from = null, ?string $to = null): array
{
    $params = [];
    $where = [activity_authorized_where($params)];
    if ($module !== null && $module !== '') {
        $entity_type = activity_module_entity_type($module);
        if ($entity_type !== null && in_array($module, activity_accessible_modules(), true)) {
            $where[] = 'l.entity_type = :activity_entity_type';
            $params[':activity_entity_type'] = $entity_type;
        } else {
            $where[] = '1 = 0';
        }
    }
    if ($from !== null && $from !== '') { $where[] = 'l.created_at >= :activity_from'; $params[':activity_from'] = $from . ' 00:00:00'; }
    if ($to !== null && $to !== '') { $where[] = 'l.created_at < DATE_ADD(:activity_to, INTERVAL 1 DAY)'; $params[':activity_to'] = $to; }
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;
    $statement = $connection->prepare('SELECT l.id, l.action, l.entity_type, l.created_at, u.name AS actor_name FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY l.created_at DESC, l.id DESC LIMIT :limit OFFSET :offset');
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $value) { if ($key !== ':limit' && $key !== ':offset') $statement->bindValue($key, $value); }
    $statement->execute();
    return array_map('activity_normalize', $statement->fetchAll());
}

function activity_find(PDO $connection, int $id): ?array
{
    $params = [':activity_id' => $id];
    $where = activity_authorized_where($params);
    $statement = $connection->prepare("SELECT l.id, l.action, l.entity_type, l.entity_id, l.created_at, u.name AS actor_name FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id WHERE l.id = :activity_id AND $where LIMIT 1");
    $statement->execute($params);
    $row = $statement->fetch();
    // action_key is the raw audit action, used only for server-side presentation logic on the details page.
    return $row ? activity_normalize($row) + ['entity_id' => $row['entity_id'] !== null ? (int) $row['entity_id'] : null, 'action_key' => (string) $row['action']] : null;
}

function activity_total(PDO $connection, ?string $module = null, ?string $from = null, ?string $to = null): int
{
    $params = [];
    $where = [activity_authorized_where($params)];
    if ($module !== null && $module !== '') {
        $entity_type = activity_module_entity_type($module);
        if ($entity_type !== null && in_array($module, activity_accessible_modules(), true)) {
            $where[] = 'l.entity_type = :activity_entity_type';
            $params[':activity_entity_type'] = $entity_type;
        } else {
            $where[] = '1 = 0';
        }
    }
    if ($from !== null && $from !== '') { $where[] = 'l.created_at >= :activity_from'; $params[':activity_from'] = $from . ' 00:00:00'; }
    if ($to !== null && $to !== '') { $where[] = 'l.created_at < DATE_ADD(:activity_to, INTERVAL 1 DAY)'; $params[':activity_to'] = $to; }
    $statement = $connection->prepare('SELECT COUNT(*) FROM audit_logs l WHERE ' . implode(' AND ', $where));
    $statement->execute($params);
    return (int) $statement->fetchColumn();
}
