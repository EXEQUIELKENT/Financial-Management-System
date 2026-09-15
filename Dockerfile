# Financial Management System - production image.
# Plain PHP + Apache serving the application from the document root.
#
# Build steps are kept few and cheap, because the platform's build step times out:
# PHP extensions come from prebuilt binaries rather than a from-source compile, and
# there is no Composer step at all. The one third-party library (PHPMailer, used to send
# password-reset codes) is vendored into lib/ and committed, so no package registry is
# contacted at build time. composer.json stays in the repository as an accurate manifest
# of the PHP version and extensions required, but nothing here needs to run Composer.

FROM php:8.2-apache

# APP_ENV=production keeps PHP errors out of responses (see config/config.php).
# PORT is the port Apache binds to; the entrypoint rewrites the Apache config from it.
ENV APP_ENV=production \
    PORT=80

# pdo_mysql is the only database driver the app uses (it is PDO throughout), and it
# pulls in pdo itself. opcache is the one meaningful performance win. Both are installed
# via mlocati/docker-php-extension-installer, which fetches prebuilt binaries instead of
# compiling from source — compiling opcache's JIT support from scratch was taking over
# 20 minutes in HostForge's build environment and timing out the deployment.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN set -eux; \
install-php-extensions pdo_mysql opcache; \
a2enmod rewrite headers remoteip expires

# Embedded MariaDB, started only when DB_EMBEDDED=true at runtime (docker/entrypoint.sh).
#
# This exists because HostForge's managed-database provisioning is stuck, and the
# platform deploys a single service -- it will not run this repository's
# docker-compose.yml. Running the database inside the application container is a
# workaround for that platform limitation, NOT a recommended topology: the data lives
# on the container filesystem, so it must be backed by a persistent volume mounted at
# /var/lib/mysql or every redeploy starts from an empty ledger. Move back to a separate
# database service once one is available.
#
# Only the two -core packages are installed. The full mariadb-server package also drags
# in Galera replication, systemd integration, init scripts, logrotate and the complete
# client suite -- none of which this container uses, and that weight is what pushed the
# build past the platform's 20-minute cap.
#
# The split is not obvious, so for the record: mariadb-server-core provides mariadbd and
# mariadb-install-db plus the bootstrap SQL under /usr/share/mysql, while
# mariadb-client-core provides my_print_defaults -- which mariadb-install-db shells out
# to and aborts without. Dropping that second package would leave the data directory
# uninitialized and the database silently absent.
#
# The mysql system user normally comes from mariadb-server's postinst, which is not
# installed here, so it is created explicitly: the entrypoint chowns the data directory
# to it and starts mariadbd --user=mysql.
#
# Docs and man pages are excluded -- nothing reads them here and they are pure I/O.
RUN set -eux; \
    echo 'exit 101' > /usr/sbin/policy-rc.d; \
    chmod +x /usr/sbin/policy-rc.d; \
    apt-get update; \
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        -o Dpkg::Options::="--path-exclude=/usr/share/doc/*" \
        -o Dpkg::Options::="--path-exclude=/usr/share/man/*" \
        mariadb-server-core \
        mariadb-client-core; \
    rm -rf /var/lib/apt/lists/*; \
    getent group mysql >/dev/null || groupadd -r mysql; \
    getent passwd mysql >/dev/null || useradd -r -g mysql -d /var/lib/mysql -s /usr/sbin/nologin mysql

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
COPY . /var/www/html/

RUN set -eux; \
    mkdir -p /var/www/sessions; \
    chown -R www-data:www-data /var/www/html /var/www/sessions; \
    chmod 1733 /var/www/sessions

EXPOSE 80

# Liveness only: /health answers 200 while the database is still coming up and reports
# the database state in its body, so a database blip cannot crash-loop the container.
# The probe uses PHP's own sockets, which is why this image needs no curl.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php /var/www/html/scripts/healthcheck.php /health || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
