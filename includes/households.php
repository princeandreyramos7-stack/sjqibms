<?php
declare(strict_types=1);

require_once __DIR__ . '/residents.php';

// Viewing follows the existing Households navigation roles; managing reuses the resident-management roles (super_admin, secretary).
function households_can_view(): bool
{
    return can_access_navigation('households');
}

function households_can_manage(): bool
{
    return residents_can_manage();
}

function households_require_view(): void
{
    require_auth();
    if (!households_can_view()) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function households_require_manage(): void
{
    require_auth();
    if (!households_can_view() || !households_can_manage()) {
        http_response_code(403);
        exit('Access denied.');
    }
}

// Active member count and occupancy follow the Dashboard definition: current primary membership of an Active resident.
// resident_households has a (resident_id, household_id) primary key, so a resident is never counted twice for one household.
function households_active_members_sql(string $alias = 'h'): string
{
    return "(SELECT COUNT(*) FROM resident_households rh_active INNER JOIN residents r_active ON r_active.id = rh_active.resident_id WHERE rh_active.household_id = $alias.id AND rh_active.is_primary = 1 AND rh_active.left_at IS NULL AND r_active.status = 'active')";
}

function households_occupancy_badge(int $active_members): string
{
    return $active_members > 0 ? '<span class="resident-status resident-status-active">Occupied</span>' : '<span class="resident-status resident-status-inactive">Unoccupied</span>';
}

// The address parts and housing details (house_no ... has_electricity) come from migration 20261014_household_details;
// before it is applied they are absent and the form keeps the single address box.
function households_details_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) $ready = (int) $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'households' AND COLUMN_NAME IN ('house_no', 'street', 'zone', 'house_ownership', 'water_source', 'toilet_facility', 'has_electricity')")->fetchColumn() === 7;
    return $ready;
}

function households_detail_columns(PDO $connection, string $alias = ''): string
{
    if (!households_details_ready($connection)) return '';
    $prefix = $alias === '' ? '' : $alias . '.';
    return implode('', array_map(static fn (string $column): string => ', ' . $prefix . $column, ['house_no', 'street', 'zone', 'house_ownership', 'water_source', 'toilet_facility', 'has_electricity']));
}

function households_find(PDO $connection, int $id, bool $lock = false): ?array
{
    if ($lock) {
        $statement = $connection->prepare('SELECT id, household_no, household_head_resident_id, address, purok, housing_type, notes' . households_detail_columns($connection) . ', created_at, updated_at FROM households WHERE id = :id LIMIT 1 FOR UPDATE');
    } else {
        $statement = $connection->prepare('SELECT h.id, h.household_no, h.household_head_resident_id, h.address, h.purok, h.housing_type, h.notes' . households_detail_columns($connection, 'h') . ', h.created_at, h.updated_at, hr.first_name, hr.middle_name, hr.last_name, hr.suffix, ' . households_active_members_sql() . ' AS active_members FROM households h LEFT JOIN residents hr ON hr.id = h.household_head_resident_id WHERE h.id = :id LIMIT 1');
    }
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row ?: null;
}

// ── Delete (unused households only) ──────────────────────────────────────────────────────────────────────────
// A household may be deleted only when nothing refers to it: no Household Head, no membership (current or past), no
// Relief & Assistance record and no evacuation or DRR relief record. Anything else keeps its history and is not deleted.
function households_reference_counts(PDO $connection, int $id): array
{
    $counts = ['members' => 'resident_households', 'assistance' => 'assistance_distributions', 'evacuations' => 'drr_evacuations', 'relief' => 'drr_relief_distributions'];
    $present = $connection->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('resident_households', 'assistance_distributions', 'drr_evacuations', 'drr_relief_distributions')")->fetchAll(PDO::FETCH_COLUMN);
    $result = [];
    foreach ($counts as $key => $table) {
        if (!in_array($table, $present, true)) { $result[$key] = 0; continue; }
        $statement = $connection->prepare("SELECT COUNT(*) FROM `$table` WHERE household_id = :id");
        $statement->execute(['id' => $id]);
        $result[$key] = (int) $statement->fetchColumn();
    }
    return $result;
}

function households_can_delete(PDO $connection, array $household): bool
{
    return households_delete_block_reason($connection, $household) === null;
}

// Why a household cannot be deleted, and what to do instead (null when it can be deleted). Shown when Delete is
// pressed on such a household; nothing is removed.
function households_delete_block_reason(PDO $connection, array $household): ?string
{
    $counts = households_reference_counts($connection, (int) $household['id']);
    $statement = $connection->prepare('SELECT COUNT(*) FROM resident_households WHERE household_id = :id AND is_primary = 1 AND left_at IS NULL');
    $statement->execute(['id' => $household['id']]);
    $current = (int) $statement->fetchColumn();
    $label = 'Household ' . $household['household_no'] . ' cannot be deleted: ';
    if ($current > 0) return $label . 'it has ' . $current . ' current member' . ($current === 1 ? '' : 's') . '. Transfer or remove them first in Manage Household. Its membership history is kept afterwards, so it stays as an Unoccupied household.';
    if ($counts['assistance'] + $counts['evacuations'] + $counts['relief'] > 0) return $label . 'it has Relief & Assistance or disaster records, which must be kept.';
    if ($household['household_head_resident_id'] !== null) return $label . 'it still has a designated household head.';
    if ($counts['members'] > 0) return $label . 'it has past members, and their membership history is kept for the residents\' records. It stays as an Unoccupied household.';
    return null;
}

// ── Choices on the Add / Edit Household form ─────────────────────────────────────────────────────────────────

function households_housing_types(): array
{
    return ['Concrete', 'Semi-concrete', 'Light materials', 'Makeshift / Salvaged materials', 'Other'];
}

function households_ownership_options(): array
{
    return ['Owned', 'Rented', 'Living with relatives', 'Informal settler', 'Other'];
}

function households_water_sources(): array
{
    return ['Level III (piped to house)', 'Level II (communal faucet)', 'Level I (deep well / pump)', 'Spring or river', 'Bottled / refilling station', 'Other'];
}

function households_toilet_options(): array
{
    return ['Own flush toilet', 'Shared flush toilet', 'Pit latrine', 'None', 'Other'];
}

function households_barangay_label(): string
{
    require_once __DIR__ . '/../config/site.php';
    return preg_replace('/^Barangay\s+/i', '', BARANGAY_NAME) . ', ' . BARANGAY_LOCATION;
}

// A stored housing type split into the dropdown choice and the "Please specify" text ("Other: Bamboo" → Other / Bamboo).
// An older free-text value that is not one of the choices is shown as Other with that text.
function households_housing_type_parts(?string $stored): array
{
    $stored = (string) $stored;
    if ($stored === '') return ['', ''];
    if (in_array($stored, households_housing_types(), true) && $stored !== 'Other') return [$stored, ''];
    if (str_starts_with($stored, 'Other: ')) return ['Other', mb_substr($stored, 7)];
    return ['Other', $stored];
}

// The next free number in a Purok: P[purok]-[4 digits], one more than the highest number already in that series.
function households_next_number(PDO $connection, string $purok): ?string
{
    if (!array_key_exists($purok, residents_purok_options())) return null;
    $prefix = 'P' . $purok . '-';
    $statement = $connection->prepare('SELECT household_no FROM households WHERE household_no LIKE :prefix');
    $statement->execute(['prefix' => addcslashes($prefix, '%_\\') . '%']);
    $highest = 0;
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $number) {
        if (preg_match('/^P' . preg_quote($purok, '/') . '-(\d+)$/', (string) $number, $match)) $highest = max($highest, (int) $match[1]);
    }
    return $prefix . str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
}

function households_number_taken(PDO $connection, string $number, ?int $exclude_id = null): bool
{
    $statement = $connection->prepare('SELECT id FROM households WHERE household_no = :household_no' . ($exclude_id !== null ? ' AND id <> :id' : '') . ' LIMIT 1');
    $statement->execute(['household_no' => $number] + ($exclude_id !== null ? ['id' => $exclude_id] : []));
    return (bool) $statement->fetchColumn();
}

// Validates the Add / Edit Household form. The address column is composed from the parts (house number, street, zone,
// Purok, barangay). $existing_purok is the stored Purok when editing, so an older value outside Purok 1–4 may be kept.
// Returns ['values' (database columns), 'errors' (by form field), 'duplicate', 'form' (what to show again)].
function households_validate(PDO $connection, array $input, ?int $exclude_id = null, ?string $existing_purok = null): array
{
    $form = [
        'household_no' => residents_collapse($input['household_no'] ?? ''),
        'purok' => residents_collapse($input['purok'] ?? ''),
        'house_no' => residents_collapse($input['house_no'] ?? ''),
        'street' => residents_collapse($input['street'] ?? ''),
        'zone' => residents_collapse($input['zone'] ?? ''),
        'housing_type' => (string) ($input['housing_type'] ?? ''),
        'housing_type_other' => residents_collapse($input['housing_type_other'] ?? ''),
        'house_ownership' => (string) ($input['house_ownership'] ?? ''),
        'water_source' => (string) ($input['water_source'] ?? ''),
        'toilet_facility' => (string) ($input['toilet_facility'] ?? ''),
        'has_electricity' => (string) ($input['has_electricity'] ?? ''),
        'notes' => trim((string) ($input['notes'] ?? '')),
    ];
    $errors = [];
    $duplicate = false;
    if ($form['household_no'] === '') $errors['household_no'] = 'Household number is required.';
    elseif (mb_strlen($form['household_no']) > 50 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9\-\/ ]*$/', $form['household_no'])) $errors['household_no'] = 'Use up to 50 letters, digits, spaces, hyphens or slashes.';
    elseif (households_number_taken($connection, $form['household_no'], $exclude_id)) { $errors['household_no'] = 'This household number is already in use.'; $duplicate = true; }
    if ($form['purok'] === '') $errors['purok'] = 'Please select a purok.';
    elseif (($purok_error = residents_purok_error($form['purok'], $existing_purok)) !== null) $errors['purok'] = $purok_error;
    // Same rules as Create Account (includes/sms_registration.php): house number and zone optional (many houses have no
    // number), street / sitio required.
    if ($form['house_no'] !== '' && (mb_strlen($form['house_no']) > 20 || !preg_match('/^[\p{L}0-9\s.\-\/#]+$/u', $form['house_no']))) $errors['house_no'] = 'Enter a valid house number (up to 20 characters).';
    if (mb_strlen($form['street']) < 2) $errors['street'] = 'Street / sitio is required.';
    elseif (mb_strlen($form['street']) > 150) $errors['street'] = 'Street / sitio must not exceed 150 characters.';
    if (mb_strlen($form['zone']) > 40 || ($form['zone'] !== '' && !preg_match('/^[\p{L}0-9\s.\-]+$/u', $form['zone']))) $errors['zone'] = 'Enter a valid zone (up to 40 characters).';
    if ($form['housing_type'] !== '' && !in_array($form['housing_type'], households_housing_types(), true)) $errors['housing_type'] = 'Select a housing type from the list.';
    elseif ($form['housing_type'] === 'Other' && $form['housing_type_other'] === '') $errors['housing_type_other'] = 'Please specify the housing type.';
    elseif (mb_strlen($form['housing_type_other']) > 70) $errors['housing_type_other'] = 'Use up to 70 characters.';
    foreach (['house_ownership' => households_ownership_options(), 'water_source' => households_water_sources(), 'toilet_facility' => households_toilet_options()] as $field => $options) {
        if ($form[$field] !== '' && !in_array($form[$field], $options, true)) $errors[$field] = 'Select an option from the list.';
    }
    if (!in_array($form['has_electricity'], ['', '1', '0'], true)) $errors['has_electricity'] = 'Select Yes or No.';
    if (mb_strlen($form['notes']) > 1000) $errors['notes'] = 'Notes must not exceed 1,000 characters.';

    $null = static fn (string $value): ?string => $value === '' ? null : $value;
    $address_parts = array_filter([$form['house_no'], $form['street'], $form['zone']], static fn (string $part): bool => $part !== '');
    $values = [
        'household_no' => $form['household_no'],
        'purok' => $form['purok'],
        'address' => implode(', ', $address_parts) . ', ' . residents_purok_label($form['purok']) . ', ' . households_barangay_label(),
        'house_no' => $null($form['house_no']),
        'street' => $form['street'],
        'zone' => $null($form['zone']),
        'housing_type' => $form['housing_type'] === 'Other' ? 'Other: ' . $form['housing_type_other'] : $null($form['housing_type']),
        'house_ownership' => $null($form['house_ownership']),
        'water_source' => $null($form['water_source']),
        'toilet_facility' => $null($form['toilet_facility']),
        'has_electricity' => $form['has_electricity'] === '' ? null : (int) $form['has_electricity'],
        'notes' => $null($form['notes']),
    ];
    // 'duplicate' lets the form show the dedicated duplicate-number message only for that specific error.
    return ['values' => $values, 'errors' => $errors, 'duplicate' => $duplicate, 'form' => $form];
}

// The form fields for a stored household (Edit), the reverse of households_validate().
function households_form_values(?array $record): array
{
    [$type, $other] = households_housing_type_parts($record['housing_type'] ?? null);
    return [
        'household_no' => (string) ($record['household_no'] ?? ''),
        'purok' => (string) ($record['purok'] ?? ''),
        'house_no' => (string) ($record['house_no'] ?? ''),
        'street' => (string) ($record['street'] ?? ''),
        'zone' => (string) ($record['zone'] ?? ''),
        'housing_type' => $type,
        'housing_type_other' => $other,
        'house_ownership' => (string) ($record['house_ownership'] ?? ''),
        'water_source' => (string) ($record['water_source'] ?? ''),
        'toilet_facility' => (string) ($record['toilet_facility'] ?? ''),
        'has_electricity' => ($record['has_electricity'] ?? null) === null ? '' : (string) (int) $record['has_electricity'],
        'notes' => (string) ($record['notes'] ?? ''),
    ];
}

// Every recorded membership for the household, oldest first. Missing dates are shown as not recorded, never inferred.
function households_membership_timeline(PDO $connection, int $household_id): array
{
    $statement = $connection->prepare('SELECT rh.resident_id, rh.relationship_to_head, rh.is_primary, rh.joined_at, rh.left_at, rh.created_at, r.first_name, r.middle_name, r.last_name, r.suffix, r.status FROM resident_households rh INNER JOIN residents r ON r.id = rh.resident_id WHERE rh.household_id = :household_id ORDER BY COALESCE(rh.joined_at, DATE(rh.created_at)) ASC, rh.created_at ASC, rh.resident_id ASC');
    $statement->execute(['household_id' => $household_id]);
    return $statement->fetchAll();
}

// Minimal identification for the member search: ID, name, birth year, Purok, status and current household number.
// previous_member flags residents with a closed membership in $household_id: resident_households allows one row per resident and household,
// so rejoining would overwrite that history and is refused.
function households_search_residents(PDO $connection, string $term, int $household_id): array
{
    $like = '%' . addcslashes($term, '%_\\') . '%';
    $statement = $connection->prepare("SELECT r.id, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.purok, r.status, rh.household_id AS current_household_id, h.household_no AS current_household_no, EXISTS (SELECT 1 FROM resident_households rh_prev WHERE rh_prev.resident_id = r.id AND rh_prev.household_id = :household_id AND NOT (rh_prev.is_primary = 1 AND rh_prev.left_at IS NULL)) AS previous_member FROM residents r LEFT JOIN resident_households rh ON rh.resident_id = r.id AND rh.is_primary = 1 AND rh.left_at IS NULL LEFT JOIN households h ON h.id = rh.household_id WHERE CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name, r.suffix) LIKE :search_full OR CONCAT_WS(' ', r.first_name, r.last_name) LIKE :search_short OR CONCAT_WS(', ', r.last_name, r.first_name) LIKE :search_reverse ORDER BY r.last_name, r.first_name, r.id LIMIT 20");
    $statement->execute(['search_full' => $like, 'search_short' => $like, 'search_reverse' => $like, 'household_id' => $household_id]);
    return $statement->fetchAll();
}
