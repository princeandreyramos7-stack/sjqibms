<?php
declare(strict_types=1);
// Superseded by the Reports module (report.php); kept so existing links and bookmarks keep working.
require_once __DIR__ . '/includes/reports.php';
require_auth();
if (!reports_can_generate('complaints')) { http_response_code(404); exit('Report not found.'); }
redirect('report.php?category=complaints');
