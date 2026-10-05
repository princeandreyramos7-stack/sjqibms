<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/documents.php';
require_auth();
if (!can_access_navigation('my_documents')) { http_response_code(403); exit('Access denied.'); }
$connection = db();
$profile = documents_resident_profile($connection);
$values = ['document_type' => (string) ($_GET['type'] ?? ''), 'purpose' => ''];
$errors = [];
$form_error = null;
$max_open = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validated = documents_validate_request($_POST);
    $values = $validated['values'];
    $errors = $validated['errors'];
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $form_error = 'Your session expired. Please review the form and submit again.';
    elseif ($profile === null) $form_error = 'Your account is not linked to a resident profile yet. Please visit the Barangay Hall.';
    elseif ($profile['status'] !== 'active') $form_error = 'Only active resident profiles can request documents. Please visit the Barangay Hall.';
    if ($form_error === null && $errors === []) {
        try {
            // Centralized creation; the resident ID comes only from the signed-in account's linked profile, never from the form.
            $created = documents_create_request($connection, (int) $profile['id'], $values['document_type'], $values['purpose'], 'online', $max_open);
            flash('document_success', 'Request ' . $created['reference'] . ' submitted. You will see its status here once the Barangay Secretary reviews it.');
            redirect('resident_documents.php');
        } catch (RuntimeException $exception) {
            $form_error = str_replace(['for this resident', 'A resident can have'], ['for you', 'You can have'], $exception->getMessage());
        } catch (PDOException) {
            $form_error = 'Your request could not be submitted. Please try again.';
        }
    }
}
$requests = $profile ? documents_for_resident($connection, (int) $profile['id']) : [];
$can_request = $profile !== null && $profile['status'] === 'active';
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
$page_title = 'Document Requests'; $active_page = 'my_documents';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
    <div class="page-heading"><div><h1>Document Requests</h1><p>Request barangay documents and follow their status online.</p></div></div>
    <?php if ($success = flash('document_success')): ?><div class="announcement-flash" role="status"><?= e($success) ?></div><?php endif; ?>

    <div class="resident-portal-grid">
        <section class="dashboard-panel resident-form-panel" aria-labelledby="request-heading">
            <h2 class="resident-portal-title" id="request-heading">New Request</h2>
            <?php if (!$can_request): ?>
                <p class="resident-pending"><?= $profile === null ? 'Your account is not linked to a resident profile yet. Please visit the Barangay Hall to link your account before requesting documents.' : 'Only active resident profiles can request documents. Please visit the Barangay Hall.' ?></p>
            <?php else: ?>
                <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php endif; ?>
                <form method="post" action="resident_documents.php">
                    <?= csrf_field() ?>
                    <fieldset class="resident-section">
                        <legend class="gate-visually-hidden">Document</legend>
                        <div class="document-type-grid" role="radiogroup" aria-label="Document type">
                            <?php foreach (documents_types() as $type => $description): ?>
                                <label class="document-type<?= $values['document_type'] === $type ? ' is-selected' : '' ?>">
                                    <input type="radio" name="document_type" value="<?= e($type) ?>" <?= $values['document_type'] === $type ? 'checked' : '' ?> required>
                                    <strong><?= e($type) ?></strong>
                                    <small><?= e($description) ?></small>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (isset($errors['document_type'])): ?><div class="invalid-feedback d-block"><?= e($errors['document_type']) ?></div><?php endif; ?>
                    </fieldset>
                    <div class="mb-3">
                        <label class="form-label" for="purpose">Purpose</label>
                        <textarea class="form-control<?= $field_class('purpose') ?>" id="purpose" name="purpose" rows="3" minlength="5" maxlength="500" placeholder="For example: Employment requirement at ABC Company" required><?= e($values['purpose']) ?></textarea>
                        <?= $field_error('purpose') ?>
                    </div>
                    <p class="resident-static">Requested for: <strong><?= e(residents_full_name($profile)) ?></strong> · <?= e($profile['purok']) ?>. Bring a valid ID when claiming your document at the Barangay Hall.</p>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit" data-form-confirm="custom" data-dialog-heading="Submit document request?" data-dialog-message="Your request will be sent to the Barangay Secretary for review. You can follow its status on this page." data-dialog-confirm="Submit Request">Submit Request</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel resident-form-panel" aria-labelledby="history-heading">
            <h2 class="resident-portal-title" id="history-heading">My Requests</h2>
            <?php if ($requests === []): ?>
                <div class="dashboard-empty-state">You have not requested any documents yet.</div>
            <?php else: ?>
                <ul class="document-request-list">
                    <?php foreach ($requests as $request): ?>
                        <li>
                            <div class="document-request-top"><strong><?= e($request['document_type']) ?></strong><?= documents_status_badge($request['status']) ?></div>
                            <span class="document-request-ref"><a class="activity-detail-link" href="document_view.php?id=<?= e((string) $request['id']) ?>"><?= e($request['reference_code']) ?></a> · Requested <?= e(documents_format_datetime($request['requested_at'])) ?></span>
                            <p><?= e($request['purpose']) ?></p>
                            <?php if ($request['status'] === 'rejected' && ($reason = documents_rejection_reason(documents_history($connection, (int) $request['id']))) !== null): ?><p class="document-reject-reason"><strong>Reason:</strong> <?= e($reason) ?></p><?php endif; ?>
                            <ol class="document-steps" aria-label="Request progress">
                                <li class="is-done">Submitted</li>
                                <?php if ($request['status'] === 'rejected'): ?>
                                    <li class="is-rejected">Rejected — see the reason above or visit the Barangay Hall</li>
                                <?php else: ?>
                                    <li class="<?= in_array($request['status'], ['approved', 'released'], true) ? 'is-done' : 'is-current' ?>">Approved<?= $request['approved_at'] ? ' · ' . e(documents_format_datetime($request['approved_at'])) : '' ?></li>
                                    <li class="<?= $request['status'] === 'released' ? 'is-done' : ($request['status'] === 'approved' ? 'is-current' : '') ?>"><?= $request['status'] === 'approved' ? 'Being prepared by the barangay — you will be informed when it is ready to claim' : 'Released' ?><?= $request['released_at'] ? ' · ' . e(documents_format_datetime($request['released_at'])) : '' ?></li>
                                <?php endif; ?>
                            </ol>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
