<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/households.php';
households_require_manage();
$connection = db();

// Add / Edit Household helper (GET, JSON): the next free household number for a Purok (?purok=3 → "P3-0012") and, with
// ?number=, whether that number is already used by another household (?exclude= the household being edited).
// Read-only; the form is checked again on the server when it is saved.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$purok = (string) ($_GET['purok'] ?? '');
$number = residents_collapse((string) ($_GET['number'] ?? ''));
$exclude = filter_var($_GET['exclude'] ?? null, FILTER_VALIDATE_INT) ?: null;
$result = ['next' => households_next_number($connection, $purok)];
if ($number !== '') $result['taken'] = households_number_taken($connection, mb_substr($number, 0, 50), $exclude);
echo json_encode($result);
