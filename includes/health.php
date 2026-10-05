<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/residents.php';

// Health service records (health_records; migration 20260928_health_records). Each record belongs to a registered
// resident. Access follows the sidebar item: Health Workers only (health records are confidential, even from the System
// Administrator and the Secretary), within their assigned Puroks. Records are archived, never deleted.

function health_can_manage(): bool
{
    return can_access_navigation('health');
}

function health_require_manage(): void
{
    require_auth();
    if (!health_can_manage()) { http_response_code(403); exit('Access denied.'); }
}

function health_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $check = $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'health_records'");
        $ready = (int) $check->fetchColumn() === 1;
    }
    return $ready;
}

function health_services(): array
{
    return ['check_up' => 'Check-up', 'prenatal' => 'Prenatal', 'postnatal' => 'Postnatal', 'immunization' => 'Immunization', 'bp_monitoring' => 'BP Monitoring', 'blood_sugar_monitoring' => 'Blood Sugar Monitoring', 'deworming' => 'Deworming', 'family_planning' => 'Family Planning', 'tb_dots_monitoring' => 'TB-DOTS Monitoring', 'other' => 'Other'];
}

function health_statuses(): array
{
    return ['scheduled' => 'Scheduled', 'completed' => 'Completed', 'follow_up' => 'Follow-up', 'cancelled' => 'Cancelled'];
}

// Badge colours reuse the existing tones (same as the former sample page).
function health_status_badge(string $status): string
{
    $tone = ['scheduled' => 'pending', 'completed' => 'active', 'follow_up' => 'moved', 'cancelled' => 'inactive'][$status] ?? 'inactive';
    return '<span class="resident-status resident-status-' . e($tone) . '">' . e(health_statuses()[$status] ?? ucfirst($status)) . '</span>';
}

function health_service_label(array $record): string
{
    $service = health_services()[$record['service']] ?? $record['service'];
    return $record['service_details'] ? $service . ' — ' . $record['service_details'] : $service;
}

function health_format_date(?string $value): string
{
    if (!$value) return '';
    $date = date_create($value);
    return $date ? $date->format('M j, Y') : $value;
}

// Phase 2 columns and tables (migration 20261017_health_records_phase2): vital signs, complaint, diagnosis, findings,
// medicines given (from Inventory) and referral to the RHU. Until then the form keeps its earlier fields.
function health_phase2_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $tables = (int) $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('health_conditions', 'health_record_medicines')")->fetchColumn();
        $column = (int) $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'health_records' AND COLUMN_NAME = 'condition_id'")->fetchColumn();
        $ready = $tables === 2 && $column === 1;
    }
    return $ready;
}

// Due today or overdue (SQL, for records aliased $alias):
//   * a Scheduled visit whose date has come, or
//   * a record with a follow-up date that has come (any status except Cancelled), until the resident has a later visit
//     (Completed or Follow-up) — that later visit closes the follow-up by itself.
function health_due_sql(string $alias): string
{
    return "($alias.archived_at IS NULL AND (($alias.status = 'scheduled' AND $alias.service_date <= CURDATE())"
        . " OR ($alias.status <> 'cancelled' AND $alias.follow_up_date IS NOT NULL AND $alias.follow_up_date <= CURDATE()"
        . " AND NOT EXISTS (SELECT 1 FROM health_records nx WHERE nx.resident_id = $alias.resident_id AND nx.id <> $alias.id AND nx.archived_at IS NULL AND nx.status IN ('completed', 'follow_up') AND nx.service_date > $alias.service_date))))";
}

// Rows read with health_due_sql() AS is_due use that value; otherwise the date rules alone are applied.
function health_is_due(array $record): bool
{
    if (array_key_exists('is_due', $record)) return (int) $record['is_due'] === 1;
    if ($record['archived_at'] !== null) return false;
    $today = date('Y-m-d');
    if ($record['status'] === 'scheduled' && (string) $record['service_date'] <= $today) return true;
    return $record['status'] !== 'cancelled' && $record['follow_up_date'] !== null && (string) $record['follow_up_date'] <= $today;
}

function health_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $phase2 = health_phase2_ready($connection);
    $statement = $connection->prepare('SELECT h.*, ' . health_due_sql('h') . ' AS is_due, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.sex, r.purok, r.status AS resident_status, cu.name AS created_by_name, uu.name AS updated_by_name, au.name AS archived_by_name' . ($phase2 ? ', hc.name AS condition_name' : '') . ' FROM health_records h INNER JOIN residents r ON r.id = h.resident_id LEFT JOIN users cu ON cu.id = h.created_by LEFT JOIN users uu ON uu.id = h.updated_by LEFT JOIN users au ON au.id = h.archived_by' . ($phase2 ? ' LEFT JOIN health_conditions hc ON hc.id = h.condition_id' : '') . ' WHERE h.id = :id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch() ?: null;
    // Health Worker with assigned Puroks: records of residents outside them are treated as not found.
    return $row !== null && residents_in_scope($connection, $row['purok']) ? $row : null;
}

function health_resident(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare('SELECT id, first_name, middle_name, last_name, suffix, birth_date, sex, purok, status FROM residents WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch() ?: null;
    return $row !== null && residents_in_scope($connection, $row['purok']) ? $row : null;
}

function health_resident_meta(array $resident): string
{
    $age = residents_age($resident['birth_date'] ?? null);
    return '#' . $resident['id'] . ' · ' . ($age === null ? 'Age unknown' : $age . ' yrs') . ' · ' . residents_purok_label((string) $resident['purok']);
}

// Puroks offered when choosing a resident: Purok 1–4 plus any other Purok still stored on an active resident's profile.
function health_resident_puroks(PDO $connection): array
{
    $puroks = array_map('strval', array_keys(residents_purok_options()));
    $stored = $connection->query("SELECT DISTINCT purok FROM residents WHERE status = 'active' AND purok <> ''")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($stored as $value) if (!in_array((string) $value, $puroks, true)) $puroks[] = (string) $value;
    $scope = residents_purok_scope($connection);
    return $scope === null ? $puroks : array_values(array_intersect($puroks, $scope));
}

// Next record number (HLT-0001, HLT-0002, …). Called inside the saving transaction; the unique key is the final guard.
function health_next_no(PDO $connection): string
{
    $max = (int) $connection->query("SELECT COALESCE(MAX(CAST(SUBSTRING(record_no, 5) AS UNSIGNED)), 0) FROM health_records WHERE record_no REGEXP '^HLT-[0-9]+$' FOR UPDATE")->fetchColumn();
    return sprintf('HLT-%04d', $max + 1);
}

// Health worker names offered in the form: active Health Worker accounts and Barangay Officials whose position mentions
// health. Any other name (for example a nurse from the Rural Health Unit) may be typed.
function health_worker_options(PDO $connection): array
{
    $names = $connection->query("SELECT name FROM users WHERE role = 'health_worker' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    $officials = $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'barangay_personnel'")->fetchColumn();
    if ((int) $officials === 1) $names = array_merge($names, $connection->query("SELECT full_name FROM barangay_personnel WHERE status = 'active' AND (position LIKE '%health%' OR position LIKE '%nurse%' OR position LIKE '%midwife%')")->fetchAll(PDO::FETCH_COLUMN));
    $names = array_values(array_unique(array_map(static fn ($n): string => residents_collapse((string) $n), $names)));
    sort($names, SORT_NATURAL | SORT_FLAG_CASE);
    return $names;
}

// Health worker names already used on records the signed-in Health Worker may see (their assigned Puroks), for the
// list and report filters.
function health_worker_filter_options(PDO $connection): array
{
    [$scope_sql, $scope_params] = residents_purok_scope_sql($connection, 'r.purok');
    $statement = $connection->prepare('SELECT DISTINCT h.health_worker FROM health_records h INNER JOIN residents r ON r.id = h.resident_id WHERE ' . $scope_sql . ' ORDER BY h.health_worker');
    $statement->execute($scope_params);
    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function health_fields(?PDO $connection = null): array
{
    $fields = ['resident_id', 'service', 'service_details', 'health_worker', 'service_date', 'status', 'follow_up_date', 'remarks'];
    if (health_phase2_ready($connection ?? db())) array_push($fields, 'bp_systolic', 'bp_diastolic', 'weight_kg', 'height_cm', 'temperature_c', 'chief_complaint', 'findings', 'condition_id', 'referred_rhu', 'referral_reason');
    if (health_programs_columns_ready($connection ?? db())) array_push($fields, 'blood_sugar_mgdl', 'referral_status', 'referral_completed_on', 'referral_outcome');
    return $fields;
}

// New health_records columns of migration 20261018_health_programs (blood sugar, referral status).
function health_programs_columns_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) $ready = (int) $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'health_records' AND COLUMN_NAME IN ('blood_sugar_mgdl', 'referral_status', 'referral_completed_on', 'referral_outcome')")->fetchColumn() === 4;
    return $ready;
}

// The date that is due for a record (aliased $alias): a Scheduled visit's own date, otherwise its follow-up date.
function health_due_date_sql(string $alias): string
{
    return "(CASE WHEN $alias.status = 'scheduled' AND $alias.service_date <= CURDATE() THEN $alias.service_date ELSE $alias.follow_up_date END)";
}

// ── Diagnosis list (health_conditions) ─────────────────────────────────────────
function health_conditions(PDO $connection, bool $active_only = true): array
{
    if (!health_phase2_ready($connection)) return [];
    return $connection->query('SELECT id, name, is_active FROM health_conditions' . ($active_only ? ' WHERE is_active = 1' : '') . ' ORDER BY name')->fetchAll();
}

// Finds a diagnosis by name (letter case and extra spaces ignored) or adds it. Called inside the caller's transaction.
function health_condition_find_or_add(PDO $connection, string $name): int
{
    $name = residents_collapse($name);
    $statement = $connection->prepare('SELECT id, is_active FROM health_conditions WHERE LOWER(name) = LOWER(:name) LIMIT 1');
    $statement->execute(['name' => $name]);
    $row = $statement->fetch();
    if ($row) {
        if ((int) $row['is_active'] !== 1) $connection->prepare('UPDATE health_conditions SET is_active = 1 WHERE id = :id')->execute(['id' => $row['id']]);
        return (int) $row['id'];
    }
    $connection->prepare('INSERT INTO health_conditions (name, created_by) VALUES (:name, :user)')->execute(['name' => $name, 'user' => current_user()['id']]);
    $id = (int) $connection->lastInsertId();
    health_audit($connection, 0, 'health_condition_added', ['condition' => $name]);
    return $id;
}

// ── Medicines given (health_record_medicines; from Inventory, "Medical" category) ──
// Medicines and medical supplies that can be given now: Medical category, supplies, not archived, with stock.
function health_medicine_options(PDO $connection): array
{
    if (!health_phase2_ready($connection)) return [];
    $statement = $connection->query("SELECT i.id, i.item_code, i.name, i.quantity, i.unit, i.expiry_date FROM inventory_items i INNER JOIN inventory_categories c ON c.id = i.category_id WHERE c.name = 'Medical' AND i.item_type = 'supply' AND i.archived_at IS NULL AND i.quantity > 0 AND i.status NOT IN ('for_repair', 'unserviceable') ORDER BY i.name");
    return $statement->fetchAll();
}

// The medicine lines posted with the form, merged by item: [item_id => quantity], and errors.
function health_medicine_input(PDO $connection, array $input): array
{
    $items = (array) ($input['medicine_item'] ?? []);
    $quantities = (array) ($input['medicine_qty'] ?? []);
    $wanted = [];
    $errors = [];
    $options = array_column(health_medicine_options($connection), null, 'id');
    foreach ($items as $index => $raw_item) {
        $item_id = filter_var($raw_item, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $raw_qty = trim((string) ($quantities[$index] ?? ''));
        if (!$item_id && $raw_qty === '') continue;   // empty line
        $quantity = filter_var($raw_qty, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        if (!$item_id || !isset($options[$item_id])) { $errors['medicines'] = 'Choose medicines from the list (Medical items with stock).'; continue; }
        if (!$quantity) { $errors['medicines'] = 'Enter a whole-number quantity of at least 1 for each medicine.'; continue; }
        $wanted[$item_id] = ($wanted[$item_id] ?? 0) + $quantity;
    }
    foreach ($wanted as $item_id => $quantity) {
        if ($quantity > (int) $options[$item_id]['quantity']) $errors['medicines'] = 'Not enough stock of ' . $options[$item_id]['name'] . ': ' . $options[$item_id]['quantity'] . ' ' . $options[$item_id]['unit'] . ' on hand.';
    }
    return [$wanted, $errors];
}

// Deducts the medicines from Inventory and records them on the health record. Stock is re-read with a lock, so two
// records saved at the same moment cannot give more than is on hand. Called inside the caller's transaction. The
// Inventory movement names only the record number, never the resident (health information stays in Health).
function health_give_medicines(PDO $connection, int $record_id, string $record_no, array $wanted): void
{
    if ($wanted === []) return;
    require_once __DIR__ . '/inventory.php';
    ksort($wanted);
    $user = current_user()['id'];
    foreach ($wanted as $item_id => $quantity) {
        $item = inventory_find($connection, (int) $item_id, true);
        if ($item === null || $item['category_name'] !== 'Medical' || !inventory_can_issue($item)) throw new RuntimeException('A medicine on this record can no longer be given from the Inventory. Remove it and save again.');
        if ((int) $item['quantity'] < $quantity) throw new RuntimeException('Not enough stock of ' . $item['name'] . ': ' . (int) $item['quantity'] . ' ' . $item['unit'] . ' on hand.');
        $on_hand = (int) $item['quantity'] - $quantity;
        $status = inventory_status_after($item, $on_hand, 0);
        $connection->prepare('UPDATE inventory_items SET quantity = :quantity, status = :status, updated_by = :user WHERE id = :id')->execute(['quantity' => $on_hand, 'status' => $status, 'user' => $user, 'id' => $item_id]);
        inventory_log_movement($connection, (int) $item_id, 'issued', -$quantity, $on_hand, 'Given at the health station (' . $record_no . ')', $status !== $item['status'] ? (string) $item['status'] : null, $status !== $item['status'] ? $status : null, 'Health record ' . $record_no);
        $movement_id = inventory_borrowing_ready($connection) ? (int) $connection->lastInsertId() : 0;
        $connection->prepare('INSERT INTO health_record_medicines (health_record_id, item_id, quantity, unit, inventory_movement_id) VALUES (:record, :item, :quantity, :unit, :movement)')->execute(['record' => $record_id, 'item' => $item_id, 'quantity' => $quantity, 'unit' => $item['unit'], 'movement' => $movement_id ?: null]);
    }
}

function health_record_medicines(PDO $connection, int $record_id): array
{
    if (!health_phase2_ready($connection)) return [];
    $statement = $connection->prepare('SELECT m.quantity, m.unit, i.item_code, i.name FROM health_record_medicines m INNER JOIN inventory_items i ON i.id = m.item_id WHERE m.health_record_id = :id ORDER BY i.name');
    $statement->execute(['id' => $record_id]);
    return $statement->fetchAll();
}

// "120/80 mmHg · 55 kg · 160 cm · 36.8 °C" (only what was recorded).
function health_vitals_label(array $record): string
{
    $parts = [];
    if (($record['bp_systolic'] ?? null) !== null && ($record['bp_diastolic'] ?? null) !== null) $parts[] = (int) $record['bp_systolic'] . '/' . (int) $record['bp_diastolic'] . ' mmHg';
    if (($record['weight_kg'] ?? null) !== null) $parts[] = rtrim(rtrim((string) $record['weight_kg'], '0'), '.') . ' kg';
    if (($record['height_cm'] ?? null) !== null) $parts[] = rtrim(rtrim((string) $record['height_cm'], '0'), '.') . ' cm';
    if (($record['temperature_c'] ?? null) !== null) $parts[] = rtrim(rtrim((string) $record['temperature_c'], '0'), '.') . ' °C';
    if (($record['blood_sugar_mgdl'] ?? null) !== null) $parts[] = 'Blood sugar ' . rtrim(rtrim((string) $record['blood_sugar_mgdl'], '0'), '.') . ' mg/dL';
    return implode(' · ', $parts);
}

function health_valid_date(string $value): ?DateTimeImmutable
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $parsed && $parsed->format('Y-m-d') === $value ? $parsed : null;
}

// Server-side validation. $existing is the stored record when editing (an inactive resident already on the record is kept).
function health_validate(PDO $connection, array $input, ?array $existing = null): array
{
    $values = [
        'resident_id' => trim((string) ($input['resident_id'] ?? '')),
        'service' => (string) ($input['service'] ?? ''),
        'service_details' => residents_collapse($input['service_details'] ?? ''),
        'health_worker' => residents_collapse($input['health_worker'] ?? ''),
        'service_date' => trim((string) ($input['service_date'] ?? '')),
        'status' => (string) ($input['status'] ?? ''),
        'follow_up_date' => trim((string) ($input['follow_up_date'] ?? '')),
        'remarks' => trim((string) ($input['remarks'] ?? '')),
    ];
    $errors = [];
    $today = new DateTimeImmutable('today');

    $resident_id = filter_var($values['resident_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $resident = $resident_id ? health_resident($connection, $resident_id) : null;
    if ($resident === null) $errors['resident_id'] = 'Select a resident from the resident records.';
    elseif ($resident['status'] !== 'active' && (int) ($existing['resident_id'] ?? 0) !== (int) $resident_id) $errors['resident_id'] = 'Only active residents can be selected.';
    else $values['resident_id'] = (int) $resident_id;

    if (!array_key_exists($values['service'], health_services())) $errors['service'] = 'Select a service.';
    if (mb_strlen($values['service_details']) > 150) $errors['service_details'] = 'Keep the details to 150 characters.';
    elseif ($values['service'] === 'other' && mb_strlen($values['service_details']) < 3) $errors['service_details'] = 'Describe the service when "Other" is selected.';
    if (mb_strlen($values['health_worker']) < 2 || mb_strlen($values['health_worker']) > 150 || !preg_match("/^\p{L}[\p{L}\p{M}\s.,'\-]*$/u", $values['health_worker'])) $errors['health_worker'] = 'Enter the health worker\'s name (letters only, 2 to 150 characters).';

    $service_date = health_valid_date($values['service_date']);
    if ($service_date === null) $errors['service_date'] = 'Enter a valid date.';
    elseif ($service_date < new DateTimeImmutable('2000-01-01')) $errors['service_date'] = 'Enter a realistic date.';
    elseif ($service_date > $today->modify('+1 year')) $errors['service_date'] = 'Schedule the service within the next year.';
    if (!array_key_exists($values['status'], health_statuses())) $errors['status'] = 'Select a status.';
    elseif ($values['status'] === 'completed' && $service_date !== null && $service_date > $today) $errors['status'] = 'A service in the future cannot be Completed yet. Use Scheduled.';

    // Follow-up date: optional on any record (the next visit to come back for), required for Follow-up, none when Cancelled.
    if ($values['status'] === 'cancelled') {
        $values['follow_up_date'] = '';
    } elseif ($values['follow_up_date'] === '') {
        if ($values['status'] === 'follow_up') $errors['follow_up_date'] = 'A follow-up date is required when the status is Follow-up.';
    } else {
        $follow_up = health_valid_date($values['follow_up_date']);
        if ($follow_up === null) $errors['follow_up_date'] = 'Enter a valid date.';
        elseif ($service_date !== null && $follow_up <= $service_date) $errors['follow_up_date'] = 'The follow-up date must be after the service date.';
        elseif ($follow_up > $today->modify('+2 years')) $errors['follow_up_date'] = 'Enter a follow-up date within the next two years.';
    }
    if (mb_strlen($values['remarks']) > 1000) $errors['remarks'] = 'Remarks must not exceed 1,000 characters.';
    $nullables = ['service_details', 'follow_up_date', 'remarks'];

    if (health_phase2_ready($connection)) {
        $number = static fn (string $key): string => trim(str_replace(',', '.', (string) ($input[$key] ?? '')));
        $values += [
            'bp_systolic' => $number('bp_systolic'), 'bp_diastolic' => $number('bp_diastolic'),
            'weight_kg' => $number('weight_kg'), 'height_cm' => $number('height_cm'), 'temperature_c' => $number('temperature_c'),
            'chief_complaint' => residents_collapse($input['chief_complaint'] ?? ''),
            'findings' => trim((string) ($input['findings'] ?? '')),
            'condition_id' => trim((string) ($input['condition_id'] ?? '')),
            'referred_rhu' => (string) ($input['referred_rhu'] ?? '') === '1' ? 1 : 0,
            'referral_reason' => residents_collapse($input['referral_reason'] ?? ''),
        ];
        // Blood pressure: both numbers or neither.
        if (($values['bp_systolic'] === '') !== ($values['bp_diastolic'] === '')) $errors['bp'] = 'Enter both blood pressure numbers (for example 120 / 80), or leave both blank.';
        elseif ($values['bp_systolic'] !== '') {
            $sys = filter_var($values['bp_systolic'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 50, 'max_range' => 300]]);
            $dia = filter_var($values['bp_diastolic'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 30, 'max_range' => 200]]);
            if ($sys === false || $dia === false) $errors['bp'] = 'Blood pressure: upper number 50–300, lower number 30–200 (whole numbers).';
            elseif ($dia >= $sys) $errors['bp'] = 'The upper blood pressure number must be higher than the lower number.';
            else { $values['bp_systolic'] = $sys; $values['bp_diastolic'] = $dia; }
        }
        foreach (['weight_kg' => [0.5, 300, 'Weight must be 0.5–300 kg.'], 'height_cm' => [30, 250, 'Height must be 30–250 cm.'], 'temperature_c' => [30, 45, 'Temperature must be 30–45 °C.']] as $key => [$min, $max, $message]) {
            if ($values[$key] === '') continue;
            $value = filter_var($values[$key], FILTER_VALIDATE_FLOAT);
            if ($value === false || $value < $min || $value > $max || !preg_match('/^\d+(\.\d{1,2})?$/', $values[$key])) $errors[$key] = $message;
            else $values[$key] = round($value, $key === 'weight_kg' ? 2 : 1);
        }
        if (mb_strlen($values['chief_complaint']) > 255) $errors['chief_complaint'] = 'Keep the chief complaint to 255 characters.';
        if (mb_strlen($values['findings']) > 2000) $errors['findings'] = 'Findings must not exceed 2,000 characters.';
        if ($values['condition_id'] !== '') {
            $condition_id = filter_var($values['condition_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $statement = $connection->prepare('SELECT is_active FROM health_conditions WHERE id = :id');
            $statement->execute(['id' => $condition_id ?: 0]);
            $active = $statement->fetchColumn();
            // An inactive diagnosis already on the record being edited is kept.
            if ($active === false || ((int) $active !== 1 && (int) ($existing['condition_id'] ?? 0) !== (int) $condition_id)) $errors['condition_id'] = 'Choose a diagnosis from the list.';
            else $values['condition_id'] = (int) $condition_id;
        }
        if ($values['referred_rhu'] === 1 && mb_strlen($values['referral_reason']) < 3) $errors['referral_reason'] = 'Give the reason for the referral to the RHU.';
        elseif (mb_strlen($values['referral_reason']) > 255) $errors['referral_reason'] = 'Keep the reason to 255 characters.';
        if ($values['referred_rhu'] === 0) $values['referral_reason'] = '';
        array_push($nullables, 'bp_systolic', 'bp_diastolic', 'weight_kg', 'height_cm', 'temperature_c', 'chief_complaint', 'findings', 'condition_id', 'referral_reason');
    }

    // Phases 3–5 (migration 20261018_health_programs): blood sugar reading; the referral's status is kept from the
    // stored record (Pending when first referred) and changed only on the Referrals tab.
    if (health_programs_columns_ready($connection)) {
        $values['blood_sugar_mgdl'] = trim(str_replace(',', '.', (string) ($input['blood_sugar_mgdl'] ?? '')));
        if ($values['blood_sugar_mgdl'] !== '') {
            $sugar = filter_var($values['blood_sugar_mgdl'], FILTER_VALIDATE_FLOAT);
            if ($sugar === false || $sugar < 20 || $sugar > 900 || !preg_match('/^\d+(\.\d)?$/', $values['blood_sugar_mgdl'])) $errors['blood_sugar_mgdl'] = 'Blood sugar must be 20–900 mg/dL.';
            else $values['blood_sugar_mgdl'] = round($sugar, 1);
        }
        $referred = (int) ($values['referred_rhu'] ?? 0) === 1;
        $values['referral_status'] = $referred ? ($existing['referral_status'] ?? 'pending') : '';
        $values['referral_completed_on'] = $referred ? (string) ($existing['referral_completed_on'] ?? '') : '';
        $values['referral_outcome'] = $referred ? (string) ($existing['referral_outcome'] ?? '') : '';
        array_push($nullables, 'blood_sugar_mgdl', 'referral_status', 'referral_completed_on', 'referral_outcome');
    }

    foreach ($nullables as $nullable) if ($values[$nullable] === '') $values[$nullable] = null;
    return ['values' => $values, 'errors' => $errors];
}

function health_audit(PDO $connection, int $id, string $action, array $details = []): void
{
    residents_audit($connection, 'health', $id, $action, $details);
}

// ── List state (search, filters, due follow-ups, paging — all in the URL) ──────

function health_list_state(array $input): array
{
    $date = static fn (string $value): string => health_valid_date($value) ? $value : '';
    $state = [
        'q' => mb_substr(residents_collapse((string) ($input['q'] ?? '')), 0, 100),
        'status' => (string) ($input['status'] ?? ''),
        'service' => (string) ($input['service'] ?? ''),
        'worker' => mb_substr(residents_collapse((string) ($input['worker'] ?? '')), 0, 150),
        'from' => $date((string) ($input['from'] ?? '')),
        'to' => $date((string) ($input['to'] ?? '')),
        'due' => (string) ($input['due'] ?? '') === '1',
        // With due: 'today' (due today only) or 'overdue' (before today); '' = both.
        'when' => in_array((string) ($input['when'] ?? ''), ['today', 'overdue'], true) ? (string) $input['when'] : '',
        // 'mine' (default): records this Health Worker encoded; 'all': every record in their Puroks.
        'show' => (string) ($input['show'] ?? '') === 'all' ? 'all' : 'mine',
        'purok' => (string) ($input['purok'] ?? ''),
        'condition' => (int) ($input['condition'] ?? 0) > 0 ? (int) $input['condition'] : 0,
        'page' => max(1, (int) ($input['page'] ?? 1)),
    ];
    if ($state['purok'] !== '' && !in_array($state['purok'], health_resident_puroks(db()), true)) $state['purok'] = '';
    if ($state['status'] !== 'archived' && !array_key_exists($state['status'], health_statuses())) $state['status'] = '';
    if (!array_key_exists($state['service'], health_services())) $state['service'] = '';
    if ($state['from'] !== '' && $state['to'] !== '' && $state['from'] > $state['to']) [$state['from'], $state['to']] = [$state['to'], $state['from']];
    return $state;
}

function health_query_string(array $state): string
{
    return http_build_query(array_filter(['q' => $state['q'], 'status' => $state['status'], 'service' => $state['service'], 'worker' => $state['worker'], 'from' => $state['from'], 'to' => $state['to'], 'purok' => $state['purok'], 'condition' => $state['condition'] > 0 ? (string) $state['condition'] : '', 'show' => $state['show'] === 'all' ? 'all' : '', 'due' => $state['due'] ? '1' : '', 'when' => $state['due'] ? ($state['when'] ?? '') : '', 'page' => $state['page'] > 1 ? $state['page'] : ''], static fn ($v): bool => $v !== ''));
}

function health_list_where(array $state): array
{
    $where = [$state['status'] === 'archived' ? 'h.archived_at IS NOT NULL' : 'h.archived_at IS NULL'];
    $params = [];
    if ($state['q'] !== '') {
        $like = '%' . addcslashes($state['q'], '%_\\') . '%';
        $parts = [];
        foreach (["CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name, r.suffix)", "CONCAT_WS(' ', r.first_name, r.last_name)", 'h.record_no', 'h.service_details', 'h.health_worker', 'h.remarks'] as $index => $column) { $parts[] = "$column LIKE :search$index"; $params['search' . $index] = $like; }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($state['status'] !== '' && $state['status'] !== 'archived') { $where[] = 'h.status = :status'; $params['status'] = $state['status']; }
    if ($state['service'] !== '') { $where[] = 'h.service = :service'; $params['service'] = $state['service']; }
    if ($state['worker'] !== '') { $where[] = 'h.health_worker = :worker'; $params['worker'] = $state['worker']; }
    if ($state['from'] !== '') { $where[] = 'h.service_date >= :from'; $params['from'] = $state['from']; }
    if ($state['to'] !== '') { $where[] = 'h.service_date <= :to'; $params['to'] = $state['to']; }
    if ($state['purok'] !== '') { $where[] = 'r.purok = :purok'; $params['purok'] = $state['purok']; }
    if ($state['condition'] > 0 && health_phase2_ready(db())) { $where[] = 'h.condition_id = :condition'; $params['condition'] = $state['condition']; }
    if ($state['show'] !== 'all') { $where[] = 'h.created_by = :me'; $params['me'] = (int) current_user()['id']; }
    [$scope_sql, $scope_params] = residents_purok_scope_sql(db(), 'r.purok');
    if ($scope_params !== []) { $where[] = $scope_sql; $params += $scope_params; }
    if ($state['due']) {
        $where[] = health_due_sql('h');
        if (($state['when'] ?? '') === 'today') $where[] = health_due_date_sql('h') . ' = CURDATE()';
        elseif (($state['when'] ?? '') === 'overdue') $where[] = health_due_date_sql('h') . ' < CURDATE()';
    }
    return [$where, $params];
}

// ── Morbidity Report: consultations per diagnosis for a day, week or month ─────
// Counted: records not archived, status Completed or Follow-up (visits that took place), service date in the period,
// residents within the Health Worker's assigned Puroks (and the chosen Purok). Age is the age on the service date.

function health_age_groups(): array
{
    return ['0-4' => [0, 4], '5-9' => [5, 9], '10-19' => [10, 19], '20-59' => [20, 59], '60+' => [60, 200]];
}

function health_morbidity_state(PDO $connection, array $input): array
{
    $period = in_array($input['period'] ?? '', ['day', 'week', 'month'], true) ? (string) $input['period'] : 'month';
    $raw = trim((string) ($input['date'] ?? ''));
    if ($period === 'month' && preg_match('/^\d{4}-\d{2}$/', $raw)) $raw .= '-01';
    $date = health_valid_date($raw) ?? new DateTimeImmutable('today');
    if ($date < new DateTimeImmutable('2000-01-01') || $date > new DateTimeImmutable('today +1 year')) $date = new DateTimeImmutable('today');
    [$from, $to, $label] = match ($period) {
        'day' => [$date, $date, $date->format('F j, Y')],
        'week' => [$date->modify('monday this week'), $date->modify('sunday this week'), ''],
        default => [$date->modify('first day of this month'), $date->modify('last day of this month'), $date->format('F Y')],
    };
    if ($period === 'week') $label = 'Week of ' . $from->format('M j') . ' – ' . $to->format('M j, Y');
    $purok = (string) ($input['purok'] ?? '');
    if ($purok !== '' && !in_array($purok, health_resident_puroks($connection), true)) $purok = '';
    return ['period' => $period, 'date' => $date->format('Y-m-d'), 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'label' => $label, 'purok' => $purok];
}

function health_morbidity_query(array $state): string
{
    return http_build_query(array_filter(['period' => $state['period'], 'date' => $state['period'] === 'month' ? substr($state['date'], 0, 7) : $state['date'], 'purok' => $state['purok']], static fn ($v): bool => $v !== ''));
}

// Rows per diagnosis: total visits, residents, by sex and by age group; plus totals and visits without a diagnosis.
function health_morbidity(PDO $connection, array $state): array
{
    $where = ["h.archived_at IS NULL", "h.status IN ('completed', 'follow_up')", 'h.service_date BETWEEN :from AND :to'];
    $params = ['from' => $state['from'], 'to' => $state['to']];
    [$scope_sql, $scope_params] = residents_purok_scope_sql($connection, 'r.purok');
    if ($scope_params !== []) { $where[] = $scope_sql; $params += $scope_params; }
    if ($state['purok'] !== '') { $where[] = 'r.purok = :purok'; $params['purok'] = $state['purok']; }
    $age = 'TIMESTAMPDIFF(YEAR, r.birth_date, h.service_date)';
    $groups = '';
    $index = 0;
    foreach (health_age_groups() as [$min, $max]) { $groups .= ", SUM(CASE WHEN $age BETWEEN $min AND $max THEN 1 ELSE 0 END) AS age$index"; $index++; }
    $statement = $connection->prepare("SELECT h.condition_id, hc.name, COUNT(*) AS visits, COUNT(DISTINCT h.resident_id) AS residents, SUM(r.sex = 'male') AS male, SUM(r.sex = 'female') AS female, SUM(r.sex NOT IN ('male', 'female') OR r.sex IS NULL) AS other_sex, SUM(r.birth_date IS NULL) AS age_unknown$groups FROM health_records h INNER JOIN residents r ON r.id = h.resident_id LEFT JOIN health_conditions hc ON hc.id = h.condition_id WHERE " . implode(' AND ', $where) . ' GROUP BY h.condition_id, hc.name ORDER BY h.condition_id IS NULL, visits DESC, hc.name');
    $statement->execute($params);
    $rows = [];
    $none = null;
    foreach ($statement->fetchAll() as $row) {
        $row = array_map(static fn ($v) => is_numeric($v) ? (int) $v : $v, $row);
        if ($row['condition_id'] === null) $none = $row; else $rows[] = $row;
    }
    $sum = static fn (string $key): int => array_sum(array_column($rows, $key));
    $totals = ['visits' => $sum('visits'), 'male' => $sum('male'), 'female' => $sum('female'), 'other_sex' => $sum('other_sex'), 'age_unknown' => $sum('age_unknown')];
    foreach (array_keys(array_values(health_age_groups())) as $i) $totals['age' . $i] = $sum('age' . $i);
    // Residents counted once across all diagnoses.
    $distinct = $connection->prepare('SELECT COUNT(DISTINCT h.resident_id) FROM health_records h INNER JOIN residents r ON r.id = h.resident_id WHERE ' . implode(' AND ', $where) . ' AND h.condition_id IS NOT NULL');
    $distinct->execute($params);
    $totals['residents'] = (int) $distinct->fetchColumn();
    return ['rows' => $rows, 'totals' => $totals, 'without' => $none];
}

// The report table (screen and print use the same columns).
function health_morbidity_table(array $report, string $class): string
{
    $groups = array_keys(health_age_groups());
    $other = $report['totals']['other_sex'] > 0;
    $unknown = $report['totals']['age_unknown'] > 0;
    $head = '<th>#</th><th>Diagnosis</th><th class="num">Visits</th><th class="num">Residents</th><th class="num">Male</th><th class="num">Female</th>' . ($other ? '<th class="num">Other</th>' : '');
    foreach ($groups as $group) $head .= '<th class="num">' . e($group) . '</th>';
    if ($unknown) $head .= '<th class="num">Age unknown</th>';
    $cells = static function (array $row) use ($groups, $other, $unknown): string {
        $html = '<td class="num">' . number_format($row['visits']) . '</td><td class="num">' . number_format($row['residents']) . '</td><td class="num">' . number_format($row['male']) . '</td><td class="num">' . number_format($row['female']) . '</td>' . ($other ? '<td class="num">' . number_format($row['other_sex']) . '</td>' : '');
        foreach (array_keys($groups) as $i) $html .= '<td class="num">' . number_format($row['age' . $i]) . '</td>';
        return $html . ($unknown ? '<td class="num">' . number_format($row['age_unknown']) . '</td>' : '');
    };
    $body = '';
    foreach ($report['rows'] as $index => $row) $body .= '<tr><td>' . ($index + 1) . '</td><td>' . e((string) $row['name']) . '</td>' . $cells($row) . '</tr>';
    return '<table class="' . e($class) . '"><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody><tfoot><tr><td></td><td>Total</td>' . $cells($report['totals']) . '</tr></tfoot></table>';
}

// List order: by the date that is due (scheduled visit date, otherwise the follow-up date) when showing what is due;
// newest service first otherwise ($newest_first = false for the printed list, oldest first).
function health_list_order(bool $due, bool $newest_first = true): string
{
    if ($due) return 'COALESCE(' . health_due_date_sql('h') . ', h.service_date) ASC, h.id ASC';
    return $newest_first ? 'h.service_date DESC, h.id DESC' : 'h.service_date ASC, h.id ASC';
}

// Due today or overdue among the records the signed-in Health Worker sees (their own records by default; every record in
// their Puroks with $mine = false).
function health_due_count(PDO $connection, bool $mine = true): int
{
    [$scope_sql, $scope_params] = residents_purok_scope_sql($connection, 'r.purok');
    $statement = $connection->prepare('SELECT COUNT(*) FROM health_records h INNER JOIN residents r ON r.id = h.resident_id WHERE h.archived_at IS NULL AND ' . health_due_sql('h') . ' AND ' . $scope_sql . ($mine ? ' AND h.created_by = :me' : ''));
    $statement->execute($scope_params + ($mine ? ['me' => (int) current_user()['id']] : []));
    return (int) $statement->fetchColumn();
}
