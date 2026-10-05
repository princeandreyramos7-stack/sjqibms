<?php
declare(strict_types=1);

require_once __DIR__ . '/disaster_response.php';
require_once __DIR__ . '/inventory.php';

// Disaster Management — recovery (migration 20261002_disaster_recovery): relief distribution and damage assessment.
// Relief uses the Inventory module's own issue rules: only supplies that can be issued, never more than on hand, the Low
// Stock rule afterwards, and an 'issued' row in the Inventory movement history — all in the same transaction.
// Cancelling a relief entry made by mistake archives it and returns the items to stock ('adjusted' movement).

const DISASTER_PHOTO_MAX_BYTES = 5 * 1024 * 1024;

function disaster_recovery_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $check = $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('drr_relief_distributions', 'drr_relief_items', 'drr_damage_assessments', 'drr_evacuations', 'inventory_items', 'inventory_movements')");
        $ready = (int) $check->fetchColumn() === 6;
    }
    return $ready;
}

// Incidents relief or damage can be recorded for: incident records that are not archived or cancelled (completed
// incidents are included, since recovery continues after the incident), plus the one already on a record.
function disaster_recovery_incidents(PDO $connection, ?int $include_id = null): array
{
    $statement = $connection->prepare("SELECT d.id, d.reference_no, d.title, d.status FROM drr_records d WHERE d.record_type = 'incident' AND ((d.archived_at IS NULL AND d.status <> 'cancelled') OR d.id = :include) ORDER BY d.record_date DESC, d.id DESC");
    $statement->execute(['include' => $include_id ?? 0]);
    return $statement->fetchAll();
}

function disaster_family_key(array $row): string
{
    return $row['household_id'] !== null ? 'h' . $row['household_id'] : 'r' . $row['resident_id'];
}

// ── Relief ─────────────────────────────────────────────────────────────────────

// Supplies that can be given out now (same rule as Inventory's Issue Supplies).
function disaster_relief_supplies(PDO $connection): array
{
    $rows = $connection->query("SELECT i.id, i.item_code, i.name, i.item_type, i.quantity, i.unit, i.status, i.archived_at FROM inventory_items i WHERE i.item_type = 'supply' AND i.archived_at IS NULL AND i.quantity > 0 ORDER BY i.name")->fetchAll();
    return array_values(array_filter($rows, 'inventory_can_issue'));
}

function disaster_relief_next_reference(PDO $connection): string
{
    $prefix = 'RLF-' . date('Y') . '-';
    $statement = $connection->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(reference_no, 10) AS UNSIGNED)), 0) FROM drr_relief_distributions WHERE reference_no LIKE :prefix FOR UPDATE');
    $statement->execute(['prefix' => $prefix . '%']);
    return $prefix . sprintf('%04d', (int) $statement->fetchColumn() + 1);
}

function disaster_relief_find(PDO $connection, int $id, bool $lock = false): ?array
{
    [$family, $joins] = disaster_family_sql('r');
    $statement = $connection->prepare("SELECT r.*, $family AS family_label, d.reference_no AS incident_reference, d.title AS incident_title, cu.name AS created_by_name, au.name AS archived_by_name FROM drr_relief_distributions r INNER JOIN drr_records d ON d.id = r.incident_id LEFT JOIN users cu ON cu.id = r.created_by LEFT JOIN users au ON au.id = r.archived_by$joins WHERE r.id = :id LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function disaster_relief_items(PDO $connection, int $distribution_id): array
{
    $statement = $connection->prepare('SELECT x.item_id, x.quantity, x.unit, i.item_code, i.name FROM drr_relief_items x INNER JOIN inventory_items i ON i.id = x.item_id WHERE x.distribution_id = :id ORDER BY i.name');
    $statement->execute(['id' => $distribution_id]);
    return $statement->fetchAll();
}

// Relief already given to this family for this incident (not cancelled).
function disaster_relief_previous(PDO $connection, int $incident_id, array $family): array
{
    $column = $family['household_id'] !== null ? 'household_id' : 'resident_id';
    $statement = $connection->prepare("SELECT reference_no, distributed_on FROM drr_relief_distributions WHERE incident_id = :incident AND $column = :family AND archived_at IS NULL ORDER BY distributed_on, id");
    $statement->execute(['incident' => $incident_id, 'family' => $family['household_id'] ?? $family['resident_id']]);
    return $statement->fetchAll();
}

// Item lines from the form (item_id[] and quantity[]); blank lines are skipped and repeated items are added together.
function disaster_relief_lines(array $input): array
{
    $ids = (array) ($input['item_id'] ?? []);
    $quantities = (array) ($input['quantity'] ?? []);
    $lines = [];
    foreach (array_slice($ids, 0, 10, true) as $index => $raw_id) {
        $raw_id = trim((string) $raw_id);
        $raw_quantity = trim((string) ($quantities[$index] ?? ''));
        if ($raw_id === '' && $raw_quantity === '') continue;
        $lines[] = ['item_id' => $raw_id, 'quantity' => $raw_quantity];
    }
    return $lines;
}

// Saves a relief distribution. Returns ['id', 'message'] or ['errors', 'values', 'previous'].
// $input['confirm_duplicate'] = '1' is required when the family already received relief for the same incident.
function disaster_relief_save(PDO $connection, array $input): array
{
    $values = [
        'incident_id' => trim((string) ($input['incident_id'] ?? '')),
        'resident_id' => trim((string) ($input['resident_id'] ?? '')),
        'received_by' => residents_collapse($input['received_by'] ?? ''),
        'distributed_on' => trim((string) ($input['distributed_on'] ?? '')),
        'remarks' => residents_collapse($input['remarks'] ?? ''),
        'lines' => disaster_relief_lines($input),
        'confirm_duplicate' => (string) ($input['confirm_duplicate'] ?? '') === '1',
    ];
    $errors = [];
    $incident_id = filter_var($values['incident_id'], FILTER_VALIDATE_INT);
    $allowed = array_map(static fn (array $i): int => (int) $i['id'], disaster_recovery_incidents($connection));
    if (!$incident_id || !in_array($incident_id, $allowed, true)) $errors['incident_id'] = 'Select an incident.';
    $resident_id = filter_var($values['resident_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $family = $resident_id ? disaster_family_for_resident($connection, $resident_id) : null;
    if ($family === null) $errors['resident_id'] = 'Select a resident from the resident records.';
    elseif ($family['resident']['status'] !== 'active') $errors['resident_id'] = 'Only active residents can receive relief.';
    if ($error = disaster_person_name_error($values['received_by'], true, 'name of the person who received the relief')) $errors['received_by'] = $error;
    $date = disaster_valid_date($values['distributed_on']);
    if ($date === null) $errors['distributed_on'] = 'Enter a valid date.';
    elseif ($date > new DateTimeImmutable('today')) $errors['distributed_on'] = 'The distribution date cannot be in the future.';
    elseif ($date < (new DateTimeImmutable('today'))->modify('-1 year')) $errors['distributed_on'] = 'Enter a date within the past year.';
    if (mb_strlen($values['remarks']) > 500) $errors['remarks'] = 'Keep the remarks to 500 characters.';

    $wanted = [];
    foreach ($values['lines'] as $line) {
        $item_id = filter_var($line['item_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($line['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        if (!$item_id || $quantity === false) { $errors['items'] = 'Each line needs an item and a whole-number quantity of at least 1.'; continue; }
        $wanted[$item_id] = ($wanted[$item_id] ?? 0) + $quantity;
    }
    if ($wanted === [] && !isset($errors['items'])) $errors['items'] = 'Add at least one item.';

    $previous = [];
    if (!isset($errors['incident_id']) && $family !== null) {
        $previous = disaster_relief_previous($connection, $incident_id, $family);
        if ($previous !== [] && !$values['confirm_duplicate']) $errors['confirm_duplicate'] = 'This family already received relief for this incident (' . implode(', ', array_map(static fn (array $p): string => $p['reference_no'] . ' on ' . disaster_format_date($p['distributed_on']), $previous)) . '). Tick the box to give again anyway.';
    }
    if ($errors !== []) return ['errors' => $errors, 'values' => $values, 'previous' => $previous];

    $connection->beginTransaction();
    try {
        ksort($wanted);   // lock items in a fixed order so two distributions at once cannot deadlock
        $locked = [];
        foreach ($wanted as $item_id => $quantity) {
            $item = inventory_find($connection, $item_id, true);
            if ($item === null || !inventory_can_issue($item)) throw new DomainException('items|' . ($item ? $item['name'] : 'An item') . ' can no longer be given out (only supplies with stock on hand). Reload the page.');
            if ($quantity > (int) $item['quantity']) throw new DomainException('items|Only ' . number_format((int) $item['quantity']) . ' ' . $item['unit'] . ' of ' . $item['name'] . ' are on hand.');
            $locked[$item_id] = $item;
        }
        $reference = disaster_relief_next_reference($connection);
        $incident = disaster_find($connection, (int) $incident_id);
        $user = current_user()['id'];
        $connection->prepare('INSERT INTO drr_relief_distributions (reference_no, incident_id, household_id, resident_id, received_by, distributed_on, remarks, created_by) VALUES (:reference, :incident, :household, :resident, :received, :date, :remarks, :user)')
            ->execute(['reference' => $reference, 'incident' => $incident_id, 'household' => $family['household_id'], 'resident' => $family['resident_id'], 'received' => $values['received_by'], 'date' => $values['distributed_on'], 'remarks' => $values['remarks'] === '' ? null : $values['remarks'], 'user' => $user]);
        $distribution_id = (int) $connection->lastInsertId();
        $insert = $connection->prepare('INSERT INTO drr_relief_items (distribution_id, item_id, quantity, unit) VALUES (:distribution, :item, :quantity, :unit)');
        $given = [];
        foreach ($wanted as $item_id => $quantity) {
            $item = $locked[$item_id];
            $on_hand = (int) $item['quantity'] - $quantity;
            $status = inventory_status_after($item, $on_hand, 0);
            $connection->prepare('UPDATE inventory_items SET quantity = :quantity, status = :status, updated_by = :user WHERE id = :id')->execute(['quantity' => $on_hand, 'status' => $status, 'user' => $user, 'id' => $item_id]);
            inventory_log_movement($connection, $item_id, 'issued', -$quantity, $on_hand, 'Relief ' . $reference . ' (' . $incident['reference_no'] . ')', $status !== $item['status'] ? (string) $item['status'] : null, $status !== $item['status'] ? $status : null, mb_substr($family['label'], 0, 150));
            inventory_audit($connection, $item_id, 'inventory_issued', ['item_code' => $item['item_code'], 'quantity' => $quantity, 'recipient' => $family['label'], 'reference' => $reference]);
            $insert->execute(['distribution' => $distribution_id, 'item' => $item_id, 'quantity' => $quantity, 'unit' => $item['unit']]);
            $given[] = $quantity . ' ' . $item['unit'] . ' ' . $item['name'];
        }
        residents_audit($connection, 'disaster_relief', $distribution_id, 'disaster_relief_distributed', ['reference' => $reference, 'name' => $family['label'], 'items' => implode(', ', $given), 'repeat' => $previous !== [] ? 'yes' : 'no']);
        $connection->commit();
        return ['id' => $distribution_id, 'message' => $reference . ': ' . implode(', ', $given) . ' given to ' . $family['label'] . '. Inventory stock was updated.'];
    } catch (DomainException $exception) {
        $connection->rollBack();
        [$field, $message] = explode('|', $exception->getMessage(), 2);
        return ['errors' => [$field => $message], 'values' => $values, 'previous' => $previous];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

// Cancels a relief entry made by mistake: archives it and returns each item to stock. Throws RuntimeException.
function disaster_relief_cancel(PDO $connection, int $id, string $reason): string
{
    $reason = residents_collapse($reason);
    if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) throw new RuntimeException('Give the reason for cancelling (3 to 255 characters).');
    $connection->beginTransaction();
    try {
        $relief = disaster_relief_find($connection, $id, true);
        if ($relief === null) throw new RuntimeException('The relief entry no longer exists.');
        if ($relief['archived_at'] !== null) throw new RuntimeException('This relief entry was already cancelled.');
        $user = current_user()['id'];
        $lines = disaster_relief_items($connection, $id);
        usort($lines, static fn (array $a, array $b): int => (int) $a['item_id'] <=> (int) $b['item_id']);
        foreach ($lines as $line) {
            $item = inventory_find($connection, (int) $line['item_id'], true);
            if ($item === null) continue;
            $on_hand = (int) $item['quantity'] + (int) $line['quantity'];
            $status = inventory_status_after($item, $on_hand, 0);
            $connection->prepare('UPDATE inventory_items SET quantity = :quantity, status = :status, updated_by = :user WHERE id = :id')->execute(['quantity' => $on_hand, 'status' => $status, 'user' => $user, 'id' => $item['id']]);
            inventory_log_movement($connection, (int) $item['id'], 'adjusted', (int) $line['quantity'], $on_hand, 'Relief ' . $relief['reference_no'] . ' cancelled: ' . $reason, $status !== $item['status'] ? (string) $item['status'] : null, $status !== $item['status'] ? $status : null);
        }
        $connection->prepare('UPDATE drr_relief_distributions SET archived_at = NOW(), archived_by = :user, archive_reason = :reason WHERE id = :id')->execute(['user' => $user, 'reason' => $reason, 'id' => $id]);
        residents_audit($connection, 'disaster_relief', $id, 'disaster_relief_cancelled', ['reference' => $relief['reference_no'], 'name' => $relief['family_label'], 'reason' => $reason]);
        $connection->commit();
        return $relief['reference_no'] . ' was cancelled and its items were returned to Inventory stock.';
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

// Families evacuated for an incident (any check-in that was not removed) that have not received relief for it yet.
function disaster_relief_unserved(PDO $connection, int $incident_id): array
{
    [$family, $joins] = disaster_family_sql('e');
    $statement = $connection->prepare("SELECT e.household_id, e.resident_id, MAX($family) AS family_label, MAX(e.family_members) AS persons, GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS centers, MAX(e.departed_at IS NULL) AS in_center, MAX(COALESCE(e.resident_id, fh.household_head_resident_id, (SELECT rh.resident_id FROM resident_households rh WHERE rh.household_id = e.household_id AND rh.left_at IS NULL ORDER BY rh.resident_id LIMIT 1))) AS pick_resident FROM drr_evacuations e INNER JOIN drr_evacuation_centers c ON c.id = e.center_id$joins WHERE e.incident_id = :incident AND e.archived_at IS NULL AND NOT EXISTS (SELECT 1 FROM drr_relief_distributions r WHERE r.incident_id = e.incident_id AND r.archived_at IS NULL AND ((e.household_id IS NOT NULL AND r.household_id = e.household_id) OR (e.resident_id IS NOT NULL AND r.resident_id = e.resident_id))) GROUP BY e.household_id, e.resident_id ORDER BY family_label");
    $statement->execute(['incident' => $incident_id]);
    return $statement->fetchAll();
}

// ── Damage assessment ──────────────────────────────────────────────────────────

function disaster_damage_fields(): array
{
    return ['incident_id', 'area_id', 'assessed_on', 'houses_partial', 'houses_total', 'roads', 'bridges', 'crops', 'public_facilities', 'notes'];
}

function disaster_damage_other_fields(): array
{
    return ['roads' => 'Roads', 'bridges' => 'Bridges', 'crops' => 'Crops', 'public_facilities' => 'Public facilities'];
}

function disaster_damage_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $statement = $connection->prepare('SELECT g.*, a.name AS area_name, d.reference_no, d.title AS incident_title, cu.name AS created_by_name, uu.name AS updated_by_name FROM drr_damage_assessments g INNER JOIN drr_areas a ON a.id = g.area_id INNER JOIN drr_records d ON d.id = g.incident_id LEFT JOIN users cu ON cu.id = g.created_by LEFT JOIN users uu ON uu.id = g.updated_by WHERE g.id = :id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function disaster_damage_validate(PDO $connection, array $input, ?array $existing = null): array
{
    $values = [
        'incident_id' => trim((string) ($input['incident_id'] ?? '')),
        'area_id' => trim((string) ($input['area_id'] ?? '')),
        'assessed_on' => trim((string) ($input['assessed_on'] ?? '')),
        'houses_partial' => trim((string) ($input['houses_partial'] ?? '')),
        'houses_total' => trim((string) ($input['houses_total'] ?? '')),
        'notes' => trim((string) ($input['notes'] ?? '')),
    ];
    foreach (array_keys(disaster_damage_other_fields()) as $field) $values[$field] = residents_collapse($input[$field] ?? '');
    $errors = [];
    $incident_id = filter_var($values['incident_id'], FILTER_VALIDATE_INT);
    $allowed = array_map(static fn (array $i): int => (int) $i['id'], disaster_recovery_incidents($connection, isset($existing['incident_id']) ? (int) $existing['incident_id'] : null));
    if (!$incident_id || !in_array($incident_id, $allowed, true)) $errors['incident_id'] = 'Select an incident.';
    else $values['incident_id'] = $incident_id;
    [$values['area_id'], $area_error] = disaster_area_error($connection, $values['area_id'], isset($existing['area_id']) ? (int) $existing['area_id'] : null);
    if ($area_error) $errors['area_id'] = $area_error;
    $date = disaster_valid_date($values['assessed_on']);
    if ($date === null) $errors['assessed_on'] = 'Enter a valid date.';
    elseif ($date > new DateTimeImmutable('today')) $errors['assessed_on'] = 'The assessment date cannot be in the future.';
    elseif ($date < new DateTimeImmutable('2000-01-01')) $errors['assessed_on'] = 'Enter a realistic date.';
    foreach (['houses_partial' => 'partially damaged houses', 'houses_total' => 'totally damaged houses'] as $field => $noun) {
        if ($values[$field] === '') $values[$field] = '0';
        $number = filter_var($values[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10000]]);
        if ($number === false) $errors[$field] = 'Enter the number of ' . $noun . ' (0 to 10,000).';
        else $values[$field] = $number;
    }
    foreach (disaster_damage_other_fields() as $field => $label) if (mb_strlen($values[$field]) > 255) $errors[$field] = $label . ': keep it to 255 characters.';
    if (mb_strlen($values['notes']) > 2000) $errors['notes'] = 'Notes must not exceed 2,000 characters.';
    if (!isset($errors['incident_id']) && !isset($errors['area_id'])) {
        $statement = $connection->prepare('SELECT COUNT(*) FROM drr_damage_assessments WHERE incident_id = :incident AND area_id = :area AND archived_at IS NULL AND id <> :id');
        $statement->execute(['incident' => $values['incident_id'], 'area' => $values['area_id'], 'id' => (int) ($existing['id'] ?? 0)]);
        if ((int) $statement->fetchColumn() > 0) $errors['area_id'] = 'This area already has an assessment for this incident. Edit that one instead.';
    }
    foreach (['roads', 'bridges', 'crops', 'public_facilities', 'notes'] as $nullable) if ($values[$nullable] === '') $values[$nullable] = null;
    return ['values' => $values, 'errors' => $errors];
}

// ── Damage photos (private storage/disaster/, served only by disaster_photo.php) ──

function disaster_photo_root(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'disaster';
}

// Validates an uploaded photo (size, real image type checked on the server) and stores it under a random name.
// Returns the stored filename, or null when no file was chosen. Throws RuntimeException with a safe message.
function disaster_store_photo(array $file): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('The photo is larger than 5 MB.');
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) throw new RuntimeException('The photo could not be uploaded. Please try again.');
    $path = (string) $file['tmp_name'];
    if ((int) filesize($path) > DISASTER_PHOTO_MAX_BYTES) throw new RuntimeException('The photo is larger than 5 MB.');
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $extension = inventory_photo_types()[$mime] ?? null;
    if ($extension === null || @getimagesize($path) === false) throw new RuntimeException('Upload a JPG, PNG or WebP image.');
    $directory = disaster_photo_root();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('The photo folder could not be prepared.');
    $name = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($path, $directory . DIRECTORY_SEPARATOR . $name)) throw new RuntimeException('The photo could not be stored. Please try again.');
    return $name;
}

// Absolute path of a stored photo, or null. Only generated names are accepted, so no path can be injected.
function disaster_photo_path(?string $filename): ?string
{
    if ($filename === null || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) return null;
    $path = disaster_photo_root() . DIRECTORY_SEPARATOR . $filename;
    return is_file($path) ? $path : null;
}

// ── Summary cards and the incident report ──────────────────────────────────────

// Cards on the Records page. Families Served counts distinct families given relief for incidents that are still active.
function disaster_summary_counts(PDO $connection): array
{
    $counts = ['incidents' => (int) $connection->query("SELECT COUNT(*) FROM drr_records WHERE record_type = 'incident' AND archived_at IS NULL AND status IN ('planned', 'ongoing', 'monitoring')")->fetchColumn(), 'centers' => null, 'evacuees' => null, 'served' => null];
    if (disaster_prep_ready($connection)) $counts['centers'] = (int) $connection->query("SELECT COUNT(*) FROM drr_evacuation_centers WHERE archived_at IS NULL AND status IN ('open', 'full')")->fetchColumn();
    if (disaster_response_ready($connection)) $counts['evacuees'] = (int) $connection->query('SELECT COALESCE(SUM(family_members), 0) FROM drr_evacuations WHERE departed_at IS NULL AND archived_at IS NULL')->fetchColumn();
    if (disaster_assistance_ready($connection)) $counts['served'] = (int) $connection->query("SELECT COUNT(DISTINCT CONCAT(IF(a.household_id IS NULL, 'r', 'h'), COALESCE(a.household_id, a.resident_id))) FROM assistance_distributions a INNER JOIN drr_records d ON d.id = a.incident_id WHERE a.status = 'given' AND d.archived_at IS NULL AND d.status IN ('planned', 'ongoing', 'monitoring')")->fetchColumn();
    return $counts;
}

// Relief for incidents is recorded in Relief & Assistance (assistance_distributions.incident_id, migration
// 20261009_relief_assistance_complete); the old drr_relief_* tables are no longer written.
function disaster_assistance_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) $ready = (int) $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'assistance_distributions' AND COLUMN_NAME IN ('incident_id', 'status')")->fetchColumn() === 2;
    return $ready;
}

// Everything the printable Incident Report needs, read from the module's tables.
function disaster_incident_report_data(PDO $connection, int $incident_id): array
{
    $evacuees = $connection->prepare("SELECT c.name, c.capacity, COUNT(*) AS families, SUM(e.family_members) AS persons, SUM(e.departed_at IS NULL) AS families_now, SUM(IF(e.departed_at IS NULL, e.family_members, 0)) AS persons_now FROM drr_evacuations e INNER JOIN drr_evacuation_centers c ON c.id = e.center_id WHERE e.incident_id = :incident AND e.archived_at IS NULL GROUP BY c.id, c.name, c.capacity ORDER BY c.name");
    $evacuees->execute(['incident' => $incident_id]);
    $relief = [];
    $cash = ['entries' => 0, 'total' => 0];
    $served = 0;
    if (disaster_assistance_ready($connection)) {
        // Given relief & assistance records linked to the incident (Relief & Assistance module).
        $statement = $connection->prepare("SELECT i.name, x.unit, SUM(x.quantity) AS quantity, COUNT(DISTINCT a.id) AS distributions FROM assistance_items x INNER JOIN assistance_distributions a ON a.id = x.distribution_id INNER JOIN inventory_items i ON i.id = x.item_id WHERE a.incident_id = :incident AND a.status = 'given' GROUP BY i.id, i.name, x.unit ORDER BY i.name");
        $statement->execute(['incident' => $incident_id]);
        $relief = $statement->fetchAll();
        $statement = $connection->prepare("SELECT COUNT(*) AS entries, COALESCE(SUM(cash_amount), 0) AS total FROM assistance_distributions WHERE incident_id = :incident AND status = 'given' AND cash_amount IS NOT NULL");
        $statement->execute(['incident' => $incident_id]);
        $cash = $statement->fetch();
        $statement = $connection->prepare("SELECT COUNT(DISTINCT CONCAT(IF(household_id IS NULL, 'r', 'h'), COALESCE(household_id, resident_id))) FROM assistance_distributions WHERE incident_id = :incident AND status = 'given'");
        $statement->execute(['incident' => $incident_id]);
        $served = (int) $statement->fetchColumn();
    }
    $damage = $connection->prepare('SELECT g.*, a.name AS area_name FROM drr_damage_assessments g INNER JOIN drr_areas a ON a.id = g.area_id WHERE g.incident_id = :incident AND g.archived_at IS NULL ORDER BY a.sort_order, a.name');
    $damage->execute(['incident' => $incident_id]);
    return ['evacuees' => $evacuees->fetchAll(), 'relief' => $relief, 'relief_cash' => $cash, 'families_served' => $served, 'damage' => $damage->fetchAll()];
}
