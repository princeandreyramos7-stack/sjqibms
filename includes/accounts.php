<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/residents.php';

// Account self-service limited to the System Administrator (super_admin): editing the administrator's own name and email,
// changing the administrator's own password, and setting new passwords for barangay staff accounts. Every change requires
// the administrator's current password. Passwords are never displayed, logged or stored in plain text.

function accounts_require_admin(): void
{
    require_role('super_admin');
}

// Staff roles whose passwords the administrator may change (barangay officials and staff; never residents).
function accounts_staff_roles(): array
{
    return ['punong_barangay', 'secretary', 'treasurer', 'health_worker', 'official'];
}

function accounts_role_label(string $role): string
{
    return ['super_admin' => 'System Administrator', 'punong_barangay' => 'Punong Barangay', 'secretary' => 'Barangay Secretary', 'treasurer' => 'Barangay Treasurer', 'health_worker' => 'Barangay Health Worker', 'official' => 'Barangay Official', 'resident' => 'Resident'][$role] ?? ucwords(str_replace('_', ' ', $role));
}

// Roles held by one active account at a time (the finance approval chain needs exactly one of each).
function accounts_single_holder_roles(): array
{
    return ['treasurer', 'punong_barangay'];
}

// Whether users.role accepts the Punong Barangay role yet (migration 20261003_role_punong_barangay).
function accounts_punong_barangay_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $type = (string) $connection->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'")->fetchColumn();
        $ready = str_contains($type, "'punong_barangay'");
    }
    return $ready;
}

// Roles the administrator may assign to a staff account (never System Administrator or Resident).
function accounts_assignable_roles(PDO $connection): array
{
    // The Punong Barangay role is not offered (removed by the owner, 2026-10-04); existing accounts keep their role.
    return array_values(array_filter(accounts_staff_roles(), static fn (string $role): bool => $role !== 'punong_barangay'));
}

// Another ACTIVE account that already holds a single-holder role, or null.
function accounts_role_holder(PDO $connection, string $role, int $except_id): ?array
{
    $statement = $connection->prepare("SELECT id, name FROM users WHERE role = :role AND status = 'active' AND id <> :id LIMIT 1");
    $statement->execute(['role' => $role, 'id' => $except_id]);
    return $statement->fetch() ?: null;
}

function accounts_find(PDO $connection, int $id): ?array
{
    $statement = $connection->prepare('SELECT id, name, email, password_hash, role, status, created_at, last_login_at, updated_at FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    return $statement->fetch() ?: null;
}

function accounts_staff(PDO $connection): array
{
    $roles = accounts_staff_roles();
    $placeholders = implode(', ', array_map(static fn (int $i): string => ':role' . $i, array_keys($roles)));
    $statement = $connection->prepare("SELECT id, name, email, role, status, last_login_at FROM users WHERE role IN ($placeholders) ORDER BY FIELD(role, 'punong_barangay', 'secretary', 'treasurer', 'official', 'health_worker'), name");
    foreach ($roles as $i => $role) $statement->bindValue('role' . $i, $role);
    $statement->execute();
    return $statement->fetchAll();
}

// Re-authentication: the signed-in administrator's current password.
function accounts_verify_admin_password(PDO $connection, string $password): bool
{
    $admin = accounts_find($connection, (int) current_user()['id']);
    return $admin !== null && $admin['role'] === 'super_admin' && password_verify($password, $admin['password_hash']);
}

// ── Password set by someone else → change it at the next sign-in (users.must_change_password, migration
// 20261019_users_must_change_password). Without the column nothing is enforced. ─────────────────────────────────
function accounts_must_change_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) $ready = (int) $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'must_change_password'")->fetchColumn() === 1;
    return $ready;
}

// $on = true when the System Administrator or staff created the account or set its password; false once the account
// holder chose their own. Clears the signed-in session's flag when it is their own account.
function accounts_set_must_change(PDO $connection, int $user_id, bool $on): void
{
    if (!accounts_must_change_ready($connection)) return;
    $connection->prepare('UPDATE users SET must_change_password = :on WHERE id = :id')->execute(['on' => $on ? 1 : 0, 'id' => $user_id]);
    if (!$on && (int) (current_user()['id'] ?? 0) === $user_id) unset($_SESSION['user']['must_change_password']);
}

// ── Password rules (every form that sets a password) ──────────────────────────────
// 10 to 72 characters (72 = bcrypt limit), at least one letter and one number, not a common or easily guessed password,
// not built from the person's own name, email or username, and typed the same twice. Length matters more than symbols.
const ACCOUNTS_PASSWORD_MIN = 10;

function accounts_password_rules_text(): string
{
    return 'At least ' . ACCOUNTS_PASSWORD_MIN . ' characters with a letter and a number. Avoid common passwords (like password123) and your own name, email or username.';
}

// Words that make a password easy to guess (checked after removing digits and symbols), including local words.
function accounts_common_password_words(): array
{
    return ['password', 'passw', 'passwd', 'pass', 'qwerty', 'qwertyuiop', 'asdf', 'asdfgh', 'asdfghjkl', 'zxcvbnm', 'abc', 'abcd', 'abcde', 'abcdef', 'abcdefg', 'abcdefgh', 'iloveyou', 'loveyou', 'love', 'welcome', 'letmein', 'admin', 'administrator', 'user', 'login', 'secret', 'monkey', 'dragon', 'master', 'sunshine', 'princess', 'football', 'basketball', 'baseball', 'superman', 'batman', 'test', 'tester', 'guest', 'default', 'changeme', 'trustno', 'freedom', 'whatever', 'computer', 'internet', 'samsung', 'android', 'iphone',
        'sanjose', 'barangay', 'brgy', 'quirino', 'isabela', 'sjqibms', 'philippines', 'pilipinas', 'pinoy', 'mahalkita', 'mahal', 'iloveu', 'secretary', 'treasurer', 'kagawad', 'official', 'health', 'healthworker', 'resident', 'staff', 'account'];
}

function accounts_password_errors(string $password, string $confirm, array $personal = []): array
{
    $errors = [];
    $lower = mb_strtolower($password);
    $letters = preg_replace('/[^\p{L}]/u', '', $lower) ?? '';
    $digits = preg_replace('/\D/', '', $password) ?? '';
    if (strlen($password) < ACCOUNTS_PASSWORD_MIN || strlen($password) > 72) $errors['new_password'] = 'Use ' . ACCOUNTS_PASSWORD_MIN . ' to 72 characters.';
    elseif (!preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) $errors['new_password'] = 'Include at least one letter and one number.';
    elseif (count(array_unique(mb_str_split($lower))) < 5) $errors['new_password'] = 'Use more different characters; repeated characters are easy to guess.';
    elseif (in_array($letters, accounts_common_password_words(), true) || mb_strlen($letters) < 3 && preg_match('/^(0123456789|1234567890|9876543210|0987654321|(\d)\2+)/', $digits)) $errors['new_password'] = 'This password is too common or easy to guess. Choose another one.';
    else {
        // The person's own name parts, email name or username (3+ letters) must not appear in the password.
        $words = [];
        foreach ($personal as $value) {
            $value = mb_strtolower(trim((string) $value));
            if ($value === '') continue;
            if (str_contains($value, '@')) $value = strstr($value, '@', true);
            foreach (preg_split('/[^\p{L}\p{N}]+/u', $value) ?: [] as $word) if (mb_strlen($word) >= 3) $words[] = $word;
        }
        foreach (array_unique($words) as $word) if (str_contains($lower, $word)) { $errors['new_password'] = 'Do not use your name, email or username in the password.'; break; }
    }
    if (!isset($errors['new_password']) && !hash_equals($password, $confirm)) $errors['confirm_password'] = 'The passwords do not match.';
    return $errors;
}

// Audit entries never contain passwords or password hashes.
function accounts_audit(PDO $connection, int $user_id, string $action, array $details = []): void
{
    residents_audit($connection, 'user', $user_id, $action, $details);
}
