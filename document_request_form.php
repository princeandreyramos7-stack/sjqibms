<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/documents.php';
require_once __DIR__ . '/includes/live_search.php';
require_auth();
if (!role_can('documents.request.create')) { http_response_code(403); exit('Access denied.'); }
$connection = db();

$search = residents_collapse((string) ($_GET['q'] ?? ''));
if (mb_strlen($search) > 100) $search = mb_substr($search, 0, 100);
$search_ready = mb_strlen(preg_replace('/\s+/u', '', $search) ?? '') >= 2;
$selected_id = filter_var($_POST['resident_id'] ?? $_GET['resident'] ?? null, FILTER_VALIDATE_INT) ?: null;
$values = ['document_type' => (string) ($_POST['document_type'] ?? $_GET['type'] ?? ''), 'purpose' => (string) ($_POST['purpose'] ?? '')];
$errors = [];
$form_error = null;
$open_request = null;
// Template shortcut (Frequently Used Templates): only an approved, active, non-sample template is accepted; it preselects its type.
$template_notice = null;
$template_id = filter_var($_POST['template'] ?? $_GET['template'] ?? null, FILTER_VALIDATE_INT) ?: null;
$template = $template_id ? documents_approved_template($connection, $template_id) : null;
if ($template_id && ($template === null || !array_key_exists($template['document_type'], documents_types()))) {
    $template_notice = 'The selected template is not available (it is not approved, not active, or the template library is not installed). Choose the document type below.';
    $template = null;
    $template_id = null;
}
if ($template !== null && $_SERVER['REQUEST_METHOD'] !== 'POST') $values['document_type'] = $template['document_type'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = documents_validate_request($_POST);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if ($template !== null && $values['document_type'] !== $template['document_type']) $errors['document_type'] = 'The document type must match the selected template (' . $template['document_type'] . ').';
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif (!$selected_id) $errors['resident'] = 'Select an existing resident.';
    if ($form_error === null && $errors === []) {
        try {
            // Same centralized creation as online requests; staff-created requests start as Pending and are never auto-approved.
            $created = documents_create_request($connection, $selected_id, $values['document_type'], $values['purpose'], 'staff');
            flash('document_success', 'Request ' . $created['reference'] . ' created for the resident. It is now Pending Review.');
            redirect('document_view.php?id=' . $created['id']);
        } catch (DocumentOpenRequestException $exception) {
            // The resident already has this document open: link straight to it so staff can release (or reject) it.
            $form_error = $exception->getMessage();
            $open_request = ['id' => $exception->request_id, 'reference' => $exception->reference, 'status' => $exception->status];
        } catch (RuntimeException $exception) {
            $form_error = $exception->getMessage();
        } catch (PDOException) {
            $form_error = 'The request could not be created. No changes were made.';
        }
    }
}

// Selected resident (re-read from the database; only active profiles can be requested for).
$selected = null;
if ($selected_id) {
    $statement = $connection->prepare('SELECT id, first_name, middle_name, last_name, suffix, birth_date, purok, status FROM residents WHERE id = :id');
    $statement->execute(['id' => $selected_id]);
    $selected = $statement->fetch() ?: null;
    if ($selected === null) $form_error ??= 'The selected resident profile no longer exists.';
}

// Resident lookup: minimum identifying fields only.
$results = [];
if ($search_ready) {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $lookup = $connection->prepare("SELECT id, first_name, middle_name, last_name, suffix, birth_date, purok, status FROM residents WHERE CONCAT_WS(' ', first_name, middle_name, last_name, suffix) LIKE :a OR CONCAT_WS(' ', first_name, last_name) LIKE :b OR CONCAT_WS(', ', last_name, first_name) LIKE :c ORDER BY last_name, first_name, id LIMIT 15");
    $lookup->execute(['a' => $like, 'b' => $like, 'c' => $like]);
    $results = $lookup->fetchAll();
}
$type_param = ($values['document_type'] !== '' ? '&type=' . rawurlencode($values['document_type']) : '') . ($template_id ? '&template=' . $template_id : '');
$render_results = static function () use ($search, $search_ready, $results, $type_param, $selected_id): void {
    if ($search !== '' && !$search_ready): ?>
        <p class="resident-static">Type at least 2 characters to search.</p>
    <?php elseif ($search_ready && $results === []): ?>
        <p class="resident-pending">No matching records found.</p>
    <?php elseif ($results !== []): ?>
        <ul class="household-member-list">
            <?php foreach ($results as $result): $year = $result['birth_date'] ? substr($result['birth_date'], 0, 4) : null; ?>
                <?php $details = '<div><strong>' . e(residents_full_name($result)) . '</strong><span>#' . e((string) $result['id']) . ' · ' . ($year ? 'Born ' . e($year) : 'Birth year unknown') . ' · ' . e(residents_purok_label($result['purok'])) . ' · ' . residents_status_badge($result['status']) . '</span></div>'; ?>
                <?php if ($result['status'] !== 'active'): ?>
                    <li><?= $details ?><p class="household-result-note">Only active residents can request documents.</p></li>
                <?php elseif ((int) $result['id'] === (int) $selected_id): ?>
                    <li class="is-selected" aria-current="true"><?= $details ?><span class="resident-status resident-status-active">Selected</span></li>
                <?php else: ?>
                    <?php // The whole row selects the resident (no separate button). ?>
                    <li class="is-selectable"><a class="household-member-pick" href="document_request_form.php?resident=<?= e((string) $result['id']) ?><?= e($type_param) ?>" aria-label="Select <?= e(residents_full_name($result)) ?>"><?= $details ?><span class="household-member-pick-cue" aria-hidden="true">&rsaquo;</span></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif;
};
if (live_search_is_request()) live_search_respond($render_results);

$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$page_title = 'New Document Request'; $active_page = 'documents';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="documents.php" data-form-confirm="custom" data-dialog-heading="Leave this request?" data-dialog-message="The request has not been submitted. Any information you entered will be lost." data-dialog-confirm="Leave Page" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div><h1>New Document Request</h1><p>Record a request on behalf of an existing resident. It starts as Pending Review and follows the normal approval process.</p></div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?><?php if ($open_request !== null): ?> <a class="alert-link" href="document_view.php?id=<?= e((string) $open_request['id']) ?>">Open <?= e($open_request['reference']) ?> to <?= $open_request['status'] === 'approved' ? 'release it' : 'process it' ?> &rarr;</a><?php endif; ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert">Please correct the highlighted fields.</div><?php endif; ?>
    <?php if ($template_notice !== null): ?><div class="dashboard-status warning" role="status"><?= e($template_notice) ?></div><?php elseif ($template !== null): ?><div class="document-selected-resident"><div><strong><?= e($template['document_type']) ?></strong><span>Template: <?= e($template['title']) ?></span></div></div><?php endif; ?>

    <fieldset class="resident-section">
        <legend>1. Select Resident</legend>
        <?php if ($selected): ?>
            <div class="document-selected-resident"><div><strong><?= e(residents_full_name($selected)) ?></strong><span>#<?= e((string) $selected['id']) ?> · <?= e(residents_purok_label($selected['purok'])) ?> ·<?= residents_status_badge($selected['status']) ?></span></div><span class="activity-detail-muted">Search again to choose a different resident.</span></div>
        <?php endif; ?>
        <form class="resident-filters" method="get" action="document_request_form.php" data-live-search data-live-target="#resident-lookup" data-live-min="2" data-live-min-message="Type at least 2 characters to search.">
            <?php if ($selected_id): ?><input type="hidden" name="resident" value="<?= e((string) $selected_id) ?>"><?php endif; ?>
            <?php if ($values['document_type'] !== ''): ?><input type="hidden" name="type" value="<?= e($values['document_type']) ?>"><?php endif; ?>
            <?php if ($template_id): ?><input type="hidden" name="template" value="<?= e((string) $template_id) ?>"><?php endif; ?>
            <div class="resident-filter-group resident-filter-search"><label class="activity-filter-label" for="resident-search">Search resident by name</label><input class="activity-filter-input" type="search" id="resident-search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Type at least 2 characters" autocomplete="off" data-live-query></div>
            <div class="activity-filter-actions"><button class="btn btn-primary btn-sm" type="submit" data-live-submit>Search</button></div>
        </form>
        <div id="resident-lookup" class="live-search-results"><?php $render_results(); ?></div>
        <?php if (isset($errors['resident'])): ?><div class="invalid-feedback d-block"><?= e($errors['resident']) ?></div><?php endif; ?>
    </fieldset>

    <form method="post" action="document_request_form.php">
        <?= csrf_field() ?>
        <input type="hidden" name="resident_id" value="<?= e((string) ($selected['id'] ?? '')) ?>">
        <?php if ($template_id): ?><input type="hidden" name="template" value="<?= e((string) $template_id) ?>"><?php endif; ?>
        <fieldset class="resident-section">
            <legend>2. Document</legend>
            <div class="document-type-grid" role="radiogroup" aria-label="Document type">
                <?php foreach (documents_types() as $type => $description): ?>
                    <label class="document-type"><input type="radio" name="document_type" value="<?= e($type) ?>" <?= $values['document_type'] === $type ? 'checked' : '' ?> required><strong><?= e($type) ?></strong><small><?= e($description) ?></small></label>
                <?php endforeach; ?>
            </div>
            <?php if (isset($errors['document_type'])): ?><div class="invalid-feedback d-block"><?= e($errors['document_type']) ?></div><?php endif; ?>
        </fieldset>
        <fieldset class="resident-section">
            <legend>3. Purpose</legend>
            <label class="form-label" for="purpose">Purpose</label>
            <textarea class="form-control<?= $field_class('purpose') ?>" id="purpose" name="purpose" rows="3" minlength="5" maxlength="500" required placeholder="As stated by the resident"><?= e($values['purpose']) ?></textarea>
            <?php if (isset($errors['purpose'])): ?><div class="invalid-feedback"><?= e($errors['purpose']) ?></div><?php endif; ?>
        </fieldset>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" <?= $selected && $selected['status'] === 'active' ? '' : 'disabled' ?> data-form-confirm="custom" data-dialog-heading="Create this document request?" data-dialog-message="<?= e($selected ? 'The request will be recorded for ' . residents_full_name($selected) . ' as Pending Review. It is not approved automatically.' : 'Select a resident first.') ?>" data-dialog-confirm="Create Request">Create Request</button>
            <a class="btn btn-light" href="documents.php" data-form-confirm="custom" data-dialog-heading="Leave this request?" data-dialog-message="The request has not been submitted. Any information you entered will be lost." data-dialog-confirm="Leave Page" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
