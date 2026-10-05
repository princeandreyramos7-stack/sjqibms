<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_auth();
require_once __DIR__ . '/includes/dashboard_stats.php';
require_once __DIR__ . '/includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$result = dashboard_statistics();
http_response_code($result['errors'] === [] ? 200 : 503);
echo json_encode([
    'statistics' => $result['statistics'],
    'demographics' => $result['demographics'],
    'announcements' => $result['announcements'],
    'activities' => $result['activities'],
    'notifications' => [
        'count' => unread_notifications_count(),
        'items' => notifications_for_current_user(),
    ],
    'errors' => $result['errors'],
    'updated_at' => date(DATE_ATOM),
], JSON_THROW_ON_ERROR);
