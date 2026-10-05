// Draws a QR code (SVG) into every [data-qr-url] element, using qrcode-generator (loaded before this file with
// Subresource Integrity). The URL is set by the server. If the library could not load (for example, no internet),
// the element shows the item link as text instead so the label is still usable.
(() => {
    const targets = document.querySelectorAll('[data-qr-url]');
    targets.forEach((target) => {
        const url = target.dataset.qrUrl || '';
        if (typeof window.qrcode !== 'function') {
            target.classList.add('is-unavailable');
            target.textContent = 'QR unavailable (offline)';
            return;
        }
        const qr = window.qrcode(0, 'M');
        qr.addData(url);
        qr.make();
        target.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
    });
})();
