<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security_log.php';
security_log_register_denied_hook();

function is_authenticated(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function require_auth(): void
{
    if (!is_authenticated()) {
        flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }
    role_page_guard();
    password_change_guard();
}

// A password set by the System Administrator or staff must be replaced by the account holder before anything else:
// every page except the change-password pages and sign-out sends them there (flag set at sign-in from
// users.must_change_password; cleared when they choose their own password).
function password_change_guard(): void
{
    if (empty($_SESSION['user']['must_change_password'])) return;
    $page = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($page, ['my_password.php', 'account_password.php', 'logout.php'], true)) return;
    redirect(has_role('super_admin') ? 'account_password.php' : 'my_password.php');
}

// Health Worker portal: a Health Worker may open only these pages (Dashboard, Health, Inventory view, Announcements,
// Document Requests, My Household, Disaster Info, and their own account pages). Any other page, even typed in the address bar, sends them to Health.
// Each page still checks its own permissions as well.
function role_page_allowlist(string $role): ?array
{
    return match ($role) {
        'health_worker' => [
            'dashboard.php', 'module.php', 'notifications.php', 'notification_open.php', 'profile.php', 'my_password.php', 'logout.php',
            'health.php', 'health_form.php', 'health_view.php', 'health_action.php', 'health_resident_lookup.php', 'health_print.php', 'health_list_print.php',
            'health_history.php', 'health_conditions.php', 'health_morbidity.php', 'health_morbidity_print.php',
            'module_report.php', 'module_report_print.php', 'module_report_export.php',   // the Health Report only (report_require())
            'health_immunization.php', 'health_immunization_form.php',
            'health_nutrition.php', 'health_nutrition_form.php', 'health_chronic.php', 'health_chronic_form.php', 'health_referrals.php',
            'health_program_action.php', 'health_monthly.php', 'health_monthly_print.php', 'health_monthly_export.php',
            'inventory.php', 'inventory_view.php', 'inventory_photo.php',
            'announcements.php', 'announcement_view.php', 'announcement_form.php', 'announcement_action.php',
            // As residents have (they live in the barangay too): their own documents and household, and Disaster Info.
            'resident_documents.php', 'document_view.php', 'my_household.php', 'disaster_info.php',
        ],
        default => null,
    };
}

function role_page_guard(): void
{
    $allowed = role_page_allowlist((string) (current_user()['role'] ?? ''));
    if ($allowed === null) return;
    $page = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($page === '' || in_array($page, $allowed, true)) return;
    flash('health_error', 'That page is not part of the Health Worker portal.');
    redirect('health.php');
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], $roles, true);
}

function require_role(string ...$roles): void
{
    require_auth();
    if (!has_role(...$roles)) {
        http_response_code(403);
        exit('Access denied.');
    }
}
