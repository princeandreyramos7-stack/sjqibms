<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/dashboard_stats.php';

// Viewing follows the existing Residents navigation roles; managing follows the announcement-management roles (super_admin, secretary).
function residents_can_view(): bool
{
    return can_access_navigation('residents');
}

function residents_can_manage(): bool
{
    return role_can('residents.manage');
}

function residents_require_view(): void
{
    require_auth();
    if (!residents_can_view()) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function residents_require_manage(): void
{
    require_auth();
    if (!residents_can_manage()) {
        http_response_code(403);
        exit('Access denied.');
    }
}

// Option lists mirror the live ENUM definitions of residents.status, residents.sex and residents.civil_status.
function residents_status_labels(): array
{
    return ['active' => 'Active', 'pending' => 'Pending', 'inactive' => 'Inactive', 'moved' => 'Moved', 'deceased' => 'Deceased'];
}

function residents_sex_labels(): array
{
    return ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'];
}

function residents_civil_status_labels(): array
{
    return ['single' => 'Single', 'married' => 'Married', 'widowed' => 'Widowed', 'separated' => 'Separated', 'other' => 'Other'];
}

function residents_relationships(): array
{
    return ['Spouse', 'Son', 'Daughter', 'Father', 'Mother', 'Brother', 'Sister', 'Grandchild', 'Grandparent', 'Other relative', 'Non-relative'];
}

function residents_collapse(?string $value): string
{
    return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
}

function residents_normalize_name(?string $value): string
{
    return mb_strtolower(residents_collapse($value), 'UTF-8');
}

function residents_full_name(array $resident): string
{
    return residents_collapse(implode(' ', [$resident['first_name'] ?? '', $resident['middle_name'] ?? '', $resident['last_name'] ?? '', $resident['suffix'] ?? '']));
}

function residents_age(?string $birth_date): ?int
{
    return demographic_age($birth_date);
}

function residents_format_date(?string $value): string
{
    if (!$value) return '';
    $date = date_create($value);
    return $date ? $date->format('M j, Y') : $value;
}

function residents_valid_date(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) return null;
    return $date;
}

// ── Health Worker Purok assignment (table health_worker_puroks, migration 20261016_health_worker_portal) ──
// The Puroks assigned to a Health Worker account (empty: none assigned).
function health_worker_puroks(PDO $connection, int $user_id): array
{
    static $ready = null;
    if ($ready === null) $ready = (int) $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'health_worker_puroks'")->fetchColumn() === 1;
    if (!$ready) return [];
    $statement = $connection->prepare('SELECT purok FROM health_worker_puroks WHERE user_id = :id ORDER BY purok');
    $statement->execute(['id' => $user_id]);
    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

// The Puroks the signed-in user is limited to: a Health Worker with assigned Puroks sees only health records (Health
// list, history, reports, resident picker) of residents in those Puroks. Residents and Households pages also apply it
// as a safeguard, although Health Workers no longer open them. Null means no limit (every other role, or a Health
// Worker with none assigned).
function residents_purok_scope(PDO $connection): ?array
{
    $user = current_user();
    if (($user['role'] ?? '') !== 'health_worker') return null;
    $puroks = health_worker_puroks($connection, (int) $user['id']);
    return $puroks === [] ? null : $puroks;
}

// SQL condition and parameters for that limit on a residents column (e.g. 'purok' or 'r.purok'); ['1 = 1', []] when none.
function residents_purok_scope_sql(PDO $connection, string $column, string $prefix = 'scope'): array
{
    $scope = residents_purok_scope($connection);
    if ($scope === null) return ['1 = 1', []];
    $names = [];
    $params = [];
    foreach (array_values($scope) as $index => $purok) { $names[] = ':' . $prefix . $index; $params[$prefix . $index] = $purok; }
    return ["$column IN (" . implode(', ', $names) . ')', $params];
}

function residents_in_scope(PDO $connection, ?string $purok): bool
{
    $scope = residents_purok_scope($connection);
    return $scope === null || in_array((string) $purok, $scope, true);
}

// $existing is the stored record when editing: birthdate and sex stay optional for legacy profiles that never had them, but cannot be cleared once recorded.
function residents_validate(array $input, ?array $existing = null): array
{
    $values = [];
    foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'contact_number', 'purok'] as $field) {
        $values[$field] = residents_collapse($input[$field] ?? '');
    }
    $values['address'] = trim((string) ($input['address'] ?? ''));
    $values['birth_date'] = trim((string) ($input['birth_date'] ?? ''));
    $values['sex'] = (string) ($input['sex'] ?? '');
    $values['civil_status'] = (string) ($input['civil_status'] ?? '');
    $errors = [];

    $name_pattern = "/^\p{L}[\p{L}\p{M}\s.'\-]*$/u";
    foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $field => $label) {
        if ($values[$field] === '') $errors[$field] = "$label is required.";
        elseif (mb_strlen($values[$field]) > 80) $errors[$field] = "$label must not exceed 80 characters.";
        elseif (!preg_match($name_pattern, $values[$field])) $errors[$field] = "$label may contain only letters, spaces, periods, apostrophes and hyphens.";
    }
    if ($values['middle_name'] !== '' && (mb_strlen($values['middle_name']) > 80 || !preg_match($name_pattern, $values['middle_name']))) $errors['middle_name'] = 'Enter a valid middle name of up to 80 characters.';
    if ($values['suffix'] !== '' && (mb_strlen($values['suffix']) > 20 || !preg_match('/^[\p{L}0-9.\s]+$/u', $values['suffix']))) $errors['suffix'] = 'Enter a valid suffix of up to 20 characters, such as Jr. or III.';

    $birth_required = $existing === null || !empty($existing['birth_date']);
    if ($values['birth_date'] === '') {
        if ($birth_required) $errors['birth_date'] = 'Birthdate is required.';
    } else {
        $birth = residents_valid_date($values['birth_date']);
        if ($birth === null) $errors['birth_date'] = 'Enter a valid birthdate.';
        elseif ($birth > new DateTimeImmutable('today')) $errors['birth_date'] = 'Birthdate cannot be in the future.';
        elseif ($birth < new DateTimeImmutable('1900-01-01')) $errors['birth_date'] = 'Enter a realistic birthdate.';
    }

    $sex_required = $existing === null || !empty($existing['sex']);
    if ($values['sex'] === '') {
        if ($sex_required) $errors['sex'] = 'Sex is required.';
    } elseif (!array_key_exists($values['sex'], residents_sex_labels())) {
        $errors['sex'] = 'Select a valid sex.';
    }
    if ($values['civil_status'] !== '' && !array_key_exists($values['civil_status'], residents_civil_status_labels())) $errors['civil_status'] = 'Select a valid civil status.';

    if ($values['contact_number'] !== '') {
        $digits = preg_replace('/\D/', '', $values['contact_number']) ?? '';
        if (mb_strlen($values['contact_number']) > 30 || !preg_match('/^\+?[0-9][0-9\s()\-]*$/', $values['contact_number']) || strlen($digits) < 7 || strlen($digits) > 15) {
            $errors['contact_number'] = 'Enter a valid contact number, for example 09171234567 or +63 917 123 4567.';
        }
    }
    if ($values['address'] === '') $errors['address'] = 'Address is required.';
    elseif (mb_strlen($values['address']) > 500) $errors['address'] = 'Address must not exceed 500 characters.';
    if (($purok_error = residents_purok_error($values['purok'], $existing['purok'] ?? null)) !== null) $errors['purok'] = $purok_error;

    foreach (['middle_name', 'suffix', 'birth_date', 'sex', 'civil_status', 'contact_number'] as $nullable) {
        if ($values[$nullable] === '') $values[$nullable] = null;
    }
    return ['values' => $values, 'errors' => $errors];
}

// ── Years of residency (residents.residency_start_year, added by the review migration 20260926_resident_years_of_residency) ──
// Staff enter the number of years; the starting year is stored so the count stays correct as time passes. Until the
// migration is applied the column is absent and every caller behaves exactly as before.
function residents_residency_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $check = $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'residents' AND COLUMN_NAME = 'residency_start_year'");
        $ready = (int) $check->fetchColumn() === 1;
    }
    return $ready;
}

function residents_years_of_residency(mixed $start_year): ?int
{
    if ($start_year === null || $start_year === '') return null;
    return max(0, (int) date('Y') - (int) $start_year);
}

// Validates the optional "Years of residency" input (whole years, 0–120, not more than the resident's age when the
// birthdate is known). Returns the value to show again, the starting year to store (null when left blank) and any error.
function residents_validate_residency(array $input, ?string $birth_date): array
{
    $raw = trim((string) ($input['years_of_residency'] ?? ''));
    if ($raw === '') return ['input' => '', 'start_year' => null, 'error' => null];
    if (!preg_match('/^\d{1,3}$/', $raw) || (int) $raw > 120) return ['input' => $raw, 'start_year' => null, 'error' => 'Enter the number of years as a whole number from 0 to 120.'];
    $years = (int) $raw;
    $age = residents_age($birth_date);
    if ($age !== null && $years > $age) return ['input' => $raw, 'start_year' => null, 'error' => 'Years of residency cannot be more than the resident’s age (' . $age . ').'];
    return ['input' => (string) $years, 'start_year' => (int) date('Y') - $years, 'error' => null];
}

// ── PWD and Solo Parent (residents.is_pwd / is_solo_parent, review migration 20261007_resident_pwd_solo_parent) ──
// Checkboxes on the resident profile. Until the migration is applied the columns are absent and every caller behaves
// exactly as before (nothing is shown, counted or saved).
function residents_sector_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $check = $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'residents' AND COLUMN_NAME IN ('is_pwd', 'is_solo_parent')");
        $ready = (int) $check->fetchColumn() === 2;
    }
    return $ready;
}

function residents_sector_labels(): array
{
    return ['is_pwd' => 'PWD', 'is_solo_parent' => 'Solo Parent'];
}

// The checkbox values from a submitted form (1 when ticked, otherwise 0).
function residents_sector_input(array $input): array
{
    $values = [];
    foreach (array_keys(residents_sector_labels()) as $field) $values[$field] = (string) ($input[$field] ?? '') === '1' ? 1 : 0;
    return $values;
}

// The Sector choice is required: "None" (sector_none=1), or PWD and/or Solo Parent — never None together with either.
function residents_sector_error(array $input, array $sector): ?string
{
    $none = (string) ($input['sector_none'] ?? '') === '1';
    if ($none && array_sum($sector) > 0) return 'Select None only when the resident is neither PWD nor a Solo Parent.';
    if (!$none && array_sum($sector) === 0) return 'Select None, or tick PWD and/or Solo Parent.';
    return null;
}

// "PWD" / "Solo Parent" tags for a resident row (empty when none applies or the columns are absent).
function residents_sector_tags(array $resident): string
{
    $html = '';
    foreach (residents_sector_labels() as $field => $label) if ((int) ($resident[$field] ?? 0) === 1) $html .= ' <span class="resident-tag resident-tag-' . e(str_replace('is_', '', $field)) . '">' . e($label) . '</span>';
    return $html;
}

function residents_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $residency = residents_residency_ready($connection) ? ' residency_start_year,' : '';
    $residency .= residents_sector_ready($connection) ? ' is_pwd, is_solo_parent,' : '';
    $statement = $connection->prepare('SELECT id, user_id, first_name, middle_name, last_name, suffix, birth_date, sex, civil_status, contact_number, address, purok,' . $residency . ' status, created_at, updated_at FROM residents WHERE id = :id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row ?: null;
}

// Exact: same normalized first and last name and the same birthdate, with middle name and suffix agreeing whenever both are recorded.
// Possible: same normalized first and last name with a different or missing birthdate, or same last name and birthdate with a different first name.
// Every status is checked, so a moved or deceased profile still blocks a duplicate.
function residents_duplicates(PDO $connection, array $values, ?int $exclude_id = null): array
{
    $statement = $connection->prepare("SELECT id, first_name, middle_name, last_name, suffix, birth_date, purok, status FROM residents WHERE LOWER(TRIM(REGEXP_REPLACE(last_name, '[[:space:]]+', ' '))) = :last_name" . ($exclude_id !== null ? ' AND id <> :exclude_id' : '') . ' ORDER BY id');
    $params = ['last_name' => residents_normalize_name($values['last_name'])];
    if ($exclude_id !== null) $params['exclude_id'] = $exclude_id;
    $statement->execute($params);
    $first = residents_normalize_name($values['first_name']);
    $middle = residents_normalize_name($values['middle_name'] ?? '');
    $suffix = residents_normalize_name(str_replace('.', '', (string) ($values['suffix'] ?? '')));
    $birth = $values['birth_date'] ?? null;
    $matches = ['exact' => [], 'possible' => []];
    foreach ($statement->fetchAll() as $row) {
        $same_first = residents_normalize_name($row['first_name']) === $first;
        $same_birth = $birth !== null && $row['birth_date'] !== null && $row['birth_date'] === $birth;
        $row_middle = residents_normalize_name($row['middle_name']);
        $row_suffix = residents_normalize_name(str_replace('.', '', (string) $row['suffix']));
        $middle_agrees = $middle === '' || $row_middle === '' || $middle === $row_middle;
        $suffix_agrees = $suffix === '' || $row_suffix === '' || $suffix === $row_suffix;
        $summary = ['id' => (int) $row['id'], 'name' => residents_full_name($row), 'birth_date' => $row['birth_date'], 'purok' => $row['purok'], 'status' => $row['status']];
        if ($same_first && $same_birth && $middle_agrees && $suffix_agrees) $matches['exact'][] = $summary;
        elseif ($same_first || $same_birth) $matches['possible'][] = $summary;
    }
    return $matches;
}

function residents_duplicate_ids(array $matches): array
{
    $ids = array_merge(array_column($matches['exact'], 'id'), array_column($matches['possible'], 'id'));
    sort($ids);
    return $ids;
}

// Server-side duplicate gate. An override is accepted only when the reviewer confirmed, gave a reason, named one of the listed matches,
// and the matches reviewed on screen still cover every match found now (a newly appearing match forces a fresh review).
function residents_duplicate_review(array $matches, array $input): array
{
    $ids = residents_duplicate_ids($matches);
    if ($ids === []) return ['blocked' => false, 'override' => null, 'errors' => []];
    $reviewed = array_values(array_filter(array_map('intval', explode(',', (string) ($input['reviewed_match_ids'] ?? ''))), static fn (int $id): bool => $id > 0));
    sort($reviewed);
    if (($input['duplicate_override'] ?? '') !== '1' || $reviewed === [] || array_diff($ids, $reviewed) !== []) return ['blocked' => true, 'override' => null, 'errors' => []];
    $errors = [];
    $reason = trim((string) ($input['override_reason'] ?? ''));
    $reference = filter_var($input['override_resident_id'] ?? null, FILTER_VALIDATE_INT);
    if (mb_strlen($reason) < 15 || mb_strlen($reason) > 500) $errors['override_reason'] = 'Explain how identity was verified (15 to 500 characters).';
    if (!$reference || !in_array($reference, $ids, true)) $errors['override_resident_id'] = 'Select the existing resident record you reviewed.';
    if ($errors !== []) return ['blocked' => true, 'override' => null, 'errors' => $errors];
    return ['blocked' => false, 'override' => ['reason' => $reason, 'reviewed_resident_id' => $reference, 'matched_resident_ids' => $ids, 'exact_match_ids' => array_column($matches['exact'], 'id')], 'errors' => []];
}

// Barangay San Jose has four Puroks. The number is what is stored (matching existing records and announcement targets); the label is what is shown.
function residents_purok_options(): array
{
    return ['1' => 'Purok 1', '2' => 'Purok 2', '3' => 'Purok 3', '4' => 'Purok 4'];
}

// Stored values outside Purok 1–4 (older free-text entries) are shown as recorded.
function residents_purok_label(?string $purok): string
{
    $purok = (string) $purok;
    return residents_purok_options()[$purok] ?? $purok;
}

// A Purok must be one of the four, except that an edit may keep the older value already on the record.
function residents_purok_error(string $purok, ?string $current = null, string $label = 'Purok'): ?string
{
    if ($purok === '') return "$label is required.";
    if (array_key_exists($purok, residents_purok_options()) || ($current !== null && $current !== '' && $purok === $current)) return null;
    return 'Select Purok 1, 2, 3 or 4.';
}

// Select options: the four Puroks plus, when editing an older record, its current value so it is not silently replaced.
function residents_purok_select_options(?string $current = null): array
{
    $options = residents_purok_options();
    if ($current !== null && $current !== '' && !array_key_exists($current, $options)) $options[$current] = $current . ' (current, not Purok 1–4)';
    return $options;
}

// Age groups (completed years, as residents_age() computes them). Brackets match the dashboard's age statistics, with infants listed separately.
function residents_age_groups(): array
{
    return [
        'infant' => ['label' => 'Infants', 'range' => 'Under 1 year', 'min' => 0, 'max' => 0],
        'child' => ['label' => 'Children', 'range' => '1–12 years', 'min' => 1, 'max' => 12],
        'teen' => ['label' => 'Teenagers', 'range' => '13–17 years', 'min' => 13, 'max' => 17],
        'adult' => ['label' => 'Adults', 'range' => '18–59 years', 'min' => 18, 'max' => 59],
        'senior' => ['label' => 'Senior Citizens', 'range' => '60 years and above', 'min' => 60, 'max' => null],
    ];
}

// SQL condition on residents.birth_date for an age group key or 'unknown' (no valid birthdate). Dates are computed here from
// today's date, so the database and residents_age() always agree; they are generated values, quoted for the query.
function residents_age_group_condition(PDO $connection, string $group): ?string
{
    $today = new DateTimeImmutable('today');
    if ($group === 'unknown') return 'birth_date IS NULL OR birth_date > ' . $connection->quote($today->format('Y-m-d'));
    $definition = residents_age_groups()[$group] ?? null;
    if ($definition === null) return null;
    // Age >= min  <=>  born on or before today minus min years; age <= max  <=>  born after today minus (max + 1) years.
    $condition = 'birth_date <= ' . $connection->quote($today->modify('-' . $definition['min'] . ' years')->format('Y-m-d'));
    if ($definition['max'] !== null) $condition .= ' AND birth_date > ' . $connection->quote($today->modify('-' . ($definition['max'] + 1) . ' years')->format('Y-m-d'));
    return $condition;
}

// Resident counts per age group, plus 'unknown'. $where_sql (" WHERE ...") and $params optionally narrow the residents counted.
function residents_age_group_counts(PDO $connection, string $where_sql = '', array $params = []): array
{
    $cases = [];
    foreach (array_merge(['unknown'], array_keys(residents_age_groups())) as $group) $cases[] = 'WHEN ' . residents_age_group_condition($connection, $group) . ' THEN ' . $connection->quote($group);
    $statement = $connection->prepare('SELECT CASE ' . implode(' ', $cases) . ' END AS age_group, COUNT(*) FROM residents' . $where_sql . ' GROUP BY age_group');
    $statement->execute($params);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_KEY_PAIR));
}

function residents_puroks(PDO $connection): array
{
    return $connection->query("SELECT purok FROM residents WHERE purok <> '' UNION SELECT purok FROM households WHERE purok <> '' ORDER BY purok")->fetchAll(PDO::FETCH_COLUMN);
}

function residents_household_options(PDO $connection): array
{
    return $connection->query('SELECT h.id, h.household_no, h.address, h.purok, h.household_head_resident_id, hr.first_name, hr.middle_name, hr.last_name, hr.suffix FROM households h LEFT JOIN residents hr ON hr.id = h.household_head_resident_id ORDER BY h.household_no')->fetchAll();
}

function residents_household_label(array $household): string
{
    $head = $household['household_head_resident_id'] ? residents_full_name($household) : 'No designated head';
    return $household['household_no'] . ' — ' . $head . ' — ' . residents_collapse($household['address']) . ' (' . residents_purok_label($household['purok']) . ')';
}

// Household section input shared by registration and the Manage Household page. $allow_later is false where an assignment is mandatory.
function residents_validate_household(PDO $connection, array $input, bool $allow_later = true): array
{
    $mode = (string) ($input['household_mode'] ?? ($allow_later ? 'later' : ''));
    $allowed = $allow_later ? ['existing', 'new', 'later'] : ['existing', 'new'];
    $result = ['mode' => $mode, 'household_id' => null, 'relationship' => null, 'new' => ['household_no' => '', 'address' => '', 'purok' => '']];
    $errors = [];
    if (!in_array($mode, $allowed, true)) return ['values' => $result, 'errors' => ['household_mode' => 'Select a household option.']];
    if ($mode === 'later') return ['values' => $result, 'errors' => []];

    $relationship = residents_collapse($input['relationship_to_head'] ?? '');
    if ($relationship !== '' && !in_array($relationship, residents_relationships(), true)) $errors['relationship_to_head'] = 'Select a valid relationship.';
    $result['relationship'] = $relationship === '' ? null : $relationship;

    if ($mode === 'existing') {
        $household_id = filter_var($input['household_id'] ?? null, FILTER_VALIDATE_INT);
        $statement = $connection->prepare('SELECT id FROM households WHERE id = :id');
        $statement->execute(['id' => $household_id ?: 0]);
        if (!$household_id || !$statement->fetchColumn()) $errors['household_id'] = 'Select an existing household.';
        $result['household_id'] = $household_id ?: null;
    } else {
        $new = ['household_no' => residents_collapse($input['new_household_no'] ?? ''), 'address' => trim((string) ($input['new_household_address'] ?? '')), 'purok' => residents_collapse($input['new_household_purok'] ?? '')];
        if ($new['household_no'] === '' || mb_strlen($new['household_no']) > 50 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9\-\/ ]*$/', $new['household_no'])) $errors['new_household_no'] = 'Enter a household number of up to 50 letters, digits, hyphens or slashes.';
        else {
            $statement = $connection->prepare('SELECT id FROM households WHERE household_no = :household_no');
            $statement->execute(['household_no' => $new['household_no']]);
            if ($statement->fetchColumn()) $errors['new_household_no'] = 'This household number is already registered.';
        }
        if ($new['address'] === '' || mb_strlen($new['address']) > 500) $errors['new_household_address'] = 'Household address is required (up to 500 characters).';
        if (($purok_error = residents_purok_error($new['purok'], null, 'Household Purok')) !== null) $errors['new_household_purok'] = $purok_error;
        $result['new'] = $new;
    }
    return ['values' => $result, 'errors' => $errors];
}

function residents_current_membership(PDO $connection, int $resident_id, bool $lock = false): ?array
{
    $statement = $connection->prepare('SELECT rh.resident_id, rh.household_id, rh.relationship_to_head, rh.joined_at, h.household_no, h.address, h.purok, h.household_head_resident_id, hr.first_name, hr.middle_name, hr.last_name, hr.suffix FROM resident_households rh INNER JOIN households h ON h.id = rh.household_id LEFT JOIN residents hr ON hr.id = h.household_head_resident_id WHERE rh.resident_id = :resident_id AND rh.is_primary = 1 AND rh.left_at IS NULL LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['resident_id' => $resident_id]);
    $row = $statement->fetch();
    return $row ?: null;
}

function residents_membership_history(PDO $connection, int $resident_id): array
{
    $statement = $connection->prepare('SELECT rh.household_id, rh.relationship_to_head, rh.is_primary, rh.joined_at, rh.left_at, h.household_no FROM resident_households rh INNER JOIN households h ON h.id = rh.household_id WHERE rh.resident_id = :resident_id AND NOT (rh.is_primary = 1 AND rh.left_at IS NULL) ORDER BY rh.joined_at DESC, rh.created_at DESC');
    $statement->execute(['resident_id' => $resident_id]);
    return $statement->fetchAll();
}

// Current, Active primary members: the only residents eligible to be designated Household Head.
function residents_household_members(PDO $connection, int $household_id): array
{
    $statement = $connection->prepare("SELECT r.id, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.sex, r.status, rh.relationship_to_head, rh.joined_at FROM resident_households rh INNER JOIN residents r ON r.id = rh.resident_id WHERE rh.household_id = :household_id AND rh.is_primary = 1 AND rh.left_at IS NULL ORDER BY r.last_name, r.first_name, r.id");
    $statement->execute(['household_id' => $household_id]);
    return $statement->fetchAll();
}

function residents_is_current_active_member(PDO $connection, int $household_id, int $resident_id): bool
{
    $statement = $connection->prepare("SELECT 1 FROM resident_households rh INNER JOIN residents r ON r.id = rh.resident_id WHERE rh.household_id = :household_id AND rh.resident_id = :resident_id AND rh.is_primary = 1 AND rh.left_at IS NULL AND r.status = 'active'");
    $statement->execute(['household_id' => $household_id, 'resident_id' => $resident_id]);
    return (bool) $statement->fetchColumn();
}

// Creates the household when requested, then opens the resident's current primary membership. Must run inside the caller's transaction.
// resident_households has a (resident_id, household_id) primary key, so rejoining a household the resident previously left would overwrite
// that history row; this is refused instead.
function residents_open_membership(PDO $connection, int $resident_id, array $household): int
{
    if ($household['mode'] === 'new') {
        $insert = $connection->prepare('INSERT INTO households (household_no, address, purok) VALUES (:household_no, :address, :purok)');
        $insert->execute($household['new']);
        $household_id = (int) $connection->lastInsertId();
        residents_audit($connection, 'household', $household_id, 'household_created', ['household_no' => $household['new']['household_no'], 'first_member_resident_id' => $resident_id]);
    } else {
        $household_id = (int) $household['household_id'];
        $lock = $connection->prepare('SELECT id FROM households WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $household_id]);
        if (!$lock->fetchColumn()) throw new RuntimeException('The selected household no longer exists.');
    }
    $previous = $connection->prepare('SELECT 1 FROM resident_households WHERE resident_id = :resident_id AND household_id = :household_id');
    $previous->execute(['resident_id' => $resident_id, 'household_id' => $household_id]);
    if ($previous->fetchColumn()) throw new RuntimeException('This resident already has a membership record for that household. Rejoining a previous household is not supported without overwriting its history.');
    $membership = $connection->prepare('INSERT INTO resident_households (resident_id, household_id, relationship_to_head, is_primary, joined_at) VALUES (:resident_id, :household_id, :relationship, 1, :joined_at)');
    $membership->execute(['resident_id' => $resident_id, 'household_id' => $household_id, 'relationship' => $household['relationship'], 'joined_at' => date('Y-m-d')]);
    return $household_id;
}

// Closes the current primary membership (history is kept) and clears any Household Head designation held by the resident.
function residents_close_membership(PDO $connection, int $resident_id, string $left_at): array
{
    $membership = residents_current_membership($connection, $resident_id, true);
    $closed = null;
    if ($membership !== null) {
        if ($membership['joined_at'] !== null && $left_at < $membership['joined_at']) throw new RuntimeException('The effective date cannot be earlier than the date the resident joined the household (' . residents_format_date($membership['joined_at']) . ').');
        $close = $connection->prepare('UPDATE resident_households SET left_at = :left_at WHERE resident_id = :resident_id AND household_id = :household_id AND is_primary = 1 AND left_at IS NULL');
        $close->execute(['left_at' => $left_at, 'resident_id' => $resident_id, 'household_id' => $membership['household_id']]);
        $closed = (int) $membership['household_id'];
    }
    $heads = $connection->prepare('SELECT id FROM households WHERE household_head_resident_id = :resident_id FOR UPDATE');
    $heads->execute(['resident_id' => $resident_id]);
    $cleared = array_map('intval', $heads->fetchAll(PDO::FETCH_COLUMN));
    if ($cleared !== []) {
        $clear = $connection->prepare('UPDATE households SET household_head_resident_id = NULL WHERE household_head_resident_id = :resident_id');
        $clear->execute(['resident_id' => $resident_id]);
    }
    return ['closed_household_id' => $closed, 'cleared_head_household_ids' => $cleared];
}

function residents_audit(PDO $connection, string $entity_type, int $id, string $action, array $details = []): void
{
    $statement = $connection->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, details) VALUES (:user_id, :action, :type, :id, :ip, :agent, :details)');
    $statement->execute(['user_id' => current_user()['id'], 'action' => $action, 'type' => $entity_type, 'id' => $id, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null, 'agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500), 'details' => $details === [] ? '{}' : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
}

function residents_status_badge(string $status): string
{
    return '<span class="resident-status resident-status-' . e($status) . '">' . e(residents_status_labels()[$status] ?? ucfirst($status)) . '</span>';
}
