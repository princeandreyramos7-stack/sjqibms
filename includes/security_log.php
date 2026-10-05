<?php
declare(strict_types=1);

// Security events for the Audit Logs (audit_logs, entity_type 'security'): sign-ins, failed sign-ins, sign-outs, Admin
// Access Gate results and refused page access. Business actions (residents, documents, announcements, ...) are already
// written by their own modules. Logging never interrupts the request: any failure is written to the PHP error log only.
// Passwords, access codes and session tokens are never recorded.

require_once __DIR__ . '/../config/database.php';

// ── Sign-in lockout (no extra table: counts the failed sign-ins already kept in audit_logs) ──────────────────────
// An account is locked for 15 minutes after 5 wrong passwords since its last successful sign-in; one device (IP
// address) is locked for 15 minutes after 20 failed sign-ins on any accounts. Returns the seconds left (0 = not locked).
const LOGIN_MAX_ACCOUNT_FAILURES = 5;
const LOGIN_MAX_IP_FAILURES = 20;
const LOGIN_LOCK_SECONDS = 900;

function login_lock_seconds(PDO $connection, ?int $user_id): int
{
    $window = "created_at > DATE_SUB(NOW(), INTERVAL " . LOGIN_LOCK_SECONDS . " SECOND)";
    $until = 0;
    try {
        if ($user_id !== null) {
            $statement = $connection->prepare("SELECT COUNT(*) AS n, UNIX_TIMESTAMP(MAX(created_at)) AS last FROM audit_logs WHERE action = 'auth_login_failed' AND user_id = :u AND $window AND created_at > COALESCE((SELECT MAX(s.created_at) FROM audit_logs s WHERE s.action = 'auth_login' AND s.user_id = :u2), '1970-01-01')");
            $statement->execute(['u' => $user_id, 'u2' => $user_id]);
            $row = $statement->fetch();
            if ((int) $row['n'] >= LOGIN_MAX_ACCOUNT_FAILURES) $until = max($until, (int) $row['last'] + LOGIN_LOCK_SECONDS);
        }
        $ip = mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        if ($ip !== '') {
            $statement = $connection->prepare("SELECT COUNT(*) AS n, UNIX_TIMESTAMP(MAX(created_at)) AS last FROM audit_logs WHERE action = 'auth_login_failed' AND ip_address = :ip AND $window");
            $statement->execute(['ip' => $ip]);
            $row = $statement->fetch();
            if ((int) $row['n'] >= LOGIN_MAX_IP_FAILURES) $until = max($until, (int) $row['last'] + LOGIN_LOCK_SECONDS);
        }
        $now = (int) $connection->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
    } catch (PDOException) {
        return 0;   // logging problems must never lock everyone out
    }
    return max(0, $until - $now);
}

function security_log(string $action, ?int $user_id = null, array $details = [], string $entity_type = 'security', ?int $entity_id = null): void
{
    try {
        $statement = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, details) VALUES (:user_id, :action, :type, :entity_id, :ip, :agent, :details)');
        $statement->execute([
            'user_id' => $user_id,
            'action' => mb_substr($action, 0, 100),
            'type' => $entity_type,
            'entity_id' => $entity_id,
            'ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            'agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null,
            'details' => $details === [] ? '{}' : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
        ]);
    } catch (Throwable $exception) {
        error_log('SJQIBMS security log failed: ' . $exception->getMessage());
    }
}

// The page that was requested (path only; query strings may contain search terms and are not stored).
function security_log_page(): string
{
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '')), PHP_URL_PATH);
    return mb_substr(basename($path) ?: $path, 0, 120);
}

// Every refused page request (HTTP 403) is recorded once, whichever page refused it.
function security_log_register_denied_hook(): void
{
    static $registered = false;
    if ($registered) return;
    $registered = true;
    register_shutdown_function(static function (): void {
        if (http_response_code() !== 403) return;
        $user = $_SESSION['user'] ?? null;
        security_log('access_denied', isset($user['id']) ? (int) $user['id'] : null, ['page' => security_log_page(), 'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), 'role' => (string) ($user['role'] ?? 'not signed in')]);
    });
}
