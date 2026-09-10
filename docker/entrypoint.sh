#!/bin/sh
#
# Container entrypoint.
#
#   1. Binds Apache to $PORT (platforms often inject their own port).
#   2. Optionally waits for the database and creates the schema on first boot.
#   3. Hands off to the CMD (apache2-foreground).
#
set -eu

log() { printf '[entrypoint] %s\n' "$1"; }

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

# --- 2. Database bootstrap (opt-in) ---------------------------------------
is_true() {
    case "$(printf '%s' "${1:-}" | tr '[:upper:]' '[:lower:]')" in
        1|true|yes|on) return 0 ;;
        *) return 1 ;;
    esac
}

if is_true "${DB_WAIT:-true}"; then
    DB_WAIT_SECONDS="${DB_WAIT_SECONDS:-45}"
    log "Waiting up to ${DB_WAIT_SECONDS}s for the database ..."
    waited=0
    while [ "$waited" -lt "$DB_WAIT_SECONDS" ]; do
        if php /var/www/html/scripts/db-ping.php >/dev/null 2>&1; then
            log "Database is reachable."
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
    log "DB_AUTO_MIGRATE is on - ensuring the schema exists ..."
    if php /var/www/html/scripts/migrate.php; then
        log "Schema check complete."
    else
        log "Migration failed; continuing so /health can report the problem."
    fi
fi

if is_true "${DB_AUTO_SEED:-false}"; then
    log "DB_AUTO_SEED is on - loading demo data if the database is empty ..."
    php /var/www/html/sql/seed.php || log "Seeding failed; continuing."
fi

log "Starting: $*"
exec "$@"
