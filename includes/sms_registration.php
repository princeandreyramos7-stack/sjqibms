<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/site.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/residents.php';
require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/registrations.php';
require_once __DIR__ . '/security_log.php';
require_once __DIR__ . '/sms.php';

// Public Resident Portal sign-up (register.php), four steps: personal info, address, mobile number (verified by a
// 6-digit SMS OTP) and the account (email or username + password). Nothing is saved before the last step. The result
// is a Pending resident profile, a Pending resident account and an application in Resident Registrations
// (registration_applications); the account signs in only after the Secretary or the System Administrator approves it
// there (or activates the account in User Management), which also makes the resident profile Active. Applicants must be
// 18 or older. When exactly one existing resident profile has the same name and birthdate and has no account yet, the new
// account is linked to that profile instead of creating a duplicate; the profile itself is not changed.
// The verified number is also registered for announcement texts (sms_subscribers), as stated in the consent.
// Tables: sms_subscribers, sms_otp_requests (migration 20261010_sms_subscribers); users.username (20261011_users_username).
// OTP rules: 6 digits, valid 5 minutes, 5 wrong tries (then a new OTP is needed), 60-second resend cooldown, at most 5
// OTPs per number per day, and at most 10 OTPs per IP address per hour (against SMS flooding). Only a hash of the code
// is stored. The flow is kept in the session (the number is locked once verified). In SMS TEST MODE (config) no text is
// sent and the code is returned so the page can show it.

const SMS_OTP_TTL = 300;
const SMS_OTP_MAX_ATTEMPTS = 5;
const SMS_OTP_COOLDOWN = 60;
const SMS_OTP_DAILY_LIMIT = 5;
const SMS_OTP_IP_HOURLY_LIMIT = 10;
const SMS_VERIFIED_WINDOW = 900;   // after verifying, the registration must be finished within 15 minutes
const SMS_REG_MIN_AGE = 18;
const SMS_CONSENT_TEXT = 'I agree to let Barangay San Jose use my information to verify my identity and send me barangay announcements, in accordance with the Data Privacy Act of 2012.';

function sms_reg_ready(PDO $connection): bool
{
    static $ready = null;
    if ($ready === null) {
        $tables = (int) $connection->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('sms_subscribers', 'sms_otp_requests')")->fetchColumn();
        $username = (int) $connection->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'username'")->fetchColumn();
        $ready = $tables === 2 && $username === 1;
    }
    return $ready;
}

// 09XXXXXXXXX only → 639XXXXXXXXX, or null.
function sms_reg_mobile(string $raw): ?string
{
    $value = preg_replace('/[\s\-]/', '', trim($raw)) ?? '';
    return preg_match('/^09\d{9}$/', $value) ? '63' . substr($value, 1) : null;
}

// 639171234567 → 0917•••4567
function sms_reg_mask(string $mobile): string
{
    $local = '0' . substr($mobile, 2);
    return substr($local, 0, 4) . '•••' . substr($local, -4);
}

// 639171234567 → 0917 123 4567 (shown only to the person who verified it)
function sms_reg_display(string $mobile): string
{
    $local = '0' . substr($mobile, 2);
    return substr($local, 0, 4) . ' ' . substr($local, 4, 3) . ' ' . substr($local, 7);
}

function sms_reg_ip(): string
{
    return mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function sms_reg_state(): array
{
    return (array) ($_SESSION['sms_reg'] ?? []);
}

function sms_reg_reset(): void
{
    unset($_SESSION['sms_reg']);
}

function sms_reg_sex_options(): array
{
    return ['male' => 'Male', 'female' => 'Female'];
}

function sms_reg_barangay_label(): string
{
    return preg_replace('/^Barangay\s+/i', '', BARANGAY_NAME) . ', ' . BARANGAY_LOCATION;
}

// Step 1 — name, birthdate (18 or older), gender and civil status. Returns [values, errors].
function sms_reg_personal(array $input): array
{
    $validated = residents_validate([
        'first_name' => $input['first_name'] ?? '', 'middle_name' => $input['middle_name'] ?? '', 'last_name' => $input['last_name'] ?? '', 'suffix' => $input['suffix'] ?? '',
        'birth_date' => $input['birth_date'] ?? '', 'sex' => $input['sex'] ?? '', 'civil_status' => $input['civil_status'] ?? '',
        'address' => '-', 'purok' => '1',
    ]);
    $values = array_intersect_key($validated['values'], array_flip(['first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'sex', 'civil_status']));
    $errors = array_intersect_key($validated['errors'], $values);
    if (!isset($errors['sex']) && !array_key_exists((string) $values['sex'], sms_reg_sex_options())) $errors['sex'] = 'Please select your gender.';
    if (!isset($errors['civil_status']) && $values['civil_status'] === null) $errors['civil_status'] = 'Please select your civil status.';
    if (!isset($errors['birth_date']) && (residents_age($values['birth_date']) ?? 0) < SMS_REG_MIN_AGE) $errors['birth_date'] = 'You must be at least 18 years old to create an account. A parent or guardian can request documents for you, or visit the Barangay Hall.';
    [$household, $household_errors] = sms_reg_household($input);
    return [$values + $household, $errors + $household_errors];
}

// Step 2 — house number and zone (optional), street and Purok. Returns [values, errors]; values['address'] is the text
// stored on the resident profile.
function sms_reg_address(array $input): array
{
    $values = [
        'house_no' => residents_collapse((string) ($input['house_no'] ?? '')),
        'zone' => residents_collapse((string) ($input['zone'] ?? '')),
        'street' => residents_collapse((string) ($input['street'] ?? '')),
        'purok' => (string) ($input['purok'] ?? ''),
    ];
    $errors = [];
    if (mb_strlen($values['house_no']) > 20 || ($values['house_no'] !== '' && !preg_match('/^[\p{L}0-9\s.\-\/#]+$/u', $values['house_no']))) $errors['house_no'] = 'Enter a valid house number (up to 20 characters).';
    if (mb_strlen($values['zone']) > 40 || ($values['zone'] !== '' && !preg_match('/^[\p{L}0-9\s.\-]+$/u', $values['zone']))) $errors['zone'] = 'Enter a valid zone (up to 40 characters).';
    if (mb_strlen($values['street']) < 2 || mb_strlen($values['street']) > 150) $errors['street'] = 'Please enter your street or sitio.';
    if (!array_key_exists($values['purok'], residents_purok_options())) $errors['purok'] = 'Please select your Purok.';
    // Same order as the Add Household form: house number, street / sitio, zone, Purok, barangay.
    $parts = array_filter([$values['house_no'], $values['street'], $values['zone']], static fn (string $part): bool => $part !== '');
    $values['address'] = implode(', ', $parts) . ', ' . residents_purok_label($values['purok']) . ', ' . sms_reg_barangay_label();
    return [$values, $errors];
}

// Step 1 (Personal information) — "Your household": the applicant's own statement about their household (staff confirm
// it when they approve the registration). Returns [values, errors].
function sms_reg_household(array $input): array
{
    $values = [];
    $errors = [];
    $values['household_role'] = (string) ($input['household_role'] ?? '');
    $values['household_head_name'] = residents_collapse((string) ($input['household_head_name'] ?? ''));
    $values['household_relationship'] = residents_collapse((string) ($input['household_relationship'] ?? ''));
    $values['household_no'] = mb_strtoupper(residents_collapse((string) ($input['household_no'] ?? '')));
    if (!array_key_exists($values['household_role'], sms_reg_household_roles())) $errors['household_role'] = 'Please tell us about your household.';
    elseif ($values['household_role'] === 'member') {
        if (mb_strlen($values['household_head_name']) < 2 || mb_strlen($values['household_head_name']) > 150 || !preg_match("/^\p{L}[\p{L}\p{M}\s.,'\-]*$/u", $values['household_head_name'])) $errors['household_head_name'] = 'Enter the name of your household head (letters only).';
        if (!in_array($values['household_relationship'], residents_relationships(), true)) $errors['household_relationship'] = 'Select your relationship to the household head.';
        if ($values['household_no'] !== '' && (mb_strlen($values['household_no']) > 50 || !preg_match('/^[A-Z0-9][A-Z0-9\-\/ ]*$/', $values['household_no']))) $errors['household_no'] = 'Enter a valid household number, for example P3-0012.';
    }
    if ($values['household_role'] !== 'member') { $values['household_head_name'] = ''; $values['household_relationship'] = ''; $values['household_no'] = ''; }
    return [$values, $errors];
}

// "Name of the household head" check on the public form. To keep residents' names private, nothing is listed: only a
// full name that matches exactly one active household head (first + last name, with or without the middle name and
// suffix; letter case and extra spaces ignored) returns that household's number and Purok. Several matches are not
// revealed. At most 20 checks per browser session every 10 minutes.
const SMS_REG_HEAD_LOOKUPS = 20;

function sms_reg_head_lookup(PDO $connection, string $name): array
{
    $now = time();
    $log = array_values(array_filter((array) ($_SESSION['sms_reg_head_lookups'] ?? []), static fn ($t): bool => (int) $t > $now - 600));
    if (count($log) >= SMS_REG_HEAD_LOOKUPS) return ['ok' => false, 'found' => false, 'message' => 'Too many checks. Please try again in a few minutes.'];
    $log[] = $now;
    $_SESSION['sms_reg_head_lookups'] = $log;

    $wanted = residents_normalize_name(str_replace([',', '.'], ' ', $name));
    $wanted = residents_collapse($wanted);
    if (mb_strlen($wanted) < 5 || substr_count($wanted, ' ') < 1 || mb_strlen($wanted) > 150) return ['ok' => true, 'found' => false];
    $statement = $connection->prepare("SELECT h.household_no, h.purok, r.first_name, r.middle_name, r.last_name, r.suffix FROM households h INNER JOIN residents r ON r.id = h.household_head_resident_id WHERE r.status = 'active' AND LOCATE(LOWER(TRIM(r.last_name)), :wanted) > 0 LIMIT 200");
    $statement->execute(['wanted' => $wanted]);
    $clean = static fn (?string $value): string => residents_collapse(residents_normalize_name(str_replace([',', '.'], ' ', (string) $value)));
    $matches = [];
    foreach ($statement->fetchAll() as $row) {
        [$first, $middle, $last, $suffix] = [$clean($row['first_name']), $clean($row['middle_name']), $clean($row['last_name']), $clean($row['suffix'])];
        $variants = [
            residents_collapse("$first $middle $last $suffix"), residents_collapse("$first $middle $last"),
            residents_collapse("$first $last $suffix"), residents_collapse("$first $last"),
        ];
        if (in_array($wanted, $variants, true)) $matches[$row['household_no']] = $row;
    }
    if (count($matches) !== 1) return ['ok' => true, 'found' => false];
    $row = reset($matches);
    return ['ok' => true, 'found' => true, 'household_no' => $row['household_no'], 'purok' => residents_purok_label($row['purok'])];
}

function sms_reg_household_roles(): array
{
    return ['head' => 'I am the head of my household', 'member' => 'I am a member of a household', 'unsure' => "Not sure / I'll ask the Barangay Hall"];
}

// Steps 1 and 2 together.
function sms_reg_details(array $input): array
{
    [$personal, $errors] = sms_reg_personal($input);
    [$address, $address_errors] = sms_reg_address($input);
    return [$personal + $address, $errors + $address_errors];
}

// Step 4 — email or username, password and confirmation. Returns [values, errors].
function sms_reg_login(PDO $connection, array $input): array
{
    $login = mb_strtolower(trim((string) ($input['login'] ?? '')));
    $values = ['email' => null, 'username' => null];
    $errors = [];
    if ($login === '') $errors['login'] = 'Please enter an email address or a username.';
    elseif (str_contains($login, '@')) {
        if (mb_strlen($login) > 190 || filter_var($login, FILTER_VALIDATE_EMAIL) === false) $errors['login'] = 'Enter a valid email address.';
        else $values['email'] = $login;
    } elseif (!preg_match('/^[a-z][a-z0-9._]{3,29}$/', $login)) {
        $errors['login'] = 'A username has 4 to 30 characters: letters, numbers, dots or underscores, starting with a letter.';
    } else $values['username'] = $login;
    if (!isset($errors['login'])) {
        $statement = $connection->prepare('SELECT COUNT(*) FROM users WHERE email = :a OR username = :b');
        $statement->execute(['a' => $login, 'b' => $login]);
        if ((int) $statement->fetchColumn() > 0) $errors['login'] = 'This ' . ($values['email'] !== null ? 'email address' : 'username') . ' is already used. Please choose another one.';
    }
    // The person's name (from step 1, kept in the session) and the email / username must not be in the password.
    $person = (array) ($_SESSION['sms_reg']['details'] ?? $_SESSION['sms_reg'] ?? []);
    $password_errors = accounts_password_errors((string) ($input['password'] ?? ''), (string) ($input['confirm_password'] ?? ''), [$login, $input['first_name'] ?? ($person['first_name'] ?? ''), $input['last_name'] ?? ($person['last_name'] ?? '')]);
    if (isset($password_errors['new_password'])) $errors['password'] = $password_errors['new_password'];
    if (isset($password_errors['confirm_password'])) $errors['confirm_password'] = $password_errors['confirm_password'];
    return [$values, $errors];
}

// Cloudflare Turnstile. With Cloudflare's official always-pass test secret (the default in config) the answer is known
// in advance, so no network call is made; with real keys the token is checked with Cloudflare.
function sms_reg_captcha_ok(string $token): bool
{
    if ($token === '') return false;
    if (TURNSTILE_SECRET_KEY === '1x0000000000000000000000000000000AA') return true;
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => http_build_query(['secret' => TURNSTILE_SECRET_KEY, 'response' => $token, 'remoteip' => sms_reg_ip()]), 'timeout' => 10, 'ignore_errors' => true]]);
    try {
        $body = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
        $data = is_string($body) ? json_decode($body, true) : null;
        return is_array($data) && ($data['success'] ?? false) === true;
    } catch (Throwable) {
        return false;
    }
}

// A number already used by a resident profile that has a Pending or Active account cannot sign up again.
function sms_reg_number_has_account(PDO $connection, string $mobile): bool
{
    $statement = $connection->prepare("SELECT COUNT(*) FROM residents r JOIN users u ON u.resident_id = r.id AND u.status IN ('pending', 'active') WHERE REGEXP_REPLACE(COALESCE(r.contact_number, ''), '[^0-9]', '') IN (:local, :intl)");
    $statement->execute(['local' => '0' . substr($mobile, 2), 'intl' => $mobile]);
    return (int) $statement->fetchColumn() > 0;
}

// Checks one step without saving anything (Next buttons). Returns ['ok', 'errors'?, 'message'?].
function sms_reg_check(PDO $connection, array $input): array
{
    $step = (string) ($input['step'] ?? '');
    $errors = match ($step) {
        '1' => sms_reg_personal($input)[1],
        '2' => sms_reg_address($input)[1],
        '4' => sms_reg_login($connection, $input)[1],
        default => ['step' => 'Unknown step.'],
    };
    return $errors === [] ? ['ok' => true] : ['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors];
}

// Step 3a: validate the details, number, consent and CAPTCHA, apply the limits, create and send the OTP.
// Returns ['ok', 'message', 'field'?, 'cooldown'?, 'test_code'?, 'masked'?].
function sms_reg_request_otp(PDO $connection, array $input): array
{
    [, $errors] = sms_reg_details($input);
    if ($errors !== []) return ['ok' => false, 'message' => 'Please go back and correct your details.', 'errors' => $errors];
    $mobile = sms_reg_mobile((string) ($input['mobile'] ?? ''));
    if ($mobile === null) return ['ok' => false, 'message' => 'Invalid number format. Example: 09171234567', 'field' => 'mobile'];
    if ((string) ($input['consent'] ?? '') !== '1') return ['ok' => false, 'message' => 'Please tick the consent box to continue.', 'field' => 'consent'];
    $state = sms_reg_state();
    if (!empty($state['verified']) && ($state['mobile'] ?? '') !== $mobile) return ['ok' => false, 'message' => 'Your number is already verified and can no longer be changed.', 'field' => 'mobile'];
    if (!sms_reg_captcha_ok((string) ($input['captcha'] ?? ''))) return ['ok' => false, 'message' => 'Please complete the CAPTCHA.', 'field' => 'captcha'];
    if (sms_reg_number_has_account($connection, $mobile)) return ['ok' => false, 'message' => 'This number is already used by a resident account. Please log in instead.', 'field' => 'mobile'];

    $connection->beginTransaction();
    try {
        // Lock this number's rows so two requests at the same moment cannot both pass the limits.
        $statement = $connection->prepare('SELECT created_at FROM sms_otp_requests WHERE mobile = :mobile ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $statement->execute(['mobile' => $mobile]);
        $last = $statement->fetchColumn();
        if ($last !== false) {
            $wait = SMS_OTP_COOLDOWN - (time() - strtotime((string) $last));
            if ($wait > 0) { $connection->rollBack(); return ['ok' => false, 'message' => 'Please wait 60 seconds before requesting another OTP.', 'cooldown' => $wait]; }
        }
        $statement = $connection->prepare('SELECT COUNT(*) FROM sms_otp_requests WHERE mobile = :mobile AND created_at >= :today');
        $statement->execute(['mobile' => $mobile, 'today' => date('Y-m-d 00:00:00')]);
        if ((int) $statement->fetchColumn() >= SMS_OTP_DAILY_LIMIT) { $connection->rollBack(); return ['ok' => false, 'message' => "You've reached today's OTP limit. Please try again tomorrow."]; }
        $statement = $connection->prepare('SELECT COUNT(*) FROM sms_otp_requests WHERE requested_ip = :ip AND created_at >= :since');
        $statement->execute(['ip' => sms_reg_ip(), 'since' => date('Y-m-d H:i:s', time() - 3600)]);
        if ((int) $statement->fetchColumn() >= SMS_OTP_IP_HOURLY_LIMIT) { $connection->rollBack(); return ['ok' => false, 'message' => 'Too many OTP requests from this connection. Please try again later.']; }

        $code = (string) random_int(100000, 999999);
        $token = bin2hex(random_bytes(32));
        $connection->prepare('INSERT INTO sms_otp_requests (mobile, code_hash, request_token, expires_at, requested_ip) VALUES (:mobile, :hash, :token, :expires, :ip)')
            ->execute(['mobile' => $mobile, 'hash' => password_hash($code, PASSWORD_DEFAULT), 'token' => $token, 'expires' => date('Y-m-d H:i:s', time() + SMS_OTP_TTL), 'ip' => sms_reg_ip()]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }

    $sent = sms_gateway_send([$mobile], 'Your ' . BARANGAY_NAME . ' verification code is ' . $code . '. It is valid for 5 minutes. Do not share this code with anyone.');
    if (!$sent) return ['ok' => false, 'message' => 'The OTP could not be sent right now. Please try again later or register at the Barangay Hall.'];
    $_SESSION['sms_reg'] = ['mobile' => $mobile, 'token' => $token, 'verified' => false];
    $result = ['ok' => true, 'message' => 'An OTP has been sent to your number.', 'cooldown' => SMS_OTP_COOLDOWN, 'expires_in' => SMS_OTP_TTL, 'masked' => sms_reg_mask($mobile)];
    if (SMS_TEST_MODE) $result['test_code'] = $code;
    return $result;
}

// Step 3b: check the code. Returns ['ok', 'message', 'attempts_left'?, 'need_new'?, 'display'?].
function sms_reg_verify(PDO $connection, string $code): array
{
    $state = sms_reg_state();
    if (empty($state['token'])) return ['ok' => false, 'message' => 'Please request an OTP first.', 'need_new' => true];
    if (!empty($state['verified'])) return ['ok' => true, 'message' => 'Your number is verified.', 'display' => sms_reg_display((string) $state['mobile'])];
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) return ['ok' => false, 'message' => 'Please enter the 6-digit code.', 'field' => 'code'];
    $connection->beginTransaction();
    try {
        $statement = $connection->prepare('SELECT id, code_hash, attempts, expires_at, verified_at FROM sms_otp_requests WHERE request_token = :token AND mobile = :mobile LIMIT 1 FOR UPDATE');
        $statement->execute(['token' => $state['token'], 'mobile' => $state['mobile']]);
        $otp = $statement->fetch();
        if (!$otp) { $connection->rollBack(); return ['ok' => false, 'message' => 'Please request an OTP first.', 'need_new' => true]; }
        if ((int) $otp['attempts'] >= SMS_OTP_MAX_ATTEMPTS) { $connection->rollBack(); return ['ok' => false, 'message' => 'No attempts left. Please request a new OTP.', 'need_new' => true, 'attempts_left' => 0]; }
        if (strtotime((string) $otp['expires_at']) < time()) { $connection->rollBack(); return ['ok' => false, 'message' => 'This code has expired. Please request a new one.', 'need_new' => true]; }
        if (!password_verify($code, (string) $otp['code_hash'])) {
            $attempts = (int) $otp['attempts'] + 1;
            $connection->prepare('UPDATE sms_otp_requests SET attempts = :attempts WHERE id = :id')->execute(['attempts' => $attempts, 'id' => $otp['id']]);
            $connection->commit();
            $left = SMS_OTP_MAX_ATTEMPTS - $attempts;
            if ($left <= 0) return ['ok' => false, 'message' => 'No attempts left. Please request a new OTP.', 'need_new' => true, 'attempts_left' => 0];
            return ['ok' => false, 'message' => 'Incorrect code. You have ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.', 'attempts_left' => $left, 'field' => 'code'];
        }
        $connection->prepare('UPDATE sms_otp_requests SET verified_at = NOW() WHERE id = :id')->execute(['id' => $otp['id']]);
        $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
    $_SESSION['sms_reg']['verified'] = true;
    $_SESSION['sms_reg']['verified_time'] = time();
    return ['ok' => true, 'message' => 'Your number is verified.', 'display' => sms_reg_display((string) $state['mobile'])];
}

// Step 4: create the Pending resident profile (or link the one matching profile) and the Pending account for the
// verified number. The number comes from the session, never from the form; every step is validated again here.
function sms_reg_register(PDO $connection, array $input): array
{
    $state = sms_reg_state();
    if (empty($state['verified']) || empty($state['token'])) return ['ok' => false, 'message' => 'Please verify your mobile number first.', 'step' => 3];
    if (time() - (int) ($state['verified_time'] ?? 0) > SMS_VERIFIED_WINDOW) { sms_reg_reset(); return ['ok' => false, 'message' => 'Your verification has expired. Please request a new OTP.', 'need_new' => true, 'step' => 3]; }
    [$details, $errors] = sms_reg_details($input);
    if ($errors !== []) return ['ok' => false, 'message' => 'Please go back and correct your details.', 'errors' => $errors];
    if ((string) ($input['consent'] ?? '') !== '1') return ['ok' => false, 'message' => 'Please tick the consent box to continue.', 'step' => 3];
    [$login, $login_errors] = sms_reg_login($connection, $input);
    if ($login_errors !== []) return ['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $login_errors];
    $mobile = (string) $state['mobile'];
    $local = '0' . substr($mobile, 2);
    $name = residents_full_name($details);

    $connection->beginTransaction();
    try {
        $statement = $connection->prepare('SELECT id FROM sms_otp_requests WHERE request_token = :token AND mobile = :mobile AND verified_at IS NOT NULL AND used_at IS NULL LIMIT 1 FOR UPDATE');
        $statement->execute(['token' => $state['token'], 'mobile' => $mobile]);
        $otp_id = $statement->fetchColumn();
        if ($otp_id === false) { $connection->rollBack(); sms_reg_reset(); return ['ok' => false, 'message' => 'Please request a new OTP.', 'need_new' => true, 'step' => 3]; }
        if (sms_reg_number_has_account($connection, $mobile)) { $connection->rollBack(); sms_reg_reset(); return ['ok' => false, 'message' => 'This number is already used by a resident account. Please log in instead.']; }

        // Same name and birthdate as an existing profile: link to it when it is the only one and has no account yet.
        $exact = residents_duplicates($connection, $details)['exact'];
        $linked = false;
        if (count($exact) > 1) { $connection->rollBack(); return ['ok' => false, 'message' => 'We could not match your details to a single resident record. Please visit the Barangay Hall to get your account.']; }
        if (count($exact) === 1) {
            $resident_id = (int) $exact[0]['id'];
            $statement = $connection->prepare('SELECT COUNT(*) FROM users WHERE resident_id = :id FOR UPDATE');
            $statement->execute(['id' => $resident_id]);
            if ((int) $statement->fetchColumn() > 0) { $connection->rollBack(); return ['ok' => false, 'message' => 'An account already exists for this resident. Please log in, or visit the Barangay Hall if you forgot your password.']; }
            if (in_array($exact[0]['status'], ['moved', 'deceased'], true)) { $connection->rollBack(); return ['ok' => false, 'message' => 'We could not create your account online. Please visit the Barangay Hall.']; }
            $linked = true;
        } else {
            $connection->prepare("INSERT INTO residents (first_name, middle_name, last_name, suffix, birth_date, sex, civil_status, contact_number, address, purok, status) VALUES (:first_name, :middle_name, :last_name, :suffix, :birth_date, :sex, :civil_status, :contact, :address, :purok, 'pending')")
                ->execute(array_intersect_key($details, array_flip(['first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'sex', 'civil_status', 'address', 'purok'])) + ['contact' => $local]);
            $resident_id = (int) $connection->lastInsertId();
        }

        $connection->prepare("INSERT INTO users (name, email, username, password_hash, role, status, resident_id) VALUES (:name, :email, :username, :hash, 'resident', 'pending', :resident_id)")
            ->execute(['name' => $name, 'email' => $login['email'], 'username' => $login['username'], 'hash' => password_hash((string) $input['password'], PASSWORD_DEFAULT), 'resident_id' => $resident_id]);
        $user_id = (int) $connection->lastInsertId();
        if (!$linked) $connection->prepare('UPDATE residents SET user_id = :user_id WHERE id = :id')->execute(['user_id' => $user_id, 'id' => $resident_id]);

        // Announcement texts for the verified number (stated in the consent).
        $statement = $connection->prepare('SELECT id, status FROM sms_subscribers WHERE mobile = :mobile LIMIT 1 FOR UPDATE');
        $statement->execute(['mobile' => $mobile]);
        $subscriber = $statement->fetch();
        $row = ['name' => $name, 'purok' => $details['purok'], 'street' => $details['street'], 'consent' => SMS_CONSENT_TEXT, 'ip' => sms_reg_ip()];
        if (!$subscriber) $connection->prepare("INSERT INTO sms_subscribers (full_name, purok, street, mobile, consent_text, consent_at, verified_at, status, registered_ip) VALUES (:name, :purok, :street, :mobile, :consent, NOW(), NOW(), 'active', :ip)")->execute($row + ['mobile' => $mobile]);
        elseif ($subscriber['status'] !== 'active') $connection->prepare("UPDATE sms_subscribers SET full_name = :name, purok = :purok, street = :street, consent_text = :consent, consent_at = NOW(), verified_at = NOW(), status = 'active', registered_ip = :ip WHERE id = :id")->execute($row + ['id' => $subscriber['id']]);

        // The application staff review in Resident Registrations.
        $application_id = reg_create($connection, $details, $user_id, $resident_id, $login['email'], $local);

        $connection->prepare('UPDATE sms_otp_requests SET used_at = NOW() WHERE id = :id')->execute(['id' => $otp_id]);
        security_log('resident_self_registered', null, ['name' => $name, 'application_id' => $application_id, 'resident_id' => $resident_id,'resident_record' => $linked ? 'existing' : 'new', 'login' => $login['email'] !== null ? 'email' : 'username', 'purok' => $details['purok'], 'mobile' => sms_reg_mask($mobile)], 'user', $user_id);
        $connection->commit();
    } catch (PDOException $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        if (($exception->errorInfo[1] ?? 0) === 1062) return ['ok' => false, 'message' => 'This email address or username is already used. Please choose another one.', 'errors' => ['login' => 'This email address or username is already used. Please choose another one.']];
        throw $exception;
    }
    sms_reg_reset();
    return ['ok' => true, 'message' => 'Account created.', 'name' => $details['first_name']];
}
