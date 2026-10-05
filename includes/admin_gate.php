<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// Admin Access Gate: an access code required before EVERY staff login page is shown.
// A gate pass is issued for one portal and is used up when that portal's login page opens; the page then carries a
// one-time login token for its form. Refreshing, going back, or opening another portal therefore asks for the code again.
// Failures are limited per session and per IP address (the IP counter lives in the system temp directory,
// outside the web root, so clearing cookies does not reset it).

function admin_gate_grant(string $portal): void
{
    session_regenerate_id(true);
    $_SESSION['admin_gate_pass'] = ['portal' => $portal, 'until' => time() + ADMIN_GATE_PASS_SECONDS];
}

// Uses up the gate pass for $portal; true only if a valid, unexpired pass for that portal existed.
function admin_gate_consume(string $portal): bool
{
    $pass = $_SESSION['admin_gate_pass'] ?? null;
    unset($_SESSION['admin_gate_pass']);
    return is_array($pass) && ($pass['portal'] ?? null) === $portal && (int) ($pass['until'] ?? 0) > time();
}

// One-time token that ties a login form submission to the gate-verified page view.
function portal_login_token_issue(string $portal): string
{
    $token = bin2hex(random_bytes(16));
    $_SESSION['portal_login'] = ['portal' => $portal, 'token' => $token, 'until' => time() + ADMIN_GATE_PASS_SECONDS];
    return $token;
}

function portal_login_token_valid(string $portal, string $token): bool
{
    $login = $_SESSION['portal_login'] ?? null;
    return is_array($login) && ($login['portal'] ?? null) === $portal && (int) ($login['until'] ?? 0) > time()
        && is_string($login['token'] ?? null) && $token !== '' && hash_equals($login['token'], $token);
}

function admin_gate_clear(): void
{
    unset($_SESSION['admin_gate_pass'], $_SESSION['portal_login']);
}

function admin_gate_ip_file(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'sjqibms_gate_' . hash('sha256', $ip) . '.json';
}

// Reads and optionally updates the per-IP record under an exclusive lock.
function admin_gate_ip_record(?callable $update = null): array
{
    $record = ['failures' => 0, 'locked_until' => 0];
    $handle = @fopen(admin_gate_ip_file(), 'c+');
    if ($handle === false) return $record;
    try {
        flock($handle, LOCK_EX);
        $data = json_decode((string) stream_get_contents($handle), true);
        if (is_array($data)) $record = ['failures' => (int) ($data['failures'] ?? 0), 'locked_until' => (int) ($data['locked_until'] ?? 0)];
        if ($update !== null) {
            $record = $update($record);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($record));
            fflush($handle);
        }
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
    return $record;
}

// Seconds remaining on the longer of the session and IP lockouts (0 when not locked).
function admin_gate_lock_remaining(): int
{
    $session_until = (int) ($_SESSION['admin_gate_locked_until'] ?? 0);
    $ip_until = admin_gate_ip_record()['locked_until'];
    return max(0, max($session_until, $ip_until) - time());
}

function admin_gate_attempts_left(): int
{
    $session_failures = (int) ($_SESSION['admin_gate_failures'] ?? 0);
    $ip_failures = admin_gate_ip_record()['failures'];
    return max(0, ADMIN_GATE_MAX_ATTEMPTS - max($session_failures, $ip_failures));
}

function admin_gate_record_failure(): void
{
    $now = time();
    $failures = (int) ($_SESSION['admin_gate_failures'] ?? 0) + 1;
    $_SESSION['admin_gate_failures'] = $failures;
    if ($failures >= ADMIN_GATE_MAX_ATTEMPTS) {
        $_SESSION['admin_gate_locked_until'] = $now + ADMIN_GATE_LOCKOUT_SECONDS;
        $_SESSION['admin_gate_failures'] = 0;
    }
    admin_gate_ip_record(static function (array $record) use ($now): array {
        if ($record['locked_until'] <= $now && $record['locked_until'] !== 0) $record = ['failures' => 0, 'locked_until' => 0];
        $record['failures']++;
        if ($record['failures'] >= ADMIN_GATE_MAX_ATTEMPTS) $record = ['failures' => 0, 'locked_until' => $now + ADMIN_GATE_LOCKOUT_SECONDS];
        return $record;
    });
}

function admin_gate_reset_failures(): void
{
    unset($_SESSION['admin_gate_failures'], $_SESSION['admin_gate_locked_until']);
    admin_gate_ip_record(static fn (array $record): array => ['failures' => 0, 'locked_until' => 0]);
}

function admin_gate_verify(string $code): bool
{
    // password_verify is constant-time; the code format is checked first so malformed input never reaches it.
    return preg_match('/^\d{6}$/', $code) === 1 && password_verify($code, ADMIN_GATE_CODE_HASH);
}
