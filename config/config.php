<?php
declare(strict_types=1);

const APP_NAME = 'SJQIBMS';
const APP_FULL_NAME = 'Barangay San Jose Management System';
const APP_TIMEZONE = 'Asia/Manila';

// Passwords and keys for this server live in config/secrets.php, which is never uploaded over another server's copy:
// the database login (DB_HOST, DB_NAME, DB_USER, DB_PASS) and the Semaphore API key. The defaults below are the live
// server's database login; a DB_* value set in secrets.php (for example on XAMPP) takes precedence over them.
if (is_file(__DIR__ . '/secrets.php')) require __DIR__ . '/secrets.php';
defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_NAME') || define('DB_NAME', 'u988863428_sjqibms_db');
defined('DB_USER') || define('DB_USER', 'u988863428_sjqibms_user');
defined('DB_PASS') || define('DB_PASS', 'Sjqibms_pass1');

// Admin Access Gate (admin_gate.php): only a bcrypt hash of the access code is stored, never the code itself.
// To change the code, run: php -r "echo password_hash('NEWCODE', PASSWORD_DEFAULT);" and paste the result here.
const ADMIN_GATE_CODE_HASH = '$2y$10$fjN0eFbQiKFTl.amUC5bduNkL30do4avihS.x6aEYl6VPXzfWbKSm';
const ADMIN_GATE_MAX_ATTEMPTS = 5;        // failed codes before a lockout
const ADMIN_GATE_LOCKOUT_SECONDS = 900;   // 15-minute lockout
const ADMIN_GATE_PASS_SECONDS = 600;      // a passed gate is valid for 10 minutes

// ── Semaphore SMS ─────────────────────────────────────────────────────────────
// Regenerate the API key at https://semaphore.co if it was ever exposed.
// The API key is kept in config/secrets.php (loaded above; not in this file, so it is never shared with the code).
defined('SEMAPHORE_API_KEY') || define('SEMAPHORE_API_KEY', '');
const SEMAPHORE_SENDER_NAME = 'LandCert';

// Set this to true only when the application is served over HTTPS.
const SESSION_SECURE = false;

// Optional machine-specific settings (config/local.php). Only a developer with file access can create or change it; nothing a
// browser sends can switch these settings on. Without that file the application runs as production with test modes off.
if (is_file(__DIR__ . '/local.php')) require __DIR__ . '/local.php';
defined('APP_ENV') || define('APP_ENV', 'production');
// SMS TEST MODE (public SMS registration and announcement texts): true = no text is sent; the OTP is shown on the
// screen so the whole flow can be tried at no cost. Turn it off by adding  define('SMS_TEST_MODE', false);  to
// config/local.php once the SMS provider (Semaphore, includes/sms.php) is ready.
defined('SMS_TEST_MODE') || define('SMS_TEST_MODE', true);
// Cloudflare Turnstile (CAPTCHA) for sms_register.php. Put the barangay's own keys in config/local.php
// (TURNSTILE_SITE_KEY / TURNSTILE_SECRET_KEY). The defaults are Cloudflare's official TEST keys, which always pass.
defined('TURNSTILE_SITE_KEY') || define('TURNSTILE_SITE_KEY', '1x00000000000000000000AA');
defined('TURNSTILE_SECRET_KEY') || define('TURNSTILE_SECRET_KEY', '1x0000000000000000000000000000000AA');
// Development-only Documents "test release" (see includes/documents.php documents_test_release_enabled()).
defined('DOCUMENTS_TEST_RELEASE') || define('DOCUMENTS_TEST_RELEASE', false);
defined('DOCUMENTS_TEST_RELEASE_USER_IDS') || define('DOCUMENTS_TEST_RELEASE_USER_IDS', []);

date_default_timezone_set(APP_TIMEZONE);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('sjqibms_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => SESSION_SECURE,
        'samesite' => 'Lax',
    ]);
    session_start();
}
