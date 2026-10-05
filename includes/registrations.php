<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/site.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/residents.php';
require_once __DIR__ . '/households.php';

// Resident Registrations (System Administrator and Secretary): the online Resident Portal sign-ups (register.php).
// Each sign-up writes one row in registration_applications (status 'submitted') next to its Pending account and resident
// profile. Approving makes the account and a Pending resident profile Active; rejecting (a reason is required) suspends
// the account and makes a resident profile that was created by the sign-up Inactive. An existing profile that the
// sign-up was linked to is never changed. Nothing is deleted; every decision is kept in registration_approvals and the
// audit log. Activating the account in User Management approves the application too.

function reg_require(): void
{
    require_auth();
    if (!can_access_navigation('registrations')) { http_response_code(403); exit('Access denied.'); }
}

function reg_pending_statuses(): array
{
    return ['submitted', 'verified', 'awaiting_final_approval'];
}

function reg_status_labels(): array
{
    return ['submitted' => 'Pending', 'verified' => 'Pending', 'awaiting_final_approval' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];
}

function reg_status_badge(string $status): string
{
    $tone = ['approved' => 'active', 'rejected' => 'deceased', 'cancelled' => 'inactive'][$status] ?? 'pending';
    return '<span class="resident-status resident-status-' . e($tone) . '">' . e(reg_status_labels()[$status] ?? ucfirst($status)) . '</span>';
}

function reg_reference(array $application): string
{
    return 'REG-' . date('Y', strtotime((string) $application['submitted_at'])) . '-' . str_pad((string) $application['id'], 4, '0', STR_PAD_LEFT);
}

function reg_format_datetime(?string $value): string
{
    if (!$value) return '';
    $date = date_create($value);
    return $date ? $date->format('M j, Y g:i A') : $value;
}

// Called by the sign-up inside its transaction.
function reg_create(PDO $connection, array $details, int $user_id, int $resident_id, ?string $email, string $contact): int
{
    $connection->prepare("INSERT INTO registration_applications (application_type, first_name, middle_name, last_name, suffix, email, birth_date, sex, civil_status, contact_number, address, purok, household_no, household_role, household_head_name, household_relationship, user_id, resident_id, status) VALUES ('resident', :first_name, :middle_name, :last_name, :suffix, :email, :birth_date, :sex, :civil_status, :contact, :address, :purok, :household_no, :household_role, :household_head_name, :household_relationship, :user_id, :resident_id, 'submitted')")
        ->execute(array_intersect_key($details, array_flip(['first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'sex', 'civil_status', 'address', 'purok'])) + [
            'household_no' => ($details['household_no'] ?? '') === '' ? null : $details['household_no'],
            'household_role' => ($details['household_role'] ?? '') === '' ? null : $details['household_role'],
            'household_head_name' => ($details['household_head_name'] ?? '') === '' ? null : $details['household_head_name'],
            'household_relationship' => ($details['household_relationship'] ?? '') === '' ? null : $details['household_relationship'],
            'email' => $email, 'contact' => $contact, 'user_id' => $user_id, 'resident_id' => $resident_id,
        ]);
    return (int) $connection->lastInsertId();
}

function reg_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $statement = $connection->prepare("SELECT a.*, u.email AS user_email, u.username, u.status AS user_status, r.status AS resident_status, r.user_id AS resident_user_id, d.name AS decided_by_name FROM registration_applications a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN residents r ON r.id = a.resident_id LEFT JOIN users d ON d.id = a.decided_by WHERE a.id = :id AND a.application_type = 'resident' LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

// The sign-up created the resident profile (rather than being linked to an existing one).
function reg_created_profile(array $application): bool
{
    return $application['resident_id'] !== null && $application['resident_user_id'] !== null && (int) $application['resident_user_id'] === (int) $application['user_id'];
}

function reg_login_label(array $application): string
{
    if (!empty($application['user_email'])) return (string) $application['user_email'];
    return !empty($application['username']) ? '@' . $application['username'] : '';
}

function reg_history(PDO $connection, int $id): array
{
    $statement = $connection->prepare('SELECT p.action, p.from_status, p.to_status, p.notes, p.created_at, u.name AS actor_name FROM registration_approvals p LEFT JOIN users u ON u.id = p.actor_id WHERE p.application_id = :id ORDER BY p.created_at, p.id');
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

// ── List ──────────────────────────────────────────────────────────────────────────────────────────────────────

function reg_list_state(array $input): array
{
    $state = ['q' => mb_substr(residents_collapse((string) ($input['q'] ?? '')), 0, 100), 'status' => (string) ($input['status'] ?? 'pending'), 'page' => max(1, (int) ($input['page'] ?? 1))];
    if (!in_array($state['status'], ['pending', 'approved', 'rejected', 'all'], true)) $state['status'] = 'pending';
    return $state;
}

function reg_list_where(array $state): array
{
    $where = ["a.application_type = 'resident'"];
    $params = [];
    if ($state['status'] === 'pending') $where[] = "a.status IN ('submitted', 'verified', 'awaiting_final_approval')";
    elseif ($state['status'] !== 'all') { $where[] = 'a.status = :status'; $params['status'] = $state['status']; }
    if ($state['q'] !== '') {
        $like = '%' . addcslashes($state['q'], '%_\\') . '%';
        $where[] = "(CONCAT_WS(' ', a.first_name, a.middle_name, a.last_name, a.suffix) LIKE :q1 OR a.contact_number LIKE :q2)";
        $params += ['q1' => $like, 'q2' => $like];
    }
    return [implode(' AND ', $where), $params];
}

function reg_counts(PDO $connection): array
{
    return $connection->query("SELECT COUNT(*) AS `all`, COALESCE(SUM(status IN ('submitted', 'verified', 'awaiting_final_approval')), 0) AS pending, COALESCE(SUM(status = 'approved'), 0) AS approved, COALESCE(SUM(status = 'rejected'), 0) AS rejected FROM registration_applications WHERE application_type = 'resident'")->fetch();
}

// ── Decisions ─────────────────────────────────────────────────────────────────────────────────────────────────

function reg_log(PDO $connection, int $id, string $action, string $from, string $to, ?string $notes): void
{
    $connection->prepare('INSERT INTO registration_approvals (application_id, action, from_status, to_status, actor_id, notes) VALUES (:id, :action, :from, :to, :actor, :notes)')
        ->execute(['id' => $id, 'action' => $action, 'from' => $from, 'to' => $to, 'actor' => current_user()['id'], 'notes' => $notes]);
}

// Marks a pending application approved (also used when the account is activated in User Management). The caller holds
// the transaction.
function reg_mark_approved(PDO $connection, int $id, string $from, ?string $notes): void
{
    $connection->prepare("UPDATE registration_applications SET status = 'approved', decided_by = :admin, decided_at = NOW(), review_notes = :notes WHERE id = :id")->execute(['admin' => current_user()['id'], 'notes' => $notes, 'id' => $id]);
    reg_log($connection, $id, 'approved', $from, 'approved', $notes);
    residents_audit($connection, 'registration', $id, 'registration_approved', array_filter(['notes' => $notes]));
}

// On approval, a sign-up that was linked to an existing profile also gets the profile's side of the link
// (residents.user_id), which the Resident Portal requires; it is set only when the profile has no account yet.
function reg_link_profile(PDO $connection, int $user_id, ?int $resident_id): void
{
    if ($resident_id === null) return;
    $update = $connection->prepare('UPDATE residents SET user_id = :user_id WHERE id = :id AND user_id IS NULL');
    $update->execute(['user_id' => $user_id, 'id' => $resident_id]);
    if ($update->rowCount() === 1) residents_audit($connection, 'resident', $resident_id, 'resident_account_linked', ['user_id' => $user_id]);
}

// The pending application of a resident account, if any (User Management activation).
function reg_approve_for_user(PDO $connection, int $user_id): void
{
    $statement = $connection->prepare("SELECT id, status FROM registration_applications WHERE user_id = :id AND application_type = 'resident' AND status IN ('submitted', 'verified', 'awaiting_final_approval') FOR UPDATE");
    $statement->execute(['id' => $user_id]);
    foreach ($statement->fetchAll() as $row) reg_mark_approved($connection, (int) $row['id'], $row['status'], 'Approved by activating the account in User Management');
}

// The household choice staff confirm on approval ($household from the review form):
//   household_action = create   → a new household (next P[purok]-number, the applicant's address) with the resident as head
//                    = existing → household_id, as household_relationship (optional) to its head
//                    = later    → nothing now; the resident is listed under Residents without a household
// A resident who already has a household (an existing profile) is never moved. Returns a sentence for the message.
function reg_assign_household(PDO $connection, array $application, array $household): string
{
    $action = (string) ($household['household_action'] ?? 'later');
    if ($action === 'later' || $application['resident_id'] === null) return '';
    $resident_id = (int) $application['resident_id'];
    if (residents_current_membership($connection, $resident_id, true) !== null) return ' The resident already belongs to a household, so no household was changed.';
    if ($action === 'create') {
        $number = households_next_number($connection, (string) $application['purok']);
        if ($number === null) throw new RuntimeException('A household number cannot be made for this Purok. Choose an existing household or assign later.');
        $household_id = residents_open_membership($connection, $resident_id, ['mode' => 'new', 'relationship' => null, 'new' => ['household_no' => $number, 'address' => (string) $application['address'], 'purok' => (string) $application['purok']]]);
        $connection->prepare('UPDATE households SET household_head_resident_id = :head WHERE id = :id')->execute(['head' => $resident_id, 'id' => $household_id]);
        residents_audit($connection, 'resident', $resident_id, 'resident_household_assigned', ['household_id' => $household_id, 'relationship_to_head' => null, 'source' => reg_reference($application)]);
        residents_audit($connection, 'household', $household_id, 'household_head_changed', ['previous_head_resident_id' => null, 'new_head_resident_id' => $resident_id]);
        return ' Household ' . $number . ' was created with the resident as household head.';
    }
    if ($action !== 'existing') throw new RuntimeException('Choose what to do with the household.');
    $household_id = filter_var($household['household_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $relationship = residents_collapse((string) ($household['household_relationship'] ?? ''));
    if ($relationship !== '' && !in_array($relationship, residents_relationships(), true)) throw new RuntimeException('Select a valid relationship to the household head.');
    $statement = $connection->prepare('SELECT household_no FROM households WHERE id = :id');
    $statement->execute(['id' => $household_id]);
    $number = $statement->fetchColumn();
    if ($number === false) throw new RuntimeException('Select the household to add the resident to.');
    residents_open_membership($connection, $resident_id, ['mode' => 'existing', 'household_id' => $household_id, 'relationship' => $relationship === '' ? null : $relationship]);
    residents_audit($connection, 'resident', $resident_id, 'resident_household_assigned', ['household_id' => $household_id, 'relationship_to_head' => $relationship === '' ? null : $relationship, 'source' => reg_reference($application)]);
    return ' Added to household ' . $number . ($relationship === '' ? '.' : ' as ' . $relationship . '.');
}

// Approve or reject. Returns the message to show; throws RuntimeException with a message for the person.
function reg_decide(PDO $connection, int $id, string $decision, string $notes, array $household = []): string
{
    $notes = trim($notes);
    if (!in_array($decision, ['approve', 'reject'], true)) throw new RuntimeException('This action is not available.');
    if ($decision === 'reject' && (mb_strlen($notes) < 10 || mb_strlen($notes) > 500)) throw new RuntimeException('Give the reason for rejecting (10 to 500 characters). The applicant may ask the Barangay Hall about it.');
    if (mb_strlen($notes) > 500) throw new RuntimeException('Notes must not exceed 500 characters.');
    $connection->beginTransaction();
    try {
        $application = reg_find($connection, $id, true);
        if ($application === null) throw new RuntimeException('The registration no longer exists.');
        if (!in_array($application['status'], reg_pending_statuses(), true)) throw new RuntimeException('This registration was already ' . strtolower(reg_status_labels()[$application['status']] ?? $application['status']) . '.');
        $name = residents_full_name($application);
        if ($decision === 'approve') {
            if ($application['user_id'] === null) throw new RuntimeException('The account of this registration no longer exists, so it cannot be approved.');
            if ($application['user_status'] === 'pending') {
                $connection->prepare("UPDATE users SET status = 'active', approved_by = COALESCE(approved_by, :admin), approved_at = COALESCE(approved_at, NOW()) WHERE id = :id")->execute(['admin' => current_user()['id'], 'id' => $application['user_id']]);
                residents_audit($connection, 'user', (int) $application['user_id'], 'account_status_changed', ['name' => $name, 'status_from' => 'pending', 'status_to' => 'active']);
            }
            reg_link_profile($connection, (int) $application['user_id'], $application['resident_id'] === null ? null : (int) $application['resident_id']);
            if ($application['resident_status'] === 'pending') {
                $connection->prepare("UPDATE residents SET status = 'active' WHERE id = :id AND status = 'pending'")->execute(['id' => $application['resident_id']]);
                residents_audit($connection, 'resident', (int) $application['resident_id'], 'resident_status_changed', ['from' => 'pending', 'to' => 'active', 'reason' => 'Online registration ' . reg_reference($application) . ' approved']);
            }
            $household_note = reg_assign_household($connection, $application, $household);
            reg_mark_approved($connection, $id, $application['status'], $notes === '' ? null : $notes);
            $message = $name . ' was approved and can now sign in to the Resident Portal.' . $household_note;
        } else {
            if ($application['user_id'] !== null && $application['user_status'] === 'pending') {
                $connection->prepare("UPDATE users SET status = 'suspended' WHERE id = :id")->execute(['id' => $application['user_id']]);
                residents_audit($connection, 'user', (int) $application['user_id'], 'account_status_changed', ['name' => $name, 'status_from' => 'pending', 'status_to' => 'suspended']);
            }
            if (reg_created_profile($application) && $application['resident_status'] === 'pending') {
                $connection->prepare("UPDATE residents SET status = 'inactive' WHERE id = :id AND status = 'pending'")->execute(['id' => $application['resident_id']]);
                residents_audit($connection, 'resident', (int) $application['resident_id'], 'resident_status_changed', ['from' => 'pending', 'to' => 'inactive', 'reason' => 'Online registration ' . reg_reference($application) . ' rejected: ' . $notes]);
            }
            $connection->prepare("UPDATE registration_applications SET status = 'rejected', decided_by = :admin, decided_at = NOW(), review_notes = :notes WHERE id = :id")->execute(['admin' => current_user()['id'], 'notes' => $notes, 'id' => $id]);
            reg_log($connection, $id, 'rejected', $application['status'], 'rejected', $notes);
            residents_audit($connection, 'registration', $id, 'registration_rejected', ['reason' => $notes]);
            $message = 'The registration of ' . $name . ' was rejected. The account cannot sign in.';
        }
        $connection->commit();
        return $message;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}
