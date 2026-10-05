<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/activity.php';

function dashboard_card_definitions(): array
{
    return [
        'total_resident_profiles' => ['label' => 'Total Resident Profiles', 'icon' => 'users', 'module' => 'residents', 'cap' => 'stats.population'],
        'active_residents' => ['label' => 'Active Residents', 'icon' => 'users', 'module' => 'residents', 'cap' => 'stats.population'],
        'total_households' => ['label' => 'Total Households', 'icon' => 'home', 'module' => 'households', 'cap' => 'stats.households'],
        'pending_resident_registrations' => ['label' => 'Pending Resident Registrations', 'icon' => 'user-plus', 'module' => 'registrations', 'cap' => 'stats.registrations'],
        'pending_documents' => ['label' => 'Pending Documents', 'icon' => 'file', 'module' => 'documents', 'cap' => 'stats.documents'],
        'active_complaints' => ['label' => 'Active Complaints', 'icon' => 'case', 'module' => 'complaints', 'cap' => 'stats.complaints'],
        'active_programs' => ['label' => 'Active Programs', 'icon' => 'briefcase', 'module' => 'projects', 'cap' => 'stats.programs'],
        'published_announcements' => ['label' => 'Published Announcements', 'icon' => 'megaphone', 'module' => 'announcements', 'cap' => 'stats.announcements'],
        // Health Worker only: follow-ups and scheduled visits due today or overdue (opens that list).
        'health_due' => ['label' => 'Health: Due Today & Overdue', 'icon' => 'heart', 'module' => 'health', 'cap' => 'stats.health_due', 'query' => 'show=all&due=1'],
    ];
}

// Cards follow the office's statistics capabilities (includes/roles.php). A card links to its module only when the
// office may open that module; otherwise it is shown as an aggregate figure (for example, Kagawad resident summaries).
function authorized_dashboard_cards(): array
{
    $cards = [];
    foreach (dashboard_card_definitions() as $key => $card) {
        if (role_can($card['cap'])) {
            $card['key'] = $key;
            $card['href'] = can_access_navigation($card['module']) ? navigation_item($card['module'])['href'] . (isset($card['query']) ? '?' . $card['query'] : '') : null;
            $cards[$key] = $card;
        }
    }
    return $cards;
}

function empty_demographic_statistics(): array
{
    return [
        'total' => 0,
        'sex' => ['Male' => 0, 'Female' => 0, 'Other' => 0, 'Unknown / Unspecified' => 0],
        'age' => ['Children' => 0, 'Teenagers' => 0, 'Adults' => 0, 'Senior Citizens' => 0, 'Unknown Age' => 0],
        'status' => ['Active' => 0, 'Pending' => 0, 'Inactive' => 0, 'Moved' => 0, 'Deceased' => 0],
    ];
}

function demographic_age(?string $birth_date): ?int
{
    if ($birth_date === null || $birth_date === '') {
        return null;
    }

    $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $birth_date);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$birth || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }

    $today = new DateTimeImmutable('today');
    if ($birth > $today) {
        return null;
    }

    return $birth->diff($today)->y;
}

function demographic_statistics(PDO $connection): array
{
    $statistics = empty_demographic_statistics();
    $rows = $connection->query('SELECT birth_date, sex, status FROM residents')->fetchAll();

    foreach ($rows as $row) {
        $statistics['total']++;

        $sex = strtolower(trim((string) ($row['sex'] ?? '')));
        $sex_label = match ($sex) {
            'male' => 'Male',
            'female' => 'Female',
            'other' => 'Other',
            default => 'Unknown / Unspecified',
        };
        $statistics['sex'][$sex_label]++;

        $age = demographic_age($row['birth_date'] ?? null);
        $age_label = match (true) {
            $age === null => 'Unknown Age',
            $age <= 12 => 'Children',
            $age <= 17 => 'Teenagers',
            $age <= 59 => 'Adults',
            default => 'Senior Citizens',
        };
        $statistics['age'][$age_label]++;

        $status_label = match ((string) ($row['status'] ?? '')) {
            'active' => 'Active',
            'pending' => 'Pending',
            'inactive' => 'Inactive',
            'moved' => 'Moved',
            'deceased' => 'Deceased',
            default => null,
        };
        if ($status_label !== null) {
            $statistics['status'][$status_label]++;
        }
    }

    // PWD and Solo Parents among ACTIVE residents (resident profile checkboxes; review migration 20261007_resident_pwd_solo_parent).
    // Absent until the migration is applied, so the Dashboard shows the block only when the columns exist.
    $sector_ready = (int) $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'residents' AND COLUMN_NAME IN ('is_pwd', 'is_solo_parent')")->fetchColumn() === 2;
    if ($sector_ready) {
        $sector = $connection->query("SELECT COALESCE(SUM(is_pwd = 1), 0) AS pwd, COALESCE(SUM(is_solo_parent = 1), 0) AS solo FROM residents WHERE status = 'active'")->fetch();
        $statistics['sector'] = ['PWD' => (int) $sector['pwd'], 'Solo Parents' => (int) $sector['solo']];
    }

    return $statistics;
}

function can_manage_announcements(): bool
{
    return role_can('announcements.manage');
}

function announcement_preview(string $body): string
{
    $text = preg_replace('/\s+/', ' ', trim(strip_tags($body))) ?? '';
    return function_exists('mb_strimwidth')
        ? mb_strimwidth($text, 0, 120, '...', 'UTF-8')
        : (strlen($text) > 117 ? substr($text, 0, 117) . '...' : $text);
}

function normalize_dashboard_announcements(array $announcements): array
{
    foreach (['published', 'drafts'] as $tab) {
        foreach ($announcements[$tab] as &$announcement) {
            $announcement = [
                'id' => (int) $announcement['id'],
                'title' => (string) $announcement['title'],
                'preview' => announcement_preview((string) $announcement['body']),
                'date' => (string) ($tab === 'published'
                    ? ($announcement['published_at'] ?: $announcement['created_at'])
                    : $announcement['created_at']),
            ];
        }
        unset($announcement);
    }
    return $announcements;
}

function announcement_purok(PDO $connection): ?string
{
    $statement = $connection->prepare('SELECT r.purok FROM users u INNER JOIN residents r ON r.id = u.resident_id WHERE u.id = :user_id AND r.purok IS NOT NULL AND r.purok <> \'\' LIMIT 1');
    $statement->execute(['user_id' => current_user()['id']]);
    $purok = $statement->fetchColumn();
    return is_string($purok) && $purok !== '' ? $purok : null;
}

function dashboard_announcements(PDO $connection): array
{
    $published = [];
    $drafts = [];
    $purok = announcement_purok($connection);
    // Staff-only audiences for this role (fixed internal values from role_staff_announcement_audiences()).
    $staff = implode('', array_map(static fn (string $a): string => ", '" . $a . "'", array_filter(role_staff_announcement_audiences(), static fn (string $a): bool => in_array($a, ['barangay_officials', 'kagawads', 'all_staff'], true))));
    // Health Workers see only Health announcements (announcements.category), here as in the Announcements module.
    $health_only = has_role('health_worker') && (int) $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'category'")->fetchColumn() === 1;
    $category = $health_only ? " AND category = 'health'" : '';
    $publishedSql = "SELECT id, title, body, published_at, created_at
        FROM announcements
        WHERE status = 'published'$category
        AND (audience IN ('public', 'all_residents'$staff)" . ($purok !== null ? " OR (audience = 'purok' AND target_purok = :target_purok)" : '') . ")
        ORDER BY published_at DESC, id DESC
        LIMIT 3";
    $publishedStatement = $connection->prepare($publishedSql);
    if ($purok !== null) {
        $publishedStatement->bindValue(':target_purok', $purok, PDO::PARAM_STR);
    }
    $publishedStatement->execute();
    $published = $publishedStatement->fetchAll();

    if (can_manage_announcements()) {
        $draftStatement = $connection->prepare("SELECT id, title, body, published_at, created_at
            FROM announcements
            WHERE status = 'draft'$category
            ORDER BY created_at DESC, id DESC
            LIMIT 3");
        $draftStatement->execute();
        $drafts = $draftStatement->fetchAll();
    }

    return normalize_dashboard_announcements(['published' => $published, 'drafts' => $drafts]);
}

function dashboard_recent_activity(PDO $connection): array
{
    return activity_fetch($connection, 3);
}

function dashboard_statistics(): array
{
    $statistics = [];
    $errors = [];
    $demographics = null;
    $announcements = null;
    $activities = null;
    $queries = [
        'total_resident_profiles' => 'SELECT COUNT(*) FROM residents',
        'active_residents' => "SELECT COUNT(*) FROM residents WHERE status = 'active'",
        'total_households' => "SELECT COUNT(DISTINCT rh.household_id)
            FROM resident_households rh
            INNER JOIN residents r ON r.id = rh.resident_id
            WHERE rh.is_primary = TRUE AND rh.left_at IS NULL AND r.status = 'active'",
        // Online sign-ups plus family members added by residents (both reviewed in Resident Registrations).
        'pending_resident_registrations' => "SELECT (SELECT COUNT(*) FROM registration_applications
            WHERE application_type = 'resident'
            AND status IN ('submitted', 'verified', 'awaiting_final_approval'))
            + (SELECT COUNT(*) FROM household_member_requests WHERE status = 'pending')",
        'pending_documents' => "SELECT COUNT(*) FROM document_requests WHERE status = 'pending'",
        'active_complaints' => "SELECT COUNT(*) FROM complaint_cases
            WHERE status IN ('pending_review', 'open', 'under_review', 'for_hearing')",
        'active_programs' => "SELECT COUNT(*) FROM barangay_projects WHERE status = 'ongoing'",
        'published_announcements' => "SELECT COUNT(*) FROM announcements WHERE status = 'published'",
    ];

    try {
        $connection = db();
        foreach (authorized_dashboard_cards() as $key => $card) {
            try {
                // Active residents and households use the Reports module's statistics functions (the same figures as the
                // Residents and Households reports and the Analytics Overview).
                $value = match ($key) {
                    'active_residents' => (static function () use ($connection): int { require_once __DIR__ . '/report_stats.php'; return report_residents_population($connection); })(),
                    'total_households' => (static function () use ($connection): int { require_once __DIR__ . '/report_stats.php'; return report_households_total($connection); })(),
                    // Every due record within the Health Worker's Puroks — the same count as the Due Today and Overdue
                    // cards on the Health page (the card opens "All records" filtered to due).
                    'health_due' => (static function () use ($connection): int { require_once __DIR__ . '/health.php'; return health_due_count($connection, false); })(),
                    default => $connection->query($queries[$key])->fetchColumn(),
                };
                $statistics[$key] = (int) $value;
            } catch (PDOException) {
                $statistics[$key] = null;
                $errors[$key] = 'This statistic is temporarily unavailable.';
            }
        }
        if (role_can('stats.population')) {
            try {
                $demographics = demographic_statistics($connection);
            } catch (PDOException) {
                $errors['demographics'] = 'Population demographics are temporarily unavailable.';
            }
        }
        if (can_access_navigation('announcements')) {
            try {
                $announcements = dashboard_announcements($connection);
            } catch (PDOException) {
                $errors['announcements'] = 'Announcements are temporarily unavailable.';
            }
        }
        if (role_can('activity.view')) {
            try {
                $activities = dashboard_recent_activity($connection);
            } catch (PDOException) {
                $errors['activities'] = 'Recent activity is temporarily unavailable.';
            }
        }
    } catch (PDOException) {
        foreach (authorized_dashboard_cards() as $key => $card) {
            $statistics[$key] = null;
            $errors[$key] = 'This statistic is temporarily unavailable.';
        }
        if (role_can('stats.population')) {
            $errors['demographics'] = 'Population demographics are temporarily unavailable.';
        }
        if (can_access_navigation('announcements')) {
            $errors['announcements'] = 'Announcements are temporarily unavailable.';
        }
        if (role_can('activity.view')) {
            $errors['activities'] = 'Recent activity is temporarily unavailable.';
        }
    }

    return ['statistics' => $statistics, 'demographics' => $demographics, 'announcements' => $announcements, 'activities' => $activities, 'errors' => $errors];
}
