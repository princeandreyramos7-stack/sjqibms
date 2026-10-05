<?php
// TEMPORARY diagnostic for the HTTP 500 on register.php in production. Open it as
//   diag_register.php?key=c33406cbb9768f0aa6578a9d
// It loads register.php and shows only the error that stops it. DELETE THIS FILE right after use.
if (($_GET['key'] ?? '') !== 'c33406cbb9768f0aa6578a9d') { http_response_code(404); exit; }

header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);
echo 'PHP version: ' . PHP_VERSION . "\n";
echo 'Server: ' . php_uname('s') . "\n\n";

$report = static function (string $type, string $message, string $file, int $line): void {
    while (ob_get_level() > 0) ob_end_clean();
    echo "$type: $message\nFile: " . str_replace(__DIR__, '', $file) . "\nLine: $line\n";
};
register_shutdown_function(static function () use ($report): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $report('Fatal error', $error['message'], $error['file'], $error['line']);
    }
});

ob_start();
try {
    require __DIR__ . '/register.php';
    ob_end_clean();
    echo "register.php ran with no error.\n";
} catch (Throwable $exception) {
    $report(get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine());
}
