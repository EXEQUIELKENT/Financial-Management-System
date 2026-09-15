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

HostForge's managed-database provisioning is currently stuck, and the platform deploys
a **single service** - it will not run this repository's `docker-compose.yml`
("HostForge deploys a single service and does not run compose files"). Until a managed
database is available again, the app can run MariaDB **inside its own container**.

MariaDB is fully supported either way: `sql/schema.sql` is plain InnoDB with
`utf8mb4_unicode_ci` and uses no MySQL-8-only syntax (no window functions, CTEs, JSON
columns, generated columns or MySQL-8 default collations).

### Option A - embedded database (the current workaround)

Set `DB_EMBEDDED=true` and the entrypoint starts a local MariaDB, provisions
`DB_NAME`/`DB_USER`/`DB_PASS` on it, then runs the normal migrate/seed steps.

| Variable | Value |
|---|---|
| `DB_EMBEDDED` | `true` |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3306` |
| `DB_NAME` | `travelcore_fms` |
| `DB_USER` | `travelcore` |
| `DB_PASS` | *(choose one; stored as a secret)* |
| `APP_ENV` | `production` |
| `DB_AUTO_MIGRATE` | `true` |
| `DB_AUTO_SEED` | `true` for a demo, `false` for real data |

> ### Read this before using it for anything real
>
> **The database lives on the container filesystem.** Mount a persistent volume at
> `/var/lib/mysql`, or **every redeploy starts from an empty ledger** - the container
> filesystem is discarded when the container is replaced. This matters more here than
> in most apps: the general ledger is the system of record.
>
> Also note that this is one container running two services. It rules out autoscaling
> entirely (see section 7), it means a database restart takes the web app down with it,
> and backups are your responsibility.
>
> This is a workaround for a platform outage, not a target architecture. Move back to
> Option B once HostForge's database service works.

The entrypoint shuts MariaDB down cleanly when the platform stops the container, so a
redeploy does not leave InnoDB to crash-recover on the next boot.

### Option B - separate database service (preferred)

Point the same variables at a real database and leave `DB_EMBEDDED` unset:

| Variable | Example |
|---|---|
| `DB_HOST` | *(hostname from the service)* |
| `DB_PORT` | `3306` |
| `DB_NAME` | `travelcore_fms` |
| `DB_USER` | `travelcore` |
| `DB_PASS` | *(secret)* |

Nothing else changes - `DB_AUTO_MIGRATE=true` creates the schema on either.

Optional everywhere: `APP_BASE_URL` (leave unset - auto-detected), `APP_TIMEZONE`,
`IDLE_TIMEOUT_SECONDS`, `DB_WAIT_SECONDS`, `DB_EMBEDDED_BUFFER_POOL`, `SHOW_GUIDES`.
Full list in [`.env.example`](../.env.example).

### Guided tours and the Getting Started page

These are onboarding aids, and `APP_ENV=production` hides all of them: the sidebar
entry, the per-page "?" button and its overlay, the `page-guide.js` asset, and the
Getting Started page itself (a bookmarked or typed URL redirects to the dashboard).
No separate setting is needed for a normal deployment. Set `SHOW_GUIDES=true` to keep
them on a deployment where they are wanted, such as a training environment.

> **`DB_AUTO_SEED=true` publishes a known admin password.** The demo data ships four
> accounts whose password (`Passw0rd!`) is in this repository. Fine for a demo URL; an
> open door on anything holding real data. Change or delete them after first login.

## 3. Build cost

HostForge kills a build at 20 minutes, and this project has hit that cap twice:

1. **Compiling PHP extensions from source.** `docker-php-ext-install opcache` alone ran
   past 20 minutes. Extensions now come from prebuilt binaries via
   `mlocati/docker-php-extension-installer`.
2. **Installing the full `mariadb-server` package.** It pulls in Galera replication,
   systemd integration, init scripts, logrotate and the complete client suite. The image
   now installs only `mariadb-server-core` (which provides `mariadbd`,
   `mariadb-install-db` and the bootstrap SQL) and `mariadb-client-core` (which provides
   `my_print_defaults`, without which `mariadb-install-db` aborts). Docs and man pages
   are excluded from extraction.

There is no Composer step - the application has no third-party PHP dependencies, so
`composer install` would install nothing.

If a future change puts the build back over the cap, the next thing to cut is `opcache`.
It is a performance optimization, not a requirement: the app runs correctly without it,
and the `opcache.*` settings in `docker/php.ini` are simply ignored when the extension
is absent. Dropping it from the `install-php-extensions` line is a one-word change.

## 4. First deploy

1. Set the environment variables from section 2 (Option A or B), with
   `DB_AUTO_MIGRATE=true`.
2. Set the health check path to `/health`.
3. With `DB_EMBEDDED=true`, attach a persistent volume at `/var/lib/mysql` unless you
   are happy to lose the data on every redeploy.
4. Deploy. On boot the container starts the embedded database if enabled, waits for the
   database (up to `DB_WAIT_SECONDS`, default 45), creates the 39 tables from
   `sql/schema.sql`, seeds if asked, then starts Apache.

The container logs every step with a `[entrypoint]` prefix, so the boot sequence is
visible in the platform's log viewer.

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

**Do not enable autoscaling as it stands.** Two separate things rule it out:

- Sessions are stored on the container's local disk, so a second replica would sign
  users out at random as requests land on different instances. Moving sessions into
  the database would lift this; it is not implemented.
- With `DB_EMBEDDED=true`, each replica would run its **own private database**. Two
  replicas means two divergent sets of books. This is not a degraded experience, it is
  data corruption.

Single replica is the supported configuration.

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
- **`scripts/embedded-db-bootstrap.php`** - creates the database and user on the
  embedded MariaDB. In PHP rather than shell so identifiers and passwords are quoted
  properly instead of being escaped through a shell heredoc.
- **`docker/entrypoint.sh`** supervises both processes when the embedded database is
  on, forwarding the platform's stop signal to Apache and then shutting MariaDB down
  cleanly; with an external database it still `exec`s Apache as PID 1.
- **`config/config.php`** - every deployment value reads from the environment, and
  `BASE_URL` is correct at a domain root. It previously evaluated to `/` there, so
  every link rendered as `//modules/...`, which a browser reads as the host
  `modules` - navigation would have been broken on the hosted URL.
- **HTTPS awareness** - session cookies are marked `Secure` from `X-Forwarded-Proto`,
  since the platform terminates TLS in front of the container.
- **`mod_remoteip`** - the audit log records the real client IP, not the proxy's.
- Demo credentials are not printed on the login page when `APP_ENV=production`.
