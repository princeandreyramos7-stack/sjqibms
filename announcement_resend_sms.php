<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/announcements.php';
require_once __DIR__ . '/includes/sms.php';

// Only managers (super_admin, secretary) may resend SMS
announcements_require_manage();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid request.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Invalid request.');
}

$connection = db();
$record = $connection->prepare(
    'SELECT id, title, body, audience, target_purok, status FROM announcements WHERE id = :id LIMIT 1'
);
$record->execute(['id' => $id]);
$announcement = $record->fetch();

// Must exist and be published or archived (not a draft)
if (!$announcement || $announcement['status'] === 'draft') {
    flash('announcement_error', 'SMS could not be sent: announcement not found or is still a draft.');
    redirect('announcements.php');
}

// Fire SMS — fire-and-forget, never throws
sms_send_announcement($connection, $announcement);

flash('announcement_success', 'SMS notification resent successfully.');
redirect('announcements.php?tab=' . ($announcement['status'] === 'archived' ? 'archived' : 'published'));
