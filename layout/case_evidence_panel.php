<?php
// Staff-only evidence panel for a complaint or blotter entry. Expects: $evidence_kind ('complaint'|'blotter'),
// $evidence_case_id (int), $evidence_rows (complaints_attachments()), $evidence_closed (bool), $evidence_upload_ready (bool).
?>
<section class="dashboard-panel case-panel" aria-labelledby="evidence-heading">
    <h2 id="evidence-heading">Confidential Evidence</h2>
    <?php if ($evidence_rows === []): ?>
        <p class="resident-static">No evidence files are attached.</p>
    <?php else: ?>
        <ul class="case-evidence-list">
            <?php foreach ($evidence_rows as $file): ?>
                <li class="<?= $file['removed_at'] ? 'is-removed' : '' ?>">
                    <div>
                        <strong><?= e($file['original_filename']) ?></strong>
                        <span class="activity-detail-muted"><?= e(strtoupper(explode('/', (string) $file['mime_type'])[1] ?? 'file')) ?> · <?= e(complaints_format_bytes((int) $file['file_size'])) ?> · Uploaded <?= e(complaints_format_datetime($file['uploaded_at'])) ?><?= $file['uploader_name'] ? ' by ' . e($file['uploader_name']) : '' ?></span>
                        <?php if ($file['description']): ?><span><?= e($file['description']) ?></span><?php endif; ?>
                        <?php if ($file['removed_at']): ?><span class="case-removed-note">Removed <?= e(complaints_format_datetime($file['removed_at'])) ?><?= $file['remover_name'] ? ' by ' . e($file['remover_name']) : '' ?> — <?= e($file['removal_reason']) ?></span><?php endif; ?>
                    </div>
                    <?php if (!$file['removed_at']): ?>
                        <div class="doc-action-group">
                            <a class="btn doc-action-btn is-view" href="case_evidence.php?id=<?= e((string) $file['id']) ?>">Download</a>
                            <form method="post" action="case_evidence.php" class="doc-action-form"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= e((string) $file['id']) ?>"><input type="hidden" name="reason" value=""><button class="btn doc-action-btn is-danger" type="submit" data-form-confirm="custom" data-dialog-heading="Remove evidence file?" data-dialog-message="The file will no longer be downloadable. Its record and removal reason are kept as history." data-dialog-reason="Reason for removal" data-dialog-confirm="Remove File" data-dialog-dismiss="Cancel" data-dialog-danger="true">Remove</button></form>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if (!$evidence_closed): ?>
        <?php if ($evidence_upload_ready): ?>
            <form class="case-evidence-upload" method="post" action="case_evidence.php" enctype="multipart/form-data">
                <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="kind" value="<?= e($evidence_kind) ?>"><input type="hidden" name="case_id" value="<?= e((string) $evidence_case_id) ?>">
                <div><label class="form-label" for="evidence-file">Add file <span class="activity-detail-muted">(PDF, JPG or PNG, up to <?= e(complaints_format_bytes(COMPLAINTS_EVIDENCE_MAX_BYTES)) ?>)</span></label><input class="form-control" type="file" id="evidence-file" name="evidence" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></div>
                <div><label class="form-label" for="evidence-description">Description <span class="activity-detail-muted">(optional)</span></label><input class="form-control" id="evidence-description" name="description" maxlength="255"></div>
                <div class="doc-action-group"><button class="btn doc-action-btn is-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Upload evidence file?" data-dialog-message="The file is stored in protected storage and can only be downloaded by the Secretary and Super Admin." data-dialog-confirm="Upload File" data-dialog-dismiss="Cancel">Upload</button></div>
            </form>
        <?php else: ?>
            <p class="dashboard-status warning">Evidence uploads are disabled because the protected storage folder failed its security check.</p>
        <?php endif; ?>
    <?php endif; ?>
</section>
