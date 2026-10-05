<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/disaster_prep.php';
require_auth();
if (!can_access_navigation('disaster_info')) { http_response_code(403); exit('Access denied.'); }
$connection = db();

// Resident Portal → Disaster Info (read-only): the hazards of the resident's own Purok first, the evacuation centers
// (no personal contact numbers) and the emergency hotlines (tap to call). Everything comes from Disaster Management;
// nothing is entered here. No names of other residents are shown.
$ready = disaster_prep_ready($connection);
$statement = $connection->prepare('SELECT r.purok FROM users u INNER JOIN residents r ON r.id = u.resident_id WHERE u.id = :id LIMIT 1');
$statement->execute(['id' => current_user()['id']]);
$my_purok = $statement->fetchColumn();
$my_purok = $my_purok === false ? null : (string) $my_purok;
$hazards = $ready ? $connection->query("SELECT h.hazard_type, h.hazard_other, h.risk_level, a.name AS area_name FROM drr_hazard_areas h INNER JOIN drr_areas a ON a.id = h.area_id WHERE h.archived_at IS NULL ORDER BY FIELD(h.risk_level, 'high', 'medium', 'low'), a.sort_order, a.name")->fetchAll() : [];
$mine = array_values(array_filter($hazards, static fn (array $h): bool => in_array(disaster_area_purok((string) $h['area_name']), ['*', $my_purok], true)));
$centers = $ready ? disaster_current_centers($connection) : [];
$hotlines = $ready ? disaster_current_hotlines($connection) : [];
$page_title = 'Disaster Info'; $active_page = 'disaster_info';
$page_styles = ['assets/css/disaster.css'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading"><div><h1>Disaster Info</h1><p>Hazards in your area, evacuation centers and emergency hotlines. Kept up to date by the BDRRMC.</p></div></div>
    <?php if (!$ready): ?>
        <div class="dashboard-status warning" role="status">Disaster information is not available yet. Please ask the Barangay Hall.</div>
    <?php else: ?>
        <section class="dashboard-panel resident-list-panel">
            <h2 class="drr-section-title">Emergency hotlines</h2>
            <?php if ($hotlines === []): ?>
                <div class="dashboard-empty-state">No hotlines are listed yet. In an emergency, go to the Barangay Hall.</div>
            <?php else: ?>
                <ul class="resident-history">
                    <?php foreach ($hotlines as $hotline): ?>
                        <li><strong><?= e($hotline['name']) ?></strong><span><?= e(disaster_hotline_categories()[$hotline['hotline_category']] ?? '') ?> · <?= disaster_tel_link($hotline['contact_number']) ?><?= $hotline['alternate_number'] ? ' / ' . disaster_tel_link($hotline['alternate_number']) : '' ?><?= $hotline['notes'] ? ' · ' . e($hotline['notes']) : '' ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel resident-list-panel" style="margin-top: 24px;">
            <h2 class="drr-section-title">Hazards in your area<?= $my_purok !== null ? ' (' . e(residents_purok_label($my_purok)) . ')' : '' ?></h2>
            <?php if ($mine === []): ?>
                <div class="dashboard-empty-state">No hazards are recorded for your area.</div>
            <?php else: ?>
                <ul class="resident-history">
                    <?php foreach ($mine as $hazard): ?><li><strong><?= e(disaster_hazard_label($hazard)) ?></strong><span><?= disaster_risk_badge($hazard['risk_level']) ?> · <?= e($hazard['area_name']) ?></span></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php $others = array_values(array_filter($hazards, static fn (array $h): bool => !in_array($h, $mine, true))); ?>
            <?php if ($others !== []): ?>
                <h3 class="resident-subheading mt-3">Other areas</h3>
                <ul class="resident-history">
                    <?php foreach ($others as $hazard): ?><li><strong><?= e($hazard['area_name']) ?></strong><span><?= e(disaster_hazard_label($hazard)) ?> · <?= disaster_risk_badge($hazard['risk_level']) ?></span></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel resident-list-panel" style="margin-top: 24px;">
            <h2 class="drr-section-title">Evacuation centers</h2>
            <?php if ($centers === []): ?>
                <div class="dashboard-empty-state">No evacuation centers are listed yet.</div>
            <?php else: ?>
                <ul class="resident-cards drr-info-cards">
                    <?php foreach ($centers as $center): ?>
                        <li class="resident-card">
                            <div class="resident-card-top"><strong><?= e($center['name']) ?></strong><?= disaster_center_status_badge($center['status']) ?></div>
                            <p><?= e((string) $center['address']) ?><?= $center['area_name'] ? ' · For ' . e($center['area_name']) : '' ?></p>
                            <p>Capacity: <?= $center['capacity'] !== null ? e(number_format((int) $center['capacity'])) . ' persons' : 'not recorded' ?></p>
                            <p><?php foreach (disaster_facilities() as $column => $label): ?><span class="drr-facility<?= (int) $center[$column] === 1 ? ' is-yes' : ' is-no' ?>"><?= (int) $center[$column] === 1 ? '✓' : '✗' ?> <?= e($label) ?></span> <?php endforeach; ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="document-info-note">Go to the nearest <strong>Open</strong> center. Bring water, food, medicines and important documents, especially if the center has no kitchen or water.</p>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
