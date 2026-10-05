// Relief & Assistance form (assistance_form.php). The server validates everything again; without JavaScript every field
// stays visible and usable except the beneficiary search.
//   * Record as: Given now (date cannot be in the future, "Received by" required) or Scheduled.
//   * Beneficiary: All residents, PWD or Solo Parent (everyone in the group, shown as a list — they become Scheduled records to
//     claim), or One resident / Household (search the whole barangay; the Purok is an optional filter). The lists are served by
//     assistance_beneficiary_lookup.php. Picking one fills "Received by".
//   * Form of assistance: Cash shows the amount; In-kind shows the Inventory item lines. The disbursement link is shown for
//     cash from the Barangay Fund; "Source details" for the source Other.
//   * Amount: numbers only (digits, commas, one decimal point, up to two decimals).
(() => {
    const form = document.querySelector('[data-ast-form]');
    if (!form) return;
    const limited = form.dataset.astLimited === '1';
    const $ = (selector) => form.querySelector(selector);
    const $$ = (selector) => [...form.querySelectorAll(selector)];
    const show = (block, shown) => {
        if (!block) return;
        block.hidden = !shown;
        block.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = !shown; });
    };

    // Amount: numbers only.
    const amount = $('[data-ast-amount]');
    if (amount) {
        const clean = (value) => {
            let text = value.replace(/[^\d.,]/g, '');
            const dot = text.indexOf('.');
            if (dot !== -1) text = text.slice(0, dot + 1) + text.slice(dot + 1).replace(/[.,]/g, '').slice(0, 2);
            return text;
        };
        amount.addEventListener('input', () => {
            const cleaned = clean(amount.value);
            if (cleaned === amount.value) return;
            const caret = Math.max(0, (amount.selectionStart ?? amount.value.length) - (amount.value.length - cleaned.length));
            amount.value = cleaned;
            try { amount.setSelectionRange(caret, caret); } catch (error) { /* no caret */ }
        });
    }

    // Record as (new records only).
    const recordAs = () => ($$('[data-ast-record-as]').find((radio) => radio.checked) || { value: limited ? 'given' : 'scheduled' }).value;
    const date = $('[data-ast-date]');
    const received = $('[data-ast-received]');
    const syncRecordAs = () => {
        const given = recordAs() === 'given';
        if (date) {
            if (given) date.max = date.dataset.today; else date.removeAttribute('max');
            const label = $('[data-ast-date-label]');
            if (label) label.firstChild.textContent = given ? 'Date given ' : 'Scheduled date ';
        }
        if (received) received.required = given;
        const star = $('[data-ast-received-required]');
        if (star) star.hidden = !given;
        const note = $('[data-ast-stock-note]');
        if (note) note.textContent = given ? 'When Given, the quantity cannot be more than the stock on hand.' : 'Scheduled: nothing is deducted now; the stock is checked when it is marked as Given.';
        syncQuantities();
    };

    // Form of assistance, source and disbursement link.
    const formChoice = () => { const fixed = $('[data-ast-form-fixed]'); if (fixed) return fixed.value; const radio = $$('[data-ast-form-choice]').find((r) => r.checked); return radio ? radio.value : ''; };
    const sourceValue = () => { const fixed = $('[data-ast-source-fixed]'); if (fixed) return fixed.value; const select = $('[data-ast-source]'); return select ? select.value : ''; };
    // Category vs form: a gentle reminder only (the server accepts any combination).
    const category = $('[data-ast-category]');
    const categoryHint = $('[data-ast-category-hint]');
    const syncCategoryHint = () => {
        if (!category || !categoryHint) return;
        const kind = formChoice();
        let text = '';
        if (kind === 'cash' && category.value !== '' && category.value !== 'cash') text = 'Cash is usually recorded under Cash Assistance. Keep this category only if the cash is meant for it (e.g. cash for food).';
        if (kind === 'in_kind' && category.value === 'cash') text = 'Cash Assistance is usually given as Cash, not as Inventory items.';
        categoryHint.textContent = text;
        categoryHint.hidden = text === '';
    };
    if (category) category.addEventListener('change', syncCategoryHint);
    const details = $('[data-ast-source-details]');
    const detailsInput = details ? details.querySelector('input') : null;
    const syncForm = () => {
        const kind = formChoice();
        show($('[data-ast-cash-block]'), kind === 'cash');
        show($('[data-ast-items-block]'), kind === 'in_kind');
        show($('[data-ast-finance-block]'), kind === 'cash' && sourceValue() === 'barangay_fund');
        if (details) {
            const other = ['donation', 'ngo', 'other'].includes(sourceValue());   // donor / NGO / other giver required
            details.hidden = !(other || (detailsInput && detailsInput.value.trim() !== ''));
            if (detailsInput) detailsInput.required = other;
            const star = $('[data-ast-details-required]');
            if (star) star.hidden = !other;
        }
        if (amount) amount.required = kind === 'cash' && !amount.disabled;
        syncCategoryHint();
    };
    $$('[data-ast-form-choice]').forEach((radio) => radio.addEventListener('change', syncForm));
    const sourceSelect = $('[data-ast-source]');
    if (sourceSelect) sourceSelect.addEventListener('change', syncForm);

    // Item lines: unit, quantity limit (Given), Add item.
    const syncQuantities = () => {
        $$('[data-ast-line]').forEach((line) => {
            const option = line.querySelector('[data-ast-item]').selectedOptions[0];
            const qty = line.querySelector('[data-ast-qty]');
            const unit = line.querySelector('[data-ast-unit]');
            const stock = option && option.value !== '' ? Number(option.dataset.stock) : null;
            unit.textContent = option && option.value !== '' ? `${option.dataset.unit} · ${Number(stock).toLocaleString()} on hand` : '';
            if (stock !== null && recordAs() === 'given') qty.max = String(stock); else qty.removeAttribute('max');
        });
    };
    $$('[data-ast-item]').forEach((select) => select.addEventListener('change', syncQuantities));
    const addLine = $('[data-ast-add-line]');
    if (addLine) {
        const syncAdd = () => { addLine.hidden = !$$('[data-ast-line]').some((line) => line.hidden); };
        addLine.addEventListener('click', () => {
            const next = $$('[data-ast-line]').find((line) => line.hidden);
            if (next) { next.hidden = false; next.querySelector('select').focus(); }
            syncAdd();
        });
        syncAdd();
    }

    // Received by and relationship.
    const relationship = $('[data-ast-relationship]');
    const syncRelationship = () => {
        if (!relationship || !received || relationship.querySelector('input').disabled) return;
        const beneficiary = (received.dataset.beneficiary || '').trim().toLowerCase();
        const name = received.value.trim().toLowerCase();
        relationship.hidden = !(name !== '' && beneficiary !== '' && name !== beneficiary) && relationship.querySelector('input').value.trim() === '';
    };
    if (received) received.addEventListener('input', syncRelationship);

    // Beneficiary picker.
    const picker = $('[data-ast-picker]');
    if (picker) {
        const ids = { resident: picker.querySelector('[data-ast-id="resident"]'), household: picker.querySelector('[data-ast-id="household"]') };
        const selected = picker.querySelector('[data-ast-selected]');
        const search = picker.querySelector('[data-ast-search]');
        const purok = picker.querySelector('[data-ast-purok]');
        const query = picker.querySelector('[data-ast-query]');
        const results = picker.querySelector('[data-ast-results]');
        const hint = picker.querySelector('[data-ast-hint]');
        const change = picker.querySelector('[data-ast-change]');
        const queryLabel = form.querySelector('[data-ast-query-label]');
        const defaultHint = 'Choose who receives it, then pick the name from the list.';
        // Beneficiary buttons: All residents, PWD, Solo Parent (residents) or Household.
        const choice = () => ($$('[data-ast-type]').find((radio) => radio.checked) || { value: 'resident' }).value;
        const type = () => (choice() === 'household' ? 'household' : 'resident');
        // Groups (everyone receives it): All residents, PWD, Solo Parent. The lookup narrows by PWD / Solo Parent only.
        const isGroup = () => ['all', 'pwd', 'solo_parent'].includes(choice());
        const group = { get value() { return ['pwd', 'solo_parent'].includes(choice()) ? choice() : ''; } };
        const labels = { all: 'Resident ', resident: 'Resident ', pwd: 'PWD ', solo_parent: 'Solo Parent ', household: 'Household ' };
        // PWD / Solo Parent: everyone in the group receives it — no name to pick; the list of recipients is shown instead,
        // and each person is recorded as the receiver of their own record.
        const searchBox = picker.querySelector('[data-ast-search-box]');
        const bulk = picker.querySelector('[data-ast-bulk]');
        const bulkCount = picker.querySelector('[data-ast-bulk-count]');
        const bulkNames = picker.querySelector('[data-ast-bulk-names]');
        const receiverField = form.querySelector('[data-ast-receiver-field]');
        const bulkReceiver = form.querySelector('[data-ast-bulk-receiver]');
        let bulkText = '';
        let bulkTotal = 0;
        const peso = (cents) => `₱${(cents / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        const renderCount = () => {
            if (!isGroup()) return;
            const cents = amount && !amount.disabled ? Math.round(Number(amount.value.replace(/,/g, '')) * 100) : 0;
            bulkCount.textContent = bulkText + (bulkTotal > 0 && cents > 0 ? ` Total cash: ${peso(cents * bulkTotal)} (${peso(cents)} each).` : '');
        };
        const groupNote = form.querySelector('[data-ast-group-note]');
        const givenRadio = form.querySelector('[data-ast-record-as][value="given"]');
        const scheduledRadio = form.querySelector('[data-ast-record-as][value="scheduled"]');
        const syncGroupMode = () => {
            const on = isGroup();
            // A group is always a payout list (Scheduled); each person is marked as Given on the Claim List.
            if (givenRadio && scheduledRadio) {
                if (on) scheduledRadio.checked = true;
                givenRadio.disabled = on;
                if (groupNote) groupNote.hidden = !on;
                syncRecordAs();
            }
            selected.hidden = on;
            searchBox.hidden = on;
            bulk.hidden = !on;
            if (bulkReceiver) bulkReceiver.hidden = !on;
            show(receiverField, !on);
            if (relationship) {
                if (on) show(relationship, false);
                else { relationship.querySelectorAll('input').forEach((input) => { input.disabled = false; }); syncRelationship(); }
            }
        };
        if (amount) amount.addEventListener('input', renderCount);
        $$('[data-ast-form-choice]').forEach((radio) => radio.addEventListener('change', renderCount));
        let timer = 0;
        let sequence = 0;
        const open = () => { if (purok.value !== '') { results.hidden = false; query.setAttribute('aria-expanded', 'true'); } };
        const close = () => { results.hidden = true; query.setAttribute('aria-expanded', 'false'); };
        const note = (text) => { const line = document.createElement('p'); line.className = 'case-lookup-empty'; line.textContent = text; return line; };
        const badges = (groups) => (Array.isArray(groups) ? groups : []).map((tag) => {
            const badge = document.createElement('span');
            badge.className = `drr-group drr-group-${tag.key} drr-option-tag`;
            badge.textContent = tag.label;
            return badge;
        });
        const general = () => { const badge = document.createElement('span'); badge.className = 'drr-group ast-group-general drr-option-tag'; badge.textContent = 'All residents'; return badge; };
        // Household: "Received by" is picked from the current members, or "Other (representative)" to type a name.
        const pick = form.querySelector('[data-ast-receiver-pick]');
        const usePick = (members, preferred) => {
            if (!pick || !received) return;
            if (!Array.isArray(members)) { pick.hidden = true; pick.replaceChildren(); received.hidden = false; return; }
            const options = members.map((member) => Object.assign(document.createElement('option'), { value: member, textContent: member }));
            options.push(Object.assign(document.createElement('option'), { value: '', textContent: 'Other (representative)' }));
            pick.replaceChildren(...options);
            pick.hidden = false;
            pick.value = members.includes(preferred) ? preferred : (members[0] || '');
            pick.dispatchEvent(new Event('change'));
        };
        if (pick && received) {
            pick.addEventListener('change', () => {
                if (pick.value !== '') { received.value = pick.value; received.dataset.beneficiary = pick.value; received.hidden = true; }
                else { if (received.hidden) received.value = ''; received.dataset.beneficiary = '\u0000'; received.hidden = false; received.focus(); }
                syncRelationship();
            });
            if (!pick.hidden) {
                if (pick.value !== '') { received.hidden = true; received.dataset.beneficiary = pick.value; } else received.dataset.beneficiary = '\u0000';
            }
        }
        const clear = () => {
            usePick(null);
            if (received) received.dataset.beneficiary = '';
            ids.resident.value = '';
            ids.household.value = '';
            selected.querySelector('[data-ast-name]').textContent = 'No beneficiary selected';
            selected.querySelector('[data-ast-meta]').textContent = defaultHint;
            selected.querySelector('[data-ast-tags]').replaceChildren();
            selected.classList.add('is-empty');
            change.hidden = true;
            search.hidden = false;
        };
        const choose = (item) => {
            ids.resident.value = type() === 'resident' ? String(item.id) : '';
            ids.household.value = type() === 'household' ? String(item.id) : '';
            selected.querySelector('[data-ast-name]').textContent = item.name;
            selected.querySelector('[data-ast-meta]').textContent = item.meta;
            const tags = badges(item.groups);
            selected.querySelector('[data-ast-tags]').replaceChildren(...(tags.length ? tags : [general()]));
            selected.classList.remove('is-empty');
            change.hidden = false;
            search.hidden = true;
            results.replaceChildren();
            close();
            query.value = '';
            if (received) {
                if (type() === 'household') {
                    received.value = '';
                    usePick(Array.isArray(item.members) ? item.members : [], item.receiver || '');
                } else {
                    usePick(null);
                    const previous = (received.dataset.beneficiary || '').trim();
                    if (received.value.trim() === '' || received.value.trim() === previous) received.value = item.receiver || '';
                    received.dataset.beneficiary = item.receiver || '';
                    syncRelationship();
                }
            }
        };
        const load = async (term) => {
            if (purok.value === '') { results.replaceChildren(); close(); return; }
            const current = ++sequence;
            try {
                const response = await fetch(`assistance_beneficiary_lookup.php?type=${encodeURIComponent(type())}&purok=${encodeURIComponent(purok.value)}&group=${encodeURIComponent(group.value)}&q=${encodeURIComponent(term)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const data = response.ok ? await response.json() : { results: [] };
                if (current !== sequence) return;
                const items = Array.isArray(data.results) ? data.results : [];
                const total = Number(data.total) || items.length;
                const place = data.purok || 'this Purok';
                const who = data.group ? ` (${data.group})` : '';
                const noun = type() === 'household' ? 'household' : 'resident';
                if (isGroup()) {
                    const label = data.group || 'residents';
                    bulkTotal = total;
                    bulkText = total === 0 ? `No residents are recorded as ${label} in ${place}. Tick ${label} under Sector on the resident profiles first.` : `Will receive: all ${total} ${label} in ${place}.`;
                    renderCount();
                    bulkNames.replaceChildren(...items.map((item) => {
                        const li = document.createElement('li');
                        const name = document.createElement('strong');
                        name.textContent = item.name;
                        const meta = document.createElement('span');
                        meta.textContent = item.meta;
                        li.append(name, meta);
                        return li;
                    }), ...(total > items.length ? [Object.assign(document.createElement('li'), { textContent: `and ${total - items.length} more` })] : []));
                    hint.hidden = true;
                    results.replaceChildren();
                    close();
                    return;
                }
                hint.textContent = isGroup() ? '' : (data.searching ? `${total} matching ${noun}${total === 1 ? '' : 's'}${who} in ${place}.` : `${total} ${noun}${total === 1 ? '' : 's'}${who} in ${place}. Click the box to see the list, or type a name.`);
                hint.hidden = hint.textContent === '';
                if (items.length === 0) { results.replaceChildren(note(data.searching ? `No matching ${noun}${who} in ${place}.` : `No ${noun}s${who} in ${place}.`)); return; }
                results.replaceChildren(...items.map((item) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'case-lookup-option';
                    button.setAttribute('role', 'option');
                    const name = document.createElement('strong');
                    name.textContent = item.name;
                    name.append(...badges(item.groups));
                    const meta = document.createElement('span');
                    meta.textContent = item.meta;
                    button.append(name, meta);
                    button.addEventListener('click', () => choose(item));
                    return button;
                }), ...(total > items.length ? [note(`Showing the first ${items.length} of ${total}. Type a name to find others.`)] : []));
            } catch (error) {
                if (current === sequence) results.replaceChildren(note('The list could not be loaded. Check the connection and try again.'));
            }
        };
        const onPurok = () => {
            query.value = '';
            query.disabled = purok.value === '';
            query.placeholder = 'Type a name, or click to see the list';
            if (purok.value === '') { hint.textContent = defaultHint; hint.hidden = false; load(''); return; }
            load('');   // the list opens when the box is used
        };
        purok.addEventListener('change', onPurok);
        // PWD / Solo Parent: every Purok at once (the Purok can still narrow it); All residents / Household: pick a Purok.
        $$('[data-ast-type]').forEach((radio) => radio.addEventListener('change', () => {
            clear();
            if (queryLabel) queryLabel.firstChild.textContent = labels[choice()] || 'Resident ';
            if (purok.value === '') purok.value = 'all';
            syncGroupMode();
            onPurok();
        }));
        if (purok.value === '') purok.value = 'all';
        syncGroupMode();
        if (isGroup()) onPurok();
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
            clear();
            if (purok.value !== '') load('');
            (purok.value !== '' ? query : purok).focus();
        });
    }

    $$('[data-ast-record-as]').forEach((radio) => radio.addEventListener('change', syncRecordAs));
    syncForm();
    syncRecordAs();
    syncRelationship();
})();
