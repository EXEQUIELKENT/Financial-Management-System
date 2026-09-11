<?php
/**
 * Provisions the application database and user inside the container-local MariaDB that
 * docker/entrypoint.sh starts when DB_EMBEDDED=true.
 *
 * Connects as root over the unix socket -- root authenticates via the socket, so no
 * root password has to be stored or generated anywhere -- then creates DB_NAME and
 * DB_USER/DB_PASS if they are not already present.
 *
 * This is PHP rather than shell so that identifier and string quoting happens once, in
 * one place. A database name or password containing a backtick or quote would otherwise
 * have to survive two layers of shell escaping inside a heredoc, which is exactly the
 * kind of thing that works until someone picks a password with a quote in it.
 */
require_once __DIR__ . '/../config/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

function say(string $msg): void {
    echo '[db-bootstrap] ' . $msg . "\n";
}

/** Backtick-quotes an identifier, doubling any backtick inside it. */
function quote_ident(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}

$socket  = getenv('DB_EMBEDDED_SOCKET') ?: '/run/mysqld/mysqld.sock';
$timeout = (int)(getenv('DB_EMBEDDED_TIMEOUT') ?: 60);

// mariadbd was started moments ago by the entrypoint and may still be doing crash
// recovery, so keep retrying rather than failing on the first refused connection.
$deadline  = time() + $timeout;
$root      = null;
$lastError = 'no attempt made';

while (time() < $deadline) {
    try {
        $root = new PDO('mysql:unix_socket=' . $socket, 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        break;
    } catch (PDOException $e) {
        $lastError = $e->getMessage();
        sleep(2);
    }
}

if ($root === null) {
    say("MariaDB did not accept a root connection on $socket within {$timeout}s.");
    say('Last error: ' . $lastError);
    exit(1);
}

$name = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;

if ($pass === '') {
    say('WARNING: DB_PASS is empty, so the application database user has no password.');
}

try {
    $root->exec('CREATE DATABASE IF NOT EXISTS ' . quote_ident($name)
        . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    // The app connects over TCP to 127.0.0.1, but granting localhost and the loopback
    // address too means DB_HOST can be written either way without silently failing --
    // PDO uses the unix socket for "localhost" and TCP for "127.0.0.1", and those are
    // two different host entries as far as the grant tables are concerned.
    foreach (['%', 'localhost', '127.0.0.1'] as $host) {
        $u = $root->quote($user);
        $h = $root->quote($host);
        $p = $root->quote($pass);
        $root->exec("CREATE USER IF NOT EXISTS $u@$h IDENTIFIED BY $p");
        // Re-apply the password so that changing DB_PASS and redeploying takes effect
        // instead of silently keeping the old credentials.
        $root->exec("ALTER USER $u@$h IDENTIFIED BY $p");
        $root->exec('GRANT ALL PRIVILEGES ON ' . quote_ident($name) . ".* TO $u@$h");
    }
    $root->exec('FLUSH PRIVILEGES');
} catch (PDOException $e) {
    say('Provisioning failed: ' . $e->getMessage());
    exit(1);
}

say("Database " . $name . " and user " . $user . " are ready on the embedded server.");
