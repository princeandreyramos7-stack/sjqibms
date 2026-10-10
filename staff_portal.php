<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_gate.php';
require_once __DIR__ . '/includes/navigation.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/security_log.php';

// Officials Portal: the staff login list, shown only after the access code was entered on admin_gate.php (the
// "Officials Portal" entry of the website's Login menu). The pass lasts ADMIN_GATE_PASS_SECONDS. Choosing an office
// issues that office's one-portal gate pass and opens its login page.
if (is_authenticated()) {
    redirect('dashboard.php');
}
if (!admin_gate_hub_valid()) {
    redirect('admin_gate.php');
}
$portals = array_diff_key(staff_portals(), ['punong_barangay' => true]);   // Punong Barangay is not offered
$chosen = (string) ($_GET['portal'] ?? '');
if ($chosen !== '') {
    if (!isset($portals[$chosen])) redirect('staff_portal.php');
    admin_gate_grant($chosen);
    redirect('login.php?portal=' . $chosen);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Officials Portal | Barangay San Jose</title>
    <link rel="icon" href="assets/img/barangay-san-jose-logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/landing.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/landing.css') ?>" rel="stylesheet">
</head>
<body class="landing gate">
<main class="gate-page">
    <a class="gate-back" href="index.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>Back to website</a>
    <section class="gate-card staff-hub-card" aria-labelledby="hub-title">
        <div class="gate-emblem">
            <img src="assets/img/barangay-san-jose-logo.jpg" alt="Barangay San Jose seal">
        </div>
        <span class="gate-eyebrow"><span class="landing-dot"></span>Access verified</span>
        <h1 id="hub-title">Officials Portal</h1>
        <p class="gate-sub">Choose which official you are to sign in.</p>
        <nav class="staff-hub-list" aria-label="Staff login">
            <?php foreach ($portals as $key => $portal): ?>
                <a class="landing-login-item" href="staff_portal.php?portal=<?= e($key) ?>">
                    <span class="landing-login-icon"><?= icon_svg($portal['icon']) ?></span>
                    <span><strong><?= e($portal['title']) ?></strong><small><?= e($portal['short']) ?> login</small></span>
                    <span class="landing-login-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <p class="gate-foot-note">This page expires 10 minutes after the access code was entered.</p>
    </section>
    <p class="gate-foot">Authorized barangay personnel only · © <?= e(date('Y')) ?> Barangay San Jose</p>
</main>
</body>
</html>
