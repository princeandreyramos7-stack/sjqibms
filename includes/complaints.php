<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/residents.php';

// ── Complaints & Blotter (confidential) ─────────────────────────────────────────────────────────────
// Administration: Super Admin and Barangay Secretary only ('complaints.manage'). Residents use their own verified
// profile for online complaints and see only their own complaints. Hearing personnel assignments and notifications
// never grant access. Audit rows (entity_type 'case') hold references and statuses only — never names, narratives,
// contact details or evidence details; confidential notes live in case_status_history.

const COMPLAINTS_PER_PAGE = 10;

// Complaints & Blotter pages never end on a blank page: an uncaught database/server error shows a safe notice
// (no SQL, paths or case data) and the details go to the PHP error log only.
set_exception_handler(static function (Throwable $error): void {
    error_log('[SJQIBMS complaints] ' . get_class($error) . ': ' . $error->getMessage() . ' in ' . basename($error->getFile()) . ':' . $error->getLine());
    if (!headers_sent()) { http_response_code(503); header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); }
    $database = $error instanceof PDOException;
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Service unavailable | SJQIBMS</title>'
        . '<style>body{margin:0;display:grid;min-height:100vh;place-items:center;background:#f5f8f6;color:#153f35;font-family:Arial,sans-serif}main{max-width:520px;margin:16px;padding:28px;border:1px solid #dfe9e3;border-radius:12px;background:#fff}h1{margin:0 0 10px;font-size:20px}p{margin:0 0 12px;color:#647b72;line-height:1.5}a{color:#246b55;font-weight:700}</style></head><body><main>'
        . '<h1>' . ($database ? 'Database unavailable' : 'Something went wrong') . '</h1>'
        . '<p>' . ($database ? 'SJQIBMS cannot reach its database right now, so this page cannot be shown. No changes were saved.' : 'This page could not be completed. No changes were saved.') . '</p>'
        . '<p>Please try again in a moment. If this continues, contact the system administrator.</p><p><a href="dashboard.php">Return to the dashboard</a></p></main></body></html>';
});
const COMPLAINTS_EVIDENCE_MAX_BYTES = 10 * 1024 * 1024; // default until the barangay approves an evidence policy

function complaints_can_manage(): bool
{
    return can_access_navigation('complaints') && role_can('complaints.manage');
}

function complaints_require_manage(): void
{
    require_auth();
    if (!complaints_can_manage()) { http_response_code(403); exit('Access denied.'); }
}

function complaints_can_submit_online(): bool
{
    return can_access_navigation('my_complaints');
}

function complaints_foundation_tables(): array
{
    return ['barangay_personnel', 'hearing_venues', 'blotter_entries', 'case_persons', 'case_status_history', 'case_hearings',
        'case_hearing_schedule_history', 'case_hearing_participants', 'case_hearing_personnel', 'case_attachments', 'case_attachment_access_log'];
}

// True only when every foundation table exists AND complaint_cases has the approved 'pending_review' status.
function complaints_schema_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    $tables = complaints_foundation_tables();
    $statement = $connection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . implode(', ', array_fill(0, count($tables), '?')) . ')');
    $statement->execute($tables);
    $status = $connection->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'complaint_cases' AND COLUMN_NAME = 'status'")->fetchColumn();
    return $ready = (int) $statement->fetchColumn() === count($tables) && is_string($status) && str_contains($status, "'pending_review'");
}

function complaints_require_schema(PDO $connection): void
{
    if (!complaints_schema_ready($connection)) { http_response_code(503); exit('The Complaints & Blotter database update is not installed.'); }
}

// ── Verified resident profile (online complaints) ────────────────────────────────────────────────────
// Both sides of the account ↔ profile link must agree (users.resident_id and residents.user_id) and the account must be an
// active resident account. Anything else is treated as ambiguous and online submission is blocked.
function complaints_resident_profile(PDO $connection): array
{
    $statement = $connection->prepare("SELECT r.id, r.user_id, r.first_name, r.middle_name, r.last_name, r.suffix, r.address, r.purok, r.contact_number, r.status, u.status AS account_status, u.role FROM users u INNER JOIN residents r ON r.id = u.resident_id WHERE u.id = :user_id LIMIT 1");
    $statement->execute(['user_id' => current_user()['id']]);
    $row = $statement->fetch();
    if (!$row) return ['state' => 'unlinked', 'profile' => null];
    if ((int) $row['user_id'] !== (int) current_user()['id'] || $row['role'] !== 'resident' || $row['account_status'] !== 'active') return ['state' => 'ambiguous', 'profile' => null];
    if ($row['status'] !== 'active') return ['state' => 'inactive', 'profile' => $row];
    return ['state' => 'verified', 'profile' => $row];
}

// ── Labels and badges ────────────────────────────────────────────────────────────────────────────────
function complaints_status_labels(bool $with_legacy = false): array
{
    $labels = ['pending_review' => 'Pending Review', 'under_review' => 'Under Review', 'resolved' => 'Resolved', 'closed' => 'Closed'];
    return $with_legacy ? $labels + ['open' => 'Open (legacy)', 'for_hearing' => 'For Hearing (legacy)', 'settled' => 'Settled (legacy)', 'dismissed' => 'Dismissed (legacy)'] : $labels;
}

function blotter_status_labels(): array
{
    return ['recorded' => 'Recorded', 'active' => 'Active', 'resolved' => 'Resolved', 'closed' => 'Closed'];
}

function hearing_status_labels(): array
{
    return ['scheduled' => 'Scheduled', 'rescheduled' => 'Rescheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
}

function hearing_attendance_labels(): array
{
    return ['not_recorded' => 'Not Recorded', 'present' => 'Present', 'absent' => 'Absent', 'excused' => 'Excused'];
}

function case_person_role_labels(): array
{
    return ['complainant' => 'Complainant', 'respondent' => 'Respondent', 'witness' => 'Witness', 'other' => 'Other involved person'];
}

function complaints_badge(string $kind, string $status): string
{
    $tones = [
        'complaint' => ['pending_review' => 'pending', 'open' => 'pending', 'under_review' => 'moved', 'for_hearing' => 'moved', 'resolved' => 'active', 'settled' => 'active', 'closed' => 'inactive', 'dismissed' => 'inactive'],
        'blotter' => ['recorded' => 'pending', 'active' => 'moved', 'resolved' => 'active', 'closed' => 'inactive'],
        'hearing' => ['scheduled' => 'moved', 'rescheduled' => 'pending', 'completed' => 'active', 'cancelled' => 'inactive'],
        'attendance' => ['not_recorded' => 'inactive', 'present' => 'active', 'absent' => 'deceased', 'excused' => 'pending'],
    ][$kind] ?? [];
    $labels = match ($kind) {
        'complaint' => complaints_status_labels(true),
        'blotter' => blotter_status_labels(),
        'hearing' => hearing_status_labels(),
        default => hearing_attendance_labels(),
    };
    return '<span class="resident-status resident-status-' . e($tones[$status] ?? 'inactive') . '">' . e($labels[$status] ?? ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

function complaints_source_label(?string $source): string
{
    return match ($source) { 'online' => 'Online (resident account)', 'staff' => 'Staff-assisted', default => 'Not recorded' };
}

function complaints_format_datetime(?string $value): string
{
    if (!$value) return '';
    $date = date_create($value);
    return $date ? $date->format('M j, Y g:i A') : $value;
}

function complaints_format_time_range(string $start, string $end): string
{
    $a = date_create($start); $b = date_create($end);
    if (!$a || !$b) return $start . ' – ' . $end;
    return $a->format('M j, Y g:i A') . ' – ' . ($a->format('Y-m-d') === $b->format('Y-m-d') ? $b->format('g:i A') : $b->format('M j, Y g:i A'));
}

// Accepts <input type="datetime-local"> values; returns 'Y-m-d H:i:s' or null.
function complaints_parse_datetime_local(string $value): ?string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) return null;
    return $date->format('Y-m-d H:i:s');
}

function complaints_datetime_local(?string $value): string
{
    if (!$value) return '';
    $date = date_create($value);
    return $date ? $date->format('Y-m-d\TH:i') : '';
}

function complaints_text(mixed $value, int $max): string
{
    $text = trim(str_replace("\r\n", "\n", (string) $value));
    return mb_substr($text, 0, $max);
}

// ── References, history, audit ───────────────────────────────────────────────────────────────────────
// Random, database-checked references; the UNIQUE key on each table is the final guarantee.
function complaints_generate_reference(PDO $connection, string $table, string $column, string $prefix): string
{
    $allowed = ['complaint_cases' => 'case_number', 'blotter_entries' => 'blotter_number', 'case_hearings' => 'hearing_number'];
    if (($allowed[$table] ?? null) !== $column) throw new InvalidArgumentException('Unsupported reference target.');
    $check = $connection->prepare("SELECT 1 FROM $table WHERE $column = :code");
    do {
        $code = $prefix . '-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $check->execute(['code' => $code]);
    } while ($check->fetchColumn());
    return $code;
}

function complaints_record_history(PDO $connection, ?int $complaint_id, ?int $blotter_id, string $action, ?string $from, string $to, ?string $notes = null): void
{
    $statement = $connection->prepare('INSERT INTO case_status_history (complaint_id, blotter_id, action, from_status, to_status, notes, actor_user_id) VALUES (:complaint, :blotter, :action, :from_status, :to_status, :notes, :actor)');
    $statement->execute(['complaint' => $complaint_id, 'blotter' => $blotter_id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'notes' => $notes === '' ? null : $notes, 'actor' => current_user()['id']]);
}

// General audit trail: references and statuses only.
function complaints_audit(PDO $connection, int $entity_id, string $action, array $details = []): void
{
    residents_audit($connection, 'case', $entity_id, $action, $details);
}

function complaints_history(PDO $connection, string $kind, int $id): array
{
    $column = $kind === 'blotter' ? 'blotter_id' : 'complaint_id';
    $statement = $connection->prepare("SELECT h.action, h.from_status, h.to_status, h.notes, h.acted_at, u.name AS actor_name FROM case_status_history h LEFT JOIN users u ON u.id = h.actor_user_id WHERE h.$column = :id ORDER BY h.acted_at ASC, h.id ASC");
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

function complaints_history_label(string $action): string
{
    return [
        'submitted' => 'Complaint submitted', 'review_started' => 'Review started', 'resolved' => 'Resolved', 'closed' => 'Closed',
        'note_added' => 'Note added', 'supporting_note' => 'Supporting note recorded', 'blotter_recorded' => 'Blotter entry recorded',
        'hearing_scheduled' => 'Hearing scheduled', 'recorded' => 'Blotter recorded', 'processing_started' => 'Processing started',
    ][$action] ?? ucfirst(str_replace('_', ' ', $action));
}

// Prevents the same form being saved twice by a double click or a browser resubmission: a payload that was SAVED
// within the last 2 minutes is refused. Only successful saves are remembered, so correcting and retrying a refused
// submission (for example after a schedule conflict) is never blocked.
function complaints_is_duplicate_submission(string $scope, array $payload): bool
{
    $previous = $_SESSION['case_submit_guard'][$scope] ?? null;
    return is_array($previous) && $previous['hash'] === hash('sha256', json_encode($payload)) && time() - $previous['at'] < 120;
}

function complaints_remember_submission(string $scope, array $payload): void
{
    $_SESSION['case_submit_guard'][$scope] = ['hash' => hash('sha256', json_encode($payload)), 'at' => time()];
}

// ── Involved persons ─────────────────────────────────────────────────────────────────────────────────
// Normalizes posted person rows. An existing resident is identified only by ID and re-read from the database (the name
// always comes from the resident record). Non-residents are entered by name; no resident profile is ever created.
function complaints_collect_persons(PDO $connection, array $rows, array $allowed_roles, bool $allow_resident_link = true): array
{
    $persons = [];
    $errors = [];
    $resident_lookup = $connection->prepare('SELECT id, first_name, middle_name, last_name, suffix, address, contact_number FROM residents WHERE id = :id');
    foreach ($rows as $index => $row) {
        if (!is_array($row)) continue;
        $role = (string) ($row['role'] ?? '');
        $resident_id = $allow_resident_link ? (filter_var($row['resident_id'] ?? null, FILTER_VALIDATE_INT) ?: null) : null;
        $name = residents_collapse((string) ($row['full_name'] ?? ''));
        $contact = residents_collapse((string) ($row['contact_number'] ?? ''));
        $address = complaints_text($row['address'] ?? '', 500);
        $details = residents_collapse((string) ($row['identifying_details'] ?? ''));
        if ($resident_id === null && $name === '' && $contact === '' && $address === '' && $details === '') continue; // empty optional row
        $label = '#' . ((int) $index + 1);
        if (!in_array($role, $allowed_roles, true)) { $errors[] = "Person $label: choose a valid role."; continue; }
        if ($resident_id !== null) {
            $resident_lookup->execute(['id' => $resident_id]);
            $resident = $resident_lookup->fetch();
            if (!$resident) { $errors[] = "Person $label: the selected resident profile no longer exists."; continue; }
            $name = residents_full_name($resident);
            if ($address === '') $address = residents_collapse((string) $resident['address']);
            if ($contact === '') $contact = (string) ($resident['contact_number'] ?? '');
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 180) $errors[] = "Person $label: enter a full name of 2 to 180 characters.";
        if ($contact !== '' && !preg_match('/^[0-9+()\-\s]{7,30}$/', $contact)) $errors[] = "Person $label: enter a valid contact number.";
        if (mb_strlen($details) > 255) $errors[] = "Person $label: identifying details are limited to 255 characters.";
        $persons[] = ['role' => $role, 'resident_id' => $resident_id, 'full_name' => $name, 'contact_number' => $contact === '' ? null : $contact, 'address' => $address === '' ? null : $address, 'identifying_details' => $details === '' ? null : $details];
    }
    return ['persons' => $persons, 'errors' => $errors];
}

function complaints_require_parties(array $persons, array &$errors): void
{
    $roles = array_column($persons, 'role');
    if (!in_array('complainant', $roles, true)) $errors[] = 'Enter the complainant.';
    if (!in_array('respondent', $roles, true)) $errors[] = 'Enter the respondent (use "Unidentified" when the person is not known).';
}

function complaints_insert_persons(PDO $connection, ?int $complaint_id, ?int $blotter_id, array $persons): void
{
    $insert = $connection->prepare('INSERT INTO case_persons (complaint_id, blotter_id, person_role, resident_id, full_name, contact_number, address, identifying_details, created_by) VALUES (:complaint, :blotter, :role, :resident, :name, :contact, :address, :details, :user)');
    foreach ($persons as $person) {
        $insert->execute(['complaint' => $complaint_id, 'blotter' => $blotter_id, 'role' => $person['role'], 'resident' => $person['resident_id'], 'name' => $person['full_name'], 'contact' => $person['contact_number'], 'address' => $person['address'], 'details' => $person['identifying_details'], 'user' => current_user()['id']]);
    }
}

function complaints_persons(PDO $connection, string $kind, int $id): array
{
    $column = $kind === 'blotter' ? 'blotter_id' : 'complaint_id';
    $statement = $connection->prepare("SELECT id, person_role, resident_id, full_name, contact_number, address, identifying_details FROM case_persons WHERE $column = :id ORDER BY FIELD(person_role, 'complainant', 'respondent', 'witness', 'other'), id");
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

// ── Complaint validation and creation ───────────────────────────────────────────────────────────────
function complaints_validate_details(array $input): array
{
    $values = [
        'category' => residents_collapse((string) ($input['category'] ?? '')),
        'subject' => residents_collapse((string) ($input['subject'] ?? '')),
        'description' => complaints_text($input['description'] ?? '', 5000),
        'incident_at' => (string) ($input['incident_at'] ?? ''),
        'incident_location' => residents_collapse((string) ($input['incident_location'] ?? '')),
        'supporting_note' => complaints_text($input['supporting_note'] ?? '', 2000),
    ];
    $errors = [];
    if (mb_strlen($values['category']) < 2 || mb_strlen($values['category']) > 80) $errors['category'] = 'Enter a category of 2 to 80 characters.';
    if (mb_strlen($values['subject']) < 5 || mb_strlen($values['subject']) > 255) $errors['subject'] = 'Enter a subject of 5 to 255 characters.';
    if (mb_strlen($values['description']) < 10) $errors['description'] = 'Describe the complaint in at least 10 characters.';
    $incident = complaints_parse_datetime_local($values['incident_at']);
    if ($incident === null) $errors['incident_at'] = 'Enter the incident date and time.';
    elseif ($incident > date('Y-m-d H:i:s', time() + 300)) $errors['incident_at'] = 'The incident date and time cannot be in the future.';
    if (mb_strlen($values['incident_location']) < 2 || mb_strlen($values['incident_location']) > 255) $errors['incident_location'] = 'Enter the incident location (2 to 255 characters).';
    $values['incident_at_db'] = $incident;
    return ['values' => $values, 'errors' => $errors];
}

function complaints_admin_recipients(PDO $connection): array
{
    $statement = $connection->prepare("SELECT id FROM users WHERE role IN ('super_admin', 'secretary') AND status = 'active' AND id <> :actor");
    $statement->execute(['actor' => current_user()['id']]);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

// Active resident accounts of the complaint's complainant(s) and of the submitting resident account.
function complaints_complainant_accounts(PDO $connection, int $complaint_id): array
{
    $statement = $connection->prepare("SELECT DISTINCT u.id FROM users u WHERE u.role = 'resident' AND u.status = 'active' AND (u.resident_id IN (SELECT p.resident_id FROM case_persons p WHERE p.complaint_id = :complaint_a AND p.person_role = 'complainant' AND p.resident_id IS NOT NULL) OR u.id = (SELECT c.submitted_by_user_id FROM complaint_cases c WHERE c.id = :complaint_b))");
    $statement->execute(['complaint_a' => $complaint_id, 'complaint_b' => $complaint_id]);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

// Recipient-specific notification (user_notifications). Title and message must never contain names, narratives or evidence.
function complaints_notify(PDO $connection, array $recipient_ids, string $category, string $entity_type, int $entity_id, string $title, string $message): void
{
    $insert = $connection->prepare('INSERT IGNORE INTO user_notifications (recipient_user_id, category, entity_type, entity_id, title, message) VALUES (:user, :category, :type, :id, :title, :message)');
    foreach (array_unique($recipient_ids) as $user_id) {
        if ($user_id === (int) current_user()['id']) continue;
        $insert->execute(['user' => $user_id, 'category' => $category, 'type' => $entity_type, 'id' => $entity_id, 'title' => mb_substr($title, 0, 200), 'message' => mb_substr($message, 0, 500)]);
    }
}

// Centralized complaint recording (staff-assisted and online). Always confidential; always starts as Pending Review.
function complaints_create(PDO $connection, array $details, array $persons, string $source): array
{
    $connection->beginTransaction();
    try {
        $reference = complaints_generate_reference($connection, 'complaint_cases', 'case_number', 'CMP');
        $first = static function (string $role) use ($persons): ?array { foreach ($persons as $p) if ($p['role'] === $role) return $p; return null; };
        $complainant = $first('complainant'); $respondent = $first('respondent');
        $user_id = (int) current_user()['id'];
        $insert = $connection->prepare("INSERT INTO complaint_cases (case_number, complainant_resident_id, respondent_resident_id, complainant_name, respondent_name, subject, category, confidential_details, incident_at, incident_location, submission_source, submitted_by_user_id, is_confidential, status, created_by, updated_by) VALUES (:ref, :c_res, :r_res, :c_name, :r_name, :subject, :category, :details, :incident_at, :location, :source, :submitted_by, 1, 'pending_review', :created_by, :updated_by)");
        $insert->execute(['ref' => $reference, 'c_res' => $complainant['resident_id'] ?? null, 'r_res' => $respondent['resident_id'] ?? null, 'c_name' => $complainant['full_name'] ?? null, 'r_name' => $respondent['full_name'] ?? null, 'subject' => $details['subject'], 'category' => $details['category'], 'details' => $details['description'], 'incident_at' => $details['incident_at_db'], 'location' => $details['incident_location'], 'source' => $source, 'submitted_by' => $user_id, 'created_by' => $user_id, 'updated_by' => $user_id]);
        $id = (int) $connection->lastInsertId();
        complaints_insert_persons($connection, $id, null, $persons);
        complaints_record_history($connection, $id, null, 'submitted', null, 'pending_review', null);
        if ($details['supporting_note'] !== '') complaints_record_history($connection, $id, null, 'supporting_note', 'pending_review', 'pending_review', $details['supporting_note']);
        complaints_audit($connection, $id, 'complaint_submitted', ['reference' => $reference, 'source' => $source]);
        complaints_notify($connection, complaints_admin_recipients($connection), 'complaint_submitted', 'complaint', $id, 'New complaint received', "Complaint $reference was submitted and is waiting for review.");
        complaints_notify($connection, complaints_complainant_accounts($connection, $id), 'complaint_submitted', 'complaint', $id, 'Complaint received', "Your complaint $reference was received and is Pending Review.");
        $connection->commit();
        return ['id' => $id, 'reference' => $reference];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function complaints_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $statement = $connection->prepare('SELECT c.*, rv.name AS reviewer_name, rs.name AS resolver_name, cl.name AS closer_name, sb.name AS submitter_name, sb.role AS submitter_role FROM complaint_cases c LEFT JOIN users rv ON rv.id = c.reviewed_by LEFT JOIN users rs ON rs.id = c.resolved_by LEFT JOIN users cl ON cl.id = c.closed_by LEFT JOIN users sb ON sb.id = c.submitted_by_user_id WHERE c.id = :id' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// A resident may view a complaint only when it was submitted from their account or they are a recorded complainant.
function complaints_resident_owns(PDO $connection, array $complaint): bool
{
    if (!has_role('resident')) return false;
    $link = complaints_resident_profile($connection);
    if ((int) $complaint['submitted_by_user_id'] === (int) current_user()['id']) return true;
    if ($link['state'] !== 'verified' && $link['state'] !== 'inactive') return false;
    $statement = $connection->prepare("SELECT 1 FROM case_persons WHERE complaint_id = :id AND person_role = 'complainant' AND resident_id = :resident LIMIT 1");
    $statement->execute(['id' => $complaint['id'], 'resident' => $link['profile']['id']]);
    return (bool) $statement->fetchColumn();
}

// ── Status transitions ───────────────────────────────────────────────────────────────────────────────
function complaints_transitions(string $kind): array
{
    return $kind === 'blotter' ? [
        'start' => ['from' => ['recorded'], 'to' => 'active', 'history' => 'processing_started', 'audit' => 'blotter_processing_started', 'notes' => false, 'set' => 'processing_started_by = :actor, processing_started_at = NOW()'],
        'resolve' => ['from' => ['active'], 'to' => 'resolved', 'history' => 'resolved', 'audit' => 'blotter_resolved', 'notes' => true, 'set' => 'resolved_by = :actor, resolved_at = NOW(), resolution_notes = :notes'],
        'close' => ['from' => ['resolved'], 'to' => 'closed', 'history' => 'closed', 'audit' => 'blotter_closed', 'notes' => true, 'set' => 'closed_by = :actor, closed_at = NOW(), closing_notes = :notes'],
    ] : [
        'start_review' => ['from' => ['pending_review', 'open'], 'to' => 'under_review', 'history' => 'review_started', 'audit' => 'complaint_review_started', 'notes' => false, 'set' => 'reviewed_by = :actor, reviewed_at = NOW()'],
        'resolve' => ['from' => ['under_review'], 'to' => 'resolved', 'history' => 'resolved', 'audit' => 'complaint_resolved', 'notes' => true, 'set' => 'resolved_by = :actor, resolved_at = NOW(), resolution_notes = :notes'],
        'close' => ['from' => ['resolved'], 'to' => 'closed', 'history' => 'closed', 'audit' => 'complaint_closed', 'notes' => true, 'set' => 'closed_by = :actor, closed_at = NOW(), closing_notes = :notes'],
    ];
}

// Locks the record, re-checks its status, updates it, and records history + audit (+ complainant notification).
// Throws RuntimeException with a user-facing message when the transition is not allowed.
function complaints_apply_transition(PDO $connection, string $kind, int $id, string $action, string $notes): string
{
    $transitions = complaints_transitions($kind);
    if (!isset($transitions[$action])) throw new RuntimeException('This action is not available.');
    $rule = $transitions[$action];
    if ($rule['notes'] && (mb_strlen($notes) < 5 || mb_strlen($notes) > 2000)) throw new RuntimeException('Enter the required notes (5 to 2,000 characters).');
    $table = $kind === 'blotter' ? 'blotter_entries' : 'complaint_cases';
    $ref_column = $kind === 'blotter' ? 'blotter_number' : 'case_number';
    $connection->beginTransaction();
    try {
        $lock = $connection->prepare("SELECT id, $ref_column AS reference, status FROM $table WHERE id = :id FOR UPDATE");
        $lock->execute(['id' => $id]);
        $row = $lock->fetch();
        if (!$row) throw new RuntimeException('This record no longer exists.');
        if (!in_array($row['status'], $rule['from'], true)) throw new RuntimeException('This record was already updated. Its current status is shown below.');
        $params = ['id' => $id, 'from_status' => $row['status'], 'actor' => current_user()['id']];
        if ($rule['notes']) $params['notes'] = $notes;
        $update = $connection->prepare("UPDATE $table SET status = '{$rule['to']}', {$rule['set']}" . ($kind === 'complaint' ? ', updated_by = :updater' : '') . ' WHERE id = :id AND status = :from_status');
        if ($kind === 'complaint') $params['updater'] = current_user()['id'];
        $update->execute($params);
        if ($update->rowCount() !== 1) throw new RuntimeException('This record was already updated. Its current status is shown below.');
        complaints_record_history($connection, $kind === 'complaint' ? $id : null, $kind === 'blotter' ? $id : null, $rule['history'], $row['status'], $rule['to'], $rule['notes'] ? $notes : null);
        complaints_audit($connection, $id, $rule['audit'], ['reference' => $row['reference'], 'from' => $row['status'], 'to' => $rule['to']]);
        if ($kind === 'complaint') {
            $label = complaints_status_labels()[$rule['to']];
            complaints_notify($connection, complaints_complainant_accounts($connection, $id), 'complaint_' . $rule['to'], 'complaint', $id, 'Complaint update', "Your complaint {$row['reference']} is now $label.");
        }
        $connection->commit();
        return (string) $row['reference'];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

// Confidential staff note on a complaint or blotter (no status change).
function complaints_add_note(PDO $connection, string $kind, int $id, string $note): string
{
    if (mb_strlen($note) < 5 || mb_strlen($note) > 2000) throw new RuntimeException('Enter a note of 5 to 2,000 characters.');
    $table = $kind === 'blotter' ? 'blotter_entries' : 'complaint_cases';
    $ref_column = $kind === 'blotter' ? 'blotter_number' : 'case_number';
    $statement = $connection->prepare("SELECT $ref_column AS reference, status FROM $table WHERE id = :id");
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if (!$row) throw new RuntimeException('This record no longer exists.');
    if ($row['status'] === 'closed') throw new RuntimeException('Closed records are read-only.');
    $connection->beginTransaction();
    try {
        complaints_record_history($connection, $kind === 'complaint' ? $id : null, $kind === 'blotter' ? $id : null, 'note_added', $row['status'], $row['status'], $note);
        complaints_audit($connection, $id, $kind . '_note_added', ['reference' => $row['reference']]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
    return (string) $row['reference'];
}

// ── Blotter ──────────────────────────────────────────────────────────────────────────────────────────
function blotter_validate_details(array $input): array
{
    $values = [
        'incident_type' => residents_collapse((string) ($input['incident_type'] ?? '')),
        'incident_at' => (string) ($input['incident_at'] ?? ''),
        'incident_location' => residents_collapse((string) ($input['incident_location'] ?? '')),
        'narrative' => complaints_text($input['narrative'] ?? '', 10000),
        'supporting_note' => complaints_text($input['supporting_note'] ?? '', 2000),
    ];
    $errors = [];
    if (mb_strlen($values['incident_type']) < 2 || mb_strlen($values['incident_type']) > 80) $errors['incident_type'] = 'Enter an incident type of 2 to 80 characters.';
    $incident = complaints_parse_datetime_local($values['incident_at']);
    if ($incident === null) $errors['incident_at'] = 'Enter the incident date and time.';
    elseif ($incident > date('Y-m-d H:i:s', time() + 300)) $errors['incident_at'] = 'The incident date and time cannot be in the future.';
    if (mb_strlen($values['incident_location']) < 2 || mb_strlen($values['incident_location']) > 255) $errors['incident_location'] = 'Enter the incident location (2 to 255 characters).';
    if (mb_strlen($values['narrative']) < 10) $errors['narrative'] = 'Enter the incident narrative (at least 10 characters).';
    $values['incident_at_db'] = $incident;
    return ['values' => $values, 'errors' => $errors];
}

// Saves an independent blotter entry. A linked complaint must be Under Review; it is never modified (only its history
// gains a "blotter recorded" entry). One complaint may have many blotter entries.
function blotter_create(PDO $connection, array $details, array $persons, ?int $complaint_id): array
{
    $connection->beginTransaction();
    try {
        $complaint_ref = null;
        if ($complaint_id !== null) {
            $lock = $connection->prepare('SELECT case_number, status FROM complaint_cases WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $complaint_id]);
            $complaint = $lock->fetch();
            if (!$complaint) throw new RuntimeException('The related complaint no longer exists.');
            if ($complaint['status'] !== 'under_review') throw new RuntimeException('A blotter entry can be recorded from a complaint only while it is Under Review.');
            $complaint_ref = $complaint['case_number'];
        }
        $reference = complaints_generate_reference($connection, 'blotter_entries', 'blotter_number', 'BLT');
        $insert = $connection->prepare("INSERT INTO blotter_entries (blotter_number, complaint_id, incident_type, incident_at, incident_location, narrative, status, is_confidential, recorded_by) VALUES (:ref, :complaint, :type, :incident_at, :location, :narrative, 'recorded', 1, :user)");
        $insert->execute(['ref' => $reference, 'complaint' => $complaint_id, 'type' => $details['incident_type'], 'incident_at' => $details['incident_at_db'], 'location' => $details['incident_location'], 'narrative' => $details['narrative'], 'user' => current_user()['id']]);
        $id = (int) $connection->lastInsertId();
        complaints_insert_persons($connection, null, $id, $persons);
        complaints_record_history($connection, null, $id, 'recorded', null, 'recorded', $complaint_ref ? "Linked to complaint $complaint_ref." : null);
        if ($details['supporting_note'] !== '') complaints_record_history($connection, null, $id, 'supporting_note', 'recorded', 'recorded', $details['supporting_note']);
        if ($complaint_id !== null) complaints_record_history($connection, $complaint_id, null, 'blotter_recorded', 'under_review', 'under_review', "Blotter $reference recorded.");
        complaints_audit($connection, $id, 'blotter_recorded', array_filter(['reference' => $reference, 'complaint_reference' => $complaint_ref]));
        $connection->commit();
        return ['id' => $id, 'reference' => $reference];
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function blotter_find(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare('SELECT b.*, c.case_number AS complaint_reference, c.status AS complaint_status, rb.name AS recorder_name, ps.name AS starter_name, rs.name AS resolver_name, cl.name AS closer_name FROM blotter_entries b LEFT JOIN complaint_cases c ON c.id = b.complaint_id LEFT JOIN users rb ON rb.id = b.recorded_by LEFT JOIN users ps ON ps.id = b.processing_started_by LEFT JOIN users rs ON rs.id = b.resolved_by LEFT JOIN users cl ON cl.id = b.closed_by WHERE b.id = :id');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// Distinct values already used (suggestions only; no invented category lists).
function complaints_distinct_values(PDO $connection, string $what): array
{
    $sql = match ($what) {
        'category' => 'SELECT DISTINCT category FROM complaint_cases WHERE category IS NOT NULL ORDER BY category',
        'incident_type' => 'SELECT DISTINCT incident_type FROM blotter_entries ORDER BY incident_type',
        'hearing_type' => 'SELECT DISTINCT hearing_type FROM case_hearings ORDER BY hearing_type',
        default => throw new InvalidArgumentException('Unknown list.'),
    };
    return $connection->query($sql)->fetchAll(PDO::FETCH_COLUMN);
}

// ── Case links: hearings and evidence of a complaint or blotter ──────────────────────────────────────
function complaints_case_hearings(PDO $connection, string $kind, int $id): array
{
    $column = $kind === 'blotter' ? 'blotter_id' : 'complaint_id';
    $statement = $connection->prepare("SELECT h.id, h.hearing_number, h.hearing_type, h.starts_at, h.ends_at, h.status, h.outcome_summary, v.name AS venue_name FROM case_hearings h INNER JOIN hearing_venues v ON v.id = h.venue_id WHERE h.$column = :id ORDER BY h.starts_at DESC");
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

function complaints_attachments(PDO $connection, string $kind, int $id): array
{
    $column = $kind === 'blotter' ? 'blotter_id' : 'complaint_id';
    $statement = $connection->prepare("SELECT a.id, a.original_filename, a.mime_type, a.file_size, a.description, a.uploaded_at, a.removed_at, a.removal_reason, u.name AS uploader_name, r.name AS remover_name FROM case_attachments a LEFT JOIN users u ON u.id = a.uploaded_by LEFT JOIN users r ON r.id = a.removed_by WHERE a.$column = :id ORDER BY a.uploaded_at DESC, a.id DESC");
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

function complaints_format_bytes(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
}

// ── Summary counts (aggregates only) ─────────────────────────────────────────────────────────────────
function complaints_summary_counts(PDO $connection): array
{
    $counts = [
        'pending_complaints' => (int) $connection->query("SELECT COUNT(*) FROM complaint_cases WHERE status IN ('open', 'pending_review')")->fetchColumn(),
        'active_blotter' => null,
        'upcoming_hearings' => null,
        'resolved_cases' => null,
    ];
    if (complaints_schema_ready($connection)) {
        $counts['active_blotter'] = (int) $connection->query("SELECT COUNT(*) FROM blotter_entries WHERE status = 'active'")->fetchColumn();
        $counts['upcoming_hearings'] = (int) $connection->query("SELECT COUNT(*) FROM case_hearings WHERE status IN ('scheduled', 'rescheduled') AND starts_at >= NOW()")->fetchColumn();
        // Complaints and blotter entries are counted from their own tables, so a complaint with linked blotters is not double counted.
        $counts['resolved_cases'] = (int) $connection->query("SELECT (SELECT COUNT(*) FROM complaint_cases WHERE status = 'resolved') + (SELECT COUNT(*) FROM blotter_entries WHERE status = 'resolved')")->fetchColumn();
    }
    return $counts;
}

// ── Shared form rendering: one involved-person block ─────────────────────────────────────────────────
// $index is the persons[] key; $fixed_role locks the role (primary complainant/respondent); $lookup enables the staff
// resident lookup (residents never search the resident registry).
function complaints_person_fields(string $index, array $person, ?string $fixed_role, bool $lookup, array $role_options = [], ?string $summary_label = null): string
{
    $summary = $summary_label !== null ? ' data-summary-label="' . e($summary_label) . '"' : '';
    $name = static fn (string $field): string => 'persons[' . $index . '][' . $field . ']';
    $id = static fn (string $field): string => 'person-' . $index . '-' . $field;
    $value = static fn (string $field): string => e((string) ($person[$field] ?? ''));
    $linked = !empty($person['resident_id']);
    $html = '<div class="case-person"' . ($lookup ? ' data-person-lookup' : '') . '>';
    if ($fixed_role !== null) {
        $html .= '<input type="hidden" name="' . $name('role') . '" value="' . e($fixed_role) . '">';
    } else {
        $html .= '<div class="case-person-role"><label class="form-label" for="' . $id('role') . '">Role</label><select class="form-select" id="' . $id('role') . '" name="' . $name('role') . '" data-summary-skip>';
        foreach ($role_options as $role) $html .= '<option value="' . e($role) . '"' . (($person['role'] ?? '') === $role ? ' selected' : '') . '>' . e(case_person_role_labels()[$role]) . '</option>';
        $html .= '</select></div>';
    }
    if ($lookup) {
        $html .= '<input type="hidden" name="' . $name('resident_id') . '" value="' . $value('resident_id') . '" data-lookup-id>';
        $html .= '<div class="case-lookup"><label class="form-label" for="' . $id('lookup') . '">Find existing resident <span class="activity-detail-muted">(optional)</span></label>'
            . '<input class="form-control" type="search" id="' . $id('lookup') . '" placeholder="Type at least 2 characters of the name" autocomplete="off" maxlength="100" data-lookup-query data-summary-skip>'
            . '<div class="case-lookup-results" data-lookup-results role="listbox" aria-label="Matching residents"></div>'
            . '<p class="case-lookup-selected" data-lookup-selected' . ($linked ? '' : ' hidden') . '>Linked to resident profile #<span data-lookup-selected-id>' . $value('resident_id') . '</span> <button class="btn btn-sm btn-link" type="button" data-lookup-clear>Remove link</button></p></div>';
    }
    $html .= '<div class="case-person-grid">'
        . '<div><label class="form-label" for="' . $id('name') . '">Full name</label><input class="form-control" id="' . $id('name') . '" name="' . $name('full_name') . '" value="' . $value('full_name') . '" maxlength="180" data-lookup-name' . $summary . ($linked ? ' readonly' : '') . '></div>'
        . '<div><label class="form-label" for="' . $id('contact') . '">Contact number</label><input class="form-control" id="' . $id('contact') . '" name="' . $name('contact_number') . '" value="' . $value('contact_number') . '" maxlength="30" inputmode="tel" data-lookup-contact data-summary-skip></div>'
        . '<div class="case-person-wide"><label class="form-label" for="' . $id('address') . '">Address</label><input class="form-control" id="' . $id('address') . '" name="' . $name('address') . '" value="' . $value('address') . '" maxlength="500" data-lookup-address data-summary-skip></div>'
        . '<div class="case-person-wide"><label class="form-label" for="' . $id('details') . '">Identifying details <span class="activity-detail-muted">(optional)</span></label><input class="form-control" id="' . $id('details') . '" name="' . $name('identifying_details') . '" value="' . $value('identifying_details') . '" maxlength="255" placeholder="e.g. approximate age, description" data-summary-skip></div>'
        . '</div></div>';
    return $html;
}

// Splits posted persons into the fixed first complainant/respondent blocks and the additional rows (for re-rendering).
function complaints_person_form_state(array $persons): array
{
    $state = ['complainant' => null, 'respondent' => null, 'additional' => []];
    foreach ($persons as $person) {
        if ($state[$person['role']] ?? false) { $state['additional'][] = $person; continue; }
        if (in_array($person['role'], ['complainant', 'respondent'], true) && $state[$person['role']] === null) { $state[$person['role']] = $person; continue; }
        $state['additional'][] = $person;
    }
    return $state;
}

function complaints_person_summary(array $person, bool $show_contact = true): string
{
    $parts = [e($person['full_name'])];
    if (!empty($person['resident_id'])) $parts[] = '<span class="activity-detail-muted">Resident #' . e((string) $person['resident_id']) . '</span>';
    $html = '<strong>' . implode(' · ', $parts) . '</strong>';
    $lines = [];
    if ($show_contact && !empty($person['contact_number'])) $lines[] = e($person['contact_number']);
    if (!empty($person['address'])) $lines[] = e($person['address']);
    if (!empty($person['identifying_details'])) $lines[] = e($person['identifying_details']);
    return $html . ($lines === [] ? '' : '<br><span class="activity-detail-muted">' . implode(' · ', $lines) . '</span>');
}
