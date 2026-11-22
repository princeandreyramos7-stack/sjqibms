<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$role = is_authenticated() ? (string) current_user()['role'] : '';
if ($role !== '') security_log('auth_logout', (int) current_user()['id'], ['role' => $role]);
logout_user();
// Residents go back to the residents' copy of the website (resident_home.php), whose Login menu offers only the Resident
// Portal, so they never see the Officials Portal. Staff go back to the public website, where the Officials Portal asks
// for the access code again.
header('Location: ' . ($role === 'resident' ? 'resident_home.php' : 'index.php'));
exit;
