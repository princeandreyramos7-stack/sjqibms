<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/navigation.php';
require_auth();

$module_key = (string) ($_GET['module'] ?? '');
$selected_module = navigation_item($module_key);
if ($selected_module === null || !can_access_navigation($module_key)) {
    http_response_code(403);
    exit('Access denied.');
}

if ($module_key === 'announcements') {
    redirect('announcements.php');
}
if ($module_key === 'residents') {
    redirect('residents.php');
}
if ($module_key === 'registrations') {
    redirect('registrations.php');
}
if ($module_key === 'households') {
    redirect('households.php');
}
if ($module_key === 'documents') {
    redirect('documents.php');
}
if ($module_key === 'complaints') {
    redirect('complaints.php');
}
if ($module_key === 'reports') {
    redirect('reports.php');
}
if ($module_key === 'officials') {
    redirect('officials.php');
}
if ($module_key === 'audit') {
    redirect('audit_logs.php');
}
if ($module_key === 'inventory') {
    redirect('inventory.php');
}
if ($module_key === 'health') {
    redirect('health.php');
}
if ($module_key === 'disaster') {
    redirect('disaster.php');
}
if ($module_key === 'users') {
    redirect('users.php');
}
if ($module_key === 'finance') {
    $finance_query = http_build_query(array_diff_key($_GET, ['module' => true]));
    redirect('finance.php' . ($finance_query !== '' ? '?' . $finance_query : ''));
}

// TEMPORARY: modules without a built workflow show read-only sample data (includes/sample_data.php).
// Access was already checked above with the module's navigation roles. Remove this block to restore the placeholder.
require_once __DIR__ . '/includes/sample_data.php';
$sample = sample_data_module($module_key);
if ($sample !== null) {
    require __DIR__ . '/layout/sample_module.php';
    exit;
}

$page_title = $selected_module['label'];
$active_page = $selected_module['key'];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/layout/sidebar.php'; ?>
    <div class="app-main">
        <?php require __DIR__ . '/layout/topbar.php'; ?>
        <main class="content">
            <section class="placeholder-page">
                <div class="placeholder-icon"><?= icon_svg($selected_module['icon']) ?></div>
                <span class="eyebrow">SJQIBMS module</span>
                <h1><?= e($selected_module['label']) ?></h1>
                <p>The <?= e($selected_module['label']) ?> workspace is being prepared and will be available in a future development milestone.</p>
                <a class="btn btn-primary placeholder-action" href="dashboard.php">Return to dashboard</a>
            </section>
        </main>
        <?php require __DIR__ . '/layout/footer.php'; ?>
    </div>
</div>
