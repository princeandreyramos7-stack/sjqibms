// Inventory list: keeps the filter form in step with the tab, summary card and sort that were chosen inside the results.
// Tabs, cards, sort headers and pagination are links loaded in place by Live Search (app.js, data-live-page); after each
// update the results report their state in [data-inventory-state], which is copied into the form's fields here, so typing
// a search or changing a filter keeps the chosen tab, card and sort. Without JavaScript every link is a normal page load.
(() => {
    const form = document.querySelector('[data-inventory-form]');
    if (!form) return;
    const field = (name) => form.querySelector(`[data-inventory-field="${name}"]`);

    const sync = (scope) => {
        const source = scope.querySelector('[data-inventory-state]');
        if (!source) return;
        let state;
        try { state = JSON.parse(source.dataset.inventoryState); } catch (error) { return; }
        ['type', 'expiring', 'sort', 'dir', 'status'].forEach((name) => {
            const input = field(name);
            if (input && typeof state[name] === 'string') input.value = state[name];
        });
    };

    document.addEventListener('live-search:updated', (event) => sync(event.target));

    // Reset clears the hidden tab/card/sort fields too (app.js clears only the visible fields); runs before app.js's handler.
    form.addEventListener('click', (event) => {
        if (!event.target.closest('[data-live-reset]')) return;
        ['type', 'expiring', 'sort', 'dir'].forEach((name) => { const input = field(name); if (input) input.value = ''; });
    }, true);
})();
