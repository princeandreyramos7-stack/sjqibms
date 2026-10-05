<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_authenticated()) security_log('auth_logout', (int) current_user()['id'], ['role' => (string) current_user()['role']]);
logout_user();
// Back to the public website, where the Login menu opens each portal (staff portals ask for the access gate again).
header('Location: index.php');
exit;
