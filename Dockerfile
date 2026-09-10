# syntax=docker/dockerfile:1
#
# Financial Management System - production image.
# Plain PHP + Apache serving the application from the document root.

FROM php:8.2-apache

# APP_ENV=production keeps PHP errors out of responses (see config/config.php).
# PORT is the port Apache binds to; the entrypoint rewrites the Apache config from it.
ENV APP_ENV=production \
    PORT=80 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        curl \
        unzip \
        default-mysql-client; \
    rm -rf /var/lib/apt/lists/*; \
    docker-php-ext-install -j"$(nproc)" pdo pdo_mysql mysqli opcache; \
    a2enmod rewrite headers remoteip expires

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# The whole tree is copied before `composer install` because the autoloader is a
# classmap over includes/, which has to exist for the optimized autoloader to be built.
COPY . /var/www/html/

RUN set -eux; \
    composer install --no-dev --optimize-autoloader --no-progress; \
    mkdir -p /var/www/sessions; \
    chown -R www-data:www-data /var/www/html /var/www/sessions; \
    chmod 1733 /var/www/sessions

EXPOSE 80

# Liveness only: /health.php answers 200 while the database is still coming up, and
# reports the database state in its body. See health.php for why.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT}/health.php" >/dev/null || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
