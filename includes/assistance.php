<?php
declare(strict_types=1);

require_once __DIR__ . '/disaster_recovery.php';   // incidents, evacuations, date / name helpers (also loads inventory.php)

// Relief & Assistance (migrations 20261008_relief_assistance and 20261009_relief_assistance_complete): every relief and
// assistance given by the barangay — disaster relief (linked to a Disaster Management incident) and regular ayuda —
// to ONE resident or ONE household. Each record is either Cash or In-kind (Inventory items).
//   * In-kind items follow the Inventory module's own issue rules (only supplies that can be issued, never more than on
//     hand, the Low Stock rule, an 'issued' movement whose id is kept on the line) in the same transaction.
//   * Cash from the Barangay Fund may be linked to an existing, released Financial Management disbursement (DV). This
//     module never creates financial transactions; the linked cash never exceeds the disbursement amount.
//   * Status: Scheduled (planned; no stock is deducted) → Given (stock deducted) or Void (kept on record with a reason;
//     items of a Given record return to stock). Records are never deleted.
//   * Priority group: PWD and Solo Parent from the resident profile (for a household, from its current members), saved as
//     a snapshot when the record is made; no group = All residents.
// Access: role_can('assistance.manage') records and edits (System Administrator, Secretary, Officials, Punong Barangay);
// everyone with the menu item can view and print.

const ASSISTANCE_MAX_LINES = 10;
const ASSISTANCE_PURPOSE_MIN = 10;

function assistance_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $tables = (int) $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('assistance_distributions', 'assistance_items', 'inventory_items', 'inventory_movements', 'households', 'drr_records', 'finance_transactions')")->fetchColumn();
        $columns = (int) $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assistance_distributions' AND COLUMN_NAME IN ('household_id', 'incident_id', 'status', 'source', 'finance_transaction_id', 'ref_seq')")->fetchColumn();
        $ready = $tables === 7 && $columns === 6 && residents_sector_ready($connection);
    }
    return $ready;
}

function assistance_can_view(): bool
{
    return can_access_navigation('assistance');
}

function assistance_can_manage(): bool
{
    return assistance_can_view() && role_can('assistance.manage');
}

function assistance_require_view(): void
{
    require_auth();
    if (!assistance_can_view()) { http_response_code(403); exit('Access denied.'); }
}

function assistance_require_manage(): void
{
    require_auth();
    if (!assistance_can_manage()) { http_response_code(403); exit('Access denied.'); }
}

// ── Lists ───────────────────────────────────────────────────────────────────────

function assistance_categories(): array
{
    return ['medical' => 'Medical Assistance', 'educational' => 'Educational Assistance', 'food' => 'Food Assistance', 'cash' => 'Cash Assistance', 'rice' => 'Rice Assistance', 'hygiene' => 'Hygiene Assistance', 'medicine' => 'Medicine Assistance', 'other' => 'Other'];
}

// Includes the labels of values kept from the first version of the module (never offered on the form).
function assistance_category_label(?string $category): string
{
    return (assistance_categories() + ['financial' => 'Financial Assistance', 'burial' => 'Burial Assistance'])[(string) $category] ?? ucfirst((string) $category);
}

function assistance_forms(): array
{
    return ['cash' => 'Cash', 'in_kind' => 'In-kind (Inventory item)'];
}

function assistance_sources(): array
{
    return ['barangay_fund' => 'Barangay Fund', 'barangay_inventory' => 'Barangay Inventory', 'donation' => 'Donation', 'municipal_government' => 'Municipal Government', 'provincial_government' => 'Provincial Government', 'national_agency' => 'National Government Agency', 'ngo' => 'NGO', 'other' => 'Other'];
}

// Sources whose details (donor, NGO or other giver) are required.
function assistance_sources_needing_details(): array
{
    return ['donation', 'ngo', 'other'];
}

function assistance_statuses(): array
{
    return ['scheduled' => 'Scheduled', 'given' => 'Given', 'void' => 'Void'];
}

function assistance_priority_groups(): array
{
    return ['pwd' => 'PWD', 'solo_parent' => 'Solo Parent'];
}

function assistance_status_badge(string $status): string
{
    $tone = ['scheduled' => 'pending', 'given' => 'active', 'void' => 'inactive'][$status] ?? 'inactive';
    return '<span class="resident-status resident-status-' . e($tone) . '">' . e(assistance_statuses()[$status] ?? ucfirst($status)) . '</span>';
}

// Groups from a SET value ('pwd,solo_parent') or a list.
function assistance_group_list(string|array $groups): array
{
    $list = is_array($groups) ? $groups : array_filter(explode(',', $groups));
    return array_values(array_filter(array_keys(assistance_priority_groups()), static fn (string $key): bool => in_array($key, $list, true)));
}

function assistance_group_badges(string|array $groups, bool $general = true): string
{
    $list = assistance_group_list($groups);
    if ($list === []) return $general ? '<span class="drr-group ast-group-general">All residents</span>' : '';
    return implode(' ', array_map(static fn (string $group): string => '<span class="drr-group drr-group-' . e($group) . '">' . e(assistance_priority_groups()[$group]) . '</span>', $list));
}

function assistance_group_text(string|array $groups): string
{
    $list = assistance_group_list($groups);
    return $list === [] ? 'All residents' : implode(', ', array_map(static fn (string $g): string => assistance_priority_groups()[$g], $list));
}

function assistance_peso(mixed $value): string
{
    return '₱' . number_format((float) $value, 2);
}

// "1,500.5" → "1500.50"; null when not a positive amount with at most two decimals (DECIMAL(12,2)).
function assistance_parse_cash(string $value): ?string
{
    $value = str_replace([',', ' ', '₱'], '', trim($value));
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value)) return null;
    [$whole, $cents] = array_pad(explode('.', $value), 2, '');
    $normalized = (ltrim($whole, '0') === '' ? '0' : ltrim($whole, '0')) . '.' . str_pad($cents, 2, '0');
    return $normalized === '0.00' ? null : $normalized;
}

function assistance_cents(mixed $value): int
{
    return (int) round(((float) $value) * 100);
}

// ── Beneficiaries ───────────────────────────────────────────────────────────────

// Active resident (the browser only sends the id; everything is read again here).
function assistance_resident(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare("SELECT id, first_name, middle_name, last_name, suffix, birth_date, purok, status, is_pwd, is_solo_parent FROM residents WHERE id = :id LIMIT 1");
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row ?: null;
}

// Household with its head's name, current active members and their priority groups.
function assistance_household(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare("SELECT h.id, h.household_no, h.purok, h.address, hr.first_name, hr.middle_name, hr.last_name, hr.suffix, (SELECT COUNT(*) FROM resident_households rh INNER JOIN residents m ON m.id = rh.resident_id WHERE rh.household_id = h.id AND rh.left_at IS NULL AND m.status = 'active') AS members, (SELECT COALESCE(MAX(m.is_pwd), 0) FROM resident_households rh INNER JOIN residents m ON m.id = rh.resident_id WHERE rh.household_id = h.id AND rh.left_at IS NULL AND m.status = 'active') AS has_pwd, (SELECT COALESCE(MAX(m.is_solo_parent), 0) FROM resident_households rh INNER JOIN residents m ON m.id = rh.resident_id WHERE rh.household_id = h.id AND rh.left_at IS NULL AND m.status = 'active') AS has_solo_parent FROM households h LEFT JOIN residents hr ON hr.id = h.household_head_resident_id WHERE h.id = :id LIMIT 1");
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row ?: null;
}

// Full names of the household's current active members (for "Received by").
function assistance_household_members(PDO $connection, int $household_id): array
{
    $statement = $connection->prepare("SELECT m.first_name, m.middle_name, m.last_name, m.suffix FROM resident_households rh INNER JOIN residents m ON m.id = rh.resident_id WHERE rh.household_id = :id AND rh.left_at IS NULL AND m.status = 'active' ORDER BY m.last_name, m.first_name, m.id");
    $statement->execute(['id' => $household_id]);
    return array_map('residents_full_name', $statement->fetchAll());
}

// "Received by" rule: the beneficiary (for a household, one of its current members) — or a representative, whose
// relationship to the beneficiary must then be given. Returns an error or null.
function assistance_receiver_error(PDO $connection, string $type, int $beneficiary_id, string $beneficiary_name, string $received, string $relationship): ?string
{
    if ($received === '' || $relationship !== '') return null;
    $names = $type === 'household' ? assistance_household_members($connection, $beneficiary_id) : [$beneficiary_name];
    foreach ($names as $name) if (mb_strtolower(residents_collapse($name)) === mb_strtolower($received)) return null;
    return $type === 'household' ? 'The receiver is not a current member of this household. Enter their relationship to the household (representative).' : 'The receiver is not the beneficiary. Enter their relationship to the beneficiary (representative).';
}

function assistance_household_label(array $household): string
{
    $head = trim(residents_full_name($household));
    return 'Household ' . $household['household_no'] . ($head !== '' ? ' (' . $head . ')' : '');
}

// Priority groups of a resident or household row.
function assistance_beneficiary_groups(string $type, array $row): array
{
    return $type === 'household'
        ? array_values(array_filter([(int) $row['has_pwd'] === 1 ? 'pwd' : null, (int) $row['has_solo_parent'] === 1 ? 'solo_parent' : null]))
        : array_values(array_filter([(int) $row['is_pwd'] === 1 ? 'pwd' : null, (int) $row['is_solo_parent'] === 1 ? 'solo_parent' : null]));
}

// Name for lists and the printout from a joined assistance row (see assistance_select()).
function assistance_beneficiary_label(array $row): string
{
    if ($row['household_id'] !== null) {
        $head = trim(residents_full_name(['first_name' => $row['head_first_name'], 'middle_name' => $row['head_middle_name'], 'last_name' => $row['head_last_name'], 'suffix' => $row['head_suffix']]));
        return 'Household ' . $row['household_no'] . ($head !== '' ? ' (' . $head . ')' : '');
    }
    return residents_full_name($row);
}

function assistance_beneficiary_purok(array $row): string
{
    return residents_purok_label((string) ($row['household_id'] !== null ? $row['h_purok'] : $row['r_purok']));
}

// ── Reading records ─────────────────────────────────────────────────────────────

function assistance_select(): string
{
    return "SELECT a.*, r.first_name, r.middle_name, r.last_name, r.suffix, r.purok AS r_purok, h.household_no, h.purok AS h_purok, hr.first_name AS head_first_name, hr.middle_name AS head_middle_name, hr.last_name AS head_last_name, hr.suffix AS head_suffix, d.reference_no AS incident_reference, d.title AS incident_title, f.reference_no AS finance_reference, f.amount AS finance_amount, f.release_date AS finance_release_date, cu.name AS created_by_name, uu.name AS updated_by_name, au.name AS archived_by_name FROM assistance_distributions a LEFT JOIN residents r ON r.id = a.resident_id LEFT JOIN households h ON h.id = a.household_id LEFT JOIN residents hr ON hr.id = h.household_head_resident_id LEFT JOIN drr_records d ON d.id = a.incident_id LEFT JOIN finance_transactions f ON f.id = a.finance_transaction_id LEFT JOIN users cu ON cu.id = a.created_by LEFT JOIN users uu ON uu.id = a.updated_by LEFT JOIN users au ON au.id = a.archived_by";
}

function assistance_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $statement = $connection->prepare(assistance_select() . ' WHERE a.id = :id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function assistance_items(PDO $connection, int $distribution_id): array
{
    $statement = $connection->prepare('SELECT x.id, x.item_id, x.quantity, x.unit, x.inventory_movement_id, i.item_code, i.name FROM assistance_items x INNER JOIN inventory_items i ON i.id = x.item_id WHERE x.distribution_id = :id ORDER BY i.name');
    $statement->execute(['id' => $distribution_id]);
    return $statement->fetchAll();
}

// Form of a record (records from the first version may have no form: cash when an amount is recorded).
function assistance_form_of(array $row): string
{
    return $row['assistance_form'] ?? ($row['cash_amount'] !== null ? 'cash' : 'in_kind');
}

// "₱1,000.00" or "2 packs Food pack, 5 kg Rice".
function assistance_given_text(array $row, array $lines): string
{
    $parts = $row['cash_amount'] !== null ? [assistance_peso($row['cash_amount'])] : [];
    foreach ($lines as $line) $parts[] = number_format((int) $line['quantity']) . ' ' . $line['unit'] . ' ' . $line['name'];
    return implode(', ', $parts);
}

// Other records (not void) of the same resident or household, newest first.
function assistance_history(PDO $connection, array $entry, int $limit = 10): array
{
    $column = $entry['household_id'] !== null ? 'household_id' : 'resident_id';
    $statement = $connection->prepare("SELECT a.id, a.reference_no, a.assistance_type, a.purpose, a.cash_amount, a.given_on, a.status FROM assistance_distributions a WHERE a.$column = :beneficiary AND a.id <> :id AND a.status <> 'void' ORDER BY a.given_on DESC, a.id DESC LIMIT " . (int) $limit);
    $statement->execute(['beneficiary' => $entry[$column], 'id' => (int) $entry['id']]);
    return $statement->fetchAll();
}

// ── Inventory and Finance ───────────────────────────────────────────────────────

// Supplies that can be given out now (same rule as Inventory's Issue Supplies).
function assistance_supplies(PDO $connection): array
{
    $rows = $connection->query("SELECT i.id, i.item_code, i.name, i.item_type, i.quantity, i.unit, i.status, i.archived_at FROM inventory_items i WHERE i.item_type = 'supply' AND i.archived_at IS NULL AND i.quantity > 0 ORDER BY i.name")->fetchAll();
    return array_values(array_filter($rows, 'inventory_can_issue'));
}

// Items that may be planned on a Scheduled record: every supply that is not archived (stock is checked when given).
function assistance_plannable_items(PDO $connection): array
{
    return $connection->query("SELECT i.id, i.item_code, i.name, i.quantity, i.unit, i.status FROM inventory_items i WHERE i.item_type = 'supply' AND i.archived_at IS NULL ORDER BY i.name")->fetchAll();
}

// Released disbursements (DV) with the cash not yet linked to other assistance. Only the reference, release date, amount
// and remaining amount are shown to assistance staff (the rest of the financial record stays in Financial Management).
function assistance_finance_options(PDO $connection, ?int $exclude_assistance_id = null, ?int $include_id = null): array
{
    $statement = $connection->prepare("SELECT f.id, f.reference_no, f.release_date, f.amount, COALESCE((SELECT SUM(a.cash_amount) FROM assistance_distributions a WHERE a.finance_transaction_id = f.id AND a.status <> 'void' AND a.id <> :exclude), 0) AS linked FROM finance_transactions f WHERE (f.type = 'expense' AND f.ref_kind = 'dv' AND f.status = 'released') OR f.id = :include ORDER BY f.release_date DESC, f.id DESC LIMIT 200");
    $statement->execute(['exclude' => $exclude_assistance_id ?? 0, 'include' => $include_id ?? 0]);
    $rows = $statement->fetchAll();
    foreach ($rows as &$row) $row['remaining'] = number_format(max(0, assistance_cents($row['amount']) - assistance_cents($row['linked'])) / 100, 2, '.', '');
    unset($row);
    return $rows;
}

// Checks a disbursement link: released DV and enough unlinked amount. Returns an error or null.
function assistance_finance_error(PDO $connection, int $finance_id, string $cash, ?int $exclude_assistance_id, bool $lock = false): ?string
{
    $statement = $connection->prepare("SELECT id, reference_no, type, ref_kind, status, amount FROM finance_transactions WHERE id = :id LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $finance_id]);
    $finance = $statement->fetch();
    if (!$finance || $finance['type'] !== 'expense' || $finance['ref_kind'] !== 'dv' || $finance['status'] !== 'released') return 'Select a released disbursement (DV) from Financial Management.';
    $linked = $connection->prepare("SELECT COALESCE(SUM(cash_amount), 0) FROM assistance_distributions WHERE finance_transaction_id = :id AND status <> 'void' AND id <> :exclude");
    $linked->execute(['id' => $finance_id, 'exclude' => $exclude_assistance_id ?? 0]);
    $remaining = assistance_cents($finance['amount']) - assistance_cents($linked->fetchColumn());
    if (assistance_cents($cash) > $remaining) return 'Only ' . assistance_peso($remaining / 100) . ' of ' . $finance['reference_no'] . ' is not yet linked to other assistance.';
    return null;
}

// Deducts stock for each line of a record and keeps the Inventory movement id on the line. Throws DomainException
// ('items|message') when an item can no longer be given.
function assistance_issue_items(PDO $connection, int $distribution_id, array $wanted, string $reference, string $beneficiary, string $purpose, bool $lines_exist): array
{
    ksort($wanted);   // lock items in a fixed order so two records at once cannot deadlock
    $locked = [];
    foreach ($wanted as $item_id => $quantity) {
        $item = inventory_find($connection, (int) $item_id, true);
        if ($item === null || !inventory_can_issue($item)) throw new DomainException('items|' . ($item ? $item['name'] : 'An item') . ' can no longer be given out (only supplies with stock on hand).');
        if ($quantity > (int) $item['quantity']) throw new DomainException('items|Only ' . number_format((int) $item['quantity']) . ' ' . $item['unit'] . ' of ' . $item['name'] . ' are on hand.');
        $locked[$item_id] = $item;
    }
    $user = current_user()['id'];
    $given = [];
    foreach ($wanted as $item_id => $quantity) {
        $item = $locked[$item_id];
        $on_hand = (int) $item['quantity'] - $quantity;
        $status = inventory_status_after($item, $on_hand, 0);
        $connection->prepare('UPDATE inventory_items SET quantity = :quantity, status = :status, updated_by = :user WHERE id = :id')->execute(['quantity' => $on_hand, 'status' => $status, 'user' => $user, 'id' => $item_id]);
        inventory_log_movement($connection, (int) $item_id, 'issued', -$quantity, $on_hand, mb_substr('Assistance ' . $reference . ' (' . $purpose . ')', 0, 255), $status !== $item['status'] ? (string) $item['status'] : null, $status !== $item['status'] ? $status : null, mb_substr($beneficiary, 0, 150));
        $movement_id = (int) $connection->lastInsertId();
        if ($lines_exist) {
            $connection->prepare('UPDATE assistance_items SET inventory_movement_id = :movement, unit = :unit WHERE distribution_id = :distribution AND item_id = :item')->execute(['movement' => $movement_id, 'unit' => $item['unit'], 'distribution' => $distribution_id, 'item' => $item_id]);
        } else {
            $connection->prepare('INSERT INTO assistance_items (distribution_id, item_id, quantity, unit, inventory_movement_id) VALUES (:distribution, :item, :quantity, :unit, :movement)')->execute(['distribution' => $distribution_id, 'item' => $item_id, 'quantity' => $quantity, 'unit' => $item['unit'], 'movement' => $movement_id]);
        }
        inventory_audit($connection, (int) $item_id, 'inventory_issued', ['item_code' => $item['item_code'], 'quantity' => $quantity, 'recipient' => $beneficiary, 'reference' => $reference]);
        $given[] = $quantity . ' ' . $item['unit'] . ' ' . $item['name'];
    }
    return $given;
}

// Returns to stock every line of a record whose stock was deducted ('adjusted' movement).
function assistance_return_items(PDO $connection, array $entry, string $reason): int
{
    $lines = array_filter(assistance_items($connection, (int) $entry['id']), static fn (array $line): bool => $line['inventory_movement_id'] !== null);
    usort($lines, static fn (array $a, array $b): int => (int) $a['item_id'] <=> (int) $b['item_id']);
    $user = current_user()['id'];
    foreach ($lines as $line) {
        $item = inventory_find($connection, (int) $line['item_id'], true);
        if ($item === null) continue;
        $on_hand = (int) $item['quantity'] + (int) $line['quantity'];
        $status = inventory_status_after($item, $on_hand, 0);
        $connection->prepare('UPDATE inventory_items SET quantity = :quantity, status = :status, updated_by = :user WHERE id = :id')->execute(['quantity' => $on_hand, 'status' => $status, 'user' => $user, 'id' => $item['id']]);
        inventory_log_movement($connection, (int) $item['id'], 'adjusted', (int) $line['quantity'], $on_hand, mb_substr('Assistance ' . $entry['reference_no'] . ' voided: ' . $reason, 0, 255), $status !== $item['status'] ? (string) $item['status'] : null, $status !== $item['status'] ? $status : null);
    }
    return count($lines);
}

// ── Saving ──────────────────────────────────────────────────────────────────────

// One-time form token (in the session) so a double click or a resubmitted page cannot record the same assistance twice.
function assistance_form_token(): string
{
    $tokens = array_slice((array) ($_SESSION['assistance_form_tokens'] ?? []), -20, 20, true);
    $token = bin2hex(random_bytes(16));
    $tokens[$token] = time();
    $_SESSION['assistance_form_tokens'] = $tokens;
    return $token;
}

function assistance_form_token_valid(string $token): bool
{
    return $token !== '' && isset($_SESSION['assistance_form_tokens'][$token]);
}

function assistance_form_token_used(string $token): void
{
    unset($_SESSION['assistance_form_tokens'][$token]);
}

function assistance_lines(array $input): array
{
    $ids = (array) ($input['item_id'] ?? []);
    $quantities = (array) ($input['quantity'] ?? []);
    $lines = [];
    foreach (array_slice($ids, 0, ASSISTANCE_MAX_LINES, true) as $index => $raw_id) {
        $raw_id = trim((string) $raw_id);
        $raw_quantity = trim((string) ($quantities[$index] ?? ''));
        if ($raw_id === '' && $raw_quantity === '') continue;
        $lines[] = ['item_id' => $raw_id, 'quantity' => $raw_quantity];
    }
    return $lines;
}

// Values of the form (strings, as typed).
function assistance_input(array $input): array
{
    return [
        'record_as' => (string) ($input['record_as'] ?? 'given') === 'scheduled' ? 'scheduled' : 'given',
        // The Beneficiary buttons: All residents (all), PWD, Solo Parent — everyone in the group — or One resident (resident)
        // or Household. The groups are residents.
        'beneficiary_choice' => in_array((string) ($input['beneficiary_type'] ?? ''), ['all', 'resident', 'pwd', 'solo_parent', 'household'], true) ? (string) $input['beneficiary_type'] : 'resident',
        'beneficiary_type' => (string) ($input['beneficiary_type'] ?? 'resident') === 'household' ? 'household' : 'resident',
        'resident_id' => trim((string) ($input['resident_id'] ?? '')),
        'household_id' => trim((string) ($input['household_id'] ?? '')),
        'group_purok' => residents_collapse((string) ($input['group_purok'] ?? 'all')),   // PWD / Solo Parent: 'all' or one Purok
        'incident_id' => trim((string) ($input['incident_id'] ?? '')),
        'assistance_type' => (string) ($input['assistance_type'] ?? ''),
        'assistance_form' => (string) ($input['assistance_form'] ?? ''),
        'cash_amount' => trim((string) ($input['cash_amount'] ?? '')),
        'source' => (string) ($input['source'] ?? ''),
        'source_details' => residents_collapse($input['source_details'] ?? ''),
        'finance_transaction_id' => trim((string) ($input['finance_transaction_id'] ?? '')),
        'purpose' => trim(preg_replace('/[ \t]+/', ' ', (string) ($input['purpose'] ?? '')) ?? ''),
        'given_on' => trim((string) ($input['given_on'] ?? '')),
        'received_by' => residents_collapse($input['received_by'] ?? ''),
        'receiver_relationship' => residents_collapse($input['receiver_relationship'] ?? ''),
        'document_no' => residents_collapse($input['document_no'] ?? ''),
        'remarks' => residents_collapse($input['remarks'] ?? ''),
        'lines' => assistance_lines($input),
        'confirm_duplicate' => (string) ($input['confirm_duplicate'] ?? '') === '1',
    ];
}

// Values of a stored record, for the edit form.
function assistance_values_from(array $entry, array $lines): array
{
    return [
        'record_as' => $entry['status'] === 'scheduled' ? 'scheduled' : 'given',
        'beneficiary_choice' => $entry['household_id'] !== null ? 'household' : 'resident',
        'beneficiary_type' => $entry['household_id'] !== null ? 'household' : 'resident',
        'resident_id' => (string) ($entry['resident_id'] ?? ''), 'household_id' => (string) ($entry['household_id'] ?? ''), 'group_purok' => 'all',
        'incident_id' => (string) ($entry['incident_id'] ?? ''), 'assistance_type' => (string) $entry['assistance_type'], 'assistance_form' => assistance_form_of($entry),
        'cash_amount' => (string) ($entry['cash_amount'] ?? ''), 'source' => (string) ($entry['source'] ?? ''), 'source_details' => (string) ($entry['source_details'] ?? ''),
        'finance_transaction_id' => (string) ($entry['finance_transaction_id'] ?? ''), 'purpose' => (string) $entry['purpose'], 'given_on' => (string) $entry['given_on'],
        'received_by' => (string) $entry['received_by'], 'receiver_relationship' => (string) ($entry['receiver_relationship'] ?? ''), 'document_no' => (string) ($entry['document_no'] ?? ''), 'remarks' => (string) ($entry['remarks'] ?? ''),
        'lines' => array_map(static fn (array $line): array => ['item_id' => (string) $line['item_id'], 'quantity' => (string) $line['quantity']], $lines),
        'confirm_duplicate' => false,
    ];
}

// Fields that can still be changed on a Given record (item, quantity, amount, beneficiary and form need Void + new record).
function assistance_given_editable(): array
{
    return ['incident_id', 'purpose', 'received_by', 'receiver_relationship', 'document_no', 'remarks', 'source_details', 'finance_transaction_id'];
}

// Validates the form. $existing is the record being edited (null when creating). $group = true for PWD / Solo Parent,
// where every recorded PWD / Solo Parent receives it (the beneficiary and receiver come from the list, not the form).
// Returns ['values', 'errors', 'resolved' => [beneficiary, groups, cash, wanted, finance_id, incident_id, name]].
function assistance_validate(PDO $connection, array $input, ?array $existing = null, bool $group = false): array
{
    $values = assistance_input($input);
    $limited = $existing !== null && $existing['status'] === 'given';
    if ($limited) {
        // Only the editable fields come from the form; everything else stays as recorded.
        $stored = assistance_values_from($existing, assistance_items($connection, (int) $existing['id']));
        foreach ($stored as $key => $value) if (!in_array($key, assistance_given_editable(), true)) $values[$key] = $value;
    }
    if ($existing !== null && $existing['status'] === 'scheduled') $values['record_as'] = 'scheduled';   // Given only through Mark as Given
    $errors = [];
    $resolved = ['beneficiary' => null, 'groups' => [], 'cash' => null, 'wanted' => [], 'finance_id' => null, 'incident_id' => null, 'name' => '', 'default_receiver' => ''];

    // Beneficiary (for PWD / Solo Parent the recipients are read in assistance_create_group())
    if (!$group && $values['beneficiary_type'] === 'household') {
        $household_id = filter_var($values['household_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $household = $household_id ? assistance_household($connection, $household_id) : null;
        if ($household === null) $errors['beneficiary'] = 'Select a household from the household records.';
        elseif (!$limited && (int) $household['members'] === 0) $errors['beneficiary'] = 'This household has no current active members.';
        else { $resolved['beneficiary'] = $household; $resolved['groups'] = assistance_beneficiary_groups('household', $household); $resolved['name'] = assistance_household_label($household); $resolved['default_receiver'] = trim(residents_full_name($household)); }
    } elseif (!$group) {
        $resident_id = filter_var($values['resident_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $resident = $resident_id ? assistance_resident($connection, $resident_id) : null;
        if ($resident === null) $errors['beneficiary'] = 'Select a resident from the resident records.';
        elseif (!$limited && $resident['status'] !== 'active') $errors['beneficiary'] = 'Only active residents can receive assistance.';
        elseif (!$limited && $values['beneficiary_choice'] === 'pwd' && (int) $resident['is_pwd'] !== 1) $errors['beneficiary'] = 'Select a resident recorded as PWD, or choose All residents.';
        elseif (!$limited && $values['beneficiary_choice'] === 'solo_parent' && (int) $resident['is_solo_parent'] !== 1) $errors['beneficiary'] = 'Select a resident recorded as a Solo Parent, or choose All residents.';
        else { $resolved['beneficiary'] = $resident; $resolved['groups'] = assistance_beneficiary_groups('resident', $resident); $resolved['name'] = residents_full_name($resident); $resolved['default_receiver'] = $resolved['name']; }
    }
    if ($limited) $resolved['groups'] = assistance_group_list((string) $existing['priority_groups']);

    // Incident (optional)
    if ($values['incident_id'] !== '') {
        $incident_id = filter_var($values['incident_id'], FILTER_VALIDATE_INT);
        $allowed = array_map(static fn (array $i): int => (int) $i['id'], disaster_recovery_incidents($connection, $existing['incident_id'] ?? null));
        if (!$incident_id || !in_array($incident_id, $allowed, true)) $errors['incident_id'] = 'Select an incident from Disaster Management, or leave it blank.';
        else $resolved['incident_id'] = $incident_id;
    }

    if (!$limited) {
        if (!array_key_exists($values['assistance_type'], assistance_categories())) $errors['assistance_type'] = 'Select the assistance category.';
        if (!array_key_exists($values['assistance_form'], assistance_forms())) $errors['assistance_form'] = 'Select the form of assistance.';
        if (!array_key_exists($values['source'], assistance_sources())) $errors['source'] = 'Select the source.';
    }
    // Who gave it must be known for donations, NGOs and other sources (name of the donor, organization or program).
    if (in_array($values['source'], assistance_sources_needing_details(), true) && (mb_strlen($values['source_details']) < 2 || mb_strlen($values['source_details']) > 150)) $errors['source_details'] = $values['source'] === 'donation' ? 'Enter the name of the donor (2 to 150 characters).' : ($values['source'] === 'ngo' ? 'Enter the name of the NGO (2 to 150 characters).' : 'Describe the source (2 to 150 characters).');
    elseif (mb_strlen($values['source_details']) > 150) $errors['source_details'] = 'Keep the source details to 150 characters.';
    // The purpose is printed on the acknowledgment and identifies the payout list, so it must be descriptive.
    if (mb_strlen($values['purpose']) < ASSISTANCE_PURPOSE_MIN || mb_strlen($values['purpose']) > 255) $errors['purpose'] = 'Describe the purpose or reason in ' . ASSISTANCE_PURPOSE_MIN . ' to 255 characters, e.g. Ayuda para sa PWD – October 2026.';

    // Cash or items
    if ($values['assistance_form'] === 'cash') {
        $resolved['cash'] = assistance_parse_cash($values['cash_amount']);
        if (!$limited && $resolved['cash'] === null) $errors['cash_amount'] = 'Enter an amount greater than zero, with up to two decimals (e.g. 1500.00).';
        if ($values['finance_transaction_id'] !== '') {
            $finance_id = filter_var($values['finance_transaction_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($values['source'] !== 'barangay_fund') $errors['finance_transaction_id'] = 'Only cash from the Barangay Fund is linked to a disbursement.';
            elseif (!$finance_id) $errors['finance_transaction_id'] = 'Select a released disbursement (DV) from Financial Management.';
            elseif ($resolved['cash'] !== null && ($error = assistance_finance_error($connection, $finance_id, $resolved['cash'], $existing !== null ? (int) $existing['id'] : null)) !== null) $errors['finance_transaction_id'] = $error;
            else $resolved['finance_id'] = $finance_id;
        }
    } elseif ($values['assistance_form'] === 'in_kind') {
        if ($values['finance_transaction_id'] !== '') $errors['finance_transaction_id'] = 'Only cash assistance is linked to a disbursement.';
        if (!$limited) {
            foreach ($values['lines'] as $line) {
                $item_id = filter_var($line['item_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $quantity = filter_var($line['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
                if (!$item_id || $quantity === false) { $errors['items'] = 'Each line needs an item and a whole-number quantity of at least 1.'; continue; }
                $resolved['wanted'][$item_id] = ($resolved['wanted'][$item_id] ?? 0) + $quantity;
            }
            if ($resolved['wanted'] === [] && !isset($errors['items'])) $errors['items'] = 'Add at least one item.';
            if (!isset($errors['items'])) {
                $known = array_column(assistance_plannable_items($connection), null, 'id');
                foreach ($resolved['wanted'] as $item_id => $quantity) {
                    if (!isset($known[$item_id])) { $errors['items'] = 'Select items from the Inventory list.'; break; }
                    if ($values['record_as'] === 'given' && $quantity > (int) $known[$item_id]['quantity']) { $errors['items'] = 'Only ' . number_format((int) $known[$item_id]['quantity']) . ' ' . $known[$item_id]['unit'] . ' of ' . $known[$item_id]['name'] . ' are on hand.'; break; }
                }
            }
        }
    }

    // Dates and receiver
    $date = disaster_valid_date($values['given_on']);
    $today = new DateTimeImmutable('today');
    if ($date === null) $errors['given_on'] = 'Enter a valid date.';
    elseif ($values['record_as'] === 'given' && $date > $today) $errors['given_on'] = 'The date given cannot be in the future. Record it as Scheduled instead.';
    elseif ($values['record_as'] === 'scheduled' && $date > $today->modify('+1 year')) $errors['given_on'] = 'Schedule it within the next year.';
    elseif ($date < $today->modify('-1 year') && ($existing === null || $values['given_on'] !== (string) $existing['given_on'])) $errors['given_on'] = 'Enter a date within the past year.';
    if (!$group && ($values['record_as'] === 'given' || $values['received_by'] !== '')) {
        if ($error = disaster_person_name_error($values['received_by'], $values['record_as'] === 'given', 'name of the person who received the assistance')) $errors['received_by'] = $error;
    }
    if (!$group && !isset($errors['received_by']) && $resolved['beneficiary'] !== null && ($error = assistance_receiver_error($connection, $values['beneficiary_type'], (int) $resolved['beneficiary']['id'], $resolved['name'], $values['received_by'], $values['receiver_relationship'])) !== null) $errors['receiver_relationship'] = $error;
    if (!$group && $values['receiver_relationship'] !== '' && (mb_strlen($values['receiver_relationship']) > 60 || !preg_match("/^\p{L}[\p{L}\p{M}\s.,'\-\/]*$/u", $values['receiver_relationship']))) $errors['receiver_relationship'] = 'Enter the relationship in words (up to 60 characters), e.g. Daughter.';
    if ($values['document_no'] !== '' && (mb_strlen($values['document_no']) > 60 || !preg_match('/^[\p{L}0-9][\p{L}0-9 .\/#\-]*$/u', $values['document_no']))) $errors['document_no'] = 'Enter a reference of up to 60 letters, numbers, spaces, dots, slashes, # or dashes.';
    if (mb_strlen($values['remarks']) > 500) $errors['remarks'] = 'Keep the remarks to 500 characters.';

    // Same beneficiary again (not void): same incident, or same purpose when no incident.
    if ($existing === null && $resolved['beneficiary'] !== null && !isset($errors['purpose'])) {
        $column = $values['beneficiary_type'] === 'household' ? 'household_id' : 'resident_id';
        $match = $resolved['incident_id'] !== null ? 'incident_id = :match' : 'incident_id IS NULL AND purpose = :match';
        $statement = $connection->prepare("SELECT reference_no, given_on FROM assistance_distributions WHERE $column = :beneficiary AND $match AND status <> 'void' ORDER BY given_on, id");
        $statement->execute(['beneficiary' => $resolved['beneficiary']['id'], 'match' => $resolved['incident_id'] ?? $values['purpose']]);
        $previous = $statement->fetchAll();
        if ($previous !== [] && !$values['confirm_duplicate']) $errors['confirm_duplicate'] = $resolved['name'] . ' already has assistance ' . ($resolved['incident_id'] !== null ? 'for this incident' : 'for the same purpose') . ' (' . implode(', ', array_map(static fn (array $p): string => $p['reference_no'] . ' on ' . disaster_format_date($p['given_on']), $previous)) . '). Tick the box to record it again anyway.';
    }
    return ['values' => $values, 'errors' => $errors, 'resolved' => $resolved];
}

function assistance_next_reference(PDO $connection): array
{
    $year = (int) date('Y');
    $statement = $connection->prepare('SELECT COALESCE(MAX(ref_seq), 0) FROM assistance_distributions WHERE ref_year = :year FOR UPDATE');
    $statement->execute(['year' => $year]);
    $seq = (int) $statement->fetchColumn() + 1;
    return ['reference_no' => sprintf('AST-%d-%04d', $year, $seq), 'ref_year' => $year, 'ref_seq' => $seq];
}

// Inserts ONE record inside the caller's transaction (Given: stock deducted now; Scheduled: items planned only).
// $beneficiary = ['type' => resident|household, 'id', 'name', 'groups' => [...]]. Returns ['id', 'reference', 'given'].
function assistance_insert_record(PDO $connection, array $values, array $resolved, array $beneficiary, string $received, ?string $relationship): array
{
    $reference = assistance_next_reference($connection);
    $user = current_user()['id'];
    $household = $beneficiary['type'] === 'household';
    $connection->prepare('INSERT INTO assistance_distributions (reference_no, ref_year, ref_seq, beneficiary_type, resident_id, household_id, priority_groups, incident_id, assistance_type, assistance_form, source, source_details, purpose, cash_amount, finance_transaction_id, status, received_by, receiver_relationship, document_no, given_on, remarks, created_by, updated_by) VALUES (:reference, :year, :seq, :type, :resident, :household, :groups, :incident, :category, :form, :source, :source_details, :purpose, :cash, :finance, :status, :received, :relationship, :document, :date, :remarks, :user, :user2)')
        ->execute(['reference' => $reference['reference_no'], 'year' => $reference['ref_year'], 'seq' => $reference['ref_seq'], 'type' => $beneficiary['type'], 'resident' => $household ? null : $beneficiary['id'], 'household' => $household ? $beneficiary['id'] : null, 'groups' => implode(',', $beneficiary['groups']), 'incident' => $resolved['incident_id'], 'category' => $values['assistance_type'], 'form' => $values['assistance_form'], 'source' => $values['source'], 'source_details' => $values['source_details'] === '' ? null : $values['source_details'], 'purpose' => $values['purpose'], 'cash' => $values['assistance_form'] === 'cash' ? $resolved['cash'] : null, 'finance' => $resolved['finance_id'], 'status' => $values['record_as'], 'received' => $received, 'relationship' => $relationship, 'document' => $values['document_no'] === '' ? null : $values['document_no'], 'date' => $values['given_on'], 'remarks' => $values['remarks'] === '' ? null : $values['remarks'], 'user' => $user, 'user2' => $user]);
    $id = (int) $connection->lastInsertId();
    $given = $values['assistance_form'] === 'cash' ? [assistance_peso($resolved['cash']) . ' cash'] : [];
    if ($values['assistance_form'] === 'in_kind') {
        if ($values['record_as'] === 'given') {
            $given = assistance_issue_items($connection, $id, $resolved['wanted'], $reference['reference_no'], $beneficiary['name'], $values['purpose'], false);
        } else {
            $known = array_column(assistance_plannable_items($connection), null, 'id');
            $insert = $connection->prepare('INSERT INTO assistance_items (distribution_id, item_id, quantity, unit) VALUES (:distribution, :item, :quantity, :unit)');
            foreach ($resolved['wanted'] as $item_id => $quantity) { $insert->execute(['distribution' => $id, 'item' => $item_id, 'quantity' => $quantity, 'unit' => $known[$item_id]['unit']]); $given[] = $quantity . ' ' . $known[$item_id]['unit'] . ' ' . $known[$item_id]['name']; }
        }
    }
    residents_audit($connection, 'assistance', $id, $values['record_as'] === 'given' ? 'assistance_given' : 'assistance_scheduled', ['reference' => $reference['reference_no'], 'name' => $beneficiary['name'], 'category' => assistance_category_label($values['assistance_type']), 'form' => $values['assistance_form'], 'given' => implode(', ', $given), 'source' => assistance_sources()[$values['source']], 'incident_id' => $resolved['incident_id'], 'group' => $beneficiary['group_label'] ?? null, 'repeat' => $values['confirm_duplicate'] ? 'yes' : 'no']);
    if ($resolved['finance_id'] !== null) residents_audit($connection, 'assistance', $id, 'assistance_finance_linked', ['reference' => $reference['reference_no'], 'finance_reference' => assistance_finance_reference($connection, $resolved['finance_id'])]);
    return ['id' => $id, 'reference' => $reference['reference_no'], 'given' => $given];
}

// Runs $work in a transaction; retries when two users took the same AST number at the same moment (duplicate key).
function assistance_transaction(PDO $connection, callable $work, array $values): array
{
    for ($attempt = 1; ; $attempt++) {
        $connection->beginTransaction();
        try {
            $result = $work();
            $connection->commit();
            return $result;
        } catch (DomainException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            [$field, $message] = explode('|', $exception->getMessage(), 2);
            return ['errors' => [$field => $message], 'values' => $values];
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            if ($attempt < 3 && ($exception->errorInfo[1] ?? 0) === 1062) continue;
            throw $exception;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) $connection->rollBack();
            throw $exception;
        }
    }
}

// Creates a record for one resident or household, or — when PWD / Solo Parent is chosen — one record for EVERY active
// resident recorded as PWD / Solo Parent (in the chosen Purok or all Puroks). Returns ['id', 'message'],
// ['redirect', 'message'] (group) or ['errors', 'values'].
function assistance_create(PDO $connection, array $input): array
{
    $choice = assistance_input($input)['beneficiary_choice'];
    if (in_array($choice, ['all', 'pwd', 'solo_parent'], true)) return assistance_create_group($connection, $input, $choice);
    $checked = assistance_validate($connection, $input);
    if ($checked['errors'] !== []) return ['errors' => $checked['errors'], 'values' => $checked['values']];
    $values = $checked['values'];
    $resolved = $checked['resolved'];
    return assistance_transaction($connection, static function () use ($connection, $values, $resolved): array {
        if ($resolved['finance_id'] !== null && ($error = assistance_finance_error($connection, $resolved['finance_id'], (string) $resolved['cash'], null, true)) !== null) throw new DomainException('finance_transaction_id|' . $error);
        $beneficiary = ['type' => $values['beneficiary_type'], 'id' => (int) $resolved['beneficiary']['id'], 'name' => $resolved['name'], 'groups' => $resolved['groups']];
        $record = assistance_insert_record($connection, $values, $resolved, $beneficiary, $values['received_by'], $values['receiver_relationship'] === '' ? null : $values['receiver_relationship']);
        return ['id' => $record['id'], 'message' => $record['reference'] . ': ' . implode(', ', $record['given']) . ($values['record_as'] === 'given' ? ' given to ' : ' scheduled for ') . $resolved['name'] . '.' . ($values['assistance_form'] === 'in_kind' && $values['record_as'] === 'given' ? ' Inventory stock was updated.' : '')];
    }, $values);
}

// Active residents in a group (all = every active resident; PWD / Solo Parent from the profile), in one Purok or all, A–Z.
function assistance_group_recipients(PDO $connection, string $group, string $purok): array
{
    $flag = ['pwd' => ' AND is_pwd = 1', 'solo_parent' => ' AND is_solo_parent = 1'][$group] ?? '';
    $statement = $connection->prepare("SELECT id, first_name, middle_name, last_name, suffix, purok, is_pwd, is_solo_parent FROM residents WHERE status = 'active'$flag" . ($purok === 'all' ? '' : ' AND purok = :purok') . ' ORDER BY last_name, first_name, id');
    $statement->execute($purok === 'all' ? [] : ['purok' => $purok]);
    return $statement->fetchAll();
}

// All residents / PWD / Solo Parent: the same assistance for everyone in the group — one SCHEDULED record each (a payout
// list). Nothing is deducted and nobody is recorded as having received it yet: each person is marked as Given on the
// Claim List when they (or a representative) actually claim it, and only then are the items deducted from stock.
function assistance_create_group(PDO $connection, array $input, string $group): array
{
    $input['record_as'] = 'scheduled';
    $checked = assistance_validate($connection, $input, null, true);
    $values = $checked['values'];
    $resolved = $checked['resolved'];
    $errors = $checked['errors'];
    $label = assistance_priority_groups()[$group] ?? 'residents';
    $purok = $values['group_purok'];
    if ($purok !== 'all' && !in_array($purok, disaster_vulnerable_puroks($connection), true)) $errors['beneficiary'] = 'Select a Purok, or All Puroks.';
    $recipients = isset($errors['beneficiary']) ? [] : assistance_group_recipients($connection, $group, $purok);
    $place = $purok === 'all' ? 'the barangay' : residents_purok_label($purok);
    if (!isset($errors['beneficiary']) && $recipients === []) $errors['beneficiary'] = $group === 'all' ? 'There are no active residents in ' . $place . '.' : 'No active residents are recorded as ' . $label . ' in ' . $place . '. Tick ' . $label . ' under Sector on the resident profiles first.';
    $count = count($recipients);
    if ($count > 0 && !isset($errors['items']) && $values['assistance_form'] === 'in_kind' && $values['record_as'] === 'given') {
        $known = array_column(assistance_plannable_items($connection), null, 'id');
        foreach ($resolved['wanted'] as $item_id => $quantity) {
            if (isset($known[$item_id]) && $quantity * $count > (int) $known[$item_id]['quantity']) { $errors['items'] = number_format($quantity * $count) . ' ' . $known[$item_id]['unit'] . ' of ' . $known[$item_id]['name'] . ' are needed for ' . $count . ' ' . $label . ', but only ' . number_format((int) $known[$item_id]['quantity']) . ' are on hand.'; break; }
        }
    }
    $total_cash = $resolved['cash'] !== null ? number_format(assistance_cents($resolved['cash']) * $count / 100, 2, '.', '') : null;
    if ($count > 0 && $resolved['finance_id'] !== null && !isset($errors['finance_transaction_id']) && ($error = assistance_finance_error($connection, $resolved['finance_id'], (string) $total_cash, null)) !== null) $errors['finance_transaction_id'] = 'The total for ' . $count . ' ' . $label . ' is ' . assistance_peso($total_cash) . '. ' . $error;
    if ($count > 0 && !isset($errors['purpose'])) {
        $ids = implode(',', array_map(static fn (array $r): int => (int) $r['id'], $recipients));
        $match = $resolved['incident_id'] !== null ? 'incident_id = :match' : 'incident_id IS NULL AND purpose = :match';
        $statement = $connection->prepare("SELECT DISTINCT resident_id FROM assistance_distributions WHERE resident_id IN ($ids) AND $match AND status <> 'void'");
        $statement->execute(['match' => $resolved['incident_id'] ?? $values['purpose']]);
        $already = count($statement->fetchAll(PDO::FETCH_COLUMN));
        if ($already > 0 && !$values['confirm_duplicate']) $errors['confirm_duplicate'] = $already . ' of the ' . $count . ' ' . $label . ' already have assistance ' . ($resolved['incident_id'] !== null ? 'for this incident' : 'for the same purpose') . '. Tick the box to give it to all ' . $count . ' again anyway.';
    }
    if ($errors !== []) return ['errors' => $errors, 'values' => $values];

    return assistance_transaction($connection, static function () use ($connection, $values, $resolved, $recipients, $label, $count, $total_cash, $place): array {
        if ($resolved['finance_id'] !== null && ($error = assistance_finance_error($connection, $resolved['finance_id'], (string) $total_cash, null, true)) !== null) throw new DomainException('finance_transaction_id|' . $error);
        $references = [];
        foreach ($recipients as $resident) {
            $name = residents_full_name($resident);
            try {
                $record = assistance_insert_record($connection, $values, $resolved, ['type' => 'resident', 'id' => (int) $resident['id'], 'name' => $name, 'groups' => assistance_beneficiary_groups('resident', $resident), 'group_label' => $label], '', null);
            } catch (DomainException $exception) {
                throw new DomainException('items|' . explode('|', $exception->getMessage(), 2)[1] . ' (while giving to ' . $name . '; nothing was recorded)');
            }
            $references[] = $record['reference'];
        }
        $each = $values['assistance_form'] === 'cash' ? assistance_peso($resolved['cash']) . ' each (' . assistance_peso($total_cash) . ' total)' : 'the same items each';
        return ['redirect' => 'assistance_claims.php?q=' . rawurlencode(mb_substr($values['purpose'], 0, 100)), 'message' => 'Scheduled for all ' . $count . ' ' . $label . ' in ' . $place . ': ' . $each . '. References ' . $references[0] . ($count > 1 ? ' to ' . end($references) : '') . '. Mark each person as Given here when they claim it' . ($values['assistance_form'] === 'in_kind' ? '; the items are deducted from stock then.' : '.')];
    }, $values);
}

function assistance_finance_reference(PDO $connection, ?int $finance_id): ?string
{
    if ($finance_id === null) return null;
    $statement = $connection->prepare('SELECT reference_no FROM finance_transactions WHERE id = :id');
    $statement->execute(['id' => $finance_id]);
    return $statement->fetchColumn() ?: null;
}

// Edits a Scheduled record (everything) or a Given record (details only). Returns ['id', 'message'] or ['errors', 'values'].
function assistance_update(PDO $connection, int $id, array $input, string $expected_updated_at): array
{
    $entry = assistance_find($connection, $id);
    if ($entry === null || $entry['status'] === 'void') return ['errors' => ['form' => 'This record can no longer be edited.'], 'values' => assistance_input($input)];
    $checked = assistance_validate($connection, $input, $entry);
    if ($checked['errors'] !== []) return ['errors' => $checked['errors'], 'values' => $checked['values']];
    $values = $checked['values'];
    $resolved = $checked['resolved'];
    $connection->beginTransaction();
    try {
        $locked = assistance_find($connection, $id, true);
        if ($locked === null || $locked['updated_at'] !== $expected_updated_at || $locked['status'] !== $entry['status']) throw new DomainException('form|This record was changed by another user after you opened it. Reload the page and try again.');
        if ($resolved['finance_id'] !== null && ($error = assistance_finance_error($connection, $resolved['finance_id'], (string) ($locked['status'] === 'given' ? $locked['cash_amount'] : $resolved['cash']), $id, true)) !== null) throw new DomainException('finance_transaction_id|' . $error);
        $user = current_user()['id'];
        $new = [
            'incident_id' => $resolved['incident_id'], 'purpose' => $values['purpose'], 'received_by' => $values['received_by'],
            'receiver_relationship' => $values['receiver_relationship'] === '' ? null : $values['receiver_relationship'], 'document_no' => $values['document_no'] === '' ? null : $values['document_no'],
            'remarks' => $values['remarks'] === '' ? null : $values['remarks'], 'source_details' => $values['source_details'] === '' ? null : $values['source_details'], 'finance_transaction_id' => $resolved['finance_id'],
        ];
        if ($locked['status'] === 'scheduled') {
            $household = $values['beneficiary_type'] === 'household';
            $new += [
                'beneficiary_type' => $values['beneficiary_type'], 'resident_id' => $household ? null : $resolved['beneficiary']['id'], 'household_id' => $household ? $resolved['beneficiary']['id'] : null,
                'priority_groups' => implode(',', $resolved['groups']), 'assistance_type' => $values['assistance_type'], 'assistance_form' => $values['assistance_form'], 'source' => $values['source'],
                'cash_amount' => $values['assistance_form'] === 'cash' ? $resolved['cash'] : null, 'given_on' => $values['given_on'],
            ];
        }
        $changes = [];
        foreach ($new as $field => $value) {
            $old = $locked[$field];
            if ((string) $old !== (string) $value) $changes[$field] = ['old' => $old, 'new' => $value];
        }
        $lines_changed = false;
        if ($locked['status'] === 'scheduled') {
            $current = [];
            foreach (assistance_items($connection, $id) as $line) $current[(int) $line['item_id']] = (int) $line['quantity'];
            $wanted = $values['assistance_form'] === 'in_kind' ? $resolved['wanted'] : [];
            ksort($current); ksort($wanted);
            if ($current !== $wanted) {
                $lines_changed = true;
                $connection->prepare('DELETE FROM assistance_items WHERE distribution_id = :id AND inventory_movement_id IS NULL')->execute(['id' => $id]);
                $known = array_column(assistance_plannable_items($connection), null, 'id');
                $insert = $connection->prepare('INSERT INTO assistance_items (distribution_id, item_id, quantity, unit) VALUES (:distribution, :item, :quantity, :unit)');
                foreach ($wanted as $item_id => $quantity) $insert->execute(['distribution' => $id, 'item' => $item_id, 'quantity' => $quantity, 'unit' => $known[$item_id]['unit']]);
                $changes['items'] = ['old' => $current, 'new' => $wanted];
            }
        }
        if ($changes === []) { $connection->rollBack(); return ['id' => $id, 'message' => 'No changes were made.']; }
        $set = implode(', ', array_map(static fn (string $field): string => "$field = :$field", array_keys($new)));
        $connection->prepare("UPDATE assistance_distributions SET $set, updated_by = :updated_by WHERE id = :id")->execute($new + ['updated_by' => $user, 'id' => $id]);
        residents_audit($connection, 'assistance', $id, 'assistance_updated', ['reference' => $locked['reference_no'], 'changed_fields' => array_keys($changes)]);
        if (array_key_exists('finance_transaction_id', $changes)) {
            if ($locked['finance_transaction_id'] !== null) residents_audit($connection, 'assistance', $id, 'assistance_finance_unlinked', ['reference' => $locked['reference_no'], 'finance_reference' => $locked['finance_reference']]);
            if ($resolved['finance_id'] !== null) residents_audit($connection, 'assistance', $id, 'assistance_finance_linked', ['reference' => $locked['reference_no'], 'finance_reference' => assistance_finance_reference($connection, $resolved['finance_id'])]);
        }
        $connection->commit();
        return ['id' => $id, 'message' => $locked['reference_no'] . ' was updated.' . ($lines_changed ? ' The planned items were updated.' : '')];
    } catch (DomainException $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        [$field, $message] = explode('|', $exception->getMessage(), 2);
        return ['errors' => [$field => $message], 'values' => $values];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

// Scheduled → Given: deducts the planned items from stock now. Throws RuntimeException with a message for the user.
function assistance_mark_given(PDO $connection, int $id, array $input): string
{
    $date_input = trim((string) ($input['given_on'] ?? ''));
    $received = residents_collapse($input['received_by'] ?? '');
    $relationship = residents_collapse($input['receiver_relationship'] ?? '');
    $date = disaster_valid_date($date_input);
    if ($date === null || $date > new DateTimeImmutable('today') || $date < (new DateTimeImmutable('today'))->modify('-1 year')) throw new RuntimeException('Enter the date it was given (today or within the past year, not in the future).');
    if ($error = disaster_person_name_error($received, true, 'name of the person who received the assistance')) throw new RuntimeException($error);
    if ($relationship !== '' && (mb_strlen($relationship) > 60 || !preg_match("/^\p{L}[\p{L}\p{M}\s.,'\-\/]*$/u", $relationship))) throw new RuntimeException('Enter the relationship in words (up to 60 characters).');
    $connection->beginTransaction();
    try {
        $entry = assistance_find($connection, $id, true);
        if ($entry === null || $entry['status'] !== 'scheduled') throw new RuntimeException('Only a Scheduled record can be marked as Given.');
        $name = assistance_beneficiary_label($entry);
        $household = $entry['household_id'] !== null;
        if (($error = assistance_receiver_error($connection, $household ? 'household' : 'resident', (int) ($household ? $entry['household_id'] : $entry['resident_id']), residents_full_name($entry), $received, $relationship)) !== null) throw new RuntimeException($error);
        $given = [];
        if (assistance_form_of($entry) === 'in_kind') {
            $wanted = [];
            foreach (assistance_items($connection, $id) as $line) $wanted[(int) $line['item_id']] = (int) $line['quantity'];
            if ($wanted === []) throw new RuntimeException('This record has no items. Edit it first.');
            try {
                $given = assistance_issue_items($connection, $id, $wanted, $entry['reference_no'], $name, $entry['purpose'], true);
            } catch (DomainException $exception) {
                throw new RuntimeException(explode('|', $exception->getMessage(), 2)[1]);
            }
        } else {
            $given = [assistance_peso($entry['cash_amount']) . ' cash'];
        }
        $connection->prepare("UPDATE assistance_distributions SET status = 'given', given_on = :date, received_by = :received, receiver_relationship = :relationship, updated_by = :user WHERE id = :id")->execute(['date' => $date_input, 'received' => $received, 'relationship' => $relationship === '' ? null : $relationship, 'user' => current_user()['id'], 'id' => $id]);
        residents_audit($connection, 'assistance', $id, 'assistance_marked_given', ['reference' => $entry['reference_no'], 'name' => $name, 'given' => implode(', ', $given)]);
        $connection->commit();
        return $entry['reference_no'] . ' is now Given: ' . implode(', ', $given) . ' to ' . $name . '.' . (assistance_form_of($entry) === 'in_kind' ? ' Inventory stock was updated.' : '');
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

// Void: the record is kept with the reason; items of a Given record are returned to stock. Throws RuntimeException.
function assistance_void(PDO $connection, int $id, string $reason): string
{
    $reason = residents_collapse($reason);
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) throw new RuntimeException('Give the reason for voiding (5 to 255 characters).');
    $connection->beginTransaction();
    try {
        $entry = assistance_find($connection, $id, true);
        if ($entry === null) throw new RuntimeException('The record no longer exists.');
        if ($entry['status'] === 'void') throw new RuntimeException('This record is already void.');
        $returned = $entry['status'] === 'given' ? assistance_return_items($connection, $entry, $reason) : 0;
        $connection->prepare("UPDATE assistance_distributions SET status = 'void', archived_at = NOW(), archived_by = :user, archive_reason = :reason, updated_by = :user2 WHERE id = :id")->execute(['user' => current_user()['id'], 'user2' => current_user()['id'], 'reason' => $reason, 'id' => $id]);
        residents_audit($connection, 'assistance', $id, 'assistance_voided', ['reference' => $entry['reference_no'], 'name' => assistance_beneficiary_label($entry), 'status_from' => $entry['status'], 'reason' => $reason, 'items_returned' => $returned]);
        $connection->commit();
        return $entry['reference_no'] . ' is now Void.' . ($returned > 0 ? ' Its items were returned to Inventory stock.' : '');
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

// ── List ────────────────────────────────────────────────────────────────────────

function assistance_list_state(array $input): array
{
    $state = [
        'q' => mb_substr(residents_collapse((string) ($input['q'] ?? '')), 0, 100),
        'form' => (string) ($input['form'] ?? ''),
        'group' => (string) ($input['group'] ?? ''),
        'category' => (string) ($input['category'] ?? ''),
        'source' => (string) ($input['source'] ?? ''),
        'incident' => (string) ($input['incident'] ?? ''),
        'from' => disaster_valid_date((string) ($input['from'] ?? '')) ? (string) $input['from'] : '',
        'to' => disaster_valid_date((string) ($input['to'] ?? '')) ? (string) $input['to'] : '',
        'status' => (string) ($input['status'] ?? ''),
        'page' => max(1, (int) ($input['page'] ?? 1)),
    ];
    if (!array_key_exists($state['form'], assistance_forms())) $state['form'] = '';
    if (!array_key_exists($state['group'], assistance_priority_groups() + ['general' => ''])) $state['group'] = '';
    if (!array_key_exists($state['category'], assistance_categories())) $state['category'] = '';
    if (!array_key_exists($state['source'], assistance_sources())) $state['source'] = '';
    if ($state['incident'] !== 'none' && !ctype_digit($state['incident'])) $state['incident'] = '';
    if (!array_key_exists($state['status'], assistance_statuses())) $state['status'] = '';
    if ($state['from'] !== '' && $state['to'] !== '' && $state['from'] > $state['to']) [$state['from'], $state['to']] = [$state['to'], $state['from']];
    return $state;
}

// WHERE on the assistance_select() joins. Status '' shows Scheduled and Given (Void only when chosen).
function assistance_list_where(array $state, ?string $status = null): array
{
    $status ??= $state['status'];
    $where = [$status === '' ? "a.status <> 'void'" : 'a.status = :status'];
    $params = $status === '' ? [] : ['status' => $status];
    if ($state['q'] !== '') {
        $like = '%' . addcslashes($state['q'], '%_\\') . '%';
        $where[] = "(CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name, r.suffix) LIKE :q1 OR CONCAT_WS(' ', r.first_name, r.last_name) LIKE :q2 OR h.household_no LIKE :q3 OR CONCAT_WS(' ', hr.first_name, hr.last_name) LIKE :q4 OR a.reference_no LIKE :q5 OR a.purpose LIKE :q6 OR a.received_by LIKE :q7 OR a.document_no LIKE :q8)";
        for ($i = 1; $i <= 8; $i++) $params["q$i"] = $like;
    }
    if ($state['form'] !== '') { $where[] = 'COALESCE(a.assistance_form, IF(a.cash_amount IS NULL, \'in_kind\', \'cash\')) = :form'; $params['form'] = $state['form']; }
    if ($state['group'] === 'general') $where[] = "a.priority_groups = ''";
    elseif ($state['group'] !== '') { $where[] = 'FIND_IN_SET(:grp, a.priority_groups) > 0'; $params['grp'] = $state['group']; }
    if ($state['category'] !== '') { $where[] = 'a.assistance_type = :category'; $params['category'] = $state['category']; }
    if ($state['source'] !== '') { $where[] = 'a.source = :source'; $params['source'] = $state['source']; }
    if ($state['incident'] === 'none') $where[] = 'a.incident_id IS NULL';
    elseif ($state['incident'] !== '') { $where[] = 'a.incident_id = :incident'; $params['incident'] = (int) $state['incident']; }
    if ($state['from'] !== '') { $where[] = 'a.given_on >= :from'; $params['from'] = $state['from']; }
    if ($state['to'] !== '') { $where[] = 'a.given_on <= :to'; $params['to'] = $state['to']; }
    return [implode(' AND ', $where), $params];
}

// Cards: records, beneficiaries, cash and item quantity. They follow the filters; with Status "All" they count Given
// records only (Scheduled and Void are counted only when that status is chosen).
function assistance_summary(PDO $connection, array $state): array
{
    [$where, $params] = assistance_list_where($state, $state['status'] === '' ? 'given' : $state['status']);
    $joins = ' FROM assistance_distributions a LEFT JOIN residents r ON r.id = a.resident_id LEFT JOIN households h ON h.id = a.household_id LEFT JOIN residents hr ON hr.id = h.household_head_resident_id';
    $statement = $connection->prepare("SELECT COUNT(*) AS records, COUNT(DISTINCT CONCAT(IF(a.household_id IS NULL, 'r', 'h'), COALESCE(a.household_id, a.resident_id))) AS beneficiaries, COALESCE(SUM(a.cash_amount), 0) AS cash$joins WHERE $where");
    $statement->execute($params);
    $summary = $statement->fetch();
    $items = $connection->prepare("SELECT COALESCE(SUM(x.quantity), 0) FROM assistance_items x INNER JOIN assistance_distributions a ON a.id = x.distribution_id LEFT JOIN residents r ON r.id = a.resident_id LEFT JOIN households h ON h.id = a.household_id LEFT JOIN residents hr ON hr.id = h.household_head_resident_id WHERE $where");
    $items->execute($params);
    $summary['items'] = (int) $items->fetchColumn();
    return $summary;
}

// Families evacuated for an incident (check-ins not removed) with no assistance (not void) for that incident yet.
function assistance_unserved(PDO $connection, int $incident_id): array
{
    [$family, $joins] = disaster_family_sql('e');
    $statement = $connection->prepare("SELECT e.household_id, e.resident_id, MAX($family) AS family_label, MAX(e.family_members) AS persons, GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS centers, MAX(e.departed_at IS NULL) AS in_center FROM drr_evacuations e INNER JOIN drr_evacuation_centers c ON c.id = e.center_id$joins WHERE e.incident_id = :incident AND e.archived_at IS NULL AND NOT EXISTS (SELECT 1 FROM assistance_distributions a WHERE a.incident_id = e.incident_id AND a.status <> 'void' AND ((e.household_id IS NOT NULL AND a.household_id = e.household_id) OR (e.resident_id IS NOT NULL AND a.resident_id = e.resident_id))) GROUP BY e.household_id, e.resident_id ORDER BY family_label");
    $statement->execute(['incident' => $incident_id]);
    return $statement->fetchAll();
}

// Editable header and signatories of the Acknowledgment printout (templates/assistance/print_settings.php).
function assistance_print_settings(): array
{
    $settings = require __DIR__ . '/../templates/assistance/print_settings.php';
    $defaults = ['header_lines' => [], 'barangay_name' => '', 'office' => '', 'address' => '', 'logo' => '', 'title' => 'ACKNOWLEDGMENT RECEIPT', 'statement' => '', 'noted_by' => ['name' => '', 'position' => '']];
    $settings = is_array($settings) ? array_merge($defaults, $settings) : $defaults;
    if ($settings['logo'] !== '' && !is_file(__DIR__ . '/../' . $settings['logo'])) $settings['logo'] = '';
    return $settings;
}
