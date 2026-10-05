<?php
declare(strict_types=1);

require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/navigation.php';

// User Management (System Administrator only): every account in the users table, with search, role and status filters.
// Staff accounts can be added, edited (name, email), given a new password or role, and suspended or reactivated.
// Resident accounts are created through resident registration and linked to a resident profile; here they can only be
// suspended or reactivated. The System Administrator account is changed only through its own Profile pages, and nobody
// can suspend their own account. Every change requires CSRF protection and is recorded in the audit log.

function users_require_admin(): void
{
    require_auth();
    if (!has_role('super_admin') || !can_access_navigation('users')) { http_response_code(403); exit('Access denied.'); }
}

function users_all_roles(): array
{
    $roles = ['super_admin', 'punong_barangay', 'secretary', 'treasurer', 'official', 'health_worker', 'resident'];
    return array_combine($roles, array_map('accounts_role_label', $roles));
}

function users_statuses(): array
{
    return ['active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended'];
}

function users_status_badge(string $status): string
{
    $tone = ['active' => 'active', 'pending' => 'pending', 'suspended' => 'suspended'][$status] ?? 'inactive';
    return '<span class="resident-status users-status-' . e($tone) . ' resident-status-' . e($tone) . '">' . e(users_statuses()[$status] ?? ucfirst($status)) . '</span>';
}

function users_format_datetime(?string $value): string
{
    if (!$value) return '';
    $date = date_create($value);
    return $date ? $date->format('M j, Y g:i A') : $value;
}

// Replaces the Puroks assigned to a Health Worker account (health_worker_puroks). The caller holds the transaction.
function users_save_health_puroks(PDO $connection, int $user_id, array $puroks): void
{
    $connection->prepare('DELETE FROM health_worker_puroks WHERE user_id = :id')->execute(['id' => $user_id]);
    $insert = $connection->prepare('INSERT INTO health_worker_puroks (user_id, purok) VALUES (:id, :purok)');
    foreach ($puroks as $purok) $insert->execute(['id' => $user_id, 'purok' => (string) $purok]);
}

// ── Health Worker = resident too (they live in the barangay) ──────────────────
// Staff roles whose account is also their resident profile (they live in the barangay): My Household for both; Health
// Workers also Document Requests and Disaster Info.
function users_resident_staff_roles(): array
{
    return ['health_worker', 'treasurer'];
}

// The resident profile linked to a staff account (users.resident_id), or null.
function users_linked_resident(PDO $connection, int $user_id): ?array
{
    $statement = $connection->prepare('SELECT r.id, r.first_name, r.middle_name, r.last_name, r.suffix, r.birth_date, r.purok, r.status FROM users u INNER JOIN residents r ON r.id = u.resident_id WHERE u.id = :id LIMIT 1');
    $statement->execute(['id' => $user_id]);
    return $statement->fetch() ?: null;
}

// Links a Health Worker account to its resident profile, inside the caller's transaction. A resident already on the
// list with the same first name, last name and birthdate is linked as is (no duplicate, nothing on it is changed);
// otherwise a new active resident profile is created from the validated details. Returns [resident_id, created].
function users_link_hw_resident(PDO $connection, int $user_id, array $values): array
{
    $find = $connection->prepare("SELECT r.id, r.status, r.user_id, (SELECT u.id FROM users u WHERE u.resident_id = r.id LIMIT 1) AS linked_user FROM residents r WHERE LOWER(r.first_name) = LOWER(:first) AND LOWER(r.last_name) = LOWER(:last) AND r.birth_date = :birth FOR UPDATE");
    $find->execute(['first' => $values['first_name'], 'last' => $values['last_name'], 'birth' => $values['birth_date']]);
    $matches = $find->fetchAll();
    if (count($matches) > 1) throw new RuntimeException('More than one resident has this name and birthdate. Ask the Barangay Secretary to check the Residents list first.');
    if ($matches !== []) {
        $match = $matches[0];
        if (($match['linked_user'] !== null && (int) $match['linked_user'] !== $user_id) || ($match['user_id'] !== null && (int) $match['user_id'] !== $user_id)) throw new RuntimeException('This resident is already linked to another account.');
        if (!in_array($match['status'], ['active', 'pending'], true)) throw new RuntimeException('This resident\'s profile is not active (' . $match['status'] . '). Ask the Barangay Secretary to check it first.');
        $resident_id = (int) $match['id'];
        $created = false;
    } else {
        $connection->prepare("INSERT INTO residents (first_name, middle_name, last_name, suffix, birth_date, sex, civil_status, contact_number, address, purok, status) VALUES (:first_name, :middle_name, :last_name, :suffix, :birth_date, :sex, :civil_status, :contact_number, :address, :purok, 'active')")
            ->execute(array_intersect_key($values, array_flip(['first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'sex', 'civil_status', 'contact_number', 'address', 'purok'])));
        $resident_id = (int) $connection->lastInsertId();
        $created = true;
        residents_audit($connection, 'resident', $resident_id, 'resident_created', ['source' => 'Health Worker account', 'user_id' => $user_id]);
    }
    $connection->prepare('UPDATE residents SET user_id = :user WHERE id = :id')->execute(['user' => $user_id, 'id' => $resident_id]);
    $connection->prepare('UPDATE users SET resident_id = :resident WHERE id = :id')->execute(['resident' => $resident_id, 'id' => $user_id]);
    residents_audit($connection, 'resident', $resident_id, 'resident_account_linked', ['user_id' => $user_id, 'source' => 'Health Worker account']);
    return [$resident_id, $created];
}

// What the account signs in with: the email, or "@username" for a resident who registered with a username only.
function users_login_label(array $user): string
{
    if (!empty($user['email'])) return (string) $user['email'];
    return !empty($user['username']) ? '@' . $user['username'] : '';
}

function users_find(PDO $connection, int $id, bool $lock = false): ?array
{
    $statement = $connection->prepare('SELECT u.id, u.name, u.email, u.username, u.role, u.status, u.resident_id, r.status AS resident_status, u.approved_at, u.last_login_at, u.created_at, u.updated_at, r.first_name, r.middle_name, r.last_name, r.suffix FROM users u LEFT JOIN residents r ON r.id = u.resident_id WHERE u.id = :id LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function users_is_staff(array $user): bool
{
    return in_array($user['role'], accounts_staff_roles(), true);
}

// What the signed-in administrator may do with an account (users_action.php and the forms check the same rules).
function users_allowed_actions(array $user): array
{
    $self = (int) $user['id'] === (int) current_user()['id'];
    $staff = users_is_staff($user);
    $can_activate = $user['status'] !== 'active' && !$self && $user['role'] !== 'super_admin' && ($staff || ($user['role'] === 'resident' && $user['resident_id'] !== null));
    return [
        'edit' => $staff,
        'password' => $staff || $self,
        'role' => $staff,
        'suspend' => $user['status'] === 'active' && !$self && $user['role'] !== 'super_admin',
        'activate' => $can_activate,
        'self' => $self,
    ];
}

// ── List state (search, role, status, page — all in the URL) ─────────────────────────────────────────────────

function users_list_state(array $input): array
{
    $state = [
        'q' => mb_substr(residents_collapse((string) ($input['q'] ?? '')), 0, 100),
        'role' => (string) ($input['role'] ?? ''),
        'status' => (string) ($input['status'] ?? ''),
        'page' => max(1, (int) ($input['page'] ?? 1)),
    ];
    if (!array_key_exists($state['role'], users_all_roles())) $state['role'] = '';
    if (!array_key_exists($state['status'], users_statuses())) $state['status'] = '';
    return $state;
}

function users_query_string(array $state): string
{
    return http_build_query(array_filter(['q' => $state['q'], 'role' => $state['role'], 'status' => $state['status'], 'page' => ($state['page'] ?? 1) > 1 ? $state['page'] : ''], static fn ($v): bool => $v !== ''));
}

function users_list_where(array $state): array
{
    $where = [];
    $params = [];
    if ($state['q'] !== '') {
        $like = '%' . addcslashes($state['q'], '%_\\') . '%';
        $where[] = '(u.name LIKE :q1 OR u.email LIKE :q2 OR u.username LIKE :q3)';
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
    }
    if ($state['role'] !== '') { $where[] = 'u.role = :role'; $params['role'] = $state['role']; }
    if ($state['status'] !== '') { $where[] = 'u.status = :status'; $params['status'] = $state['status']; }
    return [$where === [] ? '1 = 1' : implode(' AND ', $where), $params];
}

// ── Validation ────────────────────────────────────────────────────────────────────────────────────────────────

// Name, email and (for new accounts) role; $existing is the account being edited.
function users_validate(PDO $connection, array $input, ?array $existing = null): array
{
    $values = [
        'name' => residents_collapse((string) ($input['name'] ?? '')),
        'email' => mb_strtolower(trim((string) ($input['email'] ?? ''))),
        'role' => $existing['role'] ?? (string) ($input['role'] ?? ''),
    ];
    $errors = [];
    if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 150 || !preg_match("/^\p{L}[\p{L}\p{M}\s.,'\-]*$/u", $values['name'])) $errors['name'] = 'Enter the full name (letters only, 2 to 150 characters).';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 190) $errors['email'] = 'Enter a valid email address.';
    else {
        $statement = $connection->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id');
        $statement->execute(['email' => $values['email'], 'id' => (int) ($existing['id'] ?? 0)]);
        if ((int) $statement->fetchColumn() > 0) $errors['email'] = 'This email address is already used by another account.';
    }
    if ($existing === null && !in_array($values['role'], accounts_assignable_roles($connection), true)) $errors['role'] = 'Select a staff role.';
    return ['values' => $values, 'errors' => $errors];
}
