<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/residents.php';

// Barangay Officials and staff registry (barangay_personnel). The same records are used for hearing assignments in
// Complaints & Blotter. Committee, term, contact and 'On Leave' need the migration 20260926_barangay_officials_details.

function officials_can_manage(): bool
{
    return can_access_navigation('officials');
}

function officials_require_manage(): void
{
    require_auth();
    if (!officials_can_manage()) { http_response_code(403); exit('Access denied.'); }
}

// True once barangay_personnel exists with the officials detail columns.
function officials_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $check = $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'barangay_personnel' AND COLUMN_NAME IN ('committee', 'service_type', 'term_start_year', 'term_end_year', 'contact_number')");
        $ready = (int) $check->fetchColumn() === 5;
    }
    return $ready;
}

function officials_status_labels(): array
{
    return ['active' => 'Active', 'on_leave' => 'On Leave', 'inactive' => 'Inactive'];
}

function officials_status_badge(string $status): string
{
    $tone = ['active' => 'active', 'on_leave' => 'pending', 'inactive' => 'inactive'][$status] ?? 'inactive';
    return '<span class="resident-status resident-status-' . e($tone) . '">' . e(officials_status_labels()[$status] ?? ucfirst($status)) . '</span>';
}

function officials_service_types(): array
{
    return ['elected' => 'Elected', 'appointed' => 'Appointed'];
}

// Common barangay positions: offered as suggestions (any position may be typed) and used to list officials in rank order.
function officials_positions(): array
{
    return ['Punong Barangay', 'Barangay Kagawad', 'SK Chairperson', 'SK Kagawad', 'Barangay Secretary', 'Barangay Treasurer', 'Barangay Health Worker', 'Barangay Nutrition Scholar', 'Day Care Worker', 'Barangay Tanod', 'Lupon Tagapamayapa Member'];
}

// SQL ORDER BY expression: known positions first in the order above ("Barangay Captain" ranks with Punong Barangay).
function officials_order_sql(PDO $connection): string
{
    $cases = ["WHEN LOWER(position) = 'barangay captain' THEN 0"];
    foreach (officials_positions() as $rank => $position) $cases[] = 'WHEN LOWER(position) = ' . $connection->quote(mb_strtolower($position)) . ' THEN ' . $rank;
    return 'CASE ' . implode(' ', $cases) . ' ELSE 99 END, full_name';
}

// Elected officials are shown with the "Hon." title, unless the saved name already includes it.
function officials_display_name(array $row): string
{
    $name = (string) $row['full_name'];
    return ($row['service_type'] ?? null) === 'elected' && !preg_match('/^hon\.?\s/i', $name) ? 'Hon. ' . $name : $name;
}

function officials_term_label(array $row): string
{
    $start = $row['term_start_year'] ?? null;
    $end = $row['term_end_year'] ?? null;
    if (($row['service_type'] ?? null) === 'appointed') return $start ? 'Appointed (since ' . $start . ')' : 'Appointed';
    if ($start && $end) return $start . '–' . $end;
    if ($start) return 'Since ' . $start;
    return '';
}

function officials_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $statement = $connection->prepare('SELECT p.id, p.full_name, p.position, p.committee, p.service_type, p.term_start_year, p.term_end_year, p.contact_number, p.user_id, p.status, p.created_at, p.updated_at, (SELECT COUNT(*) FROM case_hearing_personnel a WHERE a.personnel_id = p.id AND a.removed_at IS NULL) AS active_assignments FROM barangay_personnel p WHERE p.id = :id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// Active staff accounts that may optionally be linked to an official (never residents).
function officials_linkable_accounts(PDO $connection): array
{
    return $connection->query("SELECT id, name, role FROM users WHERE role <> 'resident' AND status = 'active' ORDER BY name")->fetchAll();
}

function officials_validate(PDO $connection, array $input): array
{
    $values = [
        'full_name' => residents_collapse($input['full_name'] ?? ''),
        'position' => residents_collapse($input['position'] ?? ''),
        'committee' => residents_collapse($input['committee'] ?? ''),
        'service_type' => (string) ($input['service_type'] ?? ''),
        'term_start_year' => trim((string) ($input['term_start_year'] ?? '')),
        'term_end_year' => trim((string) ($input['term_end_year'] ?? '')),
        'contact_number' => residents_collapse($input['contact_number'] ?? ''),
        'status' => (string) ($input['status'] ?? 'active'),
        'user_id' => trim((string) ($input['user_id'] ?? '')),
    ];
    $errors = [];
    if (mb_strlen($values['full_name']) < 2 || mb_strlen($values['full_name']) > 180) $errors['full_name'] = 'Enter the full name (2 to 180 characters).';
    elseif (!preg_match("/^\p{L}[\p{L}\p{M}\s.,'\-]*$/u", $values['full_name'])) $errors['full_name'] = 'The name may contain only letters, spaces, periods, commas, apostrophes and hyphens.';
    if (mb_strlen($values['position']) < 2 || mb_strlen($values['position']) > 120) $errors['position'] = 'Enter the position (2 to 120 characters).';
    if (mb_strlen($values['committee']) > 120) $errors['committee'] = 'The committee must not exceed 120 characters.';
    if (!array_key_exists($values['service_type'], officials_service_types())) $errors['service_type'] = 'Select Elected or Appointed.';
    foreach (['term_start_year' => 'Start year', 'term_end_year' => 'End year'] as $field => $label) {
        if ($values[$field] !== '' && (!preg_match('/^\d{4}$/', $values[$field]) || (int) $values[$field] < 1950 || (int) $values[$field] > 2100)) $errors[$field] = "$label must be a 4-digit year (1950–2100).";
    }
    if (!isset($errors['term_start_year']) && !isset($errors['term_end_year']) && $values['term_start_year'] !== '' && $values['term_end_year'] !== '' && (int) $values['term_end_year'] < (int) $values['term_start_year']) $errors['term_end_year'] = 'End year cannot be earlier than the start year.';
    if ($values['service_type'] === 'elected' && $values['term_start_year'] === '' && !isset($errors['term_start_year'])) $errors['term_start_year'] = 'Enter the start year of the term.';
    if ($values['service_type'] === 'elected' && $values['term_end_year'] === '' && !isset($errors['term_end_year'])) $errors['term_end_year'] = 'Enter the end year of the term.';
    if ($values['contact_number'] !== '') {
        $digits = preg_replace('/\D/', '', $values['contact_number']) ?? '';
        if (mb_strlen($values['contact_number']) > 30 || !preg_match('/^\+?[0-9][0-9\s()\-]*$/', $values['contact_number']) || strlen($digits) < 7 || strlen($digits) > 15) $errors['contact_number'] = 'Enter a valid contact number, for example 09171234567 or +63 917 123 4567.';
    }
    if (!array_key_exists($values['status'], officials_status_labels())) $errors['status'] = 'Select a valid status.';
    if ($values['user_id'] !== '') {
        $user_id = filter_var($values['user_id'], FILTER_VALIDATE_INT);
        $check = $connection->prepare("SELECT 1 FROM users WHERE id = :id AND role <> 'resident' AND status = 'active'");
        $check->execute(['id' => $user_id ?: 0]);
        if (!$user_id || !$check->fetchColumn()) $errors['user_id'] = 'Select an active staff account, or leave it as No account.';
    }
    foreach (['committee', 'service_type', 'term_start_year', 'term_end_year', 'contact_number', 'user_id'] as $nullable) {
        if ($values[$nullable] === '') $values[$nullable] = null;
    }
    foreach (['term_start_year', 'term_end_year', 'user_id'] as $number) if ($values[$number] !== null && !isset($errors[$number])) $values[$number] = (int) $values[$number];
    return ['values' => $values, 'errors' => $errors];
}

// Audit entries use the existing barangay personnel actions (shown in Activity History under Complaints & Blotter).
function officials_audit(PDO $connection, int $id, string $action, array $details = []): void
{
    residents_audit($connection, 'case', $id, $action, $details);
}
