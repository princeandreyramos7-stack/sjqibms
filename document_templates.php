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

    <section class="documents-section" aria-labelledby="approved-heading">
        <div class="section-heading"><div><h2 id="approved-heading">Official Templates</h2><p>The barangay's official formats. Each request is printed with the format of its document type, filled in from the resident's record.</p></div></div>
        <div class="document-template-grid">
            <?php foreach (array_keys(documents_types()) as $type): $format = documents_official_format_template($type); if ($format === null) continue; ?>
                <article class="document-template-card">
                    <span class="resident-status resident-status-active">Official format</span>
                    <h3><?= e($type) ?></h3>
                    <dl>
                        <div><dt>Used for</dt><dd><?= e($type) ?> requests</dd></div>
                        <div><dt>Format</dt><dd>A4 portrait</dd></div>
                    </dl>
                    <a class="btn btn-light resident-action-btn" href="document_preview.php?format=<?= e(rawurlencode($type)) ?>" target="_blank" rel="noopener">Preview (A4)</a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
