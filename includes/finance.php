<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/residents.php';

// Financial Management — access control (Phase 1).
//   Barangay Treasurer   : full access (record, edit before approval, cancel, release, budget, categories, reports).
//   System Administrator : views everything and approves or rejects disbursements (the Punong Barangay role was removed
//                          by the owner on 2026-10-04); cannot create, edit, release or cancel, nor set the budget or
//                          beginning balance.
//   Punong Barangay      : kept for any existing account (view, reports); no longer approves.
//   Everyone else: no access.
// Every page and action calls finance_require(); buttons are hidden with finance_can(). The role is re-read from the
// database on each request, so an account whose role was changed (or that was suspended) loses access at once, even
// before it signs out.

function finance_permissions(): array
{
    return [
        'view' => ['treasurer', 'punong_barangay', 'super_admin'],
        'create' => ['treasurer'],
        'edit' => ['treasurer'],
        'cancel' => ['treasurer'],
        'release' => ['treasurer'],
        'approve' => ['super_admin'],
        'budget' => ['treasurer'],
        'categories' => ['treasurer'],
        'reports' => ['treasurer', 'punong_barangay', 'super_admin'],
        'audit' => ['treasurer', 'punong_barangay', 'super_admin'],
    ];
}

// The signed-in user's current role from the database (null when signed out, not active, or changed since sign-in).
function finance_role(): ?string
{
    static $cache = [];
    $user = current_user();
    if ($user === null) return null;
    $id = (int) $user['id'];
    if (!array_key_exists($id, $cache)) {
        try {
            $statement = db()->prepare('SELECT role, status FROM users WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $row = $statement->fetch();
            $cache[$id] = $row && $row['status'] === 'active' && $row['role'] === ($user['role'] ?? null) ? (string) $row['role'] : null;
        } catch (PDOException) {
            $cache[$id] = null;
        }
    }
    return $cache[$id];
}

function finance_can(string $action): bool
{
    $role = finance_role();
    return $role !== null && in_array($role, finance_permissions()[$action] ?? [], true);
}

function finance_require(string $action = 'view'): void
{
    require_auth();
    if (!finance_can($action)) { http_response_code(403); exit('Access denied.'); }
}

function finance_role_label(?string $role = null): string
{
    return ['treasurer' => 'Barangay Treasurer', 'punong_barangay' => 'Punong Barangay', 'super_admin' => 'System Administrator'][$role ?? (string) finance_role()] ?? '';
}


function finance_peso(mixed $value): string
{
    return '₱' . number_format((float) $value, 2);
}

// ── Phase 2: collections and disbursements (migration 20261004_finance_transactions) ─────────────────────────────

const FINANCE_ATTACHMENT_MAX_BYTES = 10 * 1024 * 1024;

function finance_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $check = $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('finance_categories', 'finance_transactions', 'finance_attachments')");
        $ready = (int) $check->fetchColumn() === 3;
    }
    return $ready;
}

function finance_types(): array
{
    return ['income' => 'Income', 'expense' => 'Expense'];
}

function finance_statuses(): array
{
    return ['posted' => 'Posted', 'pending_approval' => 'Pending Approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'released' => 'Released', 'cancelled' => 'Cancelled'];
}

// Labels for every stored mode (older records may still show Check, Bank Transfer or Other).
function finance_payment_modes(): array
{
    return ['cash' => 'Cash', 'check' => 'Check', 'bank_transfer' => 'Bank Transfer', 'other' => 'Other'];
}

// Modes allowed for new collections and releases: the barangay accepts and pays out cash only.
function finance_payment_mode_options(): array
{
    return ['cash' => 'Cash'];
}

function finance_status_badge(string $status): string
{
    $tone = ['posted' => 'active', 'pending_approval' => 'pending', 'approved' => 'moved', 'rejected' => 'deceased', 'released' => 'active', 'cancelled' => 'inactive'][$status] ?? 'inactive';
    return '<span class="resident-status resident-status-' . e($tone) . '">' . e(finance_statuses()[$status] ?? ucfirst($status)) . '</span>';
}

function finance_format_date(?string $value): string
{
    if (!$value) return '';
    $date = date_create($value);
    return $date ? $date->format('M j, Y') : $value;
}

function finance_format_datetime(?string $value): string
{
    if (!$value) return '';
    $date = date_create($value);
    return $date ? $date->format('M j, Y g:i A') : $value;
}

function finance_valid_date(string $value): ?DateTimeImmutable
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $parsed && $parsed->format('Y-m-d') === $value ? $parsed : null;
}

// Highest amount allowed for one collection (Record Collection), set by the barangay.
function finance_collection_max(): string
{
    return '10000.00';
}

// "12,300.5" → "12300.50"; null when not a positive amount with at most two decimals (max 99,999,999,999.99).
function finance_parse_amount(string $value): ?string
{
    $value = str_replace([',', ' ', '₱'], '', trim($value));
    if (!preg_match('/^\d{1,11}(\.\d{1,2})?$/', $value)) return null;
    [$whole, $cents] = array_pad(explode('.', $value), 2, '');
    $normalized = ltrim($whole, '0') === '' ? '0' : ltrim($whole, '0');
    $normalized .= '.' . str_pad($cents, 2, '0');
    return $normalized === '0.00' ? null : $normalized;
}

// ── Categories ─────────────────────────────────────────────────────────────────

// Categories for a form: active ones of the type (or all types), plus the one already on a record.
function finance_categories(PDO $connection, ?string $type = null, ?int $include_id = null, bool $all = false): array
{
    $where = [];
    $params = [];
    if ($type !== null) { $where[] = 'type = :type'; $params['type'] = $type; }
    if (!$all) { $where[] = '(is_active = 1 OR id = :include)'; $params['include'] = $include_id ?? 0; }
    $statement = $connection->prepare('SELECT id, name, type, is_allotment, is_active FROM finance_categories' . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY type, sort_order, name');
    $statement->execute($params);
    return $statement->fetchAll();
}

function finance_category(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare('SELECT id, name, type, is_allotment, is_active FROM finance_categories WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// ── Transactions ───────────────────────────────────────────────────────────────

function finance_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $statement = $connection->prepare('SELECT t.*, c.name AS category_name, c.is_allotment, r.first_name, r.middle_name, r.last_name, r.suffix, cu.name AS created_by_name, uu.name AS updated_by_name, au.name AS approved_by_name, ju.name AS rejected_by_name, ru.name AS released_by_name, xu.name AS cancelled_by_name FROM finance_transactions t INNER JOIN finance_categories c ON c.id = t.category_id LEFT JOIN residents r ON r.id = t.resident_id LEFT JOIN users cu ON cu.id = t.created_by LEFT JOIN users uu ON uu.id = t.updated_by LEFT JOIN users au ON au.id = t.approved_by LEFT JOIN users ju ON ju.id = t.rejected_by LEFT JOIN users ru ON ru.id = t.released_by LEFT JOIN users xu ON xu.id = t.cancelled_by WHERE t.id = :id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// Next automatic number for the year: DV-2026-0001 … (expenses), NTA-2026-01 … (allotments). Called inside the saving
// transaction; the UNIQUE keys are the final guard.
function finance_next_reference(PDO $connection, string $kind, int $year): array
{
    $statement = $connection->prepare('SELECT COALESCE(MAX(ref_seq), 0) FROM finance_transactions WHERE ref_kind = :kind AND ref_year = :year FOR UPDATE');
    $statement->execute(['kind' => $kind, 'year' => $year]);
    $seq = (int) $statement->fetchColumn() + 1;
    return ['reference_no' => $kind === 'dv' ? sprintf('DV-%d-%04d', $year, $seq) : sprintf('NTA-%d-%02d', $year, $seq), 'ref_kind' => $kind, 'ref_year' => $year, 'ref_seq' => $seq];
}

function finance_resident(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare('SELECT id, first_name, middle_name, last_name, suffix, purok, status FROM residents WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// Fields the Treasurer enters. payment_mode / check_no are entered for Income here and for Expenses at release.
function finance_fields(): array
{
    return ['transaction_date', 'category_id', 'description', 'amount', 'payor_or_payee', 'resident_id', 'payment_mode', 'check_no', 'remarks'];
}

// Server-side validation of a new record, or of an Expense edited while Pending Approval ($existing).
function finance_validate(PDO $connection, array $input, ?array $existing = null): array
{
    $values = [
        'type' => $existing['type'] ?? (string) ($input['type'] ?? ''),
        'or_number' => strtoupper(preg_replace('/\s+/', '', (string) ($input['or_number'] ?? '')) ?? ''),
        'transaction_date' => trim((string) ($input['transaction_date'] ?? '')),
        'category_id' => trim((string) ($input['category_id'] ?? '')),
        'description' => residents_collapse($input['description'] ?? ''),
        'amount' => trim((string) ($input['amount'] ?? '')),
        'payor_or_payee' => residents_collapse($input['payor_or_payee'] ?? ''),
        'resident_id' => trim((string) ($input['resident_id'] ?? '')),
        'payment_mode' => (string) ($input['payment_mode'] ?? ''),
        'check_no' => strtoupper(residents_collapse($input['check_no'] ?? '')),
        'remarks' => trim((string) ($input['remarks'] ?? '')),
    ];
    $errors = [];
    $today = new DateTimeImmutable('today');
    if (!array_key_exists($values['type'], finance_types())) $errors['type'] = 'Select Income or Expense.';
    $income = $values['type'] === 'income';

    $category = null;
    $category_id = filter_var($values['category_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($category_id) $category = finance_category($connection, $category_id);
    if ($category === null || ($values['type'] !== '' && $category['type'] !== $values['type'])) $errors['category_id'] = 'Select a category for ' . ($income ? 'income' : 'expenses') . '.';
    elseif ((int) $category['is_active'] !== 1 && (int) ($existing['category_id'] ?? 0) !== (int) $category_id) $errors['category_id'] = 'This category is no longer in use. Select another category.';
    else $values['category_id'] = (int) $category_id;
    $allotment = $category !== null && (int) $category['is_allotment'] === 1 && $income;

    // OR number: typed for income except allotments (NTA-YYYY-## is automatic); expenses get DV-YYYY-#### automatically.
    if ($income && !$allotment && $existing === null) {
        if (!preg_match('/^OR-\d{4}-\d{1,10}$/', $values['or_number'])) $errors['or_number'] = 'Enter the official receipt number, for example OR-2026-0412.';
        else {
            $statement = $connection->prepare('SELECT COUNT(*) FROM finance_transactions WHERE reference_no = :reference');
            $statement->execute(['reference' => $values['or_number']]);
            if ((int) $statement->fetchColumn() > 0) $errors['or_number'] = 'This OR number is already recorded. Each official receipt number can be used only once.';
        }
    }

    $date = finance_valid_date($values['transaction_date']);
    if ($date === null) $errors['transaction_date'] = 'Enter a valid date.';
    elseif ($date < new DateTimeImmutable('2000-01-01')) $errors['transaction_date'] = 'Enter a realistic date.';
    elseif ($income && $date > $today) $errors['transaction_date'] = 'A collection cannot be dated in the future.';
    elseif (!$income && $date > $today->modify('+1 year')) $errors['transaction_date'] = 'Enter a date within the next year.';
    elseif (($earliest = finance_earliest_date($connection)) !== null && $values['transaction_date'] < $earliest) $errors['transaction_date'] = 'The date cannot be before the beginning balance date (' . finance_format_date($earliest) . ').';

    if (mb_strlen($values['description']) < 3 || mb_strlen($values['description']) > 255) $errors['description'] = 'Enter a description of 3 to 255 characters.';
    $amount = finance_parse_amount($values['amount']);
    if ($amount === null) $errors['amount'] = 'Enter an amount greater than zero, with up to two decimals (e.g. 12300.00).';
    elseif ($income && finance_cents($amount) > finance_cents(finance_collection_max())) $errors['amount'] = 'A collection can be at most ' . finance_peso(finance_collection_max()) . '.';
    else $values['amount'] = $amount;
    if (mb_strlen($values['payor_or_payee']) < 2 || mb_strlen($values['payor_or_payee']) > 150) $errors['payor_or_payee'] = 'Enter the ' . ($income ? 'payor' : 'payee') . ' (2 to 150 characters).';

    if ($values['resident_id'] !== '') {
        $resident_id = filter_var($values['resident_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $resident = $resident_id ? finance_resident($connection, $resident_id) : null;
        if ($resident === null) $errors['resident_id'] = 'Select the resident again, or clear the resident link.';
        else $values['resident_id'] = (int) $resident_id;
    }

    if ($income) {
        if (!array_key_exists($values['payment_mode'], finance_payment_mode_options())) $errors['payment_mode'] = 'Select the mode of payment.';
        elseif ($values['payment_mode'] === 'check' && !preg_match('/^[A-Z0-9\-]{3,40}$/', $values['check_no'])) $errors['check_no'] = 'Enter the check number (letters, numbers and dashes).';
        if ($values['payment_mode'] !== 'check') $values['check_no'] = '';
    } else {
        $values['payment_mode'] = '';   // recorded when the disbursement is released
        $values['check_no'] = '';
    }
    if (mb_strlen($values['remarks']) > 1000) $errors['remarks'] = 'Remarks must not exceed 1,000 characters.';
    foreach (['resident_id', 'payment_mode', 'check_no', 'remarks'] as $nullable) if ($values[$nullable] === '') $values[$nullable] = null;
    $values['is_allotment'] = $allotment;
    return ['values' => $values, 'errors' => $errors];
}

// Old and new values of the changed fields (for the audit history). Money is compared as DECIMAL text.
function finance_changes(array $old, array $new, array $fields): array
{
    $changes = [];
    foreach ($fields as $field) {
        $before = $old[$field] ?? null;
        $after = $new[$field] ?? null;
        if ((string) $before !== (string) $after) $changes[$field] = ['old' => $before, 'new' => $after];
    }
    return $changes;
}

function finance_audit(PDO $connection, int $id, string $action, array $details = []): void
{
    residents_audit($connection, 'finance', $id, $action, $details);
}

// Bell notifications (existing user_notifications table) to every active account of the given role.
function finance_notify(PDO $connection, int $transaction_id, string $role, string $category, string $title, string $message): void
{
    $statement = $connection->prepare("INSERT IGNORE INTO user_notifications (recipient_user_id, category, entity_type, entity_id, title, message) SELECT id, :category, 'finance_transaction', :id, :title, :message FROM users WHERE role = :role AND status = 'active'");
    $statement->execute(['category' => $category, 'id' => $transaction_id, 'title' => mb_substr($title, 0, 200), 'message' => mb_strimwidth($message, 0, 500, '…'), 'role' => $role]);
}

// What the signed-in user may do with a record now (also enforced again by finance_action.php).
function finance_allowed_actions(array $row): array
{
    $status = (string) $row['status'];
    return [
        'edit' => finance_can('edit') && $row['type'] === 'expense' && $status === 'pending_approval',
        'approve' => finance_can('approve') && $row['type'] === 'expense' && $status === 'pending_approval',
        'reject' => finance_can('approve') && $row['type'] === 'expense' && $status === 'pending_approval',
        'release' => finance_can('release') && $row['type'] === 'expense' && $status === 'approved',
        'cancel' => finance_can('cancel') && !in_array($status, ['cancelled', 'rejected'], true),
        'attach' => finance_can('create') && $status !== 'cancelled',
    ];
}

// ── List state (tabs, search, filters, sorting, paging — all kept in the URL) ─────────────────────────────────

function finance_tabs(): array
{
    return ['' => 'All', 'collections' => 'Collections', 'disbursements' => 'Disbursements', 'pending' => 'Pending Approval'];
}

function finance_sort_columns(): array
{
    return [
        'date' => 't.transaction_date',
        'reference' => 't.reference_no',
        'description' => 't.description',
        'category' => 'c.name',
        'type' => 't.type',
        'amount' => 't.amount',
        'status' => "FIELD(t.status, 'pending_approval', 'approved', 'released', 'posted', 'rejected', 'cancelled')",
    ];
}

function finance_list_state(array $input): array
{
    $date = static fn (string $value): string => finance_valid_date($value) ? $value : '';
    $state = [
        'tab' => (string) ($input['tab'] ?? ''),
        'q' => mb_substr(residents_collapse((string) ($input['q'] ?? '')), 0, 100),
        'status' => (string) ($input['status'] ?? ''),
        'type' => (string) ($input['type'] ?? ''),
        'category' => filter_var($input['category'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
        'from' => $date((string) ($input['from'] ?? '')),
        'to' => $date((string) ($input['to'] ?? '')),
        'sort' => (string) ($input['sort'] ?? 'date'),
        'dir' => (string) ($input['dir'] ?? 'desc'),
        'page' => max(1, (int) ($input['page'] ?? 1)),
    ];
    if (!array_key_exists($state['tab'], finance_tabs())) $state['tab'] = '';
    if (!array_key_exists($state['status'], finance_statuses())) $state['status'] = '';
    if (!array_key_exists($state['type'], finance_types())) $state['type'] = '';
    if ($state['from'] !== '' && $state['to'] !== '' && $state['from'] > $state['to']) [$state['from'], $state['to']] = [$state['to'], $state['from']];
    if (!array_key_exists($state['sort'], finance_sort_columns())) $state['sort'] = 'date';
    if (!in_array($state['dir'], ['asc', 'desc'], true)) $state['dir'] = 'desc';
    return $state;
}

function finance_query_string(array $state): string
{
    return http_build_query(array_filter([
        'tab' => $state['tab'], 'q' => $state['q'], 'status' => $state['status'], 'type' => $state['type'], 'category' => $state['category'], 'from' => $state['from'], 'to' => $state['to'],
        'sort' => $state['sort'] === 'date' ? '' : $state['sort'], 'dir' => $state['dir'] === 'desc' ? '' : $state['dir'],
        'page' => ($state['page'] ?? 1) > 1 ? $state['page'] : '',
    ], static fn ($value): bool => $value !== null && $value !== ''));
}

function finance_state_filtered(array $state): bool
{
    return $state['q'] !== '' || $state['status'] !== '' || $state['type'] !== '' || $state['category'] !== null || $state['from'] !== '' || $state['to'] !== '';
}

function finance_list_where(array $state): array
{
    $where = [];
    $params = [];
    if ($state['tab'] === 'collections') $where[] = "t.type = 'income'";
    elseif ($state['tab'] === 'disbursements') $where[] = "t.type = 'expense'";
    elseif ($state['tab'] === 'pending') $where[] = "t.status = 'pending_approval'";
    if ($state['q'] !== '') {
        $like = '%' . addcslashes($state['q'], '%_\\') . '%';
        $parts = [];
        foreach (['t.reference_no', 't.description', 't.payor_or_payee', 't.check_no', 't.remarks', 'c.name'] as $index => $column) { $parts[] = "$column LIKE :search$index"; $params['search' . $index] = $like; }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($state['status'] !== '') { $where[] = 't.status = :status'; $params['status'] = $state['status']; }
    if ($state['type'] !== '') { $where[] = 't.type = :type'; $params['type'] = $state['type']; }
    if ($state['category'] !== null) { $where[] = 't.category_id = :category'; $params['category'] = $state['category']; }
    if ($state['from'] !== '') { $where[] = 't.transaction_date >= :from'; $params['from'] = $state['from']; }
    if ($state['to'] !== '') { $where[] = 't.transaction_date <= :to'; $params['to'] = $state['to']; }
    return [$where === [] ? ['1 = 1'] : $where, $params];
}

function finance_sort_sql(string $sort, string $dir): string
{
    $column = finance_sort_columns()[$sort] ?? finance_sort_columns()['date'];
    $direction = $dir === 'asc' ? 'ASC' : 'DESC';
    return "$column $direction, t.id $direction";
}

function finance_pending_count(PDO $connection): int
{
    return (int) $connection->query("SELECT COUNT(*) FROM finance_transactions WHERE status = 'pending_approval'")->fetchColumn();
}

// ── Phase 3: budget, summary cards and cashbook (migration 20261005_finance_budgets) ───────────────────────────
// Totals always use posted collections and released disbursements only; pending, approved, rejected and cancelled
// records never count. A disbursement belongs to the budget year of its transaction date (the DV year).

function finance_budget_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) $ready = (int) $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_budgets'")->fetchColumn() === 1;
    return $ready;
}

// Module sections shown on every Financial Management list page.
function finance_nav(string $active): string
{
    $tabs = ['transactions' => ['finance.php', 'Transactions'], 'budget' => ['finance_budget.php', 'Budget'], 'cashbook' => ['finance_cashbook.php', 'Cashbook'], 'reports' => ['finance_reports.php', 'Reports'], 'opening' => ['finance_opening.php', 'Beginning Balance']];
    $html = '<nav class="announcement-tabs page-tabs fin-sections" aria-label="Financial Management sections">';
    foreach ($tabs as $key => [$href, $label]) $html .= '<a class="announcement-tab' . ($key === $active ? ' active" aria-current="page' : '') . '" href="' . e($href) . '">' . e($label) . '</a>';
    return $html . '</nav>';
}

// Adds or subtracts two DECIMAL(14,2) strings exactly (cents as integers; no floating-point rounding).
function finance_cents(mixed $value): int
{
    $value = trim((string) $value);
    if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $m)) return ($m[1] === '-' ? -1 : 1) * ((int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0'));
    return (int) round((float) $value * 100);
}

function finance_from_cents(int $cents): string
{
    return ($cents < 0 ? '-' : '') . intdiv(abs($cents), 100) . '.' . str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
}

// Per Expense category for a year: appropriated, released, awaiting (pending approval or approved, not yet released),
// remaining (appropriated − released) and percentage used. Lists active categories and any category with a budget or
// spending that year.
function finance_budget_rows(PDO $connection, int $year): array
{
    $statement = $connection->prepare("SELECT c.id, c.name, c.is_active, COALESCE(b.appropriated, 0) AS appropriated, b.id AS budget_id,
            COALESCE((SELECT SUM(t.amount) FROM finance_transactions t WHERE t.category_id = c.id AND t.status = 'released' AND YEAR(t.transaction_date) = :y1), 0) AS released,
            COALESCE((SELECT SUM(t.amount) FROM finance_transactions t WHERE t.category_id = c.id AND t.status IN ('pending_approval', 'approved') AND YEAR(t.transaction_date) = :y2), 0) AS awaiting
        FROM finance_categories c LEFT JOIN finance_budgets b ON b.category_id = c.id AND b.budget_year = :y3
        WHERE c.type = 'expense' ORDER BY c.sort_order, c.name");
    $statement->execute(['y1' => $year, 'y2' => $year, 'y3' => $year]);
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        if ((int) $row['is_active'] !== 1 && $row['budget_id'] === null && finance_cents($row['released']) === 0 && finance_cents($row['awaiting']) === 0) continue;
        $appropriated = finance_cents($row['appropriated']);
        $released = finance_cents($row['released']);
        $row['remaining'] = finance_from_cents($appropriated - $released);
        $row['percent'] = $appropriated > 0 ? round($released / $appropriated * 100, 1) : ($released > 0 ? null : 0.0);
        $rows[] = $row;
    }
    return $rows;
}

// Budget warning for a disbursement (not a block): null when it fits. It fits when appropriated − released − other
// disbursements awaiting approval or release (same category and year) still covers the amount.
function finance_budget_check(PDO $connection, int $category_id, int $year, string $amount, int $exclude_id = 0): ?array
{
    if (!finance_budget_ready($connection)) return null;
    $statement = $connection->prepare("SELECT COALESCE((SELECT appropriated FROM finance_budgets WHERE category_id = :c1 AND budget_year = :y1), 0) AS appropriated, (SELECT COUNT(*) FROM finance_budgets WHERE category_id = :c4 AND budget_year = :y4) AS has_budget,
            COALESCE((SELECT SUM(amount) FROM finance_transactions WHERE category_id = :c2 AND status = 'released' AND YEAR(transaction_date) = :y2 AND id <> :x1), 0) AS released,
            COALESCE((SELECT SUM(amount) FROM finance_transactions WHERE category_id = :c3 AND status IN ('pending_approval', 'approved') AND YEAR(transaction_date) = :y3 AND id <> :x2), 0) AS awaiting");
    $statement->execute(['c1' => $category_id, 'y1' => $year, 'c2' => $category_id, 'y2' => $year, 'x1' => $exclude_id, 'c3' => $category_id, 'y3' => $year, 'x2' => $exclude_id, 'c4' => $category_id, 'y4' => $year]);
    $row = $statement->fetch();
    $available = finance_cents($row['appropriated']) - finance_cents($row['released']) - finance_cents($row['awaiting']);
    $excess = finance_cents($amount) - $available;
    if ($excess <= 0) return null;
    return ['year' => $year, 'has_budget' => (int) $row['has_budget'] > 0, 'appropriated' => $row['appropriated'], 'released' => $row['released'], 'awaiting' => $row['awaiting'], 'remaining' => finance_from_cents(finance_cents($row['appropriated']) - finance_cents($row['released'])), 'available' => finance_from_cents($available), 'excess' => finance_from_cents($excess)];
}

function finance_budget_message(array $warning, string $category): string
{
    if (!$warning['has_budget']) return 'No budget is set for ' . $category . ' in ' . $warning['year'] . '. This disbursement is not covered by an appropriation.';
    return 'This exceeds the ' . $warning['year'] . ' budget for ' . $category . ' by ' . finance_peso($warning['excess']) . '. Appropriated ' . finance_peso($warning['appropriated']) . ', released ' . finance_peso($warning['released']) . ', remaining ' . finance_peso($warning['remaining']) . ((float) $warning['awaiting'] > 0 ? ', of which ' . finance_peso($warning['awaiting']) . ' is already set aside for other disbursements awaiting approval or release' : '') . '.';
}

// Summary cards: collections and disbursements this month, current fund balance (all posted collections less all
// released disbursements), and the number of disbursements pending approval.
function finance_summary(PDO $connection): array
{
    $start = date('Y-m-01');
    $end = date('Y-m-t');
    $statement = $connection->prepare("SELECT
            COALESCE(SUM(CASE WHEN type = 'income' AND status = 'posted' AND transaction_date BETWEEN :s1 AND :e1 THEN amount END), 0) AS month_collections,
            COALESCE(SUM(CASE WHEN type = 'expense' AND status = 'released' AND release_date BETWEEN :s2 AND :e2 THEN amount END), 0) AS month_disbursements,
            COALESCE(SUM(CASE WHEN type = 'income' AND status = 'posted' THEN amount END), 0) AS all_collections,
            COALESCE(SUM(CASE WHEN type = 'expense' AND status = 'released' THEN amount END), 0) AS all_disbursements,
            SUM(status = 'pending_approval') AS pending
        FROM finance_transactions");
    $statement->execute(['s1' => $start, 'e1' => $end, 's2' => $start, 'e2' => $end]);
    $row = $statement->fetch();
    $opening = finance_opening($connection);
    $balance = ($opening !== null ? finance_cents($opening['amount']) : 0) + finance_cents($row['all_collections']) - finance_cents($row['all_disbursements']);
    return ['month_collections' => $row['month_collections'], 'month_disbursements' => $row['month_disbursements'], 'balance' => finance_from_cents($balance), 'pending' => (int) $row['pending']];
}

// Cashbook: posted collections (by transaction date) and released disbursements (by release date) in date order, with
// the balance carried forward from before the period (including the beginning balance) and a running balance.
function finance_cashbook(PDO $connection, string $from, string $to): array
{
    $balance = finance_balance_before($connection, $from, $to);
    $statement = $connection->prepare("SELECT t.id, t.reference_no, t.type, t.description, t.payor_or_payee, t.amount, c.name AS category_name, CASE WHEN t.type = 'income' THEN t.transaction_date ELSE t.release_date END AS book_date
        FROM finance_transactions t INNER JOIN finance_categories c ON c.id = t.category_id
        WHERE (t.type = 'income' AND t.status = 'posted' AND t.transaction_date BETWEEN :f1 AND :t1) OR (t.type = 'expense' AND t.status = 'released' AND t.release_date BETWEEN :f2 AND :t2)
        ORDER BY book_date, t.type = 'expense', t.id");
    $statement->execute(['f1' => $from, 't1' => $to, 'f2' => $from, 't2' => $to]);
    $rows = [];
    $in = 0;
    $out = 0;
    $opening_balance = $balance;
    foreach ($statement->fetchAll() as $row) {
        $cents = finance_cents($row['amount']);
        if ($row['type'] === 'income') { $balance += $cents; $in += $cents; } else { $balance -= $cents; $out += $cents; }
        $row['balance'] = finance_from_cents($balance);
        $rows[] = $row;
    }
    return ['opening' => finance_from_cents($opening_balance), 'rows' => $rows, 'total_in' => finance_from_cents($in), 'total_out' => finance_from_cents($out), 'closing' => finance_from_cents($balance)];
}

function finance_summary_cards(array $summary): string
{
    $cards = [
        ['Total Collections (this month)', finance_peso($summary['month_collections']), 'tone-income', 'finance.php?tab=collections&from=' . date('Y-m-01') . '&to=' . date('Y-m-t')],
        ['Total Disbursements (this month)', finance_peso($summary['month_disbursements']), 'tone-expense', 'finance_cashbook.php'],
        ['Current Fund Balance', finance_peso($summary['balance']), 'tone-balance', 'finance_cashbook.php'],
        ['Pending Approvals', number_format($summary['pending']), 'tone-pending', 'finance.php?tab=pending'],
    ];
    $html = '<div class="fin-cards" role="list" aria-label="Financial summary">';
    foreach ($cards as [$label, $value, $tone, $href]) $html .= '<a class="fin-card ' . $tone . '" role="listitem" href="' . e($href) . '"><span class="fin-card-value">' . e($value) . '</span><span class="fin-card-label">' . e($label) . '</span></a>';
    return $html . '</div>';
}

// ── Phase 4: beginning balance, monthly report and audit history ───────────────────────────────────────────────

function finance_opening_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) $ready = (int) $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_opening_balances'")->fetchColumn() === 1;
    return $ready;
}

// The current beginning balance (not cancelled), or null.
function finance_opening(PDO $connection): ?array
{
    if (!finance_opening_ready($connection)) return null;
    $row = $connection->query('SELECT o.*, u.name AS created_by_name FROM finance_opening_balances o LEFT JOIN users u ON u.id = o.created_by WHERE o.cancelled_at IS NULL LIMIT 1')->fetch();
    return $row ?: null;
}

// Fund balance brought into a period that starts on $from and ends on $to: the beginning balance (when it is dated on
// or before the end of the period; nothing can be recorded before it) plus posted collections less released
// disbursements dated before the period.
function finance_balance_before(PDO $connection, string $from, string $to): int
{
    $statement = $connection->prepare("SELECT COALESCE(SUM(CASE WHEN type = 'income' AND status = 'posted' AND transaction_date < :f1 THEN amount END), 0) AS income, COALESCE(SUM(CASE WHEN type = 'expense' AND status = 'released' AND release_date < :f2 THEN amount END), 0) AS expense FROM finance_transactions");
    $statement->execute(['f1' => $from, 'f2' => $from]);
    $row = $statement->fetch();
    $opening = finance_opening($connection);
    $carried = $opening !== null && (string) $opening['as_of_date'] <= $to ? finance_cents($opening['amount']) : 0;
    return $carried + finance_cents($row['income']) - finance_cents($row['expense']);
}

// Earliest date a collection or disbursement may carry: the beginning balance date (or null when none is set).
function finance_earliest_date(PDO $connection): ?string
{
    $opening = finance_opening($connection);
    return $opening !== null ? (string) $opening['as_of_date'] : null;
}

// Monthly Financial Report: collections (posted, by transaction date) and disbursements (released, by release date)
// grouped by category, with totals and the beginning and ending balance of the month.
function finance_monthly_report(PDO $connection, int $year, int $month): array
{
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-t', strtotime($from));
    $collections = $connection->prepare("SELECT c.name, COUNT(*) AS entries, SUM(t.amount) AS total FROM finance_transactions t INNER JOIN finance_categories c ON c.id = t.category_id WHERE t.type = 'income' AND t.status = 'posted' AND t.transaction_date BETWEEN :f AND :t GROUP BY c.id, c.name ORDER BY c.sort_order, c.name");
    $collections->execute(['f' => $from, 't' => $to]);
    $disbursements = $connection->prepare("SELECT c.name, COUNT(*) AS entries, SUM(t.amount) AS total FROM finance_transactions t INNER JOIN finance_categories c ON c.id = t.category_id WHERE t.type = 'expense' AND t.status = 'released' AND t.release_date BETWEEN :f AND :t GROUP BY c.id, c.name ORDER BY c.sort_order, c.name");
    $disbursements->execute(['f' => $from, 't' => $to]);
    $in = $connection->prepare("SELECT t.reference_no, t.transaction_date AS book_date, t.description, t.payor_or_payee, t.amount, c.name AS category_name FROM finance_transactions t INNER JOIN finance_categories c ON c.id = t.category_id WHERE t.type = 'income' AND t.status = 'posted' AND t.transaction_date BETWEEN :f AND :t ORDER BY c.sort_order, c.name, t.transaction_date, t.id");
    $in->execute(['f' => $from, 't' => $to]);
    $out = $connection->prepare("SELECT t.reference_no, t.release_date AS book_date, t.description, t.payor_or_payee, t.amount, c.name AS category_name FROM finance_transactions t INNER JOIN finance_categories c ON c.id = t.category_id WHERE t.type = 'expense' AND t.status = 'released' AND t.release_date BETWEEN :f AND :t ORDER BY c.sort_order, c.name, t.release_date, t.id");
    $out->execute(['f' => $from, 't' => $to]);
    $collection_rows = $collections->fetchAll();
    $disbursement_rows = $disbursements->fetchAll();
    $total_in = array_sum(array_map(static fn (array $r): int => finance_cents($r['total']), $collection_rows));
    $total_out = array_sum(array_map(static fn (array $r): int => finance_cents($r['total']), $disbursement_rows));
    $beginning = finance_balance_before($connection, $from, $to);
    return [
        'from' => $from, 'to' => $to, 'label' => date('F Y', strtotime($from)),
        'collections' => $collection_rows, 'disbursements' => $disbursement_rows,
        'collection_entries' => $in->fetchAll(), 'disbursement_entries' => $out->fetchAll(),
        'total_collections' => finance_from_cents($total_in), 'total_disbursements' => finance_from_cents($total_out),
        'beginning' => finance_from_cents($beginning), 'ending' => finance_from_cents($beginning + $total_in - $total_out),
        'opening' => finance_opening($connection),
    ];
}

// Printed-report settings from templates/finance/report_settings.php (editable), with safe defaults.
function finance_report_settings(): array
{
    $defaults = ['header_lines' => [], 'barangay_name' => 'BARANGAY SAN JOSE', 'office' => '', 'address' => '', 'logo' => '', 'monthly_title' => 'MONTHLY FINANCIAL REPORT', 'list_title' => 'FINANCIAL TRANSACTIONS', 'show_details' => true, 'signatories' => []];
    $settings = require __DIR__ . '/../templates/finance/report_settings.php';
    $settings = is_array($settings) ? array_merge($defaults, $settings) : $defaults;
    if ($settings['logo'] !== '' && !is_file(__DIR__ . '/../' . $settings['logo'])) $settings['logo'] = '';
    return $settings;
}

// One-line description of the list filters, for exports, printouts and the audit log.
function finance_filter_summary(PDO $connection, array $state): string
{
    $parts = [];
    if ($state['tab'] !== '') $parts[] = finance_tabs()[$state['tab']];
    if ($state['status'] !== '') $parts[] = finance_statuses()[$state['status']];
    if ($state['type'] !== '') $parts[] = finance_types()[$state['type']];
    if ($state['category'] !== null && ($category = finance_category($connection, $state['category']))) $parts[] = $category['name'];
    if ($state['from'] !== '' || $state['to'] !== '') $parts[] = ($state['from'] !== '' ? finance_format_date($state['from']) : 'start') . ' to ' . ($state['to'] !== '' ? finance_format_date($state['to']) : 'today');
    if ($state['q'] !== '') $parts[] = 'Search: ' . $state['q'];
    return $parts === [] ? 'All transactions' : implode(' · ', $parts);
}

// Every transaction matching the list filters, in the list's order (for the Excel and PDF exports).
function finance_list_rows(PDO $connection, array $state, int $limit = 5000): array
{
    [$where, $params] = finance_list_where($state);
    $statement = $connection->prepare('SELECT t.*, c.name AS category_name FROM finance_transactions t INNER JOIN finance_categories c ON c.id = t.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . finance_sort_sql($state['sort'], $state['dir']) . ' LIMIT ' . max(1, $limit));
    $statement->execute($params);
    return $statement->fetchAll();
}

// ── Audit history shown on each transaction's page (Treasurer and Punong Barangay) ──────────────────────────────

function finance_history_labels(): array
{
    return ['finance_created' => 'Recorded', 'finance_updated' => 'Edited', 'finance_approved' => 'Approved', 'finance_rejected' => 'Rejected', 'finance_released' => 'Released', 'finance_cancelled' => 'Cancelled', 'finance_attachment_added' => 'Attachment added', 'finance_attachment_removed' => 'Attachment removed'];
}

function finance_field_labels(): array
{
    return ['transaction_date' => 'Date', 'category_id' => 'Category', 'description' => 'Description', 'amount' => 'Amount', 'payor_or_payee' => 'Payor / payee', 'resident_id' => 'Resident', 'payment_mode' => 'Mode of payment', 'check_no' => 'Check no.', 'remarks' => 'Remarks', 'status' => 'Status', 'release_date' => 'Release date'];
}

// A stored value as people read it (peso amounts, category and status names, dates).
function finance_history_value(PDO $connection, string $field, mixed $value): string
{
    if ($value === null || $value === '') return '—';
    return match ($field) {
        'amount' => finance_peso($value),
        'category_id' => (string) (finance_category($connection, (int) $value)['name'] ?? '#' . $value),
        'status' => finance_statuses()[(string) $value] ?? (string) $value,
        'payment_mode' => finance_payment_modes()[(string) $value] ?? (string) $value,
        'transaction_date', 'release_date' => finance_format_date((string) $value),
        'resident_id' => '#' . $value,
        default => is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE),
    };
}

// Audit entries of one transaction, oldest first, with their changes as [label, old, new] rows.
function finance_history(PDO $connection, int $transaction_id): array
{
    $statement = $connection->prepare("SELECT l.id, l.action, l.details, l.created_at, u.name AS user_name, u.role AS user_role FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id WHERE l.entity_type = 'finance' AND l.entity_id = :id ORDER BY l.created_at, l.id");
    $statement->execute(['id' => $transaction_id]);
    $history = [];
    foreach ($statement->fetchAll() as $row) {
        $details = json_decode((string) $row['details'], true) ?: [];
        $changes = [];
        foreach ((array) ($details['changes'] ?? []) as $field => $change) {
            $changes[] = [finance_field_labels()[$field] ?? ucfirst(str_replace('_', ' ', (string) $field)), finance_history_value($connection, (string) $field, $change['old'] ?? null), finance_history_value($connection, (string) $field, $change['new'] ?? null)];
        }
        if ($row['action'] === 'finance_created') foreach ((array) ($details['values'] ?? []) as $field => $value) if ($value !== null && $value !== '') $changes[] = [finance_field_labels()[$field] ?? (string) $field, '', finance_history_value($connection, (string) $field, $value)];
        $notes = array_values(array_filter([isset($details['reason']) ? 'Reason: ' . $details['reason'] : null, isset($details['name']) && str_starts_with($row['action'], 'finance_attachment') ? 'File: ' . $details['name'] : null, isset($details['budget_warning']) ? 'Budget warning: ' . $details['budget_warning'] : null]));
        $history[] = ['label' => finance_history_labels()[$row['action']] ?? ucfirst(str_replace('_', ' ', (string) $row['action'])), 'user' => $row['user_name'] ?: 'Unavailable account', 'role' => finance_role_label($row['user_role']) ?: '', 'at' => $row['created_at'], 'changes' => $changes, 'notes' => $notes];
    }
    return $history;
}

// ── Attachments (private storage/finance/, served only by finance_attachment.php) ──────────────────────────────

function finance_attachment_root(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'finance';
}

function finance_attachment_types(): array
{
    return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
}

// Validates an upload (size, real type checked on the server; images must decode, PDFs must start with %PDF-) and stores
// it under a random name. Returns [stored_name, mime, size]. Throws RuntimeException with a safe message.
function finance_store_attachment(array $file): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a file to upload.');
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('The file is larger than 10 MB.');
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) throw new RuntimeException('The file could not be uploaded. Please try again.');
    $path = (string) $file['tmp_name'];
    $size = (int) filesize($path);
    if ($size < 1 || $size > FINANCE_ATTACHMENT_MAX_BYTES) throw new RuntimeException('The file is larger than 10 MB.');
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $extension = finance_attachment_types()[$mime] ?? null;
    if ($extension === null) throw new RuntimeException('Upload a JPG, PNG or WebP image, or a PDF file.');
    if ($extension === 'pdf' ? (string) file_get_contents($path, false, null, 0, 5) !== '%PDF-' : @getimagesize($path) === false) throw new RuntimeException('The file could not be read. Upload a JPG, PNG, WebP or PDF file.');
    $directory = finance_attachment_root();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('The attachment folder could not be prepared.');
    $name = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($path, $directory . DIRECTORY_SEPARATOR . $name)) throw new RuntimeException('The file could not be stored. Please try again.');
    return [$name, $mime, $size];
}

function finance_attachment_path(?string $stored_name): ?string
{
    if ($stored_name === null || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|pdf)$/', $stored_name)) return null;
    $path = finance_attachment_root() . DIRECTORY_SEPARATOR . $stored_name;
    return is_file($path) ? $path : null;
}

// A display-safe original file name (no path, no control characters, at most 150 characters).
function finance_clean_filename(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F<>:"|?*]/u', '', $name) ?? '';
    return mb_substr($name !== '' ? $name : 'attachment', 0, 150);
}

function finance_attachments(PDO $connection, int $transaction_id, bool $archived = false): array
{
    $statement = $connection->prepare('SELECT a.*, u.name AS uploaded_by_name FROM finance_attachments a LEFT JOIN users u ON u.id = a.uploaded_by WHERE a.transaction_id = :id AND a.archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL') . ' ORDER BY a.created_at, a.id');
    $statement->execute(['id' => $transaction_id]);
    return $statement->fetchAll();
}
