<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/roles.php';

// Module access per role (sidebar, module pages and page guards). Secretary, Treasurer and Kagawad ('official')
// follow the approved office access lists; see includes/roles.php for dashboard and management capabilities.

function navigation_groups(): array
{
    return [
        [
            'label' => 'Dashboard',
            'items' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid', 'href' => 'dashboard.php', 'roles' => ['super_admin', 'punong_barangay', 'secretary', 'treasurer', 'health_worker', 'official', 'resident']],
            ],
        ],
        [
            'label' => 'Barangay Services',
            'items' => [
                ['key' => 'residents', 'label' => 'Residents', 'icon' => 'users', 'href' => 'module.php?module=residents', 'roles' => ['super_admin', 'secretary']],
                ['key' => 'households', 'label' => 'Households', 'icon' => 'home', 'href' => 'module.php?module=households', 'roles' => ['super_admin', 'secretary']],
                ['key' => 'documents', 'label' => 'Documents', 'icon' => 'file', 'href' => 'documents.php', 'roles' => ['super_admin', 'secretary']],
                ['key' => 'my_documents', 'label' => 'Document Requests', 'icon' => 'file', 'href' => 'resident_documents.php', 'roles' => ['resident', 'health_worker']],
                // Complaints & Blotter is hidden for now (no roles): the pages, reports and dashboard card are not shown and
                // refuse access. The records stay in the database; put the roles back to restore the module
                // (staff: 'super_admin', 'secretary'; residents' My Complaints: 'resident'; and 'stats.complaints' in roles.php).
                ['key' => 'complaints', 'label' => 'Complaints & Blotter', 'icon' => 'case', 'href' => 'complaints.php', 'roles' => []],
                ['key' => 'my_complaints', 'label' => 'My Complaints', 'icon' => 'case', 'href' => 'resident_complaints.php', 'roles' => []],
                ['key' => 'my_household', 'label' => 'My Household', 'icon' => 'home', 'href' => 'my_household.php', 'roles' => ['resident', 'health_worker', 'treasurer']],   // staff who live in the barangay too
            ],
        ],
        [
            'label' => 'Community Services',
            'items' => [
                // Treasurer: read-only access (announcements.manage is not granted) so "All Barangay Staff" announcements reach them.
                ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'megaphone', 'href' => 'module.php?module=announcements', 'roles' => ['super_admin', 'punong_barangay', 'secretary', 'treasurer', 'health_worker', 'official', 'resident']],
                ['key' => 'health', 'label' => 'Health', 'icon' => 'heart', 'href' => 'health.php', 'roles' => ['health_worker']],
                // Disaster Management: Barangay Official (BDRRMC) and System Administrator edit; Secretary views (includes/disaster.php).
                ['key' => 'disaster', 'label' => 'Disaster Management', 'icon' => 'shield', 'href' => 'disaster.php', 'roles' => ['super_admin', 'official', 'secretary']],
                // Residents, Health Workers and the Treasurer: evacuation centers, hazards and hotlines, read-only (disaster_info.php).
                ['key' => 'disaster_info', 'label' => 'Disaster Info', 'icon' => 'shield', 'href' => 'disaster_info.php', 'roles' => ['resident', 'health_worker', 'treasurer']],
                // Relief & Assistance: recorded by the Secretary, Officials and the Punong Barangay; System Administrator views only (includes/assistance.php).
                ['key' => 'assistance', 'label' => 'Relief & Assistance', 'icon' => 'gift', 'href' => 'assistance.php', 'roles' => ['super_admin', 'punong_barangay', 'secretary', 'official']],
            ],
        ],
        [
            'label' => 'Barangay Administration',
            'items' => [
                ['key' => 'officials', 'label' => 'Barangay Officials', 'icon' => 'badge', 'href' => 'officials.php', 'roles' => ['super_admin', 'secretary']],
                // Financial Management: Treasurer, Punong Barangay, and System Administrator (read-only; see includes/finance.php).
                ['key' => 'finance', 'label' => 'Financial Management', 'icon' => 'wallet', 'href' => 'finance.php', 'roles' => ['treasurer', 'punong_barangay', 'super_admin']],
                ['key' => 'projects', 'label' => 'Projects', 'icon' => 'briefcase', 'href' => 'module.php?module=projects', 'roles' => ['super_admin', 'treasurer', 'official']],
                ['key' => 'inventory', 'label' => 'Inventory', 'icon' => 'archive', 'href' => 'inventory.php', 'roles' => ['super_admin', 'health_worker']],
            ],
        ],
        [
            'label' => 'System Management',
            'items' => [
                ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart', 'href' => 'reports.php', 'roles' => ['super_admin', 'punong_barangay', 'secretary', 'treasurer', 'official']],
                ['key' => 'activity', 'label' => 'Activity History', 'icon' => 'history', 'href' => 'activity_history.php', 'roles' => ['secretary', 'treasurer']], // System Administrator: Audit Logs
                ['key' => 'registrations', 'label' => 'Resident Registrations', 'icon' => 'user-plus', 'href' => 'module.php?module=registrations', 'roles' => ['super_admin', 'secretary']],
                ['key' => 'users', 'label' => 'User Management', 'icon' => 'user-cog', 'href' => 'users.php', 'roles' => ['super_admin']],
                ['key' => 'audit', 'label' => 'Audit Logs', 'icon' => 'history', 'href' => 'audit_logs.php', 'roles' => ['super_admin']],
            ],
        ],
    ];
}

function accessible_navigation_groups(): array
{
    $role = current_user()['role'] ?? '';
    $groups = [];

    foreach (navigation_groups() as $group) {
        $items = array_values(array_filter($group['items'], static fn (array $item): bool => in_array($role, $item['roles'], true)));
        if ($items !== []) {
            $group['items'] = $items;
            $groups[] = $group;
        }
    }

    return $groups;
}

function navigation_item(string $key): ?array
{
    foreach (navigation_groups() as $group) {
        foreach ($group['items'] as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }
    }

    return null;
}

function can_access_navigation(string $key): bool
{
    $item = navigation_item($key);
    return $item !== null && in_array(current_user()['role'] ?? '', $item['roles'], true);
}

function icon_svg(string $name): string
{
    $icons = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
        'map-pin' => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'folder' =>'<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'case' => '<path d="M21 16V8a2 2 0 0 0-2-2h-3V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H5a2 2 0 0 0-2 2v8"/><path d="M3 11h18v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM9 6h6M9 15h6"/>',
        'megaphone' => '<path d="m3 11 18-5v12L3 14v-3zM11.6 16.4 13 21H8l-1.7-5.1"/>',
        'heart' => '<path d="M20.8 8.8a5.5 5.5 0 0 0-7.8 0L12 9.8l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 25l7.8-7.4 1-1a5.5 5.5 0 0 0 0-7.8z"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'badge' => '<circle cx="12" cy="8" r="5"/><path d="M8.2 12.5 7 22l5-3 5 3-1.2-9.5"/>',
        'wallet' => '<path d="M4 7V5a2 2 0 0 1 2-2h12v4"/><path d="M4 7h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2z"/><path d="M16 13h4"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v3h4v-3"/>',
        'archive' => '<path d="M3 6h18M5 6v14h14V6M8 10h8M4 3h16v3H4z"/>',
        'chart' => '<path d="M4 19V5M4 19h17"/><path d="m7 15 4-4 3 2 5-6"/>',
        'user-cog' => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6M19.4 15a1.7 1.7 0 0 0 0-3.2l-.4-.7a1.7 1.7 0 0 0-2.9 0l-.4.7a1.7 1.7 0 0 0 0 3.2l.4.7a1.7 1.7 0 0 0 2.9 0zM18 11v-1M18 18v-1M14.5 14.5h-1M22.5 14.5h-1"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 0 3.2l.4.7a1.7 1.7 0 0 0-2.9 0l-.4-.7a1.7 1.7 0 0 0 0-3.2l.4-.7a1.7 1.7 0 0 0 2.9 0l.4.7z"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5M12 7v5l3 2"/>',
        'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-6h6v6"/>',
        'user-plus' => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6M19 8v6M16 11h6"/>',
        'gift' => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
    ];

    return '<svg class="nav-svg" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$name] ?? $icons['grid']) . '</svg>';
}
