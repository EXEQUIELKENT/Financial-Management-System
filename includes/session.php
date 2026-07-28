<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
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
