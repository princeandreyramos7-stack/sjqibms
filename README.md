# SJQIBMS

Barangay San Jose Management System for Quirino, Isabela.

This repository contains the initial capstone foundation only. The dashboard currently uses clearly labeled sample values; resident, document, complaint, finance, and other module workflows will be added in later milestones.

## Requirements

- XAMPP with Apache, MySQL/MariaDB, and PHP 8.2+
- A modern browser

## Local installation

1. Copy or keep this project in `C:\xampp\htdocs\SJQIBMS`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Create/import the database by opening `database/schema.sql` in phpMyAdmin's Import tab. The script creates the `sjqibms` database and initial tables.
5. Review `config/config.php`. The default XAMPP connection is `root` with an empty password. Change it to match your local MySQL account.
6. Visit `http://localhost/SJQIBMS/`.
7. Use the development-only account shown below, then change or remove it before real use:

   - Email: `admin@san-jose.local`
   - Password: `password`

## Security notes

- PDO uses prepared statements, native prepares, and `utf8mb4`.
- Passwords are verified with `password_verify()` and should be created with `password_hash()`.
- Login sessions use HTTP-only, SameSite cookies and regenerate the session ID after login.
- State-changing forms use a session-backed CSRF token.
- Authentication and role checks run on the server through `includes/auth.php`.
- Sensitive actions have an `audit_logs` table ready for use.
- `push_subscriptions` stores future Firebase Cloud Messaging/Web Push subscription data per authenticated user. Firebase is intentionally not integrated yet.
- Set `SESSION_SECURE` to `true` and deploy behind HTTPS before production use. Never expose database credentials or Firebase credentials in frontend code.

## Initial structure

- `config/` - application and PDO database configuration
- `database/` - initial schema and development seed account
- `includes/` - authentication, CSRF, and shared helper functions
- `layout/` - header, sidebar, top navigation, and footer partials
- `assets/` - responsive CSS and small navigation JavaScript
- `login.php`, `logout.php`, `dashboard.php` - initial application entry points

## Live Search standard

Every SJQIBMS search bar and list filter updates results automatically; users do not press Search or Apply. New modules must reuse the shared helper instead of writing their own search JavaScript.

How it works: the helper in `assets/js/app.js` re-requests the page's own URL with the `X-Live-Search: 1` header. The page runs its normal authentication, authorization, validation and SQL, then `includes/live_search.php` returns only the results fragment. There is no separate endpoint, so a live response can never show more than the full page would.

1. Render the results through one closure, used by both the full page and live responses:

   ```php
   require_once __DIR__ . '/includes/live_search.php';
   // ...authorization, filtering, COUNT and LIMIT/OFFSET queries as usual...
   $render_results = static function () use ($records /* , ... */): void { ?>
       <!-- empty state ("No matching records found."), result count, rows/cards, pagination -->
   <?php };
   if (live_search_is_request()) live_search_respond($render_results);
   ```

2. Configure the GET filter form with data attributes and place the results container after it:

   ```html
   <form method="get" action="module.php" data-live-search data-live-target="#module-results">
       <input type="search" name="q" data-live-query>              <!-- debounced while typing -->
       <select name="status">...</select>                         <!-- applied immediately on change -->
       <button type="submit" data-live-submit>Apply</button>       <!-- hidden by the helper; no-JavaScript fallback -->
       <a href="module.php" data-live-reset>Reset</a>             <!-- clears fields and reloads results -->
   </form>
   <div id="module-results" class="live-search-results"><?php $render_results(); ?></div>
   ```

3. Add `data-live-page` to pagination links inside the results. They are built on the server with the current search and filters, and the helper loads them in place.

| Attribute | Purpose | Default |
|---|---|---|
| `data-live-target` | Selector of the results container (required) | — |
| `data-live-debounce` | Milliseconds after the last keystroke | `300` |
| `data-live-min` | Minimum non-whitespace characters before querying (use `2` for resident lookups) | `0` |
| `data-live-min-message` | Message shown below the minimum | `Type at least N characters to search.` |
| `data-live-range` | Two date inputs, e.g. `#from,#to`; incomplete or reversed ranges are not queried | — |
| `data-live-status` | Selector of an existing status element (otherwise one is created after the form) | — |
| `data-live-error` | Message shown when a request fails (existing results are kept, with a Retry button) | generic message |

Rules: live responses are GET-only and never write data; filter changes reset to page 1; older responses never overwrite newer ones (AbortController plus request sequencing); the browser URL is updated so reloads keep the current search. Buttons inside results that open confirmation dialogs work automatically because those dialogs use delegated listeners. For forms added after page load, call `window.SJQIBMS.initLiveSearch(form)`.

## Planned next milestones

1. User approval and account management
2. Resident records and household management
3. Document requests and printable verification-ready documents
4. Complaints/blotter and restricted case access
5. Announcements and notification preferences
6. Firebase Cloud Messaging integration after the subscription and authorization flows are tested
