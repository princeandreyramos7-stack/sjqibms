<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/residents.php';

// Projects: barangay development projects and programs (barangay_projects, migration 20260922_dashboard_foundation).
// The System Administrator, the Secretary and Barangay Officials add and edit; the Treasurer, Health Workers and residents
// view only.
// Projects are never deleted: a stopped project is Suspended or Cancelled and keeps its history in the audit log.
// Category and budget need migration 20261119_project_details; until then they are not shown or saved. That migration
// only adds two empty columns, so projects_details_ready() applies it by itself once (nothing existing is changed).

function projects_can_view(): bool
{
    return can_access_navigation('projects');
}

function projects_can_manage(): bool
{
    return projects_can_view() && role_can('projects.manage');
}

function projects_require_view(): void
{
    require_auth();
    if (!projects_can_view()) { http_response_code(403); exit('Access denied.'); }
}

function projects_require_manage(): void
{
    require_auth();
    if (!projects_can_manage()) { http_response_code(403); exit('Access denied.'); }
}

// True once the barangay_projects table exists.
function projects_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) $ready = (int) $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'barangay_projects'")->fetchColumn() === 1;
    return $ready;
}

// True once barangay_projects has the category and budget columns; adds them (empty) the first time if missing.
function projects_details_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        if (!projects_ready($connection)) return $ready = false;
        $count = static fn (): int => (int) $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'barangay_projects' AND COLUMN_NAME IN ('category', 'budget')")->fetchColumn();
        $ready = $count() === 2;
        if (!$ready) {
            try {
                $connection->exec('ALTER TABLE barangay_projects ADD COLUMN IF NOT EXISTS category VARCHAR(80) NULL AFTER description, ADD COLUMN IF NOT EXISTS budget DECIMAL(14,2) NULL AFTER category');
                $ready = $count() === 2;
            } catch (PDOException) {
                $ready = false;   // the database account may not change tables: category and budget simply stay hidden
            }
        }
    }
    return $ready;
}

function projects_status_labels(): array
{
    return ['planned' => 'Planned', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'suspended' => 'Suspended', 'cancelled' => 'Cancelled'];
}

function projects_status_badge(string $status): string
{
    $tone = ['planned' => 'pending', 'ongoing' => 'moved', 'completed' => 'active', 'suspended' => 'inactive', 'cancelled' => 'deceased'][$status] ?? 'inactive';
    return '<span class="resident-status resident-status-' . e($tone) . '">' . e(projects_status_labels()[$status] ?? ucfirst($status)) . '</span>';
}

// Common categories, offered as suggestions (any category may be typed).
function projects_categories(): array
{
    return ['Infrastructure', 'Agriculture', 'Health', 'Education', 'Livelihood', 'Environment', 'Peace & Order', 'Sports', 'Utilities', 'Maintenance', 'Social Services', 'Disaster Preparedness'];
}

function projects_peso(mixed $amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

function projects_date(?string $value): string
{
    return $value ? date('M j, Y', strtotime($value)) : '';
}

function projects_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $statement = $connection->prepare('SELECT * FROM barangay_projects WHERE id = :id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// PRJ-2026-001, PRJ-2026-002, ... (the next number for the year; the unique key on project_code is the final check).
function projects_next_code(PDO $connection): string
{
    $prefix = 'PRJ-' . date('Y') . '-';
    $statement = $connection->prepare('SELECT project_code FROM barangay_projects WHERE project_code LIKE :prefix ORDER BY id DESC LIMIT 50');
    $statement->execute(['prefix' => $prefix . '%']);
    $max = 0;
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $code) $max = max($max, (int) substr((string) $code, strlen($prefix)));
    return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
}

// Validates the project form. Returns ['values' => ..., 'errors' => ...]. Category and budget are kept only when
// $details is true (columns present).
function projects_validate(array $input, bool $details): array
{
    $date = static function (string $value): ?string {
        $value = trim($value);
        return $value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && residents_valid_date($value) ? $value : null;
    };
    $values = [
        'title' => residents_collapse((string) ($input['title'] ?? '')),
        'description' => trim(preg_replace("/[ \t]+/u", ' ', str_replace("\r", '', (string) ($input['description'] ?? ''))) ?? ''),
        'status' => (string) ($input['status'] ?? 'planned'),
        'start_date' => $date((string) ($input['start_date'] ?? '')),
        'target_end_date' => $date((string) ($input['target_end_date'] ?? '')),
        'completed_at' => $date((string) ($input['completed_at'] ?? '')),
    ];
    $errors = [];
    if (mb_strlen($values['title']) < 3 || mb_strlen($values['title']) > 200) $errors['title'] = 'Enter the project name (3 to 200 characters).';
    if (mb_strlen($values['description']) > 2000) $errors['description'] = 'Keep the description to 2,000 characters.';
    if (!array_key_exists($values['status'], projects_status_labels())) $errors['status'] = 'Select a status.';
    if (trim((string) ($input['start_date'] ?? '')) !== '' && $values['start_date'] === null) $errors['start_date'] = 'Enter a valid start date.';
    if (trim((string) ($input['target_end_date'] ?? '')) !== '' && $values['target_end_date'] === null) $errors['target_end_date'] = 'Enter a valid target end date.';
    elseif ($values['start_date'] && $values['target_end_date'] && $values['target_end_date'] < $values['start_date']) $errors['target_end_date'] = 'The target end date cannot be before the start date.';
    if ($values['status'] === 'completed') {
        if (trim((string) ($input['completed_at'] ?? '')) === '') $values['completed_at'] = date('Y-m-d');
        elseif ($values['completed_at'] === null) $errors['completed_at'] = 'Enter a valid completion date.';
        elseif ($values['completed_at'] > date('Y-m-d')) $errors['completed_at'] = 'The completion date cannot be in the future.';
        elseif ($values['start_date'] && $values['completed_at'] < $values['start_date']) $errors['completed_at'] = 'The completion date cannot be before the start date.';
    } else {
        $values['completed_at'] = null;
    }
    if ($details) {
        $values['category'] = residents_collapse((string) ($input['category'] ?? ''));
        $budget_raw = str_replace([',', '₱', ' '], '', (string) ($input['budget'] ?? ''));
        $values['budget'] = $budget_raw === '' ? null : $budget_raw;
        if (mb_strlen($values['category']) > 80) $errors['category'] = 'Keep the category to 80 characters.';
        if ($values['budget'] !== null && !preg_match('/^\d{1,12}(\.\d{1,2})?$/', $values['budget'])) $errors['budget'] = 'Enter the budget in pesos, for example 150000 or 150000.00.';
        elseif ($values['budget'] !== null) $values['budget'] = number_format((float) $values['budget'], 2, '.', '');   // same form as the database
        if ($values['category'] === '') $values['category'] = null;
    }
    foreach (['description'] as $nullable) if ($values[$nullable] === '') $values[$nullable] = null;
    return ['values' => $values, 'errors' => $errors];
}

function projects_audit(PDO $connection, int $id, string $action, array $details = []): void
{
    residents_audit($connection, 'project', $id, $action, $details);
}
