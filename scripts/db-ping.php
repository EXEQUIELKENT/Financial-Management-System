<?php
/**
 * Minimal "is the database accepting connections yet?" probe, used by the container
 * entrypoint to wait out a database that is still starting. Exits 0 on success and 1
 * on failure, and prints nothing on the happy path.
 */
require_once __DIR__ . '/../config/db.php';

try {
    get_db()->query('SELECT 1');
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
