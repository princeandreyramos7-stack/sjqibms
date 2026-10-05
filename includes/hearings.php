<?php
declare(strict_types=1);

require_once __DIR__ . '/complaints.php';

// Hearing management for complaints (Under Review) and blotter entries (Active). Every schedule change runs in one
// transaction that first locks the case, the venue and the assigned personnel rows (in id order), then checks for
// overlapping Scheduled/Rescheduled hearings of the same venue or personnel — so two staff members cannot book the
// same slot concurrently. Completing a hearing never changes the complaint or blotter status.

function hearings_active_statuses(): array
{
    return ['scheduled', 'rescheduled'];
}

// "complaint:12" / "blotter:5" → ['kind' => ..., 'id' => ...] or null.
function hearings_parse_case_key(string $key): ?array
{
    if (!preg_match('/^(complaint|blotter):(\d{1,18})$/', $key, $m)) return null;
    return ['kind' => $m[1], 'id' => (int) $m[2]];
}

// Loads (optionally locks) a case that may receive hearings: complaints Under Review, blotter entries Active.
function hearings_eligible_case(PDO $connection, string $kind, int $id, bool $lock = false): ?array
{
    $sql = $kind === 'blotter'
        ? 'SELECT id, blotter_number AS reference, status, incident_type AS title FROM blotter_entries WHERE id = :id'
        : 'SELECT id, case_number AS reference, status, subject AS title FROM complaint_cases WHERE id = :id';
    $statement = $connection->prepare($sql . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if (!$row) return null;
    $row['kind'] = $kind;
    $row['eligible'] = $kind === 'blotter' ? $row['status'] === 'active' : $row['status'] === 'under_review';
    return $row;
}

function hearings_eligible_cases(PDO $connection): array
{
    return $connection->query("SELECT CONCAT('complaint:', id) AS case_key, case_number AS reference, subject AS title, 'Complaint' AS kind_label FROM complaint_cases WHERE status = 'under_review' UNION ALL SELECT CONCAT('blotter:', id), blotter_number, incident_type, 'Blotter' FROM blotter_entries WHERE status = 'active' ORDER BY reference")->fetchAll();
}

function hearings_venue_options(PDO $connection): array
{
    return $connection->query('SELECT id, name FROM hearing_venues ORDER BY name')->fetchAll();
}

function hearings_active_venues(PDO $connection): array
{
    return $connection->query('SELECT id, name FROM hearing_venues WHERE is_active = 1 ORDER BY name')->fetchAll();
}

function hearings_active_personnel(PDO $connection): array
{
    return $connection->query("SELECT id, full_name, position FROM barangay_personnel WHERE status = 'active' ORDER BY full_name")->fetchAll();
}

// Validates date + start/end time inputs. Returns ['start' => ..., 'end' => ..., 'errors' => [...]].
function hearings_validate_schedule(string $date, string $start, string $end, bool $require_future = true): array
{
    $errors = [];
    $starts = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $start);
    $ends = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $end);
    $warnings = DateTimeImmutable::getLastErrors();
    if (!$starts || !$ends || ($warnings !== false && ($warnings['warning_count'] > 0 || $warnings['error_count'] > 0))) {
        $errors['schedule'] = 'Enter a valid hearing date, start time and end time.';
        return ['start' => null, 'end' => null, 'errors' => $errors];
    }
    if ($ends <= $starts) $errors['schedule'] = 'The end time must be later than the start time.';
    elseif (($ends->getTimestamp() - $starts->getTimestamp()) > 12 * 3600) $errors['schedule'] = 'A hearing session cannot be longer than 12 hours.';
    elseif ($require_future && $starts->getTimestamp() < time() - 300) $errors['schedule'] = 'The hearing must be scheduled for a future date and time.';
    return ['start' => $starts->format('Y-m-d H:i:s'), 'end' => $ends->format('Y-m-d H:i:s'), 'errors' => $errors];
}

// Locks the venue (must be active) and the given personnel (must be active) in id order; returns [venue, personnel by id].
function hearings_lock_resources(PDO $connection, int $venue_id, array $personnel_ids): array
{
    $venue = $connection->prepare('SELECT id, name, is_active FROM hearing_venues WHERE id = :id FOR UPDATE');
    $venue->execute(['id' => $venue_id]);
    $venue_row = $venue->fetch();
    if (!$venue_row || (int) $venue_row['is_active'] !== 1) throw new RuntimeException('Select an active hearing venue.');
    $people = [];
    $ids = array_values(array_unique(array_map('intval', $personnel_ids)));
    sort($ids);
    $person = $connection->prepare('SELECT id, full_name, position, status FROM barangay_personnel WHERE id = :id FOR UPDATE');
    foreach ($ids as $id) {
        $person->execute(['id' => $id]);
        $row = $person->fetch();
        if (!$row || $row['status'] !== 'active') throw new RuntimeException('One of the selected personnel is no longer active.');
        $people[$id] = $row;
    }
    return [$venue_row, $people];
}

// Overlap: existing.start < proposed.end AND existing.end > proposed.start, for active hearings only.
function hearings_find_conflicts(PDO $connection, int $venue_id, string $start, string $end, array $personnel_ids, ?int $exclude_hearing_id = null): array
{
    $conflicts = [];
    $venue = $connection->prepare("SELECT h.hearing_number, h.starts_at, h.ends_at, v.name FROM case_hearings h INNER JOIN hearing_venues v ON v.id = h.venue_id WHERE h.venue_id = :venue AND h.status IN ('scheduled', 'rescheduled') AND h.starts_at < :end_at AND h.ends_at > :start_at AND h.id <> :exclude");
    $venue->execute(['venue' => $venue_id, 'end_at' => $end, 'start_at' => $start, 'exclude' => $exclude_hearing_id ?? 0]);
    foreach ($venue->fetchAll() as $row) $conflicts[] = 'Venue "' . $row['name'] . '" is already booked ' . complaints_format_time_range($row['starts_at'], $row['ends_at']) . ' (' . $row['hearing_number'] . ').';
    $person = $connection->prepare("SELECT h.hearing_number, h.starts_at, h.ends_at, p.full_name FROM case_hearing_personnel a INNER JOIN case_hearings h ON h.id = a.hearing_id INNER JOIN barangay_personnel p ON p.id = a.personnel_id WHERE a.personnel_id = :person AND a.removed_at IS NULL AND h.status IN ('scheduled', 'rescheduled') AND h.starts_at < :end_at AND h.ends_at > :start_at AND h.id <> :exclude");
    foreach (array_unique(array_map('intval', $personnel_ids)) as $person_id) {
        $person->execute(['person' => $person_id, 'end_at' => $end, 'start_at' => $start, 'exclude' => $exclude_hearing_id ?? 0]);
        foreach ($person->fetchAll() as $row) $conflicts[] = $row['full_name'] . ' is already assigned to ' . $row['hearing_number'] . ' at ' . complaints_format_time_range($row['starts_at'], $row['ends_at']) . '.';
    }
    return $conflicts;
}

function hearings_conflict_message(array $conflicts): string
{
    return 'Schedule conflict: ' . implode(' ', $conflicts) . ' Please choose another time or venue.';
}

// Accounts that receive private hearing notifications: linked resident accounts of the participants and the linked
// accounts of actively assigned personnel. (Notification only — never access to the case.)
function hearings_notification_recipients(PDO $connection, int $hearing_id): array
{
    $statement = $connection->prepare("SELECT DISTINCT u.id FROM users u WHERE u.status = 'active' AND ((u.role = 'resident' AND u.resident_id IN (SELECT COALESCE(p.resident_id, cp.resident_id) FROM case_hearing_participants p LEFT JOIN case_persons cp ON cp.id = p.case_person_id WHERE p.hearing_id = :h1)) OR u.id IN (SELECT bp.user_id FROM case_hearing_personnel a INNER JOIN barangay_personnel bp ON bp.id = a.personnel_id WHERE a.hearing_id = :h2 AND a.removed_at IS NULL AND bp.user_id IS NOT NULL))");
    $statement->execute(['h1' => $hearing_id, 'h2' => $hearing_id]);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

// $participants: list of case_person ids (must belong to the case); $extra: list of ['name' => ..., 'role' => ...];
// $personnel: [personnel_id => assignment role].
function hearings_create(PDO $connection, array $case_key, array $values, array $participants, array $extra, array $personnel): array
{
    $connection->beginTransaction();
    try {
        $case = hearings_eligible_case($connection, $case_key['kind'], $case_key['id'], true);
        if (!$case) throw new RuntimeException('The selected case no longer exists.');
        if (!$case['eligible']) throw new RuntimeException('Hearings can be scheduled only for complaints Under Review or blotter entries that are Active.');
        [$venue] = hearings_lock_resources($connection, $values['venue_id'], array_keys($personnel));
        $conflicts = hearings_find_conflicts($connection, $values['venue_id'], $values['start'], $values['end'], array_keys($personnel));
        if ($conflicts !== []) throw new RuntimeException(hearings_conflict_message($conflicts));
        $reference = complaints_generate_reference($connection, 'case_hearings', 'hearing_number', 'HRG');
        $insert = $connection->prepare("INSERT INTO case_hearings (hearing_number, complaint_id, blotter_id, hearing_type, starts_at, ends_at, venue_id, status, notes, created_by, updated_by) VALUES (:ref, :complaint, :blotter, :type, :start_at, :end_at, :venue, 'scheduled', :notes, :created_by, :updated_by)");
        $insert->execute(['ref' => $reference, 'complaint' => $case['kind'] === 'complaint' ? $case['id'] : null, 'blotter' => $case['kind'] === 'blotter' ? $case['id'] : null, 'type' => $values['hearing_type'], 'start_at' => $values['start'], 'end_at' => $values['end'], 'venue' => $values['venue_id'], 'notes' => $values['notes'] === '' ? null : $values['notes'], 'created_by' => current_user()['id'], 'updated_by' => current_user()['id']]);
        $hearing_id = (int) $connection->lastInsertId();
        $connection->prepare("INSERT INTO case_hearing_schedule_history (hearing_id, change_type, new_starts_at, new_ends_at, new_venue_id, changed_by) VALUES (:hearing, 'initial', :start_at, :end_at, :venue, :user)")
            ->execute(['hearing' => $hearing_id, 'start_at' => $values['start'], 'end_at' => $values['end'], 'venue' => $values['venue_id'], 'user' => current_user()['id']]);
        // Participants: only persons recorded on this case.
        $column = $case['kind'] === 'blotter' ? 'blotter_id' : 'complaint_id';
        $person = $connection->prepare("SELECT id, person_role, resident_id FROM case_persons WHERE id = :id AND $column = :case_id");
        $add = $connection->prepare('INSERT INTO case_hearing_participants (hearing_id, case_person_id, participant_name, participant_role, resident_id, created_by) VALUES (:hearing, :person, :name, :role, :resident, :user)');
        foreach (array_unique(array_map('intval', $participants)) as $person_id) {
            $person->execute(['id' => $person_id, 'case_id' => $case['id']]);
            $row = $person->fetch();
            if (!$row) throw new RuntimeException('A selected participant does not belong to this case.');
            $add->execute(['hearing' => $hearing_id, 'person' => $row['id'], 'name' => null, 'role' => $row['person_role'], 'resident' => $row['resident_id'], 'user' => current_user()['id']]);
        }
        foreach ($extra as $item) $add->execute(['hearing' => $hearing_id, 'person' => null, 'name' => $item['name'], 'role' => $item['role'], 'resident' => null, 'user' => current_user()['id']]);
        $assign = $connection->prepare('INSERT INTO case_hearing_personnel (hearing_id, personnel_id, assignment_role, assigned_by) VALUES (:hearing, :person, :role, :user)');
        foreach ($personnel as $person_id => $role) $assign->execute(['hearing' => $hearing_id, 'person' => $person_id, 'role' => $role, 'user' => current_user()['id']]);
        complaints_record_history($connection, $case['kind'] === 'complaint' ? $case['id'] : null, $case['kind'] === 'blotter' ? $case['id'] : null, 'hearing_scheduled', $case['status'], $case['status'], "Hearing $reference scheduled.");
        complaints_audit($connection, $hearing_id, 'hearing_scheduled', ['reference' => $reference, 'case_reference' => $case['reference']]);
        complaints_notify($connection, hearings_notification_recipients($connection, $hearing_id), 'hearing_scheduled', 'hearing', $hearing_id, 'Hearing scheduled', "Hearing $reference is scheduled on " . complaints_format_time_range($values['start'], $values['end']) . ' at ' . $venue['name'] . '.');
        $connection->commit();
        return ['id' => $hearing_id, 'reference' => $reference];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function hearings_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $statement = $connection->prepare('SELECT h.*, v.name AS venue_name, c.case_number AS complaint_reference, c.status AS complaint_status, b.blotter_number AS blotter_reference, b.status AS blotter_status, cb.name AS canceller_name, ob.name AS outcome_recorder_name, cr.name AS creator_name FROM case_hearings h INNER JOIN hearing_venues v ON v.id = h.venue_id LEFT JOIN complaint_cases c ON c.id = h.complaint_id LEFT JOIN blotter_entries b ON b.id = h.blotter_id LEFT JOIN users cb ON cb.id = h.cancelled_by LEFT JOIN users ob ON ob.id = h.outcome_recorded_by LEFT JOIN users cr ON cr.id = h.created_by WHERE h.id = :id' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function hearings_lock(PDO $connection, int $id): array
{
    $statement = $connection->prepare('SELECT id, hearing_number, starts_at, ends_at, venue_id, status FROM case_hearings WHERE id = :id FOR UPDATE');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if (!$row) throw new RuntimeException('This hearing no longer exists.');
    return $row;
}

function hearings_active_personnel_ids(PDO $connection, int $hearing_id): array
{
    $statement = $connection->prepare('SELECT personnel_id FROM case_hearing_personnel WHERE hearing_id = :id AND removed_at IS NULL');
    $statement->execute(['id' => $hearing_id]);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

function hearings_reschedule(PDO $connection, int $hearing_id, array $values, string $reason): string
{
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 1000) throw new RuntimeException('Enter a rescheduling reason of 5 to 1,000 characters.');
    $connection->beginTransaction();
    try {
        $hearing = hearings_lock($connection, $hearing_id);
        if (!in_array($hearing['status'], hearings_active_statuses(), true)) throw new RuntimeException('Only scheduled hearings can be rescheduled.');
        if ($hearing['starts_at'] === $values['start'] && $hearing['ends_at'] === $values['end'] && (int) $hearing['venue_id'] === $values['venue_id']) throw new RuntimeException('The new schedule is the same as the current schedule.');
        $personnel = hearings_active_personnel_ids($connection, $hearing_id);
        [$venue] = hearings_lock_resources($connection, $values['venue_id'], $personnel);
        $conflicts = hearings_find_conflicts($connection, $values['venue_id'], $values['start'], $values['end'], $personnel, $hearing_id);
        if ($conflicts !== []) throw new RuntimeException(hearings_conflict_message($conflicts));
        $connection->prepare("INSERT INTO case_hearing_schedule_history (hearing_id, change_type, previous_starts_at, previous_ends_at, previous_venue_id, new_starts_at, new_ends_at, new_venue_id, reason, changed_by) VALUES (:hearing, 'rescheduled', :prev_start, :prev_end, :prev_venue, :new_start, :new_end, :new_venue, :reason, :user)")
            ->execute(['hearing' => $hearing_id, 'prev_start' => $hearing['starts_at'], 'prev_end' => $hearing['ends_at'], 'prev_venue' => $hearing['venue_id'], 'new_start' => $values['start'], 'new_end' => $values['end'], 'new_venue' => $values['venue_id'], 'reason' => $reason, 'user' => current_user()['id']]);
        $history_id = (int) $connection->lastInsertId();
        $update = $connection->prepare("UPDATE case_hearings SET starts_at = :start_at, ends_at = :end_at, venue_id = :venue, status = 'rescheduled', updated_by = :user WHERE id = :id AND status IN ('scheduled', 'rescheduled')");
        $update->execute(['start_at' => $values['start'], 'end_at' => $values['end'], 'venue' => $values['venue_id'], 'user' => current_user()['id'], 'id' => $hearing_id]);
        if ($update->rowCount() !== 1) throw new RuntimeException('This hearing was already updated.');
        complaints_audit($connection, $hearing_id, 'hearing_rescheduled', ['reference' => $hearing['hearing_number']]);
        complaints_notify($connection, hearings_notification_recipients($connection, $hearing_id), 'hearing_rescheduled', 'hearing_schedule', $history_id, 'Hearing rescheduled', "Hearing {$hearing['hearing_number']} was moved to " . complaints_format_time_range($values['start'], $values['end']) . ' at ' . $venue['name'] . '.');
        $connection->commit();
        return (string) $hearing['hearing_number'];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function hearings_cancel(PDO $connection, int $hearing_id, string $reason): string
{
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 1000) throw new RuntimeException('Enter a cancellation reason of 5 to 1,000 characters.');
    $connection->beginTransaction();
    try {
        $hearing = hearings_lock($connection, $hearing_id);
        if (!in_array($hearing['status'], hearings_active_statuses(), true)) throw new RuntimeException('Only scheduled hearings can be cancelled.');
        $recipients = hearings_notification_recipients($connection, $hearing_id);
        $update = $connection->prepare("UPDATE case_hearings SET status = 'cancelled', cancelled_by = :user, cancelled_at = NOW(), cancellation_reason = :reason, updated_by = :updater WHERE id = :id AND status IN ('scheduled', 'rescheduled')");
        $update->execute(['user' => current_user()['id'], 'reason' => $reason, 'updater' => current_user()['id'], 'id' => $hearing_id]);
        if ($update->rowCount() !== 1) throw new RuntimeException('This hearing was already updated.');
        complaints_audit($connection, $hearing_id, 'hearing_cancelled', ['reference' => $hearing['hearing_number']]);
        complaints_notify($connection, $recipients, 'hearing_cancelled', 'hearing', $hearing_id, 'Hearing cancelled', "Hearing {$hearing['hearing_number']} scheduled on " . complaints_format_time_range($hearing['starts_at'], $hearing['ends_at']) . ' was cancelled.');
        $connection->commit();
        return (string) $hearing['hearing_number'];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

// Only after the hearing has actually started; never changes the related complaint or blotter.
function hearings_record_outcome(PDO $connection, int $hearing_id, string $summary): string
{
    if (mb_strlen($summary) < 10 || mb_strlen($summary) > 5000) throw new RuntimeException('Enter the documented hearing outcome (10 to 5,000 characters).');
    $connection->beginTransaction();
    try {
        $hearing = hearings_lock($connection, $hearing_id);
        if (!in_array($hearing['status'], hearings_active_statuses(), true)) throw new RuntimeException('An outcome can be recorded only for a scheduled hearing.');
        if (strtotime($hearing['starts_at']) > time()) throw new RuntimeException('This hearing has not taken place yet. Record the outcome after it starts.');
        $update = $connection->prepare("UPDATE case_hearings SET status = 'completed', outcome_summary = :summary, outcome_recorded_by = :user, outcome_recorded_at = NOW(), updated_by = :updater WHERE id = :id AND status IN ('scheduled', 'rescheduled') AND starts_at <= NOW()");
        $update->execute(['summary' => $summary, 'user' => current_user()['id'], 'updater' => current_user()['id'], 'id' => $hearing_id]);
        if ($update->rowCount() !== 1) throw new RuntimeException('This hearing was already updated.');
        complaints_audit($connection, $hearing_id, 'hearing_outcome_recorded', ['reference' => $hearing['hearing_number']]);
        $connection->commit();
        return (string) $hearing['hearing_number'];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

// $entries: [participant_id => ['status' => ..., 'notes' => ...]]. Only for sessions that have started and were not cancelled.
function hearings_record_attendance(PDO $connection, int $hearing_id, array $entries): string
{
    $statuses = array_keys(hearing_attendance_labels());
    $connection->beginTransaction();
    try {
        $hearing = hearings_lock($connection, $hearing_id);
        if ($hearing['status'] === 'cancelled') throw new RuntimeException('Attendance cannot be recorded for a cancelled hearing.');
        if (strtotime($hearing['starts_at']) > time()) throw new RuntimeException('Attendance can be recorded only once the hearing session has started.');
        $update = $connection->prepare('UPDATE case_hearing_participants SET attendance_status = :status, attendance_recorded_at = :recorded_at, attendance_recorded_by = :recorded_by, attendance_notes = :notes WHERE id = :id AND hearing_id = :hearing');
        $changed = 0;
        foreach ($entries as $participant_id => $entry) {
            $status = (string) ($entry['status'] ?? '');
            if (!in_array($status, $statuses, true)) throw new RuntimeException('Choose a valid attendance status for every participant.');
            $notes = complaints_text($entry['notes'] ?? '', 500);
            $recorded = $status !== 'not_recorded';
            $update->execute(['status' => $status, 'recorded_at' => $recorded ? date('Y-m-d H:i:s') : null, 'recorded_by' => $recorded ? current_user()['id'] : null, 'notes' => $notes === '' ? null : $notes, 'id' => (int) $participant_id, 'hearing' => $hearing_id]);
            $changed += $update->rowCount();
        }
        complaints_audit($connection, $hearing_id, 'hearing_attendance_recorded', ['reference' => $hearing['hearing_number'], 'updated' => $changed]);
        $connection->commit();
        return (string) $hearing['hearing_number'];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function hearings_assign_personnel(PDO $connection, int $hearing_id, int $personnel_id, string $role): string
{
    if (mb_strlen($role) < 2 || mb_strlen($role) > 80) throw new RuntimeException('Enter an assignment role of 2 to 80 characters.');
    $connection->beginTransaction();
    try {
        $hearing = hearings_lock($connection, $hearing_id);
        if (!in_array($hearing['status'], hearings_active_statuses(), true)) throw new RuntimeException('Personnel can be assigned only to scheduled hearings.');
        hearings_lock_resources($connection, (int) $hearing['venue_id'], [$personnel_id]);
        if (in_array($personnel_id, hearings_active_personnel_ids($connection, $hearing_id), true)) throw new RuntimeException('This person is already assigned to the hearing.');
        $conflicts = hearings_find_conflicts($connection, 0, $hearing['starts_at'], $hearing['ends_at'], [$personnel_id], $hearing_id);
        if ($conflicts !== []) throw new RuntimeException(hearings_conflict_message($conflicts));
        $connection->prepare('INSERT INTO case_hearing_personnel (hearing_id, personnel_id, assignment_role, assigned_by) VALUES (:hearing, :person, :role, :user)')
            ->execute(['hearing' => $hearing_id, 'person' => $personnel_id, 'role' => $role, 'user' => current_user()['id']]);
        complaints_audit($connection, $hearing_id, 'hearing_personnel_assigned', ['reference' => $hearing['hearing_number']]);
        $connection->commit();
        return (string) $hearing['hearing_number'];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function hearings_remove_personnel(PDO $connection, int $hearing_id, int $assignment_id, string $reason): string
{
    if (mb_strlen($reason) < 5 || mb_strlen($reason) > 255) throw new RuntimeException('Enter a removal reason of 5 to 255 characters.');
    $connection->beginTransaction();
    try {
        $hearing = hearings_lock($connection, $hearing_id);
        if (!in_array($hearing['status'], hearings_active_statuses(), true)) throw new RuntimeException('Assignments of completed or cancelled hearings are kept as history.');
        $update = $connection->prepare('UPDATE case_hearing_personnel SET removed_at = NOW(), removed_by = :user, removal_reason = :reason WHERE id = :id AND hearing_id = :hearing AND removed_at IS NULL');
        $update->execute(['user' => current_user()['id'], 'reason' => $reason, 'id' => $assignment_id, 'hearing' => $hearing_id]);
        if ($update->rowCount() !== 1) throw new RuntimeException('This assignment was already removed.');
        complaints_audit($connection, $hearing_id, 'hearing_personnel_removed', ['reference' => $hearing['hearing_number']]);
        $connection->commit();
        return (string) $hearing['hearing_number'];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function hearings_participants(PDO $connection, int $hearing_id): array
{
    $statement = $connection->prepare('SELECT p.id, p.participant_role, p.attendance_status, p.attendance_recorded_at, p.attendance_notes, COALESCE(cp.full_name, p.participant_name) AS name, u.name AS recorder_name FROM case_hearing_participants p LEFT JOIN case_persons cp ON cp.id = p.case_person_id LEFT JOIN users u ON u.id = p.attendance_recorded_by WHERE p.hearing_id = :id ORDER BY FIELD(p.participant_role, \'complainant\', \'respondent\', \'witness\', \'representative\', \'other\'), p.id');
    $statement->execute(['id' => $hearing_id]);
    return $statement->fetchAll();
}

function hearings_personnel_assignments(PDO $connection, int $hearing_id): array
{
    $statement = $connection->prepare('SELECT a.id, a.assignment_role, a.assigned_at, a.removed_at, a.removal_reason, p.full_name, p.position, ab.name AS assigner_name, rb.name AS remover_name FROM case_hearing_personnel a INNER JOIN barangay_personnel p ON p.id = a.personnel_id LEFT JOIN users ab ON ab.id = a.assigned_by LEFT JOIN users rb ON rb.id = a.removed_by WHERE a.hearing_id = :id ORDER BY a.removed_at IS NOT NULL, a.assigned_at');
    $statement->execute(['id' => $hearing_id]);
    return $statement->fetchAll();
}

function hearings_schedule_history(PDO $connection, int $hearing_id): array
{
    $statement = $connection->prepare('SELECT s.change_type, s.previous_starts_at, s.previous_ends_at, pv.name AS previous_venue, s.new_starts_at, s.new_ends_at, nv.name AS new_venue, s.reason, s.changed_at, u.name AS actor_name FROM case_hearing_schedule_history s LEFT JOIN hearing_venues pv ON pv.id = s.previous_venue_id INNER JOIN hearing_venues nv ON nv.id = s.new_venue_id LEFT JOIN users u ON u.id = s.changed_by WHERE s.hearing_id = :id ORDER BY s.changed_at, s.id');
    $statement->execute(['id' => $hearing_id]);
    return $statement->fetchAll();
}
