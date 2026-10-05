<?php
// Household assignment fields. Expects $households, $household, $field_class, $field_error; $household_allow_later defaults to true.
$household_allow_later = $household_allow_later ?? true;
$household_modes = ['existing' => 'Assign to existing household', 'new' => 'Create new household'] + ($household_allow_later ? ['later' => 'Assign later'] : []);
?>
<fieldset class="resident-section" data-household-fields>
    <legend>Household Information</legend>
    <div class="resident-choice-row" role="radiogroup" aria-label="Household option">
        <?php foreach ($household_modes as $mode => $label): ?>
            <label class="resident-choice"><input type="radio" name="household_mode" value="<?= e($mode) ?>" <?= $household['mode'] === $mode ? 'checked' : '' ?> data-household-mode> <?= e($label) ?></label>
        <?php endforeach; ?>
    </div>
    <?= $field_error('household_mode') ?>

    <div class="resident-household-panel" data-household-panel="existing">
        <?php if ($households === []): ?>
            <p class="resident-static">No households are registered yet. Choose <strong>Create new household</strong> instead.</p>
        <?php else: ?>
            <div class="resident-grid">
                <div class="resident-grid-full"><label class="form-label" for="household_filter">Find household</label><input class="form-control" type="search" id="household_filter" placeholder="Type a household number, head, address or Purok" data-household-filter="household_id" autocomplete="off"></div>
                <div class="resident-grid-full"><label class="form-label" for="household_id">Household</label><select class="form-select<?= $field_class('household_id') ?>" id="household_id" name="household_id" required><option value="">Select a household</option><?php foreach ($households as $option): ?><option value="<?= e((string) $option['id']) ?>" <?= (string) $household['household_id'] === (string) $option['id'] ? 'selected' : '' ?>><?= e(residents_household_label($option)) ?></option><?php endforeach; ?></select><?= $field_error('household_id') ?></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="resident-household-panel" data-household-panel="new">
        <p class="resident-static">The resident will not be made Household Head automatically. Assign a head later from Manage Household Assignment.</p>
        <div class="resident-grid">
            <div><label class="form-label" for="new_household_no">Household number</label><input class="form-control<?= $field_class('new_household_no') ?>" id="new_household_no" name="new_household_no" maxlength="50" value="<?= e($household['new']['household_no']) ?>" required><?= $field_error('new_household_no') ?></div>
            <div><label class="form-label" for="new_household_purok">Household Purok</label><select class="form-select<?= $field_class('new_household_purok') ?>" id="new_household_purok" name="new_household_purok" required><option value="">Select Purok</option><?php foreach (residents_purok_options() as $value => $label): ?><option value="<?= e((string) $value) ?>" <?= (string) $household['new']['purok'] === (string) $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $field_error('new_household_purok') ?></div>
            <div class="resident-grid-full"><label class="form-label" for="new_household_address">Household address</label><textarea class="form-control<?= $field_class('new_household_address') ?>" id="new_household_address" name="new_household_address" rows="2" maxlength="500" required><?= e($household['new']['address']) ?></textarea><?= $field_error('new_household_address') ?></div>
        </div>
    </div>

    <div class="resident-household-panel" data-household-panel="existing new">
        <div class="resident-grid">
            <div><label class="form-label" for="relationship_to_head">Relationship to Household Head</label><select class="form-select<?= $field_class('relationship_to_head') ?>" id="relationship_to_head" name="relationship_to_head"><option value="">Not specified</option><?php foreach (residents_relationships() as $relationship): ?><option value="<?= e($relationship) ?>" <?= $household['relationship'] === $relationship ? 'selected' : '' ?>><?= e($relationship) ?></option><?php endforeach; ?></select><?= $field_error('relationship_to_head') ?></div>
        </div>
    </div>

    <?php if ($household_allow_later): ?><p class="resident-static" data-household-panel="later">The profile will be saved with <strong>Household assignment pending</strong>. Status is not affected.</p><?php endif; ?>
</fieldset>
