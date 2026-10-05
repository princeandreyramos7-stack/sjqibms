<?php
// Disaster Management family picker (check-in and relief forms): optionally a priority group (Senior, Under 5, PWD,
// Solo Parent; then "All Puroks" is allowed), then the Purok, then a resident from a compact dropdown served by
// disaster_family_lookup.php. Expects $connection, $values['resident_id'], $picker_selected (['name', 'meta'] or null),
// $field_class and $field_error. See assets/js/disaster.js.
?>
<div class="health-resident-picker" data-drr-family-picker>
    <input type="hidden" name="resident_id" value="<?= e($picker_selected ? (string) $values['resident_id'] : '') ?>" data-drr-family-id>
    <div class="drr-selected<?= $picker_selected ? '' : ' is-empty' ?>" data-drr-family-selected>
        <div><strong data-drr-family-name><?= $picker_selected ? e($picker_selected['name']) : 'No family selected' ?></strong><span data-drr-family-meta><?= $picker_selected ? e($picker_selected['meta']) : 'Select the Purok, then choose any member of the family.' ?></span></div>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-drr-family-change <?= $picker_selected ? '' : 'hidden' ?>>Change</button>
    </div>
    <div data-drr-family-search <?= $picker_selected ? 'hidden' : '' ?>>
        <div class="drr-search-grid has-group">
            <div><label class="form-label" for="drr-family-group">Priority group</label><select class="form-select" id="drr-family-group" data-drr-family-group data-summary-skip><option value="">All residents</option><?php foreach (disaster_vulnerable_groups() as $group_value => $group_label): ?><option value="<?= e($group_value) ?>"><?= e($group_label) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="drr-family-purok">Purok <span class="resident-required" aria-hidden="true">*</span></label><select class="form-select<?= $field_class('resident_id') ?>" id="drr-family-purok" data-drr-family-purok data-summary-skip><option value="">Select Purok</option><option value="all" disabled data-drr-all-puroks>All Puroks (with a priority group)</option><?php foreach (disaster_vulnerable_puroks($connection) as $purok_value): ?><option value="<?= e($purok_value) ?>"><?= e(residents_purok_label($purok_value)) ?></option><?php endforeach; ?></select></div>
            <div class="drr-search-box">
                <label class="form-label" for="drr-family-query">Resident <span class="resident-required" aria-hidden="true">*</span></label>
                <input class="form-control<?= $field_class('resident_id') ?>" type="search" id="drr-family-query" placeholder="Select a Purok first" autocomplete="off" maxlength="100" disabled data-drr-family-query data-summary-skip aria-controls="drr-family-list" aria-expanded="false">
                <div class="case-lookup-results drr-dropdown" id="drr-family-list" role="listbox" aria-label="Residents in the selected Purok" data-drr-family-results hidden></div>
            </div>
        </div>
        <p class="drr-hint drr-hint-below" data-drr-family-hint>Select the Purok, then pick any member of the family from the list.</p>
        <noscript><p class="resident-static">Choosing a family needs JavaScript. Please enable JavaScript.</p></noscript>
    </div>
    <?= $field_error('resident_id') ?>
</div>
