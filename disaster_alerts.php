<?php
declare(strict_types=1);
// Disaster alerts were removed from Disaster Management (barangay announcements are used instead). Existing alerts
// stay in the database and can still be opened from old notifications (disaster_alert_view.php).
require_once __DIR__ . '/includes/disaster.php';
disaster_require_view();
redirect('disaster.php');