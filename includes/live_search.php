<?php
declare(strict_types=1);

// Live Search standard (see README): a page answers its own URL with only its results fragment when the shared
// JavaScript helper sends the X-Live-Search header. The page's normal authentication, authorization, input validation
// and SQL run first, so live responses can never contain more than the full page would show. Read-only (GET) only.
function live_search_is_request(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($_SERVER['HTTP_X_LIVE_SEARCH'] ?? '') === '1';
}

function live_search_respond(callable $render_results): never
{
    header('Content-Type: text/html; charset=utf-8');
    // The same URL also serves the full page, so fragments must never be cached or reused for normal navigation.
    header('Cache-Control: no-store');
    header('Vary: X-Live-Search');
    header('X-Live-Search-Fragment: 1');
    $render_results();
    exit;
}
