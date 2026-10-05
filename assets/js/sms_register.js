// Public SMS registration (sms_register.php). The server (sms_register_api.php) checks every rule; this script only
// runs the three steps — send the OTP, verify the code, register — and shows the answers:
//   * "Ipadala ang OTP" is disabled for the cooldown with a countdown on the button.
//   * Once the number is verified it is locked and "Mag-register" is enabled.
//   * Changing the number before it is verified hides the code field (a new OTP is needed).
(() => {
    const form = document.querySelector('[data-smsr-form]');
    if (!form) return;
    const $ = (selector) => form.querySelector(selector);
    const sendBtn = $('[data-smsr-send]');
    const verifyBtn = $('[data-smsr-verify]');
    const registerBtn = $('[data-smsr-register]');
    const mobile = $('[data-smsr-mobile]');
    const code = $('[data-smsr-code]');
    const otpBox = $('[data-smsr-otp]');
    const sentText = $('[data-smsr-sent]');
    const testCode = $('[data-smsr-test-code]');
    const message = $('[data-smsr-message]');
    const captchaBox = $('[data-smsr-captcha]');
    const done = document.querySelector('[data-smsr-done]');
    const testCaptcha = form.dataset.testCaptcha === '1';
    let verified = false;
    let timer = 0;
    let sentOnce = false;

    const say = (text, tone = 'error') => {
        message.textContent = text;
        message.className = `smsr-message is-${tone}`;
        message.hidden = text === '';
    };
    const sendLabel = () => (sentOnce ? 'Ipadala muli ang OTP' : 'Ipadala ang OTP');
    const startCountdown = (seconds) => {
        window.clearInterval(timer);
        let left = Math.max(0, Math.ceil(seconds));
        const tick = () => {
            if (left <= 0 || verified) { window.clearInterval(timer); sendBtn.disabled = verified; sendBtn.textContent = sendLabel(); return; }
            sendBtn.disabled = true;
            sendBtn.textContent = `${sendLabel()} (${left})`;
            left -= 1;
        };
        tick();
        timer = window.setInterval(tick, 1000);
    };
    const captchaToken = () => {
        if (window.turnstile && typeof window.turnstile.getResponse === 'function') {
            const token = window.turnstile.getResponse();
            if (token) return token;
        }
        const field = form.querySelector('[name="cf-turnstile-response"]');
        if (field && field.value) return field.value;
        // Cloudflare's test keys (TEST MODE): the widget may not load without internet; its dummy token is accepted.
        return testCaptcha ? 'XXXX.DUMMY.TOKEN.XXXX' : '';
    };
    const resetCaptcha = () => { try { if (window.turnstile) window.turnstile.reset(); } catch (error) { /* not loaded */ } };
    const post = async (action, extra = {}) => {
        const data = new FormData(form);
        data.set('action', action);
        data.set('consent', form.querySelector('[data-smsr-consent]').checked ? '1' : '');
        Object.entries(extra).forEach(([key, value]) => data.set(key, value));
        try {
            const response = await fetch('sms_register_api.php', { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } });
            return await response.json();
        } catch (error) {
            return { ok: false, message: 'Connection problem. Please check your internet and try again.' };
        }
    };
    const focusField = (name) => { const field = form.querySelector(`[name="${name}"]`); if (field) field.focus(); };

    sendBtn.addEventListener('click', async () => {
        say('');
        const number = mobile.value.replace(/[\s-]/g, '');
        if (!/^09\d{9}$/.test(number)) { say('Invalid number format. Example: 09171234567'); mobile.focus(); return; }
        const token = captchaToken();
        if (token === '') { say('Please complete the CAPTCHA.'); return; }
        sendBtn.disabled = true;
        const result = await post('send_otp', { mobile: number, captcha: token });
        resetCaptcha();
        if (result.reload) { say(result.message); return; }
        if (!result.ok) {
            say(result.message || 'Something went wrong. Please try again.');
            if (result.cooldown) startCountdown(result.cooldown); else sendBtn.disabled = false;
            if (result.field) focusField(result.field === 'captcha' ? 'full_name' : result.field);
            return;
        }
        sentOnce = true;
        otpBox.hidden = false;
        sentText.textContent = `Ipinadala ang code sa ${result.masked}. Valid ito sa loob ng 5 minuto.`;
        if (result.test_code) { testCode.hidden = false; testCode.innerHTML = ''; testCode.append('TEST MODE — Ang iyong OTP: ', Object.assign(document.createElement('strong'), { textContent: result.test_code })); }
        else testCode.hidden = true;
        code.value = '';
        say(result.message, 'success');
        startCountdown(result.cooldown || Number(form.dataset.cooldown) || 60);
        code.focus();
    });

    code.addEventListener('input', () => { code.value = code.value.replace(/\D/g, '').slice(0, 6); });
    code.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); verifyBtn.click(); } });

    verifyBtn.addEventListener('click', async () => {
        say('');
        if (!/^\d{6}$/.test(code.value)) { say('Please enter the 6-digit code.'); code.focus(); return; }
        verifyBtn.disabled = true;
        const result = await post('verify', { code: code.value });
        verifyBtn.disabled = false;
        if (!result.ok) {
            say(result.message || 'Something went wrong. Please try again.');
            if (result.need_new) code.value = '';
            code.focus();
            return;
        }
        verified = true;
        window.clearInterval(timer);
        mobile.readOnly = true;
        mobile.classList.add('is-locked');
        code.disabled = true;
        verifyBtn.disabled = true;
        verifyBtn.textContent = 'Na-verify ✓';
        sendBtn.disabled = true;
        sendBtn.textContent = 'Na-verify na ang numero';
        if (captchaBox) captchaBox.hidden = true;
        registerBtn.disabled = false;
        say(result.message, 'success');
        registerBtn.focus();
    });

    // Changing the number before it is verified: the code that was sent no longer applies.
    mobile.addEventListener('input', () => {
        mobile.value = mobile.value.replace(/[^\d]/g, '').slice(0, 11);
        if (verified || otpBox.hidden) return;
        otpBox.hidden = true;
        code.value = '';
        say('');
    });

    registerBtn.addEventListener('click', async () => {
        if (!verified) return;
        say('');
        registerBtn.disabled = true;
        const result = await post('register', { mobile: mobile.value });
        if (!result.ok) {
            say(result.message || 'Something went wrong. Please try again.');
            if (result.need_new) { verified = false; mobile.readOnly = false; mobile.classList.remove('is-locked'); code.disabled = false; verifyBtn.textContent = 'I-verify'; sendBtn.disabled = false; sendBtn.textContent = 'Ipadala muli ang OTP'; if (captchaBox) captchaBox.hidden = false; otpBox.hidden = true; }
            else registerBtn.disabled = false;
            if (result.field) focusField(result.field);
            return;
        }
        form.hidden = true;
        done.hidden = false;
        done.querySelector('[data-smsr-done-text]').textContent = `Salamat, ${result.name}! Makakatanggap ka ng text sa ${result.masked} tuwing may bagong anunsyo ang barangay (${result.purok}).`;
        done.focus();
    });
})();
