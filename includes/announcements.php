<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/residents.php';

function announcements_can_manage(): bool
{
    return role_can('announcements.manage');
}

// Health Workers see and post only Health announcements (announcements.category, migration 20261016_health_worker_portal).
function announcements_health_only(): bool
{
    return has_role('health_worker');
}

function announcements_category_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) $ready = (int) $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'category'")->fetchColumn() === 1;
    return $ready;
}

function announcements_categories(): array
{
    return ['general' => 'General', 'health' => 'Health'];
}

function announcements_require_manage(): void
{
    require_auth();
    if (!announcements_can_manage()) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function announcements_resident_purok(PDO $connection): ?string
{
    $statement = $connection->prepare("SELECT r.purok FROM users u INNER JOIN residents r ON r.id = u.resident_id WHERE u.id = :user_id AND r.status = 'active' AND r.purok IS NOT NULL AND r.purok <> '' LIMIT 1");
    $statement->execute(['user_id' => current_user()['id']]);
    $purok = $statement->fetchColumn();
    return is_string($purok) && $purok !== '' ? $purok : null;
}

// Display labels for every audience value, including earlier values kept for existing records.
function announcements_audience_label(string $audience): string
{
    return [
        'public' => 'Public', 'all_residents' => 'All Residents', 'purok' => 'Specific Purok', 'barangay_officials' => 'Barangay Officials',
        'kagawads' => 'Barangay Official accounts only (earlier option)', 'all_staff' => 'All Barangay Staff (earlier option)',
    ][$audience] ?? ucwords(str_replace('_', ' ', $audience));
}

// True once announcements.audience accepts 'barangay_officials'. Until then the option is not offered, because a
// non-strict database would silently store an unknown value as ''.
function announcements_officials_audience_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $type = $connection->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'audience'")->fetchColumn();
        $ready = is_string($type) && str_contains($type, "'barangay_officials'");
    }
    return $ready;
}

// Options for the Target Audience dropdown, in the confirmed order. When editing an announcement that still uses an
// earlier audience value, that value is kept as an option so saving never changes its recipients silently.
function announcements_selectable_audiences(PDO $connection, ?string $current = null): array
{
    $options = ['public' => 'Public', 'all_residents' => 'All Residents', 'purok' => 'Specific Purok'];
    if (announcements_officials_audience_ready($connection)) $options['barangay_officials'] = 'Barangay Officials';
    if ($current !== null && in_array($current, ['kagawads', 'all_staff'], true)) $options[$current] = announcements_audience_label($current);
    return $options;
}

function announcements_visibility_sql(PDO $connection, array &$params): string
{
    if (announcements_can_manage()) {
        return "a.audience <> 'selected_users'" . (announcements_health_only() && announcements_category_ready($connection) ? " AND a.category = 'health'" : '');
    }

    $params[':audience_public'] = 'public';
    $params[':audience_all'] = 'all_residents';
    // Staff-only audiences for this role (Treasurer: All Barangay Staff). Residents and Health Workers get none.
    $staff = '';
    foreach (role_staff_announcement_audiences() as $index => $audience) {
        $params[':audience_staff_' . $index] = $audience;
        $staff .= ', :audience_staff_' . $index;
    }
    $purok = announcements_resident_purok($connection);
    if ($purok === null) {
        return "a.audience IN (:audience_public, :audience_all$staff)";
    }

    $params[':audience_purok'] = 'purok';
    $params[':target_purok'] = $purok;
    return "(a.audience IN (:audience_public, :audience_all$staff) OR (a.audience = :audience_purok AND a.target_purok = :target_purok))";
}

function announcements_preview(string $body): string
{
    $text = preg_replace('/\s+/', ' ', trim(strip_tags($body))) ?? '';
    return function_exists('mb_strimwidth') ? mb_strimwidth($text, 0, 120, '...', 'UTF-8') : (strlen($text) > 117 ? substr($text, 0, 117) . '...' : $text);
}

function announcements_format_date(?string $value): string
{
    if (!$value) return '';
    $date = date_create($value);
    return $date ? $date->format('M j, Y g:i A') : $value;
}

function announcements_find(PDO $connection, int $id): ?array
{
    $params = [':id' => $id];
    $visibility = announcements_visibility_sql($connection, $params);
    $statement = $connection->prepare("SELECT a.id, a.author_id, a.title, a.body, a.audience, a.target_purok, a.status, a.published_at, a.created_at, u.name AS author_name" . (announcements_category_ready($connection) ? ', a.category' : ", 'general' AS category") . " FROM announcements a INNER JOIN users u ON u.id = a.author_id WHERE a.id = :id AND $visibility LIMIT 1");
    $statement->execute($params);
    $result = $statement->fetch();
    return $result ?: null;
}

// $allowed_audiences comes from announcements_selectable_audiences(); any other submitted value is rejected.
// The Purok must be one of Purok 1–4 (stored as its number, the same value as on resident profiles, so the right
// residents see it and get the text); "Purok 3" is read as 3. An older stored value may be kept unchanged ($current_purok).
// Returns 'values', 'errors' (a list) and 'field_errors' (the same messages by field, for the form).
function announcements_validate(array $input, array $allowed_audiences = ['public' => 'Public', 'all_residents' => 'All Residents', 'purok' => 'Specific Purok'], ?string $current_purok = null): array
{
    $title = trim((string) ($input['title'] ?? ''));
    $body = trim((string) ($input['body'] ?? ''));
    $audience = (string) ($input['audience'] ?? '');
    $target_purok = trim((string) ($input['target_purok'] ?? ''));
    if (preg_match('/^purok\s+(\S+)$/i', $target_purok, $match)) $target_purok = $match[1];
    $field_errors = [];
    if ($title === '') $field_errors['title'] = 'Title is required.';
    elseif (mb_strlen($title) > 200) $field_errors['title'] = 'Title must not exceed 200 characters.';
    if ($body === '') $field_errors['body'] = 'Content is required.';
    elseif (mb_strlen($body) > 10000) $field_errors['body'] = 'Content must not exceed 10,000 characters.';
    if (!array_key_exists($audience, $allowed_audiences)) $field_errors['audience'] = 'Select a target audience.';
    if ($audience === 'purok' && !array_key_exists($target_purok, residents_purok_options()) && ($current_purok === null || $target_purok !== $current_purok)) $field_errors['target_purok'] = 'Please select a purok.';
    if ($audience !== 'purok') $target_purok = null;
    // Category: Health Workers always post Health announcements; others choose General or Health.
    $category = announcements_health_only() ? 'health' : (string) ($input['category'] ?? 'general');
    if (!array_key_exists($category, announcements_categories())) $field_errors['category'] = 'Select a category.';
    return ['values' => compact('title', 'body', 'audience', 'target_purok', 'category'), 'errors' => array_values($field_errors), 'field_errors' => $field_errors];
}

// How many mobile numbers would receive the SMS for each audience and Purok, for the note next to Publish
// (['public' => n, 'all_residents' => n, 'purok' => ['1' => n, ...]]).
function announcements_sms_counts(PDO $connection): array
{
    require_once __DIR__ . '/sms.php';
    $counts = ['public' => count(sms_announcement_recipients($connection, ['audience' => 'public'])), 'purok' => []];
    $counts['all_residents'] = $counts['public'];
    foreach (array_keys(residents_purok_options()) as $purok) $counts['purok'][(string) $purok] = count(sms_announcement_recipients($connection, ['audience' => 'purok', 'target_purok' => (string) $purok]));
    return $counts;
}

function announcements_history(PDO $connection, int $id, string $action, ?string $from, string $to, ?string $summary = null): void
{
    $statement = $connection->prepare('INSERT INTO announcement_history (announcement_id, action, previous_status, new_status, actor_id, change_summary) VALUES (:id, :action, :previous, :new_status, :actor, :summary)');
    $statement->execute(['id' => $id, 'action' => $action, 'previous' => $from, 'new_status' => $to, 'actor' => current_user()['id'], 'summary' => $summary]);
}

function announcements_audit(PDO $connection, int $id, string $action, array $details = []): void
{
    $statement = $connection->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, details) VALUES (:user_id, :action, :type, :id, :ip, :agent, :details)');
    $statement->execute(['user_id' => current_user()['id'], 'action' => $action, 'type' => 'announcement', 'id' => $id, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null, 'agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500), 'details' => $details === [] ? '{}' : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
}

// Status an archived announcement held before archival: the latest archive history entry, else published_at as evidence of publication. Null when it cannot be determined reliably.
function announcements_status_before_archive(PDO $connection, array $record): ?string
{
    $statement = $connection->prepare("SELECT previous_status FROM announcement_history WHERE announcement_id = :id AND new_status = 'archived' ORDER BY created_at DESC, id DESC LIMIT 1");
    $statement->execute(['id' => $record['id']]);
    $previous = $statement->fetchColumn();
    if ($previous === 'draft') return 'draft';
    if ($previous === 'published' || ($previous === false && $record['published_at'] !== null)) return $record['published_at'] !== null ? 'published' : null;
    return null;
}

function announcement_notification_recipients(PDO $connection, array $announcement): array
{
    $recipients = [];
    if (in_array($announcement['audience'], ['public', 'all_residents'], true)) {
        $statement = $connection->query("SELECT id FROM users WHERE role IN ('resident', 'health_worker', 'official') AND status = 'active'");
        $recipients = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    } elseif ($announcement['audience'] === 'purok' && !empty($announcement['target_purok'])) {
        $statement = $connection->prepare("SELECT u.id FROM users u INNER JOIN residents r ON r.id = u.resident_id WHERE u.role = 'resident' AND u.status = 'active' AND r.status = 'active' AND r.purok = :purok");
        $statement->execute(['purok' => $announcement['target_purok']]);
        $recipients = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    } elseif ($announcement['audience'] === 'barangay_officials') {
        // Barangay Officials: active accounts of the officials roles only (no Health Workers, no residents).
        $roles = barangay_officials_audience_roles();
        $statement = $connection->prepare('SELECT id FROM users WHERE status = \'active\' AND role IN (' . implode(', ', array_fill(0, count($roles), '?')) . ')');
        $statement->execute($roles);
        $recipients = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    } elseif ($announcement['audience'] === 'kagawads') {
        // Barangay Kagawads only (Kagawads use the existing 'official' role).
        $statement = $connection->query("SELECT id FROM users WHERE role = 'official' AND status = 'active'");
        $recipients = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    } elseif ($announcement['audience'] === 'all_staff') {
        // All Barangay Staff: Kagawads, Secretary and Treasurer. Health Workers are intentionally excluded.
        $statement = $connection->query("SELECT id FROM users WHERE role IN ('official', 'secretary', 'treasurer') AND status = 'active'");
        $recipients = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    $managerStatement = $connection->query("SELECT id FROM users WHERE role IN ('super_admin', 'secretary') AND status = 'active'");
    $recipients = array_merge($recipients, array_map('intval', $managerStatement->fetchAll(PDO::FETCH_COLUMN)));
    return array_values(array_unique($recipients));
}

function create_announcement_notifications(PDO $connection, array $announcement): void
{
    if ($announcement['audience'] === 'selected_users') {
        return;
    }

    $statement = $connection->prepare('INSERT INTO announcement_notifications (recipient_user_id, announcement_id, notification_type, title, message) VALUES (:recipient_user_id, :announcement_id, :notification_type, :title, :message) ON DUPLICATE KEY UPDATE id = id');
    $message = announcements_preview((string) $announcement['body']);
    foreach (announcement_notification_recipients($connection, $announcement) as $recipient_id) {
        $statement->execute([
            'recipient_user_id' => $recipient_id,
            'announcement_id' => (int) $announcement['id'],
            'notification_type' => 'announcement_published',
            'title' => $announcement['title'],
            'message' => $message,
        ]);
    }
}
