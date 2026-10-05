<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/assistance.php';
assistance_require_manage();
$connection = db();
if (!assistance_ready($connection)) { flash('assistance_error', 'Relief & Assistance needs its database update first.'); redirect('assistance.php'); }

// Give Assistance (new record) or Edit (?id=). A new record is Given now (stock deducted) or Scheduled (nothing deducted
// until it is marked as Given). A Scheduled record can be edited fully; a Given record only its details (purpose,
// receiver, relationship, document no., remarks, source details, incident and disbursement link) — a different item,
// quantity, amount or beneficiary needs Void and a new record. Void records cannot be edited.
$id = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$entry = $id ? assistance_find($connection, $id) : null;
if ($id && ($entry === null || $entry['status'] === 'void')) { http_response_code(404); exit('Record not found or cannot be edited.'); }
$is_edit = $entry !== null;
$limited = $is_edit && $entry['status'] === 'given';
$values = $is_edit ? assistance_values_from($entry, assistance_items($connection, (int) $entry['id'])) : assistance_input([
    // New form: All residents by default; a link for one family (evacuation) opens it for that household or resident.
    'beneficiary_type' => ctype_digit((string) ($_GET['household'] ?? '')) ? 'household' : (ctype_digit((string) ($_GET['resident'] ?? '')) ? 'resident' : 'all'),
    'resident_id' => (string) ($_GET['resident'] ?? ''), 'household_id' => (string) ($_GET['household'] ?? ''),
    'incident_id' => (string) ($_GET['incident'] ?? ''), 'given_on' => date('Y-m-d'),
]);
$errors = [];
$form_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['form_token'] ?? '');
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $form_error = 'Your session expired. Please review the form and submit again.';
        $values = assistance_input($_POST);
    } elseif (!$is_edit && !assistance_form_token_valid($token)) {
        $form_error = 'This form was already submitted. Check the Relief & Assistance list before recording it again.';
        $values = assistance_input($_POST);
    } else {
        try {
            $result = $is_edit ? assistance_update($connection, (int) $entry['id'], $_POST, (string) ($_POST['form_updated_at'] ?? '')) : assistance_create($connection, $_POST);
            if (isset($result['redirect'])) {
                assistance_form_token_used($token);
                flash('assistance_success', $result['message']);
                redirect($result['redirect']);
            }
            if (isset($result['id'])) {
                if (!$is_edit) assistance_form_token_used($token);
                flash('assistance_success', $result['message']);
                redirect('assistance_view.php?id=' . $result['id']);
            }
            $errors = $result['errors'];
            $values = $result['values'];
            if (isset($errors['form'])) { $form_error = $errors['form']; unset($errors['form']); }
        } catch (PDOException) {
            $form_error = 'The record could not be saved. No changes were made to the records or to Inventory stock.';
            $values = assistance_input($_POST);
        }
    }
}

// Beneficiary shown in the picker.
$selected = null;
$household_members = null;   // current members, for the household "Received by" list
if ($values['beneficiary_type'] === 'household' && ctype_digit($values['household_id'])) {
    $household = assistance_household($connection, (int) $values['household_id']);
    if ($household) $household_members = assistance_household_members($connection, (int) $household['id']);
    if ($household) $selected = ['name' => assistance_household_label($household), 'meta' => residents_purok_label((string) $household['purok']) . ' · ' . (int) $household['members'] . ' member' . ((int) $household['members'] === 1 ? '' : 's'), 'groups' => assistance_beneficiary_groups('household', $household), 'receiver' => trim(residents_full_name($household))];
} elseif ($values['beneficiary_type'] === 'resident' && ctype_digit($values['resident_id'])) {
    $resident = assistance_resident($connection, (int) $values['resident_id']);
    if ($resident && ($resident['status'] === 'active' || $limited)) $selected = ['name' => residents_full_name($resident), 'meta' => residents_purok_label((string) $resident['purok']), 'groups' => assistance_beneficiary_groups('resident', $resident), 'receiver' => residents_full_name($resident)];
}
if ($limited) $selected['groups'] = assistance_group_list((string) $entry['priority_groups']);
if (!$is_edit && $_SERVER['REQUEST_METHOD'] !== 'POST' && $selected && $values['received_by'] === '') $values['received_by'] = $selected['receiver'];

$incidents = disaster_recovery_incidents($connection, $entry['incident_id'] ?? null);
$items = assistance_plannable_items($connection);
$finance_options = assistance_finance_options($connection, $entry ? (int) $entry['id'] : null, $entry && $entry['finance_transaction_id'] !== null ? (int) $entry['finance_transaction_id'] : null);
$lines = $values['lines'];
while (count($lines) < ASSISTANCE_MAX_LINES) $lines[] = ['item_id' => '', 'quantity' => ''];
$filled_lines = max(1, count($values['lines']));
$stored_lines = $limited ? assistance_items($connection, (int) $entry['id']) : [];
$field_class = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$field_error = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
$val = static fn (string $field): string => is_scalar($values[$field] ?? null) ? (string) $values[$field] : '';
$form_token = $is_edit ? '' : assistance_form_token();
$page_title = $is_edit ? 'Edit ' . $entry['reference_no'] : 'Give Assistance'; $active_page = 'assistance';
$page_styles = ['assets/css/disaster.css', 'assets/css/assistance.css'];
$back = $is_edit ? 'assistance_view.php?id=' . $entry['id'] : 'assistance.php';
require __DIR__ . '/layout/header.php';
?>
<div class="app-shell"><?php require __DIR__ . '/layout/sidebar.php'; ?><div class="app-main"><?php require __DIR__ . '/layout/topbar.php'; ?>
<main class="content">
<section class="dashboard-panel resident-form-panel">
    <a class="announcement-back" href="<?= e($back) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true"><span aria-hidden="true">&larr;</span> Back</a>
    <div class="page-heading"><div>
        <h1><?= $is_edit ? 'Edit ' . e($entry['reference_no']) : 'Give Assistance' ?></h1>
        <p><?php if ($limited): ?>This record is Given. Only its details can be changed; to change the beneficiary, item, quantity or amount, void it and record it again.<?php elseif ($is_edit): ?>This record is Scheduled. Everything can still be changed; nothing is deducted from Inventory until it is marked as Given.<?php else: ?>Record relief or assistance for all residents, PWD, Solo Parents or a household. The reference number (AST-<?= e(date('Y')) ?>-####) is assigned on save.<?php endif; ?></p>
    </div></div>
    <?php if ($form_error !== null): ?><div class="alert alert-danger" role="alert"><?= e($form_error) ?></div><?php elseif ($errors !== []): ?><div class="alert alert-danger" role="alert"><?= isset($errors['confirm_duplicate']) && count($errors) === 1 ? 'This beneficiary already has a similar record. Please confirm below.' : 'Please correct the highlighted fields.' ?></div><?php endif; ?>
    <form method="post" data-ast-form data-ast-limited="<?= $limited ? '1' : '0' ?>">
        <?= csrf_field() ?>
        <?php if ($is_edit): ?><input type="hidden" name="id" value="<?= e((string) $entry['id']) ?>"><input type="hidden" name="form_updated_at" value="<?= e($entry['updated_at']) ?>"><?php else: ?><input type="hidden" name="form_token" value="<?= e($form_token) ?>"><?php endif; ?>

        <?php if (!$is_edit): ?>
        <fieldset class="resident-section">
            <legend>Record As</legend>
            <div class="ast-choice-row" role="radiogroup" aria-label="Record as">
                <label class="ast-choice"><input type="radio" name="record_as" value="given" <?= $values['record_as'] === 'given' ? 'checked' : '' ?> data-ast-record-as> <span><strong>Given now</strong><small>Items are deducted from Inventory today.</small></span></label>
                <label class="ast-choice"><input type="radio" name="record_as" value="scheduled" <?= $values['record_as'] === 'scheduled' ? 'checked' : '' ?> data-ast-record-as> <span><strong>Scheduled</strong><small>Planned; nothing is deducted until it is marked as Given.</small></span></label>
            </div>
            <p class="drr-hint" data-ast-group-note hidden>All residents, PWD and Solo Parent are recorded as Scheduled (a payout list). Mark each person as Given on the Claim List when they claim it.</p>
        </fieldset>
        <?php endif; ?>

        <fieldset class="resident-section">
            <legend>Beneficiary</legend>
            <?php if ($limited): ?>
                <input type="hidden" name="beneficiary_type" value="<?= e($values['beneficiary_type']) ?>">
                <p class="resident-static"><strong><?= e((string) ($selected['name'] ?? assistance_beneficiary_label($entry))) ?></strong> · <?= e(assistance_beneficiary_purok($entry)) ?> <?= assistance_group_badges((string) $entry['priority_groups']) ?></p>
            <?php else: ?>
                <span class="form-label d-block">Beneficiary <span class="resident-required" aria-hidden="true">*</span></span>
                <div class="ast-choice-row" role="radiogroup" aria-label="Beneficiary">
                    <?php foreach (['all' => 'All residents', 'pwd' => 'PWD', 'solo_parent' => 'Solo Parent', 'resident' => 'One resident', 'household' => 'Household'] as $choice_value => $choice_label): if ($choice_value === 'resident' && $values['beneficiary_choice'] !== 'resident') continue;   // only for a link or record made for one resident ?>
                        <label class="ast-choice is-compact"><input type="radio" name="beneficiary_type" value="<?= e($choice_value) ?>" <?= $values['beneficiary_choice'] === $choice_value ? 'checked' : '' ?> data-ast-type> <span><strong><?= e($choice_label) ?></strong></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="health-resident-picker" data-ast-picker>
                    <input type="hidden" name="resident_id" value="<?= e($values['beneficiary_type'] === 'resident' && $selected ? $values['resident_id'] : '') ?>" data-ast-id="resident">
                    <input type="hidden" name="household_id" value="<?= e($values['beneficiary_type'] === 'household' && $selected ? $values['household_id'] : '') ?>" data-ast-id="household">
                    <div class="drr-selected<?= $selected ? '' : ' is-empty' ?>" data-ast-selected>
                        <div><strong data-ast-name><?= $selected ? e($selected['name']) : 'No beneficiary selected' ?></strong><span data-ast-meta><?= $selected ? e($selected['meta']) : 'Type a name, or click the box to see the list.' ?></span><span class="ast-selected-tags" data-ast-tags><?= $selected ? assistance_group_badges($selected['groups']) : '' ?></span></div>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-ast-change <?= $selected ? '' : 'hidden' ?>>Change</button>
                    </div>
                    <div data-ast-search <?= $selected ? 'hidden' : '' ?>>
                        <?php $by_group = in_array($values['beneficiary_choice'], ['all', 'pwd', 'solo_parent'], true); ?>
                        <div class="drr-search-grid">
                            <div><label class="form-label" for="ast-purok">Purok <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('beneficiary') ?>" id="ast-purok" name="group_purok" data-ast-purok data-summary-skip><option value="">Select Purok</option><option value="all" <?= $values['group_purok'] === 'all' || $values['group_purok'] === '' ? 'selected' : '' ?> data-ast-all-puroks>All Puroks</option><?php foreach (disaster_vulnerable_puroks($connection) as $purok_value): ?><option value="<?= e($purok_value) ?>" <?= $values['group_purok'] === (string) $purok_value ? 'selected' : '' ?>><?= e(residents_purok_label($purok_value)) ?></option><?php endforeach; ?></select></div>
                            <div class="drr-search-box" data-ast-search-box>
                                <label class="form-label" for="ast-query" data-ast-query-label><?= ['household' => 'Household', 'pwd' => 'PWD', 'solo_parent' => 'Solo Parent'][$values['beneficiary_choice']] ?? 'Resident' ?> <span class="resident-required" aria-hidden="true">*</span></label>
                                <input class="form-control<?= $field_class('beneficiary') ?>" type="search" id="ast-query" placeholder="Type a name, or click to see the list" autocomplete="off" maxlength="100" data-ast-query data-summary-skip aria-controls="ast-list" aria-expanded="false">
                                <div class="case-lookup-results drr-dropdown" id="ast-list" role="listbox" aria-label="Beneficiaries" data-ast-results hidden></div>
                            </div>
                        </div>
                        <p class="drr-hint drr-hint-below" data-ast-hint <?= $by_group ? 'hidden' : '' ?>><?= $by_group ? '' : 'Type a name, or click the box to see the list. The Purok is optional.' ?></p>
                        <div class="ast-bulk" data-ast-bulk hidden aria-live="polite">
                            <p class="ast-bulk-count" data-ast-bulk-count></p>
                            <ul class="ast-bulk-names" data-ast-bulk-names></ul>
                        </div>
                        <noscript><p class="resident-static">Choosing a beneficiary needs JavaScript. Please enable JavaScript.</p></noscript>
                    </div>
                </div>
            <?php endif; ?>
            <?= $field_error('beneficiary') ?>
            <div class="resident-grid drr-family-extra">
                <div><label class="form-label" for="incident_id">Related incident</label><select class="form-select<?= $field_class('incident_id') ?>" id="incident_id" name="incident_id" data-summary-label="Incident"><option value="">None (not disaster relief)</option><?php foreach ($incidents as $incident): ?><option value="<?= e((string) $incident['id']) ?>" <?= $val('incident_id') === (string) $incident['id'] ? 'selected' : '' ?>><?= e(disaster_incident_label($incident)) ?></option><?php endforeach; ?></select><div class="form-text">For relief after a typhoon, flood or other incident recorded in Disaster Management.</div><?= $field_error('incident_id') ?></div>
            </div>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Assistance</legend>
            <?php if ($limited): ?>
                <dl class="activity-detail-list ast-static-list">
                    <div><dt>Category</dt><dd><?= e(assistance_category_label($entry['assistance_type'])) ?></dd></div>
                    <div><dt>Form</dt><dd><?= e(assistance_forms()[assistance_form_of($entry)] ?? '') ?></dd></div>
                    <div><dt>Given</dt><dd><?= e(assistance_given_text($entry, $stored_lines)) ?></dd></div>
                    <div><dt>Source</dt><dd><?= e(assistance_sources()[(string) $entry['source']] ?? 'Not recorded') ?></dd></div>
                </dl>
                <input type="hidden" name="assistance_form" value="<?= e(assistance_form_of($entry)) ?>" data-ast-form-fixed>
                <input type="hidden" name="source" value="<?= e((string) $entry['source']) ?>" data-ast-source-fixed>
            <?php else: ?>
                <div class="resident-grid">
                    <div><label class="form-label" for="assistance_type">Assistance category <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('assistance_type') ?>" id="assistance_type" name="assistance_type" required data-ast-category data-summary-label="Category"><option value="">Select category</option><?php foreach (assistance_categories() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('assistance_type') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><div class="form-text ast-mismatch" data-ast-category-hint hidden></div><?= $field_error('assistance_type') ?></div>
                    <div><span class="form-label d-block">Form of assistance <span class="resident-required" aria-hidden="true">*</span></span>
                        <div class="ast-choice-row" role="radiogroup" aria-label="Form of assistance">
                            <?php foreach (assistance_forms() as $value => $label): ?><label class="ast-choice is-compact"><input type="radio" name="assistance_form" value="<?= e($value) ?>" <?= $val('assistance_form') === $value ? 'checked' : '' ?> required data-ast-form-choice> <span><strong><?= e($label) ?></strong></span></label><?php endforeach; ?>
                        </div><?= $field_error('assistance_form') ?></div>
                    <div><label class="form-label" for="source">Source <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('source') ?>" id="source" name="source" required data-ast-source data-summary-label="Source"><option value="">Select source</option><?php foreach (assistance_sources() as $value => $label): ?><option value="<?= e($value) ?>" <?= $val('source') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('source') ?></div>
                </div>
            <?php endif; ?>
            <div class="resident-grid">
                <div data-ast-source-details><label class="form-label" for="source_details">Source details<span class="resident-required" aria-hidden="true" data-ast-details-required> *</span></label><input class="form-control<?= $field_class('source_details') ?>" id="source_details" name="source_details" maxlength="150" value="<?= e($val('source_details')) ?>" placeholder="Name of the donor, NGO, agency or program" data-summary-label="Source details"><?= $field_error('source_details') ?></div>
            </div>

            <?php if (!$limited): ?>
            <div class="ast-block" data-ast-cash-block>
                <div class="resident-grid">
                    <div><label class="form-label" for="cash_amount">Amount (₱) <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control ast-amount<?= $field_class('cash_amount') ?>" id="cash_amount" name="cash_amount" inputmode="decimal" maxlength="16" value="<?= e($val('cash_amount')) ?>" placeholder="0.00" autocomplete="off" data-ast-amount data-summary-label="Amount"><?= $field_error('cash_amount') ?></div>
                </div>
            </div>
            <div class="ast-block" data-ast-items-block>
                <p class="drr-hint">Items come from Inventory supplies. <span data-ast-stock-note>When Given, the quantity cannot be more than the stock on hand.</span></p>
                <div class="drr-item-lines">
                    <?php foreach ($lines as $index => $line): ?>
                        <div class="drr-item-line ast-item-line" data-ast-line <?= $index >= $filled_lines ? 'hidden' : '' ?>>
                            <select class="form-select<?= $field_class('items') ?>" name="item_id[]" aria-label="Item <?= $index + 1 ?>" data-ast-item><option value="">Select item</option><?php foreach ($items as $item): ?><option value="<?= e((string) $item['id']) ?>" data-stock="<?= e((string) (int) $item['quantity']) ?>" data-unit="<?= e($item['unit']) ?>" <?= (string) $line['item_id'] === (string) $item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?> (<?= e(number_format((int) $item['quantity']) . ' ' . $item['unit']) ?> on hand)</option><?php endforeach; ?></select>
                            <input class="form-control<?= $field_class('items') ?>" type="number" name="quantity[]" min="1" step="1" inputmode="numeric" value="<?= e((string) $line['quantity']) ?>" placeholder="Qty" aria-label="Quantity <?= $index + 1 ?>" data-ast-qty>
                            <span class="ast-unit" data-ast-unit aria-live="polite"></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-sm btn-outline-secondary ast-add-line" type="button" data-ast-add-line>Add item</button>
                <?php if ($items === []): ?><p class="drr-hint">There are no supplies in Inventory yet. Add them in Inventory first.</p><?php endif; ?>
                <?= $field_error('items') ?>
            </div>
            <?php endif; ?>

            <div class="ast-block" data-ast-finance-block>
                <div class="resident-grid">
                    <div><label class="form-label" for="finance_transaction_id">Financial record (disbursement)</label><select class="form-select<?= $field_class('finance_transaction_id') ?>" id="finance_transaction_id" name="finance_transaction_id" data-summary-label="Disbursement"><option value="">Not linked yet</option><?php foreach ($finance_options as $option): ?><option value="<?= e((string) $option['id']) ?>" <?= $val('finance_transaction_id') === (string) $option['id'] ? 'selected' : '' ?>><?= e($option['reference_no']) ?> · <?= e($option['release_date'] ? disaster_format_date($option['release_date']) : '') ?> · <?= e(assistance_peso($option['amount'])) ?> (<?= e(assistance_peso($option['remaining'])) ?> not yet linked)</option><?php endforeach; ?></select><div class="form-text">For cash from the Barangay Fund: the released disbursement (DV) in Financial Management that paid it. Optional; it can be linked later.</div><?= $field_error('finance_transaction_id') ?></div>
                </div>
            </div>
            <div class="resident-grid">
                <div class="resident-grid-full"><label class="form-label" for="purpose">Purpose / reason <span class="resident-required" aria-hidden="true">*</span></label><textarea class="form-control<?= $field_class('purpose') ?>" id="purpose" name="purpose" rows="2" minlength="<?= e((string) ASSISTANCE_PURPOSE_MIN) ?>" maxlength="255" required placeholder="e.g. Ayuda para sa PWD – September 2026; Relief after Typhoon Kristine" data-summary-label="Purpose"><?= e($val('purpose')) ?></textarea><div class="form-text">At least <?= e((string) ASSISTANCE_PURPOSE_MIN) ?> characters. It is printed on the acknowledgment and names the payout list on the Claim List.</div><?= $field_error('purpose') ?></div>
            </div>
        </fieldset>

        <fieldset class="resident-section">
            <legend>Receiving</legend>
            <div class="resident-grid">
                <?php if ($limited): ?>
                    <div><span class="form-label d-block">Date given</span><p class="resident-static"><?= e(disaster_format_date($entry['given_on'])) ?></p></div>
                <?php else: ?>
                    <div><label class="form-label" for="given_on" data-ast-date-label><?= $values['record_as'] === 'scheduled' ? 'Scheduled date' : 'Date given' ?> <span class="resident-required" aria-hidden="true">*</span></label><input class="form-control<?= $field_class('given_on') ?>" type="date" id="given_on" name="given_on" value="<?= e($val('given_on')) ?>" <?= $values['record_as'] === 'given' ? 'max="' . e(date('Y-m-d')) . '"' : '' ?> required data-ast-date data-today="<?= e(date('Y-m-d')) ?>" data-summary-label="Date"><?= $field_error('given_on') ?></div>
                <?php endif; ?>
                <p class="drr-hint resident-grid-full" data-ast-bulk-receiver hidden>Each person in the list gets their own record and acknowledgment; who received it is entered on the Claim List.</p>
                <div data-ast-receiver-field><label class="form-label" for="received_by">Received by<span class="resident-required" aria-hidden="true" data-ast-received-required> *</span></label><select class="form-select ast-receiver-pick" aria-label="Household member who received it" data-ast-receiver-pick <?= $household_members === null ? 'hidden' : '' ?>><?php if ($household_members !== null): ?><?php foreach ($household_members as $member): ?><option value="<?= e($member) ?>" <?= mb_strtolower($member) === mb_strtolower($val('received_by')) ? 'selected' : '' ?>><?= e($member) ?></option><?php endforeach; ?><option value="" <?= $val('received_by') !== '' && !in_array(mb_strtolower($val('received_by')), array_map('mb_strtolower', $household_members), true) ? 'selected' : '' ?>>Other (representative)</option><?php endif; ?></select><input class="form-control<?= $field_class('received_by') ?>" id="received_by" name="received_by" maxlength="150" value="<?= e($val('received_by')) ?>" placeholder="Name of the person who received it" data-ast-received data-beneficiary="<?= e((string) ($selected['receiver'] ?? '')) ?>" data-summary-label="Received by"><div class="form-text" data-ast-received-help>Filled with the beneficiary's name; change it if a representative received it.</div><?= $field_error('received_by') ?></div>
                <div data-ast-relationship><label class="form-label" for="receiver_relationship">Relationship to beneficiary</label><input class="form-control<?= $field_class('receiver_relationship') ?>" id="receiver_relationship" name="receiver_relationship" maxlength="60" value="<?= e($val('receiver_relationship')) ?>" placeholder="e.g. Daughter" data-summary-label="Relationship"><?= $field_error('receiver_relationship') ?></div>
                <div><label class="form-label" for="document_no">Reference / document no.</label><input class="form-control<?= $field_class('document_no') ?>" id="document_no" name="document_no" maxlength="60" value="<?= e($val('document_no')) ?>" placeholder="Voucher, acknowledgment or program no." data-summary-label="Document no."><?= $field_error('document_no') ?></div>
                <div class="resident-grid-full"><label class="form-label" for="remarks">Remarks</label><input class="form-control<?= $field_class('remarks') ?>" id="remarks" name="remarks" maxlength="500" value="<?= e($val('remarks')) ?>"><?= $field_error('remarks') ?></div>
            </div>
        </fieldset>

        <?php if (isset($errors['confirm_duplicate']) || $values['confirm_duplicate']): ?>
            <div class="dashboard-status warning drr-duplicate" role="alert">
                <?php if (isset($errors['confirm_duplicate'])): ?><p><?= e($errors['confirm_duplicate']) ?></p><?php endif; ?>
                <label class="form-check"><input class="form-check-input" type="checkbox" name="confirm_duplicate" value="1" <?= $values['confirm_duplicate'] ? 'checked' : '' ?>> <span class="form-check-label">Record it again for this beneficiary</span></label>
            </div>
        <?php endif; ?>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" data-ast-submit data-form-confirm="custom" data-dialog-summary="form" data-dialog-heading="<?= $is_edit ? 'Save changes to this record?' : 'Record this assistance?' ?>" data-dialog-message="<?= $is_edit ? 'The changes are recorded in the audit log.' : 'Given in-kind items are deducted from Inventory stock. Records are never deleted; a mistake is voided with a reason.' ?>" data-dialog-confirm="<?= $is_edit ? 'Save Changes' : 'Record Assistance' ?>" data-dialog-dismiss="Cancel"><?= $is_edit ? 'Save Changes' : 'Record Assistance' ?></button>
            <a class="btn btn-light" href="<?= e($back) ?>" data-form-confirm="custom" data-dialog-heading="Discard changes?" data-dialog-message="Are you sure you want to leave this form? Any unsaved changes will be lost." data-dialog-confirm="Discard Changes" data-dialog-danger="true">Cancel</a>
        </div>
    </form>
    <?php require __DIR__ . '/layout/form_confirm_dialog.php'; ?>
</section>
</main>
<?php require __DIR__ . '/layout/footer.php'; ?></div></div>
<script src="assets/js/assistance.js?v=<?= e((string) @filemtime(__DIR__ . '/assets/js/assistance.js')) ?>"></script>
