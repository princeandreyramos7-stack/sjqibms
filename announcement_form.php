<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/announcements.php';
require_once __DIR__ . '/includes/sms.php';
announcements_require_manage();
$connection = db(); $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null; $record = $id ? announcements_find($connection, $id) : null;
if ($id && !$record) { http_response_code(404); exit('Announcement not found.'); }
// Archived announcements are read-only until unarchived; this also rejects submissions from forms opened before archival.
if ($record && $record['status'] === 'archived') redirect('announcement_view.php?id=' . $record['id']);
// Allowed actions come from the stored status, never client input: drafts may stay drafts or be published; published records are only updated in place.
$allowed_actions = !$record ? ['draft', 'publish'] : ($record['status'] === 'draft' ? ['draft', 'publish'] : ['update']);
$values = $record ? ['title' => $record['title'], 'body' => $record['body'], 'audience' => $record['audience'], 'target_purok' => $record['target_purok'] ?? '', 'category' => $record['category'] ?? 'general'] : ['title' => '', 'body' => '', 'audience' => 'public', 'target_purok' => '', 'category' => announcements_health_only() ? 'health' : 'general']; $errors = []; $field_errors = [];
$current_purok = $record['target_purok'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $errors[] = 'Your session token expired. Please try again.';
    $validated = announcements_validate($_POST, announcements_selectable_audiences($connection, $record['audience'] ?? null), $current_purok); $values = $validated['values']; $field_errors = $validated['field_errors']; $save = (string) ($_POST['save_action'] ?? '');
    if (!in_array($save, $allowed_actions, true)) $errors[] = 'Invalid save action.';
    if ($errors === [] && $field_errors === []) try { $connection->beginTransaction(); if ($record) {
        // Re-read and lock the row so a stale form cannot overwrite a status change made after it was opened.
        $lock = $connection->prepare('SELECT status FROM announcements WHERE id = :id FOR UPDATE'); $lock->execute(['id' => $id]); $current_status = $lock->fetchColumn();
        if ($current_status !== $record['status'] || $current_status !== (string) ($_POST['form_status'] ?? '')) { $connection->rollBack(); $errors[] = 'This announcement was changed by another action after you opened it. Please reload the page and try again.'; }
        else { $changed = $values['title'] !== $record['title'] || $values['body'] !== $record['body'] || $values['audience'] !== $record['audience'] || $values['target_purok'] !== ($record['target_purok'] ?? null) || $values['category'] !== ($record['category'] ?? 'general'); if ($changed) { $update = $connection->prepare('UPDATE announcements SET title = :title, body = :body, audience = :audience, target_purok = :target_purok, category = :category WHERE id = :id AND status = :status'); $update->execute([...$values, 'id' => $id, 'status' => $current_status]); announcements_history($connection, $id, 'updated', $current_status, $current_status); announcements_audit($connection, $id, 'announcement_updated'); }
        if ($save === 'publish') { $publish = $connection->prepare("UPDATE announcements SET status = 'published', published_at = :published_at WHERE id = :id AND status = 'draft'"); $publish->execute(['published_at' => date('Y-m-d H:i:s'), 'id' => $id]); announcements_history($connection, $id, 'published', 'draft', 'published'); announcements_audit($connection, $id, 'announcement_published'); create_announcement_notifications($connection, ['id' => $id, 'status' => 'published', ...$values]); } $saved = $id; } } else { $status = $save === 'publish' ? 'published' : 'draft'; $insert = $connection->prepare('INSERT INTO announcements (author_id, title, body, audience, target_purok, category, status, published_at) VALUES (:author_id, :title, :body, :audience, :target_purok, :category, :status, :published_at)'); $insert->execute([...$values, 'author_id' => current_user()['id'], 'status' => $status, 'published_at' => $status === 'published' ? date('Y-m-d H:i:s') : null]); $saved = (int) $connection->lastInsertId(); announcements_history($connection, $saved, 'created', null, $status); announcements_audit($connection, $saved, 'announcement_created'); if ($status === 'published') { create_announcement_notifications($connection, ['id' => $saved, 'status' => $status, ...$values]); } } if ($errors === []) { $connection->commit(); if ($save === 'publish') { sms_send_announcement($connection, ['id' => $saved, 'status' => 'published', ...$values]); } redirect('announcement_view.php?id=' . $saved); } } catch (Throwable) { if ($connection->inTransaction()) $connection->rollBack(); $errors[] = 'The announcement could not be saved.'; }
}
// Cancel returns to the management tab that lists this record, never the details page.
$cancel_url = !$record ? 'announcements.php' : 'announcements.php?tab=' . ($record['status'] === 'draft' ? 'drafts' : 'published');
// SMS note next to the buttons: how many numbers get the text for each audience (texts go out only when publishing).
$sms_counts = announcements_sms_counts($connection);
$sends_sms = in_array('publish', $allowed_actions, true);
$purok_options = residents_purok_options();
if ($current_purok !== null && $current_purok !== '' && !isset($purok_options[$current_purok])) $purok_options[$current_purok] = $current_purok . ' (earlier value)';
$field_class = static fn (string $field): string => isset($field_errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => '<div class="invalid-feedback" data-error-for="' . e($field) . '"' . (isset($field_errors[$field]) ? '' : ' hidden') . '>' . e($field_errors[$field] ?? '') . '</div>';
$page_title = $record ? 'Edit Announcement' : 'New Announcement'; $active_page = 'announcements'; require __DIR__ . '/layout/header.php';
?><div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?><main class="content"><section class="dashboard-panel announcement-form-panel">
    <div class="page-heading"><div><h1><?= e($page_title) ?></h1><p>Create or update a barangay announcement.</p></div></div>
    <?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php elseif ($field_errors): ?><div class="alert alert-danger">Please correct the highlighted fields.</div><?php endif; ?>
    <form method="post" novalidate data-announcement-form data-sms-counts="<?= e(json_encode($sms_counts, JSON_THROW_ON_ERROR)) ?>" data-sms-limit="480" data-sms-test="<?= SMS_TEST_MODE ? '1' : '0' ?>">
        <?= csrf_field() ?><?php if ($record): ?><input type="hidden" name="form_status" value="<?= e($record['status']) ?>"><?php endif; ?>
        <div class="mb-3"><label class="form-label" for="title">Title</label><input class="form-control<?= $field_class('title') ?>" id="title" name="title" maxlength="200" value="<?= e($values['title']) ?>" data-required="Title is required."><?= $field_error('title') ?></div>
        <div class="mb-3"><label class="form-label" for="body">Content</label><textarea class="form-control<?= $field_class('body') ?>" id="body" name="body" rows="8" maxlength="10000" data-required="Content is required."><?= e($values['body']) ?></textarea><?= $field_error('body') ?>
            <?php if ($sends_sms): ?><div class="form-text" data-sms-length aria-live="polite"></div><?php endif; ?></div>
        <div class="row g-3 mb-4">
            <div class="col-md-6"><label class="form-label" for="audience">Target audience</label><select class="form-select<?= $field_class('audience') ?>" id="audience" name="audience" data-audience><?php foreach (announcements_selectable_audiences($connection, $record['audience'] ?? null) as $audience_value => $audience_label): ?><option value="<?= e($audience_value) ?>" <?= $values['audience'] === $audience_value ? 'selected' : '' ?>><?= e($audience_label) ?></option><?php endforeach; ?></select><?= $field_error('audience') ?>
                <div class="form-text" data-audience-help></div></div>
            <?php if (!announcements_health_only()): ?><div class="col-md-6"><label class="form-label" for="category">Category</label><select class="form-select<?= $field_class('category') ?>" id="category" name="category"><?php foreach (announcements_categories() as $category_value => $category_label): ?><option value="<?= e($category_value) ?>" <?= $values['category'] === $category_value ? 'selected' : '' ?>><?= e($category_label) ?></option><?php endforeach; ?></select><?= $field_error('category') ?><div class="form-text">Health announcements are also shown to Health Workers.</div></div><?php else: ?><div class="col-md-6"><span class="form-label d-block">Category</span><p class="resident-static">Health</p></div><?php endif; ?>
            <div class="col-md-6" data-purok-field<?= $values['audience'] === 'purok' ? '' : ' hidden' ?>><label class="form-label" for="target_purok">Purok</label><select class="form-select<?= $field_class('target_purok') ?>" id="target_purok" name="target_purok" data-required="Please select a purok." data-required-when="audience=purok"><option value="">Select purok</option><?php foreach ($purok_options as $value => $label): ?><option value="<?= e((string) $value) ?>" <?= (string) $values['target_purok'] === (string) $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('target_purok') ?></div>
        </div>
        <?php if ($sends_sms): ?><p class="resident-static" data-sms-note aria-live="polite"></p><?php endif; ?>
        <div class="form-actions"><?php if (in_array('update', $allowed_actions, true)): ?><button class="btn btn-primary" type="submit" name="save_action" value="update" data-form-confirm="save">Save Changes</button><?php else: ?><button class="btn btn-outline-success" type="submit" name="save_action" value="draft" data-form-confirm="<?= $record ? 'draft-edit' : 'draft-new' ?>">Save as Draft</button><button class="btn btn-primary" type="submit" name="save_action" value="publish" data-form-confirm="<?= $record ? 'publish-edit' : 'publish-new' ?>">Publish Now</button><?php endif; ?><a class="btn btn-light" href="<?= e($cancel_url) ?>" data-form-confirm="discard">Cancel</a></div>
    </form>
    <dialog class="announcement-confirm-dialog" id="announcement-form-dialog" aria-labelledby="announcement-form-dialog-heading" aria-describedby="announcement-form-dialog-message"><h2 id="announcement-form-dialog-heading" data-form-dialog-heading></h2><p id="announcement-form-dialog-message" data-form-dialog-message></p><div class="announcement-confirm-actions"><button class="btn btn-light" type="button" data-form-dialog-dismiss>Continue Editing</button><button class="btn announcement-confirm-submit" type="button" data-form-dialog-confirm></button></div></dialog>
</section></main>
<script>
// Announcement form: the Purok list appears only for "Specific Purok"; a short note explains the chosen audience; the
// SMS note says how many numbers get the text when it is published and warns when the text will be cut (480 characters,
// title included); missing fields get a red message under the field (checked again on the server). This runs before the
// confirmation dialog, which opens only when the form is complete.
(() => {
    const form = document.querySelector('[data-announcement-form]');
    if (!form) return;
    const audience = form.querySelector('[data-audience]');
    const purokField = form.querySelector('[data-purok-field]');
    const purok = form.querySelector('#target_purok');
    const help = form.querySelector('[data-audience-help]');
    const smsNote = form.querySelector('[data-sms-note]');
    const smsLength = form.querySelector('[data-sms-length]');
    const counts = JSON.parse(form.dataset.smsCounts || '{}');
    const limit = Number(form.dataset.smsLimit) || 480;
    const helpText = {
        public: 'Anyone can read it, including visitors on the barangay website.',
        all_residents: 'Only residents who are signed in to the Resident Portal can read it.',
        purok: 'Only signed-in residents of the selected Purok can read it.',
        barangay_officials: 'Only barangay officials can read it.',
    };
    const slot = (field) => form.querySelector(`[data-error-for="${field.name}"]`);
    const showError = (field, text) => { field.classList.add('is-invalid'); const box = slot(field); if (box) { box.textContent = text; box.hidden = false; } };
    const clearError = (field) => { field.classList.remove('is-invalid'); const box = slot(field); if (box) { box.textContent = ''; box.hidden = true; } };
    const plural = (n) => `${n} mobile number${n === 1 ? '' : 's'}`;
    const update = () => {
        const value = audience.value;
        purokField.hidden = value !== 'purok';
        if (value !== 'purok') clearError(purok);
        help.textContent = helpText[value] || '';
        if (smsNote) {
            let n = null;
            if (value === 'public' || value === 'all_residents') n = counts[value];
            else if (value === 'purok') n = purok.value ? (counts.purok || {})[purok.value] : null;
            smsNote.textContent = n === null || n === undefined
                ? (value === 'purok' ? 'Select a Purok to see how many numbers will get the text.' : 'No text message is sent for this audience.')
                : `Publishing also sends this as a text message to ${plural(n)}.` + (form.dataset.smsTest === '1' ? ' (SMS TEST MODE: no text is actually sent yet.)' : '');
        }
        if (smsLength) {
            const length = (form.querySelector('#title').value.trim() + '\n\n' + form.querySelector('#body').value.trim().replace(/\s+/g, ' ')).length;
            smsLength.textContent = length > limit ? `Text message: ${length} of ${limit} characters. The text will be cut at ${limit} characters; the full announcement stays on the website.` : `Text message: ${length} of ${limit} characters.`;
            smsLength.classList.toggle('text-danger', length > limit);
        }
    };
    audience.addEventListener('change', update);
    purok.addEventListener('change', () => { clearError(purok); update(); });
    form.addEventListener('input', (event) => { if (event.target.name) clearError(event.target); update(); });
    // Runs before the confirmation dialog (that listener is on the document): stop it when a field is missing.
    form.addEventListener('click', (event) => {
        const button = event.target.closest('button[name="save_action"]');
        if (!button) return;
        let first = null;
        form.querySelectorAll('[data-required]').forEach((field) => {
            const when = field.dataset.requiredWhen;
            if (when) { const [name, value] = when.split('='); if (form.querySelector(`[name="${name}"]`).value !== value) return; }
            if (field.value.trim() === '') { showError(field, field.dataset.required); first = first || field; }
        });
        if (first) { event.preventDefault(); event.stopPropagation(); first.scrollIntoView({ behavior: 'smooth', block: 'center' }); first.focus({ preventScroll: true }); }
    });
    update();
    const firstError = form.querySelector('.is-invalid');
    if (firstError) { firstError.scrollIntoView({ block: 'center' }); firstError.focus({ preventScroll: true }); }
})();
</script>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
