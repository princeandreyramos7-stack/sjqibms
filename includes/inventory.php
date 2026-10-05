<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/residents.php';

// Inventory (inventory_items, inventory_categories, inventory_locations; migration 20260927_inventory_foundation).
// Access follows the sidebar item's roles (System Administrator). Records are archived, never deleted.

const INVENTORY_PHOTO_MAX_BYTES = 5 * 1024 * 1024;

// Only the System Administrator manages the inventory. Health Workers see it read-only and only the items in the
// "Medical" category (medicines and medical supplies); the menu item lists both roles.
function inventory_can_manage(): bool
{
    return can_access_navigation('inventory') && has_role('super_admin');
}

function inventory_can_view(): bool
{
    return can_access_navigation('inventory');
}

// Health Workers: only the "Medical" category.
function inventory_medical_only(): bool
{
    return has_role('health_worker');
}

function inventory_medical_category_id(PDO $connection): ?int
{
    static $id = false;
    if ($id === false) { $value = $connection->query("SELECT id FROM inventory_categories WHERE name = 'Medical' LIMIT 1")->fetchColumn(); $id = $value === false ? null : (int) $value; }
    return $id;
}

function inventory_require_view(): void
{
    require_auth();
    if (!inventory_can_view()) { http_response_code(403); exit('Access denied.'); }
}

function inventory_require_manage(): void
{
    require_auth();
    if (!inventory_can_manage()) { http_response_code(403); exit('Access denied.'); }
}

function inventory_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $check = $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('inventory_items', 'inventory_categories', 'inventory_locations')");
        $ready = (int) $check->fetchColumn() === 3;
    }
    return $ready;
}

// ── Labels ─────────────────────────────────────────────────────────────────────

function inventory_types(): array
{
    return ['equipment' => 'Equipment', 'supply' => 'Supply'];
}

function inventory_statuses(): array
{
    return ['available' => 'Available', 'in_use' => 'In Use', 'borrowed' => 'Borrowed', 'for_repair' => 'For Repair', 'low_stock' => 'Low Stock', 'unserviceable' => 'Unserviceable'];
}

function inventory_conditions(): array
{
    return ['good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor', 'unserviceable' => 'Unserviceable'];
}

function inventory_fund_sources(): array
{
    return ['barangay_fund' => 'Barangay Fund', 'donation' => 'Donation', 'lgu_grant' => 'LGU Grant', 'other' => 'Other'];
}

// Suggested units (any short unit may be typed).
function inventory_units(): array
{
    return ['pcs', 'unit', 'units', 'set', 'sets', 'reams', 'kits', 'boxes', 'packs', 'bottles', 'liters', 'rolls', 'pairs'];
}

// Badge colours reuse the existing status tones (same as the former sample page).
function inventory_status_badge(string $status): string
{
    $tone = ['available' => 'active', 'in_use' => 'moved', 'borrowed' => 'moved', 'for_repair' => 'deceased', 'low_stock' => 'pending', 'unserviceable' => 'inactive'][$status] ?? 'inactive';
    return '<span class="resident-status resident-status-' . e($tone) . '">' . e(inventory_statuses()[$status] ?? ucfirst($status)) . '</span>';
}

function inventory_quantity_label(array $item): string
{
    return number_format((int) $item['quantity']) . ' ' . $item['unit'];
}

function inventory_money(mixed $value): string
{
    return $value === null || $value === '' ? '' : '₱' . number_format((float) $value, 2);
}

function inventory_format_date(?string $value): string
{
    if (!$value) return '';
    $date = date_create($value);
    return $date ? $date->format('M j, Y') : $value;
}

// ── Lists (categories, locations) ──────────────────────────────────────────────

// Active entries plus the entry currently used by the record being edited (so an old value is never silently replaced).
function inventory_list(PDO $connection, string $table, ?int $include_id = null): array
{
    $table = $table === 'locations' ? 'inventory_locations' : 'inventory_categories';
    $statement = $connection->prepare("SELECT id, name, is_active FROM $table WHERE is_active = 1 OR id = :include ORDER BY name");
    $statement->execute(['include' => $include_id ?? 0]);
    return $statement->fetchAll();
}

// ── Records ────────────────────────────────────────────────────────────────────

function inventory_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $statement = $connection->prepare('SELECT i.*, c.name AS category_name, l.name AS location_name, cu.name AS created_by_name, uu.name AS updated_by_name, au.name AS archived_by_name FROM inventory_items i INNER JOIN inventory_categories c ON c.id = i.category_id INNER JOIN inventory_locations l ON l.id = i.location_id LEFT JOIN users cu ON cu.id = i.created_by LEFT JOIN users uu ON uu.id = i.updated_by LEFT JOIN users au ON au.id = i.archived_by WHERE i.id = :id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// Next item code (INV-001, INV-002, … INV-1000). Called inside the saving transaction; the unique key is the final guard.
function inventory_next_code(PDO $connection): string
{
    $max = (int) $connection->query("SELECT COALESCE(MAX(CAST(SUBSTRING(item_code, 5) AS UNSIGNED)), 0) FROM inventory_items WHERE item_code REGEXP '^INV-[0-9]+$' FOR UPDATE")->fetchColumn();
    return sprintf('INV-%03d', $max + 1);
}

// Supplies: 'Low Stock' whenever quantity <= reorder level (unless the item is For Repair or Unserviceable); a supply
// that is back above its reorder level returns to Available. Equipment never uses Low Stock.
function inventory_apply_status_rules(array $values): array
{
    if ($values['item_type'] !== 'supply') return $values;
    $low = $values['reorder_level'] !== null && $values['quantity'] <= $values['reorder_level'];
    if ($low && !in_array($values['status'], ['for_repair', 'unserviceable'], true)) $values['status'] = 'low_stock';
    elseif (!$low && $values['status'] === 'low_stock') $values['status'] = 'available';
    return $values;
}

function inventory_fields(): array
{
    return ['name', 'description', 'item_type', 'category_id', 'location_id', 'quantity', 'unit', 'status', 'item_condition', 'custodian', 'serial_number', 'property_number', 'date_acquired', 'unit_cost', 'source_of_funds', 'reorder_level', 'expiry_date', 'remarks'];
}

// Server-side validation of every field. $existing is the stored record when editing.
function inventory_validate(PDO $connection, array $input, ?array $existing = null): array
{
    $text = static fn (string $key): string => residents_collapse($input[$key] ?? '');
    $values = [
        'name' => $text('name'),
        'description' => trim((string) ($input['description'] ?? '')),
        'item_type' => (string) ($input['item_type'] ?? ''),
        'category_id' => trim((string) ($input['category_id'] ?? '')),
        'location_id' => trim((string) ($input['location_id'] ?? '')),
        'quantity' => trim((string) ($input['quantity'] ?? '')),
        'unit' => $text('unit'),
        'status' => (string) ($input['status'] ?? 'available'),
        'item_condition' => (string) ($input['item_condition'] ?? 'good'),
        'custodian' => $text('custodian'),
        'serial_number' => $text('serial_number'),
        'property_number' => $text('property_number'),
        'date_acquired' => trim((string) ($input['date_acquired'] ?? '')),
        'unit_cost' => str_replace(',', '', trim((string) ($input['unit_cost'] ?? ''))),
        'source_of_funds' => (string) ($input['source_of_funds'] ?? ''),
        'reorder_level' => trim((string) ($input['reorder_level'] ?? '')),
        'expiry_date' => trim((string) ($input['expiry_date'] ?? '')),
        'remarks' => trim((string) ($input['remarks'] ?? '')),
    ];
    $errors = [];
    $date = static function (string $value): ?DateTimeImmutable {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $parsed && $parsed->format('Y-m-d') === $value ? $parsed : null;
    };

    if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 150) $errors['name'] = 'Enter the item name (2 to 150 characters).';
    if (mb_strlen($values['description']) > 2000) $errors['description'] = 'The description must not exceed 2,000 characters.';
    if (!array_key_exists($values['item_type'], inventory_types())) $errors['item_type'] = 'Select Equipment or Supply.';
    foreach (['category_id' => ['inventory_categories', 'category'], 'location_id' => ['inventory_locations', 'location']] as $field => [$table, $label]) {
        $id = filter_var($values[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $check = $connection->prepare("SELECT 1 FROM $table WHERE id = :id AND (is_active = 1 OR id = :current)");
        $check->execute(['id' => $id ?: 0, 'current' => (int) ($existing[$field] ?? 0)]);
        if (!$id || !$check->fetchColumn()) $errors[$field] = "Select a $label.";
        else $values[$field] = $id;
    }
    if (!preg_match('/^\d{1,7}$/', $values['quantity']) || (int) $values['quantity'] > 1000000) $errors['quantity'] = 'Enter a whole number from 0 to 1,000,000.';
    else $values['quantity'] = (int) $values['quantity'];
    if ($values['unit'] === '' || mb_strlen($values['unit']) > 30 || !preg_match('/^\p{L}[\p{L}\s.\-]*$/u', $values['unit'])) $errors['unit'] = 'Enter a unit such as pcs, units, reams or kits.';
    if (!array_key_exists($values['status'], inventory_statuses())) $errors['status'] = 'Select a valid status.';
    elseif ($values['status'] === 'low_stock' && $values['item_type'] === 'equipment') $errors['status'] = 'Low Stock applies to supplies only.';
    if (!array_key_exists($values['item_condition'], inventory_conditions())) $errors['item_condition'] = 'Select a valid condition.';
    if (mb_strlen($values['custodian']) > 150) $errors['custodian'] = 'The name must not exceed 150 characters.';
    foreach (['serial_number' => 'Serial number', 'property_number' => 'Property number'] as $field => $label) {
        if (mb_strlen($values[$field]) > 100 || ($values[$field] !== '' && !preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\s\-\/.#]*$/u', $values[$field]))) $errors[$field] = "$label may contain letters, numbers, spaces, - / . # (up to 100 characters).";
    }
    if ($values['date_acquired'] !== '') {
        $acquired = $date($values['date_acquired']);
        if ($acquired === null) $errors['date_acquired'] = 'Enter a valid date.';
        elseif ($acquired > new DateTimeImmutable('today')) $errors['date_acquired'] = 'The date acquired cannot be in the future.';
        elseif ($acquired < new DateTimeImmutable('1950-01-01')) $errors['date_acquired'] = 'Enter a realistic date.';
    }
    if ($values['unit_cost'] !== '' && (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $values['unit_cost']))) $errors['unit_cost'] = 'Enter an amount such as 1500 or 1500.50.';
    elseif ($values['unit_cost'] !== '') $values['unit_cost'] = number_format((float) $values['unit_cost'], 2, '.', '');
    if ($values['source_of_funds'] !== '' && !array_key_exists($values['source_of_funds'], inventory_fund_sources())) $errors['source_of_funds'] = 'Select a valid source of funds.';
    if ($values['reorder_level'] !== '') {
        if (!preg_match('/^\d{1,7}$/', $values['reorder_level'])) $errors['reorder_level'] = 'Enter a whole number, or leave it blank.';
        else $values['reorder_level'] = (int) $values['reorder_level'];
    }
    if ($values['expiry_date'] !== '' && $date($values['expiry_date']) === null) $errors['expiry_date'] = 'Enter a valid date.';
    if (mb_strlen($values['remarks']) > 2000) $errors['remarks'] = 'Remarks must not exceed 2,000 characters.';

    foreach (['description', 'custodian', 'serial_number', 'property_number', 'date_acquired', 'unit_cost', 'source_of_funds', 'reorder_level', 'expiry_date', 'remarks'] as $nullable) {
        if ($values[$nullable] === '') $values[$nullable] = null;
    }
    // Reorder level is a supply setting; equipment never keeps one.
    if ($values['item_type'] === 'equipment') $values['reorder_level'] = null;
    if ($errors === []) $values = inventory_apply_status_rules($values);
    return ['values' => $values, 'errors' => $errors];
}

// Fields that changed (for the audit log). Values are compared as stored.
function inventory_changed_fields(array $values, array $existing): array
{
    return array_values(array_filter(inventory_fields(), static fn (string $field): bool => (string) ($values[$field] ?? '') !== (string) ($existing[$field] ?? '')));
}

function inventory_audit(PDO $connection, int $id, string $action, array $details = []): void
{
    residents_audit($connection, 'inventory', $id, $action, $details);
}

// ── List state (tabs, cards, filters, sorting, paging — all kept in the URL) ──────

function inventory_sort_columns(): array
{
    return [
        'code' => 'CAST(SUBSTRING(i.item_code, 5) AS UNSIGNED)',
        'name' => 'i.name',
        'category' => 'c.name',
        'quantity' => 'i.quantity',
        'location' => 'l.name',
        'status' => "FIELD(i.status, 'available', 'in_use', 'borrowed', 'for_repair', 'low_stock', 'unserviceable')",
    ];
}

// Validated list settings from the query string (unknown values fall back to the defaults).
function inventory_list_state(array $input, ?PDO $connection = null): array
{
    $int = static fn ($value): ?int => filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $state = [
        'q' => mb_substr(residents_collapse((string) ($input['q'] ?? '')), 0, 100),
        'type' => (string) ($input['type'] ?? ''),
        'status' => (string) ($input['status'] ?? ''),
        'expiring' => (string) ($input['expiring'] ?? '') === '1',
        'category' => $int($input['category'] ?? null),
        'location' => $int($input['location'] ?? null),
        'condition' => (string) ($input['condition'] ?? ''),
        'sort' => (string) ($input['sort'] ?? 'code'),
        'dir' => (string) ($input['dir'] ?? 'asc'),
        'per_page' => (int) ($input['per_page'] ?? 10),
        'page' => max(1, (int) ($input['page'] ?? 1)),
    ];
    if (!array_key_exists($state['type'], inventory_types())) $state['type'] = '';
    if ($state['status'] !== 'archived' && !array_key_exists($state['status'], inventory_statuses())) $state['status'] = '';
    if (!array_key_exists($state['condition'], inventory_conditions())) $state['condition'] = '';
    if (!array_key_exists($state['sort'], inventory_sort_columns())) $state['sort'] = 'code';
    if (!in_array($state['dir'], ['asc', 'desc'], true)) $state['dir'] = 'asc';
    if (!in_array($state['per_page'], [10, 25, 50], true)) $state['per_page'] = 10;
    return $state;
}

// Query string of the non-default settings (so shared links stay short).
function inventory_query_string(array $state): string
{
    return http_build_query(array_filter([
        'q' => $state['q'], 'type' => $state['type'], 'status' => $state['status'], 'expiring' => $state['expiring'] ? '1' : '',
        'category' => $state['category'], 'location' => $state['location'], 'condition' => $state['condition'],
        'sort' => $state['sort'] === 'code' ? '' : $state['sort'], 'dir' => $state['dir'] === 'asc' ? '' : $state['dir'],
        'per_page' => $state['per_page'] === 10 ? '' : $state['per_page'], 'page' => ($state['page'] ?? 1) > 1 ? $state['page'] : '',
    ], static fn ($value): bool => $value !== null && $value !== ''));
}

function inventory_state_filtered(array $state): bool
{
    return $state['q'] !== '' || $state['type'] !== '' || $state['status'] !== '' || $state['expiring'] || $state['category'] !== null || $state['location'] !== null || $state['condition'] !== '';
}

// WHERE conditions and parameters for the list. Archived items appear only under the Archived status filter.
function inventory_list_where(array $state, PDO $connection): array
{
    $where = [$state['status'] === 'archived' ? 'i.archived_at IS NOT NULL' : 'i.archived_at IS NULL'];
    $params = [];
    if ($state['q'] !== '') {
        $like = '%' . addcslashes($state['q'], '%_\\') . '%';
        $parts = [];
        foreach (['i.item_code', 'i.name', 'i.custodian', 'i.serial_number', 'i.property_number', 'c.name', 'l.name'] as $index => $column) { $parts[] = "$column LIKE :search$index"; $params['search' . $index] = $like; }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($state['type'] !== '') { $where[] = 'i.item_type = :type'; $params['type'] = $state['type']; }
    if ($state['status'] !== '' && $state['status'] !== 'archived') { $where[] = 'i.status = :status'; $params['status'] = $state['status']; }
    if ($state['expiring']) { $where[] = 'i.expiry_date IS NOT NULL AND i.expiry_date <= :soon'; $params['soon'] = date('Y-m-d', strtotime('+30 days')); }
    if ($state['category'] !== null) { $where[] = 'i.category_id = :category'; $params['category'] = $state['category']; }
    if ($state['location'] !== null) { $where[] = 'i.location_id = :location'; $params['location'] = $state['location']; }
    if ($state['condition'] !== '') { $where[] = 'i.item_condition = :condition'; $params['condition'] = $state['condition']; }
    return [$where, $params];
}

function inventory_sort_sql(string $sort, string $dir): string
{
    $column = inventory_sort_columns()[$sort] ?? inventory_sort_columns()['code'];
    $direction = $dir === 'desc' ? 'DESC' : 'ASC';
    return "$column $direction, i.item_code $direction, i.id";
}

// Summary cards. Counts cover active (not archived) items of the selected tab.
function inventory_card_definitions(): array
{
    return [
        'total' => ['label' => 'Total Items', 'tone' => 'total'],
        'available' => ['label' => 'Available', 'tone' => 'active'],
        'in_use' => ['label' => 'In Use', 'tone' => 'moved'],
        'borrowed' => ['label' => 'Borrowed', 'tone' => 'moved'],
        'for_repair' => ['label' => 'For Repair', 'tone' => 'deceased'],
        'low_stock' => ['label' => 'Low Stock', 'tone' => 'pending'],
        'expiring' => ['label' => 'Expiring Soon (30 days)', 'tone' => 'pending'],
    ];
}

function inventory_summary_counts(PDO $connection, string $type = ''): array
{
    $statement = $connection->prepare("SELECT COUNT(*) AS total, SUM(status = 'available') AS available, SUM(status = 'in_use') AS in_use, SUM(status = 'borrowed') AS borrowed, SUM(status = 'for_repair') AS for_repair, SUM(status = 'low_stock') AS low_stock, SUM(expiry_date IS NOT NULL AND expiry_date <= :soon) AS expiring FROM inventory_items WHERE archived_at IS NULL" . ($type !== '' ? ' AND item_type = :type' : ''));
    $statement->execute(['soon' => date('Y-m-d', strtotime('+30 days'))] + ($type !== '' ? ['type' => $type] : []));
    return array_map('intval', $statement->fetch() ?: []);
}

// ── Borrowing, issuing and the movement log (migration 20260927_inventory_borrowing_movements) ──────────────
// quantity on inventory_items is the quantity ON HAND. Lending equipment lowers it; a return adds back what came back
// (missing pieces are not added back). Supplies are issued, which lowers the quantity permanently.

function inventory_borrowing_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $check = $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('inventory_borrow_records', 'inventory_movements')");
        $ready = (int) $check->fetchColumn() === 2;
    }
    return $ready;
}

function inventory_movement_types(): array
{
    return ['added' => 'Added', 'issued' => 'Issued', 'borrowed' => 'Borrowed', 'returned' => 'Returned', 'adjusted' => 'Adjusted', 'repaired' => 'Repaired', 'status_changed' => 'Status changed', 'archived' => 'Archived', 'restored' => 'Restored'];
}

function inventory_return_conditions(): array
{
    return ['good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor', 'damaged' => 'Damaged'];
}

// Appends one movement row (no-op until the movement table exists). Called inside the caller's transaction.
function inventory_log_movement(PDO $connection, int $item_id, string $type, int $change, int $quantity_after, ?string $reason = null, ?string $status_from = null, ?string $status_to = null, ?string $recipient = null, ?int $borrow_id = null): void
{
    if (!inventory_borrowing_ready($connection)) return;
    $connection->prepare('INSERT INTO inventory_movements (item_id, movement_type, quantity_change, quantity_after, status_from, status_to, reason, recipient, borrow_id, performed_by) VALUES (:item, :type, :change, :after, :from, :to, :reason, :recipient, :borrow, :user)')
        ->execute(['item' => $item_id, 'type' => $type, 'change' => $change, 'after' => max(0, $quantity_after), 'from' => $status_from, 'to' => $status_to, 'reason' => $reason !== null ? mb_substr($reason, 0, 255) : null, 'recipient' => $recipient, 'borrow' => $borrow_id, 'user' => current_user()['id'] ?? null]);
}

// Movement type for a status change made in the item form: leaving For Repair counts as "repaired".
function inventory_status_movement_type(string $from, string $to): string
{
    return $from === 'for_repair' && in_array($to, ['available', 'in_use'], true) ? 'repaired' : 'status_changed';
}

// Quantity currently lent out (open borrow records).
function inventory_borrowed_out(PDO $connection, int $item_id): int
{
    if (!inventory_borrowing_ready($connection)) return 0;
    $statement = $connection->prepare('SELECT COALESCE(SUM(quantity), 0) FROM inventory_borrow_records WHERE item_id = :item AND actual_return_date IS NULL');
    $statement->execute(['item' => $item_id]);
    return (int) $statement->fetchColumn();
}

// Status after a borrow, return or issue. Supplies follow the Low Stock rule; equipment shows Borrowed while nothing is
// left on hand because of open borrows, and returns to Available once pieces are back. Other statuses are kept.
function inventory_status_after(array $item, int $on_hand, int $borrowed_out): string
{
    $status = (string) $item['status'];
    if ($item['item_type'] === 'supply') {
        return inventory_apply_status_rules(['item_type' => 'supply', 'status' => $status, 'quantity' => $on_hand, 'reorder_level' => $item['reorder_level'] !== null ? (int) $item['reorder_level'] : null])['status'];
    }
    if ($on_hand === 0 && $borrowed_out > 0 && $status === 'available') return 'borrowed';
    if ($status === 'borrowed' && $on_hand > 0) return 'available';
    return $status;
}

// Equipment can be lent when it is not archived, is Available or partly Borrowed, and has pieces on hand.
function inventory_can_borrow(array $item): bool
{
    return $item['archived_at'] === null && $item['item_type'] === 'equipment' && in_array($item['status'], ['available', 'borrowed'], true) && (int) $item['quantity'] > 0;
}

function inventory_can_issue(array $item): bool
{
    return $item['archived_at'] === null && $item['item_type'] === 'supply' && (int) $item['quantity'] > 0 && !in_array($item['status'], ['for_repair', 'unserviceable'], true);
}

// Next borrow reference: BR-YYYY-0001 (per year). Called inside the saving transaction.
function inventory_next_borrow_code(PDO $connection): string
{
    $prefix = 'BR-' . date('Y') . '-';
    $statement = $connection->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(borrow_code, 9) AS UNSIGNED)), 0) FROM inventory_borrow_records WHERE borrow_code LIKE :prefix FOR UPDATE');
    $statement->execute(['prefix' => $prefix . '%']);
    return $prefix . sprintf('%04d', (int) $statement->fetchColumn() + 1);
}

function inventory_valid_date(string $value): ?DateTimeImmutable
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $parsed && $parsed->format('Y-m-d') === $value ? $parsed : null;
}

function inventory_contact_error(string $contact): ?string
{
    if ($contact === '') return null;
    $digits = preg_replace('/\D/', '', $contact) ?? '';
    return mb_strlen($contact) > 30 || !preg_match('/^\+?[0-9][0-9\s()\-]*$/', $contact) || strlen($digits) < 7 || strlen($digits) > 15 ? 'Enter a valid contact number, for example 09171234567.' : null;
}

function inventory_validate_borrow(array $input, int $on_hand): array
{
    $values = [
        'borrower_name' => residents_collapse($input['borrower_name'] ?? ''),
        'borrower_contact' => residents_collapse($input['borrower_contact'] ?? ''),
        'borrower_address' => residents_collapse($input['borrower_address'] ?? ''),
        'quantity' => trim((string) ($input['quantity'] ?? '')),
        'purpose' => residents_collapse($input['purpose'] ?? ''),
        'date_borrowed' => trim((string) ($input['date_borrowed'] ?? '')),
        'expected_return_date' => trim((string) ($input['expected_return_date'] ?? '')),
        'remarks' => trim((string) ($input['remarks'] ?? '')),
    ];
    $errors = [];
    if (mb_strlen($values['borrower_name']) < 2 || mb_strlen($values['borrower_name']) > 150 || !preg_match("/^\p{L}[\p{L}\p{M}\s.,'\-]*$/u", $values['borrower_name'])) $errors['borrower_name'] = 'Enter the borrower\'s full name (letters only, 2 to 150 characters).';
    if (($contact_error = inventory_contact_error($values['borrower_contact'])) !== null) $errors['borrower_contact'] = $contact_error;
    if (mb_strlen($values['borrower_address']) < 2 || mb_strlen($values['borrower_address']) > 255) $errors['borrower_address'] = 'Enter the borrower\'s address or Purok.';
    if (!preg_match('/^\d{1,7}$/', $values['quantity']) || (int) $values['quantity'] < 1) $errors['quantity'] = 'Enter a quantity of at least 1.';
    elseif ((int) $values['quantity'] > $on_hand) $errors['quantity'] = 'Only ' . number_format($on_hand) . ' available to lend.';
    else $values['quantity'] = (int) $values['quantity'];
    if (mb_strlen($values['purpose']) < 3 || mb_strlen($values['purpose']) > 255) $errors['purpose'] = 'Describe the purpose (3 to 255 characters).';
    $borrowed = inventory_valid_date($values['date_borrowed']);
    $today = new DateTimeImmutable('today');
    if ($borrowed === null) $errors['date_borrowed'] = 'Enter a valid date.';
    elseif ($borrowed > $today) $errors['date_borrowed'] = 'The borrow date cannot be in the future.';
    elseif ($borrowed < $today->modify('-1 year')) $errors['date_borrowed'] = 'Enter a date within the past year.';
    $expected = inventory_valid_date($values['expected_return_date']);
    if ($expected === null) $errors['expected_return_date'] = 'Enter a valid date.';
    elseif ($borrowed !== null && $expected < $borrowed) $errors['expected_return_date'] = 'The expected return date cannot be before the borrow date.';
    elseif ($expected > $today->modify('+1 year')) $errors['expected_return_date'] = 'Enter a date within the next year.';
    if (mb_strlen($values['remarks']) > 1000) $errors['remarks'] = 'Remarks must not exceed 1,000 characters.';
    foreach (['borrower_contact', 'remarks'] as $nullable) if ($values[$nullable] === '') $values[$nullable] = null;
    return ['values' => $values, 'errors' => $errors];
}

function inventory_validate_return(array $input, array $borrow): array
{
    $values = [
        'actual_return_date' => trim((string) ($input['actual_return_date'] ?? '')),
        'missing_quantity' => trim((string) ($input['missing_quantity'] ?? '0')),
        'damaged_quantity' => trim((string) ($input['damaged_quantity'] ?? '0')),
        'return_condition' => (string) ($input['return_condition'] ?? ''),
        'return_remarks' => trim((string) ($input['return_remarks'] ?? '')),
    ];
    $errors = [];
    $quantity = (int) $borrow['quantity'];
    $returned = inventory_valid_date($values['actual_return_date']);
    if ($returned === null) $errors['actual_return_date'] = 'Enter a valid date.';
    elseif ($returned > new DateTimeImmutable('today')) $errors['actual_return_date'] = 'The return date cannot be in the future.';
    elseif ($returned < new DateTimeImmutable((string) $borrow['date_borrowed'])) $errors['actual_return_date'] = 'The return date cannot be before the borrow date.';
    foreach (['missing_quantity' => 'Missing', 'damaged_quantity' => 'Damaged'] as $field => $label) {
        if ($values[$field] === '') $values[$field] = '0';
        if (!preg_match('/^\d{1,7}$/', $values[$field])) $errors[$field] = "$label quantity must be a whole number.";
        else $values[$field] = (int) $values[$field];
    }
    if (!isset($errors['missing_quantity']) && $values['missing_quantity'] > $quantity) $errors['missing_quantity'] = 'Missing cannot be more than the ' . $quantity . ' borrowed.';
    if (!isset($errors['missing_quantity']) && !isset($errors['damaged_quantity']) && is_int($values['missing_quantity']) && is_int($values['damaged_quantity']) && $values['damaged_quantity'] > $quantity - $values['missing_quantity']) $errors['damaged_quantity'] = 'Damaged cannot be more than the quantity returned.';
    if (!array_key_exists($values['return_condition'], inventory_return_conditions())) $errors['return_condition'] = 'Select the condition of the returned items.';
    if (is_int($values['damaged_quantity']) && $values['damaged_quantity'] > 0 && $values['return_remarks'] === '') $errors['return_remarks'] = 'Describe the damage.';
    if (is_int($values['missing_quantity']) && $values['missing_quantity'] > 0 && $values['return_remarks'] === '') $errors['return_remarks'] = 'Explain the missing pieces.';
    if (mb_strlen($values['return_remarks']) > 1000) $errors['return_remarks'] = 'Remarks must not exceed 1,000 characters.';
    if ($values['return_remarks'] === '') $values['return_remarks'] = null;
    return ['values' => $values, 'errors' => $errors];
}

function inventory_validate_issue(array $input, int $on_hand): array
{
    $values = ['quantity' => trim((string) ($input['quantity'] ?? '')), 'recipient' => residents_collapse($input['recipient'] ?? ''), 'reason' => residents_collapse($input['reason'] ?? '')];
    $errors = [];
    if (!preg_match('/^\d{1,7}$/', $values['quantity']) || (int) $values['quantity'] < 1) $errors['quantity'] = 'Enter a quantity of at least 1.';
    elseif ((int) $values['quantity'] > $on_hand) $errors['quantity'] = 'Only ' . number_format($on_hand) . ' on hand.';
    else $values['quantity'] = (int) $values['quantity'];
    if (mb_strlen($values['recipient']) < 2 || mb_strlen($values['recipient']) > 150) $errors['recipient'] = 'Enter who received the supplies (2 to 150 characters).';
    if (mb_strlen($values['reason']) < 3 || mb_strlen($values['reason']) > 255) $errors['reason'] = 'Describe the purpose (3 to 255 characters).';
    return ['values' => $values, 'errors' => $errors];
}

function inventory_is_overdue(array $borrow): bool
{
    return $borrow['actual_return_date'] === null && (string) $borrow['expected_return_date'] < date('Y-m-d');
}

// ── Reports, exports and labels (Phase 4) ────────────────────────────────────────

// Printed-report settings from templates/inventory/report_settings.php (editable), with safe defaults.
function inventory_report_settings(): array
{
    $defaults = ['header_lines' => [], 'barangay_name' => 'BARANGAY SAN JOSE', 'address' => '', 'logo' => '', 'title' => 'INVENTORY REPORT', 'signatories' => []];
    $file = dirname(__DIR__) . '/templates/inventory/report_settings.php';
    $settings = is_file($file) ? include $file : [];
    $settings = is_array($settings) ? array_merge($defaults, array_intersect_key($settings, $defaults)) : $defaults;
    // The logo must be an image inside the project (no URLs, no parent folders).
    if (!is_string($settings['logo']) || !preg_match('#^[A-Za-z0-9_\-/]+\.(jpg|jpeg|png|webp|svg)$#i', $settings['logo']) || str_contains($settings['logo'], '..') || !is_file(dirname(__DIR__) . '/' . $settings['logo'])) $settings['logo'] = '';
    $settings['header_lines'] = array_values(array_filter((array) $settings['header_lines'], 'is_string'));
    $settings['signatories'] = array_values(array_filter((array) $settings['signatories'], 'is_array'));
    return $settings;
}

// Report filters on top of the list state: date acquired range (validated YYYY-MM-DD).
function inventory_report_dates(array $input): array
{
    $from = (string) ($input['acquired_from'] ?? '');
    $to = (string) ($input['acquired_to'] ?? '');
    $from = inventory_valid_date($from) ? $from : '';
    $to = inventory_valid_date($to) ? $to : '';
    if ($from !== '' && $to !== '' && $from > $to) [$from, $to] = [$to, $from];
    return ['acquired_from' => $from, 'acquired_to' => $to];
}

// Every item matching the list state and date range (not paged), grouped-by-category order. Capped for safety.
function inventory_report_rows(PDO $connection, array $state, array $dates, int $limit = 5000, bool $group_by_category = true): array
{
    [$where, $params] = inventory_list_where($state, $connection);
    if ($dates['acquired_from'] !== '') { $where[] = 'i.date_acquired >= :acquired_from'; $params['acquired_from'] = $dates['acquired_from']; }
    if ($dates['acquired_to'] !== '') { $where[] = 'i.date_acquired <= :acquired_to'; $params['acquired_to'] = $dates['acquired_to']; }
    $borrowed = inventory_borrowing_ready($connection) ? '(SELECT COALESCE(SUM(b.quantity), 0) FROM inventory_borrow_records b WHERE b.item_id = i.id AND b.actual_return_date IS NULL)' : '0';
    $statement = $connection->prepare("SELECT i.*, c.name AS category_name, l.name AS location_name, $borrowed AS borrowed_out FROM inventory_items i INNER JOIN inventory_categories c ON c.id = i.category_id INNER JOIN inventory_locations l ON l.id = i.location_id WHERE " . implode(' AND ', $where) . ' ORDER BY ' . ($group_by_category ? 'c.name, ' : '') . inventory_sort_sql($state['sort'], $state['dir']) . ' LIMIT :limit');
    foreach ($params as $key => $value) $statement->bindValue($key, $value);
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

// Plain-language summary of the active filters (printed on reports and exports).
function inventory_filter_summary(PDO $connection, array $state, array $dates = ['acquired_from' => '', 'acquired_to' => '']): string
{
    $parts = [];
    if ($state['type'] !== '') $parts[] = 'Type: ' . inventory_types()[$state['type']];
    if ($state['status'] === 'archived') $parts[] = 'Archived items';
    elseif ($state['status'] !== '') $parts[] = 'Status: ' . inventory_statuses()[$state['status']];
    if ($state['expiring']) $parts[] = 'Expiring within 30 days';
    foreach (['category' => 'inventory_categories', 'location' => 'inventory_locations'] as $key => $table) {
        if ($state[$key] === null) continue;
        $name = $connection->prepare("SELECT name FROM $table WHERE id = :id");
        $name->execute(['id' => $state[$key]]);
        $parts[] = ucfirst($key) . ': ' . ((string) $name->fetchColumn() ?: 'Unknown');
    }
    if ($state['condition'] !== '') $parts[] = 'Condition: ' . inventory_conditions()[$state['condition']];
    if ($state['q'] !== '') $parts[] = 'Search: "' . $state['q'] . '"';
    if ($dates['acquired_from'] !== '' || $dates['acquired_to'] !== '') $parts[] = 'Acquired ' . ($dates['acquired_from'] !== '' ? 'from ' . inventory_format_date($dates['acquired_from']) : '') . ($dates['acquired_to'] !== '' ? ' to ' . inventory_format_date($dates['acquired_to']) : '');
    return $parts === [] ? 'All active items' : implode(' · ', $parts);
}

// Absolute link to an item's details page, encoded in its QR code. Uses APP_BASE_URL when defined in
// config/local.php (e.g. 'http://192.168.1.10/SJQIBMS'), so phones on the office network can open it; otherwise the
// address this page was opened with.
function inventory_item_url(int $id): string
{
    if (defined('APP_BASE_URL') && is_string(APP_BASE_URL) && preg_match('#^https?://[A-Za-z0-9.\-:\[\]]+(/[A-Za-z0-9._\-/]*)?$#', APP_BASE_URL)) {
        $base = rtrim(APP_BASE_URL, '/');
    } else {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) $host = 'localhost';
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $path = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        $base = $scheme . '://' . $host . (preg_match('#^[A-Za-z0-9._\-/]*$#', $path) ? $path : '');
    }
    return $base . '/inventory_view.php?id=' . $id;
}

// QR library (qrcode-generator 1.4.4, MIT) from jsDelivr, pinned with Subresource Integrity.
function inventory_qr_script_tags(): string
{
    return '<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js" integrity="sha384-8FWZA6BGMXhsfO+BLtrJK0We6gg5o1JyO8xQm6peWDEUs17ACA5ziE/NIAkl9z2k" crossorigin="anonymous"></script>'
        . '<script src="assets/js/inventory_qr.js?v=' . e((string) @filemtime(dirname(__DIR__) . '/assets/js/inventory_qr.js')) . '"></script>';
}

// ── Photos (private storage/inventory/, served only by inventory_photo.php) ────

function inventory_photo_root(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'inventory';
}

function inventory_photo_types(): array
{
    return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
}

// Validates an uploaded photo (size, real image type checked on the server) and stores it under a random name.
// Returns the stored filename, or null when no file was chosen. Throws RuntimeException with a safe message.
function inventory_store_photo(array $file): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('The photo is larger than 5 MB.');
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) throw new RuntimeException('The photo could not be uploaded. Please try again.');
    $path = (string) $file['tmp_name'];
    if ((int) filesize($path) > INVENTORY_PHOTO_MAX_BYTES) throw new RuntimeException('The photo is larger than 5 MB.');
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $extension = inventory_photo_types()[$mime] ?? null;
    if ($extension === null || @getimagesize($path) === false) throw new RuntimeException('Upload a JPG, PNG or WebP image.');
    $directory = inventory_photo_root();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('The photo folder could not be prepared.');
    $name = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($path, $directory . DIRECTORY_SEPARATOR . $name)) throw new RuntimeException('The photo could not be stored. Please try again.');
    return $name;
}

// Absolute path of a stored photo, or null. Only generated names are accepted, so no path can be injected.
function inventory_photo_path(?string $filename): ?string
{
    if ($filename === null || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) return null;
    $path = inventory_photo_root() . DIRECTORY_SEPARATOR . $filename;
    return is_file($path) ? $path : null;
}

function inventory_delete_photo(?string $filename): void
{
    $path = inventory_photo_path($filename);
    if ($path !== null) @unlink($path);
}
