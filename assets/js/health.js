// Health record form: resident picker (choose the Purok first, then pick an active resident of that Purok from a compact
// dropdown served by health_resident_lookup.php) and the follow-up date field, shown when the status is Follow-up.
// The server validates everything again on save.
(() => {
    // The health record form, or any Health program form that has the resident picker (Maternal, Immunization, …).
    const form = document.querySelector('[data-health-form]') || document.querySelector('[data-health-resident]')?.closest('form');
    if (!form) return;

    // ── Resident picker (only while choosing the resident; the picker's data-health-filter narrows the list) ──
    const picker = form.querySelector('[data-health-resident]');
    if (picker) {
    const hiddenId = picker.querySelector('[data-health-resident-id]');
    const selected = picker.querySelector('[data-health-selected]');
    const search = picker.querySelector('[data-health-search]');
    const purok = picker.querySelector('[data-health-purok]');
    const query = picker.querySelector('[data-health-query]');
    const results = picker.querySelector('[data-health-results]');
    const hint = picker.querySelector('[data-health-hint]');
    const change = picker.querySelector('[data-health-change]');
    let timer = 0;
    let sequence = 0;

    const open = () => { if (purok.value !== '') { results.hidden = false; query.setAttribute('aria-expanded', 'true'); } };
    const close = () => { results.hidden = true; query.setAttribute('aria-expanded', 'false'); };
    const note = (text) => {
        const line = document.createElement('p');
        line.className = 'case-lookup-empty';
        line.textContent = text;
        return line;
    };

    const choose = (item) => {
        hiddenId.value = String(item.id);
        selected.querySelector('[data-health-selected-name]').textContent = item.name;
        selected.querySelector('[data-health-selected-meta]').textContent = item.meta;
        selected.classList.remove('is-empty');
        change.hidden = false;
        search.hidden = true;
        results.replaceChildren();
        close();
        query.value = '';
    };

    // Residents of the chosen Purok: all of them (first 50, A–Z), or those whose name matches 2+ typed characters.
    const load = async (term) => {
        if (purok.value === '') { results.replaceChildren(); close(); return; }
        const current = ++sequence;
        try {
            const response = await fetch(`health_resident_lookup.php?purok=${encodeURIComponent(purok.value)}&q=${encodeURIComponent(term)}&filter=${encodeURIComponent(picker.dataset.healthFilter || '')}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = response.ok ? await response.json() : { results: [] };
            if (current !== sequence) return;
            const items = Array.isArray(data.results) ? data.results : [];
            const total = Number(data.total) || items.length;
            hint.textContent = data.searching
                ? `${total} matching resident${total === 1 ? '' : 's'} in ${data.purok || 'this Purok'}.`
                : `${total} registered active resident${total === 1 ? '' : 's'} in ${data.purok || 'this Purok'}. Click the box to see the list, or type a name.`;
            if (items.length === 0) {
                results.replaceChildren(note(data.searching ? 'No matching resident in this Purok. Check the spelling or the Purok.' : 'No active residents are registered in this Purok.'));
                return;
            }
            results.replaceChildren(...items.map((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'case-lookup-option';
                button.setAttribute('role', 'option');
                const name = document.createElement('strong');
                name.textContent = item.name;
                const meta = document.createElement('span');
                meta.textContent = item.meta;
                button.append(name, meta);
                button.addEventListener('click', () => choose(item));
                return button;
            }), ...(total > items.length ? [note(`Showing the first ${items.length} of ${total}. Type a name to find others.`)] : []));
        } catch (error) {
            if (current === sequence) results.replaceChildren(note('The resident list could not be loaded. Check the connection and try again.'));
        }
    };

    purok.addEventListener('change', () => {
        query.value = '';
        query.disabled = purok.value === '';
        query.placeholder = purok.value === '' ? 'Select a Purok first' : 'Click to see the residents, or type a name';
        if (purok.value === '') { hint.textContent = 'Select the resident\'s Purok, then pick the resident from the list.'; load(''); return; }
        load('').then(() => { open(); query.focus(); });
    });
    query.addEventListener('focus', open);
    query.addEventListener('click', open);
    query.addEventListener('input', () => {
        window.clearTimeout(timer);
        const term = query.value.trim();
        open();
        timer = window.setTimeout(() => load(term.replace(/\s+/g, '').length >= 2 ? term : ''), 250);
    });
    // Keyboard: Down arrow moves into the list, Escape closes it.
    picker.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') { close(); query.focus(); }
        if (event.key === 'ArrowDown' && event.target === query) { const first = results.querySelector('.case-lookup-option'); if (first) { event.preventDefault(); open(); first.focus(); } }
    });
    // The list closes when clicking anywhere outside the picker.
    document.addEventListener('click', (event) => { if (!picker.contains(event.target)) close(); });

    change.addEventListener('click', () => {
        hiddenId.value = '';
        selected.querySelector('[data-health-selected-name]').textContent = 'No resident selected';
        selected.querySelector('[data-health-selected-meta]').textContent = 'Select the Purok, then choose the resident.';
        selected.classList.add('is-empty');
        change.hidden = true;
        search.hidden = false;
        if (purok.value !== '') load('');
        (purok.value !== '' ? query : purok).focus();
    });
    }

    // ── Follow-up date: optional, required for Follow-up, hidden when Cancelled.
    //    Medicines: only on Completed or Follow-up visits. ──
    const status = form.querySelector('[data-health-status]');
    const followUp = form.querySelector('[data-health-follow-up]');
    if (status && followUp) {
    const input = followUp.querySelector('input');
    const star = followUp.querySelector('[data-health-follow-up-required]');
    const medicines = form.querySelector('[data-health-medicines]');
    const sync = () => {
        const cancelled = status.value === 'cancelled';
        followUp.hidden = cancelled;
        input.disabled = cancelled;
        input.required = status.value === 'follow_up';
        if (star) star.hidden = status.value !== 'follow_up';
        if (medicines) {
            const giving = status.value === 'completed' || status.value === 'follow_up';
            medicines.hidden = !giving;
            medicines.querySelectorAll('select, input').forEach((field) => { field.disabled = !giving; });
        }
    };
    status.addEventListener('change', sync);
    sync();
    }

    // ── Medicine lines: add another, remove one (the last line is cleared instead). ──
    const lines = form.querySelector('[data-health-medicine-lines]');
    const add = form.querySelector('[data-health-medicine-add]');
    if (lines && add) {
        add.addEventListener('click', () => {
            const line = lines.querySelector('[data-health-medicine-line]').cloneNode(true);
            line.querySelectorAll('select, input').forEach((field) => { field.value = ''; field.classList.remove('is-invalid'); });
            lines.append(line);
            line.querySelector('select').focus();
        });
        lines.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-health-medicine-remove]');
            if (!remove) return;
            const line = remove.closest('[data-health-medicine-line]');
            if (lines.querySelectorAll('[data-health-medicine-line]').length > 1) line.remove();
            else line.querySelectorAll('select, input').forEach((field) => { field.value = ''; });
        });
    }

    // ── Referral reason shown when "Referred to the RHU" is Yes. ──
    const referral = form.querySelector('[data-health-referral]');
    if (referral) {
        const reason = referral.querySelector('input');
        const syncReferral = () => {
            const yes = form.querySelector('[data-health-referred]:checked')?.value === '1';
            referral.hidden = !yes;
            reason.required = yes;
        };
        form.querySelectorAll('[data-health-referred]').forEach((radio) => radio.addEventListener('change', syncReferral));
        syncReferral();
    }
})();

// Health program forms: a block marked data-show-when="field:value1,value2" is shown only while that field (select or
// checked radio/checkbox) has one of the values; its inputs are disabled while hidden. The server validates again.
(() => {
    document.querySelectorAll('[data-show-when]').forEach((block) => {
        const [name, list] = block.dataset.showWhen.split(':');
        const values = list.split(',');
        const form = block.closest('form');
        if (!form) return;
        const current = () => {
            const field = form.querySelector(`[name="${name}"]`);
            if (!field) return '';
            if (field.type === 'radio' || field.type === 'checkbox') return form.querySelector(`[name="${name}"]:checked`)?.value ?? '';
            return field.value;
        };
        const sync = () => {
            const show = values.includes(current());
            block.hidden = !show;
            block.querySelectorAll('input, select, textarea').forEach((field) => { field.disabled = !show; });
        };
        form.querySelectorAll(`[name="${name}"]`).forEach((field) => field.addEventListener('change', sync));
        sync();
    });
})();
