<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// ─────────────────────────────────────────────────────────────────────────────
// Staff portals and role permissions — the single source of truth for what each office may use.
// Module (sidebar) access is defined per item in includes/navigation.php; the capabilities below cover
// everything else (dashboard statistics, management rights, activity history).
// Database roles are unchanged: a Barangay Kagawad uses the existing 'official' role.
// ─────────────────────────────────────────────────────────────────────────────

// Every sign-in portal. Staff portals ('gate' => true) require the Admin Access Gate before each login;
// the Resident Portal signs in directly.
function login_portals(): array
{
    return staff_portals() + [
        'resident' => [
            'role' => 'resident',
            'title' => 'Resident',
            'short' => 'Resident',
            'icon' => 'users',
            'summary' => 'Request barangay documents, track your requests, and read community announcements.',
            'gate' => false,
        ],
    ];
}

function staff_portals(): array
{
    return array_map(static fn (array $portal): array => $portal + ['gate' => true], [
        'secretary' => [
            'role' => 'secretary',
            'title' => 'Barangay Secretary',
            'short' => 'Secretary',
            'icon' => 'file',
            'summary' => 'Resident records, document processing, registrations, and administrative information.',
        ],
        'treasurer' => [
            'role' => 'treasurer',
            'title' => 'Barangay Treasurer',
            'short' => 'Treasurer',
            'icon' => 'wallet',
            'summary' => 'Collections, official receipts, expenses, project budgets, and financial reports.',
        ],
        'punong_barangay' => [
            'role' => 'punong_barangay',
            'title' => 'Punong Barangay',
            'short' => 'Punong Barangay',
            'icon' => 'badge',
            'summary' => 'Barangay leadership: review the barangay finances and approve or reject disbursements.',
        ],
        'kagawad' => [
            'role' => 'official',
            'title' => 'Barangay Official',
            'short' => 'Official',
            'icon' => 'badge',
            'summary' => 'Committee work, announcements, projects and programs, disaster management, and reports.',
        ],
        'health' => [
            'role' => 'health_worker',
            'title' => 'Barangay Health Worker',
            'short' => 'Health Worker',
            'icon' => 'heart',
            'summary' => 'Health services, resident health records, and community health programs.',
        ],
        'admin' => [
            'role' => 'super_admin',
            'title' => 'System Administrator',
            'short' => 'Administrator',
            'icon' => 'shield',
            'summary' => 'Full system administration: users, settings, security, and audit logs.',
        ],
    ]);
}

// Any sign-in portal (staff or resident) by key.
function staff_portal(string $key): ?array
{
    return login_portals()[$key] ?? null;
}

function staff_portal_for_role(?string $role): ?string
{
    foreach (login_portals() as $key => $portal) {
        if ($portal['role'] === $role) return $key;
    }
    return null;
}

// Human-readable title for the signed-in user's role (for the topbar, sidebar and dashboard).
function role_title(?string $role = null): string
{
    $role ??= current_user()['role'] ?? '';
    $portal = staff_portal_for_role($role);
    if ($portal !== null) return login_portals()[$portal]['title'];
    return ucwords(str_replace('_', ' ', (string) $role));
}

function role_capabilities(): array
{
    return [
        // Dashboard statistics (aggregate counts only).
        'stats.population' => ['super_admin', 'secretary', 'official'],
        'stats.households' => ['super_admin', 'secretary', 'official'],
        'stats.registrations' => ['super_admin', 'secretary'],
        'stats.documents' => ['super_admin', 'secretary', 'official'],
        'stats.complaints' => [],   // Complaints & Blotter is hidden for now (was: super_admin, secretary)
        'stats.health_due' => ['health_worker'],   // health records are for Health Workers only
        'stats.programs' => ['super_admin', 'treasurer', 'official'],
        'stats.announcements' => ['super_admin', 'secretary', 'official'],
        // Financial figures: Treasurer, Punong Barangay and System Administrator (read-only); includes/finance.php
        // re-checks the role in the database.
        'stats.finance' => ['treasurer', 'punong_barangay', 'super_admin'],
        // Management rights.
        'residents.manage' => ['super_admin', 'secretary'],
        'documents.process' => ['super_admin', 'secretary'],
        'documents.request.create' => ['super_admin', 'secretary'],
        'documents.templates.manage' => ['super_admin', 'secretary'],
        // Confidential Complaints & Blotter administration. Hearing personnel assignments never grant this.
        'complaints.manage' => ['super_admin', 'secretary'],
        'announcements.manage' => ['super_admin', 'secretary', 'official', 'health_worker'],   // Health Workers: Health announcements only (includes/announcements.php)
        // Relief & Assistance: record, edit, mark as given and void (everyone with the menu item may view and print).
        'assistance.manage' => ['super_admin', 'secretary', 'official', 'punong_barangay'],
        // Activity history (the modules each role may see are listed in role_activity_modules()).
        'activity.view' => ['super_admin', 'secretary', 'treasurer'],
    ];
}

function role_can(string $capability): bool
{
    $roles = role_capabilities()[$capability] ?? [];
    return has_role(...$roles);
}

// Roles whose active accounts form the "Barangay Officials" announcement audience: Punong Barangay, System
// Administrator, Secretary, Treasurer and the 'official' role (Barangay Kagawads; SK Chairman and SK Kagawads use the
// same role — there is no separate SK role). Health Workers and residents are excluded.
function barangay_officials_audience_roles(): array
{
    return ['super_admin', 'punong_barangay', 'secretary', 'treasurer', 'official'];
}

// Restricted (officials-only) announcement audiences the signed-in role may read and be notified about.
// 'barangay_officials' is the current audience; 'kagawads' and 'all_staff' are earlier values that stay readable by
// their original recipients for any announcement that still uses them (none are offered for new announcements).
function role_staff_announcement_audiences(): array
{
    $role = current_user()['role'] ?? '';
    if (!in_array($role, barangay_officials_audience_roles(), true)) return [];
    return $role === 'treasurer' ? ['barangay_officials', 'all_staff'] : ['barangay_officials', 'kagawads', 'all_staff'];
}

// Activity-history modules per role. The super administrator sees every audit record (see includes/activity.php).
function role_activity_modules(): array
{
    return match (current_user()['role'] ?? '') {
        'secretary' => ['announcements', 'residents', 'households', 'documents', 'complaints', 'assistance'],
        'treasurer' => ['finance'],
        default => [],
    };
}
