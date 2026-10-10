<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/documents.php';
require_auth();
// Staff with document processing rights only; residents never see internal document previews.
if (!documents_can_process()) { http_response_code(403); exit('Access denied.'); }
$connection = db();

$request = null;
$request_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
if ($request_id !== null) {
    $request = documents_find($connection, $request_id);
    if ($request === null) { http_response_code(404); exit('Document request not found.'); }
}

// Same template choice as the details page: the approved template for the request's type, or — only when asked for with
// sample=1 — the neutral, watermarked development sample. This page never changes a request's status.
$official = $request !== null && ($_GET['sample'] ?? '') !== '1' ? documents_print_template($connection, (string) $request['document_type']) : null;
// Without a request, ?format=<document type> previews that official format with its placeholders unfilled (Document Templates page).
if ($request === null && isset($_GET['format'])) {
    $official = documents_official_format_template((string) $_GET['format']);
    if ($official === null) { http_response_code(404); exit('No official format exists for this document type.'); }
}
$is_sample = $official === null;
if ($request !== null && $is_sample && ($_GET['sample'] ?? '') !== '1') { http_response_code(404); exit('No approved document template is available for this document type.'); }
$template = $is_sample ? documents_sample_template() : (string) $official['body'];
if ($template === '') { http_response_code(500); exit('The sample template file is missing.'); }
$rendered = documents_render_template($template, documents_placeholder_values($request));
$back = $request ? 'document_view.php?id=' . $request['id'] : 'document_templates.php';
$auto_print = ($_GET['print'] ?? '') === '1';
// Opening a document for preview or printing is recorded in the Audit Logs (the status is never changed).
if ($request !== null) security_log($auto_print ? 'document_print_opened' : 'document_previewed', (int) current_user()['id'], ['reference' => $request['reference_code'], 'document_type' => $request['document_type'], 'layout' => $is_sample ? 'development sample' : 'official format'], 'document', (int) $request['id']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($request ? $request['reference_code'] . ' – Sample Preview' : 'Sample Template Preview') ?> | SJQIBMS</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link href="assets/css/document.css?v=<?= e((string) @filemtime(__DIR__ . '/assets/css/document.css')) ?>" rel="stylesheet">
</head>
<body class="doc-page">
<div class="doc-toolbar">
    <div class="doc-toolbar-title">
        <strong><?= $is_sample ? 'Development Preview — Not for Official Issuance' . ($request ? ' · ' . $request['document_type'] : '') : e($official['title'] . ' · v' . $official['version_no']) ?></strong>
        <span><?= $request ? e($request['reference_code'] . ' · ' . residents_full_name($request)) : 'Template preview — the bracketed fields are filled from the resident\'s request when printed' ?></span>
    </div>
    <div class="doc-toolbar-actions">
        <a href="<?= e($back) ?>">&larr; Back</a>
        <button class="is-primary" type="button" data-print>Print (A4)</button>
        <button type="button" disabled title="PDF download needs an approved PDF library (see the Documents database update report).">Download PDF — unavailable</button>
    </div>
</div>
<?php if ($is_sample): ?><p class="doc-banner"><strong>SAMPLE — NOT VALID FOR OFFICIAL ISSUANCE.</strong> This is a development-only sample template for testing A4 layout, preview and printing. Signatory placeholders stay unfilled until approved signatory records exist. Printing does not change the request status.</p><?php endif; ?>
<main class="doc-stage">
    <article class="doc-sheet<?= $is_sample ? ' is-sample' : (($official['layout'] ?? '') === 'official-format' ? ' is-official-format' : '') ?>"><?= $rendered ?></article>
</main>
<script>
document.querySelector('[data-print]').addEventListener('click', () => window.print());
<?php if ($auto_print): ?>window.addEventListener('load', () => window.print());<?php endif; ?>
</script>
</body>
</html>
