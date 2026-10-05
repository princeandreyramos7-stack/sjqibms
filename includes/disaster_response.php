<?php
declare(strict_types=1);

require_once __DIR__ . '/disaster_prep.php';

// Disaster Management — response (migration 20261001_disaster_response): evacuation tracking and disaster alerts.
// A "family" is a household; a resident without a current household is checked in alone. The same family can be
// checked in at only one center at a time (checked here under a lock and guarded by a UNIQUE key in the table).
// A center is marked Full automatically when its evacuees reach the capacity, and Open again when space frees up.

function disaster_response_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $check = $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('drr_records', 'drr_evacuation_centers', 'drr_evacuations', 'drr_alerts', 'drr_alert_areas')");
        $ready = (int) $check->fetchColumn() === 5;
    }
    return $ready;
}

function disaster_format_datetime(?string $value): string
{
    if (!$value) return '';
    $date = date_create($value);
    return $date ? $date->format('M j, Y g:i A') : $value;
}

// "2026-09-26T14:30" (datetime-local) → "2026-09-26 14:30:00", or null when invalid.
function disaster_valid_datetime(string $value): ?string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    return $parsed && $parsed->format('Y-m-d\TH:i') === $value ? $parsed->format('Y-m-d H:i:s') : null;
}

// Incidents a family can be checked in for (or relief given for): incident records that are not archived, cancelled or
// completed, plus the one already on a record.
function disaster_open_incidents(PDO $connection, ?int $include_id = null): array
{
    $statement = $connection->prepare("SELECT d.id, d.reference_no, d.title, d.status, d.record_date, a.name AS area_name FROM drr_records d INNER JOIN drr_areas a ON a.id = d.area_id WHERE d.record_type = 'incident' AND ((d.archived_at IS NULL AND d.status IN ('planned', 'ongoing', 'monitoring')) OR d.id = :include) ORDER BY d.record_date DESC, d.id DESC");
    $statement->execute(['include' => $include_id ?? 0]);
    return $statement->fetchAll();
}

// All incident records (any status) for filters and reports.
function disaster_all_incidents(PDO $connection): array
{
    return $connection->query("SELECT d.id, d.reference_no, d.title, d.status, d.archived_at FROM drr_records d WHERE d.record_type = 'incident' ORDER BY d.record_date DESC, d.id DESC")->fetchAll();
}

function disaster_incident_label(array $incident): string
{
    return $incident['reference_no'] . ' — ' . $incident['title'];
}

// Evacuees currently in each center (open check-ins only): persons and families. Keyed by center id.
function disaster_center_occupancy(PDO $connection): array
{
    $rows = $connection->query('SELECT center_id, COALESCE(SUM(family_members), 0) AS persons, COUNT(*) AS families FROM drr_evacuations WHERE departed_at IS NULL AND archived_at IS NULL GROUP BY center_id')->fetchAll();
    $occupancy = [];
    foreach ($rows as $row) $occupancy[(int) $row['center_id']] = ['persons' => (int) $row['persons'], 'families' => (int) $row['families']];
    return $occupancy;
}

function disaster_center_persons(PDO $connection, int $center_id): int
{
    $statement = $connection->prepare('SELECT COALESCE(SUM(family_members), 0) FROM drr_evacuations WHERE center_id = :center AND departed_at IS NULL AND archived_at IS NULL');
    $statement->execute(['center' => $center_id]);
    return (int) $statement->fetchColumn();
}

// Re-applies the capacity rule to a (locked) center after evacuees arrive or leave: Full when the evacuees reach the
// capacity; back to Open when a Full center has space again. A Closed center is left as it is.
function disaster_apply_capacity_rule(PDO $connection, array $center): ?string
{
    $persons = disaster_center_persons($connection, (int) $center['id']);
    $status = $center['status'];
    if ($status === 'open' && $persons >= (int) $center['capacity']) $status = 'full';
    elseif ($status === 'full' && $persons < (int) $center['capacity']) $status = 'open';
    if ($status === $center['status']) return null;
    $connection->prepare('UPDATE drr_evacuation_centers SET status = :status, updated_by = :user WHERE id = :id')->execute(['status' => $status, 'user' => current_user()['id'], 'id' => $center['id']]);
    disaster_prep_audit($connection, 'center', (int) $center['id'], 'disaster_center_updated', ['name' => $center['name'], 'changed_fields' => ['status'], 'status_from' => $center['status'], 'status_to' => $status, 'reason' => 'capacity rule (' . $persons . ' of ' . $center['capacity'] . ' persons)']);
    return $status;
}

// ── Families ───────────────────────────────────────────────────────────────────

// The family a resident belongs to: their current primary household (with its current members), or the resident alone.
function disaster_family_for_resident(PDO $connection, int $resident_id): ?array
{
    $statement = $connection->prepare("SELECT r.id, r.first_name, r.middle_name, r.last_name, r.suffix, r.purok, r.status, h.id AS household_id, h.household_no, h.household_head_resident_id FROM residents r LEFT JOIN resident_households rh ON rh.resident_id = r.id AND rh.is_primary = 1 AND rh.left_at IS NULL LEFT JOIN households h ON h.id = rh.household_id WHERE r.id = :id LIMIT 1");
    $statement->execute(['id' => $resident_id]);
    $resident = $statement->fetch();
    if (!$resident) return null;
    if ($resident['household_id'] === null) return ['household_id' => null, 'resident_id' => (int) $resident['id'], 'key' => 'r' . $resident['id'], 'label' => residents_full_name($resident), 'members' => 1, 'resident' => $resident];
    $count = $connection->prepare("SELECT COUNT(*) FROM resident_households rh INNER JOIN residents r ON r.id = rh.resident_id WHERE rh.household_id = :household AND rh.left_at IS NULL AND r.status = 'active'");
    $count->execute(['household' => $resident['household_id']]);
    return ['household_id' => (int) $resident['household_id'], 'resident_id' => null, 'key' => 'h' . $resident['household_id'], 'label' => disaster_household_label($connection, (int) $resident['household_id']), 'members' => max(1, (int) $count->fetchColumn()), 'resident' => $resident];
}

// "HH-0001 · Dela Cruz family (head: Juan Dela Cruz)" style label.
function disaster_household_label(PDO $connection, int $household_id): string
{
    $statement = $connection->prepare('SELECT h.household_no, r.first_name, r.middle_name, r.last_name, r.suffix FROM households h LEFT JOIN residents r ON r.id = h.household_head_resident_id WHERE h.id = :id');
    $statement->execute(['id' => $household_id]);
    $row = $statement->fetch();
    if (!$row) return 'Household #' . $household_id;
    return $row['household_no'] . ($row['first_name'] ? ' · Head: ' . residents_full_name($row) : ' · No designated head');
}

// SQL expression (on alias e) naming the family of an evacuation / relief row, and the joins it needs.
function disaster_family_sql(string $alias): array
{
    return [
        "CASE WHEN $alias.household_id IS NOT NULL THEN CONCAT(fh.household_no, IF(fhr.id IS NULL, '', CONCAT(' · Head: ', CONCAT_WS(' ', fhr.first_name, fhr.last_name)))) ELSE CONCAT_WS(' ', fr.first_name, fr.middle_name, fr.last_name, fr.suffix) END",
        " LEFT JOIN households fh ON fh.id = $alias.household_id LEFT JOIN residents fhr ON fhr.id = fh.household_head_resident_id LEFT JOIN residents fr ON fr.id = $alias.resident_id",
    ];
}

// The family's current check-in (not departed, not archived), with the center name, or null.
function disaster_open_checkin(PDO $connection, string $family_key, bool $lock = false): ?array
{
    $statement = $connection->prepare('SELECT e.*, c.name AS center_name FROM drr_evacuations e INNER JOIN drr_evacuation_centers c ON c.id = e.center_id WHERE e.open_family_key = :key LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['key' => $family_key]);
    return $statement->fetch() ?: null;
}

function disaster_evacuation_find(PDO $connection, int $id, bool $lock = false): ?array
{
    [$family, $joins] = disaster_family_sql('e');
    $statement = $connection->prepare("SELECT e.*, $family AS family_label, c.name AS center_name, d.reference_no FROM drr_evacuations e INNER JOIN drr_evacuation_centers c ON c.id = e.center_id INNER JOIN drr_records d ON d.id = e.incident_id$joins WHERE e.id = :id LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function disaster_center_lock(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare('SELECT * FROM drr_evacuation_centers WHERE id = :id FOR UPDATE');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// Check-in: validates and saves inside one transaction with the center row locked (so two check-ins at the same moment
// cannot overfill it). Returns ['errors' => [...], 'values' => [...]] or ['id' => …, 'message' => …].
function disaster_checkin(PDO $connection, array $input): array
{
    $values = [
        'incident_id' => trim((string) ($input['incident_id'] ?? '')),
        'center_id' => trim((string) ($input['center_id'] ?? '')),
        'resident_id' => trim((string) ($input['resident_id'] ?? '')),
        'family_members' => trim((string) ($input['family_members'] ?? '')),
        'arrived_at' => trim((string) ($input['arrived_at'] ?? '')),
        'remarks' => residents_collapse($input['remarks'] ?? ''),
    ];
    $errors = [];
    $incident_id = filter_var($values['incident_id'], FILTER_VALIDATE_INT);
    $open_ids = array_map(static fn (array $i): int => (int) $i['id'], disaster_open_incidents($connection));
    if (!$incident_id || !in_array($incident_id, $open_ids, true)) $errors['incident_id'] = 'Select an ongoing incident.';
    $resident_id = filter_var($values['resident_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $family = $resident_id ? disaster_family_for_resident($connection, $resident_id) : null;
    if ($family === null) $errors['resident_id'] = 'Select a resident from the resident records.';
    elseif ($family['resident']['status'] !== 'active') $errors['resident_id'] = 'Only active residents can be checked in.';
    $members = filter_var($values['family_members'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
    if ($members === false) $errors['family_members'] = 'Enter the number of family members checked in (1 to 50).';
    $arrived = disaster_valid_datetime($values['arrived_at']);
    if ($arrived === null) $errors['arrived_at'] = 'Enter the arrival date and time.';
    elseif ($arrived > date('Y-m-d H:i:s', time() + 300)) $errors['arrived_at'] = 'The arrival time cannot be in the future.';
    elseif ($arrived < date('Y-m-d H:i:s', strtotime('-1 year'))) $errors['arrived_at'] = 'Enter an arrival time within the past year.';
    if (mb_strlen($values['remarks']) > 500) $errors['remarks'] = 'Keep the remarks to 500 characters.';
    $center_id = filter_var($values['center_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$center_id) $errors['center_id'] = 'Select an evacuation center.';
    if ($errors !== []) return ['errors' => $errors, 'values' => $values];

    $connection->beginTransaction();
    try {
        $center = disaster_center_lock($connection, $center_id);
        if ($center === null || $center['archived_at'] !== null) throw new DomainException('center_id|Select an evacuation center.');
        if ($center['status'] === 'closed') throw new DomainException('center_id|' . $center['name'] . ' is Closed. Open the center first (Evacuation Centers tab) or choose another center.');
        $current = disaster_center_persons($connection, $center_id);
        $space = (int) $center['capacity'] - $current;
        if ($center['status'] === 'full' || $space <= 0) throw new DomainException('center_id|' . $center['name'] . ' is Full (' . $current . ' of ' . $center['capacity'] . ' persons). Choose another center.');
        if ($members > $space) throw new DomainException('family_members|' . $center['name'] . ' has space for only ' . $space . ' more person' . ($space === 1 ? '' : 's') . '. Check in the rest at another center.');
        $existing = disaster_open_checkin($connection, $family['key'], true);
        if ($existing !== null) throw new DomainException('resident_id|This family is already checked in at ' . $existing['center_name'] . ' since ' . disaster_format_datetime($existing['arrived_at']) . '. Check them out there first.');
        $user = current_user()['id'];
        $connection->prepare('INSERT INTO drr_evacuations (incident_id, center_id, household_id, resident_id, family_members, arrived_at, remarks, created_by, updated_by) VALUES (:incident, :center, :household, :resident, :members, :arrived, :remarks, :user, :updater)')
            ->execute(['incident' => $incident_id, 'center' => $center_id, 'household' => $family['household_id'], 'resident' => $family['resident_id'], 'members' => $members, 'arrived' => $arrived, 'remarks' => $values['remarks'] === '' ? null : $values['remarks'], 'user' => $user, 'updater' => $user]);
        $id = (int) $connection->lastInsertId();
        residents_audit($connection, 'disaster_evacuation', $id, 'disaster_evacuee_checked_in', ['name' => $family['label'], 'center' => $center['name'], 'persons' => $members]);
        $new_status = disaster_apply_capacity_rule($connection, $center);
        $connection->commit();
        return ['id' => $id, 'message' => $family['label'] . ' (' . $members . ' person' . ($members === 1 ? '' : 's') . ') checked in at ' . $center['name'] . '.' . ($new_status === 'full' ? ' The center is now Full.' : '')];
    } catch (DomainException $exception) {
        $connection->rollBack();
        [$field, $message] = explode('|', $exception->getMessage(), 2);
        return ['errors' => [$field => $message], 'values' => $values];
    } catch (PDOException $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        if (($exception->errorInfo[1] ?? 0) === 1062) return ['errors' => ['resident_id' => 'This family was checked in by another user at the same moment.'], 'values' => $values];
        throw $exception;
    }
}

// ── Alerts ─────────────────────────────────────────────────────────────────────

function disaster_alert_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $statement = $connection->prepare('SELECT al.*, d.reference_no, d.title AS incident_title, cu.name AS created_by_name, lu.name AS lifted_by_name FROM drr_alerts al LEFT JOIN drr_records d ON d.id = al.incident_id LEFT JOIN users cu ON cu.id = al.created_by LEFT JOIN users lu ON lu.id = al.lifted_by WHERE al.id = :id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function disaster_alert_areas(PDO $connection, int $alert_id): array
{
    $statement = $connection->prepare('SELECT a.id, a.name FROM drr_alert_areas x INNER JOIN drr_areas a ON a.id = x.area_id WHERE x.alert_id = :alert ORDER BY a.sort_order, a.name');
    $statement->execute(['alert' => $alert_id]);
    return $statement->fetchAll();
}

function disaster_alert_status(array $alert): string
{
    return $alert['archived_at'] !== null ? 'withdrawn' : ($alert['lifted_at'] !== null ? 'lifted' : 'active');
}

function disaster_alert_status_badge(array $alert): string
{
    $status = disaster_alert_status($alert);
    return '<span class="resident-status resident-status-' . ['active' => 'deceased', 'lifted' => 'active', 'withdrawn' => 'inactive'][$status] . '">' . ['active' => 'Active', 'lifted' => 'Lifted', 'withdrawn' => 'Withdrawn'][$status] . '</span>';
}

function disaster_alert_validate(PDO $connection, array $input): array
{
    $values = [
        'title' => residents_collapse($input['title'] ?? ''),
        'message' => trim((string) ($input['message'] ?? '')),
        'alert_level' => (string) ($input['alert_level'] ?? ''),
        'incident_id' => trim((string) ($input['incident_id'] ?? '')),
        'areas' => array_values(array_unique(array_filter(array_map(static fn ($v): int => (int) $v, (array) ($input['areas'] ?? []))))),
    ];
    $errors = [];
    if (mb_strlen($values['title']) < 5 || mb_strlen($values['title']) > 150) $errors['title'] = 'Enter a title of 5 to 150 characters.';
    if (mb_strlen($values['message']) < 10 || mb_strlen($values['message']) > 2000) $errors['message'] = 'Enter the message (10 to 2,000 characters).';
    if (!array_key_exists($values['alert_level'], disaster_alert_levels())) $errors['alert_level'] = 'Select a warning level.';
    if ($values['incident_id'] !== '') {
        $open_ids = array_map(static fn (array $i): int => (int) $i['id'], disaster_open_incidents($connection));
        $incident = filter_var($values['incident_id'], FILTER_VALIDATE_INT);
        if (!$incident || !in_array($incident, $open_ids, true)) $errors['incident_id'] = 'Select an ongoing incident, or leave it blank.';
        else $values['incident_id'] = $incident;
    }
    $active_areas = array_map(static fn (array $a): int => (int) $a['id'], disaster_areas($connection));
    if ($values['areas'] === []) $errors['areas'] = 'Select at least one affected area.';
    elseif (array_diff($values['areas'], $active_areas) !== []) $errors['areas'] = 'Select areas from the list.';
    if ($values['incident_id'] === '') $values['incident_id'] = null;
    return ['values' => $values, 'errors' => $errors];
}

// Short text for the notification bell (user_notifications.message is at most 500 characters).
function disaster_alert_notice(array $values, array $area_names): array
{
    $level = disaster_alert_levels()[$values['alert_level']] ?? '';
    $message = 'Areas: ' . implode(', ', $area_names) . '. ' . preg_replace('/\s+/u', ' ', $values['message']);
    return [mb_substr($level . ': ' . $values['title'], 0, 200), mb_strimwidth($message, 0, 500, '…')];
}

// Sends the alert to every active account (staff and residents) through the notification bell.
function disaster_alert_notify(PDO $connection, int $alert_id, string $category, string $title, string $message): int
{
    $statement = $connection->prepare("INSERT IGNORE INTO user_notifications (recipient_user_id, category, entity_type, entity_id, title, message) SELECT id, :category, 'disaster_alert', :alert, :title, :message FROM users WHERE status = 'active'");
    $statement->execute(['category' => $category, 'alert' => $alert_id, 'title' => $title, 'message' => $message]);
    return $statement->rowCount();
}
