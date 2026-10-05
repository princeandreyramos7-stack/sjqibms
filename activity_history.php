<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_auth();
require_once __DIR__ . '/includes/activity.php';
require_once __DIR__ . '/includes/live_search.php';
// The System Administrator uses Audit Logs (a complete record); Activity History remains for the other permitted offices.
if (has_role('super_admin')) {
    redirect('audit_logs.php');
}
if (!role_can('activity.view')) {
    http_response_code(403);
    exit('Access denied.');
}

// ── Input sanitisation ────────────────────────────────────────────────────────
$per_page      = 10;
$current_page  = max(1, (int) ($_GET['page'] ?? 1));

// Module filter — only allow modules the current user can actually access
$accessible    = activity_accessible_modules();
$filter_module = trim((string) ($_GET['module'] ?? ''));
if ($filter_module !== '' && !in_array($filter_module, $accessible, true)) {
    $filter_module = '';          // silently discard unauthorised module attempts
}

// Date filters — accept YYYY-MM-DD only
$filter_from = '';
$filter_to   = '';
$raw_from    = trim((string) ($_GET['from'] ?? ''));
$raw_to      = trim((string) ($_GET['to'] ?? ''));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_from)) {
    $filter_from = $raw_from;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_to)) {
    $filter_to = $raw_to;
}

// ── Data fetch ────────────────────────────────────────────────────────────────
$activities  = [];
$total       = 0;
$fetch_error = null;

try {
    $connection = db();
    $total      = activity_total(
        $connection,
        $filter_module !== '' ? $filter_module : null,
        $filter_from   !== '' ? $filter_from   : null,
        $filter_to     !== '' ? $filter_to     : null,
    );
    $total_pages  = max(1, (int) ceil($total / $per_page));
    $current_page = min($current_page, $total_pages);
    $offset       = ($current_page - 1) * $per_page;

    $activities = activity_fetch(
        $connection,
        $per_page,
        $offset,
        $filter_module !== '' ? $filter_module : null,
        $filter_from   !== '' ? $filter_from   : null,
        $filter_to     !== '' ? $filter_to     : null,
    );
} catch (PDOException) {
    $fetch_error = 'Activity history is temporarily unavailable. Please try again.';
    $total_pages = 1;
}

// ── Pagination URL builder ────────────────────────────────────────────────────
function history_url(int $page, string $module = '', string $from = '', string $to = ''): string
{
    $params = ['page' => $page];
    if ($module !== '') { $params['module'] = $module; }
    if ($from   !== '') { $params['from']   = $from; }
    if ($to     !== '') { $params['to']     = $to; }
    return 'activity_history.php?' . http_build_query($params);
}

// ── Results (shared by the full page and Live Search responses) ─────────────
$render_results = static function () use ($fetch_error, $activities, $current_page, $per_page, $total, $total_pages, $filter_module, $filter_from, $filter_to): void {
    if ($fetch_error !== null): ?>
                    <div class="dashboard-empty-state">Activity history is temporarily unavailable.</div>

                <?php elseif ($activities === []): ?>
                    <div class="dashboard-empty-state">No matching records found.</div>

                <?php else: ?>
                    <div class="activity-history-meta">
                        Showing <?= e((string) (($current_page - 1) * $per_page + 1)) ?>–<?= e((string) min($current_page * $per_page, $total)) ?> of <?= e(number_format($total)) ?> records
                    </div>

                    <ul class="activity-history-list">
                        <?php foreach ($activities as $activity): ?>
                            <li class="activity-history-row">
                                <span class="activity-card-icon"><?= icon_svg($activity['icon']) ?></span>
                                <span class="activity-history-body">
                                    <span class="activity-card-action"><?= e(ucfirst($activity['action'])) ?></span>
                                    <span class="activity-card-meta"><?= e($activity['actor']) ?> &middot; <?= e($activity['module_label']) ?></span>
                                </span>
                                <time class="activity-card-time" datetime="<?= e($activity['created_at']) ?>"><?= e($activity['date']) ?></time>
                                <a class="activity-history-details-link" href="activity_view.php?id=<?= e((string) $activity['id']) ?>">View Details</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Pagination ──────────────────────────────────────── -->
                    <?php if ($total_pages > 1): ?>
                        <nav class="activity-pagination" aria-label="Activity history pagination">
                            <?php if ($current_page > 1): ?>
                                <a class="activity-page-btn" href="<?= e(history_url($current_page - 1, $filter_module, $filter_from, $filter_to)) ?>" aria-label="Previous page" data-live-page>&larr; Previous</a>
                            <?php else: ?>
                                <span class="activity-page-btn is-disabled" aria-disabled="true">&larr; Previous</span>
                            <?php endif; ?>

                            <span class="activity-page-info">Page <?= e((string) $current_page) ?> of <?= e((string) $total_pages) ?></span>

                            <?php if ($current_page < $total_pages): ?>
                                <a class="activity-page-btn" href="<?= e(history_url($current_page + 1, $filter_module, $filter_from, $filter_to)) ?>" aria-label="Next page" data-live-page>Next &rarr;</a>
                            <?php else: ?>
                                <span class="activity-page-btn is-disabled" aria-disabled="true">Next &rarr;</span>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif;
};
if (live_search_is_request()) live_search_respond($render_results);

$page_title  = 'Activity History';
$active_page = 'activity';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/layout/sidebar.php'; ?>
    <div class="app-main">
        <?php require __DIR__ . '/layout/topbar.php'; ?>
        <main class="content">

            <div class="page-heading">
                <div>
                    <h1>Activity History</h1>
                    <p>Authorized audit log records for your role.</p>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="dashboard.php">&larr; Back to Dashboard</a>
            </div>

            <?php if ($fetch_error !== null): ?>
                <div class="dashboard-status warning" role="status"><?= e($fetch_error) ?></div>
            <?php endif; ?>

            <!-- Filters ─────────────────────────────────────────────────── -->
            <form class="activity-filters" method="get" action="activity_history.php" data-live-search data-live-target="#activity-results" data-live-range="#filter-from,#filter-to">
                <div class="activity-filter-group">
                    <label class="activity-filter-label" for="filter-module">Module</label>
                    <select class="activity-filter-select" id="filter-module" name="module">
                        <option value="">All modules</option>
                        <?php foreach ($accessible as $mod): ?>
                            <option value="<?= e($mod) ?>" <?= $filter_module === $mod ? 'selected' : '' ?>><?= e(activity_module_label($mod)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="activity-filter-group">
                    <label class="activity-filter-label" for="filter-from">From</label>
                    <input class="activity-filter-input" type="date" id="filter-from" name="from" value="<?= e($filter_from) ?>">
                </div>
                <div class="activity-filter-group">
                    <label class="activity-filter-label" for="filter-to">To</label>
                    <input class="activity-filter-input" type="date" id="filter-to" name="to" value="<?= e($filter_to) ?>">
                </div>
                <div class="activity-filter-actions">
                    <button class="btn btn-primary btn-sm" type="submit" data-live-submit>Apply</button>
                    <a class="btn btn-outline-secondary btn-sm" href="activity_history.php" data-live-reset>Reset</a>
                </div>
            </form>

            <!-- Results ─────────────────────────────────────────────────── -->
            <div class="dashboard-panel activity-history-panel" id="activity-results"><?php $render_results(); ?></div>

        </main>
        <?php require __DIR__ . '/layout/footer.php'; ?>
    </div>
</div>
