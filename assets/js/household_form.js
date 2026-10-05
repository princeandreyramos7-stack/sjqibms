// Add / Edit Household (household_form.php). The server checks everything again; this script only helps:
//   * Choosing a Purok fills in the next household number (P3-0012) while the number has not been typed by hand
//     (a new household only; an existing number is never replaced).
//   * The household number is checked for duplicates as it is typed ("This household number is already in use.").
//   * Housing type "Other" shows the "Please specify" box.
//   * Required fields are checked on Save with red messages under the fields (no browser tooltips); the page scrolls
//     to and focuses the first field with a problem, also after a server-side error.
(() => {
    const form = document.querySelector('[data-household-form]');
    if (!form) return;
    const purok = form.querySelector('#purok');
    const number = form.querySelector('#household_no');
    const isEdit = form.dataset.edit === '1';
    let autoValue = number.dataset.auto === '1' ? number.value : '';
    let checkTimer = 0;

    const slot = (field) => form.querySelector(`[data-error-for="${field.name}"]`);
    const showError = (field, text) => {
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        const box = slot(field);
        if (box) { box.textContent = text; box.hidden = false; }
    };
    const clearError = (field) => {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        const box = slot(field);
        if (box) { box.textContent = ''; box.hidden = true; }
    };
    const focusFirstError = () => {
        const first = form.querySelector('.is-invalid');
        if (!first) return;
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        first.focus({ preventScroll: true });
    };

    const lookup = async (params) => {
        try {
            const response = await fetch(`${form.dataset.nextUrl}?${new URLSearchParams(params)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            return response.ok ? await response.json() : null;
        } catch (error) {
            return null;
        }
    };
    const checkNumber = async () => {
        const value = number.value.trim();
        if (value === '') return;
        const result = await lookup({ number: value, exclude: form.dataset.exclude || '' });
        if (result && result.taken && number.value.trim() === value) showError(number, 'This household number is already in use.');
    };

    purok.addEventListener('change', async () => {
        clearError(purok);
        if (isEdit || purok.value === '') return;
        // Replace the number only while it is empty or still the one filled in automatically.
        if (number.value.trim() !== '' && number.value !== autoValue) return;
        const result = await lookup({ purok: purok.value });
        if (result && result.next) {
            number.value = result.next;
            autoValue = result.next;
            clearError(number);
        }
    });
    number.addEventListener('input', () => {
        clearError(number);
        window.clearTimeout(checkTimer);
        checkTimer = window.setTimeout(checkNumber, 400);
    });

    // Housing type "Other" → Please specify
    form.querySelectorAll('[data-other-toggle]').forEach((select) => {
        const box = document.getElementById(select.dataset.otherToggle);
        const sync = () => {
            box.hidden = select.value !== 'Other';
            if (box.hidden) box.querySelectorAll('input').forEach(clearError);
        };
        select.addEventListener('change', sync);
        sync();
    });

    form.addEventListener('input', (event) => { if (event.target !== number && event.target.name) clearError(event.target); });
    form.addEventListener('change', (event) => { if (event.target !== number && event.target !== purok && event.target.name) clearError(event.target); });

    form.addEventListener('submit', (event) => {
        let ok = true;
        form.querySelectorAll('[data-required]').forEach((field) => {
            const condition = field.dataset.requiredWhen;
            if (condition) {
                const [name, value] = condition.split('=');
                if (form.querySelector(`[name="${name}"]`).value !== value) return;
            }
            if (field.value.trim() === '') { showError(field, field.dataset.required); ok = false; }
        });
        if (form.querySelector('.is-invalid')) ok = false;
        if (!ok) { event.preventDefault(); focusFirstError(); }
    });

    focusFirstError();
})();
