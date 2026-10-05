// SJQIBMS public landing page: sticky navigation, mobile menu, login dropdown, highlights carousel, count-up and scroll reveal.
document.addEventListener('DOMContentLoaded', () => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const nav = document.querySelector('[data-landing-nav]');
    const links = document.querySelector('[data-landing-links]');
    const menuToggle = document.querySelector('[data-menu-toggle]');
    const loginMenu = document.querySelector('[data-login-menu]');

    // Solid navigation bar once the page scrolls past the top of the hero.
    const updateNav = () => nav.classList.toggle('is-scrolled', window.scrollY > 24);
    updateNav();
    window.addEventListener('scroll', updateNav, { passive: true });

    // Mobile navigation drawer.
    const setMenu = (open) => {
        links.classList.toggle('is-open', open);
        menuToggle.setAttribute('aria-expanded', String(open));
        menuToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        if (open) nav.classList.add('is-scrolled'); else updateNav();
    };
    menuToggle.addEventListener('click', () => setMenu(menuToggle.getAttribute('aria-expanded') !== 'true'));
    links.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenu(false)));

    // Login dropdown: toggles on click, closes on outside click or Escape and returns focus to the button.
    if (loginMenu) {
        const toggle = loginMenu.querySelector('[data-login-toggle]');
        const panel = loginMenu.querySelector('[data-login-panel]');
        const setLogin = (open, focusFirst = false) => {
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
            if (open) setMenu(false);
            if (open && focusFirst) panel.querySelector('a').focus();
        };
        toggle.addEventListener('click', () => setLogin(panel.hidden));
        toggle.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') { event.preventDefault(); setLogin(true, true); }
        });
        document.addEventListener('click', (event) => { if (!loginMenu.contains(event.target)) setLogin(false); });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !panel.hidden) { setLogin(false); toggle.focus(); }
        });
    }
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && links.classList.contains('is-open')) { setMenu(false); menuToggle.focus(); } });

    // Highlight the navigation link of the section in view.
    const sectionLinks = Array.from(document.querySelectorAll('[data-nav-section]'));
    const sections = sectionLinks.map((link) => document.querySelector(link.getAttribute('href'))).filter(Boolean);
    if ('IntersectionObserver' in window && sections.length) {
        const spy = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                sectionLinks.forEach((link) => link.classList.toggle('is-active', link.getAttribute('href') === `#${entry.target.id}`));
            });
        }, { rootMargin: '-45% 0px -50% 0px' });
        sections.forEach((section) => spy.observe(section));
    }

    // Highlights carousel with arrows, dots, swipe, keyboard and gentle autoplay (paused on hover/focus and for reduced motion).
    document.querySelectorAll('[data-carousel]').forEach((carousel) => {
        const track = carousel.querySelector('[data-carousel-track]');
        const slides = carousel.querySelectorAll('[data-carousel-slide]');
        const dots = carousel.querySelectorAll('[data-carousel-dot]');
        const prev = carousel.querySelector('[data-carousel-prev]');
        const next = carousel.querySelector('[data-carousel-next]');
        let index = 0;
        let timer = null;
        if (slides.length < 2) { prev.hidden = true; next.hidden = true; carousel.querySelector('[data-carousel-dots]').hidden = true; return; }
        const show = (target) => {
            index = (target + slides.length) % slides.length;
            track.style.transform = `translateX(-${index * 100}%)`;
            slides.forEach((slide, i) => slide.setAttribute('aria-hidden', String(i !== index)));
            dots.forEach((dot, i) => dot.setAttribute('aria-current', String(i === index)));
        };
        const stop = () => { window.clearInterval(timer); timer = null; };
        const start = () => { if (!reduceMotion && !timer) timer = window.setInterval(() => show(index + 1), 6000); };
        prev.addEventListener('click', () => { show(index - 1); stop(); });
        next.addEventListener('click', () => { show(index + 1); stop(); });
        dots.forEach((dot) => dot.addEventListener('click', () => { show(Number(dot.dataset.carouselDot)); stop(); }));
        carousel.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') { show(index - 1); stop(); }
            if (event.key === 'ArrowRight') { show(index + 1); stop(); }
        });
        let touchX = null;
        carousel.addEventListener('touchstart', (event) => { touchX = event.touches[0].clientX; stop(); }, { passive: true });
        carousel.addEventListener('touchend', (event) => {
            if (touchX === null) return;
            const delta = event.changedTouches[0].clientX - touchX;
            if (Math.abs(delta) > 40) show(index + (delta < 0 ? 1 : -1));
            touchX = null;
        });
        carousel.addEventListener('mouseenter', stop);
        carousel.addEventListener('mouseleave', start);
        carousel.addEventListener('focusin', stop);
        show(0);
        start();
    });

    // Count-up for statistics when they scroll into view.
    const countUp = (element) => {
        const target = Number.parseInt(element.dataset.count, 10);
        if (Number.isNaN(target) || reduceMotion || target === 0) return;
        const duration = 1200;
        const started = performance.now();
        const format = new Intl.NumberFormat('en-US');
        const step = (now) => {
            const progress = Math.min(1, (now - started) / duration);
            element.textContent = format.format(Math.round(target * (1 - Math.pow(1 - progress, 3))));
            if (progress < 1) window.requestAnimationFrame(step);
        };
        window.requestAnimationFrame(step);
    };

    // Reveal-on-scroll; everything is shown immediately where IntersectionObserver is unavailable.
    const revealables = document.querySelectorAll('[data-reveal]');
    if (!('IntersectionObserver' in window)) {
        revealables.forEach((element) => element.classList.add('is-visible'));
        return;
    }
    const reveal = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            const element = entry.target;
            element.classList.add('is-visible');
            element.querySelectorAll('[data-count]').forEach(countUp);
            reveal.unobserve(element);
            // After revealing, return the element to its own transitions (for example card hover) without the stagger delay.
            window.setTimeout(() => { element.removeAttribute('data-reveal'); element.style.transitionDelay = ''; }, 900);
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
    revealables.forEach((element, i) => {
        element.style.transitionDelay = `${(i % 3) * 90}ms`;
        reveal.observe(element);
    });
});
