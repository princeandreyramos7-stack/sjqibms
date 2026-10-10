<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_gate.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/security_log.php';

if (is_authenticated()) {
    redirect('dashboard.php');
}
// Without ?portal the gate protects the "Officials Portal" staff login list (staff_portal.php); with ?portal it
// protects that one staff login page (used when a login page sends the visitor back). The code is asked every time.
$portal_key = (string) ($_GET['portal'] ?? '');
$portal = $portal_key === '' ? null : (staff_portals()[$portal_key] ?? null);
if ($portal_key !== '' && $portal === null) {
    redirect('index.php');
}
$is_hub = $portal === null;
$self = $is_hub ? 'admin_gate.php' : 'admin_gate.php?portal=' . $portal_key;
$log_portal = $is_hub ? 'barangay_officials' : $portal_key;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('gate_error', 'Your session expired. Please enter the access code again.');
    } elseif (($remaining = admin_gate_lock_remaining()) > 0) {
        flash('gate_error', 'Too many incorrect attempts. Access is temporarily locked.');
        security_log('admin_gate_locked', null, ['portal' => $log_portal]);
    } else {
        $digits = $_POST['code'] ?? [];
        $code = is_array($digits) ? implode('', array_map(static fn ($digit): string => substr(preg_replace('/\D/', '', (string) $digit) ?? '', 0, 1), array_slice($digits, 0, 6))) : '';
        if (admin_gate_verify($code)) {
            admin_gate_reset_failures();
            security_log('admin_gate_passed', null, ['portal' => $log_portal]);
            if ($is_hub) {
                admin_gate_grant_hub();
                redirect('staff_portal.php');
            }
            admin_gate_grant($portal_key);
            redirect('login.php?portal=' . $portal_key);
        }
        admin_gate_record_failure();
        // The entered code is never recorded.
        security_log('admin_gate_failed', null, ['portal' => $log_portal, 'locked' => admin_gate_lock_remaining() > 0]);
        $left = admin_gate_attempts_left();
        flash('gate_error', admin_gate_lock_remaining() > 0 ? 'Too many incorrect attempts. Access is temporarily locked.' : 'Incorrect access code. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' remaining.');
    }
    // Post/Redirect/Get: refreshing the page never resubmits a code.
    redirect($self);
}

$error = flash('gate_error');
$lock_remaining = admin_gate_lock_remaining();
$locked = $lock_remaining > 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $is_hub ? 'Officials Portal' : 'Admin Access Gate' ?> | Barangay San Jose</title>
    <link rel="icon" href="assets/img/barangay-san-jose-logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/landing.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/landing.css') ?>" rel="stylesheet">
</head>
<body class="landing gate">
<main class="gate-page">
    <a class="gate-back" href="index.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>Back to website</a>
    <section class="gate-card" aria-labelledby="gate-title">
        <div class="gate-emblem">
            <img src="assets/img/barangay-san-jose-logo.jpg" alt="Barangay San Jose seal">
            <span class="gate-shield" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><rect x="9" y="10.5" width="6" height="5" rx="1"/><path d="M10.5 10.5V9a1.5 1.5 0 0 1 3 0v1.5"/></svg></span>
        </div>
        <span class="gate-eyebrow"><span class="landing-dot"></span>Restricted Area</span>
        <h1 id="gate-title"><?= $is_hub ? 'Officials Portal' : 'Admin Access Gate' ?></h1>
        <p class="gate-sub">Enter the 6-digit access code to continue to the <strong><?= $is_hub ? 'barangay staff login' : e($portal['title']) . ' login' ?></strong>.</p>

        <?php if ($locked): ?>
            <div class="gate-alert is-locked" role="alert">
                <strong>Access temporarily locked</strong>
                <span>Too many incorrect attempts. Try again in <span data-gate-countdown="<?= e((string) $lock_remaining) ?>"><?= e(sprintf('%d:%02d', intdiv($lock_remaining, 60), $lock_remaining % 60)) ?></span>.</span>
            </div>
        <?php elseif ($error): ?>
            <div class="gate-alert" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="gate-form" autocomplete="off" data-gate-form>
            <?= csrf_field() ?>
            <fieldset class="gate-code" <?= $locked ? 'disabled' : '' ?>>
                <legend class="gate-visually-hidden">Access code</legend>
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input class="gate-digit" type="password" name="code[]" inputmode="numeric" pattern="[0-9]" maxlength="1" required aria-label="Digit <?= $i + 1 ?> of 6" <?= $i === 0 && !$locked ? 'autofocus' : '' ?> data-gate-digit>
                <?php endfor; ?>
            </fieldset>
            <button class="gate-toggle" type="button" aria-pressed="false" data-gate-reveal <?= $locked ? 'disabled' : '' ?>>Show code</button>
            <button class="portal-submit gate-submit" type="submit" <?= $locked ? 'disabled' : '' ?> data-gate-submit>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                Verify Access
            </button>
        </form>

        <ul class="gate-assurances">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Verified on the server</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Attempts are limited</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>Access expires after 10 minutes</li>
        </ul>
    </section>
    <p class="gate-foot">Authorized barangay personnel only · © <?= e(date('Y')) ?> Barangay San Jose</p>
</main>
<script>
// Six-box code entry: digits only, auto-advance, backspace to previous box, arrow keys, and paste of a full code.
(() => {
    const form = document.querySelector('[data-gate-form]');
    const digits = Array.from(document.querySelectorAll('[data-gate-digit]'));
    const submit = document.querySelector('[data-gate-submit]');
    const reveal = document.querySelector('[data-gate-reveal]');
    const fill = (text, start = 0) => {
        const clean = text.replace(/\D/g, '').slice(0, digits.length - start).split('');
        clean.forEach((char, offset) => { digits[start + offset].value = char; });
        const next = digits[Math.min(start + clean.length, digits.length - 1)];
        if (next) next.focus();
        if (digits.every((input) => input.value !== '')) form.requestSubmit ? form.requestSubmit() : form.submit();
    };
    digits.forEach((input, index) => {
        input.addEventListener('input', () => {
            const value = input.value.replace(/\D/g, '');
            input.value = '';
            if (value) fill(value, index);
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && input.value === '' && index > 0) { digits[index - 1].value = ''; digits[index - 1].focus(); event.preventDefault(); }
            if (event.key === 'ArrowLeft' && index > 0) digits[index - 1].focus();
            if (event.key === 'ArrowRight' && index < digits.length - 1) digits[index + 1].focus();
        });
        input.addEventListener('paste', (event) => { event.preventDefault(); fill((event.clipboardData || window.clipboardData).getData('text'), index); });
        input.addEventListener('focus', () => input.select());
    });
    // Submit exactly once.
    form.addEventListener('submit', (event) => {
        if (submit.disabled && submit.dataset.sending === 'true') { event.preventDefault(); return; }
        submit.dataset.sending = 'true';
        submit.disabled = true;
        submit.lastChild.textContent = ' Verifying…';
    });
    if (reveal) reveal.addEventListener('click', () => {
        const show = reveal.getAttribute('aria-pressed') !== 'true';
        digits.forEach((input) => { input.type = show ? 'text' : 'password'; });
        reveal.setAttribute('aria-pressed', String(show));
        reveal.textContent = show ? 'Hide code' : 'Show code';
    });
    // Lockout countdown; reloads when the lock expires so the form re-enables from the server.
    const countdown = document.querySelector('[data-gate-countdown]');
    if (countdown) {
        let remaining = Number.parseInt(countdown.dataset.gateCountdown, 10);
        const tick = () => {
            remaining -= 1;
            if (remaining <= 0) { window.location.reload(); return; }
            countdown.textContent = `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`;
        };
        window.setInterval(tick, 1000);
    }
})();
</script>
</body>
</html>
