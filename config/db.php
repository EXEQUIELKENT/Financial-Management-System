<?php
require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(db_dsn(DB_NAME), DB_USER, DB_PASS, db_pdo_options());
    }
    return $pdo;
}

function db_dsn(?string $database): string {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT;
    if ($database !== null && $database !== '') {
        $dsn .= ';dbname=' . $database;
    }
    return $dsn . ';charset=' . DB_CHARSET;
}

function db_pdo_options(): array {
    return [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        // A managed database can be a network hop away and may still be booting
        // when the app container starts; fail fast rather than hanging a worker.
        PDO::ATTR_TIMEOUT => (int)env_value('DB_TIMEOUT', '10'),
    ];
}
