// Report charts: draws every <canvas data-report-chart="{json}"> with Chart.js (loaded just before this file, pinned
// version with an integrity check). The figures come from the server (the same data as the table inside each chart
// card). When Chart.js is not available the table stays visible instead, so nothing is ever lost. Charts inside Live
// Search results are drawn again after each update.
(() => {
    if (typeof window.Chart === 'undefined') return;
    const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', minimumFractionDigits: 2 });
    const count = new Intl.NumberFormat('en-PH', { maximumFractionDigits: 1 });
    const css = getComputedStyle(document.documentElement);
    const ink = (css.getPropertyValue('--ink') || '#1d2b27').trim();
    const muted = (css.getPropertyValue('--muted') || '#5f6f69').trim();
    window.Chart.defaults.font.family = "'DM Sans', system-ui, sans-serif";
    window.Chart.defaults.color = muted;

    const draw = (canvas) => {
        if (canvas.dataset.chartDrawn === '1') return;
        let spec;
        try { spec = JSON.parse(canvas.dataset.reportChart); } catch (error) { return; }
        const format = (value) => (spec.money ? peso.format(value) : count.format(value));
        const round = spec.type === 'doughnut';
        const axis = (title, stacked) => ({ stacked, beginAtZero: true, title: { display: title !== '', text: title, color: ink }, ticks: {} });
        const options = {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            indexAxis: spec.horizontal ? 'y' : 'x',
            plugins: {
                title: { display: false },
                legend: { display: round || spec.datasets.length > 1, position: 'bottom' },
                tooltip: { callbacks: { label: (item) => `${item.dataset.label}: ${format(spec.horizontal ? item.parsed.x : item.parsed.y)}` } },
            },
        };
        if (round) {
            options.plugins.tooltip.callbacks.label = (item) => `${item.label}: ${format(item.parsed)}`;
        } else {
            const valueAxis = axis(spec.y || '', spec.stacked);
            valueAxis.ticks = { precision: spec.money ? undefined : 0, callback: (value) => format(value) };
            const categoryAxis = axis(spec.x || '', spec.stacked);
            options.scales = spec.horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis };
        }
        try {
            // eslint-disable-next-line no-new
            new window.Chart(canvas, { type: spec.type, data: { labels: spec.labels, datasets: spec.datasets }, options });
            canvas.dataset.chartDrawn = '1';
            canvas.closest('.rpa-chart')?.classList.add('has-chart');
        } catch (error) {
            // Keep the data table visible.
        }
    };
    const drawAll = (scope) => scope.querySelectorAll('canvas[data-report-chart]').forEach(draw);
    drawAll(document);
    document.addEventListener('live-search:updated', (event) => drawAll(event.target instanceof Element ? event.target : document));
})();
