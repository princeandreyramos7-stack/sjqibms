<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/health_programs.php';
$connection = db();
health_programs_require($connection);

// Archive or restore a Maternal, Immunization, Nutrition or Chronic Care record (POST only, CSRF, Purok scope).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
$type = (string) ($_POST['type'] ?? '');
$action = (string) ($_POST['action'] ?? '');
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$tables = health_program_tables();
$back = health_program_back((string) ($_POST['back'] ?? ''), 'health.php');
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) { flash('health_error', 'Your session expired. Please try again.'); redirect($back); }
if (!isset($tables[$type]) || !in_array($action, ['archive', 'restore'], true) || !$id) { http_response_code(400); exit('Invalid request.'); }
[$table, $prefix, $label] = $tables[$type];
$statement = $connection->prepare("SELECT t.id, t.archived_at, r.purok FROM $table t INNER JOIN residents r ON r.id = t.resident_id WHERE t.id = :id");
$statement->execute(['id' => $id]);
$row = $statement->fetch();
if (!$row || !residents_in_scope($connection, $row['purok'])) { http_response_code(404); exit('Record not found.'); }
if (($action === 'archive') === ($row['archived_at'] !== null)) { flash('health_error', "This $label was already " . ($action === 'archive' ? 'archived' : 'restored') . '.'); redirect($back); }
$connection->prepare("UPDATE $table SET archived_at = " . ($action === 'archive' ? 'NOW()' : 'NULL') . ', archived_by = ' . ($action === 'archive' ? ':user' : 'NULL') . ' WHERE id = :id')->execute(['id' => $id] + ($action === 'archive' ? ['user' => current_user()['id']] : []));
health_program_audit($connection, $id, $prefix . '_' . ($action === 'archive' ? 'archived' : 'restored'));
flash('health_success', "The $label was " . ($action === 'archive' ? 'archived' : 'restored') . '.');
redirect($back);
