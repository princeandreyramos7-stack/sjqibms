<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_auth();

// Relief is now recorded in Relief & Assistance (assistance.php), linked to the incident. This old Disaster Management
// page only forwards old links and bookmarks; nothing is read or written here.
redirect('assistance.php');
