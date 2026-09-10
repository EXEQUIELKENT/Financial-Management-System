<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    // Cookie flags have to be set before the session starts. Secure is driven by
    // the *client's* scheme (see request_is_https()), because behind a TLS-terminating
    // proxy PHP sees plain HTTP and would otherwise never mark the cookie secure --
    // while on local XAMPP over HTTP a hardcoded secure flag would drop it entirely.
    session_set_cookie_params([
        'path' => BASE_URL === '' ? '/' : BASE_URL . '/',
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
