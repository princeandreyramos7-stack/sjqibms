<?php
declare(strict_types=1);

/**
 * Semaphore SMS helper
 *
 * Public surface:
 *   sms_send_announcement(PDO $connection, array $announcement): void
 *
 * All functions are intentionally fire-and-forget: a failure never throws
 * and never affects the calling transaction.
 */

require_once __DIR__ . '/../config/config.php';

// ── Number normalisation ──────────────────────────────────────────────────────

/**
 * Normalise a Philippine mobile number to the 63XXXXXXXXXX format Semaphore
 * requires, or return null when the number cannot be parsed as a valid PH mobile.
 *
 * Accepted inputs: 09XXXXXXXXX, 9XXXXXXXXX, +639XXXXXXXXX, 639XXXXXXXXX
 */
function sms_normalise_number(string $raw): ?string
{
    // Strip everything that is not a digit or leading +
    $cleaned = preg_replace('/[^\d+]/', '', $raw) ?? '';
    // Remove leading +
    $cleaned = ltrim($cleaned, '+');

    // 639XXXXXXXXX (already full format, 12 digits)
    if (preg_match('/^639\d{9}$/', $cleaned)) {
        return $cleaned;
    }
    // 09XXXXXXXXX (11 digits, local format)
    if (preg_match('/^09\d{9}$/', $cleaned)) {
        return '63' . substr($cleaned, 1);
    }
    // 9XXXXXXXXX (10 digits, no leading 0)
    if (preg_match('/^9\d{9}$/', $cleaned)) {
        return '63' . $cleaned;
    }

    return null; // not a recognisable PH mobile number
}

// ── SMS text builder ──────────────────────────────────────────────────────────

/**
 * Build the SMS body for an announcement.
 * Semaphore counts a standard SMS as 160 characters per credit.
 */
function sms_announcement_message(array $announcement): string
{
    $title   = mb_strtoupper(trim((string) ($announcement['title'] ?? '')), 'UTF-8');
    $body    = trim(strip_tags((string) ($announcement['body'] ?? '')));
    $body    = preg_replace('/\s+/', ' ', $body) ?? $body;

    // Format: "TITLE\n\nBODY" — caps title, blank line separator, then body.
    // Allow up to 3 SMS credits (480 chars); Semaphore splits automatically.
    $message = $title . "\n\n" . $body;
    $max     = 480;

    return mb_strlen($message, 'UTF-8') <= $max
        ? $message
        : mb_strimwidth($message, 0, $max - 3, '...', 'UTF-8');
}

// ── Recipient query ───────────────────────────────────────────────────────────

/**
 * Return an array of normalised phone numbers for residents who should receive
 * an SMS for this announcement, based on the audience setting.
 *
 * Only residents with a non-empty contact_number that parses as a valid PH
 * mobile number are included.
 *
 * This mirrors the audience logic in announcement_notification_recipients()
 * but queries residents.contact_number instead of users.id.
 */
function sms_announcement_recipients(PDO $connection, array $announcement): array
{
    $audience     = (string) ($announcement['audience'] ?? '');
    $target_purok = (string) ($announcement['target_purok'] ?? '');

    // Base query: join users → residents to get contact numbers for active,
    // resident-role accounts that have a linked resident record with a phone.
    $base = "SELECT DISTINCT r.contact_number
             FROM users u
             INNER JOIN residents r ON r.id = u.resident_id
             WHERE u.status = 'active'
               AND r.status = 'active'
               AND r.contact_number IS NOT NULL
               AND r.contact_number <> ''";

    try {
        switch ($audience) {
            case 'public':
            case 'all_residents':
                // All active residents with a linked active user account
                $statement = $connection->query($base);
                break;

            case 'purok':
                if ($target_purok === '') {
                    return [];
                }
                $statement = $connection->prepare($base . " AND r.purok = :purok");
                $statement->execute(['purok' => $target_purok]);
                break;

            case 'barangay_officials':
                // Officials and kagawads (role = 'official')
                $statement = $connection->query($base . " AND u.role IN ('official')");
                break;

            case 'kagawads':
                $statement = $connection->query($base . " AND u.role = 'official'");
                break;

            case 'all_staff':
                $statement = $connection->query($base . " AND u.role IN ('official', 'secretary', 'treasurer')");
                break;

            default:
                // 'selected_users' or unknown — do not send SMS
                return [];
        }

        $raw = $statement->fetchAll(PDO::FETCH_COLUMN);
        // Residents who registered on the public SMS page (sms_register.php, verified by OTP) also receive announcements
        // for everyone or for their Purok; duplicates with resident accounts are removed below.
        $raw = array_merge($raw, sms_subscriber_numbers($connection, $audience, $target_purok));
    } catch (Throwable) {
        return [];
    }

    // Normalise and deduplicate
    $numbers = [];
    foreach ($raw as $number) {
        $normalised = sms_normalise_number((string) $number);
        if ($normalised !== null && !in_array($normalised, $numbers, true)) {
            $numbers[] = $normalised;
        }
    }

    return $numbers;
}

/**
 * Mobile numbers of active SMS subscribers for an audience: all of them for 'public' / 'all_residents', the ones in the
 * Purok for 'purok', none for staff-only audiences. Empty when the sms_subscribers table does not exist yet.
 */
function sms_subscriber_numbers(PDO $connection, string $audience, string $target_purok): array
{
    if (!in_array($audience, ['public', 'all_residents', 'purok'], true)) return [];
    try {
        if ($audience === 'purok') {
            if ($target_purok === '') return [];
            $statement = $connection->prepare("SELECT mobile FROM sms_subscribers WHERE status = 'active' AND purok = :purok");
            $statement->execute(['purok' => $target_purok]);
        } else {
            $statement = $connection->query("SELECT mobile FROM sms_subscribers WHERE status = 'active'");
        }
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable) {
        return [];
    }
}

// ── Sending (the one place where the SMS provider is called) ─────────────────

/**
 * Send one message to one or more numbers (639XXXXXXXXX). This is the only function that talks to the SMS provider:
 * to switch providers, change it here. In SMS TEST MODE (config SMS_TEST_MODE) nothing is sent and true is returned —
 * the OTP page shows the code on screen instead.
 */
function sms_gateway_send(array $numbers, string $message): bool
{
    if ($numbers === []) return true;
    if (SMS_TEST_MODE) return true;
    return semaphore_send($numbers, $message);
}

// ── Semaphore API call ────────────────────────────────────────────────────────

/**
 * Send a single SMS message to one or more recipients via Semaphore.
 *
 * Semaphore's /messages endpoint accepts a comma-separated list of numbers
 * (up to 1,000 per request).
 *
 * Returns true on HTTP 200/201, false on any error.
 * Never throws.
 */
function semaphore_send(array $numbers, string $message): bool
{
    if ($numbers === []) {
        return true;
    }

    $payload = http_build_query([
        'apikey'      => SEMAPHORE_API_KEY,
        'number'      => implode(',', $numbers),
        'message'     => $message,
        'sendername'  => SEMAPHORE_SENDER_NAME,
    ]);

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($payload) . "\r\n",
            'content'       => $payload,
            'timeout'       => 15,
            'ignore_errors' => true,   // lets us read the body even on 4xx/5xx
        ],
    ]);

    try {
        $response = file_get_contents('https://api.semaphore.co/api/v4/messages', false, $context);
        // $http_response_header is set by file_get_contents when using HTTP context
        $status_line = $http_response_header[0] ?? '';
        preg_match('/HTTP\/\S+\s+(\d+)/', $status_line, $matches);
        $code = (int) ($matches[1] ?? 0);
        return $response !== false && in_array($code, [200, 201], true);
    } catch (Throwable) {
        return false;
    }
}

// ── Public entry point ────────────────────────────────────────────────────────

/**
 * Send an SMS notification to all eligible residents when an announcement is
 * published.
 *
 * Called after the DB transaction commits.  Any failure is silently ignored
 * so it never disrupts the publish workflow.
 */
function sms_send_announcement(PDO $connection, array $announcement): void
{
    // SMS is skipped for audience types that do not map to residents with phones
    if (($announcement['audience'] ?? '') === 'selected_users') {
        return;
    }

    $numbers = sms_announcement_recipients($connection, $announcement);
    if ($numbers === []) {
        return;
    }

    $message = sms_announcement_message($announcement);

    // Semaphore allows up to 1,000 numbers per request; chunk just in case
    foreach (array_chunk($numbers, 1000) as $batch) {
        sms_gateway_send($batch, $message);
    }
}
