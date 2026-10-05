// Resident Portal sign-up (register.php). The server (sms_register_api.php) checks every rule; this script shows one
// step at a time and the answers:
//   * Next (steps 1, 2) asks the server to check that step; errors are shown under the fields.
//   * Step 3: "Send OTP" is disabled for the cooldown with a countdown; once the code is verified the number is locked
//     and "Next: Account" is enabled.
//   * Step 4: "Create account" sends everything; an error on an earlier step takes the person back to that step.
(() => {
    const form = document.querySelector('[data-rg-form]');
    if (!form) return;
    const $ = (selector) => form.querySelector(selector);
    const panels = [...form.querySelectorAll('[data-rg-panel]')];
    const markers = [...document.querySelectorAll('[data-rg-step-marker]')];
    const message = $('[data-rg-message]');
    const sendBtn = $('[data-rg-send]');
    const verifyBtn = $('[data-rg-verify]');
    const createBtn = $('[data-rg-create]');
    const mobile = $('[data-rg-mobile]');
    const code = $('[data-rg-code]');
    const consent = $('[data-rg-consent]');
    const sentText = $('[data-rg-sent]');
    const testCode = $('[data-rg-test-code]');
    const captchaBox = $('[data-rg-captcha]');
    const nextAccount = panels[2].querySelector('[data-rg-next]');
    const done = document.querySelector('[data-rg-done]');
    const minAge = Number(form.dataset.minAge) || 18;
    const fieldStep = { first_name: 1, middle_name: 1, last_name: 1, suffix: 1, birth_date: 1, sex: 1, civil_status: 1, house_no: 2, zone: 2, street: 2, purok: 2, household_role: 1, household_head_name: 1, household_relationship: 1, household_no: 1, mobile: 3, consent: 3, code: 3, login: 4, password: 4, confirm_password: 4 };
    let step = 1;
    let verified = false;
    let timer = 0;
    let sentOnce = false;

    const say = (text, tone = 'error') => {
        message.textContent = text;
        message.className = `rg-message is-${tone}`;
        message.hidden = text === '';
    };
    const clearErrors = () => {
        form.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });
        form.querySelectorAll('.is-invalid').forEach((node) => node.classList.remove('is-invalid'));
    };
    const fieldOf = (name) => form.querySelector(`[name="${name}"]`);
    const showErrors = (errors) => {
        let first = null;
        Object.entries(errors || {}).forEach(([name, text]) => {
            const slot = form.querySelector(`[data-error-for="${name}"]`);
            if (slot) slot.textContent = text;
            const field = fieldOf(name);
            if (field && field.type === 'radio') field.closest('fieldset').classList.add('is-invalid');
            else if (field) { field.classList.add('is-invalid'); field.setAttribute('aria-invalid', 'true'); }
            if (!first) first = name;
        });
        return first;
    };
    const showStep = (number, focusTitle = true) => {
        step = number;
        panels.forEach((panel) => { panel.hidden = Number(panel.dataset.rgPanel) !== number; });
        markers.forEach((marker) => {
            const n = Number(marker.dataset.rgStepMarker);
            marker.classList.toggle('is-current', n === number);
            marker.classList.toggle('is-done', n < number);
            if (n === number) marker.setAttribute('aria-current', 'step'); else marker.removeAttribute('aria-current');
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
        if (focusTitle) { const title = panels[number - 1].querySelector('.rg-title'); if (title) title.focus({ preventScroll: true }); }
    };
    // An error list from the server: go to the earliest step that has one and show the errors there.
    const handleErrors = (result) => {
        const first = showErrors(result.errors);
        if (first && fieldStep[first] && fieldStep[first] !== step) showStep(fieldStep[first], false);
        say(result.message || 'Please correct the highlighted fields.');
        if (first) { const field = fieldOf(first); if (field) field.focus(); }
    };
    const post = async (action, extra = {}) => {
        const data = new FormData(form);
        data.set('action', action);
        data.set('consent', consent.checked ? '1' : '');
        Object.entries(extra).forEach(([key, value]) => data.set(key, value));
        try {
            const response = await fetch('sms_register_api.php', { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } });
            return await response.json();
        } catch (error) {
            return { ok: false, message: 'Connection problem. Please check your internet and try again.' };
        }
    };

    // Clear a field's error as soon as it is changed.
    form.addEventListener('input', (event) => {
        const name = event.target.name;
        if (!name) return;
        event.target.classList.remove('is-invalid');
        event.target.removeAttribute('aria-invalid');
        const slot = form.querySelector(`[data-error-for="${name}"]`);
        if (slot) slot.textContent = '';
    });

    // "Your household": the member details are shown only for "I am a member of a household".
    const memberBox = $('[data-rg-member]');
    const roles = [...form.querySelectorAll('[data-rg-household-role]')];
    const syncMember = () => {
        const role = (roles.find((radio) => radio.checked) || {}).value;
        memberBox.hidden = role !== 'member';
        const group = roles[0].closest('fieldset');
        if (role) group.classList.remove('is-invalid');
    };
    roles.forEach((radio) => radio.addEventListener('change', () => {
        syncMember();
        const slot = form.querySelector('[data-error-for="household_role"]');
        if (slot) slot.textContent = '';
    }));
    syncMember();

    // Household head check: only an exact full-name match is confirmed (nothing is listed, to keep names private).
    // A confirmed household fills in the household number unless the person typed one.
    const headName = $('[data-rg-head-name]');
    const headResult = $('[data-rg-head-result]');
    const householdNo = fieldOf('household_no');
    let headTimer = 0;
    let autoNo = '';
    const lookupHead = async () => {
        const name = headName.value.trim();
        if (name.split(/\s+/).length < 2 || name.length < 5) { headResult.hidden = true; return; }
        const result = await post('head_lookup', { household_head_name: name });
        if (headName.value.trim() !== name) return;
        headResult.hidden = false;
        if (result.found) {
            headResult.className = 'rg-lookup is-found';
            headResult.textContent = `✓ Household found: ${result.household_no} (${result.purok})`;
            if (householdNo.value.trim() === '' || householdNo.value === autoNo) { householdNo.value = result.household_no; autoNo = result.household_no; }
        } else {
            headResult.className = 'rg-lookup';
            headResult.textContent = result.message || 'No match yet. The Barangay Hall will check your household.';
            if (autoNo !== '' && householdNo.value === autoNo) { householdNo.value = ''; autoNo = ''; }
        }
    };
    headName.addEventListener('input', () => { window.clearTimeout(headTimer); headTimer = window.setTimeout(lookupHead, 700); });

    // Age from the birthdate (the server checks the 18-year minimum again).
    const birth = $('[data-rg-birth]');
    const age = $('[data-rg-age]');
    const updateAge = () => {
        const value = birth.value;
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) { age.value = ''; return; }
        const [y, m, d] = value.split('-').map(Number);
        const today = new Date();
        let years = today.getFullYear() - y;
        if (today.getMonth() + 1 < m || (today.getMonth() + 1 === m && today.getDate() < d)) years -= 1;
        age.value = years >= 0 && years < 130 ? String(years) : '';
        const slot = form.querySelector('[data-error-for="birth_date"]');
        if (age.value !== '' && years < minAge) { slot.textContent = 'You must be at least 18 years old to create an account. A parent or guardian can request documents for you, or visit the Barangay Hall.'; birth.classList.add('is-invalid'); }
    };
    birth.addEventListener('change', updateAge);
    birth.addEventListener('input', updateAge);

    // Next / Back
    panels.forEach((panel) => {
        const number = Number(panel.dataset.rgPanel);
        const back = panel.querySelector('[data-rg-back]');
        if (back) back.addEventListener('click', () => { say(''); showStep(number - 1); });
        const next = panel.querySelector('[data-rg-next]');
        if (!next) return;
        next.addEventListener('click', async () => {
            say('');
            if (number === 3) { if (verified) showStep(4); return; }
            clearErrors();
            next.disabled = true;
            const result = await post('check', { step: String(number) });
            next.disabled = false;
            if (result.reload) { say(result.message); return; }
            if (!result.ok) { handleErrors(result); return; }
            showStep(number + 1);
        });
    });

    // Step 3 — OTP
    const sendLabel = () => (sentOnce ? 'Resend OTP' : 'Send OTP');
    const startCountdown = (seconds) => {
        window.clearInterval(timer);
        let left = Math.max(0, Math.ceil(seconds));
        const tick = () => {
            if (left <= 0 || verified) { window.clearInterval(timer); sendBtn.disabled = verified; sendBtn.textContent = verified ? 'Number verified' : sendLabel(); return; }
            sendBtn.disabled = true;
            sendBtn.textContent = `${sendLabel()} (${left}s)`;
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
        return form.dataset.testCaptcha === '1' ? 'XXXX.DUMMY.TOKEN.XXXX' : '';
    };
    const resetCaptcha = () => { try { if (window.turnstile) window.turnstile.reset(); } catch (error) { /* not loaded */ } };
    const unlockNumber = () => {
        verified = false;
        mobile.readOnly = false;
        code.value = '';
        code.disabled = true;
        verifyBtn.disabled = true;
        verifyBtn.textContent = 'Verify';
        sendBtn.disabled = false;
        sendBtn.textContent = sendLabel();
        nextAccount.disabled = true;
        sentText.hidden = true;
        testCode.hidden = true;
        if (captchaBox) captchaBox.hidden = false;
    };

    mobile.addEventListener('input', () => {
        mobile.value = mobile.value.replace(/\D/g, '').slice(0, 11);
        if (verified || code.disabled) return;
        // A different number: the code that was sent no longer applies.
        code.value = '';
        code.disabled = true;
        verifyBtn.disabled = true;
        sentText.hidden = true;
        testCode.hidden = true;
    });

    sendBtn.addEventListener('click', async () => {
        say('');
        const number = mobile.value.replace(/[\s-]/g, '');
        if (!/^09\d{9}$/.test(number)) { say('Invalid number format. Example: 09171234567'); mobile.classList.add('is-invalid'); mobile.focus(); return; }
        if (!consent.checked) { say('Please tick the consent box to continue.'); consent.closest('.rg-consent').classList.add('is-invalid'); consent.focus(); return; }
        const token = captchaToken();
        if (token === '') { say('Please complete the CAPTCHA.'); return; }
        sendBtn.disabled = true;
        const result = await post('send_otp', { mobile: number, captcha: token });
        resetCaptcha();
        if (result.reload) { say(result.message); return; }
        if (!result.ok) {
            if (result.errors) { handleErrors(result); sendBtn.disabled = false; return; }
            say(result.message || 'Something went wrong. Please try again.');
            if (result.cooldown) startCountdown(result.cooldown); else sendBtn.disabled = false;
            if (result.field === 'mobile') { mobile.classList.add('is-invalid'); mobile.focus(); }
            return;
        }
        sentOnce = true;
        sentText.hidden = false;
        sentText.textContent = `We sent a code to ${result.masked}. It is valid for 5 minutes.`;
        if (result.test_code) { testCode.hidden = false; testCode.textContent = 'TEST MODE: your OTP is'; testCode.append(Object.assign(document.createElement('strong'), { textContent: result.test_code })); }
        else testCode.hidden = true;
        code.disabled = false;
        verifyBtn.disabled = false;
        code.value = '';
        say(result.message, 'success');
        startCountdown(result.cooldown || Number(form.dataset.cooldown) || 60);
        code.focus();
    });
    consent.addEventListener('change', () => consent.closest('.rg-consent').classList.remove('is-invalid'));

    code.addEventListener('input', () => { code.value = code.value.replace(/\D/g, '').slice(0, 6); });
    code.addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); verifyBtn.click(); } });

    verifyBtn.addEventListener('click', async () => {
        say('');
        if (!/^\d{6}$/.test(code.value)) { say('Please enter the 6-digit code.'); code.focus(); return; }
        verifyBtn.disabled = true;
        const result = await post('verify', { code: code.value });
        if (!result.ok) {
            verifyBtn.disabled = false;
            say(result.message || 'Something went wrong. Please try again.');
            if (result.need_new) { code.value = ''; code.disabled = true; verifyBtn.disabled = true; } else code.focus();
            return;
        }
        verified = true;
        window.clearInterval(timer);
        mobile.readOnly = true;
        code.disabled = true;
        verifyBtn.textContent = 'Verified ✓';
        sendBtn.disabled = true;
        sendBtn.textContent = 'Number verified';
        if (captchaBox) captchaBox.hidden = true;
        nextAccount.disabled = false;
        document.querySelector('[data-rg-verified-number]').textContent = result.display || mobile.value;
        say(result.message, 'success');
        nextAccount.focus();
    });

    // Step 4 — create the account
    createBtn.addEventListener('click', async () => {
        say('');
        clearErrors();
        if (!verified) { showStep(3); say('Please verify your mobile number first.'); return; }
        createBtn.disabled = true;
        const result = await post('register');
        createBtn.disabled = false;
        if (result.reload) { say(result.message); return; }
        if (!result.ok) {
            if (result.need_new || result.step === 3) { unlockNumber(); showStep(3, false); say(result.message); return; }
            if (result.errors) { handleErrors(result); return; }
            say(result.message || 'Something went wrong. Please try again.');
            return;
        }
        form.hidden = true;
        document.querySelector('[data-rg-steps]').hidden = true;
        done.hidden = false;
        done.querySelector('[data-rg-done-text]').textContent = `Thank you, ${result.name}! Your Resident Portal account has been created.`;
        window.scrollTo({ top: 0 });
        done.focus();
    });
})();
