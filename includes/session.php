<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    // Cookie flags have to be set before the session starts. Secure is driven by
    // the *client's* scheme (see request_is_https()), because behind a TLS-terminating
    // proxy PHP sees plain HTTP and would otherwise never mark the cookie secure --
    // while on local XAMPP over HTTP a hardcoded secure flag would drop it entirely.
    // Path '/' deliberately, not the app subfolder: cookie path matching is
    // case-sensitive (RFC 6265) while Windows/XAMPP folder paths and
    // user-typed URLs are not. A subfolder-scoped path silently drops the
    // session cookie the moment the URL case differs from the on-disk folder
    // case (e.g. /financial-management-system/... vs /Financial-Management-System/),
    // which shows up as "CSRF check failed" on every login attempt even though
    // the pages render fine. The session name is unique per app, so sibling
    // apps on the same host are unaffected by the root-wide path.
    session_set_cookie_params([
        'path' => '/',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name(env_value('SESSION_NAME', 'TRAVELCORE_FMS'));
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > IDLE_TIMEOUT_SECONDS) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}
