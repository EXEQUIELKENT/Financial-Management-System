<?php
/**
 * Creates the database schema on a fresh install.
 *
 * sql/schema.sql is written for phpMyAdmin/XAMPP: it hardcodes `CREATE DATABASE
 * travelcore_fms` and `USE travelcore_fms`, which is wrong on a hosted platform
 * where the database name is assigned for you (DB_NAME). This script strips those
 * statements and runs the rest against whatever database the environment points at,
 * so the same schema file serves both.
 *
 * Usage:
 *   php scripts/migrate.php            # create the schema if the database is empty
 *   php scripts/migrate.php --seed     # also load demo data afterwards
 *   php scripts/migrate.php --fresh    # DESTRUCTIVE: drop every table, then rebuild
 *
 * CLI only. On a platform with no shell access set DB_AUTO_MIGRATE=true instead, and
 * the container entrypoint runs this on every boot (a no-op once the schema exists).
 */
require_once __DIR__ . '/../config/db.php';

if (PHP_SAPI !== 'cli') {
    // Schema management is never exposed over HTTP: there is no safe way to
    // authenticate it here, and DB_AUTO_MIGRATE already covers hosts without a shell.
    http_response_code(404);
    exit("Not found.\n");
}

$argv = $argv ?? [];
// --fresh destroys data, so it must always be typed out explicitly.
$fresh = in_array('--fresh', $argv, true);
$withSeed = in_array('--seed', $argv, true);

function say(string $msg): void {
    echo $msg . "\n";
}

/**
 * Splits a SQL script into individual statements, honouring quoted strings,
 * backtick identifiers and comments so a semicolon inside a value can't cut a
 * statement in half.
 */
function split_sql(string $sql): array {
    $statements = [];
    $current = '';
    $len = strlen($sql);
    $quote = null;          // active quote char, or null outside a literal
    $lineComment = false;
    $blockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($lineComment) {
            if ($char === "\n") { $lineComment = false; $current .= $char; }
            continue;
        }
        if ($blockComment) {
            if ($char === '*' && $next === '/') { $blockComment = false; $i++; }
            continue;
        }
        if ($quote === null) {
            if (($char === '-' && $next === '-') || $char === '#') { $lineComment = true; continue; }
            if ($char === '/' && $next === '*') { $blockComment = true; $i++; continue; }
            if ($char === "'" || $char === '"' || $char === '`') { $quote = $char; }
            elseif ($char === ';') {
                if (trim($current) !== '') { $statements[] = trim($current); }
                $current = '';
                continue;
            }
        } else {
            // Backslash escape inside a string literal (MySQL default mode).
            if ($char === chr(92) && $quote !== '`') { $current .= $char . $next; $i++; continue; }
            if ($char === $quote) {
                // A doubled quote is an escaped quote, not the end of the literal.
                if ($next === $quote) { $current .= $char . $next; $i++; continue; }
                $quote = null;
            }
        }
        $current .= $char;
    }
    if (trim($current) !== '') { $statements[] = trim($current); }
    return $statements;
}

$schemaFile = __DIR__ . '/../sql/schema.sql';
if (!is_readable($schemaFile)) {
    say('ERROR: cannot read ' . $schemaFile);
    exit(1);
}

say('Target: ' . DB_USER . '@' . DB_HOST . ':' . DB_PORT . '/' . DB_NAME);

// --- 1. Make sure the database exists -------------------------------------
try {
    $server = new PDO(db_dsn(null), DB_USER, DB_PASS, db_pdo_options());
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', DB_NAME) . '`'
        . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    say('Database is present.');
} catch (PDOException $e) {
    // Managed databases are usually pre-created and deny CREATE DATABASE. That is
    // fine -- only fail if we also cannot connect to the database itself, below.
    say('Note: could not create the database (' . $e->getMessage() . '). Assuming it already exists.');
}

// --- 2. Connect to it -----------------------------------------------------
try {
    $db = get_db();
} catch (PDOException $e) {
    say('ERROR: cannot connect to database "' . DB_NAME . '": ' . $e->getMessage());
    say('Check the DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS environment variables.');
    exit(1);
}

$existing = (int)$db->query(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
)->fetchColumn();

if ($existing > 0 && !$fresh) {
    // schema.sql contains no DROP statements, so re-applying it over an existing schema
    // would just fail on the first CREATE TABLE. Stopping here keeps this script safe
    // to run unconditionally on every deploy.
    say("Schema already present ($existing tables) - nothing to do.");
    say('Use --fresh to drop every table and rebuild (this destroys all data).');
    exit(0);
}

if ($fresh && $existing > 0) {
    say("--fresh: dropping $existing existing tables ...");
    $names = $db->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()')
                ->fetchAll(PDO::FETCH_COLUMN);
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($names as $name) {
        $db->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', $name) . '`');
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    say('Dropped.');
}

// --- 3. Apply the schema --------------------------------------------------
$sql = file_get_contents($schemaFile);
// Drop the XAMPP-only database selection; the connection is already scoped to DB_NAME.
$sql = preg_replace('/^\s*CREATE\s+DATABASE\b[^;]*;/im', '', $sql);
$sql = preg_replace('/^\s*USE\s+[^;]*;/im', '', $sql);

$statements = split_sql($sql);
say('Applying ' . count($statements) . ' statements from sql/schema.sql ...');

$applied = 0;
foreach ($statements as $index => $statement) {
    try {
        $db->exec($statement);
        $applied++;
    } catch (PDOException $e) {
        say('ERROR on statement #' . ($index + 1) . ': ' . $e->getMessage());
        say('---- statement ----');
        say(substr($statement, 0, 500));
        exit(1);
    }
}

$tables = (int)$db->query(
    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
)->fetchColumn();
say("Done. $applied statements applied, $tables tables now present.");

// --- 4. Optional demo data ------------------------------------------------
if ($withSeed) {
    say('Loading demo data ...');
    require __DIR__ . '/../sql/seed.php';
}
