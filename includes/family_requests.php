<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/site.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/residents.php';

// Family members added by residents (Resident Portal → My Household; table household_member_requests, migration
// 20261013_household_member_requests). An active resident whose account and profile are linked both ways asks to add a
// household member who has no account of their own (children, or elders without a phone). The member is saved at once
// as a Pending resident profile without an account, and the request waits in Resident Registrations for the Secretary
// or the System Administrator. Approving makes the profile Active and adds it to the requester's current household
// (when the requester has one); rejecting (a reason is required) makes the profile Inactive. Nothing is deleted.
// The requester is always the signed-in account; the browser never chooses a resident or a household.

const FAM_MAX_PENDING = 5;

function fam_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) $ready = (int) $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'household_member_requests'")->fetchColumn() === 1;
    return $ready;
}

// The signed-in resident's own profile, only when the account and the profile point to each other, the account is an
// active resident account and the profile is Active. Returns ['state' => verified|unlinked|ambiguous|inactive, 'profile'].
function fam_requester(PDO $connection): array
{
    $statement = $connection->prepare("SELECT r.id, r.user_id, r.first_name, r.middle_name, r.last_name, r.suffix, r.address, r.purok, r.status, u.status AS account_status, u.role FROM users u INNER JOIN residents r ON r.id = u.resident_id WHERE u.id = :user_id LIMIT 1");
    $statement->execute(['user_id' => current_user()['id']]);
    $row = $statement->fetch();
    if (!$row) return ['state' => 'unlinked', 'profile' => null];
    if ((int) $row['user_id'] !== (int) current_user()['id'] || !in_array($row['role'], ['resident', 'health_worker', 'treasurer'], true) || $row['account_status'] !== 'active') return ['state' => 'ambiguous', 'profile' => null];
    if ($row['status'] !== 'active') return ['state' => 'inactive', 'profile' => $row];
    return ['state' => 'verified', 'profile' => $row];
}

function fam_status_labels(): array
{
    return ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
}

function fam_status_badge(string $status): string
{
    $tone = ['approved' => 'active', 'rejected' => 'deceased'][$status] ?? 'pending';
    return '<span class="resident-status resident-status-' . e($tone) . '">' . e(fam_status_labels()[$status] ?? ucfirst($status)) . '</span>';
}

function fam_reference(array $request): string
{
    return 'FAM-' . date('Y', strtotime((string) $request['created_at'])) . '-' . str_pad((string) $request['id'], 4, '0', STR_PAD_LEFT);
}

// What the member is to the household head, from what the member is to the requester. Only clear cases are mapped;
// anything else is left for staff to set in Manage Household.
function fam_relationship_to_head(string $relationship, ?string $requester_to_head, bool $requester_is_head): ?string
{
    if ($requester_is_head) return $relationship;
    if ($requester_to_head === 'Spouse' && in_array($relationship, ['Son', 'Daughter', 'Grandchild'], true)) return $relationship;
    if (in_array($requester_to_head, ['Son', 'Daughter'], true) && in_array($relationship, ['Son', 'Daughter'], true)) return 'Grandchild';
    if (in_array($requester_to_head, ['Son', 'Daughter'], true) && in_array($relationship, ['Brother', 'Sister'], true)) return $relationship === 'Brother' ? 'Son' : 'Daughter';
    return null;
}

// Name, birthdate, gender, civil status (optional) and relationship. Returns [values, errors].
function fam_validate(array $input): array
{
    $validated = residents_validate([
        'first_name' => $input['first_name'] ?? '', 'middle_name' => $input['middle_name'] ?? '', 'last_name' => $input['last_name'] ?? '', 'suffix' => $input['suffix'] ?? '',
        'birth_date' => $input['birth_date'] ?? '', 'sex' => $input['sex'] ?? '', 'civil_status' => $input['civil_status'] ?? '',
        'address' => '-', 'purok' => '1',
    ]);
    $fields = ['first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'sex', 'civil_status'];
    $values = array_intersect_key($validated['values'], array_flip($fields));
    $errors = array_intersect_key($validated['errors'], $values);
    if (!isset($errors['sex']) && !in_array($values['sex'], ['male', 'female'], true)) $errors['sex'] = 'Select Male or Female.';
    $values['relationship'] = residents_collapse((string) ($input['relationship'] ?? ''));
    if (!in_array($values['relationship'], residents_relationships(), true)) $errors['relationship'] = 'Select how this person is related to you.';
    return [$values, $errors];
}

function fam_pending_count(PDO $connection, int $user_id): int
{
    $statement = $connection->prepare("SELECT COUNT(*) FROM household_member_requests WHERE requested_by = :id AND status = 'pending'");
    $statement->execute(['id' => $user_id]);
    return (int) $statement->fetchColumn();
}

// Saves the Pending profile and the request. Throws RuntimeException with a message for the resident.
function fam_create(PDO $connection, array $requester, array $values): string
{
    $user_id = (int) current_user()['id'];
    $connection->beginTransaction();
    try {
        // Lock the requester's account row so two submissions at the same moment cannot pass the limit together.
        $connection->prepare('SELECT id FROM users WHERE id = :id FOR UPDATE')->execute(['id' => $user_id]);
        if (fam_pending_count($connection, $user_id) >= FAM_MAX_PENDING) throw new RuntimeException('You already have ' . FAM_MAX_PENDING . ' family members waiting for approval. Please wait until the Barangay Hall reviews them.');
        $matches = residents_duplicates($connection, $values);
        if ($matches['exact'] !== []) throw new RuntimeException('A resident with the same name and birthdate is already recorded. Please ask the Barangay Hall to add them to your household.');
        $membership = residents_current_membership($connection, (int) $requester['id']);
        $address = $membership['address'] ?? $requester['address'];
        $purok = $membership['purok'] ?? $requester['purok'];
        $connection->prepare("INSERT INTO residents (first_name, middle_name, last_name, suffix, birth_date, sex, civil_status, address, purok, status) VALUES (:first_name, :middle_name, :last_name, :suffix, :birth_date, :sex, :civil_status, :address, :purok, 'pending')")
            ->execute(array_intersect_key($values, array_flip(['first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'sex', 'civil_status'])) + ['address' => $address, 'purok' => $purok]);
        $resident_id = (int) $connection->lastInsertId();
        $connection->prepare('INSERT INTO household_member_requests (requested_by, resident_id, relationship_to_requester) VALUES (:by, :resident, :relationship)')
            ->execute(['by' => $user_id, 'resident' => $resident_id, 'relationship' => $values['relationship']]);
        $request_id = (int) $connection->lastInsertId();
        residents_audit($connection, 'household_request', $request_id, 'household_member_requested', ['resident_id' => $resident_id, 'relationship_to_requester' => $values['relationship'], 'possible_matches' => count($matches['possible'])]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
    return residents_full_name($values) . ' was added and is waiting for approval by the Barangay Hall.';
}

// The signed-in resident's own requests.
function fam_requests_of(PDO $connection, int $user_id): array
{
    $statement = $connection->prepare('SELECT q.id, q.relationship_to_requester, q.status, q.review_notes, q.created_at, q.decided_at, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date FROM household_member_requests q LEFT JOIN residents r ON r.id = q.resident_id WHERE q.requested_by = :id ORDER BY q.created_at DESC, q.id DESC LIMIT 50');
    $statement->execute(['id' => $user_id]);
    return $statement->fetchAll();
}

function fam_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $statement = $connection->prepare('SELECT q.*, m.first_name, m.middle_name, m.last_name, m.suffix, m.birth_date, m.sex, m.civil_status, m.address, m.purok, m.status AS member_status, u.name AS requester_name, u.resident_id AS requester_resident_id, rr.status AS requester_resident_status, d.name AS decided_by_name FROM household_member_requests q LEFT JOIN residents m ON m.id = q.resident_id LEFT JOIN users u ON u.id = q.requested_by LEFT JOIN residents rr ON rr.id = u.resident_id LEFT JOIN users d ON d.id = q.decided_by WHERE q.id = :id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function fam_counts(PDO $connection): array
{
    return $connection->query("SELECT COUNT(*) AS `all`, COALESCE(SUM(status = 'pending'), 0) AS pending, COALESCE(SUM(status = 'approved'), 0) AS approved, COALESCE(SUM(status = 'rejected'), 0) AS rejected FROM household_member_requests")->fetch();
}

// Approve or reject. Returns the message to show; throws RuntimeException with a message for staff.
function fam_decide(PDO $connection, int $id, string $decision, string $notes): string
{
    $notes = trim($notes);
    if (!in_array($decision, ['approve', 'reject'], true)) throw new RuntimeException('This action is not available.');
    if ($decision === 'reject' && (mb_strlen($notes) < 10 || mb_strlen($notes) > 500)) throw new RuntimeException('Give the reason for rejecting (10 to 500 characters). The resident will see it.');
    if (mb_strlen($notes) > 500) throw new RuntimeException('Notes must not exceed 500 characters.');
    $connection->beginTransaction();
    try {
        $request = fam_find($connection, $id, true);
        if ($request === null) throw new RuntimeException('The request no longer exists.');
        if ($request['status'] !== 'pending') throw new RuntimeException('This request was already ' . $request['status'] . '.');
        if ($request['resident_id'] === null) throw new RuntimeException('The resident profile of this request no longer exists.');
        $name = residents_full_name($request);
        $member_id = (int) $request['resident_id'];
        if ($decision === 'approve') {
            if ($request['member_status'] === 'pending') {
                $connection->prepare("UPDATE residents SET status = 'active' WHERE id = :id AND status = 'pending'")->execute(['id' => $member_id]);
                residents_audit($connection, 'resident', $member_id, 'resident_status_changed', ['from' => 'pending', 'to' => 'active', 'reason' => 'Family member request ' . fam_reference($request) . ' approved']);
            }
            $message = $name . ' was approved.';
            $household = $request['requester_resident_id'] !== null ? residents_current_membership($connection, (int) $request['requester_resident_id'], true) : null;
            if ($household === null) {
                $message .= ' The requester has no household yet, so ' . $name . ' is listed under Residents without a household.';
            } elseif (residents_current_membership($connection, $member_id) === null) {
                $is_head = (int) ($household['household_head_resident_id'] ?? 0) === (int) $request['requester_resident_id'];
                $relationship = fam_relationship_to_head($request['relationship_to_requester'], $household['relationship_to_head'], $is_head);
                $household_id = residents_open_membership($connection, $member_id, ['mode' => 'existing', 'household_id' => (int) $household['household_id'], 'relationship' => $relationship]);
                residents_audit($connection, 'resident', $member_id, 'resident_household_assigned', ['household_id' => $household_id, 'relationship_to_head' => $relationship, 'source' => fam_reference($request)]);
                $message .= ' Added to household ' . $household['household_no'] . ($relationship === null ? '; set the relationship to the Household Head in Manage Household.' : ' as ' . $relationship . '.');
            }
            $status = 'approved';
        } else {
            if ($request['member_status'] === 'pending') {
                $connection->prepare("UPDATE residents SET status = 'inactive' WHERE id = :id AND status = 'pending'")->execute(['id' => $member_id]);
                residents_audit($connection, 'resident', $member_id, 'resident_status_changed', ['from' => 'pending', 'to' => 'inactive', 'reason' => 'Family member request ' . fam_reference($request) . ' rejected: ' . $notes]);
            }
            $message = 'The request to add ' . $name . ' was rejected.';
            $status = 'rejected';
        }
        $connection->prepare('UPDATE household_member_requests SET status = :status, decided_by = :by, decided_at = NOW(), review_notes = :notes WHERE id = :id')->execute(['status' => $status, 'by' => current_user()['id'], 'notes' => $notes === '' ? null : $notes, 'id' => $id]);
        residents_audit($connection, 'household_request', $id, 'household_member_' . $status, array_filter(['resident_id' => $member_id, 'notes' => $notes]));
        $connection->commit();
        return $message;
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}
