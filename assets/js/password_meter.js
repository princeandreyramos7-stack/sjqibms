// Password strength meter for every "new password" field (autocomplete="new-password", name new_password or password).
// A guide only: the server applies the real rules (includes/accounts.php accounts_password_errors()).
(() => {
    const MIN = 10;
    const common = ['password', 'qwerty', 'asdf', 'abc', 'iloveyou', 'welcome', 'letmein', 'admin', 'sanjose', 'barangay', 'quirino', 'isabela', 'sjqibms', 'mahalkita', 'test', 'user', 'login'];
    const assess = (value) => {
        if (value === '') return null;
        const lower = value.toLowerCase();
        const letters = lower.replace(/[^a-zñ]/g, '');
        if (value.length < MIN) return [0, `Too short — at least ${MIN} characters`];
        if (!/[a-zñ]/i.test(value) || !/\d/.test(value)) return [1, 'Weak — add both letters and numbers'];
        if (new Set(lower).size < 5 || common.includes(letters)) return [1, 'Weak — too common or easy to guess'];
        let score = 2;
        if (value.length >= 14) score++;
        if (/[^a-z0-9]/i.test(value) || (/[a-z]/.test(value) && /[A-Z]/.test(value))) score++;
        return score >= 4 ? [3, 'Strong'] : (score === 3 ? [2, 'Good'] : [2, 'Fair — longer is stronger']);
    };
    const fields = document.querySelectorAll('input[type="password"][autocomplete="new-password"]');
    if (fields.length === 0) return;
    // Self-contained styles, so the meter looks the same on staff pages and on Create Account.
    const style = document.createElement('style');
    style.textContent = '.pw-meter{display:flex;align-items:center;gap:8px;margin-top:6px;font-size:12px}.pw-meter[hidden]{display:none}'
        + '.pw-meter-bar{flex:0 0 120px;height:6px;border-radius:999px;background:#e5e7eb;overflow:hidden}.pw-meter-bar i{display:block;height:100%;width:0;border-radius:999px;transition:width .2s}'
        + '.pw-meter[data-level="0"] i{width:15%;background:#b4232a}.pw-meter[data-level="1"] i{width:35%;background:#d97706}.pw-meter[data-level="2"] i{width:70%;background:#2f855a}.pw-meter[data-level="3"] i{width:100%;background:#1f6f4a}'
        + '.pw-meter[data-level="0"] .pw-meter-text,.pw-meter[data-level="1"] .pw-meter-text{color:#b4232a}.pw-meter-text{color:#2f5f4c}';
    document.head.appendChild(style);
    fields.forEach((input) => {
        if (!['new_password', 'password'].includes(input.name)) return;
        const meter = document.createElement('div');
        meter.className = 'pw-meter';
        meter.setAttribute('aria-live', 'polite');
        meter.innerHTML = '<span class="pw-meter-bar"><i></i></span><span class="pw-meter-text"></span>';
        meter.hidden = true;
        input.insertAdjacentElement('afterend', meter);
        const update = () => {
            const result = assess(input.value);
            meter.hidden = result === null;
            if (!result) return;
            meter.dataset.level = String(result[0]);
            meter.querySelector('.pw-meter-text').textContent = result[1];
        };
        input.addEventListener('input', update);
        update();
    });
})();
