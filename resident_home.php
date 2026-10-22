<?php
declare(strict_types=1);

// Residents' landing page: the same page as index.php, opened from "Resident Portal" in the website's Login menu.
// Its Login menu offers only the Resident Portal; the Officials Portal (staff) is not shown here.
const LANDING_RESIDENT_ONLY = true;
require __DIR__ . '/index.php';
