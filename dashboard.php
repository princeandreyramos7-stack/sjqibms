<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_auth();
require_once __DIR__ . '/includes/dashboard_stats.php';
require_once __DIR__ . '/includes/users.php';   // users_resident_staff_roles()

$page_title = 'Dashboard';
$active_page = 'dashboard';
$dashboard_cards = authorized_dashboard_cards();
$dashboard_result = dashboard_statistics();
$dashboard_statistics = $dashboard_result['statistics'];
$dashboard_demographics = $dashboard_result['demographics'];
$dashboard_announcements = $dashboard_result['announcements'];
$dashboard_activities = $dashboard_result['activities'];
$dashboard_errors = $dashboard_result['errors'];
$demographic_labels = [
    'sex' => ['Male', 'Female', 'Other', 'Unknown / Unspecified'],
    'age' => ['Children', 'Teenagers', 'Adults', 'Senior Citizens', 'Unknown Age'],
    'status' => ['Active', 'Pending', 'Inactive', 'Moved', 'Deceased'],
];

// ── Office-specific dashboard ────────────────────────────────────────────────
$role = current_user()['role'] ?? '';
// Residents have their own dashboard (profile, household, document requests, announcements).
if ($role === 'resident') {
    require __DIR__ . '/layout/resident_dashboard.php';
    exit;
}
$office = role_title($role);
$dashboard_titles = [
    'super_admin' => ['System Administrator Dashboard', 'Complete overview of Barangay San Jose operations.'],
    'secretary' => ['Secretary Dashboard', 'Resident records, documents, registrations, and barangay administration.'],
    'treasurer' => ['Treasurer Dashboard', 'Collections, expenses, project budgets, and financial reporting.'],
    'punong_barangay' => ['Punong Barangay Dashboard', 'Barangay finances, disbursements awaiting your approval, and announcements.'],
    'official' => ['Barangay Official Dashboard', 'Community summaries, programs, announcements, and committee work.'],
    'health_worker' => ['Health Worker Dashboard', 'Community health services and resident health information.'],
];
[$dashboard_title, $dashboard_intro] = $dashboard_titles[$role] ?? ['Dashboard', 'Here is the latest overview of Barangay San Jose.'];
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$show_demographics = role_can('stats.population');
$show_activity = role_can('activity.view');
$show_announcements = can_access_navigation('announcements');

// Quick actions only list pages the office may open (each page also enforces its own permission).
$quick_actions = array_values(array_filter([
    ['label' => 'Add Resident', 'icon' => 'user-plus', 'href' => 'resident_form.php', 'allowed' => role_can('residents.manage')],
    ['label' => 'Residents', 'icon' => 'users', 'href' => 'residents.php', 'allowed' => can_access_navigation('residents')],
    ['label' => 'Households', 'icon' => 'home', 'href' => 'households.php', 'allowed' => can_access_navigation('households')],
    ['label' => 'Documents', 'icon' => 'file', 'href' => 'documents.php', 'allowed' => can_access_navigation('documents')],
    ['label' => 'Registrations', 'icon' => 'user-plus', 'href' => 'module.php?module=registrations', 'allowed' => can_access_navigation('registrations')],
    ['label' => 'Complaints & Blotter', 'icon' => 'case', 'href' => 'module.php?module=complaints', 'allowed' => can_access_navigation('complaints')],
    ['label' => 'New Announcement', 'icon' => 'megaphone', 'href' => 'announcement_form.php', 'allowed' => can_manage_announcements()],
    ['label' => 'Financial Management', 'icon' => 'wallet', 'href' => 'finance.php', 'allowed' => can_access_navigation('finance')],
    ['label' => 'Projects', 'icon' => 'briefcase', 'href' => 'module.php?module=projects', 'allowed' => can_access_navigation('projects')],
    ['label' => 'Disaster Management', 'icon' => 'shield', 'href' => 'disaster.php', 'allowed' => can_access_navigation('disaster')],
    ['label' => 'Health', 'icon' => 'heart', 'href' => 'module.php?module=health', 'allowed' => can_access_navigation('health')],
    ['label' => 'Add Health Record', 'icon' => 'heart', 'href' => 'health_form.php', 'allowed' => can_access_navigation('health')],
    ['label' => 'Morbidity Report', 'icon' => 'chart', 'href' => 'health_morbidity.php', 'allowed' => can_access_navigation('health')],
    // Staff who are residents too (Health Workers): their own requests, household and Disaster Info.
    ['label' => 'Request a Document', 'icon' => 'file', 'href' => 'resident_documents.php', 'allowed' => can_access_navigation('my_documents')],
    ['label' => 'My Household', 'icon' => 'home', 'href' => 'my_household.php', 'allowed' => can_access_navigation('my_household')],
    ['label' => 'Disaster Info', 'icon' => 'shield', 'href' => 'disaster_info.php', 'allowed' => can_access_navigation('disaster_info')],
    ['label' => 'Reports', 'icon' => 'chart', 'href' => 'module.php?module=reports', 'allowed' => can_access_navigation('reports')],
], static fn (array $action): bool => $action['allowed']));

// Financial figures only for the Treasurer and Punong Barangay, with the role re-checked in the database. Totals use
// posted collections and released disbursements; cancelled and rejected records are excluded.
$finance_overview = null;
require_once __DIR__ . '/includes/finance.php';
if (role_can('stats.finance') && finance_can('view')) {
    try {
        if (finance_ready(db())) $finance_overview = db()->query("SELECT COALESCE(SUM(CASE WHEN type = 'income' AND status = 'posted' THEN amount END), 0) AS income, COALESCE(SUM(CASE WHEN type = 'expense' AND status = 'released' THEN amount END), 0) AS expenses, SUM(status = 'pending_approval') AS pending, COUNT(*) AS transactions FROM finance_transactions")->fetch();
    } catch (PDOException) {
        $finance_overview = null;
    }
}
$peso = static fn (float $value): string => '₱' . number_format($value, 2);
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/layout/sidebar.php'; ?>
    <div class="app-main">
        <?php require __DIR__ . '/layout/topbar.php'; ?>
        <main class="content dashboard-content">
            <section class="office-hero" aria-label="<?= e($office) ?>">
                <div class="office-hero-copy">
                    <span class="office-hero-badge"><?= icon_svg(login_portals()[staff_portal_for_role($role) ?? '']['icon'] ?? 'grid') ?><?= e($office) ?></span>
                    <h1><?= e($greeting) ?>, <?= e(explode(' ', current_user()['name'] ?? 'Administrator')[0]) ?>.</h1>
                    <p><strong><?= e($dashboard_title) ?></strong> · <?= e($dashboard_intro) ?></p>
                </div>
                <div class="dashboard-meta office-hero-meta"><span class="live-badge">Live database data</span><span class="dashboard-updated">Last Updated: <time data-dashboard-updated><?= e(date('M j, Y g:i A')) ?></time></span></div>
            </section>
            <?php if ($dashboard_errors !== []): ?><div class="dashboard-status warning" role="status">Some statistics are temporarily unavailable. Previously loaded values will be kept during refresh.</div><?php endif; ?>
            <?php if ($dashboard_cards !== []): ?>
            <section class="stats-grid" aria-label="Dashboard summary">
                <?php foreach ($dashboard_cards as $card): ?>
                    <?php $tag = $card['href'] !== null ? 'a' : 'div'; ?>
                    <<?= $tag ?> class="stat-card<?= $card['href'] !== null ? ' stat-card-link' : '' ?>"<?= $card['href'] !== null ? ' href="' . e($card['href']) . '"' : '' ?> data-dashboard-card="<?= e($card['key']) ?>">
                        <div class="stat-top"><span><?= e($card['label']) ?></span><span class="stat-icon"><?= icon_svg($card['icon']) ?></span></div>
                        <div class="stat-value" data-stat-value="<?= e($card['key']) ?>"><?= $dashboard_statistics[$card['key']] === null ? 'Unavailable' : e(number_format($dashboard_statistics[$card['key']])) ?></div>
                        <div class="stat-foot" data-stat-error="<?= e($card['key']) ?>"><?= $dashboard_errors[$card['key']] ?? ($card['href'] !== null ? 'Current records' : 'Summary only') ?></div>
                    </<?= $tag ?>>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>
            <?php if (has_role(...users_resident_staff_roles())) require __DIR__ . '/layout/staff_personal_panels.php';   // they are residents too ?>
            <?php if ($finance_overview !== null): ?>
            <section class="finance-overview" aria-labelledby="finance-heading">
                <div class="section-heading"><div><h2 id="finance-heading">Financial Overview</h2><p>All-time posted collections and released disbursements; cancelled and rejected records are excluded. <a class="activity-detail-link" href="finance.php">Open Financial Management</a></p></div></div>
                <div class="finance-grid">
                    <div class="finance-card is-income"><span>Total Collections</span><strong><?= e($peso((float) $finance_overview['income'])) ?></strong><small>Posted income</small></div>
                    <div class="finance-card is-expense"><span>Total Disbursements</span><strong><?= e($peso((float) $finance_overview['expenses'])) ?></strong><small>Released expenses</small></div>
                    <div class="finance-card is-net"><span>Net Balance</span><strong><?= e($peso((float) $finance_overview['income'] - (float) $finance_overview['expenses'])) ?></strong><small>Collections less disbursements</small></div>
                    <div class="finance-card is-pending"><span>Pending Approval</span><strong><?= e((string) (int) $finance_overview['pending']) ?></strong><small>of <?= e((string) (int) $finance_overview['transactions']) ?> recorded</small></div>
                </div>
            </section>
            <?php endif; ?>
            <?php if ($quick_actions !== []): ?>
            <section class="quick-actions" aria-labelledby="quick-actions-heading">
                <div class="section-heading"><div><h2 id="quick-actions-heading">Quick Actions</h2><p>Shortcuts to the modules assigned to your office.</p></div></div>
                <div class="quick-actions-grid">
                    <?php foreach ($quick_actions as $action): ?><a class="quick-action" href="<?= e($action['href']) ?>"><span class="quick-action-icon"><?= icon_svg($action['icon']) ?></span><span><?= e($action['label']) ?></span></a><?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            <?php if ($show_demographics): ?>
            <section class="demographics-section" aria-labelledby="demographics-heading">
                <div class="section-heading"><div><h2 id="demographics-heading">Demographic Breakdown of All Recorded Resident Profiles</h2><p>Includes active, pending, inactive, moved, and deceased resident profiles.</p></div></div>
                <?php if ($dashboard_demographics === null): ?>
                    <div class="dashboard-status warning" role="status">Population demographics are temporarily unavailable.</div>
                <?php else: ?>
                    <div class="demographics-layout" data-demographics>
                        <article class="dashboard-panel demographic-statistics">
                            <div class="panel-heading"><h3>Population Statistics</h3><span class="demographic-total" data-demographic-total><?= e(number_format($dashboard_demographics['total'])) ?></span></div>
                            <div class="demographic-block"><h4>Sex Distribution</h4><div class="demographic-list" data-demographic-list="sex"><?php foreach ($demographic_labels['sex'] as $label): ?><div class="demographic-row"><span><?= e($label) ?></span><strong data-demographic-value="sex-<?= e($label) ?>"><?= e(number_format($dashboard_demographics['sex'][$label])) ?></strong></div><?php endforeach; ?></div></div>
                            <div class="demographic-block"><h4>Age Groups</h4><div class="demographic-list" data-demographic-list="age"><?php foreach ($demographic_labels['age'] as $label): ?><div class="demographic-row"><span><?= e($label) ?></span><strong data-demographic-value="age-<?= e($label) ?>"><?= e(number_format($dashboard_demographics['age'][$label])) ?></strong></div><?php endforeach; ?></div></div>
                            <?php if (isset($dashboard_demographics['sector'])): ?><div class="demographic-block"><h4>Sectors <small class="demographic-note">(active residents)</small></h4><div class="demographic-list" data-demographic-list="sector"><?php foreach ($dashboard_demographics['sector'] as $label => $count): ?><div class="demographic-row"><span><?= e($label) ?></span><strong data-demographic-value="sector-<?= e($label) ?>"><?= e(number_format($count)) ?></strong></div><?php endforeach; ?></div></div><?php endif; ?><div class="demographic-block"><h4>Resident Status</h4><div class="demographic-list" data-demographic-list="status"><?php foreach ($demographic_labels['status'] as $label): ?><div class="demographic-row"><span><?= e($label) ?></span><strong data-demographic-value="status-<?= e($label) ?>"><?= e(number_format($dashboard_demographics['status'][$label])) ?></strong></div><?php endforeach; ?></div></div>
                        </article>
                        <article class="dashboard-panel demographic-charts">
                            <div class="panel-heading"><h3>Demographic Charts</h3><span class="chart-note">Actual resident records</span></div>
                            <?php if ($dashboard_demographics['total'] === 0): ?><div class="demographics-empty">No resident records available.</div><?php endif; ?><div class="chart-grid">
                                <div class="chart-card"><h4>Age Group Distribution</h4><div class="vertical-chart" data-chart="age"><?php $age_max = max($dashboard_demographics['age']) ?: 1; foreach ($demographic_labels['age'] as $index => $label): ?><div class="vertical-bar-item"><div class="vertical-bar-track"><span class="vertical-bar age-color-<?= $index ?>" data-chart-bar="age-<?= e($label) ?>" style="height: <?= e((string) round(($dashboard_demographics['age'][$label] / $age_max) * 100)) ?>%"></span></div><strong data-chart-label="age-<?= e($label) ?>"><?= e(number_format($dashboard_demographics['age'][$label])) ?></strong><small><?= e($label) ?></small></div><?php endforeach; ?></div></div>
                                <div class="chart-card"><h4>Sex Distribution</h4><div class="donut-wrap"><div class="donut-chart" data-chart-donut="sex" style="--male: <?= e((string) ($dashboard_demographics['total'] ? round($dashboard_demographics['sex']['Male'] / $dashboard_demographics['total'] * 100, 2) : 0)) ?>%; --female: <?= e((string) ($dashboard_demographics['total'] ? round($dashboard_demographics['sex']['Female'] / $dashboard_demographics['total'] * 100, 2) : 0)) ?>%; --other: <?= e((string) ($dashboard_demographics['total'] ? round($dashboard_demographics['sex']['Other'] / $dashboard_demographics['total'] * 100, 2) : 0)) ?>%;"><span><?= e(number_format($dashboard_demographics['total'])) ?><small>Total</small></span></div><div class="chart-legend"><?php foreach ($demographic_labels['sex'] as $index => $label): ?><span><i class="legend-dot sex-color-<?= $index ?>"></i><?= e($label) ?> <strong data-chart-label="sex-<?= e($label) ?>"><?= e(number_format($dashboard_demographics['sex'][$label])) ?></strong></span><?php endforeach; ?></div></div></div>
                                <div class="chart-card chart-card-wide"><h4>Resident Status Distribution</h4><div class="horizontal-chart" data-chart="status"><?php foreach ($demographic_labels['status'] as $index => $label): ?><div class="horizontal-bar-item"><span><?= e($label) ?></span><div class="horizontal-bar-track"><i class="horizontal-bar status-color-<?= $index ?>" data-chart-bar="status-<?= e($label) ?>" style="width: <?= e((string) ($dashboard_demographics['total'] ? round($dashboard_demographics['status'][$label] / $dashboard_demographics['total'] * 100, 2) : 0)) ?>%"></i></div><strong data-chart-label="status-<?= e($label) ?>"><?= e(number_format($dashboard_demographics['status'][$label])) ?></strong></div><?php endforeach; ?></div></div>
                            </div>
                        </article>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            <?php if ($show_activity || $show_announcements): ?>
            <section class="dashboard-grid<?= $show_activity && $show_announcements ? '' : ' is-single' ?>">
                <?php if ($show_activity): ?>
                <article class="dashboard-panel activity-panel" data-activity-panel>
                    <div class="panel-heading">
                        <h2>Recent Activity</h2>
                        <a href="<?= has_role('super_admin') ? 'audit_logs.php' : 'activity_history.php' ?>">View All</a>
                    </div>
                    <?php if (isset($dashboard_errors['activities'])): ?>
                        <div class="dashboard-empty-state"><?= e($dashboard_errors['activities']) ?></div>
                    <?php elseif (empty($dashboard_activities)): ?>
                        <div class="dashboard-empty-state">No recent activities available.</div>
                    <?php else: ?>
                        <ul class="activity-card-list" data-activity-list>
                            <?php foreach ($dashboard_activities as $activity): ?>
                                <li>
                                    <a class="activity-card" href="<?= has_role('super_admin') ? 'audit_log_view.php' : 'activity_view.php' ?>?id=<?= e((string) $activity['id']) ?>">
                                        <span class="activity-card-icon"><?= icon_svg($activity['icon']) ?></span>
                                        <span class="activity-card-body">
                                            <span class="activity-card-action"><?= e(ucfirst($activity['action'])) ?></span>
                                            <span class="activity-card-meta"><?= e($activity['actor']) ?> &middot; <?= e($activity['module_label']) ?></span>
                                        </span>
                                        <time class="activity-card-time" datetime="<?= e($activity['created_at']) ?>"><?= e($activity['date']) ?></time>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
                <?php endif; ?>
                <?php if ($show_announcements): ?>
                <article class="dashboard-panel announcements-panel" data-announcements-panel data-can-manage="<?= can_manage_announcements() ? 'true' : 'false' ?>">
                    <div class="panel-heading"><h2>Latest Announcements</h2><?php if (can_access_navigation('announcements')): ?><a href="<?= e(navigation_item('announcements')['href']) ?>">View All</a><?php endif; ?></div>
                            <?php if ($dashboard_announcements === null): ?>
                        <div class="dashboard-empty-state">Announcements are temporarily unavailable.</div>
                    <?php else: ?>
                        <div class="announcement-tabs" role="tablist" aria-label="Announcement status">
                            <button class="announcement-tab active" type="button" role="tab" aria-selected="true" data-announcement-tab="published">Published</button>
                            <?php if (can_manage_announcements()): ?><button class="announcement-tab" type="button" role="tab" aria-selected="false" data-announcement-tab="drafts">Drafts</button><?php endif; ?>
                        </div>
                            <div class="announcement-list" data-announcement-list="published">
                            <?php if ($dashboard_announcements['published'] === []): ?><div class="announcement-empty">No announcements available.<?php if (can_manage_announcements()): ?><a class="announcement-action" href="announcement_form.php">Add Announcement</a><?php endif; ?></div><?php else: ?><?php foreach ($dashboard_announcements['published'] as $announcement): ?><a class="announcement-item" href="announcement_view.php?id=<?= e((string) $announcement['id']) ?>"><strong><?= e($announcement['title']) ?></strong><span><?= e($announcement['preview']) ?></span><time><?= e($announcement['date']) ?></time></a><?php endforeach; ?><?php endif; ?>
                        </div>
                        <?php if (can_manage_announcements()): ?><div class="announcement-list" data-announcement-list="drafts" hidden><?php if ($dashboard_announcements['drafts'] === []): ?><div class="announcement-empty">No drafts available.<a class="announcement-action" href="announcement_form.php">Add Announcement</a></div><?php else: ?><?php foreach ($dashboard_announcements['drafts'] as $announcement): ?><a class="announcement-item" href="announcement_view.php?id=<?= e((string) $announcement['id']) ?>"><strong><?= e($announcement['title']) ?></strong><span><?= e($announcement['preview']) ?></span><time><?= e($announcement['date']) ?></time></a><?php endforeach; ?><?php endif; ?></div><?php endif; ?>
                    <?php endif; ?>
                </article>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </main>
        <?php require __DIR__ . '/layout/footer.php'; ?>
    </div>
</div>
