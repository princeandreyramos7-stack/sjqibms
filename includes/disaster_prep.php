<?php
declare(strict_types=1);

require_once __DIR__ . '/disaster.php';

// Disaster Management — preparedness (migration 20260930_disaster_preparedness): evacuation centers, hazard-prone
// areas, BDRRMC members and emergency hotlines. Vulnerable Residents is read-only from the Residents module.

function disaster_prep_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $check = $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('drr_areas', 'drr_evacuation_centers', 'drr_hazard_areas', 'drr_contacts')");
        $ready = (int) $check->fetchColumn() === 4;
    }
    return $ready;
}

// Records kept by this file: table, list page, and the words used in messages.
function disaster_prep_kinds(): array
{
    return [
        'center' => ['table' => 'drr_evacuation_centers', 'list' => 'disaster_centers.php', 'noun' => 'evacuation center', 'audit' => 'disaster_center'],
        'hazard' => ['table' => 'drr_hazard_areas', 'list' => 'disaster_hazards.php', 'noun' => 'hazard-prone area', 'audit' => 'disaster_hazard'],
        'contact' => ['table' => 'drr_contacts', 'list' => 'disaster_contacts.php', 'noun' => 'contact', 'audit' => 'disaster_contact'],
    ];
}

// Module tabs shown on every Disaster Management list page.
// Four tabs. Evacuees and damage belong to an incident and are opened from it (they show under Incidents); the Risk
// Map holds the hazard-prone areas and the vulnerable residents per Purok. Alerts were removed (Announcements are used).
function disaster_tabs(string $active): string
{
    $active = ['evacuation' => 'records', 'damage' => 'records', 'hazards' => 'risk', 'vulnerable' => 'risk'][$active] ?? $active;
    $tabs = ['records' => ['disaster.php', 'Incidents'], 'risk' => ['disaster_hazards.php', 'Risk Map'], 'centers' => ['disaster_centers.php', 'Evacuation Centers'], 'contacts' => ['disaster_contacts.php', 'BDRRMC & Hotlines']];
    $html = '<nav class="announcement-tabs page-tabs drr-tabs" aria-label="Disaster Management sections">';
    foreach ($tabs as $key => [$href, $label]) $html .= '<a class="announcement-tab' . ($key === $active ? ' active" aria-current="page' : '') . '" href="' . e($href) . '">' . e($label) . '</a>';
    return $html . '</nav>';
}

// ── Labels ─────────────────────────────────────────────────────────────────────

function disaster_center_statuses(): array
{
    return ['open' => 'Open', 'closed' => 'Closed', 'full' => 'Full'];
}

function disaster_center_status_badge(string $status): string
{
    $tone = ['open' => 'active', 'closed' => 'inactive', 'full' => 'deceased'][$status] ?? 'inactive';
    return '<span class="resident-status resident-status-' . e($tone) . '">' . e(disaster_center_statuses()[$status] ?? ucfirst($status)) . '</span>';
}

function disaster_facilities(): array
{
    return ['has_toilets' => 'Toilets', 'has_water' => 'Water', 'has_electricity' => 'Electricity', 'has_kitchen' => 'Kitchen'];
}

function disaster_hazard_types(): array
{
    return ['flood' => 'Flood', 'landslide' => 'Landslide', 'storm_surge' => 'Storm surge', 'fire' => 'Fire', 'other' => 'Other'];
}

function disaster_hazard_label(array $hazard): string
{
    return $hazard['hazard_type'] === 'other' && $hazard['hazard_other'] ? 'Other — ' . $hazard['hazard_other'] : (disaster_hazard_types()[$hazard['hazard_type']] ?? $hazard['hazard_type']);
}

function disaster_risk_levels(): array
{
    return ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
}

function disaster_risk_badge(string $level): string
{
    return '<span class="drr-risk drr-risk-' . e($level) . '">' . e(disaster_risk_levels()[$level] ?? ucfirst($level)) . '</span>';
}

function disaster_member_roles(): array
{
    return ['rescue' => 'Rescue', 'relief' => 'Relief', 'medical' => 'Medical', 'communication' => 'Communication', 'security' => 'Security', 'other' => 'Other'];
}

function disaster_hotline_categories(): array
{
    return ['mdrrmo' => 'MDRRMO', 'bfp' => 'Fire (BFP)', 'pnp' => 'Police (PNP)', 'hospital' => 'Hospital / Health', 'other' => 'Other'];
}

// ── Shared lists for the hotline poster and the residents' Disaster Info page ─────────────────────────────────
// Current (not archived) evacuation centers, open first, then full, then closed.
function disaster_current_centers(PDO $connection): array
{
    return $connection->query("SELECT c.*, a.name AS area_name FROM drr_evacuation_centers c LEFT JOIN drr_areas a ON a.id = c.area_id WHERE c.archived_at IS NULL ORDER BY FIELD(c.status, 'open', 'full', 'closed'), c.name")->fetchAll();
}

// Current (not archived) emergency hotlines by category.
function disaster_current_hotlines(PDO $connection): array
{
    return $connection->query("SELECT id, name, hotline_category, contact_number, alternate_number, notes FROM drr_contacts WHERE contact_type = 'hotline' AND archived_at IS NULL ORDER BY FIELD(hotline_category, 'mdrrmo', 'bfp', 'pnp', 'hospital', 'other'), name")->fetchAll();
}

// A number as a tap-to-call link (digits and a leading + only in the href).
function disaster_tel_link(?string $number): string
{
    $number = trim((string) $number);
    if ($number === '') return '';
    $dial = preg_replace('/(?!^\+)[^\d]/', '', $number) ?? '';
    return '<a class="activity-detail-link" href="tel:' . e($dial) . '">' . e($number) . '</a>';
}

// ── Shared validation ──────────────────────────────────────────────────────────

// Phone numbers: mobile (09XX XXX XXXX, +63…), landline ((078) 123-4567) or short hotlines (911). 3–15 digits.
function disaster_phone_error(string $value, bool $required, string $label = 'contact number'): ?string
{
    if ($value === '') return $required ? 'Enter the ' . $label . '.' : null;
    $digits = preg_replace('/\D/', '', $value) ?? '';
    if (!preg_match('/^[0-9+(][0-9()\-\s]*$/', $value) || strlen($digits) < 3 || strlen($digits) > 15 || mb_strlen($value) > 30) return 'Enter a valid ' . $label . ' (digits, spaces, dashes, parentheses; e.g. 0917 123 4567 or 911).';
    return null;
}

function disaster_person_name_error(string $value, bool $required, string $label = 'name'): ?string
{
    if ($value === '') return $required ? 'Enter the ' . $label . '.' : null;
    if (mb_strlen($value) < 2 || mb_strlen($value) > 150 || !preg_match("/^\p{L}[\p{L}\p{M}\s.,'\-]*$/u", $value)) return 'Enter the ' . $label . ' (letters only, 2 to 150 characters).';
    return null;
}

function disaster_optional_count(string $value, int $max, string $message): array
{
    if ($value === '') return [null, null];
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $max]]);
    return $number === false ? [$value, $message] : [$number, null];
}

// Area chosen on a form: must exist and be active, unless it is the one already on the record.
function disaster_area_error(PDO $connection, string $value, ?int $current): array
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $area = $id ? disaster_area($connection, $id) : null;
    if ($area === null) return [$value, 'Select an area.'];
    if ((int) $area['is_active'] !== 1 && $current !== (int) $id) return [$value, 'This area is no longer in use. Select another area.'];
    return [(int) $id, null];
}

// ── Finding records ────────────────────────────────────────────────────────────

function disaster_prep_find(PDO $connection, string $kind, int $id, bool $lock = false): ?array
{
    $table = disaster_prep_kinds()[$kind]['table'] ?? null;
    if ($table === null) return null;
    $join = $kind === 'contact' ? '' : ' INNER JOIN drr_areas a ON a.id = t.area_id';
    $statement = $connection->prepare('SELECT t.*' . ($kind === 'contact' ? '' : ', a.name AS area_name') . ", cu.name AS created_by_name, uu.name AS updated_by_name FROM $table t$join LEFT JOIN users cu ON cu.id = t.created_by LEFT JOIN users uu ON uu.id = t.updated_by WHERE t.id = :id LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function disaster_prep_audit(PDO $connection, string $kind, int $id, string $action, array $details = []): void
{
    residents_audit($connection, disaster_prep_kinds()[$kind]['audit'], $id, $action, $details);
}

// Saves a new or edited center / hazard / contact inside a transaction. Editing re-reads the row with a lock and refuses
// when it changed after the form was opened (updated_at). Returns [id, message]; throws RuntimeException or PDOException.
function disaster_prep_save(PDO $connection, string $kind, array $fields, array $values, ?array $record, string $label): array
{
    $kinds = disaster_prep_kinds();
    $table = $kinds[$kind]['table'];
    $user = current_user()['id'];
    $params = array_intersect_key($values, array_flip($fields));
    $connection->beginTransaction();
    try {
        if ($record !== null) {
            $locked = disaster_prep_find($connection, $kind, (int) $record['id'], true);
            if (!$locked || $locked['updated_at'] !== $record['updated_at'] || $locked['archived_at'] !== null) throw new RuntimeException('This record was changed by another action after you opened it. Reload the page to review the latest information.');
            $changed = array_values(array_filter($fields, static fn (string $f): bool => (string) ($values[$f] ?? '') !== (string) ($locked[$f] ?? '')));
            if ($changed !== []) {
                $set = implode(', ', array_map(static fn (string $f): string => "$f = :$f", $fields));
                $connection->prepare("UPDATE $table SET $set, updated_by = :user WHERE id = :id")->execute($params + ['user' => $user, 'id' => $record['id']]);
                disaster_prep_audit($connection, $kind, (int) $record['id'], $kinds[$kind]['audit'] . '_updated', ['name' => $label, 'changed_fields' => $changed]);
            }
            $result = [(int) $record['id'], $changed === [] ? 'No changes were made.' : $label . ' was updated.'];
        } else {
            $columns = implode(', ', $fields);
            $placeholders = implode(', ', array_map(static fn (string $f): string => ":$f", $fields));
            $connection->prepare("INSERT INTO $table ($columns, created_by, updated_by) VALUES ($placeholders, :user, :updater)")->execute($params + ['user' => $user, 'updater' => $user]);
            $id = (int) $connection->lastInsertId();
            disaster_prep_audit($connection, $kind, $id, $kinds[$kind]['audit'] . '_created', ['name' => $label]);
            $result = [$id, $label . ' was added.'];
        }
        $connection->commit();
        return $result;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

// ── Evacuation centers ─────────────────────────────────────────────────────────

function disaster_center_fields(): array
{
    return ['name', 'address', 'area_id', 'capacity', 'has_toilets', 'has_water', 'has_electricity', 'has_kitchen', 'contact_person', 'contact_number', 'status'];
}

function disaster_center_validate(PDO $connection, array $input, ?array $existing = null): array
{
    $values = [
        'name' => residents_collapse($input['name'] ?? ''),
        'address' => residents_collapse($input['address'] ?? ''),
        'area_id' => trim((string) ($input['area_id'] ?? '')),
        'capacity' => trim((string) ($input['capacity'] ?? '')),
        'contact_person' => residents_collapse($input['contact_person'] ?? ''),
        'contact_number' => residents_collapse($input['contact_number'] ?? ''),
        'status' => (string) ($input['status'] ?? ''),
    ];
    foreach (array_keys(disaster_facilities()) as $facility) $values[$facility] = (string) ($input[$facility] ?? '') === '1' ? 1 : 0;
    $errors = [];
    if (mb_strlen($values['name']) < 3 || mb_strlen($values['name']) > 150) $errors['name'] = 'Enter the center name (3 to 150 characters).';
    if (mb_strlen($values['address']) < 3 || mb_strlen($values['address']) > 255) $errors['address'] = 'Enter the address (3 to 255 characters).';
    [$values['area_id'], $area_error] = disaster_area_error($connection, $values['area_id'], isset($existing['area_id']) ? (int) $existing['area_id'] : null);
    if ($area_error) $errors['area_id'] = $area_error;
    $capacity = filter_var($values['capacity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
    if ($capacity === false) $errors['capacity'] = 'Enter the capacity in persons (1 to 10,000).';
    else $values['capacity'] = $capacity;
    if ($error = disaster_person_name_error($values['contact_person'], false, 'contact person\'s name')) $errors['contact_person'] = $error;
    if ($error = disaster_phone_error($values['contact_number'], false)) $errors['contact_number'] = $error;
    if (!array_key_exists($values['status'], disaster_center_statuses())) $errors['status'] = 'Select a status.';
    foreach (['contact_person', 'contact_number'] as $nullable) if ($values[$nullable] === '') $values[$nullable] = null;
    return ['values' => $values, 'errors' => $errors];
}

// ── Hazard-prone areas ─────────────────────────────────────────────────────────

function disaster_hazard_fields(): array
{
    return ['area_id', 'hazard_type', 'hazard_other', 'risk_level', 'families_at_risk', 'notes'];
}

function disaster_hazard_validate(PDO $connection, array $input, ?array $existing = null): array
{
    $values = [
        'area_id' => trim((string) ($input['area_id'] ?? '')),
        'hazard_type' => (string) ($input['hazard_type'] ?? ''),
        'hazard_other' => residents_collapse($input['hazard_other'] ?? ''),
        'risk_level' => (string) ($input['risk_level'] ?? ''),
        'families_at_risk' => trim((string) ($input['families_at_risk'] ?? '')),
        'notes' => trim((string) ($input['notes'] ?? '')),
    ];
    $errors = [];
    [$values['area_id'], $area_error] = disaster_area_error($connection, $values['area_id'], isset($existing['area_id']) ? (int) $existing['area_id'] : null);
    if ($area_error) $errors['area_id'] = $area_error;
    if (!array_key_exists($values['hazard_type'], disaster_hazard_types())) $errors['hazard_type'] = 'Select a hazard type.';
    elseif ($values['hazard_type'] === 'other' && (mb_strlen($values['hazard_other']) < 3 || mb_strlen($values['hazard_other']) > 100)) $errors['hazard_other'] = 'Describe the hazard when "Other" is selected (3 to 100 characters).';
    if ($values['hazard_type'] !== 'other') $values['hazard_other'] = '';
    if (!array_key_exists($values['risk_level'], disaster_risk_levels())) $errors['risk_level'] = 'Select a risk level.';
    [$values['families_at_risk'], $count_error] = disaster_optional_count($values['families_at_risk'], 10000, 'Enter the number of families at risk (0 to 10,000).');
    if ($count_error) $errors['families_at_risk'] = $count_error;
    if (mb_strlen($values['notes']) > 1000) $errors['notes'] = 'Notes must not exceed 1,000 characters.';
    // The same hazard is recorded once per area (archived entries do not count).
    if (!isset($errors['area_id']) && !isset($errors['hazard_type']) && !isset($errors['hazard_other'])) {
        $statement = $connection->prepare('SELECT COUNT(*) FROM drr_hazard_areas WHERE area_id = :area AND hazard_type = :type AND COALESCE(hazard_other, \'\') = :other AND archived_at IS NULL AND id <> :id');
        $statement->execute(['area' => $values['area_id'], 'type' => $values['hazard_type'], 'other' => $values['hazard_other'], 'id' => (int) ($existing['id'] ?? 0)]);
        if ((int) $statement->fetchColumn() > 0) $errors['hazard_type'] = 'This hazard is already recorded for this area. Edit the existing entry instead.';
    }
    foreach (['hazard_other', 'notes'] as $nullable) if ($values[$nullable] === '') $values[$nullable] = null;
    return ['values' => $values, 'errors' => $errors];
}

// ── BDRRMC members and hotlines ────────────────────────────────────────────────

function disaster_contact_fields(): array
{
    return ['contact_type', 'name', 'member_role', 'hotline_category', 'position', 'contact_number', 'alternate_number', 'notes'];
}

function disaster_contact_validate(array $input): array
{
    $values = [
        'contact_type' => (string) ($input['contact_type'] ?? ''),
        'name' => residents_collapse($input['name'] ?? ''),
        'member_role' => (string) ($input['member_role'] ?? ''),
        'hotline_category' => (string) ($input['hotline_category'] ?? ''),
        'position' => residents_collapse($input['position'] ?? ''),
        'contact_number' => residents_collapse($input['contact_number'] ?? ''),
        'alternate_number' => residents_collapse($input['alternate_number'] ?? ''),
        'notes' => residents_collapse($input['notes'] ?? ''),
    ];
    $errors = [];
    if (!in_array($values['contact_type'], ['member', 'hotline'], true)) $errors['contact_type'] = 'Select Member or Hotline.';
    if ($values['contact_type'] === 'member') {
        if ($error = disaster_person_name_error($values['name'], true, 'member\'s name')) $errors['name'] = $error;
        if (!array_key_exists($values['member_role'], disaster_member_roles())) $errors['member_role'] = 'Select the member\'s role.';
        $values['hotline_category'] = '';
    } else {
        if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 150) $errors['name'] = 'Enter the office or hotline name (2 to 150 characters).';
        if ($values['contact_type'] === 'hotline' && !array_key_exists($values['hotline_category'], disaster_hotline_categories())) $errors['hotline_category'] = 'Select a category.';
        $values['member_role'] = '';
    }
    if (mb_strlen($values['position']) > 100) $errors['position'] = 'Keep the position to 100 characters.';
    if ($error = disaster_phone_error($values['contact_number'], true)) $errors['contact_number'] = $error;
    if ($error = disaster_phone_error($values['alternate_number'], false, 'alternate number')) $errors['alternate_number'] = $error;
    if (mb_strlen($values['notes']) > 255) $errors['notes'] = 'Keep the notes to 255 characters.';
    foreach (['member_role', 'hotline_category', 'position', 'alternate_number', 'notes'] as $nullable) if ($values[$nullable] === '') $values[$nullable] = null;
    return ['values' => $values, 'errors' => $errors];
}

// ── Vulnerable residents (read-only from Residents) ────────────────────────────
// Only what the Residents module records can be used: senior citizens and children under 5 come from the birth date;
// PWD and solo parents come from the checkboxes on the resident profile (once migration 20261007_resident_pwd_solo_parent
// is applied); the "Priority" group comes from the Health records (disaster_priority_visible()). A resident can be in more
// than one group (for example a senior citizen who is also a PWD) and is then listed once with every group shown.

function disaster_vulnerable_groups(): array
{
    $groups = ['senior' => 'Senior citizen (60+)', 'under5' => 'Child under 5'];
    if (residents_sector_ready(db())) $groups += ['pwd' => 'PWD (person with disability)', 'solo_parent' => 'Solo parent'];
    if (disaster_priority_visible()) $groups['priority'] = disaster_priority_reason_visible() ? 'Pregnant' : 'Priority';
    // Serious chronic illness is NOT listed here: it stays with the Health Workers (owner's decision, 2026-10-04).
    return $groups;
}

// "Priority": residents who are pregnant, inferred from the Health module (a prenatal visit in the last 280 days with no
// postnatal visit after it), so they are included in evacuation and relief planning. Pregnancy is sensitive personal
// information (Data Privacy Act), so the reason is shown only to Health Workers (owner's decision, 2026-10-07); everyone
// else who may see vulnerable residents' names (not the Treasurer) sees only "Priority", and the group key, filter value
// and badge class never name it. No health record or detail is ever shown here.
function disaster_priority_visible(): bool
{
    static $ready = null;
    if ($ready === null) $ready = (int) db()->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'health_records'")->fetchColumn() === 1;
    return $ready && disaster_can_view_vulnerable();
}

function disaster_priority_reason_visible(): bool
{
    return has_role('health_worker');
}

// Short labels for badges and table cells.
function disaster_vulnerable_short_label(string $group): string
{
    return ['senior' => 'Senior', 'under5' => 'Under 5', 'pwd' => 'PWD', 'solo_parent' => 'Solo Parent', 'priority' => disaster_priority_reason_visible() ? 'Pregnant' : 'Priority'][$group] ?? $group;
}

// SQL condition for a group on residents r (dates computed here from today's date, quoted).
function disaster_vulnerable_condition(PDO $connection, string $group): string
{
    $today = new DateTimeImmutable('today');
    return match ($group) {
        'senior' => 'r.birth_date <= ' . $connection->quote($today->modify('-60 years')->format('Y-m-d')),
        'under5' => 'r.birth_date > ' . $connection->quote($today->modify('-5 years')->format('Y-m-d')) . ' AND r.birth_date <= ' . $connection->quote($today->format('Y-m-d')),
        'pwd' => 'r.is_pwd = 1',
        'solo_parent' => 'r.is_solo_parent = 1',
        'priority' => "EXISTS (SELECT 1 FROM health_records hp WHERE hp.resident_id = r.id AND hp.service = 'prenatal' AND hp.archived_at IS NULL AND hp.status <> 'cancelled' AND hp.service_date > " . $connection->quote($today->modify('-280 days')->format('Y-m-d')) . ' AND hp.service_date <= ' . $connection->quote($today->format('Y-m-d'))
            . " AND NOT EXISTS (SELECT 1 FROM health_records pn WHERE pn.resident_id = r.id AND pn.service = 'postnatal' AND pn.archived_at IS NULL AND pn.status <> 'cancelled' AND pn.service_date >= hp.service_date))",
    };
}

function disaster_vulnerable_state(array $input): array
{
    $state = [
        'q' => mb_substr(residents_collapse((string) ($input['q'] ?? '')), 0, 100),
        'purok' => residents_collapse((string) ($input['purok'] ?? '')),
        'group' => (string) ($input['group'] ?? ''),
    ];
    if (!array_key_exists($state['group'], disaster_vulnerable_groups())) $state['group'] = '';
    if (mb_strlen($state['purok']) > 80) $state['purok'] = '';
    return $state;
}

// Active residents in the chosen groups, ordered by Purok then name. Each row carries 'vulnerable_groups' (every group
// the resident belongs to, in the order of disaster_vulnerable_groups()).
function disaster_vulnerable_rows(PDO $connection, array $state): array
{
    $conditions = [];
    foreach (array_keys(disaster_vulnerable_groups()) as $group) $conditions[$group] = '(' . disaster_vulnerable_condition($connection, $group) . ')';
    $groups = $state['group'] === '' ? '(' . implode(' OR ', $conditions) . ')' : $conditions[$state['group']];
    $flags = implode(', ', array_map(static fn (string $group, string $condition): string => "($condition) AS g_$group", array_keys($conditions), $conditions));
    $where = ["r.status = 'active'", $groups];
    $params = [];
    if ($state['purok'] !== '') { $where[] = 'r.purok = :purok'; $params['purok'] = $state['purok']; }
    if ($state['q'] !== '') {
        $like = '%' . addcslashes($state['q'], '%_\\') . '%';
        $where[] = "(CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name, r.suffix) LIKE :q1 OR CONCAT_WS(' ', r.first_name, r.last_name) LIKE :q2 OR r.address LIKE :q3)";
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
    }
    $statement = $connection->prepare("SELECT r.id, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.sex, r.purok, r.address, r.contact_number, $flags, h.household_no FROM residents r LEFT JOIN resident_households rh ON rh.resident_id = r.id AND rh.is_primary = 1 AND rh.left_at IS NULL LEFT JOIN households h ON h.id = rh.household_id WHERE " . implode(' AND ', $where) . ' ORDER BY r.purok, r.last_name, r.first_name, r.id');
    $statement->execute($params);
    $rows = $statement->fetchAll();
    foreach ($rows as &$row) {
        $row['vulnerable_groups'] = array_values(array_filter(array_keys($conditions), static fn (string $group): bool => (int) $row['g_' . $group] === 1));
    }
    unset($row);
    return $rows;
}

// How many listed residents are in each group (a resident in two groups counts in both).
function disaster_vulnerable_counts(array $rows): array
{
    $counts = array_fill_keys(array_keys(disaster_vulnerable_groups()), 0);
    foreach ($rows as $row) foreach ($row['vulnerable_groups'] as $group) $counts[$group]++;
    return $counts;
}

// "5 senior citizens · 3 children under 5 · 2 PWD · 1 solo parent"
function disaster_vulnerable_summary(array $counts): string
{
    $words = ['senior' => ['senior citizen', 'senior citizens'], 'under5' => ['child under 5', 'children under 5'], 'pwd' => ['PWD', 'PWD'], 'solo_parent' => ['solo parent', 'solo parents'], 'priority' => disaster_priority_reason_visible() ? ['pregnant', 'pregnant'] : ['priority', 'priority']];
    $parts = [];
    foreach ($counts as $group => $count) $parts[] = $count . ' ' . $words[$group][$count === 1 ? 0 : 1];
    return implode(' · ', $parts);
}
// Age for the list: months for babies under 1 year, otherwise completed years.
function disaster_age_label(?string $birth_date): string
{
    $age = residents_age($birth_date);
    if ($age === null) return '—';
    if ($age > 0) return $age . ' yr' . ($age === 1 ? '' : 's');
    $months = (int) (new DateTimeImmutable((string) $birth_date))->diff(new DateTimeImmutable('today'))->format('%m');
    return $months . ' mo' . ($months === 1 ? '' : 's');
}

// Puroks offered in the filter: Purok 1–4 plus any other Purok still stored on an active resident's profile.
function disaster_vulnerable_puroks(PDO $connection): array
{
    $puroks = array_map('strval', array_keys(residents_purok_options()));
    foreach ($connection->query("SELECT DISTINCT purok FROM residents WHERE status = 'active' AND purok <> ''")->fetchAll(PDO::FETCH_COLUMN) as $value) if (!in_array((string) $value, $puroks, true)) $puroks[] = (string) $value;
    return $puroks;
}

// Rows grouped by Purok label, with the count of each group inside it.
function disaster_vulnerable_grouped(array $rows): array
{
    $groups = [];
    foreach ($rows as $row) {
        $label = residents_purok_label((string) $row['purok']);
        $groups[$label] ??= ['rows' => []];
        $groups[$label]['rows'][] = $row;
    }
    foreach ($groups as &$group) $group['counts'] = disaster_vulnerable_counts($group['rows']);
    unset($group);
    return $groups;
}
// ── Risk Map: per Purok ───────────────────────────────────────────────────────
// The Purok a DRR area stands for: "Purok 3" → '3', "All Puroks" → '*', anything else (a building) → null.
function disaster_area_purok(string $area_name): ?string
{
    if (preg_match('/^Purok\s+(\S+)$/i', trim($area_name), $match)) return $match[1];
    return strcasecmp(trim($area_name), 'All Puroks') === 0 ? '*' : null;
}

// For Purok 1–4: families (households with at least one active member, from Households), the vulnerable residents
// by group (from Residents) and the current hazards recorded for that Purok or for All Puroks. Nothing is typed in.
function disaster_risk_by_purok(PDO $connection): array
{
    $puroks = [];
    foreach (residents_purok_options() as $value => $label) $puroks[(string) $value] = ['label' => $label, 'families' => 0, 'vulnerable' => array_fill_keys(array_keys(disaster_vulnerable_groups()), 0), 'vulnerable_total' => 0, 'hazards' => []];
    $statement = $connection->query("SELECT h.purok, COUNT(DISTINCT h.id) FROM households h INNER JOIN resident_households rh ON rh.household_id = h.id AND rh.is_primary = 1 AND rh.left_at IS NULL INNER JOIN residents r ON r.id = rh.resident_id AND r.status = 'active' GROUP BY h.purok");
    foreach ($statement->fetchAll(PDO::FETCH_NUM) as [$purok, $count]) if (isset($puroks[(string) $purok])) $puroks[(string) $purok]['families'] = (int) $count;
    foreach (disaster_vulnerable_rows($connection, ['q' => '', 'purok' => '', 'group' => '']) as $row) {
        $purok = (string) $row['purok'];
        if (!isset($puroks[$purok])) continue;
        $puroks[$purok]['vulnerable_total']++;
        foreach ($row['vulnerable_groups'] as $group) $puroks[$purok]['vulnerable'][$group]++;
    }
    $hazards = $connection->query("SELECT h.*, a.name AS area_name FROM drr_hazard_areas h INNER JOIN drr_areas a ON a.id = h.area_id WHERE h.archived_at IS NULL ORDER BY FIELD(h.risk_level, 'high', 'medium', 'low'), h.hazard_type")->fetchAll();
    foreach ($hazards as $hazard) {
        $purok = disaster_area_purok((string) $hazard['area_name']);
        foreach ($puroks as $key => &$entry) if ($purok === '*' || $purok === $key) $entry['hazards'][] = $hazard;
        unset($entry);
    }
    return $puroks;
}

// Families at risk for a hazard row: the families of its Purok (all Puroks for "All Puroks"); null for a building.
function disaster_hazard_families(array $risk, string $area_name): ?int
{
    $purok = disaster_area_purok($area_name);
    if ($purok === null) return null;
    if ($purok === '*') return array_sum(array_column($risk, 'families'));
    return $risk[$purok]['families'] ?? null;
}

// Editable header of the printouts (templates/disaster/report_settings.php).
function disaster_report_settings(): array
{
    $settings = require __DIR__ . '/../templates/disaster/report_settings.php';
    $defaults = ['header_lines' => [], 'barangay_name' => '', 'office' => '', 'address' => '', 'logo' => '', 'records_title' => 'DISASTER RISK REDUCTION RECORDS', 'incident_title' => 'INCIDENT REPORT', 'incident_intro' => '', 'signatories' => []];
    $settings = is_array($settings) ? array_merge($defaults, $settings) : $defaults;
    if ($settings['logo'] !== '' && !is_file(__DIR__ . '/../' . $settings['logo'])) $settings['logo'] = '';
    return $settings;
}

// Active residents whose birth date is missing (they cannot be classified by age).
function disaster_vulnerable_unknown_count(PDO $connection): int
{
    return (int) $connection->query("SELECT COUNT(*) FROM residents WHERE status = 'active' AND (birth_date IS NULL OR birth_date > CURDATE())")->fetchColumn();
}
