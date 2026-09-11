# Deploying to HostForge

This guide covers deploying the Financial Management System to
`https://hostforgeplatform.cloud/`.

---

## 1. Settings to enter on the Deployment Blueprint step

Open **Advanced settings** and set these.

| Field | Value | Why |
|---|---|---|
| **Build command** | *leave empty* (see below) | The `Dockerfile` **is** the build. Nothing needs to run outside it |
| **Runtime** | `PHP 8.2` | Pinned by `FROM php:8.2-apache`, `.php-version` and `composer.json` |
| **Build system** | `Dockerfile` (Docker) | `Dockerfile` at the repository root |
| **Build context** | `.` | |
| **Port** | `80` | Apache listens on 80 (`EXPOSE 80`). The entrypoint rebinds it if the platform injects a different `PORT` |
| **Health check path** | `/health` | Must be entered by hand - the platform reports it cannot infer a path from a Dockerfile |
| **Framework** | Custom / PHP + Apache | Plain PHP, no framework |

### About the build command

An earlier version of this guide suggested `composer install --no-dev --optimize-autoloader`
here. **Do not use that.** The image no longer runs Composer at all, and if the
platform executes that string on a build host without PHP and Composer installed,
the build fails before Docker is ever invoked.

This application has no third-party dependencies - `composer.json` exists to declare
the PHP version and extensions it needs, not to install anything. If the field
refuses to stay empty, give it something that succeeds anywhere:

```sh
echo "Build is defined by the Dockerfile"
```

## 2. The database

The attached managed service is **MariaDB**, and the platform currently reports it as
**failed**. Nothing in this repository can fix that - reprovision or restart the
service from the HostForge console first. The application cannot start without it.

MariaDB is fully supported: `sql/schema.sql` is plain InnoDB with
`utf8mb4_unicode_ci` and uses no MySQL-8-only syntax (no window functions, CTEs, JSON
columns, generated columns or MySQL-8 default collations), so it runs unchanged on
either engine.

Once the service is healthy, set these under **Environment**:

| Variable | Example | Notes |
|---|---|---|
| `DB_HOST` | *(from the service)* | MariaDB hostname |
| `DB_PORT` | `3306` | |
| `DB_NAME` | `travelcore_fms` | Any name; no longer has to match `schema.sql` |
| `DB_USER` | `travelcore` | |
| `DB_PASS` | *(secret)* | Store as a secret, not plain text |
| `APP_ENV` | `production` | Hides PHP errors and closes the demo-data endpoint |
| `DB_AUTO_MIGRATE` | `true` | Creates the schema on first boot; a no-op afterwards |
| `DB_AUTO_SEED` | `false` | See the warning below |

Optional: `APP_BASE_URL` (leave unset - auto-detected), `APP_TIMEZONE`,
`IDLE_TIMEOUT_SECONDS`, `DB_WAIT_SECONDS`. Full list with defaults in
[`.env.example`](../.env.example).

> **Do not set `DB_AUTO_SEED=true` on a deployment that will hold real data.**
> The demo data ships four accounts whose password (`Passw0rd!`) is published in
> this repository. It is fine for a demo URL; it is an open door on anything else.

## 3. What the image does and does not need

The build depends on **nothing but the base image**. There is no apt layer, no
Composer step, no second image pull and no package-registry access, because the
application has no third-party runtime dependencies. If a build still fails, the
cause is in the platform's build environment or its build command - not in a
dependency download.

At runtime the container needs exactly one thing: a reachable MariaDB/MySQL.

## 4. First deploy

1. Confirm the MariaDB service is healthy.
2. Set the environment variables above, with `DB_AUTO_MIGRATE=true`.
3. Set the health check path to `/health`.
4. Deploy. On boot the container waits for the database (up to `DB_WAIT_SECONDS`,
   default 45), creates the 39 tables from `sql/schema.sql`, then starts Apache.

With shell access you can do the same by hand and skip `DB_AUTO_MIGRATE`:

```sh
php scripts/migrate.php          # create the schema (no-op if it already exists)
php scripts/migrate.php --seed   # ... and load demo data
php scripts/migrate.php --fresh  # DESTRUCTIVE: drop every table and rebuild
```

## 5. Verifying the deployment

```sh
curl https://<your-app>.hostforgeplatform.cloud/health
```

`/health` is a **liveness** check: it returns 200 whenever PHP is serving, even if
the database is down, and reports the database state in the body. That is
deliberate - a health check that also failed on database trouble would make the
platform restart the container in a loop while the database is still starting.

`/health?deep=1` is the **readiness** form: HTTP 503 if the database is unreachable
or the schema is missing. Use it to verify a deploy, not as the configured probe.

If `database.status` is `error`, the connection variables are wrong or MariaDB is not
reachable. If it is `degraded` with `tables: 0`, the schema was never created - set
`DB_AUTO_MIGRATE=true` and redeploy.

## 6. Verifying locally before you push

`docker-compose.yml` builds the same image the platform builds and runs MariaDB
alongside it:

```sh
docker compose up --build          # http://localhost:8080
docker compose logs -f app         # watch the entrypoint bootstrap the database
docker compose down -v             # tear down, including the database volume
```

The compose file sets `DB_AUTO_MIGRATE` and `DB_AUTO_SEED` to `true`, so the first
run comes up with the schema and demo data already in place.

## 7. Scaling and SSL

**Do not enable autoscaling as it stands.** Sessions are stored on the container's
local disk, so a second replica would sign users out at random as requests land on
different instances, and a restart signs everyone out. Single replica is the
supported configuration. Moving sessions into the database would lift that
restriction; it is not implemented.

**SSL**: a certificate is normally issued only after a custom domain resolves to the
application *and* a release has succeeded. Expect the SSL warning to clear once the
first deploy completes; if it does not, check the domain's DNS records.

## 8. After the first successful deploy

- Log in and change the password on every seeded account, or delete the ones you do
  not need (**Users** in the sidebar).
- Set up backups on the database - the ledger is the system of record and nothing in
  the container is durable.

## 9. What this repository provides for deployment

- **`Dockerfile`** - PHP 8.2 + Apache, `pdo_mysql` and `opcache`, production PHP
  settings, `rewrite`/`headers`/`remoteip`/`expires` enabled, and a `HEALTHCHECK`.
  No network access needed beyond pulling the base image.
- **`composer.json` / `.php-version`** - the runtime markers the validator needs,
  declaring PHP >=8.1 and the required extensions. Nothing installs from them.
- **`docker/entrypoint.sh`** - binds Apache to `$PORT`, waits for the database, and
  optionally creates the schema on first boot.
- **`health.php` + `.htaccess`** - a real health endpoint at `/health`.
- **`scripts/migrate.php`** - applies `sql/schema.sql` to whatever `DB_NAME` points
  at. The schema file hardcodes `CREATE DATABASE travelcore_fms` and `USE
  travelcore_fms`, which cannot work on a managed database; the script strips those.
- **`scripts/healthcheck.php`** - the container's own liveness probe, over a plain
  socket so the image needs no curl.
- **`config/config.php`** - every deployment value reads from the environment, and
  `BASE_URL` is correct at a domain root. It previously evaluated to `/` there, so
  every link rendered as `//modules/...`, which a browser reads as the host
  `modules` - navigation would have been broken on the hosted URL.
- **HTTPS awareness** - session cookies are marked `Secure` from `X-Forwarded-Proto`,
  since the platform terminates TLS in front of the container.
- **`mod_remoteip`** - the audit log records the real client IP, not the proxy's.
- Demo credentials are not printed on the login page when `APP_ENV=production`.
