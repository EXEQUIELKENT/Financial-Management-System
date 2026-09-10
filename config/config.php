<?php
/**
 * Application + environment configuration.
 *
 * Every deployment-sensitive value is read from the environment first and only
 * falls back to the XAMPP-friendly local default, so the exact same code runs
 * unchanged on a laptop (htdocs subfolder, MySQL on localhost) and on a hosted
 * container (document root, managed MySQL, HTTPS terminated by a proxy).
 */

// Composer's autoloader is optional -- the app uses require_once throughout and
// has no runtime dependencies -- but load it when the build produced one.
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Reads an environment value, tolerating both real env vars and values injected
 * into $_ENV/$_SERVER (which is how some PHP-FPM/Apache setups expose them).
 * Empty strings count as "not set" so a blank platform field can't wipe a default.
 */
function env_value(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    return ($value === null || $value === '') ? $default : (string)$value;
}

define('APP_TITLE', 'Intelligent Integrated Financial Management System');
define('APP_FULL_TITLE', 'Design and Development of an Intelligent Integrated Financial Management System with AI Financial Assistance, Predictive Analysis, and Decision Support for Travel and Tour Agencies');
define('APP_SHORT_NAME', 'TravelCore FMS');
define('APP_TAGLINE', 'Travel & Tours');

// 'production' hides PHP errors from visitors; anything else keeps them on screen.
define('APP_ENV', env_value('APP_ENV', 'development'));

define('DB_HOST', env_value('DB_HOST', 'localhost'));
define('DB_PORT', (int)env_value('DB_PORT', '3306'));
define('DB_NAME', env_value('DB_NAME', 'travelcore_fms'));
define('DB_USER', env_value('DB_USER', 'root'));
define('DB_PASS', env_value('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

date_default_timezone_set(env_value('APP_TIMEZONE', 'Asia/Manila'));

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

/**
 * True when the original client request used HTTPS. Behind a load balancer that
 * terminates TLS (which is how the hosted deployment runs) PHP only ever sees a
 * plain HTTP request, so the X-Forwarded-Proto header is the only honest signal.
 */
function request_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') return true;
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
    $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if ($forwarded !== '') {
        // May be a comma-separated chain ("https, http") -- the first hop is the client's.
        return strtolower(trim(explode(',', $forwarded)[0])) === 'https';
    }
    return false;
}

/**
 * The URL prefix the app is served under: '' at a domain root (the hosted
 * deployment) and '/Financial-Management-System' under an htdocs subfolder.
 *
 * It must never be a bare '/', because every link is built as BASE_URL . '/path'
 * and '/' . '/path' produces '//path', which browsers read as the protocol-relative
 * URL https://path -- i.e. an entirely different host.
 */
$baseUrl = env_value('APP_BASE_URL');
if ($baseUrl === null) {
    $root = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
    $docRoot = str_replace(DIRECTORY_SEPARATOR, '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
    // No DOCUMENT_ROOT under CLI (seed/migrate scripts), and nothing to strip when
    // the app *is* the document root -- both mean "served from the root".
    $folder = ($docRoot !== '' && stripos($root, $docRoot) === 0) ? substr($root, strlen($docRoot)) : '';
    $baseUrl = trim($folder, '/');
    $baseUrl = $baseUrl === '' ? '' : '/' . $baseUrl;
}
define('BASE_URL', rtrim($baseUrl, '/'));

define('CURRENCY_SYMBOL', env_value('CURRENCY_SYMBOL', '₱'));
define('IDLE_TIMEOUT_SECONDS', (int)env_value('IDLE_TIMEOUT_SECONDS', '1800'));
