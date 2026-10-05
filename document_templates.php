<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/documents.php';
require_auth();
if (!documents_can_process()) { http_response_code(403); exit('Access denied.'); }
$connection = db();
$installed = documents_templates_installed($connection);
$page_title = 'Document Templates'; $active_page = 'documents';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading">
        <div><a class="announcement-back" href="documents.php"><span aria-hidden="true">&larr;</span> Back</a><h1>Document Templates</h1><p>Approved templates available for official document requests.</p></div>
    </div>

    <?php if (!$installed): ?>
        <div class="dashboard-status warning" role="status">
            <strong>Template library not installed.</strong> Configurable document types, versioned templates, approval, featured templates and "Use Template" need the Documents database update in
            <code>database/migrations/review/</code>. It has been prepared for review and has not been imported.
        </div>
    <?php endif; ?>

    <section class="documents-section" aria-labelledby="approved-heading">
        <div class="section-heading"><div><h2 id="approved-heading">Approved Templates</h2><p>Only approved and active templates can be used for official requests.</p></div></div>
        <div class="document-quick-empty"><p><strong>No approved document templates are available yet.</strong></p></div>
    </section>

    <section class="documents-section" aria-labelledby="dev-heading">
        <div class="section-heading"><div><h2 id="dev-heading">Development Sample</h2><p>For testing A4 layout, placeholders, preview and printing only.</p></div></div>
        <div class="document-template-grid">
            <article class="document-template-card is-sample">
                <span class="resident-status resident-status-deceased">Not for issuance</span>
                <h3>Development Sample Layout</h3>
                <dl>
                    <div><dt>Status</dt><dd>Development sample (not approved)</dd></div>
                    <div><dt>Used for</dt><dd>Any type without an approved template</dd></div>
                    <div><dt>Format</dt><dd>A4 portrait · 20mm margins</dd></div>
                </dl>
                <a class="btn btn-light resident-action-btn" href="document_preview.php">Preview (A4)</a>
            </article>
        </div>
    </section>

    <section class="documents-section" aria-labelledby="placeholders-heading">
        <div class="section-heading"><div><h2 id="placeholders-heading">Controlled Placeholders</h2><p>Templates may only use these placeholders; values are filled from verified records and escaped.</p></div></div>
        <div class="dashboard-panel">
            <ul class="document-placeholder-list">
                <?php foreach (documents_placeholders() as $key => $label): ?><li><code>{{<?= e($key) ?>}}</code><span><?= e($label) ?></span></li><?php endforeach; ?>
            </ul>
        </div>
    </section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
