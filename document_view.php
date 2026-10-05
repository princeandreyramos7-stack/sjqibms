<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/documents.php';
require_auth();
$connection = db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$request = $id ? documents_find($connection, $id) : null;
// Same 404 for "missing" and "not yours", so request IDs belonging to other residents are never confirmed.
if (!$request || !documents_can_view_request($connection, $request)) { http_response_code(404); exit('Document request not found.'); }

$is_staff = documents_can_process();
$history = documents_history($connection, (int) $request['id']);
$source = documents_request_source($history);
$rejection_reason = $request['status'] === 'rejected' ? documents_rejection_reason($history) : null;
// Back to the list with its previous search/filters (only a safe query string is accepted).
$return = (string) ($_GET['return'] ?? '');
$back = $is_staff ? 'documents.php' . (preg_match('/^[A-Za-z0-9_=&%+.\-]{1,300}$/', $return) ? '?' . $return : '') : 'resident_documents.php';
$released = null;
foreach ($history as $entry) if ($entry['action'] === 'document_released') $released = $entry;
$not_recorded = '<span class="activity-detail-muted">Not recorded</span>';
// Development test release (this user's session only; the saved status is unchanged).
$test_release = $is_staff ? documents_test_release($request) : null;
$status_badge = $test_release !== null ? documents_test_release_badge() : documents_status_badge($request['status']);

// A4 preview (staff only): the approved template for this type always wins. Until one exists, the neutral development
// sample is shown with visible "not for official issuance" markings; it never counts as an approved template.
$official = $is_staff ? documents_print_template($connection, (string) $request['document_type']) : null;
$sheet_class = ($official['layout'] ?? '') === 'official-format' ? ' is-official-format' : '';
$sample_available = $is_staff && $official === null && documents_sample_template() !== '';
$rendered = null;
if ($official !== null) $rendered = documents_render_template((string) $official['body'], documents_placeholder_values($request));
elseif ($sample_available) $rendered = documents_render_template(documents_sample_template(), documents_placeholder_values($request));
$print_url = 'document_preview.php?id=' . $request['id'] . ($official === null ? '&sample=1' : '') . '&print=1';

$page_title = 'Document Request Details'; $active_page = $is_staff ? 'documents' : 'my_documents';
$page_styles = $is_staff ? ['assets/css/document.css'] : [];
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<?php if ($success = flash('document_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if ($failure = flash('document_error')): ?><div class="dashboard-status warning" role="alert"><?= e($failure) ?></div><?php endif; ?>

<div class="document-detail-head">
    <div>
        <a class="announcement-back" href="<?= e($back) ?>"><span aria-hidden="true">&larr;</span> <?= $is_staff ? 'Back to Documents' : 'Back to My Documents' ?></a>
        <h1>Document Request Details</h1>
        <p class="document-detail-sub"><strong><?= e($request['reference_code']) ?></strong> <?= $status_badge ?> <span class="resident-detail-meta"><?= e($request['document_type']) ?></span></p>
    </div>
    <?php if ($is_staff && ($buttons = documents_action_buttons($connection, $request)) !== ''): ?>
        <div class="doc-action-group is-large document-detail-actions"><?= $buttons ?></div>
    <?php elseif ($test_release !== null): ?>
        <div class="doc-action-group is-large document-detail-actions">
            <form method="post" action="document_action.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $request['id']) ?>"><input type="hidden" name="action" value="test_release_reset"><button class="btn doc-action-btn is-view" type="submit" data-form-confirm="custom" data-dialog-heading="Reset Test Release" data-dialog-message="Show this request as Approved again? Only your test view changes; nothing is saved to the database." data-dialog-confirm="Reset Test Release" data-dialog-dismiss="Cancel">Reset Test Release</button></form>
        </div>
    <?php endif; ?>
</div>
<?php if ($test_release !== null): ?>
    <p class="document-test-banner" role="note"><strong>Development test release — not an official issuance.</strong> Shown only to you in this session. The saved status is still Approved; no release, payment, signing or claimant record was created.</p>
<?php endif; ?>

<div class="document-detail-layout<?= $is_staff ? '' : ' is-single' ?>">
    <div class="document-detail-info">
        <section class="dashboard-panel document-info-panel">
            <h2>Request Information</h2>
            <dl class="activity-detail-list">
                <div><dt>Request reference</dt><dd><?= e($request['reference_code']) ?></dd></div>
                <div><dt>Resident</dt><dd><?= $is_staff ? '<a class="activity-detail-link" href="resident_view.php?id=' . e((string) $request['resident_id']) . '">' . e(residents_full_name($request)) . '</a>' : e(residents_full_name($request)) ?></dd></div>
                <div><dt>Document type</dt><dd><?= e($request['document_type']) ?></dd></div>
                <div><dt>Purpose</dt><dd class="resident-wrap"><?= e($request['purpose']) ?></dd></div>
                <div><dt>Requested</dt><dd><?= e(documents_format_datetime($request['requested_at'])) ?></dd></div>
                <div><dt>Request source</dt><dd><?= $source !== null ? e($source) : $not_recorded ?></dd></div>
                <div><dt>Current status</dt><dd><?= $status_badge ?><?= $test_release !== null ? ' <span class="activity-detail-muted">(saved status: Approved)</span>' : '' ?></dd></div>
                <div><dt>Applicable fee</dt><dd><?= $not_recorded ?></dd></div>
                <div><dt>Payment status</dt><dd><?= $not_recorded ?></dd></div>
                <div><dt>Payment reference</dt><dd><?= $not_recorded ?></dd></div>
            </dl>
            <p class="document-info-note">Fees and payments can be recorded after the Documents database update and the barangay's official fee schedule are approved.</p>
        </section>

        <section class="dashboard-panel document-info-panel">
            <h2>Processing</h2>
            <dl class="activity-detail-list">
                <?php if ($is_staff): ?><div><dt>Approved by</dt><dd><?= $request['approver_name'] ? e($request['approver_name']) : $not_recorded ?></dd></div><?php endif; ?>
                <div><dt>Approval date</dt><dd><?= $request['approved_at'] ? e(documents_format_datetime($request['approved_at'])) : $not_recorded ?></dd></div>
                <?php if ($request['status'] === 'rejected'): ?><div><dt>Rejection reason</dt><dd class="resident-wrap"><?= $rejection_reason !== null ? e($rejection_reason) : $not_recorded ?></dd></div><?php endif; ?>
                <?php if ($request['status'] === 'released'): ?>
                    <div><dt>Released</dt><dd><?= $request['released_at'] ? e(documents_format_datetime($request['released_at'])) : $not_recorded ?></dd></div>
                    <?php if ($is_staff): ?><div><dt>Releasing personnel</dt><dd><?= $released ? e($released['actor']) : $not_recorded ?></dd></div><?php endif; ?>
                    <?php if ($is_staff): ?><div><dt>Received by</dt><dd><?= $released && $released['received_by'] ? e($released['received_by']) : $not_recorded ?></dd></div><?php endif; ?>
                <?php endif; ?>
            </dl>
            <?php if ($test_release !== null): ?>
                <dl class="activity-detail-list">
                    <div><dt>Test release</dt><dd><?= e(documents_format_datetime($test_release['at'])) ?> · <?= e($test_release['by']) ?></dd></div>
                    <div><dt>Official issuance</dt><dd><?= $not_recorded ?></dd></div>
                    <div><dt>Claimant verification</dt><dd><?= $not_recorded ?></dd></div>
                </dl>
            <?php elseif ($request['status'] === 'approved' && $is_staff): ?><p class="document-info-note is-warning">This approved request is ready to release. Select Release when the document is handed over and enter who received it. Previewing or printing never releases it.</p><?php endif; ?>
            <h3 class="resident-subheading">Processing History</h3>
            <?php if ($history === []): ?>
                <p class="resident-static">No processing history is recorded for this request.</p>
            <?php else: ?>
                <ol class="document-steps is-history">
                    <?php foreach ($history as $entry): ?>
                        <li class="is-done"><strong><?= e($entry['label']) ?></strong> · <?= e(documents_format_datetime($entry['at'])) ?><?= $is_staff ? ' · ' . e($entry['actor']) : '' ?><?php if ($entry['reason'] !== null && ($is_staff || $entry['action'] === 'document_rejected')): ?><br><span class="activity-detail-muted">Reason: <?= e($entry['reason']) ?></span><?php endif; ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($is_staff): ?>
    <section class="dashboard-panel document-preview-panel" aria-labelledby="preview-heading">
        <div class="document-preview-head">
            <div>
                <h2 id="preview-heading">A4 Document Preview</h2>
                <?php if ($official !== null): ?><span class="activity-detail-muted"><?= e($official['title']) ?> · v<?= e((string) $official['version_no']) ?></span><?php else: ?><span class="document-dev-label">Development Preview — Not for Official Issuance</span><?php endif; ?>
            </div>
            <div class="doc-action-group">
                <?php if ($official === null && $sample_available): ?><a class="btn doc-action-btn is-view" href="<?= e($print_url) ?>" target="_blank" rel="noopener">Print Sample</a><?php endif; ?>
                <?php if ($official !== null): ?>
                    <a class="btn doc-action-btn is-primary" href="<?= e($print_url) ?>" target="_blank" rel="noopener">Print Document</a>
                <?php else: ?>
                    <button class="btn doc-action-btn is-primary" type="button" disabled aria-describedby="print-unavailable">Print Document</button>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($official === null): ?>
            <p class="document-preview-note" id="print-unavailable">No approved template exists for <?= e($request['document_type']) ?> yet, so a development sample layout is shown. Print Document and Download PDF become available once an approved template is configured.</p>
        <?php endif; ?>
        <?php if ($rendered !== null): ?>
            <div class="document-viewer" id="document-viewer" data-doc-viewer>
                <div class="document-viewer-toolbar" role="toolbar" aria-label="Document preview controls">
                    <div class="document-viewer-zoom">
                        <button class="btn btn-light btn-sm" type="button" data-doc-zoom="out" aria-label="Zoom out">&minus;</button>
                        <span class="document-viewer-level" data-doc-zoom-level aria-live="polite">100%</span>
                        <button class="btn btn-light btn-sm" type="button" data-doc-zoom="in" aria-label="Zoom in">+</button>
                        <button class="btn btn-light btn-sm" type="button" data-doc-zoom="fit">Fit to Screen</button>
                    </div>
                    <button class="btn btn-light btn-sm" type="button" disabled aria-describedby="pdf-unavailable">Download PDF</button>
                </div>
                <p class="document-viewer-note" id="pdf-unavailable">PDF download is unavailable: <?= $official === null ? 'it requires an approved template and ' : '' ?>a PDF library has not been approved or installed.</p>
                <div class="document-viewer-stage" data-doc-stage>
                    <article class="doc-sheet<?= $official === null ? ' is-sample' : $sheet_class ?>" data-doc-sheet><?= $rendered ?></article>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
