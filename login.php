<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/site.php';   // office hours shown under the Resident login
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/admin_gate.php';
require_once __DIR__ . '/includes/navigation.php';

if (is_authenticated()) {
    redirect('dashboard.php');
}

// Each office signs in through its own portal; the chosen portal decides which account role is accepted.
$portal_key = (string) ($_GET['portal'] ?? '');
$portal = staff_portal($portal_key);
if ($portal === null) {
    redirect('index.php');
}

// Staff portals: the Admin Access Gate comes before every login page view. Opening the page uses up the gate pass
// and issues a one-time token for this form; a missing or stale token sends the visitor back to the gate.
$login_token = '';
if ($portal['gate']) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!portal_login_token_valid($portal_key, (string) ($_POST['login_token'] ?? ''))) {
            admin_gate_clear();
            redirect('admin_gate.php?portal=' . $portal_key);
        }
        $login_token = (string) $_POST['login_token'];
    } elseif (admin_gate_consume($portal_key)) {
        $login_token = portal_login_token_issue($portal_key);
    } else {
        admin_gate_clear();
        redirect('admin_gate.php?portal=' . $portal_key);
    }
}

$error = flash('error');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Your session token expired. Please try again.';
    } else {
        // Residents may also sign in with the username chosen at sign-up (register.php); staff sign in with their email.
        $identifier = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $email = str_contains($identifier, '@') ? filter_var($identifier, FILTER_VALIDATE_EMAIL) : false;
        $username = !$portal['gate'] && preg_match('/^[a-z][a-z0-9._]{3,29}$/', $identifier) ? $identifier : null;
        $password = (string) ($_POST['password'] ?? '');

        if ((!$email && $username === null) || $password === '') {
            $error = $portal['gate'] ? 'Enter a valid email address and password.' : 'Enter your email address or username and your password.';
        } else {
            try {
                $statement = db()->prepare('SELECT id, name, email, password_hash, role, status FROM users WHERE ' . ($email ? 'email = :login' : 'username = :login') . ' LIMIT 1');
                $statement->execute(['login' => $email ?: $username]);
                $user = $statement->fetch();
                $email = $email ?: $username;
            } catch (PDOException) {
                $user = null;
                $error = 'The database is not ready yet. Import database/schema.sql and try again.';
            }

            // Too many failed sign-ins on this account or from this device: refuse before checking the password.
            $locked = !$error ? login_lock_seconds(db(), $user ? (int) $user['id'] : null) : 0;
            if ($locked > 0) {
                $error = 'Too many failed sign-in attempts. Please try again in ' . max(1, (int) ceil($locked / 60)) . ' minute' . ($locked > 60 ? 's' : '') . '.';
                security_log('auth_login_locked', $user ? (int) $user['id'] : null, ['portal' => $portal_key, 'email' => mb_substr((string) $email, 0, 190)]);
            } elseif (!$error && $user && $user['status'] === 'pending' && $user['role'] === $portal['role'] && password_verify($password, $user['password_hash'])) {
                // Only someone who knows the password learns that the account is waiting for approval.
                $error = 'Your account is still waiting for approval from the Barangay Hall. You can log in once it is approved.';
                security_log('auth_login_failed', (int) $user['id'], ['portal' => $portal_key, 'email' => mb_substr((string) $email, 0, 190), 'reason' => 'account_pending']);
            } elseif (!$error && (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash']))) {
                $error = 'The email or password is incorrect.';
                // Recorded for the System Administrator; the password itself is never logged.
                $reason = !$user ? 'unknown_account' : ($user['status'] !== 'active' ? 'account_' . $user['status'] : 'wrong_password');
                security_log('auth_login_failed', $user ? (int) $user['id'] : null, ['portal' => $portal_key, 'email' => mb_substr((string) $email, 0, 190), 'reason' => $reason]);
            } elseif (!$error && $user['role'] !== $portal['role']) {
                // Valid credentials for a different office: refuse here so each portal only admits its own role.
                $error = 'This account is not authorized for the ' . $portal['title'] . ' portal. Please sign in through your assigned portal.';
                security_log('auth_login_failed', (int) $user['id'], ['portal' => $portal_key, 'email' => mb_substr((string) $email, 0, 190), 'reason' => 'wrong_portal']);
            } elseif (!$error) {
                login_user($user);
                // A password set by someone else must be changed first (includes/auth.php password_change_guard()).
                try {
                    require_once __DIR__ . '/includes/accounts.php';
                    if (accounts_must_change_ready(db())) {
                        $flag = db()->prepare('SELECT must_change_password FROM users WHERE id = :id');
                        $flag->execute(['id' => $user['id']]);
                        if ((int) $flag->fetchColumn() === 1) $_SESSION['user']['must_change_password'] = true;
                    }
                } catch (PDOException) {}
                security_log('auth_login', (int) $user['id'], ['portal' => $portal_key, 'role' => $user['role']]);
                try { db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]); } catch (PDOException) {}
                admin_gate_clear();
                redirect('dashboard.php');
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($portal['short']) ?> Login | Barangay San Jose</title>
    <link rel="icon" href="assets/img/barangay-san-jose-logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/landing.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/landing.css') ?>" rel="stylesheet">
</head>
<body class="landing portal">
<div class="portal-page">
    <section class="portal-visual" aria-hidden="true">
        <a class="portal-brand" href="index.php" tabindex="-1"><img src="assets/img/barangay-san-jose-logo.jpg" alt=""><span><strong>Barangay San Jose</strong><small>Quirino, Isabela</small></span></a>
        <div class="portal-visual-copy">
            <span class="landing-hero-eyebrow"><span class="landing-dot"></span><?= e($portal['title']) ?> Portal</span>
            <h1>Connected service for a stronger community.</h1>
            <p><?= e($portal['summary']) ?></p>
            <ul>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg><?= $portal['gate'] ? 'Role-based access enforced on the server' : 'Your personal information stays private to your account' ?></li>
                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5M12 7v5l3 2"/></svg><?= $portal['gate'] ? 'Every change is recorded in the audit log' : 'Track the status of your document requests online' ?></li>
            </ul>
        </div>
        <p class="portal-visual-foot">© <?= e(date('Y')) ?> Barangay San Jose · SJQIBMS</p>
    </section>
    <main class="portal-form-area">
        <div class="portal-card">
            <a class="portal-back" href="index.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>Back to website</a>
            <div class="portal-logo-row">
                <img class="portal-logo" src="assets/img/barangay-san-jose-logo.jpg" alt="Barangay San Jose seal">
                <span class="portal-logo-name">SJQIBMS</span>
            </div>
            <span class="portal-role-badge"><?= icon_svg($portal['icon']) ?><?= e($portal['title']) ?></span>
            <h2><?= e($portal['short']) ?> Login</h2>
            <p class="portal-sub">Sign in with your <?= e($portal['title']) ?> account.</p>
            <?php if ($error): ?><div class="portal-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <?php if ($portal['gate']): ?><input type="hidden" name="login_token" value="<?= e($login_token) ?>"><?php endif; ?>
                <label class="portal-label" for="email"><?= $portal['gate'] ? 'Email address' : 'Email or username' ?></label>
                <div class="portal-field">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                    <input type="<?= $portal['gate'] ? 'email' : 'text' ?>" id="email" name="email" value="<?= old('email') ?>" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="<?= $portal['gate'] ? 'name@barangay.gov.ph' : 'name@example.com or username' ?>" required>
                </div>
                <label class="portal-label" for="password">Password</label>
                <div class="portal-field">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    <input type="password" id="password" name="password" autocomplete="current-password" placeholder="Enter your password" required>
                    <button class="portal-reveal" type="button" aria-label="Show password" aria-pressed="false" data-password-toggle>Show</button>
                </div>
                <button class="portal-submit" type="submit">Sign in <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
            </form>
            <?php if (!$portal['gate']): ?>
                <div class="portal-or" role="separator" aria-label="or"><span>or</span></div>
                <section class="portal-register" aria-labelledby="portal-register-title">
                    <h3 class="portal-register-title" id="portal-register-title">Don't have an account yet?</h3>
                    <a class="portal-register-btn" href="register.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6M19 8v6M16 11h6"/></svg>Create an account</a>
                </section>
                <a class="portal-switch is-centered" href="index.php">Not a resident? Choose another portal</a>
                <p class="portal-note is-centered">Forgot your password or need help registering? Visit the Barangay Hall during office hours (<?= e(defined('BARANGAY_OFFICE_HOURS') ? BARANGAY_OFFICE_HOURS : 'Monday to Friday, 8:00 AM – 5:00 PM') ?>).</p>
            <?php else: ?>
                <a class="portal-switch" href="index.php">Not a <?= e($portal['title']) ?>? Choose another portal</a>
                <p class="portal-note">Only active <?= e($portal['title']) ?> accounts can sign in here. Access to each module is limited to this office and enforced on the server.</p>
            <?php endif; ?>
        </div>
    </main>
</div>
<script>
// Show/hide password; the field remains a normal password input for submission and password managers.
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = button.parentElement.querySelector('input');
    button.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Hide' : 'Show';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
});
</script>
</body>
</html>
