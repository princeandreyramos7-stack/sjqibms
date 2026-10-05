// Financial Management pages.
// List: tabs, sort headers and pagination are links loaded in place by Live Search (app.js, data-live-page); after each
// update the results report the chosen tab and sort in [data-fin-state], which is copied into the filter form's hidden
// fields, so typing a search keeps them. Forms: the check number is shown only for Check payments, the OR number is
// hidden for allotments (NTA numbers are automatic), and a transaction can be linked to a resident found by name
// (typing the payor / payee name shows matching residents too).
// Without JavaScript every link is a normal page load, and the server validates everything again on save.
(() => {
    const form = document.querySelector('[data-fin-form]');
    if (form) {
        const field = (name) => form.querySelector(`[data-fin-field="${name}"]`);
        document.addEventListener('live-search:updated', (event) => {
            const source = event.target.querySelector('[data-fin-state]');
            if (!source) return;
            let state;
            try { state = JSON.parse(source.dataset.finState); } catch (error) { return; }
            ['tab', 'sort', 'dir'].forEach((name) => { const input = field(name); if (input && typeof state[name] === 'string') input.value = state[name]; });
        });
        // Reset also clears the hidden tab and sort fields (app.js clears only the visible fields); runs before app.js's handler.
        form.addEventListener('click', (event) => {
            if (!event.target.closest('[data-live-reset]')) return;
            ['tab', 'sort', 'dir'].forEach((name) => { const input = field(name); if (input) input.value = ''; });
        }, true);
    }

    // Year / period pickers submit on change (the Show button remains for keyboard users and without JavaScript).
    document.querySelectorAll('[data-fin-autosubmit]').forEach((select) => {
        select.addEventListener('change', () => select.form.submit());
        const button = select.form.querySelector('[data-fin-autosubmit-button]');
        if (button) button.hidden = true;
    });

    // Shows a block only while a condition holds; hidden fields are disabled so they are not sent.
    const toggle = (block, shown) => {
        if (!block) return;
        block.hidden = !shown;
        block.querySelectorAll('input, select, textarea').forEach((input) => { input.disabled = !shown; });
    };

    // Check number only for Check payments (collection form and release form).
    document.querySelectorAll('[data-fin-mode]').forEach((mode) => {
        const scope = mode.closest('form');
        const check = scope.querySelector('[data-fin-check]');
        const sync = () => toggle(check, mode.value === 'check');
        mode.addEventListener('change', sync);
        sync();
    });

    // Amount fields accept numbers only: digits, commas, one decimal point and up to two decimals (letters and other
    // characters are removed as they are typed or pasted). data-fin-amount="signed" also allows a leading minus sign.
    document.querySelectorAll('[data-fin-amount]').forEach((input) => {
        const signed = input.dataset.finAmount === 'signed';
        const clean = (value) => {
            const negative = signed && value.trimStart().startsWith('-');
            let text = value.replace(/[^\d.,]/g, '');
            const dot = text.indexOf('.');
            if (dot !== -1) text = text.slice(0, dot + 1) + text.slice(dot + 1).replace(/[.,]/g, '').slice(0, 2);
            return (negative ? '-' : '') + text;
        };
        // data-fin-max: the highest amount allowed (Record Collection); the browser blocks submitting a larger amount.
        const max = input.dataset.finMax ? Number(input.dataset.finMax) : null;
        const checkMax = (typing = true) => {
            const value = Number(input.value.replace(/,/g, ''));
            const over = max !== null && input.value !== '' && Number.isFinite(value) && value > max;
            input.setCustomValidity(over ? `A collection can be at most ₱${max.toLocaleString('en-PH', { minimumFractionDigits: 2 })}.` : '');
            if (over || typing) input.classList.toggle('is-invalid', over);   // on page load, keep the server's error styling
        };
        input.addEventListener('input', () => {
            const cleaned = clean(input.value);
            if (cleaned !== input.value) {
                const caret = Math.max(0, (input.selectionStart ?? input.value.length) - (input.value.length - cleaned.length));
                input.value = cleaned;
                try { input.setSelectionRange(caret, caret); } catch (error) { /* some input types have no caret */ }
            }
            checkMax();
            if (input.validationMessage) input.reportValidity();
        });
        if (input.value !== '') input.value = clean(input.value);
        if (max !== null && input.value !== '') checkMax(false);
    });

    const recordForm = document.querySelector('[data-fin-record-form]');
    if (!recordForm) return;

    // OR number for ordinary collections; allotments get an NTA number automatically.
    const category = recordForm.querySelector('[data-fin-category]');
    const orBlock = recordForm.querySelector('[data-fin-or]');
    const ntaBlock = recordForm.querySelector('[data-fin-nta]');
    if (category && orBlock && ntaBlock) {
        const sync = () => {
            const allotment = category.selectedOptions[0]?.dataset.allotment === '1';
            toggle(orBlock, !allotment);
            ntaBlock.hidden = !allotment;
        };
        category.addEventListener('change', sync);
        sync();
    }

    // Optional link to a resident: type 2+ letters, pick from the list (the payor / payee is filled when empty).
    const box = recordForm.querySelector('[data-fin-resident]');
    if (!box) return;
    const hiddenId = box.querySelector('[data-fin-resident-id]');
    const selected = box.querySelector('[data-fin-resident-selected]');
    const nameLabel = box.querySelector('[data-fin-resident-name]');
    const search = box.querySelector('[data-fin-resident-search]');
    const query = box.querySelector('[data-fin-resident-query]');
    const results = box.querySelector('[data-fin-resident-results]');
    const payor = recordForm.querySelector('[data-fin-payor]');
    let timer = 0;
    let sequence = 0;
    let fromPayor = false;   // the suggestions were started by typing in the payor / payee field
    const close = () => { results.hidden = true; };
    const load = async (term) => {
        const current = ++sequence;
        if (term.replace(/\s+/g, '').length < 2) { results.replaceChildren(); close(); return; }
        try {
            const response = await fetch(`finance_resident_lookup.php?q=${encodeURIComponent(term)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = response.ok ? await response.json() : { results: [] };
            if (current !== sequence) return;
            const items = Array.isArray(data.results) ? data.results : [];
            if (items.length === 0) {
                const line = document.createElement('p');
                line.className = 'case-lookup-empty fin-not-registered';
                const title = document.createElement('strong');
                title.textContent = `"${term}" is not yet a registered resident.`;
                const note = document.createElement('span');
                note.textContent = 'You can still post this without a resident link.';
                line.append(title, note);
                results.replaceChildren(line);
            } else {
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
                    button.addEventListener('click', () => {
                        hiddenId.value = String(item.id);
                        nameLabel.textContent = `${item.name} · ${item.meta}`;
                        selected.hidden = false;
                        search.hidden = true;
                        close();
                        query.value = '';
                        if (payor && (fromPayor || payor.value.trim() === '')) payor.value = item.name;
                        fromPayor = false;
                    });
                    return button;
                }));
            }
            results.hidden = false;
        } catch (error) {
            if (current === sequence) close();
        }
    };
    query.addEventListener('input', () => { fromPayor = false; window.clearTimeout(timer); timer = window.setTimeout(() => load(query.value.trim()), 250); });
    query.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    // Typing the payor / payee name also searches residents (while none is linked), so a match can be linked in one click.
    if (payor) {
        payor.setAttribute('autocomplete', 'off');
        payor.addEventListener('input', () => {
            if (hiddenId.value !== '') return;
            fromPayor = true;
            query.value = payor.value;
            window.clearTimeout(timer);
            timer = window.setTimeout(() => load(payor.value.trim()), 250);
        });
        payor.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    }
    document.addEventListener('click', (event) => { if (!box.contains(event.target) && event.target !== payor) close(); });
    box.querySelector('[data-fin-resident-clear]').addEventListener('click', () => {
        hiddenId.value = '';
        nameLabel.textContent = '';
        selected.hidden = true;
        search.hidden = false;
        query.focus();
    });
})();
