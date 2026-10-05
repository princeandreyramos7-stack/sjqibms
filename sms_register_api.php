<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/sms_registration.php';

// JSON endpoint of the public Resident Portal sign-up (register.php). POST only, with the page's CSRF token.
// action = check (one step, nothing saved) | head_lookup (exact household-head match, nothing listed) | send_otp |
// verify | register. Every rule is checked here; the page only
// shows the answers.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$respond = static function (array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
};
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $respond(['ok' => false, 'message' => 'Your session expired. Please reload the page and try again.', 'reload' => true], 419);
$connection = db();
if (!sms_reg_ready($connection)) $respond(['ok' => false, 'message' => 'Online registration is not available yet. Please register at the Barangay Hall.'], 503);
try {
    $result = match ((string) ($_POST['action'] ?? '')) {
        'check' => sms_reg_check($connection, $_POST),
        'head_lookup' => sms_reg_head_lookup($connection, (string) ($_POST['household_head_name'] ?? '')),
        'send_otp' => sms_reg_request_otp($connection, $_POST),
        'verify' => sms_reg_verify($connection, (string) ($_POST['code'] ?? '')),
        'register' => sms_reg_register($connection, $_POST),
        default => ['ok' => false, 'message' => 'Unknown action.'],
    };
} catch (Throwable) {
    $result = ['ok' => false, 'message' => 'Something went wrong. Please try again, or register at the Barangay Hall.'];
}
$respond($result);
