<?php
/**
 * Deployment health probe. Reachable as /health.php and, via the rewrite rules in
 * .htaccess, as /health.
 *
 * By default this is a LIVENESS check: it reports whether PHP is serving requests
 * and returns 200 even when the database is unreachable. That is deliberate -- a
 * platform health check that also fails on database trouble will kill and restart
 * the app container in a loop while the database is still being provisioned or is
 * briefly restarting, which turns a recoverable dependency blip into an outage.
 *
 * The database result is always reported in the body so it stays visible, and
 * /health?deep=1 turns it into a READINESS check (HTTP 503 when the database or
 * schema is not usable) for use during deployment verification.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$deep = isset($_GET['deep']) || isset($_GET['strict']);
$startedAt = microtime(true);

$report = [
    'status'      => 'ok',
    'app'         => APP_SHORT_NAME,
    'environment' => APP_ENV,
    'php'         => PHP_VERSION,
    'time'        => date('c'),
    'checks'      => [],
];

$report['checks']['php'] = ['status' => 'ok'];

// --- Database connectivity + schema presence ------------------------------
try {
    $db = get_db();
    $db->query('SELECT 1');
    $tables = (int)$db->query(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
    )->fetchColumn();

    $report['checks']['database'] = [
        'status' => $tables > 0 ? 'ok' : 'degraded',
        'host'   => DB_HOST . ':' . DB_PORT,
        'name'   => DB_NAME,
        'tables' => $tables,
    ];
    if ($tables === 0) {
        // Connected, but schema.sql was never imported -- the app will error on
        // every page, so surface it rather than reporting a clean bill of health.
        $report['checks']['database']['detail'] = 'Connected, but no tables found. Import sql/schema.sql (see scripts/migrate.php).';
    }
} catch (Throwable $e) {
    $report['checks']['database'] = [
        'status' => 'error',
        'host'   => DB_HOST . ':' . DB_PORT,
        'name'   => DB_NAME,
        // Driver messages can contain credentials/host details, so only expose them
        // outside production.
        'detail' => APP_ENV === 'production' ? 'Database connection failed.' : $e->getMessage(),
    ];
}

// --- Writable session storage ---------------------------------------------
$sessionPath = ini_get('session.save_path') ?: sys_get_temp_dir();
$report['checks']['session_storage'] = is_writable($sessionPath)
    ? ['status' => 'ok', 'path' => $sessionPath]
    : ['status' => 'error', 'path' => $sessionPath, 'detail' => 'Session save path is not writable.'];

$report['duration_ms'] = round((microtime(true) - $startedAt) * 1000, 1);

$failed = array_filter($report['checks'], fn($c) => $c['status'] !== 'ok');
if ($failed) {
    $report['status'] = 'degraded';
}

// Liveness always answers 200 unless PHP itself is broken (in which case this file
// never runs). Readiness reflects the dependencies.
$sessionBroken = $report['checks']['session_storage']['status'] === 'error';
http_response_code(($deep && $failed) || $sessionBroken ? 503 : 200);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
