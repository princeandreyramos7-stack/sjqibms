<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/sms_registration.php';
require_once __DIR__ . '/includes/functions.php';

// Public Resident Portal sign-up in four steps (rules in includes/sms_registration.php). The steps are shown one at a
// time; the server checks each step (sms_register_api.php) and saves nothing until "Create account". In SMS TEST MODE
// no text is sent and the OTP is shown on the screen.
if (is_authenticated()) redirect('dashboard.php');
$connection = db();
$ready = sms_reg_ready($connection);
$test_captcha = TURNSTILE_SITE_KEY === '1x00000000000000000000AA';
$office_hours = defined('BARANGAY_OFFICE_HOURS') ? BARANGAY_OFFICE_HOURS : 'Monday to Friday, 8:00 AM – 5:00 PM';
$steps = [1 => 'Personal info', 2 => 'Address', 3 => 'Contact', 4 => 'Account'];
$input = static fn (string $name, string $label, string $placeholder, array $attrs = []): string => '<div class="rg-field"><label for="rg-' . e($name) . '">' . $label . '</label><input id="rg-' . e($name) . '" name="' . e($name) . '" placeholder="' . e($placeholder) . '"' . implode('', array_map(static fn ($k, $v): string => ' ' . $k . ($v === true ? '' : '="' . e((string) $v) . '"'), array_keys($attrs + ['type' => 'text']), $attrs + ['type' => 'text'])) . ' aria-describedby="rg-' . e($name) . '-error"><p class="rg-error" id="rg-' . e($name) . '-error" data-error-for="' . e($name) . '"></p></div>';
$select = static fn (string $name, string $label, string $placeholder, array $options): string => '<div class="rg-field"><label for="rg-' . e($name) . '">' . $label . '</label><select id="rg-' . e($name) . '" name="' . e($name) . '" aria-describedby="rg-' . e($name) . '-error"><option value="">' . e($placeholder) . '</option>' . implode('', array_map(static fn ($v, $l): string => '<option value="' . e((string) $v) . '">' . e($l) . '</option>', array_keys($options), $options)) . '</select><p class="rg-error" id="rg-' . e($name) . '-error" data-error-for="' . e($name) . '"></p></div>';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create an Account | <?= e(BARANGAY_NAME) ?></title>
    <link rel="icon" href="assets/img/barangay-san-jose-logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/register.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/register.css') ?>" rel="stylesheet">
    <?php if ($ready): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
</head>
<body class="rg">
<?php if (SMS_TEST_MODE): ?>
    <div class="rg-test-banner" role="note"><strong>TEST MODE</strong> No real text messages are sent. The OTP is shown on the screen.</div>
<?php endif; ?>
<header class="rg-header">
    <a class="rg-brand" href="index.php"><img src="assets/img/barangay-san-jose-logo.jpg" alt="" width="36" height="36"><span><strong><?= e(BARANGAY_NAME) ?></strong><small><?= e(BARANGAY_LOCATION) ?></small></span></a>
</header>
<main class="rg-main">
    <?php if (!$ready): ?>
        <section class="rg-card"><h1 class="rg-title">Create an account</h1><p class="rg-message is-error" role="alert">Online registration is not available yet. Please register at the Barangay Hall.</p></section>
    <?php else: ?>
    <ol class="rg-steps" data-rg-steps aria-label="Registration steps">
        <?php foreach ($steps as $number => $label): ?>
            <li class="rg-step<?= $number === 1 ? ' is-current' : '' ?>" data-rg-step-marker="<?= $number ?>"<?= $number === 1 ? ' aria-current="step"' : '' ?>><span class="rg-step-dot"><span class="rg-step-num"><?= $number ?></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg></span><span class="rg-step-label"><?= e($label) ?></span></li>
        <?php endforeach; ?>
    </ol>

    <form class="rg-card" novalidate data-rg-form data-test-captcha="<?= $test_captcha ? '1' : '0' ?>" data-cooldown="<?= e((string) SMS_OTP_COOLDOWN) ?>" data-min-age="<?= e((string) SMS_REG_MIN_AGE) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <p class="rg-message" role="alert" aria-live="assertive" data-rg-message hidden></p>

        <section class="rg-panel" data-rg-panel="1" aria-labelledby="rg-title-1">
            <p class="rg-kicker">Step 1 of 4</p>
            <h1 class="rg-title" id="rg-title-1" tabindex="-1">Personal information</h1>
            <p class="rg-sub">Enter your full legal name and basic details.</p>
            <h2 class="rg-section">Name</h2>
            <?= $input('first_name', 'First name', 'e.g. Juan', ['maxlength' => 80, 'autocomplete' => 'given-name']) ?>
            <?= $input('middle_name', 'Middle name', 'e.g. Santos', ['maxlength' => 80, 'autocomplete' => 'additional-name']) ?>
            <div class="rg-row rg-row-name">
                <?= $input('last_name', 'Last name', 'e.g. Dela Cruz', ['maxlength' => 80, 'autocomplete' => 'family-name']) ?>
                <?= $input('suffix', 'Suffix <span class="rg-optional">(optional)</span>', 'e.g. Jr.', ['maxlength' => 20, 'autocomplete' => 'honorific-suffix']) ?>
            </div>
            <h2 class="rg-section">Birth and status</h2>
            <div class="rg-row">
                <?= $input('birth_date', 'Birthdate', '', ['type' => 'date', 'max' => date('Y-m-d'), 'min' => '1900-01-01', 'autocomplete' => 'bday', 'data-rg-birth' => true]) ?>
                <div class="rg-field"><label for="rg-age">Age</label><input id="rg-age" type="text" placeholder="Auto-filled" readonly tabindex="-1" data-rg-age><p class="rg-hint">Based on your birthdate</p></div>
            </div>
            <div class="rg-row">
                <?= $select('sex', 'Gender', 'Select gender', sms_reg_sex_options()) ?>
                <?= $select('civil_status', 'Civil status', 'Select status', residents_civil_status_labels()) ?>
            </div>
            <h2 class="rg-section">Your household</h2>
            <fieldset class="rg-field rg-choices" aria-describedby="rg-household_role-error">
                <legend class="rg-legend">Which describes you?</legend>
                <?php foreach (sms_reg_household_roles() as $value => $label): ?>
                    <label class="rg-choice"><input type="radio" name="household_role" value="<?= e($value) ?>" data-rg-household-role><span><?= e($label) ?></span></label>
                <?php endforeach; ?>
                <p class="rg-error" id="rg-household_role-error" data-error-for="household_role"></p>
            </fieldset>
            <div class="rg-member" data-rg-member hidden>
                <?= str_replace('<p class="rg-error"', '<p class="rg-lookup" data-rg-head-result aria-live="polite" hidden></p><p class="rg-error"', $input('household_head_name', 'Name of the household head', 'e.g. Juan Dela Cruz', ['maxlength' => 150, 'autocomplete' => 'off', 'data-rg-head-name' => true])) ?>
                <div class="rg-row">
                    <?= $select('household_relationship', 'Your relationship to the head', 'Select relationship', array_combine(residents_relationships(), residents_relationships())) ?>
                    <?= $input('household_no', 'Household number <span class="rg-optional">(optional)</span>', 'e.g. P3-0012', ['maxlength' => 50, 'autocomplete' => 'off', 'autocapitalize' => 'characters']) ?>
                </div>
            </div>
            <p class="rg-hint rg-hint-after">The Barangay Hall confirms your household when your registration is approved.</p>
            <div class="rg-actions"><button class="rg-btn rg-btn-primary" type="button" data-rg-next>Next: Address</button></div>
        </section>

        <section class="rg-panel" data-rg-panel="2" aria-labelledby="rg-title-2" hidden>
            <p class="rg-kicker">Step 2 of 4</p>
            <h1 class="rg-title" id="rg-title-2" tabindex="-1">Complete address</h1>
            <p class="rg-sub">Where do you live in <?= e(BARANGAY_NAME) ?>?</p>
            <h2 class="rg-section">Home address</h2>
            <?= $select('purok', 'Purok', 'Select purok', residents_purok_options()) ?>
            <?= $input('street', 'Street / Sitio', 'e.g. Centro, Rizal St.', ['maxlength' => 150, 'autocomplete' => 'address-line1']) ?>
            <?= str_replace('<p class="rg-error"', '<p class="rg-hint">Leave blank if your house has no number.</p><p class="rg-error"', $input('house_no', 'House number <span class="rg-optional rg-optional-block">(optional)</span>', 'e.g. 123 or 12-B', ['maxlength' => 20, 'autocomplete' => 'off'])) ?>
            <div class="rg-field"><label for="rg-barangay">Barangay</label><input id="rg-barangay" type="text" value="<?= e(sms_reg_barangay_label()) ?>" readonly tabindex="-1"><p class="rg-hint">Registration is for residents of <?= e(BARANGAY_NAME) ?> only.</p></div>
            <div class="rg-actions"><button class="rg-btn rg-btn-outline" type="button" data-rg-back>Back</button><button class="rg-btn rg-btn-primary" type="button" data-rg-next>Next: Contact</button></div>
        </section>

        <section class="rg-panel" data-rg-panel="3" aria-labelledby="rg-title-3" hidden>
            <p class="rg-kicker">Step 3 of 4</p>
            <h1 class="rg-title" id="rg-title-3" tabindex="-1">Verify your mobile number</h1>
            <p class="rg-sub">We'll send a one-time code to make sure your number is active.</p>
            <h2 class="rg-section">Mobile number</h2>
            <div class="rg-field"><label for="rg-mobile">Mobile number</label><input id="rg-mobile" name="mobile" type="tel" inputmode="numeric" maxlength="11" autocomplete="tel-national" placeholder="09XXXXXXXXX" aria-describedby="rg-mobile-hint" data-rg-mobile><p class="rg-hint" id="rg-mobile-hint">Example: 09171234567. We'll text you a 6-digit code.</p></div>
            <label class="rg-consent"><input type="checkbox" name="consent" value="1" data-rg-consent><span><?= e(SMS_CONSENT_TEXT) ?></span></label>
            <div class="rg-captcha" data-rg-captcha><div class="cf-turnstile" data-sitekey="<?= e(TURNSTILE_SITE_KEY) ?>" data-theme="light"></div></div>
            <button class="rg-btn rg-btn-primary rg-btn-block" type="button" data-rg-send>Send OTP</button>
            <h2 class="rg-section">Verification code</h2>
            <p class="rg-sent" data-rg-sent hidden></p>
            <div class="rg-test-code" data-rg-test-code hidden></div>
            <div class="rg-field"><label for="rg-code">Verification code</label><div class="rg-inline"><input id="rg-code" name="code" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="6-digit code" disabled data-rg-code><button class="rg-btn rg-btn-soft" type="button" disabled data-rg-verify>Verify</button></div><p class="rg-hint">The code is valid for 5 minutes. You have 5 attempts.</p></div>
            <div class="rg-actions"><button class="rg-btn rg-btn-outline" type="button" data-rg-back>Back</button><button class="rg-btn rg-btn-primary" type="button" disabled data-rg-next>Next: Account</button></div>
        </section>

        <section class="rg-panel" data-rg-panel="4" aria-labelledby="rg-title-4" hidden>
            <p class="rg-kicker">Step 4 of 4</p>
            <h1 class="rg-title" id="rg-title-4" tabindex="-1">Set up your account</h1>
            <p class="rg-sub">Your number is verified. Add your email or a username and create a password.</p>
            <div class="rg-verified"><div><span>Mobile number</span><strong data-rg-verified-number></strong></div><span class="rg-verified-tag"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg>Verified</span></div>
            <h2 class="rg-section">Login</h2>
            <?= $input('login', 'Email or username', 'e.g. juan.delacruz or name@example.com', ['maxlength' => 190, 'autocomplete' => 'username', 'autocapitalize' => 'none', 'spellcheck' => 'false']) ?>
            <p class="rg-hint rg-hint-after">Must be unique. You'll use this to log in to the Resident Portal.</p>
            <h2 class="rg-section">Password</h2>
            <?= $input('password', 'Password', 'At least ' . ACCOUNTS_PASSWORD_MIN . ' characters', ['type' => 'password', 'maxlength' => 72, 'autocomplete' => 'new-password']) ?>
            <?= $input('confirm_password', 'Confirm password', 'Re-enter your password', ['type' => 'password', 'maxlength' => 72, 'autocomplete' => 'new-password']) ?>
            <div class="rg-actions"><button class="rg-btn rg-btn-outline" type="button" data-rg-back>Back</button><button class="rg-btn rg-btn-primary" type="button" data-rg-create>Create account</button></div>
        </section>
    </form>

    <section class="rg-card rg-done" data-rg-done hidden tabindex="-1" aria-labelledby="rg-done-title">
        <div class="rg-done-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg></div>
        <h1 class="rg-title" id="rg-done-title">Account created</h1>
        <p class="rg-sub" data-rg-done-text></p>
        <p class="rg-sub">Your account is <strong>pending approval</strong>. The Barangay Hall will review your details, and you can log in once your account is approved. You will also receive barangay announcements by text.</p>
        <a class="rg-btn rg-btn-primary" href="login.php?portal=resident">Go to Resident Login</a>
    </section>
    <noscript><p class="rg-message is-error">JavaScript is needed to register online. You can also register at the Barangay Hall.</p></noscript>
    <?php endif; ?>

    <p class="rg-login">Already have an account? <a href="login.php?portal=resident">Log in</a></p>
    <p class="rg-foot">No mobile phone or need help? You can also register at the Barangay Hall, <?= e(str_replace(['–', '-'], 'to', $office_hours)) ?>.</p>
</main>
<?php if ($ready): ?><script src="assets/js/register.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/register.js') ?>"></script><script src="assets/js/password_meter.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/password_meter.js') ?>"></script><?php endif; ?>
</body>
</html>
