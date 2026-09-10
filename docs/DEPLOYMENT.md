# Deploying to HostForge

This guide covers deploying the Financial Management System to
`https://hostforgeplatform.cloud/`. Everything the platform's validator could not
auto-detect now has an answer in the repository; this file lists the exact values
to enter for the fields it still asks about.

---

## 1. Settings to enter on the Deployment Blueprint step

Open **Advanced settings** and set these. They match what the repository actually
contains, so re-running validation after a push should clear the blocking item and
the four "inferred" warnings.

| Field | Value | Why |
|---|---|---|
| **Build command** | `composer install --no-dev --optimize-autoloader` | The blocking item. This is exactly what the `Dockerfile` runs; `composer.json` now makes it detectable. If the field expects a container build instead, use `docker build -t financial-management-system .` |
| **Runtime** | `PHP 8.2` | Pinned by `FROM php:8.2-apache` in the `Dockerfile`, `.php-version`, and `composer.json`'s platform config |
| **Build system** | `Dockerfile` (Docker) | `Dockerfile` at the repository root is the build definition |
| **Build context** | `.` | Already correct |
| **Port** | `80` | Apache listens on 80 (`EXPOSE 80`). The entrypoint rebinds it if the platform injects a different `PORT` |
| **Health check path** | `/health` | Real endpoint, added in `health.php` and routed by `.htaccess` |
| **Framework** | Custom / PHP + Apache | Plain PHP with no framework — the custom build path is correct here |

## 2. Environment variables (required)

The application is useless without a MySQL database — **this is the one thing the
platform cannot supply from the repository.** Provision a MySQL 8.0 database
(or attach an external one) and set these under **Environment**:

| Variable | Example | Notes |
|---|---|---|
| `DB_HOST` | `mysql.internal` | Database hostname |
| `DB_PORT` | `3306` | |
| `DB_NAME` | `travelcore_fms` | Any name; no longer has to match `schema.sql` |
| `DB_USER` | `travelcore` | |
| `DB_PASS` | *(secret)* | Store as a secret, not plain text |
| `APP_ENV` | `production` | Hides PHP errors and closes the demo-data endpoint |
| `DB_AUTO_MIGRATE` | `true` | Creates the schema on first boot; a no-op afterwards |
| `DB_AUTO_SEED` | `false` | See the warning below |

Optional: `APP_BASE_URL` (leave unset — auto-detected), `APP_TIMEZONE`,
`IDLE_TIMEOUT_SECONDS`, `DB_WAIT_SECONDS`. Full list with defaults in
[`.env.example`](../.env.example).

> **Do not set `DB_AUTO_SEED=true` on a deployment that will hold real data.**
> The demo data ships four accounts whose password (`Passw0rd!`) is published in
> this repository. It is fine for a demo URL; it is an open door on anything else.
> If you do seed, change every password immediately after the first login.

## 3. First deploy

1. Push this branch to `main`.
2. Re-run validation — the build command should now be detected, with the runtime
   reported as PHP 8.2.
3. Set the environment variables above, with `DB_AUTO_MIGRATE=true`.
4. Deploy. On boot the container waits for the database (up to `DB_WAIT_SECONDS`,
   default 45), creates the 39 tables from `sql/schema.sql`, and starts Apache.

If the platform gives you shell access you can do the same thing by hand and skip
`DB_AUTO_MIGRATE`:

```sh
php scripts/migrate.php          # create the schema (no-op if it already exists)
php scripts/migrate.php --seed   # ... and load demo data
php scripts/migrate.php --fresh  # DESTRUCTIVE: drop every table and rebuild
```

## 4. Verifying the deployment

```sh
curl https://<your-app>.hostforgeplatform.cloud/health
```

`/health` is a **liveness** check: it returns 200 whenever PHP is serving, even if
the database is down, and reports the database state in the body. That is
deliberate — a health check that also fails on database trouble would make the
platform restart the container in a loop while the database is still starting.

```sh
curl "https://<your-app>.hostforgeplatform.cloud/health?deep=1"
```

`?deep=1` is the **readiness** form: HTTP 503 if the database is unreachable or the
schema is missing. Use it after deploying, not as the platform's configured probe.

A healthy response looks like:

```json
{
  "status": "ok",
  "checks": {
    "php": { "status": "ok" },
    "database": { "status": "ok", "tables": 39 },
    "session_storage": { "status": "ok" }
  }
}
```

If `database.status` is `error`, the connection variables are wrong or the database
is not reachable. If it is `degraded` with `tables: 0`, the schema was never
created — set `DB_AUTO_MIGRATE=true` and redeploy.

## 5. Verifying locally before you push

With Docker installed, `docker-compose.yml` builds the same image the platform
builds and runs MySQL alongside it:

```sh
docker compose up --build          # http://localhost:8080
docker compose logs -f app         # watch the entrypoint bootstrap the database
docker compose down -v             # tear down, including the database volume
```

The compose file sets `DB_AUTO_MIGRATE` and `DB_AUTO_SEED` to `true`, so the first
run comes up with the schema and demo data already in place.

## 6. What changed in the repository for deployment

- **`composer.json` / `.php-version`** — the framework and runtime markers the
  validator was missing. This is what makes the build command determinable.
- **`Dockerfile`** — now installs opcache, enables `rewrite`/`headers`/`remoteip`,
  applies production PHP settings, runs the Composer build, and declares a
  `HEALTHCHECK`.
- **`docker/entrypoint.sh`** — binds Apache to `$PORT`, waits for the database, and
  optionally creates the schema on first boot.
- **`health.php` + `.htaccess`** — a real health endpoint at `/health`, replacing
  the guessed `/` probe.
- **`scripts/migrate.php`** — applies `sql/schema.sql` to whatever database
  `DB_NAME` points at. The schema file hardcodes `CREATE DATABASE travelcore_fms`
  and `USE travelcore_fms`, which does not work on a hosted database; the script
  strips those statements.
- **`config/config.php`** — every deployment value now reads from the environment,
  and `BASE_URL` is correct at a domain root. Previously it evaluated to `/` there,
  so every link rendered as `//modules/...`, which a browser reads as the host
  `modules` — the whole navigation would have been broken on the hosted URL.
- **HTTPS awareness** — session cookies are marked `Secure` based on
  `X-Forwarded-Proto`, since the platform terminates TLS in front of the container.
- **`mod_remoteip`** — the audit log records the real client IP instead of the
  proxy's address.
- **Demo credentials** are no longer printed on the login page when
  `APP_ENV=production`.

## 7. After the first successful deploy

- Log in and change the password on every seeded account, or delete the ones you
  do not need (**Users** in the sidebar).
- Sessions are stored on the container's local disk, so a restart signs everyone
  out and running more than one replica will bounce users between them. Single
  replica is the supported configuration.
- Set up backups on the database — the ledger is the system of record and nothing
  in the container is durable.
