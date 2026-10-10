<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/site.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/announcements.php';

// Public landing page. It shows only aggregate counts and announcements published for the public audience;
// every records page still requires sign-in through login.php.

// ── Editable page content ────────────────────────────────────────────────────
// Replace the mission, vision and values with the barangay's official statements when available.
$about = [
    ['icon' => 'flag', 'tone' => 'green', 'title' => 'Our Mission', 'text' => 'Barangay San Jose promotes sustainable agricultural development, responsive governance, quality public services, and active cooperation among its leaders and constituents through building a progressive, peaceful, and resilient community.'],
    ['icon' => 'eye', 'tone' => 'mint', 'title' => 'Our Vision', 'text' => 'Barangay San Jose, a leading rice and corn-producing community with a progressive economy, empowered citizens, safe and healthy environment, quality infrastructure, under good governance.'],
    ['icon' => 'star', 'tone' => 'gold', 'title' => 'Our Core Values', 'text' => 'Integrity, transparency, accountability, service excellence, and genuine care for the people of San Jose.'],
];
$services = [
    ['icon' => 'file', 'title' => 'Barangay Clearance & Certificates', 'text' => 'Barangay clearance, residency, and indigency certificates and other documents for employment, business, and government transactions.'],
    ['icon' => 'heart', 'title' => 'Health & Medical Assistance', 'text' => 'Basic health support, referrals, vaccination coordination, and community health programs with barangay health workers.'],
    ['icon' => 'shield', 'title' => 'Peace and Order', 'text' => 'Community safety, mediation of disputes through the Lupon, and coordination with local law enforcement.'],
    ['icon' => 'users', 'title' => 'Resident Records', 'text' => 'Accurate resident and household profiling that helps the barangay plan services and respond to community needs.'],
    ['icon' => 'megaphone', 'title' => 'Community Announcements', 'text' => 'Timely advisories, events, and public information from the barangay to every household.'],
    ['icon' => 'alert', 'title' => 'Disaster Preparedness', 'text' => 'Risk reduction, early warning coordination, and emergency response support for residents and families.'],
];
// Highlights use only real barangay imagery: the community center photo and the official seal.
$highlights = [
    ['image' => 'assets/img/san-jose-community-center.jpg', 'tag' => 'Community', 'title' => 'San Jose Community Center', 'text' => 'The heart of barangay gatherings — assemblies, programs, and public services for the residents of San Jose.'],
    ['seal' => true, 'tag' => 'Heritage', 'title' => 'Rooted in Agriculture', 'text' => 'Corn and rice, featured on the barangay seal, reflect the farming heritage that sustains many San Jose families.'],
    ['seal' => true, 'tag' => 'Since ' . BARANGAY_ESTABLISHED, 'title' => 'Serving the Community Since ' . BARANGAY_ESTABLISHED, 'text' => 'Decades of public service in Quirino, Isabela, built on the cooperation and resilience of its people.'],
];

// ── Live, public-safe data ───────────────────────────────────────────────────
$stats = ['residents' => null, 'households' => null, 'puroks' => null];
$news = [];
try {
    $connection = db();
    $stats['residents'] = (int) $connection->query("SELECT COUNT(*) FROM residents WHERE status = 'active'")->fetchColumn();
    // Same occupied-household definition as the Dashboard: a current primary membership of an Active resident.
    $stats['households'] = (int) $connection->query("SELECT COUNT(DISTINCT rh.household_id) FROM resident_households rh INNER JOIN residents r ON r.id = rh.resident_id WHERE rh.is_primary = 1 AND rh.left_at IS NULL AND r.status = 'active'")->fetchColumn();
    $stats['puroks'] = (int) $connection->query("SELECT COUNT(DISTINCT purok) FROM residents WHERE status = 'active' AND purok <> ''")->fetchColumn();
    $news = $connection->query("SELECT title, body, published_at FROM announcements WHERE status = 'published' AND audience = 'public' ORDER BY published_at DESC, id DESC LIMIT 3")->fetchAll();
} catch (PDOException) {
    // The page still renders; statistics show a dash and News shows its empty state.
}

$signed_in = is_authenticated();
$year = date('Y');

function landing_icon(string $name): string
{
    $paths = [
        'flag' => '<path d="M4 22V4"/><path d="M4 4h12l-2 4 2 4H4"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'star' => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
        'heart' => '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 12 5a5.5 5.5 0 0 0-10 3.5c0 2.3 1.5 4 3 5.5l7 7z"/><path d="M3.5 12h4l2-3 3 6 2-3h6"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-6h6v6"/>',
        'map' => '<path d="M12 21s-7-5.6-7-11a7 7 0 0 1 14 0c0 5.4-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'megaphone' => '<path d="m3 11 18-5v12L3 14v-3zM11.6 16.4 13 21H8l-1.7-5.1"/>',
        'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        'map-pin' => '<path d="M3 6v15l6-3 6 3 6-3V3l-6 3-6-3z"/><path d="M9 3v15M15 6v15"/>',
        'login' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5M15 12H3"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'wallet' => '<path d="M4 7V5a2 2 0 0 1 2-2h12v4"/><path d="M4 7h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2z"/><path d="M16 13h4"/>',
        'badge' => '<circle cx="12" cy="8" r="5"/><path d="M8.2 12.5 7 22l5-3 5 3-1.2-9.5"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? '') . '</svg>';
}

$stat_value = static fn (?int $value): string => $value === null ? '—' : number_format($value);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Official portal of <?= e(BARANGAY_NAME) ?>, <?= e(BARANGAY_LOCATION) ?>: services, announcements, and community information.">
    <title><?= e(BARANGAY_NAME) ?> | <?= e(BARANGAY_LOCATION) ?></title>
    <link rel="icon" href="assets/img/barangay-san-jose-logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/landing.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/landing.css') ?>" rel="stylesheet">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body class="landing">
<a class="landing-skip" href="#main">Skip to content</a>

<header class="landing-nav" data-landing-nav>
    <div class="landing-container landing-nav-inner">
        <a class="landing-brand" href="#home" aria-label="<?= e(BARANGAY_NAME) ?> home">
            <img src="assets/img/barangay-san-jose-logo.jpg" alt="" width="46" height="46">
            <span><strong><?= e(BARANGAY_NAME) ?></strong><small><?= e(BARANGAY_LOCATION) ?></small></span>
        </a>
        <nav class="landing-links" id="landing-links" aria-label="Main navigation" data-landing-links>
            <a href="#home" data-nav-section>Home</a>
            <a href="#about" data-nav-section>About</a>
            <a href="#services" data-nav-section>Services</a>
            <a href="#highlights" data-nav-section>Highlights</a>
            <a href="#news" data-nav-section>News</a>
            <a href="#contact" data-nav-section>Contact</a>
        </nav>
        <div class="landing-nav-actions">
            <?php if ($signed_in): ?>
                <a class="landing-login-btn" href="dashboard.php" aria-label="Go to Dashboard"><?= landing_icon('grid') ?><span>Dashboard</span></a>
            <?php else: ?>
                <div class="landing-login" data-login-menu>
                    <button class="landing-login-btn" type="button" aria-label="Login options" aria-haspopup="true" aria-expanded="false" aria-controls="login-menu" data-login-toggle><?= landing_icon('login') ?><span>Login</span><span class="landing-caret"><?= landing_icon('chevron-down') ?></span></button>
                    <div class="landing-login-menu" id="login-menu" role="menu" hidden data-login-panel>
                        <p class="landing-login-label">For residents</p>
                        <a class="landing-login-item" href="login.php?portal=resident" role="menuitem">
                            <span class="landing-login-icon is-resident"><?= landing_icon('users') ?></span>
                            <span><strong>Resident Portal</strong><small>Request documents &amp; track requests</small></span>
                            <span class="landing-login-arrow"><?= landing_icon('arrow-right') ?></span>
                        </a>
                        <p class="landing-login-label">Barangay staff <span class="landing-login-lock"><?= landing_icon('lock') ?>Access code required</span></p>
                        <?php // One entry for every staff office: the access code first (admin_gate.php), then the office list (staff_portal.php). ?>
                        <a class="landing-login-item" href="admin_gate.php" role="menuitem">
                            <span class="landing-login-icon"><?= landing_icon('shield') ?></span>
                            <span><strong>Officials Portal</strong><small>Secretary, Treasurer, Officials, Health Workers &amp; Admin</small></span>
                            <span class="landing-login-arrow"><?= landing_icon('arrow-right') ?></span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            <button class="landing-menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="landing-links" data-menu-toggle><span class="icon-open"><?= landing_icon('menu') ?></span><span class="icon-close"><?= landing_icon('close') ?></span></button>
        </div>
    </div>
</header>

<main id="main">
    <!-- Hero ──────────────────────────────────────────────────────────── -->
    <section class="landing-hero" id="home">
        <div class="landing-container landing-hero-inner">
            <span class="landing-hero-eyebrow"><span class="landing-dot"></span>Official Barangay Portal</span>
            <h1><?= e(BARANGAY_NAME) ?></h1>
            <p class="landing-hero-place"><?= e(BARANGAY_LOCATION) ?> · Serving the community since <?= e(BARANGAY_ESTABLISHED) ?></p>
            <p class="landing-hero-copy">Accessible public service, reliable community information, and a barangay that works hand in hand with its people.</p>
            <div class="landing-hero-actions">
                <a class="landing-btn landing-btn-gold" href="#services">Explore Services <?= landing_icon('arrow-right') ?></a>
                <a class="landing-btn landing-btn-ghost" href="#news">Latest Updates</a>            </div>
        </div>
        <div class="landing-container">
            <dl class="landing-hero-facts">
                <div><dt><?= landing_icon('clock') ?>Office Hours</dt><dd><?= e(BARANGAY_OFFICE_HOURS) ?></dd></div>
                <div><dt><?= landing_icon('map') ?>Location</dt><dd><?= e(BARANGAY_OFFICE_ADDRESS) ?></dd></div>
                <div><dt><?= landing_icon('star') ?>Established</dt><dd><?= e(BARANGAY_ESTABLISHED) ?></dd></div>
            </dl>
        </div>
    </section>

    <!-- About ─────────────────────────────────────────────────────────── -->
    <section class="landing-section" id="about">
        <div class="landing-container">
            <header class="landing-heading" data-reveal>
                <span class="landing-pill">About Us</span>
                <h2>Serving with Integrity &amp; Excellence</h2>
                <p>Our barangay is committed to accessible public service, community welfare, and a safe, organized, and progressive environment for all residents.</p>
            </header>
            <div class="landing-grid landing-grid-3">
                <?php foreach ($about as $item): ?>
                    <article class="landing-card landing-card-center" data-reveal>
                        <span class="landing-icon-tile tone-<?= e($item['tone']) ?>"><?= landing_icon($item['icon']) ?></span>
                        <h3><?= e($item['title']) ?></h3>
                        <p><?= e($item['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Services ──────────────────────────────────────────────────────── -->
    <section class="landing-section landing-section-tint" id="services">
        <div class="landing-container">
            <header class="landing-heading" data-reveal>
                <span class="landing-pill">Services</span>
                <h2>Essential Barangay Services</h2>
                <p>Public services that keep the community safe, support family welfare, and assist residents with their daily administrative and social needs.</p>
            </header>
            <div class="landing-grid landing-grid-3">
                <?php foreach ($services as $service): ?>
                    <article class="landing-card landing-service" data-reveal>
                        <span class="landing-icon-tile tone-solid"><?= landing_icon($service['icon']) ?></span>
                        <h3><?= e($service['title']) ?></h3>
                        <p><?= e($service['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Highlights ────────────────────────────────────────────────────── -->
    <section class="landing-section" id="highlights">
        <div class="landing-container">
            <header class="landing-heading" data-reveal>
                <span class="landing-pill">Community Highlights</span>
                <h2>Community Highlights</h2>
                <p>The places, heritage, and spirit that shape the progress of San Jose.</p>
            </header>
            <div class="landing-carousel" data-carousel data-reveal aria-roledescription="carousel" aria-label="Community highlights">
                <button class="landing-carousel-btn is-prev" type="button" aria-label="Previous highlight" data-carousel-prev><?= landing_icon('chevron-left') ?></button>
                <div class="landing-carousel-frame">
                    <div class="landing-carousel-track" data-carousel-track>
                        <?php foreach ($highlights as $index => $slide): ?>
                            <article class="landing-slide" role="group" aria-roledescription="slide" aria-label="<?= e(($index + 1) . ' of ' . count($highlights)) ?>" data-carousel-slide>
                                <?php if (!empty($slide['image'])): ?>
                                    <div class="landing-slide-media"><img src="<?= e($slide['image']) ?>" alt="<?= e($slide['title']) ?>" loading="lazy"></div>
                                <?php else: ?>
                                    <div class="landing-slide-media landing-slide-seal"><img src="assets/img/barangay-san-jose-logo.jpg" alt="Official seal of <?= e(BARANGAY_NAME) ?>" loading="lazy"></div>
                                <?php endif; ?>
                                <div class="landing-slide-body">
                                    <span class="landing-chip"><?= e($slide['tag']) ?></span>
                                    <h3><?= e($slide['title']) ?></h3>
                                    <p><?= e($slide['text']) ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="landing-carousel-btn is-next" type="button" aria-label="Next highlight" data-carousel-next><?= landing_icon('chevron-right') ?></button>
                <div class="landing-carousel-dots" data-carousel-dots>
                    <?php foreach ($highlights as $index => $slide): ?><button type="button" aria-label="Show highlight <?= e((string) ($index + 1)) ?>" data-carousel-dot="<?= e((string) $index) ?>"></button><?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics ────────────────────────────────────────────────────── -->
    <section class="landing-section landing-section-tint" id="statistics">
        <div class="landing-container">
            <header class="landing-heading" data-reveal>
                <span class="landing-pill">Statistics</span>
                <h2>Community Overview</h2>
                <p>Key community figures drawn live from the barangay's official records.</p>
            </header>
            <div class="landing-grid landing-grid-3 landing-stats">
                <article class="landing-card landing-card-center landing-stat" data-reveal>
                    <span class="landing-icon-tile tone-solid"><?= landing_icon('users') ?></span>
                    <strong data-count="<?= e((string) ($stats['residents'] ?? '')) ?>"><?= e($stat_value($stats['residents'])) ?></strong>
                    <span>Active Residents</span>
                </article>
                <article class="landing-card landing-card-center landing-stat" data-reveal>
                    <span class="landing-icon-tile tone-solid"><?= landing_icon('home') ?></span>
                    <strong data-count="<?= e((string) ($stats['households'] ?? '')) ?>"><?= e($stat_value($stats['households'])) ?></strong>
                    <span>Occupied Households</span>
                </article>
                <article class="landing-card landing-card-center landing-stat" data-reveal>
                    <span class="landing-icon-tile tone-solid"><?= landing_icon('map-pin') ?></span>
                    <strong data-count="<?= e((string) ($stats['puroks'] ?? '')) ?>"><?= e($stat_value($stats['puroks'])) ?></strong>
                    <span>Puroks Represented</span>
                </article>
            </div>
        </div>
    </section>

    <!-- News ──────────────────────────────────────────────────────────── -->
    <section class="landing-section" id="news">
        <div class="landing-container">
            <header class="landing-heading" data-reveal>
                <span class="landing-pill">Announcements</span>
                <h2>News &amp; Updates</h2>
                <p>Latest advisories, events, and public announcements from the barangay.</p>
            </header>
            <div class="landing-card landing-sms-cta" data-reveal>
                <span class="landing-sms-icon"><?= landing_icon('megaphone') ?></span>
                <div><h3>Get announcements by text</h3><p>Magparehistro para makatanggap ng text tuwing may bagong anunsyo ang barangay.</p></div>
                <a class="landing-btn landing-btn-gold" href="register.php">Magparehistro <?= landing_icon('arrow-right') ?></a>
            </div>
            <?php if ($news === []): ?>
                <div class="landing-card landing-empty" data-reveal><?= landing_icon('megaphone') ?><p>No public announcements have been published yet. Please check back soon.</p></div>
            <?php else: ?>
                <div class="landing-grid landing-grid-news">
                    <?php foreach ($news as $item): ?>
                        <article class="landing-card landing-news" data-reveal>
                            <div class="landing-news-meta"><span class="landing-chip">Announcement</span><time datetime="<?= e((string) $item['published_at']) ?>"><?= e(date_create((string) $item['published_at'])?->format('M j, Y') ?? '') ?></time></div>
                            <h3><?= e($item['title']) ?></h3>
                            <p><?= e(announcements_preview((string) $item['body'])) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Contact ───────────────────────────────────────────────────────── -->
    <section class="landing-section landing-section-tint" id="contact">
        <div class="landing-container">
            <header class="landing-heading" data-reveal>
                <span class="landing-pill">Contact Us</span>
                <h2>Get in Touch With Us</h2>
                <p>Reach out to the barangay for inquiries, assistance, public services, and community concerns.</p>
            </header>
            <div class="landing-grid landing-grid-3">
                <article class="landing-card landing-card-center" data-reveal>
                    <span class="landing-icon-tile tone-solid"><?= landing_icon('map') ?></span>
                    <h3>Office Address</h3>
                    <p><?= e(BARANGAY_OFFICE_ADDRESS) ?></p>
                </article>
                <article class="landing-card landing-card-center" data-reveal>
                    <span class="landing-icon-tile tone-solid"><?= landing_icon('phone') ?></span>
                    <h3>Contact Number</h3>
                    <p><?= BARANGAY_CONTACT_NUMBER !== '' ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', BARANGAY_CONTACT_NUMBER)) . '">' . e(BARANGAY_CONTACT_NUMBER) . '</a>' : 'Available at the Barangay Hall' ?></p>
                </article>
                <article class="landing-card landing-card-center" data-reveal>
                    <span class="landing-icon-tile tone-solid"><?= landing_icon('mail') ?></span>
                    <h3>Email Address</h3>
                    <p><?= BARANGAY_EMAIL !== '' ? '<a href="mailto:' . e(BARANGAY_EMAIL) . '">' . e(BARANGAY_EMAIL) . '</a>' : 'Available at the Barangay Hall' ?></p>
                </article>
            </div>
            <div class="landing-hours" data-reveal>
                <span class="landing-hours-icon"><?= landing_icon('clock') ?></span>
                <div><h3>Office Hours</h3><p><?= e(BARANGAY_OFFICE_HOURS) ?></p></div>
            </div>
        </div>
    </section>
</main>

<footer class="landing-footer">
    <div class="landing-container landing-footer-grid">
        <div class="landing-footer-brand">
            <img src="assets/img/barangay-san-jose-logo.jpg" alt="" width="72" height="72">
            <strong><?= e(BARANGAY_NAME) ?></strong>
            <span><?= e(BARANGAY_LOCATION) ?> · Est. <?= e(BARANGAY_ESTABLISHED) ?></span>
            <p>Republic of the Philippines. Serving the community with integrity, transparency, and care.</p>
        </div>
        <div>
            <h4>Quick Links</h4>
            <ul>
                <li><a href="#home">Home</a></li>
                <li><a href="#about">About Us</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#news">News &amp; Updates</a></li>
                <li><a href="#contact">Contact Us</a></li>
            </ul>
        </div>
        <div>
            <h4>Barangay Office</h4>
            <ul class="landing-footer-contact">
                <li><?= landing_icon('map') ?><span><?= e(BARANGAY_OFFICE_ADDRESS) ?></span></li>
                <li><?= landing_icon('clock') ?><span><?= e(BARANGAY_OFFICE_HOURS) ?></span></li>
                <?php if (BARANGAY_CONTACT_NUMBER !== ''): ?><li><?= landing_icon('phone') ?><span><?= e(BARANGAY_CONTACT_NUMBER) ?></span></li><?php endif; ?>
                <?php if (BARANGAY_EMAIL !== ''): ?><li><?= landing_icon('mail') ?><span><?= e(BARANGAY_EMAIL) ?></span></li><?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="landing-container landing-footer-bottom">
        <span>© <?= e($year) ?> <?= e(BARANGAY_NAME) ?>. All rights reserved.</span>
        <span>Powered by <?= e(APP_NAME) ?></span>
    </div>
</footer>

<script src="assets/js/landing.js"></script>
</body>
</html>
