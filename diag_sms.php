<?php
// TEMPORARY diagnostic for SMS not sending in production. Open it as
//   diag_sms.php?key=41c69ee3311f2e568269f3b3&to=09XXXXXXXXX
// It shows the SMS config in effect and fires one real test message through Semaphore,
// printing the raw HTTP status and response body so the real failure reason is visible.
// DELETE THIS FILE RIGHT AFTER USE — it can send real SMS and its key is in this file.
declare(strict_types=1);

if (($_GET['key'] ?? '') !== '41c69ee3311f2e568269f3b3') {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/sms.php';

echo "PHP version: " . PHP_VERSION . "\n";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'On' : 'Off (file_get_contents to https:// will fail)') . "\n";
echo "curl extension loaded: " . (extension_loaded('curl') ? 'yes' : 'no') . "\n";
echo "openssl extension loaded: " . (extension_loaded('openssl') ? 'yes' : 'no (https:// wrapper needs this)') . "\n";
echo "\n";
echo "APP_ENV: " . (defined('APP_ENV') ? APP_ENV : '(not defined)') . "\n";
echo "SMS_TEST_MODE: " . (SMS_TEST_MODE ? 'true (no real SMS is sent in this mode)' : 'false (real sending is attempted)') . "\n";
echo "SEMAPHORE_API_KEY length: " . strlen(SEMAPHORE_API_KEY) . (SEMAPHORE_API_KEY === '' ? ' (EMPTY — this alone would explain it)' : '') . "\n";
echo "SEMAPHORE_SENDER_NAME: " . SEMAPHORE_SENDER_NAME . "\n";
echo "\n";

$to = (string) ($_GET['to'] ?? '');
if ($to === '') {
    echo "Pass &to=09XXXXXXXXX in the URL to actually fire a test SMS.\n";
    exit;
}

$number = sms_normalise_number($to);
if ($number === null) {
    echo "Could not parse '$to' as a PH mobile number. Use 09XXXXXXXXX, 9XXXXXXXXX, or 639XXXXXXXXX.\n";
    exit;
}

echo "Normalised number: $number\n";
echo "Sending a real test message via Semaphore directly (bypassing SMS_TEST_MODE)...\n\n";

// Call the Semaphore API directly here (not through sms_gateway_send) so this test always
// actually hits the network, regardless of SMS_TEST_MODE, and so we can show the raw response.
$payload = http_build_query([
    'apikey'     => SEMAPHORE_API_KEY,
    'number'     => $number,
    'message'    => 'SJQIBMS diagnostic test message. If you received this, Semaphore sending works.',
    'sendername' => SEMAPHORE_SENDER_NAME,
]);

$context = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($payload) . "\r\n",
        'content'       => $payload,
        'timeout'       => 15,
        'ignore_errors' => true,
    ],
]);

$response = @file_get_contents('https://api.semaphore.co/api/v4/messages', false, $context);

echo "HTTP response headers:\n";
foreach (($http_response_header ?? []) as $line) {
    echo "  $line\n";
}
echo "\nResponse body:\n";
echo $response === false ? "(request failed outright — no response; see error_get_last below)\n" : $response . "\n";

if ($response === false) {
    $err = error_get_last();
    echo "\nLast PHP error: " . ($err['message'] ?? '(none)') . "\n";
}
