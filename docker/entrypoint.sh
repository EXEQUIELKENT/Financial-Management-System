#!/bin/sh
#
# Container entrypoint.
#
#   1. Binds Apache to $PORT (platforms often inject their own port).
#   2. Optionally starts a MariaDB server inside this container (DB_EMBEDDED=true).
#   3. Optionally waits for the database and creates the schema on first boot.
#   4. Hands off to the CMD (apache2-foreground).
#
set -eu

log() { printf '[entrypoint] %s\n' "$1"; }

is_true() {
    case "$(printf '%s' "${1:-}" | tr '[:upper:]' '[:lower:]')" in
        1|true|yes|on) return 0 ;;
        *) return 1 ;;
    esac
}

# --- 1. Listen port --------------------------------------------------------
PORT="${PORT:-80}"
case "$PORT" in
    ''|*[!0-9]*) log "PORT='$PORT' is not a number; falling back to 80."; PORT=80 ;;
esac

sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
log "Apache will listen on port ${PORT}."

# Sessions live on a writable volume-friendly path; recreate it if a mount replaced it.
mkdir -p /var/www/sessions
chown www-data:www-data /var/www/sessions
chmod 1733 /var/www/sessions

# --- 2. Embedded MariaDB (opt-in) -----------------------------------------
# Only for environments where no separate database service is available. See the
# comment above the mariadb-server install in the Dockerfile for why this exists.
MARIADB_PID=''

start_embedded_mariadb() {
    log 'DB_EMBEDDED is on - starting MariaDB inside this container.'

    case "${DB_HOST:-}" in
        ''|127.0.0.1|localhost) ;;
        *) log "WARNING: DB_EMBEDDED is on but DB_HOST is '${DB_HOST}', which is not this container. Set DB_HOST=127.0.0.1." ;;
    esac

    mkdir -p /var/lib/mysql /run/mysqld
    chown -R mysql:mysql /var/lib/mysql /run/mysqld

    if [ ! -d /var/lib/mysql/mysql ]; then
        log 'Initializing the MariaDB data directory ...'
        if command -v mariadb-install-db >/dev/null 2>&1; then
            mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null 2>&1 \
                || log 'mariadb-install-db reported an error; continuing.'
        else
            mysql_install_db --user=mysql --datadir=/var/lib/mysql >/dev/null 2>&1 \
                || log 'mysql_install_db reported an error; continuing.'
        fi
    fi

    mariadbd_bin="$(command -v mariadbd || command -v mysqld || echo /usr/sbin/mariadbd)"

    # This server shares a container with Apache and PHP, so keep its footprint small.
    # The stock 128M buffer pool plus performance_schema is a lot of resident memory to
    # spend on a box that also has to serve the application.
    "$mariadbd_bin" \
        --user=mysql \
        --datadir=/var/lib/mysql \
        --bind-address=127.0.0.1 \
        --skip-name-resolve \
        --performance-schema=OFF \
        --innodb-buffer-pool-size="${DB_EMBEDDED_BUFFER_POOL:-64M}" &
    MARIADB_PID=$!
    log "MariaDB started (pid ${MARIADB_PID}); provisioning the application database ..."

    if php /var/www/html/scripts/embedded-db-bootstrap.php; then
        log 'Embedded MariaDB is ready on 127.0.0.1:3306.'
    else
        log 'Embedded MariaDB provisioning failed; continuing so /health can report it.'
    fi
}

stop_embedded_mariadb() {
    [ -n "$MARIADB_PID" ] || return 0
    log 'Shutting MariaDB down cleanly ...'
    kill -TERM "$MARIADB_PID" 2>/dev/null || true
    wait "$MARIADB_PID" 2>/dev/null || true
    log 'MariaDB stopped.'
}

if is_true "${DB_EMBEDDED:-false}"; then
    start_embedded_mariadb
fi

# --- 3. Database bootstrap (opt-in) ---------------------------------------
if is_true "${DB_WAIT:-true}"; then
    DB_WAIT_SECONDS="${DB_WAIT_SECONDS:-45}"
    log "Waiting up to ${DB_WAIT_SECONDS}s for the database ..."
    waited=0
    while [ "$waited" -lt "$DB_WAIT_SECONDS" ]; do
        if php /var/www/html/scripts/db-ping.php >/dev/null 2>&1; then
            log 'Database is reachable.'
            break
        fi
        waited=$((waited + 2))
        sleep 2
    done
    if [ "$waited" -ge "$DB_WAIT_SECONDS" ]; then
        # Not fatal: the app still needs to serve /health so the platform can report
        # *why* it is unhealthy, instead of the container crash-looping silently.
        log "Database still unreachable after ${DB_WAIT_SECONDS}s; starting anyway (see /health)."
    fi
fi

if is_true "${DB_AUTO_MIGRATE:-false}"; then
    log 'DB_AUTO_MIGRATE is on - ensuring the schema exists ...'
    if php /var/www/html/scripts/migrate.php; then
        log 'Schema check complete.'
    else
        log 'Migration failed; continuing so /health can report the problem.'
    fi
fi

if is_true "${DB_AUTO_SEED:-false}"; then
    log 'DB_AUTO_SEED is on - loading demo data if the database is empty ...'
    php /var/www/html/sql/seed.php || log 'Seeding failed; continuing.'
fi

# --- 4. Hand off to the CMD -----------------------------------------------
log "Starting: $*"

if [ -z "$MARIADB_PID" ]; then
    # Nothing else to supervise: let Apache replace this shell so it is PID 1 and
    # receives the platform's stop signals directly.
    exec "$@"
fi

# With an embedded database there are two processes to bring down. Staying alive as
# PID 1 lets us forward the stop signal to Apache and then shut MariaDB down cleanly --
# otherwise the database is SIGKILLed on every redeploy and has to crash-recover.
APP_PID=''
on_term() {
    log 'Stop signal received.'
    [ -n "$APP_PID" ] && kill -TERM "$APP_PID" 2>/dev/null || true
    [ -n "$APP_PID" ] && wait "$APP_PID" 2>/dev/null || true
    stop_embedded_mariadb
    exit 0
}
trap on_term TERM INT

"$@" &
APP_PID=$!
wait "$APP_PID" 2>/dev/null || true

log 'Apache exited.'
stop_embedded_mariadb
