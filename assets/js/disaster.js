// Disaster Management pages.
// List: sort headers and pagination are links loaded in place by Live Search (app.js, data-live-page); after each update
// the results report the chosen sort in [data-drr-state], which is copied into the filter form's hidden fields, so typing
// a search keeps the sort. Form: the Incident Details section is shown only when the type is Incident.
// Without JavaScript every link is a normal page load, and the server validates everything again on save.
(() => {
    const form = document.querySelector('[data-drr-form]');
    if (form) {
        const field = (name) => form.querySelector(`[data-drr-field="${name}"]`);
        document.addEventListener('live-search:updated', (event) => {
            const source = event.target.querySelector('[data-drr-state]');
            if (!source) return;
            let state;
            try { state = JSON.parse(source.dataset.drrState); } catch (error) { return; }
            ['sort', 'dir'].forEach((name) => { const input = field(name); if (input && typeof state[name] === 'string') input.value = state[name]; });
        });
        // Reset also clears the hidden sort fields (app.js clears only the visible fields); runs before app.js's handler.
        form.addEventListener('click', (event) => {
            if (!event.target.closest('[data-live-reset]')) return;
            ['sort', 'dir'].forEach((name) => { const input = field(name); if (input) input.value = ''; });
        }, true);
    }

    const recordForm = document.querySelector('[data-drr-record-form]');
    if (recordForm) {
        const type = recordForm.querySelector('[data-drr-type]');
        const incident = recordForm.querySelector('[data-drr-incident]');
        const sync = () => {
            const shown = type.value === 'incident';
            incident.hidden = !shown;
            incident.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = !shown; });
        };
        type.addEventListener('change', sync);
        sync();
    }

    // Shows a block only while a condition holds; hidden fields are disabled so they are not sent.
    const toggle = (block, shown) => {
        block.hidden = !shown;
        block.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = !shown; });
    };

    // Hazard form: "Describe the hazard" only for Other.
    const hazardForm = document.querySelector('[data-drr-hazard-form]');
    if (hazardForm) {
        const hazardType = hazardForm.querySelector('[data-drr-hazard-type]');
        const other = hazardForm.querySelector('[data-drr-hazard-other]');
        const sync = () => toggle(other, hazardType.value === 'other');
        hazardType.addEventListener('change', sync);
        sync();
    }

    // Family picker (check-in and relief): choose the Purok, then any member of the family from a compact dropdown served
    // by disaster_family_lookup.php. Picking fills the number of family members when the form has that field.
    const picker = document.querySelector('[data-drr-family-picker]');
    if (picker) {
        const hiddenId = picker.querySelector('[data-drr-family-id]');
        const selected = picker.querySelector('[data-drr-family-selected]');
        const search = picker.querySelector('[data-drr-family-search]');
        const purok = picker.querySelector('[data-drr-family-purok]');
        const query = picker.querySelector('[data-drr-family-query]');
        const results = picker.querySelector('[data-drr-family-results]');
        const hint = picker.querySelector('[data-drr-family-hint]');
        const change = picker.querySelector('[data-drr-family-change]');
        const members = document.querySelector('[data-drr-family-members]');
        const group = picker.querySelector('[data-drr-family-group]');
        const allPuroks = picker.querySelector('[data-drr-all-puroks]');
        let timer = 0;
        let sequence = 0;
        const open = () => { if (purok.value !== '') { results.hidden = false; query.setAttribute('aria-expanded', 'true'); } };
        const close = () => { results.hidden = true; query.setAttribute('aria-expanded', 'false'); };
        const note = (text) => { const line = document.createElement('p'); line.className = 'case-lookup-empty'; line.textContent = text; return line; };
        const choose = (item) => {
            hiddenId.value = String(item.id);
            selected.querySelector('[data-drr-family-name]').textContent = item.name;
            selected.querySelector('[data-drr-family-meta]').textContent = item.checked_in ? `${item.meta} · ${item.checked_in}` : item.meta;
            selected.classList.remove('is-empty');
            selected.classList.toggle('is-warning', Boolean(item.checked_in));
            change.hidden = false;
            search.hidden = true;
            results.replaceChildren();
            close();
            query.value = '';
            if (members) members.value = String(item.members || 1);
        };
        const load = async (term) => {
            if (purok.value === '') { results.replaceChildren(); close(); return; }
            const current = ++sequence;
            try {
                const groupValue = group ? group.value : '';
                const response = await fetch(`disaster_family_lookup.php?purok=${encodeURIComponent(purok.value)}&group=${encodeURIComponent(groupValue)}&q=${encodeURIComponent(term)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const data = response.ok ? await response.json() : { results: [] };
                if (current !== sequence) return;
                const items = Array.isArray(data.results) ? data.results : [];
                const total = Number(data.total) || items.length;
                const place = data.purok || 'this Purok';
                const who = data.group ? ` (${data.group})` : '';
                hint.textContent = data.searching ? `${total} matching resident${total === 1 ? '' : 's'}${who} in ${place}.` : `${total} active resident${total === 1 ? '' : 's'}${who} in ${place}. Click the box to see the list, or type a name.`;
                if (items.length === 0) { results.replaceChildren(note(data.searching ? `No matching resident${who} in ${place}.` : (data.group ? `No active residents${who} in ${place}.` : 'No active residents are registered in this Purok.'))); return; }
                results.replaceChildren(...items.map((item) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'case-lookup-option';
                    button.setAttribute('role', 'option');
                    const name = document.createElement('strong');
                    name.textContent = item.name;
                    (Array.isArray(item.groups) ? item.groups : []).forEach((tag) => {
                        const badge = document.createElement('span');
                        badge.className = `drr-group drr-group-${tag.key} drr-option-tag`;
                        badge.textContent = tag.label;
                        name.append(badge);
                    });
                    const meta = document.createElement('span');
                    meta.textContent = item.checked_in ? `${item.meta} · ${item.checked_in}` : item.meta;
                    button.append(name, meta);
                    button.addEventListener('click', () => choose(item));
                    return button;
                }), ...(total > items.length ? [note(`Showing the first ${items.length} of ${total}. Type a name to find others.`)] : []));
            } catch (error) {
                if (current === sequence) results.replaceChildren(note('The resident list could not be loaded. Check the connection and try again.'));
            }
        };
        const onPurok = () => {
            query.value = '';
            query.disabled = purok.value === '';
            query.placeholder = purok.value === '' ? 'Select a Purok first' : 'Click to see the residents, or type a name';
            if (purok.value === '') { hint.textContent = 'Select the Purok, then pick any member of the family from the list.'; load(''); return; }
            load('').then(() => { open(); query.focus(); });
        };
        purok.addEventListener('change', onPurok);
        // Priority group: lists only Senior / Under 5 / PWD / Solo Parent residents; "All Puroks" needs a group.
        if (group && allPuroks) {
            group.addEventListener('change', () => {
                allPuroks.disabled = group.value === '';
                if (group.value === '' && purok.value === 'all') purok.value = '';
                onPurok();
            });
        }
        query.addEventListener('focus', open);
        query.addEventListener('click', open);
        query.addEventListener('input', () => {
            window.clearTimeout(timer);
            const term = query.value.trim();
            open();
            timer = window.setTimeout(() => load(term.replace(/\s+/g, '').length >= 2 ? term : ''), 250);
        });
        picker.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') { close(); query.focus(); }
            if (event.key === 'ArrowDown' && event.target === query) { const first = results.querySelector('.case-lookup-option'); if (first) { event.preventDefault(); open(); first.focus(); } }
        });
        document.addEventListener('click', (event) => { if (!picker.contains(event.target)) close(); });
        change.addEventListener('click', () => {
            hiddenId.value = '';
            selected.querySelector('[data-drr-family-name]').textContent = 'No family selected';
            selected.querySelector('[data-drr-family-meta]').textContent = 'Select the Purok, then choose any member of the family.';
            selected.classList.add('is-empty');
            selected.classList.remove('is-warning');
            change.hidden = true;
            search.hidden = false;
            if (purok.value !== '') load('');
            (purok.value !== '' ? query : purok).focus();
        });
    }

    // Contact form: role and position for members, category for hotlines.
    const contactForm = document.querySelector('[data-drr-contact-form]');
    if (contactForm) {
        const contactType = contactForm.querySelector('[data-drr-contact-type]');
        const sync = () => {
            const member = contactType.value === 'member';
            contactForm.querySelectorAll('[data-drr-member-only]').forEach((block) => toggle(block, member));
            contactForm.querySelectorAll('[data-drr-hotline-only]').forEach((block) => toggle(block, !member));
        };
        contactType.addEventListener('change', sync);
        sync();
    }
})();
